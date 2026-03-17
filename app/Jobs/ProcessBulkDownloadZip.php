<?php

namespace App\Jobs;

use App\Models\TteRequest;
use App\Support\BulkRedisState;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class ProcessBulkDownloadZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    private const MAX_FILES = 100;

    public function __construct(
        public string $bulkDownloadId,
        public array $filter
    ) {}

    public function handle(): void
    {
        $this->mutateState(function (array &$state): void {
            $state['status'] = 'running';
            $state['started_at'] = now()->toISOString();
            $state['finished_at'] = null;
            $state['total_file'] = 0;
            $state['processed'] = 0;
            $state['success'] = 0;
            $state['failed'] = 0;
            $state['summary'] = [];
            $state['errors'] = [];
            $state['results'] = [];
            $state['zip_path'] = null;
        });

        $query = $this->buildFilteredQuery($this->filter);
        $query->orderBy($this->filter['sort'] ?? 'created_at', $this->filter['dir'] ?? 'desc');
        $rows = $query->limit(self::MAX_FILES)->get();

        $this->mutateState(function (array &$state) use ($rows): void {
            $state['total_file'] = $rows->count();
        });

        $zipDir = Storage::disk('local')->path('bulk-download-zips');
        if (! is_dir($zipDir)) {
            mkdir($zipDir, 0777, true);
        }

        $zipName = 'bulk_download_'.$this->bulkDownloadId.'_'.now()->format('Ymd_His').'.zip';
        $zipFullPath = $zipDir.DIRECTORY_SEPARATOR.$zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat file ZIP.');
        }

        $usedNames = [];
        $errorLog = [];
        $resultLog = [];

        try {
            foreach ($rows as $row) {
                $sourcePath = $row->file_req;
                if (! $sourcePath || ! Storage::disk('s3')->exists($sourcePath)) {
                    $error = [
                        'token' => $row->token,
                        'file' => $sourcePath,
                        'reason' => 'file_tidak_ditemukan',
                    ];
                    $errorLog[] = $error;

                    $this->mutateState(function (array &$state) use ($error, $errorLog): void {
                        $state['processed'] = (int) ($state['processed'] ?? 0) + 1;
                        $state['failed'] = (int) ($state['failed'] ?? 0) + 1;
                        $state['errors'] = $errorLog;
                    });
                    continue;
                }

                $filename = basename($sourcePath);
                $filenameInZip = $this->makeUniqueFilename($filename, $usedNames);

                $stream = Storage::disk('s3')->readStream($sourcePath);
                if (! is_resource($stream)) {
                    $error = [
                        'token' => $row->token,
                        'file' => $sourcePath,
                        'reason' => 'gagal_baca_stream',
                    ];
                    $errorLog[] = $error;

                    $this->mutateState(function (array &$state) use ($errorLog): void {
                        $state['processed'] = (int) ($state['processed'] ?? 0) + 1;
                        $state['failed'] = (int) ($state['failed'] ?? 0) + 1;
                        $state['errors'] = $errorLog;
                    });
                    continue;
                }

                $content = stream_get_contents($stream);
                fclose($stream);

                if ($content === false) {
                    $error = [
                        'token' => $row->token,
                        'file' => $sourcePath,
                        'reason' => 'gagal_baca_konten',
                    ];
                    $errorLog[] = $error;

                    $this->mutateState(function (array &$state) use ($errorLog): void {
                        $state['processed'] = (int) ($state['processed'] ?? 0) + 1;
                        $state['failed'] = (int) ($state['failed'] ?? 0) + 1;
                        $state['errors'] = $errorLog;
                    });
                    continue;
                }

                $zip->addFromString($filenameInZip, $content);

                $result = [
                    'token' => $row->token,
                    'source' => $sourcePath,
                    'zip_name' => $filenameInZip,
                ];
                $resultLog[] = $result;

                $this->mutateState(function (array &$state) use ($resultLog): void {
                    $state['processed'] = (int) ($state['processed'] ?? 0) + 1;
                    $state['success'] = (int) ($state['success'] ?? 0) + 1;
                    $state['results'] = $resultLog;
                });
            }

            $zip->close();

            $this->mutateState(function (array &$state) use ($zipName, $errorLog, $resultLog): void {
                $state['status'] = 'done';
                $state['zip_path'] = 'bulk-download-zips/'.$zipName;
                $state['finished_at'] = now()->toISOString();
                $state['errors'] = $errorLog;
                $state['results'] = $resultLog;
            }, BulkRedisState::FINAL_TTL_SECONDS);
        } catch (Throwable $e) {
            $zip->close();
            $errorLog[] = ['reason' => 'job_error: '.$e->getMessage()];

            $this->mutateState(function (array &$state) use ($errorLog, $resultLog): void {
                $state['status'] = 'failed';
                $state['finished_at'] = now()->toISOString();
                $state['errors'] = $errorLog;
                $state['results'] = $resultLog;
            }, BulkRedisState::FINAL_TTL_SECONDS);
        }
    }

    private function mutateState(callable $mutator, ?int $ttlSeconds = null): void
    {
        $state = BulkRedisState::getDownload($this->bulkDownloadId) ?? [
            'id' => $this->bulkDownloadId,
            'type' => 'download',
            'status' => 'queued',
            'total_file' => 0,
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'summary' => [],
            'errors' => [],
            'results' => [],
            'zip_path' => null,
            'started_at' => null,
            'finished_at' => null,
        ];

        $mutator($state);
        BulkRedisState::setDownload($this->bulkDownloadId, $state, $ttlSeconds);
    }

    private function buildFilteredQuery(array $filter)
    {
        $q = TteRequest::query();

        if (($filter['status'] ?? '') !== '') {
            $q->where('status', $filter['status']);
        }

        if (($filter['tim'] ?? '') !== '') {
            $q->where('tim', $filter['tim']);
        }

        if (($filter['q'] ?? '') !== '') {
            $keyword = trim((string) $filter['q']);
            $q->where(function ($inner) use ($keyword) {
                $inner->where('nama', 'like', "%{$keyword}%")
                    ->orWhere('tim', 'like', "%{$keyword}%")
                    ->orWhere('token', 'like', "%{$keyword}%");
            });
        }

        if (($filter['tgl_dari'] ?? '') !== '') {
            $q->whereDate('created_at', '>=', $filter['tgl_dari']);
        }

        if (($filter['tgl_sampai'] ?? '') !== '') {
            $q->whereDate('created_at', '<=', $filter['tgl_sampai']);
        }

        return $q;
    }

    private function makeUniqueFilename(string $filename, array &$used): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $candidate = $filename;
        $counter = 2;

        while (in_array(strtolower($candidate), $used, true)) {
            $candidate = $base.'_'.$counter.($ext ? '.'.$ext : '');
            $counter++;
        }

        $used[] = strtolower($candidate);

        return $candidate;
    }
}

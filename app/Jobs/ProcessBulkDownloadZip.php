<?php

namespace App\Jobs;

use App\Models\BulkDownload;
use App\Models\TteRequest;
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
        public int $bulkDownloadId,
        public array $filter
    ) {}

    public function handle(): void
    {
        $bulk = BulkDownload::query()->findOrFail($this->bulkDownloadId);
        $bulk->update([
            'status' => 'running',
            'started_at' => now(),
            'error_log_json' => [],
            'result_log_json' => [],
        ]);

        $query = $this->buildFilteredQuery($this->filter);
        $query->orderBy($this->filter['sort'] ?? 'created_at', $this->filter['dir'] ?? 'desc');
        $rows = $query->limit(self::MAX_FILES)->get();
        $bulk->update(['total_file' => $rows->count()]);

        $zipDir = Storage::disk('local')->path('bulk-download-zips');
        if (! is_dir($zipDir)) {
            mkdir($zipDir, 0777, true);
        }
        $zipName = 'bulk_download_'.$bulk->id.'_'.now()->format('Ymd_His').'.zip';
        $zipFullPath = $zipDir.DIRECTORY_SEPARATOR.$zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat file ZIP.');
        }

        $usedNames = [];
        $resultLog = [];
        $errorLog = [];

        try {
            foreach ($rows as $row) {
                $sourcePath = $row->file_req;
                if (! $sourcePath || ! Storage::disk('s3')->exists($sourcePath)) {
                    $errorLog[] = [
                        'token' => $row->token,
                        'file' => $sourcePath,
                        'reason' => 'file_tidak_ditemukan',
                    ];
                    $bulk->increment('processed');
                    $bulk->increment('failed');
                    $bulk->update(['error_log_json' => $errorLog]);
                    continue;
                }

                $filename = basename($sourcePath);
                $filenameInZip = $this->makeUniqueFilename($filename, $usedNames);

                $stream = Storage::disk('s3')->readStream($sourcePath);
                if (! is_resource($stream)) {
                    $errorLog[] = [
                        'token' => $row->token,
                        'file' => $sourcePath,
                        'reason' => 'gagal_baca_stream',
                    ];
                    $bulk->increment('processed');
                    $bulk->increment('failed');
                    $bulk->update(['error_log_json' => $errorLog]);
                    continue;
                }

                $content = stream_get_contents($stream);
                fclose($stream);
                if ($content === false) {
                    $errorLog[] = [
                        'token' => $row->token,
                        'file' => $sourcePath,
                        'reason' => 'gagal_baca_konten',
                    ];
                    $bulk->increment('processed');
                    $bulk->increment('failed');
                    $bulk->update(['error_log_json' => $errorLog]);
                    continue;
                }

                $zip->addFromString($filenameInZip, $content);

                $resultLog[] = [
                    'token' => $row->token,
                    'source' => $sourcePath,
                    'zip_name' => $filenameInZip,
                ];
                $bulk->increment('processed');
                $bulk->increment('success');
                $bulk->update(['result_log_json' => $resultLog]);
            }

            $zip->close();

            $bulk->update([
                'status' => 'done',
                'zip_path' => 'bulk-download-zips/'.$zipName,
                'finished_at' => now(),
                'error_log_json' => $errorLog,
                'result_log_json' => $resultLog,
            ]);
        } catch (Throwable $e) {
            $zip->close();
            $errorLog[] = ['reason' => 'job_error: '.$e->getMessage()];
            $bulk->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_log_json' => $errorLog,
                'result_log_json' => $resultLog,
            ]);
        }
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

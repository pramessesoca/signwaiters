<?php

namespace App\Jobs;

use App\Models\TteRequest;
use App\Support\BulkRedisState;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class ProcessBulkZipUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    private const MAX_FILES = 100;

    public function __construct(
        public string $bulkUploadId,
        public string $zipPath
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
            $state['summary'] = [
                'token_tidak_ditemukan' => 0,
                'format_invalid' => 0,
                'melewati_batas_100' => 0,
            ];
            $state['errors'] = [];
            $state['results'] = [];
        });

        $tmpRoot = storage_path('app/tmp/bulk_upload_'.$this->bulkUploadId.'_'.Str::random(8));
        $zipFile = Storage::disk('local')->path($this->zipPath);

        try {
            if (! is_dir($tmpRoot)) {
                mkdir($tmpRoot, 0777, true);
            }

            $uploadableFiles = $this->collectUploadableFilesFromZip($zipFile, $tmpRoot);
            $selectedFiles = array_slice($uploadableFiles, 0, self::MAX_FILES);

            $this->mutateState(function (array &$state) use ($selectedFiles): void {
                $state['total_file'] = count($selectedFiles);
            });

            if (count($uploadableFiles) > self::MAX_FILES) {
                $this->mutateState(function (array &$state) use ($uploadableFiles): void {
                    $state['summary']['melewati_batas_100'] = count($uploadableFiles) - self::MAX_FILES;
                    $state['errors'][] = [
                        'file' => null,
                        'reason' => 'Sebagian file dilewati karena batas maksimal 100 file per proses.',
                    ];
                });
            }

            foreach ($selectedFiles as $filePath) {
                $this->processOneFile($filePath);
            }

            $this->mutateState(function (array &$state): void {
                $state['status'] = 'done';
                $state['finished_at'] = now()->toISOString();
            }, BulkRedisState::FINAL_TTL_SECONDS);
        } catch (Throwable $e) {
            $this->mutateState(function (array &$state) use ($e): void {
                $state['status'] = 'failed';
                $state['finished_at'] = now()->toISOString();
                $state['errors'][] = [
                    'file' => null,
                    'reason' => 'job_error: '.$e->getMessage(),
                ];
            }, BulkRedisState::FINAL_TTL_SECONDS);
        } finally {
            $this->deleteDir($tmpRoot);
            if (Storage::disk('local')->exists($this->zipPath)) {
                Storage::disk('local')->delete($this->zipPath);
            }
        }
    }

    private function processOneFile(string $filePath): void
    {
        $filename = basename($filePath);
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($ext, ['pdf', 'zip'], true)) {
            $this->markFailed($filename, 'format_invalid');
            return;
        }

        if (! preg_match('/^([A-Za-z0-9]{8})_(.+)$/', $nameWithoutExt, $m)) {
            $this->markFailed($filename, 'format_invalid');
            return;
        }

        $token = Str::upper($m[1]);
        $request = TteRequest::query()->where('token', $token)->first();

        if (! $request) {
            $this->markFailed($filename, 'token_tidak_ditemukan');
            return;
        }

        $safeName = Str::slug($nameWithoutExt);
        $path = 'tte/'.Carbon::now()->format('Y/m/d').'/'.$token.'_'.$safeName.'.'.$ext;

        $stream = fopen($filePath, 'rb');
        Storage::disk('s3')->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $request->update([
            'status' => TteRequest::STATUS_SIAP,
            'file_tte' => $path,
            'tgl_setuju' => $request->tgl_setuju ?? now(),
        ]);

        $this->mutateState(function (array &$state) use ($filename, $token): void {
            $state['processed'] = (int) ($state['processed'] ?? 0) + 1;
            $state['success'] = (int) ($state['success'] ?? 0) + 1;
            $state['results'][] = [
                'file' => $filename,
                'token' => $token,
                'status' => 'uploaded',
            ];
        });
    }

    private function markFailed(string $filename, string $reason): void
    {
        $this->mutateState(function (array &$state) use ($filename, $reason): void {
            $state['summary'][$reason] = (int) ($state['summary'][$reason] ?? 0) + 1;
            $state['errors'][] = [
                'file' => $filename,
                'reason' => $reason,
            ];
            $state['processed'] = (int) ($state['processed'] ?? 0) + 1;
            $state['failed'] = (int) ($state['failed'] ?? 0) + 1;
        });
    }

    private function mutateState(callable $mutator, ?int $ttlSeconds = null): void
    {
        $state = BulkRedisState::getUpload($this->bulkUploadId) ?? [
            'id' => $this->bulkUploadId,
            'type' => 'upload',
            'status' => 'queued',
            'total_file' => 0,
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'summary' => [
                'token_tidak_ditemukan' => 0,
                'format_invalid' => 0,
                'melewati_batas_100' => 0,
            ],
            'errors' => [],
            'results' => [],
            'started_at' => null,
            'finished_at' => null,
        ];

        $mutator($state);
        BulkRedisState::setUpload($this->bulkUploadId, $state, $ttlSeconds);
    }

    /**
     * @return array<int, string>
     */
    private function collectUploadableFilesFromZip(string $zipPath, string $extractRoot): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('ZIP tidak bisa dibuka: '.$zipPath);
        }

        $extractHere = $extractRoot.'/'.Str::random(8);
        if (! is_dir($extractHere)) {
            mkdir($extractHere, 0777, true);
        }

        $zip->extractTo($extractHere);
        $zip->close();

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractHere, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            $path = $file->getPathname();

            if (in_array($ext, ['pdf', 'zip'], true)) {
                $files[] = $path;
            }
        }

        return $files;
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}

<?php

namespace App\Jobs;

use App\Models\BulkUpload;
use App\Models\TteRequest;
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

    public function __construct(
        public int $bulkUploadId,
        public string $zipPath
    ) {}

    public function handle(): void
    {
        $bulk = BulkUpload::query()->findOrFail($this->bulkUploadId);
        $bulk->update([
            'status' => 'running',
            'started_at' => now(),
            'error_log_json' => [
                'summary' => [
                    'token_tidak_ditemukan' => 0,
                    'format_invalid' => 0,
                ],
                'items' => [],
            ],
        ]);

        $tmpRoot = storage_path('app/tmp/bulk_upload_'.$bulk->id.'_'.Str::random(8));
        $zipFile = Storage::disk('local')->path($this->zipPath);

        try {
            if (! is_dir($tmpRoot)) {
                mkdir($tmpRoot, 0777, true);
            }

            $pdfFiles = $this->collectPdfFilesFromZip($zipFile, $tmpRoot);
            $bulk->update(['total_file' => count($pdfFiles)]);

            foreach ($pdfFiles as $pdfPath) {
                $this->processOnePdf($bulk, $pdfPath);
            }

            $bulk->update([
                'status' => 'done',
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $log = $bulk->error_log_json ?? ['summary' => [], 'items' => []];
            $items = $log['items'] ?? [];
            $items[] = [
                'file' => null,
                'reason' => 'job_error: '.$e->getMessage(),
            ];
            $log['items'] = $items;

            $bulk->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_log_json' => $log,
            ]);
        } finally {
            $this->deleteDir($tmpRoot);
            if (Storage::disk('local')->exists($this->zipPath)) {
                Storage::disk('local')->delete($this->zipPath);
            }
        }
    }

    private function processOnePdf(BulkUpload $bulk, string $pdfPath): void
    {
        $filename = basename($pdfPath);
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        if (! preg_match('/^([A-Za-z0-9]{8})_(.+)$/', $nameWithoutExt, $m)) {
            $this->markFailed($bulk, $filename, 'format_invalid');
            return;
        }

        $token = Str::upper($m[1]);
        $request = TteRequest::query()->where('token', $token)->first();
        if (! $request) {
            $this->markFailed($bulk, $filename, 'token_tidak_ditemukan');
            return;
        }

        $safeName = Str::slug($nameWithoutExt);
        $path = 'tte/'.Carbon::now()->format('Y/m/d').'/'.$token.'_'.$safeName.'.pdf';

        $stream = fopen($pdfPath, 'rb');
        Storage::disk('s3')->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $request->update([
            'status' => TteRequest::STATUS_SIAP,
            'file_tte' => $path,
            'tgl_setuju' => $request->tgl_setuju ?? now(),
        ]);

        $bulk->increment('processed');
        $bulk->increment('success');
    }

    private function markFailed(BulkUpload $bulk, string $filename, string $reason): void
    {
        $log = $bulk->error_log_json ?? ['summary' => [], 'items' => []];
        $summary = $log['summary'] ?? [];
        $items = $log['items'] ?? [];

        $summary[$reason] = ($summary[$reason] ?? 0) + 1;
        $items[] = [
            'file' => $filename,
            'reason' => $reason,
        ];

        $log['summary'] = $summary;
        $log['items'] = $items;

        $bulk->update(['error_log_json' => $log]);
        $bulk->increment('processed');
        $bulk->increment('failed');
    }

    /**
     * @return array<int, string>
     */
    private function collectPdfFilesFromZip(string $zipPath, string $extractRoot): array
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

        $pdfFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractHere, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            $path = $file->getPathname();

            if ($ext === 'pdf') {
                $pdfFiles[] = $path;
                continue;
            }

            if ($ext === 'zip') {
                $nestedPdf = $this->collectPdfFilesFromZip($path, $extractRoot);
                foreach ($nestedPdf as $p) {
                    $pdfFiles[] = $p;
                }
            }
        }

        return $pdfFiles;
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

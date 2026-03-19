<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBulkDownloadZip;
use App\Jobs\ProcessBulkZipUpload;
use App\Models\TteRequest;
use App\Support\BulkRedisState;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminPanelController extends Controller
{
    public function dashboard(Request $request)
    {
        if (! $request->filled('tgl_dari')) {
            $request->merge([
                'tgl_dari' => now()->toDateString(),
            ]);
        }

        $validated = $this->validateDashboardFilter($request);
        $q = $this->buildFilteredQuery($validated);
        $q->orderBy($validated['sort'], $validated['dir']);

        $perPage = (int) $validated['per_page'];
        $data = $q->paginate($perPage)->withQueryString();

        return view('admin.dashboard', [
            'data' => $data,
            'filterStatus' => $validated['status'],
            'filterCari' => $validated['q'],
            'filterTim' => $validated['tim'],
            'filterTglDari' => $validated['tgl_dari'],
            'filterTglSampai' => $validated['tgl_sampai'],
            'filterPerPage' => $perPage,
            'listTim' => TteRequest::listTim(),
            'sortBy' => $validated['sort'],
            'sortDir' => $validated['dir'],
        ]);
    }

    public function generateTxt(Request $request)
    {
        $validated = $this->validateDashboardFilter($request);
        $q = $this->buildFilteredQuery($validated);
        $q->orderBy($validated['sort'], $validated['dir']);

        $rows = $q->get(['token', 'nama', 'tim', 'file_req', 'file_tte']);

        $lines = [];
        foreach ($rows as $index => $row) {
            $namaFile = basename($row->file_tte ?: $row->file_req);
            $lines[] = ($index + 1).'. '.$namaFile;
        }

        $txt = implode(PHP_EOL, $lines);
        $filename = 'nama-file-filtered-'.now()->format('Ymd_His').'.txt';

        return response($txt, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function bulkUploadForm()
    {
        return view('admin.bulk-upload');
    }

    public function bulkUploadStore(Request $request)
    {
        $data = $request->validate([
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:102400'],
        ]);

        $storedPath = $data['zip_file']->store('bulk-zips', 'local');
        $bulkId = (string) Str::uuid();

        BulkRedisState::setUpload($bulkId, [
            'id' => $bulkId,
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
        ]);

        ProcessBulkZipUpload::dispatch($bulkId, $storedPath);

        return redirect()
            ->route('admin.bulk_upload.form', ['bulk_upload_id' => $bulkId])
            ->with('sukses', 'ZIP berhasil diupload. Proses berjalan di background.');
    }

    public function bulkUploadStatus(string $bulkUpload)
    {
        $state = BulkRedisState::getUpload($bulkUpload);
        if (! $state) {
            return response()->json([
                'message' => 'Proses bulk upload tidak ditemukan atau sudah kedaluwarsa.',
            ], 404);
        }

        return response()->json([
            'id' => $state['id'],
            'status' => $state['status'] ?? 'queued',
            'total_file' => (int) ($state['total_file'] ?? 0),
            'processed' => (int) ($state['processed'] ?? 0),
            'success' => (int) ($state['success'] ?? 0),
            'failed' => (int) ($state['failed'] ?? 0),
            'summary' => $state['summary'] ?? [
                'token_tidak_ditemukan' => 0,
                'format_invalid' => 0,
                'melewati_batas_100' => 0,
            ],
            'errors' => $state['errors'] ?? [],
            'started_at' => $state['started_at'] ?? null,
            'finished_at' => $state['finished_at'] ?? null,
        ]);
    }

    public function bulkUploadClear()
    {
        BulkRedisState::clearUploadDoneFailed();

        return redirect()
            ->route('admin.bulk_upload.form')
            ->with('sukses', 'Riwayat bulk upload selesai berhasil dibersihkan.');
    }

    public function bulkDownloadForm()
    {
        return view('admin.bulk-download');
    }

    public function bulkDownloadStore(Request $request)
    {
        $validated = $this->validateDashboardFilter($request);
        $bulkId = (string) Str::uuid();

        BulkRedisState::setDownload($bulkId, [
            'id' => $bulkId,
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
        ]);

        ProcessBulkDownloadZip::dispatch($bulkId, $validated);

        return redirect()
            ->route('admin.bulk_download.form', ['bulk_download_id' => $bulkId])
            ->with('sukses', 'Bulk download dimulai di background.');
    }

    public function bulkDownloadStatus(string $bulkDownload)
    {
        $state = BulkRedisState::getDownload($bulkDownload);
        if (! $state) {
            return response()->json([
                'message' => 'Proses bulk download tidak ditemukan atau sudah kedaluwarsa.',
            ], 404);
        }

        return response()->json([
            'id' => $state['id'],
            'status' => $state['status'] ?? 'queued',
            'total_file' => (int) ($state['total_file'] ?? 0),
            'processed' => (int) ($state['processed'] ?? 0),
            'success' => (int) ($state['success'] ?? 0),
            'failed' => (int) ($state['failed'] ?? 0),
            'errors' => $state['errors'] ?? [],
            'results' => $state['results'] ?? [],
            'started_at' => $state['started_at'] ?? null,
            'finished_at' => $state['finished_at'] ?? null,
            'has_file' => ! empty($state['zip_path'] ?? null),
        ]);
    }

    public function bulkDownloadFile(string $bulkDownload)
    {
        $state = BulkRedisState::getDownload($bulkDownload);
        if (! $state) {
            abort(404, 'Proses bulk download tidak ditemukan atau sudah kedaluwarsa.');
        }

        $zipPath = (string) ($state['zip_path'] ?? '');
        if (($state['status'] ?? '') !== 'done' || $zipPath === '') {
            abort(404, 'File ZIP belum siap.');
        }

        if (Storage::disk('local')->exists($zipPath)) {
            return Storage::disk('local')->download(
                $zipPath,
                'bulk-download-'.$bulkDownload.'.zip'
            );
        }

        $legacyPath = storage_path('app'.DIRECTORY_SEPARATOR.$zipPath);
        if (is_file($legacyPath)) {
            return response()->download($legacyPath, 'bulk-download-'.$bulkDownload.'.zip');
        }

        abort(404, 'File ZIP tidak ditemukan.');
    }

    public function bulkDownloadClear()
    {
        $cleared = BulkRedisState::clearDownloadDoneFailed();

        foreach ($cleared as $state) {
            $zipPath = (string) ($state['zip_path'] ?? '');
            if ($zipPath !== '' && Storage::disk('local')->exists($zipPath)) {
                Storage::disk('local')->delete($zipPath);
            }

            $legacyPath = storage_path('app'.DIRECTORY_SEPARATOR.$zipPath);
            if ($zipPath !== '' && is_file($legacyPath)) {
                @unlink($legacyPath);
            }
        }

        return redirect()
            ->route('admin.bulk_download.form')
            ->with('sukses', 'Riwayat bulk download selesai berhasil dibersihkan.');
    }

    public function bulkClearAll()
    {
        $allDownloads = BulkRedisState::clearAllDownload();
        BulkRedisState::clearAllUpload();

        foreach ($allDownloads as $state) {
            $zipPath = (string) ($state['zip_path'] ?? '');
            if ($zipPath !== '' && Storage::disk('local')->exists($zipPath)) {
                Storage::disk('local')->delete($zipPath);
            }

            $legacyPath = storage_path('app'.DIRECTORY_SEPARATOR.$zipPath);
            if ($zipPath !== '' && is_file($legacyPath)) {
                @unlink($legacyPath);
            }
        }

        Storage::disk('local')->deleteDirectory('bulk-zips');
        Storage::disk('local')->deleteDirectory('bulk-download-zips');
        Storage::disk('local')->deleteDirectory('tmp');

        return back()->with('sukses', 'Semua proses bulk (upload/download) dan file sementara berhasil dibersihkan.');
    }

    public function detail(TteRequest $tteRequest)
    {
        return view('admin.detail', [
            'item' => $tteRequest,
        ]);
    }

    public function setuju(TteRequest $tteRequest)
    {
        $tteRequest->update([
            'status' => TteRequest::STATUS_SETUJU,
            'tgl_setuju' => Carbon::now(),
            'tgl_tolak' => null,
            'cat_admin' => null,
        ]);

        return back()->with('sukses', 'Permohonan disetujui.');
    }

    public function tolak(Request $request, TteRequest $tteRequest)
    {
        $data = $request->validate([
            'cat_admin' => ['required', 'string'],
        ]);

        $tteRequest->update([
            'status' => TteRequest::STATUS_TOLAK,
            'cat_admin' => $data['cat_admin'],
            'tgl_tolak' => Carbon::now(),
            'tgl_setuju' => null,
            'file_tte' => null,
        ]);

        return back()->with('sukses', 'Permohonan ditolak.');
    }

    public function unggahTte(Request $request, TteRequest $tteRequest)
    {
        $data = $request->validate([
            'file_tte' => ['required', 'file', 'mimes:pdf,zip', 'max:102400'],
        ]);

        $file = $data['file_tte'];
        $tanggal = now()->format('Y/m/d');
        $namaAsli = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $ext = $ext === 'zip' ? 'zip' : 'pdf';
        $namaFile = $tteRequest->token.'_'.Str::slug($namaAsli).'.'.$ext;
        $path = $file->storeAs("tte/{$tanggal}", $namaFile, 's3');

        $tteRequest->update([
            'status' => TteRequest::STATUS_SIAP,
            'file_tte' => $path,
            'tgl_setuju' => $tteRequest->tgl_setuju ?? Carbon::now(),
        ]);

        return back()->with('sukses', 'File TTE berhasil diunggah.');
    }

    public function generateUlangToken(TteRequest $tteRequest)
    {
        $tokenBaru = TteRequest::buatTokenUnik();
        $fileReqBaru = $this->renameFileDenganTokenBaru($tteRequest->file_req, $tokenBaru);
        $fileTteBaru = $this->renameFileDenganTokenBaru($tteRequest->file_tte, $tokenBaru);

        $tteRequest->update([
            'token' => $tokenBaru,
            'file_req' => $fileReqBaru ?? $tteRequest->file_req,
            'file_tte' => $fileTteBaru ?? $tteRequest->file_tte,
            'kedaluwarsa' => now()->addDays(7),
        ]);

        return back()->with('sukses', 'Token berhasil digenerate ulang.');
    }

    public function hapus(TteRequest $tteRequest)
    {
        if ($tteRequest->file_req && Storage::disk('s3')->exists($tteRequest->file_req)) {
            Storage::disk('s3')->delete($tteRequest->file_req);
        }

        if ($tteRequest->file_tte && Storage::disk('s3')->exists($tteRequest->file_tte)) {
            Storage::disk('s3')->delete($tteRequest->file_tte);
        }

        $tteRequest->delete();

        return back()->with('sukses', 'Permohonan berhasil dihapus.');
    }

    private function validateDashboardFilter(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'q' => ['nullable', 'string'],
            'status' => ['nullable', 'in:tunggu,setuju,tolak,siap'],
            'tim' => ['nullable', 'string', 'in:'.implode(',', TteRequest::listTim())],
            'tgl_dari' => ['nullable', 'date'],
            'tgl_sampai' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],
            'sort' => ['nullable', 'in:id,nama,tim,token,status,created_at'],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);
        $validated = $validator->validate();

        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'status' => (string) ($validated['status'] ?? ''),
            'tim' => (string) ($validated['tim'] ?? ''),
            'tgl_dari' => (string) ($validated['tgl_dari'] ?? ''),
            'tgl_sampai' => (string) ($validated['tgl_sampai'] ?? ''),
            'per_page' => (int) ($validated['per_page'] ?? 15),
            'sort' => (string) ($validated['sort'] ?? 'created_at'),
            'dir' => (string) ($validated['dir'] ?? 'desc'),
        ];
    }

    private function buildFilteredQuery(array $filter)
    {
        $q = TteRequest::query();

        if ($filter['status'] !== '') {
            $q->where('status', $filter['status']);
        }

        if ($filter['tim'] !== '') {
            $q->where('tim', $filter['tim']);
        }

        if ($filter['q'] !== '') {
            $keyword = $filter['q'];
            $q->where(function ($inner) use ($keyword) {
                $inner->where('nama', 'like', "%{$keyword}%")
                    ->orWhere('tim', 'like', "%{$keyword}%")
                    ->orWhere('token', 'like', "%{$keyword}%");
            });
        }

        if ($filter['tgl_dari'] !== '') {
            $q->whereDate('created_at', '>=', $filter['tgl_dari']);
        }

        if ($filter['tgl_sampai'] !== '') {
            $q->whereDate('created_at', '<=', $filter['tgl_sampai']);
        }

        return $q;
    }

    private function renameFileDenganTokenBaru(?string $oldPath, string $tokenBaru): ?string
    {
        if (! $oldPath) {
            return null;
        }

        $disk = Storage::disk('s3');
        if (! $disk->exists($oldPath)) {
            return null;
        }

        $dir = trim(str_replace('\\', '/', dirname($oldPath)), '.');
        $filename = basename($oldPath);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $nameNoExt = pathinfo($filename, PATHINFO_FILENAME);

        $suffix = $nameNoExt;
        if (str_contains($nameNoExt, '_')) {
            $parts = explode('_', $nameNoExt, 2);
            $suffix = $parts[1] !== '' ? $parts[1] : $parts[0];
        }

        $newFilename = $tokenBaru.'_'.$suffix.($ext ? '.'.$ext : '');
        $newPath = ($dir !== '' && $dir !== '/') ? $dir.'/'.$newFilename : $newFilename;

        if ($newPath === $oldPath) {
            return $oldPath;
        }

        $disk->copy($oldPath, $newPath);
        $disk->delete($oldPath);

        return $newPath;
    }
}

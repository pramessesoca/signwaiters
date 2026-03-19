<?php

namespace App\Http\Controllers;

use App\Models\TteRequest;
use App\Support\ZipTokenNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserPortalController extends Controller
{
    public function formPermohonan()
    {
        return view('user.request', [
            'listTim' => TteRequest::listTim(),
        ]);
    }

    public function simpanPermohonan(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tim' => ['required', 'string', 'in:'.implode(',', TteRequest::listTim())],
            'file_zip' => ['required', 'file', 'mimes:pdf,zip', 'max:102400'],
            'upload_kind' => ['nullable', 'in:single_pdf,multi_pdf,zip_direct'],
        ]);

        $token = TteRequest::buatTokenUnik();
        $file = $data['file_zip'];
        $tanggal = now()->format('Y/m/d');
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $ext = $ext === 'zip' ? 'zip' : 'pdf';
        if ($ext === 'pdf' && $file->getSize() > 10 * 1024 * 1024) {
            return back()
                ->withErrors(['file_zip' => 'Ukuran file PDF maksimal 10MB.'])
                ->withInput();
        }
        $namaFile = $token.'_'.Str::slug($baseName).'.'.$ext;
        if ($ext === 'zip' && ($data['upload_kind'] ?? '') === 'multi_pdf') {
            $normalizedZipPath = ZipTokenNormalizer::normalizeWithToken($file->getRealPath(), $token, 'zip_req_web');
            $stream = fopen($normalizedZipPath, 'rb');
            Storage::disk('s3')->put("request/{$tanggal}/{$namaFile}", $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($normalizedZipPath);
            $path = "request/{$tanggal}/{$namaFile}";
        } else {
            $path = $file->storeAs("request/{$tanggal}", $namaFile, 's3');
        }

        TteRequest::query()->create([
            'nama' => $data['nama'],
            'tim' => $data['tim'],
            'token' => $token,
            'status' => TteRequest::STATUS_TUNGGU,
            'file_req' => $path,
            'kedaluwarsa' => now()->addDays(7),
        ]);

        return redirect()
            ->route('user.request.form')
            ->with('sukses', 'Permohonan berhasil dikirim.')
            ->with('token', $token);
    }

    public function formCekToken()
    {
        return view('user.status');
    }

    public function cekToken(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:8'],
        ]);

        $token = Str::upper($data['token']);
        $permohonan = TteRequest::query()->where('token', $token)->first();

        if (! $permohonan) {
            return redirect()
                ->route('user.status.form')
                ->withErrors(['token' => 'Token tidak ditemukan.'])
                ->withInput();
        }

        $statusTampil = $permohonan->status;
        if ($permohonan->kedaluwarsa && $permohonan->kedaluwarsa->isPast()) {
            $statusTampil = 'kedaluwarsa';
        }

        return view('user.status', [
            'hasil' => $permohonan,
            'statusTampil' => $statusTampil,
        ]);
    }

    public function unduh(string $token)
    {
        $permohonan = TteRequest::query()->where('token', Str::upper($token))->firstOrFail();

        if ($permohonan->kedaluwarsa && $permohonan->kedaluwarsa->isPast()) {
            abort(410, 'Token sudah kedaluwarsa.');
        }

        if ($permohonan->status !== TteRequest::STATUS_SIAP || ! $permohonan->file_tte) {
            abort(422, 'File TTE belum siap diunduh.');
        }

        if (! Storage::disk('s3')->exists($permohonan->file_tte)) {
            abort(404, 'File TTE tidak ditemukan.');
        }

        $stream = Storage::disk('s3')->readStream($permohonan->file_tte);
        $extension = strtolower((string) pathinfo($permohonan->file_tte, PATHINFO_EXTENSION));
        $isZip = $extension === 'zip';
        $downloadName = $isZip ? 'tte_'.$permohonan->token.'.zip' : 'tte_'.$permohonan->token.'.pdf';
        $contentType = $isZip ? 'application/zip' : 'application/pdf';

        return response()->streamDownload(
            fn () => fpassthru($stream),
            $downloadName,
            ['Content-Type' => $contentType]
        );
    }
}

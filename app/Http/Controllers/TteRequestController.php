<?php

namespace App\Http\Controllers;

use App\Models\TteRequest;
use App\Support\ZipTokenNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TteRequestController extends Controller
{
    public function simpan(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tim' => ['required', 'string', 'in:'.implode(',', TteRequest::listTim())],
            'file_zip' => ['required', 'file', 'mimes:pdf,zip', 'max:102400'],
            'upload_kind' => ['nullable', 'in:single_pdf,multi_pdf,zip_direct'],
        ]);

        $token = TteRequest::buatTokenUnik();
        $file = $data['file_zip'];
        $today = now()->format('Y/m/d');
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $ext = $ext === 'zip' ? 'zip' : 'pdf';
        if ($ext === 'pdf' && $file->getSize() > 20 * 1024 * 1024) {
            return response()->json(['pesan' => 'Ukuran file PDF maksimal 20MB.'], 422);
        }
        $namaFile = $token.'_'.Str::slug($baseName);
        $targetFilename = $namaFile.'.'.$ext;

        if ($ext === 'zip' && ($data['upload_kind'] ?? '') === 'multi_pdf') {
            $normalizedZipPath = ZipTokenNormalizer::normalizeWithToken($file->getRealPath(), $token, 'zip_req_api');
            $stream = fopen($normalizedZipPath, 'rb');
            Storage::disk('s3')->put("request/{$today}/{$targetFilename}", $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($normalizedZipPath);
            $path = "request/{$today}/{$targetFilename}";
        } else {
            $path = $file->storeAs("request/{$today}", $targetFilename, 's3');
        }

        $permohonan = TteRequest::create([
            'nama' => $data['nama'],
            'tim' => $data['tim'],
            'token' => $token,
            'status' => TteRequest::STATUS_TUNGGU,
            'file_req' => $path,
            'kedaluwarsa' => now()->addDays(7),
        ]);

        return response()->json([
            'pesan' => 'Permohonan berhasil dibuat.',
            'token' => $permohonan->token,
            'id' => $permohonan->id,
        ], 201);
    }

    public function cek(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:8'],
        ]);

        $token = Str::upper($data['token']);
        $permohonan = TteRequest::where('token', $token)->first();

        if (! $permohonan) {
            return response()->json(['pesan' => 'Token tidak ditemukan.'], 404);
        }

        $status = $permohonan->status;
        if ($permohonan->kedaluwarsa && $permohonan->kedaluwarsa->isPast()) {
            $status = 'kedaluwarsa';
        }

        return response()->json([
            'token' => $permohonan->token,
            'nama' => $permohonan->nama,
            'tim' => $permohonan->tim,
            'status' => $status,
            'kedaluwarsa' => $permohonan->kedaluwarsa,
        ]);
    }

    public function unduh(string $token)
    {
        $permohonan = TteRequest::where('token', Str::upper($token))->first();

        if (! $permohonan) {
            abort(404, 'Token tidak ditemukan.');
        }

        if ($permohonan->kedaluwarsa && $permohonan->kedaluwarsa->isPast()) {
            abort(410, 'Token sudah kedaluwarsa.');
        }

        if ($permohonan->status !== TteRequest::STATUS_SIAP || ! $permohonan->file_tte) {
            abort(422, 'File TTE belum siap diunduh.');
        }

        if (! Storage::disk('s3')->exists($permohonan->file_tte)) {
            abort(404, 'File TTE tidak ditemukan di penyimpanan.');
        }

        $stream = Storage::disk('s3')->readStream($permohonan->file_tte);
        $extension = strtolower((string) pathinfo($permohonan->file_tte, PATHINFO_EXTENSION));
        $isZip = $extension === 'zip';
        $namaUnduh = $isZip ? 'tte_'.$permohonan->token.'.zip' : 'tte_'.$permohonan->token.'.pdf';
        $contentType = $isZip ? 'application/zip' : 'application/pdf';

        return response()->streamDownload(
            fn () => fpassthru($stream),
            $namaUnduh,
            ['Content-Type' => $contentType]
        );
    }

    public function setuju(TteRequest $tteRequest)
    {
        $tteRequest->update([
            'status' => TteRequest::STATUS_SETUJU,
            'tgl_setuju' => Carbon::now(),
            'tgl_tolak' => null,
            'cat_admin' => null,
        ]);

        return response()->json(['pesan' => 'Permohonan disetujui.']);
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

        return response()->json(['pesan' => 'Permohonan ditolak.']);
    }

    public function unggahTte(Request $request, TteRequest $tteRequest)
    {
        $data = $request->validate([
            'file_tte' => ['required', 'file', 'mimes:pdf,zip', 'max:102400'],
        ]);

        $file = $data['file_tte'];
        $today = now()->format('Y/m/d');
        $namaFile = $tteRequest->token.'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $ext = $ext === 'zip' ? 'zip' : 'pdf';
        $path = $file->storeAs("tte/{$today}", $namaFile.'.'.$ext, 's3');

        $tteRequest->update([
            'status' => TteRequest::STATUS_SIAP,
            'file_tte' => $path,
            'tgl_setuju' => $tteRequest->tgl_setuju ?? Carbon::now(),
        ]);

        return response()->json(['pesan' => 'File TTE berhasil diunggah.']);
    }
}

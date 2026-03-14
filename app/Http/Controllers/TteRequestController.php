<?php

namespace App\Http\Controllers;

use App\Models\TteRequest;
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
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $token = TteRequest::buatTokenUnik();
        $file = $data['file'];
        $today = now()->format('Y/m/d');
        $namaFile = $token . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $path = $file->storeAs("request/{$today}", $namaFile . '.pdf', 's3');

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
        $namaUnduh = 'tte_' . $permohonan->token . '.pdf';

        return response()->streamDownload(
            fn () => fpassthru($stream),
            $namaUnduh,
            ['Content-Type' => 'application/pdf']
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
            'file_tte' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $data['file_tte'];
        $today = now()->format('Y/m/d');
        $namaFile = $tteRequest->token . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $path = $file->storeAs("tte/{$today}", $namaFile . '.pdf', 's3');

        $tteRequest->update([
            'status' => TteRequest::STATUS_SIAP,
            'file_tte' => $path,
            'tgl_setuju' => $tteRequest->tgl_setuju ?? Carbon::now(),
        ]);

        return response()->json(['pesan' => 'File TTE berhasil diunggah.']);
    }
}

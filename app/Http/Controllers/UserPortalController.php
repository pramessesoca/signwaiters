<?php

namespace App\Http\Controllers;

use App\Models\TteRequest;
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
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $token = TteRequest::buatTokenUnik();
        $file = $data['file'];
        $tanggal = now()->format('Y/m/d');
        $namaAsli = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $namaFile = $token.'_'.Str::slug($namaAsli).'.pdf';
        $path = $file->storeAs("request/{$tanggal}", $namaFile, 's3');

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

        return response()->streamDownload(
            fn () => fpassthru($stream),
            'tte_'.$permohonan->token.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}

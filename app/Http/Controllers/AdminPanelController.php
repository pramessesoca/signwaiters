<?php

namespace App\Http\Controllers;

use App\Models\TteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AdminPanelController extends Controller
{
    public function dashboard(Request $request)
    {
        $q = TteRequest::query()->latest();

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        $data = $q->paginate(15)->withQueryString();

        return view('admin.dashboard', [
            'data' => $data,
            'filterStatus' => $request->string('status')->toString(),
        ]);
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
            'file_tte' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $data['file_tte'];
        $tanggal = now()->format('Y/m/d');
        $namaAsli = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $namaFile = $tteRequest->token.'_'.Str::slug($namaAsli).'.pdf';
        $path = $file->storeAs("tte/{$tanggal}", $namaFile, 's3');

        $tteRequest->update([
            'status' => TteRequest::STATUS_SIAP,
            'file_tte' => $path,
            'tgl_setuju' => $tteRequest->tgl_setuju ?? Carbon::now(),
        ]);

        return back()->with('sukses', 'File TTE berhasil diunggah.');
    }
}

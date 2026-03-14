<?php

namespace App\Http\Controllers;

use App\Models\TteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminPanelController extends Controller
{
    public function dashboard(Request $request)
    {
        $request->validate([
            'tgl_dari' => ['nullable', 'date'],
            'tgl_sampai' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],
            'tim' => ['nullable', 'string', 'in:'.implode(',', TteRequest::listTim())],
            'sort' => ['nullable', 'in:id,nama,tim,token,status,created_at'],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        $q = TteRequest::query();

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('tim')) {
            $q->where('tim', $request->string('tim')->toString());
        }

        if ($request->filled('q')) {
            $keyword = trim($request->string('q')->toString());
            $q->where(function ($inner) use ($keyword) {
                $inner->where('nama', 'like', "%{$keyword}%")
                    ->orWhere('tim', 'like', "%{$keyword}%")
                    ->orWhere('token', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('tgl_dari')) {
            $q->whereDate('created_at', '>=', $request->string('tgl_dari')->toString());
        }

        if ($request->filled('tgl_sampai')) {
            $q->whereDate('created_at', '<=', $request->string('tgl_sampai')->toString());
        }

        $sort = $request->string('sort')->toString() ?: 'created_at';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $q->orderBy($sort, $dir);

        $perPage = (int) $request->integer('per_page', 15);
        $data = $q->paginate($perPage)->withQueryString();

        return view('admin.dashboard', [
            'data' => $data,
            'filterStatus' => $request->string('status')->toString(),
            'filterCari' => $request->string('q')->toString(),
            'filterTim' => $request->string('tim')->toString(),
            'filterTglDari' => $request->string('tgl_dari')->toString(),
            'filterTglSampai' => $request->string('tgl_sampai')->toString(),
            'filterPerPage' => $perPage,
            'listTim' => TteRequest::listTim(),
            'sortBy' => $sort,
            'sortDir' => $dir,
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

    public function generateUlangToken(TteRequest $tteRequest)
    {
        $tteRequest->update([
            'token' => TteRequest::buatTokenUnik(),
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
}

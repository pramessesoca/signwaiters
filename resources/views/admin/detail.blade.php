@extends('layouts.app')

@section('content')
    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-3 text-xl font-bold text-slate-900">Detail Permohonan #{{ $item->id }}</h2>
        <div class="space-y-1 text-sm text-slate-700">
            <p>Nama: <strong>{{ $item->nama }}</strong></p>
            <p>Tim: <strong>{{ $item->tim }}</strong></p>
            <p>Token: <strong>{{ $item->token }}</strong></p>
            <p>Status:
                <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-800">{{ $item->status }}</span>
            </p>
            <p>Catatan Admin: {{ $item->cat_admin ?: '-' }}</p>
        </div>
    </div>

    @if (session('sukses'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="font-medium text-emerald-700">{{ session('sukses') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4">
            @foreach ($errors->all() as $error)
                <p class="text-sm text-red-600">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="mb-3 text-lg font-semibold text-slate-900">Aksi Admin</h3>
        <div class="flex flex-col gap-4 md:flex-row">
            <form action="{{ route('admin.request.setuju', $item) }}" method="POST">
                @csrf
                <button class="rounded-xl bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" type="submit">Setujui</button>
            </form>

            <form action="{{ route('admin.request.generate_token', $item) }}" method="POST">
                @csrf
                <button class="rounded-xl bg-amber-500 px-4 py-2 font-medium text-white hover:bg-amber-600" type="submit">Generate Ulang Token</button>
            </form>

            <form action="{{ route('admin.request.tolak', $item) }}" method="POST" class="flex-1">
                @csrf
                <label class="mb-1 block text-sm font-medium text-slate-700">Catatan Penolakan</label>
                <textarea class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-red-500" name="cat_admin" rows="2" required></textarea>
                <div class="mt-2">
                    <button class="rounded-xl bg-red-600 px-4 py-2 font-medium text-white hover:bg-red-700" type="submit">Tolak</button>
                </div>
            </form>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="mb-3 text-lg font-semibold text-slate-900">Upload File TTE</h3>
        <form action="{{ route('admin.request.unggah_tte', $item) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <input class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" type="file" name="file_tte" accept="application/pdf" required>
            </div>
            <div>
                <button class="rounded-xl bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700" type="submit">Upload File TTE</button>
            </div>
        </form>
    </div>
@endsection



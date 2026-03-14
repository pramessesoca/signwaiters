@extends('layouts.app')

@section('content')
    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="text-xl font-bold text-slate-900">Form Permohonan TTE</h2>
        <p class="mt-1 text-sm text-slate-600">Isi data dan upload file PDF untuk diproses admin.</p>
    </div>

    @if (session('sukses'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <p class="font-semibold text-emerald-700">{{ session('sukses') }}</p>
            <p class="mt-1 text-slate-700">Token Anda: <strong>{{ session('token') }}</strong></p>
            <p class="text-sm text-slate-600">Simpan token ini untuk cek status dan download file.</p>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form action="{{ route('user.request.submit') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nama</label>
                <input class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none ring-0 focus:border-blue-500" type="text" name="nama" value="{{ old('nama') }}" required>
                @error('nama') <small class="mt-1 block text-sm text-red-600">{{ $message }}</small> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tim Kerja</label>
                <select class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none ring-0 focus:border-blue-500" name="tim" required>
                    <option value="">Pilih Tim</option>
                    @foreach ($listTim as $tim)
                        <option value="{{ $tim }}" @selected(old('tim') === $tim)>{{ $tim }}</option>
                    @endforeach
                </select>
                @error('tim') <small class="mt-1 block text-sm text-red-600">{{ $message }}</small> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">File PDF (maks. 10MB)</label>
                <input class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" type="file" name="file" accept="application/pdf" required>
                @error('file') <small class="mt-1 block text-sm text-red-600">{{ $message }}</small> @enderror
            </div>
            <div>
                <button class="rounded-xl bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" type="submit">Kirim Permohonan</button>
            </div>
        </form>
    </div>
@endsection



@extends('layouts.app')

@section('content')
    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-3 text-xl font-bold text-slate-900">Cek Status Permohonan</h2>
        <form action="{{ route('user.status.check') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Token</label>
                <input class="w-full rounded-xl border border-slate-300 px-3 py-2 uppercase outline-none ring-0 focus:border-blue-500" type="text" name="token" maxlength="8" value="{{ old('token') }}" required>
                @error('token') <small class="mt-1 block text-sm text-red-600">{{ $message }}</small> @enderror
            </div>
            <div>
                <button class="rounded-xl bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" type="submit">Cek Status</button>
            </div>
        </form>
    </div>

    @isset($hasil)
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="mb-3 text-lg font-semibold text-slate-900">Hasil Pencarian</h3>
            <div class="space-y-1 text-sm text-slate-700">
                <p>Nama: <strong>{{ $hasil->nama }}</strong></p>
                <p>Tim: <strong>{{ $hasil->tim }}</strong></p>
                <p>Token: <strong>{{ $hasil->token }}</strong></p>
                <p>Status:
                    <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-800">{{ $statusTampil }}</span>
                </p>
                <p>Kedaluwarsa: <strong>{{ optional($hasil->kedaluwarsa)->format('d-m-Y H:i') }}</strong></p>
            </div>

            @if ($statusTampil === 'siap')
                <div class="mt-4">
                    <a class="inline-block rounded-xl bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700" href="{{ route('user.download', ['token' => $hasil->token]) }}">Download File TTE</a>
                </div>
            @endif
        </div>
    @endisset
@endsection

@extends('layouts.app')

@section('content')
    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <h2 class="text-xl font-bold text-slate-900">Dashboard Admin</h2>
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button class="rounded-xl bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" type="submit">Logout</button>
            </form>
        </div>
    </div>

    @if (session('sukses'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="font-medium text-emerald-700">{{ session('sukses') }}</p>
        </div>
    @endif

    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="GET" action="{{ route('admin.dashboard') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Filter Status</label>
                <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-blue-500">
                    <option value="">Semua</option>
                    @foreach (['tunggu', 'setuju', 'tolak', 'siap'] as $status)
                        <option value="{{ $status }}" @selected($filterStatus === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button class="rounded-xl bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" type="submit">Terapkan</button>
            </div>
        </form>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-500">
                        <th class="px-3 py-2">ID</th>
                        <th class="px-3 py-2">Nama</th>
                        <th class="px-3 py-2">Tim</th>
                        <th class="px-3 py-2">Token</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $row)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2">{{ $row->id }}</td>
                            <td class="px-3 py-2">{{ $row->nama }}</td>
                            <td class="px-3 py-2">{{ $row->tim }}</td>
                            <td class="px-3 py-2">{{ $row->token }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-800">{{ $row->status }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <a class="inline-block rounded-xl bg-slate-700 px-3 py-2 text-xs font-medium text-white hover:bg-slate-800" href="{{ route('admin.detail', $row) }}">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-3 text-slate-500">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $data->links() }}
        </div>
    </div>
@endsection

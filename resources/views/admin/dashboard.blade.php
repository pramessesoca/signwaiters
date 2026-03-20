@extends('layouts.app')

@section('content')
    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <h2 class="text-xl font-bold text-slate-900">Dashboard Admin</h2>
            <div class="flex flex-wrap items-center gap-2">
                <form action="{{ route('admin.request.generate_txt') }}" method="POST">
                    @csrf
                    @foreach (request()->query() as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700" type="submit">Generate TXT</button>
                </form>
                <form action="{{ route('admin.bulk_download.store') }}" method="POST">
                    @csrf
                    @foreach (request()->query() as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <button class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700" type="submit">Bulk Download</button>
                </form>
                <a href="{{ route('admin.bulk_upload.form') }}" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Bulk Upload</a>
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button class="rounded-xl bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" type="submit">Logout</button>
                </form>
            </div>
        </div>
    </div>

    @if (session('sukses'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="font-medium text-emerald-700">{{ session('sukses') }}</p>
        </div>
    @endif

    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="GET" action="{{ route('admin.dashboard') }}" class="grid grid-cols-1 gap-4 md:grid-cols-7">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Cari Nama / Token</label>
                <input
                    type="text"
                    name="q"
                    value="{{ $filterCari }}"
                    placeholder="contoh: prames / KULHK3ER"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-blue-500"
                >
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Filter Status</label>
                <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-blue-500">
                    <option value="">Semua</option>
                    @foreach (['tunggu', 'setuju', 'tolak', 'siap'] as $status)
                        <option value="{{ $status }}" @selected($filterStatus === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Filter Tim</label>
                <select name="tim" class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-blue-500">
                    <option value="">Semua Tim</option>
                    @foreach ($listTim as $tim)
                        <option value="{{ $tim }}" @selected($filterTim === $tim)>{{ $tim }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Dari</label>
                <input
                    type="date"
                    name="tgl_dari"
                    value="{{ $filterTglDari }}"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-blue-500"
                >
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Sampai</label>
                <input
                    type="date"
                    name="tgl_sampai"
                    value="{{ $filterTglSampai }}"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-blue-500"
                >
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Data / Halaman</label>
                <select name="per_page" class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-blue-500">
                    @foreach ([10, 15, 25, 50, 100] as $n)
                        <option value="{{ $n }}" @selected((int) $filterPerPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <div class="flex gap-2">
                    <button class="rounded-xl bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" type="submit">Terapkan</button>
                    <button class="rounded-xl bg-amber-500 px-4 py-2 font-medium text-white hover:bg-amber-600" type="submit" name="clear_tanggal" value="1">Clear Tanggal</button>
                    <a href="{{ route('admin.dashboard') }}" class="rounded-xl bg-slate-200 px-4 py-2 font-medium text-slate-700 hover:bg-slate-300">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        @php
            $sortUrl = function (string $field) use ($sortBy, $sortDir) {
                $nextDir = ($sortBy === $field && $sortDir === 'asc') ? 'desc' : 'asc';
                return route('admin.dashboard', array_merge(request()->query(), [
                    'sort' => $field,
                    'dir' => $nextDir,
                    'page' => 1,
                ]));
            };
            $sortArrow = function (string $field) use ($sortBy, $sortDir) {
                if ($sortBy !== $field) {
                    return '-';
                }

                return $sortDir === 'asc' ? '^' : 'v';
            };
        @endphp
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-500">
                        <th class="px-3 py-2">
                            <a href="{{ $sortUrl('id') }}" class="inline-flex items-center gap-1 hover:text-slate-700">
                                ID <span class="text-xs">{{ $sortArrow('id') }}</span>
                            </a>
                        </th>
                        <th class="px-3 py-2">
                            <a href="{{ $sortUrl('nama') }}" class="inline-flex items-center gap-1 hover:text-slate-700">
                                Nama <span class="text-xs">{{ $sortArrow('nama') }}</span>
                            </a>
                        </th>
                        <th class="px-3 py-2">
                            <a href="{{ $sortUrl('tim') }}" class="inline-flex items-center gap-1 hover:text-slate-700">
                                Tim <span class="text-xs">{{ $sortArrow('tim') }}</span>
                            </a>
                        </th>
                        <th class="px-3 py-2">
                            <a href="{{ $sortUrl('token') }}" class="inline-flex items-center gap-1 hover:text-slate-700">
                                Token <span class="text-xs">{{ $sortArrow('token') }}</span>
                            </a>
                        </th>
                        <th class="px-3 py-2">
                            <a href="{{ $sortUrl('status') }}" class="inline-flex items-center gap-1 hover:text-slate-700">
                                Status <span class="text-xs">{{ $sortArrow('status') }}</span>
                            </a>
                        </th>
                        <th class="px-3 py-2">
                            <a href="{{ $sortUrl('created_at') }}" class="inline-flex items-center gap-1 hover:text-slate-700">
                                Tanggal <span class="text-xs">{{ $sortArrow('created_at') }}</span>
                            </a>
                        </th>
                        <th class="px-3 py-2">Nama File</th>
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
                                @php
                                    $badge = match($row->status) {
                                        'tunggu' => 'bg-amber-100 text-amber-700',
                                        'setuju' => 'bg-blue-100 text-blue-700',
                                        'tolak' => 'bg-rose-100 text-rose-700',
                                        'siap' => 'bg-emerald-100 text-emerald-700',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge }}">{{ $row->status }}</span>
                            </td>
                            <td class="px-3 py-2">{{ optional($row->created_at)->format('d-m-Y H:i') }}</td>
                            <td class="px-3 py-2">{{ basename($row->file_req) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-2">
                                    <a class="inline-block rounded-xl bg-slate-700 px-3 py-2 text-xs font-medium text-white hover:bg-slate-800" href="{{ route('admin.detail', $row) }}">Detail</a>
                                    <form action="{{ route('admin.request.generate_token', $row) }}" method="POST">
                                        @csrf
                                        <button class="rounded-xl bg-amber-500 px-3 py-2 text-xs font-medium text-white hover:bg-amber-600" type="submit">Generate Token</button>
                                    </form>
                                    <form action="{{ route('admin.request.hapus', $row) }}" method="POST" onsubmit="return confirm('Yakin hapus permohonan ini?')">
                                        @csrf
                                        <button class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-medium text-white hover:bg-rose-700" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-3 text-slate-500">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-600">
                Menampilkan <strong>{{ $data->firstItem() ?? 0 }}</strong> - <strong>{{ $data->lastItem() ?? 0 }}</strong>
                dari <strong>{{ $data->total() }}</strong> data
            </p>
            <div>
                {{ $data->links() }}
            </div>
        </div>
    </div>
@endsection

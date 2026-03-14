@extends('layouts.app')

@section('content')
    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Bulk Upload ZIP TTE</h2>
                <p class="text-sm text-slate-600">Upload ZIP (boleh berisi ZIP + PDF). Format file PDF: <code>TOKEN_namafile.pdf</code>.</p>
            </div>
            <div class="flex gap-2">
                <form action="{{ route('admin.bulk_upload.clear') }}" method="POST" onsubmit="return confirm('Hapus semua riwayat bulk upload selesai?')">
                    @csrf
                    <button class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700" type="submit">Clear Bulk Upload</button>
                </form>
                <form action="{{ route('admin.bulk.clear_all') }}" method="POST" onsubmit="return confirm('HAPUS SEMUA BULK (termasuk queued/running) dan semua file sementara?')">
                    @csrf
                    <button class="rounded-xl bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800" type="submit">Clear All Bulk</button>
                </form>
                <a href="{{ route('admin.dashboard') }}" class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Kembali Dashboard</a>
            </div>
        </div>
    </div>

    @if (session('sukses'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="font-medium text-emerald-700">{{ session('sukses') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4">
            @foreach ($errors->all() as $error)
                <p class="text-sm text-rose-700">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form action="{{ route('admin.bulk_upload.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-4 sm:flex-row sm:items-end">
            @csrf
            <div class="flex-1">
                <label class="mb-1 block text-sm font-medium text-slate-700">File ZIP (maks. 100MB)</label>
                <input type="file" name="zip_file" accept=".zip,application/zip" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500" required>
            </div>
            <div>
                <button class="rounded-xl bg-indigo-600 px-5 py-2.5 font-medium text-white hover:bg-indigo-700" type="submit">Mulai Proses</button>
            </div>
        </form>
    </div>

    @php
        $bulkId = request()->query('bulk_upload_id');
    @endphp

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="mb-3 text-lg font-semibold text-slate-900">Progress Live</h3>
        <div class="mb-3 h-3 w-full overflow-hidden rounded-full bg-slate-200">
            <div id="progress-bar" class="h-full w-0 bg-indigo-600 transition-all"></div>
        </div>
        <p id="progress-text" class="mb-4 text-sm text-slate-700">Belum ada proses berjalan.</p>

        <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-5">
            <div class="rounded-xl bg-slate-100 p-3"><p class="text-slate-500">Total</p><p id="sum-total" class="font-semibold text-slate-900">0</p></div>
            <div class="rounded-xl bg-slate-100 p-3"><p class="text-slate-500">Processed</p><p id="sum-processed" class="font-semibold text-slate-900">0</p></div>
            <div class="rounded-xl bg-emerald-50 p-3"><p class="text-emerald-700">Berhasil</p><p id="sum-success" class="font-semibold text-emerald-800">0</p></div>
            <div class="rounded-xl bg-rose-50 p-3"><p class="text-rose-700">Gagal</p><p id="sum-failed" class="font-semibold text-rose-800">0</p></div>
            <div class="rounded-xl bg-amber-50 p-3"><p class="text-amber-700">Token Tidak Ditemukan</p><p id="sum-token-miss" class="font-semibold text-amber-800">0</p></div>
        </div>
        <div class="mt-3 rounded-xl bg-amber-50 p-3 text-sm">
            <p class="text-amber-700">Format Nama Invalid: <strong id="sum-format-invalid">0</strong></p>
        </div>

        <div class="mt-4">
            <p class="mb-2 text-sm font-medium text-slate-700">Error Ringkas (maks. 20 baris):</p>
            <ul id="error-list" class="list-disc space-y-1 pl-5 text-sm text-rose-700"></ul>
        </div>
    </div>

    @if ($bulkId)
        <script>
            (function() {
                const bulkId = @json($bulkId);
                const statusUrl = @json(route('admin.bulk_upload.status', ['bulkUpload' => '__ID__']));

                function setText(id, value) {
                    const el = document.getElementById(id);
                    if (el) el.textContent = String(value);
                }

                function render(data) {
                    const total = Number(data.total_file || 0);
                    const processed = Number(data.processed || 0);
                    const pct = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;

                    document.getElementById('progress-bar').style.width = pct + '%';
                    setText('progress-text', `Status: ${data.status} | ${processed}/${total} (${pct}%)`);
                    setText('sum-total', total);
                    setText('sum-processed', processed);
                    setText('sum-success', data.success || 0);
                    setText('sum-failed', data.failed || 0);
                    setText('sum-token-miss', (data.summary && data.summary.token_tidak_ditemukan) || 0);
                    setText('sum-format-invalid', (data.summary && data.summary.format_invalid) || 0);

                    const list = document.getElementById('error-list');
                    list.innerHTML = '';
                    (data.errors || []).slice(-20).forEach(item => {
                        const li = document.createElement('li');
                        li.textContent = `${item.file || '-'} : ${item.reason}`;
                        list.appendChild(li);
                    });
                }

                async function poll() {
                    try {
                        const res = await fetch(statusUrl.replace('__ID__', bulkId), {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (!res.ok) return;
                        const data = await res.json();
                        render(data);

                        if (data.status === 'done' || data.status === 'failed') {
                            return;
                        }
                    } catch (_) {}
                    setTimeout(poll, 1000);
                }

                poll();
            })();
        </script>
    @endif
@endsection


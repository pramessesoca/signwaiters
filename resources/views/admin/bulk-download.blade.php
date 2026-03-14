@extends('layouts.app')

@section('content')
    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Bulk Download ZIP</h2>
                <p class="text-sm text-slate-600">Proses membuat satu ZIP dari file request (`file_req`) sesuai filter dashboard.</p>
            </div>
            <div class="flex gap-2">
                <form action="{{ route('admin.bulk_download.clear') }}" method="POST" onsubmit="return confirm('Hapus semua riwayat bulk download selesai?')">
                    @csrf
                    <button class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700" type="submit">Clear Bulk Download</button>
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

    @php
        $bulkId = request()->query('bulk_download_id');
    @endphp

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="mb-3 text-lg font-semibold text-slate-900">Progress Live</h3>
        <div class="mb-3 h-3 w-full overflow-hidden rounded-full bg-slate-200">
            <div id="progress-bar" class="h-full w-0 bg-cyan-600 transition-all"></div>
        </div>
        <p id="progress-text" class="mb-4 text-sm text-slate-700">Belum ada proses berjalan.</p>

        <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div class="rounded-xl bg-slate-100 p-3"><p class="text-slate-500">Total</p><p id="sum-total" class="font-semibold text-slate-900">0</p></div>
            <div class="rounded-xl bg-slate-100 p-3"><p class="text-slate-500">Processed</p><p id="sum-processed" class="font-semibold text-slate-900">0</p></div>
            <div class="rounded-xl bg-emerald-50 p-3"><p class="text-emerald-700">Berhasil</p><p id="sum-success" class="font-semibold text-emerald-800">0</p></div>
            <div class="rounded-xl bg-rose-50 p-3"><p class="text-rose-700">Gagal</p><p id="sum-failed" class="font-semibold text-rose-800">0</p></div>
        </div>

        @if ($bulkId)
            <div class="mt-4">
                <a id="manual-download" href="#" class="hidden rounded-xl bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700">Download Ulang ZIP</a>
            </div>
        @endif

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div>
                <p class="mb-2 text-sm font-medium text-slate-700">Log file masuk ZIP (maks. 30):</p>
                <ul id="result-list" class="list-disc space-y-1 pl-5 text-sm text-emerald-700"></ul>
            </div>
            <div>
                <p class="mb-2 text-sm font-medium text-slate-700">Error (maks. 30):</p>
                <ul id="error-list" class="list-disc space-y-1 pl-5 text-sm text-rose-700"></ul>
            </div>
        </div>
    </div>

    @if ($bulkId)
        <script>
            (function () {
                const bulkId = @json($bulkId);
                const statusUrl = @json(route('admin.bulk_download.status', ['bulkDownload' => '__ID__']));
                const fileUrl = @json(route('admin.bulk_download.file', ['bulkDownload' => '__ID__']));
                let autoDownloaded = false;

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

                    const resultList = document.getElementById('result-list');
                    resultList.innerHTML = '';
                    (data.results || []).slice(-30).forEach(item => {
                        const li = document.createElement('li');
                        li.textContent = `${item.zip_name} (token: ${item.token})`;
                        resultList.appendChild(li);
                    });

                    const errorList = document.getElementById('error-list');
                    errorList.innerHTML = '';
                    (data.errors || []).slice(-30).forEach(item => {
                        const li = document.createElement('li');
                        li.textContent = `${item.file || '-'} : ${item.reason}`;
                        errorList.appendChild(li);
                    });

                    const dl = document.getElementById('manual-download');
                    if (data.status === 'done' && data.has_file) {
                        const url = fileUrl.replace('__ID__', bulkId);
                        dl.classList.remove('hidden');
                        dl.href = url;
                        if (!autoDownloaded) {
                            autoDownloaded = true;
                            window.location.href = url;
                        }
                    }
                }

                async function poll() {
                    try {
                        const res = await fetch(statusUrl.replace('__ID__', bulkId), {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (!res.ok) return;
                        const data = await res.json();
                        render(data);
                        if (data.status === 'done' || data.status === 'failed') return;
                    } catch (_) {}
                    setTimeout(poll, 1000);
                }

                poll();
            })();
        </script>
    @endif
@endsection


@extends('layouts.app')

@section('content')
    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="text-xl font-bold text-slate-900">Form Permohonan TTE</h2>
        <p class="mt-1 text-sm text-slate-600">Isi data lalu upload file sesuai mode: multi PDF (otomatis jadi ZIP) atau ZIP langsung.</p>
    </div>

    @if (session('sukses'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <p class="font-semibold text-emerald-700">{{ session('sukses') }}</p>
            <p class="mt-1 text-slate-700">Token Anda: <strong>{{ session('token') }}</strong></p>
            <p class="text-sm text-slate-600">Simpan token ini untuk cek status dan download file.</p>
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form id="form-permohonan" action="{{ route('user.request.submit') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
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
                <label class="mb-2 block text-sm font-medium text-slate-700">Mode Upload</label>
                <div class="flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="mode_upload" value="multi_pdf" checked>
                        Multi PDF (maks. 10 file, tiap file maks. 10MB)
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="mode_upload" value="zip">
                        ZIP langsung
                    </label>
                </div>
                @error('mode_upload') <small class="mt-1 block text-sm text-red-600">{{ $message }}</small> @enderror
            </div>
            <div id="multi-pdf-group">
                <label class="mb-1 block text-sm font-medium text-slate-700">Upload PDF (multiple)</label>
                <input id="input-pdf-files" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" type="file" accept="application/pdf,.pdf" multiple>
                <small class="mt-1 block text-xs text-slate-500">Sistem akan membuat ZIP otomatis di browser sebelum dikirim.</small>
            </div>
            <div id="zip-group" class="hidden">
                <label class="mb-1 block text-sm font-medium text-slate-700">Upload ZIP</label>
                <input id="input-zip-file" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" type="file" accept=".zip,application/zip">
                <small class="mt-1 block text-xs text-slate-500">Validasi isi ZIP tidak diperiksa mendalam oleh sistem.</small>
            </div>
            <div>
                <input id="file-zip-generated" type="file" name="file_zip" class="hidden" required>
                @error('file_zip') <small class="mt-1 block text-sm text-red-600">{{ $message }}</small> @enderror
                <small id="upload-hint" class="mt-1 block text-xs text-slate-500">Pilih file sesuai mode upload.</small>
            </div>
            <div id="client-error" class="hidden rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-600"></div>
            <div id="preparing-zip" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                Sedang menyiapkan ZIP, mohon tunggu...
            </div>
            <div>
                <button id="btn-submit" class="rounded-xl bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" type="submit">Kirim Permohonan</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
    <script>
        (function () {
            const MAX_FILES = 10;
            const MAX_FILE_SIZE = 10 * 1024 * 1024;

            const form = document.getElementById('form-permohonan');
            const btnSubmit = document.getElementById('btn-submit');
            const modeInputs = document.querySelectorAll('input[name=\"mode_upload\"]');
            const multiGroup = document.getElementById('multi-pdf-group');
            const zipGroup = document.getElementById('zip-group');
            const inputPdfFiles = document.getElementById('input-pdf-files');
            const inputZipFile = document.getElementById('input-zip-file');
            const generatedZipInput = document.getElementById('file-zip-generated');
            const clientError = document.getElementById('client-error');
            const preparingZip = document.getElementById('preparing-zip');
            const uploadHint = document.getElementById('upload-hint');

            function currentMode() {
                const checked = document.querySelector('input[name=\"mode_upload\"]:checked');
                return checked ? checked.value : 'multi_pdf';
            }

            function showError(message) {
                clientError.textContent = message;
                clientError.classList.remove('hidden');
            }

            function clearError() {
                clientError.textContent = '';
                clientError.classList.add('hidden');
            }

            function setBusy(isBusy) {
                btnSubmit.disabled = isBusy;
                preparingZip.classList.toggle('hidden', !isBusy);
                btnSubmit.classList.toggle('opacity-70', isBusy);
                btnSubmit.classList.toggle('cursor-not-allowed', isBusy);
            }

            function syncModeUI() {
                const mode = currentMode();
                const isMulti = mode === 'multi_pdf';

                multiGroup.classList.toggle('hidden', !isMulti);
                zipGroup.classList.toggle('hidden', isMulti);
                inputPdfFiles.required = isMulti;
                inputZipFile.required = !isMulti;
                generatedZipInput.required = true;
                uploadHint.textContent = isMulti
                    ? 'Mode multi PDF aktif. File akan dijadikan ZIP otomatis saat submit.'
                    : 'Mode ZIP aktif. File ZIP akan dikirim langsung.';
                clearError();
            }

            modeInputs.forEach((input) => {
                input.addEventListener('change', syncModeUI);
            });

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                clearError();
                setBusy(false);

                const mode = currentMode();
                const dt = new DataTransfer();

                if (mode === 'multi_pdf') {
                    const files = Array.from(inputPdfFiles.files || []);
                    if (files.length === 0) {
                        showError('Minimal upload 1 file PDF.');
                        return;
                    }

                    if (files.length > MAX_FILES) {
                        showError('Maksimal 10 file PDF per permohonan.');
                        return;
                    }

                    for (const file of files) {
                        const lowerName = file.name.toLowerCase();
                        if (!lowerName.endsWith('.pdf')) {
                            showError('Semua file pada mode multi harus berformat PDF.');
                            return;
                        }
                        if (file.size > MAX_FILE_SIZE) {
                            showError(`Ukuran file ${file.name} melebihi 10MB.`);
                            return;
                        }
                    }

                    if (typeof JSZip === 'undefined') {
                        showError('Library ZIP belum tersedia. Refresh halaman lalu coba lagi.');
                        return;
                    }

                    setBusy(true);
                    try {
                        const zip = new JSZip();
                        files.forEach((file) => zip.file(file.name, file));

                        const blob = await zip.generateAsync({ type: 'blob', compression: 'DEFLATE' });
                        const zipFile = new File([blob], 'permohonan-multi.pdfs.zip', { type: 'application/zip' });
                        dt.items.add(zipFile);
                    } catch (error) {
                        setBusy(false);
                        showError('Gagal membuat ZIP di browser. Coba ulangi.');
                        return;
                    }
                } else {
                    const file = (inputZipFile.files || [])[0];
                    if (!file) {
                        showError('Silakan pilih file ZIP terlebih dahulu.');
                        return;
                    }

                    const lowerName = file.name.toLowerCase();
                    if (!lowerName.endsWith('.zip')) {
                        showError('File harus berformat ZIP.');
                        return;
                    }
                    dt.items.add(file);
                }

                generatedZipInput.files = dt.files;
                form.submit();
            });

            syncModeUI();
        })();
    </script>
@endsection



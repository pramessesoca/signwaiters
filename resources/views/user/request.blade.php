@extends('layouts.app')

@section('content')
    <div class="mb-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="text-xl font-bold text-slate-900">Form Permohonan TTE</h2>
        <p class="mt-1 text-sm text-slate-600">Pastikan file yang dikirim sesuai dengan ketentuan agar dapat diproses</p>
    </div>

    <div class="mb-4 rounded-2xl border border-blue-200 bg-blue-50 p-5">
        <h3 class="text-base font-semibold text-blue-900">Petunjuk Penggunaan</h3>
        <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-blue-900">
            <li>Isi <strong>Nama</strong> dan pilih <strong>Tim Kerja</strong></li>
            <li>Bisa upload PDF (maks. 10 file) atau 1 ZIP</li>
            <li>Maks ukuran 20 MB per file PDF</li>
            <li>Jika upload ZIP: isi ZIP wajib hanya PDF, tanpa folder, maksimal 10 file</li>
            <li>Setelah berhasil, simpan token yang muncul untuk cek status dan unduh hasil</li>
        </ol>
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
                <label class="mb-1 block text-sm font-medium text-slate-700">Upload File</label>
                <input id="input-files" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" type="file" accept="application/pdf,.pdf,.zip,application/zip" multiple required>
            </div>
            <div>
                <input id="file-zip-generated" type="file" name="file_zip" class="hidden">
                <input id="upload-kind" type="hidden" name="upload_kind" value="">
                @error('file_zip') <small class="mt-1 block text-sm text-red-600">{{ $message }}</small> @enderror
                @error('upload_kind') <small class="mt-1 block text-sm text-red-600">{{ $message }}</small> @enderror
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
            const MAX_FILE_SIZE = 20 * 1024 * 1024;

            const form = document.getElementById('form-permohonan');
            const btnSubmit = document.getElementById('btn-submit');
            const inputFiles = document.getElementById('input-files');
            const generatedZipInput = document.getElementById('file-zip-generated');
            const uploadKindInput = document.getElementById('upload-kind');
            const clientError = document.getElementById('client-error');
            const preparingZip = document.getElementById('preparing-zip');

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

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                clearError();
                setBusy(false);

                const dt = new DataTransfer();
                const files = Array.from(inputFiles.files || []);
                if (files.length === 0) {
                    showError('Silakan pilih minimal 1 file.');
                    return;
                }

                const zipFiles = files.filter((file) => file.name.toLowerCase().endsWith('.zip'));
                const pdfFiles = files.filter((file) => file.name.toLowerCase().endsWith('.pdf'));

                if (zipFiles.length > 1) {
                    showError('Upload ZIP hanya boleh 1 file.');
                    return;
                }

                if (zipFiles.length === 1 && pdfFiles.length > 0) {
                    showError('Tidak bisa menggabungkan ZIP dan PDF dalam satu upload.');
                    return;
                }

                if (zipFiles.length === 1) {
                    uploadKindInput.value = 'zip_direct';
                    dt.items.add(zipFiles[0]);
                } else {
                    if (pdfFiles.length !== files.length) {
                        showError('File yang diizinkan hanya PDF atau ZIP.');
                        return;
                    }

                    if (pdfFiles.length > MAX_FILES) {
                        showError('Maksimal 10 file PDF per permohonan.');
                        return;
                    }

                    for (const file of pdfFiles) {
                        if (file.size > MAX_FILE_SIZE) {
                            showError(`Ukuran file ${file.name} melebihi 20MB.`);
                            return;
                        }
                    }

                    if (pdfFiles.length === 1) {
                        uploadKindInput.value = 'single_pdf';
                        dt.items.add(pdfFiles[0]);
                    } else {
                        uploadKindInput.value = 'multi_pdf';
                        if (typeof JSZip === 'undefined') {
                            showError('Library ZIP belum tersedia. Refresh halaman lalu coba lagi.');
                            return;
                        }

                        setBusy(true);
                        try {
                            const zip = new JSZip();
                            pdfFiles.forEach((file) => zip.file(file.name, file));
                            const blob = await zip.generateAsync({ type: 'blob', compression: 'DEFLATE' });
                            const zipFile = new File([blob], 'permohonan-pdf.zip', { type: 'application/zip' });
                            dt.items.add(zipFile);
                        } catch (error) {
                            setBusy(false);
                            showError('Gagal membuat ZIP di browser. Coba ulangi.');
                            return;
                        }
                    }
                }

                generatedZipInput.files = dt.files;
                form.submit();
            });
        })();
    </script>
@endsection

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Signwaiters' }}</title>
    @vite(['resources/css/tailwind.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
    <nav class="bg-white shadow-sm ring-1 ring-slate-200">
        <div class="mx-auto flex max-w-6xl gap-2 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('user.request.form') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Permohonan</a>
            <a href="{{ route('user.status.form') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Cek Token</a>
            <a href="{{ route('admin.login.form') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Admin</a>
        </div>
    </nav>
    <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
        @yield('content')
    </main>
</body>
</html>

@extends('layouts.app')

@section('content')
    <div class="mx-auto mt-8 max-w-md rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-4 text-xl font-bold text-slate-900">Login Admin</h2>
        <form action="{{ route('admin.login.submit') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Username</label>
                <input class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-blue-500" type="text" name="username" value="{{ old('username') }}" required>
                @error('username') <small class="mt-1 block text-sm text-red-600">{{ $message }}</small> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                <input class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-blue-500" type="password" name="password" required>
            </div>
            <div>
                <button class="w-full rounded-xl bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700" type="submit">Masuk</button>
            </div>
        </form>
    </div>
@endsection

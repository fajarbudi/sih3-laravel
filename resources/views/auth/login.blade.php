<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Masuk · Portal SIH3 Wilayah Sungai Serayu Opak</title>
    <x-layouts.tailwind />
</head>
<body class="flex min-h-screen flex-col bg-white font-sans text-sm text-gray-900 antialiased">
    <main class="flex flex-1 items-center justify-center px-4 py-12">
        <div class="w-full max-w-xs">
            <img src="{{ asset('images/logo-header-admin.png') }}" alt="Portal SIH3 Wilayah Sungai Serayu Opak" width="256" height="73" class="mx-auto h-12 w-auto">
            <h1 class="mt-8 text-center text-xl font-semibold">Masuk ke Portal SIH3</h1>
            <p class="mt-1.5 text-center text-gray-600">Panel pengelola data hidrologi BBWS Serayu Opak</p>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
                @csrf
                <div>
                    <label for="username" class="text-gray-700">Nama login<span class="text-red-600">*</span></label>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username" @error('username') aria-invalid="true" aria-describedby="username-error" @enderror
                        @class([
                            'mt-1.5 block min-h-11 w-full rounded-md border bg-white px-3 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-100 focus:outline-none lg:min-h-10',
                            'border-red-600' => $errors->has('username'),
                            'border-gray-200 hover:border-gray-300' => ! $errors->has('username'),
                        ])>
                    @error('username')
                        <p id="username-error" class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="text-gray-700">Kata sandi<span class="text-red-600">*</span></label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                        class="mt-1.5 block min-h-11 w-full rounded-md border border-gray-200 bg-white px-3 text-sm hover:border-gray-300 focus:border-primary-500 focus:ring-2 focus:ring-primary-100 focus:outline-none lg:min-h-10">
                </div>

                <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm">
                    <input type="checkbox" name="ingat" value="1" class="size-4 rounded border-gray-300 accent-primary-500">
                    Ingat saya
                </label>

                <button type="submit" class="min-h-11 w-full rounded-md bg-primary-500 px-3 font-medium text-white shadow-xs hover:bg-primary-600 active:bg-primary-700 lg:min-h-10">
                    Masuk
                </button>
            </form>
        </div>
    </main>
    <x-admin.footer />
</body>
</html>

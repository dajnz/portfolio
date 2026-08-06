<!DOCTYPE html>
@php use App\Features\Shared\Infra\Middleware\CspNonce; @endphp
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="{{ ($page['props']['theme'] ?? 'light') === 'dark' ? 'dark' : '' }}"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Portfolio') }}</title>
    <script nonce="{{ app(CspNonce::class)->getValue() }}">
        (function () {
            var c = document.cookie.match(/(?:^|;\s*)theme=(light|dark)/);
            var t = c ? c[1] : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            if (t === 'dark') document.documentElement.classList.add('dark');
            document.cookie = 'theme=' + t + ';path=/;max-age=31536000;SameSite=Lax';
        })();
    </script>
    @inertiaHead
    @vite(['resources/js/app.ts', 'resources/css/app.css'])
</head>
<body>
    @inertia
</body>
</html>

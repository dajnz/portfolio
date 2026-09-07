{{--
    Shared error-page layout (error-handling.md:196-212, seo.md:260-279).
    Extended by resources/views/errors/{403,404,419,422,429,500,503}.blade.php.

    Self-contained: inline BEM CSS mirroring the public design tokens
    (docs/mockups/public/404.html + tokens.css) — no external dependencies,
    no JavaScript. The theme class is applied server-side from the theme
    cookie (ThemeMiddleware); without it, `prefers-color-scheme` decides.
    404 pages carry a `noindex, follow` robots meta (seo.md:332).
--}}
@php
    $status = ($exception ?? null)?->getStatusCode() ?? 500;
    $message = trans()->has("errors.{$status}") ? __("errors.{$status}") : __('errors.server_error');
    $requestId = request()->attributes->get('request_id');
    $theme = request()->attributes->get('theme');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" @if ($theme) class="{{ $theme }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, follow">
    <title>Error {{ $status }} — {{ config('app.name') }}</title>
    <style>
        :root {
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-size-sm: 0.875rem;
            --font-size-lg: 1.125rem;
            --font-weight-semibold: 600;
            --font-weight-extrabold: 800;
            --line-height-relaxed: 1.75;
            --letter-spacing-tight: -0.02em;

            --color-bg-primary: oklch(0.98 0.01 0.06);
            --color-bg-secondary: oklch(0.94 0.01 0.11);
            --color-bg-hero: oklch(0.98 0.01 260.46);
            --color-text-primary: oklch(0.24 0.02 89.88);
            --color-text-secondary: oklch(0.37 0.03 101.67);
            --color-accent: oklch(0.52 0.21 262);
            --color-accent-hover: oklch(0.58 0.23 262.07);
            --gradient-hero-color-1: color-mix(in oklab, oklab(0.85 -0.01 0.2) 40%, transparent);
            --gradient-hero-color-2: color-mix(in oklab, lab(63.3038% -18.433 -51.0407) 27%, transparent);
            --gradient-hero-color-3: color-mix(in oklab, lab(56.9303% 76.8162 -8.07021) 25%, transparent);

            --space-md: 1rem;
            --space-lg: 1.5rem;
            --space-xl: 2rem;
            --space-3xl: 4rem;
            --radius-md: 0.625rem;
            --radius-xl: 1.5rem;
            --glass-bg: color-mix(in oklab, var(--color-bg-secondary) 85%, transparent);
            --glass-border: rgba(22, 33, 27, 0.10);
            --glass-blur: 16px;
            --glass-shadow: 0 8px 32px rgba(22, 33, 27, 0.10);
            --btn-padding-y: 0.625rem;
            --btn-padding-x: 1.25rem;
            --btn-font-size: 0.875rem;
            --btn-height: 2.75rem;
            --btn-radius: var(--radius-md);
            --btn-transition: all 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --focus-ring-width: 3px;
            --focus-ring-color: oklch(0.63 0.19 268.45);
            --focus-ring-offset: 2px;
        }

        :root.dark {
            --color-bg-primary: oklch(0.18 0.01 17.05);
            --color-bg-secondary: oklch(0.23 0.01 0);
            --color-bg-hero: oklch(0.19 0.03 327 / 1);
            --color-text-primary: oklch(0.96 0.01 0);
            --color-text-secondary: oklch(0.76 0.01 50.93);
            --color-accent: oklch(0.5 0.23 266.5);
            --color-accent-hover: oklch(0.54 0.25 266.52);
            --gradient-hero-color-1: color-mix(in oklab, oklab(0.85 -0.01 0.2) 20%, transparent);
            --gradient-hero-color-2: color-mix(in oklab, lab(63.3038% -18.433 -51.0407) 20%, transparent);
            --gradient-hero-color-3: color-mix(in oklab, lab(56.9303% 76.8162 -8.07021) 20%, transparent);
            --glass-bg: oklch(0.23 0.01 0 / 0.68);
            --glass-border: rgba(168, 181, 169, 0.14);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
            --focus-ring-color: oklch(0.55 0.27 270.12);
        }

        @media (prefers-color-scheme: dark) {
            :root:not(.light) {
                --color-bg-primary: oklch(0.18 0.01 17.05);
                --color-bg-secondary: oklch(0.23 0.01 0);
                --color-bg-hero: oklch(0.19 0.03 327 / 1);
                --color-text-primary: oklch(0.96 0.01 0);
                --color-text-secondary: oklch(0.76 0.01 50.93);
                --color-accent: oklch(0.5 0.23 266.5);
                --color-accent-hover: oklch(0.54 0.25 266.52);
                --gradient-hero-color-1: color-mix(in oklab, oklab(0.85 -0.01 0.2) 20%, transparent);
                --gradient-hero-color-2: color-mix(in oklab, lab(63.3038% -18.433 -51.0407) 20%, transparent);
                --gradient-hero-color-3: color-mix(in oklab, lab(56.9303% 76.8162 -8.07021) 20%, transparent);
                --glass-bg: oklch(0.23 0.01 0 / 0.68);
                --glass-border: rgba(168, 181, 169, 0.14);
                --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
                --focus-ring-color: oklch(0.55 0.27 270.12);
            }
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }

        body {
            font-family: var(--font-sans);
            font-size: var(--font-size-sm);
            font-weight: 400;
            line-height: 1.6;
            color: var(--color-text-primary);
            background-color: var(--color-bg-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        a { color: inherit; text-decoration: none; }

        :focus-visible {
            outline: var(--focus-ring-width) solid var(--focus-ring-color);
            outline-offset: var(--focus-ring-offset);
            border-radius: var(--radius-md);
        }

        .error-page {
            position: relative;
            overflow: hidden;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-3xl) var(--space-lg);
            background: var(--color-bg-hero);
        }

        .gradient-hero__yellow-blue,
        .gradient-hero__red {
            position: absolute;
            inset: 0;
            z-index: 0;
        }

        .gradient-hero__yellow-blue::before,
        .gradient-hero__yellow-blue::after,
        .gradient-hero__red::before {
            inset: 0;
            content: "";
            position: absolute;
        }

        .gradient-hero__yellow-blue::before {
            background: radial-gradient(30% 120% at 35% 100%, var(--gradient-hero-color-1) 0, transparent 100%);
        }

        .gradient-hero__yellow-blue::after {
            background: radial-gradient(30% 80% at 53% 100%, var(--gradient-hero-color-2) 0, transparent 100%);
        }

        .gradient-hero__red::before {
            background: radial-gradient(30% 100% at 65% 0%, var(--gradient-hero-color-3) 0, transparent 100%);
        }

        .error-page__panel {
            position: relative;
            z-index: 1;
            max-width: 560px;
            width: 100%;
            text-align: center;
            padding: var(--space-3xl) var(--space-xl);
            background: var(--glass-bg);
            backdrop-filter: blur(var(--glass-blur));
            -webkit-backdrop-filter: blur(var(--glass-blur));
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--glass-shadow);
        }

        .error-page__code {
            font-size: clamp(4rem, 12vw, 7rem);
            font-weight: var(--font-weight-extrabold);
            line-height: 1;
            letter-spacing: var(--letter-spacing-tight);
            background: linear-gradient(120deg, var(--color-accent), var(--color-accent) 55%, var(--color-accent-hover));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: var(--space-md);
        }

        .error-page__message {
            font-size: var(--font-size-lg);
            line-height: var(--line-height-relaxed);
            color: var(--color-text-secondary);
            margin-bottom: var(--space-xl);
        }

        .error-page__reference {
            font-size: var(--font-size-sm);
            line-height: var(--line-height-relaxed);
            color: var(--color-text-secondary);
            margin-bottom: var(--space-xl);
            word-break: break-all;
        }

        .error-page__action {
            display: inline-flex;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-md);
            height: var(--btn-height);
            padding: var(--btn-padding-y) var(--btn-padding-x);
            font-size: var(--btn-font-size);
            font-weight: var(--font-weight-semibold);
            border-radius: var(--btn-radius);
            border: 1px solid transparent;
            cursor: pointer;
            transition: var(--btn-transition);
            white-space: nowrap;
            user-select: none;
        }

        .btn--primary {
            background: var(--color-accent);
            color: #fff;
            border-color: var(--color-accent);
        }

        .btn--primary:hover { background: var(--color-accent-hover); border-color: var(--color-accent-hover); }

        .btn--lg {
            --btn-height: 3.25rem;
            --btn-padding-y: 0.75rem;
            --btn-padding-x: 2rem;
            --btn-font-size: 1.125rem;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
<main class="error-page" data-component="error-page" data-status="{{ $status }}" data-testid="error-page">
    <div class="gradient-hero__yellow-blue" aria-hidden="true"></div>
    <div class="gradient-hero__red" aria-hidden="true"></div>

    <div class="error-page__panel">
        <p class="error-page__code" data-testid="error-page-code">{{ $status }}</p>
        <p class="error-page__message">{{ $message }}</p>
        @if ($requestId)
            <p class="error-page__reference">{{ __('errors.reference_id', ['request_id' => $requestId]) }}</p>
        @endif
        <div class="error-page__action">
            <a class="btn btn--primary btn--lg" href="{{ url('/') }}" data-testid="error-page-home">{{ __('errors.back_home') }}</a>
        </div>
    </div>
</main>
</body>
</html>

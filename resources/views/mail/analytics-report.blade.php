<!doctype html>
{{-- Queued analytics digest mail (analytics.md:214-219, ADR-0017, 11.3.5):
     a simple HTML table with the previous day's key metrics and the
     top article by views. All values are aggregated numbers and
     translated labels — no user-controlled markup. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 24px; background: #f6f7f9; color: #1e293b; }
        .container { max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .period { color: #64748b; font-size: 13px; margin-bottom: 20px; }
        h2 { font-size: 14px; margin: 24px 0 8px; color: #334155; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        th { color: #64748b; font-weight: 600; }
        td.value { text-align: right; font-variant-numeric: tabular-nums; }
        .muted { color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ __('mail.analytics_title') }}</h1>
        <div class="period">{{ $periodLabel }}</div>

        <table>
            <tr><th>{{ __('mail.analytics_total_page_views') }}</th><td class="value">{{ $digest->pageViews }}</td></tr>
            <tr><th>{{ __('mail.analytics_unique_visitors') }}</th><td class="value">{{ $digest->uniqueVisitors }}</td></tr>
            <tr><th>{{ __('mail.analytics_feedback_submissions') }}</th><td class="value">{{ $digest->feedbackSubmissions }}</td></tr>
        </table>

        <h2>{{ __('mail.analytics_top_article') }}</h2>
        @if ($digest->topArticle !== null)
            <table>
                <tr>
                    <th>{{ __('mail.analytics_article') }}</th>
                    <th>{{ __('mail.analytics_views') }}</th>
                </tr>
                <tr>
                    <td>{{ $digest->topArticle->title }}</td>
                    <td class="value">{{ $digest->topArticle->views }}</td>
                </tr>
            </table>
        @else
            <div class="muted">{{ __('mail.analytics_none') }}</div>
        @endif
    </div>
</body>
</html>

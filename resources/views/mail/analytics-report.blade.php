<!doctype html>
{{-- Queued analytics report mail (analytics.md:189-194): a simple HTML
     table with the key metrics of the four report types. The payloads
     are the serialized typed results (B288): KPIs as {value, previous},
     series as {points}, tables as {columns, rows} — the values are
     aggregated numbers and EN titles, no user-controlled markup. --}}
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

        <h2>{{ __('mail.analytics_overall') }}</h2>
        <table>
            <tr><th>{{ __('mail.analytics_total_page_views') }}</th><td class="value">{{ $reports['overall']['total_page_views']['value'] }}</td></tr>
            <tr><th>{{ __('mail.analytics_unique_visitors') }}</th><td class="value">{{ $reports['overall']['unique_visitors']['value'] }}</td></tr>
            <tr><th>{{ __('mail.analytics_avg_time_on_page') }}</th><td class="value">{{ $reports['overall']['avg_time_on_page']['value'] ?? '—' }}</td></tr>
            @foreach ($reports['overall']['cta_clicks']['rows'] as $row)
                <tr><th>{{ __('mail.analytics_cta_'.$row['target']) }}</th><td class="value">{{ $row['count'] }}</td></tr>
            @endforeach
        </table>

        <h2>{{ __('mail.analytics_blog') }}</h2>
        <table>
            <tr><th>{{ __('mail.analytics_total_views') }}</th><td class="value">{{ $reports['blog']['total_views']['value'] }}</td></tr>
            <tr><th>{{ __('mail.analytics_unique_visitors') }}</th><td class="value">{{ $reports['blog']['unique_visitors']['value'] }}</td></tr>
            @foreach ($reports['blog']['reactions']['points'] as $point)
                <tr><th>{{ __('mail.analytics_'.$point['label']) }}</th><td class="value">{{ $point['value'] }}</td></tr>
            @endforeach
        </table>
        @if (count($reports['blog']['top_articles']['rows']) > 0)
            <table>
                <tr>
                    <th>{{ __('mail.analytics_article') }}</th>
                    <th>{{ __('mail.analytics_views') }}</th>
                    <th>{{ __('mail.analytics_avg_read_time') }}</th>
                    <th>{{ __('mail.analytics_completion_rate') }}</th>
                </tr>
                @foreach ($reports['blog']['top_articles']['rows'] as $row)
                    <tr>
                        <td>{{ $row['title'] }}</td>
                        <td class="value">{{ $row['views'] }}</td>
                        <td class="value">{{ $row['avg_read_time'] ?? '—' }}</td>
                        <td class="value">{{ $row['completion_rate'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <h2>{{ __('mail.analytics_projects') }}</h2>
        @if (count($reports['projects']['project_views']['rows']) > 0)
            <table>
                <tr>
                    <th>{{ __('mail.analytics_project') }}</th>
                    <th>{{ __('mail.analytics_views') }}</th>
                </tr>
                @foreach ($reports['projects']['project_views']['rows'] as $row)
                    <tr>
                        <td>{{ $row['title'] }}</td>
                        <td class="value">{{ $row['views'] }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <div class="muted">{{ __('mail.analytics_none') }}</div>
        @endif

        <h2>{{ __('mail.analytics_feedback') }}</h2>
        <table>
            <tr><th>{{ __('mail.analytics_total_submissions') }}</th><td class="value">{{ $reports['feedback']['total_submissions']['value'] }}</td></tr>
            @foreach ($reports['feedback']['submissions_per_day']['points'] as $point)
                <tr>
                    <th>{{ $point['label'] }}</th>
                    <td class="value">{{ $point['value'] }}</td>
                </tr>
            @endforeach
        </table>
    </div>
</body>
</html>

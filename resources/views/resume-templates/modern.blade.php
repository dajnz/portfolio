{{--
    Resume PDF template: `modern` (content-management.md:352-358) —
    single column, accent color bar, generous whitespace. Receives the
    assembled per-locale `data` (visible sections only) + `locale`.
    dompdf-rendered: self-contained CSS only, system fonts (DejaVu —
    Cyrillic-safe).
--}}
@php
    $order = collect($data['sections'])
        ->filter(fn (array $section): bool => $section['visible'])
        ->sortBy('order')
        ->keys()
        ->all();
    $period = fn (array $item): string => substr((string) $item['started_at'], 0, 4)
        .' – '
        .($item['ended_at'] === null ? __('resume.present') : substr((string) $item['ended_at'], 0, 4));
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 48px 56px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #1f2937;
            margin: 0;
        }
        header {
            border-bottom: 3px solid #2563eb;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        h1 { font-size: 24pt; margin: 0 0 4px; color: #111827; }
        .title { font-size: 12pt; color: #4b5563; margin: 0; }
        h2 {
            font-size: 12pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #2563eb;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin: 24px 0 10px;
        }
        .about { margin: 0; }
        .skill-group { margin-bottom: 8px; }
        .skill-group-name { font-weight: bold; color: #374151; }
        .skill { margin: 0 0 2px; }
        .skill .level { color: #6b7280; font-size: 9.5pt; }
        .experience-item { margin-bottom: 14px; page-break-inside: avoid; }
        .experience-item h3 { font-size: 11.5pt; margin: 0 0 2px; color: #111827; }
        .company { color: #4b5563; margin: 0 0 4px; }
        .period { color: #6b7280; font-size: 9.5pt; }
        .achievements { margin: 4px 0 0; padding-left: 18px; }
        .achievements li { margin-bottom: 2px; }
        .tech-tags { color: #6b7280; font-size: 9.5pt; margin: 2px 0 0; }
        .contact { margin: 0 0 4px; }
        .contact .label { font-weight: bold; color: #374151; }
        .contact .value { color: #4b5563; }
    </style>
</head>
<body>
    @if (in_array('header', $order, true))
        <header>
            <h1>{{ $data['header']['name'] }}</h1>
            <p class="title">{{ $data['header']['title'] }}</p>
        </header>
    @endif

    @if (in_array('about_me', $order, true) && $data['about'] !== '')
        <h2>{{ __('resume.section_about_me') }}</h2>
        <p class="about">{{ $data['about'] }}</p>
    @endif

    @if (in_array('skills', $order, true) && $data['skills'] !== [])
        <h2>{{ __('resume.section_skills') }}</h2>
        @foreach ($data['skills'] as $group)
            <div class="skill-group">
                <div class="skill-group-name">{{ $group['group'] }}</div>
                @foreach ($group['skills'] as $skill)
                    <p class="skill">{{ $skill['name'] }} <span class="level">— {{ $skill['proficiency_label'] }}</span></p>
                @endforeach
            </div>
        @endforeach
    @endif

    @if (in_array('experience', $order, true) && $data['experience'] !== [])
        <h2>{{ __('resume.section_experience') }}</h2>
        @foreach ($data['experience'] as $item)
            <div class="experience-item">
                <h3>{{ $item['position'] }}</h3>
                @if ($item['company_name'] !== null)
                    <p class="company">{{ $item['company_name'] }}@if ($item['company_country'] !== null), {{ $item['company_country'] }}@endif <span class="period">· {{ $period($item) }}</span></p>
                @else
                    <p class="period">{{ $period($item) }}</p>
                @endif
                @if ($item['achievements'] !== [])
                    <ul class="achievements">
                        @foreach ($item['achievements'] as $achievement)
                            <li>{{ $achievement['description'] }}</li>
                        @endforeach
                    </ul>
                @endif
                @if ($item['tech_tags'] !== [])
                    <p class="tech-tags">{{ implode(' · ', $item['tech_tags']) }}</p>
                @endif
            </div>
        @endforeach
    @endif

    @if (in_array('contacts', $order, true) && $data['contacts'] !== [])
        <h2>{{ __('resume.section_contacts') }}</h2>
        @foreach ($data['contacts'] as $contact)
            <p class="contact">
                @if ($contact['name'] !== '')
                    <span class="label">{{ $contact['name'] }}:</span>
                @endif
                <span class="value">{{ $contact['url'] ?? $contact['value'] }}</span>
            </p>
        @endforeach
    @endif
</body>
</html>

{{--
    Resume PDF template: `compact` (content-management.md:352-358) —
    single column, minimal spacing, small fonts, optimized for the
    1-page constraint. dompdf-rendered: self-contained CSS only, system
    fonts (DejaVu — Cyrillic-safe).
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
        @page { margin: 28px 34px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5pt;
            line-height: 1.35;
            color: #1f2937;
            margin: 0;
        }
        header { margin-bottom: 10px; }
        h1 { font-size: 16pt; margin: 0; color: #111827; }
        .title { font-size: 9pt; color: #4b5563; margin: 1px 0 0; }
        h2 {
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #2563eb;
            margin: 10px 0 4px;
        }
        h2:first-of-type { margin-top: 0; }
        .about { margin: 0; }
        .skill { margin: 0 0 1px; }
        .skill .level { color: #6b7280; }
        .skill-group-name { font-weight: bold; }
        .skill-group { margin-bottom: 4px; }
        .experience-item { margin-bottom: 6px; page-break-inside: avoid; }
        .experience-item h3 { font-size: 8.5pt; margin: 0; color: #111827; display: inline; }
        .period { color: #6b7280; }
        .company { color: #4b5563; margin: 0; }
        .achievements { margin: 2px 0 0; padding-left: 14px; }
        .achievements li { margin-bottom: 0; }
        .tech-tags { color: #6b7280; margin: 1px 0 0; }
        .contact { margin: 0 0 2px; }
        .contact .label { font-weight: bold; }
        .contact .value { color: #4b5563; }
    </style>
</head>
<body>
    {{-- The visible sections render in their layout order — `$order` is
         the render sequence, not a visibility gate (the @if blocks below
         skip empty sections, content-management.md:337-347). --}}
    @foreach ($order as $section)
        @if ($section === 'header')
            <header>
                <h1>{{ $data['header']['name'] }} <span class="title">{{ $data['header']['title'] }}</span></h1>
            </header>
        @elseif ($section === 'about_me' && $data['about'] !== '')
            <h2>{{ __('resume.section_about_me') }}</h2>
            <p class="about">{{ $data['about'] }}</p>
        @elseif ($section === 'skills' && $data['skills'] !== [])
            <h2>{{ __('resume.section_skills') }}</h2>
            @foreach ($data['skills'] as $group)
                <div class="skill-group">
                    <span class="skill-group-name">{{ $group['group'] }}:</span>
                    @foreach ($group['skills'] as $skill)
                        <span class="skill">{{ $skill['name'] }} <span class="level">({{ $skill['proficiency_label'] }})</span>@if (! $loop->last)·@endif</span>
                    @endforeach
                </div>
            @endforeach
        @elseif ($section === 'experience' && $data['experience'] !== [])
            <h2>{{ __('resume.section_experience') }}</h2>
            @foreach ($data['experience'] as $item)
                <div class="experience-item">
                    <h3>{{ $item['position'] }}</h3>
                    @if ($item['company_name'] !== null)
                        <span class="company">{{ $item['company_name'] }}@if ($item['company_country'] !== null), {{ $item['company_country'] }}@endif</span>
                    @endif
                    <span class="period">— {{ $period($item) }}</span>
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
        @elseif ($section === 'contacts' && $data['contacts'] !== [])
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
    @endforeach
</body>
</html>

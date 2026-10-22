{{--
    Resume PDF template: `compact` — single column, minimal spacing, small
    fonts, optimized for the 1-page constraint. dompdf-rendered:
    self-contained CSS only, system fonts (DejaVu — Cyrillic-safe).
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
    $showContactsHeading = $data['options']['contacts_show_heading'] ?? true;
    $showExperienceSkills = $data['options']['experience_show_skills'] ?? true;
    $flushNextHeading = true;
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
        h2.flush { margin-top: 0; }
        .about { margin: 0; }
        .skill { margin: 0 0 1px; }
        .skill .level { color: #6b7280; }
        .skill-group-name { font-weight: bold; }
        .skill-group { margin: 0 0 4px; }
        .experience-item { margin-bottom: 6px; page-break-inside: avoid; }
        .experience-item h3 { font-size: 8.5pt; margin: 0; color: #111827; display: inline; }
        .period { color: #6b7280; float: right; }
        .company { color: #4b5563; margin: 0; }
        .achievements { margin: 2px 0 0; padding-left: 14px; }
        .achievements li { margin-bottom: 0; }
        .tech-tags { color: #6b7280; margin: 1px 0 0; }
        table.contacts { width: 100%; border-collapse: collapse; }
        table.contacts td { width: 50%; vertical-align: top; padding: 0 8px 0 0; }
        .contact { margin: 0 0 2px; }
        .contact .label { font-weight: bold; }
        .contact .value { color: #4b5563; }
    </style>
</head>
<body>
    {{-- The visible sections render in their layout order — `$order` is
         the render sequence, not a visibility gate (the @if blocks below
         skip empty sections). --}}
    @foreach ($order as $section)
        @if ($section === 'header')
            <header>
                <h1>{{ $data['header']['name'] }} <span class="title">{{ $data['header']['title'] }}</span></h1>
            </header>
            @php $flushNextHeading = true; @endphp
        @elseif ($section === 'about_me' && $data['about'] !== '')
            <h2 class="{{ $flushNextHeading ? 'flush' : '' }}">{{ __('resume.section_about_me') }}</h2>
            @php $flushNextHeading = false; @endphp
            <p class="about">{{ $data['about'] }}</p>
        @elseif ($section === 'skills' && $data['skills'] !== [])
            <h2 class="{{ $flushNextHeading ? 'flush' : '' }}">{{ __('resume.section_skills') }}</h2>
            @php $flushNextHeading = false; @endphp
            @include('resume-templates.partials.skills', ['data' => $data])
        @elseif ($section === 'experience' && $data['experience'] !== [])
            <h2 class="{{ $flushNextHeading ? 'flush' : '' }}">{{ __('resume.section_experience') }}</h2>
            @php $flushNextHeading = false; @endphp
            @foreach ($data['experience'] as $item)
                <div class="experience-item">
                    <span class="period">{{ $period($item) }}</span>
                    <h3>{{ $item['position'] }}</h3>
                    @if ($item['company_name'] !== null)
                        <span class="company">{{ $item['company_name'] }}@if ($item['company_country'] !== null), {{ $item['company_country'] }}@endif</span>
                    @endif
                    @if ($item['achievements'] !== [])
                        <ul class="achievements">
                            @foreach ($item['achievements'] as $achievement)
                                <li>{{ $achievement['description'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($showExperienceSkills && $item['tech_tags'] !== [])
                        <p class="tech-tags">{{ implode(' · ', $item['tech_tags']) }}</p>
                    @endif
                </div>
            @endforeach
        @elseif ($section === 'contacts' && $data['contacts'] !== [])
            @if ($showContactsHeading)
                <h2 class="{{ $flushNextHeading ? 'flush' : '' }}">{{ __('resume.section_contacts') }}</h2>
            @endif
            @php $flushNextHeading = false; @endphp
            @include('resume-templates.partials.contacts', ['data' => $data])
        @endif
    @endforeach
</body>
</html>

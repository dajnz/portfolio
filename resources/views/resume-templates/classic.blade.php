{{--
    Resume PDF template: `classic` — two columns (skills/contacts sidebar,
    experience/about main), dense traditional resume layout. dompdf-rendered:
    absolutely positioned sidebar + system fonts only (a table row cannot
    break across pages in dompdf, which clipped long resumes, and a float
    narrows the margined main column).
--}}
@php
    $sections = $data['sections'];
    $period = fn (array $item): string => substr((string) $item['started_at'], 0, 4)
        .' – '
        .($item['ended_at'] === null ? __('resume.present') : substr((string) $item['ended_at'], 0, 4));
    // Classic is a fixed two-column layout (skills/contacts sidebar,
    // about/experience main) — the layout order is honored within each
    // column: the visible column sections render in their relative order.
    $columnOrder = function (array $keys) use ($sections): array {
        return collect($keys)
            ->filter(fn (string $key): bool => $sections[$key]['visible'] ?? false)
            ->sortBy(fn (string $key): int => $sections[$key]['order'])
            ->values()
            ->all();
    };
    $sidebarOrder = $columnOrder(['skills', 'contacts']);
    $mainOrder = $columnOrder(['about_me', 'experience']);
    $showContactsHeading = $data['options']['contacts_show_heading'] ?? true;
    $showExperienceSkills = $data['options']['experience_show_skills'] ?? true;
    $sidebarHeadingRendered = false;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 36px 40px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            line-height: 1.45;
            color: #1f2937;
            margin: 0;
        }
        header { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 14px; }
        h1 { font-size: 19pt; margin: 0 0 2px; color: #111827; }
        .title { font-size: 10.5pt; color: #374151; margin: 0; }
        .columns { position: relative; }
        .sidebar { position: absolute; top: 0; left: 0; width: 32%; padding-right: 18px; }
        .main { margin-left: 34%; }
        h2 {
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #111827;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 3px;
            margin: 12px 0 6px;
        }
        h2.first { margin-top: 0; }
        .skill { margin: 0 0 1px; }
        .skill .level { color: #6b7280; font-size: 8.5pt; }
        .skill-group { margin: 0 0 6px; }
        .skill-group-name { font-weight: bold; }
        table.contacts { width: 100%; border-collapse: collapse; }
        table.contacts td { width: 50%; vertical-align: top; padding: 0 6px 0 0; }
        .contact { margin: 0 0 3px; }
        .contact .label { font-weight: bold; }
        .contact .value { color: #4b5563; }
        .experience-item { margin-bottom: 9px; page-break-inside: avoid; }
        .experience-item h3 { font-size: 10pt; margin: 0 0 1px; color: #111827; }
        .company { margin: 0; color: #374151; }
        .period { color: #6b7280; font-size: 8.5pt; }
        .achievements { margin: 3px 0 0; padding-left: 16px; }
        .achievements li { margin-bottom: 1px; }
        .tech-tags { color: #6b7280; font-size: 8.5pt; margin: 2px 0 0; }
        .about { margin: 0; }
    </style>
</head>
<body>
    <header>
        <h1>{{ $data['header']['name'] }}</h1>
        <p class="title">{{ $data['header']['title'] }}</p>
    </header>

    <div class="columns">
        <div class="sidebar">
            @foreach ($sidebarOrder as $section)
                @if ($section === 'skills')
                    <h2 class="{{ $sidebarHeadingRendered ? '' : 'first' }}">{{ __('resume.section_skills') }}</h2>
                    @php $sidebarHeadingRendered = true; @endphp
                    @include('resume-templates.partials.skills', ['data' => $data])
                @elseif ($section === 'contacts')
                    @if ($showContactsHeading)
                        <h2 class="{{ $sidebarHeadingRendered ? '' : 'first' }}">{{ __('resume.section_contacts') }}</h2>
                        @php $sidebarHeadingRendered = true; @endphp
                    @endif
                    @include('resume-templates.partials.contacts', ['data' => $data])
                @endif
            @endforeach
        </div>
        <div class="main">
            @foreach ($mainOrder as $section)
                @if ($section === 'about_me')
                    <h2 class="{{ $loop->first ? 'first' : '' }}">{{ __('resume.section_about_me') }}</h2>
                    <p class="about">{{ $data['about'] }}</p>
                @elseif ($section === 'experience')
                    <h2 class="{{ $loop->first ? 'first' : '' }}">{{ __('resume.section_experience') }}</h2>
                    @foreach ($data['experience'] as $item)
                        <div class="experience-item">
                            <h3>{{ $item['position'] }}</h3>
                            <p class="company">
                                @if ($item['company_name'] !== null)
                                    {{ $item['company_name'] }}@if ($item['company_country'] !== null), {{ $item['company_country'] }}@endif ·
                                @endif
                                <span class="period">{{ $period($item) }}</span>
                            </p>
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
                @endif
            @endforeach
        </div>
    </div>
</body>
</html>

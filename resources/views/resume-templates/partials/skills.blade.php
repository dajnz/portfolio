{{--
    The skills block shared by every resume template. `per_line` renders
    the group name on its own line with one proficiency-labelled line per
    skill; `inline` renders the skills comma-separated after the bold
    group name (no proficiency).
--}}
@php
    $skillsLayout = $data['options']['skills_layout'] ?? 'per_line';
@endphp
@foreach ($data['skills'] as $group)
    @if ($skillsLayout === 'inline')
        <p class="skill-group">
            <span class="skill-group-name">{{ $group['group'] }}:</span>
            {{ implode(', ', array_column($group['skills'], 'name')) }}
        </p>
    @else
        <div class="skill-group">
            <div class="skill-group-name">{{ $group['group'] }}</div>
            @foreach ($group['skills'] as $skill)
                <p class="skill">{{ $skill['name'] }} <span class="level">— {{ $skill['proficiency_label'] }}</span></p>
            @endforeach
        </div>
    @endif
@endforeach

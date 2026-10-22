{{--
    The contacts list shared by every resume template. `per_line` renders
    one contact per line; `two_columns` uses a table filled row by row,
    left to right.
--}}
@php
    $contactsLayout = $data['options']['contacts_layout'] ?? 'per_line';
@endphp
@if ($contactsLayout === 'two_columns')
    <table class="contacts">
        <tbody>
            @foreach (array_chunk($data['contacts'], 2) as $row)
                <tr>
                    @foreach ($row as $contact)
                        <td>
                            <p class="contact">
                                @if ($contact['name'] !== '')
                                    <span class="label">{{ $contact['name'] }}:</span>
                                @endif
                                <span class="value">{{ $contact['url'] ?? $contact['value'] }}</span>
                            </p>
                        </td>
                    @endforeach
                    @if (count($row) === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    @foreach ($data['contacts'] as $contact)
        <p class="contact">
            @if ($contact['name'] !== '')
                <span class="label">{{ $contact['name'] }}:</span>
            @endif
            <span class="value">{{ $contact['url'] ?? $contact['value'] }}</span>
        </p>
    @endforeach
@endif

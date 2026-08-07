@component('mail::message')
# {{ __('mail.feedback_title') }}

{{ __('mail.feedback_intro', ['sender_name' => $senderName, 'sender_email' => $senderEmail]) }}

> {{ $excerpt }}

@component('mail::button', ['url' => url('/admin/feedback')])
{{ __('mail.feedback_view_all') }}
@endcomponent

{{ __('mail.feedback_footer') }}
@endcomponent

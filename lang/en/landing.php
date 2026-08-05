<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Landing page (public-section-ui.md)
|--------------------------------------------------------------------------
|
| Public landing page chrome: CV button, featured projects, contacts,
| feedback form states and the cookies notice. The `feedback_sent` key
| uses ICU syntax (the `{sender_name}` argument).
|
*/

return [
    'download_cv' => 'Download CV',
    'view_projects' => 'View Projects',
    'view_all_projects' => 'View all projects',
    'send_feedback' => 'Send Feedback',
    'get_in_touch' => 'Get in Touch',
    'about_me' => 'About Me',
    'featured_projects' => 'Featured Projects',
    'loading' => 'Loading...',

    'open_to_relocation' => 'Open to relocation',
    'availability' => [
        'available' => 'Available',
        'limited' => 'Limited',
        'busy' => 'Busy',
    ],
    'work_type' => [
        'remote' => 'Remote',
        'hybrid' => 'Hybrid',
        'office' => 'Office',
    ],

    'cookies_notice' => 'This site uses cookies for language preferences, theme settings, and self-hosted analytics. No third-party trackers.',
    'cookies_accept' => 'Accept',
    'footer_copyright' => '© :year :name. All rights reserved.',

    'feedback_name' => 'Name',
    'feedback_email' => 'Email',
    'feedback_message' => 'Message',
    'feedback_never_share' => 'We will never share your email.',
    'feedback_send' => 'Send',
    'feedback_sending' => 'Sending...',
    'feedback_sent' => 'Thanks, {sender_name}! Your message has been sent.',
    'feedback_send_another' => 'Send another',
    'feedback_error' => 'Could not send your message. Please try again.',
    'feedback_invalid_email' => 'Please enter a valid email address.',
];

<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Error strings (error-handling.md)
|--------------------------------------------------------------------------
|
| The mapped messages are used by the exception handler for JSON and
| Inertia (`ErrorPage`) responses (error-handling.md:53-64); the numeric
| status keys feed the Blade error views (errors/layout.blade.php).
|
*/

return [
    'unauthenticated' => 'Unauthenticated.',
    'unauthorized' => 'This action is unauthorized.',
    'entity_not_found' => ':entity not found.',
    'not_found' => 'Not found.',
    'method_not_allowed' => 'Method not allowed.',
    'validation_failed' => 'The given data was invalid.',
    'too_many_attempts' => 'Too many attempts.',
    'page_expired' => 'The page has expired. Please try again.',
    'service_unavailable' => 'Service unavailable.',
    'server_error' => 'Server error.',

    '403' => "Sorry, you don't have permission to view this page.",
    '404' => "Sorry, this page doesn't exist — it may have been moved or the link is out of date.",
    '419' => 'Your session has expired. Please go back and try again.',
    '422' => 'The submitted data could not be processed.',
    '429' => 'Too many requests. Please try again in a moment.',
    '500' => 'Something went wrong on our end. Please try again in a moment.',
    '503' => "We're performing maintenance right now. Please check back soon.",

    'back_home' => 'Back to home',
    'reference_id' => 'Reference ID: :request_id',
];

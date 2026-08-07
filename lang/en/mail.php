<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Admin notification mail (architecture.md:172-173)
|--------------------------------------------------------------------------
|
| Queued mailables sent to the admin: the "new feedback" notification
| (NotifyAdminOfFeedbackMail) and the analytics report. The mail body is
| rendered from the markdown template `resources/views/mail/*.blade.php`.
|
*/

return [
    'feedback_subject' => 'New feedback received',
    'feedback_title' => 'New Feedback',
    'feedback_intro' => ':sender_name (:sender_email) sent you a message:',
    'feedback_view_all' => 'View feedback',
    'feedback_footer' => 'You are receiving this because the "Email on Feedback" option is enabled.',
];

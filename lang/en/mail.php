<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Admin notification mail (architecture.md:172-173)
|--------------------------------------------------------------------------
|
| Queued mailables sent to the admin: the "new feedback" notification
| (NotifyAdminOfFeedbackMail) and the analytics report
| (SendAnalyticsReportMail). The mail body is rendered from the
| templates `resources/views/mail/*.blade.php`.
|
*/

return [
    'feedback_subject' => 'New feedback received',
    'feedback_title' => 'New Feedback',
    'feedback_intro' => ':sender_name (:sender_email) sent you a message:',
    'feedback_view_all' => 'View feedback',
    'feedback_footer' => 'You are receiving this because the "Email on Feedback" option is enabled.',
    'analytics_subject' => 'Analytics report — :period',
    'analytics_title' => 'Analytics Report',
    'analytics_total_page_views' => 'Total page views',
    'analytics_unique_visitors' => 'Unique visitors',
    'analytics_feedback_submissions' => 'Feedback submissions',
    'analytics_top_article' => 'Top article',
    'analytics_article' => 'Article',
    'analytics_views' => 'Views',
    'analytics_none' => 'No data available.',
];

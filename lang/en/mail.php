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
    'analytics_overall' => 'Overview',
    'analytics_blog' => 'Blog',
    'analytics_projects' => 'Projects',
    'analytics_feedback' => 'Feedback',
    'analytics_total_page_views' => 'Total page views',
    'analytics_unique_visitors' => 'Unique visitors',
    'analytics_avg_time_on_page' => 'Avg time on page (s)',
    'analytics_cta_github' => 'GitHub clicks',
    'analytics_cta_demo' => 'Live demo clicks',
    'analytics_cta_cv' => 'CV download clicks',
    'analytics_cta_feedback' => 'Feedback form clicks',
    'analytics_cta_language' => 'Language switch clicks',
    'analytics_cta_theme' => 'Theme switch clicks',
    'analytics_total_views' => 'Total views',
    'analytics_likes' => 'Likes',
    'analytics_dislikes' => 'Dislikes',
    'analytics_article' => 'Article',
    'analytics_views' => 'Views',
    'analytics_avg_read_time' => 'Avg read time (s)',
    'analytics_completion_rate' => 'Completion %',
    'analytics_project' => 'Project',
    'analytics_none' => 'No data available.',
    'analytics_total_submissions' => 'Total submissions',
];

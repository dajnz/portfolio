<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Blog public pages (blog.md, public-section-ui.md)
|--------------------------------------------------------------------------
|
| Blog index + article detail chrome. `min_read` uses ICU plural syntax —
| Russian has one/few/many forms, English one/other.
|
*/

return [
    'read_more' => 'Read more',
    'min_read' => '{minutes, plural, one {# min read} other {# min read}}',
    'related_posts' => 'Related Articles',
    'no_articles' => 'No articles yet',
    'no_articles_match' => 'No articles match your search.',
    'could_not_load' => 'Could not load articles. [Retry]',
    'published_on' => 'Published: :date',
    'table_of_contents' => 'Table of Contents',
    'back_to_blog' => 'Back to blog',
    'rss_feed' => 'RSS',

    'filter_all' => 'All years',
    'show_filters' => 'Show filters',
    'hide_filters' => 'Hide filters',
    'toc_empty' => 'No headings in this article.',

    'search_placeholder' => 'Search articles...',
    'filter_by_tags' => 'Filter by tags...',
    'grid_view' => 'Grid view',
    'list_view' => 'List view',

    'views' => 'Views: :count',
    'like' => 'Like',
    'dislike' => 'Dislike',
    'reaction_error' => 'Could not update your reaction. Please try again.',

    'more_tags' => 'And {count} more: {names}',
    'copy_code' => 'Copy',

    'callout_warning' => 'Warning',
    'callout_tip' => 'Tip',
    'callout_important' => 'Important',
    'callout_tradeoff' => 'Trade-off',
    'copied' => 'Copied!',
];

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
    'published_on' => 'Published: :date',
    'table_of_contents' => 'Table of Contents',
    'back_to_blog' => 'Back to blog',

    'filter_year' => 'Year',
    'filter_all' => 'All',
];

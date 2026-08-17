<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Публичные страницы блога (blog.md, public-section-ui.md)
|--------------------------------------------------------------------------
|
| `min_read` — ICU-множественное число: русский язык различает формы
| one/few/many, английский — one/other.
|
*/

return [
    'read_more' => 'Читать далее',
    'min_read' => '{minutes, plural, one {# минута чтения} few {# минуты чтения} many {# минут чтения} other {# минут чтения}}',
    'related_posts' => 'Похожие статьи',
    'no_articles' => 'Статей пока нет',
    'no_articles_match' => 'По вашему запросу статей не найдено.',
    'could_not_load' => 'Не удалось загрузить статьи. [Повторить]',
    'published_on' => 'Опубликовано: :date',
    'table_of_contents' => 'Содержание',
    'back_to_blog' => 'Назад к блогу',

    'filter_year' => 'Год',
    'filter_all' => 'Все',
    'show_filters' => 'Показать фильтры',
    'hide_filters' => 'Скрыть фильтры',
    'toc_empty' => 'В статье нет заголовков.',

    'search_placeholder' => 'Поиск по статьям...',
    'filter_by_tags' => 'Фильтр по тегам...',
    'grid_view' => 'Сетка',
    'list_view' => 'Список',

    'views' => 'Просмотров: :count',
    'like' => 'Нравится',
    'dislike' => 'Не нравится',
    'reaction_error' => 'Не удалось обновить оценку. Попробуйте ещё раз.',

    'more_tags' => 'Ещё {count}: {names}',
    'copy_code' => 'Копировать',

    'callout_warning' => 'Предупреждение',
    'callout_tip' => 'Совет',
    'callout_important' => 'Важно',
    'callout_tradeoff' => 'Компромисс',
    'copied' => 'Скопировано!',
];

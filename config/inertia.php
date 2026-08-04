<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Inertia configuration
|--------------------------------------------------------------------------
|
| The page component Vue files land with their UI phases (3.x-8.x), so
| backend tests must not require them to exist on disk.
|
*/

return [

    'testing' => [
        'ensure_pages_exist' => false,
    ],

];

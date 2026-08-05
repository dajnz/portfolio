<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use Illuminate\Support\Facades\File;

/**
 * `lang:export` (step 2.6) — PHP catalogs → committed
 * `resources/js/lang/{locale}.json` (i18n-and-theming.md:42, B9).
 */
describe('ExportLangCommand', function (): void {
    it('exports every locale from lang/ to a JSON file keyed by group', function (): void {
        $this->artisan('lang:export')->assertSuccessful();

        foreach (['en', 'ru'] as $locale) {
            $jsonPath = resource_path("js/lang/{$locale}.json");

            expect($jsonPath)->toBeFile();

            $decoded = json_decode((string) File::get($jsonPath), true, flags: JSON_THROW_ON_ERROR);

            $expected = [];
            foreach (glob(lang_path("{$locale}/*.php")) ?: [] as $file) {
                $expected[basename($file, '.php')] = require $file;
            }
            $sortRecursively = static function (array &$array) use (&$sortRecursively): void {
                foreach ($array as &$value) {
                    if (is_array($value)) {
                        $sortRecursively($value);
                    }
                }
                ksort($array);
            };
            $sortRecursively($expected);

            expect($decoded)->toBe($expected);
        }
    });

    it('preserves ICU MessageFormat syntax in the exported JSON', function (): void {
        $this->artisan('lang:export')->assertSuccessful();

        foreach (['en', 'ru'] as $locale) {
            $decoded = json_decode(
                (string) File::get(resource_path("js/lang/{$locale}.json")),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            expect($decoded['blog']['min_read'])->toBe(
                (require lang_path("{$locale}/blog.php"))['min_read'],
            )->and($decoded['blog']['min_read'])->toContain('{minutes, plural');
        }
    });

    it('keeps the committed JSON files in sync with the PHP catalogs', function (): void {
        $before = [];
        foreach (['en', 'ru'] as $locale) {
            $before[$locale] = File::get(resource_path("js/lang/{$locale}.json"));
        }

        $this->artisan('lang:export')->assertSuccessful();

        foreach (['en', 'ru'] as $locale) {
            expect(File::get(resource_path("js/lang/{$locale}.json")))->toBe($before[$locale]);
        }
    });
});

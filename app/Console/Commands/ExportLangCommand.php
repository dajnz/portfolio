<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Exports the PHP translation catalogs to the frontend JSON files.
 *
 * Reads every `lang/{locale}/*.php` file and writes
 * `resources/js/lang/{locale}.json` keyed by group name, preserving ICU
 * MessageFormat syntax as-is (i18n-and-theming.md:42, B9). The JSON files
 * are committed — the frontend build consumes only them.
 */
class ExportLangCommand extends Command
{
    protected $signature = 'lang:export {--locales=en,ru : Comma-separated locales to export}';

    protected $description = 'Export the PHP language catalogs to resources/js/lang/{locale}.json';

    public function handle(): int
    {
        $locales = explode(',', $this->option('locales'));

        foreach ($locales as $locale) {
            $locale = trim($locale);

            if (! is_dir(lang_path($locale))) {
                $this->warn("Locale directory lang/{$locale} does not exist — skipped.");

                continue;
            }

            $catalog = [];

            foreach (glob(lang_path($locale).'/*.php') ?: [] as $file) {
                $catalog[basename($file, '.php')] = require $file;
            }

            if ($catalog === []) {
                $this->warn("No translation files found for locale '{$locale}' — skipped.");

                continue;
            }

            ksort($catalog);
            $this->sortRecursively($catalog);

            $json = json_encode(
                $catalog,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );

            if ($json === false) {
                $this->error("Failed to encode the '{$locale}' catalog: ".json_last_error_msg());

                return self::FAILURE;
            }

            $path = resource_path("js/lang/{$locale}.json");

            File::ensureDirectoryExists(dirname($path));

            if (file_put_contents($path, $json."\n") === false) {
                $this->error("Failed to write {$path}.");

                return self::FAILURE;
            }

            $this->info("Exported {$locale} → {$path}");
        }

        return self::SUCCESS;
    }

    private function sortRecursively(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->sortRecursively($value);
            }
        }

        ksort($array);
    }
}

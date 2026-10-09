<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A kebab-case slug (`my-post`, `my-post-2`) shared by every slugged
 * content entity. The format invariant lives here, not in the consuming
 * entities.
 *
 * `generate()` slugifies an arbitrary string into a valid slug: Latin
 * letters/digits are kept, Cyrillic input is transliterated to latin (so
 * Russian titles produce usable slugs) and every other character sequence
 * collapses into a single hyphen.
 */
final readonly class Slug
{
    /**
     * Russian → latin transliteration (lowercase keys; apply
     * `mb_strtolower()` before `strtr()`).
     *
     * @var array<string, string>
     */
    private const TRANSLITERATION = [
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'yo',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'y',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'kh',
        'ц' => 'ts',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'sch',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya',
    ];

    /**
     * Common Latin diacritics folded to their plain ASCII letters.
     *
     * @var array<string, string>
     */
    private const DIACRITICS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'æ' => 'ae',
        'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o', 'œ' => 'oe',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
    ];

    public function __construct(
        private string $value,
    ) {
        if ($value === '') {
            throw new InvalidArgumentException('Slug value must not be empty.');
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
            throw new InvalidArgumentException(
                "Slug must match \"[a-z0-9]+(?:-[a-z0-9]+)*\", got \"{$value}\"."
            );
        }
    }

    /**
     * Slugifies an arbitrary string: lowercase, Cyrillic → latin
     * transliteration, diacritic folding, non-alphanumeric runs
     * collapsed to a single hyphen. Returns '' when nothing remains.
     */
    public static function generate(string $value): string
    {
        $normalized = strtr(mb_strtolower($value), self::TRANSLITERATION + self::DIACRITICS);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $normalized), '-');
    }

    public function toValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $other->value === $this->value;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * Token navigation shared by the architecture guards. Whitespace, comments and
 * docblocks are insignificant, and a name preceded by a call, static, property
 * or declaration operator is not a global function call.
 */
final class PhpTokenStream
{
    public const SKIPPED = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    public const CALL_PRECEDER = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
        T_FUNCTION,
        T_NEW,
        T_NS_SEPARATOR,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
    ];

    /**
     * @param  array<int, mixed>  $tokens
     */
    public static function significantAfter(array $tokens, int $from): int
    {
        $count = count($tokens);

        for ($i = $from; $i < $count; $i++) {
            if (self::isSkipped($tokens[$i])) {
                continue;
            }

            return $i;
        }

        return -1;
    }

    /**
     * @param  array<int, mixed>  $tokens
     */
    public static function significantBefore(array $tokens, int $from): int
    {
        for ($i = $from; $i >= 0; $i--) {
            if (self::isSkipped($tokens[$i])) {
                continue;
            }

            return $i;
        }

        return -1;
    }

    public static function precedesFunctionCall(mixed $token): bool
    {
        return is_array($token) && in_array($token[0], self::CALL_PRECEDER, true);
    }

    public static function isSkipped(mixed $token): bool
    {
        return is_array($token) && in_array($token[0], self::SKIPPED, true);
    }
}

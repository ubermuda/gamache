<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

final class TranslationKeyValidator
{
    private const string PATTERN = '/^[a-z][a-z0-9]*([._-][a-z0-9]+)*$/';

    public function isValid(string $value): bool
    {
        return (bool) preg_match(self::PATTERN, $value);
    }
}

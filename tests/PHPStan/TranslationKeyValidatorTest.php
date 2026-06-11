<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan;

use Gamache\PHPStan\TranslationKeyValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TranslationKeyValidatorTest extends TestCase
{
    #[DataProvider('validKeys')]
    public function test_valid_key_passes(string $key): void
    {
        self::assertTrue(new TranslationKeyValidator()->isValid($key));
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_key_fails(string $key): void
    {
        self::assertFalse(new TranslationKeyValidator()->isValid($key));
    }

    /** @return iterable<string, array{string}> */
    public static function validKeys(): iterable
    {
        yield 'simple dotted key' => ['account.login.heading'];
        yield 'deep dotted key' => ['account.form.registration_form.email.label'];
        yield 'key with hyphen' => ['some.key-with-hyphen'];
        yield 'key with underscore' => ['some.key_with_underscore'];
        yield 'single letter' => ['a'];
        yield 'key with digit' => ['account.v2.heading'];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidKeys(): iterable
    {
        yield 'natural language with space' => ['Welcome back'];
        yield 'starts with uppercase' => ['Account.login'];
        yield 'sentence' => ['Sign in to continue'];
        yield 'trailing dot' => ['account.login.'];
        yield 'empty string' => [''];
        yield 'exclamation mark' => ['account.login!'];
        yield 'starts with digit' => ['2account.login'];
        yield 'trailing hyphen' => ['account.login-'];
        yield 'consecutive dots' => ['account..login'];
        yield 'consecutive hyphens' => ['foo--bar'];
        yield 'mixed consecutive separators' => ['foo.-bar'];
    }
}

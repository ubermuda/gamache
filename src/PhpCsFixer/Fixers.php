<?php

declare(strict_types=1);

namespace Gamache\PhpCsFixer;

use PhpCsFixer\Fixer\FixerInterface;

/**
 * Aggregate of every gamache PHP-CS-Fixer custom fixer and its recommended
 * rule configuration. Reference this from .php-cs-fixer.dist.php so new gamache
 * rules arrive automatically on `composer update` without editing the config.
 *
 * @implements \IteratorAggregate<int, FixerInterface>
 */
final class Fixers implements \IteratorAggregate
{
    /**
     * @return list<FixerInterface>
     */
    public static function all(): array
    {
        return [
            new BlankLineBetweenAttributedParametersFixer(),
            new MultilineAttributeFixer(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>|bool>
     */
    public static function rules(): array
    {
        return [
            'Gamache/blank_line_between_attributed_parameters' => true,
            'Gamache/multiline_attribute' => ['attributes' => ['Route'], 'minimum_arguments' => 3],
            'multiline_promoted_properties' => true,
            'php_unit_method_casing' => ['case' => 'snake_case'],
            'ordered_attributes' => true,
        ];
    }

    /**
     * @return \Iterator<int, FixerInterface>
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator(self::all());
    }
}

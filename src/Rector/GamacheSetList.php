<?php

declare(strict_types=1);

namespace Gamache\Rector;

/**
 * Set list of gamache Rector conventions. Reference via
 * `->withSets([GamacheSetList::CONVENTIONS])` so new gamache rules arrive
 * automatically on `composer update`.
 */
final class GamacheSetList
{
    public const CONVENTIONS = __DIR__.'/config/conventions.php';
}

<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

use Doctrine\ORM\EntityRepository;

/**
 * A repository reached through its Doctrine base class, which is how a project
 * writes one. Detection has to follow the parent to see it.
 *
 * @extends EntityRepository<object>
 */
final class CardRepository extends EntityRepository
{
}

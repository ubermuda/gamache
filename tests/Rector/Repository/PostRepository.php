<?php

declare(strict_types=1);

namespace Gamache\Tests\Rector\Repository;

use Doctrine\ORM\EntityRepository;
use Gamache\Tests\Rector\Entity\Post;

/** @extends EntityRepository<Post> */
class PostRepository extends EntityRepository
{
}

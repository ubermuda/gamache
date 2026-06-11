<?php

declare(strict_types=1);

namespace Gamache\Tests\Rector\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gamache\Tests\Rector\Repository\PostRepository;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class Post
{
}

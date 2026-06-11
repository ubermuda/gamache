<?php

namespace Gamache\Tests\Rector\Fixture;

use Gamache\Tests\Rector\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;

final class MultipleCallsSameEntity
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findPost(int $id): ?Post
    {
        return $this->em->getRepository(Post::class)->find($id);
    }

    public function countPosts(): int
    {
        return count($this->em->getRepository(Post::class)->findAll());
    }
}

-----
<?php

namespace Gamache\Tests\Rector\Fixture;

use Gamache\Tests\Rector\Repository\PostRepository;
use Gamache\Tests\Rector\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;

final class MultipleCallsSameEntity
{
    public function __construct(
        private readonly EntityManagerInterface $em, private readonly PostRepository $postRepository,
    ) {
    }

    public function findPost(int $id): ?Post
    {
        return $this->postRepository->find($id);
    }

    public function countPosts(): int
    {
        return count($this->postRepository->findAll());
    }
}

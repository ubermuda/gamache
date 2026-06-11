<?php

namespace Gamache\Tests\Rector\Fixture;

use Gamache\Tests\Rector\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;

final class BasicService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getPost(int $id): ?Post
    {
        return $this->em->getRepository(Post::class)->find($id);
    }
}

-----
<?php

namespace Gamache\Tests\Rector\Fixture;

use Gamache\Tests\Rector\Repository\PostRepository;
use Gamache\Tests\Rector\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;

final class BasicService
{
    public function __construct(
        private readonly EntityManagerInterface $em, private readonly PostRepository $postRepository,
    ) {
    }

    public function getPost(int $id): ?Post
    {
        return $this->postRepository->find($id);
    }
}

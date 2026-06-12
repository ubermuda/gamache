# Rector rule

One Rector rule in the `Gamache\Rector` namespace.

Register it in `rector.php`:

```php
use Gamache\Rector\InjectRepositoryInsteadOfGetRepositoryRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRules([
        InjectRepositoryInsteadOfGetRepositoryRector::class,
    ]);
```

---

## InjectRepositoryInsteadOfGetRepositoryRector

Replaces `$em->getRepository(Entity::class)` calls with an injected, properly typed repository.

For each `getRepository()` call on an `EntityManagerInterface`, the rule:

1. Resolves the entity's repository class from its `#[ORM\Entity(repositoryClass: …)]` attribute
2. Adds a `private readonly <Repository> $<repository>` promoted constructor parameter (once per repository, even with multiple calls)
3. Replaces every `$em->getRepository(Entity::class)` with the injected property

Entities without a custom `repositoryClass` are skipped — injecting the generic `EntityRepository` would gain nothing.

```php
// BEFORE
final class PostService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function getPost(int $id): ?Post
    {
        return $this->em->getRepository(Post::class)->find($id);
    }
}

// AFTER — given #[ORM\Entity(repositoryClass: PostRepository::class)] on Post
final class PostService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PostRepository $postRepository,
    ) {}

    public function getPost(int $id): ?Post
    {
        return $this->postRepository->find($id);
    }
}
```

The injected repository gives you full type information: PHPStan understands `find()` returns `?Post`, and custom repository methods autocomplete.

**Options:** none.

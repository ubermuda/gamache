<?php

declare(strict_types=1);

namespace App\Module\Project\Messenger;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class ProvisionWorkspace
{
    public function __construct(public string $workspaceId)
    {
    }
}

#[AsMessageHandler]
final readonly class ProvisionWorkspaceHandler
{
    public function __invoke(ProvisionWorkspace $message): void
    {
    }
}

// Not a handler: no attribute, parameter namespace is irrelevant.
final readonly class NotAHandler
{
    public function __invoke(\App\Module\Other\SomeMessage $message): void
    {
    }
}

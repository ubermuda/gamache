<?php

declare(strict_types=1);

namespace App\Module\Project\Messenger;

use App\Module\Other\StrayMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StrayMessageHandler
{
    public function __invoke(StrayMessage $message): void
    {
    }
}

#[AsMessageHandler(handles: StrayMessage::class)]
final readonly class ExplicitHandlesHandler
{
    public function handle(mixed $message): void
    {
    }
}

final readonly class MethodLevelHandler
{
    #[AsMessageHandler]
    public function onStray(StrayMessage $message): void
    {
    }
}

#[AsMessageHandler(method: 'handleStray')]
final readonly class CustomMethodHandler
{
    public function handleStray(StrayMessage $message): void
    {
    }
}

<?php

declare(strict_types=1);

use Symfony\Component\Routing\Attribute\Route;

#[Route('/workspace')]
class ControllerA
{
}

#[Route(path: '/workspace/create')]
class ControllerB
{
}

// No path arg — no false positive
#[Route(name: 'some_route')]
class ControllerC
{
}

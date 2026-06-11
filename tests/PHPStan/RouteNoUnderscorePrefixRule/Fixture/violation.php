<?php

declare(strict_types=1);

use Symfony\Component\Routing\Attribute\Route;

#[Route('/_workspace')]
class ControllerA
{
}

#[Route(path: '/_admin/users')]
class ControllerB
{
}

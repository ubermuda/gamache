<?php

declare(strict_types=1);

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Minimal stub of Symfony's AbstractController so controller-rule test fixtures
 * have a reflectable ancestry chain (symfony/framework-bundle is not a gamache
 * dependency). Only the inherited helpers exercised by the fixtures are declared.
 */
abstract class AbstractController
{
    protected function getUser(): ?object
    {
        return null;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function render(string $view, array $parameters = []): Response
    {
        return new Response();
    }
}

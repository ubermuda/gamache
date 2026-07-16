<?php

declare(strict_types=1);

namespace App\Module\Foo\Controller\Api;

use Symfony\Component\Routing\Attribute\Route;

#[Route('/pages', name: 'page_list')]
class ApiNamespacedWebController
{
}

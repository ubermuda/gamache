<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class WrongParentController extends AbstractController
{
    public function __invoke(): Response
    {
        return new Response();
    }
}

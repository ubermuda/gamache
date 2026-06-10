<?php

namespace App;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('kernel.event_listener')]
interface MyInterface
{
}

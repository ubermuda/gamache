<?php

namespace App;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.my_handler')]
interface MyInterface
{
}

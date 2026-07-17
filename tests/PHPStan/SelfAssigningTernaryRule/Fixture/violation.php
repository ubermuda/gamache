<?php

declare(strict_types=1);

$cond = random_int(0, 1) === 1;
$x = 1;
$new = 2;

$x = $cond ? $x : $new;
$x = $cond ? $new : $x;

$arr = ['k' => 1];
$arr['k'] = $cond ? $arr['k'] : $new;

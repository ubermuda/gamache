<?php

declare(strict_types=1);

$cond = random_int(0, 1) === 1;
$x = 1;
$default = 2;
$a = 3;
$b = 4;

// Elvis "keep-or-default" — legitimate, not a self-assigning ternary.
$x = $x ?: $default;

// Full ternary where neither branch is the assignment target.
$x = $cond ? $a : $b;

// Target differs from both branches.
$y = $cond ? $x : $default;

// The `if` form the rule steers toward.
if (!$cond) {
    $x = $default;
}

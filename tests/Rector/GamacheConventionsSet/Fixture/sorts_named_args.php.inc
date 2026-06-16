<?php

namespace Gamache\Tests\Rector\GamacheConventionsSet\Fixture;

function run(?int $foo = null, ?int $bar = null): void
{
}

run(bar: 2, foo: 1);

?>
-----
<?php

namespace Gamache\Tests\Rector\GamacheConventionsSet\Fixture;

function run(?int $foo = null, ?int $bar = null): void
{
}

run(foo: 1, bar: 2);

?>

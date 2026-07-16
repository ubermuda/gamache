<?php

declare(strict_types=1);

class WithPassThroughHelpers
{
    public function __construct(
        private readonly PassThroughMatrixBuilder $matrixBuilder,
    ) {
    }

    public function show(): array
    {
        return $this->buildMatrix([1, 2]);
    }

    public function close(): void
    {
        $this->flush();
    }

    private function buildMatrix(array $items): array
    {
        return $this->matrixBuilder->build($items);
    }

    private function flush(): void
    {
        $this->matrixBuilder->flush();
    }

    protected function protectedForward(array $items): array
    {
        return $this->matrixBuilder->build($items);
    }
}

class PassThroughMatrixBuilder
{
    public function build(array $items): array
    {
        return $items;
    }

    public function flush(): void
    {
    }
}

<?php

declare(strict_types=1);

class WithPassThroughHelpers
{
    public object $lazy;

    public array $defaults = [];

    public function __construct(
        private readonly PassThroughMatrixBuilder $matrixBuilder,
    ) {
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

    private function variadicForward(int ...$numbers): int
    {
        return $this->matrixBuilder->sum(...$numbers);
    }

    private function forwardsProperty(): array
    {
        return $this->matrixBuilder->build($this->defaults);
    }

    private function reordersArguments(string $first, string $second): void
    {
        $this->matrixBuilder->pair($second, $first);
    }

    private function dropsParameter(array $items, bool $flag): array
    {
        return $this->matrixBuilder->build($items);
    }

    private function usesNamedArgument(array $items): array
    {
        return $this->matrixBuilder->build(items: $items);
    }

    private function callsNonPromotedProperty(array $items): array
    {
        return $this->lazy->build($items);
    }

    private function callsThroughChain(array $items): array
    {
        return $this->lazy->inner->build($items);
    }

    private function callsAccessorInChain(array $items): array
    {
        return $this->matrixBuilder->inner()->build($items);
    }

    private function delegatesToSibling(array $items): array
    {
        return $this->reshape($items);
    }

    private function reshape(array $items): array
    {
        return $this->matrixBuilder->build(array_values($items));
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

    public function sum(int ...$numbers): int
    {
        return array_sum($numbers);
    }

    public function pair(string $first, string $second): void
    {
    }

    public function inner(): self
    {
        return $this;
    }
}

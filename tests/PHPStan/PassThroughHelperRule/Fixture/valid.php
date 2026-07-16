<?php

declare(strict_types=1);

abstract class ValidPassThroughParent
{
    abstract protected function send(string $message): void;
}

class ValidPassThroughHelpers extends ValidPassThroughParent
{
    public array $defaults = [];

    private object $lazy;

    public function __construct(
        private readonly ValidPassThroughService $service,
    ) {
        $this->lazy = new \stdClass();
    }

    public function publicDelegation(array $items): array
    {
        // Public API delegation is a deliberate surface, not a private helper.
        return $this->service->build($items);
    }

    protected function send(string $message): void
    {
        // Overrides the abstract parent — exists to satisfy the contract.
        $this->service->send($message);
    }

    private function reshapesArgument(array $items): array
    {
        return $this->service->build(array_values($items));
    }

    private function addsArgument(array $items): array
    {
        return $this->service->buildWith($items, true);
    }

    private function reordersArguments(string $first, string $second): void
    {
        $this->service->pair($second, $first);
    }

    private function dropsParameter(array $items, bool $flag): array
    {
        return $this->service->build($items);
    }

    private function usesNamedArgument(array $items): array
    {
        return $this->service->build(items: $items);
    }

    private function forwardsProperty(): array
    {
        return $this->service->build($this->defaults);
    }

    private function callsNonPromotedProperty(array $items): array
    {
        return $this->lazy->build($items);
    }

    private function hasCondition(array $items): array
    {
        if ([] === $items) {
            return [];
        }

        return $this->service->build($items);
    }

    private function hasTwoStatements(array $items): array
    {
        $items = array_values($items);

        return $this->service->build($items);
    }

    private static function staticHelper(ValidPassThroughService $service, array $items): array
    {
        return $service->build($items);
    }

    private function variadicForward(int ...$numbers): void
    {
        $this->service->sum(...$numbers);
    }
}

class ValidPassThroughService
{
    public function build(array $items): array
    {
        return $items;
    }

    public function buildWith(array $items, bool $flag): array
    {
        return $flag ? $items : [];
    }

    public function pair(string $first, string $second): void
    {
    }

    public function send(string $message): void
    {
    }

    public function sum(int ...$numbers): void
    {
    }
}

<?php

declare(strict_types=1);

abstract class ValidPassThroughParent
{
    abstract protected function send(string $message): void;
}

class ValidPassThroughHelpers extends ValidPassThroughParent
{
    public function __construct(
        private readonly ValidPassThroughService $service,
    ) {
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

    private function bindsLiteral(array $items): array
    {
        // Partial application: names a variant. Inlining would scatter the flag.
        return $this->service->buildWith($items, true);
    }

    private function byRefForward(array &$items): array
    {
        return $this->service->build($items);
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

    private function callsAccessorInChain(array $items): array
    {
        // A method call in the receiver chain is logic, not a static path.
        return $this->service->inner()->build($items);
    }

    private function delegatesToSiblingMethod(array $items): array
    {
        // A bare $this->method() call is sibling delegation, not a dependency facade.
        return $this->reshapesArgument($items);
    }

    private static function staticHelper(ValidPassThroughService $service, array $items): array
    {
        return $service->build($items);
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

    public function send(string $message): void
    {
    }

    public function inner(): self
    {
        return $this;
    }
}

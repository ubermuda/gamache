<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\DesignSystemAdoptionCheck;
use Gamache\Check\DesignSystemComponent;
use Gamache\Check\Severity;
use Gamache\Check\Violation;
use PHPUnit\Framework\TestCase;

final class DesignSystemAdoptionCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/DesignSystemAdoptionCheck/templates';
    }

    public function test_counts_class_attributes_in_both_quote_styles(): void
    {
        $check = new DesignSystemAdoptionCheck([$this->button()]);
        $check->run($this->fixtures.'/attributes.html.twig');
        $result = $check->getResult();

        self::assertSame([1, 2, 4, 6], $this->lines($result->violations));
        self::assertSame(
            'Hand-written lp-btn markup; render the Button component instead',
            $result->violations[0]->message,
        );
        self::assertSame($this->fixtures.'/attributes.html.twig', $result->violations[0]->file);
    }

    public function test_counts_twig_hash_keys(): void
    {
        $check = new DesignSystemAdoptionCheck([$this->button(), $this->card()]);
        $check->run($this->fixtures.'/hash_keys.html.twig');
        $result = $check->getResult();

        self::assertSame([1, 2, 2, 3], $this->lines($result->violations));
        self::assertStringContainsString('lp-card', $result->violations[2]->message);
    }

    public function test_counts_root_class_inside_twig_expressions_and_skips_comments(): void
    {
        $check = new DesignSystemAdoptionCheck([$this->button()]);
        $check->run($this->fixtures.'/twig_expressions.html.twig');

        self::assertSame([1, 2, 3, 5], $this->lines($check->getResult()->violations));
    }

    public function test_modifier_prefix_and_other_attributes_count_zero(): void
    {
        $check = new DesignSystemAdoptionCheck([$this->button()]);
        $check->run($this->fixtures.'/not_counted.html.twig');

        self::assertEmpty($check->getResult()->violations);
    }

    public function test_own_template_skips_only_its_component(): void
    {
        $check = new DesignSystemAdoptionCheck([$this->button(), $this->card()]);
        $check->run($this->fixtures.'/components/Ds/Button.html.twig');
        $result = $check->getResult();

        self::assertCount(1, $result->violations);
        self::assertSame(2, $result->violations[0]->line);
        self::assertStringContainsString('Card component', $result->violations[0]->message);
    }

    public function test_ignored_path_is_not_scanned(): void
    {
        $check = new DesignSystemAdoptionCheck([$this->button()], ignoredPaths: ['templates/legacy']);
        $check->run($this->fixtures.'/legacy/old.html.twig');

        self::assertEmpty($check->getResult()->violations);
    }

    public function test_ignored_path_matches_whole_segments_only(): void
    {
        $check = new DesignSystemAdoptionCheck([$this->button()], ignoredPaths: ['templates/leg']);
        $check->run($this->fixtures.'/legacy/old.html.twig');

        self::assertCount(1, $check->getResult()->violations);
    }

    public function test_component_not_enforced_warns(): void
    {
        $check = new DesignSystemAdoptionCheck([$this->button()]);
        $check->run($this->fixtures.'/legacy/old.html.twig');
        $result = $check->getResult();

        self::assertSame(Severity::Warning, $result->violations[0]->severity);
        self::assertFalse($result->hasFailed());
    }

    public function test_enforced_component_fails(): void
    {
        $check = new DesignSystemAdoptionCheck([new DesignSystemComponent('Button', 'lp-btn', enforced: true)]);
        $check->run($this->fixtures.'/legacy/old.html.twig');
        $result = $check->getResult();

        self::assertSame(Severity::Error, $result->violations[0]->severity);
        self::assertTrue($result->hasFailed());
    }

    public function test_no_components_gives_no_violations(): void
    {
        $check = new DesignSystemAdoptionCheck();
        $check->run($this->fixtures.'/attributes.html.twig');

        self::assertEmpty($check->getResult()->violations);
    }

    private function button(): DesignSystemComponent
    {
        return new DesignSystemComponent('Button', 'lp-btn', 'templates/components/Ds/Button.html.twig');
    }

    private function card(): DesignSystemComponent
    {
        return new DesignSystemComponent('Card', 'lp-card');
    }

    /**
     * @param list<Violation> $violations
     *
     * @return list<int|null>
     */
    private function lines(array $violations): array
    {
        return array_map(static fn (Violation $violation): ?int => $violation->line, $violations);
    }
}

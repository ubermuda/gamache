<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\DesignSystemPattern;
use Gamache\Check\DesignSystemPatternCheck;
use Gamache\Check\Severity;
use Gamache\Check\Violation;
use PHPUnit\Framework\TestCase;

final class DesignSystemPatternCheckTest extends TestCase
{
    private const string FORM_PATTERN = '~<form\b[^>]*\bmethod\s*=\s*["\']post["\'][^>]*>\s*<input\b[^>]*\bname\s*=\s*["\']_csrf_token["\'][^>]*>\s*<(button|twig:Ds:Button)\b[^>]*>(?:(?!<(?:button|twig:Ds:Button)\b).)*?</\1>\s*</form>~is';

    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/DesignSystemPatternCheck/templates';
    }

    public function test_flags_single_line_matches_and_skips_comments_and_marked_lines(): void
    {
        $check = new DesignSystemPatternCheck([$this->relativeTime()]);
        $check->run($this->fixtures.'/relative_time.html.twig');
        $result = $check->getResult();

        self::assertSame([1, 2], $this->lines($result->violations));
        self::assertSame(
            'Hand-written relative_time filter call; render the RelativeTime component instead',
            $result->violations[0]->message,
        );
        self::assertSame($this->fixtures.'/relative_time.html.twig', $result->violations[0]->file);
    }

    public function test_multi_line_match_reports_its_first_line(): void
    {
        $check = new DesignSystemPatternCheck([$this->actionForm()]);
        $check->run($this->fixtures.'/forms.html.twig');

        self::assertSame([1, 25], $this->lines($check->getResult()->violations));
    }

    public function test_custom_marker_replaces_the_default_one(): void
    {
        $check = new DesignSystemPatternCheck([$this->actionForm()], ignoreMarker: 'name="b"');
        $check->run($this->fixtures.'/forms.html.twig');

        self::assertSame([1, 19, 25], $this->lines($check->getResult()->violations));
    }

    public function test_empty_marker_skips_nothing(): void
    {
        $check = new DesignSystemPatternCheck([$this->relativeTime()], ignoreMarker: '');
        $check->run($this->fixtures.'/relative_time.html.twig');

        self::assertSame([1, 2, 5], $this->lines($check->getResult()->violations));
    }

    public function test_own_template_skips_only_its_pattern(): void
    {
        $check = new DesignSystemPatternCheck([$this->relativeTime(), $this->actionForm()]);
        $check->run($this->fixtures.'/components/Ds/RelativeTime.html.twig');
        $result = $check->getResult();

        self::assertSame([2], $this->lines($result->violations));
        self::assertStringContainsString('ActionForm component', $result->violations[0]->message);
    }

    public function test_ignored_path_is_not_scanned(): void
    {
        $check = new DesignSystemPatternCheck([$this->relativeTime()], ignoredPaths: ['templates/legacy']);
        $check->run($this->fixtures.'/legacy/old.html.twig');

        self::assertEmpty($check->getResult()->violations);
    }

    public function test_ignored_path_matches_whole_segments_only(): void
    {
        $check = new DesignSystemPatternCheck([$this->relativeTime()], ignoredPaths: ['templates/leg']);
        $check->run($this->fixtures.'/legacy/old.html.twig');

        self::assertCount(1, $check->getResult()->violations);
    }

    public function test_pattern_not_enforced_warns(): void
    {
        $check = new DesignSystemPatternCheck([new DesignSystemPattern('RelativeTime', '/\|\s*relative_time\b/', 'relative_time filter call')]);
        $check->run($this->fixtures.'/legacy/old.html.twig');
        $result = $check->getResult();

        self::assertSame(Severity::Warning, $result->violations[0]->severity);
        self::assertFalse($result->hasFailed());
    }

    public function test_enforced_pattern_fails(): void
    {
        $check = new DesignSystemPatternCheck([$this->relativeTime()]);
        $check->run($this->fixtures.'/legacy/old.html.twig');
        $result = $check->getResult();

        self::assertSame(Severity::Error, $result->violations[0]->severity);
        self::assertTrue($result->hasFailed());
    }

    public function test_invalid_pattern_gives_one_error_and_other_patterns_still_run(): void
    {
        $check = new DesignSystemPatternCheck([
            new DesignSystemPattern('Broken', '/(unclosed/', 'broken markup'),
            $this->relativeTime(),
        ]);
        $check->run($this->fixtures.'/legacy/old.html.twig');
        $violations = $check->getResult()->violations;

        self::assertCount(2, $violations);
        self::assertSame(Severity::Error, $violations[0]->severity);
        self::assertNull($violations[0]->line);
        self::assertStringStartsWith('Could not match the broken markup pattern of the Broken component', $violations[0]->message);
        self::assertSame(1, $violations[1]->line);
    }

    public function test_no_patterns_gives_no_violations(): void
    {
        $check = new DesignSystemPatternCheck();
        $check->run($this->fixtures.'/forms.html.twig');

        self::assertEmpty($check->getResult()->violations);
    }

    private function relativeTime(): DesignSystemPattern
    {
        return new DesignSystemPattern(
            'RelativeTime',
            '/\|\s*relative_time\b/',
            'relative_time filter call',
            'templates/components/Ds/RelativeTime.html.twig',
            enforced: true,
        );
    }

    private function actionForm(): DesignSystemPattern
    {
        return new DesignSystemPattern('ActionForm', self::FORM_PATTERN, 'one-button form', enforced: true);
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

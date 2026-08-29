<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\CheckResult;
use Gamache\Check\Severity;
use Gamache\Check\SkillReferenceCheck;
use PHPUnit\Framework\TestCase;

final class SkillReferenceCheckTest extends TestCase
{
    private string $fixtures;

    /** @var list<string> */
    private array $trees = [];

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/SkillReferenceCheck';
    }

    protected function tearDown(): void
    {
        foreach ($this->trees as $tree) {
            if (is_dir($tree)) {
                self::remove($tree);
            }
        }

        $this->trees = [];
    }

    public function test_passes_when_every_reference_resolves(): void
    {
        $result = $this->check('passing');
        self::assertEmpty($result->violations);
        self::assertFalse($result->hasFailed());
    }

    public function test_detects_a_recipe_the_justfile_does_not_define(): void
    {
        $result = $this->check('missing_recipe');
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('just deploy-prod', $result->violations[0]->message);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
        self::assertTrue($result->hasFailed());
        self::assertSame(5, $result->violations[0]->line);
    }

    public function test_detects_a_file_that_does_not_exist(): void
    {
        $result = $this->check('missing_path');
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('docs/CONVENTIONS.md', $result->violations[0]->message);
    }

    public function test_prose_use_of_just_is_not_a_recipe_reference(): void
    {
        self::assertEmpty($this->check('prose')->violations);
    }

    public function test_reads_recipes_with_parameters_dependencies_and_aliases(): void
    {
        $result = $this->check('justfile_syntax');
        self::assertCount(2, $result->violations);
        self::assertStringContainsString('just prod_image', $result->violations[0]->message);
        self::assertStringContainsString('just positional-arguments', $result->violations[1]->message);
    }

    public function test_a_project_without_a_justfile_reports_no_recipe(): void
    {
        self::assertEmpty($this->check('no_justfile')->violations);
    }

    public function test_placeholders_and_globs_are_not_paths(): void
    {
        self::assertEmpty($this->check('placeholders')->violations);
    }

    public function test_ignored_recipes_and_paths_are_exempt(): void
    {
        $result = $this->check('ignored', ignoredRecipes: ['plugin-recipe'], ignoredPaths: ['docs/GENERATED.md']);
        self::assertEmpty($result->violations);
    }

    public function test_an_exemption_is_needed_for_each_side(): void
    {
        $result = $this->check('ignored', ignoredRecipes: ['plugin-recipe']);
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('docs/GENERATED.md', $result->violations[0]->message);
    }

    public function test_a_recipe_named_rather_than_run_is_a_proposal(): void
    {
        $result = $this->check('mentions');
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('just deploy-prod', $result->violations[0]->message);
        self::assertSame(11, $result->violations[0]->line);
    }

    public function test_mention_markers_can_be_switched_off(): void
    {
        $result = $this->check('mentions', mentionMarkers: []);
        self::assertCount(4, $result->violations);
    }

    public function test_severity_is_configurable(): void
    {
        $result = $this->check('missing_recipe', severity: Severity::Warning);
        self::assertSame(Severity::Warning, $result->violations[0]->severity);
        self::assertFalse($result->hasFailed());
    }

    public function test_src_paths_are_not_scanned_by_default_but_can_be(): void
    {
        $result = $this->check('placeholders', pathPrefixes: ['templates/']);
        self::assertEmpty($result->violations);
    }

    public function test_a_gitignored_path_is_not_reported(): void
    {
        $skill = $this->tree([
            '.claude/skills/demo/SKILL.md' => "A worktree's `e2e/node_modules` is a symlink to the main checkout.\n",
            'e2e/.gitignore' => "node_modules/\n",
        ]);

        self::assertEmpty($this->checkFile($skill)->violations);
    }

    public function test_a_missing_path_git_does_not_ignore_is_still_reported(): void
    {
        $skill = $this->tree([
            '.claude/skills/demo/SKILL.md' => "Conventions live in `docs/CONVENTIONS.md`.\n",
            '.gitignore' => "e2e/node_modules\n",
        ]);

        $result = $this->checkFile($skill);
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('docs/CONVENTIONS.md', $result->violations[0]->message);
    }

    public function test_a_tree_that_is_not_a_repository_reports_missing_paths(): void
    {
        $skill = $this->tree(
            ['.claude/skills/demo/SKILL.md' => "Conventions live in `docs/CONVENTIONS.md`.\n"],
            repository: false,
        );

        $result = $this->checkFile($skill);
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('docs/CONVENTIONS.md', $result->violations[0]->message);
    }

    private function checkFile(string $skillPath): CheckResult
    {
        $check = new SkillReferenceCheck();
        $check->run($skillPath);

        return $check->getResult();
    }

    /**
     * @param array<string, string> $files contents by path relative to the tree root
     *
     * @return string the absolute path of the skill file to run the check on
     */
    private function tree(array $files, bool $repository = true): string
    {
        $root = sys_get_temp_dir().'/gamache_skill_reference_'.uniqid();
        $this->trees[] = $root;

        foreach ($files as $path => $content) {
            $absolute = $root.'/'.$path;
            if (!is_dir(\dirname($absolute))) {
                mkdir(\dirname($absolute), 0o777, true);
            }

            file_put_contents($absolute, $content);
        }

        if ($repository) {
            exec('git init -q '.escapeshellarg($root).' 2>/dev/null', $output, $status);
            if (0 !== $status) {
                self::markTestSkipped('git is not available');
            }
        }

        return $root.'/.claude/skills/demo/SKILL.md';
    }

    private static function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ('.' !== $entry && '..' !== $entry) {
                    self::remove($path.'/'.$entry);
                }
            }

            rmdir($path);

            return;
        }

        unlink($path);
    }

    /**
     * @param list<string>      $ignoredRecipes
     * @param list<string>      $ignoredPaths
     * @param list<string>|null $pathPrefixes
     * @param list<string>|null $mentionMarkers
     */
    private function check(
        string $case,
        array $ignoredRecipes = [],
        array $ignoredPaths = [],
        Severity $severity = Severity::Error,
        ?array $pathPrefixes = null,
        ?array $mentionMarkers = null,
    ): CheckResult {
        $check = new SkillReferenceCheck(
            pathPrefixes: $pathPrefixes,
            ignoredRecipes: $ignoredRecipes,
            ignoredPaths: $ignoredPaths,
            severity: $severity,
            mentionMarkers: $mentionMarkers,
        );
        $check->run($this->fixtures.'/'.$case.'/.claude/skills/demo/SKILL.md');

        return $check->getResult();
    }
}

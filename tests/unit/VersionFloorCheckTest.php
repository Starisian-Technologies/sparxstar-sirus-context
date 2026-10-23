<?php

/**
 * Tests for bin/check-version-floors.php – the guard that keeps the declared
 * WordPress and PHP minimums from drifting apart across the files that state
 * them.
 *
 * Each case copies the real declaration files into a scratch root, breaks one
 * of them, and asserts the check fails for that specific reason. A guard that
 * cannot be shown to fail is not a guard, and this one was written after four
 * of these declarations had already silently disagreed.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

final class VersionFloorCheckTest extends SirusTestCase
{
    /**
     * Files the check reads. Everything else in the repo is irrelevant to it,
     * so the scratch root holds only these.
     *
     * @var list<string>
     */
    private const DECLARATION_FILES = [
        'sparxstar-sirus-context.php',
        'sparxstar-user-environment-check.php',
        'phpcs.xml',
        'phpcs.xml.dist',
        'composer.json',
    ];

    private string $root;

    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);

        $scratch = tempnam(sys_get_temp_dir(), 'floors');
        unlink($scratch);
        mkdir($scratch . '/bin', 0o777, true);
        $this->scratch = $scratch;

        // The script resolves the repo root as dirname(__DIR__), so it has to
        // sit in bin/ of the scratch root for the copies to be what it reads.
        copy($this->root . '/bin/check-version-floors.php', $scratch . '/bin/check-version-floors.php');

        foreach (self::DECLARATION_FILES as $file) {
            copy($this->root . '/' . $file, $scratch . '/' . $file);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::DECLARATION_FILES as $file) {
            @unlink($this->scratch . '/' . $file);
        }
        @unlink($this->scratch . '/bin/check-version-floors.php');
        @rmdir($this->scratch . '/bin');
        @rmdir($this->scratch);

        parent::tearDown();
    }

    /**
     * Runs the check against the scratch root.
     *
     * @return array{0: int, 1: string} Exit code and combined output.
     */
    private function runCheck(): array
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg($this->scratch . '/bin/check-version-floors.php')
            . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }

    /**
     * Rewrites one scratch file, asserting the replacement actually applied so
     * a mutation that silently no-ops cannot masquerade as a passing guard.
     */
    private function mutate(string $file, string $search, string $replacement): void
    {
        $path = $this->scratch . '/' . $file;
        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString(
            $search,
            $contents,
            "Cannot mutate {$file}: it no longer contains the text this test rewrites."
        );

        file_put_contents($path, str_replace($search, $replacement, $contents));
    }

    private function assertCheckFailsWith(string $expectedMessage): void
    {
        [$exitCode, $output] = $this->runCheck();

        $this->assertSame(1, $exitCode, "Expected the check to fail. Output:\n{$output}");
        $this->assertStringContainsString($expectedMessage, $output);
    }

    // ── The repository as it stands ───────────────────────────────────────────

    public function testPassesOnTheRealDeclarations(): void
    {
        [$exitCode, $output] = $this->runCheck();

        $this->assertSame(0, $exitCode, "Expected the check to pass. Output:\n{$output}");
        $this->assertStringContainsString('Version-floor check passed', $output);
    }

    // ── PHPCS rulesets ────────────────────────────────────────────────────────

    public function testFailsWhenARulesetDeclaresNoWordPressFloor(): void
    {
        // The state phpcs.xml.dist was in: no floor at all, so the
        // WordPress.WP.Deprecated* sniffs quietly used the WPCS default.
        $this->mutate('phpcs.xml.dist', '<config name="minimum_wp_version" value="6.8"/>', '');

        $this->assertCheckFailsWith('declares no minimum_wp_version');
    }

    public function testFailsWhenARulesetWordPressFloorDisagreesWithTheHeader(): void
    {
        $this->mutate('phpcs.xml.dist', 'minimum_wp_version" value="6.8"', 'minimum_wp_version" value="6.4"');

        $this->assertCheckFailsWith('minimum_wp_version is 6.4');
    }

    public function testAcceptsTheOlderWpcsPropertyName(): void
    {
        // phpcs.xml uses minimum_supported_wp_version, the WPCS 2.x spelling
        // that 3.x still honours. Either name has to satisfy the check.
        $this->mutate('phpcs.xml', 'minimum_supported_wp_version', 'minimum_wp_version');

        [$exitCode, $output] = $this->runCheck();

        $this->assertSame(0, $exitCode, "Expected either property name to be accepted. Output:\n{$output}");
    }

    public function testFailsWhenARulesetDeclaresNoTestVersion(): void
    {
        $this->mutate('phpcs.xml.dist', '<config name="testVersion" value="8.2-"/>', '');

        $this->assertCheckFailsWith('declares no testVersion');
    }

    public function testFailsWhenTestVersionStartsBelowTheDeclaredPhpFloor(): void
    {
        // phpcs.xml really did say 8.1-8.4 while the header said 8.2.
        $this->mutate('phpcs.xml', 'testVersion" value="8.2-"', 'testVersion" value="8.1-8.4"');

        $this->assertCheckFailsWith('testVersion is 8.1-8.4');
    }

    // ── Plugin headers ────────────────────────────────────────────────────────

    public function testFailsWhenASecondPluginFileDeclaresNoFloors(): void
    {
        // sparxstar-user-environment-check.php carries its own "Plugin Name"
        // header, so WordPress offers it for activation separately. Without
        // the floors it activates anywhere.
        $this->mutate('sparxstar-user-environment-check.php', " * Requires at least: 6.8\n", '');

        $this->assertCheckFailsWith("no 'Requires at least'");
    }

    public function testFailsWhenASecondPluginFileDisagreesOnThePhpFloor(): void
    {
        $this->mutate('sparxstar-user-environment-check.php', ' * Requires PHP: 8.2', ' * Requires PHP: 7.4');

        $this->assertCheckFailsWith("'Requires PHP' is 7.4");
    }

    public function testFailsWhenTheSourceOfTruthItselfIsMissing(): void
    {
        $this->mutate('sparxstar-sirus-context.php', " * Requires at least: 6.8\n", '');

        $this->assertCheckFailsWith('source of truth');
    }

    public function testFailsWhenTheFloorMovesAndTheRulesetsAreLeftBehind(): void
    {
        // Raising the supported floor is the normal reason these files change.
        // The check has to catch the half-done version of that edit.
        $this->mutate('sparxstar-sirus-context.php', ' * Requires at least: 6.8', ' * Requires at least: 6.9');

        $this->assertCheckFailsWith('but the plugin header declares 6.9');
    }

    // ── Composer platform pin ─────────────────────────────────────────────────

    public function testFailsWhenTheComposerPlatformPinSitsBelowTheFloor(): void
    {
        $this->mutate('composer.json', '"php": "8.2.29"', '"php": "8.1.0"');

        $this->assertCheckFailsWith('config.platform.php is 8.1.0');
    }

    // ── Invalid XML ───────────────────────────────────────────────────────────

    public function testFailsWhenARulesetIsNotWellFormedXml(): void
    {
        // A ruleset PHPCS cannot parse reads to this check as a ruleset with
        // no configuration at all, which is exactly as bad and must not pass.
        // This caught a real mistake: a `--` left inside an XML comment while
        // writing the very edit this check guards.
        $this->mutate('phpcs.xml', '<!--', '<!-- -- ');

        // Unparseable, so neither value is found: both assertions fire.
        $this->assertCheckFailsWith('phpcs.xml: declares no minimum_wp_version');
        $this->assertCheckFailsWith('phpcs.xml: declares no testVersion');
    }
}

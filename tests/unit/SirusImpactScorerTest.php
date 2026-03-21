<?php

/**
 * Tests for SirusImpactScorer.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\helpers\SirusImpactScorer;

final class SirusImpactScorerTest extends SirusTestCase
{
    private SirusImpactScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new SirusImpactScorer();
    }

    public function testBaseFormulaOneErrorOneSession(): void
    {
        // 1 * 1 = 1
        $score = $this->scorer->score(['url' => '/page', 'network' => '4g'], []);
        $this->assertSame(1, $score);
    }

    public function testCheckoutUrlAddsWeight(): void
    {
        // 1*1 + 10*10 = 101
        $score = $this->scorer->score(['url' => '/checkout', 'network' => '4g'], []);
        $this->assertSame(101, $score);
    }

    public function testSlowNetworkAddsWeight(): void
    {
        // 1*1 + 5*5 = 26
        $score = $this->scorer->score(['url' => '/page', 'network' => '2g'], []);
        $this->assertSame(26, $score);
    }

    public function testCheckoutAndSlowNetworkCombine(): void
    {
        // 1*1 + 100 + 25 = 126
        $score = $this->scorer->score(['url' => '/checkout', 'network' => 'slow-2g'], []);
        $this->assertSame(126, $score);
    }

    public function testClusterContextScalesBaseFormula(): void
    {
        // 5 * 3 = 15 (no checkout, no slow network)
        $score = $this->scorer->score(
            ['url' => '/page', 'network' => '4g'],
            ['error_count' => 5, 'affected_sessions' => 3]
        );
        $this->assertSame(15, $score);
    }

    public function testNetworkFromContextJson(): void
    {
        // network in context_json: 1*1 + 25 = 26
        $score = $this->scorer->score([
            'url'          => '/page',
            'context_json' => json_encode(['network' => 'slow-3g']),
        ], []);
        $this->assertSame(26, $score);
    }

    public function testSeverityFromScoreLow(): void
    {
        $this->assertSame(SirusImpactScorer::SEVERITY_LOW, $this->scorer->severityFromScore(5));
    }

    public function testSeverityFromScoreMedium(): void
    {
        $this->assertSame(SirusImpactScorer::SEVERITY_MEDIUM, $this->scorer->severityFromScore(15));
    }

    public function testSeverityFromScoreHigh(): void
    {
        $this->assertSame(SirusImpactScorer::SEVERITY_HIGH, $this->scorer->severityFromScore(30));
    }

    public function testSeverityFromScoreCritical(): void
    {
        $this->assertSame(SirusImpactScorer::SEVERITY_CRITICAL, $this->scorer->severityFromScore(60));
    }

    public function testSeverityBoundaries(): void
    {
        $this->assertSame(SirusImpactScorer::SEVERITY_LOW, $this->scorer->severityFromScore(0));
        $this->assertSame(SirusImpactScorer::SEVERITY_LOW, $this->scorer->severityFromScore(9));
        $this->assertSame(SirusImpactScorer::SEVERITY_MEDIUM, $this->scorer->severityFromScore(10));
        $this->assertSame(SirusImpactScorer::SEVERITY_MEDIUM, $this->scorer->severityFromScore(24));
        $this->assertSame(SirusImpactScorer::SEVERITY_HIGH, $this->scorer->severityFromScore(25));
        $this->assertSame(SirusImpactScorer::SEVERITY_HIGH, $this->scorer->severityFromScore(49));
        $this->assertSame(SirusImpactScorer::SEVERITY_CRITICAL, $this->scorer->severityFromScore(50));
    }
}

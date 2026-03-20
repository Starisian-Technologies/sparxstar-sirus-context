<?php

/**
 * Tests for SirusPriorityScorer – impact priority scoring helper.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\helpers\SirusPriorityScorer;

/**
 * Validates the SirusPriorityScorer impact score formula and thresholds.
 */
final class SirusPriorityScorerTest extends SirusTestCase
{
    private SirusPriorityScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new SirusPriorityScorer();
    }

    // ── score() ───────────────────────────────────────────────────────────────

    /**
     * impact_score = error_count * affected_sessions.
     */
    public function testScoreFormula(): void
    {
        $result = $this->scorer->score(10, 5);
        $this->assertSame(50, $result['impact_score']);
    }

    /**
     * 0 errors or 0 sessions always produces score 0.
     */
    public function testScoreZeroWhenNoErrors(): void
    {
        $this->assertSame(0, $this->scorer->score(0, 10)['impact_score']);
        $this->assertSame(0, $this->scorer->score(10, 0)['impact_score']);
    }

    /**
     * impact_score >= 50 → HIGH.
     */
    public function testHighPriorityAtThreshold(): void
    {
        $result = $this->scorer->score(10, 5); // 50 → HIGH
        $this->assertSame(SirusPriorityScorer::PRIORITY_HIGH, $result['priority']);
    }

    /**
     * impact_score above 50 is also HIGH.
     */
    public function testHighPriorityAboveThreshold(): void
    {
        $result = $this->scorer->score(100, 100); // 10000 → HIGH
        $this->assertSame(SirusPriorityScorer::PRIORITY_HIGH, $result['priority']);
    }

    /**
     * impact_score in [10, 49] → MEDIUM.
     */
    public function testMediumPriorityInRange(): void
    {
        $result = $this->scorer->score(5, 2); // 10 → MEDIUM
        $this->assertSame(SirusPriorityScorer::PRIORITY_MEDIUM, $result['priority']);

        $result = $this->scorer->score(7, 7); // 49 → MEDIUM
        $this->assertSame(SirusPriorityScorer::PRIORITY_MEDIUM, $result['priority']);
    }

    /**
     * impact_score < 10 → LOW.
     */
    public function testLowPriorityBelowThreshold(): void
    {
        $result = $this->scorer->score(3, 3); // 9 → LOW
        $this->assertSame(SirusPriorityScorer::PRIORITY_LOW, $result['priority']);

        $result = $this->scorer->score(0, 0); // 0 → LOW
        $this->assertSame(SirusPriorityScorer::PRIORITY_LOW, $result['priority']);
    }

    /**
     * score() returns both impact_score and priority keys.
     */
    public function testScoreReturnShape(): void
    {
        $result = $this->scorer->score(5, 5);
        $this->assertArrayHasKey('impact_score', $result);
        $this->assertArrayHasKey('priority', $result);
    }

    // ── scoreRows() ───────────────────────────────────────────────────────────

    /**
     * scoreRows() should annotate each row with impact_score and priority.
     */
    public function testScoreRowsAnnotatesEachRow(): void
    {
        $rows = [
            ['url' => '/checkout', 'error_count' => 10, 'affected_sessions' => 5],
            ['url' => '/login', 'error_count' => 2, 'affected_sessions' => 1],
        ];

        $scored = $this->scorer->scoreRows($rows);

        $this->assertCount(2, $scored);

        $this->assertArrayHasKey('impact_score', $scored[0]);
        $this->assertArrayHasKey('priority', $scored[0]);
        $this->assertSame(50, $scored[0]['impact_score']);
        $this->assertSame(SirusPriorityScorer::PRIORITY_HIGH, $scored[0]['priority']);

        $this->assertSame(2, $scored[1]['impact_score']);
        $this->assertSame(SirusPriorityScorer::PRIORITY_LOW, $scored[1]['priority']);
    }

    /**
     * scoreRows() preserves all original row fields.
     */
    public function testScoreRowsPreservesOriginalFields(): void
    {
        $rows = [
            ['url' => '/order', 'error_count' => 20, 'affected_sessions' => 3, 'custom_field' => 'kept'],
        ];

        $scored = $this->scorer->scoreRows($rows);

        $this->assertSame('/order', $scored[0]['url']);
        $this->assertSame('kept', $scored[0]['custom_field']);
    }

    /**
     * scoreRows() returns an empty array for empty input.
     */
    public function testScoreRowsEmptyInput(): void
    {
        $this->assertSame([], $this->scorer->scoreRows([]));
    }

    /**
     * Rows missing error_count or affected_sessions default to 0.
     */
    public function testScoreRowsMissingCountsDefaultToZero(): void
    {
        $rows = [['url' => '/test']];
        $scored = $this->scorer->scoreRows($rows);

        $this->assertSame(0, $scored[0]['impact_score']);
        $this->assertSame(SirusPriorityScorer::PRIORITY_LOW, $scored[0]['priority']);
    }
}

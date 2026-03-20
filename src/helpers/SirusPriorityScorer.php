<?php

/**
 * SirusPriorityScorer - Computes impact priority for Sirus observability data.
 *
 * This is a standalone scoring helper, intentionally separate from the DAL.
 * It consumes pre-fetched data and returns a priority level and score.
 *
 * HARD RULE: No database access in this class.
 *
 * Formula: impact_score = error_count * affected_sessions
 * Thresholds:
 *   HIGH   >= 50
 *   MEDIUM >= 10
 *   LOW    < 10
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Computes an impact priority (HIGH/MEDIUM/LOW) from error and session counts.
 * This class contains no DAL calls and no WordPress I/O.
 */
final class SirusPriorityScorer
{
    public const PRIORITY_HIGH   = 'HIGH';
    public const PRIORITY_MEDIUM = 'MEDIUM';
    public const PRIORITY_LOW    = 'LOW';

    private const THRESHOLD_HIGH   = 50;
    private const THRESHOLD_MEDIUM = 10;

    /**
     * Calculates impact_score = error_count * affected_sessions,
     * then maps to a priority label.
     *
     * @param int $errorCount       Total number of error events.
     * @param int $affectedSessions Number of distinct sessions that experienced errors.
     * @return array{impact_score: int, priority: string}
     */
    public function score(int $errorCount, int $affectedSessions): array
    {
        $impact = $errorCount * $affectedSessions;

        $priority = match (true) {
            $impact >= self::THRESHOLD_HIGH   => self::PRIORITY_HIGH,
            $impact >= self::THRESHOLD_MEDIUM => self::PRIORITY_MEDIUM,
            default                           => self::PRIORITY_LOW,
        };

        return [
            'impact_score' => $impact,
            'priority'     => $priority,
        ];
    }

    /**
     * Scores a list of URL failure rows (as returned by SirusEventRepository::getTopFailingUrls).
     * Each row is annotated with its impact_score and priority.
     *
     * @param array<int, array<string, mixed>> $rows Rows with error_count and affected_sessions.
     * @return array<int, array<string, mixed>> Same rows with impact_score and priority added.
     */
    public function scoreRows(array $rows): array
    {
        $scored = [];

        foreach ($rows as $row) {
            $errorCount       = (int) ($row['error_count'] ?? 0);
            $affectedSessions = (int) ($row['affected_sessions'] ?? 0);
            $result           = $this->score($errorCount, $affectedSessions);

            $scored[] = array_merge($row, $result);
        }

        return $scored;
    }
}

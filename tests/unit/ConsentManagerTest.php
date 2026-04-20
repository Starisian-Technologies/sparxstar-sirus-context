<?php

/**
 * Tests for ConsentManager – privacy-first sovereignty invariants.
 *
 * ConsentManager enforces a strict three-level cascade:
 *   1. Individual user meta  (highest priority — per-user override)
 *   2. Site-level option     (authority default — set by site admin)
 *   3. System hard default   (lowest priority — always STATE_DENIED, privacy-first)
 *
 * A cascade-order regression is not a style issue: it is a sovereignty violation.
 * These tests are the canonical record of the required privacy posture.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\ConsentManager;

/**
 * Unit tests for ConsentManager consent cascade, history, and purpose consent.
 */
final class ConsentManagerTest extends SirusTestCase
{
    private ConsentManager $manager;

    /**
     * Reset in-memory WordPress option and user-meta stores before every test
     * so tests are fully isolated.
     */
    protected function setUp(): void
    {
        $GLOBALS['wp_options']   = [];
        $GLOBALS['wp_user_meta'] = [];
        $this->manager           = new ConsentManager();
    }

    // ── Test 1: User preference beats site default ─────────────────────────────

    /**
     * When a user has explicitly granted consent, that overrides a site-level deny.
     *
     * Cascade order: user meta (STATE_GRANTED) → site default (STATE_DENIED)
     * Expected result: STATE_GRANTED
     */
    public function testUserPreferenceTakesPriorityOverSiteDefault(): void
    {
        // Site default: deny.
        $this->manager->setSiteConsentDefault(ConsentManager::STATE_DENIED);

        // User 42: explicitly granted.
        $this->manager->setTechnicalConsent(42, ConsentManager::STATE_GRANTED);

        $this->assertSame(
            ConsentManager::STATE_GRANTED,
            $this->manager->getTechnicalConsent(42),
            'User-level STATE_GRANTED must override a site-level STATE_DENIED.'
        );
    }

    // ── Test 2: Site default used when user has no meta ───────────────────────

    /**
     * When no user meta exists, the site-level default is used.
     *
     * Cascade order: user meta (absent) → site default (STATE_DENIED)
     * Expected result: STATE_DENIED
     */
    public function testSiteDefaultUsedWhenNoUserMetaSet(): void
    {
        $this->manager->setSiteConsentDefault(ConsentManager::STATE_DENIED);

        // User 7 has no personal preference set.
        $this->assertSame(
            ConsentManager::STATE_DENIED,
            $this->manager->getTechnicalConsent(7),
            'Site default STATE_DENIED must be used when user has no meta.'
        );
    }

    // ── Test 3: System hard default is STATE_DENIED — unconditionally ──────────

    /**
     * When neither user meta nor site option is configured, the result is
     * STATE_DENIED. This must hold under every code path — no filter, no missing
     * option, and no WordPress default may resolve to STATE_GRANTED.
     *
     * This is the privacy-first sovereignty invariant: the system defaults to deny.
     */
    public function testSystemHardDefaultIsDeniedWhenNeitherUserNorSiteIsSet(): void
    {
        // Blank environment: no user meta, no site option.
        $result = $this->manager->getTechnicalConsent(99);

        $this->assertSame(
            ConsentManager::STATE_DENIED,
            $result,
            'System hard default MUST be STATE_DENIED when no user meta and no site option exist.'
        );

        $this->assertNotSame(
            ConsentManager::STATE_GRANTED,
            $result,
            'The system hard default must NEVER resolve to STATE_GRANTED.'
        );
    }

    // ── Test 4: Anonymous user (user_id === 0) skips user-meta lookup ─────────

    /**
     * Anonymous users (user_id = 0) must never trigger a user-meta lookup.
     * The result falls through to the site default, then the system hard default.
     */
    public function testAnonymousUserSkipsUserMetaLookupAndFallsToSiteDefault(): void
    {
        // Site default: granted (e.g., a public research site).
        $this->manager->setSiteConsentDefault(ConsentManager::STATE_GRANTED);

        // No user-meta write for user_id = 0 (ConsentManager must not call get_user_meta).
        $result = $this->manager->getTechnicalConsent(0);

        $this->assertSame(
            ConsentManager::STATE_GRANTED,
            $result,
            'Anonymous user must fall through to site default without touching user meta.'
        );
    }

    /**
     * Anonymous users with no site default fall to the system hard default.
     */
    public function testAnonymousUserWithNoSiteDefaultFallsToSystemHardDenied(): void
    {
        $result = $this->manager->getTechnicalConsent(0);

        $this->assertSame(
            ConsentManager::STATE_DENIED,
            $result,
            'Anonymous user with no site default must resolve to system hard default (STATE_DENIED).'
        );
    }

    // ── Test 5: Invalid meta value falls through cascade ──────────────────────

    /**
     * A stored meta value that is neither STATE_GRANTED nor STATE_DENIED
     * (e.g., 'maybe', 'yes', or an old invalid value) must be ignored,
     * and the cascade must continue to the site default.
     */
    public function testInvalidMetaValueFallsThroughToSiteDefault(): void
    {
        // Directly inject an invalid meta value, bypassing setTechnicalConsent().
        $GLOBALS['wp_user_meta'][55]['sirus_technical_consent'] = 'maybe';

        $this->manager->setSiteConsentDefault(ConsentManager::STATE_DENIED);

        $this->assertSame(
            ConsentManager::STATE_DENIED,
            $this->manager->getTechnicalConsent(55),
            'An invalid meta value must not resolve consent — cascade must continue.'
        );
    }

    /**
     * An invalid meta value with no site default must resolve to the system hard
     * default (STATE_DENIED), not to the invalid stored value.
     */
    public function testInvalidMetaWithNoSiteDefaultResolvesToHardDenied(): void
    {
        $GLOBALS['wp_user_meta'][55]['sirus_technical_consent'] = 'yes';

        $this->assertSame(
            ConsentManager::STATE_DENIED,
            $this->manager->getTechnicalConsent(55),
            'Invalid meta + missing site option must fall to system hard default STATE_DENIED.'
        );
    }

    // ── Test 6: Multisite blog context isolation ──────────────────────────────

    /**
     * Blog-specific site defaults must be independent — updating blog 2's default
     * must not affect blog 1's default and vice versa.
     */
    public function testMultisiteBlogContextIsolation(): void
    {
        $this->manager->setSiteConsentDefault(ConsentManager::STATE_GRANTED, 2);
        $this->manager->setSiteConsentDefault(ConsentManager::STATE_DENIED,  1);

        $this->assertSame(
            ConsentManager::STATE_GRANTED,
            $this->manager->getSiteConsentDefault(2),
            'Blog 2 default must be STATE_GRANTED.'
        );

        $this->assertSame(
            ConsentManager::STATE_DENIED,
            $this->manager->getSiteConsentDefault(1),
            'Blog 1 default must be STATE_DENIED — blogs are isolated.'
        );
    }

    /**
     * getSiteConsentDefault with a blog_id not yet configured returns STATE_PENDING,
     * causing getTechnicalConsent to fall through to the system hard default.
     */
    public function testUnconfiguredBlogDefaultReturnsPending(): void
    {
        // Blog 99 has never had a default set.
        $this->assertSame(
            ConsentManager::STATE_PENDING,
            $this->manager->getSiteConsentDefault(99),
            'Unconfigured blog must return STATE_PENDING, not STATE_DENIED or STATE_GRANTED.'
        );
    }

    // ── Test 7: setTechnicalConsent writes to history before updating state ────

    /**
     * History is append-only and recorded before the current state is updated.
     * After calling setTechnicalConsent(), the history must contain exactly one entry
     * with the correct scope, state, and a plausible timestamp.
     */
    public function testSetTechnicalConsentAppendsToHistory(): void
    {
        $before = time();
        $this->manager->setTechnicalConsent(10, ConsentManager::STATE_GRANTED);
        $after = time();

        $history = $this->manager->getHistory(10);

        $this->assertCount(1, $history, 'Exactly one history entry must be appended.');

        $entry = $history[0];
        $this->assertSame('technical', $entry['scope']);
        $this->assertSame(ConsentManager::STATE_GRANTED, $entry['state']);
        $this->assertGreaterThanOrEqual($before, $entry['timestamp']);
        $this->assertLessThanOrEqual($after,  $entry['timestamp']);
    }

    /**
     * Calling setTechnicalConsent() twice appends two history entries (append-only).
     */
    public function testSetTechnicalConsentHistoryIsAppendOnly(): void
    {
        $this->manager->setTechnicalConsent(10, ConsentManager::STATE_GRANTED);
        $this->manager->setTechnicalConsent(10, ConsentManager::STATE_DENIED);

        $history = $this->manager->getHistory(10);

        $this->assertCount(2, $history, 'Two calls must produce two history entries.');
        $this->assertSame(ConsentManager::STATE_GRANTED, $history[0]['state']);
        $this->assertSame(ConsentManager::STATE_DENIED,  $history[1]['state']);
    }

    // ── Test 8: setTechnicalConsent rejects invalid user_id ───────────────────

    /**
     * setTechnicalConsent must return false and write nothing for user_id ≤ 0.
     */
    public function testSetTechnicalConsentRejectsInvalidUserId(): void
    {
        $result = $this->manager->setTechnicalConsent(0, ConsentManager::STATE_GRANTED);

        $this->assertFalse($result, 'setTechnicalConsent must reject user_id = 0.');
        $this->assertEmpty(
            $GLOBALS['wp_user_meta'],
            'No user-meta must be written when user_id is invalid.'
        );
    }

    /**
     * setTechnicalConsent must return false for an unrecognised state string.
     */
    public function testSetTechnicalConsentRejectsInvalidState(): void
    {
        $result = $this->manager->setTechnicalConsent(10, 'maybe');

        $this->assertFalse($result, 'setTechnicalConsent must reject invalid state strings.');
    }

    // ── Test 9: setPurposeConsent validates key and state ────────────────────

    /**
     * setPurposeConsent must return false and write nothing when the purpose key
     * is empty after sanitization.
     */
    public function testSetPurposeConsentRejectsEmptyPurposeKey(): void
    {
        $result = $this->manager->setPurposeConsent(10, '!!!', ConsentManager::STATE_GRANTED);

        $this->assertFalse($result, 'setPurposeConsent must reject keys that are empty after sanitization.');
    }

    /**
     * setPurposeConsent accepts a valid purpose key and stores STATE_GRANTED.
     * getPurposeConsent returns the correct state for that key.
     */
    public function testSetPurposeConsentStoresState(): void
    {
        $result = $this->manager->setPurposeConsent(10, 'analytics', ConsentManager::STATE_GRANTED);

        $this->assertTrue($result);

        $map = $this->manager->getPurposeConsent(10);
        $this->assertArrayHasKey('analytics', $map);
        $this->assertSame(ConsentManager::STATE_GRANTED, $map['analytics']);
    }

    // ── Test 10: getPurposeConsent filters invalid state values ──────────────

    /**
     * getPurposeConsent must silently drop purpose entries whose state is not
     * STATE_GRANTED or STATE_DENIED, ensuring the returned map is always clean.
     */
    public function testGetPurposeConsentFiltersInvalidStateValues(): void
    {
        // Directly inject a mixed map that contains an invalid entry.
        $GLOBALS['wp_user_meta'][10]['sirus_purpose_consent'] = [
            'analytics' => ConsentManager::STATE_GRANTED,
            'telemetry' => 'maybe',                        // invalid — must be filtered
            'profiling' => ConsentManager::STATE_DENIED,
        ];

        $map = $this->manager->getPurposeConsent(10);

        $this->assertArrayHasKey('analytics', $map);
        $this->assertArrayHasKey('profiling', $map);
        $this->assertArrayNotHasKey(
            'telemetry',
            $map,
            "Invalid state value 'maybe' must be filtered out of the purpose-consent map."
        );
    }
}

<?php

/**
 * ConsentManager - Manages technical and purpose-level consent for Sirus context operations.
 *
 * Consent is recorded as WordPress options (per-site) and user meta (per-identity).
 * This class owns:
 *   - Technical consent: whether the user has agreed to device/session tracking.
 *   - Purpose consent:   per-named-purpose opt-in/opt-out.
 *   - Consent history:   immutable append-only log of consent changes.
 *
 * Technical consent is resolved in a three-level cascade:
 *   1. Individual user meta (highest priority — per-user override)
 *   2. Site-level WordPress option (authority default — set by site admin)
 *   3. System hard default: STATE_DENIED (lowest priority — privacy-first)
 *
 * ConsentManager MUST NOT make authorization decisions. It records and surfaces
 * consent state only. Downstream layers (Helios, Mehns) act on that state.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Records and retrieves consent state for device and purpose-level tracking.
 *
 * Consent is resolved in a strict three-level cascade:
 *   1. Individual preference  — per-user WordPress user meta (highest priority)
 *   2. Authority default      — per-site WordPress option set by the site admin
 *   3. System hard default    — STATE_DENIED (lowest priority, privacy-first)
 *
 * All writes are append-only to the consent history. Existing history records
 * MUST NOT be updated or deleted.
 */
final class ConsentManager
{
    /**
     * WordPress option key for the site-level technical consent default.
     *
     * Set by a site administrator to define the site's default privacy posture.
     * Individual users can override this via user meta (META_TECHNICAL_CONSENT).
     * Stored as a site option so multisite sub-sites have independent defaults.
     */
    private const OPTION_TECHNICAL_CONSENT = 'sirus_technical_consent_default';
    /** User meta key for per-user technical consent state. */
    private const META_TECHNICAL_CONSENT = 'sirus_technical_consent';

    /** User meta key for per-user purpose consent map. */
    private const META_PURPOSE_CONSENT = 'sirus_purpose_consent';

    /** User meta key for per-user consent history (append-only). */
    private const META_CONSENT_HISTORY = 'sirus_consent_history';

    /** Consent state: explicitly granted. */
    public const STATE_GRANTED = 'granted';

    /** Consent state: explicitly denied. */
    public const STATE_DENIED = 'denied';

    /** Consent state: not yet set (pending). */
    public const STATE_PENDING = 'pending';

    /**
     * Returns the current technical consent state for the given user.
     *
     * Resolution cascade (highest priority first):
     *   1. Individual user meta — if explicitly set, returns it directly.
     *   2. Site-level option   — authority default set by the site admin.
     *   3. System hard default — STATE_DENIED (privacy-first).
     *
     * Anonymous users (user_id = 0) skip user-meta resolution and fall through
     * to the site default or STATE_DENIED.
     *
     * @param int $user_id WordPress user ID.
     * @return string One of STATE_GRANTED, STATE_DENIED, STATE_PENDING.
     */
    public function getTechnicalConsent(int $user_id): string
    {
        // Step 1: per-user preference (highest priority).
        if ($user_id > 0) {
            $raw = get_user_meta($user_id, self::META_TECHNICAL_CONSENT, true);

            if ($raw === self::STATE_GRANTED || $raw === self::STATE_DENIED) {
                return $raw;
            }
        }

        // Step 2: site authority default.
        $site_default = $this->getSiteConsentDefault();

        if ($site_default === self::STATE_GRANTED || $site_default === self::STATE_DENIED) {
            return $site_default;
        }

        // Step 3: system hard default — deny (privacy-first).
        return self::STATE_DENIED;
    }

    /**
     * Records a technical consent decision for the given user.
     *
     * Appends to consent history before updating the current state so that
     * the history record always reflects the state at the time of change.
     *
     * @param int    $user_id WordPress user ID. Must be > 0.
     * @param string $state   One of STATE_GRANTED or STATE_DENIED.
     * @return bool True on success, false if user_id is invalid or state is unrecognised.
     */
    public function setTechnicalConsent(int $user_id, string $state): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        if ($state !== self::STATE_GRANTED && $state !== self::STATE_DENIED) {
            return false;
        }

        $this->appendHistory($user_id, 'technical', $state);
        update_user_meta($user_id, self::META_TECHNICAL_CONSENT, $state);

        return true;
    }

    /**
     * Returns the site-level technical consent default.
     *
     * This is the authority default used by getTechnicalConsent() when no
     * per-user preference is set. Set by site administrators via setSiteConsentDefault().
     *
     * Returns STATE_PENDING when no site default has been configured, which
     * causes getTechnicalConsent() to fall through to the system hard default (STATE_DENIED).
     *
     * @param int $blog_id Blog/site ID. 0 = current site.
     * @return string One of STATE_GRANTED, STATE_DENIED, or STATE_PENDING.
     */
    public function getSiteConsentDefault(int $blog_id = 0): string
    {
        $raw = $blog_id > 0
            ? get_blog_option($blog_id, self::OPTION_TECHNICAL_CONSENT, self::STATE_PENDING)
            : get_option(self::OPTION_TECHNICAL_CONSENT, self::STATE_PENDING);

        if ($raw === self::STATE_GRANTED || $raw === self::STATE_DENIED) {
            return (string) $raw;
        }

        return self::STATE_PENDING;
    }

    /**
     * Sets the site-level technical consent default.
     *
     * Only site administrators should call this. ConsentManager does not enforce
     * capability checks — callers must ensure appropriate authorization before
     * invoking this method.
     *
     * @param string $state   One of STATE_GRANTED or STATE_DENIED.
     * @param int    $blog_id Blog/site ID. 0 = current site.
     * @return bool True on success, false if state is unrecognised.
     */
    public function setSiteConsentDefault(string $state, int $blog_id = 0): bool
    {
        if ($state !== self::STATE_GRANTED && $state !== self::STATE_DENIED) {
            return false;
        }

        if ($blog_id > 0) {
            update_blog_option($blog_id, self::OPTION_TECHNICAL_CONSENT, $state);
        } else {
            update_option(self::OPTION_TECHNICAL_CONSENT, $state);
        }

        return true;
    }

    /**
     * Returns the purpose consent map for the given user.
     *
     * @param int $user_id WordPress user ID.
     * @return array<string, string> Map of purpose_key → STATE_* constant.
     */
    public function getPurposeConsent(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $raw = get_user_meta($user_id, self::META_PURPOSE_CONSENT, true);

        if (! is_array($raw)) {
            return [];
        }

        /** @var array<string, string> $consent */
        $consent = array_filter(
            $raw,
            static fn ($v): bool => in_array($v, [self::STATE_GRANTED, self::STATE_DENIED], true)
        );

        return $consent;
    }

    /**
     * Records a purpose-level consent decision for the given user.
     *
     * @param int    $user_id     WordPress user ID. Must be > 0.
     * @param string $purpose_key Machine-readable purpose identifier (e.g. 'analytics', 'telemetry').
     * @param string $state       One of STATE_GRANTED or STATE_DENIED.
     * @return bool True on success, false if inputs are invalid.
     */
    public function setPurposeConsent(int $user_id, string $purpose_key, string $state): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $sanitized_purpose_key = sanitize_key($purpose_key);

        if ($sanitized_purpose_key === '') {
            return false;
        }

        if ($state !== self::STATE_GRANTED && $state !== self::STATE_DENIED) {
            return false;
        }

        $map = $this->getPurposeConsent($user_id);
        $map[ $sanitized_purpose_key ] = $state;

        $this->appendHistory($user_id, 'purpose:' . $sanitized_purpose_key, $state);
        update_user_meta($user_id, self::META_PURPOSE_CONSENT, $map);

        return true;
    }

    /**
     * Returns the consent history for the given user (most-recent last).
     *
     * History is append-only — entries are never deleted or modified.
     *
     * @param int $user_id WordPress user ID.
     * @return array<int, array{scope: string, state: string, timestamp: int}> History entries.
     */
    public function getHistory(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $raw = get_user_meta($user_id, self::META_CONSENT_HISTORY, true);

        if (! is_array($raw)) {
            return [];
        }

        return array_values(
            array_filter(
                $raw,
                static fn ($entry): bool =>
                    is_array($entry) &&
                    isset($entry['scope'], $entry['state'], $entry['timestamp']) &&
                    is_string($entry['scope']) &&
                    is_string($entry['state']) &&
                    is_int($entry['timestamp'])
            )
        );
    }

    /**
     * Appends a new history entry. This is the only write path to the history.
     *
     * @param int    $user_id   WordPress user ID.
     * @param string $scope     Consent scope identifier (e.g. 'technical', 'purpose:analytics').
     * @param string $state     The consent state recorded at this point.
     */
    private function appendHistory(int $user_id, string $scope, string $state): void
    {
        $history = $this->getHistory($user_id);

        $history[] = [
            'scope'     => $scope,
            'state'     => $state,
            'timestamp' => time(),
        ];

        update_user_meta($user_id, self::META_CONSENT_HISTORY, $history);
    }
}

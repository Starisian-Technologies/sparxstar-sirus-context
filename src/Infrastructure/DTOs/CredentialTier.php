<?php

declare(strict_types=1);

namespace Starisian\Sparxstar\Infrastructure\DTOs;

/**
 * Provisional definition — remove once Ouroboros exports this type (tracked in OQ-009).
 *
 * Credential tier represents the identity/role axis of the two-field trust architecture.
 * Orthogonal to TrustLevelPrimitive (device trust axis). Defined in PAM-003 §2.1.
 */
enum CredentialTier: string
{
    // ── Identity/role-axis tiers (lowercase backing values) ───────────────────
    case ANONYMOUS   = 'anonymous';
    case DEVICE      = 'device';
    case CONTRIBUTOR = 'contributor';
    case USER        = 'user';
    case AUTHORITY   = 'authority';

    // ── Trust-level-axis tiers (uppercase backing values, match TrustLevelPrimitive) ──
    case LOCKED           = 'LOCKED';
    case STEP_UP_REQUIRED = 'STEP_UP_REQUIRED';
    case NORMAL           = 'NORMAL';
}

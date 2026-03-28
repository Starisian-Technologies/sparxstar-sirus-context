# NetworkContextBroker

**Namespace:** `Starisian\Sparxstar\Sirus\core`

**Full Class Name:** `Starisian\Sparxstar\Sirus\core\NetworkContextBroker`

## Description

NetworkContextBroker - Generates and verifies signed cross-domain context tokens.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Creates and verifies short-lived signed tokens that carry a portable SirusContext
payload across network boundaries without exposing the identity_id.

## Methods

### `generateToken(SirusContext $context)`

NetworkContextBroker - Generates and verifies signed cross-domain context tokens.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Creates and verifies short-lived signed tokens that carry a portable SirusContext
payload across network boundaries without exposing the identity_id.
/
final class NetworkContextBroker
{
    /** Short-lived token TTL in seconds. */
    private const TOKEN_TTL = 30;

    /**
Generates a signed base64url-encoded context token.
Format: base64url(json_payload) . '.' . base64url(hmac_signature)
@param SirusContext $context The context to encode into the token.
@return string The signed token string.

### `verifyToken(string $token)`

Verifies a signed token and returns a reconstructed SirusContext, or null on failure.
@param string $token The token string to verify.
@return SirusContext|null The reconstructed context, or null if invalid/expired.

### `base64url_encode(string $data)`

Encodes a binary string using base64url (RFC 4648 §5).
@param string $data Raw bytes or plain string to encode.

### `base64url_decode(string $data)`

Decodes a base64url-encoded string back to raw bytes.
@param string $data Base64url-encoded string.


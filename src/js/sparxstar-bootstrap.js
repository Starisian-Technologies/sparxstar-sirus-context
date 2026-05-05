/**
 * @file sparxstar-bootstrap.js
 * @version 2.0.0
 * @description Bootstrap file for the Sirus Context Engine JS client.
 *
 * ARCHITECTURE NOTE (spec §B):
 * Device detection runs server-side via Matomo DeviceDetector (PHP).
 * The JS client sends raw signals only — it never bundles a device-detection library.
 * Bundling device-detector-js (~576KB) client-side violates the lightweight client rule.
 *
 * FINGERPRINTING NOTE:
 * FingerprintJS is enqueued as a separate vendored <script> tag by the PHP plugin
 * (src/js/vendor/fingerprintjs.min.js). It is NOT bundled via npm. The collector
 * accesses it via window.FingerprintJS and treats it as optional — if the script
 * is absent, visitor_id collection is skipped gracefully.
 *
 * CROSS-DOMAIN CONTEXT HANDOFF (spec §F):
 * When a signed context token arrives via the `?ctx` URL parameter, this bootstrap:
 *   1. Reads the token from the URL.
 *   2. Validates the format and exposes it on window.SPARXSTAR._inboundContextToken
 *      for optional consumption by state/sync modules.
 *   3. Calls history.replaceState() to REMOVE the parameter from the URL immediately.
 *      This is a security requirement — the token must never persist in browser history,
 *      server logs, or referrer headers.
 */
window.SPARXSTAR = window.SPARXSTAR || {};

// ── Cross-domain context token handoff ────────────────────────────────────────
(function handleCtxHandoff() {
    try {
        const params = new URLSearchParams(window.location.search);
        const ctxToken = params.get('ctx');

        if (ctxToken) {
            // Validate the token format before storing: Sirus tokens are base64url-encoded
            // JSON payloads. A valid token contains only base64url characters (A-Z, a-z, 0-9,
            // -, _, .) and is at least 32 characters long.
            // This prevents malicious or malformed values from reaching state consumers.
            const isValidFormat = /^[A-Za-z0-9\-_.]{32,}$/.test(ctxToken);

            if (isValidFormat) {
                // Expose the token on the global SPARXSTAR namespace for downstream consumers.
                window.SPARXSTAR._inboundContextToken = ctxToken;
            } else if (window.console && console.warn) {
                console.warn('[SPARXSTAR] Discarded malformed ctx token');
            }

            // SECURITY: Remove ?ctx from the URL immediately regardless of validity.
            // history.replaceState() does not trigger a page reload.
            // The token must never persist in browser history, server logs, or referrer headers.
            params.delete('ctx');
            const newSearch = params.toString();
            const newUrl =
                window.location.pathname +
                (newSearch ? '?' + newSearch : '') +
                window.location.hash;
            window.history.replaceState(null, document.title, newUrl);
        }
    } catch (e) {
        // Silently ignore — non-blocking.
        if (window.console && console.warn) {
            console.warn('[SPARXSTAR] ctx handoff failed', e && e.message ? e.message : e);
        }
    }
})();

// ── Import all IIFE modules ───────────────────────────────────────────────────
import './sparxstar-state.js';
import './sparxstar-collector.js';
import './sparxstar-profile.js';
import './sparxstar-sync.js';
import './sparxstar-recorder.js';
import './sparxstar-ui.js';
import './sparxstar-integrator.js';

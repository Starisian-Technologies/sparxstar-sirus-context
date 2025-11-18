/**
 * @file sparxstar-sync.js
 * @version 2.1.0
 * @description Resilient server communication for UEC snapshots (fingerprint + device hash + session).
 */
(function (window) {
    'use strict';

    window.SPARXSTAR = window.SPARXSTAR || {};

    const localized = window.sparxstarUserEnvData || {};
    const nonce     = localized.nonce || '';
    const restUrls  = localized.rest_urls || {};
    const debug     = !!localized.debug;

    const log = (msg, data) => {
        if (debug && window.console && console.debug) {
            console.debug('[SPARXSTAR Sync]', msg, data || '');
        }
    };

    function send(endpointUrl, payload) {
        if (!endpointUrl) return log('Missing endpoint URL.');
        if (!nonce)       return log('Missing nonce.');

        const json = JSON.stringify(payload || {});
        const blob = new Blob([json], { type: 'application/json' });

        // Prefer navigator.sendBeacon
        if (navigator.sendBeacon) {
            const ok = navigator.sendBeacon(endpointUrl, blob);
            if (ok) {
                log('Sent via sendBeacon.', { endpointUrl });
                return;
            }
            log('sendBeacon failed → falling back to fetch.');
        }

        fetch(endpointUrl, {
            method: 'POST',
            body: blob,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            keepalive: true
        })
            .then(() => log('Sent via fetch.', { endpointUrl }))
            .catch(err => log('Fetch failed.', err.message));
    }

    /**
     * TECHNICAL SNAPSHOT PAYLOAD FORMAT
     *
     * {
     *   fingerprint: "...",
     *   device_hash: "...",
     *   session_id: "...",
     *   data: {
     *      userAgent, screen, features, privacy, network, device, client, os, language, ...
     *   }
     * }
     */
    function sendTechnicalSnapshot(fingerprint, deviceHash, sessionId, technicalData) {
        if (!restUrls.technical) {
            return log('Technical endpoint missing.');
        }

        const payload = {
            fingerprint: fingerprint || '',
            device_hash: deviceHash || '',
            session_id: sessionId || '',
            data: technicalData || {}
        };

        send(restUrls.technical, payload);
    }

    /**
     * IDENTIFYING SNAPSHOT PAYLOAD FORMAT
     *
     * {
     *   fingerprint: "...",
     *   device_hash: "...",
     *   session_id: "...",
     *   identifiers: {
     *       cookie_id, local_id, timezone, battery, storage, etc...
     *   }
     * }
     */
    function sendIdentifyingSnapshot(fingerprint, deviceHash, sessionId, identifiersData) {
        if (!restUrls.identifiers) {
            return log('Identifiers endpoint missing.');
        }

        const payload = {
            fingerprint: fingerprint || '',
            device_hash: deviceHash || '',
            session_id: sessionId || '',
            identifiers: identifiersData || {}
        };

        send(restUrls.identifiers, payload);
    }

    window.SPARXSTAR.Sync = {
        sendTechnicalSnapshot,
        sendIdentifyingSnapshot
    };

})(window);

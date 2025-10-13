/**
 * @file Main client-side script for SPARXSTAR User Environment Check.
 * @description Compatibility check, banner, session ID, daily diagnostics, and snapshot send.
 */
(function() {
    'use strict';

    // ---------- Logger ----------
    const Logger = {
        levels: { ERROR: 0, WARN: 1, INFO: 2, DEBUG: 3 },
        currentLevel: window.sparxstarUserEnvData?.debug ? 3 : 1,
        log(level, message, data = null) {
            if (level <= this.currentLevel) {
                const prefix = '[SparxstarUserEnv]';
                const t = new Date().toISOString();
                if (level === 0) return console.error(`${prefix} ${t} ERROR:`, message, data);
                if (level === 1) return console.warn(`${prefix} ${t} WARN:`,  message, data);
                if (level === 2) return console.info(`${prefix} ${t} INFO:`,  message, data);
                return console.debug(`${prefix} ${t} DEBUG:`, message, data);
            }
        },
        error(m,d){this.log(0,m,d);}, warn(m,d){this.log(1,m,d);}, info(m,d){this.log(2,m,d);}, debug(m,d){this.log(3,m,d);}
    };

    // ---------- Local Storage helper ----------
    const LS = {
        get: (k) => { try { return localStorage.getItem(`sparxstaruserenv:${k}`);} catch(e){ Logger.warn('localStorage get failed',{k,e:e.message}); return null;}},
        set: (k,v) => { try { localStorage.setItem(`sparxstaruserenv:${k}`, v);} catch(e){ Logger.warn('localStorage set failed',{k,e:e.message});}}
    };

    // ---------- Env data from PHP ----------
    if (!window.sparxstarUserEnvData) { Logger.error('sparxstarUserEnvData not found'); return; }
    
    // Destructure the localized data
    const { nonce, rest_urls, i18n } = window.sparxstarUserEnvData;
    const log_rest_url = rest_urls ? rest_urls.log : null; // Use the specific 'log' URL
    const fingerprint_rest_url = rest_urls ? rest_urls.fingerprint : null; // Also get the fingerprint URL

    if (!nonce || !log_rest_url)  {
        Logger.error('Missing REST config for log endpoint', { nonce: nonce, log_rest_url: log_rest_url });
        return;
    }
    // The problematic `const rsp = await fetch(log_rest_url, ...)` block was here. It has been removed.

    // ---------- Session ID (stable in sessionStorage, robust fallback) ----------
    let sessionId;
    try {
        sessionId = sessionStorage.getItem('sparxstaruserenv_session_id');
        if (!sessionId) {
            sessionId = (crypto?.randomUUID?.() ?? ('ses_' + Date.now() + '_' + secureRand(12)));
            sessionStorage.setItem('sparxstaruserenv_session_id', sessionId);
            Logger.debug('New session ID', { sessionId });
        } else {
            Logger.debug('Existing session ID', { sessionId });
        }
    } catch(e){
        sessionId = 'ses_' + Date.now() + '_' + secureRand(12);
        Logger.warn('sessionStorage unavailable; temp session ID used', { sessionId, err: e.message });
    }
    function secureRand(n) {
        const arr = new Uint8Array(n);
        if (crypto?.getRandomValues) { crypto.getRandomValues(arr); return Array.from(arr).map(b=>b.toString(36)).join(''); }
        return Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
    }

    // ---------- Compatibility + Banner ----------
    function isBrowserCompatible() {
        return (
            'Promise' in window &&
            'fetch' in window &&
            'MediaRecorder' in window &&
            (navigator.mediaDevices && 'getUserMedia' in navigator.mediaDevices)
        );
    }

    function displayUpgradeBanner() {
        if (LS.get('bannerDismissed') === 'true') return;
        if (isBrowserCompatible()) return;
        const banner = document.createElement('div');
        banner.className = 'sparxstaruserenv-banner';
        banner.innerHTML = `
            <div class="sparxstaruserenv-banner-content">
                <strong>${i18n.notice}</strong> ${i18n.update_message}
                <a href="https://browsehappy.com/" target="_blank" rel="noopener noreferrer">${i18n.update_link}</a>.
            </div>
            <button class="sparxstaruserenv-dismiss" aria-label="${i18n.dismiss}">&times;</button>`;
        document.body.appendChild(banner);
        banner.querySelector('.sparxstaruserenv-dismiss').addEventListener('click', () => {
            banner.remove(); LS.set('bannerDismissed','true');
        });
    }

    // --- NEW: Offline Notification Banner ---
    function displayOfflineBanner() {
        // First, check if a banner is already there to avoid duplicates
        if (document.getElementById('sparxstaruserenv-offline-banner')) return;

        Logger.warn('Displaying offline notification banner.');
        const banner = document.createElement('div');
        banner.id = 'sparxstaruserenv-offline-banner'; // Use an ID for easy removal
        banner.className = 'sparxstaruserenv-banner sparxstaruserenv-banner-offline'; // Add a specific class for styling
        banner.innerHTML = `
            <div class="sparxstaruserenv-banner-content">
                <strong>Connection Offline:</strong> You are currently not connected to the internet.
            </div>`;
        document.body.appendChild(banner);
    }

    function hideOfflineBanner() {
        const banner = document.getElementById('sparxstaruserenv-offline-banner');
        if (banner) {
            Logger.info('Hiding offline notification banner.');
            banner.remove();
        }
    }


    // ---------- Feature + Privacy ----------
    function collectFeatures() {
        const safe = (fn, fallback=false) => { try { return fn(); } catch { return fallback; } };
        return {
            webrtc: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
            webgl: safe(() => {
                const c = document.createElement('canvas');
                return !!(window.WebGLRenderingContext && (c.getContext('webgl') || c.getContext('experimental-webgl')));
            }),
            serviceWorker: 'serviceWorker' in navigator,
            localStorage: 'localStorage' in window, sessionStorage: 'sessionStorage' in window,
            mediaRecorder: 'MediaRecorder' in window, getUserMedia: !!(navigator.mediaDevices?.getUserMedia),
            promise: 'Promise' in window, fetch: 'fetch' in window,
            indexedDB: 'indexedDB' in window, webWorkers: 'Worker' in window,
            pushManager: 'PushManager' in window, notification: 'Notification' in window,
            geolocation: 'geolocation' in navigator, clipboard: 'clipboard' in navigator,
            wakeLock: 'wakeLock' in navigator, bluetooth: 'bluetooth' in navigator, usb: 'usb' in navigator,
            webAssembly: 'WebAssembly' in window,
            intersectionObserver: 'IntersectionObserver' in window, mutationObserver: 'MutationObserver' in window,
            resizeObserver: 'ResizeObserver' in window
        };
    }
    function collectPrivacy() {
        return {
            doNotTrack: (navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.msDoNotTrack === '1'),
            gpc: navigator.globalPrivacyControl || false
        };
    }

    // ---------- Env snapshot (now reads from SPARXSTAR.State) ----------
    function collectEnvData() {
        const State = window.SPARXSTAR.State;
        // Assume you have SPARXSTAR.SparxstarUserEnv.getVisitorId() returning the FingerprintJS visitor ID
        const visitorId = window.SPARXSTAR.SparxstarUserEnv.getVisitorId ? window.SPARXSTAR.SparxstarUserEnv.getVisitorId() : null;

        return {
            identifiers: { // Matches 'identifiers.visitor_id' and 'identifiers.session_id'
                visitor_id: visitorId,
                session_id: State.sessionId, // This should come from your session ID logic
            },
            client_side_data: { // Matches 'client_side_data.device.type' etc.
                userAgent: State.userAgent,
                // These nested structures are crucial for get_value_from_array
                os: { name: State.device.os_name }, // Assuming State.device provides an os_name
                client: { name: State.device.browser_name }, // Assuming State.device provides a browser_name
                language: navigator.language || navigator.userLanguage,
                screen: {
                    width: window.screen.width, height: window.screen.height,
                    availWidth: window.screen.availWidth, availHeight: window.screen.availHeight,
                    pixelDepth: window.screen.pixelDepth, devicePixelRatio: window.devicePixelRatio || 1
                },
                device: { // This structure should match what server-side expects for 'device.type'
                    full: State.device.full, // The full detector data if needed
                    type: State.device.type, // e.g., 'desktop', 'mobile'
                    // ... other device properties (like os_name, browser_name if not duplicated)
                },
                network: { // This structure should match what server-side expects for 'network.effectiveType'
                    full: State.network.full, // Full network data
                    effectiveType: State.network.effectiveType, // e.g., '4g', '3g'
                    online: State.network.online,
                    rtt: State.network.rtt,
                    downlink: State.network.downlink,
                    saveData: State.network.saveData,
                },
                features: collectFeatures(),
                privacy: collectPrivacy(),
                compatible: isBrowserCompatible()
            },
            // The server will add 'server_side_data'
        };
    }

    // ---------- Diagnostics (kept from your flow; parallelized queries) ----------
    async function collectDiagnostics() {
        let data = collectEnvData();
        const [storage, mic, battery] = await Promise.allSettled([
            navigator.storage?.estimate?.(),
            navigator.permissions?.query?.({ name: 'microphone' }),
            navigator.getBattery?.(),
        ]);
        if (storage.status === 'fulfilled' && storage.value) data.storage = { quota: storage.value.quota, usage: storage.value.usage };
        if (mic.status === 'fulfilled' && mic.value) data.micPermission = mic.value.state;
        if (battery.status === 'fulfilled' && battery.value) data.battery = { level: battery.value.level, charging: battery.value.charging };

        // The previous logic for doNotTrack / gpc was potentially removing too much data.
        // It's commented out for now to ensure all data is sent to the server for processing,
        // as privacy handling should primarily be server-side based on the full snapshot.
        /*
        if (data.client_side_data.privacy.doNotTrack || data.client_side_data.privacy.gpc) {
            data = {
                identifiers: data.identifiers,
                client_side_data: {
                    privacy: data.client_side_data.privacy,
                    userAgent: data.client_side_data.userAgent,
                    compatible: data.client_side_data.compatible,
                    features: data.client_side_data.features,
                    device: data.client_side_data.device,
                    network: data.client_side_data.network
                }
            };
        }
        */
        return data;
    }

    // ---------- Sanitize + Send ----------
    function sanitizeData(data) {
        const sanitized = { ...data };
        // Deep clone client_side_data to avoid modifying the original
        if (sanitized.client_side_data) {
            sanitized.client_side_data = { ...sanitized.client_side_data };
        }

        // Apply sanitization to client_side_data where appropriate
        if (sanitized.client_side_data) {
            // Remove sensitive fields that should not be stored directly if they were accidentally included
            ['inputs','formData','cookies','localStorage'].forEach(f => {
                if (sanitized.client_side_data[f]) delete sanitized.client_side_data[f];
            });

            // Sanitize userAgent if present in client_side_data
            if (sanitized.client_side_data.userAgent) {
                sanitized.client_side_data.userAgent = sanitized.client_side_data.userAgent.replace(/\b\d{2,}\.\d+\.\d+\.\d+\b/g, 'x.x.x.x');
            }

            // Sanitize geo-coordinates if present (though these should come from server-side GeoIP)
            if (sanitized.client_side_data.latitude && sanitized.client_side_data.longitude) {
                sanitized.client_side_data.latitude  = Math.round(sanitized.client_side_data.latitude  * 100) / 100;
                sanitized.client_side_data.longitude = Math.round(sanitized.client_side_data.longitude * 100) / 100;
            }

            // Filter network data to only keep specific keys
            if (sanitized.client_side_data.network && typeof sanitized.client_side_data.network === 'object') {
                const keep = ['online','type','effectiveType','rtt','downlink','downlinkMax','saveData'];
                sanitized.client_side_data.network = Object.fromEntries(
                    Object.entries(sanitized.client_side_data.network).filter(([k]) => keep.includes(k))
                );
            }
        }
        return sanitized;
    }

    async function sendDiagnostics(payload) {
        const lastSend = LS.get('lastSendTime');
        if (lastSend && (Date.now() - parseInt(lastSend, 10) < 5000)) return; // 5s rate limit
        const body = sanitizeData(payload);
        try {
            // Use log_rest_url which is correctly defined in the outer IIFE scope
            const rsp = await fetch(log_rest_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                    'Accept-CH': 'Sec-CH-UA, Sec-CH-UA-Mobile, Sec-CH-UA-Platform, Sec-CH-UA-Model, Sec-CH-UA-Full-Version'
                },
                body: JSON.stringify(body)
            });
            if (!rsp.ok) throw new Error(`HTTP ${rsp.status} - ${rsp.statusText}`);
            const json = await rsp.json();
            Logger.info('Diagnostics sent', { status: json.status, action: json.action || 'updated', id: json.id || 'N/A' });
            LS.set('lastSendTime', Date.now().toString());
        } catch (e) {
            Logger.error('Diagnostics send failed', { error: e.message });
        }
    }

    async function runDiagnosticsOncePerDay() {
        const oneDay = 24 * 60 * 60 * 1000;
        const last = LS.get('lastCheck');
        if (last && (Date.now() - parseInt(last, 10) < oneDay)) return;
        const data = await collectDiagnostics();
        await sendDiagnostics(data);
        LS.set('lastCheck', Date.now().toString());
    }

    function initializeConsentListener() {
        if (!window.wp_consent_api) return;
        document.addEventListener('wp_listen_for_consent_change', async (event) => { // Added async here
            const { consent_changed, new_consent } = event.detail || {};
            if (consent_changed && new_consent && new_consent.includes('statistics')) {
                await runDiagnosticsOncePerDay(); // Added await here
            }
        });
    }

    // ---------- Boot ----------
    document.addEventListener('DOMContentLoaded', async () => { // MARKED ASYNC
        // Check for essential localized data. log_rest_url is already checked above.
        // We ensure `rest_urls` object exists and `i18n` exists.
        if (!rest_urls || !i18n) {
            Logger.error('Missing critical localization data for DOMContentLoaded', { rest_urls: !!rest_urls, i18n: !!i18n });
            return;
        }

        // Hydrate central state (requires DeviceDetector & NetworkMonitor loaded)
        if (window.SPARXSTAR?.initializeState) {
            window.SPARXSTAR.initializeState();
        } else {
            Logger.error('Global state initializer missing. Load global.js earlier.');
            return;
        }

        Logger.info('SparxstarUserEnv initialized', { sessionId: window.SPARXSTAR.State.sessionId });
        displayUpgradeBanner();
        window.addEventListener('offline', displayOfflineBanner);
        window.addEventListener('online', hideOfflineBanner);

        // Immediate lightweight snapshot each page load
        await sendDiagnostics(collectEnvData()); // AWAIT THE CALL

        // Detailed daily set
        await runDiagnosticsOncePerDay(); // AWAIT THE CALL
        initializeConsentListener();
    });

    // Expose for other modules
    window.SPARXSTAR = window.SPARXSTAR || {};
    window.SPARXSTAR.Logger = Logger;
    window.SPARXSTAR.SparxstarUserEnv = {
        collectEnvData,
        collectDiagnostics,
        sendDiagnostics,
        isBrowserCompatible,
        getSessionId: () => sessionId,
        getVisitorId: () => window.SPARXSTAR.State.visitorId // Assuming State.visitorId holds the FPJS ID
    };
})();
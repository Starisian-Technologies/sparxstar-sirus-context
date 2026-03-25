/**
 * sirus-bootstrap.js
 *
 * Sirus Observability — client-side bootstrap.
 *
 * Captures:
 *   - window.onerror       → js_error
 *   - unhandledrejection   → js_error
 *   - fetch() failures     → api_error
 *   - XHR failures         → api_error
 *
 * All captured events are sent to POST /wp-json/sirus/v1/event.
 *
 * Depends on window.SirusContext being set by the PHP enqueue layer:
 *   window.SirusContext = {
 *     device_id:     string,
 *     session_id:    string,
 *     rest_url:      string,   // base REST URL (e.g. /wp-json)
 *     nonce:         string,   // wp_rest nonce
 *   };
 */

(function () {
    'use strict';

    // ─── Guard: only initialise when the context object is available. ──────────
    if (
        typeof window.SirusContext === 'undefined' ||
        !window.SirusContext.device_id ||
        !window.SirusContext.session_id
    ) {
        return;
    }

    var ctx   = window.SirusContext;
    var baseRestUrl = ctx.rest_url || '/wp-json';

    if (baseRestUrl.charAt(baseRestUrl.length - 1) === '/') {
        baseRestUrl = baseRestUrl.slice(0, -1);
    }

    var ENDPOINT = baseRestUrl + '/sirus/v1/event';
    // For sendBeacon (which cannot set custom headers) embed the nonce as a query param.
    var SIGNED_ENDPOINT = ENDPOINT + '?_wpnonce=' + encodeURIComponent(ctx.nonce || '');
    // Capture fetch before any interceptor override so directive fetches never self-log.
    var _directiveFetch = window.fetch;

    // ─── Session ID: use provided session_id from context. ────────────────────
    var DEVICE_ID  = ctx.device_id;
    var SESSION_ID = ctx.session_id;

    // ─── Circuit breaker: hard cap on events per page load. ───────────────────
    // Prevents runaway JS error loops (e.g. render loops) from DDoS-ing the DB.
    var MAX_EVENTS_PER_PAGE_LOAD = 50;
    var _eventCount = 0;

    // ─── Context builder: pulls available environment signals. ────────────────
    function buildContext() {
        var nav = window.navigator || {};
        var conn = nav.connection || nav.mozConnection || nav.webkitConnection || {};
        return {
            browser:     detectBrowser(),
            os:          detectOS(),
            device_type: detectDeviceType(),
            network:     conn.effectiveType || 'unknown',
            user_agent:  nav.userAgent || '',
        };
    }

    function detectBrowser() {
        var ua = navigator.userAgent || '';
        if (ua.indexOf('Firefox') > -1)  return 'Firefox';
        if (ua.indexOf('Edg/') > -1)     return 'Edge';
        if (ua.indexOf('OPR') > -1)      return 'Opera';
        if (ua.indexOf('Chrome') > -1)   return 'Chrome';
        if (ua.indexOf('Safari') > -1)   return 'Safari';
        return 'Unknown';
    }

    function detectOS() {
        var ua = navigator.userAgent || '';
        if (/iPad|iPhone|iPod/.test(ua)) return 'iOS';
        if (/Android/.test(ua))          return 'Android';
        if (/Windows/.test(ua))          return 'Windows';
        if (/Mac OS/.test(ua))           return 'macOS';
        if (/Linux/.test(ua))            return 'Linux';
        return 'Unknown';
    }

    function detectDeviceType() {
        var ua = navigator.userAgent || '';
        if (/iPad|Android.*Tablet|Tablet/.test(ua)) return 'tablet';
        if (/Mobi|Android|iPhone|iPod/.test(ua))    return 'mobile';
        return 'desktop';
    }

    // ─── Core send function. ──────────────────────────────────────────────────
    function sendToSirus(payload) {
        // Circuit breaker: drop events once the per-page cap is reached.
        if (_eventCount >= MAX_EVENTS_PER_PAGE_LOAD) {
            return;
        }
        _eventCount++;

        // Attach required fields to every payload.
        payload.device_id  = DEVICE_ID;
        payload.session_id = SESSION_ID;
        payload.context    = payload.context || buildContext();

        try {
            fetch(ENDPOINT, {
                method:      'POST',
                headers:     {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   ctx.nonce || '',
                },
                body:        JSON.stringify(payload),
                credentials: 'same-origin',
            }).catch(function () {
                // Swallow fetch errors silently to avoid infinite loops.
            });
        } catch (e) {
            // Swallow synchronous errors silently.
        }
    }

    // ─── 1. JS errors (window.onerror). ──────────────────────────────────────
    var _prevOnError = window.onerror;

    window.onerror = function (message, source, lineno, colno, error) {
        sendToSirus({
            event_type: 'js_error',
            timestamp:  Math.floor(Date.now() / 1000),
            url:        window.location.pathname,
            error:      {
                message: String(message || ''),
                source:  String(source  || ''),
                line:    lineno  || 0,
                column:  colno   || 0,
                stack:   (error && error.stack) ? String(error.stack) : null,
            },
        });

        if (typeof _prevOnError === 'function') {
            return _prevOnError.apply(this, arguments);
        }
        return false;
    };

    // ─── 2. Unhandled promise rejections. ────────────────────────────────────
    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason;
        var message = '';
        var stack   = null;

        if (reason instanceof Error) {
            message = reason.message;
            stack   = reason.stack || null;
        } else if (typeof reason === 'string') {
            message = reason;
        } else {
            try {
                message = JSON.stringify(reason);
            } catch (e) {
                message = 'Unhandled rejection';
            }
        }

        sendToSirus({
            event_type: 'js_error',
            timestamp:  Math.floor(Date.now() / 1000),
            url:        window.location.pathname,
            error:      {
                message: message,
                source:  'unhandledrejection',
                line:    0,
                stack:   stack,
            },
        });
    });

    // ─── 3. fetch() failure interception. ────────────────────────────────────
    if (typeof window.fetch === 'function') {
        var _nativeFetch = window.fetch.bind(window);

        window.fetch = function (input, init) {
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            var startMs = Date.now();

            return _nativeFetch(input, init).then(
                function (response) {
                    if (!response.ok) {
                        // Never log errors about our own telemetry endpoint to prevent
                        // infinite feedback loops (e.g. expired nonce → 403 → log → 403 …).
                        if (url.indexOf(ENDPOINT) !== -1) {
                            return response;
                        }
                        sendToSirus({
                            event_type: 'api_error',
                            timestamp:  Math.floor(Date.now() / 1000),
                            url:        window.location.pathname,
                            metrics:    {
                                latency_ms:    Date.now() - startMs,
                                http_status:   response.status,
                            },
                            error:      {
                                message: 'HTTP ' + response.status + ' from ' + url,
                                source:  url,
                                line:    0,
                                stack:   null,
                            },
                        });
                    }
                    return response;
                },
                function (networkError) {
                    // Never log network errors about our own telemetry endpoint.
                    if (url.indexOf(ENDPOINT) !== -1) {
                        return Promise.reject(networkError);
                    }
                    sendToSirus({
                        event_type: 'network_issue',
                        timestamp:  Math.floor(Date.now() / 1000),
                        url:        window.location.pathname,
                        metrics:    {
                            latency_ms: Date.now() - startMs,
                        },
                        error:      {
                            message: String((networkError && networkError.message) || 'Network error'),
                            source:  url,
                            line:    0,
                            stack:   (networkError && networkError.stack) ? String(networkError.stack) : null,
                        },
                    });
                    return Promise.reject(networkError);
                }
            );
        };
    }

    // ─── 4. XHR failure interception. ────────────────────────────────────────
    if (typeof window.XMLHttpRequest === 'function') {
        var _nativeOpen  = XMLHttpRequest.prototype.open;
        var _nativeSend  = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url) {
            this._sirusUrl     = String(url || '');
            this._sirusMethod  = String(method || '');
            this._sirusStartMs = Date.now();
            return _nativeOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function () {
            var xhr = this;

            var _prevOnReadyStateChange = xhr.onreadystatechange;

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status === 0 || xhr.status >= 400) {
                        // Never log errors about our own telemetry endpoint.
                        if ((xhr._sirusUrl || '').indexOf(ENDPOINT) === -1) {
                            sendToSirus({
                                event_type: xhr.status === 0 ? 'network_issue' : 'api_error',
                                timestamp:  Math.floor(Date.now() / 1000),
                                url:        window.location.pathname,
                                metrics:    {
                                    latency_ms:  Date.now() - (xhr._sirusStartMs || 0),
                                    http_status: xhr.status,
                                },
                                error:      {
                                    message: 'XHR ' + (xhr.status || 0) + ' from ' + (xhr._sirusUrl || ''),
                                    source:  xhr._sirusUrl || '',
                                    line:    0,
                                    stack:   null,
                                },
                            });
                        }
                    }
                }

                if (typeof _prevOnReadyStateChange === 'function') {
                    _prevOnReadyStateChange.apply(this, arguments);
                }
            };

            return _nativeSend.apply(this, arguments);
        };
    }

    // ─── 5. Session start event. ─────────────────────────────────────────────
    sendToSirus({
        event_type: 'session_start',
        timestamp:  Math.floor(Date.now() / 1000),
        url:        window.location.pathname,
    });

    // ─── 5a. Page ready event (DOM fully interactive). ────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        sendToSirus({
            event_type: 'page_ready',
            timestamp:  Math.floor(Date.now() / 1000),
            device_id:  DEVICE_ID,
            session_id: SESSION_ID,
            context:    buildContext(),
        });
    });

    // ─── 6. Session end on page unload. ──────────────────────────────────────
    function sendSessionEndEvent() {
        var payload = {
            event_type: 'session_end',
            timestamp:  Math.floor(Date.now() / 1000),
            url:        window.location.pathname,
        };

        var data;
        try {
            data = JSON.stringify(payload);
        } catch (e) {
            // If serialization fails, abort sending to avoid throwing during unload.
            return;
        }

        // Prefer navigator.sendBeacon for reliable unload telemetry.
        // sendBeacon cannot set custom headers, so the nonce is embedded as ?_wpnonce= in the URL.
        if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
            try {
                var blob = new Blob([data], { type: 'application/json' });
                navigator.sendBeacon(SIGNED_ENDPOINT, blob);
                return;
            } catch (e) {
                // Fallback to fetch keepalive below.
            }
        }

        // Fallback: use fetch with keepalive to reduce cancellation on unload.
        if (typeof window.fetch === 'function') {
            try {
                window.fetch(ENDPOINT, {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce':   ctx.nonce || '',
                    },
                    body:      data,
                    keepalive: true
                });
            } catch (e) {
                // Swallow errors during unload.
            }
        }
    }

    window.addEventListener('beforeunload', function () {
        sendSessionEndEvent();
    });

    // ─── 7. Fetch active directive and apply mode. ────────────────────────────
    (function fetchDirective() {
        if (!DEVICE_ID) return;
        var directiveUrl = ENDPOINT.replace('/event', '/directives')
            + '?device_id=' + encodeURIComponent(DEVICE_ID)
            + '&session_id=' + encodeURIComponent(SESSION_ID);

        // Use the pre-interceptor fetch reference to avoid self-logging.
        var fetchFn = (typeof _directiveFetch === 'function') ? _directiveFetch : window.fetch;
        if (typeof fetchFn !== 'function') return;

        fetchFn(directiveUrl, {
            method: 'GET',
            credentials: 'same-origin',
        }).then(function (res) {
            return res.ok ? res.json() : null;
        }).then(function (directive) {
            if (!directive || typeof directive.mode === 'undefined') return;

            window.SIRUS = window.SIRUS || {};
            window.SIRUS.directive = directive;

            // Remove any previously applied Sirus mode classes before applying the new one.
            document.documentElement.classList.remove('sirus-lite', 'sirus-degraded');

            // Locked 3-mode contract — server is the sole decision maker.
            switch (directive.mode) {
                case 'lite':
                    document.documentElement.classList.add('sirus-lite');
                    disableHeavyScripts();
                    break;

                case 'degraded':
                    document.documentElement.classList.add('sirus-degraded');
                    enableSafeMode();
                    break;

                case 'normal':
                default:
                    // No adaptation — already cleaned up above.
                    break;
            }
        }).catch(function () {
            // Directive fetch failure is non-fatal; silently ignore.
        });

        function disableHeavyScripts() {
            // Signals to cooperating modules to reduce resource usage.
            document.documentElement.classList.add('sirus-no-animations');
            document.documentElement.classList.add('sirus-reduce-polling');
        }

        function enableSafeMode() {
            // Full degraded mode — disable all non-essential UI enhancements.
            document.documentElement.classList.add('sirus-no-animations');
            document.documentElement.classList.add('sirus-no-waveform');
            document.documentElement.classList.add('sirus-reduce-polling');
        }
    }());

    // ─── Expose public API for direct use. ───────────────────────────────────
    window.SIRUS = window.SIRUS || {};
    window.SIRUS.deviceId  = DEVICE_ID;
    window.SIRUS.sessionId = SESSION_ID;
    window.SIRUS.context   = buildContext();
    window.SIRUS.send      = sendToSirus;

}());

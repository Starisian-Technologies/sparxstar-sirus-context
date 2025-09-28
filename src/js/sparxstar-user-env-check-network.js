/**
 * @file SPARXSTAR Network Monitor.
 * @description Monitors network status, updates SPARXSTAR.State, and (optionally) syncs deltas to server.
 */
(function() {
    'use strict';

    const Logger = window.SPARXSTAR?.Logger || console;
    let listenersReady = false;

    function updateNetworkInfo() {
        const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        const current = {
            online: navigator.onLine,
            type: conn?.type || 'unknown',
            effectiveType: conn?.effectiveType || 'unknown',
            rtt: conn?.rtt,
            downlink: conn?.downlink,
            downlinkMax: conn?.downlinkMax,
            saveData: conn?.saveData,
            supported: !!conn
        };

        // Reflect to central state
        if (window.SPARXSTAR?.State) {
            window.SPARXSTAR.State.network = {
                isOnline: current.online,
                effectiveType: current.effectiveType,
                type: current.type,
                full: current
            };
            Logger.debug('[SPARXSTAR] Network state updated.', window.SPARXSTAR.State.network);
        }

        // Send delta (small payload) if sync helper exists
        if (window.SPARXSTAR?.syncStateToServer) {
            window.SPARXSTAR.syncStateToServer({ network: current });
        }

        return current;
    }

    function initListeners() {
        if (listenersReady) return;

        window.addEventListener('online',  () => {
            Logger.info('Network: ONLINE');
            updateNetworkInfo();
            // --- ADD THIS LINE ---
            if (window.hideOfflineBanner) window.hideOfflineBanner();
        });
        window.addEventListener('offline', () => {
            Logger.warn('Network: OFFLINE');
            updateNetworkInfo();
            // --- ADD THIS LINE ---
            if (window.displayOfflineBanner) window.displayOfflineBanner();
        });

        const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (conn && conn.addEventListener) {
            conn.addEventListener('change', () => {
                Logger.info('Network properties changed.');
                updateNetworkInfo();
            });
        } else {
            Logger.debug('navigator.connection change event not supported.');
        }

        listenersReady = true;
    }

    // Public API
    window.SPARXSTAR = window.SPARXSTAR || {};
    window.SPARXSTAR.NetworkMonitor = {
        getNetworkInfo: () => updateNetworkInfo(),
        isOnline:       () => navigator.onLine
    };

    // Initialize immediately to seed state and attach listeners
    updateNetworkInfo();
    initListeners();
    Logger.info('SPARXSTAR NetworkMonitor loaded.');
})();

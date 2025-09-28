/**
 * @file SPARXSTAR Device Detector (device-detector-js wrapper).
 */
(function() {
    'use strict';

    const Logger = window.SPARXSTAR?.Logger || console;

    if (typeof DeviceDetector === 'undefined') {
        Logger.error('device-detector-js not found. Ensure it is loaded before this file.');
        return;
    }

    let detector;
    function getInstance() {
        if (!detector) {
            try {
                detector = new DeviceDetector({ skipBotDetection: true, versionTruncation: 2 });
                Logger.debug('device-detector-js initialized.');
            } catch (e) {
                Logger.error('device-detector-js init failed', { error: e.message });
                detector = null;
            }
        }
        return detector;
    }

    function getDeviceInfo(userAgent = navigator.userAgent) {
        const d = getInstance();
        if (!d) return null;
        try {
            const parsed = d.parse(userAgent);
            Logger.debug('Device info parsed', { parsed });
            return parsed;
        } catch (e) {
            Logger.error('UA parse failed', { error: e.message });
            return null;
        }
    }

    window.SPARXSTAR = window.SPARXSTAR || {};
    window.SPARXSTAR.DeviceDetector = { getDeviceInfo };
})();

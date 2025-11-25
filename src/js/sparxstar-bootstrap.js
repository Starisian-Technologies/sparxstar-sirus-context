/**
 * @file sparxstar-bootstrap.js
 * @version 1.0.0
 * @description Bootstrap file that imports vendor dependencies and exposes them globally.
 * This is the Rollup entry point that bundles everything together.
 */

// Import vendor libraries from node_modules
import FingerprintJS from '@fingerprintjs/fingerprintjs';
import DeviceDetector from 'device-detector-js';

// Expose fingerprint globally
window.FingerprintJS = FingerprintJS;

// Initialize SPARXSTAR namespace
window.SPARXSTAR = window.SPARXSTAR || {};

// Wrap DeviceDetector with the getDeviceInfo() API expected by collectors
class SparxstarDeviceDetector {
    constructor() {
        this.detector = new DeviceDetector();
    }

    getDeviceInfo() {
        try {
            const ua = navigator.userAgent || '';
            return this.detector.parse(ua);
        } catch (e) {
            if (window.console && console.warn) {
                console.warn('[SPARXSTAR DeviceDetector] parse() failed', e && e.message ? e.message : e);
            }
            return null;
        }
    }
}

// Expose a single shared instance
window.SPARXSTAR.DeviceDetector = new SparxstarDeviceDetector();

// Now import all the IIFE modules
import './sparxstar-state.js';
import './sparxstar-collector.js';
import './sparxstar-profile.js';
import './sparxstar-sync.js';
import './sparxstar-recorder.js';
import './sparxstar-ui.js';
import './sparxstar-integrator.js';

/**
 * @file sparxstar-bootstrap.js
 * @version 1.0.0
 * @description Bootstrap file that imports vendor dependencies and exposes them globally.
 * This is the Rollup entry point that bundles everything together.
 */

// Import vendor libraries from node_modules
import FingerprintJS from '@fingerprintjs/fingerprintjs';
import DeviceDetector from 'device-detector-js';

// Expose vendor libraries globally for the IIFE modules
window.FingerprintJS = FingerprintJS;

// Initialize DeviceDetector and expose it
window.SPARXSTAR = window.SPARXSTAR || {};
window.SPARXSTAR.DeviceDetector = DeviceDetector;

// Now import all the IIFE modules (they will execute and attach to window.SPARXSTAR)
import './sparxstar-state.js';
import './sparxstar-collector.js';
import './sparxstar-profile.js';
import './sparxstar-sync.js';
import './sparxstar-ui.js';
import './sparxstar-integrator.js';

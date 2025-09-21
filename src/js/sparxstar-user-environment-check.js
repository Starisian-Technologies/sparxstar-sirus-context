/**
 * @file Main client-side script for SPARXSTAR User Environment Check.
 * @author Starisian Technologies (Max Barrett)
 * @version 2.2
 *
 * @description This script performs two primary functions:
 * 1. Checks for modern browser API compatibility and displays a dismissible banner if required APIs are missing.
 * 2. Collects and sends anonymized, consent-based diagnostic data once per day to a WordPress AJAX endpoint.
 */

(function() {
	'use strict';

	/**
	 * A namespaced wrapper for browser localStorage to prevent key collisions with other scripts.
	 * @const {object}
	 */
	const LS = {
		/**
		 * Retrieves an item from localStorage under the plugin's namespace.
		 * @param {string} k - The key for the item.
		 * @returns {string|null} The value of the item, or null if not found.
		 */
		get: (k) => localStorage.getItem(`envcheck:${k}`),

		/**
		 * Sets an item in localStorage under the plugin's namespace.
		 * @param {string} k - The key for the item.
		 * @param {string} v - The value to store.
		 */
		set: (k, v) => localStorage.setItem(`envcheck:${k}`, v),
	};

	/**
	 * Data passed from WordPress via `wp_localize_script`.
	 * @const {object}
	 * @property {string} nonce - The security nonce for the AJAX request.
	 * @property {string} ajax_url - The URL for WordPress's admin-ajax.php.
	 * @property {object} i18n - An object containing translated strings.
	 */
	const { nonce, ajax_url, i18n } = window.envCheckData || {};

	/**
	 * Checks for the presence of essential browser APIs.
	 *
	 * @returns {boolean} Returns `true` if all required APIs are present, otherwise `false`.
	 */
	function isBrowserCompatible() {
		return (
			'Promise' in window &&
			'fetch' in window &&
			'MediaRecorder' in window &&
			(navigator.mediaDevices && 'getUserMedia' in navigator.mediaDevices)
		);
	}

	/**
	 * Creates and displays the browser upgrade banner if the browser is incompatible.
	 */
	function displayUpgradeBanner() {
		// Banner guard: Do not show the banner if the user has previously dismissed it.
		if (LS.get('bannerDismissed') === 'true') {
			return;
		}

		if (isBrowserCompatible()) {
			return;
		}

		const banner = document.createElement('div');
		banner.className = 'envcheck-banner';
		banner.innerHTML = `
            <div class="envcheck-banner-content">
                <strong>${i18n.notice}</strong> ${i18n.update_message}
                <a href="https://browsehappy.com/" target="_blank" rel="noopener noreferrer">${i18n.update_link}</a>.
            </div>
            <button class="envcheck-banner-dismiss" aria-label="${i18n.dismiss}">&times;</button>
        `;

		document.body.appendChild(banner);

		// Add event listener to the dismiss button.
		banner.querySelector('.envcheck-banner-dismiss').addEventListener('click', () => {
			banner.remove();
			LS.set('bannerDismissed', 'true');
		});
	}

	/**
	 * Asynchronously collects a wide range of browser and platform diagnostics.
	 *
	 * @returns {Promise<object>} A promise that resolves with the diagnostic data object.
	 */
	async function collectDiagnostics() {
		let data = {
			privacy: {
				doNotTrack: navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.msDoNotTrack === '1',
				gpc: !!navigator.globalPrivacyControl,
			},
			userAgent: navigator.userAgent || 'N/A',
			os: navigator.platform || 'N/A',
			language: navigator.language || 'N/A',
			screen: {
				width: window.screen.width,
				height: window.screen.height,
				colorDepth: window.screen.colorDepth,
			},
			compatible: isBrowserCompatible(),
		};

		// Parallelize expensive or slow API queries for performance.
		const [storage, mic, battery] = await Promise.allSettled([
			navigator.storage?.estimate?.(),
			navigator.permissions?.query?.({ name: 'microphone' }),
			navigator.getBattery?.(),
		]);

		// Safely process the results of the parallel queries.
		if (storage.status === 'fulfilled' && storage.value) {
			data.storage = { quota: storage.value.quota, usage: storage.value.usage };
		}
		if (mic.status === 'fulfilled' && mic.value) {
			data.micPermission = mic.value.state;
		}
		if (battery.status === 'fulfilled' && battery.value) {
			data.battery = { level: battery.value.level, charging: battery.value.charging };
		}

		// Client-side data minimization: If DNT or GPC signals are present,
		// strip the payload down to the bare essentials before sending.
		if (data.privacy.doNotTrack || data.privacy.gpc) {
			data = {
				privacy: data.privacy,
				userAgent: data.userAgent,
				os: data.os,
				compatible: data.compatible,
			};
		}

		return data;
	}

	/**
	 * Sends the collected diagnostic data to the server.
	 *
	 * @param {object} diagnosticData - The object containing the data to log.
	 */
	async function sendDiagnostics(diagnosticData) {
		const formData = new FormData();
		formData.append('action', 'envcheck_log');
		formData.append('nonce', nonce);
		formData.append('data', JSON.stringify(diagnosticData));

		try {
			await fetch(ajax_url, {
				method: 'POST',
				body: formData,
			});
		} catch (error) {
			console.error('SPARXSTAR EnvCheck: Failed to send diagnostics.', error);
		}
	}

	/**
	 * The main execution function for logging.
	 * Checks if a log has been sent today and proceeds if not.
	 */
	async function runDiagnosticsOncePerDay() {
		const oneDay = 24 * 60 * 60 * 1000;
		const lastCheck = LS.get('lastCheck');

		// Check if we have already logged in the last 24 hours.
		if (lastCheck && (Date.now() - parseInt(lastCheck, 10) < oneDay)) {
			return;
		}

		// Collect and send the data, then update the timestamp.
		const data = await collectDiagnostics();
		await sendDiagnostics(data);
		LS.set('lastCheck', Date.now().toString());
	}

	/**
	 * Sets up a listener for the WordPress Consent API.
	 * If consent for 'statistics' is granted after the page loads, it triggers the diagnostic check.
	 */
	function initializeConsentListener() {
		// Feature detection: Only run if a WP Consent API provider is active.
		if (window.wp_consent_api) {
			document.addEventListener('wp_listen_for_consent_change', (event) => {
				const { consent_changed, new_consent } = event.detail;
				// If consent was just granted for the 'statistics' category, run the check.
				if (consent_changed && new_consent && new_consent.includes('statistics')) {
					runDiagnosticsOncePerDay();
				}
			});
		}
	}

	// Utility function to get current timestamp
const getCurrentTimestamp = () => new Date().toISOString();

// Gather user information
const gatherDeviceInfo = async () => {
    const deviceInfo = {
        userTimezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        userLanguage: navigator.language || navigator.userLanguage,
        userAgent: navigator.userAgent,
        screen: {
            width: window.screen.width,
            height: window.screen.height
        },
        viewport: {
            width: window.innerWidth,
            height: window.innerHeight
        },
        cpuCores: navigator.hardwareConcurrency, // Device CPU cores
        memory: navigator.deviceMemory || 'Unknown', // Device Memory
    };

    // Capture Audio Devices
    const audioDevices = await navigator.mediaDevices.enumerateDevices()
        .then((devices) => devices.filter(
            (device) => device.kind === 'audioinput' || device.kind === 'audiooutput'
        ));

    // Capture Network Information
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    const networkInfo = connection
        ? {
            effectiveType: connection.effectiveType,
            downlink: connection.downlink,
            rtt: connection.rtt,
            saveData: connection.saveData || false, // Data saver mode
            // Capture more network performance data like jitter and packet loss (where possible)
            roundTripTime: connection.rtt,
        }
        : null;

    // Capture Battery Status
    const batteryStatus = await navigator.getBattery().then((battery) => ({
        charging: battery.charging,
        level: battery.level * 100,
        chargingTime: battery.chargingTime,
        dischargingTime: battery.dischargingTime,
    }));

    // Web Speech Capabilities
    const webSpeechCapabilities = {
        textToSpeech: 'speechSynthesis' in window,
        speechRecognition: 'SpeechRecognition' in window || 'webkitSpeechRecognition' in window
    };

    // Combine all the data into a single object
    deviceInfo.audioDevices = audioDevices;
    deviceInfo.networkInfo = networkInfo;
    deviceInfo.batteryStatus = batteryStatus;
    deviceInfo.webSpeechCapabilities = webSpeechCapabilities;

    return JSON.stringify(deviceInfo); // Convert to JSON
};

const gatherUserInfo = async () => {
    const userInfo = {}; // Declare the object here

    // Get geolocation if available
    try {
        const position = await new Promise((resolve, reject) => 
            navigator.geolocation.getCurrentPosition(resolve, reject)
        );
        userInfo.latitude = position.coords.latitude;
        userInfo.longitude = position.coords.longitude;
    } catch (error) {
        console.warn('Geolocation not available:', error.message);
    }

    // Get device details
    const deviceDetails = await gatherDeviceInfo();
    userInfo.deviceDetails = deviceDetails;

    // Get connection information
    if (navigator.connection) {
        userInfo.connection = {
            type: navigator.connection.effectiveType,
            downlink: navigator.connection.downlink, // Mbps
            rtt: navigator.connection.rtt, // ms
            saveData: navigator.connection.saveData
        };
    }

    // Get audio capabilities
    userInfo.audio = {
        sampleRate: (new AudioContext()).sampleRate,
        channels: (new AudioContext()).destination.channelCount,
        audioDevices: []
    };

    // Get available audio devices
    if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            userInfo.audio.audioDevices = devices
                .filter(device => device.kind === 'audioinput' || device.kind === 'audiooutput')
                .map(device => ({
                    kind: device.kind,
                    label: device.label,
                    deviceId: device.deviceId
                }));
        } catch (error) {
            console.warn('Unable to enumerate audio devices:', error);
        }
    }

    return userInfo;
};

// Gather user activity data
const gatherUserActivity = (startTime) => {
    const activity = {
        referrer: document.referrer,
        submissionTime: getCurrentTimestamp(),
        timeSpent: Date.now() - startTime,
        inputs: {}
    };

    document.querySelectorAll('input, textarea, select').forEach((input) => {
        activity.inputs[input.name] = { value: input.value, isEmpty: !input.value };
    });

    return activity;
};

// Send data to server
const sendDataToServer = async (data) => {
    try {
        const response = await fetch('/your-endpoint', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        
        if (result.success) {
            console.log('Data sent successfully');
        } else {
            if (result.data && result.data.login_required) {
                showLoginModal(); // Assuming this function exists
            } else {
                alert(result.message || 'An error occurred');
            }
        }
    } catch (error) {
        console.error('Error sending data:', error);
    }
};

// Main function to handle form submission
const handleFormSubmission = async (event) => {
    event.preventDefault();
    const startTime = Date.now(); // Assuming this is set when the page loads

    const formData = new FormData(event.target);
    const userActivity = gatherUserActivity(startTime);
    const userInfo = await gatherUserInfo();

    const postData = {
        user: userInfo,
        activity: userActivity,
        fields: Object.fromEntries(formData)
    };

    await sendDataToServer(postData);
};

// Event listener for form submission
document.querySelector('form').addEventListener('submit', handleFormSubmission);

	/**
	 * Initialize the script once the DOM is fully loaded.
	 */
	document.addEventListener('DOMContentLoaded', () => {
		// Stop if essential data from WordPress is missing.
		if (!nonce || !ajax_url || !i18n) {
			console.error('SPARXSTAR EnvCheck: Missing localization data.');
			return;
		}

		displayUpgradeBanner();
		runDiagnosticsOncePerDay();
		initializeConsentListener();
	});

})();

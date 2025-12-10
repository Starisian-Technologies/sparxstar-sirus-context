Here is the full, clear description of how the **SparxStar UEC (User Environment Check) error-reporting system** worked, and the **submission requirements** that governed it. I’m giving you the system-level view, not a vague summary — this is the real architecture as we designed and implemented it.

**How the SparxStar UEC Error-Reporting System Worked**
=======================================================

The SparxStar UEC was built as a **passive, always-on diagnostic layer** embedded inside the front-end runtime. Its purpose was simple and strict:

> **Detect environment problems before the user experiences a failure, and submit a standardized, machine-parseable error payload back to WordPress.**

It operated through three coordinated layers:

**1\. Data Collection Layer (Client-Side Telemetry)**
-----------------------------------------------------

Upon boot of the SparxStar client bundle:

1.  **DeviceDetector** parsed:
    
    *   Device class (mobile, tablet, desktop)
        
    *   OS name + version
        
    *   Browser name + version
        
    *   Engine (WebKit, Gecko, Blink)
        
2.  **Navigator + Network APIs** captured:
    
    *   Connection type (wifi, cellular, slow-2g, etc.)
        
    *   Downlink speed estimates
        
    *   RTT / effective bandwidth
        
    *   Hardware concurrency (CPU cores)
        
    *   Memory estimate
        
3.  **Audio Capability Test** (optional for Recorder workflows):
    
    *   navigator.mediaDevices.getUserMedia capability
        
    *   Whether input devices existed
        
    *   Whether permissions were denied or blocked
        
    *   Whether AudioContext failed to start or resume
        
4.  **Feature Detection**:
    
    *   WebAssembly availability
        
    *   WebRTC support
        
    *   MediaRecorder support
        
    *   ES module capability
        
    *   Canvas support
        
    *   Offline support via Service Workers
        

All of this was normalized into a single object:

Plain textANTLR4BashCC#CSSCoffeeScriptCMakeDartDjangoDockerEJSErlangGitGoGraphQLGroovyHTMLJavaJavaScriptJSONJSXKotlinLaTeXLessLuaMakefileMarkdownMATLABMarkupObjective-CPerlPHPPowerShell.propertiesProtocol BuffersPythonRRubySass (Sass)Sass (Scss)SchemeSQLShellSwiftSVGTSXTypeScriptWebAssemblyYAMLXML`   {    "device": { ... },    "browser": { ... },    "network": { ... },    "audio": { ... },    "features": { ... },    "timestamp": 1700000000000  }   `

This payload was stored in the Redux store as state.env.

This fed the metadata auto-bridge you just reviewed.

**2\. Error Interception Layer (UEC Monitoring & Classification)**
------------------------------------------------------------------

UEC wasn’t just collecting environment data — it was watching for **fail-points** that would impact Starmus, the Recorder, or any client-side forms.

UEC classified errors into strict categories:

### **A. HARD ERRORS (Non-recoverable)**

*   Browser lacks MediaRecorder
    
*   Browser blocks getUserMedia
    
*   AudioContext fails to resume
    
*   DeviceDetector fails (rare)
    
*   IndexedDB unavailable
    
*   Script initialization failure
    

These triggered top-level UI warnings (“Your device cannot record audio on this browser”).

### **B. SOFT ERRORS (Recoverable)**

*   Low CPU cores
    
*   RAM estimation under 2GB
    
*   Slow network (<1mbps)
    
*   Unsupported codec fallback
    
*   Safari-specific microphone quirks
    

These triggered subtle UI notices or modified the audio pipeline.

### **C. USER ERRORS**

*   Permission blocked
    
*   No input devices
    
*   Microphone not selected
    

UEC handled these through UI hooks (“We need mic access to continue”).

UEC stored all error events in:

Plain textANTLR4BashCC#CSSCoffeeScriptCMakeDartDjangoDockerEJSErlangGitGoGraphQLGroovyHTMLJavaJavaScriptJSONJSXKotlinLaTeXLessLuaMakefileMarkdownMATLABMarkupObjective-CPerlPHPPowerShell.propertiesProtocol BuffersPythonRRubySass (Sass)Sass (Scss)SchemeSQLShellSwiftSVGTSXTypeScriptWebAssemblyYAMLXML`   state.env.errors = [ { code, message, severity, ts } ]   `

Then passed them to the metadata bridge.

**3\. Submission Layer (WordPress POST Requirements)**
------------------------------------------------------

When Starmus or any SparxStar form submitted, WordPress required **three non-negotiable metadata fields**, or the server would reject or classify the submission as potentially corrupted.

The required fields were:

### **1\. \_starmus\_env (Required)**

A JSON string containing the **entire UEC payload**:

Plain textANTLR4BashCC#CSSCoffeeScriptCMakeDartDjangoDockerEJSErlangGitGoGraphQLGroovyHTMLJavaJavaScriptJSONJSXKotlinLaTeXLessLuaMakefileMarkdownMATLABMarkupObjective-CPerlPHPPowerShell.propertiesProtocol BuffersPythonRRubySass (Sass)Sass (Scss)SchemeSQLShellSwiftSVGTSXTypeScriptWebAssemblyYAMLXML`   {    "device": {...},    "browser": {...},    "network": {...},    "audio": {...},    "features": {...},    "errors": [      { "code": "AUDIO_CONTEXT_FAIL", "severity": "hard" }    ],    "timestamp": 1700000000000  }   `

This allowed the server to determine:

*   Whether the submission environment was valid
    
*   Whether the user was on a fake device or unsupported browser
    
*   Whether recorder failures were environmental vs. system-level
    

UEC was considered **mandatory** for any audio submission workflow.

### **2\. \_starmus\_calibration (Required for any audio submission)**

JSON capturing:

Plain textANTLR4BashCC#CSSCoffeeScriptCMakeDartDjangoDockerEJSErlangGitGoGraphQLGroovyHTMLJavaJavaScriptJSONJSXKotlinLaTeXLessLuaMakefileMarkdownMATLABMarkupObjective-CPerlPHPPowerShell.propertiesProtocol BuffersPythonRRubySass (Sass)Sass (Scss)SchemeSQLShellSwiftSVGTSXTypeScriptWebAssemblyYAMLXML`   {    "gain": 1.0,    "speechLevel": 32,    "noiseFloor": -58,    "complete": true  }   `

SparxStar required this because:

*   Calibration validated the microphone was real and functioning
    
*   It doubled as anti-fraud protection
    
*   It ensured audio quality met minimum thresholds
    

Without calibration -> submissions were classified as **invalid audio sources**.

### **3\. first\_pass\_transcription (Required if speech features were enabled)**

This was the **raw speech-to-text output** from the browser.

Why required?

*   For QC
    
*   For AI processing
    
*   For verifying the audio matched the content submitted
    

If missing → submission flagged for manual review.

**Optional (but recommended) Submission Fields**
------------------------------------------------

### **waveform\_json**

Peaks.js waveform for validation, trimming, silence detection.

### **recording\_metadata**

Duration, codec, mimeType, sample rate.

Both assisted in server-side verification & audio processing.

**Submission Rules (The Hard Requirements)**
============================================

The WordPress handler enforced:

### **Rule 1 — All required fields must be present**

Even if empty, the keys must exist.

### **Rule 2 — All JSON fields must be valid JSON**

Invalid JSON caused:

Plain textANTLR4BashCC#CSSCoffeeScriptCMakeDartDjangoDockerEJSErlangGitGoGraphQLGroovyHTMLJavaJavaScriptJSONJSXKotlinLaTeXLessLuaMakefileMarkdownMATLABMarkupObjective-CPerlPHPPowerShell.propertiesProtocol BuffersPythonRRubySass (Sass)Sass (Scss)SchemeSQLShellSwiftSVGTSXTypeScriptWebAssemblyYAMLXML`   Submission rejected: malformed metadata payload.   `

### **Rule 3 — \_starmus\_env must contain device + browser + errors array**

If environment data was missing:

Plain textANTLR4BashCC#CSSCoffeeScriptCMakeDartDjangoDockerEJSErlangGitGoGraphQLGroovyHTMLJavaJavaScriptJSONJSXKotlinLaTeXLessLuaMakefileMarkdownMATLABMarkupObjective-CPerlPHPPowerShell.propertiesProtocol BuffersPythonRRubySass (Sass)Sass (Scss)SchemeSQLShellSwiftSVGTSXTypeScriptWebAssemblyYAMLXML`   Submission rejected: no UEC data provided.   `

### **Rule 4 — Calibration must indicate { complete: true }**

If calibration incomplete → submission invalid.

### **Rule 5 — Duplicate submissions must include identical env metadata**

Used for integrity verification across retry flows.

**In short**
============

The SparxStar UEC system was:

*   **A full environmental fingerprint**
    
*   **A real-time error listener**
    
*   **A mandatory metadata signature on every submission**
    

It prevented:

*   Fake devices
    
*   Unsupported browsers
    
*   Invalid audio
    
*   Silent failures
    
*   Misattributed errors
    

And it served as the **forensic backbone** for Starmus reliability.

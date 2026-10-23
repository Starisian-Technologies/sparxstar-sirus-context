const fs = require('fs');
const { execFileSync } = require('child_process');

/**
 * Post-build integrity check for assets/.
 *
 * assets/ holds three different kinds of file and the build only produces one
 * of them:
 *
 *   generated  - rebuilt every time by build:css and build:js
 *   guards     - index.php silence files, hand-written, never regenerated
 *   source     - sirus-bootstrap.js, 430 lines of hand-written client code
 *                that lives here rather than in src/ and has no build step
 *
 * `clean` used to be `rm -rf assets`, which deleted all three. The guards came
 * back only because they are committed, and sirus-bootstrap.js came back only
 * if the working tree was restored -- a build in a clean checkout destroyed it
 * outright. SirusPlugin::enqueue wraps that script in file_exists(), so the
 * loss is silent: error capture and session tracking simply stop.
 *
 * This runs after every build and fails it if anything that should have
 * survived did not.
 */

console.log('🔍 Verifying assets/ integrity after build...');

// Guards that must exist in every shipped directory under assets/.
const requiredGuards = [
    'assets/index.php',
    'assets/css/index.php',
    'assets/js/index.php',
];

// Files the build is expected to have produced.
const expectedBuildOutput = [
    'assets/css/sparxstar-user-environment-check.min.css',
    'assets/js/sirus-context.js',
];

let ok = true;

const fail = (message) => {
    console.log(`❌ ${message}`);
    ok = false;
};

for (const guard of requiredGuards) {
    if (fs.existsSync(guard)) {
        console.log(`✅ guard present: ${guard}`);
    } else {
        fail(`guard missing: ${guard} — the build deleted a directory-listing guard`);
    }
}

for (const artifact of expectedBuildOutput) {
    if (fs.existsSync(artifact)) {
        console.log(`✅ build output: ${artifact}`);
    } else {
        fail(`build output missing: ${artifact}`);
    }
}

/*
 * The checks above name files explicitly, so they cannot catch a tracked file
 * nobody remembered to list. Ask git what it is tracking under assets/ and
 * require all of it to still be on disk. In a tarball or an export there is no
 * git to ask; the explicit checks still ran, so skip rather than fail.
 */
let tracked = null;

try {
    tracked = execFileSync('git', ['ls-files', '--', 'assets/'], { encoding: 'utf8' })
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');
} catch (error) {
    console.log(`ℹ️  git unavailable (${error.code || 'error'}); skipping tracked-file sweep`);
}

if (tracked !== null) {
    if (tracked.length === 0) {
        fail('git tracks no files under assets/ — expected the guards at minimum');
    }

    for (const file of tracked) {
        if (!fs.existsSync(file)) {
            fail(`tracked file deleted by the build: ${file}`);
        }
    }

    if (ok) {
        console.log(`✅ all ${tracked.length} git-tracked files under assets/ survived the build`);
    }
}

if (!ok) {
    console.log('⚠️  assets/ integrity check failed.');
    process.exit(1);
}

console.log('🎉 assets/ integrity verified.');

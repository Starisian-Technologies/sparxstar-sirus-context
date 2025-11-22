#!/usr/bin/env node

/**
 * Auto-generate JS documentation for SPARXSTAR User Environment Check.
 * Scans /src/js and outputs Markdown docs into /docs/js.
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import jsdoc2md from 'jsdoc-to-markdown';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const SRC = path.resolve(__dirname, '../src/js');
const OUT = path.resolve(__dirname, '../docs/js');

if (!fs.existsSync(OUT)) {
    fs.mkdirSync(OUT, { recursive: true });
}

async function generate() {
    console.log(`🔍 Reading JavaScript source from ${SRC}`);

    const files = fs.readdirSync(SRC).filter(f => f.endsWith('.js') && !f.startsWith('.'));

    let count = 0;

    for (const file of files) {
        const filePath = path.join(SRC, file);
        const outFile  = path.join(OUT, file.replace('.js', '.md'));

        console.log(`✅ Generating for ${file} → ${path.basename(outFile)}`);

        try {
            const output = await jsdoc2md.render({
                files: filePath,
                'no-cache': true,
                'heading-depth': 2
            });

            // Add a header
            const header = `# ${file}\n\n**Source:** \`src/js/${file}\`\n\n`;
            
            fs.writeFileSync(outFile, header + (output || '_No documentation found_\n'));
            count++;
        } catch (err) {
            console.error(`❌ Error processing ${file}:`, err.message);
        }
    }

    console.log(`\n🎉 JavaScript documentation complete! (${count} files)`);
    console.log(`📁 Output directory: ${OUT}\n`);
}

generate().catch(err => {
    console.error('❌ [JS DOCS] Error:', err);
    process.exit(1);
});

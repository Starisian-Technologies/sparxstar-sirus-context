import resolve from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import terser from '@rollup/plugin-terser';

export default {
    input: 'src/js/sparxstar-bootstrap.js',

    output: {
        file: 'assets/js/sparxstar-user-environment-check-app.bundle.min.js',
        format: 'iife',
        name: 'SparxstarUserEnvironmentCheckApp',
        sourcemap: false
    },

    plugins: [
        json(),
        resolve({
            browser: true,
            preferBuiltins: false
        }),
        commonjs(),
        terser()
    ]
};

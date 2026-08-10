import resolve from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import terser from '@rollup/plugin-terser';

export default {
    input: 'src/js/sparxstar-bootstrap.js',

    output: {
        file: 'assets/js/sirus-context.js',
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
        terser({
            compress: {
                passes: 1,  // Reduce compression passes for faster builds
                pure_funcs: ['console.log', 'console.debug']
            },
            mangle: {
                safari10: true
            },
            format: {
                comments: false
            }
        })
    ]
};

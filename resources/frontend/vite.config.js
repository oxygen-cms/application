import { defineConfig } from 'vite'
const { createVuePlugin } = require('vite-plugin-vue2');

let commitHash = require('child_process')
    .execSync('git rev-parse --short HEAD')
    .toString().trim();
console.log('using commit hash: ' + commitHash);

let sentryRelease = 'oxygen-ui@' + commitHash;

// https://vitejs.dev/config/
export default defineConfig(( { command, mode }) => {
    return {
        plugins: [
            createVuePlugin()
        ],
        define: {
            SENTRY_RELEASE: JSON.stringify(sentryRelease)
        },
        base: command === 'serve' ? '/' : '/vendor/oxygen/',
        build: {
            outDir: '../../public/vendor/oxygen'
        },
        optimizeDeps: {
            include: [
                'ua-parser-js'
            ]
        },
        resolve: { alias: {
            // this is required for the SCSS modules
            '~@oxygen-cms/ui': '@oxygen-cms/ui',
            '~bulma': 'bulma',
            '~buefy': 'buefy'
        } }
    }
})

// @ts-check
const { defineConfig, devices } = require( '@playwright/test' );

/**
 * Config Playwright per gli E2E di DB Role UX.
 *
 * baseURL punta all'ambiente "development" di wp-env (porta 8888), quello su
 * cui opera `wp-env run cli` (bin/setup-e2e.sh vi crea ruoli, utenti e la
 * pagina dell'Area riservata).
 */
module.exports = defineConfig( {
	testDir: './tests/e2e',
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: process.env.CI ? [ [ 'list' ], [ 'html', { open: 'never' } ] ] : 'list',

	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},

	projects: [
		// Login di admin, docente e sottoscrittore (sessioni in tests/e2e/.auth/).
		{
			name: 'setup',
			testMatch: /.*\.setup\.js/,
		},
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
			dependencies: [ 'setup' ],
		},
	],
} );

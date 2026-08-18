const { defineConfig, devices } = require( '@playwright/test' );

// Port and base URL must match scripts/e2e-env.sh - the disposable
// WordPress instance that "up" provisions before this config's tests run.
const PORT = process.env.E2E_PORT || 8890;

module.exports = defineConfig( {
	testDir: './tests/e2e/specs',
	fullyParallel: false, // Tests share one WordPress instance and database.
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: process.env.CI ? [ [ 'html', { open: 'never' } ], [ 'github' ] ] : 'list',
	use: {
		baseURL: `http://127.0.0.1:${ PORT }`,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );

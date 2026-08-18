/** Logs the given page in as the disposable instance's admin user (created by scripts/e2e-env.sh). */
async function loginAsAdmin( page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

module.exports = { loginAsAdmin };

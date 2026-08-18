const fs = require( 'fs' );
const path = require( 'path' );
const { wpPath } = require( './wp-cli' );

/**
 * Reads every email captured so far by tests/e2e/fixtures/mu-plugins/e2e-mail-log.php
 * (which intercepts wp_mail() so no real email is ever sent).
 *
 * @return {Array<{to: string, subject: string, message: string, headers: *, time: number}>}
 */
function readMailLog() {
	const logFile = path.join( wpPath(), 'e2e-mail-log.json' );

	if ( ! fs.existsSync( logFile ) ) {
		return [];
	}

	return fs
		.readFileSync( logFile, 'utf8' )
		.split( '\n' )
		.filter( Boolean )
		.map( ( line ) => JSON.parse( line ) );
}

module.exports = { readMailLog };

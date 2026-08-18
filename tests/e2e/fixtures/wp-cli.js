const { execSync } = require( 'child_process' );
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );

const STATE_FILE = '/tmp/formtura-e2e-env.state';

/**
 * Reads the workspace path scripts/e2e-env.sh wrote when it provisioned the
 * disposable WordPress instance these tests run against.
 */
function wpPath() {
	const raw = fs.readFileSync( STATE_FILE, 'utf8' );
	const line = raw.split( '\n' ).find( ( l ) => l.startsWith( 'WORKSPACE=' ) );

	if ( ! line ) {
		throw new Error( `WORKSPACE not found in ${ STATE_FILE } - is the E2E environment up?` );
	}

	return line.slice( 'WORKSPACE='.length ).trim();
}

/** Runs a wp-cli command against the disposable instance and returns stdout. */
function wp( args ) {
	return execSync( `wp ${ args } --path="${ wpPath() }"`, { encoding: 'utf8' } ).trim();
}

/** Runs a PHP snippet inside the WordPress instance via `wp eval-file`. */
function wpEval( phpCode ) {
	const tmpFile = path.join( os.tmpdir(), `formtura-e2e-eval-${ Date.now() }-${ Math.random().toString( 36 ).slice( 2 ) }.php` );
	fs.writeFileSync( tmpFile, `<?php\n${ phpCode }` );

	try {
		return wp( `eval-file "${ tmpFile }"` );
	} finally {
		fs.unlinkSync( tmpFile );
	}
}

module.exports = { wp, wpEval, wpPath };

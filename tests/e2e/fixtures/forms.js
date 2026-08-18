const { wpEval } = require( './wp-cli' );

/**
 * Creates a form directly via fta_create_form(), bypassing the builder UI.
 * The builder itself (a React SPA) gets its own dedicated browser-driven
 * coverage; this helper is for tests whose subject is submission, entries,
 * or another downstream flow that merely needs a form to already exist.
 *
 * @param {object} data Passed straight through to fta_create_form().
 * @return {number} The new form's ID.
 */
function createForm( data ) {
	const json = JSON.stringify( data ).replace( /'/g, "\\'" );
	const output = wpEval( `
		$data = json_decode( '${ json }', true );
		echo fta_create_form( $data );
	` );

	const id = parseInt( output, 10 );

	if ( ! id ) {
		throw new Error( `createForm() failed - fta_create_form() returned: ${ output }` );
	}

	return id;
}

/**
 * Creates a WordPress page whose content is just the form's shortcode, and
 * returns its URL path. The disposable instance uses plain ("?p=")
 * permalinks (scripts/e2e-env.sh never runs `wp rewrite structure`), so the
 * path is built from the post ID rather than its slug.
 */
function createFormPage( formId, title = 'E2E Test Form' ) {
	const postId = wpEval( `
		echo wp_insert_post( array(
			'post_title'   => '${ title.replace( /'/g, "\\'" ) }',
			'post_content' => '[formtura id="${ formId }"]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );
	` );

	return `/?page_id=${ parseInt( postId, 10 ) }`;
}

function deleteForm( formId ) {
	wpEval( `fta_delete_form( ${ parseInt( formId, 10 ) } );` );
}

module.exports = { createForm, createFormPage, deleteForm };

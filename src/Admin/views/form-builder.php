<?php
/**
 * Form Builder View - React Version
 *
 * Admin page for building and editing forms with React.
 *
 * @package Formtura
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_id = isset( $form['id'] ) ? $form['id'] : 0;
?>


<div class="wrap formtura-builder-wrap">
	<div id="formtura-builder-root" data-form-id="<?php echo esc_attr( $form_id ); ?>"></div>
</div><!-- /.wrap -->

<script>
// Pass data to React app
window.formturaBuilder = {
	ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
	nonce: '<?php echo wp_create_nonce( 'formtura_admin' ); ?>',
	formId: '<?php echo esc_js( $form_id ); ?>',
	editUrl: '<?php echo esc_url( admin_url( 'admin.php?page=formtura-builder' ) ); ?>',
	formsUrl: '<?php echo esc_url( admin_url( 'admin.php?page=formtura' ) ); ?>',
	adminUrl: '<?php echo esc_url( admin_url() ); ?>'
};

</script>

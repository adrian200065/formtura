<?php
/**
 * Email Notification Template
 *
 * HTML template for email notifications.
 *
 * @package Formtura
 * @since 1.0.0
 *
 * @var string $message Email message content.
 * @var array  $form Form data.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php bloginfo( 'name' ); ?></title>
	<style>
		body {
			color: hsla(0, 0%, 20%, 1);
			background-color: hsla(0, 0%, 96%, 1);
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
			line-height: 1.6;
			padding: 0;
			margin: 0;
		}
		.email-container {
			background-color: hsla(0, 0%, 100%, 1);
			max-inline-size: 37.5rem;
			margin: 1.25rem auto;
			border-radius: .5rem;
			overflow: hidden;
			box-shadow: 0 2px 4px rgb(0 0 0 / .1);
		}
		.email-header {
			color: hsla(0, 0%, 100%, 1);
			background-color: hsla(240, 100%, 50%, 1);
			text-align: center;
			padding: 1.875rem 1.25rem;
		}
		.email-header h1 {
            font-size: 1.5rem;
			font-weight: 600;
			margin: 0;
		}
		.email-body {
			padding: 1.875rem 1.25rem;
		}
		.email-footer {
			color: hsla(0, 0%, 20%, 1);
			background-color: hsla(0, 0%, 96%, 1);
			font-size: .875rem;
			text-align: center;
			padding: 1.25rem;
			border-block-start: 1px solid hsla(0, 0%, 80%, 1);
		}
		.email-footer a {
			color: hsla(240, 100%, 50%, 1);
			text-decoration: none;
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1><?php bloginfo( 'name' ); ?></h1>
		</div><!-- /.email-header -->

		<div class="email-body">
			<?php echo wp_kses_post( wpautop( $message ) ); ?>
		</div><!-- /.email-body -->

		<div class="email-footer">
			<p>
				<?php
				printf(
					/* translators: %s: site name */
					esc_html__( 'This email was sent from %s', FORMTURA_TEXTDOMAIN ),
					'<a href="' . esc_url( home_url() ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a>'
				);
				?>
			</p>
		</div><!-- /.email-footer -->
	</div><!-- /.email-container -->
</body>
</html>

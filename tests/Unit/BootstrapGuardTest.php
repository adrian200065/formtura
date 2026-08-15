<?php
/**
 * Plugin bootstrap failure-mode tests.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit;

use Formtura\Tests\TestCase;

class BootstrapGuardTest extends TestCase {

	/**
	 * A source checkout without Composer dependencies must not register the
	 * runtime bootstrap that will later call an unavailable Core class.
	 */
	public function test_missing_autoloader_registers_notice_not_runtime_bootstrap() {
		$root = sys_get_temp_dir() . '/formtura-bootstrap-' . uniqid( '', true );
		mkdir( $root . '/src', 0777, true );
		copy( FORMTURA_PLUGIN_FILE, $root . '/formtura.php' );
		file_put_contents( $root . '/src/Functions.php', "<?php\n" );

		$runner = $root . '/runner.php';
		file_put_contents(
			$runner,
			<<<'PHP'
<?php
define( 'ABSPATH', __DIR__ . '/wordpress/' );
$GLOBALS['hooks'] = [];
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://example.test/formtura/'; }
function plugin_basename( $file ) { return 'formtura/formtura.php'; }
function add_action( $hook, $callback ) { $GLOBALS['hooks'][] = $hook; }
function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
include $argv[1];
echo json_encode( $GLOBALS['hooks'] );
PHP
		);

		$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $runner ) . ' ' . escapeshellarg( $root . '/formtura.php' ) );
		$hooks  = json_decode( $output, true );

		$this->removeTree( $root );

		$this->assertContains( 'admin_notices', $hooks );
		$this->assertNotContains( 'plugins_loaded', $hooks );
	}

	/**
	 * Remove a test-only directory tree.
	 *
	 * @param string $root Directory to remove.
	 */
	private function removeTree( $root ) {
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}

		rmdir( $root );
	}
}

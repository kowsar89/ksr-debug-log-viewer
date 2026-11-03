<?php
/**
 * Plugin Name: KSR Debug Log Viewer
 * Plugin URI: https://github.com/kowsar89/ksr-debug-log-viewer/
 * Description: View, edit and delete the debug.log file, and toggle WP_DEBUG, WP_DEBUG_LOG and WP_DEBUG_DISPLAY from the Debug Log link in the admin bar
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Author: Kowsar Hossain
 * Author URI: https://kowsarhossain.com
 * Text Domain: ksr-debug-log-viewer
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package KSR_Debug_Log_Viewer
 */

namespace kowsarhossain\ksrdlv;

defined( 'ABSPATH' ) || exit;

define( 'KSRDLV_VERSION', '1.0.0' );
define( 'KSRDLV_FILE', __FILE__ );
define( 'KSRDLV_PATH', plugin_dir_path( __FILE__ ) );
define( 'KSRDLV_URL', plugin_dir_url( __FILE__ ) );

// The plugin has no frontend, so nothing else is loaded outside wp-admin and admin-ajax.php.
if ( ! is_admin() ) {
	return;
}

/**
 * Loads a plugin class from inc/, where Admin_Page lives in admin-page.php.
 *
 * @param string $class_name Fully qualified class name.
 * @return void
 */
function autoload( string $class_name ) {
	$prefix = __NAMESPACE__ . '\\';
	if ( 0 !== strpos( $class_name, $prefix ) ) {
		return;
	}

	$file_name = str_replace( '_', '-', strtolower( substr( $class_name, strlen( $prefix ) ) ) );
	$path      = KSRDLV_PATH . 'inc/' . $file_name . '.php';

	if ( is_readable( $path ) ) {
		require $path;
	}
}
spl_autoload_register( __NAMESPACE__ . '\autoload' );

/**
 * Loads the plugin translations.
 *
 * @return void
 */
function load_textdomain() {
	load_plugin_textdomain( 'ksr-debug-log-viewer', false, dirname( plugin_basename( KSRDLV_FILE ) ) . '/languages/' );
}
add_action( 'init', __NAMESPACE__ . '\load_textdomain' );

new Admin_Page();
new Ajax();

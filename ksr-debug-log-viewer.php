<?php
/**
 * Plugin Name: KSR Debug Log Viewer
 * Plugin URI: https://github.com/kowsar89/ksr-debug-log-viewer/
 * Description: View, edit and delete the debug.log file, and toggle WP_DEBUG, WP_DEBUG_LOG and WP_DEBUG_DISPLAY from Tools → Debug Log
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Author: Kowsar Hossain
 * Author URI: https://kowsarhossain.com
 * Text Domain: ksr-debug-log-viewer
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'KSRDLV_VERSION', '1.0.0' );
define( 'KSRDLV_FILE'   , __FILE__ );
define( 'KSRDLV_PATH'   , plugin_dir_path( __FILE__ ) );
define( 'KSRDLV_URL'    , plugin_dir_url( __FILE__ ) );

final class KSRDLV {

	public function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'includes' ) );
	}

	public function load_textdomain(){
		load_plugin_textdomain( 'ksr-debug-log-viewer', false, dirname( plugin_basename(__FILE__) ) . '/languages/' );
	}

	public function includes(){
		if ( !is_admin() ){
			return;
		}
		require_once KSRDLV_PATH . 'inc/init.php';
	}
}

new KSRDLV();

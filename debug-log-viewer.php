<?php
/**
 * Plugin Name: Debug Log Viewer
 * Plugin URI: http://kowsarhossain.com/
 * Description: View, edit and delete the debug.log file, and toggle WP_DEBUG, WP_DEBUG_LOG and WP_DEBUG_DISPLAY from Tools → Debug Log
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Author: Kowsar Hossain
 * Author URI: http://kowsarhossain.com
 * Text Domain: debug-log-viewer
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'DLV_VERSION', '1.0.0' );
define( 'DLV_PATH'   , plugin_dir_path( __FILE__ ) );
define( 'DLV_URL'    , plugin_dir_url( __FILE__ ) );

final class DLV {

	public function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'includes' ) );
	}

	public function load_textdomain(){
		load_plugin_textdomain( 'debug-log-viewer', false, dirname( plugin_basename(__FILE__) ) . '/languages/' );
	}

	public function includes(){
		if ( !is_admin() ){
			return;
		}
		require_once DLV_PATH . 'inc/init.php';
	}
}

new DLV();
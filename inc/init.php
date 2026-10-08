<?php

namespace kowsarhossain\ksrdlv;

class Initialize {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts_and_styles' ) );
		$this->load_files();
	}

	public function scripts_and_styles(){
		$screen = get_current_screen();

		if( !$screen || $screen->id != Admin_Page::SCREEN_ID ) return;

		wp_enqueue_style( 'ksrdlv-admin', KSRDLV_URL . 'assets/css/admin.css', array(), KSRDLV_VERSION );
		wp_enqueue_script( 'ksrdlv-admin', KSRDLV_URL . 'assets/js/admin.js', array( 'jquery' ), KSRDLV_VERSION, true );

		wp_localize_script( 'ksrdlv-admin', 'ksrdlv', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'ksrdlv_nonce' ),
			'i18n'     => array(
				'saved'           => __( 'Log file saved.', 'ksr-debug-log-viewer' ),
				'deleted'         => __( 'Log file deleted.', 'ksr-debug-log-viewer' ),
				'refreshed'       => __( 'Log file reloaded.', 'ksr-debug-log-viewer' ),
				'confirm_delete'  => __( 'Delete the debug log file? This cannot be undone.', 'ksr-debug-log-viewer' ),
				'confirm_reload'  => __( 'You have unsaved changes. Discard them and reload the log?', 'ksr-debug-log-viewer' ),
				'conflict'        => __( 'The log file has changed on disk since it was loaded (new entries may have been written). Overwrite it with your version anyway?', 'ksr-debug-log-viewer' ),
				'unsaved'         => __( 'You have unsaved changes to the debug log.', 'ksr-debug-log-viewer' ),
				'request_failed'  => __( 'Request failed. Please try again.', 'ksr-debug-log-viewer' ),
				'enabled'         => __( 'Enabled', 'ksr-debug-log-viewer' ),
				'disabled'        => __( 'Disabled', 'ksr-debug-log-viewer' ),
				'constant_saved'  => __( '%1$s is now %2$s. The change takes effect on the next request.', 'ksr-debug-log-viewer' ),
				'no_file'         => __( 'The log file does not exist yet. Type here and save to create it.', 'ksr-debug-log-viewer' ),
				'truncated'       => __( 'The log file is too large to edit. Showing the last %s only (read-only).', 'ksr-debug-log-viewer' ),
				'not_found'       => __( 'not found', 'ksr-debug-log-viewer' ),
			),
		) );
	}

	public function load_files(){
		require_once KSRDLV_PATH . 'inc/helper.php';
		require_once KSRDLV_PATH . 'inc/config.php';
		require_once KSRDLV_PATH . 'inc/ajax.php';
		require_once KSRDLV_PATH . 'inc/admin-page.php';

		new Admin_Page();
		new Ajax();
	}
}

new Initialize();
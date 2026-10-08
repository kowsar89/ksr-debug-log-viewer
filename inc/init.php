<?php

namespace kowsarhossain\dlv;

class Initialize {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts_and_styles' ) );
		$this->load_files();
	}

	public function scripts_and_styles(){
		$screen = get_current_screen();

		if( !$screen || $screen->id != Admin_Page::SCREEN_ID ) return;

		wp_enqueue_style( 'dlv-admin', DLV_URL . 'assets/css/admin.css', array(), DLV_VERSION );
		wp_enqueue_script( 'dlv-admin', DLV_URL . 'assets/js/admin.js', array( 'jquery' ), DLV_VERSION, true );

		wp_localize_script( 'dlv-admin', 'dlv', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'dlv_nonce' ),
			'i18n'     => array(
				'saved'           => __( 'Log file saved.', 'debug-log-viewer' ),
				'deleted'         => __( 'Log file deleted.', 'debug-log-viewer' ),
				'refreshed'       => __( 'Log file reloaded.', 'debug-log-viewer' ),
				'confirm_delete'  => __( 'Delete the debug log file? This cannot be undone.', 'debug-log-viewer' ),
				'confirm_reload'  => __( 'You have unsaved changes. Discard them and reload the log?', 'debug-log-viewer' ),
				'conflict'        => __( 'The log file has changed on disk since it was loaded (new entries may have been written). Overwrite it with your version anyway?', 'debug-log-viewer' ),
				'unsaved'         => __( 'You have unsaved changes to the debug log.', 'debug-log-viewer' ),
				'request_failed'  => __( 'Request failed. Please try again.', 'debug-log-viewer' ),
				'enabled'         => __( 'Enabled', 'debug-log-viewer' ),
				'disabled'        => __( 'Disabled', 'debug-log-viewer' ),
				'constant_saved'  => __( '%1$s is now %2$s. The change takes effect on the next request.', 'debug-log-viewer' ),
				'no_file'         => __( 'The log file does not exist yet. Type here and save to create it.', 'debug-log-viewer' ),
				'truncated'       => __( 'The log file is too large to edit. Showing the last %s only (read-only).', 'debug-log-viewer' ),
				'not_found'       => __( 'not found', 'debug-log-viewer' ),
			),
		) );
	}

	public function load_files(){
		require_once DLV_PATH . 'inc/helper.php';
		require_once DLV_PATH . 'inc/config.php';
		require_once DLV_PATH . 'inc/ajax.php';
		require_once DLV_PATH . 'inc/admin-page.php';

		new Admin_Page();
		new Ajax();
	}
}

new Initialize();
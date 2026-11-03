<?php
/**
 * AJAX handlers behind the Debug Log page.
 *
 * @package KSR_Debug_Log_Viewer
 */

namespace kowsarhossain\ksrdlv;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX handlers for the log file and the debug constants.
 */
class Ajax {

	/**
	 * Registers the AJAX actions.
	 */
	public function __construct() {
		add_action( 'wp_ajax_ksrdlv_get_log', array( $this, 'get_log' ) );
		add_action( 'wp_ajax_ksrdlv_save_log', array( $this, 'save_log' ) );
		add_action( 'wp_ajax_ksrdlv_delete_log', array( $this, 'delete_log' ) );
		add_action( 'wp_ajax_ksrdlv_toggle_constant', array( $this, 'toggle_constant' ) );
	}

	/**
	 * Sends the log file content.
	 *
	 * @return void
	 */
	public function get_log() {
		$this->authorize_view();

		$log             = Log_File::read();
		$log['writable'] = $log['writable'] && Admin_Page::can_modify();

		wp_send_json_success( $log );
	}

	/**
	 * Saves the edited log. Refuses with a "conflict" error when the file changed since it was
	 * loaded, unless the request forces the save.
	 *
	 * @return void
	 */
	public function save_log() {
		$this->authorize_modify();

		$stat = Log_File::stat();

		if ( ! rest_sanitize_boolean( $this->posted( 'force' ) ) ) {
			$loaded_size  = absint( $this->posted( 'size' ) );
			$loaded_mtime = absint( $this->posted( 'mtime' ) );

			if ( $stat['size'] !== $loaded_size || $stat['mtime'] !== $loaded_mtime ) {
				wp_send_json_error( array( 'code' => 'conflict' ), 409 );
			}
		}

		// Saved verbatim: it's a plain text log, and the allowed extensions keep it from ever running as code.
		$result = Log_File::write( $this->posted( 'content' ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( Log_File::stat() );
	}

	/**
	 * Deletes the log file and sends the now empty state.
	 *
	 * @return void
	 */
	public function delete_log() {
		$this->authorize_modify();

		$result = Log_File::delete();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( Log_File::read() );
	}

	/**
	 * Turns a debug constant on or off in wp-config.php and sends the new values.
	 *
	 * @return void
	 */
	public function toggle_constant() {
		$this->authorize_modify();

		$name    = sanitize_text_field( $this->posted( 'constant' ) );
		$enabled = rest_sanitize_boolean( $this->posted( 'value' ) );

		$states = Config::set( $name, $enabled );
		if ( is_wp_error( $states ) ) {
			wp_send_json_error( array( 'message' => $states->get_error_message() ) );
		}

		wp_send_json_success( array( 'states' => $states ) );
	}

	/**
	 * Ends the request unless it has a valid nonce and the user may view the log.
	 *
	 * @return void
	 */
	private function authorize_view() {
		check_ajax_referer( 'ksrdlv_nonce', 'nonce' );

		if ( ! current_user_can( Admin_Page::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'ksr-debug-log-viewer' ) ), 403 );
		}
	}

	/**
	 * Ends the request unless it has a valid nonce and the user may change files.
	 *
	 * @return void
	 */
	private function authorize_modify() {
		$this->authorize_view();

		if ( ! Admin_Page::can_modify() ) {
			wp_send_json_error( array( 'message' => __( 'File modifications are disabled on this site.', 'ksr-debug-log-viewer' ) ), 403 );
		}
	}

	/**
	 * Returns a POST field, unslashed but not sanitized; callers sanitize it for its own use.
	 *
	 * @param string $key Field name.
	 * @return string Empty when the field is missing or not a string.
	 */
	private function posted( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every handler verifies the nonce first.
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';

		return is_string( $value ) ? $value : '';
	}
}

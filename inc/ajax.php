<?php

namespace kowsarhossain\dlv;

class Ajax {

	public function __construct() {
		add_action( 'wp_ajax_dlv_get_log', array( $this, 'get_log' ) );
		add_action( 'wp_ajax_dlv_save_log', array( $this, 'save_log' ) );
		add_action( 'wp_ajax_dlv_delete_log', array( $this, 'delete_log' ) );
		add_action( 'wp_ajax_dlv_toggle_constant', array( $this, 'toggle_constant' ) );
	}

	private function verify() {
		check_ajax_referer( 'dlv_nonce', 'nonce' );

		if ( !current_user_can( Admin_Page::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'debug-log-viewer' ) ), 403 );
		}
	}

	public function get_log() {
		$this->verify();
		wp_send_json_success( Helper::read_log() );
	}

	public function save_log() {
		$this->verify();

		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$force   = !empty( $_POST['force'] );
		$stat    = Helper::stat();

		// The file was written to since it was loaded in the browser
		if ( !$force ) {
			$loaded_size  = isset( $_POST['size'] ) ? (int) $_POST['size'] : 0;
			$loaded_mtime = isset( $_POST['mtime'] ) ? (int) $_POST['mtime'] : 0;

			if ( $stat['size'] != $loaded_size || $stat['mtime'] != $loaded_mtime ) {
				wp_send_json_error( array( 'code' => 'conflict' ), 409 );
			}
		}

		$result = Helper::write_log( (string) $content );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( Helper::stat() );
	}

	public function delete_log() {
		$this->verify();

		$result = Helper::delete_log();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( Helper::read_log() );
	}

	public function toggle_constant() {
		$this->verify();

		$name  = isset( $_POST['constant'] ) ? sanitize_text_field( wp_unslash( $_POST['constant'] ) ) : '';
		$value = !empty( $_POST['value'] ) && $_POST['value'] !== 'false';

		$states = Config::set( $name, $value );
		if ( is_wp_error( $states ) ) {
			wp_send_json_error( array( 'message' => $states->get_error_message() ) );
		}

		wp_send_json_success( array( 'states' => $states ) );
	}
}
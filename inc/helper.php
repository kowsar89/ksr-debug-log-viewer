<?php

namespace kowsarhossain\ksrdlv;

class Helper {

	// Same rule WordPress core uses in wp_debug_mode()
	public static function log_path(): string {
		$path = WP_CONTENT_DIR . '/debug.log';

		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && !in_array( strtolower( WP_DEBUG_LOG ), array( 'true', '1', 'false', '0', '' ), true ) ) {
			$path = WP_DEBUG_LOG;
		}

		return apply_filters( 'ksrdlv_log_path', $path );
	}

	public static function max_read_bytes(): int {
		return (int) apply_filters( 'ksrdlv_max_read_bytes', 5 * MB_IN_BYTES );
	}

	public static function stat(): array {
		$path = self::log_path();
		clearstatcache( true, $path );

		$exists = file_exists( $path );
		$size   = $exists ? (int) filesize( $path ) : 0;
		$mtime  = $exists ? (int) filemtime( $path ) : 0;

		return array(
			'path'      => $path,
			'exists'    => $exists,
			'size'      => $size,
			'size_h'    => size_format( $size, 1 ) ?: '0 B',
			'mtime'     => $mtime,
			'mtime_h'   => $mtime ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $mtime ) : '',
			'writable'  => $exists ? is_writable( $path ) : is_writable( dirname( $path ) ),
		);
	}

	public static function read_log(): array {
		$data = self::stat();
		$data['content']   = '';
		$data['truncated'] = false;

		if( !$data['exists'] ) return $data;

		$max = self::max_read_bytes();

		// Small enough, read the whole file
		if ( $data['size'] <= $max ) {
			$content = file_get_contents( $data['path'] );
			$data['content'] = ( $content === false ) ? '' : $content;
			return $data;
		}

		// Too big, read only the tail and drop the first partial line
		$handle = fopen( $data['path'], 'rb' );
		if( !$handle ) return $data;

		fseek( $handle, -$max, SEEK_END );
		$content = (string) stream_get_contents( $handle );
		fclose( $handle );

		$newline = strpos( $content, "\n" );
		if ( $newline !== false ) {
			$content = substr( $content, $newline + 1 );
		}

		$data['content']   = $content;
		$data['truncated'] = true;
		$data['max_h']     = size_format( $max );

		return $data;
	}

	public static function write_log( string $content ) {
		$path = self::log_path();

		if ( file_put_contents( $path, $content, LOCK_EX ) === false ) {
			/* translators: %s: log file path */
			return new \WP_Error( 'write_failed', sprintf( __( 'Could not write to %s. Check file permissions.', 'ksr-debug-log-viewer' ), $path ) );
		}

		return true;
	}

	public static function delete_log() {
		$path = self::log_path();

		if( !file_exists( $path ) ) return true;

		wp_delete_file( $path );
		clearstatcache( true, $path );

		if ( file_exists( $path ) ) {
			/* translators: %s: log file path */
			return new \WP_Error( 'delete_failed', sprintf( __( 'Could not delete %s. Check file permissions.', 'ksr-debug-log-viewer' ), $path ) );
		}

		return true;
	}
}
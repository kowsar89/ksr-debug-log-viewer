<?php
/**
 * Reading, writing and deleting the debug log file.
 *
 * @package KSR_Debug_Log_Viewer
 */

namespace kowsarhossain\ksrdlv;

defined( 'ABSPATH' ) || exit;

/**
 * The debug log file.
 */
class Log_File {

	/**
	 * Returns the log file path, by the same rule WordPress core uses in wp_debug_mode().
	 *
	 * @return string
	 */
	public static function path(): string {
		$path = WP_CONTENT_DIR . '/debug.log';

		if ( defined( 'WP_DEBUG_LOG' ) && self::is_custom_path( WP_DEBUG_LOG ) ) {
			$path = WP_DEBUG_LOG;
		}

		return apply_filters( 'ksrdlv_log_path', $path );
	}

	/**
	 * Tells whether a WP_DEBUG_LOG value is a custom log path rather than an on/off flag.
	 *
	 * @param mixed $value WP_DEBUG_LOG value.
	 * @return bool
	 */
	public static function is_custom_path( $value ): bool {
		return is_string( $value ) && ! in_array( strtolower( $value ), array( 'true', '1', 'false', '0', '' ), true );
	}

	/**
	 * Returns the file's path, size, modification time and whether it can be written.
	 *
	 * @return array
	 */
	public static function stat(): array {
		$path = self::path();
		clearstatcache( true, $path );

		$exists = file_exists( $path );
		$size   = $exists ? (int) filesize( $path ) : 0;
		$mtime  = $exists ? (int) filemtime( $path ) : 0;

		return array(
			'path'     => $path,
			'exists'   => $exists,
			'size'     => $size,
			'size_h'   => size_format( $size, 1 ),
			'mtime'    => $mtime,
			'mtime_h'  => $mtime ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $mtime ) : '',
			'writable' => self::is_allowed_path( $path ) && wp_is_writable( $exists ? $path : dirname( $path ) ),
		);
	}

	/**
	 * Returns the file stat plus its content. A file over the size limit is cut to its last lines.
	 *
	 * @return array
	 */
	public static function read(): array {
		$data              = self::stat();
		$data['content']   = '';
		$data['truncated'] = false;

		if ( ! $data['exists'] ) {
			return $data;
		}

		$max_bytes = self::max_read_bytes();

		if ( $data['size'] <= $max_bytes ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions -- Reads a local file; WP_Filesystem may need FTP credentials.
			$data['content'] = (string) file_get_contents( $data['path'] );
			return $data;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions -- WP_Filesystem can't read just the end of a file.
		$handle = fopen( $data['path'], 'rb' );
		if ( ! $handle ) {
			return $data;
		}

		fseek( $handle, -$max_bytes, SEEK_END );
		$tail = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Closes the handle from fopen() above.

		// The tail most likely starts mid-line.
		$first_newline = strpos( $tail, "\n" );
		if ( false !== $first_newline ) {
			$tail = substr( $tail, $first_newline + 1 );
		}

		$data['content']   = $tail;
		$data['truncated'] = true;
		$data['max_h']     = size_format( $max_bytes );

		return $data;
	}

	/**
	 * Replaces the file content, creating the file if needed.
	 *
	 * @param string $content New content.
	 * @return true|\WP_Error
	 */
	public static function write( string $content ) {
		$path = self::path();

		if ( ! self::is_allowed_path( $path ) ) {
			return self::not_allowed_error( $path );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions -- WP_Filesystem may need FTP credentials, which an AJAX request can't ask for.
		if ( false === file_put_contents( $path, $content, LOCK_EX ) ) {
			/* translators: %s: log file path */
			return new \WP_Error( 'write_failed', sprintf( __( 'Could not write to %s. Check file permissions.', 'ksr-debug-log-viewer' ), $path ) );
		}

		return true;
	}

	/**
	 * Deletes the file. A file that doesn't exist counts as deleted.
	 *
	 * @return true|\WP_Error
	 */
	public static function delete() {
		$path = self::path();

		if ( ! self::is_allowed_path( $path ) ) {
			return self::not_allowed_error( $path );
		}

		if ( ! file_exists( $path ) ) {
			return true;
		}

		wp_delete_file( $path );
		clearstatcache( true, $path );

		if ( file_exists( $path ) ) {
			/* translators: %s: log file path */
			return new \WP_Error( 'delete_failed', sprintf( __( 'Could not delete %s. Check file permissions.', 'ksr-debug-log-viewer' ), $path ) );
		}

		return true;
	}

	/**
	 * Tells whether a path may be written or deleted. Only plain log files qualify, so a log path
	 * such as debug.php can never be turned into code.
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	private static function is_allowed_path( string $path ): bool {
		return in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), self::allowed_extensions(), true );
	}

	/**
	 * Returns the file extensions that may be written or deleted.
	 *
	 * @return string[]
	 */
	private static function allowed_extensions(): array {
		return (array) apply_filters( 'ksrdlv_allowed_extensions', array( 'log', 'txt' ) );
	}

	/**
	 * Returns the largest number of bytes read into the editor.
	 *
	 * @return int
	 */
	private static function max_read_bytes(): int {
		return (int) apply_filters( 'ksrdlv_max_read_bytes', 5 * MB_IN_BYTES );
	}

	/**
	 * Builds the error for a path that may not be modified.
	 *
	 * @param string $path File path.
	 * @return \WP_Error
	 */
	private static function not_allowed_error( string $path ): \WP_Error {
		return new \WP_Error(
			'path_not_allowed',
			sprintf(
				/* translators: 1: log file path, 2: allowed file extensions */
				__( 'Refusing to modify %1$s: only files with these extensions can be edited or deleted: %2$s', 'ksr-debug-log-viewer' ),
				$path,
				implode( ', ', self::allowed_extensions() )
			)
		);
	}
}

<?php
/**
 * Reading and changing the debug constants in wp-config.php.
 *
 * @package KSR_Debug_Log_Viewer
 */

namespace kowsarhossain\ksrdlv;

defined( 'ABSPATH' ) || exit;

/**
 * The debug constants in wp-config.php.
 */
class Config {

	const CONSTANTS = array( 'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY' );

	/**
	 * Returns the wp-config.php path, found the same way as in core's wp-load.php.
	 *
	 * @return string Empty when the file can't be found.
	 */
	public static function path(): string {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}

		$parent_dir = dirname( ABSPATH );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors -- Same as core: open_basedir may block the parent folder.
		if ( @file_exists( $parent_dir . '/wp-config.php' ) && ! @file_exists( $parent_dir . '/wp-settings.php' ) ) {
			return $parent_dir . '/wp-config.php';
		}

		return '';
	}

	/**
	 * Tells whether wp-config.php can be changed.
	 *
	 * @return bool
	 */
	public static function is_writable(): bool {
		$path = self::path();
		return '' !== $path && wp_is_writable( $path );
	}

	/**
	 * Returns the value of each constant in the current request.
	 *
	 * @return bool[] Keyed by constant name.
	 */
	public static function states(): array {
		$states = array();
		foreach ( self::CONSTANTS as $name ) {
			$states[ $name ] = self::runtime_value( $name );
		}
		return $states;
	}

	/**
	 * Turns a constant on or off in wp-config.php, adding its define() when it's missing.
	 *
	 * @param string $name    Constant name, one of self::CONSTANTS.
	 * @param bool   $enabled New value.
	 * @return bool[]|\WP_Error The constant values as now written in wp-config.php.
	 */
	public static function set( string $name, bool $enabled ) {
		if ( ! in_array( $name, self::CONSTANTS, true ) ) {
			return new \WP_Error( 'invalid_constant', __( 'Invalid constant.', 'ksr-debug-log-viewer' ) );
		}

		$path = self::path();
		if ( '' === $path ) {
			return new \WP_Error( 'config_missing', __( 'Could not locate wp-config.php.', 'ksr-debug-log-viewer' ) );
		}
		if ( ! wp_is_writable( $path ) ) {
			return new \WP_Error( 'config_not_writable', __( 'wp-config.php is not writable.', 'ksr-debug-log-viewer' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions -- Reads a local file; WP_Filesystem may need FTP credentials.
		$content = file_get_contents( $path );
		if ( false === $content ) {
			return new \WP_Error( 'config_unreadable', __( 'Could not read wp-config.php.', 'ksr-debug-log-viewer' ) );
		}

		$new_content = self::with_constant( $content, $name, $enabled );
		if ( is_wp_error( $new_content ) ) {
			return $new_content;
		}

		// Never write a file that would break the site.
		if ( ! self::is_valid_php( $new_content ) ) {
			return new \WP_Error( 'config_parse', __( 'Aborted: the change would produce an invalid wp-config.php.', 'ksr-debug-log-viewer' ) );
		}

		if ( $new_content !== $content ) {
			$replaced = self::replace_file( $path, $new_content, $content );
			if ( is_wp_error( $replaced ) ) {
				return $replaced;
			}
		}

		wp_opcache_invalidate( $path, true );

		return self::file_states( $new_content );
	}

	/**
	 * Returns wp-config.php content with the constant set to the given value.
	 *
	 * @param string $content wp-config.php content.
	 * @param string $name    Constant name.
	 * @param bool   $enabled New value.
	 * @return string|\WP_Error
	 */
	private static function with_constant( string $content, string $name, bool $enabled ) {
		$literal = $enabled ? 'true' : 'false';
		$pattern = self::define_pattern( $name );

		if ( ! preg_match( $pattern, $content ) ) {
			$offset = self::insert_offset( $content );
			if ( false === $offset ) {
				return new \WP_Error( 'config_no_anchor', __( 'Could not find a place to add the constant in wp-config.php.', 'ksr-debug-log-viewer' ) );
			}
			return substr( $content, 0, $offset ) . "define( '$name', $literal );\n" . substr( $content, $offset );
		}

		$new_content = preg_replace_callback(
			$pattern,
			function ( $define ) use ( $name, $enabled, $literal ) {
				// Turning WP_DEBUG_LOG on keeps a custom log path.
				if ( 'WP_DEBUG_LOG' === $name && $enabled && self::is_path_literal( $define['value'] ) ) {
					return $define[0];
				}
				return $define['before'] . $literal . $define['after'];
			},
			$content
		);

		if ( null === $new_content ) {
			return new \WP_Error( 'config_regex', __( 'Could not update wp-config.php.', 'ksr-debug-log-viewer' ) );
		}

		return $new_content;
	}

	/**
	 * Tells whether PHP code parses without syntax errors.
	 *
	 * @param string $code PHP code.
	 * @return bool
	 */
	private static function is_valid_php( string $code ): bool {
		try {
			token_get_all( $code, TOKEN_PARSE );
			return true;
		} catch ( \ParseError $error ) {
			return false;
		}
	}

	/**
	 * Replaces wp-config.php without ever leaving it empty or half written.
	 *
	 * A plain file_put_contents() empties the file before writing it. If the disk
	 * fills up or the process dies in between, the site is left with a broken
	 * wp-config.php that can only be fixed over FTP. So the new content is written
	 * to a temporary file first and then renamed over the original: rename() swaps
	 * the file in a single step, so wp-config.php is always either the old version
	 * or the new one.
	 *
	 * @param string $path        wp-config.php path.
	 * @param string $new_content Content to write.
	 * @param string $old_content Current content, restored if an in-place write fails.
	 * @return true|\WP_Error
	 */
	private static function replace_file( string $path, string $new_content, string $old_content ) {
		// Some hosts symlink wp-config.php. Work on the real file, or rename() would replace the link itself.
		$real_path = realpath( $path );
		$target    = false === $real_path ? $path : $real_path;

		if ( wp_is_writable( dirname( $target ) ) ) {
			return self::replace_by_rename( $target, $new_content );
		}

		// Only the file is writable, not its folder, so a temporary file can't be created next to it.
		return self::replace_in_place( $target, $new_content, $old_content );
	}

	/**
	 * Writes the content to a temporary file and renames it over the target.
	 *
	 * @param string $target      File to replace.
	 * @param string $new_content Content to write.
	 * @return true|\WP_Error
	 */
	private static function replace_by_rename( string $target, string $new_content ) {
		// The random part makes the name unguessable while it briefly exists. It ends in .php so that, if it
		// is ever requested, the web server runs it instead of serving the database credentials as plain text.
		$temp = $target . '.ksrdlv-' . wp_generate_password( 12, false ) . '.tmp.php';

		// A short write (e.g. disk full) is a failure too. The original file has not been touched yet.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions -- Failure is handled below; the one-step replace needs direct file access.
		if ( @file_put_contents( $temp, $new_content ) !== strlen( $new_content ) ) {
			wp_delete_file( $temp );
			return self::write_error();
		}

		// A new file gets default permissions, which can be looser than the original's (e.g. 0644 instead of 0400).
		$permissions = @fileperms( $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- Failure is handled below.
		if ( false !== $permissions ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions -- Best effort: if it fails, the file keeps the default permissions.
			@chmod( $temp, $permissions & 0777 );
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions -- Failure is handled below; WP_Filesystem::move() deletes the target before renaming.
		if ( ! @rename( $temp, $target ) ) {
			wp_delete_file( $temp );
			return self::write_error();
		}

		return true;
	}

	/**
	 * Overwrites the target directly, restoring the old content if the write fails.
	 *
	 * @param string $target      File to replace.
	 * @param string $new_content Content to write.
	 * @param string $old_content Content to restore on failure.
	 * @return true|\WP_Error
	 */
	private static function replace_in_place( string $target, string $new_content, string $old_content ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions -- Failure is handled below; WP_Filesystem may need FTP credentials.
		if ( @file_put_contents( $target, $new_content, LOCK_EX ) === strlen( $new_content ) ) {
			return true;
		}

		// The file was already emptied when the write failed. Putting the original content back usually succeeds,
		// because emptying the file freed exactly the space that content needs.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions -- Failure is handled below; WP_Filesystem may need FTP credentials.
		if ( @file_put_contents( $target, $old_content, LOCK_EX ) === strlen( $old_content ) ) {
			return self::write_error();
		}

		return new \WP_Error( 'config_corrupt', __( 'Could not write wp-config.php, and restoring its original content also failed. Restore wp-config.php over FTP before reloading the site.', 'ksr-debug-log-viewer' ) );
	}

	/**
	 * Builds the error for a write that left wp-config.php unchanged.
	 *
	 * @return \WP_Error
	 */
	private static function write_error(): \WP_Error {
		return new \WP_Error( 'config_write', __( 'Could not write wp-config.php. It has been left unchanged.', 'ksr-debug-log-viewer' ) );
	}

	/**
	 * Returns the value of each constant as written in wp-config.php, falling back to the current request.
	 *
	 * @param string $content wp-config.php content.
	 * @return bool[] Keyed by constant name.
	 */
	private static function file_states( string $content ): array {
		$states = array();

		foreach ( self::CONSTANTS as $name ) {
			$value = null;
			if ( preg_match( self::define_pattern( $name ), $content, $define ) ) {
				$value = self::parse_literal( $name, $define['value'] );
			}
			$states[ $name ] = $value ?? self::runtime_value( $name );
		}

		return $states;
	}

	/**
	 * Returns a regex matching an uncommented define( 'NAME', value ); at the start of a line.
	 *
	 * @param string $name Constant name.
	 * @return string
	 */
	private static function define_pattern( string $name ): string {
		return '/^(?P<before>[ \t]*define\s*\(\s*([\'"])' . preg_quote( $name, '/' ) . '\2\s*,\s*)(?P<value>.+?)(?P<after>\s*\)\s*;)/m';
	}

	/**
	 * Finds where to insert a new define(): before the "stop editing" line, or else before wp-settings.php is loaded.
	 *
	 * @param string $content wp-config.php content.
	 * @return int|false Byte offset, or false when neither line exists.
	 */
	private static function insert_offset( string $content ) {
		$anchors = array(
			'/^.*That\'s all, stop editing.*$/m',
			'/^.*require_once\s*\(?\s*ABSPATH\s*\.\s*[\'"]wp-settings\.php.*$/m',
		);

		foreach ( $anchors as $anchor ) {
			if ( preg_match( $anchor, $content, $match, PREG_OFFSET_CAPTURE ) ) {
				return $match[0][1];
			}
		}

		return false;
	}

	/**
	 * Tells whether a value written in wp-config.php is a quoted custom log path.
	 *
	 * @param string $literal Value as written, e.g. '/tmp/debug.log' with quotes.
	 * @return bool
	 */
	private static function is_path_literal( string $literal ): bool {
		if ( ! preg_match( '/^([\'"])(.*)\1$/', trim( $literal ), $quoted ) ) {
			return false;
		}
		return Log_File::is_custom_path( $quoted[2] );
	}

	/**
	 * Converts a value written in wp-config.php to a bool.
	 *
	 * @param string $name    Constant name.
	 * @param string $literal Value as written.
	 * @return bool|null Null when it's an expression that can't be evaluated here.
	 */
	private static function parse_literal( string $name, string $literal ) {
		$literal = strtolower( trim( $literal ) );

		if ( in_array( $literal, array( 'true', '1', "'1'", '"1"', "'true'", '"true"' ), true ) ) {
			return true;
		}
		if ( in_array( $literal, array( 'false', '0', 'null', "''", '""', "'0'", '"0"', "'false'", '"false"' ), true ) ) {
			return false;
		}
		if ( 'WP_DEBUG_LOG' === $name && self::is_path_literal( $literal ) ) {
			return true;
		}

		return null;
	}

	/**
	 * Returns a constant's value in the current request, as a bool.
	 *
	 * @param string $name Constant name.
	 * @return bool
	 */
	private static function runtime_value( string $name ): bool {
		if ( ! defined( $name ) ) {
			// WP_DEBUG_DISPLAY defaults to true in wp_initial_constants().
			return 'WP_DEBUG_DISPLAY' === $name;
		}

		$value = constant( $name );

		if ( 'WP_DEBUG_LOG' === $name && is_string( $value ) ) {
			return ! in_array( strtolower( $value ), array( 'false', '0', '' ), true );
		}

		return (bool) $value;
	}
}

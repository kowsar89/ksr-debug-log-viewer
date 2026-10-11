<?php

namespace kowsarhossain\ksrdlv;

if ( ! defined( 'ABSPATH' ) ) exit;

class Config {

	const CONSTANTS = array( 'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY' );

	// Same lookup WordPress core uses in wp-load.php
	public static function path(): string {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}

		if ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && !@file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return dirname( ABSPATH ) . '/wp-config.php';
		}

		return '';
	}

	public static function is_writable(): bool {
		$path = self::path();
		return $path && is_writable( $path );
	}

	// Values of the constants in the current request
	public static function states(): array {
		$states = array();
		foreach ( self::CONSTANTS as $name ) {
			$states[$name] = self::runtime_value( $name );
		}
		return $states;
	}

	public static function set( string $name, bool $value ) {
		if ( !in_array( $name, self::CONSTANTS, true ) ) {
			return new \WP_Error( 'invalid_constant', __( 'Invalid constant.', 'ksr-debug-log-viewer' ) );
		}

		$path = self::path();
		if ( !$path ) {
			return new \WP_Error( 'config_missing', __( 'Could not locate wp-config.php.', 'ksr-debug-log-viewer' ) );
		}
		if ( !is_writable( $path ) ) {
			return new \WP_Error( 'config_not_writable', __( 'wp-config.php is not writable.', 'ksr-debug-log-viewer' ) );
		}

		$content = file_get_contents( $path );
		if ( $content === false ) {
			return new \WP_Error( 'config_unreadable', __( 'Could not read wp-config.php.', 'ksr-debug-log-viewer' ) );
		}

		$literal = $value ? 'true' : 'false';
		$pattern = self::define_pattern( $name );

		if ( preg_match( $pattern, $content ) ) {
			// Replace the value of the existing define(s)
			$new = preg_replace_callback( $pattern, function( $m ) use ( $name, $value, $literal ) {
				// Keep a custom log path when turning WP_DEBUG_LOG on
				if ( $name == 'WP_DEBUG_LOG' && $value && self::is_path_value( $m['value'] ) ) {
					return $m[0];
				}
				return $m['before'] . $literal . $m['after'];
			}, $content );
		} else {
			// Insert a new define before the "stop editing" line, or before wp-settings.php is loaded
			$offset = self::insert_offset( $content );
			if ( $offset === false ) {
				return new \WP_Error( 'config_no_anchor', __( 'Could not find a place to add the constant in wp-config.php.', 'ksr-debug-log-viewer' ) );
			}
			$line = "define( '$name', $literal );\n";
			$new  = substr( $content, 0, $offset ) . $line . substr( $content, $offset );
		}

		if ( $new === null ) {
			return new \WP_Error( 'config_regex', __( 'Could not update wp-config.php.', 'ksr-debug-log-viewer' ) );
		}

		// Never write a file that would break the site
		try {
			token_get_all( $new, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			return new \WP_Error( 'config_parse', __( 'Aborted: the change would produce an invalid wp-config.php.', 'ksr-debug-log-viewer' ) );
		}

		if ( $new !== $content ) {
			$replaced = self::replace_file( $path, $new, $content );
			if ( is_wp_error( $replaced ) ) {
				return $replaced;
			}
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $path, true );
		}

		return self::file_states( $new );
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
	 * @return true|\WP_Error
	 */
	private static function replace_file( string $path, string $new_content, string $old_content ) {
		// Some hosts symlink wp-config.php. Work on the real file, or rename() would replace the link itself.
		$target = realpath( $path ) ?: $path;

		if ( is_writable( dirname( $target ) ) ) {
			return self::replace_by_rename( $target, $new_content );
		}

		// Only the file is writable, not its folder, so a temporary file can't be created next to it
		return self::replace_in_place( $target, $new_content, $old_content );
	}

	/** @return true|\WP_Error */
	private static function replace_by_rename( string $target, string $new_content ) {
		// The random part makes the name unguessable while it briefly exists. It ends in .php so that, if it
		// is ever requested, the web server runs it instead of serving the database credentials as plain text.
		$temp = $target . '.ksrdlv-' . wp_generate_password( 12, false ) . '.tmp.php';

		// A short write (e.g. disk full) is a failure too: the original file has not been touched yet
		if ( @file_put_contents( $temp, $new_content ) !== strlen( $new_content ) ) {
			@unlink( $temp );
			return self::write_error();
		}

		// A new file gets default permissions, which can be looser than the original's (e.g. 0644 instead of 0400)
		$permissions = @fileperms( $target );
		if ( $permissions !== false ) {
			@chmod( $temp, $permissions & 0777 );
		}

		if ( !@rename( $temp, $target ) ) {
			@unlink( $temp );
			return self::write_error();
		}

		return true;
	}

	/** @return true|\WP_Error */
	private static function replace_in_place( string $target, string $new_content, string $old_content ) {
		if ( @file_put_contents( $target, $new_content, LOCK_EX ) === strlen( $new_content ) ) {
			return true;
		}

		// The file was already emptied when the write failed. Putting the original content back usually succeeds,
		// because emptying the file freed exactly the space that content needs.
		if ( @file_put_contents( $target, $old_content, LOCK_EX ) === strlen( $old_content ) ) {
			return self::write_error();
		}

		return new \WP_Error( 'config_corrupt', __( 'Could not write wp-config.php, and restoring its original content also failed. Restore wp-config.php over FTP before reloading the site.', 'ksr-debug-log-viewer' ) );
	}

	private static function write_error(): \WP_Error {
		return new \WP_Error( 'config_write', __( 'Could not write wp-config.php. It has been left unchanged.', 'ksr-debug-log-viewer' ) );
	}

	// Values as written in wp-config.php, falling back to the current request
	public static function file_states( string $content ): array {
		$states = array();

		foreach ( self::CONSTANTS as $name ) {
			$states[$name] = self::runtime_value( $name );

			if ( preg_match( self::define_pattern( $name ), $content, $m ) ) {
				$parsed = self::parse_value( $name, $m['value'] );
				if ( $parsed !== null ) {
					$states[$name] = $parsed;
				}
			}
		}

		return $states;
	}

	// Matches an uncommented define( 'NAME', value ); at the start of a line
	private static function define_pattern( string $name ): string {
		return '/^(?P<before>[ \t]*define\s*\(\s*([\'"])' . preg_quote( $name, '/' ) . '\2\s*,\s*)(?P<value>.+?)(?P<after>\s*\)\s*;)/m';
	}

	private static function insert_offset( string $content ) {
		$anchors = array(
			'/^.*That\'s all, stop editing.*$/m',
			'/^.*require_once\s*\(?\s*ABSPATH\s*\.\s*[\'"]wp-settings\.php.*$/m',
		);

		foreach ( $anchors as $anchor ) {
			if ( preg_match( $anchor, $content, $m, PREG_OFFSET_CAPTURE ) ) {
				return $m[0][1];
			}
		}

		return false;
	}

	private static function is_path_value( string $value ): bool {
		$value = trim( $value );
		if ( !preg_match( '/^([\'"])(.*)\1$/', $value, $m ) ) {
			return false;
		}
		return !in_array( strtolower( $m[2] ), array( 'true', '1', 'false', '0', '' ), true );
	}

	// Converts a written value to a bool, or null when it is an expression we can't evaluate
	private static function parse_value( string $name, string $value ) {
		$value = strtolower( trim( $value ) );

		if ( in_array( $value, array( 'true', '1', "'1'", '"1"', "'true'", '"true"' ), true ) ) return true;
		if ( in_array( $value, array( 'false', '0', 'null', "''", '""', "'0'", '"0"', "'false'", '"false"' ), true ) ) return false;
		if ( $name == 'WP_DEBUG_LOG' && self::is_path_value( $value ) ) return true;

		return null;
	}

	private static function runtime_value( string $name ): bool {
		if ( !defined( $name ) ) {
			// WP_DEBUG_DISPLAY defaults to true in wp_initial_constants()
			return $name == 'WP_DEBUG_DISPLAY';
		}

		$value = constant( $name );

		if ( $name == 'WP_DEBUG_LOG' && is_string( $value ) ) {
			return !in_array( strtolower( $value ), array( 'false', '0', '' ), true );
		}

		return (bool) $value;
	}
}
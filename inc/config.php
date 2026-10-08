<?php

namespace kowsarhossain\dlv;

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
			return new \WP_Error( 'invalid_constant', __( 'Invalid constant.', 'debug-log-viewer' ) );
		}

		$path = self::path();
		if ( !$path ) {
			return new \WP_Error( 'config_missing', __( 'Could not locate wp-config.php.', 'debug-log-viewer' ) );
		}
		if ( !is_writable( $path ) ) {
			return new \WP_Error( 'config_not_writable', __( 'wp-config.php is not writable.', 'debug-log-viewer' ) );
		}

		$content = file_get_contents( $path );
		if ( $content === false ) {
			return new \WP_Error( 'config_unreadable', __( 'Could not read wp-config.php.', 'debug-log-viewer' ) );
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
				return new \WP_Error( 'config_no_anchor', __( 'Could not find a place to add the constant in wp-config.php.', 'debug-log-viewer' ) );
			}
			$line = "define( '$name', $literal );\n";
			$new  = substr( $content, 0, $offset ) . $line . substr( $content, $offset );
		}

		if ( $new === null ) {
			return new \WP_Error( 'config_regex', __( 'Could not update wp-config.php.', 'debug-log-viewer' ) );
		}

		// Never write a file that would break the site
		try {
			token_get_all( $new, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			return new \WP_Error( 'config_parse', __( 'Aborted: the change would produce an invalid wp-config.php.', 'debug-log-viewer' ) );
		}

		if ( $new !== $content && file_put_contents( $path, $new, LOCK_EX ) === false ) {
			return new \WP_Error( 'config_write', __( 'Could not write wp-config.php.', 'debug-log-viewer' ) );
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $path, true );
		}

		return self::file_states( $new );
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
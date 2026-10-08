<?php

namespace kowsarhossain\dlv;

class Admin_Page {

	const SLUG       = 'debug-log-viewer';
	const SCREEN_ID  = 'tools_page_debug-log-viewer';
	const CAPABILITY = 'manage_options';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	public function add_menu(){
		add_management_page(
			__( 'Debug Log Viewer', 'debug-log-viewer' ),
			__( 'Debug Log', 'debug-log-viewer' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render(){
		$states   = Config::states();
		$writable = Config::is_writable();
		$stat     = Helper::stat();

		$constants = array(
			'WP_DEBUG'         => __( 'Enables debug mode across WordPress.', 'debug-log-viewer' ),
			'WP_DEBUG_LOG'     => __( 'Saves errors to the debug.log file.', 'debug-log-viewer' ),
			'WP_DEBUG_DISPLAY' => __( 'Shows errors in the page HTML.', 'debug-log-viewer' ),
		);
		?>
		<div class="wrap dlv-wrap">
			<h1><?php esc_html_e( 'Debug Log Viewer', 'debug-log-viewer' ); ?></h1>

			<div class="dlv-notices"></div>

			<div class="dlv-card dlv-settings">
				<div class="dlv-constants">
					<?php foreach ( $constants as $name => $desc ) : $on = $states[$name]; ?>
						<div class="dlv-constant">
							<label class="dlv-switch">
								<input type="checkbox" class="dlv-toggle" data-constant="<?php echo esc_attr( $name ); ?>" <?php checked( $on ); disabled( !$writable ); ?>>
								<span class="dlv-slider"></span>
							</label>
							<div class="dlv-constant-info">
								<code><?php echo esc_html( $name ); ?></code>
								<span class="dlv-badge <?php echo $on ? 'is-on' : 'is-off'; ?>" data-badge="<?php echo esc_attr( $name ); ?>">
									<?php echo $on ? esc_html__( 'Enabled', 'debug-log-viewer' ) : esc_html__( 'Disabled', 'debug-log-viewer' ); ?>
								</span>
								<p class="description"><?php echo esc_html( $desc ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<p class="description dlv-hint">
					<?php esc_html_e( 'WP_DEBUG_LOG and WP_DEBUG_DISPLAY only apply while WP_DEBUG is enabled. Changes are written to wp-config.php and take effect on the next request.', 'debug-log-viewer' ); ?>
				</p>

				<?php if ( !$writable ) : ?>
					<p class="dlv-warning">
						<?php
						/* translators: %s: wp-config.php path */
						printf( esc_html__( '%s is not writable, so the constants cannot be changed from here.', 'debug-log-viewer' ), '<code>' . esc_html( Config::path() ?: 'wp-config.php' ) . '</code>' );
						?>
					</p>
				<?php endif; ?>

				<div class="dlv-view-options">
					<label><input type="checkbox" id="dlv-wrap"> <?php esc_html_e( 'Word wrap', 'debug-log-viewer' ); ?></label>
					<label><input type="checkbox" id="dlv-autoscroll" checked> <?php esc_html_e( 'Scroll to bottom on load', 'debug-log-viewer' ); ?></label>
				</div>
			</div>

			<div class="dlv-toolbar">
				<div class="dlv-file-info">
					<span class="dashicons dashicons-media-text"></span>
					<code class="dlv-path"><?php echo esc_html( $stat['path'] ); ?></code>
					<span class="dlv-meta"></span>
					<span class="dlv-dirty" hidden>● <?php esc_html_e( 'Unsaved changes', 'debug-log-viewer' ); ?></span>
				</div>
				<div class="dlv-actions">
					<span class="spinner"></span>
					<button type="button" class="button dlv-refresh"><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh', 'debug-log-viewer' ); ?></button>
					<button type="button" class="button button-primary dlv-save" title="Ctrl+S"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save', 'debug-log-viewer' ); ?></button>
					<button type="button" class="button dlv-delete"><span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete', 'debug-log-viewer' ); ?></button>
				</div>
			</div>

			<div class="dlv-editor">
				<div class="dlv-gutter" aria-hidden="true"></div>
				<textarea class="dlv-textarea" spellcheck="false" wrap="off" autocomplete="off" autocapitalize="off" aria-label="<?php esc_attr_e( 'Debug log contents', 'debug-log-viewer' ); ?>"></textarea>
			</div>
		</div>
		<?php
	}
}
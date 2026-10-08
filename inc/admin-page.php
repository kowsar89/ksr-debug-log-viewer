<?php

namespace kowsarhossain\ksrdlv;

class Admin_Page {

	const SLUG       = 'ksr-debug-log-viewer';
	const SCREEN_ID  = 'tools_page_ksr-debug-log-viewer';
	const CAPABILITY = 'manage_options';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	public function add_menu(){
		add_management_page(
			__( 'KSR Debug Log Viewer', 'ksr-debug-log-viewer' ),
			__( 'Debug Log', 'ksr-debug-log-viewer' ),
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
			'WP_DEBUG'         => __( 'Enables debug mode across WordPress.', 'ksr-debug-log-viewer' ),
			'WP_DEBUG_LOG'     => __( 'Saves errors to the debug.log file.', 'ksr-debug-log-viewer' ),
			'WP_DEBUG_DISPLAY' => __( 'Shows errors in the page HTML.', 'ksr-debug-log-viewer' ),
		);
		?>
		<div class="wrap ksrdlv-wrap">
			<h1><?php esc_html_e( 'KSR Debug Log Viewer', 'ksr-debug-log-viewer' ); ?></h1>

			<div class="ksrdlv-notices"></div>

			<div class="ksrdlv-card ksrdlv-settings">
				<div class="ksrdlv-constants">
					<?php foreach ( $constants as $name => $desc ) : $on = $states[$name]; ?>
						<div class="ksrdlv-constant">
							<label class="ksrdlv-switch">
								<input type="checkbox" class="ksrdlv-toggle" data-constant="<?php echo esc_attr( $name ); ?>" <?php checked( $on ); disabled( !$writable ); ?>>
								<span class="ksrdlv-slider"></span>
							</label>
							<div class="ksrdlv-constant-info">
								<code><?php echo esc_html( $name ); ?></code>
								<span class="ksrdlv-badge <?php echo $on ? 'is-on' : 'is-off'; ?>" data-badge="<?php echo esc_attr( $name ); ?>">
									<?php echo $on ? esc_html__( 'Enabled', 'ksr-debug-log-viewer' ) : esc_html__( 'Disabled', 'ksr-debug-log-viewer' ); ?>
								</span>
								<p class="description"><?php echo esc_html( $desc ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<p class="description ksrdlv-hint">
					<?php esc_html_e( 'WP_DEBUG_LOG and WP_DEBUG_DISPLAY only apply while WP_DEBUG is enabled. Changes are written to wp-config.php and take effect on the next request.', 'ksr-debug-log-viewer' ); ?>
				</p>

				<?php if ( !$writable ) : ?>
					<p class="ksrdlv-warning">
						<?php
						/* translators: %s: wp-config.php path */
						printf( esc_html__( '%s is not writable, so the constants cannot be changed from here.', 'ksr-debug-log-viewer' ), '<code>' . esc_html( Config::path() ?: 'wp-config.php' ) . '</code>' );
						?>
					</p>
				<?php endif; ?>

				<div class="ksrdlv-view-options">
					<label class="ksrdlv-option">
						<span class="ksrdlv-switch">
							<input type="checkbox" id="ksrdlv-wrap" checked>
							<span class="ksrdlv-slider"></span>
						</span>
						<?php esc_html_e( 'Word wrap', 'ksr-debug-log-viewer' ); ?>
					</label>
					<label class="ksrdlv-option">
						<span class="ksrdlv-switch">
							<input type="checkbox" id="ksrdlv-autoscroll" checked>
							<span class="ksrdlv-slider"></span>
						</span>
						<?php esc_html_e( 'Scroll to bottom on load', 'ksr-debug-log-viewer' ); ?>
					</label>
				</div>
			</div>

			<div class="ksrdlv-toolbar">
				<div class="ksrdlv-file-info">
					<span class="dashicons dashicons-media-text"></span>
					<code class="ksrdlv-path"><?php echo esc_html( $stat['path'] ); ?></code>
					<span class="ksrdlv-meta"></span>
					<span class="ksrdlv-dirty" hidden>● <?php esc_html_e( 'Unsaved changes', 'ksr-debug-log-viewer' ); ?></span>
				</div>
				<div class="ksrdlv-actions">
					<span class="spinner"></span>
					<button type="button" class="button ksrdlv-refresh"><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh', 'ksr-debug-log-viewer' ); ?></button>
					<button type="button" class="button button-primary ksrdlv-save" title="Ctrl+S"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save', 'ksr-debug-log-viewer' ); ?></button>
					<button type="button" class="button ksrdlv-delete"><span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete', 'ksr-debug-log-viewer' ); ?></button>
				</div>
			</div>

			<div class="ksrdlv-editor">
				<div class="ksrdlv-gutter" aria-hidden="true"></div>
				<textarea class="ksrdlv-textarea" spellcheck="false" wrap="off" autocomplete="off" autocapitalize="off" aria-label="<?php esc_attr_e( 'Debug log contents', 'ksr-debug-log-viewer' ); ?>"></textarea>
			</div>
		</div>
		<?php
	}
}
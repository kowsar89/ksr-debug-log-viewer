<?php
/**
 * The Debug Log admin page and its admin bar link.
 *
 * @package KSR_Debug_Log_Viewer
 */

namespace kowsarhossain\ksrdlv;

defined( 'ABSPATH' ) || exit;

/**
 * The Debug Log admin page.
 */
class Admin_Page {

	const SLUG = 'ksr-debug-log-viewer';

	/**
	 * Hooks the page into the admin menus and the admin bar.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'add_network_menu' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_node' ), 100 );
	}

	/**
	 * Returns the capability needed to view the page. The log and wp-config.php are shared by
	 * the whole network, so on multisite only super admins get access.
	 *
	 * @return string
	 */
	public static function capability(): string {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * Tells whether the current user may change the log and wp-config.php. That also needs
	 * edit_files, which core denies when DISALLOW_FILE_EDIT or DISALLOW_FILE_MODS is set.
	 *
	 * @return bool
	 */
	public static function can_modify(): bool {
		return current_user_can( self::capability() ) && current_user_can( 'edit_files' );
	}

	/**
	 * Registers the page in the site admin, unless it lives in Network Admin.
	 *
	 * @return void
	 */
	public function add_menu() {
		if ( self::is_network_page() ) {
			return;
		}
		$this->register_page();
	}

	/**
	 * Registers the page in Network Admin.
	 *
	 * @return void
	 */
	public function add_network_menu() {
		$this->register_page();
	}

	/**
	 * Sets up the screen once WordPress knows the page is being loaded.
	 *
	 * @return void
	 */
	public function load_page() {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Core can't find the title of a page outside the menu, which leaves the browser tab title empty.
		$GLOBALS['title'] = self::title();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Loads the page's styles and scripts.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'ksrdlv-admin', KSRDLV_URL . 'assets/css/admin.css', array(), KSRDLV_VERSION );
		wp_enqueue_script( 'ksrdlv-admin', KSRDLV_URL . 'assets/js/admin.js', array( 'jquery' ), KSRDLV_VERSION, true );

		wp_localize_script(
			'ksrdlv-admin',
			'ksrdlv',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ksrdlv_nonce' ),
				'i18n'     => array(
					'saved'          => __( 'Log file saved.', 'ksr-debug-log-viewer' ),
					'deleted'        => __( 'Log file deleted.', 'ksr-debug-log-viewer' ),
					'refreshed'      => __( 'Log file reloaded.', 'ksr-debug-log-viewer' ),
					'confirm_delete' => __( 'Delete the debug log file? This cannot be undone.', 'ksr-debug-log-viewer' ),
					'confirm_reload' => __( 'You have unsaved changes. Discard them and reload the log?', 'ksr-debug-log-viewer' ),
					'conflict'       => __( 'The log file has changed on disk since it was loaded (new entries may have been written). Overwrite it with your version anyway?', 'ksr-debug-log-viewer' ),
					'unsaved'        => __( 'You have unsaved changes to the debug log.', 'ksr-debug-log-viewer' ),
					'request_failed' => __( 'Request failed. Please try again.', 'ksr-debug-log-viewer' ),
					'enabled'        => __( 'Enabled', 'ksr-debug-log-viewer' ),
					'disabled'       => __( 'Disabled', 'ksr-debug-log-viewer' ),
					'dismiss'        => __( 'Dismiss this notice.', 'ksr-debug-log-viewer' ),
					/* translators: 1: constant name, 2: "Enabled" or "Disabled" */
					'constant_saved' => __( '%1$s is now %2$s. The change takes effect on the next request.', 'ksr-debug-log-viewer' ),
					'no_file'        => __( 'The log file does not exist yet. Type here and save to create it.', 'ksr-debug-log-viewer' ),
					/* translators: %s: maximum size, e.g. "5 MB" */
					'truncated'      => __( 'The log file is too large to edit. Showing the last %s only (read-only).', 'ksr-debug-log-viewer' ),
					'not_found'      => __( 'not found', 'ksr-debug-log-viewer' ),
				),
			)
		);
	}

	/**
	 * Adds the Debug Log link to the admin bar. The plugin only loads in wp-admin, so the link
	 * never shows on the frontend.
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar.
	 * @return void
	 */
	public function add_admin_bar_node( \WP_Admin_Bar $admin_bar ) {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'ksrdlv',
				'title' => esc_html__( 'Debug Log', 'ksr-debug-log-viewer' ),
				'href'  => self::url(),
			)
		);
	}

	/**
	 * Outputs the page.
	 *
	 * @return void
	 */
	public function render() {
		$states               = Config::states();
		$config_path          = Config::path();
		$can_modify           = self::can_modify();
		$can_toggle_constants = $can_modify && Config::is_writable();

		$descriptions = array(
			'WP_DEBUG'         => __( 'Enables debug mode across WordPress.', 'ksr-debug-log-viewer' ),
			'WP_DEBUG_LOG'     => __( 'Saves errors to the debug.log file.', 'ksr-debug-log-viewer' ),
			'WP_DEBUG_DISPLAY' => __( 'Shows errors in the page HTML.', 'ksr-debug-log-viewer' ),
		);
		?>
		<div class="wrap ksrdlv-wrap">
			<h1><?php echo esc_html( self::title() ); ?></h1>

			<div class="ksrdlv-notices"></div>

			<div class="ksrdlv-card ksrdlv-settings">
				<div class="ksrdlv-constants">
					<?php foreach ( $descriptions as $name => $description ) : ?>
						<div class="ksrdlv-constant">
							<label class="ksrdlv-switch">
								<input type="checkbox" class="ksrdlv-toggle" data-constant="<?php echo esc_attr( $name ); ?>" <?php checked( $states[ $name ] ); ?> <?php disabled( ! $can_toggle_constants ); ?>>
								<span class="ksrdlv-slider"></span>
							</label>
							<div class="ksrdlv-constant-info">
								<code><?php echo esc_html( $name ); ?></code>
								<span class="ksrdlv-badge <?php echo $states[ $name ] ? 'is-on' : 'is-off'; ?>" data-badge="<?php echo esc_attr( $name ); ?>">
									<?php echo $states[ $name ] ? esc_html__( 'Enabled', 'ksr-debug-log-viewer' ) : esc_html__( 'Disabled', 'ksr-debug-log-viewer' ); ?>
								</span>
								<p class="description"><?php echo esc_html( $description ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<p class="description ksrdlv-hint">
					<?php esc_html_e( 'WP_DEBUG_LOG and WP_DEBUG_DISPLAY only apply while WP_DEBUG is enabled. Changes are written to wp-config.php and take effect on the next request.', 'ksr-debug-log-viewer' ); ?>
				</p>

				<?php if ( ! $can_modify ) : ?>
					<p class="ksrdlv-warning">
						<?php esc_html_e( 'File modifications are disabled on this site (DISALLOW_FILE_EDIT or DISALLOW_FILE_MODS), so the log is read-only and the constants cannot be changed from here.', 'ksr-debug-log-viewer' ); ?>
					</p>
				<?php elseif ( ! $can_toggle_constants ) : ?>
					<p class="ksrdlv-warning">
						<?php
						printf(
							/* translators: %s: wp-config.php path */
							esc_html__( '%s is not writable, so the constants cannot be changed from here.', 'ksr-debug-log-viewer' ),
							'<code>' . esc_html( '' === $config_path ? 'wp-config.php' : $config_path ) . '</code>'
						);
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
					<code class="ksrdlv-path"><?php echo esc_html( Log_File::path() ); ?></code>
					<span class="ksrdlv-meta"></span>
					<span class="ksrdlv-dirty" hidden>● <?php esc_html_e( 'Unsaved changes', 'ksr-debug-log-viewer' ); ?></span>
				</div>
				<div class="ksrdlv-actions">
					<span class="spinner"></span>
					<button type="button" class="button ksrdlv-refresh"><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh', 'ksr-debug-log-viewer' ); ?></button>
					<?php if ( $can_modify ) : ?>
						<button type="button" class="button button-primary ksrdlv-save" title="Ctrl+S"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save', 'ksr-debug-log-viewer' ); ?></button>
						<button type="button" class="button ksrdlv-delete"><span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete', 'ksr-debug-log-viewer' ); ?></button>
					<?php endif; ?>
				</div>
			</div>

			<div class="ksrdlv-editor">
				<div class="ksrdlv-gutter" aria-hidden="true"></div>
				<textarea class="ksrdlv-textarea" spellcheck="false" wrap="off" autocomplete="off" autocapitalize="off" aria-label="<?php esc_attr_e( 'Debug log contents', 'ksr-debug-log-viewer' ); ?>"></textarea>
			</div>
		</div>
		<?php
	}

	/**
	 * Tells whether the page lives in Network Admin. It does when the plugin is network-activated,
	 * since the log and wp-config.php are shared by all sites.
	 *
	 * @return bool
	 */
	private static function is_network_page(): bool {
		return is_multisite() && is_plugin_active_for_network( plugin_basename( KSRDLV_FILE ) );
	}

	/**
	 * Returns the page URL.
	 *
	 * @return string
	 */
	private static function url(): string {
		$path = 'admin.php?page=' . self::SLUG;
		return self::is_network_page() ? network_admin_url( $path ) : admin_url( $path );
	}

	/**
	 * Returns the page title.
	 *
	 * @return string
	 */
	private static function title(): string {
		return __( 'KSR Debug Log Viewer', 'ksr-debug-log-viewer' );
	}

	/**
	 * Registers the page without a parent, which keeps it out of the sidebar. It's reached from the admin bar instead.
	 *
	 * @return void
	 */
	private function register_page() {
		$hook_suffix = add_submenu_page(
			'',
			self::title(),
			__( 'Debug Log', 'ksr-debug-log-viewer' ),
			self::capability(),
			self::SLUG,
			array( $this, 'render' )
		);

		add_action( 'load-' . $hook_suffix, array( $this, 'load_page' ) );
	}
}

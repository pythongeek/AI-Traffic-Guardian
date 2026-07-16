<?php
/**
 * Admin experience: menu structure, asset loading, page rendering.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Admin
 */
class ATG_Admin {

	/**
	 * Page slug => [ title, view file, capability ].
	 *
	 * @return array
	 */
	public static function pages() {
		return array(
			'atg-dashboard'      => array( __( 'Dashboard', 'ai-traffic-guardian' ), 'dashboard.php' ),
			'atg-policy'         => array( __( 'AI Policy Matrix', 'ai-traffic-guardian' ), 'policy-matrix.php' ),
			'atg-log'            => array( __( 'Traffic Log', 'ai-traffic-guardian' ), 'traffic-log.php' ),
			'atg-allowlist'      => array( __( 'Allowlist', 'ai-traffic-guardian' ), 'allowlist.php' ),
			'atg-protection'     => array( __( 'Forms & Checkout', 'ai-traffic-guardian' ), 'protection.php' ),
			'atg-analytics'      => array( __( 'Analytics Integrity', 'ai-traffic-guardian' ), 'analytics.php' ),
			'atg-seo'            => array( __( 'SEO & AI Discovery', 'ai-traffic-guardian' ), 'seo-tools.php' ),
			'atg-alerts'         => array( __( 'Alerts', 'ai-traffic-guardian' ), 'alerts.php' ),
			'atg-settings'       => array( __( 'Settings', 'ai-traffic-guardian' ), 'settings.php' ),
		);
	}

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . ATG_PLUGIN_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Build the menu tree.
	 */
	public function menu() {
		$alerts = ATG_Plugin::instance()->alerts->open_count();
		$badge  = $alerts ? ' <span class="awaiting-mod">' . (int) $alerts . '</span>' : '';

		add_menu_page(
			__( 'AI Traffic Guardian', 'ai-traffic-guardian' ),
			__( 'Traffic Guardian', 'ai-traffic-guardian' ) . $badge,
			'manage_options',
			'atg-dashboard',
			array( $this, 'render' ),
			'dashicons-shield-alt',
			58
		);
		foreach ( self::pages() as $slug => $page ) {
			add_submenu_page(
				'atg-dashboard',
				$page[0],
				$page[0],
				'manage_options',
				$slug,
				array( $this, 'render' )
			);
		}
	}

	/**
	 * Load CSS/JS only on plugin screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'atg' ) ) {
			return;
		}
		wp_enqueue_style( 'atg-admin', ATG_PLUGIN_URL . 'assets/css/admin.css', array(), ATG_VERSION );
		wp_enqueue_script( 'atg-chart', ATG_PLUGIN_URL . 'assets/js/vendor/chart.umd.min.js', array(), '4.4.3', true );
		wp_enqueue_script( 'atg-admin', ATG_PLUGIN_URL . 'assets/js/admin.js', array( 'atg-chart' ), ATG_VERSION, true );
		wp_localize_script(
			'atg-admin',
			'ATG_ADMIN',
			array(
				'rest'   => esc_url_raw( rest_url( 'atg/v1/' ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'page'   => isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'atg-dashboard', // phpcs:ignore WordPress.Security.NonceVerification
				'i18n'   => array(
					'confirmPanic'  => __( 'This disables ALL blocking and throttling immediately. Continue?', 'ai-traffic-guardian' ),
					'confirmActive' => __( 'Switch to Active enforcement? Block/throttle rules will start affecting real traffic.', 'ai-traffic-guardian' ),
					'saved'         => __( 'Saved.', 'ai-traffic-guardian' ),
					'error'         => __( 'Something went wrong. Please try again.', 'ai-traffic-guardian' ),
				),
			)
		);
	}

	/**
	 * Render the current plugin screen.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ai-traffic-guardian' ) );
		}
		$slug  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'atg-dashboard'; // phpcs:ignore WordPress.Security.NonceVerification
		$pages = self::pages();
		if ( ! isset( $pages[ $slug ] ) ) {
			$slug = 'atg-dashboard';
		}
		$file = ATG_PLUGIN_DIR . 'admin/views/' . $pages[ $slug ][1];

		echo '<div class="wrap atg-wrap" data-atg-page="' . esc_attr( $slug ) . '">';
		$this->render_topbar( $slug );
		if ( file_exists( $file ) ) {
			include $file;
		}
		echo '</div>';
	}

	/**
	 * Shared top bar: mode pill + panic button.
	 *
	 * @param string $slug Current page slug.
	 */
	private function render_topbar( $slug ) {
		$plugin = ATG_Plugin::instance();
		$mode   = $plugin->enforcement_mode();
		$labels = array(
			'shadow' => __( 'Shadow mode — observing only', 'ai-traffic-guardian' ),
			'active' => __( 'Active enforcement', 'ai-traffic-guardian' ),
			'off'    => __( 'Protection paused', 'ai-traffic-guardian' ),
		);
		?>
		<div class="atg-topbar">
			<div class="atg-brand">
				<span class="dashicons dashicons-shield-alt"></span>
				<div>
					<strong><?php esc_html_e( 'AI Traffic Guardian', 'ai-traffic-guardian' ); ?></strong>
					<span class="atg-version">v<?php echo esc_html( ATG_VERSION ); ?></span>
				</div>
			</div>
			<div class="atg-topbar-actions">
				<span class="atg-mode-pill atg-mode-<?php echo esc_attr( $mode ); ?>" data-atg-mode-pill>
					<?php echo esc_html( $labels[ $mode ] ); ?>
				</span>
				<?php if ( 'off' !== $mode ) : ?>
					<button type="button" class="button atg-panic" data-atg-panic>
						<?php esc_html_e( 'Disable all blocking', 'ai-traffic-guardian' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="button button-primary" data-atg-resume data-mode="shadow">
						<?php esc_html_e( 'Resume (shadow mode)', 'ai-traffic-guardian' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Quick links on the plugins list table.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=atg-dashboard' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Dashboard', 'ai-traffic-guardian' ) . '</a>' );
		return $links;
	}
}

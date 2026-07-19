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
			'atg-dashboard'      => array( __( 'Dashboard', 'ai-traffic-guardian' ), 'dashboard.php', 'atg_view_reports' ),
			'atg-policy'         => array( __( 'AI Policy Matrix', 'ai-traffic-guardian' ), 'policy-matrix.php', 'manage_options' ),
			'atg-log'            => array( __( 'Traffic Log', 'ai-traffic-guardian' ), 'traffic-log.php', 'atg_view_reports' ),
			'atg-allowlist'      => array( __( 'Allowlist', 'ai-traffic-guardian' ), 'allowlist.php', 'manage_options' ),
			'atg-protection'     => array( __( 'Forms & Checkout', 'ai-traffic-guardian' ), 'protection.php', 'manage_options' ),
			'atg-analytics'      => array( __( 'Analytics Integrity', 'ai-traffic-guardian' ), 'analytics.php', 'manage_options' ),
			'atg-seo'            => array( __( 'SEO & AI Discovery', 'ai-traffic-guardian' ), 'seo-tools.php', 'manage_options' ),
			'atg-audit'          => array( __( 'Bot Security Audit', 'ai-traffic-guardian' ), 'bot-audit.php', 'manage_options' ),
			'atg-alerts'         => array( __( 'Alerts', 'ai-traffic-guardian' ), 'alerts.php', 'atg_view_reports' ),
			'atg-settings'       => array( __( 'Settings', 'ai-traffic-guardian' ), 'settings.php', 'manage_options' ),
			'atg-edge'           => array( __( 'Edge Setup', 'ai-traffic-guardian' ), 'edge-setup.php', 'manage_options' ),
			'atg-report'         => array( __( 'Bot Audit Report', 'ai-traffic-guardian' ), 'report.php', 'manage_options' ),
		);
	}

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'admin_init', array( $this, 'dismiss_conflict_notice' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'network_admin_menu', array( $this, 'network_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . ATG_PLUGIN_BASENAME, array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'conflict_notices' ) );
		add_action( 'admin_notices', array( $this, 'show_shadow_report_notice' ) );
	}

	/**
	 * Build the menu tree.
	 */
	public function menu() {
		$alerts = ATG_Plugin::instance()->alerts->open_count();
		$badge  = $alerts ? ' <span class="awaiting-mod">' . (int) $alerts . '</span>' : '';

		$is_pro     = ATG_Licensing::atg_is_pro();
		$page_title = ( $is_pro && defined( 'ATG_BRAND_NAME' ) ) ? ATG_BRAND_NAME : __( 'Bot Shield Pro', 'ai-traffic-guardian' );
		$menu_title = ( $is_pro && defined( 'ATG_BRAND_NAME' ) ) ? ATG_BRAND_NAME : __( 'Bot Shield Pro', 'ai-traffic-guardian' );
		$icon       = ( $is_pro && defined( 'ATG_BRAND_ICON' ) ) ? ATG_BRAND_ICON : 'dashicons-shield-alt';

		add_menu_page(
			$page_title,
			$menu_title . $badge,
			'atg_view_reports',
			'atg-dashboard',
			array( $this, 'render' ),
			$icon,
			58
		);
		foreach ( self::pages() as $slug => $page ) {
			$cap = isset( $page[2] ) ? $page[2] : 'manage_options';
			add_submenu_page(
				'atg-dashboard',
				$page[0],
				$page[0],
				$cap,
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

		// Inject inline branding color overrides when config/branding.php defines them.
		$overrides = '';
		$is_pro    = ATG_Licensing::atg_is_pro();
		if ( $is_pro && defined( 'ATG_BRAND_COLOR_PRIMARY' ) ) {
			$overrides .= '--atg-brand:' . esc_attr( ATG_BRAND_COLOR_PRIMARY ) . ';';
		}
		if ( $is_pro && defined( 'ATG_BRAND_COLOR_DARK' ) ) {
			$overrides .= '--atg-topbar-start:' . esc_attr( ATG_BRAND_COLOR_DARK ) . ';';
		}
		if ( $overrides ) {
			wp_add_inline_style( 'atg-admin', '.atg-wrap{' . $overrides . '}' );
		}
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
		$is_pro = ATG_Licensing::atg_is_pro();
		if ( ! $is_pro || ! defined( 'ATG_BRAND_HIDE_CREDITS' ) || ! ATG_BRAND_HIDE_CREDITS ) {
			echo '<p class="atg-footer-credit">' . esc_html__( 'Powered by Bot Shield Pro', 'ai-traffic-guardian' ) . '</p>';
		}
		do_action( 'atg_dashboard_footer' );
		echo '</div>';
	}

	/**
	 * Shared top bar: mode pill + panic button.
	 *
	 * @param string $slug Current page slug.
	 */
	private function render_topbar( $slug ) {
		$plugin     = ATG_Plugin::instance();
		$mode       = $plugin->enforcement_mode();
		$is_pro     = ATG_Licensing::atg_is_pro();
		$brand_name = ( $is_pro && defined( 'ATG_BRAND_NAME' ) ) ? ATG_BRAND_NAME : __( 'Bot Shield Pro', 'ai-traffic-guardian' );
		$logo_url   = ( $is_pro && defined( 'ATG_BRAND_LOGO_URL' ) ) ? ATG_BRAND_LOGO_URL : '';
		$icon_class = ( $is_pro && defined( 'ATG_BRAND_ICON' ) ) ? ATG_BRAND_ICON : 'dashicons-shield-alt';
		$labels     = array(
			'shadow' => __( 'Shadow mode — observing only', 'ai-traffic-guardian' ),
			'active' => __( 'Active enforcement', 'ai-traffic-guardian' ),
			'off'    => __( 'Protection paused', 'ai-traffic-guardian' ),
		);
		?>
		<div class="atg-topbar">
			<div class="atg-brand">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" height="32" alt="<?php echo esc_attr( $brand_name ); ?>" />
				<?php else : ?>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #6366f1; filter: drop-shadow(0 0 8px rgba(99, 102, 241, 0.4)); flex-shrink: 0;">
						<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" fill="url(#atgLogoGrad)" />
						<polyline points="22 4 12 14.01 9 11.01" stroke="#ffffff" stroke-width="2.5" />
						<defs>
							<linearGradient id="atgLogoGrad" x1="0%" y1="0%" x2="100%" y2="100%">
								<stop offset="0%" stop-color="#4f46e5" />
								<stop offset="100%" stop-color="#06b6d4" />
							</linearGradient>
						</defs>
					</svg>
				<?php endif; ?>
				<div>
					<strong><?php echo esc_html( $brand_name ); ?></strong>
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

	/**
	 * Plugin row meta: support & docs links.
	 *
	 * @param array  $links Existing meta links.
	 * @param string $file  Plugin basename.
	 * @return array
	 */
	public function row_meta( $links, $file ) {
		if ( strpos( $file, 'ai-traffic-guardian.php' ) === false ) {
			return $links;
		}
		$is_pro = ATG_Licensing::atg_is_pro();
		if ( $is_pro && defined( 'ATG_BRAND_SUPPORT_URL' ) && ATG_BRAND_SUPPORT_URL ) {
			$links[] = '<a href="' . esc_url( ATG_BRAND_SUPPORT_URL ) . '">' . esc_html__( 'Support', 'ai-traffic-guardian' ) . '</a>';
		}
		if ( $is_pro && defined( 'ATG_BRAND_DOCS_URL' ) && ATG_BRAND_DOCS_URL ) {
			$links[] = '<a href="' . esc_url( ATG_BRAND_DOCS_URL ) . '">' . esc_html__( 'Documentation', 'ai-traffic-guardian' ) . '</a>';
		}
		return $links;
	}

	/**
	 * Register the network menu.
	 */
	public function network_menu() {
		add_menu_page(
			__( 'Bot Shield Pro Network Settings', 'ai-traffic-guardian' ),
			__( 'Bot Shield Pro', 'ai-traffic-guardian' ),
			'manage_network_options',
			'atg-network-settings',
			array( $this, 'render_network_page' ),
			'dashicons-shield-alt',
			25
		);
	}

	/**
	 * Render network options screen.
	 */
	public function render_network_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ai-traffic-guardian' ) );
		}
		if ( isset( $_POST['submit'] ) && check_admin_referer( 'atg-network-settings' ) ) {
			$raw_post = isset( $_POST['settings'] ) ? map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' ) : array();
			$settings = is_array( $raw_post ) ? $raw_post : array();
			$defaults = ATG_Plugin::default_settings();
			$clean    = array();
			foreach ( $defaults as $key => $default ) {
				if ( isset( $settings[ $key ] ) ) {
					if ( is_bool( $default ) ) {
						$clean[ $key ] = '1' === $settings[ $key ];
					} elseif ( is_int( $default ) ) {
						$clean[ $key ] = (int) $settings[ $key ];
					} else {
						$clean[ $key ] = sanitize_text_field( $settings[ $key ] );
					}
				} else {
					if ( is_bool( $default ) ) {
						$clean[ $key ] = false;
					}
				}
			}
			update_site_option( 'atg_network_settings', $clean );
			echo '<div class="updated"><p>' . esc_html__( 'Network baseline settings saved.', 'ai-traffic-guardian' ) . '</p></div>';
		}

		$settings = get_site_option( 'atg_network_settings', array() );
		$defaults = ATG_Plugin::default_settings();
		$settings = wp_parse_args( $settings, $defaults );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bot Shield Pro — Network Baseline Settings', 'ai-traffic-guardian' ); ?></h1>
			<form method="post" action="">
				<?php wp_nonce_field( 'atg-network-settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Default Enforcement Mode', 'ai-traffic-guardian' ); ?></th>
						<td>
							<select name="settings[enforcement]">
								<option value="shadow" <?php selected( $settings['enforcement'], 'shadow' ); ?>><?php esc_html_e( 'Shadow Mode', 'ai-traffic-guardian' ); ?></option>
								<option value="active" <?php selected( $settings['enforcement'], 'active' ); ?>><?php esc_html_e( 'Active Enforcement', 'ai-traffic-guardian' ); ?></option>
								<option value="off" <?php selected( $settings['enforcement'], 'off' ); ?>><?php esc_html_e( 'Off (Paused)', 'ai-traffic-guardian' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auth Bypass', 'ai-traffic-guardian' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="settings[auth_bypass]" value="1" <?php checked( $settings['auth_bypass'] ); ?> />
								<?php esc_html_e( 'Authenticated users bypass bot classification', 'ai-traffic-guardian' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rate Limiting', 'ai-traffic-guardian' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="settings[rate_enabled]" value="1" <?php checked( $settings['rate_enabled'] ); ?> />
								<?php esc_html_e( 'Enable progressive rate limiting by default', 'ai-traffic-guardian' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Hash IPs', 'ai-traffic-guardian' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="settings[hash_ips]" value="1" <?php checked( $settings['hash_ips'] ); ?> />
								<?php esc_html_e( 'Hash IPs in logs (GDPR compliance)', 'ai-traffic-guardian' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Network Settings', 'ai-traffic-guardian' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render dismissible admin notices for plugin conflicts.
	 */
	public function conflict_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$env = ATG_Compat::environment();
		$user_id   = get_current_user_id();
		$dismissed = get_user_meta( $user_id, 'atg_dismissed_conflicts', true );
		if ( ! is_array( $dismissed ) ) {
			$dismissed = array();
		}

		// Detect high DB rate-limiter load.
		if ( ! wp_using_ext_object_cache() && ! in_array( 'no_object_cache', $dismissed, true ) ) {
			global $wpdb;
			$day     = gmdate( 'Y-m-d' );
			$hits    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(hits) FROM ' . ATG_DB::table( 'stats' ) . ' WHERE day = %s', $day ) );
			$minutes = ( (int) gmdate( 'H' ) * 60 ) + (int) gmdate( 'i' );
			if ( $minutes < 60 ) {
				$yesterday = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
				$hits      = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(hits) FROM ' . ATG_DB::table( 'stats' ) . ' WHERE day IN (%s, %s)', $yesterday, $day ) );
				$minutes   = 1440;
			}
			$rpm = $minutes > 0 ? ( $hits / $minutes ) : 0;

			if ( $rpm > 500 ) {
				$dismiss_url = wp_nonce_url( add_query_arg( 'atg_dismiss_conflict', 'no_object_cache' ), 'atg_dismiss_conflict' );
				echo '<div class="notice notice-warning is-dismissible" style="position:relative;">';
				echo '<p><strong>' . esc_html__( 'Bot Shield Pro warning: High database rate-limiting load', 'ai-traffic-guardian' ) . '</strong></p>';
				/* translators: %d requests per minute */
				echo '<p>' . sprintf( esc_html__( 'Your site is serving approximately %d rpm but is not using a persistent object cache. Rate limiter transients are causing heavy database write load. Action: Install/configure Redis or Memcached.', 'ai-traffic-guardian' ), (int) $rpm ) . '</p>';
				echo '<p><a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss this warning', 'ai-traffic-guardian' ) . '</a></p>';
				echo '</div>';
			}
		}

		if ( empty( $env['conflicts'] ) ) {
			return;
		}

		foreach ( $env['conflicts'] as $conflict ) {
			$plugin_name = is_array( $conflict ) ? $conflict['plugin'] : $conflict;
			if ( in_array( $plugin_name, $dismissed, true ) ) {
				continue;
			}

			$remediation = '';
			if ( is_array( $conflict ) && ! empty( $conflict['remediation'] ) ) {
				$remediation = $conflict['issue'] . ' — ' . $conflict['remediation'];
			}

			$is_pro      = ATG_Licensing::atg_is_pro();
			$brand_name  = ( $is_pro && defined( 'ATG_BRAND_NAME' ) ) ? ATG_BRAND_NAME : __( 'Bot Shield Pro', 'ai-traffic-guardian' );
			$dismiss_url = wp_nonce_url( add_query_arg( 'atg_dismiss_conflict', $plugin_name ), 'atg_dismiss_conflict' );

			echo '<div class="notice notice-warning is-dismissible" style="position:relative;">';
			/* translators: 1: brand name, 2: conflicting plugin */
			echo '<p><strong>' . sprintf( esc_html__( '%1$s conflict detected: %2$s', 'ai-traffic-guardian' ), esc_html( $brand_name ), esc_html( $plugin_name ) ) . '</strong></p>';
			if ( $remediation ) {
				echo '<p>' . esc_html( $remediation ) . '</p>';
			}
			echo '<p><a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss this warning', 'ai-traffic-guardian' ) . '</a></p>';
			echo '</div>';
		}
	}

	/**
	 * Render 7-day shadow report completion notice banner.
	 */
	public function show_shadow_report_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( 'atg_shadow_report_ready' ) ) {
			$report_url = admin_url( 'admin.php?page=atg-report' );
			echo '<div class="notice notice-info is-dismissible">';
			echo '<p><strong>' . esc_html__( 'Your 7-Day Bot Audit Report is ready!', 'ai-traffic-guardian' ) . '</strong> ' . sprintf( /* translators: %s link */ esc_html__( 'Analyze AI crawler overhead, bandwidth waste, and traffic composition in your %s.', 'ai-traffic-guardian' ), '<a href="' . esc_url( $report_url ) . '">' . esc_html__( 'Bot Audit Report', 'ai-traffic-guardian' ) . '</a>' ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Handle dismissing conflict notices via admin_init.
	 */
	public function dismiss_conflict_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_GET['atg_dismiss_conflict'] ) && check_admin_referer( 'atg_dismiss_conflict' ) ) {
			$user_id   = get_current_user_id();
			$dismissed = get_user_meta( $user_id, 'atg_dismissed_conflicts', true );
			if ( ! is_array( $dismissed ) ) {
				$dismissed = array();
			}
			$to_dismiss = sanitize_text_field( wp_unslash( $_GET['atg_dismiss_conflict'] ) );
			if ( ! in_array( $to_dismiss, $dismissed, true ) ) {
				$dismissed[] = $to_dismiss;
				update_user_meta( $user_id, 'atg_dismissed_conflicts', $dismissed );
			}
			wp_safe_redirect( remove_query_arg( array( 'atg_dismiss_conflict', '_wpnonce' ) ) );
			exit;
		}
	}
}


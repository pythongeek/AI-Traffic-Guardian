<?php
/**
 * Dashboard view with Simple vs Advanced modes and licensing settings.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$preset = get_option( 'atg_preset', 'publisher' );
$enforcement = ATG_Plugin::instance()->enforcement_mode();
$is_pro = ATG_Licensing::is_pro();
$license_key = get_option( 'atg_license_key', '' );

if ( isset( $_POST['atg_license_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['atg_license_nonce'] ), 'atg_license_save' ) ) {
	$key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';
	ATG_Licensing::update_license( $key );
	wp_safe_redirect( admin_url( 'admin.php?page=atg-dashboard' ) );
	exit;
}
?>
<div class="atg-page" id="atg-dashboard">

	<!-- ── Page explanation ───────────────────────────────────────────────── -->
	<div class="atg-explainer">
		<div class="atg-explainer-icon"><span class="dashicons dashicons-visibility"></span></div>
		<div class="atg-explainer-text">
			<h2><?php esc_html_e( 'Your control room', 'ai-traffic-guardian' ); ?></h2>
			<p><?php esc_html_e( 'This is like a security camera screen. Every visitor to your website — real person or computer program (bot) — shows up here. The numbers tell you how many real people visited vs. how many were bots, and what happened to them. Nothing on this page is made-up: all data comes from your actual traffic.', 'ai-traffic-guardian' ); ?></p>
		</div>
	</div>

	<!-- Header and Mode Toggles -->
	<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
		<nav class="nav-tab-wrapper atg-tabs" style="margin-bottom: 0; border: none; display: flex; gap: 15px;">
			<a href="#overview" class="nav-tab nav-tab-active" data-tab="overview" style="font-weight: 600; font-size: 14px; text-decoration: none; padding: 5px 10px; border-bottom: 2px solid transparent; color: #64748b; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.color='#0f172a'" onmouseout="if(!this.classList.contains('nav-tab-active'))this.style.color='#64748b'"><?php esc_html_e( 'Overview', 'ai-traffic-guardian' ); ?></a>
			<a href="#cost" class="nav-tab" data-tab="cost" style="font-weight: 600; font-size: 14px; text-decoration: none; padding: 5px 10px; border-bottom: 2px solid transparent; color: #64748b; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.color='#0f172a'" onmouseout="if(!this.classList.contains('nav-tab-active'))this.style.color='#64748b'"><?php esc_html_e( 'Cost & Bandwidth', 'ai-traffic-guardian' ); ?></a>
		</nav>

		<?php $preferred_view = get_user_meta( get_current_user_id(), 'atg_dashboard_view', true ) ?: 'simple'; ?>
		<div style="display:flex; align-items:center; gap:10px;">
			<label style="font-weight:600; font-size:13px; color:#475569;">
				<input type="checkbox" id="atg-view-mode-toggle" onchange="toggleViewMode(this.checked)" <?php checked( 'advanced', $preferred_view ); ?> /> <?php esc_html_e( 'Advanced View', 'ai-traffic-guardian' ); ?>
			</label>
		</div>
	</div>

	<!-- Simple View Wrapper -->
	<div id="atg-simple-view-wrapper" style="display: <?php echo 'simple' === $preferred_view ? 'block' : 'none'; ?>;">
		<div class="atg-card" style="margin-bottom: 20px; border-left: 6px solid <?php echo 'shadow' === $enforcement ? '#f97316' : ( 'active' === $enforcement ? '#16a34a' : '#ef4444' ); ?>;">
			<div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0;">
				<div>
					<h3 style="margin:0; font-size:18px; font-weight:700; color:#1e293b;">
						<?php
						if ( 'shadow' === $enforcement ) {
							esc_html_e( 'Status: Observing & Shadowing', 'ai-traffic-guardian' );
						} elseif ( 'active' === $enforcement ) {
							esc_html_e( 'Status: Fully Protected', 'ai-traffic-guardian' );
						} else {
							esc_html_e( 'Status: Protection Disabled', 'ai-traffic-guardian' );
						}
						?>
					</h3>
					<p class="description" style="margin:5px 0 0 0;">
						<?php printf( esc_html__( 'Current Active Preset: %s', 'ai-traffic-guardian' ), '<strong>' . esc_html( ucfirst( $preset ) ) . '</strong>' ); ?>
					</p>
				</div>
				<div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=atg-policy' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Adjust Preset', 'ai-traffic-guardian' ); ?></a>
				</div>
			</div>
		</div>
	</div>

	<div id="atg-dashboard-overview-tab">
		<div class="atg-shadow-banner" data-atg-shadow-banner hidden>
			<div class="atg-shadow-icon"><span class="dashicons dashicons-visibility"></span></div>
			<div class="atg-shadow-text">
				<strong><?php esc_html_e( 'Shadow mode is on — watching, not blocking', 'ai-traffic-guardian' ); ?></strong>
				<p><?php esc_html_e( 'Every decision in the charts below is being recorded but nothing is being blocked yet. This is intentional: review what would have happened for a few days, check the Traffic Log for any real visitors that look like they would have been wrongly flagged, then go live when you\'re confident.', 'ai-traffic-guardian' ); ?></p>
				<p class="atg-shadow-countdown" data-atg-shadow-countdown></p>
			</div>
			<div class="atg-shadow-actions">
				<button class="button button-primary button-hero" data-atg-resume data-mode="active">
					<?php esc_html_e( 'Go live: start enforcing', 'ai-traffic-guardian' ); ?>
				</button>
			</div>
		</div>

		<div class="atg-toolbar">
			<div class="atg-range" data-atg-range>
				<button data-days="1"><?php esc_html_e( '24h', 'ai-traffic-guardian' ); ?></button>
				<button data-days="7" class="is-active"><?php esc_html_e( '7 days', 'ai-traffic-guardian' ); ?></button>
				<button data-days="30"><?php esc_html_e( '30 days', 'ai-traffic-guardian' ); ?></button>
			</div>
			<button class="button" data-atg-refresh><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh', 'ai-traffic-guardian' ); ?></button>
		</div>

		<!-- Licensing Widget (Always available to enter Pro) -->
		<div class="atg-card" style="margin-bottom: 20px; border-left: 6px solid <?php echo $is_pro ? '#10b981' : '#cbd5e1'; ?>;">
			<div class="atg-card-head">
				<h2><?php esc_html_e( 'Bot Shield Pro License', 'ai-traffic-guardian' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Enter your premium license key starting with BSPRO- to unlock advanced vendors database, white-label settings, and edge integrations.', 'ai-traffic-guardian' ); ?></p>
			</div>
			<form method="POST" style="margin-top:15px; display:flex; gap:10px; align-items:center;">
				<?php wp_nonce_field( 'atg_license_save', 'atg_license_nonce' ); ?>
				<input type="password" name="license_key" value="<?php echo esc_attr( $license_key ); ?>" placeholder="e.g. BSPRO-XXXX-XXXX" style="width:300px; padding:6px; border-radius:4px; border:1px solid #cbd5e1;" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Save License Key', 'ai-traffic-guardian' ); ?></button>
				<span style="font-weight:600; color:<?php echo $is_pro ? '#10b981' : '#64748b'; ?>;">
					<?php echo $is_pro ? esc_html__( 'Pro Activated', 'ai-traffic-guardian' ) : esc_html__( 'Free Tier Active', 'ai-traffic-guardian' ); ?>
				</span>
			</form>
		</div>

		<div class="atg-kpis" data-atg-kpis>
			<div class="atg-kpi" title="<?php esc_attr_e( 'All requests seen by WordPress in the selected period — bots and humans combined.', 'ai-traffic-guardian' ); ?>">
				<span class="atg-kpi-label"><?php esc_html_e( 'Total requests', 'ai-traffic-guardian' ); ?></span>
				<span class="atg-kpi-value" data-kpi="total">—</span>
			</div>
			<div class="atg-kpi" title="<?php esc_attr_e( 'Percentage of traffic that was identified as a bot rather than a real human visitor.', 'ai-traffic-guardian' ); ?>">
				<span class="atg-kpi-label"><?php esc_html_e( 'Bot share', 'ai-traffic-guardian' ); ?></span>
				<span class="atg-kpi-value" data-kpi="bot_share">—</span>
			</div>
			<div class="atg-kpi atg-kpi-block" title="<?php esc_attr_e( 'Requests that were turned away with a 403 error. In shadow mode, this shows what WOULD have been blocked.', 'ai-traffic-guardian' ); ?>">
				<span class="atg-kpi-label"><?php esc_html_e( 'Blocked', 'ai-traffic-guardian' ); ?></span>
				<span class="atg-kpi-value" data-kpi="blocked">—</span>
			</div>
			<div class="atg-kpi atg-kpi-throttle" title="<?php esc_attr_e( 'Requests slowed down with a small delay. In shadow mode, this shows what WOULD have been throttled.', 'ai-traffic-guardian' ); ?>">
				<span class="atg-kpi-label"><?php esc_html_e( 'Throttled', 'ai-traffic-guardian' ); ?></span>
				<span class="atg-kpi-value" data-kpi="throttled">—</span>
			</div>
			<div class="atg-kpi atg-kpi-human" title="<?php esc_attr_e( 'Requests from real people, logged-in users, and trusted services. These are always allowed through.', 'ai-traffic-guardian' ); ?>">
				<span class="atg-kpi-label"><?php esc_html_e( 'Human traffic', 'ai-traffic-guardian' ); ?></span>
				<span class="atg-kpi-value" data-kpi="human_eq">—</span>
			</div>
			<div class="atg-kpi atg-kpi-alert" title="<?php esc_attr_e( 'New bot signatures that have appeared in your traffic and need your attention.', 'ai-traffic-guardian' ); ?>">
				<span class="atg-kpi-label"><?php esc_html_e( 'Open alerts', 'ai-traffic-guardian' ); ?></span>
				<span class="atg-kpi-value" data-kpi="alerts">—</span>
			</div>
		</div>

		<!-- Advanced View Only Section -->
		<div id="atg-advanced-view-section" style="display: <?php echo 'advanced' === $preferred_view ? 'block' : 'none'; ?>;">
			<div class="atg-grid-2">
				<div class="atg-card">
					<div class="atg-card-head">
						<h2><?php esc_html_e( 'Traffic over time', 'ai-traffic-guardian' ); ?></h2>
						<span class="atg-help-tip" title="<?php esc_attr_e( 'Stacked lines show each type of visitor per day. Green areas are real people. Blue/orange/red areas are different kinds of bots. Taller bars mean more traffic.', 'ai-traffic-guardian' ); ?>">?</span>
					</div>
					<div class="atg-chart-empty-state" id="atg-chart-series-empty" hidden>
						<p><?php esc_html_e( 'No traffic data yet for this time range. This chart will fill in as your site receives visitors.', 'ai-traffic-guardian' ); ?></p>
					</div>
					<div class="atg-chart-wrap" id="atg-chart-series-wrap"><canvas id="atg-chart-series"></canvas></div>
				</div>
				<div class="atg-card">
					<div class="atg-card-head">
						<h2><?php esc_html_e( 'Bot traffic by category', 'ai-traffic-guardian' ); ?></h2>
						<span class="atg-help-tip" title="<?php esc_attr_e( 'Each slice shows what KIND of bot visited. Search engines help people find you (usually welcome). AI training crawlers copy your content for AI models. Scrapers are usually unwanted.', 'ai-traffic-guardian' ); ?>">?</span>
					</div>
					<div class="atg-chart-empty-state" id="atg-chart-purpose-empty" hidden>
						<p><?php esc_html_e( 'No bot categories to display yet. Bot categories will appear here once classified traffic is recorded.', 'ai-traffic-guardian' ); ?></p>
					</div>
					<div class="atg-chart-wrap" id="atg-chart-purpose-wrap"><canvas id="atg-chart-purpose"></canvas></div>
				</div>
			</div>

			<div class="atg-grid-2">
				<div class="atg-card">
					<div class="atg-card-head">
						<h2><?php esc_html_e( 'Top AI vendors in your traffic', 'ai-traffic-guardian' ); ?></h2>
						<span class="atg-help-tip" title="<?php esc_attr_e( 'Shows which AI companies\' bots are visiting most often. OpenAI, Anthropic, Google etc. all have bots that crawl the web.', 'ai-traffic-guardian' ); ?>">?</span>
					</div>
					<table class="atg-table" data-atg-vendors>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Vendor', 'ai-traffic-guardian' ); ?></th>
								<th><?php esc_html_e( 'Requests', 'ai-traffic-guardian' ); ?></th>
								<th><?php esc_html_e( 'Share', 'ai-traffic-guardian' ); ?></th>
							</tr>
						</thead>
						<tbody><tr><td colspan="3" class="atg-empty"><?php esc_html_e( 'Loading…', 'ai-traffic-guardian' ); ?></td></tr></tbody>
					</table>
				</div>

				<div class="atg-card">
					<div class="atg-card-head">
						<h2><?php esc_html_e( 'Bot traffic Origins (Country)', 'ai-traffic-guardian' ); ?></h2>
					</div>
					<table class="atg-table" data-atg-countries>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Country', 'ai-traffic-guardian' ); ?></th>
								<th><?php esc_html_e( 'Requests', 'ai-traffic-guardian' ); ?></th>
								<th><?php esc_html_e( 'Share', 'ai-traffic-guardian' ); ?></th>
							</tr>
						</thead>
						<tbody><tr><td colspan="3" class="atg-empty"><?php esc_html_e( 'Loading…', 'ai-traffic-guardian' ); ?></td></tr></tbody>
					</table>
				</div>
			</div>

			<div class="atg-card" style="margin-top:20px; margin-bottom:20px;">
				<div class="atg-card-head">
					<h2><?php esc_html_e( 'Latest decisions', 'ai-traffic-guardian' ); ?></h2>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=atg-log' ) ); ?>" class="atg-link"><?php esc_html_e( 'View full log', 'ai-traffic-guardian' ); ?></a>
				</div>
				<table class="atg-table" data-atg-recent>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'ai-traffic-guardian' ); ?></th>
							<th><?php esc_html_e( 'Bot', 'ai-traffic-guardian' ); ?></th>
							<th><?php esc_html_e( 'Verified', 'ai-traffic-guardian' ); ?></th>
							<th><?php esc_html_e( 'Decision', 'ai-traffic-guardian' ); ?></th>
						</tr>
					</thead>
					<tbody><tr><td colspan="4" class="atg-empty"><?php esc_html_e( 'Loading…', 'ai-traffic-guardian' ); ?></td></tr></tbody>
				</table>
			</div>
		</div>

		<div class="atg-card atg-next-steps" style="margin-top:20px;">
			<div class="atg-card-head"><h2><?php esc_html_e( 'Your go-live checklist', 'ai-traffic-guardian' ); ?></h2></div>
			<ul>
				<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Let shadow mode run 3–7 days so real bot patterns emerge in your Traffic Log.', 'ai-traffic-guardian' ); ?></li>
				<li><span class="dashicons dashicons-yes-alt"></span> <?php printf( esc_html__( 'In the %s, filter by Classification = Humans and confirm no real visitors are being flagged.', 'ai-traffic-guardian' ), '<a href="' . esc_url( admin_url( 'admin.php?page=atg-log' ) ) . '">' . esc_html__( 'Traffic Log', 'ai-traffic-guardian' ) . '</a>' ); // phpcs:ignore ?></li>
				<li><span class="dashicons dashicons-yes-alt"></span> <?php printf( esc_html__( 'Tune per-vendor rules in the %s — for example allow OpenAI Search but throttle OpenAI Training.', 'ai-traffic-guardian' ), '<a href="' . esc_url( admin_url( 'admin.php?page=atg-policy' ) ) . '">' . esc_html__( 'AI Policy Matrix', 'ai-traffic-guardian' ) . '</a>' ); // phpcs:ignore ?></li>
				<li><span class="dashicons dashicons-yes-alt"></span> <?php printf( esc_html__( 'Add your office IP and monitoring service to the %s so they are never blocked.', 'ai-traffic-guardian' ), '<a href="' . esc_url( admin_url( 'admin.php?page=atg-allowlist' ) ) . '">' . esc_html__( 'Allowlist', 'ai-traffic-guardian' ) . '</a>' ); // phpcs:ignore ?></li>
				<li><span class="dashicons dashicons-yes-alt"></span> <?php printf( esc_html__( 'Run the %s to check for any gaps in your setup before going live.', 'ai-traffic-guardian' ), '<a href="' . esc_url( admin_url( 'admin.php?page=atg-audit' ) ) . '">' . esc_html__( 'Bot Security Audit', 'ai-traffic-guardian' ) . '</a>' ); // phpcs:ignore ?></li>
			</ul>
		</div>
	</div>

	<div id="atg-dashboard-cost-tab" style="display:none;">
		<div class="atg-card" style="margin-bottom: 20px;">
			<div class="atg-card-head">
				<h2><?php esc_html_e( 'Cost & Bandwidth Estimation', 'ai-traffic-guardian' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Estimate the financial and server overhead savings by blocking automated scraping traffic.', 'ai-traffic-guardian' ); ?></p>
			</div>
			<div style="display: flex; gap: 20px; align-items: center; margin-bottom: 20px;">
				<label>
					<span><strong><?php esc_html_e( 'Estimated Server Cost per 10k Requests ($):', 'ai-traffic-guardian' ); ?></strong></span>
					<input type="number" id="atg-cost-multiplier" value="0.05" step="0.01" min="0.01" style="width: 80px; padding: 5px; border-radius: 4px; border: 1px solid #cbd5e1;" />
				</label>
				<label>
					<span><strong><?php esc_html_e( 'Average Size per Request (KB):', 'ai-traffic-guardian' ); ?></strong></span>
					<input type="number" id="atg-bandwidth-multiplier" value="150" step="10" min="10" style="width: 80px; padding: 5px; border-radius: 4px; border: 1px solid #cbd5e1;" />
				</label>
				<button type="button" class="button button-primary" id="atg-recalculate-cost-btn" style="margin-top: 18px;"><?php esc_html_e( 'Recalculate', 'ai-traffic-guardian' ); ?></button>
			</div>
			<div class="atg-kpis" style="margin-top:20px;">
				<div class="atg-kpi">
					<span class="atg-kpi-label"><?php esc_html_e( 'Total Bot Requests', 'ai-traffic-guardian' ); ?></span>
					<span class="atg-kpi-value" id="atg-cost-total-bots">—</span>
				</div>
				<div class="atg-kpi atg-kpi-block">
					<span class="atg-kpi-label"><?php esc_html_e( 'Blocked Requests', 'ai-traffic-guardian' ); ?></span>
					<span class="atg-kpi-value" id="atg-cost-blocked-bots">—</span>
				</div>
				<div class="atg-kpi">
					<span class="atg-kpi-label"><?php esc_html_e( 'Estimated Server Cost Savings', 'ai-traffic-guardian' ); ?></span>
					<span class="atg-kpi-value" id="atg-cost-savings">—</span>
				</div>
				<div class="atg-kpi">
					<span class="atg-kpi-label"><?php esc_html_e( 'Estimated Bandwidth Saved', 'ai-traffic-guardian' ); ?></span>
					<span class="atg-kpi-value" id="atg-bandwidth-saved">—</span>
				</div>
			</div>
		</div>
		<div class="atg-card">
			<div class="atg-card-head">
				<h2><?php esc_html_e( 'Forecasting (Next 30 Days)', 'ai-traffic-guardian' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Linear projection of bot traffic based on the selected period.', 'ai-traffic-guardian' ); ?></p>
			</div>
			<div class="atg-kpis">
				<div class="atg-kpi">
					<span class="atg-kpi-label"><?php esc_html_e( 'Projected Monthly Bot Requests', 'ai-traffic-guardian' ); ?></span>
					<span class="atg-kpi-value" id="atg-projected-requests">—</span>
				</div>
				<div class="atg-kpi">
					<span class="atg-kpi-label"><?php esc_html_e( 'Projected Monthly Server Cost', 'ai-traffic-guardian' ); ?></span>
					<span class="atg-kpi-value" id="atg-projected-cost">—</span>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
function toggleViewMode(isAdvanced) {
	var advSection = document.getElementById('atg-advanced-view-section');
	var simpleSection = document.getElementById('atg-simple-view-wrapper');
	var val = isAdvanced ? 'advanced' : 'simple';

	if (isAdvanced) {
		if(advSection) advSection.style.display = 'block';
		if(simpleSection) simpleSection.style.display = 'none';
	} else {
		if(advSection) advSection.style.display = 'none';
		if(simpleSection) simpleSection.style.display = 'block';
	}

	localStorage.setItem('atg_view_mode', val);

	// Persist on the server via REST API
	fetch('<?php echo esc_url( rest_url( "atg/v1/dashboard/view" ) ); ?>', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( "wp_rest" ) ); ?>'
		},
		body: JSON.stringify({ view: val })
	}).catch(function(err) {
		console.error('Failed to save dashboard view preference', err);
	});
}

// Restore user view preference.
document.addEventListener('DOMContentLoaded', function() {
	var preferredMode = '<?php echo esc_js( $preferred_view ); ?>' || localStorage.getItem('atg_view_mode') || 'simple';
	var checkbox = document.getElementById('atg-view-mode-toggle');
	if (checkbox) {
		checkbox.checked = (preferredMode === 'advanced');
		// Initial state is set server-side via inline CSS display properties
	}
});
</script>

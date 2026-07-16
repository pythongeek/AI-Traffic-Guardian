<?php
/**
 * Dashboard view.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="atg-page" id="atg-dashboard">

	<div class="atg-shadow-banner" data-atg-shadow-banner hidden>
		<div class="atg-shadow-icon"><span class="dashicons dashicons-visibility"></span></div>
		<div class="atg-shadow-text">
			<strong><?php esc_html_e( 'Shadow mode is on', 'ai-traffic-guardian' ); ?></strong>
			<p><?php esc_html_e( 'Every decision below is being recorded but nothing is blocked yet. Review what would have happened, then switch to Active enforcement when you are confident.', 'ai-traffic-guardian' ); ?></p>
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

	<div class="atg-kpis" data-atg-kpis>
		<div class="atg-kpi">
			<span class="atg-kpi-label"><?php esc_html_e( 'Total requests', 'ai-traffic-guardian' ); ?></span>
			<span class="atg-kpi-value" data-kpi="total">—</span>
		</div>
		<div class="atg-kpi">
			<span class="atg-kpi-label"><?php esc_html_e( 'Bot share', 'ai-traffic-guardian' ); ?></span>
			<span class="atg-kpi-value" data-kpi="bot_share">—</span>
		</div>
		<div class="atg-kpi atg-kpi-block">
			<span class="atg-kpi-label"><?php esc_html_e( 'Blocked', 'ai-traffic-guardian' ); ?></span>
			<span class="atg-kpi-value" data-kpi="blocked">—</span>
		</div>
		<div class="atg-kpi atg-kpi-throttle">
			<span class="atg-kpi-label"><?php esc_html_e( 'Throttled', 'ai-traffic-guardian' ); ?></span>
			<span class="atg-kpi-value" data-kpi="throttled">—</span>
		</div>
		<div class="atg-kpi atg-kpi-human">
			<span class="atg-kpi-label"><?php esc_html_e( 'Human-equivalent', 'ai-traffic-guardian' ); ?></span>
			<span class="atg-kpi-value" data-kpi="human_eq">—</span>
		</div>
		<div class="atg-kpi atg-kpi-alert">
			<span class="atg-kpi-label"><?php esc_html_e( 'Open alerts', 'ai-traffic-guardian' ); ?></span>
			<span class="atg-kpi-value" data-kpi="alerts">—</span>
		</div>
	</div>

	<div class="atg-grid-2">
		<div class="atg-card">
			<div class="atg-card-head">
				<h2><?php esc_html_e( 'Traffic over time', 'ai-traffic-guardian' ); ?></h2>
			</div>
			<div class="atg-chart-wrap"><canvas id="atg-chart-series"></canvas></div>
		</div>
		<div class="atg-card">
			<div class="atg-card-head">
				<h2><?php esc_html_e( 'Bot traffic by category', 'ai-traffic-guardian' ); ?></h2>
			</div>
			<div class="atg-chart-wrap"><canvas id="atg-chart-purpose"></canvas></div>
		</div>
	</div>

	<div class="atg-grid-2">
		<div class="atg-card">
			<div class="atg-card-head">
				<h2><?php esc_html_e( 'Top AI vendors in your traffic', 'ai-traffic-guardian' ); ?></h2>
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

	<div class="atg-card atg-next-steps">
		<div class="atg-card-head"><h2><?php esc_html_e( 'Recommended next steps', 'ai-traffic-guardian' ); ?></h2></div>
		<ul>
			<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Let shadow mode run a few days, then review the Traffic Log for false positives.', 'ai-traffic-guardian' ); ?></li>
			<li><span class="dashicons dashicons-yes-alt"></span> <?php printf( /* translators: %s link */ esc_html__( 'Tune per-vendor rules in the %s.', 'ai-traffic-guardian' ), '<a href="' . esc_url( admin_url( 'admin.php?page=atg-policy' ) ) . '">' . esc_html__( 'AI Policy Matrix', 'ai-traffic-guardian' ) . '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>
			<li><span class="dashicons dashicons-yes-alt"></span> <?php printf( /* translators: %s link */ esc_html__( 'Add office IPs and webhook endpoints to the %s.', 'ai-traffic-guardian' ), '<a href="' . esc_url( admin_url( 'admin.php?page=atg-allowlist' ) ) . '">' . esc_html__( 'Allowlist', 'ai-traffic-guardian' ) . '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>
			<li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'For raw-volume CPU savings, pair this plugin with an edge layer (Cloudflare or host-level caching).', 'ai-traffic-guardian' ); ?></li>
		</ul>
	</div>
</div>

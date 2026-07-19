<?php
/**
 * Bot Audit view — full health-check of your bot protection setup.
 * Runs real tests; shows zero mock data.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="atg-page" id="atg-audit">

	<!-- ── Page explanation ───────────────────────────────────────────────── -->
	<div class="atg-explainer">
		<div class="atg-explainer-icon"><span class="dashicons dashicons-yes-alt"></span></div>
		<div class="atg-explainer-text">
			<h2><?php esc_html_e( 'Bot Protection Audit', 'ai-traffic-guardian' ); ?></h2>
			<p><?php esc_html_e( 'Think of this as a health check for your website\'s bot defences. Like a mechanic checking your car — we test each part, tell you if anything is broken, and give you step-by-step instructions to fix it. Every result on this page comes from checking your actual live site right now. There is no sample data.', 'ai-traffic-guardian' ); ?></p>
		</div>
	</div>

	<!-- ── Run button ─────────────────────────────────────────────────────── -->
	<div class="atg-card atg-audit-launcher" id="atg-audit-launcher">
		<div class="atg-audit-prompt">
			<span class="dashicons dashicons-shield-alt atg-audit-icon"></span>
			<div>
				<h3><?php esc_html_e( 'Ready to run the audit', 'ai-traffic-guardian' ); ?></h3>
				<p><?php esc_html_e( 'The audit takes 10–30 seconds. It fetches your live robots.txt, queries your traffic database, and checks every protection setting. Nothing is changed — it only reads.', 'ai-traffic-guardian' ); ?></p>
			</div>
		</div>
		<button class="button button-primary button-hero" id="atg-run-audit">
			<span class="dashicons dashicons-controls-play"></span>
			<?php esc_html_e( 'Run Security Audit Now', 'ai-traffic-guardian' ); ?>
		</button>
	</div>

	<!-- ── Progress ───────────────────────────────────────────────────────── -->
	<div class="atg-card atg-audit-progress" id="atg-audit-progress" hidden>
		<div class="atg-progress-bar-wrap"><div class="atg-progress-bar" id="atg-progress-fill"></div></div>
		<p class="atg-progress-label" id="atg-progress-label"><?php esc_html_e( 'Starting audit…', 'ai-traffic-guardian' ); ?></p>
	</div>

	<!-- ── Results ────────────────────────────────────────────────────────── -->
	<div id="atg-audit-results" hidden>

		<!-- Score card -->
		<div class="atg-card atg-audit-score-card" id="atg-score-card">
			<div class="atg-score-grade" id="atg-score-grade">—</div>
			<div class="atg-score-detail">
				<div class="atg-score-number" id="atg-score-number">—</div>
				<p class="atg-score-label" id="atg-score-label"><?php esc_html_e( 'Overall protection score', 'ai-traffic-guardian' ); ?></p>
				<p class="atg-score-meta" id="atg-score-meta"></p>
			</div>
			<div class="atg-score-counts" id="atg-score-counts">
				<span class="atg-sc fail" id="sc-fail"><span class="dashicons dashicons-dismiss"></span> — critical</span>
				<span class="atg-sc warning" id="sc-warn"><span class="dashicons dashicons-warning"></span> — warnings</span>
				<span class="atg-sc pass" id="sc-pass"><span class="dashicons dashicons-yes-alt"></span> — passing</span>
			</div>
		</div>

		<!-- Priority action list (fails and warnings only) -->
		<div class="atg-card" id="atg-priority-wrap" hidden>
			<div class="atg-card-head">
				<h2><?php esc_html_e( 'Priority actions', 'ai-traffic-guardian' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Fix these first — they have the biggest impact on your protection.', 'ai-traffic-guardian' ); ?></p>
			</div>
			<div id="atg-priority-list"></div>
		</div>

		<!-- Full report sections -->
		<div id="atg-full-report"></div>

		<!-- Re-run -->
		<p style="margin-top:16px">
			<button class="button" id="atg-rerun-audit">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Re-run audit', 'ai-traffic-guardian' ); ?>
			</button>
			<span class="atg-muted" id="atg-audit-timestamp" style="margin-left:12px;font-size:12px"></span>
		</p>
	</div>

</div>

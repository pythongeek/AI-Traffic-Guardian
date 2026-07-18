<?php
/**
 * Allowlist view.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="atg-page" id="atg-allowlist">

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Business-critical endpoints (always allowed)', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These paths bypass all classification and rate limiting. They cannot be removed — blocking them would break payments, cron, or the REST API.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div data-atg-protected-paths class="atg-tags"><span class="atg-empty"><?php esc_html_e( 'Loading…', 'ai-traffic-guardian' ); ?></span></div>
	</div>

	<div class="atg-grid-2">
		<div class="atg-card">
			<div class="atg-card-head">
				<h2><?php esc_html_e( 'IP allowlist', 'ai-traffic-guardian' ); ?></h2>
				<p class="description"><?php esc_html_e( 'One IP or CIDR per line (e.g. 203.0.113.10 or 198.51.100.0/24). Use for office networks, your uptime monitor, POS systems.', 'ai-traffic-guardian' ); ?></p>
			</div>
			<textarea data-atg-ips rows="8" class="large-text code" spellcheck="false"></textarea>
		</div>
		<div class="atg-card">
			<div class="atg-card-head">
				<h2><?php esc_html_e( 'Path allowlist', 'ai-traffic-guardian' ); ?></h2>
				<p class="description"><?php esc_html_e( 'One path prefix per line (e.g. /wp-json/my-app/). Use for custom webhook or app endpoints.', 'ai-traffic-guardian' ); ?></p>
			</div>
			<textarea data-atg-paths rows="8" class="large-text code" spellcheck="false"></textarea>
		</div>
	</div>
	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'User-agent allowlist', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'One UA substring per line. Any request whose user agent contains it is always allowed (e.g. MyInventoryApp).', 'ai-traffic-guardian' ); ?></p>
		</div>
		<textarea data-atg-uas rows="5" class="large-text code" spellcheck="false"></textarea>
	</div>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Path Rules (Overrides)', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Define override rules for specific paths. Bot traffic visiting matched path prefixes will be handled by the selected action, bypassing global rules.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<table class="atg-table" id="atg-path-rules-table" style="margin-bottom: 20px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Path Prefix', 'ai-traffic-guardian' ); ?></th>
					<th><?php esc_html_e( 'Override Action', 'ai-traffic-guardian' ); ?></th>
					<th><?php esc_html_e( 'Action', 'ai-traffic-guardian' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr><td colspan="3" class="atg-empty"><?php esc_html_e( 'Loading…', 'ai-traffic-guardian' ); ?></td></tr>
			</tbody>
		</table>
		<div style="display:flex; gap:10px; align-items:center; margin-bottom: 15px;">
			<input type="text" id="atg-new-path-rule-path" placeholder="/members/" class="regular-text" style="margin:0;" />
			<select id="atg-new-path-rule-action" style="margin:0;">
				<option value="allow"><?php esc_html_e( 'Allow', 'ai-traffic-guardian' ); ?></option>
				<option value="throttle"><?php esc_html_e( 'Throttle', 'ai-traffic-guardian' ); ?></option>
				<option value="block"><?php esc_html_e( 'Block', 'ai-traffic-guardian' ); ?></option>
			</select>
			<button type="button" class="button" id="atg-add-path-rule-btn"><?php esc_html_e( 'Add Path Rule', 'ai-traffic-guardian' ); ?></button>
		</div>
		<p style="margin-top:20px;"><button class="button button-primary" data-atg-save-allowlist><?php esc_html_e( 'Save allowlist', 'ai-traffic-guardian' ); ?></button></p>
	</div>
</div>

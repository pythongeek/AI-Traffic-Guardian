<?php
/**
 * Settings view.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="atg-page" id="atg-settings" data-atg-settings-form>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Enforcement', 'ai-traffic-guardian' ); ?></h2>
		</div>
		<div class="atg-form-grid">
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Mode', 'ai-traffic-guardian' ); ?></span>
				<select data-setting="enforcement">
					<option value="shadow"><?php esc_html_e( 'Shadow — log decisions, enforce nothing', 'ai-traffic-guardian' ); ?></option>
					<option value="active"><?php esc_html_e( 'Active — enforce policy decisions', 'ai-traffic-guardian' ); ?></option>
					<option value="off"><?php esc_html_e( 'Off — protection paused', 'ai-traffic-guardian' ); ?></option>
				</select>
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Shadow observation period (days)', 'ai-traffic-guardian' ); ?></span>
				<input type="number" min="1" max="30" data-setting="shadow_days" />
			</label>
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="auth_bypass" />
				<span><?php esc_html_e( 'Authenticated users bypass all bot classification (strongly recommended)', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="staging_mode" />
				<span><?php esc_html_e( 'Staging / Dev Mode (forces shadow mode and silences email alerts)', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Unknown automated traffic', 'ai-traffic-guardian' ); ?></span>
				<select data-setting="default_unknown_action">
					<option value="throttle_log"><?php esc_html_e( 'Throttle + log (recommended)', 'ai-traffic-guardian' ); ?></option>
					<option value="allow"><?php esc_html_e( 'Allow', 'ai-traffic-guardian' ); ?></option>
					<option value="throttle"><?php esc_html_e( 'Throttle', 'ai-traffic-guardian' ); ?></option>
					<option value="block"><?php esc_html_e( 'Block', 'ai-traffic-guardian' ); ?></option>
				</select>
			</label>
		</div>
	</div>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Rate limiting', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Session-based first, IP-based as a soft backstop — safe for corporate networks, universities, VPNs and mobile carriers.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div class="atg-form-grid">
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="rate_enabled" />
				<span><?php esc_html_e( 'Enable progressive rate limiting', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Anonymous humans (requests/min)', 'ai-traffic-guardian' ); ?></span>
				<input type="number" min="10" max="2000" data-setting="rate_human_rpm" />
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Known bots (requests/min)', 'ai-traffic-guardian' ); ?></span>
				<input type="number" min="1" max="600" data-setting="rate_bot_rpm" />
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Burst allowance', 'ai-traffic-guardian' ); ?></span>
				<input type="number" min="0" max="500" data-setting="rate_burst" />
			</label>
		</div>
	</div>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Privacy & data', 'ai-traffic-guardian' ); ?></h2>
		</div>
		<div class="atg-form-grid">
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="hash_ips" />
				<span><?php esc_html_e( 'Hash IP addresses in logs (GDPR-friendly)', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="log_humans" />
				<span><?php esc_html_e( 'Log human requests row-by-row (bots are always logged)', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="allow_editor_reports" />
				<span><?php esc_html_e( 'Allow Editor role to view Traffic Guardian reports & dashboard', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Log retention (days)', 'ai-traffic-guardian' ); ?></span>
				<input type="number" min="7" max="365" data-setting="retention_days" />
			</label>
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="alert_new_bot" />
				<span><?php esc_html_e( 'Alert me when a new AI bot signature appears', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="alert_email" />
				<span><?php esc_html_e( 'Also email alerts to the site admin', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Webhook URL', 'ai-traffic-guardian' ); ?></span>
				<input type="url" class="regular-text" data-setting="webhook_url" placeholder="https://hooks.slack.com/services/..." style="max-width:400px;" />
			</label>
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="send_status_header" />
				<span><?php esc_html_e( 'Send X-ATG-Status header (useful for CDN rules and debugging)', 'ai-traffic-guardian' ); ?></span>
			</label>
		</div>
	</div>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Cloudflare Integration', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Edge protection. Pushes blocked IPs directly to a Cloudflare IP List to offload CPU and network costs.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div class="atg-form-grid">
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="cloudflare_enabled" />
				<span><?php esc_html_e( 'Enable Cloudflare Edge Push', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'API Token', 'ai-traffic-guardian' ); ?></span>
				<input type="password" data-setting="cloudflare_api_token" style="max-width:400px;" />
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Account ID', 'ai-traffic-guardian' ); ?></span>
				<input type="text" data-setting="cloudflare_account_id" style="max-width:400px;" />
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'IP List ID', 'ai-traffic-guardian' ); ?></span>
				<input type="text" data-setting="cloudflare_ip_list_id" style="max-width:400px;" />
			</label>
		</div>
	</div>

	<div class="atg-card">
		<div class="atg-card-head"><h2><?php esc_html_e( 'Environment', 'ai-traffic-guardian' ); ?></h2></div>
		<table class="atg-table" data-atg-env>
			<tbody><tr><td class="atg-empty"><?php esc_html_e( 'Loading…', 'ai-traffic-guardian' ); ?></td></tr></tbody>
		</table>
	</div>

	<div class="atg-card atg-danger-zone">
		<div class="atg-card-head"><h2><?php esc_html_e( 'Data management', 'ai-traffic-guardian' ); ?></h2></div>
		<label class="atg-switch-row" style="margin-bottom:15px;">
			<input type="checkbox" data-setting="delete_data_on_uninstall" />
			<span><?php esc_html_e( 'Delete all tables and settings when the plugin is uninstalled', 'ai-traffic-guardian' ); ?></span>
		</label>
		<div style="border-top:1px solid #fee2e2; padding-top:15px; margin-top:15px;">
			<button type="button" class="button" id="atg-clear-traffic-data-btn" style="background:#ef4444; border-color:#ef4444; color:#fff;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'">
				<?php esc_html_e( 'Clear All Traffic Logs & Stats', 'ai-traffic-guardian' ); ?>
			</button>
			<p class="description" style="margin-top:5px; color:#ef4444;"><?php esc_html_e( 'Warning: This will delete all rows from the traffic log and daily statistics tables. The dashboard stats and charts will start fresh from zero.', 'ai-traffic-guardian' ); ?></p>
		</div>
	</div>

	<p><button class="button button-primary button-hero" data-atg-save-settings><?php esc_html_e( 'Save settings', 'ai-traffic-guardian' ); ?></button></p>
</div>

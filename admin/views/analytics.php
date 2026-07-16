<?php
/**
 * Analytics integrity view.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="atg-page" id="atg-analytics" data-atg-settings-form>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'GA4 / GTM bot filtering', 'ai-traffic-guardian' ); ?></h2>
		</div>
		<div class="atg-form-grid">
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Mode', 'ai-traffic-guardian' ); ?></span>
				<select data-setting="ga4_mode">
					<option value="off"><?php esc_html_e( 'Off — do not touch analytics', 'ai-traffic-guardian' ); ?></option>
					<option value="compat"><?php esc_html_e( 'Compatibility mode — tag bot sessions with a custom parameter (GTM-safe, recommended)', 'ai-traffic-guardian' ); ?></option>
					<option value="conditional"><?php esc_html_e( 'Conditional loading — never load GA4/GTM for flagged bots', 'ai-traffic-guardian' ); ?></option>
				</select>
			</label>
			<p class="description atg-inline-note">
				<?php esc_html_e( 'Compatibility mode pushes atg_bot=true into the dataLayer/gtag config for flagged sessions. Register "atg_bot" as a custom dimension in GA4 to filter or segment on it — nothing breaks in existing GTM setups.', 'ai-traffic-guardian' ); ?>
			</p>
		</div>
	</div>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Server-side purchase tracking (WooCommerce)', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Fires the GA4 purchase event from your server on payment completion — bots cannot fake it and ad blockers cannot stop it.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div class="atg-form-grid">
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="ga4_server_purchase" />
				<span><?php esc_html_e( 'Enable server-side purchase events', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'GA4 Measurement ID', 'ai-traffic-guardian' ); ?></span>
				<input type="text" class="regular-text" data-setting="ga4_measurement_id" placeholder="G-XXXXXXX" autocomplete="off" />
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Measurement Protocol API secret', 'ai-traffic-guardian' ); ?></span>
				<input type="password" class="regular-text" data-setting="ga4_api_secret" autocomplete="new-password" />
			</label>
		</div>
	</div>

	<div class="atg-card atg-limitations">
		<div class="atg-card-head"><h2><?php esc_html_e( 'Honest limitations', 'ai-traffic-guardian' ); ?></h2></div>
		<ul>
			<li><?php esc_html_e( '"Ghost traffic" sent directly to GA4\'s Measurement Protocol never touches WordPress — no plugin can intercept it. Rotating your Measurement ID and moving to server-side-only tagging is the only fix.', 'ai-traffic-guardian' ); ?></li>
			<li><?php esc_html_e( 'Conditional loading only removes beacons enqueued through standard WordPress script APIs. Hard-coded snippets in theme files may still load.', 'ai-traffic-guardian' ); ?></li>
			<li><?php esc_html_e( 'Sophisticated agentic traffic inside a real browser can be indistinguishable from a human — this is risk reduction, not elimination.', 'ai-traffic-guardian' ); ?></li>
		</ul>
	</div>

	<p><button class="button button-primary button-hero" data-atg-save-settings><?php esc_html_e( 'Save analytics settings', 'ai-traffic-guardian' ); ?></button></p>
</div>

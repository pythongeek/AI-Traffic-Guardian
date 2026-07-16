<?php
/**
 * Alerts view: new AI bot detections and spoofing attempts.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="atg-page" id="atg-alerts">

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Detection alerts', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'New AI crawlers and spoofing attempts found in your traffic. Add promising newcomers to the policy matrix, or allowlist them directly.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div class="atg-filters">
			<select data-atg-alert-status>
				<option value="open"><?php esc_html_e( 'Open', 'ai-traffic-guardian' ); ?></option>
				<option value="dismissed"><?php esc_html_e( 'Dismissed', 'ai-traffic-guardian' ); ?></option>
				<option value="all"><?php esc_html_e( 'All', 'ai-traffic-guardian' ); ?></option>
			</select>
		</div>
		<div data-atg-alerts-list>
			<div class="atg-empty"><?php esc_html_e( 'Loading…', 'ai-traffic-guardian' ); ?></div>
		</div>
	</div>
</div>

<?php
/**
 * Policy matrix view: presets + granular vendor × purpose grid.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="atg-page" id="atg-policy">

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'One-click policy presets', 'ai-traffic-guardian' ); ?></h2>
		</div>
		<div class="atg-presets" data-atg-presets>
			<div class="atg-empty"><?php esc_html_e( 'Loading…', 'ai-traffic-guardian' ); ?></div>
		</div>
	</div>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Granular policy matrix', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Vendor × purpose control. Changes save instantly and feed both the enforcement engine and robots.txt.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div class="atg-matrix-wrap">
			<table class="atg-table atg-matrix" data-atg-matrix>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Bot', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Vendor', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Purpose', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Verification', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Policy', 'ai-traffic-guardian' ); ?></th>
					</tr>
				</thead>
				<tbody><tr><td colspan="5" class="atg-empty"><?php esc_html_e( 'Loading…', 'ai-traffic-guardian' ); ?></td></tr></tbody>
			</table>
		</div>
		<p class="description atg-matrix-note">
			<?php esc_html_e( 'Identity rules: bots that fail verification are treated as spoofers (blocked). Bots whose identity cannot be verified are throttled and logged, never silently trusted.', 'ai-traffic-guardian' ); ?>
		</p>
	</div>
</div>

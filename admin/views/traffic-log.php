<?php
/**
 * Traffic log view: filterable, paginated decision log + CSV export.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="atg-page" id="atg-log">

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Bot traffic log', 'ai-traffic-guardian' ); ?></h2>
			<button class="button" data-atg-export><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export CSV', 'ai-traffic-guardian' ); ?></button>
		</div>

		<div class="atg-filters" data-atg-filters>
			<select data-filter="classification">
				<option value=""><?php esc_html_e( 'All classifications', 'ai-traffic-guardian' ); ?></option>
				<option value="bot"><?php esc_html_e( 'Known bots', 'ai-traffic-guardian' ); ?></option>
				<option value="unknown_bot"><?php esc_html_e( 'Unknown bots', 'ai-traffic-guardian' ); ?></option>
				<option value="agent_proxy"><?php esc_html_e( 'Human-intent proxies', 'ai-traffic-guardian' ); ?></option>
				<option value="form_abuse"><?php esc_html_e( 'Form abuse', 'ai-traffic-guardian' ); ?></option>
				<option value="allowlisted"><?php esc_html_e( 'Allowlisted', 'ai-traffic-guardian' ); ?></option>
				<option value="authenticated"><?php esc_html_e( 'Authenticated', 'ai-traffic-guardian' ); ?></option>
				<option value="human"><?php esc_html_e( 'Humans', 'ai-traffic-guardian' ); ?></option>
			</select>
			<select data-filter="action">
				<option value=""><?php esc_html_e( 'All decisions', 'ai-traffic-guardian' ); ?></option>
				<option value="allow"><?php esc_html_e( 'Allowed', 'ai-traffic-guardian' ); ?></option>
				<option value="throttle"><?php esc_html_e( 'Throttled', 'ai-traffic-guardian' ); ?></option>
				<option value="block"><?php esc_html_e( 'Blocked', 'ai-traffic-guardian' ); ?></option>
			</select>
			<input type="search" data-filter="search" placeholder="<?php esc_attr_e( 'Search UA, path or bot…', 'ai-traffic-guardian' ); ?>" />
			<button class="button button-primary" data-atg-apply-filters><?php esc_html_e( 'Filter', 'ai-traffic-guardian' ); ?></button>
		</div>

		<div class="atg-table-scroll">
			<table class="atg-table atg-log-table" data-atg-log-table>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Class', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Bot / UA', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Path', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Verified', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Decision', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Enforced', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'IP', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'ai-traffic-guardian' ); ?></th>
					</tr>
				</thead>
				<tbody><tr><td colspan="10" class="atg-empty"><?php esc_html_e( 'Loading…', 'ai-traffic-guardian' ); ?></td></tr></tbody>
			</table>
		</div>
		<div class="atg-pagination" data-atg-pagination></div>
	</div>
</div>

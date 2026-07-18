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
			<h2><?php esc_html_e( 'Export / Import Policy Configuration', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Backup or restore your full policy matrix settings and custom signatures.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div style="display: flex; gap: 15px; align-items: center; padding: 10px 0;">
			<button type="button" class="button" id="atg-export-policy-btn">
				<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Export Configuration JSON', 'ai-traffic-guardian' ); ?>
			</button>
			<label class="button" style="cursor: pointer;">
				<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Import Configuration JSON', 'ai-traffic-guardian' ); ?>
				<input type="file" id="atg-import-policy-file" accept=".json" style="display: none;" />
			</label>
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

	<div class="atg-card" id="atg-custom-signatures-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Custom Bot Signatures', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Define your own bot signatures using custom user agent matching patterns.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div class="atg-table-wrap">
			<table class="atg-table" id="atg-custom-signatures-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Vendor', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Purpose', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Pattern (Regex)', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Verification', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'ai-traffic-guardian' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr><td colspan="6" class="atg-empty"><?php esc_html_e( 'Loading…', 'ai-traffic-guardian' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<div class="atg-custom-sig-actions" style="margin-top: 15px;">
			<button type="button" class="button button-primary" id="atg-add-custom-sig-btn">
				<?php esc_html_e( 'Add Custom Signature', 'ai-traffic-guardian' ); ?>
			</button>
		</div>

		<!-- Add/Edit Form -->
		<div id="atg-custom-sig-form-wrap" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
			<h3 id="atg-form-title"><?php esc_html_e( 'Add Custom Signature', 'ai-traffic-guardian' ); ?></h3>
			<form id="atg-custom-sig-form" onsubmit="return false;">
				<input type="hidden" id="atg-sig-index" value="" />
				<div class="atg-form-row" style="margin-bottom: 10px;">
					<label style="display:block; font-weight:bold; margin-bottom: 5px;"><?php esc_html_e( 'Name', 'ai-traffic-guardian' ); ?></label>
					<input type="text" id="atg-sig-name" required class="regular-text" placeholder="e.g. My Custom Bot" />
				</div>
				<div class="atg-form-row" style="margin-bottom: 10px;">
					<label style="display:block; font-weight:bold; margin-bottom: 5px;"><?php esc_html_e( 'Vendor', 'ai-traffic-guardian' ); ?></label>
					<input type="text" id="atg-sig-vendor" required class="regular-text" placeholder="e.g. My Company" />
				</div>
				<div class="atg-form-row" style="margin-bottom: 10px;">
					<label style="display:block; font-weight:bold; margin-bottom: 5px;"><?php esc_html_e( 'Purpose', 'ai-traffic-guardian' ); ?></label>
					<select id="atg-sig-purpose" required>
						<!-- Filled via JS -->
					</select>
				</div>
				<div class="atg-form-row" style="margin-bottom: 10px;">
					<label style="display:block; font-weight:bold; margin-bottom: 5px;"><?php esc_html_e( 'Pattern (Regex)', 'ai-traffic-guardian' ); ?></label>
					<input type="text" id="atg-sig-pattern" required class="regular-text" placeholder="e.g. #\bMyCustomBot\b#i" />
					<p class="description"><?php esc_html_e( 'Must be a valid PHP regular expression (with delimiters, e.g. #\bBotName\b#i)', 'ai-traffic-guardian' ); ?></p>
				</div>
				<div class="atg-form-row" style="margin-bottom: 10px;">
					<label style="display:block; font-weight:bold; margin-bottom: 5px;"><?php esc_html_e( 'Verification Type', 'ai-traffic-guardian' ); ?></label>
					<select id="atg-sig-verify">
						<option value="none"><?php esc_html_e( 'None (Unverifiable)', 'ai-traffic-guardian' ); ?></option>
						<option value="rdns"><?php esc_html_e( 'Reverse DNS (rDNS)', 'ai-traffic-guardian' ); ?></option>
						<option value="ip_range"><?php esc_html_e( 'IP Range JSON Endpoint', 'ai-traffic-guardian' ); ?></option>
					</select>
				</div>
				<div class="atg-form-row atg-sig-verify-extra rdns-extra" style="display: none; margin-bottom: 10px;">
					<label style="display:block; font-weight:bold; margin-bottom: 5px;"><?php esc_html_e( 'rDNS Suffixes', 'ai-traffic-guardian' ); ?></label>
					<input type="text" id="atg-sig-rdns" class="regular-text" placeholder="e.g. .mycompany.com, .mybot.org" />
					<p class="description"><?php esc_html_e( 'Comma-separated suffixes that the reverse DNS host must end with.', 'ai-traffic-guardian' ); ?></p>
				</div>
				<div class="atg-form-row atg-sig-verify-extra ip-range-extra" style="display: none; margin-bottom: 10px;">
					<label style="display:block; font-weight:bold; margin-bottom: 5px;"><?php esc_html_e( 'IP Range JSON Source URL', 'ai-traffic-guardian' ); ?></label>
					<input type="url" id="atg-sig-ip-source" class="regular-text" placeholder="https://example.com/ips.json" />
					<p class="description"><?php esc_html_e( 'URL that publishes the IP ranges for this bot (JSON format).', 'ai-traffic-guardian' ); ?></p>
				</div>
				<div style="margin-top: 15px;">
					<button type="submit" class="button button-primary" id="atg-save-sig-btn"><?php esc_html_e( 'Save Signature', 'ai-traffic-guardian' ); ?></button>
					<button type="button" class="button" id="atg-cancel-sig-btn"><?php esc_html_e( 'Cancel', 'ai-traffic-guardian' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

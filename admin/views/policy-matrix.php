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

	<!-- ── Page explanation ───────────────────────────────────────────────── -->
	<div class="atg-explainer">
		<div class="atg-explainer-icon"><span class="dashicons dashicons-groups"></span></div>
		<div class="atg-explainer-text">
			<h2><?php esc_html_e( 'Your bot guest list', 'ai-traffic-guardian' ); ?></h2>
			<p><?php esc_html_e( 'Think of this page like a guest list for a party. Some robots are on the VIP list (like Googlebot — it helps people find your website in search results, so you definitely want it in). Some you want to slow down so they don\'t hog your bandwidth. And some you want to turn away at the door. Each row is one type of robot. You pick: Allow, Throttle (slow down), or Block. Changes take effect immediately.', 'ai-traffic-guardian' ); ?></p>
		</div>
	</div>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Quick-start presets', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Not sure where to start? Pick the preset that describes your site. You can fine-tune individual bots below afterward.', 'ai-traffic-guardian' ); ?></p>
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

		<!-- Column guide -->
		<div class="atg-matrix-guide">
			<div class="atg-guide-item">
				<strong><?php esc_html_e( 'Bot', 'ai-traffic-guardian' ); ?></strong>
				<p><?php esc_html_e( 'The specific robot\'s name.', 'ai-traffic-guardian' ); ?></p>
			</div>
			<div class="atg-guide-item">
				<strong><?php esc_html_e( 'Company', 'ai-traffic-guardian' ); ?></strong>
				<p><?php esc_html_e( 'Who owns the robot.', 'ai-traffic-guardian' ); ?></p>
			</div>
			<div class="atg-guide-item">
				<strong><?php esc_html_e( 'Purpose', 'ai-traffic-guardian' ); ?></strong>
				<p><?php esc_html_e( 'What it\'s doing on your site — search indexing, AI training, link previews etc.', 'ai-traffic-guardian' ); ?></p>
			</div>
			<div class="atg-guide-item">
				<strong><?php esc_html_e( 'Verification', 'ai-traffic-guardian' ); ?></strong>
				<p><?php esc_html_e( 'How we confirm the bot is who it claims to be. "DNS verify" = gold standard. "Unverifiable" = we throttle it instead of trusting it blindly.', 'ai-traffic-guardian' ); ?></p>
			</div>
			<div class="atg-guide-item">
				<strong><?php esc_html_e( 'Policy', 'ai-traffic-guardian' ); ?></strong>
				<p><?php esc_html_e( 'Your choice. Allow = let it in. Throttle = slow it down. Block = turn it away.', 'ai-traffic-guardian' ); ?></p>
			</div>
		</div>

		<div class="atg-matrix-wrap">
			<table class="atg-table atg-matrix" data-atg-matrix>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Bot', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Company', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Purpose', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Verification', 'ai-traffic-guardian' ); ?></th>
						<th><?php esc_html_e( 'Policy', 'ai-traffic-guardian' ); ?></th>
					</tr>
				</thead>
				<tbody><tr><td colspan="5" class="atg-empty"><?php esc_html_e( 'Loading your policy settings…', 'ai-traffic-guardian' ); ?></td></tr></tbody>
			</table>
		</div>

		<div class="atg-matrix-legend">
			<span class="atg-pill atg-pill-allow">Allow</span> <?php esc_html_e( '= full access', 'ai-traffic-guardian' ); ?> &nbsp;
			<span class="atg-pill atg-pill-throttle">Throttle</span> <?php esc_html_e( '= add a delay + send robots.txt Crawl-delay', 'ai-traffic-guardian' ); ?> &nbsp;
			<span class="atg-pill atg-pill-block">Block</span> <?php esc_html_e( '= 403 response + Disallow: / in robots.txt', 'ai-traffic-guardian' ); ?>
		</div>

		<p class="description atg-matrix-note">
			<strong><?php esc_html_e( 'About verification:', 'ai-traffic-guardian' ); ?></strong>
			<?php esc_html_e( 'A bot that claims to be Googlebot but fails the DNS identity check is treated as a spoofer and blocked automatically — your policy setting doesn\'t matter in that case. Bots whose identity cannot be checked are throttled and logged instead of being trusted.', 'ai-traffic-guardian' ); ?>
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

<?php
/**
 * Debug Log view.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$debug_on = ATG_Debug::enabled();
$expiry   = ATG_Debug::expiry();
?>
<div class="atg-page" id="atg-debug">

	<div class="atg-card <?php echo $debug_on ? 'atg-debug-active' : 'atg-debug-inactive'; ?>">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Debug Log', 'ai-traffic-guardian' ); ?>
				<?php if ( $debug_on ) : ?>
					<span class="atg-pill atg-pill-allow" style="margin-left:10px;"><?php esc_html_e( 'LIVE', 'ai-traffic-guardian' ); ?></span>
				<?php else : ?>
					<span class="atg-pill atg-pill-neutral" style="margin-left:10px;"><?php esc_html_e( 'OFF', 'ai-traffic-guardian' ); ?></span>
				<?php endif; ?>
			</h2>
			<div style="display:flex;gap:8px;flex-wrap:wrap;">
				<button class="button <?php echo $debug_on ? '' : 'button-primary'; ?>" data-atg-debug-toggle>
					<?php echo $debug_on ? esc_html__( 'Disable logging', 'ai-traffic-guardian' ) : esc_html__( 'Enable logging', 'ai-traffic-guardian' ); ?>
				</button>
				<button class="button" data-atg-debug-refresh><?php esc_html_e( 'Refresh', 'ai-traffic-guardian' ); ?></button>
				<button class="button atg-panic" data-atg-debug-clear><?php esc_html_e( 'Clear log', 'ai-traffic-guardian' ); ?></button>
			</div>
		</div>

		<?php if ( $debug_on && $expiry ) : ?>
			<p class="description" style="margin-bottom:12px;">
				<?php printf(
					/* translators: %s expiry datetime */
					esc_html__( 'Auto-disables at %s to protect the database. Re-enable any time.', 'ai-traffic-guardian' ),
					esc_html( date_i18n( 'Y-m-d H:i:s', $expiry ) )
				); ?>
			</p>
		<?php endif; ?>

		<?php if ( ! $debug_on ) : ?>
			<div class="atg-limitations" style="margin-bottom:16px;">
				<ul>
					<li><?php esc_html_e( 'Enable logging, then click every page, button, and feature you want to test.', 'ai-traffic-guardian' ); ?></li>
					<li><?php esc_html_e( 'All REST API calls, classifications, enforcements, errors, and stray output are captured.', 'ai-traffic-guardian' ); ?></li>
					<li><?php esc_html_e( 'Logging auto-disables after 1 hour to prevent database bloat.', 'ai-traffic-guardian' ); ?></li>
					<li><?php esc_html_e( 'Look for "stray-output" entries — those cause REST API JSON parse failures and explain why the UI shows "Loading..." forever.', 'ai-traffic-guardian' ); ?></li>
				</ul>
			</div>
		<?php endif; ?>

		<div class="atg-filters" style="margin-bottom:16px;" data-atg-debug-filters>
			<select data-atg-debug-context>
				<option value=""><?php esc_html_e( 'All contexts', 'ai-traffic-guardian' ); ?></option>
				<option value="rest"><?php esc_html_e( 'REST API calls', 'ai-traffic-guardian' ); ?></option>
				<option value="classifier"><?php esc_html_e( 'Classification', 'ai-traffic-guardian' ); ?></option>
				<option value="enforcer"><?php esc_html_e( 'Enforcement', 'ai-traffic-guardian' ); ?></option>
				<option value="stray-output"><?php esc_html_e( 'Stray output (⚠ critical)', 'ai-traffic-guardian' ); ?></option>
				<option value="php-error"><?php esc_html_e( 'PHP errors', 'ai-traffic-guardian' ); ?></option>
				<option value="form"><?php esc_html_e( 'Form protection', 'ai-traffic-guardian' ); ?></option>
				<option value="system"><?php esc_html_e( 'System', 'ai-traffic-guardian' ); ?></option>
				<option value="error"><?php esc_html_e( 'Errors', 'ai-traffic-guardian' ); ?></option>
			</select>
			<input type="search" placeholder="<?php esc_attr_e( 'Search messages…', 'ai-traffic-guardian' ); ?>" data-atg-debug-search style="min-height:34px;" />
		</div>

		<div data-atg-debug-entries>
			<div class="atg-empty"><?php esc_html_e( $debug_on ? 'Loading log entries…' : 'Enable logging above to start capturing events.', 'ai-traffic-guardian' ); ?></div>
		</div>
	</div>

	<div class="atg-card">
		<div class="atg-card-head"><h2><?php esc_html_e( 'Common issues & what to look for', 'ai-traffic-guardian' ); ?></h2></div>
		<table class="atg-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Symptom', 'ai-traffic-guardian' ); ?></th>
					<th><?php esc_html_e( 'Log context', 'ai-traffic-guardian' ); ?></th>
					<th><?php esc_html_e( 'Root cause & fix', 'ai-traffic-guardian' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Dashboard shows "—" or "Loading…" forever', 'ai-traffic-guardian' ); ?></td>
					<td><code>stray-output</code></td>
					<td><?php esc_html_e( 'PHP is outputting something before headers — corrupts REST JSON. Look for stray-output entries; the preview field shows exactly what was output.', 'ai-traffic-guardian' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( '"Go live" button does nothing / mode stays shadow', 'ai-traffic-guardian' ); ?></td>
					<td><code>rest</code></td>
					<td><?php esc_html_e( 'REST POST /mode is failing. Check rest entries for "ERROR" status. Most likely cause is the stray output issue above, or a caching plugin intercepting admin-ajax/REST.', 'ai-traffic-guardian' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( '265 chars of unexpected output on activation', 'ai-traffic-guardian' ); ?></td>
					<td><code>system</code></td>
					<td><?php esc_html_e( 'The dbDelta / upgrade.php include was emitting output. Fixed by moving schema upgrades to admin_init and wrapping activation in ob_start().', 'ai-traffic-guardian' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'REST API returns 401 / 403', 'ai-traffic-guardian' ); ?></td>
					<td><code>rest</code></td>
					<td><?php esc_html_e( 'Nonce expired or user not logged in as admin. Reload the page to get a fresh nonce. Some caching plugins strip the WP-Nonce cookie — add wp-json/* to their exclusion list.', 'ai-traffic-guardian' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Bot classification not firing', 'ai-traffic-guardian' ); ?></td>
					<td><code>classifier</code></td>
					<td><?php esc_html_e( 'Classifier skips admin pages, REST requests, and WP-Cron by design. If missing on front-end requests, check for a page cache serving cached HTML before PHP runs.', 'ai-traffic-guardian' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>

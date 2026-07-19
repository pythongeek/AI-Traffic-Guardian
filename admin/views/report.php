<?php
/**
 * Bot Audit Report view page.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats = ATG_Report_Generator::get_stats();
?>
<style>
@media print {
	.atg-wrap .atg-topbar, .atg-wrap .atg-footer-credit, .atg-print-hide {
		display: none !important;
	}
	.atg-page {
		margin: 0 !important;
		padding: 0 !important;
	}
	.atg-card {
		box-shadow: none !important;
		border: none !important;
	}
}
</style>
<div class="atg-page" id="atg-report-page">
	<div class="atg-card">
		<div class="atg-card-head" style="display:flex; justify-content:space-between; align-items:center;">
			<div>
				<h2><?php esc_html_e( '7-Day Bot Audit Report', 'ai-traffic-guardian' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Analyze AI crawler overhead, bandwidth waste, and traffic composition.', 'ai-traffic-guardian' ); ?></p>
			</div>
			<div class="atg-print-hide">
				<button class="button button-primary" onclick="window.print()"><?php esc_html_e( 'Print / Save PDF', 'ai-traffic-guardian' ); ?></button>
				<button class="button button-secondary" onclick="exportSocialCard()"><?php esc_html_e( 'Download Shareable Card', 'ai-traffic-guardian' ); ?></button>
			</div>
		</div>

		<div class="atg-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top:20px;">
			<div class="atg-card-sub" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:20px;">
				<h3><?php esc_html_e( 'Traffic Composition', 'ai-traffic-guardian' ); ?></h3>
				<p style="font-size:24px; font-weight:700; margin:10px 0;">
					<?php echo esc_html( $stats['bot_pct'] ); ?>% <span style="font-size:14px; font-weight:normal; color:#64748b;"><?php esc_html_e( 'Bot / Crawler Traffic', 'ai-traffic-guardian' ); ?></span>
				</p>
				<p style="font-size:16px; margin:0; color:#334155;">
					<strong><?php esc_html_e( 'Human traffic:', 'ai-traffic-guardian' ); ?></strong> <?php echo esc_html( $stats['human_pct'] ); ?>% (<?php echo esc_html( number_format( $stats['human_hits'] ) ); ?> <?php esc_html_e( 'hits', 'ai-traffic-guardian' ); ?>)
				</p>
			</div>

			<div class="atg-card-sub" style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:20px;">
				<h3><?php esc_html_e( 'Bandwidth & Server Load Wasted', 'ai-traffic-guardian' ); ?></h3>
				<p style="font-size:24px; font-weight:700; margin:10px 0; color:#dc2626;">
					<?php echo esc_html( $stats['bandwidth_mb'] ); ?> MB <span style="font-size:14px; font-weight:normal; color:#991b1b;"><?php esc_html_e( 'Estimated Waste', 'ai-traffic-guardian' ); ?></span>
				</p>
				<p style="font-size:14px; margin:0; color:#7f1d1d;">
					<strong><?php esc_html_e( 'Standout Stat:', 'ai-traffic-guardian' ); ?></strong> <?php echo esc_html( $stats['standout'] ); ?>
				</p>
			</div>
		</div>

		<h3 style="margin-top:30px;"><?php esc_html_e( 'Top Crawler Activity', 'ai-traffic-guardian' ); ?></h3>
		<table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Crawler Vendor', 'ai-traffic-guardian' ); ?></th>
					<th><?php esc_html_e( 'Request Hits', 'ai-traffic-guardian' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $stats['vendors'] ) ) : ?>
					<?php foreach ( $stats['vendors'] as $v ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $v['vendor'] ); ?></strong></td>
							<td><?php echo esc_html( number_format( $v['total'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="2"><?php esc_html_e( 'No crawler traffic logged yet.', 'ai-traffic-guardian' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Shadow Mode Challenge Opt-In (Phase 11) -->
	<div class="atg-card atg-print-hide" id="atg-challenge-card" style="display:none; margin-top:20px; border-left: 6px solid #6366f1;">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Enter the Shadow Mode Challenge', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Submit your 7-day shadow mode bot statistics. Sites with the highest AI crawler traffic win!', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div style="margin-top:15px; padding:15px; background:#f5f3ff; border:1px solid #ddd6fe; border-radius:6px;">
			<p style="margin:0 0 10px 0; font-weight:600; color:#4c1d95;">
				<span id="atg-challenge-prize"></span>
			</p>
			<form id="atg-challenge-form" onsubmit="submitChallengeEntry(event)">
				<input type="hidden" id="atg-challenge-id" value="" />
				<div style="margin-bottom:12px;">
					<label style="display:block; font-weight:600; font-size:13px; color:#475569; margin-bottom:5px;"><?php esc_html_e( 'Leaderboard Handle / Display Name:', 'ai-traffic-guardian' ); ?></label>
					<input type="text" id="atg-challenge-display-name" placeholder="anonymous" style="width:100%; max-width:300px; padding:6px; border:1px solid #cbd5e1; border-radius:4px;" />
				</div>
				<div style="margin-bottom:15px;">
					<label style="font-weight:500; color:#475569; font-size:13px;">
						<input type="checkbox" id="atg-challenge-show-domain" /> <?php esc_html_e( 'Disclose my full domain name on the public leaderboard (instead of masked e***e.com)', 'ai-traffic-guardian' ); ?>
					</label>
				</div>
				<button type="submit" class="button button-primary" id="atg-challenge-submit-btn"><?php esc_html_e( 'Submit Entry', 'ai-traffic-guardian' ); ?></button>
			</form>
			<div id="atg-challenge-result" style="display:none; margin-top:10px; font-weight:600; color:#059669;"></div>
		</div>
	</div>

	<!-- Hidden Canvas for Social Card Generation -->
	<canvas id="atgSocialCanvas" width="600" height="315" style="display:none;"></canvas>
</div>

<script>
function exportSocialCard() {
	var canvas = document.getElementById('atgSocialCanvas');
	var ctx = canvas.getContext('2d');

	// Background gradient
	var grad = ctx.createLinearGradient(0, 0, 600, 315);
	grad.addColorStop(0, '#0f172a');
	grad.addColorStop(1, '#1e293b');
	ctx.fillStyle = grad;
	ctx.fillRect(0, 0, 600, 315);

	// Header branding
	ctx.fillStyle = '#38bdf8';
	ctx.font = 'bold 20px sans-serif';
	ctx.fillText('Bot Shield Pro — Weekly Audit', 40, 50);

	// Border box
	ctx.strokeStyle = '#334155';
	ctx.lineWidth = 1;
	ctx.strokeRect(20, 20, 560, 275);

	// Stats content
	ctx.fillStyle = '#ffffff';
	ctx.font = 'bold 36px sans-serif';
	ctx.fillText('<?php echo esc_attr( $stats['bot_pct'] ); ?>% Crawler Traffic', 40, 120);

	ctx.fillStyle = '#94a3b8';
	ctx.font = '16px sans-serif';
	ctx.fillText('Wasted Bandwidth: <?php echo esc_attr( $stats['bandwidth_mb'] ); ?> MB', 40, 160);
	ctx.fillText('Top Crawler: <?php echo esc_attr( isset( $stats['vendors'][0]['vendor'] ) ? $stats['vendors'][0]['vendor'] : 'None' ); ?>', 40, 190);

	// Standout stat
	ctx.fillStyle = '#f87171';
	ctx.font = 'italic 14px sans-serif';
	ctx.fillText('<?php echo esc_attr( substr( $stats['standout'], 0, 65 ) ); ?>', 40, 240);

	// Download link trigger
	var link = document.createElement('a');
	link.download = 'bot-audit-report.png';
	link.href = canvas.toDataURL('image/png');
	link.click();
}

// On page load check if active challenge campaign exists
document.addEventListener('DOMContentLoaded', function() {
	var restUrl = '<?php echo esc_url( rest_url( "atg/v1/challenge/campaign" ) ); ?>';
	fetch(restUrl, {
		headers: {
			'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( "wp_rest" ) ); ?>'
		}
	})
	.then(function(res) { return res.json(); })
	.then(function(data) {
		if (data && data.campaign_id) {
			document.getElementById('atg-challenge-id').value = data.campaign_id;
			var prizeText = '<?php esc_html_e( "Active Challenge Campaign:", "ai-traffic-guardian" ); ?> ' + data.campaign_id;
			if (data.prize) {
				prizeText += ' — <?php esc_html_e( "Prize:", "ai-traffic-guardian" ); ?> ' + data.prize;
			}
			document.getElementById('atg-challenge-prize').innerText = prizeText;
			document.getElementById('atg-challenge-card').style.display = 'block';
		}
	})
	.catch(function(err) {
		console.error('Failed to load active campaign details', err);
	});
});

function submitChallengeEntry(event) {
	event.preventDefault();
	var campaignId = document.getElementById('atg-challenge-id').value;
	var displayName = document.getElementById('atg-challenge-display-name').value;
	var showDomain = document.getElementById('atg-challenge-show-domain').checked;
	var submitBtn = document.getElementById('atg-challenge-submit-btn');
	var resultDiv = document.getElementById('atg-challenge-result');

	submitBtn.disabled = true;
	submitBtn.innerText = '<?php esc_html_e( "Submitting...", "ai-traffic-guardian" ); ?>';

	var restUrl = '<?php echo esc_url( rest_url( "atg/v1/challenge/submit" ) ); ?>';
	fetch(restUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( "wp_rest" ) ); ?>'
		},
		body: JSON.stringify({
			campaign_id: campaignId,
			display_name: displayName,
			show_domain: showDomain
		})
	})
	.then(function(res) { return res.json(); })
	.then(function(data) {
		submitBtn.innerText = '<?php esc_html_e( "Submitted", "ai-traffic-guardian" ); ?>';
		resultDiv.style.display = 'block';
		if (data.accepted) {
			resultDiv.style.color = '#059669';
			resultDiv.innerHTML = '<?php esc_html_e( "Success! You are ranked #", "ai-traffic-guardian" ); ?>' + data.current_rank + ' <?php esc_html_e( "of", "ai-traffic-guardian" ); ?> ' + data.total_entries + ' <?php esc_html_e( "entries.", "ai-traffic-guardian" ); ?> <a href="https://campaign.aitrafficguardian.com/leaderboard?campaign_id=' + encodeURIComponent(campaignId) + '" target="_blank" style="text-decoration: underline; font-weight: bold; color: #4338ca; margin-left: 5px;"><?php esc_html_e( "View Standings", "ai-traffic-guardian" ); ?></a>';
		} else {
			resultDiv.style.color = '#dc2626';
			var errorMsg = data.message || '<?php esc_html_e( "Failed to submit challenge entry.", "ai-traffic-guardian" ); ?>';
			resultDiv.innerText = errorMsg;
			submitBtn.disabled = false;
			submitBtn.innerText = '<?php esc_html_e( "Submit Entry", "ai-traffic-guardian" ); ?>';
		}
	})
	.catch(function(err) {
		console.error('Failed to submit entry', err);
		resultDiv.style.display = 'block';
		resultDiv.style.color = '#dc2626';
		resultDiv.innerText = '<?php esc_html_e( "Failed to submit challenge entry.", "ai-traffic-guardian" ); ?>';
		submitBtn.disabled = false;
		submitBtn.innerText = '<?php esc_html_e( "Submit Entry", "ai-traffic-guardian" ); ?>';
	});
}
</script>

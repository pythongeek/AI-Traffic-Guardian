<?php
/**
 * Edge Setup Wizard View.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_id = ATG_Edge::get_site_id();
$secret  = ATG_Edge::get_shared_secret();
$worker  = ATG_Edge::generate_worker_js();
$nginx   = ATG_Edge::generate_nginx_map();
?>
<div class="atg-page" id="atg-edge-setup">
	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'Edge Integration Wizard', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Block traffic at the DNS or cache level before it reaches your server. Positioned as: Bring Your Own Edge.', 'ai-traffic-guardian' ); ?></p>
		</div>

		<div style="margin: 20px 0; padding: 15px; background: #f0fdf4; border-left: 4px solid #16a34a; border-radius: 4px;">
			<h4 style="margin: 0 0 5px 0; color: #166534;"><?php esc_html_e( 'Your Edge Connection Credentials', 'ai-traffic-guardian' ); ?></h4>
			<p style="margin: 0; font-family: monospace; font-size: 13px;">
				<strong>Site ID:</strong> <?php echo esc_html( $site_id ); ?><br>
				<strong>Shared Secret:</strong> <?php echo esc_html( $secret ); ?>
			</p>
		</div>

		<?php
		$cf_active = (bool) get_option( 'atg_cloudflare_detected', false );
		?>
		<div class="atg-tabs" style="margin-top: 20px;">
			<button class="button button-secondary <?php echo $cf_active ? 'active' : ''; ?>" onclick="switchEdgeTab(event, 'cf-worker')"><?php esc_html_e( 'Cloudflare Worker', 'ai-traffic-guardian' ); ?></button>
			<button class="button button-secondary <?php echo ! $cf_active ? 'active' : ''; ?>" onclick="switchEdgeTab(event, 'nginx-map')"><?php esc_html_e( 'Nginx Map Block', 'ai-traffic-guardian' ); ?></button>
		</div>

		<div id="cf-worker" class="edge-tab-content" style="margin-top: 20px; display: <?php echo $cf_active ? 'block' : 'none'; ?>;">
			<h3><?php esc_html_e( 'Cloudflare Worker Deployment', 'ai-traffic-guardian' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Copy the script below and paste it into a new Cloudflare Worker, or connect your API Token to deploy it automatically.', 'ai-traffic-guardian' ); ?></p>
			
			<textarea readonly style="width: 100%; height: 250px; font-family: monospace; font-size: 12px; background: #fafafa; border: 1px solid #ddd; padding: 10px; margin-top: 10px;"><?php echo esc_textarea( $worker ); ?></textarea>

			<h3 style="margin-top: 30px;"><?php esc_html_e( 'Deployment', 'ai-traffic-guardian' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Copy the worker script above and paste it into a new Cloudflare Worker via the Cloudflare dashboard. Then add the KV namespace binding and your site credentials as environment variables.', 'ai-traffic-guardian' ); ?></p>
			<button type="button" class="button button-secondary" onclick="navigator.clipboard.writeText(this.closest('.edge-tab-content').querySelector('textarea').value).then(function(){alert('Worker script copied to clipboard.')})">
				<?php esc_html_e( 'Copy Worker Script', 'ai-traffic-guardian' ); ?>
			</button>
		</div>

		<div id="nginx-map" class="edge-tab-content" style="display: <?php echo ! $cf_active ? 'block' : 'none'; ?>; margin-top: 20px;">
			<h3><?php esc_html_e( 'Nginx User-Agent Map Block', 'ai-traffic-guardian' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Add this block to your main Nginx configuration file (typically inside your http {} block) to reject blocked crawlers pre-boot.', 'ai-traffic-guardian' ); ?></p>
			
			<textarea readonly style="width: 100%; height: 250px; font-family: monospace; font-size: 12px; background: #fafafa; border: 1px solid #ddd; padding: 10px; margin-top: 10px;"><?php echo esc_textarea( $nginx ); ?></textarea>
		</div>
	</div>
</div>

<script>
function switchEdgeTab(evt, tabId) {
	var i, tabcontent, tablinks;
	tabcontent = document.getElementsByClassName("edge-tab-content");
	for (i = 0; i < tabcontent.length; i++) {
		tabcontent[i].style.display = "none";
	}
	tablinks = evt.currentTarget.parentNode.getElementsByClassName("button");
	for (i = 0; i < tablinks.length; i++) {
		tablinks[i].classList.remove("active");
	}
	document.getElementById(tabId).style.display = "block";
	evt.currentTarget.classList.add("active");
}
</script>

<?php
/**
 * SEO & AI discovery view: robots.txt + llms.txt.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="atg-page" id="atg-seo" data-atg-settings-form>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'robots.txt automation', 'ai-traffic-guardian' ); ?></h2>
			<p class="description" data-atg-seo-detected><?php esc_html_e( 'Rules are appended through the WordPress robots_txt filter, so SEO plugins keep working — nothing is overwritten.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div class="atg-form-grid">
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Mode', 'ai-traffic-guardian' ); ?></span>
				<select data-setting="robots_mode">
					<option value="auto"><?php esc_html_e( 'Automatic — reflect my policy matrix in robots.txt', 'ai-traffic-guardian' ); ?></option>
					<option value="manual"><?php esc_html_e( 'Manual — I will paste the snippet myself', 'ai-traffic-guardian' ); ?></option>
				</select>
			</label>
		</div>
		<div class="atg-card-head"><h3><?php esc_html_e( 'Generated rules (live preview)', 'ai-traffic-guardian' ); ?></h3></div>
		<pre class="atg-code" data-atg-robots-preview><?php esc_html_e( 'Loading…', 'ai-traffic-guardian' ); ?></pre>
		<button class="button" data-atg-copy-robots><?php esc_html_e( 'Copy snippet', 'ai-traffic-guardian' ); ?></button>
	</div>

	<div class="atg-card">
		<div class="atg-card-head">
			<h2><?php esc_html_e( 'llms.txt — AI discovery file', 'ai-traffic-guardian' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Served at /llms.txt. A curated map of your site for AI answer engines.', 'ai-traffic-guardian' ); ?></p>
		</div>
		<div class="atg-form-grid">
			<label class="atg-switch-row">
				<input type="checkbox" data-setting="llms_enabled" />
				<span><?php esc_html_e( 'Enable /llms.txt', 'ai-traffic-guardian' ); ?></span>
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Intro line', 'ai-traffic-guardian' ); ?></span>
				<input type="text" class="large-text" data-setting="llms_intro" placeholder="<?php esc_attr_e( 'One sentence describing your site to AI readers', 'ai-traffic-guardian' ); ?>" />
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Number of content links', 'ai-traffic-guardian' ); ?></span>
				<input type="number" min="5" max="100" data-setting="llms_posts" />
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'License type', 'ai-traffic-guardian' ); ?></span>
				<input type="text" class="regular-text" data-setting="llms_license" placeholder="CC-BY-4.0" />
			</label>
			<label class="atg-field-row">
				<span><?php esc_html_e( 'Optional URLs', 'ai-traffic-guardian' ); ?></span>
				<textarea class="large-text code" data-setting="llms_optional_urls" rows="4" placeholder="<?php esc_attr_e( 'Title|https://example.com/url (one per line)', 'ai-traffic-guardian' ); ?>"></textarea>
			</label>
		</div>
	</div>

	<p><button class="button button-primary button-hero" data-atg-save-settings><?php esc_html_e( 'Save SEO settings', 'ai-traffic-guardian' ); ?></button></p>
</div>

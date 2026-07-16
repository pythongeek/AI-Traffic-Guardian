<?php
/**
 * Analytics integrity module.
 *
 * Modes:
 *  - off         : do nothing.
 *  - compat      : load analytics for everyone, but flag suspected bot
 *                  sessions with a custom event parameter (GTM-safe).
 *  - conditional : do not output GA4/GTM/Cloudflare beacons for requests the
 *                  classifier flagged as non-human.
 *
 * Plus an optional server-side GA4 Measurement Protocol bridge for
 * WooCommerce purchases, which bots cannot fake.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Analytics
 */
class ATG_Analytics {

	/**
	 * Whether the current request was flagged non-human (set on
	 * atg_request_classified).
	 *
	 * @var bool
	 */
	private $flagged_bot = false;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		$plugin = ATG_Plugin::instance();
		if ( 'off' === $plugin->get( 'ga4_mode', 'compat' ) ) {
			return;
		}
		add_action( 'atg_request_classified', array( $this, 'capture' ) );
		add_filter( 'script_loader_tag', array( $this, 'filter_script_tag' ), 20, 3 );
		add_action( 'wp_head', array( $this, 'compat_flag' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_beacon' ) );

		if ( $plugin->get( 'ga4_server_purchase', false ) ) {
			add_action( 'woocommerce_payment_complete', array( $this, 'server_side_purchase' ) );
		}
	}

	/**
	 * Capture the classification for this request.
	 *
	 * @param array $decision Classifier decision.
	 */
	public function capture( $decision ) {
		$this->flagged_bot = in_array( $decision['classification'], array( 'bot', 'unknown_bot' ), true );
	}

	/**
	 * In conditional mode, strip analytics beacons for bot requests.
	 *
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Script handle.
	 * @param string $src    Script source.
	 * @return string
	 */
	public function filter_script_tag( $tag, $handle, $src ) {
		$plugin = ATG_Plugin::instance();
		if ( 'conditional' !== $plugin->get( 'ga4_mode' ) || ! $this->flagged_bot ) {
			return $tag;
		}
		$haystack = strtolower( (string) $src . ' ' . (string) $handle );
		$needles  = array( 'googletagmanager', 'gtag', 'google-analytics', 'analytics.js', 'gtm.js', 'cloudflareinsights', 'plausible', 'fathom', 'matomo' );
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $haystack, $needle ) ) {
				return ''; // Never fires for bots.
			}
		}
		return $tag;
	}

	/**
	 * In compat mode, tag suspected-bot sessions so admins can filter them in
	 * GA4 (register `atg_bot` as a custom dimension) or in GTM dataLayer.
	 */
	public function compat_flag() {
		$plugin = ATG_Plugin::instance();
		if ( ! $this->flagged_bot || 'compat' !== $plugin->get( 'ga4_mode' ) ) {
			return;
		}
		echo "<script>(function(){window.dataLayer=window.dataLayer||[];window.dataLayer.push({'atg_bot':'true'});window.addEventListener('load',function(){if(typeof window.gtag==='function'){window.gtag('set',{'atg_bot':'true'});}});})();</script>\n";
	}

	/**
	 * Enqueue the accessible human-confirmation beacon.
	 */
	public function enqueue_beacon() {
		if ( is_user_logged_in() ) {
			return;
		}
		wp_enqueue_script( 'atg-frontend', ATG_PLUGIN_URL . 'assets/js/frontend.js', array(), ATG_VERSION, true );
		wp_localize_script(
			'atg-frontend',
			'ATG_FRONT',
			array(
				'beacon' => esc_url_raw( rest_url( 'atg/v1/beacon' ) ),
			)
		);
	}

	/**
	 * Server-side GA4 purchase event (bots cannot fake this).
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function server_side_purchase( $order_id ) {
		$plugin = ATG_Plugin::instance();
		$mid    = trim( (string) $plugin->get( 'ga4_measurement_id', '' ) );
		$secret = trim( (string) $plugin->get( 'ga4_api_secret', '' ) );
		if ( '' === $mid || '' === $secret || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'item_id'   => (string) $item->get_product_id(),
				'item_name' => $item->get_name(),
				'quantity'  => $item->get_quantity(),
				'price'     => (float) $order->get_item_total( $item, false ),
			);
		}
		$client_id = isset( $_COOKIE['_ga'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['_ga'] ) ) : 'atg.' . wp_generate_password( 20, false, false );
		$client_id = preg_replace( '/^GA\d\.\d\./', '', $client_id );

		$body = array(
			'client_id' => $client_id,
			'events'    => array(
				array(
					'name'   => 'purchase',
					'params' => array(
						'transaction_id' => (string) $order->get_order_number(),
						'value'          => (float) $order->get_total(),
						'currency'       => $order->get_currency(),
						'items'          => $items,
						'engagement_time_msec' => 100,
					),
				),
			),
		);
		wp_remote_post(
			add_query_arg(
				array(
					'measurement_id' => $mid,
					'api_secret'     => $secret,
				),
				'https://www.google-analytics.com/mp/collect'
			),
			array(
				'timeout' => 5,
				'body'    => wp_json_encode( $body ),
				'headers' => array( 'Content-Type' => 'application/json' ),
			)
		);
	}
}

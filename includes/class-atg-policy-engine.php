<?php
/**
 * Policy engine: granular Vendor × Purpose → Action matrix plus one-click
 * presets. Actions: allow | throttle | block.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Policy_Engine
 */
class ATG_Policy_Engine {

	const ACTIONS = array( 'allow', 'throttle', 'block' );

	/**
	 * Sensible default matrix following industry guidance:
	 * allow citation/search, throttle training, block junk.
	 *
	 * @return array vendor => purpose => action
	 */
	public static function default_matrix() {
		return array(
			'Google'          => array(
				'search_engine' => 'allow',
				'ai_search'     => 'allow',
				'ai_training'   => 'throttle',
				'agent_proxy'   => 'allow',
			),
			'Microsoft'       => array( 'search_engine' => 'allow' ),
			'DuckDuckGo'      => array( 'search_engine' => 'allow' ),
			'Apple'           => array( 'search_engine' => 'allow' ),
			'Sogou'           => array( 'search_engine' => 'allow' ),
			'Ecosia'          => array( 'search_engine' => 'allow' ),
			'Qwant'           => array( 'search_engine' => 'allow' ),
			'OpenAI'          => array(
				'ai_search'   => 'allow',
				'agent_proxy' => 'allow',
				'ai_training' => 'throttle',
			),
			'Anthropic'       => array(
				'ai_search'   => 'allow',
				'agent_proxy' => 'allow',
				'ai_training' => 'throttle',
			),
			'Perplexity'      => array(
				'ai_search'   => 'allow',
				'agent_proxy' => 'allow',
			),
			'You.com'         => array( 'ai_search' => 'allow' ),
			'Common Crawl'    => array( 'ai_training' => 'throttle' ),
			'ByteDance'       => array( 'ai_training' => 'throttle' ),
			'Meta'            => array(
				'ai_training' => 'throttle',
				'ai_search'   => 'allow',
				'social'      => 'allow',
			),
			'Amazon'          => array( 'ai_training' => 'throttle' ),
			'Cohere'          => array( 'ai_training' => 'throttle' ),
			'Diffbot'         => array( 'ai_training' => 'throttle' ),
			'Allen Institute' => array( 'ai_training' => 'throttle' ),
			'Timpi'           => array( 'ai_training' => 'throttle' ),
			'Omgili (Webz.io)' => array( 'ai_training' => 'throttle' ),
			'Ahrefs'          => array( 'seo_tool' => 'throttle' ),
			'Semrush'         => array( 'seo_tool' => 'throttle' ),
			'Majestic'        => array( 'seo_tool' => 'throttle' ),
			'Moz'             => array( 'seo_tool' => 'throttle' ),
			'Screaming Frog'  => array( 'seo_tool' => 'allow' ),
			'X/Twitter'       => array( 'social' => 'allow' ),
			'LinkedIn'        => array( 'social' => 'allow' ),
			'Discord'         => array( 'social' => 'allow' ),
			'Slack'           => array( 'social' => 'allow' ),
			'Telegram'        => array( 'social' => 'allow' ),
			'Pinterest'       => array( 'social' => 'allow' ),
			'Feedly'          => array( 'feed' => 'allow' ),
			'Inoreader'       => array( 'feed' => 'allow' ),
			'NewsBlur'        => array( 'feed' => 'allow' ),
			'UptimeRobot'     => array( 'monitor' => 'allow' ),
			'Pingdom'         => array( 'monitor' => 'allow' ),
			'StatusCake'      => array( 'monitor' => 'allow' ),
			'Better Stack'    => array( 'monitor' => 'allow' ),
			'Huawei'          => array( 'scraper' => 'block' ),
			'Unknown'         => array( 'scraper' => 'block' ),
		);
	}

	/**
	 * Presets that rewrite the whole matrix in one click.
	 *
	 * @return array name => array(label, description, matrix)
	 */
	public static function presets() {
		$base = self::default_matrix();

		$apply = function ( $matrix, $purpose, $action ) {
			foreach ( $matrix as $vendor => $purposes ) {
				if ( isset( $purposes[ $purpose ] ) ) {
					$matrix[ $vendor ][ $purpose ] = $action;
				}
			}
			return $matrix;
		};

		// Publisher: maximize AI citation — allow everything citation-related.
		$publisher = $apply( $base, 'ai_search', 'allow' );
		$publisher = $apply( $publisher, 'agent_proxy', 'allow' );
		$publisher = $apply( $publisher, 'ai_training', 'throttle' );

		// WooCommerce: performance-first — block training, throttle SEO tools.
		$woo = $apply( $base, 'ai_training', 'block' );
		$woo = $apply( $woo, 'seo_tool', 'throttle' );

		// Private/membership: block all scraping incl. AI search & SEO tools.
		$private = $apply( $base, 'ai_training', 'block' );
		$private = $apply( $private, 'ai_search', 'block' );
		$private = $apply( $private, 'seo_tool', 'block' );
		$private = $apply( $private, 'agent_proxy', 'throttle' );

		return array(
			'publisher'   => array(
				'label'       => __( 'Content publisher — maximize AI citation', 'ai-traffic-guardian' ),
				'description' => __( 'Allow AI search and agentic fetchers, throttle training crawlers. Best for blogs, news and content sites that want to be cited by ChatGPT, Perplexity and AI Overviews.', 'ai-traffic-guardian' ),
				'matrix'      => $publisher,
			),
			'woocommerce' => array(
				'label'       => __( 'WooCommerce store — protect checkout & performance', 'ai-traffic-guardian' ),
				'description' => __( 'Block AI training crawlers, throttle SEO tools, keep search engines and citation bots so products stay discoverable.', 'ai-traffic-guardian' ),
				'matrix'      => $woo,
			),
			'private'     => array(
				'label'       => __( 'Private / membership site — block all scraping', 'ai-traffic-guardian' ),
				'description' => __( 'Block AI crawlers and SEO tools entirely, throttle agentic fetchers. Search engines and business-critical traffic stay allowed.', 'ai-traffic-guardian' ),
				'matrix'      => $private,
			),
		);
	}

	/**
	 * Get the current matrix (option, merged over defaults).
	 *
	 * @return array
	 */
	public function matrix() {
		$stored = get_option( 'atg_policy_matrix', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::default_matrix(), $stored );
	}

	/**
	 * Resolve the action for a vendor + purpose.
	 *
	 * @param string $vendor  Vendor name.
	 * @param string $purpose Purpose key.
	 * @return string allow|throttle|block
	 */
	public function action_for( $vendor, $purpose ) {
		$matrix = $this->matrix();
		if ( isset( $matrix[ $vendor ][ $purpose ] ) && in_array( $matrix[ $vendor ][ $purpose ], self::ACTIONS, true ) ) {
			return $matrix[ $vendor ][ $purpose ];
		}
		// Fallback by purpose when vendor has no explicit row.
		$fallbacks = array(
			'search_engine' => 'allow',
			'ai_search'     => 'allow',
			'agent_proxy'   => 'allow',
			'ai_training'   => 'throttle',
			'seo_tool'      => 'throttle',
			'social'        => 'allow',
			'feed'          => 'allow',
			'monitor'       => 'allow',
			'scraper'       => 'block',
		);
		return isset( $fallbacks[ $purpose ] ) ? $fallbacks[ $purpose ] : 'throttle';
	}

	/**
	 * Set a single cell.
	 *
	 * @param string $vendor  Vendor.
	 * @param string $purpose Purpose.
	 * @param string $action  Action.
	 * @return bool
	 */
	public function set( $vendor, $purpose, $action ) {
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return false;
		}
		$matrix = $this->matrix();
		if ( ! isset( $matrix[ $vendor ] ) ) {
			$matrix[ $vendor ] = array();
		}
		$matrix[ $vendor ][ $purpose ] = $action;
		update_option( 'atg_policy_matrix', $matrix );
		return true;
	}

	/**
	 * Apply a preset.
	 *
	 * @param string $name Preset key.
	 * @return bool
	 */
	public function apply_preset( $name ) {
		$presets = self::presets();
		if ( ! isset( $presets[ $name ] ) ) {
			return false;
		}
		update_option( 'atg_policy_matrix', $presets[ $name ]['matrix'] );
		return true;
	}

	/**
	 * Update the entire policy matrix.
	 *
	 * @param array $matrix Matrix data.
	 */
	public function update_matrix( $matrix ) {
		if ( is_array( $matrix ) ) {
			update_option( 'atg_policy_matrix', $matrix );
		}
	}
}

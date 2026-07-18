<?php
/**
 * robots.txt manager. Uses the core `robots_txt` filter (priority 99) so it
 * APPENDS to output from Yoast / Rank Math / SEOPress / AIOSEO instead of
 * fighting them — no race conditions, no overwritten files (gap-analysis P2).
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Robots
 */
class ATG_Robots {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		$plugin = ATG_Plugin::instance();
		if ( 'auto' === $plugin->get( 'robots_mode', 'auto' ) ) {
			add_filter( 'robots_txt', array( $this, 'append_rules' ), 99, 2 );
		}
	}

	/**
	 * Append per-bot rules derived from the live policy matrix.
	 *
	 * @param string $output Existing robots.txt content.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	public function append_rules( $output, $public ) {
		$rules = $this->build_rules();
		if ( '' === $rules ) {
			return $output;
		}
		return rtrim( $output ) . "\n\n" . $rules . "\n";
	}

	/**
	 * Build the ATG rule block from the policy matrix.
	 *
	 * @return string
	 */
	public function build_rules() {
		$plugin = ATG_Plugin::instance();
		$matrix = $plugin->policy->matrix();

		$blocks = array( '# BEGIN AI Traffic Guardian' );
		$added  = 0;

		foreach ( ATG_Bot_Database::signatures() as $sig ) {
			$action = isset( $matrix[ $sig['vendor'] ][ $sig['purpose'] ] )
				? $matrix[ $sig['vendor'] ][ $sig['purpose'] ]
				: $plugin->policy->action_for( $sig['vendor'], $sig['purpose'] );

			if ( 'allow' === $action ) {
				continue;
			}

			if ( ! in_array( $sig['purpose'], array( 'ai_training', 'ai_search', 'seo_tool', 'search_engine' ), true ) ) {
				continue;
			}

			$agent = $this->ua_token( $sig['name'] );
			if ( '' === $agent ) {
				continue;
			}

			$blocks[] = 'User-agent: ' . $agent;
			if ( 'block' === $action ) {
				$blocks[] = 'Disallow: /';
			} else {
				$blocks[] = 'Crawl-delay: 10';
			}
			$blocks[] = '';
			$added++;
		}

		$blocks[] = '# END AI Traffic Guardian';
		return $added ? implode( "\n", $blocks ) : '';
	}

	/**
	 * Derive the robots.txt UA token from a signature name.
	 *
	 * @param string $name Bot name.
	 * @return string
	 */
	private function ua_token( $name ) {
		$map = array(
			'Google-Extended'       => 'Google-Extended',
			'Facebook External Hit' => 'facebookexternalhit',
			'DotNet/HTTP libs'      => '',
		);
		if ( isset( $map[ $name ] ) ) {
			return $map[ $name ];
		}
		// Strip version-ish suffixes, keep the product token.
		$token = preg_replace( '/\s.*$/', '', $name );
		return preg_match( '/^[A-Za-z0-9._-]+$/', $token ) ? $token : '';
	}

	/**
	 * Detect active SEO plugins (for the settings UI notice).
	 *
	 * @return array List of detected plugin names.
	 */
	public static function detected_seo_plugins() {
		$found = array();
		if ( defined( 'WPSEO_VERSION' ) ) {
			$found[] = 'Yoast SEO';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$found[] = 'Rank Math';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$found[] = 'SEOPress';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			$found[] = 'All in One SEO';
		}
		return $found;
	}
}

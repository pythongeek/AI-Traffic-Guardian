<?php
/**
 * llms.txt generator: an AI-discovery file served at /llms.txt describing the
 * site and its key content for answer engines.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Llms
 */
class ATG_Llms {

	const QUERY_VAR = 'atg_llms';

	/**
	 * Register hooks.
	 */
	public function hooks() {
		// Defer add_rewrite_rule() to 'init' so that $wp_rewrite is fully
		// initialised before we call it.  Calling it during plugins_loaded
		// (our boot priority 5) causes a fatal "Call to a member function
		// add_rule() on null" because $wp_rewrite hasn't been created yet.
		add_action( 'init', array( __CLASS__, 'register_rewrite' ), 1 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve' ) );
	}

	/**
	 * Add the rewrite rule.
	 */
	public static function register_rewrite() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Allow the query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Serve the file when enabled.
	 */
	public function maybe_serve() {
		$plugin = ATG_Plugin::instance();
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		if ( ! $plugin->get( 'llms_enabled', false ) ) {
			status_header( 404 );
			exit;
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8', true );
		header( 'X-Robots-Tag: noindex', true );
		// Plain-text response: no HTML escaping (Content-Type prevents markup parsing).
		echo apply_filters( 'atg_llms_content', $this->render() ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Render the llms.txt body.
	 *
	 * @return string
	 */
	public function render() {
		$plugin  = ATG_Plugin::instance();
		$lines   = array();
		$lines[] = '# ' . get_bloginfo( 'name' );
		$desc    = $plugin->get( 'llms_intro', '' );
		if ( '' === trim( (string) $desc ) ) {
			$desc = get_bloginfo( 'description' );
		}
		if ( $desc ) {
			$lines[] = '';
			$lines[] = '> ' . $desc;
		}

		$license = $plugin->get( 'llms_license', 'CC-BY-4.0' );
		if ( $license ) {
			$lines[] = '';
			$lines[] = 'Info:';
			$lines[] = '- License: ' . $license;
		}

		$lines[] = '';
		$lines[] = '## Content';

		$post_types = array( 'post', 'page' );
		if ( class_exists( 'WooCommerce' ) ) {
			$post_types[] = 'product';
		}

		$posts = get_posts(
			array(
				'post_type'      => apply_filters( 'atg_llms_post_types', $post_types ),
				'posts_per_page' => (int) $plugin->get( 'llms_posts', 20 ),
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		foreach ( $posts as $post ) {
			$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( $post->post_content, 20 );
			$lines[] = sprintf( '- [%s](%s): %s', $post->post_title, get_permalink( $post ), $excerpt );
		}

		$optional_text = $plugin->get( 'llms_optional_urls', '' );
		$filtered_links = apply_filters( 'atg_llms_optional_links', array() );

		if ( $optional_text || ! empty( $filtered_links ) ) {
			$lines[] = '';
			$lines[] = '## Optional';

			if ( $optional_text ) {
				$opt_lines = explode( "\n", $optional_text );
				foreach ( $opt_lines as $opt_line ) {
					$opt_line = trim( $opt_line );
					if ( '' === $opt_line ) {
						continue;
					}
					if ( false !== strpos( $opt_line, '|' ) ) {
						list( $title, $url ) = explode( '|', $opt_line, 2 );
						$lines[] = sprintf( '- [%s](%s)', trim( $title ), trim( $url ) );
					} else {
						$lines[] = sprintf( '- <%s>', $opt_line );
					}
				}
			}

			foreach ( $filtered_links as $link ) {
				if ( is_array( $link ) && count( $link ) >= 2 ) {
					$title   = $link[0];
					$url     = $link[1];
					$link_desc = isset( $link[2] ) ? $link[2] : '';
					$lines[] = sprintf( '- [%s](%s)%s', $title, $url, $link_desc ? ': ' . $link_desc : '' );
				}
			}
		}

		return implode( "\n", $lines ) . "\n";
	}
}

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
		echo $this->render(); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Render the llms.txt body.
	 *
	 * @return string
	 */
	public function render() {
		$plugin = ATG_Plugin::instance();
		$lines  = array();
		$lines[] = '# ' . get_bloginfo( 'name' );
		$desc    = $plugin->get( 'llms_intro', '' );
		if ( '' === trim( (string) $desc ) ) {
			$desc = get_bloginfo( 'description' );
		}
		if ( $desc ) {
			$lines[] = '';
			$lines[] = '> ' . $desc;
		}
		$lines[] = '';
		$lines[] = '## Content';

		$posts = get_posts(
			array(
				'post_type'      => apply_filters( 'atg_llms_post_types', array( 'post', 'page' ) ),
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
		return implode( "\n", $lines ) . "\n";
	}
}

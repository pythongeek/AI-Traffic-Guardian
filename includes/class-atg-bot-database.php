<?php
/**
 * Bot signature database: vendors, purposes, UA patterns, verification meta.
 *
 * Purposes:
 *  - search_engine : classic verified search crawlers (Googlebot, Bingbot)
 *  - ai_search     : AI citation / answer-engine crawlers (OAI-SearchBot…)
 *  - ai_training   : AI training-data crawlers (GPTBot, CCBot, Bytespider…)
 *  - agent_proxy   : human-intent proxies / agentic fetchers (ChatGPT-User…)
 *  - seo_tool      : commercial SEO crawlers (Ahrefs, Semrush…)
 *  - social        : link-preview / social crawlers
 *  - feed          : RSS / feed readers
 *  - monitor       : uptime & performance monitors (business-critical)
 *  - scraper       : known junk / malicious bots
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_Bot_Database
 */
class ATG_Bot_Database {

	/**
	 * Full signature table. Each entry:
	 * name, vendor, purpose, pattern (regex, case-insensitive, matched on UA),
	 * verify: none|rdns|ip_range, rdns_suffix, ip_source (URL returning JSON
	 * prefixes), doc (optional info URL).
	 *
	 * @return array
	 */
	public static function signatures() {
		$sigs = array(
			// ------------------------------------------------------------------
			// Classic search engines (reverse-DNS verifiable).
			// ------------------------------------------------------------------
			array(
				'name'        => 'Googlebot',
				'vendor'      => 'Google',
				'purpose'     => 'search_engine',
				'pattern'     => '#Googlebot(?!-)|Googlebot/#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.googlebot.com', '.google.com' ),
			),
			array(
				'name'        => 'Googlebot-Image',
				'vendor'      => 'Google',
				'purpose'     => 'search_engine',
				'pattern'     => '#Googlebot-Image#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.googlebot.com', '.google.com' ),
			),
			array(
				'name'        => 'Googlebot-News',
				'vendor'      => 'Google',
				'purpose'     => 'search_engine',
				'pattern'     => '#Googlebot-News#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.googlebot.com', '.google.com' ),
			),
			array(
				'name'        => 'Googlebot-Video',
				'vendor'      => 'Google',
				'purpose'     => 'search_engine',
				'pattern'     => '#Googlebot-Video#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.googlebot.com', '.google.com' ),
			),
			array(
				'name'        => 'AdsBot-Google',
				'vendor'      => 'Google',
				'purpose'     => 'search_engine',
				'pattern'     => '#AdsBot-Google#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.googlebot.com', '.google.com' ),
			),
			array(
				'name'        => 'Google Shopping',
				'vendor'      => 'Google',
				'purpose'     => 'search_engine',
				'pattern'     => '#Google-Shopping|Googlebot-Product#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.googlebot.com', '.google.com' ),
			),
			array(
				'name'    => 'Ecosia',
				'vendor'  => 'Ecosia',
				'purpose' => 'search_engine',
				'pattern' => '#Ecosia#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Qwant',
				'vendor'  => 'Qwant',
				'purpose' => 'search_engine',
				'pattern' => '#Qwant#i',
				'verify'  => 'none',
			),
			array(
				'name'        => 'Sogou Spider',
				'vendor'      => 'Sogou',
				'purpose'     => 'search_engine',
				'pattern'     => '#Sogou web spider#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.sogou.com' ),
			),
			array(
				'name'        => 'Bingbot',
				'vendor'      => 'Microsoft',
				'purpose'     => 'search_engine',
				'pattern'     => '#\bbingbot\b#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.search.msn.com' ),
			),
			array(
				'name'    => 'Bing Shopping',
				'vendor'  => 'Microsoft',
				'purpose' => 'search_engine',
				'pattern' => '#BingBot-Shopping|MSShoppingBot#i',
				'verify'  => 'none',
			),
			array(
				'name'        => 'DuckDuckBot',
				'vendor'      => 'DuckDuckGo',
				'purpose'     => 'search_engine',
				'pattern'     => '#DuckDuckBot#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.duckduckgo.com' ),
			),
			array(
				'name'        => 'Applebot',
				'vendor'      => 'Apple',
				'purpose'     => 'search_engine',
				'pattern'     => '#\bApplebot\b#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.applebot.apple.com' ),
			),
			array(
				'name'    => 'Alexa Crawler',
				'vendor'  => 'Amazon',
				'purpose' => 'search_engine',
				'pattern' => '#ia_archiver.*Alexa|Alexa Internet#i',
				'verify'  => 'none',
			),
			array(
				'name'        => 'YandexBot',
				'vendor'      => 'Yandex',
				'purpose'     => 'search_engine',
				'pattern'     => '#YandexBot|YandexImages|YandexVideo|YandexNews#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.yandex.com', '.yandex.net', '.yandex.ru' ),
			),
			array(
				'name'        => 'Baiduspider',
				'vendor'      => 'Baidu',
				'purpose'     => 'search_engine',
				'pattern'     => '#Baiduspider|baiduspider#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.baidu.com', '.baidu.jp' ),
			),
			array(
				'name'        => 'Yahoo Slurp',
				'vendor'      => 'Yahoo',
				'purpose'     => 'search_engine',
				'pattern'     => '#Yahoo! Slurp|Slurp\.com#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.crawl.yahoo.net' ),
			),
			array(
				'name'        => 'Yeti (Naver)',
				'vendor'      => 'Naver',
				'purpose'     => 'search_engine',
				'pattern'     => '#\bYeti\b.*Naver|NaverBot#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.naver.com' ),
			),
			array(
				'name'    => 'Brave Search',
				'vendor'  => 'Brave',
				'purpose' => 'search_engine',
				'pattern' => '#Brave-Search|BraveBot#i',
				'verify'  => 'none',
			),
			array(
				'name'        => 'MojeekBot',
				'vendor'      => 'Mojeek',
				'purpose'     => 'search_engine',
				'pattern'     => '#MojeekBot#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.mojeek.com' ),
			),
			array(
				'name'    => 'KagiBot',
				'vendor'  => 'Kagi',
				'purpose' => 'search_engine',
				'pattern' => '#Kagi|Neva#i',
				'verify'  => 'none',
			),
			array(
				'name'        => 'SeznamBot',
				'vendor'      => 'Seznam',
				'purpose'     => 'search_engine',
				'pattern'     => '#SeznamBot#i',
				'verify'      => 'rdns',
				'rdns_suffix' => array( '.seznam.cz' ),
			),
			array(
				'name'    => 'Internet Archive',
				'vendor'  => 'Internet Archive',
				'purpose' => 'search_engine',
				'pattern' => '#ia_archiver|archive\.org_bot|Wayback#i',
				'verify'  => 'none',
			),

			// ------------------------------------------------------------------
			// AI search / citation bots — referral value, keep by default.
			// ------------------------------------------------------------------
			array(
				'name'      => 'OAI-SearchBot',
				'vendor'    => 'OpenAI',
				'purpose'   => 'ai_search',
				'pattern'   => '#OAI-SearchBot#i',
				'verify'    => 'ip_range',
				'ip_source' => 'https://openai.com/searchbot.json',
			),
			array(
				'name'    => 'PerplexityBot',
				'vendor'  => 'Perplexity',
				'purpose' => 'ai_search',
				'pattern' => '#PerplexityBot#i',
				'verify'  => 'ip_range',
				'ip_source' => 'https://www.perplexity.ai/perplexitybot.json',
			),
			array(
				'name'    => 'Claude-SearchBot',
				'vendor'  => 'Anthropic',
				'purpose' => 'ai_search',
				'pattern' => '#Claude-SearchBot#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Google-CloudVertexBot',
				'vendor'  => 'Google',
				'purpose' => 'ai_search',
				'pattern' => '#Google-CloudVertexBot#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Meta-ExternalFetcher',
				'vendor'  => 'Meta',
				'purpose' => 'ai_search',
				'pattern' => '#meta-externalfetcher#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'YouBot',
				'vendor'  => 'You.com',
				'purpose' => 'ai_search',
				'pattern' => '#\bYouBot\b#i',
				'verify'  => 'none',
			),

			// ------------------------------------------------------------------
			// Human-intent proxies (agentic AI acting for a real user).
			// Never treat as generic bots (gap-analysis P3).
			// ------------------------------------------------------------------
			array(
				'name'      => 'ChatGPT-User',
				'vendor'    => 'OpenAI',
				'purpose'   => 'agent_proxy',
				'pattern'   => '#ChatGPT-User#i',
				'verify'    => 'ip_range',
				'ip_source' => 'https://openai.com/chatgpt-user.json',
			),
			array(
				'name'    => 'Claude-User',
				'vendor'  => 'Anthropic',
				'purpose' => 'agent_proxy',
				'pattern' => '#Claude-User#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Perplexity-User',
				'vendor'  => 'Perplexity',
				'purpose' => 'agent_proxy',
				'pattern' => '#Perplexity-User#i',
				'verify'  => 'ip_range',
				'ip_source' => 'https://www.perplexity.ai/perplexity-user.json',
			),
			array(
				'name'    => 'Gemini-User',
				'vendor'  => 'Google',
				'purpose' => 'agent_proxy',
				'pattern' => '#Gemini-Deep-Research|Google-Agent#i',
				'verify'  => 'none',
			),

			// ------------------------------------------------------------------
			// AI training crawlers — no direct referral value.
			// ------------------------------------------------------------------
			array(
				'name'      => 'GPTBot',
				'vendor'    => 'OpenAI',
				'purpose'   => 'ai_training',
				'pattern'   => '#\bGPTBot\b#i',
				'verify'    => 'ip_range',
				'ip_source' => 'https://openai.com/gptbot.json',
			),
			array(
				'name'    => 'ClaudeBot',
				'vendor'  => 'Anthropic',
				'purpose' => 'ai_training',
				'pattern' => '#\bClaudeBot\b#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'anthropic-ai',
				'vendor'  => 'Anthropic',
				'purpose' => 'ai_training',
				'pattern' => '#anthropic-ai#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Google-Extended',
				'vendor'  => 'Google',
				'purpose' => 'ai_training',
				'pattern' => '#Google-Extended#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'CCBot',
				'vendor'  => 'Common Crawl',
				'purpose' => 'ai_training',
				'pattern' => '#\bCCBot\b#i',
				'verify'  => 'rdns',
				'rdns_suffix' => array( '.commoncrawl.org' ),
			),
			array(
				'name'    => 'Bytespider',
				'vendor'  => 'ByteDance',
				'purpose' => 'ai_training',
				'pattern' => '#Bytespider#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Meta-ExternalAgent',
				'vendor'  => 'Meta',
				'purpose' => 'ai_training',
				'pattern' => '#meta-externalagent#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'FacebookBot',
				'vendor'  => 'Meta',
				'purpose' => 'ai_training',
				'pattern' => '#facebookbot#i',
				'verify'  => 'rdns',
				'rdns_suffix' => array( '.facebook.com' ),
			),
			array(
				'name'    => 'Amazonbot',
				'vendor'  => 'Amazon',
				'purpose' => 'ai_training',
				'pattern' => '#\bAmazonbot\b#i',
				'verify'  => 'ip_range',
				'ip_source' => 'https://developer.amazon.com/amazonbot/ip-ranges.json',
			),
			array(
				'name'    => 'cohere-ai',
				'vendor'  => 'Cohere',
				'purpose' => 'ai_training',
				'pattern' => '#cohere-ai|cohere-training-data-crawler#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Diffbot',
				'vendor'  => 'Diffbot',
				'purpose' => 'ai_training',
				'pattern' => '#\bDiffbot\b#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'AI2Bot',
				'vendor'  => 'Allen Institute',
				'purpose' => 'ai_training',
				'pattern' => '#\bAI2Bot\b|Ai2Bot-Dolma#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Timpibot',
				'vendor'  => 'Timpi',
				'purpose' => 'ai_training',
				'pattern' => '#Timpibot#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'omgili',
				'vendor'  => 'Omgili (Webz.io)',
				'purpose' => 'ai_training',
				'pattern' => '#\bomgili\b#i',
				'verify'  => 'none',
			),

			// ------------------------------------------------------------------
			// SEO tools (site owners often pay for these — never auto-block).
			// ------------------------------------------------------------------
			array(
				'name'    => 'AhrefsBot',
				'vendor'  => 'Ahrefs',
				'purpose' => 'seo_tool',
				'pattern' => '#AhrefsBot#i',
				'verify'  => 'rdns',
				'rdns_suffix' => array( '.ahrefs.com', '.ahrefs.net' ),
			),
			array(
				'name'    => 'SemrushBot',
				'vendor'  => 'Semrush',
				'purpose' => 'seo_tool',
				'pattern' => '#SemrushBot#i',
				'verify'  => 'rdns',
				'rdns_suffix' => array( '.semrush.com' ),
			),
			array(
				'name'    => 'MJ12bot',
				'vendor'  => 'Majestic',
				'purpose' => 'seo_tool',
				'pattern' => '#MJ12bot#i',
				'verify'  => 'rdns',
				'rdns_suffix' => array( '.majestic12.co.uk', '.majestic.com' ),
			),
			array(
				'name'    => 'DotBot',
				'vendor'  => 'Moz',
				'purpose' => 'seo_tool',
				'pattern' => '#\bDotBot\b#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Screaming Frog',
				'vendor'  => 'Screaming Frog',
				'purpose' => 'seo_tool',
				'pattern' => '#Screaming Frog SEO Spider#i',
				'verify'  => 'none',
			),

			// ------------------------------------------------------------------
			// Social / link-preview crawlers (drive human traffic).
			// ------------------------------------------------------------------
			array(
				'name'    => 'Facebook External Hit',
				'vendor'  => 'Meta',
				'purpose' => 'social',
				'pattern' => '#facebookexternalhit#i',
				'verify'  => 'rdns',
				'rdns_suffix' => array( '.facebook.com' ),
			),
			array(
				'name'    => 'Twitterbot',
				'vendor'  => 'X/Twitter',
				'purpose' => 'social',
				'pattern' => '#Twitterbot#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'LinkedInBot',
				'vendor'  => 'LinkedIn',
				'purpose' => 'social',
				'pattern' => '#LinkedInBot#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Discordbot',
				'vendor'  => 'Discord',
				'purpose' => 'social',
				'pattern' => '#Discordbot#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Slackbot',
				'vendor'  => 'Slack',
				'purpose' => 'social',
				'pattern' => '#Slackbot-LinkExpanding|Slack-ImgProxy#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'TelegramBot',
				'vendor'  => 'Telegram',
				'purpose' => 'social',
				'pattern' => '#TelegramBot#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'WhatsApp',
				'vendor'  => 'Meta',
				'purpose' => 'social',
				'pattern' => '#WhatsApp/\d#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Pinterest',
				'vendor'  => 'Pinterest',
				'purpose' => 'social',
				'pattern' => '#Pinterest/\d|Pinterestbot#i',
				'verify'  => 'none',
			),

			// ------------------------------------------------------------------
			// Feed readers.
			// ------------------------------------------------------------------
			array(
				'name'    => 'Feedly',
				'vendor'  => 'Feedly',
				'purpose' => 'feed',
				'pattern' => '#Feedly#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Inoreader',
				'vendor'  => 'Inoreader',
				'purpose' => 'feed',
				'pattern' => '#Inoreader#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'NewsBlur',
				'vendor'  => 'NewsBlur',
				'purpose' => 'feed',
				'pattern' => '#NewsBlur#i',
				'verify'  => 'none',
			),

			// ------------------------------------------------------------------
			// Uptime monitors — business-critical, allowlist by default.
			// ------------------------------------------------------------------
			array(
				'name'    => 'Google Site Verification',
				'vendor'  => 'Google',
				'purpose' => 'monitor',
				'pattern' => '#Google-Site-Verification|Google-InspectionTool#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Bing Webmaster',
				'vendor'  => 'Microsoft',
				'purpose' => 'monitor',
				'pattern' => '#bingpreview|BingPreview#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Google Lighthouse',
				'vendor'  => 'Google',
				'purpose' => 'monitor',
				'pattern' => '#Chrome-Lighthouse|Google Page Speed#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'UptimeRobot',
				'vendor'  => 'UptimeRobot',
				'purpose' => 'monitor',
				'pattern' => '#UptimeRobot#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'Pingdom',
				'vendor'  => 'Pingdom',
				'purpose' => 'monitor',
				'pattern' => '#Pingdom\.com|pingdom#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'StatusCake',
				'vendor'  => 'StatusCake',
				'purpose' => 'monitor',
				'pattern' => '#StatusCake#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'BetterUptime',
				'vendor'  => 'Better Stack',
				'purpose' => 'monitor',
				'pattern' => '#Better\s?Uptime|betteruptime#i',
				'verify'  => 'none',
			),

			// ------------------------------------------------------------------
			// Known junk / hostile.
			// ------------------------------------------------------------------
			array(
				'name'    => 'PetalBot',
				'vendor'  => 'Huawei',
				'purpose' => 'scraper',
				'pattern' => '#PetalBot#i',
				'verify'  => 'none',
			),
			array(
				'name'    => 'DotNet/HTTP libs',
				'vendor'  => 'Unknown',
				'purpose' => 'scraper',
				'pattern' => '#^(python-requests|python-urllib|curl/|wget/|libwww-perl|Go-http-client|Java/|okhttp|Scrapy|httpx|aiohttp)#i',
				'verify'  => 'none',
			),
		);

		$sigs = apply_filters( 'atg_bot_signatures', $sigs );

		if ( ! ATG_Licensing::is_pro() ) {
			$allowed_vendors = array( 'Google', 'OpenAI', 'Anthropic', 'Perplexity' );
			$sigs = array_filter( $sigs, function( $sig ) use ( $allowed_vendors ) {
				return in_array( $sig['vendor'], $allowed_vendors, true );
			} );
		}

		return $sigs;
	}

	/**
	 * Match a user agent against the signature table.
	 *
	 * @param string $ua User agent string.
	 * @return array|null Signature entry or null.
	 */
	public function match( $ua ) {
		if ( '' === $ua ) {
			return null;
		}
		foreach ( self::signatures() as $sig ) {
			if ( preg_match( $sig['pattern'], $ua ) ) {
				return $sig;
			}
		}
		return null;
	}

	/**
	 * Heuristic: does this UA look like an unknown automated client?
	 * Used for the "New AI bot detected" alert system.
	 *
	 * @param string $ua User agent.
	 * @return bool
	 */
	public function looks_automated( $ua ) {
		if ( '' === trim( $ua ) ) {
			return true; // Empty UA on a browser route is a strong bot signal.
		}
		$ai_hint = '#(bot|crawler|spider|scrape|gpt|claude|llm|openai|anthropic|deepseek|mistral|qwen|\bai[-_ ]|agent)#i';
		$browser = '#(Mozilla/5\.0|Chrome/|Safari/|Firefox/|Edg/|OPR/)#i';
		if ( preg_match( $ai_hint, $ua ) && ! preg_match( $browser, $ua ) ) {
			return true;
		}
		if ( preg_match( '#(bot|crawler|spider)#i', $ua ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Distinct vendors present in the signature table.
	 *
	 * @return array
	 */
	public static function vendors() {
		$vendors = array();
		foreach ( self::signatures() as $sig ) {
			$vendors[ $sig['vendor'] ] = true;
		}
		return array_keys( $vendors );
	}

	/**
	 * All purposes with human labels.
	 *
	 * @return array
	 */
	public static function purposes() {
		return array(
			'search_engine' => __( 'Search engines', 'ai-traffic-guardian' ),
			'ai_search'     => __( 'AI search / citation', 'ai-traffic-guardian' ),
			'ai_training'   => __( 'AI training crawlers', 'ai-traffic-guardian' ),
			'agent_proxy'   => __( 'Human-intent proxies (agentic AI)', 'ai-traffic-guardian' ),
			'seo_tool'      => __( 'SEO tools', 'ai-traffic-guardian' ),
			'social'        => __( 'Social / link previews', 'ai-traffic-guardian' ),
			'feed'          => __( 'Feed readers', 'ai-traffic-guardian' ),
			'monitor'       => __( 'Uptime monitors', 'ai-traffic-guardian' ),
			'scraper'       => __( 'Junk / hostile bots', 'ai-traffic-guardian' ),
		);
	}
}

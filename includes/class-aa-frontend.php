<?php
/**
 * Front end: applies rules to the main post content at render time.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AA_Frontend {

	private static $running = false;

	public static function init() {
		// Very late, so shortcodes, blocks, wpautop and page-builder output are already in place.
		add_filter( 'the_content', array( __CLASS__, 'filter' ), 99999 );
	}

	/** Options handed to the engine (also used by the Test tool). */
	public static function engine_opts() {
		$s = AA_Settings::get_all();
		return array(
			'max_links_page'  => (int) $s['max_links_page'],
			'max_per_keyword' => (int) $s['max_per_keyword'],
			'max_per_url'     => (int) $s['max_per_url'],
			'scope_mode'      => $s['scope_mode'],
			'skip_accordions' => (bool) $s['skip_accordions'],
			'new_tab'         => (bool) $s['new_tab'],
			'nofollow'        => (bool) $s['nofollow'],
			'link_class'      => $s['link_class'],
			'extra_block'     => AA_Settings::lines( 'extra_block' ),
			'skip_tags'       => apply_filters( 'aa_skip_tags', AA_Engine::default_skip_tags() ),
			'allow_tags'      => apply_filters( 'aa_allow_tags', AA_Engine::default_allow_tags() ),
			'block_classes'   => apply_filters( 'aa_block_classes', AA_Engine::default_block_classes() ),
			'block_prefixes'  => apply_filters( 'aa_block_class_prefixes', AA_Engine::default_block_prefixes() ),
			'block_ids'       => apply_filters( 'aa_block_ids', AA_Engine::default_block_ids() ),
		);
	}

	/** True when the current request is a normal front-end view of a singular page. */
	public static function should_run() {
		if ( ! AA_Settings::get( 'enabled' ) ) {
			return false;
		}
		if ( is_admin() || is_feed() || is_embed() || is_customize_preview() || wp_doing_ajax() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return false;
		}
		if ( doing_filter( 'get_the_excerpt' ) || doing_filter( 'wp_trim_excerpt' ) ) {
			return false;
		}
		// Page-builder editors and previews.
		foreach ( array( 'elementor-preview', 'et_fb', 'fl_builder', 'brizy-edit', 'ct_builder', 'bricks', 'vc_editable', 'is-editor-iframe' ) as $q ) {
			if ( isset( $_GET[ $q ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return false;
			}
		}
		return true;
	}

	public static function filter( $content ) {
		if ( self::$running || ! is_string( $content ) || '' === $content ) {
			return $content;
		}
		if ( ! self::should_run() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || (int) $post->ID !== (int) get_queried_object_id() ) {
			return $content;
		}
		self::$running = true;
		try {
			$out = self::process_post( $content, $post );
		} catch ( \Throwable $e ) {
			$out = $content; // Never break a page because of a link.
		}
		self::$running = false;
		return is_string( $out ) ? $out : $content;
	}

	/** Why a post is skipped, or '' when it can be processed. */
	public static function skip_reason( $post ) {
		$s = AA_Settings::get_all();
		if ( ! in_array( $post->post_type, (array) $s['post_types'], true ) ) {
			return __( 'This post type is not selected in Settings.', 'anchor-autopilot' );
		}
		if ( get_post_meta( $post->ID, '_aa_disable', true ) ) {
			return __( 'Auto links are switched off for this page (page setting).', 'anchor-autopilot' );
		}
		if ( post_password_required( $post ) ) {
			return __( 'Password-protected page.', 'anchor-autopilot' );
		}
		$self_key = AA_Url::key( get_permalink( $post ) );
		foreach ( AA_Settings::lines( 'exclude_urls' ) as $line ) {
			$k = AA_Url::key( $line );
			if ( '' !== $k && $k === $self_key ) {
				return __( 'This URL is on the exclusion list in Settings.', 'anchor-autopilot' );
			}
		}
		if ( ! apply_filters( 'aa_should_process', true, $post ) ) {
			return __( 'Switched off by a filter.', 'anchor-autopilot' );
		}
		return '';
	}

	public static function process_post( $content, $post ) {
		if ( '' !== self::skip_reason( $post ) ) {
			return $content;
		}
		if ( strlen( $content ) > (int) apply_filters( 'aa_max_content_bytes', 600000 ) ) {
			return $content;
		}
		$self_key = AA_Url::key( get_permalink( $post ) );
		$rules    = AA_DB::runtime_rules( $post->ID, $self_key );
		if ( ! $rules ) {
			return $content;
		}

		$use_cache = wp_using_ext_object_cache();
		if ( $use_cache ) {
			$ck  = 'p' . $post->ID . '_' . md5( $content ) . '_' . get_option( 'aa_rules_version', '0' );
			$hit = wp_cache_get( $ck, 'anchor_autopilot' );
			if ( is_string( $hit ) ) {
				return $hit;
			}
		}

		$engine = new AA_Engine( self::engine_opts() );
		$out    = $engine->process( $content, $rules, $self_key );

		if ( $use_cache ) {
			wp_cache_set( $ck, $out, 'anchor_autopilot', HOUR_IN_SECONDS );
		}
		return $out;
	}

	/** Used by the Test tool: run the engine and return the report as well. */
	public static function process_with_report( $html, $post_id, $self_url, $opts_override = array() ) {
		$self_key = AA_Url::key( $self_url );
		$rules    = AA_DB::runtime_rules( (int) $post_id, $self_key );
		$engine   = new AA_Engine( array_merge( self::engine_opts(), $opts_override ) );
		$report   = null;
		$out      = $engine->process( $html, $rules, $self_key, $report );
		return array( $out, $report, count( $rules ) );
	}
}

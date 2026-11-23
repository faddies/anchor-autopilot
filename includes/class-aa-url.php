<?php
/**
 * URL helpers. key() is pure PHP so the engine can be tested without WordPress.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'AA_TESTING' ) ) {
	exit;
}

class AA_Url {

	/** Host of this site (lowercase, no www.). Set by the plugin bootstrap. */
	public static $home_host = '';

	/**
	 * Normalised comparison key for a URL.
	 * Same-site URLs become a path ("/services/seo"); other hosts keep "//host/path".
	 * Returns '' for things that are not page links (mailto:, tel:, #fragment only, javascript:).
	 */
	public static function key( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'http:' . $url;
		}
		if ( preg_match( '~^([a-z][a-z0-9+.-]*):~i', $url, $m ) && ! in_array( strtolower( $m[1] ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		$p = @parse_url( $url );
		if ( false === $p || ! is_array( $p ) ) {
			return '';
		}
		$host = isset( $p['host'] ) ? strtolower( $p['host'] ) : '';
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}
		$has_path = isset( $p['path'] ) && '' !== $p['path'];
		$has_query = isset( $p['query'] ) && '' !== $p['query'];
		if ( '' === $host && ! $has_path && ! $has_query ) {
			return ''; // "#section" or empty.
		}
		$path = $has_path ? $p['path'] : '/';
		$path = rawurldecode( $path );
		$path = self::lower( $path );
		$path = preg_replace( '~/{2,}~', '/', $path );
		if ( '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}
		$path = preg_replace( '~/index\.(php|html?)$~', '/', $path );
		$path = rtrim( $path, '/' );
		if ( '' === $path ) {
			$path = '/';
		}
		$q = '';
		if ( $has_query ) {
			parse_str( $p['query'], $qs );
			foreach ( array( 'p', 'page_id' ) as $k ) {
				if ( isset( $qs[ $k ] ) && is_scalar( $qs[ $k ] ) ) {
					$q = '?' . $k . '=' . $qs[ $k ];
					break;
				}
			}
		}
		$prefix = ( '' !== $host && $host !== self::$home_host ) ? '//' . $host : '';
		return $prefix . $path . $q;
	}

	public static function lower( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	/** True when a key points to another site. */
	public static function is_external_key( $key ) {
		return 0 === strpos( $key, '//' );
	}

	/**
	 * Turn user input into an absolute URL on this site (or external when allowed).
	 * Returns array( url, error ). Needs WordPress.
	 */
	public static function clean_input( $raw, $allow_external = false ) {
		$raw = trim( wp_strip_all_tags( (string) $raw ) );
		$raw = preg_replace( '/[\x00-\x1F\x7F]/', '', $raw );
		if ( '' === $raw ) {
			return array( '', __( 'URL is empty.', 'anchor-autopilot' ) );
		}
		if ( preg_match( '~^(javascript|data|vbscript|file):~i', $raw ) ) {
			return array( '', __( 'That URL type is not allowed.', 'anchor-autopilot' ) );
		}
		if ( '/' === substr( $raw, 0, 1 ) && 0 !== strpos( $raw, '//' ) ) {
			$raw = home_url( $raw );
		} elseif ( ! preg_match( '~^https?://~i', $raw ) ) {
			if ( preg_match( '~^[a-z0-9.-]+\.[a-z]{2,}(/|$)~i', $raw ) ) {
				$raw = 'https://' . $raw; // "example.com/page" typed without a scheme.
			} else {
				return array( '', __( 'Use a full URL (https://…) or a path starting with /.', 'anchor-autopilot' ) );
			}
		}
		$url = esc_url_raw( $raw );
		if ( '' === $url ) {
			return array( '', __( 'URL is not valid.', 'anchor-autopilot' ) );
		}
		$key = self::key( $url );
		if ( '' === $key ) {
			return array( '', __( 'URL is not valid.', 'anchor-autopilot' ) );
		}
		if ( self::is_external_key( $key ) && ! $allow_external ) {
			return array( '', __( 'Only URLs on this site are allowed (see Settings to allow external URLs).', 'anchor-autopilot' ) );
		}
		return array( $url, '' );
	}

	/** Best-effort lookup of the post ID behind a URL. 0 when unknown. */
	public static function resolve_post_id( $url ) {
		$key = self::key( $url );
		if ( '' === $key || self::is_external_key( $key ) ) {
			return 0;
		}
		if ( '/' === $key ) {
			$front = (int) get_option( 'page_on_front' );
			return $front > 0 ? $front : 0;
		}
		$id = (int) url_to_postid( $url );
		return $id > 0 ? $id : 0;
	}
}

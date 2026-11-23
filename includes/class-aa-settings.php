<?php
/**
 * Settings storage and sanitising.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AA_Settings {

	const OPTION = 'aa_settings';

	public static function defaults() {
		return array(
			'enabled'            => 1,
			'post_types'         => array( 'post', 'page' ),
			'max_links_page'     => 5,
			'max_per_keyword'    => 1,
			'max_per_url'        => 1,
			'new_tab'            => 0,
			'nofollow'           => 0,
			'link_class'         => '',
			'scope_mode'         => 'strict',
			'skip_accordions'    => 1,
			'allow_external'     => 0,
			'exclude_urls'       => '',
			'extra_block'        => '',
			'delete_on_uninstall' => 0,
		);
	}

	public static function get_all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function get( $key ) {
		$all = self::get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function sanitize( $in ) {
		$d   = self::defaults();
		$out = array();

		$out['enabled']             = empty( $in['enabled'] ) ? 0 : 1;
		$out['new_tab']             = empty( $in['new_tab'] ) ? 0 : 1;
		$out['nofollow']            = empty( $in['nofollow'] ) ? 0 : 1;
		$out['skip_accordions']     = empty( $in['skip_accordions'] ) ? 0 : 1;
		$out['allow_external']      = empty( $in['allow_external'] ) ? 0 : 1;
		$out['delete_on_uninstall'] = empty( $in['delete_on_uninstall'] ) ? 0 : 1;

		$out['max_links_page']  = isset( $in['max_links_page'] ) ? max( 0, min( 200, (int) $in['max_links_page'] ) ) : $d['max_links_page'];
		$out['max_per_keyword'] = isset( $in['max_per_keyword'] ) ? max( 1, min( 50, (int) $in['max_per_keyword'] ) ) : $d['max_per_keyword'];
		$out['max_per_url']     = isset( $in['max_per_url'] ) ? max( 0, min( 50, (int) $in['max_per_url'] ) ) : $d['max_per_url'];

		$cls = isset( $in['link_class'] ) ? preg_replace( '/[^A-Za-z0-9_\- ]/', '', (string) $in['link_class'] ) : '';
		$out['link_class'] = trim( preg_replace( '/\s+/', ' ', $cls ) );

		$out['scope_mode'] = ( isset( $in['scope_mode'] ) && 'relaxed' === $in['scope_mode'] ) ? 'relaxed' : 'strict';

		$types = array();
		if ( isset( $in['post_types'] ) && is_array( $in['post_types'] ) ) {
			$public = get_post_types( array( 'public' => true ) );
			foreach ( $in['post_types'] as $t ) {
				$t = sanitize_key( $t );
				if ( isset( $public[ $t ] ) && 'attachment' !== $t ) {
					$types[] = $t;
				}
			}
		}
		$out['post_types'] = $types;

		$out['exclude_urls'] = self::clean_lines( isset( $in['exclude_urls'] ) ? $in['exclude_urls'] : '', false );
		$out['extra_block']  = self::clean_lines( isset( $in['extra_block'] ) ? $in['extra_block'] : '', true );

		return $out;
	}

	private static function clean_lines( $text, $selectors ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) wp_unslash( $text ) );
		$out   = array();
		foreach ( $lines as $line ) {
			$line = trim( wp_strip_all_tags( $line ) );
			if ( '' === $line ) {
				continue;
			}
			if ( $selectors ) {
				if ( ! preg_match( '/^[.#][A-Za-z0-9_\-]+$/', $line ) ) {
					continue;
				}
			}
			$out[] = $line;
		}
		return implode( "\n", array_slice( $out, 0, 500 ) );
	}

	public static function lines( $key ) {
		$v = (string) self::get( $key );
		return '' === $v ? array() : preg_split( '/\r\n|\r|\n/', $v );
	}
}

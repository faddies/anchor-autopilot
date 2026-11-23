<?php
/**
 * Conflict analysis across all rules.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AA_Conflicts {

	private static function tokens( $akey ) {
		return preg_match_all( '/[\p{L}\p{N}]+/u', $akey, $m ) ? $m[0] : array();
	}

	/**
	 * @return array {
	 *   duplicates: rows sharing an anchor but pointing to different URLs
	 *   overlaps:   [ long rule, short rule ] pairs where the short anchor sits inside the long one
	 *   multi:      target key => rules (several anchors to the same URL)
	 *   single:     rules with a one-word anchor
	 *   total:      active rules analysed
	 * }
	 */
	public static function analyze() {
		$rows = array();
		foreach ( AA_DB::all_rows() as $r ) {
			if ( 'active' === $r->status ) {
				$rows[] = $r;
			}
		}

		$info = array();
		$word_index = array(); // first token => rule ids (short rules grouped by their first token).
		foreach ( $rows as $r ) {
			$t = self::tokens( $r->anchor_key );
			$info[ $r->id ] = array( 'row' => $r, 't' => $t, 'j' => ' ' . implode( ' ', $t ) . ' ' );
		}

		// Duplicates: same anchor (and same From page for page rules), different destination.
		$dups  = array();
		$group = array();
		foreach ( $rows as $r ) {
			$k = $r->scope . '|' . ( 'page' === $r->scope ? $r->from_key . '|' : '' ) . $r->anchor_key;
			$group[ $k ][] = $r;
		}
		foreach ( $group as $list ) {
			if ( count( $list ) > 1 ) {
				$targets = array();
				foreach ( $list as $r ) {
					$targets[ $r->target_key ] = true;
				}
				if ( count( $targets ) > 1 ) {
					$dups[] = $list;
				}
			}
		}

		// Same anchor as a keyword rule AND a page rule: page rule wins on that page (informational).
		$overrides = array();
		$kw = array();
		foreach ( $rows as $r ) {
			if ( 'keyword' === $r->scope ) {
				$kw[ $r->anchor_key ] = $r;
			}
		}
		foreach ( $rows as $r ) {
			if ( 'page' === $r->scope && isset( $kw[ $r->anchor_key ] ) && $kw[ $r->anchor_key ]->target_key !== $r->target_key ) {
				$overrides[] = array( $r, $kw[ $r->anchor_key ] );
			}
		}

		// Overlaps: short anchor contained, as whole words, in a longer anchor.
		$by_first = array();
		foreach ( $info as $id => $i ) {
			foreach ( array_unique( $i['t'] ) as $tok ) {
				$by_first[ $tok ][] = $id;
			}
		}
		$overlaps = array();
		$cap      = 500;
		foreach ( $info as $sid => $s ) {
			if ( ! $s['t'] || count( $overlaps ) >= $cap ) {
				continue;
			}
			$rare = $s['t'][0];
			if ( empty( $by_first[ $rare ] ) ) {
				continue;
			}
			foreach ( $by_first[ $rare ] as $lid ) {
				if ( $lid === $sid ) {
					continue;
				}
				$l = $info[ $lid ];
				if ( count( $l['t'] ) <= count( $s['t'] ) ) {
					continue;
				}
				if ( false === strpos( $l['j'], $s['j'] ) ) {
					continue;
				}
				$a = $l['row'];
				$b = $s['row'];
				if ( $a->target_key === $b->target_key ) {
					continue; // Same destination: nothing to confuse.
				}
				if ( 'page' === $a->scope && 'page' === $b->scope && $a->from_key !== $b->from_key ) {
					continue;
				}
				if ( 'page' === $a->scope && 'keyword' === $b->scope ) {
					// Fine, still reported: the page rule's longer phrase wins on its From page only.
				}
				$overlaps[] = array( $a, $b );
				if ( count( $overlaps ) >= $cap ) {
					break;
				}
			}
		}

		// Several anchors to one URL.
		$multi = array();
		$by_t  = array();
		foreach ( $rows as $r ) {
			if ( 'keyword' === $r->scope ) {
				$by_t[ $r->target_key ][] = $r;
			}
		}
		foreach ( $by_t as $k => $list ) {
			if ( count( $list ) >= 2 ) {
				$multi[ $k ] = $list;
			}
		}
		uasort(
			$multi,
			function ( $a, $b ) {
				return count( $b ) - count( $a );
			}
		);

		$single = array();
		foreach ( $info as $i ) {
			if ( 1 === count( $i['t'] ) ) {
				$single[] = $i['row'];
			}
		}

		return array(
			'duplicates' => $dups,
			'overrides'  => $overrides,
			'overlaps'   => $overlaps,
			'multi'      => $multi,
			'single'     => $single,
			'total'      => count( $rows ),
		);
	}
}

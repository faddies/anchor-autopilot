<?php
/**
 * CSV import and export.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AA_CSV {

	const MAX_ROWS = 20000;
	const MAX_BYTES = 5242880; // 5 MB.

	private static function aliases() {
		return array(
			'anchor'         => array( 'anchor', 'anchor text', 'anchor_text', 'keyword', 'keywords', 'phrase', 'text', 'target keyword' ),
			'url'            => array( 'url', 'to', 'to url', 'to_url', 'target', 'target url', 'target_url', 'link', 'link url', 'link_url', 'destination', 'destination url', 'link to' ),
			'from'           => array( 'from', 'from url', 'from_url', 'source', 'source url', 'source_url', 'page', 'page url', 'on page' ),
			'case_sensitive' => array( 'case_sensitive', 'case sensitive', 'case' ),
			'max'            => array( 'max', 'max per page', 'max_per_page', 'limit' ),
			'nofollow'       => array( 'nofollow', 'no follow', 'rel nofollow' ),
			'new_tab'        => array( 'new_tab', 'new tab', 'open in new tab', 'target blank', 'blank' ),
			'status'         => array( 'status', 'state' ),
		);
	}

	/** Parse CSV text. Returns array( rows[], error ). */
	public static function parse_string( $text ) {
		$text = (string) $text;
		if ( strlen( $text ) > self::MAX_BYTES ) {
			return array( array(), __( 'CSV is larger than 5 MB.', 'anchor-autopilot' ) );
		}
		if ( 0 === strpos( $text, "\xEF\xBB\xBF" ) ) {
			$text = substr( $text, 3 );
		}
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' ) : utf8_encode( $text );
		}
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$first = strtok( $text, "\n" );
		$delim = ',';
		$best  = substr_count( (string) $first, ',' );
		foreach ( array( ';', "\t" ) as $d ) {
			$c = substr_count( (string) $first, $d );
			if ( $c > $best ) {
				$best  = $c;
				$delim = $d;
			}
		}
		$h = fopen( 'php://temp', 'r+' );
		fwrite( $h, $text );
		rewind( $h );
		$rows = array();
		while ( false !== ( $r = fgetcsv( $h, 0, $delim, '"', '\\' ) ) ) {
			if ( null === $r || ( 1 === count( $r ) && ( null === $r[0] || '' === trim( $r[0] ) ) ) ) {
				continue; // Blank line.
			}
			$rows[] = $r;
			if ( count( $rows ) > self::MAX_ROWS + 1 ) {
				fclose( $h );
				return array( array(), sprintf( /* translators: %d: row limit */ __( 'Too many rows (limit %d). Split the file.', 'anchor-autopilot' ), self::MAX_ROWS ) );
			}
		}
		fclose( $h );
		if ( ! $rows ) {
			return array( array(), __( 'The CSV has no rows.', 'anchor-autopilot' ) );
		}
		return array( $rows, '' );
	}

	public static function parse_upload( $file ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return array( array(), __( 'No file received.', 'anchor-autopilot' ) );
		}
		if ( ! empty( $file['error'] ) ) {
			return array( array(), __( 'Upload failed.', 'anchor-autopilot' ) );
		}
		$name = isset( $file['name'] ) ? strtolower( (string) $file['name'] ) : '';
		if ( ! preg_match( '/\.(csv|txt|tsv)$/', $name ) ) {
			return array( array(), __( 'Please upload a .csv file.', 'anchor-autopilot' ) );
		}
		if ( filesize( $file['tmp_name'] ) > self::MAX_BYTES ) {
			return array( array(), __( 'CSV is larger than 5 MB.', 'anchor-autopilot' ) );
		}
		return self::parse_string( (string) file_get_contents( $file['tmp_name'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/** Map header cells to field names; returns array( map, has_header ). */
	private static function map_header( array $cells, $type ) {
		$al  = self::aliases();
		$map = array();
		foreach ( $cells as $i => $c ) {
			$c = strtolower( trim( (string) $c ) );
			foreach ( $al as $field => $names ) {
				if ( in_array( $c, $names, true ) && ! in_array( $field, $map, true ) ) {
					// In a keyword file "url" means target; in a page file "to"/"url" also mean target.
					$map[ $i ] = $field;
					break;
				}
			}
		}
		$has = in_array( 'anchor', $map, true ) || in_array( 'url', $map, true ) || in_array( 'from', $map, true );
		if ( $has ) {
			return array( $map, true );
		}
		// No header: positional.
		$map = ( 'page' === $type ) ? array( 0 => 'from', 1 => 'url', 2 => 'anchor' ) : array( 0 => 'anchor', 1 => 'url' );
		$extra = array( 'case_sensitive', 'max', 'nofollow', 'new_tab', 'status' );
		$n = count( $map );
		foreach ( $extra as $k => $f ) {
			$map[ $n + $k ] = $f;
		}
		return array( $map, false );
	}

	/**
	 * Import parsed rows.
	 *
	 * @param array  $rows Parsed rows.
	 * @param string $type 'keyword' or 'page'.
	 * @param string $dup  'update' or 'skip'.
	 * @return array Result summary.
	 */
	public static function import( array $rows, $type, $dup ) {
		$type = ( 'page' === $type ) ? 'page' : 'keyword';
		$dup  = ( 'skip' === $dup ) ? 'skip' : 'update';
		$res  = array( 'type' => $type, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'warnings' => 0, 'problems' => array(), 'rows' => 0 );

		list( $map, $has_header ) = self::map_header( $rows[0], $type );
		if ( $has_header ) {
			array_shift( $rows );
			if ( 'keyword' === $type && in_array( 'from', $map, true ) ) {
				$res['problems'][] = array( 0, '', __( 'This file has a "from" column. Use the From / To / Anchor importer for page-specific rules.', 'anchor-autopilot' ), 'error' );
				$res['errors']++;
				return $res;
			}
			if ( ! in_array( 'anchor', $map, true ) || ! in_array( 'url', $map, true ) || ( 'page' === $type && ! in_array( 'from', $map, true ) ) ) {
				$need = ( 'page' === $type ) ? 'from, to, anchor' : 'anchor, url';
				$res['problems'][] = array( 0, '', sprintf( /* translators: %s: column names */ __( 'Missing required columns. Needed: %s.', 'anchor-autopilot' ), $need ), 'error' );
				$res['errors']++;
				return $res;
			}
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$t0       = microtime( true );
		$line     = $has_header ? 1 : 0;
		$resolve  = true;
		foreach ( $rows as $r ) {
			$line++;
			$res['rows']++;
			$in = array( 'scope' => $type );
			foreach ( $map as $i => $field ) {
				$v = isset( $r[ $i ] ) ? trim( (string) $r[ $i ] ) : '';
				$in[ $field ] = $v;
			}
			if ( 'page' === $type && isset( $in['from'] ) ) {
				$in['from'] = $in['from'];
			}
			if ( $resolve && ( microtime( true ) - $t0 ) > 20 ) {
				$resolve = false; // Stop slow page look-ups on huge files; rules still match by URL.
			}
			$out = AA_DB::save( $in, $dup, 0, $resolve && 'page' === $type );
			if ( 'inserted' === $out['status'] ) {
				$res['inserted']++;
			} elseif ( 'updated' === $out['status'] ) {
				$res['updated']++;
			} elseif ( 'skipped' === $out['status'] ) {
				$res['skipped']++;
			} else {
				$res['errors']++;
			}
			$anchor = isset( $in['anchor'] ) ? $in['anchor'] : '';
			if ( 'error' === $out['status'] || 'skipped' === $out['status'] ) {
				if ( count( $res['problems'] ) < 300 ) {
					$res['problems'][] = array( $line, $anchor, $out['message'], $out['status'] );
				}
			}
			foreach ( $out['warnings'] as $w ) {
				$res['warnings']++;
				if ( count( $res['problems'] ) < 300 ) {
					$res['problems'][] = array( $line, $anchor, $w, 'warning' );
				}
			}
		}
		AA_DB::bump();
		return $res;
	}

	/* ------------------------------------------------------------------ */
	/* Export and samples                                                  */
	/* ------------------------------------------------------------------ */

	private static function safe( $v ) {
		$v = (string) $v;
		if ( '' !== $v && in_array( $v[0], array( '=', '+', '@', "\t", "\r" ), true ) ) {
			return "'" . $v; // Stops spreadsheet formula injection.
		}
		return $v;
	}

	private static function tri( $v ) {
		$v = (int) $v;
		return -1 === $v ? '' : ( $v ? 'yes' : 'no' );
	}

	public static function export( $type ) {
		$type = ( 'page' === $type ) ? 'page' : 'keyword';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="anchor-autopilot-' . ( 'page' === $type ? 'page-rules' : 'keyword-rules' ) . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		if ( 'page' === $type ) {
			fputcsv( $out, array( 'from', 'to', 'anchor', 'case_sensitive', 'max', 'nofollow', 'new_tab', 'status' ), ',', '"', '\\' );
		} else {
			fputcsv( $out, array( 'anchor', 'url', 'case_sensitive', 'max', 'nofollow', 'new_tab', 'status' ), ',', '"', '\\' );
		}
		foreach ( AA_DB::all_rows( $type ) as $r ) {
			$common = array( $r->case_sensitive ? 'yes' : '', $r->max_per_page ? $r->max_per_page : '', self::tri( $r->nofollow ), self::tri( $r->new_tab ), $r->status );
			if ( 'page' === $type ) {
				fputcsv( $out, array_merge( array( $r->from_url, $r->target_url, self::safe( $r->anchor ) ), $common ), ',', '"', '\\' );
			} else {
				fputcsv( $out, array_merge( array( self::safe( $r->anchor ), $r->target_url ), $common ), ',', '"', '\\' );
			}
		}
		fclose( $out );
		exit;
	}

	public static function sample( $type ) {
		$home = untrailingslashit( home_url() );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		if ( 'page' === $type ) {
			header( 'Content-Disposition: attachment; filename="sample-page-rules.csv"' );
			echo "from,to,anchor\n";
			echo $home . '/blog/seo-checklist/,' . $home . '/services/seo/,"SEO services"' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $home . '/about/,' . $home . '/contact/,"get in touch"' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			header( 'Content-Disposition: attachment; filename="sample-keyword-rules.csv"' );
			echo "anchor,url\n";
			echo '"SEO services",' . $home . "/services/seo/\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '"web design Dubai",' . $home . "/services/web-design/\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		exit;
	}
}

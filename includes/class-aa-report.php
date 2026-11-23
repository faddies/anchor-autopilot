<?php
/**
 * Link report: scans published content with the same engine the front end uses and records,
 * for every rule, where it linked, where it was skipped (and why), and where the phrase
 * appears only in a protected area.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AA_Report {

	const CHUNK = 5;

	/* ------------------------------------------------------------------ */
	/* Scanning                                                            */
	/* ------------------------------------------------------------------ */

	private static function post_types() {
		$types = (array) AA_Settings::get( 'post_types' );
		return $types ? array_values( array_filter( array_map( 'sanitize_key', $types ) ) ) : array( 'post', 'page' );
	}

	private static function state_hash() {
		return md5( (string) get_option( 'aa_rules_version', '0' ) . '|' . wp_json_encode( AA_Settings::get_all() ) );
	}

	private static function normalise( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[\s\x{00A0}\x{200B}]+/u', ' ', $text );
		$text = is_string( $text ) ? $text : '';
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $text ), 'UTF-8' ) : strtolower( trim( $text ) );
	}

	/** Fetch the live page through a loopback request. Returns array( http_code, body, error ). */
	private static function loopback( $p, $mode ) {
		$url = add_query_arg(
			array(
				'aa_probe' => self::probe_token_for( $p->ID ),
				'aa_pid'   => (int) $p->ID,
				'aa_mode'  => $mode,
				'aa_nc'    => time(),
			),
			get_permalink( $p )
		);
		$res = wp_remote_get( $url, array( 'timeout' => 25, 'redirection' => 3, 'sslverify' => apply_filters( 'https_local_ssl_verify', false ), 'user-agent' => 'AnchorAutopilot/' . AA_VERSION . '; ' . home_url( '/' ), 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
		if ( is_wp_error( $res ) ) {
			return array( 0, '', $res->get_error_message() );
		}
		return array( (int) wp_remote_retrieve_response_code( $res ), (string) wp_remote_retrieve_body( $res ), '' );
	}

	private static function probe_token_for( $post_id ) {
		return AA_Frontend::probe_token( $post_id );
	}

	/** Reduce a full theme page to the part inside <body> without scripts, so the engine can judge it. */
	private static function body_only( $html ) {
		if ( preg_match( '~<body\b[^>]*>(.*)</body>~is', $html, $m ) ) {
			$html = $m[1];
		}
		return preg_replace( '~<(script|style|noscript|template|svg)\b.*?</\1>~is', ' ', $html );
	}

	/**
	 * "Check this page": scan it AND load the live page with a one-time token to see whether the
	 * plugin's filter really runs there. Returns array( state, message, links[] ).
	 */
	public static function probe( $p ) {
		delete_transient( 'aa_probe_' . (int) $p->ID );
		list( $code, , $err ) = self::loopback( $p, 'run' );
		if ( $err || $code < 200 || $code >= 300 ) {
			return array( 'state' => 'fetch_failed', 'message' => $err ? $err : sprintf( /* translators: %d: HTTP status */ __( 'The live page answered with HTTP %d (a firewall, login wall or maintenance mode can cause this).', 'anchor-autopilot' ), $code ), 'links' => array() );
		}
		$got = get_transient( 'aa_probe_' . (int) $p->ID );
		delete_transient( 'aa_probe_' . (int) $p->ID );
		if ( ! is_array( $got ) || ! $got ) {
			return array( 'state' => 'not_run', 'message' => __( 'The plugin did not run when this page loaded. The page probably prints its text through a layout or widget that bypasses WordPress\'s standard content filter, or a full-page cache served an old copy. Clear the cache and try again; if it persists, tell the developer which theme or page builder this page uses.', 'anchor-autopilot' ), 'links' => array() );
		}
		foreach ( $got as $g ) {
			if ( 'ran' === $g['state'] ) {
				return array( 'state' => 'ran', 'message' => '', 'links' => $g['links'] );
			}
		}
		foreach ( $got as $g ) {
			if ( 'skipped' === $g['state'] ) {
				return array( 'state' => 'skipped', 'message' => $g['why'], 'links' => array() );
			}
		}
		return array( 'state' => 'blocked', 'message' => isset( $got[0]['why'] ) ? $got[0]['why'] : '', 'links' => array() );
	}

	/** Scan one post and replace its rows in the log. Returns array( linked, skipped, protected ). */
	public static function scan_post( $p ) {
		global $wpdb;
		$log = AA_DB::log_table();
		$out = array( 'linked' => 0, 'skipped' => 0, 'protected' => 0 );
		$now = current_time( 'mysql', true );

		$wpdb->delete( $log, array( 'post_id' => (int) $p->ID ), array( '%d' ) );

		$reason = AA_Frontend::skip_reason( $p );
		if ( '' !== $reason ) {
			$wpdb->insert( $log, array( 'post_id' => (int) $p->ID, 'rule_id' => 0, 'status' => 'page_off', 'cnt' => 0, 'anchor_shown' => '', 'reason' => 'page_off', 'detail' => mb_substr( $reason, 0, 190 ), 'scanned_at' => $now ) );
			return $out;
		}

		$html = AA_Admin::render_post_html( $p );
		if ( get_post_meta( $p->ID, '_elementor_data', true ) || '' === trim( $html ) ) {
			// Page builders: read the page as visitors get it, with automatic linking held back, so the text is the real text.
			list( $code, $body, $err ) = self::loopback( $p, 'raw' );
			if ( ! $err && $code >= 200 && $code < 300 && '' !== trim( $body ) ) {
				$html = self::body_only( $body );
			}
		}
		if ( '' === trim( $html ) ) {
			$wpdb->insert( $log, array( 'post_id' => (int) $p->ID, 'rule_id' => 0, 'status' => 'page_off', 'cnt' => 0, 'anchor_shown' => '', 'reason' => 'unreadable', 'detail' => __( 'The text of this page could not be read by the scan. Use "Check this page" for it.', 'anchor-autopilot' ), 'scanned_at' => $now ) );
			return $out;
		}
		$url      = get_permalink( $p );
		$self_key = AA_Url::key( $url );
		list( , $report ) = AA_Frontend::process_with_report( $html, (int) $p->ID, $url );
		if ( ! is_array( $report ) ) {
			return $out;
		}

		$seen   = array();
		$linked = array();
		foreach ( $report['links'] as $l ) {
			$id = (int) $l['id'];
			if ( ! isset( $linked[ $id ] ) ) {
				$linked[ $id ] = array( 'n' => 0, 'anchor' => $l['anchor'] );
			}
			$linked[ $id ]['n']++;
		}
		foreach ( $linked as $id => $v ) {
			$wpdb->insert( $log, array( 'post_id' => (int) $p->ID, 'rule_id' => $id, 'status' => 'linked', 'cnt' => $v['n'], 'anchor_shown' => mb_substr( $v['anchor'], 0, 190 ), 'reason' => '', 'detail' => '', 'scanned_at' => $now ) );
			$seen[ $id ] = true;
			$out['linked']++;
		}
		foreach ( $report['skipped'] as $s ) {
			$id = (int) $s['id'];
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$wpdb->insert( $log, array( 'post_id' => (int) $p->ID, 'rule_id' => $id, 'status' => 'skipped', 'cnt' => 0, 'anchor_shown' => mb_substr( $s['anchor'], 0, 190 ), 'reason' => substr( (string) $s['reason'], 0, 20 ), 'detail' => mb_substr( (string) $s['detail'], 0, 190 ), 'scanned_at' => $now ) );
			$seen[ $id ] = true;
			$out['skipped']++;
		}

		// Phrase present in the page text but neither linked nor skipped: it sits in a protected spot.
		// Texts of links that are already on the page (by hand, theme or builder), with their destinations.
		$existing = array();
		if ( preg_match_all( '~<a\b[^>]*?\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\')[^>]*>(.*?)</a>~is', $html, $am, PREG_SET_ORDER ) ) {
			foreach ( $am as $m ) {
				$href       = html_entity_decode( '' !== $m[1] ? $m[1] : $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$existing[] = array( self::normalise( wp_strip_all_tags( $m[3] ) ), $href );
			}
		}
		$plain = self::normalise( wp_strip_all_tags( preg_replace( '~<(script|style|noscript|template)\b.*?</\1>~is', ' ', $html ) ) );
		if ( '' !== $plain ) {
			foreach ( AA_DB::runtime_rules( (int) $p->ID, $self_key ) as $r ) {
				$id = (int) $r['id'];
				if ( isset( $seen[ $id ] ) || empty( $r['anchor'] ) ) {
					continue;
				}
				$tkey = isset( $r['key'] ) ? $r['key'] : '';
				if ( '' !== $self_key && $tkey === $self_key ) {
					continue;
				}
				$needle = self::normalise( $r['anchor'] );
				if ( '' === $needle || false === strpos( $plain, $needle ) ) {
					continue;
				}
				if ( ! preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $needle, '/' ) . '(?![\p{L}\p{N}])/u', $plain ) ) {
					continue;
				}
				foreach ( $existing as $ex ) {
					if ( false !== strpos( $ex[0], $needle ) ) {
						$same   = ( '' !== $tkey && AA_Url::key( $ex[1] ) === $tkey );
						$wpdb->insert( $log, array( 'post_id' => (int) $p->ID, 'rule_id' => $id, 'status' => 'skipped', 'cnt' => 0, 'anchor_shown' => mb_substr( $r['anchor'], 0, 190 ), 'reason' => $same ? 'in_link_same' : 'in_link', 'detail' => mb_substr( $ex[1], 0, 190 ), 'scanned_at' => $now ) );
						$out['skipped']++;
						continue 2;
					}
				}
				$wpdb->insert( $log, array( 'post_id' => (int) $p->ID, 'rule_id' => $id, 'status' => 'protected', 'cnt' => 0, 'anchor_shown' => mb_substr( $r['anchor'], 0, 190 ), 'reason' => 'protected', 'detail' => '', 'scanned_at' => $now ) );
				$out['protected']++;
			}
		}
		return $out;
	}

	public static function ajax_scan() {
		global $wpdb;
		if ( ! current_user_can( AA_Admin::cap() ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'aa_scan', 'nonce' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$step  = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : 'chunk';
		$types = self::post_types();
		$in    = "'" . implode( "','", array_map( 'esc_sql', $types ) ) . "'";

		if ( 'start' === $step ) {
			$wpdb->query( 'TRUNCATE TABLE ' . AA_DB::log_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			update_option( 'aa_scan', array( 'state' => 'running', 'total' => $total, 'done' => 0, 'last_id' => 0, 'started' => time(), 'finished' => 0, 'hash' => self::state_hash() ), false );
			wp_send_json_success( array( 'total' => $total, 'done' => 0, 'finished' => 0 === $total ) );
		}

		$scan = get_option( 'aa_scan' );
		if ( ! is_array( $scan ) || empty( $scan['state'] ) ) {
			wp_send_json_error( array( 'message' => 'not started' ) );
		}
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$in}) AND ID > %d ORDER BY ID ASC LIMIT %d", (int) $scan['last_id'], self::CHUNK ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		foreach ( (array) $ids as $id ) {
			$post = get_post( (int) $id );
			if ( $post ) {
				try {
					self::scan_post( $post );
				} catch ( \Throwable $e ) {
					$wpdb->insert( AA_DB::log_table(), array( 'post_id' => (int) $id, 'rule_id' => 0, 'status' => 'page_off', 'cnt' => 0, 'anchor_shown' => '', 'reason' => 'error', 'detail' => mb_substr( __( 'Scan error: ', 'anchor-autopilot' ) . $e->getMessage(), 0, 190 ), 'scanned_at' => current_time( 'mysql', true ) ) ); // One bad page must not stop the scan.
				}
			}
			$scan['last_id'] = (int) $id;
			$scan['done']++;
		}
		$finished = empty( $ids ) || count( $ids ) < self::CHUNK;
		if ( $finished ) {
			$scan['state']    = 'done';
			$scan['finished'] = time();
			$scan['hash']     = isset( $scan['hash'] ) ? $scan['hash'] : self::state_hash();
		}
		update_option( 'aa_scan', $scan, false );
		wp_send_json_success( array( 'total' => (int) $scan['total'], 'done' => (int) $scan['done'], 'finished' => $finished ) );
	}

	/* ------------------------------------------------------------------ */
	/* Reading                                                             */
	/* ------------------------------------------------------------------ */

	public static function scan_info() {
		$scan = get_option( 'aa_scan' );
		if ( ! is_array( $scan ) || empty( $scan['state'] ) ) {
			return array( 'state' => 'none' );
		}
		$scan['stale'] = ( 'done' === $scan['state'] && isset( $scan['hash'] ) && self::state_hash() !== $scan['hash'] );
		return $scan;
	}

	/** True when a skip means "this page already has the link": phrase is already a link, or the destination is already linked by hand. */
	public static function is_already( $reason, $detail ) {
		return in_array( $reason, array( 'in_link', 'in_link_same' ), true ) || ( 'already_linked' === $reason && 'existing' === $detail );
	}

	/** Per-rule numbers for the given rule IDs: id => array( pages, links, skipped, prot ). */
	public static function counts_for( array $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( ! $ids ) {
			return array();
		}
		$log  = AA_DB::log_table();
		$in   = implode( ',', $ids );
		$rows = $wpdb->get_results( "SELECT rule_id, SUM(status = 'linked') AS pages, SUM(CASE WHEN status = 'linked' THEN cnt ELSE 0 END) AS links, SUM(status = 'skipped') AS skipped, SUM(status = 'protected') AS prot, SUM(status = 'skipped' AND ( reason IN ('in_link','in_link_same') OR ( reason = 'already_linked' AND detail = 'existing' ) )) AS already FROM {$log} WHERE rule_id IN ({$in}) GROUP BY rule_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r->rule_id ] = array( 'pages' => (int) $r->pages, 'links' => (int) $r->links, 'skipped' => (int) $r->skipped, 'prot' => (int) $r->prot, 'already' => (int) $r->already );
		}
		return $out;
	}

	public static function view_data() {
		global $wpdb;
		$log   = AA_DB::log_table();
		$rules = AA_DB::table();
		$g     = function ( $k, $d = '' ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : $d; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		};
		$v = array(
			'scan'   => self::scan_info(),
			'tab'    => ( 'page' === $g( 'tab' ) ) ? 'page' : 'rule',
			'rule'   => (int) $g( 'rule', 0 ),
			'post'   => (int) $g( 'post', 0 ),
			'filter' => ( 'zero' === $g( 'f' ) ) ? 'zero' : '',
			'search' => $g( 's' ),
			'paged'  => max( 1, (int) $g( 'paged', 1 ) ),
			'per'    => 25,
		);

		$v['tiles'] = array(
			'pages'  => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$log} WHERE status = 'linked'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'links'  => (int) $wpdb->get_var( "SELECT COALESCE(SUM(cnt),0) FROM {$log} WHERE status = 'linked'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'active' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rules} WHERE status = 'active'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'zero'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rules} r WHERE r.status = 'active' AND NOT EXISTS ( SELECT 1 FROM {$log} l WHERE l.rule_id = r.id AND l.status = 'linked' )" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$v['problems'] = (array) $wpdb->get_results( "SELECT post_id, detail FROM {$log} WHERE status = 'page_off' ORDER BY id ASC LIMIT 50" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$v['problem_n'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log} WHERE status = 'page_off'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Detail: one rule.
		if ( $v['rule'] > 0 ) {
			$v['rule_row'] = AA_DB::get( $v['rule'] );
			$v['rule_log'] = (array) $wpdb->get_results( $wpdb->prepare( "SELECT l.*, p.post_title FROM {$log} l LEFT JOIN {$wpdb->posts} p ON p.ID = l.post_id WHERE l.rule_id = %d ORDER BY p.post_title ASC LIMIT 1000", $v['rule'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $v;
		}

		// Detail: one page.
		if ( $v['post'] > 0 ) {
			$v['post_obj'] = get_post( $v['post'] );
			$pr            = get_transient( 'aa_probe_result_' . get_current_user_id() );
			$v['probe']    = ( is_array( $pr ) && (int) $pr['post'] === $v['post'] ) ? $pr['res'] : null;
			$v['post_log'] = (array) $wpdb->get_results( $wpdb->prepare( "SELECT l.*, r.anchor AS rule_anchor, r.target_url, r.scope FROM {$log} l LEFT JOIN {$rules} r ON r.id = l.rule_id WHERE l.post_id = %d ORDER BY FIELD(l.status,'linked','skipped','protected','page_off'), l.anchor_shown ASC", $v['post'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $v;
		}

		// Lists.
		$per    = $v['per'];
		$offset = ( $v['paged'] - 1 ) * $per;
		if ( 'page' === $v['tab'] ) {
			$v['total'] = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$log} WHERE status = 'linked'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$v['rows']  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT l.post_id, SUM(l.cnt) AS links, COUNT(*) AS rules_n, p.post_title FROM {$log} l LEFT JOIN {$wpdb->posts} p ON p.ID = l.post_id WHERE l.status = 'linked' GROUP BY l.post_id, p.post_title ORDER BY links DESC, p.post_title ASC LIMIT %d OFFSET %d", $per, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $v;
		}

		if ( 'zero' === $v['filter'] ) {
			$v['total'] = $v['tiles']['zero'];
			$v['rows']  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT r.* FROM {$rules} r WHERE r.status = 'active' AND NOT EXISTS ( SELECT 1 FROM {$log} l WHERE l.rule_id = r.id AND l.status = 'linked' ) ORDER BY r.id DESC LIMIT %d OFFSET %d", $per, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$res        = AA_DB::query( array( 'search' => $v['search'], 'paged' => $v['paged'], 'per_page' => $per ) );
			$v['total'] = $res['total'];
			$v['rows']  = $res['rows'];
		}
		$ids        = array();
		foreach ( $v['rows'] as $r ) {
			$ids[] = (int) $r->id;
		}
		$v['counts'] = self::counts_for( $ids );
		return $v;
	}

	/* ------------------------------------------------------------------ */
	/* Export                                                              */
	/* ------------------------------------------------------------------ */

	private static function safe_cell( $s ) {
		$s = (string) $s;
		return ( '' !== $s && false !== strpos( "=+-@\t\r", $s[0] ) ) ? "'" . $s : $s;
	}

	public static function handle_export() {
		global $wpdb;
		if ( ! current_user_can( AA_Admin::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'anchor-autopilot' ), 403 );
		}
		check_admin_referer( 'aa_export_report' );
		$log   = AA_DB::log_table();
		$rules = AA_DB::table();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="anchor-autopilot-link-report-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$fh = fopen( 'php://output', 'w' );
		fwrite( $fh, "\xEF\xBB\xBF" );
		fputcsv( $fh, array( 'rule_id', 'anchor', 'destination', 'rule_type', 'page_title', 'page_url', 'result', 'times_linked', 'why' ) );
		$last = 0;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT l.*, r.anchor AS rule_anchor, r.target_url, r.scope, p.post_title FROM {$log} l LEFT JOIN {$rules} r ON r.id = l.rule_id LEFT JOIN {$wpdb->posts} p ON p.ID = l.post_id WHERE l.id > %d ORDER BY l.id ASC LIMIT 1000", $last ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $rows as $r ) {
				$last = (int) $r->id;
				$why  = '';
				$res  = $r->status;
				if ( 'skipped' === $r->status && self::is_already( $r->reason, $r->detail ) ) {
					$res = 'already_linked';
				}
				if ( 'page_off' === $r->status ) {
					$why = $r->detail;
				} elseif ( 'linked' !== $r->status ) {
					$why = AA_Admin::reason_label( $r->reason, $r->detail );
				}
				fputcsv(
					$fh,
					array(
						(int) $r->rule_id,
						self::safe_cell( $r->rule_anchor ? $r->rule_anchor : $r->anchor_shown ),
						self::safe_cell( $r->target_url ),
						'page' === $r->scope ? 'one page' : ( $r->scope ? 'whole site' : '' ),
						self::safe_cell( $r->post_title ),
						(string) get_permalink( (int) $r->post_id ),
						$res,
						(int) $r->cnt,
						self::safe_cell( $why ),
					)
				);
			}
		} while ( $rows && count( $rows ) === 1000 );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}

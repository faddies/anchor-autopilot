<?php
/**
 * Rules storage.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AA_DB {

	const DB_VERSION = '1.0.0';
	const MAX_RULES  = 20000;

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aa_rules';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  scope varchar(10) NOT NULL DEFAULT 'keyword',
  anchor varchar(190) NOT NULL,
  anchor_key varchar(190) NOT NULL,
  target_url text NOT NULL,
  target_key varchar(190) NOT NULL,
  from_url text NULL,
  from_key varchar(190) NOT NULL DEFAULT '',
  from_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  case_sensitive tinyint(1) NOT NULL DEFAULT 0,
  max_per_page smallint(5) unsigned NOT NULL DEFAULT 0,
  nofollow tinyint(2) NOT NULL DEFAULT -1,
  new_tab tinyint(2) NOT NULL DEFAULT -1,
  status varchar(10) NOT NULL DEFAULT 'active',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY scope_status (scope,status),
  KEY from_key (from_key),
  KEY from_post_id (from_post_id),
  KEY anchor_key (anchor_key),
  KEY target_key (target_key)
) {$charset};";
		dbDelta( $sql );
		update_option( 'aa_db_version', self::DB_VERSION );
		self::bump();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'aa_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/** Invalidate caches after any rule or setting change. */
	public static function bump() {
		update_option( 'aa_rules_version', (string) microtime( true ), false );
		delete_transient( 'aa_rules_cache' );
	}

	/* ------------------------------------------------------------------ */
	/* Runtime (front end)                                                 */
	/* ------------------------------------------------------------------ */

	private static function runtime_row( $row ) {
		$akey = $row->anchor_key;
		return array(
			'id'       => (int) $row->id,
			'scope'    => $row->scope,
			'anchor'   => $row->anchor,
			'akey'     => $akey,
			'w'        => AA_Engine::anchor_words( $row->anchor ),
			'len'      => strlen( $akey ),
			'url'      => $row->target_url,
			'key'      => $row->target_key,
			'case'     => (int) $row->case_sensitive,
			'max'      => (int) $row->max_per_page,
			'nofollow' => (int) $row->nofollow,
			'new_tab'  => (int) $row->new_tab,
		);
	}

	private static function cache() {
		global $wpdb;
		$ver   = (string) get_option( 'aa_rules_version', '0' );
		$cache = get_transient( 'aa_rules_cache' );
		if ( is_array( $cache ) && isset( $cache['v'] ) && $cache['v'] === $ver ) {
			return $cache;
		}
		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE scope = 'keyword' AND status = 'active' ORDER BY id ASC LIMIT %d", self::MAX_RULES ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rules = array();
		foreach ( (array) $rows as $row ) {
			$rules[] = self::runtime_row( $row );
		}
		$has_page = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE scope = 'page' AND status = 'active'" ) > 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cache    = array( 'v' => $ver, 'rules' => $rules, 'has_page' => $has_page );
		set_transient( 'aa_rules_cache', $cache, 12 * HOUR_IN_SECONDS );
		return $cache;
	}

	/** All rules that apply to a page: keyword rules plus page-specific rules for this page. */
	public static function runtime_rules( $post_id, $self_key ) {
		global $wpdb;
		$cache = self::cache();
		$rules = $cache['rules'];
		if ( ! empty( $cache['has_page'] ) ) {
			$table = self::table();
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE scope = 'page' AND status = 'active' AND ( from_key = %s OR ( from_post_id > 0 AND from_post_id = %d ) )", $self_key, (int) $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $rows as $row ) {
				$rules[] = self::runtime_row( $row );
			}
		}
		return $rules;
	}

	/* ------------------------------------------------------------------ */
	/* Admin queries                                                       */
	/* ------------------------------------------------------------------ */

	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function query( $args ) {
		global $wpdb;
		$table = self::table();
		$a     = array_merge(
			array( 'scope' => '', 'status' => '', 'search' => '', 'orderby' => 'id', 'order' => 'DESC', 'paged' => 1, 'per_page' => 20 ),
			$args
		);
		$where  = array( '1=1' );
		$params = array();
		if ( in_array( $a['scope'], array( 'keyword', 'page' ), true ) ) {
			$where[]  = 'scope = %s';
			$params[] = $a['scope'];
		}
		if ( in_array( $a['status'], array( 'active', 'paused' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $a['status'];
		}
		if ( '' !== $a['search'] ) {
			$like     = '%' . $wpdb->esc_like( $a['search'] ) . '%';
			$where[]  = '( anchor LIKE %s OR target_url LIKE %s OR from_url LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$cols    = array( 'id', 'anchor', 'target_url', 'from_url', 'scope', 'status', 'created_at' );
		$orderby = in_array( $a['orderby'], $cols, true ) ? $a['orderby'] : 'id';
		$order   = ( 'ASC' === strtoupper( $a['order'] ) ) ? 'ASC' : 'DESC';
		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$per    = max( 1, (int) $a['per_page'] );
		$offset = max( 0, ( (int) $a['paged'] - 1 ) * $per );
		$sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$p2     = array_merge( $params, array( $per, $offset ) );
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $p2 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array( 'rows' => (array) $rows, 'total' => $total );
	}

	/** Iterate all rows in chunks (used by export and the conflicts report). */
	public static function all_rows( $scope = '', $limit = 100000 ) {
		global $wpdb;
		$table = self::table();
		if ( in_array( $scope, array( 'keyword', 'page' ), true ) ) {
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE scope = %s ORDER BY id ASC LIMIT %d", $scope, (int) $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id ASC LIMIT %d", (int) $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function stats() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT scope, status, COUNT(*) AS n FROM {$table} GROUP BY scope, status" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$s     = array( 'keyword' => 0, 'page' => 0, 'active' => 0, 'paused' => 0, 'total' => 0 );
		foreach ( (array) $rows as $r ) {
			$n = (int) $r->n;
			$s['total'] += $n;
			if ( isset( $s[ $r->scope ] ) ) {
				$s[ $r->scope ] += $n;
			}
			if ( isset( $s[ $r->status ] ) ) {
				$s[ $r->status ] += $n;
			}
		}
		return $s;
	}

	public static function delete( array $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( ! $ids ) {
			return 0;
		}
		$table = self::table();
		$in    = implode( ',', $ids );
		$n     = (int) $wpdb->query( "DELETE FROM {$table} WHERE id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::bump();
		return $n;
	}

	public static function delete_all( $scope = '' ) {
		global $wpdb;
		$table = self::table();
		if ( in_array( $scope, array( 'keyword', 'page' ), true ) ) {
			$n = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE scope = %s", $scope ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$n = (int) $wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		self::bump();
		return $n;
	}

	public static function set_status( array $ids, $status ) {
		global $wpdb;
		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( ! $ids || ! in_array( $status, array( 'active', 'paused' ), true ) ) {
			return 0;
		}
		$table = self::table();
		$in    = implode( ',', $ids );
		$n     = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s, updated_at = %s WHERE id IN ({$in})", $status, current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::bump();
		return $n;
	}

	/* ------------------------------------------------------------------ */
	/* Validation and saving                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Validate and save one rule.
	 *
	 * @param array  $in       Raw input: scope, anchor, url, from, case_sensitive, max, nofollow, new_tab, status.
	 * @param string $dup_mode 'update' to overwrite an existing identical anchor, 'skip' to leave it.
	 * @param int    $edit_id  Row being edited (0 = new).
	 * @param bool   $resolve  Look up the From page in WordPress (slower).
	 * @return array status (inserted|updated|skipped|error), message, id, warnings[].
	 */
	public static function save( array $in, $dup_mode = 'update', $edit_id = 0, $resolve = true ) {
		global $wpdb;
		$res = array( 'status' => 'error', 'message' => '', 'id' => 0, 'warnings' => array() );

		$scope = ( isset( $in['scope'] ) && 'page' === $in['scope'] ) ? 'page' : 'keyword';

		$anchor = isset( $in['anchor'] ) ? wp_strip_all_tags( (string) $in['anchor'] ) : '';
		$anchor = trim( preg_replace( '/\s+/u', ' ', $anchor ) );
		if ( '' === $anchor ) {
			$res['message'] = __( 'Anchor text is empty.', 'anchor-autopilot' );
			return $res;
		}
		if ( ! preg_match( '/[\p{L}\p{N}]/u', $anchor ) ) {
			$res['message'] = __( 'Anchor text needs at least one letter or number.', 'anchor-autopilot' );
			return $res;
		}
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $anchor, 'UTF-8' ) > 190 : strlen( $anchor ) > 190 ) {
			$res['message'] = __( 'Anchor text is too long (190 characters max).', 'anchor-autopilot' );
			return $res;
		}

		$allow_ext = (bool) AA_Settings::get( 'allow_external' );
		list( $target, $err ) = AA_Url::clean_input( isset( $in['url'] ) ? $in['url'] : '', $allow_ext );
		if ( '' !== $err ) {
			$res['message'] = sprintf( /* translators: %s: error */ __( 'Destination URL: %s', 'anchor-autopilot' ), $err );
			return $res;
		}
		$tkey = AA_Url::key( $target );

		$from = '';
		$fkey = '';
		$fid  = 0;
		if ( 'page' === $scope ) {
			list( $from, $err ) = AA_Url::clean_input( isset( $in['from'] ) ? $in['from'] : '', false );
			if ( '' !== $err ) {
				$res['message'] = sprintf( /* translators: %s: error */ __( 'From page: %s', 'anchor-autopilot' ), $err );
				return $res;
			}
			$fkey = AA_Url::key( $from );
			if ( $fkey === $tkey ) {
				$res['message'] = __( 'From and To are the same page: a page cannot link to itself.', 'anchor-autopilot' );
				return $res;
			}
			if ( $resolve ) {
				$fid = AA_Url::resolve_post_id( $from );
				if ( ! $fid ) {
					$res['warnings'][] = __( 'The From URL did not match a published page or post on this site. The rule is saved but may never fire.', 'anchor-autopilot' );
				}
			}
		}

		$akey = AA_Engine::anchor_key( $anchor );
		$table = self::table();

		// Duplicate detection (same anchor in the same scope; page rules also same From page).
		if ( 'page' === $scope ) {
			$dup = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE scope = 'page' AND anchor_key = %s AND from_key = %s AND id <> %d LIMIT 1", $akey, $fkey, (int) $edit_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$dup = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE scope = 'keyword' AND anchor_key = %s AND id <> %d LIMIT 1", $akey, (int) $edit_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( $dup && $edit_id ) {
			$res['message'] = ( 'page' === $scope )
				? __( 'Another rule already uses this anchor on this From page.', 'anchor-autopilot' )
				: __( 'Another keyword rule already uses this anchor text. One anchor can only point to one URL.', 'anchor-autopilot' );
			return $res;
		}
		if ( $dup ) {
			if ( 'skip' === $dup_mode ) {
				$res['status']  = 'skipped';
				$res['message'] = ( $dup->target_key === $tkey )
					? __( 'Already exists (same anchor and URL).', 'anchor-autopilot' )
					: sprintf( /* translators: %s: URL */ __( 'Anchor already points to %s (kept).', 'anchor-autopilot' ), $dup->target_url );
				$res['id'] = (int) $dup->id;
				return $res;
			}
			$edit_id = (int) $dup->id;
		}

		if ( ! $edit_id ) {
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $count >= self::MAX_RULES ) {
				$res['message'] = sprintf( /* translators: %d: limit */ __( 'Rule limit reached (%d).', 'anchor-autopilot' ), self::MAX_RULES );
				return $res;
			}
		}

		$tri = function ( $v ) {
			if ( '' === $v || null === $v ) {
				return -1;
			}
			$v = strtolower( trim( (string) $v ) );
			if ( in_array( $v, array( '1', 'yes', 'y', 'true', 'on' ), true ) ) {
				return 1;
			}
			if ( in_array( $v, array( '0', 'no', 'n', 'false', 'off' ), true ) ) {
				return 0;
			}
			return -1;
		};

		$now  = current_time( 'mysql', true );
		$data = array(
			'scope'          => $scope,
			'anchor'         => $anchor,
			'anchor_key'     => $akey,
			'target_url'     => $target,
			'target_key'     => $tkey,
			'from_url'       => $from,
			'from_key'       => $fkey,
			'from_post_id'   => (int) $fid,
			'case_sensitive' => ( isset( $in['case_sensitive'] ) && ( 1 === (int) $tri( $in['case_sensitive'] ) ) ) ? 1 : 0,
			'max_per_page'   => isset( $in['max'] ) ? max( 0, min( 50, (int) $in['max'] ) ) : 0,
			'nofollow'       => $tri( isset( $in['nofollow'] ) ? $in['nofollow'] : '' ),
			'new_tab'        => $tri( isset( $in['new_tab'] ) ? $in['new_tab'] : '' ),
			'status'         => ( isset( $in['status'] ) && 'paused' === $in['status'] ) ? 'paused' : 'active',
			'updated_at'     => $now,
		);
		$fmt = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' );

		if ( $edit_id ) {
			$ok = $wpdb->update( $table, $data, array( 'id' => $edit_id ), $fmt, array( '%d' ) );
			if ( false === $ok ) {
				$res['message'] = __( 'Database error while saving.', 'anchor-autopilot' );
				return $res;
			}
			$res['status'] = 'updated';
			$res['id']     = $edit_id;
		} else {
			$data['created_at'] = $now;
			$fmt[]              = '%s';
			$ok = $wpdb->insert( $table, $data, $fmt );
			if ( ! $ok ) {
				$res['message'] = __( 'Database error while saving.', 'anchor-autopilot' );
				return $res;
			}
			$res['status'] = 'inserted';
			$res['id']     = (int) $wpdb->insert_id;
		}
		self::bump();
		return $res;
	}
}

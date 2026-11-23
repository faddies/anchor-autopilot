<?php
/**
 * Rules list table.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AA_Rules_Table extends WP_List_Table {

	private $scan   = array( 'state' => 'none' );
	private $counts = array();

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'rule',
				'plural'   => 'rules',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" />',
			'anchor'  => __( 'Anchor text', 'anchor-autopilot' ),
			'target'  => __( 'Links to', 'anchor-autopilot' ),
			'scope'   => __( 'Applies to', 'anchor-autopilot' ),
			'live'    => __( 'Live links', 'anchor-autopilot' ),
			'options' => __( 'Options', 'anchor-autopilot' ),
			'status'  => __( 'Status', 'anchor-autopilot' ),
			'created' => __( 'Added', 'anchor-autopilot' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'anchor'  => array( 'anchor', false ),
			'target'  => array( 'target_url', false ),
			'created' => array( 'created_at', true ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'pause'    => __( 'Pause', 'anchor-autopilot' ),
			'activate' => __( 'Activate', 'anchor-autopilot' ),
			'delete'   => __( 'Delete', 'anchor-autopilot' ),
		);
	}

	protected function get_views() {
		$s       = AA_DB::stats();
		$current = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base    = admin_url( 'admin.php?page=aa-rules' );
		$items   = array(
			'all'     => array( __( 'All', 'anchor-autopilot' ), $s['total'] ),
			'keyword' => array( __( 'Keyword rules', 'anchor-autopilot' ), $s['keyword'] ),
			'page'    => array( __( 'Page-specific', 'anchor-autopilot' ), $s['page'] ),
			'paused'  => array( __( 'Paused', 'anchor-autopilot' ), $s['paused'] ),
		);
		$out = array();
		foreach ( $items as $k => $v ) {
			$url       = 'all' === $k ? $base : add_query_arg( 'view', $k, $base );
			$out[ $k ] = sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( $url ), $current === $k ? ' class="current"' : '', esc_html( $v[0] ), (int) $v[1] );
		}
		return $out;
	}

	public function no_items() {
		esc_html_e( 'No rules yet. Add one above or import a CSV.', 'anchor-autopilot' );
	}

	public function prepare_items() {
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args = array(
			'search'  => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'orderby' => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'id', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'order'   => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'DESC', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'paged'   => $this->get_pagenum(),
			'per_page' => 25,
		);
		if ( in_array( $view, array( 'keyword', 'page' ), true ) ) {
			$args['scope'] = $view;
		} elseif ( 'paused' === $view ) {
			$args['status'] = 'paused';
		}
		$res = AA_DB::query( $args );
		$this->items = $res['rows'];
		$ids = array();
		foreach ( $this->items as $it ) {
			$ids[] = (int) $it->id;
		}
		$this->scan   = AA_Report::scan_info();
		$this->counts = AA_Report::counts_for( $ids );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $res['total'],
				'per_page'    => $args['per_page'],
			)
		);
	}

	protected function column_cb( $item ) {
		return '<input type="checkbox" name="rule[]" value="' . (int) $item->id . '" />';
	}

	protected function column_anchor( $item ) {
		$edit   = admin_url( 'admin.php?page=aa-rules&action=edit&id=' . (int) $item->id );
		$toggle = ( 'active' === $item->status ) ? 'pause' : 'activate';
		$mk     = function ( $do ) use ( $item ) {
			return wp_nonce_url( admin_url( 'admin-post.php?action=aa_row&do=' . $do . '&id=' . (int) $item->id ), 'aa_row_' . $do . '_' . (int) $item->id );
		};
		$actions = array(
			'edit'   => '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'anchor-autopilot' ) . '</a>',
			'toggle' => '<a href="' . esc_url( $mk( $toggle ) ) . '">' . esc_html( 'pause' === $toggle ? __( 'Pause', 'anchor-autopilot' ) : __( 'Activate', 'anchor-autopilot' ) ) . '</a>',
			'delete' => '<a class="aa-delete" href="' . esc_url( $mk( 'delete' ) ) . '">' . esc_html__( 'Delete', 'anchor-autopilot' ) . '</a>',
		);
		return '<strong><a href="' . esc_url( $edit ) . '">' . esc_html( $item->anchor ) . '</a></strong>' . $this->row_actions( $actions );
	}

	protected function column_target( $item ) {
		$short = preg_replace( '~^https?://(www\.)?~i', '', $item->target_url );
		return '<a href="' . esc_url( $item->target_url ) . '" target="_blank" rel="noopener">' . esc_html( $this->cut( $short, 60 ) ) . '</a>';
	}

	protected function column_scope( $item ) {
		if ( 'page' === $item->scope ) {
			$short = preg_replace( '~^https?://(www\.)?~i', '', (string) $item->from_url );
			return '<span class="aa-badge aa-badge-page">' . esc_html__( 'One page', 'anchor-autopilot' ) . '</span><br><a href="' . esc_url( $item->from_url ) . '" target="_blank" rel="noopener">' . esc_html( $this->cut( $short, 50 ) ) . '</a>';
		}
		return '<span class="aa-badge aa-badge-kw">' . esc_html__( 'Whole site', 'anchor-autopilot' ) . '</span>';
	}

	protected function column_live( $item ) {
		$url = admin_url( 'admin.php?page=aa-report&rule=' . (int) $item->id );
		if ( 'done' !== $this->scan['state'] ) {
			return '<a href="' . esc_url( admin_url( 'admin.php?page=aa-report' ) ) . '" class="description">' . esc_html__( 'Not scanned', 'anchor-autopilot' ) . '</a>';
		}
		$c = isset( $this->counts[ (int) $item->id ] ) ? $this->counts[ (int) $item->id ] : array( 'pages' => 0, 'links' => 0, 'already' => 0 );
		$al = isset( $c['already'] ) && $c['already'] > 0
			? '<br><span class="description">' . esc_html( sprintf( /* translators: %d: pages */ _n( 'already linked on %d page', 'already linked on %d pages', (int) $c['already'], 'anchor-autopilot' ), (int) $c['already'] ) ) . '</span>'
			: '';
		if ( 0 === (int) $c['pages'] ) {
			return '<a href="' . esc_url( $url ) . '"><span class="aa-badge aa-badge-off">' . esc_html__( '0 pages', 'anchor-autopilot' ) . '</span></a>' . $al;
		}
		/* translators: 1: links, 2: pages */
		return '<a href="' . esc_url( $url ) . '">' . esc_html( sprintf( _n( '%1$d link on %2$d page', '%1$d links on %2$d pages', (int) $c['pages'], 'anchor-autopilot' ), (int) $c['links'], (int) $c['pages'] ) ) . '</a>' . $al;
	}

	protected function column_options( $item ) {
		$o = array();
		if ( $item->case_sensitive ) {
			$o[] = __( 'case-sensitive', 'anchor-autopilot' );
		}
		if ( (int) $item->max_per_page > 0 ) {
			/* translators: %d: number */
			$o[] = sprintf( __( 'max %d', 'anchor-autopilot' ), (int) $item->max_per_page );
		}
		if ( 1 === (int) $item->nofollow ) {
			$o[] = 'nofollow';
		} elseif ( 0 === (int) $item->nofollow ) {
			$o[] = 'follow';
		}
		if ( 1 === (int) $item->new_tab ) {
			$o[] = __( 'new tab', 'anchor-autopilot' );
		}
		return $o ? esc_html( implode( ', ', $o ) ) : '<span class="description">' . esc_html__( 'defaults', 'anchor-autopilot' ) . '</span>';
	}

	protected function column_status( $item ) {
		if ( 'active' === $item->status ) {
			return '<span class="aa-badge aa-badge-on">' . esc_html__( 'Active', 'anchor-autopilot' ) . '</span>';
		}
		return '<span class="aa-badge aa-badge-off">' . esc_html__( 'Paused', 'anchor-autopilot' ) . '</span>';
	}

	protected function column_created( $item ) {
		$ts = strtotime( $item->created_at . ' UTC' );
		return esc_html( $ts ? date_i18n( get_option( 'date_format' ), $ts ) : '' );
	}

	private function cut( $s, $n ) {
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $s, 'UTF-8' ) > $n ) {
			return mb_substr( $s, 0, $n - 1, 'UTF-8' ) . '…';
		}
		return strlen( $s ) > $n ? substr( $s, 0, $n - 1 ) . '…' : $s;
	}
}

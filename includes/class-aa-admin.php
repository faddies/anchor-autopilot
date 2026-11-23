<?php
/**
 * Admin screens and form handlers.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AA_Admin {

	public static function cap() {
		return apply_filters( 'aa_capability', 'manage_options' );
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_notice' ) );
		add_filter( 'plugin_action_links_' . AA_BASENAME, array( __CLASS__, 'action_links' ) );

		add_action( 'admin_post_aa_save_rule', array( __CLASS__, 'handle_save_rule' ) );
		add_action( 'admin_post_aa_row', array( __CLASS__, 'handle_row' ) );
		add_action( 'admin_post_aa_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_aa_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_aa_sample', array( __CLASS__, 'handle_sample' ) );
		add_action( 'admin_post_aa_check_page', array( __CLASS__, 'handle_check_page' ) );
		add_action( 'admin_post_aa_export_report', array( 'AA_Report', 'handle_export' ) );
		add_action( 'wp_ajax_aa_scan', array( 'AA_Report', 'ajax_scan' ) );
		add_action( 'admin_post_aa_save_settings', array( __CLASS__, 'handle_settings' ) );
		add_action( 'load-toplevel_page_aa-rules', array( __CLASS__, 'handle_bulk' ) );

		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	public static function menu() {
		$cap = self::cap();
		add_menu_page( __( 'Anchor Autopilot', 'anchor-autopilot' ), __( 'Anchor Autopilot', 'anchor-autopilot' ), $cap, 'aa-rules', array( __CLASS__, 'page_rules' ), 'dashicons-admin-links', 58 );
		add_submenu_page( 'aa-rules', __( 'Rules', 'anchor-autopilot' ), __( 'Rules', 'anchor-autopilot' ), $cap, 'aa-rules', array( __CLASS__, 'page_rules' ) );
		add_submenu_page( 'aa-rules', __( 'Link Report', 'anchor-autopilot' ), __( 'Link Report', 'anchor-autopilot' ), $cap, 'aa-report', array( __CLASS__, 'page_report' ) );
		add_submenu_page( 'aa-rules', __( 'Import / Export', 'anchor-autopilot' ), __( 'Import / Export', 'anchor-autopilot' ), $cap, 'aa-import', array( __CLASS__, 'page_import' ) );
		add_submenu_page( 'aa-rules', __( 'Conflicts', 'anchor-autopilot' ), __( 'Conflicts', 'anchor-autopilot' ), $cap, 'aa-conflicts', array( __CLASS__, 'page_conflicts' ) );
		add_submenu_page( 'aa-rules', __( 'Test', 'anchor-autopilot' ), __( 'Test', 'anchor-autopilot' ), $cap, 'aa-test', array( __CLASS__, 'page_test' ) );
		add_submenu_page( 'aa-rules', __( 'Settings', 'anchor-autopilot' ), __( 'Settings', 'anchor-autopilot' ), $cap, 'aa-settings', array( __CLASS__, 'page_settings' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=aa-rules' ) ) . '">' . esc_html__( 'Rules', 'anchor-autopilot' ) . '</a>' );
		return $links;
	}

	public static function is_our_screen() {
		return isset( $_GET['page'] ) && 0 === strpos( sanitize_key( $_GET['page'] ), 'aa-' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public static function assets() {
		if ( ! self::is_our_screen() ) {
			return;
		}
		wp_enqueue_style( 'aa-admin', AA_URL . 'assets/admin.css', array(), AA_VERSION );
		wp_enqueue_script( 'aa-admin', AA_URL . 'assets/admin.js', array(), AA_VERSION, true );
		wp_localize_script(
			'aa-admin',
			'aaScan',
			array(
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'aa_scan' ),
				'scanning' => __( 'Scanning… %1$d of %2$d pages', 'anchor-autopilot' ),
				'failed'   => __( 'The scan stopped (server error). Run it again; it starts over.', 'anchor-autopilot' ),
			)
		);
		wp_localize_script( 'aa-admin', 'aaI18n', array( 'confirmDelete' => __( 'Delete this rule?', 'anchor-autopilot' ), 'confirmReplace' => __( 'This deletes all existing rules of this kind before importing. Continue?', 'anchor-autopilot' ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Notices                                                             */
	/* ------------------------------------------------------------------ */

	public static function notice( $type, $msg ) {
		set_transient( 'aa_notice_' . get_current_user_id(), array( $type, $msg ), 120 );
	}

	public static function show_notice() {
		if ( ! self::is_our_screen() ) {
			return;
		}
		$key = 'aa_notice_' . get_current_user_id();
		$n   = get_transient( $key );
		if ( is_array( $n ) ) {
			delete_transient( $key );
			$class = in_array( $n[0], array( 'success', 'error', 'warning', 'info' ), true ) ? $n[0] : 'info';
			echo '<div class="notice notice-' . esc_attr( $class ) . ' is-dismissible"><p>' . wp_kses( $n[1], array( 'strong' => array(), 'code' => array(), 'br' => array() ) ) . '</p></div>';
		}
	}

	private static function back( $page = 'aa-rules', $args = array() ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) ) );
		exit;
	}

	private static function guard( $nonce_action ) {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'anchor-autopilot' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	/* ------------------------------------------------------------------ */
	/* Pages                                                               */
	/* ------------------------------------------------------------------ */

	private static function header( $title, $current ) {
		$tabs = array(
			'aa-rules'     => __( 'Rules', 'anchor-autopilot' ),
			'aa-report'    => __( 'Link Report', 'anchor-autopilot' ),
			'aa-import'    => __( 'Import / Export', 'anchor-autopilot' ),
			'aa-conflicts' => __( 'Conflicts', 'anchor-autopilot' ),
			'aa-test'      => __( 'Test', 'anchor-autopilot' ),
			'aa-settings'  => __( 'Settings', 'anchor-autopilot' ),
		);
		echo '<div class="wrap aa-wrap"><h1 class="aa-title"><span class="dashicons dashicons-admin-links"></span> ' . esc_html( $title ) . ' <span class="aa-ver">v' . esc_html( AA_VERSION ) . '</span></h1>';
		echo '<nav class="nav-tab-wrapper aa-tabs">';
		foreach ( $tabs as $slug => $label ) {
			echo '<a class="nav-tab' . ( $slug === $current ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		if ( ! AA_Settings::get( 'enabled' ) ) {
			echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Automatic linking is switched off.', 'anchor-autopilot' ) . '</strong> ' . esc_html__( 'Rules are saved but no links are shown on the site. Turn it on in Settings.', 'anchor-autopilot' ) . '</p></div>';
		}
	}

	private static function guard_page() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'anchor-autopilot' ), 403 );
		}
	}

	public static function page_rules() {
		self::guard_page();
		$stats  = AA_DB::stats();
		$edit   = null;
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit' === $action && isset( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$edit = AA_DB::get( (int) $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$form = get_transient( 'aa_form_' . get_current_user_id() );
		if ( is_array( $form ) ) {
			delete_transient( 'aa_form_' . get_current_user_id() );
		} else {
			$form = null;
		}
		$table = new AA_Rules_Table();
		$table->prepare_items();

		self::header( __( 'Anchor Autopilot', 'anchor-autopilot' ), 'aa-rules' );
		include AA_PATH . 'admin/views/rules.php';
		echo '</div>';
	}

	public static function page_report() {
		self::guard_page();
		$v = AA_Report::view_data();
		self::header( __( 'Link Report', 'anchor-autopilot' ), 'aa-report' );
		include AA_PATH . 'admin/views/report.php';
		echo '</div>';
	}

	public static function handle_check_page() {
		self::guard( 'aa_check_page' );
		$ref = isset( $_POST['page_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['page_ref'] ) ) : '';
		$p   = null;
		if ( ctype_digit( $ref ) ) {
			$p = get_post( (int) $ref );
		} elseif ( '' !== $ref ) {
			$id = (int) url_to_postid( $ref );
			$p  = $id ? get_post( $id ) : null;
		}
		if ( ! $p ) {
			self::notice( 'error', esc_html__( 'Could not find that page. Enter its full URL or its post ID.', 'anchor-autopilot' ) );
			self::back( 'aa-report', array( 'tab' => 'page' ) );
		}
		AA_Report::scan_post( $p );
		self::back( 'aa-report', array( 'tab' => 'page', 'post' => $p->ID ) );
	}

	public static function page_import() {
		self::guard_page();
		$result = get_transient( 'aa_import_' . get_current_user_id() );
		if ( $result ) {
			delete_transient( 'aa_import_' . get_current_user_id() );
		}
		self::header( __( 'Import / Export', 'anchor-autopilot' ), 'aa-import' );
		include AA_PATH . 'admin/views/import.php';
		echo '</div>';
	}

	public static function page_conflicts() {
		self::guard_page();
		$report = AA_Conflicts::analyze();
		self::header( __( 'Conflicts', 'anchor-autopilot' ), 'aa-conflicts' );
		include AA_PATH . 'admin/views/conflicts.php';
		echo '</div>';
	}

	public static function page_settings() {
		self::guard_page();
		$s = AA_Settings::get_all();
		self::header( __( 'Settings', 'anchor-autopilot' ), 'aa-settings' );
		include AA_PATH . 'admin/views/settings.php';
		echo '</div>';
	}

	public static function reason_label( $reason, $detail ) {
		switch ( $reason ) {
			case 'self':
				return __( 'This page is the destination (a page never links to itself).', 'anchor-autopilot' );
			case 'already_linked':
				if ( 'existing' === $detail ) {
					return __( 'This page already links to the destination (added by hand or by the theme), so no second link is added. Raise "Max links to the same URL" in Settings if you want both.', 'anchor-autopilot' );
				}
				return __( 'Another rule already links to this destination on this page (per-URL limit reached).', 'anchor-autopilot' );
			case 'in_link_same':
				return __( 'The phrase is already a link to this same destination on this page (added by hand, by the theme or a builder), so it is left as it is.', 'anchor-autopilot' );
			case 'in_link':
				/* translators: %s: URL */
				return sprintf( __( 'The phrase is already a link on this page (to %s), so it is left as it is.', 'anchor-autopilot' ), $detail );
			case 'protected':
				return __( 'The phrase only appears in a protected area (heading, menu, button, hidden or similar), which is never linked.', 'anchor-autopilot' );
			case 'page_cap':
				return __( 'The maximum number of links per page was reached.', 'anchor-autopilot' );
			case 'anchor_conflict':
				/* translators: %s: URL key */
				return sprintf( __( 'The same anchor already links elsewhere on this page (%s).', 'anchor-autopilot' ), $detail );
			case 'overlap':
				/* translators: %s: longer phrase */
				return sprintf( __( 'Only found inside a longer phrase that wins: "%s".', 'anchor-autopilot' ), $detail );
		}
		return $reason;
	}

	public static function page_test() {
		self::guard_page();
		$out = null;
		$in  = array( 'source' => 'post', 'post_ref' => '', 'html' => '', 'page_url' => home_url( '/' ) );
		if ( isset( $_POST['aa_test_run'] ) ) {
			check_admin_referer( 'aa_test' );
			$in['source']   = ( isset( $_POST['source'] ) && 'html' === $_POST['source'] ) ? 'html' : 'post';
			$in['post_ref'] = isset( $_POST['post_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['post_ref'] ) ) : '';
			$in['html']     = isset( $_POST['html'] ) ? wp_kses_post( wp_unslash( $_POST['html'] ) ) : '';
			$in['page_url'] = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : home_url( '/' );
			$out            = self::run_test( $in );
		}
		self::header( __( 'Test', 'anchor-autopilot' ), 'aa-test' );
		include AA_PATH . 'admin/views/test.php';
		echo '</div>';
	}

	private static function run_test( $in ) {
		$res = array( 'error' => '', 'warning' => '', 'html' => '', 'report' => null, 'rules' => 0, 'title' => '' );
		$post_id  = 0;
		$self_url = $in['page_url'];
		$html     = $in['html'];

		if ( 'post' === $in['source'] ) {
			$ref = $in['post_ref'];
			$p   = null;
			if ( ctype_digit( $ref ) ) {
				$p = get_post( (int) $ref );
			} elseif ( '' !== $ref ) {
				$id = (int) url_to_postid( $ref );
				$p  = $id ? get_post( $id ) : null;
			}
			if ( ! $p ) {
				$res['error'] = __( 'Could not find that post. Enter a post ID or its full URL.', 'anchor-autopilot' );
				return $res;
			}
			$post_id  = $p->ID;
			$self_url = get_permalink( $p );
			$res['title'] = get_the_title( $p );
			$reason = AA_Frontend::skip_reason( $p );
			if ( '' !== $reason ) {
				$res['warning'] = $reason . ' ' . __( 'The test below ignores this, so you can still see what would happen.', 'anchor-autopilot' );
			}
			$html = self::render_post_html( $p );
			if ( '' === trim( $html ) ) {
				$res['error'] = __( 'This post has no content to test.', 'anchor-autopilot' );
				return $res;
			}
		} elseif ( '' === trim( $html ) ) {
			$res['error'] = __( 'Paste some HTML to test.', 'anchor-autopilot' );
			return $res;
		} else {
			$post_id = (int) url_to_postid( $self_url );
		}

		list( $out, $report, $count ) = AA_Frontend::process_with_report( $html, $post_id, $self_url, array( 'link_class' => 'aa-hl' ) );
		$res['html']   = $out;
		$res['report'] = $report;
		$res['rules']  = $count;
		return $res;
	}

	/** Rendered body HTML of a post, the way the site would produce it (builder-aware where possible). */
	public static function render_post_html( $p ) {
		$html = '';
		if ( class_exists( '\Elementor\Plugin' ) && get_post_meta( $p->ID, '_elementor_data', true ) ) {
			try {
				$el = \Elementor\Plugin::$instance;
				if ( $el && isset( $el->frontend ) ) {
					$html = (string) $el->frontend->get_builder_content_for_display( $p->ID, true );
				}
			} catch ( \Throwable $e ) {
				$html = '';
			}
		}
		if ( '' === trim( $html ) ) {
			global $post;
			$old = $post;
			remove_filter( 'the_content', array( 'AA_Frontend', 'filter' ), 99999 );
			try {
				$post = $p; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				setup_postdata( $p );
				$html = (string) apply_filters( 'the_content', $p->post_content );
			} catch ( \Throwable $e ) {
				$html = '';
			}
			add_filter( 'the_content', array( 'AA_Frontend', 'filter' ), 99999 );
			$post = $old; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $old ) {
				setup_postdata( $old );
			} else {
				wp_reset_postdata();
			}
		}
		return $html;
	}

	/* ------------------------------------------------------------------ */
	/* Handlers                                                            */
	/* ------------------------------------------------------------------ */

	public static function handle_save_rule() {
		self::guard( 'aa_save_rule' );
		$id = isset( $_POST['rule_id'] ) ? (int) $_POST['rule_id'] : 0;
		$raw = array(
			'scope'          => isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'keyword',
			'anchor'         => isset( $_POST['anchor'] ) ? sanitize_text_field( wp_unslash( $_POST['anchor'] ) ) : '',
			'url'            => isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '',
			'from'           => isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '',
			'case_sensitive' => ! empty( $_POST['case_sensitive'] ) ? '1' : '0',
			'max'            => isset( $_POST['max'] ) ? (int) $_POST['max'] : 0,
			'nofollow'       => isset( $_POST['nofollow'] ) ? sanitize_text_field( wp_unslash( $_POST['nofollow'] ) ) : '',
			'new_tab'        => isset( $_POST['new_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['new_tab'] ) ) : '',
			'status'         => ( isset( $_POST['status'] ) && 'paused' === $_POST['status'] ) ? 'paused' : 'active',
		);
		$res = AA_DB::save( $raw, 'skip', $id, true );
		if ( 'inserted' === $res['status'] || 'updated' === $res['status'] ) {
			$msg = ( 'inserted' === $res['status'] ) ? __( 'Rule added.', 'anchor-autopilot' ) : __( 'Rule saved.', 'anchor-autopilot' );
			if ( $res['warnings'] ) {
				self::notice( 'warning', esc_html( $msg . ' ' . implode( ' ', $res['warnings'] ) ) );
			} else {
				self::notice( 'success', esc_html( $msg ) );
			}
			self::back();
		}
		set_transient( 'aa_form_' . get_current_user_id(), $raw + array( 'rule_id' => $id ), 120 );
		self::notice( 'error', esc_html( $res['message'] ) );
		self::back( 'aa-rules', $id ? array( 'action' => 'edit', 'id' => $id ) : array() );
	}

	public static function handle_row() {
		$do = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'anchor-autopilot' ), 403 );
		}
		check_admin_referer( 'aa_row_' . $do . '_' . $id );
		if ( 'delete' === $do ) {
			AA_DB::delete( array( $id ) );
			self::notice( 'success', esc_html__( 'Rule deleted.', 'anchor-autopilot' ) );
		} elseif ( 'pause' === $do ) {
			AA_DB::set_status( array( $id ), 'paused' );
			self::notice( 'success', esc_html__( 'Rule paused.', 'anchor-autopilot' ) );
		} elseif ( 'activate' === $do ) {
			AA_DB::set_status( array( $id ), 'active' );
			self::notice( 'success', esc_html__( 'Rule activated.', 'anchor-autopilot' ) );
		}
		self::back();
	}

	public static function handle_bulk() {
		if ( empty( $_POST['aa_bulk'] ) ) {
			return;
		}
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'anchor-autopilot' ), 403 );
		}
		check_admin_referer( 'aa_bulk', 'aa_bulk_nonce' );
		$do = '';
		foreach ( array( 'action', 'action2' ) as $k ) {
			if ( ! empty( $_POST[ $k ] ) && '-1' !== $_POST[ $k ] ) {
				$do = sanitize_key( wp_unslash( $_POST[ $k ] ) );
				break;
			}
		}
		$ids = isset( $_POST['rule'] ) ? array_map( 'intval', (array) $_POST['rule'] ) : array();
		if ( $do && $ids ) {
			if ( 'delete' === $do ) {
				$n = AA_DB::delete( $ids );
				/* translators: %d: count */
				self::notice( 'success', esc_html( sprintf( _n( '%d rule deleted.', '%d rules deleted.', $n, 'anchor-autopilot' ), $n ) ) );
			} elseif ( 'pause' === $do || 'activate' === $do ) {
				$n = AA_DB::set_status( $ids, 'pause' === $do ? 'paused' : 'active' );
				/* translators: %d: count */
				self::notice( 'success', esc_html( sprintf( _n( '%d rule updated.', '%d rules updated.', $n, 'anchor-autopilot' ), $n ) ) );
			}
		}
		self::back();
	}

	public static function handle_import() {
		self::guard( 'aa_import' );
		$type = ( isset( $_POST['type'] ) && 'page' === $_POST['type'] ) ? 'page' : 'keyword';
		$dup  = ( isset( $_POST['dup'] ) && 'skip' === $_POST['dup'] ) ? 'skip' : 'update';

		$rows = array();
		$err  = '';
		$pasted = isset( $_POST['paste'] ) ? trim( (string) wp_unslash( $_POST['paste'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $_FILES['csv']['tmp_name'] ) ) {
			list( $rows, $err ) = AA_CSV::parse_upload( $_FILES['csv'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		} elseif ( '' !== $pasted ) {
			list( $rows, $err ) = AA_CSV::parse_string( $pasted );
		} else {
			$err = __( 'Choose a CSV file or paste rows.', 'anchor-autopilot' );
		}
		if ( '' !== $err ) {
			self::notice( 'error', esc_html( $err ) );
			self::back( 'aa-import' );
		}
		if ( ! empty( $_POST['replace'] ) ) {
			AA_DB::delete_all( $type );
		}
		$res = AA_CSV::import( $rows, $type, $dup );
		set_transient( 'aa_import_' . get_current_user_id(), $res, 600 );
		self::back( 'aa-import' );
	}

	public static function handle_export() {
		$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'keyword';
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'anchor-autopilot' ), 403 );
		}
		check_admin_referer( 'aa_export' );
		AA_CSV::export( $type );
	}

	public static function handle_sample() {
		$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'keyword';
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'anchor-autopilot' ), 403 );
		}
		check_admin_referer( 'aa_sample' );
		AA_CSV::sample( $type );
	}

	public static function handle_settings() {
		self::guard( 'aa_save_settings' );
		$new = AA_Settings::sanitize( wp_unslash( $_POST['aa'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		update_option( AA_Settings::OPTION, $new, false );
		AA_DB::bump();
		self::notice( 'success', esc_html__( 'Settings saved.', 'anchor-autopilot' ) );
		self::back( 'aa-settings' );
	}

	/* ------------------------------------------------------------------ */
	/* Per-page switch                                                     */
	/* ------------------------------------------------------------------ */

	public static function meta_box() {
		$types = (array) AA_Settings::get( 'post_types' );
		if ( ! $types ) {
			return;
		}
		add_meta_box( 'aa_meta', __( 'Anchor Autopilot', 'anchor-autopilot' ), array( __CLASS__, 'meta_box_html' ), $types, 'side', 'low', array( '__back_compat_meta_box' => true ) );
	}

	public static function meta_box_html( $post ) {
		wp_nonce_field( 'aa_meta_' . $post->ID, 'aa_meta_nonce' );
		$v = get_post_meta( $post->ID, '_aa_disable', true );
		echo '<label><input type="checkbox" name="aa_disable" value="1"' . checked( (bool) $v, true, false ) . '> ' . esc_html__( 'Do not add automatic links on this page', 'anchor-autopilot' ) . '</label>';
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['aa_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aa_meta_nonce'] ) ), 'aa_meta_' . $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! empty( $_POST['aa_disable'] ) ) {
			update_post_meta( $post_id, '_aa_disable', 1 );
		} else {
			delete_post_meta( $post_id, '_aa_disable' );
		}
	}
}

<?php
/**
 * Plugin Name:       Anchor Autopilot – Auto Internal Links
 * Plugin URI:        https://fahadbinzafar.com/
 * Description:       Automatically links your chosen phrases to your chosen URLs, inside body content only. Smart about overlapping phrases, never touches menus, footers, headings, alt text or hidden content. Add rules one by one or by CSV, site-wide or for one specific page.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Fahad
 * Author URI:        https://fahadbinzafar.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       anchor-autopilot
 * Domain Path:       /languages
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AA_VERSION', '1.1.0' );
define( 'AA_FILE', __FILE__ );
define( 'AA_PATH', plugin_dir_path( __FILE__ ) );
define( 'AA_URL', plugin_dir_url( __FILE__ ) );
define( 'AA_BASENAME', plugin_basename( __FILE__ ) );

require_once AA_PATH . 'includes/class-aa-url.php';
require_once AA_PATH . 'includes/class-aa-engine.php';
require_once AA_PATH . 'includes/class-aa-settings.php';
require_once AA_PATH . 'includes/class-aa-db.php';
require_once AA_PATH . 'includes/class-aa-frontend.php';

if ( is_admin() ) {
	require_once AA_PATH . 'includes/class-aa-csv.php';
	require_once AA_PATH . 'includes/class-aa-conflicts.php';
	require_once AA_PATH . 'includes/class-aa-report.php';
	require_once AA_PATH . 'includes/class-aa-rules-table.php';
	require_once AA_PATH . 'includes/class-aa-admin.php';
}

function aa_activate() {
	AA_DB::install();
	if ( false === get_option( AA_Settings::OPTION ) ) {
		add_option( AA_Settings::OPTION, AA_Settings::defaults(), '', false );
	}
}
register_activation_hook( __FILE__, 'aa_activate' );

function aa_boot() {
	load_plugin_textdomain( 'anchor-autopilot', false, dirname( AA_BASENAME ) . '/languages' );

	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$host = is_string( $host ) ? strtolower( $host ) : '';
	if ( 0 === strpos( $host, 'www.' ) ) {
		$host = substr( $host, 4 );
	}
	AA_Url::$home_host = $host;

	AA_DB::maybe_upgrade();
	AA_Frontend::init();
	if ( is_admin() ) {
		AA_Admin::init();
	}
}
add_action( 'plugins_loaded', 'aa_boot' );

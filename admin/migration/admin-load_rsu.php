<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$rs_pp_loader = new RS_PP_Loader();

class RS_PP_Loader {
	function __construct() {
		global $pagenow;
		
		add_action( 'admin_menu', array( &$this, 'rsu_admin_menu' ) );
		add_action( 'admin_head', array( &$this, 'rsu_admin_head' ) );
	}

	function rsu_admin_head() {
		$screen = get_current_screen();
        $page_slug = $screen->id;
		
		if ( false !== strpos( $page_slug, 'rs-migration-preview' ) ) {
			wp_enqueue_style( 'rs-migration-preview', plugins_url( '', SCOPER_FILE ) . '/admin/migration/css/rs-migration-preview.css', array(), SCOPER_VERSION );
		}
	}
	
	function rsu_admin_menu() {
		global $menu;
		
		if ((!is_super_admin() && ! current_user_can('activate_plugins')) || defined('PRESSPERMIT_VERSION')) {
			return;
		}

		$menu_pos = (!defined('SCOPER_DISABLE_MENU_TWEAK') && (isset($menu[70]) && $menu[70][2] == 'users.php')) ? 73 : null;

		$slug = add_menu_page( __( 'RS Migration Preview', 'scoper' ), __( 'Migrate to PublishPress', 'scoper' ), 'read', 'rs-migration-preview', array( &$this, 'do_rs_advisor' ), 'dashicons-external', $menu_pos );
		add_action( "load-$slug", array(&$this, 'nada'));

		$slug = add_submenu_page( 'rs-migration-preview', __( 'RS Migration Preview', 'scoper' ), __( 'RS Migration', 'scoper' ), 'read', 'rs-migration-preview', array( &$this, 'do_rs_advisor' ) );
		add_action( "load-$slug", array(&$this, 'nada'));

		wp_enqueue_style( 'rs-migration-menu', plugins_url( '', SCOPER_FILE ) . '/admin/migration/css/rs-migration-menu.css', array(), SCOPER_VERSION );
	}

	function nada() {
	}
	
	function do_rs_advisor() {
		$screen = get_current_screen();
        $page_slug = $screen->id;
		
		if ( false !== strpos( $page_slug, 'rs-migration-preview' ) ) {
			global $rsu_rs_import;
			require_once( dirname(__FILE__).'/rs-pp-analysis_rsu.php' );
			$rsu_rs_import = new RS_PP_Analysis();
			$rsu_rs_import->analyze();
		}
	}
}
?>
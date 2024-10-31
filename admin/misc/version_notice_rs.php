<?php
// note: This file was moved into admin/misc subdirectory to avoid detection as a plugin file by the WP plugin updater (due to Plugin Name search string)

if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die( 'This page cannot be called directly.' );

wp_enqueue_style( 'rs-migration-preview', plugins_url( '', SCOPER_FILE ) . '/admin/migration/css/rs-migration-preview.css', array(), SCOPER_VERSION );

function scoper_pp_msg( $on_options_page = false ) {
	$more_url = ( $on_options_page ) ? '#pp-more' : admin_url( 'admin.php?page=rs-options&show_pp=1', SCOPER_BASENAME );

	$slug = 'role-scoper-migration-advisor';
	$use_network_admin = is_multisite() && ( is_network_admin() || agp_is_plugin_network_active(SCOPER_FILE) ) && is_super_admin();
	$_url = "update.php?action=$slug&amp;plugin=$slug&amp;pp_install=1&amp;TB_iframe=true";
	//$_url = "update.php?action=$slug&plugin=$slug&pp_install=1&TB_iframe=true";
	$install_url = ( $use_network_admin ) ? network_admin_url($_url) : admin_url($_url);
	
	$rs_migration_url = admin_url("admin.php?page=rs-migration-preview");
	//$install_link =  "<span><a href='$url' class='thickbox' target='_blank'>" . __awp('install', 'pp') . '</a></span>';

    $msg = '<h2 class="rs-migration-caution">' . __( 'The Role Scoper plugin is obsolete and no longer supported.', 'scoper' ) . '</h2>';

    $msg .= '<div class="rs-mb">';

	$msg .= sprintf(
        __('Role Scoper development and support has ended.&nbsp;&nbsp;For onging WordPress compatibility and support, %1$srun the migration preview%2$s, install %3$s or buy %4$s.', 'scoper'),
        '<span><a href="' . $rs_migration_url . '" style="color:#655997;">',
        '</a></span>',
        /*'<span class="plugins update-message"><a href="' . awp_plugin_info_url('press-permit-core') . '" class="thickbox" title=" PublishPress Permissions">PublishPress&nbsp;Permissions</a></span>', */
        '<span class="plugins update-message"><a href="' . admin_url('plugin-install.php?tab=plugin-information&plugin=press-permit-core') . '" target="_blank" title=" PublishPress Permissions" style="color:#655997;">PublishPress&nbsp;Permissions</a></span>',
        "<a href='https://publishpress.com/presspermit/' target='_blank' style='color:#655997;'>PublishPress&nbsp;Permissions&nbsp;Pro</a>",
        '<span style="font-weight:bold;color:#c00">',
        '</span>'
    );

    $msg .= '</div><div class="rs-mb">';

    // Step 1
    $msg .= sprintf(
        __('%1$sStep 1%2$s %3$sRun the migration preview%4$s', 'scoper'),
        '<span class="rs-step-button rs-mr">',
        '</span><br class="rs-dm">',
        '<a href="' . $rs_migration_url . '" class="button button-primary rs-primary-button rs-mr-3x">',
        '</a>'
    );

    $msg .= '</div><div>';

    // Step 2
    $msg .= sprintf(
        __('%1$sStep 2%2$s %3$sInstall PublishPress Permissions%4$s %5$sor%6$s %7$sBuy PublishPress Permissions Pro%8$s', 'scoper'),
        '<span class="rs-step-button rs-mr">',
        '</span><br class="rs-dm">',
        '<a href="' . admin_url('plugin-install.php?tab=plugin-information&plugin=press-permit-core') . '" target="_blank" class="button rs-default-button">',
        '</a>',
        '<br class="rs-dm"><span class="rs-step-button rs-mlr-s">',
        '</span><br class="rs-dm">',
        '<a href="https://publishpress.com/presspermit/" target="_blank" class="button button-primary rs-pro-button">',
        '</a>'
    );

    $msg .= '</div>';

	return $msg;
}

?>
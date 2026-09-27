<?php
/**
 * Plugin Name: Lokahitam Video Library
 * Description: Turns a Google Sheet of YouTube videos into a browsable, searchable video library, with an admin panel to sync new videos on demand or on a schedule.
 * Version: 1.0.0
 * Author: Lokahitam
 * Text Domain: lokahitam-video-library
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'LVL_VERSION', '1.0.0' );
define( 'LVL_PLUGIN_FILE', __FILE__ );
define( 'LVL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LVL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once LVL_PLUGIN_DIR . 'includes/class-lvl-db.php';
require_once LVL_PLUGIN_DIR . 'includes/class-lvl-sync.php';
require_once LVL_PLUGIN_DIR . 'includes/class-lvl-cron.php';
require_once LVL_PLUGIN_DIR . 'includes/class-lvl-admin.php';
require_once LVL_PLUGIN_DIR . 'includes/class-lvl-frontend.php';

register_activation_hook( LVL_PLUGIN_FILE, array( 'LVL_DB', 'create_table' ) );
register_activation_hook( LVL_PLUGIN_FILE, array( 'LVL_Cron', 'activate' ) );
register_deactivation_hook( LVL_PLUGIN_FILE, array( 'LVL_Cron', 'deactivate' ) );

add_action(
	'plugins_loaded',
	function () {
		LVL_Admin::init();
		LVL_Frontend::init();
		LVL_Cron::init();
	}
);

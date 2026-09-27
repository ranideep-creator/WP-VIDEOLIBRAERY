<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The wp-admin side: the "Video Library" menu page where the sheet link
 * is configured and syncs are triggered, plus the AJAX handler behind
 * the Sync Now button.
 */
class LVL_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_lvl_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'wp_ajax_lvl_sync_now', array( __CLASS__, 'ajax_sync_now' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue( $hook ) {
		if ( 'toplevel_page_lokahitam-video-library' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'lvl-admin', LVL_PLUGIN_URL . 'assets/css/admin.css', array(), LVL_VERSION );
		wp_enqueue_script( 'lvl-admin', LVL_PLUGIN_URL . 'assets/js/admin.js', array(), LVL_VERSION, true );
		wp_localize_script(
			'lvl-admin',
			'lvlAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'lvl_sync_nonce' ),
			)
		);
	}

	public static function add_menu() {
		add_menu_page(
			'Video Library',
			'Video Library',
			'manage_options',
			'lokahitam-video-library',
			array( __CLASS__, 'render_page' ),
			'dashicons-video-alt3',
			26
		);
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do this.' );
		}
		check_admin_referer( 'lvl_save_settings' );

		$sheet_input = isset( $_POST['lvl_sheet_input'] ) ? sanitize_text_field( wp_unslash( $_POST['lvl_sheet_input'] ) ) : '';
		$gid_input   = isset( $_POST['lvl_gid_input'] ) ? sanitize_text_field( wp_unslash( $_POST['lvl_gid_input'] ) ) : '';
		$per_page    = isset( $_POST['lvl_per_page'] ) ? absint( $_POST['lvl_per_page'] ) : 24;
		$interval    = isset( $_POST['lvl_interval'] ) ? sanitize_text_field( wp_unslash( $_POST['lvl_interval'] ) ) : 'daily';

		$allowed_intervals = array( 'hourly', 'twicedaily', 'daily', 'lvl_weekly' );
		if ( ! in_array( $interval, $allowed_intervals, true ) ) {
			$interval = 'daily';
		}

		LVL_Sync::save_sheet_source( $sheet_input, $gid_input );
		update_option( 'lvl_per_page', max( 6, min( 60, $per_page ) ) );
		update_option( 'lvl_interval', $interval );

		LVL_Cron::reschedule( $interval );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'lokahitam-video-library',
					'lvl_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function ajax_sync_now() {
		check_ajax_referer( 'lvl_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'You do not have permission to do this.' ) );
		}

		$result = LVL_Sync::run_sync();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$sheet_id  = get_option( 'lvl_sheet_id', '' );
		$gid       = get_option( 'lvl_sheet_gid', '0' );
		$per_page  = get_option( 'lvl_per_page', 24 );
		$interval  = get_option( 'lvl_interval', 'daily' );
		$last_sync = get_option( 'lvl_last_sync', array() );
		$stats     = LVL_DB::get_stats();
		$site_url  = home_url( '/wp-cron.php?doing_wp_cron' );

		include LVL_PLUGIN_DIR . 'includes/views/admin-page.php';
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules the recurring sync via WP-Cron. Note that WP-Cron only fires
 * on a page visit, so the admin screen also documents pointing a real
 * cPanel Cron Job at wp-cron.php for reliable, on-time runs.
 */
class LVL_Cron {

	const HOOK = 'lvl_sync_cron_hook';

	public static function init() {
		add_action( self::HOOK, array( 'LVL_Sync', 'run_sync' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
	}

	public static function add_schedules( $schedules ) {
		if ( ! isset( $schedules['lvl_weekly'] ) ) {
			$schedules['lvl_weekly'] = array(
				'interval' => defined( 'WEEK_IN_SECONDS' ) ? WEEK_IN_SECONDS : 604800,
				'display'  => 'Once Weekly',
			);
		}
		return $schedules;
	}

	public static function activate() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			$interval = get_option( 'lvl_interval', 'daily' );
			wp_schedule_event( time() + 300, $interval, self::HOOK );
		}
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}

	/** Called when the admin changes the sync frequency in Settings. */
	public static function reschedule( $interval ) {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
		wp_schedule_event( time() + 300, $interval, self::HOOK );
	}
}

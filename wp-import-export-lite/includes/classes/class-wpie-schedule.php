<?php
/**
 * Cron Schedules Registration.
 *
 * Registers custom cron intervals used by WP Import Export background operations.
 *
 * @since      1.0.0
 * @package    wpie
 * @subpackage wpie\Core
 * @author     VJinfotech <support@vjinfotech.com>
 */

namespace wpie;

defined( 'ABSPATH' ) || exit;

/**
 * WPIE_Schedule Class
 *
 * Adds custom recurrence intervals to WordPress cron schedules.
 *
 * @since 1.0.0
 */
class WPIE_Schedule {

	/**
	 * Constructor.
	 *
	 * Registers the custom schedules with WordPress.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ), 99999, 1 );
	}

	/**
	 * Merges custom schedules with existing WordPress cron schedules.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  mixed $schedules Existing WordPress cron schedules array.
	 *
	 * @return array Merged cron schedules array.
	 */
	public static function cron_schedules( $schedules = array() ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}

		return array_merge( self::get_schedules(), $schedules );
	}

	/**
	 * Retrieves the custom cron schedules definition.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array Array of custom schedule definitions.
	 */
	public static function get_schedules() {
		return array(
			'wpie_10_min'     => array(
				'interval' => 10 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 10 Minutes', 'wp-import-export-lite' ),
			),
			'wpie_30_min'     => array(
				'interval' => 30 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 30 Minutes', 'wp-import-export-lite' ),
			),
			'wpie_hourly'     => array(
				'interval' => HOUR_IN_SECONDS,
				'display'  => __( 'Once Hourly', 'wp-import-export-lite' ),
			),
			'wpie_twicedaily' => array(
				'interval' => 12 * HOUR_IN_SECONDS,
				'display'  => __( 'Twice Daily', 'wp-import-export-lite' ),
			),
			'wpie_daily'      => array(
				'interval' => DAY_IN_SECONDS,
				'display'  => __( 'Once Daily', 'wp-import-export-lite' ),
			),
			'wpie_weekly'     => array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'wp-import-export-lite' ),
			),
			'wpie_monthly'    => array(
				'interval' => MONTH_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'wp-import-export-lite' ),
			),
		);
	}
}

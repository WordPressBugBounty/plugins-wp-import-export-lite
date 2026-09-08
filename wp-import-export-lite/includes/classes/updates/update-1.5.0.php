<?php
/**
 * Database update routine for version 1.5.0.
 *
 * @since      1.5.0
 * @package    wpie
 * @subpackage wpie\Core
 * @author     VJinfotech <support@vjinfotech.com>
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpie_1_5_0_update' ) ) {

	/**
	 * Adds `username` and `unique_id` columns to the template table if they don't already exist.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	function wpie_1_5_0_update() {
		// phpcs:disable PluginCheck.Security.DirectDB -- Internal database migration for plugin custom table schema updates.
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpie_template';

		// Verify table exists before altering schema.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $table_exists !== $table_name ) {
			return;
		}

		$existing_columns = $wpdb->get_col( "DESC `{$table_name}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $existing_columns ) ) {
			$existing_columns = array();
		}

		if ( ! in_array( 'username', $existing_columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table_name}` ADD `username` VARCHAR(60) NOT NULL AFTER `opration`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( ! in_array( 'unique_id', $existing_columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table_name}` ADD `unique_id` VARCHAR(100) NOT NULL AFTER `opration`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$wpdb->query( "UPDATE `{$table_name}` SET `unique_id` = MD5(`id`) WHERE `unique_id` = ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable PluginCheck.Security.DirectDB
	}
}

wpie_1_5_0_update();

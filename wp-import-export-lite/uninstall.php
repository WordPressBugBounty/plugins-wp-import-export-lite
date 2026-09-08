<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Cleans up scheduled cron jobs, database options, custom database tables,
 * user meta, and uploaded export/import files when the user has opted to delete data on uninstall.
 * Supports both single site and WordPress Multisite networks.
 *
 * @package    WP_Import_Export_Lite
 * @subpackage WP_Import_Export_Lite/uninstall
 * @since      1.0.11
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Always clear scheduled cron hooks.
wp_clear_scheduled_hook( 'wpie_cron_schedule_import' );
wp_clear_scheduled_hook( 'wpie_cron_schedule_export' );

/**
 * Recursively deletes a directory and its contents.
 *
 * @since 1.0.11
 * @param string $dir Target directory path.
 * @return void
 */
function wpie_uninstall_delete_dir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = scandir( $dir );
	if ( false === $items ) {
		return;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if ( is_dir( $path ) ) {
			wpie_uninstall_delete_dir( $path );
		} else {
			@wp_delete_file( $path );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Directory removal on uninstall.
	@rmdir( $dir );
}

/**
 * Deletes plugin options, tables, and cron events for a single blog.
 *
 * @since 1.0.11
 * @global wpdb $wpdb WordPress database abstraction object.
 * @return void
 */
function wpie_uninstall_single_blog() {
	global $wpdb;

	// Clear scheduled cron hooks for current blog.
	wp_clear_scheduled_hook( 'wpie_cron_schedule_import' );
	wp_clear_scheduled_hook( 'wpie_cron_schedule_export' );

	// Delete all plugin options and transients using prepared query.
	$like_pattern = $wpdb->esc_like( 'wpie_' ) . '%';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$like_pattern
		)
	);

	// Also remove transient timeouts if stored separately.
	$transient_timeout_like = $wpdb->esc_like( '_transient_timeout_wpie_' ) . '%';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$transient_timeout_like
		)
	);

	// Drop custom template table.
	$table_name = $wpdb->prefix . 'wpie_template';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB -- Drop custom table on uninstall.
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
}

/**
 * Executes plugin uninstall cleanup.
 *
 * @since 1.0.11
 * @return void
 */
function wpie_uninstall_plugin() {
	// Only proceed with database and file cleanup if user opted in.
	if ( 1 !== (int) get_option( 'wpie_delete_on_uninstall', 0 ) ) {
		return;
	}

	global $wpdb;

	// Handle Multisite network cleanup vs single-site cleanup.
	if ( is_multisite() ) {
		$wpie_blog_ids = array();

		if ( function_exists( 'get_sites' ) ) {
			$sites = get_sites( array( 'number' => 0 ) );
			foreach ( $sites as $site ) {
				$wpie_blog_ids[] = (int) $site->blog_id;
			}
		} else {
			$wpie_blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
		}

		$original_blog_id = get_current_blog_id();

		foreach ( $wpie_blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			wpie_uninstall_single_blog();
		}

		switch_to_blog( $original_blog_id );
	} else {
		wpie_uninstall_single_blog();
	}

	// Delete user meta across all users.
	delete_metadata( 'user', 0, 'dismissed_wpie_file_security_notice', '', true );

	// Clean up upload and export directory files.
	$upload_dir = wp_upload_dir();
	if ( ! empty( $upload_dir['basedir'] ) ) {
		$wpie_dir = $upload_dir['basedir'] . '/wp-import-export-lite';
		wpie_uninstall_delete_dir( $wpie_dir );
	}

	// Flush object cache to prevent stale option reads.
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}

wpie_uninstall_plugin();


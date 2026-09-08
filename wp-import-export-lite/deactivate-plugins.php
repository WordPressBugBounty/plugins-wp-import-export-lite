<?php
/**
 * Auto-deactivation of conflicting plugins.
 *
 * Automatically deactivates the Lite version if the Pro version of WP Import Export
 * or Woo Import Export is detected as active.
 *
 * @package    WP_Import_Export_Lite
 * @subpackage WP_Import_Export_Lite/bootstrap
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpie_auto_deactivate_pro_plugins' ) ) {
	/**
	 * Automatically deactivates the Lite version if Pro version is active.
	 *
	 * Checks for the presence of the Pro plugin and cleanly deactivates Lite,
	 * ensuring no function or class redeclaration conflicts occur.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	function wpie_auto_deactivate_pro_plugins() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$pro_plugins = array(
			'woo-import-export/woo-import-export.php',
			'vj-wp-import-export/vj-wp-import-export.php',
		);

		$is_pro_active = false;
		foreach ( $pro_plugins as $pro_plugin ) {
			if ( is_plugin_active( $pro_plugin ) ) {
				$is_pro_active = true;
				break;
			}
		}

		if ( $is_pro_active ) {
			$lite_plugin = 'wp-import-export-lite/wp-import-export-lite.php';
			if ( is_plugin_active( $lite_plugin ) ) {
				deactivate_plugins( array( $lite_plugin ) );
				set_transient( 'wpie_lite_deactivated_notice', 1, 60 );
			}
		}
	}
}

if ( ! function_exists( 'wpie_deactivated_pro_admin_notice' ) ) {
	/**
	 * Displays an admin notice informing the user that Lite was deactivated in favor of Pro.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	function wpie_deactivated_pro_admin_notice() {
		if ( get_transient( 'wpie_lite_deactivated_notice' ) ) {
			delete_transient( 'wpie_lite_deactivated_notice' );
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					echo esc_html__( 'WP Import Export Lite has been automatically deactivated because WP Import Export Pro is active.', 'wp-import-export-lite' );
					?>
				</p>
			</div>
			<?php
		}
	}
	add_action( 'admin_notices', 'wpie_deactivated_pro_admin_notice' );
}

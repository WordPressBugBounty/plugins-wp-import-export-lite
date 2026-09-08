<?php
/**
 * Functions and actions related to updates.
 *
 * @since      1.5.0
 * @package    wpie
 * @subpackage wpie\Core
 * @author     VJinfotech <support@vjinfotech.com>
 */

namespace wpie;

defined( 'ABSPATH' ) || exit;

/**
 * Updates class
 *
 * Handles version checking and automatic database migrations.
 *
 * @since 1.5.0
 */
class Updates {

	/**
	 * Updates that need to be run, mapped by version to filename.
	 *
	 * @since  1.5.0
	 * @access private
	 *
	 * @var    array
	 */
	private static $updates = array(
		'1.5.0' => 'update-1.5.0.php',
	);

	/**
	 * Class Constructor
	 *
	 * Registers update hooks on admin_init.
	 *
	 * @since  1.5.0
	 * @access public
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'do_updates' ) );
	}

	/**
	 * Check if any update is required and current user is authorized.
	 *
	 * @since  1.5.0
	 * @access public
	 *
	 * @return void
	 */
	public function do_updates() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$installed_version = get_option( 'wpie_plugin_version' );

		// Maybe it's the first install.
		if ( ! $installed_version ) {
			return;
		}

		if ( version_compare( (string) $installed_version, WPIE_PLUGIN_VERSION, '<' ) ) {
			$this->perform_updates();
		}
	}

	/**
	 * Perform plugin updates.
	 *
	 * Iterates over update scripts and applies pending migrations.
	 *
	 * @since  1.5.0
	 * @access private
	 *
	 * @return void
	 */
	private function perform_updates() {
		if ( get_transient( 'wpie_performing_updates' ) ) {
			return;
		}

		set_transient( 'wpie_performing_updates', 1, 5 * MINUTE_IN_SECONDS );

		$installed_version = get_option( 'wpie_plugin_version' );

		foreach ( self::$updates as $version => $path ) {
			$abs_path = wp_normalize_path( WPIE_CLASSES_DIR . '/updates/' . $path );

			if ( version_compare( (string) $installed_version, (string) $version, '<' ) && file_exists( $abs_path ) ) {
				require_once $abs_path;
			}
		}

		// Save install date if not set.
		if ( ! get_option( 'wpie_install_date' ) ) {
			update_option( 'wpie_install_date', time() );
		}

		update_option( 'wpie_plugin_version', WPIE_PLUGIN_VERSION );
		update_option( 'wpie_db_version', WPIE_DB_VERSION );

		delete_transient( 'wpie_performing_updates' );
	}
}

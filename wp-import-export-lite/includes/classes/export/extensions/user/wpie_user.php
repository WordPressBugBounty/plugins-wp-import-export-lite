<?php
/**
 * WPIE User Export Extension Bootstrap
 *
 * @package    WPIE
 * @subpackage WPIE/Export/Extensions/User
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPIE_User_Export_Extension
 *
 * Hooks into the export engine initialization for user and customer exports.
 */
class WPIE_User_Export_Extension {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wpie_export_engine_init', array( $this, 'wpie_export_engine_init' ), 10, 3 );
	}

	/**
	 * Override export engine class for user or customer export types.
	 *
	 * @param string $export_engine Default export engine class.
	 * @param string $export_type   Current export type.
	 * @param mixed  $template_data Template data.
	 * @return string
	 */
	public function wpie_export_engine_init( $export_engine = '', $export_type = '', $template_data = '' ) {

		if ( 'users' === $export_type || 'shop_customer' === $export_type ) {

			$file_name = WPIE_EXPORT_CLASSES_DIR . '/extensions/user/class-wpie-user.php';

			if ( file_exists( $file_name ) ) {
				require_once $file_name;
			}

			unset( $file_name );

			$export_engine = '\wpie\export\user\WPIE_User_Export';
		}

		unset( $template_data, $export_type );

		return $export_engine;
	}
}

new WPIE_User_Export_Extension();

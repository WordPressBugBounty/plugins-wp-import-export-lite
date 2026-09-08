<?php
/**
 * WPIE Background Export Extension Bootstrap
 *
 * @package    WPIE
 * @subpackage WPIE/Export/Extensions/BG
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPIE_BG_Extension
 *
 * Loads background export module.
 */
class WPIE_BG_Extension {

	/**
	 * Constructor.
	 */
	public function __construct() {

		$file_name = WPIE_EXPORT_CLASSES_DIR . '/extensions/bg/class-wpie-bg.php';

		if ( file_exists( $file_name ) ) {

			require_once $file_name;

			$bg_export = new \wpie\export\bg\WPIE_BG();

			$bg_export->init();

			unset( $bg_export );
		}
	}
}

new WPIE_BG_Extension();

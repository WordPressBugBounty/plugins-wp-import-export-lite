<?php
/**
 * Export AJAX Actions Handler.
 *
 * Registers and handles all AJAX actions related to the export lifecycle,
 * including templates, count, fields, preview, processing, and file generation.
 *
 * @package WP_Import_Export_Lite
 * @subpackage Export
 * @since 1.0.0
 */

namespace wpie\export\actions;

use wpie\Security;
use WpieApp\Core\Helpers\Param;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export.php' ) ) {
	require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export.php';
}
if ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-security.php' ) ) {
	require_once WPIE_CLASSES_DIR . '/class-wpie-security.php';
}

/**
 * Class WPIE_Export_Actions
 *
 * Dispatches export AJAX requests with capability and nonce verification.
 *
 * @since 1.0.0
 */
class WPIE_Export_Actions extends \wpie\export\WPIE_Export {

	/**
	 * Constructor: registers all AJAX hooks for export operations.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		add_action( 'wp_ajax_wpie_export_get_template_list', array( $this, 'wpie_export_get_template_list' ) );

		add_action( 'wp_ajax_wpie_export_get_settings_list', array( $this, 'wpie_export_get_settings_list' ) );

		add_action( 'wp_ajax_wpie_export_save_template', array( $this, 'wpie_export_save_template' ) );

		add_action( 'wp_ajax_wpie_export_get_template_data', array( $this, 'wpie_export_get_template_data' ) );

		add_action( 'wp_ajax_wpie_export_records_count', array( $this, 'wpie_export_records_count' ) );

		add_action( 'wp_ajax_wpie_export_field_list', array( $this, 'wpie_export_field_list' ) );

		add_action( 'wp_ajax_wpie_export_get_rule_list', array( $this, 'wpie_export_get_rule_list' ) );

		add_action( 'wp_ajax_wpie_export_create_data', array( $this, 'wpie_export_create_data' ) );

		add_action( 'wp_ajax_wpie_export_update_data', array( $this, 'wpie_export_update_data' ) );

		add_action( 'wp_ajax_wpie_export_prepare_file', array( $this, 'wpie_export_prepare_file' ) );

		add_action( 'wp_ajax_wpie_export_get_preview_data', array( $this, 'wpie_export_get_preview_data' ) );

		add_action( 'wp_ajax_wpie_export_update_status', array( $this, 'wpie_export_update_status' ) );
	}

	/**
	 * AJAX endpoint: Fetch list of saved export templates.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wpie_export_get_template_list() {

		Security::verify_request( 'wpie_new_export' );

		parent::get_template_list();
	}

	/**
	 * AJAX endpoint: Fetch list of previous export run settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wpie_export_get_settings_list() {

		Security::verify_request( 'wpie_new_export' );

		parent::get_export_settings_list();
	}

	/**
	 * AJAX endpoint: Calculate total exportable records matching filters.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wpie_export_records_count() {

		Security::verify_request( 'wpie_new_export' );

		parent::get_item_count();
	}

	/**
	 * AJAX endpoint: Retrieve available export fields for selected post/entity type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wpie_export_field_list() {

		Security::verify_request( 'wpie_new_export' );

		parent::get_field_list();
	}

	/**
	 * AJAX endpoint: Retrieve rule filtering operators.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wpie_export_get_rule_list() {

		Security::verify_request( 'wpie_new_export' );

		parent::get_export_rule();
	}

	/**
	 * AJAX endpoint: Save export template or update existing template.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wpie_export_save_template() {

		Security::verify_request( 'wpie_new_export' );

		parent::save_template_data();
	}

	/**
	 * AJAX endpoint: Retrieve saved template configuration data by ID.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wpie_export_get_template_data() {

		Security::verify_request( 'wpie_new_export' );

		parent::get_template();
	}

	/**
	 * AJAX endpoint: Initialize a new export template record in the database.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wpie_export_create_data() {

		Security::verify_request( 'wpie_new_export' );

		$is_package = Param::postSanitized( 'is_package', 'int', 0 ) === 1;

		if ( $is_package && ! class_exists( '\ZipArchive' ) ) {
			$error_data = array(
				'status'  => 'error',
				'message' => __( 'Please enable PHP ZIP extension', 'wp-import-export-lite' ),
			);

			wp_send_json( $error_data );
		}

		parent::init_new_export();
	}

	/**
	 * AJAX endpoint: Run one iteration batch of the export process.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wpie_export_update_data() {

		Security::verify_request( 'wpie_new_export' );

		parent::init_export_process();
	}

	/**
	 * AJAX endpoint: Convert the exported CSV into final file format (XML, JSON, Excel, ZIP).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wpie_export_prepare_file() {

		Security::verify_request( 'wpie_new_export' );

		parent::prepare_file();
	}

	/**
	 * AJAX endpoint: Retrieve live preview sample rows for the export configuration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wpie_export_get_preview_data() {

		Security::verify_request( 'wpie_new_export' );

		parent::get_preview();
	}

	/**
	 * AJAX endpoint: Update the export execution status (e.g. background or stopped).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wpie_export_update_status() {

		Security::verify_request( 'wpie_new_export' );

		parent::update_process_status();
	}

}

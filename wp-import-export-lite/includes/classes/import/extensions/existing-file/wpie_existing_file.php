<?php
/**
 * Existing File Upload AJAX Extension
 *
 * @package   wpie\import\upload\existingfile
 * @author    WP Import Export
 * @copyright 2026 WP Import Export
 * @license   GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

use WpieApp\Core\Helpers\Param;

if ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-security.php' ) ) {
	require_once( WPIE_CLASSES_DIR . '/class-wpie-security.php' );
}

/**
 * Class WPIE_Existing_File_Upload_Extension
 *
 * Registers the existing file section and handles server existing file imports via AJAX.
 *
 * @since 1.0.0
 */
class WPIE_Existing_File_Upload_Extension {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		add_filter( 'wpie_import_upload_sections', array( $this, 'get_existing_file_view' ), 10, 1 );

		add_action( 'wp_ajax_wpie_import_set_existing_file', array( $this, 'prepare_existing_file' ) );
	}

	/**
	 * Register existing file upload tab section view.
	 *
	 * @since 1.0.0
	 *
	 * @param array $wpie_sections Registered sections.
	 * @return array
	 */
	public function get_existing_file_view( $wpie_sections = array() ) {

		$wpie_sections[ "wpie_import_existing_file_upload" ] = array(
			"label" => __( "Use existing file", 'wp-import-export-lite' ),
			"icon"  => 'fas fa-paperclip',
			"view"  => WPIE_IMPORT_CLASSES_DIR . "/extensions/existing-file/wpie-existing-file-view.php",
		);

		return $wpie_sections;
	}

	/**
	 * Handle AJAX existing file preparation request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prepare_existing_file() {

		\wpie\Security::verify_request( 'wpie_new_import' );

		$require_file = WPIE_IMPORT_CLASSES_DIR . '/extensions/existing-file/class-existing-file.php';

		if ( file_exists( $require_file ) ) {
			require_once( $require_file );
		}

		$upload = new \wpie\import\upload\existingfile\WPIE_Existing_File();

		$fileName       = Param::requestSanitized( 'file_name', 'text', '' );
		$wpie_import_id = Param::requestSanitized( 'wpie_import_id', 'absint', 0 );

		$file = $upload->wpie_upload_file( $fileName, $wpie_import_id );

		unset( $fileName );

		$return_value = array( 'status' => 'error' );

		if ( is_wp_error( $file ) ) {
			$return_value[ 'message' ]       = $file->get_error_message();
			$return_value[ 'error_message' ] = $file->get_error_message();
			$return_value[ 'erorr_message' ] = $file->get_error_message();
		} elseif ( empty( $file ) ) {
			$fail_msg                        = __( 'Failed to upload files', 'wp-import-export-lite' );
			$return_value[ 'message' ]       = $fail_msg;
			$return_value[ 'error_message' ] = $fail_msg;
			$return_value[ 'erorr_message' ] = $fail_msg;
		} elseif ( $file === "processing" ) {
			$return_value[ 'status' ]  = 'success';
			$return_value[ 'message' ] = 'processing';
		} else {

			$return_value[ 'file_list' ] = ( isset( $file[ 'file_list' ] ) && is_array( $file[ 'file_list' ] ) ) ? $file[ 'file_list' ] : array();

			$return_value[ 'file_count' ] = count( $return_value[ 'file_list' ] );

			$return_value[ 'wpie_import_id' ] = isset( $file[ 'wpie_import_id' ] ) ? $file[ 'wpie_import_id' ] : 0;

			$return_value[ 'file_name' ] = isset( $file[ 'file_name' ] ) ? $file[ 'file_name' ] : "";

			$return_value[ 'file_size' ] = isset( $file[ 'file_size' ] ) ? $file[ 'file_size' ] : "";

			$return_value[ 'status' ] = 'success';
		}
		unset( $file );

		wp_send_json( $return_value );
	}

}

new WPIE_Existing_File_Upload_Extension();

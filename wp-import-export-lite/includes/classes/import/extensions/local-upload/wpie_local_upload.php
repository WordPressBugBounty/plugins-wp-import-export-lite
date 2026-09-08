<?php
/**
 * Local Upload AJAX Extension
 *
 * @package   wpie\import\upload\local
 * @author    WP Import Export
 * @copyright 2026 WP Import Export
 * @license   GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-security.php' ) ) {
	require_once( WPIE_CLASSES_DIR . '/class-wpie-security.php' );
}

/**
 * Class WPIE_Local_Upload_Extension
 *
 * Registers the Desktop Upload section and handles chunked local upload AJAX requests.
 *
 * @since 1.0.0
 */
class WPIE_Local_Upload_Extension {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		add_filter( 'wpie_import_upload_sections', array( $this, 'get_local_upload_view' ), 10, 1 );

		add_action( 'wp_ajax_wpie_import_local_upload_file', array( $this, 'upload_local_file' ) );
	}

	/**
	 * Register local upload tab section view.
	 *
	 * @since 1.0.0
	 *
	 * @param array $wpie_sections Registered sections.
	 * @return array
	 */
	public function get_local_upload_view( $wpie_sections = array() ) {

		$wpie_sections[ "wpie_import_local_upload" ] = array(
			"label" => __( "Upload from Desktop", 'wp-import-export-lite' ),
			"icon"  => 'fas fa-upload',
			"view"  => WPIE_IMPORT_CLASSES_DIR . "/extensions/local-upload/wpie-local-upload-view.php",
		);

		return $wpie_sections;
	}

	/**
	 * Handle AJAX local file upload.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function upload_local_file() {

		\wpie\Security::verify_request( 'wpie_new_import' );

		$fileName = WPIE_IMPORT_CLASSES_DIR . '/extensions/local-upload/class-wpie-local-upload.php';

		if ( file_exists( $fileName ) ) {
			require_once( $fileName );
		}
		$upload = new \wpie\import\upload\local\WPIE_Local_Upload();

		$file = $upload->upload_local_file();

		unset( $fileName, $upload );

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

			$return_value = array_merge( $return_value, $file );

			$return_value[ 'file_count' ] = ( isset( $file[ 'file_list' ] ) && is_array( $file[ 'file_list' ] ) ) ? count( $file[ 'file_list' ] ) : 0;

			$return_value[ 'status' ] = 'success';
		}

		unset( $file );

		wp_send_json( $return_value );
	}

}

new WPIE_Local_Upload_Extension();

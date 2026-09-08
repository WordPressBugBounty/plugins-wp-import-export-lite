<?php
/**
 * Remote File Downloader
 *
 * @package   wpie\import\Downloader
 * @author    WP Import Export
 * @copyright 2026 WP Import Export
 * @license   GPL-2.0+
 */

namespace wpie\import\Downloader;

defined( 'ABSPATH' ) || exit;

/**
 * Class Download
 *
 * Safely streams remote files to a temporary location using WordPress HTTP APIs.
 *
 * @since 1.0.0
 */
class Download {

	/**
	 * Remote download URL.
	 *
	 * @var string
	 */
	private $url = "";

	/**
	 * Verify SSL certificate.
	 *
	 * @var bool
	 */
	private $sslverify = true;

	/**
	 * Maximum HTTP redirects.
	 *
	 * @var int
	 */
	private $redirection = 5;

	/**
	 * Download request timeout in seconds.
	 *
	 * @var int
	 */
	private $timeout = 3000;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Download remote file to local temporary storage.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Target remote URL.
	 * @return string|\WP_Error Path to downloaded temporary file, or WP_Error on failure.
	 */
	public function download_file( $url = "" ) {

		if ( empty( $url ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'File Download Error : File URL is empty', 'wp-import-export-lite' ) );
		}

		$this->url = \wp_http_validate_url( $url );

		if ( false === $this->url ) {
			return new \WP_Error( 'wpie_import_error', __( 'File Download Error : File URL is not valid', 'wp-import-export-lite' ) );
		}

		$wp_file = $this->wp_download();

		return $wp_file;
	}

	/**
	 * Execute download using wp_safe_remote_get.
	 *
	 * @since 1.0.0
	 * @return string|\WP_Error
	 */
	private function wp_download() {

		$filename = time() . wp_rand() . ".tmp";

		$file = get_temp_dir() . $filename;

		$response = wp_safe_remote_get( $this->url, [
			'timeout'     => $this->timeout,
			'stream'      => true,
			'filename'    => $file,
			'sslverify'   => $this->sslverify,
			'redirection' => $this->redirection,
		] );

		if ( is_wp_error( $response ) ) {

			if ( file_exists( $file ) ) {
				@wp_delete_file( $file );
			}
			return $response;
		}

		if ( 200 != wp_remote_retrieve_response_code( $response ) ) {

			if ( file_exists( $file ) ) {
				@wp_delete_file( $file );
			}
			return new \WP_Error( 'http_404', trim( wp_remote_retrieve_response_message( $response ) ) );
		}

		$content_md5 = wp_remote_retrieve_header( $response, 'content-md5' );

		if ( $content_md5 ) {

			if ( ! function_exists( 'verify_file_md5' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
			}

			if ( function_exists( 'verify_file_md5' ) ) {
				$md5_check = verify_file_md5( $file, $content_md5 );
			} else {
				$md5_check = md5_file( $file ) === $content_md5 ? true : new \WP_Error( 'md5_mismatch', __( 'Checksum mismatch', 'wp-import-export-lite' ) );
			}

			if ( is_wp_error( $md5_check ) ) {

				if ( file_exists( $file ) ) {
					@wp_delete_file( $file );
				}
				return $md5_check;
			}

			unset( $md5_check );
		}

		return $file;
	}

}

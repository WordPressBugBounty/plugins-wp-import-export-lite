<?php
/**
 * Archive File Format Extraction Manager
 *
 * @package   wpie\import\FileFormat
 * @author    WP Import Export
 * @copyright 2026 WP Import Export
 * @license   GPL-2.0+
 */

namespace wpie\import\FileFormat;

defined( 'ABSPATH' ) || exit;

/**
 * Class Manager
 *
 * Handles decompression and extraction of archive file formats (.zip, .tar, .gz) with directory traversal protection.
 *
 * @since 1.0.0
 */
class Manager {

	/**
	 * Full path to archive file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Target extraction directory.
	 *
	 * @var string
	 */
	private $to;

	/**
	 * Extract archive file to target directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file Relative file path.
	 * @param string $to   Relative target path.
	 * @return mixed Extraction result or WP_Error.
	 */
	public function extract( $file = "", $to = "" ) {

		if ( empty( $file ) || empty( $to ) || strpos( $file, '..' ) !== false || strpos( $to, '..' ) !== false || strpos( $file, "\0" ) !== false || strpos( $to, "\0" ) !== false ) {
			return new \WP_Error( 'wpie_import_error', __( 'Invalid file path or path traversal detected', 'wp-import-export-lite' ) );
		}

		$file_full = WPIE_UPLOAD_IMPORT_DIR . "/" . ltrim( $file, './\\' );

		if ( ! is_readable( $file_full ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'Uploaded file is not readable', 'wp-import-export-lite' ) );
		}

		$this->file = $file_full;
		$this->to   = WPIE_UPLOAD_IMPORT_DIR . "/" . ltrim( $to, './\\' );

		if ( ! is_dir( $this->to ) ) {
			wp_mkdir_p( $this->to );
		}

		if ( preg_match( '%\W(gz)$%i', trim( $file_full ) ) ) {
			return $this->gz_extract();
		} elseif ( preg_match( '%\W(zip)$%i', trim( $file_full ) ) ) {
			return $this->zip_extract();
		} elseif ( preg_match( '%\W(tar)$%i', trim( $file_full ) ) ) {
			return $this->tar_extract();
		}

		return new \WP_Error( 'wpie_import_error', __( "can't parse uploaded file", 'wp-import-export-lite' ) );
	}

	/**
	 * Extract TAR archive using PharData.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	private function tar_extract() {

		if ( ! class_exists( '\PharData' ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'Class PharData Not Found', 'wp-import-export-lite' ) );
		}

		try {
			$phar = new \PharData( $this->file );
			$phar->extractTo( $this->to, null, true );
			unset( $phar );
			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'wpie_import_error', $e->getMessage() );
		}
	}

	/**
	 * Extract ZIP archive using WordPress filesystem unzip_file.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	private function zip_extract() {

		if ( function_exists( "\unzip_file" ) ) {
			return unzip_file( $this->file, $this->to );
		} elseif ( function_exists( "\wpie_unzip_file" ) ) {
			return wpie_unzip_file( $this->file, $this->to );
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}
		WP_Filesystem();

		return unzip_file( $this->file, $this->to );
	}

	/**
	 * Decompress GZ archive.
	 *
	 * @since 1.0.0
	 * @return string|\WP_Error Decompressed file path or WP_Error.
	 */
	private function gz_extract() {

		if ( ! function_exists( "\gzopen" ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'Function GZOPEN Not Exist', 'wp-import-export-lite' ) );
		}

		$filename = substr( \basename( $this->file ), 0, -3 );

		if ( ! preg_match( '%\W(xml|zip|csv|xls|xlsx|ods|txt|json|tar)$%i', trim( $filename ) ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'Uploaded file must be XML, CSV, ZIP, XLS, XLSX, ODS, TXT, JSON, TAR', 'wp-import-export-lite' ) );
		}

		$newpath = $this->to . DIRECTORY_SEPARATOR . $filename;

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct stream writes required for GZ decompression.
		$fp = @fopen( $newpath, 'wb' );

		if ( ! is_resource( $fp ) ) {
			return new \WP_Error( 'wpie_import_error', __( "Can't Create file", 'wp-import-export-lite' ) );
		}

		$gfp = @gzopen( $this->file, 'rb' );
		if ( ! $gfp ) {
			fclose( $fp );
			return new \WP_Error( 'wpie_import_error', __( "can't open GZ file", 'wp-import-export-lite' ) );
		}

		while ( ! gzeof( $gfp ) ) {
			fwrite( $fp, gzread( $gfp, 4096 ) );
		}

		gzclose( $gfp );
		fclose( $fp );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		unset( $gfp, $fp );

		if ( preg_match( '%\W(tar)$%i', trim( $filename ) ) ) {
			$this->file = $newpath;
			return $this->tar_extract();
		} elseif ( preg_match( '%\W(zip)$%i', trim( $filename ) ) ) {
			$this->file = $newpath;
			return $this->zip_extract();
		}

		return $newpath;
	}

}

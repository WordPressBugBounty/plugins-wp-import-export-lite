<?php
/**
 * Upload File Validation & Format Converter
 *
 * @package   wpie\import\upload\validate
 * @author    WP Import Export
 * @copyright 2026 WP Import Export
 * @license   GPL-2.0+
 */

namespace wpie\import\upload\validate;

use wpie\import\chunk\csv;
use wpie\lib\xml\array2xml;
use PhpOffice\PhpSpreadsheet\Reader;
use PhpOffice\PhpSpreadsheet\Writer;
use WP_Error;
use WpieApp\Core\Helpers\Param;

defined( 'ABSPATH' ) || exit;

/**
 * Class WPIE_Upload_Validate
 *
 * Validates uploaded files (CSV, Excel, JSON, XML, TXT) and converts them into normalized XML chunks.
 *
 * @since 1.0.0
 */
class WPIE_Upload_Validate {

	/**
	 * Output chunk filename prefix.
	 *
	 * @var string
	 */
	private $wpie_fileName = "wpie-import-data-";

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Parse and convert uploaded data file to intermediate XML format.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed       $template_data      Template data object or array.
	 * @param string      $wpie_csv_delimiter Field delimiter.
	 * @param int         $is_first_row_title 1 if header row present, 0 otherwise.
	 * @param string|bool $activeFile         Specific file identifier.
	 * @param int|bool    $wpie_import_id     Import job ID.
	 * @param string|bool $activeSheet        Active spreadsheet worksheet name.
	 * @param string|bool $fileFormat         Target text file format.
	 * @return mixed Converted file data, true on XML success, or WP_Error.
	 */
	public function wpie_parse_upload_data( $template_data = null, $wpie_csv_delimiter = ",", $is_first_row_title = 1, $activeFile = false, $wpie_import_id = false, $activeSheet = false, $fileFormat = false ) {

		if ( empty( $template_data ) ) {
			return false;
		}

		global $wpdb;

		if ( is_array( $template_data ) ) {
			$template_options = $template_data;
		} else {
			$template_options = isset( $template_data->options ) ? maybe_unserialize( $template_data->options ) : array();
		}

		$importFile = isset( $template_options[ 'importFile' ] ) ? $template_options[ 'importFile' ] : array();

		if ( $activeFile === false ) {
			$activeFile = Param::getSanitized( 'activeFile', 'text', '' );
		}

		if ( $activeSheet === false ) {
			$activeSheet = Param::getSanitized( 'activeSheet', 'text', '' );
		}

		if ( $wpie_import_id === false ) {
			$wpie_import_id = Param::getSanitized( 'wpie_import_id', 'int', 0 );
		}

		$is_new_req = Param::getSanitized( 'is_new_req', 'int', 0 );

		$fileData = isset( $importFile[ $activeFile ] ) ? $importFile[ $activeFile ] : array();

		$file_path = isset( $fileData[ 'fileDir' ] ) ? wpie_sanitize_field( $fileData[ 'fileDir' ] ) : "";

		$file_name = isset( $fileData[ 'fileName' ] ) ? wpie_sanitize_field( $fileData[ 'fileName' ] ) : "";

		$baseDir = isset( $fileData[ 'baseDir' ] ) ? wpie_sanitize_field( $fileData[ 'baseDir' ] ) : "";

		$template_options[ 'activeFile' ] = $activeFile;

		$template_options[ 'activeSheet' ] = $activeSheet;

		if ( is_dir( WPIE_UPLOAD_IMPORT_DIR . "/" . $baseDir . "/parse/" ) ) {
			$this->wpie_remove_old_files( WPIE_UPLOAD_IMPORT_DIR . "/" . $baseDir . "/parse/" );
		}

		$wpdb->update( $wpdb->prefix . "wpie_template", array( "options" => maybe_serialize( $template_options ) ), array( 'id' => $wpie_import_id ) );

		$file = WPIE_UPLOAD_IMPORT_DIR . "/" . $file_path . "/" . $file_name;

		if ( ! file_exists( $file ) ) {

			unset( $template_options, $importFile, $activeFile, $file, $wpie_import_id, $fileData, $file_path, $file_name, $baseDir );

			return new \WP_Error( 'wpie_import_error', __( 'File not found', 'wp-import-export-lite' ) );
		} elseif ( filesize( $file ) === 0 ) {

			unset( $template_options, $importFile, $activeFile, $file, $wpie_import_id, $fileData, $file_path, $file_name, $baseDir );

			return new \WP_Error( 'wpie_import_error', __( 'File is Empty. Please Choose another file', 'wp-import-export-lite' ) );
		}

		if ( preg_match( '%\W(txt)$%i', trim( $file_name ) ) ) {

			if ( $fileFormat === false || ! in_array( $fileFormat, [ 'csv', 'json', 'xml' ], true ) ) {
				$active_format = Param::getSanitized( 'activeFormat', 'key', 'csv' );
				$fileFormat = in_array( $active_format, array( 'csv', 'json', 'xml' ), true ) ? $active_format : 'csv';
			}

			$validate = \wp_check_filetype( $file );

			if ( $validate[ 'type' ] !== 'text/plain' ) {
				wp_send_json( [ 'status' => 'error', 'message' => __( 'File type is not allowed', 'wp-import-export-lite' ) ] );
			}

			$newFileName = pathinfo( $file_name, PATHINFO_FILENAME ) . "." . $fileFormat;

			$newFileName = \wp_unique_filename( WPIE_UPLOAD_IMPORT_DIR . "/" . $file_path, $newFileName );

			$dest = WPIE_UPLOAD_IMPORT_DIR . "/" . $file_path . "/" . $newFileName;

			if ( copy( $file, $dest ) === false ) {
				return new \WP_Error( 'wpie_import_error', __( 'Fail to copy file', 'wp-import-export-lite' ) );
			}

			$file_name = $newFileName;

			$file = $dest;
		}

		if ( preg_match( '%\W(xls|xlsx|ods)$%i', trim( $file_name ) ) ) {

			unset( $template_options, $importFile, $activeFile, $file, $wpie_import_id, $fileData );

			return $this->wpie_convert_excel_2_csv( $file_path, $file_name, $baseDir, $is_first_row_title, $activeSheet );
		} elseif ( preg_match( '%\W(csv)$%i', trim( $file_name ) ) ) {

			unset( $template_options, $importFile, $activeFile, $file, $wpie_import_id, $fileData );

			return $this->wpie_convert_csv_2_xml( $file_path, $file_name, $baseDir, $is_first_row_title, $wpie_csv_delimiter, $is_new_req );
		} elseif ( preg_match( '%\W(txt|json)$%i', trim( $file_name ) ) ) {

			unset( $template_options, $importFile, $activeFile, $file, $wpie_import_id, $fileData );

			return $this->wpie_convert_json_2_xml( $file_path, $file_name, $baseDir );
		} elseif ( preg_match( '%\W(xml)$%i', trim( $file_name ) ) ) {

			$parse_dir = WPIE_UPLOAD_IMPORT_DIR . "/" . $baseDir . "/parse";
			if ( ! is_dir( $parse_dir ) ) {
				wp_mkdir_p( $parse_dir );
			}

			$xmlFile = $parse_dir . "/" . $this->wpie_fileName . "1.xml";

			if ( ! $this->validateFileData( $file, 'xml' ) ) {

				$this->createEmptyFile( $xmlFile );
			} else {
				copy( $file, $xmlFile );
			}

			unset( $template_options, $importFile, $activeFile, $file, $wpie_import_id, $fileData, $file_path, $file_name, $baseDir, $parse_dir );

			return true;
		}

		unset( $template_options, $importFile, $activeFile, $file, $wpie_import_id, $fileData, $file_path, $file_name, $baseDir );

		return new \WP_Error( 'wpie_import_error', __( 'Invalid File to parse. Please Choose other FIle', 'wp-import-export-lite' ) );
	}

	/**
	 * Convert Excel spreadsheet (.xls, .xlsx, .ods) into CSV format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $fileDir            File directory.
	 * @param string $file_name          Spreadsheet filename.
	 * @param string $baseDir            Base directory of import job.
	 * @param int    $is_first_row_title Whether first row has headers.
	 * @param string $activeSheet        Name of active worksheet.
	 * @return mixed Converted CSV chunk data or WP_Error.
	 */
	private function wpie_convert_excel_2_csv( $fileDir = "", $file_name = "", $baseDir = "", $is_first_row_title = 1, $activeSheet = "" ) {

		$file = WPIE_UPLOAD_IMPORT_DIR . "/" . $fileDir . "/" . $file_name;

		if ( ! file_exists( $file ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'File not found', 'wp-import-export-lite' ) );
		}

		$newFileName = wp_unique_filename( WPIE_UPLOAD_IMPORT_DIR . "/" . $fileDir, preg_replace( '%\W(xls|xlsx|ods)$%i', ".csv", $file_name ) );

		wpie_load_vendor_autoloader();

		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $file );

		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv( $spreadsheet );

		if ( ( ! empty( $activeSheet ) ) && $spreadsheet->getSheetByName( $activeSheet ) ) {

			$spreadsheet->setActiveSheetIndexByName( $activeSheet );

			$sheetIndex = $spreadsheet->getActiveSheetIndex();

			$writer->setSheetIndex( $sheetIndex );
		}

		$writer->save( WPIE_UPLOAD_IMPORT_DIR . "/" . $fileDir . "/" . preg_replace( '%\W(xls|xlsx|ods)$%i', ".csv", $newFileName ) );

		$spreadsheet->disconnectWorksheets();

		$return_data = $this->wpie_convert_csv_2_xml( $fileDir, $newFileName, $baseDir, $is_first_row_title );

		unset( $file, $newFileName, $spreadsheet, $writer );

		return $return_data;
	}

	/**
	 * Convert CSV file into XML format via CSV chunker.
	 *
	 * @since 1.0.0
	 *
	 * @param string $fileDir            File directory.
	 * @param string $file_name          CSV filename.
	 * @param string $baseDir            Base directory.
	 * @param int    $is_first_row_title Header row indicator.
	 * @param string $wpie_csv_delimiter Delimiter character.
	 * @param int    $is_new_req         Initial parse request flag.
	 * @return mixed XML chunk data or WP_Error.
	 */
	private function wpie_convert_csv_2_xml( $fileDir = "", $file_name = "", $baseDir = "", $is_first_row_title = 1, $wpie_csv_delimiter = ",", $is_new_req = 0 ) {

		if ( file_exists( WPIE_IMPORT_CLASSES_DIR . '/class-wpie-csv-chunk.php' ) ) {
			require_once( WPIE_IMPORT_CLASSES_DIR . '/class-wpie-csv-chunk.php' );
		}

		$csv_chunk = new \wpie\import\chunk\csv\WPIE_CSV_Chunk();

		$return_data = $csv_chunk->process_csv( $fileDir, $file_name, $baseDir, $wpie_csv_delimiter, $this->wpie_fileName, $is_new_req, $is_first_row_title );

		unset( $csv_chunk );

		return $return_data;
	}

	/**
	 * Convert JSON file into XML structure.
	 *
	 * @since 1.0.0
	 *
	 * @param string $fileDir   File directory.
	 * @param string $file_name JSON filename.
	 * @param string $baseDir   Base directory.
	 * @return string Path to generated XML file.
	 */
	private function wpie_convert_json_2_xml( $fileDir = "", $file_name = "", $baseDir = "" ) {

		$xmlFileName = $this->wpie_fileName . '1.xml';

		$xmlFilePath = WPIE_UPLOAD_IMPORT_DIR . "/" . $baseDir . "/parse";
		if ( ! is_dir( $xmlFilePath ) ) {
			wp_mkdir_p( $xmlFilePath );
		}

		if ( file_exists( WPIE_LIBRARIES_DIR . '/xml/class-wpie-array2xml.php' ) ) {
			require_once( WPIE_LIBRARIES_DIR . '/xml/class-wpie-array2xml.php' );
		}
		$xmlFile = $xmlFilePath . "/" . $xmlFileName;

		$file = WPIE_UPLOAD_IMPORT_DIR . "/" . $fileDir . "/" . $file_name;

		if ( ! $this->validateFileData( $file, 'json' ) ) {

			$this->createEmptyFile( $xmlFile );

			return $xmlFile;
		}

		$json = @file_get_contents( $file );

		$file_data = json_decode( (string) $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {

			unset( $file, $json, $file_data );

			$this->createEmptyFile( $xmlFile );

			return $xmlFile;
		}

		$converter = new \wpie\lib\xml\array2xml\ArrayToXml();

		$converter->create_root( "wpiedata" );

		$converter->convertElement( $converter->root, $file_data, 0 );

		$converter->saveFile( $xmlFile );

		unset( $file, $json, $file_data, $converter );

		return $xmlFile;
	}

	/**
	 * Recursively remove old files and subdirectories with strict directory traversal protection.
	 *
	 * @since 1.0.0
	 *
	 * @param string $targetDir Path to directory to clear.
	 * @return void
	 */
	private function wpie_remove_old_files( $targetDir = "" ) {

		if ( empty( $targetDir ) || ! is_string( $targetDir ) || strpos( $targetDir, '..' ) !== false || strpos( $targetDir, "\0" ) !== false ) {
			return;
		}

		$real_base = realpath( WPIE_UPLOAD_IMPORT_DIR );
		$real_target = realpath( $targetDir );

		if ( $real_base === false || $real_target === false || strpos( $real_target, $real_base ) !== 0 ) {
			return;
		}

		if ( ! is_dir( $real_target ) ) {
			return;
		}

		$cdir = scandir( $real_target );

		if ( is_array( $cdir ) && ! empty( $cdir ) ) {
			foreach ( $cdir as $value ) {
				if ( ! in_array( $value, array( ".", ".." ) ) ) {
					$item_path = $real_target . '/' . $value;
					if ( is_dir( $item_path ) ) {
						$this->wpie_remove_old_files( $item_path );
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Cleanup old temp directories.
						@rmdir( $item_path );
					} else {
						@wp_delete_file( $item_path );
					}
				}
			}
		}
		unset( $cdir );
	}

	/**
	 * Validate file initial bytes to ensure expected format (JSON vs XML).
	 *
	 * @since 1.0.0
	 *
	 * @param string $file   File path.
	 * @param string $format Expected format ('xml' or 'json').
	 * @return bool True if initial bytes match format signature.
	 */
	private function validateFileData( $file, $format = "xml" ) {

		if ( empty( $file ) || ! file_exists( $file ) || ! is_readable( $file ) || filesize( $file ) === 0 ) {
			return false;
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct stream inspection for file header verification.
		$fp = @fopen( $file, 'r' );

		if ( ! is_resource( $fp ) ) {
			return false;
		}

		$data = fread( $fp, 50 );

		fclose( $fp );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$data = preg_replace( '/[^[:print:]]/', '', (string) $data );

		$fileFirstChar = mb_substr( trim( $data ), 0, 1 );

		if ( $format === "json" ) {
			if ( $fileFirstChar === '[' || $fileFirstChar === '{' ) {
				return true;
			}
		} else {
			if ( $fileFirstChar === '<' || mb_strpos( $data, "<?xml" ) !== false ) {
				return true;
			}
		}

		unset( $data, $fp );

		return false;
	}

	/**
	 * Create or truncate empty file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file Path to file.
	 * @return bool True on success, false on failure.
	 */
	private function createEmptyFile( $file ) {

		if ( empty( $file ) ) {
			return false;
		}

		$dir = dirname( $file );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Create empty file.
		$fp = @fopen( $file, "w" );

		if ( is_resource( $fp ) ) {

			if ( file_exists( $file ) && filesize( $file ) > 0 ) {
				ftruncate( $fp, 0 );
			}

			fclose( $fp );

			return true;
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return false;
	}

	/**
	 * Destructor.
	 *
	 * @since 1.0.0
	 */
	public function __destruct() {
		foreach ( $this as $key => $value ) {
			unset( $this->$key );
		}
	}
}

<?php
/**
 * CSV to XML Chunker
 *
 * @package   wpie\import\chunk\csv
 * @author    WP Import Export
 * @copyright 2026 WP Import Export
 * @license   GPL-2.0+
 */

namespace wpie\import\chunk\csv;

use WP_Error;
use wpie\lib\xml\array2xml;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_IMPORT_CLASSES_DIR . '/class-wpie-chunk.php' ) ) {
	require_once( WPIE_IMPORT_CLASSES_DIR . '/class-wpie-chunk.php' );
}

/**
 * Class WPIE_CSV_Chunk
 *
 * Converts CSV files into intermediate XML structures for uniform import processing.
 *
 * @since 1.0.0
 */
class WPIE_CSV_Chunk extends \wpie\import\chunk\WPIE_Chunk {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Process CSV file and convert records to XML chunk format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $fileDir            Relative directory of CSV file.
	 * @param string $file_name          CSV filename.
	 * @param string $baseDir            Base directory of import job.
	 * @param string $wpie_csv_delimiter Delimiter character.
	 * @param string $wpie_xml_fileName  Output XML filename prefix.
	 * @param int    $is_new_req         Whether this is an initial parse request.
	 * @param int    $is_first_row_title Whether the first row contains column headers.
	 * @return array|\WP_Error Array with detected delimiter or WP_Error on failure.
	 */
	public function process_csv( $fileDir = "", $file_name = "", $baseDir = "", $wpie_csv_delimiter = ",", $wpie_xml_fileName = "", $is_new_req = 0, $is_first_row_title = 1 ) {

		$file = WPIE_UPLOAD_IMPORT_DIR . "/" . $fileDir . "/" . $file_name;

		$newFileDir = WPIE_UPLOAD_IMPORT_DIR . "/" . $baseDir . "/parse";

		$is_first_row_title = intval( $is_first_row_title ) > 0 ? 1 : 0;

		if ( empty( $file ) || is_readable( $file ) === false ) {
			return new \WP_Error( 'wpie_import_error', __( 'File is not Readable', 'wp-import-export-lite' ) );
		}

		if ( ! is_dir( $newFileDir ) ) {
			wp_mkdir_p( $newFileDir );
		}

		if ( intval( $is_new_req ) === 1 || empty( $wpie_csv_delimiter ) ) {
			$detected_del = $this->analyse_file( $file );
			$wpie_csv_delimiter = is_wp_error( $detected_del ) ? "," : $detected_del;
		} else {
			$wpie_csv_delimiter = $this->detect_delimiter( $wpie_csv_delimiter );
		}

		// Delimiter must be strictly 1 single-byte character in PHP 8.0+
		if ( ! is_string( $wpie_csv_delimiter ) || strlen( $wpie_csv_delimiter ) < 1 ) {
			$wpie_csv_delimiter = ',';
		} else {
			$wpie_csv_delimiter = substr( $wpie_csv_delimiter, 0, 1 );
		}

		$enclosure = $this->detect_enclosure( $file, $wpie_csv_delimiter );
		if ( is_wp_error( $enclosure ) || ! is_string( $enclosure ) || strlen( $enclosure ) < 1 ) {
			$enclosure = '"';
		} else {
			$enclosure = substr( $enclosure, 0, 1 );
		}

		if ( file_exists( WPIE_LIBRARIES_DIR . '/xml/class-wpie-array2xml.php' ) ) {
			require_once( WPIE_LIBRARIES_DIR . '/xml/class-wpie-array2xml.php' );
		}

		$converter = new \wpie\lib\xml\array2xml\ArrayToXml();

		$converter->create_root( "wpiedata" );

		$headers = [];

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct stream operations required for CSV parsing and chunk conversion.
		$wfp = @fopen( $file, "rb" );
		if ( ! is_resource( $wfp ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'Failed to open file for reading', 'wp-import-export-lite' ) );
		}

		while ( ( $keys = fgetcsv( $wfp, 0, $wpie_csv_delimiter, $enclosure ) ) !== false ) {

			if ( $this->is_empty_array( $keys ) ) {
				continue;
			}
			if ( empty( $headers ) ) {

				$keys[ 0 ] = isset( $keys[ 0 ] ) ? $this->remove_utf8_bom( $keys[ 0 ] ) : "";

				if ( $is_first_row_title === 1 ) {
					foreach ( $keys as $key => $value ) {

						$value = trim( strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) $value ) ) );

						if ( preg_match( '/^[0-9]{1}/', $value ) ) {
							$value = 'el_' . trim( strtolower( $value ) );
						}

						$value = ( ! empty( $value ) ) ? $value : 'undefined' . $key;

						if ( isset( $headers[ $key ] ) ) {
							$key = $this->unique_array_key_name( $key, $headers );
						}

						$headers[ $key ] = $this->unescape_data( $value );
					}

					continue;
				} else {
					$fieldkey_count = 1;

					foreach ( $keys as $key => $value ) {
						$value = "field_" . $fieldkey_count;

						if ( isset( $headers[ $key ] ) ) {
							$key = $this->unique_array_key_name( $key, $headers );
						}

						$headers[ $key ] = $this->unescape_data( $value );
						$fieldkey_count++;
					}
				}
			}

			$fileData = array();

			foreach ( $keys as $key => $value ) {

				$header = isset( $headers[ $key ] ) ? $headers[ $key ] : "";

				if ( ! empty( $header ) ) {

					if ( isset( $fileData[ $header ] ) ) {
						$header = $this->unique_array_key_name( $header, $fileData );
					}

					$fileData[ $header ] = $this->unescape_data( $value );
				}

				unset( $header );
			}

			$converter->addNode( $converter->root, "item", $fileData, 0 );

			unset( $fileData );
		}

		fclose( $wfp );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$converter->saveFile( $newFileDir . '/' . $wpie_xml_fileName . '1.xml' );

		unset( $file, $newFileDir, $converter, $headers );

		return [ 'delimiter' => $wpie_csv_delimiter ];
	}

	/**
	 * Escape a string to prevent CSV formula injection (DDE/Excel macro injection).
	 *
	 * @since 3.8.1
	 *
	 * @param string $data CSV field to unescape.
	 * @return string
	 */
	private function unescape_data( $data = "" ) {

		if ( empty( $data ) || ! is_string( $data ) ) {
			return $data;
		}

		$active_content_triggers = [ "'=", "'+", "'-", "'@" ];

		if ( in_array( mb_substr( trim( $data ), 0, 2 ), $active_content_triggers, true ) ) {
			$data = mb_substr( $data, 1 );
		}
		return $data;
	}

	/**
	 * Remove UTF-8 BOM signature.
	 *
	 * @since 1.0.0
	 *
	 * @param string $string String to handle.
	 * @return string
	 */
	protected function remove_utf8_bom( $string ) {

		if ( empty( $string ) || ! is_string( $string ) ) {
			return $string;
		}

		if ( 'efbbbf' === substr( bin2hex( $string ), 0, 6 ) ) {
			$string = substr( $string, 3 );
		}

		return $string;
	}

	/**
	 * Auto Detect and Correct Delimiter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $delimiter String to verify.
	 * @return string 1-character delimiter.
	 */
	protected function detect_delimiter( $delimiter = "," ) {

		if ( empty( $delimiter ) ) {
			return ",";
		}

		if ( in_array( $delimiter, [ 'comma', ',' ] ) || strpos( $delimiter, "," ) !== false ) {
			$delimiter = ',';
		} elseif ( in_array( $delimiter, [ 'semicolon', ';' ] ) || strpos( $delimiter, ";" ) !== false ) {
			$delimiter = ';';
		} elseif ( in_array( $delimiter, [ 'pipe', '|' ] ) || strpos( $delimiter, "|" ) !== false ) {
			$delimiter = '|';
		} elseif ( strpos( $delimiter, "t" ) !== false ) {
			$delimiter = "\t";
		} else {
			$delimiter = substr( $delimiter, 0, 1 );
		}

		return $delimiter;
	}

	/**
	 * Analyse file to automatically detect column delimiter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file CSV file path.
	 * @return string|\WP_Error Detected single-byte delimiter or WP_Error.
	 */
	private function analyse_file( $file = "" ) {

		if ( empty( $file ) || is_readable( $file ) === false ) {
			return new \WP_Error( 'wpie_import_error', __( 'File is not Readable', 'wp-import-export-lite' ) );
		}

		// read first line
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct stream operations required for CSV delimiter detection.
		$fh = @fopen( $file, 'r' );
		if ( ! is_resource( $fh ) ) {
			return ",";
		}
		$contents = fgets( $fh );
		fclose( $fh );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( $contents === false || $contents === "" ) {
			return ",";
		}

		// specify allowed field delimiters
		$delimiters = [
			'comma'      => ',',
			'semicolon'  => ';',
			'pipe'       => '|',
			'tabulation' => "\t"
		];

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Backwards compatibility hook.
		$delimiters = apply_filters( 'wp_import_export_specified_delimiters', $delimiters );

		$delim = ",";
		$count = 0;

		// loop and count each delimiter instance
		if ( ! empty( $delimiters ) && is_array( $delimiters ) ) {

			foreach ( $delimiters as $delimiter_key => $delimiter ) {

				$single_del = substr( (string) $delimiter, 0, 1 );
				if ( strlen( $single_del ) !== 1 ) {
					continue;
				}

				$parsed = str_getcsv( $contents, $single_del );
				$total  = is_array( $parsed ) ? count( $parsed ) : 0;

				if ( $total > $count ) {
					$delim = $single_del;
					$count = $total;
				}
			}
		}

		return $delim;
	}

	/**
	 * Detect CSV enclosure character ('"' vs "'").
	 *
	 * @since 1.0.0
	 *
	 * @param string $file      File path.
	 * @param string $delimiter Field delimiter.
	 * @return string|\WP_Error Enclosure character or WP_Error.
	 */
	private function detect_enclosure( $file = "", $delimiter = ',' ) {

		if ( empty( $file ) || is_readable( $file ) === false ) {
			return new \WP_Error( 'wpie_import_error', __( 'File is not Readable', 'wp-import-export-lite' ) );
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct stream operations required for CSV enclosure detection.
		$fp = @fopen( $file, "r" );
		if ( ! is_resource( $fp ) ) {
			return '"';
		}

		$enclosure = '"';

		$count = 0;

		$data_size = 0;

		$single_del = substr( (string) $delimiter, 0, 1 );
		if ( strlen( $single_del ) !== 1 ) {
			$single_del = ',';
		}

		while ( ( $keys = fgetcsv( $fp, 0, $single_del, $enclosure ) ) !== false ) {

			if ( $this->is_empty_array( $keys ) ) {
				continue;
			}
			$data_size = count( $keys );

			$keys[ 0 ] = isset( $keys[ 0 ] ) ? $this->remove_utf8_bom( $keys[ 0 ] ) : "";

			foreach ( $keys as $key => $value ) {

				if ( ! empty( $value ) && is_string( $value ) && substr( $value, 0, 1 ) === "'" && substr( $value, -1 ) === "'" ) {
					$count++;
					continue;
				}
				break;
			}

			break;
		}
		fclose( $fp );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( $data_size > 0 && $data_size === $count ) {
			$enclosure = "'";
		}

		return $enclosure;
	}

	/**
	 * Check if array is empty or contains only whitespace.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $var Array to check.
	 * @return bool
	 */
	private function is_empty_array( $var = null ) {

		if ( ! is_array( $var ) || count( $var ) === 0 ) {
			return true;
		}
		return strlen( trim( implode( "", $var ) ) ) === 0;
	}

	/**
	 * Ensure array key is unique by appending counter if necessary.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   Original key.
	 * @param array  $array Target array.
	 * @return string Unique key.
	 */
	private function unique_array_key_name( $key = "", $array = array() ) {

		$count = 1;

		$new_key = $key;

		while ( isset( $array[ $key ] ) ) {

			$key = $new_key . "_" . $count;
			$count++;
		}

		unset( $count, $new_key );

		return $key;
	}

}

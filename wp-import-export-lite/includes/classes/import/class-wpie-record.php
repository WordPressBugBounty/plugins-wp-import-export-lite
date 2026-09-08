<?php
/**
 * XML Record Extractor
 *
 * @package   wpie\import\record
 * @author    WP Import Export
 * @copyright 2026 WP Import Export
 * @license   GPL-2.0+
 */

namespace wpie\import\record;

use wpie\lib\xml\xml2array;
use XMLReader;

defined( 'ABSPATH' ) || exit;

/**
 * Class WPIE_Record
 *
 * Extracts and queries record sets and elements from XML files.
 *
 * @since 1.0.0
 */
class WPIE_Record {

	/**
	 * File prefix.
	 *
	 * @var string
	 */
	private $wpie_fileName = "wpie-import-data-";

	/**
	 * Record count.
	 *
	 * @var int
	 */
	public $record_length = 0;

	/**
	 * Available tag elements.
	 *
	 * @var array
	 */
	public $tag_list = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Get records from XML file by xpath.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $fileName Target file path.
	 * @param string      $xpath    XPath query expression.
	 * @param int|bool    $start    Start offset.
	 * @param int|bool    $length   Number of records to fetch.
	 * @param bool        $total    Whether to count total records.
	 * @param bool        $tags     Whether to retrieve tag schema list.
	 * @param string      $xmlView  Output view mode ('single_array', 'xml', etc.).
	 * @return mixed Parsed records or empty string on failure.
	 */
	public function get_records( $fileName = "", $xpath = "", $start = false, $length = false, $total = false, $tags = false, $xmlView = "single_array" ) {

		if ( file_exists( WPIE_LIBRARIES_DIR . '/xml/class-wpie-xml2array.php' ) ) {
			require_once( WPIE_LIBRARIES_DIR . '/xml/class-wpie-xml2array.php' );
		}

		$converter = new \wpie\lib\xml\xml2array\XmlToArray( $fileName );

		if ( ! $converter->isValidXml() ) {
			return "";
		}

		if ( $xpath !== '//' ) {
			$converter->set_xpath( $xpath );
		}
		$records = $converter->get_records( $start, $length, $xmlView );

		if ( $total === true ) {
			$this->record_length = $converter->get_record_length();
		} else {
			$this->record_length = 0;
		}
		if ( $tags === true ) {
			$this->tag_list = $converter->get_tags();
		} else {
			$this->tag_list = array();
		}

		unset( $converter );

		return $records;
	}

	/**
	 * Automatically inspect and fetch sample records based on template configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param array $template_options Template import options.
	 * @return array Sample records data and metadata.
	 */
	public function auto_fetch_records_by_template( $template_options = array() ) {

		$xpath = isset( $template_options[ "xpath" ] ) ? "/" . wp_unslash( $template_options[ "xpath" ] ) : "";

		$root = isset( $template_options[ "root" ] ) ? wpie_sanitize_field( wp_unslash( $template_options[ "root" ] ) ) : "";

		$start = isset( $template_options[ "start" ] ) ? intval( wpie_sanitize_field( $template_options[ "start" ] ) ) : 0;

		$length = isset( $template_options[ "length" ] ) ? intval( wpie_sanitize_field( $template_options[ "length" ] ) ) : 1;

		$activeFile = isset( $template_options[ 'activeFile' ] ) ? $template_options[ 'activeFile' ] : "";

		$importFile = isset( $template_options[ 'importFile' ] ) ? $template_options[ 'importFile' ] : array();

		$fileData = isset( $importFile[ $activeFile ] ) ? $importFile[ $activeFile ] : "";

		$file_name = isset( $fileData[ 'fileName' ] ) ? $fileData[ 'fileName' ] : "";

		$baseDir = isset( $fileData[ 'baseDir' ] ) ? $fileData[ 'baseDir' ] : "";

		$type = explode( '.', $file_name );

		$fileType = end( $type );

		$file_count = 1;

		$newFile = WPIE_UPLOAD_IMPORT_DIR . "/" . $baseDir . "/parse/" . $this->wpie_fileName . $file_count . '.xml';

		$isValid = $this->validateXmlFile( $newFile );

		$node_list = array();

		if ( $root === "" ) {

			$raw_nodes = $isValid ? $this->wpie_get_node_list( $newFile ) : array();

			$node_list = is_wp_error( $raw_nodes ) ? array() : $raw_nodes;

			$root = $isValid ? $this->wpie_get_root_node( $node_list ) : "";

			$xpath = "//" . $root;
		}

		$data = array();

		$data[ "root" ] = $root;

		$data[ "xpath" ] = $xpath;

		$data[ "node_list" ] = $node_list;

		$data[ "file_type" ] = $fileType;

		$data[ "content" ] = $isValid ? $this->get_records( $newFile, $xpath, $start, $length, true, true, "xml" ) : "";

		$data[ "count" ] = $this->record_length;

		$data[ "filter_element" ] = $this->tag_list;

		unset( $xpath, $root, $start, $length, $activeFile, $importFile, $fileData, $file_name, $baseDir, $type, $fileType, $file_count, $newFile, $node_list );

		return $data;
	}

	/**
	 * Validate if file contains valid XML header or opening tags.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file File path to validate.
	 * @return bool True if file contains XML opening, false otherwise.
	 */
	private function validateXmlFile( $file ) {

		if ( empty( $file ) || ! file_exists( $file ) || ! is_readable( $file ) || filesize( $file ) === 0 ) {
			return false;
		}

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct stream inspection for XML validation.
		$fp = @fopen( $file, 'r' );

		if ( ! is_resource( $fp ) ) {
			return false;
		}

		$data = fread( $fp, 100 );

		$data = preg_replace( '/[^[:print:]]/', '', (string) $data );

		fclose( $fp );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( mb_substr( trim( $data ), 0, 1 ) === "<" || mb_strpos( $data, "<?xml" ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine the most suitable root node from detected element frequencies.
	 *
	 * @since 1.0.0
	 *
	 * @param array $nodeList Detected nodes with occurrence counts.
	 * @return string Selected root element name.
	 */
	private function wpie_get_root_node( $nodeList = array() ) {

		$wpie_xpath = "";

		if ( ! empty( $nodeList ) && is_array( $nodeList ) ) {

			$preset_elements = array( 'item', 'property', 'listing', 'hotel', 'record', 'article', 'node', 'post', 'book', 'item_0', 'job', 'deal', 'product', 'entry' );

			foreach ( $nodeList as $element_name => $value ) {
				if ( in_array( strtolower( $element_name ), $preset_elements, true ) ) {
					$wpie_xpath = $element_name;
					break;
				}
			}
			unset( $preset_elements );

			if ( empty( $wpie_xpath ) ) {
				foreach ( $nodeList as $element => $count ) {
					$wpie_xpath = $element;
					break;
				}
			}
		}

		return $wpie_xpath;
	}

	/**
	 * Count node element occurrences using XMLReader streaming.
	 *
	 * @since 1.0.0
	 *
	 * @param string $filePath Path to XML file.
	 * @return array|\WP_Error Associative array of element names and counts, or WP_Error.
	 */
	private function wpie_get_node_list( $filePath = "" ) {

		if ( empty( $filePath ) || ! file_exists( $filePath ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'File not exist', 'wp-import-export-lite' ) );
		}
		if ( filesize( $filePath ) === 0 ) {
			return new \WP_Error( 'wpie_import_error', __( 'File is Empty', 'wp-import-export-lite' ) );
		}

		$nodeList = array();

		$reader = new \XMLReader();

		$opened = @$reader->open( $filePath );
		if ( ! $opened ) {
			return new \WP_Error( 'wpie_import_error', __( 'Unable to open XML file', 'wp-import-export-lite' ) );
		}

		$reader->setParserProperty( XMLReader::VALIDATE, false );

		while ( $reader->read() ) {

			switch ( $reader->nodeType ) {

				case ( XMLREADER::ELEMENT ):

					$localName = $reader->name;

					if ( isset( $nodeList[ $localName ] ) ) {
						$nodeList[ $localName ]++;
					} else {
						$nodeList[ $localName ] = 1;
					}
					unset( $localName );

					break;
				default:

					break;
			}
		}

		$reader->close();
		unset( $reader );

		return $nodeList;
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

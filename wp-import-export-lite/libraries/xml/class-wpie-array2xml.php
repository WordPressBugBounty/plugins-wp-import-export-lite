<?php
/**
 * Array to XML Converter Library.
 *
 * Converts PHP associative and indexed arrays into well-formed XML documents or strings.
 * Compatible with PHP 5.6 to 8.4+.
 *
 * @package    wpie\lib\xml\array2xml
 * @subpackage wpie\lib\xml
 * @since      1.0.0
 */

namespace wpie\lib\xml\array2xml;

defined( 'ABSPATH' ) || exit;

/**
 * Class ArrayToXml
 *
 * Provides methods to construct and manipulate XML DOM documents from nested PHP arrays.
 *
 * @since 1.0.0
 */
class ArrayToXml {

	/**
	 * The root DOM Document instance.
	 *
	 * @var \DOMDocument
	 */
	protected $document;

	/**
	 * Whether to replace spaces with underscores in XML element key names.
	 *
	 * @var bool
	 */
	protected $replaceSpacesByUnderScoresInKeyNames = true;

	/**
	 * Root DOMElement of the XML document.
	 *
	 * @var \DOMElement|null
	 */
	public $root;

	/**
	 * Whether to skip empty nodes during generation.
	 *
	 * @var bool
	 */
	private $skip_empty = false;

	/**
	 * Constructor.
	 *
	 * Initializes the DOMDocument instance with specified encoding, version, and formatting.
	 *
	 * @since 1.0.0
	 * @param bool   $replaceSpacesByUnderScoresInKeyNames Whether to replace spaces with underscores.
	 * @param string $xmlEncoding                          Document character encoding.
	 * @param string $xmlVersion                           XML standard version.
	 * @param bool   $formatOutput                         Whether to format output with indentation.
	 */
	public function __construct( $replaceSpacesByUnderScoresInKeyNames = true, $xmlEncoding = 'UTF-8', $xmlVersion = '1.0', $formatOutput = true ) {
		$this->document = new \DOMDocument( (string) $xmlVersion, (string) $xmlEncoding );
		$this->replaceSpacesByUnderScoresInKeyNames = (bool) $replaceSpacesByUnderScoresInKeyNames;
		$this->document->formatOutput              = (bool) $formatOutput;
	}

	/**
	 * Loads an existing XML document from a file path.
	 *
	 * @since 1.0.0
	 * @param string $fileName Path to the XML file.
	 * @return bool True on success, false on failure.
	 */
	public function load_file( $fileName = '' ) {
		if ( ! empty( $fileName ) && file_exists( $fileName ) ) {
			return (bool) $this->document->load( $fileName );
		}
		return false;
	}

	/**
	 * Retrieves elements matching a tag name from the underlying DOMDocument.
	 *
	 * @since 1.0.0
	 * @param string $name Tag name to search for.
	 * @return \DOMNodeList Matching elements.
	 */
	public function getElementsByTagName( $name = '' ) {
		return $this->document->getElementsByTagName( (string) $name );
	}

	/**
	 * Enables skipping of empty nodes during XML generation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function skip_empty() {
		$this->skip_empty = true;
	}

	/**
	 * Appends a child node under a parent tag in the document.
	 *
	 * @since 1.0.0
	 * @param string       $parent_tag Tag name of the parent element.
	 * @param string       $key        Tag name for the new child element.
	 * @param mixed        $value      Node data (scalar, array, or null).
	 * @return void
	 */
	public function append_child( $parent_tag = '', $key = '', $value = array() ) {
		$elements = $this->document->getElementsByTagName( (string) $parent_tag );

		if ( ! $elements || 0 === $elements->length || ! $elements->item( 0 ) ) {
			$element = $this->create_root( $parent_tag );
		} else {
			$element = $elements->item( 0 );
		}

		if ( $element instanceof \DOMElement ) {
			$this->addNode( $element, $key, $value );
		}
	}

	/**
	 * Creates and appends the root element to the DOMDocument.
	 *
	 * @since 1.0.0
	 * @param string|array $rootElement Root element definition.
	 * @return \DOMElement Created root element.
	 */
	public function create_root( $rootElement = '' ) {
		$this->root = $this->createRootElement( $rootElement );
		$this->document->appendChild( $this->root );
		return $this->root;
	}

	/**
	 * Converts an array to an XML string statically.
	 *
	 * @since 1.0.0
	 * @param array        $array                                Array of data to convert.
	 * @param string|array $rootElement                          Root element name or structure.
	 * @param bool         $replaceSpacesByUnderScoresInKeyNames Whether to replace spaces with underscores.
	 * @param string|null  $xmlEncoding                          XML encoding (defaults to UTF-8).
	 * @param string       $xmlVersion                           XML version (defaults to 1.0).
	 * @return string XML document string.
	 */
	public static function convert( array $array, $rootElement = '', $replaceSpacesByUnderScoresInKeyNames = true, $xmlEncoding = null, $xmlVersion = '1.0' ) {
		$encoding  = ! empty( $xmlEncoding ) ? $xmlEncoding : 'UTF-8';
		$version   = ! empty( $xmlVersion ) ? $xmlVersion : '1.0';
		$converter = new static( $replaceSpacesByUnderScoresInKeyNames, $encoding, $version );

		$root = $converter->create_root( $rootElement );

		if ( ! empty( $array ) ) {
			foreach ( $array as $key => $value ) {
				$converter->addNode( $root, $key, $value, 0 );
			}
		}

		return $converter->toXml();
	}

	/**
	 * Returns the generated XML string.
	 *
	 * @since 1.0.0
	 * @return string XML string representation.
	 */
	public function toXml() {
		return (string) $this->document->saveXML();
	}

	/**
	 * Saves the XML document to a specified file.
	 *
	 * @since 1.0.0
	 * @param string $fileName Target file path.
	 * @return int|false Bytes written, or false on error.
	 */
	public function saveFile( $fileName = '' ) {
		if ( empty( $fileName ) ) {
			return false;
		}
		return $this->document->save( $fileName );
	}

	/**
	 * Returns the underlying DOMDocument object.
	 *
	 * @since 1.0.0
	 * @return \DOMDocument DOM document instance.
	 */
	public function toDom() {
		return $this->document;
	}

	/**
	 * Recursively converts array elements into DOM nodes.
	 *
	 * @since 1.0.0
	 * @param \DOMElement $element Parent DOM element.
	 * @param mixed       $value   Data value or child array.
	 * @param int         $lvl     Nesting depth level.
	 * @return void
	 */
	public function convertElement( \DOMElement $element, $value, $lvl = 0 ) {
		if ( ! is_array( $value ) ) {
			if ( null !== $value && '' !== $value ) {
				$is_valid_utf8 = function_exists( 'wp_is_valid_utf8' ) ? wp_is_valid_utf8( $value ) : ( function_exists( 'seems_utf8' ) ? seems_utf8( $value ) : true ); // phpcs:ignore WordPress.WP.DeprecatedFunctions.seems_utf8Found
				if ( is_string( $value ) && false === $is_valid_utf8 ) {
					if ( function_exists( 'wpie_utf8_encode' ) ) {
						$value = wpie_utf8_encode( $value );
					} elseif ( function_exists( 'mb_convert_encoding' ) ) {
						$value = mb_convert_encoding( $value, 'UTF-8', 'ISO-8859-1' );
					} elseif ( function_exists( 'iconv' ) ) {
						$value = iconv( 'ISO-8859-1', 'UTF-8//IGNORE', $value );
					} elseif ( function_exists( 'utf8_encode' ) && PHP_VERSION_ID < 80200 ) {
						$value = @utf8_encode( $value ); // phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.utf8_encodeDeprecated
					}
				}

				$value = $this->removeControlCharacters( (string) $value );

				// Escape CDATA delimiter sequences to maintain valid XML.
				$safe_cdata = str_replace( ']]>', ']]]]><![CDATA[>', $value );
				$element->appendChild( $this->document->createCDATASection( $safe_cdata ) );
			} else {
				$element->nodeValue = '';
			}
			return;
		}

		if ( ! empty( $value ) ) {
			foreach ( $value as $key => $data ) {
				if ( '_attributes' === $key || '@attributes' === $key ) {
					$this->addAttributes( $element, $data );
				} elseif ( ( '_value' === $key || '@value' === $key ) && is_string( $data ) ) {
					$safe_cdata = str_replace( ']]>', ']]]]><![CDATA[>', $data );
					$element->appendChild( $this->document->createCDATASection( $safe_cdata ) );
				} elseif ( ( '_cdata' === $key || '@cdata' === $key ) && is_string( $data ) ) {
					$safe_cdata = str_replace( ']]>', ']]]]><![CDATA[>', $data );
					$element->appendChild( $this->document->createCDATASection( $safe_cdata ) );
				} elseif ( ( '_mixed' === $key || '@mixed' === $key ) && is_string( $data ) ) {
					$fragment = $this->document->createDocumentFragment();
					$fragment->appendXML( $data );
					$element->appendChild( $fragment );
					unset( $fragment );
				} else {
					if ( is_numeric( $key ) ) {
						$key = 'item';
						if ( $lvl > 0 ) {
							$key = $key . '_' . $lvl;
						}
					}
					$this->addNode( $element, $key, $data, $lvl );
				}
			}
		}
	}

	/**
	 * Adds a child node with a sanitized tag name to the parent DOMElement.
	 *
	 * @since 1.0.0
	 * @param \DOMElement $element Parent DOM element.
	 * @param string      $key     Node tag name.
	 * @param mixed       $value   Node content or children.
	 * @param int         $lvl     Nesting depth level.
	 * @return void
	 */
	public function addNode( \DOMElement $element, $key, $value, $lvl = 0 ) {
		if ( true === $this->skip_empty && is_scalar( $value ) && '' === trim( (string) $value ) ) {
			return;
		}

		if ( $this->replaceSpacesByUnderScoresInKeyNames ) {
			$key = str_replace( ' ', '_', (string) $key );
		}

		$key = preg_replace( '/[^a-z0-9_]/i', '', strtolower( (string) $key ) );

		if ( '' === trim( $key ) ) {
			return;
		}

		// XML element names cannot start with a digit; prefix with 'item_' if needed.
		if ( is_numeric( substr( $key, 0, 1 ) ) ) {
			$key = 'item_' . $key;
		}

		$child = $this->document->createElement( $key );
		$element->appendChild( $child );

		$this->convertElement( $child, $value, $lvl + 1 );
	}

	/**
	 * Sets attributes on a DOMElement from an associative array.
	 *
	 * @since 1.0.0
	 * @param \DOMElement $element Target DOM element.
	 * @param array       $data    Key-value attributes.
	 * @return void
	 */
	protected function addAttributes( \DOMElement $element, $data ) {
		if ( is_array( $data ) && ! empty( $data ) ) {
			foreach ( $data as $attrKey => $attrVal ) {
				$cleanKey = preg_replace( '/[^a-z0-9_:-]/i', '', (string) $attrKey );
				if ( '' !== $cleanKey && ! is_numeric( substr( $cleanKey, 0, 1 ) ) ) {
					$element->setAttribute( $cleanKey, (string) $attrVal );
				}
			}
		}
	}

	/**
	 * Creates the root element for the XML document.
	 *
	 * @since 1.0.0
	 * @param string|array $rootElement Root element name or specification.
	 * @return \DOMElement Created root element.
	 */
	protected function createRootElement( $rootElement ) {
		if ( is_string( $rootElement ) ) {
			$rootElementName = '' !== trim( $rootElement ) ? $rootElement : 'root';
			$rootElementName = preg_replace( '/[^a-z0-9_]/i', '', strtolower( $rootElementName ) );
			if ( is_numeric( substr( $rootElementName, 0, 1 ) ) ) {
				$rootElementName = 'root_' . $rootElementName;
			}
			return $this->document->createElement( $rootElementName );
		}

		$rootElementName = isset( $rootElement['rootElementName'] ) ? (string) $rootElement['rootElementName'] : 'root';
		$rootElementName = preg_replace( '/[^a-z0-9_]/i', '', strtolower( $rootElementName ) );
		if ( '' === $rootElementName || is_numeric( substr( $rootElementName, 0, 1 ) ) ) {
			$rootElementName = 'root_' . $rootElementName;
		}

		$element = $this->document->createElement( $rootElementName );

		if ( is_array( $rootElement ) && ! empty( $rootElement ) ) {
			foreach ( $rootElement as $key => $value ) {
				if ( '_attributes' !== $key && '@attributes' !== $key ) {
					continue;
				}
				$this->addAttributes( $element, $rootElement[ $key ] );
			}
		}

		return $element;
	}

	/**
	 * Strips ASCII control characters from string values to ensure valid XML.
	 *
	 * @since 1.0.0
	 * @param string $value Input string.
	 * @return string Sanitized string.
	 */
	protected function removeControlCharacters( $value ) {
		return preg_replace( '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $value );
	}

	/**
	 * Destructor.
	 *
	 * Cleans up references safely without unsetting internal properties dynamically.
	 *
	 * @since 1.0.0
	 */
	public function __destruct() {
		$this->root     = null;
		$this->document = null;
	}
}

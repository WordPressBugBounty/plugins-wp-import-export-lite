<?php
/**
 * XML to Array Parser Library.
 *
 * Parses XML strings and files into hierarchical or flattened PHP arrays using DOMDocument and DOMXPath.
 * Compatible with PHP 5.6 to 8.4+.
 *
 * @package    wpie\lib\xml\xml2array
 * @subpackage wpie\lib\xml
 * @since      1.0.0
 */

namespace wpie\lib\xml\xml2array;

defined( 'ABSPATH' ) || exit;

/**
 * Class XmlToArray
 *
 * Provides methods to load XML, execute XPath queries, and convert elements to various array formats.
 *
 * @since 1.0.0
 */
class XmlToArray {

	/**
	 * Internal DOMDocument instance.
	 *
	 * @var \DOMDocument
	 */
	protected $document;

	/**
	 * DOMXPath query runner.
	 *
	 * @var \DOMXPath|null
	 */
	protected $domxpath;

	/**
	 * NodeList resulting from XPath query.
	 *
	 * @var \DOMNodeList|false|null
	 */
	protected $xpathelement;

	/**
	 * Flattened XML single-array data storage.
	 *
	 * @var array
	 */
	private $xmldata = array();

	/**
	 * Tracks element occurrences during tag path resolution.
	 *
	 * @var array
	 */
	private $wpie_filtering_element = array();

	/**
	 * Whether the loaded XML was valid.
	 *
	 * @var bool
	 */
	private $isValidXml = true;

	/**
	 * Constructor.
	 *
	 * Loads XML from file path or string, applies sanitization, and configures DOMDocument securely.
	 *
	 * @since 1.0.0
	 * @param string $fileName           Path to XML file.
	 * @param string $xml                Raw XML content string.
	 * @param string $xmlEncoding        XML document encoding.
	 * @param string $xmlVersion         XML document version.
	 * @param bool   $formatOutput       Whether to format XML output.
	 * @param bool   $preserveWhiteSpace Whether to preserve whitespace.
	 */
	public function __construct( $fileName = '', $xml = '', $xmlEncoding = 'UTF-8', $xmlVersion = '1.0', $formatOutput = true, $preserveWhiteSpace = false ) {
		$this->document = new \DOMDocument( (string) $xmlVersion, (string) $xmlEncoding );

		$this->document->formatOutput       = (bool) $formatOutput;
		$this->document->strictErrorChecking = false;
		$this->document->recover            = true;
		$this->document->preserveWhiteSpace = (bool) $preserveWhiteSpace;

		$new_xml = '';

		if ( ! empty( $fileName ) && file_exists( $fileName ) ) {
			$new_xml = (string) file_get_contents( $fileName );
		} elseif ( ! empty( $xml ) ) {
			$new_xml = (string) $xml;
		}

		$trimmed_xml = ltrim( $new_xml );
		if ( '' === $trimmed_xml || ( '<' !== substr( $trimmed_xml, 0, 1 ) && false === strpos( $trimmed_xml, '<?xml' ) ) ) {
			$this->isValidXml = false;
			return;
		}

		if ( ! empty( $new_xml ) ) {
			$new_xml = preg_replace( '%xmlns\s*=\s*([\'"]).*\1%sU', '', $new_xml );

			$is_valid_utf8 = function_exists( 'wp_is_valid_utf8' ) ? wp_is_valid_utf8( $new_xml ) : ( function_exists( 'seems_utf8' ) ? seems_utf8( $new_xml ) : true ); // phpcs:ignore WordPress.WP.DeprecatedFunctions.seems_utf8Found
			if ( ! $is_valid_utf8 ) {
				if ( function_exists( 'wpie_utf8_encode' ) ) {
					$new_xml = wpie_utf8_encode( $new_xml );
				} elseif ( function_exists( 'mb_convert_encoding' ) ) {
					$new_xml = mb_convert_encoding( $new_xml, 'UTF-8', 'ISO-8859-1' );
				} elseif ( function_exists( 'iconv' ) ) {
					$new_xml = iconv( 'ISO-8859-1', 'UTF-8//IGNORE', $new_xml );
				} elseif ( function_exists( 'utf8_encode' ) && PHP_VERSION_ID < 80200 ) {
					$new_xml = @utf8_encode( $new_xml ); // phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.utf8_encodeDeprecated
				}
			}

			// Clean invalid XML characters safely in UTF-8 mode.
			$new_xml = preg_replace( '/[^\x{9}\x{a}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $new_xml );

			// Prevent XXE attacks: disable external entity loading conditionally on PHP < 8.0.
			$prev_entity_loader = false;
			if ( function_exists( 'libxml_disable_entity_loader' ) && PHP_VERSION_ID < 80000 ) {
				$prev_entity_loader = libxml_disable_entity_loader( true );
			}

			$load_options = LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR;
			$load_result  = $this->document->loadXML( $new_xml, $load_options );

			if ( function_exists( 'libxml_disable_entity_loader' ) && PHP_VERSION_ID < 80000 ) {
				libxml_disable_entity_loader( $prev_entity_loader );
			}

			if ( false === $load_result ) {
				$this->isValidXml = false;
			}
		}
	}

	/**
	 * Checks if the loaded XML is valid.
	 *
	 * @since 1.0.0
	 * @return bool True if valid XML, false otherwise.
	 */
	public function isValidXml() {
		return $this->isValidXml;
	}

	/**
	 * Sets the XPath query and executes it against the document.
	 *
	 * @since 1.0.0
	 * @param string $xpath XPath query expression.
	 * @return void
	 */
	public function set_xpath( $xpath = '' ) {
		if ( ! $this->document instanceof \DOMDocument ) {
			return;
		}

		$this->domxpath     = new \DOMXPath( $this->document );
		$this->xpathelement = $this->domxpath->query( (string) $xpath );
	}

	/**
	 * Gets the number of matching records from the XPath query.
	 *
	 * @since 1.0.0
	 * @return int Number of records.
	 */
	public function get_record_length() {
		if ( $this->xpathelement instanceof \DOMNodeList && isset( $this->xpathelement->length ) ) {
			return (int) $this->xpathelement->length;
		}
		return 0;
	}

	/**
	 * Retrieves records within a slice in the specified view format.
	 *
	 * @since 1.0.0
	 * @param int|false $start   Starting record index.
	 * @param int|false $length  Number of records to fetch.
	 * @param string    $xmlView Output format: 'array', 'xml', or 'single_array'.
	 * @return array Array of parsed records.
	 */
	public function get_records( $start = false, $length = false, $xmlView = 'array' ) {
		$result = array();

		if ( $this->xpathelement instanceof \DOMNodeList && $this->xpathelement->length > 0 ) {
			if ( false === $start || false === $length ) {
				$new_start    = 0;
				$final_length = $this->xpathelement->length;
			} else {
				$new_start    = max( absint( $start ), 0 );
				$fetch_len    = min( absint( $length ), $this->xpathelement->length );
				$final_length = $new_start + $fetch_len;
			}

			for ( $i = $new_start; $i < $final_length; $i++ ) {
				if ( $i >= $this->xpathelement->length ) {
					break;
				}

				$item = $this->xpathelement->item( $i );
				if ( ! $item instanceof \DOMElement ) {
					continue;
				}

				if ( 'xml' === $xmlView ) {
					$result[] = $this->convertXMLView( $item, '', 1, 0 );
				} elseif ( 'single_array' === $xmlView ) {
					$this->xmldata = array();
					$this->convertSingleArray( $item, '', 1, 0 );
					$result[] = $this->xmldata;
				} else {
					$result[] = $this->convertDomElement( $item );
				}
			}
		}

		return $result;
	}

	/**
	 * Retrieves unique tag paths from the first matching XPath record.
	 *
	 * @since 1.0.0
	 * @return array List of tag paths.
	 */
	public function get_tags() {
		if ( $this->xpathelement instanceof \DOMNodeList && $this->xpathelement->length > 0 ) {
			$this->wpie_filtering_element = array();
			$first_item = $this->xpathelement->item( 0 );
			if ( $first_item instanceof \DOMElement ) {
				return $this->get_tag_list( $first_item );
			}
		}
		return array();
	}

	/**
	 * Recursively gathers tag paths and attribute paths for an element.
	 *
	 * @since 1.0.0
	 * @param mixed  $element    Target DOM element.
	 * @param string $originPath Parent path prefix.
	 * @param int    $lvl        Nesting depth level.
	 * @return array Collected tag paths.
	 */
	private function get_tag_list( $element, $originPath = '', $lvl = 0 ) {
		if ( ! $element instanceof \DOMElement ) {
			return array();
		}

		$filtering_elements = array();
		$path               = $originPath;

		if ( '' !== $path ) {
			if ( $lvl > 1 ) {
				$path .= '->' . $element->nodeName;
			} else {
				$path = $element->nodeName;
			}

			if ( empty( $this->wpie_filtering_element[ $path ] ) ) {
				$this->wpie_filtering_element[ $path ] = 1;
			} else {
				$this->wpie_filtering_element[ $path ]++;
			}

			$filtering_elements[] = $path . '[' . $this->wpie_filtering_element[ $path ] . ']';
		} else {
			$path = $element->nodeName;
		}

		if ( ! empty( $element->attributes ) ) {
			foreach ( $element->attributes as $attr ) {
				if ( empty( $originPath ) ) {
					$filtering_elements[] = '@' . $attr->nodeName;
				} else {
					$filtering_elements[] = $path . '[' . $this->wpie_filtering_element[ $path ] . ']/@' . $attr->nodeName;
				}
			}
		}

		if ( $element->hasChildNodes() ) {
			foreach ( $element->childNodes as $child ) {
				if ( $child instanceof \DOMElement ) {
					$element_data       = $this->get_tag_list( $child, $path, $lvl + 1 );
					$filtering_elements = array_merge( $filtering_elements, $element_data );
					unset( $element_data );
				}
			}
		}

		return $filtering_elements;
	}

	/**
	 * Converts the loaded document into an associative array.
	 *
	 * @since 1.0.0
	 * @return array Hierarchical array representation.
	 */
	public function toArray() {
		$result = array();

		if ( $this->document instanceof \DOMDocument && $this->document->hasChildNodes() ) {
			$children = $this->document->childNodes;
			foreach ( $children as $child ) {
				if ( $child instanceof \DOMElement ) {
					$result[ $child->nodeName ] = $this->convertDomElement( $child );
				}
			}
		}

		return $result;
	}

	/**
	 * Recursively flattens an element into XPath-keyed single-array entries.
	 *
	 * @since 1.0.0
	 * @param mixed  $element Target DOM element.
	 * @param string $path    Current XPath representation.
	 * @param int    $ind     Sibling index.
	 * @param int    $lvl     Nesting depth level.
	 * @return void
	 */
	private function convertSingleArray( $element, $path = '/', $ind = 1, $lvl = 0 ) {
		if ( ! $element instanceof \DOMElement ) {
			return;
		}

		if ( $lvl > 1 ) {
			$path .= $element->nodeName;
		} else {
			$path = $element->nodeName;
		}

		if ( ! $element->parentNode instanceof \DOMDocument && (int) $ind > 0 ) {
			$path .= '[' . (int) $ind . ']';
		}

		if ( $element->hasAttributes() ) {
			foreach ( $element->attributes as $attr ) {
				$this->xmldata[ '{' . $path . '/@' . $attr->nodeName . '}' ] = $attr->value;
			}
		}

		if ( $element->hasChildNodes() ) {
			$index = array();

			foreach ( $element->childNodes as $node ) {
				$index[ $node->nodeName ] = isset( $index[ $node->nodeName ] ) ? ++$index[ $node->nodeName ] : 1;

				if ( $node instanceof \DOMCdataSection ) {
					$this->xmldata[ '{' . $path . '}' ] = isset( $node->data ) ? $node->data : ( isset( $node->nodeValue ) ? $node->nodeValue : '' );
				} elseif ( $node instanceof \DOMText ) {
					$this->xmldata[ '{' . $path . '}' ] = $node->textContent;
				} elseif ( $node instanceof \DOMElement ) {
					$this->convertSingleArray( $node, $path . '/', $index[ $node->nodeName ], $lvl + 1 );
				}
			}
			unset( $index );
		} else {
			$this->xmldata[ '{' . $path . '}' ] = '';
		}
	}

	/**
	 * Converts an element to an XML structure array containing node metadata.
	 *
	 * @since 1.0.0
	 * @param mixed  $element Target DOM element.
	 * @param string $path    Current XPath representation.
	 * @param int    $ind     Sibling index.
	 * @param int    $lvl     Nesting depth level.
	 * @return array|string XML structure array or empty string.
	 */
	private function convertXMLView( $element, $path = '/', $ind = 1, $lvl = 0 ) {
		if ( ! $element instanceof \DOMElement ) {
			return '';
		}

		if ( $lvl > 1 ) {
			$path .= $element->nodeName;
		} else {
			$path = $element->nodeName;
		}

		if ( ! $element->parentNode instanceof \DOMDocument && (int) $ind > 0 ) {
			$path .= '[' . (int) $ind . ']';
		}

		$result = array(
			'name' => $element->nodeName,
			'path' => $path,
		);

		if ( $element->hasAttributes() ) {
			$attributes = $this->convertAttributes( $element->attributes );
			if ( ! empty( $attributes ) ) {
				$result['@attributes'] = $attributes;
			}
			unset( $attributes );
		}

		if ( $element->hasChildNodes() ) {
			$index = array();

			foreach ( $element->childNodes as $node ) {
				$index[ $node->nodeName ] = isset( $index[ $node->nodeName ] ) ? ++$index[ $node->nodeName ] : 1;

				if ( $node instanceof \DOMCdataSection ) {
					$result['value'] = isset( $node->data ) ? $node->data : ( isset( $node->nodeValue ) ? $node->nodeValue : '' );
				} elseif ( $node instanceof \DOMText ) {
					$result['value'] = $node->textContent;
				} elseif ( $node instanceof \DOMElement ) {
					$result['value'][] = $this->convertXMLView( $node, $path . '/', $index[ $node->nodeName ], $lvl + 1 );
				}
			}
			unset( $index );
		}

		return ! empty( $result ) ? $result : '';
	}

	/**
	 * Converts a DOMElement recursively into a nested associative array.
	 *
	 * @since 1.0.0
	 * @param mixed $element Target DOM element.
	 * @return array|string Parsed array or node value.
	 */
	private function convertDomElement( $element ) {
		if ( ! $element instanceof \DOMElement ) {
			return '';
		}

		$result = array();

		if ( $element->hasAttributes() ) {
			$attributes = $this->convertAttributes( $element->attributes );
			if ( ! empty( $attributes ) ) {
				$result['@attributes'] = $attributes;
			}
			unset( $attributes );
		}

		if ( $element->hasChildNodes() ) {
			$index = array();

			foreach ( $element->childNodes as $node ) {
				$index[ $node->nodeName ] = isset( $index[ $node->nodeName ] ) ? ++$index[ $node->nodeName ] : 0;

				if ( $node instanceof \DOMCdataSection ) {
					$result = isset( $node->data ) ? $node->data : ( isset( $node->nodeValue ) ? $node->nodeValue : '' );
				} elseif ( $node instanceof \DOMText ) {
					$result = $node->textContent;
				} elseif ( $node instanceof \DOMElement ) {
					$nodeData = $this->convertDomElement( $node );

					if ( $index[ $node->nodeName ] > 0 ) {
						if ( 1 === $index[ $node->nodeName ] ) {
							$result[ $node->nodeName ] = array( $result[ $node->nodeName ], $nodeData );
						} else {
							$result[ $node->nodeName ][] = $nodeData;
						}
					} else {
						$result[ $node->nodeName ] = $nodeData;
					}
					unset( $nodeData );
				}
			}
			unset( $index );
		}

		return ! empty( $result ) ? $result : '';
	}

	/**
	 * Extracts attributes from a DOMNamedNodeMap into an associative array.
	 *
	 * @since 1.0.0
	 * @param \DOMNamedNodeMap $nodeMap Map of attributes.
	 * @return array|null Associative array of attributes or null if empty.
	 */
	private function convertAttributes( \DOMNamedNodeMap $nodeMap ) {
		if ( 0 === $nodeMap->length ) {
			return null;
		}

		$attributes = array();

		/** @var \DOMAttr $item */
		foreach ( $nodeMap as $item ) {
			$attributes[ $item->name ] = $item->value;
		}

		return $attributes;
	}

	/**
	 * Destructor.
	 *
	 * Cleans up DOM and XPath objects safely.
	 *
	 * @since 1.0.0
	 */
	public function __destruct() {
		$this->document     = null;
		$this->domxpath     = null;
		$this->xpathelement = null;
		$this->xmldata      = array();
	}
}

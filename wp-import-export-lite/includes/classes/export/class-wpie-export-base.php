<?php
/**
 * Abstract Base Class for Export Engines.
 *
 * Provides shared utilities for filter rule compilation, meta key validation,
 * date parsing, prefix removal, user-defined function execution, and taxonomy lookups.
 *
 * @package WP_Import_Export_Lite
 * @subpackage Export\Base
 * @since 1.0.0
 */

namespace wpie\export\base;

use WpieApp\Core\Helpers\SafeFunction;

defined( 'ABSPATH' ) || exit;

/**
 * Class WPIE_Export_Base
 *
 * Abstract base providing standard properties and helper methods for export engines.
 *
 * @since 1.0.0
 */
abstract class WPIE_Export_Base {

	/**
	 * Export post types or entity identifiers.
	 *
	 * @var array|string
	 */
	protected $export_type;

	/**
	 * Target taxonomy type if exporting taxonomy terms.
	 *
	 * @var string
	 */
	protected $export_taxonomy_type;

	/**
	 * Active export template record ID.
	 *
	 * @var int
	 */
	protected $export_id;

	/**
	 * Deserialized template configuration options.
	 *
	 * @var array|object
	 */
	protected $template_options;

	/**
	 * Process progress log data (e.g. exported, total).
	 *
	 * @var array
	 */
	protected $process_log;

	/**
	 * Buffer of export row data for the current item.
	 *
	 * @var array
	 */
	protected $export_data;

	/**
	 * Whether the current run is a preview request.
	 *
	 * @var bool
	 */
	protected $is_preview = false;

	/**
	 * Current operation mode (e.g. 'export', 'count', 'ids', 'preview', 'fields').
	 *
	 * @var string
	 */
	protected $opration = 'export';

	/**
	 * Preview rows buffer.
	 *
	 * @var array
	 */
	protected $preview_data = array();

	/**
	 * Associative array of column header keys to human-readable labels.
	 *
	 * @var array
	 */
	protected $export_labels = array();

	/**
	 * Instantiated export add-ons for the current export run.
	 *
	 * @var array
	 */
	protected $addons = array();

	/**
	 * Whether the current entity expands to multiple CSV rows.
	 *
	 * @var bool
	 */
	protected $has_multiple_rows = false;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Retrieve taxonomy names or objects associated with given object type(s).
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $object_type Object type slug(s) (e.g. 'post', 'product').
	 * @param string       $output      Output format: 'names' or 'objects'. Default 'names'.
	 * @return array Array of taxonomy names or objects.
	 */
	protected function taxonomies_by_object_type( $object_type = null, $output = 'names' ) {

		global $wp_taxonomies;

		if ( ! is_array( $object_type ) ) {
			$object_type = array( (string) $object_type );
		}

		$field = ( 'names' === $output ) ? 'name' : false;

		$taxonomy = array();

		if ( ! empty( $wp_taxonomies ) && is_array( $wp_taxonomies ) ) {
			foreach ( $wp_taxonomies as $key => $obj ) {
				if ( is_object( $obj ) && isset( $obj->object_type ) && is_array( $obj->object_type ) ) {
					if ( array_intersect( $object_type, $obj->object_type ) ) {
						$taxonomy[ $key ] = $obj;
					}
				}
			}
		}

		if ( $field ) {
			$taxonomy = wp_list_pluck( $taxonomy, $field );
		}

		unset( $field, $object_type );

		return $taxonomy;
	}

	/**
	 * Build SQL comparison clause and value expression for filter rules.
	 *
	 * @since 1.0.0
	 *
	 * @param array       $filters     Rule configuration array containing condition, value, element, clause.
	 * @param bool        $is_int      Whether value should be treated as an integer. Default false.
	 * @param string|bool $table_alias Optional table alias prefix for meta columns. Default false.
	 * @return string Formatted SQL comparison snippet.
	 */
	protected function add_filter_rule( $filters = array(), $is_int = false, $table_alias = false ) {

		global $wpdb;

		$condition  = isset( $filters['condition'] ) ? sanitize_key( $filters['condition'] ) : '';
		$value      = isset( $filters['value'] ) ? $filters['value'] : '';
		$element    = isset( $filters['element'] ) ? sanitize_text_field( $filters['element'] ) : '';
		$raw_clause = isset( $filters['clause'] ) ? strtoupper( trim( (string) $filters['clause'] ) ) : '';
		$clause     = in_array( $raw_clause, array( 'AND', 'OR' ), true ) ? $raw_clause : '';

		$return_data = '';

		if ( ! empty( $condition ) ) {

			switch ( $condition ) {
				case 'equals':
					if ( in_array( $element, array( 'post_date', 'comment_date', 'user_registered', 'user_role' ), true ) ) {
						$return_data = $wpdb->prepare( 'LIKE %s', '%' . $wpdb->esc_like( (string) $value ) . '%' );
					} else {
						$return_data = '= ' . ( ( $is_int && is_numeric( $value ) ) ? intval( $value ) : $wpdb->prepare( '%s', $value ) );
					}
					break;
				case 'not_equals':
					if ( in_array( $element, array( 'post_date', 'comment_date', 'user_registered', 'user_role' ), true ) ) {
						$return_data = $wpdb->prepare( 'NOT LIKE %s', '%' . $wpdb->esc_like( (string) $value ) . '%' );
					} else {
						$return_data = '!= ' . ( ( $is_int && is_numeric( $value ) ) ? intval( $value ) : $wpdb->prepare( '%s', $value ) );
					}
					break;
				case 'greater':
					$return_data = '> ' . ( ( $is_int && is_numeric( $value ) ) ? intval( $value ) : $wpdb->prepare( '%s', $value ) );
					break;
				case 'equals_or_greater':
					$return_data = '>= ' . ( ( $is_int && is_numeric( $value ) ) ? intval( $value ) : $wpdb->prepare( '%s', $value ) );
					break;
				case 'less':
					$return_data = '< ' . ( ( $is_int && is_numeric( $value ) ) ? intval( $value ) : $wpdb->prepare( '%s', $value ) );
					break;
				case 'equals_or_less':
					$return_data = '<= ' . ( ( $is_int && is_numeric( $value ) ) ? intval( $value ) : $wpdb->prepare( '%s', $value ) );
					break;
				case 'contains':
					$return_data = $wpdb->prepare( 'LIKE %s', '%' . $wpdb->esc_like( (string) $value ) . '%' );
					break;
				case 'not_contains':
					$return_data = $wpdb->prepare( 'NOT LIKE %s', '%' . $wpdb->esc_like( (string) $value ) . '%' );
					break;
				case 'is_empty':
					$return_data = 'IS NULL';
					break;
				case 'is_not_empty':
					$return_data = 'IS NOT NULL';
					if ( $table_alias ) {
						$safe_alias = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $table_alias );
						$return_data .= " AND {$safe_alias}.meta_value <> '' ";
					}
					break;
				case 'in':
					$in_value = array();

					if ( ! empty( $value ) ) {
						$value_arr = explode( ',', (string) $value );

						foreach ( $value_arr as $_val ) {
							$trimmed_val = trim( (string) $_val );
							if ( $is_int && is_numeric( $trimmed_val ) ) {
								$in_value[] = intval( $trimmed_val );
							} else {
								$in_value[] = $wpdb->prepare( '%s', $trimmed_val );
							}
						}
					}
					$in_value_str = empty( $in_value ) ? "''" : implode( ',', $in_value );
					$return_data  = 'IN (' . $in_value_str . ')';
					unset( $in_value, $in_value_str );
					break;
				case 'not_in':
					$in_value = array();

					if ( ! empty( $value ) ) {
						$value_arr = explode( ',', (string) $value );

						foreach ( $value_arr as $_val ) {
							$trimmed_val = trim( (string) $_val );
							if ( $is_int && is_numeric( $trimmed_val ) ) {
								$in_value[] = intval( $trimmed_val );
							} else {
								$in_value[] = $wpdb->prepare( '%s', $trimmed_val );
							}
						}
					}
					$in_value_str = empty( $in_value ) ? "''" : implode( ',', $in_value );
					$return_data  = 'NOT IN (' . $in_value_str . ')';
					unset( $in_value, $in_value_str );
					break;
				default:
					break;
			}
		}

		if ( ! empty( $clause ) ) {
			$return_data .= ' ' . $clause . ' ';
		}

		unset( $condition, $value, $element, $clause, $raw_clause );

		return $this->add_placeholder( $return_data );
	}

	/**
	 * Validate meta key against allowlist and database.
	 *
	 * @since 4.0.1
	 *
	 * @param string $meta_key  Meta key to validate. Default empty string.
	 * @param string $meta_type Meta type ('post', 'user', 'taxonomy', 'comment'). Default 'post'.
	 * @return bool True if valid and allowed, false otherwise.
	 */
	protected function is_valid_meta_key( $meta_key = '', $meta_type = 'post' ) {

		if ( empty( $meta_key ) || ! is_string( $meta_key ) || strlen( $meta_key ) > 255 ) {
			return false;
		}

		// Strict identifier validation: no quotes, whitespace, or SQL control characters.
		if ( ! preg_match( '/^[a-zA-Z0-9_\-\.\:\/]+$/', $meta_key ) ) {
			return false;
		}

		static $meta_key_allowlist_cache = array();

		if ( isset( $meta_key_allowlist_cache[ $meta_type ][ $meta_key ] ) ) {
			return $meta_key_allowlist_cache[ $meta_type ][ $meta_key ];
		}

		global $wpdb;

		// Built-in allowlist of common WordPress meta keys.
		$common_meta_keys = array(
			'post'     => array(
				'_thumbnail_id',
				'_edit_last',
				'_edit_lock',
				'_wp_page_template',
				'_wp_attached_file',
				'_wp_attachment_metadata',
				'_sku',
				'_price',
				'_regular_price',
				'_sale_price',
				'_stock',
				'_stock_status',
				'_yoast_wpseo_focuskw',
				'_yoast_wpseo_title',
				'_yoast_wpseo_metadesc',
				'_completed_date',
			),
			'user'     => array(
				'first_name',
				'last_name',
				'nickname',
				'description',
				'wp_capabilities',
				'wp_user_level',
				'dismissed_wp_pointers',
				'show_admin_bar_front',
				'use_ssl',
				'admin_color',
			),
			'taxonomy' => array(
				'thumbnail_id',
				'display_type',
				'product_count_product_cat',
			),
			'comment'  => array(
				'rating',
				'verified',
			),
		);

		if ( isset( $common_meta_keys[ $meta_type ] ) && in_array( $meta_key, $common_meta_keys[ $meta_type ], true ) ) {
			$meta_key_allowlist_cache[ $meta_type ][ $meta_key ] = true;
			return true;
		}

		// Allow dynamic hooks to filter allowed meta keys.
		$allowed = apply_filters( 'wpie_is_valid_meta_key', null, $meta_key, $meta_type );
		if ( is_bool( $allowed ) ) {
			$meta_key_allowlist_cache[ $meta_type ][ $meta_key ] = $allowed;
			return $allowed;
		}

		// Check if the meta key exists in the corresponding database table.
		$table = '';
		switch ( $meta_type ) {
			case 'post':
				$table = isset( $wpdb->postmeta ) ? $wpdb->postmeta : '';
				break;
			case 'user':
				$table = isset( $wpdb->usermeta ) ? $wpdb->usermeta : '';
				break;
			case 'taxonomy':
			case 'term':
				$table = isset( $wpdb->termmeta ) ? $wpdb->termmeta : '';
				break;
			case 'comment':
				$table = isset( $wpdb->commentmeta ) ? $wpdb->commentmeta : '';
				break;
			default:
				$table = isset( $wpdb->postmeta ) ? $wpdb->postmeta : '';
				break;
		}

		if ( ! empty( $table ) && isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_var' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is strictly derived from trusted core $wpdb properties.
			$exists   = $wpdb->get_var( $wpdb->prepare( "SELECT meta_key FROM {$table} WHERE meta_key = %s LIMIT 1", $meta_key ) );
			$is_valid = ! empty( $exists );
			$meta_key_allowlist_cache[ $meta_type ][ $meta_key ] = $is_valid;
			return $is_valid;
		}

		$meta_key_allowlist_cache[ $meta_type ][ $meta_key ] = false;
		return false;
	}

	/**
	 * Remove placeholder escape characters from query if wpdb supports it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query Query string.
	 * @return string Processed query string.
	 */
	public function add_placeholder( $query = '' ) {

		if ( empty( $query ) ) {
			return $query;
		}
		global $wpdb;

		return method_exists( $wpdb, 'remove_placeholder_escape' ) ? $wpdb->remove_placeholder_escape( $query ) : $query;
	}

	/**
	 * Backward compatibility alias for add_placeholder.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query Query string.
	 * @return string Processed query string.
	 */
	public function addlaceholder( $query = '' ) {
		return $this->add_placeholder( $query );
	}

	/**
	 * Calculate formatted date for date filter rules.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rule Rule settings array with 'value' and 'condition'.
	 * @return string Processed date string.
	 */
	protected function add_date_filter_rule( $rule = array() ) {

		$value = isset( $rule['value'] ) ? (string) $rule['value'] : '';

		$condition = isset( $rule['condition'] ) ? sanitize_key( $rule['condition'] ) : '';

		$date = $this->get_date( $value );

		if ( 'greater' === $condition && strpos( $value, ':' ) === false && $date && strtotime( $date ) ) {
			$date = gmdate( 'Y-m-d', strtotime( '+1 day', strtotime( $date ) ) );
		}

		unset( $condition, $value );

		return (string) $date;
	}

	/**
	 * Validate and format a date string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date   Input date string.
	 * @param string $format Target date format. Default 'Y-m-d H:i:s'.
	 * @return string|false Formatted date string or false on failure.
	 */
	protected function get_date( $date = '', $format = '' ) {

		if ( empty( $date ) || ! is_string( $date ) ) {
			$date = gmdate( 'Y-m-d H:i:s' );
		}

		$format = ( empty( $format ) || ! is_string( $format ) || trim( $format ) === '' ) ? 'Y-m-d H:i:s' : $format;

		if ( ! strtotime( $date ) ) {
			$date = $this->get_valid_date( $date );
		}

		if ( ! strtotime( $date ) ) {
			return false;
		}

		return gmdate( $format, strtotime( $date ) );
	}

	/**
	 * Attempt to parse alternative date formats by swapping common separators.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date Input date string.
	 * @return string Normalized date string or original input if unparseable.
	 */
	private function get_valid_date( $date = '' ) {

		if ( empty( $date ) || ! is_string( $date ) ) {
			return (string) $date;
		}

		$separator = '';

		$date_separators = array( '/', '-', '.' );

		foreach ( $date_separators as $sep ) {
			if ( strpos( $date, $sep ) !== false ) {
				$separator = $sep;
				break;
			}
		}

		if ( empty( $separator ) ) {
			return $date;
		}

		$date_separators = array_diff( $date_separators, array( $separator ) );

		$new_date = '';

		foreach ( $date_separators as $sep ) {
			$new_date = str_replace( $separator, $sep, $date );

			if ( strtotime( $new_date ) ) {
				break;
			} else {
				$new_date = '';
			}
		}

		unset( $date_separators );

		return empty( $new_date ) ? $date : $new_date;
	}

	/**
	 * Strip a prefix from the start of a string if present.
	 *
	 * @since 1.0.0
	 *
	 * @param string $str    Original string.
	 * @param string $prefix Prefix to remove.
	 * @return string String without prefix.
	 */
	protected function remove_prefix( $str = '', $prefix = '' ) {

		$str    = (string) $str;
		$prefix = (string) $prefix;

		if ( '' !== $prefix && substr( $str, 0, strlen( $prefix ) ) === $prefix ) {
			$str = substr( $str, strlen( $prefix ) );
		}

		return $str;
	}

	/**
	 * Execute user-defined PHP function on export data safely.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $data       Data to transform.
	 * @param bool   $is_enabled Whether user function execution is enabled.
	 * @param string $php_fun    Function name or snippet.
	 * @return mixed Processed data.
	 */
	protected function apply_user_function( $data = '', $is_enabled = false, $php_fun = '' ) {
		return SafeFunction::apply( $data, $is_enabled, $php_fun, 'export' );
	}

	/**
	 * Retrieve taxonomies registered for a given post type.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $export_type Post type slug(s). Default array('post').
	 * @param string $cats_type   Field type identifier. Default 'wpie_tax'.
	 * @param bool   $is_attr     Whether retrieving WooCommerce product attributes. Default false.
	 * @param array  $excludes    Array of taxonomy slugs to exclude.
	 * @return array Formatted array of taxonomy metadata.
	 */
	public function get_taxonomies_by_post_type( $export_type = array( 'post' ), $cats_type = 'wpie_tax', $is_attr = false, $excludes = array() ) {

		$post_taxonomies = array_diff_key( $this->taxonomies_by_object_type( $export_type, 'object' ), array_flip( array( 'post_format' ) ) );

		$taxonomies = array();

		if ( ! empty( $post_taxonomies ) ) {

			foreach ( $post_taxonomies as $slug => $tax ) {

				if ( ! empty( $excludes ) && in_array( $slug, $excludes, true ) ) {
					continue;
				}
				if ( ( ! $is_attr && strpos( $tax->name, 'pa_' ) !== 0 ) || ( $is_attr && strpos( $tax->name, 'pa_' ) === 0 ) ) {

					if ( 'product_type' === $tax->name ) {
						$tax_name = __( 'Product Type', 'wp-import-export-lite' );
					} elseif ( 'product_visibility' === $tax->name ) {
						$tax_name = __( 'Product Visibility', 'wp-import-export-lite' );
					} else {
						$tax_name = isset( $tax->label ) ? $tax->label : $tax->name;
					}
					if ( $is_attr ) {
						$tax_name = ( isset( $tax->labels ) && isset( $tax->labels->singular_name ) ) ? $tax->labels->singular_name : $tax_name;
					}
					$taxonomies[] = array(
						'name'         => ( trim( (string) $tax_name ) === '' ? $tax->name : $tax_name ),
						'type'         => $cats_type,
						'taxName'      => $tax->name,
						'isTax'        => true,
						'hierarchical' => $tax->hierarchical,
					);
					unset( $tax_name );
				}
			}
		}

		unset( $post_taxonomies );

		return $taxonomies;
	}

	/**
	 * Format a timestamp into date string or unix timestamp.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $date_type      Date output type: 'unix' or formatted string.
	 * @param int|string $timestamp      Unix timestamp.
	 * @param string     $date_format    Target PHP date format.
	 * @param string     $default_format Default date format fallback. Default 'Y-m-d H:i:s'.
	 * @return int|string Formatted date or timestamp.
	 */
	protected function get_date_field( $date_type = '', $timestamp = '', $date_format = '', $default_format = 'Y-m-d H:i:s' ) {

		if ( empty( $timestamp ) ) {
			return $timestamp;
		}

		$timestamp = (int) $timestamp;

		if ( 'unix' === $date_type ) {
			$date = $timestamp;
		} else {
			if ( empty( $date_format ) || ! is_string( $date_format ) ) {
				$date_format = $default_format;
			}

			$date = gmdate( $date_format, $timestamp );
			unset( $date_format );
		}

		return $date;
	}

	/**
	 * Check if a taxonomy term exists, filterable by add-ons.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string $term     The term name or ID.
	 * @param string     $taxonomy The taxonomy slug.
	 * @param int|null   $parent   Optional parent term ID.
	 * @return mixed Term ID/array if found, null/0 if not.
	 */
	protected function is_term_exists( $term, $taxonomy = '', $parent = null ) {
		return apply_filters( 'wpie_is_term_exists', term_exists( $term, $taxonomy, $parent ), $term, $taxonomy, $parent );
	}

}

<?php
/**
 * WPIE Export Controller Class
 *
 * Handles export templates, field retrieval, process initialization, and file conversion.
 *
 * @package    WPIE
 * @subpackage WPIE/Export
 */

namespace wpie\export;

use wpie\export\post\WPIE_Post;
use wpie\export\taxonomy\WPIE_Taxonomy;
use wpie\lib\xml\array2xml\ArrayToXml;
use WpieApp\Core\Helpers\Param;

defined( 'ABSPATH' ) || exit;

/**
 * Class WPIE_Export
 *
 * Manages the export life-cycle including configuration, execution, and formats.
 */
class WPIE_Export {

	/**
	 * Retrieve saved export templates list.
	 *
	 * @return void
	 */
	protected function get_template_list() {

		global $wpdb;

		$content_type = Param::postSanitized( 'content_type', 'key', 'post' );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `id`, `options` FROM {$wpdb->prefix}wpie_template WHERE `opration_type` = %s AND `opration` = 'export_template' ORDER BY `id` DESC",
				$content_type
			)
		);

		$data = array();

		if ( ! empty( $results ) ) {

			$count = 0;

			foreach ( $results as $template ) {

				$data[ $count ]['id'] = isset( $template->id ) ? (int) $template->id : 0;

				$options = isset( $template->options ) ? maybe_unserialize( $template->options ) : array();

				$data[ $count ]['name'] = isset( $options['template_name'] ) ? (string) $options['template_name'] : '';

				unset( $options );

				$count++;
			}

			unset( $count );
		}

		unset( $content_type, $results );

		wp_send_json(
			array(
				'status' => 'success',
				'data'   => $data,
			)
		);
	}

	/**
	 * Retrieve saved export settings list.
	 *
	 * @return void
	 */
	protected function get_export_settings_list() {

		global $wpdb;

		$content_type = Param::postSanitized( 'content_type', 'key', 'post' );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `id`, `options`, `create_date` FROM {$wpdb->prefix}wpie_template WHERE `opration_type` = %s AND `opration` = 'export' ORDER BY `id` DESC",
				$content_type
			)
		);

		$data = array();

		if ( ! empty( $results ) ) {

			$count = 0;

			foreach ( $results as $template ) {

				$data[ $count ]['id'] = isset( $template->id ) ? (int) $template->id : 0;

				$date = isset( $template->create_date ) ? (string) $template->create_date : '';

				$options = isset( $template->options ) ? maybe_unserialize( $template->options ) : array();

				$fileName = isset( $options['fileName'] ) ? (string) $options['fileName'] : '';

				$data[ $count ]['name'] = trim( $date . ' ' . $fileName );

				unset( $options, $date, $fileName );

				$count++;
			}

			unset( $count );
		}

		unset( $content_type, $results );

		wp_send_json(
			array(
				'status' => 'success',
				'data'   => $data,
			)
		);
	}

	/**
	 * Prepare export fields for the specified export type.
	 *
	 * @param string $export_type        Export type (e.g. post, taxonomy, etc.).
	 * @param string $taxonomy_type      Taxonomy slug if applicable.
	 * @param array  $attribute_taxonomy Attribute taxonomies list.
	 * @return array|\WP_Error
	 */
	public function prepare_fields( $export_type = '', $taxonomy_type = '', $attribute_taxonomy = array() ) {
		return $this->init_export(
			$export_type,
			'fields',
			array(
				'wpie_taxonomy_type'      => $taxonomy_type,
				'wpie_attribute_taxonomy' => $attribute_taxonomy,
			)
		);
	}

	/**
	 * Retrieve available field list via AJAX.
	 *
	 * @return void
	 */
	protected function get_field_list() {

		$export_type = Param::getSanitized( 'export_type', 'key', 'post' );

		$taxonomy_type = Param::getSanitized( 'taxonomy_type', 'key', '' );

		$raw_taxonomy       = Param::getSanitized( 'attribute_taxonomy', 'text', '' );
		$attribute_taxonomy = ! empty( $raw_taxonomy ) ? explode( ',', $raw_taxonomy ) : array();

		$fields = $this->prepare_fields( $export_type, $taxonomy_type, $attribute_taxonomy );

		unset( $export_type, $taxonomy_type, $raw_taxonomy, $attribute_taxonomy );

		if ( is_wp_error( $fields ) ) {
			wp_send_json(
				array(
					'status'  => 'error',
					'message' => $fields->get_error_message(),
				)
			);
		}

		wp_send_json(
			array(
				'status' => 'success',
				'fields' => $fields,
			)
		);
	}

	/**
	 * Initialize export process or engine for a given operation.
	 *
	 * @param string            $export_type Export type (post, taxonomies, comments, etc.).
	 * @param string            $opration    Operation mode (fields, export, count, preview).
	 * @param array|object|null $template    Template configuration options or record.
	 * @return mixed Array of data or \WP_Error on failure.
	 */
	public function init_export( $export_type = 'post', $opration = 'export', $template = null ) {

		$export_engine = '';

		if ( 'taxonomies' === $export_type ) {

			if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-taxonomy.php' ) ) {
				require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-taxonomy.php';
			}

			$export_engine = '\wpie\export\taxonomy\WPIE_Taxonomy';

		} elseif ( 'comments' === $export_type || 'product_reviews' === $export_type ) {

			if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-comment.php' ) ) {
				require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-comment.php';
			}

			$export_engine = '\wpie\export\comment\WPIE_Comment';

		} else {

			if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-post.php' ) ) {
				require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-post.php';
			}

			$export_engine = '\wpie\export\post\WPIE_Post';
		}

		$export_engine = apply_filters( 'wpie_export_engine_init', $export_engine, $export_type, $template );

		$export_process = array();

		if ( class_exists( $export_engine ) ) {

			$export_data = new $export_engine();

			if ( method_exists( $export_data, 'init_engine' ) ) {
				$export_process = $export_data->init_engine( $export_type, $opration, $template );
			}

			unset( $export_data );
		} else {
			return new \WP_Error(
				'wpie_import_error',
				sprintf(
					/* translators: %s: Class name */
					__( 'Class %s Not Exist', 'wp-import-export-lite' ),
					esc_html( $export_engine )
				)
			);
		}

		unset( $export_engine, $export_type );

		return $export_process;
	}

	/**
	 * Get default export types supported by the plugin.
	 *
	 * @return array
	 */
	private function get_default_export_type() {

		$types = array(
			'post'       => __( 'Post', 'wp-import-export-lite' ),
			'page'       => __( 'Page', 'wp-import-export-lite' ),
			'taxonomies' => __( 'Taxonomies | Categories | Tags', 'wp-import-export-lite' ),
			'users'      => __( 'Users', 'wp-import-export-lite' ),
			'comments'   => __( 'Comments', 'wp-import-export-lite' ),
		);

		if ( defined( 'WC_VERSION' ) || class_exists( '\WooCommerce' ) ) {
			$types['product']            = __( 'WooCommerce Products', 'wp-import-export-lite' );
			$types['product_reviews']    = __( 'Product Reviews', 'wp-import-export-lite' );
			$types['product_attributes'] = __( 'Product Attributes', 'wp-import-export-lite' );
			$types['shop_order']         = __( 'WooCommerce Orders', 'wp-import-export-lite' );
			$types['shop_coupon']        = __( 'WooCommerce Coupons', 'wp-import-export-lite' );
			$types['shop_customer']      = __( 'WooCommerce Customers', 'wp-import-export-lite' );
		}

		return $types;
	}

	/**
	 * Get all registered exportable types including public post types.
	 *
	 * @return array
	 */
	public function get_export_type() {

		$export_type = $this->get_default_export_type();

		$custom_export_type = get_post_types( array( '_builtin' => true ), 'objects' )
			+ get_post_types( array( '_builtin' => false, 'show_ui' => true ), 'objects' )
			+ get_post_types( array( '_builtin' => false, 'show_ui' => false ), 'objects' );

		if ( empty( $custom_export_type ) ) {
			return $export_type;
		}

		$hidden_posts = array(
			'attachment',
			'revision',
			'nav_menu_item',
			'shop_webhook',
			'import_users',
			'wp-types-group',
			'wp-types-user-group',
			'wp-types-term-group',
			'acf-field',
			'acf-field-group',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'wp_block',
			'user_request',
			'scheduled-action',
			'product_variation',
			'shop_order_refund',
		);

		foreach ( $custom_export_type as $key => $data ) {

			if ( in_array( $key, $hidden_posts, true ) ) {
				continue;
			}

			if ( isset( $export_type[ $key ] ) ) {
				continue;
			}

			$label = isset( $data->labels ) && isset( $data->labels->singular_name ) ? $data->labels->singular_name : '';

			if ( trim( (string) $label ) === '' ) {

				$label = isset( $data->labels ) && isset( $data->labels->name ) ? $data->labels->name : '';

				if ( trim( (string) $label ) === '' ) {
					continue;
				}
			}

			$export_type[ $key ] = $label;
		}

		unset( $custom_export_type, $hidden_posts );

		return $export_type;
	}

	/**
	 * Retrieve taxonomies available for export.
	 *
	 * @return array
	 */
	public function wpie_get_taxonomies() {

		$taxonomies = get_taxonomies( false, 'objects' );

		$data = array(
			'category'    => __( 'Post Categories', 'wp-import-export-lite' ),
			'product_cat' => __( 'Product Categories', 'wp-import-export-lite' ),
			'post_tag'    => __( 'Post Tags', 'wp-import-export-lite' ),
			'product_tag' => __( 'Product Tags', 'wp-import-export-lite' ),
		);

		$excludes = apply_filters( 'wpie_exclude_taxonomies', array( 'nav_menu', 'link_category', 'post_format' ) );

		if ( ! empty( $taxonomies ) ) {

			foreach ( $taxonomies as $key => $taxonomy ) {

				if ( in_array( $key, $excludes, true ) || isset( $data[ $key ] ) || ( isset( $taxonomy->public ) && false === $taxonomy->public ) ) {
					continue;
				}

				$data[ $key ] = ucwords( str_replace( '_', ' ', (string) $key ) );
			}
		}

		unset( $taxonomies, $excludes );

		return $data;
	}

	/**
	 * Retrieve WooCommerce attribute taxonomies list.
	 *
	 * @return array
	 */
	public function get_attribute_list() {

		global $wpdb;

		return $wpdb->get_results( "SELECT attribute_name, attribute_label FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name != '' ORDER BY attribute_name ASC;" );
	}

	/**
	 * Retrieve attribute taxonomies.
	 *
	 * @return array
	 */
	public function wpie_get_attribute_taxonomies() {

		$data       = array();
		$taxonomies = get_taxonomies( false, 'objects' );

		if ( ! empty( $taxonomies ) ) {

			foreach ( $taxonomies as $key => $taxonomy ) {

				if ( in_array( $key, array( 'nav_menu', 'link_category' ), true ) || isset( $data[ $key ] ) || ( isset( $taxonomy->show_in_nav_menus ) && false === $taxonomy->show_in_nav_menus ) ) {
					continue;
				}

				$data[ $key ] = ucwords( str_replace( '_', ' ', (string) $key ) );
			}
		}

		unset( $taxonomies );

		return $data;
	}

	/**
	 * Get export rule operators dictionary.
	 *
	 * @return void
	 */
	protected function get_export_rule() {

		$wpie_export_rules = array(
			'wpie_tax'              => array(
				'in'     => __( 'In', 'wp-import-export-lite' ),
				'not_in' => __( 'Not In', 'wp-import-export-lite' ),
			),
			'wpie_date'             => array(
				'equals'            => __( 'equals', 'wp-import-export-lite' ),
				'not_equals'        => __( "doesn't equal", 'wp-import-export-lite' ),
				'greater'           => __( 'newer than', 'wp-import-export-lite' ),
				'equals_or_greater' => __( 'equal to or newer than', 'wp-import-export-lite' ),
				'less'              => __( 'older than', 'wp-import-export-lite' ),
				'equals_or_less'    => __( 'equal to or older than', 'wp-import-export-lite' ),
				'contains'          => __( 'contains', 'wp-import-export-lite' ),
				'not_contains'      => __( "doesn't contain", 'wp-import-export-lite' ),
				'is_empty'          => __( 'is empty', 'wp-import-export-lite' ),
				'is_not_empty'      => __( 'is not empty', 'wp-import-export-lite' ),
			),
			'wpie_capabilities'     => array(
				'contains'     => __( 'contains', 'wp-import-export-lite' ),
				'not_contains' => __( "doesn't contain", 'wp-import-export-lite' ),
			),
			'wpie_user'             => array(
				'equals'       => __( 'equals', 'wp-import-export-lite' ),
				'not_equals'   => __( "doesn't equal", 'wp-import-export-lite' ),
				'contains'     => __( 'contains', 'wp-import-export-lite' ),
				'not_contains' => __( "doesn't contain", 'wp-import-export-lite' ),
				'is_empty'     => __( 'is empty', 'wp-import-export-lite' ),
				'is_not_empty' => __( 'is not empty', 'wp-import-export-lite' ),
			),
			'wpie_term_parent_slug' => array(
				'equals'            => __( 'equals', 'wp-import-export-lite' ),
				'not_equals'        => __( "doesn't equal", 'wp-import-export-lite' ),
				'greater'           => __( 'greater than', 'wp-import-export-lite' ),
				'equals_or_greater' => __( 'equal to or greater than', 'wp-import-export-lite' ),
				'less'              => __( 'less than', 'wp-import-export-lite' ),
				'equals_or_less'    => __( 'equal to or less than', 'wp-import-export-lite' ),
				'is_empty'          => __( 'is empty', 'wp-import-export-lite' ),
				'is_not_empty'      => __( 'is not empty', 'wp-import-export-lite' ),
			),
			'default'               => array(
				'equals'            => __( 'equals', 'wp-import-export-lite' ),
				'not_equals'        => __( "doesn't equal", 'wp-import-export-lite' ),
				'greater'           => __( 'greater than', 'wp-import-export-lite' ),
				'equals_or_greater' => __( 'equal to or greater than', 'wp-import-export-lite' ),
				'less'              => __( 'less than', 'wp-import-export-lite' ),
				'equals_or_less'    => __( 'equal to or less than', 'wp-import-export-lite' ),
				'contains'          => __( 'contains', 'wp-import-export-lite' ),
				'not_contains'      => __( "doesn't contain", 'wp-import-export-lite' ),
				'is_empty'          => __( 'is empty', 'wp-import-export-lite' ),
				'is_not_empty'      => __( 'is not empty', 'wp-import-export-lite' ),
				'in'                => __( 'In', 'wp-import-export-lite' ),
				'not_in'            => __( 'Not In', 'wp-import-export-lite' ),
			),
		);

		wp_send_json(
			array(
				'status'           => 'success',
				'wpie_export_rule' => apply_filters( 'wpie_export_rules', $wpie_export_rules ),
			)
		);
	}

	/**
	 * Save or update export template settings.
	 *
	 * @return void
	 */
	protected function save_template_data() {

		global $wpdb;

		$template_name = Param::postSanitized( 'template_name', 'text', '' );

		$template_id = Param::postSanitized( 'template_id', 'absint', 0 );

		if ( $template_id > 0 ) {

			$options = $wpdb->get_var( $wpdb->prepare( "SELECT `options` FROM {$wpdb->prefix}wpie_template WHERE `id` = %d", $template_id ) );

			if ( ! is_null( $options ) ) {

				$options = maybe_unserialize( $options );

				$new_options = Param::post();

				// Sanitize fileDir and fileName to prevent directory traversal sequences.
				if ( isset( $new_options['fileDir'] ) && is_string( $new_options['fileDir'] ) ) {
					$clean_fdir = str_replace( "\0", '', $new_options['fileDir'] );
					while ( false !== strpos( $clean_fdir, '..' ) ) {
						$clean_fdir = str_replace( '..', '', $clean_fdir );
					}
					$new_options['fileDir'] = sanitize_file_name( $clean_fdir );
				}

				if ( isset( $new_options['fileName'] ) && is_string( $new_options['fileName'] ) ) {
					$clean_fname = str_replace( "\0", '', $new_options['fileName'] );
					while ( false !== strpos( $clean_fname, '..' ) ) {
						$clean_fname = str_replace( '..', '', $clean_fname );
					}
					$new_options['fileName'] = sanitize_file_name( $clean_fname );
				}

				$new_options['template_name'] = isset( $options['template_name'] ) ? (string) $options['template_name'] : '';

				$new_values = array(
					'options' => maybe_serialize( $new_options ),
				);

				$wpdb->update( "{$wpdb->prefix}wpie_template", $new_values, array( 'id' => $template_id ) );

				wp_send_json(
					array(
						'status'  => 'success',
						'message' => __( 'Setting updated successfully', 'wp-import-export-lite' ),
					)
				);
			}
		}

		$is_exist = false;

		if ( ! empty( $template_name ) ) {

			$results = $wpdb->get_results( "SELECT `id`, `options` FROM {$wpdb->prefix}wpie_template WHERE `opration` = 'export_template'" );

			if ( ! empty( $results ) ) {

				foreach ( $results as $template ) {

					$options = isset( $template->options ) ? maybe_unserialize( $template->options ) : array();

					$temp_name = isset( $options['template_name'] ) ? (string) $options['template_name'] : '';

					if ( ! empty( $temp_name ) && $temp_name === $template_name ) {
						$is_exist = true;
						break;
					}

					unset( $options, $temp_name );
				}
			}

			unset( $results );
		}

		if ( false === $is_exist ) {

			$new_values = array(
				'opration'      => 'export_template',
				'opration_type' => Param::postSanitized( 'wpie_export_type', 'key', 'post' ),
			);

			$template_options = Param::post();

			if ( isset( $template_options['fileDir'] ) && is_string( $template_options['fileDir'] ) ) {
				$clean_fdir = str_replace( "\0", '', $template_options['fileDir'] );
				while ( false !== strpos( $clean_fdir, '..' ) ) {
					$clean_fdir = str_replace( '..', '', $clean_fdir );
				}
				$template_options['fileDir'] = sanitize_file_name( $clean_fdir );
			}

			if ( isset( $template_options['fileName'] ) && is_string( $template_options['fileName'] ) ) {
				$clean_fname = str_replace( "\0", '', $template_options['fileName'] );
				while ( false !== strpos( $clean_fname, '..' ) ) {
					$clean_fname = str_replace( '..', '', $clean_fname );
				}
				$template_options['fileName'] = sanitize_file_name( $clean_fname );
			}

			$new_values['options']     = maybe_serialize( $template_options );
			$new_values['create_date'] = current_time( 'mysql' );
			$new_values['unique_id']   = uniqid();

			$current_user = wp_get_current_user();

			if ( $current_user && isset( $current_user->user_login ) ) {
				$new_values['username'] = $current_user->user_login;
			}

			$wpdb->insert( "{$wpdb->prefix}wpie_template", $new_values );

			unset( $new_values, $current_user );

			$template_id = $wpdb->insert_id;

			if ( $template_id && absint( $template_id ) > 0 ) {
				wp_send_json(
					array(
						'status'      => 'success',
						'template_id' => (int) $template_id,
						'message'     => __( 'Setting Saved Successfully', 'wp-import-export-lite' ),
					)
				);
			} else {
				wp_send_json(
					array(
						'status'  => 'error',
						'message' => __( 'Fail to save Setting in database', 'wp-import-export-lite' ),
					)
				);
			}
		} else {
			wp_send_json(
				array(
					'status'  => 'error',
					'message' => __( 'Setting Name Already Exists', 'wp-import-export-lite' ),
				)
			);
		}
	}

	/**
	 * Retrieve template data by template ID via AJAX.
	 *
	 * @return void
	 */
	protected function get_template() {

		$template_id = Param::getSanitized( 'template_id', 'absint', 0 );

		if ( $template_id > 0 ) {

			$template_data = $this->get_template_by_id( $template_id );

			if ( false !== $template_data && isset( $template_data->options ) ) {

				$options = isset( $template_data->options ) ? wp_unslash( maybe_unserialize( $template_data->options ) ) : array();

				$template_data->fields_data = isset( $options['fields_data'] ) ? wp_unslash( $options['fields_data'] ) : array();

				wp_send_json(
					array(
						'status'  => 'success',
						'message' => 'success',
						'data'    => $options,
					)
				);
			}
		}

		wp_send_json(
			array(
				'status'  => 'error',
				'message' => __( 'Template Not Found', 'wp-import-export-lite' ),
			)
		);
	}

	/**
	 * Retrieve template row from database by ID.
	 *
	 * @param int $export_id Template ID.
	 * @return object|false
	 */
	protected function get_template_by_id( $export_id = 0 ) {

		if ( ! empty( $export_id ) && absint( $export_id ) > 0 ) {

			global $wpdb;

			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpie_template WHERE `id` = %d", $export_id ) );

			if ( ! empty( $results ) && isset( $results[0] ) ) {
				return $results[0];
			}
		}

		return false;
	}

	/**
	 * Update process execution status (bg / stop).
	 *
	 * @return void
	 */
	protected function update_process_status() {

		global $wpdb;

		$wpie_import_id = Param::getSanitized( 'wpie_export_id', 'absint', 0 );

		if ( $wpie_import_id > 0 ) {

			$process_status = Param::getSanitized( 'process_status', 'text', '' );

			$new_status = '';
			$message    = '';

			if ( 'bg' === $process_status ) {

				$new_status = 'background';
				$message    = __( 'Background Process Successfully Set', 'wp-import-export-lite' );

			} elseif ( 'stop' === $process_status ) {

				$new_status = 'stopped';
				$message    = __( 'Process Stopped Successfully', 'wp-import-export-lite' );
			}

			unset( $process_status );

			if ( '' !== $new_status ) {

				$final_data = array(
					'last_update_date' => current_time( 'mysql' ),
					'status'           => $new_status,
				);

				$wpdb->update( "{$wpdb->prefix}wpie_template", $final_data, array( 'id' => $wpie_import_id ) );

				unset( $final_data, $new_status );

				wp_send_json(
					array(
						'status'  => 'success',
						'message' => $message,
					)
				);
			} else {
				wp_send_json(
					array(
						'status'  => 'error',
						'message' => __( 'Empty Status', 'wp-import-export-lite' ),
					)
				);
			}
		} else {
			wp_send_json(
				array(
					'status'  => 'error',
					'message' => __( 'Template id not found', 'wp-import-export-lite' ),
				)
			);
		}
	}

	/**
	 * Generate safe directory name for export files.
	 *
	 * @param string $str       Base string.
	 * @param string $separator Separator type ('dash' or other).
	 * @param bool   $lowercase Convert to lowercase.
	 * @return string MD5 hash based safe directory name.
	 */
	protected function get_safe_dir_name( $str = '', $separator = 'dash', $lowercase = true ) {

		if ( 'dash' === $separator ) {
			$search  = '_';
			$replace = '-';
		} else {
			$search  = '-';
			$replace = '_';
		}

		$trans = array(
			'&\#\d+?;'       => '',
			'&\S+?;'         => '',
			'\s+'            => $replace,
			'[^a-z0-9\-\._]' => '',
			$search . '+'    => $replace,
			$search . '$'    => $replace,
			'^' . $search    => $replace,
			'\.+$'           => '',
		);

		$str = wp_strip_all_tags( (string) $str );

		foreach ( $trans as $key => $val ) {
			$str = preg_replace( '#' . $key . '#i', $val, $str );
		}

		if ( true === $lowercase ) {
			$str = strtolower( (string) $str );
		}

		unset( $search, $replace, $trans );

		return md5( trim( wp_unslash( (string) $str ) ) . time() );
	}

	/**
	 * Initialize a new export template record.
	 *
	 * @return void
	 */
	protected function init_new_export() {

		$export_id = $this->generate_template( Param::post(), 'export' );

		wp_send_json(
			array(
				'status'    => 'success',
				'export_id' => (int) $export_id,
			)
		);
	}

	/**
	 * Generate template entry in database.
	 *
	 * @param array  $options       Template configuration options.
	 * @param string $template_type Template operation type (export, etc.).
	 * @param string $status        Initial status.
	 * @return int Inserted template ID.
	 */
	protected function generate_template( $options = array(), $template_type = 'export', $status = 'processing' ) {

		$options['max_item_count'] = apply_filters( 'wpie_export_max_item_count', 1, $options );

		$file_data = $this->set_file_headers( $options );

		$options['fileName'] = isset( $file_data['filename'] ) ? $file_data['filename'] : '';

		$options['fileDir'] = isset( $file_data['filedir'] ) ? $file_data['filedir'] : '';

		$total = 0;

		if ( isset( $options['total'] ) ) {

			$total = absint( $options['total'] );

			unset( $options['total'] );
		}

		$wpie_export_type = ( isset( $options['wpie_export_type'] ) && is_string( $options['wpie_export_type'] ) && trim( $options['wpie_export_type'] ) !== '' ) ? wpie_sanitize_field( $options['wpie_export_type'] ) : 'post';

		$current_time = current_time( 'mysql' );

		$new_values = array(
			'opration'         => $template_type,
			'opration_type'    => $wpie_export_type,
			'process_lock'     => 0,
			'process_log'      => maybe_serialize( array( 'total' => $total ) ),
			'status'           => $status,
			'options'          => maybe_serialize( $options ),
			'create_date'      => $current_time,
			'last_update_date' => $current_time,
			'unique_id'        => uniqid(),
		);

		$current_user = wp_get_current_user();

		if ( $current_user && isset( $current_user->user_login ) ) {
			$new_values['username'] = $current_user->user_login;
		}

		global $wpdb;

		$wpdb->insert( "{$wpdb->prefix}wpie_template", $new_values );

		unset( $options, $file_data, $total, $wpie_export_type, $current_time, $new_values );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Prepare export file name, directory, and initialize BOM if requested.
	 *
	 * @param array $template_data Export template options.
	 * @return array Array with 'filename' and 'filedir'.
	 */
	private function set_file_headers( $template_data = array() ) {

		$wpie_export_type = ( isset( $template_data['wpie_export_type'] ) && is_string( $template_data['wpie_export_type'] ) && trim( $template_data['wpie_export_type'] ) !== '' )
			? array( wpie_sanitize_field( $template_data['wpie_export_type'] ) )
			: ( ( isset( $template_data['wpie_export_type'] ) && is_array( $template_data['wpie_export_type'] ) ) ? $template_data['wpie_export_type'] : array( 'post' ) );

		$export_type = $this->get_export_type();

		$temp_wpie_export_type = $wpie_export_type[0];

		unset( $wpie_export_type );

		$exported_data = ( isset( $export_type[ $temp_wpie_export_type ] ) && ! empty( $export_type[ $temp_wpie_export_type ] ) ) ? $export_type[ $temp_wpie_export_type ] : 'post';

		unset( $export_type );

		if ( 'taxonomies' === $temp_wpie_export_type ) {

			$taxonomy_data = $this->wpie_get_taxonomies();

			$tax_temp_data = ( isset( $template_data['wpie_taxonomy_type'] ) && is_string( $template_data['wpie_taxonomy_type'] ) && trim( $template_data['wpie_taxonomy_type'] ) !== '' ) ? wpie_sanitize_field( $template_data['wpie_taxonomy_type'] ) : '';

			if ( ! empty( $tax_temp_data ) && isset( $taxonomy_data[ $tax_temp_data ] ) && ! empty( $taxonomy_data[ $tax_temp_data ] ) ) {
				$exported_data = $taxonomy_data[ $tax_temp_data ];
			}

			unset( $tax_temp_data, $taxonomy_data );
		}

		unset( $temp_wpie_export_type );

		$filename = sanitize_file_name( ( isset( $template_data['wpie_export_file_name'] ) && is_string( $template_data['wpie_export_file_name'] ) && trim( $template_data['wpie_export_file_name'] ) !== '' ) ? $template_data['wpie_export_file_name'] : $exported_data . ' Export ' . gmdate( 'Y M d His' ) );

		$filename = apply_filters( 'wpie_export_file_name', $filename );

		$filename = pathinfo( $filename, PATHINFO_FILENAME ) . '.csv';

		$export_dir = $this->get_safe_dir_name( $filename );

		wp_mkdir_p( WPIE_UPLOAD_EXPORT_DIR . '/' . $export_dir );

		$filepath = WPIE_UPLOAD_EXPORT_DIR . '/' . $export_dir . '/' . $filename;

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct file operations required for export file initialization.
		$fh = @fopen( $filepath, 'w+' );

		$wpie_export_include_bom = ( isset( $template_data['wpie_export_include_bom'] ) && is_string( $template_data['wpie_export_include_bom'] ) && trim( $template_data['wpie_export_include_bom'] ) !== '' ) ? wpie_sanitize_field( $template_data['wpie_export_include_bom'] ) : '';

		if ( '1' === (string) $wpie_export_include_bom && is_resource( $fh ) ) {
			fwrite( $fh, "\xEF\xBB\xBF" );
		}

		if ( is_resource( $fh ) ) {
			fclose( $fh );
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		unset( $exported_data, $filepath, $fh, $template_data, $wpie_export_include_bom );

		return array(
			'filename' => $filename,
			'filedir'  => $export_dir,
		);
	}

	/**
	 * Run one batch iteration of export process.
	 *
	 * @return void
	 */
	protected function init_export_process() {

		$export_id = Param::getSanitized( 'export_id', 'absint', 0 );

		if ( $export_id > 0 ) {

			$template = $this->get_template_by_id( $export_id );

			if ( false !== $template ) {

				$export_type = isset( $template->opration_type ) ? $template->opration_type : 'post';

				$process_log = $this->init_export( $export_type, 'export', $template );

				if ( is_wp_error( $process_log ) ) {
					wp_send_json(
						array(
							'status'  => 'error',
							'message' => $process_log->get_error_message(),
						)
					);
				}

				$exported_records = isset( $process_log['exported'] ) ? (int) $process_log['exported'] : 0;
				$total            = isset( $process_log['total'] ) ? (int) $process_log['total'] : 0;

				$export_status = ( $exported_records >= $total ) ? 'completed' : 'processing';

				wp_send_json(
					array(
						'status'           => 'success',
						'exported_records' => $exported_records,
						'export_status'    => $export_status,
					)
				);
			} else {
				wp_send_json(
					array(
						'status'  => 'error',
						'message' => __( 'Template not found', 'wp-import-export-lite' ),
					)
				);
			}
		} else {
			wp_send_json(
				array(
					'status'  => 'error',
					'message' => __( 'Template not found', 'wp-import-export-lite' ),
				)
			);
		}
	}

	/**
	 * AJAX endpoint to trigger post-export file conversion (JSON, XML, Excel, ZIP).
	 *
	 * @return void
	 */
	protected function prepare_file() {

		$export_id = Param::getSanitized( 'export_id', 'absint', 0 );

		$process = $this->process_export_file( $export_id );

		if ( is_wp_error( $process ) ) {
			wp_send_json(
				array(
					'status'  => 'error',
					'message' => $process->get_error_message(),
				)
			);
		}

		wp_send_json(
			array(
				'status' => 'success',
			)
		);
	}

	/**
	 * Converts the generated CSV into target export format or package ZIP.
	 *
	 * @param int $export_id Template ID.
	 * @return true|\WP_Error
	 */
	protected function process_export_file( $export_id = 0 ) {

		$export_id = absint( $export_id );

		if ( $export_id > 0 ) {

			$template = $this->get_template_by_id( $export_id );

			if ( false !== $template ) {

				$options = isset( $template->options ) ? maybe_unserialize( $template->options ) : array();

				$raw_filename = isset( $options['fileName'] ) ? (string) $options['fileName'] : '';
				$fileDir      = isset( $options['fileDir'] ) ? (string) $options['fileDir'] : '';

				$validated_filename = $this->validate_export_filename( $raw_filename );
				if ( is_wp_error( $validated_filename ) ) {
					return $validated_filename;
				}

				$filename = $validated_filename;

				// Verify source export directory and file remain strictly inside WPIE_UPLOAD_EXPORT_DIR.
				$src_filepath = $this->validate_export_filepath( $fileDir, $filename );
				if ( is_wp_error( $src_filepath ) ) {
					return $src_filepath;
				}

				$is_package = isset( $options['is_package'] ) ? (int) $options['is_package'] : 0;

				$skip_empty_nodes = isset( $options['wpie_skip_empty_nodes'] ) && 1 === (int) $options['wpie_skip_empty_nodes'];

				if ( 'schedule_export' === $template->opration ) {
					$is_package = isset( $options['is_migrate_package'] ) ? (int) $options['is_migrate_package'] : 0;
				}

				$type = isset( $options['wpie_export_file_type'] ) && ! empty( $options['wpie_export_file_type'] ) ? strtolower( (string) $options['wpie_export_file_type'] ) : 'csv';

				$new_type = '';

				if ( 0 === $is_package ) {

					// Only convert if format is non-empty and NOT csv.
					if ( '' !== $type && 'csv' !== $type ) {

						$data = null;

						switch ( $type ) {

							case 'xml':
								$data = $this->csv2xml( $filename, $fileDir, $skip_empty_nodes );
								break;

							case 'json':
								$data = $this->csv2json( $filename, $fileDir );
								break;

							case 'xls':
							case 'xlsx':
							case 'ods':
								$data = $this->csv2excel( $filename, $fileDir, $type );
								break;

							default:
								$data = new \WP_Error( 'wpie_export_invalid_type', __( 'Invalid export file type.', 'wp-import-export-lite' ) );
								break;
						}

						if ( is_wp_error( $data ) ) {
							return $data;
						}

						if ( true === $data ) {
							$new_type = $type;
						}
					}
				} else {

					$is_success = $this->create_zip( $options );

					if ( is_wp_error( $is_success ) ) {
						return $is_success;
					}

					unset( $is_success );

					$new_type = 'zip';
				}

				if ( '' !== $new_type ) {

					$base_name          = pathinfo( $filename, PATHINFO_FILENAME );
					$new_filename       = $base_name . '.' . $new_type;
					$validated_new_name = $this->validate_export_filename( $new_filename, array( $new_type ) );

					if ( is_wp_error( $validated_new_name ) ) {
						return $validated_new_name;
					}

					$options['fileName'] = $validated_new_name;

					global $wpdb;

					$wpdb->update( "{$wpdb->prefix}wpie_template", array( 'options' => maybe_serialize( $options ) ), array( 'id' => $export_id ) );
				}

				$raw_extra_copy_path = isset( $options['extra_copy_path'] ) ? (string) $options['extra_copy_path'] : '';
				$raw_extra_copy_path = wp_unslash( $raw_extra_copy_path );

				if ( '' !== $raw_extra_copy_path && strpos( $raw_extra_copy_path, '..' ) === false && strpos( $raw_extra_copy_path, "\0" ) === false && strpos( $raw_extra_copy_path, ':' ) === false ) {

					$extra_copy_path = ltrim( trailingslashit( sanitize_text_field( $raw_extra_copy_path ) ), '/\\' );

					if ( '' !== $extra_copy_path && is_dir( WPIE_SITE_UPLOAD_DIR . '/' . $extra_copy_path ) ) {

						$current_export_file = isset( $options['fileName'] ) ? (string) $options['fileName'] : $filename;
						$validated_copy_name = $this->validate_export_filename( $current_export_file );

						if ( ! is_wp_error( $validated_copy_name ) ) {

							$src_filepath = $this->validate_export_filepath( $fileDir, $validated_copy_name );

							$base_upload_dir = realpath( WPIE_SITE_UPLOAD_DIR );
							$dest_dir        = realpath( WPIE_SITE_UPLOAD_DIR . '/' . $extra_copy_path );

							if ( ! is_wp_error( $src_filepath ) && file_exists( $src_filepath ) && $base_upload_dir && $dest_dir ) {

								$base_upload_dir_norm = trailingslashit( wp_normalize_path( $base_upload_dir ) );
								$dest_dir_norm        = trailingslashit( wp_normalize_path( $dest_dir ) );

								// Enforce that destination sits strictly inside WPIE_SITE_UPLOAD_DIR
								if ( strpos( $dest_dir_norm, $base_upload_dir_norm ) === 0 ) {
									@copy( $src_filepath, $dest_dir_norm . $validated_copy_name );
								}
							}
						}
					}
				}

				unset( $options, $filename, $fileDir, $is_package, $type, $new_type );
			} else {
				return new \WP_Error( 'woo_import_export_error', __( 'Template not found', 'wp-import-export-lite' ) );
			}

			unset( $template );
		} else {
			return new \WP_Error( 'woo_import_export_error', __( 'Template not found', 'wp-import-export-lite' ) );
		}

		return true;
	}

	/**
	 * Package export CSV and configuration into a ZIP file.
	 *
	 * @param array $options Template options.
	 * @return true|\WP_Error
	 */
	protected function create_zip( $options = array() ) {

		if ( ! class_exists( '\ZipArchive' ) ) {
			return new \WP_Error( 'wpie_zip_error', __( 'ZipArchive class not available on this server.', 'wp-import-export-lite' ) );
		}

		if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-import-config.php' ) ) {
			require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-import-config.php';
		}

		WPIE_Import_Config::generate( $options );

		$filename = isset( $options['fileName'] ) ? (string) $options['fileName'] : '';
		$fileDir  = isset( $options['fileDir'] ) ? (string) $options['fileDir'] : '';

		// Validate raw filename first
		$validated_filename = $this->validate_export_filename( $filename );
		if ( is_wp_error( $validated_filename ) ) {
			return $validated_filename;
		}

		// Force archive name to strictly have a .zip extension
		$zip_filename           = pathinfo( $validated_filename, PATHINFO_FILENAME ) . '.zip';
		$validated_zip_filename = $this->validate_export_filename( $zip_filename, array( 'zip' ) );
		if ( is_wp_error( $validated_zip_filename ) ) {
			return $validated_zip_filename;
		}

		// Validate and resolve destination zip path inside WPIE_UPLOAD_EXPORT_DIR
		$zipfile = $this->validate_export_filepath( $fileDir, $validated_zip_filename );
		if ( is_wp_error( $zipfile ) ) {
			return $zipfile;
		}

		// Validate source file path inside WPIE_UPLOAD_EXPORT_DIR
		$source_file = $this->validate_export_filepath( $fileDir, $validated_filename );
		if ( is_wp_error( $source_file ) ) {
			return $source_file;
		}

		$config_file = $this->validate_export_filepath( $fileDir, 'config.json' );
		if ( is_wp_error( $config_file ) ) {
			return $config_file;
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $zipfile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return new \WP_Error( 'woo_import_export_error', __( 'Could not open archive', 'wp-import-export-lite' ) );
		}

		unset( $zipfile );

		if ( file_exists( $source_file ) ) {
			$zip->addFile( $source_file, $validated_filename );
		}

		if ( file_exists( $config_file ) ) {
			$zip->addFile( $config_file, 'config.json' );
		}

		$zip->close();

		unset( $zip );

		return true;
	}

	/**
	 * Validate and sanitize export filename.
	 *
	 * Blocks traversal sequences, null bytes, executable or server-parsed extensions,
	 * and restricts export files to safe extensions.
	 *
	 * @since 3.9.34
	 *
	 * @param string $filename Raw filename.
	 * @param array  $extra_allowed Optional additional allowed extensions.
	 * @return string|\WP_Error Validated filename, or WP_Error on failure.
	 */
	protected function validate_export_filename( $filename = '', $extra_allowed = array() ) {

		if ( ! is_string( $filename ) || trim( $filename ) === '' ) {
			return new \WP_Error( 'wpie_export_invalid_filename', __( 'Export file name cannot be empty.', 'wp-import-export-lite' ) );
		}

		// Disallow null bytes.
		if ( strpos( $filename, "\0" ) !== false ) {
			return new \WP_Error( 'wpie_export_invalid_filename', __( 'Invalid export file name.', 'wp-import-export-lite' ) );
		}

		// Sanitize and isolate base filename to prevent path traversal in filename parameter.
		$clean_filename = wp_basename( sanitize_file_name( $filename ) );

		if ( empty( $clean_filename ) ) {
			return new \WP_Error( 'wpie_export_invalid_filename', __( 'Invalid export file name.', 'wp-import-export-lite' ) );
		}

		// Strictly block executable, script, or server-parsed extensions anywhere in the filename.
		if ( preg_match( '/\.(php[0-9]?|phtml|phar|inc|cgi|pl|py|sh|bash|exe|bat|cmd|htaccess|htpasswd)/i', $clean_filename ) ) {
			return new \WP_Error( 'wpie_export_disallowed_file', __( 'File type not allowed.', 'wp-import-export-lite' ) );
		}

		// Allow only safe, valid export file extensions.
		$ext                = strtolower( (string) pathinfo( $clean_filename, PATHINFO_EXTENSION ) );
		$default_allowed    = array( 'csv', 'tsv', 'xml', 'json', 'xls', 'xlsx', 'ods', 'txt', 'zip' );
		$allowed_extensions = apply_filters( 'wpie_export_allowed_file_extensions', array_unique( array_merge( $default_allowed, (array) $extra_allowed ) ) );

		if ( empty( $ext ) || ! in_array( $ext, $allowed_extensions, true ) ) {
			return new \WP_Error( 'wpie_export_invalid_extension', __( 'Invalid export file extension.', 'wp-import-export-lite' ) );
		}

		return $clean_filename;
	}

	/**
	 * Resolve and validate export directory and file path using realpath().
	 *
	 * Ensures that constructed directory and file paths remain strictly inside
	 * the intended base directory (WPIE_UPLOAD_EXPORT_DIR) to prevent directory traversal.
	 *
	 * @since 3.9.34
	 *
	 * @param string $filedir  Relative export directory.
	 * @param string $filename Validated export filename.
	 * @return string|\WP_Error Validated full file path, or WP_Error on failure.
	 */
	protected function validate_export_filepath( $filedir = '', $filename = '' ) {

		// Reject directory traversal sequences or null bytes in directory parameter.
		if ( ! is_string( $filedir ) || strpos( $filedir, '..' ) !== false || strpos( $filedir, "\0" ) !== false ) {
			return new \WP_Error( 'wpie_export_traversal_detected', __( 'Directory traversal detected in export directory.', 'wp-import-export-lite' ) );
		}

		// Reject directory traversal sequences or null bytes in filename parameter.
		if ( ! is_string( $filename ) || strpos( $filename, '..' ) !== false || strpos( $filename, "\0" ) !== false ) {
			return new \WP_Error( 'wpie_export_invalid_filename', __( 'Invalid export file name.', 'wp-import-export-lite' ) );
		}

		if ( ! is_dir( WPIE_UPLOAD_EXPORT_DIR ) ) {
			wp_mkdir_p( WPIE_UPLOAD_EXPORT_DIR );
		}

		$base_export_dir = realpath( WPIE_UPLOAD_EXPORT_DIR );
		if ( false === $base_export_dir ) {
			return new \WP_Error( 'wpie_export_dir_error', __( 'Export base directory could not be resolved.', 'wp-import-export-lite' ) );
		}

		$base_export_dir = trailingslashit( wp_normalize_path( $base_export_dir ) );

		// Construct and normalize target directory.
		$clean_dir  = ltrim( str_replace( array( '\\', '/' ), DIRECTORY_SEPARATOR, $filedir ), DIRECTORY_SEPARATOR );
		$target_dir = ! empty( $clean_dir ) ? WPIE_UPLOAD_EXPORT_DIR . DIRECTORY_SEPARATOR . $clean_dir : WPIE_UPLOAD_EXPORT_DIR;

		if ( ! is_dir( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$resolved_dir = realpath( $target_dir );
		if ( false === $resolved_dir ) {
			return new \WP_Error( 'wpie_export_dir_error', __( 'Export target directory could not be resolved.', 'wp-import-export-lite' ) );
		}

		$resolved_dir_norm = trailingslashit( wp_normalize_path( $resolved_dir ) );

		// Enforce containment: Target directory must reside within base export directory.
		if ( strpos( $resolved_dir_norm, $base_export_dir ) !== 0 ) {
			return new \WP_Error( 'wpie_export_traversal_detected', __( 'Directory traversal detected outside export directory.', 'wp-import-export-lite' ) );
		}

		$clean_filename = wp_basename( sanitize_file_name( $filename ) );
		$full_path      = $resolved_dir_norm . $clean_filename;

		if ( file_exists( $full_path ) ) {
			$real_filepath = realpath( $full_path );
			if ( false === $real_filepath || strpos( trailingslashit( wp_normalize_path( dirname( $real_filepath ) ) ), $base_export_dir ) !== 0 ) {
				return new \WP_Error( 'wpie_export_traversal_detected', __( 'Export file path is outside the allowed base directory.', 'wp-import-export-lite' ) );
			}
		}

		return $full_path;
	}

	/**
	 * Converts exported CSV file to Excel format (xls, xlsx, ods).
	 *
	 * @param string $filename CSV file name.
	 * @param string $fileDir  Export directory relative name.
	 * @param string $type     Target extension (xlsx, xls, ods).
	 * @return true|\WP_Error
	 */
	private function csv2excel( $filename = '', $fileDir = '', $type = 'xlsx' ) {

		$src_file = $this->validate_export_filepath( $fileDir, $filename );
		if ( is_wp_error( $src_file ) ) {
			return $src_file;
		}

		if ( ! file_exists( $src_file ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'File not found', 'wp-import-export-lite' ), 404 );
		}

		wpie_load_vendor_autoloader();

		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $src_file );

		unset( $src_file );

		if ( 'xls' === $type ) {
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls( $spreadsheet );
		} elseif ( 'ods' === $type ) {
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Ods( $spreadsheet );
		} else {
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
		}

		$target_filename       = pathinfo( $filename, PATHINFO_FILENAME ) . '.' . $type;
		$validated_target_name = $this->validate_export_filename( $target_filename, array( $type ) );
		if ( is_wp_error( $validated_target_name ) ) {
			return $validated_target_name;
		}

		$target_filepath = $this->validate_export_filepath( $fileDir, $validated_target_name );
		if ( is_wp_error( $target_filepath ) ) {
			return $target_filepath;
		}

		$writer->save( $target_filepath );

		$spreadsheet->disconnectWorksheets();

		unset( $writer, $spreadsheet );

		return true;
	}

	/**
	 * Converts exported CSV file into JSON format.
	 *
	 * @param string $filename CSV file name.
	 * @param string $fileDir  Export directory relative name.
	 * @return true|\WP_Error
	 */
	private function csv2json( $filename = '', $fileDir = '' ) {

		$src_file = $this->validate_export_filepath( $fileDir, $filename );
		if ( is_wp_error( $src_file ) ) {
			return $src_file;
		}

		if ( ! file_exists( $src_file ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'File not found', 'wp-import-export-lite' ), 404 );
		}

		$csv = array();

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct CSV stream operations.
		$handle = fopen( $src_file, 'rb' );

		if ( false !== $handle ) {

			$headersList = fgetcsv( $handle, 0, ',' );

			if ( is_array( $headersList ) ) {

				$headers = array();

				foreach ( $headersList as $header ) {

					if ( in_array( $header, $headers, true ) ) {
						$temp        = 1;
						$tempHeader  = $header;
						while ( in_array( $tempHeader, $headers, true ) ) {
							$tempHeader = $header . ' ' . $temp;
							$temp++;
						}
						$header = $tempHeader;
					}

					$headers[] = $header;
				}

				$headersCount = count( $headers );

				while ( ( $row = fgetcsv( $handle, 0, ',' ) ) !== false ) {

					if ( ! is_array( $row ) ) {
						continue;
					}

					$tempHeaders = $headers;

					if ( $headersCount < count( $row ) ) {
						$row = array_slice( $row, 0, $headersCount );
					}

					if ( $headersCount > count( $row ) ) {
						$tempHeaders = array_slice( $headers, 0, count( $row ) );
					}

					$csv[] = array_combine( $tempHeaders, $row );
				}
			}

			fclose( $handle );
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$target_filename       = pathinfo( $filename, PATHINFO_FILENAME ) . '.json';
		$validated_target_name = $this->validate_export_filename( $target_filename, array( 'json' ) );
		if ( is_wp_error( $validated_target_name ) ) {
			return $validated_target_name;
		}

		$target_filepath = $this->validate_export_filepath( $fileDir, $validated_target_name );
		if ( is_wp_error( $target_filepath ) ) {
			return $target_filepath;
		}

		file_put_contents( $target_filepath, wp_json_encode( $csv ) );

		unset( $src_file, $csv );

		return true;
	}

	/**
	 * Converts exported CSV file into XML format.
	 *
	 * @param string $filename         CSV file name.
	 * @param string $fileDir          Export directory relative name.
	 * @param bool   $skip_empty_nodes Whether to omit empty elements.
	 * @return true|\WP_Error
	 */
	private function csv2xml( $filename = '', $fileDir = '', $skip_empty_nodes = false ) {

		$src_file = $this->validate_export_filepath( $fileDir, $filename );
		if ( is_wp_error( $src_file ) ) {
			return $src_file;
		}

		if ( ! file_exists( $src_file ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'File not found', 'wp-import-export-lite' ), 404 );
		}

		if ( file_exists( WPIE_LIBRARIES_DIR . '/xml/class-wpie-array2xml.php' ) ) {
			require_once WPIE_LIBRARIES_DIR . '/xml/class-wpie-array2xml.php';
		}

		$converter = new \wpie\lib\xml\array2xml\ArrayToXml();

		$converter->create_root( 'wpiedata' );

		if ( $skip_empty_nodes ) {
			$converter->skip_empty();
		}

		$headers = array();

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct CSV stream operations.
		$wfp = fopen( $src_file, 'rb' );

		unset( $src_file );

		if ( false !== $wfp ) {

			while ( ( $keys = fgetcsv( $wfp, 0 ) ) !== false ) {

				if ( empty( $headers ) ) {

					foreach ( $keys as $key => $value ) {

						$value = trim( strtolower( (string) preg_replace( '/[^a-z0-9_]/i', '', (string) $value ) ) );

						if ( preg_match( '/^[0-9]/', $value ) ) {
							$value = 'el_' . trim( strtolower( $value ) );
						}

						$value = ( ! empty( $value ) ) ? $value : 'undefined' . $key;

						if ( isset( $headers[ $key ] ) ) {
							$key = $this->unique_array_key_name( (string) $key, $headers );
						}

						$headers[ $key ] = $value;
					}

					continue;
				}

				$fileData = array();

				foreach ( $keys as $key => $value ) {

					$header = isset( $headers[ $key ] ) ? $headers[ $key ] : '';

					if ( ! empty( $header ) ) {

						if ( isset( $fileData[ $header ] ) ) {
							$header = $this->unique_array_key_name( (string) $header, $fileData );
						}

						$fileData[ $header ] = $value;
					}

					unset( $header );
				}

				$converter->addNode( $converter->root, 'item', $fileData, 0 );

				unset( $fileData );
			}

			fclose( $wfp );
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$target_filename       = pathinfo( $filename, PATHINFO_FILENAME ) . '.xml';
		$validated_target_name = $this->validate_export_filename( $target_filename, array( 'xml' ) );
		if ( is_wp_error( $validated_target_name ) ) {
			return $validated_target_name;
		}

		$target_filepath = $this->validate_export_filepath( $fileDir, $validated_target_name );
		if ( is_wp_error( $target_filepath ) ) {
			return $target_filepath;
		}

		$converter->saveFile( $target_filepath );

		unset( $converter, $headers );

		return true;
	}

	/**
	 * AJAX endpoint to return total exportable item count.
	 *
	 * @return void
	 */
	protected function get_item_count() {

		$export_type = Param::postSanitized( 'wpie_export_type', 'key', 'post' );

		$count = $this->init_export( $export_type, 'count', Param::post() );

		unset( $export_type );

		wp_send_json(
			array(
				'status'       => 'success',
				'totalRecords' => $count,
			)
		);
	}

	/**
	 * AJAX endpoint to return export preview rows.
	 *
	 * @return void
	 */
	protected function get_preview() {

		$export_type = Param::postSanitized( 'wpie_export_type', 'key', 'post' );

		$post_data                                  = Param::post();
		$post_data['wpie_records_per_iteration'] = Param::postSanitized( 'length', 'absint', 10 );

		$preview_data = $this->init_export( $export_type, 'preview', $post_data );

		$total = Param::postSanitized( 'total', 'absint', 0 );

		unset( $export_type, $post_data );

		wp_send_json(
			array(
				'status'          => 'success',
				'data'            => $preview_data,
				'recordsTotal'    => $total,
				'recordsFiltered' => $total,
			)
		);
	}

	/**
	 * Ensure unique array key name by suffixing counter if necessary.
	 *
	 * @param string $key   Desired key.
	 * @param array  $array Associative array to check against.
	 * @return string Unique key.
	 */
	private function unique_array_key_name( $key = '', $array = array() ) {

		$count   = 1;
		$new_key = $key;

		while ( isset( $array[ $key ] ) ) {
			$key = $new_key . '_' . $count;
			$count++;
		}

		unset( $count, $new_key );

		return $key;
	}

	/**
	 * Destructor.
	 */
	public function __destruct() {
		foreach ( $this as $key => $value ) {
			unset( $this->$key );
		}
	}
}

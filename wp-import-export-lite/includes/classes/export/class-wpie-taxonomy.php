<?php
/**
 * WPIE Taxonomy Export Class
 *
 * Handles taxonomy term data extraction, custom term queries, term meta retrieval, and taxonomy field mappings.
 *
 * @package    WPIE
 * @subpackage WPIE/Export
 */

namespace wpie\export\taxonomy;

use WP_Term_Query;
use WP_Term;
use wpie\export\media\WPIE_Media;
use wpie\export\engine\WPIE_Export_Engine;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-engine.php' ) ) {
	require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-engine.php';
}

/**
 * Class WPIE_Taxonomy
 *
 * Manages term queries, term meta filtering, and field preparation for taxonomy-based exports.
 */
class WPIE_Taxonomy extends WPIE_Export_Engine {

	/**
	 * Term query WHERE clause string.
	 *
	 * @var string
	 */
	protected $item_where = '';

	/**
	 * Term query JOIN clauses.
	 *
	 * @var array
	 */
	protected $item_join = array();

	/**
	 * Media exporter instance cache.
	 *
	 * @var WPIE_Media|null
	 */
	private $media_engine = null;

	/**
	 * Get the media handler instance.
	 *
	 * @return WPIE_Media
	 */
	private function get_media_engine() {
		if ( null === $this->media_engine ) {
			if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-media.php' ) ) {
				require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-media.php';
			}
			$this->media_engine = new WPIE_Media();
		}
		return $this->media_engine;
	}

	/**
	 * Prepare exportable fields definition.
	 *
	 * @return array
	 */
	protected function get_fields() {

		$standard_fields = array(
			'title'     => __( 'Standard', 'wp-import-export-lite' ),
			'isDefault' => true,
			'data'      => array(
				array(
					'name'      => 'Term ID',
					'type'      => 'term_id',
					'isDefault' => true,
				),
				array(
					'name'      => 'Term Name',
					'type'      => 'term_name',
					'isDefault' => true,
				),
				array(
					'name'      => 'Term Slug',
					'type'      => 'term_slug',
					'isDefault' => true,
				),
				array(
					'name'      => 'Description',
					'type'      => 'term_description',
					'isDefault' => true,
				),
				array(
					'name'      => 'Parent ID',
					'type'      => 'term_parent_id',
					'isDefault' => true,
				),
				array(
					'name'      => 'Parent Name',
					'type'      => 'term_parent_name',
					'isDefault' => true,
				),
				array(
					'name'      => 'Parent Slug',
					'type'      => 'term_parent_slug',
					'isDefault' => true,
				),
				array(
					'name'      => 'Count',
					'type'      => 'term_posts_count',
					'isDefault' => true,
				),
			),
		);

		$image_fields = array(
			'title'      => __( 'Images', 'wp-import-export-lite' ),
			'isFiltered' => false,
			'data'       => array(
				array(
					'name' => 'Image URL',
					'type' => 'image_url',
				),
				array(
					'name' => 'Images Filename',
					'type' => 'image_filename',
				),
				array(
					'name' => 'Images Path',
					'type' => 'image_path',
				),
				array(
					'name' => 'Images ID',
					'type' => 'image_id',
				),
				array(
					'name' => 'Images Title',
					'type' => 'image_title',
				),
				array(
					'name' => 'Images Caption',
					'type' => 'image_caption',
				),
				array(
					'name' => 'Images Description',
					'type' => 'image_description',
				),
				array(
					'name' => 'Images Alt Text',
					'type' => 'image_alt',
				),
			),
		);

		$export_fields = array(
			'standard' => apply_filters( 'wpie_taxonomy_standard_fields', $standard_fields ),
			'meta'     => apply_filters( 'wpie_taxonomy_meta_fields', $this->get_meta_keys() ),
			'image'    => apply_filters( 'wpie_taxonomy_image_fields', $image_fields ),
		);

		$addon_class = apply_filters( 'wpie_prepare_taxonomy_fields', array(), $this->export_type );

		if ( ! empty( $addon_class ) ) {

			foreach ( $addon_class as $addon ) {

				if ( class_exists( $addon ) ) {

					$addon_data = new $addon();

					if ( method_exists( $addon_data, 'pre_process_fields' ) ) {
						$addon_data->pre_process_fields( $export_fields, $this->export_type, $this->export_taxonomy_type );
					}

					unset( $addon_data );
				}
			}
		}

		$meta_data = array();

		if ( ! empty( $export_fields['meta'] ) ) {
			foreach ( $export_fields['meta'] as $key ) {

				if ( empty( trim( (string) $key ) ) ) {
					continue;
				}

				$meta_data[] = apply_filters(
					'wpie_pre_item_meta',
					array(
						'name'    => $key,
						'type'    => 'wpie_cf',
						'metaKey' => $key,
					),
					$key
				);
			}
		}

		$export_fields['meta'] = array(
			'title' => __( 'Custom Fields', 'wp-import-export-lite' ),
			'data'  => $meta_data,
		);

		unset( $standard_fields, $image_fields, $addon_class, $meta_data );

		return apply_filters( 'wpie_export_fields', $export_fields, $this->export_type );
	}

	/**
	 * Retrieve distinct meta keys present across terms of the taxonomy.
	 *
	 * @return array
	 */
	private function get_meta_keys() {

		$this->opration = 'ids';

		$this->process_log = array(
			'exported' => 0,
			'total'    => 0,
		);

		$this->manage_rules();

		$taxonomies = $this->process_export();

		$meta = array();

		if ( ! empty( $taxonomies ) && is_array( $taxonomies ) ) {

			foreach ( $taxonomies as $taxonomy_term_id ) {

				$term_meta = get_term_meta( (int) $taxonomy_term_id, '' );

				if ( ! empty( $term_meta ) && is_array( $term_meta ) ) {

					foreach ( $term_meta as $_key => $_value ) {

						if ( ! in_array( $_key, $meta, true ) ) {
							$meta[] = $_key;
						}
					}
				}
				unset( $term_meta );
			}
		}

		unset( $taxonomies );

		return $meta;
	}

	/**
	 * Parse filter rule criteria into SQL expressions for terms.
	 *
	 * @param array $filter Filter criteria.
	 * @return void
	 */
	protected function parse_rule( $filter = array() ) {

		if ( isset( $filter['element'] ) ) {

			$filter['condition'] = isset( $filter['condition'] ) ? $filter['condition'] : '';

			$filter['value'] = isset( $filter['value'] ) ? $filter['value'] : '';

			$raw_clause       = isset( $filter['clause'] ) ? strtoupper( trim( (string) $filter['clause'] ) ) : '';
			$filter['clause'] = in_array( $raw_clause, array( 'AND', 'OR' ), true ) ? $raw_clause : '';
			unset( $raw_clause );

			$clause = $filter['clause'];

			$taxonomy_slug = isset( $this->template_options['wpie_taxonomy_type'] ) ? (string) $this->template_options['wpie_taxonomy_type'] : '';

			switch ( $filter['element'] ) {
				case 'term_id':
				case 'term_group':
					$this->item_where .= 't.' . $filter['element'] . ' ' . $this->add_filter_rule( $filter, true, false );
					break;
				case 'term_name':
				case 'term_slug':
					$col               = ( 'term_name' === $filter['element'] ) ? 'name' : 'slug';
					$this->item_where .= 't.' . $col . ' ' . $this->add_filter_rule( $filter, false, false );
					unset( $col );
					break;
				case 'term_parent_id':
					switch ( $filter['condition'] ) {
						case 'is_empty':
							$filter['value']     = 0;
							$filter['condition'] = 'equals';
							break;
						case 'is_not_empty':
							$filter['value']     = 0;
							$filter['condition'] = 'not_equals';
							break;
					}
					$this->item_where .= 'tt.parent ' . $this->add_filter_rule( $filter, false, false );
					break;
				case 'term_parent_name':

					switch ( $filter['condition'] ) {

						case 'contains':

							$result = new WP_Term_Query(
								array(
									'taxonomy'   => $taxonomy_slug,
									'name__like' => $filter['value'],
									'hide_empty' => false,
								)
							);

							$parent_terms = $result->get_terms();

							unset( $result );

							if ( ! empty( $parent_terms ) && is_array( $parent_terms ) ) {

								$parent_term_ids = array();

								foreach ( $parent_terms as $p_term ) {
									if ( isset( $p_term->term_id ) ) {
										$parent_term_ids[] = absint( $p_term->term_id );
									}
								}

								$parent_term_ids_str = implode( ',', array_unique( $parent_term_ids ) );

								$this->item_where .= "tt.parent IN ($parent_term_ids_str)";

								if ( ! empty( $clause ) ) {
									$this->item_where .= ' ' . $clause . ' ';
								}

								unset( $parent_term_ids, $parent_term_ids_str );
							}

							unset( $parent_terms );

							break;
						case 'not_contains':

							$result = new WP_Term_Query(
								array(
									'taxonomy'   => $taxonomy_slug,
									'name__like' => $filter['value'],
									'hide_empty' => false,
								)
							);

							// Correct order: fetch terms before unsetting $result!
							$parent_terms = $result->get_terms();

							unset( $result );

							if ( ! empty( $parent_terms ) && is_array( $parent_terms ) ) {

								$parent_term_ids = array();

								foreach ( $parent_terms as $p_term ) {
									if ( isset( $p_term->term_id ) ) {
										$parent_term_ids[] = absint( $p_term->term_id );
									}
								}

								$parent_term_ids_str = implode( ',', array_unique( $parent_term_ids ) );

								$this->item_where .= "tt.parent NOT IN ($parent_term_ids_str)";

								if ( ! empty( $clause ) ) {
									$this->item_where .= ' ' . $clause . ' ';
								}

								unset( $parent_term_ids, $parent_term_ids_str );
							}

							unset( $parent_terms );

							break;
						default:

							switch ( $filter['condition'] ) {
								case 'is_empty':
									$filter['value']     = 0;
									$filter['condition'] = 'equals';
									break;
								case 'is_not_empty':
									$filter['value']     = 0;
									$filter['condition'] = 'not_equals';
									break;
								default:
									$parent_term = get_term_by( 'name', $filter['value'], $taxonomy_slug );

									if ( $parent_term && isset( $parent_term->term_id ) ) {
										$filter['value'] = absint( $parent_term->term_id );
									}

									unset( $parent_term );
									break;
							}

							$this->item_where .= 'tt.parent ' . $this->add_filter_rule( $filter, false, false );
							break;
					}
					break;
				case 'term_parent_slug':

					switch ( $filter['condition'] ) {
						case 'is_empty':
							$filter['value']     = 0;
							$filter['condition'] = 'equals';
							break;
						case 'is_not_empty':
							$filter['value']     = 0;
							$filter['condition'] = 'not_equals';
							break;
						default:
							$parent_term = get_term_by( 'slug', $filter['value'], $taxonomy_slug );

							if ( $parent_term && isset( $parent_term->term_id ) ) {
								$filter['value'] = absint( $parent_term->term_id );
							}

							unset( $parent_term );
							break;
					}

					$this->item_where .= 'tt.parent ' . $this->add_filter_rule( $filter, false, false );

					break;

				case 'term_posts_count':
					$this->item_where .= 'tt.count ' . $this->add_filter_rule( $filter, false, false );
					break;

				case 'wpie_cf':

					global $wpdb;

					$meta_key = isset( $filter['metaKey'] ) ? sanitize_text_field( $filter['metaKey'] ) : '';

					if ( ! $this->is_valid_meta_key( $meta_key, 'taxonomy' ) ) {
						unset( $meta_key );
						break;
					}

					if ( 'is_empty' === $filter['condition'] ) {
						$this->item_join[] = $this->add_placeholder( $wpdb->prepare( " LEFT JOIN {$wpdb->termmeta} ON ({$wpdb->termmeta}.term_id = t.term_id AND {$wpdb->termmeta}.meta_key = %s) ", $meta_key ) );
						$this->item_where .= "{$wpdb->termmeta}.meta_id " . $this->add_filter_rule( $filter, false, false );
					} else {
						$this->item_join[] = " INNER JOIN {$wpdb->termmeta} ON ({$wpdb->termmeta}.term_id = t.term_id) ";
						$rule_sql          = $this->add_filter_rule( $filter, false, false );
						$this->item_where .= $this->add_placeholder( $wpdb->prepare( "{$wpdb->termmeta}.meta_key = %s AND ", $meta_key ) ) . "{$wpdb->termmeta}.meta_value " . $rule_sql;
						unset( $rule_sql );
					}
					unset( $meta_key );
					break;
				default:
					break;
			}
			unset( $clause );
		}
		unset( $filter );
	}

	/**
	 * Execute taxonomy term query for export.
	 *
	 * @return true|int|array
	 */
	protected function process_export() {

		$query = array(
			'taxonomy'   => isset( $this->template_options['wpie_taxonomy_type'] ) ? (string) $this->template_options['wpie_taxonomy_type'] : '',
			'orderby'    => 'id',
			'order'      => 'ASC',
			'hide_empty' => false,
			'offset'     => isset( $this->process_log['exported'] ) ? absint( $this->process_log['exported'] ) : 0,
			'fields'     => 'ids',
			'number'     => isset( $this->template_options['wpie_records_per_iteration'] ) ? absint( $this->template_options['wpie_records_per_iteration'] ) : 50,
		);

		if ( 'count' === $this->opration ) {
			$query['count']  = true;
			$query['offset'] = 0;
			$query['number'] = 0;
		}

		$query = apply_filters( 'wpie_pre_execute_taxonomy_query', $query );

		add_filter( 'terms_clauses', array( $this, 'terms_clauses' ), 10, 3 );

		$taxonomy_result = new WP_Term_Query( $query );

		unset( $query );

		$terms = $taxonomy_result->get_terms();

		$taxonomy_data = ( ! empty( $terms ) && is_array( $terms ) ) ? $terms : array();

		remove_filter( 'terms_clauses', array( $this, 'terms_clauses' ) );

		unset( $taxonomy_result, $terms );

		if ( 'count' === $this->opration ) {
			return count( $taxonomy_data );
		} elseif ( 'ids' === $this->opration ) {
			return $taxonomy_data;
		}

		$this->process_items( $taxonomy_data );

		unset( $taxonomy_data );

		return true;
	}

	/**
	 * Process row data for each exported term.
	 *
	 * @param array $taxonomy_data Array of term IDs.
	 * @return void
	 */
	protected function process_items( $taxonomy_data = array() ) {

		if ( ! empty( $taxonomy_data ) ) {

			$fields_data = ( isset( $this->template_options['fields_data'] ) && trim( (string) $this->template_options['fields_data'] ) !== '' )
				? explode( '~||~', wp_unslash( wpie_sanitize_field( (string) $this->template_options['fields_data'] ) ) )
				: array();

			$media = array();

			$taxonomy = isset( $this->template_options['wpie_taxonomy_type'] ) ? (string) $this->template_options['wpie_taxonomy_type'] : '';

			$site_date_format = get_option( 'date_format' );

			$temp_field_count = 0;

			global $wpie_export_id;

			$wpie_export_id = 0;

			$wpie_media = $this->get_media_engine();

			foreach ( $taxonomy_data as $item_term_id ) {

				$wpie_export_id = $item_term_id;

				$item = get_term_by( 'id', $item_term_id, $taxonomy );

				if ( $item instanceof WP_Term && ! empty( $fields_data ) ) {

					foreach ( $fields_data as $field ) {

						if ( empty( $field ) ) {
							continue;
						}

						$temp_field_count++;

						$new_field = explode( '|~|', (string) $field );

						$field_label = isset( $new_field[0] ) ? wpie_sanitize_field( $new_field[0] ) : '';

						$field_option = isset( $new_field[1] ) ? json_decode( wpie_sanitize_field( $new_field[1] ), true ) : array();

						if ( ! is_array( $field_option ) ) {
							$field_option = array();
						}

						$field_type = isset( $field_option['type'] ) ? wpie_sanitize_field( $field_option['type'] ) : '';

						$is_php = isset( $field_option['isPhp'] ) && 1 === (int) wpie_sanitize_field( $field_option['isPhp'] );

						$php_func = isset( $field_option['phpFun'] ) ? wpie_sanitize_field( $field_option['phpFun'] ) : '';

						$date_type = isset( $field_option['dateType'] ) ? wpie_sanitize_field( $field_option['dateType'] ) : '';

						$date_format = isset( $field_option['dateFormat'] ) ? wpie_sanitize_field( $field_option['dateFormat'] ) : '';

						$field_name = strtolower( (string) preg_replace( '/[^a-zA-Z0-9]+/', '', $field_type . $field_label ) ) . $this->get_unique_str() . '_' . $temp_field_count;

						if ( 0 === (int) $this->process_log['exported'] ) {

							$this->export_labels[ $field_name ] = $field_label;

							if ( $this->addons && is_array( $this->addons ) ) {

								foreach ( $this->addons as $addon ) {

									if ( method_exists( $addon, 'change_export_labels' ) ) {
										$addon->change_export_labels( $this->export_labels, $field_type, $field_name, $field_label, $field_option );
									}
								}
							}
						}

						if ( $this->addons && is_array( $this->addons ) ) {

							foreach ( $this->addons as $addon ) {

								if ( method_exists( $addon, 'pre_process_data' ) ) {
									$addon->pre_process_data( $this->export_labels, $field_type, $field_name, $field_label );
								}
							}
						}

						switch ( $field_type ) {

							case 'term_id':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_term_id', $this->apply_user_function( $item->term_id, $is_php, $php_func ), $item );
								break;
							case 'term_name':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_term_name', $this->apply_user_function( $item->name, $is_php, $php_func ), $item );
								break;

							case 'term_slug':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_term_slug', $this->apply_user_function( $item->slug, $is_php, $php_func ), $item );
								break;

							case 'term_description':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_term_description', $this->apply_user_function( $item->description, $is_php, $php_func ), $item );
								break;

							case 'term_parent_id':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_term_parent', $this->apply_user_function( $item->parent, $is_php, $php_func ), $item );
								break;

							case 'term_parent_name':

								$term_parent_name = '';

								if ( $item->parent ) {

									$parent_term = get_term( $item->parent, $item->taxonomy );

									if ( $parent_term && isset( $parent_term->name ) ) {
										$term_parent_name = $parent_term->name;
									}
									unset( $parent_term );
								}

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_term_parent_name', $this->apply_user_function( $term_parent_name, $is_php, $php_func ), $item );

								unset( $term_parent_name );

								break;

							case 'term_parent_slug':

								$term_parent_slug = '';

								if ( $item->parent ) {

									$parent_term = get_term( $item->parent, $item->taxonomy );

									if ( $parent_term && isset( $parent_term->slug ) ) {
										$term_parent_slug = $parent_term->slug;
									}
									unset( $parent_term );
								}

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_term_parent_slug', $this->apply_user_function( $term_parent_slug, $is_php, $php_func ), $item );

								unset( $term_parent_slug );

								break;

							case 'term_posts_count':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_term_count', $this->apply_user_function( $item->count, $is_php, $php_func ), $item );
								break;

							case 'wpie_cf':

								$meta_value = '';

								$meta_key = isset( $field_option['metaKey'] ) ? wpie_sanitize_field( $field_option['metaKey'] ) : '';

								if ( '' !== $meta_key ) {

									$term_metas = get_term_meta( $item->term_id, $meta_key );

									if ( ! empty( $term_metas ) && is_array( $term_metas ) ) {

										foreach ( $term_metas as $_value ) {
											if ( '' === $meta_value ) {
												$meta_value = maybe_serialize( $_value );
											} else {
												$meta_value .= '||' . maybe_serialize( $_value );
											}
										}
									}
									unset( $term_metas );
								}

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_term_meta', $this->apply_user_function( $meta_value, $is_php, $php_func ), $meta_key, $item );

								unset( $meta_value, $meta_key );
								break;

							case 'media':
							case 'image_id':
							case 'image_url':
							case 'image_filename':
							case 'image_path':
							case 'image_title':
							case 'image_caption':
							case 'image_description':
							case 'image_alt':

								if ( ! isset( $media[ $item->term_id ] ) ) {
									$media[ $item->term_id ] = $wpie_media->get_media( $item->term_id );
								}

								$image_media = '';

								if ( isset( $media[ $item->term_id ] ) ) {

									$image_data = $wpie_media->get_images( $item->term_id, $media[ $item->term_id ], $field_type, 'taxonomy' );

									if ( ! empty( $image_data ) ) {
										$image_media = implode( '||', $image_data );
									}

									unset( $image_data );
								}

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_taxonomy_image_' . $field_type, $this->apply_user_function( $image_media, $is_php, $php_func ), $item );

								unset( $image_media );

								break;

							default:
								$defaults = apply_filters( 'wpie_export_pre_term_default_field', '', $field_name, $field_option, $item );

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_term_default_field', $this->apply_user_function( $defaults, $is_php, $php_func ), $item );

								unset( $defaults );
								break;
						}

						if ( $this->addons && is_array( $this->addons ) ) {
							foreach ( $this->addons as $addon ) {
								if ( method_exists( $addon, 'process_addon_data' ) ) {
									$addon->process_addon_data( $this->export_data, $field_type, $field_name, $field_option, $item, $site_date_format );
								}
							}
						}

						unset( $new_field, $field_label, $field_option, $field_type, $is_php, $php_func, $date_type, $date_format, $field_name );
					}

					if ( $this->addons && is_array( $this->addons ) ) {

						foreach ( $this->addons as $addon ) {

							if ( method_exists( $addon, 'finalyze_export_process' ) ) {
								$addon->finalyze_export_process( $this->export_data, $this->has_multiple_rows );
							}
						}
					}

					$this->process_data();
				}
			}

			$wpie_export_id = 0;

			unset( $fields_data, $media );
		}
	}

	/**
	 * Append custom WHERE and JOIN clauses to terms query.
	 *
	 * @param array $data Clauses array.
	 * @return array
	 */
	public function terms_clauses( $data = array() ) {

		if ( ! empty( $this->item_join ) ) {
			$data['join'] .= implode( ' ', array_unique( $this->item_join ) );
		}

		if ( '' !== $this->item_where ) {
			$clean_where = preg_replace( array( '/\s+OR\s*$/i', '/\s+AND\s*$/i' ), '', trim( $this->item_where ) );
			$clean_where = preg_replace( array( '/^\s*OR\s+/i', '/^\s*AND\s+/i' ), '', $clean_where );
			if ( ! empty( $clean_where ) ) {
				$data['where'] .= " AND ( $clean_where )";
			}
			unset( $clean_where );
		}

		return $data;
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

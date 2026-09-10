<?php
/**
 * WPIE Comment Export Class
 *
 * Handles comment and review extraction, custom comment queries, comment meta, and field mappings.
 *
 * @package    WPIE
 * @subpackage WPIE/Export
 */

namespace wpie\export\comment;

use WP_Comment_Query;
use WP_Post;
use wpie\export\engine\WPIE_Export_Engine;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-engine.php' ) ) {
	require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-engine.php';
}

/**
 * Class WPIE_Comment
 *
 * Manages comment queries, meta filtering, and field preparation for comment exports.
 */
class WPIE_Comment extends WPIE_Export_Engine {

	/**
	 * Comment query WHERE clause string.
	 *
	 * @var string
	 */
	protected $item_where = '';

	/**
	 * Comment query JOIN clauses.
	 *
	 * @var array
	 */
	protected $item_join = array();

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
					'name'      => 'ID',
					'type'      => 'comment_ID',
					'isDefault' => true,
				),
				array(
					'name'      => 'Parent Post ID',
					'type'      => 'comment_post_ID',
					'isDefault' => true,
				),
				array(
					'name'       => 'Parent Post title',
					'type'       => 'comment_post_title',
					'isDefault'  => true,
					'isFiltered' => false,
				),
				array(
					'name'       => 'Parent Post slug',
					'type'       => 'comment_post_slug',
					'isDefault'  => true,
					'isFiltered' => false,
				),
				array(
					'name'      => 'Author',
					'type'      => 'comment_author',
					'isDefault' => true,
				),
				array(
					'name'      => 'Author Email',
					'type'      => 'comment_author_email',
					'isDefault' => true,
				),
				array(
					'name'      => 'Author URL',
					'type'      => 'comment_author_url',
					'isDefault' => true,
				),
				array(
					'name'      => 'Author IP',
					'type'      => 'comment_author_IP',
					'isDefault' => true,
				),
				array(
					'name'      => 'Date',
					'type'      => 'comment_date',
					'isDefault' => true,
					'isDate'    => true,
				),
				array(
					'name'      => 'Date GMT',
					'type'      => 'comment_date_gmt',
					'isDefault' => true,
					'isDate'    => true,
				),
				array(
					'name'      => 'Content',
					'type'      => 'comment_content',
					'isDefault' => true,
				),
				array(
					'name'      => 'Karma',
					'type'      => 'comment_karma',
					'isDefault' => true,
				),
				array(
					'name'      => 'Approved',
					'type'      => 'comment_approved',
					'isDefault' => true,
				),
				array(
					'name'      => 'Agent',
					'type'      => 'comment_agent',
					'isDefault' => true,
				),
				array(
					'name'      => 'Type',
					'type'      => 'comment_type',
					'isDefault' => true,
				),
				array(
					'name'      => 'Comment Parent ID',
					'type'      => 'comment_parent',
					'isDefault' => true,
				),
				array(
					'name'       => 'Comment Parent Content',
					'type'       => 'comment_parent_content',
					'isDefault'  => true,
					'isFiltered' => false,
				),
				array(
					'name'      => 'User ID',
					'type'      => 'user_id',
					'isDefault' => true,
				),
			),
		);

		$export_fields = array(
			'standard' => apply_filters( 'wpie_comment_standard_fields', $standard_fields ),
			'meta'     => apply_filters( 'wpie_comment_meta_fields', $this->get_meta_keys() ),
		);

		$addon_class = apply_filters( 'wpie_prepare_comment_fields', array(), $this->export_type );

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

		$is_product_reviews = is_array( $this->export_type ) && in_array( 'product_reviews', $this->export_type, true );

		if ( ! empty( $export_fields['meta'] ) ) {
			foreach ( $export_fields['meta'] as $key ) {

				if ( empty( trim( (string) $key ) ) ) {
					continue;
				}

				$meta_data[] = apply_filters(
					'wpie_pre_item_meta',
					array(
						'name'      => esc_html( $key ),
						'type'      => 'wpie_cf',
						'metaKey'   => $key,
						'isDefault' => $is_product_reviews,
					),
					$key
				);
			}
		}

		$export_fields['meta'] = array(
			'title'     => __( 'Custom Fields', 'wp-import-export-lite' ),
			'isDefault' => $is_product_reviews,
			'data'      => $meta_data,
		);

		unset( $standard_fields, $addon_class, $meta_data );

		return apply_filters( 'wpie_export_fields', $export_fields, $this->export_type );
	}

	/**
	 * Retrieve distinct meta keys from comment records.
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

		$comments = $this->process_export();

		$meta = array();

		if ( ! empty( $comments ) && is_array( $comments ) ) {

			foreach ( $comments as $comment ) {

				$comment_id = is_object( $comment ) ? $comment->comment_ID : $comment;

				$comment_meta = get_comment_meta( (int) $comment_id, '' );

				if ( ! empty( $comment_meta ) && is_array( $comment_meta ) ) {

					foreach ( $comment_meta as $_key => $_value ) {

						if ( ! in_array( $_key, $meta, true ) ) {
							$meta[] = $_key;
						}
					}
				}
				unset( $comment_meta );
			}
		}

		unset( $comments );

		return $meta;
	}

	/**
	 * Parse comment filter rules into SQL WHERE / JOIN fragments.
	 *
	 * @param array $filter Filter criteria.
	 * @return void
	 */
	protected function parse_rule( $filter = array() ) {

		if ( isset( $filter['element'] ) ) {

			global $wpdb;

			$filter['condition'] = isset( $filter['condition'] ) ? $filter['condition'] : '';

			$filter['value'] = isset( $filter['value'] ) ? $filter['value'] : '';

			$raw_clause       = isset( $filter['clause'] ) ? strtoupper( trim( (string) $filter['clause'] ) ) : '';
			$filter['clause'] = in_array( $raw_clause, array( 'AND', 'OR' ), true ) ? $raw_clause : '';
			unset( $raw_clause );

			switch ( $filter['element'] ) {
				case 'comment_ID':
				case 'comment_post_ID':
				case 'comment_karma':
				case 'user_id':
				case 'comment_parent':
					$col               = sanitize_key( $filter['element'] );
					$this->item_where .= "{$wpdb->comments}.{$col} " . $this->add_filter_rule( $filter, true, false );
					unset( $col );
					break;
				case 'comment_date':
				case 'comment_date_gmt':
					$col               = sanitize_key( $filter['element'] );
					$this->item_where .= "{$wpdb->comments}.{$col} " . $this->add_filter_rule( $filter, false, false );
					unset( $col );
					break;
				case 'comment_author':
				case 'comment_author_email':
				case 'comment_author_url':
				case 'comment_author_IP':
				case 'comment_approved':
				case 'comment_agent':
				case 'comment_type':
				case 'comment_content':
					$col               = sanitize_key( $filter['element'] );
					$this->item_where .= "{$wpdb->comments}.{$col} " . $this->add_filter_rule( $filter, false, false );
					unset( $col );
					break;

				case 'wpie_cf':

					$meta_key = isset( $filter['metaKey'] ) ? sanitize_text_field( $filter['metaKey'] ) : '';

					if ( ! $this->is_valid_meta_key( $meta_key, 'comment' ) ) {
						unset( $meta_key );
						break;
					}

					if ( 'is_empty' === $filter['condition'] ) {
						$this->item_join[] = $this->add_placeholder( $wpdb->prepare( " LEFT JOIN {$wpdb->commentmeta} ON ({$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID AND {$wpdb->commentmeta}.meta_key = %s) ", $meta_key ) );
						$this->item_where .= "{$wpdb->commentmeta}.meta_id " . $this->add_filter_rule( $filter, false, false );
					} else {
						$this->item_join[] = " INNER JOIN {$wpdb->commentmeta} ON ({$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID) ";
						$rule_sql          = $this->add_filter_rule( $filter, false, false );
						$this->item_where .= $this->add_placeholder( $wpdb->prepare( "{$wpdb->commentmeta}.meta_key = %s AND ", $meta_key ) ) . "{$wpdb->commentmeta}.meta_value " . $rule_sql;
						unset( $rule_sql );
					}

					unset( $meta_key );
					break;
				default:
					break;
			}
		}

		unset( $filter );
	}

	/**
	 * Execute comment query for export batch.
	 *
	 * @return true|int|array
	 */
	protected function process_export() {

		$query = array(
			'orderby' => 'comment_ID',
			'order'   => 'ASC',
			'offset'  => isset( $this->process_log['exported'] ) ? absint( $this->process_log['exported'] ) : 0,
			'number'  => isset( $this->template_options['wpie_records_per_iteration'] ) ? absint( $this->template_options['wpie_records_per_iteration'] ) : 50,
		);

		if ( is_array( $this->export_type ) && in_array( 'product_reviews', $this->export_type, true ) ) {
			$query['post_type'] = array( 'product' );
			$query['type']      = 'review';
		}

		if ( 'count' === $this->opration ) {
			$query['count']  = true;
			$query['fields'] = 'ids';
			$query['offset'] = 0;
			$query['number'] = 0;
		} elseif ( 'ids' === $this->opration ) {
			$query['fields'] = 'ids';
		}

		$query = apply_filters( 'wpie_pre_execute_comment_query', $query );

		add_action( 'comments_clauses', array( $this, 'comments_clauses' ), 10, 1 );

		$comment_result = new WP_Comment_Query( $query );

		$comment_data = $comment_result->get_comments();

		unset( $comment_result, $query );

		remove_action( 'comments_clauses', array( $this, 'comments_clauses' ) );

		if ( 'count' === $this->opration ) {
			return is_array( $comment_data ) ? count( $comment_data ) : (int) $comment_data;
		} elseif ( 'ids' === $this->opration ) {
			return is_array( $comment_data ) ? $comment_data : array();
		}

		if ( ! empty( $comment_data ) && is_array( $comment_data ) ) {

			$fields_data = ( isset( $this->template_options['fields_data'] ) && trim( (string) $this->template_options['fields_data'] ) !== '' )
				? explode( '~||~', wp_unslash( wpie_sanitize_field( (string) $this->template_options['fields_data'] ) ) )
				: array();

			$site_date_format = get_option( 'date_format' );

			$temp_field_count = 0;

			global $wpie_export_id;

			$wpie_export_id = 0;

			foreach ( $comment_data as $item ) {

				if ( ! is_object( $item ) ) {
					continue;
				}

				$wpie_export_id = isset( $item->comment_ID ) ? (int) $item->comment_ID : 0;

				if ( ! empty( $fields_data ) ) {

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
						}

						switch ( $field_type ) {

							case 'comment_ID':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_id', $this->apply_user_function( $item->comment_ID, $is_php, $php_func ), $item );
								break;
							case 'comment_post_ID':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_post_id', $this->apply_user_function( $item->comment_post_ID, $is_php, $php_func ), $item );
								break;
							case 'comment_post_title':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_post_title', $this->apply_user_function( get_the_title( $item->comment_post_ID ), $is_php, $php_func ), $item );
								break;
							case 'comment_post_slug':
								$post      = get_post( $item->comment_post_ID );
								$post_slug = ( $post instanceof WP_Post ) ? $post->post_name : '';
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_post_slug', $this->apply_user_function( $post_slug, $is_php, $php_func ), $item );
								unset( $post, $post_slug );
								break;
							case 'comment_author':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_author', $this->apply_user_function( $item->comment_author, $is_php, $php_func ), $item );
								break;
							case 'comment_author_email':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_author_email', $this->apply_user_function( $item->comment_author_email, $is_php, $php_func ), $item );
								break;
							case 'comment_author_url':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_author_url', $this->apply_user_function( $item->comment_author_url, $is_php, $php_func ), $item );
								break;
							case 'comment_author_IP':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_author_ip', $this->apply_user_function( $item->comment_author_IP, $is_php, $php_func ), $item );
								break;
							case 'comment_karma':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_karma', $this->apply_user_function( $item->comment_karma, $is_php, $php_func ), $item );
								break;
							case 'comment_content':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_content', $this->apply_user_function( $item->comment_content, $is_php, $php_func ), $item );
								break;
							case 'comment_date':
								$comment_time = ! empty( $item->comment_date ) ? strtotime( (string) $item->comment_date ) : 0;
								$comment_date = $this->get_date_field( $date_type, $comment_time, $date_format );
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_date', $this->apply_user_function( $comment_date, $is_php, $php_func ), $item );
								break;
							case 'comment_date_gmt':
								$comment_time_gmt = ! empty( $item->comment_date_gmt ) ? strtotime( (string) $item->comment_date_gmt ) : 0;
								$comment_date_gmt = $this->get_date_field( $date_type, $comment_time_gmt, $date_format );
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_date', $this->apply_user_function( $comment_date_gmt, $is_php, $php_func ), $item );
								break;
							case 'comment_approved':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_approved', $this->apply_user_function( $item->comment_approved, $is_php, $php_func ), $item );
								break;
							case 'comment_agent':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_agent', $this->apply_user_function( $item->comment_agent, $is_php, $php_func ), $item );
								break;
							case 'comment_type':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_type', $this->apply_user_function( $item->comment_type, $is_php, $php_func ), $item );
								break;
							case 'comment_parent':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_parent', $this->apply_user_function( $item->comment_parent, $is_php, $php_func ), $item );
								break;
							case 'comment_parent_content':
								$parent_comment = ( (int) $item->comment_parent > 0 ) ? get_comment( $item->comment_parent ) : '';
								$parent_content = ( ! empty( $parent_comment ) && isset( $parent_comment->comment_content ) ) ? $parent_comment->comment_content : '';
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_parent_content', $this->apply_user_function( $parent_content, $is_php, $php_func ), $item );
								break;
							case 'user_id':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_user_id', $this->apply_user_function( $item->user_id, $is_php, $php_func ), $item );
								break;

							case 'wpie_cf':
								$meta_value = '';
								$meta_key   = isset( $field_option['metaKey'] ) ? wpie_sanitize_field( $field_option['metaKey'] ) : '';

								if ( '' !== $meta_key ) {
									$metas = get_comment_meta( $item->comment_ID, $meta_key );

									if ( ! empty( $metas ) && is_array( $metas ) ) {
										foreach ( $metas as $_value ) {
											if ( '' === $meta_value ) {
												$meta_value = maybe_serialize( $_value );
											} else {
												$meta_value .= '||' . maybe_serialize( $_value );
											}
										}
									}
									unset( $metas );
								}

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_meta', $this->apply_user_function( $meta_value, $is_php, $php_func ), $meta_key, $item );
								unset( $meta_value, $meta_key );
								break;

							default:
								$defaults = apply_filters( 'wpie_export_pre_comment_default_field', '', $field_name, $field_option, $item );
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_comment_default_field', $this->apply_user_function( $defaults, $is_php, $php_func ), $item );
								unset( $defaults );
								break;
						}

						unset( $new_field, $field_label, $field_option, $field_type, $is_php, $php_func, $date_type, $date_format );
					}

					$this->process_data();
				}
			}

			$wpie_export_id = 0;

			unset( $fields_data, $site_date_format );
		}

		unset( $comment_data );

		return true;
	}

	/**
	 * Append custom WHERE and JOIN clauses to comment query.
	 *
	 * @param array $data Clauses array.
	 * @return array
	 */
	public function comments_clauses( $data = array() ) {

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

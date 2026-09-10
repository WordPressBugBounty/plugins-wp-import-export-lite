<?php
/**
 * WPIE Post Export Class
 *
 * Handles post data extraction, querying, term hierarchy resolution, and post-type field mappings.
 *
 * @package    WPIE
 * @subpackage WPIE/Export
 */

namespace wpie\export\post;

use WP_User_Query;
use WP_Query;
use WP_Post;
use wpie\export\media\WPIE_Media;
use wpie\export\engine\WPIE_Export_Engine;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-engine.php' ) ) {
	require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-engine.php';
}

/**
 * Class WPIE_Post
 *
 * Manages post queries, meta filtering, and field preparation for post-based exports.
 */
class WPIE_Post extends WPIE_Export_Engine {

	/**
	 * Post query where clauses.
	 *
	 * @var array
	 */
	protected $item_where = array();

	/**
	 * Post query join clauses.
	 *
	 * @var array
	 */
	protected $item_join = array();

	/**
	 * User query where clause.
	 *
	 * @var string
	 */
	protected $user_where = '';

	/**
	 * User query join clauses.
	 *
	 * @var array
	 */
	protected $user_join = array();

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
	 * @param array $exclude_meta Meta keys to exclude.
	 * @return array
	 */
	protected function get_fields( $exclude_meta = array() ) {

		$standard_fields = array(
			'title'     => __( 'Standard', 'wp-import-export-lite' ),
			'isDefault' => true,
			'data'      => array(
				array(
					'name'      => 'ID',
					'type'      => 'id',
					'isDefault' => true,
				),
				array(
					'name'      => 'Title',
					'type'      => 'title',
					'isDefault' => true,
				),
				array(
					'name'      => 'Content',
					'type'      => 'content',
					'isDefault' => true,
				),
				array(
					'name'      => 'Excerpt',
					'type'      => 'excerpt',
					'isDefault' => true,
				),
				array(
					'name'      => 'Date',
					'type'      => 'date',
					'isDate'    => true,
					'isDefault' => true,
				),
				array(
					'name'      => 'Post Type',
					'type'      => 'post_type',
					'isDefault' => true,
				),
				array(
					'name'      => 'Permalink',
					'type'      => 'permalink',
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

		$attachment_fields = array(
			'title'      => __( 'Attachments', 'wp-import-export-lite' ),
			'isFiltered' => false,
			'data'       => array(
				array(
					'name' => 'Attachment URL',
					'type' => 'attachment_url',
				),
				array(
					'name' => 'Attachments Filename',
					'type' => 'attachment_filename',
				),
				array(
					'name' => 'Attachments Path',
					'type' => 'attachment_path',
				),
				array(
					'name' => 'Attachments ID',
					'type' => 'attachment_id',
				),
				array(
					'name' => 'Attachments Title',
					'type' => 'attachment_title',
				),
				array(
					'name' => 'Attachments Caption',
					'type' => 'attachment_caption',
				),
				array(
					'name' => 'Attachments Description',
					'type' => 'attachment_description',
				),
				array(
					'name' => 'Attachments Alt Text',
					'type' => 'attachment_alt',
				),
			),
		);

		$author_fields = array(
			'title'      => __( 'Author', 'wp-import-export-lite' ),
			'isExported' => false,
			'data'       => array(
				array(
					'name' => 'User ID',
					'type' => 'user_ID',
				),
				array(
					'name' => 'User Login',
					'type' => 'user_login',
				),
				array(
					'name' => 'Nicename',
					'type' => 'user_nicename',
				),
				array(
					'name' => 'Email',
					'type' => 'user_email',
				),
				array(
					'name'   => 'Date Registered (Y-m-d H:i:s)',
					'type'   => 'user_registered',
					'isDate' => true,
				),
				array(
					'name' => 'Display Name',
					'type' => 'display_name',
				),
				array(
					'name' => 'First Name',
					'type' => 'wpie_cf_first_name',
				),
				array(
					'name' => 'Last Name',
					'type' => 'wpie_cf_last_name',
				),
				array(
					'name' => 'Nickname',
					'type' => 'nickname',
				),
				array(
					'name' => 'User Description',
					'type' => 'description',
				),
				array(
					'name'         => 'User Role',
					'type'         => 'wp_capabilities',
					'isCapability' => true,
				),
			),
		);

		$other_fields = array(
			'title' => __( 'Other', 'wp-import-export-lite' ),
			'data'  => array(
				array(
					'name' => 'Status',
					'type' => 'status',
				),
				array(
					'name' => 'Author ID',
					'type' => 'author_id',
				),
				array(
					'name' => 'Author Username',
					'type' => 'author_username',
				),
				array(
					'name' => 'Author Email',
					'type' => 'author_email',
				),
				array(
					'name' => 'Author First Name',
					'type' => 'wpie_cf_first_name',
				),
				array(
					'name' => 'Author Last Name',
					'type' => 'wpie_cf_last_name',
				),
				array(
					'name' => 'Slug',
					'type' => 'slug',
				),
				array(
					'name' => 'Format',
					'type' => 'format',
				),
				array(
					'name' => 'Post Password',
					'type' => 'post_password',
				),
				array(
					'name' => 'Template',
					'type' => 'template',
				),
				array(
					'name' => 'Parent',
					'type' => 'parent',
				),
				array(
					'name' => 'Parent Slug',
					'type' => 'parent_slug',
				),
				array(
					'name' => 'Order',
					'type' => 'order',
				),
				array(
					'name' => 'Comment Status',
					'type' => 'comment_status',
				),
				array(
					'name' => 'Ping Status',
					'type' => 'ping_status',
				),
				array(
					'name'   => 'Post Modified Date',
					'type'   => 'post_modified',
					'isDate' => true,
				),
			),
		);

		$excludes = apply_filters( 'wpie_exclude_post_taxonomy_fields', array() );

		$taxonomy_data = $this->get_taxonomies_by_post_type( $this->export_type, 'wpie_tax', false, $excludes );

		$taxonomy = array(
			'title' => __( 'Taxonomy', 'wp-import-export-lite' ),
			'data'  => $taxonomy_data,
		);

		unset( $taxonomy_data );

		$metas = $this->get_meta_keys( $this->export_type );

		$export_fields = array(
			'standard'   => $standard_fields,
			'meta'       => $metas,
			'taxonomy'   => $taxonomy,
			'image'      => $image_fields,
			'attachment' => $attachment_fields,
			'author'     => $author_fields,
			'other'      => $other_fields,
		);

		$addon_class = apply_filters( 'wpie_prepare_post_fields', array(), $this->export_type );

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
						'name'    => esc_html( $key ),
						'type'    => 'wpie_cf',
						'metaKey' => $key,
					),
					$key
				);
			}
		}

		unset( $metas );

		$meta_fields = array(
			'title' => __( 'Custom Fields', 'wp-import-export-lite' ),
			'data'  => $meta_data,
		);

		$export_fields['meta'] = $meta_fields;

		unset( $standard_fields, $meta_fields, $taxonomy, $image_fields, $attachment_fields, $author_fields, $other_fields, $addon_class, $meta_data );

		return apply_filters( 'wpie_export_fields', $export_fields, $this->export_type );
	}

	/**
	 * Retrieve distinct meta keys for post types.
	 *
	 * @param array|string $export_type Post type(s).
	 * @return array
	 */
	protected function get_meta_keys( $export_type = array() ) {

		global $wpdb;

		$export_type = is_array( $export_type ) ? $export_type : ( empty( $export_type ) ? array() : array( $export_type ) );
		$export_type = array_filter( array_map( 'sanitize_key', $export_type ) );

		if ( empty( $export_type ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $export_type ), '%s' ) );

		$limit = apply_filters( 'wpie_postmeta_form_limit', 1000 );

		$query_params = array_merge( $export_type, array( '_edit%', '_oembed_%', $limit ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT {$wpdb->postmeta}.meta_key
				FROM {$wpdb->postmeta}, {$wpdb->posts}
				WHERE {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
				AND {$wpdb->posts}.post_type IN ($placeholders)
				AND {$wpdb->postmeta}.meta_key NOT LIKE %s
				AND {$wpdb->postmeta}.meta_key NOT LIKE %s
				ORDER BY {$wpdb->postmeta}.meta_key
				LIMIT %d",
				$query_params
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		unset( $export_type, $placeholders, $limit, $query_params );

		return $keys;
	}

	/**
	 * Parse filter rules into SQL WHERE and JOIN expressions.
	 *
	 * @param array $filter Filter criteria.
	 * @return void
	 */
	protected function parse_rule( $filter = array() ) {

		if ( isset( $filter['element'] ) ) {

			$filter['element'] = $this->get_valid_element( $filter['element'] );

			$filter['condition'] = isset( $filter['condition'] ) ? $filter['condition'] : '';

			$filter['value'] = isset( $filter['value'] ) ? $filter['value'] : '';

			$raw_clause        = isset( $filter['clause'] ) ? strtoupper( trim( (string) $filter['clause'] ) ) : '';
			$filter['clause']  = in_array( $raw_clause, array( 'AND', 'OR' ), true ) ? $raw_clause : '';
			unset( $raw_clause );

			global $wpdb;

			switch ( $filter['element'] ) {
				case 'ID':
				case 'id':
				case 'post_parent':
				case 'post_author':
					$element             = ( 'id' === $filter['element'] ) ? 'ID' : $filter['element'];
					$this->item_where[]  = " {$wpdb->posts}.{$element} " . $this->add_filter_rule( $filter, true, false );
					unset( $element );
					break;
				case 'post_status':
				case 'post_title':
				case 'post_content':
				case 'post_excerpt':
				case 'guid':
				case 'post_name':
				case 'menu_order':
				case 'post_password':
					$this->item_where[] = " {$wpdb->posts}.{$filter['element']} " . $this->add_filter_rule( $filter, false, false );
					break;
				case 'post_date':
				case 'post_modified':
					$filter['value']    = $this->add_date_filter_rule( $filter );
					$this->item_where[] = " {$wpdb->posts}.{$filter['element']} " . $this->add_filter_rule( $filter, false, false );
					break;
				case 'user_ID':
					$filter['element']  = 'post_author';
					$this->item_where[] = " {$wpdb->posts}.post_author " . $this->add_filter_rule( $filter, true, false );
					break;
				case 'user_login':
				case 'user_nicename':
				case 'user_email':
				case 'user_registered':
				case 'display_name':
				case 'first_name':
				case 'last_name':
				case 'nickname':
				case 'description':
				case 'wp_capabilities':

					$this->user_where = ' AND (';

					$this->user_join = array();

					$meta_query = false;

					switch ( $filter['element'] ) {
						case 'wp_capabilities':

							$meta_query = true;

							$cap_key = $wpdb->prefix . 'capabilities';

							$this->user_join[] = " INNER JOIN {$wpdb->usermeta} ON ({$wpdb->usermeta}.user_id = {$wpdb->users}.ID) ";

							$rule_sql = $this->add_filter_rule( $filter, false, false );

							$this->user_where .= $this->add_placeholder( $wpdb->prepare( "{$wpdb->usermeta}.meta_key = %s AND ", $cap_key ) ) . "{$wpdb->usermeta}.meta_value " . $rule_sql;

							unset( $cap_key, $rule_sql );

							break;
						case 'user_registered':
							$filter['value']   = $this->add_date_filter_rule( $filter );
							$this->user_where .= "{$wpdb->users}.user_registered " . $this->add_filter_rule( $filter, false, false );
							break;
						case 'user_login':
						case 'user_nicename':
						case 'user_email':
						case 'display_name':
						case 'description':
							$user_col          = sanitize_key( $filter['element'] );
							$this->user_where .= "{$wpdb->users}.{$user_col} " . $this->add_filter_rule( $filter, false, false );
							unset( $user_col );
							break;
						default:

							if ( 0 === strpos( $filter['element'], 'wpie_cf_' ) || 'nickname' === $filter['element'] ) {

								$meta_key = str_replace( 'wpie_cf_', '', $filter['element'] );

								if ( ! $this->is_valid_meta_key( $meta_key, 'user' ) ) {
									unset( $meta_key );
									break;
								}

								if ( 'is_empty' === $filter['condition'] ) {

									$this->user_join[] = $this->add_placeholder( $wpdb->prepare( " LEFT JOIN {$wpdb->usermeta} ON ({$wpdb->usermeta}.user_id = {$wpdb->users}.ID AND {$wpdb->usermeta}.meta_key = %s) ", $meta_key ) );

									$this->user_where .= "{$wpdb->usermeta}.umeta_id " . $this->add_filter_rule( $filter, false, false );
								} else {
									$this->user_join[] = " INNER JOIN {$wpdb->usermeta} ON ({$wpdb->usermeta}.user_id = {$wpdb->users}.ID) ";

									$rule_sql = $this->add_filter_rule( $filter, false, false );

									$this->user_where .= $this->add_placeholder( $wpdb->prepare( "{$wpdb->usermeta}.meta_key = %s AND ", $meta_key ) ) . "{$wpdb->usermeta}.meta_value " . $rule_sql;

									unset( $rule_sql );
								}
								unset( $meta_key );
							}
							break;
					}

					$user_clean_where = preg_replace( array( '/\s+OR\s*$/i', '/\s+AND\s*$/i' ), '', $this->user_where );
					$this->user_where = $user_clean_where . ( $meta_query ? " ) GROUP BY {$wpdb->users}.ID" : ')' );
					unset( $user_clean_where, $meta_query );

					add_action( 'pre_user_query', array( $this, 'pre_user_query' ), 10, 1 );

					$user_query = new WP_User_Query(
						array(
							'orderby' => 'ID',
							'order'   => 'ASC',
							'fields'  => 'ids',
						)
					);

					remove_action( 'pre_user_query', array( $this, 'pre_user_query' ) );

					$user_list = array();

					if ( ! empty( $user_query->results ) ) {
						foreach ( $user_query->results as $userID ) {
							$user_list[] = absint( $userID );
						}
					}

					if ( ! empty( $user_list ) ) {

						$users_str = implode( ',', $user_list );

						$user_data_where = "{$wpdb->posts}.post_author IN ($users_str)";

						$clause = ( ! empty( $filter['clause'] ) && in_array( strtoupper( trim( $filter['clause'] ) ), array( 'AND', 'OR' ), true ) ) ? strtoupper( trim( $filter['clause'] ) ) : '';

						if ( ! empty( $clause ) ) {
							$user_data_where .= ' ' . $clause . ' ';
						}

						$this->item_where[] = $user_data_where;

						unset( $users_str, $user_data_where, $clause );
					}

					unset( $user_list );

					break;
				case 'wpie_tax':

					if ( ! empty( $filter['value'] ) ) {

						$tx_name = isset( $filter['taxName'] ) ? sanitize_key( $filter['taxName'] ) : 'category';

						if ( ! taxonomy_exists( $tx_name ) ) {
							break;
						}

						$terms = array();

						$txs = explode( ',', (string) $filter['value'] );

						if ( ! empty( $txs ) ) {
							foreach ( $txs as $tx ) {
								$tx = trim( (string) $tx );
								if ( is_numeric( $tx ) ) {
									$terms[] = absint( $tx );
								} else {
									$term = $this->is_term_exists( $tx, $tx_name );

									if ( is_array( $term ) && isset( $term['term_taxonomy_id'] ) ) {
										$terms[] = absint( $term['term_taxonomy_id'] );
									}
									unset( $term );
								}
							}
						}

						unset( $txs, $tx_name );

						if ( ! empty( $terms ) ) {

							$terms     = array_unique( $terms );
							$terms_str = implode( ',', $terms );
							$clause    = ( ! empty( $filter['clause'] ) && in_array( strtoupper( trim( (string) $filter['clause'] ) ), array( 'AND', 'OR' ), true ) ) ? strtoupper( trim( (string) $filter['clause'] ) ) : '';

							switch ( $filter['condition'] ) {
								case 'in':

									$table_alias = 'tr' . time() . uniqid();

									$this->item_join[] = " LEFT JOIN {$wpdb->term_relationships} AS $table_alias ON ({$wpdb->posts}.ID = $table_alias.object_id)";

									$term_tx_data = "$table_alias.term_taxonomy_id IN ($terms_str)";

									if ( ! empty( $clause ) ) {
										$term_tx_data .= ' ' . $clause . ' ';
									}

									$this->item_where[] = $term_tx_data;

									unset( $table_alias, $term_tx_data );

									break;
								case 'not_in':

									$term_not_in_tx_data = "{$wpdb->posts}.ID NOT IN (
										SELECT object_id
										FROM {$wpdb->term_relationships}
										WHERE term_taxonomy_id IN ($terms_str)
									)";

									if ( ! empty( $clause ) ) {
										$term_not_in_tx_data .= ' ' . $clause . ' ';
									}

									$this->item_where[] = $term_not_in_tx_data;

									unset( $term_not_in_tx_data );

									break;
								default:
									break;
							}

							unset( $terms_str, $clause );
						}

						unset( $terms );
					}
					break;
				case 'wpie_cf':

					$meta_key = isset( $filter['metaKey'] ) ? sanitize_text_field( $filter['metaKey'] ) : '';

					if ( ! $this->is_valid_meta_key( $meta_key, 'post' ) ) {
						unset( $meta_key );
						break;
					}

					$table_alias = ( count( $this->item_join ) > 0 ) ? 'meta' . count( $this->item_join ) : 'meta';

					if ( 'is_empty' === $filter['condition'] ) {

						$this->item_join[] = $this->add_placeholder( $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS $table_alias ON ($table_alias.post_id = {$wpdb->posts}.ID AND $table_alias.meta_key = %s) ", $meta_key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

						$this->item_where[] = "$table_alias.meta_id " . $this->add_filter_rule( $filter, false, false );
					} else {

						if ( in_array( $meta_key, array( '_completed_date' ), true ) ) {
							$filter['value'] = $this->add_date_filter_rule( $filter );
						}

						$this->item_join[] = " INNER JOIN {$wpdb->postmeta} AS $table_alias ON ({$wpdb->posts}.ID = $table_alias.post_id) ";

						$rule_sql = $this->add_filter_rule( $filter, false, $table_alias );

						$this->item_where[] = $this->add_placeholder( $wpdb->prepare( "$table_alias.meta_key = %s AND ", $meta_key ) ) . "$table_alias.meta_value " . $rule_sql; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

						unset( $rule_sql );
					}
					unset( $meta_key, $table_alias );
					break;
				default:
					break;
			}

			$filter_data = apply_filters( 'wpie_apply_post_filter', array(), $this->export_type, $filter );

			if ( ! empty( $filter_data ) ) {
				$item_where = isset( $filter_data['item_where'] ) && ! empty( $filter_data['item_where'] ) ? $filter_data['item_where'] : array();
				if ( ! empty( $item_where ) ) {
					$this->item_where = array_merge( $this->item_where, $item_where );
				}
				$item_join = isset( $filter_data['item_join'] ) && ! empty( $filter_data['item_join'] ) ? $filter_data['item_join'] : array();
				if ( ! empty( $item_join ) ) {
					$this->item_join = array_merge( $this->item_join, $item_join );
				}

				unset( $item_where, $item_join );
			}
		}
		unset( $filter );
	}

	/**
	 * Execute export query and batch iteration.
	 *
	 * @return true|int|array
	 */
	protected function process_export() {

		$query = array(
			'post_type'      => $this->export_type,
			'post_status'    => array_keys( get_post_stati() ),
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'offset'         => isset( $this->process_log['exported'] ) ? absint( $this->process_log['exported'] ) : 0,
			'posts_per_page' => isset( $this->template_options['wpie_records_per_iteration'] ) ? absint( $this->template_options['wpie_records_per_iteration'] ) : 50,
		);

		if ( 'count' === $this->opration || 'ids' === $this->opration ) {
			$query['offset']         = 0;
			$query['posts_per_page'] = -1;
		}

		$query = apply_filters( 'wpie_pre_execute_post_query', $query );

		add_filter( 'posts_where', array( $this, 'posts_where' ), 10, 1 );

		add_filter( 'posts_join', array( $this, 'posts_join' ), 10, 1 );

		add_filter( 'posts_groupby', array( $this, 'posts_groupby' ), 10, 1 );

		$post_result = new WP_Query( $query );

		unset( $query );

		remove_filter( 'posts_where', array( $this, 'posts_where' ), 10 );

		remove_filter( 'posts_join', array( $this, 'posts_join' ), 10 );

		remove_filter( 'posts_groupby', array( $this, 'posts_groupby' ), 10 );

		wp_reset_postdata();

		if ( 'count' === $this->opration ) {
			return ( isset( $post_result->posts ) && is_array( $post_result->posts ) ) ? count( $post_result->posts ) : 0;
		} elseif ( 'ids' === $this->opration ) {
			return $post_result->posts;
		}

		$post_data = $post_result->posts;

		unset( $post_result );

		$this->process_items( $post_data );

		return true;
	}

	/**
	 * Process rows for posts in batch.
	 *
	 * @param array $post_data Array of post IDs.
	 * @return void
	 */
	protected function process_items( $post_data = array() ) {

		$fields_data = ( isset( $this->template_options['fields_data'] ) && trim( (string) $this->template_options['fields_data'] ) !== '' )
			? explode( '~||~', wp_unslash( (string) $this->template_options['fields_data'] ) )
			: array();

		$site_date_format = get_option( 'date_format' );

		$users = array();
		$media = array();

		if ( $this->addons && is_array( $this->addons ) ) {

			foreach ( $this->addons as $addon ) {

				if ( method_exists( $addon, 'init_export_process' ) ) {
					$addon->init_export_process( $post_data, $this->template_options, $this->export_id );
				}
			}
		}

		if ( ! empty( $post_data ) ) {

			$temp_field_count = 0;

			global $wpie_export_id;

			$wpie_export_id = 0;

			$wpie_media = $this->get_media_engine();

			foreach ( $post_data as $post_id ) {

				$wpie_export_id = $post_id;

				$item = get_post( $post_id );

				// Guard: skip if not a valid WP_Post object (deleted, invalid ID).
				if ( ! $item instanceof WP_Post ) {
					continue;
				}

				$this->export_data = array();

				$this->has_multiple_rows = false;

				if ( ! empty( $fields_data ) ) {

					foreach ( $fields_data as $field ) {

						if ( empty( $field ) ) {
							continue;
						}

						$temp_field_count++;

						$new_field = explode( '|~|', (string) $field );

						$field_label = isset( $new_field[0] ) ? $new_field[0] : '';

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

							case 'id':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_id', $this->apply_user_function( $item->ID, $is_php, $php_func ), $item );
								break;

							case 'permalink':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_permalink', $this->apply_user_function( get_permalink( $item ), $is_php, $php_func ), $item );
								break;

							case 'post_type':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_type', $this->apply_user_function( $item->post_type, $is_php, $php_func ), $item );
								break;

							case 'title':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_title', $this->apply_user_function( $item->post_title, $is_php, $php_func ), $item );
								break;

							case 'content':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_content', $this->apply_user_function( $item->post_content, $is_php, $php_func ), $item );
								break;

							case 'date':
								$post_date                        = $this->get_date_field( $date_type, get_post_time( 'U', true, $item->ID ), $date_format );
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_date', $this->apply_user_function( $post_date, $is_php, $php_func ), $item );
								unset( $post_date );
								break;

							case 'post_modified':
								$post_modified_date               = $this->get_date_field( $date_type, get_post_modified_time( 'U', true, $item->ID ), $date_format );
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_modified_time', $this->apply_user_function( $post_modified_date, $is_php, $php_func ), $item );
								unset( $post_modified_date );
								break;

							case 'parent':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_parent', $this->apply_user_function( $item->post_parent, $is_php, $php_func ), $item );
								break;

							case 'parent_slug':

								$parent_slug = '';

								if ( 0 !== (int) $item->post_parent ) {

									$wpie_parent_posts = get_post_ancestors( $item->ID );

									if ( ! empty( $wpie_parent_posts ) ) {

										$wpie_slugs = array();

										foreach ( $wpie_parent_posts as $wpie_parent_post ) {

											$the_post = get_post( $wpie_parent_post );

											if ( $the_post ) {
												$wpie_slugs[] = $the_post->post_name;
											}

											unset( $the_post );
										}

										$parent_slug = implode( '/', array_reverse( $wpie_slugs ) );

										unset( $wpie_slugs );
									} else {

										$the_post = get_post( $item->post_parent );

										if ( $the_post ) {
											$parent_slug = $the_post->post_name;
										}

										unset( $the_post );
									}

									unset( $wpie_parent_posts );
								} else {
									$parent_slug = $item->post_parent;
								}

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_parent_slug', $this->apply_user_function( $parent_slug, $is_php, $php_func ), $item );

								unset( $parent_slug );

								break;

							case 'comment_status':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_comment_status', $this->apply_user_function( $item->comment_status, $is_php, $php_func ), $item );
								break;

							case 'ping_status':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_ping_status', $this->apply_user_function( $item->ping_status, $is_php, $php_func ), $item );
								break;

							case 'post_password':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_password', $this->apply_user_function( $item->post_password, $is_php, $php_func ), $item );
								break;

							case 'template':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_template', $this->apply_user_function( get_post_meta( $item->ID, '_wp_page_template', true ), $is_php, $php_func ), $item );
								break;

							case 'order':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_menu_order', $this->apply_user_function( $item->menu_order, $is_php, $php_func ), $item );
								break;

							case 'status':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_status', $this->apply_user_function( $item->post_status, $is_php, $php_func ), $item );
								break;

							case 'format':
								$postFormat                       = get_post_format( $item->ID );
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_format', $this->apply_user_function( ( false === $postFormat ? '' : $postFormat ), $is_php, $php_func ), $item );
								break;

							case 'author_id':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_author_id', $this->apply_user_function( $item->post_author, $is_php, $php_func ), $item );
								break;

							case 'author_username':

								if ( ! isset( $users[ $item->post_author ] ) ) {
									$users[ $item->post_author ] = get_user_by( 'id', $item->post_author );
								}

								$user_data = $users[ $item->post_author ];

								$username = ( is_object( $user_data ) && isset( $user_data->user_login ) ) ? $user_data->user_login : '';

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_author', $this->apply_user_function( $username, $is_php, $php_func ), $item );

								unset( $user_data, $username );

								break;

							case 'author_email':

								if ( ! isset( $users[ $item->post_author ] ) ) {
									$users[ $item->post_author ] = get_user_by( 'id', $item->post_author );
								}

								$user_data = $users[ $item->post_author ];

								$user_email = ( is_object( $user_data ) && isset( $user_data->user_email ) ) ? $user_data->user_email : '';

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_author_email', $this->apply_user_function( $user_email, $is_php, $php_func ), $item );

								unset( $user_data, $user_email );

								break;

							case 'wpie_cf_first_name':

								if ( ! isset( $users[ $item->post_author ] ) ) {
									$users[ $item->post_author ] = get_user_by( 'id', $item->post_author );
								}

								$user_data = $users[ $item->post_author ];

								$user_fname = ( is_object( $user_data ) && isset( $user_data->first_name ) ) ? $user_data->first_name : '';

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_author_first_name', $this->apply_user_function( $user_fname, $is_php, $php_func ), $item );

								unset( $user_data, $user_fname );

								break;

							case 'wpie_cf_last_name':

								if ( ! isset( $users[ $item->post_author ] ) ) {
									$users[ $item->post_author ] = get_user_by( 'id', $item->post_author );
								}

								$user_data = $users[ $item->post_author ];

								$user_lname = ( is_object( $user_data ) && isset( $user_data->last_name ) ) ? $user_data->last_name : '';

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_author_last_name', $this->apply_user_function( $user_lname, $is_php, $php_func ), $item );

								unset( $user_data, $user_lname );

								break;

							case 'slug':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_slug', $this->apply_user_function( $item->post_name, $is_php, $php_func ), $item );
								break;

							case 'excerpt':
							case 'post_excerpt':
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_excerpt', $this->apply_user_function( $item->post_excerpt, $is_php, $php_func ), $item );
								break;

							case 'wpie_cf':

								$meta_value = '';

								$meta_key = isset( $field_option['metaKey'] ) ? wpie_sanitize_field( $field_option['metaKey'] ) : '';

								if ( '' !== $meta_key ) {

									$post_meta_value = get_post_meta( $item->ID, $meta_key );

									if ( ! empty( $post_meta_value ) && is_array( $post_meta_value ) ) {

										foreach ( $post_meta_value as $mvalue ) {
											if ( '' === $meta_value ) {
												$meta_value = maybe_serialize( $mvalue );
											} else {
												$meta_value .= '||' . maybe_serialize( $mvalue );
											}
										}
									}
									unset( $post_meta_value );
								}

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_meta', $this->apply_user_function( $meta_value, $is_php, $php_func ), $meta_key, $item );

								unset( $meta_value, $meta_key );

								break;

							case 'wpie_tax':

								$tax_name = isset( $field_option['taxName'] ) ? wpie_sanitize_field( $field_option['taxName'] ) : '';

								$tax_value = array();

								if ( '' !== $tax_name ) {

									$_post_id = $item->ID;
									if ( $this->addons && is_array( $this->addons ) ) {
										foreach ( $this->addons as $addon ) {
											if ( method_exists( $addon, 'process_item_taxonomy_id' ) ) {
												$_post_id = $addon->process_item_taxonomy_id( $_post_id, $field_type, $field_name, $field_option, $item );
											}
										}
									}

									$tax_value = $this->get_hierarchy_by_post_id( $_post_id, $tax_name );
								}

								if ( ! empty( $tax_value ) && is_array( $tax_value ) ) {
									$tax_str = implode( ',', $tax_value );
								} else {
									$tax_str = '';
								}

								$this->export_data[ $field_name ] = $tax_str;

								if ( $this->addons && is_array( $this->addons ) ) {
									foreach ( $this->addons as $addon ) {
										if ( method_exists( $addon, 'process_item_taxonomy' ) ) {
											$addon->process_item_taxonomy( $this->export_data, $field_type, $field_name, $field_option, $item );
										}
									}
								}

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_term', $this->apply_user_function( $this->export_data[ $field_name ], $is_php, $php_func ), $tax_name, $item );

								unset( $tax_name, $tax_value, $tax_str );

								break;

							case 'attachments':
							case 'attachment_id':
							case 'attachment_url':
							case 'attachment_filename':
							case 'attachment_path':
							case 'attachment_title':
							case 'attachment_caption':
							case 'attachment_description':
							case 'attachment_alt':

								if ( ! isset( $media[ $item->ID ] ) ) {
									$media[ $item->ID ] = $wpie_media->get_media( $item->ID );
								}

								$attach_value = '';

								if ( ! empty( $media[ $item->ID ] ) ) {

									$attch = $wpie_media->get_attch( $media[ $item->ID ], $field_type, 'post' );

									if ( ! empty( $attch ) ) {
										$attach_value = implode( '||', $attch );
									}
									unset( $attch );
								}

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_attach_' . $field_type, $this->apply_user_function( $attach_value, $is_php, $php_func ), $item );

								unset( $attach_value );

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

								if ( ! isset( $media[ $item->ID ] ) ) {
									$media[ $item->ID ] = $wpie_media->get_media( $item->ID );
								}

								$image_media = '';

								if ( isset( $media[ $item->ID ] ) ) {

									$image_data = $wpie_media->get_images( $item->ID, $media[ $item->ID ], $field_type, 'post' );

									if ( ! empty( $image_data ) ) {
										$image_media = implode( '||', $image_data );
									}

									unset( $image_data );
								}

								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_image_' . $field_type, $this->apply_user_function( $image_media, $is_php, $php_func ), $item );

								unset( $image_media );

								break;

							default:
								$this->export_data[ $field_name ] = apply_filters( 'wpie_export_post_field', $this->apply_user_function( '', $is_php, $php_func ), $item );
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

				unset( $item );
			}

			$wpie_export_id = 0;
		}

		unset( $fields_data, $site_date_format, $users, $media );
	}

	/**
	 * Append post WHERE conditions to main posts query.
	 *
	 * @param string $data SQL WHERE clause.
	 * @return string
	 */
	public function posts_where( $data = '' ) {

		global $wpdb;

		$this->item_where = apply_filters( 'wpie_export_posts_where', $this->item_where, $this->item_join );

		if ( ! empty( $this->item_where ) ) {

			$post_where = preg_replace( array( '/OR$/', '/OR $/', '/AND$/', '/AND $/' ), ' ', preg_replace( array( '%^ OR %', '%^ AND %' ), ' ', implode( ' ', $this->item_where ) ) );

			$data .= ' AND ( ' . $post_where . ')';

			unset( $post_where );
		}

		$data .= " AND ({$wpdb->posts}.post_status != 'auto-draft' AND {$wpdb->posts}.post_title != 'Auto Draft') ";

		return $data;
	}

	/**
	 * Append post JOIN clauses to main posts query.
	 *
	 * @param string $data SQL JOIN clause.
	 * @return string
	 */
	public function posts_join( $data = '' ) {

		$this->item_join = apply_filters( 'wpie_export_posts_join', $this->item_join );

		if ( ! empty( $this->item_join ) ) {
			$data .= implode( ' ', array_unique( $this->item_join ) );
		}

		return $data;
	}

	/**
	 * Force GROUP BY post ID in posts query.
	 *
	 * @param string $groupby SQL GROUP BY clause.
	 * @return string
	 */
	public function posts_groupby( $groupby = '' ) {

		global $wpdb;

		return "{$wpdb->posts}.ID";
	}

	/**
	 * Map friendly field names to valid internal DB elements.
	 *
	 * @param string $element Field identifier.
	 * @return string
	 */
	private function get_valid_element( $element = '' ) {

		switch ( $element ) {
			case 'id':
				$return_data = strtoupper( $element );
				break;
			case 'parent':
			case 'author':
			case 'status':
			case 'title':
			case 'content':
			case 'date':
			case 'excerpt':
				$return_data = 'post_' . $element;
				break;
			case 'permalink':
				$return_data = 'guid';
				break;
			case 'slug':
				$return_data = 'post_name';
				break;
			case 'order':
				$return_data = 'menu_order';
				break;
			case 'template':
				$return_data = 'wpie_cf__wp_page_template';
				break;
			case 'format':
				$return_data = 'wpie_tax_post_format';
				break;
			default:
				$return_data = $element;
				break;
		}

		return $return_data;
	}

	/**
	 * Verify if any child terms of a given parent term are assigned to the current post.
	 *
	 * @param int    $parent   Term ID of the parent.
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $term_ids Array of term IDs assigned to the post.
	 * @return bool True if this term is the deepest assigned descendant.
	 */
	private function check_children_assign( $parent, $taxonomy, $term_ids = array() ) {

		$is_latest_child = true;

		$children = get_term_children( (int) $parent, $taxonomy );

		if ( ! is_wp_error( $children ) && is_array( $children ) && ! empty( $children ) ) {

			foreach ( $children as $child ) {

				if ( in_array( (int) $child, array_map( 'intval', $term_ids ), true ) ) {
					$is_latest_child = false;
					break;
				}
			}
		}

		unset( $children );

		return $is_latest_child;
	}

	/**
	 * Retrieve taxonomy hierarchy strings by term IDs.
	 *
	 * @param array  $tax_ids  Array of term IDs.
	 * @param string $tax_name Taxonomy slug.
	 * @return array|false
	 */
	public function get_hierarchy_by_taxonomy_id( $tax_ids = array(), $tax_name = '' ) {

		if ( is_array( $tax_ids ) && ! empty( $tax_ids ) ) {

			global $wp_version;

			$term_args = array(
				'taxonomy'   => $tax_name,
				'include'    => $tax_ids,
				'hide_empty' => false,
			);

			if ( ! empty( $wp_version ) && version_compare( strval( $wp_version ), '4.5.0', '<' ) ) {
				// phpcs:ignore WordPress.WP.DeprecatedParameters.Get_termsParam2Found -- Compatibility fallback for WordPress < 4.5.0.
				$taxonomies = get_terms( $tax_name, $term_args );
			} else {
				$taxonomies = get_terms( $term_args );
			}

			return $this->get_hierarchy_by_taxonomy( $taxonomies, $tax_name );
		}

		return false;
	}

	/**
	 * Retrieve taxonomy hierarchy for a post.
	 *
	 * @param int    $_post_id Post ID.
	 * @param string $tax_name Taxonomy slug.
	 * @return array|false
	 */
	private function get_hierarchy_by_post_id( $_post_id = 0, $tax_name = '' ) {

		$taxonomies = get_the_terms( $_post_id, $tax_name );

		return $this->get_hierarchy_by_taxonomy( $taxonomies, $tax_name );
	}

	/**
	 * Build hierarchical term chains formatted with '>' delimiters.
	 *
	 * @param array|\WP_Error $taxonomy Array of WP_Term objects.
	 * @param string          $tax_name Taxonomy slug.
	 * @return array|false
	 */
	private function get_hierarchy_by_taxonomy( $taxonomy = array(), $tax_name = '' ) {

		$hierarchy_groups = array();

		if ( ! is_wp_error( $taxonomy ) && ! empty( $taxonomy ) ) {

			$tax_ids = array();

			foreach ( $taxonomy as $tax_list ) {
				if ( isset( $tax_list->term_id ) ) {
					$tax_ids[] = (int) $tax_list->term_id;
				}
			}

			foreach ( $taxonomy as $tax_list ) {

				if ( ! isset( $tax_list->term_id ) ) {
					continue;
				}

				if ( $this->check_children_assign( $tax_list->term_id, $tax_name, $tax_ids ) ) {

					$ancestors = get_ancestors( $tax_list->term_id, $tax_name );

					if ( count( $ancestors ) > 0 ) {

						$hierarchy = array();

						for ( $i = count( $ancestors ) - 1; $i >= 0; $i-- ) {

							$term = get_term_by( 'id', $ancestors[ $i ], $tax_name );

							if ( $term && isset( $term->name ) ) {
								$hierarchy[] = $term->name;
							}

							unset( $term );
						}

						$hierarchy[] = isset( $tax_list->name ) ? $tax_list->name : '';

						$hierarchy_groups[] = implode( '>', $hierarchy );

						unset( $hierarchy );
					} else {
						$hierarchy_groups[] = isset( $tax_list->name ) ? $tax_list->name : '';
					}

					unset( $ancestors );
				}
			}

			unset( $tax_ids );
		}

		if ( ! empty( $hierarchy_groups ) ) {
			return $hierarchy_groups;
		}

		return false;
	}

	/**
	 * Modify WP_User_Query clauses during author filter queries.
	 *
	 * @param \WP_User_Query $obj User query instance.
	 * @return void
	 */
	public function pre_user_query( $obj ) {

		$obj->query_where .= $this->user_where;

		if ( ! empty( $this->user_join ) ) {
			$obj->query_from .= implode( ' ', array_unique( $this->user_join ) );
		}
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

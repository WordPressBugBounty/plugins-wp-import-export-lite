<?php
/**
 * WPIE User Export Class
 *
 * Handles WordPress user queries, user metadata extraction, roles, and field mappings.
 *
 * @package    WPIE
 * @subpackage WPIE/Export/Extensions/User
 */

namespace wpie\export\user;

use WP_User_Query;
use WP_User;
use wpie\export\engine\WPIE_Export_Engine;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-engine.php' ) ) {
	require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-engine.php';
}

/**
 * Class WPIE_User_Export
 *
 * User export engine implementation.
 */
class WPIE_User_Export extends WPIE_Export_Engine {

	/**
	 * User query WHERE clauses.
	 *
	 * @var array
	 */
	protected $item_where = array();

	/**
	 * User query JOIN clauses.
	 *
	 * @var array
	 */
	protected $item_join = array();

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Prepare exportable user fields definition.
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
					'type'      => 'id',
					'isDefault' => true,
				),
				array(
					'name'      => 'User Login',
					'type'      => 'user_login',
					'isDefault' => true,
				),
				array(
					'name'      => 'User Email',
					'type'      => 'user_email',
					'isDefault' => true,
				),
				array(
					'name'      => 'First Name',
					'type'      => 'wpie_cf',
					'metaKey'   => 'first_name',
					'isDefault' => true,
				),
				array(
					'name'      => 'Last Name',
					'type'      => 'wpie_cf',
					'metaKey'   => 'last_name',
					'isDefault' => true,
				),
				array(
					'name'      => 'User Registered',
					'type'      => 'user_registered',
					'isDate'    => true,
					'isDefault' => true,
				),
				array(
					'name'      => 'User Nicename',
					'type'      => 'user_nicename',
					'isDefault' => true,
				),
				array(
					'name'      => 'User URL',
					'type'      => 'user_url',
					'isDefault' => true,
				),
				array(
					'name'      => 'Display Name',
					'type'      => 'display_name',
					'isDefault' => true,
				),
				array(
					'name'      => 'Nickname',
					'type'      => 'wpie_cf',
					'metaKey'   => 'nickname',
					'isDefault' => true,
				),
				array(
					'name'      => 'Description',
					'type'      => 'wpie_cf',
					'metaKey'   => 'description',
					'isDefault' => true,
				),
			),
		);

		$other_fields = array(
			'title' => __( 'Other', 'wp-import-export-lite' ),
			'data'  => array(
				array(
					'name' => 'User Role',
					'type' => 'user_role',
				),
				array(
					'name'       => 'User Pass',
					'type'       => 'user_pass',
					'isFiltered' => false,
				),
				array(
					'name' => 'User Activation Key',
					'type' => 'user_activation_key',
				),
				array(
					'name' => 'User Status',
					'type' => 'user_status',
				),
			),
		);

		$metas = apply_filters( 'wpie_pre_user_meta_fields', $this->get_meta_keys() );

		$export_fields = array(
			'standard' => apply_filters( 'wpie_post_standard_fields', $standard_fields ),
			'meta'     => apply_filters( 'wpie_post_meta_fields', $metas ),
			'other'    => apply_filters( 'wpie_post_other_fields', $other_fields ),
		);

		$addon_class = apply_filters( 'wpie_prepare_user_fields', array(), $this->export_type );

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

		$meta_fields = array(
			'title' => __( 'User Meta', 'wp-import-export-lite' ),
			'data'  => $meta_data,
		);

		$export_fields['meta'] = $meta_fields;

		unset( $standard_fields, $other_fields, $addon_class, $meta_data, $meta_fields );

		return apply_filters( 'wpie_export_fields', $export_fields, $this->export_type );
	}

	/**
	 * Retrieve distinct user meta keys for field configuration.
	 *
	 * @return array
	 */
	private function get_meta_keys() {

		global $wpdb;

		$limit = apply_filters( 'wpie_usermeta_form_limit', 500 );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT {$wpdb->usermeta}.meta_key
				FROM {$wpdb->usermeta}, {$wpdb->users}
				WHERE {$wpdb->usermeta}.user_id = {$wpdb->users}.ID
				AND {$wpdb->usermeta}.meta_key NOT BETWEEN '_' AND '_z'
				HAVING {$wpdb->usermeta}.meta_key NOT LIKE %s
				ORDER BY {$wpdb->usermeta}.meta_key
				LIMIT %d",
				$wpdb->esc_like( '_' ) . '%',
				$limit
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		unset( $limit );

		return is_array( $keys ) ? $keys : array();
	}

	/**
	 * Parse user filter criteria into SQL clauses.
	 *
	 * @param array $filter Filter rule.
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
				case 'ID':
				case 'id':
					$this->item_where[] = "{$wpdb->users}.ID " . $this->add_filter_rule( $filter, true, false );
					break;
				case 'user_role':
					$cap_key            = $wpdb->prefix . 'capabilities';
					$rule_sql           = $this->add_filter_rule( $filter, false, false );
					$this->item_where[] = $this->add_placeholder( $wpdb->prepare( "{$wpdb->usermeta}.meta_key = %s AND ", $cap_key ) ) . "{$wpdb->usermeta}.meta_value " . $rule_sql;
					$this->item_join[]  = " INNER JOIN {$wpdb->usermeta} ON ( {$wpdb->users}.ID = {$wpdb->usermeta}.user_id )";
					unset( $cap_key, $rule_sql );
					break;
				case 'user_registered':
					$filter['value']    = $this->add_date_filter_rule( $filter );
					$this->item_where[] = "{$wpdb->users}.user_registered " . $this->add_filter_rule( $filter, false, false );
					break;
				case 'user_status':
				case 'display_name':
				case 'user_login':
				case 'user_nicename':
				case 'user_email':
				case 'user_url':
					$col                = sanitize_key( $filter['element'] );
					$this->item_where[] = "{$wpdb->users}.{$col} " . $this->add_filter_rule( $filter, false, false );
					unset( $col );
					break;

				case 'wpie_cf':

					$meta_key = isset( $filter['metaKey'] ) ? sanitize_text_field( $filter['metaKey'] ) : '';

					if ( ! $this->is_valid_meta_key( $meta_key, 'user' ) ) {
						unset( $meta_key );
						break;
					}

					$table_alias = ( count( $this->item_join ) > 0 ) ? 'meta' . count( $this->item_join ) : 'meta';

					if ( 'is_empty' === $filter['condition'] ) {
						$this->item_join[]  = $this->add_placeholder( $wpdb->prepare( " LEFT JOIN {$wpdb->usermeta} AS $table_alias ON ($table_alias.user_id = {$wpdb->users}.ID AND $table_alias.meta_key = %s) ", $meta_key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$this->item_where[] = "$table_alias.meta_id " . $this->add_filter_rule( $filter, false, false );
					} else {
						$this->item_join[]  = " INNER JOIN {$wpdb->usermeta} AS $table_alias ON ({$wpdb->users}.ID = $table_alias.user_id) ";
						$rule_sql           = $this->add_filter_rule( $filter, false, $table_alias );
						$this->item_where[] = $this->add_placeholder( $wpdb->prepare( "$table_alias.meta_key = %s AND ", $meta_key ) ) . "$table_alias.meta_value " . $rule_sql; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						unset( $rule_sql );
					}

					unset( $meta_key, $table_alias );
					break;

				default:
					break;
			}
		}
		unset( $filter );
	}

	/**
	 * Execute user query for export batch.
	 *
	 * @return true|int|array
	 */
	protected function process_export() {

		$query = array(
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'ids',
			'offset'  => isset( $this->process_log['exported'] ) ? absint( $this->process_log['exported'] ) : 0,
			'number'  => isset( $this->template_options['wpie_records_per_iteration'] ) ? absint( $this->template_options['wpie_records_per_iteration'] ) : 50,
		);

		if ( 'count' === $this->opration || 'ids' === $this->opration ) {
			$query['offset'] = 0;
			unset( $query['number'] );
		}

		$query = apply_filters( 'wpie_pre_execute_user_query', $query );

		add_action( 'pre_user_query', array( $this, 'pre_user_query' ), 10, 1 );

		$user_result = new WP_User_Query( $query );

		remove_action( 'pre_user_query', array( $this, 'pre_user_query' ) );

		$results = ( isset( $user_result->results ) && is_array( $user_result->results ) ) ? $user_result->results : array();

		if ( 'count' === $this->opration ) {
			return count( $results );
		} elseif ( 'ids' === $this->opration ) {
			return $results;
		}

		$this->process_items( $results );

		unset( $query, $user_result, $results );

		return true;
	}

	/**
	 * Process row data for each user in the batch.
	 *
	 * @param array $user_data Array of user IDs.
	 * @return void
	 */
	protected function process_items( $user_data = array() ) {

		$fields_data = ( isset( $this->template_options['fields_data'] ) && trim( (string) $this->template_options['fields_data'] ) !== '' )
			? explode( '~||~', wpie_sanitize_field( wp_unslash( (string) $this->template_options['fields_data'] ) ) )
			: array();

		if ( ! empty( $user_data ) && ! empty( $fields_data ) ) {

			if ( $this->addons && is_array( $this->addons ) ) {

				foreach ( $this->addons as $addon ) {

					if ( method_exists( $addon, 'init_export_process' ) ) {
						// Correct variable passed: $user_data instead of undefined $post_data.
						$addon->init_export_process( $user_data, $this->template_options, $this->export_id );
					}
				}
			}

			$temp_field_count = 0;

			global $wpie_export_id;

			$wpie_export_id = 0;

			foreach ( $user_data as $item_id ) {

				$wpie_export_id = absint( $item_id );

				$item = get_user_by( 'id', absint( $item_id ) );

				// Guard: skip if user does not exist or was deleted.
				if ( ! $item instanceof WP_User ) {
					continue;
				}

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

					unset( $new_field );

					$field_type = isset( $field_option['type'] ) ? wpie_sanitize_field( $field_option['type'] ) : '';

					$is_php = isset( $field_option['isPhp'] ) && 1 === (int) wpie_sanitize_field( $field_option['isPhp'] );

					$php_func = isset( $field_option['phpFun'] ) ? wpie_sanitize_field( $field_option['phpFun'] ) : '';

					$date_type = isset( $field_option['dateType'] ) ? wpie_sanitize_field( $field_option['dateType'] ) : '';

					$date_format = isset( $field_option['dateFormat'] ) ? wpie_sanitize_field( $field_option['dateFormat'] ) : '';

					$site_date_format = get_option( 'date_format' );

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
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_id', $this->apply_user_function( $item->ID, $is_php, $php_func ), $item );
							break;
						case 'user_login':
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_login', $this->apply_user_function( $item->user_login, $is_php, $php_func ), $item );
							break;
						case 'user_pass':
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_pass', $this->apply_user_function( $item->user_pass, $is_php, $php_func ), $item );
							break;
						case 'user_email':
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_email', $this->apply_user_function( $item->user_email, $is_php, $php_func ), $item );
							break;
						case 'user_nicename':
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_nicename', $this->apply_user_function( $item->user_nicename, $is_php, $php_func ), $item );
							break;
						case 'user_url':
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_url', $this->apply_user_function( $item->user_url, $is_php, $php_func ), $item );
							break;
						case 'user_activation_key':
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_activation_key', $this->apply_user_function( $item->user_activation_key, $is_php, $php_func ), $item );
							break;
						case 'user_status':
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_status', $this->apply_user_function( $item->user_status, $is_php, $php_func ), $item );
							break;
						case 'display_name':
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_display_name', $this->apply_user_function( $item->display_name, $is_php, $php_func ), $item );
							break;
						case 'nickname':
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_nickname', $this->apply_user_function( $item->nickname, $is_php, $php_func ), $item );
							break;
						case 'first_name':
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_first_name', $this->apply_user_function( $item->first_name, $is_php, $php_func ), $item );
							break;
						case 'last_name':
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_last_name', $this->apply_user_function( $item->last_name, $is_php, $php_func ), $item );
							break;
						case 'user_role':
							$roles_str = ( is_array( $item->roles ) && ! empty( $item->roles ) ) ? implode( ',', $item->roles ) : '';
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_role', $this->apply_user_function( $roles_str, $is_php, $php_func ), $item );
							unset( $roles_str );
							break;
						case 'description':
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_description', $this->apply_user_function( $item->description, $is_php, $php_func ), $item );
							break;
						case 'user_registered':
							$registered_time                  = ! empty( $item->user_registered ) ? strtotime( (string) $item->user_registered ) : 0;
							$user_registered                  = $this->get_date_field( $date_type, $registered_time, $date_format );
							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_registered', $this->apply_user_function( $user_registered, $is_php, $php_func ), $item );
							break;
						case 'wpie_cf':

							$meta_value = '';

							$meta_key = isset( $field_option['metaKey'] ) ? wpie_sanitize_field( $field_option['metaKey'] ) : '';

							if ( '' !== $meta_key ) {

								$user_metas = get_user_meta( $item->ID, $meta_key );

								if ( ! empty( $user_metas ) && is_array( $user_metas ) ) {

									foreach ( $user_metas as $_value ) {
										if ( '' === $meta_value ) {
											$meta_value = maybe_serialize( $_value );
										} else {
											$meta_value .= '||' . maybe_serialize( $_value );
										}
									}
								}
								unset( $user_metas );
							}

							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_meta', $this->apply_user_function( $meta_value, $is_php, $php_func ), $meta_key, $item );

							unset( $meta_value, $meta_key );
							break;

						default:
							$defaults = apply_filters( 'wpie_export_pre_user_default_field', '', $field_name, $field_option, $item );

							$this->export_data[ $field_name ] = apply_filters( 'wpie_export_user_default_field', $this->apply_user_function( $defaults, $is_php, $php_func ), $item );

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

					unset( $field_label, $field_option, $field_type, $is_php, $php_func, $date_type, $date_format, $field_name );
				}

				if ( $this->addons && is_array( $this->addons ) ) {

					foreach ( $this->addons as $addon ) {

						if ( method_exists( $addon, 'finalyze_export_process' ) ) {
							$addon->finalyze_export_process( $this->export_data, $this->has_multiple_rows );
						}
					}
				}

				$this->process_data();

				unset( $item );
			}

			$wpie_export_id = 0;
		}
		unset( $fields_data );
	}

	/**
	 * Modify query clauses for WP_User_Query.
	 *
	 * @param \WP_User_Query $data User query instance.
	 * @return \WP_User_Query
	 */
	public function pre_user_query( $data ) {

		$this->item_where = apply_filters( 'wpie_export_users_where', $this->item_where, $this->item_join );

		if ( ! empty( $this->item_join ) ) {
			$data->query_from .= implode( ' ', array_unique( $this->item_join ) );
		}

		if ( ! empty( $this->item_where ) ) {

			$query_where = preg_replace( array( '/\s+OR\s*$/i', '/\s+AND\s*$/i' ), '', implode( ' ', $this->item_where ) );
			$query_where = preg_replace( array( '/^\s*OR\s+/i', '/^\s*AND\s+/i' ), '', trim( $query_where ) );

			if ( ! empty( $query_where ) ) {
				$data->query_where .= ' AND ( ' . $query_where . ' )';
			}

			unset( $query_where );
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

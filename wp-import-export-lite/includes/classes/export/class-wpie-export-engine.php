<?php
/**
 * Abstract Export Engine Class.
 *
 * Coordinates file creation, directory traversal validation, CSV streaming,
 * filter rule ingestion, and background add-on lifecycles.
 *
 * @package WP_Import_Export_Lite
 * @subpackage Export\Engine
 * @since 1.0.0
 */

namespace wpie\export\engine;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-base.php' ) ) {
	require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-base.php';
}

/**
 * Class WPIE_Export_Engine
 *
 * Coordinates file creation, data chunking, CSV streaming, and filter compilation.
 *
 * @since 1.0.0
 */
abstract class WPIE_Export_Engine extends \wpie\export\base\WPIE_Export_Base {

	/**
	 * File pointer resource for the streaming export file.
	 *
	 * @var resource|false
	 */
	private $fp = false;

	/**
	 * Single-character delimiter used for CSV output.
	 *
	 * @var string
	 */
	private $csv_delim = ',';

	/**
	 * Retrieve field definitions for this entity type.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	abstract protected function get_fields();

	/**
	 * Parse and append an individual filter rule to the query clauses.
	 *
	 * @since 1.0.0
	 *
	 * @param array $filter Filter rule array.
	 * @return void
	 */
	abstract protected function parse_rule( $filter );

	/**
	 * Execute the export query and process matched records.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	abstract protected function process_export();

	/**
	 * Initialize the export engine and dispatch by requested operation.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $export_type Target export type (e.g. 'post', 'product', 'taxonomies'). Default 'post'.
	 * @param string      $opration    Operation mode ('export', 'fields', 'count', 'ids', 'preview', 'import_backup').
	 * @param object|null $template    Export template database object or null.
	 * @return mixed
	 */
	public function init_engine( $export_type = 'post', $opration = 'export', $template = null ) {

		if ( 'product' === $export_type ) {
			$this->export_type = array( 'product', 'product_variation' );
		} else {
			$this->export_type = array( (string) $export_type );
		}

		$this->export_taxonomy_type = ( isset( $template->wpie_taxonomy_type ) && is_string( $template->wpie_taxonomy_type ) ) ? $template->wpie_taxonomy_type : '';

		$this->opration = strtolower( trim( (string) $opration ) );

		if ( 'fields' === $this->opration ) {
			$this->template_options = $template;
			return $this->get_fields();
		} elseif ( 'count' === $this->opration ) {
			return $this->get_item_data( $template );
		} elseif ( 'ids' === $this->opration ) {
			return $this->get_item_data( $template );
		} elseif ( 'preview' === $this->opration ) {
			$this->is_preview = true;
			return $this->get_item_data( $template );
		} elseif ( 'import_backup' === $this->opration ) {
			return $this->get_backup_data( $template );
		} else {
			return $this->init_export( $template );
		}
	}

	/**
	 * Generate backup file for migration packages.
	 *
	 * @since 1.0.0
	 *
	 * @param array|object $template Template configuration options.
	 * @return void
	 */
	private function get_backup_data( $template ) {

		$this->template_options = $template;

		$this->process_log = array(
			'exported' => isset( $this->template_options['count'] ) ? absint( $this->template_options['count'] ) : 0,
			'total'    => 0,
		);

		$backup_dir = isset( $this->template_options['backup_dir'] ) ? (string) $this->template_options['backup_dir'] : '';

		$this->open_export_file( $backup_dir . '/backup.csv' );

		unset( $backup_dir );

		$this->init_export_addons();

		$id = ( isset( $this->template_options['id'] ) && absint( $this->template_options['id'] ) > 0 ) ? absint( $this->template_options['id'] ) : 0;

		$this->process_items( array( $id ) );

		unset( $id );

		$this->remove_addons();

		$this->close_export_file();
	}

	/**
	 * Compile metadata and execute preview, count, or ids query.
	 *
	 * @since 1.0.0
	 *
	 * @param array|object|null $template Template configuration options.
	 * @return mixed Query result or preview rows array.
	 */
	private function get_item_data( $template = null ) {

		global $wpieExportTemplate;

		$this->template_options = $wpieExportTemplate = $template;

		$this->process_log = array(
			'exported' => 0,
			'total'    => 0,
		);

		if ( $this->is_preview ) {
			$this->process_log['exported']                        = isset( $this->template_options['start'] ) ? absint( $this->template_options['start'] ) : 0;
			$this->template_options['wpie_records_per_iteration'] = isset( $this->template_options['length'] ) ? absint( $this->template_options['length'] ) : 10;
		}

		$this->manage_rules();

		$this->init_export_addons();

		$export = $this->process_export();

		$this->remove_addons();

		if ( isset( $this->is_preview ) && true === $this->is_preview ) {
			unset( $export );
			return $this->preview_data;
		} else {
			return $export;
		}
	}

	/**
	 * Instantiate and initialize export add-ons.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_export_addons() {

		$addon_class = apply_filters( 'wpie_prepare_export_addons', array(), $this->export_type );

		if ( ! empty( $addon_class ) && is_array( $addon_class ) ) {

			foreach ( $addon_class as $key => $addon ) {

				if ( class_exists( $addon ) ) {

					$this->addons[ $key ] = new $addon();

					if ( method_exists( $this->addons[ $key ], 'init_process' ) ) {
						$this->addons[ $key ]->init_process( $this->template_options );
					}
				}
			}
		}
	}

	/**
	 * Unset active add-ons buffer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function remove_addons() {
		$this->addons = array();
	}

	/**
	 * Validate export file name and extension.
	 *
	 * Allows only valid export file types and strictly blocks executable files/scripts.
	 *
	 * @since 3.9.33
	 *
	 * @param string $filename Raw filename.
	 * @return string|\WP_Error Validated and sanitized filename, or WP_Error on failure.
	 */
	protected function validate_export_filename( $filename = '' ) {

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
		$allowed_extensions = apply_filters( 'wpie_export_allowed_file_extensions', array( 'csv', 'tsv', 'xml', 'json', 'xls', 'xlsx', 'txt' ) );

		if ( empty( $ext ) || ! in_array( $ext, $allowed_extensions, true ) ) {
			return new \WP_Error( 'wpie_export_invalid_extension', __( 'Invalid export file extension.', 'wp-import-export-lite' ) );
		}

		return $clean_filename;
	}

	/**
	 * Resolve and validate export directory and file path using realpath().
	 *
	 * Ensures that constructed directory and file paths remain strictly inside
	 * the intended export base directory (WPIE_UPLOAD_EXPORT_DIR) to prevent directory traversal.
	 *
	 * @since 3.9.33
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

		if ( ! is_dir( $target_dir ) || ! wp_is_writable( $target_dir ) ) {

			wp_mkdir_p( $target_dir );

			$stat = @stat( $target_dir );
			if ( $stat ) {
				$dir_perms = $stat['mode'] & 0007777;
			} else {
				$dir_perms = 0777;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Adjust directory permissions.
			@chmod( $target_dir, $dir_perms );
		}

		$real_target_dir = realpath( $target_dir );
		if ( false === $real_target_dir ) {
			return new \WP_Error( 'wpie_export_dir_error', __( 'Export directory could not be resolved.', 'wp-import-export-lite' ) );
		}

		$real_target_dir = trailingslashit( wp_normalize_path( $real_target_dir ) );

		// Verify canonical directory stays strictly inside intended export base directory.
		if ( strpos( $real_target_dir, $base_export_dir ) !== 0 ) {
			return new \WP_Error( 'wpie_export_traversal_detected', __( 'Export directory is outside the allowed export base directory.', 'wp-import-export-lite' ) );
		}

		$filepath = $real_target_dir . $filename;

		if ( file_exists( $filepath ) ) {
			$real_filepath = realpath( $filepath );
			if ( false === $real_filepath || strpos( trailingslashit( wp_normalize_path( dirname( $real_filepath ) ) ), $base_export_dir ) !== 0 ) {
				return new \WP_Error( 'wpie_export_traversal_detected', __( 'Export file path is outside the allowed base directory.', 'wp-import-export-lite' ) );
			}
		}

		return $filepath;
	}

	/**
	 * Initialize and run full export workflow for a given template.
	 *
	 * @since 1.0.0
	 *
	 * @param object|null $template Database template record.
	 * @return array|\WP_Error Process log array on success, WP_Error on failure.
	 */
	private function init_export( $template = null ) {

		global $wpdb, $wpieExportTemplate;

		$this->fp = false;

		$this->export_id = isset( $template->id ) ? absint( $template->id ) : 0;

		$this->template_options = $wpieExportTemplate = ( isset( $template->options ) && is_string( $template->options ) ) ? maybe_unserialize( $template->options ) : array();

		$wpdb->update( $wpdb->prefix . 'wpie_template', array( 'process_lock' => 1 ), array( 'id' => absint( $this->export_id ) ) );

		$process_data = ( isset( $template->process_log ) && is_string( $template->process_log ) ) ? maybe_unserialize( $template->process_log ) : array();

		$this->process_log = array(
			'exported' => ( isset( $process_data['exported'] ) && '' !== $process_data['exported'] ) ? absint( $process_data['exported'] ) : 0,
			'total'    => ( isset( $process_data['total'] ) && '' !== $process_data['total'] ) ? absint( $process_data['total'] ) : 0,
		);

		$filename = isset( $this->template_options['fileName'] ) ? (string) $this->template_options['fileName'] : '';

		$filedir = isset( $this->template_options['fileDir'] ) ? (string) $this->template_options['fileDir'] : '';

		// 1) Validate file name and allow only valid files.
		$validated_filename = $this->validate_export_filename( $filename );
		if ( is_wp_error( $validated_filename ) ) {
			$wpdb->update( $wpdb->prefix . 'wpie_template', array( 'process_lock' => 0 ), array( 'id' => absint( $this->export_id ) ) );
			return $validated_filename;
		}
		$filename                             = $validated_filename;
		$this->template_options['fileName'] = $filename;

		// 2) Use realpath to avoid vulnerability.
		$filepath = $this->validate_export_filepath( $filedir, $filename );
		if ( is_wp_error( $filepath ) ) {
			$wpdb->update( $wpdb->prefix . 'wpie_template', array( 'process_lock' => 0 ), array( 'id' => absint( $this->export_id ) ) );
			return $filepath;
		}

		unset( $process_data, $filedir );

		$this->manage_rules();

		$this->open_export_file( $filepath );

		if ( false === $this->fp ) {
			$wpdb->update( $wpdb->prefix . 'wpie_template', array( 'process_lock' => 0 ), array( 'id' => absint( $this->export_id ) ) );
			return new \WP_Error( 'wpie_import_error', __( 'File is not Writable', 'wp-import-export-lite' ) );
		}

		// Verify opened file canonical path via realpath().
		$real_opened_file = realpath( $filepath );
		$base_export_dir  = realpath( WPIE_UPLOAD_EXPORT_DIR );
		if ( false === $real_opened_file || false === $base_export_dir || strpos( trailingslashit( wp_normalize_path( dirname( $real_opened_file ) ) ), trailingslashit( wp_normalize_path( $base_export_dir ) ) ) !== 0 ) {
			$this->close_export_file();
			if ( file_exists( $filepath ) ) {
				@wp_delete_file( $filepath );
			}
			$wpdb->update( $wpdb->prefix . 'wpie_template', array( 'process_lock' => 0 ), array( 'id' => absint( $this->export_id ) ) );
			return new \WP_Error( 'wpie_import_error', __( 'File path validation failed', 'wp-import-export-lite' ) );
		}

		$this->init_export_addons();

		$this->process_export();

		$this->remove_addons();

		$this->close_export_file();

		$final_data = array(
			'last_update_date' => current_time( 'mysql' ),
			'process_lock'     => 0,
		);

		$wpdb->update( $wpdb->prefix . 'wpie_template', $final_data, array( 'id' => $this->export_id ) );

		unset( $final_data, $filepath );

		return $this->process_log;
	}

	/**
	 * Open the export target file for appending.
	 *
	 * @since 1.0.0
	 *
	 * @param string $filepath Path to the target file.
	 * @return void
	 */
	private function open_export_file( $filepath = '' ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Direct file handle required for streaming export data.
		$this->fp = @fopen( $filepath, 'a+' );
	}

	/**
	 * Safely close the export target file resource.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function close_export_file() {
		if ( is_resource( $this->fp ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct file handle required for streaming export data.
			fclose( $this->fp );
		}
		$this->fp = false;
	}

	/**
	 * Process and append the current item row(s) to the output stream.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function process_data() {

		if ( ! empty( $this->export_data ) ) {

			if ( $this->is_preview ) {

				$this->process_log['exported']++;

				$this->preview_data[] = array_values( $this->export_data );
			} else {
				$file_type = ( isset( $this->template_options['wpie_export_file_type'] ) && trim( (string) $this->template_options['wpie_export_file_type'] ) !== '' ) ? wpie_sanitize_field( $this->template_options['wpie_export_file_type'] ) : 'csv';

				if ( empty( $this->csv_delim ) || strlen( $this->csv_delim ) !== 1 ) {
					if ( 'csv' === $file_type ) {
						$raw_delim = ( isset( $this->template_options['wpie_csv_field_separator'] ) && trim( (string) $this->template_options['wpie_csv_field_separator'] ) !== '' ) ? wpie_sanitize_field( $this->template_options['wpie_csv_field_separator'] ) : ',';
						$this->csv_delim = ( strlen( $raw_delim ) === 1 ) ? $raw_delim : ',';
					} else {
						$this->csv_delim = ',';
					}
				}

				if ( 0 === $this->process_log['exported'] && ! empty( $this->export_labels ) ) {
					$this->addCsvData( array_values( $this->export_labels ) );
					$this->export_labels = array();
				}

				if ( $this->has_multiple_rows ) {
					foreach ( $this->export_data as $data ) {
						if ( is_array( $data ) ) {
							$this->addCsvData( array_values( $data ) );
						}
					}
				} else {
					$this->addCsvData( array_values( $this->export_data ) );
				}

				$this->process_log['exported']++;

				$final_data = array(
					'last_update_date' => current_time( 'mysql' ),
					'process_log'      => maybe_serialize( $this->process_log ),
				);

				if ( $this->process_log['exported'] >= $this->process_log['total'] ) {

					$final_data['status'] = 'completed';

					$raw_extra_copy_path = isset( $this->template_options['extra_copy_path'] ) ? wp_unslash( (string) $this->template_options['extra_copy_path'] ) : '';

					$extra_copy_path = ( '' !== $raw_extra_copy_path && strpos( $raw_extra_copy_path, '..' ) === false && strpos( $raw_extra_copy_path, "\0" ) === false && strpos( $raw_extra_copy_path, ':' ) === false ) ? ltrim( trailingslashit( sanitize_text_field( $raw_extra_copy_path ) ), '/\\' ) : '';

					$is_package = isset( $this->template_options['is_package'] ) ? intval( $this->template_options['is_package'] ) : 0;

					if ( 'schedule_export' === $this->opration ) {
						$is_package = isset( $this->template_options['is_migrate_package'] ) ? intval( $this->template_options['is_migrate_package'] ) : 0;
					}

					if ( 0 === $is_package && 'csv' === strtolower( $file_type ) && ! empty( $extra_copy_path ) && is_dir( WPIE_SITE_UPLOAD_DIR . '/' . $extra_copy_path ) ) {

						$filename = isset( $this->template_options['fileName'] ) ? (string) $this->template_options['fileName'] : '';

						$filedir = isset( $this->template_options['fileDir'] ) ? (string) $this->template_options['fileDir'] : '';

						$validated_filename = $this->validate_export_filename( $filename );

						if ( ! is_wp_error( $validated_filename ) ) {
							$filepath = $this->validate_export_filepath( $filedir, $validated_filename );

							$base_upload_dir = realpath( WPIE_SITE_UPLOAD_DIR );
							$dest_dir        = realpath( WPIE_SITE_UPLOAD_DIR . '/' . $extra_copy_path );

							if ( ! is_wp_error( $filepath ) && file_exists( $filepath ) && $base_upload_dir && $dest_dir ) {
								$base_upload_dir_norm = trailingslashit( wp_normalize_path( $base_upload_dir ) );
								$dest_dir_norm        = trailingslashit( wp_normalize_path( $dest_dir ) );

								if ( strpos( $dest_dir_norm, $base_upload_dir_norm ) === 0 ) {
									copy( $filepath, $dest_dir_norm . $validated_filename );
								}
							}
						}

						unset( $filename, $filedir, $filepath, $validated_filename );
					}

					do_action( 'wpie_export_task_complete', $this->export_id, $this->opration, $this->template_options );
				}

				global $wpdb;

				$wpdb->update( $wpdb->prefix . 'wpie_template', $final_data, array( 'id' => $this->export_id ) );

				do_action( 'wpie_export_complete', $this->export_id, $this->opration, $this->template_options );

				unset( $final_data );
			}
		}

		$this->export_data = array();
	}

	/**
	 * Write an escaped row of data to the CSV stream.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Array of column values.
	 * @return void
	 */
	private function addCsvData( $data ) {

		if ( empty( $data ) || ! is_resource( $this->fp ) ) {
			return;
		}

		$delim = ( strlen( (string) $this->csv_delim ) === 1 ) ? $this->csv_delim : ',';

		$escaped_data = array_map( array( $this, 'validate_escape_csv_data' ), $data );

		fputcsv( $this->fp, $escaped_data, $delim );
	}

	/**
	 * Escape active content triggers to neutralize formula injection in spreadsheet software.
	 *
	 * Neutralizes formulas starting with '=', '+', '-', '@', "\t", "\r".
	 *
	 * @since 3.8.1
	 *
	 * @param string $data CSV cell string.
	 * @return string Neutralized string.
	 */
	private function escape_data( $data = '' ) {

		if ( empty( $data ) || ! is_string( $data ) ) {
			return '';
		}

		$active_content_triggers = array( '=', '+', '-', '@', "\t", "\r" );

		$first_char = ( function_exists( 'mb_substr' ) ) ? mb_substr( $data, 0, 1 ) : substr( $data, 0, 1 );

		if ( in_array( $first_char, $active_content_triggers, true ) ) {
			$data = "'" . $data;
		}

		return $data;
	}

	/**
	 * Validate, normalize encoding, and escape a field value for CSV export.
	 *
	 * @since 3.8.1
	 *
	 * @param mixed $data Raw field value.
	 * @return string Sanitized and encoded string.
	 */
	private function validate_escape_csv_data( $data = '' ) {

		if ( empty( $data ) || ! is_scalar( $data ) ) {
			return '';
		}

		if ( is_bool( $data ) ) {
			$data = $data ? 1 : 0;
		}

		$data = (string) $data;

		if ( function_exists( 'mb_convert_encoding' ) ) {
			$encoding = function_exists( 'mb_detect_encoding' ) ? \mb_detect_encoding( $data, 'UTF-8, ISO-8859-1', true ) : false;
			if ( ! in_array( $encoding, array( 'UTF-8', 'UTF8' ), true ) ) {
				$data = \mb_convert_encoding( $data, 'UTF-8', 'ISO-8859-1' );
			}
		} elseif ( function_exists( 'wpie_utf8_encode' ) ) {
			$data = wpie_utf8_encode( $data );
		}

		return $this->escape_data( $data );
	}

	/**
	 * Parse serialized filter rules and invoke parse_rule for each condition.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function manage_rules() {

		$wpie_export_condition = ( isset( $this->template_options['wpie_filter_rule'] ) && is_string( $this->template_options['wpie_filter_rule'] ) ) ? wpie_sanitize_field( stripslashes_deep( $this->template_options['wpie_filter_rule'] ) ) : '';
		if ( empty( $wpie_export_condition ) && isset( $this->template_options['wpie_export_condition'] ) && is_string( $this->template_options['wpie_export_condition'] ) ) {
			$wpie_export_condition = wpie_sanitize_field( stripslashes_deep( $this->template_options['wpie_export_condition'] ) );
		}

		if ( ! empty( $wpie_export_condition ) ) {

			$wpie_filter_rule = explode( '~`|`~', $wpie_export_condition );

			if ( is_array( $wpie_filter_rule ) && ! empty( $wpie_filter_rule ) ) {

				foreach ( $wpie_filter_rule as $data ) {

					if ( empty( $data ) || ! is_string( $data ) ) {
						continue;
					}

					$options = explode( '`|~`', $data );

					$filter = array();

					$rule = isset( $options[0] ) ? wp_unslash( $options[0] ) : '';

					if ( '' !== $rule ) {

						$filter_data = json_decode( $rule, true );

						if ( is_array( $filter_data ) && isset( $filter_data['type'] ) && ! empty( $filter_data['type'] ) ) {
							$filter              = $filter_data;
							$filter['element'] = sanitize_text_field( $filter_data['type'] );
							if ( isset( $filter['metaKey'] ) ) {
								$filter['metaKey'] = sanitize_text_field( $filter['metaKey'] );
							}
							if ( isset( $filter['taxName'] ) ) {
								$filter['taxName'] = sanitize_key( $filter['taxName'] );
							}
						} else {
							unset( $options, $filter, $rule, $filter_data );
							continue;
						}
						unset( $rule, $filter_data );
					} else {
						unset( $options, $filter, $rule );
						continue;
					}

					$raw_condition      = isset( $options[1] ) ? sanitize_key( wp_unslash( $options[1] ) ) : '';
					$allowed_conditions = array(
						'equals',
						'not_equals',
						'greater',
						'equals_or_greater',
						'less',
						'equals_or_less',
						'contains',
						'not_contains',
						'is_empty',
						'is_not_empty',
						'in',
						'not_in',
					);

					if ( ! in_array( $raw_condition, $allowed_conditions, true ) ) {
						unset( $options, $filter );
						continue;
					}
					$filter['condition'] = $raw_condition;

					$filter['value'] = isset( $options[2] ) ? wpie_sanitize_field( wp_unslash( $options[2] ) ) : '';

					$raw_clause       = isset( $options[3] ) ? strtoupper( trim( (string) wp_unslash( $options[3] ) ) ) : '';
					$filter['clause'] = in_array( $raw_clause, array( 'AND', 'OR' ), true ) ? $raw_clause : '';

					if ( ! empty( $filter['element'] ) && ! empty( $filter['condition'] ) ) {
						$this->parse_rule( $filter );
					}

					unset( $filter );
				}
			}

			unset( $wpie_filter_rule );
		}

		unset( $wpie_export_condition );
	}

	/**
	 * Generate a unique random suffix for internal field keys.
	 *
	 * @since 1.0.0
	 * @return string Unique string.
	 */
	protected function get_unique_str() {
		return uniqid() . '__' . wp_rand( 1, 999 );
	}

}

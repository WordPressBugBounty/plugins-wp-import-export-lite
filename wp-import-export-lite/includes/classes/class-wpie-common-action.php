<?php
/**
 * Common AJAX Actions Controller.
 *
 * Handles general AJAX endpoints: user capabilities, templates management,
 * background cron settings, process status updates, and template import/export.
 *
 * @since      1.0.0
 * @package    wpie
 * @subpackage wpie\Core
 * @author     VJinfotech <support@vjinfotech.com>
 */

defined( 'ABSPATH' ) || exit;

use WpieApp\Core\Helpers\Param;

if ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-security.php' ) ) {
	require_once WPIE_CLASSES_DIR . '/class-wpie-security.php';
}

/**
 * WPIE_Common_Actions Class
 *
 * Registers and executes common administrative AJAX actions.
 *
 * @since 1.0.0
 */
class WPIE_Common_Actions {

	/**
	 * Class Constructor.
	 *
	 * Registers AJAX action hooks for admin users.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_wpie_save_user_cap', array( $this, 'wpie_save_user_cap' ) );
		add_action( 'wp_ajax_wpie_get_user_cap', array( $this, 'wpie_get_user_cap' ) );
		add_action( 'wp_ajax_wpie_delete_tempaltes', array( $this, 'wpie_delete_tempaltes' ) );
		add_action( 'wp_ajax_wpie_tempalte_import', array( $this, 'wpie_tempalte_import' ) );
		add_action( 'wp_ajax_wpie_get_tempaltes', array( $this, 'wpie_get_tempalte_list' ) );
		add_action( 'wp_ajax_wpie_save_advance_option', array( $this, 'wpie_save_advance_option' ) );
		add_action( 'wp_ajax_wpie_update_process_status', array( $this, 'update_process_status' ) );
		add_action( 'wp_ajax_wpie_save_bg_cron_processing', array( $this, 'wpie_save_bg_cron_processing' ) );
		add_action( 'wp_ajax_wpie_change_license_status', array( $this, 'wpie_change_license_status' ) );
	}

	/**
	 * AJAX Handler: Saves customized capabilities for a specific user role.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wpie_save_user_cap() {
		\wpie\Security::verify_request( 'wpie_settings' );

		$new_export            = Param::postSanitized( 'wpie_cap_new_export', 'int', 0 );
		$manage_export         = Param::postSanitized( 'wpie_cap_manage_export', 'int', 0 );
		$new_import            = Param::postSanitized( 'wpie_cap_new_import', 'int', 0 );
		$manage_import         = Param::postSanitized( 'wpie_cap_manage_import', 'int', 0 );
		$cap_settings          = Param::postSanitized( 'wpie_cap_settings', 'int', 0 );
		$cap_ext               = Param::postSanitized( 'wpie_cap_ext', 'int', 0 );
		$add_shortcode         = Param::postSanitized( 'wpie_cap_add_shortcode', 'int', 0 );
		$update_superior_users = Param::postSanitized( 'wpie_cap_update_superior_users', 'int', 0 );
		$wpie_user_role        = Param::postSanitized( 'wpie_user_role', 'text', '' );

		$role = get_role( $wpie_user_role );

		if ( $role ) {
			if ( 1 === $new_export ) {
				if ( ! $role->has_cap( 'wpie_new_export' ) ) {
					$role->add_cap( 'wpie_new_export' );
				}
			} else {
				$role->remove_cap( 'wpie_new_export' );
			}

			if ( 1 === $manage_export ) {
				if ( ! $role->has_cap( 'wpie_manage_export' ) ) {
					$role->add_cap( 'wpie_manage_export' );
				}
			} else {
				$role->remove_cap( 'wpie_manage_export' );
			}

			if ( 1 === $new_import ) {
				if ( ! $role->has_cap( 'wpie_new_import' ) ) {
					$role->add_cap( 'wpie_new_import' );
				}
			} else {
				$role->remove_cap( 'wpie_new_import' );
			}

			if ( 1 === $manage_import ) {
				if ( ! $role->has_cap( 'wpie_manage_import' ) ) {
					$role->add_cap( 'wpie_manage_import' );
				}
			} else {
				$role->remove_cap( 'wpie_manage_import' );
			}

			if ( 1 === $cap_settings ) {
				if ( ! $role->has_cap( 'wpie_settings' ) ) {
					$role->add_cap( 'wpie_settings' );
				}
			} else {
				$role->remove_cap( 'wpie_settings' );
			}

			if ( 1 === $cap_ext ) {
				if ( ! $role->has_cap( 'wpie_extensions' ) ) {
					$role->add_cap( 'wpie_extensions' );
				}
			} else {
				$role->remove_cap( 'wpie_extensions' );
			}

			if ( 1 === $add_shortcode ) {
				if ( ! $role->has_cap( 'wpie_add_shortcode' ) ) {
					$role->add_cap( 'wpie_add_shortcode' );
				}
			} else {
				$role->remove_cap( 'wpie_add_shortcode' );
			}

			if ( 1 === $update_superior_users ) {
				if ( ! $role->has_cap( 'wpie_update_superior_users' ) ) {
					$role->add_cap( 'wpie_update_superior_users' );
				}
			} else {
				$role->remove_cap( 'wpie_update_superior_users' );
			}
		}

		$return_value = array(
			'status'  => 'success',
			'message' => __( 'Role Successfully Saved', 'wp-import-export-lite' ),
		);

		wp_send_json( $return_value );
	}

	/**
	 * AJAX Handler: Retrieves the active capabilities for a specific user role.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wpie_get_user_cap() {
		\wpie\Security::verify_request( 'wpie_settings' );

		$wpie_user_role = Param::requestSanitized( 'user_role', 'text', '' );
		$role           = get_role( $wpie_user_role );
		$cap            = array();

		if ( $role ) {
			if ( $role->has_cap( 'wpie_new_export' ) ) {
				$cap[] = 'wpie_new_export';
			}
			if ( $role->has_cap( 'wpie_manage_export' ) ) {
				$cap[] = 'wpie_manage_export';
			}
			if ( $role->has_cap( 'wpie_new_import' ) ) {
				$cap[] = 'wpie_new_import';
			}
			if ( $role->has_cap( 'wpie_manage_import' ) ) {
				$cap[] = 'wpie_manage_import';
			}
			if ( $role->has_cap( 'wpie_settings' ) ) {
				$cap[] = 'wpie_settings';
			}
			if ( $role->has_cap( 'wpie_extensions' ) ) {
				$cap[] = 'wpie_extensions';
			}
			if ( $role->has_cap( 'wpie_add_shortcode' ) ) {
				$cap[] = 'wpie_add_shortcode';
			}
			if ( $role->has_cap( 'wpie_update_superior_users' ) ) {
				$cap[] = 'wpie_update_superior_users';
			}
		}

		$return_value = array(
			'status' => 'success',
			'cap'    => $cap,
		);

		wp_send_json( $return_value );
	}

	/**
	 * AJAX Handler: Retrieves the template list as HTML option elements.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wpie_get_tempalte_list() {
		\wpie\Security::verify_request( 'wpie_settings' );

		$templates     = $this->wpie_get_templates();
		$template_html = '';

		if ( ! empty( $templates ) && is_array( $templates ) ) {
			foreach ( $templates as $data ) {
				$id      = isset( $data->id ) ? absint( $data->id ) : 0;
				$options = isset( $data->options ) ? maybe_unserialize( $data->options ) : array();
				$name    = isset( $options['wpie_template_name'] ) ? $options['wpie_template_name'] : '';

				if ( $id > 0 && '' !== trim( strval( $name ) ) ) {
					$template_html .= '<option value="' . esc_attr( $id ) . '">' . esc_html( $name ) . '</option>';
				}
			}
		}

		$return_value = array(
			'status' => 'success',
			'html'   => $template_html,
		);

		wp_send_json( $return_value );
	}

	/**
	 * Retrieve list of export jobs.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  int $limit Maximum records to return. Default 25.
	 *
	 * @return array Array of template database rows.
	 */
	public function get_export_list( $limit = 25 ) {
		global $wpdb;

		$limit = absint( $limit ) > 0 ? absint( $limit ) : 25;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpie_template WHERE `opration` IN ('export','schedule_export') ORDER BY `id` DESC LIMIT 0, %d",
				$limit
			)
		);
	}

	/**
	 * Retrieve list of import jobs.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  int $limit Maximum records to return. Default 25.
	 *
	 * @return array Array of template database rows.
	 */
	public function get_import_list( $limit = 25 ) {
		global $wpdb;

		$limit = absint( $limit ) > 0 ? absint( $limit ) : 25;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpie_template WHERE `opration` IN ('import','schedule_import') ORDER BY `id` DESC LIMIT 0, %d",
				$limit
			)
		);
	}

	/**
	 * Retrieve saved export and import templates.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array Array of saved template records.
	 */
	public function wpie_get_templates() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}wpie_template WHERE `opration` IN ('import_template','export_template')"
		);
	}

	/**
	 * AJAX Handler: Deletes specified templates and their associated storage directories.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wpie_delete_tempaltes() {
		\wpie\Security::verify_request( 'wpie_settings' );

		$ids = Param::requestSanitized( 'templates', 'text', '' );

		if ( ! empty( $ids ) ) {
			if ( is_string( $ids ) ) {
				$ids = explode( ',', $ids );
			}

			if ( ! is_array( $ids ) ) {
				$ids = array( $ids );
			}

			$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

			if ( ! empty( $ids ) ) {
				global $wpdb;

				foreach ( $ids as $id ) {
					if ( $id < 1 ) {
						continue;
					}

					$template = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT opration, options FROM {$wpdb->prefix}wpie_template WHERE `id` = %d LIMIT 0, 1",
							$id
						)
					);

					if ( ! $template ) {
						continue;
					}

					$opration = isset( $template->opration ) ? $template->opration : 'import';
					$options  = isset( $template->options ) ? maybe_unserialize( $template->options ) : array();
					if ( ! is_array( $options ) ) {
						$options = array();
					}

					if ( in_array( $opration, array( 'import', 'schedule_import', 'schedule_import_template' ), true ) ) {
						if ( 'schedule_import_template' === $opration ) {
							wp_clear_scheduled_hook( 'wpie_cron_schedule_import', array( $id ) );
						}

						$import_file = isset( $options['importFile'] ) && is_array( $options['importFile'] ) ? $options['importFile'] : array();

						foreach ( $import_file as $file_data ) {
							$base_dir = isset( $file_data['baseDir'] ) && is_string( $file_data['baseDir'] ) ? sanitize_file_name( $file_data['baseDir'] ) : '';

							if ( '' === trim( $base_dir ) || strpos( $base_dir, '..' ) !== false || strpos( $base_dir, "\0" ) !== false ) {
								continue;
							}

							$this->remove_dir( WPIE_UPLOAD_IMPORT_DIR . '/' . $base_dir, WPIE_UPLOAD_IMPORT_DIR );
						}
					} elseif ( in_array( $opration, array( 'export', 'schedule_export', 'schedule_export_template' ), true ) ) {
						if ( 'schedule_export_template' === $opration ) {
							wp_clear_scheduled_hook( 'wpie_cron_schedule_export', array( $id ) );
						}

						$file_dir = isset( $options['fileDir'] ) && is_string( $options['fileDir'] ) ? sanitize_file_name( $options['fileDir'] ) : '';

						if ( '' === trim( $file_dir ) || strpos( $file_dir, '..' ) !== false || strpos( $file_dir, "\0" ) !== false ) {
							continue;
						}

						$this->remove_dir( WPIE_UPLOAD_EXPORT_DIR . '/' . $file_dir, WPIE_UPLOAD_EXPORT_DIR );
					}
				}

				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}wpie_template WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders in prepared query.
						$ids
					)
				);
			}
		}

		$data = array(
			'status'  => 'success',
			'message' => __( 'Templates Successfully Deleted', 'wp-import-export-lite' ),
		);

		wp_send_json( $data );
	}

	/**
	 * Safely removes a directory and its contents, strictly confined to the allowed base directory.
	 * Resolves canonical paths using realpath() to prevent arbitrary directory deletion via directory traversal.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param  string $targetDir Directory to delete. Default empty string.
	 * @param  string $baseDir   Allowed base directory boundary (defaults to WPIE_UPLOAD_DIR). Default empty string.
	 *
	 * @return bool True if directory was removed, false otherwise.
	 */
	private function remove_dir( $targetDir = '', $baseDir = '' ) {
		if ( empty( $targetDir ) || ! is_string( $targetDir ) ) {
			return false;
		}

		// Disallow directory traversal sequences and null bytes.
		if ( strpos( $targetDir, '..' ) !== false || strpos( $targetDir, "\0" ) !== false ) {
			return false;
		}

		// If base directory is not explicitly specified, default to WPIE_UPLOAD_DIR.
		if ( empty( $baseDir ) ) {
			$baseDir = defined( 'WPIE_UPLOAD_DIR' ) ? WPIE_UPLOAD_DIR : '';
		}

		if ( empty( $baseDir ) || ! is_string( $baseDir ) || strpos( $baseDir, "\0" ) !== false ) {
			return false;
		}

		if ( ! is_dir( $baseDir ) ) {
			return false;
		}

		$real_base = realpath( $baseDir );
		if ( false === $real_base ) {
			return false;
		}

		// If target is a symlink, remove it directly without recursing into target.
		if ( is_link( $targetDir ) ) {
			return @wp_delete_file( $targetDir );
		}

		if ( ! is_dir( $targetDir ) ) {
			return false;
		}

		$real_target = realpath( $targetDir );
		if ( false === $real_target ) {
			return false;
		}

		$base_norm   = trailingslashit( wp_normalize_path( $real_base ) );
		$target_norm = trailingslashit( wp_normalize_path( $real_target ) );

		// Target directory must be strictly inside the base directory, never the base directory itself.
		if ( $target_norm === $base_norm || strpos( $target_norm, $base_norm ) !== 0 ) {
			return false;
		}

		// Also ensure target is strictly inside WPIE_UPLOAD_DIR when defined.
		if ( defined( 'WPIE_UPLOAD_DIR' ) ) {
			$real_upload = realpath( WPIE_UPLOAD_DIR );
			if ( false !== $real_upload ) {
				$upload_norm = trailingslashit( wp_normalize_path( $real_upload ) );
				if ( $target_norm === $upload_norm || strpos( $target_norm, $upload_norm ) !== 0 ) {
					return false;
				}
			}
		}

		$cdir = scandir( $real_target );

		if ( is_array( $cdir ) && ! empty( $cdir ) ) {
			foreach ( $cdir as $value ) {
				if ( ! in_array( $value, array( '.', '..' ), true ) ) {
					$child_path = $real_target . DIRECTORY_SEPARATOR . $value;
					if ( is_link( $child_path ) ) {
						@wp_delete_file( $child_path );
					} elseif ( is_dir( $child_path ) ) {
						$this->remove_dir( $child_path, $baseDir );
					} else {
						@wp_delete_file( $child_path );
					}
				}
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Custom recursive directory removal with path validation.
		return (bool) @rmdir( $real_target );
	}

	/**
	 * AJAX Handler: Saves advanced plugin options (e.g. delete data on uninstall).
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wpie_save_advance_option() {
		\wpie\Security::verify_request( 'wpie_settings' );

		$is_delete_data = Param::requestSanitized( 'is_delete_data', 'absint', 0 );

		update_option( 'wpie_delete_on_uninstall', $is_delete_data );

		$return_value = array(
			'status'  => 'success',
			'message' => __( 'Settings Successfully Saved', 'wp-import-export-lite' ),
		);

		wp_send_json( $return_value );
	}

	/**
	 * AJAX Handler: Imports exported template configuration from an uploaded TXT file.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wpie_tempalte_import() {
		\wpie\Security::verify_request( 'wpie_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via \wpie\Security::verify_request()
		if ( ! isset( $_FILES['wpie_template_file'] ) || ! is_array( $_FILES['wpie_template_file'] ) ) {
			wp_send_json(
				array(
					'status'  => 'error',
					'message' => __( 'No file uploaded', 'wp-import-export-lite' ),
				)
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified via \wpie\Security::verify_request()
		$file_input = $_FILES['wpie_template_file'];

		$filename   = isset( $file_input['name'] ) ? sanitize_file_name( wp_unslash( $file_input['name'] ) ) : '';
		$validate   = \wp_check_filetype( $filename );
		$ext        = isset( $validate['ext'] ) && is_string( $validate['ext'] ) ? strtolower( $validate['ext'] ) : '';
		$type       = isset( $validate['type'] ) && is_string( $validate['type'] ) ? $validate['type'] : '';
		$mime_check = isset( $file_input['type'] ) && is_string( $file_input['type'] ) ? sanitize_mime_type( wp_unslash( $file_input['type'] ) ) : '';

		if ( 'txt' !== $ext || 'text/plain' !== $type || 'text/plain' !== $mime_check ) {
			wp_send_json(
				array(
					'status'  => 'error',
					'message' => __( 'File type is not allowed', 'wp-import-export-lite' ),
				)
			);
		}

		if ( isset( $file_input['tmp_name'] ) && is_uploaded_file( $file_input['tmp_name'] ) ) {
			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$movefile = wp_handle_upload( $file_input, array( 'test_form' => false, 'test_type' => false ) );

			if ( $movefile && isset( $movefile['file'] ) && ! isset( $movefile['error'] ) ) {
				$file_path     = $movefile['file'];
				$template_data = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

				if ( ! empty( $template_data ) ) {
					$template_data = json_decode( $template_data, true );

					if ( ! empty( $template_data ) && is_array( $template_data ) ) {
						global $wpdb;

						$time         = current_time( 'mysql' );
						$current_user = \wp_get_current_user();
						$username     = ( $current_user && isset( $current_user->user_login ) ) ? $current_user->user_login : '';

						$allowed_operations = array(
							'export',
							'import',
							'schedule_export',
							'schedule_import',
							'import_template',
							'export_template',
							'schedule_import_template',
							'schedule_export_template',
						);

						foreach ( $template_data as $template ) {
							if ( ! is_array( $template ) ) {
								continue;
							}

							$opration = isset( $template['opration'] ) ? sanitize_key( $template['opration'] ) : '';
							if ( ! in_array( $opration, $allowed_operations, true ) ) {
								continue;
							}

							$opration_type = isset( $template['opration_type'] ) ? sanitize_key( $template['opration_type'] ) : '';
							$options       = isset( $template['options'] ) && is_array( $template['options'] ) ? $template['options'] : array();

							// Sanitize file options to strictly prevent directory traversal sequences.
							if ( isset( $options['fileDir'] ) && is_string( $options['fileDir'] ) ) {
								$clean_fdir = str_replace( "\0", '', $options['fileDir'] );
								while ( strpos( $clean_fdir, '..' ) !== false ) {
									$clean_fdir = str_replace( '..', '', $clean_fdir );
								}
								$options['fileDir'] = sanitize_file_name( $clean_fdir );
							}

							if ( isset( $options['fileName'] ) && is_string( $options['fileName'] ) ) {
								$clean_fname = str_replace( "\0", '', $options['fileName'] );
								while ( strpos( $clean_fname, '..' ) !== false ) {
									$clean_fname = str_replace( '..', '', $clean_fname );
								}
								$options['fileName'] = sanitize_file_name( $clean_fname );
							}

							if ( isset( $options['importFile'] ) && is_array( $options['importFile'] ) ) {
								foreach ( $options['importFile'] as $import_key => $import_val ) {
									if ( is_array( $import_val ) ) {
										if ( isset( $import_val['baseDir'] ) && is_string( $import_val['baseDir'] ) ) {
											$clean_bdir = str_replace( "\0", '', $import_val['baseDir'] );
											while ( strpos( $clean_bdir, '..' ) !== false ) {
												$clean_bdir = str_replace( '..', '', $clean_bdir );
											}
											$options['importFile'][ $import_key ]['baseDir'] = sanitize_file_name( $clean_bdir );
										}
										if ( isset( $import_val['fileDir'] ) && is_string( $import_val['fileDir'] ) ) {
											$clean_ifdir = str_replace( "\0", '', $import_val['fileDir'] );
											while ( strpos( $clean_ifdir, '..' ) !== false ) {
												$clean_ifdir = str_replace( '..', '', $clean_ifdir );
											}
											$options['importFile'][ $import_key ]['fileDir'] = sanitize_file_name( $clean_ifdir );
										}
									}
								}
							}

							$data = array(
								'status'           => 'completed',
								'opration'         => $opration,
								'username'         => $username,
								'unique_id'        => uniqid( 'wpie_' ),
								'opration_type'    => $opration_type,
								'options'          => maybe_serialize( $options ),
								'process_log'      => '',
								'process_lock'     => 0,
								'create_date'      => $time,
								'last_update_date' => $time,
							);

							$wpdb->insert( $wpdb->prefix . 'wpie_template', $data );
						}

						if ( file_exists( $file_path ) ) {
							@wp_delete_file( $file_path );
						}

						wp_send_json(
							array(
								'status'  => 'success',
								'message' => __( 'Templates Successfully Imported', 'wp-import-export-lite' ),
							)
						);
					}
				}

				if ( file_exists( $file_path ) ) {
					@wp_delete_file( $file_path );
				}

				wp_send_json(
					array(
						'status'  => 'error',
						'message' => __( 'Empty Data', 'wp-import-export-lite' ),
					)
				);
			} else {
				wp_send_json(
					array(
						'status'  => 'error',
						'message' => isset( $movefile['error'] ) ? $movefile['error'] : __( 'Error when moving uploaded file', 'wp-import-export-lite' ),
					)
				);
			}
		}

		wp_send_json(
			array(
				'status'  => 'error',
				'message' => __( 'File upload failed', 'wp-import-export-lite' ),
			)
		);
	}

	/**
	 * AJAX Handler: Updates status of a running import/export job (background, pause, stop).
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function update_process_status() {
		\wpie\Security::verify_request( 'wpie_settings' );

		global $wpdb;

		$wpie_import_id = Param::requestSanitized( 'wpie_process_id', 'absint', 0 );

		if ( $wpie_import_id > 0 ) {
			$process_status = Param::requestSanitized( 'process_status', 'text', '' );
			$new_status     = '';
			$message        = '';

			if ( 'bg' === $process_status ) {
				$new_status = 'background';
				$message    = __( 'Background Process Successfully Set', 'wp-import-export-lite' );
			} elseif ( 'stop' === $process_status ) {
				$new_status = 'stopped';
				$message    = __( 'Process Stopped Successfully', 'wp-import-export-lite' );
			} elseif ( 'pause' === $process_status ) {
				$new_status = 'paused';
				$message    = __( 'Process Paused Successfully', 'wp-import-export-lite' );
			}

			if ( '' !== $new_status ) {
				$final_data = array(
					'last_update_date' => current_time( 'mysql' ),
					'status'           => $new_status,
				);

				$wpdb->update( $wpdb->prefix . 'wpie_template', $final_data, array( 'id' => $wpie_import_id ) );

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
		}

		wp_send_json(
			array(
				'status'  => 'error',
				'message' => __( 'Template id not found', 'wp-import-export-lite' ),
			)
		);
	}

	/**
	 * AJAX Handler: Configures background cron processing method and generates secure token.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wpie_save_bg_cron_processing() {
		\wpie\Security::verify_request( 'wpie_settings' );

		$wpie_bg_and_cron_processing = get_option( 'wpie_bg_and_cron_processing' );
		$cron_data                   = maybe_unserialize( $wpie_bg_and_cron_processing );

		if ( ! is_array( $cron_data ) ) {
			$cron_data = array(
				'token' => wp_generate_password( 32, false ),
			);
		}

		// Ensure token is strong and cryptographically random, not predictable time().
		if ( empty( $cron_data['token'] ) ) {
			$cron_data['token'] = wp_generate_password( 32, false );
		}

		$cron_method          = Param::requestSanitized( 'method', 'key', 'wp' ) === 'external' ? 'external' : 'wp';
		$cron_data['method']  = $cron_method;

		update_option( 'wpie_bg_and_cron_processing', maybe_serialize( $cron_data ) );

		wp_send_json(
			array(
				'status'  => 'success',
				'message' => __( 'Settings Successfully Saved', 'wp-import-export-lite' ),
			)
		);
	}

	/**
	 * AJAX Handler: Toggles or activates plugin license.
	 * Handled safely with class existence verification for Lite edition.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wpie_change_license_status() {
		\wpie\Security::verify_request( 'wpie_settings' );

		if ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-license-manager.php' ) ) {
			require_once WPIE_CLASSES_DIR . '/class-wpie-license-manager.php';
		}

		if ( class_exists( '\wpie\license\WPIE_License_Manager' ) ) {
			$license = new \wpie\license\WPIE_License_Manager(
				WPIE_PLUGIN_API,
				WPIE_PLUGIN_FILE,
				array(
					'version'        => WPIE_PLUGIN_VERSION,
					'license_db_key' => 'wpie_license',
					'author'         => 'vjinfotech',
				)
			);

			$license->wpie_change_license_status();
		} else {
			wp_send_json(
				array(
					'status'  => 'error',
					'message' => __( 'License management is available in the Pro version.', 'wp-import-export-lite' ),
				)
			);
		}
	}
}

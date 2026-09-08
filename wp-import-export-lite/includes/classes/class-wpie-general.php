<?php
/**
 * Main Administrative & Core Lifecycle Controller.
 *
 * Manages menus, asset enqueuing, file downloads, database checks,
 * security headers, rewrite rules, and notices.
 *
 * @since      1.0.0
 * @package    wpie
 * @subpackage wpie\Core
 * @author     VJinfotech <support@vjinfotech.com>
 */

namespace wpie\core;

use wpie\Security;
use WpieApp\Core\Helpers\Param;
use WpieApp\Core\Helpers\Sanitizer;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-security.php' ) ) {
	require_once WPIE_CLASSES_DIR . '/class-wpie-security.php';
}

/**
 * WPIE_General Class
 *
 * Core coordinator for administrative screens, assets, database, and file protection.
 *
 * @since 1.0.0
 */
class WPIE_General {

	/**
	 * Recognized WPIE admin pages.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @var    array
	 */
	private static $wpie_page = array(
		'wpie-new-export',
		'wpie-new-import',
		'wpie-extensions',
		'wpie-settings',
		'wpie-manage-import',
		'wpie-manage-export',
	);

	/**
	 * Constructor.
	 *
	 * Registers hooks for admin screens, assets, database, and filters.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'wpie_set_menu' ) );
			add_action( 'init', array( __CLASS__, 'wpie_db_check' ), 1 );
			add_action( 'admin_head', array( __CLASS__, 'wpie_hide_all_notice_to_admin_side' ), 10000 );
			add_filter( 'admin_footer_text', array( __CLASS__, 'wpie_replace_footer_admin' ) );
			add_filter( 'update_footer', array( __CLASS__, 'wpie_replace_footer_version' ), 1234 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'wpie_set_admin_css' ), 10 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'wpie_set_admin_js' ), 10 );
			add_action( 'init', array( $this, 'wpie_process_file_download' ), 10 );
			add_action( 'admin_notices', array( __CLASS__, 'wpie_admin_notices' ), 10099 );
			add_filter( 'mod_rewrite_rules', array( __CLASS__, 'mod_rewrite_rules' ) );
			add_action( 'admin_init', array( __CLASS__, 'update_file_security' ) );
			add_filter( 'robots_txt', array( __CLASS__, 'update_robots_txt' ), 10, 2 );
			add_action( 'wp_loaded', array( __CLASS__, 'hide_notices' ) );
			add_action( 'shutdown', array( __CLASS__, 'flush_rewrite_rules' ) );
			add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
			add_filter( 'plugin_action_links_' . plugin_basename( WPIE_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );

			register_deactivation_hook( WPIE_PLUGIN_FILE, array( __CLASS__, 'deactivation' ) );
		}

		add_action( 'init', array( __CLASS__, 'wpie_load_textdomain' ) );
		add_filter( 'wpmu_drop_tables', array( __CLASS__, 'wpmu_drop_tables' ) );
		add_filter( 'woocommerce_order_number', array( __CLASS__, 'wpie_woocommerce_order_number' ), 9999, 2 );
	}

	/**
	 * Plugin deactivation routine.
	 *
	 * Clears scheduled cron events and pauses active background jobs.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function deactivation() {
		wp_clear_scheduled_hook( 'wpie_cron_schedule_import' );
		wp_clear_scheduled_hook( 'wpie_cron_schedule_export' );

		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->prefix}wpie_template WHERE `opration` IN ('schedule_export_template','schedule_import_template')" );
		$wpdb->query( "UPDATE {$wpdb->prefix}wpie_template SET `status` = 'paused' WHERE status LIKE '%background%'" );
	}

	/**
	 * Flush rewrite rules once for upload directory protection.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function flush_rewrite_rules() {
		if ( self::is_apache() && self::is_htaccess_writable() && false === get_option( 'wpie_flush_rewrite_rules', false ) ) {
			flush_rewrite_rules();
			update_option( 'wpie_flush_rewrite_rules', 1 );
		}
	}

	/**
	 * Removes plugin tables when a Multisite blog is deleted.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  array $tables Existing list of tables to drop.
	 *
	 * @return array Modified list of tables.
	 */
	public static function wpmu_drop_tables( $tables = array() ) {
		if ( ! is_array( $tables ) ) {
			$tables = array();
		}

		global $wpdb;
		$tables[] = $wpdb->prefix . 'wpie_template';

		return $tables;
	}

	/**
	 * Appends extra links on the Plugins list screen row.
	 *
	 * @since  1.4.1
	 * @access public
	 *
	 * @param  array  $links Existing row meta links.
	 * @param  string $file  Plugin base file path.
	 *
	 * @return array Updated row meta links.
	 */
	public static function plugin_row_meta( $links, $file ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		if ( plugin_basename( WPIE_PLUGIN_FILE ) !== $file ) {
			return $links;
		}

		$more = array(
			'<a href="' . esc_url( WPIE_DOC_URL ) . '">' . esc_html__( 'Documentation', 'wp-import-export-lite' ) . '</a>',
			'<a href="' . esc_url( WPIE_SUPPORT_URL ) . '">' . esc_html__( 'Support', 'wp-import-export-lite' ) . '</a>',
		);

		return array_merge( $links, $more );
	}

	/**
	 * Appends action links on the Plugins screen.
	 *
	 * @since  1.4.1
	 * @access public
	 *
	 * @param  array $links Existing action links.
	 *
	 * @return array Updated action links.
	 */
	public static function plugin_action_links( $links = array() ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		$export_url = add_query_arg( array( 'page' => 'wpie-new-export' ), admin_url( 'admin.php' ) );
		$import_url = add_query_arg( array( 'page' => 'wpie-new-import' ), admin_url( 'admin.php' ) );

		$plugin_links = array(
			'<a href="' . esc_url( $import_url ) . '">' . esc_html__( 'Import', 'wp-import-export-lite' ) . '</a>',
			'<a href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export', 'wp-import-export-lite' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Processes download requests for exports, imports, logs, and templates.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wpie_process_file_download() {
		$download_export_id     = Param::requestSanitized( 'wpie_download_export_id', 'absint', 0 );
		$download_import_id     = Param::requestSanitized( 'wpie_download_import_id', 'absint', 0 );
		$download_import_log_id = Param::requestSanitized( 'wpie_download_import_log_id', 'absint', 0 );
		$template_list          = Param::request( 'wpie_template_list', array() );
		$download_file_id       = Param::requestSanitized( 'wpie_download_file', 'absint', 0 );

		if ( $download_export_id > 0 ) {
			Security::verify_request( 'wpie_new_export' );

			$current_data = $this->get_template_data_by_id( $download_export_id );
			$options      = ( $current_data && isset( $current_data->options ) ) ? maybe_unserialize( $current_data->options ) : array();

			$filename = isset( $options['fileName'] ) ? sanitize_file_name( $options['fileName'] ) : '';
			$filedir  = isset( $options['fileDir'] ) ? sanitize_file_name( $options['fileDir'] ) : '';

			$file_path = WPIE_UPLOAD_EXPORT_DIR . '/' . $filedir . '/' . $filename;

			$this->wpie_download_file( $file_path );
		} elseif ( $download_import_id > 0 ) {
			Security::verify_request( 'wpie_manage_import' );

			$current_data = $this->get_template_data_by_id( $download_import_id );
			$options      = ( $current_data && isset( $current_data->options ) ) ? maybe_unserialize( $current_data->options ) : array();

			$active_file = isset( $options['activeFile'] ) ? sanitize_key( $options['activeFile'] ) : '';
			$import_file = isset( $options['importFile'] ) && is_array( $options['importFile'] ) ? $options['importFile'] : array();
			$file_data   = isset( $import_file[ $active_file ] ) && is_array( $import_file[ $active_file ] ) ? $import_file[ $active_file ] : array();

			$file_dir  = isset( $file_data['baseDir'] ) ? sanitize_file_name( $file_data['baseDir'] ) : '';
			$file_name = isset( $file_data['originalName'] ) ? sanitize_file_name( $file_data['originalName'] ) : '';

			$file_path = WPIE_UPLOAD_IMPORT_DIR . '/' . $file_dir . '/original/' . $file_name;

			$this->wpie_download_file( $file_path );
		} elseif ( $download_import_log_id > 0 ) {
			Security::verify_request( 'wpie_manage_import' );

			$current_data = $this->get_template_data_by_id( $download_import_log_id );
			$options      = ( $current_data && isset( $current_data->options ) ) ? maybe_unserialize( $current_data->options ) : array();

			$active_file = isset( $options['activeFile'] ) ? sanitize_key( $options['activeFile'] ) : '';
			$import_file = isset( $options['importFile'] ) && is_array( $options['importFile'] ) ? $options['importFile'] : array();
			$file_data   = isset( $import_file[ $active_file ] ) && is_array( $import_file[ $active_file ] ) ? $import_file[ $active_file ] : array();

			$base_dir = isset( $file_data['baseDir'] ) ? sanitize_file_name( $file_data['baseDir'] ) : '';

			$file_path = WPIE_UPLOAD_IMPORT_DIR . '/' . $base_dir . '/log/import_log.txt';

			$this->wpie_download_file( $file_path );
		} elseif ( ! empty( $template_list ) ) {
			Security::verify_request( 'wpie_settings' );

			$templates = Sanitizer::clean( $template_list, 'int_array' );

			if ( is_array( $templates ) && ! empty( $templates ) ) {
				$ids = array_values( array_filter( array_map( 'absint', $templates ) ) );

				if ( ! empty( $ids ) ) {
					global $wpdb;

					$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
					$results      = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}wpie_template WHERE `id` IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders in prepared query.
							$ids
						)
					);

					$data = array();
					if ( $results && ! empty( $results ) ) {
						foreach ( $results as $result ) {
							$result->options = isset( $result->options ) ? maybe_unserialize( $result->options ) : '';
							$data[]          = $result;
						}
					}

					$file_data = wp_json_encode( $data );
					$file_path = WPIE_UPLOAD_TEMP_DIR . '/' . time() . '_templates.txt';

					// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct file operations required for temp template export.
					$handle = @fopen( $file_path, 'wb' );
					if ( false !== $handle ) {
						fwrite( $handle, $file_data );
						fclose( $handle );

						$this->wpie_download_file( $file_path );
					}
					// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				}
			}
		} elseif ( $download_file_id > 0 ) {
			Security::verify_request( 'wpie_settings' );

			$current_data = $this->get_template_data_by_id( $download_file_id );
			$options      = ( $current_data && isset( $current_data->options ) ) ? maybe_unserialize( $current_data->options ) : array();

			$active_file = isset( $options['activeFile'] ) ? sanitize_key( $options['activeFile'] ) : '';
			$import_file = isset( $options['importFile'] ) && is_array( $options['importFile'] ) ? $options['importFile'] : array();
			$file_data   = isset( $import_file[ $active_file ] ) && is_array( $import_file[ $active_file ] ) ? $import_file[ $active_file ] : array();

			$file_name = isset( $file_data['fileName'] ) ? sanitize_file_name( $file_data['fileName'] ) : '';
			$file_dir  = isset( $file_data['fileDir'] ) ? sanitize_file_name( $file_data['fileDir'] ) : '';

			$file_path = WPIE_UPLOAD_IMPORT_DIR . '/' . $file_dir . '/' . $file_name;

			$this->wpie_download_file( $file_path );
		}
	}

	/**
	 * Streams file to client with strict canonical path traversal validation and header sanitization.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param  string $filePath Absolute path to the file to download.
	 *
	 * @return void
	 */
	private function wpie_download_file( $filePath ) {
		if ( empty( $filePath ) || ! is_string( $filePath ) || strpos( $filePath, "\0" ) !== false ) {
			wp_die( esc_html__( 'Invalid file path.', 'wp-import-export-lite' ) );
		}

		$real_path = realpath( $filePath );
		if ( false === $real_path || ! is_file( $real_path ) ) {
			wp_die( esc_html__( 'File does not exist.', 'wp-import-export-lite' ) );
		}

		$upload_base = defined( 'WPIE_UPLOAD_DIR' ) ? realpath( WPIE_UPLOAD_DIR ) : false;
		if ( false === $upload_base ) {
			wp_die( esc_html__( 'Upload directory not found.', 'wp-import-export-lite' ) );
		}

		$real_dir_norm   = trailingslashit( wp_normalize_path( dirname( $real_path ) ) );
		$upload_base_norm = trailingslashit( wp_normalize_path( $upload_base ) );

		// Strict path boundary validation: ensure requested file is inside WPIE_UPLOAD_DIR.
		if ( strpos( $real_dir_norm, $upload_base_norm ) !== 0 ) {
			wp_die( esc_html__( 'Unauthorized file download.', 'wp-import-export-lite' ) );
		}

		$filename = sanitize_file_name( basename( $real_path ) );

		while ( ob_get_level() > 0 ) {
			@ob_end_clean();
		}

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $real_path ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Direct file download stream.
		readfile( $real_path );
		exit;
	}

	/**
	 * Retrieves template options row by template ID.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param  int $template_id Template ID. Default 0.
	 *
	 * @return object|false Template database record, or false if not found.
	 */
	private function get_template_data_by_id( $template_id = 0 ) {
		$template_id = absint( $template_id );
		if ( $template_id > 0 ) {
			global $wpdb;

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `options` FROM {$wpdb->prefix}wpie_template WHERE `id` = %d",
					$template_id
				)
			);

			if ( ! empty( $results ) && isset( $results[0] ) ) {
				return $results[0];
			}
		}

		return false;
	}

	/**
	 * Checks whether the upload directory is writable.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return int 1 if writable, 0 otherwise.
	 */
	public static function is_upload_dir_writable() {
		if ( is_readable( WPIE_UPLOAD_DIR ) && wp_is_writable( WPIE_UPLOAD_DIR ) ) {
			return 1;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Attempt to adjust permissions for upload directory.
		@chmod( WPIE_UPLOAD_DIR, 0755 );

		if ( wp_is_writable( WPIE_UPLOAD_DIR ) ) {
			return 1;
		}

		return 0;
	}

	/**
	 * Enqueues admin stylesheet assets based on active screen.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function wpie_set_admin_css() {
		$page = Param::getSanitized( 'page', 'key', '' );

		wp_register_style( 'wpie-global-admin-css', WPIE_CSS_URL . '/wpie-global-admin.min.css', array(), WPIE_PLUGIN_VERSION );
		wp_enqueue_style( 'wpie-global-admin-css' );

		if ( ! empty( $page ) && in_array( $page, self::$wpie_page, true ) ) {
			wp_register_style( 'wpie-export-admin-css', WPIE_CSS_URL . '/wpie-export-admin.min.css', array(), WPIE_PLUGIN_VERSION );
			wp_register_style( 'wpie-general-admin-css', WPIE_CSS_URL . '/wpie-general-admin.min.css', array(), WPIE_PLUGIN_VERSION );
			wp_register_style( 'wpie-import-admin-css', WPIE_CSS_URL . '/wpie-import-admin.min.css', array(), WPIE_PLUGIN_VERSION );

			wp_enqueue_style( 'fontawesome-css', WPIE_CSS_URL . '/fontawesome-all.css', array(), WPIE_PLUGIN_VERSION );
			wp_enqueue_style( 'bootstrap-css', WPIE_CSS_URL . '/bootstrap.css', array(), WPIE_PLUGIN_VERSION );
			wp_enqueue_style( 'animate-css', WPIE_CSS_URL . '/animate.css', array(), WPIE_PLUGIN_VERSION );
			wp_enqueue_style( 'chosen-css', WPIE_CSS_URL . '/chosen.css', array(), WPIE_PLUGIN_VERSION );
			wp_enqueue_style( 'tipso-css', WPIE_CSS_URL . '/tipso.css', array(), WPIE_PLUGIN_VERSION );

			if ( 'wpie-new-export' === $page ) {
				wp_enqueue_style( 'wpie-export-admin-css' );
				wp_enqueue_style( 'datatables.bootstrap5-css', WPIE_CSS_URL . '/dataTables.bootstrap5.min.css', array(), WPIE_PLUGIN_VERSION );
			} elseif ( 'wpie-new-import' === $page ) {
				wp_enqueue_style( 'wpie-import-admin-css' );
				wp_enqueue_style( 'datatables.bootstrap5-css', WPIE_CSS_URL . '/dataTables.bootstrap5.min.css', array(), WPIE_PLUGIN_VERSION );
			} elseif ( in_array( $page, array( 'wpie-extensions', 'wpie-settings', 'wpie-manage-export', 'wpie-manage-import' ), true ) ) {
				wp_enqueue_style( 'wpie-general-admin-css' );
			}
		}
	}

	/**
	 * Enqueues and localizes admin scripts and options.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function wpie_set_admin_js() {
		$page = Param::getSanitized( 'page', 'key', '' );

		if ( ! empty( $page ) && in_array( $page, self::$wpie_page, true ) ) {
			wp_register_script( 'wpie-export-admin-js', WPIE_JS_URL . '/wpie-export-admin.min.js', array( 'jquery' ), WPIE_PLUGIN_VERSION, true );
			wp_register_script( 'wpie-general-admin-js', WPIE_JS_URL . '/wpie-general-admin.min.js', array( 'jquery' ), WPIE_PLUGIN_VERSION, true );
			wp_register_script( 'wpie-import-admin-js', WPIE_JS_URL . '/wpie-import-admin.min.js', array( 'jquery' ), WPIE_PLUGIN_VERSION, true );

			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'bootstrap-js', WPIE_JS_URL . '/bootstrap.js', array( 'jquery' ), WPIE_PLUGIN_VERSION, true );
			wp_enqueue_script( 'bootstrap-notify-js', WPIE_JS_URL . '/bootstrap-notify.js', array( 'jquery' ), WPIE_PLUGIN_VERSION, true );
			wp_enqueue_script( 'chosen-js', WPIE_JS_URL . '/chosen.js', array( 'jquery' ), WPIE_PLUGIN_VERSION, true );
			wp_enqueue_script( 'tipso-js', WPIE_JS_URL . '/tipso.js', array( 'jquery' ), WPIE_PLUGIN_VERSION, true );

			if ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-extensions.php' ) ) {
				require_once WPIE_CLASSES_DIR . '/class-wpie-extensions.php';
			}

			$wpie_ext_data = array();
			if ( class_exists( '\wpie\addons\WPIE_Extension' ) ) {
				$wpie_ext      = new \wpie\addons\WPIE_Extension();
				$wpie_ext_data = $wpie_ext->wpie_get_activated_ext();
				unset( $wpie_ext );
			}

			$is_upload_writable = self::is_upload_dir_writable();
			$nonce              = wp_create_nonce( 'wpie-security' );

			if ( 'wpie-new-export' === $page ) {
				wp_enqueue_script( 'wpie-export-admin-js' );

				$wpie_localize_script_data = array(
					'wpieAjaxURL'         => admin_url( 'admin-ajax.php' ),
					'wpieSiteURL'         => site_url(),
					'wpieUploadURL'       => WPIE_UPLOAD_URL,
					'wpieUploadDir'       => WPIE_UPLOAD_DIR,
					'wpiePluginURL'       => WPIE_PLUGIN_URL,
					'wpieImageURL'        => WPIE_IMAGES_URL,
					'wpieLocalizeText'    => self::wpie_load_msg(),
					'wpieSiteUrl'         => home_url(),
					'wpiePluginData'      => array(),
					'isWcActive'          => class_exists( 'WooCommerce' ),
					'wpieExtensions'      => $wpie_ext_data,
					'isUploadDirWritable' => $is_upload_writable,
					'wpieSecurity'        => $nonce,
				);

				wp_localize_script( 'wpie-export-admin-js', 'wpiePluginSettings', $wpie_localize_script_data );

				wp_enqueue_script( 'datatables-js', WPIE_JS_URL . '/jquery.dataTables.min.js', array( 'jquery' ), WPIE_PLUGIN_VERSION, true );
				wp_enqueue_script( 'datatables-ellipsis-js', WPIE_JS_URL . '/ellipsis.js', array( 'jquery', 'datatables-js' ), WPIE_PLUGIN_VERSION, true );
				wp_enqueue_script( 'editable-js', WPIE_JS_URL . '/editable.js', array( 'jquery' ), WPIE_PLUGIN_VERSION, true );
				wp_enqueue_script( 'dataTables.bootstrap5-js', WPIE_JS_URL . '/dataTables.bootstrap5.min.js', array( 'jquery', 'bootstrap-js' ), WPIE_PLUGIN_VERSION, true );
				wp_enqueue_script( 'jquery-ui-sortable' );
			} elseif ( 'wpie-new-import' === $page ) {
				wp_enqueue_script( 'wpie-import-admin-js' );

				$wpie_localize_script_data = array(
					'wpieAjaxURL'         => admin_url( 'admin-ajax.php' ),
					'wpieSiteURL'         => site_url(),
					'wpieUploadURL'       => WPIE_UPLOAD_URL,
					'wpieUploadDir'       => WPIE_UPLOAD_DIR,
					'wpiePluginURL'       => WPIE_PLUGIN_URL,
					'wpieImageURL'        => WPIE_IMAGES_URL,
					'wpieLocalizeText'    => self::wpie_load_msg(),
					'wpieSiteUrl'         => home_url(),
					'wpiePluginData'      => array(),
					'isWcActive'          => class_exists( 'WooCommerce', false ),
					'wpieExtensions'      => $wpie_ext_data,
					'isUploadDirWritable' => $is_upload_writable,
					'wpieSecurity'        => $nonce,
				);

				wp_localize_script( 'wpie-import-admin-js', 'wpiePluginSettings', $wpie_localize_script_data );

				wp_enqueue_script( 'datatables-js', WPIE_JS_URL . '/jquery.dataTables.min.js', array( 'jquery' ), WPIE_PLUGIN_VERSION, true );
				wp_enqueue_script( 'datatables-ellipsis-js', WPIE_JS_URL . '/ellipsis.js', array( 'jquery', 'datatables-js' ), WPIE_PLUGIN_VERSION, true );
				wp_enqueue_script( 'dataTables.bootstrap5-js', WPIE_JS_URL . '/dataTables.bootstrap5.min.js', array( 'jquery', 'bootstrap-js' ), WPIE_PLUGIN_VERSION, true );
				wp_enqueue_script( 'plupload' );
				wp_enqueue_script( 'plupload-all' );
			} elseif ( in_array( $page, array( 'wpie-extensions', 'wpie-settings', 'wpie-manage-export', 'wpie-manage-import' ), true ) ) {
				wp_enqueue_script( 'wpie-general-admin-js' );

				$wpie_localize_script_data = array(
					'wpieAjaxURL'      => admin_url( 'admin-ajax.php' ),
					'wpieSiteURL'      => site_url(),
					'wpieUploadURL'    => WPIE_UPLOAD_URL,
					'wpieUploadDir'    => WPIE_UPLOAD_DIR,
					'wpiePluginURL'    => WPIE_PLUGIN_URL,
					'wpieImageURL'     => WPIE_IMAGES_URL,
					'wpieLocalizeText' => self::wpie_load_msg(),
					'wpieSecurity'     => $nonce,
				);

				wp_localize_script( 'wpie-general-admin-js', 'wpiePluginSettings', $wpie_localize_script_data );
			}
		}
	}

	/**
	 * Creates and verifies plugin tables upon installation.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function wpie_db_check() {
		$wpie_plugin_version = get_option( 'wpie_plugin_version', '' );

		if ( '' === $wpie_plugin_version ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();

			update_option( 'wpie_plugin_version', WPIE_PLUGIN_VERSION );
			update_option( 'wpie_db_version', WPIE_DB_VERSION );

			if ( ! get_option( 'wpie_install_date' ) ) {
				update_option( 'wpie_install_date', time() );
			}

			$wpie_template       = $wpdb->prefix . 'wpie_template';
			$wpie_template_table = "CREATE TABLE {$wpie_template} (
				id int(11) NOT NULL AUTO_INCREMENT,
				status varchar(25) DEFAULT NULL,
				opration varchar(100) NOT NULL DEFAULT '',
				username varchar(60) NOT NULL DEFAULT '',
				unique_id varchar(100) NOT NULL DEFAULT '',
				opration_type varchar(100) NOT NULL DEFAULT '',
				options longtext DEFAULT NULL,
				process_log varchar(255) DEFAULT NULL,
				process_lock int(3) DEFAULT 0,
				create_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				last_update_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id)
			) {$charset_collate};";

			dbDelta( $wpie_template_table );
		}
	}

	/**
	 * Loads plugin internationalization textdomain.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function wpie_load_textdomain() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Backwards compatibility for translation loading.
		load_plugin_textdomain( 'wp-import-export-lite', false, 'wp-import-export-lite/languages/' );
	}

	/**
	 * Suppresses 3rd-party admin notices on main WPIE screens to avoid visual interference.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function wpie_hide_all_notice_to_admin_side() {
		$page = Param::getSanitized( 'page', 'key', '' );
		if ( in_array( $page, array( 'wpie-new-export', 'wpie-new-import' ), true ) ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
			remove_all_actions( 'network_admin_notices' );
			remove_all_actions( 'user_admin_notices' );
		}
	}

	/**
	 * Registers administrative menus and submenus.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function wpie_set_menu() {
		global $current_user;

		if ( ( current_user_can( 'administrator' ) || is_super_admin() ) && $current_user instanceof \WP_User ) {
			$wpie_caps = self::wpie_user_capabilities();

			if ( ! empty( $wpie_caps ) && is_array( $wpie_caps ) ) {
				foreach ( $wpie_caps as $wpie_cap => $cap_desc ) {
					if ( ! $current_user->has_cap( $wpie_cap ) ) {
						$current_user->add_cap( $wpie_cap );
					}
				}
			}
		}

		$menu_place = (string) self::get_dynamic_position( 28.81, 0.1 );

		add_menu_page( __( 'WP Import Export Dashboard', 'wp-import-export-lite' ), __( 'WP Imp Exp', 'wp-import-export-lite' ), 'wpie_new_export', 'wpie-new-export', array( __CLASS__, 'wpie_get_page' ), null, $menu_place );
		add_submenu_page( 'wpie-new-export', __( 'New Export', 'wp-import-export-lite' ), __( 'New Export', 'wp-import-export-lite' ), 'wpie_new_export', 'wpie-new-export', array( __CLASS__, 'wpie_get_page' ) );
		add_submenu_page( 'wpie-new-export', __( 'Manage Export', 'wp-import-export-lite' ), __( 'Manage Export', 'wp-import-export-lite' ), 'wpie_manage_export', 'wpie-manage-export', array( __CLASS__, 'wpie_get_page' ) );
		add_submenu_page( 'wpie-new-export', __( 'New Import', 'wp-import-export-lite' ), __( 'New Import', 'wp-import-export-lite' ), 'wpie_new_import', 'wpie-new-import', array( __CLASS__, 'wpie_get_page' ) );
		add_submenu_page( 'wpie-new-export', __( 'Manage Import', 'wp-import-export-lite' ), __( 'Manage Import', 'wp-import-export-lite' ), 'wpie_manage_import', 'wpie-manage-import', array( __CLASS__, 'wpie_get_page' ) );
		add_submenu_page( 'wpie-new-export', __( 'Settings', 'wp-import-export-lite' ), __( 'Settings', 'wp-import-export-lite' ), 'wpie_settings', 'wpie-settings', array( __CLASS__, 'wpie_get_page' ) );
		add_submenu_page( 'wpie-new-export', __( 'Extensions', 'wp-import-export-lite' ), __( 'Extensions', 'wp-import-export-lite' ), 'wpie_extensions', 'wpie-extensions', array( __CLASS__, 'wpie_get_page' ) );
	}

	/**
	 * Renders the requested plugin admin page template.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function wpie_get_page() {
		$page = Param::getSanitized( 'page', 'key', '' );

		if ( ! empty( $page ) && in_array( $page, self::$wpie_page, true ) ) {
			if ( 'wpie-new-export' === $page && file_exists( WPIE_VIEW_DIR . '/wpie-new-export.php' ) ) {
				require_once WPIE_VIEW_DIR . '/wpie-new-export.php';
			} elseif ( 'wpie-new-import' === $page && file_exists( WPIE_VIEW_DIR . '/wpie-new-import.php' ) ) {
				require_once WPIE_VIEW_DIR . '/wpie-new-import.php';
			} elseif ( 'wpie-manage-export' === $page && file_exists( WPIE_VIEW_DIR . '/wpie-manage-export.php' ) ) {
				require_once WPIE_VIEW_DIR . '/wpie-manage-export.php';
			} elseif ( 'wpie-manage-import' === $page && file_exists( WPIE_VIEW_DIR . '/wpie-manage-import.php' ) ) {
				require_once WPIE_VIEW_DIR . '/wpie-manage-import.php';
			} elseif ( 'wpie-settings' === $page && file_exists( WPIE_VIEW_DIR . '/wpie-settings.php' ) ) {
				require_once WPIE_VIEW_DIR . '/wpie-settings.php';
			} elseif ( 'wpie-extensions' === $page ) {
				$require_page  = WPIE_VIEW_DIR . '/wpie-extensions.php';
				$include_page  = WPIE_VIEW_DIR . '/wpie-extension-info.php';
				$wpie_ext_name = Param::getSanitized( 'wpie_ext', 'key', '' );

				if ( ! empty( $wpie_ext_name ) ) {
					if ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-extensions.php' ) ) {
						require_once WPIE_CLASSES_DIR . '/class-wpie-extensions.php';
					}

					$is_valid_ext = false;
					if ( class_exists( '\wpie\addons\WPIE_Extension' ) ) {
						$wpie_ext     = new \wpie\addons\WPIE_Extension();
						$is_valid_ext = $wpie_ext->wpie_import_extension_info( $wpie_ext_name );
						unset( $wpie_ext );
					}

					if ( $is_valid_ext && file_exists( $include_page ) ) {
						require_once $include_page;
					} elseif ( file_exists( $require_page ) ) {
						require_once $require_page;
					}
				} elseif ( file_exists( $require_page ) ) {
					require_once $require_page;
				}
			}
		}
	}

	/**
	 * Returns available plugin capability definitions.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @return array Associative array of capability slugs and human-readable descriptions.
	 */
	private static function wpie_user_capabilities() {
		return array(
			'wpie_new_export'    => __( 'User can export new data', 'wp-import-export-lite' ),
			'wpie_manage_export' => __( 'User can manage export data', 'wp-import-export-lite' ),
			'wpie_new_import'    => __( 'User can import new data', 'wp-import-export-lite' ),
			'wpie_manage_import' => __( 'User can manage import data', 'wp-import-export-lite' ),
			'wpie_settings'      => __( 'User can manage Settings of import and export', 'wp-import-export-lite' ),
			'wpie_extensions'    => __( 'User can manage Extensions of import and export', 'wp-import-export-lite' ),
			'wpie_add_shortcode' => __( 'User Add Shortcode in import field', 'wp-import-export-lite' ),
		);
	}

	/**
	 * Calculates non-conflicting menu position.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param  float|int $start     Starting menu position.
	 * @param  float     $increment Step to increment on conflict. Default 0.1.
	 *
	 * @return float Calculated available position.
	 */
	private static function get_dynamic_position( $start, $increment = 0.1 ) {
		$menus_positions = array();
		if ( isset( $GLOBALS['menu'] ) && is_array( $GLOBALS['menu'] ) ) {
			$menus_positions = array_keys( $GLOBALS['menu'] );
		}

		if ( ! in_array( $start, $menus_positions ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			return $start;
		}

		while ( in_array( $start, $menus_positions ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			$start += $increment;
		}

		return $start;
	}

	/**
	 * Replaces admin footer text on plugin screens.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function wpie_replace_footer_admin() {
		echo '';
	}

	/**
	 * Replaces admin footer version text.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string Empty string.
	 */
	public static function wpie_replace_footer_version() {
		return '';
	}

	/**
	 * Checks if root .htaccess file exists and is writable.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @return bool True if writable, false otherwise.
	 */
	private static function is_htaccess_writable() {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$htaccess_file = get_home_path() . '.htaccess';

		if ( ! file_exists( $htaccess_file ) ) {
			return false;
		}

		if ( wp_is_writable( $htaccess_file ) ) {
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Adjust htaccess permissions if possible.
		@chmod( $htaccess_file, 0666 );

		return (bool) wp_is_writable( $htaccess_file );
	}

	/**
	 * Adds Apache rewrite rules to protect the plugin upload directory.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  string $rules Existing rewrite rules.
	 *
	 * @return string Modified rewrite rules.
	 */
	public static function mod_rewrite_rules( $rules = '' ) {
		if ( ! is_string( $rules ) ) {
			$rules = '';
		}

		$new_rule  = 'RewriteCond %{REQUEST_FILENAME} -s' . PHP_EOL;
		$new_rule .= 'RewriteCond %{HTTP_USER_AGENT} !facebookexternalhit/[0-9]' . PHP_EOL;
		$new_rule .= 'RewriteCond %{HTTP_USER_AGENT} !Twitterbot/[0-9]' . PHP_EOL;
		$new_rule .= 'RewriteCond %{HTTP_USER_AGENT} !Googlebot/[0-9]' . PHP_EOL;

		$upload_dir_url = str_replace( 'https', 'http', WPIE_UPLOAD_URL );
		$site_url       = str_replace( 'https', 'http', site_url() );

		$new_rule .= 'RewriteRule ' . str_replace( trailingslashit( $site_url ), '', $upload_dir_url ) . "(\/[A-Za-z0-9_@.\/&+-]+)+\.([A-Za-z0-9_@.\/&+-]+)$ [L]" . PHP_EOL;

		update_option( 'wpie_is_admin_notice_clear', 1 );
		update_option( 'wpie_flush_rewrite_rules', 1 );

		return $new_rule . $rules . PHP_EOL;
	}

	/**
	 * Handles notice dismissal actions via admin URL.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function hide_notices() {
		$hide_notice  = Param::getSanitized( 'wpie-hide-notice', 'key', '' );
		$notice_nonce = Param::getSanitized( '_wpie_notice_nonce', 'key', '' );

		if ( ! empty( $hide_notice ) && ! empty( $notice_nonce ) ) {
			if ( ! wp_verify_nonce( $notice_nonce, 'wpie_hide_notices_nonce' ) ) {
				wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'wp-import-export-lite' ) );
			}

			update_user_meta( get_current_user_id(), 'dismissed_' . $hide_notice . '_notice', 1 );
		}
	}

	/**
	 * Displays admin notice regarding upload folder protection when .htaccess is non-writable.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function wpie_admin_notices() {
		if ( self::is_apache() && false === get_option( 'wpie_is_admin_notice_clear', false ) ) {
			$notice = get_user_meta( get_current_user_id(), 'dismissed_wpie_file_security_notice', true );

			if ( 1 !== intval( $notice ) ) {
				?>
				<div class="wpie-message updated">
					<a class="wpie-message-close notice-dismiss" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wpie-hide-notice', 'wpie_file_security' ), 'wpie_hide_notices_nonce', '_wpie_notice_nonce' ) ); ?>"><?php esc_html_e( 'Dismiss', 'wp-import-export-lite' ); ?></a>
					<p><b><?php esc_html_e( 'WP Import Export: ', 'wp-import-export-lite' ); ?></b><?php esc_html_e( 'If your .htaccess file were writable, we could do this automatically, but it isn’t. So you must either make it writable or manually update your .htaccess with the mod_rewrite rules found under WP Settings >> Permalinks. Until then, the exported and imported files are not protected from direct access.', 'wp-import-export-lite' ); ?></p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Checks if server is running Apache.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return bool True if Apache or software unknown, false otherwise.
	 */
	public static function is_apache() {
		$server_software = Param::server( 'SERVER_SOFTWARE', '' );

		if ( empty( $server_software ) ) {
			return true;
		}

		return stripos( $server_software, 'Apache' ) !== false;
	}

	/**
	 * Updates robots.txt file to disallow crawling of plugin upload directories.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public static function update_file_security() {
		$is_updated  = get_option( 'wpie_is_updated_file_security', false );
		$robots_file = get_home_path() . 'robots.txt';

		if ( false === $is_updated || ( ! file_exists( $robots_file ) ) ) {
			if ( wp_is_writable( get_home_path() ) ) {
				// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct file operations required for robots.txt update.
				$fp = @fopen( $robots_file, 'a+b' );
				if ( false !== $fp ) {
					$filesize   = @filesize( $robots_file );
					$robotstext = ( is_numeric( $filesize ) && $filesize > 0 ) ? fread( $fp, $filesize ) : '';

					if ( '' !== trim( $robotstext ) && strpos( $robotstext, '#WP Import Export Rule' ) === false ) {
						$robots_content = self::update_robots_txt();
						fwrite( $fp, $robots_content );
					}
					fclose( $fp );

					update_option( 'wpie_is_updated_file_security', 1 );
				}
				// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
		}
	}

	/**
	 * Appends plugin protection rule to robots.txt contents.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  string $robotstext Existing robots.txt contents. Default empty string.
	 * @param  bool   $public     Whether site is public. Default false.
	 *
	 * @return string Modified robots.txt text.
	 */
	public static function update_robots_txt( $robotstext = '', $public = false ) {
		if ( ! is_string( $robotstext ) ) {
			$robotstext = '';
		}

		if ( strpos( $robotstext, '#WP Import Export Rule' ) === false ) {
			$robotstext .= PHP_EOL . PHP_EOL . '#WP Import Export Rule';
			$robotstext .= PHP_EOL . 'User-agent: *';
			$robotstext .= PHP_EOL . 'Disallow: /wp-content/uploads/wp-import-export-lite/';
		}

		return $robotstext;
	}

	/**
	 * Filters WooCommerce order numbers, supporting both HPOS and post meta.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  int|string   $order_id Order ID or number.
	 * @param  mixed $order    WC_Order object or order data array. Default array().
	 *
	 * @return int|string Custom order number or original ID.
	 */
	public static function wpie_woocommerce_order_number( $order_id = 0, $order = array() ) {
		$order_number = $order_id;
		$order_id_num = absint( $order_id );

		if ( $order_id_num > 0 ) {
			if ( is_object( $order ) && method_exists( $order, 'get_meta' ) ) {
				$meta = $order->get_meta( '_wpie_order_number' );
				if ( ! empty( $meta ) ) {
					return $meta;
				}
			}

			$new_order_number = get_post_meta( $order_id_num, '_wpie_order_number', true );
			if ( ! empty( $new_order_number ) ) {
				$order_number = $new_order_number;
			}
		}

		return $order_number;
	}

	/**
	 * Returns localized translation strings for front-end JavaScript scripts.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @return array Translation dictionary.
	 */
	private static function wpie_load_msg() {
		return array(
			'yesText'                         => __( 'Yes', 'wp-import-export-lite' ),
			'okText'                          => __( 'Ok', 'wp-import-export-lite' ),
			'errorText'                       => __( 'Error', 'wp-import-export-lite' ),
			'confirmText'                     => __( 'Confirm', 'wp-import-export-lite' ),
			'selectTemplateText'              => __( 'Select Template', 'wp-import-export-lite' ),
			'selectSettingText'               => __( 'Select Setting', 'wp-import-export-lite' ),
			'selectSettingloadText'           => __( 'Setting Loaded Successfully', 'wp-import-export-lite' ),
			'wpie_ajax_not_connect_error'     => __( 'Not connected. Verify Network.', 'wp-import-export-lite' ),
			'wpie_ajax_404_error'             => __( 'Requested page not found. [404]', 'wp-import-export-lite' ),
			'wpie_ajax_internal_server_error' => __( 'Internal Server Error [500].', 'wp-import-export-lite' ),
			'wpie_ajax_jason_parse_error'     => __( 'Requested JSON parse failed.', 'wp-import-export-lite' ),
			'wpie_ajax_time_out_error'        => __( 'Time out error.', 'wp-import-export-lite' ),
			'wpie_ajax_request_aborted_error' => __( 'Ajax request aborted.', 'wp-import-export-lite' ),
			'wpie_ajax_400_error'             => __( 'Bad Request', 'wp-import-export-lite' ),
			'wpie_ajax_uncaught_error'        => __( 'Uncaught Error', 'wp-import-export-lite' ),
			'selectExportRuleText'            => __( 'Select Rule', 'wp-import-export-lite' ),
			'selectElementText'               => __( 'Select Element', 'wp-import-export-lite' ),
			'selectExportTypeText'            => __( 'Please choose export type', 'wp-import-export-lite' ),
			'selectExportTaxTypeText'         => __( 'Please choose export taxonomy type', 'wp-import-export-lite' ),
			'enterTemplateNameText'           => __( 'Please enter template Name', 'wp-import-export-lite' ),
			'enterSettingNameText'            => __( 'Please enter Setting Name', 'wp-import-export-lite' ),
			'enterCsvDelimiterText'           => __( 'Please enter CSV delimiter', 'wp-import-export-lite' ),
			'andText'                         => __( 'AND', 'wp-import-export-lite' ),
			'orText'                          => __( 'OR', 'wp-import-export-lite' ),
			'saveText'                        => __( 'Save', 'wp-import-export-lite' ),
			'closeText'                       => __( 'Close', 'wp-import-export-lite' ),
			'wpieNoFieldsFoundText'           => __( 'No fields found please choose other option', 'wp-import-export-lite' ),
			'wpieExportFieldEditorText'       => __( 'Export Field Editor', 'wp-import-export-lite' ),
			'wpieExportEmptyFieldText'        => __( 'Please Enter Field Name', 'wp-import-export-lite' ),
			'wpieExportEmptyDataText'         => __( "There aren't any Records to export.", 'wp-import-export-lite' ),
			'wpieExportCompletedText'         => __( 'Export Completed', 'wp-import-export-lite' ),
			'wpieExportUserExtDisableText'    => __( 'Please Activate User Export Extension', 'wp-import-export-lite' ),
			'wpieExportWCExtDisableText'      => __( 'Please Activate WooCommerce Export Extension', 'wp-import-export-lite' ),
			'wpieExportattrExtDisableText'    => __( 'Please Activate Product Attributes Export Extension', 'wp-import-export-lite' ),
			'wpieExportEmptyColumnText'       => __( "You haven't selected any columns for export.", 'wp-import-export-lite' ),
			'wpieChooseFileText'              => __( 'Choose File', 'wp-import-export-lite' ),
			'wpieChooseSheetText'             => __( 'Choose Excel Sheet', 'wp-import-export-lite' ),
			'fileUploadSuccessText'           => __( 'File Uploaded Successfully', 'wp-import-export-lite' ),
			'invalidFileExtensionText'        => __( 'Uploaded file must be CSV, ZIP, XLS, XLSX, XML, TXT, JSON', 'wp-import-export-lite' ),
			'wpieUploadingText'               => __( 'Uploading', 'wp-import-export-lite' ),
			'wpieUploadCompleteText'          => __( 'Upload Complete', 'wp-import-export-lite' ),
			'wpieParingUploadFileText'        => __( 'Parsing upload file', 'wp-import-export-lite' ),
			'wpieGetTemplatesText'            => __( 'Get Template List', 'wp-import-export-lite' ),
			'wpieGetSettingList'              => __( 'Get Setting List', 'wp-import-export-lite' ),
			'wpieGetSettingsText'             => __( 'Get Settings List', 'wp-import-export-lite' ),
			'wpieGetConfigText'               => __( 'Get Configuration', 'wp-import-export-lite' ),
			'wpieGetFieldsText'               => __( 'Get Import Fields', 'wp-import-export-lite' ),
			'wpieGetRecordsText'              => __( 'Get Preview Records', 'wp-import-export-lite' ),
			'wpieChangeTemplatesText'         => __( 'Set Template', 'wp-import-export-lite' ),
			'wpieUpdateSettingsText'          => __( 'Settings Updating', 'wp-import-export-lite' ),
			'wpieSaveTemplatesText'           => __( 'Save Template', 'wp-import-export-lite' ),
			'wpieSaveSettingsText'            => __( 'Save Settings', 'wp-import-export-lite' ),
			'wpieNoRecordsFoundText'          => __( 'No Records Found. Please Try another filters', 'wp-import-export-lite' ),
			'wpieNoRecordsText'               => __( 'No Records Found. Please Try another File or Data Format or Excel Sheet', 'wp-import-export-lite' ),
			'wpieImportProcessingText'        => __( 'Import Processing', 'wp-import-export-lite' ),
			'wpieImportCompleteText'          => __( 'Import Complete!', 'wp-import-export-lite' ),
			'wpieImportPausedText'            => __( 'Import Paused', 'wp-import-export-lite' ),
			'wpieImportStoppedText'           => __( 'Import Stopped', 'wp-import-export-lite' ),
			'wpieImportProcessingNoticeText'  => __( 'Importing may take some time. Please do not close your browser or refresh the page until the process is complete.', 'wp-import-export-lite' ),
			'wpieImportPartiallyText'         => __( 'WordPress Import Export partially imported your file into your WordPress installation!', 'wp-import-export-lite' ),
			'wpieImportCompleteNoticeText'    => __( 'WordPress Import Export successfully imported your file into your WordPress installation!', 'wp-import-export-lite' ),
			'wpieChooseValidFileText'         => __( 'Please Choose Valid File', 'wp-import-export-lite' ),
			'wpieSetExistingFileText'         => __( 'Set Existing File', 'wp-import-export-lite' ),
			'wpieUploadFromURLText'           => __( 'File is downloading from URL', 'wp-import-export-lite' ),
			'wpieUploadFromFTPText'           => __( 'File is downloading from FTP/SFTP', 'wp-import-export-lite' ),
			'wpieEmptyUsesrRole'              => __( 'Please choose user role', 'wp-import-export-lite' ),
			'wpieEmptyTemplates'              => __( 'Please Select Templates', 'wp-import-export-lite' ),
			'wpieEmptyActions'                => __( 'Please select any action', 'wp-import-export-lite' ),
			'wpieSetBGProcessText'            => __( 'Set Background Process', 'wp-import-export-lite' ),
			'wpieBgProcessingText'            => __( 'Background Process Set Successfully', 'wp-import-export-lite' ),
			'wpieImportBGText'                => __( 'Import in Background', 'wp-import-export-lite' ),
			'wpieImportBGNoticeText'          => __( 'plugin will automatically import data in Background. you can close your browser.', 'wp-import-export-lite' ),
			'wpieInvalidURLText'              => __( 'Please Enter Valid URL', 'wp-import-export-lite' ),
			'wpieInvalidHostNameText'         => __( 'Please Enter Valid Host Name', 'wp-import-export-lite' ),
			'wpieInvalidHostUsernameText'     => __( 'Please Enter Valid Host Username', 'wp-import-export-lite' ),
			'wpieInvalidHostPasswordText'     => __( 'Please Enter Valid Host Password', 'wp-import-export-lite' ),
			'wpieDownloadFileText'            => __( 'Downloading File', 'wp-import-export-lite' ),
			'wpieInvalidHostPathText'         => __( 'Please Enter Valid Host Path', 'wp-import-export-lite' ),
			'wpieImportUserExtDisableText'    => __( 'Please Activate User Import Extension', 'wp-import-export-lite' ),
			'wpieImportWCExtDisableText'      => __( 'Please Activate WooCommerce Import Extension', 'wp-import-export-lite' ),
			'wpieImportPAExtDisableText'      => __( 'Please Activate Product Attributes Import Extension', 'wp-import-export-lite' ),
			'wpiePrepareFile'                 => __( 'Prepare File', 'wp-import-export-lite' ),
			'wpiePaused'                      => __( 'Paused', 'wp-import-export-lite' ),
			'wpieProcessing'                  => __( 'Processing', 'wp-import-export-lite' ),
			'wpieStopped'                     => __( 'Stopped', 'wp-import-export-lite' ),
			'wpieEmptyLicenseKey'             => __( 'Purchase Code is empty', 'wp-import-export-lite' ),
			'wpieInvalidLicenseKey'           => __( 'Purchase Code is Invalid', 'wp-import-export-lite' ),
			'wpieSetScheduleExportText'       => __( 'Set Schedule', 'wp-import-export-lite' ),
			'wpieFillRequiredFieldText'       => __( 'Please Fill Required Fields', 'wp-import-export-lite' ),
			'wpieEmptyTemplate'               => __( 'Please Select any template', 'wp-import-export-lite' ),
			'wpieEmptyLayout'                 => __( 'Please Select any Layout', 'wp-import-export-lite' ),
			'processingReimport'              => __( 'Processing Reimport', 'wp-import-export-lite' ),
			'ActivateWc'                      => __( 'Please Activate WooCommerce Plugin', 'wp-import-export-lite' ),
			'showMoreText'                    => __( '[Show more]', 'wp-import-export-lite' ),
			'showLessText'                    => __( '[Show less]', 'wp-import-export-lite' ),
			'wpieEmptyTitleField'             => __( 'Title is required field for new data', 'wp-import-export-lite' ),
			'chooseDataUpdate'                => __( 'Choose which data to update', 'wp-import-export-lite' ),
			'uploadDirWritableError'          => __( 'Upload Directory YOUR_SITE/wp-content/uploads/wp-import-export-lite is not Writable. Please change permission to make writable', 'wp-import-export-lite' ),
			'wpieInvalidLicense'              => __( 'Please Activate Plugin Purchase Code from WP Imp Exp => Settings', 'wp-import-export-lite' ),
			'wpieProFeatureOnly'              => __( 'Pro Feature Only', 'wp-import-export-lite' ),
		);
	}
}

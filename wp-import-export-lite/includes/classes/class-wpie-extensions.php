<?php
/**
 * WPIE Extensions Manager.
 *
 * Manages active and available extensions for import and export operations.
 *
 * @since      1.0.0
 * @package    wpie
 * @subpackage wpie\Addons
 * @author     VJinfotech <support@vjinfotech.com>
 */

namespace wpie\addons;

use wpie\Security;
use WpieApp\Core\Helpers\Param;
use WpieApp\Core\Helpers\Sanitizer;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_CLASSES_DIR . '/class-wpie-security.php' ) ) {
	require_once WPIE_CLASSES_DIR . '/class-wpie-security.php';
}

/**
 * WPIE_Extension Class
 *
 * Handles extension registration, saving, initialization, and remote storage bindings.
 *
 * @since 1.0.0
 */
class WPIE_Extension {

	/**
	 * Export extensions definition cache.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @var    array
	 */
	private $wpie_export_extensions = array();

	/**
	 * Import extensions definition cache.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @var    array
	 */
	private $wpie_import_extensions = array();

	/**
	 * Activated extensions keys cache.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @var    array
	 */
	private $wpie_activated_extensions = array();

	/**
	 * Class Constructor.
	 *
	 * Registers AJAX actions and extension filters.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_wpie_ext_save_extensions', array( $this, 'wpie_ext_save_extensions' ) );
		add_action( 'wp_ajax_wpie_ext_save_extension_data', array( $this, 'wpie_ext_save_extension_data' ) );
		add_filter( 'wpie_get_export_remote_locations', array( $this, 'wpie_get_export_remote_locations' ), 10, 1 );
	}

	/**
	 * Retrieve all available export extensions.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array Array of export extension definitions.
	 */
	public function wpie_get_export_extension() {
		if ( empty( $this->wpie_export_extensions ) ) {
			$this->wpie_export_extensions = array(
				'wpie_acf_export'               => array(
					'name'       => __( 'Advanced Custom Fields', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Export Advanced Custom Fields from WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_bg_export'                => array(
					'name'         => __( 'Background Export', 'wp-import-export-lite' ),
					'include_path' => WPIE_EXPORT_CLASSES_DIR . '/extensions/bg/wpie_bg.php',
					'short_desc'   => __( 'Export in Background from WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_schedule_export'          => array(
					'name'       => __( 'Schedule Export', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Export automatically and periodically from WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_user_export'              => array(
					'name'         => __( 'User', 'wp-import-export-lite' ),
					'include_path' => WPIE_EXPORT_CLASSES_DIR . '/extensions/user/wpie_user.php',
					'short_desc'   => __( 'Export Users & User\'s metadata from WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_wc_export'                => array(
					'name'       => __( 'WooCommerce', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Export Products, Orders, Product Categories and coupons from WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_wpml_export'              => array(
					'name'       => __( 'WPML', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Export multilingual content from WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_polylang_export'          => array(
					'name'       => __( 'Polylang Export', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Export multilingual content from WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_product_attribute_export' => array(
					'name'       => __( 'Product Attributes', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Export Product Attributes from WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_yoast_seo_export'         => array(
					'name'         => __( 'Yoast SEO', 'wp-import-export-lite' ),
					'include_path' => WPIE_EXPORT_CLASSES_DIR . '/extensions/yoast-seo/wpie_yoast_seo.php',
					'short_desc'   => __( 'Export Yoast SEO Settings from WordPress Site', 'wp-import-export-lite' ),
				),
			);
		}

		return apply_filters( 'wpie_export_extensions', $this->wpie_export_extensions );
	}

	/**
	 * Retrieve all available import extensions.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array Array of import extension definitions.
	 */
	public function wpie_get_import_extension() {
		if ( empty( $this->wpie_import_extensions ) ) {
			$this->wpie_import_extensions = array(
				'wpie_import_local_upload'            => array(
					'name'         => __( 'Upload From Desktop', 'wp-import-export-lite' ),
					'is_default'   => true,
					'include_path' => WPIE_IMPORT_CLASSES_DIR . '/extensions/local-upload/wpie_local_upload.php',
				),
				'wpie_import_existing_file_upload'    => array(
					'name'         => __( 'Use existing file', 'wp-import-export-lite' ),
					'is_default'   => true,
					'include_path' => WPIE_IMPORT_CLASSES_DIR . '/extensions/existing-file/wpie_existing_file.php',
				),
				'wpie_acf_import'                     => array(
					'name'       => __( 'Advanced Custom Fields', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Import Advanced Custom Fields to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_bg_import'                      => array(
					'name'         => __( 'Background Import', 'wp-import-export-lite' ),
					'include_path' => WPIE_IMPORT_CLASSES_DIR . '/extensions/bg/wpie_bg.php',
					'short_desc'   => __( 'Import in Background to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_import_dropbox_file_upload'     => array(
					'name'       => __( 'Dropbox', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Import File from Dropbox to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_import_ftp_file_upload'         => array(
					'name'       => __( 'Upload From FTP/SFTP', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Import File from FTP/SFTP to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_import_googledrive_file_upload' => array(
					'name'       => __( 'Google Drive', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Import File from Google Drive to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_import_onedrive_file_upload'    => array(
					'name'       => __( 'Microsoft Onedrive', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Import File from Microsoft Onedrive to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_schedule_import'                => array(
					'name'       => __( 'Schedule Import', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Import automatically & periodically to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_import_url_file_upload'         => array(
					'name'         => __( 'Upload From URL', 'wp-import-export-lite' ),
					'include_path' => WPIE_IMPORT_CLASSES_DIR . '/extensions/url-upload/wpie_url_upload.php',
					'short_desc'   => __( 'Import File from URL to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_user_import'                    => array(
					'name'         => __( 'User Import', 'wp-import-export-lite' ),
					'include_path' => WPIE_IMPORT_CLASSES_DIR . '/extensions/user/user.php',
					'short_desc'   => __( 'Import Users & User\'s metadata to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_wc_import'                      => array(
					'name'       => __( 'WooCommerce Import', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Import Products, Orders, Product Categories and coupons to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_wpml_import'                    => array(
					'name'       => __( 'WPML', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Import multilingual content to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_polylang_import'                => array(
					'name'       => __( 'Polylang Import', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Import multilingual content to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_product_attribute_import'       => array(
					'name'       => __( 'Product Attributes', 'wp-import-export-lite' ),
					'is_pro'     => true,
					'short_desc' => __( 'Import Product Attributes to WordPress Site', 'wp-import-export-lite' ),
				),
				'wpie_yoast_seo_import'               => array(
					'name'         => __( 'Yoast SEO', 'wp-import-export-lite' ),
					'include_path' => WPIE_IMPORT_CLASSES_DIR . '/extensions/yoast-seo/wpie_yoast_seo.php',
					'short_desc'   => __( 'Import Yoast SEO Settings to WordPress Site', 'wp-import-export-lite' ),
				),
			);
		}

		return apply_filters( 'wpie_import_extensions', $this->wpie_import_extensions );
	}

	/**
	 * AJAX Handler: Saves the list of activated extensions.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wpie_ext_save_extensions() {
		Security::verify_request( 'wpie_extensions' );

		$wpie_ext = Param::post( 'wpie_ext', '' );
		$wpie_ext = ! empty( $wpie_ext ) ? Sanitizer::clean( $wpie_ext, 'text' ) : '';

		if ( ! empty( $wpie_ext ) ) {
			$wpie_ext = maybe_serialize( $wpie_ext );
		} else {
			$wpie_ext = '';
		}

		update_option( 'wpie_extensions', $wpie_ext );

		$return_value = array(
			'status'  => 'success',
			'message' => __( 'Settings Successfully Updated', 'wp-import-export-lite' ),
		);

		wp_send_json( $return_value );
	}

	/**
	 * AJAX Handler: Saves configuration data for an individual extension.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wpie_ext_save_extension_data() {
		Security::verify_request( 'wpie_extensions' );

		$wpie_ext = Param::postSanitized( 'wpie_ext', 'key', '' );

		$export_ext = $this->wpie_get_export_extension();
		$import_ext = $this->wpie_get_import_extension();

		if ( ( ! empty( $wpie_ext ) ) && strpos( $wpie_ext, 'wpie' ) === 0 && ( isset( $export_ext[ $wpie_ext ] ) || isset( $import_ext[ $wpie_ext ] ) ) ) {
			$post_data = Param::post();
			if ( ! is_array( $post_data ) ) {
				$post_data = array();
			}

			// Clean internal routing and security keys.
			unset( $post_data['action'], $post_data['wpieSecurity'], $post_data['wpie_ext'] );

			$wpie_ext_data = maybe_serialize( map_deep( $post_data, 'wp_kses_post' ) );

			update_option( $wpie_ext, $wpie_ext_data );

			$return_value = array(
				'status'  => 'success',
				'message' => __( 'Settings Successfully Updated', 'wp-import-export-lite' ),
			);
		} else {
			$return_value = array(
				'status'  => 'error',
				'message' => __( 'Extension Not Found', 'wp-import-export-lite' ),
			);
		}

		wp_send_json( $return_value );
	}

	/**
	 * Retrieves the array of currently active extension keys.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array List of activated extension slugs.
	 */
	public function wpie_get_activated_ext() {
		if ( empty( $this->wpie_activated_extensions ) ) {
			$wpie_extensions = get_option( 'wpie_extensions' );

			$wpie_export = $this->wpie_get_export_extension();
			$wpie_import = $this->wpie_get_import_extension();
			$all_ext     = array_merge( (array) $wpie_export, (array) $wpie_import );

			if ( $wpie_extensions ) {
				$default_ext = array();
				if ( ! empty( $all_ext ) ) {
					foreach ( $all_ext as $key => $ext ) {
						if ( isset( $ext['is_pro'] ) && true === (bool) $ext['is_pro'] ) {
							continue;
						}
						if ( isset( $ext['is_default'] ) && true === (bool) $ext['is_default'] ) {
							$default_ext[] = $key;
						}
					}
				}

				$unserialized = maybe_unserialize( $wpie_extensions );
				if ( ! is_array( $unserialized ) ) {
					$unserialized = array();
				}

				$this->wpie_activated_extensions = array_values( array_unique( array_merge( $default_ext, $unserialized ) ) );
			} else {
				$ext_data = array();
				if ( ! empty( $all_ext ) ) {
					foreach ( $all_ext as $key => $ext ) {
						if ( isset( $ext['is_pro'] ) && true === (bool) $ext['is_pro'] ) {
							continue;
						}
						$ext_data[] = $key;
					}
				}
				$this->wpie_activated_extensions = $ext_data;
			}
		}

		return apply_filters( 'wpie_activated_extensions', $this->wpie_activated_extensions );
	}

	/**
	 * Initializes and loads active extensions.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  string $type Context to initialize: 'all', 'export', or 'import'. Default 'all'.
	 *
	 * @return void
	 */
	public function wpie_init_extensions( $type = 'all' ) {
		$wpie_activated_ext = $this->wpie_get_activated_ext();
		$data               = is_array( $wpie_activated_ext ) ? $wpie_activated_ext : array();

		try {
			$wpie_ext = array();

			if ( 'all' === $type || 'export' === $type ) {
				$export_ext = $this->wpie_get_export_extension();
				$wpie_ext   = is_array( $export_ext ) ? $export_ext : array();
			}

			if ( 'all' === $type || 'import' === $type ) {
				$import_ext = $this->wpie_get_import_extension();
				if ( is_array( $import_ext ) ) {
					$wpie_ext = array_merge( $wpie_ext, $import_ext );
				}
			}

			if ( ! empty( $wpie_ext ) ) {
				foreach ( $wpie_ext as $key => $ext ) {
					if ( isset( $ext['include_path'] ) && is_string( $ext['include_path'] ) && file_exists( $ext['include_path'] ) ) {
						$is_default = isset( $ext['is_default'] ) && true === (bool) $ext['is_default'];
						if ( $is_default || in_array( $key, $data, true ) ) {
							require_once $ext['include_path'];
						}
					}
				}
			}
		} catch ( \Exception $e ) {
			// Fail safely.
		} catch ( \Throwable $e ) {
			// Fail safely for PHP 7+ errors.
		}
	}

	/**
	 * Check if a specific export extension is registered.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  string $wpie_ext Extension identifier slug.
	 *
	 * @return bool True if registered, false otherwise.
	 */
	public function wpie_export_extension_info( $wpie_ext = '' ) {
		$wpie_export_ext = $this->wpie_get_export_extension();

		return ! empty( $wpie_ext ) && is_array( $wpie_export_ext ) && isset( $wpie_export_ext[ $wpie_ext ] );
	}

	/**
	 * Check if a specific import extension is registered.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  string $wpie_ext Extension identifier slug.
	 *
	 * @return bool True if registered, false otherwise.
	 */
	public function wpie_import_extension_info( $wpie_ext = '' ) {
		$wpie_import_ext = $this->wpie_get_import_extension();

		return ! empty( $wpie_ext ) && is_array( $wpie_import_ext ) && isset( $wpie_import_ext[ $wpie_ext ] );
	}

	/**
	 * Filter callback to append external storage locations for export.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  mixed $remote_loc Existing remote locations array.
	 *
	 * @return array Filtered remote locations array.
	 */
	public function wpie_get_export_remote_locations( $remote_loc = array() ) {
		if ( ! is_array( $remote_loc ) ) {
			$remote_loc = array();
		}

		$wpie_export_ext    = $this->wpie_get_export_extension();
		$wpie_activated_ext = $this->wpie_get_activated_ext();

		if ( ! empty( $wpie_export_ext ) && is_array( $wpie_activated_ext ) && ! empty( $wpie_activated_ext ) ) {
			foreach ( $wpie_activated_ext as $wpie_ext ) {
				if ( ! ( isset( $wpie_export_ext[ $wpie_ext ]['is_external_save'] ) && true === (bool) $wpie_export_ext[ $wpie_ext ]['is_external_save'] ) ) {
					continue;
				}
				if ( isset( $remote_loc[ $wpie_ext ] ) ) {
					continue;
				}

				$option_name = 'wpie_export_ext_' . $wpie_ext;
				$settings    = maybe_unserialize( get_option( $option_name ) );

				$remote_loc[ $wpie_ext ] = array(
					'label' => isset( $wpie_export_ext[ $wpie_ext ]['name'] ) ? $wpie_export_ext[ $wpie_ext ]['name'] : '',
					'data'  => $settings,
				);
			}
		}

		return $remote_loc;
	}
}

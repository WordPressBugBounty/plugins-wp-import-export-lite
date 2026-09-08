<?php
/**
 * Upload Management Handler
 *
 * @package   wpie\import\upload
 * @author    WP Import Export
 * @copyright 2026 WP Import Export
 * @license   GPL-2.0+
 */

namespace wpie\import\upload;

use wpie\import;
use wpie\import\FileFormat\Manager as FileExtract;

defined( 'ABSPATH' ) || exit;

/**
 * Class WPIE_Upload
 *
 * Handles file uploads, archive extractions, and temporary file management for import processes.
 *
 * @since 1.0.0
 */
class WPIE_Upload {

	/**
	 * Date format for file listing.
	 *
	 * @var string
	 */
	protected $wpie_date_format;

	/**
	 * Time format for file listing.
	 *
	 * @var string
	 */
	protected $wpie_time_format;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Retrieve registered upload section tabs.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function wpie_get_upload_section() {
		return apply_filters( "wpie_import_upload_sections", array() );
	}

	/**
	 * Generate a unique safe directory hash name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $str        Base string.
	 * @param string $separator  Word separator ('dash' or 'underscore').
	 * @param bool   $lowercase  Whether to convert to lowercase.
	 * @return string MD5 hash string.
	 */
	public function wpie_create_safe_dir_name( $str = "", $separator = 'dash', $lowercase = true ) {

		if ( $separator === 'dash' ) {
			$search = '_';
			$replace = '-';
		} else {
			$search = '-';
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
			'\.+$'           => ''
		);

		$str = wp_strip_all_tags( (string) $str );

		foreach ( $trans as $key => $val ) {
			$str = preg_replace( "#" . $key . "#i", $val, $str );
		}

		if ( $lowercase === true ) {
			$str = strtolower( $str );
		}

		unset( $search, $replace, $trans );

		return md5( trim( wp_unslash( $str ) ) . time() . wp_rand() );
	}

	/**
	 * Organize and register uploaded or extracted files into template configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $fileName       Uploaded filename.
	 * @param string $fileDir        Target directory name.
	 * @param int    $wpie_import_id Import job ID.
	 * @return array|\WP_Error Import files array or WP_Error on failure.
	 */
	protected function wpie_manage_import_file( $fileName = "", $fileDir = "", $wpie_import_id = 0 ) {

		if ( empty( $fileName ) || empty( $fileDir ) || strpos( $fileName, '..' ) !== false || strpos( $fileName, "\0" ) !== false ) {
			return new \WP_Error( 'wpie_import_error', __( 'Invalid file path or name', 'wp-import-export-lite' ) );
		}

		$relative_path = $fileDir . "/original";

		$filePath = WPIE_UPLOAD_IMPORT_DIR . "/" . $relative_path;

		$current_file = $filePath . "/" . $fileName;

		$file_data = [];

		$fileList = [];

		$active_file = "";

		if ( ! is_file( $current_file ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'Uploaded file is empty or does not exist', 'wp-import-export-lite' ) );
		} elseif ( ! preg_match( '%\W(xml|zip|csv|xls|xlsx|ods|txt|json|gz|tar)$%i', trim( $fileName ) ) ) {
			return new \WP_Error( 'wpie_import_error', __( 'Uploaded file must be XML, CSV, ZIP, XLS, XLSX, ODS, TXT, JSON, GZ, TAR', 'wp-import-export-lite' ) );
		} elseif ( preg_match( '%\W(zip|tar|gz)$%i', trim( $fileName ) ) ) {

			$extract_path = $relative_path . "/extract";

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
			}
			WP_Filesystem();

			wp_mkdir_p( WPIE_UPLOAD_IMPORT_DIR . "/" . $fileDir . "/config" );
			wp_mkdir_p( WPIE_UPLOAD_IMPORT_DIR . "/" . $extract_path );

			if ( is_readable( WPIE_IMPORT_CLASSES_DIR . '/file-format/manager.php' ) ) {
				require_once( WPIE_IMPORT_CLASSES_DIR . '/file-format/manager.php' );
			}

			$data_extract = new FileExtract();
			$data = $data_extract->extract( $relative_path . "/" . $fileName, $extract_path );
			unset( $data_extract );
			if ( is_wp_error( $data ) ) {
				return $data;
			}

			$file_list = $this->wpie_get_file_list( WPIE_UPLOAD_IMPORT_DIR . "/" . $extract_path, true, false );

			if ( ! empty( $file_list ) ) {

				if ( isset( $file_list[ "config.json" ] ) ) {

					$configPath = WPIE_UPLOAD_IMPORT_DIR . "/" . $fileDir . "/config/config.json";

					if ( is_readable( $filePath . "/extract/config.json" ) ) {
						copy( $filePath . "/extract/config.json", $configPath );
						wp_delete_file( $filePath . "/extract/config.json" );
					}
					unset( $file_list[ "config.json" ] );
				}

				if ( ! empty( $file_list ) ) {

					foreach ( $file_list as $key => $value ) {

						$new_key = $this->wpie_create_safe_dir_name( $key );

						$new_file_dir = "";

						if ( $key === $value ) {
							$new_file_dir = $fileDir . "/original/extract";
						} else {
							$new_file_dir = $fileDir . "/original/extract/" . dirname( $key );
						}
						$_new_filename = preg_replace( "/[^a-z0-9\_\-\.]/i", '', (string) $value );

						$_temp_path = WPIE_UPLOAD_IMPORT_DIR . "/" . $new_file_dir . "/";

						if ( file_exists( $_temp_path . $value ) && $value !== $_new_filename ) {
							// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Rename sanitized filename in upload directory.
							rename( $_temp_path . $value, $_temp_path . $_new_filename );
						}

						$file_data[ $new_key ] = array(
							'fileDir'      => $new_file_dir,
							'fileName'     => $_new_filename,
							'originalName' => $fileName,
							'baseDir'      => $fileDir
						);

						$fileList[] = array(
							'fileKey'  => $new_key,
							'fileName' => $_new_filename
						);
						if ( $active_file === "" ) {
							$active_file = $new_key;
						}
						unset( $new_file_dir, $new_key, $_new_filename, $_temp_path );
					}
				}
			}
		} else {

			$file_data[ $fileDir ] = array(
				'fileDir'      => $fileDir . "/original",
				'fileName'     => $fileName,
				'originalName' => $fileName,
				'baseDir'      => $fileDir
			);

			$fileList[] = array(
				'fileKey'  => $fileDir,
				'fileName' => sanitize_file_name( $fileName )
			);

			$active_file = $fileDir;
		}

		if ( file_exists( WPIE_IMPORT_CLASSES_DIR . '/class-wpie-import.php' ) ) {
			require_once( WPIE_IMPORT_CLASSES_DIR . '/class-wpie-import.php' );
		}

		$wpie_import = new \wpie\import\WPIE_Import();

		$activeFileData = isset( $file_data[ $active_file ] ) ? $file_data[ $active_file ] : [];

		$sheetData = $wpie_import->getSheetData( $activeFileData );

		$sheetList = isset( $sheetData[ 'sheetList' ] ) ? $sheetData[ 'sheetList' ] : [];

		$activeSheet = isset( $sheetData[ 'activeSheet' ] ) ? $sheetData[ 'activeSheet' ] : '';

		if ( absint( $wpie_import_id ) > 0 ) {

			global $wpdb;

			$new_values = array();

			$template_data = $wpie_import->get_template_by_id( $wpie_import_id );

			if ( $template_data ) {

				$template_options = isset( $template_data->options ) ? maybe_unserialize( $template_data->options ) : array();

				$template_options[ 'importFile' ] = isset( $template_options[ 'importFile' ] ) ? $template_options[ 'importFile' ] : array();

				$template_options[ 'importFile' ] = array_merge( $file_data, $template_options[ 'importFile' ] );

				$template_options[ 'activeFile' ] = $active_file;

				$template_options[ 'sheetList' ] = $sheetList;

				$template_options[ 'activeSheet' ] = $activeSheet;

				$new_values[ 'options' ] = maybe_serialize( $template_options );

				unset( $template_options );
			} else {

				$new_values[ 'options' ] = maybe_serialize( array( "importFile" => $file_data, "activeFile" => $active_file, "sheetList" => $sheetList, "activeSheet" => $activeSheet ) );
			}

			$wpdb->update( $wpdb->prefix . "wpie_template", $new_values, array( 'id' => absint( $wpie_import_id ) ) );

			unset( $new_values, $template_data );
		} else {
			$wpie_import_id = $wpie_import->wpie_generate_template( array( "importFile" => $file_data, "activeFile" => $active_file, "sheetList" => $sheetList, "activeSheet" => $activeSheet ), 'import-draft', 'processing' );
		}

		unset( $filePath, $file_data, $active_file, $wpie_import );

		return array( 'file_list' => $fileList, 'wpie_import_id' => $wpie_import_id, "file_name" => sanitize_file_name( $fileName ), "file_size" => is_file( $current_file ) ? filesize( $current_file ) : 0, "sheetList" => $sheetList, "activeSheet" => $activeSheet );
	}

	/**
	 * Scan directory and collect supported data files.
	 *
	 * @since 1.0.0
	 *
	 * @param string $targetDir           Path to target directory.
	 * @param bool   $remove_extra_files  Whether to delete unsupported files.
	 * @param bool   $time_string         Whether to append modified date string.
	 * @return array
	 */
	public function wpie_get_file_list( $targetDir = "", $remove_extra_files = true, $time_string = false ) {

		$result = array();

		if ( empty( $targetDir ) || ! is_dir( $targetDir ) ) {
			return $result;
		}

		if ( ! isset( $this->wpie_date_format ) || empty( $this->wpie_date_format ) ) {
			$this->wpie_date_format = get_option( 'date_format' );
			$this->wpie_time_format = get_option( 'time_format' );
		}

		$cdir = scandir( $targetDir );

		if ( is_array( $cdir ) ) {

			foreach ( $cdir as $key => $value ) {
				if ( ! in_array( $value, array( ".", ".." ) ) ) {
					$item_path = $targetDir . '/' . $value;
					if ( is_dir( $item_path ) ) {
						$new_data = $this->wpie_get_file_list( $item_path, $remove_extra_files, $time_string );
						if ( is_array( $new_data ) ) {
							foreach ( $new_data as $new_key => $new_info ) {
								$result[ $value . '/' . $new_key ] = $new_info;
							}
						} else {
							$result[ $value . '/' . $new_data ] = $new_data;
						}
						unset( $new_data );
					} else {
						if ( preg_match( '%\W(csv|xml|json|txt|xls|xlsx|ods)$%i', basename( $value ) ) ) {

							if ( $time_string ) {
								$value_data = $value . '&nbsp;&nbsp;&nbsp;' . gmdate( $this->wpie_date_format . ' ' . $this->wpie_time_format, ( filectime( $item_path ) ) );
							} else {
								$value_data = $value;
							}
							$result[ $value ] = $value_data;

							unset( $value_data );
						} elseif ( $remove_extra_files ) {
							@wp_delete_file( $item_path );
						}
					}
				}
			}
		}

		unset( $cdir );

		return $result;
	}

	/**
	 * Unzip archive file to target path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file Archive file.
	 * @param string $to   Destination directory.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	private function unzip_file( $file, $to ) {

		global $wp_version;

		if ( version_compare( $wp_version, '4.6', '<' ) ) {
			return wpie_unzip_file( $file, $to );
		} else {
			return unzip_file( $file, $to );
		}
	}

	/**
	 * Destructor.
	 *
	 * @since 1.0.0
	 */
	public function __destruct() {
		foreach ( $this as $key => $value ) {
			unset( $this->$key );
		}
	}

}

<?php
/**
 * Import Backup Manager
 *
 * @package   wpie\import\backup
 * @author    WP Import Export
 * @copyright 2026 WP Import Export
 * @license   GPL-2.0+
 */

namespace wpie\import\backup;

use WP_Error;
use wpie\export\WPIE_Export;

defined( 'ABSPATH' ) || exit;

/**
 * Class WPIE_Import_Backup
 *
 * Handles snapshot and backup generation prior to overwriting records during import.
 *
 * @since 1.0.0
 */
class WPIE_Import_Backup {

	/**
	 * Backup directory path.
	 *
	 * @var string
	 */
	private $backup_dir;

	/**
	 * Backup index filename.
	 *
	 * @var string
	 */
	private $filename = "backup.json";

	/**
	 * Backup records array.
	 *
	 * @var array
	 */
	private $backup = array();

	/**
	 * Export engine instance.
	 *
	 * @var \wpie\export\WPIE_Export|null
	 */
	private $export;

	/**
	 * Item export template configuration.
	 *
	 * @var array
	 */
	private $item_template = array();

	/**
	 * Import entity type (e.g. 'post', 'taxonomy', 'user').
	 *
	 * @var string
	 */
	private $import_type;

	/**
	 * Taxonomy type for term imports.
	 *
	 * @var string
	 */
	private $import_taxonomy_type;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Initialize backup services and directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $import_type         Entity type being imported.
	 * @param string $wpie_taxonomy_type Taxonomy name if applicable.
	 * @param string $backup_dir          Target backup directory path.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function init_backup_services( $import_type = "", $wpie_taxonomy_type = "", $backup_dir = "" ) {

		$is_dir = $this->set_dir( $backup_dir );

		if ( is_wp_error( $is_dir ) ) {
			return $is_dir;
		}

		unset( $is_dir );

		$backup_file = $this->backup_dir . "/" . $this->filename;
		if ( file_exists( $backup_file ) && is_readable( $backup_file ) ) {
			$content = @file_get_contents( $backup_file );
			if ( $content !== false && trim( $content ) !== "" ) {
				$decoded = json_decode( $content, true );
				$this->backup = is_array( $decoded ) ? $decoded : array();
			}
		}

		$this->import_type = $import_type;

		$this->import_taxonomy_type = $wpie_taxonomy_type;

		$this->init_export();

		return true;
	}

	/**
	 * Initialize export helper for backup creation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_export() {

		if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export.php' ) ) {
			require_once( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export.php' );
		}

		$this->export = new \wpie\export\WPIE_Export();

		$fields = $this->export->prepare_fields( $this->import_type, $this->import_taxonomy_type );

		$item_fields = "";

		if ( $fields ) {

			foreach ( $fields as $field_data ) {

				if ( isset( $field_data[ 'isExported' ] ) && $field_data[ 'isExported' ] == false ) {
					continue;
				}

				if ( isset( $field_data[ 'data' ] ) && is_array( $field_data[ 'data' ] ) ) {

					foreach ( $field_data[ 'data' ] as $_field ) {

						if ( isset( $_field[ 'isExported' ] ) && $_field[ 'isExported' ] == false ) {
							continue;
						}
						$label = isset( $_field[ 'name' ] ) ? $_field[ 'name' ] : "";

						$value = json_encode( $_field );

						$item_fields .= $label . "|~|" . $value . "|~|" . $value . "~||~";

						unset( $label, $value );
					}
				}
			}
		}

		$this->item_template[ "fields_data" ] = $item_fields;

		$this->item_template[ "wpie_export_type" ] = $this->import_type;

		$this->item_template[ "wpie_taxonomy_type" ] = $this->import_taxonomy_type;

		$this->item_template[ "backup_dir" ] = $this->backup_dir;

		if ( ! empty( $this->backup ) && is_array( $this->backup ) ) {
			$this->item_template[ "count" ] = count( $this->backup );
		} else {
			$this->item_template[ "count" ] = 0;
		}
		unset( $item_fields, $fields );
	}

	/**
	 * Validate and set backup directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $backup_dir Path to backup folder.
	 * @return bool|\WP_Error True if writable, WP_Error otherwise.
	 */
	private function set_dir( $backup_dir = "" ) {

		if ( ! empty( $backup_dir ) && wp_is_writable( $backup_dir ) ) {
			$this->backup_dir = $backup_dir;
		} else {
			return new \WP_Error( 'wpie_import_error', __( 'Backup Directory is not writable', 'wp-import-export-lite' ) );
		}
		return true;
	}

	/**
	 * Create backup snapshot for an item before modifying it.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $item_id     Item identifier.
	 * @param bool $is_new_item Whether item is newly created or existing.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function create_backup( $item_id = 0, $is_new_item = false ) {

		if ( ! isset( $this->backup[ $item_id ] ) ) {

			$this->backup[ $item_id ] = array( "is_new_item" => $is_new_item );

			$is_success = @file_put_contents( $this->backup_dir . "/" . $this->filename, json_encode( $this->backup ) );

			if ( $is_success === false ) {
				return new \WP_Error( 'wpie_import_error', __( 'Fail To Generate Log', 'wp-import-export-lite' ) );
			}
			unset( $is_success );

			if ( ! $is_new_item ) {
				$this->generate_backup( $item_id );
			}
		}

		return true;
	}

	/**
	 * Generate backup file via export engine.
	 *
	 * @since 1.0.0
	 *
	 * @param int $item_id Item identifier.
	 * @return void
	 */
	private function generate_backup( $item_id = 0 ) {

		$this->item_template[ "id" ] = $item_id;

		if ( $this->export && method_exists( $this->export, 'init_export' ) ) {
			$this->export->init_export( $this->import_type, "import_backup", $this->item_template );
		}

		$this->item_template[ "count" ]++;
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

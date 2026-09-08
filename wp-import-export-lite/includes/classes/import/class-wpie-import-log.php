<?php
/**
 * Import Log Handler
 *
 * @package   wpie\import\log
 * @author    WP Import Export
 * @copyright 2026 WP Import Export
 * @license   GPL-2.0+
 */

namespace wpie\import\log;

defined( 'ABSPATH' ) || exit;

/**
 * Class WPIE_Import_Log
 *
 * Manages import file logging.
 *
 * @since 1.0.0
 */
class WPIE_Import_Log {

	/**
	 * Log directory path.
	 *
	 * @var string
	 */
	private $log_dir;

	/**
	 * Log file pointer resource.
	 *
	 * @var resource|null
	 */
	private $log_fp = null;

	/**
	 * Log filename.
	 *
	 * @var string
	 */
	private $filename = "import_log.txt";

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Initialize log services.
	 *
	 * @since 1.0.0
	 *
	 * @param string $log_dir Target log directory path.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function init_log_services( $log_dir = "" ) {

		$is_dir = $this->set_dir( $log_dir );

		if ( is_wp_error( $is_dir ) ) {
			return $is_dir;
		}

		unset( $is_dir );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Direct append stream for log file.
		$fp = @fopen( $this->log_dir . "/" . $this->filename, 'a' );
		if ( is_resource( $fp ) ) {
			$this->log_fp = $fp;
		} else {
			$this->log_fp = null;
		}

		return true;
	}

	/**
	 * Set and validate log directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $log_dir Log directory.
	 * @return bool|\WP_Error True if writable, WP_Error otherwise.
	 */
	private function set_dir( $log_dir = "" ) {

		if ( ! empty( $log_dir ) && wp_is_writable( $log_dir ) ) {
			$this->log_dir = $log_dir;
		} else {
			return new \WP_Error( 'wpie_import_error', __( 'Log Directory is not writable', 'wp-import-export-lite' ) );
		}
		return true;
	}

	/**
	 * Append a message to the import log file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $log Log message string.
	 * @return void
	 */
	public function add_log( $log = "" ) {
		if ( is_resource( $this->log_fp ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Direct append stream for log file.
			fwrite( $this->log_fp, $log . PHP_EOL );
		}
	}

	/**
	 * Finalize log operations and close file handle.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function finalyze_process() {
		if ( is_resource( $this->log_fp ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct append stream for log file.
			fclose( $this->log_fp );
		}
		$this->log_fp = null;
	}

	/**
	 * Destructor.
	 *
	 * @since 1.0.0
	 */
	public function __destruct() {
		$this->finalyze_process();
		foreach ( $this as $key => $value ) {
			unset( $this->$key );
		}
	}

}

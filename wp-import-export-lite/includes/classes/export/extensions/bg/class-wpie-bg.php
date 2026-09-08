<?php
/**
 * WPIE Background Export Controller Class
 *
 * Handles background cron execution, token authentication, and lock management for background export tasks.
 *
 * @package    WPIE
 * @subpackage WPIE/Export/Extensions/BG
 */

namespace wpie\export\bg;

use WpieApp\Core\Helpers\Param;
use wpie\export\WPIE_Export;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export.php' ) ) {
	require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export.php';
}

/**
 * Class WPIE_BG
 *
 * Background and external cron controller for exports.
 */
class WPIE_BG extends WPIE_Export {

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Initialize hooks for background exports.
	 *
	 * @return void
	 */
	public function init() {

		add_action( 'init', array( $this, 'init_process' ), 100 );

		add_filter( 'wpie_add_export_extension_process_btn', array( $this, 'add_bg_export_btn' ), 10, 1 );
	}

	/**
	 * Register background export action button template.
	 *
	 * @param array $files Template file paths.
	 * @return array
	 */
	public function add_bg_export_btn( $files = array() ) {

		$fileName = WPIE_EXPORT_CLASSES_DIR . '/extensions/bg/wpie_bg_btn.php';

		if ( ! in_array( $fileName, $files, true ) ) {
			$files[] = $fileName;
		}

		return $files;
	}

	/**
	 * Run background export process iteration.
	 *
	 * @return void|bool
	 */
	public function init_process() {

		if ( ! $this->isValidRequest() ) {

			$wpie_bg_and_cron_processing = maybe_unserialize( get_option( 'wpie_bg_and_cron_processing', '' ) );

			$cronMethod = isset( $wpie_bg_and_cron_processing['method'] ) ? (string) $wpie_bg_and_cron_processing['method'] : '';

			$cron_token = Param::getSanitized( 'wpie_cron_token', 'text', '' );

			if ( 'external' !== $cronMethod || empty( $cron_token ) ) {
				return true;
			}

			wp_send_json(
				array(
					'status'  => 'error',
					'plugin'  => 'WP Import Export',
					'message' => __( 'Invalid Request', 'wp-import-export-lite' ),
				)
			);
		}

		$this->unlockTemplate();

		$this->setBgExport();
	}

	/**
	 * Dispatch one export batch step in the background.
	 *
	 * @return void
	 */
	public function setBgExport() {

		global $wpdb;

		$bgProcess = get_option( 'wpie_bg_process' );

		$wpieProcess = array();

		if ( is_string( $bgProcess ) && trim( $bgProcess ) !== '' ) {
			$wpieProcess = maybe_unserialize( $bgProcess );
		}

		if ( ! is_array( $wpieProcess ) || empty( $wpieProcess ) ) {
			$wpieProcess = array();
		}

		$wpieProcess['processing']           = isset( $wpieProcess['processing'] ) ? $wpieProcess['processing'] : array();
		$wpieProcess['processing']['export'] = ( ! empty( $wpieProcess['processing'] ) && isset( $wpieProcess['processing']['export'] ) && is_array( $wpieProcess['processing']['export'] ) ) ? $wpieProcess['processing']['export'] : array();

		$template = $this->getTemplate( $wpieProcess['processing']['export'] );

		if ( ! isset( $template->id ) ) {
			return;
		}

		$templateId = (int) $template->id;

		$wpieProcess['processing']['export'][] = $templateId;

		update_option( 'wpie_bg_process', maybe_serialize( $wpieProcess ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wpie_template SET `process_lock` = 1 WHERE `id` = %d", $templateId ) );

		$export_type = isset( $template->opration_type ) ? (string) $template->opration_type : 'post';

		$opration = isset( $template->opration ) ? (string) $template->opration : 'export';

		$this->init_export( $export_type, $opration, $template );

		$wpieProcess['processing']['export'] = array_values( array_diff( $wpieProcess['processing']['export'], array( $templateId ) ) );

		update_option( 'wpie_bg_process', maybe_serialize( $wpieProcess ) );

		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wpie_template SET `process_lock` = 0 WHERE `id` = %d", $templateId ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->setCronMsg( $templateId );
	}

	/**
	 * Retrieve next pending background export template record.
	 *
	 * @param array $excludes Template IDs currently being processed.
	 * @return object|false
	 */
	public function getTemplate( $excludes = array() ) {

		global $wpdb;

		$clean_excludes = ( ! empty( $excludes ) && is_array( $excludes ) ) ? array_values( array_filter( array_map( 'absint', array_unique( $excludes ) ) ) ) : array();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( ! empty( $clean_excludes ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $clean_excludes ), '%d' ) );
			$template     = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}wpie_template WHERE `opration` IN ('export', 'schedule_export') AND `status` LIKE %s AND `process_lock` = 0 AND `id` NOT IN ({$placeholders}) ORDER BY `id` ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					array_merge( array( '%background%' ), $clean_excludes )
				)
			);
		} else {
			$template = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}wpie_template WHERE `opration` IN ('export', 'schedule_export') AND `status` LIKE %s AND `process_lock` = 0 ORDER BY `id` ASC LIMIT 1",
					'%background%'
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		if ( isset( $template->id ) && absint( $template->id ) > 0 ) {
			return $template;
		}

		unset( $template );

		return false;
	}

	/**
	 * Release stale process locks for interrupted background jobs.
	 *
	 * @return void
	 */
	private function unlockTemplate() {

		global $wpdb;

		$wpieProcess = maybe_unserialize( get_option( 'wpie_bg_process', array() ) );

		if ( ! is_array( $wpieProcess ) || empty( $wpieProcess ) ) {
			$wpieProcess = array();
		}

		$wpieProcess['processing'] = isset( $wpieProcess['processing'] ) ? $wpieProcess['processing'] : array();

		$processingIds = ( ! empty( $wpieProcess['processing'] ) && isset( $wpieProcess['processing']['export'] ) && is_array( $wpieProcess['processing']['export'] ) ) ? $wpieProcess['processing']['export'] : array();

		if ( empty( $processingIds ) ) {
			$this->updateTemplateLock();
			return;
		}

		$idList = array_values( array_filter( array_map( 'absint', $processingIds ) ) );

		if ( empty( $idList ) ) {
			$this->updateTemplateLock();
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $idList ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$templates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `id`, `process_log`, `process_lock`, `last_update_date` FROM {$wpdb->prefix}wpie_template WHERE `id` IN ({$placeholders}) ORDER BY `id` ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$idList
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$updateIds = array();

		foreach ( $templates as $template ) {

			$id = isset( $template->id ) ? (int) $template->id : 0;

			if ( 0 === (int) $template->process_lock ) {
				$updateIds[] = $id;
				continue;
			}

			$currentTime = strtotime( current_time( 'mysql' ) );

			$allowTime = 60 * 5;

			$last_update_date = isset( $template->last_update_date ) ? (string) $template->last_update_date : '';
			$last_update_time = ( '' !== $last_update_date ) ? strtotime( $last_update_date ) : 0;

			if ( $currentTime >= ( $last_update_time + $allowTime ) ) {
				$updateIds[] = $id;
				continue;
			}
		}

		$this->updateTemplateLock( $updateIds );

		$wpieProcess['processing']['export'] = array_values( array_diff( $processingIds, $updateIds ) );

		update_option( 'wpie_bg_process', maybe_serialize( $wpieProcess ) );
	}

	/**
	 * Reset lock column in database for given template IDs.
	 *
	 * @param array $ids Array of template IDs.
	 * @return void
	 */
	private function updateTemplateLock( $ids = array() ) {

		$clean_ids = ( ! empty( $ids ) && is_array( $ids ) ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( ! empty( $clean_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $clean_ids ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}wpie_template SET `process_lock` = 0 WHERE `process_lock` = 1 AND `opration` IN ('export', 'schedule_export') AND `id` IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					$clean_ids
				)
			);
		} else {
			$wpdb->query( "UPDATE {$wpdb->prefix}wpie_template SET `process_lock` = 0 WHERE `process_lock` = 1 AND `opration` IN ('export', 'schedule_export')" );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Validate request authorization for external cron triggers.
	 *
	 * @return bool
	 */
	private function isValidRequest() {

		$wpie_bg_and_cron_processing = maybe_unserialize( get_option( 'wpie_bg_and_cron_processing', '' ) );

		$cronMethod = isset( $wpie_bg_and_cron_processing['method'] ) ? (string) $wpie_bg_and_cron_processing['method'] : '';

		if ( 'external' !== $cronMethod ) {
			return true;
		}

		$token = Param::getSanitized( 'wpie_cron_token', 'text', '' );

		$siteToken = isset( $wpie_bg_and_cron_processing['token'] ) ? (string) $wpie_bg_and_cron_processing['token'] : '';

		if ( '' === $siteToken || '' === $token || ! hash_equals( $siteToken, $token ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Send JSON response for external cron calls.
	 *
	 * @param int $templateId Template ID.
	 * @return void
	 */
	private function setCronMsg( $templateId ) {

		$wpie_bg_and_cron_processing = maybe_unserialize( get_option( 'wpie_bg_and_cron_processing', '' ) );

		$cronMethod = isset( $wpie_bg_and_cron_processing['method'] ) ? (string) $wpie_bg_and_cron_processing['method'] : '';

		if ( 'external' !== $cronMethod ) {
			return;
		}

		wp_send_json(
			array(
				'status'  => 'success',
				'plugin'  => 'WP Import Export',
				'message' => 'WP Import Export : Export #' . absint( $templateId ) . ' Processing',
			)
		);
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

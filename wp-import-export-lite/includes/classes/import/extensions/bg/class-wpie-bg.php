<?php

namespace wpie\import\bg;

use WpieApp\Core\Helpers\Param;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_IMPORT_CLASSES_DIR . '/class-wpie-import.php' ) ) {

        require_once(WPIE_IMPORT_CLASSES_DIR . '/class-wpie-import.php');
}

class WPIE_BG_Import extends \wpie\import\WPIE_Import {

        private $process_lock = false;

        public function __construct() {

                add_action( 'init', array( $this, 'init_process' ), 100 );

                add_filter( 'wpie_add_import_extension_process_btn_files', array( $this, 'wpie_add_bg_process_btn' ), 10, 1 );
        }

        public function wpie_add_bg_process_btn( $files = array() ) {

                $fileName = WPIE_IMPORT_CLASSES_DIR . '/extensions/bg/wpie_bg_btn.php';

                if ( !in_array( $fileName, $files ) ) {

                        $files[] = $fileName;
                }

                return $files;
        }

        public function wpie_bg_import_init() {
                $id = $this->get_bg_template_id();

                if ( $id && absint( $id ) > 0 ) {

                        parent::wpie_import_process_data( $id );
                }
                unset( $id );
        }

        public function get_bg_template_id() {

                global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM {$wpdb->prefix}wpie_template WHERE `opration` IN ('import','schedule_import') AND `status` LIKE %s AND `process_lock` = 0 ORDER BY `id` ASC LIMIT 0,1",
				'%background%'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

                return $id;
        }

        public function init_process() {

                if ( !$this->isValidRequest() ) {

                        $wpie_bg_and_cron_processing = \maybe_unserialize( \get_option( "wpie_bg_and_cron_processing" ) );

                        $cronMethod = isset( $wpie_bg_and_cron_processing[ 'method' ] ) ? $wpie_bg_and_cron_processing[ 'method' ] : "";

                        $cron_token = Param::getSanitized( 'wpie_cron_token', 'text', '' );

                        if ( $cronMethod !== "external" || empty( $cron_token ) ) {
                                return true;
                        }

                        $respons = [
                                "status"  => "error",
                                "plugin"  => "WP Import Export",
                                "message" => "Invalid Request",
                        ];

                        echo json_encode( $respons );

                        die();
                }

                $this->unlockTemplate();

                $this->setBgImport();
        }

        public function setBgImport() {

                global $wpdb;

                $bgProcess = \get_option( "wpie_bg_process" );

                $wpieProcess = [];

                if ( is_string( $bgProcess ) && trim( $bgProcess ) !== '' ) {
                        $wpieProcess = \maybe_unserialize( $bgProcess );
                }

                if ( !is_array( $wpieProcess ) || empty( $wpieProcess ) ) {
                        $wpieProcess = [];
                }

                $wpieProcess[ 'processing' ] = isset( $wpieProcess[ 'processing' ] ) ? $wpieProcess[ 'processing' ] : [];

                $wpieProcess[ 'processing' ][ 'import' ] = !empty( $wpieProcess[ 'processing' ] ) && isset( $wpieProcess[ 'processing' ][ 'import' ] ) ? $wpieProcess[ 'processing' ][ 'import' ] : [];

                $template = $this->getTemplate( $wpieProcess[ 'processing' ][ 'import' ] );

                if ( !isset( $template->id ) ) {
                        $this->setCronMsg();
                        return;
                }
                $templateId = $template->id;

                $wpieProcess[ 'processing' ][ 'import' ][] = $templateId;

                \update_option( "wpie_bg_process", \maybe_serialize( $wpieProcess ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wpie_template SET `process_lock` = 1 WHERE `id` = %d ", $templateId ) );

		parent::wpie_import_process_data( $templateId );

		$wpieProcess[ 'processing' ][ 'import' ] = array_diff( $wpieProcess[ 'processing' ][ 'import' ], [ $templateId ] );

		\update_option( "wpie_bg_process", \maybe_serialize( $wpieProcess ) );

		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wpie_template SET `process_lock` = 0 WHERE `id` = %d ", $templateId ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$this->setCronMsg( $templateId );
	}

	public function getTemplate( $excludes = [] ) {

		global $wpdb;

		$clean_excludes = ( ! empty( $excludes ) && is_array( $excludes ) ) ? array_values( array_filter( array_map( 'absint', array_unique( $excludes ) ) ) ) : array();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( ! empty( $clean_excludes ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $clean_excludes ), '%d' ) );
			$template     = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}wpie_template WHERE `opration` IN ('import','schedule_import') AND `status` LIKE %s AND `process_lock` = 0 AND `id` NOT IN ({$placeholders}) ORDER BY `id` ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					array_merge( array( '%background%' ), $clean_excludes )
				)
			);
		} else {
			$template = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}wpie_template WHERE `opration` IN ('import','schedule_import') AND `status` LIKE %s AND `process_lock` = 0 ORDER BY `id` ASC LIMIT 1",
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

	private function unlockTemplate() {

		global $wpdb;

		$wpieProcess = \maybe_unserialize( \get_option( "wpie_bg_process" ) );

		if ( ! $wpieProcess || empty( $wpieProcess ) ) {
			$wpieProcess = [];
		}

		$wpieProcess[ 'processing' ] = isset( $wpieProcess[ 'processing' ] ) ? $wpieProcess[ 'processing' ] : [];

		$processingIds = ! empty( $wpieProcess[ 'processing' ] ) && isset( $wpieProcess[ 'processing' ][ 'import' ] ) ? $wpieProcess[ 'processing' ][ 'import' ] : [];

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
				"SELECT `id`,`process_log`,`process_lock`,`last_update_date` FROM {$wpdb->prefix}wpie_template WHERE `id` IN ({$placeholders}) ORDER BY `id` ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$idList
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$updateIds = [];

		foreach ( $templates as $template ) {

			$id = isset( $template->id ) ? $template->id : 0;

			if ( intval( $template->process_lock ) === 0 ) {
				$updateIds[] = $id;
				continue;
			}

			$currentTime = strtotime( current_time( "mysql" ) );

			$allowTime = 60 * 5;

			$last_update_date = isset( $template->last_update_date ) ? $template->last_update_date : "";

			if ( $currentTime >= ( strtotime( $last_update_date ) + $allowTime ) ) {
				$updateIds[] = $id;
				continue;
			}
		}

		$this->updateTemplateLock( $updateIds );

		$wpieProcess[ 'processing' ][ 'import' ] = array_diff( $processingIds, $updateIds );

		\update_option( "wpie_bg_process", \maybe_serialize( $wpieProcess ) );
	}

	private function updateTemplateLock( $ids = [] ) {

		$clean_ids = ( ! empty( $ids ) && is_array( $ids ) ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( ! empty( $clean_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $clean_ids ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}wpie_template SET `process_lock` = 0 WHERE `process_lock` = 1 AND `opration` IN ('import','schedule_import') AND `id` IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					$clean_ids
				)
			);
		} else {
			$wpdb->query( "UPDATE {$wpdb->prefix}wpie_template SET `process_lock` = 0 WHERE `process_lock` = 1 AND `opration` IN ('import','schedule_import')" );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Validate external cron request token using constant-time hash comparison.
	 *
	 * @since 1.0.0
	 * @return bool True if request is valid, false otherwise.
	 */
	private function isValidRequest() {

		$wpie_bg_and_cron_processing = \maybe_unserialize( \get_option( "wpie_bg_and_cron_processing", "" ) );

		$cronMethod = isset( $wpie_bg_and_cron_processing[ 'method' ] ) ? $wpie_bg_and_cron_processing[ 'method' ] : "";

		if ( $cronMethod !== "external" ) {
			return true;
		}

		$token = Param::getSanitized( 'wpie_cron_token', 'text', '' );

		$siteToken = isset( $wpie_bg_and_cron_processing[ 'token' ] ) ? (string) $wpie_bg_and_cron_processing[ 'token' ] : "";

		if ( empty( $siteToken ) || empty( $token ) ) {
			return false;
		}

		if ( function_exists( 'hash_equals' ) ) {
			return hash_equals( $siteToken, (string) $token );
		}

		return $siteToken === (string) $token;
	}

	/**
	 * Send JSON response for external cron invocation.
	 *
	 * @since 1.0.0
	 *
	 * @param int $templateId Template ID.
	 * @return void
	 */
	private function setCronMsg( $templateId = 0 ) {

		$wpie_bg_and_cron_processing = \maybe_unserialize( \get_option( "wpie_bg_and_cron_processing" ) );

		$cronMethod = isset( $wpie_bg_and_cron_processing[ 'method' ] ) ? $wpie_bg_and_cron_processing[ 'method' ] : "";

		if ( $cronMethod !== "external" ) {
			return;
		}

		if ( absint( $templateId ) > 0 ) {
			$response = [
				"status"  => "success",
				"plugin"  => "WP Import Export",
				"message" => "WP Import Export : Import #" . $templateId . " Processing"
			];
		} else {
			$response = [
				"status"  => "success",
				"plugin"  => "WP Import Export",
				"message" => "WP Import Export : No More pending schedules"
			];
		}

		wp_send_json( $response );
	}

}

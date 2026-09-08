<?php

namespace wpie\import\upload\existingfile;

use WP_Error;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_IMPORT_CLASSES_DIR . '/class-wpie-upload.php' ) ) {
        require_once(WPIE_IMPORT_CLASSES_DIR . '/class-wpie-upload.php');
}

class WPIE_Existing_File extends \wpie\import\upload\WPIE_Upload {

        public function __construct() {
                
        }

        public function wpie_upload_file( $fileName = "", $wpie_import_id = "" ) {

                if ( empty( $fileName ) ) {

                        unset( $fileName );

                        return new \WP_Error( 'wpie_import_error', __( 'File Name is empty', 'wp-import-export-lite' ) );
                }

                if ( ! defined( 'WPIE_UPLOAD_MAIN_DIR' ) ) {
                        return new \WP_Error( 'wpie_import_error', __( 'Upload directory is not defined', 'wp-import-export-lite' ) );
                }

                $baseDir = realpath( WPIE_UPLOAD_MAIN_DIR );
                if ( false === $baseDir || ! is_dir( $baseDir ) ) {
                        return new \WP_Error( 'wpie_import_error', __( 'Upload directory not found', 'wp-import-export-lite' ) );
                }

                $filePath     = WPIE_UPLOAD_MAIN_DIR . '/' . ltrim( $fileName, '/\\' );
                $realFilePath = realpath( $filePath );

                if ( false === $realFilePath || ! is_file( $realFilePath ) ) {

                        unset( $fileName, $filePath, $realFilePath, $baseDir );

                        return new \WP_Error( 'wpie_import_error', __( 'File not exist', 'wp-import-export-lite' ) );
                }

                // Normalize path separators
                $normalizedBaseDir  = rtrim( str_replace( '\\', '/', $baseDir ), '/' ) . '/';
                $normalizedFilePath = str_replace( '\\', '/', $realFilePath );

                // Strictly confine to WPIE_UPLOAD_MAIN_DIR
                $isWindows   = ( DIRECTORY_SEPARATOR === '\\' );
                $isContained = $isWindows
                        ? ( 0 === stripos( $normalizedFilePath, $normalizedBaseDir ) )
                        : ( 0 === strpos( $normalizedFilePath, $normalizedBaseDir ) );

                if ( ! $isContained ) {
                        unset( $fileName, $filePath, $realFilePath, $baseDir, $normalizedBaseDir, $normalizedFilePath );

                        return new \WP_Error( 'wpie_import_error', __( 'Invalid file path', 'wp-import-export-lite' ) );
                }

                // Check allowed extensions BEFORE creating directories or copying
                $safeFileName = function_exists( 'wp_basename' ) ? wp_basename( $realFilePath ) : basename( $realFilePath );
                if ( ! preg_match( '%\W(xml|zip|csv|xls|xlsx|ods|txt|json|gz|tar)$%i', trim( $safeFileName ) ) ) {
                        unset( $fileName, $filePath, $realFilePath, $baseDir, $normalizedBaseDir, $normalizedFilePath, $safeFileName );

                        return new \WP_Error( 'wpie_import_error', __( 'Uploaded file must be XML, CSV, ZIP, XLS, XLSX, ODS, TXT, JSON, GZ, TAR', 'wp-import-export-lite' ) );
                }

                $newfiledir = parent::wpie_create_safe_dir_name( $safeFileName );

                $destDir = WPIE_UPLOAD_IMPORT_DIR . '/' . $newfiledir . '/original';

                wp_mkdir_p( WPIE_UPLOAD_IMPORT_DIR . '/' . $newfiledir );
                wp_mkdir_p( $destDir );
                wp_mkdir_p( WPIE_UPLOAD_IMPORT_DIR . '/' . $newfiledir . '/parse' );
                wp_mkdir_p( WPIE_UPLOAD_IMPORT_DIR . '/' . $newfiledir . '/parse/chunks' );

                $destFile = $destDir . '/' . $safeFileName;

                if ( file_exists( $destFile ) ) {
                        return new \WP_Error( 'wpie_import_error', __( 'Destination file already exists', 'wp-import-export-lite' ) );
                }

                if ( ! copy( $realFilePath, $destFile ) ) {
                        return new \WP_Error( 'wpie_import_error', __( 'Failed to copy file', 'wp-import-export-lite' ) );
                }

                unset( $filePath, $realFilePath, $baseDir, $normalizedBaseDir, $normalizedFilePath, $destDir, $destFile );

                return $this->wpie_manage_import_file( $safeFileName, $newfiledir, $wpie_import_id );
        }

}

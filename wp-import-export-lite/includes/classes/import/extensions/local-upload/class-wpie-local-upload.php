<?php


namespace wpie\import\upload\local;

use WP_Error;
use WpieApp\Core\Helpers\Param;
use WpieApp\Core\Helpers\Sanitizer;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_IMPORT_CLASSES_DIR . '/class-wpie-upload.php' ) ) {
        require_once(WPIE_IMPORT_CLASSES_DIR . '/class-wpie-upload.php');
}

class WPIE_Local_Upload extends \wpie\import\upload\WPIE_Upload {

        public function __construct() {
                
        }

        public function upload_local_file() {

                if ( !is_dir( WPIE_UPLOAD_DIR ) || !wp_is_writable( WPIE_UPLOAD_DIR ) ) {

                        return new \WP_Error( 'wpie_import_error', __( 'Uploads folder is not writable', 'wp-import-export-lite' ) );
                }

                $raw_name = Param::post( 'name', '' );
                $fileName = ! empty( $raw_name ) ? Sanitizer::clean( preg_replace( "/[^a-z0-9\_\-\.]/i", '', $raw_name ), 'filename' ) : '';

                if ( !preg_match( '%\W(zip|csv|xls|xlsx|xml|txt|json|ods|tar|gz)$%i', trim( basename( $fileName ) ) ) ) {

                        unset( $fileName );

                        return new \WP_Error( 'wpie_import_error', __( 'Uploaded file must be CSV, ZIP, XLS, XLSX, XML, TXT, JSON, ODS, TAR, GZ', 'wp-import-export-lite' ) );
                }

                $wpie_import_id = Param::postSanitized( 'wpie_import_id', 'int', 0 );

                $maxFileAge = 5 * 3600;

                $chunk = Param::postSanitized( 'chunk', 'int', 0 );

                $chunks = Param::postSanitized( 'chunks', 'int', 0 );

                if ( $chunks < 2 && file_exists( WPIE_UPLOAD_TEMP_DIR . '/' . $fileName ) ) {

                        $ext = strrpos( $fileName, '.' );

                        $fileName_a = substr( $fileName, 0, $ext );

                        $fileName_a = (strlen( $fileName_a ) < 30) ? $fileName_a : substr( $fileName_a, 0, 30 );

                        $fileName_b = substr( $fileName, $ext );

                        $count = 1;

                        while ( file_exists( WPIE_UPLOAD_TEMP_DIR . '/' . $fileName_a . '_' . $count . $fileName_b ) ) {

                                $count++;
                        }

                        $fileName = sanitize_file_name( $fileName_a ) . '_' . $count . $fileName_b;

                        unset( $ext, $fileName_a, $fileName_b, $count );
                }

                $filePath = WPIE_UPLOAD_TEMP_DIR . '/' . $fileName;

                if ( is_dir( WPIE_UPLOAD_TEMP_DIR ) && ($dir = opendir( WPIE_UPLOAD_TEMP_DIR )) ) {

                        while ( ($file = readdir( $dir )) !== false ) {

                                $tmpfilePath = WPIE_UPLOAD_TEMP_DIR . '/' . $file;

                                if ( preg_match( '/\.part$/', $file ) && (filemtime( $tmpfilePath ) < time() - $maxFileAge) && ($tmpfilePath != "{$filePath}.part") && file_exists( $tmpfilePath ) ) {
                                        wp_delete_file( $tmpfilePath );
                                }

                                unset( $tmpfilePath );
                        }

                        closedir( $dir );
                } else {
                        unset( $chunk, $maxFileAge, $chunks, $filePath );
                        return new \WP_Error( 'wpie_import_error', __( 'Failed to open temp directory', 'wp-import-export-lite' ) );
                }
                unset( $maxFileAge );

                $contentType = Param::server( 'CONTENT_TYPE', '' );
                if ( empty( $contentType ) ) {
                        $contentType = Param::server( 'HTTP_CONTENT_TYPE', '' );
                }

                // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in caller WPIE_Local_Upload_Extension::upload_local_file(). Stream operations required for chunked upload assembly.
                if ( strpos( $contentType, "multipart" ) !== false ) {

                        unset( $contentType );

                        if ( $_FILES && file_exists( $_FILES[ 'local_file' ][ 'tmp_name' ] ) && is_uploaded_file( $_FILES[ 'local_file' ][ 'tmp_name' ] ) ) {

                                $out = fopen( "{$filePath}.part", $chunk == 0 ? "wb" : "ab" );

                                if ( $out ) {

                                        $in = fopen( $_FILES[ 'local_file' ][ 'tmp_name' ], "rb" );

                                        if ( $in ) {
                                                while ( $buff = fread( $in, 4096 ) ) {
                                                        fwrite( $out, $buff );
                                                }
                                        } else {

                                                fclose( $out );

                                                unset( $out );

                                                return new \WP_Error( 'wpie_import_error', __( 'Failed to open input stream.', 'wp-import-export-lite' ) );
                                        }

                                        fclose( $in );

                                        fclose( $out );

                                        unset( $in, $out );
                                } else {
                                        unset( $out );
                                        return new \WP_Error( 'wpie_import_error', __( 'Failed to open output stream.', 'wp-import-export-lite' ) );
                                }
                        } else {
                                return new \WP_Error( 'wpie_import_error', __( 'Failed to move uploaded file.', 'wp-import-export-lite' ) );
                        }
                } else {

                        unset( $contentType );

                        $out = fopen( "{$filePath}.part", $chunk == 0 ? "wb" : "ab" );

                        if ( $out ) {

                                $in = fopen( "php://input", "rb" );

                                if ( $in ) {
                                        while ( $buff = fread( $in, 4096 ) ) {
                                                fwrite( $out, $buff );
                                        }
                                } else {

                                        fclose( $out );

                                        unset( $out );

                                        return new \WP_Error( 'wpie_import_error', __( 'Failed to open input stream.', 'wp-import-export-lite' ) );
                                }

                                fclose( $in );

                                fclose( $out );

                                unset( $in, $out );
                        } else {

                                unset( $out );

                                return new \WP_Error( 'wpie_import_error', __( 'Failed to open output stream.', 'wp-import-export-lite' ) );
                        }
                }
                // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

                $newfiledir = "";

                if ( !$chunks || $chunk == $chunks - 1 ) {

                        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Rename assembled chunked file.
                        rename( "{$filePath}.part", $filePath );

                        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Adjust assembled file permissions.
                        chmod( $filePath, 0755 );

                        $newfiledir = parent::wpie_create_safe_dir_name( $fileName );

                        $newFilePath = WPIE_UPLOAD_IMPORT_DIR . "/" . $newfiledir;

                        wp_mkdir_p( $newFilePath );

                        wp_mkdir_p( $newFilePath . "/original" );

                        wp_mkdir_p( $newFilePath . "/parse" );

                        wp_mkdir_p( $newFilePath . "/parse/chunks" );

                        copy( $filePath, $newFilePath . "/original/" . $fileName );

                        if ( file_exists( $filePath ) ) {
                                wp_delete_file( $filePath );
                        }
                        unset( $newFilePath, $filePath, $chunk, $chunks );

                        return parent::wpie_manage_import_file( $fileName, $newfiledir, $wpie_import_id );
                } else {
                        unset( $chunk, $chunks, $newfiledir );
                        return 'processing';
                }
        }

        public function __destruct() {
                foreach ( $this as $key => $value ) {
                        unset( $this->$key );
                }
        }

}

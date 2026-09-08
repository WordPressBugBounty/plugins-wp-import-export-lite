<?php


namespace wpie\import\Downloader;

defined( 'ABSPATH' ) || exit;

class File_Header {

        private $url;
        private $type;

        public function __construct( $url = "", $type = "file" ) {
                $this->url  = $url;
                $this->type = $type;
        }

        public function get_filename() {

                if ( empty( $this->url ) ) {
                        return new \WP_Error( 'wpie_import_error', __( 'File Download Error : File URL is empty', 'wp-import-export-lite' ) );
                }

                $validated_url = \wp_http_validate_url( $this->url );
                if ( false === $validated_url ) {
                        return new \WP_Error( 'wpie_import_error', __( 'File Download Error : File URL is not valid', 'wp-import-export-lite' ) );
                }
                $this->url = $validated_url;

                $headers = $this->get_headers();

                if ( is_wp_error( $headers ) ) {
                        return $headers;
                }

                return $this->generate_filename( $headers );
        }

        private function get_headers() {

                return $this->get_headers_by_wp_request();
        }

        private function get_headers_by_wp_request() {

                $response = wp_safe_remote_head( $this->url, [
                        'timeout'     => 30,
                        'redirection' => 10,
                        'sslverify'   => true,
                ] );

                if ( is_wp_error( $response ) ) {
                        return $response;
                }

                $response_code = wp_remote_retrieve_response_code( $response );

                // Some servers do not support HEAD requests (e.g. 405 Method Not Allowed or 501 Not Implemented).
                // Safely retry using a ranged GET request via WordPress HTTP API without downloading the entire body.
                if ( 405 === $response_code || 501 === $response_code ) {
                        $response = wp_safe_remote_get( $this->url, [
                                'timeout'     => 30,
                                'redirection' => 10,
                                'sslverify'   => true,
                                'headers'     => [ 'Range' => 'bytes=0-0' ],
                        ] );

                        if ( is_wp_error( $response ) ) {
                                return $response;
                        }

                        $response_code = wp_remote_retrieve_response_code( $response );
                }

                if ( 200 !== $response_code && 206 !== $response_code ) {
                        $message = trim( wp_remote_retrieve_response_message( $response ) );
                        if ( empty( $message ) ) {
                                /* translators: %s: HTTP response status code. */
                                $message = sprintf( __( "File Download Error : %s invalid http response status code", 'wp-import-export-lite' ), $response_code );
                        }
                        return new \WP_Error( 'http_' . $response_code, $message );
                }

                $raw_headers = wp_remote_retrieve_headers( $response );
                $data        = [];

                if ( is_iterable( $raw_headers ) || is_array( $raw_headers ) ) {
                        foreach ( $raw_headers as $key => $value ) {
                                if ( is_array( $value ) ) {
                                        $value = end( $value );
                                }
                                $data[ strtolower( $key ) ] = $value;
                        }
                }

                if ( ! isset( $data[ 'content-type' ] ) ) {
                        $ct = wp_remote_retrieve_header( $response, 'content-type' );
                        if ( ! empty( $ct ) ) {
                                $data[ 'content-type' ] = is_array( $ct ) ? end( $ct ) : $ct;
                        }
                }

                if ( ! isset( $data[ 'content-disposition' ] ) ) {
                        $cd = wp_remote_retrieve_header( $response, 'content-disposition' );
                        if ( ! empty( $cd ) ) {
                                $data[ 'content-disposition' ] = is_array( $cd ) ? end( $cd ) : $cd;
                        }
                }

                $data[ 'status' ] = $response_code;

                return $data;
        }

        private function generate_filename( $headers = [] ) {

                $url_data  = wp_parse_url( urldecode( $this->url ) );
                $url_path  = isset( $url_data[ 'path' ] ) ? $url_data[ 'path' ] : "";
                $path_info = trim( $url_path ) !== "" ? pathinfo( $url_path ) : pathinfo( urldecode( $this->url ) );
                $url_ext   = isset( $path_info[ 'extension' ] ) && !empty( $path_info[ 'extension' ] ) ? strtolower( trim( $path_info[ 'extension' ] ) ) : "";

                $valid_filename = "";
                $valid_ext      = "";

                if ( trim( $url_ext ) !== "" ) {

                        $temp_ext = $this->search_ext_from_data( $url_ext );

                        if ( trim( $temp_ext ) !== "" ) {

                                $valid_ext = $temp_ext;

                                $_filename      = isset( $path_info[ 'filename' ] ) && !empty( $path_info[ 'filename' ] ) ? $path_info[ 'filename' ] : "";
                                $valid_filename = empty( $_filename ) ? "" : $_filename . '.' . strtolower( trim( $valid_ext ) );
                                unset( $_filename );
                        }
                        unset( $temp_ext );
                }


                if ( empty( trim( $valid_ext ) ) ) {

                        $content_type = isset( $headers[ 'content-type' ] ) ? $headers[ 'content-type' ] : "";

                        //get ext from headers
                        $content_ext = $this->search_ext_from_data( $content_type );
                        if ( trim( $content_ext ) !== "" ) {
                                $valid_ext = $content_ext;
                        }
                        unset( $content_type, $content_ext );
                }

                $temp_filename = "";

                if ( empty( trim( $valid_filename ) ) ) {

                        //get headers data
                        $content_disposition = isset( $headers[ 'content-disposition' ] ) ? $headers[ 'content-disposition' ] : "";

                        //get filename from headers
                        $filename = $this->get_filename_from_content_disposition( $content_disposition );

                        if ( trim( $filename ) !== "" ) {
                                $valid_filename = pathinfo( $filename, PATHINFO_FILENAME );
                                $temp_filename  = $filename;
                        }
                        unset( $content_disposition, $filename );
                }

                if ( (trim( $valid_ext ) === "" || $valid_ext === "jpeg" ) && trim( $temp_filename ) !== "" ) {

                        $temp_ext = pathinfo( $temp_filename, PATHINFO_EXTENSION );

                        $temp_ext = $this->search_ext_from_data( $temp_ext );

                        if ( trim( $temp_ext ) !== "" && ( trim( $valid_ext ) === "" || $temp_ext === "jpg") ) {

                                $valid_ext = $temp_ext;
                        }

                        unset( $temp_ext );
                }

                if ( trim( $valid_filename ) === "" ) {
                        $valid_filename = time() . uniqid();
                }

                $valid_filename = $this->rtrim_str( $valid_filename, "." . $valid_ext ) . "." . $valid_ext;

                if ( !$this->is_valid_file( $valid_filename ) ) {
                        return new \WP_Error( 'wpie_error', __( "File Download Error : Invalid File Format", 'wp-import-export-lite' ) );
                }

                return $valid_filename;
        }

        private function rtrim_str( $str = "", $mask = "" ) {

                if ( empty( $str ) || empty( $mask ) || strpos( strtolower( $str ), strtolower( $mask ) ) === false ) {
                        return $str;
                }

                $mask = strtolower( $mask );

                $mask_len = strlen( $mask );

                while ( strtolower( substr( $str, -($mask_len) ) ) === $mask ) {
                        $str = substr( $str, 0, -($mask_len) );
                }

                return $str;
        }

        private function get_filename_from_content_disposition( $content_disposition = "" ) {

                if ( empty( $content_disposition ) ) {
                        return "";
                }

                $regex = '/.*?filename=(?<fn>[^\s]+|\x22[^\x22]+\x22)\x3B?.*$/m';

                $new_file_data = null;

                $original_name = "";

                if ( preg_match( $regex, $content_disposition, $new_file_data ) ) {

                        if ( isset( $new_file_data[ 'fn' ] ) && !empty( $new_file_data[ 'fn' ] ) ) {
                                $wp_filetype = wp_check_filetype( $new_file_data[ 'fn' ] );
                                if ( isset( $wp_filetype[ 'ext' ] ) && (!empty( $wp_filetype[ 'ext' ] )) && isset( $wp_filetype[ 'type' ] ) && (!empty( $wp_filetype[ 'type' ] )) ) {
                                        $original_name = $new_file_data[ 'fn' ];
                                }
                        }
                }

                if ( empty( $original_name ) ) {

                        $regex = '/.*filename=([\'\"]?)([^\"]+)\1/';

                        if ( preg_match( $regex, $content_disposition, $new_file_data ) ) {

                                if ( isset( $new_file_data[ '2' ] ) && !empty( $new_file_data[ '2' ] ) ) {
                                        $wp_filetype = wp_check_filetype( $new_file_data[ '2' ] );
                                        if ( isset( $wp_filetype[ 'ext' ] ) && (!empty( $wp_filetype[ 'ext' ] )) && isset( $wp_filetype[ 'type' ] ) && (!empty( $wp_filetype[ 'type' ] )) ) {
                                                $original_name = $new_file_data[ '2' ];
                                        }
                                }
                        }
                }

                return $original_name;
        }

        private function search_ext_from_data( $data = "" ) {

                if ( is_array( $data ) ) {
                        $data = implode( " ", $data );
                }
                $data = strtolower( trim( $data ) );

                if ( $data === "" ) {
                        return "";
                }

                if ( $this->type === "file" ) {
                        return $this->get_file_ext( $data );
                } elseif ( $this->type === "media" ) {

                        $fileExt    = "";
                        $mime_types = \wp_get_mime_types();

                        foreach ( $mime_types as $ext => $type ) {
                                if ( strpos( $data, $type ) !== false || strpos( $data, $ext ) !== false ) {
                                        $fileExt = $ext;
                                        break;
                                }
                        }
                        return $fileExt;
                }

                return $this->get_image_ext( $data );
        }

        private function get_file_ext( $data = "" ) {

                $ext = "";

                if ( strpos( $data, "text/xml" ) !== false || strpos( $data, "application/xml" ) !== false || $data === "xml" ) {
                        $ext = "xml";
                } elseif ( strpos( $data, "text/plain" ) !== false || $data === "txt" ) {
                        $ext = "txt";
                } elseif ( strpos( $data, "text/csv" ) !== false || $data === "csv" ) {
                        $ext = "csv";
                } elseif ( strpos( $data, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ) !== false || $data === "xlsx" ) {
                        $ext = "xlsx";
                } elseif ( strpos( $data, 'application/vnd.ms-excel' ) !== false || $data === "xls" ) {
                        $ext = "xls";
                } elseif ( strpos( $data, 'json' ) !== false ) {
                        $ext = "json";
                } elseif ( strpos( $data, 'zip' ) !== false ) {
                        $ext = "zip";
                } elseif ( strpos( $data, 'application/vnd.oasis.opendocument.spreadsheet' ) !== false || $data === "ods" ) {
                        $ext = "ods";
                }

                return $ext;
        }

        private function get_image_ext( $data = "" ) {

                $ext = "";

                if ( strpos( $data, "jpeg" ) !== false ) {
                        $ext = "jpeg";
                } elseif ( strpos( $data, "jpe" ) !== false ) {
                        $ext = "jpe";
                } elseif ( strpos( $data, "jpg" ) !== false ) {
                        $ext = "jpg";
                } elseif ( strpos( $data, "gif" ) !== false ) {
                        $ext = "gif";
                } elseif ( strpos( $data, "png" ) !== false ) {
                        $ext = "png";
                } elseif ( strpos( $data, 'bmp' ) !== false ) {
                        $ext = "bmp";
                } elseif ( strpos( $data, 'tiff' ) !== false ) {
                        $ext = "tiff";
                } elseif ( strpos( $data, 'tif' ) !== false ) {
                        $ext = "tif";
                } elseif ( strpos( $data, 'icon' ) !== false ) {
                        $ext = "ico";
                } elseif ( strpos( $data, 'svg' ) !== false ) {
                        $ext = "svg";
                } elseif ( strpos( $data, 'webp' ) !== false ) {
                        $ext = "webp";
                } elseif ( strpos( $data, 'heic' ) !== false ) {
                        $ext = "heic";
                }

                return $ext;
        }

        private function is_valid_file( $filename = "" ) {

                if ( $this->type === "file" && !preg_match( '%\W(xml|zip|csv|xls|xlsx|xml|ods|txt|json)$%i', trim( $filename ) ) ) {

                        return false;
                } elseif ( $this->type === "image" && !preg_match( '%\W(jpg|jpeg|jpe|gif|png|bmp|tif|tiff|ico|heic|webp|svg)$%i', trim( $filename ) ) ) {

                        return false;
                } elseif ( $this->type === "media" ) {

                        $media = \wp_check_filetype( trim( $filename ) );

                        if ( is_array( $media ) && isset( $media[ 'type' ] ) && $media[ 'type' ] === false ) {
                                return false;
                        }
                }

                return true;
        }

        public function __destruct() {
                foreach ( $this as $key => $value ) {
                        unset( $this->$key );
                }
        }

}

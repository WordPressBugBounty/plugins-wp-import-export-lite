<?php
/**
 * Backward compatibility polyfill for WordPress 4.6.
 *
 * Provides fallback implementations for `WP_Term_Query` and safe ZIP archive extraction.
 * Protects against Zip Slip path traversal vulnerabilities (CWE-22) and enforces PHP 5.6 to 8.4+ compatibility.
 *
 * @package    WP_Import_Export_Lite
 * @subpackage WP_Import_Export_Lite/support
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound, Squiz.PHP.DiscouragedFunctions.Discouraged -- WordPress core polyfills.

if ( ! class_exists( 'WP_Term_Query' ) ) {

	/**
	 * Class WP_Term_Query Polyfill.
	 *
	 * Polyfill for `WP_Term_Query` introduced in WordPress 4.6.0.
	 *
	 * @since 1.0.0
	 */
	class WP_Term_Query {

		/**
		 * Term query arguments.
		 *
		 * @var array
		 */
		private $query;

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 * @param string|array $query Term query arguments.
		 */
		public function __construct( $query = '' ) {
			$this->query = wp_parse_args( $query );
		}

		/**
		 * Retrieves terms matching query arguments.
		 *
		 * @since 1.0.0
		 * @return array|int|\WP_Error Array of terms, count, or WP_Error.
		 */
		public function get_terms() {
			global $wp_version;

			if ( ! empty( $wp_version ) && version_compare( strval( $wp_version ), '4.5.0', '<' ) ) {
				$taxonomy = isset( $this->query['taxonomy'] ) ? $this->query['taxonomy'] : 'category';
				// phpcs:ignore WordPress.WP.DeprecatedParameters.Get_termsParam2Found -- Fallback for WordPress < 4.5.0.
				return get_terms( $taxonomy, $this->query );
			}

			return get_terms( $this->query );
		}

		/**
		 * Destructor.
		 *
		 * @since 1.0.0
		 */
		public function __destruct() {
			$this->query = null;
		}
	}
}

if ( ! function_exists( 'wpie_unzip_file' ) ) {

	/**
	 * Extracts a ZIP archive to a specified directory securely.
	 *
	 * Delegates to WordPress core `unzip_file()` if available, or uses a hardened
	 * ZipArchive implementation protected against Zip Slip vulnerabilities.
	 *
	 * @since 1.0.0
	 * @param string $file Absolute path to ZIP file.
	 * @param string $to   Absolute path to destination directory.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	function wpie_unzip_file( $file, $to ) {
		// Prefer core unzip_file if available.
		if ( ! function_exists( 'unzip_file' ) && file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( function_exists( 'unzip_file' ) ) {
			return unzip_file( $file, $to );
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem || ! is_object( $wp_filesystem ) ) {
			return new \WP_Error( 'fs_unavailable', __( 'Could not access filesystem.', 'wp-import-export-lite' ) );
		}

		@ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Risk

		$needed_dirs = array();
		$to          = trailingslashit( wp_normalize_path( $to ) );

		if ( ! $wp_filesystem->is_dir( $to ) ) {
			$path = preg_split( '![/\\\]!', untrailingslashit( $to ) );
			$path = is_array( $path ) ? $path : array();
			for ( $i = count( $path ); $i >= 0; $i-- ) {
				if ( empty( $path[ $i ] ) ) {
					continue;
				}

				$dir = implode( '/', array_slice( $path, 0, $i + 1 ) );
				if ( preg_match( '!^[a-z]:$!i', $dir ) ) {
					continue;
				}

				if ( ! $wp_filesystem->is_dir( $dir ) ) {
					$needed_dirs[] = $dir;
				} else {
					break;
				}
			}
		}

		if ( class_exists( 'ZipArchive', false ) && apply_filters( 'unzip_file_use_ziparchive', true ) ) {
			$result = wpie_unzip_file_ziparchive( $file, $to, $needed_dirs );
			if ( true === $result ) {
				return $result;
			} elseif ( is_wp_error( $result ) ) {
				if ( 'incompatible_archive' !== $result->get_error_code() ) {
					return $result;
				}
			}
		}

		if ( function_exists( '_unzip_file_pclzip' ) ) {
			return _unzip_file_pclzip( $file, $to, $needed_dirs );
		}

		return new \WP_Error( 'zip_unavailable', __( 'No suitable ZIP extraction library available.', 'wp-import-export-lite' ) );
	}
}

if ( ! function_exists( 'wpie_unzip_file_ziparchive' ) ) {

	/**
	 * Extracts archive using ZipArchive with strict path validation against Zip Slip.
	 *
	 * @since 1.0.0
	 * @param string $file        ZIP file path.
	 * @param string $to          Destination directory path.
	 * @param array  $needed_dirs Required parent directories.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	function wpie_unzip_file_ziparchive( $file, $to, $needed_dirs = array() ) {
		global $wp_filesystem;

		$z = new \ZipArchive();

		$zopen = $z->open( $file, \ZipArchive::CHECKCONS );
		if ( true !== $zopen ) {
			return new \WP_Error( 'incompatible_archive', __( 'Incompatible Archive.', 'wp-import-export-lite' ), array( 'ziparchive_error' => $zopen ) );
		}

		$to                = trailingslashit( wp_normalize_path( $to ) );
		$uncompressed_size = 0;

		for ( $i = 0; $i < $z->numFiles; $i++ ) {
			$info = $z->statIndex( $i );
			if ( ! $info ) {
				$z->close();
				return new \WP_Error( 'stat_failed_ziparchive', __( 'Could not retrieve file from archive.', 'wp-import-export-lite' ) );
			}

			// Normalize entry filename and prevent path traversal (Zip Slip).
			$entry_name = wp_normalize_path( $info['name'] );

			if ( false !== strpos( $entry_name, '../' ) || false !== strpos( $entry_name, '..\\' ) || '..' === $entry_name ) {
				$z->close();
				return new \WP_Error( 'zip_slip_detected', __( 'Directory traversal detected in archive entry.', 'wp-import-export-lite' ) );
			}

			$target_path = wp_normalize_path( $to . $entry_name );
			if ( 0 !== strpos( $target_path, $to ) ) {
				$z->close();
				return new \WP_Error( 'zip_slip_detected', __( 'Path outside destination directory detected.', 'wp-import-export-lite' ) );
			}

			if ( '__MACOSX/' === substr( $entry_name, 0, 9 ) ) {
				continue;
			}

			$uncompressed_size += $info['size'];

			if ( '/' === substr( $entry_name, -1 ) ) {
				$needed_dirs[] = $to . untrailingslashit( $entry_name );
			} elseif ( '.' !== ( $dirname = dirname( $entry_name ) ) ) {
				$needed_dirs[] = $to . untrailingslashit( $dirname );
			}
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			$available_space = @disk_free_space( WP_CONTENT_DIR );
			if ( $available_space && ( $uncompressed_size * 2.1 ) > $available_space ) {
				$z->close();
				return new \WP_Error( 'disk_full_unzip_file', __( 'Could not copy files. You may have run out of disk space.', 'wp-import-export-lite' ), compact( 'uncompressed_size', 'available_space' ) );
			}
		}

		$needed_dirs = array_unique( $needed_dirs );
		foreach ( $needed_dirs as $dir ) {
			$dir = wp_normalize_path( $dir );
			if ( untrailingslashit( $to ) === $dir || 0 !== strpos( $dir, $to ) ) {
				continue;
			}

			$parent_folder = dirname( $dir );
			while ( ! empty( $parent_folder ) && untrailingslashit( $to ) !== $parent_folder && ! in_array( $parent_folder, $needed_dirs, true ) ) {
				$needed_dirs[] = $parent_folder;
				$parent_folder = dirname( $parent_folder );
			}
		}
		asort( $needed_dirs );

		foreach ( $needed_dirs as $_dir ) {
			if ( ! $wp_filesystem->mkdir( $_dir, FS_CHMOD_DIR ) && ! $wp_filesystem->is_dir( $_dir ) ) {
				$z->close();
				return new \WP_Error( 'mkdir_failed_ziparchive', __( 'Could not create directory.', 'wp-import-export-lite' ), substr( $_dir, strlen( $to ) ) );
			}
		}
		unset( $needed_dirs );

		for ( $i = 0; $i < $z->numFiles; $i++ ) {
			$info = $z->statIndex( $i );
			if ( ! $info ) {
				$z->close();
				return new \WP_Error( 'stat_failed_ziparchive', __( 'Could not retrieve file from archive.', 'wp-import-export-lite' ) );
			}

			$entry_name = wp_normalize_path( $info['name'] );

			if ( '/' === substr( $entry_name, -1 ) || '__MACOSX/' === substr( $entry_name, 0, 9 ) ) {
				continue;
			}

			// Path traversal check before extracting contents.
			$destination_file = wp_normalize_path( $to . $entry_name );
			if ( 0 !== strpos( $destination_file, $to ) ) {
				$z->close();
				return new \WP_Error( 'zip_slip_detected', __( 'Illegal file path in archive.', 'wp-import-export-lite' ) );
			}

			$contents = $z->getFromIndex( $i );
			if ( false === $contents ) {
				$z->close();
				return new \WP_Error( 'extract_failed_ziparchive', __( 'Could not extract file from archive.', 'wp-import-export-lite' ), $entry_name );
			}

			if ( ! $wp_filesystem->put_contents( $destination_file, $contents, FS_CHMOD_FILE ) ) {
				$z->close();
				return new \WP_Error( 'copy_failed_ziparchive', __( 'Could not copy file.', 'wp-import-export-lite' ), $entry_name );
			}
		}

		$z->close();

		return true;
	}
}
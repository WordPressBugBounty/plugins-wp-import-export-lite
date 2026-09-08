<?php
/**
 * WPIE Yoast SEO Export Extension Bootstrap
 *
 * @package    WPIE
 * @subpackage WPIE/Export/Extensions/Yoast_SEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPIE_Yoast_SEO_Export_Extension
 *
 * Checks Yoast SEO availability and binds field filter hooks.
 */
class WPIE_Yoast_SEO_Export_Extension {

	/**
	 * Constructor.
	 */
	public function __construct() {

		if ( $this->is_active_yoast_seo() ) {

			add_filter( 'wpie_prepare_post_fields', array( $this, 'prepare_yoast_addon' ), 10, 2 );

			add_filter( 'wpie_prepare_taxonomy_fields', array( $this, 'prepare_yoast_addon' ), 10, 2 );

			add_filter( 'wpie_prepare_export_addons', array( $this, 'prepare_yoast_addon' ), 10, 2 );
		}
	}

	/**
	 * Register Yoast SEO exporter class in addons list.
	 *
	 * @param array        $addons      Active addon classes.
	 * @param array|string $export_type Export post type or taxonomy.
	 * @return array
	 */
	public function prepare_yoast_addon( $addons = array(), $export_type = 'post' ) {

		$export_type = is_array( $export_type ) && isset( $export_type[0] ) ? $export_type[0] : $export_type;

		if ( in_array( $export_type, array( 'shop_coupon', 'comments', 'product_reviews', 'shop_order', 'users', 'shop_customer', 'product_attributes' ), true ) ) {
			return $addons;
		}

		if ( 'taxonomies' === $export_type ) {

			$file_name = WPIE_EXPORT_CLASSES_DIR . '/extensions/yoast-seo/class-wpie-yoast-seo_taxonomy.php';

			$class = '\wpie\export\yoast_seo\WPIE_Yoast_SEO_Taxonomy_Export';
		} else {
			$file_name = WPIE_EXPORT_CLASSES_DIR . '/extensions/yoast-seo/class-wpie-yoast-seo.php';

			$class = '\wpie\export\yoast_seo\WPIE_Yoast_SEO_Export';
		}

		if ( file_exists( $file_name ) ) {
			require_once $file_name;
		}

		if ( '' !== $class && ! in_array( $class, $addons, true ) ) {
			$addons[] = $class;
		}

		unset( $class, $file_name );

		return $addons;
	}

	/**
	 * Check if Yoast SEO plugin is currently active.
	 *
	 * @return bool
	 */
	private function is_active_yoast_seo() {

		if ( defined( 'WPSEO_VERSION' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( function_exists( 'is_plugin_active' ) && ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) ) {
			return true;
		}

		return false;
	}
}

new WPIE_Yoast_SEO_Export_Extension();

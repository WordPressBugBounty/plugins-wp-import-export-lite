<?php
/**
 * Import Compatibility Manager
 *
 * @package   wpie\import\Compatibility
 * @author    WP Import Export
 * @copyright 2026 WP Import Export
 * @license   GPL-2.0+
 */

namespace wpie\import\Compatibility;

defined( 'ABSPATH' ) || exit;

/**
 * Class Manager
 *
 * Provides compatibility hooks for external themes and plugins (e.g. Houzez theme property images).
 *
 * @since 1.0.0
 */
class Manager {

	/**
	 * Template object instance.
	 *
	 * @var object
	 */
	private $template;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param object $template Template data object.
	 */
	public function __construct( $template ) {
		$this->template = $template;

		$this->add_compatibility();
	}

	/**
	 * Register compatibility hooks based on current import operation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_compatibility() {

		$import_type = ( isset( $this->template->opration_type ) && trim( $this->template->opration_type ) !== "" ) ? $this->template->opration_type : "post";

		if ( defined( 'HOUZEZ_THEME_VERSION' ) && $import_type === 'property' ) {
			add_action( 'wpie_after_completed_item_import', array( $this, 'add_houzez_theme_images' ), 10, 4 );
			add_action( 'wpie_after_post_import', array( $this, 'add_gallery_images' ), 10, 4 );
		}
	}

	/**
	 * Copy Houzez property images to standard WooCommerce product gallery meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $item_id            Item ID.
	 * @param array $wpie_import_record Import record row.
	 * @param array $wpie_final_data    Final sanitized data.
	 * @param array $wpie_import_option Import options.
	 * @return void
	 */
	public function add_gallery_images( $item_id = 0, $wpie_import_record = array(), $wpie_final_data = array(), $wpie_import_option = array() ) {

		if ( empty( $item_id ) ) {
			return;
		}

		$gallery = get_post_meta( $item_id, 'fave_property_images' );

		if ( is_array( $gallery ) && ! empty( $gallery ) ) {
			$gallery = implode( ',', $gallery );
		} else {
			$gallery = "";
		}

		update_post_meta( $item_id, '_product_image_gallery', $gallery );

		unset( $gallery );
	}

	/**
	 * Sync product gallery meta back to Houzez fave_property_images meta entries.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $item_id            Item ID.
	 * @param array $wpie_import_record Import record row.
	 * @param array $wpie_final_data    Final sanitized data.
	 * @param array $wpie_import_option Import options.
	 * @return void
	 */
	public function add_houzez_theme_images( $item_id = 0, $wpie_import_record = array(), $wpie_final_data = array(), $wpie_import_option = array() ) {

		if ( empty( $item_id ) ) {
			return;
		}

		delete_post_meta( $item_id, 'fave_property_images' );

		$gallery = get_post_meta( $item_id, '_product_image_gallery', true );

		if ( ! empty( $gallery ) ) {

			$gallery_ids = explode( ',', (string) $gallery );

			foreach ( $gallery_ids as $image ) {
				$img_id = absint( $image );
				if ( $img_id > 0 ) {
					add_post_meta( $item_id, 'fave_property_images', $img_id );
				}
			}
		}

		unset( $gallery );
	}

}

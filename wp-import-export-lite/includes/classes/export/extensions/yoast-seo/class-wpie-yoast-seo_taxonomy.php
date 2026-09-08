<?php
/**
 * WPIE Yoast SEO Taxonomy Export Extension Class
 *
 * Adds Yoast SEO meta fields to taxonomy exports and retrieves term SEO meta.
 *
 * @package    WPIE
 * @subpackage WPIE/Export/Extensions/Yoast_SEO
 */

namespace wpie\export\yoast_seo;

use wpie\export\base\WPIE_Export_Base;

defined( 'ABSPATH' ) || exit;

if ( file_exists( WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-base.php' ) ) {
	require_once WPIE_EXPORT_CLASSES_DIR . '/class-wpie-export-base.php';
}

/**
 * Class WPIE_Yoast_SEO_Taxonomy_Export
 *
 * Appends Yoast SEO fields to taxonomy exports.
 */
class WPIE_Yoast_SEO_Taxonomy_Export extends WPIE_Export_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Pre-process and inject Yoast SEO taxonomy fields.
	 *
	 * @param array  $export_fields        Export fields array by reference.
	 * @param array  $export_type          Current export post type(s).
	 * @param string $export_taxonomy_type Taxonomy type if applicable.
	 * @return void
	 */
	public function pre_process_fields( &$export_fields = array(), $export_type = array(), $export_taxonomy_type = '' ) {

		$fields = array(
			'title'      => __( 'Yoast SEO', 'wp-import-export-lite' ),
			'isFiltered' => false,
			'data'       => array(
				array(
					'name'    => 'Focus Keywords',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_focuskw',
				),
				array(
					'name'    => 'SEO Title',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_title',
				),
				array(
					'name'    => 'Meta Description',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_desc',
				),
				array(
					'name'    => 'Keyphrase Synonyms',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_keywordsynonyms',
				),
				array(
					'name'    => 'Related keyphrase',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_focuskeywords',
				),
				array(
					'name'    => 'Facebook Title',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_opengraph-title',
				),
				array(
					'name'    => 'Facebook Description',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_opengraph-description',
				),
				array(
					'name'    => 'Facebook Image',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_opengraph-image',
				),
				array(
					'name'    => 'Twitter Title',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_twitter-title',
				),
				array(
					'name'    => 'Twitter Description',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_twitter-description',
				),
				array(
					'name'    => 'Twitter Image',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_twitter-image',
				),
				array(
					'name'    => 'Meta Robots Index',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_noindex',
				),
				array(
					'name'    => 'is cornerstone content',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_is_cornerstone',
				),
				array(
					'name'    => 'Breadcrumbs Title',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_bctitle',
				),
				array(
					'name'    => 'Canonical URL',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_canonical',
				),
				array(
					'name'    => 'SEO Score',
					'type'    => 'yoast_seo',
					'metaKey' => 'wpseo_content_score',
				),
			),
		);

		$export_fields['yoast_seo'] = $fields;
	}

	/**
	 * Process row data for Yoast SEO taxonomy fields.
	 *
	 * @param array       $export_data      Export row data by reference.
	 * @param string      $field_type       Current field type.
	 * @param string      $field_name       Output column name.
	 * @param array       $field_option     Field options definition.
	 * @param object|null $item             Current term object.
	 * @param string      $site_date_format Site date format.
	 * @return void
	 */
	public function process_addon_data( &$export_data = array(), $field_type = '', $field_name = '', $field_option = array(), $item = null, $site_date_format = '' ) {

		if ( 'yoast_seo' === $field_type ) {

			$is_php   = isset( $field_option['isPhp'] ) && 1 === (int) wpie_sanitize_field( $field_option['isPhp'] );
			$php_func = isset( $field_option['phpFun'] ) ? wpie_sanitize_field( $field_option['phpFun'] ) : '';

			$metaKey  = isset( $field_option['metaKey'] ) ? wpie_sanitize_field( $field_option['metaKey'] ) : '';
			$taxonomy = isset( $item->taxonomy ) ? (string) $item->taxonomy : 'category';
			$termMeta = substr( $metaKey, 6 );

			$data = '';

			if ( class_exists( '\WPSEO_Taxonomy_Meta' ) && method_exists( '\WPSEO_Taxonomy_Meta', 'get_term_meta' ) ) {
				$data = \WPSEO_Taxonomy_Meta::get_term_meta( $item, $taxonomy, $termMeta );
			}

			$export_data[ $field_name ] = apply_filters( 'wpie_export_yoast_field', $this->apply_user_function( ( ( empty( $data ) || false === $data ) ? '' : $data ), $is_php, $php_func ), $item );

			unset( $is_php, $php_func );
		}
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

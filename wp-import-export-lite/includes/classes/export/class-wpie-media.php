<?php
/**
 * WPIE Media Export Helper Class
 *
 * Extracts and prepares featured images, galleries, and media attachments for export rows.
 *
 * @package    WPIE
 * @subpackage WPIE/Export
 */

namespace wpie\export\media;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Class WPIE_Media
 *
 * Retrieves and formats attached images and media files.
 */
class WPIE_Media {

	/**
	 * Retrieve attachment posts for a given parent ID.
	 *
	 * @param int $media_id Parent object ID.
	 * @return array
	 */
	public function get_media( $media_id = 0 ) {

		if ( empty( $media_id ) ) {
			return array();
		}

		$wpie_attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'post_parent'    => (int) $media_id,
			)
		);

		return is_array( $wpie_attachments ) ? $wpie_attachments : array();
	}

	/**
	 * Retrieve and format images associated with an entity.
	 *
	 * @param int    $parent     Entity ID.
	 * @param array  $attch      Direct attachment objects.
	 * @param string $field_type Requested image attribute (url, filename, path, id, etc.).
	 * @param string $type       Entity type (post, taxonomy, user).
	 * @return array
	 */
	public function get_images( $parent = 0, $attch = array(), $field_type = '', $type = 'post' ) {

		$image_data = array();

		$featured_image_id = $this->get_meta( $parent, '_thumbnail_id', true, $type );

		if ( empty( $featured_image_id ) ) {
			$featured_image_id = $this->get_meta( $parent, 'thumbnail_id', true, $type );
		}

		if ( ! empty( $featured_image_id ) ) {
			$post_img = get_post( (int) $featured_image_id );
			if ( $post_img instanceof WP_Post ) {
				$image_data[ (int) $featured_image_id ] = $post_img;
			}
			unset( $post_img );
		}

		unset( $featured_image_id );

		$image_gallery = $this->get_meta( $parent, '_product_image_gallery', true, $type );

		if ( ! empty( $image_gallery ) ) {

			$image_gallery_data = explode( ',', (string) $image_gallery );

			if ( ! empty( $image_gallery_data ) && is_array( $image_gallery_data ) ) {

				foreach ( $image_gallery_data as $gallery_id ) {

					$gallery_id = absint( trim( (string) $gallery_id ) );

					if ( $gallery_id > 0 && ! isset( $image_data[ $gallery_id ] ) ) {

						$wpie_image = get_post( $gallery_id );

						if ( $wpie_image instanceof WP_Post ) {
							$image_data[ $gallery_id ] = $wpie_image;
						}
						unset( $wpie_image );
					}
				}
			}
			unset( $image_gallery_data );
		}
		unset( $image_gallery );

		if ( ! empty( $attch ) && is_array( $attch ) ) {

			foreach ( $attch as $wpie_image ) {

				if ( $wpie_image instanceof WP_Post && ! isset( $image_data[ $wpie_image->ID ] ) && wp_attachment_is_image( $wpie_image->ID ) ) {
					$image_data[ $wpie_image->ID ] = $wpie_image;
				}
			}
		}
		unset( $attch );

		$images = array();

		if ( ! empty( $image_data ) ) {

			$is_empty = true;

			foreach ( $image_data as $wpie_attach ) {

				$_value = $this->get_field_value( str_replace( 'image_', '', (string) $field_type ), $wpie_attach, $type );

				$images[] = $_value;

				if ( ! empty( $_value ) ) {
					$is_empty = false;
				}
			}

			if ( $is_empty ) {
				$images = array();
			}

			unset( $is_empty );
		}

		return $images;
	}

	/**
	 * Retrieve and format non-image attachments.
	 *
	 * @param array|null $attachments Array of attachment post objects.
	 * @param string     $field_type  Requested field (url, filename, path, id, etc.).
	 * @param string     $type        Entity type.
	 * @return array
	 */
	public function get_attch( $attachments = null, $field_type = '', $type = 'post' ) {

		if ( empty( $attachments ) || ! is_array( $attachments ) ) {
			return array();
		}

		$media_attch = array();

		foreach ( $attachments as $wpie_attch ) {
			if ( $wpie_attch instanceof WP_Post && ! wp_attachment_is_image( $wpie_attch->ID ) ) {
				$media_attch[] = $wpie_attch;
			}
		}

		$attach_data = array();

		if ( ! empty( $media_attch ) ) {

			$is_empty = true;

			foreach ( $media_attch as $attach ) {

				$_value = $this->get_field_value( str_replace( 'attachment_', '', (string) $field_type ), $attach, $type );

				$attach_data[] = $_value;

				if ( ! empty( $_value ) ) {
					$is_empty = false;
				}
			}

			if ( $is_empty ) {
				$attach_data = array();
			}

			unset( $is_empty );
		}

		unset( $media_attch );

		return $attach_data;
	}

	/**
	 * Extract specific field value from an attachment post.
	 *
	 * @param string         $field_type Field type (url, filename, path, id, title, caption, description, alt).
	 * @param WP_Post|false  $attachment Attachment post.
	 * @param string         $type       Parent entity type.
	 * @return mixed
	 */
	private function get_field_value( $field_type = 'url', $attachment = false, $type = 'post' ) {

		if ( ! $attachment instanceof WP_Post ) {
			return false;
		}

		switch ( $field_type ) {
			case 'media':
			case 'attachments':
			case 'url':
				return wp_get_attachment_url( $attachment->ID );
			case 'filename':
				return basename( (string) wp_get_attachment_url( $attachment->ID ) );
			case 'path':
				return get_attached_file( $attachment->ID );
			case 'id':
				return $attachment->ID;
			case 'title':
				return $attachment->post_title;
			case 'caption':
				return $attachment->post_excerpt;
			case 'description':
				return $attachment->post_content;
			case 'alt':
				// Attachments are posts; alt text is always in postmeta.
				return $this->get_meta( $attachment->ID, '_wp_attachment_image_alt', true, 'post' );
			default:
				return false;
		}
	}

	/**
	 * Helper to get metadata based on entity type.
	 *
	 * @param int    $data_id  Entity ID.
	 * @param string $meta_key Meta key.
	 * @param bool   $single   Whether to return single value.
	 * @param string $type     Entity type (taxonomy, user, post).
	 * @return mixed
	 */
	private function get_meta( $data_id = 0, $meta_key = '', $single = true, $type = 'post' ) {

		if ( 'taxonomy' === $type || 'texonomy' === $type ) {
			return get_term_meta( (int) $data_id, $meta_key, $single );
		} elseif ( 'user' === $type ) {
			return get_user_meta( (int) $data_id, $meta_key, $single );
		} else {
			return get_post_meta( (int) $data_id, $meta_key, $single );
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

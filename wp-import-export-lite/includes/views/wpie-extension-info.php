<?php
/**
 * Extension info and settings view.
 *
 * Displays configuration and settings views for active extensions.
 *
 * @package    WP_Import_Export_Lite
 * @subpackage WP_Import_Export_Lite/includes/views
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template scope variables.

use WpieApp\Core\Helpers\Param;

if ( ! class_exists( '\wpie\addons\WPIE_Extension' ) && file_exists( WPIE_CLASSES_DIR . '/class-wpie-extensions.php' ) ) {
	require_once WPIE_CLASSES_DIR . '/class-wpie-extensions.php';
}

$wpie_ext_data = Param::getSanitized( 'wpie_ext', 'key', '' );
$ext_data      = array();

if ( class_exists( '\wpie\addons\WPIE_Extension' ) ) {
	$wpie_ext        = new \wpie\addons\WPIE_Extension();
	$wpie_import_ext = method_exists( $wpie_ext, 'wpie_get_import_extension' ) ? $wpie_ext->wpie_get_import_extension() : array();
	$wpie_export_ext = method_exists( $wpie_ext, 'wpie_get_export_extension' ) ? $wpie_ext->wpie_get_export_extension() : array();

	if ( is_array( $wpie_import_ext ) && isset( $wpie_import_ext[ $wpie_ext_data ] ) ) {
		$ext_data = $wpie_import_ext[ $wpie_ext_data ];
	} elseif ( is_array( $wpie_export_ext ) && isset( $wpie_export_ext[ $wpie_ext_data ] ) ) {
		$ext_data = $wpie_export_ext[ $wpie_ext_data ];
	}
	unset( $wpie_ext, $wpie_import_ext, $wpie_export_ext );
}
?>
<div class="wpie_main_container">
	<div class="wpie_content_header">
		<div class="wpie_content_header_inner_wrapper">
			<div class="wpie_content_header_title"><?php echo isset( $ext_data['name'] ) ? esc_html( $ext_data['name'] ) : ''; ?></div>
		</div>
	</div>
	<div class="wpie_content_wrapper">
		<?php
		$settings = isset( $ext_data['settings'] ) && is_string( $ext_data['settings'] ) ? $ext_data['settings'] : '';

		if ( ! empty( $settings ) && file_exists( $settings ) ) {
			?>
			<div class="wpie_section_wrapper">
				<div class="wpie_content_data_header wpie_section_wrapper_selected">
					<div class="wpie_content_title"><?php esc_html_e( 'Settings', 'wp-import-export-lite' ); ?></div>
					<div class="wpie_layout_header_icon_wrapper"><i class="fas fa-chevron-up wpie_layout_header_icon wpie_layout_header_icon_collapsed" aria-hidden="true"></i><i class="fas fa-chevron-down wpie_layout_header_icon wpie_layout_header_icon_expand" aria-hidden="true"></i></div>
				</div>
				<div class="wpie_section_content wpie_show">
					<form class="wpie_ext_settings_frm">
						<input type="hidden" class="wpieSecurity" name="wpieSecurity" value="<?php echo esc_attr( wp_create_nonce( 'wpie-security' ) ); ?>"/>
						<input type="hidden" name="wpie_ext" value="<?php echo esc_attr( $wpie_ext_data ); ?>"/>
						<div class="wpie_content_data_wrapper">
							<?php
							include $settings;
							?>
							<div class="wpie_ext_save_wrapper">
								<div class="wpie_btn wpie_btn_primary wpie_ext_save_data">
									<i class="fas fa-check wpie_general_btn_icon " aria-hidden="true"></i><?php esc_html_e( 'Save', 'wp-import-export-lite' ); ?>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
			<?php
		} else {
			?>
			<div class="wpie_section_wrapper">
				<div class="wpie_section_content wpie_show">
					<div class="wpie_empty_records">
						<?php esc_html_e( 'No configuration settings found for this extension.', 'wp-import-export-lite' ); ?>
					</div>
				</div>
			</div>
			<?php
		}
		unset( $settings );
		?>
	</div>
</div>
<div class="wpie_doc_wrapper">
	<div class="wpie_doc_container">
		<a class="wpie_doc_url" href="<?php echo esc_url( WPIE_SUPPORT_URL ); ?>" target="_blank"><?php esc_html_e( 'Support', 'wp-import-export-lite' ); ?></a>
		<div class="wpie_doc_url_delim">|</div>
		<a class="wpie_doc_url" href="<?php echo esc_url( WPIE_DOC_URL ); ?>" target="_blank"><?php esc_html_e( 'Documentation', 'wp-import-export-lite' ); ?></a>
	</div>
</div>
<div class="wpie_loader wpie_hidden">
	<div></div>
	<div></div>
</div>
<div class="modal fade wpie_error_model" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered " role="document">
		<div class="modal-content wpie_error">
			<div class="modal-header">
				<h5 class="modal-title"><?php esc_html_e( 'ERROR', 'wp-import-export-lite' ); ?></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="wpie_error_content"></div>
			</div>
			<div class="modal-footer">
				<div class="wpie_btn wpie_btn_red wpie_btn_radius " data-bs-dismiss="modal">
					<i class="fas fa-check wpie_general_btn_icon " aria-hidden="true"></i><?php esc_html_e( 'Ok', 'wp-import-export-lite' ); ?>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
unset( $wpie_ext_data, $ext_data );
<?php
/**
 * Extensions management view.
 *
 * Displays export and import extensions list and enable/disable switches.
 *
 * @package    WP_Import_Export_Lite
 * @subpackage WP_Import_Export_Lite/includes/views
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template scope variables.

if ( ! class_exists( '\wpie\addons\WPIE_Extension' ) && file_exists( WPIE_CLASSES_DIR . '/class-wpie-extensions.php' ) ) {
	require_once WPIE_CLASSES_DIR . '/class-wpie-extensions.php';
}

if ( class_exists( '\wpie\addons\WPIE_Extension' ) ) {
	$wpie_ext        = new \wpie\addons\WPIE_Extension();
	$wpie_export_ext = method_exists( $wpie_ext, 'wpie_get_export_extension' ) ? $wpie_ext->wpie_get_export_extension() : array();
	$wpie_import_ext = method_exists( $wpie_ext, 'wpie_get_import_extension' ) ? $wpie_ext->wpie_get_import_extension() : array();
	$wpieExtData     = method_exists( $wpie_ext, 'wpie_get_activated_ext' ) ? $wpie_ext->wpie_get_activated_ext() : array();
	unset( $wpie_ext );
} else {
	$wpie_export_ext = array();
	$wpie_import_ext = array();
	$wpieExtData     = array();
}
?>
<div class="wpie_main_container">
	<div class="wpie_content_header">
		<div class="wpie_content_header_inner_wrapper">
			<div class="wpie_content_header_title"><?php esc_html_e( 'Extensions', 'wp-import-export-lite' ); ?></div>
			<a class="wpie_btn wpie_btn_primary ms-4" href="https://1.envato.market/1krom" target="_blank">
				<?php esc_html_e( 'Upgrade Pro', 'wp-import-export-lite' ); ?>
			</a>
			<div class="wpie_fixed_header_button">
				<div class="wpie_btn wpie_btn_primary wpie_ext_save">
					<i class="fas fa-check wpie_general_btn_icon " aria-hidden="true"></i><?php esc_html_e( 'Save', 'wp-import-export-lite' ); ?>
				</div>
			</div>
		</div>
	</div>
	<div class="wpie_content_wrapper">
		<form class="wpie_general_frm" method="post" action="#">
			<input type="hidden" class="wpieSecurity" name="wpieSecurity" value="<?php echo esc_attr( wp_create_nonce( 'wpie-security' ) ); ?>"/>
			<div class="wpie_section_wrapper">
				<div class="wpie_content_data_header wpie_section_wrapper_selected">
					<div class="wpie_content_title"><?php esc_html_e( 'Export Extensions', 'wp-import-export-lite' ); ?></div>
					<div class="wpie_layout_header_icon_wrapper"><i class="fas fa-chevron-up wpie_layout_header_icon wpie_layout_header_icon_collapsed" aria-hidden="true"></i><i class="fas fa-chevron-down wpie_layout_header_icon wpie_layout_header_icon_expand" aria-hidden="true"></i></div>
				</div>
				<div class="wpie_section_content" style="display: block;">
					<table class="wpie_ext_list_table">
						<tr>
							<?php
							$displayed_export = 0;
							if ( ! empty( $wpie_export_ext ) && is_array( $wpie_export_ext ) ) {
								foreach ( $wpie_export_ext as $key => $extData ) {
									if ( ! empty( $extData['is_default'] ) ) {
										continue;
									}
									$is_pro = ! empty( $extData['is_pro'] );

									if ( $displayed_export > 0 && $displayed_export % 3 === 0 ) {
										echo '</tr><tr>';
									}
									?>
									<td class="wpie_ext_container">
										<div class="wpie_ext_wrapper">
											<div class="wpie_ext_name_wrapper"><?php echo esc_html( isset( $extData['name'] ) ? $extData['name'] : '' ); ?></div>
											<div class="wpie_ext_desc_wrapper"><?php echo esc_html( isset( $extData['short_desc'] ) ? $extData['short_desc'] : '' ); ?></div>
											<div class="wpie_ext_btn_wrapper">
												<div class="wpie_switch">
													<input type="checkbox" name="wpie_ext[]" <?php if ( $is_pro ) { ?> disabled="disabled" <?php } ?> class="wpie_switch_checkbox" value="<?php echo esc_attr( $key ); ?>" id="wpie_switch_<?php echo esc_attr( $displayed_export ); ?>" <?php if ( is_array( $wpieExtData ) && in_array( $key, $wpieExtData, true ) && false === $is_pro ) { ?>checked="checked"<?php } ?>>
													<label class="wpie_switch_label<?php if ( $is_pro ) { ?> wpie_switch_label_disabled<?php } ?>" for="wpie_switch_<?php echo esc_attr( $displayed_export ); ?>">
														<span class="wpie_switch_inner">
															<span class="wpie_switch_active"><span class="wpie_switch_switch"><?php esc_html_e( 'ON', 'wp-import-export-lite' ); ?></span></span>
															<span class="wpie_switch_inactive"><span class="wpie_switch_switch"><?php esc_html_e( 'OFF', 'wp-import-export-lite' ); ?></span></span>
														</span>
													</label>
												</div>
												<?php if ( $is_pro ) { ?>
													<div class="wpie_ext_setting_btn">
														<div class="wpie_pro_ext_mark"><?php esc_html_e( 'Pro Addon', 'wp-import-export-lite' ); ?></div>
													</div>
												<?php } ?>
											</div>
										</div>
									</td>
									<?php
									$displayed_export++;
								}
							}
							if ( 0 === $displayed_export ) {
								?>
								<td class="wpie_ext_empty_msg"><?php esc_html_e( 'No Extension installed. Please install extension for use all features.', 'wp-import-export-lite' ); ?></td>
								<?php
							}
							?>
						</tr>
					</table>
				</div>
			</div>
			<div class="wpie_section_wrapper">
				<div class="wpie_content_data_header wpie_section_wrapper_selected">
					<div class="wpie_content_title"><?php esc_html_e( 'Import Extensions', 'wp-import-export-lite' ); ?></div>
					<div class="wpie_layout_header_icon_wrapper"><i class="fas fa-chevron-up wpie_layout_header_icon wpie_layout_header_icon_collapsed" aria-hidden="true"></i><i class="fas fa-chevron-down wpie_layout_header_icon wpie_layout_header_icon_expand" aria-hidden="true"></i></div>
				</div>
				<div class="wpie_section_content" style="display: block;">
					<table class="wpie_ext_list_table">
						<tr>
							<?php
							$displayed_import = 0;
							if ( ! empty( $wpie_import_ext ) && is_array( $wpie_import_ext ) ) {
								foreach ( $wpie_import_ext as $key => $extData ) {
									if ( ! empty( $extData['is_default'] ) ) {
										continue;
									}
									$is_pro = ! empty( $extData['is_pro'] );

									if ( $displayed_import > 0 && $displayed_import % 3 === 0 ) {
										echo '</tr><tr>';
									}
									?>
									<td class="wpie_ext_container">
										<div class="wpie_ext_wrapper">
											<div class="wpie_ext_name_wrapper"><?php echo esc_html( isset( $extData['name'] ) ? $extData['name'] : '' ); ?></div>
											<div class="wpie_ext_desc_wrapper"><?php echo esc_html( isset( $extData['short_desc'] ) ? $extData['short_desc'] : '' ); ?></div>
											<div class="wpie_ext_btn_wrapper">
												<div class="wpie_switch">
													<input type="checkbox" name="wpie_ext[]" <?php if ( $is_pro ) { ?> disabled="disabled" <?php } ?> class="wpie_switch_checkbox" value="<?php echo esc_attr( $key ); ?>" id="wpie_switch_import_<?php echo esc_attr( $displayed_import ); ?>" <?php if ( is_array( $wpieExtData ) && in_array( $key, $wpieExtData, true ) && false === $is_pro ) { ?>checked="checked"<?php } ?>>
													<label class="wpie_switch_label<?php if ( $is_pro ) { ?> wpie_switch_label_disabled<?php } ?>" for="wpie_switch_import_<?php echo esc_attr( $displayed_import ); ?>">
														<span class="wpie_switch_inner">
															<span class="wpie_switch_active"><span class="wpie_switch_switch"><?php esc_html_e( 'ON', 'wp-import-export-lite' ); ?></span></span>
															<span class="wpie_switch_inactive"><span class="wpie_switch_switch"><?php esc_html_e( 'OFF', 'wp-import-export-lite' ); ?></span></span>
														</span>
													</label>
												</div>
												<?php if ( $is_pro ) { ?>
													<div class="wpie_ext_setting_btn">
														<div class="wpie_pro_ext_mark"><?php esc_html_e( 'Pro Addon', 'wp-import-export-lite' ); ?></div>
													</div>
												<?php } ?>
											</div>
										</div>
									</td>
									<?php
									$displayed_import++;
								}
							}
							if ( 0 === $displayed_import ) {
								?>
								<td class="wpie_ext_empty_msg"><?php esc_html_e( 'No Extension installed. Please install extension for use all features.', 'wp-import-export-lite' ); ?></td>
								<?php
							}
							?>
						</tr>
					</table>
				</div>
			</div>
		</form>
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
unset( $wpie_export_ext, $wpie_import_ext, $wpieExtData, $displayed_export, $displayed_import );
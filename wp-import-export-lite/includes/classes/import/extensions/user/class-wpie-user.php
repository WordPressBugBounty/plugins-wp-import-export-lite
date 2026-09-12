<?php

namespace wpie\import\user;

use WP_User_Query;

defined( 'ABSPATH' ) || exit;
if ( file_exists( WPIE_IMPORT_CLASSES_DIR . '/class-wpie-import-engine.php' ) ) {

        require_once(WPIE_IMPORT_CLASSES_DIR . '/class-wpie-import-engine.php');
}

class WPIE_User_Import extends \wpie\import\engine\WPIE_Import_Engine {

        protected $import_type = "user";
        protected $login_user_id = false;

        protected function get_login_user_id() {

                $current_id = \get_current_user_id();
                if ( $current_id > 0 ) {
                        return $current_id;
                }
                if ( $this->login_user_id !== false && (int) $this->login_user_id > 0 ) {
                        return (int) $this->login_user_id;
                }
                // Background/cron: resolve the importer's ID from the stored username
                // so that guards like the self-update check (line ~419) work correctly.
                if ( ! empty( $this->import_username ) ) {
                        $user = \get_user_by( 'login', $this->import_username );
                        if ( $user && isset( $user->ID ) ) {
                                return (int) $user->ID;
                        }
                }
                return 0;
        }

        public function get_importer() {
                $user_id = $this->get_login_user_id();
                if ( $user_id > 0 ) {
                        $user = \get_user_by( 'id', $user_id );
                        if ( $user ) {
                                return $user;
                        }
                }
                if ( ! empty( $this->import_username ) ) {
                        $user = \get_user_by( 'login', $this->import_username );
                        if ( $user ) {
                                return $user;
                        }
                }
                return false;
        }

        public function can_manage_superior_users() {
                $importer = $this->get_importer();
                if ( ! $importer ) {
                        return false;
                }

                if ( \is_multisite() && \is_super_admin( $importer->ID ) ) {
                        return true;
                }

                if ( \user_can( $importer, 'wpie_update_superior_users' ) || \user_can( $importer, 'wpie_update_superior_user' ) ) {
                        return true;
                }

                if ( ! \is_multisite() && ( \user_can( $importer, 'administrator' ) || \user_can( $importer, 'manage_options' ) ) ) {
                        return true;
                }

                return false;
        }

        public function is_superior_user( $target_user ) {
                if ( ! $target_user ) {
                        return false;
                }

                $importer = $this->get_importer();
                if ( ! $importer ) {
                        return true;
                }

                if ( \is_multisite() ) {
                        // In multisite, if target user is super admin, only super admin can manage them.
                        if ( \is_super_admin( $target_user->ID ) ) {
                                if ( ! \is_super_admin( $importer->ID ) ) {
                                        return true;
                                }
                        }

                        // If importer is network super admin, they hold all capabilities network-wide.
                        if ( \is_super_admin( $importer->ID ) ) {
                                return false;
                        }

                        // For non-super-admin importers, resolve capabilities across ALL network blogs (including archived/spam/deleted).
                        $target_blogs   = function_exists( 'get_blogs_of_user' ) ? \get_blogs_of_user( $target_user->ID, true ) : array();
                        $importer_blogs = function_exists( 'get_blogs_of_user' ) ? \get_blogs_of_user( $importer->ID, true ) : array();

                        if ( ! empty( $target_blogs ) && is_array( $target_blogs ) ) {
                                // Index importer's blogs (all sites, including archived/spam/deleted) for fast lookup.
                                $importer_blog_ids = array();
                                if ( ! empty( $importer_blogs ) && is_array( $importer_blogs ) ) {
                                        foreach ( $importer_blogs as $ib_key => $ib_info ) {
                                                $ib_id = is_object( $ib_info ) && isset( $ib_info->userblog_id ) ? (int) $ib_info->userblog_id : (int) $ib_key;
                                                if ( $ib_id > 0 ) {
                                                        $importer_blog_ids[ $ib_id ] = true;
                                                }
                                        }
                                }

                                foreach ( $target_blogs as $blog_id => $blog_info ) {
                                        $b_id = is_object( $blog_info ) && isset( $blog_info->userblog_id ) ? (int) $blog_info->userblog_id : (int) $blog_id;
                                        if ( $b_id <= 0 ) {
                                                continue;
                                        }

                                        if ( function_exists( 'switch_to_blog' ) && function_exists( 'restore_current_blog' ) ) {
                                                \switch_to_blog( $b_id );
                                                try {
                                                        $target_on_blog   = new \WP_User( $target_user->ID, '', $b_id );
                                                        $importer_on_blog = new \WP_User( $importer->ID, '', $b_id );

                                                        // Defence-in-depth: if the importer has no membership on this blog
                                                        // (resolved with all=true), they hold no capabilities here.
                                                        $importer_on_this_blog = isset( $importer_blog_ids[ $b_id ] );

                                                        if ( \user_can( $target_on_blog, 'administrator' ) || \user_can( $target_on_blog, 'manage_options' ) ) {
                                                                if ( ! $importer_on_this_blog || ( ! \user_can( $importer_on_blog, 'administrator' ) && ! \user_can( $importer_on_blog, 'manage_options' ) ) ) {
                                                                        return true;
                                                                }
                                                        }

                                                        if ( ! empty( $target_on_blog->allcaps ) && is_array( $target_on_blog->allcaps ) ) {
                                                                $wp_roles = function_exists( 'wp_roles' ) ? \wp_roles() : null;
                                                                foreach ( $target_on_blog->allcaps as $cap => $grant ) {
                                                                        if ( ! $grant ) {
                                                                                continue;
                                                                        }
                                                                        if ( $wp_roles && $wp_roles->is_role( $cap ) ) {
                                                                                continue;
                                                                        }
                                                                        if ( ! $importer_on_this_blog || ( empty( $importer_on_blog->allcaps[ $cap ] ) && ! \user_can( $importer_on_blog, $cap ) ) ) {
                                                                                return true;
                                                                        }
                                                                }
                                                        }
                                                } finally {
                                                        \restore_current_blog();
                                                }
                                        }
                                }
                        }

                        // Multisite capability resolution is complete.
                        // Target holds no capabilities exceeding the importer's on any network blog.
                        return false;
                }

                // In single-site, an administrator holds full management authority.
                if ( \user_can( $importer, 'administrator' ) || \user_can( $importer, 'manage_options' ) ) {
                        return false;
                }

                if ( \user_can( $target_user, 'administrator' ) || \user_can( $target_user, 'manage_options' ) ) {
                        return true;
                }

                if ( ! empty( $target_user->allcaps ) && is_array( $target_user->allcaps ) ) {
                        $wp_roles = function_exists( 'wp_roles' ) ? \wp_roles() : null;
                        foreach ( $target_user->allcaps as $cap => $grant ) {
                                if ( ! $grant ) {
                                        continue;
                                }
                                if ( $wp_roles && $wp_roles->is_role( $cap ) ) {
                                        continue;
                                }
                                if ( empty( $importer->allcaps[ $cap ] ) && ! \user_can( $importer, $cap ) ) {
                                        return true;
                                }
                        }
                }

                return false;
        }

        public function is_superior_role( $role_slug ) {
                if ( empty( $role_slug ) || ! is_string( $role_slug ) ) {
                        return false;
                }

                $importer = $this->get_importer();
                if ( ! $importer ) {
                        return true;
                }

                if ( \is_multisite() && \is_super_admin( $importer->ID ) ) {
                        return false;
                }

                if ( ! \is_multisite() && ( \user_can( $importer, 'administrator' ) || \user_can( $importer, 'manage_options' ) ) ) {
                        return false;
                }

                $role_obj = \get_role( $role_slug );
                if ( ! $role_obj ) {
                        return true;
                }

                if ( $role_slug === 'administrator' && ! \user_can( $importer, 'administrator' ) && ! \user_can( $importer, 'manage_options' ) ) {
                        return true;
                }

                if ( ! empty( $role_obj->capabilities ) && is_array( $role_obj->capabilities ) ) {
                        foreach ( $role_obj->capabilities as $cap => $grant ) {
                                if ( $grant && empty( $importer->allcaps[ $cap ] ) && ! \user_can( $importer, $cap ) ) {
                                        return true;
                                }
                        }
                }

                return false;
        }

        protected function is_safe_user_meta_key( $meta_key = "" ) {
                if ( empty( $meta_key ) || ! is_string( $meta_key ) ) {
                        return false;
                }

                $clean_key = strtolower( trim( $meta_key ) );

                if ( strpos( $clean_key, "\0" ) !== false ) {
                        return false;
                }

                if ( $clean_key === 'capabilities' || preg_match( '/(^|_)capabilities$/i', $clean_key ) ) {
                        return false;
                }

                if ( $clean_key === 'user_level' || preg_match( '/(^|_)user_level$/i', $clean_key ) ) {
                        return false;
                }

                $blocked_keys = array(
                        'session_tokens',
                        'primary_blog',
                        'source_domain',
                        'default_password_nag',
                        'user_pass',
                        'user_activation_key'
                );

                $blocked_keys = apply_filters( 'wpie_blocked_user_meta_keys', $blocked_keys );

                if ( in_array( $clean_key, $blocked_keys, true ) ) {
                        return false;
                }

                return true;
        }

        protected function update_meta( $meta_key = "", $meta_val = "" ) {
                if ( ! $this->is_safe_user_meta_key( $meta_key ) ) {
                        return false;
                }
                return parent::update_meta( $meta_key, $meta_val );
        }

        protected function remove_meta( $meta_key = "" ) {
                if ( ! $this->is_safe_user_meta_key( $meta_key ) ) {
                        return false;
                }
                return parent::remove_meta( $meta_key );
        }

        protected function get_meta( $meta_key = "", $is_single = false ) {
                $metas = parent::get_meta( $meta_key, $is_single );
                if ( empty( $meta_key ) && is_array( $metas ) ) {
                        foreach ( array_keys( $metas ) as $k ) {
                                if ( ! $this->is_safe_user_meta_key( $k ) ) {
                                        unset( $metas[ $k ] );
                                }
                        }
                }
                return $metas;
        }

        public function process_import_data() {

                global $wpdb;

                if ( $this->is_update_field( "fname" ) ) {

                        $this->wpie_final_data[ 'first_name' ] = wpie_sanitize_field( $this->get_field_value( 'wpie_item_first_name' ) );
                }
                if ( $this->is_update_field( "lname" ) ) {

                        $this->wpie_final_data[ 'last_name' ] = wpie_sanitize_field( $this->get_field_value( 'wpie_item_last_name' ) );
                }
                $roles = [];
                $superior_role_attempted = false;
                $attempted_role_name = "";

                if ( $this->is_update_field( "role" ) ) {

                        $roleData = wpie_sanitize_field( $this->get_field_value( 'wpie_item_user_role' ) );

                        $role = "";

                        if ( !empty( $roleData ) ) {

                                $separator = "|";

                                if ( strpos( $roleData, "|" ) === false && strpos( $roleData, "," ) !== false ) {
                                        $separator = ",";
                                }

                                $roleData = trim( $roleData, $separator );

                                $userRoles = empty( $roleData ) ? [] : explode( $separator, $roleData );

                                $roles = $this->get_valid_roles( $userRoles );

                                if ( ! $this->can_manage_superior_users() ) {
                                        foreach ( $roles as $r_check ) {
                                                if ( $this->is_superior_role( $r_check ) ) {
                                                        $superior_role_attempted = true;
                                                        $attempted_role_name = $r_check;
                                                        break;
                                                }
                                        }
                                }

                                $role = isset( $roles[ 0 ] ) ? $roles[ 0 ] : "";

                                if ( count( $roles ) <= 1 ) {
                                        $roles = [];
                                } else {
                                        array_shift( $roles );
                                }
                        }

                        $this->wpie_final_data[ 'role' ] = apply_filters( 'wpie_import_user_role', $role );

                        if ( ! $this->can_manage_superior_users() && ! empty( $this->wpie_final_data[ 'role' ] ) && $this->is_superior_role( $this->wpie_final_data[ 'role' ] ) ) {
                                $superior_role_attempted = true;
                                $attempted_role_name = $this->wpie_final_data[ 'role' ];
                        }
                }

                if ( $superior_role_attempted && ! $this->can_manage_superior_users() ) {
                        /* translators: %s: User role name. */
                        $this->set_log( '<strong>' . __( 'ERROR', 'wp-import-export-lite' ) . '</strong> : ' . sprintf( __( 'You do not have permission to assign the superior role "%s".', 'wp-import-export-lite' ), esc_html( $attempted_role_name ) ) );
                        $this->process_log[ 'imported' ]++;
                        $this->process_log[ 'skipped' ]++;
                        return true;
                }

                if ( $this->is_update_field( "nickname" ) ) {

                        $this->wpie_final_data[ 'nickname' ] = wpie_sanitize_field( $this->get_field_value( 'wpie_item_nickname' ) );
                }
                if ( $this->is_update_field( "desc" ) ) {

                        $this->wpie_final_data[ 'description' ] = $this->get_field_value( 'wpie_item_description' );
                }
                if ( $this->is_update_field( "login" ) ) {

                        $this->wpie_final_data[ 'user_login' ] = wpie_sanitize_field( $this->get_field_value( 'wpie_item_user_login' ) );
                }

                $is_hashed_wp_password = false;

                if ( $this->is_update_field( "password" ) ) {

                        $this->wpie_final_data[ 'user_pass' ] = wpie_sanitize_field( $this->get_field_value( 'wpie_item_user_pass' ) );

                        $is_hashed_wp_password = ( absint( wpie_sanitize_field( $this->get_field_value( 'wpie_item_set_hashed_password' ) ) ) == 1 );

                        if ( $is_hashed_wp_password && ! $this->can_manage_superior_users() ) {
                                $is_hashed_wp_password = false;
                        }
                }

                if ( $this->is_update_field( "nicename" ) ) {

                        $this->wpie_final_data[ 'user_nicename' ] = wpie_sanitize_field( $this->get_field_value( 'wpie_item_user_nicename' ) );
                }
                if ( $this->is_update_field( "email" ) ) {

                        $this->wpie_final_data[ 'user_email' ] = wpie_sanitize_field( $this->get_field_value( 'wpie_item_user_email' ) );
                }
                if ( $this->is_update_field( "registered_date" ) ) {

                        $user_registered = wpie_sanitize_field( $this->get_field_value( 'wpie_item_user_registered' ) );

                        if ( empty( trim( $user_registered ) ) || strtotime( $user_registered ) === false ) {
                                $user_registered = current_time( 'mysql' );
                        }

                        $this->wpie_final_data[ 'user_registered' ] = gmdate( 'Y-m-d H:i:s', strtotime( $user_registered ) );
                }
                if ( $this->is_update_field( "display_name" ) ) {

                        $this->wpie_final_data[ 'display_name' ] = wpie_sanitize_field( $this->get_field_value( 'wpie_item_display_name' ) );
                }
                if ( $this->is_update_field( "url" ) ) {

                        $this->wpie_final_data[ 'user_url' ] = wpie_sanitize_field( $this->get_field_value( 'wpie_item_user_url' ) );
                }

                $this->wpie_final_data = apply_filters( 'wpie_before_user_import', $this->wpie_final_data, $this->wpie_import_option );

                $send_notifications = wpie_sanitize_field( $this->get_field_value( 'wpie_item_send_email_notifications' ) );

                if ( empty( $send_notifications ) || absint( $send_notifications ) !== 1 ) {

                        $this->remove_email_notifications();
                }

                if ( $this->is_new_item ) {

                        if ( !isset( $this->wpie_final_data[ 'user_email' ] ) || '' === trim( $this->wpie_final_data[ 'user_email' ] ) ) {

                                $this->set_log( '<strong>' . __( 'ERROR', 'wp-import-export-lite' ) . '</strong> : ' . __( 'Cannot create a user with an empty email', 'wp-import-export-lite' ) );

                                $this->process_log[ 'imported' ]++;

                                $this->process_log[ 'skipped' ]++;

                                return true;
                        }

                        if ( ! $this->can_manage_superior_users() ) {
                                $assigned_role = isset( $this->wpie_final_data[ 'role' ] ) && ! empty( $this->wpie_final_data[ 'role' ] ) ? $this->wpie_final_data[ 'role' ] : \get_option( 'default_role' );
                                if ( $this->is_superior_role( $assigned_role ) ) {
                                        /* translators: %s: User role name. */
                                        $this->set_log( '<strong>' . __( 'ERROR', 'wp-import-export-lite' ) . '</strong> : ' . sprintf( __( 'You do not have permission to assign the superior role "%s".', 'wp-import-export-lite' ), esc_html( $assigned_role ) ) );
                                        $this->process_log[ 'imported' ]++;
                                        $this->process_log[ 'skipped' ]++;
                                        return true;
                                }
                        }

                        $this->item_id = wp_insert_user( $this->wpie_final_data );
                } else {

                        if ( $this->get_login_user_id() === $this->existing_item_id ) {

                                $this->set_log( '<strong>' . __( 'Warning', 'wp-import-export-lite' ) . '</strong> : ' . __( "Can't update current Login user", 'wp-import-export-lite' ) );

                                $this->process_log[ 'skipped' ]++;

                                $this->process_log[ 'imported' ]++;

                                return true;
                        }

                        $target_user = \get_user_by( 'id', $this->existing_item_id );
                        if ( $target_user && $this->is_superior_user( $target_user ) && ! $this->can_manage_superior_users() ) {
                                $this->set_log( '<strong>' . __( 'Warning', 'wp-import-export-lite' ) . '</strong> : ' . __( 'You do not have permission to update superior users.', 'wp-import-export-lite' ) );
                                $this->process_log[ 'skipped' ]++;
                                $this->process_log[ 'imported' ]++;
                                return true;
                        }

                        // Defence-in-depth: block superior role assignment in the UPDATE path.
                        // This mirrors the guard in the INSERT branch (lines ~405-414) and catches
                        // roles injected via the 'wpie_before_user_import' filter after the earlier check.
                        if ( ! $this->can_manage_superior_users() && ! empty( $this->wpie_final_data[ 'role' ] ) ) {
                                if ( $this->is_superior_role( $this->wpie_final_data[ 'role' ] ) ) {
                                        /* translators: %s: User role name. */
                                        $this->set_log( '<strong>' . __( 'ERROR', 'wp-import-export-lite' ) . '</strong> : ' . sprintf( __( 'You do not have permission to assign the superior role "%s".', 'wp-import-export-lite' ), esc_html( $this->wpie_final_data[ 'role' ] ) ) );
                                        $this->process_log[ 'imported' ]++;
                                        $this->process_log[ 'skipped' ]++;
                                        return true;
                                }
                        }

                        $this->wpie_final_data[ 'ID' ] = $this->existing_item_id;

                        $this->item_id = wp_update_user( $this->wpie_final_data );
                }

                $this->process_log[ 'imported' ]++;

                if ( is_wp_error( $this->item_id ) ) {

                        $this->set_log( '<strong>' . __( 'ERROR', 'wp-import-export-lite' ) . '</strong> : ' . $this->item_id->get_error_message() );

                        $this->process_log[ 'skipped' ]++;

                        return true;
                } elseif ( $this->item_id == 0 ) {

                        $this->set_log( '<strong>' . __( 'ERROR', 'wp-import-export-lite' ) . '</strong> : ' . __( 'something wrong, ID = 0 was generated.', 'wp-import-export-lite' ) );

                        $this->process_log[ 'skipped' ]++;

                        return true;
                }
                if ( $this->is_new_item ) {
                        $this->process_log[ 'created' ]++;
                } else {
                        $this->process_log[ 'updated' ]++;
                }

                $this->item = get_user_by( "id", $this->item_id );

                $this->process_log[ 'last_records_id' ] = $this->item_id;

                $this->process_log[ 'last_records_status' ] = 'pending';

                $this->process_log[ 'last_activity' ] = gmdate( 'Y-m-d H:i:s' );

                $wpdb->update( $wpdb->prefix . "wpie_template", array( 'last_update_date' => current_time( 'mysql' ),
                        'process_log'      => maybe_serialize( $this->process_log ) ), array(
                        'id' => $this->wpie_import_id ) );

                if ( $is_hashed_wp_password && $this->can_manage_superior_users() ) {

                        $pass_hash = trim( (string) $this->wpie_final_data[ 'user_pass' ] );
                        if ( preg_match( '/^(\$P\$|\$2y\$|\$argon2[a-z]*\$|[a-f0-9]{32})/i', $pass_hash ) ) {
                                $wpdb->query( $wpdb->prepare(
                                                "
                                UPDATE `" . $wpdb->prefix . 'users' . "`
                                SET `user_pass` = %s
                                WHERE `ID` = %d
                                ", $pass_hash, $this->item_id
                                        ) );
                        }
                }

                if ( empty( $send_notifications ) || absint( $send_notifications ) !== 1 ) {
                        $this->add_email_notifications();
                } elseif ( $this->is_new_item ) {
                        \wp_new_user_notification( $this->item_id, null, 'both' );
                }

                unset( $send_notifications );

                do_action( 'wpie_after_user_import', $this->item_id, $this->wpie_final_data, $this->wpie_import_option );

                if ( $this->is_update_field( "cf" ) ) {

                        $this->wpie_import_cf();
                }

                if ( !empty( $roles ) ) {

                        foreach ( $roles as $role ) {
                                if ( ! $this->can_manage_superior_users() && $this->is_superior_role( $role ) ) {
                                        continue;
                                }
                                $this->item->add_role( $role );
                        }
                }

                return $this->item_id;
        }

        public function do_not_send_notification( $is_notify, $user, $userdata ) {

                return false;
        }

        private function remove_email_notifications() {

                remove_filter( 'after_password_reset', 'wp_password_change_notification' );
                remove_filter( 'register_new_user', 'wp_send_new_user_notifications' );
                remove_filter( 'edit_user_created_user', 'wp_send_new_user_notifications' );

                //Ultimate Member plugin Email Notifications 
                remove_all_actions( 'um_registration_complete' );

                add_filter( 'send_password_change_email', [ $this, 'do_not_send_notification' ], 99999, 3 );
                add_filter( 'send_email_change_email', [ $this, 'do_not_send_notification' ], 99999, 3 );
        }

        private function add_email_notifications() {

                remove_filter( 'send_password_change_email', [ $this, 'do_not_send_notification' ] );
                remove_filter( 'send_email_change_email', [ $this, 'do_not_send_notification' ] );

                add_action( 'after_password_reset', 'wp_password_change_notification' );
                add_action( 'register_new_user', 'wp_send_new_user_notifications' );
                add_action( 'edit_user_created_user', 'wp_send_new_user_notifications', 10, 2 );
        }

        protected function search_duplicate_item() {

		$raw_indicator = $this->get_field_value( 'wpie_existing_item_search_logic', true );
		$wpie_duplicate_indicator = empty( $raw_indicator ) ? 'email' : wpie_sanitize_field( $raw_indicator );

                if ( $wpie_duplicate_indicator == "id" ) {

                        $duplicate_id = absint( wpie_sanitize_field( $this->get_field_value( 'wpie_existing_item_search_logic_id' ) ) );

                        if ( $duplicate_id > 0 ) {
                                $user = get_user_by( 'id', absint( $duplicate_id ) );

                                if ( $user ) {
                                        $this->existing_item_id = $duplicate_id;
                                }
                                unset( $user );
                        }
                        unset( $duplicate_id );
                } elseif ( $wpie_duplicate_indicator == "email" ) {

                        $email = wpie_sanitize_field( $this->get_field_value( 'wpie_item_user_email' ) );

                        if ( !empty( $email ) ) {
                                $user = get_user_by( 'email', $email );

                                if ( $user ) {
                                        $this->existing_item_id = $user->ID;
                                }
                                unset( $user );
                        }
                        unset( $email );
                } elseif ( $wpie_duplicate_indicator == "login" ) {

                        $user_login = wpie_sanitize_field( $this->get_field_value( 'wpie_item_user_login' ) );

                        if ( !empty( $user_login ) ) {
                                $user = get_user_by( 'login', $user_login );

                                if ( $user ) {
                                        $this->existing_item_id = $user->ID;
                                }
                                unset( $user );
                        }
                        unset( $user_login );
                } elseif ( $wpie_duplicate_indicator == "cf" ) {

                        $meta_key = wpie_sanitize_field( $this->get_field_value( 'wpie_existing_item_search_logic_cf_key' ) );

                        $meta_val = wpie_sanitize_field( $this->get_field_value( 'wpie_existing_item_search_logic_cf_value' ) );

                        $user_query = array(
                                'meta_query' => array(
                                        0 => array(
                                                'key'     => $meta_key,
                                                'value'   => $meta_val,
                                                'compare' => '='
                                        )
                                )
                        );

                        $user_data = new \WP_User_Query( $user_query );

                        unset( $user_query );

                        if ( !empty( $user_data->results ) ) {
                                foreach ( $user_data->results as $user ) {
                                        $this->existing_item_id = $user->ID;
                                        break;
                                }
                        } else {
                                $user_data_found = $wpdb->get_results( $wpdb->prepare( "SELECT SQL_CALC_FOUND_ROWS " . $wpdb->users . ".ID FROM " . $wpdb->users . " INNER JOIN " . $wpdb->usermeta . " ON (" . $wpdb->users . ".ID = " . $wpdb->usermeta . ".user_id) WHERE 1=1 AND ( (" . $wpdb->usermeta . ".meta_key = %s AND " . $wpdb->usermeta . ".meta_value = %s) ) GROUP BY " . $wpdb->users . ".ID ORDER BY " . $wpdb->users . ".ID ASC LIMIT 0, 1", $meta_key, $meta_val ) );

                                if ( !empty( $user_data_found ) ) {
                                        foreach ( $user_data_found as $user ) {
                                                $this->existing_item_id = $user->ID;
                                                break;
                                        }
                                }
                                unset( $user_data_found );
                        }
                        unset( $meta_key, $meta_val, $user_data );
                }
                unset( $wpie_duplicate_indicator );
        }

        private function get_valid_roles( $userRoles = [] ) {

                if ( empty( $userRoles ) ) {
                        return [];
                }

                $roles = [];

                $wp_roles = \wp_roles();

                $site_roles = $wp_roles->get_names();

                foreach ( $userRoles as $role ) {

                        if ( empty( trim( $role ) ) ) {
                                continue;
                        }
                        $role = trim( $role );

                        $site_role = "";

                        foreach ( $site_roles as $key => $name ) {

                                if (
                                        $role === $key ||
                                        $role === $name ||
                                        strtolower( trim( $role ) ) === strtolower( trim( $key ) ) ||
                                        strtolower( trim( $role ) ) === strtolower( trim( $name ) )
                                ) {
                                        $site_role = $key;
                                        break;
                                }
                        }
                        if ( !empty( $site_role ) ) {
                                $roles[] = $site_role;
                        }
                }

                unset( $wp_roles, $site_roles );

                return $roles;
        }

}

<?php
/**
 * Plugin Name: Role-Based Split Engine
 * Description: Manages and tracks intended monetary splits based on user roles.
 * Version: 0.1.0
 * Author: Your Name
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: role-based-split-engine
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die( 'No direct script access allowed!' );
}

/**
 * Load text domain for translations.
 *
 * @since 0.1.0
 */
function rbse_load_textdomain() {
    load_plugin_textdomain( 'role-based-split-engine', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'rbse_load_textdomain' );

/**
 * Displays the 'Wallet Address' field on user profile editing pages.
 *
 * @since 0.1.0
 * @param WP_User $user The current WP_User object.
 */
function rbse_show_wallet_address_field( $user ) {
    ?>
    <h3><?php esc_html_e( 'Payment Information', 'role-based-split-engine' ); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="user_wallet_address"><?php esc_html_e( 'Wallet Address', 'role-based-split-engine' ); ?></label></th>
            <td>
                <input type="text" name="user_wallet_address" id="user_wallet_address" value="<?php echo esc_attr( get_user_meta( $user->ID, 'user_wallet_address', true ) ); ?>" class="regular-text" />
                <p class="description">
                    <?php esc_html_e( 'Enter the identifier for off-system payments for this user (e.g., PayPal email, bank account details, crypto wallet address).', 'role-based-split-engine' ); ?>
                </p>
                <?php wp_nonce_field( 'rbse_save_wallet_address', 'rbse_wallet_address_nonce' ); ?>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'rbse_show_wallet_address_field' ); // For editing own profile
add_action( 'edit_user_profile', 'rbse_show_wallet_address_field' ); // For admins editing other users

/**
 * Saves the 'Wallet Address' field data when user profile is updated.
 *
 * @since 0.1.0
 * @param int $user_id The ID of the user being updated.
 */
function rbse_save_wallet_address_field( $user_id ) {
    // Verify nonce
    if ( ! isset( $_POST['rbse_wallet_address_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['rbse_wallet_address_nonce'] ), 'rbse_save_wallet_address' ) ) {
        return;
    }

    // Check permissions: ensure the current user can edit the target user's profile.
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    // Save the field
    if ( isset( $_POST['user_wallet_address'] ) ) {
        $wallet_address = sanitize_text_field( wp_unslash( $_POST['user_wallet_address'] ) );
        update_user_meta( $user_id, 'user_wallet_address', $wallet_address );
    }
}
add_action( 'personal_options_update', 'rbse_save_wallet_address_field' ); // When user updates their own profile
add_action( 'edit_user_profile_update', 'rbse_save_wallet_address_field' ); // When admin updates another user's profile

/**
 * Adds an admin menu page to view all user wallet addresses.
 *
 * @since 0.1.0
 */
function rbse_admin_menu_wallet_addresses() {
    add_users_page(
        __( 'User Wallet Addresses', 'role-based-split-engine' ),
        __( 'Wallet Addresses', 'role-based-split-engine' ),
        'manage_options', // Capability required
        'user-wallet-addresses',
        'rbse_render_user_wallet_addresses_page'
    );
}
add_action( 'admin_menu', 'rbse_admin_menu_wallet_addresses' );

/**
 * Renders the admin page for viewing user wallet addresses.
 *
 * Displays a table of users with their username, email, and saved wallet address.
 * Includes a filter to show all users or only those missing a wallet address.
 *
 * @since 0.1.0
 */
function rbse_render_user_wallet_addresses_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'role-based-split-engine' ) );
    }

    // Handle filtering
    $filter = isset( $_GET['filter_wallet_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_wallet_status'] ) ) : 'all';

    $args = array(
        'orderby' => 'login',
        'order'   => 'ASC',
        // Potentially add pagination in a future version if user count is very high
        // 'number' => 20, 
        // 'paged' => isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1,
    );

    if ( 'missing' === $filter ) {
        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key'     => 'user_wallet_address',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => 'user_wallet_address',
                'value'   => '',
                'compare' => '=',
            ),
        );
    }

    $users = get_users( $args );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'User Wallet Addresses', 'role-based-split-engine' ); ?></h1>

        <form method="get">
            <input type="hidden" name="page" value="user-wallet-addresses" />
            <label for="filter_wallet_status"><?php esc_html_e( 'Filter by:', 'role-based-split-engine' ); ?></label>
            <select name="filter_wallet_status" id="filter_wallet_status">
                <option value="all" <?php selected( $filter, 'all' ); ?>><?php esc_html_e( 'Show all users', 'role-based-split-engine' ); ?></option>
                <option value="missing" <?php selected( $filter, 'missing' ); ?>><?php esc_html_e( 'Show users missing wallet address', 'role-based-split-engine' ); ?></option>
            </select>
            <?php submit_button( __( 'Filter', 'role-based-split-engine' ), 'secondary', '', false ); ?>
        </form>

        <table class="wp-list-table widefat fixed striped users">
            <thead>
                <tr>
                    <th scope="col" id="username" class="manage-column column-username column-primary sortable desc">
                        <a href="<?php echo esc_url( add_query_arg( array( 'orderby' => 'login', 'order' => ( isset( $_GET['order'] ) && $_GET['order'] === 'asc' ? 'desc' : 'asc' ) ) ) ); ?>">
                            <span><?php esc_html_e( 'Username', 'role-based-split-engine' ); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th scope="col" id="email" class="manage-column column-email sortable desc">
                         <a href="<?php echo esc_url( add_query_arg( array( 'orderby' => 'email', 'order' => ( isset( $_GET['order'] ) && $_GET['order'] === 'asc' ? 'desc' : 'asc' ) ) ) ); ?>">
                            <span><?php esc_html_e( 'Email', 'role-based-split-engine' ); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th scope="col" id="wallet_address" class="manage-column column-wallet_address">
                        <?php esc_html_e( 'Wallet Address', 'role-based-split-engine' ); ?>
                    </th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php if ( ! empty( $users ) ) : ?>
                    <?php foreach ( $users as $user ) : ?>
                        <?php $wallet_address = get_user_meta( $user->ID, 'user_wallet_address', true ); ?>
                        <tr>
                            <td class="username column-username has-row-actions column-primary" data-colname="Username">
                                <?php echo get_avatar( $user->ID, 32 ); ?>
                                <strong>
                                    <a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>">
                                        <?php echo esc_html( $user->user_login ); ?>
                                    </a>
                                </strong>
                                <br>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>">
                                            <?php esc_html_e( 'Edit Profile', 'role-based-split-engine' ); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td class="email column-email" data-colname="Email">
                                <a href="<?php echo esc_url( 'mailto:' . $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a>
                            </td>
                            <td class="wallet_address column-wallet_address" data-colname="Wallet Address">
                                <?php if ( ! empty( $wallet_address ) ) : ?>
                                    <?php echo esc_html( $wallet_address ); ?>
                                <?php else : ?>
                                    <em><?php esc_html_e( 'Not Set', 'role-based-split-engine' ); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="3"><?php esc_html_e( 'No users found.', 'role-based-split-engine' ); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// (Rest of the plugin code will go here)

/**
 * Creates the custom database tables required by the plugin.
 *
 * This function is hooked to run on plugin activation.
 * It uses dbDelta to create/update tables safely.
 *
 * @since 0.1.0
 */
function rbse_create_custom_tables() {
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $charset_collate = $wpdb->get_charset_collate();

    // Table for storing split event master records
    $table_name_events = $wpdb->prefix . 'rbse_split_events';
    $sql_events = "CREATE TABLE $table_name_events (
        split_event_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        total_amount DECIMAL(15,4) NOT NULL,
        currency VARCHAR(10) NOT NULL,
        initiated_by_user_id BIGINT(20) UNSIGNED NOT NULL,
        processing_status VARCHAR(25) NOT NULL DEFAULT 'pending',
        notes TEXT NULL,
        event_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (split_event_id),
        INDEX idx_initiated_by_user_id (initiated_by_user_id),
        INDEX idx_event_timestamp (event_timestamp),
        INDEX idx_processing_status (processing_status)
    ) $charset_collate;";
    dbDelta( $sql_events );

    // Table for storing individual recipient records for each split event
    $table_name_recipients = $wpdb->prefix . 'rbse_split_event_recipients';
    $sql_recipients = "CREATE TABLE $table_name_recipients (
        recipient_record_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        split_event_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        user_role_at_time_of_split VARCHAR(100) NOT NULL,
        assigned_wallet_address VARCHAR(255) NULL,
        role_percentage_for_event DECIMAL(7,4) NOT NULL COMMENT 'e.g., 50.0000 for 50% for the role',
        num_users_in_role_for_event INT UNSIGNED NOT NULL,
        individual_share_amount DECIMAL(15,4) NOT NULL,
        is_missing_wallet TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Boolean: 0 for false, 1 for true',
        PRIMARY KEY  (recipient_record_id),
        INDEX idx_split_event_id (split_event_id),
        INDEX idx_user_id (user_id),
        INDEX idx_user_role_at_time_of_split (user_role_at_time_of_split),
        INDEX idx_is_missing_wallet (is_missing_wallet)
    ) $charset_collate;";
    dbDelta( $sql_recipients );
}
register_activation_hook( __FILE__, 'rbse_create_custom_tables' );

/**
 * Adds the admin menu pages for the Role-Based Split Engine.
 *
 * @since 0.1.0
 */
function rbse_admin_menu() {
    // Add top-level menu
    add_menu_page(
        __( 'Split Engine', 'role-based-split-engine' ),
        __( 'Split Engine', 'role-based-split-engine' ),
        'manage_options',
        'rbse-main-menu',
        null, // No callback for the top-level page itself, first submenu will be default
        'dashicons-share-alt2',
        75 // Position
    );

    // Add submenu page for processing new splits
    add_submenu_page(
        'rbse-main-menu',
        __( 'Process New Split', 'role-based-split-engine' ),
        __( 'Process New Split', 'role-based-split-engine' ),
        'manage_options',
        'rbse-process-new-split',
        'rbse_render_process_new_split_page'
    );

    // Add submenu page for analytics
        'rbse-process-new-split',
        'rbse_render_process_new_split_page'
    );

    // Add submenu page for analytics
    add_submenu_page(
        'rbse-main-menu',
        __( 'Split Analytics', 'role-based-split-engine' ),
        __( 'Split Analytics', 'role-based-split-engine' ),
        'manage_options', // Or a more specific capability for viewing analytics
        'rbse-split-analytics',
        'rbse_render_split_analytics_page'
    );
    
    // Placeholder for Event Detail Page (will be created in a future task)
    add_submenu_page(
        null, // No parent menu, hidden
        __( 'Split Event Details', 'role-based-split-engine' ),
        __( 'Split Event Details', 'role-based-split-engine' ),
        'manage_options',
        'rbse-event-detail', // This slug will be used in links
        function() { echo '<div class="wrap"><h1>Event Details Page (To be implemented)</h1></div>'; } // Placeholder callback
    );

    // Add submenu page for Settings
    add_submenu_page(
        'rbse-main-menu',
        __( 'Settings', 'role-based-split-engine' ),
        __( 'Settings', 'role-based-split-engine' ),
        'manage_options', 
        'rbse-settings',
        'rbse_render_settings_page'
    );
}
add_action( 'admin_menu', 'rbse_admin_menu' );

/**
 * Renders the HTML for the 'Process New Split' admin page.
 *
 * @since 0.1.0
 */
function rbse_render_process_new_split_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'role-based-split-engine' ) );
    }
    ?>
    <div class="wrap rbse-wrap">
        <h1><?php esc_html_e( 'Process New Role-Based Split', 'role-based-split-engine' ); ?></h1>
        
        <form id="rbse-new-split-form" method="POST">
            <?php wp_nonce_field( 'rbse_process_new_split_action', 'rbse_process_new_split_nonce' ); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="rbse_total_amount"><?php esc_html_e( 'Total Amount', 'role-based-split-engine' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="rbse_total_amount" name="rbse_total_amount" step="0.01" min="0" required class="regular-text" />
                        <p class="description"><?php esc_html_e( 'The total amount to be split (e.g., 100.00).', 'role-based-split-engine' ); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="rbse_currency"><?php esc_html_e( 'Currency', 'role-based-split-engine' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="rbse_currency" name="rbse_currency" required class="regular-text" placeholder="<?php esc_attr_e( 'e.g., USD, EUR, POINTS', 'role-based-split-engine' ); ?>" />
                        <p class="description"><?php esc_html_e( 'The currency code or unit for the amount (e.g., USD, POINTS).', 'role-based-split-engine' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Role Splits', 'role-based-split-engine' ); ?></h2>
            <div id="rbse_role_splits_container">
                <?php // JavaScript will populate this area ?>
            </div>
            <button type="button" id="rbse_add_role_split_button" class="button">
                <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e( 'Add Role Split', 'role-based-split-engine' ); ?>
            </button>
            
            <p style="margin-top: 15px; font-weight: bold;">
                <?php esc_html_e( 'Total Percentage:', 'role-based-split-engine' ); ?> <span id="rbse_total_percentage_display">0</span>%
                <span id="rbse_percentage_warning" style="color: red; margin-left: 10px; display: none;">
                    <?php esc_html_e( 'Total must be 100% to proceed.', 'role-based-split-engine' ); ?>
                </span>
            </p>

            <table class="form-table">
                 <tr valign="top">
                    <th scope="row">
                        <label for="rbse_notes"><?php esc_html_e( 'Notes (Optional)', 'role-based-split-engine' ); ?></label>
                    </th>
                    <td>
                        <textarea id="rbse_notes" name="rbse_notes" rows="4" class="large-text"></textarea>
                        <p class="description"><?php esc_html_e( 'Any notes regarding this split event.', 'role-based-split-engine' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="rbse_submit_split" id="rbse_submit_split_button" class="button button-primary" value="<?php esc_attr_e( 'Process Split', 'role-based-split-engine' ); ?>" />
            </p>
        </form>
        <div id="rbse-ajax-response" style="margin-top: 20px;"></div>
    </div>
    <?php
}

/**
 * Enqueues admin scripts for the Role-Based Split Engine.
 *
 * @since 0.1.0
 * @param string $hook_suffix The current admin page hook.
 */
function rbse_enqueue_admin_scripts( $hook_suffix ) {
    // Only load on our specific admin page (Split Engine > Process New Split) or Analytics page
    $current_screen = get_current_screen();
    $is_process_split_page = ( $current_screen && $current_screen->id === 'split-engine_page_rbse-process-new-split' );
    $is_analytics_page = ( $current_screen && $current_screen->id === 'split-engine_page_rbse-split-analytics' );

    if ( $is_process_split_page || $is_analytics_page ) {
        $script_path = plugin_dir_url( __FILE__ ) . 'admin/js/rbse-admin-scripts.js';
        $script_asset_path = plugin_dir_path( __FILE__ ) . 'admin/js/rbse-admin-scripts.asset.php';
        
        $dependencies = array( 'jquery' );
        $version = '0.1.1'; // Incremented version
        if ( file_exists( $script_asset_path ) ) {
            $asset = require( $script_asset_path );
            $dependencies = array_unique( array_merge( $dependencies, $asset['dependencies'] ) );
            $version = $asset['version'];
        }

        if ( $is_analytics_page ) {
            // Enqueue Chart.js from CDN for analytics page
            wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js', array(), '3.7.0', true );
            $dependencies[] = 'chartjs'; // Add chartjs as a dependency for our script on this page
        }
        
        wp_enqueue_script(
            'rbse-admin-scripts',
            $script_path,
            array_unique($dependencies), // Ensure unique dependencies
            $version,
            true 
        );

        // Data for "Process New Split" page
        if ($is_process_split_page) {
        $editable_roles = get_editable_roles();
        $roles_for_js = array();
        foreach ( $editable_roles as $slug => $details ) {
            $roles_for_js[ $slug ] = translate_user_role( $details['name'] ); // Use translated role names
        }

            $editable_roles = get_editable_roles();
            $roles_for_js = array();
            foreach ( $editable_roles as $slug => $details ) {
                $roles_for_js[ $slug ] = translate_user_role( $details['name'] );
            }
            wp_localize_script(
                'rbse-admin-scripts',
                'rbse_process_split_params', // Changed name to be specific
                array(
                    'roles' => $roles_for_js,
                    'nonce' => wp_create_nonce( 'rbse_process_new_split_action' ),
                    'i18n'  => array(
                        'remove_role' => __( 'Remove Role', 'role-based-split-engine' ),
                        'role'        => __( 'Role', 'role-based-split-engine' ),
                        'percentage'  => __( 'Percentage', 'role-based-split-engine' ),
                        'total_must_be_100' => __( 'Total percentage must be 100%.', 'role-based-split-engine' ),
                        'processing'  => __( 'Processing...', 'role-based-split-engine' ),
                        'error_occurred' => __( 'An error occurred. Please try again.', 'role-based-split-engine' ),
                        'invalid_percentage_entry' => __( 'Invalid percentage values detected.', 'role-based-split-engine' ),
                    ),
                )
            );
        }
        // Data for "Analytics Page" (Chart data will be localized here)
        if ($is_analytics_page) {
            // Data for Role-Based Analytics Chart will be added here later after querying it in rbse_render_split_analytics_page
        }
    }
}
add_action( 'admin_enqueue_scripts', 'rbse_enqueue_admin_scripts' );

/**
 * Handles the AJAX request for processing a new split event.
 *
 * Verifies nonce, user permissions, sanitizes and validates input data,
 * then inserts records into custom database tables.
 *
 * @since 0.1.0
 */
function rbse_ajax_process_split_handler() {
    // Security: Check AJAX nonce
    check_ajax_referer( 'rbse_process_new_split_action', 'rbse_process_new_split_nonce' );

    // Security: Check user capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied. You must have manage_options capability.', 'role-based-split-engine' ) ), 403 );
        return;
    }

    // Retrieve and sanitize basic form data
    $total_amount = isset( $_POST['rbse_total_amount'] ) ? floatval( wp_unslash( $_POST['rbse_total_amount'] ) ) : 0;
    $currency     = isset( $_POST['rbse_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['rbse_currency'] ) ) : '';
    $notes        = isset( $_POST['rbse_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rbse_notes'] ) ) : '';
    $roles_data   = isset( $_POST['rbse_roles'] ) && is_array( $_POST['rbse_roles'] ) ? $_POST['rbse_roles'] : array();

    // --- Validation ---
    // Retrieve settings to use the correct wallet meta key and policies
    $rbse_settings_options = get_option( 'rbse_settings', array() );
    $wallet_meta_key = !empty( $rbse_settings_options['wallet_meta_key'] ) ? $rbse_settings_options['wallet_meta_key'] : 'user_wallet_address';
    $missing_wallet_policy = !empty( $rbse_settings_options['missing_wallet_policy'] ) ? $rbse_settings_options['missing_wallet_policy'] : 'record_anyway'; // Default: 'record_anyway' renamed from 'record_flag' for clarity
    $empty_role_policy = !empty( $rbse_settings_options['empty_role_policy'] ) ? $rbse_settings_options['empty_role_policy'] : 'forfeit_share';

    if ( $total_amount <= 0 ) {
        wp_send_json_error( array( 'message' => __( 'Total amount must be greater than zero.', 'role-based-split-engine' ) ) );
        return;
    }
    if ( empty( $currency ) ) {
        wp_send_json_error( array( 'message' => __( 'Currency cannot be empty.', 'role-based-split-engine' ) ) );
        return;
    }
    if ( empty( $roles_data ) ) {
        wp_send_json_error( array( 'message' => __( 'No role splits defined. Please add at least one role.', 'role-based-split-engine' ) ) );
        return;
    }

    $calculated_total_percentage = 0;
    $sanitized_roles = array();
    foreach ( $roles_data as $index => $role_item ) {
        if ( !isset( $role_item['name'] ) || !isset( $role_item['percentage'] ) ) {
            wp_send_json_error( array( 'message' => sprintf( __( 'Invalid data for role split at row %d.', 'role-based-split-engine' ), $index + 1 ) ) );
            return;
        }
        $role_slug = sanitize_key( $role_item['name'] );
        $percentage = floatval( $role_item['percentage'] );

        if ( empty( $role_slug ) ) {
            wp_send_json_error( array( 'message' => sprintf( __( 'Role not selected at row %d.', 'role-based-split-engine' ), $index + 1 ) ) );
            return;
        }
        if ( $percentage <= 0 || $percentage > 100 ) {
            wp_send_json_error( array( 'message' => sprintf( __( 'Percentage for role %s (row %d) must be between 0 (exclusive) and 100 (inclusive).', 'role-based-split-engine' ), $role_slug, $index + 1 ) ) );
            return;
        }
        $sanitized_roles[] = array( 'name' => $role_slug, 'percentage' => $percentage );
        $calculated_total_percentage += $percentage;
    }

    // Check if total percentage is very close to 100
    if ( abs( $calculated_total_percentage - 100.0 ) > 0.001 ) { // Tolerance for floating point
        wp_send_json_error( array( 'message' => sprintf( __( 'Total percentage must be exactly 100%%. Current total: %.2f%%.', 'role-based-split-engine' ), $calculated_total_percentage ) ) );
        return;
    }

    // --- Processing Logic ---
    global $wpdb;
    $table_events = $wpdb->prefix . 'rbse_split_events';
    $table_recipients = $wpdb->prefix . 'rbse_split_event_recipients';
    $current_user_id = get_current_user_id();

    // 1. Create the master split event record
    $event_data = array(
        'total_amount'           => $total_amount,
        'currency'               => $currency,
        'initiated_by_user_id'   => $current_user_id,
        'processing_status'      => 'pending', // Initial status
        'notes'                  => $notes,
        'event_timestamp'        => current_time( 'mysql', true ), // GMT time
    );
    
    $event_inserted = $wpdb->insert( $table_events, $event_data );
    if ( ! $event_inserted ) {
        wp_send_json_error( array( 'message' => __( 'Failed to create split event record in the database.', 'role-based-split-engine' ) . ' ' . $wpdb->last_error ) );
        return;
    }
    $split_event_id = $wpdb->insert_id;

    $issues_found = false;
    $processing_notes_array = array(); // For collecting notes about processing

    // 2. Process each role and its recipients
    foreach ( $sanitized_roles as $role_data ) {
        $role_slug = $role_data['name'];
        $percentage_for_role = $role_data['percentage'];

        $users_in_role_objects = get_users( array( 'role' => $role_slug, 'fields' => array( 'ID', 'user_login' ) ) );

        if ( empty( $users_in_role_objects ) ) {
            if ( $empty_role_policy === 'forfeit_share' ) {
                $processing_notes_array[] = sprintf( __( "Policy Applied (Empty Role - forfeit_share): No users found for role: %s. Its %.2f%% share was unallocated/forfeited.", 'role-based-split-engine' ), $role_slug, $percentage_for_role );
                // $issues_found = true; // This is noted, but not necessarily an "issue" that needs highlighting if policy is "forfeit".
            } 
            // Future policy: 'disperse_among_others'
            // else if ( $empty_role_policy === 'disperse_among_others' ) {
            //     $processing_notes_array[] = sprintf( __( "Policy Applied (Empty Role - disperse_among_others): No users for role %s. Its %.2f%% will be redistributed (logic TBD).", 'role-based-split-engine' ), $role_slug, $percentage_for_role );
            //     // Logic to handle redistribution would be complex:
            //     // 1. Sum total percentage of roles WITH users.
            //     // 2. Recalculate effective percentage for each role with users based on this new total.
            //     // 3. This would likely require a preliminary loop or a more complex data structure.
            //     // For now, we just note it and effectively forfeit as no users to distribute to from THIS role's direct processing.
            // }
            else {
                 // Fallback to default if policy is unknown (should not happen with proper settings sanitization)
                $processing_notes_array[] = sprintf( __( "Policy Applied (Empty Role - default/unknown: %s): No users found for role: %s. Its %.2f%% share was unallocated/forfeited.", 'role-based-split-engine' ), $empty_role_policy, $role_slug, $percentage_for_role );
            }
            continue; // Skip to next role if no users or policy dictates forfeiture for this step
        }

        $num_users_in_role = count( $users_in_role_objects );
        $amount_for_role = ( $total_amount * $percentage_for_role ) / 100.0;
        // Round to 4 decimal places for precision, can be adjusted based on currency needs
        $individual_share_amount = round( $amount_for_role / $num_users_in_role, 4 ); 

        foreach ( $users_in_role_objects as $user_object ) {
            $user_id = $user_object->ID;
            // Use the wallet meta key from settings
            $wallet_address = get_user_meta( $user_id, $wallet_meta_key, true );
            $is_missing_wallet_flag = empty( $wallet_address ) ? 1 : 0;

            if ( $is_missing_wallet_flag ) {
                $issues_found = true; // General flag that something needs attention in notes
                if ($missing_wallet_policy === 'record_anyway') {
                    $processing_notes_array[] = sprintf( __( "Policy Applied (Missing Wallet - record_anyway): User '%s' (ID: %d, Role: %s) is missing a wallet address. Share recorded.", 'role-based-split-engine' ), $user_object->user_login, $user_id, $role_slug );
                } 
                // Future policy: 'hold_share'
                // else if ($missing_wallet_policy === 'hold_share') {
                //     $processing_notes_array[] = sprintf( __( "Policy Applied (Missing Wallet - hold_share): User '%s' (ID: %d, Role: %s) is missing wallet. Share 'held' (logic TBD - e.g. different status in recipient table).", 'role-based-split-engine' ), $user_object->user_login, $user_id, $role_slug );
                //     // This might involve setting a different status in the recipient record, or flagging the main event more specifically.
                // }
                // Future policy: 'ignore_user'
                // else if ($missing_wallet_policy === 'ignore_user') {
                //     $processing_notes_array[] = sprintf( __( "Policy Applied (Missing Wallet - ignore_user): User '%s' (ID: %d, Role: %s) is missing wallet. Share forfeited/ignored for this user.", 'role-based-split-engine' ), $user_object->user_login, $user_id, $role_slug );
                //     // This would mean *not* creating a recipient record.
                //     // The share for this user would then need to be handled (e.g., forfeited, or redistributed among others in the same role if possible).
                //     continue; // Skip creating recipient record for this user
                // }
                else {
                     // Fallback to default if policy is unknown
                    $processing_notes_array[] = sprintf( __( "Policy Applied (Missing Wallet - default/unknown: %s): User '%s' (ID: %d, Role: %s) is missing a wallet address. Share recorded.", 'role-based-split-engine' ), $missing_wallet_policy, $user_object->user_login, $user_id, $role_slug );
                }
            }
            
            // Proceed to record if not 'ignore_user' (or if 'ignore_user' is not yet implemented, it falls through)
            // This part implicitly handles 'record_anyway' and is a placeholder for 'hold_share'
            $recipient_data = array(
                'split_event_id'              => $split_event_id,
                'user_id'                     => $user_id,
                'user_role_at_time_of_split'  => $role_slug,
                'assigned_wallet_address'     => $wallet_address, // Store even if empty, flag indicates status
                'role_percentage_for_event'   => $percentage_for_role,
                'num_users_in_role_for_event' => $num_users_in_role,
                'individual_share_amount'     => $individual_share_amount,
                'is_missing_wallet'           => $is_missing_wallet_flag,
            );

            $recipient_inserted = $wpdb->insert( $table_recipients, $recipient_data );
            if ( ! $recipient_inserted ) {
                // Log this error, update event status to 'error', and inform admin
                error_log("RBSE Error: Failed to insert recipient record for user ID {$user_id}, event ID {$split_event_id}. DB Error: " . $wpdb->last_error);
                $wpdb->update( $table_events, array( 'processing_status' => 'error', 'notes' => $notes . "\nCritical error during recipient insertion." ), array( 'split_event_id' => $split_event_id ) );
                wp_send_json_error( array( 'message' => __( 'A critical error occurred while saving recipient data. Event marked as error. Please check server logs.', 'role-based-split-engine' ) ) );
                return;
            }
        }
    }

    // 3. Finalize Event Status and Notes
    $final_status = $issues_found ? 'processed_with_issues' : 'processed';
    $update_event_data = array( 'processing_status' => $final_status );

    if ( !empty( $processing_notes_array ) ) {
        $final_notes = $notes;
        if( !empty($final_notes) ) $final_notes .= "\n\n"; // Add separator if original notes exist
        $final_notes .= __( "Processing Notes:", 'role-based-split-engine' ) . "\n" . implode( "\n", $processing_notes_array );
        $update_event_data['notes'] = $final_notes;
    }
    
    $wpdb->update( $table_events, $update_event_data, array( 'split_event_id' => $split_event_id ) );

    wp_send_json_success( array( 'message' => __( 'Split event processed successfully.', 'role-based-split-engine' ) . ( $issues_found ? ' ' . __( 'Please review processing notes for details.', 'role-based-split-engine' ) : '' ) ) );
}
add_action( 'wp_ajax_rbse_process_split', 'rbse_ajax_process_split_handler' );

/**
 * Renders the 'Split Analytics' admin page.
 *
 * Displays summary statistics and a list of recent split events.
 *
 * @since 0.1.0
 */
function rbse_render_split_analytics_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'role-based-split-engine' ) );
    }

    global $wpdb;
    $table_events = $wpdb->prefix . 'rbse_split_events';
    $table_recipients = $wpdb->prefix . 'rbse_split_event_recipients';

    // --- Summary Statistics ---
    $total_events = $wpdb->get_var( "SELECT COUNT(split_event_id) FROM $table_events" );
    $total_amount_processed_by_currency = $wpdb->get_results(
        "SELECT currency, SUM(total_amount) as total FROM $table_events GROUP BY currency", OBJECT_K );
    $recipient_share_summary = $wpdb->get_results( $wpdb->prepare(
        "SELECT e.currency, SUM(r.individual_share_amount) as total_distributed, 
                SUM(CASE WHEN r.is_missing_wallet = 0 THEN r.individual_share_amount ELSE 0 END) as distributed_to_wallets,
                SUM(CASE WHEN r.is_missing_wallet = 1 THEN r.individual_share_amount ELSE 0 END) as pending_missing_wallets
         FROM $table_recipients r JOIN $table_events e ON r.split_event_id = e.split_event_id GROUP BY e.currency"
    ), OBJECT_K );
    
    // --- Role-Based Analytics Data ---
    $role_analytics_data = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.user_role_at_time_of_split, SUM(r.individual_share_amount) as total_amount_for_role, 
                COUNT(DISTINCT r.user_id) as unique_recipients_in_role
         FROM $table_recipients r JOIN $table_events e ON r.split_event_id = e.split_event_id
         WHERE e.currency = %s GROUP BY r.user_role_at_time_of_split ORDER BY total_amount_for_role DESC",
         'USD' // Default or most common currency for chart - could be made dynamic
    ));

    // Localize data for Chart.js
    $chart_labels = array();
    $chart_data = array();
    if (!empty($role_analytics_data)) {
        foreach ($role_analytics_data as $row) {
            $chart_labels[] = translate_user_role($row->user_role_at_time_of_split); // Translate role name
            $chart_data[] = round($row->total_amount_for_role, 2);
        }
    }
     wp_localize_script('rbse-admin-scripts', 'rbse_analytics_params', array(
        'role_chart_labels' => $chart_labels,
        'role_chart_data' => $chart_data,
        'i18n' => array(
            'total_amount_by_role' => __( 'Total Amount Distributed by Role', 'role-based-split-engine' ),
        )
    ));


    // --- Recent Split Events ---
    $recent_events = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table_events ORDER BY event_timestamp DESC LIMIT %d", 10 ) );

    // --- User-Based Analytics List Table ---
    if ( ! class_exists( 'WP_List_Table' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
    }
    if ( ! class_exists( 'RBSE_Users_Analytics_List_Table' ) ) {
        // Define RBSE_Users_Analytics_List_Table class here or include if in separate file
        // For this task, defining inline for brevity, but ideally in its own file.
        class RBSE_Users_Analytics_List_Table extends WP_List_Table {
            public function __construct() {
                parent::__construct( array(
                    'singular' => __( 'User Analytics', 'role-based-split-engine' ),
                    'plural'   => __( 'Users Analytics', 'role-based-split-engine' ),
                    'ajax'     => false
                ) );
            }
            public function get_columns() {
                return array(
                    'user_display_name' => __( 'User', 'role-based-split-engine' ),
                    'total_amount_received' => __( 'Total Amount Received', 'role-based-split-engine' ),
                    'split_count' => __( 'Number of Splits', 'role-based-split-engine' ),
                    'last_split_date' => __( 'Last Split Date', 'role-based-split-engine' )
                );
            }
            public function column_default( $item, $column_name ) {
                return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : print_r( $item, true );
            }
            public function column_user_display_name($item) {
                return sprintf( '<a href="%s">%s</a>', esc_url(get_edit_user_link($item['user_id'])), esc_html($item['user_display_name']) );
            }
             public function column_total_amount_received($item) {
                // Assuming a primary currency or needing logic to handle multiple currencies if summarized this way
                return esc_html(number_format_i18n($item['total_amount_received'], 2) . ' (' . $item['currency'] . ')'); 
            }
            public function prepare_items() {
                global $wpdb;
                $table_recipients = $wpdb->prefix . 'rbse_split_event_recipients';
                $table_events = $wpdb->prefix . 'rbse_split_events';

                $columns = $this->get_columns();
                $hidden = array();
                $sortable = $this->get_sortable_columns();
                $this->_column_headers = array( $columns, $hidden, $sortable );

                $per_page = 10;
                $current_page = $this->get_pagenum();
                $offset = ( $current_page - 1 ) * $per_page;

                $orderby = ( ! empty( $_REQUEST['orderby'] ) && array_key_exists( $_REQUEST['orderby'], $this->get_sortable_columns()[0] ) ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'total_amount_received';
                $order = ( ! empty( $_REQUEST['order'] ) && in_array( strtoupper( $_REQUEST['order'] ), array( 'ASC', 'DESC' ) ) ) ? strtoupper( $_REQUEST['order'] ) : 'DESC';
                
                // For simplicity, this query assumes one primary currency for user total. 
                // A more complex setup would be needed for multi-currency totals per user.
                // This example will pick the currency from the latest event for that user.
                $query = $wpdb->prepare(
                    "SELECT r.user_id, u.display_name as user_display_name, 
                            SUM(r.individual_share_amount) as total_amount_received, 
                            COUNT(r.recipient_record_id) as split_count, 
                            MAX(e.event_timestamp) as last_split_date,
                            (SELECT e_curr.currency FROM $table_events e_curr JOIN $table_recipients r_curr ON e_curr.split_event_id = r_curr.split_event_id WHERE r_curr.user_id = r.user_id ORDER BY e_curr.event_timestamp DESC LIMIT 1) as currency
                     FROM $table_recipients r 
                     JOIN {$wpdb->users} u ON r.user_id = u.ID 
                     JOIN $table_events e ON r.split_event_id = e.split_event_id
                     GROUP BY r.user_id, u.display_name
                     ORDER BY %s %s
                     LIMIT %d OFFSET %d",
                    $orderby, $order, $per_page, $offset
                );
                $this->items = $wpdb->get_results( $query, ARRAY_A );

                $total_items = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_recipients");
                $this->set_pagination_args( array(
                    'total_items' => $total_items,
                    'per_page'    => $per_page,
                    'total_pages' => ceil( $total_items / $per_page )
                ) );
            }
             public function get_sortable_columns() {
                return array(
                    'user_display_name' => array('user_display_name', false),
                    'total_amount_received' => array('total_amount_received', true), // true means it's already sorted by this column
                    'split_count' => array('split_count', false),
                    'last_split_date' => array('last_split_date', false),
                );
            }
        } // End class RBSE_Users_Analytics_List_Table
    } // End if class exists check
    $users_list_table = new RBSE_Users_Analytics_List_Table();
    $users_list_table->prepare_items();


    ?>
    <div class="wrap rbse-wrap">
        <h1><?php esc_html_e( 'Split Analytics Dashboard', 'role-based-split-engine' ); ?></h1>

        <div id="dashboard-widgets-wrap">
            <div id="dashboard-widgets" class="metabox-holder columns-2">
                
                <div id="postbox-container-1" class="postbox-container">
                    <div class="meta-box-sortables">
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Overview', 'role-based-split-engine' ); ?></span></h2>
                            <div class="inside">
                                <p><strong><?php esc_html_e( 'Total Split Events:', 'role-based-split-engine' ); ?></strong> <?php echo esc_html( number_format_i18n( $total_events ) ); ?></p>
                                <?php if ( ! empty( $total_amount_processed_by_currency ) ) : ?>
                                    <h4><?php esc_html_e( 'Total Amount Processed (by currency):', 'role-based-split-engine' ); ?></h4>
                                    <ul>
                                        <?php foreach ( $total_amount_processed_by_currency as $currency_code => $data ) : ?>
                                            <li><?php echo esc_html( $currency_code ); ?>: <?php echo esc_html( number_format_i18n( $data->total, ($currency_code === 'POINTS' ? 0 : 2) ) ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?> <p><?php esc_html_e( 'No amounts processed yet.', 'role-based-split-engine' ); ?></p> <?php endif; ?>
                            </div>
                        </div>
                         <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Recipient Share Summary', 'role-based-split-engine' ); ?></span></h2>
                            <div class="inside">
                                <?php if ( ! empty( $recipient_share_summary ) ) : ?>
                                    <?php foreach ( $recipient_share_summary as $currency_code => $data ) : ?>
                                        <h4><?php printf( esc_html__( 'Currency: %s', 'role-based-split-engine' ), esc_html( $currency_code ) ); ?></h4>
                                        <ul>
                                            <li> <?php esc_html_e( 'Total Distributed to Recipients:', 'role-based-split-engine' ); ?> <?php echo esc_html( number_format_i18n( $data->total_distributed, ($currency_code === 'POINTS' ? 0 : 2) ) ); ?> </li>
                                            <li> <?php esc_html_e( 'Actually Distributed (Wallet Set):', 'role-based-split-engine' ); ?> <?php echo esc_html( number_format_i18n( $data->distributed_to_wallets, ($currency_code === 'POINTS' ? 0 : 2) ) ); ?> </li>
                                            <li> <?php esc_html_e( 'Pending (Wallet Missing):', 'role-based-split-engine' ); ?> <strong style="color: red;"><?php echo esc_html( number_format_i18n( $data->pending_missing_wallets, ($currency_code === 'POINTS' ? 0 : 2) ) ); ?></strong> </li>
                                        </ul>
                                    <?php endforeach; ?>
                                <?php else: ?> <p><?php esc_html_e( 'No recipient data available yet.', 'role-based-split-engine' ); ?></p> <?php endif; ?>
                            </div>
                        </div>
                         <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Role-Based Analytics (USD)', 'role-based-split-engine' ); ?></span></h2>
                            <div class="inside">
                                <canvas id="rbseRoleAnalyticsChart" width="400" height="200"></canvas>
                                <?php if ( ! empty( $role_analytics_data ) ) : ?>
                                    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                                        <thead><tr><th><?php esc_html_e('Role', 'role-based-split-engine'); ?></th><th><?php esc_html_e('Total Distributed', 'role-based-split-engine'); ?></th><th><?php esc_html_e('# Recipients', 'role-based-split-engine'); ?></th><th><?php esc_html_e('Avg/User', 'role-based-split-engine'); ?></th></tr></thead>
                                        <tbody>
                                        <?php foreach ($role_analytics_data as $row): ?>
                                            <tr>
                                                <td><?php echo esc_html(translate_user_role($row->user_role_at_time_of_split)); ?></td>
                                                <td><?php echo esc_html(number_format_i18n($row->total_amount_for_role, 2)); ?></td>
                                                <td><?php echo esc_html(number_format_i18n($row->unique_recipients_in_role)); ?></td>
                                                <td><?php echo esc_html($row->unique_recipients_in_role > 0 ? number_format_i18n($row->total_amount_for_role / $row->unique_recipients_in_role, 2) : 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?> <p><?php esc_html_e('No data available for role-based analytics.', 'role-based-split-engine'); ?></p> <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="postbox-container-2" class="postbox-container">
                     <div class="meta-box-sortables">
                        <div class="postbox">
                             <h2 class="hndle"><span><?php esc_html_e( 'User-Based Analytics', 'role-based-split-engine' ); ?></span></h2>
                            <div class="inside">
                                <form method="get">
                                    <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                                    <?php $users_list_table->display(); ?>
                                </form>
                            </div>
                        </div>
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Recent Split Events', 'role-based-split-engine' ); ?></span></h2>
                            <div class="inside">
                                <?php if ( ! empty( $recent_events ) ) : ?>
                                    <table class="wp-list-table widefat fixed striped">
                                        <thead><tr><th>ID</th><th><?php esc_html_e('Total Amt', 'role-based-split-engine'); ?></th><th><?php esc_html_e('Currency', 'role-based-split-engine'); ?></th><th><?php esc_html_e('Status', 'role-based-split-engine'); ?></th><th><?php esc_html_e('Date', 'role-based-split-engine'); ?></th><th><?php esc_html_e('Actions', 'role-based-split-engine'); ?></th></tr></thead>
                                        <tbody>
                                            <?php foreach ( $recent_events as $event ) : ?>
                                                <tr>
                                                    <td><?php echo esc_html( $event->split_event_id ); ?></td>
                                                    <td><?php echo esc_html( number_format_i18n( $event->total_amount, ($event->currency === 'POINTS' ? 0 : 2) ) ); ?></td>
                                                    <td><?php echo esc_html( $event->currency ); ?></td>
                                                    <td><?php echo esc_html( $event->processing_status ); ?></td>
                                                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $event->event_timestamp ) ) ); ?></td>
                                                    <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=rbse-event-detail&event_id=' . $event->split_event_id ) ); ?>" class="button button-small"><?php esc_html_e( 'View Details', 'role-based-split-engine' );?></a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else : ?> <p><?php esc_html_e( 'No split events found.', 'role-based-split-engine' ); ?></p> <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Handles the [my_split_history] shortcode.
 *
 * Displays a table of the logged-in user's split history, including
 * event details, their role at the time, share amount, and wallet status.
 *
 * @since 0.1.0
 * @param array $atts Shortcode attributes (not used in this version).
 * @return string HTML output for the shortcode.
 */
function rbse_my_split_history_shortcode_handler( $atts ) {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'You must be logged in to view your split history.', 'role-based-split-engine' ) . '</p>';
    }

    $current_user_id = get_current_user_id();
    global $wpdb;
    $table_recipients = $wpdb->prefix . 'rbse_split_event_recipients';
    $table_events     = $wpdb->prefix . 'rbse_split_events';

    $recipient_history = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.individual_share_amount, r.user_role_at_time_of_split, r.is_missing_wallet, r.assigned_wallet_address,
                e.event_timestamp, e.currency, e.total_amount as event_total_amount, e.notes as event_notes, e.split_event_id
         FROM $table_recipients r
         JOIN $table_events e ON r.split_event_id = e.split_event_id
         WHERE r.user_id = %d
         ORDER BY e.event_timestamp DESC",
        $current_user_id
    ) );

    if ( empty( $recipient_history ) ) {
        return '<p>' . esc_html__( 'You do not have any split history recorded yet.', 'role-based-split-engine' ) . '</p>';
    }

    $output = '<div class="rbse-my-split-history">';
    $output .= '<h3>' . esc_html__( 'My Split History', 'role-based-split-engine' ) . '</h3>';
    $output .= '<table class="rbse-history-table">';
    $output .= '<thead><tr>';
    $output .= '<th>' . esc_html__( 'Event Date', 'role-based-split-engine' ) . '</th>';
    $output .= '<th>' . esc_html__( 'Event ID', 'role-based-split-engine' ) . '</th>';
    $output .= '<th>' . esc_html__( 'My Share', 'role-based-split-engine' ) . '</th>';
    $output .= '<th>' . esc_html__( 'Currency', 'role-based-split-engine' ) . '</th>';
    $output .= '<th>' . esc_html__( 'My Role', 'role-based-split-engine' ) . '</th>';
    $output .= '<th>' . esc_html__( 'Wallet Address Used', 'role-based-split-engine' ) . '</th>';
    $output .= '<th>' . esc_html__( 'Status', 'role-based-split-engine' ) . '</th>';
    $output .= '</tr></thead>';
    $output .= '<tbody>';

    foreach ( $recipient_history as $record ) {
        $output .= '<tr>';
        $output .= '<td>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $record->event_timestamp ) ) ) . '</td>';
        $output .= '<td>' . esc_html( $record->split_event_id ) . '</td>';
        $output .= '<td>' . esc_html( number_format_i18n( $record->individual_share_amount, ($record->currency === 'POINTS' ? 0 : 2) ) ) . '</td>';
        $output .= '<td>' . esc_html( $record->currency ) . '</td>';
        $output .= '<td>' . esc_html( translate_user_role($record->user_role_at_time_of_split) ) . '</td>';
        
        if ( $record->is_missing_wallet ) {
            $output .= '<td><em>' . esc_html__( 'Wallet missing at time of split', 'role-based-split-engine' ) . '</em></td>';
            $output .= '<td><span style="color:orange;">' . esc_html__( 'Pending - Wallet Missing', 'role-based-split-engine' ) . '</span></td>';
        } else {
            $output .= '<td>' . esc_html( $record->assigned_wallet_address ) . '</td>';
            $output .= '<td><span style="color:green;">' . esc_html__( 'Recorded', 'role-based-split-engine' ) . '</span></td>';
        }
        $output .= '</tr>';
    }

    $output .= '</tbody></table>';
    $output .= '</div>'; // .rbse-my-split-history

    // Basic styling for the table (can be moved to a proper CSS file)
    $output .= "<style>
        .rbse-my-split-history table.rbse-history-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .rbse-my-split-history table.rbse-history-table th, .rbse-my-split-history table.rbse-history-table td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        .rbse-my-split-history table.rbse-history-table th { background-color: #f4f4f4; }
        .rbse-my-split-history h3 { margin-bottom: 10px; }
    </style>";

    return $output;
}
add_shortcode( 'my_split_history', 'rbse_my_split_history_shortcode_handler' );

/**
 * Initializes settings for the Role-Based Split Engine plugin.
 * Registers settings, sections, and fields for the settings page.
 *
 * @since 0.1.0
 */
function rbse_settings_init() {
    // Register the main setting group
    register_setting( 'rbse_settings_group', 'rbse_settings', 'rbse_sanitize_settings' );

    // Section for General Settings
    add_settings_section(
        'rbse_general_settings_section',
        __( 'General Settings', 'role-based-split-engine' ),
        'rbse_general_settings_section_callback',
        'rbse-settings-page'
    );

    // Field: Custom User Meta Key for Wallet Address
    add_settings_field(
        'rbse_wallet_meta_key_field',
        __( 'Wallet Address Meta Key', 'role-based-split-engine' ),
        'rbse_wallet_meta_key_field_render',
        'rbse-settings-page',
        'rbse_general_settings_section'
    );

    // Section for Processing Policies
    add_settings_section(
        'rbse_policy_settings_section',
        __( 'Processing Policies', 'role-based-split-engine' ),
        'rbse_policy_settings_section_callback',
        'rbse-settings-page'
    );
    
    // Field: Policy for Missing Wallets
    add_settings_field(
        'rbse_missing_wallet_policy_field',
        __( 'Policy for Missing Wallets', 'role-based-split-engine' ),
        'rbse_missing_wallet_policy_field_render',
        'rbse-settings-page',
        'rbse_policy_settings_section'
    );

    // Field: Policy for Empty Roles
    add_settings_field(
        'rbse_empty_role_policy_field',
        __( 'Policy for Empty Roles', 'role-based-split-engine' ),
        'rbse_empty_role_policy_field_render',
        'rbse-settings-page',
        'rbse_policy_settings_section'
    );
}
add_action( 'admin_init', 'rbse_settings_init' );

/** Callbacks for settings sections */
function rbse_general_settings_section_callback() {
    echo '<p>' . esc_html__( 'Configure general settings for the plugin.', 'role-based-split-engine' ) . '</p>';
}
function rbse_policy_settings_section_callback() {
    echo '<p>' . esc_html__( 'Define how the plugin should handle specific scenarios during split processing.', 'role-based-split-engine' ) . '</p>';
}

/** Render functions for settings fields */
function rbse_wallet_meta_key_field_render() {
    $options = get_option( 'rbse_settings' );
    $value = isset( $options['wallet_meta_key'] ) ? $options['wallet_meta_key'] : 'user_wallet_address';
    ?>
    <input type='text' name='rbse_settings[wallet_meta_key]' value='<?php echo esc_attr( $value ); ?>' class="regular-text">
    <p class="description"><?php esc_html_e( "Enter the user meta key used to store users' wallet addresses. Default: user_wallet_address", 'role-based-split-engine' ); ?></p>
    <?php
}

function rbse_missing_wallet_policy_field_render() {
    $options = get_option( 'rbse_settings' );
    $value = isset( $options['missing_wallet_policy'] ) ? $options['missing_wallet_policy'] : 'record_anyway';
    ?>
    <select name='rbse_settings[missing_wallet_policy]'>
        <option value='record_anyway' <?php selected( $value, 'record_anyway' ); ?>><?php esc_html_e( 'Record Anyway (flag as missing)', 'role-based-split-engine' ); ?></option>
        <option value='hold_share' <?php selected( $value, 'hold_share' ); ?>><?php esc_html_e( 'Hold Share (mark as pending distribution - Future)', 'role-based-split-engine' ); ?></option>
        <option value='ignore_user' <?php selected( $value, 'ignore_user' ); ?>><?php esc_html_e( 'Ignore User (their share is forfeited/redispersed based on role policy - Future)', 'role-based-split-engine' ); ?></option>
    </select>
    <p class="description"><?php esc_html_e( "How to handle a recipient whose wallet address is not set.", 'role-based-split-engine' ); ?></p>
    <?php
}

function rbse_empty_role_policy_field_render() {
    $options = get_option( 'rbse_settings' );
    $value = isset( $options['empty_role_policy'] ) ? $options['empty_role_policy'] : 'forfeit_share';
    ?>
    <select name='rbse_settings[empty_role_policy]'>
        <option value='forfeit_share' <?php selected( $value, 'forfeit_share' ); ?>><?php esc_html_e( "Forfeit Share (role's portion is not distributed)", 'role-based-split-engine' ); ?></option>
        <option value='disperse_among_others' <?php selected( $value, 'disperse_among_others' ); ?>><?php esc_html_e( 'Disperse Among Others (equally or proportionally - Future)', 'role-based-split-engine' ); ?></option>
    </select>
    <p class="description"><?php esc_html_e( "How to handle a role in the split that has no users.", 'role-based-split-engine' ); ?></p>
    <?php
}

/**
 * Sanitizes the settings input.
 *
 * @since 0.1.0
 * @param array $input The input array from the settings form.
 * @return array The sanitized array.
 */
function rbse_sanitize_settings( $input ) {
    $sanitized_input = array();
    $default_settings = array(
        'wallet_meta_key' => 'user_wallet_address',
        'missing_wallet_policy' => 'record_anyway', // Changed from record_flag for clarity
        'empty_role_policy' => 'forfeit_share',
    );

    if ( isset( $input['wallet_meta_key'] ) ) {
        $sanitized_input['wallet_meta_key'] = sanitize_key( $input['wallet_meta_key'] );
        if(empty($sanitized_input['wallet_meta_key'])) {
            $sanitized_input['wallet_meta_key'] = $default_settings['wallet_meta_key'];
        }
    } else {
        $sanitized_input['wallet_meta_key'] = $default_settings['wallet_meta_key'];
    }

    $allowed_missing_wallet_policies = array( 'record_anyway', 'hold_share', 'ignore_user' ); // 'record_anyway' is the new key for 'record_flag'
    if ( isset( $input['missing_wallet_policy'] ) && in_array( $input['missing_wallet_policy'], $allowed_missing_wallet_policies, true ) ) {
        $sanitized_input['missing_wallet_policy'] = $input['missing_wallet_policy'];
    } else {
        $sanitized_input['missing_wallet_policy'] = $default_settings['missing_wallet_policy'];
    }

    $allowed_empty_role_policies = array( 'forfeit_share', 'disperse_among_others' ); // Keep as is
    if ( isset( $input['empty_role_policy'] ) && in_array( $input['empty_role_policy'], $allowed_empty_role_policies, true ) ) {
        $sanitized_input['empty_role_policy'] = $input['empty_role_policy'];
    } else {
        $sanitized_input['empty_role_policy'] = $default_settings['empty_role_policy'];
    }
    
    // Add admin notices for settings saved
    add_settings_error(
        'rbse_settings_notices', 
        'settings_saved', 
        __( 'Settings saved.', 'role-based-split-engine' ), 
        'updated'
    );

    return $sanitized_input;
}


/**
 * Renders the HTML for the plugin's settings page.
 *
 * @since 0.1.0
 */
function rbse_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'role-based-split-engine' ) );
    }
    ?>
    <div class="wrap rbse-wrap">
        <h1><?php esc_html_e( 'Role-Based Split Engine Settings', 'role-based-split-engine' ); ?></h1>
        <?php settings_errors( 'rbse_settings_notices' ); ?>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'rbse_settings_group' ); // Output nonce, action, and option_page fields for 'rbse_settings_group'.
            do_settings_sections( 'rbse-settings-page' ); // Prints out all settings sections added to a particular settings page.
            submit_button( __( 'Save Settings', 'role-based-split-engine' ) );
            ?>
        </form>
    </div>
    <?php
}

?>

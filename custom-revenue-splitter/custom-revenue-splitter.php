<?php
/**
 * Plugin Name: Custom Revenue Splitter
 * Plugin URI: https://example.com/custom-revenue-splitter
 * Description: A custom plugin to split revenue between authors and the platform based on user roles.
 * Version: 1.0.0
 * Author: Your Name (Replace with actual name or WordPress.org username)
 * Author URI: https://example.com (Replace with actual URI)
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: custom-revenue-splitter
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die( 'No direct script access allowed!' ); // Added a message to die.
}

// Define plugin path constant for easy access to the plugin directory.
define( 'CUSTOM_REVENUE_SPLITTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Loads the plugin text domain for translation.
 *
 * This function is hooked to `plugins_loaded` to ensure translations are
 * available as early as possible.
 *
 * @since 1.0.0
 */
function custom_revenue_splitter_load_textdomain() {
    load_plugin_textdomain(
        'custom-revenue-splitter', // Unique slug for this plugin.
        false,                    // Deprecated argument.
        dirname( plugin_basename( __FILE__ ) ) . '/languages/' // Path to .mo files.
    );
}
add_action( 'plugins_loaded', 'custom_revenue_splitter_load_textdomain' );

/**
 * Activation hook callback.
 *
 * Sets up default options when the plugin is activated.
 * Specifically, it populates an initial set of revenue split percentages
 * for all existing user roles.
 *
 * @since 1.0.0
 */
function custom_revenue_splitter_activate() {
    // Get all WordPress user roles.
    global $wp_roles;
    if ( ! isset( $wp_roles ) ) {
        // Ensure WP_Roles is initialized if not already.
        $wp_roles = new WP_Roles();
    }
    $all_roles = $wp_roles->get_names(); // Get role slugs and their display names.

    // Define default revenue split percentages for common roles.
    $default_splits = array(
        'administrator' => 10,
        'editor'        => 8,
        'author'        => 5,
        'contributor'   => 2,
        'subscriber'    => 1, // Subscribers typically might not get a share, but included for completeness.
    );

    $role_splits = array();

    // Assign splits to all existing roles, providing a fallback for roles not in our default list.
    foreach ( $all_roles as $role_slug => $role_name ) {
        if ( isset( $default_splits[ $role_slug ] ) ) {
            $role_splits[ $role_slug ] = $default_splits[ $role_slug ];
        } else {
            // For any other roles (e.g., custom roles from other plugins), assign a default of 1%.
            $role_splits[ $role_slug ] = 1;
        }
    }

    // Store the array as a single WordPress option.
    // `add_option` ensures this is only set if it doesn't already exist,
    // preserving settings if the plugin is deactivated and reactivated.
    add_option( 'custom_revenue_splitter_role_splits', $role_splits );
}

/**
 * Deactivation hook callback.
 *
 * Cleans up plugin options when the plugin is deactivated.
 * This helps keep the WordPress database clean.
 *
 * @since 1.0.0
 */
function custom_revenue_splitter_deactivate() {
    // Delete the WordPress option storing the role splits.
    delete_option( 'custom_revenue_splitter_role_splits' );
}

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, 'custom_revenue_splitter_activate' );
register_deactivation_hook( __FILE__, 'custom_revenue_splitter_deactivate' );

/**
 * Adds the admin menu item for the plugin's settings page.
 *
 * This function creates a submenu page under the "Settings" top-level menu.
 *
 * @since 1.0.0
 */
function custom_revenue_splitter_admin_menu() {
    add_options_page(
        __( 'Revenue Split Settings', 'custom-revenue-splitter' ), // Page title.
        __( 'Revenue Split', 'custom-revenue-splitter' ),        // Menu title.
        'manage_options',                                       // Capability required to access.
        'custom-revenue-splitter-settings',                     // Menu slug (unique identifier).
        'custom_revenue_splitter_settings_page_html'            // Callback function to render the page HTML.
    );
}
add_action( 'admin_menu', 'custom_revenue_splitter_admin_menu' );

/**
 * Renders the HTML structure for the plugin's settings page.
 *
 * This function outputs the main form and calls WordPress Settings API functions
 * to render the actual settings fields and sections.
 *
 * @since 1.0.0
 */
function custom_revenue_splitter_settings_page_html() {
    // Check if the current user has the 'manage_options' capability.
    if ( ! current_user_can( 'manage_options' ) ) {
        // If not, display an error message and die.
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'custom-revenue-splitter' ) );
        // return; // Not strictly necessary after wp_die, but good practice.
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form method="post" action="options.php">
            <?php
            // Output hidden fields for the WordPress settings API (nonce, action, option_page).
            settings_fields( 'custom_revenue_splitter_settings_group' );
            // Output the settings sections and their fields.
            do_settings_sections( 'custom_revenue_splitter_settings_page' );
            // Output the submit button.
            submit_button( __( 'Save Settings', 'custom-revenue-splitter' ) );
            ?>
        </form>
    </div>
    <?php
}

/**
 * Initializes the WordPress Settings API for the plugin.
 *
 * This function registers the plugin's settings, adds sections, and fields
 * to the settings page. It's hooked to `admin_init`.
 *
 * @since 1.0.0
 */
function custom_revenue_splitter_settings_init() {
    // Register the main setting group and the option name.
    // 'custom_revenue_splitter_role_splits' will store an array of role_slug => percentage.
    register_setting(
        'custom_revenue_splitter_settings_group',      // Option group (must match settings_fields call).
        'custom_revenue_splitter_role_splits',         // Option name.
        'custom_revenue_splitter_sanitize_settings'    // Sanitization callback.
    );

    // Add a settings section to group related fields.
    add_settings_section(
        'crs_roles_split_section',                                 // Section ID.
        __( 'Define Revenue Split Percentages per Role', 'custom-revenue-splitter' ), // Section title (translatable).
        'crs_roles_split_section_callback',                        // Callback to render content at the top of the section.
        'custom_revenue_splitter_settings_page'                    // Page slug where this section will be displayed.
    );

    // Retrieve all editable WordPress roles (roles that can be assigned to users).
    $roles = get_editable_roles();
    // Get currently saved options to pass to field rendering, avoiding multiple get_option calls.
    $options = get_option( 'custom_revenue_splitter_role_splits', array() );

    // Dynamically create a settings field for each editable role.
    foreach ( $roles as $role_slug => $role_details ) {
        add_settings_field(
            'crs_role_split_' . $role_slug, // Unique ID for the field.
            // Translatable title, e.g., "Administrator (%)". Role name is from WP core, use translate_user_role for its translation.
            sprintf( esc_html__( '%s (%%)', 'custom-revenue-splitter' ), esc_html( translate_user_role( $role_details['name'] ) ) ),
            'crs_render_role_split_field',   // Callback to render the input field.
            'custom_revenue_splitter_settings_page', // Page slug.
            'crs_roles_split_section',       // Section ID where this field belongs.
            array(                           // Arguments passed to the render callback.
                'role_slug' => $role_slug,
                'options'   => $options, // Pass current options to avoid repeated get_option calls
            )
        );
    }
}
add_action( 'admin_init', 'custom_revenue_splitter_settings_init' );

/**
 * Callback function to render introductory text for the roles split section.
 *
 * @since 1.0.0
 */
function crs_roles_split_section_callback() {
    echo '<p>' . esc_html__( 'Set the percentage of revenue that users with each role will receive. This percentage will be used to calculate their share from an order total.', 'custom-revenue-splitter' ) . '</p>';
}

/**
 * Renders the input field (number type) for a role's split percentage.
 *
 * @since 1.0.0
 * @param array $args {
 *     An array of arguments passed from `add_settings_field`.
 *     @type string $role_slug The slug of the role for this field.
 *     @type array  $options   The current saved options for all roles.
 * }
 */
function crs_render_role_split_field( $args ) {
    $role_slug = isset( $args['role_slug'] ) ? $args['role_slug'] : '';
    // Retrieve options passed via args.
    $options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : get_option( 'custom_revenue_splitter_role_splits', array() );
    // Get the current value for this role, defaulting to 0 if not set.
    $value = isset( $options[ $role_slug ] ) ? intval( $options[ $role_slug ] ) : 0;

    if ( ! empty( $role_slug ) ) {
        // Output the number input field with class 'small-text' and a '%' symbol after it.
        printf(
            '<input type="number" name="custom_revenue_splitter_role_splits[%s]" value="%d" min="0" max="100" class="small-text" /> %%',
            esc_attr( $role_slug ), // Sanitize role slug for attribute.
            esc_attr( $value )      // Sanitize value for attribute.
        );
    }
}

/**
 * Sanitizes the settings input received from the settings page.
 *
 * Ensures that percentages are integers between 0 and 100.
 *
 * @since 1.0.0
 * @param array $input The raw input array from the settings form.
 * @return array The sanitized array of role slugs and their percentages.
 */
function custom_revenue_splitter_sanitize_settings( $input ) {
    $sanitized_input = array();
    if ( is_array( $input ) ) {
        foreach ( $input as $role_slug => $percentage ) {
            // Ensure the role slug is a valid key (although WordPress roles are usually safe).
            $clean_role_slug = sanitize_key( $role_slug );
            // Sanitize and validate the percentage value.
            $percentage = intval( $percentage ); // Convert to integer.
            if ( $percentage < 0 ) {
                $percentage = 0; // Clamp to minimum 0.
            } elseif ( $percentage > 100 ) {
                $percentage = 100; // Clamp to maximum 100.
            }
            $sanitized_input[ $clean_role_slug ] = $percentage;
        }
    }
    return $sanitized_input;
}

/**
 * Calculates the revenue share for a specific user based on their role and the order amount.
 *
 * If a user has multiple roles, the role with the highest defined percentage in the
 * plugin's settings will be used to calculate their share. If none of the user's roles
 * have a defined split percentage, or if the user has no roles, their share will be 0.0.
 * This function is intended for developers to integrate into their payment processing logic.
 *
 * @since 1.0.0
 * @param float|int $order_amount The total amount of the order or revenue. Must be positive.
 * @param int       $user_id      The ID of the user for whom to calculate the share.
 * @return float The calculated share for the user. Returns 0.0 if the user is not found,
 *               has no roles, no applicable roles have a defined split, or order amount is zero or negative.
 */
function custom_revenue_splitter_calculate_share( $order_amount, $user_id ) {
    // Ensure order amount is a positive number.
    $order_amount = floatval( $order_amount );
    if ( $order_amount <= 0 ) {
        return 0.0; // No share for zero or negative order amounts.
    }

    // Get user data for the given user ID.
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        // Optional: Log that the user was not found.
        // error_log("Custom Revenue Splitter: User ID {$user_id} not found for share calculation.");
        return 0.0; // User not found.
    }

    // Get user roles.
    $user_roles = $user->roles;
    if ( empty( $user_roles ) ) {
        // Optional: Log that the user has no roles.
        // error_log("Custom Revenue Splitter: User ID {$user_id} has no roles for share calculation.");
        return 0.0; // User has no roles.
    }

    // Fetch the saved revenue split percentages from WordPress options.
    $role_splits = get_option( 'custom_revenue_splitter_role_splits', array() );
    if ( empty( $role_splits ) ) {
        // Optional: Log that no split settings are defined.
        // error_log("Custom Revenue Splitter: No role split percentages are defined in settings.");
        return 0.0; // No split settings defined in the plugin.
    }

    $highest_percentage = 0.0;

    // Iterate through the user's roles to find the highest applicable percentage.
    // This handles users who might have multiple roles with different split settings.
    foreach ( $user_roles as $role_slug ) {
        if ( isset( $role_splits[ $role_slug ] ) ) {
            $current_percentage = floatval( $role_splits[ $role_slug ] );
            if ( $current_percentage > $highest_percentage ) {
                $highest_percentage = $current_percentage;
            }
        }
    }

    // If no applicable split percentage was found for the user's roles, or if the highest is 0.
    if ( $highest_percentage <= 0 ) {
        return 0.0;
    }

    // Calculate the user's share based on the highest percentage found.
    $user_share = ( $order_amount * $highest_percentage ) / 100.0;

    return floatval( $user_share );
}

/**
 * Demonstrates the revenue share calculation on user login for illustrative purposes.
 *
 * This function is hooked to `wp_login`. It logs the potential share for a dummy
 * order amount and, if an admin is viewing, sets a transient to display an admin notice.
 * This is primarily for demonstration and debugging.
 *
 * @since 1.0.0
 * @param string  $user_login The user's login name.
 * @param WP_User $user       The WP_User object of the logged-in user.
 */
function custom_revenue_splitter_demonstrate_share_calculation( $user_login, $user ) {
    // Ensure we have a valid WP_User object.
    if ( ! is_a( $user, 'WP_User' ) ) {
        return;
    }

    $user_id = $user->ID;
    $dummy_order_amount = 100.00; // Example order amount for demonstration.
    $calculated_share = custom_revenue_splitter_calculate_share( $dummy_order_amount, $user_id );

    // Log the demonstration details (intended for developers/admins).
    // Note: Ensure the log directory is writable by the web server for error_log to function.
    error_log(
        sprintf(
            // This string is not translated as it's for developer logs.
            'Custom Revenue Splitter Demo: User %s (ID: %d) logged in. Potential share for a $%.2f order: $%.2f',
            esc_html( $user_login ),
            intval( $user_id ),
            floatval( $dummy_order_amount ),
            floatval( $calculated_share )
        )
    );

    // Admin Notice (Conditional): Show a notice to administrators.
    // This checks if the *currently viewing* user (not necessarily $user) can manage options.
    if ( current_user_can( 'manage_options' ) ) {
        $message = sprintf(
            // This string is translatable as it's displayed to admins.
            esc_html__( "Custom Revenue Splitter Demo: User '%s' (ID: %d) just logged in. For a hypothetical $%.2f order, their calculated revenue share would be: $%.2f. (This is a demonstration message from the Custom Revenue Splitter plugin.)", 'custom-revenue-splitter' ),
            esc_html( $user_login ),
            intval( $user_id ),
            floatval( $dummy_order_amount ),
            number_format( $calculated_share, 2 ) // Format currency for display.
        );
        // Set a transient to display the message on the next admin page load. Expires in 60 seconds.
        set_transient( 'crs_demo_admin_notice', $message, 60 );
    }
}
add_action( 'wp_login', 'custom_revenue_splitter_demonstrate_share_calculation', 10, 2 );

/**
 * Displays the admin notice for the revenue share calculation demonstration.
 *
 * This function checks for a transient set by `custom_revenue_splitter_demonstrate_share_calculation()`
 * and, if found, displays it as an admin notice. The transient is then deleted to
 * ensure the notice is shown only once per login event that sets it.
 *
 * @since 1.0.0
 */
function custom_revenue_splitter_show_demo_admin_notice() {
    // Only proceed if in the admin area.
    if ( ! is_admin() ) {
        return;
    }

    // Attempt to retrieve the message from the transient.
    $message = get_transient( 'crs_demo_admin_notice' );

    if ( $message ) {
        // Display the notice. The message was already translated and escaped when set in the transient.
        printf(
            '<div class="notice notice-info is-dismissible"><p>%s</p></div>',
            $message // Message is pre-escaped and translated.
        );
        // Delete the transient so the notice doesn't show up repeatedly.
        delete_transient( 'crs_demo_admin_notice' );
    }
}
add_action( 'admin_notices', 'custom_revenue_splitter_show_demo_admin_notice' );


// This is the main plugin file. No code should come after this.
?>

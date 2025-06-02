<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://example.com/plugins/my-webapp/
 * @since      1.0.0
 *
 * @package    My_WebApp
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// WordPress loads the plugin file before uninstall.php, so functions defined in it are available
// However, it's safer and more common to ensure uninstall.php is self-sufficient
// or only relies on WordPress core functions.
// Let's define a function here or call remove_role directly.

if ( ! function_exists( 'my_webapp_uninstall_remove_roles' ) ) {
    /**
     * Remove custom roles.
     */
    function my_webapp_uninstall_remove_roles() {
        // Ensure WordPress role functions are available
        if ( ! function_exists( 'remove_role' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        remove_role( 'my_webapp_admin' );
        remove_role( 'my_webapp_moderator' );
        remove_role( 'my_webapp_instructor' );
        remove_role( 'my_webapp_user' );
    }
}

my_webapp_uninstall_remove_roles();

?>

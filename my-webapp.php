<?php
/**
 * Plugin Name:       My WebApp
 * Plugin URI:        https://example.com/plugins/my-webapp/
 * Description:       A plugin to register custom user roles and capabilities for My WebApp.
 * Version:           1.0.0
 * Author:            Jules AI Agent
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       my-webapp
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * The code that runs during plugin activation.
 */
function activate_my_webapp() {
    // Get the administrator role.
    $admin_role = get_role( 'administrator' );
    $admin_capabilities = array();

    if ( $admin_role ) {
        $admin_capabilities = $admin_role->capabilities;
    }

    // Define capabilities for My WebApp Admin role
    // Start with all capabilities of a standard WordPress administrator
    $my_webapp_admin_caps = $admin_capabilities;

    // Add core WordPress capabilities if somehow not inherited (should be)
    $my_webapp_admin_caps['manage_options'] = true;
    $my_webapp_admin_caps['edit_users'] = true;
    $my_webapp_admin_caps['edit_posts'] = true; // General content editing

    // Add custom capabilities
    $my_webapp_admin_caps['manage_projects'] = true;
    $my_webapp_admin_caps['manage_accounting'] = true;

    // Add other common admin capabilities that might be useful
    $my_webapp_admin_caps['list_users'] = true;
    $my_webapp_admin_caps['create_users'] = true;
    $my_webapp_admin_caps['remove_users'] = true;
    $my_webapp_admin_caps['delete_users'] = true;
    $my_webapp_admin_caps['promote_users'] = true;
    $my_webapp_admin_caps['edit_theme_options'] = true;
    $my_webapp_admin_caps['export'] = true;
    $my_webapp_admin_caps['import'] = true;
    $my_webapp_admin_caps['manage_categories'] = true; // If using posts for projects or tasks
    $my_webapp_admin_caps['moderate_comments'] = true;
    $my_webapp_admin_caps['unfiltered_html'] = true; // Potentially dangerous, but admins often have it
    $my_webapp_admin_caps['upload_files'] = true;
    $my_webapp_admin_caps['edit_dashboard'] = true;
    $my_webapp_admin_caps['customize'] = true;
    $my_webapp_admin_caps['delete_others_pages'] = true;
    $my_webapp_admin_caps['delete_others_posts'] = true;
    $my_webapp_admin_caps['delete_pages'] = true;
    $my_webapp_admin_caps['delete_posts'] = true;
    $my_webapp_admin_caps['delete_private_pages'] = true;
    $my_webapp_admin_caps['delete_private_posts'] = true;
    $my_webapp_admin_caps['delete_published_pages'] = true;
    $my_webapp_admin_caps['delete_published_posts'] = true;
    $my_webapp_admin_caps['edit_others_pages'] = true;
    $my_webapp_admin_caps['edit_others_posts'] = true;
    $my_webapp_admin_caps['edit_pages'] = true;
    // edit_posts is already added
    $my_webapp_admin_caps['edit_private_pages'] = true;
    $my_webapp_admin_caps['edit_private_posts'] = true;
    $my_webapp_admin_caps['edit_published_pages'] = true;
    $my_webapp_admin_caps['edit_published_posts'] = true;
    $my_webapp_admin_caps['manage_links'] = true; // If used
    $my_webapp_admin_caps['publish_pages'] = true;
    $my_webapp_admin_caps['publish_posts'] = true;
    $my_webapp_admin_caps['read_private_pages'] = true;
    $my_webapp_admin_caps['read_private_posts'] = true;


    add_role( 'my_webapp_admin', 'My WebApp Admin', $my_webapp_admin_caps );

    // Define capabilities for My WebApp Moderator role
    $my_webapp_moderator_caps = array(
        'read' => true, // Basic access
        'edit_users' => true, // As per requirement
        'list_users' => true, // Implied by edit_users
        'promote_users' => true, // Can change roles, but not create/delete admins
        'edit_posts' => true, // Edit general content
        'moderate_comments' => true, // As per requirement
        'upload_files' => true, // Usually needed for content editing
        'edit_others_posts' => true, // Moderators often edit others' content
        'delete_posts' => true, // Can delete posts
        'delete_others_posts' => true, // Can delete others' posts
        'publish_posts' => true, // Can publish posts
        'edit_published_posts' => true, // Can edit published posts
        'delete_published_posts' => true, // Can delete published posts
        // Custom capability
        'approve_kyc' => true,
    );
    add_role( 'my_webapp_moderator', 'My WebApp Moderator', $my_webapp_moderator_caps );

    // Define capabilities for My WebApp Instructor role
    $my_webapp_instructor_caps = array(
        'read' => true, // Basic access
        'upload_files' => true, // Typically needed if they are creating content for projects
        'edit_posts' => true, // Ability to create/edit their own content (e.g. project descriptions)
        'publish_posts' => true, // Ability to publish their own content
        'edit_published_posts' => true, // Ability to edit their own published content
        'delete_posts' => true, // Ability to delete their own content
        // Custom capabilities
        'edit_projects' => true,
        'assign_tasks' => true,
        'view_all_users_progress' => true,
    );
    add_role( 'my_webapp_instructor', 'My WebApp Instructor', $my_webapp_instructor_caps );

    // Define capabilities for My WebApp User role
    $my_webapp_user_caps = array(
        'read' => true, // Basic access
        'edit_posts' => true, // Allows editing their own posts/profile if it's a CPT or similar
        'upload_files' => true, // May be needed for submitting KYC documents or profile pictures
        // Custom capabilities
        'edit_own_profile' => true, // This is a custom cap, actual profile editing is handled by edit_profile + user level checks
        'view_own_dashboard' => true,
        'submit_kyc_documents' => true,
    );
    // Add WordPress's actual capability for editing own profile
    // Note: WordPress checks if user ID matches for 'edit_profile' and 'edit_user'.
    // Granting 'edit_user' is too broad. 'edit_profile' is not a cap to grant.
    // Instead, ensure the user can edit their own specific data.
    // 'edit_posts' allows users to edit their own 'post' type posts.
    // If profile data is stored elsewhere, specific checks are needed in the theme/plugin.
    // For now, 'edit_posts' is a general way to allow them to edit content they own.

    add_role( 'my_webapp_user', 'My WebApp User', $my_webapp_user_caps );
}
register_activation_hook( __FILE__, 'activate_my_webapp' );

/**
 * The code that runs during plugin deactivation.
 */
function my_webapp_remove_roles() {
    remove_role( 'my_webapp_admin' );
    remove_role( 'my_webapp_moderator' );
    remove_role( 'my_webapp_instructor' );
    remove_role( 'my_webapp_user' );
}

function deactivate_my_webapp() {
    my_webapp_remove_roles();
}
register_deactivation_hook( __FILE__, 'deactivate_my_webapp' );

?>

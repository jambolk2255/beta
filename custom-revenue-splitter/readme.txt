=== Custom Revenue Splitter ===
Contributors: YourNameOrWordPressOrgUsername
Donate link: https://example.com/your-donate-link
Tags: revenue share, user roles, e-commerce, split income, profit share, roles, authors, commissions
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin to calculate revenue splits based on user roles. Highly configurable via an admin settings page.

== Description ==

The Custom Revenue Splitter plugin provides a flexible way to define and calculate revenue shares for users based on their assigned roles. Administrators can easily set specific percentages for each role through a dedicated settings page ("Settings" > "Revenue Split").

This plugin is designed to provide the **calculation logic** for revenue shares. It does **not** automatically process or distribute payments. Developers need to integrate the `custom_revenue_splitter_calculate_share( $order_amount, $user_id )` function with their specific e-commerce plugin (e.g., WooCommerce, Easy Digital Downloads) or payment gateway system to handle the actual distribution of funds.

**Core Features:**
*   Easy-to-use settings page under "Settings > Revenue Split".
*   Define different revenue split percentages for each user role (e.g., Administrator, Editor, Author, Contributor, and custom roles).
*   Core function `custom_revenue_splitter_calculate_share( $order_amount, $user_id )` available for developers to integrate into any e-commerce or payment system.
*   Handles users with multiple roles by applying the highest defined percentage.
*   Demonstration hook on `wp_login` to show how the calculation works (logs data and shows an admin notice to administrators).
*   Internationalized and ready for translation.

**Important for Developers:**
This plugin provides the **calculation logic** for revenue shares. It does **not** automatically process or distribute payments. You need to integrate the `custom_revenue_splitter_calculate_share( $order_amount, $user_id )` function with your specific e-commerce plugin (e.g., WooCommerce, Easy Digital Downloads) or payment gateway. This typically involves:
1. Identifying the correct action hook in your e-commerce system that fires after a successful payment (e.g., `woocommerce_order_status_completed`, `edd_complete_purchase`).
2. Calling `custom_revenue_splitter_calculate_share()` with the order total and relevant user ID (e.g., product author, course instructor) to get the calculated share.
3. Implementing your own logic to handle the calculated share (e.g., store it in user meta for payouts, use payment gateway APIs to distribute funds, log for manual processing).

== Installation ==

1.  Upload the `custom-revenue-splitter` folder to your `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Navigate to "Settings > Revenue Split" in the WordPress admin area to configure the revenue split percentages for each role. These settings are stored and used by the calculation function.
4.  **For Developers (Implementing Actual Revenue Splitting):**
    *   Identify the relevant action hook in your e-commerce plugin or payment processing workflow (e.g., `woocommerce_order_status_completed`, `edd_complete_purchase`, or a custom hook).
    *   In your theme's `functions.php` or a custom plugin, add a function that hooks into this action.
    *   Inside your function, retrieve the order total and the user ID of the user who should receive a share (e.g., the author of a purchased product, the instructor of a course).
    *   Call `custom_revenue_splitter_calculate_share( $order_total, $user_id )` to get the calculated share for that user based on their role and the configured percentages.
    *   Use this share amount as needed by your payment distribution system or payout process. For example, you might store this in user meta, record it in a custom table, or use a payment gateway's API to schedule a transfer.

== Frequently Asked Questions ==

= Does this plugin automatically split payments via Stripe, PayPal, etc.? =
No. This plugin calculates the share amount based on user roles and the settings you configure. The actual financial distribution of funds needs to be implemented by you or a developer by integrating the provided `custom_revenue_splitter_calculate_share()` function with your specific e-commerce platform or payment gateway's API.

= How do I configure the split percentages? =
Go to "Settings > Revenue Split" in your WordPress admin dashboard. You will find a list of all user roles on your site (including custom roles), and you can input a percentage (0-100) for each. Save your settings, and these percentages will be used for calculations.

= What happens if a user has multiple roles? =
The plugin will use the percentage associated with the role that has the **highest** defined split percentage. For example, if a user is an "Editor" (set to 10%) and an "Author" (set to 5%), their share will be calculated at 10% of the order amount.

= Where are the settings stored? =
The role percentages are stored in the WordPress options table under the key `custom_revenue_splitter_role_splits`.

= Is the plugin translatable? =
Yes, the plugin is internationalized and includes a `.pot` file (coming soon) or strings within the code that can be translated using standard WordPress translation tools. The text domain is `custom-revenue-splitter`.

== Screenshots ==

1.  The "Revenue Split Settings" page in the WordPress admin area, showing fields for each role to set a percentage.
2.  (Future screenshot: Example of the admin notice shown by the demonstration hook.)

== Changelog ==

= 1.0.0 =
* Initial release of the plugin.
* Provides settings page under "Settings > Revenue Split" for role-based percentage configuration.
* Includes core calculation function `custom_revenue_splitter_calculate_share($order_amount, $user_id)`.
* Handles users with multiple roles by selecting the highest percentage.
* Adds a demonstration feature on `wp_login` that logs calculations and shows an admin notice.
* Internationalization support added (Text Domain: `custom-revenue-splitter`).
* Basic plugin structure with activation (sets default role splits) and deactivation (removes settings) hooks.
* Inline code documentation (DocBlocks) for all functions.

== Upgrade Notice ==

= 1.0.0 =
* No upgrade notice for the initial release. This is the first version.
```

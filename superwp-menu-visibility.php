<?php
/**
 * SuperWP Menu Visibility
 *
 * @package       SUPERWPMEN
 * @author        Thiarara
 * @license       gplv2-or-later
 * @version       1.0.02
 *
 * @wordpress-plugin
 * Plugin Name:   SuperWP Menu Visibility
 * Plugin URI:    https://github.com/Thiararapeter/SuperWP-Menu-Visibility
 * Description:   Control the visibility of WordPress menu items based on user roles, login state, device, location, language, and more.
 * Version:       1.0.05
 * Author:        Thiarara
 * Author URI:    https://profiles.wordpress.org/thiarara/
 * Text Domain:   superwp-menu-visibility
 * Domain Path:   /languages
 * License:       GPLv2 or later
 * License URI:   https://www.gnu.org/licenses/gpl-2.0.html
 *
 * You should have received a copy of the GNU General Public License
 * along with SuperWP Menu Visibility. If not, see <https://www.gnu.org/licenses/gpl-2.0.html/>.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * HELPER COMMENT START
 * 
 * This file contains the logic required to run the plugin.
 * To add some functionality, you can simply define the WordPres hooks as followed: 
 * 
 * add_action( 'init', 'some_callback_function', 10 );
 * 
 * and call the callback function like this 
 * 
 * function some_callback_function(){}
 * 
 * HELPER COMMENT END
 */


/**
 * Plugin Name: SuperWP Menu Visibility
 * Description: Control the visibility of WordPress menu items based on user roles, login state, device, location, language, and more.
 * Version: 1.7
 * Author: SuperWP
 * License: GPL2
 */

// Add custom menu item visibility logic
function superwp_menu_visibility_control($items, $args) {
    // Get settings values
    $debug_mode = get_option('superwp_debug_mode', 'no');
    $enable_location = get_option('superwp_enable_location', 'no');
    $enable_language = get_option('superwp_enable_language', 'no');
    $enable_woocommerce = get_option('superwp_enable_woocommerce', 'no');
    $geolocation_api_key = get_option('superwp_geolocation_api_key', '');
    
    foreach ($items as $key => $item) {
        // Get visibility rules set via menu options
        $visibility = get_post_meta($item->ID, '_menu_item_visibility', true);
        $allowed_countries = get_post_meta($item->ID, '_menu_item_allowed_countries', true);

        // Default visibility if no rules set
        if (!$visibility) {
            $default_visibility = get_option('superwp_default_visibility', '');
            $visibility = $default_visibility;
        }

        // User logged in only
        if ($visibility === 'logged-in-only' && !is_user_logged_in()) {
            unset($items[$key]);
            if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (logged-in-only)");
        }

        // User logged out only (hide for logged in users)
        if ($visibility === 'logged-out-only' && is_user_logged_in()) {
            unset($items[$key]);
            if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (logged-out-only)");
        }

        // Admins and Editors only
        if ($visibility === 'admin-editor-only' && !(current_user_can('administrator') || current_user_can('editor'))) {
            unset($items[$key]);
            if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (admin-editor-only)");
        }

        // Show for Authors only
        if ($visibility === 'author-only' && !current_user_can('author')) {
            unset($items[$key]);
            if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (author-only)");
        }

        // Show only on the front page
        if ($visibility === 'front-page-only' && !is_front_page()) {
            unset($items[$key]);
            if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (front-page-only)");
        }

        // Show only on single posts
        if ($visibility === 'single-post-only' && !is_single()) {
            unset($items[$key]);
            if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (single-post-only)");
        }

        // Hide on mobile devices
        if ($visibility === 'hide-on-mobile' && wp_is_mobile()) {
            unset($items[$key]);
            if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (hide-on-mobile)");
        }

        // Show only on desktop devices
        if ($visibility === 'desktop-only' && wp_is_mobile()) {
            unset($items[$key]);
            if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (desktop-only)");
        }

        // Language-specific visibility (GTranslate integration)
        if ($enable_language === 'yes') {
            $menu_item_lang = get_post_meta($item->ID, '_menu_item_language', true);
            $current_lang = superwp_get_current_language();
            if (!empty($menu_item_lang) && $menu_item_lang !== $current_lang) {
                unset($items[$key]);
                if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (language mismatch)");
            }
        }

        // Country-specific visibility
        if ($enable_location === 'yes' && !empty($geolocation_api_key) && !empty($allowed_countries)) {
            $user_country = superwp_get_user_country($geolocation_api_key);
            if ($user_country && !in_array($user_country, $allowed_countries)) {
                unset($items[$key]);
                if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (country mismatch)");
            }
        }

        // WooCommerce-specific visibility
        if ($enable_woocommerce === 'yes' && function_exists('is_woocommerce')) {
            $woo_visibility = get_post_meta($item->ID, '_menu_item_woo_visibility', true);
            if ($woo_visibility === 'shop-only' && !is_shop()) {
                unset($items[$key]);
                if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (not on shop page)");
            } elseif ($woo_visibility === 'product-only' && !is_product()) {
                unset($items[$key]);
                if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (not on product page)");
            }
        }
    }
    return $items;
}
add_filter('wp_nav_menu_objects', 'superwp_menu_visibility_control', 10, 2);

// Function to get user's country using geolocation API
function superwp_get_user_country($api_key) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $api_url = "https://api.ipgeolocation.io/ipgeo?apiKey={$api_key}&ip={$ip_address}";
    
    $response = wp_remote_get($api_url);
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['country_code2'])) {
        return $data['country_code2'];
    }
    
    return false;
}

// Function to get current language (GTranslate integration)
function superwp_get_current_language() {
    if (function_exists('gtranslate_get_lang')) {
        return gtranslate_get_lang();
    }
    return get_locale();
}

// Add visibility options to menu items in admin
function superwp_add_menu_visibility_option($item_id, $item, $depth, $args, $id) {
    $visibility = get_post_meta($item_id, '_menu_item_visibility', true);
    $allowed_countries = get_post_meta($item_id, '_menu_item_allowed_countries', true);
    $enable_location = get_option('superwp_enable_location', 'no');
    $enable_language = get_option('superwp_enable_language', 'no');
    $enable_woocommerce = get_option('superwp_enable_woocommerce', 'no');
    ?>
    <p class="field-visibility description description-wide">
        <label for="edit-menu-item-visibility-<?php echo esc_attr($item_id); ?>">
            <?php _e('Change menu item visibility', 'superwp'); ?><br />
            <select id="edit-menu-item-visibility-<?php echo esc_attr($item_id); ?>" name="menu-item-visibility[<?php echo esc_attr($item_id); ?>]">
                <option value="" <?php selected($visibility, ''); ?>><?php _e('Default (Visible to all)', 'superwp'); ?></option>
                <option value="logged-in-only" <?php selected($visibility, 'logged-in-only'); ?>><?php _e('Only Logged-in Users', 'superwp'); ?></option>
                <option value="logged-out-only" <?php selected($visibility, 'logged-out-only'); ?>><?php _e('Only Logged-out Users', 'superwp'); ?></option>
                <option value="admin-editor-only" <?php selected($visibility, 'admin-editor-only'); ?>><?php _e('Admins and Editors Only', 'superwp'); ?></option>
                <option value="author-only" <?php selected($visibility, 'author-only'); ?>><?php _e('Authors Only', 'superwp'); ?></option>
                <option value="front-page-only" <?php selected($visibility, 'front-page-only'); ?>><?php _e('Only on Front Page', 'superwp'); ?></option>
                <option value="single-post-only" <?php selected($visibility, 'single-post-only'); ?>><?php _e('Only on Single Post', 'superwp'); ?></option>
                <option value="hide-on-mobile" <?php selected($visibility, 'hide-on-mobile'); ?>><?php _e('Hide on Mobile Devices', 'superwp'); ?></option>
                <option value="desktop-only" <?php selected($visibility, 'desktop-only'); ?>><?php _e('Show Only on Desktop', 'superwp'); ?></option>
            </select>
        </label>
    </p>
    <?php
    // Add language selection for GTranslate integration
    if ($enable_language === 'yes') {
        $menu_item_lang = get_post_meta($item_id, '_menu_item_language', true);
        $languages = superwp_get_available_languages();
        ?>
        <p class="field-language description description-wide">
            <label for="edit-menu-item-language-<?php echo esc_attr($item_id); ?>">
                <?php _e('Menu Item Language', 'superwp'); ?><br />
                <select id="edit-menu-item-language-<?php echo esc_attr($item_id); ?>" name="menu-item-language[<?php echo esc_attr($item_id); ?>]">
                    <option value="" <?php selected($menu_item_lang, ''); ?>><?php _e('All Languages', 'superwp'); ?></option>
                    <?php foreach ($languages as $code => $name) : ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($menu_item_lang, $code); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <?php
    }
    // Add country selection for geolocation-based visibility
    if ($enable_location === 'yes') {
        ?>
        <p class="field-allowed-countries description description-wide">
            <label for="edit-menu-item-allowed-countries-<?php echo esc_attr($item_id); ?>">
                <?php _e('Allowed Countries (comma-separated country codes)', 'superwp'); ?><br />
                <input type="text" id="edit-menu-item-allowed-countries-<?php echo esc_attr($item_id); ?>" 
                       name="menu-item-allowed-countries[<?php echo esc_attr($item_id); ?>]" 
                       value="<?php echo esc_attr(is_array($allowed_countries) ? implode(',', $allowed_countries) : $allowed_countries); ?>" />
            </label>
        </p>
        <?php
    }
    // Add WooCommerce-specific visibility options
    if ($enable_woocommerce === 'yes' && function_exists('is_woocommerce')) {
        $woo_visibility = get_post_meta($item_id, '_menu_item_woo_visibility', true);
        ?>
        <p class="field-woo-visibility description description-wide">
            <label for="edit-menu-item-woo-visibility-<?php echo esc_attr($item_id); ?>">
                <?php _e('WooCommerce Visibility', 'superwp'); ?><br />
                <select id="edit-menu-item-woo-visibility-<?php echo esc_attr($item_id); ?>" name="menu-item-woo-visibility[<?php echo esc_attr($item_id); ?>]">
                    <option value="" <?php selected($woo_visibility, ''); ?>><?php _e('All Pages', 'superwp'); ?></option>
                    <option value="shop-only" <?php selected($woo_visibility, 'shop-only'); ?>><?php _e('Shop Page Only', 'superwp'); ?></option>
                    <option value="product-only" <?php selected($woo_visibility, 'product-only'); ?>><?php _e('Product Pages Only', 'superwp'); ?></option>
                </select>
            </label>
        </p>
        <?php
    }
}
add_action('wp_nav_menu_item_custom_fields', 'superwp_add_menu_visibility_option', 10, 5);

// Save the visibility option when menu is saved
function superwp_save_menu_visibility_option($menu_id, $menu_item_db_id) {
    if (isset($_POST['menu-item-visibility'][$menu_item_db_id])) {
        update_post_meta($menu_item_db_id, '_menu_item_visibility', sanitize_text_field($_POST['menu-item-visibility'][$menu_item_db_id]));
    } else {
        delete_post_meta($menu_item_db_id, '_menu_item_visibility');
    }

    // Save language selection for GTranslate integration
    if (isset($_POST['menu-item-language'][$menu_item_db_id])) {
        update_post_meta($menu_item_db_id, '_menu_item_language', sanitize_text_field($_POST['menu-item-language'][$menu_item_db_id]));
    } else {
        delete_post_meta($menu_item_db_id, '_menu_item_language');
    }

    // Save allowed countries
    if (isset($_POST['menu-item-allowed-countries'][$menu_item_db_id])) {
        $allowed_countries = explode(',', sanitize_text_field($_POST['menu-item-allowed-countries'][$menu_item_db_id]));
        $allowed_countries = array_map('trim', $allowed_countries);
        update_post_meta($menu_item_db_id, '_menu_item_allowed_countries', $allowed_countries);
		} else {
        delete_post_meta($menu_item_db_id, '_menu_item_allowed_countries');
    }

    // Save WooCommerce-specific visibility
    if (isset($_POST['menu-item-woo-visibility'][$menu_item_db_id])) {
        update_post_meta($menu_item_db_id, '_menu_item_woo_visibility', sanitize_text_field($_POST['menu-item-woo-visibility'][$menu_item_db_id]));
    } else {
        delete_post_meta($menu_item_db_id, '_menu_item_woo_visibility');
    }
}
add_action('wp_update_nav_menu_item', 'superwp_save_menu_visibility_option', 10, 2);

// Add plugin settings page
function superwp_menu_visibility_settings_page() {
    add_options_page(
        'SuperWP Menu Visibility Settings',
        'Menu Visibility',
        'manage_options',
        'superwp-menu-visibility',
        'superwp_menu_visibility_settings_page_content'
    );
}
add_action('admin_menu', 'superwp_menu_visibility_settings_page');

// Settings page content
function superwp_menu_visibility_settings_page_content() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['superwp_save_settings'])) {
        check_admin_referer('superwp_menu_visibility_settings');
        
        update_option('superwp_debug_mode', isset($_POST['superwp_debug_mode']) ? 'yes' : 'no');
        update_option('superwp_enable_location', isset($_POST['superwp_enable_location']) ? 'yes' : 'no');
        update_option('superwp_enable_language', isset($_POST['superwp_enable_language']) ? 'yes' : 'no');
        update_option('superwp_enable_woocommerce', isset($_POST['superwp_enable_woocommerce']) ? 'yes' : 'no');
        update_option('superwp_geolocation_api_key', sanitize_text_field($_POST['superwp_geolocation_api_key']));
        update_option('superwp_default_visibility', sanitize_text_field($_POST['superwp_default_visibility']));
        
        echo '<div class="updated"><p>Settings saved successfully!</p></div>';
    }

    $debug_mode = get_option('superwp_debug_mode', 'no');
    $enable_location = get_option('superwp_enable_location', 'no');
    $enable_language = get_option('superwp_enable_language', 'no');
    $enable_woocommerce = get_option('superwp_enable_woocommerce', 'no');
    $geolocation_api_key = get_option('superwp_geolocation_api_key', '');
    $default_visibility = get_option('superwp_default_visibility', '');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('superwp_menu_visibility_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="superwp_debug_mode">Enable Debug Mode</label></th>
                    <td>
                        <input type="checkbox" id="superwp_debug_mode" name="superwp_debug_mode" <?php checked($debug_mode, 'yes'); ?>>
                        <p class="description">Log visibility decisions for debugging purposes.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="superwp_enable_location">Enable Location-based Visibility</label></th>
                    <td>
                        <input type="checkbox" id="superwp_enable_location" name="superwp_enable_location" <?php checked($enable_location, 'yes'); ?>>
                        <p class="description">Allow menu items to be shown/hidden based on user's location.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="superwp_enable_language">Enable Language-based Visibility</label></th>
                    <td>
                        <input type="checkbox" id="superwp_enable_language" name="superwp_enable_language" <?php checked($enable_language, 'yes'); ?>>
                        <p class="description">Allow menu items to be shown/hidden based on the current language.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="superwp_enable_woocommerce">Enable WooCommerce-specific Visibility</label></th>
                    <td>
                        <input type="checkbox" id="superwp_enable_woocommerce" name="superwp_enable_woocommerce" <?php checked($enable_woocommerce, 'yes'); ?>>
                        <p class="description">Allow menu items to have WooCommerce-specific visibility options.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="superwp_geolocation_api_key">Geolocation API Key</label></th>
                    <td>
                        <input type="password" id="superwp_geolocation_api_key" name="superwp_geolocation_api_key" value="<?php echo esc_attr($geolocation_api_key); ?>" class="regular-text">
                        <p class="description">Enter your Geolocation API key for location-based visibility.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="superwp_default_visibility">Default Visibility</label></th>
                    <td>
                        <select id="superwp_default_visibility" name="superwp_default_visibility">
                            <option value="" <?php selected($default_visibility, ''); ?>><?php _e('Visible to all', 'superwp'); ?></option>
                            <option value="logged-in-only" <?php selected($default_visibility, 'logged-in-only'); ?>><?php _e('Only Logged-in Users', 'superwp'); ?></option>
                            <option value="logged-out-only" <?php selected($default_visibility, 'logged-out-only'); ?>><?php _e('Only Logged-out Users', 'superwp'); ?></option>
                        </select>
                        <p class="description">Set the default visibility for menu items without specific rules.</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="superwp_save_settings" class="button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    <?php
}

// Function to get available languages (for GTranslate integration)
function superwp_get_available_languages() {
    if (function_exists('gtranslate_get_available_languages')) {
        return gtranslate_get_available_languages();
    }
    return array('en' => 'English'); // Fallback if GTranslate is not available
}

// Modify the menu items in the admin area to show visibility status
function superwp_admin_menu_visibility_indicator($item_output, $item, $depth, $args) {
    if (is_admin()) {
        $visibility = get_post_meta($item->ID, '_menu_item_visibility', true);
        if ($visibility) {
            $item_output .= ' <span class="superwp-visibility-indicator" title="Visibility: ' . esc_attr($visibility) . '">👁️</span>';
        }
    }
    return $item_output;
}
add_filter('walker_nav_menu_item_title', 'superwp_admin_menu_visibility_indicator', 10, 4);

// Add custom CSS for the admin area
function superwp_admin_custom_css() {
    echo '<style>
        .superwp-visibility-indicator {
            display: inline-block;
            margin-left: 5px;
            font-size: 16px;
            vertical-align: middle;
        }
    </style>';
}
add_action('admin_head', 'superwp_admin_custom_css');

// Function to check if a menu item should be visible based on all criteria
function superwp_should_menu_item_be_visible($item) {
    $visibility = get_post_meta($item->ID, '_menu_item_visibility', true);
    $allowed_countries = get_post_meta($item->ID, '_menu_item_allowed_countries', true);
    $menu_item_lang = get_post_meta($item->ID, '_menu_item_language', true);
    $woo_visibility = get_post_meta($item->ID, '_menu_item_woo_visibility', true);

    $enable_location = get_option('superwp_enable_location', 'no');
    $enable_language = get_option('superwp_enable_language', 'no');
    $enable_woocommerce = get_option('superwp_enable_woocommerce', 'no');
    $geolocation_api_key = get_option('superwp_geolocation_api_key', '');
    $debug_mode = get_option('superwp_debug_mode', 'no');

    // Check visibility rules
    if ($visibility === 'logged-in-only' && !is_user_logged_in()) {
        return false;
    }
    if ($visibility === 'logged-out-only' && is_user_logged_in()) {
        return false;
    }
    if ($visibility === 'admin-editor-only' && !(current_user_can('administrator') || current_user_can('editor'))) {
        return false;
    }
    if ($visibility === 'author-only' && !current_user_can('author')) {
        return false;
    }
    if ($visibility === 'front-page-only' && !is_front_page()) {
        return false;
    }
    if ($visibility === 'single-post-only' && !is_single()) {
        return false;
    }
    if ($visibility === 'hide-on-mobile' && wp_is_mobile()) {
        return false;
    }
    if ($visibility === 'desktop-only' && wp_is_mobile()) {
        return false;
    }

    // Check language-based visibility
    if ($enable_language === 'yes' && !empty($menu_item_lang)) {
        $current_lang = superwp_get_current_language();
        if ($menu_item_lang !== $current_lang) {
            return false;
        }
    }

    // Check location-based visibility
    if ($enable_location === 'yes' && !empty($geolocation_api_key) && !empty($allowed_countries)) {
        $user_country = superwp_get_user_country($geolocation_api_key);
        if ($user_country && !in_array($user_country, $allowed_countries)) {
            return false;
        }
    }

    // Check WooCommerce-specific visibility
    if ($enable_woocommerce === 'yes' && function_exists('is_woocommerce')) {
        if ($woo_visibility === 'shop-only' && !is_shop()) {
            return false;
        } elseif ($woo_visibility === 'product-only' && !is_product()) {
            return false;
        }
    }

    // Log visibility decision if debug mode is enabled
    if ($debug_mode === 'yes') {
        error_log("Menu item {$item->title} is visible");
    }

    return true;
}

// Add a dashboard widget to display visibility statistics
function superwp_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'superwp_visibility_stats',
        'Menu Visibility Statistics',
        'superwp_display_visibility_stats'
    );
}
add_action('wp_dashboard_setup', 'superwp_add_dashboard_widget');

function superwp_display_visibility_stats() {
    $menus = wp_get_nav_menus();
    $total_items = 0;
    $hidden_items = 0;

    foreach ($menus as $menu) {
        $menu_items = wp_get_nav_menu_items($menu->term_id);
        if ($menu_items) {
            foreach ($menu_items as $item) {
                $total_items++;
                if (!superwp_should_menu_item_be_visible($item)) {
                    $hidden_items++;
                }
            }
        }
    }

    echo "<p>Total menu items: {$total_items}</p>";
    echo "<p>Hidden menu items: {$hidden_items}</p>";
    echo "<p>Visible menu items: " . ($total_items - $hidden_items) . "</p>";
}

// Activation hook to set default options
function superwp_menu_visibility_activate() {
    add_option('superwp_debug_mode', 'no');
    add_option('superwp_enable_location', 'no');
    add_option('superwp_enable_language', 'no');
    add_option('superwp_enable_woocommerce', 'no');
    add_option('superwp_geolocation_api_key', '');
    add_option('superwp_default_visibility', '');
}
register_activation_hook(__FILE__, 'superwp_menu_visibility_activate');

// Deactivation hook to clean up options
function superwp_menu_visibility_deactivate() {
    delete_option('superwp_debug_mode');
    delete_option('superwp_enable_location');
    delete_option('superwp_enable_language');
    delete_option('superwp_enable_woocommerce');
    delete_option('superwp_geolocation_api_key');
    delete_option('superwp_default_visibility');
}
register_deactivation_hook(__FILE__, 'superwp_menu_visibility_deactivate');

// push update from Github
require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/Thiararapeter/SuperWP-Menu-Visibility',
	__FILE__,
	'SuperWP Menu Visibility'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');
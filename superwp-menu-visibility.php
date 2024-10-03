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
 * Version:       1.0.04
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

// Enqueue admin scripts and styles for menu settings
function superwp_enqueue_admin_scripts() {
    wp_enqueue_style('superwp-admin-style', plugin_dir_url(__FILE__) . 'admin-style.css');
    wp_enqueue_script('superwp-admin-script', plugin_dir_url(__FILE__) . 'admin-script.js', array('jquery'), '1.0', true);
}
add_action('admin_enqueue_scripts', 'superwp_enqueue_admin_scripts');

// Add custom menu item visibility logic
function superwp_menu_visibility_control($items, $args) {
    // Get settings values
    $debug_mode = get_option('superwp_debug_mode', 'no');
    $advanced_features = get_option('superwp_enable_advanced_features', 'no');
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

        // Language-specific visibility (Polylang integration)
        if ($advanced_features === 'yes' && function_exists('pll_current_language')) {
            $menu_item_lang = get_post_meta($item->ID, '_menu_item_language', true);
            $current_lang = pll_current_language();
            if (!empty($menu_item_lang) && $menu_item_lang !== $current_lang) {
                unset($items[$key]);
                if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (language mismatch)");
            }
        }

        // Country-specific visibility
        if ($advanced_features === 'yes' && !empty($geolocation_api_key) && !empty($allowed_countries)) {
            $user_country = superwp_get_user_country($geolocation_api_key);
            if (!in_array($user_country, $allowed_countries)) {
                unset($items[$key]);
                if ($debug_mode === 'yes') error_log("Menu item {$item->title} hidden (country mismatch)");
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

// Add visibility options to menu items in admin
function superwp_add_menu_visibility_option($item_id, $item, $depth, $args, $id) {
    $visibility = get_post_meta($item_id, '_menu_item_visibility', true);
    $allowed_countries = get_post_meta($item_id, '_menu_item_allowed_countries', true);
    $advanced_features = get_option('superwp_enable_advanced_features', 'no');
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
    // Add language selection for Polylang integration
    if ($advanced_features === 'yes' && function_exists('pll_the_languages')) {
        $menu_item_lang = get_post_meta($item_id, '_menu_item_language', true);
        $languages = pll_the_languages(array('raw' => 1));
        ?>
        <p class="field-language description description-wide">
            <label for="edit-menu-item-language-<?php echo esc_attr($item_id); ?>">
                <?php _e('Menu Item Language', 'superwp'); ?><br />
                <select id="edit-menu-item-language-<?php echo esc_attr($item_id); ?>" name="menu-item-language[<?php echo esc_attr($item_id); ?>]">
                    <option value="" <?php selected($menu_item_lang, ''); ?>><?php _e('All Languages', 'superwp'); ?></option>
                    <?php foreach ($languages as $lang) : ?>
                        <option value="<?php echo esc_attr($lang['slug']); ?>" <?php selected($menu_item_lang, $lang['slug']); ?>><?php echo esc_html($lang['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <?php
    }
    // Add country selection for geolocation-based visibility
    if ($advanced_features === 'yes') {
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
}
add_action('wp_nav_menu_item_custom_fields', 'superwp_add_menu_visibility_option', 10, 5);

// Save the visibility option when menu is saved
function superwp_save_menu_visibility_option($menu_id, $menu_item_db_id) {
    if (isset($_POST['menu-item-visibility'][$menu_item_db_id])) {
        update_post_meta($menu_item_db_id, '_menu_item_visibility', sanitize_text_field($_POST['menu-item-visibility'][$menu_item_db_id]));
    } else {
        delete_post_meta($menu_item_db_id, '_menu_item_visibility');
    }

    // Save language selection for Polylang integration
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
}
add_action('wp_update_nav_menu_item', 'superwp_save_menu_visibility_option', 10, 2);

// Add plugin settings page as a submenu under the Settings menu
function superwp_menu_visibility_settings_page() {
    add_submenu_page(
        'options-general.php',              // Parent menu (Settings)
        __('SuperWP Menu Visibility Settings', 'superwp'),  // Page title
        __('Menu Visibility', 'superwp'),   // Menu title
        'manage_options',                   // Capability
        'superwp-menu-visibility',          // Menu slug
        'superwp_menu_visibility_settings_page_html'  // Function to display the settings page
    );
}
add_action('admin_menu', 'superwp_menu_visibility_settings_page');

// Render the plugin settings page
function superwp_menu_visibility_settings_page_html() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if settings were updated
    if (isset($_GET['settings-updated'])) {
        add_settings_error('superwp_messages', 'superwp_message', __('Settings Saved', 'superwp'), 'updated');
    }

    // Show error/update messages
    settings_errors('superwp_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('superwp_menu_visibility_settings');
            do_settings_sections('superwp_menu_visibility_settings');
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}

// Register and define settings for the plugin
function superwp_menu_visibility_settings_init() {
    register_setting('superwp_menu_visibility_settings', 'superwp_visibility_rules');
    register_setting('superwp_menu_visibility_settings', 'superwp_default_visibility');
    register_setting('superwp_menu_visibility_settings', 'superwp_debug_mode');
    register_setting('superwp_menu_visibility_settings', 'superwp_enable_advanced_features');
    register_setting('superwp_menu_visibility_settings', 'superwp_default_new_menu_visibility');
    register_setting('superwp_menu_visibility_settings', 'superwp_geolocation_api_key');
    
   add_settings_section(
        'superwp_menu_visibility_section',
        __('Visibility Rules', 'superwp'),
        'superwp_menu_visibility_section_cb',
        'superwp_menu_visibility_settings'
    );
    
    add_settings_field(
        'superwp_default_visibility',
        __('Default Visibility Rule', 'superwp'),
        'superwp_default_visibility_cb',
        'superwp_menu_visibility_settings',
        'superwp_menu_visibility_section'
    );

    add_settings_field(
        'superwp_debug_mode',
        __('Enable Debug Mode', 'superwp'),
        'superwp_debug_mode_cb',
        'superwp_menu_visibility_settings',
        'superwp_menu_visibility_section'
    );

    add_settings_field(
        'superwp_enable_advanced_features',
        __('Enable Advanced Features', 'superwp'),
        'superwp_enable_advanced_features_cb',
        'superwp_menu_visibility_settings',
        'superwp_menu_visibility_section'
    );

    add_settings_field(
        'superwp_default_new_menu_visibility',
        __('Default Visibility for New Menus', 'superwp'),
        'superwp_default_new_menu_visibility_cb',
        'superwp_menu_visibility_settings',
        'superwp_menu_visibility_section'
    );

    add_settings_field(
        'superwp_geolocation_api_key',
        __('Geolocation API Key', 'superwp'),
        'superwp_geolocation_api_key_cb',
        'superwp_menu_visibility_settings',
        'superwp_menu_visibility_section'
    );
}
add_action('admin_init', 'superwp_menu_visibility_settings_init');

// Callback function for the settings section
function superwp_menu_visibility_section_cb() {
    echo '<p>' . __('Configure menu visibility settings below.', 'superwp') . '</p>';
}

// Field callback for default visibility
function superwp_default_visibility_cb() {
    $default_visibility = get_option('superwp_default_visibility', '');
    ?>
    <select name="superwp_default_visibility">
        <option value="" <?php selected($default_visibility, ''); ?>><?php _e('Default (Visible to all)', 'superwp'); ?></option>
        <option value="logged-in-only" <?php selected($default_visibility, 'logged-in-only'); ?>><?php _e('Only Logged-in Users', 'superwp'); ?></option>
        <option value="logged-out-only" <?php selected($default_visibility, 'logged-out-only'); ?>><?php _e('Only Logged-out Users', 'superwp'); ?></option>
    </select>
    <?php
}

// Field callback for debug mode
function superwp_debug_mode_cb() {
    $debug_mode = get_option('superwp_debug_mode', 'no');
    ?>
    <input type="checkbox" name="superwp_debug_mode" value="yes" <?php checked($debug_mode, 'yes'); ?> />
    <?php _e('Enable Debug Mode (Logs applied visibility rules)', 'superwp'); ?>
    <?php
}

// Field callback for enabling advanced features
function superwp_enable_advanced_features_cb() {
    $advanced_features = get_option('superwp_enable_advanced_features', 'no');
    ?>
    <input type="checkbox" name="superwp_enable_advanced_features" value="yes" <?php checked($advanced_features, 'yes'); ?> />
    <?php _e('Enable Advanced Features (Location, Language, WooCommerce)', 'superwp'); ?>
    <?php
}

// Field callback for default visibility for new menus
function superwp_default_new_menu_visibility_cb() {
    $default_new_menu_visibility = get_option('superwp_default_new_menu_visibility', '');
    ?>
    <select name="superwp_default_new_menu_visibility">
        <option value="" <?php selected($default_new_menu_visibility, ''); ?>><?php _e('Default (Visible to all)', 'superwp'); ?></option>
        <option value="logged-in-only" <?php selected($default_new_menu_visibility, 'logged-in-only'); ?>><?php _e('Only Logged-in Users', 'superwp'); ?></option>
        <option value="logged-out-only" <?php selected($default_new_menu_visibility, 'logged-out-only'); ?>><?php _e('Only Logged-out Users', 'superwp'); ?></option>
    </select>
    <?php
}

// Field callback for geolocation API key
function superwp_geolocation_api_key_cb() {
    $geolocation_api_key = get_option('superwp_geolocation_api_key', '');
    ?>
    <input type="text" name="superwp_geolocation_api_key" value="<?php echo esc_attr($geolocation_api_key); ?>" size="40" />
    <p class="description">
        <?php _e('Enter your Geolocation API Key here.', 'superwp'); ?>
        <a href="https://ipgeolocation.io/" target="_blank"><?php _e('Get your API key', 'superwp'); ?></a>
    </p>
    <?php
}

// Add settings link on plugin page
function superwp_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=superwp-menu-visibility">' . __('Settings', 'superwp') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'superwp_settings_link');

// Display admin notices for success and error messages
function superwp_admin_notices() {
    settings_errors('superwp_messages');
}
add_action('admin_notices', 'superwp_admin_notices');

// Sanitize and validate input before saving
function superwp_sanitize_options($input) {
    $new_input = array();
    
    if (isset($input['superwp_default_visibility'])) {
        $new_input['superwp_default_visibility'] = sanitize_text_field($input['superwp_default_visibility']);
    }
    
    if (isset($input['superwp_debug_mode'])) {
        $new_input['superwp_debug_mode'] = ($input['superwp_debug_mode'] == 'yes') ? 'yes' : 'no';
    }
    
    if (isset($input['superwp_enable_advanced_features'])) {
        $new_input['superwp_enable_advanced_features'] = ($input['superwp_enable_advanced_features'] == 'yes') ? 'yes' : 'no';
    }
    
    if (isset($input['superwp_default_new_menu_visibility'])) {
        $new_input['superwp_default_new_menu_visibility'] = sanitize_text_field($input['superwp_default_new_menu_visibility']);
    }
    
    if (isset($input['superwp_geolocation_api_key'])) {
        $new_input['superwp_geolocation_api_key'] = sanitize_text_field($input['superwp_geolocation_api_key']);
    }
    
    return $new_input;
}

// Register sanitization callback
register_setting(
    'superwp_menu_visibility_settings',
    'superwp_menu_visibility_options',
    'superwp_sanitize_options'
);

// Initialize plugin
function superwp_menu_visibility_init() {
    load_plugin_textdomain('superwp', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'superwp_menu_visibility_init');

// Add Polylang compatibility
function superwp_polylang_compatibility() {
    if (function_exists('pll_register_string')) {
        pll_register_string('SuperWP Menu Visibility', 'Default (Visible to all)', 'SuperWP');
        pll_register_string('SuperWP Menu Visibility', 'Only Logged-in Users', 'SuperWP');
        pll_register_string('SuperWP Menu Visibility', 'Only Logged-out Users', 'SuperWP');
        pll_register_string('SuperWP Menu Visibility', 'Admins and Editors Only', 'SuperWP');
        pll_register_string('SuperWP Menu Visibility', 'Authors Only', 'SuperWP');
        pll_register_string('SuperWP Menu Visibility', 'Only on Front Page', 'SuperWP');
        pll_register_string('SuperWP Menu Visibility', 'Only on Single Post', 'SuperWP');
        pll_register_string('SuperWP Menu Visibility', 'Hide on Mobile Devices', 'SuperWP');
        pll_register_string('SuperWP Menu Visibility', 'Show Only on Desktop', 'SuperWP');
        pll_register_string('SuperWP Menu Visibility', 'All Languages', 'SuperWP');
    }
}
add_action('plugins_loaded', 'superwp_polylang_compatibility');


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
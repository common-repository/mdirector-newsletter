<?php
use MDirectorNewsletter\includes\Mdirector_Newsletter;
use MDirectorNewsletter\includes\Mdirector_Newsletter_Activator;
use MDirectorNewsletter\includes\Mdirector_Newsletter_Deactivator;
use MDirectorNewsletter\includes\Mdirector_Newsletter_Utils;
/**
 *
 * @link              https://mdirector.com
 * @since             1.0.0
 * @package           MDirector_Newsletter
 *
 * @wordpress-plugin
 * Plugin Name:       MDirector Newsletter
 * Plugin URI:        https://www.mdirector.com/
 * Description:       Official MDirector plugin for WordPress. Add MDirector sign-up forms to your WordPress site.
 * Version:           4.4.0
 * Author:            MDirector
 * Author URI:        https://mdirector.com/
 * License:           GPL-2.0
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       mdirector-newsletter
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

if (!defined('ABSPATH')) return;

global $wp_version;

define('MDIRECTOR_MIN_WP_VERSION', '4.0.0');
define('MDIRECTOR_NEWSLETTER', 'mdirector-newsletter');
define('MDIRECTOR_NEWSLETTER_VERSION', '4.4.0');
define('MDIRECTOR_NEWSLETTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MDIRECTOR_TEMPLATES_PATH', plugin_dir_path(__FILE__) . 'templates/');
define('MDIRECTOR_LOGS_PATH', plugin_dir_path(__FILE__) . 'log/');
define('MDIRECTOR_NEWSLETTER_PLUGIN_URL', plugins_url('/', __FILE__));
define('MDIRECTOR_NEWSLETTER_PLUGIN_FILE', __FILE__);
define('MDIRECTOR_CURRENT_WP_VERSION', $wp_version);

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require MDIRECTOR_NEWSLETTER_PLUGIN_DIR . 'includes/class-mdirector-newsletter.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-mdirector-newsletter-activator.php
 */
function activate_mdirector_newsletter() {
    require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR . 'includes/class-mdirector-newsletter-activator.php';
    Mdirector_Newsletter_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-mdirector-newsletter-deactivator.php
 */
function deactivate_mdirector_newsletter() {
    require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR . 'includes/class-mdirector-newsletter-deactivator.php';
    Mdirector_Newsletter_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_mdirector_newsletter');
register_deactivation_hook(__FILE__, 'deactivate_mdirector_newsletter');

function mdirector_admin_notice() {
    require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR . 'includes/class-mdirector-newsletter-utils.php';

    if (!get_option('mdirector-notice')) {
        echo '<div class="updated" style="padding: 10px;margin: 20px 0 0 2px;"><p>';
        echo __('PLUGIN-INSTALLED',
            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN);
        echo ' <a href="admin.php?page=mdirector-newsletter&tab=welcome&mdirector_notice_ignore=0">';
        echo __('PLUGIN-CONFIGURE',
            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN);
        echo '</a>';
        echo '</p></div>';
    }
}


function mdirector_notice_ignore() {
    if (isset($_GET['mdirector_notice_ignore']) && $_GET['mdirector_notice_ignore'] == '0') {
        update_option('mdirector-notice', 'true', true);
    }
}

add_action('admin_notices', 'mdirector_admin_notice');
add_action('admin_init', 'mdirector_notice_ignore');

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 * @throws Twig_Error_Loader
 * @throws Twig_Error_Runtime
 * @throws Twig_Error_Syntax
 */
function run_mdirector_newsletter() {
    $plugin = new Mdirector_Newsletter();
    $plugin->run();
}

run_mdirector_newsletter();

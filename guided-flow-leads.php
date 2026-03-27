<?php
/**
 * Plugin Name:       Guided Flow Leads
 * Plugin URI:        https://example.com/
 * Description:       A lightweight guided chat and lead capture plugin for WordPress. This version refines the admin experience into a cleaner flow configurator without AI dependencies.
 * Version:           1.0.9
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            OpenAI for Winson Yang
 * Author URI:        https://openai.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       guided-flow-leads
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'GFL_VERSION' ) ) {
    define( 'GFL_VERSION', '1.0.9' );
}

if ( ! defined( 'GFL_FILE' ) ) {
    define( 'GFL_FILE', __FILE__ );
}

if ( ! defined( 'GFL_PATH' ) ) {
    define( 'GFL_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'GFL_URL' ) ) {
    define( 'GFL_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'GFL_SLUG' ) ) {
    define( 'GFL_SLUG', 'guided-flow-leads' );
}

if ( ! defined( 'GFL_OPTION_KEY' ) ) {
    define( 'GFL_OPTION_KEY', 'gfl_settings' );
}

if ( ! defined( 'GFL_VERSION_KEY' ) ) {
    define( 'GFL_VERSION_KEY', 'gfl_version' );
}

require_once GFL_PATH . 'includes/class-activator.php';
require_once GFL_PATH . 'includes/class-deactivator.php';
require_once GFL_PATH . 'includes/class-lead-service.php';
require_once GFL_PATH . 'includes/class-flow-service.php';
require_once GFL_PATH . 'includes/class-admin.php';
require_once GFL_PATH . 'includes/class-rest.php';
require_once GFL_PATH . 'includes/class-frontend.php';
require_once GFL_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'GFL_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GFL_Deactivator', 'deactivate' ) );

function guided_flow_leads() {
    return GFL_Plugin::instance();
}

guided_flow_leads();

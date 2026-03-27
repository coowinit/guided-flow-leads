<?php
/**
 * Plugin Name: Guided Flow Leads
 * Description: Guided conversational lead capture plugin.
 * Version: 1.0.4
 * Author: Coowin
 */

if (!defined('ABSPATH')) exit;

define('GFL_VERSION', '1.0.4');
define('GFL_PATH', plugin_dir_path(__FILE__));
define('GFL_URL', plugin_dir_url(__FILE__));

require_once GFL_PATH . 'includes/class-flow.php';
require_once GFL_PATH . 'includes/class-api.php';
require_once GFL_PATH . 'admin/class-admin.php';
<?php
/**
 * Main plugin bootstrap.
 *
 * @package Guided_Flow_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GFL_Plugin {

    /**
     * Singleton instance.
     *
     * @var GFL_Plugin|null
     */
    private static $instance = null;

    /**
     * Admin handler.
     *
     * @var GFL_Admin
     */
    private $admin;

    /**
     * Front-end handler.
     *
     * @var GFL_Frontend
     */
    private $frontend;

    /**
     * REST handler.
     *
     * @var GFL_REST
     */
    private $rest;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->maybe_upgrade();
        $this->load_textdomain();

        $this->admin    = new GFL_Admin();
        $this->frontend = new GFL_Frontend();
        $this->rest     = new GFL_REST();

        $this->admin->hooks();
        $this->frontend->hooks();
        $this->rest->hooks();
    }

    private function maybe_upgrade() {
        $installed_version = get_option( GFL_VERSION_KEY, '0.0.0' );
        if ( version_compare( (string) $installed_version, GFL_VERSION, '<' ) ) {
            GFL_Activator::activate();
        }
    }

    private function load_textdomain() {
        add_action(
            'plugins_loaded',
            function() {
                load_plugin_textdomain( 'guided-flow-leads', false, dirname( plugin_basename( GFL_FILE ) ) . '/languages/' );
            }
        );
    }
}

<?php
class GFL_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
    }

    public function menu() {

        add_menu_page(
            'Guided Flow Leads',
            'Guided Flow Leads',
            'manage_options',
            'gfl-settings',
            [$this, 'settings_page']
        );

        add_submenu_page(
            'gfl-settings',
            'Guide',
            'Guide',
            'manage_options',
            'gfl-guide',
            [$this, 'guide_page']
        );
    }

    public function settings_page() {
        echo '<h1>Flow Settings</h1>';
    }

    public function guide_page() {
        echo '<h1>Guide</h1>';
        echo '<p>This plugin helps you build guided flows.</p>';
    }
}

new GFL_Admin();
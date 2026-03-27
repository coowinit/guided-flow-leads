<?php
/**
 * Activation logic.
 *
 * @package Guided_Flow_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GFL_Activator {

    public static function activate() {
        self::create_tables();
        self::set_default_options();
        update_option( GFL_VERSION_KEY, GFL_VERSION );
    }

    public static function default_settings() {
        return array(
            'enabled'            => '1',
            'launcher_label'     => 'Chat with us',
            'window_title'       => 'Quick Inquiry',
            'window_subtitle'    => 'Answer a few questions and we will contact you soon.',
            'brand_color'        => '#6f2c91',
            'notification_email' => get_option( 'admin_email', '' ),
            'restart_label'      => 'Start over',
            'input_placeholder'  => 'Type your answer and click Send',
            'flow_steps_json'    => wp_json_encode( self::build_default_steps() ),
        );
    }

    public static function build_default_steps() {
        return array(
            array(
                'id'            => 'select_topic',
                'title'         => 'Step 1 · First Question',
                'type'          => 'choice',
                'message'       => 'Hi! What are you interested in today?',
                'placeholder'   => '',
                'save_to'       => 'primary_topic',
                'save_label_to' => 'primary_topic_label',
                'next'          => 'ask_contact_method',
                'options'       => array(
                    array( 'label' => 'Product Inquiry', 'value' => 'product_inquiry', 'next' => 'ask_contact_method' ),
                    array( 'label' => 'Request a Quote', 'value' => 'quote_request', 'next' => 'ask_contact_method' ),
                    array( 'label' => 'Talk to Sales', 'value' => 'talk_to_sales', 'next' => 'ask_contact_method' ),
                ),
            ),
            array(
                'id'            => 'ask_contact_method',
                'title'         => 'Step 2 · Contact Preference',
                'type'          => 'choice',
                'message'       => 'Okay. Could you please provide your Phone Number or Email Address so we can get in touch?',
                'placeholder'   => '',
                'save_to'       => 'contact_method',
                'save_label_to' => 'contact_method_label',
                'next'          => '',
                'options'       => array(
                    array( 'label' => 'Phone Number', 'value' => 'phone', 'next' => 'ask_phone' ),
                    array( 'label' => 'Email', 'value' => 'email', 'next' => 'ask_email' ),
                ),
            ),
            array(
                'id'            => 'ask_phone',
                'title'         => 'Step 3A · Phone Question',
                'type'          => 'input_phone',
                'message'       => "Sure! What's your Phone Number?",
                'placeholder'   => 'Type your phone number and click Send',
                'save_to'       => 'phone',
                'save_label_to' => '',
                'next'          => 'ask_business_type',
                'options'       => array(),
            ),
            array(
                'id'            => 'ask_email',
                'title'         => 'Step 3B · Email Question',
                'type'          => 'input_email',
                'message'       => "Sure! What's your Email Address?",
                'placeholder'   => 'Type your email address and click Send',
                'save_to'       => 'email',
                'save_label_to' => '',
                'next'          => 'ask_business_type',
                'options'       => array(),
            ),
            array(
                'id'            => 'ask_business_type',
                'title'         => 'Step 4 · Business Type',
                'type'          => 'choice',
                'message'       => 'By the way, what type of business are you running?',
                'placeholder'   => '',
                'save_to'       => 'business_type',
                'save_label_to' => 'business_type_label',
                'next'          => 'ask_details',
                'options'       => array(
                    array( 'label' => 'Retailer', 'value' => 'retailer', 'next' => 'ask_details' ),
                    array( 'label' => 'Distributor', 'value' => 'distributor', 'next' => 'ask_details' ),
                    array( 'label' => 'Contractor', 'value' => 'contractor', 'next' => 'ask_details' ),
                    array( 'label' => 'Homeowner', 'value' => 'homeowner', 'next' => 'ask_details' ),
                ),
            ),
            array(
                'id'            => 'ask_details',
                'title'         => 'Step 5 · Final Details',
                'type'          => 'input_text',
                'message'       => 'All right. Please tell us a little more about your project or request.',
                'placeholder'   => 'Type your answer and click Send',
                'save_to'       => 'project_details',
                'save_label_to' => '',
                'next'          => 'complete',
                'options'       => array(),
            ),
            array(
                'id'            => 'complete',
                'title'         => 'Completion',
                'type'          => 'complete',
                'message'       => 'Thanks. Our team will review your information and get in touch soon.',
                'placeholder'   => '',
                'save_to'       => '',
                'save_label_to' => '',
                'next'          => '',
                'options'       => array(),
            ),
        );
    }

    private static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $leads_table     = $wpdb->prefix . 'gfl_leads';
        $sessions_table  = $wpdb->prefix . 'gfl_sessions';

        $sql_leads = "CREATE TABLE {$leads_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(100) NOT NULL DEFAULT '',
            flow_key VARCHAR(80) NOT NULL DEFAULT 'default_flow',
            source_url VARCHAR(255) NOT NULL DEFAULT '',
            page_title VARCHAR(255) NOT NULL DEFAULT '',
            contact_name VARCHAR(120) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL DEFAULT '',
            phone VARCHAR(60) NOT NULL DEFAULT '',
            company VARCHAR(190) NOT NULL DEFAULT '',
            country VARCHAR(120) NOT NULL DEFAULT '',
            primary_topic VARCHAR(190) NOT NULL DEFAULT '',
            contact_method VARCHAR(30) NOT NULL DEFAULT '',
            lead_status VARCHAR(30) NOT NULL DEFAULT 'draft',
            summary_text LONGTEXT NULL,
            answers_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY flow_key (flow_key),
            KEY lead_status (lead_status),
            KEY email (email),
            KEY phone (phone)
        ) {$charset_collate};";

        $sql_sessions = "CREATE TABLE {$sessions_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(100) NOT NULL DEFAULT '',
            flow_key VARCHAR(80) NOT NULL DEFAULT 'default_flow',
            current_step VARCHAR(80) NOT NULL DEFAULT '',
            source_url VARCHAR(255) NOT NULL DEFAULT '',
            page_title VARCHAR(255) NOT NULL DEFAULT '',
            answers_json LONGTEXT NULL,
            lead_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            session_status VARCHAR(30) NOT NULL DEFAULT 'in_progress',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY session_id (session_id),
            KEY flow_key (flow_key),
            KEY current_step (current_step),
            KEY session_status (session_status),
            KEY lead_id (lead_id)
        ) {$charset_collate};";

        dbDelta( $sql_leads );
        dbDelta( $sql_sessions );
    }

    private static function set_default_options() {
        $defaults = self::default_settings();
        $current  = get_option( GFL_OPTION_KEY, array() );

        if ( ! is_array( $current ) ) {
            $current = array();
        }

        update_option( GFL_OPTION_KEY, wp_parse_args( $current, $defaults ) );
    }
}

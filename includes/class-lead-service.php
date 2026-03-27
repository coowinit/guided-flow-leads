<?php
/**
 * Lead storage service.
 *
 * @package Guided_Flow_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GFL_Lead_Service {

    /**
     * Table name.
     *
     * @var string
     */
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'gfl_leads';
    }

    public function get_table_name() {
        return $this->table_name;
    }

    public function upsert_session_lead( $session_id, $answers, $context, $status = 'draft', $lead_id = 0 ) {
        global $wpdb;

        $session_id = sanitize_text_field( (string) $session_id );
        $answers    = is_array( $answers ) ? $answers : array();
        $context    = is_array( $context ) ? $context : array();
        $status     = in_array( $status, array( 'draft', 'new', 'trash' ), true ) ? $status : 'draft';
        $lead_id    = absint( $lead_id );
        $payload    = array(
            'session_id'      => $session_id,
            'flow_key'        => 'default_flow',
            'source_url'      => isset( $context['page_url'] ) ? esc_url_raw( (string) $context['page_url'] ) : '',
            'page_title'      => isset( $context['page_title'] ) ? sanitize_text_field( (string) $context['page_title'] ) : '',
            'contact_name'    => $this->pick_answer( $answers, array( 'contact_name', 'name', 'full_name' ) ),
            'email'           => sanitize_email( $this->pick_answer( $answers, array( 'email' ) ) ),
            'phone'           => $this->pick_answer( $answers, array( 'phone' ) ),
            'company'         => $this->pick_answer( $answers, array( 'company' ) ),
            'country'         => $this->pick_answer( $answers, array( 'country' ) ),
            'primary_topic'   => $this->pick_answer( $answers, array( 'primary_topic', 'interest', 'product_interest' ) ),
            'contact_method'  => $this->pick_answer( $answers, array( 'contact_method' ) ),
            'lead_status'     => $status,
            'summary_text'    => $this->build_summary( $answers ),
            'answers_json'    => wp_json_encode( $answers ),
            'updated_at'      => current_time( 'mysql' ),
        );

        if ( $lead_id < 1 ) {
            $lead_id = $this->get_lead_id_by_session( $session_id );
        }

        if ( $lead_id > 0 ) {
            $updated = $wpdb->update(
                $this->table_name,
                $payload,
                array( 'id' => $lead_id ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );

            if ( false === $updated ) {
                return new WP_Error( 'gfl_lead_update_failed', __( 'Could not update the lead.', 'guided-flow-leads' ) );
            }

            if ( 'new' === $status ) {
                $this->maybe_send_notification( $payload );
            }

            return $lead_id;
        }

        $payload['created_at'] = current_time( 'mysql' );
        $inserted              = $wpdb->insert(
            $this->table_name,
            $payload,
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'gfl_lead_insert_failed', __( 'Could not create the lead.', 'guided-flow-leads' ) );
        }

        $new_id = (int) $wpdb->insert_id;

        if ( 'new' === $status ) {
            $this->maybe_send_notification( $payload );
        }

        return $new_id;
    }

    public function get_leads_page( $page = 1, $per_page = 20, $view = 'active', $search = '' ) {
        global $wpdb;

        $page      = max( 1, absint( $page ) );
        $per_page  = max( 1, absint( $per_page ) );
        $offset    = ( $page - 1 ) * $per_page;
        $search    = trim( sanitize_text_field( (string) $search ) );
        $where_sql = $this->build_search_where( $view, $search, $where_args );
        $sql       = "SELECT * FROM {$this->table_name} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $args      = array_merge( $where_args, array( $per_page, $offset ) );
        $prepared  = empty( $args ) ? $sql : $wpdb->prepare( $sql, $args );
        $rows      = $wpdb->get_results( $prepared, ARRAY_A );

        return is_array( $rows ) ? $rows : array();
    }

    public function get_lead_count( $view = 'active', $search = '' ) {
        global $wpdb;

        $search    = trim( sanitize_text_field( (string) $search ) );
        $where_sql = $this->build_search_where( $view, $search, $where_args );
        $sql       = "SELECT COUNT(*) FROM {$this->table_name} {$where_sql}";
        $prepared  = empty( $where_args ) ? $sql : $wpdb->prepare( $sql, $where_args );

        return (int) $wpdb->get_var( $prepared );
    }

    public function get_total_count() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
    }

    public function get_lead_by_id( $lead_id ) {
        global $wpdb;

        $lead_id = absint( $lead_id );
        if ( $lead_id < 1 ) {
            return array();
        }

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1", $lead_id ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : array();
    }

    public function move_to_trash( $ids ) {
        return $this->update_status_for_ids( $ids, 'trash' );
    }

    public function restore_from_trash( $ids ) {
        return $this->update_status_for_ids( $ids, 'new' );
    }

    public function delete_permanently( $ids ) {
        global $wpdb;

        $ids = $this->sanitize_ids( $ids );
        if ( empty( $ids ) ) {
            return 0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql          = $wpdb->prepare( "DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})", $ids );
        $deleted      = $wpdb->query( $sql );

        return is_numeric( $deleted ) ? (int) $deleted : 0;
    }

    public function empty_trash() {
        global $wpdb;
        $deleted = $wpdb->query( "DELETE FROM {$this->table_name} WHERE lead_status = 'trash'" );
        return is_numeric( $deleted ) ? (int) $deleted : 0;
    }

    private function update_status_for_ids( $ids, $status ) {
        global $wpdb;

        $ids = $this->sanitize_ids( $ids );
        if ( empty( $ids ) ) {
            return 0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql          = $wpdb->prepare( "UPDATE {$this->table_name} SET lead_status = %s, updated_at = %s WHERE id IN ({$placeholders})", array_merge( array( $status, current_time( 'mysql' ) ), $ids ) );
        $updated      = $wpdb->query( $sql );

        return is_numeric( $updated ) ? (int) $updated : 0;
    }

    private function sanitize_ids( $ids ) {
        $ids = is_array( $ids ) ? $ids : array();
        $ids = array_map( 'absint', $ids );
        return array_values( array_filter( $ids ) );
    }

    private function get_lead_id_by_session( $session_id ) {
        global $wpdb;

        if ( '' === trim( (string) $session_id ) ) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE session_id = %s ORDER BY id DESC LIMIT 1",
                $session_id
            )
        );
    }

    private function pick_answer( $answers, $keys ) {
        foreach ( (array) $keys as $key ) {
            if ( isset( $answers[ $key ] ) && '' !== trim( (string) $answers[ $key ] ) ) {
                return sanitize_text_field( (string) $answers[ $key ] );
            }
        }

        return '';
    }

    private function build_summary( $answers ) {
        $answers = is_array( $answers ) ? $answers : array();
        if ( empty( $answers ) ) {
            return '';
        }

        $lines = array();

        if ( ! empty( $answers['primary_topic_label'] ) ) {
            $lines[] = 'Inquiry Type: ' . sanitize_text_field( (string) $answers['primary_topic_label'] );
        } elseif ( ! empty( $answers['primary_topic'] ) ) {
            $lines[] = 'Inquiry Type: ' . sanitize_text_field( ucwords( str_replace( array( '_', '-' ), ' ', (string) $answers['primary_topic'] ) ) );
        }

        if ( ! empty( $answers['contact_method_label'] ) ) {
            $lines[] = 'Contact Method: ' . sanitize_text_field( (string) $answers['contact_method_label'] );
        }

        if ( ! empty( $answers['email'] ) ) {
            $lines[] = 'Email: ' . sanitize_text_field( (string) $answers['email'] );
        }

        if ( ! empty( $answers['phone'] ) ) {
            $lines[] = 'Phone: ' . sanitize_text_field( (string) $answers['phone'] );
        }

        if ( ! empty( $answers['business_type_label'] ) ) {
            $lines[] = 'Business Type: ' . sanitize_text_field( (string) $answers['business_type_label'] );
        }

        if ( ! empty( $answers['project_details'] ) ) {
            $lines[] = 'Project Details: ' . sanitize_text_field( (string) $answers['project_details'] );
        }

        return implode( "\n", $lines );
    }

    private function build_search_where( $view, $search, &$where_args ) {
        global $wpdb;

        $where_args = array();
        $clauses    = array();
        $clauses[]  = ( 'trash' === $view ) ? "lead_status = 'trash'" : "lead_status <> 'trash'";

        if ( '' !== $search ) {
            $like       = '%' . $wpdb->esc_like( $search ) . '%';
            $clauses[]  = '(primary_topic LIKE %s OR email LIKE %s OR phone LIKE %s OR company LIKE %s OR contact_name LIKE %s OR source_url LIKE %s OR page_title LIKE %s OR summary_text LIKE %s)';
            $where_args = array_fill( 0, 8, $like );
        }

        return 'WHERE ' . implode( ' AND ', $clauses );
    }

    private function maybe_send_notification( $payload ) {
        $settings  = get_option( GFL_OPTION_KEY, array() );
        $recipient = isset( $settings['notification_email'] ) ? sanitize_email( (string) $settings['notification_email'] ) : '';

        if ( '' === $recipient ) {
            return false;
        }

        $subject = __( '[Guided Flow Leads] New lead received', 'guided-flow-leads' );
        $body    = array(
            __( 'A new guided lead has been submitted.', 'guided-flow-leads' ),
            '',
            sprintf( __( 'Topic: %s', 'guided-flow-leads' ), $payload['primary_topic'] ?? '' ),
            sprintf( __( 'Email: %s', 'guided-flow-leads' ), $payload['email'] ?? '' ),
            sprintf( __( 'Phone: %s', 'guided-flow-leads' ), $payload['phone'] ?? '' ),
            sprintf( __( 'Page: %s', 'guided-flow-leads' ), $payload['source_url'] ?? '' ),
            '',
            __( 'Summary:', 'guided-flow-leads' ),
            (string) ( $payload['summary_text'] ?? '' ),
        );

        return (bool) wp_mail( $recipient, $subject, implode( "\n", $body ) );
    }
}

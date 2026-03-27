<?php
/**
 * Guided flow service.
 *
 * @package Guided_Flow_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GFL_Flow_Service {

    /**
     * Sessions table.
     *
     * @var string
     */
    private $table_name;

    /**
     * Lead service.
     *
     * @var GFL_Lead_Service
     */
    private $lead_service;

    public function __construct() {
        global $wpdb;

        $this->table_name   = $wpdb->prefix . 'gfl_sessions';
        $this->lead_service = new GFL_Lead_Service();
    }

    public function start_session( $context = array() ) {
        global $wpdb;

        $context    = $this->sanitize_context( $context );
        $definition = $this->get_flow_definition();
        $start_step = $definition['start_step'];
        $session_id = wp_generate_uuid4();

        $inserted = $wpdb->insert(
            $this->table_name,
            array(
                'session_id'     => $session_id,
                'flow_key'       => $definition['id'],
                'current_step'   => $start_step,
                'source_url'     => $context['page_url'],
                'page_title'     => $context['page_title'],
                'answers_json'   => wp_json_encode( array() ),
                'lead_id'        => 0,
                'session_status' => 'in_progress',
                'created_at'     => current_time( 'mysql' ),
                'updated_at'     => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new WP_Error( 'gfl_session_create_failed', __( 'Could not create the chat session.', 'guided-flow-leads' ) );
        }

        $lead_id = $this->lead_service->upsert_session_lead( $session_id, array(), $context, 'draft', 0 );

        $wpdb->update(
            $this->table_name,
            array(
                'lead_id'    => is_wp_error( $lead_id ) ? 0 : absint( $lead_id ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'session_id' => $session_id ),
            array( '%d', '%s' ),
            array( '%s' )
        );

        return $this->build_payload( $session_id, $start_step, $definition['steps'][ $start_step ], array(), false, false );
    }

    public function restart_session( $session_id, $context = array() ) {
        global $wpdb;

        $session_id = sanitize_text_field( (string) $session_id );
        $context    = $this->sanitize_context( $context );
        $definition = $this->get_flow_definition();
        $start_step = $definition['start_step'];

        if ( '' !== $session_id ) {
            $wpdb->update(
                $this->table_name,
                array(
                    'session_status' => 'restarted',
                    'updated_at'     => current_time( 'mysql' ),
                ),
                array( 'session_id' => $session_id ),
                array( '%s', '%s' ),
                array( '%s' )
            );
        }

        return $this->start_session( $context );
    }

    public function resume_session( $session_id ) {
        $session_id = sanitize_text_field( (string) $session_id );

        if ( '' === $session_id ) {
            return new WP_Error( 'gfl_session_missing', __( 'Missing session ID.', 'guided-flow-leads' ) );
        }

        $session = $this->get_session( $session_id );
        if ( empty( $session ) ) {
            return new WP_Error( 'gfl_session_not_found', __( 'The session could not be found.', 'guided-flow-leads' ) );
        }

        $definition = $this->get_flow_definition();
        $step_id    = isset( $session['current_step'] ) ? sanitize_key( (string) $session['current_step'] ) : $definition['start_step'];
        $answers    = $this->decode_answers( $session['answers_json'] ?? '' );
        $completed  = 'completed' === (string) ( $session['session_status'] ?? '' );

        if ( ! isset( $definition['steps'][ $step_id ] ) ) {
            $step_id = $definition['start_step'];
        }

        return $this->build_payload( $session_id, $step_id, $definition['steps'][ $step_id ], $answers, $completed, $completed );
    }

    public function submit_answer( $session_id, $step_id, $answer, $context = array() ) {
        global $wpdb;

        $session_id = sanitize_text_field( (string) $session_id );
        $step_id    = sanitize_key( (string) $step_id );
        $answer     = trim( (string) $answer );
        $context    = $this->sanitize_context( $context );

        if ( '' === $session_id || '' === $step_id ) {
            return new WP_Error( 'gfl_missing_data', __( 'Missing session data.', 'guided-flow-leads' ) );
        }

        $session = $this->get_session( $session_id );
        if ( empty( $session ) ) {
            return new WP_Error( 'gfl_session_not_found', __( 'The session could not be found.', 'guided-flow-leads' ) );
        }

        if ( 'completed' === (string) ( $session['session_status'] ?? '' ) ) {
            return $this->resume_session( $session_id );
        }

        $definition   = $this->get_flow_definition();
        $current_step = isset( $session['current_step'] ) ? sanitize_key( (string) $session['current_step'] ) : $definition['start_step'];

        if ( $step_id !== $current_step ) {
            $step_id = $current_step;
        }

        if ( ! isset( $definition['steps'][ $step_id ] ) ) {
            return new WP_Error( 'gfl_invalid_step', __( 'The requested step is invalid.', 'guided-flow-leads' ) );
        }

        $step    = $definition['steps'][ $step_id ];
        $answers = $this->decode_answers( $session['answers_json'] ?? '' );
        $result  = $this->validate_step_answer( $step, $answer );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! empty( $step['save_to'] ) ) {
            $answers[ $step['save_to'] ] = $result['store'];
        }

        if ( ! empty( $step['save_label_to'] ) ) {
            $answers[ $step['save_label_to'] ] = $result['display'] ?? $result['store'];
        }

        $next_step_id = isset( $result['next'] ) ? sanitize_key( (string) $result['next'] ) : sanitize_key( (string) ( $step['next'] ?? '' ) );
        if ( '' === $next_step_id || ! isset( $definition['steps'][ $next_step_id ] ) ) {
            return new WP_Error( 'gfl_invalid_next_step', __( 'Could not determine the next step.', 'guided-flow-leads' ) );
        }

        $completed = 'complete' === $definition['steps'][ $next_step_id ]['type'];
        $lead_id   = $this->lead_service->upsert_session_lead(
            $session_id,
            $answers,
            $context,
            $completed ? 'new' : 'draft',
            isset( $session['lead_id'] ) ? absint( $session['lead_id'] ) : 0
        );

        $wpdb->update(
            $this->table_name,
            array(
                'current_step'   => $next_step_id,
                'answers_json'   => wp_json_encode( $answers ),
                'lead_id'        => is_wp_error( $lead_id ) ? absint( $session['lead_id'] ?? 0 ) : absint( $lead_id ),
                'session_status' => $completed ? 'completed' : 'in_progress',
                'updated_at'     => current_time( 'mysql' ),
            ),
            array( 'session_id' => $session_id ),
            array( '%s', '%s', '%d', '%s', '%s' ),
            array( '%s' )
        );

        return $this->build_payload(
            $session_id,
            $next_step_id,
            $definition['steps'][ $next_step_id ],
            $answers,
            $completed,
            $completed && ! is_wp_error( $lead_id )
        );
    }

    public function get_frontend_config() {
        $definition = $this->get_flow_definition();
        $settings   = $this->get_settings();

        return array(
            'id'                    => $definition['id'],
            'steps_count'           => $this->count_interactive_steps( $definition ),
            'input_placeholder'     => $settings['input_placeholder'],
            'completed_placeholder' => __( 'Conversation completed.', 'guided-flow-leads' ),
        );
    }

    private function get_session( $session_id ) {
        global $wpdb;
        $sql = $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE session_id = %s LIMIT 1", $session_id );
        $row = $wpdb->get_row( $sql, ARRAY_A );
        return is_array( $row ) ? $row : array();
    }

    private function validate_step_answer( $step, $answer ) {
        $type = isset( $step['type'] ) ? (string) $step['type'] : 'input_text';

        if ( 'choice' === $type ) {
            $normalized = strtolower( trim( $answer ) );
            foreach ( (array) ( $step['options'] ?? array() ) as $option ) {
                $label = strtolower( trim( (string) ( $option['label'] ?? '' ) ) );
                $value = strtolower( trim( (string) ( $option['value'] ?? '' ) ) );
                if ( '' !== $normalized && ( $normalized === $label || $normalized === $value ) ) {
                    return array(
                        'store'   => sanitize_text_field( (string) ( $option['value'] ?? '' ) ),
                        'display' => sanitize_text_field( (string) ( $option['label'] ?? '' ) ),
                        'next'    => sanitize_key( (string) ( $option['next'] ?? '' ) ),
                    );
                }
            }

            return new WP_Error( 'gfl_invalid_choice', __( 'Please choose one of the available options.', 'guided-flow-leads' ) );
        }

        if ( '' === $answer ) {
            return new WP_Error( 'gfl_empty_answer', __( 'Please enter a value before sending.', 'guided-flow-leads' ) );
        }

        if ( 'input_email' === $type ) {
            $email = sanitize_email( $answer );
            if ( ! is_email( $email ) ) {
                return new WP_Error( 'gfl_invalid_email', __( 'Please enter a valid email address.', 'guided-flow-leads' ) );
            }

            return array(
                'store'   => $email,
                'display' => $email,
            );
        }

        if ( 'input_phone' === $type ) {
            $phone = preg_replace( '/[^0-9+()\-\s]/', '', $answer );
            if ( strlen( preg_replace( '/\D/', '', $phone ) ) < 5 ) {
                return new WP_Error( 'gfl_invalid_phone', __( 'Please enter a valid phone number.', 'guided-flow-leads' ) );
            }

            return array(
                'store'   => sanitize_text_field( $phone ),
                'display' => sanitize_text_field( $phone ),
            );
        }

        return array(
            'store'   => sanitize_text_field( $answer ),
            'display' => sanitize_text_field( $answer ),
        );
    }

    private function build_payload( $session_id, $step_id, $step, $answers, $completed = false, $lead_saved = false ) {
        $definition = $this->get_flow_definition();
        $settings   = $this->get_settings();

        return array(
            'success'           => true,
            'session_id'        => $session_id,
            'flow_id'           => $definition['id'],
            'step_id'           => $step_id,
            'assistant_message' => isset( $step['message'] ) ? (string) $step['message'] : '',
            'input'             => array(
                'type'        => isset( $step['type'] ) ? (string) $step['type'] : 'input_text',
                'placeholder' => isset( $step['placeholder'] ) ? (string) $step['placeholder'] : $settings['input_placeholder'],
                'required'    => 'complete' !== (string) ( $step['type'] ?? '' ),
            ),
            'options'           => ( 'choice' === (string) ( $step['type'] ?? '' ) ) ? (array) ( $step['options'] ?? array() ) : array(),
            'meta'              => array(
                'completed'     => (bool) $completed,
                'lead_saved'    => (bool) $lead_saved,
                'progress'      => $this->get_progress_position( $step_id, $definition ),
                'steps_count'   => $this->count_interactive_steps( $definition ),
                'answers'       => $answers,
                'can_restart'   => true,
                'restart_label' => $settings['restart_label'],
            ),
        );
    }

    private function sanitize_context( $context ) {
        $context = is_array( $context ) ? $context : array();

        return array(
            'page_url'   => isset( $context['page_url'] ) ? esc_url_raw( (string) $context['page_url'] ) : '',
            'page_title' => isset( $context['page_title'] ) ? sanitize_text_field( (string) $context['page_title'] ) : '',
        );
    }

    private function decode_answers( $json ) {
        $answers = json_decode( (string) $json, true );
        return is_array( $answers ) ? $answers : array();
    }

    private function get_flow_definition() {
        $settings = $this->get_settings();
        $steps    = json_decode( (string) ( $settings['flow_steps_json'] ?? '' ), true );

        if ( ! is_array( $steps ) || empty( $steps ) ) {
            $steps = GFL_Activator::build_default_steps();
        }

        $normalized_steps = array();
        $start_step       = '';

        foreach ( $steps as $step ) {
            if ( ! is_array( $step ) || empty( $step['id'] ) ) {
                continue;
            }

            $id = sanitize_key( (string) $step['id'] );
            if ( '' === $id ) {
                continue;
            }

            if ( '' === $start_step ) {
                $start_step = $id;
            }

            $normalized = array(
                'type'          => sanitize_key( (string) ( $step['type'] ?? 'input_text' ) ),
                'message'       => sanitize_textarea_field( (string) ( $step['message'] ?? '' ) ),
                'placeholder'   => sanitize_text_field( (string) ( $step['placeholder'] ?? '' ) ),
                'save_to'       => sanitize_key( (string) ( $step['save_to'] ?? '' ) ),
                'save_label_to' => sanitize_key( (string) ( $step['save_label_to'] ?? '' ) ),
                'next'          => sanitize_key( (string) ( $step['next'] ?? '' ) ),
                'options'       => array(),
            );

            if ( 'choice' === $normalized['type'] ) {
                foreach ( (array) ( $step['options'] ?? array() ) as $option ) {
                    $label = sanitize_text_field( (string) ( $option['label'] ?? '' ) );
                    if ( '' === $label ) {
                        continue;
                    }
                    $normalized['options'][] = array(
                        'label' => $label,
                        'value' => sanitize_text_field( (string) ( $option['value'] ?? sanitize_title( $label ) ) ),
                        'next'  => sanitize_key( (string) ( $option['next'] ?? $normalized['next'] ) ),
                    );
                }
            }

            $normalized_steps[ $id ] = $normalized;
        }

        if ( empty( $normalized_steps ) ) {
            return array(
                'id'         => 'default_flow',
                'start_step' => 'complete',
                'steps'      => array(
                    'complete' => array(
                        'type'        => 'complete',
                        'message'     => __( 'Thanks. Our team will review your information and get in touch soon.', 'guided-flow-leads' ),
                        'placeholder' => '',
                        'save_to'     => '',
                        'save_label_to' => '',
                        'next'        => '',
                        'options'     => array(),
                    ),
                ),
            );
        }

        if ( '' === $start_step ) {
            $keys = array_keys( $normalized_steps );
            $start_step = reset( $keys );
        }

        return array(
            'id'         => 'default_flow',
            'start_step' => $start_step,
            'steps'      => $normalized_steps,
        );
    }

    private function build_configured_options( $settings, $prefix, $count, $default_next ) {
        $options = array();

        for ( $i = 1; $i <= $count; $i++ ) {
            $label = trim( (string) ( $settings[ $prefix . $i . '_label' ] ?? '' ) );
            $value = trim( (string) ( $settings[ $prefix . $i . '_value' ] ?? '' ) );

            if ( '' === $label ) {
                continue;
            }

            if ( '' === $value ) {
                $value = sanitize_title( $label );
            }

            $options[] = array(
                'label' => sanitize_text_field( $label ),
                'value' => sanitize_text_field( $value ),
                'next'  => sanitize_key( $default_next ),
            );
        }

        return $options;
    }

    private function parse_options( $raw, $default_next ) {
        $raw     = trim( (string) $raw );
        $lines   = preg_split( '/\r\n|\r|\n/', $raw );
        $options = array();

        foreach ( (array) $lines as $line ) {
            $line = trim( (string) $line );
            if ( '' === $line ) {
                continue;
            }

            $parts = array_map( 'trim', explode( '|', $line ) );
            $label = $parts[0] ?? '';
            $value = $parts[1] ?? sanitize_title( $label );
            $next  = $parts[2] ?? $default_next;

            if ( '' === $label || '' === $value ) {
                continue;
            }

            $options[] = array(
                'label' => sanitize_text_field( $label ),
                'value' => sanitize_text_field( $value ),
                'next'  => sanitize_key( (string) $next ),
            );
        }

        return $options;
    }

    private function count_interactive_steps( $definition ) {
        $count = 0;
        foreach ( (array) ( $definition['steps'] ?? array() ) as $step ) {
            if ( 'complete' !== (string) ( $step['type'] ?? '' ) ) {
                $count++;
            }
        }
        return $count;
    }

    private function get_progress_position( $step_id, $definition ) {
        $position = 0;
        foreach ( array_keys( (array) ( $definition['steps'] ?? array() ) ) as $key ) {
            if ( 'complete' === (string) ( $definition['steps'][ $key ]['type'] ?? '' ) ) {
                continue;
            }
            $position++;
            if ( $key === $step_id ) {
                return $position;
            }
        }

        return $position;
    }

    private function get_settings() {
        $settings = get_option( GFL_OPTION_KEY, array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        return wp_parse_args( $settings, GFL_Activator::default_settings() );
    }
}

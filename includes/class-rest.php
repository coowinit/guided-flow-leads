<?php
/**
 * REST endpoints.
 *
 * @package Guided_Flow_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GFL_REST {

    /**
     * Flow service.
     *
     * @var GFL_Flow_Service
     */
    private $flow_service;

    public function __construct() {
        $this->flow_service = new GFL_Flow_Service();
    }

    public function hooks() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route(
            'gfl/v1',
            '/flow/start',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_flow_start' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'gfl/v1',
            '/flow/next',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_flow_next' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'gfl/v1',
            '/flow/resume',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_flow_resume' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'gfl/v1',
            '/flow/restart',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_flow_restart' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function handle_flow_start( WP_REST_Request $request ) {
        $result = $this->flow_service->start_session(
            array(
                'page_url'   => (string) $request->get_param( 'page_url' ),
                'page_title' => (string) $request->get_param( 'page_title' ),
            )
        );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => $result->get_error_message(),
                ),
                400
            );
        }

        return new WP_REST_Response( $result, 200 );
    }

    public function handle_flow_next( WP_REST_Request $request ) {
        $result = $this->flow_service->submit_answer(
            (string) $request->get_param( 'session_id' ),
            (string) $request->get_param( 'step_id' ),
            (string) $request->get_param( 'answer' ),
            array(
                'page_url'   => (string) $request->get_param( 'page_url' ),
                'page_title' => (string) $request->get_param( 'page_title' ),
            )
        );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => $result->get_error_message(),
                ),
                400
            );
        }

        return new WP_REST_Response( $result, 200 );
    }

    public function handle_flow_resume( WP_REST_Request $request ) {
        $result = $this->flow_service->resume_session( (string) $request->get_param( 'session_id' ) );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => $result->get_error_message(),
                ),
                404
            );
        }

        return new WP_REST_Response( $result, 200 );
    }

    public function handle_flow_restart( WP_REST_Request $request ) {
        $result = $this->flow_service->restart_session(
            (string) $request->get_param( 'session_id' ),
            array(
                'page_url'   => (string) $request->get_param( 'page_url' ),
                'page_title' => (string) $request->get_param( 'page_title' ),
            )
        );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => $result->get_error_message(),
                ),
                400
            );
        }

        return new WP_REST_Response( $result, 200 );
    }
}

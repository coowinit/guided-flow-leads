<?php
class GFL_API {

    public function __construct() {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes() {
        register_rest_route('gfl/v1', '/flow/start', [
            'methods' => 'GET',
            'callback' => [$this, 'start']
        ]);
    }

    public function start() {
        return [
            'message' => 'Welcome!',
            'step' => 'step_1'
        ];
    }
}

new GFL_API();
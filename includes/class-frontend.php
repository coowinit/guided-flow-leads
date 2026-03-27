<?php
/**
 * Front-end widget loader.
 *
 * @package Guided_Flow_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GFL_Frontend {

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
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_widget' ) );
    }

    public function enqueue_assets() {
        $settings = $this->get_settings();

        if ( '1' !== (string) $settings['enabled'] ) {
            return;
        }

        wp_enqueue_style(
            'guided-flow-leads-frontend',
            GFL_URL . 'assets/css/frontend.css',
            array(),
            GFL_VERSION
        );

        wp_enqueue_script(
            'guided-flow-leads-frontend',
            GFL_URL . 'assets/js/frontend.js',
            array(),
            GFL_VERSION,
            true
        );

        $page_title = wp_get_document_title();
        if ( ! $page_title ) {
            $page_title = get_bloginfo( 'name' );
        }

        wp_localize_script(
            'guided-flow-leads-frontend',
            'guidedFlowLeads',
            array(
                'brandColor'     => $settings['brand_color'],
                'launcherLabel'  => $settings['launcher_label'],
                'windowTitle'    => $settings['window_title'],
                'windowSubtext'  => $settings['window_subtitle'],
                'pageTitle'      => $page_title,
                'pageUrl'        => home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) ),
                'flow'           => $this->flow_service->get_frontend_config(),
                'flowStartUrl'   => rest_url( 'gfl/v1/flow/start' ),
                'flowNextUrl'    => rest_url( 'gfl/v1/flow/next' ),
                'flowResumeUrl'  => rest_url( 'gfl/v1/flow/resume' ),
                'flowRestartUrl' => rest_url( 'gfl/v1/flow/restart' ),
                'strings'        => array(
                    'assistant'      => __( 'Guide', 'guided-flow-leads' ),
                    'you'            => __( 'You', 'guided-flow-leads' ),
                    'send'           => __( 'Send', 'guided-flow-leads' ),
                    'sending'        => __( 'Sending...', 'guided-flow-leads' ),
                    'status'         => __( 'Guided flow ready.', 'guided-flow-leads' ),
                    'input'          => $settings['input_placeholder'],
                    'empty'          => __( 'Please enter your answer first.', 'guided-flow-leads' ),
                    'error'          => __( 'Something went wrong. Please try again.', 'guided-flow-leads' ),
                    'flowComplete'   => __( 'Conversation completed.', 'guided-flow-leads' ),
                    'flowStartError' => __( 'Could not start the guided flow.', 'guided-flow-leads' ),
                    'restart'        => $settings['restart_label'],
                    'restarting'     => __( 'Restarting...', 'guided-flow-leads' ),
                ),
            )
        );

        wp_add_inline_style(
            'guided-flow-leads-frontend',
            ':root{--gfl-brand:' . esc_html( $settings['brand_color'] ) . ';}'
        );
    }

    public function render_widget() {
        $settings = $this->get_settings();

        if ( '1' !== (string) $settings['enabled'] ) {
            return;
        }
        ?>
        <button id="gflLauncher" class="gfl-launcher" type="button" aria-expanded="false">
            <span class="gfl-launcher__icon">💬</span>
            <span class="gfl-launcher__label"><?php echo esc_html( $settings['launcher_label'] ); ?></span>
        </button>

        <section id="gflWindow" class="gfl-window" hidden>
            <header class="gfl-window__header">
                <div>
                    <strong class="gfl-window__title"><?php echo esc_html( $settings['window_title'] ); ?></strong>
                    <p class="gfl-window__subtitle"><?php echo esc_html( $settings['window_subtitle'] ); ?></p>
                </div>
                <button id="gflClose" type="button" class="gfl-window__close" aria-label="<?php esc_attr_e( 'Close', 'guided-flow-leads' ); ?>">×</button>
            </header>

            <div id="gflStatus" class="gfl-window__status"><?php esc_html_e( 'Guided flow ready.', 'guided-flow-leads' ); ?></div>
            <div class="gfl-window__body">
                <div id="gflMessages" class="gfl-window__messages"></div>
            </div>
            <div id="gflChoices" class="gfl-window__choices" hidden></div>
            <form id="gflForm" class="gfl-window__form">
                <textarea id="gflInput" rows="3" placeholder="<?php echo esc_attr( $settings['input_placeholder'] ); ?>"></textarea>
                <div class="gfl-window__actions">
                    <button id="gflRestart" class="gfl-button gfl-button--ghost" type="button"><?php echo esc_html( $settings['restart_label'] ); ?></button>
                    <button id="gflSend" class="gfl-button gfl-button--primary" type="submit"><?php esc_html_e( 'Send', 'guided-flow-leads' ); ?></button>
                </div>
            </form>
        </section>
        <?php
    }

    private function get_settings() {
        $settings = get_option( GFL_OPTION_KEY, array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        return wp_parse_args( $settings, GFL_Activator::default_settings() );
    }
}

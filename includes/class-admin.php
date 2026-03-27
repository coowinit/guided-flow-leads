<?php
/**
 * Admin pages and settings.
 *
 * @package Guided_Flow_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GFL_Admin {

    /**
     * Option key.
     *
     * @var string
     */
    private $option_name = GFL_OPTION_KEY;

    /**
     * Lead service.
     *
     * @var GFL_Lead_Service
     */
    private $lead_service;

    public function __construct() {
        $this->lead_service = new GFL_Lead_Service();
    }

    public function hooks() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_post_gfl_leads_bulk', array( $this, 'handle_leads_bulk' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( GFL_FILE ), array( $this, 'add_settings_link' ) );
    }

    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=guided-flow-leads' ) ),
            esc_html__( 'Flow Settings', 'guided-flow-leads' )
        );

        array_unshift( $links, $settings_link );
        return $links;
    }

    public function enqueue_assets( $hook ) {
        if ( false === strpos( (string) $hook, 'guided-flow-leads' ) ) {
            return;
        }

        wp_enqueue_style(
            'guided-flow-leads-admin',
            GFL_URL . 'assets/css/admin.css',
            array(),
            GFL_VERSION
        );

        if ( false !== strpos( (string) $hook, 'guided-flow-leads' ) && false === strpos( (string) $hook, 'guided-flow-leads-leads' ) ) {
            wp_enqueue_script(
                'guided-flow-leads-admin',
                GFL_URL . 'assets/js/admin.js',
                array( 'jquery' ),
                GFL_VERSION,
                true
            );
        }
    }

    public function register_admin_menu() {
        add_menu_page(
            __( 'Guided Flow Leads', 'guided-flow-leads' ),
            __( 'Guided Flow Leads', 'guided-flow-leads' ),
            'manage_options',
            'guided-flow-leads',
            array( $this, 'render_settings_page' ),
            'dashicons-format-chat',
            58
        );

        add_submenu_page(
            'guided-flow-leads',
            __( 'Flow Settings', 'guided-flow-leads' ),
            __( 'Flow Settings', 'guided-flow-leads' ),
            'manage_options',
            'guided-flow-leads',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'guided-flow-leads',
            __( 'Leads', 'guided-flow-leads' ),
            __( 'Leads', 'guided-flow-leads' ),
            'manage_options',
            'guided-flow-leads-leads',
            array( $this, 'render_leads_page' )
        );
    }

    public function register_settings() {
        register_setting(
            'gfl_settings_group',
            $this->option_name,
            array( $this, 'sanitize_settings' )
        );
    }

    public function sanitize_settings( $settings ) {
        $defaults  = GFL_Activator::default_settings();
        $settings  = is_array( $settings ) ? $settings : array();
        $sanitized = array();

        foreach ( $defaults as $key => $default_value ) {
            $value = $settings[ $key ] ?? $default_value;

            if ( 'enabled' === $key ) {
                $sanitized[ $key ] = isset( $settings[ $key ] ) && '1' === (string) $settings[ $key ] ? '1' : '0';
                continue;
            }

            if ( 'notification_email' === $key ) {
                $sanitized[ $key ] = sanitize_email( (string) $value );
                continue;
            }

            if ( 'brand_color' === $key ) {
                $color             = sanitize_hex_color( (string) $value );
                $sanitized[ $key ] = $color ? $color : $default_value;
                continue;
            }

            if ( 'flow_steps_json' === $key ) {
                continue;
            }

            $sanitized[ $key ] = sanitize_textarea_field( (string) $value );
        }

        $flow_steps                    = isset( $settings['flow_steps'] ) ? $this->sanitize_flow_steps( $settings['flow_steps'] ) : $this->get_default_flow_steps();
        $sanitized['flow_steps_json']  = wp_json_encode( $flow_steps );

        return $sanitized;
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings   = $this->get_settings();
        $flow_steps = $this->get_flow_steps( $settings );
        ?>
        <div class="wrap gfl-wrap">
            <div class="gfl-pagehead">
                <div>
                    <h1><?php esc_html_e( 'Flow Settings', 'guided-flow-leads' ); ?></h1>
                    <p class="description"><?php esc_html_e( 'Build the visitor journey with reusable steps. Add, remove, and reorder steps to shape your lead capture flow.', 'guided-flow-leads' ); ?></p>
                </div>
                <span class="gfl-badge"><?php echo esc_html( 'v' . GFL_VERSION ); ?></span>
            </div>

            <form method="post" action="options.php" class="gfl-editor-form">
                <?php settings_fields( 'gfl_settings_group' ); ?>

                <div class="gfl-settings-layout">
                    <div class="gfl-settings-main">
                        <section class="gfl-card gfl-card--section">
                            <div class="gfl-card__head">
                                <h2><?php esc_html_e( 'Widget Basics', 'guided-flow-leads' ); ?></h2>
                                <p><?php esc_html_e( 'Control the floating widget, launcher text, and general presentation.', 'guided-flow-leads' ); ?></p>
                            </div>
                            <div class="gfl-field-grid gfl-field-grid--two">
                                <?php $this->render_toggle_field( 'enabled', __( 'Enable widget', 'guided-flow-leads' ), __( 'Show the floating guided chat on the front end.', 'guided-flow-leads' ) ); ?>
                                <?php $this->render_text_input_field( 'brand_color', __( 'Brand color', 'guided-flow-leads' ), __( 'Used for the launcher, buttons, and accents.', 'guided-flow-leads' ), '#6f2c91', 'small-text' ); ?>
                                <?php $this->render_text_input_field( 'launcher_label', __( 'Launcher label', 'guided-flow-leads' ), __( 'Text shown on the floating launcher button.', 'guided-flow-leads' ) ); ?>
                                <?php $this->render_text_input_field( 'restart_label', __( 'Restart button label', 'guided-flow-leads' ), __( 'Shown inside the chat window.', 'guided-flow-leads' ) ); ?>
                                <?php $this->render_text_input_field( 'window_title', __( 'Window title', 'guided-flow-leads' ), __( 'Main heading inside the chat window.', 'guided-flow-leads' ) ); ?>
                                <?php $this->render_text_input_field( 'notification_email', __( 'Notification email', 'guided-flow-leads' ), __( 'Receive an email when a visitor finishes the flow.', 'guided-flow-leads' ), get_option( 'admin_email', '' ) ); ?>
                            </div>
                            <div class="gfl-field-stack">
                                <?php $this->render_textarea_input_field( 'window_subtitle', __( 'Helper text', 'guided-flow-leads' ), __( 'Short supporting text under the title.', 'guided-flow-leads' ), 2 ); ?>
                                <?php $this->render_text_input_field( 'input_placeholder', __( 'Default input placeholder', 'guided-flow-leads' ), __( 'Used for text, phone, and email input steps when a step-specific placeholder is empty.', 'guided-flow-leads' ) ); ?>
                            </div>
                        </section>

                        <section class="gfl-card gfl-card--section">
                            <div class="gfl-card__head gfl-card__head--split">
                                <div>
                                    <h2><?php esc_html_e( 'Flow Steps', 'guided-flow-leads' ); ?></h2>
                                    <p><?php esc_html_e( 'Each step can ask a question, collect an answer, and send the visitor to the next step.', 'guided-flow-leads' ); ?></p>
                                </div>
                                <button type="button" class="button button-secondary gfl-add-step"><?php esc_html_e( '+ Add Step', 'guided-flow-leads' ); ?></button>
                            </div>

                            <div class="gfl-steps-builder" data-step-count="<?php echo esc_attr( (string) count( $flow_steps ) ); ?>">
                                <?php foreach ( $flow_steps as $index => $step ) : ?>
                                    <?php $this->render_step_editor( $step, $index ); ?>
                                <?php endforeach; ?>
                            </div>

                            <div class="gfl-builder-footer">
                                <button type="button" class="button button-secondary gfl-add-step"><?php esc_html_e( '+ Add Step', 'guided-flow-leads' ); ?></button>
                                <p class="description"><?php esc_html_e( 'Tip: use short step IDs like choose_topic, ask_phone, or complete. Choice steps can point each option to a different next step.', 'guided-flow-leads' ); ?></p>
                            </div>

                            <script type="text/template" id="tmpl-gfl-step-card"><?php echo $this->get_step_template_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
                            <script type="text/template" id="tmpl-gfl-option-row"><?php echo $this->get_option_template_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
                        </section>
                    </div>

                    <aside class="gfl-settings-side">
                        <section class="gfl-card gfl-card--sticky">
                            <div class="gfl-card__head">
                                <h2><?php esc_html_e( 'Flow Preview', 'guided-flow-leads' ); ?></h2>
                                <p><?php esc_html_e( 'A quick structural view of the current journey.', 'guided-flow-leads' ); ?></p>
                            </div>
                            <ol class="gfl-flow-preview">
                                <?php foreach ( $flow_steps as $step ) : ?>
                                    <li>
                                        <strong><?php echo esc_html( $step['title'] ?: $step['id'] ); ?></strong>
                                        <span><?php echo esc_html( strtoupper( str_replace( 'input_', '', $step['type'] ) ) ); ?> · <?php echo esc_html( $step['message'] ); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <p><button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Flow Settings', 'guided-flow-leads' ); ?></button></p>
                        </section>
                    </aside>
                </div>
            </form>
        </div>
        <?php
    }

    public function render_leads_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $view      = ( isset( $_GET['view'] ) && 'trash' === sanitize_key( wp_unslash( $_GET['view'] ) ) ) ? 'trash' : 'active';
        $paged     = max( 1, isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1 );
        $per_page  = 20;
        $search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $lead_id   = isset( $_GET['lead_id'] ) ? absint( wp_unslash( $_GET['lead_id'] ) ) : 0;
        $all_count = $this->lead_service->get_total_count();
        $trash_cnt = $this->lead_service->get_lead_count( 'trash' );

        if ( $lead_id > 0 ) {
            $lead = $this->lead_service->get_lead_by_id( $lead_id );
            $this->render_lead_detail_page( $lead );
            return;
        }

        $rows  = $this->lead_service->get_leads_page( $paged, $per_page, $view, $search );
        $count = $this->lead_service->get_lead_count( $view, $search );
        $pages = max( 1, (int) ceil( $count / $per_page ) );
        ?>
        <div class="wrap gfl-wrap">
            <div class="gfl-pagehead">
                <div>
                    <h1><?php esc_html_e( 'Leads', 'guided-flow-leads' ); ?></h1>
                    <p class="description"><?php esc_html_e( 'Review captured leads in a cleaner list. Open a lead to view the collected answers and flow path.', 'guided-flow-leads' ); ?></p>
                </div>
            </div>

            <div class="gfl-card">
                <ul class="subsubsub gfl-subsubsub">
                    <li>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=guided-flow-leads-leads' ) ); ?>" class="<?php echo 'active' === $view ? 'current' : ''; ?>">
                            <?php esc_html_e( 'All', 'guided-flow-leads' ); ?>
                            <span class="count">(<?php echo esc_html( (string) ( $all_count - $trash_cnt ) ); ?>)</span>
                        </a> |
                    </li>
                    <li>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=guided-flow-leads-leads&view=trash' ) ); ?>" class="<?php echo 'trash' === $view ? 'current' : ''; ?>">
                            <?php esc_html_e( 'Trash', 'guided-flow-leads' ); ?>
                            <span class="count">(<?php echo esc_html( (string) $trash_cnt ); ?>)</span>
                        </a>
                    </li>
                </ul>

                <form method="get" class="gfl-searchbar">
                    <input type="hidden" name="page" value="guided-flow-leads-leads" />
                    <input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>" />
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search topic, email, phone, company, page...', 'guided-flow-leads' ); ?>" />
                    <button type="submit" class="button button-secondary"><?php esc_html_e( 'Search Leads', 'guided-flow-leads' ); ?></button>
                    <?php if ( '' !== $search ) : ?>
                        <a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=guided-flow-leads-leads&view=' . $view ) ); ?>"><?php esc_html_e( 'Clear search', 'guided-flow-leads' ); ?></a>
                    <?php endif; ?>
                </form>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'gfl_leads_bulk_action', 'gfl_leads_nonce' ); ?>
                    <input type="hidden" name="action" value="gfl_leads_bulk" />
                    <input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>" />

                    <div class="gfl-bulkbar">
                        <select name="bulk_action">
                            <option value=""><?php esc_html_e( 'Bulk actions', 'guided-flow-leads' ); ?></option>
                            <?php if ( 'trash' === $view ) : ?>
                                <option value="restore"><?php esc_html_e( 'Restore', 'guided-flow-leads' ); ?></option>
                                <option value="delete"><?php esc_html_e( 'Delete permanently', 'guided-flow-leads' ); ?></option>
                                <option value="empty_trash"><?php esc_html_e( 'Empty trash', 'guided-flow-leads' ); ?></option>
                            <?php else : ?>
                                <option value="trash"><?php esc_html_e( 'Move to trash', 'guided-flow-leads' ); ?></option>
                            <?php endif; ?>
                        </select>
                        <button type="submit" class="button action"><?php esc_html_e( 'Apply', 'guided-flow-leads' ); ?></button>
                        <span class="gfl-bulkbar__count"><?php echo esc_html( sprintf( _n( '%d result', '%d results', $count, 'guided-flow-leads' ), $count ) ); ?></span>
                    </div>

                    <div class="gfl-table-wrap">
                        <table class="widefat fixed striped gfl-leads-table">
                            <thead>
                                <tr>
                                    <td class="manage-column check-column"><input type="checkbox" class="gfl-check-all" /></td>
                                    <th><?php esc_html_e( 'Lead', 'guided-flow-leads' ); ?></th>
                                    <th><?php esc_html_e( 'Created', 'guided-flow-leads' ); ?></th>
                                    <th><?php esc_html_e( 'Inquiry Type', 'guided-flow-leads' ); ?></th>
                                    <th><?php esc_html_e( 'Contact', 'guided-flow-leads' ); ?></th>
                                    <th><?php esc_html_e( 'Business Type', 'guided-flow-leads' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'guided-flow-leads' ); ?></th>
                                    <th><?php esc_html_e( 'Source Page', 'guided-flow-leads' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty( $rows ) ) : ?>
                                    <tr>
                                        <td colspan="8"><?php esc_html_e( 'No leads found.', 'guided-flow-leads' ); ?></td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ( $rows as $row ) : ?>
                                        <?php
                                        $answers   = $this->get_answers( $row );
                                        $view_url  = add_query_arg( array( 'page' => 'guided-flow-leads-leads', 'lead_id' => (int) $row['id'] ), admin_url( 'admin.php' ) );
                                        $topic     = $this->get_answer_label( $answers, 'primary_topic', (string) ( $row['primary_topic'] ?? '' ) );
                                        $contact   = $this->get_preferred_contact( $row, $answers );
                                        $business  = $this->get_answer_label( $answers, 'business_type', '' );
                                        $page_name = $this->get_page_display_name( $row );
                                        ?>
                                        <tr>
                                            <th scope="row" class="check-column">
                                                <input type="checkbox" name="lead_ids[]" value="<?php echo esc_attr( (string) $row['id'] ); ?>" />
                                            </th>
                                            <td>
                                                <strong><a href="<?php echo esc_url( $view_url ); ?>">#<?php echo esc_html( (string) $row['id'] ); ?></a></strong>
                                                <div class="row-actions"><span class="view"><a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View', 'guided-flow-leads' ); ?></a></span></div>
                                            </td>
                                            <td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
                                            <td><?php echo esc_html( $topic ); ?></td>
                                            <td><?php echo wp_kses_post( $contact ); ?></td>
                                            <td><?php echo esc_html( $business ?: '—' ); ?></td>
                                            <td><span class="gfl-status gfl-status--<?php echo esc_attr( sanitize_html_class( (string) $row['lead_status'] ) ); ?>"><?php echo esc_html( $this->format_status_label( (string) $row['lead_status'] ) ); ?></span></td>
                                            <td>
                                                <?php if ( ! empty( $row['source_url'] ) ) : ?>
                                                    <a href="<?php echo esc_url( (string) $row['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $page_name ); ?></a>
                                                <?php else : ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <?php if ( $pages > 1 ) : ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php
                            echo wp_kses_post(
                                paginate_links(
                                    array(
                                        'base'      => add_query_arg(
                                            array(
                                                'page'  => 'guided-flow-leads-leads',
                                                'view'  => $view,
                                                's'     => $search,
                                                'paged' => '%#%',
                                            ),
                                            admin_url( 'admin.php' )
                                        ),
                                        'format'    => '',
                                        'current'   => $paged,
                                        'total'     => $pages,
                                        'prev_text' => '&laquo;',
                                        'next_text' => '&raquo;',
                                    )
                                )
                            );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var checkAll = document.querySelector('.gfl-check-all');
            if (!checkAll) return;
            checkAll.addEventListener('change', function () {
                document.querySelectorAll('input[name="lead_ids[]"]').forEach(function (item) {
                    item.checked = checkAll.checked;
                });
            });
        });
        </script>
        <?php
    }

    private function render_lead_detail_page( $lead ) {
        if ( empty( $lead ) ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Lead not found.', 'guided-flow-leads' ) . '</h1></div>';
            return;
        }

        $answers   = $this->get_answers( $lead );
        $summary   = $this->build_conversation_summary( $lead, $answers );
        $flow_path = $this->build_flow_path( $lead, $answers );
        ?>
        <div class="wrap gfl-wrap">
            <div class="gfl-pagehead">
                <div>
                    <h1><?php esc_html_e( 'Lead Details', 'guided-flow-leads' ); ?></h1>
                    <p class="description"><?php esc_html_e( 'A cleaner business view first, with the technical data available below when needed.', 'guided-flow-leads' ); ?></p>
                </div>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=guided-flow-leads-leads' ) ); ?>">&larr; <?php esc_html_e( 'Back to Leads', 'guided-flow-leads' ); ?></a>
            </div>

            <div class="gfl-grid gfl-grid--detail">
                <section class="gfl-card">
                    <h2><?php esc_html_e( 'Lead Overview', 'guided-flow-leads' ); ?></h2>
                    <table class="form-table gfl-detail-table">
                        <tbody>
                            <?php $this->render_detail_row( __( 'Lead ID', 'guided-flow-leads' ), '#' . (string) $lead['id'] ); ?>
                            <?php $this->render_detail_row( __( 'Created', 'guided-flow-leads' ), (string) $lead['created_at'] ); ?>
                            <?php $this->render_detail_row( __( 'Updated', 'guided-flow-leads' ), (string) $lead['updated_at'] ); ?>
                            <?php $this->render_detail_row( __( 'Status', 'guided-flow-leads' ), $this->format_status_label( (string) $lead['lead_status'] ) ); ?>
                            <?php $this->render_detail_row( __( 'Inquiry Type', 'guided-flow-leads' ), $this->get_answer_label( $answers, 'primary_topic', (string) ( $lead['primary_topic'] ?? '' ) ) ); ?>
                            <?php $this->render_detail_row( __( 'Contact Preference', 'guided-flow-leads' ), $this->get_answer_label( $answers, 'contact_method', (string) ( $lead['contact_method'] ?? '' ) ) ); ?>
                            <?php $this->render_detail_row( __( 'Email', 'guided-flow-leads' ), (string) ( $lead['email'] ?: '—' ) ); ?>
                            <?php $this->render_detail_row( __( 'Phone', 'guided-flow-leads' ), (string) ( $lead['phone'] ?: '—' ) ); ?>
                            <?php $this->render_detail_row( __( 'Business Type', 'guided-flow-leads' ), $this->get_answer_label( $answers, 'business_type', '—' ) ); ?>
                            <?php if ( ! empty( $lead['source_url'] ) ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Source Page', 'guided-flow-leads' ); ?></th>
                                    <td><a href="<?php echo esc_url( (string) $lead['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $this->get_page_display_name( $lead ) ); ?></a></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>

                <section class="gfl-card">
                    <h2><?php esc_html_e( 'Collected Details', 'guided-flow-leads' ); ?></h2>
                    <div class="gfl-detail-stack">
                        <?php $this->render_data_item( __( 'Project Details', 'guided-flow-leads' ), (string) ( $answers['project_details'] ?? '—' ) ); ?>
                        <?php if ( ! empty( $lead['company'] ) ) : ?>
                            <?php $this->render_data_item( __( 'Company', 'guided-flow-leads' ), (string) $lead['company'] ); ?>
                        <?php endif; ?>
                        <?php if ( ! empty( $lead['country'] ) ) : ?>
                            <?php $this->render_data_item( __( 'Country', 'guided-flow-leads' ), (string) $lead['country'] ); ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="gfl-grid gfl-grid--detail gfl-grid--stacked">
                <section class="gfl-card">
                    <h2><?php esc_html_e( 'Conversation Summary', 'guided-flow-leads' ); ?></h2>
                    <p class="gfl-summary-paragraph"><?php echo esc_html( $summary ); ?></p>
                </section>

                <section class="gfl-card">
                    <h2><?php esc_html_e( 'Flow Path', 'guided-flow-leads' ); ?></h2>
                    <ol class="gfl-path-list">
                        <?php foreach ( $flow_path as $item ) : ?>
                            <li><?php echo esc_html( $item ); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            </div>

            <section class="gfl-card">
                <details class="gfl-technical-details">
                    <summary><?php esc_html_e( 'Technical Data', 'guided-flow-leads' ); ?></summary>
                    <div class="gfl-technical-grid">
                        <div>
                            <h3><?php esc_html_e( 'Raw Answers', 'guided-flow-leads' ); ?></h3>
                            <table class="widefat striped gfl-answers-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Field', 'guided-flow-leads' ); ?></th>
                                        <th><?php esc_html_e( 'Value', 'guided-flow-leads' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $answers as $key => $value ) : ?>
                                        <tr>
                                            <td><code><?php echo esc_html( (string) $key ); ?></code></td>
                                            <td><?php echo esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div>
                            <h3><?php esc_html_e( 'Record Data', 'guided-flow-leads' ); ?></h3>
                            <table class="form-table gfl-detail-table">
                                <tbody>
                                    <?php $this->render_detail_row( __( 'Session ID', 'guided-flow-leads' ), (string) $lead['session_id'] ); ?>
                                    <?php $this->render_detail_row( __( 'Flow Key', 'guided-flow-leads' ), (string) $lead['flow_key'] ); ?>
                                    <?php $this->render_detail_row( __( 'Summary Text', 'guided-flow-leads' ), (string) ( $lead['summary_text'] ?: '—' ) ); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            </section>
        </div>
        <?php
    }

    private function render_detail_row( $label, $value ) {
        ?>
        <tr>
            <th><?php echo esc_html( $label ); ?></th>
            <td><?php echo nl2br( esc_html( $value ) ); ?></td>
        </tr>
        <?php
    }

    private function render_data_item( $label, $value ) {
        ?>
        <div class="gfl-data-item">
            <span class="gfl-data-item__label"><?php echo esc_html( $label ); ?></span>
            <div class="gfl-data-item__value"><?php echo nl2br( esc_html( $value ) ); ?></div>
        </div>
        <?php
    }

    public function handle_leads_bulk() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'guided-flow-leads' ) );
        }

        check_admin_referer( 'gfl_leads_bulk_action', 'gfl_leads_nonce' );

        $view   = ( isset( $_POST['view'] ) && 'trash' === sanitize_key( wp_unslash( $_POST['view'] ) ) ) ? 'trash' : 'active';
        $action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
        $ids    = isset( $_POST['lead_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['lead_ids'] ) ) : array();

        switch ( $action ) {
            case 'trash':
                $this->lead_service->move_to_trash( $ids );
                break;
            case 'restore':
                $this->lead_service->restore_from_trash( $ids );
                break;
            case 'delete':
                $this->lead_service->delete_permanently( $ids );
                break;
            case 'empty_trash':
                $this->lead_service->empty_trash();
                break;
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'guided-flow-leads-leads',
                    'view' => $view,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    private function render_toggle_field( $key, $label, $help = '' ) {
        $settings = $this->get_settings();
        ?>
        <div class="gfl-field gfl-field--toggle">
            <label class="gfl-toggle">
                <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( '1', (string) $settings[ $key ] ); ?> />
                <span class="gfl-toggle__switch"></span>
                <span class="gfl-toggle__text"><?php echo esc_html( $label ); ?></span>
            </label>
            <?php if ( $help ) : ?>
                <p class="description"><?php echo esc_html( $help ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_text_input_field( $key, $label, $help = '', $placeholder = '', $class = 'regular-text' ) {
        $settings = $this->get_settings();
        ?>
        <div class="gfl-field">
            <label for="<?php echo esc_attr( 'gfl-' . $key ); ?>"><?php echo esc_html( $label ); ?></label>
            <input id="<?php echo esc_attr( 'gfl-' . $key ); ?>" type="text" class="<?php echo esc_attr( $class ); ?>" name="<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings[ $key ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" />
            <?php if ( $help ) : ?>
                <p class="description"><?php echo esc_html( $help ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_textarea_input_field( $key, $label, $help = '', $rows = 3 ) {
        $settings = $this->get_settings();
        ?>
        <div class="gfl-field">
            <label for="<?php echo esc_attr( 'gfl-' . $key ); ?>"><?php echo esc_html( $label ); ?></label>
            <textarea id="<?php echo esc_attr( 'gfl-' . $key ); ?>" class="large-text" rows="<?php echo esc_attr( (string) $rows ); ?>" name="<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $settings[ $key ] ?? '' ); ?></textarea>
            <?php if ( $help ) : ?>
                <p class="description"><?php echo esc_html( $help ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_step_editor( $step, $index ) {
        $step  = wp_parse_args(
            is_array( $step ) ? $step : array(),
            array(
                'id'            => 'step_' . ( (int) $index + 1 ),
                'title'         => '',
                'type'          => 'input_text',
                'message'       => '',
                'placeholder'   => '',
                'save_to'       => '',
                'save_label_to' => '',
                'next'          => '',
                'options'       => array(),
            )
        );
        $base  = $this->option_name . '[flow_steps][' . $index . ']';
        $types = $this->get_step_types();
        ?>
        <div class="gfl-step-card" data-step-card>
            <div class="gfl-step-card__top">
                <div>
                    <span class="gfl-step-card__eyebrow"><?php esc_html_e( 'Flow Step', 'guided-flow-leads' ); ?></span>
                    <h3><?php echo esc_html( $step['title'] ?: $step['id'] ); ?></h3>
                </div>
                <div class="gfl-step-card__actions">
                    <button type="button" class="button button-small gfl-move-step-up">↑</button>
                    <button type="button" class="button button-small gfl-move-step-down">↓</button>
                    <button type="button" class="button button-small gfl-delete-step"><?php esc_html_e( 'Delete', 'guided-flow-leads' ); ?></button>
                </div>
            </div>

            <div class="gfl-step-card__grid">
                <div class="gfl-field">
                    <label><?php esc_html_e( 'Step title', 'guided-flow-leads' ); ?></label>
                    <input type="text" name="<?php echo esc_attr( $base . '[title]' ); ?>" value="<?php echo esc_attr( $step['title'] ); ?>" placeholder="<?php esc_attr_e( 'Shown only in the editor', 'guided-flow-leads' ); ?>" />
                </div>
                <div class="gfl-field">
                    <label><?php esc_html_e( 'Step ID', 'guided-flow-leads' ); ?></label>
                    <input type="text" class="gfl-step-id" name="<?php echo esc_attr( $base . '[id]' ); ?>" value="<?php echo esc_attr( $step['id'] ); ?>" placeholder="choose_topic" />
                    <p class="description"><?php esc_html_e( 'Used by the flow engine. Keep it unique and machine-friendly.', 'guided-flow-leads' ); ?></p>
                </div>
                <div class="gfl-field">
                    <label><?php esc_html_e( 'Step type', 'guided-flow-leads' ); ?></label>
                    <select name="<?php echo esc_attr( $base . '[type]' ); ?>" class="gfl-step-type">
                        <?php foreach ( $types as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $step['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="gfl-field">
                    <label><?php esc_html_e( 'Default next step ID', 'guided-flow-leads' ); ?></label>
                    <input type="text" name="<?php echo esc_attr( $base . '[next]' ); ?>" value="<?php echo esc_attr( $step['next'] ); ?>" placeholder="complete" />
                    <p class="description"><?php esc_html_e( 'Used by input steps, or by choice options that do not define their own next step.', 'guided-flow-leads' ); ?></p>
                </div>
            </div>

            <div class="gfl-field-stack">
                <div class="gfl-field">
                    <label><?php esc_html_e( 'Assistant message', 'guided-flow-leads' ); ?></label>
                    <textarea rows="3" name="<?php echo esc_attr( $base . '[message]' ); ?>"><?php echo esc_textarea( $step['message'] ); ?></textarea>
                </div>
            </div>

            <div class="gfl-step-card__grid gfl-step-card__grid--meta">
                <div class="gfl-field gfl-step-placeholder-field">
                    <label><?php esc_html_e( 'Placeholder', 'guided-flow-leads' ); ?></label>
                    <input type="text" name="<?php echo esc_attr( $base . '[placeholder]' ); ?>" value="<?php echo esc_attr( $step['placeholder'] ); ?>" placeholder="<?php esc_attr_e( 'Optional. Falls back to the global placeholder.', 'guided-flow-leads' ); ?>" />
                </div>
                <div class="gfl-field gfl-step-save-field">
                    <label><?php esc_html_e( 'Save answer to', 'guided-flow-leads' ); ?></label>
                    <input type="text" name="<?php echo esc_attr( $base . '[save_to]' ); ?>" value="<?php echo esc_attr( $step['save_to'] ); ?>" placeholder="email" />
                </div>
                <div class="gfl-field gfl-step-save-label-field">
                    <label><?php esc_html_e( 'Save label to', 'guided-flow-leads' ); ?></label>
                    <input type="text" name="<?php echo esc_attr( $base . '[save_label_to]' ); ?>" value="<?php echo esc_attr( $step['save_label_to'] ); ?>" placeholder="email_label" />
                </div>
            </div>

            <div class="gfl-step-options <?php echo 'choice' === $step['type'] ? '' : 'is-hidden'; ?>" data-step-options>
                <div class="gfl-step-options__head">
                    <h4><?php esc_html_e( 'Choice Options', 'guided-flow-leads' ); ?></h4>
                    <button type="button" class="button button-secondary gfl-add-option"><?php esc_html_e( '+ Add Option', 'guided-flow-leads' ); ?></button>
                </div>
                <div class="gfl-options-list" data-options-list>
                    <?php
                    $options = is_array( $step['options'] ) ? $step['options'] : array();
                    if ( empty( $options ) ) {
                        $options = array(
                            array(
                                'label' => '',
                                'value' => '',
                                'next'  => '',
                            ),
                        );
                    }
                    foreach ( $options as $option_index => $option ) {
                        $this->render_option_editor( $base, $option_index, $option );
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_option_editor( $base, $option_index, $option ) {
        $option = wp_parse_args(
            is_array( $option ) ? $option : array(),
            array(
                'label' => '',
                'value' => '',
                'next'  => '',
            )
        );
        $option_name = $base . '[options][' . $option_index . ']';
        ?>
        <div class="gfl-option-row" data-option-row>
            <div class="gfl-option-row__grid">
                <div class="gfl-field">
                    <label><?php esc_html_e( 'Label', 'guided-flow-leads' ); ?></label>
                    <input type="text" name="<?php echo esc_attr( $option_name . '[label]' ); ?>" value="<?php echo esc_attr( $option['label'] ); ?>" />
                </div>
                <div class="gfl-field">
                    <label><?php esc_html_e( 'Value', 'guided-flow-leads' ); ?></label>
                    <input type="text" name="<?php echo esc_attr( $option_name . '[value]' ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>" />
                </div>
                <div class="gfl-field">
                    <label><?php esc_html_e( 'Next step ID', 'guided-flow-leads' ); ?></label>
                    <input type="text" name="<?php echo esc_attr( $option_name . '[next]' ); ?>" value="<?php echo esc_attr( $option['next'] ); ?>" placeholder="ask_contact" />
                </div>
            </div>
            <button type="button" class="button-link-delete gfl-delete-option"><?php esc_html_e( 'Remove option', 'guided-flow-leads' ); ?></button>
        </div>
        <?php
    }

    private function get_step_template_html() {
        ob_start();
        $this->render_step_editor(
            array(
                'id'            => '__STEP_ID__',
                'title'         => '',
                'type'          => 'input_text',
                'message'       => '',
                'placeholder'   => '',
                'save_to'       => '',
                'save_label_to' => '',
                'next'          => '',
                'options'       => array(),
            ),
            '__STEP_INDEX__'
        );
        return str_replace( array( '&#039;__STEP_ID__&#039;', '__STEP_ID__' ), array( '__STEP_ID__', '__STEP_ID__' ), (string) ob_get_clean() );
    }

    private function get_option_template_html() {
        ob_start();
        $this->render_option_editor( '__BASE__', '__OPTION_INDEX__', array() );
        return (string) ob_get_clean();
    }

    private function sanitize_flow_steps( $steps ) {
        $steps           = is_array( $steps ) ? array_values( $steps ) : array();
        $sanitized_steps = array();
        $used_ids        = array();

        foreach ( $steps as $index => $step ) {
            if ( ! is_array( $step ) ) {
                continue;
            }

            $type = isset( $step['type'] ) ? sanitize_key( (string) $step['type'] ) : 'input_text';
            if ( ! array_key_exists( $type, $this->get_step_types() ) ) {
                $type = 'input_text';
            }

            $id = isset( $step['id'] ) ? sanitize_key( (string) $step['id'] ) : '';
            if ( '' === $id ) {
                $id = 'step_' . ( count( $sanitized_steps ) + 1 );
            }
            if ( in_array( $id, $used_ids, true ) ) {
                $id .= '_' . ( count( $sanitized_steps ) + 1 );
            }
            $used_ids[] = $id;

            $sanitized_step = array(
                'id'            => $id,
                'title'         => sanitize_text_field( (string) ( $step['title'] ?? '' ) ),
                'type'          => $type,
                'message'       => sanitize_textarea_field( (string) ( $step['message'] ?? '' ) ),
                'placeholder'   => sanitize_text_field( (string) ( $step['placeholder'] ?? '' ) ),
                'save_to'       => sanitize_key( (string) ( $step['save_to'] ?? '' ) ),
                'save_label_to' => sanitize_key( (string) ( $step['save_label_to'] ?? '' ) ),
                'next'          => sanitize_key( (string) ( $step['next'] ?? '' ) ),
                'options'       => array(),
            );

            if ( 'choice' === $type ) {
                $options = isset( $step['options'] ) && is_array( $step['options'] ) ? array_values( $step['options'] ) : array();
                foreach ( $options as $option ) {
                    $label = sanitize_text_field( (string) ( $option['label'] ?? '' ) );
                    $value = sanitize_text_field( (string) ( $option['value'] ?? '' ) );
                    $next  = sanitize_key( (string) ( $option['next'] ?? '' ) );
                    if ( '' === $label ) {
                        continue;
                    }
                    if ( '' === $value ) {
                        $value = sanitize_title( $label );
                    }
                    $sanitized_step['options'][] = array(
                        'label' => $label,
                        'value' => $value,
                        'next'  => $next,
                    );
                }
            }

            $sanitized_steps[] = $sanitized_step;
        }

        if ( empty( $sanitized_steps ) ) {
            $sanitized_steps = $this->get_default_flow_steps();
        }

        $has_complete = false;
        foreach ( $sanitized_steps as $step ) {
            if ( 'complete' === $step['type'] ) {
                $has_complete = true;
                break;
            }
        }
        if ( ! $has_complete ) {
            $sanitized_steps[] = array(
                'id'            => 'complete',
                'title'         => 'Completion',
                'type'          => 'complete',
                'message'       => __( 'Thanks. Our team will review your information and get in touch soon.', 'guided-flow-leads' ),
                'placeholder'   => '',
                'save_to'       => '',
                'save_label_to' => '',
                'next'          => '',
                'options'       => array(),
            );
        }

        return $sanitized_steps;
    }

    private function get_default_flow_steps() {
        return GFL_Activator::build_default_steps();
    }

    private function get_flow_steps( $settings ) {
        $steps = json_decode( (string) ( $settings['flow_steps_json'] ?? '' ), true );
        if ( ! is_array( $steps ) || empty( $steps ) ) {
            $steps = $this->get_default_flow_steps();
        }
        return $steps;
    }

    private function get_step_types() {
        return array(
            'choice'      => __( 'Choice buttons', 'guided-flow-leads' ),
            'input_text'  => __( 'Text input', 'guided-flow-leads' ),
            'input_email' => __( 'Email input', 'guided-flow-leads' ),
            'input_phone' => __( 'Phone input', 'guided-flow-leads' ),
            'complete'    => __( 'Completion message', 'guided-flow-leads' ),
        );
    }

    private function get_settings() {
        $settings = get_option( $this->option_name, array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        return wp_parse_args( $settings, GFL_Activator::default_settings() );
    }

    private function get_answers( $lead ) {
        $answers = json_decode( (string) ( $lead['answers_json'] ?? '' ), true );
        return is_array( $answers ) ? $answers : array();
    }

    private function get_answer_label( $answers, $key, $fallback = '' ) {
        if ( isset( $answers[ $key . '_label' ] ) && '' !== trim( (string) $answers[ $key . '_label' ] ) ) {
            return sanitize_text_field( (string) $answers[ $key . '_label' ] );
        }

        if ( isset( $answers[ $key ] ) && '' !== trim( (string) $answers[ $key ] ) ) {
            return $this->humanize_value( (string) $answers[ $key ] );
        }

        return $fallback;
    }

    private function humanize_value( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return '—';
        }

        return ucwords( str_replace( array( '_', '-' ), ' ', $value ) );
    }

    private function format_status_label( $status ) {
        return $this->humanize_value( $status );
    }

    private function get_preferred_contact( $row, $answers ) {
        $method = $this->get_answer_label( $answers, 'contact_method', (string) ( $row['contact_method'] ?? '' ) );
        $parts  = array();

        if ( ! empty( $row['phone'] ) ) {
            $parts[] = sprintf( '<strong>%s:</strong> %s', esc_html__( 'Phone', 'guided-flow-leads' ), esc_html( (string) $row['phone'] ) );
        }

        if ( ! empty( $row['email'] ) ) {
            $parts[] = sprintf( '<strong>%s:</strong> %s', esc_html__( 'Email', 'guided-flow-leads' ), esc_html( (string) $row['email'] ) );
        }

        if ( empty( $parts ) ) {
            return '—';
        }

        if ( $method && '—' !== $method ) {
            array_unshift( $parts, '<span class="gfl-mini-label">' . esc_html( $method ) . '</span>' );
        }

        return implode( '<br>', $parts );
    }

    private function get_page_display_name( $row ) {
        $page_title = trim( (string) ( $row['page_title'] ?? '' ) );
        if ( '' !== $page_title ) {
            return $page_title;
        }

        $source_url = trim( (string) ( $row['source_url'] ?? '' ) );
        if ( '' === $source_url ) {
            return '—';
        }

        $parsed = wp_parse_url( $source_url );
        if ( empty( $parsed['path'] ) || '/' === $parsed['path'] ) {
            return __( 'Home Page', 'guided-flow-leads' );
        }

        return untrailingslashit( (string) $parsed['path'] );
    }

    private function build_conversation_summary( $lead, $answers ) {
        $topic    = $this->get_answer_label( $answers, 'primary_topic', (string) ( $lead['primary_topic'] ?? '' ) );
        $method   = $this->get_answer_label( $answers, 'contact_method', (string) ( $lead['contact_method'] ?? '' ) );
        $business = $this->get_answer_label( $answers, 'business_type', '' );
        $details  = trim( (string) ( $answers['project_details'] ?? '' ) );
        $contact  = ! empty( $lead['phone'] ) ? (string) $lead['phone'] : (string) $lead['email'];

        $parts   = array();
        $parts[] = sprintf( __( 'The visitor selected “%s”.', 'guided-flow-leads' ), $topic ?: __( 'an inquiry', 'guided-flow-leads' ) );

        if ( $method && '—' !== $method && $contact ) {
            $parts[] = sprintf( __( 'Preferred contact method: %1$s (%2$s).', 'guided-flow-leads' ), $method, $contact );
        } elseif ( $contact ) {
            $parts[] = sprintf( __( 'Shared contact information: %s.', 'guided-flow-leads' ), $contact );
        }

        if ( $business && '—' !== $business ) {
            $parts[] = sprintf( __( 'Business type: %s.', 'guided-flow-leads' ), $business );
        }

        if ( '' !== $details ) {
            $parts[] = sprintf( __( 'Project details: %s', 'guided-flow-leads' ), $details );
        }

        return implode( ' ', $parts );
    }

    private function build_flow_path( $lead, $answers ) {
        $path   = array();
        $topic  = $this->get_answer_label( $answers, 'primary_topic', (string) ( $lead['primary_topic'] ?? '' ) );
        $method = $this->get_answer_label( $answers, 'contact_method', (string) ( $lead['contact_method'] ?? '' ) );
        $biz    = $this->get_answer_label( $answers, 'business_type', '' );

        if ( $topic && '—' !== $topic ) {
            $path[] = sprintf( __( 'Selected inquiry type: %s', 'guided-flow-leads' ), $topic );
        }

        if ( $method && '—' !== $method ) {
            $path[] = sprintf( __( 'Chose contact method: %s', 'guided-flow-leads' ), $method );
        }

        if ( ! empty( $lead['phone'] ) ) {
            $path[] = sprintf( __( 'Entered phone number: %s', 'guided-flow-leads' ), (string) $lead['phone'] );
        }

        if ( ! empty( $lead['email'] ) ) {
            $path[] = sprintf( __( 'Entered email address: %s', 'guided-flow-leads' ), (string) $lead['email'] );
        }

        if ( $biz && '—' !== $biz ) {
            $path[] = sprintf( __( 'Selected business type: %s', 'guided-flow-leads' ), $biz );
        }

        if ( ! empty( $answers['project_details'] ) ) {
            $path[] = sprintf( __( 'Shared project details: %s', 'guided-flow-leads' ), (string) $answers['project_details'] );
        }

        return $path;
    }
}

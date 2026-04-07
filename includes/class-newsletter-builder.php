<?php
/**
 * Newsletter Builder Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSSB_Newsletter_Builder
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('wp_ajax_sssb_search_posts', array($this, 'ajax_search_posts'));
        add_action('wp_ajax_sssb_create_campaign', array($this, 'ajax_create_campaign'));

    }

    public function add_submenu()
    {
        add_submenu_page(
            'simple_sendy_bridge',
            __('Create Newsletter', 'simple-ses-bridge-for-sendy'),
            __('Create Newsletter', 'simple-ses-bridge-for-sendy'),
            'manage_options',
            'sssb_newsletter_builder',
            array($this, 'render_page')
        );
    }

    public function render_page()
    {
        $options = get_option('sssb_settings');
        $default_from_name = isset($options['from_name']) ? $options['from_name'] : '';
        $default_from_email = isset($options['from_email']) ? $options['from_email'] : '';
        $default_list_id = isset($options['list_id']) ? $options['list_id'] : '';
        ?>
        <div class="wrap sssb-container">
            <h1>
                <?php esc_html_e('Create Newsletter', 'simple-ses-bridge-for-sendy'); ?>
            </h1>

            <div class="sssb-flex">
                <!-- Left Column: Controls -->
                <div class="sssb-col-left">
                    <div class="sssb-card">
                        <h2>
                            <?php esc_html_e('Campaign Settings', 'simple-ses-bridge-for-sendy'); ?>
                        </h2>
                        <p>
                            <label>
                                <?php esc_html_e('Subject Line', 'simple-ses-bridge-for-sendy'); ?>
                            </label><br>
                            <input type="text" id="sssb-subject" class="widefat" placeholder="Newsletter Subject">
                        </p>
                        <p>
                            <label>
                                <?php esc_html_e('From Name', 'simple-ses-bridge-for-sendy'); ?>
                            </label><br>
                            <input type="text" id="sssb-from-name" class="widefat"
                                value="<?php echo esc_attr($default_from_name); ?>">
                        </p>
                        <p>
                            <label>
                                <?php esc_html_e('From Email', 'simple-ses-bridge-for-sendy'); ?>
                            </label><br>
                            <input type="email" id="sssb-from-email" class="widefat"
                                value="<?php echo esc_attr($default_from_email); ?>">
                        </p>
                        <p>
                            <label>
                                <?php esc_html_e('Choose your lists & segments', 'simple-ses-bridge-for-sendy'); ?>
                            </label><br>
                            <input type="hidden" id="sssb-list-id" value="<?php echo esc_attr($default_list_id); ?>">
                            <span id="sssb-list-empty-notice" class="description" style="display:none;">
                                <?php
                                $refresh_url = esc_url(add_query_arg('sssb_refresh_lists', '1'));
                                printf(
                                    /* translators: %s: refresh link */
                                    esc_html__('No lists found. Check your Sendy URL, API Key and Brand ID in Settings, then %s.', 'simple-ses-bridge-for-sendy'),
                                    '<a href="' . $refresh_url . '">' . esc_html__('refresh', 'simple-ses-bridge-for-sendy') . '</a>'
                                );
                                ?>
                            </span>
                            <span class="description" style="display:block; margin-top:5px;">
                                <a href="<?php echo esc_url(add_query_arg('sssb_refresh_lists', '1')); ?>"><?php esc_html_e('Refresh lists from Sendy', 'simple-ses-bridge-for-sendy'); ?></a>
                            </span>
                        </p>
                    </div>

                    <div class="sssb-card">
                        <h2><?php esc_html_e('Design Settings', 'simple-ses-bridge-for-sendy'); ?></h2>
                        <p>
                            <label><strong><?php esc_html_e('Banner Image', 'simple-ses-bridge-for-sendy'); ?></strong></label><br>
                            <button class="button"
                                id="sssb-upload-banner"><?php esc_html_e('Select Banner', 'simple-ses-bridge-for-sendy'); ?></button>
                            <button class="button hidden" id="sssb-remove-banner"
                                style="display:none; color: #a00; border-color: #a00;"><?php esc_html_e('Remove', 'simple-ses-bridge-for-sendy'); ?></button>
                        <p class="description" style="margin-top: 5px; color: #666; font-style: italic;">
                            <?php esc_html_e('Recommended Size: 600px wide. Keep height under 200px for best results on mobile and desktop.', 'simple-ses-bridge-for-sendy'); ?>
                        </p>
                        <input type="hidden" id="sssb-banner-url">
                        <div id="sssb-banner-preview" style="margin-top:10px; max-width:100%;"></div>
                        </p>
                        <p>
                            <!-- Custom Template Enforced -->
                             <input type="hidden" name="sssb_layout" value="custom">
                             <span class="description"><?php esc_html_e('Using Custom Template (Hero + Grid)', 'simple-ses-bridge-for-sendy'); ?></span>
                        </p>
                    </div>

                    <div class="sssb-card">
                        <h2>
                            <?php esc_html_e('Add Posts', 'simple-ses-bridge-for-sendy'); ?>
                        </h2>
                        <input type="text" id="sssb-search" class="widefat" placeholder="Search posts...">
                        <div id="sssb-post-results" class="sssb-post-list">
                            <!-- Search results -->
                        </div>
                    </div>

                    <div class="sssb-card">
                        <h2>
                            <?php esc_html_e('Selected Posts', 'simple-ses-bridge-for-sendy'); ?>
                        </h2>
                        <div id="sssb-selected-list" class="sssb-selected-posts"></div>
                    </div>

                    <div class="sssb-card">
                        <h2>
                            <?php esc_html_e('Actions', 'simple-ses-bridge-for-sendy'); ?>
                        </h2>
                        <label><input type="radio" name="sssb_send_type" value="draft" checked>
                            <?php esc_html_e('Save as Draft in Sendy', 'simple-ses-bridge-for-sendy'); ?>
                        </label><br>
                        <label><input type="radio" name="sssb_send_type" value="send">
                            <?php esc_html_e('Send Immediately', 'simple-ses-bridge-for-sendy'); ?>
                        </label><br>
                        <label><input type="radio" name="sssb_send_type" value="schedule">
                            <?php esc_html_e('Schedule', 'simple-ses-bridge-for-sendy'); ?>
                        </label>
                        
                        <div id="sssb-schedule-options" style="display:none; margin-top: 10px; padding-left: 20px;">
                            <label><?php esc_html_e('Send Date/Time', 'simple-ses-bridge-for-sendy'); ?></label><br>
                            <?php
                            // Set default to current time + 1 hour
                            $default_time = current_time('timestamp') + 3600; // +1 hour
                            $min_time = current_time('timestamp');
                            $default_datetime = wp_date('Y-m-d\TH:i', $default_time);
                            $min_datetime = wp_date('Y-m-d\TH:i', $min_time);
                            ?>
                            <input type="datetime-local" 
                                   id="sssb-schedule-datetime" 
                                   class="regular-text"
                                   value="<?php echo esc_attr($default_datetime); ?>"
                                   min="<?php echo esc_attr($min_datetime); ?>"
                                   style="position: relative; z-index: 999999;">
                            
                            <?php
                            $timezone = get_option('timezone_string');
                            if (!$timezone) {
                                $timezone = 'UTC ' . get_option('gmt_offset');
                            }
                            ?>
                            <p class="description">
                                <?php
                                /* translators: %s: Current server time */
                                echo esc_html(sprintf(__('Current Server Time: %s', 'simple-ses-bridge-for-sendy'), current_time('mysql')));
                                ?>
                                <br>
                                <?php
                                /* translators: %s: Timezone */
                                echo esc_html(sprintf(__('Timezone: %s', 'simple-ses-bridge-for-sendy'), $timezone));
                                ?>
                            </p>
                        </div>
                        
                        <br><br>
                        <button id="sssb-create-campaign" class="button button-primary large">
                            <?php esc_html_e('Create Campaign', 'simple-ses-bridge-for-sendy'); ?>
                        </button>
                    </div>

                    <div class="sssb-card">
                        <h3><?php esc_html_e('Support & Contact', 'simple-ses-bridge-for-sendy'); ?></h3>
                        <p>
                            <a href="https://buymeacoffee.com/gunjanjaswal" target="_blank" class="button" style="background-color: #FFDD00 !important; color: #000000 !important; border-color: #FFDD00 !important; font-weight: bold;">
                                <?php esc_html_e('☕ Buy Me A Coffee', 'simple-ses-bridge-for-sendy'); ?>
                            </a>
                        </p>
                        <p style="margin-top:15px;">
                            <strong><?php esc_html_e('Email:', 'simple-ses-bridge-for-sendy'); ?></strong> <a
                                href="mailto:hello@gunjanjaswal.me" style="color: #2271b1 !important; text-decoration: underline;">hello@gunjanjaswal.me</a><br>
                            <strong><?php esc_html_e('Website:', 'simple-ses-bridge-for-sendy'); ?></strong> <a
                                href="https://gunjanjaswal.me" target="_blank" style="color: #2271b1 !important; text-decoration: underline;">gunjanjaswal.me</a>
                        </p>
                    </div>
                </div>

                <!-- Right Column: Preview -->
                <div class="sssb-col-right">


                    <div class="sssb-card">
                        <h2>
                            <?php esc_html_e('Email Preview', 'simple-ses-bridge-for-sendy'); ?>
                        </h2>
                        <div id="sssb-preview-content">
                            <p style="text-align:center; color:#999;">
                                <?php esc_html_e('Add posts to see preview', 'simple-ses-bridge-for-sendy'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_search_posts()
    {
        check_ajax_referer('sssb_newsletter_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';

        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 10,
        );

        if (!empty($query)) {
            $args['s'] = $query;
        }

        $posts = get_posts($args);
        $data = array();

        foreach ($posts as $post) {
            $thumb_id = get_post_thumbnail_id($post->ID);
            $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : '';

            // Fallback if no thumb
            if (empty($thumb_url))
                $thumb_url = ''; // Let frontend handle empty state or use a local asset


            $data[] = array(
                'id' => $post->ID,
                'title' => get_the_title($post->ID),
                'thumbnail' => $thumb_url,
                'excerpt' => wp_trim_words($post->post_excerpt ? $post->post_excerpt : strip_shortcodes($post->post_content), 20),
                'link' => get_permalink($post->ID),
            );
        }

        wp_send_json_success($data);
    }

    public function ajax_create_campaign()
    {
        check_ajax_referer('sssb_newsletter_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        if (!isset($_POST['campaign'])) {
            wp_send_json_error('No campaign data received');
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $campaign_data = wp_unslash($_POST['campaign']);

        // Create Campaign Post
        $post_args = array(
            'post_type'    => 'sssb_campaign',
            'post_title'   => sanitize_text_field($campaign_data['subject']),
            'post_content' => $campaign_data['html_text'], // Save full HTML
            'post_status'  => 'draft',
        );

        $post_id = wp_insert_post($post_args);

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => 'Could not save campaign locally: ' . $post_id->get_error_message()));
        }

        // Save Meta
        update_post_meta($post_id, '_sssb_from_name', sanitize_text_field($campaign_data['from_name']));
        update_post_meta($post_id, '_sssb_from_email', sanitize_email($campaign_data['from_email']));
        update_post_meta($post_id, '_sssb_plain_text', sanitize_textarea_field($campaign_data['plain_text']));
        update_post_meta($post_id, '_sssb_list_id', sanitize_text_field($campaign_data['list_id']));
        
        $send_type = $campaign_data['send_type'];

        if ($send_type === 'schedule') {
             $schedule_date = sanitize_text_field($campaign_data['schedule_date']);
             $timestamp = strtotime($schedule_date);

             if (!$timestamp || $timestamp <= current_time('timestamp')) {
                 wp_delete_post($post_id, true);
                 wp_send_json_error(array('message' => 'Invalid or past date for scheduling.'));
             }

             wp_schedule_single_event($timestamp, 'sssb_send_scheduled_campaign', array($post_id));
             
             update_post_meta($post_id, '_sssb_status', 'scheduled');
             update_post_meta($post_id, '_sssb_scheduled_time', $schedule_date);
             wp_update_post(array('ID' => $post_id, 'post_status' => 'publish'));
             
             wp_send_json_success(array('message' => 'Campaign scheduled successfully for ' . $schedule_date));

        } elseif ($send_type === 'send') {
            
            update_post_meta($post_id, '_sssb_status', 'sending');

            $api_args = array(
                'from_name' => sanitize_text_field($campaign_data['from_name']),
                'from_email' => sanitize_email($campaign_data['from_email']),
                'reply_to' => sanitize_email($campaign_data['from_email']),
                'subject' => sanitize_text_field($campaign_data['subject']),
                'html_text' => $campaign_data['html_text'], // Full HTML required for email
                'plain_text' => sanitize_textarea_field($campaign_data['plain_text']),
                'list_ids' => sanitize_text_field($campaign_data['list_id']),
                'send_campaign' => 1
            );
    
            $sendy_api = new SSSB_Sendy_API();
            $result = $sendy_api->create_campaign($api_args);
    
            if (is_wp_error($result)) {
                wp_delete_post($post_id, true);
                wp_send_json_error(array('message' => $result->get_error_message()));
            } else {
                update_post_meta($post_id, '_sssb_status', 'sent');
                update_post_meta($post_id, '_sssb_sent_time', current_time('mysql'));
                wp_update_post(array('ID' => $post_id, 'post_status' => 'publish'));
                wp_send_json_success(array('message' => 'Campaign created and sent successfully!'));
            }

        } else {
            // Draft
            update_post_meta($post_id, '_sssb_status', 'draft');
            
             $api_args = array(
                'from_name' => sanitize_text_field($campaign_data['from_name']),
                'from_email' => sanitize_email($campaign_data['from_email']),
                'reply_to' => sanitize_email($campaign_data['from_email']),
                'subject' => sanitize_text_field($campaign_data['subject']),
                'html_text' => $campaign_data['html_text'], // Full HTML required for email
                'plain_text' => sanitize_textarea_field($campaign_data['plain_text']),
                'list_ids' => sanitize_text_field($campaign_data['list_id']),
                'send_campaign' => 0
            );

            $sendy_api = new SSSB_Sendy_API();
            $result = $sendy_api->create_campaign($api_args);

            if (is_wp_error($result)) {
                 wp_delete_post($post_id, true);
                 wp_send_json_error(array('message' => $result->get_error_message()));
            } else {
                 wp_send_json_success(array('message' => 'Campaign saved as draft in Sendy!'));
            }
        }
    }


}

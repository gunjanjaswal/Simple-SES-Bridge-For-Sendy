<?php
/**
 * Plugin Name: Simple SES Bridge for Sendy
 * Plugin URI:  https://github.com/gunjanjaswal/Simple-SES-Bridge-for-Sendy
 * Description: Connects WordPress to Sendy (via Amazon SES) to create and send newsletters from your content.
 * Version:     1.5.1
 * Author:      Gunjan Jaswal
 * Author URI:  https://gunjanjaswal.me
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: simple-ses-bridge-for-sendy
 * Domain Path: /languages
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants.
define('SSSB_VERSION', '1.5.1');
define('SSSB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SSSB_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class SSSB_Core
{

	/**
	 * Instance of the class.
	 *
	 * @var SSSB_Core
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SSSB_Core
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->includes();
        $this->init_hooks();
        $this->instantiate_classes();
    }

    /**
     * Include required files.
     */
    private function includes()
    {
        require_once SSSB_PLUGIN_DIR . 'includes/class-sendy-api.php';
        require_once SSSB_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once SSSB_PLUGIN_DIR . 'includes/class-newsletter-builder.php';
    }

    /**
     * Instantiate classes.
     */
    private function instantiate_classes()
    {
        if (is_admin()) {
            new SSSB_Admin_Settings();
            new SSSB_Newsletter_Builder();
        }
    }

    /**
     * Init hooks.
     */
    private function init_hooks()
    {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('init', array($this, 'register_post_type'));
        add_action('sssb_send_scheduled_campaign', array($this, 'send_scheduled_campaign'));
        add_action('admin_notices', array($this, 'check_overdue_campaigns'));
        add_action('admin_init', array($this, 'handle_manual_send'));
    }

    /**
     * Register Custom Post Type for Campaigns
     */
    public function register_post_type()
    {
        register_post_type(
            'sssb_campaign',
            array(
                'labels'      => array(
                    'name'          => __('Campaigns', 'simple-ses-bridge-for-sendy'),
                    'singular_name' => __('Campaign', 'simple-ses-bridge-for-sendy'),
                ),
                'public'      => false,
                'show_ui'     => true, // Show in admin to let user see history/status
                'show_in_menu' => 'simple_sendy_bridge',
                'supports'    => array('title', 'custom-fields', 'editor'), // editor can hold HTML content
                'capability_type' => 'post',
                'capabilities' => array(
                    'create_posts' => false, // Only created via code
                ),
                'map_meta_cap' => true,
            )
        );

        // Add custom columns
        add_filter('manage_sssb_campaign_posts_columns', array($this, 'add_campaign_columns'));
        add_action('manage_sssb_campaign_posts_custom_column', array($this, 'manage_campaign_columns'), 10, 2);
    }

    public function add_campaign_columns($columns) {
        $columns['sssb_status'] = __('Status', 'simple-ses-bridge-for-sendy');
        $columns['sssb_scheduled'] = __('Scheduled For', 'simple-ses-bridge-for-sendy');
        $columns['sssb_error'] = __('Error', 'simple-ses-bridge-for-sendy');
        return $columns;
    }

    public function manage_campaign_columns($column, $post_id) {
        switch ($column) {
            case 'sssb_status':
                $status = get_post_meta($post_id, '_sssb_status', true);
                if (!$status) $status = 'draft';
                
                $color = '#999';
                if ($status === 'sent') $color = '#46b450';
                if ($status === 'scheduled') $color = '#ffb900';
                
                echo '<span style="font-weight:bold; color:' . esc_attr($color) . ';">' . esc_html(ucfirst($status)) . '</span>';
                break;

            case 'sssb_scheduled':
                $scheduled = get_post_meta($post_id, '_sssb_scheduled_time', true);
                if ($scheduled) {
                    echo esc_html($scheduled);
                } else {
                    echo '-';
                }
                break;

            case 'sssb_error':
                $error = get_post_meta($post_id, '_sssb_send_error', true);
                if ($error) {
                    echo '<span style="color: #d63638;">' . esc_html($error) . '</span>';
                } else {
                    echo '-';
                }
                break;
        }
    }

    /**
     * Handle Scheduled Campaign Sending
     */
    public function send_scheduled_campaign($post_id)
    {
        // Check if already sent to avoid duplicates (though CPT status should handle this)
        if (get_post_meta($post_id, '_sssb_status', true) === 'sent') {
            return;
        }

        $campaign_data = array(
            'from_name' => get_post_meta($post_id, '_sssb_from_name', true),
            'from_email' => get_post_meta($post_id, '_sssb_from_email', true),
            'reply_to' => get_post_meta($post_id, '_sssb_from_email', true), // Use from_email as reply_to
            'subject' => get_the_title($post_id),
            'html_text' => get_post_field('post_content', $post_id),
            'plain_text' => get_post_meta($post_id, '_sssb_plain_text', true),
            'list_ids' => get_post_meta($post_id, '_sssb_list_id', true),
            'send_campaign' => 1 // Always send when triggered by schedule
        );

        $sendy_api = new SSSB_Sendy_API();
        $result = $sendy_api->create_campaign($campaign_data);

        if (is_wp_error($result)) {
            // Log error
            update_post_meta($post_id, '_sssb_send_error', $result->get_error_message());
            update_post_meta($post_id, '_sssb_status', 'failed');
        } else {
            // Mark as sent
            update_post_meta($post_id, '_sssb_status', 'sent');
            update_post_meta($post_id, '_sssb_sent_time', current_time('mysql'));
        }
    }

    /**
     * Check for overdue scheduled campaigns and show admin notice
     */
    public function check_overdue_campaigns()
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'sssb_campaign') === false) {
            return;
        }

        // Check for overdue scheduled campaigns
        $args = array(
            'post_type' => 'sssb_campaign',
            'post_status' => 'publish',
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary for filtering scheduled campaigns
                array(
                    'key' => '_sssb_status',
                    'value' => 'scheduled',
                ),
            ),
            'posts_per_page' => -1,
        );

        $campaigns = get_posts($args);
        $overdue = array();

        foreach ($campaigns as $campaign) {
            $scheduled_time = get_post_meta($campaign->ID, '_sssb_scheduled_time', true);
            if ($scheduled_time && strtotime($scheduled_time) < current_time('timestamp')) {
                $overdue[] = $campaign;
            }
        }

        if (!empty($overdue)) {
            foreach ($overdue as $campaign) {
                // Automatically send the overdue campaign
                $this->send_scheduled_campaign($campaign->ID);
                
                // Check if it was sent successfully
                $status = get_post_meta($campaign->ID, '_sssb_status', true);
                
                if ($status === 'sent') {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>Overdue Campaign Sent:</strong> "' . esc_html($campaign->post_title) . '" was automatically sent.</p>';
                    echo '</div>';
                } else {
                    // If it failed, show error with retry button
                    $error = get_post_meta($campaign->ID, '_sssb_send_error', true);
                    $retry_url = add_query_arg(array(
                        'action' => 'sssb_retry_send',
                        'campaign_id' => $campaign->ID,
                        'nonce' => wp_create_nonce('sssb_retry_send_' . $campaign->ID)
                    ), admin_url('admin.php'));

                    echo '<div class="notice notice-warning is-dismissible">';
                    echo '<p><strong>Campaign Failed to Auto-Send:</strong> "' . esc_html($campaign->post_title) . '"</p>';
                    if ($error) {
                        echo '<p><strong>Error:</strong> ' . esc_html($error) . '</p>';
                    }
                    echo '<p><a href="' . esc_url($retry_url) . '" class="button button-primary">Retry Send</a></p>';
                    echo '</div>';
                }
            }
        }

        // Check for failed campaigns
        $failed_args = array(
            'post_type' => 'sssb_campaign',
            'post_status' => 'publish',
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary for filtering failed campaigns
                array(
                    'key' => '_sssb_status',
                    'value' => 'failed',
                ),
            ),
            'posts_per_page' => -1,
        );

        $failed_campaigns = get_posts($failed_args);

        if (!empty($failed_campaigns)) {
            foreach ($failed_campaigns as $campaign) {
                $error = get_post_meta($campaign->ID, '_sssb_send_error', true);
                $retry_url = add_query_arg(array(
                    'action' => 'sssb_retry_send',
                    'campaign_id' => $campaign->ID,
                    'nonce' => wp_create_nonce('sssb_retry_send_' . $campaign->ID)
                ), admin_url('admin.php'));

                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Campaign Failed:</strong> "' . esc_html($campaign->post_title) . '"</p>';
                if ($error) {
                    echo '<p><strong>Error:</strong> ' . esc_html($error) . '</p>';
                }
                echo '<p><a href="' . esc_url($retry_url) . '" class="button button-primary">Retry Send</a></p>';
                echo '</div>';
            }
        }
    }

    /**
     * Handle manual send request
     */
    public function handle_manual_send()
    {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
        
        if ($action !== 'sssb_manual_send' && $action !== 'sssb_retry_send') {
            return;
        }

        if (!isset($_GET['campaign_id']) || !isset($_GET['nonce'])) {
            return;
        }

        $campaign_id = intval($_GET['campaign_id']);
        
        $nonce_action = $action === 'sssb_retry_send' ? 'sssb_retry_send_' . $campaign_id : 'sssb_manual_send_' . $campaign_id;
        
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), $nonce_action)) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Clear previous error if retrying
        if ($action === 'sssb_retry_send') {
            delete_post_meta($campaign_id, '_sssb_send_error');
        }

        // Trigger the send
        $this->send_scheduled_campaign($campaign_id);

        // Redirect back
        wp_safe_redirect(admin_url('edit.php?post_type=sssb_campaign&sent=1'));
        exit;
    }


    /**
     * Load text domain.
     */
    public function load_textdomain()
    {
        // load_plugin_textdomain('simple-ses-bridge-for-sendy', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on our plugin pages
        $screen = get_current_screen();
        
        // Check if we are on the settings page or builder page
        $is_plugin_page = false;
        if ($screen && (
            strpos($screen->base, 'simple_sendy_bridge') !== false || 
            strpos($screen->base, 'sssb_newsletter_builder') !== false ||
            'sssb_campaign' === $screen->post_type
        )) {
            $is_plugin_page = true;
        }

        if (!$is_plugin_page) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('sssb-admin-style', SSSB_PLUGIN_URL . 'admin/css/style.css', array(), SSSB_VERSION);
        wp_enqueue_script('sssb-admin-script', SSSB_PLUGIN_URL . 'admin/js/script.js', array('jquery', 'jquery-ui-datepicker'), SSSB_VERSION, true);

        
        $options = get_option('sssb_settings');

        // Auto-fetch lists from Sendy (cached). Allows force refresh via ?sssb_refresh_lists=1.
        $known_lists = array();
        if (class_exists('SSSB_Sendy_API')) {
            $sendy_api  = new SSSB_Sendy_API();
            $force      = isset($_GET['sssb_refresh_lists']);
            $known_lists = $sendy_api->get_lists($force);
        }
        
        wp_localize_script('sssb-admin-script', 'sssb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sssb_newsletter_nonce'),
            'site_url' => home_url(),
            'known_lists'      => $known_lists,
            'remembered_lists' => array_values((array) get_option('sssb_remembered_lists', array())),
            'settings' => array(
                'footer_logo' => get_option('sssb_footer_logo_url'),
                'footer_copyright' => get_option('sssb_footer_copyright', '© {year} ' . get_bloginfo('name')),
                'more_articles_link' => get_option('sssb_more_articles_link'),
                'social_instagram' => get_option('sssb_social_instagram'),
                'social_linkedin' => get_option('sssb_social_linkedin'),
                'social_twitter' => get_option('sssb_social_twitter'),
                'social_youtube' => get_option('sssb_social_youtube'),
                'footer_custom_text' => wp_kses_post(nl2br(get_option('sssb_footer_custom_text'))),
                'show_article_excerpt' => isset($options['show_article_excerpt']) ? $options['show_article_excerpt'] : '',
                // Editorial format texts (HTML allowed; newlines converted to <br>)
                'editorial_greeting'       => wp_kses_post(nl2br(get_option('sssb_editorial_greeting', ''))),
                'editorial_intro'          => wp_kses_post(nl2br(get_option('sssb_editorial_intro', ''))),
                'editorial_hero_label'     => wp_kses_post(get_option('sssb_editorial_hero_label', '')),
                'editorial_grid_heading'   => wp_kses_post(get_option('sssb_editorial_grid_heading', '')),
                'editorial_why_heading'    => wp_kses_post(get_option('sssb_editorial_why_heading', '')),
                'editorial_why_body'       => wp_kses_post(nl2br(get_option('sssb_editorial_why_body', ''))),
                'editorial_collab_heading' => wp_kses_post(get_option('sssb_editorial_collab_heading', '')),
                'editorial_collab_body'    => wp_kses_post(nl2br(get_option('sssb_editorial_collab_body', ''))),
                'editorial_about_heading'  => wp_kses_post(get_option('sssb_editorial_about_heading', '')),
                'editorial_about_body'     => wp_kses_post(nl2br(get_option('sssb_editorial_about_body', ''))),
            )
        ));
    }
}

// Initialize the plugin.
function sssb_init()
{
    return SSSB_Core::get_instance();
}
add_action('plugins_loaded', 'sssb_init');

// Add Settings link to plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'sssb_plugin_action_links');
function sssb_plugin_action_links($links)
{
    $settings_link = '<a href="' . admin_url('admin.php?page=simple_sendy_bridge') . '">' . __('Settings', 'simple-ses-bridge-for-sendy') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

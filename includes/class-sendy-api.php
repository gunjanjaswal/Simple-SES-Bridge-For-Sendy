<?php
/**
 * Sendy API Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class SSSB_Sendy_API
{

    private $installation_url;
    private $api_key;
    private $list_id;
    private $brand_id;
    private $trigger_cron;

    public function __construct()
    {
        $options = get_option('sssb_settings');
        $this->installation_url = isset($options['installation_url']) ? trailingslashit($options['installation_url']) : '';
        $this->api_key = isset($options['api_key']) ? $options['api_key'] : '';
        $this->list_id = isset($options['list_id']) ? $options['list_id'] : '';
        $this->brand_id = isset($options['brand_id']) ? $options['brand_id'] : '';
        $this->trigger_cron = isset($options['trigger_cron']) ? $options['trigger_cron'] : false;
    }

    /**
     * Create and optionally send a campaign.
     * 
     * @param array $args
     * @return array|WP_Error
     */
    public function create_campaign($args)
    {
        if (empty($this->installation_url) || empty($this->api_key)) {
            return new WP_Error('sssb_missing_config', __('Sendy settings are incomplete.', 'simple-ses-bridge-for-sendy'));
        }

        $defaults = array(
            'from_name' => '',
            'from_email' => '',
            'reply_to' => '',
            'title' => '', // Subject
            'subject' => '', // Subject (Sendy uses 'subject' or 'title' depending on version/endpoint, usually 'subject' for creation)
            'plain_text' => '',
            'html_text' => '',
            'list_ids' => $this->list_id,
            'brand_id' => $this->brand_id, // Optional
            'query_string' => '', // Optional
            'send_campaign' => 0, // 0 for draft, 1 for send
            'api_key' => $this->api_key,
        );

        $body = wp_parse_args($args, $defaults);

        // Ensure subject is set
        if (empty($body['subject']) && !empty($body['title'])) {
            $body['subject'] = $body['title'];
        }

        $endpoint = $this->installation_url . 'api/campaigns/create.php';

        $response = wp_remote_post($endpoint, array(
            'body' => $body,
            'timeout' => 60, // Increased timeout
            'sslverify' => false, // Prevent SSL handshake issues
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);

        if ('Campaign created' === $response_body || 'Campaign created and now sending' === $response_body) {
            
            // Auto-trigger Cron if enabled
            if ($this->trigger_cron) {
                $this->trigger_sendy_cron();
            }

            return array(
                'success' => true,
                'message' => $response_body,
            );
        } else {
            return new WP_Error('sssb_sendy_error', $response_body);
        }
    }

    /**
     * Fetch all lists from Sendy for the configured brand.
     * Uses api/lists/get-lists.php (Sendy 4.x+).
     * Results are cached in a transient for 10 minutes.
     *
     * @param bool $force_refresh Bypass cache if true.
     * @return array Array of ['id' => ..., 'name' => ...]; empty array on failure.
     */
    public function get_lists($force_refresh = false)
    {
        if (empty($this->installation_url) || empty($this->api_key) || empty($this->brand_id)) {
            return array();
        }

        $cache_key = 'sssb_lists_' . md5($this->installation_url . '|' . $this->brand_id);

        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $endpoint = $this->installation_url . 'api/lists/get-lists.php';

        $response = wp_remote_post($endpoint, array(
            'body' => array(
                'api_key'        => $this->api_key,
                'brand_id'       => $this->brand_id,
                'include_hidden' => 'no',
            ),
            'timeout'   => 20,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $lists = array();
        if (is_array($data)) {
            foreach ($data as $item) {
                if (isset($item['id']) && isset($item['name'])) {
                    $list_id = (string) $item['id'];
                    $lists[] = array(
                        'id'    => $list_id,
                        'name'  => (string) $item['name'],
                        'count' => $this->fetch_subscriber_count($list_id),
                    );
                }
            }
        }

        if (!empty($lists)) {
            set_transient($cache_key, $lists, 10 * MINUTE_IN_SECONDS);
        }

        return $lists;
    }

    /**
     * Fetch active subscriber count for a specific list ID.
     * Returns an integer, or null if the call fails / response isn't numeric.
     */
    private function fetch_subscriber_count($list_id)
    {
        if (empty($this->installation_url) || empty($this->api_key) || empty($list_id)) {
            return null;
        }

        $response = wp_remote_post($this->installation_url . 'api/subscribers/active-subscriber-count.php', array(
            'body' => array(
                'api_key' => $this->api_key,
                'list_id' => $list_id,
            ),
            'timeout'   => 15,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $body = trim(wp_remote_retrieve_body($response));
        return is_numeric($body) ? (int) $body : null;
    }

    /**
     * Get subscriber count (simple test method)
     */
    public function get_subscriber_count()
    {
        if (empty($this->installation_url) || empty($this->api_key) || empty($this->list_id)) {
            return false;
        }

        $endpoint = $this->installation_url . 'api/subscribers/active-subscriber-count.php';

        $body = array(
            'api_key' => $this->api_key,
            'list_id' => $this->list_id
        );

        $response = wp_remote_post($endpoint, array(
            'body' => $body,
            'timeout' => 20,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_body($response);
    }
    /**
     * Trigger Sendy's scheduled.php script
     */
    private function trigger_sendy_cron()
    {
        if (empty($this->installation_url)) {
            return;
        }

        $cron_url = $this->installation_url . 'scheduled.php';

        // Append Brand ID if available (as requested by user for multi-brand setups)
        if (!empty($this->brand_id)) {
            $cron_url = add_query_arg('i', $this->brand_id, $cron_url);
        }

        // Non-blocking request
        wp_remote_get($cron_url, array(
            'blocking' => false,
            'timeout' => 0.01,
            'sslverify' => apply_filters('sssb_cron_ssl_verify', false),
        ));
    }
}

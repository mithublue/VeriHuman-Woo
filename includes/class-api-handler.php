<?php
defined('ABSPATH') || exit;

class Verihuman_Api_Handler
{

    public function __construct()
    {
        // Ajax actions
        add_action('wp_ajax_verihuman_generate_copy', [$this, 'generate_copy']);
        add_action('wp_ajax_verihuman_delete_history', [$this, 'delete_history']);
        add_action('wp_ajax_verihuman_detect_text', [$this, 'detect_text']);
        add_action('wp_ajax_verihuman_humanize_text', [$this, 'humanize_text']);

        // Bulk actions
        add_action('wp_ajax_verihuman_bulk_analyze', [$this, 'bulk_analyze']);
        add_action('wp_ajax_verihuman_bulk_process_item', [$this, 'bulk_process_item']);
    }

    /**
     * AJAX: Detect AI in text.
     */
    public function detect_text(): void
    {
        check_ajax_referer('verihuman_nonce', 'nonce');

        $api_key = get_option('verihuman_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API key missing'], 400);
        }

        $text = $_POST['text'] ?? '';
        if (empty($text)) {
            wp_send_json_error(['message' => 'No text provided'], 400);
        }

        $response = wp_remote_post(VERIHUMAN_API_BASE . '/detect', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['text' => $text]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 502);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            wp_send_json_error(['message' => $body['error'] ?? 'SaaS Error'], $code);
        }

        wp_send_json_success($body);
    }

    /**
     * AJAX: Humanize AI text.
     */
    public function humanize_text(): void
    {
        check_ajax_referer('verihuman_nonce', 'nonce');

        $api_key = get_option('verihuman_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API key missing'], 400);
        }

        $text = $_POST['text'] ?? '';
        $tone = $_POST['tone'] ?? 'standard';

        if (empty($text)) {
            wp_send_json_error(['message' => 'No text provided'], 400);
        }

        $response = wp_remote_post(VERIHUMAN_API_BASE . '/humanize', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(['text' => $text, 'tone' => $tone]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 502);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            wp_send_json_error(['message' => $body['error'] ?? 'SaaS Error'], $code);
        }

        wp_send_json_success($body);
    }

    public function generate_copy(): void
    {
        // Verify nonce
        if (!check_ajax_referer('verihuman_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }

        $api_key = get_option('verihuman_api_key', '');
        error_log('[VeriHuman] API key from DB: ' . (empty($api_key) ? 'EMPTY' : 'Present (length: ' . strlen($api_key) . ')'));
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API key is not configured. Go to WooCommerce → Settings → VeriHuman AI to add your key.'], 400);
        }

        $product_id = absint($_POST['product_id'] ?? 0);
        $product_name = sanitize_text_field($_POST['product_name'] ?? '');
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');
        $language = sanitize_text_field($_POST['language'] ?? 'english');
        $copy_length = sanitize_text_field($_POST['copy_length'] ?? 'Medium');

        // Collect extra product context (tags, categories, attributes, price)
        $features = '';
        $keywords = '';

        if ($product_id > 0) {
            // Tags
            $tags = get_the_terms($product_id, 'product_tag');
            if (!empty($tags) && !is_wp_error($tags)) {
                $keywords = implode(', ', wp_list_pluck($tags, 'name'));
            }

            // Categories
            $cats = get_the_terms($product_id, 'product_cat');
            if (!empty($cats) && !is_wp_error($cats)) {
                $cat_names = implode(', ', wp_list_pluck($cats, 'name'));
                $features .= 'Categories: ' . $cat_names . '. ';
            }

            // WooCommerce attributes
            $product = wc_get_product($product_id);
            if ($product) {
                $attributes = $product->get_attributes();
                foreach ($attributes as $attr) {
                    if ($attr->is_taxonomy()) {
                        $terms = wc_get_product_terms($product_id, $attr->get_name(), ['fields' => 'names']);
                        $features .= ucfirst(wc_attribute_label($attr->get_name())) . ': ' . implode(', ', $terms) . '. ';
                    } else {
                        $features .= ucfirst($attr->get_name()) . ': ' . implode(', ', $attr->get_options()) . '. ';
                    }
                }

                // Price
                $price = $product->get_price();
                if ($price) {
                    $features .= 'Price: ' . get_woocommerce_currency_symbol() . $price . '. ';
                }
            }
        }

        // Add any extra context passed from JS
        if (!empty($_POST['extra_context'])) {
            $features .= ' ' . sanitize_textarea_field($_POST['extra_context']);
        }

        $payload = [
            'productName' => $product_name,
            'features' => trim($features),
            'keywords' => $keywords,
            'audience' => 'online shoppers',
            'platform' => 'woocommerce',
            'tone' => $tone,
            'copyLength' => $copy_length,
            'language' => $language,
        ];

        $api_url = VERIHUMAN_API_BASE . '/copy-gen';
        error_log('[VeriHuman] Step 1: Starting API call to ' . $api_url);
        error_log('[VeriHuman] Step 2: Payload: ' . wp_json_encode($payload));

        $response = wp_remote_post($api_url, [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            error_log('[VeriHuman] Step 3: API Request FAILED: ' . $response->get_error_message());
            wp_send_json_error(['message' => 'Could not reach VeriHuman API: ' . $response->get_error_message()], 502);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);
        error_log('[VeriHuman] Step 4: API Response Code: ' . $code);
        error_log('[VeriHuman] Step 5: API Raw Body: ' . $body_raw);

        if ($code !== 200) {
            $error_msg = $body['error'] ?? 'Unknown error from VeriHuman API (HTTP ' . $code . ').';
            wp_send_json_error(['message' => $error_msg], $code);
        }

        $generated = $body['generatedText'] ?? '';

        // Save to WP custom table history
        if ($product_id > 0 && !empty($generated)) {
            Verihuman_DB::save_history($product_id, $product_name, $generated, 'woocommerce', $tone);
        }

        wp_send_json_success(['generatedText' => $generated]);
    }

    public function delete_history(): void
    {
        $id = absint($_POST['history_id'] ?? 0);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');

        if (!wp_verify_nonce($nonce, 'verihuman_delete_history_' . $id)) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }

        Verihuman_DB::delete_history_item($id);
        wp_send_json_success();
    }

    /**
     * AJAX: Analyze bulk selection
     */
    public function bulk_analyze(): void
    {
        check_ajax_referer('verihuman_nonce', 'nonce');

        $target = sanitize_text_field($_POST['target'] ?? 'content');
        $scope = sanitize_text_field($_POST['scope'] ?? 'empty');

        // Ensure status is an array and sanitized
        $statuses = isset($_POST['status']) && is_array($_POST['status'])
            ? array_map('sanitize_text_field', $_POST['status'])
            : ['publish'];

        $categories = isset($_POST['categories']) ? array_map('absint', $_POST['categories']) : [];

        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => $statuses,
        ];

        if (!empty($categories)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $categories,
                ]
            ];
        }

        // Handle scope
        if ($scope === 'empty') {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => ($target === 'excerpt') ? '_excerpt' : '_description', // This is tricky, WP content is not usually in meta
                ]
            ];
            // Since product description is usually in post_content/post_excerpt, we need a different approach
        }

        $query = new WP_Query($args);
        $ids = $query->posts;

        // Filter by content emptiness manually if scope is 'empty'
        if ($scope === 'empty') {
            $ids = array_filter($ids, function ($id) use ($target) {
                $post = get_post($id);
                $content = ($target === 'excerpt') ? $post->post_excerpt : $post->post_content;
                return empty(trim(strip_tags($content)));
            });
            $ids = array_values($ids);
        }

        wp_send_json_success([
            'count' => count($ids),
            'ids' => $ids
        ]);
    }

    /**
     * AJAX: Process a single item for bulk
     */
    public function bulk_process_item(): void
    {
        check_ajax_referer('verihuman_nonce', 'nonce');

        $product_id = absint($_POST['product_id'] ?? 0);
        $target = sanitize_text_field($_POST['target'] ?? 'content');
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');
        $language = sanitize_text_field($_POST['language'] ?? 'english');
        $copy_length = sanitize_text_field($_POST['copy_length'] ?? 'Medium');

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Product not found'], 404);
        }

        // Reuse the same logic as single generation but capture the result
        $_POST['product_id'] = $product_id;
        $_POST['product_name'] = $product->get_name();
        $_POST['platform'] = 'woocommerce';
        $_POST['tone'] = $tone;
        $_POST['language'] = $language;
        $_POST['copy_length'] = $copy_length;

        // Mock the logic for internal call or just replicate the API call part
        // To keep it clean, let's just run the generation and save it
        $this->generate_for_bulk($product_id, $target, $tone, $language, $copy_length);
    }

    private function generate_for_bulk($product_id, $target_area, $tone, $language, $copy_length): void
    {
        $api_key = get_option('verihuman_api_key', '');
        $product = wc_get_product($product_id);
        $product_name = $product->get_name();

        // Collect context
        $features = '';
        $keywords = '';

        $tags = get_the_terms($product_id, 'product_tag');
        if (!empty($tags) && !is_wp_error($tags)) {
            $keywords = implode(', ', wp_list_pluck($tags, 'name'));
        }

        $cats = get_the_terms($product_id, 'product_cat');
        if (!empty($cats) && !is_wp_error($cats)) {
            $cat_names = implode(', ', wp_list_pluck($cats, 'name'));
            $features .= 'Categories: ' . $cat_names . '. ';
        }

        $attributes = $product->get_attributes();
        foreach ($attributes as $attr) {
            if ($attr->is_taxonomy()) {
                $terms = wc_get_product_terms($product_id, $attr->get_name(), ['fields' => 'names']);
                $features .= ucfirst(wc_attribute_label($attr->get_name())) . ': ' . implode(', ', $terms) . '. ';
            }
        }

        $payload = [
            'productName' => $product_name,
            'features' => trim($features),
            'keywords' => $keywords,
            'audience' => 'online shoppers',
            'platform' => 'woocommerce',
            'tone' => $tone,
            'copyLength' => $copy_length,
            'language' => $language,
        ];

        $response = wp_remote_post(VERIHUMAN_API_BASE . '/copy-gen', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            wp_send_json_error(['message' => $body['error'] ?? 'API Error']);
        }

        $generated = $body['generatedText'] ?? '';

        if (!empty($generated)) {
            // Update the product
            if ($target_area === 'excerpt') {
                $product->set_short_description($generated);
            } else {
                $product->set_description($generated);
            }
            $product->save();

            // Save to history
            Verihuman_DB::save_history($product_id, $product_name, $generated, 'woocommerce', $tone);
        }

        wp_send_json_success(['name' => $product_name]);
    }
}

<?php
defined('ABSPATH') || exit;

class Verihuman_Api_Handler
{

    public function __construct()
    {
        add_action('wp_ajax_verihuman_generate_copy', [$this, 'generate_copy']);
        add_action('wp_ajax_verihuman_delete_history', [$this, 'delete_history']);
    }

    public function generate_copy(): void
    {
        // Verify nonce
        if (!check_ajax_referer('verihuman_generate_copy', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }

        $api_key = get_option('verihuman_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API key is not configured. Go to Settings → VeriHuman AI to add your key.'], 400);
        }

        $product_id = absint($_POST['product_id'] ?? 0);
        $product_name = sanitize_text_field($_POST['product_name'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? 'woocommerce');
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');
        $language = sanitize_text_field($_POST['language'] ?? 'english');

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
            'platform' => $platform,
            'tone' => $tone,
            'copyLength' => 'Medium',
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
            wp_send_json_error(['message' => 'Could not reach VeriHuman API: ' . $response->get_error_message()], 502);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $error_msg = $body['error'] ?? 'Unknown error from VeriHuman API.';
            wp_send_json_error(['message' => $error_msg], $code);
        }

        $generated = $body['generatedText'] ?? '';

        // Save to WP custom table history
        if ($product_id > 0 && !empty($generated)) {
            Verihuman_DB::save_history($product_id, $product_name, $generated, $platform, $tone);
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
}

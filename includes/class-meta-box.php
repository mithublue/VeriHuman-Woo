<?php
defined('ABSPATH') || exit;

class Verihuman_Meta_Box
{

    public function __construct()
    {
        add_action('add_meta_boxes_product', [$this, 'register_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_meta_box(): void
    {
        add_meta_box(
            'verihuman-copy-generator',
            __('✨ VeriHuman AI Copy Generator', 'verihuman-woo'),
            [$this, 'render_meta_box'],
            'product',
            'side',
            'high'
        );
    }

    public function enqueue_assets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        global $post;
        if (!$post || 'product' !== $post->post_type) {
            return;
        }

        wp_enqueue_style(
            'verihuman-admin',
            VERIHUMAN_PLUGIN_URL . 'assets/css/admin.css',
            [],
            VERIHUMAN_VERSION
        );

        wp_enqueue_script(
            'verihuman-admin',
            VERIHUMAN_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            VERIHUMAN_VERSION,
            true
        );

        wp_localize_script(
            'verihuman-admin',
            'verihumanData',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('verihuman_generate_copy'),
                'platform' => get_option('verihuman_default_platform', 'woocommerce'),
                'tone' => get_option('verihuman_default_tone', 'professional'),
                'language' => get_option('verihuman_default_language', 'english'),
            ]
        );
    }

    public function render_meta_box(\WP_Post $post): void
    {
        $history = Verihuman_DB::get_history($post->ID, 5);
        $api_key = get_option('verihuman_api_key', '');
        $woo_settings_url = admin_url('admin.php?page=wc-settings&tab=verihuman');
        ?>
        <div id="verihuman-box">
            <?php if (empty($api_key)): ?>
                <div class="verihuman-notice verihuman-notice-error">
                    ❌ <strong>Invalid or missing API key.</strong><br>
                    <a href="<?php echo esc_url($woo_settings_url); ?>">Go to WooCommerce → Settings → VeriHuman AI</a> to add your
                    key.
                </div>
            <?php endif; ?>

            <!-- Generate Button -->
            <button type="button" id="verihuman-generate-btn" class="verihuman-btn verihuman-btn-primary">
                <span class="verihuman-btn-icon">✨</span>
                <span class="verihuman-btn-text">Generate AI Copy</span>
                <span class="verihuman-spinner" style="display:none;"></span>
            </button>

            <p class="verihuman-hint">Saves draft first, then generates AI copy using product data.</p>

            <!-- Status Message -->
            <div id="verihuman-status" class="verihuman-status" style="display:none;"></div>

            <!-- History Section -->
            <?php if (!empty($history)): ?>
                <div class="verihuman-history">
                    <p class="verihuman-history-title">Recent copy history:</p>
                    <ul class="verihuman-history-list">
                        <?php foreach ($history as $item): ?>
                            <li class="verihuman-history-item">
                                <div class="verihuman-history-meta">
                                    <span>
                                        <?php echo esc_html(date('M j, H:i', strtotime($item['created_at']))); ?>
                                    </span>
                                    <span class="verihuman-badge">
                                        <?php echo esc_html($item['platform']); ?>
                                    </span>
                                </div>
                                <button type="button" class="verihuman-use-history"
                                    data-copy="<?php echo esc_attr($item['generated_copy']); ?>">Use This Copy</button>
                                <button type="button" class="verihuman-delete-history" data-id="<?php echo esc_attr($item['id']); ?>"
                                    data-nonce="<?php echo wp_create_nonce('verihuman_delete_history_' . $item['id']); ?>">Delete</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

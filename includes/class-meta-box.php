<?php
defined('ABSPATH') || exit;

/**
 * Handles the meta box in the product edit screen.
 */
class Verihuman_Meta_Box
{
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Add the meta box to the product edit screen.
     */
    public function add_meta_box(): void
    {
        add_meta_box(
            'verihuman_ai_box',
            '✨ VeriHuman AI Toolbox',
            [$this, 'render_meta_box'],
            'product',
            'side',
            'high'
        );
    }

    /**
     * Enqueue CSS and JS for the meta box.
     */
    public function enqueue_assets($hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        global $post;
        if ($post->post_type !== 'product') {
            return;
        }

        wp_enqueue_style('verihuman-admin-css', VERIHUMAN_PLUGIN_URL . 'assets/css/admin.css', [], VERIHUMAN_VERSION);
        wp_enqueue_script('verihuman-admin-js', VERIHUMAN_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], VERIHUMAN_VERSION, true);

        // Pass data to JS
        wp_localize_script('verihuman-admin-js', 'verihumanData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('verihuman_nonce'),
            'platform' => get_option('verihuman_default_platform', 'woocommerce'),
            'tone' => get_option('verihuman_default_tone', 'professional'),
            'language' => get_option('verihuman_default_language', 'english'),
        ]);
    }

    /**
     * Render the meta box content.
     */
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

            <!-- Tab Navigation -->
            <div class="verihuman-tabs">
                <button type="button" class="verihuman-tab-btn active" data-tab="generate">✨ Generate</button>
                <button type="button" class="verihuman-tab-btn" data-tab="detect">🔍 Detect</button>
                <button type="button" class="verihuman-tab-btn" data-tab="humanize">🫂 Humanize</button>
            </div>

            <!-- Tab: Generate -->
            <div id="verihuman-tab-generate" class="verihuman-tab-content active">
                <button type="button" id="verihuman-generate-btn" class="verihuman-btn verihuman-btn-primary">
                    <span class="verihuman-btn-icon">✨</span>
                    <span class="verihuman-btn-text">Generate AI Copy</span>
                    <span class="verihuman-spinner" style="display:none;"></span>
                </button>
                <p class="verihuman-hint">Saves draft first, then generates AI copy using product data.</p>
            </div>

            <!-- Tab: Detect -->
            <div id="verihuman-tab-detect" class="verihuman-tab-content">
                <div id="verihuman-selection-notice-detect" class="verihuman-selection-notice" style="display:none;">
                    📍 Selection detected. Analyzing only the highlighted part.
                </div>
                <button type="button" id="verihuman-detect-btn" class="verihuman-btn verihuman-btn-secondary">
                    <span class="verihuman-btn-text">Check AI Score</span>
                    <span class="verihuman-spinner" style="display:none;"></span>
                </button>
                <div id="verihuman-detect-result" class="verihuman-result-box" style="display:none;"></div>
            </div>

            <!-- Tab: Humanize -->
            <div id="verihuman-tab-humanize" class="verihuman-tab-content">
                <div id="verihuman-selection-notice-humanize" class="verihuman-selection-notice" style="display:none;">
                    📍 Selection detected. Humanizing highlighted text.
                </div>
                <div class="verihuman-field-group">
                    <label>Tone:</label>
                    <select id="verihuman-humanize-tone">
                        <option value="standard">Standard</option>
                        <option value="casual">Casual</option>
                        <option value="formal">Formal</option>
                        <option value="academic">Academic</option>
                    </select>
                </div>
                <button type="button" id="verihuman-humanize-btn" class="verihuman-btn verihuman-btn-secondary">
                    <span class="verihuman-btn-text">Humanize Content</span>
                    <span class="verihuman-spinner" style="display:none;"></span>
                </button>
                <div id="verihuman-humanize-result" class="verihuman-result-box" style="display:none;">
                    <div class="verihuman-result-text"></div>
                    <button type="button" id="verihuman-apply-humanized" class="verihuman-btn verihuman-btn-small">Apply to
                        Editor</button>
                </div>
            </div>

            <!-- Source Toggle -->
            <div class="verihuman-source-toggle">
                <span>Target:</span>
                <label>
                    <input type="radio" name="verihuman_target" value="content" checked> Main Desc.
                </label>
                <label>
                    <input type="radio" name="verihuman_target" value="excerpt"> Short Desc.
                </label>
            </div>

            <!-- Global Status -->
            <div id="verihuman-status" class="verihuman-status" style="display:none;"></div>

            <!-- History Section (Always Visible) -->
            <div class="verihuman-history">
                <h4 class="verihuman-history-title">Recent Generations</h4>
                <?php if (empty($history)): ?>
                    <p class="verihuman-hint">No history yet.</p>
                <?php else: ?>
                    <ul class="verihuman-history-list">
                        <?php foreach ($history as $item): ?>
                            <li class="verihuman-history-item">
                                <div class="verihuman-history-meta">
                                    <span class="verihuman-badge"><?php echo esc_html(ucfirst($item['platform'] ?? 'AI')); ?></span>
                                    <span>
                                        <?php 
                                        $time = !empty($item['created_at']) ? strtotime($item['created_at']) : 0;
                                        echo $time ? esc_html(human_time_diff($time, current_time('timestamp'))) . ' ago' : 'Just now';
                                        ?>
                                    </span>
                                </div>
                                <div class="verihuman-history-actions">
                                    <button type="button" class="verihuman-use-history"
                                        data-copy="<?php echo esc_attr($item['generated_copy'] ?? ''); ?>">Use</button>
                                    <button type="button" class="verihuman-delete-history"
                                        data-id="<?php echo esc_attr($item['id']); ?>"
                                        data-nonce="<?php echo wp_create_nonce('verihuman_delete_' . $item['id']); ?>">Delete</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

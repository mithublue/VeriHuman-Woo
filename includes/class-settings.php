<?php
defined('ABSPATH') || exit;

class Verihuman_Settings
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu_page(): void
    {
        add_options_page(
            __('VeriHuman AI Settings', 'verihuman-woo'),
            __('VeriHuman AI', 'verihuman-woo'),
            'manage_options',
            'verihuman-ai-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('verihuman_settings_group', 'verihuman_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('verihuman_settings_group', 'verihuman_default_platform', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'woocommerce',
        ]);
        register_setting('verihuman_settings_group', 'verihuman_default_tone', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'professional',
        ]);
        register_setting('verihuman_settings_group', 'verihuman_default_language', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'english',
        ]);
    }

    public function render_settings_page(): void
    {
        ?>
        <div class="wrap">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
                <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                    <rect width="36" height="36" rx="8" fill="#4F46E5" />
                    <path d="M10 18h16M18 10v16" stroke="white" stroke-width="2.5" stroke-linecap="round" />
                </svg>
                <div>
                    <h1 style="margin:0;font-size:1.5rem;font-weight:700">VeriHuman AI</h1>
                    <p style="margin:0;color:#6B7280;font-size:0.875rem">WooCommerce Marketing Copy Generator</p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('verihuman_settings_group'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="verihuman_api_key">
                                <?php _e('API Key', 'verihuman-woo'); ?>
                            </label></th>
                        <td>
                            <input id="verihuman_api_key" name="verihuman_api_key" type="password" class="regular-text"
                                value="<?php echo esc_attr(get_option('verihuman_api_key')); ?>"
                                placeholder="vh_xxxxxxxxxxxxxxxx" />
                            <p class="description">Generate your key at <a href="https://verihuman.xyz/dashboard/api-keys"
                                    target="_blank">verihuman.xyz/dashboard/api-keys</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="verihuman_default_platform">
                                <?php _e('Default Platform', 'verihuman-woo'); ?>
                            </label></th>
                        <td>
                            <select id="verihuman_default_platform" name="verihuman_default_platform">
                                <?php
                                $platforms = [
                                    'woocommerce' => 'WooCommerce Product',
                                    'ecommerce' => 'General E-Commerce (Amazon/Shopify)',
                                    'noon' => 'Noon',
                                    'social' => 'Social Media (Facebook/Instagram)',
                                    'google_ads' => 'Google Ads',
                                    'blog' => 'Blog / SEO Article',
                                ];
                                $current = get_option('verihuman_default_platform', 'woocommerce');
                                foreach ($platforms as $value => $label) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($value),
                                        selected($current, $value, false),
                                        esc_html($label)
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="verihuman_default_tone">
                                <?php _e('Default Tone', 'verihuman-woo'); ?>
                            </label></th>
                        <td>
                            <select id="verihuman_default_tone" name="verihuman_default_tone">
                                <?php
                                $tones = ['professional', 'persuasive', 'friendly', 'luxury', 'urgent', 'respectful_aspirational'];
                                $current = get_option('verihuman_default_tone', 'professional');
                                foreach ($tones as $tone) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($tone),
                                        selected($current, $tone, false),
                                        esc_html(ucwords(str_replace('_', ' ', $tone)))
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="verihuman_default_language">
                                <?php _e('Default Language', 'verihuman-woo'); ?>
                            </label></th>
                        <td>
                            <select id="verihuman_default_language" name="verihuman_default_language">
                                <?php
                                $langs = ['english' => 'English', 'bengali' => 'Bengali (বাংলা)', 'arabic' => 'Arabic (العربية)'];
                                $current = get_option('verihuman_default_language', 'english');
                                foreach ($langs as $val => $label) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($val),
                                        selected($current, $val, false),
                                        esc_html($label)
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'verihuman-woo')); ?>
            </form>
        </div>
        <?php
    }
}

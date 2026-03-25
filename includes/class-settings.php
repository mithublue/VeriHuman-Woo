<?php
defined('ABSPATH') || exit;

class Verihuman_Settings
{
    public function __construct()
    {
        // Hook into WooCommerce settings tabs
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_woo_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_verihuman', [$this, 'render_settings_tab']);
        add_action('woocommerce_update_options_verihuman', [$this, 'save_settings']);
    }

    /**
     * Add a new tab to WooCommerce > Settings
     */
    public function add_woo_settings_tab(array $tabs): array
    {
        $tabs['verihuman'] = __('VeriHuman AI', 'verihuman-woo');
        return $tabs;
    }

    /**
     * Render the settings fields in the WooCommerce tab
     */
    public function render_settings_tab(): void
    {
        woocommerce_admin_fields($this->get_fields());
    }

    /**
     * Save the settings when the WooCommerce settings form is submitted
     */
    public function save_settings(): void
    {
        woocommerce_update_options($this->get_fields());
    }

    /**
     * Define the settings fields using WooCommerce's helper format
     */
    public function get_fields(): array
    {
        return [
            // Section Start
            [
                'title' => __('VeriHuman AI Copy Generator', 'verihuman-woo'),
                'desc' => __('Configure your VeriHuman AI integration for WooCommerce product copy generation.', 'verihuman-woo'),
                'type' => 'title',
                'id' => 'verihuman_section_title',
            ],

            // API Key
            [
                'title' => __('API Key', 'verihuman-woo'),
                'desc' => sprintf(
                    __('Generate your API key at <a href="%s" target="_blank">verihuman.xyz/dashboard/api-keys</a>.', 'verihuman-woo'),
                    'https://verihuman.xyz/dashboard/api-keys'
                ),
                'id' => 'verihuman_api_key',
                'type' => 'password',
                'css' => 'min-width:350px;',
                'desc_tip' => false,
            ],

            // Default Platform
            [
                'title' => __('Default Platform', 'verihuman-woo'),
                'id' => 'verihuman_default_platform',
                'type' => 'select',
                'default' => 'woocommerce',
                'options' => [
                    'woocommerce' => __('WooCommerce Product', 'verihuman-woo'),
                    'ecommerce' => __('General E-Commerce (Amazon / Shopify)', 'verihuman-woo'),
                    'noon' => __('Noon', 'verihuman-woo'),
                    'social' => __('Social Media (Facebook / Instagram)', 'verihuman-woo'),
                    'google_ads' => __('Google Ads', 'verihuman-woo'),
                    'blog' => __('Blog / SEO Article', 'verihuman-woo'),
                ],
            ],

            // Default Tone
            [
                'title' => __('Default Tone', 'verihuman-woo'),
                'id' => 'verihuman_default_tone',
                'type' => 'select',
                'default' => 'professional',
                'options' => [
                    'professional' => __('Professional', 'verihuman-woo'),
                    'respectful_aspirational' => __('Respectful & Aspirational', 'verihuman-woo'),
                    'persuasive' => __('Persuasive', 'verihuman-woo'),
                    'friendly' => __('Friendly', 'verihuman-woo'),
                    'luxury' => __('Luxury & Elegant', 'verihuman-woo'),
                    'urgent' => __('Urgent (Sales)', 'verihuman-woo'),
                ],
            ],

            // Default Language
            [
                'title' => __('Default Language', 'verihuman-woo'),
                'id' => 'verihuman_default_language',
                'type' => 'select',
                'default' => 'english',
                'options' => [
                    'english' => __('English', 'verihuman-woo'),
                    'bengali' => __('Bengali (বাংলা)', 'verihuman-woo'),
                    'arabic' => __('Arabic (العربية)', 'verihuman-woo'),
                ],
            ],

            // Section End
            [
                'type' => 'sectionend',
                'id' => 'verihuman_section_end',
            ],
        ];
    }
}

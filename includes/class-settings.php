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
        add_action('admin_menu', [$this, 'add_dashboard_menu']);
    }

    /**
     * Add sub-menu under WooCommerce
     */
    public function add_dashboard_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('VeriHuman Usage', 'verihuman-woo'),
            __('VeriHuman Usage', 'verihuman-woo'),
            'manage_options',
            'verihuman-dashboard',
            [$this, 'render_dashboard_page']
        );
    }

    public function render_dashboard_page(): void
    {
        $saas_url = str_replace('/api/v1', '', VERIHUMAN_API_BASE);
        $dashboard_url = $saas_url . '/dashboard?embed=true';
        ?>
        <div class="wrap verihuman-dashboard-wrap" style="margin: 0; padding: 0;">
            <h1 style="display: none;"><?php _e('VeriHuman Usage', 'verihuman-woo'); ?></h1>
            <iframe src="<?php echo esc_url($dashboard_url); ?>" width="100%" height="1200px"
                style="border: none; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); background: #fff; margin-top: 20px;"
                allow="clipboard-write"></iframe>
        </div>
        <?php
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
                    __('Generate your API key at <a href="%s" target="_blank">%s/dashboard/api-keys</a>.', 'verihuman-woo'),
                    str_replace('/api/v1', '', VERIHUMAN_API_BASE) . '/dashboard/api-keys',
                    str_replace(['https://', 'http://'], '', str_replace('/api/v1', '', VERIHUMAN_API_BASE))
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

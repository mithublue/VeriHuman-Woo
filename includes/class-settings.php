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
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Add admin menus
     */
    public function add_admin_menus(): void
    {
        // Usage Dashboard under WooCommerce
        add_submenu_page(
            'woocommerce',
            __('VeriHuman Usage', 'verihuman-woo'),
            __('VeriHuman Usage', 'verihuman-woo'),
            'manage_options',
            'verihuman-dashboard',
            [$this, 'render_dashboard_page']
        );

        // Bulk Generator under Products
        add_submenu_page(
            'edit.php?post_type=product',
            __('Bulk AI Generator', 'verihuman-woo'),
            __('Bulk AI Generator', 'verihuman-woo'),
            'manage_options',
            'verihuman-bulk-generator',
            [$this, 'render_bulk_generator_page']
        );
    }

    /**
     * Enqueue assets for VeriHuman pages
     */
    public function enqueue_admin_assets($hook): void
    {
        // Load on our custom bulk page or WC settings tab
        $is_bulk_page = (isset($_GET['page']) && $_GET['page'] === 'verihuman-bulk-generator');
        $is_settings_tab = (isset($_GET['page']) && $_GET['page'] === 'wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'verihuman');

        if (!$is_bulk_page && !$is_settings_tab) {
            return;
        }

        wp_enqueue_style('verihuman-admin-css', VERIHUMAN_PLUGIN_URL . 'assets/css/admin.css', [], VERIHUMAN_VERSION);
        
        // WooCommerce Backend Styles and Enhanced Select
        wp_enqueue_style('woocommerce_admin_styles');
        wp_enqueue_script('wc-enhanced-select');

        wp_enqueue_script('verihuman-admin-js', VERIHUMAN_PLUGIN_URL . 'assets/js/admin.js', ['jquery', 'wc-enhanced-select'], VERIHUMAN_VERSION, true);

        // Localize data
        $default_tone = get_option('verihuman_default_tone', 'professional');
        $default_lang = get_option('verihuman_default_language', 'english');

        wp_localize_script('verihuman-admin-js', 'verihumanData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('verihuman_nonce'),
            'tone' => $default_tone,
            'language' => $default_lang,
        ]);
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
     * Render the standalone Bulk Generator page under Products
     */
    public function render_bulk_generator_page(): void
    {
        ?>
        <div class="wrap">
            <h1>✨ <?php _e('Bulk AI Copy Generator', 'verihuman-woo'); ?></h1>
            <?php $this->render_bulk_generator_ui(); ?>
        </div>
        <?php
    }

    private function render_bulk_generator_ui(): void
    {
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $default_tone = get_option('verihuman_default_tone', 'professional');
        $default_copy_length = get_option('verihuman_default_copy_length', 'Medium');
        $default_lang = get_option('verihuman_default_language', 'english');

        // Match WP locale if no language set
        if (empty($user_lang)) {
            $wp_lang = explode('_', get_locale())[0];
            if ($wp_lang === 'bn')
                $default_lang = 'bengali';
            elseif ($wp_lang === 'ar')
                $default_lang = 'arabic';
        }
        ?>
        <div class="wrap" style="margin-top:20px;">
            <p><?php _e('Select your filters and generate high-converting descriptions for multiple products at once.', 'verihuman-woo'); ?></p>

            <table class="form-table">
                <tbody>
                    <!-- Filters -->
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="vh-bulk-target">Target Field</label>
                        </th>
                        <td class="forminp">
                            <select id="vh-bulk-target" style="min-width:350px;">
                                <option value="content">Main Description</option>
                                <option value="excerpt">Short Description</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="vh-bulk-scope">Scope</label>
                        </th>
                        <td class="forminp">
                            <select id="vh-bulk-scope" style="min-width:350px;">
                                <option value="empty" selected>Only Empty Descriptions</option>
                                <option value="all">Analyze All Products (Overwrite)</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="vh-bulk-status">Product Status</label>
                        </th>
                        <td class="forminp">
                            <select id="vh-bulk-status" multiple="multiple" class="wc-enhanced-select" style="min-width:350px;" data-placeholder="Select status...">
                                <option value="publish" selected>Published</option>
                                <option value="draft">Draft</option>
                                <option value="pending">Pending</option>
                                <option value="private">Private</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="vh-bulk-cats">Include Categories</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <label><input type="checkbox" id="vh-bulk-all-cats" checked> <strong>All Categories</strong></label>
                                <div id="vh-cat-list" style="display:none; margin-top:10px; max-height:200px; overflow-y:auto; padding:10px; border:1px solid #ccd0d4; background:#fff; min-width:330px; max-width:s400px; border-radius:3px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.07);">
                                    <?php foreach ($categories as $cat): ?>
                                        <label style="display:block; margin-bottom:5px;">
                                            <input type="checkbox" class="vh-cat-item" value="<?php echo esc_attr($cat->term_id); ?>">
                                            <?php echo esc_html($cat->name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <!-- Generation Settings -->
                    <tr valign="top">
                        <th scope="row" class="titledesc" colspan="2" style="padding-top:30px;">
                            <h3 style="margin:0; border-bottom: 1px solid #eee; padding-bottom:10px;">Generation Settings</h3>
                        </th>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="vh-bulk-tone">Tone</label>
                        </th>
                        <td class="forminp">
                            <select id="vh-bulk-tone" style="min-width:350px;">
                                <option value="professional" <?php selected($default_tone, 'professional'); ?>>Professional</option>
                                <option value="respectful_aspirational" <?php selected($default_tone, 'respectful_aspirational'); ?>>Respectful & Aspirational</option>
                                <option value="persuasive" <?php selected($default_tone, 'persuasive'); ?>>Persuasive</option>
                                <option value="friendly" <?php selected($default_tone, 'friendly'); ?>>Friendly</option>
                                <option value="luxury" <?php selected($default_tone, 'luxury'); ?>>Luxury & Elegant</option>
                                <option value="urgent" <?php selected($default_tone, 'urgent'); ?>>Urgent</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="vh-bulk-lang">Language</label>
                        </th>
                        <td class="forminp">
                            <select id="vh-bulk-lang" style="min-width:350px;">
                                <option value="english" <?php selected($default_lang, 'english'); ?>>English</option>
                                <option value="bengali" <?php selected($default_lang, 'bengali'); ?>>Bengali</option>
                                <option value="arabic" <?php selected($default_lang, 'arabic'); ?>>Arabic</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label>Copy Length</label>
                        </th>
                        <td class="forminp">
                            <fieldset style="display: flex; gap: 15px; align-items: center; min-height:30px;">
                                <label><input type="radio" name="vh_bulk_length" value="Short" <?php checked($default_copy_length, 'Short'); ?>> Short</label>
                                <label><input type="radio" name="vh_bulk_length" value="Medium" <?php checked($default_copy_length, 'Medium'); ?>> Medium</label>
                                <label><input type="radio" name="vh_bulk_length" value="Long" <?php checked($default_copy_length, 'Long'); ?>> Long</label>
                                <label><input type="radio" name="vh_bulk_length" value="Custom" <?php checked($default_copy_length, 'Custom'); ?>> Custom</label>
                                
                                <div id="vh-bulk-custom-length-wrap" style="display: <?php echo ($default_copy_length === 'Custom') ? 'inline-block' : 'none'; ?>; margin-left: 15px;">
                                    <input type="number" id="vh-bulk-custom-length" min="10" max="2000" placeholder="e.g. 100" style="width: 80px;">
                                    <span class="description" style="margin-left:5px;">words</span>
                                </div>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div style="margin-top:20px; padding:20px; background:#fff; border:1px solid #ccd0d4; max-width:800px; border-radius:4px;">
                <div id="vh-bulk-summary" style="margin-bottom:0; display: flex; align-items: center; gap: 15px;">
                    <button type="button" id="vh-bulk-start" class="button button-primary button-hero">🚀 Generate Bulk Descriptions</button>
                    <span class="description"><?php _e('This will fetch products based on filters above and generate AI copy for each.', 'verihuman-woo'); ?></span>
                </div>

                <div id="vh-bulk-progress-wrap" style="display:none; margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                    <div style="background:#e5e5e5; height:20px; border-radius:10px; overflow:hidden; margin-bottom:10px;">
                        <div id="vh-progress-bar" style="background:#007cba; height:100%; width:0%; transition:width 0.3s;"></div>
                    </div>
                    <div style="font-weight:600; margin-bottom:15px; color:#3c434a;">
                        <span id="vh-processed">0</span> / <span id="vh-total">0</span> Processed (<span id="vh-percent">0%</span>)
                    </div>
                    
                    <div style="background:#1e1e1e; color:#00ff00; font-family:monospace; padding:15px; height:250px; overflow-y:auto; border-radius:4px; font-size:13px; margin-bottom:15px;">
                        <ul id="vh-bulk-log" style="margin:0; padding:0; list-style:none;"></ul>
                    </div>

                    <button type="button" id="vh-bulk-stop" class="button" style="color:#b32d2e; border-color:#b32d2e;">Stop Process</button>
                </div>
            </div>
        </div>
        <script>
            // Ensure WC Enhanced Select initializes our multi-select
            jQuery(document).ready(function($) {
                if ($.fn.select2) {
                    $('#vh-bulk-status').select2();
                }
            });
        </script>
        <?php
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

            // Default Copy Length
            [
                'title' => __('Default Copy Length', 'verihuman-woo'),
                'id' => 'verihuman_default_copy_length',
                'type' => 'select',
                'default' => 'Medium',
                'options' => [
                    'Short' => __('Short', 'verihuman-woo'),
                    'Medium' => __('Medium', 'verihuman-woo'),
                    'Long' => __('Long', 'verihuman-woo'),
                    'Custom' => __('Custom', 'verihuman-woo'),
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

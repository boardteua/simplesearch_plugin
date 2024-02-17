<?php
/*
Plugin Name: Simple Search Shortcode
Plugin URI: https://weekdays.te.ua
Description: Simple search shortcode
Author: org100h
Version: 1.0.0
Author URI: https://weekdays.te.ua
Text Domain: ss
*/

defined('\ABSPATH') || die('No direct script access allowed!');

if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

class simpleSearch
{
    /**
     * The unique instance of the plugin.
     */
    private static $instance = null;
    public static $pref = 'ss';
    public static $class = 'ss';
    public static function get_instance(): SimpleSearch
    {
        if (null == self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Register assets
        add_action('wp_enqueue_scripts', [$this, 'frontAssets']);

        //Add Rest api endpoint to return wat we have
        add_action('rest_api_init', [$this, 'searchEndpoint']);

        // Register shortcode for search
        add_shortcode('custom_search', [$this, 'searchShortCode']);

        // Register options page
        add_action('admin_menu', [$this, 'ss_options_page']);

        // Options Init
        add_action('admin_init', [$this, 'ss_options_init']);

    }

    /**
     * Load assets to site frontend
     */

    public function frontAssets(): void
    {
        // vue2 from cdn
        wp_enqueue_script(
            self::$pref . '-vue',
            'https://cdn.jsdelivr.net/npm/vue@2',
            [],
            '1.0',
            true
        );

        // app for front
        wp_enqueue_script(
            self::$pref . '-index',
            plugins_url('/js/functions.js', __FILE__),
            [self::$pref . '-vue'],
            '1.0',
            true
        );

        wp_localize_script(
            self::$pref . '-index',
            self::$pref . '_obj',
            [
                'prefix' => self::$pref,
                'url' => site_url(),
                'symbolError' => __('The entered value contains invalid characters.', 'ss'),
                'noItems' => __('No results found.', 'ss'),
                'emptyReq' => __('Add some search text.', 'ss'),
                'appId' => self::$class
            ]
        );

        wp_enqueue_style(
            self::$pref . '-css',
            plugins_url('/css/style.css', __FILE__)
        );
    }

    /**
     * Return shortcode
     *
     * @param array $atts
     * @param string|null $content
     * @return string
     */
    public function searchShortCode($atts, $content = null): string
    {
        $label = __('Search', 'ss');
        $atts = shortcode_atts(
            [
                'post-type' => 'post',
                'class' => 'simplesearch',
                'element-count' => false,
                'view' => false,
            ],
            $atts,
            'custom_search'
        );
        self::$class = $atts['class'];
        $options = get_option(self::$pref . '_options');

        $elementCount = $atts['element-count'] ? $atts['element-count'] : $options['items_per_page'];
        $view = $atts['view'] ? $atts['view'] : $options['grid_or_list'];
        ob_start();
        include('template/search.php');
        $output_string = ob_get_contents();
        ob_end_clean();
        return $output_string;

    }

    /**
     * Register route for search
     */
    public function searchEndpoint(): void
    {
        register_rest_route(self::$pref . '/v1', '/search', [
            'methods' => 'POST',
            'callback' => [$this, 'searchCallback'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Callback for search
     */
    public function searchCallback($request): object
    {
        $data = $request->get_json_params();

        // Let`s make new query object
        $query = new WP_Query([
            'post_type' => explode(',', $data['type'] ?? 'post'),
            's' => $data['text'],
            'posts_per_page' => $data['count'],
            'paged' => $data['page']
        ]);

        $res['info']['pages'] = $query->max_num_pages;
        $res['info']['total'] = $query->found_posts;

        // Let`s parse
        if ($query->have_posts()) {
            // Get the results
            while ($query->have_posts()) {
                $query->the_post();
                $res['items'][] = [
                    'title' => get_the_title(),
                    'content' => wp_trim_words(get_the_content(), 200, '...'),
                    'link' => get_permalink()
                ];
            }
            return rest_ensure_response($res);
        } else {
            $error = new WP_Error(
                'no_results',
                __('No results found.', 'ss'),
                ['status' => 404]
            );

            // Return Error Message
            return rest_ensure_response($error);
        }
    }

    /**
     * Seardh options page
     */
    public function ss_options_page(): void
    {
        add_options_page(
            'SimpleSearch', // Page name
            'SimpleSearch', // Nav label
            'manage_options',  // User perms
            self::$pref . '-options',  // slug
            [$this, 'ss_options_page_content'] // callback-func for content
        );
    }

    /**
     * Options page calback
     */
    public function ss_options_page_content(): void
    {
        ?>
                        <div class="wrap">
                            <h2><?= __('Simple Search Options', 'ss') ?></h2>
                            <form method="post" action="options.php">
                                <?php
                                settings_fields(self::$pref . '_options_group');
                                do_settings_sections('ss-options');
                                submit_button();
                                ?>
                            </form>
                        </div>
                        <?php
    }
    /**
     * Init options page
     */
    public function ss_options_init(): void
    {
        // Lets register our options
        register_setting(self::$pref . '_options_group', self::$pref . '_options', [$this, 'ss_options_sanitize']);

        // Section on setting page
        add_settings_section(
            self::$pref . '_options_section',
            '<hr />',
            [$this, 'ss_options_section_callback'],
            self::$pref . '-options'
        );

        add_settings_field(
            'grid_or_list',
            __('Grid or list', 'ss'),
            [$this, 'ss_default_view_type_callback'],
            self::$pref . '-options',
            self::$pref . '_options_section'
        );

        add_settings_field(
            'items_per_page',
            __('Item Per Page', 'ss'),
            [$this, 'ss_items_per_page_callback'],
            self::$pref . '-options',
            self::$pref . '_options_section'
        );
    }

    /**
     * Callback for section content.
     */
    public function ss_options_section_callback(): void
    {
        echo '<p>Default settings for shortcode <code>[custom_search post-type="post" element-count="6" class="my__search" ]</code>
        </p>';
    }

    /**
     * Grid or list selector callback
     */
    public function ss_default_view_type_callback(): void
    {
        $options = get_option(self::$pref . '_options');
        $grid_or_list = $options['grid_or_list'] ?? '';
        ?>
                        <select name="<?= self::$pref ?>_options[grid_or_list]">
                            <option value="grid" <?php selected($grid_or_list, 'grid'); ?>><?= __('Grid', 'ss') ?></option>
                            <option value="list" <?php selected($grid_or_list, 'list'); ?>><?= __('List', 'ss') ?></option>
                        </select>
                        <?php
    }

    /**
     * Grid or list selector callback
     */
    public function ss_items_per_page_callback(): void
    {
        $options = get_option(self::$pref . '_options');
        ?>
                        <input type="number" name="<?= self::$pref ?>_options[items_per_page]" value="<?= $options['items_per_page'] ?? '6' ?>">
                        <?php
    }

    /**
     * Simple sanitize
     *  */
    public function ss_options_sanitize($input): array
    {
        $output = array();
        // Очищаємо значення перед збереженням
        foreach ($input as $key => $value) {
            $output[$key] = sanitize_text_field($value);
        }
        return $output;
    }
}

/*
 * Load lang file
 */

add_action('plugins_loaded', function () {
    load_plugin_textdomain(simpleSearch::$pref, false, dirname(plugin_basename(__FILE__)) . '/lang/');
});

// Init
$simpleSearch = simpleSearch::get_instance();



<?php

/*
Plugin Name: WPU Store locator
Description: Manage stores localizations
Version: 0.16
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Thanks to : http://biostall.com/performing-a-radial-search-with-wp_query-in-wordpress
*/

class WPUStoreLocator {
    private $script_version = '0.16';
    private $country_code = '';
    public $use_markerclusterer = 0;
    public $use_preventsingle = 0;

    private $csv_separators = array(
        ';',
        ',',
    );
    private $notices_categories = array(
        'updated',
        'update-nag',
        'error'
    );

    function __construct() {

        global $wpdb;
        $this->options = array(
            'name' => 'WPU Store locator',
            'id' => 'wpustorelocator',
            'level' => 'manage_options',
        );
        $this->table_name = $wpdb->prefix . 'wpustorelocatorlist';
        $this->serverapi_key = get_option('wpustorelocator_serverapikey');
        $this->frontapi_key = get_option('wpustorelocator_frontapikey');
        $this->use_markerclusterer = (get_option('wpustorelocator_use_markerclusterer') == 1);
        $this->use_preventsingle = (get_option('wpustorelocator_preventsingle') == 1);
        $this->selectcountryrequired = (get_option('wpustorelocator_selectcountryrequired') == 1);
        $this->limitsearchincountry = (get_option('wpustorelocator_limitsearchincountry') == 1);

        $pages = array(
            'admin' => array(
                'name' => __('Store locator', 'wpustorelocator') ,
                'function_content' => array(&$this,
                    'admin_options'
                ) ,
                'function_action' => array(&$this,
                    'admin_options_postAction'
                ) ,
                'icon_url' => 'dashicons-location-alt',
                'has_file' => true
            )
        );

        if (is_admin()) {

            if (!class_exists('WPUBaseAdminPage')) {
                include dirname(__FILE__) . '/inc/class-WPUBaseAdminPage.php';
            }
            new WPUBaseAdminPage($this, $pages);
        }
        add_action('init', array(&$this,
            'init'
        ));
        add_action('init', array(&$this,
            'check_dependencies'
        ));
        add_filter('wputh_get_posttypes', array(&$this,
            'set_theme_posttypes'
        ));
        add_filter('wpu_options_tabs', array(&$this,
            'set_wpu_options_tabs'
        ) , 10, 3);
        add_filter('wpu_options_boxes', array(&$this,
            'set_wpu_options_boxes'
        ) , 10, 3);
        add_filter('wpu_options_fields', array(&$this,
            'set_wputh_options_fields'
        ) , 10, 3);
        add_filter('wputh_post_metas_boxes', array(&$this,
            'post_metas_boxes'
        ) , 10, 3);
        add_filter('wputh_post_metas_fields', array(&$this,
            'post_metas_fields'
        ) , 10, 3);
        add_action('save_post', array(&$this,
            'save_latlng'
        ) , 99);
        add_action('wp_head', array(&$this,
            'display_infos'
        ) , 99);
        add_action('wp_enqueue_scripts', array(&$this,
            'enqueue_scripts'
        ));
        add_action('admin_enqueue_scripts', array(&$this,
            'enqueue_scripts'
        ));
        add_action('add_meta_boxes_stores', array(&$this,
            'adding_custom_meta_boxes'
        ));
        add_action('plugins_loaded', array(&$this,
            'load_languages'
        ));
        add_action('plugins_loaded', array(&$this,
            'table_creation'
        ));
        add_action('template_redirect', array(&$this,
            'prevent_single'
        ));
        add_filter('admin_init', array(&$this,
            'admin_visual_cron'
        ));
        add_filter('admin_init', array(&$this,
            'admin_reload_dbcache'
        ));

        // Admin list enhancement
        if (is_admin()) {
            add_action('restrict_manage_posts', array(&$this,
                'filter_storelist_by_country'
            ));
            add_filter('parse_query', array(&$this,
                'filter_storelist_by_country_query'
            ));
            add_filter("pre_get_posts", array(&$this,
                'search_fields_admin'
            ));
        }

        // Set cron actions
        add_action('wpustorelocator_cron_event_hook', array(&$this,
            'cron'
        ));
        add_filter('cron_schedules', array(&$this,
            'add_scheduled_interval'
        ));

        // Display notices
        add_action('admin_notices', array(&$this,
            'admin_notices'
        ));
    }

    function init() {

        $this->options['archive_url'] = apply_filters('wpustorelocator_archive_url', get_post_type_archive_link('stores'));

        /* Transient */
        global $current_user;
        $this->transient_prefix = sanitize_title(basename(__FILE__)) . $current_user->ID;
        $this->transient_msg = $this->transient_prefix . '__messages';
    }

    function is_storelocator_front() {
        return apply_filters('wpustorelocator_is_storelocator_front', is_post_type_archive('stores'));
    }

    function check_dependencies() {
        if (!is_admin()) {
            return;
        }
        include_once (ABSPATH . 'wp-admin/includes/plugin.php');

        // Check for Plugins activation
        $this->plugins = array(
            'wpuoptions' => array(
                'installed' => true,
                'path' => 'wpuoptions/wpuoptions.php',
                'message_url' => '<a target="_blank" href="https://github.com/WordPressUtilities/wpuoptions">WPU Options</a>',
            ) ,
            'wpupostmetas' => array(
                'installed' => true,
                'path' => 'wpupostmetas/wpupostmetas.php',
                'message_url' => '<a target="_blank" href="https://github.com/WordPressUtilities/wpupostmetas">WPU Post metas</a>',
            ) ,
            'wpuposttypestaxos' => array(
                'installed' => true,
                'path' => 'wpuposttypestaxos/wpuposttypestaxos.php',
                'message_url' => '<a target="_blank" href="https://github.com/WordPressUtilities/wpuposttypestaxos">WPU Post types & taxonomies</a>',
            )
        );
        foreach ($this->plugins as $id => $plugin) {
            if (!is_plugin_active($plugin['path'])) {
                $this->plugins[$id]['installed'] = false;
                $this->set_message($id . '__not_installed', $this->options['name'] . ': ' . sprintf(__('The plugin %s should be installed.', 'wpustorelocator') , $plugin['message_url']) , 'error');
            }
        }
    }

    function prevent_single() {
        $prevent_single = apply_filters('wpustorelocator_preventsingle', $this->use_preventsingle);
        if (is_singular('stores') && $prevent_single) {
            wp_redirect($this->options['archive_url']);
            die;
        }
    }

    function display_infos() {
        if (!$this->is_storelocator_front() && !is_singular('stores')) {
            return;
        }
        $this->infos = array(
            'icon' => apply_filters('wpustorelocator_iconurl', '') ,
            'base_url' => $this->options['archive_url'],
        );

        echo '<script>window.wpustorelocatorconf=' . json_encode($this->infos) . ';</script>';
    }

    function load_languages() {
        load_plugin_textdomain('wpustorelocator', false, dirname(plugin_basename(__FILE__)) . '/lang');
    }

    function enqueue_scripts() {
        $lang = explode('_', get_locale());
        $mainlang = '';
        if (isset($lang[0])) {
            $mainlang = $lang[0];
        }
        wp_enqueue_script('wpustorelocator-maps', 'https://maps.googleapis.com/maps/api/js?v=3.exp&libraries=places&key=' . $this->frontapi_key . '&language=' . $mainlang . '&sensor=false', false, '3');

        if ($this->is_storelocator_front() || is_singular('stores')) {
            wp_enqueue_script('wpustorelocator-front', plugins_url('/assets/front.js', __FILE__) , array(
                'jquery'
            ) , $this->script_version, true);
            if ($this->use_markerclusterer) {
                wp_enqueue_script('wpustorelocator-markerclusterer', plugins_url('/assets/markerclusterer_packed.js', __FILE__) , array(
                    'wpustorelocator-maps'
                ) , $this->script_version, true);
            }
        }
        if (is_admin()) {
            wp_enqueue_script('wpustorelocator-back', plugins_url('/assets/back.js', __FILE__) , array(
                'jquery'
            ) , $this->script_version, true);
        }
    }

    /* ----------------------------------------------------------
      Register post type
    ---------------------------------------------------------- */

    function set_theme_posttypes($post_types) {
        $post_types['stores'] = array(
            'menu_icon' => 'dashicons-store',
            'name' => __('Store', 'wpustorelocator') ,
            'plural' => __('Stores', 'wpustorelocator') ,
            'female' => 1,
            'supports' => array(
                'title',
                'thumbnail'
            )
        );
        return $post_types;
    }

    /* ----------------------------------------------------------
      Options
    ---------------------------------------------------------- */

    function set_wpu_options_tabs($tabs) {
        $tabs['wpustorelocator'] = array(
            'name' => 'Store Locator'
        );
        return $tabs;
    }

    function set_wpu_options_boxes($boxes) {
        $boxes['wpustorelocator_settings'] = array(
            'name' => 'Settings',
            'tab' => 'wpustorelocator'
        );
        $boxes['wpustorelocator_api'] = array(
            'name' => 'API',
            'tab' => 'wpustorelocator'
        );
        return $boxes;
    }

    function set_wputh_options_fields($options) {

        /* Settings */
        $options['wpustorelocator_radiuslist'] = array(
            'label' => __('Radius list', 'wpustorelocator') ,
            'box' => 'wpustorelocator_settings',
            'type' => 'textarea'
        );
        $options['wpustorelocator_use_markerclusterer'] = array(
            'label' => __('Use marker clusterer', 'wpustorelocator') ,
            'box' => 'wpustorelocator_settings',
            'type' => 'select'
        );
        $options['wpustorelocator_preventsingle'] = array(
            'label' => __('Prevent single store view', 'wpustorelocator') ,
            'box' => 'wpustorelocator_settings',
            'type' => 'select'
        );
        $options['wpustorelocator_selectcountryrequired'] = array(
            'label' => __('Selecting a country is required', 'wpustorelocator') ,
            'box' => 'wpustorelocator_settings',
            'type' => 'select'
        );
        $options['wpustorelocator_limitsearchincountry'] = array(
            'label' => __('Search is limited to the selected country', 'wpustorelocator') ,
            'box' => 'wpustorelocator_settings',
            'type' => 'select'
        );

        /* API */
        $options['wpustorelocator_serverapikey'] = array(
            'label' => __('Server API Key', 'wpustorelocator') ,
            'box' => 'wpustorelocator_api'
        );
        $options['wpustorelocator_frontapikey'] = array(
            'label' => __('Front API Key', 'wpustorelocator') ,
            'box' => 'wpustorelocator_api'
        );
        return $options;
    }

    /* ----------------------------------------------------------
      Post metas
    ---------------------------------------------------------- */

    /* Boxes
     -------------------------- */

    function post_metas_boxes($boxes) {
        $boxes['stores_details'] = array(
            'name' => __('Details', 'wpustorelocator') ,
            'post_type' => array(
                'stores'
            )
        );
        $boxes['stores_localization'] = array(
            'name' => __('Localization', 'wpustorelocator') ,
            'post_type' => array(
                'stores'
            )
        );
        $boxes['stores_mapposition'] = array(
            'name' => __('Map position', 'wpustorelocator') ,
            'post_type' => array(
                'stores'
            )
        );
        return $boxes;
    }

    /* Fields
     -------------------------- */

    function post_metas_fields($fields) {

        /* Details */
        $fields['store_name'] = array(
            'box' => 'stores_details',
            'name' => __('Name', 'wpustorelocator')
        );
        $fields['store_email'] = array(
            'box' => 'stores_details',
            'name' => __('Email address', 'wpustorelocator')
        );
        $fields['store_openinghours'] = array(
            'box' => 'stores_details',
            'name' => __('Opening time', 'wpustorelocator') ,
            'type' => 'table',
            'columns' => array(
                'day' => array(
                    'name' => 'Jour'
                ) ,
                'time' => array(
                    'name' => 'Heures'
                ) ,
            ) ,
            'lang' => 1
        );
        $fields['store_phone'] = array(
            'box' => 'stores_details',
            'name' => __('Phone number', 'wpustorelocator')
        );

        /* Localization */
        $fields['store_address'] = array(
            'box' => 'stores_localization',
            'name' => __('Address', 'wpustorelocator') ,
        );
        $fields['store_address2'] = array(
            'box' => 'stores_localization',
            'name' => __('Address2', 'wpustorelocator') ,
        );
        $fields['store_zip'] = array(
            'box' => 'stores_localization',
            'name' => __('Zip code', 'wpustorelocator') ,
        );
        $fields['store_city'] = array(
            'box' => 'stores_localization',
            'name' => __('City', 'wpustorelocator') ,
            'admin_column' => true,
            'admin_column_sortable' => true,
        );
        $fields['store_region'] = array(
            'box' => 'stores_localization',
            'name' => __('Region', 'wpustorelocator') ,
        );
        $fields['store_country'] = array(
            'box' => 'stores_localization',
            'name' => __('Country', 'wpustorelocator') ,
            'type' => 'select',
            'admin_column' => true,
            'admin_column_sortable' => true,
            'datas' => $this->get_countries() ,
        );

        /* Map position */
        $fields['store_lat'] = array(
            'box' => 'stores_mapposition',
            'name' => __('Latitude', 'wpustorelocator')
        );
        $fields['store_lng'] = array(
            'box' => 'stores_mapposition',
            'name' => __('Longitude', 'wpustorelocator')
        );
        $fields['store_defaultlat'] = array(
            'box' => 'stores_mapposition',
            'name' => __('Default Coordinates', 'wpustorelocator') ,
            'type' => 'select',
            'admin_column' => true,
            'admin_column_sortable' => true,
        );
        $fields['store_zoom'] = array(
            'box' => 'stores_mapposition',
            'name' => __('Zoom', 'wpustorelocator')
        );
        return $fields;
    }

    /* ----------------------------------------------------------
      Get datas
    ---------------------------------------------------------- */

    function get_stores_list() {

        // Execute the query
        $location_query = get_posts(array(
            'post_type' => 'stores',
            'posts_per_page' => - 1,
            'suppress_filters' => true
        ));

        // Sort stores
        $stores = array();
        foreach ($location_query as $store) {
            $stores[$store->ID] = $this->get_store_postinfos($store);
        }

        return $stores;
    }

    function get_stores($lat = 0, $lng = 0, $radius = 10, $country = '') {

        $this->tmp_lat = $lat;
        $this->tmp_lng = $lng;
        $this->tmp_country = $country;
        $this->tmp_radius = $radius / 1.609344;

        // Add our filter before executing the query
        add_filter('posts_where', array(&$this,
            'location_posts_where'
        ));

        add_filter("pre_get_posts", array(&$this,
            'location_posts_country'
        ));

        $stores = $this->get_stores_list();

        // Remove the filter
        remove_filter('posts_where', array(&$this,
            'location_posts_where'
        ));
        remove_filter('pre_get_posts', array(&$this,
            'location_posts_country'
        ));

        return $stores;
    }

    function get_store_postinfos($post) {
        $store = array(
            'post' => $post,
            'metas' => get_post_custom($post->ID)
        );

        return $store;
    }

    function get_radius() {
        $radius_base = get_option('wpustorelocator_radiuslist');
        $radius_list = array();
        $raw_radius_list = explode("\n", $radius_base);
        foreach ($raw_radius_list as $radius) {
            $radius = trim($radius);
            if (is_numeric($radius)) {
                $radius_list[] = $radius;
            }
        }
        natsort($radius_list);
        return $radius_list;
    }

    function get_countries($full = false) {
        $is_full = $full ? '1' : '0';
        if (isset($this->countries[$is_full])) {
            return $this->countries[$is_full];
        }
        global $WPUCountryList;
        if (!isset($WPUCountryList) || !is_object($WPUCountryList)) {
            $countries = $this->get_countries_from_csv($full);
        }
        else {
            $countries = $this->get_countries_from_wpucountrylist($full);
        }

        if ($is_full) {
            uasort($countries, array(&$this,
                'sort_countries'
            ));
        }
        else {
            uasort($countries, array(&$this,
                'sort_countries_light'
            ));
        }

        $this->countries[$is_full] = $countries;

        return $countries;
    }

    function get_countries_from_wpucountrylist($full = false) {
        global $WPUCountryList;
        $WPUCountryList->load_list();
        $countries = array();
        $list = $WPUCountryList->list;
        foreach ($list as $id => $country) {
            if ($full) {
                $countries[$id] = array(
                    'lat' => $country['lat'],
                    'lng' => $country['lng'],
                    'zoom' => $country['zoom'],
                    'name' => $country['country']
                );
            }
            else {
                $countries[$id] = $country['country'];
            }
        }
        return $countries;
    }

    function get_countries_from_csv($full = false) {
        $csv = array_map('str_getcsv', file(dirname(__FILE__) . '/inc/countries.csv'));
        $locale = get_locale();
        $countries = array();
        foreach ($csv as $country) {
            $value = $country[3];
            if ($full) {
                $value = array(
                    'lat' => $country[1],
                    'lng' => $country[2],
                    'zoom' => 6,
                    'name' => $country[3]
                );
                if ($locale == 'fr_FR') {
                    $value['name'] = $country[4];
                }
            }
            $countries[$country[0]] = $value;
        }
        return $countries;
    }

    function get_stores_countries() {
        global $wpdb;
        $countries = array();
        $country_list = $this->get_countries(true);
        $used_country_list = $wpdb->get_results("SELECT DISTINCT country FROM $this->table_name;", ARRAY_A);
        foreach ($used_country_list as $country) {
            if (isset($country['country']) && array_key_exists($country['country'], $country_list)) {
                $countries[$country['country']] = $country_list[$country['country']];
            }
        }
        return $countries;
    }

    function get_current_country_code() {

        $country_code = '';
        if (!empty($this->country_code)) {
            return $this->country_code;
        }

        $current_language = apply_filters('wpustorelocator_getcurrentlanguage', strtolower(get_locale()));

        // Get coordinates of country for current language
        switch ($current_language) {
            case 'en_en':
                $country_code = 'GB';
            break;
            case 'fr_fr':
                $country_code = 'FR';
            break;
            case 'en_us':
                $country_code = 'US';
            break;
            case 'it_it':
                $country_code = 'IT';
            break;
        }
        $this->country_code = $country_code;
        return $country_code;
    }

    /* ----------------------------------------------------------
      Map datas
    ---------------------------------------------------------- */

    function get_result_map_position($search) {
        $datas = array();
        $country_list = $this->get_countries(true);

        if ($search['lat'] == 0 && $search['lng'] == 0) {

            // Country mode
            if (isset($search['country']) && !empty($search['country']) && isset($country_list[$search['country']])) {
                $country = $country_list[$search['country']];
            }
            else {
                $country_code = $this->get_current_country_code();
                if (isset($country_list[$country_code])) {
                    $country = $country_list[$country_code];
                }
            }

            if (is_array($country) && isset($country['lat'])) {
                $datas['lat'] = $country['lat'];
                $datas['lng'] = $country['lng'];
                $datas['zoom'] = $country['zoom'];
            }
        }

        // Coordinate mode
        else {
            $datas = $search;
        }

        if (isset($_GET['fromgeo'])) {
            $datas['fromgeo'] = '1';
        }

        $html = '';
        foreach ($datas as $id => $name) {
            $html.= ' data-' . $id . '="' . esc_attr($name) . '" ';
        }

        return $html;
    }

    function get_json_from_storelist($stores) {
        $prevent_single = apply_filters('wpustorelocator_preventsingle', $this->use_preventsingle);
        $datas = array();
        foreach ($stores as $store) {
            $itinerary_link = 'https://maps.google.com/?daddr=' . urlencode($this->get_address_from_store($store, '', 1));
            $store_url = parse_url(get_permalink($store['post']->ID));
            $data = array(
                'name' => $store['post']->post_title,
                'lat' => $store['metas']['store_lat'][0],
                'lng' => $store['metas']['store_lng'][0],
                'address' => $this->get_address_from_store($store, 'h3', 0, 1) ,
                'link' => '<a href="' . $store_url['path'] . '">' . __('View this store', 'wpustorelocator') . '</a>',
                'itinerary' => '<a target="_blank" href="' . $itinerary_link . '">' . __('Go to this store', 'wpustorelocator') . '</a>',
            );

            if ($prevent_single) {
                $data['link'] = '';
            }

            $datas[] = $data;
        }

        return json_encode($datas);
    }

    /* ----------------------------------------------------------
      Display helpers
    ---------------------------------------------------------- */

    function get_address_from_store($store, $title_tag = 'h3', $raw = false, $minhtml = false) {
        $content = '';
        if (!$raw) {
            $content.= $minhtml ? '' : '<div class="store-address">';
            $content.= '<' . $title_tag . ($minhtml ? '' : ' class="store-name"') . '>';
            $content.= $store['metas']['store_name'][0];
            $content.= '</' . $title_tag . '>';
        }

        if (!empty($store['metas']['store_address'][0])) {
            $content.= $store['metas']['store_address'][0] . ($raw ? ' ' : '<br />');
        }
        if (!empty($store['metas']['store_address2'][0])) {
            $content.= $store['metas']['store_address2'][0] . ($raw ? ' ' : '<br />');
        }
        if (!empty($store['metas']['store_zip'][0]) || !empty($store['metas']['store_city'][0])) {
            $content.= $store['metas']['store_zip'][0] . ' ' . $store['metas']['store_city'][0] . ($raw ? ' ' : '<br />');
        }
        if (!empty($store['metas']['store_region'][0])) {
            $content.= $store['metas']['store_region'][0] . ($raw ? ' ' : '<br />');
        }
        $country = '';
        $country_list = $this->get_countries();
        if (isset($country_list[$store['metas']['store_country'][0]])) {
            $content.= ' ' . $country_list[$store['metas']['store_country'][0]];
        }

        if (!$raw) {
            $content.= $minhtml ? '' : '</div>';
        }

        return $content;
    }

    function get_default_search_form() {
        $html = '';
        $html.= '<form id="wpustorelocator-search" action="#" method="get"><div>';
        $html.= $this->get_default_search_fields();
        $html.= '<button class="cssc-button" type="submit">' . __('Search', 'wpustorelocator') . '</button>';
        $html.= '</div></form>';
        return $html;
    }

    function get_default_search_fields() {
        $address_value = '';
        if (isset($_GET['address'])) {
            $address_value = $_GET['address'];
        }

        $search = $this->get_search_parameters($_GET);

        $return = '<input id="wpustorelocator-search-address" type="text" name="address" value="' . esc_attr($address_value) . '" />';
        $return.= '<input id="wpustorelocator-baseurl" type="hidden" value="' . apply_filters('wpustorelocator_archive_url', get_post_type_archive_link('stores')) . '" />';
        $return.= '<input id="wpustorelocator-search-lat" type="hidden" name="lat" value="' . $search['lat'] . '" />';
        $return.= '<input id="wpustorelocator-search-lng" type="hidden" name="lng" value="' . $search['lng'] . '" />';
        return $return;
    }

    function get_default_switch_country() {
        $return = '';
        $countries = $this->get_stores_countries();
        $current_country = '';
        if (!empty($countries)) {
            uasort($countries, array(&$this,
                'sort_countries'
            ));
            if (isset($_GET['country']) && array_key_exists($_GET['country'], $countries)) {
                $current_country = $_GET['country'];
            }
            $is_required_select = apply_filters('wpustorelocator_selectcountryrequired', $this->selectcountryrequired);
            $return.= '<select ' . ($is_required_select ? 'required="required"' : '') . ' id="wpustorelocator-country" name="country">';
            $return.= '<option selected disabled>' . __('Select a country', 'wpustorelocator') . ($is_required_select ? ' (' . __('required', 'wpustorelocator') . ')' : '') . '</option>';
            foreach ($countries as $id => $country) {
                $return.= '<option ' . ($current_country == $id ? 'selected="selected"' : '') . ' value="' . $id . '" data-latlng="' . $country['lat'] . '|' . $country['lng'] . '|' . $country['zoom'] . '">' . $country['name'] . '</option>';
            }
            $return.= '</select>';
        }
        return $return;
    }

    function get_default_extend_radius($type = 'link') {
        $return = '';

        $radius_list = $this->get_radius();

        $current_radius = $radius_list[0];
        if (isset($_GET['radius']) && in_array($_GET['radius'], $radius_list)) {
            $current_radius = $_GET['radius'];
        }

        $search = $this->get_search_parameters($_GET);

        $radius_values = array();
        $current_country = $this->get_current_country_code();
        $use_miles = false;
        switch ($current_country) {
            case 'US':
            case 'GB':
                $use_miles = true;
            break;
        }

        foreach ($radius_list as $radius) {
            if ($radius <= $current_radius) {
                continue;
            }
            $url = add_query_arg(array(
                'lat' => $search['lat'],
                'lng' => $search['lng'],
                'radius' => $radius,
            ) , $this->options['archive_url']);

            $radius_name = sprintf(__('Extend search to %s km', 'wpustorelocator') , $radius);
            if ($use_miles) {
                $radius_name = sprintf(__('Extend search to %s miles', 'wpustorelocator') , round($radius * 0.621371192));
            }

            $radius_values[] = array(
                'value' => $radius,
                'url' => $url,
                'name' => $radius_name
            );
        }
        if (!empty($radius_values)) {
            switch ($type) {
                case 'select':
                    $return.= '<select name="" onchange="window.location.href=this.value;return false;">';
                    $return.= '<option selected disabled>' . __('Extend search to ...', 'wpustorelocator') . '</option>';
                    foreach ($radius_values as $rad) {
                        $return.= '<option value="' . $rad['url'] . '">' . $rad['name'] . '</option>';
                    }
                    $return.= '</select>';
                break;
                default:
                    $return.= '<ul>';
                    foreach ($radius_values as $rad) {
                        if ($rad['value'] <= $current_radius) {
                            continue;
                        }
                        $return.= '<li><a href="' . $rad['url'] . '">' . $rad['name'] . '</a></li>';
                    }
                    $return.= '</ul>';
            }
        }

        return $return;
    }

    function sort_countries_light($a, $b) {
        $aname = $this->clean_name($a);
        $bname = $this->clean_name($b);
        return strcoll($aname, $bname);
    }

    function sort_countries($a, $b) {
        $aname = $this->clean_name($a['name']);
        $bname = $this->clean_name($b['name']);
        return strcoll($aname, $bname);
    }

    function clean_name($str) {
        $str = htmlentities($str, ENT_NOQUOTES, 'utf-8');
        $str = preg_replace('#&([A-za-z])(?:uml|circ|tilde|acute|grave|cedil|ring);#', '\1', $str);
        $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str);
        $str = preg_replace('#&[^;]+;#', '', $str);
        $str = strtolower($str);
        return $str;
    }

    /* ----------------------------------------------------------
      Admin
    ---------------------------------------------------------- */

    function adding_custom_meta_boxes($post) {
        add_meta_box('geocoding-metabox', __('Geocoding') , array(&$this,
            'render_box_geocoding'
        ) , 'stores');
    }

    function render_box_geocoding() {
        echo '<p><label for="wpustorelocator-admingeocoding-content">' . __('Please load the address below and click on a suggested result to update GPS Coordinates', 'wpustorelocator') . '</label></p>';
        echo '<p><input id="wpustorelocator-admingeocoding-content" type="text" name="_geocoding" class="widefat" value="" /></p>';
        echo '<p><button type="button" id="wpustorelocator-admingeocoding-button">' . __('Load address', 'wpustorelocator') . '</button></p>';
    }

    /* Launch the cron in a loop */
    function admin_visual_cron() {
        if (!isset($_GET['wpustorelocator-visualcron']) || !current_user_can('create_users')) {
            return;
        }
        $this->cron(1);
        echo $this->admin_count_stores();
        echo '<script>setTimeout(function(){window.location=window.location.href;},1000);</script>';
        die;
    }

    /* Reload cached coords */
    function admin_reload_dbcache() {
        if (!isset($_GET['wpustorelocator-reloaddbcache']) || !current_user_can('create_users')) {
            return;
        }

        global $wpdb;

        // Delete all cached coords
        $wpdb->query("DELETE FROM $this->table_name");

        $content = '';
        $args = $this->get_query_for_defaultcoord(-1, '0');
        $wpq_stores = new WP_Query($args);
        if ($wpq_stores->have_posts()) {
            $content.= '<ul>';
            while ($wpq_stores->have_posts()) {
                $wpq_stores->the_post();
                $post_id = get_the_ID();
                $store_lat = get_post_meta($post_id, 'store_lat', 1);
                $store_lng = get_post_meta($post_id, 'store_lng', 1);
                $store_country = get_post_meta($post_id, 'store_country', 1);
                $this->save_store_coord($post_id, $store_lat, $store_lng, $store_country, true);

                $content.= '<li>';
                $content.= get_the_title();
                $content.= '</li>';
            }
            $content.= '</ul>';
        }
        wp_reset_postdata();

        // Display success
        ob_start("ob_gzhandler");
        echo $content;
        die;
    }

    function admin_count_stores() {
        $count_stores = wp_count_posts('stores');
        $wpq_stores_nb = new WP_Query($this->get_query_for_defaultcoord(1));

        $return = '<p>' . __('Stores with default localization:', 'wpustorelocator') . ' ' . $wpq_stores_nb->found_posts . ' / ' . $count_stores->publish . '</p>';
        wp_reset_postdata();

        return $return;
    }

    function admin_options() {

        echo '<h3>' . __('Infos', 'wpustorelocator') . '</h3>';

        echo $this->admin_count_stores();

        echo '<hr />';

        echo '<h3>' . __('Geocoding', 'wpustorelocator') . '</h3>';
        echo submit_button(__('Launch geocoding', 'wpustorelocator') , 'primary', 'launch_cron');

        echo '<hr />';

        echo '<h3>' . __('Import', 'wpustorelocator') . '</h3>';

        // Download model
        echo '<p><label><strong>' . __('Store list file (.csv)', 'wpustorelocator') . '</strong></label><br /><input type="file" name="file" value="" /><small><a href="#" onclick="jQuery(\'#wpustorelocator-importdetails\').show();jQuery(this).remove();return false;"><br />' . __('Settings') . '</a></small></p>';

        echo '<div id="wpustorelocator-importdetails" style="display: none;">';

        $data_base64 = 'data:text/csv;base64,QXBwbGUgU3RvcmUgT3DDqXJhOzEyIFJ1ZSBIYWzDqXZ5Ozs3NTAwOTtQYXJpczvDjmxlLWRlLUZyYW5jZTtGcmFuY2U7MDE0NDgzNDIwMApBcHBsZSBTdG9yZSBMb3V2cmU7OTkgcnVlIGRlIFJpdm9saTs7NzUwMDE7UGFyaXM7w45sZS1kZS1GcmFuY2U7RnJhbmNlOzE1NzMyMjg4Mjk=';

        // CSV Format
        echo '<p><strong>' . __('Format:', 'wpustorelocator') . '</strong>';
        echo '<br />' . __('Store name;Address;Address #2;Zipcode;City;State/Province/County;Country;Phone', 'wpustorelocator');
        echo '<br /><a download="storelocator-example.csv" href="' . $data_base64 . '">' . __('Download a CSV model', 'wpustorelocator') . '</a>';
        echo '</p>';

        echo '<p><strong><label for="wpustorelocator-csvseparator">' . __('Select a CSV separator', 'wpustorelocator') . '</label></strong><br />';
        echo '<select id="wpustorelocator-csvseparator" name="separator">';
        echo '<option value="" disabled selected style="display:none;">' . __('Select a CSV separator', 'wpustorelocator') . '</option>';
        foreach ($this->csv_separators as $separator) {
            echo '<option value="' . $separator . '">' . $separator . '</option>';
        }
        echo '</select></p>';

        // Destroy ?
        echo '<p><label><input type="checkbox" name="replace" value="" /> ' . __('Replace the previous stores', 'wpustorelocator') . '</label></p>';

        echo '</div>';

        // upload input and destroy
        echo submit_button(__('Import', 'wpustorelocator'));
    }

    function admin_options_postAction() {

        if (isset($_POST['launch_cron'])) {
            $this->cron();
            return;
        }

        $max_store_nb = 550;

        // If file exists & valid
        if (isset($_FILES, $_FILES['file'], $_FILES['file']['tmp_name']) && !empty($_FILES['file']['tmp_name']) && stripos($_FILES['file']['type'], 'csv')) {

            // If replace
            if (isset($_POST['replace'])) {

                // Delete all
                $this->admin__delete_all_stores();
            }

            $separator = $this->csv_separators[0];
            if (isset($_POST['separator']) && in_array($_POST['separator'], $this->csv_separators)) {
                $separator = $_POST['separator'];
            }

            // Extract list
            $stores_list = $this->get_array_from_csv($_FILES['file']['tmp_name'], $separator);

            // - Insert new
            $inserted_stores = 0;
            foreach ($stores_list as $store) {
                $store_id = $this->create_store_from_array($store, false);
                if (is_numeric($store_id)) {
                    $inserted_stores++;
                }
                if ($inserted_stores >= $max_store_nb) {
                    break;
                }
            }
            if ($inserted_stores > 0) {
                $success_msg = ($inserted_stores > 1) ? sprintf(__('%s stores have been imported.', 'wpustorelocator') , $inserted_stores) : __('1 store has been imported.', 'wpustorelocator');
                $this->set_message('import_ok', $success_msg, 'updated');
            }
        }
    }

    function get_array_from_csv($csv_file, $separator = ';') {
        $list = array();
        $row = 1;
        if (($handle = fopen($csv_file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, $separator)) !== FALSE) {
                if ($data[1] != 'Address') {
                    $list[] = $data;
                }
            }
            fclose($handle);
        }
        return $list;
    }

    function admin__delete_all_stores() {

        // Execute the query
        $location_query = get_posts(array(
            'post_type' => 'stores',
            'post_status' => 'any',
            'posts_per_page' => - 1,
            'suppress_filters' => true
        ));

        // Delete stores
        foreach ($location_query as $store) {
            wp_delete_post($store->ID, 1);
        }

        global $wpdb;
        $wpdb->query("DELETE FROM $this->table_name");
    }

    /* Admin list
     -------------------------- */

    function search_fields_admin($query) {
        if (!is_admin() || !$query->is_search) {
            return;
        }
        $screen = get_current_screen();
        if (!is_object($screen) || $screen->id != 'edit-stores') {
            return;
        }

        $custom_fields = array(
            "store_name",
            "store_address",
            "store_address2",
            "store_city"
        );
        $searchterm = $query->query_vars['s'];
        $query->query_vars['s'] = "";
        if ($searchterm != "") {
            $meta_query = array(
                'relation' => 'OR'
            );
            foreach ($custom_fields as $cf) {
                array_push($meta_query, array(
                    'key' => $cf,
                    'value' => $searchterm,
                    'compare' => 'LIKE'
                ));
            }
            $query->set("meta_query", $meta_query);
        }
    }

    function filter_storelist_by_country() {
        $screen = get_current_screen();
        if ($screen->post_type == 'stores') {
            $countries = $this->get_stores_countries();
            uasort($countries, array(&$this,
                'sort_countries'
            ));
            printf('<select name="%s" class="postform">', 'store_country');
            printf('<option value="0" selected>%s</option>', __('All countries', 'wpustorelocator'));
            foreach ($countries as $id => $country) {
                if (isset($_GET["store_country"]) && $_GET["store_country"] == $id) {
                    printf('<option value="%s" selected>%s</option>', $id, $country['name']);
                }
                else {
                    printf('<option value="%s">%s</option>', $id, $country['name']);
                }
            }
            print ('</select>');
        }
    }

    function filter_storelist_by_country_query($query) {
        $screen = get_current_screen();
        if ($screen->post_type == 'stores') {
            $countries = $this->get_stores_countries();
            if (isset($_GET['store_country']) && array_key_exists($_GET['store_country'], $countries)) {
                $meta_query[] = array(
                    'key' => 'store_country',
                    'value' => $_GET['store_country'],
                    'compare' => '=',
                );
                $query->set('meta_query', $meta_query);
            }
        }
    }

    /* ----------------------------------------------------------
      Search
    ---------------------------------------------------------- */

    function get_search_parameters($src) {
        $lat = 0;
        $country_list = $this->get_countries();

        if (isset($src['lat']) && preg_match('/([0-9\.])/', $src['lat'])) {
            $lat = $src['lat'];
        }

        $lng = 0;
        if (isset($src['lng']) && preg_match('/([0-9\.])/', $src['lng'])) {
            $lng = $src['lng'];
        }

        $country = 0;
        if (isset($src['country']) && preg_match('/([A-Z]{2})/', $src['country'])) {
            $country = $src['country'];
        }

        if (isset($src['address']) && $lat == 0 && $lng == 0 && !empty($src['address'])) {
            if ($country !== 0 && isset($country_list[$country])) {
                $src['address'].= ' ' . $country_list[$country];
            }
            $details = $this->geocode_address($src['address']);
            $lat = $details['lat'];
            $lng = $details['lng'];
        }

        $radius_list = $this->get_radius();
        $radius = $radius_list[0];
        if (isset($src['radius']) && in_array($src['radius'], $radius_list)) {
            $radius = $src['radius'];
        }

        return array(
            'lat' => $lat,
            'lng' => $lng,
            'country' => $country,
            'radius' => $radius
        );
    }

    function location_posts_where($where) {

        global $wpdb;

        if ($this->tmp_lat != 0 || $this->tmp_lng != 0) {

            // Append our radius calculation to the WHERE
            $where.= $wpdb->prepare(" AND $wpdb->posts.ID IN (SELECT post_id FROM " . $this->table_name . " WHERE
            ( 3959 * acos( cos( radians(%s) )
            * cos( radians( lat ) )
            * cos( radians( lng )
            - radians(%s) )
            + sin( radians(%s) )
            * sin( radians( lat ) ) ) ) <= %s)", $this->tmp_lat, $this->tmp_lng, $this->tmp_lat, $this->tmp_radius);
        }

        // Return the updated WHERE part of the query
        return $where;
    }

    function location_posts_country($query) {

        $limitsearchincountry = apply_filters('wpustorelocator_limitsearchincountry', $this->limitsearchincountry);

        if ((($this->tmp_lat == 0 && $this->tmp_lng == 0) || $limitsearchincountry) && !empty($this->tmp_country)) {
            $query->set("meta_query", array(
                array(
                    'key' => 'store_country',
                    'value' => $this->tmp_country,
                    'compare' => '='
                )
            ));
        }
    }

    /* ----------------------------------------------------------
      Geocoding
    ---------------------------------------------------------- */

    function geocode_post($post_id, $debug = false) {

        $default_details = array(
            'lat' => 0,
            'lng' => 0
        );

        $country = get_post_meta($post_id, 'store_country', 1);
        $country_code = $country;
        $country_list = $this->get_countries();
        if (isset($country_list[$country])) {
            $country = $country_list[$country];
        }

        $address = get_post_meta($post_id, 'store_address', 1) . ', ' . get_post_meta($post_id, 'store_zip', 1) . ' ' . get_post_meta($post_id, 'store_city', 1) . ', ' . $country;

        $details = $this->geocode_address($address, $debug);

        if ($details != $default_details) {
            update_post_meta($post_id, 'store_defaultlat', '0');
            update_post_meta($post_id, 'store_lat', $details['lat']);
            update_post_meta($post_id, 'store_lng', $details['lng']);
            $this->save_store_coord($post_id, $details['lat'], $details['lng'], $country_code);
            return true;
        }
        return false;
    }

    function geocode_address($address, $debug = false) {
        $get = wp_remote_get('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $this->serverapi_key);
        if (is_wp_error($get)) {
            return array(
                'lat' => 0,
                'lng' => 0
            );
        }
        $geoloc = json_decode($get['body']);
        $lat = 0;
        $lng = 0;
        if (is_object($geoloc)) {
            if ($geoloc->status === 'OK') {
                $details = $geoloc->results[0]->geometry->location;
                $lat = $details->lat;
                $lng = $details->lng;
            }
            else {
                if ($debug) {
                    echo '<pre>';
                    var_dump($address);
                    var_dump($geoloc);
                    echo '</pre>';
                }
            }
        }
        return array(
            'lat' => $lat,
            'lng' => $lng
        );
    }

    /* ----------------------------------------------------------
      Datas
    ---------------------------------------------------------- */

    function create_store_from_array($store, $cache = true) {
        $countries = $this->get_countries(true);
        $store_lat = '';
        $store_lng = '';
        $country_code = '';

        // Manually fix some countries
        if ($store[6] == 'Etats-Unis') {
            $store[6] = 'États-Unis';
        }
        if ($store[6] == 'Grande Bretagne') {
            $store[6] = 'Royaume-Uni';
        }

        // Test different versions
        $country_names_to_test = array(
            $store[6],
            $this->wd_remove_accents($store[6]) ,
        );

        // Set country settings if name recognized

        foreach ($countries as $id => $country) {
            if (in_array($country['name'], $country_names_to_test)) {
                $country_code = $id;
                $store_lat = $country['lat'];
                $store_lng = $country['lng'];
            }
        }

        // Create post
        $store_id = wp_insert_post(array(
            'post_title' => $store[0],
            'post_status' => 'publish',
            'post_type' => 'stores',
        ));

        if (is_numeric($store_id)) {
            update_post_meta($store_id, 'store_name', $store[0]);
            update_post_meta($store_id, 'store_address', $store[1]);
            update_post_meta($store_id, 'store_address2', $store[2]);
            update_post_meta($store_id, 'store_zip', $store[3]);
            update_post_meta($store_id, 'store_city', $store[4]);
            update_post_meta($store_id, 'store_region', $store[5]);
            update_post_meta($store_id, 'store_country', $country_code);
            update_post_meta($store_id, 'store_phone', $store[7]);
            update_post_meta($store_id, 'store_defaultlat', '1');
            update_post_meta($store_id, 'store_defaultlatlng', $store_lat . '|' . $store_lng);
            update_post_meta($store_id, 'store_lat', $store_lat);
            update_post_meta($store_id, 'store_lng', $store_lng);
            if ($cache) {
                $this->save_store_coord($store_id, $store_lat, $store_lng, $country_code);
            }

            return $store_id;
        }

        return false;
    }

    /* Save lat lng */
    function save_latlng($post_id) {
        if (!isset($_POST['post_type'], $_POST['store_lat'], $_POST['store_lng']) || $_POST['post_type'] != 'stores') {
            return;
        }

        $latlng = $_POST['store_lat'] . '|' . $_POST['store_lng'];
        $defaultlatlng = get_post_meta($post_id, 'store_defaultlatlng', 1);

        // Non default lat : mark as non default (stop geoloc via cron)
        if ($defaultlatlng != $latlng) {
            update_post_meta($post_id, 'store_defaultlat', '0');
        }
        $this->save_store_coord($post_id, $_POST['store_lat'], $_POST['store_lng'], $_POST['store_country']);
    }

    function save_store_coord($post_id, $store_lat, $store_lng, $store_country, $force_insert = false) {
        global $wpdb;
        $check_link = null;

        if (!$force_insert) {
            $check_link = $wpdb->get_row("SELECT * FROM " . $this->table_name . " WHERE post_id = '" . $post_id . "'");
        }

        if ($check_link != null) {

            // We already have a lat lng for this post. Update row
            $wpdb->update($this->table_name, array(
                'lat' => $store_lat,
                'lng' => $store_lng,
                'country' => $store_country
            ) , array(
                'post_id' => $post_id
            ) , array(
                '%f',
                '%f',
                '%s'
            ));
        }
        else {

            // We do not already have a lat lng for this post. Insert row
            $wpdb->insert($this->table_name, array(
                'post_id' => $post_id,
                'lat' => $store_lat,
                'lng' => $store_lng,
                'country' => $store_country,
            ) , array(
                '%d',
                '%f',
                '%f',
                '%s',
            ));
        }
    }

    /* Create table */
    function table_creation() {
        if (!is_admin()) {
            return;
        }
        $script_version = get_option('wpustorelocator_scriptversion');
        if ($script_version == $this->script_version) {
            return;
        }
        $sql = "CREATE TABLE " . $this->table_name . " (
  post_id bigint(20) unsigned NOT NULL,
  lat float NOT NULL,
  lng float NOT NULL,
  country varchar(50) DEFAULT ''
);";

        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option('wpustorelocator_scriptversion', $this->script_version);
    }

    /* ----------------------------------------------------------
      Cron
    ---------------------------------------------------------- */

    function get_query_for_defaultcoord($nb_posts = 10, $default = '1') {
        return array(
            'posts_per_page' => $nb_posts,
            'post_type' => 'stores',
            'meta_query' => array(
                array(
                    'key' => 'store_defaultlat',
                    'value' => $default,
                    'compare' => '=',
                ) ,
            ) ,
        );
    }

    function set_cron() {
        wp_schedule_event(time() , 'minutes_10', 'wpustorelocator_cron_event_hook');
    }

    // do something every 10 minutes
    function cron($debug = false) {
        $is_free_api = true;

        $nb_posts = 15;
        if (!$is_free_api) {
            $nb_posts = 50;
        }

        // Select $nb_posts posts with default geoloc
        $args = $this->get_query_for_defaultcoord($nb_posts);
        $wpq_stores = new WP_Query($args);

        // Geoloc each post
        if ($wpq_stores->have_posts()) {
            while ($wpq_stores->have_posts()) {
                $wpq_stores->the_post();
                $this->geocode_post(get_the_ID() , $debug);
                if ($is_free_api) {
                    usleep(300000);
                }
                else {
                    usleep(150000);
                }
            }
        }
        wp_reset_postdata();
    }

    function unset_cron() {
        wp_clear_scheduled_hook('wpustorelocator_cron_event_hook');
    }

    // add once 2 minute interval to wp schedules
    public function add_scheduled_interval($schedules) {

        $schedules['minutes_10'] = array(
            'interval' => 600,
            'display' => __('Once 10 minutes', 'wpustorelocator')
        );

        return $schedules;
    }

    /* ----------------------------------------------------------
      Set messages
    ---------------------------------------------------------- */

    /* Set notices messages */
    private function set_message($id, $message, $group = '') {
        $messages = (array)get_transient($this->transient_msg);
        if (!in_array($group, $this->notices_categories)) {
            $group = $this->notices_categories[0];
        }
        $messages[$group][$id] = $message;
        set_transient($this->transient_msg, $messages);
    }

    /* Display notices */
    function admin_notices() {
        $messages = (array)get_transient($this->transient_msg);
        if (!empty($messages)) {
            foreach ($messages as $group_id => $group) {
                if (is_array($group)) {
                    foreach ($group as $message) {
                        echo '<div class="' . $group_id . '"><p>' . $message . '</p></div>';
                    }
                }
            }
        }

        // Empty messages
        delete_transient($this->transient_msg);
    }

    /* ----------------------------------------------------------
      Utilities
    ---------------------------------------------------------- */

    /* Thx http://www.weirdog.com/blog/php/supprimer-les-accents-des-caracteres-accentues.html */
    function wd_remove_accents($str, $charset = 'utf-8') {
        $str = htmlentities($str, ENT_NOQUOTES, $charset);

        $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
        $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str);
        $str = preg_replace('#&[^;]+;#', '', $str);

        return $str;
    }
}

$WPUStoreLocator = new WPUStoreLocator();

register_activation_hook(__FILE__, array(&$WPUStoreLocator,
    'table_creation'
));
register_activation_hook(__FILE__, array(&$WPUStoreLocator,
    'set_cron'
));
register_deactivation_hook(__FILE__, array(&$WPUStoreLocator,
    'unset_cron'
));

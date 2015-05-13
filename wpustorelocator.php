<?php

/*
Plugin Name: WPU Store locator
Description: Manage stores localizations
Version: 0.7
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Thanks to : http://biostall.com/performing-a-radial-search-with-wp_query-in-wordpress
*/

class WPUStoreLocator {
    private $script_version = '0.7';

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

        $pages = array(
            'admin' => array(
                'name' => 'Store options',
                'function_content' => array(&$this,
                    'admin_options'
                ) ,
                'function_action' => array(&$this,
                    'admin_options_postAction'
                ) ,
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
        ));
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
        $prevent_single = apply_filters('wpustorelocator_preventsingle', false);
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
        if ($this->is_storelocator_front() || is_singular('stores')) {
            wp_enqueue_script('wpustorelocator-front', plugins_url('/assets/front.js', __FILE__) , array(
                'jquery'
            ) , $this->script_version, true);
        }
        if (is_admin()) {
            wp_enqueue_script('wpustorelocator-back', plugins_url('/assets/back.js', __FILE__) , array(
                'jquery'
            ) , $this->script_version, true);
        }
        $lang = explode('_', get_locale());
        $mainlang = '';
        if (isset($lang[0])) {
            $mainlang = $lang[0];
        }
        wp_enqueue_script('wpustorelocator-maps', 'http://maps.googleapis.com/maps/api/js?v=3.exp&libraries=places&key=' . $this->frontapi_key . '&language=' . $mainlang . '&sensor=false', false, '3');
    }

    /* ----------------------------------------------------------
      Register post type
    ---------------------------------------------------------- */

    function set_theme_posttypes($post_types) {
        $post_types['stores'] = array(
            'menu_icon' => 'dashicons-location-alt',
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
        );
        $fields['store_region'] = array(
            'box' => 'stores_localization',
            'name' => __('Region', 'wpustorelocator') ,
        );
        $fields['store_country'] = array(
            'box' => 'stores_localization',
            'name' => __('Country', 'wpustorelocator') ,
            'type' => 'select',
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
            'posts_per_page' => 100,
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

        $stores = $this->get_stores_list();

        // Remove the filter
        remove_filter('posts_where', array(&$this,
            'location_posts_where'
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
        $countries = array();
        $country_list = $this->get_countries(true);
        $store_list = $this->get_stores_list();
        foreach ($store_list as $store) {
            if (isset($store['metas']['store_country']) && array_key_exists($store['metas']['store_country'][0], $country_list)) {
                $countries[$store['metas']['store_country'][0]] = $country_list[$store['metas']['store_country'][0]];
            }
        }
        return $countries;
    }

    /* ----------------------------------------------------------
      Map datas
    ---------------------------------------------------------- */

    function get_result_map_position($search) {
        $datas = array();
        $country_list = $this->get_countries(true);

        // Country mode
        if ($search['lat'] == 0 && $search['lng'] == 0 && isset($search['country']) && !empty($search['country']) && isset($country_list[$search['country']])) {
            $country = $country_list[$search['country']];
            $datas['lat'] = $country['lat'];
            $datas['lng'] = $country['lng'];
            $datas['zoom'] = $country['zoom'];
        }

        // Coordinate mode
        else {
            $datas = $search;
        }
        $html = '';
        foreach ($datas as $id => $name) {
            $html.= ' data-' . $id . '="' . esc_attr($name) . '" ';
        }

        return $html;
    }

    function get_json_from_storelist($stores) {
        $prevent_single = apply_filters('wpustorelocator_preventsingle', false);
        $datas = array();
        foreach ($stores as $store) {
            $itinerary_link = 'https://maps.google.com/?q=' . urlencode($this->get_address_from_store($store, '', 1));

            $data = array(
                'name' => $store['post']->post_title,
                'lat' => $store['metas']['store_lat'][0],
                'lng' => $store['metas']['store_lng'][0],
                'address' => $this->get_address_from_store($store) ,
                'link' => '<a class="store-link" href="' . get_permalink($store['post']->ID) . '">' . __('View this store', 'wpustorelocator') . '</a>',
                'itinerary' => '<a target="_blank" class="store-itinerary" href="' . $itinerary_link . '">' . __('Go to this store', 'wpustorelocator') . '</a>',
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

    function get_address_from_store($store, $title_tag = 'h3', $raw = false) {
        $content = '';
        if (!$raw) {
            $content.= '<div class="store-address">';
            $content.= '<' . $title_tag . ' class="store-name">';
            $content.= $store['metas']['store_name'][0];
            $content.= '</' . $title_tag . '>';
        }

        $content.= $store['metas']['store_address'][0] . ($raw ? ' ' : '<br />');
        if (!empty($store['metas']['store_address2'][0])) {
            $content.= $store['metas']['store_address2'][0] . ($raw ? ' ' : '<br />');
        }
        $content.= $store['metas']['store_zip'][0] . ' ' . $store['metas']['store_city'][0] . ($raw ? ' ' : '<br />');

        $country = '';
        $country_list = $this->get_countries();
        if (isset($country_list[$store['metas']['store_country'][0]])) {
            $content.= ' ' . $country_list[$store['metas']['store_country'][0]];
        }

        if (!$raw) {
            $content.= '</div>';
        }

        return $content;
    }

    function get_default_search_form() {
        $html = '';
        $html.= '<form id="wpustorelocator-search" action="#" method="get"><div>';
        $html.= $this->get_default_search_fields();
        $html.= '<button class="cssc-button" type="submit">Search</button>';
        $html.= '</div></form>';
        return $html;
    }

    function get_default_search_fields() {
        $address_value = '';
        if (isset($_GET['address'])) {
            $address_value = $_GET['address'];
        }
        $return = '<input id="wpustorelocator-search-address" type="text" name="address" value="' . esc_attr($address_value) . '" />';
        $return.= '<input id="wpustorelocator-search-lat" type="hidden" name="lat" value="" />';
        $return.= '<input id="wpustorelocator-search-lng" type="hidden" name="lng" value="" />';
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
            $return.= '<select id="wpustorelocator-country" name="country">';
            $return.= '<option selected disabled>' . __('Select a country', 'wpustorelocator') . '</option>';
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

        foreach ($radius_list as $radius) {
            if ($radius <= $current_radius) {
                continue;
            }
            $url = add_query_arg(array(
                'lat' => $search['lat'],
                'lng' => $search['lng'],
                'radius' => $radius,
            ) , $this->options['archive_url']);
            $radius_values[] = array(
                'value' => $radius,
                'url' => $url,
                'name' => sprintf(__('Extend search to %s km', 'wpustorelocator') , $radius)
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

    function admin_options() {

        // Download model
        echo '<p><label><strong>' . __('Store list file (.csv)', 'wpustorelocator') . '</strong></label><br /><input type="file" name="file" value="" /><br /><small><strong>Format :</strong> Store name;Address;Address #2;Zipcode;City;Etat/Province/Comté;Country;Telephone</small></p>';

        // Destroy ?
        echo '<p><label><input checked="checked" type="checkbox" name="replace" value="" /> ' . __('Replace the previous stores', 'wpustorelocator') . '</label></p>';

        // Geoloc some posts
        echo '<p><label><input type="checkbox" name="geoloc" value="" /> ' . __('Geoloc stores', 'wpustorelocator') . '</label></p>';

        // upload input and destroy
        echo submit_button('Import');
    }

    function admin_options_postAction() {

        $max_store_nb = 500;

        // If file exists & valid
        if (isset($_FILES, $_FILES['file'], $_FILES['file']['tmp_name']) && !empty($_FILES['file']['tmp_name']) && stripos($_FILES['file']['type'], 'csv')) {

            // If replace
            if (isset($_POST['replace'])) {

                // Delete all
                $this->admin__delete_all_stores();
            }

            // Extract list
            $stores_list = $this->get_array_from_csv($_FILES['file']['tmp_name']);

            // - Insert new
            $inserted_stores = 0;
            foreach ($stores_list as $store) {
                $store_id = $this->create_store_from_array($store);
                if (is_numeric($store_id)) {
                    $inserted_stores++;
                }
                if ($inserted_stores >= $max_store_nb) {
                    break;
                }
            }
            if ($inserted_stores > 0) {
                $this->set_message('import_ok', sprintf(_n('1 store has been imported.', '%s stores has been imported.', $inserted_stores, 'wpustorelocator') , $inserted_stores) , 'updated');
            }
        }
    }

    function get_array_from_csv($csv_file) {
        $list = array();
        $row = 1;
        if (($handle = fopen($csv_file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
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
    }

    /* ----------------------------------------------------------
      Search
    ---------------------------------------------------------- */

    function get_search_parameters($src) {
        $lat = 0;
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

        if ($this->tmp_lat == 0 && $this->tmp_lng == 0) {
            $where.= $wpdb->prepare(" AND $wpdb->posts.ID IN (SELECT post_id FROM " . $this->table_name . " WHERE country='%s')", $this->tmp_country);
        }
        else {

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

    /* ----------------------------------------------------------
      Geocoding
    ---------------------------------------------------------- */

    function geocode_post($post_id) {

        $default_details = array(
            'lat' => 0,
            'lng' => 0
        );

        $country = get_post_meta($post_id, 'store_country', 1);
        $country_list = $this->get_countries();
        if (isset($country_list[$country])) {
            $country = $country_list[$country];
        }

        $address = get_post_meta($post_id, 'store_address', 1) . ', ' . get_post_meta($post_id, 'store_zip', 1) . ' ' . get_post_meta($post_id, 'store_city', 1) . ', ' . $country;

        $details = $this->geocode_address($address);

        if ($details != $default_details) {
            update_post_meta($post_id, 'store_defaultlat', '0');
            update_post_meta($post_id, 'store_lat', $details['lat']);
            update_post_meta($post_id, 'store_lng', $details['lng']);
            return true;
        }
        return false;
    }

    function geocode_address($address) {
        $get = wp_remote_get('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $this->serverapi_key);
        $geoloc = json_decode($get['body']);
        $lat = 0;
        $lng = 0;
        if (is_object($geoloc) && $geoloc->status === 'OK') {
            $details = $geoloc->results[0]->geometry->location;
            $lat = $details->lat;
            $lng = $details->lng;
        }
        return array(
            'lat' => $lat,
            'lng' => $lng
        );
    }

    /* ----------------------------------------------------------
      Datas
    ---------------------------------------------------------- */

    function create_store_from_array($store) {

        $countries = $this->get_countries(1);
        $store_lat = '';
        $store_lng = '';
        $country_code = '';

        // Set country settings if name recognized
        foreach ($countries as $id => $country) {
            if ($country['name'] == $store[6]) {
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
            update_post_meta($store_id, 'store_lat', $store_lat);
            update_post_meta($store_id, 'store_lng', $store_lng);

            return $store_id;
        }

        return false;
    }

    /* Save lat lng */
    function save_latlng($post_id) {
        global $wpdb;
        if (!isset($_POST['post_type']) || $_POST['post_type'] != 'stores') {
            return;
        }

        $check_link = $wpdb->get_row("SELECT * FROM " . $this->table_name . " WHERE post_id = '" . $post_id . "'");
        if ($check_link != null) {

            // We already have a lat lng for this post. Update row
            $wpdb->update($this->table_name, array(
                'lat' => $_POST['store_lat'],
                'lng' => $_POST['store_lng'],
                'country' => $_POST['store_country']
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
                'lat' => $_POST['store_lat'],
                'lng' => $_POST['store_lng'],
                'country' => $_POST['store_country'],
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
}

$WPUStoreLocator = new WPUStoreLocator();

register_activation_hook(__FILE__, array(&$WPUStoreLocator,
    'table_creation'
));


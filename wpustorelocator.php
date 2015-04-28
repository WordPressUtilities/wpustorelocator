<?php

/*
Plugin Name: WPU Store locator
Description: Manage stores localizations
Version: 0.5.3.1
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Thanks to : http://biostall.com/performing-a-radial-search-with-wp_query-in-wordpress
*/

class WPUStoreLocator {
    private $script_version = '0.5.3.1';
    function __construct() {

        global $wpdb;
        $this->options = array(
            'id' => 'wpustorelocator',
            'level' => 'manage_options',
        );
        $this->table_name = $wpdb->prefix . 'wpustorelocatorlist';
        $this->serverapi_key = get_option('wpustorelocator_serverapikey');
        $this->frontapi_key = get_option('wpustorelocator_frontapikey');

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
        add_action('template_redirect', array(&$this,
            'prevent_single'
        ));
    }

    function prevent_single() {
        $prevent_single = apply_filters('wpustorelocator_preventsingle', false);
        if (is_singular('stores') && $prevent_single) {
            wp_redirect(get_post_type_archive_link('stores'));
            die;
        }
    }

    function display_infos() {
        if (!is_post_type_archive('stores') && !is_singular('stores')) {
            return;
        }
        $this->infos = array(
            'icon' => apply_filters('wpustorelocator_iconurl', '') ,
            'base_url' => get_post_type_archive_link('stores') ,
        );

        echo '<script>window.wpustorelocatorconf=' . json_encode($this->infos) . ';</script>';
    }

    function load_languages() {
        load_plugin_textdomain('wpustorelocator', false, dirname(plugin_basename(__FILE__)) . '/lang');
    }

    function enqueue_scripts() {
        if (is_post_type_archive('stores') || is_singular('stores')) {
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

        $fields['store_openingtime'] = array(
            'box' => 'stores_details',
            'name' => __('Opening time', 'wpustorelocator') ,
            'type' => 'textarea'
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
            'suppress_filters' => true
        ));

        // Sort stores
        $stores = array();
        foreach ($location_query as $store) {
            $stores[$store->ID] = $this->get_store_postinfos($store);
        }

        return $stores;
    }

    function get_stores($lat = 0, $lng = 0, $radius = 10) {

        $this->tmp_lat = $lat;
        $this->tmp_lng = $lng;
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
        $csv = array_map('str_getcsv', file(dirname(__FILE__) . '/inc/countries.csv'));
        $locale = get_locale();
        $countries = array();
        foreach ($csv as $country) {
            $value = $country[3];
            if ($full) {
                $value = array(
                    'lat' => $country[1],
                    'lng' => $country[2],
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

    function get_json_from_storelist($stores) {
        $prevent_single = apply_filters('wpustorelocator_preventsingle', false);
        $datas = array();
        foreach ($stores as $store) {

            $data = array(
                'name' => $store['post']->post_title,
                'lat' => $store['metas']['store_lat'][0],
                'lng' => $store['metas']['store_lng'][0],
                'address' => $this->get_address_from_store($store) ,
                'link' => '<a class="store-link" href="' . get_permalink($store['post']->ID) . '">' . __('View this store', 'wpustorelocator') . '</a>',
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

    function get_address_from_store($store, $title_tag = 'h3') {
        $content = '<div class="store-address">';
        $content.= '<' . $title_tag . ' class="store-name">' . $store['metas']['store_name'][0] . '</' . $title_tag . '>';
        $content.= $store['metas']['store_address'][0] . '<br />';
        if (!empty($store['metas']['store_address2'][0])) {
            $content.= $store['metas']['store_address2'][0] . '<br />';
        }
        $content.= $store['metas']['store_zip'][0] . ' ' . $store['metas']['store_city'][0] . '<br />';

        $country = '';
        $country_list = $this->get_countries();
        if (isset($country_list[$store['metas']['store_country'][0]])) {
            $content.= $country_list[$store['metas']['store_country'][0]];
        }

        $content.= '</div>';

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
        if (!empty($countries)) {
            $return.= '<select id="wpustorelocator-country" name="country">';
            $return.= '<option selected disabled>' . __('Select a country', 'wpustorelocator') . '</option>';
            foreach ($countries as $country) {
                $return.= '<option value="' . $country['lat'] . '|' . $country['lng'] . '">' . $country['name'] . '</option>';
            }
            $return.= '</select>';
        }
        return $return;
    }

    function get_default_extend_radius($type = 'link') {
        $return = '';

        $radius_list = $this->get_radius();
        unset($radius_list[0]);

        $current_radius = 0;
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
            ) , get_post_type_archive_link('stores'));
            $radius_values[] = array(
                'url' => $url,
                'name' => sprintf(__('Extend search to %s km', 'wpustorelocator') , $radius)
            );
        }
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
                    $return.= '<li><a href="' . $rad['url'] . '">' . $rad['name'] . '</a></li>';
                }
                $return.= '</ul>';
        }

        return $return;
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
        echo '<p><label for="wpustorelocator-admingeocoding-content">' . __('Please type and select the address below and validate by pressing the Enter button to update GPS Coordinates', 'wpustorelocator') . '</label></p>';
        echo '<p><input id="wpustorelocator-admingeocoding-content" type="text" name="_geocoding" class="widefat" value="" /></p>';
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
            'radius' => $radius
        );
    }

    function location_posts_where($where) {

        global $wpdb;

        // Append our radius calculation to the WHERE
        $where.= $wpdb->prepare(" AND $wpdb->posts.ID IN (SELECT post_id FROM " . $this->table_name . " WHERE
            ( 3959 * acos( cos( radians(%s) )
            * cos( radians( lat ) )
            * cos( radians( lng )
            - radians(%s) )
            + sin( radians(%s) )
            * sin( radians( lat ) ) ) ) <= %s)", $this->tmp_lat, $this->tmp_lng, $this->tmp_lat, $this->tmp_radius);

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
                'lng' => $_POST['store_lng']
            ) , array(
                'post_id' => $post_id
            ) , array(
                '%f',
                '%f'
            ));
        }
        else {

            // We do not already have a lat lng for this post. Insert row
            $wpdb->insert($this->table_name, array(
                'post_id' => $post_id,
                'lat' => $_POST['store_lat'],
                'lng' => $_POST['store_lng']
            ) , array(
                '%d',
                '%f',
                '%f'
            ));
        }
    }

    /* Create table */
    function table_creation() {

        $sql = "CREATE TABLE IF NOT EXISTS `" . $this->table_name . "` (
  `post_id` bigint(20) unsigned NOT NULL,
  `lat` float NOT NULL,
  `lng` float NOT NULL
) ENGINE=InnoDB;  ";

        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

$WPUStoreLocator = new WPUStoreLocator();

register_activation_hook(__FILE__, array(&$WPUStoreLocator,
    'table_creation'
));


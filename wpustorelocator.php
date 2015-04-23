<?php

/*
Plugin Name: WPU Store locator
Description: Manage stores localizations
Version: 0.2
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Thanks to : http://biostall.com/performing-a-radial-search-with-wp_query-in-wordpress
*/

class WPUStoreLocator {
    function __construct() {

        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpustorelocatorlist';
        $this->api_key = get_option('wpustorelocator_serverapikey');

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
    }

    /* ----------------------------------------------------------
      Register post type
    ---------------------------------------------------------- */

    function set_theme_posttypes($post_types) {
        $post_types['stores'] = array(
            'menu_icon' => 'dashicons-location-alt',
            'name' => __('Store', 'wpustorelocator') ,
            'plural' => __('Stores', 'wpustorelocator') ,
            'female' => 0,
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
            'label' => __('Radius list', 'wputh') ,
            'box' => 'wpustorelocator_settings',
            'type' => 'textarea'
        );

        /* API */
        $options['wpustorelocator_serverapikey'] = array(
            'label' => __('Server API Key', 'wputh') ,
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
            'name' => __('Name', 'wpustorelocator') ,
            'lang' => true,
        );
        $fields['store_openingtime'] = array(
            'box' => 'stores_details',
            'name' => __('Opening time', 'wpustorelocator') ,
            'lang' => true,
        );
        $fields['store_phone'] = array(
            'box' => 'stores_details',
            'name' => __('Phone number', 'wpustorelocator') ,
            'lang' => true,
        );
        $fields['store_email'] = array(
            'box' => 'stores_details',
            'name' => __('Email address', 'wpustorelocator')
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
      Get Stores
    ---------------------------------------------------------- */

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

    function get_stores_list() {

        // Execute the query
        $location_query = get_posts(array(
            'post_type' => 'stores',
            'suppress_filters' => true
        ));

        // Sort stores
        $stores = array();
        foreach ($location_query as $store) {
            $stores[] = $this->get_store_postinfos($store);
        }

        return $stores;
    }

    function get_stores($lat = 0, $lng = 0, $radius = 10) {

        $this->tmp_lat = $lat;
        $this->tmp_lng = $lng;
        $this->tmp_radius = $radius * 1.609344;

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
        $radius = get_option('wpustorelocator_radiuslist');
        $radius_list = array();
        $raw_radius_list = explode("\n", $radius_list);
        foreach ($raw_radius_list as $radius) {
            $radius = trim($radius);
            if (is_numeric($radius)) {
                $radius_list[] = $radius;
            }
        }
        natsort($radius_list);
        return $radius_list;
    }

    /* ----------------------------------------------------------
      Geocoding
    ---------------------------------------------------------- */

    function geocode_post($post_id) {

        $default_details = array(
            'lat' => 0,
            'lng' => 0
        );

        $address = get_post_meta($post_id, 'store_address', 1) . ', ' . get_post_meta($post_id, 'store_zip', 1) . ' ' . get_post_meta($post_id, 'store_city', 1) . ', ' . get_post_meta($post_id, 'store_country', 1);

        $details = $this->geocode_address($address);

        if ($details != $default_details) {
            update_post_meta($post_id, 'store_lat', $details['lat']);
            update_post_meta($post_id, 'store_lng', $details['lng']);
            return true;
        }
        return false;
    }

    function geocode_address($address) {
        $get = wp_remote_get('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $this->api_key);
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

    #

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

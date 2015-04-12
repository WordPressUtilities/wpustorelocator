<?php

/*
Plugin Name: WPU Store locator
Description: Manage stores localizations
Version: 0.1.1
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUStoreLocator {
    function __construct() {
        add_filter('wputh_get_posttypes', array(&$this,
            'set_theme_posttypes'
        ));
        add_filter('wputh_post_metas_boxes', array(&$this,
            'post_metas_boxes'
        ) , 10, 3);
        add_filter('wputh_post_metas_fields', array(&$this,
            'post_metas_fields'
        ) , 10, 3);
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
            'name' => __('Name', 'wpustorelocator'),
            'lang' => true,
        );
        $fields['store_openingtime'] = array(
            'box' => 'stores_details',
            'name' => __('Opening time', 'wpustorelocator'),
            'lang' => true,
        );
        $fields['store_phone'] = array(
            'box' => 'stores_details',
            'name' => __('Phone number', 'wpustorelocator'),
            'lang' => true,
        );
        $fields['store_email'] = array(
            'box' => 'stores_details',
            'name' => __('Email address', 'wpustorelocator')
        );

        /* Localization */
        $fields['store_address'] = array(
            'box' => 'stores_localization',
            'name' => __('Address', 'wpustorelocator'),
            'lang' => true,
        );
        $fields['store_address2'] = array(
            'box' => 'stores_localization',
            'name' => __('Address2', 'wpustorelocator'),
            'lang' => true,
        );
        $fields['store_zip'] = array(
            'box' => 'stores_localization',
            'name' => __('Zip code', 'wpustorelocator'),
            'lang' => true,
        );
        $fields['store_city'] = array(
            'box' => 'stores_localization',
            'name' => __('City', 'wpustorelocator'),
            'lang' => true,
        );
        $fields['store_region'] = array(
            'box' => 'stores_localization',
            'name' => __('Region', 'wpustorelocator'),
            'lang' => true,
        );
        $fields['store_country'] = array(
            'box' => 'stores_localization',
            'name' => __('Country', 'wpustorelocator'),
            'lang' => true,
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
}

$WPUStoreLocator = new WPUStoreLocator();


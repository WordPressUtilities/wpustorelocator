var wpustorelocator = {},
    wpustorelocator_map = false,
    wpustorelocator_mapel = false;

google.maps.event.addDomListener(window, 'load', wpustorelocator_initialize);

function wpustorelocator_initialize() {
    wpustorelocator_mapel = jQuery('#wpustorelocator-map');
    if (wpustorelocator_mapel.length < 1) {
        return;
    }
    wpustorelocator.loadmap();
    wpustorelocator.loadsearch();
    wpustorelocator.setgeolocalize();
    wpustorelocator.countryswitch();
}

/* ----------------------------------------------------------
  Switch country
---------------------------------------------------------- */

wpustorelocator.countryswitch = function() {
    jQuery('#wpustorelocator-country').on('change', function(e) {
        var coords = jQuery(this).find(":selected").attr('data-latlng').split('|'),
            zoom = 6,
            latlng = new google.maps.LatLng(coords[0], coords[1]);
        if (coords[2]) {
            zoom = parseInt(coords[2], 10);
        }
        wpustorelocator_map.setZoom(zoom);
        wpustorelocator_map.panTo(latlng);
    });
};

/* ----------------------------------------------------------
  Geolocalize
---------------------------------------------------------- */

wpustorelocator.setgeolocalize = function() {
    var $geolocbutton = jQuery('#wpustorelocator-geolocalize');
    if ($geolocbutton.length < 1) {
        return;
    }

    if (!"geolocation" in navigator) {
        $geolocbutton.remove();
    }

    $geolocbutton.on('click', function(e) {
        e.preventDefault();
        var coords = coords;
        navigator.geolocation.getCurrentPosition(wpustorelocator.setgeoposition);
    });
};

wpustorelocator.setgeoposition = function(pos) {
    window.location.href = window.wpustorelocatorconf.base_url +
        '?lat=' + pos.coords.latitude +
        '&lng=' + pos.coords.longitude +
        '&country=' + jQuery('#wpustorelocator-country').val();
};

/* ----------------------------------------------------------
  Load map
---------------------------------------------------------- */

wpustorelocator.loadmap = function(map) {
    var opt = {
        center: {
            lat: 46.85,
            lng: 2.35
        },
        zoom: 6
    };

    var attr_lat = wpustorelocator_mapel.attr('data-lat'),
        attr_lng = wpustorelocator_mapel.attr('data-lng'),
        attr_radius = wpustorelocator_mapel.attr('data-radius'),
        attr_zoom = wpustorelocator_mapel.attr('data-zoom');

    if (attr_lat) {
        opt.center.lat = parseFloat(attr_lat);
    }
    if (attr_lng) {
        opt.center.lng = parseFloat(attr_lng);
    }
    if (attr_radius) {
        opt.zoom = wpustorelocator.radiusToZoom(parseInt(attr_radius, 10));
    }
    if (attr_zoom) {
        opt.zoom = parseInt(attr_zoom, 10);
    }

    wpustorelocator_map = new google.maps.Map(wpustorelocator_mapel.get(0), opt);

    var item = false;
    var markers = [];
    for (var i = 0, len = window.wpustorelocatorlist.length; i < len; i++) {
        markers.push(wpustorelocator.setmarker(window.wpustorelocatorlist[i]));
    }
    if (typeof MarkerClusterer == 'function') {
        var markerCluster = new MarkerClusterer(wpustorelocator_map, markers);
    }

};

wpustorelocator.radiusToZoom = function(radius) {
    return Math.round(15 - Math.log(radius) / Math.LN2);
};

/* ----------------------------------------------------------
  Create a marker
---------------------------------------------------------- */

wpustorelocator.infowindow = false;
wpustorelocator.setmarker = function(item) {
    var marker = new google.maps.Marker({
        position: new google.maps.LatLng(item.lat, item.lng),
        map: wpustorelocator_map,
        icon: window.wpustorelocatorconf.icon,
        title: item.name
    });
    var content = item.address + item.link;
    if (item.itinerary) {
        content += item.itinerary;
    }
    var infowindow = new google.maps.InfoWindow({
        content: content
    });
    google.maps.event.addListener(marker, 'click', function() {
        if (typeof wpustorelocator.infowindow == 'object') {
            wpustorelocator.infowindow.close();
        }
        wpustorelocator.infowindow = infowindow;
        infowindow.open(wpustorelocator_map, marker);
        wpustorelocator_map.setZoom(15);
        wpustorelocator_map.setCenter(marker.getPosition());
    });
    return marker;
};

/* ----------------------------------------------------------
  Load search autocomplete
---------------------------------------------------------- */

wpustorelocator.loadsearch = function() {
    var input = document.getElementById('wpustorelocator-search-address');
    if (!input) {
        return;
    }
    /* Load */
    var baseurl = jQuery('#wpustorelocator-baseurl').val();
    if (baseurl && ('pushState' in history)) {
        history.pushState({}, document.title, baseurl);
    }

    /* Autocomplete */
    var autocomplete = new google.maps.places.Autocomplete(input);
    jQuery(input).keypress(function(e) {
        var $this = jQuery(this);
        if (e.which == 13) {
            google.maps.event.trigger(autocomplete, 'place_changed');
            if (!jQuery('#wpustorelocator-search-lat').val()) {
                e.preventDefault();
                setTimeout(function() {
                    $this.closest('form').submit();
                }, 500);
            }
        }
    });
    google.maps.event.addListener(autocomplete, 'place_changed', function() {
        var place = autocomplete.getPlace(),
            country_code = 0,
            i;
        if (place && place.address_components) {
            for (var i in place.address_components) {
                if (place.address_components[i].types) {
                    if (place.address_components[i].types[0] == 'country') {
                        country_code = place.address_components[i].short_name;
                    }
                }
            }
            if (country_code) {
                country_selector.val(country_code);
                setTimeout(function() {
                    if (jQuery.fn.FakeSelect) {
                        jQuery('select').FakeSelect();
                    }
                }, 100);
            }
        }
        if (place && place.geometry && place.geometry.location) {
            jQuery('#wpustorelocator-search-lat').val(place.geometry.location.lat());
            jQuery('#wpustorelocator-search-lng').val(place.geometry.location.lng());
        }
    });

    /* Country */
    var country_selector = jQuery('#wpustorelocator-country');
    country_selector.on('change', function() {
        input.value = '';
        jQuery('#wpustorelocator-search-lat').val('');
        jQuery('#wpustorelocator-search-lng').val('');
    });
};
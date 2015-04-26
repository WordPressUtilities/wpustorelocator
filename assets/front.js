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
        var coords = jQuery(this).val().split('|');
        var latlng = new google.maps.LatLng(coords[0], coords[1]);
        wpustorelocator_map.setZoom(6);
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

    if (!navigator.hasOwnProperty('geolocation')) {
        $geolocbutton.destroy();
    }

    $geolocbutton.on('click', function(e) {
        e.preventDefault();
        var coords = coords;
        navigator.geolocation.getCurrentPosition(wpustorelocator.setgeoposition);
    });
};

wpustorelocator.setgeoposition = function(pos) {
    window.location.href = window.wpustorelocatorconf.base_url + '?lat=' + pos.coords.latitude + '&lng=' + pos.coords.longitude;
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
    for (var i = 0, len = window.wpustorelocatorlist.length; i < len; i++) {
        wpustorelocator.setmarker(window.wpustorelocatorlist[i]);
    }
};

wpustorelocator.radiusToZoom = function(radius) {
    return Math.round(15 - Math.log(radius) / Math.LN2);
};

/* ----------------------------------------------------------
  Create a marker
---------------------------------------------------------- */

wpustorelocator.setmarker = function(item) {
    var marker = new google.maps.Marker({
        position: new google.maps.LatLng(item.lat, item.lng),
        map: wpustorelocator_map,
        icon: window.wpustorelocatorconf.icon,
        title: item.name
    });
    var infowindow = new google.maps.InfoWindow({
        content: item.address + item.link
    });
    google.maps.event.addListener(marker, 'click', function() {
        infowindow.open(wpustorelocator_map, marker);
        wpustorelocator_map.setZoom(15);
        wpustorelocator_map.setCenter(marker.getPosition());
    });
};

/* ----------------------------------------------------------
  Load search autocomplete
---------------------------------------------------------- */

wpustorelocator.loadsearch = function() {
    var input = document.getElementById('wpustorelocator-search-address');
    if (!input) {
        return;
    }
    var autocomplete = new google.maps.places.Autocomplete(input);
    jQuery(input).keypress(function(e) {
        if (e.which == 13) {
            google.maps.event.trigger(autocomplete, 'place_changed');
            if (!jQuery('#wpustorelocator-search-lat').val()) {
                e.preventDefault();
            }
        }
    });
    google.maps.event.addListener(autocomplete, 'place_changed', function() {
        var place = autocomplete.getPlace();
        if (place && place.geometry && place.geometry.location) {
            jQuery('#wpustorelocator-search-lat').val(place.geometry.location.k);
            jQuery('#wpustorelocator-search-lng').val(place.geometry.location.D);
        }
    });
};
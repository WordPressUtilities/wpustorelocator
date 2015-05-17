jQuery(document).ready(function() {
    var input = document.getElementById('wpustorelocator-admingeocoding-content');
    if (!input) {
        return;
    }
    var autocomplete = new google.maps.places.Autocomplete(input);
    jQuery(input).keypress(function(e) {
        if (e.which == 13) {
            e.preventDefault();
        }
    });
    google.maps.event.addListener(autocomplete, 'place_changed', function() {
        var place = autocomplete.getPlace();
        if (place && place.geometry && place.geometry.location) {
            jQuery('#el_id_store_lat').val(place.geometry.location.lat());
            jQuery('#el_id_store_lng').val(place.geometry.location.lng());
        }
    });
    jQuery('#wpustorelocator-admingeocoding-button').on('click', function(e) {
        e.preventDefault();
        var val = jQuery('#el_id_store_address').val() +
            ' ' + jQuery('#el_id_store_address2').val() +
            ' ' + jQuery('#el_id_store_zip').val() +
            ' ' + jQuery('#el_id_store_city').val() +
            ' ' + jQuery('#el_id_store_region').val() +
            ', ' + jQuery('#el_id_store_country option:selected').text();
        jQuery(input).val(val);
        setTimeout(function() {
            jQuery(input).focus();
        }, 100);
    });
});
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
            jQuery('#el_id_store_lat').val(place.geometry.location.k);
            jQuery('#el_id_store_lng').val(place.geometry.location.D);
        }
    });
    jQuery('wpustorelocator-admingeocoding-save').on('click', function(e){
        e.preventDefault();
    });
});
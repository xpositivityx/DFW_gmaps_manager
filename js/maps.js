var geocoder;
var map;
var infoOpen = null;
var windows = [];
var markers = [];

function initialize() {
  geocoder = new google.maps.Geocoder();
	var myLatlng = new google.maps.LatLng(-25.363882,131.044922);
  var mapOptions = {
    zoom: 12,
    disableDefaultUI: true,
    scrollwheel: false,
    zoomControl: true
  };
  map = new google.maps.Map(document.getElementById('map-canvas'),
      mapOptions);
  google.maps.event.addListener(map, 'click', function() {
    for(var i = 0; i < windows.length; i++){
      windows[i].close();
    }
  });
  for(var i = 0; i<jsMap.length; i++){
    !function outer(i){
      google.maps.event.addListenerOnce(map, 'idle', function inner(e){
        codeAddress(jsMap[i]);
      });
    }(i);
  }
}

google.maps.event.addDomListener(window, 'load', initialize);


function codeAddress(address){
  var properties = ['lat1', 'lng1', 'name', 'phone', 'web', 'address'];
  address = address.split(',');
  var mapKey = new Array();
  address.map(function(val, i){
    if(i<2){
      mapKey[properties[i]] = Number(val);
    } else{
      mapKey[properties[i]] = val;
    }
  });
  map.setCenter({lat: mapKey['lat1'], lng: mapKey['lng1']});
  var content = '<div id="content">'+
      '<h1 id="firstHeading" class="firstHeading">' + mapKey['name'] + '</h1>'+
      '<div id="bodyContent">'+
      '<p>' + mapKey['phone'] + '</p>'+
      '<p>' + mapKey['address'] + '</p>' +
      '<p>Website: <a href="http://' + mapKey['web'] + '">'+ mapKey['web'] +
      '</a>'+
      '</p>'+
      '</div>'+
      '</div>';
  var infowindow = new google.maps.InfoWindow({
      content: content
  });
  windows.push(infowindow);
  var image_url = "http://www.dev.flairsecurity.com/wp-content/uploads/2015/03/flairman.png";

  var marker = new google.maps.Marker({
        map: map,
        position: {lat: mapKey['lat1'], lng: mapKey['lng1']},
        icon : image_url,
        title: mapKey['name']
      });

  markers.push(marker);

  google.maps.event.addListener(marker, 'click', function() {
    if(infoOpen){
      for(var i = 0; i < windows.length; i++){
        windows[i].close();
      }
    }
    infowindow.open(map,marker);
    infoOpen = true;
  });


}

google.maps.event.addDomListener(window, 'load', initialize);

function set_form(counter) {
  document.getElementById('mode').value = "update";
  var fill_name = document.getElementById('name_listing'+counter).innerHTML;
  document.getElementById('name').value = fill_name;
  var fill_street = document.getElementById('street_listing'+counter).innerHTML;
  document.getElementById('street').value = fill_street;
  var fill_city = document.getElementById('city_listing'+counter).innerHTML;
  document.getElementById('city').value = fill_city;
  var fill_state = document.getElementById('state_listing'+counter).innerHTML;
  document.getElementById('state').value = fill_state;
  var fill_web = document.getElementById('web_listing'+counter).children[0].innerHTML;
  document.getElementById('web').value = fill_web;
  var fill_phone = document.getElementById('phone_listing'+counter).innerHTML;
  document.getElementById('phone').value = fill_phone;


}


function rad(x){return x*Math.PI/180;}

function find_closest_marker() {
  address = document.getElementById('zip').value;
  geocoder.geocode( { 'address': address}, function(results, status) {
    if (status == google.maps.GeocoderStatus.OK) {
      lat = results[0].geometry.location.k;
      lng = results[0].geometry.location.D;
      var R = 6371;
      var distances = [];
      var closest = -1;
      for(i=0;i<markers.length; i++){
        var mlat = markers[i].position.lat();
        var mlng = markers[i].position.lng();
        var dLat  = rad(mlat - lat);
        var dLong = rad(mlng - lng);
        var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(rad(lat)) * Math.cos(rad(lat)) * Math.sin(dLong/2) * Math.sin(dLong/2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        var d = R * c;
        distances[i] = d;
        if ( closest == -1 || d < distances[closest] ) {
            closest = i;
        }
      }
      map.setCenter(markers[closest].position);
    }
  });
}

jQuery(document).ready(function($){
  $("#gmaps_search").click(function(){
    var term = $("#filter").attr("value");
    var data = {
      'action' : 'gmaps_search',
      'term' : term
    }
    if(term.length > 0){
      jQuery.post(ajaxurl, data, function(response){
          var obj = JSON.parse(response);
          if(obj[0]){
            $(".address_listing").remove();
            $.each(obj, function(index, value){
              var output = format_listing(index, value);
              $(".address-list").append(output);
            });
          }else{
            alert("There were no matches for your criteria.");
          }
      });
    } else {
      alert("Please enter a search term.");
    }
  });
});

function format_listing(index, value){
  var string = "<div class='address_listing'><p>";
  string += "<span class='name' id='name_listing" + index + "'>" + value.name + "</span> ";
  string += "<span class='street' id='street_listing" + index + "'>" + value.street_address + "</span>";
  string += "<span class= 'city' id='city_listing" + index + "'>" + value.city + "</span>";
  string += "<span class= 'state' id='state_listing" + index + "'>" + value.state + "</span>";
  string += "<span class= 'phone' id='phone_listing" + index + "'>" + value.phone_number + "</span>";
  string += "<span class= 'web' id='web_listing" + index + "'><a href='" + value.website + "'>" + value.website + "</a></span>";
  string += "</p>";
  string += "<a href='http://dev.flairsecurity.com/wp-admin/admin-post.php?action=remove_address&amp;name="+ value.name + "'>remove</a> ";
  string += "<a href='#' onclick='set_form(" + index +")'>update</a>";
  string += "</div>";
  return string;
}

function paginate(offset){
    var data = {
      'action' : 'paginate',
      'offset' : offset,
      'limit' : 2
    }
    send_request(data);
}

function send_request(data){
  jQuery(document).ready(function($){
    jQuery.post(ajaxurl, data, function(response){
      var obj = JSON.parse(response);
      if(obj[0]){
        $(".address_listing").remove();
        $.each(obj, function(index, value){
          var output = format_listing(index, value);
          $(".address-list").append(output);
          update_pagination(data);
        });
      }else{
        alert("There were no matches for your criteria.");
      }
    });
  });
}

function update_pagination(data){
  jQuery(document).ready(function($){
    $('.pagination').remove();
    var numbers = "";
    if (data['offset'] == 0){
      var prev = "<a href='#' class='pagination' onclick='paginate(0)'>Prev</a>";
      jQuery('#pagination').append(prev);
      for(i=1; i<4; i++){
        numbers += "<a href='#' class='pagination' onclick='paginate(" + ((i - 1) * data['limit']) + ")'>" + i + "</a>";
      }
    } else {
      var prev = "<a href='#' class='pagination' onclick='paginate(" + (data['offset'] - data['limit']) + ")'>Prev</a>";
      jQuery('#pagination').append(prev);
      var start = data['offset'] / data['limit'];
      for(i=start; i<(start+3); i++){
        if(i == (start+1)){
          numbers += "<p class='pagination'>" + i + "</p>";
        }else {
          numbers += "<a href='#' class='pagination' onclick='paginate(" + ((i - 1) * data['limit']) + ")'>" + i + "</a>";
        }  
      }
    }
    jQuery("#pagination").append(numbers);
    var next = "<a href='#' class='pagination' onclick='paginate(" + (data['offset'] + data['limit']) + ")'>Next</a>";
    jQuery("#pagination").append(next);
  });
}


<?php
/**
 * Plugin Name: gMaps Manager
 * Plugin URI: http://localhost:8000
 * Description: google maps plug in for wordpress
 * Version: 1.0
 * Author: David Williams
 * Author URI: http://no-URL.com
 * License: A short license name. Example: GPL2
 */


global $jal_db_version;
global $table_name;
global $wpdb;
$jal_db_version = '1.0';
$table_name = $wpdb->base_prefix . 'gMaps';


function enqueue_resources(){
	wp_enqueue_style('maps_style', plugins_url('/css/gMaps.css' , __FILE__));
	wp_enqueue_script('g-maps', "https://maps.googleapis.com/maps/api/js?key=AIzaSyC5MyIFc2Tyz1V_pkPp7thkSUa9xBfomws");
	wp_enqueue_script('maps_function', plugin_dir_url(__FILE__) . 'js/maps.js');
	wp_localize_script('maps_function', 'jsMap', add_markers());
}

add_action('wp_enqueue_scripts', 'enqueue_resources');
add_action('admin_enqueue_scripts', 'enqueue_resources');

function generate_map($atts=null){
	ob_start();?>
	<div class='map'>
		<div class="map-search">
			<h3 class='zip'>Find A Distributor Near You</h3>
			<input type="text" name='zip' class='zip' id='zip' onkeydown="if(event.keyCode == 13){find_closest_marker();}">
			<a class='zip' onclick='find_closest_marker()'>search</a>
		</div>
		<div id='map-canvas'>
		</div>
	</div>
	<?php
	$result = ob_get_contents();
	ob_end_clean();
	return $result;
}

add_shortcode('gmap', 'generate_map');

function dashboard_menu(){
	add_menu_page('gMaps', 'Distributor Manager', 'manage_options', 'gMaps', 'my_plugin_options');

}

add_action('wp_ajax_gmaps_search', 'gmaps_search');

function gmaps_search(){
	$term = $_POST['term'];
	$result = gmaps_search_address($term);
	$json = json_encode($result);
	header('Content-type : json');
	echo $json;
	die();
}

add_action('wp_ajax_paginate', 'paginate');

function paginate(){
	global $wpdb;
	$offset = $_POST['offset'];
	$limit = $_POST['limit'];
	$results = get_all_addresses($limit, $offset);
	$json = json_encode($results);
	header('Content-type : json');
	echo $json;
	die();
}

function my_plugin_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	?>
	<div class="heading">
		<h2>Distributor Manager</h2>
	</div>
	<div class="formwrapper"> 
	<form action= '<?php print admin_url() ?>admin-post.php' method = 'post'>
	<input type='hidden' name='action' value='add_address'>
	<input id='mode' type="hidden" name='mode' value='save'>
	<h3>Add A New Distributor</h3>
	<p>Name:</p>
	<input type=text id = 'name' name='name' required> <br>
	<p>Phone Number:</p>
	<input type=text id = 'phone' name='phone' placeholder='xxx-xxx-xxxx'> <br>
	<p>Web Address:</p>
	<input type=text id = 'web' name='web' placeholder='http://www.example.com/'> <br>
	<p>Street Address:</p>
	<input type=text id = 'street' name = 'street_address'> <br>
	<p>City:</p>
	<input type=text id = 'city' name = 'city'> <br>
	<p>State:</p>
	<input type=text id = 'state' name = 'state' maxlength=2> <br>
	<input type = submit id = 'submit'>
	</form>
	</div>

	<?php $addresses = get_all_addresses(); ?>
	<?php $total = get_address_count();?>
	<div class = 'address-list'>
		<div id="gmaps-search">
			<input type="text" id="filter">
			<button id='gmaps_search'>Search</button>
		</div>
	<?php
	$counter = 0;
	$limit = 2;
	?>
	<div id="pagination">
	<?php
	if ($total >= $limit){
		echo "<a href='#' class='pagination' onclick='paginate(0)'>Prev</a>";
		for($i=0; $i < ($total / $limit); $i++){
			if($i == 0){
				echo "<p class='pagination'>" . ($i + 1) . "</p>";
			}else{
				echo "<a href='#' class='pagination' onclick='paginate(" . ( $i * $limit ) . ")'>" . ($i + 1) . "</a>";
			}
		}
		echo "<a href='#' class='pagination' onclick=paginate(" . $limit . ")>Next</a>";
	}
	?>
	</div>
	<?php
	foreach ($addresses as $address){
		echo "
		<div class = 'address_listing'>
		<p><span class='name' id='name_listing$counter'>$address->name</span><span class='street' id='street_listing$counter'>$address->street_address</span><span class='city' id='city_listing$counter'>$address->city</span><span class='state' id='state_listing$counter'>$address->state</span>
	  <span class='phone' id='phone_listing$counter'>$address->phone_number</span><span class='web' id='web_listing$counter'><a href='$address->website'>$address->website</a></span></p>
		<a href='" . admin_url() . "admin-post.php?action=remove_address&name=" . $address->name . "'>remove</a><a href = '#' onclick='set_form($counter)'>update</a>
		</div>";
		++$counter;
	}
	?>
	</div>
	<?php
}

function get_all_addresses($limit = 2, $offset = 0){
	global $wpdb;
	$table_name = $wpdb->base_prefix . 'gMaps';
	$results = $wpdb->get_results(
		"SELECT * FROM $table_name 
		ORDER BY Name ASC
		LIMIT $limit 
		OFFSET $offset");
	return $results;
}

function get_address_count(){
	global $wpdb;
	$table_name = $wpdb->base_prefix . 'gMaps';
	$results = $wpdb->get_results(
		"SELECT COUNT(*) FROM $table_name");
	$results = get_object_vars($results[0]);
	return $results['COUNT(*)'];
}

function add_markers() {
	global $wpdb;
	$addresses = get_all_addresses(1000);
	$output = array();
	foreach($addresses as $a){
		$esc_name = addslashes($a->name);
		$esc_phone = addslashes($a->phone_number);
		$esc_web = addslashes($a->website);
		$ad = "$a->street_address. $a->city,$a->state";
		array_push($output, "$a->lat, $a->lng, '$esc_name', '$esc_phone', '$esc_web', '$ad'");
	}
	return $output;
}

add_action('admin_menu', 'dashboard_menu');

function add_address(){
	global $wpdb;
	$table_name = $wpdb->base_prefix . 'gMaps';
	$name = $_POST['name'];
	$street = $_POST['street_address'];
	$city = $_POST['city'];
	$state = $_POST['state'];
	$phone = $_POST['phone'];
	$web = $_POST['web'];
	$condition_sql = "SELECT name FROM $table_name
										WHERE street_address = '$street';
										";
	if ($_POST['mode'] == 'update'){
		$sql = "UPDATE $table_name
						SET name='$name', street_address='$street',
						city='$city',state='$state', phone_number='$phone', website='$web'
						WHERE street_address='$street'
						OR phone_number='$phone'
						OR name = '$name'
						OR website = '$web'";
		$wpdb->query($sql);
	}
	else {
		$latlng = geocode(format_address($street,$city,$state));
		$lat = $latlng['lat'];
		$lng = $latlng['lng'];
		$sql = "INSERT INTO $table_name (name,street_address,city,state, website, phone_number, lat, lng)
						VALUES ('$name', '$street', '$city', '$state', '$web', '$phone', $lat, $lng);";
		$wpdb->query($sql);
	}
	wp_redirect(admin_url('admin.php?page=gMaps'));
	exit();

}

function geocode($address){
	$address = urlencode($address);
	$response = wp_remote_get('http://maps.googleapis.com/maps/api/geocode/json?address='. $address . '&sensor=true');
	$result = wp_remote_retrieve_body( $response );
	$result = json_decode($result);
	$output = array('lat'=> $result->results[0]->geometry->location->lat, 'lng'=>$result->results[0]->geometry->location->lng);
	return $output;
}

function format_address($street,$city,$state){
	return "$street. $city,$state";
}

add_action('admin_post_add_address', 'add_address');

function remove_address(){
	global $wpdb;
	$table_name = $wpdb->base_prefix . 'gMaps';
	$name = $_GET['name'];
	$sql = "
		DELETE FROM $table_name
		WHERE name = '$name';
		";
	$wpdb->query($sql);
	wp_redirect(admin_url('admin.php?page=gMaps'));
	exit();
}

add_action('admin_post_remove_address', 'remove_address');

function gmaps_search_address($term){
	global $wpdb;
	$data = array();
	$table_name = $wpdb->base_prefix . 'gMaps';
	$sql = "
		SELECT * FROM $table_name
		WHERE name LIKE '%$term%' OR
		street_address LIKE '%$term%' OR
		city LIKE '%$term%' OR
		state LIKE '%$term%';
	";
	$result = $wpdb->get_results($sql, OBJECT);
	return $result;
}

function jal_install(){
	global $wpdb;
	global $jal_db_version;

	$table_name = $wpdb->base_prefix . 'gMaps';
	$sql = "CREATE TABLE IF NOT EXISTS " . $table_name . " (
		id int(9) PRIMARY KEY NOT NULL AUTO_INCREMENT,
		name text NOT NULL,
		street_address text NOT NULL,
		city text NOT NULL,
		state text NOT NULL,
		lat float NOT NULL,
		lng float NOT NULL,
		website text,
		phone_number text
		);";
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

	add_option('jal_db_version', $jal_db_version);

}

register_activation_hook(__FILE__, 'jal_install');


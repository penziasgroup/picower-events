<?php
/**
 * Plugin Name: Picower Events
 * Description: This plugin creates provides an API to export events in a variety of format
 * Version: 1.0.0
 * Author: Seth Seligman
 */

include( dirname(__FILE__) . "/simple_html_dom.php");

add_action('init', 'eventsRSS');
add_action('admin_head', 'picower_add_my_tc_button');

global $organizer_map;
global $event_type_map;
global $contact_name;
global $contact_email;
global $bcs_talks_events;

date_default_timezone_set('America/New_York');

$organizer_map = array(
	'The Picower Institute for Learning and Memory' => 	'74'	
);

$event_type_map = array(
        'MIT Colloquium on the Brain and Cognition ' => '57',
	'Colloquia,' => '57',
	'Special Seminars,' => '66',
	'Symposia,' => '60',
	'Plastic Lunches,' => '309',
	'Picower Lecture,' => '64',
	'Picower Retreats,' => '68',
	'Aging Brain Seminar Series' => '64',
        'Retreat' => '68'
);

$bcs_talks_events = array(
	'MIT Colloquium on the Brain and Cognition ',
        'Colloquia,',
        'Special Seminars,',
        'Symposia,',
        'Picower Lecture,',
        'Aging Brain Seminar Series'
);

$contact_name = "Casey Reisner";
$contact_email = "creisner@mit.edu";

function eventsRSS(){
	add_feed('events', 'events_rss_render');
}


function events_rss_render() {
	get_template_part( 'feed', 'events' );
}


add_shortcode("get-events", "get_events");


function get_event_meta_keys(){

	$meta_keys = array(
                'evcal_subtitle',
		'evcal_location_name',
		'evcal_location',
		'evcal_organizer',
		'event_year',
		'evcal_lat',
		'evcal_lon',
		'_evcal_ec_f1a1_cus',
		'_evcal_ec_f2a1_cus',
		'_evcal_ec_f3a1_cus',
		'evcal_srow',
		'evcal_erow'
	);
		
	$type = "ajde_events";
	global $wpdb;

	// get meta keys for events
	$q = "
	select
	distinct meta_key
	from $wpdb->posts
	left join $wpdb->postmeta on $wpdb->posts.ID = $wpdb->postmeta.post_id
	where $wpdb->posts.post_type = '$type'
	and meta_key in (" . "'" . implode("','", $meta_keys) . "'" . ")";	
	
	$m = array();
	
	$results = $wpdb->get_results($q, ARRAY_A);
	
	foreach($results as $r){
		$m[] = $r['meta_key'];	
	}
	
	return $m;
}

function convert_time_zone($t){
  $ds = date('Y-m-d H:i:s',$t);
  //echo "ds = $ds \n";

  $date = new DateTime($ds,new DateTimeZone('UTC'));
  $date->setTimezone(new DateTimeZone('America/New_York')); 

  // Word Press is recording GMT dates as if they were EST dates, so we need to add the negative of the offset 
  $u = intval($date->format('U'));
  $z = intval($date->format('Z')) * -2;
  //echo "u = $u\n";
  //echo "z = $z\n";

  $newDate = $u + $z;
  //echo "$newDate \n";
  return $newDate;
}

function get_events(){
	$type = "ajde_events";
	$time_mod = 0; //14400; // number of seconds for 4 hour time zone shift from GMT to EDT
	global $wpdb;
	global $organizer_map;
	global $event_type_map;
     	global $contact_name;
	global $contact_email;
        global $bcs_talks_events;
	
	$meta_keys = get_event_meta_keys();

	// get events query using meta keys
 	$q = "
    select 
 	p.ID,
 	concat('picower_',p.ID) as external_id,		
 	p.post_author,
 	p.post_date,
 	p.post_title,
 	p.post_name,
 	p.post_modified,
 	p.GUID as guid,
 	p.post_content,
 	tx.event_type,
 	tx.event_type_2,
 	tx.event_organizer,";
 	
 	// get meta data
 	foreach($meta_keys as $key => $value) {
 		$q .= "\nm" . $key  . ".meta_value " . $value;
 		if ( ($key + 1) <  count($meta_keys)) {
 			$q .= ",";
 		}
 	}
	
	$q .= "
	FROM
    $wpdb->posts p";	
 	foreach($meta_keys as $key => $value) {
 		$q .= "\nLEFT JOIN $wpdb->postmeta m$key on p.ID = m$key.post_id and m$key.meta_key = '" . $value . "' ";
 		if ($value == 'evcal_srow') {
 			$start_date_tb = "m" . $key;
 		}
 	}

 	$q .= "
 	LEFT JOIN 
 	(
 	select
 	tr.object_id,		
 	max(if(taxonomy = 'event_type',name,'')) as event_type,
 	max(if(taxonomy = 'event_type_2',name,'')) as event_type_2,
 	max(if(taxonomy = 'event_organizer',name,'')) as event_organizer
 	from
 	wp_term_relationships tr
 	join wp_term_taxonomy tt on tr.term_taxonomy_id = tt.term_taxonomy_id
 	join wp_terms t on tt.term_id = t.term_id
 	group by tr.object_id
 	) tx on tx.object_id = p.ID "; 	
 	
 	$q .= "
 	where p.post_type = '$type' AND p.post_status = 'publish' ";
 	
 	// Add paramters
 	get_event_params($q,$start_date_tb);
 	 	

	// Turn on SQL_BIG_SELECTS to avoid MAX_JOIN_SIZE errors on some servers
    $wpdb->query("SET SQL_BIG_SELECTS=1"); 
	$events = $wpdb->get_results($q, ARRAY_A);
	
	foreach ($events as $key => $event) {
		
		// Modify Dates from GMT to America/New_York
		if (isset($event['evcal_srow'])) {
                        $events[$key]['evcal_srow'] = convert_time_zone($event['evcal_srow']);
			$events[$key]['start_date']  = gmdate("Y-m-d H:i:s", $events[$key]['evcal_srow']);
		}
		
		if (isset($event['evcal_erow'])) {
                        $events[$key]['evcal_erow'] = convert_time_zone($event['evcal_erow']);
			$events[$key]['end_date']  = gmdate("Y-m-d H:i:s", $events[$key]['evcal_erow']);
		}
		
		// Add event_location_url if event location is defined
		if (isset($event['evcal_location_name'])) {
			$events[$key]['event_location_url']  = "http://whereis.mit.edu/?q=" . htmlentities($event['evcal_location_name']);
		}
		
		// Map Picower Organizer name to BCS name
		if (isset($event['evcal_organizer']) && isset($organizer_map[$event['evcal_organizer']])) { 
			$events[$key]['bcs_organization'] = $organizer_map[$event['evcal_organizer']];
		}
		
		// Map Picower event type to BCS event type
		if (isset($event['event_type'])){
			$events[$key]['bcs_event_type'] = $event_type_map[$event['event_type']];

                        // Add seminar_name to hold 'Picower Lecture' events
                        if ($event['event_type'] == 'Picower Lecture,') {
                          $events[$key]['bcs_seminar_name'] = 'Picower Lecture';
                        } 
		}
		

		// Get speaker name and bio
		if (isset($event['_evcal_ec_f1a1_cus'])) {
                        $content = $event['_evcal_ec_f1a1_cus'];

                        // get html dom element
                        $html = str_get_html($content);

                        // get speaker name (surrounded by 'speaker_name' class)
                        $speaker_name = $html->find('.speaker_name',0);

                        if ($speaker_name) {
                          $events[$key]['speaker_name'] = $speaker_name->innertext;
                        }

                        // get the speaker image from the full bio (surrounded by speaker-image class)
                        $speaker_image = $html->find('.speaker_image img',0);
                        if ($speaker_image) {
                          $events[$key]['speaker_image'] = $speaker_image->src;   
                        }
 
                        // try to extract speaker_bio using speaker-bio class
                        $speaker_bio = $html->find('.speaker_bio',0);
                        if ($speaker_bio) {
                          $events[$key]['speaker_bio'] = strip_tags($speaker_bio->innertext);
                        }
			// else, use the full speaker field minus the speaker name and image if found
			else {
                          if ($speaker_name) {
                            $speaker_name->outertext = "";
                          }
                          if ($speaker_image) {
                            $speaker_image->outertext = "";
                          }
                          $events[$key]['speaker_bio'] = strip_tags($html->save());
			}

		}
		
		// remove shortcodes from content
		$events[$key]['post_content'] = preg_replace( '|\[(.+?)\](.+?\[/\\1\])?|s', '', $events[$key]['post_content']);
		
		// create a new description field for the export
		$events[$key]['description'] = $events[$key]['post_content'];
		
		// remove shortcodes from '_evcal_ec_f2a1_cus' 
		if (isset($events[$key]['_evcal_ec_f2a1_cus'])) {
			$events[$key]['_evcal_ec_f2a1_cus'] = preg_replace( '|\[(.+?)\](.+?\[/\\1\])?|s', '', $events[$key]['_evcal_ec_f2a1_cus']);
			$events[$key]['description'] .= $events[$key]['_evcal_ec_f2a1_cus'];				
		}
				
		
		// Get Attachments
		$children = get_children( array('post_parent' => $event['ID'],'post_type' => 'attachment')); 
		$events[$key]['attachment'] = array();
		
		foreach($children as $ckey => $c) {
			$events[$key]['attachment'][] = $c->guid;
		}

		// Add default contact
		$events[$key]['contact_name'] = $contact_name;
            	$events[$key]['contact_email'] = $contact_email;

                // Add default notification settings
                $events[$key]['email_reminders'] = 1; 
                $events[$key]['reminder_settings'] = 'list';

                // Determine notification_list by event type
                $notification_list = "bcs-all@mit.edu";
                if (in_array($events[$key]['event_type'],$bcs_talks_events)) {
                	$notification_list .= "\nbcs-talks@mit.edu";
                }
                $events[$key]['notification_list'] = $notification_list;

	}
	
	return events_to_xml($events);	
}


function get_event_params(&$q,$start_date_tb){

	$scope = "future"; // the default behavior is to only return future events
	
		// POST _ID
	if (isset($_GET['post_id'])) {
		$q .= " AND p.ID = '" . $_GET['post_id'] . "' ";
	}
	
	// Start Date
	if (isset($_GET['start_date'])) {
		$q .= " AND $start_date_tb.meta_value >= '" . strtotime($_GET['start_date']) . "' ";
	}
	// If no start date is provided look for a scope parameter to see if all or only future events are returned
	else {
		if (isset($_GET['scope'])) {
			$scope = $_GET['scope'];
		}
		if ($scope != "all") {
			$q .= " AND $start_date_tb.meta_value >= '" . time() . "' ";
		}
	}
	
	// End Date
	if (isset($_GET['end_date'])) {
		$q .= " AND $start_date_tb.meta_value <= '" . strtotime($_GET['end_date']) . "' ";
	}
	
	// Limit
	if (isset($_GET['limit'])) {
		$limit = $_GET['limit'];
			
		// Offset
		if (isset($_GET['offset'])) {
			$offset = $_GET['offset'];
		}
		else {
			$offset = 0;
		}
	}
	
	$q .= "
 	order by evcal_srow asc,p.ID ";
	
	if (isset($limit)) {
		$q .= " limit $offset,$limit ";
	}
}

function events_to_xml($events) {
	
	$domDoc = new DOMDocument;
	$rootElt = $domDoc->createElement('events');
	$rootNode = $domDoc->appendChild($rootElt);

	foreach ($events as $e) {
		$eventElt = $domDoc->createElement('event');
		$event = $rootNode->appendChild($eventElt);

		$keys = array_keys($e);
		foreach($keys as $k) {
			$element = $event->appendChild($domDoc->createElement($k));
			$value = $e[$k];
			if (is_array($value)) {
				foreach ($value as $v){
					$item = $element->appendChild($domDoc->createElement('item'));
					$item->appendChild($domDoc->createTextNode($v));
				}
			}
			else {
				$element->appendChild($domDoc->createTextNode($value));
			}
		}
	}
	
 	return $domDoc->saveXML();
}



/* tinymce */
// add new buttons


function picower_add_my_tc_button() {

    global $typenow;
    add_filter("mce_external_plugins", "picower_add_tinymce_plugin");
    add_filter('mce_buttons', 'picower_register_my_tc_button');

}

function picower_add_tinymce_plugin($plugin_array) {
    if (get_post_type() == 'ajde_events') {
      $plugin_array['picower_speaker_name_button'] = plugins_url( '/picower-button.js', __FILE__ ); 
      $plugin_array['picower_speaker_image_button'] = plugins_url( '/picower-button.js', __FILE__ );  
      $plugin_array['picower_speaker_bio_button'] = plugins_url( '/picower-button.js', __FILE__ );   
      return $plugin_array;
    }
}

function picower_register_my_tc_button($buttons) {
   array_push($buttons, "picower_speaker_name_button");
   array_push($buttons, "picower_speaker_image_button");
   array_push($buttons, "picower_speaker_bio_button");
   return $buttons;
}

?>

<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Api extends CI_Controller {

	// todo - include total results in response status
	function __construct()
	{
		parent::__construct();
		$this->load->library('simple_html_dom');
	}
	
	function index()
	{
		echo '<h1>unofficial sxsw music api</h1>';
		echo '<p>welcome to the api. everything is in json, deal with it. last updated 10/2/2011</p>';
		echo '<p>this data is all scraped from http://austin2011.sched.org/type/music/print, with musicbrainz IDs for bands who have them and lat/lng combos for venues google knows about.';
		echo 'i plan to rescrape frequently so this data should be fairly up to date.</p>';
		
		echo '<p><strong>note</strong> a few venues have empty lat/lngs (sorry) and quite a depressing majority of bands have no musicbrainz id (975/1393). will work on improving this</p>';
		echo '<h2>api endpoints</h2>';
		echo '<h3>gigsbyband</h3>';
		echo '<ul>';
		echo '<li><strong>' . site_url("api/gigsbyband/&lt;mode&gt;/&lt;identifier&gt;") . '</strong><br /><br />';
		echo '<strong>mode</strong> can be either "musicbrainz" or "name". the latter is a free text search.<br />';
		echo '<strong>identifier</strong> can be either a musicbrainz id or a band name.<br />';
		echo '<strong>eg</strong> <a href="'.site_url("api/gigsbyband/name/freddie gibbs").'">'.site_url("api/gigsbyband/name/freddie gibbs").'</a><br />';
		echo '<strong>or</strong> <a href="'.site_url("api/gigsbyband/musicbrainz/2c7dcadf-60cd-4e5b-8147-a352fd3408c7").'">'.site_url("api/gigsbyband/musicbrainz/2c7dcadf-60cd-4e5b-8147-a352fd3408c7").'</a><br />';
		echo '<strong>all bands</strong> <a href="'.site_url("api/gigsbyband").'">'.site_url("api/gigsbyband").'</a>';
		echo '</ul>';
		
		echo '<h3>gigsbyvenue</h3>';
		echo '<ul>';
		echo '<li><strong>' . site_url("api/gigsbyvenue/&lt;venue name&gt") . '</strong><br /><br />';
		echo '<strong>venue name</strong> is a free text search on the name of a venue.<br />';
		echo '<strong>eg</strong> <a href="'.site_url("api/gigsbyvenue/flamingo cantina").'">'.site_url("api/gigsbyvenue/flamingo cantina").'</a><br />';
		echo '<strong>all venues</strong> <a href="'.site_url("api/gigsbyvenue").'">'.site_url("api/gigsbyvenue").'</a>';
		echo '</ul>';
		
		echo '<h3>gigsbydate</h3>';
		echo '<ul>';
		echo '<li><strong>' . site_url("api/gigsbydate/&lt;date&gt") . '</strong><br /><br />';
		echo '<strong>date</strong> is a YYYY-MM-DD formatted date string, although uses php\'s strtotime() function so you can be a smart arse and use stuff like "next tuesday".<br />';
		echo '<strong>eg</strong> <a href="'.site_url("api/gigsbydate/2011-03-15").'">'.site_url("api/gigsbydate/2011-03-15").'</a><br />';
		echo '<strong>all dates</strong> <a href="'.site_url("api/gigsbydate").'">'.site_url("api/gigsbydate").'</a>';
		echo '</ul>';
		
		echo '<p><small>by matt andrews (<a href="http://www.threechords.org/blog">blog</a> | <a href="http://www.twitter.com/mattpointblank">twitter</a>)</small></p>';
	}
	
	function gigsbyband($mode = NULL, $band = NULL)
	{
		if(!$mode && !$band) {
			$this->db->order_by('band_name');
			$this->db->select('band_name, musicbrainz');
			$bands = $this->db->get('bands')->result_array();
			echo json_encode(array('status' => 'success', 'response' => array('bands' => $bands)));
			die;
		}
		if(!in_array($mode, array('musicbrainz', 'name'))) {
			echo json_encode(array('status' => 'error', 'response' => 'incorrect mode (accepts "musicbrainz" or "name" - you specified "'.$mode.'"'));
			die;
		}
		if(!$mode) { echo json_encode(array('status' => 'error', 'response' => 'please enter a mode (accepts "musicbrainz" or "name"')); die;  }
		if(!$band) { echo json_encode(array('status' => 'error', 'response' => 'please enter a band name or musicbrainz id')); die;  }
		
		$band = urldecode($band);
		$response = array();
		
		if($mode == 'musicbrainz') {
			$this->db->where('musicbrainz', $band);
		} else if ($mode == 'name') {
			$this->db->like('band_name', $band);
		}
		$band = $this->db->get('bands')->row_array();
		if(empty($band)) {
			echo json_encode(array('status' => 'error', 'response' => 'no band found, sorry')); die;
		}
		$this->db->select('date, start_time, end_time, venue_name, lat, lng, id, url');
		$this->db->join('venues', 'listings.venue_id = venues.venue_id');
		$gigs = $this->db->get_where('listings', array('band_id' => $band['band_id']))->result_array();
		$response = array('status' => 'success', 'response' => array('band' => $band, 'gigs' => $gigs));
	
		echo json_encode($response);
		
	}
	
	function gigsbyvenue($venue = NULL)
	{
		if(!$venue) { 
			//echo json_encode(array('status' => 'error', 'response' => 'please enter a venue name')); die;
			$this->db->select('venue_name, lat, lng');
			$this->db->order_by('venue_name');
			$venues = $this->db->get('venues')->result_array();
			echo json_encode(array('status' => 'success', 'response' => array('venues' => $venues)));
			die;
		}
		
		$venue = urldecode($venue);
		$response = array();
		
		$this->db->like('venue_name', $venue);
		$venue = $this->db->get('venues')->row_array();
		if(empty($venue)) {
			echo json_encode(array('status' => 'error', 'response' => 'no venue found, sorry')); die;
		}
		$this->db->select('date, start_time, end_time, band_name, musicbrainz, id, url');
		$this->db->join('bands', 'listings.band_id = bands.band_id');
		$gigs = $this->db->get_where('listings', array('venue_id' => $venue['venue_id']))->result_array();
		$response = array('status' => 'success', 'response' => array('venue' => $venue, 'gigs' => $gigs));
	
		echo json_encode($response);
		
	}	
	
	function gigsbydate($date = NULL)
	{
		if(!$date) { 
			$this->db->group_by('date');
			$this->db->order_by('date');
			$this->db->select('date, COUNT(id) AS num_gigs');
			$dates = $this->db->get('listings')->result_array();
			echo json_encode(array('status' => 'success', 'response' => array('dates' => $dates)));
			die;
		}
		
		$date = date('Y-m-d', strtotime($date));
		
		$response = array();
		
		//$this->db->select('date, start_time, end_time, band_name, musicbrainz, id, url');
		$this->db->join('bands', 'listings.band_id = bands.band_id');
		$this->db->join('venues', 'listings.venue_id = venues.venue_id');
		$gigs = $this->db->get_where('listings', array('date' => $date))->result_array();
		if(empty($gigs)) {
			echo json_encode(array('status' => 'error', 'response' => 'no gigs on that date, sorry')); die;
		}
		$response = array('status' => 'success', 'response' => array('gigs' => $gigs));
	
		echo json_encode($response);
		
	}	
	
	
	
}
?>
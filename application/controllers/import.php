<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Import extends CI_Controller {

	
	//todo - trim all band/venue names
	
	function __construct()
	{
		parent::__construct();
		$this->load->library('simple_html_dom');
	}

	
	function import($page = 0)
	{
		set_time_limit(500);
		if($page == 0) {
			$this->db->truncate('initial-import');
		}
		
		$urls = array(
			array('date' => '2011-03-14', 'url' => 'http://austin2011.sched.org/2011-03-14/print'),
			array('date' => '2011-03-15', 'url' => 'http://austin2011.sched.org/2011-03-15/print'),
			array('date' => '2011-03-16', 'url' => 'http://austin2011.sched.org/2011-03-16/print'),
			array('date' => '2011-03-17', 'url' => 'http://austin2011.sched.org/2011-03-17/print'),
			array('date' => '2011-03-18', 'url' => 'http://austin2011.sched.org/2011-03-18/print'),
			array('date' => '2011-03-19', 'url' => 'http://austin2011.sched.org/2011-03-19/print'),
		);	
				
		$html = file_get_html($urls[$page]['url']);
		$date = $urls[$page]['date'];
		
		foreach($html->find('table tr') as $row) {
			
			if($row->find('td h2')) {
				$h2 = $row->find('td h2', 0)->outertext;
				$d = preg_split("#<h2 id='(.*?)'>#", $h2, NULL, PREG_SPLIT_DELIM_CAPTURE);
				$item['date'] = $d[1];
			}
			
			if($row->find('td.time', 0)) {
				
				if($row->find('td.type span', 0)->innertext == 'M') {
					$item['date'] = $date;
					$time = $row->find('td.time span', 0)->innertext;
					$time = explode(' &ndash; ', $time);
					$item['start_time'] = date('H:i:s', strtotime($time[0]));
					$item['end_time'] = date('H:i:s', strtotime($time[1]));
					$link = $row->find('td.title', 0)->innertext;
					
					$regex_venue = "#<span class='vs'>(.*?)</span>#";
					$arr = preg_split($regex_venue, $link, NULL, PREG_SPLIT_DELIM_CAPTURE);
					$item['venue'] = $arr[1];
					
					$regex_id = "#<a href=\"(.*?)\" id=\"(.*?)\">#";
					$arr = preg_split($regex_id, $link, NULL, PREG_SPLIT_DELIM_CAPTURE);
					$item['url'] = $arr[1];
					$item['id'] = $arr[2];
					$item['band'] = preg_replace("#<span class='vs'>(.*?)</span></a>#", '', $arr[3]);
					
					
					$this->db->insert('initial-import', $item);
					$item = array();
				}
				
			}
		}
		
		if($page < 5) {
			$next = $page+1;
			echo 'stage ' . $page . ' of importing complete <br />';
			echo '<a href="'.site_url('api/import/' . $next).'">go to next</a>';
		} else {
			echo 'import complete';
		}
	}
	
	function musicbrainz()
	{
		set_time_limit(50000000);
		$listings = $this->db->get('initial-import')->result_array();
		
		$bands = $venues = array();
		
		foreach($listings as $l) {
			$bands[$l['band']] = $l['band'];
			$venues[$l['venue']] = $l['venue'];
		}
		
		$api_key = 'a4ec8527e6aa80b22202881cf2aacd42';
		foreach($bands as $b) {
			
			$band_exists = $this->db->get_where('bands', array('band_name' => $b))->num_rows();
			
			if($band_exists == 0) {
				$mb_bands = simplexml_load_file('http://ws.audioscrobbler.com/2.0/?method=artist.getinfo&artist='.urlencode(trim($b)).'&api_key='.$api_key);
				sleep(0.2);
				if(!isset($mb_bands->{'error code'})) {
					$mbid = "".$mb_bands->artist->mbid."";
				} else {
					$mbid = '';
				}
				$this->db->insert('bands', array('band_name' => $b, 'musicbrainz' => $mbid));
			} else {
				echo 'band exists - ' . $b . '<br />';
			}
		}
		
		$bands = $this->db->get('bands')->result_array();
		foreach($bands as $b) { 
			 
			if(isset($bandlist[$b['band_name']])) {
				echo $b['band_name'] . ' is dupe <br />';
				$this->db->where('band_id', $b['band_id']);
				$this->db->delete('bands');
			}
			$bandlist[$b['band_name']] = $b;
		}
		
	}
	
	function geocode()
	{
		set_time_limit(50000000);
		$listings = $this->db->get('initial-import')->result_array();
		
		$venues = array();
		
		foreach($listings as $l) {
			$venues[$l['venue']] = $l['venue'];
		}
		
		foreach($venues as $v) {
			
			$venue_exists = $this->db->get_where('venues', array('venue_name' => $v))->num_rows();
			
			if($venue_exists == 0) {
			
				$url = 'http://maps.googleapis.com/maps/api/geocode/json?address='.urlencode(trim($v)).'+Austin,+TX&sensor=true';
				$json = json_decode(file_get_contents($url));
				sleep(0.2);
				
				if($json->status == 'OK') {
					$lat = $json->results[0]->geometry->location->lat;
					$lng = $json->results[0]->geometry->location->lng;
				} else {
					$lat = $lng = NULL;
				}
				$this->db->insert('venues', array('venue_name' => $v, 'lat' => $lat, 'lng' => $lng));
			}
		}
		
	}
	
	function normalise()
	{
		
		$venues = $this->db->get('venues')->result_array();
		foreach($venues as $v) { $venuelist[$v['venue_name']] = $v['venue_id']; }
		
		$bands = $this->db->get('bands')->result_array();
		foreach($bands as $b) { $bandlist[$b['band_name']] = $b['band_id']; }
		
		$listings = $this->db->get('initial-import')->result_array();
		foreach($listings as $l) {
			$l['venue_id'] = $venuelist[$l['venue']];
			unset($l['venue']);
			$l['band_id'] = $bandlist[$l['band']];
			unset($l['band']);
			$this->db->insert('listings', $l);
		}
	}
}
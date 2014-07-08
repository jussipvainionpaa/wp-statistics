<?php
	class WP_Statistics {
		
		protected $db;
		protected $tb_prefix;
		
		private $ip;
		private $result;
		private $agent;
		
		public $coefficient = 1;
		public $plugin_dir = '';
		public $user_id = 0;
		public $options = array();
		public $user_options = array();

		public function __construct() {
		
			global $wpdb, $table_prefix;
			
			$this->db = $wpdb;
			$this->tb_prefix = $table_prefix;
			$this->agent = $this->get_UserAgent();
			$this->coefficient = get_option('wps_coefficient', 1);
			$this->options = get_option( 'wp_statistics' ); 
			
			// This is a bit of a hack, we strip off the "includes/classes" at the end of the current class file's path.
			$this->plugin_dir = substr( dirname( __FILE__ ), 0, -17 );
		}

		public function set_user_id() {
			if( $this->user_id == 0 ) {
				$this->user_id = get_current_user_id();
			}
		}
		
		public function load_options() {
			$this->options = get_option( 'wp_statistics' ); 
		}
		
		public function load_user_options() {
			$this->set_user_id();

			// Not sure why, but get_user_meta() is returning an array or array's unless $single is set to true.
			$this->user_options = get_user_meta( $this->user_id, 'wp_statistics', true );
		}
		
		public function get_option($option, $default = null) {
			if( !is_array($this->options) ) { return FALSE; }
		
			if( !array_key_exists($option, $this->options) ) {
				if( isset( $default ) ) {
					return $default;
				} else {
					return FALSE;
				}
			}
			
			return $this->options[$option];
		}
		
		public function get_user_option($option, $default = null) {
			if( $this->user_id == 0 ) {return FALSE; }
			
			if( !array_key_exists($option, $this->user_options) ) {
				if( isset( $default ) ) {
					return $default;
				} else {
					return FALSE;
				}
			}
			
			return $this->user_options[$option];
		}

		public function update_option($option, $value) {
			$this->options[$option] = $value;
			
			update_option('wp_statistics', $this->options);
		}
		
		public function update_user_option($option, $value) {
			if( $this->user_id == 0 ) { return FALSE; }

			$this->user_options[$option] = $value;
			
			update_user_meta( $this->user_id, 'wp_statistics', $this->user_options );
		}

		public function store_option($option, $value) {
			$this->options[$option] = $value;
		}
		
		public function store_user_option($option, $value) {
			if( $this->user_id == 0 ) { return FALSE; }

			$this->user_options[$option] = $value;
		}

		public function save_options() {
			update_option('wp_statistics', $this->options);
		}
		
		public function save_user_options() {
			if( $this->user_id == 0 ) { return FALSE; }

			update_user_meta( $this->user_id, 'wp_statistics', $this->user_options );
		}
		
		public function isset_option($option) {
			return array_key_exists( $option, $this->options );
		}
		
		public function isset_user_option($option) {
			if( $this->user_id == 0 ) { return FALSE; }

			return array_key_exists( $option, $this->user_options );
		}

		public function Primary_Values() {
		
			$this->result = $this->db->query("SELECT * FROM {$this->tb_prefix}statistics_useronline");
			
			if( !$this->result ) {
			
				$this->db->insert(
					$this->tb_prefix . "statistics_useronline",
					array(
						'ip'		=>	$this->get_IP(),
						'timestamp'	=>	date('U'),
						'date'		=>	$this->Current_Date(),
						'referred'	=>	$this->get_Referred(),
						'agent'		=>	$this->agent['browser'],
						'platform'	=>	$this->agent['platform'],
						'version'	=> 	$this->agent['version']
					)
				);
			}
			
			$this->result = $this->db->query("SELECT * FROM {$this->tb_prefix}statistics_visit");
			
			if( !$this->result ) {
			
				$this->db->insert(
					$this->tb_prefix . "statistics_visit",
					array(
						'last_visit'	=>	$this->Current_Date(),
						'last_counter'	=>	$this->Current_date('Y-m-d'),
						'visit'			=>	1
					)
				);
			}
			
			$this->result = $this->db->query("SELECT * FROM {$this->tb_prefix}statistics_visitor");
			
			if( !$this->result ) {
			
				$this->db->insert(
					$this->tb_prefix . "statistics_visitor",
					array(
						'last_counter'	=>	$this->Current_date('Y-m-d'),
						'referred'		=>	$this->get_Referred(),
						'agent'			=>	$this->agent['browser'],
						'platform'		=>	$this->agent['platform'],
						'version'		=> 	$this->agent['version'],
						'ip'			=>	$this->get_IP(),
						'location'		=>	'000'
					)
				);
			}
		}
		
		public function get_IP() {
		
			// By default we use the remote address the server has.
			$temp_ip = $_SERVER['REMOTE_ADDR'];
		
			// Check to see if any of the HTTP headers are set to identify the remote user.
			// These often give better results as they can identify the remote user even through firewalls etc, 
			// but are sometimes used in SQL injection attacks.
			if (getenv('HTTP_CLIENT_IP')) {
				$temp_ip = getenv('HTTP_CLIENT_IP');
			} elseif (getenv('HTTP_X_FORWARDED_FOR')) {
				$temp_ip = getenv('HTTP_X_FORWARDED_FOR');
			} elseif (getenv('HTTP_X_FORWARDED')) {
				$temp_ip = getenv('HTTP_X_FORWARDED');
			} elseif (getenv('HTTP_FORWARDED_FOR')) {
				$temp_ip = getenv('HTTP_FORWARDED_FOR');
			} elseif (getenv('HTTP_FORWARDED')) {
				$temp_ip = getenv('HTTP_FORWARDED');
			} 

			// Trim off any port values that exist.
			if( strstr( $temp_ip, ':' ) !== FALSE ) {
				$temp_a = explode(':', $temp_ip);
				$temp_ip = $temp_a[0];
			}
			
			// Check to make sure the http header is actually an IP address and not some kind of SQL injection attack.
			$long = ip2long($temp_ip);
		
			// ip2long returns either -1 or FALSE if it is not a valid IP address depending on the PHP version, so check for both.
			if($long == -1 || $long === FALSE) {
				// If the headers are invalid, use the server variable which should be good always.
				$temp_ip = $_SERVER['REMOTE_ADDR'];
			}
			
			$this->ip = $temp_ip;
			
			return $this->ip;
		}
		
		public function get_UserAgent() {
		
			$agent = parse_user_agent();
			
			if( $agent['browser'] == null ) { $agent['browser'] = "Unknown"; }
			if( $agent['platform'] == null ) { $agent['platform'] = "Unknown"; }
			if( $agent['version'] == null ) { $agent['version'] = "Unknown"; }
			
			return $agent;
		}
		
		public function get_Referred($default_referr = false) {
		
			$referr = '';
			
			if( isset($_SERVER['HTTP_REFERER']) ) { $referr = $_SERVER['HTTP_REFERER']; }
			if( $default_referr ) { $referr = $default_referr; }
			
			$referr = esc_sql(strip_tags($referr) );
			
			if( !$referr ) { $referr = get_bloginfo('url'); }
			
			return $referr;
		}
		
		public function Current_Date($format = 'Y-m-d H:i:s', $strtotime = null) {
		
			if( $strtotime ) {
				return date($format, strtotime("{$strtotime} day") ) ;
			} else {
				return date($format) ;
			}
		}
		
		public function Current_Date_i18n($format = 'Y-m-d H:i:s', $strtotime = null, $day=' day') {
		
			if( $strtotime ) {
				return date_i18n($format, strtotime("{$strtotime}{$day}") ) ;
			} else {
				return date_i18n($format) ;
			}
		}
		
		public function Check_Search_Engines ($search_engine_name, $search_engine = null) {
		
			if( strstr($search_engine, $search_engine_name) ) {
				return 1;
			}
		}
		
		public function Search_Engine_Info($url = false) {
		
			if(!$url) {
				$url = isset($_SERVER['HTTP_REFERER']) ? $this->get_Referred() : false;
			}
			
			if($url == false) {
				return false;
			}
			
			$parts = parse_url($url);
			
			$search_engines = wp_statistics_searchengine_list();
			
			foreach( $search_engines as $key => $value ) {
				$search_regex = wp_statistics_searchengine_regex($key);
				
				preg_match( '/' . $search_regex . '/', $parts['host'], $matches);
				
				if( isset($matches[1]) )
					{
					return $value;
					}
			}
			
			return array('name' => 'Unknown', 'tag' => '', 'sqlpattern' => '', 'regexpattern' => '', 'querykey' => 'q', 'image' => 'unknown.png' );
		}
		
		public function Search_Engine_QueryString($url = false) {
		
			if(!$url) {
				$url = isset($_SERVER['HTTP_REFERER']) ? $this->get_Referred() : false;
			}
			
			if($url == false) {
				return false;
			}
			
			$parts = parse_url($url);
			
			if( array_key_exists('query',$parts) ) { parse_str($parts['query'], $query); } else { $query = array(); }
			
			$search_engines = wp_statistics_searchengine_list();
			
			foreach( $search_engines as $key => $value ) {
				$search_regex = wp_statistics_searchengine_regex($key);
				
				preg_match( '/' . $search_regex . '/', $parts['host'], $matches);
				
				if( isset($matches[1]) )
					{
					if( array_key_exists($search_engines[$key]['querykey'], $query) ) {
						$words = strip_tags($query[$search_engines[$key]['querykey']]);
					}
					else {
						$words = '';
					}
				
					if( $words == '' ) { $words = 'No search query found!'; }
					return $words;
					}
			}
			
			return '';
		}
	}
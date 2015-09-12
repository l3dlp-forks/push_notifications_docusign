<?php
# Manages the app's cookies
#
	# Definitions for cookies
	define ("COOKIE_NOTIFY", "PushNotifyDocuSign"); # yes or no
	define ("COOKIE_NOTIFY_ID", "PushNotifyDocuSignID"); # unique id
	
class PND_cookies {
	public $cookie_notify = NULL;
	public $cookie_notify_id = NULL;
	public $cookie_notify_id_created = NULL; # did we newly create the cookie?
	
	function __construct() {
		$this->setup_id_cookie();
	}

	private function setup_id_cookie() {
		global $pnd_config;
		if (array_key_exists ( COOKIE_NOTIFY_ID , $_COOKIE ) && strlen($_COOKIE[COOKIE_NOTIFY_ID]) > 5) {
			$this->cookie_notify_id = $_COOKIE[COOKIE_NOTIFY_ID];
			setcookie(COOKIE_NOTIFY_ID, $this->cookie_notify_id, time()+60*60*24*365); # 1 year refresh
			$this->cookie_notify_id_created = false;
			return; ### Early return
		}
		# No cookie: create and set the id cookie
		$this->cookie_notify_id = md5(uniqid($pnd_config['cookie_salt'], true)); # see http://stackoverflow.com/a/1293860/64904
		setcookie(COOKIE_NOTIFY_ID, $this->cookie_notify_id, time()+60*60*24*365); # 1 year
		$this->cookie_notify_id_created = true;
	} 
	public function cookie_is_on() {
		# Are the cookies telling us that notification is on?
		$r = !$this->cookie_notify_id_created && array_key_exists ( COOKIE_NOTIFY, $_COOKIE ) && $_COOKIE[COOKIE_NOTIFY] === 'yes';
		return ($r);
	}
	public function set_cookie($on) {
		# sets the cookie to be 'yes' or 'no'
		setcookie(COOKIE_NOTIFY, $on ? 'yes' : 'no', time()+60*60*24*365); # 1 year
	}
}
	
	
	
	
	
	


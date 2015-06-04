<?php
# Uses the Google DataStore NoSQL db
#
# 
# The notify url records the information needed to send a notification.
# Since different browsers are using different information, we're encoding the
# info in the notify_url
# 
# Google notification format:  google_notify://notification_service_point:id
#
#
# DB schema
#   cookie_notify_id -- a unique id for an instance of a browser. Stored on the
#       browser as cookie "cookie_notify_id"
#   notify_url -- how to notify the browser instance with a specific cookie_notify_id
#   ds_account -- A DocuSign account number whose member wants to receive notifications
#   ds_email -- A DocuSign email for someone in the ds_account who wants notifications
#
#  Discussion: A specific DS email can work with more than one account. We want notifications
#  for all accounts for a person, so we have an additional row for each account.
#
#  Also, a given person can receive notifications at different browsers. Eg, their 
#  desktop browser and their mobile phone. So we also have additional rows for
#  for each of the person's browsers.
#
#  We are not allowing more than one person to send notifications to a given browser.
#  -- the browser should be private to a person to receive notifications.

	# Definitions for cookies
	define ("COOKIE_NOTIFY", "PushDemoNotify"); # yes or no
	define ("COOKIE_NOTIFY_ID", "PushDemoNotifyID"); # unique id
	
class PND_google_db {

	# private variables
	private $gds_client = NULL;
	private $gds_gateway = NULL;
	private $notify_db = NULL;
	private $cookie_notify = NULL;
	private $cookie_notify_id = NULL;
	private $cookie_notify_id_created = NULL; # did we newly create the cookie?
	
	function __construct() {
		// We'll need a Google_Client, use our convenience method
		$gds_client = GDS\Gateway::createGoogleClient(GDS_APP_NAME, GDS_SERVICE_ACCOUNT_NAME, GDS_KEY_FILE_PATH);

		// Gateway requires a Google_Client and Dataset ID
		$gds_gateway = new GDS\Gateway($obj_client, GDS_DATASET_ID);

		$notify_db = new NotifyDB($gds_gateway);
		setup_id_cookie();
	}

	private function setup_id_cookie() {
		global $pnd_config;
		if (array_key_exists ( COOKIE_NOTIFY_ID , $_COOKIE ) && strlen($_COOKIE[COOKIE_NOTIFY_ID]) > 5) {
			$cookie_notify_id = $_COOKIE[COOKIE_NOTIFY_ID];
			$cookie_notify_id_created = false;
			return;
		}
		# No cookie: create and set the id cookie
		$cookie_notify_id = md5(uniqid($pnd_config['cookie_salt'], true)); # see http://stackoverflow.com/a/1293860/64904
		setcookie(COOKIE_NOTIFY_ID, $cookie_notify_id, time()+60*60*24*365); # 1 year
		$cookie_notify_id_created = true;
	} 
	private function cookie_on() {
		# Are the cookies telling us that notification is on?
		return (!$cookie_notify_id_created && 
			array_key_exists ( COOKIE_NOTIFY, $_COOKIE ) &&
			$_COOKIE[COOKIE_NOTIFY] === 'yes');
	}
	
	public function refresh ($notify_url) {
		# Whenever the page is loaded, if the service worker is already installed,
		# we need to update our db since the notification url may have changed. 
		#
		# If cookie_notify is on and the cookie_notify_id is present,
		# then update the db entries with the new notify_url
		#
		# If cookie_notify is off or missing, then remove any db entries that
		# use this notify_url
		if (cookie_on()) {
			$notifications = 
				$notify_db->fetchAll("SELECT * FROM Notifications WHERE cookie_notify_id = @id",
				['id' => $cookie_notify_id]);
			foreach($notifications as $notification) {
				$notification->notify_url = $notify_url;
				$notify_db->upsert($notification); # see https://github.com/tomwalder/php-gds/blob/master/src/GDS/Store.php
			}
		}
		
		
	}
}
	
class Notify extends GDS\Entity {}
class NotifyDB extends GDS\Store {
    /**
     * Build and return a Schema object describing the data model
     *
     * @return \GDS\Schema
     */
    protected function buildSchema()
    {
        $this->setEntityClass('\\Notifications');
        return (new GDS\Schema('Notifications'))
            ->addString('cookie_notify_id')
            ->addString('notify_url')
            ->addString('ds_account')
			->addString('ds_email');
    }
}
	
	
	
	
	
	


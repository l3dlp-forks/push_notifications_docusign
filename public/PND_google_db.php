<?php
# Uses the Google DataStore NoSQL db
#
# 
# The notify url records the information needed to send a notification.
# Since different browsers are using different information, we're encoding the
# info in the subscription_url
# 
# Google notification format:  google_notify://notification_service_point:id
#
#
# DB schema
#   cookie_notify_id -- a unique id for an instance of a browser. Stored on the
#       browser as cookie "cookie_notify_id"
#   	cookie_notify_id is indexed
#   ds_account_id -- A DocuSign account number whose member wants to receive notifications
#		ds_account_id is indexed  
#
#   Primary Key: since only one person can register at a given browser, cookie_notify_id and ds_account_id
#	     create a unique combined key.
#   ds_email -- A DocuSign email for someone in the ds_account who wants notifications
#		ds_email is indexed  
#	ds_account_name -- the account's name
#   subscription_url -- how to notify the browser instance
#   subscription_browser -- the type of browser
#   	-- Since the browser manufacturers seem to be diverging, this is browser type.
#          Currently only "Chrome" is supported.
#	ds_user_name
#	ds_user_id
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
	define ("COOKIE_NOTIFY", "PushNotifyDocuSign"); # yes or no
	define ("COOKIE_NOTIFY_ID", "PushNotifyDocuSignID"); # unique id
	
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
		$this->gds_client = GDS\Gateway::createGoogleClient(GDS_APP_NAME, GDS_SERVICE_ACCOUNT_NAME, GDS_KEY_FILE_PATH);

		// Gateway requires a Google_Client and Dataset ID
		$this->gds_gateway = new GDS\Gateway($this->gds_client, GDS_DATASET_ID);

		$this->notify_db = new NotifyDB($this->gds_gateway);
	}
	
	public function refresh ($subscription_url) {
		# Whenever the page is loaded, if the service worker is already installed,
		# we need to update our db since the notification url may have changed. 
		#
		$notifications = 
			$notify_db->fetchAll("SELECT * FROM Notifications WHERE cookie_notify_id = @id",
			['id' => $this->cookie_notify_id]);

		if (cookie_on()) {
			# If cookie_notify is on and the cookie_notify_id is present,
			# then update the db entries with the new subscription_url
			foreach($notifications as $notification) {
				$notification->subscription_url = $this->subscription_url;
				$this->notify_db->upsert($notification); # see https://github.com/tomwalder/php-gds/blob/master/src/GDS/Store.php
			}
			set_cookie_notify(true);
		} else {
			# If cookie_notify is off or missing, then remove any db entries that
			# use this subscription_url
			foreach($notifications as $notification) {
				$this->notify_db->delete($notification); # see https://github.com/tomwalder/php-gds/blob/master/src/GDS/Store.php
			}
			set_cookie_notify(false);
		}		
	}
	
	# Function Subscribe
	#
	# params is an associative array with elements:
	# subscription_url
	# subscription_browser
	# cookie_notify_id
	# ds_account_id
	# ds_account_name
	# ds_email
	# ds_user_name
	# ds_user_id
	public function subscribe ($params) {
		# If a notification exists, update it.
		$notifications = $this->get_all_notifications_account_cookie ($params['ds_account_id'], $params['cookie_notify_id']);

		if (count($notifications) > 1) {
			throw new Exception("More than one notification for " . $params['ds_account_id'] . " and this cookie id.");
		}
		if (count($notifications) == 1) {
			$notification = $notification[0];
		} else {
			$notification = $this->notify_db->createEntity();
		}
		
		# create/update the record
        $notification->subscription_url = $params['subscription_url'];
		$notification->subscription_browser = $params['subscription_browser'];
        $notification->cookie_notify_id = $params['cookie_notify_id'];
        $notification->ds_account_id = $params['ds_account_id'];
        $notification->ds_account_name = $params['ds_account_name'];
		$notification->ds_email = $params['ds_email'];
		$notification->ds_user_name = $params['ds_user_name'];
		$notification->ds_user_id = $params['ds_user_id'];
		$bol_result1 = $this->notify_db->upsert($notification);
	}
	
	public function getAllNotificationsAccountCookie ($ds_account_id, $cookie_notify_id) {
	  return $this->notify_db->fetchAll(
		"SELECT * FROM Notifications WHERE cookie_notify_id = @cookie_notify_id AND ds_account_id = @ds_account_id",
		['cookie_notify_id' => $cookie_notify_id,
		'ds_account_id' => $ds_account_id]);
	}

	public function test() {
		$notification = $this->notify_db->createEntity([
			'subscription_url' => 'url',
			'cookie_notify_id' => 'cookie id',
			'ds_account_id' => 'account id',
			'ds_account_name' => 'account name',
			'ds_email' => 'email@foo.com',
			'ds_user_name' => 'Joe User',
			'ds_user_id' => 'user id'
		]);
		$bol_result1 = $this->notify_db->upsert($notification);
		echo "Store result: ";
		var_dump($bol_result1);
	
		// Fetch all (client 1)
		$notifications = $this->notify_db->fetchAll("SELECT * FROM Notifications");
		echo "Query found ", count($notifications), " records", PHP_EOL;
		foreach($notifications as $notification) {
			echo "   Notify url: {$notification->subscription_url}, email: {$notification->ds_email}", PHP_EOL;
		}
	}
	
	
	public function notifications () {
		# Get the notifications for this id
		$_notifications = 
			$this->notify_db->fetchAll("SELECT * FROM Notifications WHERE cookie_notify_id = @id",
			['id' => $this->cookie_notify_id]);
		
		$results = array();
		foreach($_notifications as $notification) {
			$results[] = array(
				'subscription_url' => $notification->subscription_url,
				'subscription_browser' => $notification->subscription_browser,
				'cookie_notify_id' => $notification->cookie_notify_id,
				'ds_account_id' => $notification->ds_account_id,
				'ds_account_name' => $notification->ds_account_name,
				'ds_email' => $notification->ds_email,
				'ds_user_name' => $notification->ds_user_name,
				'ds_user_id' => $notification->ds_user_id
			);
		}
		return ($results);
	}
}

class Notifications extends GDS\Entity {}
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
            ->addString('subscription_url')
			->addString('subscription_browser')
            ->addString('ds_account_id')
            ->addString('ds_account_name')
			->addString('ds_email')
			->addString('ds_user_name')
			->addString('ds_user_id');
    }
}
	
	
	
	
	
	


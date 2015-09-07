<?php
# Uses the Google DataStore NoSQL db
#
# 
# The notify url records the information needed to send a notification.
#
# DB schema
#   instance_id -- a unique id for an instance of a browser/subscriber. Stored on the
#       browser as cookie
#   ds_account_id -- A DocuSign account number whose member wants to receive notifications
#		ds_account_id is indexed  
#
#   Primary Key: since only one person can register at a given browser, instance_id and ds_account_id
#	     create a unique combined key.
#   ds_email -- A DocuSign email for someone in the ds_account who wants notifications
#		ds_email is indexed  
#	ds_account_name -- the account's name
#	ds_account_admin_email -- the admin email used to create the record
#   subscription_url -- how to notify the browser instance
#   subscription_type -- the type of browser
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
	
class PND_google_db {

	# private variables
	private $gds_client = NULL;
	private $gds_gateway = NULL;
	private $notify_db = NULL;
	
	function __construct() {
		// We'll need a Google_Client, use our convenience method
		$this->gds_client = GDS\Gateway::createGoogleClient(GDS_APP_NAME, GDS_SERVICE_ACCOUNT_NAME, GDS_KEY_FILE_PATH);

		// Gateway requires a Google_Client and Dataset ID
		$this->gds_gateway = new GDS\Gateway($this->gds_client, GDS_DATASET_ID);

		$this->notify_db = new NotifyDB($this->gds_gateway);
	}
	
	public function refresh ($subscription_url, $instance_id, $cookie_on) {
		# Whenever the page is loaded, if the service worker is already installed,
		# we need to update our db since the notification url may have changed. 
		#
		# RETURNS results[] -- elements:
		# 	'ok' -- use this to set the cookie.
		#   'accounts' -- array of account information
		$results = array('accounts' => array());
		
		$notifications = 
			$this->notify_db->fetchAll("SELECT * FROM Notifications WHERE instance_id = @id",
			['id' => $instance_id]);

		if ($cookie_on) {
			# If cookie_notify is on and the instance_id is present,
			# then update the db entries with the new subscription_url
			foreach($notifications as $notification) {
				$notification->subscription_url = $subscription_url;
				$notification->ds_email = strtolower ($notification->ds_email);
				
				$this->notify_db->upsert($notification); # see https://github.com/tomwalder/php-gds/blob/master/src/GDS/Store.php
				$results['accounts'][] = array(
					'user_name' => $notification->ds_user_name,
					'user_email' => $notification->ds_email,
					'user_id' => $notification->ds_user_id,
					'account_name' => $notification->ds_account_name,
					'account_id' => $notification->ds_account_id,
					'account_admin_email' => $notification->ds_account_admin_email);
			}
			
			$results['ok'] = true;
			return $results;
		} else {
			# If cookie_notify is off or missing, then remove any db entries that
			# use this subscription_url
			foreach($notifications as $notification) {
				$this->notify_db->delete($notification); # see https://github.com/tomwalder/php-gds/blob/master/src/GDS/Store.php
			}
			$results['ok'] = false;
			return $results;
		}		
	}
	
	# Function Subscribe
	#
	# params is an associative array with elements:
	# subscription_url
	# subscription_type
	# instance_id
	# ds_account_id
	# ds_account_name
	# ds_email
	# ds_user_name
	# ds_user_id
	public function subscribe ($params) {
		# If a notification exists, update it.
		$notifications = $this->getAllNotificationsAccountInstance($params['ds_account_id'], $params['instance_id']);

		if (count($notifications) > 1) {
			throw new Exception("More than one notification for " . $params['ds_account_id'] . " and this cookie id.");
		}
		if (count($notifications) === 1) {
			$notification = $notifications[0];
		} else {
			$notification = $this->notify_db->createEntity();
		}
		
		# create/update the record
        $notification->subscription_url = $params['subscription_url'];
		$notification->subscription_type = $params['subscription_type'];
        $notification->instance_id = $params['instance_id'];
        $notification->ds_account_id = $params['ds_account_id'];
        $notification->ds_account_name = $params['ds_account_name'];
        $notification->ds_account_admin_email = $params['ds_account_admin_email'];
		$notification->ds_email = strtolower ($params['ds_email']);
		$notification->ds_user_name = $params['ds_user_name'];
		$notification->ds_user_id = $params['ds_user_id'];
		return $this->notify_db->upsert($notification); # boolean
	}
	
	public function getAllNotificationsAccountInstance ($ds_account_id, $instance_id) {
	  return $this->notify_db->fetchAll(
		"SELECT * FROM Notifications WHERE instance_id = @instance_id AND ds_account_id = @ds_account_id",
		['instance_id' => $instance_id,
		'ds_account_id' => $ds_account_id]);
	}
	
	public function get_unique_subscriptions_for_email($email) {
	  # See https://cloud.google.com/datastore/docs/concepts/queries
	  
		$all = $this->notify_db->fetchAll(
			"SELECT * FROM Notifications WHERE ds_email = @ds_email",
			['ds_email' => $email]);
		# nb. Can't use Distinct keyword since we don't have the right index defined. Sigh.
		# Also ordering didn't work, neither did projecting. Double sigh...
		
		# We only want the unique subscription_type / subscription_url pairs
		$results = array();
		foreach ($all as $row) {
			if (! $this->in_subscription_array($results, $row->subscription_type, $row->subscription_url)) {
				$results[] = array('subscription_type' => $row->subscription_type, 
									'subscription_url' => $row->subscription_url)
			}
		}
		return $results;
	}
	
	private function in_subscription_array($a, $subscription_type, $subscription_url) {
		foreach ($a as $item) {
			if ($item['subscription_type'] === $subscription_type &&
				$item['subscription_url'] === $subscription_url) {
					return true;
				}
		}
		return false;
	}

	public function test() {
		$notification = $this->notify_db->createEntity([
			'subscription_url' => 'url',
			'instance_id' => 'cookie id',
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
	
	
	public function notifications ($instance_id) {
		# Get the notifications for this id
		$_notifications = 
			$this->notify_db->fetchAll("SELECT * FROM Notifications WHERE instance_id = @id",
			['id' => $instance_id]);
		
		$results = array();
		foreach($_notifications as $notification) {
			$results[] = array(
				'subscription_url' => $notification->subscription_url,
				'subscription_type' => $notification->subscription_type,
				'instance_id' => $notification->instance_id,
				'ds_account_id' => $notification->ds_account_id,
				'ds_account_name' => $notification->ds_account_name,
				'ds_account_admin_email' => $notification->ds_account_admin_email,
				'ds_email' => $notification->ds_email,
				'ds_user_name' => $notification->ds_user_name,
				'ds_user_id' => $notification->ds_user_id
			);
		}
		return ($results);
	}

	public function delete($instance_id) {
		# Delete all the notifications for this id
		$notifications = 
			$this->notify_db->fetchAll("SELECT * FROM Notifications WHERE instance_id = @id",
			['id' => $instance_id]);
		
		$this->notify_db->delete($notifications);
	}
	
	// returns TRUE if there are 2 or more notifications for the account/user
	public function multiple_user_notifications($account_id, $user_id) {
		$notifications = $this->notify_db->fetchAll(
			"SELECT * FROM Notifications WHERE ds_account_id = @account AND ds_user_id = @user",
			['account' => $account_id, 'user' => $user_id]);
		return(count($notifications) > 1);	
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
            ->addString('instance_id')
            ->addString('subscription_url')
			->addString('subscription_type')
            ->addString('ds_account_id')
            ->addString('ds_account_name')
			->addString('ds_account_admin_email')
			->addString('ds_email')
			->addString('ds_user_name')
			->addString('ds_user_id');
    }
}
	
	
	
	
	
	


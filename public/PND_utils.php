<?php
if (!defined('APP')) {exit("Buzz off");}
 
define("CONNECTION_NAME_PREFIX", 'Connection Gateway Example--');
define("WEBHOOK_SUFFIX", '?op=webhook');

 
class PND_utils {
  private $_pnd_google_db = null;
  private $_pnd_google_log = null;
  private $_pnd_file_utils = null;
  private $_ds_client = null;
  private $_ds_connect_service = null;
  
  public function return_data( $data, $code = 200 ) {
	http_response_code($code);  # in php 5.4 and later
	header('Content-Type: application/json');
	if ($data !== null) {
		echo json_encode($data);
	} else {
		echo "{}"; // empty json
	}
  }
  
  public function pnd_file_utils() {
	if (! $this->_pnd_file_utils) {
		$this->_pnd_file_utils = new PND_file_utils();
	}
    return $this->_pnd_file_utils;
  }
  
  public function pnd_google_db() {
	if (! $this->_pnd_google_db) {
		$this->_pnd_google_db = new PND_google_db();
	}
    return $this->_pnd_google_db;
  }

  public function log($severity, $subject, $details) {  # severity: debug, warning, critical
    $this->pnd_google_log()->log($severity, $subject, $details);
  }
	
  private function pnd_google_log() {
	if (! $this->_pnd_google_log) {
		$this->_pnd_google_log = new PND_google_log();
	}
    return $this->_pnd_google_log;
  }
  
  private function connection_name() {
	global $pnd_config;
	return CONNECTION_NAME_PREFIX . $pnd_config['instance_id'];
  }
  
  public function new_docusign_client($email = true, $pw = true, $account = false) {
	global $pnd_config;
	global $pnd_api;
		
	if ($email === true && $pw === true) {
		$email = $pnd_api->email();
		$pw = $pnd_api->pw();
	}
	
	$ds_config = array(
		'integrator_key' => $pnd_config["docusign_integrator_key"], 
		'email' => $email,
		'password' => $pw,
		'version' => $pnd_config["docusign_version"],
		'environment' => $pnd_config["docusign_environment"],
	);
	if ($account) {
		$ds_config['account_id'] = $account;
	}	
	$ds = new DocuSign_Client($ds_config);
	return $ds;
  }
  
  public function account_admin($accountId, $userId, $email = true, $pw = true) {
 	# true/false is this user an admin
	$ds_client = $this->new_docusign_client($email, $pw, $accountId); # create a new client for the specific account
	$service = new DocuSign_UserService($ds_client);
	$user_settings = $service->user->getUserSettingList($userId);
	$this->good_results($user_settings, "userSettings", 'admin_accounts: bad user_settings from DS.');	
	return $this->is_admin($user_settings);
  }
  
  public function is_admin($user_settings) {
    # parameter is returned data from User Setting List
	foreach ($user_settings->userSettings as $setting) {
		if ($setting->name === "canManageAccount") {
			return $setting->value === "true";  # admin? (values are not converted.)
		}
	}
	throw new UnexpectedValueException('is_admin: no admin value from DS.'); # trouble in river city!
	return false;
  }
   
  public function good_results($obj, $property, $msg) {
	if (! ( is_object($obj) && property_exists  ( $obj, $property ))) {
		throw new UnexpectedValueException($msg); # trouble in river city!
		return false;
	}
	return true;
  }  
  
  private function get_ds_client(){
	if ($this->_ds_client === null) {
		$this->_ds_client = $this->new_docusign_client();
	}
	return $this->_ds_client;
  }
  
  private function get_ds_connect_service(){
	if ($this->_ds_connect_service === null) {
		$s = new DocuSign_ConnectService($this->get_ds_client());
		$this->_ds_connect_service = $s->connect;
	}
	return $this->_ds_connect_service;
  }
  
  # returns the url for the incoming web_hook calls
  private function web_hook_url(){
	$uri = explode ('?', $_SERVER['REQUEST_URI'])[0];
	$this->log('debug', 'web_hook_url', $_SERVER['REQUEST_URI'] . ' ==> ' . $uri);
	return "https://".$_SERVER['SERVER_NAME']. $uri . WEBHOOK_SUFFIX;
  }
  
  # Update or Insert a connection for the account and userId
  public function upsert_connection($accountId, $userId, $emailpws) {
	# emailpws is a limited list of email and pw for specific accounts. If $accountId is in
	# emailpws then use that email pw. Otherwise use the default credentials.
	
	$r = $this->find_account_in_emailpws($emailpws, $accountId);
	$email = $r['email'];
	$pw = $r['pw'];

	if ($email === null && $pw === null) {
		# use default creds
		$connect_service = $this->get_ds_connect_service();
	} else {
		# use supplied creds
		$ds_client = $this->new_docusign_client($email, $pw, $accountId);
		$s = new DocuSign_ConnectService($ds_client);	
		$connect_service = $s->connect;	
	}
	
	$connection = $this->find_connection($accountId, $connect_service);
	if ($connection) {
		# Our new userId shouldn't be in the connection. But we'll be 
		# conservative and make sure that it is not there.
		$userIds = $connection->userIds;
		if (!in_array ($userId, $userIds, true)) {
			$userIds[] = $userId; # add the new user id
			$connect_service->updateConnectConfiguration(	
				$accountId, # string	Account Id
				$connection->connectId, # string	Connection Id
				$params = array('userIds' => $userIds));
		}
	} else {
		$params = array(
			'urlToPublishTo' => $this->web_hook_url(), # Required. string	Client's incoming webhook url
			'allUsers' => false,	# boolean	Track events initiated by all users.
			'allowEnvelopePublish' => true, # boolean	Enables users to publish processed events.
			'enableLog' => true, # boolean	Enables logging on prcoessed events. Log only maintains the last 100 events.
			'envelopeEvents' => array('Sent', 'Delivered', 'Signed', 'Completed', 'Declined', 'Voided'), # Envelope related events to track.
			'includeDocuments' => true, # boolean	Include envelope documents
			'includeSenderAccountasCustomField' => true, # boolean	Include sender account as Custom Field.
			'includeTimeZoneInformation' => true, # boolean	Include time zone information.
			'name' => $this->connection_name(), # string	name of the connection
			'recipientEvents' => array('Sent', 'Delivered', 'Completed', 'Declined', 'AuthenticationFailed', 'AutoResponded'), # Recipient events to track
			'requiresAcknowledgement' => false, # boolean	true or false
			'signMessagewithX509Certificate' => false,	# boolean	Signs message with an X509 certificate.
			'useSoapInterface' => false, # boolean	Set to true if the urlToPublishTo is a SOAP endpoint
			'userIds' => array($userId) # user Id's array. Required if allUsers is false
		);
		$connect_service->createConnectConfiguration(	
			$accountId, # string	Account Id
			$params);
	}
  }
  
  public function find_account_in_emailpws($emailpws, $accountId) {
	// returns array with email and pw fields.
	$r = array('email' => null, 'pw' => null);
	foreach ($emailpws as $emailpw) {
		if ($emailpw['accountId'] === $accountId &&
			strlen($emailpw['email']) > 2 &&
			strlen($emailpw['pw']) > 2) {
				$r['email'] = $emailpw['email'];
				$r['pw'] = $emailpw['pw'];
		}
	}
	return $r;
  }
 
  private function find_connection($accountId, $connect_service) {
	$connections = $connect_service->getConnectConfiguration($accountId);
	$result = false;
	$name = $this->connection_name();
	foreach ($connections->configurations as $configuration) {
		if ($configuration->name === $name) {
			return $configuration;
		}
	}
	return false; # didn't find anything
  }
 
  public function remove_connection($account_id, $user_id, $account_admin_email, $account_admin_pw){
	$ds_client = $this->new_docusign_client($account_admin_email, $account_admin_pw, $accountId);
	$s = new DocuSign_ConnectService($ds_client);	
	$connect_service = $s->connect;	
	
	$connection = $this->find_connection($account_id, $connect_service);
	if (! $connection) {
		return;
	}
	
	# Remove user_id from the connection only if there less than two 
	# Google notification instance for the account/user_id
	if ($this->pnd_google_db()->multiple_user_notifications($account_id, $user_id)) {
		return;
	}
	
	$userIds = $connection->userIds;
	$user_key = array_search($user_id, $userIds, true);
	if ($user_key === false) {
		return; # nuthin' here!
	} else {
		if (count($userIds) === 1) {
			$connect_service->deleteConnectConfiguration(	
				$account_id, # string	Account Id
				$connection->connectId # string	Connection Id
			);
		} else {
			array_splice($userIds, $user_key, 1);
			$connect_service->updateConnectConfiguration(	
				$account_Id, # string	Account Id
				$connection->connectId, # string	Connection Id
				$params = array('userIds' => $userIds));
		}
	}
  }
 
 
	
}

$pnd_utils = new PND_utils;

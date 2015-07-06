<?php
if (!defined('APP')) {exit("Buzz off");}
 
define("CONNECTION_NAME_PREFIX", 'Browser Push Notification - ');
define("WEBHOOK_SUFFIX", '?op=webhook');

 
class PND_utils {
  private $_pnd_google_db = null;
  private $_ds_admin_client = null;
  private $_ds_connect_service = null;
  
  public function return_data( $data, $code = 200 ) {
	http_response_code($code);  # in php 5.4 and later
	header('Content-Type: application/json');
	echo json_encode($data);
  }
  
  public function pnd_google_db() {
	if (! $_pnd_google_db) {
		$_pnd_google_db = new PND_google_db();
	}
    return $_pnd_google_db;
  }
  
  private function connection_name() {
	global $pnd_config;
	return CONNECTION_NAME_PREFIX . $pnd_config['instance_id'];
  }
  
  public function new_docusign_client($email = true, $pw = true, $account = false) {
	global $pnd_config;
	if ($email === true && $pw === true) {
		# use admin credentials
		$email = $pnd_config["docusign_admin_email"];
		$pw = $pnd_config["docusign_admin_pw"];
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
  
  public function admin_accounts() {
	# return array of the accounts that the admin user has access to
	$ds_client = $this->new_docusign_client();
    $service = new DocuSign_LoginService($ds_client);
	$login_info = $service->login->getLoginInformation();
	$this->good_results($login_info, "loginAccounts", 'admin_accounts: bad login_info from DS.');	
	$accounts_raw = array(); # array of {user_id=> x, account_id=> y}
	foreach($login_info->loginAccounts as $account_info) {
		$accounts_raw[] = array("account_id" => $account_info->accountId, "user_id" => $account_info->userId);
	}
	
	# next find accounts where the admin user has admin rights
	$accounts = array();
	foreach($accounts_raw as $account_user) {
		$ds_client = $this->new_docusign_client(true, true, $account_user["account_id"]); # create a new client for the specific account
		$service = new DocuSign_UserService($ds_client);
		$user_settings = $service->user->getUserSettingList($account_user["user_id"]);
		$this->good_results($user_settings, "userSettings", 'admin_accounts: bad user_settings from DS.');	
		if ($this->is_admin($user_settings)) { $accounts[] = $account_user["account_id"]; }
	}
	return $accounts;
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
  
  private function get_ds_admin_client(){
	if ($this->_ds_admin_client === null) {
		$this->_ds_admin_client = $this->new_docusign_client();
	}
	return $this->_ds_admin_client;
  }
  
  private function get_ds_connect_service(){
	if ($this->_ds_connect_service === null) {
		$s = new DocuSign_ConnectService($this->get_ds_admin_client());
		$this->_ds_connect_service = $s->connect;
	}
	return $this->_ds_connect_service;
  }
  
  # returns the url for the incoming web_hook calls
  private function web_hook_url(){
	return "http".(!empty($_SERVER['HTTPS'])?"s":"") . "://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] . WEBHOOK_SUFFIX;
  }
  
  # Update or Insert a connection for the account and user_id
  public function upsert_connection($accountId, $userId) {
	$connect_service = get_ds_connect_service(); 
	$connection = $this->find_connection($accountId);
	if ($connection) {
		$userIds = $connection->userIds;
		# Our new userId shouldn't be in the connection. But we'll be 
		# conservative and make sure that it is not there.
		if (!in_array ($userId, $userIds, true)) {
			$user_ids[] = $userId; # add the new user id
			$connect_service->updateConnectConfiguration(	
				$accountId, # string	Account Id
				$connection->connectId, # string	Connection Id
				$params = array(user_ids => $user_ids));
		}
	} else {
		$params = array(
			'urlToPublishTo' => $this->web_hook_url(), # Required. string	Client's incoming webhook url
			'allUsers' => false,	# boolean	Track events initiated by all users.
			'allowEnvelopePublish' => true, # boolean	Enables users to publish processed events.
			'enableLog' => true, # boolean	Enables logging on prcoessed events. Log only maintains the last 100 events.
			'envelopeEvents' => array('Sent', 'Delivered', 'Signed', 'Completed', 'Declined', 'Voided'), # Envelope related events to track.
			'includeDocuments' => false, # boolean	Include envelope documents
			'includeSenderAccountasCustomField' => true, # boolean	Include sender account as Custom Field.
			'includeTimeZoneInformation' => true, # boolean	Include time zone information.
			'name' => $this->connection_name(), # string	name of the connection
			'recipientEvents' => array('Sent', 'Delivered', 'Completed', 'Declined', 'AuthenticationFailed', 'AutoResponded'), # Recipient events to track
			'requiresAcknowledgement' => false, # boolean	true or false
			'signMessagewithX509Certificate' => false,	# boolean	Signs message with an X509 certificate.
			'useSoapInterface' => false, # boolean	Set to true if the urlToPublishTo is a SOAP endpoint
			'userIds' => array($user_id) # array list of user Id's. Required if allUsers is false
		);
		$connect_service->createConnectConfiguration(	
			$accountId, # string	Account Id
			$params);
	}
  }
 
  private function find_connection($accountId) {
	$connect_service = get_ds_connect_service(); 
	$connections = $connect_service->getConnectConfiguration($accountId);
	$result = false;
	$name = $this->connection_name();
	foreach ($connections->configurations as $configuration) {
		if ($configuration['name'] === $name) {
			return $configuration;
		}
		return false; # didn't find anything
	}
  }
 
	
}

$pnd_utils = new PND_utils;

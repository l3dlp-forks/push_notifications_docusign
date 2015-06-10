<?php
if (!defined('APP')) {exit("Buzz off");}
 
class PND_utils {
  private $_pnd_google_db = null;
  
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
  
  public function new_docusign_client($email = true, $pw = true, $account = false) {
	global $pnd_config;
	if ($email === true && $pw === true) {
		# use admin credentials
		$email = $pnd_config["docusign_admin_email"];
		$ps = $pnd_config["docusign_admin_pw"];
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
  

	
}

$pnd_utils = new PND_utils;

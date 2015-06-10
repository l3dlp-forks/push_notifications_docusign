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
  
  public function new_docusign_client($email, $pw, $account = false) {
	global $pnd_config;
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
  
  public function available_accounts() {
	# return array of the accounts that the admin user has access to
	global $pnd_config;
	$ds_client = $this->new_docusign_client($pnd_config["docusign_admin_user"], $pnd_config["docusign_admin_pw"]);
    $service = new DocuSign_LoginService($ds_client);
	$login_info = $service->login->getLoginInformation();
	$accounts_raw = array(); # array of {user_id=> x, account_id=> y}
	foreach($login_info->loginAccounts as $account_info) {
		$accounts_raw[] = array("account_id" => $account_info->accountId, "user_id" => $account_info->userId);
	}
	
	# next find where he's an admin
	$accounts = array();
	foreach($accounts_raw as $account_user) {
		$ds_client = $this->new_docusign_client($pnd_config["docusign_admin_user"], $pnd_config["docusign_admin_pw"],
			$account_user["account_id"]); # create a new client
		$service = new DocuSign_UserService($ds_client);
		$user_settings = $service->getUserSettingList($account_user["user_id"]);
		if ($this->is_admin($user_settings)) { $accounts[] = $account_user["account_id"]; }
	}
	return $accounts;
  }
  
  public function is_admin($user_settings) {
    # parameter is returned data from User Setting List
	
	if (! ( is_object($user_settings) && property_exists  ( "userSettings" , $user_settings ))) {
		throw new UnexpectedValueException('is_admin: bad data from DS.'); # trouble in river city!
		return false;
	}
	
	foreach ($user_settings->userSettings as $setting) {
		if ($setting->name === "canManageAccount") {
			return $setting->value === true;  # admin?
		}
	}

	throw new UnexpectedValueException('is_admin: no admin value from DS.'); # trouble in river city!
	return false;
  }

	
}

$pnd_utils = new PND_utils;

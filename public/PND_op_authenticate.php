<?php
if (!defined('APP')) {exit("Buzz off");}

class PND_op_authenticate implements PND_Request
{
	# op = authenticate
	# args: email -- username
	#	pw -- password
	# RETURNS
	#   200 - good results:
	#   { accounts: [{account_name:
	#
	#   400 - bad request :
	#	{ bad_data: ['field_name1', 'field_name2'], msg: 'text' }

  public function request( $op )
  {
    global $pnd_utils, $pnd_api, $pnd_config;
	if ( $op != 'authenticate' ) {return false;}
	
	# check incoming
	if (! $pnd_api->check_email_pw()) {return true;}

	# authenticate with DocuSign
	$ds_client = $pnd_utils->new_docusign_client($pnd_api->email(), $pnd_api->pw());
	
	if( $ds_client->hasError()) {
		$msgs = array();
		$msgs = explode(": ", $ds_client->getErrorMessage(), 2);
		$msg = $msgs[0] === "USER_AUTHENTICATION_FAILED" ? $msgs[1] : $ds_client->getErrorMessage();
		
		$pnd_utils->return_data(
			array( 'api' => true, 'bad_data' => array('pw'), 'msg' => $msg), 400);
		return true;
	}

	$service = new DocuSign_LoginService($ds_client);
	$login_info = $service->login->getLoginInformation();
	$pnd_utils->good_results($login_info, "loginAccounts", 'authenticate api: bad login_info from DS.');	

	# $login_info:  {
    #   "loginAccounts": [
    #        {
    #            "name": "Accounts Ltd.",
    #            "accountId": "103111",
    #            "baseUrl": "https://demo.docusign.net/restapi/v2/accounts/103111",
    #            "isDefault": "true",
    #            "userName": "Larry Kluger",
    #            "userId": "14499850-43f1-4184-944f-xxx",
    #            "email": "foo@woof.bark.com",
    #            "siteDescription": ""
    #        }
    #    ]
    # }	

	$admin_accounts = $pnd_utils->admin_accounts();
	$accounts = array();
	$results = array('admin_email' => $pnd_config["docusign_admin_email"], 'accounts' => $accounts);
	# Each account item is an associative array with these fields:
	#	user_name
	# 	user_email
	#	user_id
	#	account_name
	#	account_id
	#	available  # true/false -- can notifications be received from the account?
	#		(Is our admin user an admin for this account?)
	foreach ($login_info->loginAccounts as $account_info) {
		$accounts[] = array(
			'user_name' => $account_info->userName,
			'user_email' => $account_info->email,
			'user_id' => $account_info->userId,
			'account_name' => $account_info->name,
			'account_id' => $account_info->accountId,
			'available' => in_array ($account_info->accountId, $admin_accounts, true));
	}
	
	$pnd_utils->return_data($results);

    return true;
  }
}


<?php
if (!defined('APP')) {exit("Buzz off");}

class PND_op_subscribe implements PND_Request
{
	# op = subscribe
	# args: 
	#	email 
	#	pw 
	#	subscription 
	#	browser  
	#
	# RETURNS
	#   200 - good results:
	#   { accounts: [{account_name:
	#
	#   400 - bad request :
	#	{ }

  public function request( $op )
  {
    global $pnd_utils, $pnd_api, $pnd_config;
	if ( $op != 'subscribe' ) {return false;}
	
	# parse incoming
	$params = $pnd_api->incoming_json();
	print_r ($params);

	# authenticate user with DocuSign
	$ds_client = $pnd_utils->new_docusign_client($pnd_api->email(), $pnd_api->pw());
	
	if( $ds_client->hasError()) {
		$msgs = array();
		$msgs = explode(": ", $ds_client->getErrorMessage(), 2);
		$msg = $msgs[0] === "USER_AUTHENTICATION_FAILED" ? $msgs[1] : $ds_client->getErrorMessage();
		
		$pnd_utils->return_data(
			array( 'api' => true, 'bad_data' => array('pw'), 'msg' => $msg), 400);
		return true;
	}

    # To prevent successful attacks, we don't trust the data from the user.
	# So next, ask DS what accounts the user is associated with.
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
	# Each account item is an associative array with these fields:
	#	user_name
	# 	user_email
	#	user_id
	#	account_name
	#	account_id
	#
	# We can only get connect info for the user's accounts where our admin
	# user is an account admin.
	foreach ($login_info->loginAccounts as $account_info) {
		if (in_array ($account_info->accountId, $admin_accounts, true)) {
			$accounts[] = array(
				'user_name' => $account_info->userName,
				'user_email' => $account_info->email,
				'user_id' => $account_info->userId,
				'account_name' => $account_info->name,
				'account_id' => $account_info->accountId);
		}
	}
	# accounts has the accounts that we should connect to for this user
	# 
	
	

    return true;
  }
}


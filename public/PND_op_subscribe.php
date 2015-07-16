<?php
if (!defined('APP')) {exit("Buzz off");}

class PND_op_subscribe implements PND_Request
{
	# op = subscribe
	# params: 
	#	email 
	#	pw 
	#	subscription 
	#	browser # type of browser. Currently just "Chrome"  
	#	emailpws -- array of emailpw records:
	#		accountId,
	#		email
	#		pw
	#
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
	$cookies = new PND_cookies();
	
	# parse incoming
	$params = $pnd_api->incoming_json(); # saves email and pw

	$cookies->set_cookie(true);

	# authenticate user with DocuSign
	$ds_client = $pnd_utils->new_docusign_client();
	
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
	$pnd_utils->good_results($login_info, "loginAccounts", 'subscribe api: bad login_info from DS.');	

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

	$emailpw_accounts = array(); # accounts we have a specific email/pw for administering
	foreach ($params['emailpws'] as $emailpw){$emailpw_accounts[] = $emailpw['accountId'];}
	
	$subscribed_accounts = array();
	# Each account item is an associative array with these fields:
	#	user_name
	# 	user_email
	#	user_id
	#	account_name
	#	account_id
	#
	# We can only do a connect for the user's accounts where our admin
	# user is an account admin or we were given an account-specific email/pw
	foreach ($login_info->loginAccounts as $loginAccount) {
		$accountId = $loginAccount->accountId;
		$user_is_an_admin = $pnd_utils->account_admin($accountId, $loginAccount->userId);
		if ($user_is_an_admin||in_array ($accountId, $emailpw_accounts, true)) {
			# Subscribe to the account
			#
			# Update or insert the connection to DocuSign DTM
			# Handle bad user name/pw and creds
			try {
				$pnd_utils->upsert_connection($loginAccount->accountId, $loginAccount->userId, $params['emailpws']);
			} catch (DocuSign_IOException $e) {
				$msg = $e->getMessage();
				$err_code = explode(": ", $msg, 2)[0];
				if ($err_code === "USER_NOT_ACCOUNT_ADMIN") {
					throw new DocuSign_IOException("USER_NOT_ACCOUNT_ADMIN: User is not an administrator for account " . $loginAccount->name);
				} elseif ($err_code === "USER_AUTHENTICATION_FAILED") {
					throw new DocuSign_IOException("USER_AUTHENTICATION_FAILED: Bad Username/Password for account " . $loginAccount->name . " administrator");
				} else {
					throw new DocuSign_IOException($e); # repeat the exception
				}
			}
			# Determine ds_account_admin_email
			$ds_account_admin_email = $pnd_utils->find_account_in_emailpws($emailpws, $accountId)['email'];
			if ($ds_account_admin_email === null) {$ds_account_admin_email = $pnd_api->email();}
			#
			# Store in Google Datastore
			$params2 = array(
				'subscription_url' => $params['subscription'],
				'subscription_type' => $params['browser'],
				'instance_id' => $cookies->cookie_notify_id,
				'ds_account_id' => $loginAccount->accountId,
				'ds_account_name' => $loginAccount->name,
				'ds_account_admin_email' => $ds_account_admin_email,
				'ds_email' => $loginAccount->email,
				'ds_user_name' => $loginAccount->userName,
				'ds_user_id' => $loginAccount->userId
			);			
			$status = $pnd_utils->pnd_google_db()->subscribe($params2);
			# Add to the results
			$subscribed_accounts[] = array(
				'user_name' => $loginAccount->userName,
				'user_email' => $loginAccount->email,
				'user_id' => $loginAccount->userId,
				'account_name' => $loginAccount->name,
				'account_id' => $loginAccount->accountId,
				'account_admin_email' => $ds_account_admin_email);
		}
	}
	$pnd_utils->return_data(array('accounts' => $subscribed_accounts), 200 );
	return true;
  }
}


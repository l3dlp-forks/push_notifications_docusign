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
    global $pnd_utils, $pnd_api;
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

	$service = new DocuSign_Service($ds_client);
	$login_info = $service->login->getLoginInformation();
	# $login_info:  {
    #   "loginAccounts": [
    #        {
    #            "name": "DocuSign",
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

	$accounts = $pnd_utils->available_accounts();
	
	$pnd_utils->return_data(array('msg' => 'getLoginInformation: ' , 'data' => ["login" => $login_info), "accounts" => $accounts);

    return true;
  }
}


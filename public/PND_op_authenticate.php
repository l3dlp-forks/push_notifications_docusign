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
    global $pnd_utils;
	if ( $op != 'authenticate' ) return false;
	
	# check incoming
	if (!isset($_POST['email']) || strlen($_POST['email']) < 1) {
		$pnd_utils->return_data( 
			array( 'api' => true, 'bad_data' =>array('email'), 'msg' => 'Please enter your email address' ), 400);
		return true;
	}
	if (!isset($_POST['pw']) || strlen($_POST['pw']) < 1) {
		$pnd_utils->return_data( 
			array( 'api' => true, 'bad_data' => array('pw'), 'msg' => 'Please enter your password' ), 400);
		return true;
	}

	# authenticate with DocuSign
	$email = $_POST['email'];
	$pw = $_POST['pw'];
	$ds_client = $pnd_utils->new_docusign_client($email, $pw);
	
	if( $ds_client->hasError() )
	{
		$pnd_utils->return_data(
			array( 'api' => true, 'bad_data' => array('pw'), 'msg' => 'DocuSign problem: ' .  $ds_client->getErrorMessage()), 400);
		return true;
	}

	$service = new DocuSign_LoginService($ds_client);
	$response = $service->login->getLoginInformation();	
	$pnd_utils->return_data(array('msg' => 'getLoginInformation: ' . var_export($response, true), 'data' => $response));

    return true;
  }
}


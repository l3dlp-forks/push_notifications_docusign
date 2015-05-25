<?php
if (!defined('APP')) {exit("Buzz off";}
 
class PND_op_authenticate implements PND_Request
{
	# op = authenticate
	# args: user -- username
	#	pw -- password
	# RETURNS
	#   200 - good results:
	#   { accounts: [{account_name:
	#
	#   400 - bad request :
	#	{ bad_data: ['field_name1', 'field_name2'], msg: 'text' }

  public function request( $op )
  {
    if ( $op != 'authenticate' ) return false;
		
	# check incoming
	if (!isset($_POST['user']) || strlen($_POST['user']) < 1) {
		$pnd_utils->return_error(400, 
			{ bad_data: ['user'], msg: 'Please enter your user name' });
		return true;
	}
	if (!isset($_POST['pw']) || strlen($_POST['pw']) < 1) {
		$pnd_utils->return_error(400, 
			{ bad_data: ['pw'], msg: 'Please enter your password' });
		return true;
	}

	# authenticate with DocuSign
	

    return true;
  }
}


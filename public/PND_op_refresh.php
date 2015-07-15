<?php
if (!defined('APP')) {exit("Buzz off");}

class PND_op_refresh implements PND_Request
{
	# Refresh the subscription. The subscription url may have changed. 
	# Update it in the db if it did.
	#
	# op = refresh
	# params: 
	#	subscription 
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
	if ( $op != 'refresh' ) {return false;}
	$cookies = new PND_cookies();
	
	# parse incoming
	$params = $pnd_api->incoming_json();
	$cookie_on = !$cookies->cookie_is_on();
	
	# Check record(s) in db. If cookie is off then the records will be deleted
	$results = $pnd_utils->pnd_google_db()->refresh($params['subscription'], $cookies->cookie_notify_id, $cookie_on);
	$cookies->set_cookie($results['ok']);

	if (!$cookie_on) {
		throw new Exception("Missing cookie."); # repeat the exception
	}

	$pnd_utils->return_data(array('accounts' => $results['accounts'], 200 );
	return true;
  }
}


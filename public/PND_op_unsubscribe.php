<?php
if (!defined('APP')) {exit("Buzz off");}

class PND_op_unsubscribe implements PND_Request
{
	# op = unsubscribe
	# params: 
	#	subscription: a subscription endpoint
	#	accounts: array of account info. Will be empty if this is an
	#	  implicit unsubscribe
	#	  Account fields
	#		user_name: account.user_name,
	#		user_email: account.user_email,
	#		user_id: account.user_id,
	#		account_name: account.account_name,
	#		account_id: account_id,
	#		account_admin_email: $('#ce' + account_id).val(),	# may not be right
	#		account_admin_pw: $('#cp' + account_id).val()		# may not be right
	#
	# RETURNS
	#   200 - good results:
	#
	#   400 - bad request :
	#	{ }

  public function request( $op )
  {
    global $pnd_utils, $pnd_api, $pnd_config;
	if ( $op != 'unsubscribe' ) {return false;}
	$cookies = new PND_cookies();
	
	# parse incoming
	$params = $pnd_api->incoming_json();
	$cookies->set_cookie(false);
	
	foreach ($params->accounts as $account) {
		try {
			# if a name and pw were supplied then try to use them...
			if ($account->account_admin_email && strlen($account->account_admin_email) > 2 &&
				$account->account_admin_pw && strlen($account->account_admin_pw) > 2) {
				$pnd_utils->remove_connection($account->account_id, $account->user_id, $account->account_admin_email, account->account_admin_pw);
			}
		} catch (DocuSign_IOException $e) {
			$msg = $e->getMessage();
			$err_code = explode(": ", $msg, 2)[0];
			if ($err_code === "USER_NOT_ACCOUNT_ADMIN") {
				throw new DocuSign_IOException("USER_NOT_ACCOUNT_ADMIN: " . $account->account_admin_email . " is not an admin for account " . $account->account_name);
			} elseif ($err_code === "USER_AUTHENTICATION_FAILED") {
				throw new DocuSign_IOException("USER_AUTHENTICATION_FAILED: Bad Username/Password for " . $account->account_admin_email);
			} else {
				throw new DocuSign_IOException($e); # repeat the exception
			}
		}
	}
	#
	# Delete from Google Datastore
	$status = $pnd_utils->pnd_google_db()->delete($cookies->cookie_notify_id);
	$pnd_utils->return_data(array(), 200 );
	return true;
  }
}


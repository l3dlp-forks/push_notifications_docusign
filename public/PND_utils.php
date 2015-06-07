<?php
if (!defined('APP')) {exit("Buzz off");}
 
class PND_utils {
  private $_pnd_google_db = null;
  
  public function return_data( $data, $code = 200 )
  {
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
  
  public function new_docusign_client($email, $pw, $account = false)
  {
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
}

$pnd_utils = new PND_utils;

<?php
if (!defined('APP')) {exit("Buzz off");}
 
class PND_utils
{
  public function return_data( $data, $code => 200 )
  {
	http_response_code($code);
	header('Content-Type: application/json');
	echo json_encode($data);
  }
  
  public function new_docusign_client($email, $pw, $account => false)
  {
	$ds_config = array(
		'integrator_key' => $config["docusign_integrator_key"], 
		'email' => $email,
		'password' => $pw,
		'version' => $config["docusign_version"],
		'environment' => $config["docusign_environment"],
	);
	if ($account) {
		$ds_config['account_id'] = $account;
	}	
	$ds = new mrferos\DocuSign_Client($ds_config);
	return $ds;
  }


  
}

$pnd_utils = new PND_utils;

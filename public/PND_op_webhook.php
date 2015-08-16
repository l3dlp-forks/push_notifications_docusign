<?php
if (!defined('APP')) {exit("Buzz off");}

class PND_op_webhook implements PND_Request
{
	# op = webhook
	# params: 
	#
	#
	# RETURNS
	#   200 - good results:
	#
	#   400 - bad request :
	#	{ }

  public function request( $op )
  {
    global $pnd_utils, $pnd_api, $pnd_config;
	if ( $op != 'webhook' ) {return false;}
	
	$pnd_utils->log('debug', 'Webhook incoming', '');  # severity: debug, warning, critical
	$filename = "connect_" . md5(uniqid($pnd_config['cookie_salt'], true)); # see http://stackoverflow.com/a/1293860/64904
	$pnd_utils->pnd_file_utils()->write_input_to_file($filename);
	$pnd_utils->log('debug', 'Webhook wrote file', 'File: ' . $filename);  # severity: debug, warning, critical
	
	
	$pnd_utils->return_data(null, 200);
	return true;
  }
}


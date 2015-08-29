<?php
if (!defined('APP')) {exit("Buzz off");}

class PND_op_webhook implements PND_Request
{
	# op = webhook
	# params: 
	#   query parameter (optional) test_sender = <email> // use test data for <email> sender
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
	$basename = "connect_" . md5(uniqid($pnd_config['cookie_salt'], true)); # see http://stackoverflow.com/a/1293860/64904

	$pnd_utils->pnd_file_utils()->gc_files_dir(); // garbage collect old files
	$directory = $pnd_utils->pnd_file_utils()->files_dir() . '/';
	
	$ds_connect_utils = new DocuSign_Connect_utils();
	
	# Are we in test mode?
	$test_sender = isset($_GET['test_sender']) && $_GET['test_sender']; 
	$input = $test_sender : '../assets/connect_example.xml' ? 'php://input';
	$result = $ds_connect_utils->load_connect_message($basename, $directory, $input);
	if (!$result) {
		$pnd_utils->log('critical', 'Webhook incoming', 'Bad parse result for xml');  # severity: debug, warning, critical
		$pnd_utils->return_data(null, 400);
		return true;
	}
	
	$pnd_utils->log('debug', 'Webhook xml file', $ds_connect_utils->get_xml_filename());  # severity: debug, warning, critical
	$pdf_filenames = $ds_connect_utils->get_pdf_filenames();
	foreach ($pdf_filenames as $key => $value) {
		$pnd_utils->log('debug', 'Webhook pdf file [' . $key . ']', $value);  # severity: debug, warning, critical
	}
	
	$pnd_utils->return_data(null, 200);
	return true;
  }
}


<?php
if (!defined('APP')) {exit("Buzz off");}

# Should we send a notification to the ACHolderEmail (if different from the Email field)?
define("SEND_TO_ACHOLDER", true); 


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
	
  private $ds_connect_utils = NULL;


  public function request( $op )
  {
    global $pnd_utils, $pnd_api, $pnd_config;
	if ( $op != 'webhook' ) {return false;}
	
	$pnd_utils->log('debug', 'Webhook incoming', '');  # severity: debug, warning, critical
	$basename = "connect_" . md5(uniqid($pnd_config['cookie_salt'], true)); # see http://stackoverflow.com/a/1293860/64904

	$pnd_utils->pnd_file_utils()->gc_files_dir(); // garbage collect old files
	$directory = $pnd_utils->pnd_file_utils()->files_dir() . '/';
	
	$this->ds_connect_utils = new DocuSign_Connect_utils();
	
	# Are we in test mode?
	$test_sender = isset($_GET['test_sender']) ? $_GET['test_sender'] : false; 
	$input = $test_sender ? 'assets/connect_example.xml' : 'php://input';
	$result = $this->ds_connect_utils->load_connect_message($basename, $directory, $input, $test_sender);
	if (!$result) {
		$pnd_utils->log('critical', 'Webhook incoming', 'Bad parse result for xml');  # severity: debug, warning, critical
		$pnd_utils->return_data(null, 400);
		return true;  ##### Early return
	}
	
	$pnd_utils->log('debug', 'Webhook xml file', $this->ds_connect_utils->get_xml_filename());  # severity: debug, warning, critical
	$pdf_filenames = $this->ds_connect_utils->get_pdf_filenames();
	foreach ($pdf_filenames as $key => $value) {
		$pnd_utils->log('debug', 'Webhook pdf file [' . $key . ']', $value);  # severity: debug, warning, critical
	}
	
	$this->process_xml();
	
	$pnd_utils->return_data(null, 200);
	return true;
  }

  private function process_xml() {
	# Send notification to the Email and (mayne) the ACHolderEmail 
    global $pnd_utils;
	$email = $this->ds_connect_utils->get_email();
	
	$start = microtime(true);
		$notifications = $this->notify($email);
	$time_elapsed_secs = microtime(true) - $start;
	if ($notifications > 0) {
		$pnd_utils->log('debug', 'Notification time', $time_elapsed_secs . ' sec for ' . $notifications . ' notification(s)');  # severity: debug, warning, critical
	}
	
	$ac_holder = $this->ds_connect_utils->get_ac_holder();
    if (SEND_TO_ACHOLDER && strcasecmp ($email, $ac_holder) !== 0 ) {
		$start = microtime(true);
			$notifications = $this->notify($ac_holder);
		$time_elapsed_secs = microtime(true) - $start;
		if ($notifications > 0) {
			$pnd_utils->log('debug', 'Notification time', $time_elapsed_secs . ' sec for ' . $notifications . ' notification(s)');  # severity: debug, warning, critical
		}
	}
  }
  
  private function notify($email) {
	# notify $email if we have one or more notification subscriptions for her
	# RETURNS notifications -- count of how many were found
	#
    global $pnd_utils, $pnd_api, $pnd_config;	
$pnd_utils->log('debug', 'Getting subscriptions', $email);  # severity: debug, warning, critical
	$notify_subscriptions = $pnd_utils->pnd_google_db()->get_unique_subscriptions_for_email($email);
	
$pnd_utils->log('debug', 'Got subscriptions', $email);  # severity: debug, warning, critical

	
	$notifications = count ($notify_subscriptions);
	if ($notifications === 0) {
		return $notifications; ### Early return
	}
	
$pnd_utils->log('debug', 'Entering foreach', $email);  # severity: debug, warning, critical
	foreach($notify_subscriptions as $subscription) {
		if (strcmp ($subscription->subscription_type, "chrome") === 0) {
			$this->send_chrome_notification($subscription, $email);
		} else {
			exit;
		}
	}
	return $notifications;
  }

  private function send_chrome_notification($subscription, $email) {
	# notifies via chrome
    global $pnd_utils, $pnd_config;

$pnd_utils->log('debug', 'Sending subscription', $email);  # severity: debug, warning, critical
	
	$url = $subscription->subscription_url;
	
	$ch = curl_init();
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json', 
		'Authorization: key=' . $pnd_config["google_api_key"]));
    curl_setopt( $ch, CURLOPT_URL, $url );
	$content = curl_exec( $ch );
    $response = curl_getinfo( $ch );
    curl_close ( $ch );
		
	$pnd_utils->log('debug', 'Sent Chrome notification', 'To: ' . $email . ' ' . $response . ': ' . $content);  # severity: debug, warning, critical
	
	}
	
	
  
  
  
  
}


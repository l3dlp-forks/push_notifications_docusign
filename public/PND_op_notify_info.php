<?php
if (!defined('APP')) {exit("Buzz off");}


class PND_op_notify_info implements PND_Request
{
	# op = notify_info
	#
	# Called by the service worker to learn more about the
	# notification.
	#
	# The incoming call must include a cooker that identifies
	# the instance--that enables the data lookup
	#
	# params: 
	#   id
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
	if ( $op != 'notify_info' ) {return false;}
	
	$cookie_id = array_key_exists ( "id" , $_GET ) ? $_GET["id"] : null;

	$pnd_utils->log('debug', 'Notify_info incoming', 'id = ' . $cookie_id);  # severity: debug, warning, critical

	$directory = $pnd_utils->pnd_file_utils()->files_dir() . '/';
	
	$pnd_utils->return_data([
		'cookie_id' => $cookie_id, 
		'error' => false,
		'notification' => [
			'title' => 'XYZ document signed!', 
			'message' => 'This is the message. Cookie: ' . $cookie_id . ' This is the message. This is the message. This is the message.' , 
			'icon' => true,
			'notificationTag' => 123
			]
		], 200);
	return true;

	
	
	

	$this->ds_connect_utils = new DocuSign_Connect_utils();
	
	# Are we in test mode?
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
  }
  
  private function notify($email) {
	# notify $email if we have one or more notification subscriptions for her
	# RETURNS notifications -- count of how many were found
	#
    global $pnd_utils, $pnd_api, $pnd_config;	
$pnd_utils->log('debug', 'Getting subscriptions', $email);  # severity: debug, warning, critical
	$notify_subscriptions = $pnd_utils->pnd_google_db()->get_unique_subscriptions_for_email($email);
		
	$notifications = count ($notify_subscriptions);
	if ($notifications === 0) {
		return $notifications; ### Early return
	}
		foreach($notify_subscriptions as $subscription) {
		if ($subscription['subscription_type'] === "Chrome") {
			$this->send_chrome_notification($subscription, $email);
		} else {
			throw new Exception('Unrecogonized subscription type: ' . $subscription->subscription_type);
		}
	}
	return $notifications;
  }

  private function send_chrome_notification($subscription, $email) {
	# notifies via chrome
    global $pnd_utils, $pnd_config;

$pnd_utils->log('debug', 'Sending subscription', $email);  # severity: debug, warning, critical
	
	$url = $subscription['subscription_url'];
	# Example url: https://android.googleapis.com/gcm/send/APA91bFyEk2E31i1-Gk1Ask9hO8ucO6xHGa0zQTImQH_0H
	# Follow section "Sending a Push Message" in
	#   https://developers.google.com/web/updates/2015/03/push-notificatons-on-the-open-web
	
	$chrome_endpoint = "https://android.googleapis.com/gcm/send";
	if (stripos($url, $chrome_endpoint) !== 0) {
		# Since we're sending to Chrome, the url should start with the Chrome endpoint.
		throw new Exception('Chrome endpoint not in url! url: ' . $url);
	}
	$parts = explode ("/" , $url);
	$reg_id = $parts[count($parts) - 1];
	$reg_data = array("registration_ids" => array($reg_id));
	$data_string = json_encode($reg_data);
	
	$ch = curl_init();
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
		'Content-type: application/json', 
		'Authorization: key=' . $pnd_config["google_api_key"],
		'Content-Length: ' . strlen($data_string)
		));
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_URL, $chrome_endpoint );
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);   
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
	
	$content = curl_exec( $ch );
    $response = curl_getinfo( $ch );
    curl_close ( $ch );
		
	$pnd_utils->log('debug', 'Sent Chrome notification', 'To: ' . $email . ' ' . $response['http_code'] . ': ' . $content);  # severity: debug, warning, critical
	
	}
	
	
  
  
  
  
}


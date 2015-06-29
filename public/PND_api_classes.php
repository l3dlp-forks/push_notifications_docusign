<?php

#	api classes for the Push Notifications DocuSign app
# The following chain-of-command pattern is from 
# http://www.ibm.com/developerworks/library/os-php-designptrns/

interface PND_Request
{
  function request( $op );
}

class PND_HandlerChain {
  private $_handlers = array();

  public function addHandler( $handler ) {
    $this->_handlers []= $handler;
  }

  public function handle( $op ) {
    global $pnd_utils;
	
	foreach( $this->_handlers as $handler ) {
      if ( $handler->request( $op ) )
        return;
    }
	
	# bad op
	$pnd_utils->return_data( 
		array( 'bad_data' => array(), 'msg' => 'Bad op' ), 501); # 501 - Not implemented
  }
}

class PND_API {
	# API Support
	private $_email = null;
	private $_pw = null;
	
	public function email() {return $this->_email;}
	public function pw() {return $this->_pw;}
  
	public function check_email_pw() {
		global $pnd_utils;
		if (!isset($_POST['email']) || strlen($_POST['email']) < 1) {
			$pnd_utils->return_data( 
				array( 'api' => true, 'bad_data' =>array('email'), 'msg' => 'Please enter your email address' ), 400);
			return false;
		}
		if (!isset($_POST['pw']) || strlen($_POST['pw']) < 1) {
			$pnd_utils->return_data( 
				array( 'api' => true, 'bad_data' => array('pw'), 'msg' => 'Please enter your password' ), 400);
			return false;
		}
		$this->_email = $_POST['email'];
		$this->_pw = $_POST['pw'];
		return true;
	}

	public function incoming_json() {
		$json = json_decode(file_get_contents("php://input"), true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return null;
		}
		
		if (array_key_exists('email', $json)) {$_email = $json['email'];}
		if (array_key_exists('pw', $json)) {$_pw = $json['pw'];}
		return $json
	}

	
}

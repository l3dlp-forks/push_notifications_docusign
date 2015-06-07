<?php
define("APP", "Push Notifications for DocuSign");
include (realpath(dirname(__FILE__) . '/public/bootstrap.php'));

/*	api file for the Push Notifications DocuSign app

	All requests include url parameter op.
	Some calls are GET, others are POST 
	Calls should only use HTTPS
	
	ops
	authenticate 
		POST
		params: email and pw
	
	
*/

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
	
	public function email() {return $_email;}
	public function pw() {return $_pw;}
  
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
		$_email = $_POST['email'];
		$_pw = $_POST['pw'];
		return true;
	}
}

##############################################################################
##############################################################################
##############################################################################
# 
# Mainline

$pnd_api = new PND_API();
$pnd_handlers = new PND_HandlerChain();
$pnd_handlers->addHandler( new PND_op_authenticate() );
if (!isset($_GET['op']) || strlen($_GET['op']) < 1) {
 	$pnd_utils->return_data( 
		array( 'bad_data' => array(), 'msg' => 'Missing op' ), 400);
	exit (0);
}
$pnd_handlers->handle($_GET['op']);




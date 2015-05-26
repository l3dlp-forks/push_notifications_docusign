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

class PND_HandlerChain
{
  private $_handlers = array();

  public function addHandler( $handler )
  {
    $this->_handlers []= $handler;
  }

  public function handle( $op )
  {
    foreach( $this->_handlers as $handler )
    {
      if ( $handler->request( $op ) )
        return;
    }
  }
}


##############################################################################
##############################################################################
##############################################################################
# 
# Mainline

$pnd_handlers = new PND_HandlerChain();
$pnd_handlers->addHandler( new PND_op_authenticate() );
if (!isset($_GET['op']) || strlen($_GET['op']) < 1) {
 	$pnd_utils->return_data( 
		array( 'bad_data' => array(), 'msg' => 'Missing op' ), 400);
	exit (0);
}
$pnd_handlers->handle($_GET['op']);




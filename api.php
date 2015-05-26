<?php
define("APP", "Push Notifications for DocuSign");
include (realpath(dirname(__FILE__) . '/src/bootstrap.php');

/*	api file for the Push Notifications DocuSign app

	All requests include url parameter op.
	Some calls should only use POST
	All calls should only use HTTPS
	
*/

# The following chain-of-command pattern is from 
# http://www.ibm.com/developerworks/library/os-php-designptrns/

interface PND_Request
{
  function request( $name, $args );
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

$handlers = new PND_HandlerChain();
$handlers->addHandler( new UserCommand() );
$handlers->addHandler( new MailCommand() );
$handlers->handle( 'addUser');



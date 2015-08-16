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
	subscribe
	webhook
	
	
*/
# 
# Mainline
#
# Setup:
$pnd_api = new PND_API();
$pnd_handlers = new PND_HandlerChain();
$pnd_handlers->addHandler( new PND_op_authenticate() );
$pnd_handlers->addHandler( new PND_op_subscribe() );
$pnd_handlers->addHandler( new PND_op_unsubscribe() );
$pnd_handlers->addHandler( new PND_op_refresh() );
$pnd_handlers->addHandler( new PND_op_webhook() );

# Here we go...
if (!isset($_GET['op']) || strlen($_GET['op']) < 1) {
 	$pnd_utils->return_data( 
		array( 'bad_data' => array(), 'msg' => 'Missing op' ), 400);
	exit (0);
}
$pnd_handlers->handle($_GET['op']);

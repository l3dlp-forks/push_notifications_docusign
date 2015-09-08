<?php
if (!defined('APP')) {exit("Buzz off");}

/*
    Creating constants for heavily used paths makes things a lot easier.
    ex. require_once(LIBRARY_PATH . "Paginator.php")
*/
defined("LIB_PATH")
    or define("LIB_PATH", realpath(dirname(__FILE__) . '../public'));
defined("VENDOR_PATH")
    or define("VENDOR_PATH", realpath(dirname(__FILE__) . '../vendor'));

	
#  Error reporting.
ini_set("error_reporting", "true");
error_reporting(E_ALL|E_STRCT);

# Includes
require (LIB_PATH . "config.php");
require 'vendor/autoload.php'; # See https://getcomposer.org/doc/01-basic-usage.md
require 'vendor_lsk/DocuSign-PHP-Client/src/DocuSign_Client.php';
require 'vendor_lsk/DocuSign-PHP-Client/src/service/DocuSign_LoginService.php';
require 'vendor_lsk/DocuSign-PHP-Client/src/service/DocuSign_UserService.php';
require 'vendor_lsk/DocuSign-PHP-Client/src/service/DocuSign_ConnectService.php';

require (LIB_PATH . "PND_utils.php");
require (LIB_PATH . "PND_file_utils.php");
require (LIB_PATH . "DocuSign_Connect_utils.php");
require (LIB_PATH . "PND_api_classes.php");
require (LIB_PATH . "PND_op_authenticate.php");
require (LIB_PATH . "PND_op_subscribe.php");
require (LIB_PATH . "PND_op_unsubscribe.php");
require (LIB_PATH . "PND_op_refresh.php");
require (LIB_PATH . "PND_op_webhook.php");
require (LIB_PATH . "PND_op_notify_info");
require (LIB_PATH . "PND_google_db.php");
require (LIB_PATH . "PND_google_log.php");
require (LIB_PATH . "PND_cookies.php");



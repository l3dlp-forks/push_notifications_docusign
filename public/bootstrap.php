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
require 'vendor/autoload.php'; # See https://getcomposer.org/doc/01-basic-usage.md
require (LIB_PATH . "config.php");
require (LIB_PATH . "PND_utils.php");
require (LIB_PATH . "PND_op_authenticate.php");
require (VENDOR_PATH . "mrferos/src/DocuSign_Client.php");



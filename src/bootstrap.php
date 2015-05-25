<?php
if (!defined('APP')) {exit("Buzz off";}

/*
    Creating constants for heavily used paths makes things a lot easier.
    ex. require_once(LIBRARY_PATH . "Paginator.php")
*/
defined("LIB_PATH")
    or define("LIB_PATH", realpath(dirname(__FILE__) . '../public'));

#  Error reporting.
ini_set("error_reporting", "true");
error_reporting(E_ALL|E_STRCT);

# Includes

include (LIB_PATH . "config.php");
include (LIB_PATH . "PND_utils.php");

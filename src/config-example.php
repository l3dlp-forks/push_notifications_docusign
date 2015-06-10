<?php
if (!defined('APP')) {
	exit("Buzz off");
}
 
$pnd_config = array(
    "google_api_key" => '123',
	"cookie_salt" = '123', # Use a long salt from https://api.wordpress.org/secret-key/1.1/salt/  or similar.

    "docusign_integrator_key" => '123',
    "docusign_admin_email" => 'foo@woof.com', # used to configure and request DocuSign Connect 
    "docusign_admin_pw" => '123',
	"docusign_environment" => 'demo',
	"docusign_version" => 'v2', # api version

);
 
 
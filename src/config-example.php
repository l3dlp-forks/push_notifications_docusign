<?php
if (!defined('APP')) {
	exit("Buzz off");
}
 
### You Need: a Google Developer Account
### A Project to work on with the "Google Cloud Datastore API" turned ON. 
###    See the Google Developer Console -- https://console.developers.google.com/
### A "Service account" (Service email account) and a P12 key file for that service account. 
###    See Service Accounts -- https://developers.google.com/accounts/docs/OAuth2#serviceaccount
###
### Copy this file to config.php and put in the public/ sub-directory
### You can also store the P12 key file there, a .htaccess file prevents any .p12 files
### from being served (If .htaccess files are supported by your web server)
### You can name the file "key.p12" or something else
###
### Set both GDS_APP_NAME and GDS_DATASET_ID to your Google Developer "Project ID."
###
### Your Project ID is available from the "Overview" screen in the Developer Console.
### See https://console.developers.google.com
define('GDS_APP_NAME', ''); 
define('GDS_KEY_FILE_PATH',  realpath(dirname(__FILE__)) . '/key.p12'); 
define('GDS_SERVICE_ACCOUNT_NAME', '');
define('GDS_DATASET_ID', GDS_APP_NAME);
 
$pnd_config = array(
    "google_api_key" => '123',
	"cookie_salt" = '123', # Use a long salt from https://api.wordpress.org/secret-key/1.1/salt/  or similar.

    "docusign_integrator_key" => '123',
    "docusign_admin_email" => 'foo@woof.com', # used to configure and request DocuSign Connect 
    "docusign_admin_pw" => '123',
	"docusign_environment" => 'demo',
	"docusign_version" => 'v2', # api version

);
 
 
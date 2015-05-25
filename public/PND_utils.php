<?php
if (!defined('APP')) {exit("Buzz off";}
 
class PND_utils
{
  public function return_error( $err, $data )
  {
	http_response_code($err);
	header('Content-Type: application/json');
	echo json_encode($data);
  }
}

$pnd_utils = new PND_utils;

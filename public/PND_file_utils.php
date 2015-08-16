<?php
// PND_file_utils.php
//
// Copyright 2013 (c) by Larry Kluger
// License: The MIT License. See http://opensource.org/licenses/MIT
//
// Utility Library
//
if (!defined('APP')) {exit("Buzz off");}
define ("FILES_DIR", "downloads");

class PND_file_utils {

	public function write_input_to_file($fn) {
		$this->gc_files_dir();
		$full_filename = $this->files_dir() . '/' . $fn;
		file_put_contents($full_filename, file_get_contents('php://input'));
	}


	//============================================================+
	// send_redirect -- send redirect header
	// ARGS
	//   $url
	//============================================================+
	public function send_redirect($url)
	{
		header('Location: ' . $url); /* Redirect browser */
		exit;
	}

	//============================================================+
	// url_for -- returns the url for a file
	// ARGS
	//   $for -- the file or path/file
	//============================================================+
	public function url_for($for)
	{
	  return $this->url_dir() . $for; 
	}

	// url_dir returns the url path for the current doc's directory.
	// It includes the trailing /
	private function url_dir()
	{
	  $this->fix_request_uri();
	  $server_port = $_SERVER['SERVER_PORT'];
	  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $server_port == 443);
	  $scheme = $https ? "https://" : "http://";
	  $host = $_SERVER['SERVER_NAME'];
	  
	  $port = "";
	  if (($https  && $server_port <> 443) || 
		  (!$https && $server_port <> 80)) {
		$port = ":" . $server_port;
	  }
		
	  $parts = $this->parse_url($_SERVER['REQUEST_URI']); // see http://php.net/manual/en/function.parse-url.php  
	  $path = $parts['path']; //  /foo.php  or /a/b/foo.php

	  $path_parts = preg_split("[\/]", $path);
	  array_pop ($path_parts);
	  
	  return $scheme . $host . $port . implode('/', $path_parts) . (count($path_parts) > 0 ? '/' : '');  
	}

	// request_uri is not set on some IIS servers
	private function fix_request_uri() 
	{ 
	  if (!isset($_SERVER['REQUEST_URI'])) {
		$_SERVER['REQUEST_URI'] = substr($_SERVER['PHP_SELF'],0);

		if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != '') {
		  $_SERVER['REQUEST_URI'] .= '?'.$_SERVER['QUERY_STRING'];
		}
	  }
	}  

	//============================================================+
	// gc_files_dir -- garbage collect the old signed files
	// Side effect: create the cache dir for the signed files if needed
	//============================================================+
	public function gc_files_dir()
	{
	   // functions are in the utils.php file
	   $this->make_files_dir(); 
	   $this->clean_files_dir();
	}

	// files_dir -- return full path of the dir for caching signed files
	// Does NOT include final /
	public function files_dir()
	{
	  return getcwd() . '/' . FILES_DIR;
	}

	// make_files_dir -- creates the directory for the signed files if it doesn't yet exist
	private function make_files_dir()
	{
	  $dir_name = $this->files_dir();
	  if(! is_dir($dir_name)) {
		 $err = mkdir ($dir_name, 0755);
		 if ($err) {die("Couldn't create dir " . $dir_name . $err);}
	  }	 
	}

	// clean_files_dir -- remove all files older than GC_TIME
	private function clean_files_dir()
	{
	  $dir_name = $this->files_dir();
	  // Create recursive dir iterator which skips dot folders
	  $it = new RecursiveDirectoryIterator($dir_name, FilesystemIterator::SKIP_DOTS);

	  // Maximum depth is 1 level deeper than the base folder
	  //$it->setMaxDepth(1);

	  $oldest = time() - GC_TIME;
	  // Loop and reap
	  while($it->valid()) {
		if ($it->isFile() && filemtime($it->key()) < $oldest) {unlink($it->key());}
		$it->next();
	  }
	}
}


<?php
// DocuSign_Connect_utils.php
//
// Copyright 2015 (c) by Larry Kluger
// License: The MIT License. See http://opensource.org/licenses/MIT
//
// Utility Library for DocuSign Connect XML messages
//

class DocuSign_Connect_utils {

	# private variables
	public $xml = NULL;
	private $xml_filename = NULL;
	private $directory = NULL;
	private $basename = NULL;
	private $pdf_filenames = [];
	
	//============================================================+
	// load_connect_message -- handles incoming Connect XML message
	// ARGS
	//   $basename  -- base file name that should be used for storing the incoming 
	//                 Connect messages include transaction data and one or more PDF
	//   $directory -- where the files will be stored
	//   $input     -- a filename for reading the incoming message
	// 
	// RETURNS
	//   $success boolean    
	// RAISES
	//   E_WARNING error message for each error found in the XML data.
	//
	// Actions
	// The method loads the connect message and parses it.
	// The enclosed files, if there are any, are stored as $directory/$basename_n.pdf 
	// where n is 0 through the last file name.
	// The filebytes are removed from the XML and the result is written out as
	// $directory/$basename.xml
	//                
	//============================================================+
	public function load_connect_message($basename, $directory, $input = 'php://input') {
		$slash = substr($directory, -1) === '/' ? '' : '/';
		$directory .= $slash;
		$this->directory = $directory;
		$this->basename = $basename;

		$this->xml = simplexml_load_file ($input, "SimpleXMLElement", LIBXML_PARSEHUGE);
		if ($this->xml === false) {
			return false;
		}

		$this->extract_pdf_files();
		$this->xml_filename = $directory . $basename . '.xml'; // set the xml filename
		$this->xml->asXML($this->xml_filename); // save the xml minus the pdf content
		return true;
	}

	public function get_xml_filename() {
		return $this->xml_filename;
	}
	
	public function get_pdf_filenames() {
		return $this->pdf_filenames;
	}
	
	// Extract the PDF files and store them.
	// Replace the PDF files with a text note.
	private function extract_pdf_files(){
		// see http://php.net/manual/en/simplexml.examples-basic.php
		
		$i = 0;
		foreach ($this->xml->DocumentPDFs->DocumentPDF as $pdf) {
			$filename = $this->directory . $this->basename . '_' . $i . '.pdf';
			file_put_contents($filename, base64_decode ( (string)$pdf ));
			$pdf = "Content elided.";
			$this->pdf_filenames[$i] = $filename;
			$i++;
		}
	}

	
}


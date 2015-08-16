<?php
# Uses the Google DataStore NoSQL db to log events
#
#
# DB schema
#   timestamp
#	severity -- debug, warning, critical
#	subject
#   details
	
class PND_google_log {

	# private variables
	private $gds_client = NULL;
	private $gds_gateway = NULL;
	private $log_db = NULL;
	
	function __construct() {
		// We'll need a Google_Client, use our convenience method
		$this->gds_client = GDS\Gateway::createGoogleClient(GDS_APP_NAME, GDS_SERVICE_ACCOUNT_NAME, GDS_KEY_FILE_PATH);

		// Gateway requires a Google_Client and Dataset ID
		$this->gds_gateway = new GDS\Gateway($this->gds_client, GDS_DATASET_ID);

		$this->log_db = new LogDB($this->gds_gateway);
	}
	
	public function log ($severity, $subject, $details) {  # severity: debug, warning, critical
		# create the record
		#
		# For the timestamp, we want int64 timestamp_microseconds_value (since Jan 1 1970)
		# See https://cloud.google.com/datastore/docs/concepts/entities#Datastore_Properties_and_value_types
		$microtime_parts = explode ( ' ', microtime());
		$timestamp = intval($microtime_parts[1]) + intval($microtime_parts[0]);
		
		$entry = $this->log_db->createEntity([
			'timestamp' => $timestamp,
			'severity' => $severity,
			'subject' => $subject,
			'details' => $details
		]);
		$bol_result1 = $this->log_db->upsert($entry);
	}
	

class LogEntries extends GDS\Entity {}
class LogDB extends GDS\Store {
    /**
     * Build and return a Schema object describing the data model
     *
     * @return \GDS\Schema
     */
    protected function buildSchema()
    {
        $this->setEntityClass('\\LogEntries');
        return (new GDS\Schema('LogEntries'))
            ->addDatetime('timestamp')
            ->addString('severity')
			->addString('subject')
            ->addString('details');
    }
}
	
	
	
	
	


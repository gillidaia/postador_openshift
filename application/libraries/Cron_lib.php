<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 */
class Cron_lib {

	private $lock_dir;
	private $lock_suffix;

	function __construct() {
		$this->lock_dir = APPPATH.'cache'.DIRECTORY_SEPARATOR;
		$this->lock_suffix = 'cronInProgress.lock';
	}

	function __clone() {}

	public function lock($service = "") {
		$lock_file = $this->lock_dir.$service.$this->lock_suffix;

		if(file_exists($lock_file)) {

			// Check if file is old
			// set User time zone
			date_default_timezone_set('UTC');

			$currentDateTime = new DateTime();
			$currentDateTime->modify("-1 minutes");

			if(strtotime($currentDateTime->format("Y-m-d H:i:s")) < strtotime(date( "Y-m-d H:i:s",filemtime($lock_file)))){
				return true;
			}else{
				unlink($lock_file);
			}
			
		}

		file_put_contents($lock_file, "Kingposter schedule in porgress");
		return false;
	}

	public function unlock($service = "") {
		$lock_file = $this->lock_dir.$service.$this->lock_suffix;
		if(file_exists($lock_file)){
			unlink($lock_file);
		}
		return false;
	}

}

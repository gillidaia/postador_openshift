<?php
/**
 *
 */

if (!defined('BASEPATH')) exit('No direct script access allowed');

$filesFolder = APPPATH . '../vendor/elfinder/php/';

require_once $filesFolder . 'elFinderConnector.class.php';
require_once $filesFolder . 'elFinder.class.php';
require_once $filesFolder . 'elFinderVolumeDriver.class.php';
require_once $filesFolder . 'elFinderVolumeLocalFileSystem.class.php';

class Elfinder_lib 
{
	public function __construct($opts) 
	{
		$connector = new elFinderConnector(new elFinder($opts));
		$connector->run();
	}

}
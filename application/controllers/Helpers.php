<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Controller
 */
class Helpers extends CI_Controller {

	private $settings;

	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct() {	
		parent::__construct();
		$this->load->helper(array('json_helper'));
	}
	
	public function get_url_info()
	{	
		$this->load->library('form_validation');
		
		// Check require params
		$this->form_validation->set_rules('url', 'URL', 'trim|required');

		if($this->form_validation->run() === false) {
			display_json(array(
				'status' => 'fail',
				'message' => $this->form_validation->error_array()
			));
			return;
		}

		display_json(array(
			'status' => 'ok',
			'url' => url_info($this->input->post('url',TRUE))
		));
		return;
	}
	
}

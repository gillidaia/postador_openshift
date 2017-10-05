<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * settings_modal class.
 * 
 * @extends CI_Model
 */
class Settings_model extends MY_Model {

	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->load->database();
		$this->load->library(array('session'));

	}

	public function update($newSettings = array()){
		$settings = (array)$this->get();
		foreach ($newSettings as $key => $value) {
			$this->updateORAddOption($key,$value,array_key_exists($key,$settings));
		}
	}

	public function updateORAddOption($option,$value,$action = true){
		$this->db->set('value',$value);

		if($action){
			$this->db->where('option',$option);
        	$this->db->update('options');
		}else{
			$this->db->set('option',$option);
        	$this->db->insert('options');
		}
		
		$this->session->set_userdata('app_settings',null);
		$this->get();

        return $this->db->affected_rows() > 0;
	}

	public function get(){

		if($this->session->userdata('app_settings') == null){
			$this->db->from('options');
    		$res = $this->db->get();
    		$result = $res->result_array();
	    	foreach ($result as $r) {
	    		$settings[$r["option"]] = $r["value"];
	    	}
	    	$this->session->set_userdata('app_settings',$settings);
	       	return $settings;
	  	}
	  	return (array)$this->session->userdata('app_settings');
	}

}

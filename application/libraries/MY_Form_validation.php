<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Form_validation extends CI_Form_validation {

    public function is_exists($str, $field)
    {
    	sscanf($field, '%[^.].%[^.]', $table, $field);
    	if(!isset($this->CI->db)) return FALSE;
		$this->CI->db->where($field,$str);
		return $this->CI->db->count_all_results($table) === 0;
	
    }

} 

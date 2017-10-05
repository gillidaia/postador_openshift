<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Post class.
 * 
 * @extends CI_Model
 */
class Log_model extends CI_Model {

	private $id;
	private $userId;
	private $scheduleId;
	private $content;
    private $node;
    private $fbPost;
	private $createdAt;

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

    public function save(){
        $this->db->set('user_id', $this->userId);
        $this->db->set('scheduledPosts', $this->scheduleId);
        $this->db->set('content', $this->content);
        $this->db->set('node', $this->node);
        $this->db->set('fb_post', $this->fbPost);
        $this->db->set('created_at', date('Y-m-d H:i'));
        $this->db->insert('logs');
        return $this->db->affected_rows() > 0;
    }

    public function delete(){
        if($this->schedule){
            $this->db->delete('logs', array('scheduledPosts' => $this->scheduleId,'user_id' => $this->userId));
        }else{
            $this->db->delete('logs', array('user_id' => $this->userId)); 
        }
        return $this->db->affected_rows() > 0;
    }

    public function count() {
        $this->db->where('user_id', $this->userId);
        return $this->db->count_all_results("logs");
    }

    // Fetch data according to per_page limit.
    public function get($offset = 0,$limit = 25,$scheduleId = false) {
        $this->db->from('logs');
        $this->db->limit($limit,$offset);
        $this->db->where('user_id', $this->userId);
        if($scheduleId){
            $this->db->where('scheduledPosts', $scheduleId);   
        }
        $this->db->order_by('id', 'DESC');
        return $this->db->get()->result();
    }

}
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Post class.
 * 
 * @extends CI_Model
 */
class ScheduleLogs_Model extends CI_Model {

    private $id;
    private $userId;
    private $scheduleId;
    private $content;
    private $nodeId;
    private $nodeName;
    private $nodeType;
    private $fbPost;
    private $share;
    private $comments;
    private $reactions;
    private $createdAt;

    // Setters and getters Auto Generate  
    public function __call($function, $args)
    {
        $functionType = strtolower(substr($function, 0, 3));
        $propName = lcfirst(substr($function, 3));
        switch ($functionType) {
            case 'get':
                if (property_exists($this, $propName)) {
                    return $this->$propName;
                }
                break;
            case 'set':
                if (property_exists($this, $propName)) {
                    $this->$propName = $args[0];
                }
                break;
        }
    }
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

        // Reconnect to the database
        $this->db->reconnect();
        
        $this->db->set('user_id', $this->userId);
        $this->db->set('schedule_id', $this->scheduleId);
        $this->db->set('content', $this->content);
        $this->db->set('node_id', $this->nodeId);
        $this->db->set('node_name', $this->nodeName);
        $this->db->set('node_type', $this->nodeType);
        $this->db->set('fb_post', $this->fbPost);
        $this->db->set('share', 0);
        $this->db->set('comments', 0);

        $reactions = array(
            "like" => 0,
            "love" => 0,
            "wow"  => 0,
            "haha" => 0,
            "sad"  => 0,
            "angry"=> 0,
        );

        $this->db->set('reactions', json_encode($reactions));
        $this->db->set('created_at', date('Y-m-d H:i'));
        $this->db->insert('schedule_logs');
        return $this->db->affected_rows() > 0;
    }

    public function update($data){
        foreach ($data as $key => $value) {
            $this->db->set($key, $value);
        }
        $this->db->where('id', $this->id);
        $this->db->where('user_id',$this->userId);
        $this->db->update("schedule_logs");

        return $this->db->affected_rows() > 0;
    }

     public function updateInsight($data){
        foreach ($data as $key => $value) {
            $this->db->set($key, $value);
        }
        $this->db->where('schedule_id', $this->scheduleId);
        $this->db->where('fb_post', $this->fbPost);
        $this->db->where('user_id',$this->userId);
        $this->db->update("schedule_logs");

        return $this->db->affected_rows() > 0;
    }

    public function delete(){
        if($this->scheduleId){
            $this->db->delete('schedule_logs', array('schedule_id' => $this->scheduleId,'user_id' => $this->userId));
        }else{
            $this->db->delete('schedule_logs', array('user_id' => $this->userId)); 
        }
        return $this->db->affected_rows() > 0;
    }

    // Count all record of table "instagram_accounts" in database.
    public function count() {
        $this->db->where('user_id', $this->userId);

        if($this->scheduleId)
            $this->db->where('schedule_id', $this->scheduleId);
        
        return $this->db->count_all_results("schedule_logs");
    }

    // Fetch data according to per_page limit.
    public function get($offset = 0,$limit = 25,$scheduleId = false) {
        $this->db->select("sl.*,sp.fb_account");
        $this->db->from('schedule_logs sl');
        $this->db->limit($limit,$offset);
        $this->db->where('sl.user_id', $this->userId);
        $this->db->join('scheduledposts sp', 'sp.id = sl.schedule_id');
        if($scheduleId){
            $this->db->where('schedule_id', $scheduleId);   
        }
        $this->db->order_by('sl.id', 'DESC');
        return $this->db->get()->result();
    }

     public function getById() {
        $this->db->from('schedule_logs');
        $this->db->where('id',$this->id);
        $this->db->where('user_id', $this->userId);
        return $this->db->get();
    }
}
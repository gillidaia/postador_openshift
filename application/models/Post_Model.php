<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Fbapps_model class.
 * 
 * @extends CI_Model
 */
class Post_model extends MY_Model {

    private $id;
    private $userId;
    private $content;
    private $createdAt;
    private $title;
    private $type;

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
    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library(array('session'));
        $this->load->model('User_Model');
    }

    public function save(){
        $this->db->set('userid', $this->userId);
        $this->db->set('content', $this->content);
        $this->db->set('post_title', $this->title);
        $this->db->set('type', $this->type);
        $this->db->set('date_created', date('Y-m-d H:i'));
        $this->db->insert('posts');
        return $this->db->affected_rows() > 0 ? $this->db->insert_id() : false;
    }
    
    public function count() {
        $this->db->where('userid', $this->userId);
        return $this->db->count_all_results("posts");
    }

    public function get($offset = 0,$limit = 25,$term = false){
        $this->db->from('posts');
        $this->db->where('userid',$this->userId);
        $this->db->order_by('id','DESC');
        $this->db->limit($limit,$offset);
        return $this->db->get()->result();
    }
    
    public function getById(){
        $this->db->from('posts');
        $this->db->where('id',$this->id);
        $this->db->where('userid',$this->userId);
        return $this->db->get();
    }
    
    public function update(array $params){
        foreach ($params as $key => $value) {
            $this->db->set($key, $value);
        }
        $this->db->where("id",$this->id);
        $this->db->where("userid",$this->userId);
        $this->db->update("posts");
        return $this->db->affected_rows() > 0;
    }
    
    public function delete($ids){

        if(!is_array($ids)){
            throw new Exception("Error : Expected array but received ". gettype($ids));
        }
        
        $this->db->where('userid', $this->userId);
        $this->db->where_in('id', $ids);
        $this->db->delete('posts');

        return $this->db->affected_rows() > 0;
    }
}
?>
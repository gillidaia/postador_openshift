<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Statistic class.
 * 
 * @extends CI_Model
 */
class UserNotifications_Model extends CI_Model {

    private $id;
    private $notification;
    private $userId;
    private $toAll = 0;
    private $isSeen = 0;
    private $seenAt;
    private $active = 1;
    private $showOn = 1;

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
        $this->db->set('notification', $this->notification);
        $this->db->set('user_id', $this->userId);
        $this->db->set('to_all', $this->toAll);
        $this->db->set('is_seen', $this->isSeen);
        $this->db->set('active', $this->active);
        $this->db->set('seen_at', date('Y-m-d H:i'));
        $this->db->insert('user_notifications');
        return $this->db->affected_rows() > 0 ? $this->db->insert_id() : false;
    }

    public function update(array $data){
        foreach ($data as $key => $value) {
            $this->db->set($key, $value);
        }
        $this->db->where("id",$this->id);
        $this->db->update("user_notifications");
        return $this->db->affected_rows() > 0;
    }

    public function count() {
        $this->db->where('user_id', $this->userId);
        return $this->db->count_all_results("user_notifications");
    }
    
    public function getByNotifications() {
        $this->db->select("*,u.username");
        $this->db->from('user_notifications un');
        $this->db->join('users u', 'u.id = un.user_id','left');
        $this->db->where('notification', $this->notification);
        return $this->db->get()->result();
    }

    public function get() {
        $this->db->select("un.*,n.*,un.id as 'unid'");
        $this->db->from('user_notifications un');
        $this->db->join('notifications n', 'n.id = un.notification');
        $this->db->where('n.show_on', $this->showOn);
        $this->db->where('un.active', 1);
        $this->db->where('(user_id = '.$this->userId.' OR to_all = 1)');
        $res = $this->db->get();
        return $res ? $res->result() : false;
    }

    public function getById() {
        $this->db->from('user_notifications');
        $this->db->where('id', $this->id);
        return $this->db->get();
    }

    public function delete(){
        $this->db->where('id',$this->id);
        $this->db->where('user_id',$this->userId);
        $this->db->delete('user_notifications');
        return $this->db->affected_rows() > 0;   
    }

    public function getUserNotifications($notif = array()){
        $notifications = $this->get();
        if($notifications){
            $this->load->helper(array('flash_helper'));
            foreach ($notifications as $notification) {
                
                if($notification->to_all == 1){
                    // Get closed notification from cookie 
                    $notifClosed = $this->input->cookie($this->config->item('sess_cookie_name')."_notfi_".(int)$notification->unid, TRUE);
                    if(!$notifClosed){
                        if($notification->delete_after == "seen"){
                            $cookie = array(
                                'name'   => '_notfi_'.(int)$notification->unid,
                                'value'  => 1,
                                'expire' => '9000000',
                                'path'   => '/',
                                'prefix' => $this->config->item('sess_cookie_name'),
                                'secure' => FALSE
                            );
                            $this->input->set_cookie($cookie);
                        }
                        $notif[] = flash_bag($notification->content,$notification->type,true,true,$notification->is_html,$notification->unid);
                    }
                }else{
                   $notif[] = flash_bag($notification->content,$notification->type,true,true,$notification->is_html,$notification->unid);
                    $newData = array();
                    $newData['is_seen'] = 1;
                    if($notification->delete_after == "seen"){
                        $this->setId($notification->unid);
                        $newData['active'] = 0;
                    }
                    $this->update($newData); 
                }
            }
        }
        return $notif;
    }
}
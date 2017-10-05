<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Statistic class.
 * 
 * @extends CI_Model
 */
class Statistic_model extends CI_Model {

    private $id;
    private $userId;
    private $posts;
    private $createdAt;
    private $servicesList;

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

        // List of available services
        $this->servicesList = array("posts","posts_fail","comments","comments_fail");
    }

    public function update($service){

        // Check if services is available
        if(!in_array($service, $this->servicesList)){
            throw new Exception("Update user statistice : Service not defined");
            return false;
        }

        // set default time zone
        date_default_timezone_set("UTC");

        // Check if the post date <= current datetime of the user
        // Get current time 
        $CDT = new DateTime();

        $this->db->select('id');
        $this->db->from('statistics');
        $this->db->where('created_at', $CDT->format("Y-m-d"));
        $this->db->where('user_id', $this->userId);

        $s = $this->db->get();
        if($s && $s->row() != null){
            return $this->updateUserStatistic($service,$s->row('id'));
        }

        return $this->addUserStatistic($service);
        
    }
    
    private function updateUserStatistic($service,$statiId){
        $this->db->set($service, $service.'+1',false);
        $this->db->where('id', $statiId);
        $this->db->update('statistics');
        return $this->db->affected_rows() > 0;
    }

    private function addUserStatistic($service){
        $CDT = new DateTime();
        $this->db->set($service, 1);
        $this->db->set('user_id', $this->userId);
        $this->db->set('created_at', $CDT->format("Y-m-d"));
        $this->db->insert('statistics');
        return $this->db->affected_rows() > 0 ? $this->db->insert_id() : false;
    }

    public function getUserStat($peroid = false, $services = array("posts","posts_fail")) {

        foreach ($services as $service) {
            $this->db->select($service);
        }
        $this->db->select("created_at");
        $this->db->from('statistics');
        $this->db->where('user_id', $this->userId);

        if($peroid == "day"){
            date_default_timezone_set("UTC");
            $CDT = new DateTime();
            $this->db->where('created_at', $CDT->format("Y-m-d"));
            return $this->db->get();
        }

        if($peroid == "week"){
            date_default_timezone_set("UTC");
            $CDT = new DateTime();
            $this->db->where('created_at <=', $CDT->format("Y-m-d"));
            $CDT->modify("-6 days");
            $this->db->where('created_at >=', $CDT->format("Y-m-d"));
            return $this->db->get()->result();
        }

        if($peroid == "month"){
            date_default_timezone_set("UTC");
            $CDT = new DateTime();
            $this->db->where('created_at <=', $CDT->format("Y-m-d"));
            $CDT->modify("-30 days");
            $this->db->where('created_at >=', $CDT->format("Y-m-d"));
            return $this->db->get()->result();
        }
        return $this->db->get();
    }

    public function getUserStatDay($services = array("posts","posts_fail")) {
        $this->db->from('statistics');
        $this->db->where('user_id', $this->userId);
        date_default_timezone_set("UTC");
        $CDT = new DateTime();

        foreach ($services as $service) {
            $this->db->select($service);
        }

        $this->db->where('created_at', $CDT->format("Y-m-d"));
        return $this->db->get();
    }

    public function getUserStatWeek($services = array("posts","posts_fail")) {
        $this->db->from('statistics');
        $this->db->where('user_id', $this->userId);
        date_default_timezone_set("UTC");
        $CDT = new DateTime();
        $this->db->where('created_at <=', $CDT->format("Y-m-d"));
        $CDT->modify("-6 days");
        $this->db->where('created_at >=', $CDT->format("Y-m-d"));
        
        foreach ($services as $service) {
            $this->db->select_sum($service);
        }

        return $this->db->get();
    }

    public function getUserStatMonth($services = array("posts","posts_fail")) {
        $this->db->from('statistics');
        $this->db->where('user_id', $this->userId);
        date_default_timezone_set("UTC");
        $CDT = new DateTime();
        $this->db->where('created_at <=', $CDT->format("Y-m-d"));
        $CDT->modify("-30 days");
        $this->db->where('created_at >=', $CDT->format("Y-m-d"));
        
        foreach ($services as $service) {
            $this->db->select_sum($service);
        }

        return $this->db->get();
    }

    public function getUserStatAllTime($services = array("posts","posts_fail")){
        $this->db->from('statistics');
        $this->db->where('user_id', $this->userId);

        foreach ($services as $service) {
            $this->db->select_sum($service);
        }
        
        return $this->db->get();
    }
}
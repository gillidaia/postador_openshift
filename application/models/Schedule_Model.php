<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Post class.
 * 
 * @extends CI_Model
 */
class Schedule_Model extends CI_Model {

    private $id;
    private $userId;
    private $nextRunTime;
    private $nextTarget;
    private $targets;
    private $postInterval;
    private $postId;
    private $postApp;
    private $pause;
    private $status;
    private $fbAccount;
    private $autoPause;
    private $repeatEvery;
    private $repeatedAt;
    private $endOn;
    private $totalTargets;
    private $createAt;

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
	    $this->db->set('userid', $this->userId);
	    $this->db->set('post_id', $this->postId);
	    $this->db->set('fb_account', $this->fbAccount);
	    $this->db->set('next_post_time', $this->nextRunTime);
        $this->db->set('next_target', 0);
        $this->db->set('targets',$this->targets);
        $this->db->set('post_interval',$this->postInterval);
        $this->db->set('post_app',$this->postApp);
        $this->db->set('auto_pause',$this->autoPause);
        $this->db->set('repeat_every',$this->repeatEvery);
        $this->db->set('repeated_at',$this->repeatedAt);
        $this->db->set('end_on',$this->endOn);
        $this->db->set('total_targets',$this->totalTargets);
        $this->db->set('status', 0);
        $this->db->set('pause', 0);
	    $this->db->set('created_at', date('Y-m-d H:i'));
	    $this->db->insert('scheduledposts');
	    return $this->db->affected_rows() > 0;
	}

    public function update($data){
        foreach ($data as $key => $value) {
            $this->db->set($key, $value);
        }
        $this->db->where('id', $this->id);
        $this->db->where('userid',$this->userId);
        $this->db->update("scheduledposts");

        return $this->db->affected_rows() > 0;
    }

	public function delete(){
	    $this->db->where('id', $this->id);
	    $this->db->where('userid', $this->userId);
	    $this->db->delete('scheduledposts');
	    return $this->db->affected_rows() > 0;
	}

    public function deleteAll($ids){

        if(!is_array($ids)){
            throw new Exception("Error : Expected array but received ". gettype($ids));
        }
        
        $this->db->where('userid', $this->userId);
        $this->db->where_in('id', $ids);
        $this->db->delete('scheduledposts');
        return $this->db->affected_rows() > 0;
    }
	 
    public function count() {
	    $this->db->where('userid', $this->userId);
	    return $this->db->count_all_results("scheduledposts");
	}

	// Fetch data according to per_page limit.
	public function get($offset = 0,$limit = 25) {
        $this->db->distinct();
        $this->db->select('sp.id, sp.next_post_time, sp.next_target, sp.total_targets,sp.post_interval, sp.post_app, sp.pause, sp.status, sp.fb_account, sp.auto_pause, sp.repeat_every, sp.repeated_at')
                ->select("posts.post_title,posts.id as 'post_id' ")
                ->select('fba.firstname, fba.lastname')
                ->select('fbapps.app_name')
	  			->from('scheduledposts sp')
	    		->limit($limit,$offset)
                ->join('posts', 'sp.post_id = posts.id','left')
                ->join('fb_accounts fba', 'sp.fb_account = fba.fb_id','left')
                ->join('fbapps', 'sp.post_app = fbapps.id','left')
                ->where('sp.userid', $this->userId)
                ->where('fba.user_id', $this->userId)
                ->order_by('sp.id', 'DESC');
        $res = $this->db->get();
	    return $res ? $res->result() : false;
	}

    public function getPending() {
        $this->db->distinct();
        $this->db->select('s.id, s.userid, s.next_post_time, s.next_target, s.total_targets,s.post_interval, s.post_app, s.pause, s.status, s.fb_account, s.auto_pause, s.repeat_every, s.repeated_at')
                ->select('u.timezone')
                ->from('scheduledposts s')
                ->join('users u', 'u.id = s.userid')
                ->where('u.active', 1)
                ->where('u.expired', 0)
                ->where('s.pause', 0)
                ->where('s.status', 0)
                ->order_by('s.next_post_time','ASC');
        $res = $this->db->get();
        return $res ? $res->result() : false;
    }

    public function getById() {
        $this->db->select('s.*')
                ->select('u.timezone')
                ->from('scheduledposts s')
                ->join('users u', 'u.id = s.userid','left')
                ->where('s.id',$this->id)
                ->where('s.userid', $this->userId);
        return $this->db->get();
    }

    public function toggleScheduleStatus(){
        $status = $this->getById($this->id)->row('pause') == 1 ? 0 : 1 ;
        $this->db->set('pause', $status);
        $this->db->where('id', $this->id);
        $this->db->where('userid', $this->userId);
        $this->db->update('scheduledposts');
        return $this->db->affected_rows() > 0;
    }

    public function autoPause($schedule){
        $ap = json_decode($schedule->row('auto_pause'),true);
        if(isset($ap['pause']) && $ap['pause'] != null && $ap['pause'] != 0){

            $this->setId($schedule->row('id'));
            $this->setUserId($schedule->row('userid'));

            if($ap['pause_after'] == 0){
                $ap['pause_after'] = $ap['pause']-1;
                $currentDateTime = new DateTime();
                $currentDateTime->modify("+".(int)$ap['resume']+rand(0,10)." minutes");
                
                $this->update(array("auto_pause" => json_encode($ap)));

                return $currentDateTime->format('Y-m-d H:i');
            }else{
                $ap['pause_after'] = $ap['pause_after']-1;
                $this->update(array("auto_pause" => json_encode($ap)));
            }
        }

        return false;
    }
}
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Fbapps_model class.
 * 
 * @extends CI_Model
 */
class FbApps_model extends MY_Model {

    private $id;
    private $appId = null;
    private $appSecret = null;
    private $appName = null;
    private $userId = null;
    private $adminAccessToken = null;
    private $appAuthLink = null;
    private $isPublic = 0;

    private $currentUser;

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
        $this->load->Model('User_Model');

        if($this->User_Model->isLoggedIn()){
            $this->currentUser = $this->User_Model->currentUser()['user_id'];
        }
    }

    public function save(){
        $this->db->set("user_id",$this->userId);
        $this->db->set("appid",$this->appId);
        $this->db->set("app_name",$this->appName);
        $this->db->set("app_secret",$this->appSecret);
        $this->db->set("app_auth_link",$this->appAuthLink);
        $this->db->set("admin_access_token",$this->adminAccessToken);
        $this->db->set("is_public",$this->isPublic);
        $this->db->insert('fbapps');
        return $this->db->affected_rows() > 0 ? $this->db->insert_id() : false;
    }

    public function get(){
        $this->db->from("fbapps");
        return $this->db->get()->results();
    }

    public function getUserAppById(){
        $this->db->from("fbapps");
        $this->db->where("id", $this->id);
        $this->db->where('(user_id = '.(int)$this->currentUser.' OR  is_public = 1)');
        return $this->db->get();
    }

    public function getUserAppByFbAppId(){
        $this->db->from("fbapps");
        $this->db->where("appId", $this->appId);
        $this->db->where('(user_id = '.$this->userId.' OR  is_public = 1)');
        return $this->db->get();
    }

    public function getById(){
        $this->db->from("fbapps");
        $this->db->where("id", $this->id);
        return $this->db->get();
    }

    public function getUserFBApps(){

        if($this->currentUser == null) return false;

        $this->db->select("*");
        $this->db->from('fbapps');
        $this->db->where('user_id', $this->currentUser);
        $this->db->or_where('is_public', 1);

        return $this->db->get()->result();
    }

    public function isAppAuthenticated($fbId){
        if($this->currentUser == null) return false;
        $this->db->select("*");
        $this->db->from('user_fbapp');
        $this->db->where('userid', $this->currentUser);
        $this->db->where('fb_id', $fbId);

        return $result->row() == null ? FALSE : TRUE;
    }

    public function  appType($appId){
        // 1 : Own app
        // 2 : Graph API Explorer
        // 3 : public app
        $this->setId($appId);
        $app = $this->getUserAppById();

        if(!$app->row()) return 0;

        if($app->row('appid') == "145634995501895") return 2;

        if($app->row('appid') == "6628568379" || $app->row('appid') == "350685531728") return 3;
    
        if($app->row('app_auth_link') != "") return 3;
        
        return 1;   
    }

    public function delete(){

        // Check if the current user can delete this app
        $this->db->where('id', $this->id);
        $this->db->where('user_id', $this->userId);
        if($this->db->count_all_results("fbapps") == 0){
            return false;    
        }

        $this->db->where('id', $this->id);
        $this->db->where('user_id', $this->userId);
        $this->db->delete('fbapps');

        // Delete the account apps
        $this->db->where('appid', $this->id);
        if($this->getIsPublic() == 0){
            $this->db->where('userid', $this->userId);
        }

        $this->db->delete('user_fbapp');

        // Update facebook accounts
        $this->db->set('defaultApp','');

        if($this->getIsPublic() == 0){
            $this->db->where('user_id', $this->userId);
        }

        $this->db->update('fb_accounts');
                
        return true;
        
    }

    public function getAccessToken($appId,$fbId,$userId){
        $this->db->select("access_token,access_token_date,expires_in");
        $this->db->from('user_fbapp');
        $this->db->where('appid', $appId);
        $this->db->where('fb_id', $fbId);
        $this->db->where('userid', $userId);
        return $this->db->get();
    }

    public function saveAccessToken($accessToken,$fbId,$expires_in){
        $this->db->set('userid',$this->userId);
        $this->db->set('appid',$this->id);
        $this->db->set('fb_id',$fbId);
        $this->db->set('access_token',$accessToken);
        $this->db->set('access_token_date',date('Y-m-d H:i:s'));
        $this->db->set('expires_in', $expires_in);
        $this->db->insert("user_fbapp");
        return $this->db->affected_rows() > 0 ? $this->db->insert_id() : false;
    }
    
    public function updateAccessToken($accessToken,$fbId,$expires_on){
        $this->db->set('access_token',$accessToken);
        $this->db->set('access_token_date',date('Y-m-d H:i:s'));
        $this->db->set('expires_in', $expires_on);
        $this->db->where('userid',$this->userId);
        $this->db->where('appid',$this->id);
        $this->db->where('fb_id',$fbId);
        $this->db->update("user_fbapp");
        return $this->db->affected_rows() > 0;
    }
    
    public function deauthorizeApp($fbaccount){
        $this->db->where('userid',$this->userId);
        $this->db->where('appid',$this->id);
        $this->db->where('fb_id',$fbaccount);
        $this->db->delete("user_fbapp");
        return $this->db->affected_rows() > 0;
    }

}
?>
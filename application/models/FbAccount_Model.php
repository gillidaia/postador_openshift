<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * @extends CI_Model
 */
class FbAccount_model extends MY_Model {

    private $userId = null;
    private $fbId = null;
    private $firstname = null;
    private $lastname = null;
    private $name = null;
    private $defaultApp = null;
    private $groups = null;
    private $hiddenGroups = null;
    private $pages = null;
    private $hiddenPages = null;
    private $totalGroups = 0;
    private $totalPages = 0;
    private $error;
    private $currentUser = null;
    private $defaultNodesCategory;

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

    // Save current instance
    public function save(){
        if( $this->userId == null || $this->fbId == null )
            $this->error = "User ID and facebook ID can not be empty.";
        
        $this->db->set("user_id",$this->userId);
        $this->db->set("fb_id",$this->fbId);
        $this->db->set("firstname",$this->firstname);
        $this->db->set("lastname",$this->lastname);
        $this->db->set("name",$this->name);
        $this->db->set("groups",$this->groups);
        $this->db->set("pages",$this->pages);
        $this->db->set("defaultApp",$this->defaultApp);

        $this->db->insert('fb_accounts');
        return $this->db->affected_rows() > 0 ? $this->db->insert_id() : false;
    }
    // Update current instance
    public function update(){
        
        if( $this->userId == null || $this->fbId == null )
                $this->error = "User ID and facebook ID can not be empty.";

        try{

            if($this->firstname != null){
                $this->db->set('firstname',$this->firstname);
            }

            if($this->lastname != null){
                $this->db->set('lastname',$this->lastname);
            }

            if($this->name != null){
                $this->db->set('name',$this->name);
            }
            
            if($this->defaultApp){
                $this->db->set('defaultApp',$this->defaultApp);
            }

            if($this->pages != null){
                $this->db->set('pages',$this->pages);
            }

            if($this->groups != null){
                $this->db->set('groups',$this->groups);
            }

            if($this->defaultNodesCategory != null){
                $this->db->set('default_nodes_category',$this->defaultNodesCategory);
            }
            
            $this->db->where('user_id',$this->userId);
            $this->db->where('fb_id',$this->fbId);
            $this->db->update("fb_accounts");
            
            return $this->db->affected_rows() > 0;

        }catch(Exception $e){
            $this->error = $e->getMessage();
            return FALSE;
        }
    }
    public function get($userid = null){
        if($userid == null){
            $userid = $this->currentUser;
        }
        $this->db->from('fb_accounts');
        $this->db->where('user_id', $userid);
        return $this->db->get();
    }
    public function getFbAccountById($fbid,$userid = null){
        if($userid == null){
            $userid = $this->currentUser;
        }
        $this->db->reconnect();
        $this->db->from('fb_accounts');
        $this->db->where('user_id', $userid);
        $this->db->where('fb_id', $fbid);
        return $this->db->get();
    }
    public function  getAll($userid = null){
        if($userid == null){
            $userid = $this->User_Model->currentuser()['user_id'];
        }
        $this->db->select('id,user_id,fb_id,firstname,lastname,name');
        $this->db->from('fb_accounts');
        $this->db->where('user_id', $userid);
        $res = $this->db->get();
        return $res ? $res->result() : false;
    }
    public function delete(){
        
        // Delete the facebook account if exists
        if(!$this->exists($this->fbId)){
            $this->error = $this->lang->s("FB_ACCOUNT_NOT_EXISTS");
            return;
        }
            
        // Delete the account
        $this->db->where('fb_id', $this->fbId);
        $this->db->where('user_id', $this->currentUser);
        $this->db->delete('fb_accounts');

        // Delete the account apps
        $this->db->where('fb_id', $this->fbId);
        $this->db->where('userid', $this->currentUser);
        $this->db->delete('user_fbapp');

        // Remove the account from user options if it is the default account
        if($this->UserDefaultFbAccount() == $this->fbId){
            $this->User_Model->setId($this->currentUser);
            $this->User_Model->UpdateOptions(array("default_Fb_Account"=>""));
        }

        return true;
    }
    public function exists($fbid,$userid = null){
        if($userid == null){
            $userid = $this->currentUser;
        }
        $this->db->where('user_id',$userid);
        $this->db->where('fb_id',$fbid);
        return $this->db->count_all_results("fb_accounts");
    }
    public function UserDefaultFbAccount(){
        $this->User_Model->setId($this->User_Model->currentUser()['user_id']);
        $userSettings = $this->User_Model->userSettings();
        if(isset($userSettings['default_Fb_Account'])){
            return $userSettings['default_Fb_Account'];
        }
        return false;
    }
    public function UserFbAccountDefaultApp($fb_account = null){
        $this->db->select('fa.*');
        $this->db->from('fb_accounts fba');
        $this->db->join('fbapps fa','fa.id = fba.defaultApp');
        $this->db->where('fba.user_id', $this->currentUser);

        if($fb_account === null){
            $fb_account = $this->fbId;
        }
            
        $this->db->where('fba.fb_id', $fb_account);   

        return $this->db->get();
    }
    /*
    |--------------------------------------------------------------------------
    | get the list of groups of the current user
    |--------------------------------------------------------------------------
    |
    */ 
    public function GetGroups($categoryID = null){
        $groups = null; 
        $fbAccount = $this->getFbAccountById($this->UserDefaultFbAccount(),$this->currentUser);

        if(!$fbAccount->row()){
            return false;
        }

        $this->load->Model('NodesCategory_Model');

        if($categoryID){
            $this->NodesCategory_Model->setUserId($this->currentUser);
            $this->NodesCategory_Model->setFbId($fbAccount->row('fb_id'));
            $groups = $this->NodesCategory_Model->groups($categoryID);
        }else{
            if($fbAccount->row('default_nodes_category') > 0){
                $this->NodesCategory_Model->setUserId($this->currentUser);
                $this->NodesCategory_Model->setFbId($fbAccount->row('fb_id'));
                $groups = $this->NodesCategory_Model->groups((int)$fbAccount->row('default_nodes_category'));
            }else{
                $this->db->select('groups');
                $this->db->from('fb_accounts');
                $this->db->where('fb_id', $fbAccount->row('fb_id'));
                $this->db->where('user_id', $this->currentUser);
                $groups = $this->db->get();
            }
        }

        if($groups && $groups->row() && $groups->row('groups') != null){
            $listGroups = json_decode($groups->row('groups'),true);
            // Check show open group only option is on unset non open groups
            if($this->User_Model->Options($this->currentUser) && $this->User_Model->Options($this->currentUser)->row('openGroupOnly') == 1){
                $listGroups = $this->unsetNoneOpenGroups($listGroups);
            }
            
            $this->User_Model->setID($this->User_Model->currentUser()['user_id']);
            $userSettings = $this->User_Model->userSettings();

            if(isset($userSettings['show_hidden_nodes']) && $userSettings['show_hidden_nodes'] == 0){
                // Unset hidden groups
                $this->db->select('hidden_groups');
                $this->db->from('fb_accounts');
                $this->db->where('fb_id', $fbAccount->row('fb_id'));
                $this->db->where('user_id', $this->currentUser);
                $hiddenGroups = $this->db->get();

                if($hiddenGroups && $hiddenGroups->row()){
                    $hiddenGroups = (array)json_decode($hiddenGroups->row('hidden_groups'),TRUE);
                    if(count($hiddenGroups) > 0){
                        $listGroups = $this->unsetHiddenNodes($listGroups,$hiddenGroups);
                    }
                }
            }

            $this->totalGroups = count($listGroups);
            return $listGroups;
        }
            
        return false;
    }
    /*
    |--------------------------------------------------------------------------
    | get the list of pages of the current user
    |--------------------------------------------------------------------------
    |
    |
    */ 
    public function GetPages($categoryID = null){
        
        $pages = null;

        $this->load->Model('NodesCategory_Model');

        if($categoryID){
            $this->NodesCategory_Model->setUserId($this->userId);
            $this->NodesCategory_Model->setFbId($this->fbId);
            $pages = $this->NodesCategory_Model->pages($categoryID);
        }else{

            $fbAccount = $this->getFbAccountById($this->fbId,$this->userId);

            if(!$fbAccount->row()){
                return false;
            }

            if($fbAccount->row('default_nodes_category') > 0){
                $this->NodesCategory_Model->setUserId($this->userId);
                $this->NodesCategory_Model->setFbId($fbAccount->row('fb_id'));
                $pages = $this->NodesCategory_Model->pages((int)$fbAccount->row('default_nodes_category'));
            }else{
                $this->db->reconnect();
                $this->db->select('pages');
                $this->db->from('fb_accounts');
                $this->db->where('fb_id', $fbAccount->row('fb_id'));
                $this->db->where('user_id', $this->userId);
                $pages = $this->db->get();
            }
        }

        if($pages->row()){

            $listPages = json_decode($pages->row('pages'),true);

            $this->User_Model->setID($this->User_Model->currentUser()['user_id']);
            $userSettings = $this->User_Model->userSettings();
            
            if(isset($userSettings['show_hidden_nodes']) && $userSettings['show_hidden_nodes'] == 0){
                // Unset hidden groups
                $this->db->reconnect();
                $this->db->select('hidden_pages');
                $this->db->from('fb_accounts');
                $this->db->where('fb_id', $this->fbId);
                $this->db->where('user_id', $this->currentUser);
                $hiddenPages = $this->db->get();

                if($hiddenPages && $hiddenPages->row()){
                    $hiddenPages = (array)json_decode($hiddenPages->row('hidden_pages'),TRUE);
                    if(count($hiddenPages) > 0){
                        $listPages = $this->unsetHiddenNodes($listPages,$hiddenPages);
                    }
                }
            }

            $this->totalPages = count($listPages);
            return $listPages;
        }
            
        return false;
    }
    /*
    |--------------------------------------------------------------------------
    | get the list of groups of the current user
    |--------------------------------------------------------------------------
    |
    |
    */ 
    public function GetGroupsAndPages($category = null,$userID = null){
        
        if($userID == null){
            $userID = $this->currentUser;
        }

        $fbAccountID = $this->UserDefaultFbAccount();

        $this->User_Model->setId($userID);
        $userSettings = $this->User_Model->userSettings();

        // Get current Account details
        $fbAccount = $this->FbAccount_Model->getFbAccountById($fbAccountID);

        $this->db->select('groups,pages');
        $this->db->where('user_id',$userID);
        $this->db->where('fb_id',$fbAccountID);

        // Load from Nodes category if current account has default category
        if($fbAccount->row('default_nodes_category') > 0){
            $this->db->where('id',$fbAccount->row('default_nodes_category'));
            $this->db->from('nodes_category');
        }else{
            $this->db->from('fb_accounts');
        }

        $queryResult = $this->db->get();

        if($queryResult->row()){

            $groups = array();
            $pages = array();

            if($queryResult->row('groups') != null){
                $groups = (array)json_decode($queryResult->row('groups'),true);
    

                if(isset($userSettings['openGroupOnly']) && $userSettings['openGroupOnly'] == 1){
                    $groups = $this->unsetNoneOpenGroups($groups);
                }
                
            }

            if($queryResult->row('pages') != null){
                $pages = (array)json_decode($queryResult->row('pages'),true);
            }

            if(isset($userSettings['show_hidden_nodes']) && $userSettings['show_hidden_nodes'] == 0){
                // Unset hidden groups/pages
                $this->db->select('hidden_groups,hidden_pages');
                $this->db->from('fb_accounts');
                $this->db->where('fb_id', $fbAccountID);
                $this->db->where('user_id', $this->currentUser);
                $hiddenNodes = $this->db->get();

                if($hiddenNodes && $hiddenNodes->row()){
                    $hiddenGroups = (array)json_decode($hiddenNodes->row('hidden_groups'),TRUE);
                    if(count($hiddenGroups) > 0){
                        $groups = $this->unsetHiddenNodes($groups,$hiddenGroups);
                    }
                    $hiddenPages = (array)json_decode($hiddenNodes->row('hidden_pages'),TRUE);
                    if(count($hiddenPages) > 0){
                        $pages = $this->unsetHiddenNodes($pages,$hiddenPages);
                    }
                }
            }

            $this->totalGroups = count($groups);
            $this->totalPages = count($pages);

            $nodes = array_merge($groups,$pages);

            return $nodes;

        }
    
        return false;
    }
    /*
    |--------------------------------------------------------------------------
    | Count number of groups
    |--------------------------------------------------------------------------
    |
    */
    public function GroupsCount(){
        return $this->totalGroups;
    }
    /*
    |--------------------------------------------------------------------------
    | Count number of pages
    |--------------------------------------------------------------------------
    |
    */
    public function PagesCount(){
        return $this->totalPages;
    }
    private function unsetNoneOpenGroups($listGroups){
        $i = 0;
        foreach ($listGroups as $group) {
            if(isset($group['privacy'])){
                if($group['privacy'] == 'SECRECT' || $group['privacy'] == 'CLOSED') {
                    unset($listGroups[$i]);
                }
            }
            $i++;
        }

        return $listGroups;
    }
    private function unsetHiddenNodes($listGroups,$hiddenGroups){
        $i = 0;
        foreach ($listGroups as $group) {
            if(isset($group['id']) && in_array($group['id'], $hiddenGroups)){
                unset($listGroups[$i]);
            }
            $i++;
        }
        return $listGroups;
    }
    /*
    |--------------------------------------------------------------------------
    | count Facebook Account
    |--------------------------------------------------------------------------
    |
    */
    public function countFbAccount($userID = null){
        if($userID == null){
            $userID = $this->currentUser;
        }
        $this->db->where('user_id', $userID);
        return $this->db->count_all_results("fb_accounts");
    }
    /*
    |--------------------------------------------------------------------------
    | Get user nodes
    |--------------------------------------------------------------------------
    |
    */
    public function getUserNodes(){

        $this->User_Model->setId($this->currentUser);
        $userSettings = $this->User_Model->userSettings();

        $this->userId = $this->currentUser;
        $this->fbId = $this->UserDefaultFbAccount();

        if(@$userSettings['show_pages'] == 1 && @$userSettings['show_groups'] == 1){
            return $this->GetGroupsAndPages();
        }

        if(@$userSettings['show_pages'] == 1 ){
            return $this->GetPages();
        }

        if(count($userSettings) == 0 || $userSettings['show_groups'] == 0){
            $this->User_Model->UpdateOptions(array('show_groups' => 1));
        }

        return $this->GetGroups();
    }

    public function fbAccountApps(){
        $this->db->select("f.*,f.appid as 'fbappid',uf.*");
        $this->db->from('fbapps f');
        $this->db->join('user_fbapp uf','f.id = uf.appid');
        $this->db->where('uf.userid', $this->userId);
        $this->db->where('uf.fb_id', $this->fbId);
        $res = $this->db->get();
        return $res ?  $res->result() : false;
    }

    public function defaultAccessToken($appid){
        $this->db->select("access_token");
        $this->db->from('user_fbapp');
        $this->db->where('userid', $this->userId);
        $this->db->where('fb_id', $this->fbId);
        $this->db->where('appid', $appid);
        return $this->db->get();   
    }

    public function clearProcessedFbAccounts(){
        $this->db->empty_table('processed_fb_accounts');
    }

    public function addProcessedFbAccount(){
        $this->db->set('fb_id',$this->fbId);
        $this->db->set('created_at', date('Y-m-d H:i'));
        $this->db->insert('processed_fb_accounts');
        return $this->db->affected_rows() > 0;
    }

    public function isFbAccountProcessed(){
        $this->db->where('fb_id',$this->fbId);
        return $this->db->count_all_results("processed_fb_accounts") > 0 ? TRUE : FALSE;
    }

    public function countFBAccountsGroups()
    {
        $this->db->select('groups');
        $this->db->where('user_id',$this->userId);
        $this->db->from('fb_accounts');
        $res = $this->db->get();
        $count = 0;
        foreach ($res->result() as $fbaccount) {
            $count += count(json_decode($fbaccount->groups,TRUE));
        }
        return $count;
    }

    public function countFBAccountsPages()
    {
        $this->db->select('pages');
        $this->db->where('user_id',$this->userId);
        $this->db->from('fb_accounts');
        $res = $this->db->get();
        $count = 0;
        foreach ($res->result() as $fbaccount) {
            $count += count(json_decode($fbaccount->pages,TRUE));
        }
        return $count;
    }

    public function hiddenNodes()
    {
        $this->db->select('hidden_groups,hidden_pages');
        $this->db->from('fb_accounts');
        $this->db->where('fb_id', $this->fbId);
        $this->db->where('user_id', $this->userId);
        return $this->db->get();
    }

    public function hideGroups($nodes){
        
        if( $this->userId == null || $this->fbId == null ){
            $this->error = "User ID and facebook ID can not be empty.";
        }

        // Get facebook account nodes
        $this->db->select('groups,hidden_groups');
        $this->db->from('fb_accounts');
        $this->db->where('fb_id', $this->fbId);
        $this->db->where('user_id', $this->userId);
        $groups = $this->db->get();

        if(!$groups->row()){
            return FALSE;
        }

        $groupsToHide = (array)json_decode($groups->row("hidden_groups"),TRUE);
        $mainList = (array)json_decode($groups->row("groups"),TRUE);

        foreach ($mainList as $group) {
            if(in_array($group['id'],$nodes) && !in_array($group['id'],$groupsToHide)){
                $groupsToHide[] = $group['id'];
            }
        }

        $groupsToHide = json_encode($groupsToHide);

        $this->db->set('hidden_groups',$groupsToHide);
        $this->db->where('user_id',$this->userId);
        $this->db->where('fb_id',$this->fbId);
        $this->db->update("fb_accounts");

        return $this->db->affected_rows() > 0;
    }

    public function hidePages($nodes){
        
        if( $this->userId == null || $this->fbId == null ){
            $this->error = "User ID and facebook ID can not be empty.";
        }

        // Get facebook account nodes
        $this->db->select('pages,hidden_pages');
        $this->db->from('fb_accounts');
        $this->db->where('fb_id', $this->fbId);
        $this->db->where('user_id', $this->userId);
        $pages = $this->db->get();

        if(!$pages->row()){
            return FALSE;
        }

        $pagesToHide = (array)json_decode($pages->row("hidden_pages"),TRUE);
        $mainList = (array)json_decode($pages->row("pages"),TRUE);

        foreach ($mainList as $group) {
            if(in_array($group['id'],$nodes) && !in_array($group['id'],$pagesToHide)){
                $pagesToHide[] = $group['id'];
            }
        }

        $pagesToHide = json_encode($pagesToHide);

        $this->db->set('hidden_pages',$pagesToHide);
        $this->db->where('user_id',$this->userId);
        $this->db->where('fb_id',$this->fbId);
        $this->db->update("fb_accounts");

        return $this->db->affected_rows() > 0;
    }

    public function unhideGroups($nodes){
        
        if( $this->userId == null || $this->fbId == null ){
            $this->error = "User ID and facebook ID can not be empty.";
        }

        // Get facebook account nodes
        $this->db->select('hidden_groups');
        $this->db->from('fb_accounts');
        $this->db->where('fb_id', $this->fbId);
        $this->db->where('user_id', $this->userId);
        $groups = $this->db->get();

        if(!$groups->row()){
            return FALSE;
        }

        $mainList = (array)json_decode($groups->row("hidden_groups"),TRUE);
        $newList = array();

        if(count($mainList) == 0 ) return FALSE;
       
        for ($i=0; $i < count($mainList) ; $i++) { 
            if(!in_array($mainList[$i], $nodes)){
                $newList[] = $mainList[$i];
            }
        }

        $mainList = json_encode($newList);

        $this->db->set('hidden_groups',$mainList);
        $this->db->where('user_id',$this->userId);
        $this->db->where('fb_id',$this->fbId);
        $this->db->update("fb_accounts");

        return $this->db->affected_rows() > 0;
    }

    public function unhidePages($nodes){
        
        if( $this->userId == null || $this->fbId == null ){
            $this->error = "User ID and facebook ID can not be empty.";
        }

        // Get facebook account nodes
        $this->db->select('hidden_pages');
        $this->db->from('fb_accounts');
        $this->db->where('fb_id', $this->fbId);
        $this->db->where('user_id', $this->userId);
        $groups = $this->db->get();

        if(!$groups->row()){
            return FALSE;
        }

        $mainList = (array)json_decode($groups->row("hidden_pages"),TRUE);
        $newList = array();
       
        if(count($mainList) == 0 ) return FALSE;

        for ($i=0; $i < count($mainList) ; $i++) { 
            if(!in_array($mainList[$i], $nodes)){
                $newList[] = $mainList[$i];
            }
        }

        $mainList = json_encode($newList);

        $this->db->set('hidden_pages',$mainList);
        $this->db->where('user_id',$this->userId);
        $this->db->where('fb_id',$this->fbId);
        $this->db->update("fb_accounts");

        return $this->db->affected_rows() > 0;
    }
}
?>
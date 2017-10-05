<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Fbapps_model class.
 * 
 * @extends CI_Model
 */
class NodesCategory_model extends MY_Model {

    private $id = null;
    private $userId = null;
    private $fbId = null;
    private $categoryName = null;
    private $groups = null;
    private $pages = null;
    private $createdAt = null;
    private $error = null;

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

    public function save(){
        $this->db->set('user_id', $this->userId);
        $this->db->set('fb_id', $this->fbId);
        $this->db->set('groups', $this->groups);
        $this->db->set('pages', $this->pages);
        $this->db->set('category_name', $this->categoryName);
        $this->db->set('created_at', date('Y-m-d H:i'));
        $this->db->insert('nodes_category');
        return $this->db->affected_rows() > 0 ? $this->db->insert_id() : false;
    }

    public function getbyId() {
        $this->db->select('id,user_id,fb_id'); 
        $this->db->from('nodes_category'); 
        $this->db->where('user_id',$this->userId);
        $this->db->where('id',$this->id);
        return $this->db->get();
    }

    public function update(array $data){
        foreach ($data as $key => $value) {
            $this->db->set($key, $value);
        }
        $this->db->where("id",$this->id);
        $this->db->where("user_id",$this->userId);
        $this->db->update("nodes_category");
        return $this->db->affected_rows() > 0;
    }

    public function isExists(){
        $this->db->where('id',$this->id);
        $this->db->where('user_id',$this->userId);
        return $this->db->count_all_results("nodes_category");
    
    }

    public function isCatNameExists($categoryName,$userID,$fbID){
        $this->db->where('category_name',$categoryName);
        $this->db->where('user_id',$userID);
        $this->db->where('fb_id',$fbID);
        return $this->db->count_all_results("nodes_category");
    
    }

    public function delete(){
        $this->db->where('id',$this->id);
        $this->db->where('user_id',$this->userId);
        $this->db->delete('nodes_category');
        return $this->db->affected_rows() > 0;   
    }

    public function groups($categoryID = false){
        $this->db->select('groups'); 
        $this->db->from('nodes_category'); 
        
        $this->db->where('user_id',$this->userId);
        $this->db->where('fb_id',$this->fbId);   

        if($categoryID != null){
           $this->db->where('id',$categoryID); 
        }

        return $this->db->get();
    }

    public function pages($categoryID = false){
        $this->db->select('pages'); 
        $this->db->from('nodes_category'); 
        
        $this->db->where('user_id',$this->userId);
        $this->db->where('fb_id',$this->fbId);   

        if($categoryID != null){
           $this->db->where('id',$categoryID); 
        }
        
        return $this->db->get();
    }

    public function getFbAccountCategories() {
        $this->db->select('id,category_name'); 
        $this->db->from('nodes_category'); 
        $this->db->where('user_id',$this->userId);
        $this->db->where('fb_id',$this->fbId);
        return $this->db->get()->result();
    }

    /*
    |--------------------------------------------------------------------------
    | Remove group from category
    |--------------------------------------------------------------------------
    |
    */
    public function removeNodes(array $nodes){
        
        if($this->id == -1){
            $this->error = $this->lang->s("Can not remove groups from the main node.");
            return false;
        }

        if(!$this->isExists()){
            $this->error = $this->lang->s("Nodes category not Exists.");
            return false;
        }

        $this->load->model('FbAccount_Model');
        $this->FbAccount_Model->setUserId($this->userId);
        $this->FbAccount_Model->setFbId($this->fbId);

        $groups = $this->groups($this->id);
        if($groups && $groups->row()){
            $g = (array)json_decode($groups->row('groups'),TRUE);
            $i = 0;
            foreach ($g as $key) {
                if(in_array($key['id'],$nodes)){
                    unset($g[$i]);
                    $g = array_values($g);
                }
                $i++;
            }
        }

        $pages = $this->pages($this->id);
        if($pages && $pages->row()){
            $p = (array)json_decode($pages->row('pages'),TRUE);
            $i = 0;
            foreach ($p as $key) {
                if(in_array($key['id'],$nodes)){
                    unset($p[$i]);
                    $p = array_values($p);
                }
                $i++;
            }
        }

        $data = array(
            'groups'   =>json_encode($g),
            'pages'    =>json_encode($p)
        );

        $res = $this->update($data);
        if($res){
            return true;
        }

        return false;
    }
    /*
    |--------------------------------------------------------------------------
    | Add group to category
    |--------------------------------------------------------------------------
    |
    */
    public function addNodes($nodes){

        if(!$this->isExists()){
            $this->error = $this->lang->s("Nodes category not Exists.");
            return false;
        }

        $newGroups = array();
        $newPages = array();
        $nodeBaseList = array_values($this->FbAccount_Model->GetGroupsAndPages());

        if(is_array($nodes) && count($nodes) != 0){
            if(is_array($nodeBaseList) && count($nodeBaseList) != 0){
                for($i = 0; $i<count($nodes); $i++) {
                    for($j = 0; $j<count($nodeBaseList);$j++) {
                        if(in_array($nodes[$i], $nodeBaseList[$j])){
                            if(isset($nodeBaseList[$j]['privacy'])){
                                $newGroups[] = $nodeBaseList[$j];
                            }else{
                                $newPages[] = $nodeBaseList[$j];
                            }
                            break;
                        }
                    }
                }
            }else{
                $this->error = "Could not load the list of groups and pages";
                return false;
            }
        }else{
            $this->error = "Invalid value supplied";
            return false;
        }

        $categoryGroups = $newGroups;
        $categoryPages = $newPages;

        // Merge groups 
        $oldGroups = $this->groups($this->id);
        $oldPages = $this->pages($this->id);

        if($oldGroups && $oldGroups->row()){
            $g = (array)json_decode($oldGroups->row('groups'),TRUE);
            $categoryGroups = array_merge_recursive($g,$newGroups);
        }

        // Merge groups 
         if($oldPages && $oldPages->row()){
            $p = (array)json_decode($oldPages->row('pages'),TRUE);
            $categoryPages = array_merge_recursive($p,$newPages);
        }

        // Remove duplicated groups
        $categoryGroups = array_map("unserialize", array_unique(array_map("serialize", $categoryGroups)));
        $categoryPages = array_map("unserialize", array_unique(array_map("serialize", $categoryPages)));

        $data = array(
            'groups'   =>json_encode($categoryGroups),
            'pages'    =>json_encode($categoryPages)
        );

        $res = $this->update($data);
        if($res){
            return true;
        }

        return false;
    }
}
?>
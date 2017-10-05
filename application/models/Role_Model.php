<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * model class.
 * 
 * @extends CI_Model
 */
class Role_Model extends MY_Model {
    
    private $id;
    private $name;
    private $permissions;
    private $maxPostsPerDay;
    private $maxFbAccounts;
    private $accountExpiry = 0;
    private $uploadVideos;
    private $uploadImages;
    private $maxUpload;

    private $maxComments;
    private $maxLikes;

    private $error;

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
        $this->db->set('name', $this->name);
        $this->db->set('permissions', json_encode($this->permissions));
        $this->db->set('max_posts', $this->maxPostsPerDay);
        $this->db->set('max_fbaccount', $this->maxFbAccounts);
        $this->db->set('account_expiry', $this->accountExpiry);
        $this->db->set('upload_videos', $this->uploadVideos);
        $this->db->set('upload_images', $this->uploadImages);
        $this->db->set('max_upload', $this->maxUpload);
        $this->db->set('max_comments', $this->maxComments);
        $this->db->set('max_likes', $this->maxLikes);
        $this->db->insert('roles');
        return $this->db->affected_rows() > 0 ? $this->db->insert_id() : false;
    }

    public function getAll(){
        $this->db->from('roles');
        return $this->db->get()->result();
    }

    public function getById(){
        $this->db->from('roles');
        $this->db->where('id',$this->id);
        return $this->db->get();
    }

    public function update($data){
        foreach ($data as $key => $value) {
            $this->db->set($key, $value);
        }
        $this->db->where('id', $this->id);
        $this->db->update("roles");
        
        return $this->db->affected_rows() > 0;
    }

    public function delete(){
        // If the role has user don't delete
        if($this->hasUsers() > 0){
            $this->error = $this->lang->s("Can not delete role that has users");
            return false;
        }

        $this->db->where('id',$this->id);
        $this->db->delete('roles');
        return $this->db->affected_rows() > 0;
    }

    public  function hasUsers(){
        $this->db->where('roles',$this->id);
        return $this->db->count_all_results("users");
    }

    public function isRoleNameExists(){
        $this->db->where('name',$this->name);
        return $this->db->count_all_results("roles");
    }
}

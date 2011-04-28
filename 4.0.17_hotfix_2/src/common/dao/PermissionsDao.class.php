<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 *
 * This file is a part of Codendi.
 *
 * Codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */

require_once('include/DataAccessObject.class.php');

/**
 *  Data Access Object for Permissions 
 */
class PermissionsDao extends DataAccessObject {
    /**
    * Constructs the PermissionsDao
    * @param $da instance of the DataAccess class
    */
    function PermissionsDao( & $da ) {
        DataAccessObject::DataAccessObject($da);
    }
    
    /**
    * Gets all tables of the db
    * @return DataAccessResult
    */
    function & searchAll() {
        $sql = "SELECT * FROM permissions";
        return $this->retrieve($sql);
    }
    
    /**
    * Searches Permissions by PermissionType 
    * @return DataAccessResult
    */
    function & searchByPermissionType($permissionType) {
        $sql = sprintf("SELECT object_id, ugroup_id FROM permissions WHERE permission_type = %s",
				"'".$permissionType."'");
        return $this->retrieve($sql);
    }

    /**
    * Searches Permissions by ObjectId 
    * @return DataAccessResult
    */
    function & searchByObjectId($objectId) {
        $sql = sprintf("SELECT permission_type, ugroup_id FROM permissions WHERE object_id = '%s'",
				"'".$objectId."'");
        return $this->retrieve($sql);
    }

    /**
    * Searches Permissions by UgroupId 
    * @return DataAccessResult
    */
    function & searchByUgroupId($ugroupId) {
        $sql = sprintf("SELECT permission_type, object_id FROM permissions WHERE ugroup_id = %s",
				"'".$ugroupId."'");
        return $this->retrieve($sql);
    }

    /**
     * Searches Ugroups Ids (and names if required) from ObjectId and Permission type
     *
     * @param String  $objectId       Id of object
     * @param String  $permissionType Permission type
     * @param Boolean $withName       Whether to include the group name or not
     * 
     * @return DataAccessResult
     */
    function searchUgroupByObjectIdAndPermissionType($objectId, $permissionType, $withName=true){
        $fields = '';
        $joins  = '';
        if ($withName) {
            $fields = ' ug.name, ';
            $joins  = ' JOIN ugroup AS ug USING(ugroup_id) ';
        }
        $sql = 'SELECT '.$fields.' p.ugroup_id'.
               ' FROM permissions p '.$joins.
               ' WHERE p.object_id = '.$this->da->quoteSmart($objectId).
               ' AND p.permission_type = '.$this->da->quoteSmart($permissionType).
               ' ORDER BY ugroup_id';
        return $this->retrieve($sql);
    }

    /**
     * Return the list of the default ugroup_ids authorized to access the given permission_type
     *
     * @param String  $permissionType Permission type
     * @param Boolean $withName       Whether to include the group name or not
     *
     * @return DataAccessResult
     */
    public function searchDefaults($permissionType, $withName=true) {
        $fields = '';
        $joins  = '';
        if ($withName) {
            $fields = ' ug.name, ';
            $joins  = ' JOIN ugroup AS ug USING(ugroup_id) ';
        }
        $sql = 'SELECT '.$fields.' pv.ugroup_id'.
               ' FROM permissions_values pv '.$joins.
               ' WHERE pv.permission_type='.$this->da->quoteSmart($permissionType).
               ' AND pv.is_default=1'.
               ' ORDER BY pv.ugroup_id';
        return $this->retrieve($sql);
    }

    /**
    * Searches Permissions by ObjectId and Ugroups
    * @return DataAccessResult
    */
    function & searchPermissionsByObjectId($objectId, $ptype=null) { 	
        if(is_array($objectId)) {
            $_where_clause = " object_id IN ('".implode("','",$objectId)."')";
        }
        else {
            $_where_clause = " object_id = '".$objectId."'";
        }
        if($ptype !== null) {
            $_where_clause .= ' AND permission_type IN (\''.implode(',',$ptype).'\')';
        }

        $sql = sprintf("SELECT * FROM permissions WHERE ".$_where_clause);
        return $this->retrieve($sql);
    }

    /**
    * Searches Permissions by TrackerId and Ugroups
    * @return DataAccessResult
    */
    function & searchPermissionsByArtifactFieldId($objectId) {
        $sql = sprintf("SELECT * FROM permissions WHERE object_id LIKE '%s#%%'" ,
				$objectId);
        return $this->retrieve($sql);
    }

    function clonePermissions($source, $target, $perms, $toGroupId=0) {
        foreach($perms as $key => $value) {
            $perms[$key] = $this->da->quoteSmart($value);
        }
        $sql = sprintf("DELETE FROM permissions ".
                        " WHERE object_id = '%s' ".
                        "   AND permission_type IN (%s) ",
                        $this->da->quoteSmart($target),
                        implode(', ', $perms)
        );
        $this->update($sql);
        $sql = sprintf("INSERT INTO permissions (object_id, permission_type, ugroup_id) ".
                        " SELECT %s, permission_type, IFNULL(dst_ugroup_id, permissions.ugroup_id) AS ugid ".
                        " FROM permissions LEFT JOIN ugroup_mapping ON (to_group_id=%d  and src_ugroup_id = permissions.ugroup_id)".
                        " WHERE object_id = '%s' ".
                        "   AND permission_type IN (%s) ",
                        $this->da->quoteSmart($target),
                        $toGroupId,
                        $this->da->quoteSmart($source),
                        implode(', ', $perms)
        );
        return $this->update($sql);
    }
    
    function addPermission($permission_type, $object_id, $ugroup_id){
        $sql=sprintf("INSERT INTO permissions (object_id, permission_type, ugroup_id)".
                     " VALUES ('%s', '%s', '%s')", 
                     $object_id, $permission_type, $ugroup_id);
        return $this->update($sql);
    }

}


?>
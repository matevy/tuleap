<?php
/**
 * Copyright (c) STMicroelectronics, 2006. All Rights Reserved.
 *
 * Originally written by Manuel Vacelet, 2006
 *
 * This file is a part of CodeX.
 *
 * CodeX is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * CodeX is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with CodeX; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */
require_once('service.php');

require_once('common/mvc/Actions.class.php');
require_once('common/include/HTTPRequest.class.php');

require_once('Docman_ActionsDeleteVisitor.class.php');
require_once('Docman_FolderFactory.class.php');
require_once('Docman_VersionFactory.class.php');
require_once('Docman_FileStorage.class.php');
require_once('Docman_MetadataValueFactory.class.php');
require_once('Docman_ExpandAllHierarchyVisitor.class.php');
require_once('Docman_ApprovalTableFactory.class.php');

require_once('view/Docman_View_Browse.class.php');

require_once('common/permission/PermissionsManager.class.php');
require_once('common/reference/ReferenceManager.class.php');

require_once('www/project/admin/permissions.php');
require_once('www/news/news_utils.php');

class Docman_Actions extends Actions {
    
    var $event_manager;
    
    function Docman_Actions(&$controler, $view=null) {
        parent::Actions($controler);
        $this->event_manager =& $this->_getEventManager();
    }
    
    function &_getEventManager() {
        $em =& EventManager::instance();
        return $em;
    }
    
    function expandFolder() {
        $folderFactory = new Docman_FolderFactory();
        $folderFactory->expand($this->_getFolderFromRequest());
    }
    function expandAll($params) {
        $params['hierarchy']->accept(new Docman_ExpandAllHierarchyVisitor(), array('folderFactory' => new Docman_FolderFactory()));
    }
    function collapseFolder() {
        $folderFactory = new Docman_FolderFactory();
        $folderFactory->collapse($this->_getFolderFromRequest());
    }
    function &_getFolderFromRequest() {
        $request =& HTTPRequest::instance();
        $folder = new Docman_Folder();
        $folder->setId((int) $request->get('id'));
        $folder->setGroupId((int) $request->get('group_id'));
        return $folder;
    }

    //@todo need to check owner rights on parent    
    function _checkOwnerChange($owner, &$user) {
        $ret_id = null;

        $_owner = util_user_finder($owner);
        if($_owner == "") {
            $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'warning_missingowner'));
            $ret_id = $user->getId();
        }
        else {
            $dbresults = user_get_result_set_from_unix($_owner);
            $_owner_id     = db_result($dbresults, 0, 'user_id');
            $_owner_status = db_result($dbresults, 0, 'status');
            if($_owner_id === false || $_owner_id < 1 || !($_owner_status == 'A' || $_owner_status = 'R')) {
                $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'warning_invalidowner'));
                $ret_id = $user->getId();
            }
            else {
                $ret_id = $_owner_id;
            }
        }
        return $ret_id;
    }
    
    private function _raiseMetadataChangeEvent(&$user, &$item, $group_id, $old, $new, $field) {
        $logEventParam = array('group_id' => $group_id,
                               'item'     => &$item,
                               'user'     => &$user,
                               'old_value' => $old,
                               'new_value' => $new,
                               'field'     => $field);

        $this->event_manager->processEvent('plugin_docman_event_metadata_update',
                                           $logEventParam);
    }

    /**
     * This function handle file storage regarding user parameters.
     *
     * @access: private
     */
    function _storeFile($item) {
        $fs       =& $this->_getFileStorage();
        $user     =& $this->_controler->getUser();
        $request  =& $this->_controler->request;
        $iFactory =& $this->_getItemFactory();

        $uploadSucceded = false;

        $number = 0;
        $version = $item->getCurrentVersion();
        if($version) {
            $number = $version->getNumber() + 1;
        }

        $_action_type = 'initversion';
        if($number > 0) {
            $_action_type = 'newversion';
        }

        //
        // Prepare label and changelog from user input
        $_label = '';
        $_changelog = '';
        $data_version = $request->get('version');
        if ($data_version) {
            if (isset($data_version['label'])) {
                $_label = $data_version['label'];
            }
            if (isset($data_version['changelog'])) {
                $_changelog = $data_version['changelog'];
            }
        }
        else {
            if($number == 0) {
                $_changelog = 'Initial version';
            }
        }

        switch ($iFactory->getItemTypeForItem($item)) {
        case PLUGIN_DOCMAN_ITEM_TYPE_FILE:
            if ($request->exist('upload_content')) {
                
                if ($request->exist('chunk_offset') && $request->exist('chunk_size')) {
                    $path = $fs->store($request->get('upload_content'), $request->get('group_id'), $item->getId(), $number, $request->get('chunk_offset'), $request->get('chunk_size'));
                } else {
                    $path = $fs->store($request->get('upload_content'), $request->get('group_id'), $item->getId(), $number);
                }
                
                if ($path) {
                    $uploadSucceded = true;
                    
                    if ($request->exist('file_name')) {
                        $_filename = basename($request->get('file_name'));
                    } else {
                        $_filename = basename($path);
                    }
                    
                    if ($request->exist('file_size')) {
                        $_filesize = $request->get('file_size');
                    } else {
                        $_filesize = filesize($path);
                    }
                    
                    if ($request->exist('mime_type')) {
                        $_filetype = $request->get('mime_type');
                    } else {
                        $_filetype = mime_content_type($path); //be careful with false detection
                    }
                }
            } else {
                $path = $fs->upload($_FILES['file'], $item->getGroupId(), $item->getId(), $number);
                if ($path) {
                    $uploadSucceded = true;
                    $_filename = $_FILES['file']['name'];
                    $_filesize = $_FILES['file']['size'];
                    $_filetype = $_FILES['file']['type']; //TODO detect mime type server side
                }
            }
            break;
        case PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE:
            if ($path = $fs->store($request->get('content'), $item->getGroupId(), $item->getId(), $number)) {
                $uploadSucceded = true;

                //TODO take mimetype once the file has been written ?
                $_filename = basename($path);
                $_filesize = filesize($path);
                $_filetype = 'text/html';
            }
            break;
        default:
            break;
        }

        if($uploadSucceded) {
            $vFactory =& $this->_getVersionFactory();

            $userId = $user->getId();
            if ($request->exist('author') && ($request->get('author') != $userId)) {
                $versionAuthor = $request->get('author');

                $eArray = array('group_id'  => $item->getGroupId(),
                                'item'      => &$item,
                                'new_value' => $this->_getUserManagerInstance()->getUserById($versionAuthor)->getName(),
                                'user'      => &$user);
                
                $this->event_manager->processEvent('plugin_docman_event_set_version_author', $eArray);
            } else {
                $versionAuthor = $userId;
            }
            
            $date = '';
            if ($request->exist('date')) {
                $date = $request->get('date');
                
                $eArray = array('group_id'  => $item->getGroupId(),
                                'item'      => &$item,
                                'old_value' => null, 
                                'new_value' => $date,
                                'user'      => &$user);
                
                $this->event_manager->processEvent('plugin_docman_event_set_version_date', $eArray);
            }

            $vArray = array('item_id'   => $item->getId(),
                            'number'    => $number,
                            'user_id'   => $versionAuthor,
                            'label'     => $_label,
                            'changelog' => $_changelog,
                            'filename'  => $_filename,
                            'filesize'  => $_filesize,
                            'filetype'  => $_filetype, 
                            'path'      => $path,
                            'date'      => $date);
            $vId = $vFactory->create($vArray);
            
            $eArray = array('group_id' => $item->getGroupId(),
                            'item'     => &$item, 
                            'version'  => $vId,
                            'user'     => &$user);
            $this->event_manager->processEvent('plugin_docman_event_new_version',
                                               $eArray);
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_create_'.$_action_type));

            // Approval table
            if($number > 0) {
                $vImport = new Valid_WhiteList('app_table_import', array('copy', 'reset', 'empty'));
                $vImport->required();
                $import = $request->getValidated('app_table_import', $vImport, false);
                if($import) {
                    // Approval table creation needs the item currentVersion to be set.
                    $vArray['id'] = $vId;
                    $vArray['date'] = time();
                    $newVersion =& new Docman_Version($vArray);
                    $item->setCurrentVersion($newVersion);

                    $atf =& Docman_ApprovalTableFactory::getFromItem($item);
                    $atf->createTable($user->getId(), $request->get('app_table_import'));
                }
            }
        }
        else {
            //TODO What should we do if upload failed ?
            //Maybe cancel item ?
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_create_'.$_action_type));
        }
    }

    function createFolder() {
        $this->createItem();
    }
    
    function createDocument() {
        $this->createItem();   
    }
    
    function createItem() {
        $request =& $this->_controler->request;
        $item_factory =& $this->_getItemFactory();
        if ($request->exist('item')) {
            $item = $request->get('item');
            $fs =& $this->_getFileStorage();
            if (
                    $item['item_type'] != PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE 
                    || 
                    (
                        $this->_controler->getProperty('embedded_are_allowed') 
                        && 
                        $request->exist('content')
                    )
                )
            {

                // Special handling of obsolescence date
                if(isset($item['obsolescence_date']) && $item['obsolescence_date'] != 0) {
                    if (preg_match('/^([0-9]+)-([0-9]+)-([0-9]+)$/', $item['obsolescence_date'], $d)) {
                        $item['obsolescence_date'] = mktime(0, 0, 0, $d[2], $d[3], $d[1]);
                    } else if (!preg_match('/^[0-9]*$/', $item['obsolescence_date'])) {
                        $item['obsolescence_date'] = 0;
                    }
                } else {
                    $item['obsolescence_date'] = 0;
                }

                $user =& $this->_controler->getUser();
                
                // Change owner
                $userId = $user->getId();
                if (isset($item['owner'])) {
                    $um = $this->_getUserManagerInstance();
                    $new_owner = $um->getUserByUserName($item['owner']);
                    if ($new_owner !== null) {
                        $owner = $new_owner->getId();
                    } else {
                        $owner = $userId;
                    }
                } else {
                    $owner = $userId;
                }
                $item['user_id'] = $owner;
                
                // Change creation date
                if (isset($item['create_date']) && $item['create_date'] != '') {
                    $create_date_changed = true;
                } else {
                    $create_date_changed = false;
                }
                
                // Change update date
                if (isset($item['update_date']) && $item['update_date'] != '') {
                    $update_date_changed = true;
                } else {
                    $update_date_changed = false;
                }
                
                $item['group_id'] = $request->get('group_id');
                $id = $item_factory->create($item, $request->get('ordering'));
                if ($id) {
                    $this->_controler->_viewParams['action_result'] = $id;
                    $new_item =& $item_factory->getItemFromDb($id);
                    $parent   =& $item_factory->getItemFromDb($item['parent_id']);
                    if ($request->exist('permissions') && $this->_controler->userCanManage($parent->getId())) {
                        $this->permissions(array('id' => $id, 'force' => true));
                    } else {
                        $pm = $this->_getPermissionsManagerInstance();
                        $pm->clonePermissions($item['parent_id'], $id, array('PLUGIN_DOCMAN_READ', 'PLUGIN_DOCMAN_WRITE', 'PLUGIN_DOCMAN_MANAGE'));
                    }
                    $this->event_manager->processEvent('plugin_docman_event_add', array(
                        'group_id' => $request->get('group_id'),
                        'parent'   => &$parent,
                        'item'     => &$new_item,
                        'user'     => &$user)
                    );

                    // Log change owner
                    if ($owner != $userId) {
                        $this->_raiseMetadataChangeEvent($user, $new_item, $request->get('group_id'), null, $item['owner'], 'owner');
                    }
                    
                    // Log change creation date
                    if ($create_date_changed) {
                        $this->_raiseMetadataChangeEvent($user, $new_item, $request->get('group_id'), null, $item['create_date'], 'create_date');
                    }
                    
                    // Log change update date
                    if ($update_date_changed) {
                        $this->_raiseMetadataChangeEvent($user, $new_item, $request->get('group_id'), null, $item['update_date'], 'update_date');
                    }

                    $info_item_created = 'info_document_created';
                    if($item['item_type'] == PLUGIN_DOCMAN_ITEM_TYPE_FOLDER) {
                        $info_item_created = 'info_folder_created';
                    }
                    $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_document_created'));
                    
                    if($item['item_type'] == PLUGIN_DOCMAN_ITEM_TYPE_FILE ||
                       $item['item_type'] == PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE) {
                        $this->_storeFile($new_item);
                    }
                    
                    // Create metatata
                    if($request->exist('metadata')) {
                        $metadata_array = $request->get('metadata');
                        $mdvFactory = new Docman_MetadataValueFactory($request->get('group_id'));
                        $mdvFactory->createFromRow($id, $metadata_array);
                        if($mdvFactory->isError()) {
                            $this->_controler->feedback->log('error', $mdvFactory->getErrorMessage());
                        }
                    }
                    
                    //Submit News about this document
                    if ($request->exist('news')) {
                        if ($user->isMember($request->get('group_id'), 'A') || $user->isMember($request->get('group_id'), 'N1') || $user->isMember($request->get('group_id'), 'N2')) { //only for allowed people
                            $news = $request->get('news');
                            if (isset($news['summary']) && trim($news['summary']) && isset($news['details']) && trim($news['details']) && isset($news['is_private'])) {
                                news_submit($request->get('group_id'), $news['summary'], $news['details'], $news['is_private']);
                                $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_news_created'));
                            }
                        } else {
                            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_create_news'));
                        }
                    }
                }
            }
        }
        $this->event_manager->processEvent('send_notifications', array());
    }

    function update() {
        $request =& $this->_controler->request;
        if ($request->exist('item')) {
            $user =& $this->_controler->getUser();
            
            $data = $request->get('item');

            $item_factory =& $this->_getItemFactory($request->get('group_id'));
            $item =& $item_factory->getItemFromDb($data['id']);
            
            // Update Owner
            $ownerChanged = false;
            if(isset($data['owner'])) {
                $_owner_id = $this->_checkOwnerChange($data['owner'], $user);
                if($_owner_id != $item->getOwnerId()) {
                    $ownerChanged = true;
                    $um = $this->_getUserManagerInstance();
                    $_oldowner = $um->getUserById($item->getOwnerId())->getName();
                    $_newowner = $um->getUserById($_owner_id)->getName();
                    $data['user_id'] = $_owner_id;
                }
                unset($data['owner']);
            }
            
            // Change creation date
            if (isset($data['create_date']) && $data['create_date'] != '') {
                $old_create_date = $item->getCreateDate();
                if ($old_create_date == $data['create_date']) {
                    $create_date_changed = false;
                } else {
                    $create_date_changed = true;
                }
            } else {
                $create_date_changed = false;
            }
            
            // Change update date
            if (isset($data['update_date']) && $data['update_date'] != '') {
                $old_update_date = $item->getUpdateDate();
                if ($old_update_date == $data['update_date']) {
                    $update_date_changed = false;
                } else {
                    $update_date_changed = true;
                }
            } else {
                $update_date_changed = false;
            }
            
            // Special handling of obsolescence date
            if(isset($data['obsolescence_date']) && $data['obsolescence_date'] != 0) {
                if(preg_match('/^([0-9]+)-([0-9]+)-([0-9]+)$/', $data['obsolescence_date'], $d)) {
                    $data['obsolescence_date'] = gmmktime(0, 0, 0, $d[2], $d[3], $d[1]);
                } else if (!preg_match('/^[0-9]*$/', $data['obsolescence_date'])) {
                    $data['obsolescence_date'] = 0;
                }
            }

            // Check is status change
            $statusChanged = false;
            if(array_key_exists('status', $data)) {
                $old_st = $item->getStatus();
                if($old_st != $data['status']) {
                    $statusChanged = true;
                }
            }

            // For empty document, check if type changed
            $createFile = false;
            $itemType = $item_factory->getItemTypeForItem($item);

            if($itemType == PLUGIN_DOCMAN_ITEM_TYPE_EMPTY && isset($data['item_type']) && $itemType != $data['item_type'] &&
                ($data['item_type'] != PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE || $this->_controler->getProperty('embedded_are_allowed'))) {
                
                if($data['item_type'] == PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE
                   || $data['item_type'] == PLUGIN_DOCMAN_ITEM_TYPE_FILE) {
                    $createFile = true;
                }
            }
            else {
                $data['item_type'] =  $itemType;
            }

            $item_factory->update($data);
            
            // Log the 'edit' event if link_url or wiki_page are set
            if (isset($data['link_url']) || isset($data['wiki_page'])) {
                $this->event_manager->processEvent('plugin_docman_event_edit', array(
                    'group_id' => $request->get('group_id'),
                    'item'     => &$item,
                    'user'     => &$user)
                );
            }

            if($ownerChanged) {
                $this->_raiseMetadataChangeEvent($user, $item, $request->get('group_id'), $_oldowner, $_newowner, 'owner');
            }

            if($statusChanged) {
               $this->_raiseMetadataChangeEvent($user, $item, $request->get('group_id'), $old_st, $data['status'], 'status');
            }
            
            if($create_date_changed) {
                $this->_raiseMetadataChangeEvent($user, $item, $request->get('group_id'), $old_create_date, $data['create_date'], 'create_date');
            }
            
            if($update_date_changed) {
                $this->_raiseMetadataChangeEvent($user, $item, $request->get('group_id'), $old_update_date, $data['update_date'], 'update_date');
            }

            if($createFile) {
                // Re-create from DB (because of type changed)
                $item = $item_factory->getItemFromDb($data['id']);
                $this->_storeFile($item);
            }

            // Update real metatata
            if($request->exist('metadata')) {
                $groupId = (int) $request->get('group_id');
                $metadata_array = $request->get('metadata');
                $mdvFactory = new Docman_MetadataValueFactory($groupId);
                $mdvFactory->updateFromRow($data['id'], $metadata_array);
                if($mdvFactory->isError()) {
                    $this->_controler->feedback->log('error', $mdvFactory->getErrorMessage());
                } else {
                    // Recursive update of properties
                    if($request->exist('recurse')) {
                        $recurseArray = $request->get('recurse');

                        // Check if all are actually inheritable
                        $metadataFactory = new Docman_MetadataFactory($groupId);
                        $inheritableMdLabelArray = $metadataFactory->getInheritableMdLabelArray();
                        if(count(array_diff($recurseArray, $inheritableMdLabelArray)) == 0) {
                            $dPm =& Docman_PermissionsManager::instance($groupId);
                            if($dPm->currentUserCanWriteSubItems($data['id'])) {
                                $subItemsWritableVisitor =& $dPm->getSubItemsWritableVisitor();
                                if($this->_controler->_actionParams['recurseOnDocs']) {
                                    $itemIdArray = $subItemsWritableVisitor->getItemIdList();
                                } else {
                                    $itemIdArray = $subItemsWritableVisitor->getFolderIdList();
                                }
                                // Remove the first element (parent item) to keep
                                // only the children.
                                array_shift($itemIdArray);
                                if(count($itemIdArray) > 0) {
                                    $recurseArray = $request->get('recurse');
                                    $mdvFactory->massUpdateFromRow($data['id'], $recurseArray, $itemIdArray);
                                    // extract cross references
                                    $reference_manager =& ReferenceManager::instance();
                                    foreach ($metadata_array as $curr_metadata_value) {
                                        foreach ($itemIdArray as $curr_item_id) {
                                            $reference_manager->extractCrossRef($curr_metadata_value, $curr_item_id, ReferenceManager::REFERENCE_NATURE_DOCUMENT, $groupId);
                                        }   
                                    }
                                } else {
                                    $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'warning_no_item_recurse'));
                                }
                            }
                        }
                    }
                }
            }

            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_item_updated'));
        }
        $this->event_manager->processEvent('send_notifications', array());
    }

    function new_version() {
        $request =& $this->_controler->request;
        if ($request->exist('id')) {
            $item_factory =& $this->_getItemFactory();
            $item =& $item_factory->getItemFromDb($request->get('id'));
            $item_type = $item_factory->getItemTypeForItem($item);
            if ($item_type == PLUGIN_DOCMAN_ITEM_TYPE_FILE || $item_type == PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE) {
                $this->_storeFile($item);
                
                // We update the update_date of the document only if no version date was given
                if (!$request->existAndNonEmpty('date')) {
                    $item_factory->update(array('id' => $item->getId()));    
                }
            }
        }
        $this->event_manager->processEvent('send_notifications', array());
    }
    protected $filestorage;
    function &_getFileStorage() {
        if (!$this->filestorage) {
            $this->filestorage =& new Docman_FileStorage($this->_controler->getProperty('docman_root'));
        }
        return $this->filestorage;
    }

    function _getItemFactory($groupId=null) {
        return new Docman_ItemFactory($groupId);
    }
    
    protected $version_factory;
    function &_getVersionFactory() {
        if (!$this->version_factory) {
            $this->version_factory =& new Docman_VersionFactory();
        }
        return $this->version_factory;
    }
    protected $permissions_manager;
    function &_getPermissionsManagerInstance(){
        if(!$this->permissions_manager){
            $this->permissions_manager =& PermissionsManager::instance(); 
        }
        return $this->permissions_manager;
    }
    
    protected $userManager;
    function _getUserManagerInstance(){
        if(!$this->userManager){
            $this->userManager = UserManager::instance(); 
        }
        return $this->userManager;
    }
    
    function move() {
        $request =& $this->_controler->request;
        if ($request->exist('id')) {
            $user =& $this->_controler->getUser();
            
            $item_factory =& $this->_getItemFactory();
            //Move in a specific folder (maybe the same)
            if ($request->exist('item_to_move')) {
                $item          =& $item_factory->getItemFromDb($request->get('item_to_move'));
                $new_parent_id = $request->get('id');
                $ordering      = $request->get('ordering');
            } else {
                //Move in the same folder
                if ($request->exist('quick_move')) {
                    $item          =& $item_factory->getItemFromDb($request->get('id'));
                    $new_parent_id = $item->getParentId();
                    switch($request->get('quick_move')) {
                        case 'move-up':
                        case 'move-down':
                        case 'move-beginning':
                        case 'move-end':
                            $ordering = substr($request->get('quick_move'), 5);
                            break;
                        default:
                            $ordering = 'beginning';
                            break;
                    }
                }
            }
            if ($item && $new_parent_id) {
                $old_parent =& $item_factory->getItemFromDb($item->getParentId());
                if ($item_factory->setNewParent($item->getId(), $new_parent_id, $ordering)) {
                    $new_parent =& $item_factory->getItemFromDb($new_parent_id);
                    $this->event_manager->processEvent('plugin_docman_event_move', array(
                        'group_id' => $request->get('group_id'),
                        'item'    => &$item, 
                        'parent'  => &$new_parent,
                        'user'    => &$user)
                    );
                    $hp = Codendi_HTMLPurifier::instance();
                    $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_item_moved', array(
                        $item->getGroupId(),
                        $old_parent->getId(), 
                        $hp->purify($old_parent->getTitle(), CODENDI_PURIFIER_CONVERT_HTML) ,
                        $new_parent->getId(), 
                        $hp->purify($new_parent->getTitle(), CODENDI_PURIFIER_CONVERT_HTML)
                    )), CODENDI_PURIFIER_DISABLED);
                } else {
                    $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_item_not_moved'));
                }
            }
        }
        $this->event_manager->processEvent('send_notifications', array());
    }
    
    /**
    * User has asked to set or to change permissions on an item
    * This method is the direct action of the docman controler but can also be called internally (@see createItem)
    * To call it directly, you have to give two extra parameters (in $params):
    * - id : the id of the item
    * - force : true if you want to bypass permissions checking (@see permission_add_ugroup). 
    *           Pretty difficult to know if a user can update the permissions which does not exist for a new item...
    * 
    * The asked permissions are given in the request, in the param 'permissions' as an array (ugroup => permission)
    *
    * Once the permissions on the top item are set (thanks to
    * Docman_Actions::_setPermissions) we can assume that those permissions are
    * correct so the algorithm to apply them recursively is just a clone. This
    * is done thanks to a callback.
    *
    * Docman_ItemFactory::breathFirst allows to navigate in children of
    * top item. And for each child node, there is a callback to
    * Docman_Actions::recursivePermission (see each method for details).
    */
    function permissions($params) {
        $request =& $this->_controler->request;
        $id = isset($params['id']) ? $params['id'] : $request->get('id');
        $force = isset($params['force']) ? $params['force'] : false;
        if ($id && $request->exist('permissions')) {
            $user =& $this->_controler->getUser();
            
            $item_factory =& $this->_getItemFactory();
            $item =& $item_factory->getItemFromDb($id);
            if ($item) {
                $permission_definition = array(
                    100 => array(
                        'order' => 0, 
                        'type'  => null,
                        'label' => null,
                        'previous' => null
                    ),
                    1 => array(
                        'order' => 1, 
                        'type'  => 'PLUGIN_DOCMAN_READ',
                        'label' => permission_get_name('PLUGIN_DOCMAN_READ'),
                        'previous' => 0
                    ),
                    2 => array(
                        'order' => 2, 
                        'type'  => 'PLUGIN_DOCMAN_WRITE',
                        'label' => permission_get_name('PLUGIN_DOCMAN_WRITE'),
                        'previous' => 1
                    ),
                    3 => array(
                        'order' => 3, 
                        'type'  => 'PLUGIN_DOCMAN_MANAGE',
                        'label' => permission_get_name('PLUGIN_DOCMAN_MANAGE'),
                        'previous' => 2
                    )
                );
                $permissions = $request->get('permissions');
                $old_permissions = permission_get_ugroups_permissions($item->getGroupId(), $item->getId(), array('PLUGIN_DOCMAN_READ','PLUGIN_DOCMAN_WRITE','PLUGIN_DOCMAN_MANAGE'), false);
                $done_permissions = array();
                $history = array(
                    'PLUGIN_DOCMAN_READ'  => false,
                    'PLUGIN_DOCMAN_WRITE' => false,
                    'PLUGIN_DOCMAN_MANAGE' => false
                );
                foreach($permissions as $ugroup_id => $wanted_permission) {
                    $this->_setPermission($item->getGroupId(), $item->getId(), $permission_definition, $old_permissions, $done_permissions, $ugroup_id, $permissions, $history, $force);
                }

                $updated = false;
                foreach($history as $perm => $put_in_history) {
                    if ($put_in_history) {
                        permission_add_history($item->getGroupId(), $perm, $item->getId());
                        $updated = true;
                    }
                }
             
                $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_perms_updated'));

                // If requested by user, apply permissions recursively on sub items
                if ($this->_controler->request->get('recursive')) {
                    //clone permissions for sub items
                    // Recursive application via a callback of Docman_Actions::recursivePermissions in
                    // Docman_ItemFactory::breathFirst
                    $item_factory->breathFirst($item->getId(), array(&$this, 'recursivePermissions'), array('id' => $item->getId()));
                    $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_perms_recursive_updated'));
                }
            }
        }
    }

    /**
     * Apply permissions of the reference item on the target item.
     *
     * This method is used as a callback by Docman_ItemFactory::breathFirst. It
     * aims to clone the permissions set on the reference item ($params['id'])
     * on a given item ($data['item_id']).
     *
     * Current user must have 'manage' permissions on the item to update its permissions. 
     *
     * @see Docman_ItemFactory::breathFirst
     *
     * $params['id']    Reference item id.
     * $data['item_id'] Item id on which permissions applies.
     */
    function recursivePermissions($data, $params) {
        if ($this->_controler->userCanManage($data["item_id"])) {
            $pm =& $this->_getPermissionsManagerInstance();
            $pm->clonePermissions($params['id'], $data["item_id"], array('PLUGIN_DOCMAN_READ', 'PLUGIN_DOCMAN_WRITE', 'PLUGIN_DOCMAN_MANAGE'));
        } else {
            $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'warning_recursive_perms', $data['title']));
        }
    }

    /**
    * Set the permission for a ugroup on an item.
    * 
    * The difficult part of the algorithm comes from two point:
    * - There is a hierarchy between ugroups (@see ugroup_get_parent)
    * - There is a hierarchy between permissions (READ < WRITE < MANAGE)
    * 
    * Let's see a scenario:
    * I've selected WRITE permission for Registered users and READ permission for Project Members
    * => Project Members ARE registered users therefore they have WRITE permission.
    * => WRITE is stronger than READ permission.
    * So the permissions wich will be set are: WRITE for registered and WRITE for project members
    * 
    * The force parameter must be set to true if you want to bypass permissions checking (@see permission_add_ugroup). 
    * Pretty difficult to know if a user can update the permissions which does not exist for a new item...
    * 
    * @param $group_id integer The id of the project
    * @param $item_id integer The id of the item
    * @param $permission_definition array The definission of the permission (pretty name, relations between perms, internal name, ...)
    * @param $old_permissions array The permissions before
    * @param &$done_permissions array The permissions after
    * @param $ugroup_id The ugroup_id we want to set permission now
    * @param $wanted_permissions array The permissions the user has asked
    * @param &$history array Does a permission has been set ?
    * @param $force boolean true if you want to bypass permissions checking (@see permission_add_ugroup).
    *
    * @access protected
    */
    function _setPermission($group_id, $item_id, $permission_definition, $old_permissions, &$done_permissions, $ugroup_id, $wanted_permissions, &$history, $force = false) {
        //Do nothing if we have already choose a permission for ugroup
        if (!isset($done_permissions[$ugroup_id])) {
            
            //if the ugroup has a parent
            if (($parent = ugroup_get_parent($ugroup_id)) !== false) {
                
                //first choose the permission for the parent
                $this->_setPermission($group_id, $item_id, $permission_definition, $old_permissions, $done_permissions, $parent, $wanted_permissions, $history, $force);
                
                //is there a conflict between given permissions?
                if ($parent = $this->_getBiggerOrEqualParent($permission_definition, $done_permissions, $parent, $wanted_permissions[$ugroup_id])) {
                    
                    //warn the user that there was a conflict
                    $this->_controler->feedback->log(
                        'warning', 
                        $GLOBALS['Language']->getText(
                            'plugin_docman', 
                            'warning_perms', 
                            array(
                                $old_permissions[$ugroup_id]['ugroup']['name'],
                                $old_permissions[$parent]['ugroup']['name'],
                                $permission_definition[$done_permissions[$parent]]['label']
                            )
                        )
                    );
                    
                    //remove permissions which was set for the ugroup
                    if (count($old_permissions[$ugroup_id]['permissions'])) {
                        foreach($old_permissions[$ugroup_id]['permissions'] as $permission => $nop) {
                            permission_clear_ugroup_object($group_id, $permission, $ugroup_id, $item_id);
                            $history[$permission] = true;
                        }
                    }
                    
                    //The permission is none (default) for this ugroup
                    $done_permissions[$ugroup_id] = 100;
                }
            }
            
            //If the permissions have not been set (no parent || no conflict)
            if (!isset($done_permissions[$ugroup_id])) {
                
                //remove permissions if needed
                $perms_cleared = false;
                if (count($old_permissions[$ugroup_id]['permissions'])) {
                    foreach($old_permissions[$ugroup_id]['permissions'] as $permission => $nop) {
                        if ($permission != $permission_definition[$wanted_permissions[$ugroup_id]]['type']) {
                            //The permission has been changed
                            permission_clear_ugroup_object($group_id, $permission, $ugroup_id, $item_id);
                            $history[$permission] = true;
                            $perms_cleared = true;
                            $done_permissions[$ugroup_id] = 100;
                        } else {
                            //keep the old permission
                            $done_permissions[$ugroup_id] = Docman_PermissionsManager::getDefinitionIndexForPermission($permission);
                        }
                    }
                }
                
                //If the user set an explicit permission and there was no perms before or they have been removed
                if ($wanted_permissions[$ugroup_id] != 100 && (!count($old_permissions[$ugroup_id]['permissions']) || $perms_cleared)){
                    //Then give the permission
                    $permission = $permission_definition[$wanted_permissions[$ugroup_id]]['type'];
                    permission_add_ugroup($group_id, $permission, $item_id, $ugroup_id, $force);
                    
                    $history[$permission] = true;
                    $done_permissions[$ugroup_id] = $wanted_permissions[$ugroup_id];
                } else {
                    //else set none(default) permission
                    $done_permissions[$ugroup_id] = 100;
                }
            }
        }
    }

    /**
    * Return the parent (or grand parent) of ugroup $parent which has a bigger permission
    * @return integer the ugroup id which has been found or false
    */
    function _getBiggerOrEqualParent($permission_definition, $done_permissions, $parent, $wanted_permission) {
        //No need to search for parent if the wanted permission is the default one
        if ($wanted_permission == 100) {
            return false;
        } else {
            //If the parent permission is bigger than the wanted permission
            if ($permission_definition[$done_permissions[$parent]]['order'] >= $permission_definition[$wanted_permission]['order']) {
                //then return parent
                return $parent;
            } else {
                //else compare with grand parents (recursively)
                if (($parent = ugroup_get_parent($parent)) !== false) {
                    return $this->_getBiggerOrEqualParent($permission_definition, $done_permissions, $parent, $wanted_permission);
                } else {
                    return false;
                }
            }
        }
    }

    function change_view() {
        $request =& HTTPRequest::instance();
        $group_id = (int) $request->get('group_id');
        if ($request->exist('selected_view')) {
            if(is_numeric($request->get('selected_view'))) {
                $this->_controler->setReportId($request->get('selected_view'));
                $this->_controler->forceView('Table');
            }
            elseif(Docman_View_Browse::isViewAllowed($request->get('selected_view'))) {
            $item_factory =& $this->_getItemFactory();
            $folder = $item_factory->getItemFromDb($request->get('id'));
            if ($folder) {
                user_set_preference(
                    PLUGIN_DOCMAN_VIEW_PREF .'_'. $folder->getGroupId(),
                    $request->get('selected_view')
                );
                $this->_controler->forceView($request->get('selected_view'));
            }
            }
        }
    }

    function delete() {
        $user =& $this->_controler->getUser();
        $request =& $this->_controler->request;

        $_sGroupId = (int) $request->get('group_id');
        $_sId      = (int) $request->get('id');

        $itemFactory = new Docman_ItemFactory($_sGroupId);
        $parentItem = $itemFactory->getItemFromDb($_sId);

        // Cannot delete root.
        if($parentItem && !$itemFactory->isRoot($parentItem)) {
            // Cannot delete one folder if at least on of the document inside
            // cannot be deleted
            $dPm =& Docman_PermissionsManager::instance($_sGroupId);
            $subItemsWritable = $dPm->currentUserCanWriteSubItems($parentItem->getId());
            if($subItemsWritable) {
                $item =& $itemFactory->getItemSubTree($parentItem, $user, false, true);
                if ($item) {
                    $deletor =& new Docman_ActionsDeleteVisitor($this->_getFileStorage(), $this->_controler);
                    if ($item->accept($deletor, array('user' => &$user, 'parent' => $itemFactory->getItemFromDb($item->getParentId())))) {
                        $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_item_deleted'));
                    }
                }
            } else {
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_item_not_deleted_no_w'));
            }
        }
        $this->event_manager->processEvent('send_notifications', array());
    }

    function admin_change_view() {
        $request =& HTTPRequest::instance();
        $group_id = (int) $request->get('group_id');

        if ($request->exist('selected_view') && Docman_View_Browse::isViewAllowed($request->get('selected_view'))) {
            require_once('Docman_SettingsBo.class.php');
            $sBo =& Docman_SettingsBo::instance($group_id);
            if ($sBo->updateView($request->get('selected_view'))) {
                $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_settings_updated'));
            } else {
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_settings_updated'));
            }
        }
    }
    
    /**
    * @deprecated
    */
    function install() {
        $request =& HTTPRequest::instance();
        $_gid = (int) $request->get('group_id');

        die('Install forbidden. Please contact administrator');
    }
    
    function admin_set_permissions() {
        list ($return_code, $feedback) = permission_process_selection_form($_POST['group_id'], $_POST['permission_type'], $_POST['object_id'], $_POST['ugroups']);
        if (!$return_code) {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_perms_updated', $feedback));
        } else {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_perms_updated'));
        }
    }

    function admin_md_details_update() {
        $request =& HTTPRequest::instance();
        $_label = $request->get('label');
        $_gid = (int) $request->get('group_id');

        $mdFactory = new Docman_MetadataFactory($_gid);
        $md =& $mdFactory->getFromLabel($_label);

        if($md !== null) {
            if($md->getGroupId() == $_gid) {

                // Name
                if($md->canChangeName()) {
                    $_name = $request->get('name');
                    $md->setName($_name);
                }
                
                // Description
                if($md->canChangeDescription()) {
                    $_descr = $request->get('descr');
                    $md->setDescription($_descr);
                }

                // Is empty allowed
                if($md->canChangeIsEmptyAllowed()) {
                    $_isEmptyAllowed = (int) $request->get('empty_allowed');

                    if($_isEmptyAllowed === 1) {
                        $md->setIsEmptyAllowed(PLUGIN_DOCMAN_DB_TRUE);
                    }
                    else {
                        $md->setIsEmptyAllowed(PLUGIN_DOCMAN_DB_FALSE);
                    }
                }

                if($md->canChangeIsMultipleValuesAllowed()) {
                    $_isMultipleValuesAllowed = (int) $request->get('multiplevalues_allowed');

                    if($_isMultipleValuesAllowed === 1) {
                        $md->setIsMultipleValuesAllowed(PLUGIN_DOCMAN_DB_TRUE);
                    }
                    else {
                        $md->setIsMultipleValuesAllowed(PLUGIN_DOCMAN_DB_FALSE);
                    }
                }

                // Usage
                if(!$md->isRequired()) {
                    $_useIt = (int) $request->get('use_it');
                    if($_useIt === 1) {
                        $md->setUseIt(PLUGIN_DOCMAN_METADATA_USED);
                    }
                    else {
                        $md->setUseIt(PLUGIN_DOCMAN_METADATA_UNUSED);
                    }
                }

                $updated = $mdFactory->update($md);
                if($updated) {
                    $this->_controler->feedback->log('info', 'Metadata successfully updated');
                }
                else {
                    $this->_controler->feedback->log('warning', 'Metadata not updated');
                }
            }
            else {
                $this->_controler->feedback->log('error', 'Given project id and metadata project id mismatch.');
                $this->_controler->feedback->log('error', 'Metadata not updated');
            }
        }
        else {
            $this->_controler->feedback->log('error', 'Bad metadata label');
            $this->_controler->feedback->log('error', 'Metadata not updated');
        }
    }

    function admin_create_metadata() {
        $request =& HTTPRequest::instance();

        $_gid          = (int) $request->get('group_id');
        $_name         = $request->get('name');
        $_description  = $request->get('descr');
        $_emptyallowed = (int) $request->get('empty_allowed');
        $_dfltvalue    = $request->get('dflt_value');
        $_useit        = $request->get('use_it');
        $_type         = (int) $request->get('type');

        $mdFactory = new Docman_MetadataFactory($_gid);

        //$mdrow['group_id'] = $_gid;
        $mdrow['name'] = $_name;
        $mdrow['description'] = $_description;
        $mdrow['data_type'] = $_type;
        //$mdrow['label'] = 
        $mdrow['required'] = false;
        $mdrow['empty_ok'] = $_emptyallowed;
        $mdrow['special'] = false;
        $mdrow['default_value'] = $_dfltvalue;
        $mdrow['use_it'] = $_useit;
        
        $md =& $mdFactory->_createFromRow($mdrow);

        $mdId = $mdFactory->create($md);
        if($mdId !== false) {
            $this->_controler->feedback->log('info', 'Property successfully created');
        }
        else {
            $this->_controler->feedback->log('error', 'An error occured on propery creation');
        }
    }


    function admin_delete_metadata() {
        $md = $this->_controler->_actionParams['md'];

        $name = $md->getName();

        $mdFactory = new Docman_MetadataFactory($md->getGroupId());

        $deleted = $mdFactory->delete($md);
        if($deleted) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman',
                                                                                   'md_del_success',
                                                                                   array($name)));
        }
        else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman',
                                                                                    'md_del_failure',
                                                                                    array($name)));
        }
        $this->_controler->view = 'RedirectAfterCrud';
        $this->_controler->_viewParams['default_url_params'] = array('action' => 'admin_metadata');
    }

    function admin_create_love() {
        $request =& HTTPRequest::instance();
        
        $_name         = $request->get('name');
        $_description  = $request->get('descr');
        $_rank         = $request->get('rank');
        //$_dfltvalue    = (int) $request->get('dflt_value');
        $_mdLabel      = $request->get('md');
        $_gid          = (int) $request->get('group_id');

        $mdFactory = new Docman_MetadataFactory($_gid);
        $md =& $mdFactory->getFromLabel($_mdLabel);
        
        if($md !== null 
           && $md->getType() == PLUGIN_DOCMAN_METADATA_TYPE_LIST 
           && $md->getLabel() != 'status') {

            $loveFactory = new Docman_MetadataListOfValuesElementFactory($md->getId());
            
            $love = new Docman_MetadataListOfValuesElement();
            $love->setName($_name);
            $love->setDescription($_description);
            $love->setRank($_rank);
            $loveFactory->create($love);
        }
    }

    function admin_delete_love() {
        $request =& HTTPRequest::instance();

        $_loveId  = (int) $request->get('loveid');
        $_mdLabel = $request->get('md');
        $_gid = (int) $request->get('group_id');

        $mdFactory = new Docman_MetadataFactory($_gid);
        $md =& $mdFactory->getFromLabel($_mdLabel);

        if($md !== null
           && $md->getType() == PLUGIN_DOCMAN_METADATA_TYPE_LIST
           && $md->getLabel() != 'status') {

            $love = new Docman_MetadataListOfValuesElement($md->getId());
            $love->setId($_loveId);

            // Delete value
            $loveFactory = new Docman_MetadataListOfValuesElementFactory($md->getId());
            $deleted = $loveFactory->delete($love);
            if($deleted) {
                $this->_controler->feedback->log('info', 'Element successfully deleted.');
                $this->_controler->feedback->log('info', 'Documents labeled with the deleted element were reset to the "None" value.');
            } else {
                $this->_controler->feedback->log('error', 'An error occured on element deletion.');
            }
        }
        else {
            // Sth really strange is happening... user try to delete a value
            // that do not belong to a metadata with a List type !?
            // If this happen, shutdown the server, format the hard drive and
            // leave computer science to keep goat on the Larzac.
        }
    }

    function admin_update_love() {
        $md   = $this->_controler->_actionParams['md'];
        $love = $this->_controler->_actionParams['love'];

        $loveFactory = new Docman_MetadataListOfValuesElementFactory($md->getId());
        $updated = $loveFactory->update($love);

        if($updated) {
            $this->_controler->feedback->log('info', 'Element successfully updated');

            $this->_controler->view = 'RedirectAfterCrud';
            $this->_controler->_viewParams['default_url_params']  = array('action' => 'admin_md_details',
                                                                          'md'     => $md->getLabel());
        }
        else {
            $this->_controler->feedback->log('error', 'Unable to update element.');

            $this->_controler->view = 'RedirectAfterCrud';
            $this->_controler->_viewParams['default_url_params']  = array('action' => 'admin_display_love',
                                                                          'md'     => $md->getLabel(),
                                                                          'loveid' => $love->getId());
        }
    }
    
    function admin_import_metadata() {
        $groupId    = $this->_controler->_actionParams['sGroupId'];
        $srcGroupId = $this->_controler->_actionParams['sSrcGroupId'];

        $srcGo = group_get_object($srcGroupId);
        if($srcGo != false &&
           ($srcGo->isPublic() 
            || (!$srcGo->isPublic() && $srcGo->userIsMember()))) {            
            $mdFactory = new Docman_MetadataFactory($srcGo->getGroupId());
            $mdFactory->exportMetadata($groupId);
            
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'admin_md_import_success', array($srcGo->getPublicName())));
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_perms_generic'));
        }
    }

    function monitor($params) {
        $user = $this->_controler->getUser();
        if (!$user->isAnonymous()) {
            $something_happen  = false;
            $already_monitored = $this->_controler->notificationsManager->exist($user->getId(), $params['item']->getId());
            $already_cascaded  = $this->_controler->notificationsManager->exist($user->getId(), $params['item']->getId(), PLUGIN_DOCMAN_NOTIFICATION_CASCADE);
            if ($params['monitor'] && !$already_monitored) {
                //monitor
                if (!$this->_controler->notificationsManager->add($user->getId(), $params['item']->getId())) {
                    $this->_controler->feedback->log('error', "Unable to add monitoring on '". $params['item']->getTitle() ."'.");
                }
                $something_happen = true;
            } else if(!$params['monitor'] && $already_monitored) {
                //unmonitor
                if (!$this->_controler->notificationsManager->remove($user->getId(), $params['item']->getId())) {
                    $this->_controler->feedback->log('error', "Unable to remove monitoring on '". $params['item']->getTitle() ."'.");
                }
                $something_happen = true;
            }
            if (isset($params['cascade']) && $params['cascade'] && $params['monitor'] && !$already_cascaded) {
                //cascade
                if (!$this->_controler->notificationsManager->add($user->getId(), $params['item']->getId(), PLUGIN_DOCMAN_NOTIFICATION_CASCADE)) {
                    $this->_controler->feedback->log('error', "Unable to add cascade on '". $params['item']->getTitle() ."'.");
                }
                $something_happen = true;
            } else if(!(isset($params['cascade']) && $params['cascade'] && $params['monitor']) && $already_cascaded) {
                //uncascade
                if (!$this->_controler->notificationsManager->remove($user->getId(), $params['item']->getId(), PLUGIN_DOCMAN_NOTIFICATION_CASCADE)) {
                    $this->_controler->feedback->log('error', "Unable to remove cascade on '". $params['item']->getTitle() ."'.");
                }
                $something_happen = true;
            }
            //Feedback
            if ($something_happen) {
                if ($this->_controler->notificationsManager->exist($user->getId(), $params['item']->getId())) {
                    if ($this->_controler->notificationsManager->exist($user->getId(), $params['item']->getId(), PLUGIN_DOCMAN_NOTIFICATION_CASCADE)) {
                        $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'notifications_cascade_on', array($params['item']->getTitle())));
                    } else {
                        $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'notifications_on', array($params['item']->getTitle())));
                    }
                } else {
                    $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'notifications_off', array($params['item']->getTitle())));
                }
            }
        }
    }

    function action_copy($params) {
        //
        // Param
        $user = $this->_controler->getUser();
        $item = $this->_controler->_actionParams['item'];

        //
        // Action
        $itemFactory =& $this->_getItemFactory();

        $itemFactory->delCopyPreference();
        $itemFactory->setCopyPreference($item);

        //
        // Message
        $type = $itemFactory->getItemTypeForItem($item);
        if(PLUGIN_DOCMAN_ITEM_TYPE_FOLDER == $type) {
            $_itemtype = 'folder';
        }
        else {
            $_itemtype = 'doc';
        }
        
        $_logmsg = $GLOBALS['Language']->getText('plugin_docman', 'info_copy_notify_cp_'.$_itemtype).' '.$GLOBALS['Language']->getText('plugin_docman', 'info_copy_notify_cp');
        
        $this->_controler->feedback->log('info', $_logmsg, CODENDI_PURIFIER_LIGHT);
    }

    function paste($params) {
        //
        // Param
        $user        = $this->_controler->getUser();
        $item        = $this->_controler->_actionParams['item'];
        $rank        = $this->_controler->_actionParams['rank'];
        $itemToPaste = $this->_controler->_actionParams['itemToPaste'];
        $importMd    = $this->_controler->_actionParams['importMd'];
        $dataRoot    = $this->_controler->getProperty('docman_root');
        $mdMapping   = false;

        $srcMdFactory = new Docman_MetadataFactory($itemToPaste->getGroupId());

        // Import metadata if asked
        if($importMd) {
            $srcMdFactory->exportMetadata($item->getGroupId());
        }
        
        // Get mapping between the 2 definitions
        $mdMapping = array();
        $srcMdFactory->getMetadataMapping($item->getGroupId(), $mdMapping);

        // Permissions
        if($itemToPaste->getGroupId() != $item->getGroupId()) {
            $ugroupsMapping = false;
        } else {
            $ugroupsMapping = true;
        }

        //
        // Action
        $itemFactory =& $this->_getItemFactory();
        $itemFactory->cloneItems($itemToPaste->getGroupId(),
                                 $item->getGroupId(),
                                 $user, 
                                 $mdMapping,
                                 $ugroupsMapping,
                                 $dataRoot, 
                                 $itemToPaste->getId(),
                                 $item->getId(), 
                                 $rank);

        $itemFactory->delCopyPreference();

        //
        // Message
        
    }

    /**
     * @access private
     */
    function _approval_update_settings(&$atf, $sStatus, $notification, $description, $owner) {
        $table =& $atf->getTable();
        $newOwner = false;
        if(!$table->isCustomizable()) {
            // Cannot set status of an old table to something else than 'close'
            // or 'deleted'
            if ($sStatus != PLUGIN_DOCMAN_APPROVAL_TABLE_CLOSED &&
                $sStatus != PLUGIN_DOCMAN_APPROVAL_TABLE_DELETED) {
                $sStatus = PLUGIN_DOCMAN_APPROVAL_TABLE_CLOSED;
            }
            // Ensure that, once the table belong to an old version, user
            // cannot change the notification type.
            $notification = $table->getNotification();
            $newOwner = $table->getOwner();
        }

        // Change owner
        if($newOwner === false) {
            $_owner = util_user_finder($owner);
            if($_owner == "") {
                $newOwner = $table->getOwner();
            } else {
                $dbresults = user_get_result_set_from_unix($_owner);
                $newOwner  = db_result($dbresults, 0, 'user_id');
                $_owner_status = db_result($dbresults, 0, 'status');
                if($newOwner === false || $newOwner < 1 || !($_owner_status == 'A' || $_owner_status = 'R')) {
                    $newOwner = $table->getOwner();
                }
            }
        }

        // Update settings
        $updated = $atf->updateTable($sStatus, $notification, $description, $newOwner);
        if($updated) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_tableupd_success'));
        }
    }

    /**
     * @access private
     */
    function _approval_update_add_users($atrf, $usUserList, $sUgroups) {
        $noError = true;
        $userAdded = false;

        // Update users
        if(trim($usUserList) != '') {
            $usUserArray = explode(',', $usUserList);
            // First add individual users
            if(count($usUserArray) > 0) {
                $nbUserAdded = $atrf->addUsers($usUserArray);
                if($nbUserAdded < count($usUserArray)) {
                    $noError = false;
                } else {
                    $userAdded = true;
                }
            }
        }

        // Then add ugroups.
        if($sUgroups !== null && count($sUgroups) > 0) {
            foreach($sUgroups as $ugroup) {
                $ugroupAdded = false;
                if($ugroup > 0 && $ugroup != 100) {
                    if($atrf->addUgroup($ugroup)) {
                        $ugroupAdded = true;
                    } else {
                        $noError = false;
                    }
                }
            }
        }

        $purifier =& Codendi_HTMLPurifier::instance();

        if(count($atrf->err['db']) > 0) {
            $ua  = array_unique($atrf->err['db']);
            $ua  = $purifier->purifyMap($ua);
            $uas = implode(', ', $ua);
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_useradd_err_db', $uas));
        }
        if(count($atrf->err['perm']) > 0) {
            $ua  = array_unique($atrf->err['perm']);
            $ua  = $purifier->purifyMap($ua);
            $uas = implode(', ', $ua);
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_useradd_err_perm', $uas));
        }
        if(count($atrf->err['notreg']) > 0) {
            $ua  = array_unique($atrf->err['notreg']);
            $ua  = $purifier->purifyMap($ua);
            $uas = implode(', ', $ua);
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_useradd_err_notreg', $uas));
        }
        if(count($atrf->warn['double']) > 0) {
            $ua  = array_unique($atrf->warn['double']);
            $ua  = $purifier->purifyMap($ua);
            $uas = implode(', ', $ua);
            $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'approval_useradd_warn_double', $uas));
        }

        if($userAdded && $noError) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_useradd_success'));
        }
    }

    /**
     * @access private
     */
    function _approval_update_del_users($atrf, $selectedUsers) {
        $deletedUsers = 0;
        foreach($selectedUsers as $userId) {
            if($atrf->delUser($userId)) {
                $deletedUsers++;
            }
        }

        if(count($selectedUsers) == $deletedUsers) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_userdel_success'));
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_userdel_failure'));
        }
    }

    /**
     * @access private
     */
    function _approval_update_notify_users($atrf, $selectedUsers) {
        $notifiedUsers = 0;
        $atnc = $atrf->_getApprovalTableNotificationCycle(true);
        // For each reviewer, if he is selected, notify it
        // This allow us to verify that we actully notify people
        // member of the table!
        $table = $atnc->getTable();
        $ri = $table->getReviewerIterator();
        while($ri->valid()) {
            $reviewer = $ri->current();
            if(in_array($reviewer->getId(), $selectedUsers)) {
                if($atnc->notifyIndividual($reviewer->getId())) {
                    $notifiedUsers++;
                }
            }
            $ri->next();
        }
        if(count($selectedUsers) == $notifiedUsers) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_force_notify_success'));
        }
    }

    /**
     * @access private
     */
    function _approval_update_notif_resend($atrf) {
        $res = $atrf->notifyReviewers();
        if($res) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_notification_success'));
        } else {
            $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'approval_notification_failure'));
        }
    }

    function approval_update() {
        // Params
        $item       = $this->_controler->_actionParams['item'];
        $user       = $this->_controler->getUser();
        $sStatus    = $this->_controler->_actionParams['status'];
        $notification = $this->_controler->_actionParams['notification'];
        $description =  $this->_controler->_actionParams['description'];
        $usUserList = $this->_controler->_actionParams['user_list'];
        $sUgroup    = $this->_controler->_actionParams['ugroup_list'];
        $sSelUser   = $this->_controler->_actionParams['sel_user'];
        $sSelUserAct = $this->_controler->_actionParams['sel_user_act'];
        $resendNotif = $this->_controler->_actionParams['resend_notif'];
        $version     = $this->_controler->_actionParams['version'];
        $import = $this->_controler->_actionParams['import'];
        $owner = $this->_controler->_actionParams['table_owner'];

        $atf =& Docman_ApprovalTableFactory::getFromItem($item, $version);
        $table = $atf->getTable();
        $oldTable = null;
        if($table !== null) {
            $oldTable = clone $atf->getTable();
        }

        $tableEditable = false;
        if($oldTable === null || ($import !== false && $import !== 'keep')) {
            $created = $atf->createTable($user->getId(), $import);
            if(!$created) {
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_tableins_failure'));
            }
        }

        if($import === false || $import == 'keep') {
            // New table created "from scratch" (ie. without the import
            // selector) are directly editable.
            $tableEditable = true;
        }

        if($tableEditable) {
            $this->_approval_update_settings($atf, $sStatus, $notification, $description, $owner);
            $table =& $atf->getTable();
            if(!$table->isClosed()) {
                $atrf =& new Docman_ApprovalTableReviewerFactory($table, $item);
                $this->_approval_update_add_users($atrf, $usUserList, $sUgroup);
                if(is_array($sSelUser) && count($sSelUser) > 0) {
                    switch($sSelUserAct){
                    case 'del':
                        $this->_approval_update_del_users($atrf, $sSelUser);
                        break;
                    case 'mail':
                        $this->_approval_update_notify_users($atrf, $sSelUser);
                        break;
                    }
                }
                // If needed, notify next reviewer
                if(($oldTable !== null
                    && $oldTable->getStatus() != PLUGIN_DOCMAN_APPROVAL_TABLE_ENABLED
                    && $table->getStatus() == PLUGIN_DOCMAN_APPROVAL_TABLE_ENABLED)
                   || $resendNotif) {
                    $this->_approval_update_notif_resend($atrf);
                }
            }
        }
    }

    function approval_delete() {
        // Params
        $item = $this->_controler->_actionParams['item'];
        $version = $this->_controler->_actionParams['version'];
        $atf =& Docman_ApprovalTableFactory::getFromItem($item, $version);
        $deleted = $atf->deleteTable();
        if($deleted) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_tabledel_success'));
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_tabledel_failure'));
        }
    }

   function approval_upd_user() {
        // Params
        $item    = $this->_controler->_actionParams['item'];
        $sUserId = $this->_controler->_actionParams['user_id'];
        $usRank  = $this->_controler->_actionParams['rank'];

        // Action
        $atrf =& Docman_ApprovalTableFactory::getReviewerFactoryFromItem($item);
        $atrf->updateUser($sUserId, $usRank);
    }

    function approval_user_commit() {
        // Params
        $item      = $this->_controler->_actionParams['item'];
        $svState   = $this->_controler->_actionParams['svState'];
        $sVersion  = $this->_controler->_actionParams['sVersion'];
        $usComment = $this->_controler->_actionParams['usComment'];
        $user      = $this->_controler->getUser();

        $review = new Docman_ApprovalReviewer();
        $review->setId($user->getId());
        $review->setState($svState);
        $review->setComment($usComment);
        if($svState != PLUGIN_DOCMAN_APPROVAL_STATE_NOTYET) {
            $review->setVersion($sVersion);
            $review->setReviewDate(time());
        }
        else {
            $review->setVersion(null);
            $review->setReviewDate(null);
        }

        $atrf =& Docman_ApprovalTableFactory::getReviewerFactoryFromItem($item);
        $updated = $atrf->updateReview($review);
        if($updated) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_review_success'));
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_review_failure'));
        }

        $this->monitor($this->_controler->_actionParams);
    }

    function report_del() {
        $user      = $this->_controler->getUser();
        $reportId  = $this->_controler->_actionParams['sReportId'];
        $groupId   = $this->_controler->_actionParams['sGroupId'];

        $reportFactory = new Docman_ReportFactory($groupId);
        $r = $reportFactory->getReportById($reportId);
        if($r == null) {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'report_del_notfound'));
        } else {
            if($r->getScope() == 'I' && $r->getUserId() != $user->getId()) {
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'report_del_notowner'));
            } else {
                if($r->getScope() == 'P' && !$this->_controler->userCanAdmin()) {
                    $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'report_del_notadmin'));
                } else {
                    if($reportFactory->deleteReport($r)) {
                        $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'report_del_success'));
                    } else {
                        $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'report_del_failure'));
                    }
                }
            }
        }
    }

    function report_upd() {
        $reportId = $this->_controler->_actionParams['sReportId'];
        $groupId  = $this->_controler->_actionParams['sGroupId'];
        $scope    = $this->_controler->_actionParams['sScope'];
        $title    = $this->_controler->_actionParams['title'];
        $description = $this->_controler->_actionParams['description'];
        $image    = $this->_controler->_actionParams['sImage'];

        
        $reportFactory = new Docman_ReportFactory($groupId);
        $r = $reportFactory->getReportById($reportId);
        if($r == null) {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'report_upd_notfound'));
        } else {
            if($r->getGroupId() != $groupId) {
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'report_upd_groupmismatch'));
            } else {
                if($this->_controler->userCanAdmin()) {
                    $r->setScope($scope);
                }
                $r->setTitle($title);
                $r->setDescription($description);
                $r->setImage($image);
                $reportFactory->updateReportSettings($r);
            }
        }
    }

    function report_import() {
        $groupId        = $this->_controler->_actionParams['sGroupId'];
        $importReportId = $this->_controler->_actionParams['sImportReportId'];
        $importGroupId  = $this->_controler->_actionParams['sImportGroupId'];
        $user           =& $this->_controler->getUser();

        // Any user can importreports from any public projects and from
        // Private projects he is member of.
        $go = group_get_object($importGroupId);
        if($go != false &&
           ($go->isPublic() 
            || (!$go->isPublic() && $go->userIsMember()))) {
            $srcReportFactory = new Docman_ReportFactory($importGroupId);

            // Get the mapping between src and current project metadata definition.
            $mdMap = array();
            $srcMdFactory = new Docman_MetadataFactory($importGroupId);
            $srcMdFactory->getMetadataMapping($groupId, $mdMap);

            // Get the mapping between src and current project items definition for the item involved
            // in the reports.
            $itemMapping = array();
            // Get involved items
            $srcReportItems = $srcReportFactory->getReportsItems($importReportId);
            if(count($srcReportItems) > 0) {
                // Get the subtree from the original docman on which reports applies
                $srcItemFactory = new Docman_ItemFactory($importGroupId);
                $srcItemTree = $srcItemFactory->getItemTreeFromLeaves($srcReportItems, $user);
                if($srcItemTree !== null) {
                    // Final step: find in the current ($groupId) docman
                    $dstItemFactory = new Docman_ItemFactory($groupId);
                    $itemMapping = $dstItemFactory->getItemMapping($srcItemTree);
                }
            }

            // If user is admin he can create 'P' report otherwise everything is 'I'
            $forceScope = true;
            if($this->_controler->userCanAdmin()) {
                $forceScope = false;
            }

            if($importReportId !== null) {
                // Import only one report                
                $report = $srcReportFactory->getReportById($importReportId);
                
                if($report !== null) {
                    // User can import Project wide reports or his own Individual reports.
                    if($report->getScope() == 'P' ||
                       ($report->getScope() == 'I' && $report->getUserId() == $user->getId())) {
                        
                        $srcReportFactory->cloneReport($report, $groupId, $mdMap, $user, $forceScope, $itemMapping);

                        $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'report_clone_success'));
                    } else {
                        $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'report_err_clone_iorp'));
                    }
                } else {
                    $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'report_err_notfound', array($importReportId)));
                }
            } else {
                // Import all personal and project reports from the given project.
                $srcReportFactory->copy($groupId, $mdMap, $user, $forceScope, $itemMapping);
                $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'report_clone_success'));
            }
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_perms_generic'));
        }
    }
}

?>

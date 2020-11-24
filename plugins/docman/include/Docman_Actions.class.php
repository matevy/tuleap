<?php
/**
 * Copyright © Enalean, 2011 - Present. All Rights Reserved.
 * Copyright (c) STMicroelectronics, 2006. All Rights Reserved.
 *
 * Originally written by Manuel Vacelet, 2006
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

use Tuleap\Docman\DeleteFailedException;
use Tuleap\Docman\DestinationCloneItem;
use Tuleap\Docman\Metadata\MetadataRecursiveUpdator;
use Tuleap\Docman\Metadata\ItemImpactedByMetadataChangeCollection;
use Tuleap\Docman\Metadata\Owner\OwnerRetriever;
use Tuleap\Docman\Permissions\PermissionItemUpdater;

require_once __DIR__ . '/../../../src/www/news/news_utils.php';

/**
 * @template-extends Actions<Docman_Controller>
 */
class Docman_Actions extends Actions
{
    var $event_manager;

    public function __construct($controler)
    {
        parent::__construct($controler);
        $this->event_manager = $this->_getEventManager();
    }

    protected function _getEventManager()
    {
        $em = EventManager::instance();
        return $em;
    }

    private function getPermissionItemUpdater(Docman_Item $item) : PermissionItemUpdater
    {
        return new PermissionItemUpdater(
            $this->_controler->feedback,
            $this->_getItemFactory(),
            $this->_getDocmanPermissionsManagerInstance($item->getGroupId()),
            $this->_getPermissionsManagerInstance(),
            $this->_getEventManager()
        );
    }

    function expandFolder()
    {
        $folderFactory = new Docman_FolderFactory();
        $folderFactory->expand($this->_getFolderFromRequest());
    }
    function expandAll($params)
    {
        $params['hierarchy']->accept(new Docman_ExpandAllHierarchyVisitor(), array('folderFactory' => new Docman_FolderFactory()));
    }
    function collapseFolder()
    {
        $folderFactory = new Docman_FolderFactory();
        $folderFactory->collapse($this->_getFolderFromRequest());
    }
    private function _getFolderFromRequest()
    {
        $request = HTTPRequest::instance();
        $folder = new Docman_Folder();
        $folder->setId((int) $request->get('id'));
        $folder->setGroupId((int) $request->get('group_id'));
        return $folder;
    }

    //@todo need to check owner rights on parent

    /**
     * protected for testing purpose (SOAP)
     */
    protected function _checkOwnerChange(string $new_owner_name, PFUser $change_requestor)
    {
        $new_owner_id = (new OwnerRetriever($this->_getUserManagerInstance()))->getOwnerIdFromLoginName($new_owner_name);
        if ($new_owner_id === null) {
            $this->_controler->feedback->log(
                Feedback::WARN,
                $GLOBALS['Language']->getText('plugin_docman', 'warning_missingowner')
            );
            return $change_requestor->getId();
        }
        return $new_owner_id;
    }

    private function _raiseMetadataChangeEvent(&$user, &$item, $group_id, $old, $new, $field)
    {
        $logEventParam = array('group_id' => $group_id,
                               'item'     => &$item,
                               'user'     => &$user,
                               'old_value' => $old,
                               'new_value' => $new,
                               'field'     => $field);

        $this->event_manager->processEvent(
            'plugin_docman_event_metadata_update',
            $logEventParam
        );
    }

    /**
     * This function handle file storage regarding user parameters.
     *
     * @access: private
     *
     */
    function _storeFile($item)
    {
        $fs       = $this->_getFileStorage();
        $user     = $this->_controler->getUser();
        $request  = $this->_controler->request;
        $iFactory = $this->_getItemFactory();
        $vFactory = $this->_getVersionFactory();

        $uploadSucceded = false;
        $newVersion     = null;

        $_label     = '';
        $_changelog = '';

        $nextNb = $vFactory->getNextVersionNumber($item);
        if ($nextNb === false) {
            $number       = 1;
            $_action_type = 'initversion';
            $_changelog   = 'Initial version';
        } else {
            $number       = $nextNb;
            $_action_type = 'newversion';
        }

        // Prepare label and changelog from user input
        $data_version = $request->get('version');
        if ($data_version) {
            if (isset($data_version['label'])) {
                $_label = $data_version['label'];
            }
            if (isset($data_version['changelog'])) {
                $_changelog = $data_version['changelog'];
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

                        $_filesize = filesize($path);

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

                $mime_type_detector = new Docman_MIMETypeDetector();
                if ($path && $mime_type_detector->isAnOfficeFile($_filename)) {
                    $_filetype = $mime_type_detector->getRightOfficeType($_filename);
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

        if ($uploadSucceded) {
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

            // Create a new version object
            $vArray['id'] = $vId;
            $vArray['date'] = $_SERVER['REQUEST_TIME'];
            $newVersion = new Docman_Version($vArray);

            $eArray = array('group_id' => $item->getGroupId(),
                            'item'     => &$item,
                            'version'  => $newVersion,
                            'user'     => &$user);
            $this->event_manager->processEvent('plugin_docman_event_new_version', $eArray);
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_create_'.$_action_type));

            // Approval table
            if ($number > 0) {
                // Approval table creation needs the item currentVersion to be set.
                $vArray['id']   = $vId;
                $vArray['date'] = $_SERVER['REQUEST_TIME'];
                $newVersion     = new Docman_Version($vArray);
                $item->setCurrentVersion($newVersion);

                $this->newVersionApprovalTable($request, $item, $user);
            }
        } else {
            //TODO What should we do if upload failed ?
            //Maybe cancel item ?
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_create_'.$_action_type));
        }
        return $newVersion;
    }

    private function newVersionApprovalTable(Codendi_Request $request, Docman_Item $item, PFUser $user)
    {
        $vImport = new Valid_WhiteList('app_table_import', array('copy', 'reset', 'empty'));
        $vImport->required();
        $import = $request->getValidated('app_table_import', $vImport, false);
        if ($import) {
            $atf = Docman_ApprovalTableFactoriesFactory::getFromItem($item);
            $atf->createTable($user->getId(), $request->get('app_table_import'));
        }
    }

    function createFolder()
    {
        $this->createItem();
    }

    function createDocument()
    {
        $this->createItem();
    }

    function createItem()
    {
        $request = $this->_controler->request;
        $item_factory = $this->_getItemFactory();
        if ($request->exist('item')) {
            $item = $request->get('item');

            if (isset($item['title'])) {
                $item['title'] = trim($item['title']);
            }

            if ($item['item_type'] != PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE
                    ||
                    (
                        $this->_controler->getProperty('embedded_are_allowed')
                        &&
                        $request->exist('content')
                    )
                ) {
                // Special handling of obsolescence date
                if (isset($item['obsolescence_date']) && $item['obsolescence_date'] != 0) {
                    if (preg_match('/^([0-9]+)-([0-9]+)-([0-9]+)$/', $item['obsolescence_date'], $d)) {
                        $item['obsolescence_date'] = mktime(0, 0, 0, $d[2], $d[3], $d[1]);
                    } elseif (!preg_match('/^[0-9]*$/', $item['obsolescence_date'])) {
                        $item['obsolescence_date'] = 0;
                    }
                } else {
                    $item['obsolescence_date'] = 0;
                }

                $user = $this->_controler->getUser();

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
                    $this->_controler->_viewParams['redirect_anchor'] = "#item_$id";
                    $new_item = $item_factory->getItemFromDb($id);
                    $parent   = $item_factory->getItemFromDb($item['parent_id']);
                    if ($request->exist('permissions') && $this->_controler->userCanManage($parent->getId())) {
                        $this->getPermissionItemUpdater($new_item)->initPermissionsOnNewlyCreatedItem(
                            $new_item,
                            $this->_controler->request->get('permissions')
                        );
                    } else {
                        $pm = $this->_getPermissionsManagerInstance();
                        $pm->clonePermissions($item['parent_id'], $id, array('PLUGIN_DOCMAN_READ', 'PLUGIN_DOCMAN_WRITE', 'PLUGIN_DOCMAN_MANAGE'));
                    }
                    $new_item->fireEvent('plugin_docman_event_add', $user, $parent);

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
                    if ($item['item_type'] == PLUGIN_DOCMAN_ITEM_TYPE_FOLDER) {
                        $info_item_created = 'info_folder_created';
                    }
                    $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_document_created'));

                    $new_version = null;
                    if ($item['item_type'] == PLUGIN_DOCMAN_ITEM_TYPE_FILE ||
                       $item['item_type'] == PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE) {
                        $new_version = $this->_storeFile($new_item);
                    }

                    if ($item['item_type'] ==  PLUGIN_DOCMAN_ITEM_TYPE_LINK) {
                        $link_version_factory = new Docman_LinkVersionFactory();
                        $link_version_factory->create(
                            $new_item,
                            $GLOBALS['Language']->getText('plugin_docman', 'initversion'),
                            $GLOBALS['Language']->getText('plugin_docman', 'initversion'),
                            $_SERVER['REQUEST_TIME']
                        );
                    }

                    // Create metatata
                    if ($request->exist('metadata')) {
                        $metadata_array = $request->get('metadata');
                        $mdvFactory = new Docman_MetadataValueFactory($request->get('group_id'));
                        $mdvFactory->createFromRow($id, $metadata_array);
                        if ($mdvFactory->isError()) {
                            $this->_controler->feedback->log('error', $mdvFactory->getErrorMessage());
                        }
                    }

                    //Submit News about this document
                    if ($request->exist('news')) {
                        if ($user->isMember($request->get('group_id'), 'A') || $user->isMember($request->get('group_id'), 'N1') || $user->isMember($request->get('group_id'), 'N2')) { //only for allowed people
                            $news = $request->get('news');
                            if (isset($news['summary']) && trim($news['summary']) && isset($news['details']) && trim($news['details']) && isset($news['is_private'])) {
                                news_submit($request->get('group_id'), $news['summary'], $news['details'], $news['is_private'], false);
                                $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_news_created'));
                            }
                        } else {
                            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_create_news'));
                        }
                    }

                    $folderFactory = $this->_getFolderFactory();
                    $folderFactory->expand($parent);

                    $item_type = $item_factory->getItemTypeForItem($new_item);

                    switch ($item_type) {
                        case PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE:
                        case PLUGIN_DOCMAN_ITEM_TYPE_FILE:
                            $this->event_manager->processEvent(
                                'plugin_docman_after_new_document',
                                array(
                                    'item'     => $new_item,
                                    'user'     => $user,
                                    'version'  => $new_version,
                                    'docmanControler' => $this->_controler
                                )
                            );

                            break;

                        case PLUGIN_DOCMAN_ITEM_TYPE_WIKI:
                            assert($new_item instanceof Docman_Wiki);
                            $this->event_manager->processEvent(
                                'plugin_docman_event_new_wikipage',
                                array(
                                    'item'      => $new_item,
                                    'group_id'  => $new_item->getGroupId(),
                                    'wiki_page' => $new_item->getPagename()
                                )
                            );

                            break;

                        case PLUGIN_DOCMAN_ITEM_TYPE_EMPTY:
                            $this->event_manager->processEvent(
                                PLUGIN_DOCMAN_EVENT_NEW_EMPTY,
                                array(
                                    'item' => $new_item
                                )
                            );

                            break;

                        case PLUGIN_DOCMAN_ITEM_TYPE_LINK:
                            $this->event_manager->processEvent(
                                PLUGIN_DOCMAN_EVENT_NEW_LINK,
                                array(
                                    'item' => $new_item
                                )
                            );

                            break;

                        case PLUGIN_DOCMAN_ITEM_TYPE_FOLDER:
                            $this->event_manager->processEvent(
                                PLUGIN_DOCMAN_EVENT_NEW_FOLDER,
                                array(
                                    'item' => $new_item
                                )
                            );

                            break;

                        default:
                            break;
                    }
                }
            }
        }
        $this->event_manager->processEvent('send_notifications', array());
    }

    function update()
    {
        $request = $this->_controler->request;
        if ($request->exist('item')) {
            $user = $this->_controler->getUser();

            $data = $request->get('item');

            if (isset($data['title'])) {
                $data['title'] = trim($data['title']);
            }

            $item_factory = $this->_getItemFactory($request->get('group_id'));
            $item         = $item_factory->getItemFromDb($data['id']);

            // Update Owner
            $ownerChanged = false;
            if (isset($data['owner'])) {
                $_owner_id = $this->_checkOwnerChange($data['owner'], $user);
                if ($_owner_id != $item->getOwnerId()) {
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
            if (isset($data['obsolescence_date']) && $data['obsolescence_date'] != 0) {
                if (preg_match('/^([0-9]+)-([0-9]+)-([0-9]+)$/', $data['obsolescence_date'], $d)) {
                    $data['obsolescence_date'] = gmmktime(0, 0, 0, $d[2], $d[3], $d[1]);
                } elseif (!preg_match('/^[0-9]*$/', $data['obsolescence_date'])) {
                    $data['obsolescence_date'] = 0;
                }
            }

            // Check is status change
            $statusChanged = false;
            if (array_key_exists('status', $data)) {
                $old_st = $item->getStatus();
                if ($old_st != $data['status']) {
                    $statusChanged = true;
                }
            }

            // For empty document, check if type changed
            $createFile = false;
            $itemType = $item_factory->getItemTypeForItem($item);

            if ($itemType == PLUGIN_DOCMAN_ITEM_TYPE_EMPTY && isset($data['item_type']) && $itemType != $data['item_type'] &&
                ($data['item_type'] != PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE || $this->_controler->getProperty('embedded_are_allowed'))) {
                if ($data['item_type'] == PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE
                   || $data['item_type'] == PLUGIN_DOCMAN_ITEM_TYPE_FILE) {
                    $createFile = true;
                }
            } else {
                $data['item_type'] =  $itemType;
            }

            $updated = $item_factory->update($data);
            if ($updated) {
                $this->event_manager->processEvent('plugin_docman_event_update', array(
                    'group_id' => $request->get('group_id'),
                    'item'     => $item,
                    'new'      => $data,
                    'user'     => $user));
            }

            // Log the 'edit' event if link_url or wiki_page are set
            if (isset($data['link_url']) || isset($data['wiki_page'])) {
                $this->event_manager->processEvent('plugin_docman_event_edit', array(
                    'group_id' => $request->get('group_id'),
                    'item'     => &$item,
                    'user'     => &$user));
            }

            if ($ownerChanged) {
                $this->_raiseMetadataChangeEvent($user, $item, $request->get('group_id'), $_oldowner, $_newowner, 'owner');
            }

            if ($statusChanged) {
                $this->_raiseMetadataChangeEvent($user, $item, $request->get('group_id'), $old_st, $data['status'], 'status');
            }

            if ($create_date_changed) {
                $this->_raiseMetadataChangeEvent($user, $item, $request->get('group_id'), $old_create_date, $data['create_date'], 'create_date');
            }

            if ($update_date_changed) {
                $this->_raiseMetadataChangeEvent($user, $item, $request->get('group_id'), $old_update_date, $data['update_date'], 'update_date');
            }

            if ($createFile) {
                // Re-create from DB (because of type changed)
                $item = $item_factory->getItemFromDb($data['id']);
                $this->_storeFile($item);
            }

            // Update real metatata
            if ($request->exist('metadata')) {
                $groupId = (int) $request->get('group_id');
                $metadata_array = $request->get('metadata');
                $mdvFactory = new Docman_MetadataValueFactory($groupId);
                $mdvFactory->updateFromRow($data['id'], $metadata_array);

                if ($mdvFactory->isError()) {
                    $this->_controler->feedback->log('error', $mdvFactory->getErrorMessage());
                } else {
                    // Recursive update of properties
                    if ($request->exist('recurse')) {
                        $recurse_updator = new MetadataRecursiveUpdator(
                            new Docman_MetadataFactory($groupId),
                            Docman_PermissionsManager::instance($groupId),
                            $mdvFactory,
                            ReferenceManager::instance()
                        );

                        $collection = ItemImpactedByMetadataChangeCollection::buildFromLegacy($request->get('recurse'), $metadata_array);
                        try {
                            if ($this->_controler->_actionParams['recurseOnDocs']) {
                                $recurse_updator->updateRecursiveMetadataOnFolderAndItems(
                                    $collection,
                                    $data['id'],
                                    $groupId
                                );
                            } else {
                                $recurse_updator->updateRecursiveMetadataOnFolder(
                                    $collection,
                                    $data['id'],
                                    $groupId
                                );
                            }
                        } catch (\Tuleap\Docman\Metadata\NoItemToRecurseException $e) {
                            $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'warning_no_item_recurse'));
                        }
                    }
                }
            }

            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_item_updated'));
        }
        $this->event_manager->processEvent('send_notifications', array());
    }

    function new_version()
    {
        $request = $this->_controler->request;
        if ($request->exist('id')) {
            $user         = $this->_controler->getUser();
            $item_factory = $this->_getItemFactory();
            $item         = $item_factory->getItemFromDb($request->get('id'));
            $item_type    = $item_factory->getItemTypeForItem($item);
            if ($item_type == PLUGIN_DOCMAN_ITEM_TYPE_FILE || $item_type == PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE) {
                $this->_storeFile($item);

                // We update the update_date of the document only if no version date was given
                if (! $request->existAndNonEmpty('date')) {
                    $item_factory->update(array('id' => $item->getId()));
                }

                $this->manageLockNewVersion($user, $item, $request);
            } elseif ($item_type == PLUGIN_DOCMAN_ITEM_TYPE_LINK) {
                $this->updateLink($request, $item, $user);
            }
        }
        $this->event_manager->processEvent('send_notifications', array());
    }

    private function updateLink(Codendi_Request $request, Docman_Link $item, PFUser $user)
    {
        $data = $request->get('item');
        $item->setUrl($data['link_url']);
        $updated = $this->_getItemFactory()->updateLink($item, $request->get('version'));

        $this->manageLockNewVersion($user, $item, $request);

        // Approval table
        $link_version_factory = new Docman_LinkVersionFactory();
        $last_version = $link_version_factory->getLatestVersion($item);
        if ($last_version) {
            // Approval table creation needs the item currentVersion to be set.
            $item->setCurrentVersion($last_version);
            $this->newVersionApprovalTable($request, $item, $user);
        }

        $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_create_newversion'));

        $event_data = array(
            'item'     => $item,
            'version'  => $last_version,
        );
        $this->event_manager->processEvent(PLUGIN_DOCMAN_EVENT_NEW_LINKVERSION, $event_data);

        return $updated;
    }

    private function manageLockNewVersion(PFUser $user, Docman_Item $item, Codendi_Request $request)
    {
        $permission_manager = $this->_getDocmanPermissionsManagerInstance($item->getGroupId());
        if ($request->existAndNonEmpty('lock_document')) {
            $permission_manager->getLockFactory()->lock($item, $user);
        } else {
            $permission_manager->getLockFactory()->unlock($item, $user);
        }
    }

    protected $filestorage;
    protected function _getFileStorage()
    {
        if (!$this->filestorage) {
            $this->filestorage = new Docman_FileStorage($this->_controler->getProperty('docman_root'));
        }
        return $this->filestorage;
    }

    function _getItemFactory($groupId = null)
    {
        return new Docman_ItemFactory($groupId);
    }

    function _getFolderFactory($groupId = null)
    {
        return new Docman_FolderFactory($groupId);
    }

    protected $version_factory;
    function _getVersionFactory()
    {
        if (!$this->version_factory) {
            $this->version_factory = new Docman_VersionFactory();
        }
        return $this->version_factory;
    }
    protected $permissions_manager;
    function &_getPermissionsManagerInstance()
    {
        if (!$this->permissions_manager) {
            $this->permissions_manager = PermissionsManager::instance();
        }
        return $this->permissions_manager;
    }

    function _getDocmanPermissionsManagerInstance($groupId)
    {
        return Docman_PermissionsManager::instance($groupId);
    }

    protected $userManager;
    function _getUserManagerInstance()
    {
        if (!$this->userManager) {
            $this->userManager = UserManager::instance();
        }
        return $this->userManager;
    }

    /**
     * Perform paste operation after cut
     *
     * @param Docman_Item   $itemToMove    Item to move
     * @param Docman_Folder $newParentItem New parent item
     * @param PFUser          $user          User who perform the paste
     * @param String        $ordering      Where the item should be paste within the new folder
     *
     * @return void
     */
    protected function _doCutPaste($itemToMove, $newParentItem, $user, $ordering)
    {
        if ($itemToMove && $newParentItem && $newParentItem->getId() != $itemToMove->getId()) {
            $item_factory = $this->_getItemFactory();
            $old_parent   = $item_factory->getItemFromDb($itemToMove->getParentId());
            if ($item_factory->move($itemToMove, $newParentItem, $user, $ordering)) {
                $hp = Codendi_HTMLPurifier::instance();
                $this->_controler->feedback->log(
                    'info',
                    $GLOBALS['Language']->getText('plugin_docman', 'info_item_moved', array(
                            $itemToMove->getGroupId(),
                            $old_parent->getId(),
                            $hp->purify($old_parent->getTitle(), CODENDI_PURIFIER_CONVERT_HTML) ,
                            $newParentItem->getId(),
                            $hp->purify($newParentItem->getTitle(), CODENDI_PURIFIER_CONVERT_HTML)
                            )),
                    CODENDI_PURIFIER_DISABLED
                );
                    $item_factory->delCopyPreference();
                    $item_factory->delCutPreference();
            } else {
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_item_not_moved'));
            }
        }
    }

    /**
     * Perform paste operation after a copy
     *
     * @param Docman_Item   $itemToPaste   Item to paste
     * @param Docman_Folder $newParentItem New parent item
     * @param PFUser          $user          User who perform the paste
     * @param String        $ordering      Where the item should be paste within the new folder
     * @param bool $importMd Do we need to import metadata from another project
     * @param String        $dataRoot      Where the docman data stand on hard drive
     *
     * @return void
     */
    protected function _doCopyPaste($itemToPaste, $newParentItem, $user, $ordering, $importMd, $dataRoot)
    {
        $srcMdFactory = new Docman_MetadataFactory($itemToPaste->getGroupId());

        // Import metadata if asked
        if ($importMd) {
            $srcMdFactory->exportMetadata($newParentItem->getGroupId());
        }

        // Get mapping between the 2 definitions
        $mdMapping = array();
        $srcMdFactory->getMetadataMapping($newParentItem->getGroupId(), $mdMapping);

        // Permissions
        if ($itemToPaste->getGroupId() != $newParentItem->getGroupId()) {
            $ugroupsMapping = false;
        } else {
            $ugroupsMapping = true;
        }

        // Action
        $itemFactory = $this->_getItemFactory();
        $itemFactory->cloneItems(
            $user,
            $mdMapping,
            $ugroupsMapping,
            $dataRoot,
            $itemToPaste,
            DestinationCloneItem::fromNewParentFolder($newParentItem, ProjectManager::instance(), new Docman_LinkVersionFactory()),
            $ordering
        );

        $itemFactory->delCopyPreference();
        $itemFactory->delCutPreference();
    }

    function move()
    {
        $request = $this->_controler->request;
        if ($request->exist('id')) {
            $item_factory = $this->_getItemFactory();
            //Move in a specific folder (maybe the same)
            if ($request->exist('item_to_move')) {
                $item          = $item_factory->getItemFromDb($request->get('item_to_move'));
                $new_parent_id = $request->get('id');
                $ordering      = $request->get('ordering');
            } else {
                //Move in the same folder
                if ($request->exist('quick_move')) {
                    $item          = $item_factory->getItemFromDb($request->get('id'));
                    $new_parent_id = $item->getParentId();
                    switch ($request->get('quick_move')) {
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
            $newParentItem = $item_factory->getItemFromDb($new_parent_id);
            $user          = $this->_controler->getUser();
            $this->_doCutPaste($item, $newParentItem, $user, $ordering);
        }
        $this->event_manager->processEvent('send_notifications', array());
    }

    function action_cut($params)
    {
        // Param
        $user = $this->_controler->getUser();
        $item = $this->_controler->_actionParams['item'];
        $hp   = Codendi_HTMLPurifier::instance();

        // Action
        $itemFactory = $this->_getItemFactory();

        $itemFactory->delCopyPreference();
        $itemFactory->delCutPreference();
        $itemFactory->setCutPreference($item);

        // Message
        $this->_controler->feedback->log('info', $hp->purify($item->getTitle()).' '.$GLOBALS['Language']->getText('plugin_docman', 'info_cut_notify_cut'));
    }

    function action_copy($params)
    {
        // Param
        $user = $this->_controler->getUser();
        $item = $this->_controler->_actionParams['item'];
        $hp   = Codendi_HTMLPurifier::instance();

        // Action
        $itemFactory = $this->_getItemFactory();

        $itemFactory->delCopyPreference();
        $itemFactory->delCutPreference();
        $itemFactory->setCopyPreference($item);

        // Message
        $msg = $hp->purify($item->getTitle()).' '.$GLOBALS['Language']->getText('plugin_docman', 'info_copy_notify_cp');
        $this->_controler->feedback->log('info', $msg, CODENDI_PURIFIER_DISABLED);
    }

    /**
     * Perform paste action (after a copy or a cut)
     *
     * @param Docman_Item $itemToPaste
     * @param Docman_Item $newParentItem
     * @param String      $rank
     * @param bool $importMd
     * @param String      $srcMode
     *
     * @return void
     */
    function doPaste($itemToPaste, $newParentItem, $rank, $importMd, $srcMode)
    {
        $user      = $this->_controler->getUser();
        $mdMapping = false;
        switch ($srcMode) {
            case 'copy':
                $dataRoot = $this->_controler->getProperty('docman_root');
                $this->_doCopyPaste($itemToPaste, $newParentItem, $user, $rank, $importMd, $dataRoot);
                break;

            case 'cut':
                $this->_doCutPaste($itemToPaste, $newParentItem, $user, $rank);
                break;
        }
        $this->event_manager->processEvent('send_notifications', array());
    }

    function paste($params)
    {
        $this->doPaste(
            $this->_controler->_actionParams['itemToPaste'],
            $this->_controler->_actionParams['item'],
            $this->_controler->_actionParams['rank'],
            $this->_controler->_actionParams['importMd'],
            $this->_controler->_actionParams['srcMode']
        );
    }

    /**
    * User has asked to set or to change permissions on an item
    * This method is the direct action of the docman controler
    *
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
    public function permissions($params)
    {
        $id    = isset($params['id'])    ? $params['id']    : $this->_controler->request->get('id');
        if ($id && $this->_controler->request->exist('permissions')) {
            $item = $this->_getItemFactory()->getItemFromDb($id);
            if ($item instanceof Docman_Folder && $this->_controler->request->get('recursive')) {
                $this->getPermissionItemUpdater($item)->updateFolderAndChildrenPermissions(
                    $item,
                    $this->_controler->getUser(),
                    $this->_controler->request->get('permissions')
                );
            } else {
                $this->getPermissionItemUpdater($item)->updateItemPermissions(
                    $item,
                    $this->_controler->getUser(),
                    $this->_controler->request->get('permissions')
                );
            }

            $this->_controler->view = 'RedirectAfterCrud';
            $this->_controler->_viewParams['default_url_params'] = [
                'action'  => 'details',
                'section' => 'permissions',
                'id'      => $id,
            ];
        }
    }

    function change_view()
    {
        $request  = HTTPRequest::instance();
        if ($request->exist('selected_view')) {
            if (is_numeric($request->get('selected_view'))) {
                $this->_controler->setReportId($request->get('selected_view'));
                $this->_controler->forceView('Table');
            } elseif (is_array($request->get('selected_view')) && count($request->get('selected_view'))) {
                $selected_view_request = $request->get('selected_view');
                foreach ($selected_view_request as $selected_view => $id) {
                    if (Docman_View_Browse::isViewAllowed($selected_view)) {
                        $item_factory = $this->_getItemFactory();
                        $folder = $item_factory->getItemFromDb($request->get('id'));
                        if ($folder) {
                            user_set_preference(
                                PLUGIN_DOCMAN_VIEW_PREF .'_'. $folder->getGroupId(),
                                $selected_view
                            );
                            $this->_controler->forceView($selected_view);
                        }
                    }
                }
            }
        }
    }

    function delete()
    {
        $user    = $this->_controler->getUser();
        $request = $this->_controler->request;

        $_sGroupId = (int) $request->get('group_id');
        $_sId      = (int) $request->get('id');

        if ($request->exist('cascadeWikiPageDeletion') && $request->get('cascadeWikiPageDeletion') == 'on') {
            $cascade = true;
        } else {
            $cascade = false;
        }

        $itemFactory = new Docman_ItemFactory($_sGroupId);
        $parentItem = $itemFactory->getItemFromDb($_sId);
        try {
            if ($itemFactory->deleteSubTree($parentItem, $user, $cascade)) {
                $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_item_deleted'));
            }
        } catch (DeleteFailedException $e) {
            $this->_controler->feedback->log(Feedback::ERROR, $e->getI18NExceptionMessage());
        } catch (Exception $e) {
            $this->_controler->feedback->log(Feedback::ERROR, $e->getMessage());
        }
        $this->event_manager->processEvent('send_notifications', array());
    }

    function deleteVersion()
    {
        $request = $this->_controler->request;

        $_sGroupId = (int) $request->get('group_id');
        $_sId      = (int) $request->get('id');
        $vVersion  = new Valid_UInt('version');
        $vVersion->required();
        if ($request->valid($vVersion)) {
            $_sVersion = $request->get('version');
        } else {
            $_sVersion = false;
        }

        $itemFactory = $this->_getItemFactory($_sGroupId);
        $item        = $itemFactory->getItemFromDb($_sId);
        if ($item) {
            $type = $itemFactory->getItemTypeForItem($item);
            if ($type == PLUGIN_DOCMAN_ITEM_TYPE_FILE || $type == PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE) {
                $versions = $this->_getVersionFactory()->getAllVersionForItem($item);
                if (count($versions) > 1) {
                    $version = false;
                    foreach ($versions as $v) {
                        if ($v->getNumber() == $_sVersion) {
                            $version = $v;
                        }
                    }
                    if ($version !== false) {
                        $user    = $this->_controler->getUser();
                        $deletor = $this->_getActionsDeleteVisitor();
                        try {
                            if ($item->accept($deletor, array('user' => $user, 'version' => $version))) {
                                $this->_controler->feedback->log(Feedback::INFO, $GLOBALS['Language']->getText('plugin_docman', 'info_item_version_deleted', array($version->getNumber(), $version->getLabel())));
                            }
                        } catch (DeleteFailedException $exception) {
                            $this->_controler->feedback->log(Feedback::ERROR, $exception->getI18NExceptionMessage());
                        }
                    } else {
                        $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_item_not_deleted_unknown_version'));
                    }
                } else {
                    $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_item_not_deleted_last_file_version'));
                }
            } else {
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_item_not_deleted_nonfile_version'));
            }
        }
        $this->_getEventManager()->processEvent('send_notifications', array());
    }

    /**
     * Wrapper for Docman_ActionsDeleteVisitor
     *
     * @return Docman_ActionsDeleteVisitor
     */
    function _getActionsDeleteVisitor()
    {
        return new Docman_ActionsDeleteVisitor();
    }

    function admin_change_view()
    {
        $request  = HTTPRequest::instance();
        $group_id = (int) $request->get('group_id');

        if ($request->exist('selected_view') && Docman_View_Browse::isViewAllowed($request->get('selected_view'))) {
            require_once('Docman_SettingsBo.class.php');
            $sBo = Docman_SettingsBo::instance($group_id);
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
    function install()
    {
        die('Install forbidden. Please contact administrator');
    }

    function admin_set_permissions()
    {
        /** @psalm-suppress DeprecatedFunction */
        list ($return_code, $feedback) = permission_process_selection_form($_POST['group_id'], $_POST['permission_type'], $_POST['object_id'], $_POST['ugroups']);
        if (!$return_code) {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_perms_updated', $feedback));
        } else {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'info_perms_updated'));
        }
    }

    function admin_md_details_update()
    {
        $request = HTTPRequest::instance();
        $_label  = $request->get('label');
        $_gid    = (int) $request->get('group_id');

        $mdFactory = new Docman_MetadataFactory($_gid);
        $md = $mdFactory->getFromLabel($_label);

        if ($md !== null) {
            if ($md->getGroupId() == $_gid) {
                // Name
                if ($md->canChangeName()) {
                    $_name = trim($request->get('name'));
                    $md->setName($_name);
                }

                // Description
                if ($md->canChangeDescription()) {
                    $_descr = $request->get('descr');
                    $md->setDescription($_descr);
                }

                // Is empty allowed
                if ($md->canChangeIsEmptyAllowed()) {
                    $_isEmptyAllowed = (int) $request->get('empty_allowed');

                    if ($_isEmptyAllowed === 1) {
                        $md->setIsEmptyAllowed(PLUGIN_DOCMAN_DB_TRUE);
                    } else {
                        $md->setIsEmptyAllowed(PLUGIN_DOCMAN_DB_FALSE);
                    }
                }

                if ($md->canChangeIsMultipleValuesAllowed()) {
                    $_isMultipleValuesAllowed = (int) $request->get('multiplevalues_allowed');

                    if ($_isMultipleValuesAllowed === 1) {
                        $md->setIsMultipleValuesAllowed(PLUGIN_DOCMAN_DB_TRUE);
                    } else {
                        $md->setIsMultipleValuesAllowed(PLUGIN_DOCMAN_DB_FALSE);
                    }
                }

                // Usage
                if (!$md->isRequired()) {
                    $_useIt = (int) $request->get('use_it');
                    if ($_useIt === 1) {
                        $md->setUseIt(PLUGIN_DOCMAN_METADATA_USED);
                    } else {
                        $md->setUseIt(PLUGIN_DOCMAN_METADATA_UNUSED);
                    }
                }

                $updated = $mdFactory->update($md);
                if ($updated) {
                    $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_update'));
                } else {
                    $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_not_update'));
                }
            } else {
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_id_mismatched'));
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_not_update'));
            }
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_bad_label'));
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_not_update'));
        }
    }

    function admin_create_metadata()
    {
        $request = HTTPRequest::instance();

        $_gid                   = (int) $request->get('group_id');
        $_name                  = trim($request->get('name'));
        $_description           = $request->get('descr');
        $_emptyallowed          = (int) $request->get('empty_allowed');
        $_multiplevaluesallowed = (int) $request->get('multiplevalues_allowed');
        $_dfltvalue             = $request->get('dflt_value');
        $_useit                 = $request->get('use_it');
        $_type                  = (int) $request->get('type');

        $mdFactory = new Docman_MetadataFactory($_gid);

        //$mdrow['group_id'] = $_gid;
        $mdrow['name'] = $_name;
        $mdrow['description'] = $_description;
        $mdrow['data_type'] = $_type;
        //$mdrow['label'] =
        $mdrow['required'] = false;
        $mdrow['empty_ok'] = $_emptyallowed;

        if ($_type == PLUGIN_DOCMAN_METADATA_TYPE_LIST) {
            $mdrow['mul_val_ok'] = $_multiplevaluesallowed;
        } else {
            $mdrow['mul_val_ok'] = false;
        }

        $mdrow['special'] = false;
        $mdrow['default_value'] = $_dfltvalue;
        $mdrow['use_it'] = $_useit;

        $md = $mdFactory->_createFromRow($mdrow);

        $mdId = $mdFactory->create($md);
        if ($mdId !== false) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_create'));
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_error_creation'));
        }
    }


    function admin_delete_metadata()
    {
        $md = $this->_controler->_actionParams['md'];

        $name = $md->getName();

        $mdFactory = new Docman_MetadataFactory($md->getGroupId());

        $deleted = $mdFactory->delete($md);
        if ($deleted) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText(
                'plugin_docman',
                'md_del_success',
                array($name)
            ));
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText(
                'plugin_docman',
                'md_del_failure',
                array($name)
            ));
        }
        $this->_controler->view = 'RedirectAfterCrud';
        $this->_controler->_viewParams['default_url_params'] = array('action' => 'admin_metadata');
    }

    function admin_create_love()
    {
        $request = HTTPRequest::instance();

        $_name         = $request->get('name');
        $_description  = $request->get('descr');
        $_rank         = $request->get('rank');
        //$_dfltvalue    = (int) $request->get('dflt_value');
        $_mdLabel      = $request->get('md');
        $_gid          = (int) $request->get('group_id');

        $mdFactory = new Docman_MetadataFactory($_gid);
        $md = $mdFactory->getFromLabel($_mdLabel);

        if ($md !== null
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

    function admin_delete_love()
    {
        $request = HTTPRequest::instance();

        $_loveId  = (int) $request->get('loveid');
        $_mdLabel = $request->get('md');
        $_gid = (int) $request->get('group_id');

        $mdFactory = new Docman_MetadataFactory($_gid);
        $md        = $mdFactory->getFromLabel($_mdLabel);

        if ($md !== null
           && $md->getType() == PLUGIN_DOCMAN_METADATA_TYPE_LIST
           && $md->getLabel() != 'status') {
            $love = new Docman_MetadataListOfValuesElement($md->getId());
            $love->setId($_loveId);

            // Delete value
            $loveFactory = new Docman_MetadataListOfValuesElementFactory($md->getId());
            $deleted = $loveFactory->delete($love);
            if ($deleted) {
                $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_delete_element'));
                $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_reset_delete_element'));
            } else {
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_error_delete_element'));
            }
        } else {
            // Sth really strange is happening... user try to delete a value
            // that do not belong to a metadata with a List type !?
            // If this happen, shutdown the server, format the hard drive and
            // leave computer science to keep goat on the Larzac.
        }
    }

    function admin_update_love()
    {
        $md   = $this->_controler->_actionParams['md'];
        $love = $this->_controler->_actionParams['love'];

        $loveFactory = new Docman_MetadataListOfValuesElementFactory($md->getId());
        $updated = $loveFactory->update($love);

        if ($updated) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_element_update'));

            $this->_controler->view = 'RedirectAfterCrud';
            $this->_controler->_viewParams['default_url_params']  = array('action' => 'admin_md_details',
                                                                          'md'     => $md->getLabel());
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'admin_metadata_element_not_update'));

            $this->_controler->view = 'RedirectAfterCrud';
            $this->_controler->_viewParams['default_url_params']  = array('action' => 'admin_display_love',
                                                                          'md'     => $md->getLabel(),
                                                                          'loveid' => $love->getId());
        }
    }

    function admin_import_metadata()
    {
        $groupId    = $this->_controler->_actionParams['sGroupId'];
        $srcGroupId = $this->_controler->_actionParams['sSrcGroupId'];

        $pm = ProjectManager::instance();
        $srcGo = $pm->getProject($srcGroupId);
        if ($srcGo != false &&
           ($srcGo->isPublic()
            || (!$srcGo->isPublic() && $srcGo->userIsMember()))) {
            $mdFactory = new Docman_MetadataFactory($srcGo->getGroupId());
            $mdFactory->exportMetadata($groupId);

            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'admin_md_import_success', array($srcGo->getPublicName())));
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'error_perms_generic'));
        }
    }

    function monitor($params)
    {
        $user = $this->_controler->getUser();
        if (!$user->isAnonymous()) {
            $something_happen  = false;
            $already_monitored = $this->_controler->notificationsManager->userExists($user->getId(), $params['item']->getId());
            $already_cascaded  = $this->_controler->notificationsManager->userExists($user->getId(), $params['item']->getId(), PLUGIN_DOCMAN_NOTIFICATION_CASCADE);
            if ($params['monitor'] && !$already_monitored) {
                //monitor
                if (!$this->_controler->notificationsManager->add($user->getId(), $params['item']->getId())) {
                    $this->_controler->feedback->log('error', "Unable to add monitoring on '". $params['item']->getTitle() ."'.");
                }
                $something_happen = true;
            } elseif (!$params['monitor'] && $already_monitored) {
                //unmonitor
                if (!$this->_controler->notificationsManager->removeUser($user->getId(), $params['item']->getId())) {
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
            } elseif (!(isset($params['cascade']) && $params['cascade'] && $params['monitor']) && $already_cascaded) {
                //uncascade
                if (!$this->_controler->notificationsManager->removeUser($user->getId(), $params['item']->getId(), PLUGIN_DOCMAN_NOTIFICATION_CASCADE)) {
                    $this->_controler->feedback->log('error', "Unable to remove cascade on '". $params['item']->getTitle() ."'.");
                }
                $something_happen = true;
            }
            //Feedback
            if ($something_happen) {
                if ($this->_controler->notificationsManager->userExists($user->getId(), $params['item']->getId())) {
                    if ($this->_controler->notificationsManager->userExists($user->getId(), $params['item']->getId(), PLUGIN_DOCMAN_NOTIFICATION_CASCADE)) {
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

    /**
     * Raise item monitoring list event
     *
     * @param Docman_Item $item Locked item
     * @param String      $eventType
     * @param Array       $subscribers
     *
     * @return void
     */
    function _raiseMonitoringListEvent($item, $subscribers, $eventType)
    {
        $p = array('group_id' => $item->getGroupId(),
                                       'item'     => $item,
                                       'listeners' => $subscribers,
                                       'event'    => $eventType);
        $this->event_manager->processEvent('plugin_docman_event_subcribers', $p);
    }

    public function update_monitoring($params)
    {
        $item    = $params['item'];
        $cascade = false;
        if (isset($params['monitor_cascade']) && $params['monitor_cascade']) {
            $cascade = true;
        }

        if (isset($params['listeners_users_to_add'])
            && is_array($params['listeners_users_to_add'])
            && ! empty($params['listeners_users_to_add'])) {
            $this->addMonitoringUsers($cascade, $item, $params['listeners_users_to_add']);
        }
        if (isset($params['listeners_ugroups_to_add'])
            && is_array($params['listeners_ugroups_to_add'])
            && ! empty($params['listeners_ugroups_to_add'])) {
            $this->addMonitorinUgroups($cascade, $item, $params['listeners_ugroups_to_add']);
        }
        if (isset($params['listeners_users_to_delete'])
            && is_array($params['listeners_users_to_delete'])
            && ! empty($params['listeners_users_to_delete'])) {
            $this->removeNotificationUsersByItem($item, $params['listeners_users_to_delete']);
        }
        if (isset($params['listeners_ugroups_to_delete'])
            && is_array($params['listeners_ugroups_to_delete'])
            && ! empty($params['listeners_ugroups_to_delete'])) {
            $this->removeNotificationUgroupsByItem($item, $params['listeners_ugroups_to_delete']);
        }
    }

    /**
     * @access private
     */
    function _approval_update_settings(Docman_ApprovalTableFactory $atf, $sStatus, $notification, $notificationOccurence, $description, $owner)
    {
        $table = $atf->getTable();
        $newOwner = false;
        if (!$table->isCustomizable()) {
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
        if ($newOwner === false) {
            $_owner = UserManager::instance()->findUser($owner);
            if (!$_owner) {
                $newOwner = $table->getOwner();
            } else {
                if (!$_owner->isAnonymous() && ($_owner->isActive() || $_owner->isRestricted())) {
                    $newOwner = $_owner->getId();
                } else {
                    $newOwner = $table->getOwner();
                }
            }
        }

        // Update settings
        $updated = $atf->updateTable($sStatus, $notification, $notificationOccurence, $description, $newOwner);
        if ($updated) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_tableupd_success'));
        }
    }

    /**
     * @access private
     */
    function _approval_update_add_users($atrf, $usUserList, $sUgroups)
    {
        $noError = true;
        $userAdded = false;

        // Update users
        if (trim($usUserList) != '') {
            $usUserArray = explode(',', $usUserList);
            // First add individual users
            if (count($usUserArray) > 0) {
                $nbUserAdded = $atrf->addUsers($usUserArray);
                if ($nbUserAdded < count($usUserArray)) {
                    $noError = false;
                } else {
                    $userAdded = true;
                }
            }
        }

     // Then add ugroups.
        if ($sUgroups !== null && count($sUgroups) > 0) {
            foreach ($sUgroups as $ugroup) {
                $ugroupAdded = false;
                if ($ugroup > 0 && $ugroup != 100) {
                    if ($atrf->addUgroup($ugroup)) {
                        $ugroupAdded = true;
                    } else {
                        $noError = false;
                    }
                }
            }
        }

        $purifier = Codendi_HTMLPurifier::instance();

        if (count($atrf->err['db']) > 0) {
            $ua  = array_unique($atrf->err['db']);
            $ua  = $purifier->purifyMap($ua);
            $uas = implode(', ', $ua);
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_useradd_err_db', $uas));
        }
        if (count($atrf->err['perm']) > 0) {
            $ua  = array_unique($atrf->err['perm']);
            $ua  = $purifier->purifyMap($ua);
            $uas = implode(', ', $ua);
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_useradd_err_perm', $uas));
        }
        if (count($atrf->err['notreg']) > 0) {
            $ua  = array_unique($atrf->err['notreg']);
            $ua  = $purifier->purifyMap($ua);
            $uas = implode(', ', $ua);
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_useradd_err_notreg', $uas));
        }
        if (count($atrf->warn['double']) > 0) {
            $ua  = array_unique($atrf->warn['double']);
            $ua  = $purifier->purifyMap($ua);
            $uas = implode(', ', $ua);
            $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'approval_useradd_warn_double', $uas));
        }

        if ($userAdded && $noError) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_useradd_success'));
        }
    }

    /**
     * @access private
     */
    function _approval_update_del_users($atrf, $selectedUsers)
    {
        $deletedUsers = 0;
        foreach ($selectedUsers as $userId) {
            if ($atrf->delUser($userId)) {
                $deletedUsers++;
            }
        }

        if (count($selectedUsers) == $deletedUsers) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_userdel_success'));
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_userdel_failure'));
        }
    }

    /**
     * @access private
     */
    function _approval_update_notify_users($atrf, $selectedUsers)
    {
        $notifiedUsers = 0;
        $atnc = $atrf->_getApprovalTableNotificationCycle(true);
        // For each reviewer, if he is selected, notify it
        // This allow us to verify that we actully notify people
        // member of the table!
        $table = $atnc->getTable();
        $ri = $table->getReviewerIterator();
        while ($ri->valid()) {
            $reviewer = $ri->current();
            if (in_array($reviewer->getId(), $selectedUsers)) {
                if ($atnc->notifyIndividual($reviewer->getId())) {
                    $notifiedUsers++;
                }
            }
            $ri->next();
        }
        if (count($selectedUsers) == $notifiedUsers) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_force_notify_success'));
        }
    }

    /**
     * @access private
     */
    function _approval_update_notif_resend($atrf)
    {
        $res = $atrf->notifyReviewers();
        if ($res) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_notification_success'));
        } else {
            $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'approval_notification_failure'));
        }
    }

    function approval_update()
    {
        // Params
        $item         = $this->_controler->_actionParams['item'];
        $user         = $this->_controler->getUser();
        $sStatus      = $this->_controler->_actionParams['status'];
        $notification = $this->_controler->_actionParams['notification'];
        $reminder     = $this->_controler->_actionParams['reminder'];
        if ($reminder) {
            $occurence             = $this->_controler->_actionParams['occurence'];
            $period                = $this->_controler->_actionParams['period'];
            $notificationOccurence = $occurence * $period;
        } else {
            $notificationOccurence = 0;
        }
        $description  = $this->_controler->_actionParams['description'];
        $usUserList   = $this->_controler->_actionParams['user_list'];
        $sUgroup      = $this->_controler->_actionParams['ugroup_list'];
        $sSelUser     = $this->_controler->_actionParams['sel_user'];
        $sSelUserAct  = $this->_controler->_actionParams['sel_user_act'];
        $resendNotif  = $this->_controler->_actionParams['resend_notif'];
        $version      = $this->_controler->_actionParams['version'];
        $import       = $this->_controler->_actionParams['import'];
        $owner        = $this->_controler->_actionParams['table_owner'];

        $atf = Docman_ApprovalTableFactoriesFactory::getFromItem($item, $version);
        $table = $atf->getTable();
        $oldTable = null;
        if ($table !== null) {
            $oldTable = clone $atf->getTable();
        }

        $tableEditable = false;
        if ($oldTable === null || ($import !== false && $import !== 'keep')) {
            $created = $atf->createTable($user->getId(), $import);
            if (!$created) {
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_tableins_failure'));
            }
        }

        if ($import === false || $import == 'keep') {
            // New table created "from scratch" (ie. without the import
            // selector) are directly editable.
            $tableEditable = true;
        }

        if ($tableEditable) {
            $this->_approval_update_settings($atf, $sStatus, $notification, $notificationOccurence, $description, $owner);
            $table = $atf->getTable();
            if (!$table->isClosed()) {
                $atrf = new Docman_ApprovalTableReviewerFactory($table, $item, $this->_controler->notificationsManager);
                $this->_approval_update_add_users($atrf, $usUserList, $sUgroup);
                if (is_array($sSelUser) && count($sSelUser) > 0) {
                    switch ($sSelUserAct) {
                        case 'del':
                            $this->_approval_update_del_users($atrf, $sSelUser);
                            break;
                        case 'mail':
                            $this->_approval_update_notify_users($atrf, $sSelUser);
                            break;
                    }
                }
                // If needed, notify next reviewer
                if (($oldTable !== null
                    && $oldTable->getStatus() != PLUGIN_DOCMAN_APPROVAL_TABLE_ENABLED
                    && $table->getStatus() == PLUGIN_DOCMAN_APPROVAL_TABLE_ENABLED)
                   || $resendNotif) {
                    $this->_approval_update_notif_resend($atrf);
                }
            }
        }
    }

    function approval_delete()
    {
        // Params
        $item = $this->_controler->_actionParams['item'];
        $version = $this->_controler->_actionParams['version'];
        $atf = Docman_ApprovalTableFactoriesFactory::getFromItem($item, $version);
        $deleted = $atf->deleteTable();
        if ($deleted) {
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_tabledel_success'));
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_tabledel_failure'));
        }
    }

    function approval_upd_user()
    {
        // Params
        $item    = $this->_controler->_actionParams['item'];
        $sUserId = $this->_controler->_actionParams['user_id'];
        $usRank  = $this->_controler->_actionParams['rank'];

        // Action
        $atrf = Docman_ApprovalTableFactory::getReviewerFactoryFromItem($item);
        $atrf->updateUser($sUserId, $usRank);
    }

    function approval_user_commit()
    {
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
        if ($svState != PLUGIN_DOCMAN_APPROVAL_STATE_NOTYET) {
            $review->setVersion($sVersion);
            $review->setReviewDate(time());
        } else {
            $review->setVersion(null);
            $review->setReviewDate(null);
        }

        $atrf = Docman_ApprovalTableFactory::getReviewerFactoryFromItem($item);
        $atrf->setNotificationManager($this->_controler->notificationsManager);
        $updated = $atrf->updateReview($review);
        if ($updated) {
            $this->event_manager->processEvent(
                PLUGIN_DOCMAN_EVENT_APPROVAL_TABLE_COMMENT,
                array(
                    'item'       => $item,
                    'version_nb' => $sVersion,
                    'table'      => $atrf->getTable(),
                    'review'     => $review,
                )
            );
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'approval_review_success'));
        } else {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'approval_review_failure'));
        }

        $this->monitor($this->_controler->_actionParams);
    }

    function report_del()
    {
        $user      = $this->_controler->getUser();
        $reportId  = $this->_controler->_actionParams['sReportId'];
        $groupId   = $this->_controler->_actionParams['sGroupId'];

        $reportFactory = new Docman_ReportFactory($groupId);
        $r = $reportFactory->getReportById($reportId);
        if ($r == null) {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'report_del_notfound'));
        } else {
            if ($r->getScope() == 'I' && $r->getUserId() != $user->getId()) {
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'report_del_notowner'));
            } else {
                if ($r->getScope() == 'P' && !$this->_controler->userCanAdmin()) {
                    $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'report_del_notadmin'));
                } else {
                    if ($reportFactory->deleteReport($r)) {
                        $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'report_del_success'));
                    } else {
                        $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'report_del_failure'));
                    }
                }
            }
        }
    }

    function report_upd()
    {
        $reportId = $this->_controler->_actionParams['sReportId'];
        $groupId  = $this->_controler->_actionParams['sGroupId'];
        $scope    = $this->_controler->_actionParams['sScope'];
        $title    = $this->_controler->_actionParams['title'];
        $description = $this->_controler->_actionParams['description'];
        $image    = $this->_controler->_actionParams['sImage'];

        $reportFactory = new Docman_ReportFactory($groupId);
        $r = $reportFactory->getReportById($reportId);
        if ($r == null) {
            $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'report_upd_notfound'));
        } else {
            if ($r->getGroupId() != $groupId) {
                $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'report_upd_groupmismatch'));
            } else {
                if ($this->_controler->userCanAdmin()) {
                    $r->setScope($scope);
                }
                $r->setTitle($title);
                $r->setDescription($description);
                $r->setImage($image);
                $reportFactory->updateReportSettings($r);
            }
        }
    }

    function report_import()
    {
        $groupId        = $this->_controler->_actionParams['sGroupId'];
        $importReportId = $this->_controler->_actionParams['sImportReportId'];
        $importGroupId  = $this->_controler->_actionParams['sImportGroupId'];
        $user           = $this->_controler->getUser();

        // Any user can importreports from any public projects and from
        // Private projects he is member of.
        $pm = ProjectManager::instance();
        $go = $pm->getProject($importGroupId);
        if ($go != false &&
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
            if (count($srcReportItems) > 0) {
                // Get the subtree from the original docman on which reports applies
                $srcItemFactory = new Docman_ItemFactory($importGroupId);
                $srcItemTree = $srcItemFactory->getItemTreeFromLeaves($srcReportItems, $user);
                if ($srcItemTree !== null) {
                    // Final step: find in the current ($groupId) docman
                    $dstItemFactory = new Docman_ItemFactory($groupId);
                    $itemMapping = $dstItemFactory->getItemMapping($srcItemTree);
                }
            }

            // If user is admin he can create 'P' report otherwise everything is 'I'
            $forceScope = true;
            if ($this->_controler->userCanAdmin()) {
                $forceScope = false;
            }

            if ($importReportId !== null) {
                // Import only one report
                $report = $srcReportFactory->getReportById($importReportId);

                if ($report !== null) {
                    // User can import Project wide reports or his own Individual reports.
                    if ($report->getScope() == 'P' ||
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

    function action_lock_add()
    {
        $item = $this->_controler->_actionParams['item'];
        if ($this->_controler->userCanWrite($item->getId())) {
            $user = $this->_controler->getUser();
            $lockFactory = new \Docman_LockFactory(new \Docman_LockDao(), new \Docman_Log());
            $dIF = $this->_getItemFactory();
            $canLock = true;

            // Cannot lock a wiki with a page already locked
            if ($dIF->getItemTypeForItem($item) == PLUGIN_DOCMAN_ITEM_TYPE_WIKI) {
                $pagename = $item->getPagename();
                $group_id = $item->getGroupId();
                $referencers = $dIF->getWikiPageReferencers($pagename, $group_id);
                foreach ($referencers as $referencer) {
                    if ($lockFactory->itemIsLockedByItemId($referencer->getId())) {
                        $canLock = false;
                        break;
                        // wiki page is locked by another item.
                    }
                }
            }

            // Cannot lock a folder
            if ($dIF->getItemTypeForItem($item) == PLUGIN_DOCMAN_ITEM_TYPE_FOLDER) {
                $canLock = false;
            }

            if ($canLock) {
                $lockFactory->lock($item, $user);
            }
        }
    }

    function action_lock_del()
    {
        /** @var Docman_Controller $this->_controler */
        $item = $this->_controler->_actionParams['item'];
        $user = $this->_controler->getUser();
        $lockFactory = new \Docman_LockFactory(new \Docman_LockDao(), new \Docman_Log());
        if ($user !== null && $this->_controler->userCanWrite($item->getId())) {
            $lockFactory->unlock($item, $user);
        }
    }

    private function removeNotificationUsersByItem(Docman_Item $item, array $users_to_delete)
    {
        $users = array();
        foreach ($users_to_delete as $user) {
            if ($this->_controler->notificationsManager->userExists($user->getId(), $item->getId())) {
                if ($this->_controler->notificationsManager->removeUser($user->getId(), $item->getId())
                    && $this->_controler->notificationsManager->removeUser($user->getId(), $item->getId(), PLUGIN_DOCMAN_NOTIFICATION_CASCADE)) {
                    $users[] = $user;
                } else {
                    $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'notifications_not_removed_user', array($user->getName())));
                }
            } else {
                $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'notifications_not_present_user', array($user->getName())));
            }
        }

        if (! empty($users)) {
            $removed_users = array();
            foreach ($users as $user) {
                $removed_users[] = $user->getName();
            }
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'notifications_removed_user', array(implode(',', $removed_users))));
            $this->_raiseMonitoringListEvent($item, $users, 'plugin_docman_remove_monitoring');
        }
    }

    private function removeNotificationUgroupsByItem(Docman_Item $item, array $ugroups_to_delete)
    {
        $ugroups = array();
        foreach ($ugroups_to_delete as $ugroup) {
            if ($this->_controler->notificationsManager->ugroupExists($ugroup->getId(), $item->getId())) {
                if ($this->_controler->notificationsManager->removeUgroup($ugroup->getId(), $item->getId())
                    && $this->_controler->notificationsManager->removeUgroup($ugroup->getId(), $item->getId(), PLUGIN_DOCMAN_NOTIFICATION_CASCADE)) {
                    $ugroups[] = $ugroup;
                } else {
                    $this->_controler->feedback->log('error', $GLOBALS['Language']->getText('plugin_docman', 'notifications_not_removed_ugroup', array($ugroup->getTranslatedName())));
                }
            } else {
                $this->_controler->feedback->log('warning', $GLOBALS['Language']->getText('plugin_docman', 'notifications_not_present_ugroup', array($ugroup->getTranslatedName())));
            }
        }

        if (! empty($ugroups)) {
            $removed_ugroups = array();
            foreach ($ugroups as $ugroup) {
                $removed_ugroups[] = $ugroup->getTranslatedName();
            }
            $this->_controler->feedback->log('info', $GLOBALS['Language']->getText('plugin_docman', 'notifications_removed_ugroup', array(implode(',', $removed_ugroups))));
            $this->raiseMonitoringUgroups($item, $ugroups, 'plugin_docman_remove_monitoring');
        }
    }

    private function addMonitoringUsers($cascade, Docman_Item $item, array $users_to_add)
    {
        $users = array();
        $dpm   = $this->_getDocmanPermissionsManagerInstance($item->getGroupId());
        foreach ($users_to_add as $user) {
            if ($this->_controler->notificationsManager->userExists($user->getId(), $item->getId())) {
                $this->_controler->feedback->log(
                    'warning',
                    $GLOBALS['Language']->getText(
                        'plugin_docman',
                        'notifications_already_exists_user',
                        array($user->getName())
                    )
                );
                continue;
            }
            if (! $dpm->userCanRead($user, $item->getId())) {
                $this->_controler->feedback->log(
                    'warning',
                    $GLOBALS['Language']->getText(
                        'plugin_docman',
                        'notifications_no_access_rights_user',
                        array($user->getName())
                    )
                );
                continue;
            }
            if (! $this->_controler->notificationsManager->addUser($user->getId(), $item->getId())) {
                $this->_controler->feedback->log(
                    'error',
                    $GLOBALS['Language']->getText(
                        'plugin_docman',
                        'notifications_not_added_user',
                        array($user->getName())
                    )
                );
                continue;
            }
            if ($cascade && ! $this->_controler->notificationsManager->addUser(
                $user->getId(),
                $item->getId(),
                PLUGIN_DOCMAN_NOTIFICATION_CASCADE
            )
            ) {
                $this->_controler->feedback->log(
                    'error',
                    $GLOBALS['Language']->getText(
                        'plugin_docman',
                        'notifications_cascade_not_added_user',
                        array($user->getName())
                    )
                );
            }
            $users[] = $user->getName();
        }

        if (! empty($users)) {
            $this->_controler->feedback->log(
                'info',
                $GLOBALS['Language']->getText('plugin_docman', 'notifications_added_user', array(implode(',', $users)))
            );
            $this->_raiseMonitoringListEvent(
                $item,
                $users_to_add,
                'plugin_docman_add_monitoring'
            );
        }
    }

    private function addMonitorinUgroups($cascade, Docman_Item $item, array $ugroups_to_add)
    {
        /**
         * @var Docman_Controller $controller
         */
        $controller = $this->_controler;
        $ugroups      = array();
        $ugroups_name = array();
        foreach ($ugroups_to_add as $ugroup) {
            if ($controller->notificationsManager->ugroupExists($ugroup->getId(), $item->getId())) {
                $controller->feedback->log(
                    Feedback::WARN,
                    $GLOBALS['Language']->getText(
                        'plugin_docman',
                        'notifications_already_exists_ugroup',
                        array($ugroup->getTranslatedName())
                    )
                );
                continue;
            }
            if (! $controller->notificationsManager->addUgroup($ugroup->getId(), $item->getId())) {
                $controller->feedback->log(
                    Feedback::ERROR,
                    $GLOBALS['Language']->getText(
                        'plugin_docman',
                        'notifications_not_added_ugroup',
                        array($ugroup->getTranslatedName())
                    )
                );
                continue;
            }
            if ($cascade && ! $controller->notificationsManager->addUgroup(
                $ugroup->getId(),
                $item->getId(),
                PLUGIN_DOCMAN_NOTIFICATION_CASCADE
            )
            ) {
                $controller->feedback->log(
                    Feedback::ERROR,
                    $GLOBALS['Language']->getText(
                        'plugin_docman',
                        'notifications_cascade_not_added_ugroup',
                        array($ugroup->getTranslatedName())
                    )
                );
            }
            $ugroups[]      = $ugroup;
            $ugroups_name[] = $ugroup->getTranslatedName();
        }

        if (! empty($ugroups)) {
            $controller->feedback->log(
                Feedback::INFO,
                $GLOBALS['Language']->getText('plugin_docman', 'notifications_added_ugroup', array(implode(',', $ugroups_name)))
            );
            $this->raiseMonitoringUgroups($item, $ugroups, 'plugin_docman_add_monitoring');
        }
    }

    private function raiseMonitoringUgroups(Docman_Item $item, array $ugroups, $type_event)
    {
        $users = array();
        foreach ($ugroups as $ugroup) {
            $members = $ugroup->getMembers();
            if ($members && is_array($members)) {
                $users = array_merge($users, $members);
            }
        }
        $this->_raiseMonitoringListEvent($item, $users, $type_event);
    }
}

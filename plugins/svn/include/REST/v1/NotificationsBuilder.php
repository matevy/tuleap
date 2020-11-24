<?php
/**
 *  Copyright (c) Enalean, 2017. All Rights Reserved.
 *
 *  This file is a part of Tuleap.
 *
 *  Tuleap is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  Tuleap is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Tuleap\SVN\REST\v1;

use Tuleap\Project\REST\UserGroupRepresentation;
use Tuleap\SVN\Admin\MailNotification;
use Tuleap\SVN\Notifications\NotificationsEmailsBuilder;
use Tuleap\SVN\Notifications\UgroupsToNotifyDao;
use Tuleap\SVN\Notifications\UsersToNotifyDao;
use Tuleap\User\REST\MinimalUserRepresentation;
use UGroupManager;
use UserManager;

class NotificationsBuilder
{
    /**
     * @var UGroupManager
     */
    private $ugroup_manager;
    /**
     * @var UgroupsToNotifyDao
     */
    private $ugroup_dao;
    /**
     * @var UserManager
     */
    private $user_manager;
    /**
     * @var UsersToNotifyDao
     */
    private $user_dao;
    /**
     * @var NotificationsEmailsBuilder
     */
    private $emails_builder;

    public function __construct(
        NotificationsEmailsBuilder $notifications_emails_builder,
        UsersToNotifyDao $user_dao,
        UserManager $user_manager,
        UgroupsToNotifyDao $ugroup_dao,
        UGroupManager $ugroup_manager
    ) {
        $this->emails_builder = $notifications_emails_builder;
        $this->user_dao       = $user_dao;
        $this->user_manager   = $user_manager;
        $this->ugroup_dao     = $ugroup_dao;
        $this->ugroup_manager = $ugroup_manager;
    }

    /**
     * @param MailNotification[] $mail_notifications
     *
     * @return array
     */
    public function getNotifications(array $mail_notifications)
    {
        $notifications_representation = array();

        foreach ($mail_notifications as $notification) {
            $extracted_notifications            = array();
            $extracted_notifications['emails']  = $this->extractMails($notification);
            $extracted_notifications['users']   = $this->extractUsers($notification);
            $extracted_notifications['ugroups'] = $this->extractUGroups($notification);

            $notification_representation = new NotificationRepresentation();
            $notification_representation->build($extracted_notifications, $notification->getPath());

            $notifications_representation[] = $notification_representation;
        }

        return $notifications_representation;
    }

    /**
     * @return array
     */
    private function extractMails(MailNotification $notification)
    {
        $mails = array();
        foreach ($notification->getNotifiedMails() as $mail) {
            $mails[] = $mail;
        }

        return $mails;
    }

    /**
     * @return MinimalUserRepresentation[]
     */
    private function extractUsers(MailNotification $notification)
    {
        $users = array();

        foreach ($this->user_dao->searchUsersByNotificationId($notification->getId()) as $row) {
            $user = $this->user_manager->getUserById($row['user_id']);

            $user_representation = new MinimalUserRepresentation();
            $user_representation->build($user);

            $users[] = $user_representation;
        }

        return $users;
    }

    /**
     * @return UserGroupRepresentation[]
     */
    private function extractUGroups(MailNotification $notification)
    {
        $ugroups = array();

        foreach ($this->ugroup_dao->searchUgroupsByNotificationId($notification->getId()) as $row) {
            $group = $this->ugroup_manager->getById($row['ugroup_id']);

            $ugroup_representation = new UserGroupRepresentation();
            $ugroup_representation->build((int) $notification->getRepository()->getProject()->getID(), $group);

            $ugroups[] = $ugroup_representation;
        }

        return $ugroups;
    }
}

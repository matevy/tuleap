<?php
/**
 * Copyright (c) Enalean, 2018-Present. All Rights Reserved.
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

namespace Tuleap\Tracker\Artifact\ArtifactsDeletion;

use Exception;
use Logger;
use PFUser;
use Tracker_Artifact;
use Tuleap\Queue\QueueFactory;
use Tuleap\Queue\Worker;
use Tuleap\Queue\WorkerEvent;

class AsynchronousArtifactsDeletionActionsRunner
{
    public const TOPIC = 'tuleap.tracker.artifact.deletion';
    /**
     * @var PendingArtifactRemovalDao
     */
    private $pending_artifact_removal_dao;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var \UserManager
     */
    private $user_manager;
    /**
     * @var QueueFactory
     */
    private $queue_factory;

    public function __construct(
        PendingArtifactRemovalDao $pending_artifact_removal_dao,
        Logger $logger,
        \UserManager $user_manager,
        QueueFactory $queue_factory
    ) {
        $this->pending_artifact_removal_dao = $pending_artifact_removal_dao;
        $this->logger                       = $logger;
        $this->user_manager                 = $user_manager;
        $this->queue_factory                = $queue_factory;
    }

    public function addListener(WorkerEvent $event)
    {
        if ($event->getEventName() === self::TOPIC) {
            $message = $event->getPayload();

            $pending_artifact = $this->pending_artifact_removal_dao->getPendingArtifactById($message['artifact_id']);
            $artifact         = new Tracker_Artifact(
                $pending_artifact['id'],
                $pending_artifact['tracker_id'],
                $pending_artifact['submitted_by'],
                $pending_artifact['submitted_on'],
                $pending_artifact['use_artifact_permissions']
            );

            $user = $this->user_manager->getUserById($message['user_id']);

            $this->processArchiveAndArtifactDeletion($artifact, $user);
        }
    }

    private function processArchiveAndArtifactDeletion(Tracker_Artifact $artifact, PFUser $user)
    {
        $task_builder = new ArchiveAndDeleteArtifactTaskBuilder();
        $task         = $task_builder->build($this->logger);

        $task->archive($artifact, $user);
    }

    public function executeArchiveAndArtifactDeletion(Tracker_Artifact $artifact, PFUser $user)
    {
        try {
            $queue = $this->queue_factory->getPersistentQueue(Worker::EVENT_QUEUE_NAME, QueueFactory::REDIS);
            $queue->pushSinglePersistentMessage(
                AsynchronousArtifactsDeletionActionsRunner::TOPIC,
                [
                    'artifact_id' => (int)$artifact->getId(),
                    'user_id'     => (int)$user->getId(),
                ]
            );
        } catch (Exception $exception) {
            $this->logger->error("Unable to queue deletion for {$artifact->getId()}");
            $this->processArchiveAndArtifactDeletion($artifact, $user);
        }
    }
}

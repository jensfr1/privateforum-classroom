<?php

namespace wcf\data\privateforum\classroom;

use wcf\data\DatabaseObject;
use wcf\data\privateforum\forum\PrivateForum;

/**
 * @property-read int $classroomID
 * @property-read int $privateforumID
 * @property-read int $databaseID
 * @property-read string $title
 * @property-read int $isActive
 * @property-read int $time
 */
class PrivateForumClassroom extends DatabaseObject
{
    protected static $databaseTableName = 'privateforum_classroom';
    protected static $databaseTableIndexName = 'classroomID';

    public function getPrivateForum(): ?PrivateForum
    {
        $forum = new PrivateForum($this->privateforumID);

        return $forum->privateforumID ? $forum : null;
    }
}

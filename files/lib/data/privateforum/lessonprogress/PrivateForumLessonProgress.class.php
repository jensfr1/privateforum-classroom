<?php

namespace wcf\data\privateforum\lessonprogress;

use wcf\data\DatabaseObject;

/**
 * @property-read int $progressID
 * @property-read int $classroomID
 * @property-read int $entryID
 * @property-read int $userID
 * @property-read int $isCompleted
 * @property-read int|null $completedTime
 */
class PrivateForumLessonProgress extends DatabaseObject
{
    protected static $databaseTableName = 'privateforum_lesson_progress';
    protected static $databaseTableIndexName = 'progressID';
}

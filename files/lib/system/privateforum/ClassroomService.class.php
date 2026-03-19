<?php

namespace wcf\system\privateforum;

use wcf\data\privateforum\classroom\PrivateForumClassroom;
use wcf\data\privateforum\lessonprogress\PrivateForumLessonProgress;
use wcf\data\privateforum\lessonprogress\PrivateForumLessonProgressAction;
use wcf\data\privateforum\lessonprogress\PrivateForumLessonProgressEditor;
use wcf\system\WCF;

/**
 * Service für Classroom/Kurs-Verwaltung in Private Foren.
 *
 * Jedes Classroom hat eine EIGENE FlexibleList-Datenbank (databaseID im Classroom-Eintrag).
 * Alle Kategorien dieser DB = Module. Alle Entries = Lektionen.
 */
class ClassroomService
{
    public static function getClassroomByForumID(int $privateforumID): ?PrivateForumClassroom
    {
        $sql = "SELECT * FROM wcf1_privateforum_classroom WHERE privateforumID = ?";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$privateforumID]);
        $row = $statement->fetchSingleRow();

        return $row ? new PrivateForumClassroom(null, $row) : null;
    }

    public static function markLessonComplete(int $classroomID, int $entryID, int $userID): bool
    {
        if (!$classroomID || !$entryID || !$userID) {
            return false;
        }

        $existing = self::getLessonProgress($entryID, $userID);
        if ($existing && $existing->isCompleted) {
            return false;
        }

        if ($existing) {
            $editor = new PrivateForumLessonProgressEditor($existing);
            $editor->update([
                'isCompleted' => 1,
                'completedTime' => TIME_NOW,
            ]);
        } else {
            $action = new PrivateForumLessonProgressAction([], 'create', [
                'data' => [
                    'classroomID' => $classroomID,
                    'entryID' => $entryID,
                    'userID' => $userID,
                    'isCompleted' => 1,
                    'completedTime' => TIME_NOW,
                ],
            ]);
            $action->executeAction();
        }

        return true;
    }

    public static function markLessonIncomplete(int $classroomID, int $entryID, int $userID): bool
    {
        if (!$classroomID || !$entryID || !$userID) {
            return false;
        }

        $existing = self::getLessonProgress($entryID, $userID);
        if (!$existing || !$existing->isCompleted) {
            return false;
        }

        $editor = new PrivateForumLessonProgressEditor($existing);
        $editor->update([
            'isCompleted' => 0,
            'completedTime' => 0,
        ]);

        return true;
    }

    public static function getLessonProgress(int $entryID, int $userID): ?PrivateForumLessonProgress
    {
        $sql = "SELECT * FROM wcf1_privateforum_lesson_progress
                WHERE entryID = ? AND userID = ?";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$entryID, $userID]);
        $row = $statement->fetchSingleRow();

        return $row ? new PrivateForumLessonProgress(null, $row) : null;
    }

    /**
     * Gesamtfortschritt — alle Entries in der Classroom-DB zählen.
     */
    public static function getUserProgress(int $classroomID, int $userID): array
    {
        $classroom = new PrivateForumClassroom($classroomID);
        if (!$classroom->classroomID || !$classroom->databaseID) {
            return ['completed' => 0, 'total' => 0, 'percentage' => 0.0];
        }

        $sql = "SELECT COUNT(*) FROM wcf1_flexiblelist_entry
                WHERE databaseID = ? AND isDisabled = 0 AND isDeleted = 0";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$classroom->databaseID]);
        $total = (int)$statement->fetchSingleColumn();

        $sql = "SELECT COUNT(*) FROM wcf1_privateforum_lesson_progress
                WHERE classroomID = ? AND userID = ? AND isCompleted = 1";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$classroomID, $userID]);
        $completed = (int)$statement->fetchSingleColumn();

        $percentage = $total > 0 ? \round(($completed / $total) * 100, 1) : 0.0;

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $percentage,
        ];
    }

    /**
     * Alle Kategorien der Classroom-DB = Module, mit Fortschritt.
     */
    public static function getModulesWithProgress(int $classroomID, int $userID): array
    {
        $classroom = new PrivateForumClassroom($classroomID);
        if (!$classroom->classroomID || !$classroom->databaseID) {
            return [];
        }

        $databaseID = $classroom->databaseID;

        // Kategorien laden
        $sql = "SELECT categoryID, title, iconName, showOrder
                FROM wcf1_flexiblelist_category
                WHERE databaseID = ?
                ORDER BY showOrder ASC, title ASC";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$databaseID]);

        $modules = [];
        $categoryIDs = [];
        while ($row = $statement->fetchArray()) {
            $catID = (int)$row['categoryID'];
            $categoryIDs[] = $catID;
            $modules[$catID] = [
                'categoryID' => $catID,
                'title' => $row['title'],
                'iconName' => $row['iconName'] ?? '',
                'showOrder' => (int)$row['showOrder'],
                'coverImage' => '',
                'lessons' => [],
                'completedCount' => 0,
                'totalCount' => 0,
                'percentage' => 0.0,
            ];
        }

        if (empty($categoryIDs)) {
            return [];
        }

        // Lektionen pro Kategorie
        $conditions = new \wcf\system\database\util\PreparedStatementConditionBuilder();
        $conditions->add("e.databaseID = ?", [$databaseID]);
        $conditions->add("e.categoryID IN (?)", [$categoryIDs]);
        $conditions->add("e.isDisabled = ?", [0]);
        $conditions->add("e.isDeleted = ?", [0]);

        $sql = "SELECT e.entryID, e.subject, e.categoryID, e.time, e.coverImagePath
                FROM wcf1_flexiblelist_entry e
                " . $conditions . "
                ORDER BY e.categoryID ASC, e.time ASC";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute($conditions->getParameters());

        $entryIDs = [];
        while ($row = $statement->fetchArray()) {
            $catID = (int)$row['categoryID'];
            $entryID = (int)$row['entryID'];
            $entryIDs[] = $entryID;

            if (isset($modules[$catID])) {
                $modules[$catID]['lessons'][] = [
                    'entryID' => $entryID,
                    'title' => $row['subject'],
                    'categoryID' => $catID,
                    'coverImagePath' => $row['coverImagePath'] ?? '',
                    'isCompleted' => false,
                ];
                $modules[$catID]['totalCount']++;
                if (empty($modules[$catID]['coverImage']) && !empty($row['coverImagePath'])) {
                    $modules[$catID]['coverImage'] = $row['coverImagePath'];
                }
            }
        }

        // Completion-Status
        if (!empty($entryIDs) && $userID) {
            $conditions = new \wcf\system\database\util\PreparedStatementConditionBuilder();
            $conditions->add("entryID IN (?)", [$entryIDs]);
            $conditions->add("userID = ?", [$userID]);
            $conditions->add("isCompleted = ?", [1]);

            $sql = "SELECT entryID FROM wcf1_privateforum_lesson_progress " . $conditions;
            $statement = WCF::getDB()->prepare($sql);
            $statement->execute($conditions->getParameters());

            $completedEntryIDs = [];
            while ($row = $statement->fetchArray()) {
                $completedEntryIDs[] = (int)$row['entryID'];
            }

            foreach ($modules as &$module) {
                foreach ($module['lessons'] as &$lesson) {
                    if (\in_array($lesson['entryID'], $completedEntryIDs)) {
                        $lesson['isCompleted'] = true;
                        $module['completedCount']++;
                    }
                }
                unset($lesson);
                $module['percentage'] = $module['totalCount'] > 0
                    ? \round(($module['completedCount'] / $module['totalCount']) * 100, 1)
                    : 0.0;
            }
            unset($module);
        }

        return \array_values($modules);
    }

    public static function getModuleLessons(int $classroomID, int $categoryID, int $userID): array
    {
        $classroom = new PrivateForumClassroom($classroomID);
        if (!$classroom->classroomID || !$classroom->databaseID) {
            return [];
        }

        $sql = "SELECT e.entryID, e.subject, e.categoryID, e.time, e.message, e.teaser, e.coverImagePath
                FROM wcf1_flexiblelist_entry e
                WHERE e.databaseID = ? AND e.categoryID = ? AND e.isDisabled = 0 AND e.isDeleted = 0
                ORDER BY e.time ASC";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$classroom->databaseID, $categoryID]);

        $lessons = [];
        $entryIDs = [];
        while ($row = $statement->fetchArray()) {
            $entryID = (int)$row['entryID'];
            $entryIDs[] = $entryID;
            $lessons[$entryID] = [
                'entryID' => $entryID,
                'title' => $row['subject'],
                'categoryID' => (int)$row['categoryID'],
                'message' => $row['message'] ?? '',
                'teaser' => $row['teaser'] ?? '',
                'coverImagePath' => $row['coverImagePath'] ?? '',
                'isCompleted' => false,
            ];
        }

        if (!empty($entryIDs) && $userID) {
            $conditions = new \wcf\system\database\util\PreparedStatementConditionBuilder();
            $conditions->add("entryID IN (?)", [$entryIDs]);
            $conditions->add("userID = ?", [$userID]);
            $conditions->add("isCompleted = ?", [1]);

            $sql = "SELECT entryID FROM wcf1_privateforum_lesson_progress " . $conditions;
            $statement = WCF::getDB()->prepare($sql);
            $statement->execute($conditions->getParameters());

            while ($row = $statement->fetchArray()) {
                if (isset($lessons[(int)$row['entryID']])) {
                    $lessons[(int)$row['entryID']]['isCompleted'] = true;
                }
            }
        }

        return \array_values($lessons);
    }

    public static function getLesson(int $entryID): ?array
    {
        $sql = "SELECT e.entryID, e.subject, e.categoryID, e.databaseID, e.time, e.message, e.teaser, e.coverImagePath
                FROM wcf1_flexiblelist_entry e
                WHERE e.entryID = ? AND e.isDisabled = 0 AND e.isDeleted = 0";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$entryID]);
        $row = $statement->fetchSingleRow();

        if (!$row) {
            return null;
        }

        return [
            'entryID' => (int)$row['entryID'],
            'title' => $row['subject'],
            'categoryID' => (int)$row['categoryID'],
            'databaseID' => (int)$row['databaseID'],
            'message' => $row['message'] ?? '',
            'teaser' => $row['teaser'] ?? '',
            'coverImagePath' => $row['coverImagePath'] ?? '',
            'time' => (int)$row['time'],
        ];
    }

    public static function getCategoryTitle(int $categoryID): string
    {
        $sql = "SELECT title FROM wcf1_flexiblelist_category WHERE categoryID = ?";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$categoryID]);
        $row = $statement->fetchSingleRow();

        return $row ? $row['title'] : '';
    }
}

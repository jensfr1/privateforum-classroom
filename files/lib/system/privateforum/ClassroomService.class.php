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
 * Nutzt eine SHARED FlexibleList-Datenbank (ID aus PRIVATEFORUM_CLASSROOM_DATABASE_ID).
 * Jedes Classroom filtert über die Tabelle wcf1_privateforum_classroom_module,
 * welche Kategorien (= Module) zum Classroom gehören.
 */
class ClassroomService
{
    /**
     * Findet das Classroom eines PrivateForums.
     */
    public static function getClassroomByForumID(int $privateforumID): ?PrivateForumClassroom
    {
        $sql = "SELECT * FROM wcf1_privateforum_classroom WHERE privateforumID = ?";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$privateforumID]);
        $row = $statement->fetchSingleRow();

        return $row ? new PrivateForumClassroom(null, $row) : null;
    }

    /**
     * Gibt die dem Classroom zugewiesenen Kategorie-IDs zurück.
     *
     * @return int[]
     */
    public static function getAssignedCategoryIDs(int $classroomID): array
    {
        $sql = "SELECT categoryID FROM wcf1_privateforum_classroom_module
                WHERE classroomID = ?
                ORDER BY sortOrder ASC";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$classroomID]);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Markiert eine Lektion als abgeschlossen.
     */
    public static function markLessonComplete(int $classroomID, int $entryID, int $userID): bool
    {
        if (!$classroomID || !$entryID || !$userID) {
            return false;
        }

        // Prüfen ob schon completed
        $existing = self::getLessonProgress($entryID, $userID);
        if ($existing && $existing->isCompleted) {
            return false;
        }

        if ($existing) {
            $editor = new PrivateForumLessonProgressEditor($existing);
            $editor->update([
                'isCompleted' => 1,
                'completedTime' => \TIME_NOW,
            ]);
        } else {
            $action = new PrivateForumLessonProgressAction([], 'create', [
                'data' => [
                    'classroomID' => $classroomID,
                    'entryID' => $entryID,
                    'userID' => $userID,
                    'isCompleted' => 1,
                    'completedTime' => \TIME_NOW,
                ],
            ]);
            $action->executeAction();
        }

        return true;
    }

    /**
     * Markiert eine Lektion als nicht abgeschlossen (Toggle).
     */
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

    /**
     * Gibt den Fortschritt einer Lektion zurück.
     */
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
     * Gibt den Gesamtfortschritt eines Users für ein Classroom zurück.
     * Zählt NUR Einträge in den dem Classroom zugewiesenen Kategorien.
     *
     * @return array{completed: int, total: int, percentage: float}
     */
    public static function getUserProgress(int $classroomID, int $userID): array
    {
        $classroom = new PrivateForumClassroom($classroomID);
        if (!$classroom->classroomID) {
            return ['completed' => 0, 'total' => 0, 'percentage' => 0.0];
        }

        $databaseID = self::getDatabaseID();
        if (!$databaseID) {
            return ['completed' => 0, 'total' => 0, 'percentage' => 0.0];
        }

        // Nur zugewiesene Kategorien berücksichtigen
        $categoryIDs = self::getAssignedCategoryIDs($classroomID);
        if (empty($categoryIDs)) {
            return ['completed' => 0, 'total' => 0, 'percentage' => 0.0];
        }

        // Gesamtanzahl Lektionen aus FlexibleList (nur zugewiesene Kategorien)
        $conditions = new \wcf\system\database\util\PreparedStatementConditionBuilder();
        $conditions->add("databaseID = ?", [$databaseID]);
        $conditions->add("categoryID IN (?)", [$categoryIDs]);
        $conditions->add("isDisabled = ?", [0]);
        $conditions->add("isDeleted = ?", [0]);

        $sql = "SELECT COUNT(*) FROM wcf1_flexiblelist_entry " . $conditions;
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute($conditions->getParameters());
        $total = (int)$statement->fetchSingleColumn();

        // Abgeschlossene Lektionen
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
     * Gibt die Module (FlexibleList-Kategorien) eines Classrooms mit Fortschritt zurück.
     * Nur Module die dem Classroom zugewiesen sind (JOIN mit classroom_module).
     *
     * @return array Jedes Modul: categoryID, title, iconName, showOrder, lessons, completedCount, totalCount, percentage
     */
    public static function getModulesWithProgress(int $classroomID, int $userID): array
    {
        $classroom = new PrivateForumClassroom($classroomID);
        if (!$classroom->classroomID) {
            return [];
        }

        $databaseID = self::getDatabaseID();
        if (!$databaseID) {
            return [];
        }

        // Kategorien (= Module) laden — NUR die dem Classroom zugewiesenen
        $sql = "SELECT c.categoryID, c.title, c.iconName, c.showOrder
                FROM wcf1_flexiblelist_category c
                INNER JOIN wcf1_privateforum_classroom_module cm
                    ON cm.categoryID = c.categoryID
                WHERE c.databaseID = ? AND cm.classroomID = ?
                ORDER BY cm.sortOrder ASC, c.showOrder ASC, c.title ASC";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$databaseID, $classroomID]);

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

        // Lektionen pro Kategorie laden
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

            $lesson = [
                'entryID' => $entryID,
                'title' => $row['subject'],
                'categoryID' => $catID,
                'coverImagePath' => $row['coverImagePath'] ?? '',
                'isCompleted' => false,
            ];

            if (isset($modules[$catID])) {
                $modules[$catID]['lessons'][] = $lesson;
                $modules[$catID]['totalCount']++;
                // Erstes verfügbares Cover-Bild als Modul-Cover verwenden
                if (empty($modules[$catID]['coverImage']) && !empty($row['coverImagePath'])) {
                    $modules[$catID]['coverImage'] = $row['coverImagePath'];
                }
            }
        }

        // Completion-Status laden
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

    /**
     * Gibt die Lektionen eines bestimmten Moduls (Kategorie) mit Fortschritt zurück.
     * Filtert nur Lektionen in den dem Classroom zugewiesenen Kategorien.
     */
    public static function getModuleLessons(int $classroomID, int $categoryID, int $userID): array
    {
        $classroom = new PrivateForumClassroom($classroomID);
        if (!$classroom->classroomID) {
            return [];
        }

        $databaseID = self::getDatabaseID();
        if (!$databaseID) {
            return [];
        }

        // Prüfen ob Kategorie dem Classroom zugewiesen ist
        $assignedIDs = self::getAssignedCategoryIDs($classroomID);
        if (!\in_array($categoryID, $assignedIDs)) {
            return [];
        }

        $sql = "SELECT e.entryID, e.subject, e.categoryID, e.time, e.message, e.teaser, e.coverImagePath
                FROM wcf1_flexiblelist_entry e
                WHERE e.databaseID = ? AND e.categoryID = ? AND e.isDisabled = 0 AND e.isDeleted = 0
                ORDER BY e.time ASC";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$databaseID, $categoryID]);

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

    /**
     * Gibt eine einzelne Lektion mit Content zurück.
     */
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

    /**
     * Gibt den Kategorie-Titel zurück.
     */
    public static function getCategoryTitle(int $categoryID): string
    {
        $sql = "SELECT title FROM wcf1_flexiblelist_category WHERE categoryID = ?";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$categoryID]);
        $row = $statement->fetchSingleRow();

        return $row ? $row['title'] : '';
    }

    /**
     * Gibt die FlexibleList Database-ID aus der Konstante zurück.
     */
    public static function getDatabaseID(): int
    {
        if (\defined('PRIVATEFORUM_CLASSROOM_DATABASE_ID')) {
            return (int)PRIVATEFORUM_CLASSROOM_DATABASE_ID;
        }

        return 0;
    }
}

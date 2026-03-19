<?php

namespace wcf\page;

use wcf\data\privateforum\classroom\PrivateForumClassroom;
use wcf\data\privateforum\forum\PrivateForum;
use wcf\page\AbstractPage;
use wcf\system\exception\IllegalLinkException;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\privateforum\ClassroomService;
use wcf\system\privateforum\PrivateForumAccessService;
use wcf\system\WCF;

/**
 * Classroom-Übersichtsseite: zeigt Module als Karten-Grid mit Fortschritt.
 * Owner sieht zusätzlich die Modul-Zuweisung.
 */
class PrivateForumClassroomPage extends AbstractPage
{
    /** @var string[] */
    public $neededPermissions = ['user.privateforum.canView'];

    /** @var string */
    public $templateName = 'privateForumClassroom';

    public ?PrivateForum $forum = null;
    public ?PrivateForumClassroom $classroom = null;
    public array $modules = [];
    public array $progress = ['completed' => 0, 'total' => 0, 'percentage' => 0.0];
    public bool $isOwner = false;
    public array $availableCategories = [];
    public array $assignedCategoryIDs = [];

    public function readParameters(): void
    {
        parent::readParameters();

        $privateforumID = $_REQUEST['id'] ?? 0;
        $this->forum = new PrivateForum(\intval($privateforumID));
        if (!$this->forum->privateforumID || !$this->forum->isActive) {
            throw new IllegalLinkException();
        }
    }

    public function checkPermissions(): void
    {
        parent::checkPermissions();

        $userID = WCF::getUser()->userID;
        if (!PrivateForumAccessService::getInstance()->hasAccess($this->forum, $userID)) {
            throw new PermissionDeniedException();
        }
    }

    public function readData(): void
    {
        parent::readData();

        $userID = WCF::getUser()->userID ?: 0;
        $this->isOwner = $this->forum->isOwner($userID);

        $this->classroom = ClassroomService::getClassroomByForumID($this->forum->privateforumID);
        if ($this->classroom && $this->classroom->isActive) {
            $this->modules = ClassroomService::getModulesWithProgress($this->classroom->classroomID, $userID);
            $this->progress = ClassroomService::getUserProgress($this->classroom->classroomID, $userID);

            // Zugewiesene Kategorie-IDs laden
            if ($this->isOwner) {
                $sql = "SELECT categoryID FROM wcf1_privateforum_classroom_module
                        WHERE classroomID = ? ORDER BY sortOrder ASC";
                $statement = WCF::getDB()->prepare($sql);
                $statement->execute([$this->classroom->classroomID]);
                $this->assignedCategoryIDs = $statement->fetchAll(\PDO::FETCH_COLUMN);
            }
        }

        // Verfügbare FlexibleList-Kategorien laden (für Owner)
        if ($this->isOwner) {
            $databaseID = \defined('PRIVATEFORUM_CLASSROOM_DATABASE_ID')
                ? \intval(PRIVATEFORUM_CLASSROOM_DATABASE_ID)
                : 0;

            if ($databaseID > 0) {
                $sql = "SELECT categoryID, title, showOrder
                        FROM wcf1_flexiblelist_category
                        WHERE databaseID = ?
                        ORDER BY showOrder ASC, title ASC";
                $statement = WCF::getDB()->prepare($sql);
                $statement->execute([$databaseID]);
                while ($row = $statement->fetchArray()) {
                    $this->availableCategories[] = [
                        'categoryID' => (int)$row['categoryID'],
                        'title' => $row['title'],
                        'isAssigned' => \in_array((int)$row['categoryID'], $this->assignedCategoryIDs),
                    ];
                }
            }
        }
    }

    public function assignVariables(): void
    {
        parent::assignVariables();

        WCF::getTPL()->assign([
            'forum' => $this->forum,
            'classroom' => $this->classroom,
            'modules' => $this->modules,
            'progress' => $this->progress,
            'isOwner' => $this->isOwner,
            'availableCategories' => $this->availableCategories,
            'assignedCategoryIDs' => $this->assignedCategoryIDs,
        ]);
    }
}

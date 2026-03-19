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
 * Classroom-Übersichtsseite: zeigt Module (FlexibleList-Kategorien) als Karten-Grid
 * mit Fortschrittsbalken pro User.
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

        // Mitgliedschaft prüfen
        $userID = WCF::getUser()->userID;
        if (!PrivateForumAccessService::getInstance()->hasAccess($this->forum, $userID)) {
            throw new PermissionDeniedException();
        }
    }

    public function readData(): void
    {
        parent::readData();

        $this->classroom = ClassroomService::getClassroomByForumID($this->forum->privateforumID);
        if ($this->classroom && $this->classroom->isActive) {
            $userID = WCF::getUser()->userID ?: 0;
            $this->modules = ClassroomService::getModulesWithProgress($this->classroom->classroomID, $userID);
            $this->progress = ClassroomService::getUserProgress($this->classroom->classroomID, $userID);
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
        ]);
    }
}

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
 * Modul-Detailseite: linke Sidebar mit Lektionsliste (Checkmarks),
 * rechter Bereich mit Lesson-Content (Video, Text, Bilder).
 */
class PrivateForumClassroomModulePage extends AbstractPage
{
    /** @var string[] */
    public $neededPermissions = ['user.privateforum.canView'];

    /** @var string */
    public $templateName = 'privateForumClassroomModule';

    public ?PrivateForum $forum = null;
    public ?PrivateForumClassroom $classroom = null;
    public int $categoryID = 0;
    public string $moduleTitle = '';
    public array $lessons = [];
    public array $activeLesson = [];
    public array $progress = ['completed' => 0, 'total' => 0, 'percentage' => 0.0];
    public array $moduleProgress = ['completedCount' => 0, 'totalCount' => 0, 'percentage' => 0.0];

    public function readParameters(): void
    {
        parent::readParameters();

        $privateforumID = $_REQUEST['id'] ?? 0;
        $this->forum = new PrivateForum(\intval($privateforumID));
        if (!$this->forum->privateforumID || !$this->forum->isActive) {
            throw new IllegalLinkException();
        }

        $this->categoryID = \intval($_REQUEST['categoryID'] ?? 0);
        if (!$this->categoryID) {
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
        if (!$this->classroom || !$this->classroom->isActive) {
            throw new IllegalLinkException();
        }

        $this->moduleTitle = ClassroomService::getCategoryTitle($this->categoryID);
        if (empty($this->moduleTitle)) {
            throw new IllegalLinkException();
        }

        $userID = WCF::getUser()->userID ?: 0;
        $this->lessons = ClassroomService::getModuleLessons($this->classroom->classroomID, $this->categoryID, $userID);
        $this->progress = ClassroomService::getUserProgress($this->classroom->classroomID, $userID);

        // Modul-Fortschritt berechnen
        $completedCount = \count(\array_filter($this->lessons, fn($l) => $l['isCompleted']));
        $totalCount = \count($this->lessons);
        $this->moduleProgress = [
            'completedCount' => $completedCount,
            'totalCount' => $totalCount,
            'percentage' => $totalCount > 0 ? \round(($completedCount / $totalCount) * 100, 1) : 0.0,
        ];

        // Aktive Lektion bestimmen (per URL-Parameter oder erste nicht-abgeschlossene)
        $activeLessonID = \intval($_REQUEST['lessonID'] ?? 0);
        if ($activeLessonID) {
            foreach ($this->lessons as $lesson) {
                if ($lesson['entryID'] === $activeLessonID) {
                    $this->activeLesson = $lesson;
                    break;
                }
            }
        }
        // Fallback: erste nicht abgeschlossene Lektion, oder erste Lektion
        if (empty($this->activeLesson) && !empty($this->lessons)) {
            foreach ($this->lessons as $lesson) {
                if (!$lesson['isCompleted']) {
                    $this->activeLesson = $lesson;
                    break;
                }
            }
            if (empty($this->activeLesson)) {
                $this->activeLesson = $this->lessons[0];
            }
        }

        // Full Content der aktiven Lektion laden
        if (!empty($this->activeLesson)) {
            $fullLesson = ClassroomService::getLesson($this->activeLesson['entryID']);
            if ($fullLesson) {
                $this->activeLesson['message'] = $fullLesson['message'];
                $this->activeLesson['teaser'] = $fullLesson['teaser'];
                $this->activeLesson['coverImagePath'] = $fullLesson['coverImagePath'];

                // Completion-Status für aktive Lektion prüfen
                if ($userID) {
                    $lessonProgress = ClassroomService::getLessonProgress($this->activeLesson['entryID'], $userID);
                    $this->activeLesson['isCompleted'] = $lessonProgress && $lessonProgress->isCompleted;
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
            'categoryID' => $this->categoryID,
            'moduleTitle' => $this->moduleTitle,
            'lessons' => $this->lessons,
            'activeLesson' => $this->activeLesson,
            'progress' => $this->progress,
            'moduleProgress' => $this->moduleProgress,
        ]);
    }
}

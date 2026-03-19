<?php

namespace wcf\system\box;

use wcf\system\box\AbstractBoxController;
use wcf\system\privateforum\ClassroomService;
use wcf\system\privateforum\PrivateForumAccessService;
use wcf\system\request\RequestHandler;
use wcf\system\WCF;

/**
 * Sidebar-Box: Zeigt Classroom-Module als Links.
 * Nur sichtbar wenn das aktuelle Board ein PrivateForum mit aktivem Classroom hat.
 */
class PrivateForumClassroomBoxController extends AbstractBoxController
{
    protected static $supportedPositions = ['sidebarLeft', 'sidebarRight'];

    protected function loadContent(): void
    {
        // Aktuelles Board ermitteln
        $boardID = $this->getCurrentBoardID();
        if (!$boardID) {
            return;
        }

        // PrivateForum für dieses Board finden
        $forum = PrivateForumAccessService::getInstance()->getForumByBoardID($boardID);
        if (!$forum || !$forum->isActive) {
            return;
        }

        // Classroom laden
        $classroom = ClassroomService::getClassroomByForumID($forum->privateforumID);
        if (!$classroom || !$classroom->isActive) {
            return;
        }

        $userID = WCF::getUser()->userID ?: 0;
        $modules = ClassroomService::getModulesWithProgress($classroom->classroomID, $userID);
        $progress = ClassroomService::getUserProgress($classroom->classroomID, $userID);

        if (!empty($modules)) {
            $this->content = WCF::getTPL()->fetch('boxPrivateForumClassroom', 'wcf', [
                'forum' => $forum,
                'classroom' => $classroom,
                'modules' => $modules,
                'progress' => $progress,
            ]);
        }
    }

    /**
     * Ermittelt die aktuelle Board-ID aus dem Request.
     */
    private function getCurrentBoardID(): int
    {
        $activeRequest = RequestHandler::getInstance()->getActiveRequest();
        if ($activeRequest) {
            $controller = $activeRequest->getClassName();
            if ($controller === 'wbb\page\BoardPage' || $controller === 'wbb\page\ThreadPage') {
                if (isset($_REQUEST['id'])) {
                    // ThreadPage: boardID aus Thread laden
                    if ($controller === 'wbb\page\ThreadPage') {
                        $thread = new \wbb\data\thread\Thread(\intval($_REQUEST['id']));
                        return $thread->boardID ?: 0;
                    }
                    return \intval($_REQUEST['id']);
                }
            }
        }
        return 0;
    }
}

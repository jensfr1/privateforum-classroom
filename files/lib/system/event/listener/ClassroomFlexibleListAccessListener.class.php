<?php

namespace wcf\system\event\listener;

use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\privateforum\PrivateForumAccessService;
use wcf\system\WCF;

/**
 * Prüft ob eine FlexibleList-Datenbank zu einem Classroom gehört.
 * Falls ja, muss der User Mitglied des zugehörigen PrivateForums sein.
 */
class ClassroomFlexibleListAccessListener implements IParameterizedEventListener
{
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        try {
            $databaseID = null;

            // FlexibleListEntryListPage hat $databaseID Property
            if (isset($eventObj->databaseID)) {
                $databaseID = (int)$eventObj->databaseID;
            }
            // Oder aus dem Request
            elseif (isset($_REQUEST['databaseID'])) {
                $databaseID = (int)$_REQUEST['databaseID'];
            }
            // FlexibleListEntryPage: Entry laden und databaseID extrahieren
            elseif (isset($eventObj->entry) && $eventObj->entry) {
                $databaseID = (int)$eventObj->entry->databaseID;
            }
            // FlexibleList (DB-Übersicht): id = databaseID
            elseif (isset($_REQUEST['id']) && $className === 'wcf\page\FlexibleListEntryListPage') {
                $databaseID = (int)$_REQUEST['id'];
            }

            if (!$databaseID) {
                return;
            }

            // Prüfen ob diese DB zu einem Classroom gehört
            $sql = "SELECT c.classroomID, c.privateforumID
                    FROM wcf1_privateforum_classroom c
                    WHERE c.databaseID = ?";
            $statement = WCF::getDB()->prepare($sql);
            $statement->execute([$databaseID]);
            $row = $statement->fetchSingleRow();

            if (!$row) {
                // DB gehört nicht zu einem Classroom
                return;
            }

            $privateforumID = (int)$row['privateforumID'];
            $userID = WCF::getUser()->userID;

            // PrivateForum laden und Zugriff prüfen
            $forum = new \wcf\data\privateforum\forum\PrivateForum($privateforumID);
            if (!$forum->privateforumID) {
                return;
            }

            if (!PrivateForumAccessService::getInstance()->hasAccess($forum, $userID)) {
                throw new PermissionDeniedException();
            }
        } catch (PermissionDeniedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \error_log('[PrivateForum Classroom] FlexibleList access check error: ' . $e->getMessage());
        }
    }
}

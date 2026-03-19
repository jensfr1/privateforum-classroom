<?php

namespace wcf\system\endpoint\controller\amp\privateforum\classroom;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use wcf\data\privateforum\classroom\PrivateForumClassroomAction;
use wcf\data\privateforum\classroom\PrivateForumClassroomEditor;
use wcf\data\privateforum\forum\PrivateForum;
use wcf\system\endpoint\IController;
use wcf\system\endpoint\PostRequest;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\privateforum\ClassroomService;
use wcf\system\WCF;

#[PostRequest('/amp/privateforum/forums/{id:\d+}/classroom/toggle')]
final class ToggleClassroom implements IController
{
    public function __invoke(ServerRequestInterface $request, array $variables): ResponseInterface
    {
        $userID = WCF::getUser()->userID;
        if (!$userID) {
            throw new PermissionDeniedException();
        }

        $privateforumID = (int)$variables['id'];
        $forum = new PrivateForum($privateforumID);
        if (!$forum->privateforumID) {
            return new JsonResponse(['error' => 'Forum not found'], 404);
        }

        if (!$forum->isOwner($userID) && !WCF::getSession()->getPermission('admin.privateforum.canManage')) {
            throw new PermissionDeniedException();
        }

        $classroom = ClassroomService::getClassroomByForumID($privateforumID);

        if ($classroom) {
            // Toggle isActive
            $editor = new PrivateForumClassroomEditor($classroom);
            $newState = $classroom->isActive ? 0 : 1;
            $editor->update(['isActive' => $newState]);

            return new JsonResponse([
                'success' => true,
                'isActive' => (bool)$newState,
                'classroomID' => $classroom->classroomID,
            ]);
        }

        // Classroom existiert noch nicht → erstellen + eigene FlexibleList-DB anlegen
        $databaseID = $this->createFlexibleListDatabase($forum);

        $action = new PrivateForumClassroomAction([], 'create', [
            'data' => [
                'privateforumID' => $privateforumID,
                'databaseID' => $databaseID,
                'title' => $forum->title,
                'isActive' => 1,
                'time' => TIME_NOW,
            ],
        ]);
        $result = $action->executeAction();
        $newClassroom = $result['returnValues'];

        return new JsonResponse([
            'success' => true,
            'isActive' => true,
            'classroomID' => $newClassroom->classroomID,
            'databaseID' => $databaseID,
        ]);
    }

    /**
     * Erstellt eine eigene FlexibleList-Datenbank für dieses Forum.
     */
    private function createFlexibleListDatabase(PrivateForum $forum): int
    {
        try {
            $slug = \preg_replace('/[^a-z0-9-]/', '', \strtolower(\str_replace(' ', '-', $forum->title)));
            $database = \wcf\data\flexiblelist\FlexibleListEditor::create([
                'title' => 'Classroom: ' . $forum->title,
                'path' => 'classroom-' . ($slug ?: $forum->privateforumID),
                'sortField' => 'lastChangeTime',
                'sortOrder' => 'ASC',
                'entriesPerPage' => 50,
                'isDisabled' => 0,
                'enableSubject' => 1,
                'enableTeaser' => 1,
                'enableMessage' => 1,
                'enableCoverImage' => 1,
                'enableCategories' => 1,
                'enableComments' => 0,
                'enableReactions' => 0,
                'enableLabels' => 0,
                'enableTags' => 0,
                'enableAttachments' => 1,
                'enableDoneStatus' => 0,
                'enableMaps' => 0,
                'enableModeration' => 0,
                'enableUserGeneratedContent' => 0,
                'enableRatings' => 0,
                'homepageDisplay' => 'card',
            ]);

            $databaseID = $database->databaseID;

            // Beispiel-Module (Kategorien) erstellen
            $this->createSampleContent($databaseID);

            return $databaseID;
        } catch (\Throwable $e) {
            \error_log('[PrivateForum Classroom] Failed to create FlexibleList DB: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Erstellt Beispiel-Kategorien und -Einträge für eine neue Classroom-DB.
     */
    private function createSampleContent(int $databaseID): void
    {
        try {
            $userID = WCF::getUser()->userID;
            $username = WCF::getUser()->username;

            // Modul 1: Einführung
            $cat1 = \wcf\data\flexiblelist\category\FlexibleListCategoryEditor::create([
                'databaseID' => $databaseID,
                'title' => 'Einführung',
                'showOrder' => 1,
            ]);

            // Modul 2: Fortgeschritten
            $cat2 = \wcf\data\flexiblelist\category\FlexibleListCategoryEditor::create([
                'databaseID' => $databaseID,
                'title' => 'Fortgeschritten',
                'showOrder' => 2,
            ]);

            // Beispiel-Lektionen für Modul 1
            \wcf\data\flexiblelist\entry\FlexibleListEntryEditor::create([
                'databaseID' => $databaseID,
                'categoryID' => $cat1->categoryID,
                'subject' => 'Willkommen im Classroom',
                'message' => '<p>Dies ist deine erste Lektion. Hier kannst du Inhalte für deine Mitglieder bereitstellen.</p><p>Verwende den FlexibleList-Editor um Lektionen mit Text, Bildern und Videos zu erstellen.</p>',
                'teaser' => 'Einführung in das Classroom-System',
                'userID' => $userID,
                'username' => $username,
                'time' => TIME_NOW,
                'lastChangeTime' => TIME_NOW,
            ]);

            \wcf\data\flexiblelist\entry\FlexibleListEntryEditor::create([
                'databaseID' => $databaseID,
                'categoryID' => $cat1->categoryID,
                'subject' => 'So funktioniert der Fortschritt',
                'message' => '<p>Mitglieder können Lektionen als erledigt markieren. Der Fortschritt wird pro Modul und insgesamt angezeigt.</p>',
                'teaser' => 'Fortschrittsverfolgung erklärt',
                'userID' => $userID,
                'username' => $username,
                'time' => TIME_NOW + 1,
                'lastChangeTime' => TIME_NOW + 1,
            ]);

            // Beispiel-Lektion für Modul 2
            \wcf\data\flexiblelist\entry\FlexibleListEntryEditor::create([
                'databaseID' => $databaseID,
                'categoryID' => $cat2->categoryID,
                'subject' => 'Nächste Schritte',
                'message' => '<p>Erstelle weitere Module und Lektionen über den "Lektion hinzufügen" Button oder die FlexibleList-Verwaltung.</p>',
                'teaser' => 'Wie du dein Classroom ausbaust',
                'userID' => $userID,
                'username' => $username,
                'time' => TIME_NOW + 2,
                'lastChangeTime' => TIME_NOW + 2,
            ]);
        } catch (\Throwable $e) {
            \error_log('[PrivateForum Classroom] Failed to create sample content: ' . $e->getMessage());
        }
    }
}

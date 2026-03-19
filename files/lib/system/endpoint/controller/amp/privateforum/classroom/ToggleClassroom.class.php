<?php

namespace wcf\system\endpoint\controller\amp\privateforum\classroom;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use wcf\data\privateforum\classroom\PrivateForumClassroom;
use wcf\data\privateforum\classroom\PrivateForumClassroomAction;
use wcf\data\privateforum\classroom\PrivateForumClassroomEditor;
use wcf\data\privateforum\forum\PrivateForum;
use wcf\system\endpoint\IController;
use wcf\system\endpoint\PostRequest;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\privateforum\ClassroomService;
use wcf\system\privateforum\PrivateForumAccessService;
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

        // Nur Owner oder Admin darf Classroom togglen
        if (!$forum->isOwner($userID) && !WCF::getSession()->getPermission('admin.privateforum.canManage')) {
            throw new PermissionDeniedException();
        }

        $classroom = ClassroomService::getClassroomByForumID($privateforumID);

        if ($classroom) {
            // Toggle isActive
            $editor = new PrivateForumClassroomEditor($classroom);
            $newState = $classroom->isActive ? 0 : 1;
            $editor->update([
                'isActive' => $newState,
            ]);

            return new JsonResponse([
                'success' => true,
                'isActive' => (bool)$newState,
                'classroomID' => $classroom->classroomID,
            ]);
        }

        // Classroom existiert noch nicht → erstellen
        $action = new PrivateForumClassroomAction([], 'create', [
            'data' => [
                'privateforumID' => $privateforumID,
                'title' => $forum->title,
                'isActive' => 1,
                'time' => \TIME_NOW,
            ],
        ]);
        $result = $action->executeAction();
        $newClassroom = $result['returnValues'];

        return new JsonResponse([
            'success' => true,
            'isActive' => true,
            'classroomID' => $newClassroom->classroomID,
        ]);
    }
}

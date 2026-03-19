<?php

namespace wcf\system\endpoint\controller\amp\privateforum\classroom;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use wcf\data\privateforum\classroom\PrivateForumClassroom;
use wcf\data\privateforum\forum\PrivateForum;
use wcf\http\Helper;
use wcf\system\endpoint\IController;
use wcf\system\endpoint\PostRequest;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\privateforum\ClassroomService;
use wcf\system\privateforum\PrivateForumAccessService;
use wcf\system\WCF;

#[PostRequest('/amp/privateforum/classroom/{id:\d+}/complete')]
final class MarkLessonComplete implements IController
{
    public function __invoke(ServerRequestInterface $request, array $variables): ResponseInterface
    {
        $userID = WCF::getUser()->userID;
        if (!$userID) {
            throw new PermissionDeniedException();
        }

        /** @var MarkLessonCompleteParameters $body */
        $body = Helper::mapApiParameters($request, MarkLessonCompleteParameters::class);
        $classroomID = (int)$variables['id'];

        $classroom = new PrivateForumClassroom($classroomID);
        if (!$classroom->classroomID || !$classroom->isActive) {
            return new JsonResponse(['error' => 'Classroom not found'], 404);
        }

        // Mitgliedschaft prüfen
        $forum = $classroom->getPrivateForum();
        if (!$forum || !PrivateForumAccessService::getInstance()->hasAccess($forum, $userID)) {
            throw new PermissionDeniedException();
        }

        // Toggle: wenn schon completed → uncomplete, sonst complete
        $existing = ClassroomService::getLessonProgress($body->entryID, $userID);
        if ($existing && $existing->isCompleted) {
            $success = ClassroomService::markLessonIncomplete($classroomID, $body->entryID, $userID);
            $isCompleted = false;
        } else {
            $success = ClassroomService::markLessonComplete($classroomID, $body->entryID, $userID);
            $isCompleted = true;
        }

        $progress = ClassroomService::getUserProgress($classroomID, $userID);

        return new JsonResponse([
            'success' => $success,
            'isCompleted' => $isCompleted,
            'progress' => $progress,
        ]);
    }
}

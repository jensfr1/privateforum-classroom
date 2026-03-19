<?php

namespace wcf\system\endpoint\controller\amp\privateforum\classroom;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use wcf\data\privateforum\classroom\PrivateForumClassroom;
use wcf\http\Helper;
use wcf\system\endpoint\IController;
use wcf\system\endpoint\PostRequest;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\WCF;

#[PostRequest('/amp/privateforum/classroom/{id:\d+}/modules')]
final class AssignModule implements IController
{
    public function __invoke(ServerRequestInterface $request, array $variables): ResponseInterface
    {
        $userID = WCF::getUser()->userID;
        if (!$userID) {
            throw new PermissionDeniedException();
        }

        /** @var AssignModuleParameters $body */
        $body = Helper::mapApiParameters($request, AssignModuleParameters::class);
        $classroomID = (int)$variables['id'];

        $classroom = new PrivateForumClassroom($classroomID);
        if (!$classroom->classroomID) {
            return new JsonResponse(['error' => 'Classroom not found'], 404);
        }

        // Nur Forum-Owner darf Module zuweisen
        $forum = $classroom->getPrivateForum();
        if (!$forum || (!$forum->isOwner($userID) && !WCF::getSession()->getPermission('admin.privateforum.canManage'))) {
            throw new PermissionDeniedException();
        }

        // Alle bestehenden Zuordnungen löschen
        $sql = "DELETE FROM wcf1_privateforum_classroom_module WHERE classroomID = ?";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$classroomID]);

        // Neue Zuordnungen erstellen
        if (!empty($body->categoryIDs)) {
            $sql = "INSERT INTO wcf1_privateforum_classroom_module (classroomID, categoryID, sortOrder)
                    VALUES (?, ?, ?)";
            $statement = WCF::getDB()->prepare($sql);

            WCF::getDB()->beginTransaction();
            $sortOrder = 0;
            foreach ($body->categoryIDs as $categoryID) {
                $categoryID = (int)$categoryID;
                if ($categoryID > 0) {
                    $statement->execute([$classroomID, $categoryID, $sortOrder]);
                    $sortOrder++;
                }
            }
            WCF::getDB()->commitTransaction();
        }

        return new JsonResponse([
            'success' => true,
            'assignedCount' => \count($body->categoryIDs),
        ]);
    }
}

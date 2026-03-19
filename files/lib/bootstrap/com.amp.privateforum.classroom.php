<?php

use wcf\event\endpoint\ControllerCollecting;
use wcf\system\event\EventHandler;

return static function (): void {
    EventHandler::getInstance()->register(
        ControllerCollecting::class,
        static function (ControllerCollecting $event): void {
            $event->register(new \wcf\system\endpoint\controller\amp\privateforum\classroom\MarkLessonComplete());
            $event->register(new \wcf\system\endpoint\controller\amp\privateforum\classroom\ToggleClassroom());
            $event->register(new \wcf\system\endpoint\controller\amp\privateforum\classroom\AssignModule());
        }
    );
};

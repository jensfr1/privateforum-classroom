<?php

namespace wcf\system\endpoint\controller\amp\privateforum\classroom;

/**
 * DTO für MarkLessonComplete-Endpoint.
 */
final class MarkLessonCompleteParameters
{
    public function __construct(
        public readonly int $entryID,
    ) {}
}

<?php

namespace wcf\system\endpoint\controller\amp\privateforum\classroom;

/**
 * DTO für AssignModule-Endpoint.
 */
final class AssignModuleParameters
{
    public function __construct(
        public readonly array $categoryIDs,
    ) {}
}

<?php

namespace wcf\data\privateforum\classroom;

use wcf\data\AbstractDatabaseObjectAction;

class PrivateForumClassroomAction extends AbstractDatabaseObjectAction
{
    /** @var class-string */
    public $className = PrivateForumClassroomEditor::class;

    protected $permissionsCreate = ['admin.privateforum.canManage'];
    protected $permissionsUpdate = ['admin.privateforum.canManage'];
    protected $permissionsDelete = ['admin.privateforum.canManage'];
    protected $requireACP = ['create', 'update', 'delete'];
}

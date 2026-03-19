<?php

use wcf\system\database\table\column\DefaultFalseBooleanDatabaseTableColumn;
use wcf\system\database\table\column\IntDatabaseTableColumn;
use wcf\system\database\table\column\NotNullInt10DatabaseTableColumn;
use wcf\system\database\table\column\NotNullVarchar255DatabaseTableColumn;
use wcf\system\database\table\column\ObjectIdDatabaseTableColumn;
use wcf\system\database\table\DatabaseTable;
use wcf\system\database\table\index\DatabaseTableForeignKey;
use wcf\system\database\table\index\DatabaseTableIndex;

return [
    // Classroom: Verknüpft PrivateForum mit FlexibleList-Database
    DatabaseTable::create('wcf1_privateforum_classroom')
        ->columns([
            ObjectIdDatabaseTableColumn::create('classroomID'),
            NotNullInt10DatabaseTableColumn::create('privateforumID'),
            NotNullInt10DatabaseTableColumn::create('databaseID')
                ->defaultValue(0),
            NotNullVarchar255DatabaseTableColumn::create('title'),
            DefaultFalseBooleanDatabaseTableColumn::create('isActive'),
            NotNullInt10DatabaseTableColumn::create('time')
                ->defaultValue(0),
        ])
        ->indices([
            DatabaseTableIndex::create('idx_classroom_privateforumID')
                ->type(DatabaseTableIndex::UNIQUE_TYPE)
                ->columns(['privateforumID']),
        ])
        ->foreignKeys([
            DatabaseTableForeignKey::create()
                ->columns(['privateforumID'])
                ->referencedTable('wcf1_privateforum')
                ->referencedColumns(['privateforumID'])
                ->onDelete('CASCADE'),
        ]),

    // Modul-Zuordnung: Welche FlexibleList-Kategorien gehören zu einem Classroom
    DatabaseTable::create('wcf1_privateforum_classroom_module')
        ->columns([
            ObjectIdDatabaseTableColumn::create('moduleID'),
            NotNullInt10DatabaseTableColumn::create('classroomID'),
            NotNullInt10DatabaseTableColumn::create('categoryID'),
            NotNullInt10DatabaseTableColumn::create('sortOrder')
                ->defaultValue(0),
        ])
        ->indices([
            DatabaseTableIndex::create('idx_module_classroom_category')
                ->type(DatabaseTableIndex::UNIQUE_TYPE)
                ->columns(['classroomID', 'categoryID']),
            DatabaseTableIndex::create('idx_module_classroomID')
                ->columns(['classroomID']),
        ])
        ->foreignKeys([
            DatabaseTableForeignKey::create()
                ->columns(['classroomID'])
                ->referencedTable('wcf1_privateforum_classroom')
                ->referencedColumns(['classroomID'])
                ->onDelete('CASCADE'),
        ]),

    // Lektionsfortschritt pro User
    DatabaseTable::create('wcf1_privateforum_lesson_progress')
        ->columns([
            ObjectIdDatabaseTableColumn::create('progressID'),
            NotNullInt10DatabaseTableColumn::create('classroomID'),
            NotNullInt10DatabaseTableColumn::create('entryID'),
            NotNullInt10DatabaseTableColumn::create('userID'),
            DefaultFalseBooleanDatabaseTableColumn::create('isCompleted'),
            IntDatabaseTableColumn::create('completedTime'),
        ])
        ->indices([
            DatabaseTableIndex::create('idx_progress_entry_user')
                ->type(DatabaseTableIndex::UNIQUE_TYPE)
                ->columns(['entryID', 'userID']),
            DatabaseTableIndex::create('idx_progress_classroomID')
                ->columns(['classroomID']),
            DatabaseTableIndex::create('idx_progress_userID')
                ->columns(['userID']),
        ])
        ->foreignKeys([
            DatabaseTableForeignKey::create()
                ->columns(['classroomID'])
                ->referencedTable('wcf1_privateforum_classroom')
                ->referencedColumns(['classroomID'])
                ->onDelete('CASCADE'),
            DatabaseTableForeignKey::create()
                ->columns(['userID'])
                ->referencedTable('wcf1_user')
                ->referencedColumns(['userID'])
                ->onDelete('CASCADE'),
        ]),
];

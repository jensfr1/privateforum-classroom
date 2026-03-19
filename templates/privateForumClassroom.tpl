{include file='header' pageTitle='wcf.privateforum.classroom.title'}

<link rel="stylesheet" href="{$__wcf->getPath()}style/privateForumClassroom.css?t={LAST_UPDATE_TIME}">
<style>
	.boxesSidebarLeft, .boxesSidebarRight { display: none !important; }
	#content { max-width: 100% !important; flex: 1 1 100% !important; }
	.pageContainer > .boxContainer { display: none !important; }
</style>

<header class="contentHeader">
    <div class="contentHeaderTitle">
        <h1 class="contentTitle">{$forum->title} &ndash; {lang}wcf.privateforum.classroom.title{/lang}</h1>
    </div>
    <nav class="contentHeaderNavigation">
        <ul>
            {if $isOwner && $classroom && $classroom->databaseID}
                <li><a href="{link controller='FlexibleListEntryAdd'}databaseID={$classroom->databaseID}{/link}" class="button">
                    {icon name='plus'} <span>{lang}wcf.privateforum.classroom.addLesson{/lang}</span>
                </a></li>
                <li><a href="{link controller='FlexibleListEntryList' id=$classroom->databaseID}{/link}" class="button">
                    {icon name='gear'} <span>{lang}wcf.privateforum.classroom.manageContent{/lang}</span>
                </a></li>
            {/if}
            <li><a href="{link controller='Board' id=$forum->boardID application='wbb'}{/link}" class="button">
                {icon name='arrow-left'} <span>{lang}wcf.privateforum.classroom.backToForum{/lang}</span>
            </a></li>
        </ul>
    </nav>
</header>

{if $classroom && $classroom->isActive}
    {* Gesamt-Fortschrittsbalken *}
    <section class="section">
        <div class="pfClassroomProgress">
            <div class="pfProgressBar">
                <div class="pfProgressFill" style="width: {$progress[percentage]}%"></div>
            </div>
            <span class="pfProgressLabel">{$progress[completed]}/{$progress[total]} {lang}wcf.privateforum.classroom.lessonsCompleted{/lang} ({$progress[percentage]}%)</span>
        </div>
    </section>

    {* Modul-Karten Grid *}
    {if $modules|count}
        <div class="pfModuleGrid">
            {foreach from=$modules item=module}
                <a href="{link controller='PrivateForumClassroomModule' id=$forum->privateforumID}categoryID={$module[categoryID]}{/link}" class="pfModuleCard">
                    <div class="pfModuleCardCover">
                        {if $module[coverImage]}
                            <img src="{$__wcf->getPath()}{$module[coverImage]}" alt="{$module[title]}" loading="lazy">
                        {else}
                            <div class="pfModuleCardCoverPlaceholder">
                                {icon name='book-open'}
                            </div>
                        {/if}
                    </div>
                    <div class="pfModuleCardBody">
                        <h3 class="pfModuleCardTitle">{$module[title]}</h3>
                        <div class="pfModuleCardMeta">
                            <span>{$module[totalCount]} {lang}wcf.privateforum.classroom.lessons{/lang}</span>
                        </div>
                        <div class="pfModuleProgress">
                            <div class="pfProgressBar pfProgressBarSmall">
                                <div class="pfProgressFill" style="width: {$module[percentage]}%"></div>
                            </div>
                            <span class="pfModuleProgressLabel">{$module[percentage]}%</span>
                        </div>
                    </div>
                </a>
            {/foreach}
        </div>
    {else}
        <woltlab-core-notice type="info">{lang}wcf.privateforum.classroom.noModules{/lang}</woltlab-core-notice>
        {if $isOwner}
            <p style="margin-top: 15px;">
                {lang}wcf.privateforum.classroom.addModulesHint{/lang}
            </p>
        {/if}
    {/if}
{else}
    <woltlab-core-notice type="info">{lang}wcf.privateforum.classroom.noClassroom{/lang}</woltlab-core-notice>
{/if}

{include file='footer'}

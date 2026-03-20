{include file='header' pageTitle=$moduleTitle}

<link rel="stylesheet" href="{$__wcf->getPath()}style/privateForumClassroom.css?t={LAST_UPDATE_TIME}">
<style>
	.boxesSidebarLeft, .boxesSidebarRight { display: none !important; }
	#content { max-width: 100% !important; flex: 1 1 100% !important; }
	.pageContainer > .boxContainer { display: none !important; }
</style>

<header class="contentHeader">
    <div class="contentHeaderTitle">
        <h1 class="contentTitle">{$moduleTitle}</h1>
        <p class="contentHeaderDescription">{$forum->title} &ndash; {lang}wcf.privateforum.classroom.title{/lang}</p>
    </div>
    <nav class="contentHeaderNavigation">
        <ul>
            <li><a href="{link controller='PrivateForumClassroom' id=$forum->privateforumID}{/link}" class="button">
                {icon name='arrow-left'} <span>{lang}wcf.privateforum.classroom.backToOverview{/lang}</span>
            </a></li>
        </ul>
    </nav>
</header>

{if $lessons|count}
    {* Modul-Fortschritt *}
    <div class="pfModuleHeader">
        <div class="pfClassroomProgress">
            <div class="pfProgressBar">
                <div class="pfProgressFill" style="width: {$moduleProgress[percentage]}%"></div>
            </div>
            <span class="pfProgressLabel">{$moduleProgress[completedCount]}/{$moduleProgress[totalCount]} ({$moduleProgress[percentage]}%)</span>
        </div>
    </div>

    {* 2-Spalten Layout *}
    <div class="pfModuleLayout">
        {* Linke Sidebar: Lektionsliste *}
        <div class="pfLessonSidebar">
            <div class="pfLessonSidebarHeader">
                <h3>{lang}wcf.privateforum.classroom.lessons{/lang}</h3>
            </div>
            <div class="pfLessonList">
                {foreach from=$lessons item=lesson}
                    <a href="{link controller='PrivateForumClassroomModule' id=$forum->privateforumID}categoryID={$categoryID}&lessonID={$lesson[entryID]}{/link}"
                       class="pfLessonItem{if $lesson[isCompleted]} pfLessonCompleted{/if}{if $activeLesson[entryID] == $lesson[entryID]} pfLessonActive{/if}"
                       data-entry-id="{$lesson[entryID]}">
                        <span class="pfLessonCheck" data-entry-id="{$lesson[entryID]}">
                            {if $lesson[isCompleted]}
                                {icon name='circle-check'}
                            {else}
                                {icon name='circle'}
                            {/if}
                        </span>
                        <span class="pfLessonTitle">{$lesson[title]}</span>
                    </a>
                {/foreach}
            </div>
        </div>

        {* Rechter Bereich: Lesson-Content *}
        <div class="pfLessonContent">
            {if $activeLesson|isset && $activeLesson[entryID]}
                <div class="pfLessonContentHeader">
                    <h2 class="pfLessonContentTitle">{$activeLesson[title]}</h2>
                    {if $__wcf->user->userID}
                        <button class="button pfMarkDoneBtn{if $activeLesson[isCompleted]} pfMarkDoneCompleted{/if}"
                                data-entry-id="{$activeLesson[entryID]}"
                                data-classroom-id="{$classroom->classroomID}">
                            {if $activeLesson[isCompleted]}
                                {icon name='circle-check'} <span>{lang}wcf.privateforum.classroom.completed{/lang}</span>
                            {else}
                                {icon name='circle'} <span>{lang}wcf.privateforum.classroom.markDone{/lang}</span>
                            {/if}
                        </button>
                    {/if}
                </div>

                {if $activeLesson[coverImagePath]}
                    <div class="pfLessonCoverImage">
                        <img src="{$__wcf->getPath()}{$activeLesson[coverImagePath]}" alt="{$activeLesson[title]}" loading="lazy">
                    </div>
                {/if}

                {if $activeLesson[message]}
                    <div class="pfLessonBody htmlContent">
                        {unsafe:$activeLesson[message]}
                    </div>
                {elseif $activeLesson[teaser]}
                    <div class="pfLessonBody">
                        <p>{$activeLesson[teaser]}</p>
                    </div>
                {/if}

                {if $activeLesson[customFields]|isset && $activeLesson[customFields]|count}
                    <div class="pfLessonCustomFields">
                        {foreach from=$activeLesson[customFields] item=customField}
                            <div class="pfLessonField">
                                <h3 class="pfLessonFieldLabel">{$customField[fieldName]}</h3>
                                <div class="pfLessonFieldValue htmlContent">
                                    {unsafe:$customField[formattedValue]}
                                </div>
                            </div>
                        {/foreach}
                    </div>
                {/if}

                {if !$activeLesson[message] && !$activeLesson[teaser] && (!$activeLesson[customFields]|isset || !$activeLesson[customFields]|count)}
                    <div class="pfLessonBody">
                        <woltlab-core-notice type="info">{lang}wcf.privateforum.classroom.noContent{/lang}</woltlab-core-notice>
                    </div>
                {/if}
            {else}
                <woltlab-core-notice type="info">{lang}wcf.privateforum.classroom.selectLesson{/lang}</woltlab-core-notice>
            {/if}
        </div>
    </div>
{else}
    <woltlab-core-notice type="info">{lang}wcf.privateforum.classroom.noLessons{/lang}</woltlab-core-notice>
{/if}

<script data-relocate="true">
require(['WoltLabSuite/Core/Ajax/Backend', 'WoltLabSuite/Core/Ui/Notification'], (Backend, UiNotification) => {
    async function toggleComplete(classroomID, entryID) {
        const url = '{$__wcf->getPath()}index.php?api/rpc/amp/privateforum/classroom/' + classroomID + '/complete';
        return await Backend.prepareRequest(url).post({ entryID: parseInt(entryID) }).fetchAsJson();
    }

    function updateUI(entryID, data) {
        const sidebarItem = document.querySelector('.pfLessonItem[data-entry-id="' + entryID + '"]');
        if (sidebarItem) {
            sidebarItem.classList.toggle('pfLessonCompleted', data.isCompleted);
        }

        const markDoneBtn = document.querySelector('.pfMarkDoneBtn[data-entry-id="' + entryID + '"]');
        if (markDoneBtn) {
            markDoneBtn.classList.toggle('pfMarkDoneCompleted', data.isCompleted);
            markDoneBtn.querySelector('span').textContent = data.isCompleted
                ? '{lang}wcf.privateforum.classroom.completed{/lang}'
                : '{lang}wcf.privateforum.classroom.markDone{/lang}';
        }

        if (data.progress) {
            const progressFill = document.querySelector('.pfProgressFill');
            const progressLabel = document.querySelector('.pfProgressLabel');
            if (progressFill) progressFill.style.width = data.progress.percentage + '%';
            if (progressLabel) progressLabel.textContent = data.progress.completed + '/' + data.progress.total + ' (' + data.progress.percentage + '%)';
        }
    }

    document.querySelectorAll('.pfMarkDoneBtn').forEach(button => {
        button.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                const data = await toggleComplete(button.dataset.classroomId, button.dataset.entryId);
                if (data) {
                    UiNotification.show();
                    updateUI(button.dataset.entryId, data);
                }
            } catch (e) {}
        });
    });

    document.querySelectorAll('.pfLessonCheck').forEach(check => {
        check.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            try {
                const data = await toggleComplete('{$classroom->classroomID}', check.dataset.entryId);
                if (data) {
                    UiNotification.show();
                    updateUI(check.dataset.entryId, data);
                }
            } catch (e) {}
        });
    });
});
</script>

{include file='footer'}

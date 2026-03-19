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
    {/if}

    {* Modul-Verwaltung (nur Owner) *}
    {if $isOwner && $availableCategories|count}
        <section class="section" style="margin-top: 30px;">
            <h2 class="sectionTitle">{lang}wcf.privateforum.classroom.manageModules{/lang}</h2>
            <p class="sectionDescription">{lang}wcf.privateforum.classroom.manageModules.description{/lang}</p>

            <form id="assignModulesForm">
                {foreach from=$availableCategories item=cat}
                    <dl>
                        <dt></dt>
                        <dd>
                            <label>
                                <input type="checkbox" name="categoryIDs[]" value="{$cat[categoryID]}"{if $cat[isAssigned]} checked{/if}>
                                {$cat[title]}
                            </label>
                        </dd>
                    </dl>
                {/foreach}
                <div class="formSubmit">
                    <input type="submit" value="{lang}wcf.privateforum.classroom.saveModules{/lang}" accesskey="s">
                </div>
            </form>
        </section>
    {elseif $isOwner}
        <woltlab-core-notice type="warning" style="margin-top: 20px;">{lang}wcf.privateforum.classroom.noDatabase{/lang}</woltlab-core-notice>
    {/if}
{else}
    <woltlab-core-notice type="info">{lang}wcf.privateforum.classroom.noClassroom{/lang}</woltlab-core-notice>
{/if}

{if $isOwner && $classroom}
<script data-relocate="true">
require(['WoltLabSuite/Core/Ajax/Backend', 'WoltLabSuite/Core/Ui/Notification'], (Backend, UiNotification) => {
    document.getElementById('assignModulesForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const checkboxes = e.target.querySelectorAll('input[name="categoryIDs[]"]:checked');
        const categoryIDs = Array.from(checkboxes).map(cb => parseInt(cb.value));

        try {
            const url = '{$__wcf->getPath()}index.php?api/rpc/amp/privateforum/classroom/{$classroom->classroomID}/modules';
            await Backend.prepareRequest(url).post({ categoryIDs: categoryIDs }).fetchAsJson();
            UiNotification.show('{lang}wcf.privateforum.classroom.modulesSaved{/lang}');
            setTimeout(() => window.location.reload(), 1000);
        } catch (err) {
            console.error('AssignModule error:', err);
        }
    });
});
</script>
{/if}

{include file='footer'}

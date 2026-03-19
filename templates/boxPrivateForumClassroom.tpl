<link rel="stylesheet" href="{$__wcf->getPath()}style/privateForumClassroom.css?t={LAST_UPDATE_TIME}">

{* Gesamtfortschritt *}
<div class="pfClassroomProgress" style="margin-bottom: 12px;">
    <div class="pfProgressBar">
        <div class="pfProgressFill" style="width: {$progress[percentage]}%"></div>
    </div>
    <span class="pfProgressLabel">{$progress[percentage]}%</span>
</div>

{* Modul-Links *}
<ul style="list-style: none; margin: 0; padding: 0;">
    {foreach from=$modules item=module}
        <li style="margin-bottom: 8px;">
            <a href="{link controller='PrivateForumClassroomModule' id=$forum->privateforumID}categoryID={$module[categoryID]}{/link}" style="display: flex; align-items: center; gap: 8px; text-decoration: none; color: inherit; padding: 6px 0;">
                {if $module[percentage] == 100}
                    {icon name='circle-check' size=16}
                {else}
                    {icon name='book-open' size=16}
                {/if}
                <span style="flex: 1;">{$module[title]}</span>
                <small style="color: var(--wcfContentDimmedText);">{$module[completedCount]}/{$module[totalCount]}</small>
            </a>
        </li>
    {/foreach}
</ul>

<div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--wcfContainerBorderColor);">
    <a href="{link controller='PrivateForumClassroom' id=$forum->privateforumID}{/link}" class="button small" style="width: 100%; text-align: center;">
        {icon name='graduation-cap'} {lang}wcf.privateforum.classroom.overview{/lang}
    </a>
</div>

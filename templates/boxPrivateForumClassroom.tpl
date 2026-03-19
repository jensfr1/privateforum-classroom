{if $pfClassroom && $pfClassroom->isActive && $pfClassroomModules|count}
    <h3 style="margin: 0 0 10px 0; font-size: 1rem; font-weight: 700;">{lang}wcf.privateforum.classroom.title{/lang}</h3>
    <ul style="list-style: none; margin: 0; padding: 0;">
        {foreach from=$pfClassroomModules item=module}
            <li style="margin-bottom: 6px;">
                <a href="{link controller='PrivateForumClassroomModule' id=$pfForum->privateforumID}categoryID={$module[categoryID]}{/link}" style="display: flex; align-items: center; gap: 8px; text-decoration: none; color: inherit; padding: 6px 0;">
                    {icon name='book-open' size=16}
                    <span>{$module[title]}</span>
                    <small style="margin-left: auto; color: var(--wcfContentDimmedText);">{$module[percentage]}%</small>
                </a>
            </li>
        {/foreach}
    </ul>
    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--wcfContainerBorderColor);">
        <a href="{link controller='PrivateForumClassroom' id=$pfForum->privateforumID}{/link}" class="button small">
            {lang}wcf.privateforum.classroom.overview{/lang}
        </a>
    </div>
{/if}

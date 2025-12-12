<!-- KeyManager Accordion -->
{if $showKeyManagerOnProfile}
    <div class="accordion-item">
        <h2 class="accordion-header" id="adm_profile_keymanager_accordion_heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_profile_keymanager_accordion" aria-expanded="false" aria-controls="adm_profile_keymanager_accordion">
                {$l10n->get('PLG_KEYMANAGER_NAME')}
            </button>
        </h2>
        <div id="adm_profile_keymanager_accordion" class="accordion-collapse collapse" aria-labelledby="adm_profile_keymanager_accordion_heading" data-bs-parent="#adm_profile_accordion">
            <div class="accordion-body">
                <a class="admidio-icon-link float-end" href="{$urlKeyManagerFiles}">
                    <i class="bi bi-key-fill" title="{$l10n->get('PLG_KEYMANAGER_SWITCH_TO_PLUGIN_KEYMANAGER')}"></i>
                </a>        
                <table id="adm_keymanager_table" class="table table-hover" width="100%" style="width: 100%;">
                    <tbody>
                        {foreach $keymanagerTemplateData as $row}
                            <tr id="row_{$row.id}">
                                <td style="word-break: break-word;"><a href="{$row.url}">{$row.name}</a></td>
                                <td class="text-end">
                                    {$row.received_on}
                                    {include 'sys-template-parts/list.functions.tpl' data=$row}    
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
{/if}

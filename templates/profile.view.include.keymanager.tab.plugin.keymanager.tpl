<!-- KeyManager Tab -->
{if $showKeyManagerOnProfile}
    <div class="tab-pane fade" id="adm_profile_keymanager_pane" role="tabpanel" aria-labelledby="adm_profile_keymanager_tab">
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
{/if}

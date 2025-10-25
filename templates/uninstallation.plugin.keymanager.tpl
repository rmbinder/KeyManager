<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

   <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('PLG_KEYMANAGER_ACCESS_ROLE')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['uninst_access_role']}  
            {include 'sys-template-parts/form.radio.tpl' data=$elements['uninst_access_role_org_select']}
        </div>
    </div>
    
        <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('PLG_KEYMANAGER_MENU_ITEM')}</div>
        <div class="card-body">   
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['uninst_menu_item']}
       </div>          
    </div>
    
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('PLG_KEYMANAGER_CONFIG_DATA')}</div>
        <div class="card-body"> 
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['uninst_config_data']}  
            {include 'sys-template-parts/form.radio.tpl' data=$elements['uninst_config_data_org_select']} 
       </div>         
    </div>
    
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_uninstallation']} 
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>

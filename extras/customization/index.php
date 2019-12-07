<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability to add and remove      *
 * fields in SimpleRisk and change the layout of the various        *
 * pages.                                                           *
 ********************************************************************/

// Extra Version
define('CUSTOMIZATION_EXTRA_VERSION', '20191130-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/upgrade.php'));

// Check if customization extra is enabled
if(customization_extra()){
    // Upgrade extra database version
    upgrade_customization_extra_database();
}

/****************************************
 * FUNCTION: ENABLE CUSTOMIZATION EXTRA *
 ****************************************/
function enable_customization_extra()
{
    prevent_extra_double_submit("customization", true);

    // Open the database connection
    $db = db_open();

    // Enable the governance extra
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'customization', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'; ");
    $stmt->execute();

    // Delete all invalid custom and risk data
    clean_dirty_custom_risk_asset_data();

    // This code is required to make sure the Jira extra's field is
    // hidden when the extra is deactivated and shown when it is activated
    // Even if this (de)activation happened when the Customization extra itself was deactivated
    if (is_extra_installed('jira') && table_exists('custom_fields') && table_exists('custom_template')) {
        // Include the jira extra
        require_once(realpath(__DIR__ . '/../jira/index.php'));
        //If the extra is activated
        if (jira_extra()) {
            add_jira_issue_key_field_to_customization();
        } else {
            remove_jira_issue_key_field_from_customization();
        }
    }

    // Audit log entry for Extra turned on
    $message = "Customization Extra was toggled on by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);
}

/*****************************************
 * FUNCTION: DISABLE CUSTOMIZATION EXTRA *
 *****************************************/
function disable_customization_extra()
{
    prevent_extra_double_submit("customization", false);

    // Open the database connection
    $db = db_open();

    // Disable the governance extra
    $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'customization'; ");
    $stmt->execute();
    
    // Audit log entry for Extra turned off
    $message = "Customization Extra was toggled off by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);
}

/***********************************
 * FUNCTION: CUSTOMIZATION VERSION *
 ***********************************/
function customization_version()
{
    // Return the version
    return CUSTOMIZATION_EXTRA_VERSION;
}

/***********************************
 * FUNCTION: DISPLAY CUSTOMIZATION *
 ***********************************/
function display_customization()
{
    global $escaper, $lang;
    
    $fgroup = get_param("GET", "fgroup", "risk");
    
    echo "<div class=\"hero-unit\">";
    echo "<h4>".$escaper->escapeHtml($lang['CustomizationExtra'])."</h4>";
    echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . customization_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";
    
    echo "<form name=\"field_create_form\" method=\"post\" action=\"\">\n";
    echo "<br>\n";
    echo "      <div>\n";
    echo "        <span width=\"100px\">" . $escaper->escapeHtml($lang['FieldGroup']) . ":&nbsp;&nbsp;</span>\n";
    echo "        <span>
                    <select id=\"fgroup\" name=\"fgroup\">
                        <option ". ($fgroup == "risk" ? " selected " : "") ." value=\"risk\">".$escaper->escapeHtml($lang['Risk'])."</option>
                        <option ". ($fgroup == "asset" ? " selected " : "") ." value=\"asset\">".$escaper->escapeHtml($lang['Asset'])."</option>
                    </select>
                  </span>\n";
    echo "      </div>\n";
    echo "<br>\n";
    echo "<table border=\"1\" width=\"600\" cellpadding=\"10px\">\n";
    echo "  <tbody>\n";
    echo "  <tr><td>\n";
    echo "    <table border=\"0\" width=\"100%\">\n";
    echo "      <tbody>\n";
    echo "      <tr>\n";
    echo "        <td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['AddACustomField']) . "</strong></u></td>\n";
    echo "      </tr>\n";
    echo "      <tr>\n";
    echo "        <td width=\"100px\">" . $escaper->escapeHtml($lang['FieldName']) . ":</td>\n";
    echo "        <td><input required name=\"name\" type=\"text\" maxlength=\"100\" size=\"20\" /></td>\n";
    echo "      </tr>\n";
    echo "      <tr>\n";
    echo "        <td width=\"100px\">" . $escaper->escapeHtml($lang['Required']) . ":</td>\n";
    echo "        <td>\n";
    echo "          <input type=\"checkbox\" name=\"required\" id=\"field_required\"> <label for=\"field_required\"></label> ";
    echo "        </td>\n";
    echo "      </tr>\n";
    echo "      <tr>\n";
    echo "        <td width=\"100px\">" . $escaper->escapeHtml($lang['FieldType']) . ":</td>\n";
    echo "        <td>\n";
    display_field_type_dropdown();
    echo "        </td>\n";
    echo "      </tr>\n";

    if(encryption_extra())
    {
        // Load the extra
        echo "      <tr style='display: none'>\n";
        echo "        <td width=\"100px\">" . $escaper->escapeHtml($lang['Encryption']) . ":</td>\n";
        echo "        <td>\n";
        echo "          <input type=\"checkbox\" name=\"encryption\" id=\"field_encryption\"> <label for=\"field_encryption\"></label> ";
        echo "          <input type='hidden' id='lang_encrypted' \> ";
        echo "        </td>\n";
        echo "      </tr>\n";
    }
    
    echo "      <tr><td>&nbsp;</td><td>&nbsp;</td></tr>\n";
    echo "      <tr>\n";
    echo "        <td><input type=\"submit\" value=\"" . $escaper->escapeHtml($lang['Add']) . "\" name=\"create_field\" /></td>\n";
    echo "        <td>&nbsp;</td>\n";
    echo "      </tr>\n";
    echo "      </tbody>\n";
    echo "    </table>\n";
    echo "  </td></tr>\n";
    echo "  </tbody>\n";
    echo "</table>\n";
    echo "</form>\n";

    echo "<form name=\"field_delete_form\" method=\"post\" action=\"\">\n";
        echo "<table border=\"1\" width=\"600\" cellpadding=\"10px\">\n";
        echo "  <tbody>\n";
        echo "  <tr><td>\n";
        echo "    <table border=\"0\" width=\"100%\">\n";
        echo "      <tbody>\n";
        echo "      <tr>\n";
        echo "        <td colspan=\"3\"><u><strong>" . $escaper->escapeHtml($lang['CustomFields']) . "</strong></u></td>\n";
        echo "      </tr>\n";
        echo "      <tr>\n";
        echo "        <td width=\"100px\">" . $escaper->escapeHtml($lang['FieldName']) . ":</td>\n";
        echo "        <td width='200px'>\n";
        display_field_name_dropdown($fgroup);
        echo "        </td>\n";
        echo "        <td align='center' valign='top'><a data-id=\"2\" onclick=\"confirm_delete_field();\" class=\"delete_field btn\">".$lang['Delete']."</a> &nbsp;&nbsp;<a id='update_field_btn' class=\"btn\">".$lang['Update']."</a></td>\n";
        echo "      </tr>\n";
        echo "      <tr class=\"field_sample\" style=\"display: none;\">\n";
        echo "        <td width=\"100px\">" . $escaper->escapeHtml($lang['FieldSample']) . ":</td>\n";
        echo "        <td id=\"field_sample_content\">\n";
        echo "            &nbsp;";
        echo "        </td>\n";
        echo "        <td align='center' valign='top'>\n";
        echo "            <a class='btn action-content delete_option' style=\"display: none;\">".$lang['Delete']."</button>";
        echo "        </td>\n";
        echo "      </tr>\n";
        echo "      <tr class=\"action-content\" style=\"display: none;\">\n";
        echo "        <td width=\"100px\">&nbsp;</td>\n";
        echo "        <td >\n";
        echo "            <input type='text' id='option_name' name='name' placeholder='".$escaper->escapeHtml($lang['OptionName'])."'>";
        echo "        </td>\n";
        echo "        <td align='center' valign='top'>\n";
        echo "            <a class='btn add_option' >".$lang['Add']."</a>";
        echo "        </td>\n";
        echo "      </tr>\n";
        echo "      </tbody>\n";
        echo "    </table>\n";
        echo "  </td></tr>\n";
        echo "  </tbody>\n";
        echo "</table>\n";
    echo "</form>\n";

    echo "
        <!-- MODEL WINDOW FOR ADDING FRAMEWORK -->
        <div id=\"update-custom-field-modal\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
            <div class=\"modal-header\">
                <button type=\"button\" class=\"close\" data-dismiss=\"modal\">&times;</button>
                <h4 class=\"modal-title\">".$escaper->escapeHtml($lang['UpdateCustomField'])."</h4>
            </div>
          <div class=\"modal-body\">
            <form class=\"\" action=\"#\" method=\"post\" autocomplete=\"off\">
                <table width='100%'>
                    <tr>
                        <td><label for='update-field-name'>".$escaper->escapeHtml($lang['FieldName']).":</label></td>
                        <td>
                            <input type=\"text\" id='update-field-name' required name=\"name\" value=\"\" class=\"form-control\" autocomplete=\"off\">
                            <input type='hidden' id='update-field-id' name='id' value=''>
                        </td>
                    </tr>
                    <tr>
                        <td><label for='update-required'>".$escaper->escapeHtml($lang['Required']).":</label></td>
                        <td><input id='update-required' type=\"checkbox\" name=\"required\" class=\"form-control\" value='1'></td>
                    </tr>";
                    if(encryption_extra())
                    {
                        echo "<tr style='display: none'>
                            <td><label for='update-encryption'>".$escaper->escapeHtml($lang['Encryption']).":</label></td>
                            <td><input id='update-encryption' type=\"checkbox\" name=\"encryption\" class=\"form-control\" value='1'></td>
                        </tr>";
                    }
                    
                    echo "<tr>
                        <td colspan='2'>
                          <div class=\"form-group text-right\">
                            <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                            <button type=\"submit\" name=\"update-custom-field\" class=\"btn btn-danger\">".$escaper->escapeHtml($lang['Update'])."</button>
                          </div>

                        </td>
                    </tr>
                </table>
            </form>

          </div>
        </div>
        
    ";
    
    echo "
        <script>
            $(document).ready(function(){
                $('#fgroup').change(function(){
                    var fgroup = $(this).val();
                    document.location.href = '".$_SESSION['base_url']. "/admin/customization.php?fgroup=" ."' + fgroup;
                });
                ";
                if(encryption_extra())
                {
                    echo "
                    $('#type').change(function(){
                        if($(this).val() == 'shorttext' || $(this).val() == 'longtext'){
                            $('#field_encryption').closest('tr').show()
                        }else{
                            $('#field_encryption').closest('tr').hide()
                        }
                    });";
                }
                
                echo "$('.delete_option').click(function(){
                    var form = $(this).parents('form');
                    var field = $('select[name=custom_field_name]', form).val();
                    var value = $('#field_sample_content select', form).val();

                    $.ajax({
                        url: BASE_URL + \"/api/customization/deleteOption\",
                        type: \"POST\",
                        data: {
                            field: field,
                            value: value,
                        }, 
                        success: function(result){
                            getField();
                            showAlertsFromArray(result.status_message);
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);                                
                                }
                            }
                        }
                    });
                })
                $('#update_field_btn').click(function(){
                    var formData = \"field_id=\"+$('#custom_field_name').val();
                    $.ajax({
                        url: BASE_URL + \"/api/admin/fields/get\",
                        type: \"GET\",
                        data: formData, 
                        success: function(result){
                            var name = result.data.name;
                            var type = result.data.type;
                            var required = result.data.required == '1' ? 1 : 0;
                            
                            $('#update-field-name').val(name);
                            $('#update-field-id').val($('#custom_field_name').val());
                            $('#update-required').prop('checked', required);";

                            if(encryption_extra())
                            {
                                echo "
                                var encryption = result.data.encryption == '1' ? 1 : 0;
                                $('#update-encryption').prop('checked', encryption);
                                if(type == 'shorttext' || type=='longtext')
                                {
                                    $('#update-encryption').closest('tr').show();
                                }
                                else
                                {
                                    $('#update-encryption').closest('tr').hide();
                                }";
                            }
                            
                            echo "
                            $('#update-custom-field-modal').modal();
                        }
                    });
                

                })
                $('.add_option').click(function(){
                    var form = $(this).parents('form');
                    var field = $('select[name=custom_field_name]', form).val();
                    var name = $('input[name=name]', form).val();
                    var value = $('#field_sample_content select', form).val();

                    $.ajax({
                        url: BASE_URL + \"/api/customization/addOption\",
                        type: \"POST\",
                        data: {
                            field: field,
                            name: name,
                        }, 
                        success: function(result){
                            getField();
                            $('#option_name').val('');
                            showAlertsFromArray(result.status_message);                        
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);                                
                                }
                            }
                        }
                    });
                })
            });
        </script>
    ";
    
    // If common refresh, shows default field
    if(empty($_SESSION['custom_field_id']))
    {
        echo "
            <script>
                $(document).ready(function(){
                    getField();
                })
            </script>
        ";
    }
    // If a new field was created, shows new field
    else
    {
        echo "
            <script>
                $(document).ready(function(){
                    $('#custom_field_name').val({$_SESSION['custom_field_id']});
                    getField();
                })
            </script>
        ";
    }
    
    // Remove custom_field_id session so this works only for creating a new field.
    unset($_SESSION['custom_field_id']);
    
    echo "</div>";
    
    /********** Start template container ********/
    echo "<div id='template-container' class=\"risk-details hero-unit\">";
        echo "<h4>".$escaper->escapeHtml($lang['Template'])."</h4>";
        
    if($fgroup == "risk")
    {
        echo "
                <div class='row-fluid'>
                    <div class='span6'>
                        <table width='100%'>
                            <tr>
                                <td align='right'>
                                    <label>".$escaper->escapeHtml($lang['MainField']).":&nbsp;&nbsp;&nbsp;</label>
                                </td>
                                <td >"; 
                                    // Details
                                    display_main_fields_dropdown($fgroup, 1, true);
                                    // Mitigation
                                    display_main_fields_dropdown($fgroup, 2);
                                    // Review
                                    display_main_fields_dropdown($fgroup, 3);
                                echo "</td>
                                <td valign='top'>
                                    <button id='add_main_field'>".$escaper->escapeHtml($lang['AddMainField'])."</button>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class='span6'>
                        <table width='100%'>
                            <tr>
                                <td align='right'>
                                    <label>".$escaper->escapeHtml($lang['CustomField']).":&nbsp;&nbsp;&nbsp;</label>
                                </td>
                                <td >";
                                    display_custom_fields_dropdown($fgroup);
                                echo "</td>
                                <td valign='top'>
                                    <button id='add_custom_field'>".$escaper->escapeHtml($lang['AddCustomField'])."</button>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>";
                
                echo "
                <div id=\"tabs\">
                  <ul class='tabs-nav clearfix'>
                    <li><a href=\"#tabs1\">".$escaper->escapeHtml($lang['Details'])."</a></li>
                    <li><a href=\"#tabs2\">".$escaper->escapeHtml($lang['Mitigation'])."</a></li>
                    <li><a class=\"tabList\" href=\"#tabs3\">".$escaper->escapeHtml($lang['Review'])."</a></li>
                  </ul>
                  <div id=\"tabs1\">
                      <div class='row-fluid'>
                          <div class='span6'>
                              <ul class='left-panel sort-list connectedSortable'>
                                  ";
                                  display_admin_field_list($fgroup, "left", 1);
                              echo "</ul>
                          </div>
                          <div class='span6'>
                              <ul class='right-panel sort-list connectedSortable'>
                                  ";
                                  display_admin_field_list($fgroup, "right", 1);
                              echo "</ul>
                          </div>
                      </div>
                      <ul class='bottom-panel sort-list connectedSortable'>
                          ";
                          display_admin_field_list($fgroup, "bottom", 1);
                      echo "</ul>
                  </div>
                  <div id=\"tabs2\">
                      <div class='row-fluid'>
                          <div class='span6'>
                              <ul class='left-panel sort-list connectedSortable'>
                                  ";
                                  display_admin_field_list($fgroup, "left", 2);
                              echo "</ul>
                          </div>
                          <div class='span6'>
                              <ul class='right-panel sort-list connectedSortable'>
                                  ";
                                  display_admin_field_list($fgroup, "right", 2);
                              echo "</ul>
                          </div>
                      </div>
                      <ul class='bottom-panel sort-list connectedSortable'>
                          ";
                          display_admin_field_list($fgroup, "bottom", 2);
                      echo "</ul>
                  </div>
                  <div id=\"tabs3\">
                      <div class='row-fluid'>
                          <div class='span6'>
                              <ul class='left-panel sort-list connectedSortable'>
                                  ";
                                  display_admin_field_list($fgroup, "left", 3);
                              echo "</ul>
                          </div>
                          <div class='span6'>
                              <ul class='right-panel sort-list connectedSortable'>
                                  ";
                                  display_admin_field_list($fgroup, "right", 3);
                              echo "</ul>
                          </div>
                      </div>
                      <ul class='bottom-panel sort-list connectedSortable'>
                          ";
                          display_admin_field_list($fgroup, "bottom", 3);
                      echo "</ul>
                  </div>
                </div>
            <script>
                $( function() {
                  $( \".sort-list\" ).sortable({
                      connectWith: '.connectedSortable'
                  });
                  $( \".sort-list\" ).disableSelection();
                  
                } );
              
                function getTabIndex(){
                    var selectedTab = $(\"#tabs\").tabs('option','active');
                    var tabIndex = selectedTab + 1;
                    return tabIndex;
                }
              
                $(document).ready(function(){
                    
                    $('#add_main_field').click(function(){
                        var tabIndex = getTabIndex();
                        var mainSelector = $('#main_field_'+tabIndex);
                        if(mainSelector.val()){
                            var leftPanel = $('.left-panel', '#tabs'+tabIndex);
                            
                            var optionText = $('option:selected', mainSelector).text();
                            var optionType = $('option:selected', mainSelector).data('type');
                            var optionValue = $('option:selected', mainSelector).val();
                            
                            leftPanel.append('<li class=\"field-holder\" data-main=\"1\" data-text=\"'+optionText+'\" data-type=\"'+optionType+'\" data-value=\"'+optionValue+'\">'+optionText+'<span class=\"delete\"><i class=\"fa fa-trash\"></i></span></li>')
                            leftPanel.sortable('refresh');
                            
                            $('option:selected', mainSelector).remove()
                        }
                    })
                    
                    $('#add_custom_field').click(function(){
                        var tabIndex = getTabIndex();
                        var customSelector = $('#custom_fields');
                        if(customSelector.val()){
                            var leftPanel = $('.left-panel', '#tabs'+tabIndex);
                            
                            var optionText = $('option:selected', customSelector).text();
                            var optionType = $('option:selected', customSelector).data('type');
                            var optionValue = $('option:selected', customSelector).val();
                            
                            leftPanel.append('<li class=\"field-holder\" data-main=\"0\" data-text=\"'+optionText+'\" data-type=\"'+optionType+'\" data-value=\"'+optionValue+'\">'+optionText+'<span class=\"delete\"><i class=\"fa fa-trash\"></i></span></li>')
                            leftPanel.sortable('refresh');
                            
                            $('option:selected', customSelector).remove()
                        }
                    })
                    
                    $('#template-container').on('click', '.field-holder .delete', function(){
                        var optionText = $(this).parent().data('text');
                        var optionType = $(this).parent().data('type');
                        var optionValue = $(this).parent().data('value');
                        var isMain = $(this).parent().data('main');
                        
                        if(isMain){
                            var tabIndex = getTabIndex();
                            var mainSelector = $('#main_field_'+tabIndex);
                            mainSelector.append('<option data-type=\"'+optionType+'\" value=\"'+optionValue+'\">'+optionText+'</option>')
                        }else{
                            var customSelector = $('#custom_fields');
                            customSelector.append('<option data-type=\"'+optionType+'\" value=\"'+optionValue+'\">'+optionText+'</option>')
                        }

                        $(this).parent().remove();
                    })
                    
                })
                
                $( function() {
                    $( \"#tabs\" ).tabs({
                      activate: function(event, ui){
                          var tabIndex = getTabIndex();
                          $('.main-fields').hide();
                          $('#main_field_'+tabIndex).show();
                      }
                    });
                } );
            </script>
        ";
    }
    elseif($fgroup == "asset")
    {
        echo "
                <div class='row-fluid'>
                    <div class='span6'>
                        <table width='100%'>
                            <tr>
                                <td align='right'>
                                    <label>".$escaper->escapeHtml($lang['MainField']).":&nbsp;&nbsp;&nbsp;</label>
                                </td>
                                <td >"; 
                                    // Details
                                    display_main_fields_dropdown($fgroup, 1, true);
                                echo "</td>
                                <td valign='top'>
                                    <button id='add_main_field'>".$escaper->escapeHtml($lang['AddMainField'])."</button>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class='span6'>
                        <table width='100%'>
                            <tr>
                                <td align='right'>
                                    <label>".$escaper->escapeHtml($lang['CustomField']).":&nbsp;&nbsp;&nbsp;</label>
                                </td>
                                <td >";
                                    display_custom_fields_dropdown($fgroup);
                                echo "</td>
                                <td valign='top'>
                                    <button id='add_custom_field'>".$escaper->escapeHtml($lang['AddCustomField'])."</button>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>";
                
                echo "
                  <div id=\"tabs1\" style=\"margin-top: 20px;\">
                      <div class='row-fluid'>
                          <div class='span12'>
                              <ul class='left-panel sort-list connectedSortable'>
                                  ";
                                  display_admin_field_list($fgroup, "left", 1);
                              echo "</ul>
                          </div>
                      </div>
                  </div>
                <script>
                    $( function() {
                      $( \".sort-list\" ).sortable({
                          connectWith: '.connectedSortable'
                      });
                      $( \".sort-list\" ).disableSelection();
                      
                    } );
                  
                    $(document).ready(function(){
                        
                        $('#add_main_field').click(function(){
                            var mainSelector = $('#main_field_1');
                            if(mainSelector.val()){
                                var leftPanel = $('.left-panel', '#tabs1');
                                
                                var optionText = $('option:selected', mainSelector).text();
                                var optionType = $('option:selected', mainSelector).data('type');
                                var optionValue = $('option:selected', mainSelector).val();
                                
                                leftPanel.append('<li class=\"field-holder\" data-main=\"1\" data-text=\"'+optionText+'\" data-type=\"'+optionType+'\" data-value=\"'+optionValue+'\">'+optionText+'<span class=\"delete\"><i class=\"fa fa-trash\"></i></span></li>')
                                leftPanel.sortable('refresh');
                                
                                $('option:selected', mainSelector).remove()
                            }
                        })
                        
                        $('#add_custom_field').click(function(){
                            var customSelector = $('#custom_fields');
                            if(customSelector.val()){
                                var leftPanel = $('.left-panel', '#tabs1');
                                
                                var optionText = $('option:selected', customSelector).text();
                                var optionType = $('option:selected', customSelector).data('type');
                                var optionValue = $('option:selected', customSelector).val();
                                
                                leftPanel.append('<li class=\"field-holder\" data-main=\"0\" data-text=\"'+optionText+'\" data-type=\"'+optionType+'\" data-value=\"'+optionValue+'\">'+optionText+'<span class=\"delete\"><i class=\"fa fa-trash\"></i></span></li>')
                                leftPanel.sortable('refresh');
                                
                                $('option:selected', customSelector).remove()
                            }
                        })
                        
                        $('#template-container').on('click', '.field-holder .delete', function(){
                            var optionText = $(this).parent().data('text');
                            var optionType = $(this).parent().data('type');
                            var optionValue = $(this).parent().data('value');
                            var isMain = $(this).parent().data('main');
                            
                            if(isMain){
                                var mainSelector = $('#main_field_1');
                                mainSelector.append('<option data-type=\"'+optionType+'\" value=\"'+optionValue+'\">'+optionText+'</option>')
                            }else{
                                var customSelector = $('#custom_fields');
                                customSelector.append('<option data-type=\"'+optionType+'\" value=\"'+optionValue+'\">'+optionText+'</option>')
                            }

                            $(this).parent().remove();
                        })
                        
                    })
                    
                </script>
        ";
    }
    
        echo "
            <div class='row-fluid'>
                <button id='save_template' class=\"pull-right\">".$escaper->escapeHtml($lang['Save'])."</button>
                <form method=\"POST\" class=\"pull-right\">
                    <input type='hidden' name='fgroup' value='". $escaper->escapeHtml($fgroup) ."' >
                    <button id='restore' name='restore'>".$escaper->escapeHtml($lang['Restore'])."</button> &nbsp;&nbsp;&nbsp;
                </form>
            </div>
        ";
        
        echo "
            <script>
                $('#save_template').click(function(){
                    var data = [];
                    for(var i=1; i<=3; i++){
                        var panel = $('#tabs'+i);
                        var panel_data = {left:[], right:[], bottom:[]};
                        $('.left-panel li', panel).each(function(index){
                            panel_data.left.push($(this).data('value'));
                        })
                        $('.right-panel li', panel).each(function(index){
                            panel_data.right.push($(this).data('value'));
                        })
                        $('.bottom-panel li', panel).each(function(index){
                            panel_data.bottom.push($(this).data('value'));
                        })
                        panel_data.tab_index = i;
                        data.push(panel_data)
                    }
                    
                    $.ajax({
                        url: BASE_URL + \"/api/customization/saveTemplate?fgroup=\" + $('#fgroup').val(),
                        type: \"POST\",
                        data: {
                            template: data
                        },
                        success: function(result){
                            showAlertsFromArray(result.status_message);
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)){
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    
                })
            
            </script>
        ";
        
    echo "</div>";
    /********** End template container ********/
        
}

/**************************************
 * FUNCTION: DISPLAY ADMIN FIELD LIST *
 **************************************/
function display_admin_field_list($fgroup, $panel_name, $tab_index)
{
    global $escaper, $lang;
    
    // Get actvie fields
    $fields = get_active_fields($fgroup);
    
    foreach($fields as $field)
    {
        if($field['panel_name'] == $panel_name && $field['tab_index'] == $tab_index)
        {
            // Check if this is required field
            if($field['required'])
            {
                $required_html = "*";
            }
            else
            {
                $required_html = "";
            }
            
            if($field['is_basic'])
            {
                // If field is asset name, remove delete icon
                if($field['name'] == "AssetName")
                {
                    echo "<li class=\"field-holder\" data-main=\"".$field['is_basic']."\" data-text=\"".$escaper->escapeHtml($lang[$field['name']]).$required_html."\" data-type=\"".$escaper->escapeHtml($field['type'])."\" data-value=\"".$field['id']."\">".$escaper->escapeHtml($lang[$field['name']]).$required_html."</li>";
                }
                else
                {
                    echo "<li class=\"field-holder\" data-main=\"".$field['is_basic']."\" data-text=\"".$escaper->escapeHtml($lang[$field['name']]).$required_html."\" data-type=\"".$escaper->escapeHtml($field['type'])."\" data-value=\"".$field['id']."\">".$escaper->escapeHtml($lang[$field['name']]).$required_html."<span class=\"delete\"><i class=\"fa fa-trash\"></i></span></li>";
                }
            }
            else
            {
                echo "<li class=\"field-holder\" data-main=\"".$field['is_basic']."\" data-text=\"".$escaper->escapeHtml($field['name']).$required_html."\" data-type=\"".$escaper->escapeHtml($field['type'])."\" data-value=\"".$field['id']."\">".$escaper->escapeHtml($field['name']).$required_html."<span class=\"delete\"><i class=\"fa fa-trash\"></i></span></li>";
            }
        }
    }
}

/*****************************
 * FUNCTION: GET FIELD TYPES *
 *****************************/
function get_field_types()
{
    global $lang;
    
    $fields = array( 
        $lang['Dropdown'] => "dropdown", 
        $lang['MultiDropdown'] => "multidropdown", 
        $lang['ShortText'] => "shorttext", 
        $lang['LongText'] => "longtext", 
        $lang['DateSelector'] => "date",
        $lang['UserMultiDropdown'] => "user_multidropdown"
    );

    return $fields;
}

/*****************************
 * FUNCTION: GET FIELD NAMES *
 *****************************/
function get_custom_fields($fgroup="risk", $is_basic=0, $tab_index=0)
{
    // Open the database connection
    $db = db_open();

    // Get custom field names
    $stmt = $db->prepare("SELECT * FROM `custom_fields` WHERE is_basic=:is_basic AND tab_index=:tab_index AND fgroup=:fgroup ORDER by name; ");
    $stmt->bindParam(":fgroup", $fgroup, PDO::PARAM_STR);
    $stmt->bindParam(":is_basic", $is_basic, PDO::PARAM_INT);
    $stmt->bindParam(":tab_index", $tab_index, PDO::PARAM_INT);
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Return the fields array
    return $fields;
}

/*******************************
 * FUNCTION: GET ACTIVE FIELDS *
 *******************************/
function get_active_fields($fgroup="risk")
{
    // Open the database connection
    $db = db_open();
    // Get custom field names
    $stmt = $db->prepare("SELECT t1.id, t1.name, t1.type, t1.is_basic, t1.required, t1.encryption, t2.tab_index, t2.ordering, t2.panel_name, 1 active FROM `custom_fields` t1 INNER JOIN `custom_template` t2 ON t1.id=t2.custom_field_id WHERE t1.fgroup=:fgroup ORDER BY t2.ordering; ");
    $stmt->bindParam(":fgroup", $fgroup, PDO::PARAM_STR);
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Return the fields array
    return $fields;
}

/*********************************
 * FUNCTION: GET INACTIVE FIELDS *
 *********************************/
function get_inactive_fields($fgroup="risk")
{
    // Open the database connection
    $db = db_open();

    // Get custom field names
    $stmt = $db->prepare("SELECT t1.id, t1.name, t1.type, t1.is_basic, t1.tab_index, t1.required, t1.encryption, '99' ordering, 'left' panel_name, 0 active FROM `custom_fields` t1 LEFT JOIN `custom_template` t2 ON t1.id=t2.custom_field_id
        WHERE t2.id IS NULL AND t1.fgroup=:fgroup
    ; ");
    $stmt->bindParam(":fgroup", $fgroup, PDO::PARAM_STR);
    $stmt->execute();
    $fields = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the fields array
    return $fields;
}

/*****************************
 * FUNCTION: GET FIELD BY ID *
 *****************************/
function get_field_by_id($id)
{
    // Open the database connection
    $db = db_open();

    // Get a custom field
    $stmt = $db->prepare("SELECT `id`, `fgroup`, `name`, `type`, `required`, `encryption` FROM `custom_fields` WHERE id=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $field = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // Return the fields array
    return $field;
}

/*****************************************
 * FUNCTION: DISPLAY FIELD TYPE DROPDOWN *
 *****************************************/
function display_field_type_dropdown()
{
    global $escaper;
    
    echo "<select id=\"type\" name=\"type\" class=\"form-field\" style=\"width:auto;\">\n";

    $types = get_field_types();

    // For each field type
    foreach ($types as $label => $type)
    {
        echo "<option value=\"" . $type . "\">" .  $escaper->escapeHtml($label). "</option>\n";
    }

    echo "</select>\n";
}

/******************************************
 * FUNCTION: DISPLAY MAIN FIELDS DROPDOWN *
 ******************************************/
function display_main_fields_dropdown($fgroup="risk", $tab_index=1, $is_show=false)
{
    global $escaper, $lang;
    
    if($is_show)
        echo "<select id=\"main_field_{$tab_index}\" class=\"main-fields form-field\" style=\"width:auto;\" >\n";
    else
        echo "<select id=\"main_field_{$tab_index}\" class=\"main-fields form-field\" style=\"width:auto; display:none;\" >\n";
        
    // Get inactive fileds 
    $fields = get_inactive_fields($fgroup);
    
    foreach($fields as $field)
    {   
        // Check if this is main field and matched with tab index
        if($field['is_basic'] == 1 && $field['tab_index'] == $tab_index){
            echo "<option data-type='".$escaper->escapeHtml($field['type'])."' value='".$field['id']."'>".$escaper->escapeHtml($lang[$field['name']])."</option>";
        }
    }
    echo "</select>";
}

/******************************************
 * FUNCTION: DISPLAY MAIN FIELDS DROPDOWN *
 ******************************************/
function display_custom_fields_dropdown($fgroup = "risk")
{
    global $escaper;
    
    echo "<select id=\"custom_fields\" class=\"form-field\" style=\"width:auto;\" >\n";
        
    // Get inactive fields
    $fields = get_inactive_fields($fgroup);
    
    foreach($fields as $field)
    {
        // Check if this field is requied
        if($field['required'] == 1)
        {
            $required_html = "*";
        }
        else
        {
            $required_html = "";
        }
        
        // Check if this is custom field
        if($field['is_basic'] == 0){
            echo "<option data-type='".$escaper->escapeHtml($field['type'])."' value='".$field['id']."'>".$escaper->escapeHtml($field['name']).$required_html."</option>";
        }
    }
    echo "</select>";
}

/*****************************************
 * FUNCTION: DISPLAY FIELD NAME DROPDOWN *
 *****************************************/
function display_field_name_dropdown($fgroup = "risk")
{
    global $escaper, $lang;

    echo "<script type=\"text/javascript\">\n";
    echo "
        function confirm_delete_field()
        {
            confirm('". $escaper->escapeHtml($lang['ConfirmDeleteCustomField']) ."', 'delete_field()');
        }
        function delete_field()
        {
            var e = document.getElementById(\"custom_field_name\");
            var field_id = e.options[e.selectedIndex].value;
            var formData = {field_id: field_id};

            $.ajax({
                type: \"POST\",
                url: BASE_URL + \"/api/admin/fields/delete\",
                data: formData,
                success: function(result){
                    $(\"#custom_field_name option[value=\" + field_id + \"]\").remove();
                    getField();
//                    $('.field_sample').hide();
                    showAlertsFromArray(result.status_message);                    
                },
                error: function(xhr, textStatus){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);                        
                        }
                    }
                }
            })
        }
        
        function getField()
        {
            var id = custom_field_name.value;
            if(id)
            {
                var formData = \"field_id=\"+id;
                $.ajax({
                    url: BASE_URL + \"/api/admin/fields/get\",
                    type: \"GET\",
                    data: formData, 
                    success: function(result){
                        $('.field_sample').show();
                        $('#field_sample_content').html(result.data.content_html);
                        if(result.data.type == 'multidropdown' || result.data.type == 'user_multidropdown'){
                            $('#field_sample_content select').multiselect({includeSelectAllOption: true, buttonWidth: '100%'});
                        }
                        if(result.data.type == 'date'){
                            $('#field_sample_content .datepicker').datepicker();
                        }
                        if(result.data.type == 'multidropdown' || result.data.type == 'dropdown'){
                            $('.action-content').show()
                        }else{
                            $('.action-content').hide()
                        }
                    }
                });
            }
            else
            {
                $('.field_sample').hide();
                $('.action-content').hide();
            }
        }
    ";
    echo "</script>\n";

    echo "<select id=\"custom_field_name\" name=\"custom_field_name\" class=\"form-field\" style=\"width:auto;\" onchange=\"getField()\">\n";
//    echo "<option value=\"\">--</option>\n";

    $fields = get_custom_fields($fgroup);

    // For each field
    foreach ($fields as $field)
    {
        // Check if this field is requied.
        if($field['required'] == 1)
        {
            $required_html = "*";
        }
        else{
            $required_html = "";
        }
        
        if(encryption_extra())
        {
            // Check if this field is requied.
            if($field['encryption'] == 1)
            {
                $encrypted_text = " (". $escaper->escapeHtml($lang['Encrypted']) .") ";
            }
            else{
                $encrypted_text = "";
            }
            
            echo "<option value=\"" . $field['id'] . "\">" . $escaper->escapeHtml($field['name']). $required_html . $encrypted_text . "</option>\n";
        }
        else
        {
            echo "<option value=\"" . $field['id'] . "\">" . $escaper->escapeHtml($field['name']). $required_html . "</option>\n";
        }
        
    }

    echo "</select>\n";
}

/**************************
 * FUNCTION: CREATE FIELD *
 **************************/
function create_field($fgroup, $name, $type, $required=0, $encryption=0)
{
    // Check that the specified type is in the array
    if (in_array($type, get_field_types()))
    {
        // If the field name does not already exist
        if (field_exists($name, $fgroup) == -1)
        {
            // Open the database connection
            $db = db_open();

            // Add the field to the fields table
            $stmt = $db->prepare("INSERT INTO `custom_fields` (`fgroup`, `name`, `type`, `is_basic`, `tab_index`, `required`, `encryption`) VALUES (:fgroup, :name, :type, 0, 0, :required, :encryption); ");
            $stmt->bindParam(":fgroup", $fgroup, PDO::PARAM_STR);
            $stmt->bindParam(":name", $name, PDO::PARAM_STR, 100);
            $stmt->bindParam(":type", $type, PDO::PARAM_STR, 20);
            $stmt->bindParam(":required", $required, PDO::PARAM_INT);
            $stmt->bindParam(":encryption", $encryption, PDO::PARAM_INT);
            $stmt->execute();

            // Get the id that was inserted
            $field_id = $db->lastInsertId();

            // Close the database connection
            db_close($db);

            // Run the create function for the type specified
            switch ($type)
            {
                case "dropdown":
                    create_dropdown_field($field_id);
                    return $field_id;
                break;
                case "multidropdown":
                    create_dropdown_field($field_id);
                    return $field_id;
                break;
                case "user_multidropdown":
                case "shorttext":
                case "longtext":
                case "date":
                    return $field_id;
                    break;
                default:
                    // Delete inserted field from custom_fields table
                    delete_field($field_id);
                    
                    set_alert(true, "bad", "An invalid field type was specified.");
                    return false;
            }
        }
        // The field name already exists
        else
        {
            // Display an alert
            set_alert(true, "bad", "Unable to create field.  The specified field name is already in use.");
            return false;
        }
    }
    // An invalid type was specified
    else
    {
        // Display an alert
        set_alert(true, "bad", "Unable to create field.  An invalid field type was specified.");
        return false;
    }
}

/**************************
 * FUNCTION: DELETE FIELD *
 **************************/
function delete_field($field_id)
{
    // If the field is an integer value
    if (intval($field_id))
    {
        // Open the database connection
        $db = db_open();

        // Delete the field table
        $stmt = $db->prepare("DROP TABLE IF EXISTS `custom_field_" . $field_id . "`;");
        $stmt->execute();

        // Delete custom field by ID
        $stmt = $db->prepare("DELETE FROM `custom_fields` WHERE id=:field_id;");
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
        $stmt->execute();

        // Delete custom risk data by field ID
        $stmt = $db->prepare("DELETE FROM `custom_risk_data` WHERE field_id=:field_id;");
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
        $stmt->execute();

        // Delete custom asset data by asset ID
        $stmt = $db->prepare("DELETE FROM `custom_asset_data` WHERE field_id=:field_id;");
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        return true;
    }else{
        return false;
    }
}

/****************************************************************************
 * FUNCTION: ADD_BASIC_FIELD                                                *
 * $name: name of the field                                                 *
 * $fgroup: group of the field. Either 'asset' or 'risk'                    *
 * $tab_index: The index of the tab the field should appear on              *
 *             1:details, 2: mitigation, 3: review                          *
 * $panel: Name of the panel the field should be on                         *
 *         Possible values are: 'left', 'right', 'bottom'                   *
 *                                                                          *
 * With this function the position of the field within the                  *
 * panel can't be specified. It'll always be the last field on that panel.    *
 ****************************************************************************/
function add_basic_field($name, $fgroup, $tab_index, $panel)
{
    // Connect to the database
    $db = db_open();

    $stmt = $db->prepare("
        INSERT INTO `custom_fields` (`fgroup`, `name`, `type`, `is_basic`, `tab_index`) VALUES
             (:fgroup, :name, '', '1', :tab_index)
        ON DUPLICATE KEY UPDATE
            `fgroup` = VALUES(`fgroup`),
            `type` = VALUES(`type`),
            `is_basic` = VALUES(`is_basic`),
            `tab_index` = VALUES(`tab_index`);
    ");
    $stmt->bindParam(":fgroup", $fgroup, PDO::PARAM_STR);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":tab_index", $tab_index, PDO::PARAM_STR);
    $stmt->execute();

    //Calculating the 
    $stmt = $db->prepare("
        SELECT
            MAX(ordering) + 1
        FROM
            `custom_template`
        WHERE
            `tab_index` = :tab_index
            AND `panel_name` = :panel;
    ");
    $stmt->bindParam(":tab_index", $tab_index, PDO::PARAM_STR);
    $stmt->bindParam(":panel", $panel, PDO::PARAM_STR);
    $stmt->execute();
    $order = $stmt->fetchColumn();

    // Set default main asset fields for template
    $stmt = $db->prepare("
        INSERT INTO `custom_template` (`custom_field_id` ,`tab_index` ,`ordering` ,`panel_name`) 
            SELECT id, tab_index, :order, :panel FROM `custom_fields` WHERE `name` = :name AND `fgroup` = :fgroup
        ;
    ");
    $stmt->bindParam(":order", $order, PDO::PARAM_STR);
    $stmt->bindParam(":panel", $panel, PDO::PARAM_STR);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":fgroup", $fgroup, PDO::PARAM_STR);
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/**************************
 * FUNCTION: DELETE BASIC FIELD *
 **************************/
function delete_basic_field($name, $fgroup) {
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        DELETE FROM 
            `custom_template`
        WHERE
            `custom_field_id` = (
                SELECT
                    `id`
                FROM
                    `custom_fields`
                WHERE
                    `name` = :name
                    AND `fgroup` = :fgroup
                    AND `is_basic` = 1
            )
    ;");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":fgroup", $fgroup, PDO::PARAM_STR);
    $stmt->execute();

    $stmt = $db->prepare("
        DELETE FROM 
            `custom_fields`
        WHERE
            name = :name
            AND fgroup = :fgroup
            AND is_basic = 1
        ;");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":fgroup", $fgroup, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/***********************************
 * FUNCTION: CREATE DROPDOWN FIELD *
 ***********************************/
function create_dropdown_field($field_id)
{
    // Open the database connection
    $db = db_open();

    // Add the new dropdown field
    $stmt = $db->prepare("CREATE TABLE `custom_field_" . $field_id . "` (value int(11) AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**************************
 * FUNCTION: FIELD EXISTS *
 **************************/
function field_exists($name, $fgroup="risk")
{
    // Open the database connection
    $db = db_open();

    // Check if the name is in the database
    $stmt = $db->prepare("SELECT id FROM `custom_fields` WHERE name=:name AND fgroup=:fgroup; ");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 100);
    $stmt->bindParam(":fgroup", $fgroup, PDO::PARAM_STR);
    $stmt->execute();
    $fields = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // If the field does not exist
    if (empty($fields))
    {
        // Return -1
        return -1;
    }
    // Otherwise return the field id
    else return $fields['id'];
}

/************************
 * FUNCTION: ADD OPTION *
 ************************/
function addOption()
{
    global $escaper, $lang;

    // If the user is an administrator
    if (is_admin())
    {
        // If the name and type aren't set or the name is empty
        if (empty($_POST['name']))
        {
            $status = "400";
            set_alert(true, "bad", $escaper->escapeHtml($lang['OptionNameNotEmpty']));
            $data = array();
        }
        else
        {
            $name = get_param("POST", 'name');
            $field_id = (int)get_param("POST", 'field');
            if(get_value_by_name("custom_field_".$field_id, $name))
            {
                $status = 400;
                $status_message = $escaper->escapeHtml($lang['DuplicatedOptionName']);
                set_alert(true, "bad", $status_message);
                $data = array();
            }
            else
            {
                // Try creating the field
                if (add_name("custom_field_".$field_id, $name))
                {
                    $status = 200;
                    $status_message = _lang("CustomOptionAddedSuccess", ["name" => $escaper->escapeHtml($name)]);
                    set_alert(true, "good", $status_message);
                    $data = array();
                }
            }
        }
    }else{
        $status = 401;
        set_alert(true, "bad", $escaper->escapeHtml($lang['QueryOnlyAvailableToAdminUsers']));
        $data = array();
    }
    
    $status_message = get_alert(true);

    // Return a JSON response
    json_response($status, $status_message, $data);
}

/***************************
 * FUNCTION: DELETE OPTION *
 ***************************/
function deleteOption()
{
    global $escaper, $lang;

    // If the user is an administrator
    if (is_admin())
    {
        // If the name and type aren't set or the name is empty
        if (empty($_POST['value']))
        {
            $status = "400";
            set_alert(true, "bad", $escaper->escapeHtml($lang['SelectOptionsToDelete']));
            $data = array();
        }
        else
        {
            $values = get_param("POST", 'value');
            $field_id = (int)get_param("POST", 'field');
            
            if(!is_array($values)){
                $values = [$values];
            }
            
            foreach($values as $value)
            {
                delete_value_by_name("custom_field_".$field_id, $value);
            }
            
            $status = 200;
            set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));
            $data = array();
        }
    }else{
        $status = 401;
        set_alert(true, "bad", $escaper->escapeHtml($lang['QueryOnlyAvailableToAdminUsers']));
        $data = array();
    }

    $status_message = get_alert(true);

    // Return a JSON response
    json_response($status, $status_message, $data);
}

/******************************
 * FUNCTION: ADD CUSTOM FIELD *
 ******************************/
function addCustomField()
{
    global $escaper;

    // If the user is an administrator
    if (is_admin())
    {
        // If the name and type aren't set or the name is empty
        if (!(isset($_POST['name']) && isset($_POST['type'])) || ($_POST['name'] == ""))
        {
            $status = "400";
            set_alert(true, "bad", $escaper->escapeHtml($lang['CustomFieldNameNotEmpty']));
            $data = array();
        }
        else
        {
            $fgroup = get_param("POST", 'fgroup');
            $name = get_param("POST", 'name');
            $type = get_param("POST", 'type');
            $required = get_param("POST", 'required', 0);
            $encryption = get_param("POST", 'encryption', 0);

            // Try creating the field
            if (create_field($fgroup, $name, $type, $required, $encryption))
            {
                $status = 200;
                $status_message = _lang("CustomFieldCreatedSuccess", ["name" => $escaper->escapeHtml($name)]);
                set_alert(true, "good", $status_message);
                $data = array();
            }
        }
    }else{
        $status = 401;
        set_alert(true, "bad", $escaper->escapeHtml($lang['QueryOnlyAvailableToAdminUsers']));
        $data = array();
    }

    $status_message = get_alert(true);

    // Return a JSON response
    json_response($status, $status_message, $data);
}

/********************************
 * FUNCTION: DELTE CUSTOM FIELD *
 ********************************/
function deleteCustomField()
{
    global $escaper;
    global $lang;

    // If the user is an administrator
    if (is_admin())
    {
        // If the field id is not set or is not an integer value
        if (!(isset($_POST['field_id']) && intval($_POST['field_id'])))
        {
            $status = "400";
            set_alert(true, "bad", $escaper->escapeHtml($lang['CustomFieldNameNotEmpty']));
            $data = array();
        }
        else
        {
            $field_id = get_param("POST", 'field_id');

            // Try deleting the field
            if (delete_field($field_id))
            {
                $status = 200;
                set_alert(true, "good", $escaper->escapeHtml($lang['CustomFieldDeletedSuccess']));
                $data = array();
            }else{
                $status = 400;
                set_alert(true, "bad", $escaper->escapeHtml($lang['CustomFieldDeletedFailed']));
                $data = array();
            }

        }
    }else{
        $status = 401;
        set_alert(true, "bad", $escaper->escapeHtml($lang['QueryOnlyAvailableToAdminUsers']));
        $data = array();
    }

    $status_message = get_alert(true);

    // Return a JSON response
    json_response($status, $status_message, $data);
}

/******************************
 * FUNCTION: GET CUSTOM FIELD *
 ******************************/
function getCustomField()
{
    global $escaper;
    global $lang;

    // If the user is an administrator
    if (is_admin())
    {
        // If the field id is not set or is not an integer value
        if (!(isset($_GET['field_id']) && intval($_GET['field_id'])))
        {
            $status = "400";
            $status_message = $escaper->escapeHtml($lang['CustomFieldNameNotEmpty']);
            $data = array();
        }
        else
        {
            $field_id = get_param("GET", 'field_id');
            
            $field = get_field_by_id($field_id);
            
            $data = [
                "type" => $field['type'],
                "required" => $field['required'],
                "encryption" => $field['encryption'],
                "name" => $field['name']
            ];
            
            switch($field['type'])
            {
                case "dropdown":
                    $content_html = create_dropdown("custom_field_".$field_id, NULL, NULL, true, false, true, "", "--", "", false);
                break;
                case "multidropdown":
                    ob_start();
                    create_multiple_dropdown("custom_field_".$field_id, NULL, NULL, NULL, false, "--", "", false);
                    $content_html = ob_get_contents();
                    ob_end_clean();
                break;
                case "shorttext":
                    $content_html = "<input name=\"custom_field_".$field_id."\" type=\"text\" />";
                break;
                case "longtext":
                    $content_html = "<textarea class='full-width' name=\"custom_field_".$field_id."\" ></textarea>";
                break;
                case "date":
                    $content_html = "<input class=\"datepicker\" name=\"custom_field_".$field_id."\" type=\"text\" autocomplete=\"off\"/>";
                break;
                case "user_multidropdown":
                    ob_start();
                    create_multiusers_dropdown("custom_field_".$field_id);
                    $content_html = ob_get_contents();
                    ob_end_clean();
                break;
            }
            
            $data['content_html'] = $content_html;
            
            $status = "200";
            $status_message = $escaper->escapeHtml($lang['Success']);
            // DO STUFF HERE
        }
    }
    else
    {
        $status = 401;
        $status_message = "Query only available to admin users.";
        $data = array();
    }

    // Return a JSON response
    json_response($status, $status_message, $data);
}

/**************************
 * FUNCTION: SAVE TEMPATE *
 **************************/
function svae_template($tabs, $fgroup="risk")
{
    // Open the database connection
    $db = db_open();

    // Remove all data from custom_template by fgroup
    $sql = "DELETE `custom_template` FROM `custom_template` INNER JOIN `custom_fields` ON custom_template.custom_field_id=`custom_fields`.id WHERE `custom_fields`.fgroup=:fgroup;";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":fgroup", $fgroup, PDO::PARAM_STR);
    $stmt->execute();


    foreach($tabs as $tab)
    {
        $tab_index = (int)$tab['tab_index'];

        $sql = "INSERT INTO custom_template(custom_field_id, tab_index, ordering, panel_name) VALUES ";
        $values_array = [];
        if (!empty($tab['left'])) {
            $tab['left'] = array_unique($tab['left']);
            foreach($tab['left'] as $key => $field_id){
                $field_id = (int)$field_id;
                $values_array[] = "({$field_id}, {$tab_index}, {$key}, 'left')";
            }
        }
        if (!empty($tab['right'])) {
            $tab['right'] = array_unique($tab['right']);
            foreach($tab['right'] as $key => $field_id){
                $field_id = (int)$field_id;
                $values_array[] = "({$field_id}, {$tab_index}, {$key}, 'right')";
            }
        }
        if (!empty($tab['bottom'])) {
            $tab['bottom'] = array_unique($tab['bottom']);
            foreach($tab['bottom'] as $key => $field_id){
                $field_id = (int)$field_id;
                $values_array[] = "({$field_id}, {$tab_index}, {$key}, 'bottom')";
            }
        }
        
        // Save panel infos
        if($values_array){
            $sql .= implode(", ", $values_array) . ";";
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }
    }

    // Close the database connection
    db_close($db);
}

/*******************************************
 * FUNCTION: SAVE TEMPATE AND GET RESPONSE *
 *******************************************/
function saveTemplateResponse()
{
    global $escaper;
    global $lang;

    // If the user is an administrator
    if (is_admin())
    {
        $status = 200;
        
        $fgroup = get_param("GET", "fgroup", "risk");
        $data = $_POST['template'];
        svae_template($data, $fgroup);
        set_alert(true, "good", $escaper->escapeHtml($lang['SuccessTemplateSaved']));
        $data = array('status'=>true);
    }
    else
    {
        $status = 401;
        set_alert(true, "bad", $escaper->escapeHtml($lang['QueryOnlyAvailableToAdminUsers']));
        $data = array();
    }
    
    $status_message = get_alert(true);
    // Return a JSON response
    json_response($status, $status_message, $data);
}

/******************************************
 * FUNCTION: GET CUSTOM VALUES BY RISK ID *
 ******************************************/
function getCustomFieldValuesByRiskId($risk_id, $tab_index=false, $review_id=0)
{
    $id = (int)$risk_id - 1000;
    
    // Open the database connection
    $db = db_open();

    if($tab_index === false)
    {
        // If review_id is 0, get all custom values including review
        if($review_id == 0)
        {
            $stmt = $db->prepare("
                SELECT t1.field_id, t1.value, t1.review_id, t2.name field_name, t2.type field_type, t2.encryption, t3.tab_index
                FROM risks t0 
                    INNER JOIN custom_risk_data t1 ON t0.id=t1.risk_id
                    INNER JOIN custom_fields t2 ON t1.field_id=t2.id 
                    LEFT JOIN custom_template t3 ON t2.id=t3.custom_field_id 
                WHERE t1.risk_id=:risk_id AND (t1.review_id=:review_id OR t1.review_id=t0.mgmt_review); 
            ");
            $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":review_id", $review_id, PDO::PARAM_INT);
        }
        else
        {
            $stmt = $db->prepare("
                SELECT t1.field_id, t1.value, t1.review_id, t2.name field_name, t2.type field_type, t2.encryption, t3.tab_index
                FROM custom_risk_data t1 INNER JOIN custom_fields t2 ON t1.field_id=t2.id 
                LEFT JOIN custom_template t3 ON t2.id=t3.custom_field_id 
                WHERE t1.risk_id=:risk_id AND t1.review_id=:review_id; 
            ");
            $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":review_id", $review_id, PDO::PARAM_INT);
        }
    }
    else
    {
        $stmt = $db->prepare("
            SELECT t1.field_id, t1.value, t1.review_id, t2.name field_name, t2.type field_type, t2.encryption, t3.tab_index 
            FROM custom_risk_data t1 INNER JOIN custom_fields t2 ON t1.field_id=t2.id 
                LEFT JOIN custom_template t3 ON t2.id=t3.custom_field_id 
            WHERE t1.risk_id=:risk_id AND t3.tab_index=:tab_index AND t1.review_id=:review_id; 
        ");
        $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":review_id", $review_id, PDO::PARAM_INT);
        $stmt->bindParam(":tab_index", $tab_index, PDO::PARAM_INT);
    }
    $stmt->execute();
    $values = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    return $values;
}

/*******************************************
 * FUNCTION: SAVE RISK CUSTOM FIELD VALUES *
 *******************************************/
function save_risk_custom_field_values($risk_id, $review_id=0)
{
    global $lang, $escaper;
    
    $id = (int)$risk_id - 1000;
    
    $custom_fields_by_key = [];
    $custom_fields = get_custom_fields();
    foreach($custom_fields as $custom_field){
        $custom_fields_by_key[$custom_field['id']] = $custom_field;
    }

    $custom_values = get_param("POST", 'custom_field', []);
    
    // Open the database connection
    $db = db_open();

    foreach($custom_values as $field_id => $custom_value)
    {
        // Disregard if this field is required and the value is empty
        if($custom_fields_by_key[$field_id]['required'] == 1 && empty($custom_value))
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['ThereAreRequiredFields']));
            return false;
        }
        
        // If enctypion extra is enabled, encrypt custom value
        if(encryption_extra())
        {
            require_once(realpath(__DIR__ . '/../encryption/index.php'));
            $custom_value = encrypt_custom_value($custom_fields_by_key[$field_id], $custom_value);
        }

        // If the custom field type is date, 
        if($custom_fields_by_key[$field_id]['type'] == "date")
        {
            $value = get_standard_date_from_default_format($custom_value);
            $value == "0000-00-00" && $value = "";
        }
        else
        {
            $value = is_array($custom_value) ? implode(",", $custom_value) : $custom_value;
        }
        
        // Fetch existing custom risk data
        $stmt = $db->prepare("SELECT * FROM `custom_risk_data` WHERE risk_id=:risk_id AND field_id=:field_id AND review_id=:review_id; ");
        $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
        $stmt->bindParam(":review_id", $review_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If this cumstom field was saved last, update the value
        if($row)
        {
            $stmt = $db->prepare("UPDATE `custom_risk_data` SET value=:value WHERE risk_id=:risk_id AND field_id=:field_id AND review_id=:review_id; ");
            $stmt->bindParam(":value", $value, PDO::PARAM_STR);
            $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
            $stmt->bindParam(":review_id", $review_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        // If this cumstom field wasn't saved last, add new value
        else
        {
            $stmt = $db->prepare("INSERT `custom_risk_data` SET value=:value, risk_id=:risk_id, field_id=:field_id, review_id=:review_id; ");
            $stmt->bindParam(":value", $value, PDO::PARAM_STR);
            $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
            $stmt->bindParam(":review_id", $review_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    // Close the database connection
    db_close($db);
    
    return true;
}

/********************************************
 * FUNCTION: SAVE ASSET CUSTOM FIELD VALUES *
 ********************************************/
function save_asset_custom_field_values($asset_id)
{
    global $lang, $escaper;
    
    $custom_fields_by_key = [];
    $custom_fields = get_custom_fields("asset");
    foreach($custom_fields as $custom_field){
        $custom_fields_by_key[$custom_field['id']] = $custom_field;
    }

    $custom_values = get_param("POST", 'custom_field', []);
    
    // Open the database connection
    $db = db_open();

    foreach($custom_values as $field_id => $custom_value)
    {
        // Disregard if this field is required and the value is empty
        if($custom_fields_by_key[$field_id]['required'] == 1 && empty($custom_value))
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['ThereAreRequiredFields']));
            return false;
        }

        // If enctypion extra is enabled, encrypt custom value
        if(encryption_extra())
        {
            require_once(realpath(__DIR__ . '/../encryption/index.php'));
            $custom_value = encrypt_custom_value($custom_fields_by_key[$field_id], $custom_value);
        }

        // If the custom field type is date, 
        if($custom_fields_by_key[$field_id]['type'] == "date")
        {
            $value = get_standard_date_from_default_format($custom_value);
            $value == "0000-00-00" && $value = "";
        }
        else
        {
            $value = is_array($custom_value) ? implode(",", $custom_value) : $custom_value;
        }
        
        // Fetch existing custom asset data
        $stmt = $db->prepare("SELECT * FROM `custom_asset_data` WHERE asset_id=:asset_id AND field_id=:field_id; ");
        $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If this cumstom field was saved last, update the value
        if($row)
        {
            $stmt = $db->prepare("UPDATE `custom_asset_data` SET value=:value WHERE asset_id=:asset_id AND field_id=:field_id; ");
            $stmt->bindParam(":value", $value, PDO::PARAM_STR);
            $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
            $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        // If this cumstom field wasn't saved last, add new value
        else
        {
            $stmt = $db->prepare("INSERT `custom_asset_data` SET value=:value, asset_id=:asset_id, field_id=:field_id; ");
            $stmt->bindParam(":value", $value, PDO::PARAM_STR);
            $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
            $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    // Close the database connection
    db_close($db);
    
    return true;
}

/*********************************************
 * FUNCTION: UPDATE CUSTOM NAME AND REQUIRED *
 *********************************************/
function update_custom_field($field_id, $field_name, $required, $encryption)
{
    // Open the database connection
    $db = db_open();

    if(encryption_extra())
    {
        $old_field = get_field_by_id($field_id);
        
        // Query the database
        $stmt = $db->prepare("UPDATE `custom_fields` SET name=:name, required=:required, encryption=:encryption where id=:field_id; ");
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_STR);
        $stmt->bindParam(":name", $field_name, PDO::PARAM_STR);
        $stmt->bindParam(":required", $required, PDO::PARAM_INT);
        $stmt->bindParam(":encryption", $encryption, PDO::PARAM_INT);
        $stmt->execute();

        // Load the extra
        require_once(realpath(__DIR__ . '/../encryption/index.php'));
        if($old_field['encryption'] == "0" && $encryption == "1")
        {
            encrypt_custom_field_data($field_id);
        }
        elseif($old_field['encryption'] == "1" && $encryption == "0")
        {
            decrypt_custom_field_data($field_id);
        }
    }
    else
    {
        // Query the database
        $stmt = $db->prepare("UPDATE `custom_fields` SET name=:name, required=:required where id=:field_id; ");
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_STR);
        $stmt->bindParam(":name", $field_name, PDO::PARAM_STR);
        $stmt->bindParam(":required", $required, PDO::PARAM_INT);

        $stmt->execute();
    }

    
    return true;
}

/*******************************************************
 * FUNCTION: SAVE ASSET CUSTOM FIELD VALUE BY FIELD ID *
 *******************************************************/
function save_asset_custom_field_by_field_id($asset_id, $field_id, $value)
{
    global $lang, $escaper;
    
    // Open the database connection
    $db = db_open();

    $field = get_field_by_id($field_id);
    
    // Disregard if this field is required and the value is empty
    if($field['required'] == 1 && empty($value))
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['ThisFieldIsRequired']));
        return false;
    }
    
    // If enctypion extra is enabled, encrypt custom value
    if(encryption_extra())
    {
        require_once(realpath(__DIR__ . '/../encryption/index.php'));
        $value = encrypt_custom_value($field, $value);
    }
    
    // If the custom field type is date, 
    if($field['type'] == "date")
    {
        $value = get_standard_date_from_default_format($value);
        $value == "0000-00-00" && $value = "";
    }
    else
    {
        $value = is_array($value) ? implode(",", $value) : $value;
    }
    
    
    // Fetch existing custom asset data
    $stmt = $db->prepare("SELECT * FROM `custom_asset_data` WHERE asset_id=:asset_id AND field_id=:field_id; ");
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
    $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If this cumstom field was saved last, update the value
    if($row)
    {
        $stmt = $db->prepare("UPDATE `custom_asset_data` SET value=:value WHERE asset_id=:asset_id AND field_id=:field_id; ");
        $stmt->bindParam(":value", $value, PDO::PARAM_STR);
        $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
        $stmt->execute();
    }
    // If this cumstom field wasn't saved last, add new value
    else
    {
        $stmt = $db->prepare("INSERT `custom_asset_data` SET value=:value, asset_id=:asset_id, field_id=:field_id; ");
        $stmt->bindParam(":value", $value, PDO::PARAM_STR);
        $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
    
    return true;
}

/*************************************
 * FUNCTION: SET DEFAULT MAIN FIELDS *
 *************************************/
function set_default_main_fields($fgroup="risk")
{
    // Open the database connection
    $db = db_open();
    
    // Clean old main fields
//    $stmt = $db->prepare("DELETE `custom_template` FROM `custom_template` INNER JOIN `custom_fields` ON custom_template.custom_field_id=`custom_fields`.id WHERE `custom_fields`.is_basic=1; ");
    $stmt = $db->prepare("DELETE `custom_template` FROM `custom_template` INNER JOIN `custom_fields` ON custom_template.custom_field_id=`custom_fields`.id WHERE `custom_fields`.fgroup=:fgroup; ");
    $stmt->bindParam(":fgroup", $fgroup, PDO::PARAM_STR);
    $stmt->execute();

    if($fgroup == "risk")
    {
        // Set default main fields
        $stmt = $db->prepare("
            INSERT INTO `custom_template` (`custom_field_id` ,`tab_index` ,`ordering` ,`panel_name`) 
                SELECT id, tab_index, 0, 'left' FROM `custom_fields` WHERE `name` = 'SubmissionDate' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 1, 'left' FROM `custom_fields` WHERE `name` = 'Category' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 2, 'left' FROM `custom_fields` WHERE `name` = 'SiteLocation' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 3, 'left' FROM `custom_fields` WHERE `name` = 'ExternalReferenceId' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 4, 'left' FROM `custom_fields` WHERE `name` = 'ControlRegulation' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 5, 'left' FROM `custom_fields` WHERE `name` = 'ControlNumber' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 6, 'left' FROM `custom_fields` WHERE `name` = 'AffectedAssets' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 7, 'left' FROM `custom_fields` WHERE `name` = 'Technology' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 8, 'left' FROM `custom_fields` WHERE `name` = 'Team' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 9, 'left' FROM `custom_fields` WHERE `name` = 'AdditionalStakeholders' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 10, 'left' FROM `custom_fields` WHERE `name` = 'Owner' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 11, 'left' FROM `custom_fields` WHERE `name` = 'OwnersManager' AND `fgroup` = 'risk'

                UNION 
                SELECT id, tab_index, 0, 'right' FROM `custom_fields` WHERE `name` = 'SubmittedBy' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 1, 'right' FROM `custom_fields` WHERE `name` = 'RiskSource' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 2, 'right' FROM `custom_fields` WHERE `name` = 'RiskScoringMethod' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 3, 'right' FROM `custom_fields` WHERE `name` = 'RiskAssessment' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 4, 'right' FROM `custom_fields` WHERE `name` = 'AdditionalNotes' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 5, 'right' FROM `custom_fields` WHERE `name` = 'SupportingDocumentation' AND `fgroup` = 'risk'

                UNION
                SELECT id, tab_index, 0, 'bottom' FROM `custom_fields` WHERE `name` = 'Tags' AND `fgroup` = 'risk'

                UNION 
                SELECT id, tab_index, 0, 'left' FROM `custom_fields` WHERE `name` = 'MitigationDate' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 1, 'left' FROM `custom_fields` WHERE `name` = 'MitigationPlanning' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 2, 'left' FROM `custom_fields` WHERE `name` = 'PlanningStrategy' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 3, 'left' FROM `custom_fields` WHERE `name` = 'MitigationEffort' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 4, 'left' FROM `custom_fields` WHERE `name` = 'MitigationCost' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 5, 'left' FROM `custom_fields` WHERE `name` = 'MitigationOwner' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 6, 'left' FROM `custom_fields` WHERE `name` = 'MitigationTeam' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 7, 'left' FROM `custom_fields` WHERE `name` = 'MitigationPercent' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 8, 'left' FROM `custom_fields` WHERE `name` = 'AcceptMitigation' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 9, 'left' FROM `custom_fields` WHERE `name` = 'MitigationControls' AND `fgroup` = 'risk'

                UNION 
                SELECT id, tab_index, 0, 'right' FROM `custom_fields` WHERE `name` = 'CurrentSolution' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 1, 'right' FROM `custom_fields` WHERE `name` = 'SecurityRequirements' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 2, 'right' FROM `custom_fields` WHERE `name` = 'SecurityRecommendations' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 3, 'right' FROM `custom_fields` WHERE `name` = 'MitigationSupportingDocumentation' AND `fgroup` = 'risk'

                UNION 
                SELECT id, tab_index, 0, 'bottom' FROM `custom_fields` WHERE `name` = 'MitigationControlsList' AND `fgroup` = 'risk'
                
                UNION 
                SELECT id, tab_index, 0, 'left' FROM `custom_fields` WHERE `name` = 'ReviewDate' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 1, 'left' FROM `custom_fields` WHERE `name` = 'Reviewer' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 2, 'left' FROM `custom_fields` WHERE `name` = 'Review' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 3, 'left' FROM `custom_fields` WHERE `name` = 'NextStep' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 4, 'left' FROM `custom_fields` WHERE `name` = 'NextReviewDate' AND `fgroup` = 'risk'
                UNION 
                SELECT id, tab_index, 5, 'left' FROM `custom_fields` WHERE `name` = 'Comment' AND `fgroup` = 'risk'
                
                UNION 
                SELECT id, tab_index, 0, 'right' FROM `custom_fields` WHERE `name` = 'SetNextReviewDate' AND `fgroup` = 'risk';
        ");
        $stmt->execute();
        
        if (jira_extra()) {
            // Include the jira extra
            require_once(realpath(__DIR__ . '/../jira/index.php'));
            // Add the jira issue key field
            add_jira_issue_key_field_to_customization();
        }
    }
    elseif($fgroup == "asset")
    {
        // Set default main fields
        $stmt = $db->prepare("
            INSERT INTO `custom_template` (`custom_field_id` ,`tab_index` ,`ordering` ,`panel_name`) 
                SELECT id, tab_index, 0, 'left' FROM `custom_fields` WHERE `name` = 'AssetName' AND `fgroup` = 'asset'
                UNION 
                SELECT id, tab_index, 1, 'left' FROM `custom_fields` WHERE `name` = 'IPAddress' AND `fgroup` = 'asset'
                UNION 
                SELECT id, tab_index, 2, 'left' FROM `custom_fields` WHERE `name` = 'AssetValuation' AND `fgroup` = 'asset'
                UNION 
                SELECT id, tab_index, 3, 'left' FROM `custom_fields` WHERE `name` = 'SiteLocation' AND `fgroup` = 'asset'
                UNION 
                SELECT id, tab_index, 4, 'left' FROM `custom_fields` WHERE `name` = 'Team' AND `fgroup` = 'asset'
                UNION 
                SELECT id, tab_index, 5, 'left' FROM `custom_fields` WHERE `name` = 'AssetDetails' AND `fgroup` = 'asset'
                UNION
                SELECT id, tab_index, 6, 'left' FROM `custom_fields` WHERE `name` = 'Tags' AND `fgroup` = 'asset'
            ;
        ");
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
}

/*******************************************
* FUNCTION: DISPLAY CUSTOM FIELD RISK VIEW *
********************************************/
function display_custom_field_risk_view($field, $custom_values, $review_id=0)
{
    global $lang, $escaper;

    $value = "";
    
    // Get value of custom filed
    foreach($custom_values as $custom_value)
    {
        if($custom_value['field_id'] == $field['id'] && $custom_value['review_id'] == $review_id){
            $value = $custom_value['value'];
            break;
        }
    }
    
    echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span5 text-right\" >\n";
            echo $escaper->escapeHtml($field['name']) .": \n";
        echo "</div>\n";
        echo "<div class=\"span7\" style=\"margin-bottom: 15px; padding-top: 4px; font-weight: 700; color: #555555; padding-left: 10px;\">\n";
            echo get_custom_field_name_by_value($field['id'], $field['type'], $field['encryption'], $value);
        echo "</div>\n";
    echo "</div>\n";
}

/***************************************************
 * FUNCTION: GET CUSTOM FIELD NAME BY CUSTOM VALUE *
 ***************************************************/
function get_custom_field_name_by_value($field_id, $field_type, $field_encryption, $value)
{
    global $escaper, $lang;
    
    if($field_type == "dropdown")
    {
        $name = $escaper->escapeHtml(get_name_by_value("custom_field_".$field_id, $value));
    }
    elseif($field_type == "multidropdown")
    {
        $name = $escaper->escapeHtml(get_names_by_multi_values("custom_field_".$field_id, $value));
    }
    elseif($field_type == "shorttext" || $field_type == "longtext")
    {
        // If encryption for this field is enabled, decrypt value
        if($field_encryption == "1")
        {
            $value = try_decrypt($value);
        }
        $name = nl2br($escaper->escapeHtml($value));
    }
    elseif($field_type == "date")
    {
        // $name = ($value ? date(get_default_date_format(), strtotime($value)) : "");
        $name = format_date($value);
    }
    elseif($field_type == "user_multidropdown")
    {
        $name = $escaper->escapeHtml(get_names_by_multi_values("user", $value));
    }
    else
    {
        $name = $escaper->escapeHtml($value);
    }
    
    return $name;
}

/**********************************************************
 * FUNCTION: GET OR ADD CUSTOM FIELD VALUE BY CUSTOM NAME *
 **********************************************************/
function get_or_add_custom_field_value_by_text($field_id, $field_type, $text, $add=false)
{
    global $escaper, $lang;
    
    if($field_type == "dropdown")
    {
        $value = $escaper->escapeHtml(get_value_by_name("custom_field_".$field_id, $text));
        if($add && !$value){
            $value = add_name("custom_field_".$field_id, $text, null);
        }
    }
    elseif($field_type == "multidropdown")
    {
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("SELECT value, name FROM custom_field_{$field_id} WHERE FIND_IN_SET(name, :names) ;");
        $stmt->bindParam(":names", $text, PDO::PARAM_STR);

        $stmt->execute();

        $name_arr = explode(",", $text);
        
        // Store the list in the array
        $rows = $stmt->fetchAll();
        
        // Close the database connection
        db_close($db);
    
        $existing_name_val = [];
        foreach($rows as $row)
        {
            $existing_name_val[trim($row['name'])] = $row['value'];
        }
        
        $value_arr = [];
        foreach($name_arr as $name_str){
            if(isset($existing_name_val[trim($name_str)]))
            {
                $value_arr[] = $existing_name_val[trim($name_str)];
            }
            elseif($add){
                $value_arr[] = add_name("custom_field_".$field_id, $name_str, null);
            }
        }
        
        $value = implode(",", $value_arr);

    }
    elseif($field_type == "shorttext" || $field_type == "longtext")
    {
        $value = $text;
    }
    elseif($field_type == "date")
    {
        // $name = ($value ? date(get_default_date_format(), strtotime($value)) : "");
        $value = $text;
    }
    elseif($field_type == "user_multidropdown")
    {
        $user_names = explode(", ", $text);
        $user_ids = [];
        foreach($user_names as $user_name){
            $user_id = get_value_by_name("user", $user_name);
            if($user_id){
                $user_ids[] = $user_id;
            }
        }
        $value = implode(",", $user_ids);

    }
    else
    {
        $value = $text;
    }
    
    return $value;
}

/**************************************
* FUNCTION: DISPLAY CUSTOM FIELD EDIT *
***************************************/
function display_custom_field_edit($field, $custom_values, $container_type="div")
{
    global $lang, $escaper;

    $value = "";
    
    // Get value of custom filed
    foreach($custom_values as $custom_value)
    {
        if($custom_value['field_id'] == $field['id']){
            $value = $custom_value['value'];
            break;
        }
    }
    
    if($container_type == "div"){
        echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"span5 text-right\" >\n";
    }elseif($container_type == "div_2:10"){
        echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"span2 text-right\" >\n";
    }elseif($container_type == "table"){
        echo "<tr>\n";
            echo "<td>\n";
    }
    
    // Check if this field is required
    if($field['required'] == 1)
    {
        $requied_html = "*";
    }
    else
    {
        $requied_html = "";
    }

    echo $escaper->escapeHtml($field['name']). $requied_html .": \n";

    if($container_type == "div"){
            echo "</div>\n";
            echo "<div class=\"span7\">\n";
    }elseif($container_type == "div_2:10"){
            echo "</div>\n";
            echo "<div class=\"span10\">\n";
    }elseif($container_type == "table"){
            echo "</td>\n";
            echo "<td>\n";
    }

        display_custom_field_input_element($field, $value);

    if($container_type == "div" || $container_type == "div_2:10"){
            echo "</div>\n";
        echo "</div>\n";
    }
    elseif($container_type == "table"){
            echo "</td>\n";
        echo "</tr>\n";
    }
}

/********************************************
* FUNCTION: DISPLAY CUSTOM FIELD ASSET VIEW *
*********************************************/
function display_custom_field_asset_view($field, $custom_values)
{
    global $lang, $escaper;

    $value = "";
    
    // Get value of custom filed
    foreach($custom_values as $custom_value)
    {
        if($custom_value['field_id'] == $field['id']){
            $value = $custom_value['value'];
            break;
        }
    }
    
    echo "<td>\n";
        echo get_custom_field_name_by_value($field['id'], $field['type'], $field['encryption'], $value)."\n";
    echo "</td>\n";
}

/*******************************************
 * FUNCTION: GET CUSTOM VALUES BY ASSET ID *
 *******************************************/
function getCustomFieldValuesByAssetId($asset_id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT t1.field_id, t1.value, t2.name field_name, t2.type field_type, t2.encryption
        FROM assets t0 
            INNER JOIN custom_asset_data t1 ON t0.id=t1.asset_id
            INNER JOIN custom_fields t2 ON t1.field_id=t2.id 
        WHERE t1.asset_id=:asset_id;
    ");
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
    $stmt->execute();

    $values = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    return $values;
}

/*****************************************
* FUNCTION: DISPLAY CUSTOM FIELD TD EDIT *
******************************************/
function display_custom_field_td_edit($field, $custom_values)
{
    global $lang, $escaper;

    $value = "";
    
    // Get value of custom filed
    foreach($custom_values as $custom_value)
    {
        if($custom_value['field_id'] == $field['id']){
            $value = $custom_value['value'];
            break;
        }
    }
    
    echo "<td>\n";
            
        display_custom_field_input_element($field, $value);

    echo "</td>\n";
}

/***********************************************
* FUNCTION: DISPLAY CUSTOM FIELD INPUT ELEMENT *
************************************************/
function display_custom_field_input_element($field, $value)
{
    global $lang, $escaper;
    
    $customHtml = " title='".$escaper->escapeHtml($field['name'])."' ";
    
    // Check if this field is requied
    if($field['required'] == "1")
    {
        $customHtml .= " required ";
    }
    else
    {
        $customHtml .= "";
    }
    
    if($field['type'] == "dropdown")
    {
        create_dropdown("custom_field_".$field['id'], $value, "custom_field[{$field['id']}]", true, false, false, $customHtml);
    }
    elseif($field['type'] == "multidropdown")
    {
        $values = explode(",", $value);
        $values = ":".implode(":", $values).":";
        create_multiple_dropdown("custom_field_".$field['id'], $values, "custom_field[{$field['id']}]", NULL, false, "--", "", true, "class='multiselect'".$customHtml);
    }
    elseif($field['type'] == "shorttext")
    {
        if($field['encryption'] == "1")
        {
            $value = try_decrypt($value);
        }
        echo "<input type=\"text\" ".$customHtml." name=\"custom_field[{$field['id']}]\" value=\"".$escaper->escapeHtml($value)."\" >" ;
    }
    elseif($field['type'] == "longtext")
    {
        if($field['encryption'] == "1")
        {
            $value = try_decrypt($value);
        }
        echo "<textarea class=\"active-textfield custom-longtext\" ".$customHtml." name=\"custom_field[{$field['id']}]\" cols=\"50\" rows=\"3\" >" . $escaper->escapeHtml($value) . "</textarea>";
    }
    elseif($field['type'] == "date")
    {
        echo "<input type=\"text\" class=\"datepicker\" ".$customHtml." name=\"custom_field[{$field['id']}]\" value=\"" . $escaper->escapeHtml(format_date($value)) . "\" autocomplete=\"off\">";
    }
    elseif($field['type'] == 'user_multidropdown')
    {
        create_multiusers_dropdown("custom_field[{$field['id']}]", $value, $customHtml);
    }
}

/************************************************
 * FUNCTION: GET MAPPING VALUE FOR CUSTOM FIELD *
 ************************************************/
function get_mapping_custom_field_value($field, $mappings, $csv_line)
{
    // Create the search term
    $search_term = "custom_field_" . $field['id'];

    // Search mappings array for the search term
    $column = array_search($search_term, $mappings);

    // If the search term was mapped
    if ($column != false)
    {
        // Remove col_ to get the id value
        $key = (int)preg_replace("/^col_/", "", $column);

        // The value is located in that spot in the array
        $text = $csv_line[$key];
        
        if($text)
        {
            return get_or_add_custom_field_value_by_text($field['id'], $field['type'], $text, true);
        }
        else
        {
            return "";
        }

    }
    else return "";
}

/*************************************************
 * FUNCTION: GET CUSTOM FIELD PLAN TEXT BY VALUE *
 *************************************************/
function get_plan_custom_field_name_by_value($field_id, $field_type, $field_encryption, $value)
{
    global $escaper, $lang;

    if($field_type == "dropdown")
    {
        $name = get_name_by_value("custom_field_".$field_id, $value);
    }
    elseif($field_type == "multidropdown")
    {
        $name = get_names_by_multi_values("custom_field_".$field_id, $value);
    }
    elseif($field_type == "shorttext" || $field_type == "longtext")
    {
        // If encryption for this field is enabled, decrypt value
        if($field_encryption == "1")
        {
            $value = try_decrypt($value);
        }
        $name = $value;
    }
    elseif($field_type == "date")
    {
        // $name = ($value ? date(get_default_date_format(), strtotime($value)) : "");
        $name = $value;
    }
    elseif($field_type == "user_multidropdown")
    {
        $name = get_names_by_multi_values("user", $value);
    }
    else
    {
        $name = $value;
    }
    
    return $name;
}

/******************************************
 * FUNCTION: SAVE RISK CUSTOM FIELD VALUE *
 * $value parama
 * date: Y-m-d
 ******************************************/
function save_risk_custom_field_value_from_importexport($risk_id, $field_id, $custom_value, $review_id=0)
{
    $id = (int)$risk_id - 1000;
    
    // Open the database connection
    $db = db_open();

    // Fetch existing custom risk data
    $stmt = $db->prepare("SELECT * FROM `custom_risk_data` WHERE risk_id=:risk_id AND field_id=:field_id AND review_id=:review_id; ");
    $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
    $stmt->bindParam(":review_id", $review_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    
    // Get custom field by field_id
    $field = get_field_by_id($field_id);
    // Disregard if this field is required and the value is empty
    if($field['required'] == 1 && empty($custom_value))
    {
        return false;
    }
    
    // If enctypion extra is enabled, encrypt custom value
    if(encryption_extra())
    {
        require_once(realpath(__DIR__ . '/../encryption/index.php'));
        $custom_value = encrypt_custom_value($field, $custom_value);
    }
    
    // If the custom field type is date, 
    if($field['type'] == "date")
    {
        $custom_value == "0000-00-00" && $custom_value = "";
    }
    else
    {
        $custom_value = is_array($custom_value) ? implode(",", $custom_value) : $custom_value;
    }
    
    // If this cumstom value for this risk exists, update the value
    if($row)
    {
        $stmt = $db->prepare("UPDATE `custom_risk_data` SET value=:value WHERE risk_id=:risk_id AND field_id=:field_id AND review_id=:review_id; ");
        $stmt->bindParam(":value", $custom_value, PDO::PARAM_STR);
        $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
        $stmt->bindParam(":review_id", $review_id, PDO::PARAM_INT);
        $stmt->execute();
    }
    // If this cumstom field for this risk is new, add new value
    else
    {
        $stmt = $db->prepare("INSERT `custom_risk_data` SET value=:value, risk_id=:risk_id, field_id=:field_id, review_id=:review_id; ");
        $stmt->bindParam(":value", $custom_value, PDO::PARAM_STR);
        $stmt->bindParam(":risk_id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
        $stmt->bindParam(":review_id", $review_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
}

/*******************************************
 * FUNCTION: SAVE ASSET CUSTOM FIELD VALUE *
 * $value parama
 * date: Y-m-d
 *******************************************/
function save_asset_custom_field_value_from_importexport($asset_id, $field_id, $custom_value)
{
    // Open the database connection
    $db = db_open();

    // Fetch existing custom asset data
    $stmt = $db->prepare("SELECT * FROM `custom_asset_data` WHERE asset_id=:asset_id AND field_id=:field_id ; ");
    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
    $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get custom field by field_id
    $field = get_field_by_id($field_id);
    // Disregard if this field is required and the value is empty
    if($field['required'] == 1 && empty($custom_value))
    {
        return false;
    }
    
    // If enctypion extra is enabled, encrypt custom value
    if(encryption_extra())
    {
        require_once(realpath(__DIR__ . '/../encryption/index.php'));
        $custom_value = encrypt_custom_value($field, $custom_value);
    }
    
    // If the custom field type is date, 
    if($field['type'] == "date")
    {
        $custom_value == "0000-00-00" && $custom_value = "";
    }
    else
    {
        $custom_value = is_array($custom_value) ? implode(",", $custom_value) : $custom_value;
    }

    // If this cumstom value for this asset exists, update the value
    if($row)
    {
        $stmt = $db->prepare("UPDATE `custom_asset_data` SET value=:value WHERE asset_id=:asset_id AND field_id=:field_id; ");
        $stmt->bindParam(":value", $custom_value, PDO::PARAM_STR);
        $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
        $stmt->execute();
    }
    // If this cumstom value for this asset is new, add new value
    else
    {
        $stmt = $db->prepare("INSERT `custom_asset_data` SET value=:value, asset_id=:asset_id, field_id=:field_id; ");
        $stmt->bindParam(":value", $custom_value, PDO::PARAM_STR);
        $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
        $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
}

/***********************************************
 * FUNCTION: DELETE ALL CUSTOM DATA BY RISK ID *
 ***********************************************/
function delete_custom_data_by_risk_id($risk_id)
{
    // Open the database connection
    $db = db_open();

    // Delete the custom_risk_data related with risk ID
    $stmt = $db->prepare("DELETE FROM `custom_risk_data` WHERE `risk_id`=:id;");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    // Close the database connection
    db_close($db);
}

/************************************************
 * FUNCTION: DELETE ALL CUSTOM DATA BY ASSET ID *
 ************************************************/
function delete_custom_data_by_asset_id($asset_id)
{
    // Open the database connection
    $db = db_open();

    // Delete the custom_asset_data related with asset ID
    $stmt = $db->prepare("DELETE FROM `custom_asset_data` WHERE `asset_id`=:id;");
    $stmt->bindParam(":id", $asset_id, PDO::PARAM_INT);
    $return = $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*******************************************************
 * FUNCTION: DELETE ALL INVALID CUSTOM RISK/ASSET DATA *
 *******************************************************/
function clean_dirty_custom_risk_asset_data()
{
    // Open the database connection
    $db = db_open();

    if (table_exists('custom_risk_data')) {
        // Delete all custom_risk_data without having non-exist risk ID
        $stmt = $db->prepare("DELETE t1 FROM `custom_risk_data` t1 LEFT JOIN `risks` t2 ON t1.risk_id=t2.id WHERE t2.id IS NULL;");
        $stmt->execute();
    }

    if (table_exists('custom_asset_data')) {
        // Delete all custom_asset_data without having non-exist asset ID
        $stmt = $db->prepare("DELETE t1 FROM `custom_asset_data` t1 LEFT JOIN `assets` t2 ON t1.asset_id=t2.id WHERE t2.id IS NULL;");
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
}

?>

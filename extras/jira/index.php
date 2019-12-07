<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/







/************************************************************************************************************************
 * Note to the developer:                                                                                               *
 * Whenever you're adding a new synchronized field, update the query in function `get_synchronized_risk_field_values`   *
 ************************************************************************************************************************/

// Extra Version
define('JIRA_EXTRA_VERSION', '20191130-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_jira_extra_database();

//*** Commented as we're not using it yet ***
// If the extra is enabled and called from the command line
// if (jira_extra() && PHP_SAPI === 'cli') {
//     
//     // Include the language file
//     require_once(language_file());
// 
//     jira_sync_issues();
//     return;
// }

/*******************************
 * FUNCTION: ENABLE JIRA EXTRA *
 *******************************/
function enable_jira_extra() {
    global $lang;

    prevent_extra_double_submit('jira', true);
    
    if (!get_setting('JiraWebhookAuthToken'))
        update_or_insert_setting('JiraWebhookAuthToken', generate_token(20));

    $db = db_open();

    // Create mapping table if it's not there already
    if (!table_exists('jira_issues')) {
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `jira_issues` (
                `risk_id` INT(11) NOT NULL UNIQUE,
                `issue_key` VARCHAR(20) NOT NULL UNIQUE,
                `last_sync` DATETIME,
                `project_key` VARCHAR(20) NOT NULL,
                CONSTRAINT `issue_risk_unique` UNIQUE (`issue_key`, `risk_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }

    // Create risk change history table
    if (!table_exists('jira_risk_pending_changes')) {
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `jira_risk_pending_changes` (
                `risk_id` INT(11) NOT NULL,
                `field` VARCHAR(200) NOT NULL,
                `change_time` DATETIME NOT NULL,
                `changed_from` blob NOT NULL,
                `changed_to` blob NOT NULL,
                CONSTRAINT `risk_field_unique` UNIQUE (`risk_id`, `field`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
    
    
    // Add the `issue key` field to customization when it's enabled
    add_jira_issue_key_field_to_customization();

    // If the connection settings are valid and the instance's version is high enough to support webhooks
    if (validateConnectionSettings(get_setting('JiraInstanceURL'), get_setting('JiraUserEmail'), get_setting('JiraUserAPIKey')) && is_webhook_supported())
        // Create the webhook or re-create if it's already setup
        createOrRollWebhook();

    update_or_insert_setting('jira', true);

    $GLOBALS['jira_extra'] = true;

    $message = _lang('ExtraToggledOn', ['extra_name' => $lang['Jira'], 'user' => $_SESSION['user']]);
    write_log(1000, $_SESSION['uid'], $message, 'extra');
}

/********************************
 * FUNCTION: DISABLE JIRA EXTRA *
 ********************************/
function disable_jira_extra() {
    global $lang;

    prevent_extra_double_submit('jira', false);

//NOT SO SURE ABOUT THIS ONE!!!!
    // Delete risk change history table
    if (table_exists('jira_risk_changes')) {
        $db = db_open();
        $stmt = $db->prepare("DROP TABLE `jira_risk_changes`;");
        $stmt->execute();
        db_close($db);
    }

    remove_jira_issue_key_field_from_customization();

    update_or_insert_setting('jira', false);

    $GLOBALS['jira_extra'] = false;

    $message = _lang('ExtraToggledOff', ['extra_name' => $lang['Jira'], 'user' => $_SESSION['user']]);
    write_log(1000, $_SESSION['uid'], $message, 'extra');
}

/**************************
 * FUNCTION: JIRA VERSION *
 **************************/
function jira_version() {
    return JIRA_EXTRA_VERSION;
}

/************************************************************************************
 * FUNCTION: ADD/REMOVE JIRA ISSUE KEY FIELD TO CUSTOMIZATION                       *
 * These two functions can be called to add/remove the Jira extra's own basic field *
 * to/from the Customization extra's tables. Used to make sure the field is present *
 * when needed and not when the Jira extra is deactivated.                          *
 ************************************************************************************/
function add_jira_issue_key_field_to_customization() {
    // If customization is enabled
    if (customization_extra()) {
        // Include the customization extra
        require_once(realpath(__DIR__ . '/../customization/index.php'));
        // Create the 'JiraIssueKey' field
        add_basic_field('JiraIssueKey', 'risk', '1', 'right');
    }
}
function remove_jira_issue_key_field_from_customization() {
    // If customization is enabled
    if (customization_extra()) {
        // Include the customization extra
        require_once(realpath(__DIR__ . '/../customization/index.php'));
        // Delete the 'JiraIssueKey' field
        delete_basic_field('JiraIssueKey', 'risk');
    }
}


/************************************************************************************
 * FUNCTION: JIRA VALID WEBHOOK TOKEN                                               *
 * Used to check the webhook auth token received against the one in the database.   *
 ************************************************************************************/
function jira_valid_webhook_token($token) {

    if (!$token)
        return false;

    $token_db = get_setting('JiraWebhookAuthToken');

    return $token_db && $token === $token_db;
}

function display_jira_extra_options() {
    
    global $escaper, $lang;

    $JiraInstanceURL = get_setting('JiraInstanceURL');
    $JiraUserEmail = get_setting('JiraUserEmail');
    $JiraUserAPIKey = get_setting('JiraUserAPIKey');

    $valid = validateConnectionSettings($JiraInstanceURL, $JiraUserEmail, $JiraUserAPIKey);
    $webhook_supported = is_webhook_supported();
    $is_https = startsWith(get_setting("simplerisk_base_url"), 'https');

    $JiraInstanceURL = $escaper->escapeHtml($JiraInstanceURL);
    $JiraUserEmail = $escaper->escapeHtml($JiraUserEmail);
    $JiraUserAPIKey = $escaper->escapeHtml($JiraUserAPIKey);

    $phpExecutablePath = getPHPExecutableFromPath();

    if (!$valid || !$webhook_supported || !$is_https) {
        echo "
            <div class='alert alert-danger'>";
        if (!$valid)
            echo $escaper->escapeHtml($lang['JiraConnectionSettingsWarning']);
        elseif (!$webhook_supported)
            echo $escaper->escapeHtml($lang['JiraWebhookNotSupported']);
        elseif (!$is_https)
            echo $escaper->escapeHtml($lang['JiraWebhookNotSupportedOnHttp']);
        echo "                
            </div>";
    } else {
        echo "
            <div class='alert alert-success'>
                " . $escaper->escapeHtml($lang['JiraConnectionSettingsSuccess']) . "
            </div>
        ";
    }

    echo "
        <form method='POST'>
            <h4>" . $escaper->escapeHtml($lang['JiraConnectionSettings']) . "</h4>
            <div class='row-fluid'>
                <div class='span2'>
                    " . $escaper->escapeHtml($lang['JiraInstanceURL']) . ":
                </div>
                <div class='span3'>
                    <input type='url' name='JiraInstanceURL' required value='{$JiraInstanceURL}'/>
                </div>
            </div>
            <div class='row-fluid'>
                <div class='span2'>&nbsp;</div>
                <div class='span4 instructions'>
                    " . $escaper->escapeHtml($lang['JiraInstanceURLInstructions']) . "
                </div>
            </div>
            <div class='row-fluid'>
                <div class='span2'>
                    " . $escaper->escapeHtml($lang['JiraUserEmail']) . ":
                </div>
                <div class='span3'>
                    <input type='email' name='JiraUserEmail' required value='{$JiraUserEmail}'/>
                </div>
            </div>
            <div class='row-fluid'>
                <div class='span2'>
                    " . $escaper->escapeHtml($lang['JiraUserAPIKey']) . ":
                </div>
                <div class='span3'>
                    <input type='password' name='JiraUserAPIKey' required value='{$JiraUserAPIKey}'/>
                </div>
            </div>
            <p><input value='".$escaper->escapeHtml($lang['Update'])."' name='update_connection_settings' type='submit'></p>
        </form>
        <br/>";


    if ($valid && $JiraIssueTypes = getJiraIssueTypes()) {

        // Don't have to escape this one as it's a validated project key
        $JiraProjectKeyForNewIssue = get_setting('JiraProjectKeyForNewIssue');
        $JiraIssueTypeForNewIssue = (int)get_setting('JiraIssueTypeForNewIssue');
        $JiraCreateIssueOnNewRisk = get_setting('JiraCreateIssueOnNewRisk');

        $JiraImportExistingIssues = get_setting('JiraImportExistingIssues');
        // Don't have to escape this one as it's a validated project key
        $JiraScanProjectsForNewIssues = $escaper->escapeHtml(get_setting('JiraScanProjectsForNewIssues'));
        $JiraCreateRiskOnNewIssue = get_setting('JiraCreateRiskOnNewIssue');

        $JiraSynchronizeStatus = get_setting('JiraSynchronizeStatus');
        $JiraSynchronizeStatus_RiskClose = get_setting('JiraSynchronizeStatus_RiskClose');
        $JiraSynchronizeStatus_IssueClose = get_setting('JiraSynchronizeStatus_IssueClose');
        $JiraSynchronizeStatus_IssueClose_SetStatus = get_setting('JiraSynchronizeStatus_IssueClose_SetStatus');
        
        $JiraSynchronizeStatus_RiskReopen = get_setting('JiraSynchronizeStatus_RiskReopen');
        $JiraSynchronizeStatus_RiskReopen_SetStatus = get_setting('JiraSynchronizeStatus_RiskReopen_SetStatus');
        $JiraSynchronizeStatus_IssueReopen = get_setting('JiraSynchronizeStatus_IssueReopen');
        $JiraSynchronizeStatus_IssueReopen_SetStatus = get_setting('JiraSynchronizeStatus_IssueReopen_SetStatus');

        $JiraSynchronizeSummary = get_setting('JiraSynchronizeSummary');

        $JiraSynchronizeDescription = get_setting('JiraSynchronizeDescription');
        $JiraSynchronizeDescriptionWith = get_setting('JiraSynchronizeDescriptionWith');
        if (!$JiraSynchronizeDescriptionWith) {
            update_or_insert_setting('JiraSynchronizeDescriptionWith', 'notes');
            $JiraSynchronizeDescriptionWith = 'notes';
        }
        
        // Synchronization direction on conflict. Possible values:
        // push: SimpelRisk -> Jira
        // pull: SimpelRisk <- Jira
        $JiraFieldSyncDirectionOnConflict = get_setting('JiraFieldSyncDirectionOnConflict', 'push');
        //if (!$JiraFieldSyncDirectionOnConflict) {
        //    update_or_insert_setting('JiraFieldSyncDirectionOnConflict', 'push');
        //    $JiraFieldSyncDirectionOnConflict = 'push';
        //}
        
        $availableStatuses = getJiraStatuses();

        // This section is not part of the MVP of the 1st release
        // but will be used later when we're expanding the functionality
        /*echo "
            <form method='POST'>
                <h4>" . $escaper->escapeHtml($lang['JiraProjectSynchronizationSettings']) . "</h4>
                <div>
                    <div class='row-fluid main-option'>
                        <div class='span12'>
                            <input class='hidden-checkbox' type='checkbox' name='JiraCreateIssueOnNewRisk' id='JiraCreateIssueOnNewRisk'" . ($JiraCreateIssueOnNewRisk ? " checked" : "") . " />
                            <label for='JiraCreateIssueOnNewRisk'>
                                " . $escaper->escapeHtml($lang['JiraCreateIssueOnNewRisk']) . "
                            </label>
                        </div>
                    </div>
                    <div class='row-fluid sub-option lv1" . ($JiraCreateIssueOnNewRisk ? "" : " hidden") . "'>
                        <div class='span2'>
                            " . $escaper->escapeHtml($lang['JiraProjectKeyForNewIssue']) . ":
                        </div>
                        <div class='span3'>
                            <input type='text' name='JiraProjectKeyForNewIssue' " . ($JiraCreateIssueOnNewRisk ? "required" : "") . " value='{$JiraProjectKeyForNewIssue}'/>
                        </div>
                    </div>
                    <div class='row-fluid sub-option lv1" . ($JiraCreateIssueOnNewRisk ? "" : " hidden") . "'>
                        <div class='span2'>
                        
                            " . $escaper->escapeHtml($lang['JiraIssueTypeForNewIssue']) . ":
                        </div>
                        <div class='span3'>
                            <select name='JiraIssueTypeForNewIssue'>";

        foreach ($JiraIssueTypes as $JiraIssueTypeId => $JiraIssueTypeName) {
            echo "                  
                                    <option value='" . $escaper->escapeHtml($JiraIssueTypeId) . "' " . ($JiraIssueTypeForNewIssue === (int)$JiraIssueTypeId ? "selected" : "") . ">" . $escaper->escapeHtml($JiraIssueTypeName) . "</option>\n";
        }

        echo "                  </select>
                        </div>
                    </div>
                    <div class='row-fluid sub-option lv1" . ($JiraCreateIssueOnNewRisk ? "" : " hidden") . "'>
                        <div class='span2'>&nbsp;</div>
                        <div class='span4 instructions'>
                            " . $escaper->escapeHtml($lang['JiraIssueTypeForNewIssueInstructions']) . "
                        </div>
                    </div>
                </div>
                <div>
                    <div class='row-fluid main-option'>
                        <div class='span12'>
                            <input class='hidden-checkbox' type='checkbox' name='JiraCreateRiskOnNewIssue' id='JiraCreateRiskOnNewIssue'" . ($JiraCreateRiskOnNewIssue ? " checked" : "") . " />
                            <label for='JiraCreateRiskOnNewIssue'>
                                " . $escaper->escapeHtml($lang['JiraCreateRiskOnNewIssue']) . "
                            </label>
                        </div>
                    </div>
                    <div class='row-fluid sub-option lv1" . ($JiraCreateRiskOnNewIssue ? "" : " hidden") . "'>
                        <div class='span2'>
                            " . $escaper->escapeHtml($lang['JiraScanProjectsForNewIssues']) . ":
                        </div>
                        <div class='span3'>
                            <input type='text' name='JiraScanProjectsForNewIssues' " . ($JiraCreateRiskOnNewIssue ? "required" : "") . " value='{$JiraScanProjectsForNewIssues}'/>
                        </div>
                    </div>
                    <div class='row-fluid sub-option lv1" . ($JiraCreateRiskOnNewIssue ? "" : " hidden") . "'>
                        <div class='span2'>&nbsp;</div>
                        <div class='span4 instructions'>
                            " . $escaper->escapeHtml($lang['JiraScanProjectsForNewIssuesInstructions']) . "
                        </div>
                    </div>
                    <div class='row-fluid sub-option lv1" . ($JiraCreateRiskOnNewIssue ? "" : " hidden") . "'>
                        <div class='span12'>
                            <input class='hidden-checkbox' type='checkbox' name='JiraImportExistingIssues' id='JiraImportExistingIssues'" . ($JiraImportExistingIssues ? " checked" : "") . " />
                            <label for='JiraImportExistingIssues'>
                                " . $escaper->escapeHtml($lang['JiraImportExistingIssues']) . "
                            </label>
                        </div>
                    </div>
                    <div class='row-fluid sub-option lv1" . ($JiraCreateRiskOnNewIssue ? "" : " hidden") . "'>
                        <div class='span12 instructions checkbox-instructions'>
                            " . $escaper->escapeHtml($lang['JiraImportExistingIssuesInstructions']) . "
                        </div>
                    </div>
                </div>
                <p><input value='".$escaper->escapeHtml($lang['Update'])."' name='update_project_synchronization_settings' type='submit'></p>
            </form>
            <br/>
            ";*/


        echo "
            <form method='POST'>
                <h4>" . $escaper->escapeHtml($lang['JiraGeneralSynchronizationSettings']) . "</h4>
                
                
                <h5>" . $escaper->escapeHtml($lang['JiraSynchronizationFields']) . "</h5>
                <div class='main-option'>
                    <div class='row-fluid'>
                        <div class='span12'>
                            <input class='hidden-checkbox' type='checkbox' name='JiraSynchronizeStatus' id='JiraSynchronizeStatus'" . ($JiraSynchronizeStatus ? " checked" : "") . " />
                            <label for='JiraSynchronizeStatus'>
                                " . $escaper->escapeHtml($lang['JiraSynchronizeStatus']) . "
                            </label>
                        </div>
                    </div>
                    <div class='row-fluid'>
                        <div class='span12 instructions checkbox-instructions'>
                            " . $escaper->escapeHtml($lang['JiraSynchronizeStatusInstructions']) . "
                        </div>
                    </div>
                    <div class='row-fluid sub-option lv1" . ($JiraSynchronizeStatus ? "" : " hidden") . "'>
                        <div class='row-fluid'>
                            <div class='span12'>
                                <input class='hidden-checkbox' type='checkbox' name='JiraSynchronizeStatus_RiskClose' id='JiraSynchronizeStatus_RiskClose'" . ($JiraSynchronizeStatus_RiskClose ? " checked" : "") . " />
                                <label for='JiraSynchronizeStatus_RiskClose'>
                                    " . $escaper->escapeHtml($lang['JiraSynchronizeStatus_RiskClose']) . "
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class='row-fluid sub-option lv1" . ($JiraSynchronizeStatus ? "" : " hidden") . "'>
                        <div class='row-fluid'>
                            <div class='span12'>
                                <input class='hidden-checkbox' type='checkbox' name='JiraSynchronizeStatus_IssueClose' id='JiraSynchronizeStatus_IssueClose'" . ($JiraSynchronizeStatus_IssueClose ? " checked" : "") . " />
                                <label for='JiraSynchronizeStatus_IssueClose'>
                                    " . $escaper->escapeHtml($lang['JiraSynchronizeStatus_IssueClose']) . "
                                </label>
                            </div>
                        </div>
                        <div class='row-fluid sub-option lv2" . ($JiraSynchronizeStatus_IssueClose ? "" : " hidden") . "'>
                            <div class='span2'>
                                " . $escaper->escapeHtml($lang['JiraSynchronizeStatus_IssueClose_SetStatus']) . ":
                            </div>
                            <div class='span10'>
                                <select name='JiraSynchronizeStatus_IssueClose_SetStatus' " . ($JiraSynchronizeStatus_IssueClose ? "required" : "") . ">";

        foreach($availableStatuses as $statusCategory => $statuses) {
            // Only need the statuses that are in the 'done' status category
            if ($statusCategory !== 'done')
                continue;
            foreach($statuses as $status)
                echo "              <option value='" . $status['id'] . "' " . ($JiraSynchronizeStatus_IssueClose_SetStatus === $status['id'] ? "selected" : "") . ">" . $escaper->escapeHtml($status['name']) . "</option>\n";
        }

        echo "
                                </select>
                            </div>
                        </div>
                        <div class='row-fluid sub-option lv2" . ($JiraSynchronizeStatus_IssueClose ? "" : " hidden") . "'>
                            <div class='span5 instructions'>
                                " . $escaper->escapeHtml($lang['JiraSynchronizeStatusSelectionInstructions']) . "
                            </div>
                        </div>
                    </div>
                    
                    <div class='row-fluid sub-option lv1" . ($JiraSynchronizeStatus ? "" : " hidden") . "'>
                        <div class='row-fluid'>
                            <div class='span12'>
                                <input class='hidden-checkbox' type='checkbox' name='JiraSynchronizeStatus_RiskReopen' id='JiraSynchronizeStatus_RiskReopen'" . ($JiraSynchronizeStatus_RiskReopen ? " checked" : "") . " />
                                <label for='JiraSynchronizeStatus_RiskReopen'>
                                    " . $escaper->escapeHtml($lang['JiraSynchronizeStatus_RiskReopen']) . "
                                </label>
                            </div>
                        </div>
                        <div class='row-fluid sub-option lv2" . ($JiraSynchronizeStatus_RiskReopen ? "" : " hidden") . "'>
                            <div class='span2'>
                                " . $escaper->escapeHtml($lang['JiraSynchronizeStatus_RiskReopen_SetStatus']) . ":
                            </div>
                            <div class='span10'>
                                <select name='JiraSynchronizeStatus_RiskReopen_SetStatus' " . ($JiraSynchronizeStatus_RiskReopen ? "required" : "") . ">";

        foreach(get_options_from_table('status') as $status) {
            // Skipping the 'Closed' status
            if ($status['name'] == 'Closed')
                continue;
            echo "                <option value='" . $status['value'] . "' " . ($JiraSynchronizeStatus_RiskReopen_SetStatus === $status['name'] ? "selected" : "") . ">" . $escaper->escapeHtml($status['name']) . "</option>\n";
        }

        echo "
                                </select>
                            </div>
                        </div>
                    </div>
                    

                    <div class='row-fluid sub-option lv1" . ($JiraSynchronizeStatus ? "" : " hidden") . "'>
                        <div class='row-fluid'>
                            <div class='span12'>
                                <input class='hidden-checkbox' type='checkbox' name='JiraSynchronizeStatus_IssueReopen' id='JiraSynchronizeStatus_IssueReopen'" . ($JiraSynchronizeStatus_IssueReopen ? " checked" : "") . " />
                                <label for='JiraSynchronizeStatus_IssueReopen'>
                                    " . $escaper->escapeHtml($lang['JiraSynchronizeStatus_IssueReopen']) . "
                                </label>
                            </div>
                        </div>
                        <div class='row-fluid sub-option lv2" . ($JiraSynchronizeStatus_IssueReopen ? "" : " hidden") . "'>
                            <div class='span2'>
                                " . $escaper->escapeHtml($lang['JiraSynchronizeStatus_IssueReopen_SetStatus']) . ":
                            </div>
                            <div class='span10'>
                                <select name='JiraSynchronizeStatus_IssueReopen_SetStatus' " . ($JiraSynchronizeStatus_IssueReopen ? "required" : "") . ">";

        foreach($availableStatuses as $statusCategory => $statuses) {
            // Only skipping the statuses that are in the 'done' status category
            if ($statusCategory == 'done')
                continue;
            foreach($statuses as $status)
                echo "              <option value='" . $status['id'] . "' " . ($JiraSynchronizeStatus_IssueReopen_SetStatus === $status['id'] ? "selected" : "") . ">" . $escaper->escapeHtml($status['name']) . "</option>\n";
        }

        echo "
                                </select>
                            </div>
                        </div>
                        <div class='row-fluid sub-option lv2" . ($JiraSynchronizeStatus_IssueReopen ? "" : " hidden") . "'>
                            <div class='span5 instructions'>
                                " . $escaper->escapeHtml($lang['JiraSynchronizeStatusSelectionInstructions']) . "
                            </div>
                        </div>
                    </div>
                    
                </div>
                <div class='row-fluid'>
                    <div class='span12'>
                        <input class='hidden-checkbox' type='checkbox' name='JiraSynchronizeSummary' id='JiraSynchronizeSummary'" . ($JiraSynchronizeSummary ? " checked" : "") . " />
                        <label for='JiraSynchronizeSummary'>
                            " . $escaper->escapeHtml($lang['JiraSynchronizeSummary']) . "
                        </label>
                    </div>
                </div>
                <div>
                    <div class='row-fluid main-option'>
                        <div class='span12'>
                            <input class='hidden-checkbox' type='checkbox' name='JiraSynchronizeDescription' id='JiraSynchronizeDescription'" . ($JiraSynchronizeDescription ? " checked" : "") . " />
                            <label for='JiraSynchronizeDescription'>
                                " . $escaper->escapeHtml($lang['JiraSynchronizeDescription']) . "
                            </label>
                        </div>
                    </div>
                    <div class='row-fluid sub-option lv1" . ($JiraSynchronizeDescription ? "" : " hidden") . "'>
                        <div class='span2'>" . $escaper->escapeHtml($lang['JiraSynchronizeDescriptionWith']) . ":</div>
                        <div class='span3'>
                            <select name='JiraSynchronizeDescriptionWith' " . ($JiraSynchronizeDescription ? "required" : "") . ">
                                <option value='notes' " . ($JiraSynchronizeDescriptionWith === 'notes' ? "selected" : "") . ">" . $escaper->escapeHtml($lang['AdditionalNotes']) . "</option>\n
                                <option value='assessment' " . ($JiraSynchronizeDescriptionWith === 'assessment' ? "selected" : "") . ">" . $escaper->escapeHtml($lang['RiskAssessment']) . "</option>\n
                            </select>
                        </div>
                    </div>
                    <div class='row-fluid sub-option lv1" . ($JiraSynchronizeDescription ? "" : " hidden") . "'>
                        <div class='span2'>&nbsp;</div>
                        <div class='span3 instructions'>
                            " . $escaper->escapeHtml($lang['JiraSynchronizeDescriptionWithInstructions']) . "
                        </div>
                    </div>
                </div>
                <div>
                    <br/>
                    <h5>" . $escaper->escapeHtml($lang['JiraSynchronizationConflict']) . "</h5>
                    <div class='row-fluid'>
                        <div class='span6 instructions'>
                            " . $escaper->escapeHtml($lang['JiraSynchronizationConflictDescription']) . "
                        </div>
                    </div>
                    <div class='row-fluid'>
                        <div class='span12'>
                            <label class='radio'>
                                <input type='radio' ".($JiraFieldSyncDirectionOnConflict === 'push' ? "checked": "")." name='JiraFieldSyncDirectionOnConflict' value='push'>
                                <span>" . $escaper->escapeHtml($lang['JiraUseRiskValueOnConflict']) . "</span>
                            </label>
                        </div>
                    </div>
                    <div class='row-fluid'>
                        <div class='span12'>
                            <label class='radio'>
                                <input type='radio' ".($JiraFieldSyncDirectionOnConflict === 'pull' ? "checked": "")." name='JiraFieldSyncDirectionOnConflict' value='pull'>
                                <span>" . $escaper->escapeHtml($lang['JiraUseIssueValueOnConflict']) . "</span>
                            </label>
                        </div>
                    </div>
                </div>";
                
        // This section is not part of the MVP of the 1st release
        // but will be used later when we're expanding the functionality
        /* echo "
                <div>
                    <br/>
                    <h5>" . $escaper->escapeHtml($lang['JiraSynchronizationMethod']) . "</h5>
                    <div class='row-fluid'>
                        <div class='span12'>
                            <label class='radio'>
                                <input type='radio' ".(get_setting('JiraAllowWebhookManagement') ? "checked": "")." name='JiraSyncType' value='webhook'>
                                <span>" . $escaper->escapeHtml($lang['JiraAllowWebhookManagement']) . "</span>
                            </label>
                        </div>
                    </div>
                    <div class='row-fluid'>
                        <div class='span12 instructions'>
                            " . $escaper->escapeHtml($lang['JiraAllowWebhookManagementInstructions']) . "
                        </div>
                    </div>
                    <div class='row-fluid'>
                        <div class='span12'>
                            <label class='radio'>
                                <input type='radio' ".(get_setting('JiraUseScheduledSyncOnly') ? "checked": "")." name='JiraSyncType' value='scheduled'>
                                <span>" . $escaper->escapeHtml($lang['JiraUseScheduledSyncOnly']) . "</span>
                            </label>
                        </div>
                    </div>
                    <div class='row-fluid'>
                        <div class='span12 instructions'>
                            " . $escaper->escapeHtml($lang['PlaceTheFollowingInYourCrontabToRunAutomatically']) . ":<br />0 * * * * " . $escaper->escapeHtml($phpExecutablePath ? $phpExecutablePath : $lang['PathToPhpExecutable']) . " -f " . realpath(__DIR__ . '/index.php') . "
                        </div>
                    </div>
                </div>";*/

        echo "
                <p><input value='".$escaper->escapeHtml($lang['Update'])."' name='update_general_synchronization_settings' type='submit'></p>
            </form>\n
            <script>
                $(document).ready(function() {
                    $(document).on('change', '#JiraCreateIssueOnNewRisk', function() {
                        var optionRoot = $(this).parent().parent().parent();
                        var JiraProjectKeyForNewIssue = optionRoot.find('input[name=\'JiraProjectKeyForNewIssue\']');
                        JiraProjectKeyForNewIssue.attr('required') ? JiraProjectKeyForNewIssue.removeAttr('required') : JiraProjectKeyForNewIssue.attr('required', 'required');                        
                    });

                    $(document).on('change', '#JiraCreateRiskOnNewIssue', function() {
                        var optionRoot = $(this).parent().parent().parent();
                        var JiraScanProjectsForNewIssues = optionRoot.find('input[name=\'JiraScanProjectsForNewIssues\']');
                        JiraScanProjectsForNewIssues.attr('required') ? JiraScanProjectsForNewIssues.removeAttr('required') : JiraScanProjectsForNewIssues.attr('required', 'required');                        
                    });

                    $(document).on('change', '.main-option input[type=checkbox]', function() {
                        var optionRoot = $(this).parent().parent().parent();
                        
                        optionRoot.find('.sub-option.lv1').toggleClass('hidden');
                        
                    });
                    $(document).on('change', '.sub-option.lv1 input[type=checkbox]', function() {
                        var optionRoot = $(this).parent().parent().parent();
                        
                        optionRoot.find('.sub-option.lv2').toggleClass('hidden');
                        
                    });
                });
            </script>";
    }
}

/********************************************************************************************
 * FUNCTION: GET JIRA ISSUE TYPES                                                           *
 * Getting the possible jira issue types. Skipping subtasks for now by default              *
 * and the admin should pick the 'Task' issue type as that's not requiring us to populate   *
 * additional required fields.                                                              *
 ********************************************************************************************/
function getJiraIssueTypes() {

    global $lang;

    list($status, $result) = callJiraAPI(get_setting('JiraInstanceURL') . "rest/api/latest/issuetype", 'GET');
    if (!($status === 200 || $status === 302)) {
        set_alert(true, "bad", $lang['JiraFailedGetIssueTypes']);
        return false;
    }

    $types = [];    
    foreach(json_decode($result, true) as $issueType) {
        // Skipping the subtask issue types for now
        if ($issueType['subtask'] === true)
            continue;
        $types[(int)$issueType['id']] = $issueType['name'];
    }
    
    return $types;
}

/********************************************************************************
 * FUNCTION: GET JIRA ISSUE STATUSES                                            *
 * Getting the possible jira statuses and storing them by their categories.     *
 * Currently it's ALL the statuses without regard to the project/project type.  *
 ********************************************************************************/
function getJiraStatuses() {

    global $lang;

    list($status, $result) = callJiraAPI(get_setting('JiraInstanceURL') . "rest/api/latest/status", 'GET');
    if (!($status === 200 || $status === 302)) {
        set_alert(true, "bad", $lang['JiraFailedGetStatuses']);
        return false;
    }

    $statuses = ['new' => [], 'indeterminate' => [], 'done' => []];    
    foreach(json_decode($result, true) as $status) {
        $statuses[$status['statusCategory']['key']][] = ['id' => $status['id'], 'name' => $status['name']];
    }
    
    return $statuses;
}


function jira_update_connection_settings() {

    global $lang;

    $JiraInstanceURL = isset($_POST['JiraInstanceURL']) ? trim($_POST['JiraInstanceURL']) : '';
    if (!$JiraInstanceURL) {
        set_alert(true, "bad", $lang['JiraInstanceURLIsRequired']);
        return false;
    }
    if (!endsWith($JiraInstanceURL, '/'))
        $JiraInstanceURL .= '/';

    $JiraUserEmail = isset($_POST['JiraUserEmail']) ? trim($_POST['JiraUserEmail']) : '';
    if (!$JiraUserEmail) {
        set_alert(true, "bad", $lang['JiraUserEmailIsRequired']);
        return false;
    }

    $JiraUserAPIKey = isset($_POST['JiraUserAPIKey']) ? trim($_POST['JiraUserAPIKey']) : '';
    if (!$JiraUserAPIKey) {
        set_alert(true, "bad", $lang['JiraUserAPIKeyIsRequired']);
        return false;
    }

    $JiraAuthToken = base64_encode("$JiraUserEmail:$JiraUserAPIKey");

    update_or_insert_setting('JiraInstanceURL', $JiraInstanceURL);
    update_or_insert_setting('JiraUserEmail', $JiraUserEmail);
    update_or_insert_setting('JiraUserAPIKey', $JiraUserAPIKey);
    update_or_insert_setting('JiraAuthToken', $JiraAuthToken);

    // Checking if the server is supporting webhooks
    // The minimum build number that is supporting webhooks is 810(v5.2)
    list($status, $result) = callJiraAPI("{$JiraInstanceURL}rest/api/latest/serverInfo", 'GET');

    if (!($status === 200 || $status === 302)) {
        set_alert(true, "bad", $lang['JiraServerInfoNotAccessible']);
        update_or_insert_setting('JiraBuildNumber', 0);
        return false;
    } else {
        $result = json_decode($result, true);
        if (isset($result['buildNumber']) && is_int($result['buildNumber'])) {
            update_or_insert_setting('JiraBuildNumber', (int)$result['buildNumber']);
        } else {
            set_alert(true, "bad", $lang['JiraServerInfoInvalidOrMissingBuildNumber']);
            update_or_insert_setting('JiraBuildNumber', 0);
            return false;
        }
    }

    // Every time we're updating the connection settings we roll the webhook
    if (validateConnectionSettings($JiraInstanceURL, $JiraUserEmail, $JiraUserAPIKey) && is_webhook_supported())
        createOrRollWebhook();
}

//Checking if build number is above 810 which is the minimum to support webhooks
function is_webhook_supported() {
    return (int)get_setting('JiraBuildNumber', 0) >= 810; 
}

function jira_update_project_synchronization_settings() {

    global $lang;

    $JiraCreateRiskOnNewIssue = isset($_POST['JiraCreateRiskOnNewIssue']) ? 1 : 0;
    // If we want to create a new risk for new issues
    if ($JiraCreateRiskOnNewIssue) {
        $JiraScanProjectsForNewIssues = isset($_POST['JiraScanProjectsForNewIssues']) ? strtoupper(trim($_POST['JiraScanProjectsForNewIssues'])) : '';
        $JiraImportExistingIssues = isset($_POST['JiraImportExistingIssues']) ? 1 : 0;

        if (!$JiraScanProjectsForNewIssues) {
            set_alert(true, "bad", $lang['JiraScanProjectsForNewIssuesIsRequired']);
            return false;
        }
        
        // Parse and validate the project keys
        $JiraInstanceURL = get_setting('JiraInstanceURL');
        foreach(explode(',' ,$JiraScanProjectsForNewIssues) as $key) {
            
            $key = trim($key);
            if (!preg_match('/^([A-Z][A-Z_0-9]+)$/', $key)) {
                set_alert(true, "bad", _lang('JiraScanProjectsForNewIssuesIsMalformed', ['key' => $key], false));
                return false;
            }

            // Validate project key
            list($status, $_) = callJiraAPI("{$JiraInstanceURL}rest/api/latest/project/$key", 'GET');
            if (!($status === 200 || $status === 302)) {
                set_alert(true, "bad", _lang('JiraScanProjectsForNewIssuesIsInvalid', ['key' => $key], false));
                return false;
            }
        }

        update_or_insert_setting('JiraScanProjectsForNewIssues', $JiraScanProjectsForNewIssues);
        update_or_insert_setting('JiraImportExistingIssues', $JiraImportExistingIssues);
    }

    update_or_insert_setting('JiraCreateRiskOnNewIssue', $JiraCreateRiskOnNewIssue);
}

function jira_update_general_synchronization_settings() {

    $JiraInstanceURL = get_setting('JiraInstanceURL');

    //Status related fields
    $JiraSynchronizeStatus = isset($_POST['JiraSynchronizeStatus']) ? 1 : 0;
    
    $JiraSynchronizeStatus_RiskClose = isset($_POST['JiraSynchronizeStatus_RiskClose']) ? 1 : 0;

    $JiraSynchronizeStatus_IssueClose = isset($_POST['JiraSynchronizeStatus_IssueClose']) ? 1 : 0;
    $JiraSynchronizeStatus_IssueClose_SetStatus = isset($_POST['JiraSynchronizeStatus_IssueClose_SetStatus']) ? (int)$_POST['JiraSynchronizeStatus_IssueClose_SetStatus'] : null;

    $JiraSynchronizeStatus_RiskReopen = isset($_POST['JiraSynchronizeStatus_RiskReopen']) ? 1 : 0;
    $JiraSynchronizeStatus_RiskReopen_SetStatus = get_name_by_value('status', (int)$_POST['JiraSynchronizeStatus_RiskReopen_SetStatus']);

    $JiraSynchronizeStatus_IssueReopen = isset($_POST['JiraSynchronizeStatus_IssueReopen']) ? 1 : 0;
    $JiraSynchronizeStatus_IssueReopen_SetStatus = isset($_POST['JiraSynchronizeStatus_IssueReopen_SetStatus']) ? (int)$_POST['JiraSynchronizeStatus_IssueReopen_SetStatus'] : null;
    
    update_or_insert_setting('JiraSynchronizeStatus', $JiraSynchronizeStatus);
    update_or_insert_setting('JiraSynchronizeStatus_RiskClose', $JiraSynchronizeStatus_RiskClose);
    update_or_insert_setting('JiraSynchronizeStatus_IssueClose', $JiraSynchronizeStatus_IssueClose);
    update_or_insert_setting('JiraSynchronizeStatus_IssueClose_SetStatus', $JiraSynchronizeStatus_IssueClose_SetStatus);
    update_or_insert_setting('JiraSynchronizeStatus_RiskReopen', $JiraSynchronizeStatus_RiskReopen);
    update_or_insert_setting('JiraSynchronizeStatus_RiskReopen_SetStatus', $JiraSynchronizeStatus_RiskReopen_SetStatus);
    update_or_insert_setting('JiraSynchronizeStatus_IssueReopen', $JiraSynchronizeStatus_IssueReopen);
    update_or_insert_setting('JiraSynchronizeStatus_IssueReopen_SetStatus', $JiraSynchronizeStatus_IssueReopen_SetStatus);

    //Summary related fields
    $JiraSynchronizeSummary = isset($_POST['JiraSynchronizeSummary']) ? 1 : 0;
    update_or_insert_setting('JiraSynchronizeSummary', $JiraSynchronizeSummary);
    
    // Description related fields
    $JiraSynchronizeDescription = isset($_POST['JiraSynchronizeDescription']) ? 1 : 0;
    $JiraSynchronizeDescriptionWith = isset($_POST['JiraSynchronizeDescriptionWith']) && in_array($_POST['JiraSynchronizeDescriptionWith'], ['notes', 'assessment']) ? $_POST['JiraSynchronizeDescriptionWith'] : 'notes';
    update_or_insert_setting('JiraSynchronizeDescription', $JiraSynchronizeDescription);
    update_or_insert_setting('JiraSynchronizeDescriptionWith', $JiraSynchronizeDescriptionWith);

    // Gathering what fields are synchronised for easier checking later
    $JiraSynchronizedFields = [];
    $JiraSynchronizeStatus && $JiraSynchronizedFields[] = 'status';
    $JiraSynchronizeSummary && $JiraSynchronizedFields[] = 'summary';
    $JiraSynchronizeDescription && $JiraSynchronizedFields[] = 'description';
    update_or_insert_setting('JiraSynchronizedFields', json_encode($JiraSynchronizedFields));

    // Sync direction related settings
    // not used ATM, will be in later releases
    $JiraFieldSyncDirectionOnConflict = isset($_POST['JiraFieldSyncDirectionOnConflict']) && in_array($_POST['JiraFieldSyncDirectionOnConflict'], ['push', 'pull']) ? $_POST['JiraFieldSyncDirectionOnConflict'] : 'push';
    update_or_insert_setting('JiraFieldSyncDirectionOnConflict', $JiraFieldSyncDirectionOnConflict);
}

/************************************************************************************
 * FUNCTION: VALIDATE CONNECTION SETTINGS                                           *
 * Validating connection settings. Checking if the URL points to a jira instance,   *
 * using the credentials setup on the extra's admin page.                           *
 ************************************************************************************/
function validateConnectionSettings($JiraInstanceURL, $JiraUserEmail, $JiraUserAPIKey) {

    if (!$JiraInstanceURL || !$JiraUserEmail || !$JiraUserAPIKey)
        return false;

    global $lang;
    
    $JiraAuthToken = get_setting('JiraAuthToken');

    // Checking if the instance URL is a valid URL
    $file_headers = @get_headers($JiraInstanceURL);
    if (!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
        set_alert(true, "bad", $lang['JiraInstanceURLIsInvalid']);
        return false;
    }

    // Validate credentials
    list($status, $_) = callJiraAPI("{$JiraInstanceURL}rest/api/latest/myself", 'GET', false, $JiraAuthToken);
    if (!($status === 200 || $status === 302)) {
        set_alert(true, "bad", $lang['JiraInvalidCredentials']);
        return false;
    }

    return true;
}
/************************************************************************
 * FUNCTION: JIRA VALIDATE ISSUE KEY                                    *
 * Validating issue key's format plus checks if it's an existing issue  *
 * and whether it's already assigned to a risk.                         *
 ************************************************************************/
function jira_validate_issue_key($issue_key, $risk_id = false) {

    global $lang;

    // Validating issue key format
    if (!preg_match('/^[A-Z][A-Z_0-9]+-[0-9][0-9]*$/', $issue_key)) {
        set_alert(true, "bad", $lang['JiraIssueKeyIsMalformed']);
        return false;
    }
    
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            `risk_id`
        FROM
            `jira_issues`
        WHERE
            `issue_key`=:issue_key;
    ");

    $stmt->bindParam(":issue_key", $issue_key, PDO::PARAM_STR);
    $stmt->execute();

    $id = $stmt->fetchColumn();

    // Checking if it's already assigned to another risk
    if ($id && (!$risk_id || (int)$risk_id !== (int)$id)) {
        set_alert(true, "bad", _lang('JiraIssueKeyIsAlreadyInUse', ['risk_id' => $id + 1000]));
        return false;
    }

    // Checking if it's an existing jira issue
    list($status, $_) = callJiraAPI(get_setting('JiraInstanceURL') . "rest/api/latest/issue/{$issue_key}", 'GET');
    if (!($status === 200 || $status === 302)) {
        set_alert(true, "bad", $lang['JiraIssueKeyIsInvalid']);
        return false;
    }

    // Close the database connection
    db_close($db);
    
    return true;
}


/*
webhook statuses
409 already exists
403 no permission

*/

/********************************************************************************************
 * FUNCTION: CREATE OR ROLL WEBHOOK                                                         *
 * Create new webhook when there's none registered in the database.                         *
 * If there's any, it tries to update it with a new auth token.                             *
 * In case there's an issue with the registered webhook id, it's re-creating the webhook.   *
 ********************************************************************************************/
function createOrRollWebhook() {

    global $escaper, $lang;

    $JiraInstanceURL = get_setting('JiraInstanceURL');
    $JiraProjectKeyForNewIssue = get_setting('JiraProjectKeyForNewIssue');

    $url = $post_url = "{$JiraInstanceURL}rest/webhooks/1.0/webhook";
    $JiraWebhookId = get_setting('JiraWebhookId');

    if ($JiraWebhookId) {
        $url .= "/$JiraWebhookId/";
        $JiraWebhookAuthToken = generate_token(20);
        $JiraWebhookAuthTokenRenewed = true;
        $method = 'PUT';
    } else {
        $JiraWebhookAuthToken = get_setting('JiraWebhookAuthToken');
        $JiraWebhookAuthTokenRenewed = false;
        $method = 'POST';
    }

    $callbackUrl = get_setting("simplerisk_base_url");
    if (!endsWith($callbackUrl, '/'))
        $callbackUrl .= '/';
    $callbackUrl .= 'extras/jira/callback.php?key=${issue.key}&token=' . $JiraWebhookAuthToken;

    if (!startsWith($callbackUrl, 'https')) {
        //"Invalid URL. The only allowed protocol is HTTPS."
        set_alert(true, "bad", $lang['JiraWebhookNotSupportedOnHttp']);
        return false;        
    }


    // The structure that'll be json encoded and sent to the jira instance
    // Commented parts that'll probably be used later
    $data = json_encode([
        'name' => "[SIMPLERISK] Synchronization",
        'url' => $callbackUrl,
        'events' => [
            'jira:issue_updated',
//            'jira:issue_created',
            'jira:issue_deleted'
        ],
//        'filters' => [
//            'issue-related-events-section' => "Project = $project"
//        ],
        'excludeBody' => false
    ]);
    list($status, $result) = callJiraAPI($url, $method, $data);
    
    // If we get a 404
    if ($status == 404) {
        // It either means that something happened to the webhook with the ID we're storing
        if ($method == 'PUT') {
            //so we're using the url without the webhook id and POST-ing to create the webhook
            list($status, $result) = callJiraAPI($post_url, 'POST', $data);
        } else { // or the jira instance is unreachable
            set_alert(true, "bad", $lang['JiraInstanceURLIsInvalid']);
            return false;
        }
    }
    
    // If it's a success
    if ($status === 200 || $status === 201) {
        $webhook = json_decode($result, true);
        // we're extracting the webhook id from the end of the url in the 'self' field
        if (preg_match("!^.+/(\d+)/?$!", $webhook['self'], $matches)) {
            // and storing it
            update_or_insert_setting('JiraWebhookId', $matches[1]);

            // Save the new webhook auth token
            if ($JiraWebhookAuthTokenRenewed)
                update_or_insert_setting('JiraWebhookAuthToken', $JiraWebhookAuthToken);
            set_alert(true, "good", $lang['JiraWebhookSetupSuccess']);
            return true;
        }
    }

    set_alert(true, "bad", $lang['JiraWebhookSetupFailed']);
    return false;
}

/************************************************************************************
 * FUNCTION: JIRA UPDATE RISK ISSUE CONNECTION                                      *
 * Updates the association status of the risk-jira issue associations               *
 * Returns true if after the function there IS a jira issue connected to the risk   *
 ************************************************************************************/
function jira_update_risk_issue_connection($risk_id, $issue_key, $create = true) {

    $db = db_open();

    // Checking if the risk is already has an issue assigned to it
    $stmt = $db->prepare("
        SELECT
            `issue_key`
        FROM
            `jira_issues`
        WHERE
            `risk_id`=:risk_id;
    ");

    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    $current_issue_key = $stmt->fetchColumn();

    //No changes
    if ((!$issue_key && !$current_issue_key) ||
        $issue_key === $current_issue_key) {
        db_close($db);
        return boolval($issue_key);
    }

    $result = null;

    if ($create || (!$current_issue_key && $issue_key)) {
        //At this point we don't even have to validate
        preg_match('/^([A-Z][A-Z_0-9]+)-[0-9][0-9]*$/', $issue_key, $matches);
        $project_key = $matches[1];

        // Query the database
        $stmt = $db->prepare("
            INSERT INTO `jira_issues` (risk_id, issue_key, project_key)
            VALUES (:risk_id, :issue_key, :project_key);
        ");

        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->bindParam(":issue_key", $issue_key, PDO::PARAM_STR);
        $stmt->bindParam(":project_key", $project_key, PDO::PARAM_STR);
        $stmt->execute();
        $result = true;
        
    } elseif (!$issue_key) {
        // Disassociate the Jira issue and the risk
        $stmt = $db->prepare("
            DELETE FROM
                `jira_issues`
            WHERE
                `risk_id`=:risk_id;
        ");

        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();

        // Delete pending changes
        $stmt = $db->prepare("
            DELETE FROM
                `jira_risk_pending_changes`
            WHERE
                `risk_id`=:risk_id;
        ");

        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();

        $result = false;
    } else {
        //At this point we don't even have to validate
        preg_match('/^([A-Z][A-Z_0-9]+)-[0-9][0-9]*$/', $issue_key, $matches);
        $project_key = $matches[1];

        $stmt = $db->prepare("
            UPDATE
                `jira_issues`
            SET
                `issue_key` = :issue_key,
                `project_key` = :project_key
            WHERE
                `risk_id` = :risk_id;
        ");

        $stmt->bindParam(":issue_key", $issue_key, PDO::PARAM_STR);
        $stmt->bindParam(":project_key", $project_key, PDO::PARAM_STR);
        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = true;
    }

    db_close($db);
    
    return $result;
}

/************************************************************************************
 * FUNCTION: GET SYNCHRONIZED RISK FIELD VALUES                                     *
 * Returns the values of the fields from the risk that are possibly synchronized.   *
 * For fields like the 'description' there're multiple choices on the risk side,    *
 * the function returns both.                                                       *
 * Values returned are already decrypted.                                           *
 ************************************************************************************/
function get_synchronized_risk_field_values($risk_id) {

    $db = db_open();

    // Update this query when you're adding a new field that's possible to synchronize
    $stmt = $db->prepare("
        SELECT
            `r`.`status`,
            `r`.`subject`,
            `r`.`assessment`,
            `r`.`notes`
        FROM
            `risks` r
        WHERE
            `r`.`id`=:risk_id;
    ");

    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    if (!$data)
        return [];
    else
        $data = $data[0];
    
    $data['subject'] = try_decrypt($data['subject']);
    $data['assessment'] = try_decrypt($data['assessment']);
    $data['notes'] = try_decrypt($data['notes']);
    
    return $data;
}

/********************************************************************
 * FUNCTION: GET RISK ISSUE ASSOCIATION METADATA                    *
 * Returns the risk-issue association's metadata.                   *
 * If the risk has no issue associated, it returns an empty array.  *
 ********************************************************************/
function get_risk_issue_association_metadata($risk_id) {

    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            *
        FROM
            `jira_issues`
        WHERE
            `risk_id`=:risk_id;
    ");

    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);

    if (!$data)
        return [];
    else
        $data = $data[0];

    return $data;
}

/****************************************************************************************************
 * FUNCTION: JIRA UPDATE PENDING RISK CHANGES                                                       *
 * Updates the stored data on changes happened to a risk since its last synchronization.            *
 * If a field value returns to its original state then the entry of its changes is removed.         *
 * Changes are accumulated over time. It means that the 'from' will always be the value             *
 * it started from after the last sync and the 'to' will have the current value of the field.       *
 * No changes happened between the two are stored.                                                  *
 * The $old_values parameter should contain the field values captured BEFORE the changes happened.  *
 ****************************************************************************************************/
function jira_update_pending_risk_changes($risk_id, $old_values) {
    $new_values = get_synchronized_risk_field_values($risk_id);
    $new_changes = [];

    foreach($new_values as $field => $value) {
        if ($old_values[$field] !== $value)
            $new_changes[$field] = ['from' => $old_values[$field], 'to' => $value];
    }

    if ($new_changes) {
        $pending_changes = get_pending_risk_changes($risk_id);

        $db = db_open();

        // Getting the risk's last update date so we can match the pending change's last update to the risk's
        $stmt = $db->prepare("
            SELECT
                `last_update`
            FROM
                `risks`
            WHERE
                `id` = :risk_id;
        ");

        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $last_update = $stmt->fetchColumn();

        foreach($new_changes as $field => $change) {
            
            if ($pending_changes && array_key_exists($field, $pending_changes)) {
                
                if ($pending_changes[$field]['changed_from'] === $change['to']) {
// error_log('Delete: ' . json_encode($pending_changes) . ' -- ' . json_encode($new_changes));
                    // If the value is changed back to where it started from after the last sync
                    // we just delete the entry
                    $stmt = $db->prepare("
                        DELETE FROM
                            `jira_risk_pending_changes`
                        WHERE
                            `risk_id`=:risk_id
                            AND `field`=:field;
                    ");

                    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
                    $stmt->bindParam(":field", $field, PDO::PARAM_STR);
                    
                    $stmt->execute();                    
                } else {
// error_log('Update: ' . json_encode($pending_changes) . ' -- ' . json_encode($new_changes));
                    // Instead of creating a new entry every time, we're accumulating changes.
                    // changed_from contains the value we had after the last successful synchronization(when we delete these pending changes)
                    // and the changed_to contains the latest value of the field
                    $stmt = $db->prepare("
                        UPDATE
                            `jira_risk_pending_changes`
                        SET
                            `change_time` = :change_time,
                            `changed_to` = :changed_to
                        WHERE
                            `risk_id`=:risk_id
                            and `field`=:field;
                    ");
                    
                    $stmt->bindParam(":change_time", $last_update, PDO::PARAM_STR);
                    $stmt->bindParam(":changed_to", $change['to'], PDO::PARAM_STR);
                    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
                    $stmt->bindParam(":field", $field, PDO::PARAM_STR);
                    
                    $stmt->execute();
                }
            } else {
// error_log('New: ' . json_encode($pending_changes) . ' -- ' . json_encode($new_changes));
                $stmt = $db->prepare("
                    INSERT INTO `jira_risk_pending_changes` (risk_id, field, change_time, changed_from, changed_to)
                    VALUES (:risk_id, :field, :change_time, :changed_from, :changed_to);
                ");

                $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
                $stmt->bindParam(":field", $field, PDO::PARAM_STR);
                $stmt->bindParam(":change_time", $last_update, PDO::PARAM_STR);
                $stmt->bindParam(":changed_from", $change['from'], PDO::PARAM_STR);
                $stmt->bindParam(":changed_to", $change['to'], PDO::PARAM_STR);
                
                $stmt->execute();
            }
        }
        db_close($db);
    }
}

/********************************************************************
 * FUNCTION: GET PENDING RISK CHANGES                               *
 * Returns the pending changes of a risk, grouped by field name.    *
 ********************************************************************/
function get_pending_risk_changes($risk_id) {
    
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            *
        FROM
            `jira_risk_pending_changes`
        WHERE
            `risk_id` = :risk_id;
    ");

    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    db_close($db);
    
    $pending_changes = [];
    foreach($result as $change) {
        $pending_changes[$change['field']] = $change;
    }

    return $pending_changes;
}

/****************************************************************************
 * FUNCTION: CLEAR PENDING RISK CHANGES                                     *
 * Delete pending changes of a risk. If the $fields parameter is present,   *
 * then only the entries will be deleted whose field name is in the array.  *
 ****************************************************************************/
function clear_pending_risk_changes($risk_id, $fields=[]) {
    $db = db_open();

    $stmt = $db->prepare("
        DELETE FROM
            `jira_risk_pending_changes`
        WHERE
            `risk_id` = :risk_id
            " . ($fields ? "AND `field` IN ('".implode("','", $fields)."')" : "") . ";
    ");

    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    db_close($db);
}

/************************************************************************
 * FUNCTION: JIRA SYNC ISSUES                                           *
 * Iterates through the list of risks that have a jira issue associated *
 * and initiates a 'smart-sync' on them.                                *
 *!! Part of the 'scheduled sync' functionality that's not added yet. !!*
 ************************************************************************/
function jira_sync_issues() {
    
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            `risk_id`
        FROM
            `jira_issues`;
    ");

    $stmt->execute();

    $risk_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    db_close($db);

    if (!$risk_ids)
        return;
    
    foreach($risk_ids as $risk_id) {
        jira_synchronize_risk($risk_id);
    }
    
    
}

/************************************************************************************
 * FUNCTION: JIRA SYNCHRONIZE RISK                                                  *
 * A kind of 'smart synchronization'. If the risk wasn't synchronized with the      *
 * associated jira issue yet, based on user preferences it'll do a push or a pull.  *
 * When it's not the first synchronization it's trying to merge.                    *
 * Checking the risk's pending changes and the jira issue's change logs             *
 * and uses the latest value for each field.                                        *
 *!!       Part of the 'scheduled sync' functionality that's not added yet.       !!*
 ************************************************************************************/
 // Not fully implemented!!!
 // Note: If we don't want to use this function everywhere, then we could pass the association metadata
 // instead of just the id 
function jira_synchronize_risk($risk_id) {
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            *
        FROM
            `jira_issues`
        WHERE
            `risk_id` = :risk_id;
    ");

    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    $issue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($issue && is_array($issue))
        $issue = $issue[0];
    
    db_close($db);
    
//error_log(json_encode($issue));
    $issue_key = $issue['issue_key'];
    $risk_id = $issue['risk_id'];
    $last_sync = $issue['last_sync'];
    
    // If it's the first sync, just do a push or pull
    // based on the settings
    if ($last_sync === null) {
        if (get_setting('JiraFieldSyncDirectionOnConflict') === 'pull') {
            jira_pull_changes($issue_key, $risk_id);
        } else {
            jira_push_changes($issue_key, $risk_id);
        }
    } else {
        $last_sync = new DateTime($last_sync);

        // Loading the jira issue
        list($status, $result) = callJiraAPI(get_setting('JiraInstanceURL') . "rest/api/latest/issue/{$issue_key}?expand=renderedFields,renderedBody,changelog", 'GET');
        if (!($status === 200 || $status === 302)) {
            error_log("[JIRA] Failed to get issue '$issue_key'. Response: " . json_encode($http_response_header));
            return false;
        }

        if ($result) {
//error_log(json_encode($result));
//error_log(json_encode($risk_id));
//error_log(json_encode($last_sync));
            
            $JiraSynchronizedFields = json_decode(get_setting('JiraSynchronizedFields', '[]'), true);
            $issue = json_decode($result, true);
            $pending_risk_changes = get_pending_risk_changes($risk_id);
            $pending_issue_changes = [];

            // Iterating through the changelog entries
            foreach($issue['changelog']['histories'] as $history) {
                $created = new DateTime($history['created']);

                // Only processing the entries that happened after the last sync and for fields where synchronization is enabled
                if ($created > $last_sync && isset($history['items']) && in_array($history['items'][0]['field'], $JiraSynchronizedFields)) {
                    //error_log(json_encode($created) . " - " . json_encode($history));
                    $field = $history['items'][0]['field'];
                    
                    // Only keeping the latest changes for each field
                    if (!isset($pending_issue_changes[$field]) || $pending_issue_changes[$field]['created'] < $created) {
                        // Set the created as the parsed DateTime object for later comparisons
                        $history['created'] = $created;
                        $pending_issue_changes[$field] = $history;
                    }
                }
            }
// error_log("pending_risk_changes: " . json_encode($pending_risk_changes));
// error_log("pending_issue_changes: " . json_encode($pending_issue_changes));
        }        
        
        
        
    }

}

/****************************************************************************************
 * FUNCTION: JIRA PULL CHANGES                                                          *
 * Pulls changes from the associated jira issue and overwrites local values with them.  *
 ****************************************************************************************/
function jira_pull_changes($issue_key, $risk_id, $JiraFieldsToSynchronize = []) {

    global $lang, $escaper;

    // Load the jira issue
    list($status, $result) = callJiraAPI(get_setting('JiraInstanceURL') . "rest/api/latest/issue/{$issue_key}?expand=renderedFields,renderedBody,changelog", 'GET');
    if (!($status === 200 || $status === 302)) {
        error_log("[JIRA] Failed to get issue '$issue_key'. Response: " . json_encode($http_response_header));
        return false;
    }
    
    if ($result) {
//error_log(json_encode($result));
//error_log(json_encode($risk_id));
        $issue = json_decode($result, true);
//error_log(json_encode($issue));
        
        if (!empty($issue)) {
            
            // Getting the synchronization settings
            $JiraSynchronizeStatus = get_setting('JiraSynchronizeStatus');
            $JiraSynchronizeStatus_RiskClose = get_setting('JiraSynchronizeStatus_RiskClose');
            $JiraSynchronizeStatus_RiskReopen = get_setting('JiraSynchronizeStatus_RiskReopen');
            $JiraSynchronizeStatus_RiskReopen_SetStatus = get_setting('JiraSynchronizeStatus_RiskReopen_SetStatus');

            $JiraSynchronizeSummary = get_setting('JiraSynchronizeSummary');

            $JiraSynchronizeDescription = get_setting('JiraSynchronizeDescription');

            $changes = [];

            $db = db_open();

            // Get current datetime for last_update
            $current_datetime = date("Y-m-d H:i:s");

            if ($JiraSynchronizeStatus && $issue['fields']['status']
                && ($JiraSynchronizeStatus_RiskClose || ($JiraSynchronizeStatus_RiskReopen && $JiraSynchronizeStatus_RiskReopen_SetStatus))
            ) {
                // Status category of the jira issue's current status
                $statusCategory = $issue['fields']['status']['statusCategory']['key'];
                
                // Getting the risk's current status
                $stmt = $db->prepare("
                    SELECT
                        `status`
                    FROM
                        `risks`
                    WHERE
                        `id`=:risk_id;
                ");

                $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
                $stmt->execute();

                $risk_status = $stmt->fetchColumn();

                // Issue got closed
                if ($JiraSynchronizeStatus_RiskClose && $statusCategory === 'done' && $risk_status !== "Closed") {

                    $stmt = $db->prepare("UPDATE risks SET status='Closed', last_update=:date WHERE id = :risk_id");
                    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
                    $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
                    $stmt->execute();

                    $changes['status'] = ['from' => $risk_status, 'to' => 'Closed'];

                } elseif ($JiraSynchronizeStatus_RiskReopen && $JiraSynchronizeStatus_RiskReopen_SetStatus && $risk_status === "Closed" && $statusCategory !== 'done') {// Issue got reopened

                    $stmt = $db->prepare("UPDATE risks SET status=:status, last_update=:date WHERE id = :risk_id");
                    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
                    $stmt->bindParam(":status", $JiraSynchronizeStatus_RiskReopen_SetStatus, PDO::PARAM_STR);
                    $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
                    $stmt->execute();
                    
                    $changes['status'] = ['from' => 'Closed', 'to' => $JiraSynchronizeStatus_RiskReopen_SetStatus];
                } else {
                    // ATM we don't `care` about changes that are not either "to Closed" of "from Closed"
                }
            }

            if ($JiraSynchronizeSummary && $issue['fields']['summary']) {

                $subject = $issue['fields']['summary'];

                $stmt = $db->prepare("
                    SELECT
                        `subject`
                    FROM
                        `risks`
                    WHERE
                        `id`=:risk_id;
                ");

                $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
                $stmt->execute();

                $old_subject = try_decrypt($stmt->fetchColumn());

                // Checking whether the subject changed
                if ($old_subject && $subject && $old_subject !== $subject) {
                    $subject_enc = try_encrypt($subject);

                    // If yes, then update the risk
                    $stmt = $db->prepare("UPDATE risks SET subject=:subject, last_update=:date WHERE id = :risk_id");
                    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
                    $stmt->bindParam(":subject", $subject_enc, PDO::PARAM_STR);
                    $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
                    $stmt->execute();

                    // If the encryption extra is enabled, update order_by_subject column
                    if (encryption_extra()) {
                        require_once(realpath(__DIR__ . '/../encryption/index.php'));
                        create_subject_order(fetch_key());
                    }

                    $changes['subject'] = ['from' => $old_subject, 'to' => $subject];
                }
            }

            if ($JiraSynchronizeDescription && isset($issue['renderedFields']['description'])) {
                
                $description = $issue['renderedFields']['description'];
                
                if ($description) // Stripping html tags as at this moment SimpleRisk is not supporting richtext values
                    $description = strip_tags(html_entity_decode($description));

                $JiraSynchronizeDescriptionWith = get_setting('JiraSynchronizeDescriptionWith') === 'assessment' ? 'assessment' : 'notes';

                // Getting the current value of the field
                $stmt = $db->prepare("
                    SELECT
                        `$JiraSynchronizeDescriptionWith`
                    FROM
                        `risks`
                    WHERE
                        `id`=:risk_id;
                ");

                $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
                $stmt->execute();

                $old_description = try_decrypt($stmt->fetchColumn());
                // If the description value changed
                if (($old_description || $description) && $old_description !== $description) {
                    $description_enc = try_encrypt($description);

                    // Update the risk
                    $stmt = $db->prepare("UPDATE risks SET $JiraSynchronizeDescriptionWith=:$JiraSynchronizeDescriptionWith, last_update=:date WHERE id = :risk_id");
                    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
                    $stmt->bindParam(":$JiraSynchronizeDescriptionWith", $description_enc, PDO::PARAM_STR);
                    $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
                    $stmt->execute();

                    $changes['description'] = ['from' => $old_description, 'to' => $description];
                }
            }

            // If there're changes
            if ($changes) {

                // Update the last_sync value of the association metadata
                refresh_risk_last_sync($risk_id, $current_datetime);
                
                // Add audit log entry for each change
                foreach($changes as $field => $change) {
                    $message = _lang('JiraRiskUpdatedFromJiraAuditLog', ['risk_id' => $risk_id + 1000, 'issue_key' => $issue_key, 'field' => $field, 'from' => $change['from'], 'to' => $change['to']], false);
                    write_log($risk_id + 1000, 0, $message, 'jira');
                }

                // If notification is enabled
                if (notification_extra()) {
                    // Include the notification extra
                    require_once(realpath(__DIR__ . '/../notification/index.php'));
                    // Send the notification
                    notify_risk_update_from_jira($risk_id, $changes);
                }
            }
            
            db_close($db);
        }
    }
}


/********************************************************************************************
 * FUNCTION: JIRA SYNC ISSUES                                                               *
 * Pushes changes to the associated jira issue and overwrites its fields' values with them. *
 ********************************************************************************************/
function jira_push_changes($issue_key, $risk_id) {

    global $lang, $escaper;

    // Getting the list of pending changes so we can 
    $pending_changes = get_pending_risk_changes($risk_id);

    // If there're no changes pending and the `last_sync` field is not set yet,
    // then it's a new risk-issue association and the first sync
    $new = !$pending_changes && !get_risk_issue_association_metadata($risk_id)['last_sync'];

    
    if ($new && get_setting('JiraFieldSyncDirectionOnConflict') === 'pull') {
        jira_pull_changes($issue_key, $risk_id);
        return;
    }

    $JiraSynchronizeStatus = get_setting('JiraSynchronizeStatus');
    $JiraSynchronizeSummary = get_setting('JiraSynchronizeSummary');
    $JiraSynchronizeDescription = get_setting('JiraSynchronizeDescription');

    $risk = get_synchronized_risk_field_values($risk_id);

    $issue = ['fields' => []];
    $workflow_transition = ['transition' => []];

    if ($JiraSynchronizeStatus && ($new || isset($pending_changes['status']))) {
// error_log('Synchronize status: push');
// error_log('pending_changes - status: ' . json_encode($pending_changes));

        $transition_status_id = null;
        if ($new) {
            if ($risk['status'] === 'Closed' && get_setting('JiraSynchronizeStatus_IssueClose')) {
                $transition_status_id = (int)get_setting('JiraSynchronizeStatus_IssueClose_SetStatus');
            } elseif ($risk['status'] !== 'Closed' && get_setting('JiraSynchronizeStatus_IssueReopen')) {
                $transition_status_id = (int)get_setting('JiraSynchronizeStatus_IssueReopen_SetStatus');
            }
        }
        // Risk Reopened
        elseif ($pending_changes['status']['changed_from'] === 'Closed' && get_setting('JiraSynchronizeStatus_IssueReopen')) {
            $transition_status_id = (int)get_setting('JiraSynchronizeStatus_IssueReopen_SetStatus');
        }
        // Risk Closed
        elseif ($pending_changes['status']['changed_from'] !== 'Closed'
            && $pending_changes['status']['changed_to'] === 'Closed'
            && get_setting('JiraSynchronizeStatus_IssueClose')) {
            $transition_status_id = (int)get_setting('JiraSynchronizeStatus_IssueClose_SetStatus');
        }

        if ($transition_status_id !== null) {
            list($status, $result) = callJiraAPI(get_setting('JiraInstanceURL') . "rest/api/latest/issue/{$issue_key}/transitions?expand=transitions.fields", 'GET');
//error_log('get transitions: ' . json_encode($status) . ' - ' . json_encode($result));
            $result = json_decode($result, true);
            if (($status === 200 || $status === 204 || $status === 302) && isset($result['transitions'])) {
                foreach($result['transitions'] as $transition) {
                    if ((int)$transition['to']['id'] === $transition_status_id) {
                        $workflow_transition['transition']['id'] = $transition['id'];
                        break;
                    }
                }
            }
        }
// error_log('workflow_transition: ' . json_encode($workflow_transition));
    }


    // Have to gather these so we can selectively delete only the pending changes that were synced
    $changed_fields = [];

    if ($JiraSynchronizeSummary && isset($pending_changes['subject'])) {
        $issue['fields']['summary'] = $risk['subject'];
        $changed_fields[] = 'subject';
    }

    if ($JiraSynchronizeDescription) {
        $JiraSynchronizeDescriptionWith = get_setting('JiraSynchronizeDescriptionWith') === 'assessment' ? 'assessment' : 'notes';
        if (isset($pending_changes[$JiraSynchronizeDescriptionWith])) {
            $issue['fields']['description'] = $risk[$JiraSynchronizeDescriptionWith];
            $changed_fields[] = $JiraSynchronizeDescriptionWith;
        }
    }

    if ($workflow_transition['transition']) {
        list($status, $result) = callJiraAPI(get_setting('JiraInstanceURL') . "rest/api/latest/issue/{$issue_key}/transitions", 'POST', json_encode($workflow_transition));
// error_log('status: ' . json_encode($status) . ' - ' . json_encode($result));
        if ($status === 200 || $status === 204 || $status === 302) {
            clear_pending_risk_changes($risk_id, ['status']);
            refresh_risk_last_sync($risk_id);
            return true;
        } else {
            set_alert(true, "bad", _lang('JiraFailedToSynchronizeRiskWithIssue', ['risk_id' => $risk_id + 1000, 'issue_key' => $issue_key]));
            return false;
        }
    }

// error_log("Issue: " . json_encode($issue));

    if ($issue['fields']) {
        list($status, $result) = callJiraAPI(get_setting('JiraInstanceURL') . "rest/api/latest/issue/{$issue_key}", 'PUT', json_encode($issue));
// error_log('fields: ' . json_encode($status) . ' - ' . json_encode($result));
        if ($status === 200 || $status === 204 || $status === 302) {
            clear_pending_risk_changes($risk_id, $changed_fields);
            refresh_risk_last_sync($risk_id);
            return true;
        } else {
            set_alert(true, "bad", _lang('JiraFailedToSynchronizeRiskWithIssue', ['risk_id' => $risk_id + 1000, 'issue_key' => $issue_key]));
            return false;
        }
    }

    return false;
}

function refresh_risk_last_sync($risk_id, $current_datetime = null) {
    if ($current_datetime === null)
        $current_datetime = date("Y-m-d H:i:s");
    
    $db = db_open();

    $stmt = $db->prepare("UPDATE jira_issues SET `last_sync`=:date WHERE risk_id = :risk_id");
    $stmt->bindParam(":date", $current_datetime, PDO::PARAM_STR);
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    db_close($db);
}

function get_risk_id_by_issue_key($issue_key) {
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            `risk_id`
        FROM
            `jira_issues`
        WHERE
            `issue_key`=:issue_key;
    ");

    $stmt->bindParam(":issue_key", $issue_key, PDO::PARAM_STR);
    $stmt->execute();

    $risk_id = $stmt->fetchColumn();

    db_close($db);
    
    return $risk_id;
}

/********************************************************************************************
 * FUNCTION: CALL JIRA API                                                                  *
 * Issuing a call to the jira instance using the stored credentials(or the one provided).   *
 * Returning the status and the result in a list.                                           *
 ********************************************************************************************/
function callJiraAPI($url, $method, $data=false, $JiraAuthToken=false) {

    if ($JiraAuthToken === false)
        $JiraAuthToken = get_setting('JiraAuthToken');

    $opts = array('http' =>
        array(
            'method'  => $method,
            'header'  => 
                        "Authorization: Basic $JiraAuthToken\r\n".
                        "Content-Type: application/json\r\n".
                        "Accept: application/json\r\n",
            'ignore_errors' => true
        )
    );
    
    if ($data !== false) {
        $opts['http']['content'] = $data;
        $opts['http']['header'] .= 'Content-Length: ' . strlen($data);
    }

    $context = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);

    preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);

    return [(int)$match[1], $result];
}

?>

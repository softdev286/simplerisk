<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../../includes/functions.php'));


    // If the extra is activated
    if (jira_extra() &&
        // Checking if input parameters are there
        !empty($_GET['token']) &&
        !empty($_GET['key']) &&
        // Validating issue key format
        preg_match('/^[A-Z][A-Z_0-9]+-[0-9][0-9]*$/', $_GET['key'])) {

        // Include Zend Escaper for HTML Output Encoding
        require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
        $escaper = new Zend\Escaper\Escaper('utf-8');

        // Include the language file
        require_once(language_file(true));

        // Include the Jira Extra
        require_once(realpath(__DIR__ . '/index.php'));

        // Validating token
        if (($jira_token_is_valid = jira_valid_webhook_token($_GET['token'])) && ($risk_id = get_risk_id_by_issue_key($_GET['key']))) {
            // Getting the info sent by webhook
            $body = file_get_contents('php://input');

            if ($body) {
                $body = json_decode($body, true);
// error_log("Body: " . json_encode($body));
                if ($body['webhookEvent'] == 'jira:issue_created') {
                    // Not implemented yet
                }
                elseif ($body['webhookEvent'] == 'jira:issue_deleted') {
                    // If the issue is deleted then remove the risk-issue association
                    jira_update_risk_issue_connection($risk_id, '', false);
                }
                elseif ($body['webhookEvent'] == 'jira:issue_updated' && isset($body['changelog']) && !empty($body['changelog']['items']) && is_array($body['changelog']['items'])) {
                    // Get the list of changed fields
                    $changed_fields = [];
                    foreach($body['changelog']['items'] as $change) {
                        $changed_fields[] = $change['fieldId'];
                    }
// error_log('callback - changed fields: ' . json_encode($changed_fields));
                    $JiraSynchronizedFields = json_decode(get_setting('JiraSynchronizedFields', '[]'), true);
                    $JiraFieldsToSynchronize = array_intersect($JiraSynchronizedFields, $changed_fields);
                    // Check if any of the synchronized fields changed
                    if ($JiraFieldsToSynchronize) {
                        //try {
                            jira_pull_changes($_GET['key'], $risk_id, $JiraFieldsToSynchronize);
                        //} catch (Exception $e) {
                        //    error_log('Caught exception: '.$e->getMessage()."\n");
                        //} finally {
                        //    //Could do some cleanup here if needed
                        //    //error_log("First finally.\n");
                        //}
                    }
                } else {
                    error_log($escaper->escapeHtml($lang['JiraWebhookBodyPostedIsInvalid']));
                }
            } else {
                error_log($escaper->escapeHtml($lang['JiraWebhookNoBodyPosted']));
            }
        } else {
            if (!$jira_token_is_valid)
                error_log(_lang('JiraWebhookAuthTokenIsInvalid', ['token' => $_GET['token']]));
            elseif (!$risk_id)
                error_log("Issue is not assigned to any risk: " . $_GET['key']);
        }
        
    }

?>

<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to send  *
 * email messages to users associated with the risks that are       *
 * entered into the system.  Call it once to enable the Extra and   *
 * then schedule it to run as a cron job to have it automatically   *
 * send email messages when risks are due for review.  We recommend *
 * scheduling on a monthly basis in order to keep communications to *
 * a reasonable level.                                              *
 ********************************************************************/

// Extra Version
define('NOTIFICATION_EXTRA_VERSION', '20191130-001');

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Include the functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/mail.php'));
require_once(language_file());
require_once(realpath(__DIR__ . '/upgrade.php'));

// Set the simplerisk timezone for any datetime functions
set_simplerisk_timezone();

// Upgrade extra database version
upgrade_notification_extra_database();

// If the extra is enabled
if (notification_extra())
{
    // And the extra is called from the command line
    if (PHP_SAPI === 'cli')
    {
        // Get the notification settings
        $configs = get_notification_settings();

        // If the risk cron is enabled to run
        if(check_available_cron($configs, "risk"))
        {
            // Run the automated notifications of past due and renewals
            auto_run_risk_notification();
        }

        // If the audit cron is enabled to run
        if(check_available_cron($configs, "audit"))
        {
            // Run the automated notifications of audits
            auto_run_audit_notification();
        }
        
        // If the mitigation cron is enabled to run
        if(check_available_cron($configs, "mitigation"))
        {
            // Run the automated notifications of planned mitigations
            auto_run_mitigation_notification();
        }

        // If the non mitigation cron is enabled to run
        if(check_available_cron($configs, "non-mitigation"))
        {
            // Run the automated notifications of non mitigated risks
            auto_run_non_mitigation_notification();
        }

        // If the unapproved policy and control exceptions cron is enabled to run
        if(check_available_cron($configs, "unapproved_policy_control_exceptions"))
        {
            // Run the automated notifications of unreviewd/past due policy and control exceptions 
            auto_run_policy_control_excption_review_notification();
        }
    }
    // If the auto risk notification was run manually
    else if (isset($_POST) && isset($_POST['auto_run_risk_now']))
    {
        // Run the automated notifications of risk
        auto_run_risk_notification();

        // Create an alert
        set_alert(true, "good", "The automated notification of unreviewed and past due risks has been sent.");
    }
    // If the auto audit notification was run manually
    else if (isset($_POST) && isset($_POST['auto_run_audit_now']))
    {
        // Run the automated notifications of audits
        auto_run_audit_notification();

        // Create an alert
        set_alert(true, "good", "The automated notification of audits has been sent.");
    }
    // If the auto mitigation notification was run manually
    else if (isset($_POST) && isset($_POST['auto_run_mitigation_now']))
    {
        // Run the automated notifications of mitigations
        auto_run_mitigation_notification();

        // Create an alert
        set_alert(true, "good", "The automated notification of mitigations has been sent.");
    }
    // If the auto mitigation notification was run manually
    else if (isset($_POST) && isset($_POST['non_mitigation_auto_run_risk_now']))
    {
        // Run the automated notifications of risks without mitigation
        auto_run_non_mitigation_notification();

        // Create an alert
        set_alert(true, "good", "The automated notification of unreviewed and past due risks has been sent.");
    }
    // If the auto policy and control exception notification was run manually
    else if (isset($_POST) && isset($_POST['policy_control_exception_review_run_now']))
    {
        // Run the automated notifications of unreviewd/past due policy and control exceptions 
        auto_run_policy_control_excption_review_notification();

        // Create an alert
        set_alert(true, "good", "The automated notification of unreviewed and past due risks has been sent.");
    }
}

/***************************************
 * FUNCTION: ENABLE NOTIFICATION EXTRA *
 ***************************************/
function enable_notification_extra()
{
    prevent_extra_double_submit("notification", true);

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'notifications', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
    $stmt->execute();

    // Add default values
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'VERBOSE', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("DELETE FROM `settings` WHERE `name` = 'FROM_NAME'");
    $stmt->execute();
    
    $stmt = $db->prepare("DELETE FROM `settings` WHERE `name` = 'FROM_EMAIL'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_SUBMITTER', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_OWNER', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_OWNERS_MANAGER', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_TEAM', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_NEW_RISK', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_RISK_UPDATE', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_NEW_MITIGATION', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_MITIGATION_UPDATE', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_REVIEW', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_CLOSE', `value` = 'true'");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_RISK_COMMENT', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_AUDIT_COMMENT', `value` = 'true'");
    $stmt->execute();    

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_AUDIT_STATUS_CHANGE', `value` = 'true'");
    $stmt->execute();    
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_PERIOD', `value` = ''");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_HOUR', `value` = '0'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_MINUTE', `value` = '0'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_DAY_OF_WEEK', `value` = '0'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_DATE', `value` = '1'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_MONTH', `value` = '1'");
    $stmt->execute();
    
    // Create a table for history of Cron Job
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `cron_history` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `process_id` varchar(100) DEFAULT NULL,
            `sent_at` datetime NOT NULL,
            `type` varchar(100) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;    
    ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_SUBMITTER', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_OWNER', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_OWNERS_MANAGER', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_TEAM', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_REVIEWERS', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_RISK_NOTIFY_SUBMITTER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_UPDATE_NOTIFY_SUBMITTER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_MITIGATION_NOTIFY_SUBMITTER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'MITIGATION_UPDATE_NOTIFY_SUBMITTER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_REVIEW_NOTIFY_SUBMITTER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_CLOSE_NOTIFY_SUBMITTER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_COMMENT_NOTIFY_SUBMITTER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_RISK_NOTIFY_OWNER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_UPDATE_NOTIFY_OWNER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_MITIGATION_NOTIFY_OWNER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'MITIGATION_UPDATE_NOTIFY_OWNER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_REVIEW_NOTIFY_OWNER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_CLOSE_NOTIFY_OWNER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_COMMENT_NOTIFY_OWNER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_RISK_NOTIFY_OWNERS_MANAGER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_UPDATE_NOTIFY_OWNERS_MANAGER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_MITIGATION_NOTIFY_OWNERS_MANAGER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'MITIGATION_UPDATE_NOTIFY_OWNERS_MANAGER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_REVIEW_NOTIFY_OWNERS_MANAGER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_CLOSE_NOTIFY_OWNERS_MANAGER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_COMMENT_NOTIFY_OWNERS_MANAGER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_RISK_NOTIFY_TEAM', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_UPDATE_NOTIFY_TEAM', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_MITIGATION_NOTIFY_TEAM', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'MITIGATION_UPDATE_NOTIFY_TEAM', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_REVIEW_NOTIFY_TEAM', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_CLOSE_NOTIFY_TEAM', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_COMMENT_NOTIFY_TEAM', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_RISK_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_MITIGATION_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'MITIGATION_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_REVIEW_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_CLOSE_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_HOUR', `value` = '0'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_MINUTE', `value` = '0'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_1', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_1_VALUE', `value` = '14'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_2', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_2_VALUE', `value` = '7'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_3', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_3_VALUE', `value` = '2'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_DUE_EMAIL', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL_VALUE', `value` = '1'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_TESTER_AUDITS', `value` = 'true';");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_CONTROL_OWNER_AUDITS', `value` = 'false';");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_AUDITS', `value` = 'false';");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_HOUR', `value` = '0'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_MINUTE', `value` = '0'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_1', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_1_VALUE', `value` = '14'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_2', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_2_VALUE', `value` = '7'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_3', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_3_VALUE', `value` = '2'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_DUE_EMAIL', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL', `value` = 'false'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL_VALUE', `value` = '1'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUDIT_COMMENT_NOTIFY_TESTER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUDIT_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUDIT_COMMENT_NOTIFY_CONTROL_OWNER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUDIT_STATUS_CHANGE_NOTIFY_TESTER', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUDIT_STATUS_CHANGE_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUDIT_STATUS_CHANGE_NOTIFY_CONTROL_OWNER', `value` = 'true'");
    $stmt->execute();

    // Import an existing configuration file and remove it
    import_and_remove_notification_config_file();
    
    // Audit log entry for Extra turned on
    $message = "Notification Extra was toggled on by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);
}

/****************************************
 * FUNCTION: DISABLE NOTIFICATION EXTRA *
 ****************************************/
function disable_notification_extra()
{
    prevent_extra_double_submit("notification", false);

    // Open the database connection
    $db = db_open();

    // Drop a cron table
    $stmt = $db->prepare("DROP TABLE `cron_history`;");
    $stmt->execute();

    // Query the database
    $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'notifications'");
    $stmt->execute();
    
    // Audit log entry for Extra turned off
    $message = "Notification Extra was toggled off by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: NOTIFICATION TYPE ENABLED *
 ***************************************/
function notification_type_enabled($when_to_notify, $who_to_notify)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Check the "who_to_notify" first
    switch($who_to_notify)
    {
        case "submitter":
            if ($when_to_notify == "new_risk" && $NEW_RISK_NOTIFY_SUBMITTER == "true")
            {
                $enabled = true;
            }
            else if ($when_to_notify == "risk_update" && $RISK_UPDATE_NOTIFY_SUBMITTER == "true")
            {
                $enabled = true;
            }
            else if ($when_to_notify == "new_mitigation" && $NEW_MITIGATION_NOTIFY_SUBMITTER == "true")
            {
                $enabled = true;
            }
            else if ($when_to_notify == "mitigation_update" && $MITIGATION_UPDATE_NOTIFY_SUBMITTER == "true")
            {
                $enabled = true;
            }
            else if ($when_to_notify == "risk_review" && $RISK_REVIEW_NOTIFY_SUBMITTER == "true")
            {
                $enabled = true;
            }
            else if ($when_to_notify == "risk_comment" && $RISK_COMMENT_NOTIFY_SUBMITTER == "true")
            {
                $enabled = true;
            }
            else if ($when_to_notify == "risk_close" && $RISK_CLOSE_NOTIFY_SUBMITTER == "true")
            {
                $enabled = true;
            }
            else $enabled = false;
            break;
        case "owner":
            if ($when_to_notify == "new_risk" && $NEW_RISK_NOTIFY_OWNER == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_update" && $RISK_UPDATE_NOTIFY_OWNER == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "new_mitigation" && $NEW_MITIGATION_NOTIFY_OWNER == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "mitigation_update" && $MITIGATION_UPDATE_NOTIFY_OWNER == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_review" && $RISK_REVIEW_NOTIFY_OWNER == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_comment" && $RISK_COMMENT_NOTIFY_OWNER == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_close" && $RISK_CLOSE_NOTIFY_OWNER == "true")
            {
                    $enabled = true;
            }
            else $enabled = false;
            break;
        case "owners_manager":
            if ($when_to_notify == "new_risk" && $NEW_RISK_NOTIFY_OWNERS_MANAGER == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_update" && $RISK_UPDATE_NOTIFY_OWNERS_MANAGER == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "new_mitigation" && $NEW_MITIGATION_NOTIFY_OWNERS_MANAGER == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "mitigation_update" && $MITIGATION_UPDATE_NOTIFY_OWNERS_MANAGER == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_review" && $RISK_REVIEW_NOTIFY_OWNERS_MANAGER == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_comment" && $RISK_COMMENT_NOTIFY_OWNERS_MANAGER == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_close" && $RISK_CLOSE_NOTIFY_OWNERS_MANAGER == "true")
            {
                    $enabled = true;
            }
            else $enabled = false;
            break;
        case "team":
            if ($when_to_notify == "new_risk" && $NEW_RISK_NOTIFY_TEAM == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_update" && $RISK_UPDATE_NOTIFY_TEAM == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "new_mitigation" && $NEW_MITIGATION_NOTIFY_TEAM == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "mitigation_update" && $MITIGATION_UPDATE_NOTIFY_TEAM == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_review" && $RISK_REVIEW_NOTIFY_TEAM == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_comment" && $RISK_COMMENT_NOTIFY_TEAM == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_close" && $RISK_CLOSE_NOTIFY_TEAM == "true")
            {
                    $enabled = true;
            }
            else $enabled = false;
            break;
        case "tester":
            if ($when_to_notify == "audit_comment" && $AUDIT_COMMENT_NOTIFY_TESTER == "true")
            {
                    $enabled = true;
            }            
            else if ($when_to_notify == "audit_status_change" && $AUDIT_STATUS_CHANGE_NOTIFY_TESTER == "true")
            {
                    $enabled = true;
            }            
            else $enabled = false;
            break;            
        case "additional_stakeholders":
            if ($when_to_notify == "new_risk" && $NEW_RISK_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_update" && $RISK_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "new_mitigation" && $NEW_MITIGATION_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "mitigation_update" && $MITIGATION_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_review" && $RISK_REVIEW_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_comment" && $RISK_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "risk_close" && $RISK_CLOSE_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true")
            {
                    $enabled = true;
            }
            else if ($when_to_notify == "audit_comment" && $AUDIT_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true")
            {
                    $enabled = true;
            }            
            else if ($when_to_notify == "audit_status_change" && $AUDIT_STATUS_CHANGE_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true")
            {
                    $enabled = true;
            }            
            else $enabled = false;
            break;
        case "control_owner":
            if ($when_to_notify == "audit_comment" && $AUDIT_COMMENT_NOTIFY_CONTROL_OWNER == "true")
            {
                $enabled = true;
            }
            else if ($when_to_notify == "audit_status_change" && $AUDIT_STATUS_CHANGE_NOTIFY_CONTROL_OWNER == "true")
            {
                $enabled = true;
            }
            else $enabled = false;
            break;
        default:
                $enabled = false;
            break;
    }

    // Return the enabled value
    return $enabled;
}

/*********************************
 * FUNCTION: GET USERS TO NOTIFY *
 *********************************/
function get_users_to_notify($risk_id, $when_to_notify = null)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }
    
    // Sends nothing emails for closed risks
    if(check_closed_risk_by_id($risk_id))
    {
        return [];
    }

    // Initialize the email array
    $email_array = array();

    if (team_separation_extra()) {
        // If we're allowing everyone to see a risk if it has no teams
        // and the current risk has no teams assigned
        // we treat this as the separation is turned off(for this risk only)
        if (get_setting('allow_all_to_risk_noassign_team') && !get_risk_teams($risk_id))
            $separation = false;
        else {
            require_once(realpath(__DIR__ . '/../separation/index.php'));
            $separation = true;
        }
    } else
        $separation = false;

    // If we are supposed to notify the submitter
    if (notification_type_enabled($when_to_notify, "submitter") &&
        (!$separation || get_setting('allow_submitter_to_risk'))) {

        $submitter_email = get_submitter_email($risk_id);

        // If the risk has a submitter
        if (!empty($submitter_email))
        {
            // Add the owner to the email array
            $row['name'] = $submitter_email[0]['name'];
            $row['email'] = $submitter_email[0]['email'];
            $email_array[] = $row;
        }
    }

    // If we are supposed to notify the owner
    if (notification_type_enabled($when_to_notify, "owner") &&
        (!$separation || get_setting('allow_owner_to_risk'))) {

        $owner_email = get_owner_email($risk_id);

        // If the risk has an owner
        if (!empty($owner_email))
        {
            // Add the owner to the email array
            $row['name'] = $owner_email[0]['name'];
            $row['email'] = $owner_email[0]['email'];
            $email_array[] = $row;
        }
    }

    // If we are supposed to notify the owner's manager
    if (notification_type_enabled($when_to_notify, "owners_manager") &&
        (!$separation || get_setting('allow_ownermanager_to_risk'))) {

        $owners_manager_email = get_owners_manager_email($risk_id);

        // If the risk has an owner's manager
        if (!empty($owners_manager_email))
        {
            // Add the owner's manager to the email array
            $row['name'] = $owners_manager_email[0]['name'];
            $row['email'] = $owners_manager_email[0]['email'];
            $email_array[] = $row;
        }
    }

    // If we are supposed to notify the team
    if (notification_type_enabled($when_to_notify, "team") &&
        (!$separation || get_setting('allow_team_member_to_risk'))) {

        $team_email = get_team_email($risk_id);

        // If the risk has a team
        if (!empty($team_email))
        {
            // Add the team to the email array
            foreach ($team_email as $email)
            {
                $row['name'] = $email['name'];
                $row['email'] = $email['email'];
                $email_array[] = $row;
            }
        }
    }

    // If we are supposed to notify the team
    if (notification_type_enabled($when_to_notify, "additional_stakeholders") &&
        (!$separation || get_setting('allow_stakeholder_to_risk'))) {

        $emails = get_additional_stakeholder_emails($risk_id);

        // Add the additional stakeholders to the email array
        foreach ($emails as $email)
        {
            $row['name'] = $email['name'];
            $row['email'] = $email['email'];
            $email_array[] = $row;
        }
    }

    // Create an array of unique combined e-mails
    $all_emails = array();
    foreach($email_array as $row){
        $all_emails[$row['email']] = $row;
    }

    // Write the debug log
    write_debug_log("Risk ID is ".$risk_id." and emails to send to are:\n" . print_r($all_emails, true));

    // Return the array of unique combined e-mails
    return $all_emails;
}

/*********************************
 * FUNCTION: GET SUBMITTER EMAIL *
 *********************************/
function get_submitter_email($risk_id)
{
    // Subtract 1000 from id
    $risk_id = (int)$risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT a.id, b.name, b.email FROM `risks` a JOIN `user` b ON a.submitted_by = b.value WHERE a.id = :risk_id AND b.enabled = 1");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/*****************************
 * FUNCTION: GET OWNER EMAIL *
 *****************************/
function get_owner_email($risk_id)
{
    // Subtract 1000 from id
    $risk_id = (int)$risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT a.id, b.name, b.email FROM `risks` a JOIN `user` b ON a.owner = b.value WHERE a.id = :risk_id AND b.enabled = 1");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/**************************************
 * FUNCTION: GET OWNERS MANAGER EMAIL *
 **************************************/
function get_owners_manager_email($risk_id)
{
    // Subtract 1000 from id
    $risk_id = (int)$risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT a.id, b.name, b.email FROM `risks` a JOIN `user` b ON a.manager = b.value WHERE a.id = :risk_id AND b.enabled = 1");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/****************************
 * FUNCTION: GET TEAM EMAIL *
 ****************************/
function get_team_email($risk_id)
{
    // Subtract 1000 from id
    $risk_id = (int)$risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Get the team for the risk
    $stmt = $db->prepare("SELECT team FROM `risks` WHERE `id` = :risk_id");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();

    $team = "%:" . $array['team'] . ":%";

    $stmt = $db->prepare("SELECT name, email FROM `user` WHERE (teams LIKE :team OR teams = 'all') AND enabled = 1");
    $stmt->bindParam(":team", $team, PDO::PARAM_STR);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/************************************************
 * FUNCTION: GET ADDITIONAL STAKEHOLDER EMAILS *
 ************************************************/
function get_additional_stakeholder_emails($risk_id)
{
    // Subtract 1000 from id
    $risk_id = (int)$risk_id - 1000;

    // Open the database connection
    $db = db_open();

    // Get the additional_stakeholders for the risk
    $stmt = $db->prepare("SELECT additional_stakeholders FROM `risks` WHERE `id` = :risk_id");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $obj = $stmt->fetchObject();
    $stakeholders = explode(",", $obj->additional_stakeholders);
    
    if(!$stakeholders){
        return array();
    }
    
    $keys   = array();
    $values = array();
    
    foreach($stakeholders as $index => $stakeholder){
        $keys[] = ":".$index;
        $values[":".$index] = $stakeholder;
    }
    
    $stmt = $db->prepare("SELECT name, email FROM `user` WHERE value in (". implode(",", $keys) .")");
    foreach($keys as $key){
        $stmt->bindParam($key, $values[$key], PDO::PARAM_INT);
    }
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/***************************
 * FUNCTION: PREPARE EMAIL *
 ***************************/
function prepare_email($risk_id, $subject, $message, $when_to_notify = null)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Create the full HTML message
    $full_message = "<html><body>\n";
    $full_message .= "<p>Hello,</p>\n";
    $full_message .= $message;
    $full_message .= "<p>This is an automated message and responses will be ignored or rejected.</p>\n";
    $full_message .= "</body></html>\n";

    // Get the emails to send to
    $users = get_users_to_notify($risk_id, $when_to_notify);

    // For each user
    foreach ($users as $user)
    {
        $name = $user['name'];
        $email = $user['email'];

        // Write the debug log
        write_debug_log("Name: ".$name);
        write_debug_log("Email: ".$email);
        write_debug_log("Subject: ".$subject);
        write_debug_log("Full Message: ".$full_message);

        // Send the e-mail
        send_email($name, $email, $subject, $full_message);
    }

    // Wait a second before sending another e-mail
    sleep(1);
}

/********************************************
 * FUNCTION: GET TEST AUDIT USERS TO NOTIFY *
 ********************************************/
function get_test_audit_users_to_notify($test_audit, $when_to_notify = null)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Initialize the email array
    $email_array = array();

    if (team_separation_extra()) {
        // If we're allowing everyone to see the audits we treat the separation as it is turned off
        if (get_setting('allow_everyone_to_see_test_and_audit'))
            $separation = false;
        else {
            require_once(realpath(__DIR__ . '/../separation/index.php'));
            $separation = true;
        }
    } else
        $separation = false;

    // If we are supposed to notify the tester
    if (notification_type_enabled($when_to_notify, "tester") &&
        (!$separation || get_setting('allow_tester_to_see_test_and_audit'))) {

        $tester_email = get_user_email($test_audit['tester']);

        // If the test has a tester
        if (!empty($tester_email))
        {
            // Add the tester to the email array
            $row['name'] = $tester_email[0]['name'];
            $row['email'] = $tester_email[0]['email'];
            $email_array[] = $row;
        }
    }

    // If we are supposed to notify the additional steakholders
    if (notification_type_enabled($when_to_notify, "additional_stakeholders") &&
        (!$separation || get_setting('allow_stakeholders_to_see_test_and_audit'))) {

        $emails = get_test_audit_additional_stakeholder_emails($test_audit['test_id']);

        // Add the additional stakeholders to the email array
        foreach ($emails as $email)
        {
            $row['name'] = $email['name'];
            $row['email'] = $email['email'];
            $email_array[] = $row;
        }
    }

    // If we are supposed to notify the control owner
    if (notification_type_enabled($when_to_notify, "control_owner") &&
        (!$separation || get_setting('allow_control_owner_to_see_test_and_audit'))) {

        $control_owner_email = get_user_email($test_audit['control_owner']);

        // If the test has a control owner
        if (!empty($control_owner_email))
        {
            // Add the control owner to the email array
            $row['name'] = $control_owner_email[0]['name'];
            $row['email'] = $control_owner_email[0]['email'];
            $email_array[] = $row;
        }
    }

    // Create an array of unique combined e-mails
    $all_emails = array();
    foreach($email_array as $row){
        $all_emails[$row['email']] = $row;
    }

    // Write the debug log
    write_debug_log("Audit Test ID is ".$test_audit['id']." and emails to send to are:\n" . print_r($all_emails, true));

    // Return the array of unique combined e-mails
    return $all_emails;
}

/******************************
 * FUNCTION: GET USER'S EMAIL *
 ******************************/
function get_user_email($user_id)
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT name, email FROM `user` WHERE value = :user_id AND enabled = 1;");
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/************************************************************
 * FUNCTION: GET ADDITIONAL STAKEHOLDER EMAILS OF TEST AUDIT*
 ************************************************************/
function get_test_audit_additional_stakeholder_emails($test_id)
{
    // Open the database connection
    $db = db_open();

    // Get the additional_stakeholders for the risk
    $stmt = $db->prepare("SELECT additional_stakeholders FROM `framework_control_tests` WHERE `id` = :test_id");
    $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $obj = $stmt->fetchObject();
    $stakeholders = explode(",", $obj->additional_stakeholders);
    
    if(!$stakeholders){
        return array();
    }
    
    $keys   = array();
    $values = array();
    
    foreach($stakeholders as $index => $stakeholder){
        $keys[] = ":".$index;
        $values[":".$index] = $stakeholder;
    }
    
    $stmt = $db->prepare("SELECT name, email FROM `user` WHERE value in (". implode(",", $keys) .")");
    foreach($keys as $key){
        $stmt->bindParam($key, $values[$key], PDO::PARAM_INT);
    }
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/*************************************
 * FUNCTION: ADDITIONAL RISK DETAILS *
 *************************************/
function additional_risk_details($risk_id)
{
    global $lang;
    global $escaper;

    // Get the details of the risk
    $risk = get_risk_by_id($risk_id);

    // If the risk was found use the values for the risk
    if (count($risk) != 0)
    {
        $status = $risk[0]['status'];
        $subject = try_decrypt($risk[0]['subject']);
        $calculated_risk = $risk[0]['calculated_risk'];
        $reference_id = $risk[0]['reference_id'];
        $regulation = $risk[0]['regulation'];
        $control_number = $risk[0]['control_number'];
        $location = $risk[0]['location'];
        $source = $risk[0]['source'];
        $category = $risk[0]['category'];
        $team = $risk[0]['team'];
        $technology = $risk[0]['technology'];
        $owner = $risk[0]['owner'];
        $manager = $risk[0]['manager'];
        $assessment = try_decrypt($risk[0]['assessment']);
        $notes = try_decrypt($risk[0]['notes']);
        $tags = $risk[0]['risk_tags'];

        $message = "<p><b><u>Risk Details:</u></b></p>\n";
        $message .= "<table rules=\"all\" style=\"border-color: #666;\" cellpadding=\"10\">\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Status']) .":</strong> </td><td>" . $escaper->escapeHtml($status) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Subject']) .":</strong> </td><td>" . $escaper->escapeHtml($subject) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['CalculatedRisk']) .":</strong> </td><td>" . $escaper->escapeHtml($calculated_risk) . " (". $escaper->escapeHtml(get_risk_level_name($calculated_risk)) . ")</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ExternalReferenceId']) .":</strong> </td><td>" . $escaper->escapeHtml($reference_id) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ControlRegulation']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("frameworks", $regulation)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ControlNumber']) .":</strong> </td><td>" . $escaper->escapeHtml($control_number) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['SiteLocation']) .":</strong> </td><td>" . $escaper->escapeHtml(get_names_by_multi_values("location", $location, false, "; ")) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['RiskSource']) . ":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("source", $source)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Category']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("category", $category)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Team']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("team", $team)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Technology']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("technology", $technology)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Owner']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("user", $owner)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['OwnersManager']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("user", $manager)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['RiskAssessment']) .":</strong> </td><td>" . $escaper->escapeHtml($assessment) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Tags']) .":</strong> </td><td>" . $escaper->escapeHtml($tags) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['AdditionalNotes']) .":</strong> </td><td>" . $escaper->escapeHtml($notes) . "</td></tr>\n";
        $message .= "</table>\n";
    }
    else $message = "";

    // Return the message
    return $message;
}

/*******************************************
 * FUNCTION: ADDITIONAL MITIGATION DETAILS *
 *******************************************/
function additional_mitigation_details($risk_id)
{
    global $lang;
    global $escaper;

    // Get the details of the mitigation
    $mitigation = get_mitigation_by_id($risk_id);

    // If the mitigation was found use the values for the mitigation
    if (count($mitigation) != 0)
    {
        $mitigation_date = $mitigation[0]['submission_date'];
        $planning_strategy = $mitigation[0]['planning_strategy'];
        $mitigation_effort = $mitigation[0]['mitigation_effort'];
        $mitigation_cost = $mitigation[0]['mitigation_cost'];
        $mitigation_owner = $mitigation[0]['mitigation_owner'];
        $mitigation_team = $mitigation[0]['mitigation_team'];
        $current_solution = try_decrypt($mitigation[0]['current_solution']);
        $security_requirements = try_decrypt($mitigation[0]['security_requirements']);
        $security_recommendations = try_decrypt($mitigation[0]['security_recommendations']);

        $message = "<p><b><u>Mitigation Details:</u></b></p>\n";
        $message .= "<table rules=\"all\" style=\"border-color: #666;\" cellpadding=\"10\">\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['MitigationDate']) .":</strong> </td><td>" . $escaper->escapeHtml($mitigation_date) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['PlanningStrategy']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("planning_strategy", $planning_strategy)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['MitigationEffort']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("mitigation_effort", $mitigation_effort)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['MitigationCost']) .":</strong> </td><td>" . $escaper->escapeHtml(get_asset_value_by_id($mitigation_cost)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['MitigationOwner']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("user", $mitigation_owner)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['MitigationTeam']) .":</strong> </td><td>" . $escaper->escapeHtml(get_names_by_multi_values("team", $mitigation_team)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['CurrentSolution']) .":</strong> </td><td>" . $escaper->escapeHtml($current_solution) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['SecurityRequirements']) .":</strong> </td><td>" . $escaper->escapeHtml($security_requirements) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['SecurityRecommendations']) .":</strong> </td><td>" . $escaper->escapeHtml($security_recommendations) . "</td></tr>\n";
        $message .= "</table>\n";
    }
    else $message = "";

    // Return the message
    return $message;
}

/***************************************
 * FUNCTION: ADDITIONAL REVIEW DETAILS *
 ***************************************/
function additional_review_details($risk_id)
{
    global $lang;
    global $escaper;

    // Get the details of the review
    $review = get_review_by_id($risk_id);

    // If the review was found use the values for the review
    if (count($review) != 0)
    {
        $review_date = $review[0]['submission_date'];
        $reviewer = $review[0]['reviewer'];
        $mgmt_review = $review[0]['review'];
        $next_step = $review[0]['next_step'];
        $comments = try_decrypt($review[0]['comments']);

        $message = "<p><b><u>Review Details:</u></b></p>\n";
        $message .= "<table rules=\"all\" style=\"border-color: #666;\" cellpadding=\"10\">\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ReviewDate']) .":</strong> </td><td>" . $escaper->escapeHtml($review_date) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Reviewer']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("user", $reviewer)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Review']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("review", $mgmt_review)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['NextStep']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("next_step", $next_step)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Comments']) .":</strong> </td><td>" . $escaper->escapeHtml($comments) . "</td></tr>\n";
        $message .= "</table>\n";
    }
    else $message = "";

    // Return the message
    return $message;
}

/**************************************
 * FUNCTION: ADDITIONAL CLOSE DETAILS *
 **************************************/
function additional_close_details($risk_id)
{
    global $lang;
    global $escaper;

    // Get the details of the close
    $close = get_close_by_id($risk_id);

    // If the closure was found use the values for the closure
    if (count($close) != 0)
    {
        $user = $close[0]['user_id'];
        $closure_date = $close[0]['closure_date'];
        $close_reason = $close[0]['close_reason'];
        $note = $close[0]['note'];

        $message = "<p><b><u>Close Details:</u></b></p>\n";
        $message .= "<table rules=\"all\" style=\"border-color: #666;\" cellpadding=\"10\">\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['DateClosed']) .":</strong> </td><td>" . $escaper->escapeHtml($closure_date) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ClosedBy']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("user", $user)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['CloseReason']) .":</strong> </td><td>" . $escaper->escapeHtml(get_name_by_value("close_reason", $close_reason)) . "</td></tr>\n";
        $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Comments']) .":</strong> </td><td>" . $escaper->escapeHtml($note) . "</td></tr>\n";
        $message .= "</table>\n";
            
    }
    else $message = "";

        // Return the message
    return $message;
}

/***************************
 * FUNCTION: PREPARE TEST AUDIT EMAIL *
 ***************************/
function prepare_test_audit_email($test_audit, $subject, $message, $when_to_notify = null)
{
        
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Create the full HTML message
    $full_message = "<html><body>\n";
    $full_message .= "<p>Hello,</p>\n";
    $full_message .= $message;
    $full_message .= "<p>This is an automated message and responses will be ignored or rejected.</p>\n";
    $full_message .= "</body></html>\n";

    // Get the emails to send to
    $users = get_test_audit_users_to_notify($test_audit, $when_to_notify);

    // For each user
    foreach ($users as $user)
    {
        $name = $user['name'];
        $email = $user['email'];
                
        // Write the debug log
        write_debug_log("Name: ".$name);
        write_debug_log("Email: ".$email);
        write_debug_log("Subject: ".$subject);
        write_debug_log("Full Message: ".$full_message);

        // Send the e-mail
        send_email($name, $email, $subject, $full_message);
    }

    // Wait a second before sending another e-mail
    sleep(1);
}

/*************************************
 * FUNCTION: ADDITIONAL AUDIT TEST DETAILS *
 *************************************/
function additional_test_audit_details($test_audit)
{
    global $lang;
    global $escaper;
   
    $name = $test_audit['name'];
    $tester_name = $test_audit['tester_name'];
    $status = get_name_by_value("test_status", $test_audit['status'], $lang['None']);
    $test_frequency = $test_audit['test_frequency'] . " " . $lang['days'];
    $last_date = $test_audit['last_date'];
    $next_date = $test_audit['next_date'];
    $objective = $test_audit['objective'];
    $test_steps = $test_audit['test_steps'];
    $approximate_time = $test_audit['approximate_time'] . " " . $lang['minutes'];
    $expected_results = $test_audit['expected_results'];
    $framework_control_id = $test_audit['framework_control_id'];
//    $desired_frequency = $test_audit['desired_frequency'];
    $created_at = $test_audit['created_at'];
    $control_name = $test_audit['control_name'];
    $control_owner = get_name_by_value("user", $test_audit['control_owner']);
    $additional_stakeholders = $test_audit['additional_stakeholders'] ? get_stakeholder_names($test_audit['additional_stakeholders']) : "";
    $framework_name = $test_audit['framework_name'];
    $test_result = $test_audit['test_result'];
    $summary = $test_audit['summary'];
    $test_date = $test_audit['test_date'];
    $submitted_by = get_name_by_value("user", $test_audit['submitted_by']);
    $submission_date = $test_audit['submission_date'];    
    
    
    $message = "<p><b><u>Audit Details:</u></b></p>\n";
    $message .= "<table rules=\"all\" style=\"border-color: #666;\" cellpadding=\"10\">\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['TestName']) .":</strong> </td><td>" . $escaper->escapeHtml($name) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Tester']) .":</strong> </td><td>" . $escaper->escapeHtml($tester_name) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['AuditStatus']) .":</strong> </td><td>" . $escaper->escapeHtml($status) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['TestFrequency']) .":</strong> </td><td>" . $escaper->escapeHtml($test_frequency) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['LastAuditDate']) .":</strong> </td><td>" . $escaper->escapeHtml($last_date) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['NextAuditDate']) .":</strong> </td><td>" . $escaper->escapeHtml($next_date) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ApproximateTime']) .":</strong> </td><td>" . $escaper->escapeHtml($approximate_time) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ExpectedResults']) .":</strong> </td><td>" . $escaper->escapeHtml($expected_results) . "</td></tr>\n";
//    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['DesiredFrequency']) .":</strong> </td><td>" . $escaper->escapeHtml($desired_frequency) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['CreatedDate']) .":</strong> </td><td>" . $escaper->escapeHtml($created_at) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ControlName']) .":</strong> </td><td>" . $escaper->escapeHtml($control_name) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['ControlOwner']) .":</strong> </td><td>" . $escaper->escapeHtml($control_owner) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['AdditionalStakeholders']) .":</strong> </td><td>" . $escaper->escapeHtml($additional_stakeholders) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['FrameworkName']) .":</strong> </td><td>" . $escaper->escapeHtml($framework_name) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Objective']) .":</strong> </td><td>" . $escaper->escapeHtml($objective) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['TestSteps']) .":</strong> </td><td>" . $escaper->escapeHtml($test_steps) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['TestResult']) .":</strong> </td><td>" . $escaper->escapeHtml($test_result) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['Summary']) .":</strong> </td><td>" . $escaper->escapeHtml($summary) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['TestDate']) .":</strong> </td><td>" . $escaper->escapeHtml($test_date) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['SubmittedBy']) .":</strong> </td><td>" . $escaper->escapeHtml($submitted_by) . "</td></tr>\n";
    $message .= "<tr><td><strong>". $escaper->escapeHtml($lang['SubmissionDate']) .":</strong> </td><td>" . $escaper->escapeHtml($submission_date) . "</td></tr>\n";

    $message .= "</table>\n";    

    // Return the message
    return $message;
}

/*****************************
 * FUNCTION: NOTIFY NEW RISK *
 *****************************/
function notify_new_risk($id, $subject)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we are supposed to notify on a new risk
    if ($NOTIFY_ON_NEW_RISK == "true")
    {
        global $escaper;
        global $lang;
        
        // Add 1000 to get the risk ID
        $risk_id = (int)$id + 1000;

        // Get the users information
        $user_info = get_user_by_id($_SESSION['uid']);
        $name = $user_info['name'];

        // Create the message
        $email_subject = "New Risk Submitted";
        $email_message = "<p>A new risk has been submitted by " . $escaper->escapeHtml($name) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.  The risk has been recorded as risk ID " . $escaper->escapeHtml($risk_id) .".</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true")
        {
            $email_message .= additional_risk_details($risk_id);
        }
        
        // If sinplerisk_base_url is undefined, set the default base url
        if(empty($simplerisk_base_url))
        {
            $simplerisk_base_url = $_SESSION['base_url'];
        }

        $email_message .= "<p><b><u>" . $escaper->escapeHtml($lang['Actions']) . ":</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "&type=1&action=editmitigation\">Plan a Mitigation</a></li>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "&type=2&action=editreview\">Perform a Review</a></li>\n";
        $email_message .= "</ul>\n";

        // Send the e-mail
        prepare_email($risk_id, $email_subject, $email_message, "new_risk");
    }
}

/********************************
 * FUNCTION: NOTIFY RISK UPDATE *
 ********************************/
function notify_risk_update($id)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
            // Set the name value pair as a variable
            ${$config['name']} = $config['value'];
    }

    // If we are supposed to notify on a risk update
    if ($NOTIFY_ON_RISK_UPDATE == "true")
    {
        global $escaper;
        global $lang;

        // Add 1000 to get the risk ID
        $risk_id = (int)$id + 1000;

        // Get the users information
        //$user_info = get_user_by_id($_SESSION['uid']);
        //$name = $user_info['name'];
$name = 'asdasd';
        // Create the message
        $email_subject = "Risk ID " . $escaper->escapeHtml($risk_id) . " Updated";
        $email_message = "<p>Risk ID " . $escaper->escapeHtml($risk_id) . " was updated by " . $escaper->escapeHtml($name) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true")
        {
            $email_message .= additional_risk_details($risk_id);
        }

        $email_message .= "<p><b><u>" . $escaper->escapeHtml($lang['Actions']) . ":</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "&type=2&action=editreview\">Perform a Review</a></li>\n";
        $email_message .= "</ul>\n";

        // Send the e-mail
        prepare_email($risk_id, $email_subject, $email_message, "risk_update");
    }
}

/***********************************
 * FUNCTION: NOTIFY NEW MITIGATION *
 ***********************************/
function notify_new_mitigation($id)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we are supposed to notify on a new mitigation
    if ($NOTIFY_ON_NEW_MITIGATION == "true")
    {
        global $escaper;
        global $lang;

        // Add 1000 to get the risk ID
        $risk_id = (int)$id + 1000;

        // Get the users information
        $user_info = get_user_by_id($_SESSION['uid']);
        $name = $user_info['name'];

        // Create the message
        $email_subject = "Risk Mitigation Submitted for Risk ID " . $escaper->escapeHtml($risk_id);
        $email_message = "<p>A mitigation was submitted by " . $escaper->escapeHtml($name) . " for risk ID ". $escaper->escapeHtml($risk_id) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true")
        {
            $email_message .= additional_mitigation_details($risk_id);
            $email_message .= additional_risk_details($risk_id);
        }

        $email_message .= "<p><b><u>" . $escaper->escapeHtml($lang['Actions']) . ":</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "&type=2&action=editreview\">Perform a Review</a></li>\n";
        $email_message .= "</ul>\n";

        // Send the e-mail
        prepare_email($risk_id, $email_subject, $email_message, "new_mitigation");
    }
}

/**************************************
 * FUNCTION: NOTIFY MITIGATION UPDATE *
 **************************************/
function notify_mitigation_update($id)
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }
    // If we are supposed to notify on a mitigation update
    if ($NOTIFY_ON_MITIGATION_UPDATE == "true")
    {
        global $escaper;
        global $lang;

        // Add 1000 to get the risk ID
        $risk_id = (int)$id + 1000;

        // Get the users information
        $user_info = get_user_by_id($_SESSION['uid']);
        $name = $user_info['name'];

        // Create the message
        $email_subject = "Risk Mitigation Updated for Risk ID " . $escaper->escapeHtml($risk_id);
        $email_message = "<p>The mitigation for risk ID " . $escaper->escapeHtml($risk_id) . " was updated by " . $escaper->escapeHtml($name) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true")
        {
            $email_message .= additional_mitigation_details($risk_id);
            $email_message .= additional_risk_details($risk_id);
        }

        $email_message .= "<p><b><u>" . $escaper->escapeHtml($lang['Actions']) . ":</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "&type=2&action=editreview\">Perform a Review</a></li>\n";
        $email_message .= "</ul>\n";
        // Send the e-mail
        prepare_email($risk_id, $email_subject, $email_message, "mitigation_update");
    }
}

/*******************************
 * FUNCTION: NOTIFY NEW REVIEW *
 *******************************/
function notify_new_review($id)
{
    
    
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we are supposed to notify on a review
    if ($NOTIFY_ON_REVIEW == "true")
    {
        global $escaper;
        global $lang;

        // Add 1000 to get the risk ID
        $risk_id = (int)$id + 1000;

        // Get the users information
        $user_info = get_user_by_id($_SESSION['uid']);
        $name = $user_info['name'];

        // Create the message
        $email_subject = "Management Review Performed for Risk ID " . $escaper->escapeHtml($risk_id);
        $email_message = "<p>A management review of risk ID " . $escaper->escapeHtml($risk_id) . " was performed by " . $escaper->escapeHtml($name) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true")
        {
            $email_message .= additional_review_details($risk_id);
            $email_message .= additional_risk_details($risk_id);
        }

        $email_message .= "<p><b><u>" . $escaper->escapeHtml($lang['Actions']) . ":</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
        $email_message .= "</ul>\n";

        // Send the e-mail
        prepare_email($risk_id, $email_subject, $email_message, "risk_review");
    }
}

/*******************************
 * FUNCTION: NOTIFY RISK CLOSE *
 *******************************/
function notify_risk_close($id)
{
    
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we are supposed to notify on a close
    if ($NOTIFY_ON_CLOSE == "true")
    {
        global $escaper;
        global $lang;

        // Add 1000 to get the risk ID
        $risk_id = (int)$id + 1000;

        // Get the users information
        $user_info = get_user_by_id($_SESSION['uid']);
        $name = $user_info['name'];

        // Create the message
        $email_subject = "Risk ID " . $escaper->escapeHtml($risk_id) . " Has Been Closed";
        $email_message = "<p>Risk ID " . $escaper->escapeHtml($risk_id) . " has been closed by " . $escaper->escapeHtml($name) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true")
        {
            $email_message .= additional_close_details($risk_id);
            $email_message .= additional_risk_details($risk_id);
        }

        $email_message .= "<p><b><u>" . $escaper->escapeHtml($lang['Actions']) . ":</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
        $email_message .= "</ul>\n";

        // Send the e-mail
        prepare_email($risk_id, $email_subject, $email_message, "risk_close");
    }
}

/*********************************
 * FUNCTION: NOTIFY RISK COMMENT *
 *********************************/
function notify_risk_comment($id, $comment)
{        
        // Get the notification settings
        $configs = get_notification_settings();

        // For each configuration
        foreach ($configs as $config)
        {
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
        }

        // If we are supposed to notify on a risk comment
        if (notification_extra() && $NOTIFY_ON_RISK_COMMENT == "true")
        {
                global $escaper;
                global $lang;

                // Add 1000 to get the risk ID
                $risk_id = (int)$id + 1000;
                
                // Get the risk
                $risk = get_risk_by_id($risk_id);

                // Get the users information
                $user_info = get_user_by_id($_SESSION['uid']);
                $name = $user_info['name'];

                // Create the messageRISK
                $email_subject = "Comment Added to Risk";
                $email_message = "<p><b>".$escaper->escapeHtml($lang['RiskSubject']).": </b>".try_decrypt($risk[0]['subject'])."</p>\n";
                $email_message .= "<p><b>".$escaper->escapeHtml($lang['RiskAssessment']).": </b>".try_decrypt($risk[0]['assessment'])."</p>\n";
                $email_message .= "<p><b>".$escaper->escapeHtml($lang['Comment']).": </b>".$comment."</p>\n";
                $email_message .= "<p>A new comment has been added to risk ID " . $escaper->escapeHtml($risk_id) ." by " . $escaper->escapeHtml($name) . ".  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, or part of the team associated with the risk.</p>\n";

                // If verbosity is enabled
                if ($VERBOSE == "true")
                {
                        $email_message .= additional_risk_details($risk_id);
                }

                $email_message .= "<p><b><u>" . $escaper->escapeHtml($lang['Actions']) . ":</u></b></p><ul>\n";
                $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
                $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "&type=1&action=editmitigation\">Plan a Mitigation</a></li>\n";
                $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "&type=2&action=editreview\">Perform a Review</a></li>\n";
                $email_message .= "</ul>\n";

                // Send the e-mail
                prepare_email($risk_id, $email_subject, $email_message, "risk_comment");
        }
}


/**********************************
 * FUNCTION: NOTIFY AUDIT COMMENT *
 **********************************/
function notify_audit_comment($test_audit_id, $comment)
{
    
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we are supposed to notify on an audit comment
    if (notification_extra() && $NOTIFY_ON_AUDIT_COMMENT == "true")
    {
        global $lang, $escaper;
        $test_audit = get_framework_control_test_audit_by_id($test_audit_id);

        // Get the user's information
        $user_info = get_user_by_id($_SESSION['uid']);
        $name = $user_info['name'];

        // Create the message
        $email_subject = $escaper->escapeHtml($lang['NotifyAuditCommentSubject']);

        $email_message = "<p>" . $escaper->escapeHtml(_lang("NotifyAuditCommentStatement", ['test_audit_id' => $test_audit_id, 'name' => $name])) . "</p>\n";
        $email_message .= "<p><b>".$escaper->escapeHtml($lang['Comment']).": </b>".$escaper->escapeHtml($comment)."</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true")
        {
            $email_message .= additional_test_audit_details($test_audit);
        }

        $email_message .= "<p><b><u>" . $escaper->escapeHtml($lang['Actions']) . ":</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/compliance/" . ($test_audit['status'] == get_setting("closed_audit_status") ? "view_test" : "testing") . ".php?id=" . $escaper->escapeHtml($test_audit_id) . "\">" . $escaper->escapeHtml($lang['ViewTest']) . "</a></li>\n";
        $email_message .= "</ul>\n";

        // Send the e-mail
        prepare_test_audit_email($test_audit, $email_subject, $email_message, "audit_comment");
    }
}


/****************************************
 * FUNCTION: NOTIFY AUDIT STATUS CHANGE *
 ****************************************/
function notify_audit_status_change($test_audit_id, $old_status, $new_status)
{
        
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we are supposed to notify on an audit status change
    if (notification_extra() && $NOTIFY_ON_AUDIT_STATUS_CHANGE == "true")
    {            
        global $lang, $escaper;
        $test_audit = get_framework_control_test_audit_by_id($test_audit_id);

        // Get the users information
        $user_info = get_user_by_id($_SESSION['uid']);
        $name = $user_info['name'];

        // Create the message
        $email_subject = $escaper->escapeHtml($lang['NotifyAuditStatusChangeSubject']);

        $email_message = "<p>" . $escaper->escapeHtml(_lang("NotifyAuditStatusChangeStatement", ['test_audit_id' => $test_audit_id, 'name' => $name])) . "</p>\n";
        $email_message .= "<p><b>".$escaper->escapeHtml($lang['OldValue']).": </b>".$escaper->escapeHtml(get_name_by_value("test_status", $old_status, $lang['None']))."</p>\n";
        $email_message .= "<p><b>".$escaper->escapeHtml($lang['NewValue']).": </b>".$escaper->escapeHtml(get_name_by_value("test_status", $new_status, $lang['None']))."</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true")
        {
            $email_message .= additional_test_audit_details($test_audit);
        }

        $email_message .= "<p><b><u>" . $escaper->escapeHtml($lang['Actions']) . ":</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/compliance/" . ($test_audit['status'] == get_setting("closed_audit_status") ? "view_test" : "testing") . ".php?id=" . $escaper->escapeHtml($test_audit_id) . "\">" . $escaper->escapeHtml($lang['ViewTest']) . "</a></li>\n";
        $email_message .= "</ul>\n";

        // Send the e-mail
        prepare_test_audit_email($test_audit, $email_subject, $email_message, "audit_status_change");
    }
}


/*****************************
 * FUNCTION: UPDATE SETTINGS *
 *****************************/
if (!function_exists('update_settings')) {
    function update_settings($configs)
    {
        global $lang;
        global $escaper;

        // Open the database connection
        $db = db_open();

        /* We could just do this function like this
        foreach($configs as $name => $value) {
            if ($value != "")
            {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = :name");
                $stmt->bindParam(":name", $name);
                $stmt->bindParam(":value", $value);
                $stmt->execute();
            }
        }*/

        // If VERBOSE is not empty
        if ($configs['VERBOSE'] != "")
        {
            // Update VERBOSE
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'VERBOSE'");
                $stmt->bindParam(":value", $configs['VERBOSE']);
                $stmt->execute();
        }

        // If NOTIFY_SUBMITTER is not empty
        if ($configs['NOTIFY_SUBMITTER'] != "")
        {
            // Update NOTIFY_SUBMITTER
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_SUBMITTER'");
                $stmt->bindParam(":value", $configs['NOTIFY_SUBMITTER']);
                $stmt->execute();
        }

        // If NOTIFY_OWNER is not empty
        if ($configs['NOTIFY_OWNER'] != "")
        {
            // Update NOTIFY_OWNER
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_OWNER'");
                $stmt->bindParam(":value", $configs['NOTIFY_OWNER']);
                $stmt->execute();
        }

        // If NOTIFY_OWNERS_MANAGER is not empty
        if ($configs['NOTIFY_OWNERS_MANAGER'] != "")
        {
            // Update NOTIFY_OWNERS_MANAGER
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_OWNERS_MANAGER'");
                $stmt->bindParam(":value", $configs['NOTIFY_OWNERS_MANAGER']);
                $stmt->execute();
        }

        // If NOTIFY_TEAM is not empty
        if ($configs['NOTIFY_TEAM'] != "")
        {
            // Update NOTIFY_TEAM
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_TEAM'");
                $stmt->bindParam(":value", $configs['NOTIFY_TEAM']);
                $stmt->execute();
        }

        // If NOTIFY_ON_NEW_RISK is not empty
        if ($configs['NOTIFY_ON_NEW_RISK'] != "")
        {
            // Update NOTIFY_ON_NEW_RISK
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_NEW_RISK'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_NEW_RISK']);
                $stmt->execute();
        }

        // If NOTIFY_ON_RISK_UPDATE is not empty
        if ($configs['NOTIFY_ON_RISK_UPDATE'] != "")
        {
                // Update NOTIFY_ON_RISK_UPDATE
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_RISK_UPDATE'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_RISK_UPDATE']);
                $stmt->execute();
        }

        // If NOTIFY_ON_NEW_MITIGATION is not empty
        if ($configs['NOTIFY_ON_NEW_MITIGATION'] != "")
        {
            // Update NOTIFY_ON_NEW_MITIGATION
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_NEW_MITIGATION'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_NEW_MITIGATION']);
                $stmt->execute();
        }

        // If NOTIFY_ON_MITIGATION_UPDATE is not empty
        if ($configs['NOTIFY_ON_MITIGATION_UPDATE'] != "")
        {
            // Update NOTIFY_ON_MITIGATION_UPDATE
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_MITIGATION_UPDATE'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_MITIGATION_UPDATE']);
                $stmt->execute();
        }

        // If NOTIFY_ON_REVIEW is not empty
        if ($configs['NOTIFY_ON_REVIEW'] != "")
        {
            // Update NOTIFY_ON_REVIEW
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_REVIEW'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_REVIEW']);
                $stmt->execute();
        }

        // If NOTIFY_ON_CLOSE is not empty
        if ($configs['NOTIFY_ON_CLOSE'] != "")
        {
            // Update NOTIFY_ON_CLOSE
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_CLOSE'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_CLOSE']);
                $stmt->execute();
        }

        // If NOTIFY_ON_AUDIT_COMMENT is not empty
        if ($configs['NOTIFY_ON_AUDIT_COMMENT'] != "")
        {
            // Update NOTIFY_ON_AUDIT_COMMENT
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_AUDIT_COMMENT'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_AUDIT_COMMENT']);
                $stmt->execute();
        }

        // If NOTIFY_ON_RISK_COMMENT is not empty
        if ($configs['NOTIFY_ON_RISK_COMMENT'] != "")
        {
            // Update NOTIFY_ON_RISK_COMMENT
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_RISK_COMMENT'");
                $stmt->bindParam(":value", $configs['NOTIFY_ON_RISK_COMMENT']);
                $stmt->execute();
        }        
        
        // If NOTIFY_ADDITIONAL_STAKEHOLDERS is not empty
        if ($configs['NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
            // Update NOTIFY_ADDITIONAL_STAKEHOLDERS
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ADDITIONAL_STAKEHOLDERS'");
                $stmt->bindParam(":value", $configs['NOTIFY_ADDITIONAL_STAKEHOLDERS']);
                $stmt->execute();
        }
        
        // If AUTO_NOTIFY_SUBMITTER is not empty
        if ($configs['AUTO_NOTIFY_SUBMITTER'] != "")
        {
            // Update AUTO_NOTIFY_SUBMITTER
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_SUBMITTER'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_SUBMITTER']);
                $stmt->execute();
        }

        // If AUTO_NOTIFY_OWNER is not empty
        if ($configs['AUTO_NOTIFY_OWNER'] != "")
        {
            // Update AUTO_NOTIFY_OWNER
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_OWNER'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_OWNER']);
                $stmt->execute();
        }

        // If AUTO_NOTIFY_OWNERS_MANAGER is not empty
        if ($configs['AUTO_NOTIFY_OWNERS_MANAGER'] != "")
        {
            // Update AUTO_NOTIFY_OWNERS_MANAGER
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_OWNERS_MANAGER'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_OWNERS_MANAGER']);
                $stmt->execute();
        }

        // If AUTO_NOTIFY_TEAM is not empty
        if ($configs['AUTO_NOTIFY_TEAM'] != "")
        {
            // Update AUTO_NOTIFY_TEAM
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_TEAM'");
            $stmt->bindParam(":value", $configs['AUTO_NOTIFY_TEAM']);
            $stmt->execute();
        }

        // If AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS is not empty
        if ($configs['AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
            // Update AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
            $stmt->bindParam(":value", $configs['AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
            $stmt->execute();
        }

        // If AUTO_NOTIFY_REVIEWERS is not empty
        if ($configs['AUTO_NOTIFY_REVIEWERS'] != "")
        {
            // Update AUTO_NOTIFY_REVIEWERS
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_REVIEWERS'");
            $stmt->bindParam(":value", $configs['AUTO_NOTIFY_REVIEWERS']);
            $stmt->execute();
        }

        // SET CRON JOB SETTINGS
        if (isset($configs['CRON_PERIOD']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_PERIOD'");
            $stmt->bindParam(":value", $configs['CRON_PERIOD']);
            $stmt->execute();
        }

        if (isset($configs['CRON_HOUR']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_HOUR'");
            $stmt->bindParam(":value", $configs['CRON_HOUR']);
            $stmt->execute();
        }
        
        if (isset($configs['CRON_MINUTE']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_MINUTE'");
            $stmt->bindParam(":value", $configs['CRON_MINUTE']);
            $stmt->execute();
        }

        if (isset($configs['CRON_DAY_OF_WEEK']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_DAY_OF_WEEK'");
            $stmt->bindParam(":value", $configs['CRON_DAY_OF_WEEK']);
            $stmt->execute();
        }
    
        if (isset($configs['CRON_MONTH']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_MONTH'");
            $stmt->bindParam(":value", $configs['CRON_MONTH']);
            $stmt->execute();
        }

        if (isset($configs['CRON_DATE']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_DATE'");
            $stmt->bindParam(":value", $configs['CRON_DATE']);
            $stmt->execute();
        }

        if ($configs['MITIGATION_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'MITIGATION_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
            $stmt->bindParam(":value", $configs['MITIGATION_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
            $stmt->execute();
        }

        if ($configs['MITIGATION_UPDATE_NOTIFY_OWNER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'MITIGATION_UPDATE_NOTIFY_OWNER'");
                $stmt->bindParam(":value", $configs['MITIGATION_UPDATE_NOTIFY_OWNER']);
                $stmt->execute();
        }

        if ($configs['MITIGATION_UPDATE_NOTIFY_OWNERS_MANAGER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'MITIGATION_UPDATE_NOTIFY_OWNERS_MANAGER'");
                $stmt->bindParam(":value", $configs['MITIGATION_UPDATE_NOTIFY_OWNERS_MANAGER']);
                $stmt->execute();
        }

        if ($configs['MITIGATION_UPDATE_NOTIFY_SUBMITTER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'MITIGATION_UPDATE_NOTIFY_SUBMITTER'");
                $stmt->bindParam(":value", $configs['MITIGATION_UPDATE_NOTIFY_SUBMITTER']);
                $stmt->execute();
        }

        if ($configs['MITIGATION_UPDATE_NOTIFY_TEAM'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'MITIGATION_UPDATE_NOTIFY_TEAM'");
                $stmt->bindParam(":value", $configs['MITIGATION_UPDATE_NOTIFY_TEAM']);
                $stmt->execute();
        }

        if ($configs['NEW_MITIGATION_NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NEW_MITIGATION_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
                $stmt->bindParam(":value", $configs['NEW_MITIGATION_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
                $stmt->execute();
        }

        if ($configs['NEW_MITIGATION_NOTIFY_OWNER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NEW_MITIGATION_NOTIFY_OWNER'");
                $stmt->bindParam(":value", $configs['NEW_MITIGATION_NOTIFY_OWNER']);
                $stmt->execute();
        }

        if ($configs['NEW_MITIGATION_NOTIFY_OWNERS_MANAGER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NEW_MITIGATION_NOTIFY_OWNERS_MANAGER'");
                $stmt->bindParam(":value", $configs['NEW_MITIGATION_NOTIFY_OWNERS_MANAGER']);
                $stmt->execute();
        }

        if ($configs['NEW_MITIGATION_NOTIFY_SUBMITTER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NEW_MITIGATION_NOTIFY_SUBMITTER'");
                $stmt->bindParam(":value", $configs['NEW_MITIGATION_NOTIFY_SUBMITTER']);
                $stmt->execute();
        }

        if ($configs['NEW_MITIGATION_NOTIFY_TEAM'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NEW_MITIGATION_NOTIFY_TEAM'");
                $stmt->bindParam(":value", $configs['NEW_MITIGATION_NOTIFY_TEAM']);
                $stmt->execute();
        }

        if ($configs['NEW_RISK_NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NEW_RISK_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
                $stmt->bindParam(":value", $configs['NEW_RISK_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
                $stmt->execute();
        }

        if ($configs['NEW_RISK_NOTIFY_OWNER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NEW_RISK_NOTIFY_OWNER'");
                $stmt->bindParam(":value", $configs['NEW_RISK_NOTIFY_OWNER']);
                $stmt->execute();
        }

        if ($configs['NEW_RISK_NOTIFY_OWNERS_MANAGER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NEW_RISK_NOTIFY_OWNERS_MANAGER'");
                $stmt->bindParam(":value", $configs['NEW_RISK_NOTIFY_OWNERS_MANAGER']);
                $stmt->execute();
        }

        if ($configs['NEW_RISK_NOTIFY_SUBMITTER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NEW_RISK_NOTIFY_SUBMITTER'");
                $stmt->bindParam(":value", $configs['NEW_RISK_NOTIFY_SUBMITTER']);
                $stmt->execute();
        }

        if ($configs['NEW_RISK_NOTIFY_TEAM'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NEW_RISK_NOTIFY_TEAM'");
                $stmt->bindParam(":value", $configs['NEW_RISK_NOTIFY_TEAM']);
                $stmt->execute();
        }

        if ($configs['RISK_CLOSE_NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_CLOSE_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
                $stmt->bindParam(":value", $configs['RISK_CLOSE_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
                $stmt->execute();
        }

        if ($configs['RISK_CLOSE_NOTIFY_OWNER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_CLOSE_NOTIFY_OWNER'");
                $stmt->bindParam(":value", $configs['RISK_CLOSE_NOTIFY_OWNER']);
                $stmt->execute();
        }

        if ($configs['RISK_CLOSE_NOTIFY_OWNERS_MANAGER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_CLOSE_NOTIFY_OWNERS_MANAGER'");
                $stmt->bindParam(":value", $configs['RISK_CLOSE_NOTIFY_OWNERS_MANAGER']);
                $stmt->execute();
        }

        if ($configs['RISK_CLOSE_NOTIFY_SUBMITTER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_CLOSE_NOTIFY_SUBMITTER'");
                $stmt->bindParam(":value", $configs['RISK_CLOSE_NOTIFY_SUBMITTER']);
                $stmt->execute();
        }

        if ($configs['RISK_CLOSE_NOTIFY_TEAM'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_CLOSE_NOTIFY_TEAM'");
                $stmt->bindParam(":value", $configs['RISK_CLOSE_NOTIFY_TEAM']);
                $stmt->execute();
        }

        if ($configs['RISK_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
                $stmt->bindParam(":value", $configs['RISK_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
                $stmt->execute();
        }

        if ($configs['RISK_COMMENT_NOTIFY_OWNER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_COMMENT_NOTIFY_OWNER'");
                $stmt->bindParam(":value", $configs['RISK_COMMENT_NOTIFY_OWNER']);
                $stmt->execute();
        }

        if ($configs['RISK_COMMENT_NOTIFY_OWNERS_MANAGER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_COMMENT_NOTIFY_OWNERS_MANAGER'");
                $stmt->bindParam(":value", $configs['RISK_COMMENT_NOTIFY_OWNERS_MANAGER']);
                $stmt->execute();
        }

        if ($configs['RISK_COMMENT_NOTIFY_SUBMITTER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_COMMENT_NOTIFY_SUBMITTER'");
                $stmt->bindParam(":value", $configs['RISK_COMMENT_NOTIFY_SUBMITTER']);
                $stmt->execute();
        }

        if ($configs['RISK_COMMENT_NOTIFY_TEAM'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_COMMENT_NOTIFY_TEAM'");
                $stmt->bindParam(":value", $configs['RISK_COMMENT_NOTIFY_TEAM']);
                $stmt->execute();
        }

        if ($configs['RISK_REVIEW_NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_REVIEW_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
                $stmt->bindParam(":value", $configs['RISK_REVIEW_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
                $stmt->execute();
        }

        if ($configs['RISK_REVIEW_NOTIFY_OWNER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_REVIEW_NOTIFY_OWNER'");
                $stmt->bindParam(":value", $configs['RISK_REVIEW_NOTIFY_OWNER']);
                $stmt->execute();
        }

        if ($configs['RISK_REVIEW_NOTIFY_OWNERS_MANAGER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_REVIEW_NOTIFY_OWNERS_MANAGER'");
                $stmt->bindParam(":value", $configs['RISK_REVIEW_NOTIFY_OWNERS_MANAGER']);
                $stmt->execute();
        }

        if ($configs['RISK_REVIEW_NOTIFY_SUBMITTER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_REVIEW_NOTIFY_SUBMITTER'");
                $stmt->bindParam(":value", $configs['RISK_REVIEW_NOTIFY_SUBMITTER']);
                $stmt->execute();
        }

        if ($configs['RISK_REVIEW_NOTIFY_TEAM'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_REVIEW_NOTIFY_TEAM'");
                $stmt->bindParam(":value", $configs['RISK_REVIEW_NOTIFY_TEAM']);
                $stmt->execute();
        }

        if ($configs['RISK_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
                $stmt->bindParam(":value", $configs['RISK_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
                $stmt->execute();
        }

        if ($configs['RISK_UPDATE_NOTIFY_OWNER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_UPDATE_NOTIFY_OWNER'");
                $stmt->bindParam(":value", $configs['RISK_UPDATE_NOTIFY_OWNER']);
                $stmt->execute();
        }

        if ($configs['RISK_UPDATE_NOTIFY_OWNERS_MANAGER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_UPDATE_NOTIFY_OWNERS_MANAGER'");
                $stmt->bindParam(":value", $configs['RISK_UPDATE_NOTIFY_OWNERS_MANAGER']);
                $stmt->execute();
        }

        if ($configs['RISK_UPDATE_NOTIFY_SUBMITTER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_UPDATE_NOTIFY_SUBMITTER'");
                $stmt->bindParam(":value", $configs['RISK_UPDATE_NOTIFY_SUBMITTER']);
                $stmt->execute();
        }

        if ($configs['RISK_UPDATE_NOTIFY_TEAM'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'RISK_UPDATE_NOTIFY_TEAM'");
                $stmt->bindParam(":value", $configs['RISK_UPDATE_NOTIFY_TEAM']);
                $stmt->execute();
        }

        if ($configs['AUDIT_COMMENT_NOTIFY_TESTER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUDIT_COMMENT_NOTIFY_TESTER'");
                $stmt->bindParam(":value", $configs['AUDIT_COMMENT_NOTIFY_TESTER']);
                $stmt->execute();
        }        

        if ($configs['AUDIT_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUDIT_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
                $stmt->bindParam(":value", $configs['AUDIT_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
                $stmt->execute();
        }

        if ($configs['AUDIT_COMMENT_NOTIFY_CONTROL_OWNER'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUDIT_COMMENT_NOTIFY_CONTROL_OWNER'");
                $stmt->bindParam(":value", $configs['AUDIT_COMMENT_NOTIFY_CONTROL_OWNER']);
                $stmt->execute();
        }
        
        // If NOTIFY_ON_AUDIT_STATUS_CHANGE is not empty
        if ($configs['NOTIFY_ON_AUDIT_STATUS_CHANGE'] != "")
        {
            // Update NOTIFY_ON_AUDIT_STATUS_CHANGE
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_AUDIT_STATUS_CHANGE'");
            $stmt->bindParam(":value", $configs['NOTIFY_ON_AUDIT_STATUS_CHANGE']);
            $stmt->execute();
        }        
        if ($configs['AUDIT_STATUS_CHANGE_NOTIFY_TESTER'] != "")
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUDIT_STATUS_CHANGE_NOTIFY_TESTER'");
            $stmt->bindParam(":value", $configs['AUDIT_STATUS_CHANGE_NOTIFY_TESTER']);
            $stmt->execute();
        }        
        if ($configs['AUDIT_STATUS_CHANGE_NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUDIT_STATUS_CHANGE_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
            $stmt->bindParam(":value", $configs['AUDIT_STATUS_CHANGE_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
            $stmt->execute();
        }        
        if ($configs['AUDIT_STATUS_CHANGE_NOTIFY_CONTROL_OWNER'] != "")
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUDIT_STATUS_CHANGE_NOTIFY_CONTROL_OWNER'");
            $stmt->bindParam(":value", $configs['AUDIT_STATUS_CHANGE_NOTIFY_CONTROL_OWNER']);
            $stmt->execute();
        }
        
        // If NOTIFY_ON_AUDIT_STATUS_CHANGE is not empty
        if ($configs['NOTIFY_ON_AUDIT_STATUS_CHANGE'] != "")
        {
            // Update NOTIFY_ON_AUDIT_STATUS_CHANGE
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFY_ON_AUDIT_STATUS_CHANGE'");
            $stmt->bindParam(":value", $configs['NOTIFY_ON_AUDIT_STATUS_CHANGE']);
            $stmt->execute();
        }        
        if ($configs['AUDIT_STATUS_CHANGE_NOTIFY_TESTER'] != "")
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUDIT_STATUS_CHANGE_NOTIFY_TESTER'");
            $stmt->bindParam(":value", $configs['AUDIT_STATUS_CHANGE_NOTIFY_TESTER']);
            $stmt->execute();
        }        
        if ($configs['AUDIT_STATUS_CHANGE_NOTIFY_ADDITIONAL_STAKEHOLDERS'] != "")
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUDIT_STATUS_CHANGE_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
            $stmt->bindParam(":value", $configs['AUDIT_STATUS_CHANGE_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
            $stmt->execute();
        }        
        if ($configs['AUDIT_STATUS_CHANGE_NOTIFY_CONTROL_OWNER'] != "")
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUDIT_STATUS_CHANGE_NOTIFY_CONTROL_OWNER'");
            $stmt->bindParam(":value", $configs['AUDIT_STATUS_CHANGE_NOTIFY_CONTROL_OWNER']);
            $stmt->execute();
        }
        
        // Scheduled notifications
        if ($configs['NOTIFICATION_SEND_AUDIT_HOUR'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_AUDIT_HOUR'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_AUDIT_HOUR']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_AUDIT_MINUTE'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_AUDIT_MINUTE'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_AUDIT_MINUTE']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_AUDIT_EMAIL_1'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_1'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_AUDIT_EMAIL_1']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_AUDIT_EMAIL_1_VALUE'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_1_VALUE'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_AUDIT_EMAIL_1_VALUE']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_AUDIT_EMAIL_2'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_2'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_AUDIT_EMAIL_2']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_AUDIT_EMAIL_2_VALUE'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_2_VALUE'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_AUDIT_EMAIL_2_VALUE']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_AUDIT_EMAIL_3'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_3'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_AUDIT_EMAIL_3']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_AUDIT_EMAIL_3_VALUE'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_3_VALUE'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_AUDIT_EMAIL_3_VALUE']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_AUDIT_DUE_EMAIL'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_AUDIT_DUE_EMAIL'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_AUDIT_DUE_EMAIL']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL_VALUE'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL_VALUE'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL_VALUE']);
                $stmt->execute();
        }

        if ($configs['AUTO_NOTIFY_TESTER_AUDITS'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_TESTER_AUDITS'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_TESTER_AUDITS']);
                $stmt->execute();
        }

        if ($configs['AUTO_NOTIFY_CONTROL_OWNER_AUDITS'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_CONTROL_OWNER_AUDITS'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_CONTROL_OWNER_AUDITS']);
                $stmt->execute();
        }

        if ($configs['AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_AUDITS'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_AUDITS'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_AUDITS']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_MITIGATION_HOUR'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_MITIGATION_HOUR'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_MITIGATION_HOUR']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_MITIGATION_MINUTE'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_MITIGATION_MINUTE'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_MITIGATION_MINUTE']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_MITIGATION_EMAIL_1'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_1'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_MITIGATION_EMAIL_1']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_MITIGATION_EMAIL_1_VALUE'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_1_VALUE'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_MITIGATION_EMAIL_1_VALUE']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_MITIGATION_EMAIL_2'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_2'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_MITIGATION_EMAIL_2']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_MITIGATION_EMAIL_2_VALUE'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_2_VALUE'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_MITIGATION_EMAIL_2_VALUE']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_MITIGATION_EMAIL_3'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_3'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_MITIGATION_EMAIL_3']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_MITIGATION_EMAIL_3_VALUE'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_3_VALUE'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_MITIGATION_EMAIL_3_VALUE']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_MITIGATION_DUE_EMAIL'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_MITIGATION_DUE_EMAIL'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_MITIGATION_DUE_EMAIL']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL']);
                $stmt->execute();
        }

        if ($configs['NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL_VALUE'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL_VALUE'");
                $stmt->bindParam(":value", $configs['NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL_VALUE']);
                $stmt->execute();
        }
        
        if ($configs['AUTO_NOTIFY_MITIGATION_OWNER_PLANNED_MITIGATION'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_MITIGATION_OWNER_PLANNED_MITIGATION'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_MITIGATION_OWNER_PLANNED_MITIGATION']);
                $stmt->execute();
        }
        
        if ($configs['AUTO_NOTIFY_MITIGATION_TEAM_PLANNED_MITIGATION'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_MITIGATION_TEAM_PLANNED_MITIGATION'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_MITIGATION_TEAM_PLANNED_MITIGATION']);
                $stmt->execute();
        }
        
        if ($configs['AUTO_NOTIFY_RISK_SUBMITTER_PLANNED_MITIGATION'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_RISK_SUBMITTER_PLANNED_MITIGATION'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_RISK_SUBMITTER_PLANNED_MITIGATION']);
                $stmt->execute();
        }
        
        if ($configs['AUTO_NOTIFY_RISK_OWNER_PLANNED_MITIGATION'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_RISK_OWNER_PLANNED_MITIGATION'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_RISK_OWNER_PLANNED_MITIGATION']);
                $stmt->execute();
        }
        
        if ($configs['AUTO_NOTIFY_RISK_OWNERS_MANAGER_PLANNED_MITIGATION'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_RISK_OWNERS_MANAGER_PLANNED_MITIGATION'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_RISK_OWNERS_MANAGER_PLANNED_MITIGATION']);
                $stmt->execute();
        }
        
        if ($configs['AUTO_NOTIFY_RISK_TEAM_PLANNED_MITIGATION'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_RISK_TEAM_PLANNED_MITIGATION'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_RISK_TEAM_PLANNED_MITIGATION']);
                $stmt->execute();
        }
        
        if ($configs['AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_PLANNED_MITIGATION'] != "")
        {
                $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_PLANNED_MITIGATION'");
                $stmt->bindParam(":value", $configs['AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_PLANNED_MITIGATION']);
                $stmt->execute();
        }
        
        if (isset($configs['CRON_NON_MITIGATION_PERIOD']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_NON_MITIGATION_PERIOD'");
            $stmt->bindParam(":value", $configs['CRON_NON_MITIGATION_PERIOD']);
            $stmt->execute();
        }
                
        if (isset($configs['CRON_NON_MITIGATION_HOUR']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_NON_MITIGATION_HOUR'");
            $stmt->bindParam(":value", $configs['CRON_NON_MITIGATION_HOUR']);
            $stmt->execute();
        }
                
        if (isset($configs['CRON_NON_MITIGATION_MINUTE']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_NON_MITIGATION_MINUTE'");
            $stmt->bindParam(":value", $configs['CRON_NON_MITIGATION_MINUTE']);
            $stmt->execute();
        }
                
        if (isset($configs['CRON_NON_MITIGATION_DAY_OF_WEEK']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_NON_MITIGATION_DAY_OF_WEEK'");
            $stmt->bindParam(":value", $configs['CRON_NON_MITIGATION_DAY_OF_WEEK']);
            $stmt->execute();
        }
                
        if (isset($configs['CRON_NON_MITIGATION_DATE']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_NON_MITIGATION_DATE'");
            $stmt->bindParam(":value", $configs['CRON_NON_MITIGATION_DATE']);
            $stmt->execute();
        }
                
        if (isset($configs['CRON_NON_MITIGATION_MONTH']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_NON_MITIGATION_MONTH'");
            $stmt->bindParam(":value", $configs['CRON_NON_MITIGATION_MONTH']);
            $stmt->execute();
        }
                
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NON_MITIGATION_AUTO_NOTIFY_SUBMITTER'");
        $stmt->bindParam(":value", $configs['NON_MITIGATION_AUTO_NOTIFY_SUBMITTER']);
        $stmt->execute();
        
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NON_MITIGATION_AUTO_NOTIFY_OWNER'");
        $stmt->bindParam(":value", $configs['NON_MITIGATION_AUTO_NOTIFY_OWNER']);
        $stmt->execute();
        
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NON_MITIGATION_AUTO_NOTIFY_OWNERS_MANAGER'");
        $stmt->bindParam(":value", $configs['NON_MITIGATION_AUTO_NOTIFY_OWNERS_MANAGER']);
        $stmt->execute();
        
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NON_MITIGATION_AUTO_NOTIFY_TEAM'");
        $stmt->bindParam(":value", $configs['NON_MITIGATION_AUTO_NOTIFY_TEAM']);
        $stmt->execute();
        
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'NON_MITIGATION_AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS'");
        $stmt->bindParam(":value", $configs['NON_MITIGATION_AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS']);
        $stmt->execute();
        
        // Policy and control exception review notification
        if (isset($configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD'");
            $stmt->bindParam(":value", $configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD']);
            $stmt->execute();
        }
                
        if (isset($configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_HOUR']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_POLICY_CONTROL_EXCEPTION_REVIEW_HOUR'");
            $stmt->bindParam(":value", $configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_HOUR']);
            $stmt->execute();
        }
                
        if (isset($configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MINUTE']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MINUTE'");
            $stmt->bindParam(":value", $configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MINUTE']);
            $stmt->execute();
        }
                
        if (isset($configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_DAY_OF_WEEK']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_POLICY_CONTROL_EXCEPTION_REVIEW_DAY_OF_WEEK'");
            $stmt->bindParam(":value", $configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_DAY_OF_WEEK']);
            $stmt->execute();
        }
                
        if (isset($configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_DATE']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_POLICY_CONTROL_EXCEPTION_REVIEW_DATE'");
            $stmt->bindParam(":value", $configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_DATE']);
            $stmt->execute();
        }
                
        if (isset($configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MONTH']))
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MONTH'");
            $stmt->bindParam(":value", $configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MONTH']);
            $stmt->execute();
        }

        if ($configs['POLICY_CONTROL_EXCEPTION_REVIEW_CONTROL_OWNER'] != "")
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'POLICY_CONTROL_EXCEPTION_REVIEW_CONTROL_OWNER'");
            $stmt->bindParam(":value", $configs['POLICY_CONTROL_EXCEPTION_REVIEW_CONTROL_OWNER']);
            $stmt->execute();
        }
        if ($configs['POLICY_CONTROL_EXCEPTION_REVIEW_POLICY_OWNER'] != "")
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'POLICY_CONTROL_EXCEPTION_REVIEW_POLICY_OWNER'");
            $stmt->bindParam(":value", $configs['POLICY_CONTROL_EXCEPTION_REVIEW_POLICY_OWNER']);
            $stmt->execute();
        }
        if ($configs['POLICY_CONTROL_EXCEPTION_REVIEW_ADDITIONAL_STAKEHOLDERS'] != "")
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'POLICY_CONTROL_EXCEPTION_REVIEW_ADDITIONAL_STAKEHOLDERS'");
            $stmt->bindParam(":value", $configs['POLICY_CONTROL_EXCEPTION_REVIEW_ADDITIONAL_STAKEHOLDERS']);
            $stmt->execute();
        }
        if ($configs['POLICY_CONTROL_EXCEPTION_REVIEW_APPROVER'] != "")
        {
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'POLICY_CONTROL_EXCEPTION_REVIEW_APPROVER'");
            $stmt->bindParam(":value", $configs['POLICY_CONTROL_EXCEPTION_REVIEW_APPROVER']);
            $stmt->execute();
        }

        // Close the database connection
        db_close($db);
        
        set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));

        // Return true;
        return true;
    }
}

/****************************************
 * FUNCTION: UPDATE NOTIFICATION CONFIG *
 ****************************************/
function update_notification_config()
{
    $configs['VERBOSE'] = isset($_POST['verbose']) ? 'true' : 'false';
    $configs['NOTIFY_SUBMITTER'] = isset($_POST['notify_submitter']) ? 'true' : 'false';
    $configs['NOTIFY_OWNER'] = isset($_POST['notify_owner']) ? 'true' : 'false';
    $configs['NOTIFY_OWNERS_MANAGER'] = isset($_POST['notify_owners_manager']) ? 'true' : 'false';
    $configs['NOTIFY_TEAM'] = isset($_POST['notify_team']) ? 'true' : 'false';
    $configs['NOTIFY_ON_NEW_RISK'] = isset($_POST['notify_on_new_risk']) ? 'true' : 'false';
    $configs['NOTIFY_ON_RISK_UPDATE'] = isset($_POST['notify_on_risk_update']) ? 'true' : 'false';
    $configs['NOTIFY_ON_NEW_MITIGATION'] = isset($_POST['notify_on_new_mitigation']) ? 'true' : 'false';
    $configs['NOTIFY_ON_MITIGATION_UPDATE'] = isset($_POST['notify_on_mitigation_update']) ? 'true' : 'false';
    $configs['NOTIFY_ON_REVIEW'] = isset($_POST['notify_on_review']) ? 'true' : 'false';
    $configs['NOTIFY_ON_CLOSE'] = isset($_POST['notify_on_close']) ? 'true' : 'false';
    $configs['NOTIFY_ON_RISK_COMMENT'] = isset($_POST['notify_on_risk_comment']) ? 'true' : 'false';
    $configs['NOTIFY_ON_AUDIT_COMMENT'] = isset($_POST['notify_on_audit_comment']) ? 'true' : 'false';
    $configs['NOTIFY_ON_AUDIT_STATUS_CHANGE'] = isset($_POST['notify_on_audit_status_change']) ? 'true' : 'false';    
    $configs['NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_SUBMITTER'] = isset($_POST['auto_notify_submitter']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_OWNER'] = isset($_POST['auto_notify_owner']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_OWNERS_MANAGER'] = isset($_POST['auto_notify_owners_manager']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_TEAM'] = isset($_POST['auto_notify_team']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['auto_notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_REVIEWERS'] = isset($_POST['auto_notify_reviewers']) ? 'true' : 'false';
    $configs['MITIGATION_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['mitigation_update_notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['MITIGATION_UPDATE_NOTIFY_OWNER'] = isset($_POST['mitigation_update_notify_owner']) ? 'true' : 'false';
    $configs['MITIGATION_UPDATE_NOTIFY_OWNERS_MANAGER'] = isset($_POST['mitigation_update_notify_owners_manager']) ? 'true' : 'false';
    $configs['MITIGATION_UPDATE_NOTIFY_SUBMITTER'] = isset($_POST['mitigation_update_notify_submitter']) ? 'true' : 'false';
    $configs['MITIGATION_UPDATE_NOTIFY_TEAM'] = isset($_POST['mitigation_update_notify_team']) ? 'true' : 'false';
    $configs['NEW_MITIGATION_NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['new_mitigation_notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['NEW_MITIGATION_NOTIFY_OWNER'] = isset($_POST['new_mitigation_notify_owner']) ? 'true' : 'false';
    $configs['NEW_MITIGATION_NOTIFY_OWNERS_MANAGER'] = isset($_POST['new_mitigation_notify_owners_manager']) ? 'true' : 'false';
    $configs['NEW_MITIGATION_NOTIFY_SUBMITTER'] = isset($_POST['new_mitigation_notify_submitter']) ? 'true' : 'false';
    $configs['NEW_MITIGATION_NOTIFY_TEAM'] = isset($_POST['new_mitigation_notify_team']) ? 'true' : 'false';
    $configs['NEW_RISK_NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['new_risk_notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['NEW_RISK_NOTIFY_OWNER'] = isset($_POST['new_risk_notify_owner']) ? 'true' : 'false';
    $configs['NEW_RISK_NOTIFY_OWNERS_MANAGER'] = isset($_POST['new_risk_notify_owners_manager']) ? 'true' : 'false';
    $configs['NEW_RISK_NOTIFY_SUBMITTER'] = isset($_POST['new_risk_notify_submitter']) ? 'true' : 'false';
    $configs['NEW_RISK_NOTIFY_TEAM'] = isset($_POST['new_risk_notify_team']) ? 'true' : 'false';
    $configs['RISK_CLOSE_NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['risk_close_notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['RISK_CLOSE_NOTIFY_OWNER'] = isset($_POST['risk_close_notify_owner']) ? 'true' : 'false';
    $configs['RISK_CLOSE_NOTIFY_OWNERS_MANAGER'] = isset($_POST['risk_close_notify_owners_manager']) ? 'true' : 'false';
    $configs['RISK_CLOSE_NOTIFY_SUBMITTER'] = isset($_POST['risk_close_notify_submitter']) ? 'true' : 'false';
    $configs['RISK_CLOSE_NOTIFY_TEAM'] = isset($_POST['risk_close_notify_team']) ? 'true' : 'false';
    $configs['RISK_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['risk_comment_notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['RISK_COMMENT_NOTIFY_OWNER'] = isset($_POST['risk_comment_notify_owner']) ? 'true' : 'false';
    $configs['RISK_COMMENT_NOTIFY_OWNERS_MANAGER'] = isset($_POST['risk_comment_notify_owners_manager']) ? 'true' : 'false';
    $configs['RISK_COMMENT_NOTIFY_SUBMITTER'] = isset($_POST['risk_comment_notify_submitter']) ? 'true' : 'false';
    $configs['RISK_COMMENT_NOTIFY_TEAM'] = isset($_POST['risk_comment_notify_team']) ? 'true' : 'false';
    $configs['RISK_REVIEW_NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['risk_review_notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['RISK_REVIEW_NOTIFY_OWNER'] = isset($_POST['risk_review_notify_owner']) ? 'true' : 'false';
    $configs['RISK_REVIEW_NOTIFY_OWNERS_MANAGER'] = isset($_POST['risk_review_notify_owners_manager']) ? 'true' : 'false';
    $configs['RISK_REVIEW_NOTIFY_SUBMITTER'] = isset($_POST['risk_review_notify_submitter']) ? 'true' : 'false';
    $configs['RISK_REVIEW_NOTIFY_TEAM'] = isset($_POST['risk_review_notify_team']) ? 'true' : 'false';
    $configs['RISK_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['risk_update_notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['RISK_UPDATE_NOTIFY_OWNER'] = isset($_POST['risk_update_notify_owner']) ? 'true' : 'false';
    $configs['RISK_UPDATE_NOTIFY_OWNERS_MANAGER'] = isset($_POST['risk_update_notify_owners_manager']) ? 'true' : 'false';
    $configs['RISK_UPDATE_NOTIFY_SUBMITTER'] = isset($_POST['risk_update_notify_submitter']) ? 'true' : 'false';
    $configs['RISK_UPDATE_NOTIFY_TEAM'] = isset($_POST['risk_update_notify_team']) ? 'true' : 'false';
    
    // Notify on Audit Status Change
    $configs['AUDIT_COMMENT_NOTIFY_TESTER'] = isset($_POST['audit_comment_notify_tester']) ? 'true' : 'false';
    $configs['AUDIT_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['audit_comment_notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['AUDIT_COMMENT_NOTIFY_CONTROL_OWNER'] = isset($_POST['audit_comment_notify_control_owner']) ? 'true' : 'false';
    $configs['AUDIT_STATUS_CHANGE_NOTIFY_TESTER'] = isset($_POST['audit_status_change_notify_tester']) ? 'true' : 'false';
    $configs['AUDIT_STATUS_CHANGE_NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['audit_status_change_notify_additional_stakeholders']) ? 'true' : 'false';
    $configs['AUDIT_STATUS_CHANGE_NOTIFY_CONTROL_OWNER'] = isset($_POST['audit_status_change_notify_control_owner']) ? 'true' : 'false';

    // Notify on Review of Policy and Control Exceptions
    if(isset($_POST['cron_policy_control_exception_review_period'])){
        $configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD']     = $_POST['cron_policy_control_exception_review_period'];
    }
    
    if(isset($_POST['cron_policy_control_exception_review_hour'])){
        $configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_HOUR']     = $_POST['cron_policy_control_exception_review_hour'];
    }
    
    if(isset($_POST['cron_policy_control_exception_review_minute'])){
        $configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MINUTE']     = $_POST['cron_policy_control_exception_review_minute'];
    }
    
    if(isset($_POST['cron_policy_control_exception_review_day_of_week'])){
        $configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_DAY_OF_WEEK']     = $_POST['cron_policy_control_exception_review_day_of_week'];
    }
    
    if(isset($_POST['cron_policy_control_exception_review_month'])){
        $configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MONTH']     = $_POST['cron_policy_control_exception_review_month'];
    }
    
    if(isset($_POST['cron_policy_control_exception_review_date'])){
        $configs['CRON_POLICY_CONTROL_EXCEPTION_REVIEW_DATE']     = $_POST['cron_policy_control_exception_review_date'];
    }
    
    $configs['POLICY_CONTROL_EXCEPTION_REVIEW_CONTROL_OWNER'] = isset($_POST['policy_control_exception_review_control_owner']) ? 'true' : 'false';
    $configs['POLICY_CONTROL_EXCEPTION_REVIEW_POLICY_OWNER'] = isset($_POST['policy_control_exception_review_policy_owner']) ? 'true' : 'false';
    $configs['POLICY_CONTROL_EXCEPTION_REVIEW_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['policy_control_exception_review_additional_stakeholders']) ? 'true' : 'false';
    $configs['POLICY_CONTROL_EXCEPTION_REVIEW_APPROVER'] = isset($_POST['policy_control_exception_review_approver']) ? 'true' : 'false';
    
    // Auto risk schedule
    $configs['NOTIFICATION_SEND_AUDIT_HOUR'] = isset($_POST['audit_schedule_hour']) ? $_POST['audit_schedule_hour'] : '0';
    $configs['NOTIFICATION_SEND_AUDIT_MINUTE'] = isset($_POST['audit_schedule_minute']) ? $_POST['audit_schedule_minute'] : '0';
    $configs['NOTIFICATION_SEND_AUDIT_EMAIL_1'] = isset($_POST['send_audit_email_1']) ? 'true' : 'false';
    $configs['NOTIFICATION_SEND_AUDIT_EMAIL_1_VALUE'] = isset($_POST['notification_audit_days_1']) ? $_POST['notification_audit_days_1'] : '14';
    $configs['NOTIFICATION_SEND_AUDIT_EMAIL_2'] = isset($_POST['send_audit_email_2']) ? 'true' : 'false';
    $configs['NOTIFICATION_SEND_AUDIT_EMAIL_2_VALUE'] = isset($_POST['notification_audit_days_2']) ? $_POST['notification_audit_days_2'] : '7';
    $configs['NOTIFICATION_SEND_AUDIT_EMAIL_3'] = isset($_POST['send_audit_email_3']) ? 'true' : 'false';
    $configs['NOTIFICATION_SEND_AUDIT_EMAIL_3_VALUE'] = isset($_POST['notification_audit_days_3']) ? $_POST['notification_audit_days_3'] : '2';
    $configs['NOTIFICATION_SEND_AUDIT_DUE_EMAIL'] = isset($_POST['send_audit_due_email']) ? 'true' : 'false';
    $configs['NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL'] = isset($_POST['send_audit_after_due_email']) ? 'true' : 'false';
    $configs['NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL_VALUE'] = isset($_POST['notification_audit_after_due_days']) ? $_POST['notification_audit_after_due_days'] : '1';
    $configs['AUTO_NOTIFY_TESTER_AUDITS'] = isset($_POST['auto_notify_tester_audits']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_CONTROL_OWNER_AUDITS'] = isset($_POST['auto_notify_control_owner_audits']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_AUDITS'] = isset($_POST['auto_notify_additional_stakeholders_audits']) ? 'true' : 'false';

    $configs['NOTIFICATION_SEND_MITIGATION_HOUR'] = isset($_POST['mitigation_schedule_hour']) ? $_POST['mitigation_schedule_hour'] : '0';
    $configs['NOTIFICATION_SEND_MITIGATION_MINUTE'] = isset($_POST['mitigation_schedule_minute']) ? $_POST['mitigation_schedule_minute'] : '0';
    $configs['NOTIFICATION_SEND_MITIGATION_EMAIL_1'] = isset($_POST['send_mitigation_email_1']) ? 'true' : 'false';
    $configs['NOTIFICATION_SEND_MITIGATION_EMAIL_1_VALUE'] = isset($_POST['notification_mitigation_days_1']) ? $_POST['notification_mitigation_days_1'] : '14';
    $configs['NOTIFICATION_SEND_MITIGATION_EMAIL_2'] = isset($_POST['send_mitigation_email_2']) ? 'true' : 'false';
    $configs['NOTIFICATION_SEND_MITIGATION_EMAIL_2_VALUE'] = isset($_POST['notification_mitigation_days_2']) ? $_POST['notification_mitigation_days_2'] : '7';
    $configs['NOTIFICATION_SEND_MITIGATION_EMAIL_3'] = isset($_POST['send_mitigation_email_3']) ? 'true' : 'false';
    $configs['NOTIFICATION_SEND_MITIGATION_EMAIL_3_VALUE'] = isset($_POST['notification_mitigation_days_3']) ? $_POST['notification_mitigation_days_3'] : '2';
    $configs['NOTIFICATION_SEND_MITIGATION_DUE_EMAIL'] = isset($_POST['send_mitigation_due_email']) ? 'true' : 'false';
    $configs['NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL'] = isset($_POST['send_mitigation_after_due_email']) ? 'true' : 'false';
    $configs['NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL_VALUE'] = isset($_POST['notification_mitigation_after_due_days']) ? $_POST['notification_mitigation_after_due_days'] : '1';
    
    $configs['AUTO_NOTIFY_MITIGATION_OWNER_PLANNED_MITIGATION'] = isset($_POST['auto_notify_mitigation_owner_planned_mitigation']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_MITIGATION_TEAM_PLANNED_MITIGATION'] = isset($_POST['auto_notify_mitigation_team_planned_mitigation']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_RISK_SUBMITTER_PLANNED_MITIGATION'] = isset($_POST['auto_notify_risk_submitter_planned_mitigation']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_RISK_OWNER_PLANNED_MITIGATION'] = isset($_POST['auto_notify_risk_owner_planned_mitigation']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_RISK_OWNERS_MANAGER_PLANNED_MITIGATION'] = isset($_POST['auto_notify_risk_owners_manager_planned_mitigation']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_RISK_TEAM_PLANNED_MITIGATION'] = isset($_POST['auto_notify_risk_team_planned_mitigation']) ? 'true' : 'false';
    $configs['AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_PLANNED_MITIGATION'] = isset($_POST['auto_notify_additional_stakeholders_planned_mitigation']) ? 'true' : 'false';
    
    // SET CRON CONFIGS
    if(isset($_POST['cron_period'])){
        $configs['CRON_PERIOD']     = $_POST['cron_period'];
    }
    
    if(isset($_POST['cron_hour'])){
        $configs['CRON_HOUR']     = $_POST['cron_hour'];
    }
    
    if(isset($_POST['cron_minute'])){
        $configs['CRON_MINUTE']     = $_POST['cron_minute'];
    }
    
    if(isset($_POST['cron_day_of_week'])){
        $configs['CRON_DAY_OF_WEEK']     = $_POST['cron_day_of_week'];
    }
    
    if(isset($_POST['cron_month'])){
        $configs['CRON_MONTH']     = $_POST['cron_month'];
    }
    
    if(isset($_POST['cron_date'])){
        $configs['CRON_DATE']     = $_POST['cron_date'];
    }
    
    // Update non mitigation configs
    if(isset($_POST['cron_non_mitigation_period']))
    {
        $configs['CRON_NON_MITIGATION_PERIOD'] = $_POST['cron_non_mitigation_period'];
    }
    if(isset($_POST['cron_non_mitigation_hour']))
    {
        $configs['CRON_NON_MITIGATION_HOUR'] = $_POST['cron_non_mitigation_hour'];
    }
    if(isset($_POST['cron_non_mitigation_minute']))
    {
        $configs['CRON_NON_MITIGATION_MINUTE'] = $_POST['cron_non_mitigation_minute'];
    }
    if(isset($_POST['cron_non_mitigation_day_of_week']))
    {
        $configs['CRON_NON_MITIGATION_DAY_OF_WEEK'] = $_POST['cron_non_mitigation_day_of_week'];
    }
    if(isset($_POST['cron_non_mitigation_date']))
    {
        $configs['CRON_NON_MITIGATION_DATE'] = $_POST['cron_non_mitigation_date'];
    }
    if(isset($_POST['cron_non_mitigation_month']))
    {
        $configs['CRON_NON_MITIGATION_MONTH'] = $_POST['cron_non_mitigation_month'];
    }

    $configs['NON_MITIGATION_AUTO_NOTIFY_SUBMITTER'] = isset($_POST['non_mitigation_auto_notify_submitter']) ? 'true' : 'false';
    $configs['NON_MITIGATION_AUTO_NOTIFY_OWNER'] = isset($_POST['non_mitigation_auto_notify_owner']) ? 'true' : 'false';
    $configs['NON_MITIGATION_AUTO_NOTIFY_OWNERS_MANAGER'] = isset($_POST['non_mitigation_auto_notify_owners_manager']) ? 'true' : 'false';
    $configs['NON_MITIGATION_AUTO_NOTIFY_TEAM'] = isset($_POST['non_mitigation_auto_notify_team']) ? 'true' : 'false';
    $configs['NON_MITIGATION_AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS'] = isset($_POST['non_mitigation_auto_notify_additional_stakeholders']) ? 'true' : 'false';
    
    // Update the settings
    update_settings($configs);
}

/***************************************
 * FUNCTION: GET NOTIFICATION SETTINGS *
 ***************************************/
function get_notification_settings()
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `settings` WHERE `name` = 'VERBOSE' OR `name` = 'simplerisk_base_url' OR `name` = 'NOTIFY_SUBMITTER' OR `name` = 'NOTIFY_OWNER' OR `name` = 'NOTIFY_OWNERS_MANAGER' OR `name` = 'NOTIFY_TEAM' OR `name` = 'NOTIFY_ON_NEW_RISK' OR `name` = 'NOTIFY_ON_RISK_UPDATE' OR `name` = 'NOTIFY_ON_NEW_MITIGATION' OR `name` = 'NOTIFY_ON_MITIGATION_UPDATE' OR `name` = 'NOTIFY_ON_REVIEW' OR `name` = 'NOTIFY_ON_CLOSE' OR `name` = 'NOTIFY_ON_RISK_COMMENT' OR `name` = 'NOTIFY_ON_AUDIT_COMMENT' OR `name` = 'NOTIFY_ON_AUDIT_STATUS_CHANGE' OR `name` = 'NOTIFY_ADDITIONAL_STAKEHOLDERS' OR `name` = 'CRON_PERIOD' OR `name` = 'CRON_HOUR' OR `name` = 'CRON_MINUTE' OR `name` = 'CRON_MONTH' OR `name` = 'CRON_DATE' OR `name` = 'CRON_DAY_OF_WEEK' OR `name` = 'AUTO_NOTIFY_SUBMITTER' OR `name` = 'AUTO_NOTIFY_OWNER' OR `name` = 'AUTO_NOTIFY_OWNERS_MANAGER' OR `name` = 'AUTO_NOTIFY_TEAM' OR `name` = 'AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS' OR `name` = 'AUTO_NOTIFY_REVIEWERS' OR `name` LIKE 'MITIGATION_UPDATE_NOTIFY_%' OR `name` LIKE 'NEW_MITIGATION_NOTIFY_%' OR `name` LIKE 'NEW_RISK_NOTIFY_%' OR `name` LIKE 'RISK_CLOSE_NOTIFY_%' OR `name` LIKE 'RISK_COMMENT_NOTIFY_%' OR `name` LIKE 'RISK_REVIEW_NOTIFY_%' OR `name` LIKE 'RISK_UPDATE_NOTIFY_%' OR `name` LIKE 'AUDIT_COMMENT_NOTIFY_%' OR `name` LIKE 'AUDIT_STATUS_CHANGE_NOTIFY_%' OR `name` LIKE 'NOTIFICATION_SEND_AUDIT_%' OR `name` LIKE 'NOTIFICATION_SEND_MITIGATION_%'  OR `name` LIKE 'AUTO_NOTIFY_%' OR `name` LIKE '%NON_MITIGATION%' OR `name` LIKE '%POLICY_CONTROL_EXCEPTION_%';");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/*********************************************
 * FUNCTION: CREATE HOUR AND MINUTE DROPDOWN *
 *********************************************/
function create_time_html($configArray, $enabled = true, $hour_name="cron_hour", $minute_name="cron_minute"){
    $configs = [];
    foreach($configArray as $config){
        $configs[$config['name']] = $config['value'];
    }
    
    $html = "<span>Time</span>: <select name='{$hour_name}' ".($enabled ? "" : " disabled ").">";
    foreach(range(0, 23) as $value){
        if(isset($configs[strtoupper($hour_name)]) && $configs[strtoupper($hour_name)] == $value){
            $html .= "<option selected value='{$value}'>{$value}</option>";
        }else{
            $html .= "<option value='{$value}'>{$value}</option>";
        }
    }
    $html .= "</select>";
    
    $html .= "&nbsp;&nbsp; : &nbsp;&nbsp;";
    
    $html .= "<select name='{$minute_name}' ".($enabled ? "" : " disabled ").">";
    foreach(range(0, 59) as $value){
        if(isset($configs[strtoupper($minute_name)]) && $configs[strtoupper($minute_name)] == $value){
            $html .= "<option selected value='{$value}'>{$value}</option>";
        }else{
            $html .= "<option value='{$value}'>{$value}</option>";
        }
    }
    $html .= "</select>";
    
    return $html;
}

/*********************************************
 * FUNCTION: CREATE DAY OF WEEK DROPDOWN *
 *********************************************/
function create_day_of_week_html($configArray, $enabled = true, $weekname="cron_day_of_week"){
    $configs = [];
    foreach($configArray as $config){
        $configs[$config['name']] = $config['value'];
    }

    $timestamp = strtotime('next Sunday');

    $html = "<span>Day of Week:</span> <select name='{$weekname}' ".($enabled ? "" : " disabled ").">";
    for ($i = 0; $i < 7; $i++) {
        if(isset($configs[strtoupper($weekname)]) && $configs[strtoupper($weekname)] == $i){
            $html .= "<option selected value='{$i}'>".strftime('%A', $timestamp)."</option>";
        }else{
            $html .= "<option value='{$i}'>".strftime('%A', $timestamp)."</option>";
        }
        $timestamp = strtotime('+1 day', $timestamp);
    }
    $html .= "</select>";
    return $html;
}

/*********************************************
 * FUNCTION: CREATE DATE DROPDOWN *
 *********************************************/
function create_date_html($configArray, $enabled = true, $name="cron_date"){
    $configs = [];
    foreach($configArray as $config){
        $configs[$config['name']] = $config['value'];
    }

    $html = "<span>Date:</span> <select name='{$name}' ".($enabled ? "" : " disabled ").">";
    foreach(range(1, 31) as $value){
        if(isset($configs[strtoupper($name)]) && $configs[strtoupper($name)] == $value){
            $html .= "<option selected value='{$value}'>{$value}</option>";
        }else{
            $html .= "<option value='{$value}'>{$value}</option>";
        }
    }
    $html .= "</select>";
    
    return $html;
}

/*********************************************
 * FUNCTION: CREATE MONTHS AND DATE DROPDOWN *
 *********************************************/
function create_day_html($configArray, $enabled = true, $month_name="cron_month", $date_name="cron_date"){
    $configs = [];
    foreach($configArray as $config){
        $configs[$config['name']] = $config['value'];
    }

    $html = "<span>Day</span>: <select name='{$month_name}' ".($enabled ? "" : " disabled ").">";
    for ($m=1; $m<=12; $m++) {
        $month = date('F', mktime(0,0,0,$m, 1, date('Y')));
        if(isset($configs[strtoupper($month_name)]) && $configs[strtoupper($month_name)] == $m){
            $html .= "<option selected value='{$m}'>{$month}</option>";
        }else{
            $html .= "<option value='{$m}'>{$month}</option>";
        }
    }
    $html .= "</select>";
    
    $html .= "&nbsp;&nbsp; ";
    
    $html .= "<select name='{$date_name}' ".($enabled ? "" : " disabled ").">";
    foreach(range(1, 31) as $value){
        if(isset($configs[strtoupper($date_name)]) && $configs[strtoupper($date_name)] == $value){
            $html .= "<option selected value='{$value}'>{$value}</option>";
        }else{
            $html .= "<option value='{$value}'>{$value}</option>";
        }

    }
    $html .= "</select>";
    
    return $html;
}

/**********************************
 * FUNCTION: DISPLAY NOTIFICATION *
 **********************************/
function display_notification()
{
    global $escaper;
    global $lang;

    echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . notification_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";

    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    echo "
        <script>
            function check_uncheck_values(element_name, checkbox_name)
            {
                var elements = document.getElementsByClassName(element_name);
                var checkbox = document.getElementById(checkbox_name);

                if (checkbox.checked)
                {
                    for (var i = 0; i < elements.length; i++)
                    {
                            elements[i].style.display = '';
                    }
                }
                else
                {
                    for (var i = 0; i < elements.length; i++)
                    {
                            elements[i].style.display = 'none';
                    }
                }
            }
        </script>
    ";

        echo "<form name=\"notification_extra\" method=\"post\" action=\"\">\n";

        echo "<table border=\"1\" width=\"800\" cellpadding=\"10px\">\n";
    echo "<tr><td>\n";
    echo "<table border=\"0\" width=\"100%\">\n";
        echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['HowToNotify']) . "</u></h4></td></tr>\n";
        echo "<tr>\n";
            echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"verbose\" id=\"verbose\"" . ($VERBOSE == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['VerboseEmails']) . "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";
    echo "</table>\n";

    echo "<br />\n";

        echo "<table border=\"1\" width=\"800\" cellpadding=\"10px\">\n";
        echo "<tr align=\"center\"><td><h3>" . $escaper->escapehtml($lang['SimpleRiskActionNotifications']) . "</h3></td></tr>\n";

    // Notify on New Risk
    echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
    echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['NotifyOnNewRisk']) . "</u></h4></td></tr>\n";
    echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhenToNotify']) . ":</strong></u></td></tr>\n";
    echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"notify_on_new_risk\" id=\"notify_on_new_risk\" onchange=\"check_uncheck_values('who_notify_on_new_risk', 'notify_on_new_risk');\"" . ($NOTIFY_ON_NEW_RISK == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOnNewRisk']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_new_risk\"" . ($NOTIFY_ON_NEW_RISK == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr class=\"who_notify_on_new_risk\"" . ($NOTIFY_ON_NEW_RISK == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"new_risk_notify_submitter\" id=\"new_risk_notify_submitter\"" . ($NEW_RISK_NOTIFY_SUBMITTER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifySubmitter']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_new_risk\"" . ($NOTIFY_ON_NEW_RISK == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"new_risk_notify_owner\" id=\"new_risk_notify_owner\"" . ($NEW_RISK_NOTIFY_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwner']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_new_risk\"" . ($NOTIFY_ON_NEW_RISK == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"new_risk_notify_owners_manager\" id=\"new_risk_notify_owners_manager\"" . ($NEW_RISK_NOTIFY_OWNERS_MANAGER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwnersManager']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_new_risk\"" . ($NOTIFY_ON_NEW_RISK == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"new_risk_notify_team\" id=\"new_risk_notify_team\"" . ($NEW_RISK_NOTIFY_TEAM == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyTeam']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_new_risk\"" . ($NOTIFY_ON_NEW_RISK == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"new_risk_notify_additional_stakeholders\" id=\"new_risk_notify_additional_stakeholders\"" . ($NEW_RISK_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyAdditionalStakeholders']) . "</td></tr>\n";
    echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";

    // Notify on Risk Update
    echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
    echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['NotifyOnRiskUpdate']) . "</u></h4></td></tr>\n";
        echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhenToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"notify_on_risk_update\" id=\"notify_on_risk_update\" onchange=\"check_uncheck_values('who_notify_on_risk_update', 'notify_on_risk_update');\"" . ($NOTIFY_ON_RISK_UPDATE == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOnRiskUpdate']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_update\"" . ($NOTIFY_ON_RISK_UPDATE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_update\"" . ($NOTIFY_ON_RISK_UPDATE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_update_notify_submitter\" id=\"risk_update_notify_submitter\"" . ($RISK_UPDATE_NOTIFY_SUBMITTER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifySubmitter']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_update\"" . ($NOTIFY_ON_RISK_UPDATE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_update_notify_owner\" id=\"risk_update_notify_owner\"" . ($RISK_UPDATE_NOTIFY_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwner']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_update\"" . ($NOTIFY_ON_RISK_UPDATE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_update_notify_owners_manager\" id=\"risk_update_notify_owners_manager\"" . ($RISK_UPDATE_NOTIFY_OWNERS_MANAGER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwnersManager']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_update\"" . ($NOTIFY_ON_RISK_UPDATE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_update_notify_team\" id=\"risk_update_notify_team\"" . ($RISK_UPDATE_NOTIFY_TEAM == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyTeam']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_update\"" . ($NOTIFY_ON_RISK_UPDATE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_update_notify_additional_stakeholders\" id=\"risk_update_notify_additional_stakeholders\"" . ($RISK_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyAdditionalStakeholders']) . "</td></tr>\n";
        echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";

    // Notify on New Mitigation
    echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
    echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['NotifyOnNewMitigation']) . "</u></h4></td></tr>\n";
        echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhenToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"notify_on_new_mitigation\" id=\"notify_on_new_mitigation\" onchange=\"check_uncheck_values('who_notify_on_new_mitigation', 'notify_on_new_mitigation');\"" . ($NOTIFY_ON_NEW_MITIGATION == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOnNewMitigation']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_new_mitigation\"" . ($NOTIFY_ON_NEW_MITIGATION == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr class=\"who_notify_on_new_mitigation\"" . ($NOTIFY_ON_NEW_MITIGATION == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"new_mitigation_notify_submitter\" id=\"new_mitigation_notify_submitter\"" . ($NEW_MITIGATION_NOTIFY_SUBMITTER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifySubmitter']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_new_mitigation\"" . ($NOTIFY_ON_NEW_MITIGATION == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"new_mitigation_notify_owner\" id=\"new_mitigation_notify_owner\"" . ($NEW_MITIGATION_NOTIFY_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwner']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_new_mitigation\"" . ($NOTIFY_ON_NEW_MITIGATION == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"new_mitigation_notify_owners_manager\" id=\"new_mitigation_notify_owners_manager\"" . ($NEW_MITIGATION_NOTIFY_OWNERS_MANAGER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwnersManager']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_new_mitigation\"" . ($NOTIFY_ON_NEW_MITIGATION == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"new_mitigation_notify_team\" id=\"new_mitigation_notify_team\"" . ($NEW_MITIGATION_NOTIFY_TEAM == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyTeam']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_new_mitigation\"" . ($NOTIFY_ON_NEW_MITIGATION == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"new_mitigation_notify_additional_stakeholders\" id=\"new_mitigation_notify_additional_stakeholders\"" . ($NEW_MITIGATION_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyAdditionalStakeholders']) . "</td></tr>\n";
        echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";

    // Notify on Mitigation update
    echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
    echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['NotifyOnMitigationUpdate']) . "</u></h4></td></tr>\n";
        echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhenToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"notify_on_mitigation_update\" id=\"notify_on_mitigation_update\" onchange=\"check_uncheck_values('who_notify_on_mitigation_update', 'notify_on_mitigation_update');\"" . ($NOTIFY_ON_MITIGATION_UPDATE == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOnMitigationUpdate']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_mitigation_update\"" . ($NOTIFY_ON_MITIGATION_UPDATE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr class=\"who_notify_on_mitigation_update\"" . ($NOTIFY_ON_MITIGATION_UPDATE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"mitigation_update_notify_submitter\" id=\"mitigation_update_notify_submitter\"" . ($MITIGATION_UPDATE_NOTIFY_SUBMITTER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifySubmitter']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_mitigation_update\"" . ($NOTIFY_ON_MITIGATION_UPDATE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"mitigation_update_notify_owner\" id=\"mitigation_update_notify_owner\"" . ($MITIGATION_UPDATE_NOTIFY_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwner']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_mitigation_update\"" . ($NOTIFY_ON_MITIGATION_UPDATE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"mitigation_update_notify_owners_manager\" id=\"mitigation_update_notify_owners_manager\"" . ($MITIGATION_UPDATE_NOTIFY_OWNERS_MANAGER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwnersManager']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_mitigation_update\"" . ($NOTIFY_ON_MITIGATION_UPDATE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"mitigation_update_notify_team\" id=\"mitigation_update_notify_team\"" . ($MITIGATION_UPDATE_NOTIFY_TEAM == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyTeam']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_mitigation_update\"" . ($NOTIFY_ON_MITIGATION_UPDATE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"mitigation_update_notify_additional_stakeholders\" id=\"mitigation_update_notify_additional_stakeholders\"" . ($MITIGATION_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyAdditionalStakeholders']) . "</td></tr>\n";
        echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";

    // Notify on Risk Review
    echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
    echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['NotifyOnRiskReview']) . "</u></h4></td></tr>\n";
        echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhenToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"notify_on_review\" id=\"notify_on_review\" onchange=\"check_uncheck_values('who_notify_on_risk_review', 'notify_on_review');\"" . ($NOTIFY_ON_REVIEW == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOnRiskReview']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_review\"" . ($NOTIFY_ON_REVIEW == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_review\"" . ($NOTIFY_ON_REVIEW == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_review_notify_submitter\" id=\"risk_review_notify_submitter\"" . ($RISK_REVIEW_NOTIFY_SUBMITTER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifySubmitter']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_review\"" . ($NOTIFY_ON_REVIEW == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_review_notify_owner\" id=\"risk_review_notify_owner\"" . ($RISK_REVIEW_NOTIFY_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwner']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_review\"" . ($NOTIFY_ON_REVIEW == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_review_notify_owners_manager\" id=\"risk_review_notify_owners_manager\"" . ($RISK_REVIEW_NOTIFY_OWNERS_MANAGER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwnersManager']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_review\"" . ($NOTIFY_ON_REVIEW == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_review_notify_team\" id=\"risk_review_notify_team\"" . ($RISK_REVIEW_NOTIFY_TEAM == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyTeam']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_review\"" . ($NOTIFY_ON_REVIEW == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_review_notify_additional_stakeholders\" id=\"risk_review_notify_additional_stakeholders\"" . ($RISK_REVIEW_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyAdditionalStakeholders']) . "</td></tr>\n";
        echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";

    // Notify on Risk Close
    echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
    echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['NotifyOnRiskClose']) . "</u></h4></td></tr>\n";
        echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhenToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"notify_on_close\" id=\"notify_on_close\" onchange=\"check_uncheck_values('who_notify_on_risk_close', 'notify_on_close');\"" . ($NOTIFY_ON_CLOSE == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOnRiskClose']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_close\"" . ($NOTIFY_ON_CLOSE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_close\"" . ($NOTIFY_ON_CLOSE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_close_notify_submitter\" id=\"risk_close_notify_submitter\"" . ($RISK_CLOSE_NOTIFY_SUBMITTER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifySubmitter']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_close\"" . ($NOTIFY_ON_CLOSE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_close_notify_owner\" id=\"risk_close_notify_owner\"" . ($RISK_CLOSE_NOTIFY_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwner']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_close\"" . ($NOTIFY_ON_CLOSE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_close_notify_owners_manager\" id=\"risk_close_notify_owners_manager\"" . ($RISK_CLOSE_NOTIFY_OWNERS_MANAGER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwnersManager']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_close\"" . ($NOTIFY_ON_CLOSE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_close_notify_team\" id=\"risk_close_notify_team\"" . ($RISK_CLOSE_NOTIFY_TEAM == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyTeam']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_close\"" . ($NOTIFY_ON_CLOSE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_close_notify_additional_stakeholders\" id=\"risk_close_notify_additional_stakeholders\"" . ($RISK_CLOSE_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyAdditionalStakeholders']) . "</td></tr>\n";
        echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";

    // Notify on Risk Comment
    echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
    echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['NotifyOnRiskComment']) . "</u></h4></td></tr>\n";
        echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhenToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"notify_on_risk_comment\" id=\"notify_on_risk_comment\" onchange=\"check_uncheck_values('who_notify_on_risk_comment', 'notify_on_risk_comment');\"" . ($NOTIFY_ON_RISK_COMMENT == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOnRiskComment']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_comment\"" . ($NOTIFY_ON_RISK_COMMENT == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_comment\"" . ($NOTIFY_ON_RISK_COMMENT == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_comment_notify_submitter\" id=\"risk_comment_notify_submitter\"" . ($RISK_COMMENT_NOTIFY_SUBMITTER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifySubmitter']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_comment\"" . ($NOTIFY_ON_RISK_COMMENT == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_comment_notify_owner\" id=\"risk_comment_notify_owner\"" . ($RISK_COMMENT_NOTIFY_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwner']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_comment\"" . ($NOTIFY_ON_RISK_COMMENT == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_comment_notify_owners_manager\" id=\"risk_comment_notify_owners_manager\"" . ($RISK_COMMENT_NOTIFY_OWNERS_MANAGER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwnersManager']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_comment\"" . ($NOTIFY_ON_RISK_COMMENT == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_comment_notify_team\" id=\"risk_comment_notify_team\"" . ($RISK_COMMENT_NOTIFY_TEAM == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyTeam']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_risk_comment\"" . ($NOTIFY_ON_RISK_COMMENT == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"risk_comment_notify_additional_stakeholders\" id=\"risk_comment_notify_additional_stakeholders\"" . ($RISK_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyAdditionalStakeholders']) . "</td></tr>\n";
        echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";

    // Notify on Audit Comment
    echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
    echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['NotifyOnAuditComment']) . "</u></h4></td></tr>\n";
        echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhenToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"notify_on_audit_comment\" id=\"notify_on_audit_comment\" onchange=\"check_uncheck_values('who_notify_on_audit_comment', 'notify_on_audit_comment');\"" . ($NOTIFY_ON_AUDIT_COMMENT == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOnAuditComment']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_audit_comment\"" . ($NOTIFY_ON_AUDIT_COMMENT == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr class=\"who_notify_on_audit_comment\"" . ($NOTIFY_ON_AUDIT_COMMENT == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"audit_comment_notify_tester\" id=\"audit_comment_notify_tester\"" . ($AUDIT_COMMENT_NOTIFY_TESTER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyTester']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_audit_comment\"" . ($NOTIFY_ON_AUDIT_COMMENT == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"audit_comment_notify_additional_stakeholders\" id=\"audit_comment_notify_additional_stakeholders\"" . ($AUDIT_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyAdditionalStakeholders']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_audit_comment\"" . ($NOTIFY_ON_AUDIT_COMMENT == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"audit_comment_notify_control_owner\" id=\"audit_comment_notify_control_owner\"" . ($AUDIT_COMMENT_NOTIFY_CONTROL_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyControlOwner']) . "</td></tr>\n";
        echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";    
    
    // Notify on Audit Status Change
    echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
    echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['NotifyOnAuditStatusChange']) . "</u></h4></td></tr>\n";
        echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhenToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"notify_on_audit_status_change\" id=\"notify_on_audit_status_change\" onchange=\"check_uncheck_values('who_notify_on_audit_status_change', 'notify_on_audit_status_change');\"" . ($NOTIFY_ON_AUDIT_STATUS_CHANGE == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOnAuditStatusChange']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_audit_status_change\"" . ($NOTIFY_ON_AUDIT_STATUS_CHANGE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr class=\"who_notify_on_audit_status_change\"" . ($NOTIFY_ON_AUDIT_STATUS_CHANGE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"audit_status_change_notify_tester\" id=\"audit_status_change_notify_tester\"" . ($AUDIT_STATUS_CHANGE_NOTIFY_TESTER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyTester']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_audit_status_change\"" . ($NOTIFY_ON_AUDIT_STATUS_CHANGE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"audit_status_change_notify_additional_stakeholders\" id=\"audit_status_change_notify_additional_stakeholders\"" . ($AUDIT_STATUS_CHANGE_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyAdditionalStakeholders']) . "</td></tr>\n";
        echo "<tr class=\"who_notify_on_audit_status_change\"" . ($NOTIFY_ON_AUDIT_STATUS_CHANGE == "false" ? " style=\"display:none;\"" : " style=\"display:\"\"") . "><td colspan=\"2\"><input type=\"checkbox\" name=\"audit_status_change_notify_control_owner\" id=\"audit_status_change_notify_control_owner\"" . ($AUDIT_STATUS_CHANGE_NOTIFY_CONTROL_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyControlOwner']) . "</td></tr>\n";
        echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";

    echo "</table>\n";

    $phpExecutablePath = getPHPExecutableFromPath();

    echo "<br />\n";
    echo "<table border=\"1\" width=\"800\" cellpadding=\"10px\">\n";
    echo "<tr align=\"center\"><td><h3>" . $escaper->escapeHtml($lang['SimpleRiskScheduledNotifications']) . "</h3></td></tr>\n";
    echo "<tr><td>" . $escaper->escapeHtml($lang['PlaceTheFollowingInYourCrontabToRunAutomatically']) . ":<br />0 * * * * " . $escaper->escapeHtml($phpExecutablePath ? $phpExecutablePath : $lang['PathToPhpExecutable']) . " -f " . realpath(__DIR__ . '/index.php') . "</td></tr>\n";
    
    echo "<tr><td>\n";
    echo "<table border=\"0\" id=\"past-due-container\" width=\"100%\">\n";
    
        echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['AutomatedNotificationsOfUnreviewedPastDueRisks']) . "</u></h4></td></tr>\n";
            echo "<tr>\n";
            echo "<td colspan=\"2\"><u><strong>".$escaper->escapeHtml($lang["Schedule"]).":</strong></u><span style=\"float: right;\"><button type=\"submit\" name=\"auto_run_risk_now\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['RunNow']) . "</button></span></td>\n";
            echo "</tr>\n";
            echo "<tr>\n";
            echo "<td width=\"100px\">" . $escaper->escapeHtml($lang['Period']) . ":</td>\n";
            echo "
                <td>
                    <select name=\"cron_period\" id=\"cron_period\" class=\"period-dropdown\" >
                        <option value=\"\">--- Select Period ---</option>
                        <option value=\"daily\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='daily') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Daily"])."</option>
                        <option value=\"weekly\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='weekly') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Weekly"])."</option>
                        <option value=\"monthly\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='monthly') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Monthly"])."</option>
                        <!-- option value=\"quarterly\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='quarterly') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Quarterly"])."</option -->
                        <option value=\"annually\" ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='annually') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Annually"])."</option>
                    </select>
                </td>\n
            ";
        echo "</tr>\n";
    
        $dailySelected      = (isset($CRON_PERIOD) && $CRON_PERIOD=='daily');
        $weeklySelected     = (isset($CRON_PERIOD) && $CRON_PERIOD=='weekly');
        $monthlySelected    = (isset($CRON_PERIOD) && $CRON_PERIOD=='monthly');
        $annuallySelected   = (isset($CRON_PERIOD) && $CRON_PERIOD=='annually');
        echo "
            <tr id='specified_daily' class='specified_time_holder' ". ($dailySelected ? "style='display:table-row'" : "") .">
                <td width=\"100px\">".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                <td>
                ".create_time_html($configs, $dailySelected)."
                </td>
            </tr>
            
            <tr id='specified_weekly' class='specified_time_holder' ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='weekly') ? "style='display:table-row'" : "") .">
                <td>".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                <td>
                ".create_day_of_week_html($configs, $weeklySelected)."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $weeklySelected)."
                </td>
            </tr>

            <tr id='specified_monthly' class='specified_time_holder' ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='monthly') ? "style='display:table-row'" : "") .">
                <td>".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                <td>
                ".create_date_html($configs, $monthlySelected)."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $monthlySelected)."
                </td>
            </tr>
            
            <tr id='specified_annually' class='specified_time_holder' ". ((isset($CRON_PERIOD) && $CRON_PERIOD=='annually') ? "style='display:table-row'" : "") .">
                <td>".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                <td>
                ".create_day_html($configs, $annuallySelected)."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $annuallySelected)."
                </td>
            </tr>
        ";
    echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_submitter\" id=\"auto_notify_submitter\"" . ($AUTO_NOTIFY_SUBMITTER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifySubmitter']) . "</td></tr>\n";
    echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_owner\" id=\"auto_notify_owner\"" . ($AUTO_NOTIFY_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwner']) . "</td></tr>\n";
    echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_owners_manager\" id=\"auto_notify_owners_manager\"" . ($AUTO_NOTIFY_OWNERS_MANAGER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwnersManager']) . "</td></tr>\n";
    echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_team\" id=\"auto_notify_team\"" . ($AUTO_NOTIFY_TEAM == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyTeam']) . "</td></tr>\n";
    echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_additional_stakeholders\" id=\"additional_stakeholders\"" . ($AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyAdditionalStakeholders']) . "</td></tr>\n";
    echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_reviewers\" id=\"reviewers\"" . ($AUTO_NOTIFY_REVIEWERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyReviewers']) . "</td></tr>\n";
    echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";

        
        
    // Automated notification of Planned mitigations
    echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
          echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['AutomatedNotificationOfPlannedMitigations']) . "</u></h4></td></tr>\n";
          echo "<tr>\n";
              echo "<td colspan=\"2\"><u><strong>".$escaper->escapeHtml($lang["Schedule"]).":</strong></u><span style=\"float: right;\"><button type=\"submit\" name=\"auto_run_mitigation_now\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['RunNow']) . "</button></span></td>\n";
              echo "</tr>\n";
          echo "<tr id='specified_daily' class='specified_time_holder' style='display:table-row'>\n";
              echo "<td colspan=\"2\">\n";

              echo "<span>" . $escaper->escapeHtml($lang['RunAt']) . "</span>:&nbsp;&nbsp;<select name='mitigation_schedule_hour'>\n";

              foreach(range(0, 23) as $value)
              {
                  if(isset($NOTIFICATION_SEND_MITIGATION_HOUR) && $NOTIFICATION_SEND_MITIGATION_HOUR == $value)
                  {
                      echo "<option selected value='{$value}'>{$value}</option>\n";
                  }
                  else
                  {
                      echo "<option value='{$value}'>{$value}</option>\n";
                  }
              }

              echo "</select>\n";

              echo "&nbsp; : &nbsp;\n";

              echo "<select name='mitigation_schedule_minute'>\n";

              foreach(range(0, 59) as $value)
              {
                  if(isset($NOTIFICATION_SEND_MITIGATION_MINUTE) && $NOTIFICATION_SEND_MITIGATION_MINUTE == $value)
                  {
                      echo "<option selected value='{$value}'>{$value}</option>\n";
                  }
                  else
                  {
                      echo "<option value='{$value}'>{$value}</option>\n";
                  }
              }

              echo "</select>\n";
              echo "</td>";
          echo "</tr>";

          echo "<tr>\n";
          echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"send_mitigation_email_1\" id=\"send_mitigation_email_1\"" . ($NOTIFICATION_SEND_MITIGATION_EMAIL_1 == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['SendAnEmail']) . "&nbsp;&nbsp;<input type=\"number\" min=\"0\" style=\"width:60px;text-align:right;\" name=\"notification_mitigation_days_1\" id=\"notification_mitigation_days_1\" value=\"" . $escaper->escapeHtml($NOTIFICATION_SEND_MITIGATION_EMAIL_1_VALUE) . "\" />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['DaysBeforeTheMitigationIsDue']) . "</td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
              echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"send_mitigation_email_2\" id=\"send_mitigation_email_2\"" . ($NOTIFICATION_SEND_MITIGATION_EMAIL_2 == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['SendAnEmail']) . "&nbsp;&nbsp;<input type=\"number\" min=\"0\" style=\"width:60px;text-align:right;\" name=\"notification_mitigation_days_2\" id=\"notification_mitigation_days_2\" value=\"" . $escaper->escapeHtml($NOTIFICATION_SEND_MITIGATION_EMAIL_2_VALUE) . "\" />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['DaysBeforeTheMitigationIsDue']) . "</td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
              echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"send_mitigation_email_3\" id=\"send_mitigation_email_3\"" . ($NOTIFICATION_SEND_MITIGATION_EMAIL_3 == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['SendAnEmail']) . "&nbsp;&nbsp;<input type=\"number\" min=\"0\" style=\"width:60px;text-align:right;\" name=\"notification_mitigation_days_3\" id=\"notification_mitigation_days_3\" value=\"" . $escaper->escapeHtml($NOTIFICATION_SEND_MITIGATION_EMAIL_3_VALUE) . "\" />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['DaysBeforeTheMitigationIsDue']) . "</td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
              echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"send_mitigation_due_email\" id=\"send_mitigation_due_email\"" . ($NOTIFICATION_SEND_MITIGATION_DUE_EMAIL == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['SendAnEmailWhenTheMitigationIsDue']) . "</td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
              echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"send_mitigation_after_due_email\" id=\"send_mitigation_after_due_email\"" . ($NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['SendAnEmailEvery']) . "&nbsp;&nbsp;<input type=\"number\" min=\"0\" style=\"width:60px;text-align:right;\" name=\"notification_mitigation_after_due_days\" id=\"notification_mitigation_after_due_days\" value=\"" . $escaper->escapeHtml($NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL_VALUE) . "\" disabled />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['DaysAfterTheMitigationIsDue']) . "</td>\n";
          echo "</tr>\n";


    echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_mitigation_owner_planned_mitigation\" id=\"auto_notify_mitigation_owner_planned_mitigation\"" . ($AUTO_NOTIFY_MITIGATION_OWNER_PLANNED_MITIGATION == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyMitigationOwner']) . "</td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_mitigation_team_planned_mitigation\" id=\"auto_notify_mitigation_team_planned_mitigation\"" . ($AUTO_NOTIFY_MITIGATION_TEAM_PLANNED_MITIGATION == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyMitigationTeam']) . "</td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_risk_submitter_planned_mitigation\" id=\"auto_notify_risk_submitter_planned_mitigation\"" . ($AUTO_NOTIFY_RISK_SUBMITTER_PLANNED_MITIGATION == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyRiskSubmitter']) . "</td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_risk_owner_planned_mitigation\" id=\"auto_notify_risk_owner_planned_mitigation\"" . ($AUTO_NOTIFY_RISK_OWNER_PLANNED_MITIGATION == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyRiskOwner']) . "</td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_risk_owners_manager_planned_mitigation\" id=\"auto_notify_risk_owners_manager_planned_mitigation\"" . ($AUTO_NOTIFY_RISK_OWNERS_MANAGER_PLANNED_MITIGATION == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyRiskOwnersManager']) . "</td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_risk_team_planned_mitigation\" id=\"auto_notify_risk_team_planned_mitigation\"" . ($AUTO_NOTIFY_RISK_TEAM_PLANNED_MITIGATION == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyRiskTeam']) . "</td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_additional_stakeholders_planned_mitigation\" id=\"auto_notify_additional_stakeholders_planned_mitigation\"" . ($AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_PLANNED_MITIGATION == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyRiskAdditionalStakeholders']) . "</td></tr>\n";
          
        echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";
    
    
    
    
    
    echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
          echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['AutomatedNotificationsOfAudits']) . "</u></h4></td></tr>\n";
          echo "<tr>\n";
              echo "<td colspan=\"2\"><u><strong>".$escaper->escapeHtml($lang["Schedule"]).":</strong></u><span style=\"float: right;\"><button type=\"submit\" name=\"auto_run_audit_now\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['RunNow']) . "</button></span></td>\n";
              echo "</tr>\n";
          echo "<tr id='specified_daily' class='specified_time_holder' style='display:table-row'>\n";
              echo "<td colspan=\"2\">\n";

              echo "<span>" . $escaper->escapeHtml($lang['RunAt']) . "</span>:&nbsp;&nbsp;<select name='audit_schedule_hour'>\n";

              foreach(range(0, 23) as $value)
              {
                  if(isset($NOTIFICATION_SEND_AUDIT_HOUR) && $NOTIFICATION_SEND_AUDIT_HOUR == $value)
                  {
                      echo "<option selected value='{$value}'>{$value}</option>\n";
                  }
                  else
                  {
                      echo "<option value='{$value}'>{$value}</option>\n";
                  }
              }

              echo "</select>\n";

              echo "&nbsp; : &nbsp;\n";

              echo "<select name='audit_schedule_minute'>\n";

              foreach(range(0, 59) as $value)
              {
                  if(isset($NOTIFICATION_SEND_AUDIT_MINUTE) && $NOTIFICATION_SEND_AUDIT_MINUTE == $value)
                  {
                      echo "<option selected value='{$value}'>{$value}</option>\n";
                  }
                  else
                  {
                      echo "<option value='{$value}'>{$value}</option>\n";
                  }
              }

              echo "</select>\n";
              echo "</td>";
          echo "</tr>";

          echo "<tr>\n";
          echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"send_audit_email_1\" id=\"send_audit_email_1\"" . ($NOTIFICATION_SEND_AUDIT_EMAIL_1 == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['SendAnEmail']) . "&nbsp;&nbsp;<input type=\"number\" min=\"0\" style=\"width:60px;text-align:right;\" name=\"notification_audit_days_1\" id=\"notification_audit_days_1\" value=\"" . $escaper->escapeHtml($NOTIFICATION_SEND_AUDIT_EMAIL_1_VALUE) . "\" />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['DaysBeforeTheAuditIsDue']) . "</td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
              echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"send_audit_email_2\" id=\"send_audit_email_2\"" . ($NOTIFICATION_SEND_AUDIT_EMAIL_2 == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['SendAnEmail']) . "&nbsp;&nbsp;<input type=\"number\" min=\"0\" style=\"width:60px;text-align:right;\" name=\"notification_audit_days_2\" id=\"notification_audit_days_2\" value=\"" . $escaper->escapeHtml($NOTIFICATION_SEND_AUDIT_EMAIL_2_VALUE) . "\" />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['DaysBeforeTheAuditIsDue']) . "</td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
              echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"send_audit_email_3\" id=\"send_audit_email_3\"" . ($NOTIFICATION_SEND_AUDIT_EMAIL_3 == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['SendAnEmail']) . "&nbsp;&nbsp;<input type=\"number\" min=\"0\" style=\"width:60px;text-align:right;\" name=\"notification_audit_days_3\" id=\"notification_audit_days_3\" value=\"" . $escaper->escapeHtml($NOTIFICATION_SEND_AUDIT_EMAIL_3_VALUE) . "\" />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['DaysBeforeTheAuditIsDue']) . "</td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
              echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"send_audit_due_email\" id=\"send_audit_due_email\"" . ($NOTIFICATION_SEND_AUDIT_DUE_EMAIL == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['SendAnEmailWhenTheAuditIsDue']) . "</td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
              echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"send_audit_after_due_email\" id=\"send_audit_after_due_email\"" . ($NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['SendAnEmailEvery']) . "&nbsp;&nbsp;<input type=\"number\" min=\"0\" style=\"width:60px;text-align:right;\" name=\"notification_audit_after_due_days\" id=\"notification_audit_after_due_days\" value=\"" . $escaper->escapeHtml($NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL_VALUE) . "\" disabled />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['DaysAfterTheAuditIsDue']) . "</td>\n";
          echo "</tr>\n";
          echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
          echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_tester_audits\" id=\"auto_notify_tester_audits\"" . ($AUTO_NOTIFY_TESTER_AUDITS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyTester']) . "</td></tr>\n";
          echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_control_owner_audits\" id=\"auto_notify_control_owner_audits\"" . ($AUTO_NOTIFY_CONTROL_OWNER_AUDITS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyControlOwner']) . "</td></tr>\n";
          echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"auto_notify_additional_stakeholders_audits\" id=\"auto_notify_additional_stakeholders_audits\"" . ($AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_AUDITS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyRiskAdditionalStakeholders']) . "</td></tr>\n";

        echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";
    
    
    
    
    echo "<tr><td>\n";
    echo "<table border=\"0\" id=\"non-mitigation-container\" width=\"100%\">\n";
    
        echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['AutomatedNotificationsOfUnmitigatedRisks']) . "</u></h4></td></tr>\n";
        echo "<tr>\n";
            echo "<td colspan=\"2\"><u><strong>".$escaper->escapeHtml($lang["Schedule"]).":</strong></u><span style=\"float: right;\"><button type=\"submit\" name=\"non_mitigation_auto_run_risk_now\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['RunNow']) . "</button></span></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
            echo "<td width=\"100px\">" . $escaper->escapeHtml($lang['Period']) . ":</td>\n";
            echo "
                <td>
                    <select name=\"cron_non_mitigation_period\" id=\"cron_non_mitigation_period\" class=\"period-dropdown\" >
                        <option value=\"\">--- Select Period ---</option>
                        <option value=\"daily\" ". ((isset($CRON_NON_MITIGATION_PERIOD) && $CRON_NON_MITIGATION_PERIOD=='daily') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Daily"])."</option>
                        <option value=\"weekly\" ". ((isset($CRON_NON_MITIGATION_PERIOD) && $CRON_NON_MITIGATION_PERIOD=='weekly') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Weekly"])."</option>
                        <option value=\"monthly\" ". ((isset($CRON_NON_MITIGATION_PERIOD) && $CRON_NON_MITIGATION_PERIOD=='monthly') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Monthly"])."</option>
                        <!-- option value=\"quarterly\" ". ((isset($CRON_NON_MITIGATION_PERIOD) && $CRON_NON_MITIGATION_PERIOD=='quarterly') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Quarterly"])."</option -->
                        <option value=\"annually\" ". ((isset($CRON_NON_MITIGATION_PERIOD) && $CRON_NON_MITIGATION_PERIOD=='annually') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Annually"])."</option>
                    </select>
                </td>\n
            ";
        echo "</tr>\n";
    
        $dailySelected      = (isset($CRON_NON_MITIGATION_PERIOD) && $CRON_NON_MITIGATION_PERIOD=='daily');
        $weeklySelected     = (isset($CRON_NON_MITIGATION_PERIOD) && $CRON_NON_MITIGATION_PERIOD=='weekly');
        $monthlySelected    = (isset($CRON_NON_MITIGATION_PERIOD) && $CRON_NON_MITIGATION_PERIOD=='monthly');
        $annuallySelected   = (isset($CRON_NON_MITIGATION_PERIOD) && $CRON_NON_MITIGATION_PERIOD=='annually');
        echo "
            <tr id='specified_daily' class='specified_time_holder' ". ($dailySelected ? "style='display:table-row'" : "") .">
                <td width=\"100px\">".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                <td>
                ".create_time_html($configs, $dailySelected, "cron_non_mitigation_hour", "cron_non_mitigation_minute")."
                </td>
            </tr>
            
            <tr id='specified_weekly' class='specified_time_holder' ". ((isset($CRON_NON_MITIGATION_PERIOD) && $CRON_NON_MITIGATION_PERIOD=='weekly') ? "style='display:table-row'" : "") .">
                <td>".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                <td>
                ".create_day_of_week_html($configs, $weeklySelected, "cron_non_mitigation_day_of_week")."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $weeklySelected, "cron_non_mitigation_hour", "cron_non_mitigation_minute")."
                </td>
            </tr>

            <tr id='specified_monthly' class='specified_time_holder' ". ((isset($CRON_NON_MITIGATION_PERIOD) && $CRON_NON_MITIGATION_PERIOD=='monthly') ? "style='display:table-row'" : "") .">
                <td>".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                <td>
                ".create_date_html($configs, $monthlySelected, "cron_non_mitigation_date")."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $monthlySelected, "cron_non_mitigation_hour", "cron_non_mitigation_minute")."
                </td>
            </tr>
            
            <tr id='specified_annually' class='specified_time_holder' ". ((isset($CRON_NON_MITIGATION_PERIOD) && $CRON_NON_MITIGATION_PERIOD=='annually') ? "style='display:table-row'" : "") .">
                <td>".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                <td>
                ".create_day_html($configs, $annuallySelected, "cron_non_mitigation_month", "cron_non_mitigation_date")."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $annuallySelected, "cron_non_mitigation_hour", "cron_non_mitigation_minute")."
                </td>
            </tr>
        ";
        echo "</tr>\n";
    echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
        echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"non_mitigation_auto_notify_submitter\" id=\"non_mitigation_auto_notify_submitter\"" . ($NON_MITIGATION_AUTO_NOTIFY_SUBMITTER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifySubmitter']) . "</td></tr>\n";
    echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"non_mitigation_auto_notify_owner\" id=\"non_mitigation_auto_notify_owner\"" . ($NON_MITIGATION_AUTO_NOTIFY_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwner']) . "</td></tr>\n";
    echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"non_mitigation_auto_notify_owners_manager\" id=\"non_mitigation_auto_notify_owners_manager\"" . ($NON_MITIGATION_AUTO_NOTIFY_OWNERS_MANAGER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyOwnersManager']) . "</td></tr>\n";
    echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"non_mitigation_auto_notify_team\" id=\"non_mitigation_auto_notify_team\"" . ($NON_MITIGATION_AUTO_NOTIFY_TEAM == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyTeam']) . "</td></tr>\n";
    echo "<tr><td colspan=\"2\"><input type=\"checkbox\" name=\"non_mitigation_auto_notify_additional_stakeholders\" id=\"non_mitigation_additional_stakeholders\"" . ($NON_MITIGATION_AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyAdditionalStakeholders']) . "</td></tr>\n";
    echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
    echo "</td></tr>\n";
    
    
    // Notify on Review of Policy and Control Exceptions
    echo "<tr><td>\n";
                echo "<table border=\"0\" id=\"policy-control-exception-container\" width=\"100%\">\n";
                    echo "<tr align=\"center\"><td colspan=\"2\"><h4><u>" . $escaper->escapeHtml($lang['AutomatedNotificationsOfUnReviewedPastdueControlPolicyExceptions']) . "</u></h4></td></tr>\n";
                    echo "<tr>\n";
                        echo "<td colspan=\"2\"><u><strong>".$escaper->escapeHtml($lang["Schedule"]).":</strong></u><span style=\"float: right;\"><button type=\"submit\" name=\"policy_control_exception_review_run_now\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['RunNow']) . "</button></span></td>\n";
                        echo "</tr>\n";
                        echo "<tr>\n";
                        echo "<td width=\"100px\">" . $escaper->escapeHtml($lang['Period']) . ":</td>\n";
                        echo "
                            <td>
                                <select class='period-dropdown' name=\"cron_policy_control_exception_review_period\" id=\"cron_policy_control_exception_review_period\" >
                                    <option value=\"\">--- Select Period ---</option>
                                    <option value=\"daily\" ". ((isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) && $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD=='daily') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Daily"])."</option>
                                    <option value=\"weekly\" ". ((isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) && $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD=='weekly') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Weekly"])."</option>
                                    <option value=\"monthly\" ". ((isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) && $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD=='monthly') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Monthly"])."</option>
                                    <!-- option value=\"quarterly\" ". ((isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) && $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD=='quarterly') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Quarterly"])."</option -->
                                    <option value=\"annually\" ". ((isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) && $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD=='annually') ? " selected " : "") ." >".$escaper->escapeHtml($lang["Annually"])."</option>
                                </select>
                            </td>\n
                        ";
                    echo "</tr>\n";
                
                    $dailySelected      = (isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) && $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD=='daily');
                    $weeklySelected     = (isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) && $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD=='weekly');
                    $monthlySelected    = (isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) && $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD=='monthly');
                    $annuallySelected   = (isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) && $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD=='annually');
                    echo "
                        <tr id='specified_daily' class='specified_time_holder' ". ($dailySelected ? "style='display:table-row'" : "") .">
                            <td width=\"100px\">".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                            <td>
                            ".create_time_html($configs, $dailySelected, "cron_policy_control_exception_review_hour", "cron_policy_control_exception_review_minute")."
                            </td>
                        </tr>
                        
                        <tr id='specified_weekly' class='specified_time_holder' ". ((isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) && $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD=='weekly') ? "style='display:table-row'" : "") .">
                            <td>".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                            <td>
                            ".create_day_of_week_html($configs, $weeklySelected, "cron_policy_control_exception_review_day_of_week")."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $weeklySelected, "cron_policy_control_exception_review_hour", "cron_policy_control_exception_review_minute")."
                            </td>
                        </tr>

                        <tr id='specified_monthly' class='specified_time_holder' ". ((isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) && $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD=='monthly') ? "style='display:table-row'" : "") .">
                            <td>".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                            <td>
                            ".create_date_html($configs, $monthlySelected, "cron_policy_control_exception_review_date")."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $monthlySelected, "cron_policy_control_exception_review_hour", "cron_policy_control_exception_review_minute")."
                            </td>
                        </tr>
                        
                        <tr id='specified_annually' class='specified_time_holder' ". ((isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) && $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD=='annually') ? "style='display:table-row'" : "") .">
                            <td>".$escaper->escapeHtml($lang["SpecifiedTime"]).":</td>\n
                            <td>
                            ".create_day_html($configs, $annuallySelected, "cron_policy_control_exception_review_month", "cron_policy_control_exception_review_date")."&nbsp&nbsp;&nbsp;&nbsp;&nbsp;".create_time_html($configs, $annuallySelected, "cron_policy_control_exception_review_hour", "cron_policy_control_exception_review_minute")."
                            </td>
                        </tr>
                    ";

                    echo "<tr class=\"who_notify_on_policy_control_exception_review\" ><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['WhoToNotify']) . ":</strong></u></td></tr>\n";
                    echo "<tr class=\"who_notify_on_policy_control_exception_review\" ><td colspan=\"2\"><input type=\"checkbox\" name=\"policy_control_exception_review_control_owner\" id=\"policy_control_exception_review_control_owner\"" . ($POLICY_CONTROL_EXCEPTION_REVIEW_CONTROL_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyControlOwner']) . "</td></tr>\n";
                    echo "<tr class=\"who_notify_on_policy_control_exception_review\" ><td colspan=\"2\"><input type=\"checkbox\" name=\"policy_control_exception_review_policy_owner\" id=\"policy_control_exception_review_policy_owner\"" . ($POLICY_CONTROL_EXCEPTION_REVIEW_POLICY_OWNER == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyPolicyOwner']) . "</td></tr>\n";
                    echo "<tr class=\"who_notify_on_policy_control_exception_review\" ><td colspan=\"2\"><input type=\"checkbox\" name=\"policy_control_exception_review_additional_stakeholders\" id=\"policy_control_exception_review_additional_stakeholders\"" . ($POLICY_CONTROL_EXCEPTION_REVIEW_ADDITIONAL_STAKEHOLDERS == "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyAdditionalStakeholders']) . "</td></tr>\n";
                    echo "<tr class=\"who_notify_on_policy_control_exception_review\" ><td colspan=\"2\"><input type=\"checkbox\" name=\"policy_control_exception_review_approver\" id=\"policy_control_exception_review_approver\"" . ($POLICY_CONTROL_EXCEPTION_REVIEW_APPROVER== "true" ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['NotifyApprover']) . "</td></tr>\n";

                echo "</table>\n";
                echo "<div class=\"form-actions\">\n";
                    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Save']) . "</button>\n";
                echo "</div>\n";
            echo "</td></tr>\n";
        echo "</table>\n";
    echo "</form>\n";
    echo "
        <script>
            $(document).ready(function(){
                $(document.notification_extra).submit(function(){
                    var form = new FormData($(this)[0]);

                    $.ajax({
                        type: 'POST',
                        url: BASE_URL + '/api/notification/save_settings',
                        data: form,
                        async: true,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function(data){
                            if(data.status_message){                    
                                showAlertsFromArray(data.status_message);
                            }
                        },
                        error: function(xhr,status,error){
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }
                    });
                
                    return false;
                })
            })
        </script>
    ";
}

/**********************************
 * FUNCTION: NOTIFICATION VERSION *
 **********************************/
function notification_version()
{
    // Return the version
    return NOTIFICATION_EXTRA_VERSION;
}

/******************************
 * FUNCTION: READ CONFIG FILE *
 ******************************/
if (!function_exists('read_config_file')) {
function read_config_file()
{
        // Location of the configuration file
        $config_file = realpath(__DIR__ . '/includes/config.php');

        // Open the file for reading
        $handle = fopen($config_file, 'r');

        // If we can read the file
        if ($handle)
        {
                // Create a configuration array
                $config_array = array();

                // Read each line in the file
                while ($line = fgets($handle))
                {
                        // If the line begins with define
                        if (preg_match('/^define\(\'*\'*/', $line))
                        {
                                // Grab the parameter and value
                                preg_match('/\((.*?)\,(.*?)\)/s', $line, $matches);
                                $param_name = $matches[1];
                                $param_value = $matches[2];

                                // Remove any double quotes
                                $param_name = str_replace('"', "", $param_name);
                                $param_value = str_replace('"', "", $param_value);

                                // Remove any single quotes
                                $param_name = str_replace('\'', "", $param_name);
                                $param_value = str_replace('\'', "", $param_value);

                                // Remove any spaces
                                $param_name = str_replace(' ', "", $param_name);
                                $param_value = str_replace(' ', "", $param_value);

                                $config_array[$param_name] = $param_value;
                        }
                }

                // Close the file
                fclose($handle);

                // Return the configuration array
                return $config_array;
        }
        else
        {
                // Return an error
                return 0;
        }
}
}

/********************************************************
 * FUNCTION: IMPORT AND REMOVE NOTIFICATION CONFIG FILE *
 ********************************************************/
function import_and_remove_notification_config_file()
{
        global $escaper;

        // Location of the configuration file
        $config_file = realpath(__DIR__ . '/includes/config.php');

        // If a configuration file exists
        if (file_exists($config_file))
        {
                // Read the configuration file
                $configs = read_config_file();

                // Update the configuration in the settings table
                if (update_settings($configs))
                {
                        // Remove the configuration file
                        if (!delete_file($config_file))
                        {
                                $alert_message = "ERROR: Could not remove " . $config_file;
                                echo "<div id=\"alert\" class=\"container-fluid\">\n";
                                echo "<div class=\"span12 redalert\">" . $escaper->escapeHtml($alert_message) . "</div>\n";
                                echo "</div>\n";
                        }
                }
        }
}

/*******************************
 * FUNCTION: SAVE CRON HISTORY *
 *******************************/
function save_cron_history($date, $type="risk")
{
    // Open the database connection
    $db = db_open();

    // Get latest sent date
    $stmt = $db->prepare("INSERT INTO `cron_history`(`sent_at`, `type`) VALUES(:sent_at, :type);");
    $stmt->bindParam(":sent_at", $date);
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->execute();
    
    // Close the database connection
    db_close($db);
}

/**********************************
 * FUNCTION: CHECK AVAILABLE CRON *
 **********************************/
function check_available_cron($configs=false, $type="risk")
{
    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we are checking for a risk cron
    if ($type == "risk")
    {
        // If the cron period value is not set
        if(!isset($CRON_PERIOD) || !$CRON_PERIOD)
        {
            // Do nothing
            return false;
        }
    
        // Open the database connection
        $db = db_open();

        // Get latest sent date
        $stmt = $db->prepare("SELECT * FROM `cron_history`  WHERE type = :type ORDER BY `sent_at` desc Limit 1;");
        $stmt->bindParam(":type", $type, PDO::PARAM_STR);
        $stmt->execute();
    
        $latestCronjob = $stmt->fetch(PDO::FETCH_ASSOC);
    
        // Close the database connection
        db_close($db);
    
        // Get latest date and time of cronjob
        $latestDate = isset($latestCronjob) ? $latestCronjob['sent_at'] : "";

        // Get the target date based on the specified cron period
        if($CRON_PERIOD == 'daily')
        {
            $targetDate = date('Y-m-d H:i:s', mktime($CRON_HOUR, $CRON_MINUTE, 0));
        }
        else if($CRON_PERIOD == 'weekly')
        {
            $targetDate =  date('Y-m-d H:i:s', strtotime(($CRON_DAY_OF_WEEK - date('w')).' day', mktime($CRON_HOUR, $CRON_MINUTE, 0)));
        }
        else if($CRON_PERIOD == 'monthly')
        {
            $targetDate =  date('Y-m-d H:i:s', mktime($CRON_HOUR, $CRON_MINUTE, 0, null, $CRON_DATE));
        }
        else if($CRON_PERIOD == 'annually')
        {
            $targetDate =  date('Y-m-d H:i:s', mktime($CRON_HOUR, $CRON_MINUTE, 0, $CRON_MONTH, $CRON_DATE));
        }

        // Format the last run and next run in DateTime format
        $last_run = new DateTime(date('Y-m-d', strtotime($latestDate)));
        $next_run = new DateTime(date('Y-m-d', strtotime($targetDate)));

        // If the cron job hasn't been run yet today
        if ($last_run->format('Y-m-d') != $next_run->format('Y-m-d'))
        {
            // If it is after the target time
            if (time() - strtotime($targetDate) >= 0)
            {
                // If the target time is within the past 5 minutes
                if (time() - strtotime($targetDate) < 300)
                {
                    // Save the current date to the cron history table
                    save_cron_history(date('Y-m-d H:i:s', time()), $type);

                    // Return that the cron was available to run
                    return true;
                }
            }
            // Otherwise, do nothing
            else return false;
        }
        // Otherwise, do nothing
        else return false;
    }
    // If we are checking for a risk cron without mitigation
    if ($type == "non-mitigation")
    {
        // If the cron period value is not set
        if(!isset($CRON_NON_MITIGATION_PERIOD) || !$CRON_NON_MITIGATION_PERIOD)
        {
            // Do nothing
            return false;
        }

        // Open the database connection
        $db = db_open();

        // Get latest sent date
        $stmt = $db->prepare("SELECT * FROM `cron_history`  WHERE type = :type ORDER BY `sent_at` desc Limit 1;");
        $stmt->bindParam(":type", $type, PDO::PARAM_STR);
        $stmt->execute();
    
        $latestCronjob = $stmt->fetch(PDO::FETCH_ASSOC);
    
        // Close the database connection
        db_close($db);
    
        // Get latest date and time of cronjob
        $latestDate = isset($latestCronjob) ? $latestCronjob['sent_at'] : "";

        // Get the target date based on the specified cron period
        if($CRON_NON_MITIGATION_PERIOD == 'daily')
        {
            $targetDate = date('Y-m-d H:i:s', mktime($CRON_NON_MITIGATION_HOUR, $CRON_NON_MITIGATION_MINUTE, 0));
        }
        else if($CRON_NON_MITIGATION_PERIOD == 'weekly')
        {
            $targetDate =  date('Y-m-d H:i:s', strtotime(($CRON_NON_MITIGATION_DAY_OF_WEEK - date('w')).' day', mktime($CRON_NON_MITIGATION_HOUR, $CRON_NON_MITIGATION_MINUTE, 0)));
        }
        else if($CRON_NON_MITIGATION_PERIOD == 'monthly')
        {
            $targetDate =  date('Y-m-d H:i:s', mktime($CRON_NON_MITIGATION_HOUR, $CRON_NON_MITIGATION_MINUTE, 0, null, $CRON_DATE));
        }
        else if($CRON_NON_MITIGATION_PERIOD == 'annually')
        {
            $targetDate =  date('Y-m-d H:i:s', mktime($CRON_NON_MITIGATION_HOUR, $CRON_NON_MITIGATION_MINUTE, 0, $CRON_NON_MITIGATION_MONTH, $CRON_NON_MITIGATION_DATE));
        }

        // Format the last run and next run in DateTime format
        $last_run = new DateTime(date('Y-m-d', strtotime($latestDate)));
        $next_run = new DateTime(date('Y-m-d', strtotime($targetDate)));

        // If the cron job hasn't been run yet today
        if ($last_run->format('Y-m-d') != $next_run->format('Y-m-d'))
        {
            // If it is after the target time
            if (time() - strtotime($targetDate) >= 0)
            {
                // If the target time is within the past 5 minutes
                if (time() - strtotime($targetDate) < 300)
                {
                    // Save the current date to the cron history table
                    save_cron_history(date('Y-m-d H:i:s', time()), $type);

                    // Return that the cron was available to run
                    return true;
                }
            }
            // Otherwise, do nothing
            else return false;
        }
        // Otherwise, do nothing
        else return false;
    }
    // If we are checking for an audit cron
    else if ($type == "audit")
    {
      // Open the database connection
      $db = db_open();

      // Get latest sent date
      $stmt = $db->prepare("SELECT * FROM `cron_history` WHERE type = :type ORDER BY `sent_at` desc Limit 1;");
      $stmt->bindParam(":type", $type, PDO::PARAM_STR);
      $stmt->execute();

      $latestCronjob = $stmt->fetch(PDO::FETCH_ASSOC);

      // Close the database connection
      db_close($db);

      // Get latest date and time of cronjob
      $latestDate = isset($latestCronjob) ? $latestCronjob['sent_at'] : "";

      // Set the target date and time of the cronjob
      $targetDate = date('Y-m-d H:i:s', mktime($NOTIFICATION_SEND_AUDIT_HOUR, $NOTIFICATION_SEND_AUDIT_MINUTE, 0));

      // Format the last run and next run in DateTime format
      $last_run = new DateTime(date('Y-m-d', strtotime($latestDate)));
      $next_run = new DateTime(date('Y-m-d', strtotime($targetDate)));

        // If the cron job hasn't been run yet today
        if ($last_run->format('Y-m-d') != $next_run->format('Y-m-d'))
        {
                // If it is after the target time
                if (time() - strtotime($targetDate) >= 0)
                {
                        // If the target time is within the past 5 minutes
                        if (time() - strtotime($targetDate) < 300)
                        {
                                // Save the current date to the cron history table
                                save_cron_history(date('Y-m-d H:i:s', time()), $type);

                                // Return that the cron was available to run
                                return true;
                        }
                }
                // Otherwise, do nothing
                else return false;
        }
        // Otherwise, do nothing
        else return false;
    }
    else if ($type == "mitigation")
    {
        // Open the database connection
        $db = db_open();

        // Get latest sent date
        $stmt = $db->prepare("SELECT * FROM `cron_history` WHERE type = :type ORDER BY `sent_at` desc Limit 1;");
        $stmt->bindParam(":type", $type, PDO::PARAM_STR);
        $stmt->execute();

        $latestCronjob = $stmt->fetch(PDO::FETCH_ASSOC);

        // Close the database connection
        db_close($db);

        // Get latest date and time of cronjob
        $latestDate = isset($latestCronjob) ? $latestCronjob['sent_at'] : "";

        // Set the target date and time of the cronjob
        $targetDate = date('Y-m-d H:i:s', mktime($NOTIFICATION_SEND_MITIGATION_HOUR, $NOTIFICATION_SEND_MITIGATION_MINUTE, 0));

        // Format the last run and next run in DateTime format
        $last_run = new DateTime(date('Y-m-d', strtotime($latestDate)));
        $next_run = new DateTime(date('Y-m-d', strtotime($targetDate)));

        // If the cron job hasn't been run yet today
        if ($last_run->format('Y-m-d') != $next_run->format('Y-m-d'))
        {
            // If it is after the target time
            if (time() - strtotime($targetDate) >= 0)
            {
                // If the target time is within the past 5 minutes
                if (time() - strtotime($targetDate) < 300)
                {
                    // Save the current date to the cron history table
                    save_cron_history(date('Y-m-d H:i:s', time()), $type);

                    // Return that the cron was available to run
                    return true;
                }
            }
            // Otherwise, do nothing
            else return false;
        }
        // Otherwise, do nothing
        else return false;
    }
    else if ($type == "unapproved_policy_control_exceptions")
    {
        // If the cron period value is not set
        if(!isset($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD) || !$CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD)
        {
            // Do nothing
            return false;
        }

        // Open the database connection
        $db = db_open();

        // Get latest sent date
        $stmt = $db->prepare("SELECT * FROM `cron_history`  WHERE type = :type ORDER BY `sent_at` desc Limit 1;");
        $stmt->bindParam(":type", $type, PDO::PARAM_STR);
        $stmt->execute();
    
        $latestCronjob = $stmt->fetch(PDO::FETCH_ASSOC);
    
        // Close the database connection
        db_close($db);
    
        // Get latest date and time of cronjob
        $latestDate = isset($latestCronjob) ? $latestCronjob['sent_at'] : "";

        // Get the target date based on the specified cron period
        if($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD == 'daily')
        {
            $targetDate = date('Y-m-d H:i:s', mktime($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_HOUR, $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MINUTE, 0));
        }
        else if($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD == 'weekly')
        {
            $targetDate =  date('Y-m-d H:i:s', strtotime(($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_DAY_OF_WEEK - date('w')).' day', mktime($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_HOUR, $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MINUTE, 0)));
        }
        else if($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD == 'monthly')
        {
            $targetDate =  date('Y-m-d H:i:s', mktime($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_HOUR, $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MINUTE, 0, null, $CRON_DATE));
        }
        else if($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD == 'annually')
        {
            $targetDate =  date('Y-m-d H:i:s', mktime($CRON_POLICY_CONTROL_EXCEPTION_REVIEW_HOUR, $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MINUTE, 0, $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MONTH, $CRON_POLICY_CONTROL_EXCEPTION_REVIEW_DATE));
        }

        // Format the last run and next run in DateTime format
        $last_run = new DateTime(date('Y-m-d', strtotime($latestDate)));
        $next_run = new DateTime(date('Y-m-d', strtotime($targetDate)));

        // If the cron job hasn't been run yet today
        if ($last_run->format('Y-m-d') != $next_run->format('Y-m-d'))
        {
            // If it is after the target time
            if (time() - strtotime($targetDate) >= 0)
            {
                // If the target time is within the past 5 minutes
                if (time() - strtotime($targetDate) < 300)
                {
                    // Save the current date to the cron history table
                    save_cron_history(date('Y-m-d H:i:s', time()), $type);

                    // Return that the cron was available to run
                    return true;
                }
            }
            // Otherwise, do nothing
            else return false;
        }
        // Otherwise, do nothing
        else return false;
    }
}

/****************************************
 * FUNCTION: AUTO RUN RISK NOTIFICATION *
 ****************************************/
function auto_run_risk_notification()
{
    global $lang;
    global $escaper;

    // Define the styles
    $table_style = "width:100%; border: 1px solid #ddd;border-collapse: collapse;";
    $th_style = $td_style = "border: 1px solid #ddd;";
    $th_style .= "background-color: #ebe7e7 !important;";
    $caption_style = "border: 1px solid #ddd;background-color: #ddd !important;padding: 5px;";

    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    if (team_separation_extra()) {
        require_once(realpath(__DIR__ . '/../separation/index.php'));
        $separation = true;
    } else
        $separation = false;

    // Get the list of users
    $users = get_custom_table("enabled_users");

    // Open the database connection
    $db = db_open();

    // For each user
    foreach ($users as $user)
    {
        $user_id = $user['value'];
        $name = $user['name'];
        $email = $user['email'];
        $teams = $user['teams'];
        $review_veryhigh = $user['review_veryhigh'];
        $review_high = $user['review_high'];
        $review_medium = $user['review_medium'];
        $review_low = $user['review_low'];
        $review_insignificant = $user['review_insignificant'];
        $allow_all_to_risk_noassign_team = get_setting('allow_all_to_risk_noassign_team');

        // If we are supposed to auto notify submitters
        if ($AUTO_NOTIFY_SUBMITTER == "true" && (!$separation || get_setting('allow_submitter_to_risk'))) {

            // Get all open risks with that user as submitter
            $stmt = $db->prepare("SELECT a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(m.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) as residual_risk, b.id, b.subject, b.additional_stakeholders, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter 
            FROM risk_scoring a 
                JOIN risks b ON a.id = b.id 
                LEFT JOIN team c ON FIND_IN_SET(c.value, b.team) 
                LEFT JOIN user d ON b.owner = d.value 
                LEFT JOIN user e ON b.manager = e.value 
                LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id 
                LEFT JOIN user g ON b.submitted_by = g.value 
                LEFT JOIN mitigations m ON b.id = m.risk_id 
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, m.mitigation_controls) AND fc.deleted=0

            WHERE b.status != \"Closed\" AND b.submitted_by = :user_id GROUP BY b.id ORDER BY calculated_risk DESC ; ");
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $submitter_risks = $stmt->fetchAll();
        }
        else $submitter_risks = array();

        // If we are supposed to auto notify owners
        if ($AUTO_NOTIFY_OWNER == "true" && (!$separation || get_setting('allow_owner_to_risk'))) {

            // Get all open risks with that user as owner
            $stmt = $db->prepare("SELECT a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(m.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) as residual_risk, b.id, b.subject, b.additional_stakeholders, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter 
                FROM risk_scoring a 
                    JOIN risks b ON a.id = b.id 
                    LEFT JOIN team c ON FIND_IN_SET(c.value, b.team) 
                    LEFT JOIN user d ON b.owner = d.value 
                    LEFT JOIN user e ON b.manager = e.value 
                    LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id 
                    LEFT JOIN user g ON b.submitted_by = g.value 
                    LEFT JOIN mitigations m ON b.id = m.risk_id 
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, m.mitigation_controls) AND fc.deleted=0
                WHERE b.status != \"Closed\" AND b.owner = :user_id 
                GROUP BY b.id ORDER BY calculated_risk DESC ; 
            ");
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $owner_risks = $stmt->fetchAll();
        }
        else $owner_risks = array();

        // If we are supposed to auto notify owners manager
        if ($AUTO_NOTIFY_OWNERS_MANAGER == "true" && (!$separation || get_setting('allow_ownermanager_to_risk')))
        {
            // Get all open risks with that user as manager
            $stmt = $db->prepare("SELECT a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(m.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) as residual_risk, b.id, b.subject, b.additional_stakeholders, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter 
                FROM risk_scoring a 
                    JOIN risks b ON a.id = b.id 
                    LEFT JOIN team c ON FIND_IN_SET(c.value, b.team) 
                    LEFT JOIN user d ON b.owner = d.value 
                    LEFT JOIN user e ON b.manager = e.value 
                    LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id 
                    LEFT JOIN user g ON b.submitted_by = g.value 
                    LEFT JOIN mitigations m ON b.id = m.risk_id 
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, m.mitigation_controls) AND fc.deleted=0
                WHERE b.status != \"Closed\" AND b.manager = :user_id 
                GROUP BY b.id 
                ORDER BY calculated_risk DESC ; ");
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $manager_risks = $stmt->fetchAll();
        }
        else $manager_risks = array();
        
        // If we are supposed to auto notify team
        if ($AUTO_NOTIFY_TEAM == "true" && (!$separation || get_setting('allow_team_member_to_risk')))
        {
            // If the team is not none
            if ($teams != "none")
            {
                // Remove the first colon from the teams list
                $teams = substr($teams, 1);

                // Remove the last colon from the teams list
                $teams = substr($teams, 0, -1);

                // Get an array of teams the user belongs to
                $teams = explode("::", $teams);

                // Get the number of teams
                $number_of_teams = count($teams);

                // Create an empty string for the team SQL
                $teams_sql = "";

                // For each team
                for ($i = 0; $i < $number_of_teams; $i++)
                {
                    // If this isn't the last team
                    if ($i != $number_of_teams - 1)
                    {
                        $teams_sql .= " FIND_IN_SET('{$teams[$i]}', b.team) " . " OR ";
                    }
                    else $teams_sql .= " FIND_IN_SET('{$teams[$i]}', b.team) ";
                }

                // If there is at least one team
                if ($number_of_teams > 0)
                {
                    // Get all open risks for the teams the user belongs to
                    $stmt = $db->prepare("SELECT a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(m.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) as residual_risk, b.id, b.subject, b.additional_stakeholders, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter 
                        FROM risk_scoring a 
                            JOIN risks b ON a.id = b.id 
                            LEFT JOIN team c ON FIND_IN_SET(c.value, b.team) 
                            LEFT JOIN user d ON b.owner = d.value 
                            LEFT JOIN user e ON b.manager = e.value 
                            LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id 
                            LEFT JOIN user g ON b.submitted_by = g.value 
                            LEFT JOIN mitigations m ON b.id = m.risk_id 
                            LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, m.mitigation_controls) AND fc.deleted=0
                        WHERE b.status != \"Closed\" AND (" . $teams_sql . ") 
                        GROUP BY b.id ORDER BY calculated_risk DESC ; ");
                    $stmt->execute();
                    $team_risks = $stmt->fetchAll();
                }
                // Otherwise the team risks array is empty
                else $team_risks = array();
            }
            // Otherwise the team risks array is empty
            else $team_risks = array();
        }
        else $team_risks = array();

        // If we are supposed to auto notify additional stakeholders
        if ($AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" && (!$separation || get_setting('allow_stakeholder_to_risk')))
        {
            // Get all open risks with that user as manager
            $stmt = $db->prepare("SELECT a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(m.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) as residual_risk, b.id, b.subject, b.additional_stakeholders, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN team c ON FIND_IN_SET(c.value, b.team) LEFT JOIN user d ON b.owner = d.value LEFT JOIN user e ON b.manager = e.value LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id LEFT JOIN user g ON b.submitted_by = g.value LEFT JOIN mitigations m ON b.id = m.risk_id LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, m.mitigation_controls) AND fc.deleted=0 WHERE b.status != \"Closed\" AND find_in_set(:user_id, b.additional_stakeholders) GROUP BY b.id ORDER BY calculated_risk DESC ; ");
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $stakeholder_risks = $stmt->fetchAll();
        }
        else $stakeholder_risks = array();

        // If we are supposed to auto notify reviewers
        if ($AUTO_NOTIFY_REVIEWERS == "true") {

            $teams = $user['teams'];

            // Create an empty string for the team SQL
            $teams_sql = "";

            // If the team is not none
            if ($teams != "none") {

                // Remove the first colon from the teams list
                $teams = substr($teams, 1);

                // Remove the last colon from the teams list
                $teams = substr($teams, 0, -1);

                // Get an array of teams the user belongs to
                $teams = explode("::", $teams);

                // Get the number of teams
                $number_of_teams = count($teams);

                // For each team
                for ($i = 0; $i < $number_of_teams; $i++)
                {
                    // If this isn't the last team
                    if ($i != $number_of_teams - 1)
                    {
                        $teams_sql .= " FIND_IN_SET('{$teams[$i]}', b.team) " . " OR ";
                    }
                    else $teams_sql .= " FIND_IN_SET('{$teams[$i]}', b.team) ";
                }
            }

            if ($teams_sql || ($separation && $allow_all_to_risk_noassign_team)) {
            
                // Get all open risks for the teams the user belongs to
                $stmt = $db->prepare("
                    SELECT
                        a.calculated_risk,
                        ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(m.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) as residual_risk,
                        b.id,
                        b.subject,
                        b.additional_stakeholders,
                        GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as team,
                        d.name as owner,
                        e.name as manager,
                        f.next_review,
                        g.name as submitter
                    FROM
                        risk_scoring a
                        JOIN risks b ON a.id = b.id
                        LEFT JOIN team c ON FIND_IN_SET(c.value, b.team)
                        LEFT JOIN user d ON b.owner = d.value
                        LEFT JOIN user e ON b.manager = e.value
                        LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id
                        LEFT JOIN user g ON b.submitted_by = g.value
                        LEFT JOIN mitigations m ON b.id = m.risk_id
                        LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, m.mitigation_controls) AND fc.deleted=0
                    WHERE
                        b.status != \"Closed\"" . ( $teams_sql ? "AND ($teams_sql)" : "") . "
                    GROUP BY
                        b.id
                    ORDER BY
                        calculated_risk DESC;
                ");
                $stmt->execute();
                $unfiltered_reviewer_risks = $stmt->fetchAll();

                // Create the reviewer risks array
                $reviewer_risks = array();

                // For each of the unfiltered risks
                foreach ($unfiltered_reviewer_risks as $array)
                {
                    // Skip the risk if the user has no access to it
                    if ($separation && !extra_grant_access($user_id, (int)$array['id'] + 1000)) {
                        continue;
                    }

                    // Get the risk level name
                    $risk_level_name = get_risk_level_name($array['calculated_risk']);
                    
                    // If the risk level is one the user can review
                    if (($risk_level_name == get_risk_level_display_name('Very High') && $review_veryhigh) || ($risk_level_name == get_risk_level_display_name('High') && $review_high) || ($risk_level_name == get_risk_level_display_name('Medium') && $review_medium) || ($risk_level_name == get_risk_level_display_name('Low') && $review_low) || ($risk_level_name == get_risk_level_display_name('Insignificant') && $review_insignificant))
                    {
                        $reviewer_risks[] = $array;
                    }
                }
            } else $reviewer_risks = array();
        }
        else $reviewer_risks = array();

        // Merge the arrays together
        $risks = array();
        if (!empty($submitter_risks)) $risks = array_merge($risks, $submitter_risks);
        if (!empty($owner_risks)) $risks = array_merge($risks, $owner_risks);
        if (!empty($manager_risks)) $risks = array_merge($risks, $manager_risks);
        if (!empty($team_risks)) $risks = array_merge($risks, $team_risks);
        if (!empty($stakeholder_risks)) $risks = array_merge($risks, $stakeholder_risks);
        if (!empty($reviewer_risks)) $risks = array_merge($risks, $reviewer_risks);

        // Remove duplicates from the multidimensional array
        $risks = array_map("unserialize", array_unique(array_map("serialize", $risks)));

        // Create some empty arrays
        $risk_email = array();
        $status_text = array();
        $calculated_risk = array();

        // For each risk in the array
        foreach ($risks as $risk) {

            // Get whether the risk level is high, medium, or low
            $risk_level = get_risk_level_name($risk['calculated_risk']);
            $residual_risk_level = get_risk_level_name($risk['residual_risk']);

            // Get the next review date based on the risk level
            $next_review = $risk['next_review'];

            // If next_review_date_uses setting is Residual Risk.
            if(get_setting('next_review_date_uses') == "ResidualRisk")
            {
                $next_review = next_review($residual_risk_level, $risk['id'], $next_review, false);
            }
            // If next_review_date_uses setting is Inherent Risk.
            else
            {
                $next_review = next_review($risk_level, $risk['id'], $next_review, false);
            }

            // If the risk is unreviewed or past due then we will need to send a notification
            if ($next_review === $lang['UNREVIEWED'] || $next_review === $lang['PASTDUE'])
            {
                // Set the status text in the array
                $risk['status_text'] = $next_review;
                //$risk[9] = $next_review;

                // Add the value to the risk email array
                $risk_email[] = $risk;
            }
            else
            {
                // Set the status text in the array
                $risk['status_text'] = "";
                //$risk[9] = "";
            }
        }

        // If there are risks for this user
        if (!empty($risk_email))
        {
            // Get the list of columns
            foreach ($risk_email as $key => $row)
            {
                $status_text[$key] = $row['status_text'];
                $calculated_risk[$key] = $row['calculated_risk'];
            }

            // Sort the risk email array by status text and calculated risk
            array_multisort($status_text, SORT_DESC, SORT_STRING, $calculated_risk, SORT_DESC, SORT_NUMERIC, $risk_email);

            // Create the message
            $message = "<html><body>\n";
            $message .= "<p>You are receiving this message because you are the submitter, owner, owner's manager, belong to the team, or are an additional stakeholder associated with the following risks which need to be reviewed.  You will continue to receive e-mail reminders until a review has taken place.</p>\n";

            // Track the status
            $status_tracker = "";

            // For each risk
            foreach ($risk_email as $risk)
            {
                // Get the risk values
                $id = convert_id($risk['id']);
                $calculated_risk = $risk['calculated_risk'];
                $color = $escaper->escapeHtml(get_risk_color($calculated_risk));
                $subject = try_decrypt($risk['subject']);
                $status_text = $risk['status_text'];
                $submitter = $risk['submitter'];
                $owner = $risk['owner'];
                $manager = $risk['manager'];
                $team = $risk['team'];

                // If the values aren't set, set them to unassigned
                if (is_null($submitter)) $submitter = $lang['Unassigned'];
                if (is_null($owner)) $owner = $lang['Unassigned'];
                if (is_null($manager)) $manager = $lang['Unassigned'];
                if (is_null($team)) $team = $lang['Unassigned'];

                // If the status tracker is different than the status text
                if ($status_tracker != $status_text)
                {
                    // If the status tracker is not null
                    if ($status_tracker != "")
                    {
                        // End the current table
                        $message .= "</table>\n";
                        $message .= "</p>\n";
                    }

                    // Set the status tracker to the status text
                    $status_tracker = $status_text;

                    // Display the table header
                    $message .= "<p>\n";
                    $message .= "<table cellpadding=\"10px\" style=\"{$table_style}\">\n";
                    $message .= "<caption style=\"{$caption_style}\"><b><u>" . $escaper->escapeHtml($status_text) . "</u></b></caption>\n";
                    $message .= "<tr>\n";
                    $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['RiskId']) . "</th>\n";
                    $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['CalculatedRisk']) . "</th>\n";
                    $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Subject']) . "</th>\n";
                    $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['SubmittedBy']) . "</th>\n";
                    $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Owner']) . "</th>\n";
                    $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['OwnersManager']) . "</th>\n";
                    $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Team']) . "</th>\n";
                    $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['PerformAReview']) . "</th>\n";
                    $message .= "</tr>\n";
                }

                $message .= "<tr>\n";
                $message .= "<td style=\"{$td_style}\" align=\"center\"><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($id) . "\">" . $escaper->escapeHtml($id) . "</a></td>\n";
                $message .= "<td style=\"{$td_style}\" align=\"center\"><table width=\"25px\" height=\"25px\" border=\"0\" style=\"border: 1px solid #000000; background-color: {$color};\"><tr><td valign=\"middle\" halign=\"center\"><center><font size=\"2\">" . $escaper->escapeHtml($calculated_risk) . "</font></center></td></tr></table></td>\n";
                $message .= "<td style=\"{$td_style}\" align=\"left\">" . $escaper->escapeHtml($subject) . "</td>\n";
                $message .= "<td style=\"{$td_style}\" align=\"center\">" . $escaper->escapeHtml($submitter) . "</td>\n";
                $message .= "<td style=\"{$td_style}\" align=\"center\">" . $escaper->escapeHtml($owner) . "</td>\n";
                $message .= "<td style=\"{$td_style}\" align=\"center\">" . $escaper->escapeHtml($manager) . "</td>\n";
                $message .= "<td style=\"{$td_style}\" align=\"center\">" . $escaper->escapeHtml($team) . "</td>\n";
                $message .= "<td style=\"{$td_style}\" align=\"center\"><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($id) . "&type=2&action=editreview\">" . $escaper->escapeHtml($lang['Review']) . "</a></td>\n";
                $message .= "</tr>\n";
            }

            // End the current table and message
            $message .= "</table>\n";
            $message .= "</p>\n";
            $message .= "<p>This is an automated message and responses will be ignored or rejected.</p>\n";
            $message .= "</body></html>\n";

            // Create the subject
            $subject = "Notification of Unreviewed and Past Due Risks";

            // Send the email
            send_email($name, $email, $subject, $message);
        }
    }

    // Close the database connection
    db_close($db);
}

/**************************************************
 * FUNCTION: AUTO RUN NON MITIGATION NOTIFICATION *
 **************************************************/
function auto_run_non_mitigation_notification()
{
    global $lang;
    global $escaper;

    // Define the styles
    $table_style = "width:100%; border: 1px solid #ddd;border-collapse: collapse;";
    $th_style = $td_style = "border: 1px solid #ddd;";
    $th_style .= "background-color: #ebe7e7 !important;";
    $caption_style = "border: 1px solid #ddd;background-color: #ddd !important;padding: 5px;";

    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Get the list of users
    $users = get_custom_table("enabled_users");

    if (team_separation_extra()) {
        require_once(realpath(__DIR__ . '/../separation/index.php'));
        $separation = true;
    } else
        $separation = false;

    // Open the database connection
    $db = db_open();

    // For each user
    foreach ($users as $user)
    {
        $user_id = $user['value'];
        $name = $user['name'];
        $email = $user['email'];
        $teams = $user['teams'];
        $review_veryhigh = $user['review_veryhigh'];
        $review_high = $user['review_high'];
        $review_medium = $user['review_medium'];
        $review_low = $user['review_low'];
        $review_insignificant = $user['review_insignificant'];

        // If we are supposed to auto notify submitters
        if ($NON_MITIGATION_AUTO_NOTIFY_SUBMITTER == "true" && (!$separation || get_setting('allow_submitter_to_risk'))) {

            // Get all open risks with that user as submitter
            $stmt = $db->prepare("SELECT a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(m.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) as residual_risk, b.id, b.subject, b.additional_stakeholders, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter 
            FROM risk_scoring a 
                JOIN risks b ON a.id = b.id 
                LEFT JOIN team c ON FIND_IN_SET(c.value, b.team) 
                LEFT JOIN user d ON b.owner = d.value 
                LEFT JOIN user e ON b.manager = e.value 
                LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id 
                LEFT JOIN user g ON b.submitted_by = g.value 
                LEFT JOIN mitigations m ON b.id = m.risk_id 
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, m.mitigation_controls) AND fc.deleted=0

            WHERE b.status != \"Closed\" AND m.id IS NULL AND b.submitted_by = :user_id GROUP BY b.id ORDER BY calculated_risk DESC ; ");
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $submitter_risks = $stmt->fetchAll();
        }
        else $submitter_risks = array();

        // If we are supposed to auto notify owners
        if ($NON_MITIGATION_AUTO_NOTIFY_OWNER == "true" && (!$separation || get_setting('allow_owner_to_risk'))) {

            // Get all open risks with that user as owner
            $stmt = $db->prepare("SELECT a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(m.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) as residual_risk, b.id, b.subject, b.additional_stakeholders, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter 
                FROM risk_scoring a 
                    JOIN risks b ON a.id = b.id 
                    LEFT JOIN team c ON FIND_IN_SET(c.value, b.team) 
                    LEFT JOIN user d ON b.owner = d.value 
                    LEFT JOIN user e ON b.manager = e.value 
                    LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id 
                    LEFT JOIN user g ON b.submitted_by = g.value 
                    LEFT JOIN mitigations m ON b.id = m.risk_id 
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, m.mitigation_controls) AND fc.deleted=0
                WHERE b.status != \"Closed\" AND m.id IS NULL AND b.owner = :user_id 
                GROUP BY b.id ORDER BY calculated_risk DESC ; 
            ");
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $owner_risks = $stmt->fetchAll();
        }
        else $owner_risks = array();

        // If we are supposed to auto notify owners manager
        if ($NON_MITIGATION_AUTO_NOTIFY_OWNERS_MANAGER == "true" && (!$separation || get_setting('allow_ownermanager_to_risk'))) {

            // Get all open risks with that user as manager
            $stmt = $db->prepare("SELECT a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(m.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) as residual_risk, b.id, b.subject, b.additional_stakeholders, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter 
                FROM risk_scoring a 
                    JOIN risks b ON a.id = b.id 
                    LEFT JOIN team c ON FIND_IN_SET(c.value, b.team) 
                    LEFT JOIN user d ON b.owner = d.value 
                    LEFT JOIN user e ON b.manager = e.value 
                    LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id 
                    LEFT JOIN user g ON b.submitted_by = g.value 
                    LEFT JOIN mitigations m ON b.id = m.risk_id 
                    LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, m.mitigation_controls) AND fc.deleted=0
                WHERE b.status != \"Closed\" AND b.manager = :user_id AND m.id IS NULL
                GROUP BY b.id 
                ORDER BY calculated_risk DESC ; ");
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $manager_risks = $stmt->fetchAll();
        }
        else $manager_risks = array();
        
        // If we are supposed to auto notify team
        if ($NON_MITIGATION_AUTO_NOTIFY_TEAM == "true" && (!$separation || get_setting('allow_team_member_to_risk'))) {

            // If the team is not none
            if ($teams != "none")
            {
                // Remove the first colon from the teams list
                $teams = substr($teams, 1);

                // Remove the last colon from the teams list
                $teams = substr($teams, 0, -1);

                // Get an array of teams the user belongs to
                $teams = explode("::", $teams);

                // Get the number of teams
                $number_of_teams = count($teams);

                // Create an empty string for the team SQL
                $teams_sql = "";

                // For each team
                for ($i = 0; $i < $number_of_teams; $i++)
                {
                    // If this isn't the last team
                    if ($i != $number_of_teams - 1)
                    {
                        $teams_sql .= " FIND_IN_SET('{$teams[$i]}', b.team) " . " OR ";
                    }
                    else $teams_sql .= " FIND_IN_SET('{$teams[$i]}', b.team) ";
                }

                // If there is at least one team
                if ($number_of_teams > 0)
                {
                    // Get all open risks for the teams the user belongs to
                    $stmt = $db->prepare("SELECT a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(m.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) as residual_risk, b.id, b.subject, b.additional_stakeholders, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter 
                        FROM risk_scoring a 
                            JOIN risks b ON a.id = b.id 
                            LEFT JOIN team c ON FIND_IN_SET(c.value, b.team) 
                            LEFT JOIN user d ON b.owner = d.value 
                            LEFT JOIN user e ON b.manager = e.value 
                            LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id 
                            LEFT JOIN user g ON b.submitted_by = g.value 
                            LEFT JOIN mitigations m ON b.id = m.risk_id 
                            LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, m.mitigation_controls) AND fc.deleted=0
                        WHERE b.status != \"Closed\" AND (" . $teams_sql . ") AND m.id IS NULL
                        GROUP BY b.id ORDER BY calculated_risk DESC ; ");
                    $stmt->execute();
                    $team_risks = $stmt->fetchAll();
                }
                // Otherwise the team risks array is empty
                else $team_risks = array();
            }
            // Otherwise the team risks array is empty
            else $team_risks = array();
        }
        else $team_risks = array();
        

        // If we are supposed to auto notify additional stakeholders
        if ($NON_MITIGATION_AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS == "true" && (!$separation || get_setting('allow_stakeholder_to_risk'))) {

            // Get all open risks with that user as manager
            $stmt = $db->prepare("SELECT a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(m.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0))  / 100)), 2) as residual_risk, b.id, b.subject, b.additional_stakeholders, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as team, d.name as owner, e.name as manager, f.next_review, g.name as submitter FROM risk_scoring a JOIN risks b ON a.id = b.id LEFT JOIN team c ON FIND_IN_SET(c.value, b.team) LEFT JOIN user d ON b.owner = d.value LEFT JOIN user e ON b.manager = e.value LEFT JOIN mgmt_reviews f ON b.mgmt_review = f.id LEFT JOIN user g ON b.submitted_by = g.value LEFT JOIN mitigations m ON b.id = m.risk_id LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, m.mitigation_controls) AND fc.deleted=0 WHERE b.status != \"Closed\" AND find_in_set(:user_id, b.additional_stakeholders) AND m.id IS NULL GROUP BY b.id ORDER BY calculated_risk DESC ; ");
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $stakeholder_risks = $stmt->fetchAll();
        }
        else $stakeholder_risks = array();

        // Merge the arrays together
        $risks = array();
        if (!empty($submitter_risks)) $risks = array_merge($risks, $submitter_risks);
        if (!empty($owner_risks)) $risks = array_merge($risks, $owner_risks);
        if (!empty($manager_risks)) $risks = array_merge($risks, $manager_risks);
        if (!empty($team_risks)) $risks = array_merge($risks, $team_risks);
        if (!empty($stakeholder_risks)) $risks = array_merge($risks, $stakeholder_risks);

        if(count($risks) == 0)
        {
            continue;
        }

        // Remove duplicates from the multidimensional array
        $risks = array_map("unserialize", array_unique(array_map("serialize", $risks)));

        // Create some empty arrays
        $status_text = array();
        $calculated_risk = array();
        

        // Get the list of columns
        
        foreach ($risks as $key => &$risk)
        {
            $calculated_risk[$key] = $risk['calculated_risk'];
        }
        unset($risk);
        
        // Sort the risk email array by status text and calculated risk
        array_multisort($calculated_risk, SORT_DESC, SORT_NUMERIC, $risks);

        // Create the message
        $message = "<html><body>\n";
        $message .= "<p>".$escaper->escapeHtml($lang["NonMitigationNotificationEamilDescription"])."</p>\n";

        // Track the status
        $status_tracker = "";

        // Display the table header
        $message .= "<p>\n";
        $message .= "<table cellpadding=\"10px\" style=\"{$table_style}\">\n";
        $message .= "<tr>\n";
        $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['RiskId']) . "</th>\n";
        $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['CalculatedRisk']) . "</th>\n";
        $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Subject']) . "</th>\n";
        $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['SubmittedBy']) . "</th>\n";
        $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Owner']) . "</th>\n";
        $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['OwnersManager']) . "</th>\n";
        $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Team']) . "</th>\n";
        $message .= "</tr>\n";
        
        // For each risk
        foreach ($risks as $risk)
        {
            // Get the risk values
            $id = convert_id($risk['id']);
            $calculated_risk = $risk['calculated_risk'];
            $color = $escaper->escapeHtml(get_risk_color($calculated_risk));
            $subject = try_decrypt($risk['subject']);
            $submitter = $risk['submitter'];
            $owner = $risk['owner'];
            $manager = $risk['manager'];
            $team = $risk['team'];

            // If the values aren't set, set them to unassigned
            if (is_null($submitter)) $submitter = $lang['Unassigned'];
            if (is_null($owner)) $owner = $lang['Unassigned'];
            if (is_null($manager)) $manager = $lang['Unassigned'];
            if (is_null($team)) $team = $lang['Unassigned'];

            $message .= "<tr>\n";
            $message .= "<td style=\"{$td_style}\" align=\"center\"><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($id) . "\">" . $escaper->escapeHtml($id) . "</a></td>\n";
            $message .= "<td style=\"{$td_style}\" align=\"center\"><table width=\"25px\" height=\"25px\" border=\"0\" style=\"border: 1px solid #000000; background-color: {$color};\"><tr><td valign=\"middle\" halign=\"center\"><center><font size=\"2\">" . $escaper->escapeHtml($calculated_risk) . "</font></center></td></tr></table></td>\n";
            $message .= "<td style=\"{$td_style}\" align=\"left\">" . $escaper->escapeHtml($subject) . "</td>\n";
            $message .= "<td style=\"{$td_style}\" align=\"center\">" . $escaper->escapeHtml($submitter) . "</td>\n";
            $message .= "<td style=\"{$td_style}\" align=\"center\">" . $escaper->escapeHtml($owner) . "</td>\n";
            $message .= "<td style=\"{$td_style}\" align=\"center\">" . $escaper->escapeHtml($manager) . "</td>\n";
            $message .= "<td style=\"{$td_style}\" align=\"center\">" . $escaper->escapeHtml($team) . "</td>\n";
            $message .= "</tr>\n";
        }

        // End the current table and message
        $message .= "</table>\n";
        $message .= "</p>\n";
        $message .= "<p>This is an automated message and responses will be ignored or rejected.</p>\n";
        $message .= "</body></html>\n";

        // Create the subject
        $subject = "Notification of any risks without a mitigation ";
        

        // Send the email
        send_email($name, $email, $subject, $message);
    }

    // Close the database connection
    db_close($db);
}

/****************************************************************
 * FUNCTION: AUTO RUN POLIC AND CONTROL EXCEPTIONS NOTIFICATION *
 ****************************************************************/
function auto_run_policy_control_excption_review_notification()
{
    global $lang;
    global $escaper;
    
    // Define the styles
    $table_style = "width:100%; border: 1px solid #ddd;border-collapse: collapse;";
    $th_style = $td_style = "border: 1px solid #ddd;";
    $th_style .= "background-color: #ebe7e7 !important;";
    $caption_style = "border: 1px solid #ddd;background-color: #ddd !important;padding: 5px;";

    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Get the list of users
    $users = get_custom_table("enabled_users");

    // Open the database connection
    $db = db_open();

    // For each user
    foreach ($users as $user)
    {
        $user_id = $user['value'];
        $user_name = $user['name'];
        $email = $user['email'];
        
        $policy_sql_base = "select p.id as parent_id, p.document_name as parent_name, 'policy' as type, de.* from document_exceptions de left join documents p on de.policy_document_id = p.id where p.document_type = 'policies'";
        

        $control_sql_base = "select c.id as parent_id, c.short_name as parent_name, 'control' as type, de.* from document_exceptions de left join framework_controls c on de.control_framework_id = c.id where c.id is not null";

        
        $where_by_notification = "  ";

        // Notify Control Owner is checked, add control owners
        if($POLICY_CONTROL_EXCEPTION_REVIEW_CONTROL_OWNER == "true")
        {
            $where_by_notification .= " OR (u.type='control' AND u.owner='{$user_id}')  ";
        }
        

        // If Notify Policy Owner is checked, add policy owners
        if($POLICY_CONTROL_EXCEPTION_REVIEW_POLICY_OWNER == "true")
        {
            $where_by_notification .= " OR (u.type='policy' AND u.owner='{$user_id}')  ";
        }
        

        // If Notify Additional Stakeholders is checked, add stakeholders
        if($POLICY_CONTROL_EXCEPTION_REVIEW_ADDITIONAL_STAKEHOLDERS == "true")
        {
            $where_by_notification .= " OR FIND_IN_SET({$user_id}, u.additional_stakeholders) ";
        }
        
        // If Notify Approver is checked, add approvers
        if($POLICY_CONTROL_EXCEPTION_REVIEW_APPROVER == "true")
        {
            $where_by_notification .= " OR u.approver={$user_id} ";
        }
        
        $where_by_notification = " AND (0 ".$where_by_notification.") ";
        
//        $sql = "select * from ({$policy_sql_base} union all {$control_sql_base}) u where u.approved = 0 and u.next_review_date<'".date("Y-m-d")."' ".$where_by_notification." order by u.parent_name, u.name;";
        $sql = "select * from ({$policy_sql_base} union all {$control_sql_base}) u where u.next_review_date<'".date("Y-m-d")."' ".$where_by_notification." order by u.parent_name, u.name;";
        
        // Query the database
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $exceptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if(count($exceptions) == 0)
        {
            continue;
        }

        // Remove duplicates from the multidimensional array
        $exceptions = array_map("unserialize", array_unique(array_map("serialize", $exceptions)));

        // Create the message
        $message = "<html><body>\n";
        $message .= "<p>".$escaper->escapeHtml($lang["PolicyControlExeptionReviewNotificationEamilDescription"])."</p>\n";

        // Track the status
        $status_tracker = "";

        // Display the table header
        $message .= "<p>\n";
        $message .= "<table cellpadding=\"10px\" style=\"{$table_style}\">\n";
        $message .= "<tr>\n";
        $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['UnapprovedExceptionName']) . "</th>\n";
        $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Description']) . "</th>\n";
        $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Justification']) . "</th>\n";
        $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['NextReviewDate']) . "</th>\n";
        $message .= "</tr>\n";
        
        // For each exception
        foreach ($exceptions as $exception)
        {
            $name = $exception['name'];
            $description = $exception['description'];
            $justification = $exception['justification'];
            $next_review_date = $exception['next_review_date'];
            
            $message .= "<tr>\n";
            $message .= "<td style=\"{$td_style}\" align=\"left\">" . $escaper->escapeHtml($name) . "</td>\n";
            $message .= "<td style=\"{$td_style}\" align=\"center\">" . $escaper->escapeHtml($description) . "</td>\n";
            $message .= "<td style=\"{$td_style}\" align=\"center\">" . $escaper->escapeHtml($justification) . "</td>\n";
            $message .= "<td style=\"{$td_style}\" align=\"center\">" . $escaper->escapeHtml($next_review_date) . "</td>\n";
            $message .= "</tr>\n";
        }

        // End the current table and message
        $message .= "</table>\n";
        $message .= "</p>\n";
        $message .= "<p>This is an automated message and responses will be ignored or rejected.</p>\n";
        $message .= "</body></html>\n";
        
        // Create the subject
        $subject = "Notification of any Unapproved/Pastdue Policy and Control Exceptions";

        // Send the email
        send_email($user_name, $email, $subject, $message);
    }

    // Close the database connection
    db_close($db);
}

/*****************************************
 * FUNCTION: AUTO RUN AUDIT NOTIFICATION *
 *****************************************/
function auto_run_audit_notification()
{
    global $lang;
    global $escaper;

    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Get the list of users
    $users = get_custom_table("enabled_users");

    if (team_separation_extra()) {
        // If we're allowing everyone to see the audits we treat the separation as it is turned off
        if (get_setting('allow_everyone_to_see_test_and_audit'))
            $separation = false;
        else {
            require_once(realpath(__DIR__ . '/../separation/index.php'));
            $separation = true;
        }
    } else
        $separation = false;

    // Open the database connection
    $db = db_open();

    $today = date("Y-m-d");

    $closed_audit_status = get_setting("closed_audit_status");

    $select_base = "
        SELECT t1.*, t2.name tester_name, t3.short_name control_name, t3.control_owner, GROUP_CONCAT(DISTINCT t4.name) framework_name, t5.additional_stakeholders, t6.name control_owner_name
        FROM `framework_control_test_audits` t1
            LEFT JOIN `user` t2 ON t1.tester = t2.value
            LEFT JOIN `framework_controls` t3 ON t1.framework_control_id = t3.id AND t3.deleted=0
            LEFT JOIN `frameworks` t4 ON t3.framework_ids=t4.value OR t3.framework_ids like concat('%,', t4.value) OR t3.framework_ids like concat(t4.value, ',%') OR t3.framework_ids like concat('%,', t4.value, ',%')
            LEFT JOIN `framework_control_tests` t5 ON t5.id=t1.test_id
            LEFT JOIN `user` t6 ON t3.control_owner = t6.value
        WHERE t1.status<>:closed_audit_status AND ";

    $where = [0];

    if($AUTO_NOTIFY_TESTER_AUDITS == "true" && (!$separation || get_setting('allow_tester_to_see_test_and_audit')))
    {
        $where[] = "t1.tester=:user";
    }

    if($AUTO_NOTIFY_CONTROL_OWNER_AUDITS == "true" && (!$separation || get_setting('allow_control_owner_to_see_test_and_audit')))
    {
        $where[] = "t3.control_owner=:user";
    }

    if($AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_AUDITS == "true" && (!$separation || get_setting('allow_stakeholders_to_see_test_and_audit')))
    {
        $where[] = "FIND_IN_SET(:user, t5.additional_stakeholders)";
    }

    $who_to_where = implode(" OR ", $where);

    // For each user
    foreach ($users as $user)
    {
        $user_id = $user['value'];
        $name = $user['name'];
        $email = $user['email'];
        $teams = $user['teams'];

        // If we are supposed to notify after an audit is due
        if ($NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL == "true")
        {
            $select = "{$select_base} t1.next_date < :today AND ({$who_to_where}) group by t1.id ORDER BY t1.name asc;";
            // Get all audits whose due dates are past
            $stmt = $db->prepare($select);
            $stmt->bindParam(":closed_audit_status", $closed_audit_status, PDO::PARAM_STR);
            $stmt->bindParam(":user", $user_id, PDO::PARAM_INT);
            $stmt->bindParam(":today", $today, PDO::PARAM_STR, 20);
            $stmt->execute();
            $audit_notification_past_due = $stmt->fetchAll();
        }

        // If we are supposed to notify when an audit is due
        if ($NOTIFICATION_SEND_AUDIT_DUE_EMAIL == "true")
        {
            $select = "{$select_base} t1.next_date = :today AND ({$who_to_where}) group by t1.id ORDER BY t1.name asc;";
            // Get all audits due today
            $stmt = $db->prepare($select);
            $stmt->bindParam(":closed_audit_status", $closed_audit_status, PDO::PARAM_STR);
            $stmt->bindParam(":user", $user_id, PDO::PARAM_INT);
            $stmt->bindParam(":today", $today, PDO::PARAM_STR, 20);
            $stmt->execute();
            $audit_notification_due_today = $stmt->fetchAll();
        }

        // If we are supposed to notify before the audit is due
        if ($NOTIFICATION_SEND_AUDIT_EMAIL_1 == "true")
        {
            $select = "{$select_base} t1.next_date = DATE_ADD(:today, INTERVAL :date DAY) AND ({$who_to_where}) group by t1.id ORDER BY t1.name asc;";
            // Get all audits due at this interval
            $stmt = $db->prepare($select);
            $stmt->bindParam(":closed_audit_status", $closed_audit_status, PDO::PARAM_STR);
            $stmt->bindParam(":date", $NOTIFICATION_SEND_AUDIT_EMAIL_1_VALUE, PDO::PARAM_INT);
            $stmt->bindParam(":user", $user_id, PDO::PARAM_INT);
            $stmt->bindParam(":today", $today, PDO::PARAM_STR, 20);
            $stmt->execute();
            $audit_notification_due_1 = $stmt->fetchAll();
        }

        // If we are supposed to notify before the audit is due
        if ($NOTIFICATION_SEND_AUDIT_EMAIL_2 == "true")
        {
            $select = "{$select_base} t1.next_date = DATE_ADD(:today, INTERVAL :date DAY) AND ({$who_to_where}) group by t1.id ORDER BY t1.name asc;";
            // Get all audits due at this interval
            $stmt = $db->prepare($select);
            $stmt->bindParam(":closed_audit_status", $closed_audit_status, PDO::PARAM_STR);
            $stmt->bindParam(":date", $NOTIFICATION_SEND_AUDIT_EMAIL_2_VALUE, PDO::PARAM_INT);
            $stmt->bindParam(":user", $user_id, PDO::PARAM_INT);
            $stmt->bindParam(":today", $today, PDO::PARAM_STR, 20);
            $stmt->execute();
            $audit_notification_due_2 = $stmt->fetchAll();
        }

        // If we are supposed to notify before the audit is due
        if ($NOTIFICATION_SEND_AUDIT_EMAIL_3 == "true")
        {
            $select = "{$select_base} t1.next_date = DATE_ADD(:today, INTERVAL :date DAY) AND ({$who_to_where}) group by t1.id ORDER BY t1.name asc;";
            // Get all audits due at this interval
            $stmt = $db->prepare($select);
            $stmt->bindParam(":closed_audit_status", $closed_audit_status, PDO::PARAM_STR);
            $stmt->bindParam(":date", $NOTIFICATION_SEND_AUDIT_EMAIL_3_VALUE, PDO::PARAM_INT);
            $stmt->bindParam(":user", $user_id, PDO::PARAM_INT);
            $stmt->bindParam(":today", $today, PDO::PARAM_STR, 20);
            $stmt->execute();
            $audit_notification_due_3 = $stmt->fetchAll();
        }

        // If there are audits to send
        if (!empty($audit_notification_past_due) || !empty($audit_notification_due_today) || !empty($audit_notification_due_1) || !empty($audit_notification_due_2) || !empty($audit_notification_due_3))
        {
            // Define the styles
            $table_style = "width:100%; border: 1px solid #ddd;border-collapse: collapse;";
            $th_style = $td_style = "border: 1px solid #ddd;";
            $th_style .= "background-color: #ebe7e7 !important;";
            $caption_style = "border: 1px solid #ddd;background-color: #ddd !important;padding: 5px;";

            // Create the message
            $message = "<html><body>\n";
            $message .= "<p>You are receiving this message because you are the designated tester, the control's owner or an additional stakeholder for the following audit(s):</p>\n";

            // If there is an audit due today
            if (!empty($audit_notification_due_today))
            {
                // Display the table header
                $message .= "<p>\n";
                $message .= "<table cellpadding=\"10px\" style=\"{$table_style}\">\n";
                $message .= "<caption style=\"{$caption_style}\"><b><u>" . $escaper->escapeHtml($lang['AuditsDueToday']) . "</u></b></caption>\n";
                $message .= "<tr>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['TestName']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Objective']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['ApproximateTime']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['DateDue']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Tester']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['ControlOwner']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['AdditionalStakeholders']) . "</th>\n";
                $message .= "</tr>\n";

                // For each audit due today
                foreach ($audit_notification_due_today as $audit)
                {
                    $message .= "<tr>\n";
                    $message .= "<td align=\"left\" style=\"{$td_style}\"><a href=\"" . $simplerisk_base_url . "/compliance/testing.php?id=" . $escaper->escapeHtml($audit['id']) . "\">" . $escaper->escapeHtml($audit['name']) . "</a></td>\n";
                    $message .= "<td align=\"left\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['objective']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['approximate_time']) . " " . $escaper->escapeHtml($lang['minutes']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['next_date']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['tester_name']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['control_owner_name']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml(get_stakeholder_names($audit['additional_stakeholders'])) . "</td>\n";
                    $message .= "</tr>\n";
                }

                // End the table
                $message .= "</table>\n";
                $message .= "</p>\n";
            }

            // If there are past due audits
            if (!empty($audit_notification_past_due))
            {
                // Display the table header
                $message .= "<p>\n";
                $message .= "<table cellpadding=\"10px\" style=\"{$table_style}\">\n";
                $message .= "<caption style=\"{$caption_style}\"><b><u>" . $escaper->escapeHtml($lang['AuditsPastDue']) . "</u></b></caption>\n";
                $message .= "<tr>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['TestName']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Objective']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['ApproximateTime']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['DateDue']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Tester']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['ControlOwner']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['AdditionalStakeholders']) . "</th>\n";
                $message .= "</tr>\n";

                // For each past due audit
                foreach ($audit_notification_past_due as $audit)
                {
                    $message .= "<tr>\n";
                    $message .= "<td align=\"left\" style=\"{$td_style}\"><a href=\"" . $simplerisk_base_url . "/compliance/testing.php?id=" . $escaper->escapeHtml($audit['id']) . "\">" . $escaper->escapeHtml($audit['name']) . "</a></td>\n";
                    $message .= "<td align=\"left\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['objective']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['approximate_time']) . " " . $escaper->escapeHtml($lang['minutes'])  . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['next_date']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['tester_name']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['control_owner_name']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml(get_stakeholder_names($audit['additional_stakeholders'])) . "</td>\n";
                    $message .= "</tr>\n";
                }

                // End the table
                $message .= "</table>\n";       
                $message .= "</p>\n";
            } 

            // If there is an audit due in the future
            if (!empty($audit_notification_due_1) || !empty($audit_notification_due_2) || !empty($audit_notification_due_3))
            {
                // Display the table header
                $message .= "<p>\n";
                $message .= "<table cellpadding=\"10px\" style=\"{$table_style}\">\n";
                $message .= "<caption style=\"{$caption_style}\"><b><u>" . $escaper->escapeHtml($lang['AuditsDueSoon']) . "</u></b></caption>\n";
                $message .= "<tr>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['TestName']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Objective']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['ApproximateTime']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['DateDue']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['Tester']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['ControlOwner']) . "</th>\n";
                $message .= "<th style=\"{$th_style}\">" . $escaper->escapeHtml($lang['AdditionalStakeholders']) . "</th>\n";
                $message .= "</tr>\n";

                // For each audit due 1
                foreach ($audit_notification_due_1 as $audit)
                {
                    $message .= "<tr>\n";
                    $message .= "<td align=\"left\" style=\"{$td_style}\"><a href=\"" . $simplerisk_base_url . "/compliance/testing.php?id=" . $escaper->escapeHtml($audit['id']) . "\">" . $escaper->escapeHtml($audit['name']) . "</a></td>\n";
                    $message .= "<td align=\"left\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['objective']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['approximate_time']) . " " . $escaper->escapeHtml($lang['minutes'])  . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['next_date']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['tester_name']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['control_owner_name']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml(get_stakeholder_names($audit['additional_stakeholders'])) . "</td>\n";
                    $message .= "</tr>\n";
                }

                // For each audit due 2
                foreach ($audit_notification_due_2 as $audit)
                {
                    $message .= "<tr>\n";
                    $message .= "<td align=\"left\" style=\"{$td_style}\"><a href=\"" . $simplerisk_base_url . "/compliance/testing.php?id=" . $escaper->escapeHtml($audit['id']) . "\">" . $escaper->escapeHtml($audit['name']) . "</a></td>\n";
                    $message .= "<td align=\"left\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['objective']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['approximate_time']) . " " . $escaper->escapeHtml($lang['minutes'])  . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['next_date']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['tester_name']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['control_owner_name']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml(get_stakeholder_names($audit['additional_stakeholders'])) . "</td>\n";
                    $message .= "</tr>\n";
                }

                // For each audit due 3
                foreach ($audit_notification_due_3 as $audit)
                {
                    $message .= "<tr>\n";
                    $message .= "<td align=\"left\" style=\"{$td_style}\"><a href=\"" . $simplerisk_base_url . "/compliance/testing.php?id=" . $escaper->escapeHtml($audit['id']) . "\">" . $escaper->escapeHtml($audit['name']) . "</a></td>\n";
                    $message .= "<td align=\"left\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['objective']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['approximate_time']) . " " . $escaper->escapeHtml($lang['minutes'])  . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['next_date']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['tester_name']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml($audit['control_owner_name']) . "</td>\n";
                    $message .= "<td align=\"center\" style=\"{$td_style}\">" . $escaper->escapeHtml(get_stakeholder_names($audit['additional_stakeholders'])) . "</td>\n";
                    $message .= "</tr>\n";
                }

                // End the table
                $message .= "</table>\n";       
                $message .= "</p>\n";
            } 

            // End the message
            $message .= "<p>This is an automated message and responses will be ignored or rejected.</p>\n";
            $message .= "</body></html>\n";

            // Create the subject
            $subject = "Notification of Audits Due";

            // Send the email
            send_email($name, $email, $subject, $message);
        }
    }

    // Close the database connection
    db_close($db);
}

/**********************************************
 * FUNCTION: AUTO RUN MITIGATION NOTIFICATION *
 **********************************************/
function auto_run_mitigation_notification()
{
    global $lang;
    global $escaper;

    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Get the list of users
    $users = get_custom_table("enabled_users");

    if (team_separation_extra()) {
        require_once(realpath(__DIR__ . '/../separation/index.php'));
        $separation = true;
    } else
        $separation = false;

    // Open the database connection
    $db = db_open();
    
    // For each user
    foreach ($users as $user)
    {
        $user_id = $user['value'];
        $name = $user['name'];
        $email = $user['email'];
        $teams = $user['teams'];
        
        // Get the teams the user is assigned to
        $user_teams = get_user_teams($user_id);

        if ($user_teams == "all")
        {
            $user_teams = get_all_teams();
        }

        $where = [0];

        if($AUTO_NOTIFY_MITIGATION_OWNER_PLANNED_MITIGATION == "true")
        {
            $where[] = "mg.mitigation_owner=:user_id";
        }

        if($AUTO_NOTIFY_MITIGATION_TEAM_PLANNED_MITIGATION == "true")
        {
            // Get the team query string
            $where[] = get_mitigation_team_query_string($user_teams, "mg");
        }

        if($AUTO_NOTIFY_RISK_SUBMITTER_PLANNED_MITIGATION == "true" &&
        (!$separation || get_setting('allow_submitter_to_risk')))
        {
            // Get the team query string
            $where[] = "a.submitted_by=:user_id";
        }

        if($AUTO_NOTIFY_RISK_OWNER_PLANNED_MITIGATION == "true" &&
        (!$separation || get_setting('allow_owner_to_risk')))
        {
            // Get the team query string
            $where[] = "a.owner=:user_id";
        }

        if($AUTO_NOTIFY_RISK_OWNERS_MANAGER_PLANNED_MITIGATION == "true" &&
        (!$separation || get_setting('allow_ownermanager_to_risk')))
        {
            // Get the team query string
            $where[] = "a.manager=:user_id";
        }

        if($AUTO_NOTIFY_RISK_TEAM_PLANNED_MITIGATION == "true" &&
        (!$separation || get_setting('allow_team_member_to_risk')))
        {
            // Get the team query string
            $where[] = get_team_query_string($user_teams, "a");
        }

        if($AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_PLANNED_MITIGATION == "true" &&
        (!$separation || get_setting('allow_stakeholder_to_risk')))
        {
            // Get the team query string
            $where[] = "FIND_IN_SET(:user_id, a.additional_stakeholders)";
        }

        $who_to_where = implode(" OR ", $where);

        $today = date("Y-m-d");

        // If we are supposed to notify after an mitigation is due
        if ($NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL == "true")
        {
            // Get all mitigations whose due dates are past
            $query = "
                SELECT a.*, mg.*, risk_id + 1000 plus_risk_id
                FROM risks a
                    INNER JOIN mitigations mg ON a.id = mg.risk_id
                WHERE a.status<>'Closed' AND mg.planning_date < :today AND ({$who_to_where}) ;
            ";
            $stmt = $db->prepare($query);
            
            // Check if query has user_id value
            if(stripos($query, ":user_id") !== false){
                $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            }
            $stmt->bindParam(":today", $today, PDO::PARAM_STR, 20);
            $stmt->execute();
            $mitigation_notification_past_due = $stmt->fetchAll();
        }

        // If we are supposed to notify when a mitigation is due
        if ($NOTIFICATION_SEND_MITIGATION_DUE_EMAIL == "true")
        {
            // Get all mitigations due today
            $query = "
                SELECT a.*, mg.*, risk_id + 1000 plus_risk_id
                FROM risks a
                    INNER JOIN mitigations mg ON a.id = mg.risk_id
                WHERE a.status<>'Closed' AND mg.planning_date = :today AND ({$who_to_where}) ;
            ";
            $stmt = $db->prepare($query);
            
            // Check if query has user_id value
            if(stripos($query, ":user_id") !== false){
                $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            }
            $stmt->bindParam(":today", $today, PDO::PARAM_STR, 20);
            $stmt->execute();
            $mitigation_notification_due_today = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // If we are supposed to notify before the mitigation is due
        if ($NOTIFICATION_SEND_MITIGATION_EMAIL_1 == "true")
        {
            // Get all mitigations due at this interval
            $query = "
                SELECT a.*, mg.*, risk_id + 1000 plus_risk_id
                FROM risks a
                    INNER JOIN mitigations mg ON a.id = mg.risk_id
                WHERE a.status<>'Closed' AND mg.planning_date = DATE_ADD(:today, INTERVAL :date DAY) AND ({$who_to_where}) ;
            ";
            $stmt = $db->prepare($query);
            
            // Check if query has user_id value
            if(stripos($query, ":user_id") !== false){
                $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            }
            $stmt->bindParam(":today", $today, PDO::PARAM_STR, 20);
            $stmt->bindParam(":date", $NOTIFICATION_SEND_MITIGATION_EMAIL_1_VALUE, PDO::PARAM_INT);
            $stmt->execute();
            $mitigation_notification_due_1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // If we are supposed to notify before the mitigation is due
        if ($NOTIFICATION_SEND_MITIGATION_EMAIL_2 == "true")
        {
            // Get all mitigations due at this interval
            $query = "
                SELECT a.*, mg.*, risk_id + 1000 plus_risk_id
                FROM risks a
                    INNER JOIN mitigations mg ON a.id = mg.risk_id
                WHERE a.status<>'Closed' AND mg.planning_date = DATE_ADD(:today, INTERVAL :date DAY) AND ({$who_to_where}) ;
            ";
            $stmt = $db->prepare($query);
            
            // Check if query has user_id value
            if(stripos($query, ":user_id") !== false){
                $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            }
            $stmt->bindParam(":today", $today, PDO::PARAM_STR, 20);
            $stmt->bindParam(":date", $NOTIFICATION_SEND_MITIGATION_EMAIL_2_VALUE, PDO::PARAM_INT);
            $stmt->execute();
            $mitigation_notification_due_2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // If we are supposed to notify before the mitigation is due
        if ($NOTIFICATION_SEND_MITIGATION_EMAIL_3 == "true")
        {
            // Get all mitigations due at this interval
            $query = "
                SELECT a.*, mg.*, risk_id + 1000 plus_risk_id
                FROM risks a
                    INNER JOIN mitigations mg ON a.id = mg.risk_id
                WHERE a.status<>'Closed' AND mg.planning_date = DATE_ADD(:today, INTERVAL :date DAY) AND ({$who_to_where}) ;
            ";
            $stmt = $db->prepare($query);
            
            // Check if query has user_id value
            if(stripos($query, ":user_id") !== false){
                $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            }
            $stmt->bindParam(":today", $today, PDO::PARAM_STR, 20);
            $stmt->bindParam(":date", $NOTIFICATION_SEND_MITIGATION_EMAIL_3_VALUE, PDO::PARAM_INT);
            $stmt->execute();
            $mitigation_notification_due_3 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($separation) {
            $mitigation_notification_past_due = strip_no_access_risks($mitigation_notification_past_due, $user_id);
            $mitigation_notification_due_today = strip_no_access_risks($mitigation_notification_due_today, $user_id);
            $mitigation_notification_due_1 = strip_no_access_risks($mitigation_notification_due_1, $user_id);
            $mitigation_notification_due_2 = strip_no_access_risks($mitigation_notification_due_2, $user_id);
            $mitigation_notification_due_3 = strip_no_access_risks($mitigation_notification_due_3, $user_id);
        }

        // If there are mitigations to send
        if (!empty($mitigation_notification_past_due) || !empty($mitigation_notification_due_today) || !empty($mitigation_notification_due_1) || !empty($mitigation_notification_due_2) || !empty($mitigation_notification_due_3))
        {
            // Create the message
            $message = "<html><body>\n";
            $message .= "<p>".$escaper->escapeHtml($lang['MitigationsNotificationEmailTitle'])."</p>\n";

            // If there is an mitigation due today
            if (!empty($mitigation_notification_due_today))
            {
                $message .= mitigation_due_html($mitigation_notification_due_today, $lang['MitigationsDueToday']);
            }

            // If there are past due mitigations
            if (!empty($mitigation_notification_past_due))
            {
                $message .= mitigation_due_html($mitigation_notification_past_due, $lang['MitigationsPastDue']);
            } 

            // If there is a mitigation due in the future
            if (!empty($mitigation_notification_due_1) || !empty($mitigation_notification_due_2) || !empty($mitigation_notification_due_3))
            {
                $message .= mitigation_due_html($mitigation_notification_due_1, _lang("MitigationsDueSoon", array("DueDate" => $NOTIFICATION_SEND_MITIGATION_EMAIL_1_VALUE)) );

                $message .= mitigation_due_html($mitigation_notification_due_2, _lang("MitigationsDueSoon", array("DueDate" => $NOTIFICATION_SEND_MITIGATION_EMAIL_2_VALUE)) );

                $message .= mitigation_due_html($mitigation_notification_due_3, _lang("MitigationsDueSoon", array("DueDate" => $NOTIFICATION_SEND_MITIGATION_EMAIL_3_VALUE)) );
            }

            // End the message
            $message .= "<p>This is an automated message and responses will be ignored or rejected.</p>\n";
            $message .= "</body></html>\n";
            
            // Create the subject
            $subject = "Notification of Mitigations Due";

            // Send the email
            send_email($name, $email, $subject, $message);
        }
    }

    // Close the database connection
    db_close($db);
}

/**************************************
 * FUNCTION: MAKE MITIGATION DUE HTML *
 **************************************/
function mitigation_due_html($mitigations, $label)
{
    global $lang, $escaper;
    
    $simplerisk_base_url = get_setting("simplerisk_base_url");

    // Define the styles
    $table_style = "width:100%; border: 1px solid #ddd;border-collapse: collapse;";
    $th_style = $td_style = "border: 1px solid #ddd;";
    $th_style .= "background-color: #ebe7e7 !important;";
    $caption_style = "border: 1px solid #ddd;background-color: #ddd !important;padding: 5px;";

    // Display the table header
    $message = "<p>\n";
    $message .= "<table cellpadding=\"10px\" style=\"${table_style}\">\n";
    $message .= "<caption style=\"${caption_style}\"><b><u>" . $escaper->escapeHtml($label) . "</u></b></caption>\n";
    $message .= "<tr>\n";
    $message .= "<th style=\"${th_style}\">" . $escaper->escapeHtml($lang['Subject']) . "</th>\n";
    $message .= "<th style=\"${th_style}\">" . $escaper->escapeHtml($lang['MitigationDate']) . "</th>\n";
    $message .= "<th style=\"${th_style}\">" . $escaper->escapeHtml($lang['DateDue']) . "</th>\n";
    $message .= "</tr>\n";

    // For each audit due today
    foreach ($mitigations as $mitigation)
    {

        $submission_date = format_date($mitigation['submission_date']);
        $planning_date = format_date($mitigation['planning_date']);        
        
        $message .= "<tr>\n";
        $message .= "<td style=\"${td_style}\" align=\"left\"><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $mitigation['plus_risk_id'] . "&type=1\">" . $escaper->escapeHtml(try_decrypt($mitigation['subject'])) . "</a></td>\n";
        $message .= "<td style=\"${td_style}\" align=\"center\">" . $escaper->escapeHtml($submission_date) . "</td>\n";
        $message .= "<td style=\"${td_style}\" align=\"center\">" . $escaper->escapeHtml($planning_date) . "</td>\n";
        $message .= "</tr>\n";
    }

    // End the table
    $message .= "</table>\n";
    $message .= "</p>\n";

    return $message;
}

/************************************************************************************
 * FUNCTION: NOTIFY RISK UPDATE FROM JIRA                                           *
 * Sending a notification email when the risk was updated,                          *
 * triggered by the a change of an associated Jira issue                            *
 * $risk_id: Id if the risk                                                         *
 * $changes: The changes happened in the format of                                  *
 * [{'<changed field's name>': {'from': '<original value>', 'to': <new value>}}]    *
 ************************************************************************************/
function notify_risk_update_from_jira($risk_id, $changes) {

    global $escaper, $lang;

    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config) {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we are supposed to notify on a risk update
    if ($NOTIFY_ON_RISK_UPDATE == "true") {

        // Add 1000 to get the risk ID
        $risk_id = (int)$risk_id + 1000;

        // Create the message
        $email_subject = "Risk ID " . $escaper->escapeHtml($risk_id) . " Updated";
        $email_message = "<p>Risk ID " . $escaper->escapeHtml($risk_id) . " was updated from Jira.  You are receiving this message because you are listed as either the risk owner, the risk owner's manager, an additional stakeholder or part of the team associated with the risk.</p>\n";

        // If verbosity is enabled
        if ($VERBOSE == "true" && $changes) {
            $email_message .= "<p><b><u>Risk Details:</u></b></p>\n";
            $email_message .= "<table rules=\"all\" style=\"border-color: #666;\" cellpadding=\"10\">\n";
            $email_message .= 
                "<tr>
                    <td><strong>". $escaper->escapeHtml($lang['ChangedField']) ."</strong></td>
                    <td><strong>" . $escaper->escapeHtml($lang['ChangedFrom']) . "</strong></td>
                    <td><strong>" . $escaper->escapeHtml($lang['ChangedTo']) . "</strong></td>
                </tr>";
            foreach($changes as $field => $change) {
                $email_message .= 
                    "<tr>
                        <td>" . $escaper->escapeHtml($field) . "</td>
                        <td>" . $escaper->escapeHtml($change['from']) . "</td>
                        <td>" . $escaper->escapeHtml($change['to']) . "</td>
                    </tr>";
            }

            $email_message .= "</table>\n";
        }

        $email_message .= "<p><b><u>" . $escaper->escapeHtml($lang['Actions']) . ":</u></b></p><ul>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "\">View the Risk</a></li>\n";
        $email_message .= "<li><a href=\"" . $simplerisk_base_url . "/management/view.php?id=" . $escaper->escapeHtml($risk_id) . "&type=2&action=editreview\">Perform a Review</a></li>\n";
        $email_message .= "</ul>\n";

        // Send the e-mail
        prepare_email($risk_id, $email_subject, $email_message, "risk_update");                    
    }
}
?>

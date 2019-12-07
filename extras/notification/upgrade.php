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

// Name of the version value in the settings table
//define('VERSION_NAME', 'notification_extra_version');

global $notification_updates;
$notification_updates = array(
    'upgrade_notification_extra_20170922001',
    'upgrade_notification_extra_20171201001',
    'upgrade_notification_extra_20180204001',
    'upgrade_notification_extra_20180210001',
    'upgrade_notification_extra_20180707001',
    'upgrade_notification_extra_20181224001',
    'upgrade_notification_extra_20190116001',
    'upgrade_notification_extra_20190425001',
    'upgrade_notification_extra_20190629001'
);

/*************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA DATABASE *
 *************************************************/
function upgrade_notification_extra_database()
{
    global $notification_updates;

    $version_name = 'notification_extra_version';

    // Get the current database version
    $db_version = get_setting($version_name);

    // If the database setting does not exist
    if(!$db_version)
    {
        // Set the initial version to 0
        $db_version = 0;
        update_or_insert_setting($version_name, $db_version);
    }

    // If there is a function to upgrade to the next version
    if (array_key_exists($db_version, $notification_updates))
    {
        // Get the function to upgrade to the next version
        $function = $notification_updates[$db_version];

        // If the function exists
        if (function_exists($function))
        {
            // Call the function
            call_user_func($function);

            // Set the next database version
            $db_version = $db_version + 1;

            // Update the database version
            update_or_insert_setting($version_name, $db_version);

            // Call the upgrade function again
            upgrade_notification_extra_database();
        }
    }
}


/****************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA 20170922001 *
 ****************************************************/
function upgrade_notification_extra_20170922001()
{
    // Connect to the database
    $db = db_open();


    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_RISK_COMMENT', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'; ");
    $stmt->execute();

    // Create a table for history of Cron Job
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `cron_history` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `process_id` varchar(100) DEFAULT NULL,
          `sent_at` datetime NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;    
    ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_PERIOD', `value` = ''; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_HOUR', `value` = '0'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_MINUTE', `value` = '0'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_DAY_OF_WEEK', `value` = '0'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_DATE', `value` = '1'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_MONTH', `value` = '1'; ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/****************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA 20171201001 *
 ****************************************************/
function upgrade_notification_extra_20171201001()
{
    // Connect to the database
    $db = db_open();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_SUBMITTER', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_OWNER', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_OWNERS_MANAGER', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_TEAM', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_REVIEWERS', `value` = 'true'; ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/****************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA 20180204001 *
 ****************************************************/
function upgrade_notification_extra_20180204001()
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Connect to the database
    $db = db_open();

    $NOTIFY_SUBMITTER = "true";
    // Update submitter settings
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_RISK_NOTIFY_SUBMITTER', `value` = :notify_submitter ; ");
    $stmt->bindParam(":notify_submitter", $NOTIFY_SUBMITTER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_UPDATE_NOTIFY_SUBMITTER', `value` = :notify_submitter ; ");
    $stmt->bindParam(":notify_submitter", $NOTIFY_SUBMITTER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_MITIGATION_NOTIFY_SUBMITTER', `value` = :notify_submitter ; ");
    $stmt->bindParam(":notify_submitter", $NOTIFY_SUBMITTER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'MITIGATION_UPDATE_NOTIFY_SUBMITTER', `value` = :notify_submitter ; ");
    $stmt->bindParam(":notify_submitter", $NOTIFY_SUBMITTER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_REVIEW_NOTIFY_SUBMITTER', `value` = :notify_submitter ; ");
    $stmt->bindParam(":notify_submitter", $NOTIFY_SUBMITTER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_CLOSE_NOTIFY_SUBMITTER', `value` = :notify_submitter ; ");
    $stmt->bindParam(":notify_submitter", $NOTIFY_SUBMITTER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_COMMENT_NOTIFY_SUBMITTER', `value` = :notify_submitter ; ");
    $stmt->bindParam(":notify_submitter", $NOTIFY_SUBMITTER, PDO::PARAM_STR);
    $stmt->execute();

    $NOTIFY_OWNER = "true";
    // Update owner settings
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_RISK_NOTIFY_OWNER', `value` = :notify_owner ; ");
    $stmt->bindParam(":notify_owner", $NOTIFY_OWNER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_UPDATE_NOTIFY_OWNER', `value` = :notify_owner ; ");
    $stmt->bindParam(":notify_owner", $NOTIFY_OWNER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_MITIGATION_NOTIFY_OWNER', `value` = :notify_owner ; ");
    $stmt->bindParam(":notify_owner", $NOTIFY_OWNER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'MITIGATION_UPDATE_NOTIFY_OWNER', `value` = :notify_owner ; ");
    $stmt->bindParam(":notify_owner", $NOTIFY_OWNER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_REVIEW_NOTIFY_OWNER', `value` = :notify_owner ; ");
    $stmt->bindParam(":notify_owner", $NOTIFY_OWNER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_CLOSE_NOTIFY_OWNER', `value` = :notify_owner ; ");
    $stmt->bindParam(":notify_owner", $NOTIFY_OWNER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_COMMENT_NOTIFY_OWNER', `value` = :notify_owner ; ");
    $stmt->bindParam(":notify_owner", $NOTIFY_OWNER, PDO::PARAM_STR);
    $stmt->execute();

    $NOTIFY_OWNERS_MANAGER = "true";
    // Update owner's manager settings
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_RISK_NOTIFY_OWNERS_MANAGER', `value` = :notify_owners_manager ; ");
    $stmt->bindParam(":notify_owners_manager", $NOTIFY_OWNERS_MANAGER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_UPDATE_NOTIFY_OWNERS_MANAGER', `value` = :notify_owners_manager ; ");
    $stmt->bindParam(":notify_owners_manager", $NOTIFY_OWNERS_MANAGER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_MITIGATION_NOTIFY_OWNERS_MANAGER', `value` = :notify_owners_manager ; ");
    $stmt->bindParam(":notify_owners_manager", $NOTIFY_OWNERS_MANAGER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'MITIGATION_UPDATE_NOTIFY_OWNERS_MANAGER', `value` = :notify_owners_manager ; ");
    $stmt->bindParam(":notify_owners_manager", $NOTIFY_OWNERS_MANAGER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_REVIEW_NOTIFY_OWNERS_MANAGER', `value` = :notify_owners_manager ; ");
    $stmt->bindParam(":notify_owners_manager", $NOTIFY_OWNERS_MANAGER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_CLOSE_NOTIFY_OWNERS_MANAGER', `value` = :notify_owners_manager ; ");
    $stmt->bindParam(":notify_owners_manager", $NOTIFY_OWNERS_MANAGER, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_COMMENT_NOTIFY_OWNERS_MANAGER', `value` = :notify_owners_manager ; ");
    $stmt->bindParam(":notify_owners_manager", $NOTIFY_OWNERS_MANAGER, PDO::PARAM_STR);
    $stmt->execute();

    $NOTIFY_TEAM = "true";
    // Update team settings
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_RISK_NOTIFY_TEAM', `value` = :notify_team ; ");
    $stmt->bindParam(":notify_team", $NOTIFY_TEAM, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_UPDATE_NOTIFY_TEAM', `value` = :notify_team ; ");
    $stmt->bindParam(":notify_team", $NOTIFY_TEAM, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_MITIGATION_NOTIFY_TEAM', `value` = :notify_team ; ");
    $stmt->bindParam(":notify_team", $NOTIFY_TEAM, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'MITIGATION_UPDATE_NOTIFY_TEAM', `value` = :notify_team ; ");
    $stmt->bindParam(":notify_team", $NOTIFY_TEAM, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_REVIEW_NOTIFY_TEAM', `value` = :notify_team ; ");
    $stmt->bindParam(":notify_team", $NOTIFY_TEAM, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_CLOSE_NOTIFY_TEAM', `value` = :notify_team ; ");
    $stmt->bindParam(":notify_team", $NOTIFY_TEAM, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_COMMENT_NOTIFY_TEAM', `value` = :notify_team ; ");
    $stmt->bindParam(":notify_team", $NOTIFY_TEAM, PDO::PARAM_STR);
    $stmt->execute();

    $NOTIFY_ADDITIONAL_STAKEHOLDERS = "true";
    // Update additional stakeholders settings
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_RISK_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = :notify_additional_stakeholders ; ");
    $stmt->bindParam(":notify_additional_stakeholders", $NOTIFY_ADDITIONAL_STAKEHOLDERS, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = :notify_additional_stakeholders ; ");
    $stmt->bindParam(":notify_additional_stakeholders", $NOTIFY_ADDITIONAL_STAKEHOLDERS, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NEW_MITIGATION_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = :notify_additional_stakeholders ; ");
    $stmt->bindParam(":notify_additional_stakeholders", $NOTIFY_ADDITIONAL_STAKEHOLDERS, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'MITIGATION_UPDATE_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = :notify_additional_stakeholders ; ");
    $stmt->bindParam(":notify_additional_stakeholders", $NOTIFY_ADDITIONAL_STAKEHOLDERS, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_REVIEW_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = :notify_additional_stakeholders ; ");
    $stmt->bindParam(":notify_additional_stakeholders", $NOTIFY_ADDITIONAL_STAKEHOLDERS, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_CLOSE_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = :notify_additional_stakeholders ; ");
    $stmt->bindParam(":notify_additional_stakeholders", $NOTIFY_ADDITIONAL_STAKEHOLDERS, PDO::PARAM_STR);
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'RISK_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = :notify_additional_stakeholders ; ");
    $stmt->bindParam(":notify_additional_stakeholders", $NOTIFY_ADDITIONAL_STAKEHOLDERS, PDO::PARAM_STR);
    $stmt->execute();

    // Add a type column to the cron_history table
    if (!field_exists_in_table('type', 'cron_history')) {
        $stmt = $db->prepare("ALTER TABLE `cron_history` ADD COLUMN `type` varchar(100) NOT NULL DEFAULT 'risk' AFTER `sent_at`;");
        $stmt->execute();
    }

    // Disconnect from the database
    db_close($db);
}

/****************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA 20180210001 *
 ****************************************************/
function upgrade_notification_extra_20180210001()
{
    // Get the notification settings
    $configs = get_notification_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Connect to the database
    $db = db_open();

    // Set values for automated notifications of audits
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_HOUR', `value` = '0'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_MINUTE', `value` = '0'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_1', `value` = 'false'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_1_VALUE', `value` = '14'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_2', `value` = 'false'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_2_VALUE', `value` = '7'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_3', `value` = 'false'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_EMAIL_3_VALUE', `value` = '2'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_DUE_EMAIL', `value` = 'false'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL', `value` = 'false'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_AUDIT_AFTER_DUE_EMAIL_VALUE', `value` = '1'; ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/****************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA 20180707001 *
 ****************************************************/
function upgrade_notification_extra_20180707001()
{
    // Connect to the database
    $db = db_open();

    // Set values for automated notifications of mitigations
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_HOUR', `value` = '0'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_MINUTE', `value` = '0'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_1', `value` = 'false'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_1_VALUE', `value` = '14'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_2', `value` = 'false'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_2_VALUE', `value` = '7'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_3', `value` = 'false'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_EMAIL_3_VALUE', `value` = '2'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_DUE_EMAIL', `value` = 'false'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL', `value` = 'false'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFICATION_SEND_MITIGATION_AFTER_DUE_EMAIL_VALUE', `value` = 'false'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_MITIGATION_OWNER_PLANNED_MITIGATION', `value` = 'true'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_MITIGATION_TEAM_PLANNED_MITIGATION', `value` = 'true'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_RISK_SUBMITTER_PLANNED_MITIGATION', `value` = 'true'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_RISK_OWNER_PLANNED_MITIGATION', `value` = 'true'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_RISK_OWNERS_MANAGER_PLANNED_MITIGATION', `value` = 'true'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_RISK_TEAM_PLANNED_MITIGATION', `value` = 'true'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_PLANNED_MITIGATION', `value` = 'true'; ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/****************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA 20181224001 *
 ****************************************************/
function upgrade_notification_extra_20181224001()
{
    // Connect to the database
    $db = db_open();

    // Set values for automated notifications of audit test comments    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_AUDIT_COMMENT', `value` = 'true'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUDIT_COMMENT_NOTIFY_TESTER', `value` = 'true'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUDIT_COMMENT_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'; ");
    $stmt->execute();

    // Set values for automated notifications of audit test status changes
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NOTIFY_ON_AUDIT_STATUS_CHANGE', `value` = 'true'; ");
    $stmt->execute();        
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUDIT_STATUS_CHANGE_NOTIFY_TESTER', `value` = 'true'; ");
    $stmt->execute();    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUDIT_STATUS_CHANGE_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'; ");
    $stmt->execute();        
    
    // Disconnect from the database
    db_close($db);
}

/****************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA 20190116001 *
 ****************************************************/
function upgrade_notification_extra_20190116001()
{
    // Connect to the database
    $db = db_open();

    // Set values for automated notifications of audit test comments
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUDIT_COMMENT_NOTIFY_CONTROL_OWNER', `value` = 'true';");
    $stmt->execute();

    // Set values for automated notifications of audit test status changes
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUDIT_STATUS_CHANGE_NOTIFY_CONTROL_OWNER', `value` = 'true';");
    $stmt->execute();

    // Set default values for the audit auto notifications
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_TESTER_AUDITS', `value` = 'true';");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_CONTROL_OWNER_AUDITS', `value` = 'true';");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS_AUDITS', `value` = 'true';");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/****************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA 20190425001 *
 ****************************************************/
function upgrade_notification_extra_20190425001()
{
    // Connect to the database
    $db = db_open();

    // Set values for automated notifications of audits
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_NON_MITIGATION_PERIOD', `value` = ''; ");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_NON_MITIGATION_HOUR', `value` = '0'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_NON_MITIGATION_MINUTE', `value` = '0'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_NON_MITIGATION_DAY_OF_WEEK', `value` = '0'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_NON_MITIGATION_DATE', `value` = '1'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_NON_MITIGATION_MONTH', `value` = '1'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NON_MITIGATION_AUTO_NOTIFY_SUBMITTER', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NON_MITIGATION_AUTO_NOTIFY_OWNER', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NON_MITIGATION_AUTO_NOTIFY_OWNERS_MANAGER', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NON_MITIGATION_AUTO_NOTIFY_TEAM', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'NON_MITIGATION_AUTO_NOTIFY_ADDITIONAL_STAKEHOLDERS', `value` = 'true'; ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/****************************************************
 * FUNCTION: UPGRADE NOTIFICATION EXTRA 20190629001 *
 ****************************************************/
function upgrade_notification_extra_20190629001()
{
    // Connect to the database
    $db = db_open();

    // Set values for review of policy and control exceptions
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_POLICY_CONTROL_EXCEPTION_REVIEW_PERIOD', `value` = ''; ");
    $stmt->execute();
    
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_POLICY_CONTROL_EXCEPTION_REVIEW_HOUR', `value` = '0'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MINUTE', `value` = '0'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_POLICY_CONTROL_EXCEPTION_REVIEW_DAY_OF_WEEK', `value` = '0'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_POLICY_CONTROL_EXCEPTION_REVIEW_DATE', `value` = '1'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CRON_POLICY_CONTROL_EXCEPTION_REVIEW_MONTH', `value` = '1'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'POLICY_CONTROL_EXCEPTION_REVIEW_CONTROL_OWNER', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'POLICY_CONTROL_EXCEPTION_REVIEW_POLICY_OWNER', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'POLICY_CONTROL_EXCEPTION_REVIEW_ADDITIONAL_STAKEHOLDERS', `value` = 'true'; ");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'POLICY_CONTROL_EXCEPTION_REVIEW_APPROVER', `value` = 'true'; ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

?>

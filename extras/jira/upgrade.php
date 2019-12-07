<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

global $jira_updates;
$jira_updates = array(
    'upgrade_jira_extra_20190907001',
);

/*****************************************
 * FUNCTION: UPGRADE JIRA EXTRA DATABASE *
 *****************************************/
function upgrade_jira_extra_database()
{
    global $jira_updates;

    $version_name = 'jira_extra_version';

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
    if (array_key_exists($db_version, $jira_updates))
    {
        // Get the function to upgrade to the next version
        $function = $jira_updates[$db_version];

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
            upgrade_jira_extra_database();
        }
    }
}

/********************************************
 * FUNCTION: UPGRADE JIRA EXTRA 20190907001 *
 ********************************************/
function upgrade_jira_extra_20190907001()
{
    // Open the database connection
    $db = db_open();

    // Create mapping table
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
}


?>

<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

// Name of the version value in the settings table
//define('VERSION_NAME', 'separation_extra_version');

global $separation_updates;
$separation_updates = array(
    'upgrade_seperation_extra_20181110001',
);

/*************************************************
 * FUNCTION: UPGRADE SEPARATION EXTRA DATABASE *
 *************************************************/
function upgrade_separation_extra_database()
{
    global $separation_updates;
    
    $version_name = 'separation_extra_version';
    
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
    if (array_key_exists($db_version, $separation_updates))
    {
        // Get the function to upgrade to the next version
        $function = $separation_updates[$db_version];

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
            upgrade_separation_extra_database();
        }
    }
}

/**************************************************
 * FUNCTION: UPGRADE SEPERATION EXTRA 20181110001 *
 **************************************************/
function upgrade_seperation_extra_20181110001()
{
    // Connect to the database
    $db = db_open();
    
    // Add or Update the allow_all_to_risk_noassign_team setting value.
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'allow_all_to_risk_noassign_team', `value` = '0' ON DUPLICATE KEY UPDATE `value` = '0'; ");
    $stmt->bindParam(":name", $key, PDO::PARAM_STR, 50);
    $stmt->bindParam(":value", $value, PDO::PARAM_INT);
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

?>

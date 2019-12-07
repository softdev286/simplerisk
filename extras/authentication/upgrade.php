<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

// Name of the version value in the settings table
//define('VERSION_NAME', 'authentication_extra_version');

global $authentication_updates;
$authentication_updates = array(
    'upgrade_authentication_extra_20180411001',
    'upgrade_authentication_extra_20180727001',
    'upgrade_authentication_extra_20180927001',
    'upgrade_authentication_extra_20181003001',
    'upgrade_authentication_extra_20181126001',
    'upgrade_authentication_extra_20190623001'
);

/***************************************************
 * FUNCTION: UPGRADE AUTHENTICATION EXTRA DATABASE *
 ***************************************************/
function upgrade_authentication_extra_database()
{
    global $authentication_updates;

    $version_name = 'authentication_extra_version';

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
    if (array_key_exists($db_version, $authentication_updates))
    {
        // Get the function to upgrade to the next version
        $function = $authentication_updates[$db_version];

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
            upgrade_authentication_extra_database();
        }
    }
}

/******************************************************
 * FUNCTION: UPGRADE AUTHENTICATION EXTRA 20180411001 *
 ******************************************************/
function upgrade_authentication_extra_20180411001()
{
    // Connect to the database
    $db = db_open();
    
    // Set default setting of GO_TO_SSO_LOGIN to 1
    update_or_insert_setting("GO_TO_SSO_LOGIN", 1);

    // Disconnect from the database
    db_close($db);
}

/******************************************************
 * FUNCTION: UPGRADE AUTHENTICATION EXTRA 20180727001 *
 ******************************************************/
function upgrade_authentication_extra_20180727001()
{
    // Connect to the database
    $db = db_open();

    // Set default setting of AUTHENTICATION_ADD_NEW_USERS to 0
    update_or_insert_setting("AUTHENTICATION_ADD_NEW_USERS", 0);

    // Disconnect from the database
    db_close($db);
}

/******************************************************
 * FUNCTION: UPGRADE AUTHENTICATION EXTRA 20180927001 *
 ******************************************************/
function upgrade_authentication_extra_20180927001()
{
    // Connect to the database
    $db = db_open();

    // Set default setting of LDAP_USER_ATTRIBUTE to dn
    update_or_insert_setting("AUTHENTICATION_LDAP_USER_ATTRIBUTE", "dn");

    // Disconnect from the database
    db_close($db);
}

/******************************************************
 * FUNCTION: UPGRADE AUTHENTICATION EXTRA 20180927001 *
 ******************************************************/
function upgrade_authentication_extra_20181003001()
{
    // Connect to the database
    $db = db_open();

    // Create a table for LDAP Group
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `ldap_groups` (
          `value` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(254) NOT NULL,
          `team_ids` varchar(1000) DEFAULT NULL,
          PRIMARY KEY (`value`),
          UNIQUE KEY `value` (`value`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/******************************************************
 * FUNCTION: UPGRADE AUTHENTICATION EXTRA 20181126001 *
 ******************************************************/
function upgrade_authentication_extra_20181126001()
{
    // Connect to the database
    $db = db_open();

    // Add default GROUPDN setting
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'GROUPDN', `value` = 'OU=Users,DC=Company,DC=Corp,DC=Domain,DC=COM';");
    $stmt->execute();

    // Add default GROUP ATTRIBUTE setting
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'LDAP_GROUP_ATTRIBUTE', `value` = 'cn';");
    $stmt->execute();

    // Add default MEMBER ATTRIBUTE setting
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'LDAP_MEMBER_ATTRIBUTE', `value` = 'uniquemember';");
    $stmt->execute();

    // Drop table ldap_groups
    $stmt = $db->prepare("DROP TABLE `ldap_groups`;");
    $stmt->execute();

    // Create a table for LDAP Group and Teams releation
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `ldap_group_and_teams` (
          `value` int(11) NOT NULL AUTO_INCREMENT,
          `team_id` int(11) DEFAULT NULL,
          `group_name` varchar(254) NOT NULL,
          PRIMARY KEY (`value`),
          UNIQUE KEY `value` (`value`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/******************************************************
 * FUNCTION: UPGRADE AUTHENTICATION EXTRA 20190110001 *
 ******************************************************/
function upgrade_authentication_extra_20190110001()
{
    // Connect to the database
    $db = db_open();

    // If the USERNAME_ATTRIBUTE value does not already exist, add it
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'USERNAME_ATTRIBUTE', `value` = 'uid'");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/******************************************************
 * FUNCTION: UPGRADE AUTHENTICATION EXTRA 20190623001 *
 ******************************************************/
function upgrade_authentication_extra_20190623001()
{
    // Connect to the database
    $db = db_open();

    // Set default value for ldap filter query to get group names
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'LDAP_FILTER_FOR_GROUP', `value` = '(|(|(|(objectClass=posixGroup)(objectClass=groupOfUniqueNames))(objectClass=groupOfNames))(objectClass=group))'; ");
    $stmt->execute();

    // Set default value for ldap manager attribute
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'LDAP_MANAGER_ATTRIBUTE', `value` = ''; ");
    $stmt->execute();

    // Set default value for "Automatically add a new user for a manager if they do not exist"
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTHENTICATION_ADD_NEW_MANAGER', `value` = '1'; ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

?>

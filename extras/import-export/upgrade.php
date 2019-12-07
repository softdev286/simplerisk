<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

// Name of the version value in the settings table
//define('VERSION_NAME', 'importexport_extra_version');

global $importexport_updates;
$importexport_updates = array(
    'upgrade_importexport_extra_20181111001',
    'upgrade_importexport_extra_20190110001',
    'upgrade_importexport_extra_20190815001',
    'upgrade_importexport_extra_20190822001'
);

/*************************************************
 * FUNCTION: UPGRADE IMPORTEXPORT EXTRA DATABASE *
 *************************************************/
function upgrade_importexport_extra_database()
{
    global $importexport_updates;

    $version_name = 'importexport_extra_version';

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
    if (array_key_exists($db_version, $importexport_updates))
    {
        // Get the function to upgrade to the next version
        $function = $importexport_updates[$db_version];

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
            upgrade_importexport_extra_database();
        }
    }
}

/****************************************************
 * FUNCTION: UPGRADE IMPORTEXPORT EXTRA 20181111001 *
 ****************************************************/
function upgrade_importexport_extra_20181111001()
{
    //Code moved over to the next (upgrade_importexport_extra_20190110001) function
}

/****************************************************
 * FUNCTION: UPGRADE IMPORTEXPORT EXTRA 20190110001 *
 ****************************************************/
function upgrade_importexport_extra_20190110001() {

    // Connect to the database
    $db = db_open();

    // Create a table for the file upload
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_tmp` (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, unique_name VARCHAR(30) NOT NULL, size INT NOT NULL, timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, content LONGBLOB NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Create a table for the mappings
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_mappings` (value INT NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, mapping BLOB, PRIMARY KEY (value)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Delete existing mapping value
    $stmt = $db->prepare("DELETE FROM `import_export_mappings` WHERE name='SimpleRisk Combined Import';");
    $stmt->execute();

    $mapping_json = get_simplerisk_combine_default_mapping_json();
    $mapping_serialize = serialize(json_decode($mapping_json, true));
    // Add the SimpleRisk mapping for the "Export Combined" import
    $stmt = $db->prepare("INSERT INTO `import_export_mappings` (`name`, `mapping`) VALUES ('SimpleRisk Combined Import', '{$mapping_serialize}');");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/****************************************************
 * FUNCTION: UPGRADE IMPORTEXPORT EXTRA 20190815001 *
 ****************************************************/
function upgrade_importexport_extra_20190815001() {

    // Connect to the database
    $db = db_open();

    // Create a table for the mappings
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_mappings` (value INT NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, mapping BLOB, PRIMARY KEY (value)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Delete existing mapping value
    $stmt = $db->prepare("DELETE FROM `import_export_mappings` WHERE name='SimpleRisk Combined Import';");
    $stmt->execute();

    $mapping_json = get_simplerisk_combine_default_mapping_json();
    $mapping_serialize = serialize(json_decode($mapping_json, true));
    // Add the SimpleRisk mapping for the "Export Combined" import
    $stmt = $db->prepare("INSERT INTO `import_export_mappings` (`name`, `mapping`) VALUES ('SimpleRisk Combined Import', '{$mapping_serialize}');");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/****************************************************
 * FUNCTION: UPGRADE IMPORTEXPORT EXTRA 20190822001 *
 ****************************************************/
function upgrade_importexport_extra_20190822001() {

    // Connect to the database
    $db = db_open();

    // Create a table for integration_assets
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_integration_assets` (`id` INT NOT NULL AUTO_INCREMENT, `integration_name` VARCHAR(100), `asset_id` INT(11), `uuid` VARCHAR(100), `has_agent` VARCHAR(100), `source_name` VARCHAR(100), `first_seen` VARCHAR(100), `last_seen` VARCHAR(100), `ipv4` BLOB, `fqdn` BLOB, `operating_system` BLOB, `netbios_name` BLOB, `agent_name` BLOB, `aws_ec2_name` BLOB, `mac_address` BLOB, PRIMARY KEY (`id`), UNIQUE (`uuid`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}
?>

<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

global $customization_updates;
$customization_updates = array(
    'upgrade_customization_extra_20180425001',
    'upgrade_customization_extra_20180531001',
    'upgrade_customization_extra_20190125001',
    'upgrade_customization_extra_20190213001',
    'upgrade_customization_extra_20190704001',
    'upgrade_customization_extra_20190802001',
    'upgrade_customization_extra_20190810001',
//    'upgrade_customization_extra_20190822001',
    'upgrade_customization_extra_20191027001',
);

/**************************************************
 * FUNCTION: UPGRADE CUSTOMIZATION EXTRA DATABASE *
 **************************************************/
function upgrade_customization_extra_database()
{
    global $customization_updates;

    $version_name = 'customization_extra_version';

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
    if (array_key_exists($db_version, $customization_updates))
    {
        // Get the function to upgrade to the next version
        $function = $customization_updates[$db_version];

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
            upgrade_customization_extra_database();
        }
    }
}

/*****************************************************
 * FUNCTION: UPGRADE CUSTOMIZATION EXTRA 20180425001 *
 *****************************************************/
function upgrade_customization_extra_20180425001()
{
    // Connect to the database
    $db = db_open();
    
    // Rename `fields` table name to `custom_fields`
    if (table_exists('fields') && !table_exists('custom_fields')) {
        $stmt = $db->prepare("RENAME TABLE `fields` TO `custom_fields`;");
        $stmt->execute();
    }

    // Add is_basic, tab_index fields into custom_fields table
    if (!field_exists_in_table('is_basic', 'custom_fields')) {
        $stmt = $db->prepare("ALTER TABLE `custom_fields` ADD `is_basic` TINYINT NOT NULL COMMENT '1: basic field';");
        $stmt->execute();
    }
    if (!field_exists_in_table('tab_index', 'custom_fields')) {
        $stmt = $db->prepare("ALTER TABLE `custom_fields` ADD `tab_index` INT NOT NULL COMMENT '1:details, 2: mitigation, 3: review';");
        $stmt->execute();
    }
    
    // Create custom_data table
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `custom_data` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `risk_id` int(11) NOT NULL,
          `field_id` int(11) NOT NULL,
          `value` text,
          PRIMARY KEY (`id`),
          KEY `risk_id` (`risk_id`),
          KEY `field_id` (`field_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;      
    ");
    $stmt->execute();

    // Create custom_template table
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `custom_template` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `custom_field_id` int(11) NOT NULL,
          `tab_index` int(11) NOT NULL COMMENT '1:details, 2: mitigation, 3: review',
          `ordering` int(11) NOT NULL,
          `panel_name` varchar(10) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Add default main fields to custom_fields table
    $stmt = $db->prepare("
        INSERT INTO `custom_fields` (`name`, `type`, `is_basic`, `tab_index`) VALUES
             ('SubmissionDate', '', '1', '1'),
             ('Category', '', '1', '1'),
             ('SiteLocation', '', '1', '1'),
             ('ExternalReferenceId', '', '1', '1'),
             ('ControlRegulation', '', '1', '1'),
             ('ControlNumber', '', '1', '1'),
             ('AffectedAssets', '', '1', '1'),
             ('Technology', '', '1', '1'),
             ('Team', '', '1', '1'),
             ('AdditionalStakeholders', '', '1', '1'),
             ('Owner', '', '1', '1'),
             ('OwnersManager', '', '1', '1'),
             ('SubmittedBy', '', '1', '1'),
             ('RiskSource', '', '1', '1'),
             ('RiskScoringMethod', '', '1', '1'),
             ('RiskAssessment', '', '1', '1'),
             ('AdditionalNotes', '', '1', '1'),
             ('SupportingDocumentation', '', '1', '1'),
             
             ('MitigationDate', '', '1', '2'),
             ('MitigationPlanning', '', '1', '2'),
             ('PlanningStrategy', '', '1', '2'),
             ('MitigationEffort', '', '1', '2'),
             ('MitigationCost', '', '1', '2'),
             ('MitigationOwner', '', '1', '2'),
             ('MitigationTeam', '', '1', '2'),
             ('MitigationPercent', '', '1', '2'),
             ('MitigationControls', '', '1', '2'),
             ('MitigationControlsList', '', '1', '2'),
             ('AcceptMitigation', '', '1', '2'),
             ('CurrentSolution', '', '1', '2'),
             ('SecurityRequirements', '', '1', '2'),
             ('SecurityRecommendations', '', '1', '2'),
             ('MitigationSupportingDocumentation', '', '1', '2'),
             
             ('ReviewDate', '', '1', '3'),
             ('Reviewer', '', '1', '3'),
             ('Review', '', '1', '3'),
             ('NextStep', '', '1', '3'),
             ('NextReviewDate', '', '1', '3'),
             ('Comment', '', '1', '3'),
             ('SetNextReviewDate', '', '1', '3')
        ON DUPLICATE KEY UPDATE
            `type` = VALUES(`type`),
            `is_basic` = VALUES(`is_basic`),
            `tab_index` = VALUES(`tab_index`);
    ");
    $stmt->execute();

    // Set default main fields for template
    $stmt = $db->prepare("
        INSERT INTO `custom_template` (`custom_field_id` ,`tab_index` ,`ordering` ,`panel_name`) 
            SELECT id, tab_index, 0, 'left' FROM `custom_fields` WHERE `name` = 'SubmissionDate'
            UNION 
            SELECT id, tab_index, 1, 'left' FROM `custom_fields` WHERE `name` = 'Category'
            UNION 
            SELECT id, tab_index, 2, 'left' FROM `custom_fields` WHERE `name` = 'SiteLocation'
            UNION 
            SELECT id, tab_index, 3, 'left' FROM `custom_fields` WHERE `name` = 'ExternalReferenceId'
            UNION 
            SELECT id, tab_index, 4, 'left' FROM `custom_fields` WHERE `name` = 'ControlRegulation'
            UNION 
            SELECT id, tab_index, 5, 'left' FROM `custom_fields` WHERE `name` = 'ControlNumber'
            UNION 
            SELECT id, tab_index, 6, 'left' FROM `custom_fields` WHERE `name` = 'AffectedAssets'
            UNION 
            SELECT id, tab_index, 7, 'left' FROM `custom_fields` WHERE `name` = 'Technology'
            UNION 
            SELECT id, tab_index, 8, 'left' FROM `custom_fields` WHERE `name` = 'Team'
            UNION 
            SELECT id, tab_index, 9, 'left' FROM `custom_fields` WHERE `name` = 'AdditionalStakeholders'
            UNION 
            SELECT id, tab_index, 10, 'left' FROM `custom_fields` WHERE `name` = 'Owner'
            UNION 
            SELECT id, tab_index, 11, 'left' FROM `custom_fields` WHERE `name` = 'OwnersManager'

            UNION 
            SELECT id, tab_index, 0, 'right' FROM `custom_fields` WHERE `name` = 'SubmittedBy'
            UNION 
            SELECT id, tab_index, 1, 'right' FROM `custom_fields` WHERE `name` = 'RiskSource'
            UNION 
            SELECT id, tab_index, 2, 'right' FROM `custom_fields` WHERE `name` = 'RiskScoringMethod'
            UNION 
            SELECT id, tab_index, 3, 'right' FROM `custom_fields` WHERE `name` = 'RiskAssessment'
            UNION 
            SELECT id, tab_index, 4, 'right' FROM `custom_fields` WHERE `name` = 'AdditionalNotes'
            UNION 
            SELECT id, tab_index, 5, 'right' FROM `custom_fields` WHERE `name` = 'SupportingDocumentation'

            UNION 
            SELECT id, tab_index, 0, 'left' FROM `custom_fields` WHERE `name` = 'MitigationDate'
            UNION 
            SELECT id, tab_index, 1, 'left' FROM `custom_fields` WHERE `name` = 'MitigationPlanning'
            UNION 
            SELECT id, tab_index, 2, 'left' FROM `custom_fields` WHERE `name` = 'PlanningStrategy'
            UNION 
            SELECT id, tab_index, 3, 'left' FROM `custom_fields` WHERE `name` = 'MitigationEffort'
            UNION 
            SELECT id, tab_index, 4, 'left' FROM `custom_fields` WHERE `name` = 'MitigationCost'
            UNION 
            SELECT id, tab_index, 5, 'left' FROM `custom_fields` WHERE `name` = 'MitigationOwner'
            UNION 
            SELECT id, tab_index, 6, 'left' FROM `custom_fields` WHERE `name` = 'MitigationTeam'
            UNION 
            SELECT id, tab_index, 7, 'left' FROM `custom_fields` WHERE `name` = 'MitigationPercent'
            UNION 
            SELECT id, tab_index, 8, 'left' FROM `custom_fields` WHERE `name` = 'AcceptMitigation'
            UNION 
            SELECT id, tab_index, 9, 'left' FROM `custom_fields` WHERE `name` = 'MitigationControls'

            UNION 
            SELECT id, tab_index, 0, 'right' FROM `custom_fields` WHERE `name` = 'CurrentSolution'
            UNION 
            SELECT id, tab_index, 1, 'right' FROM `custom_fields` WHERE `name` = 'SecurityRequirements'
            UNION 
            SELECT id, tab_index, 2, 'right' FROM `custom_fields` WHERE `name` = 'SecurityRecommendations'
            UNION 
            SELECT id, tab_index, 3, 'right' FROM `custom_fields` WHERE `name` = 'MitigationSupportingDocumentation'

            UNION 
            SELECT id, tab_index, 0, 'bottom' FROM `custom_fields` WHERE `name` = 'MitigationControlsList'
            
            UNION 
            SELECT id, tab_index, 0, 'left' FROM `custom_fields` WHERE `name` = 'ReviewDate'
            UNION 
            SELECT id, tab_index, 1, 'left' FROM `custom_fields` WHERE `name` = 'Reviewer'
            UNION 
            SELECT id, tab_index, 2, 'left' FROM `custom_fields` WHERE `name` = 'Review'
            UNION 
            SELECT id, tab_index, 3, 'left' FROM `custom_fields` WHERE `name` = 'NextStep'
            UNION 
            SELECT id, tab_index, 4, 'left' FROM `custom_fields` WHERE `name` = 'NextReviewDate'
            UNION 
            SELECT id, tab_index, 5, 'left' FROM `custom_fields` WHERE `name` = 'Comment'
            
            UNION 
            SELECT id, tab_index, 0, 'right' FROM `custom_fields` WHERE `name` = 'SetNextReviewDate';
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/*****************************************************
 * FUNCTION: UPGRADE CUSTOMIZATION EXTRA 20180531001 *
 *****************************************************/
function upgrade_customization_extra_20180531001()
{
    // Connect to the database
    $db = db_open();
    
    // Add review_id field to custom_data table
    if (!field_exists_in_table('review_id', 'custom_data')) {
        $stmt = $db->prepare("ALTER TABLE `custom_data` ADD `review_id` INT NOT NULL DEFAULT '0' AFTER `risk_id` ");
        $stmt->execute();
    }
    // Disconnect from the database
    db_close($db);
}

/*****************************************************
 * FUNCTION: UPGRADE CUSTOMIZATION EXTRA 20190125001 *
 *****************************************************/
function upgrade_customization_extra_20190125001()
{
    // Connect to the database
    $db = db_open();

    // Add fgroup to custom_fields table
    if (!field_exists_in_table('fgroup', 'custom_fields')) {
        $stmt = $db->prepare("ALTER TABLE `custom_fields` ADD `fgroup` VARCHAR(10) NOT NULL DEFAULT 'risk' AFTER `id`; ");
        $stmt->execute();
    }

    // Rename `custom_data` table name to `custom_risk_data`
    if (table_exists('custom_data') && !table_exists('custom_risk_data')) {
        $stmt = $db->prepare("RENAME TABLE `custom_data` TO `custom_risk_data`;");
        $stmt->execute();
    }

    // Create custom_asset_data table
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `custom_asset_data` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `asset_id` int(11) NOT NULL,
          `field_id` int(11) NOT NULL,
          `value` text,
          PRIMARY KEY (`id`),
          KEY `asset_id` (`asset_id`),
          KEY `field_id` (`field_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;      
    ");
    $stmt->execute();

    // Remove Unique Key from custom_fields table
    $stmt = $db->prepare("
        ALTER TABLE custom_fields DROP INDEX name;
    ");
    $stmt->execute();
    
    // Add Unique key by fgroup and name fields to custom_fields table
    $stmt = $db->prepare("
        ALTER TABLE `custom_fields` ADD UNIQUE KEY `fgroupname` (`fgroup`,`name`);
    ");
    $stmt->execute();
    
    // Add default main asset fields to custom_fields table
    $stmt = $db->prepare("
        INSERT INTO `custom_fields` (`fgroup`, `name`, `type`, `is_basic`, `tab_index`) VALUES
             ('asset', 'AssetName', '', '1', '1'),
             ('asset', 'IPAddress', '', '1', '1'),
             ('asset', 'AssetValuation', '', '1', '1'),
             ('asset', 'SiteLocation', '', '1', '1'),
             ('asset', 'Team', '', '1', '1'),
             ('asset', 'AssetDetails', '', '1', '1')
        ;
    ");
    $stmt->execute();

    // Set default main asset fields for template
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
        ;
    ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/*****************************************************
 * FUNCTION: UPGRADE CUSTOMIZATION EXTRA 20190213001 *
 *****************************************************/
function upgrade_customization_extra_20190213001()
{
    // Connect to the database
    $db = db_open();

    // Add tags field to custom_fields table
    $stmt = $db->prepare("
        INSERT INTO `custom_fields` (`fgroup`, `name`, `type`, `is_basic`, `tab_index`) VALUES
             ('risk', 'Tags', '', '1', '1'),
             ('asset', 'Tags', '', '1', '1')
        ON DUPLICATE KEY UPDATE
            `fgroup` = VALUES(`fgroup`),
            `type` = VALUES(`type`),
            `is_basic` = VALUES(`is_basic`),
            `tab_index` = VALUES(`tab_index`);
    ");
    $stmt->execute();

    // Set default main asset fields for template
    $stmt = $db->prepare("
        INSERT INTO `custom_template` (`custom_field_id` ,`tab_index` ,`ordering` ,`panel_name`) 
            SELECT id, tab_index, 0, 'bottom' FROM `custom_fields` WHERE `name` = 'Tags' AND `fgroup` = 'risk'
            UNION
            SELECT id, tab_index, 6, 'left' FROM `custom_fields` WHERE `name` = 'Tags' AND `fgroup` = 'asset'
        ;
    ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/*****************************************************
 * FUNCTION: UPGRADE CUSTOMIZATION EXTRA 20190704001 *
 *****************************************************/
function upgrade_customization_extra_20190704001()
{
    // Connect to the database
    $db = db_open();

    $stmt = $db->prepare("ALTER TABLE `custom_fields` ADD `required` tinyint(4) NOT NULL DEFAULT '0'; ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/*****************************************************
 * FUNCTION: UPGRADE CUSTOMIZATION EXTRA 20190802001 *
 *****************************************************/
function upgrade_customization_extra_20190802001()
{
    // Connect to the database
    $db = db_open();

    $stmt = $db->prepare("ALTER TABLE `custom_fields` ADD `encryption` tinyint(4) NOT NULL DEFAULT '0'; ");
    $stmt->execute();
    
    if(encryption_extra())
    {
        require_once(realpath(__DIR__ . '/../encryption/index.php'));
        
        /***************** Start Custom Risk Data ******************/
        // Create the new encrypted custom_risk_data table
        $stmt = $db->prepare("CREATE TABLE custom_risk_data_enc LIKE custom_risk_data; INSERT custom_risk_data_enc SELECT * FROM custom_risk_data;");
        $stmt->execute();

        // Change the text fields to blobs to store encrypted text
        $stmt = $db->prepare("ALTER TABLE `custom_risk_data_enc` CHANGE `value` `value` BLOB;");
        $stmt->execute();

        // Move the encrypted custom_risk_data table in place of the unencrypted one
        $stmt = $db->prepare("DROP TABLE custom_risk_data; CREATE TABLE custom_risk_data LIKE custom_risk_data_enc; INSERT custom_risk_data SELECT * FROM custom_risk_data_enc; DROP TABLE custom_risk_data_enc;");
        $stmt->execute();
        
        // Clear buffer sql    
        $stmt = $db->prepare("SELECT 'clear' ");
        $stmt->execute();
        $stmt->fetchAll();

        // Add settings to show tables were encrypted
        add_encrypted_field("enc_custom_risk_data_value", "custom_risk_data", "value", "openssl");
        /***********************************************************/
        
        /***************** Start Custom Asset Data *****************/
        // Create the new encrypted custom_asset_data table
        $stmt = $db->prepare("CREATE TABLE custom_asset_data_enc LIKE custom_asset_data; INSERT custom_asset_data_enc SELECT * FROM custom_asset_data;");
        $stmt->execute();

        // Change the text fields to blobs to store encrypted text
        $stmt = $db->prepare("ALTER TABLE `custom_asset_data_enc` CHANGE `value` `value` BLOB;");
        $stmt->execute();
        
        // Move the encrypted custom_asset_data table in place of the unencrypted one
        $stmt = $db->prepare("DROP TABLE custom_asset_data; CREATE TABLE custom_asset_data LIKE custom_asset_data_enc; INSERT custom_asset_data SELECT * FROM custom_asset_data_enc; DROP TABLE custom_asset_data_enc;");
        $stmt->execute();
        
        // Clear buffer sql    
        $stmt = $db->prepare("SELECT 'clear' ");
        $stmt->execute();
        $stmt->fetchAll();

        // Add settings to show tables were encrypted
        add_encrypted_field("enc_custom_asset_data_value", "custom_asset_data", "value", "openssl");
        /***********************************************************/
    }
    
    // Disconnect from the database
    db_close($db);
}

/*****************************************************
 * FUNCTION: UPGRADE CUSTOMIZATION EXTRA 20190810001 *
 *****************************************************/
function upgrade_customization_extra_20190810001()
{
    // Open the database connection
    $db = db_open();

    // Delete all custom_risk_data without having non-exist risk ID
    $stmt = $db->prepare("DELETE t1 FROM `custom_risk_data` t1 LEFT JOIN `risks` t2 ON t1.risk_id=t2.id WHERE t2.id IS NULL;");
    $return = $stmt->execute();

    // Delete all custom_asset_data without having non-exist asset ID
    $stmt = $db->prepare("DELETE t1 FROM `custom_asset_data` t1 LEFT JOIN `assets` t2 ON t1.asset_id=t2.id WHERE t2.id IS NULL;");
    $return = $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*****************************************************
 * FUNCTION: UPGRADE CUSTOMIZATION EXTRA 20190822001 *
 *****************************************************/
function upgrade_customization_extra_20190822001()
{
    // Open the database connection
    $db = db_open();


    // Close the database connection
    db_close($db);
}

/*****************************************************
 * FUNCTION: UPGRADE CUSTOMIZATION EXTRA 20191027001 *
 *****************************************************/
function upgrade_customization_extra_20191027001() {
    if (jira_extra()) {
        // Include the jira extra
        require_once(realpath(__DIR__ . '/../jira/index.php'));
        // Add the jira issue key field
        add_jira_issue_key_field_to_customization();
    }
}

?>

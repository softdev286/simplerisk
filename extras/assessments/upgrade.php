<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

global $assessment_updates;

$assessment_updates = array(
    'upgrade_assessment_extra_20170925001',
    'upgrade_assessment_extra_20171005001',
    'upgrade_assessment_extra_20171016001',
    'upgrade_assessment_extra_20171023001',
    'upgrade_assessment_extra_20171120001',
    'upgrade_assessment_extra_20171127001',
    'upgrade_assessment_extra_20171205001',
    'upgrade_assessment_extra_20171213001',
    'upgrade_assessment_extra_20171218001',
    'upgrade_assessment_extra_20180116001',
    'upgrade_assessment_extra_20180120001',
    'upgrade_assessment_extra_20180212001',
    'upgrade_assessment_extra_20180225001',
    'upgrade_assessment_extra_20180310001',
    'upgrade_assessment_extra_20180321001',
    'upgrade_assessment_extra_20180625001',
    'upgrade_assessment_extra_20180713001',
    'upgrade_assessment_extra_20180716001',
    'upgrade_assessment_extra_20181021001',
    'upgrade_assessment_extra_20181026001',
    'upgrade_assessment_extra_20181128001',
    'upgrade_assessment_extra_20181227001',
    'upgrade_assessment_extra_20190125001',
    'upgrade_assessment_extra_20190325001',
    'upgrade_assessment_extra_20190409001',
    'upgrade_assessment_extra_20190508001',
    'upgrade_assessment_extra_20190806001',
    'upgrade_assessment_extra_20190812001',
    'upgrade_assessment_extra_20190901001',
    'upgrade_assessment_extra_20190912001',
    'upgrade_assessment_extra_20191107001',
);

/***********************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA DATABASE *
 ***********************************************/
function upgrade_assessment_extra_database()
{
    global $assessment_updates;

    $version_name = 'assessment_extra_version';

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
    if (array_key_exists($db_version, $assessment_updates))
    {
        // Get the function to upgrade to the next version
        $function = $assessment_updates[$db_version];

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
            upgrade_assessment_extra_database();
        }
    }
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20170925001 *
 **************************************************/
function upgrade_assessment_extra_20170925001()
{
    // Connect to the database
    $db = db_open();

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171005001 *
 **************************************************/
function upgrade_assessment_extra_20171005001()
{
    // Connect to the database
    $db = db_open();
    
    // Add default ASSESSMENT_ASSET_SHOW_AVAILABLE setting.
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'ASSESSMENT_ASSET_SHOW_AVAILABLE', `value` = '0'");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171016001 *
 **************************************************/
function upgrade_assessment_extra_20171016001()
{
    // Connect to the database
    $db = db_open();
    
    // Create Assessment Contacts table.
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `assessment_contacts` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `company` varchar(255) DEFAULT NULL,
          `name` varchar(255) DEFAULT NULL,
          `email` varchar(255) DEFAULT NULL,
          `phone` varchar(255) DEFAULT NULL,
          `password` varchar(255) DEFAULT NULL,
          `manager` int(11) DEFAULT NULL,
          `details` TEXT DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `id` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;    
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171023001 *
 **************************************************/
function upgrade_assessment_extra_20171023001()
{
    // Connect to the database
    $db = db_open();
    
    // Create Assessment questionnaire tables.
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `questionnaires` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `questionnaire_answers` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `question_id` int(11) NOT NULL,
          `answer` varchar(500) NOT NULL,
          `ordering` int(11) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `questionnaire_id_template` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `questionnaire_id` int(11) NOT NULL,
          `template_id` int(11) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `questionnaire_questions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `question` varchar(1000) NOT NULL,
          `questionnaire_scoring_id` int(11) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `questionnaire_templates` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `questionnaire_template_question` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `questionnaire_template_id` int(11) NOT NULL,
          `questionnaire_question_id` int(11) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `questionnaire_scoring` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `scoring_method` int(11) NOT NULL,
          `calculated_risk` float NOT NULL,
          `CLASSIC_likelihood` float NOT NULL DEFAULT '5',
          `CLASSIC_impact` float NOT NULL DEFAULT '5',
          `CVSS_AccessVector` varchar(3) NOT NULL DEFAULT 'N',
          `CVSS_AccessComplexity` varchar(3) NOT NULL DEFAULT 'L',
          `CVSS_Authentication` varchar(3) NOT NULL DEFAULT 'N',
          `CVSS_ConfImpact` varchar(3) NOT NULL DEFAULT 'C',
          `CVSS_IntegImpact` varchar(3) NOT NULL DEFAULT 'C',
          `CVSS_AvailImpact` varchar(3) NOT NULL DEFAULT 'C',
          `CVSS_Exploitability` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_RemediationLevel` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_ReportConfidence` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_CollateralDamagePotential` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_TargetDistribution` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_ConfidentialityRequirement` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_IntegrityRequirement` varchar(3) NOT NULL DEFAULT 'ND',
          `CVSS_AvailabilityRequirement` varchar(3) NOT NULL DEFAULT 'ND',
          `DREAD_DamagePotential` int(11) DEFAULT '10',
          `DREAD_Reproducibility` int(11) DEFAULT '10',
          `DREAD_Exploitability` int(11) DEFAULT '10',
          `DREAD_AffectedUsers` int(11) DEFAULT '10',
          `DREAD_Discoverability` int(11) DEFAULT '10',
          `OWASP_SkillLevel` int(11) DEFAULT '10',
          `OWASP_Motive` int(11) DEFAULT '10',
          `OWASP_Opportunity` int(11) DEFAULT '10',
          `OWASP_Size` int(11) DEFAULT '10',
          `OWASP_EaseOfDiscovery` int(11) DEFAULT '10',
          `OWASP_EaseOfExploit` int(11) DEFAULT '10',
          `OWASP_Awareness` int(11) DEFAULT '10',
          `OWASP_IntrusionDetection` int(11) DEFAULT '10',
          `OWASP_LossOfConfidentiality` int(11) DEFAULT '10',
          `OWASP_LossOfIntegrity` int(11) DEFAULT '10',
          `OWASP_LossOfAvailability` int(11) DEFAULT '10',
          `OWASP_LossOfAccountability` int(11) DEFAULT '10',
          `OWASP_FinancialDamage` int(11) DEFAULT '10',
          `OWASP_ReputationDamage` int(11) DEFAULT '10',
          `OWASP_NonCompliance` int(11) DEFAULT '10',
          `OWASP_PrivacyViolation` int(11) DEFAULT '10',
          `Custom` float DEFAULT '10',
          PRIMARY KEY (`id`),
          UNIQUE KEY `id` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;        
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171120001 *
 **************************************************/
function upgrade_assessment_extra_20171120001()
{
    // Connect to the database
    $db = db_open();
    
    // Add a contact_id field to questionnaire and template relationship table
    if (!field_exists_in_table('contact_id', 'questionnaire_id_template')) {
        $stmt = $db->prepare("
            ALTER TABLE `questionnaire_id_template` ADD `contact_id` INT NOT NULL ;         
        ");
        $stmt->execute();
    }
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171127001 *
 **************************************************/
function upgrade_assessment_extra_20171127001()
{
    // Connect to the database
    $db = db_open();
    
    // Create a questionnaire tracking table
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `questionnaire_tracking` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `questionnaire_id` int(11) NOT NULL,
          `contact_id` int(11) NOT NULL,
          `token` varchar(100) NOT NULL,
          `progress` int(11) NOT NULL DEFAULT '0',
          `status` int(11) NOT NULL DEFAULT '0',
          `sent_at` datetime NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
    ");
    $stmt->execute();
    
    // Add a salt field to assessment_contacts table
    if (!field_exists_in_table('salt', 'assessment_contacts')) {
        $stmt = $db->prepare("
            ALTER TABLE `assessment_contacts` ADD `salt` VARCHAR( 20 ) AFTER `phone` ;         
        ");
        $stmt->execute();
    }
    
    // Change password field type varchar to binary
    $stmt = $db->prepare("
        ALTER TABLE `assessment_contacts` CHANGE `password` `password` BINARY( 60 ) ; UPDATE `simplerisk`.`assessment_contacts` SET `password` = NULL;
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171205001 *
 **************************************************/
function upgrade_assessment_extra_20171205001()
{
    // Connect to the database
    $db = db_open();
    
    // Create a questionnaire response table
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `questionnaire_responses` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `questionnaire_tracking_id` int(11) NOT NULL,
          `template_id` int(11) NOT NULL,
          `question_id` int(11) NOT NULL,
          `additional_information` text,
          `answer` varchar(50) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
    ");
    $stmt->execute();
    
    // Change the progress filed name in questionnaire tracking table
    $stmt = $db->prepare("
        ALTER TABLE `questionnaire_tracking` CHANGE `progress` `percent` INT( 11 ) NOT NULL DEFAULT '0';
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171213001 *
 **************************************************/
function upgrade_assessment_extra_20171213001()
{
    // Connect to the database
    $db = db_open();
    
    // Create a questionnaire response table
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `questionnaire_files` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `tracking_id` int(11) DEFAULT '0',
          `name` varchar(100) NOT NULL,
          `unique_name` varchar(30) NOT NULL,
          `type` varchar(30) NOT NULL,
          `size` int(11) NOT NULL,
          `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `content` longblob NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20171218001 *
 **************************************************/
function upgrade_assessment_extra_20171218001()
{
    // Connect to the database
    $db = db_open();
    
    // Create a questionnaire result comments table
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `questionnaire_result_comments` (
          `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `tracking_id` int(11) NOT NULL,
          `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `user` int(11) NOT NULL,
          `comment` mediumtext NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8; 
    ");
    $stmt->execute();
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20180116001 *
 **************************************************/
function upgrade_assessment_extra_20180116001()
{
    // Connect to the database
    $db = db_open();
    
    // Delete scoring ID field from questionnaire_questions table
    $stmt = $db->prepare("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND TABLE_NAME = 'questionnaire_questions' AND COLUMN_NAME = 'questionnaire_scoring_id'; ");
    $stmt->execute();
    $scoring_field = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If questionnaire_scoring_id exits in questionnaire_questions table, drop the field.
    if($scoring_field)
    {
        // Delete scoring ID field from questionnaire_questions table
        $stmt = $db->prepare("ALTER TABLE `questionnaire_questions` DROP `questionnaire_scoring_id`; ");
        $stmt->execute();
    }
    
    // Add scoring ID field to questionnaire_answers table
    if (!field_exists_in_table('questionnaire_scoring_id', 'questionnaire_answers')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_answers` ADD `questionnaire_scoring_id` INT;");
        $stmt->execute();
    }
    if (!field_exists_in_table('submit_risk', 'questionnaire_answers')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_answers` ADD `submit_risk` TINYINT NOT NULL;");
        $stmt->execute();
    }
    if (!field_exists_in_table('risk_subject', 'questionnaire_answers')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_answers` ADD `risk_subject` VARCHAR( 300 ) NOT NULL;");
        $stmt->execute();
    }
    if (!field_exists_in_table('risk_owner', 'questionnaire_answers')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_answers` ADD `risk_owner` INT NOT NULL;");
        $stmt->execute();
    }
    if (!field_exists_in_table('assets', 'questionnaire_answers')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_answers` ADD `assets` VARCHAR( 200 ) NOT NULL;");
        $stmt->execute();
    }
    
    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20180116001 *
 **************************************************/
function upgrade_assessment_extra_20180120001()
{
    // Connect to the database
    $db = db_open();
    
    // Add has_file field to questions table
    if (!field_exists_in_table('has_file', 'questionnaire_questions')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_questions` ADD `has_file` TINYINT DEFAULT '0'; ");
        $stmt->execute();
    }
    
    // Add template_id and question_id fields to questionnaire_files table
    if (!field_exists_in_table('template_id', 'questionnaire_files')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_files` ADD `template_id` INT NOT NULL DEFAULT '0' AFTER `tracking_id`;");
        $stmt->execute();
    }
    if (!field_exists_in_table('question_id', 'questionnaire_files')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_files` ADD `question_id` INT NOT NULL DEFAULT '0' AFTER `template_id`;");
        $stmt->execute();
    }

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20180212001 *
 **************************************************/
function upgrade_assessment_extra_20180212001()
{
    if (!field_exists_in_table('asset_name', 'questionnaire_tracking')) {
        // Connect to the database
        $db = db_open();

        // Add asset_name field to questionnaire_tracking table
        $stmt = $db->prepare("ALTER TABLE `questionnaire_tracking` ADD `asset_name` VARCHAR( 200 ) NOT NULL DEFAULT '' AFTER `contact_id` ;");
        $stmt->execute();

        // Disconnect from the database
        db_close($db);
    }
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20180225001 *
 * - Copies currently available assessments into  *
 *   the new assessment questions and templates.  *
 **************************************************/
function upgrade_assessment_extra_20180225001()
{
	// Connect to the database
	$db = db_open();

    // Add ordering fields for questions order in a questionnaire template
    if (!field_exists_in_table('ordering', 'questionnaire_template_question')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_template_question` ADD `ordering` INT NOT NULL DEFAULT '0' ;");
        $stmt->execute();
    }

    // Add ordering fields for questions order in a questionnaire template
    $stmt = $db->prepare("ALTER TABLE `questionnaire_answers` CHANGE `risk_subject` `risk_subject` BLOB NOT NULL; ");
    $stmt->execute();

    // Get an array of all questions and answers
    $assessment_questions_and_answers = available_assessments_array();

    // Set incremental values to null
    $track_assessment_id = null;
    $track_question_id = null;
    $question_inserted_id = null;
    $template_inserted_id = null;

    // For each questionnaire - question - answer combination
    foreach ($assessment_questions_and_answers as $row)
    {
        $assessment_id = $row['assessment_id'];
        $assessment_name = $row['assessment_name'];
        $assessment_created = $row['assessment_created'];
        $assessment_question_id = $row['assessment_question_id'];
        $assessment_question_assessment_id = $row['assessment_question_assessment_id'];
        $assessment_question_question = $row['assessment_question_question'];
        $assessment_question_order = $row['assessment_question_order'];
        $assessment_answer_id = $row['assessment_answer_id'];
        $assessment_answer_assessment_id = $row['assessment_answer_assessment_id'];
        $assessment_answer_question_id = $row['assessment_answer_question_id'];
        $assessment_answer_answer = $row['assessment_answer_answer'];
        $assessment_answer_submit_risk = $row['assessment_answer_submit_risk'];
        $assessment_answer_risk_subject = $row['assessment_answer_risk_subject'];
        $assessment_answer_assessment_scoring_id = $row['assessment_answer_assessment_scoring_id'];
        $assessment_answer_risk_owner = $row['assessment_answer_risk_owner'];
        $assessment_answer_assets = $row['assessment_answer_assets'];
        $assessment_answer_order = $row['assessment_answer_order'];

        // If tis is a new assessment id
        if ($track_assessment_id != $assessment_id)
        {
            // Set the assessment id to the new one
            $track_assessment_id = $assessment_id;

            // Add the assessment to the questionnaire templates table
            $stmt = $db->prepare("INSERT INTO questionnaire_templates (`name`) VALUES (:name);");
            $stmt->bindParam(":name", $assessment_name, PDO::PARAM_STR);
            $stmt->execute();

            // Get the id of the new template inserted
            $template_inserted_id = $db->lastInsertId();
        }

        // If this is a new question id
        if ($track_question_id != $assessment_question_id)
        {
            // Set the question id to the new one
            $track_question_id = $assessment_question_id;

            // Add the question to the questionnaire questions table
            $stmt = $db->prepare("INSERT INTO questionnaire_questions (`question`) VALUES (:question);");
            $stmt->bindParam(":question", $assessment_question_question, PDO::PARAM_STR);
            $stmt->execute();

            // Get the id of the new question inserted
            $question_inserted_id = $db->lastInsertId();

            // Add the question to the questionnaire template question tample
            $stmt = $db->prepare("INSERT INTO questionnaire_template_question (`questionnaire_template_id`, `questionnaire_question_id`, `ordering`) VALUES (:questionnaire_template_id, :questionnaire_question_id, :ordering);");
            $stmt->bindParam(":questionnaire_template_id", $template_inserted_id, PDO::PARAM_INT);
            $stmt->bindParam(":questionnaire_question_id", $question_inserted_id, PDO::PARAM_INT);
            $stmt->bindParam(":ordering", $assessment_question_order, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        $stmt = $db->prepare("
            INSERT INTO
                `questionnaire_scoring`
            SELECT
                NULL,
                `scoring_method`,
                `calculated_risk`,
                `CLASSIC_likelihood`,
                `CLASSIC_impact`,
                `CVSS_AccessVector`,
                `CVSS_AccessComplexity`,
                `CVSS_Authentication`,
                `CVSS_ConfImpact`,
                `CVSS_IntegImpact`,
                `CVSS_AvailImpact`,
                `CVSS_Exploitability`,
                `CVSS_RemediationLevel`,
                `CVSS_ReportConfidence`,
                `CVSS_CollateralDamagePotential`,
                `CVSS_TargetDistribution`,
                `CVSS_ConfidentialityRequirement`,
                `CVSS_IntegrityRequirement`,
                `CVSS_AvailabilityRequirement`,
                `DREAD_DamagePotential`,
                `DREAD_Reproducibility`,
                `DREAD_Exploitability`,
                `DREAD_AffectedUsers`,
                `DREAD_Discoverability`,
                `OWASP_SkillLevel`,
                `OWASP_Motive`,
                `OWASP_Opportunity`,
                `OWASP_Size`,
                `OWASP_EaseOfDiscovery`,
                `OWASP_EaseOfExploit`,
                `OWASP_Awareness`,
                `OWASP_IntrusionDetection`,
                `OWASP_LossOfConfidentiality`,
                `OWASP_LossOfIntegrity`,
                `OWASP_LossOfAvailability`,
                `OWASP_LossOfAccountability`,
                `OWASP_FinancialDamage`,
                `OWASP_ReputationDamage`,
                `OWASP_NonCompliance`,
                `OWASP_PrivacyViolation`,
                `Custom`" . (field_exists_in_table('Contributing_Likelihood', 'assessment_scoring') && field_exists_in_table('Contributing_Likelihood', 'questionnaire_scoring')? ",Contributing_Likelihood" : "") . "
            FROM
                `assessment_scoring`
            WHERE
                id=:assessment_scoring_id;
        ");
        $stmt->bindParam(":assessment_scoring_id", $assessment_answer_assessment_scoring_id, PDO::PARAM_INT);
        $stmt->execute();
        $questionnaire_scoring_id = $db->lastInsertId();


        // Add the answer to the questionnaire answers table
        $stmt = $db->prepare("INSERT INTO
                questionnaire_answers (`question_id`, `answer`, `ordering`, `questionnaire_scoring_id`, `submit_risk`, `risk_subject`, `risk_owner`, `assets`)
            VALUES (:question_id, :answer, :ordering, :questionnaire_scoring_id, :submit_risk, :risk_subject, :risk_owner, :assets);
        ");
        $stmt->bindParam(":question_id", $question_inserted_id, PDO::PARAM_INT);
        $stmt->bindParam(":answer", $assessment_answer_answer, PDO::PARAM_STR);
        $stmt->bindParam(":ordering", $assessment_answer_order, PDO::PARAM_INT);
        $stmt->bindParam(":questionnaire_scoring_id", $questionnaire_scoring_id, PDO::PARAM_INT);
        $stmt->bindParam(":submit_risk", $assessment_answer_submit_risk, PDO::PARAM_INT);
        $stmt->bindParam(":risk_subject", $assessment_answer_risk_subject, PDO::PARAM_STR);
        $stmt->bindParam(":risk_owner", $assessment_answer_risk_owner, PDO::PARAM_INT);
        $stmt->bindParam(":assets", $assessment_answer_assets, PDO::PARAM_STR);

        $stmt->execute();
    }

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20180310001 *
 **************************************************/
function upgrade_assessment_extra_20180310001()
{
    global $lang, $escaper;

    // Connect to the database
    $db = db_open();

    // Add a sub_questions field to questionnaire_answers table
    if (!field_exists_in_table('sub_questions', 'questionnaire_answers')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_answers` ADD `sub_questions` VARCHAR( 3000 );");
        $stmt->execute();
    }

    // Add a parent_question_id field to questionnaire_files table
    if (!field_exists_in_table('parent_question_id', 'questionnaire_files')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_files` ADD `parent_question_id` INT NOT NULL DEFAULT '0' AFTER `template_id`;");
        $stmt->execute();
    }

    // Add a parent_question_id field to questionnaire_responses table
    if (!field_exists_in_table('parent_question_id', 'questionnaire_responses')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_responses` ADD `parent_question_id` INT NOT NULL DEFAULT '0' AFTER `question_id`;");
        $stmt->execute();
    }

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20180321001 *
 **************************************************/
function upgrade_assessment_extra_20180321001()
{
    // Connect to the database
    $db = db_open();

    // Remove password field from assessment contact
    $stmt = $db->prepare("ALTER TABLE `assessment_contacts` DROP `password`;");
    $stmt->execute();

    // Add details field to assessment contact
    if (!field_exists_in_table('details', 'assessment_contacts')) {
        $stmt = $db->prepare("ALTER TABLE `assessment_contacts` ADD `details` TEXT ;");
        $stmt->execute();
    }

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20180625001 *
 **************************************************/
function upgrade_assessment_extra_20180625001()
{
    // Connect to the database
    $db = db_open();

    // Update sent_at field type to timestamp
    $stmt = $db->prepare("ALTER TABLE `questionnaire_tracking` CHANGE `sent_at` `sent_at` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00'; ");
    $stmt->execute();
    
    // Add updated_at field
    if (!field_exists_in_table('updated_at', 'questionnaire_tracking')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_tracking` ADD `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP; ");
        $stmt->execute();
    }

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20180713001 *
 **************************************************/
function upgrade_assessment_extra_20180713001()
{
    // Connect to the database
    $db = db_open();

    // Update question 144 (disallow unauthorized outbound traffic) to have both Yes and No answers
    $stmt = $db->prepare("SELECT id FROM questionnaire_questions WHERE question='(1.3.4) Do you disallow unauthorized outbound traffic from the cardholder data environment to the internet?';");
    $stmt->execute();

    // Store the ID for the question
    $array = $stmt->fetchAll();

    // Update question answer to No
    $stmt = $db->prepare("UPDATE questionnaire_answers SET answer='No' WHERE question_id=:question_id AND submit_risk=1;");
    $stmt->bindParam(":question_id", $array[0]['id'], PDO::PARAM_INT);
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20180716001 *
 **************************************************/
function upgrade_assessment_extra_20180716001()
{
    // Connect to the database
    $db = db_open();

    // Update question 433 (Does the covered entity use or disclose PHI…) to have both Yes and No answers
//    echo "Updating question 433 (Does the covered entity use or disclose PHI for the purpose of research, conducts research, provides psychotherapy services, and uses compound authorizations?)<br />\n";
    $stmt = $db->prepare("SELECT id FROM assessment_questions WHERE question='§164.508(b) (3) Does the covered entity use or disclose PHI for the purpose of research, conducts research, provides psychotherapy services, and uses compound authorizations?';");
    $stmt->execute();

    // Store the ID for the question
    $array = $stmt->fetchAll();

    // Update the question answer to No
    $stmt = $db->prepare("UPDATE assessment_answers SET answer='No' WHERE question_id=:question_id AND submit_risk=1;");
    $stmt->bindParam(":question_id", $array[0]['id'], PDO::PARAM_INT);
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20181021001 *
 **************************************************/
function upgrade_assessment_extra_20181021001()
{
    // Connect to the database
    $db = db_open();

    // Add mapped_controls field to questionnaire_questions table
    if (!field_exists_in_table('mapped_controls', 'questionnaire_questions')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_questions` ADD `mapped_controls` TEXT AFTER `question`; ");
        $stmt->execute();
    }

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20181026001 *
 **************************************************/
function upgrade_assessment_extra_20181026001()
{
    // Connect to the database
    $db = db_open();

    // Set default null for submit_risk, risk_subject, risk_owner, assets in questionnaire_answers table
    $stmt = $db->prepare("ALTER TABLE `questionnaire_answers` CHANGE `submit_risk` `submit_risk` TINYINT(4) NULL, CHANGE `risk_subject` `risk_subject` BLOB NULL, CHANGE `risk_owner` `risk_owner` INT(11) NULL, CHANGE `assets` `assets` VARCHAR(200) NULL; ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20181128001 *
 **************************************************/
function upgrade_assessment_extra_20181128001()
{
    // Connect to the database
    $db = db_open();

    // Add a field Contributing_Likelihood to questionnaire_scoring table
    if (!field_exists_in_table('Contributing_Likelihood', 'questionnaire_scoring')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_scoring` ADD `Contributing_Likelihood` INT DEFAULT '0'; ");
        $stmt->execute();
    }

    // Create questionnaire_scoring_contributing_impacts table
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS `questionnaire_scoring_contributing_impacts` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `questionnaire_scoring_id` int(11) NOT NULL,
          `contributing_risk_id` int(11) NOT NULL,
          `impact` int(11) NOT NULL,
          PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20181227001 *
 **************************************************/
function upgrade_assessment_extra_20181227001()
{
    // Connect to the database
    $db = db_open();

    // Add a new field, `compliance_audit` to questionnaire_questions table
    if (!field_exists_in_table('compliance_audit', 'questionnaire_questions')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_questions` ADD `compliance_audit` TINYINT DEFAULT '0';");
        $stmt->execute();
    }

    // Add a new field, `fail_control` to questionnaire_answers table
    if (!field_exists_in_table('fail_control', 'questionnaire_answers')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_answers` ADD `fail_control` TINYINT DEFAULT '0';");
        $stmt->execute();
    }

    // Add a new field to questionnaires table
    if (!field_exists_in_table('user_instructions', 'questionnaires')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaires` ADD `user_instructions` longtext NOT NULL after `name`;");
        $stmt->execute();
    }

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20190125001 *
 **************************************************/
function upgrade_assessment_extra_20190125001()
{
    // Connect to the database
    $db = db_open();

    // Removing ON UPDATE CURRENT_TIMESTAMP from questionnaire_result_comments table.
    $stmt = $db->prepare("ALTER TABLE `questionnaire_result_comments` CHANGE `date` `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;");
    $stmt->execute();
    // Removing ON UPDATE CURRENT_TIMESTAMP from questionnaire_tracking table.
    $stmt = $db->prepare("ALTER TABLE `questionnaire_tracking` CHANGE `updated_at` `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20190325001 *
 **************************************************/
function upgrade_assessment_extra_20190325001()
{
    if(encryption_extra()){
        // Load the extra
        require_once(realpath(__DIR__ . '/../encryption/index.php'));
        
        // Open the database connection
        $db = db_open();

        // Create the new encrypted questionnaire result comments table
        $stmt = $db->prepare("CREATE TABLE questionnaire_result_comments_enc LIKE questionnaire_result_comments; INSERT questionnaire_result_comments_enc SELECT * FROM questionnaire_result_comments");
        $stmt->execute();

        // Change the text fields to blobs to store encrypted text
        $stmt = $db->prepare("ALTER TABLE `questionnaire_result_comments_enc` CHANGE `comment` `comment` BLOB;");
        $stmt->execute();

        // Get all of the questionnaire_result_comments
        $stmt = $db->prepare("SELECT * FROM questionnaire_result_comments");
        $stmt->execute();
        $questionnaire_result_comments = $stmt->fetchAll();

        $enc_commnet_arr = is_field_encrypted(null, "questionnaire_result_comments", "comment");
        
        // For each questionnaire response 
        foreach ($questionnaire_result_comments as $questionnaire_result_comment)
        {
            if($enc_commnet_arr['encrypted'])
            {
                $encrypted_comment = $questionnaire_result_comment['comment'];
            }
            else
            {
                $encrypted_comment = encrypt($_SESSION['encrypted_pass'], $questionnaire_result_comment['comment']);
            }
            
            $stmt = $db->prepare("UPDATE `questionnaire_result_comments_enc` SET `comment` = :comment WHERE id = :id");
            $stmt->bindParam(":comment", $encrypted_comment, PDO::PARAM_STR);
            $stmt->bindParam(":id", $questionnaire_result_comment['id'], PDO::PARAM_INT);
            $stmt->execute();
        }
    
        // Move the encrypted questionnaire respponses table in place of the unencrypted one
        $stmt = $db->prepare("DROP TABLE questionnaire_result_comments; CREATE TABLE questionnaire_result_comments LIKE questionnaire_result_comments_enc; INSERT questionnaire_result_comments SELECT * FROM questionnaire_result_comments_enc; DROP TABLE questionnaire_result_comments_enc;");
        $stmt->execute();
    
        // Clear buffer sql    
        $stmt = $db->prepare("SELECT 'clear' ");
        $stmt->execute();
        $stmt->fetchAll();

        // Add settings to show tables were encrypted
        add_encrypted_field("enc_questionnaire_result_comments_comment", "questionnaire_result_comments", "comment", "openssl");

        // Close the database connection
        db_close($db);
    }
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20190409001 *
 **************************************************/
function upgrade_assessment_extra_20190409001()
{
    if(encryption_extra()){
        // Load the extra
        require_once(realpath(__DIR__ . '/../encryption/index.php'));
        
        // Open the database connection
        $db = db_open();

        // Create the new encrypted questionnaire responses table
        $stmt = $db->prepare("DROP TABLE  IF EXISTS questionnaire_responses_enc; CREATE TABLE questionnaire_responses_enc LIKE questionnaire_responses; INSERT questionnaire_responses_enc SELECT * FROM questionnaire_responses");
        $stmt->execute();

        // Change the text fields to blobs to store encrypted text
        $stmt = $db->prepare("ALTER TABLE `questionnaire_responses_enc` CHANGE `additional_information` `additional_information` BLOB, CHANGE `answer` `answer` BLOB;");
        $stmt->execute();

        // Get all of the questionnaire_responses
        $stmt = $db->prepare("SELECT * FROM questionnaire_responses");
        $stmt->execute();
        $questionnaire_responses = $stmt->fetchAll();
        
        $enc_additional_information_arr = is_field_encrypted(null, "questionnaire_responses", "additional_information");

        $enc_answer_arr = is_field_encrypted(null, "questionnaire_responses", "answer");

        // For each questionnaire response 
        foreach ($questionnaire_responses as $questionnaire_response)
        {
            if($enc_additional_information_arr['encrypted'] && check_base64_string($questionnaire_response['additional_information']))
            {
                $encrypt_additional_information = $questionnaire_response['additional_information'];
            }
            else
            {
                $encrypt_additional_information = encrypt($_SESSION['encrypted_pass'], $questionnaire_response['additional_information']);
            }
            if($enc_answer_arr['encrypted'] && check_base64_string($questionnaire_response['answer']))
            {
                $encrypt_answer = $questionnaire_response['answer'];
            }
            else
            {
                $encrypt_answer = encrypt($_SESSION['encrypted_pass'], $questionnaire_response['answer']);
            }
            
            $stmt = $db->prepare("UPDATE `questionnaire_responses_enc` SET `additional_information` = :additional_information, `answer` = :answer WHERE id = :id");
            $stmt->bindParam(":additional_information", $encrypt_additional_information, PDO::PARAM_STR);
            $stmt->bindParam(":answer", $encrypt_answer, PDO::PARAM_STR);
            $stmt->bindParam(":id", $questionnaire_response['id'], PDO::PARAM_INT);
            $stmt->execute();
        }
    
        // Move the encrypted questionnaire respponses table in place of the unencrypted one
        $stmt = $db->prepare("DROP TABLE questionnaire_responses; CREATE TABLE questionnaire_responses LIKE questionnaire_responses_enc; INSERT questionnaire_responses SELECT * FROM questionnaire_responses_enc; DROP TABLE questionnaire_responses_enc;");
        $stmt->execute();
    
        // Clear buffer sql    
        $stmt = $db->prepare("SELECT 'clear' ");
        $stmt->execute();
        $stmt->fetchAll();

        // Add settings to show tables were encrypted
        add_encrypted_field("enc_questionnaire_responses_additional_infromation", "questionnaire_responses", "additional_information", "openssl");
        add_encrypted_field("enc_questionnaire_responses_answer", "questionnaire_responses", "answer", "openssl");
    }
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20190508001 *
 **************************************************/
function upgrade_assessment_extra_20190508001() {

    // Connect to the database
    $db = db_open();

    if (!table_exists('questionnaire_answers_to_assets')) {
        // Creating the questionnaire_answers_to_assets table.
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `questionnaire_answers_to_assets` (
                `questionnaire_answer_id` INT(11) NOT NULL,
                `asset_id` INT(11) NOT NULL,
                CONSTRAINT `questionnaire_answer_asset_unique` UNIQUE (`questionnaire_answer_id`, `asset_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }

    if (!table_exists('questionnaire_answers_to_asset_groups')) {
        // Creating the questionnaire_answers_to_asset_groups table.
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS `questionnaire_answers_to_asset_groups` (
                `questionnaire_answer_id` INT(11) NOT NULL,
                `asset_group_id` INT(11) NOT NULL,
                CONSTRAINT `questionnaire_answer_asset_group_unique` UNIQUE (`questionnaire_answer_id`, `asset_group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $stmt->execute();
    }

    if (field_exists_in_table('assets', 'questionnaire_answers')
        && table_exists('questionnaire_answers_to_asset_groups')
        && table_exists('questionnaire_answers_to_assets')) {

        // Get any answers that have assets setup        
        $stmt = $db->prepare("SELECT id, assets FROM questionnaire_answers WHERE TRIM(assets) != '' AND assets IS NOT NULL;");
        $stmt->execute();
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($answers) {
            // Migrating Questionnaire Answers to to the new database structure.

            // Iterate through the answers
            foreach($answers as $answer) {

                $answer_id = $answer['id'];
                $asset_names = explode(',', $answer['assets']);

                // Iterate through the asset names
                foreach($asset_names as $asset_name) {

                    if (!$asset_name)
                        continue;

                    // Get the asset id if it exists
                    $asset_id = asset_exists($asset_name);

                    // If it doesn't yet
                    if (!$asset_id)
                        // Then create it
                        $asset_id = add_asset_by_name_with_forced_verification($asset_name, true);

                    if (!$asset_id)
                        continue;

                    // Add the new asset for this questionnaire answer
                    $stmt = $db->prepare("INSERT INTO `questionnaire_answers_to_assets` (`questionnaire_answer_id`, `asset_id`) VALUES (:questionnaire_answer_id, :asset_id)");
                    $stmt->bindParam(":questionnaire_answer_id", $answer_id, PDO::PARAM_INT);
                    $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
        }

        // Drop the assets column of the questionnaire_answers table
        $stmt = $db->prepare("ALTER TABLE `questionnaire_answers` DROP COLUMN `assets`;");
        $stmt->execute();
    }

    // Update `questionnaire_pending_risks` table's `asset` field to text type
    if (getTypeOfColumn('questionnaire_pending_risks', 'asset') == 'varchar') {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_pending_risks` CHANGE `asset` `affected_assets` TEXT;");
        $stmt->execute();
    }

    // Update `questionnaire_tracking` table's `asset_name` field to text type
    if (getTypeOfColumn('questionnaire_tracking', 'asset_name') == 'varchar') {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_tracking` CHANGE `asset_name` `affected_assets` TEXT;");
        $stmt->execute();
    }

    // Update `questionnaire_id_template` table's `contact_id` field to text type
    if (getTypeOfColumn('questionnaire_id_template', 'contact_id') == 'int') {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_id_template` CHANGE `contact_id` `contact_ids` TEXT;");
        $stmt->execute();
    }

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20190806001 *
 **************************************************/
function upgrade_assessment_extra_20190806001() {

    // Connect to the database
    $db = db_open();

    if (!field_exists_in_table('approved_at', 'questionnaire_tracking')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_tracking` ADD `approved_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' after `updated_at`;");
        $stmt->execute();
    }

    if (!field_exists_in_table('approver', 'questionnaire_tracking')) {
        $stmt = $db->prepare("ALTER TABLE `questionnaire_tracking` ADD `approver` int(11) NOT NULL after `approved_at`;");
        $stmt->execute();
    }

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20190812001 *
 **************************************************/
function upgrade_assessment_extra_20190812001() {

    // Connect to the database
    $db = db_open();

    $stmt = $db->prepare("ALTER TABLE `questionnaire_tracking` CHANGE `approver` `approver` INT(11) NOT NULL DEFAULT '0'; ");
    $stmt->execute();

    $stmt = $db->prepare("ALTER TABLE `questionnaire_tracking` ADD `pre_population` TINYINT NOT NULL DEFAULT '0'; ");
    $stmt->execute();

    $stmt = $db->prepare("ALTER TABLE `questionnaire_responses` ADD `updated_at` TIMESTAMP NOT NULL; ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20190901001 *
 **************************************************/
function upgrade_assessment_extra_20190901001() 
{
    // Connect to the database
    $db = db_open();

    $stmt = $db->prepare("ALTER TABLE `questionnaire_answers` ADD `tag_ids` VARCHAR(200) NOT NULL DEFAULT '';");
    $stmt->execute();

    $stmt = $db->prepare("ALTER TABLE `questionnaire_pending_risks` ADD `tag_ids` VARCHAR(200) NOT NULL DEFAULT '' AFTER `comment`; ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20190912001 *
 **************************************************/
function upgrade_assessment_extra_20190912001()
{
    // Connect to the database
    $db = db_open();

    $stmt = $db->prepare("ALTER TABLE `questionnaire_pending_risks` ADD `status` INT NOT NULL DEFAULT '0' COMMENT '0:Pending, 1:Added, 2:Rejected'; ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ASSESSMENT EXTRA 20191107001 *
 **************************************************/
function upgrade_assessment_extra_20191107001()
{
    // Connect to the database
    $db = db_open();

    $stmt = $db->prepare("ALTER TABLE `questionnaire_answers` CHANGE `risk_owner` `risk_owner` TINYINT NULL DEFAULT '0', CHANGE `fail_control` `fail_control` TINYINT NULL DEFAULT '0';");
    $stmt->execute();

    $stmt = $db->prepare("ALTER TABLE `questionnaire_responses`  ADD `submit_risk` TINYINT NULL DEFAULT '0' AFTER `answer`, ADD `fail_control` TINYINT NULL DEFAULT '0' AFTER `submit_risk`; ");
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}

?>

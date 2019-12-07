<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to       *
 * import and export CSV files containing risk data.                *
 ********************************************************************/

// Extra Version
define('IMPORTEXPORT_EXTRA_VERSION', '20191130-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/assets.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../../includes/governance.php'));
require_once(realpath(__DIR__ . '/includes/PHPOffice/autoload.php'));
require_once(realpath(__DIR__ . '/includes/tenable.php'));
require_once(realpath(__DIR__ . '/includes/nexpose.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_importexport_extra_database();

/****************************************
 * FUNCTION: ENABLE IMPORT EXPORT EXTRA *
 ****************************************/
function enable_import_export_extra()
{
    prevent_extra_double_submit("import_export", true);

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'import_export', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
    $stmt->execute();

    // Create a table for the file upload
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_tmp` (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, unique_name VARCHAR(30) NOT NULL, size INT NOT NULL, timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, content LONGBLOB NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Create a table for the mappings
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_mappings` (value INT NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, mapping BLOB, PRIMARY KEY (value)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    $mapping_json = get_simplerisk_combine_default_mapping_json();

    $mapping_serialize = serialize(json_decode($mapping_json, true));

    // Add the SimpleRisk mapping for the "Export Combined" import
    $stmt = $db->prepare("DELETE FROM `import_export_mappings` WHERE `name` = 'SimpleRisk Combined Import';");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO `import_export_mappings` (`name`, `mapping`) VALUES ('SimpleRisk Combined Import', '{$mapping_serialize}');");
    $stmt->execute();

    // Create a table for integration_assets
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_integration_assets` (`id` INT NOT NULL AUTO_INCREMENT, `integration_name` VARCHAR(100), `asset_id` INT(11), `uuid` VARCHAR(100), `has_agent` VARCHAR(100), `source_name` VARCHAR(100), `first_seen` VARCHAR(100), `last_seen` VARCHAR(100), `ipv4` BLOB, `fqdn` BLOB, `operating_system` BLOB, `netbios_name` BLOB, `agent_name` BLOB, `aws_ec2_name` BLOB, `mac_address` BLOB, PRIMARY KEY (`id`), UNIQUE (`uuid`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Audit log entry for Extra turned on
    $message = "Import/Export Extra was toggled on by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);
}

/*********************************************************
 * FUNCTION: GET SIMPLERISK COMBINE DEFAULT MAPPING JSON *
 *********************************************************/
function get_simplerisk_combine_default_mapping_json()
{
    $mapping_json = '{
        "col_0": "risks_id",
        "col_1": "risks_status",
        "col_2": "risks_subject",
        "col_3": "risks_reference_id",
        "col_4": "risks_regulation",
        "col_5": "risks_control_number",
        "col_6": "risks_location",
        "col_7": "risks_source",
        "col_8": "risks_category",
        "col_9": "risks_team",
        "col_10": "risks_additional_stakeholders",
        "col_11": "risks_technology",
        "col_12": "risks_owner",
        "col_13": "risks_manager",
        "col_14": "risks_assessment",
        "col_15": "risks_notes",
        "col_16": "risks_submission_date",
        "col_17": "risks_projects",
        "col_18": "risks_submitted_by",
        "col_19": "closed_date",
        "col_20": "risks_assets",
        "col_21": "riskscoring_scoring_method",
        "col_24": "riskscoring_CLASSIC_likelihood",
        "col_25": "riskscoring_CLASSIC_impact",
        "col_26": "riskscoring_CVSS_AccessVector",
        "col_27": "riskscoring_CVSS_AccessComplexity",
        "col_28": "riskscoring_CVSS_Authentication",
        "col_29": "riskscoring_CVSS_ConfImpact",
        "col_30": "riskscoring_CVSS_IntegImpact",
        "col_31": "riskscoring_CVSS_AvailImpact",
        "col_32": "riskscoring_CVSS_Exploitability",
        "col_33": "riskscoring_CVSS_RemediationLevel",
        "col_34": "riskscoring_CVSS_ReportConfidence",
        "col_35": "riskscoring_CVSS_CollateralDamagePotential",
        "col_36": "riskscoring_CVSS_TargetDistribution",
        "col_37": "riskscoring_CVSS_ConfidentialityRequirement",
        "col_38": "riskscoring_CVSS_IntegrityRequirement",
        "col_39": "riskscoring_CVSS_AvailabilityRequirement",
        "col_40": "riskscoring_DREAD_DamagePotential",
        "col_41": "riskscoring_DREAD_Reproducibility",
        "col_42": "riskscoring_DREAD_Exploitability",
        "col_43": "riskscoring_DREAD_AffectedUsers",
        "col_44": "riskscoring_DREAD_Discoverability",
        "col_45": "riskscoring_OWASP_SkillLevel",
        "col_46": "riskscoring_OWASP_Motive",
        "col_47": "riskscoring_OWASP_Opportunity",
        "col_48": "riskscoring_OWASP_Size",
        "col_49": "riskscoring_OWASP_EaseOfDiscovery",
        "col_50": "riskscoring_OWASP_EaseOfExploit",
        "col_51": "riskscoring_OWASP_Awareness",
        "col_52": "riskscoring_OWASP_IntrusionDetection",
        "col_53": "riskscoring_OWASP_LossOfConfidentiality",
        "col_54": "riskscoring_OWASP_LossOfIntegrity",
        "col_55": "riskscoring_OWASP_LossOfAvailability",
        "col_56": "riskscoring_OWASP_LossOfAccountability",
        "col_57": "riskscoring_OWASP_FinancialDamage",
        "col_58": "riskscoring_OWASP_ReputationDamage",
        "col_59": "riskscoring_OWASP_NonCompliance",
        "col_60": "riskscoring_OWASP_PrivacyViolation",
        "col_61": "riskscoring_Custom",
        "col_62": "riskscoring_Contributing_Likelihood",
        "col_63": "riskscoring_Contributing_Subjects_Impacts",
        "col_64": "mitigations_date",
        "col_65": "planning_strategy",
        "col_66": "planning_date",
        "col_67": "mitigations_effort",
        "col_68": "mitigations_cost",
        "col_69": "mitigations_owner",
        "col_70": "mitigations_team",
        "col_71": "current_solution",
        "col_72": "security_requirements",
        "col_73": "security_recommendations",
        "col_74": "mitigated_by",
        "col_75": "reviews_submission_date",
        "col_76": "reviews_review",
        "col_77": "reviews_reviewer",
        "col_78": "reviews_next_step",
        "col_79": "reviews_comments",
        "col_80": "reviews_next_review",
        "col_81": "risks_tags",
        "col_82": "mitigations_percent",
        "mapping_name": "SimpleRisk Combined Import"
    }';
    /*
        // Removed these from the mapping, but still keeping their "numbers"
        // so when the mapping is used these two columns are skipped
        "col_22": "riskscoring_calculated_risk",
        "col_23": "riskscoring_residual_risk",

    */
    return $mapping_json;
}

/****************************************
 * FUNCTION: DISABLE IMPORT EXPORTEXTRA *
 ****************************************/
function disable_import_export_extra()
{
    prevent_extra_double_submit("import_export", false);

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'import_export'");
    $stmt->execute();

    // Drop the table for the file upload
    $stmt = $db->prepare("DROP TABLE `import_export_tmp`;");
    $stmt->execute();

    // Drop the table for the mappings
    // $stmt = $db->prepare("DROP TABLE `import_export_mappings`;");
    // $stmt->execute();
    
    // Audit log entry for Extra turned off
    $message = "Import/Export Extra was toggled off by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);
}

/************************
 * FUNCTION: IMPORT CSV *
 ************************/
function import_csv($file)
{
    global $escaper, $lang;
    
    // Open the database connection
    $db = db_open();

    // Delete any existing import file
    $stmt = $db->prepare("DELETE FROM `import_export_tmp` WHERE name='import.csv';");
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Allowed file types
    $allowed_types = get_file_types();

    // If a file was submitted and the name isn't blank
    if (isset($file) && $file['name'] != "")
    {
        if (in_array($file['type'], $allowed_types))
        {
            // Get the maximum upload file size
            $max_upload_size = get_setting("max_upload_size");

            // If the file size is less than the maximum
            if ($file['size'] < $max_upload_size)
            {
                // If there was no error with the upload
                if ($file['error'] == 0)
                {
                    // Read the file
                    $content = fopen($file['tmp_name'], 'rb');

                    // Create a unique file name
                    $unique_name = generate_token(30);

                    // Actual file name
                    $name = "import.csv";

                    // Open the database connection
                    $db = db_open();

                    // Store the file in the database
                    $stmt = $db->prepare("INSERT INTO `import_export_tmp` (name, unique_name, size, content) VALUES (:name, :unique_name, :size, :content)");
                    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 30);
                    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
                    $stmt->bindParam(":size", $file['size'], PDO::PARAM_INT);
                    $stmt->bindParam(":content", $content, PDO::PARAM_LOB);
                    $stmt->execute();

                    // Close the database connection
                    db_close($db);

                    // Rename the file
                    move_uploaded_file($file['tmp_name'], sys_get_temp_dir() . '/import.csv');

                    // Get the column headers
                    $headers = get_column_headers(sys_get_temp_dir() . '/import.csv');

                    // Return the CSV column headers
                    return $headers;
                }
                // Otherwise, file upload error
                else
                {
                    // Display an alert
                    set_alert(true, "bad", $escaper->escapeHtml($lang['ImportingFileError']));
                    return 0;
                }
            }
            // Otherwise, file too big
            else
            {
                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($lang['UploadingFileTooBig']));
                return 0;
            }
        }
        // Otherwise, file type not supported
        else
        {
            // Display an alert
            set_alert(true, "bad", _lang("UploadingFileTypeNoSupport", ['file_type' => $file['type']]));
            return 0;
        }
    }
    // Otherwise, upload error
    else
    {
        // Display an alert
        set_alert(true, "bad", $escaper->escapeHtml($lang['NoImportingFile']));
        return 0;
    }
}

/********************************
 * FUNCTION: GET COLUMN HEADERS *
 ********************************/
function get_column_headers($file)
{
    // Set PHP to auto detect line endings
    ini_set('auto_detect_line_endings', true);

    // If we can read the file
    if (($handle = fopen($file, 'rb')) !== FALSE)
    {
        // If we can get the first line in the file
        if (($headers = fgetcsv($handle, 0, ",")) !== FALSE)
        {
            return $headers;
        }

        // Close the file
        fclose($handle);
    }

    // Return false
    return FALSE;
}

/*******************************
 * FUNCTION: INSERT TABLE ROWS *
 *******************************/
function insert_table_rows($file)
{
    // Set PHP to auto detect line endings
    ini_set('auto_detect_line_endings', true);

    // If we can read the file
    if (($handle = fopen($file, 'rb')) !== FALSE)
    {
        // Get the first line in the file
        $headers = fgetcsv($handle, 0, ",");

        // Start the insert query
                $start_query = "INSERT INTO `temporary_file` (";

        foreach ($headers as $header)
        {
            $start_query .= "`" . $header . "`,";
        }

        // Remove the last comma
                $start_query = substr($start_query, 0, -1);

        $start_query .= ") VALUES (";

        // Open the database connection
        $db = db_open();

        // For each row in the file
        while ($row = fgetcsv($handle, 0, ","))
        {
            $query = $start_query;

            foreach ($row as $value)
            {
                $query .= "'" . addslashes($value) . "',";                
            }

            // Remove the last comma
            $query = substr($query, 0, -1);

            $query .= ");";

            $stmt = $db->prepare($query);
            $stmt->execute();
        }

        // Close the database connection
        db_close($db);
    }
}

/************************
 * FUNCTION: EXPORT CSV *
 ************************/
function export_csv($type="combined")
{
    // Include the language file
    require_once(language_file());

    global $lang, $escaper;

    switch ($type)
    {
        // Combine risks, mitigations, and reviews
        case "combined":
            $header = array($lang['RiskId'],
                $lang['Status'],
                $lang['Subject'],
                $lang['ExternalReferenceId'],
                $lang['ControlRegulation'],
                $lang['ControlNumber'],
                $lang['SiteLocation'],
                $lang['RiskSource'],
                $lang['Category'],
                $lang['Team'],
                $lang['AdditionalStakeholders'],
                $lang['Technology'],
                $lang['Owner'],
                $lang['OwnersManager'],
                $lang['RiskAssessment'],
                $lang['AdditionalNotes'],
                $lang['SubmissionDate'],
                $lang['Project'],
                $lang['SubmittedBy'],
                $lang['DateClosed'],
                $lang['AffectedAssets'],
                $lang['RiskScoringMethod'],
                $lang['InherentRisk'],
                $lang['ResidualRisk'],
                'Classic-'.$lang['Likelihood'],
                'Classic-'.$lang['Impact'],
                'CVSS-'.$lang['AttackVector'],
                'CVSS-'.$lang['AttackComplexity'],
                'CVSS-'.$lang['Authentication'],
                'CVSS-'.$lang['ConfidentialityImpact'],
                'CVSS-'.$lang['IntegrityImpact'],
                'CVSS-'.$lang['AvailabilityImpact'],
                'CVSS-'.$lang['Exploitability'],
                'CVSS-'.$lang['RemediationLevel'],
                'CVSS-'.$lang['ReportConfidence'],
                'CVSS-'.$lang['CollateralDamagePotential'],
                'CVSS-'.$lang['TargetDistribution'],
                'CVSS-'.$lang['ConfidentialityRequirement'],
                'CVSS-'.$lang['IntegrityRequirement'],
                'CVSS-'.$lang['AvailabilityRequirement'],
                'DREAD-'.$lang['DamagePotential'],
                'DREAD-'.$lang['Reproducibility'],
                'DREAD-'.$lang['Exploitability'],
                'DREAD-'.$lang['AffectedUsers'],
                'DREAD-'.$lang['Discoverability'],
                'OWASP-'.$lang['SkillLevel'],
                'OWASP-'.$lang['Motive'],
                'OWASP-'.$lang['Opportunity'],
                'OWASP-'.$lang['Size'],
                'OWASP-'.$lang['EaseOfDiscovery'],
                'OWASP-'.$lang['EaseOfExploit'],
                'OWASP-'.$lang['Awareness'],
                'OWASP-'.$lang['IntrusionDetection'],
                'OWASP-'.$lang['LossOfConfidentiality'],
                'OWASP-'.$lang['LossOfIntegrity'],
                'OWASP-'.$lang['LossOfAvailability'],
                'OWASP-'.$lang['LossOfAccountability'],
                'OWASP-'.$lang['FinancialDamage'],
                'OWASP-'.$lang['ReputationDamage'],
                'OWASP-'.$lang['NonCompliance'],
                'OWASP-'.$lang['PrivacyViolation'],
                $lang['CustomValue'],
                $lang['ContributingLikelihood'],
                $lang['ContributingSubjectsImpacts'],
                $lang['MitigationDate'],
                $lang['PlanningStrategy'],
                $lang['MitigationPlanning'],
                $lang['MitigationEffort'],
                $lang['MitigationCost'],
                $lang['MitigationOwner'],
                $lang['MitigationTeam'],
                $lang['CurrentSolution'],
                $lang['SecurityRequirements'],
                $lang['SecurityRecommendations'],
                $lang['MitigatedBy'],
                $lang['ReviewDate'],
                $lang['Review'],
                $lang['Reviewer'],
                $lang['NextStep'],
                $lang['Comments'],
                $lang['NextReviewDate'],
                $lang['Tags'],
                $lang['MitigationPercent']
            );
            // If customization extra is enabled, add customization fields
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../customization/index.php'));
                
                $active_fields = get_active_fields();
                foreach($active_fields as $active_field)
                {
                    if($active_field['is_basic'] == 0)
                    {
                        $header[] = $escaper->escapeHtml($active_field['name']);
                    }
                }
            }

            $data = get_combined_array();
            $filename = "simplerisk_combined_export.csv";
            break;
        // Risks only
        case "risks":
            $header = array($lang['RiskId'],
                $lang['Status'],
                $lang['Subject'],
                $lang['ExternalReferenceId'],
                $lang['ControlRegulation'],
                $lang['ControlNumber'],
                $lang['SiteLocation'],
                $lang['RiskSource'],
                $lang['Category'],
                $lang['Team'],
                $lang['AdditionalStakeholders'],
                $lang['Technology'],
                $lang['Owner'],
                $lang['OwnersManager'],
                $lang['RiskAssessment'],
                $lang['AdditionalNotes'],
                $lang['SubmissionDate'],
                $lang['Project'],
                $lang['SubmittedBy'],
                $lang['RiskScoringMethod'],
                $lang['InherentRisk'],
                $lang['ResidualRisk'],
                'Classic-'.$lang['Likelihood'],
                'Classic-'.$lang['Impact'],
                'CVSS-'.$lang['AttackVector'],
                'CVSS-'.$lang['AttackComplexity'],
                'CVSS-'.$lang['Authentication'],
                'CVSS-'.$lang['ConfidentialityImpact'],
                'CVSS-'.$lang['IntegrityImpact'],
                'CVSS-'.$lang['AvailabilityImpact'],
                'CVSS-'.$lang['Exploitability'],
                'CVSS-'.$lang['RemediationLevel'],
                'CVSS-'.$lang['ReportConfidence'],
                'CVSS-'.$lang['CollateralDamagePotential'],
                'CVSS-'.$lang['TargetDistribution'],
                'CVSS-'.$lang['ConfidentialityRequirement'],
                'CVSS-'.$lang['IntegrityRequirement'],
                'CVSS-'.$lang['AvailabilityRequirement'],
                'DREAD-'.$lang['DamagePotential'],
                'DREAD-'.$lang['Reproducibility'],
                'DREAD-'.$lang['Exploitability'],
                'DREAD-'.$lang['AffectedUsers'],
                'DREAD-'.$lang['Discoverability'],
                'OWASP-'.$lang['SkillLevel'],
                'OWASP-'.$lang['Motive'],
                'OWASP-'.$lang['Opportunity'],
                'OWASP-'.$lang['Size'],
                'OWASP-'.$lang['EaseOfDiscovery'],
                'OWASP-'.$lang['EaseOfExploit'],
                'OWASP-'.$lang['Awareness'],
                'OWASP-'.$lang['IntrusionDetection'],
                'OWASP-'.$lang['LossOfConfidentiality'],
                'OWASP-'.$lang['LossOfIntegrity'],
                'OWASP-'.$lang['LossOfAvailability'],
                'OWASP-'.$lang['LossOfAccountability'],
                'OWASP-'.$lang['FinancialDamage'],
                'OWASP-'.$lang['ReputationDamage'],
                'OWASP-'.$lang['NonCompliance'],
                'OWASP-'.$lang['PrivacyViolation'],
                $lang['CustomValue'],
                $lang['ContributingLikelihood'],
                $lang['ContributingSubjectsImpacts'],
                $lang['MitigationCost'],
                $lang['MitigationOwner'],
                $lang['MitigationTeam'],
                $lang['MitigationDate'],
                $lang['PlanningStrategy'],
                $lang['MitigationPlanning'],
                $lang['MitigationEffort'],
                $lang['CurrentSolution'],
                $lang['SecurityRequirements'],
                $lang['SecurityRecommendations'],
                $lang['MitigatedBy'],
                $lang['MitigationPercent'],
                $lang['AffectedAssets'],
                $lang['Tags'],
            );
            
            // If customization extra is enabled, add customization fields
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../customization/index.php'));
                
                $active_fields = get_active_fields();
                foreach($active_fields as $active_field)
                {
                    if($active_field['is_basic'] == 0 && ($active_field['tab_index'] == 1 || $active_field['tab_index'] == 2))
                    {
                        $header[] = $escaper->escapeHtml($active_field['name']);
                    }
                }
            }
            
            $data = get_risks_array();
            
            $filename = "simplerisk_risk_export.csv";
            break;
        // Mitigations only
        case "mitigations":
            $header = array($lang['MitigationId'],
                $lang['RiskId'],
                $lang['MitigationDate'],
                $lang['PlanningStrategy'],
                $lang['MitigationPlanning'],
                $lang['MitigationEffort'],
                $lang['MitigationCost'],
                $lang['MitigationOwner'],
                $lang['MitigationTeam'],
                $lang['CurrentSolution'],
                $lang['SecurityRequirements'],
                $lang['SecurityRecommendations'],
                $lang['MitigatedBy'],
                $lang['MitigationPercent']
            );

            // If customization extra is enabled, add customization fields
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../customization/index.php'));
                
                $active_fields = get_active_fields();
                foreach($active_fields as $active_field)
                {
                    if($active_field['is_basic'] == 0 && $active_field['tab_index'] == 2)
                    {
                        $header[] = $escaper->escapeHtml($active_field['name']);
                    }
                }
            }

            $data = get_mitigations_array();
            $filename = "simplerisk_mitigation_export.csv";
            break;
        // Reviews only
        case "reviews":
            $header = array(
                $lang['ReviewId'],
                $lang['RiskId'],
                $lang['ReviewDate'],
                $lang['Review'],
                $lang['Reviewer'],
                $lang['NextStep'],
                $lang['Comments'],
                $lang['NextReviewDate']
            );

            // If customization extra is enabled, add customization fields
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../customization/index.php'));
                
                $active_fields = get_active_fields();
                foreach($active_fields as $active_field)
                {
                    if($active_field['is_basic'] == 0 && $active_field['tab_index'] == 3)
                    {
                        $header[] = $escaper->escapeHtml($active_field['name']);
                    }
                }
            }

            $data = get_reviews_array();
            $filename = "simplerisk_review_export.csv";
            break;
        // Assessments
        case "assessments":
            $header = array($lang['QuestionnaireTemplateName'],
                $lang['QuestionID'],
                $lang['Question'],
                $lang['HasFile'],
                $lang['QuestionOrdering'],
                $lang['Answer'],
                $lang['SubmitRisk'],
                $lang['Subject'],
                $lang['Owner'],
                $lang['AffectedAssets'],
                $lang['SubQuestions'],
                $lang['RiskScoringMethod'],
                $lang['CalculatedRisk'],
                'Classic-'.$lang['Likelihood'],
                'Classic-'.$lang['Impact'],
                'CVSS-'.$lang['AttackVector'],
                'CVSS-'.$lang['AttackComplexity'],
                'CVSS-'.$lang['Authentication'],
                'CVSS-'.$lang['ConfidentialityImpact'],
                'CVSS-'.$lang['IntegrityImpact'],
                'CVSS-'.$lang['AvailabilityImpact'],
                'CVSS-'.$lang['Exploitability'],
                'CVSS-'.$lang['RemediationLevel'],
                'CVSS-'.$lang['ReportConfidence'],
                'CVSS-'.$lang['CollateralDamagePotential'],
                'CVSS-'.$lang['TargetDistribution'],
                'CVSS-'.$lang['ConfidentialityRequirement'],
                'CVSS-'.$lang['IntegrityRequirement'],
                'CVSS-'.$lang['AvailabilityRequirement'],
                'DREAD-'.$lang['DamagePotential'],
                'DREAD-'.$lang['Reproducibility'],
                'DREAD-'.$lang['Exploitability'],
                'DREAD-'.$lang['AffectedUsers'],
                'DREAD-'.$lang['Discoverability'],
                'OWASP-'.$lang['SkillLevel'],
                'OWASP-'.$lang['Motive'],
                'OWASP-'.$lang['Opportunity'],
                'OWASP-'.$lang['Size'],
                'OWASP-'.$lang['EaseOfDiscovery'],
                'OWASP-'.$lang['EaseOfExploit'],
                'OWASP-'.$lang['Awareness'],
                'OWASP-'.$lang['IntrusionDetection'],
                'OWASP-'.$lang['LossOfConfidentiality'],
                'OWASP-'.$lang['LossOfIntegrity'],
                'OWASP-'.$lang['LossOfAvailability'],
                'OWASP-'.$lang['LossOfAccountability'],
                'OWASP-'.$lang['FinancialDamage'],
                'OWASP-'.$lang['ReputationDamage'],
                'OWASP-'.$lang['NonCompliance'],
                'OWASP-'.$lang['PrivacyViolation'],
                $lang['CustomValue']
            );
            
            // Assessment to be exported
            $template_id = $_POST['assessment'];
            
            $data = get_assessments_array($template_id);
            $filename = "simplerisk_assessments_export.csv";
            break;
        // Assets
        case "assets":
            $header = array(
                $lang['IPAddress'],
                $lang['AssetName'],
                $lang['AssetValuation'],
                $lang['SiteLocation'],
                $lang['Team'],
                $lang['AssetDetails'],
                $lang['Tags'],
                $lang['Verified']
            );
            
            // If customization extra is enabled, add customization fields
            if(customization_extra())
            {
                // Include the extra
                require_once(realpath(__DIR__ . '/../customization/index.php'));
                
                $active_fields = get_active_fields("asset");
                foreach($active_fields as $active_field)
                {
                    if($active_field['is_basic'] == 0)
                    {
                        $header[] = $escaper->escapeHtml($active_field['name']);
                    }
                }
            }

            $data = get_assets_array();
            $filename = "simplerisk_assets_export.csv";
            break;
        case "asset_groups":
            $header = array(
                $lang['AssetGroupId'],
                $lang['AssetGroupName'],
                $lang['Assets']
            );

            $data = get_asset_groups_array();
            $filename = "simplerisk_asset_groups_export.csv";
            break;
        case "controls":
            $header = array(
                $lang['ControlID'],
                $lang['ControlShortName'],
                $lang['ControlLongName'],
                $lang['ControlDescription'],
                $lang['SupplementalGuidance'],
                $lang['ControlOwner'],
                $lang['ControlFramework'],
                $lang['ControlClass'],
                $lang['ControlPhase'],
                $lang['ControlNumber'],
                $lang['ControlPriority'],
                $lang['ControlFamily'],
                $lang['MitigationPercent'],
            );

            $data = get_controls_array();
            $filename = "simplerisk_controls_export.csv";
            break;
        // Empty array
        default:
            $data = array();
            $header = array();
            break;
    }

    // Tell the browser it's going to be a CSV file
    header('Content-Type: application/csv; charset=UTF-8');

    // Tell the browser we want to save it instead of displaying it
    header('Content-Disposition: attachement; filename="' . $escaper->escapeUrl($filename) . '";');
    // Open memory as a file so no temp file needed
    $f = fopen('php://output', 'w');


    fputcsv($f, $header);
    foreach ($data as $row)
    {
        fputcsv($f, $row);
    }

    // Close the file
    fclose($f);

    // Exit so that page content is not included in the results
    exit(0);
}

/*********************************
 * FUNCTION: GET COMBINED ARRAY *
 *********************************/
function get_combined_array()
{
    // Get all risks, mitigations, and reviews
    $query = "
        SELECT
            a.id+1000 as risk_id,
            a.status,
            a.subject,
            a.reference_id,
            b.name AS regulation,
            a.control_number,
            GROUP_CONCAT(distinct c.name SEPARATOR '; ') AS location,
            x.name AS source,
            d.name AS category,
            group_concat(distinct e.name) AS team,
            GROUP_CONCAT(DISTINCT adsh.name, ',') additional_stakeholders,
            group_concat(distinct f.name) AS technology,
            g.name AS owner,
            h.name AS manager,
            a.assessment,
            a.notes,
            a.submission_date,
            i.name AS project,
            j.name AS submitted_by,
            z.closure_date,
            group_concat(distinct assets.name) asset_names,
            l.name AS scoring_method,
            k.calculated_risk,
            ROUND((k.calculated_risk - (k.calculated_risk * GREATEST(IFNULL(o.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk,
            m.name AS CLASSIC_likelihood,
            n.name AS CLASSIC_impact,
            k.CVSS_AccessVector,
            k.CVSS_AccessComplexity,
            k.CVSS_Authentication,
            k.CVSS_ConfImpact,
            k.CVSS_IntegImpact,
            k.CVSS_AvailImpact,
            k.CVSS_Exploitability,
            k.CVSS_RemediationLevel,
            k.CVSS_ReportConfidence,
            k.CVSS_CollateralDamagePotential,
            k.CVSS_TargetDistribution,
            k.CVSS_ConfidentialityRequirement,
            k.CVSS_IntegrityRequirement,
            k.CVSS_AvailabilityRequirement,
            k.DREAD_DamagePotential,
            k.DREAD_Reproducibility,
            k.DREAD_Exploitability,
            k.DREAD_AffectedUsers,
            k.DREAD_Discoverability,
            k.OWASP_SkillLevel,
            k.OWASP_Motive,
            k.OWASP_Opportunity,
            k.OWASP_Size,
            k.OWASP_EaseOfDiscovery,
            k.OWASP_EaseOfExploit,
            k.OWASP_Awareness,
            k.OWASP_IntrusionDetection,
            k.OWASP_LossOfConfidentiality,
            k.OWASP_LossOfIntegrity,
            k.OWASP_LossOfAvailability,
            k.OWASP_LossOfAccountability,
            k.OWASP_FinancialDamage,
            k.OWASP_ReputationDamage,
            k.OWASP_NonCompliance,
            k.OWASP_PrivacyViolation,
            k.Custom,
            cl.name Contributing_Likelihood_name,
            group_concat(distinct CONCAT_WS('_', cr.subject, ci.name)) as Contributing_Risksubjects_Impactnames,
            o.submission_date AS mitigation_date,
            p.name AS planning_strategy,
            o.planning_date,
            q.name AS mitigation_effort,
            o.mitigation_cost,
            mo.name as mitigation_owner,
            group_concat(distinct w.name) AS mitigation_team,
            o.current_solution,
            o.security_requirements,
            o.security_recommendations,
            r.name AS mitigated_by,
            s.submission_date AS review_date,
            t.name AS review,
            u.name AS reviewer,
            v.name AS next_step,
            s.comments,
            s.next_review,
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag ASC SEPARATOR ',') as risk_tags,
            o.mitigation_percent,
            GROUP_CONCAT(DISTINCT ag.name SEPARATOR ',') AS asset_group_names
        FROM risks a 
            LEFT JOIN frameworks b ON a.regulation = b.value
            LEFT JOIN location c ON FIND_IN_SET(c.value, a.location)
            LEFT JOIN category d ON a.category = d.value 
            LEFT JOIN team e ON FIND_IN_SET(e.value, a.team) 
            LEFT JOIN technology f ON FIND_IN_SET(f.value, a.technology) 
            LEFT JOIN user g ON a.owner = g.value 
            LEFT JOIN user h ON a.manager = h.value 
            LEFT JOIN projects i ON a.project_id = i.value 
            LEFT JOIN user j ON a.submitted_by = j.value 
            LEFT JOIN risk_scoring k ON a.id = k.id 
            LEFT JOIN scoring_methods l ON k.scoring_method = l.value 
            LEFT JOIN likelihood m ON k.CLASSIC_likelihood = m.value 
            LEFT JOIN impact n ON k.CLASSIC_impact = n.value 
            LEFT JOIN mitigations o ON a.id = o.risk_id 
            LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, o.mitigation_controls) AND fc.deleted=0
            LEFT JOIN planning_strategy p ON o.planning_strategy = p.value 
            LEFT JOIN mitigation_effort q ON o.mitigation_effort = q.value 
            LEFT JOIN user r ON o.submitted_by = r.value 
            LEFT JOIN (select risk_id, max(submission_date) as submission_date, review, reviewer, next_step, comments, next_review from mgmt_reviews group by risk_id) as s ON a.id = s.risk_id 
            LEFT JOIN review t ON s.review = t.value 
            LEFT JOIN user u ON s.reviewer = u.value 
            LEFT JOIN next_step v ON s.next_step = v.value 
            LEFT JOIN team w ON FIND_IN_SET(w.value, o.mitigation_team)  
            LEFT JOIN source x ON a.source = x.value 
            LEFT JOIN user mo ON o.mitigation_owner = mo.value
            LEFT JOIN risks_to_assets rta ON a.id = rta.risk_id
            LEFT JOIN assets on rta.asset_id = assets.id
            LEFT JOIN risks_to_asset_groups rtag ON a.id = rtag.risk_id
            LEFT JOIN asset_groups ag ON rtag.asset_group_id = ag.id
            LEFT JOIN (SELECT risk_id, max(closure_date) as closure_date FROM closures group by risk_id) z ON a.id = z.risk_id 
            LEFT JOIN user adsh ON FIND_IN_SET(adsh.value, a.additional_stakeholders)
            LEFT JOIN risk_scoring_contributing_impacts rsci ON k.scoring_method=6 AND k.id=rsci.risk_scoring_id
            LEFT JOIN contributing_risks cr ON rsci.contributing_risk_id=cr.id
            LEFT JOIN impact ci ON rsci.impact=ci.value
            LEFT JOIN likelihood cl ON k.Contributing_Likelihood=cl.value
            LEFT JOIN tags_taggees tt ON tt.taggee_id = a.id and tt.type = 'risk'
            LEFT JOIN tags tg on tg.id = tt.tag_id
        GROUP BY
            a.id
        ORDER BY 
            a.id ASC;
        ";

    // Query the database
    $db = db_open();
    $stmt = $db->prepare($query);
    $stmt->execute();
    db_close($db);

    // Store the results in the risks array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cehck if customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../customization/index.php'));
        
        $active_fields = get_active_fields();
        $customization_extra = true;
    }
    else
    {
        $customization_extra = false;
    }

    // For each row
    foreach ($risks as $key => $row)
    {
        if(!is_null($risks[$key]['mitigation_cost'])){
            $risks[$key]['mitigation_cost'] = get_asset_value_by_id($risks[$key]['mitigation_cost'], true);
        }
        
        // Try decrypting
        $risks[$key]['subject'] = try_decrypt($risks[$key]['subject']);
        $risks[$key]['assessment'] = try_decrypt($risks[$key]['assessment']);
        $risks[$key]['notes'] = try_decrypt($risks[$key]['notes']);
        $risks[$key]['regulation'] = try_decrypt($risks[$key]['regulation']);

        // If the project is not Unassigned Risks
        if ($risks[$key]['project'] != 'Unassigned Risks')
        {
            $risks[$key]['project'] = try_decrypt($risks[$key]['project']);
        }

        $risks[$key]['current_solution'] = try_decrypt($risks[$key]['current_solution']);
        $risks[$key]['security_requirements'] = try_decrypt($risks[$key]['security_requirements']);
        $risks[$key]['security_recommendations'] = try_decrypt($risks[$key]['security_recommendations']);
        $risks[$key]['comments'] = try_decrypt($risks[$key]['comments']);

        // If the next review is 0000-00-00
        if ($risks[$key]['next_review'] == "0000-00-00")
        {
            // If next_review_date_uses setting is Residual Risk.
            if(get_setting('next_review_date_uses') == "ResidualRisk")
            {
                $risks[$key]['next_review'] = next_review_by_score($risks[$key]['residual_risk']);
            }
            // If next_review_date_uses setting is Inherent Risk.
            else
            {
                $risks[$key]['next_review'] = next_review_by_score($risks[$key]['calculated_risk']);
            }
        }

        $asset_names = array();
        if ($risks[$key]['asset_names']) {
            $asset_names_enc = explode(",", $risks[$key]['asset_names']);
            foreach($asset_names_enc as $asset_name_enc) {
                array_push($asset_names, try_decrypt($asset_name_enc));
            }
        }

        if ($risks[$key]['asset_group_names']) {
            foreach(explode(",", $risks[$key]['asset_group_names']) as $asset_group_name) {
                array_push($asset_names, "[$asset_group_name]");
            }

        }
        unset($risks[$key]['asset_group_names']);

        $risks[$key]['asset_names'] = implode(",", $asset_names);

        if ($risks[$key]['additional_stakeholders']) {
            $adsh = array();
            $additional_stakeholders = explode(",", $risks[$key]['additional_stakeholders']);
            foreach($additional_stakeholders as $additional_stakeholder) {
                if (trim($additional_stakeholder))
                    array_push($adsh, trim($additional_stakeholder));
            }
            $risks[$key]['additional_stakeholders'] = implode(",", $adsh);
        }

        
        // If customization extra is enabled, add custom values
        if($customization_extra)
        {
            $custom_values = getCustomFieldValuesByRiskId($row['risk_id']);

            foreach($active_fields as $active_field)
            {
                // If main field, ignore.
                if($active_field['is_basic'] == 0){
                    $text = "";
                    
                    // Get value of custom filed
                    foreach($custom_values as $custom_value)
                    {
                        // Check if this custom value is for the active field
                        if($custom_value['field_id'] == $active_field['id']){
                            $value = $custom_value['value'];
                            
                            $text = get_plan_custom_field_name_by_value($active_field['id'], $custom_value['field_type'], $custom_value['encryption'], $value);
                            
                            break;
                        }
                    }

                    // Set custom value to xls risk row
                    array_push($risks[$key], $text);
                }
                
            }
        }
        
    } // End foreach for risks

    // Return the risks array
    return $risks;
}

/*****************************
 * FUNCTION: GET RISKS ARRAY *
 *****************************/
function get_risks_array()
{
        // Get all risks
    $query = "
    /*
    SELECT a.id+1000 as risk_id, a.status, a.subject, a.reference_id, b.name AS regulation, a.control_number, GROUP_CONCAT(distinct c.name SEPARATOR '; ') AS location, o.name AS source, d.name AS category, group_concat(distinct e.name) AS team, GROUP_CONCAT(DISTINCT adsh.name, ',') additional_stakeholders, group_concat(distinct f.name) AS technology, g.name AS owner, h.name AS manager, a.assessment, a.notes, a.submission_date, i.name AS project, j.name AS submitted_by, l.name AS scoring_method, k.calculated_risk, ROUND((k.calculated_risk - (k.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk, m.name AS CLASSIC_likelihood, n.name AS CLASSIC_impact, k.CVSS_AccessVector, k.CVSS_AccessComplexity, k.CVSS_Authentication, k.CVSS_ConfImpact, k.CVSS_IntegImpact, k.CVSS_AvailImpact, k.CVSS_Exploitability, k.CVSS_RemediationLevel, k.CVSS_ReportConfidence, k.CVSS_CollateralDamagePotential, k.CVSS_TargetDistribution, k.CVSS_ConfidentialityRequirement, k.CVSS_IntegrityRequirement, k.CVSS_AvailabilityRequirement, k.DREAD_DamagePotential, k.DREAD_Reproducibility, k.DREAD_Exploitability, k.DREAD_AffectedUsers, k.DREAD_Discoverability, k.OWASP_SkillLevel, k.OWASP_Motive, k.OWASP_Opportunity, k.OWASP_Size, k.OWASP_EaseOfDiscovery, k.OWASP_EaseOfExploit, k.OWASP_Awareness, k.OWASP_IntrusionDetection, k.OWASP_LossOfConfidentiality, k.OWASP_LossOfIntegrity, k.OWASP_LossOfAvailability, k.OWASP_LossOfAccountability, k.OWASP_FinancialDamage, k.OWASP_ReputationDamage, k.OWASP_NonCompliance, k.OWASP_PrivacyViolation, k.Custom, k.Contributing_Likelihood, cl.name Contributing_Likelihood_name, group_concat(distinct CONCAT_WS('_', rsci.contributing_risk_id, rsci.impact)) as Contributing_Risks_Impacts, group_concat(distinct CONCAT_WS('_', cr.subject, ci.name)) as Contributing_Risksubjects_Impactnames, mg.mitigation_cost, mo.name as mitigation_owner, mt.name as mitigation_team, mg.submission_date mitigation_date, ps.name planning_strategy, mg.planning_date, me.name mitigation_effort, mg.current_solution, mg.security_requirements, mg.security_recommendations, msu.name mitigated_by, group_concat(distinct assets.name) asset_names
    */
    SELECT
        a.id+1000 as risk_id,
        a.status,
        a.subject,
        a.reference_id,
        b.name AS regulation,
        a.control_number,
        GROUP_CONCAT(distinct c.name SEPARATOR '; ') AS location,
        o.name AS source,
        d.name AS category,
        group_concat(distinct e.name) AS team,
        GROUP_CONCAT(DISTINCT adsh.name, ',') additional_stakeholders,
        group_concat(distinct f.name) AS technology,
        g.name AS owner,
        h.name AS manager,
        a.assessment,
        a.notes,
        a.submission_date,
        i.name AS project,
        j.name AS submitted_by,
        l.name AS scoring_method,
        k.calculated_risk,
        ROUND((k.calculated_risk - (k.calculated_risk * GREATEST(IFNULL(mg.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk,
        m.name AS CLASSIC_likelihood,
        n.name AS CLASSIC_impact,
        k.CVSS_AccessVector,
        k.CVSS_AccessComplexity,
        k.CVSS_Authentication,
        k.CVSS_ConfImpact,
        k.CVSS_IntegImpact,
        k.CVSS_AvailImpact,
        k.CVSS_Exploitability,
        k.CVSS_RemediationLevel,
        k.CVSS_ReportConfidence,
        k.CVSS_CollateralDamagePotential,
        k.CVSS_TargetDistribution,
        k.CVSS_ConfidentialityRequirement,
        k.CVSS_IntegrityRequirement,
        k.CVSS_AvailabilityRequirement,
        k.DREAD_DamagePotential,
        k.DREAD_Reproducibility,
        k.DREAD_Exploitability,
        k.DREAD_AffectedUsers,
        k.DREAD_Discoverability,
        k.OWASP_SkillLevel,
        k.OWASP_Motive,
        k.OWASP_Opportunity,
        k.OWASP_Size,
        k.OWASP_EaseOfDiscovery,
        k.OWASP_EaseOfExploit,
        k.OWASP_Awareness,
        k.OWASP_IntrusionDetection,
        k.OWASP_LossOfConfidentiality,
        k.OWASP_LossOfIntegrity,
        k.OWASP_LossOfAvailability,
        k.OWASP_LossOfAccountability,
        k.OWASP_FinancialDamage,
        k.OWASP_ReputationDamage,
        k.OWASP_NonCompliance,
        k.OWASP_PrivacyViolation,
        k.Custom,
        cl.name Contributing_Likelihood_name,
        group_concat(distinct CONCAT_WS('_', cr.subject, ci.name)) as Contributing_Risksubjects_Impactnames,
        mg.mitigation_cost,
        mo.name as mitigation_owner,
        group_concat(distinct mt.name) as mitigation_team,
        mg.submission_date mitigation_date,
        ps.name planning_strategy,
        mg.planning_date,
        me.name mitigation_effort,
        mg.current_solution,
        mg.security_requirements,
        mg.security_recommendations,
        msu.name mitigated_by,
        mg.mitigation_percent,
        group_concat(distinct assets.name) asset_names,
        GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag ASC SEPARATOR ',') as risk_tags,
        GROUP_CONCAT(DISTINCT ag.name SEPARATOR ',') AS asset_group_names
    FROM risks a 
        LEFT JOIN frameworks b ON a.regulation = b.value
        LEFT JOIN location c ON FIND_IN_SET(c.value, a.location)
        LEFT JOIN category d ON a.category = d.value 
        LEFT JOIN team e ON FIND_IN_SET(e.value, a.team)
        LEFT JOIN technology f ON FIND_IN_SET(f.value, a.technology) 
        LEFT JOIN user g ON a.owner = g.value 
        LEFT JOIN user h ON a.manager = h.value 
        LEFT JOIN projects i ON a.project_id = i.value 
        LEFT JOIN user j ON a.submitted_by = j.value 
        LEFT JOIN risk_scoring k ON a.id = k.id 
        LEFT JOIN scoring_methods l ON k.scoring_method = l.value 
        LEFT JOIN likelihood m ON k.CLASSIC_likelihood = m.value 
        LEFT JOIN impact n ON k.CLASSIC_impact = n.value 
        LEFT JOIN source o ON a.source = o.value 
        LEFT JOIN mitigations mg ON a.id = mg.risk_id 
        LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, mg.mitigation_controls) AND fc.deleted=0
        LEFT JOIN user mo ON mg.mitigation_owner = mo.value 
        LEFT JOIN team mt ON FIND_IN_SET(mt.value, mg.mitigation_team)
        LEFT JOIN risks_to_assets rta ON a.id = rta.risk_id
        LEFT JOIN assets on rta.asset_id = assets.id
        LEFT JOIN risks_to_asset_groups rtag ON a.id = rtag.risk_id
        LEFT JOIN asset_groups ag ON rtag.asset_group_id = ag.id
        LEFT JOIN planning_strategy ps ON mg.planning_strategy = ps.value 
        LEFT JOIN mitigation_effort me ON mg.mitigation_effort = me.value 
        LEFT JOIN user msu ON mg.submitted_by = msu.value 
        LEFT JOIN user adsh ON FIND_IN_SET(adsh.value, a.additional_stakeholders)
        LEFT JOIN risk_scoring_contributing_impacts rsci ON k.scoring_method=6 AND k.id=rsci.risk_scoring_id
        LEFT JOIN contributing_risks cr ON rsci.contributing_risk_id=cr.id
        LEFT JOIN impact ci ON rsci.impact=ci.value
        LEFT JOIN likelihood cl ON k.Contributing_Likelihood=cl.value
        LEFT JOIN tags_taggees tt ON tt.taggee_id = a.id and tt.type = 'risk'
        LEFT JOIN tags tg on tg.id = tt.tag_id
    group by 
        a.id
    ORDER BY 
        a.id ASC; ";

    // Query the database
    $db = db_open();
    $stmt = $db->prepare($query);
    $stmt->execute();
    db_close($db);

    // Store the results in the risks array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cehck if customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../customization/index.php'));
        
        $active_fields = get_active_fields();
        $customization_extra = true;
    }
    else
    {
        $customization_extra = false;
    }

    // For each row
    foreach ($risks as $key => $row)
    {
        // Try decrypting
        $risks[$key]['subject'] = try_decrypt($risks[$key]['subject']);
        $risks[$key]['assessment'] = try_decrypt($risks[$key]['assessment']);
        $risks[$key]['notes'] = try_decrypt($risks[$key]['notes']);
        $risks[$key]['regulation'] = try_decrypt($risks[$key]['regulation']);

        // If the project is not Unassigned Risks
        if ($risks[$key]['project'] != 'Unassigned Risks')
        {
            $risks[$key]['project'] = try_decrypt($risks[$key]['project']);
        }

        $risks[$key]['mitigation_cost'] = get_asset_value_by_id($row['mitigation_cost'], true);
        $risks[$key]['current_solution'] = try_decrypt($row['current_solution']);
        $risks[$key]['security_requirements'] = try_decrypt($row['security_requirements']);
        $risks[$key]['security_recommendations'] = try_decrypt($row['security_recommendations']);

        $asset_names = array();
        if ($risks[$key]['asset_names']) {
            $asset_names_enc = explode(",", $risks[$key]['asset_names']);
            foreach($asset_names_enc as $asset_name_enc) {
                array_push($asset_names, try_decrypt($asset_name_enc));
            }
        }

        if ($risks[$key]['asset_group_names']) {
            foreach(explode(",", $risks[$key]['asset_group_names']) as $asset_group_name) {
                array_push($asset_names, "[$asset_group_name]");
            }

        }
        unset($risks[$key]['asset_group_names']);

        $risks[$key]['asset_names'] = implode(",", $asset_names);

        if ($risks[$key]['additional_stakeholders']) {
            $adsh = array();
            $additional_stakeholders = explode(",", $risks[$key]['additional_stakeholders']);
            foreach($additional_stakeholders as $additional_stakeholder) {
                if (trim($additional_stakeholder))
                    array_push($adsh, trim($additional_stakeholder));
            }
            $risks[$key]['additional_stakeholders'] = implode(",", $adsh);
        }
        
        // If customization extra is enabled, add custom values
        if($customization_extra)
        {
            $custom_values = getCustomFieldValuesByRiskId($row['risk_id']);

            foreach($active_fields as $active_field)
            {
                // If main field, ignore.
                if($active_field['is_basic'] == 0 && ($active_field['tab_index'] == 1 || $active_field['tab_index'] == 2)){
                    $text = "";
                    
                    // Get value of custom filed
                    foreach($custom_values as $custom_value)
                    {
                        // Check if this custom value is for the active field
                        if($custom_value['field_id'] == $active_field['id']){
                            $value = $custom_value['value'];
                            
                            $text = get_plan_custom_field_name_by_value($active_field['id'], $custom_value['field_type'], $custom_value['encryption'], $value);

                            break;
                        }
                    }
                    // Set custom value to xls risk row
                    array_push($risks[$key], $text);
                }
                
            }
        }
        
    } // End foreach for risks

    // Return the risks array
    return $risks;
}

/***********************************
 * FUNCTION: GET MITIGATIONS ARRAY *
 ***********************************/
function get_mitigations_array()
{
    // Get all mitigations
    $query = "
        SELECT
            a.id,
            a.risk_id+1000 as risk_id,
            a.submission_date,
            a.mitigation_cost,
            u.name as mitigation_owner,
            b.name AS planning_strategy,
            a.planning_date,
            c.name AS mitigation_effort,
            group_concat(distinct e.name) AS mitigation_team,
            a.current_solution,
            a.security_requirements,
            a.security_recommendations,
            d.name AS submitted_by,
            a.mitigation_percent
        FROM mitigations a 
            LEFT JOIN planning_strategy b ON a.planning_strategy = b.value 
            LEFT JOIN mitigation_effort c ON a.mitigation_effort = c.value 
            LEFT JOIN user d ON a.submitted_by = d.value 
            LEFT JOIN team e ON FIND_IN_SET(e.value, a.mitigation_team)   
            LEFT JOIN user u ON a.mitigation_owner = u.value 
        GROUP BY
            a.id
        ORDER BY 
            a.id ASC;
    ";

        // Query the database
        $db = db_open();
        $stmt = $db->prepare($query);
        $stmt->execute();
        db_close($db);

        // Store the results in the risks array
        $risks = $stmt->fetchAll();

        // Cehck if customization extra is enabled
        if(customization_extra())
        {
            // Include the extra
            require_once(realpath(__DIR__ . '/../customization/index.php'));
            
            $active_fields = get_active_fields();
            $customization_extra = true;
        }
        else
        {
            $customization_extra = false;
        }

        $results = array();
        // For each row
        foreach ($risks as $key => $row)
        {
            $result = array(
                $row['id'],
                $row['risk_id'],
                $row['submission_date'],
                $row['planning_strategy'],
                $row['planning_date'],
                $row['mitigation_effort'],
                get_asset_value_by_id($row['mitigation_cost'], true),
                $row['mitigation_owner'],
                $row['mitigation_team'],
                try_decrypt($row['current_solution']),
                try_decrypt($row['security_requirements']),
                try_decrypt($row['security_recommendations']),
                $row['submitted_by'],
                $row['mitigation_percent']
            );

            // If customization extra is enabled, add custom values
            if($customization_extra)
            {
                $custom_values = getCustomFieldValuesByRiskId($row['risk_id']);

                foreach($active_fields as $active_field)
                {
                    // If main field, ignore.
                    if($active_field['is_basic'] == 0 && $active_field['tab_index'] == 2){
                        $text = "";
                        
                        // Get value of custom filed
                        foreach($custom_values as $custom_value)
                        {
                            // Check if this custom value is for the active field
                            if($custom_value['field_id'] == $active_field['id']){
                                $value = $custom_value['value'];
                                
                                $text = get_plan_custom_field_name_by_value($active_field['id'], $custom_value['field_type'], $custom_value['encryption'], $value);

                                break;
                            }
                        }

                        // Set custom value to xls risk row
                        array_push($result, $text);
                    }
                }
            }
        
            $results[] = $result;
        }
        // Return the risks array
        return $results;
}

/*******************************
 * FUNCTION: GET REVIEWS ARRAY *
 *******************************/
function get_reviews_array()
{
    // Get all reviews
    $query = "SELECT a.id, a.risk_id+1000 risk_id, a.submission_date, b.name AS review, c.name AS reviewer, d.name AS next_step, a.comments, a.next_review FROM mgmt_reviews a LEFT JOIN review b ON a.review = b.value LEFT JOIN user c ON a.reviewer = c.value LEFT JOIN next_step d ON a.next_step = d.value LEFT JOIN risk_scoring e ON a.risk_id = e.id ORDER BY a.id ASC";

    // Query the database
    $db = db_open();
    $stmt = $db->prepare($query);
    $stmt->execute();
    db_close($db);

    // Store the results in the risks array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cehck if customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../customization/index.php'));
        
        $active_fields = get_active_fields();
        $customization_extra = true;
    }
    else
    {
        $customization_extra = false;
    }

    // For each row
    foreach ($risks as $key => $row)
    {       
        // Try decrypting
        $risks[$key]['comments'] = try_decrypt($risks[$key]['comments']);

        // If the next review is 0000-00-00
        if ($risks[$key]["next_review"] == "0000-00-00")
        {
            // Update it to the default next review for that calculated risk
//                $risks[$key][7] = next_review_by_score($risks[$key][8]);
        }

        // If customization extra is enabled, add custom values
        if($customization_extra)
        {
            $custom_values = getCustomFieldValuesByRiskId($row['risk_id']);

            foreach($active_fields as $active_field)
            {
                // If main field, ignore.
                if($active_field['is_basic'] == 0 && $active_field['tab_index'] == 3){
                    $text = "";
                    
                    // Get value of custom filed
                    foreach($custom_values as $custom_value)
                    {
                        // Check if this custom value is for the active field
                        if($custom_value['field_id'] == $active_field['id']){
                            $value = $custom_value['value'];
                            
                            $text = get_plan_custom_field_name_by_value($active_field['id'], $custom_value['field_type'], $custom_value['encryption'], $value);

                            break;
                        }
                    }

                    // Set custom value to xls risk row
                    array_push($risks[$key], $text);
                }
                
            }
        }

    }

    // Return the risks array
    return $risks;
}


/******************************
 * FUNCTION: GET ASSETS ARRAY *
 ******************************/
function get_assets_array()
{
    // Get all reviews
    $query = "
        SELECT
            a.id,
            a.ip,
            a.name,
            a.value,
            a.location,
            GROUP_CONCAT(DISTINCT t.name ORDER BY t.name ASC SEPARATOR ',') teams,
            a.details,
            GROUP_CONCAT(DISTINCT tg.tag ORDER BY tg.tag ASC SEPARATOR ',') tags,
            a.verified
        FROM
            `assets` a
            LEFT JOIN `team` t ON FIND_IN_SET(t.value, a.teams)
            LEFT JOIN tags_taggees tt ON tt.taggee_id = a.id and tt.type = 'asset'
            LEFT JOIN tags tg on tg.id = tt.tag_id
        GROUP BY
            a.id
        ORDER BY a.name ASC";

    // Query the database
    $db = db_open();
    $stmt = $db->prepare($query);
    $stmt->execute();
    db_close($db);

    // Store the results in the assets array
    $assets = $stmt->fetchAll();
    
    // Cehck if customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../customization/index.php'));
        
        $active_fields = get_active_fields("asset");
        $customization_extra = true;
    }
    else
    {
        $customization_extra = false;
    }

    $results = [];
    
    // For each row
    foreach ($assets as $key => &$row)
    {
        // Try decrypting and getting the display values
        $result = array(
            try_decrypt($row['ip']),
            try_decrypt($row['name']),
            get_asset_value_by_id($row['value'], true),
            get_name_by_value("location", $row['location']),
            $row['teams'],
            try_decrypt($row['details']),
            $row['tags'],
            $row['verified'] ? "True" : "False"
        );
        
        // If customization extra is enabled, add custom values
        if($customization_extra)
        {
            $custom_values = getCustomFieldValuesByAssetId($row['id']);

            foreach($active_fields as $active_field)
            {
                // If main field, ignore.
                if($active_field['is_basic'] == 0){
                    $text = "";
                    
                    // Get value of custom filed
                    foreach($custom_values as $custom_value)
                    {
                        // Check if this custom value is for the active field
                        if($custom_value['field_id'] == $active_field['id']){
                            $value = $custom_value['value'];
                            
                            $text = get_plan_custom_field_name_by_value($active_field['id'], $custom_value['field_type'], $custom_value['encryption'], $value);

                            break;
                        }
                    }

                    // Set custom value to xls risk row
                    array_push($result, $text);
                }
                
            }
        }
        $results[] = $result;
    }

    // Return the assets array
    return $results;
}

/************************************
 * FUNCTION: GET ASSET GROUPS ARRAY *
 ************************************/
function get_asset_groups_array() {

    $db = db_open();
    $stmt = $db->prepare("
        SELECT
            `ag`.`id`,
            `ag`.`name`,
            GROUP_CONCAT(DISTINCT `a`.`name` ORDER BY `a`.`name` ASC SEPARATOR ',') as assets
        FROM
            `asset_groups` ag
            LEFT JOIN `assets_asset_groups` aag ON `aag`.`asset_group_id` = `ag`.`id`
            LEFT JOIN `assets` a ON `aag`.`asset_id` = `a`.`id`
        GROUP BY
            `ag`.`id`
        ORDER BY `ag`.`name` ASC;
    ");
    $stmt->execute();
    db_close($db);

    $asset_groups = $stmt->fetchAll();
    
    $results = [];
    foreach ($asset_groups as $group) {
        $assets = [];

        if ($group['assets']) {
            foreach(explode(',', $group['assets']) as $asset) {
                $assets[] = try_decrypt($asset);
            }
        }

        $results[] = array($group['id'], $group['name'], implode(',', $assets));
    }

    return $results;
}

/********************************
 * FUNCTION: GET CONTROLS ARRAY *
 ********************************/
function get_controls_array() {

    $db = db_open();
    $stmt = $db->prepare("
        SELECT
            t1.id,
            t1.short_name,
            t1.long_name,
            t1.description,
            t1.supplemental_guidance,
            t6.name control_owner_name,
            t1.framework_ids,
            t2.name control_class_name,
            t5.name control_phase_name,
            t1.control_number,
            t3.name control_priority_name,
            t4.name family_short_name,
            t1.mitigation_percent
        FROM `framework_controls` t1 
            LEFT JOIN `control_class` t2 on t1.control_class=t2.value
            LEFT JOIN `control_priority` t3 on t1.control_priority=t3.value
            LEFT JOIN `family` t4 on t1.family=t4.value
            LEFT JOIN `control_phase` t5 on t1.control_phase=t5.value
            LEFT JOIN `user` t6 on t1.control_owner=t6.value
            LEFT JOIN `frameworks` t7 ON FIND_IN_SET(t7.value, t1.framework_ids)
        WHERE
            (t7.status=1 or t1.framework_ids is null or t1.framework_ids = '') AND t1.deleted=0
        GROUP BY
            t1.id
        ORDER BY
            t1.short_name;
    ");
    $stmt->execute();
    $controls = $stmt->fetchAll();

    db_close($db);

    $frameworks = [];
    foreach(get_frameworks(1) as $framework) {
        $frameworks[$framework['value']] = $framework['name'];
    }
    
    $results = [];
    foreach ($controls as $control) {
        $framework_names = [];

        if ($control['framework_ids']) {
            foreach(explode(',', $control['framework_ids']) as $framework_id) {
                $framework_names[] = $frameworks[$framework_id];
            }
        }

        $results[] = array(
            $control['id'],
            $control['short_name'],
            $control['long_name'],
            $control['description'],
            $control['supplemental_guidance'],
            $control['control_owner_name'],
            implode(',', $framework_names),
            $control['control_class_name'],
            $control['control_phase_name'],
            $control['control_number'],
            $control['control_priority_name'],
            $control['family_short_name'],
            $control['mitigation_percent'],
        );
    }

    return $results;
}

/************************
 * FUNCTION: CREATE CSV *
 ************************/
function create_csv($handle, $fields, $delimiter = ',', $enclosure = '"')
{
    // Check if $fields is an array
    if (!is_array($fields))
    {
        return false;
    }

    // Walk through the data array
    for ($i = 0, $n = count($fields); $i < $n; $i ++)
    {
        // Only 'correct' non-numeric values
        if (!is_numeric($fields[$i]))
        {
            // Duplicate in-value $enclusure's and put the value in $enclosure's
            $fields[$i] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $fields[$i]) . $enclosure;
        }

        // If $delimiter is a dot (.), also correct numeric values
        if (($delimiter == '.') && (is_numeric($fields[$i])))
        {
            // Put the value in $enclosure's
            $fields[$i] = $enclosure . $fields[$i] . $enclosure;
        }
    }

    // Combine the data array with $delimiter and write it to the file
    $line = implode($delimiter, $fields) . "\n";
    fwrite($handle, $line);

    // Return the length of the written data
    return strlen($line);
}

/***********************************
 * FUNCTION: DISPLAY IMPORT EXPORT *
 ***********************************/
function display_import_export()
{
    global $escaper;
    global $lang;

    echo "<div class=\"hero-unit\">\n";
        echo "<h4>" . $escaper->escapeHtml($lang['ImportExportExtra']) . "</h4>\n";
        echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . import_export_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";
    echo "</div>\n";
}

/********************************************
 * FUNCTION: DISPLAY IMPORT EXPORT SELECTOR *
 ********************************************/
function display_import_export_selector()
{
    global $escaper;
    global $lang;

    process_integration_update();

    // Script to select import and export divs
    echo "
        <script>
            function reCreateFileInput(name) {
                //Start with removing the other file inputs
                $('input[type=\"file\"]').remove();
                //Then create a new one
                var input = $('<input/>')
                        .attr('type', 'file')
                        .attr('name', name)
                        .prop('required', true);
                // and put it into its wrapper
                $('#' + name + '_wrapper').append(input);
            }

            function select_import(selectObject)
            {
                var selected = selectObject.value;
                var risks = document.getElementById(\"import-risks\");
                var assets = document.getElementById(\"import-assets\");
                var asset_groups = document.getElementById(\"import-asset-groups\");
                var controls = document.getElementById(\"import-controls\");

                if (selected == \"risks\")
                {
                    risks.style.display = \"\";
                    assets.style.display = \"none\";
                    asset_groups.style.display = \"none\";
                    controls.style.display = \"none\";

                    reCreateFileInput('risk_file');
                }
                else if (selected == \"assets\")
                {
                    risks.style.display = \"none\";
                    assets.style.display = \"\";
                    asset_groups.style.display = \"none\";
                    controls.style.display = \"none\";

                    reCreateFileInput('asset_file');
                }
                else if (selected == \"asset-groups\")
                {
                    risks.style.display = \"none\";
                    assets.style.display = \"none\";
                    asset_groups.style.display = \"\";
                    controls.style.display = \"none\";

                    reCreateFileInput('asset_group_file');
                }
                else if (selected == \"controls\")
                {
                    risks.style.display = \"none\";
                    assets.style.display = \"none\";
                    asset_groups.style.display = \"none\";
                    controls.style.display = \"\";

                    reCreateFileInput('controls_file');
                }
            }

            function select_export(selectObject)
            {
                var selected = selectObject.value;
                var risks = document.getElementById(\"export-risks\");
                var assets = document.getElementById(\"export-assets\");
                var asset_groups = document.getElementById(\"export-asset-groups\");
                var controls = document.getElementById(\"export-controls\");

                if (selected == \"risks\")
                {
                    risks.style.display = \"\";
                    assets.style.display = \"none\";
                    asset_groups.style.display = \"none\";
                    controls.style.display = \"none\";
                } else if (selected == \"assets\")
                {
                    risks.style.display = \"none\";
                    assets.style.display = \"\";
                    asset_groups.style.display = \"none\";
                    controls.style.display = \"none\";
                } else if (selected == \"asset-groups\")
                {
                    risks.style.display = \"none\";
                    assets.style.display = \"none\";
                    asset_groups.style.display = \"\";
                    controls.style.display = \"none\";
                } else if (selected == \"controls\")
                {
                    risks.style.display = \"none\";
                    assets.style.display = \"none\";
                    asset_groups.style.display = \"none\";
                    controls.style.display = \"\";
                }
            }

            function removeSelected(selected_name, selected_value){
                if(!selected_value) return;
                var elem = document.getElementsByClassName(\"mapping\");
                var currentSelected = this; // Save reference of current dropdown
                for(var i = 0; i < elem.length; i++){
                    if (elem[i].name != selected_name){
                        for (j=0;j<elem[i].length; j++){
                            if (elem[i].options[j].value == selected_value) {
                                elem[i].remove(j);
                            }
                        }
                    }
                }
            }

            function ShowHideDiv(caller, div){
                var callerElement = document.getElementById(caller);
                var divElement = document.getElementById(div);
                if (callerElement.checked == true){
                    divElement.style.display = '';
                }
                else divElement.style.display = 'none';
            }
        </script>
    ";
    
    if(!empty($_GET['importoption']))
    {
        echo "
            <script>
                $(document).ready(function(){
                    $('#select-import option[value=". $escaper->escapeHtml($_GET['importoption']) ."]').prop('selected', 'selected').change();
                })
            </script>
        ";
    }

    echo "<div class=\"wrap\">\n";
    echo "<ul class=\"tabs group\">\n";
    echo "<li><a " . (!isset($_POST['tab']) || (isset($_POST['tab']) && $_POST['tab'] == 'import') ? "class=\"active\"" : "") . " href=\"#/import\">". $escaper->escapeHtml($lang['Import']) . "</a></li>\n";
    echo "<li><a " . ((isset($_POST['tab']) && $_POST['tab'] == 'export') ? "class=\"active\"" : "") . "href=\"#/export\">". $escaper->escapeHtml($lang['Export']) . "</a></li>\n";
    echo "<li><a " . ((isset($_POST['tab']) && $_POST['tab'] == 'integrations') ? "class=\"active\"" : "") . "href=\"#/integrations\">". $escaper->escapeHtml($lang['Integrations']) . "</a></li>\n";
    echo "</ul>\n";
    echo "<div id=\"content\">\n";

    // Section to display import
    echo "<div id=\"import\" " . ((isset($_POST['tab']) && $_POST['tab'] != 'import') ? "style=\"display: none;" : "") . "\">\n";
    echo "<h4><u>" . $escaper->escapeHtml($lang['Import']) . "</u></h4>\n";
    echo "<form name=\"import\" id=\"import\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
    echo "<input type=\"hidden\" name=\"tab\" value=\"import\" />\n";
    echo "<b>" . $escaper->escapeHtml($lang['Select']) . ":</b>&nbsp;&nbsp;<select name=\"select-import\" id=\"select-import\" onchange=\"javascript: select_import(this)\">\n";
    echo "<option value=\"risks\"" . ((!isset($_POST['select-import']) || $_POST['select-import'] == "risks") ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ImportRisks']) . "</option>\n";
    echo "<option value=\"assets\"" . ((isset($_POST['select-import']) && $_POST['select-import'] == "assets") ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ImportAssets']) . "</option>\n";
    echo "<option value=\"asset-groups\"" . ((isset($_POST['select-import']) && $_POST['select-import'] == "asset-groups") ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ImportAssetGroups']) . "</option>\n";
    echo "<option value=\"controls\"" . ((isset($_POST['select-import']) && $_POST['select-import'] == "controls") ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ImportControls']) . "</option>\n";
    echo "</select>\n";

    echo "<div id=\"import-risks\" style=\"display:" . ((!isset($_POST['select-import']) || $_POST['select-import'] == "risks") ? "" : "none") . ";\">\n";
    display_import();
    echo "</div>\n";

    echo "<div id=\"import-assets\" style=\"display:" . ((isset($_POST['select-import']) && $_POST['select-import'] == "assets") ? "" : "none") . ";\">\n";
    display_import_assets();
    echo "</div>\n";
    
    echo "<div id=\"import-asset-groups\" style=\"display:" . ((isset($_POST['select-import']) && $_POST['select-import'] == "asset-groups") ? "" : "none") . ";\">\n";
    display_import_asset_groups();
    echo "</div>\n";
    
    echo "<div id=\"import-controls\" style=\"display:" . ((isset($_POST['select-import']) && $_POST['select-import'] == "controls") ? "" : "none") . ";\">\n";
    display_import_controls();
    echo "</div>\n";

    echo "</form>\n";
    echo "</div>\n";

    // Section to display export
    echo "<div id=\"export\" " . (!isset($_POST['tab']) || (isset($_POST['tab']) && $_POST['tab'] != 'export') ? "style=\"display: none;" : "") . "\">\n";
    echo "<h4><u>" . $escaper->escapeHtml($lang['Export']) . "</u></h4>\n";
    echo "<form name=\"export\" id=\"export\" method=\"post\" action=\"\">\n";
    echo "<input type=\"hidden\" name=\"tab\" value=\"export\" />\n";
        echo "<b>" . $escaper->escapeHtml($lang['Select']) . ":</b>&nbsp;&nbsp;<select name=\"select-export\" id=\"select-export\" onchange=\"javascript: select_export(this)\">\n";
        echo "<option value=\"risks\"" . ((!isset($_POST['select-export']) || (isset($_POST['select-export']) && $_POST['select-export'] == "risks")) ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ExportRisks']) . "</option>\n";
        echo "<option value=\"assets\"" . ((isset($_POST['select-export']) && $_POST['select-export'] == "assets") ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ExportAssets']) . "</option>\n";
        echo "<option value=\"asset-groups\"" . ((isset($_POST['select-export']) && $_POST['select-export'] == "asset_groups") ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ExportAssetGroups']) . "</option>\n";
        echo "<option value=\"controls\"" . ((isset($_POST['select-export']) && $_POST['select-export'] == "controls") ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ExportControls']) . "</option>\n";
        echo "</select>\n";

    echo "<div id=\"export-risks\" style=\"display:" . ((!isset($_POST['select-export']) || $_POST['select-export'] == "risks") ? "" : "none") . ";\">\n";
    display_export();
    echo "</div>\n";
    echo "<div id=\"export-assets\" style=\"display:none;\">\n";
    display_assets_export();
    echo "</div>\n";
    echo "<div id=\"export-asset-groups\" style=\"display:none;\">\n";
    display_asset_groups_export();
    echo "</div>\n";
    echo "<div id=\"export-controls\" style=\"display:none;\">\n";
    display_controls_export();
    echo "</div>\n";
    
    echo "</form>\n";
    echo "</div>\n";

    // Section to display vulnerability management functionality
    $import_assets = get_setting('import_assets');
    $import_vulnerabilities = get_setting('import_vulnerabilities');
    $integration_tenable = get_setting('integration_tenable');
    $integration_nexpose = get_setting('integration_nexpose');
    echo "<div id=\"integrations\" " . (!isset($_POST['tab']) || (isset($_POST['tab']) && $_POST['tab'] != 'integrations') ? "style=\"display: none;" : "") . "\">\n";
    echo "<form name=\"integrations\" id=\"integrations\" method=\"post\" action=\"\">\n";
    echo "<input type=\"hidden\" name=\"tab\" value=\"integrations\" />\n";
    echo "<b><u>" . $escaper->escapeHtml($lang['Settings']) . "</u></b>\n";
    echo "<label><input type=\"checkbox\" name=\"import_assets\" id =\"import_assets\"" . ($import_assets == 1 ? " checked" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['ImportAssets']) . "</label>\n";
    echo "<label><input type=\"checkbox\" name=\"import_vulnerabilities\" id =\"import_vulnerabilities\"" . ($import_vulnerabilities == 1 ? " checked" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['ImportVulnerabilities']) . "</label>\n";

    echo "<br /><b><u>" . $escaper->escapeHtml($lang['Integrations']) . "</u></b>\n";
    echo "<label><input type=\"checkbox\" name=\"tenable\" id=\"tenable\" onclick=\"ShowHideDiv('tenable','tenable_display')\"" . ($integration_tenable == 1 ? " checked" : "") . " />&nbsp;&nbsp;Tenable.io</label>\n";
    echo "<label><input type=\"checkbox\" name=\"nexpose\" id=\"nexpose\" onclick=\"ShowHideDiv('nexpose','nexpose_display')\"" . ($integration_nexpose == 1 ? " checked" : "") . " />&nbsp;&nbsp;Rapid7 Nexpose</label>\n";

    // Show Tenable configurations
    show_tenable_configuration();

    // Show Nexpose configurations
    show_nexpose_configuration();

    echo "<br /><input type=\"submit\" name=\"update_integrations\" value=\"" . $escaper->escapeHtml($lang['Update']) . "\" />&nbsp;&nbsp;<input type=\"submit\" name=\"update_assets\" value=\"" . $escaper->escapeHtml($lang['ImportAssets']) . "\" />&nbsp;&nbsp;<input type=\"submit\" name=\"update_vulnerabilities\" value=\"" . $escaper->escapeHtml($lang['ImportVulnerabilities']) . "\" />\n";
    echo "</form>\n";
    echo "</div>\n";
  
    // End the content
    echo "</div>\n";

    // End the wrap
    echo "</div>\n";

    // Run the tabs script
    echo "
        <script>
            (function($) {

                var tabs =  $(\".tabs li a\");
                
                var hash = window.location.hash;
                if(hash){
                    //console.log(hash);
                    tabs.removeClass('active');
                    $(\".tabs\").find(\"[href='\"+hash+\"']\").addClass('active');

                    var content = hash.replace('/','');
                    $(\"#content > div\").hide();
                    $(content).fadeIn(200);
                }
                
                tabs.click(function() {
                    var content = this.hash.replace('/','');
                    tabs.removeClass(\"active\");
                    $(this).addClass(\"active\");
                    
                    $('#content > div').hide();

                    $(content).fadeIn(200);
                });

            })(jQuery);
        </script>
    ";
}

/****************************************
 * FUNCTION: PROCESS INTEGRATION UPDATE *
 ****************************************/
function process_integration_update()
{
    // If the update was posted
    if (isset($_POST['update_integrations']))
    {
        // Set the asset import value
        update_setting('import_assets', (isset($_POST['import_assets']) ? '1' : '0'));

        // Set the vulnerability import value
        update_setting('import_vulnerabilities', (isset($_POST['import_vulnerabilities']) ? '1' : '0'));

        // Set the Tenable.io values
        process_tenable_integration_update();

        // Set the Rapid7 Nexpose values
        process_nexpose_integration_update();
    }

    // If the user has requested to import assets
    if (isset($_POST['update_assets']))
    {
        // If the import assets setting is set
        if (get_setting('import_assets') == '1')
        {
            // If the tenable integration is enabled
            if (get_setting('integration_tenable') == '1')
            {
                // Import the tenable assets
                $assets = import_tenable_assets();
            }

            // If the nexpose integration is enabled
            if (get_setting('integration_nexpose') == '1')
            {
                // Import the nexpose assets
                $assets = import_nexpose_assets();
            }
        }
    }

    // If the user has requested to import vulnerabilities
    if (isset($_POST['update_vulnerabilities']))
    {
        // If the import vulnerabilities setting is set
        if (get_setting('import_vulnerabilities') == '1')
        {
            // If the tenable integration is enabled
            if (get_setting('integration_tenable') == '1')
            {
                // Import the tenable vulnerabilities
                $vulnerabilities = import_tenable_vulnerabilities();
            }

            // If the Nexpose integration is enabled
            if (get_setting('integration_nexpose') == '1')
            {
                // Import the Nexpose vulnerabilities
                $vulnerabilities = import_nexpose_vulnerabilities();
            }
        }
    }
}

/****************************
 * FUNCTION: DISPLAY IMPORT *
 ****************************/
function display_import()
{
    global $escaper;
    global $lang;

    // Show the import form
    // If a file has not been imported or mapped
    if (!isset($_POST['import_csv']) && !isset($_POST['csv_mapped']))
    {
        // If the tmp file already exists
        if(!is_submitted() && file_exists(sys_get_temp_dir() . '/import.csv'))
        {                               
            // Delete it
            $file = sys_get_temp_dir() . '/import.csv';
            delete_file($file);
        }
        

        // Open the database connection
        $db = db_open();

        // Create a table for the file upload if it doesn't exist
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_tmp` (id INT NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, unique_name VARCHAR(30) NOT NULL, size INT NOT NULL, timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, content LONGBLOB NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        // Create a table for the mappings if it doesn't exist
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `import_export_mappings` (value INT NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, mapping BLOB, PRIMARY KEY (value)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        // Close the database connection
        db_close($db);

            echo "Import the following CSV file into SimpleRisk:<br />\n";
            echo "<div id=\"risk_file_wrapper\"><input type=\"file\" id=\"risk_file\" name=\"risk_file\" required /></div>\n";
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
            echo "<div class=\"row-fluid\">\n";
            echo "<div class=\"span12 text-left\" id=\"Mapping\">\n";
            echo $escaper->escapeHtml($lang['Mapping']) . ":&nbsp;&nbsp;\n";
            create_dropdown("import_export_mappings");
            echo "&nbsp;(" . $escaper->escapeHtml($lang['Optional']) . ") &nbsp;&nbsp;";
            echo "<button id=\"delete_mapping\" name=\"delete_mapping\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['Delete']) ."</button>";
            echo "</div>\n";
            echo "</div>\n";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"import_csv\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
    }
    // If a file has been imported and mapped
    else if (isset($_POST['csv_mapped']))
    {
        // If the import file doesn't exist, get it from the DB
        get_import_from_db();

        // Copy posted values into a new array
        $mappings = $_POST;

        // Remove the first value in the array (CSRF Token)
        array_shift($mappings);

        // Remove the last value in the array (Submit Button)
        array_pop($mappings);

        // Import using the mapping
        import_with_mapping($mappings);

        // If the user wants to save the mapping
        if (isset($_POST['mapping_name']) && $_POST['mapping_name'] != "")
        {
            save_mapping($_POST['mapping_name'], $mappings);
        }

        // Delete the import from the db
        delete_import_from_db();
    }
    // If a file has been imported
    else
    {
        // Import the file
        $display = import_csv($_FILES['risk_file']);

        // If the file import was successful
        if ($display != 0)
        {
            if(isset($_POST['import_export_mappings']) && $_POST['import_export_mappings'] != ''){
                $mappings = get_mapping($_POST['import_export_mappings']);
            }else{
                $mappings = array();
            }

            // Print the remove selected javascript
            //remove_selected_js();

            echo "<input type=\"checkbox\" name=\"import_first\" />&nbsp;Import First Row\n";
            echo "<br /><br />\n";
            echo "<table class=\"table table-bordered table-condensed \">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            echo "<th width=\"200px\">File Columns</th>\n";
            echo "<th>" . $escaper->escapeHtml($lang['SimpleRiskColumnMapping']) . "</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            // Column counter
            $col_counter = 0;

            // For each column in the file
            foreach ($display as $column)
            {
                echo "<tr>\n";
                    echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                    echo "<td>\n";
                    simplerisk_column_name_dropdown("col_" . $col_counter, $mappings);
                    echo "</td>\n";
                echo "</tr>\n";

                // Increment the column counter
                $col_counter++;
            }

            echo "</tbody>\n";
            echo "</table>\n";
            echo "<div>\n";
            echo $escaper->escapeHtml($lang['SaveMappingAs']) . ":&nbsp;&nbsp;<input type=\"text\" name=\"mapping_name\" />\n";
            echo "</div>\n";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"csv_mapped\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
            echo "<script>$(document).ready(function(){ $('input[type=\"file\"]').remove(); });</script>";
        }
        // Otherwise, file import error
        else
        {
            refresh();
        }
    }
}

/****************************
 * FUNCTION: DISPLAY EXPORT *
 ****************************/
function display_export()
{
    global $escaper;
    global $lang;

    // Show the export form
    echo $escaper->escapeHtml($lang['ExportToCSVByClickingBelow']) . ":<br />\n";
    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"risks_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportRisks']) . "</button>\n";
    echo "<button type=\"submit\" name=\"mitigations_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportMitigations']) . "</button>\n";
    echo "<button type=\"submit\" name=\"reviews_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportReviews']) . "</button>\n";
    echo "<button type=\"submit\" name=\"combined_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportCombined']) . "</button>\n";
    echo "</div>\n";
}

/***********************************
 * FUNCTION: DISPLAY ASSETS EXPORT *
 ***********************************/
function display_assets_export()
{
    global $escaper;
    global $lang;

    // Show the asset export form
    echo $escaper->escapeHtml($lang['ExportToCSVByClickingBelow']) . ":<br />\n";
    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"assets_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportAssets']) . "</button>\n";
    echo "</div>\n";
}

/***********************************
 * FUNCTION: DISPLAY IMPORT ASSETS *
 ***********************************/
function display_import_assets()
{
    global $escaper;
    global $lang;

    // If a file has not been imported or mapped
    if (!isset($_POST['import_asset_csv']) && !isset($_POST['asset_csv_mapped']))
    {
        echo "Import the following CSV file into SimpleRisk:<br />\n";
        echo "<div id=\"asset_file_wrapper\"></div>\n";
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"import_asset_csv\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
        echo "</div>\n";
    }
    // If a file has been imported and mapped
    else if (isset($_POST['asset_csv_mapped']))
    {
        // Copy posted values into a new array
        $mappings = $_POST;

        // Remove the first value in the array (CSRF Token)
        array_shift($mappings);

        // Remove the last value in the array (Submit Button)
        array_pop($mappings);

        // Import using the mapping
        import_assets_with_mapping($mappings);
    }
    // If a file has been imported
    else
    {
        // Import the file
        $display = import_csv($_FILES['asset_file']);

        // If the file import was successful
        if ($display != 0)
        {
            // Print the remove selected javascript
            //remove_selected_js();

            echo "<input type=\"checkbox\" name=\"import_first\" />&nbsp;Import First Row\n";
            echo "<br /><br />\n";
            echo "<table class=\"table table-bordered table-condensed sortable\">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            echo "<th width=\"200px\">File Columns</th>\n";
            echo "<th>Asset Column Mapping</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            // Column counter
            $col_counter = 0;

            // For each column in the file
            foreach ($display as $column)
            {
                    echo "<tr>\n";
                    echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                    echo "<td>\n";
                    asset_column_name_dropdown("col_" . $col_counter);
                    echo "</td>\n";
                    echo "</tr>\n";

                    // Increment the column counter
                    $col_counter++;
            }

            echo "</tbody>\n";
            echo "</table>\n";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"asset_csv_mapped\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
            echo "<script>$(document).ready(function(){ $('input[type=\"file\"]').remove(); });</script>";
        }
        // Otherwise, file import error
        else
        {
            // Get any alert messages
            //get_alert();
        }
    }
}

/***********************************
 * FUNCTION: DISPLAY ASSETS EXPORT *
 ***********************************/
function display_asset_groups_export()
{
    global $escaper;
    global $lang;

    // Show the asset export form
    echo $escaper->escapeHtml($lang['ExportToCSVByClickingBelow']) . ":<br />\n";
    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"asset_groups_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportAssetGroups']) . "</button>\n";
    echo "</div>\n";
}

/*****************************************
 * FUNCTION: DISPLAY IMPORT ASSET GROUPS *
 *****************************************/
function display_import_asset_groups()
{
    global $escaper;
    global $lang;

    // If a file has not been imported or mapped
    if (!isset($_POST['import_asset_group_csv']) && !isset($_POST['asset_group_csv_mapped']))
    {
        echo "Import the following CSV file into SimpleRisk:<br />\n";
        echo "<div id=\"asset_group_file_wrapper\"></div>\n";
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"import_asset_group_csv\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
        echo "</div>\n";
    }
    // If a file has been imported and mapped
    else if (isset($_POST['asset_group_csv_mapped']))
    {
        // Copy posted values into a new array
        $mappings = $_POST;

        // Remove the first value in the array (CSRF Token)
        array_shift($mappings);

        // Remove the last value in the array (Submit Button)
        array_pop($mappings);

        // Import using the mapping
        import_asset_groups_with_mapping($mappings);
    }
    // If a file has been imported
    else
    {
        // Import the file
        $display = import_csv($_FILES['asset_group_file']);

        // If the file import was successful
        if ($display != 0)
        {
            // Print the remove selected javascript
            //remove_selected_js();

            echo "<input type=\"checkbox\" name=\"import_first\" />&nbsp;Import First Row\n";
            echo "<br /><br />\n";
            echo "<table class=\"table table-bordered table-condensed sortable\">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            echo "<th width=\"200px\">File Columns</th>\n";
            echo "<th>Asset Column Mapping</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            // Column counter
            $col_counter = 0;

            // For each column in the file
            foreach ($display as $column)
            {
                    echo "<tr>\n";
                    echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                    echo "<td>\n";
                    asset_group_column_name_dropdown("col_" . $col_counter);
                    echo "</td>\n";
                    echo "</tr>\n";

                    // Increment the column counter
                    $col_counter++;
            }

            echo "</tbody>\n";
            echo "</table>\n";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"asset_group_csv_mapped\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
            echo "<script>$(document).ready(function(){ $('input[type=\"file\"]').remove(); });</script>";
        }
        // Otherwise, file import error
        else
        {
            // Get any alert messages
            //get_alert();
        }
    }
}

/*********************************************
 * FUNCTION: SIMPLERISK COLUMN NAME DROPDOWN *
 *********************************************/
function simplerisk_column_name_dropdown($name, $mappings=array())
{
    global $escaper;

    // Get the list of SimpleRisk fields
    $fields = simplerisk_fields();

    echo "<select class=\"mapping\" name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" onchange=\"removeSelected(this.name, this.value)\">\n";
    echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

    $column_index = 0;
    
    // For each field
    foreach ($fields as $key => $value)
    {
        if(isset($mappings[$name]) && $mappings[$name] == $key){
            $selected = "selected";
        }else{
            $selected = "";
        }
        echo "<option {$selected} value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
        $column_index++;
    }
    
    // If customization extra is enabled, add custom fields
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../customization/index.php'));
        
        $custom_column_index = $column_index;
        
        $active_fields = get_active_fields();
        foreach($active_fields as $active_field)
        {
            if($active_field['is_basic'] == 0)
            {
                if($name == ("col_".$custom_column_index)){
                    $custom_column_selected = "selected";
                }else{
                    $custom_column_selected = "";
                }
                
                echo "<option {$custom_column_selected} value=\"" . $escaper->escapeHtml("custom_field_{$active_field['id']}") . "\">" . $escaper->escapeHtml($active_field['name']) . "</option>\n";
                $custom_column_index++;
            }
        }
        
    }

    echo "</select>\n";
}

/****************************************
 * FUNCTION: ASSET COLUMN NAME DROPDOWN *
 ****************************************/
function asset_column_name_dropdown($name)
{
    global $escaper;

    // Get the list of asset fields
    $fields = asset_fields();

    echo "<select class=\"mapping\" name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" onchange=\"removeSelected(this.name, this.value)\">\n";
    echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

    // For each field
    foreach ($fields as $key => $value)
    {
        echo "<option value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
    }
    
    // If customization extra is enabled, add custom fields
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../customization/index.php'));
        
        $active_fields = get_active_fields("asset");
        foreach($active_fields as $active_field)
        {
            if($active_field['is_basic'] == 0)
            {
                echo "<option value=\"" . $escaper->escapeHtml("custom_field_{$active_field['id']}") . "\">" . $escaper->escapeHtml($active_field['name']) . "</option>\n";
            }
        }
    }

    echo "</select>\n";
}

/**********************************************
 * FUNCTION: ASSET GROUP COLUMN NAME DROPDOWN *
 **********************************************/
function asset_group_column_name_dropdown($name)
{
    global $escaper;

    // Get the list of asset group fields
    $fields = asset_group_fields();

    echo "<select class=\"mapping\" name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" onchange=\"removeSelected(this.name, this.value)\">\n";
    echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

    // For each field
    foreach ($fields as $key => $value)
    {
        echo "<option value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
    }

    echo "</select>\n";
}

/********************************
 * FUNCTION: ASSET GROUP FIELDS *
 ********************************/
function asset_group_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'asset_group_id'            =>$lang['AssetGroupId'],
        'asset_group_name'          =>$lang['AssetGroupName'],
        'asset_group_assets'        =>$lang['Assets'],
    );

    // Return the fields array
    return $fields;
}

/**************************
 * FUNCTION: ASSET FIELDS *
 **************************/
function asset_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'asset_ip'          =>$lang['IPAddress'],
        'asset_name'        =>$lang['AssetName'],
        'asset_value'       =>$lang['AssetValue'],
        'asset_location'    =>$lang['SiteLocation'],
        'asset_team'        =>$lang['Team'],
        'asset_details'     =>$lang['Details'],
        'asset_tags'        =>$lang['Tags'],
        'asset_verified'    =>$lang['Verified'],
    );

    // Return the fields array
    return $fields;
}

/*******************************
 * FUNCTION: SIMPLERISK FIELDS *
 *******************************/
function simplerisk_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'risks_id'=>$lang['RiskId'],
        'risks_status'=>$lang['Status'],
        'risks_subject'=>$lang['Subject'],
        'risks_reference_id'=>$lang['ExternalReferenceId'],
        'risks_regulation'=>$lang['ControlRegulation'],
        'risks_control_number'=>$lang['ControlNumber'],
        'risks_location'=>$lang['SiteLocation'],
        'risks_source'=>$lang['RiskSource'],
        'risks_category'=>$lang['Category'],
        'risks_team'=>$lang['Team'],
        'risks_additional_stakeholders'=>$lang['AdditionalStakeholders'],
        'risks_technology'=>$lang['Technology'],
        'risks_owner'=>$lang['Owner'],
        'risks_manager'=>$lang['OwnersManager'],
        'risks_assessment'=>$lang['RiskAssessment'],
        'risks_notes'=>$lang['AdditionalNotes'],
        'risks_assets'=>$lang['AffectedAssets'],
        'risks_submission_date'=>$lang['SubmissionDate'],
        'risks_projects'=>$lang['Project'],
        'risks_submitted_by'=>$lang['SubmittedBy'],
        //'risks_last_update'=>$lang['LastReview'],
        //'risks_review_date'=>$lang['ReviewDate'],
        'closed_date'=>$lang['DateClosed'],

        'riskscoring_scoring_method'                    =>$lang['RiskScoringMethod'],
        //'riskscoring_calculated_risk'                   =>$lang['InherentRisk'],
        //'riskscoring_residual_risk'                     =>$lang['ResidualRisk'],
        'riskscoring_CLASSIC_likelihood'                =>'Classic-'.$lang['Likelihood'],
        'riskscoring_CLASSIC_impact'                    =>'Classic-'.$lang['Impact'],
        'riskscoring_CVSS_AccessVector'                 =>'CVSS-'.$lang['AttackVector'],
        'riskscoring_CVSS_AccessComplexity'             =>'CVSS-'.$lang['AttackComplexity'],
        'riskscoring_CVSS_Authentication'               =>'CVSS-'.$lang['Authentication'],
        'riskscoring_CVSS_ConfImpact'                   =>'CVSS-'.$lang['ConfidentialityImpact'],
        'riskscoring_CVSS_IntegImpact'                  =>'CVSS-'.$lang['IntegrityImpact'],
        'riskscoring_CVSS_AvailImpact'                  =>'CVSS-'.$lang['AvailabilityImpact'],
        'riskscoring_CVSS_Exploitability'               =>'CVSS-'.$lang['Exploitability'],
        'riskscoring_CVSS_RemediationLevel'             =>'CVSS-'.$lang['RemediationLevel'],
        'riskscoring_CVSS_ReportConfidence'             =>'CVSS-'.$lang['ReportConfidence'],
        'riskscoring_CVSS_CollateralDamagePotential'    =>'CVSS-'.$lang['CollateralDamagePotential'],
        'riskscoring_CVSS_TargetDistribution'           =>'CVSS-'.$lang['TargetDistribution'],
        'riskscoring_CVSS_ConfidentialityRequirement'   =>'CVSS-'.$lang['ConfidentialityRequirement'],
        'riskscoring_CVSS_IntegrityRequirement'         =>'CVSS-'.$lang['IntegrityRequirement'],
        'riskscoring_CVSS_AvailabilityRequirement'      =>'CVSS-'.$lang['AvailabilityRequirement'],
        'riskscoring_DREAD_DamagePotential'             =>'DREAD-'.$lang['DamagePotential'],
        'riskscoring_DREAD_Reproducibility'             =>'DREAD-'.$lang['Reproducibility'],
        'riskscoring_DREAD_Exploitability'              =>'DREAD-'.$lang['Exploitability'],
        'riskscoring_DREAD_AffectedUsers'               =>'DREAD-'.$lang['AffectedUsers'],
        'riskscoring_DREAD_Discoverability'             =>'DREAD-'.$lang['Discoverability'],
        'riskscoring_OWASP_SkillLevel'                  =>'OWASP-'.$lang['SkillLevel'],
        'riskscoring_OWASP_Motive'                      =>'OWASP-'.$lang['Motive'],
        'riskscoring_OWASP_Opportunity'                 =>'OWASP-'.$lang['Opportunity'],
        'riskscoring_OWASP_Size'                        =>'OWASP-'.$lang['Size'],
        'riskscoring_OWASP_EaseOfDiscovery'             =>'OWASP-'.$lang['EaseOfDiscovery'],
        'riskscoring_OWASP_EaseOfExploit'               =>'OWASP-'.$lang['EaseOfExploit'],
        'riskscoring_OWASP_Awareness'                   =>'OWASP-'.$lang['Awareness'],
        'riskscoring_OWASP_IntrusionDetection'          =>'OWASP-'.$lang['IntrusionDetection'],
        'riskscoring_OWASP_LossOfConfidentiality'       =>'OWASP-'.$lang['LossOfConfidentiality'],
        'riskscoring_OWASP_LossOfIntegrity'             =>'OWASP-'.$lang['LossOfIntegrity'],
        'riskscoring_OWASP_LossOfAvailability'          =>'OWASP-'.$lang['LossOfAvailability'],
        'riskscoring_OWASP_LossOfAccountability'        =>'OWASP-'.$lang['LossOfAccountability'],
        'riskscoring_OWASP_FinancialDamage'             =>'OWASP-'.$lang['FinancialDamage'],
        'riskscoring_OWASP_ReputationDamage'            =>'OWASP-'.$lang['ReputationDamage'],
        'riskscoring_OWASP_NonCompliance'               =>'OWASP-'.$lang['NonCompliance'],
        'riskscoring_OWASP_PrivacyViolation'            =>'OWASP-'.$lang['PrivacyViolation'],
        'riskscoring_Custom'                            =>$lang['CustomValue'],

        'riskscoring_Contributing_Likelihood'           =>$lang['ContributingLikelihood'],
        'riskscoring_Contributing_Subjects_Impacts'     =>$lang['ContributingSubjectsImpacts'],
        
        'mitigations_cost'=>$lang['MitigationCost'],
        'mitigations_owner'=>$lang['MitigationOwner'],
        'mitigations_team'=>$lang['MitigationTeam'],
        
        'mitigations_date'=>$lang['MitigationDate'],
        'planning_strategy'=>$lang['PlanningStrategy'],
        'planning_date'=>$lang['MitigationPlanning'],
        'mitigations_effort'=>$lang['MitigationEffort'],
        'current_solution'=>$lang['CurrentSolution'],
        'security_requirements'=>$lang['SecurityRequirements'],
        'security_recommendations'=>$lang['SecurityRecommendations'],
        'mitigated_by'=>$lang['MitigatedBy'],
        
        'reviews_submission_date'=>$lang['ReviewDate'],
        'reviews_review'=>$lang['Review'],
        'reviews_reviewer'=>$lang['Reviewer'],
        'reviews_next_step'=>$lang['NextStep'],
        'reviews_comments'=>$lang['Comments'],
        'reviews_next_review'=>$lang['NextReviewDate'],
        'risks_tags'=>$lang['Tags'],
        'mitigations_percent'=>$lang['MitigationPercent']
        
    );

    // Return the fields array
    return $fields;
}

/********************************
 * FUNCTION: REMOVE SELECTED JS *
 ********************************/
function remove_selected_js()
{
    echo "<script>\n";
    echo "function removeSelected(selected_name, selected_value){\n";
    echo "var elem = document.getElementsByClassName('mapping');\n";
    echo "var currentSelected = this; // Save reference of current dropdown\n";
    echo "for(var i = 0; i < elem.length; i++){\n";
    echo "  if (elem[i].name != selected_name){\n";
    echo "    for (j=0;j<elem[i].length; j++){\n";
    echo "      if (elem[i].options[j].value == selected_value) {\n";
    echo "        elem[i].remove(j);\n";
    echo "      }\n";
    echo "    }\n";
    echo "  }\n";
    echo "}\n";
    echo "}\n";
    echo "</script>\n";
}

/**********************************
 * FUNCTION: IMPORT WITH MAPPINGS *
 **********************************/
function import_with_mapping($mappings)
{
    global $escaper, $lang;

    // Open the temporary file for reading
    ini_set('auto_detect_line_endings', true);

    // Detect first line
    $first_line = true;

    // Importing customization fields if customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../customization/index.php'));
        
        $active_fields = get_active_fields();
        
        $customization_extra = true;
    }
    else
    {
        $customization_extra = false;
    }
    
    
    // If we can read the temporary file
    if (($handle = fopen(sys_get_temp_dir() . '/import.csv', "r")) !== FALSE)
    {
        // While we have lines in the file to read
        while (($csv_line = fgetcsv($handle)) !== FALSE)
        {
            // If we can import the first line or this is not the first line
            if (isset($_POST['import_first']) || $first_line == false)
            {
                // Get the category id or add it if it does not exist
                $category_id = get_or_add_id_value("category", $mappings, $csv_line);

                // Get the team id or add it if it does not exist
                $team_names = core_get_mapping_value("risks_", "team", $mappings, $csv_line);
                $team_names = explode(",", $team_names);
                $team_ids = [];
                foreach($team_names as $team_name){
                    $db_team_id = get_value_by_name("team", $team_name);
                    if(!$db_team_id){
                        $db_team_id = add_name("team", $team_name, strlen($team_name));
                    }
                    $team_ids[] = $db_team_id;
                    
                }
                $team_id = implode(",", $team_ids);

                // Get the additional_stakeholder ids 
                $additional_stakeholder_names = core_get_mapping_value("risks_", "additional_stakeholders", $mappings, $csv_line);
                $additional_stakeholder_names = explode(",", $additional_stakeholder_names);
                $additional_stakeholder_ids = [];
                foreach($additional_stakeholder_names as $additional_stakeholder_name){
                    $additional_stakeholder_id = get_value_by_name("user", $additional_stakeholder_name);
                    if($additional_stakeholder_id){
                        $additional_stakeholder_ids[] = $additional_stakeholder_id;
                    }
                }
                $additional_stakeholder_ids = implode(",", $additional_stakeholder_ids);

                // Get the technology id or add it if it does not exist
                $technology_names = core_get_mapping_value("risks_", "technology", $mappings, $csv_line);
                $technology_names = explode(",", $technology_names);
                $technology_ids = [];
                foreach($technology_names as $technology_name){
                    $db_technology_id = get_value_by_name("technology", $technology_name);
                    if(!$db_technology_id){
                        $db_technology_id = add_name("technology", $technology_name, strlen($technology_name));
                    }
                    $technology_ids[] = $db_technology_id;
                }
                $technology_id = implode(",", $technology_ids);
                
                // Get the location id or add it if it does not exist
                $location_id = get_or_add_id_values("location", $mappings, $csv_line, ";");

                // Get the source id or add it if it does not exist
                $source_id = get_or_add_id_value("source", $mappings, $csv_line);

                // Get the control regulation id or add it if it does not exist
                $regulation_id = get_or_add_id_value("regulation", $mappings, $csv_line);

                // Get the subject
                $subject = core_get_mapping_value("risks_", "subject", $mappings, $csv_line);

                /*****************
                 *** ADD RISK ****
                 *****************/
                 
                // If the subject is not null (we don't want to add risks without a subject)
                if (!is_null($subject))
                {
                    // Get the risk values for the risk
                    $risk_id = core_get_mapping_value("risks_", "id", $mappings, $csv_line);
                    $status = core_get_mapping_value("risks_", "status", $mappings, $csv_line);
                    $reference_id = core_get_mapping_value("risks_", "reference_id", $mappings, $csv_line);
                    $control_number = core_get_mapping_value("risks_", "control_number", $mappings, $csv_line);
                    $owner_id = core_get_or_add_user("owner", $mappings, $csv_line);
                    $manager_id = core_get_or_add_user("manager", $mappings, $csv_line);
                    $assessment = core_get_mapping_value("risks_", "assessment", $mappings, $csv_line);
                    $notes = core_get_mapping_value("risks_", "notes", $mappings, $csv_line);
                    $project_id = get_or_add_id_value("projects", $mappings, $csv_line);
                    $submission_date = core_get_mapping_value("risks_", "submission_date", $mappings, $csv_line);
                    $submitted_by = core_get_mapping_value("risks_", "submitted_by", $mappings, $csv_line);
                    $submitted_by_id = get_user_value_from_name_or_id($submitted_by);

                    // If Risk ID exists, crate a new risk
                    if(!$risk_id)
                    {
                        // Set null values to default
                        if (is_null($status)) $status = "New";
                        if (is_null($reference_id)) $reference_id = "";
                        if (is_null($regulation_id) || $regulation_id == "") $regulation_id = "0";
                        if (is_null($control_number)) $control_number = "";
                        if (is_null($location_id) || $location_id == "") $location_id = "0";
                        if (is_null($source_id) || $source_id == "") $source_id = "0";
                        if (is_null($category_id) || $category_id == "") $category_id = "0";
                        if (is_null($team_id) || $team_id == "") $team_id = "";
                        if (is_null($technology_id) || $technology_id == "") $technology_id = "";
                        if (is_null($assessment)) $assessment = "";
                        if (is_null($notes)) $notes = "";
                        if (is_null($project_id)) $project_id = 0;
                        if (is_null($submission_date)) $submission_date = false;
                   
                        // Submit risk and get back the id
                        $last_insert_id = submit_risk($status, $subject, $reference_id, $regulation_id, $control_number, $location_id, $source_id, $category_id, $team_id, $technology_id, $owner_id, $manager_id, $assessment, $notes, $project_id, $submitted_by_id, $submission_date, $additional_stakeholder_ids);
                        $risk_id = (int)$last_insert_id + 1000;
                    }
                    else
                    {
                        // Check if risk ID exists
                        if(check_risk_id($risk_id))
                        {
                            // Update risk by risk ID
                            $updated_count = update_risk_from_import($risk_id, $status, $subject, $reference_id, $regulation_id, $control_number, $location_id, $source_id, $category_id, $team_id, $technology_id, $owner_id, $manager_id, $assessment, $notes, $project_id, $submitted_by_id, $submission_date);
                        }
                        // Check if risk ID doesn't exist
                        else
                        {
                            echo "<div style='color: red'>".$escaper->escapeHtml(_lang("RiskIDNoEXitFailedToUpdate", ["risk_id" => (int)$risk_id]))."</div>\n";
                            continue;
                        }
                    }

                    $risks_tags = core_get_mapping_value("risks_", "tags", $mappings, $csv_line);
                    if ($risks_tags) {
                        updateTagsOfType($risk_id-1000, 'risk', array_map('trim', explode(',', $risks_tags)));
                    }

                    // If the status is Closed
                    if ($status == "Closed")
                    {
                        $user_id = $_SESSION['uid'];
                        $close_reason = 0;
                        $note = "";
                        $closed_date = core_get_mapping_value("closed_", "date", $mappings, $csv_line);
                        
                        close_risk($risk_id, $user_id, $status, $close_reason, $note, $closed_date);
                    }
                    
                    /************* Save mitigation *****************/
                        // Get mitigation
                        $mitigation_cost = core_get_mapping_value("mitigations_", "cost", $mappings, $csv_line);
                        // convert asset ranage to asset id
                        $mitigation_cost_id = get_asset_id_by_value($mitigation_cost);
                        
                        $mitigation_owner = core_get_mapping_value("mitigations_", "owner", $mappings, $csv_line);
                        $mitigation_owner_id = get_value_by_name("user", $mitigation_owner);
                        
                        // Get the mitigation_team id or add it if it does not exist
                        $mitigation_team_names = core_get_mapping_value("mitigations_", "team", $mappings, $csv_line);
                        $mitigation_team_names = explode(",", $mitigation_team_names);
                        $mitigation_team_ids = [];
                        foreach($mitigation_team_names as $mitigation_team_name){
                            $db_mitigation_team_id = get_value_by_name("team", $mitigation_team_name);
                            if(!$db_mitigation_team_id){
                                $db_mitigation_team_id = add_name("team", $mitigation_team_name, strlen($mitigation_team_name));
                            }
                            $mitigation_team_ids[] = $db_mitigation_team_id;
                            
                        }
                        $mitigation_team_id = implode(",", $mitigation_team_ids);

                        $mitigation_date = core_get_mapping_value("mitigations_", "date", $mappings, $csv_line);
                        
                        $planning_strategy = core_get_mapping_value("planning_", "strategy", $mappings, $csv_line);
                        $planning_strategy_id = get_value_by_name("planning_strategy", $planning_strategy);

                        $planning_date = core_get_mapping_value("planning_", "date", $mappings, $csv_line);
                        
                        $mitigation_effort = core_get_mapping_value("mitigations_", "effort", $mappings, $csv_line);
                        $mitigation_effort_id = get_value_by_name("mitigation_effort", $mitigation_effort);
                        
                        $mitigation_percent = (int)core_get_mapping_value("mitigations_", "percent", $mappings, $csv_line);
                        $mitigation_percent = ($mitigation_percent >= 0 && $mitigation_percent <= 100) ? $mitigation_percent : 0;
                        
                        $current_solution = core_get_mapping_value("current_", "solution", $mappings, $csv_line);
                        $security_requirements = core_get_mapping_value("security_requirements", "", $mappings, $csv_line);
                        $security_recommendations = core_get_mapping_value("security_recommendations", "", $mappings, $csv_line);

                        $mitigated_by = core_get_mapping_value("mitigated_by", "", $mappings, $csv_line);
                        $mitigated_by_id = get_user_value_from_name_or_id($mitigated_by);

                        
//                        $status = "Mitigation Planned";
                        if($mitigation_cost || $mitigation_owner || $mitigation_team_id || $mitigation_date || $planning_strategy || $planning_date || $mitigation_effort || $mitigation_percent || $current_solution || $security_requirements || $security_recommendations){
                            
                            $planning_date = $planning_date ? date(get_default_date_format(), strtotime($planning_date)) : "";
                            
                            // If risk created.
                            if(isset($last_insert_id) || !($mitigation = get_mitigation_by_id($risk_id))){
                                $post = array(
                                    'planning_strategy' => $planning_strategy_id,
                                    'planning_date' => $planning_date,
                                    'mitigation_effort' => $mitigation_effort_id,
                                    'mitigation_percent' => $mitigation_percent,
                                    'mitigation_cost' => $mitigation_cost_id,
                                    'mitigation_owner' => $mitigation_owner_id,
                                    'mitigation_team' => $mitigation_team_id,
                                    'current_solution' => $current_solution,
                                    'security_requirements' => $security_requirements,
                                    'security_recommendations' => $security_recommendations,
                                    'mitigation_date' =>  $mitigation_date ? date(get_default_datetime_format(), strtotime($mitigation_date)) : date(get_default_datetime_format()),
                                );
                                submit_mitigation($risk_id, $status, $post, $mitigated_by_id );
                            }
                            // If risk updated
                            else{
                                $post = $mitigation[0];
                                is_null($planning_strategy) || ($post['planning_strategy'] = $planning_strategy_id);
                                is_null($planning_date) || ($post['planning_date'] = $planning_date);
                                is_null($mitigation_effort) || ($post['mitigation_effort'] = $mitigation_effort_id);
                                is_null($mitigation_percent) || ($post['mitigation_percent'] = $mitigation_percent);
                                is_null($mitigation_cost) || ($post['mitigation_cost'] = $mitigation_cost_id);
                                is_null($mitigation_owner) || ($post['mitigation_owner'] = $mitigation_owner_id);
                                is_null($mitigation_team_id) || ($post['mitigation_team'] = $mitigation_team_id);
                                $post['current_solution'] = is_null($current_solution) ? try_decrypt($post['current_solution']) : $current_solution;
                                $post['security_requirements'] = is_null($security_requirements) ? try_decrypt($post['security_requirements']) : $security_requirements;
                                $post['security_recommendations'] = is_null($security_recommendations) ? try_decrypt($post['security_recommendations']) : $security_recommendations;
                                is_null($mitigation_date) || ($post['mitigation_date'] = $mitigation_date);
                        
                                update_mitigation($risk_id, $post);
                            }
                        }
                    /************* End saving mitigation *****************/

                    /************ Save affected assets *************/
                        $names = core_get_mapping_value("risks_", "assets", $mappings, $csv_line);
                        if($names){
                            import_assets_asset_groups_for_type($risk_id - 1000, $names, 'risk');
                        }
                    /************ End saving assets *************/

                    /************ Save riviews *************/
                        $review_id = 0;
                        if($status == "Mgmt Reviewed"){
                            $reviewer = core_get_mapping_value("reviews_", "reviewer", $mappings, $csv_line);
                            if(!is_numeric($reviewer) ||  (intval($reviewer) != $reviewer)){
                                $reviewer = get_value_by_name("user", $reviewer);
                            }

                            $submission_date = core_get_mapping_value("reviews_", "submission_date", $mappings, $csv_line);

                            $review = core_get_mapping_value("reviews_", "review", $mappings, $csv_line);
                            if(!is_numeric($review) ||  (intval($review) != $review)){
                                $review = get_value_by_name("review", $review);
                            }
                            
                            $next_step = core_get_mapping_value("reviews_", "next_step", $mappings, $csv_line);
                            if(!is_numeric($next_step) ||  (intval($next_step) != $next_step)){
                                $next_step = get_value_by_name("next_step", $next_step);
                            }
                            
                            $comments = core_get_mapping_value("reviews_", "comments", $mappings, $csv_line);
                            
                            // Date format is Y-m-d
                            $next_review = core_get_mapping_value("reviews_", "next_review", $mappings, $csv_line);
                            
                            if (!validate_date($next_review, 'Y-m-d')) {
                                $next_review = "0000-00-00";
                            }

                            if(!is_null($review) || !is_null($next_step) || !is_null($reviewer) || !is_null($next_review)){
                                $review_id = submit_management_review($risk_id, $status, $review, $next_step, $reviewer, $comments, $next_review, false, $submission_date);
                            }
                            // if(!is_null($review) || !is_null($next_step) || !is_null($reviewer) || !is_null($comments) || !is_null($next_review)){
                            //     submit_management_review($risk_id, $status, $review, $next_step, $reviewer, $comments, $next_review, false, $submission_date);
                            // }
                        }

                    /************ End saving reviews *************/

                    
                    /************ Save risk scoring *************/
                    // Get the risk scoring method
                    $scoring_method = core_get_mapping_value("riskscoring_", "scoring_method", $mappings, $csv_line);

                    // Get the scoring method id
                    $scoring_method_id = get_value_by_name("scoring_methods", $scoring_method);


                            // Classic Risk Scoring Inputs
                            $CLASSIClikelihood = core_get_mapping_value("riskscoring_", "CLASSIC_likelihood", $mappings, $csv_line);
                            $CLASSIClikelihood = (int) get_value_by_name('likelihood', $CLASSIClikelihood);

                            $CLASSICimpact = core_get_mapping_value("riskscoring_", "CLASSIC_impact", $mappings, $csv_line);
                            $CLASSICimpact = (int) get_value_by_name('impact', $CLASSICimpact);
    
                            // CVSS Risk Scoring Inputs
                            $CVSSAccessVector = core_get_mapping_value("riskscoring_", "CVSS_AccessVector", $mappings, $csv_line);
                            $CVSSAccessComplexity = core_get_mapping_value("riskscoring_", "CVSS_AccessComplexity", $mappings, $csv_line);
                            $CVSSAuthentication = core_get_mapping_value("riskscoring_", "CVSS_Authentication", $mappings, $csv_line);
                            $CVSSConfImpact = core_get_mapping_value("riskscoring_", "CVSS_ConfImpact", $mappings, $csv_line);
                            $CVSSIntegImpact = core_get_mapping_value("riskscoring_", "CVSS_IntegImpact", $mappings, $csv_line);
                            $CVSSAvailImpact = core_get_mapping_value("riskscoring_", "CVSS_AvailImpact", $mappings, $csv_line);
                            $CVSSExploitability = core_get_mapping_value("riskscoring_", "CVSS_Exploitability", $mappings, $csv_line);
                            $CVSSRemediationLevel = core_get_mapping_value("riskscoring_", "CVSS_RemediationLevel", $mappings, $csv_line);
                            $CVSSReportConfidence = core_get_mapping_value("riskscoring_", "CVSS_ReportConfidence", $mappings, $csv_line);
                            $CVSSCollateralDamagePotential = core_get_mapping_value("riskscoring_", "CVSS_CollateralDamagePotential", $mappings, $csv_line);
                            $CVSSTargetDistribution = core_get_mapping_value("riskscoring_", "CVSS_TargetDistribution", $mappings, $csv_line);
                            $CVSSConfidentialityRequirement = core_get_mapping_value("riskscoring_", "CVSS_ConfidentialityRequirement", $mappings, $csv_line);
                            $CVSSIntegrityRequirement = core_get_mapping_value("riskscoring_", "CVSS_IntegrityRequirement", $mappings, $csv_line);
                            $CVSSAvailabilityRequirement = core_get_mapping_value("riskscoring_", "CVSS_AvailabilityRequirement", $mappings, $csv_line);

                            // DREAD Risk Scoring Inputs
                            $DREADDamage = (int) core_get_mapping_value("riskscoring_", "DREAD_DamagePotential", $mappings, $csv_line);
                            $DREADReproducibility = (int) core_get_mapping_value("riskscoring_", "DREAD_Reproducibility", $mappings, $csv_line);
                            $DREADExploitability = (int) core_get_mapping_value("riskscoring_", "DREAD_Exploitability", $mappings, $csv_line);
                            $DREADAffectedUsers = (int) core_get_mapping_value("riskscoring_", "DREAD_AffectedUsers", $mappings, $csv_line);
                            $DREADDiscoverability = (int) core_get_mapping_value("riskscoring_", "DREAD_Discoverability", $mappings, $csv_line);

                            // OWASP Risk Scoring Inputs
                            $OWASPSkillLevel = (int) core_get_mapping_value("riskscoring_", "OWASP_SkillLevel", $mappings, $csv_line);
                            $OWASPMotive = (int) core_get_mapping_value("riskscoring_", "OWASP_Motive", $mappings, $csv_line);
                            $OWASPOpportunity = (int) core_get_mapping_value("riskscoring_", "OWASP_Opportunity", $mappings, $csv_line);
                            $OWASPSize = (int) core_get_mapping_value("riskscoring_", "OWASP_Size", $mappings, $csv_line);
                            $OWASPEaseOfDiscovery = (int) core_get_mapping_value("riskscoring_", "OWASP_EaseOfDiscovery", $mappings, $csv_line);
                            $OWASPEaseOfExploit = (int) core_get_mapping_value("riskscoring_", "OWASP_EaseOfExploit", $mappings, $csv_line);
                            $OWASPAwareness = (int) core_get_mapping_value("riskscoring_", "OWASP_Awareness", $mappings, $csv_line);
                            $OWASPIntrusionDetection = (int) core_get_mapping_value("riskscoring_", "OWASP_IntrusionDetection", $mappings, $csv_line);
                            $OWASPLossOfConfidentiality = (int) core_get_mapping_value("riskscoring_", "OWASP_LossOfConfidentiality", $mappings, $csv_line);
                            $OWASPLossOfIntegrity = (int) core_get_mapping_value("riskscoring_", "OWASP_LossOfIntegrity", $mappings, $csv_line);
                            $OWASPLossOfAvailability = (int) core_get_mapping_value("riskscoring_", "OWASP_LossOfAvailability", $mappings, $csv_line);
                            $OWASPLossOfAccountability = (int) core_get_mapping_value("riskscoring_", "OWASP_LossOfAccountability", $mappings, $csv_line);
                            $OWASPFinancialDamage = (int) core_get_mapping_value("riskscoring_", "OWASP_FinancialDamage", $mappings, $csv_line);
                            $OWASPReputationDamage = (int) core_get_mapping_value("riskscoring_", "OWASP_ReputationDamage", $mappings, $csv_line);
                            $OWASPNonCompliance = (int) core_get_mapping_value("riskscoring_", "OWASP_NonCompliance", $mappings, $csv_line);
                            $OWASPPrivacyViolation = (int) core_get_mapping_value("riskscoring_", "OWASP_PrivacyViolation", $mappings, $csv_line);

                            // Custom Risk Scoring
                            $custom = (float) core_get_mapping_value("riskscoring_", "Custom", $mappings, $csv_line);
                            
                            // Contributing Risk Scoring
                            $ContributingLikelihoodName = core_get_mapping_value("riskscoring_", "Contributing_Likelihood", $mappings, $csv_line);
                            $ContributingLikelihood = (int) get_value_by_name('likelihood', $ContributingLikelihoodName);
                            $Contributing_Subjects_Impacts = core_get_mapping_value("riskscoring_", "Contributing_Subjects_Impacts", $mappings, $csv_line);
                            $ContributingImpacts = get_contributing_impacts_by_subjectimpact_names($Contributing_Subjects_Impacts);

                                    // Set null values to default
                                    if (is_null($CLASSIClikelihood)) $CLASSIClikelihood = "5";
                                    if (is_null($CLASSICimpact)) $CLASSICimpact = "5";
                                    if (is_null($CVSSAccessVector)) $CVSSAccessVector = "N";
                                    if (is_null($CVSSAccessComplexity)) $CVSSAccessComplexity = "L";
                                    if (is_null($CVSSAuthentication)) $CVSSAuthentication = "N";
                                    if (is_null($CVSSConfImpact)) $CVSSConfImpact = "C";
                                    if (is_null($CVSSIntegImpact)) $CVSSIntegImpact = "C";
                                    if (is_null($CVSSAvailImpact)) $CVSSAvailImpact = "C";
                                    if (is_null($CVSSExploitability)) $CVSSExploitability = "ND";
                                    if (is_null($CVSSRemediationLevel)) $CVSSRemediationLevel = "ND";
                                    if (is_null($CVSSReportConfidence)) $CVSSReportConfidence = "ND";
                                    if (is_null($CVSSCollateralDamagePotential)) $CVSSCollateralDamagePotential = "ND";
                                    if (is_null($CVSSTargetDistribution)) $CVSSTargetDistribution = "ND";
                                    if (is_null($CVSSConfidentialityRequirement)) $CVSSConfidentialityRequirement = "ND";
                                    if (is_null($CVSSIntegrityRequirement)) $CVSSIntegrityRequirement = "ND";
                                    if (is_null($CVSSAvailabilityRequirement)) $CVSSAvailabilityRequirement = "ND";
                                    if (is_null($DREADDamage)) $DREADDamage = "10";
                                    if (is_null($DREADReproducibility)) $DREADReproducibility = "10";
                                    if (is_null($DREADExploitability)) $DREADExploitability = "10";
                                    if (is_null($DREADAffectedUsers)) $DREADAffectedUsers = "10";
                                    if (is_null($DREADDiscoverability)) $DREADDiscoverability = "10";
                                    if (is_null($OWASPSkillLevel)) $OWASPSkillLevel = "10";
                                    if (is_null($OWASPMotive)) $OWASPMotive = "10";
                                    if (is_null($OWASPOpportunity)) $OWASPOpportunity = "10";
                                    if (is_null($OWASPSize)) $OWASPSize = "10";
                                    if (is_null($OWASPEaseOfDiscovery)) $OWASPEaseOfDiscovery = "10";
                                    if (is_null($OWASPEaseOfExploit)) $OWASPEaseOfExploit = "10";
                                    if (is_null($OWASPAwareness)) $OWASPAwareness = "10";
                                    if (is_null($OWASPIntrusionDetection)) $OWASPIntrusionDetection = "10";
                                    if (is_null($OWASPLossOfConfidentiality)) $OWASPLossOfConfidentiality = "10";
                                    if (is_null($OWASPLossOfIntegrity)) $OWASPLossOfIntegrity = "10";
                                    if (is_null($OWASPLossOfAvailability)) $OWASPLossOfAvailability = "10";
                                    if (is_null($OWASPLossOfAccountability)) $OWASPLossOfAccountability = "10";
                                    if (is_null($OWASPFinancialDamage)) $OWASPFinancialDamage = "10";
                                    if (is_null($OWASPReputationDamage)) $OWASPReputationDamage = "10";
                                    if (is_null($OWASPNonCompliance)) $OWASPNonCompliance = "10";
                                    if (is_null($OWASPPrivacyViolation)) $OWASPPrivacyViolation = "10";
                                    if (is_null($custom)) $custom = "";
                    
                    if(isset($last_insert_id)){
                        // If the scoring method is null
                        if (is_null($scoring_method_id))
                        {
                            // Set the scoring method to Classic
                            $scoring_method_id = 1;
                        }
                        // Submit risk scoring
                        submit_risk_scoring($last_insert_id, $scoring_method_id, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts);
                    }
                    else{
                        update_risk_scoring($risk_id, $scoring_method_id, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom, $ContributingLikelihood, $ContributingImpacts);
                    }
                    /************ End saving risk scoring *************/
                    // Importing customization fields if customization extra is enabled
                    if($customization_extra)
                    {
                        foreach($active_fields as $active_field)
                        {
                            if($active_field['is_basic'] == 0 && in_array("custom_field_{$active_field['id']}", $mappings))
                            {
                                $value = get_mapping_custom_field_value($active_field, $mappings, $csv_line);
                                
                                save_risk_custom_field_value_from_importexport($risk_id, $active_field['id'], $value, $review_id);
                            }
                        }
                    }
                    
                    // If the submission date is not null
                    if (!is_null($submission_date))
                    {
                        // Set the submission date for the risk
                        // set_risk_submission_date($last_insert_id, $submission_date);
                    }

                    if(isset($last_insert_id)){
                        echo "Submitted subject \"" . $escaper->escapeHtml($subject) . "\" as risk ID " . $escaper->escapeHtml($risk_id) . "<br />\n";
                    }else{
                        echo "Updated subject \"" . $escaper->escapeHtml($subject) . "\" as risk ID " . $escaper->escapeHtml($risk_id) . "<br />\n";
                    }
                }
                // If subject is null
                else
                {
                    set_alert(true, "bad", $escaper->escapeHtml($lang['SubjectRequired']));
                    refresh();
                    break;
                }
            }
            // Otherwise this is the first line
            else
            {
                // Set the first line to false
                $first_line = false;
            }
        }
        
        // If the encryption extra is enabled, updates order_by_subject
        if (encryption_extra() && isset($subject) && !is_null($subject))
        {
            // Load the extra
            require_once(realpath(__DIR__ . '/../encryption/index.php'));

            create_subject_order($_SESSION['encrypted_pass']);
        }
        
    }

    // Close the temporary file
    fclose($handle);
    
}

/**********************************
 * FUNCTION: GET OR ADD ID VALUES *
 **********************************/
function get_or_add_id_values($type, $mappings, $csv_line, $separator=",")
{
    // Get the mapping value
    $names = core_get_mapping_value("risks_", $type, $mappings, $csv_line);
    $names = explode($separator, $names);
    $ids = [];
    foreach($names as $name){
        $name = trim($name);
        $id = get_value_by_name($type, $name);
        if(!$id){
            $id = add_name($type, $name, strlen($name));
        }
        $ids[] = $id;
    }
    $id_string = implode(",", $ids);

    return $id_string;
}

/*********************************
 * FUNCTION: GET OR ADD ID VALUE *
 *********************************/
function get_or_add_id_value($type, $mappings, $csv_line)
{
    // Get the mapping value
    $value = core_get_mapping_value("risks_", $type, $mappings, $csv_line);

    // if the value is emptry string, return 0
    if($value === "")
    {
        return 0;
    }
    // If the value is not null
    elseif (!is_null($value))
    {
        // We're getting regulation data from the frameworks table
        if ($type == "regulation")
            $type = "frameworks";

        // Search the corresponding table for the value
        $value_id = get_value_by_name($type, $value);

        // If the value id was found (is not null)
        if (!is_null($value_id))
        {
            // Return the value id
            return $value_id;
        }
        // Otherwise the value id was not found
        else
        {
            // Change the size depending on the type
            switch ($type)
            {
                case "category":
                    $size = 50;
                    break;
                case "team":
                    $size = 50;
                    break;
                case "technology":
                    $size = 50;
                    break;
                case "location":
                    $size = 100;
                    break;
                case "source":
                    $size = 50;
                    break;
                case "frameworks":
                    $size = 50;
                    break;
                default:
                    $size = null;
                    break;
            }

            // have to create frameworks differently
            if ($type == "frameworks")
                return add_framework($value, "");

            // Add the value
            $value_id = add_name($type, $value, $size);

            // Return the value id
            return $value_id;
        }
    }
}

/**************************************
 * FUNCTION: SET RISK SUBMISSION DATE *
 **************************************/
function set_risk_submission_date($risk_id, $submission_date)
{
    if (validate_date($submission_date))
    {
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("UPDATE `risks` SET `submission_date` = :submission_date WHERE id = :id");
        $stmt->bindParam(":submission_date", $submission_date, PDO::PARAM_STR, 19);
        $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);
    }
}

/***********************************
 * FUNCTION: IMPORT EXPORT VERSION *
 ***********************************/
function import_export_version()
{
    // Return the version
    return IMPORTEXPORT_EXTRA_VERSION;
}

/****************************************
 * FUNCTION: IMPORT ASSETS WITH MAPPING *
 ****************************************/
function import_assets_with_mapping($mappings)
{
    global $escaper, $lang;

    // Open the temporary file for reading
    ini_set('auto_detect_line_endings', true);

    // Check if customization extra is enabled
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../customization/index.php'));
        
        $active_fields = get_active_fields("asset");
        
        $customization_extra = true;
    }
    else
    {
        $customization_extra = false;
    }

    // Detect first line
    $first_line = true;
    
    // If we can read the temporary file
    if (($handle = fopen(sys_get_temp_dir() . '/import.csv', "r")) !== FALSE)
    {
        // While we have lines in the file to read
        while (($csv_line = fgetcsv($handle)) !== FALSE)
        {
            // If we can import the first line or this is not the first line
            if (isset($_POST['import_first']) || $first_line == false)
            {
                // Get the name
                $name = core_get_mapping_value("asset_", "name", $mappings, $csv_line);

                /*****************
                 *** ADD ASSET ***
                 *****************/
                // If the name is not null (we don't want to add assets without a name)
                if (!is_null($name))
                {
                    // Get the asset values
                    $ip         = core_get_mapping_value("asset_", "ip", $mappings, $csv_line);
                    $value      = core_get_mapping_value("asset_", "value", $mappings, $csv_line);
                    $location   = core_get_mapping_value("asset_", "location", $mappings, $csv_line);
                    $team       = core_get_mapping_value("asset_", "team", $mappings, $csv_line);
                    $details    = core_get_mapping_value("asset_", "details", $mappings, $csv_line);
                    $tags       = core_get_mapping_value("asset_", "tags", $mappings, $csv_line);
                    $verified   = trim(core_get_mapping_value("asset_", "verified", $mappings, $csv_line));

                    if($location && !($location_id = get_value_by_name("location", $location))){
                        $location_id = add_name("location", $location, 100);
                    }

                    $team_names = explode(",", $team);
                    $team_ids = [];
                    foreach($team_names as $team_name){
                        $db_team_id = get_value_by_name("team", $team_name);
                        if(!$db_team_id){
                            $db_team_id = add_name("team", $team_name, strlen($team_name));
                        }
                        $team_ids[] = $db_team_id;
                        
                    }
                    $team_id = implode(",", $team_ids);

                    $value = get_asset_id_by_value($value);

                    if ($tags) {
                        $tags = array_map('trim', explode(",", $tags));
                    } else {
                        $tags = [];
                    }

                    // Set null values to default
                    if (is_null($ip)) $ip = "";
                    if (empty($location_id)) $location_id = 0;
                    if (is_null($details)) $details = "";
                    //Can't use empty here as 0 is a valid value in this case but empty would trigger on it
                    if (is_null($verified) || $verified == "") {
                        $verified = 1;
                    } else {
                        if (!is_string($verified)) {
                            $verified  = (bool)$verified;
                        } else {
                            switch (strtolower($verified)) {
                                case '1':
                                case 'true':
                                case 'on':
                                case 'yes':
                                case 'y':
                                    $verified = 1;
                                    break;
                                default:
                                    $verified = 0;
                            }
                        }
                    }

                    $exists = asset_exists($name);

                    $result = import_asset($ip, $name, $value, $location_id, $team_id, $details, $tags, $verified);
                    
                    // If success for importing asset and customization extra is enabled, import custom fields
                    if($result && $customization_extra)
                    {
                        $asset_id = $result;
                        
                        foreach($active_fields as $active_field)
                        {
                            if($active_field['is_basic'] == 0 && in_array("custom_field_{$active_field['id']}", $mappings))
                            {
                                $value = get_mapping_custom_field_value($active_field, $mappings, $csv_line);
                                save_asset_custom_field_value_from_importexport($asset_id, $active_field['id'], $value);
                            }
                        }
                    }

                    if (!$exists) {
                        // It was an add
                        if($result)
                        {
                            echo $escaper->escapeHtml(_lang("ImportAssetAddSucceeded", [
                                'verified_or_unverified' => ($verified?$lang['Verified']:$lang['Unverified']),
                                'asset_name' => $name, 
                                'asset_ip' => $ip, 
                                'asset_value' => get_asset_value_by_id($value)
                            ])) . "<br />\n";
                        }
                        else
                        {
                            echo $escaper->escapeHtml(_lang("ImportAssetAddFailed", [
                                'verified_or_unverified' => ($verified?$lang['Verified']:$lang['Unverified']),
                                'asset_name' => $name, 
                                'asset_ip' => $ip, 
                                'asset_value' => get_asset_value_by_id($value)
                            ])) . "<br />\n";
                        }
                    } 
                    else {
                        // Updated an existing asset from the import
                        if($result === "noop") {
                            echo $escaper->escapeHtml(_lang("NoOperationRequiredOnAsset", ['asset_name' => $name])) . "<br />\n";
                        }
                        elseif($result)
                        {
                            echo $escaper->escapeHtml(_lang("ImportAssetUpdateSucceeded", [
                                'verified_or_unverified' => ($verified?$lang['Verified']:$lang['Unverified']),
                                'asset_name' => $name, 
                                'asset_ip' => $ip, 
                                'asset_value' => get_asset_value_by_id($value)
                            ])) . "<br />\n";
                        }
                        else
                        {
                            echo $escaper->escapeHtml(_lang("ImportAssetUpdateFailed", [
                                'verified_or_unverified' => ($verified?$lang['Verified']:$lang['Unverified']),
                                'asset_name' => $name, 
                                'asset_ip' => $ip, 
                                'asset_value' => get_asset_value_by_id($value)
                            ])) . "<br />\n";
                        }
                    }
                }
            }
            // Otherwise this is the first line
            else
            {
                // Set the first line to false
                $first_line = false;
            }
        }
    }

    // Close the temporary file
    fclose($handle);
}

/**********************************************
 * FUNCTION: IMPORT ASSET GROUPS WITH MAPPING *
 **********************************************/
function import_asset_groups_with_mapping($mappings)
{
    global $escaper, $lang;

    // Open the temporary file for reading
    ini_set('auto_detect_line_endings', true);

    // Detect first line
    $first_line = true;
    
    // If we can read the temporary file
    if (($handle = fopen(sys_get_temp_dir() . '/import.csv', "r")) !== FALSE)
    {
        // Get the existing assets once, before the loop
        $existing_assets = array_map(function($item) {
            return array('id' => $item['id'], 'name' => try_decrypt($item['name']));
        }, get_entered_assets());

        // While we have lines in the file to read
        while (($csv_line = fgetcsv($handle)) !== FALSE)
        {
            // If we can import the first line or this is not the first line
            if (isset($_POST['import_first']) || $first_line == false)
            {
                $id = core_get_mapping_value("asset_group_", "id", $mappings, $csv_line);
                $name = trim(core_get_mapping_value("asset_group_", "name", $mappings, $csv_line));

                if (!is_null($name)) {
                    $asset_names = core_get_mapping_value("asset_group_", "assets", $mappings, $csv_line);
                    $asset_ids = [];

                    // Get the asset ids and/or create those assets that are not existing
                    if (!is_null($asset_names)) {

                        $asset_names = explode(',', $asset_names);

                        // Go through the list of asset names
                        foreach($asset_names as $asset_name) {
                            $asset_name = trim($asset_name);

                            if ($asset_name) {

                                $asset_id = false;

                                // check if the asset exists
                                foreach($existing_assets as $existing_asset) {
                                    if ($existing_asset['name'] === $asset_name){
                                        $asset_id = $existing_asset['id'];
                                    }
                                }

                                // if not, then create it
                                if (!$asset_id) {
                                    $asset_id = add_asset('', $asset_name);

                                    // If the asset creation fail we still try to continue the import
                                    if (!$asset_id)
                                        continue;

                                    // Don't forget to add our newly created asset to the list of existing assets
                                    $existing_assets[] = array('id' => $asset_id, 'name' => $asset_name);
                                }

                                // gather the asset ids
                                $asset_ids[] = $asset_id;
                            }
                        }
                    }
                    
                    $name_by_id = get_name_by_value('asset_groups', $id, false, true);
                    $id_by_name = get_value_by_name('asset_groups', $name);

                    if (!$name_by_id)
                        // We can't find the name for the ID, so we treat it as a "new" asset group
                        $id = false;

                    if (!$id) {
                        // It's a create
                        if ($id_by_name) {
                            // Name is already taken
                            echo $escaper->escapeHtml(_lang("ImportAssetGroupAddNameTaken", array('asset_group_name' => $name))) . "<br />\n";
                        } else {
                            if(create_asset_group($name, $asset_ids)) {
                                echo $escaper->escapeHtml(_lang("ImportAssetGroupAddSucceeded", array('asset_group_name' => $name))) . "<br />\n";
                            } else {
                                echo $escaper->escapeHtml(_lang("ImportAssetGroupAddFailed", array('asset_group_name' => $name))) . "<br />\n";
                            }
                        }
                    } else {
                        //It's an update
                        if ($id_by_name != $id && $id_by_name !== null) {
                            // Name is already taken
                            echo $escaper->escapeHtml(_lang("ImportAssetGroupUpdateNameTaken", array('asset_group_name' => $name))) . "<br />\n";
                        } else {
                            // Name is ok, we can update
                            // Check if the asset group is exactly what the input is
                            $db = db_open();

                            $sql = "
                                SELECT
                                    `a`.`id`
                                FROM
                                    `assets` a
                                    INNER JOIN
                                        `assets_asset_groups` aag
                                    ON
                                        `aag`.`asset_id` = `a`.`id` AND `aag`.`asset_group_id` = :id;
                            ";

                            $stmt = $db->prepare($sql);
                            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                            $stmt->execute();
                            $current_asset_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');

                            // if the list of current ids and those we want to import are identical
                            // we skip this row and get to the next
                            if ($name_by_id == $name && array_diff($current_asset_ids, $asset_ids) == array_diff($asset_ids, $current_asset_ids)) {
                                echo $escaper->escapeHtml(_lang("ImportAssetGroupNoop", array('asset_group_name' => $name))) . "<br />\n";
                            } else {
                                // update the group with the list of assets
                                update_asset_group($id, $name, $asset_ids);

                                // Double-check if the import was successful
                                $stmt = $db->prepare($sql);
                                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                                $stmt->execute();
                                $current_asset_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');

                                // if the list of current ids and those we want to import are identical
                                // we return that everything is ok
                                if (array_diff($current_asset_ids, $asset_ids) == array_diff($asset_ids, $current_asset_ids)){
                                    echo $escaper->escapeHtml(_lang("ImportAssetGroupUpdateSucceeded", array('asset_group_name' => $name))) . "<br />\n";
                                } else {
                                    // If they do not match, then something awful happened
                                    echo $escaper->escapeHtml(_lang("ImportAssetGroupUpdateFailed", array('asset_group_name' => $name))) . "<br />\n";
                                }
                            }
                        }
                    }
                }
            }
            // Otherwise this is the first line
            else
            {
                // Set the first line to false
                $first_line = false;
            }
        }
    }

    // Close the temporary file
    fclose($handle);
}


/*****************************
 * FUNCTION: DOWNLOAD TO XLS *
 * if $group_value is NNUL, means download all button
 *                 is NOT NULL, means download button by group
 *****************************/
function download_risks_by_table($status, $group, $sort, $affected_assets_filter, $tags_filter, $locations_filter, $download_group_value=NULL, $column_id=true, $column_status=false, $column_subject=true, $column_reference_id=false, $column_regulation=false, $column_control_number=false, $column_location=false, $column_source=false, $column_category=false, $column_team=false, $column_additional_stakeholders=false, $column_technology=false, $column_owner=false, $column_manager=false, $column_submitted_by=false, $column_scoring_method=false, $column_calculated_risk=true, $column_residual_risk=true, $column_submission_date=true, $column_review_date=false, $column_project=false, $column_mitigation_planned=true, $column_management_review=true, $column_days_open=false, $column_next_review_date=false, $column_next_step=false, $column_affected_assets=false, $column_planning_strategy=false, $column_planning_date=false, $column_mitigation_effort=false, $column_mitigation_cost=false, $column_mitigation_owner=false, $column_mitigation_team=false, $column_mitigation_accepted=false, $column_mitigation_date=false, $column_mitigation_controls=false, $column_risk_assessment=false, $column_additional_notes=false, $column_current_solution=false, $column_security_recommendations=false, $column_security_requirements=false, $column_risk_tags=false, $column_closure_date=false, $column_custom_values=[]){
    global $lang;
    global $escaper;

    
    $risks = get_risks_only_dynamic($status, $sort, 0, $affected_assets_filter, $tags_filter, $locations_filter, $rowCount, 0, -1);

    // Get group name from $group
    list($group_name, $order_query) = get_group_name_for_dynamic_risk($group, "");

    $xlsHeader = array();
    $xlsRows = array();

    $xlsRow = array();
    if($column_id == true) array_push($xlsRow, $escaper->escapeHtml($lang['ID']));
    if($column_status == true) array_push($xlsRow, $escaper->escapeHtml($lang['Status']));
    if($column_subject == true) array_push($xlsRow, $escaper->escapeHtml($lang['Subject']));
    if($column_reference_id == true) array_push($xlsRow, $escaper->escapeHtml($lang['ExternalReferenceId']));
    if($column_regulation == true) array_push($xlsRow, $escaper->escapeHtml($lang['ControlRegulation']));
    if($column_control_number == true) array_push($xlsRow, $escaper->escapeHtml($lang['ControlNumber']));
    if($column_location == true) array_push($xlsRow, $escaper->escapeHtml($lang['SiteLocation']));
    if($column_source == true) array_push($xlsRow, $escaper->escapeHtml($lang['RiskSource']));
    if($column_category == true) array_push($xlsRow, $escaper->escapeHtml($lang['Category']));
    if($column_team == true) array_push($xlsRow, $escaper->escapeHtml($lang['Team']));
    if($column_additional_stakeholders == true) array_push($xlsRow, $escaper->escapeHtml($lang['AdditionalStakeholders']));
    if($column_technology == true) array_push($xlsRow, $escaper->escapeHtml($lang['Technology']));
    if($column_owner == true) array_push($xlsRow, $escaper->escapeHtml($lang['Owner']));
    if($column_manager == true) array_push($xlsRow, $escaper->escapeHtml($lang['OwnersManager']));
    if($column_submitted_by == true) array_push($xlsRow, $escaper->escapeHtml($lang['SubmittedBy']));
    if($column_scoring_method == true) array_push($xlsRow, $escaper->escapeHtml($lang['RiskScoringMethod']));
    if($column_calculated_risk == true) array_push($xlsRow, $escaper->escapeHtml($lang['InherentRisk']));
    if($column_residual_risk == true) array_push($xlsRow, $escaper->escapeHtml($lang['ResidualRisk']));
    if($column_submission_date == true) array_push($xlsRow, $escaper->escapeHtml($lang['DateSubmitted']));
    if($column_review_date == true) array_push($xlsRow, $escaper->escapeHtml($lang['ReviewDate']));
    if($column_project == true) array_push($xlsRow, $escaper->escapeHtml($lang['Project']));
    if($column_mitigation_planned == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationPlanned']));
    if($column_management_review == true) array_push($xlsRow, $escaper->escapeHtml($lang['ManagementReview']));
    if($column_days_open == true) array_push($xlsRow, $escaper->escapeHtml($lang['DaysOpen']));
    if($column_next_review_date == true) array_push($xlsRow, $escaper->escapeHtml($lang['NextReviewDate']));
    if($column_next_step == true) array_push($xlsRow, $escaper->escapeHtml($lang['NextStep']));
    if($column_affected_assets == true) array_push($xlsRow, $escaper->escapeHtml($lang['AffectedAssets']));
    if($column_risk_assessment == true) array_push($xlsRow, $escaper->escapeHtml($lang['RiskAssessment']));
    if($column_additional_notes == true) array_push($xlsRow, $escaper->escapeHtml($lang['AdditionalNotes']));
    if($column_current_solution == true) array_push($xlsRow, $escaper->escapeHtml($lang['CurrentSolution']));
    if($column_security_recommendations == true) array_push($xlsRow, $escaper->escapeHtml($lang['SecurityRecommendations']));
    if($column_security_requirements == true) array_push($xlsRow, $escaper->escapeHtml($lang['SecurityRequirements']));
    if($column_planning_strategy == true) array_push($xlsRow, $escaper->escapeHtml($lang['PlanningStrategy']));
    if($column_planning_date == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationPlanning']));
    if($column_mitigation_effort == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationEffort']));
    if($column_mitigation_cost == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationCost']));
    if($column_mitigation_owner == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationOwner']));
    if($column_mitigation_team == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationTeam']));
    if($column_mitigation_accepted == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationAccepted']));
    if($column_mitigation_date == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationDate']));
    if($column_mitigation_controls == true) array_push($xlsRow, $escaper->escapeHtml($lang['MitigationControls']));
    if($column_risk_tags == true) array_push($xlsRow, $escaper->escapeHtml($lang['Tags']));
    if($column_closure_date == true) array_push($xlsRow, $escaper->escapeHtml($lang['DateClosed']));
    
    // If customization extra is enabled, add custom fields
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../customization/index.php'));
        
        $active_fields = get_active_fields();
        foreach($active_fields as $active_field)
        {
            if($active_field['is_basic'] == 0 && in_array("custom_field_{$active_field['id']}", array_keys($column_custom_values)))
            {
                array_push($xlsRow, $escaper->escapeHtml($active_field['name']));
            }
        }
        
    }

    $xlsHeader = $xlsRow;

    // If the group name is none
    if ($group_name == "none")
    {
        
        $xlsRows[] = "header";
        $xlsRows[] = $xlsHeader;
    }
    else
    {
        $group_xlsRows = [];
    }    
    // If this is download by group value, set query
    if($group_name != "none" && $download_group_value !== NULL)
    {
        $group_field_name = "";
        if($group_name == "month_submitted"){
            if (!$download_group_value || stripos($download_group_value, "0000-00-00") !== false)
            {
                // Set the review date to empty
                $download_group_value = "";
            }
            else
            {
                $download_group_value = date('Y F', strtotime($download_group_value)); 
            }
        }else{
            switch($group_name){
                case "risk_level":
                    $download_group_value = get_risk_level_name($download_group_value);
                break;
            }
        }
    }

    $risk_levels = get_risk_levels();
    $review_levels = get_review_levels();

    // For each risk in the risks array
    foreach ($risks as $risk)
    {
        // We only need these for grouping, it's a waste of time to calculate the rest.
        // In case you add a new grouping add to this list
        // so the $group_value = ${$group_name}; expression can get its value        
        $status = $risk['status'];
        $scoring_method = get_scoring_method_name($risk['scoring_method']);
        $risk_level = get_risk_level_name((float)$risk['calculated_risk']);
        $location = $risk['location'];
        $source = $risk['source'];
        $category = $risk['category'];
        $team = $risk['team'];
        $technology = $risk['technology'];
        $owner = $risk['owner'];
        $manager = $risk['manager'];
        $regulation = try_decrypt($risk['regulation']);
        $project = try_decrypt($risk['project']);
        $next_step = $risk['next_step'];
        if (!$risk['submission_date'] || stripos($risk['submission_date'], "0000-00-00") !== false)
        {
            // Set the review date to empty
            $month_submitted = "";
        }
        else
        {
            $month_submitted = date('Y F', strtotime($risk['submission_date']));
        }
        // If this is download for one group table, skip for other group values
        if($group_name != "none" && $download_group_value !== NULL)
        {
            if(${$group_name} != $download_group_value)
            {
                continue;
            }
        }
        
        $xlsRow = get_risk_columns_for_download($risk, $risk_levels, $review_levels, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_additional_stakeholders, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_residual_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_planning_date, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_mitigation_accepted, $column_mitigation_date, $column_mitigation_controls, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements, $column_risk_tags, $column_closure_date, $column_custom_values);

        // If the group name is not none
        if ($group_name != "none")
        {
            $group_value = ${$group_name};
            
            switch($group_name){
                case "risk_level":
                    $group_value_from_db = $risk['calculated_risk'];
                break;
                case "month_submitted":
                    $group_value_from_db = $risk['submission_date'];
                break;
                default:
                    $group_value_from_db = $risk[$group_name];
                break;
            }
            
            // If the selected group value is empty
            if ($group_value == "")
            {
                // Current group is Unassigned
                $group_value = $lang['Unassigned'];
            }

            // If this group is first appearance, initiate group_xlsRows
            if (!in_array($group_value, array_keys($group_xlsRows)))
            {
                $group_xlsRows[$group_value] = [];

                $group_xlsRows[$group_value][] = "group-header";
                $group_xlsRows[$group_value][] = $escaper->escapeHtml($group_value);
                $group_xlsRows[$group_value][] = "header";
                $group_xlsRows[$group_value][] = $xlsHeader;
            }
            $group_xlsRows[$group_value][] = $xlsRow;
        }
        else
        {
            $xlsRows[] = $xlsRow;
        }
    }
    if ($group_name != "none")
    {
        foreach($group_xlsRows as $group_xlsRow)   
        {
            $xlsRows = array_merge($xlsRows, $group_xlsRow);
        }
    }
    
    /***********Export Excel**************/
    
    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $currentExcelRowIndex = 1;
    $columnCount = count($xlsHeader);

    // Style
    $centerStyle = array(
        'alignment' => array(
            'horizontal' => PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        )
    );

    for($i=0; $i<count($xlsRows) ; $i++){
        $xlsRow = $xlsRows[$i];
        if(!is_array($xlsRow)){
            if($xlsRow == "header"){
                
                // Add emptry row for each tables
                if($group_name == "none" && $currentExcelRowIndex != 1){
                    $currentExcelRowIndex++;
                }
                
                $xlsRow = $xlsRows[++$i];
                if(is_array($xlsRow)){
                    foreach($xlsRow as $columnIndex => $value){
                        $sheet->setCellValueByColumnAndRow(++$columnIndex, $currentExcelRowIndex, $value);
                    }
                }else{
                    $sheet->setCellValueByColumnAndRow(1, $currentExcelRowIndex, $xlsRow);
                }
                $currentExcelRowIndex++;
            }elseif($xlsRow == "group-header"){
                
                // Add emptry row for each tables
                if($currentExcelRowIndex != 1){
                    $currentExcelRowIndex++;
                }
                
                $xlsRow = $xlsRows[++$i];
                if(is_array($xlsRow)){
                    $sheet->setCellValueByColumnAndRow(++$columnIndex, $currentExcelRowIndex, $xlsRow[0]);
                }else{
                    $sheet->setCellValueByColumnAndRow(1, $currentExcelRowIndex, $xlsRow);
                }
                $sheet->mergeCellsByColumnAndRow(1, $currentExcelRowIndex, $columnCount, $currentExcelRowIndex);
                $sheet->getStyleByColumnAndRow(1, $currentExcelRowIndex, $columnCount, $currentExcelRowIndex)->applyFromArray($centerStyle);
                $currentExcelRowIndex++;
            }
        }else{
            foreach($xlsRow as $columnIndex => $value){
                $sheet->setCellValueByColumnAndRow(++$columnIndex, $currentExcelRowIndex, $value);
            }
            $currentExcelRowIndex++;
        }
    }
    
    $xlsName = "Dynamic Risk Report - ".date('Y-m-d H:i:s').'.xls';
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="'.$escaper->escapeUrl($xlsName).'"');
    header('Cache-Control: max-age=0');
    $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, "Xls");
    $writer->save('php://output');
    exit;

}

/***************************************
 * FUNCTION: GET RISK COLUMNS FOR DOWNLOAD*
 **************************************/
function get_risk_columns_for_download($risk, $risk_levels, $review_levels, $column_id, $column_status, $column_subject, $column_reference_id, $column_regulation, $column_control_number, $column_location, $column_source, $column_category, $column_team, $column_additional_stakeholders, $column_technology, $column_owner, $column_manager, $column_submitted_by, $column_scoring_method, $column_calculated_risk, $column_residual_risk, $column_submission_date, $column_review_date, $column_project, $column_mitigation_planned, $column_management_review, $column_days_open, $column_next_review_date, $column_next_step, $column_affected_assets, $column_planning_strategy, $column_planning_date, $column_mitigation_effort, $column_mitigation_cost, $column_mitigation_owner, $column_mitigation_team, $column_mitigation_accepted, $column_mitigation_date, $column_mitigation_controls, $column_risk_assessment, $column_additional_notes, $column_current_solution, $column_security_recommendations, $column_security_requirements, $column_risk_tags, $column_closure_date, $column_custom_values=[])
{
    global $lang, $escaper;

    $risk_id = (int)$risk['id'];
    $status = $risk['status'];
    $subject = try_decrypt($risk['subject']);
    $reference_id = $risk['reference_id'];
    $control_number = $risk['control_number'];
    $submission_date = trim_date($risk['submission_date']);
    
    $last_update = trim_date($risk['last_update']);

    $review_date = trim_date($risk['review_date']);
        
    $scoring_method = get_scoring_method_name($risk['scoring_method']);
    $calculated_risk = (float)$risk['calculated_risk'];
        
    $residual_risk = (float)$risk['residual_risk'];
    $color = get_risk_color_from_levels($risk['calculated_risk'], $risk_levels);
    $residual_color = get_risk_color_from_levels($risk['residual_risk'], $risk_levels);
    $risk_level = get_risk_level_name_from_levels($risk['calculated_risk'], $risk_levels);
    $residual_risk_level = get_risk_level_name_from_levels($risk['residual_risk'], $risk_levels);
    $risk_tags = $risk['risk_tags'];
    $location = $risk['location'];
    $source = $risk['source'];
    $category = $risk['category'];
    $team = $risk['team'];
    $additional_stakeholders = $risk['additional_stakeholders'];
    $technology = $risk['technology'];
    $owner = $risk['owner'];
    $manager = $risk['manager'];
    $submitted_by = $risk['submitted_by'];
    $regulation = try_decrypt($risk['regulation']);
    $closure_date = $risk['closure_date'];
    $project = try_decrypt($risk['project']);
    $mitigation_id = $risk['mitigation_id'];
    $mgmt_review = $risk['mgmt_review'];

    // If the status is not closed
    if ($status != "Closed")
    {
        // Compare submission date to now
        $days_open = dayssince($risk['submission_date']);
    }
    // Otherwise the status is closed
    else
    {
        // Compare the submission date to the closure date
        $days_open = dayssince($risk['submission_date'], $risk['closure_date']);
    }

    // If next_review_date_uses setting is Residual Risk.
    if(get_setting('next_review_date_uses') == "ResidualRisk")
    {
        $next_review_date = next_review($residual_risk_level, $risk_id, $risk['next_review'], false, $review_levels);
        $next_review_date_html = next_review($residual_risk_level, $risk_id, $risk['next_review'], true, $review_levels);
    }
    // If next_review_date_uses setting is Inherent Risk.
    else
    {
        $next_review_date = next_review($risk_level, $risk_id, $risk['next_review'], false, $review_levels);
        $next_review_date_html = next_review($risk_level, $risk_id, $risk['next_review'], true, $review_levels);
    }
    $next_step = $risk['next_step'];
    
    // If the affected assets or affected asset groups is not empty
    if ($risk['affected_assets'] || $risk['affected_asset_groups'])
    {
        // Do a lookup for the list of affected assets
        $affected_assets = implode('', get_list_of_asset_and_asset_group_names($risk_id + 1000, true));
    }
    else $affected_assets = "";

    $risk_assessment = try_decrypt($risk['risk_assessment']);
    $additional_notes = try_decrypt($risk['additional_notes']);
    $current_solution = try_decrypt($risk['current_solution']);
    $security_recommendations = try_decrypt($risk['security_recommendations']);
    $security_requirements = try_decrypt($risk['security_requirements']);
    $planning_strategy = $risk['planning_strategy'];
    $planning_date = trim_date($risk['planning_date']);
    
    $mitigation_effort = $risk['mitigation_effort'];
    $mitigation_min_cost = $risk['mitigation_min_cost'];
    $mitigation_max_cost = $risk['mitigation_max_cost'];
    $mitigation_owner = $risk['mitigation_owner'];
    $mitigation_team = $risk['mitigation_team'];
    $mitigation_accepted = $risk['mitigation_accepted'] ? $lang["Yes"] : $lang["No"];
    $mitigation_date = $risk['mitigation_date'];
    $mitigation_control_names = $risk['mitigation_control_names'];

    // If the mitigation costs are empty
    if (empty($mitigation_min_cost) && empty($mitigation_max_cost))
    {
        // Return no value
        $mitigation_cost = "";
    }
    else 
    {
        $mitigation_cost = "$" . $mitigation_min_cost . " to $" . $mitigation_max_cost;
        if (!empty($risk['valuation_level_name'])){
            $mitigation_cost .= " ({$risk['valuation_level_name']})";
        }
    }

    $xlsRow = array();
    
    if($column_id == true)      $xlsRow[] = $escaper->escapeHtml(convert_id($risk_id));
    if($column_status == true)  $xlsRow[] = $escaper->escapeHtml($status);
    if($column_subject == true)  $xlsRow[] = $escaper->escapeHtml($subject);
    if($column_reference_id == true)  $xlsRow[] = $escaper->escapeHtml($reference_id);
    if($column_regulation == true)  $xlsRow[] = $escaper->escapeHtml($regulation);
    if($column_control_number == true)  $xlsRow[] = $escaper->escapeHtml($control_number);
    if($column_location == true)  $xlsRow[] = $escaper->escapeHtml($location);
    if($column_source == true)  $xlsRow[] = $escaper->escapeHtml($source);
    if($column_category == true)  $xlsRow[] = $escaper->escapeHtml($category);
    if($column_team == true)  $xlsRow[] = $escaper->escapeHtml($team);
    if($column_additional_stakeholders == true)  $xlsRow[] = $escaper->escapeHtml($additional_stakeholders);
    if($column_technology == true)  $xlsRow[] = $escaper->escapeHtml($technology);
    if($column_owner == true)  $xlsRow[] = $escaper->escapeHtml($owner);
    if($column_manager == true)  $xlsRow[] = $escaper->escapeHtml($manager);
    if($column_submitted_by == true)  $xlsRow[] = $escaper->escapeHtml($submitted_by);
    if($column_scoring_method == true)  $xlsRow[] = $escaper->escapeHtml($scoring_method);
    if($column_calculated_risk == true)  $xlsRow[] = $escaper->escapeHtml($calculated_risk);
    if($column_residual_risk == true)  $xlsRow[] = $escaper->escapeHtml($residual_risk);
    if($column_submission_date == true)  $xlsRow[] = $escaper->escapeHtml($submission_date);
    if($column_review_date == true)  $xlsRow[] = $escaper->escapeHtml($review_date);
    if($column_project == true)  $xlsRow[] = $escaper->escapeHtml($project);
    if($column_mitigation_planned == true)  $xlsRow[] = getTextBetweenTags(planned_mitigation(convert_id($risk_id), $mitigation_id), 'a');
    if($column_management_review == true)  $xlsRow[] = getTextBetweenTags(management_review(convert_id($risk_id), $mgmt_review, $next_review_date), 'a');
    if($column_days_open == true)  $xlsRow[] = $escaper->escapeHtml($days_open);
    if($column_next_review_date == true)  $xlsRow[] = getTextBetweenTags($next_review_date_html, 'a');
    if($column_next_step == true)  $xlsRow[] = $escaper->escapeHtml($next_step);
    if($column_affected_assets == true)  $xlsRow[] = $escaper->escapeHtml($affected_assets);
    if($column_risk_assessment == true)  $xlsRow[] = $escaper->escapeHtml($risk_assessment);
    if($column_additional_notes == true)  $xlsRow[] = $escaper->escapeHtml($additional_notes);
    if($column_current_solution == true)  $xlsRow[] = $escaper->escapeHtml($current_solution);
    if($column_security_recommendations == true)  $xlsRow[] = $escaper->escapeHtml($security_recommendations);
    if($column_security_requirements == true)  $xlsRow[] = $escaper->escapeHtml($security_requirements);
    if($column_planning_strategy == true)  $xlsRow[] = $escaper->escapeHtml($planning_strategy);
    if($column_planning_date == true)  $xlsRow[] = $escaper->escapeHtml($planning_date);
    if($column_mitigation_effort == true)  $xlsRow[] = $escaper->escapeHtml($mitigation_effort);
    if($column_mitigation_cost == true)  $xlsRow[] = $escaper->escapeHtml($mitigation_cost);
    if($column_mitigation_owner == true)  $xlsRow[] = $escaper->escapeHtml($mitigation_owner);
    if($column_mitigation_team == true)  $xlsRow[] = $escaper->escapeHtml($mitigation_team);
    if($column_mitigation_accepted == true)  $xlsRow[] = $escaper->escapeHtml($mitigation_accepted);
    if($column_mitigation_date == true)  $xlsRow[] = $escaper->escapeHtml($mitigation_team);
    if($column_mitigation_controls == true)  $xlsRow[] = $escaper->escapeHtml($mitigation_team);
    if($column_risk_tags == true)  $xlsRow[] = $escaper->escapeHtml($risk_tags);
    if($column_closure_date == true)  $xlsRow[] = $escaper->escapeHtml($closure_date);
    
    // If customization extra is enabled, add custom fields
    if(customization_extra())
    {
        // Include the extra
        require_once(realpath(__DIR__ . '/../customization/index.php'));
        $custom_values = getCustomFieldValuesByRiskId(convert_id($risk_id));

        $active_fields = get_active_fields();
        foreach($active_fields as $active_field)
        {
            // If main field, ignore.
            if($active_field['is_basic'] == 0 && in_array("custom_field_{$active_field['id']}", array_keys($column_custom_values))){
                $text = "";
                
                // Get value of custom filed
                foreach($custom_values as $custom_value)
                {
                    // Check if this custom value is for the active field
                    if($custom_value['field_id'] == $active_field['id']){
                        $value = $custom_value['value'];
                        
                        $text = get_plan_custom_field_name_by_value($active_field['id'], $custom_value['field_type'], $custom_value['encryption'], $value);
                        
                        break;
                    }
                }

                // Set custom value to xls risk row
                $xlsRow[] = $text;
            }
        }

    }
    
    
    return $xlsRow;
}

/***********************************
 * FUNCTION: DISPLAY DOWNLOAD LINK *
 ***********************************/
function display_download_link()
{
//    echo "<div class=\"row-fluid bottom-offset-10\">\n";
    echo "  <div class=\"span6 text-right\">\n";
    echo "    <a id=\"export-dynamic-risk-report\" title=\"Download to XLS\" ><img src=\"../images/excel.ico\" width=\"56px\" alt=\"Download to XLS\"></a>\n";
    echo "  </div>\n";
//    echo "</div>\n";
}

/********************************
 * FUNCTION: GET IMPORT FROM DB *
 ********************************/
function get_import_from_db()
{
    // If the import file does not exist
    if (!file_exists(sys_get_temp_dir() . '/import.csv'))
    {
        // Open the database connection
        $db = db_open();

        // Get the file from the database
        $stmt = $db->prepare("SELECT content FROM `import_export_tmp` WHERE name='import.csv';");
        $stmt->execute();
        $import = $stmt->fetch(PDO::FETCH_ASSOC);

        // Close the database connection
        db_close($db);

        // Write the contents to the file
        $fp = fopen(sys_get_temp_dir() . '/import.csv', "w");
        fwrite($fp, $import['content']);
    }
}

/***********************************
 * FUNCTION: DELETE IMPORT FROM DB *
 ***********************************/
function delete_import_from_db()
{
    // Open the database connection
    $db = db_open();

    // Delete the import file from the database
    $stmt = $db->prepare("DELETE FROM `import_export_tmp` WHERE name='import.csv';");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**************************
 * FUNCTION: SAVE MAPPING *
 **************************/
function save_mapping($mapping_name, $mappings)
{
    // Serialize the array as a string
    $mappings = serialize($mappings);

        // Open the database connection
        $db = db_open();

    // Store the mapping in the database
        $stmt = $db->prepare("INSERT INTO `import_export_mappings` (`name`, `mapping`) VALUES (:name, :mapping);");
    $stmt->bindParam(":name", $mapping_name, PDO::PARAM_STR, 100);
        $stmt->bindParam(":mapping", $mappings, PDO::PARAM_LOB);
        $stmt->execute();

        // Close the database connection
        db_close($db);
}

/*************************
 * FUNCTION: GET MAPPING *
 *************************/
function get_mapping($mapping_id)
{
    // Open the database connection
    $db = db_open();

    // Get the corresponding mapping
    $stmt = $db->prepare("SELECT mapping FROM `import_export_mappings` WHERE value = :mapping_id;");
    $stmt->bindParam(":mapping_id", $mapping_id, PDO::PARAM_INT);
    $stmt->execute();
    $array = $stmt->fetch();
    $mappings = $array['mapping'];

    // Close the database connection
    db_close($db);

    // Unserialize the string as an array
    $mappings = unserialize($mappings);

    // Return the mappings array
    return $mappings;
}

/****************************
 * FUNCTION: DELETE MAPPING *
 ****************************/
function delete_mapping($mapping_id){
    // Open the database connection
    $db = db_open();

    // Get the corresponding mapping
    $stmt = $db->prepare("DELETE FROM `import_export_mappings` WHERE `value`=:mapping_id;");
    $stmt->bindParam(":mapping_id", $mapping_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    return true;
}

/*************************************
 * FUNCTION: UPDATE RISK FROM IMPORT *
 *************************************/
function update_risk_from_import($risk_id, $status=null, $subject=null, $reference_id=null, $regulation=null, $control_number=null, $location=null, $source=null,  $category=null, $team=null, $technology=null, $owner=null, $manager=null, $assessment=null, $notes=null, $project_id=null, $submitted_by=null, $submission_date=null, $additional_stakeholders=null)
{
    $id = (int)$risk_id - 1000;

    $data = array(
        "status"            => $status, 
        "subject"           => try_encrypt($subject), 
        "reference_id"      => $reference_id, 
        "regulation"        => $regulation, 
        "control_number"    => $control_number, 
        "location"          => $location, 
        "source"            => $source, 
        "category"          => $category, 
        "team"              => $team, 
        "technology"        => $technology, 
        "owner"             => $owner ? $owner : NULL, 
        "manager"           => $manager ? $manager : NULL, 
        "assessment"        => try_encrypt($assessment), 
        "notes"             => try_encrypt($notes), 
        "project_id"        => $project_id, 
        "submitted_by"      => $submitted_by ? $submitted_by : NULL, 
        "submission_date"   => $submission_date, 
        "additional_stakeholders"=> $additional_stakeholders
    );

    // Open the database connection
    $db = db_open();
    
    $sql = "UPDATE risks SET ";
    foreach($data as $key => $value){
        if(!is_null($value))
            $sql .= " {$key}=:{$key}, ";
    }
    $sql = trim($sql, ", ");
    $sql .= " WHERE id = :id ";

    // Update the risk
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    foreach($data as $key => $value){
        if(!is_null($value)){
            $stmt->bindParam(":{$key}", $data[$key]);
        }
        unset($value);
    }

    $stmt->execute();
    
    $updated = $stmt->rowCount();

    // Close the database connection
    db_close($db);

    // If one risk was updated
    if($updated){
        // Audit log
        $message = "Risk details were updated for risk ID \"" . $risk_id . "\" by username \"" . $_SESSION['user'] . "\".";
        write_log($risk_id, $_SESSION['uid'], $message);

        // If the encryption extra is enabled, updates order_by_subject
        if (encryption_extra())
        {
            // Load the extra
            require_once(realpath(__DIR__ . '/../encryption/index.php'));

            create_subject_order($_SESSION['encrypted_pass']);
        }
    }

    return $updated;
}

/*************************************
 * FUNCTION: DISPLAY CONTROLS EXPORT *
 *************************************/
function display_controls_export()
{
    global $escaper;
    global $lang;

    // Show the asset export form
    echo $escaper->escapeHtml($lang['ExportToCSVByClickingBelow']) . ":<br />\n";
    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"controls_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportControls']) . "</button>\n";
    echo "</div>\n";
}

/*************************************
 * FUNCTION: DISPLAY IMPORT CONTROLS *
 *************************************/
function display_import_controls()
{
    global $escaper;
    global $lang;

    // If a file has not been imported or mapped
    if (!isset($_POST['import_controls_csv']) && !isset($_POST['controls_csv_mapped']))
    {
        echo "Import the following CSV file into SimpleRisk:<br />\n";
        echo "<div id=\"controls_file_wrapper\"></div>\n";
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"import_controls_csv\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
        echo "</div>\n";
    }
    else if (isset($_POST['controls_csv_mapped']))
    {
        // Copy posted values into a new array
        $mappings = $_POST;

        // Remove the first value in the array (CSRF Token)
        array_shift($mappings);

        // Remove the last value in the array (Submit Button)
        array_pop($mappings);

        // Import using the mapping
        if(!import_controls_with_mapping($mappings))
        {
            $url = $_SESSION['base_url']."/admin/importexport.php?importoption=controls";
            refresh();
        }
    }
    // If a file has been imported
    else
    {
        // Import the file
        $display = import_csv($_FILES['controls_file']);

        // If the file import was successful
        if ($display != 0)
        {
            // Print the remove selected javascript
            //remove_selected_js();

            echo "<input type=\"checkbox\" name=\"import_first\" />&nbsp;Import First Row\n";
            echo "<br /><br />\n";
            echo "<table class=\"table table-bordered table-condensed sortable\">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            echo "<th width=\"200px\">File Columns</th>\n";
            echo "<th>Controls Column Mapping</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            // Column counter
            $col_counter = 0;

            // For each column in the file
            foreach ($display as $column)
            {
                    echo "<tr>\n";
                    echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                    echo "<td>\n";
                    controls_column_name_dropdown("col_" . $col_counter);
                    echo "</td>\n";
                    echo "</tr>\n";

                    // Increment the column counter
                    $col_counter++;
            }

            echo "</tbody>\n";
            echo "</table>\n";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"controls_csv_mapped\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
            echo "<script>$(document).ready(function(){ $('input[type=\"file\"]').remove(); });</script>";
        }
        // Otherwise, file import error
        else
        {
            // Get any alert messages
            //get_alert();
        }
    }
}

/******************************************
 * FUNCTION: IMPORT CONTROLS WITH MAPPING *
 ******************************************/
function import_controls_with_mapping($mappings)
{
    global $escaper, $lang;

    // Open the temporary file for reading
    ini_set('auto_detect_line_endings', true);

    // Detect first line
    $first_line = true;

    // If we can read the temporary file
    if (($handle = fopen(sys_get_temp_dir() . '/import.csv', "r")) !== FALSE)
    {
        // Get frameworks 
        $frameworks = get_table_ordered_by_name("frameworks");
        $framework_values_array = array_map(function($framework){
            return $framework['name'];
        },$frameworks);
        
        // While we have lines in the file to read
        while (($csv_line = fgetcsv($handle)) !== FALSE)
        {
            // If we can import the first line or this is not the first line
            if (isset($_POST['import_first']) || $first_line == false)
            {
                // Get the name
                $short_name = core_get_mapping_value("controls_", "short_name", $mappings, $csv_line);

                /*******************
                 *** ADD CONTROL ***
                 *******************/
                // If the short name is not null (we don't want to add controls without a name)
                if (!is_null($short_name))
                {
                    // Get the control values
                    $control_id = (int)core_get_mapping_value("controls_", "id", $mappings, $csv_line);
                    $long_name = core_get_mapping_value("controls_", "long_name", $mappings, $csv_line);
                    $description = core_get_mapping_value("controls_", "description", $mappings, $csv_line);
                    $supplemental_guidance = core_get_mapping_value("controls_", "supplemental_guidance", $mappings, $csv_line);
                    $control_owner = core_get_mapping_value("controls_", "control_owner", $mappings, $csv_line);
                    $control_frameworks = core_get_mapping_value("controls_", "control_framework", $mappings, $csv_line);
                    $control_class = core_get_mapping_value("controls_", "control_class", $mappings, $csv_line);
                    $control_phase = core_get_mapping_value("controls_", "control_phase", $mappings, $csv_line);
                    $control_number = core_get_mapping_value("controls_", "control_number", $mappings, $csv_line);
                    $control_priority = core_get_mapping_value("controls_", "control_priority", $mappings, $csv_line);
                    $control_family = core_get_mapping_value("controls_", "control_family", $mappings, $csv_line);
                    $mitigation_percent = core_get_mapping_value("controls_", "mitigation_percent", $mappings, $csv_line);
                    
                    if(!$long_name && !$short_name && !$description && !$supplemental_guidance && !$control_owner && !$control_frameworks && !$control_class && !$control_phase && !$control_number && !$control_priority && !$control_family && !$mitigation_percent){
                        continue;
                    }
                    
                    if($control_frameworks){
                        $control_frameworks_arr = explode(",", $control_frameworks);
                        $control_framework_ids = [];
                        foreach($control_frameworks_arr as $control_framework){
                            $control_framework = trim($control_framework);
                            if(in_array($control_framework, $framework_values_array)){
                                $control_framework_id = get_value_by_name("frameworks", $control_framework);
                                $control_framework_ids[] = $control_framework_id;
                            }else{
                                $control_framework_id = add_framework($control_framework, "");
                                $framework_values_array[] = $control_framework;
                                $control_framework_ids[] = $control_framework_id;
                            }
                        }
                        $control_framework_ids = implode(",", $control_framework_ids);
                    }
                    else $control_framework_ids = "";
                    
                    
                    if($control_class && !($control_class_id = get_value_by_name("control_class", $control_class))){
                        $control_class_id = add_name("control_class", $control_class, 100);
                    }
                    elseif(empty($control_class_id)) $control_class_id = "";

                    if($control_phase && !($control_phase_id = get_value_by_name("control_phase", $control_phase))){
                        $control_phase_id = add_name("control_phase", $control_phase, 100);
                    }
                    elseif(empty($control_phase_id)) $control_phase_id = "";

                    if($control_priority && !($control_priority_id = get_value_by_name("control_priority", $control_priority))){
                        $control_priority_id = add_name("control_priority", $control_priority, 100);
                    }
                    elseif(empty($control_priority_id)) $control_priority_id = "";

                    if($control_family && !($control_family_id = get_value_by_name("family", $control_family))){
                        $control_family_id = add_name("family", $control_family, 100);
                    }
                    elseif(empty($control_family_id)) $control_family_id = "";

                    // Set null values to default
                    if (is_null($mitigation_percent)) $mitigation_percent = 0;

                    // Add the control
                    $control = array(
                        'short_name'=>$short_name,
                        'long_name'=>$long_name,
                        'description'=>$description,
                        'supplemental_guidance'=>$supplemental_guidance,
                        'framework_ids'=>$control_framework_ids,
                        'control_owner'=>$control_owner,
                        'control_class'=>$control_class_id,
                        'control_phase'=>$control_phase_id,
                        'control_number'=>$control_number,
                        'control_priority'=>$control_priority_id,
                        'family'=>$control_family_id,
                        'mitigation_percent'=>$mitigation_percent,
                    );

                    if (!$control_id) {
                        add_framework_control($control);

                        if($control_frameworks){
                            echo _lang('ImportControlCreatedAndAdded', array('short_name' => $short_name, 'control_frameworks' => $control_frameworks)) . "<br />\n";
                        }else{
                            echo _lang('ImportControlCreated', array('short_name' => $short_name)) . "<br />\n";
                        }
                    } else {
                        update_framework_control($control_id, $control);
                        echo _lang('ImportControlUpdated', array('short_name' => $short_name, 'control_id' => $control_id)) . "<br />\n";
                    }
                }
                else
                {
                    // Display an alert
                    set_alert(true, "bad", $escaper->escapeHtml($lang['ControlShortNameFieldRequired']));
                    return false;                    
                }
            }
            // Otherwise this is the first line
            else
            {
                // Set the first line to false
                $first_line = false;
            }
        }
    }

    // Close the temporary file
    fclose($handle);
    return true;
}

/*******************************************
 * FUNCTION: CONTROLS COLUMN NAME DROPDOWN *
 *******************************************/
function controls_column_name_dropdown($name)
{
        global $escaper;

        // Get the list of controls fields
        $fields = controls_fields();

        echo "<select class=\"mapping\" name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" onchange=\"removeSelected(this.name, this.value)\">\n";
        echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

        // For each field
        foreach ($fields as $key => $value)
        {
                echo "<option value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
        }

        echo "</select>\n";
}

/*****************************
 * FUNCTION: CONTROLS FIELDS *
 *****************************/
function controls_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'controls_id'                       =>$lang['ControlID'],
        'controls_short_name'               =>$lang['ControlShortName'],
        'controls_long_name'                =>$lang['ControlLongName'],
        'controls_description'              =>$lang['ControlDescription'],
        'controls_supplemental_guidance'    =>$lang['SupplementalGuidance'],
        'controls_control_owner'            =>$lang['ControlOwner'],
        'controls_control_framework'        =>$lang['ControlFramework'],
        'controls_control_class'            =>$lang['ControlClass'],
        'controls_control_phase'            =>$lang['ControlPhase'],
        'controls_control_number'           =>$lang['ControlNumber'],
        'controls_control_priority'         =>$lang['ControlPriority'],
        'controls_control_family'           =>$lang['ControlFamily'],
        'controls_mitigation_percent'       =>$lang['MitigationPercent'],
    );

    // Return the fields array
    return $fields;
}

/***********************************************
 * FUNCTION: DISPLAY AUDIT LOG DOWNLOAD BUTTON *
 ***********************************************/
function display_audit_download_btn($additional_fields = [])
{
    global $escaper;
    global $lang;

    echo "
        <div class=\"audit-download-folder\">
            <a class='download-btn' title=\"Download to XLS\"><img class=\"down-icon\" src=\"".$_SESSION['base_url']."/images/excel.ico\" alt=\"Download to XLS\" width=\"56px\"></a>
            <form action='' name='download_form' method='post' target='_blank'>
                <input type='hidden' name='days'>
                <input type='hidden' name='download_audit_log'>";

    foreach($additional_fields as $name => $value)
        echo "  <input type='hidden' name='{$name}' value='{$value}'>";

    echo "
            </form>
        </div>
        <script>
            $(document).ready(function(){
                $('.download-btn').click(function(){
                    var days = $('[name=days]', '.audit-select-folder').val();
                    $('[name=days]', [name=download_form]).val(days)
                    document.download_form.submit();
                })
            })
        </script>
    ";
    
}

/****************************************
 * FUNCTION: DOWNLOAD AUDIT LOGS TO XLS *
 ****************************************/
function download_audit_logs($days, $type=null, $file_title=null, $id=null){
    global $lang;
    global $escaper;
    
    if(!$file_title){
        $file_title = $escaper->escapeHtml($lang['AuditTrailReport']);
    }

    $logs = get_audit_trail($id, $days, $type);
    
    $xlsHeader = array(
        $escaper->escapeHtml($lang['DateAndTime']),
        $escaper->escapeHtml($lang['Username']),
        $escaper->escapeHtml($lang['Message'])
    );

    $xlsRows = array();

    $xlsRows[] = $xlsHeader;
    
    // For each risk in the risks array
    foreach ($logs as $log)
    {
        $date = date(get_default_datetime_format("g:i A T"), strtotime($log['timestamp']));
        
        $xlsRows[] = array(
            $date,
            $log['user_fullname'],
            $log['message'],
        );
        
    }
//    print_r($xlsRows);exit;
    
    /***********Export Excel**************/
    
    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $currentExcelRowIndex = 1;
    $columnCount = count($xlsHeader);

    // Style
    $centerStyle = array(
        'alignment' => array(
            'horizontal' => PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        )
    );

    foreach($xlsRows as $rowIndex => $xlsRow){
        $rindex = $rowIndex + 1;
        foreach($xlsRow as $columnIndex => $value){
            $cindex = $columnIndex + 1;
            $sheet->setCellValueByColumnAndRow($cindex, $rindex, $value);
        }
    }
    
    $xlsName = $file_title." - ".date('Y-m-d H:i:s').'.xls';
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="'.$escaper->escapeUrl($xlsName).'"');
    header('Cache-Control: max-age=0');
    $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, "Xls");
    $writer->save('php://output');
    exit;

}

?>

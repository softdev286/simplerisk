<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to       *
 * create custom risk assessment questionnaires.                    *
 ********************************************************************/

// Extra Version
define('ASSESSMENTS_EXTRA_VERSION', '20191130-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/services.php'));
require_once(realpath(__DIR__ . '/../../includes/display.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));

require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_assessment_extra_database();

/**************************************
 * FUNCTION: ENABLE ASSESSMENTS EXTRA *
 **************************************/
function enable_assessments_extra()
{
    prevent_extra_double_submit("assessments", true);

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'assessments', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
    $stmt->execute();

    // Add default values
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'ASSESSMENT_MINUTES_VALID', `value` = '1440'");
    $stmt->execute();

    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'ASSESSMENT_ASSET_SHOW_AVAILABLE', `value` = '0'");
    $stmt->execute();

    // Create the table to track sent assessments
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `assessment_tracking` (`id` int(11) NOT NULL AUTO_INCREMENT, `assessment_id` int(11) NOT NULL, `email` varchar(200) NOT NULL, `sender` int(11) NOT NULL, `key` varchar(20) NOT NULL, `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id)) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
    $stmt->execute();
    
    // Audit log entry for Extra turned on
    $message = "Assessment Extra was toggled on by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: DISABLE ASSESSMENTS EXTRA *
 ***************************************/
function disable_assessments_extra()
{
    prevent_extra_double_submit("assessments", false);

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'assessments'");
    $stmt->execute();

    // Audit log entry for Extra turned off
    $message = "Assessment Extra was toggled off by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);
}

/*********************************
 * FUNCTION: ASSESSMENTS VERSION *
 *********************************/
function assessments_version()
{
    // Return the version
    return ASSESSMENTS_EXTRA_VERSION;
}

/***************************************
 * FUNCTION: GET ASSESSMENT SETTINGS *
 ***************************************/
function get_assessment_settings()
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `settings` WHERE `name` = 'ASSESSMENT_MINUTES_VALID' or `name` = 'ASSESSMENT_ASSET_SHOW_AVAILABLE';");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/*****************************
 * FUNCTION: UPDATE SETTINGS *
 *****************************/
if (!function_exists('update_settings')){
    function update_settings($configs)
    {
        // Open the database connection
        $db = db_open();

        // If ASSESSMENT_MINUTES_VALID is an integer
        if (is_numeric($configs['ASSESSMENT_MINUTES_VALID']))
        {
            // Update ASSESSMENT_MINUTES_VALID
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'ASSESSMENT_MINUTES_VALID'");
            $stmt->bindParam(":value", $configs['ASSESSMENT_MINUTES_VALID']);
            $stmt->execute();
        }

        // If ASSESSMENT_ASSET_SHOW_AVAILABLE is set
        if (isset($configs['ASSESSMENT_ASSET_SHOW_AVAILABLE']))
        {
            // Update ASSESSMENT_ASSET_SHOW_AVAILABLE
            $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'ASSESSMENT_ASSET_SHOW_AVAILABLE'");
            $stmt->bindParam(":value", $configs['ASSESSMENT_ASSET_SHOW_AVAILABLE']);
            $stmt->execute();
        }

        // Close the database connection
        db_close($db);

        // Return true;
        return true;
    }
}

/**************************************
 * FUNCTION: UPDATE ASSESSMENT CONFIG *
 **************************************/
function update_assessment_config()
{
    $configs['ASSESSMENT_MINUTES_VALID'] = $_POST['assessment_minutes_valid'];
    $configs['ASSESSMENT_ASSET_SHOW_AVAILABLE'] = isset($_POST['assessment_asset_show_available']) ? 1 : 0;

    // Update the settings
    update_settings($configs);
}

/*********************************
 * FUNCTION: DISPLAY ASSESSMENTS *
 *********************************/
function display_assessments()
{
    global $escaper, $lang;

    echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . assessments_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";

    // Get the assessment settings
    $configs = get_assessment_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    echo "<form name=\"assessment_extra\" method=\"post\" action=\"\">\n";
    echo "<table>\n";
    echo "<tr>\n";
    echo "<tr>\n";
    echo "<td>".$escaper->escapeHtml($lang['MinutesAssessmentsAreValid']).":&nbsp;</td>\n";
    echo "<td><input type=\"text\" name=\"assessment_minutes_valid\" id=\"assessment_minutes_valid\" value=\"" . $ASSESSMENT_MINUTES_VALID . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td>".$escaper->escapeHtml($lang['ShowAvailableAssetsOnAssessments']).":&nbsp;</td>\n";
    echo "<td><input type=\"checkbox\" ". ((isset($ASSESSMENT_ASSET_SHOW_AVAILABLE) && $ASSESSMENT_ASSET_SHOW_AVAILABLE) ? "checked" : "") ." name=\"assessment_asset_show_available\" id=\"assessment_asset_show_available\" value=\"1\" /></td>\n";
    echo "</tr>\n";
    echo "</table>\n";
    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Submit']) . "</button>\n";
    echo "</div>\n";
    echo "</form>\n";
}

/*****************************************
 * FUNCTION: VIEW ASSESSMENTS EXTRA MENU *
 *****************************************/
function view_assessments_extra_menu($active)
{
    global $lang;
    global $escaper;

/*
    echo ($active == "CreateAssessment" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"index.php?action=create\"> " . $escaper->escapeHtml($lang['CreateAssessment']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "EditAssessment" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"index.php?action=edit\"> " . $escaper->escapeHtml($lang['EditAssessment']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "SendAssessment" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"index.php?action=send\"> " . $escaper->escapeHtml($lang['SendAssessment']) . "</a>\n";
    echo "</li>\n";
*/

    echo ($active == "AssessmentContacts" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"contacts.php\"> " . $escaper->escapeHtml($lang['AssessmentContacts']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "QuestionnaireQuestions" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"questionnaire_questions.php?action=questions_list\"> " . $escaper->escapeHtml($lang['QuestionnaireQuestions']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "QuestionnaireTemplates" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"questionnaire_templates.php?action=template_list\"> " . $escaper->escapeHtml($lang['QuestionnaireTemplates']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "Questionnaires" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"questionnaires.php?action=list\"> " . $escaper->escapeHtml($lang['Questionnaires']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "QuestionnaireResults" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"questionnaire_results.php\"> " . $escaper->escapeHtml($lang['QuestionnaireResults']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "QuestionnaireRiskAnalysis" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"questionnaire_risk_analysis.php\"> " . $escaper->escapeHtml($lang['RiskAnalysis']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "AssessmentImportexport" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"importexport.php\"> " . $escaper->escapeHtml($lang['ImportExport']) . "</a>\n";
    echo "</li>\n";
    echo ($active == "QuestionnaireTrail" ? "<li class=\"active\">\n" : "<li>\n");
    echo "<a href=\"questionnaire_trail.php\"> " . $escaper->escapeHtml($lang['QuestionnaireAuditTrail']) . "</a>\n";
    echo "</li>\n";
}

/****************************************
 * FUNCTION: DISPLAY CREATE ASSESSMENTS *
 ****************************************/
function display_create_assessments()
{
    global $lang;
    global $escaper;

    // If the create assessment was not posted
    if (!isset($_POST['create_assessment']))
    {
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        echo "<form name=\"assessment_name\" method=\"post\" action=\"\">\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"create\" />\n";
        echo "<p>Please give your assessment a name:</p>\n";
        echo "<p>Name:&nbsp;&nbsp;<input type=\"text\" name=\"assessment_name\" placeholder=\"Assessment Name\" />&nbsp;&nbsp;<input type=\"submit\" name=\"create_assessment\" value=\"" . $escaper->escapeHtml($lang['Next']) . "\" /></p>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";
    }
    else if (isset($_POST['assessment_name']))
    {
        // Set the assessment name
        $assessment_name = $_POST['assessment_name'];

        // If the assessment id was posted
        if (isset($_POST['assessment_id']))
        {
            // Set the id to the assessment id that was posted
            $id = (int)$_POST['assessment_id'];
        }
        // Otherwise, this is a new assessment
        else
        {
            // Create the assessment
            $id = (int)create_assessment($assessment_name);

            // Redirect to the edit page
            header("Location: index.php?action=edit&assessment_id=" . $id);
        }
    }
}

/*************************************
 * FUNCTION: DISPLAY RISK SCORE HTML *
 *************************************/
function display_risk_score($return, $key="", $scoring_method="5", $custom="10", $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector="N", $AccessComplexity="L", $Authentication="N", $ConfImpact="C", $IntegImpact="C", $AvailImpact="C", $Exploitability="ND", $RemediationLevel="ND", $ReportConfidence="ND", $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamagePotential="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkillLevel="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPEaseOfDiscovery="10", $OWASPEaseOfExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10"){
    global $lang;
    global $escaper;
    
    // If return HTML is required.
    if($return)
        ob_start();
    

    echo "<tr class=\"text-center\">\n";
    echo "<td >&nbsp;</td>";
    echo "<td><strong>".$escaper->escapeHtml($lang['RiskScore']).":</strong></td>\n";
    echo "<td colspan=\"3\" align=\"left\">\n";
        echo "<table class=\"risk-scoring-container\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">\n";
        echo "<tr>\n";
        echo "<td>\n";
            display_score_html_from_assessment($key, $scoring_method, $CLASSIC_likelihood, $CLASSIC_impact, $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential, $TargetDistribution, $ConfidentialityRequirement, $IntegrityRequirement, $AvailabilityRequirement, $DREADDamagePotential, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $custom);
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
    echo "</td>\n";
    echo "</tr>\n";
    
    // Return HTML 
    if($return){
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }
}

/*****************************************
 * FUNCTION: GET ASSESSMENT SCORES ARRAY *
 *****************************************/
function get_assessment_scores_array(){
    $results = array();
    if(is_array($_POST['scoring_method'])){
        foreach($_POST['scoring_method'] as $key => $scoring_method){
            $results[$key] = array(
                'scoring_method' => $_POST['scoring_method'][$key],

                // Classic Risk Scoring Inputs
                'CLASSIClikelihood' => $_POST['likelihood'][$key],
                'CLASSICimpact' =>  $_POST['impact'][$key],

                // CVSS Risk Scoring Inputs
                'CVSSAccessVector' => $_POST['AccessVector'][$key],
                'CVSSAccessComplexity' => $_POST['AccessComplexity'][$key],
                'CVSSAuthentication' => $_POST['Authentication'][$key],
                'CVSSConfImpact' => $_POST['ConfImpact'][$key],
                'CVSSIntegImpact' => $_POST['IntegImpact'][$key],
                'CVSSAvailImpact' => $_POST['AvailImpact'][$key],
                'CVSSExploitability' => $_POST['Exploitability'][$key],
                'CVSSRemediationLevel' => $_POST['RemediationLevel'][$key],
                'CVSSReportConfidence' => $_POST['ReportConfidence'][$key],
                'CVSSCollateralDamagePotential' => $_POST['CollateralDamagePotential'][$key],
                'CVSSTargetDistribution' => $_POST['TargetDistribution'][$key],
                'CVSSConfidentialityRequirement' => $_POST['ConfidentialityRequirement'][$key],
                'CVSSIntegrityRequirement' => $_POST['IntegrityRequirement'][$key],
                'CVSSAvailabilityRequirement' => $_POST['AvailabilityRequirement'][$key],
                // DREAD Risk Scoring Inputs
                'DREADDamage' => $_POST['DREADDamage'][$key],
                'DREADReproducibility' => $_POST['DREADReproducibility'][$key],
                'DREADExploitability' => $_POST['DREADExploitability'][$key],
                'DREADAffectedUsers' => $_POST['DREADAffectedUsers'][$key],
                'DREADDiscoverability' => $_POST['DREADDiscoverability'][$key],
                // OWASP Risk Scoring Inputs
                'OWASPSkillLevel' => $_POST['OWASPSkillLevel'][$key],
                'OWASPMotive' => $_POST['OWASPMotive'][$key],
                'OWASPOpportunity' => $_POST['OWASPOpportunity'][$key],
                'OWASPSize' => $_POST['OWASPSize'][$key],
                'OWASPEaseOfDiscovery' => $_POST['OWASPEaseOfDiscovery'][$key],
                'OWASPEaseOfExploit' => $_POST['OWASPEaseOfExploit'][$key],
                'OWASPAwareness' => $_POST['OWASPAwareness'][$key],
                'OWASPIntrusionDetection' => $_POST['OWASPIntrusionDetection'][$key],
                'OWASPLossOfConfidentiality' => $_POST['OWASPLossOfConfidentiality'][$key],
                'OWASPLossOfIntegrity' => $_POST['OWASPLossOfIntegrity'][$key],
                'OWASPLossOfAvailability' => $_POST['OWASPLossOfAvailability'][$key],
                'OWASPLossOfAccountability' => $_POST['OWASPLossOfAccountability'][$key],
                'OWASPFinancialDamage' => $_POST['OWASPFinancialDamage'][$key],
                'OWASPReputationDamage' => $_POST['OWASPReputationDamage'][$key],
                'OWASPNonCompliance' => $_POST['OWASPNonCompliance'][$key],
                'OWASPPrivacyViolation' => $_POST['OWASPPrivacyViolation'][$key],

                // Custom Risk Scoring
                'Custom' => $_POST['Custom'][$key],
                
                // Contributing Risk Scoring
                'ContributingLikelihood' => $_POST['ContributingLikelihood'][$key],
                'ContributingImpacts' => empty($_POST['ContributingImpacts']) ? [] : get_contributing_impacts_by_key_from_multi($_POST['ContributingImpacts'], $key)
            );
        }
    }
    
    return $results;
}

/****************************
 * FUNCTION: GET ASSESSMENT *
 ****************************/
function get_assessment_with_scoring($assessment_id)
{
        // Open the database connection
        $db = db_open();

        // Get the assessment questions and answers
        $stmt = $db->prepare("
            SELECT
                a.name AS assessment_name,
                b.question,
                b.id AS question_id,
                b.order AS question_order,
                c.answer,
                c.id AS answer_id,
                c.submit_risk,
                c.risk_subject,
                c.risk_score,
                c.risk_owner,
                c.order AS answer_order, 
                d.id assessment_scoring_id,
                d.scoring_method,
                d.calculated_risk,
                d.CLASSIC_likelihood,
                d.CLASSIC_impact,
                d.CVSS_AccessVector,
                d.CVSS_AccessComplexity,
                d.CVSS_Authentication,
                d.CVSS_ConfImpact,
                d.CVSS_IntegImpact,
                d.CVSS_AvailImpact,
                d.CVSS_Exploitability,
                d.CVSS_RemediationLevel,
                d.CVSS_ReportConfidence,
                d.CVSS_CollateralDamagePotential,
                d.CVSS_TargetDistribution,
                d.CVSS_ConfidentialityRequirement,
                d.CVSS_IntegrityRequirement,
                d.CVSS_AvailabilityRequirement,
                d.DREAD_DamagePotential,
                d.DREAD_Reproducibility,
                d.DREAD_Exploitability,
                d.DREAD_AffectedUsers,
                d.DREAD_Discoverability,
                d.OWASP_SkillLevel,
                d.OWASP_Motive,
                d.OWASP_Opportunity,
                d.OWASP_Size,
                d.OWASP_EaseOfDiscovery,
                d.OWASP_EaseOfExploit,
                d.OWASP_Awareness,
                d.OWASP_IntrusionDetection,
                d.OWASP_LossOfConfidentiality,
                d.OWASP_LossOfIntegrity,
                d.OWASP_LossOfAvailability,
                d.OWASP_LossOfAccountability,
                d.OWASP_FinancialDamage,
                d.OWASP_ReputationDamage,
                d.OWASP_NonCompliance,
                d.OWASP_PrivacyViolation,
                d.Custom
            FROM `assessments` a
                LEFT JOIN `assessment_questions` b ON a.id=b.assessment_id
                JOIN `assessment_answers` c ON b.id=c.question_id
                LEFT JOIN `assessment_scoring` d ON c.assessment_scoring_id=d.id
            WHERE
                a.id = :assessment_id
            ORDER BY
                question_order,
                b.id,
                answer_order,
                c.id;
        ");
        $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->execute();

        // Store the list in the array
        $array = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

    // Return the assessment
    return $array;
}

/**************************************
 * FUNCTION: DISPLAY EDIT ASSESSMENTS *
 **************************************/
function display_edit_assessments()
{
    global $lang;
    global $escaper;

    // If no assessment id is specified
    if (!(isset($_GET['assessment_id']) || isset($_POST['assessment_id'])))
    {
        // Get the assessment names
        $assessments = get_assessment_names();

        echo "<ul class=\"nav nav-pills nav-stacked\">\n";

        // For each entry in the assessments array
        foreach ($assessments as $assessment)
        {
            // Display the assessment
            echo "<li style=\"text-align:center\"><a href=\"index.php?action=edit&assessment_id=" . $escaper->escapeHtml($assessment['id']) . "\">" . $escaper->escapeHtml($assessment['name']) . "</a></li>\n";
        }

        echo "</ul>\n";
    }
    // Otherwise
    else
    {
        // If the assessment id is in a GET request
        if (isset($_GET['assessment_id']))
        {
            // Set the assessment id value to the GET value
            $assessment_id = (int)$_GET['assessment_id'];
        }
        // If the assessment id is in a POST request
        else if (isset($_POST['assessment_id']))
        {
            // Set the assessment id value to the POST value
            $assessment_id = (int)$_POST['assessment_id'];
        }

        // If the delete assessment was submitted
        if (isset($_POST['delete_assessment']))
        {
            // Delete the assessment
            delete_assessment($assessment_id);

            // Set the alert
            set_alert(true, "good", "The assessment was deleted successfully.");

            // Redirect to the edit page
            header("Location: index.php?action=edit");
        }
        // If a new question was submitted
        else if (isset($_POST['add']))
        {
            // Get the posted values
            $question = isset($_POST['question']) ? $_POST['question'] : "";
            $answer = isset($_POST['answer']) ? $_POST['answer'] : "";
            $submit_risk = isset($_POST['submit_risk']) ? $_POST['submit_risk'] : null;
            $risk_subject = isset($_POST['risk_subject']) ? $_POST['risk_subject'] : "";
//            $risk_score = isset($_POST['risk_score']) ? $_POST['risk_score'] : 0;
            $risk_owner = isset($_POST['risk_owner']) ? $_POST['risk_owner'] : null;
            $assets_asset_groups = isset($_POST['assets_asset_groups']) && is_array($_POST['assets_asset_groups']) ? $_POST['assets_asset_groups'] : [];

            // Get assessment score values as an array
            $assessment_scores = get_assessment_scores_array();
            
            // Add assessment risk score
            $assessment_scoring_ids = [];
            $risk_score = [];
            foreach($assessment_scores as $key => $assessment_score){
                list($assessment_scoring_ids[$key], $risk_score[$key]) = add_assessment_scoring($assessment_score);
            }

            // Add the question
            add_assessment_question($assessment_id, $question, $answer, $submit_risk, $risk_subject, $risk_score, $risk_owner, $assets_asset_groups, $assessment_scoring_ids);
        }

        // Get the assessment with that id
        $assessment = get_assessment_names($assessment_id);
        $assessment_name = $assessment['name'];

        // Add a question
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";

        echo "<table id=\"adding_row\" class=\"hide\">\n";
        echo "<tr>\n";
        echo "<td><input type=\"text\" name=\"answer[]\" size=\"200\" value=\"Yes\" placeholder=\"Answer\" /></td>\n";
        echo "<td align=\"middle\"><input type=\"checkbox\" name=\"submit_risk[]\" value=\"0\" checked /></td>\n";
        echo "<td><input type=\"text\" name=\"risk_subject[]\" size=\"200\" placeholder=\"Enter Risk Subject\" /></td>\n";
        echo "<td>\n";
        echo create_dropdown("enabled_users", NULL, "risk_owner[]");
        echo "</td>\n";
        echo "<td><select class='assets_asset_groups_template' multiple placeholder='" . $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']) . "'></select></td>\n";
        echo "</tr>\n";
        display_risk_score(false, "", 5, 10);
        echo "</table>";

        echo "<form name=\"assessment_question\" method=\"post\" action=\"\">\n";
        echo "<h4>" . $escaper->escapeHtml($lang['NewAssessmentQuestion']) . "<button name=\"delete_assessment\" value=\"\" style=\"float: right;\">" . $escaper->escapeHtml($lang['DeleteAssessment']) . "</button><div class=\"clearfix\"></div></h4>\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"edit\" />\n";
        echo "<input type=\"hidden\" name=\"create_assessment\" value=\"true\" />\n";
        echo "<input type=\"hidden\" name=\"assessment_name\" value=\"" . $escaper->escapeHtml($assessment_name) . "\" />\n";
        echo "<input type=\"hidden\" name=\"assessment_id\" value=\"" . $escaper->escapeHtml($assessment_id) . "\" />\n";
        echo "<table name=\"question\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\">\n";
        echo "<tr>\n";
        echo "<td>" . $escaper->escapeHtml($lang['Question']) . ":&nbsp;&nbsp;</td>\n";
        echo "<td width=\"100%\"><input type=\"text\" style=\"width: 99%;\" name=\"question\" placeholder=\"Enter Question Here\" /></td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "<table id=\"dataTable\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\">\n";
        echo "<tr>\n";
        echo "<th width=\"10%\">" . $escaper->escapeHtml($lang['Answer']) . "</th>\n";
        echo "<th width=\"10%\" align=\"middle\">" . $escaper->escapeHtml($lang['SubmitRisk']) . "</th>\n";
        echo "<th width=\"40%\" align=\"middle\">" . $escaper->escapeHtml($lang['Subject']) . "</th>\n";
//        echo "<th align=\"middle\">" . $escaper->escapeHtml($lang['RiskScore']) . "</th>\n";
        echo "<th width=\"10%\" align=\"middle\">" . $escaper->escapeHtml($lang['Owner']) . "</th>\n";
        echo "<th width=\"30%\" align=\"middle\">" . $escaper->escapeHtml($lang['AffectedAssets']) . "</th>\n";
        echo "</tr>\n";
        echo "<tr>\n";
        echo "<td><input type=\"text\" name=\"answer[]\" size=\"200\" value=\"Yes\" placeholder=\"Answer\" /></td>\n";
        echo "<td align=\"middle\"><input type=\"checkbox\" name=\"submit_risk[]\" value=\"0\" checked /></td>\n";
        echo "<td><input type=\"text\" name=\"risk_subject[]\" size=\"200\" placeholder=\"Enter Risk Subject\" /></td>\n";
//        echo "<td><input type=\"number\" min=\"0\" max=\"10\" name=\"risk_score[]\" value=\"10\" step=\"0.1\"/></td>\n";
        echo "<td>\n";
        echo create_dropdown("enabled_users", NULL, "risk_owner[]");
        echo "</td>\n";
        echo "<td><select class='assets-asset-groups-select' name='assets_asset_groups[0][]' multiple placeholder='" . $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']) . "'></select></td>\n";
        echo "</tr>\n";
        
        display_risk_score(false, "", 5, 10);

        echo "<tr>\n";
        echo "<td><input type=\"text\" name=\"answer[]\" size=\"200\" value=\"No\" placeholder=\"Answer\" /></td>\n";
        echo "<td align=\"middle\"><input type=\"checkbox\" name=\"submit_risk[]\" value=\"1\" /></td>\n";
        echo "<td><input type=\"text\" name=\"risk_subject[]\" size=\"200\" placeholder=\"Enter Risk Subject\" /></td>\n";
//        echo "<td><input type=\"number\" min=\"0\" max=\"10\" name=\"risk_score[]\" value=\"0\" step=\"0.1\" /></td>\n";
        echo "<td>\n";
        echo create_dropdown("enabled_users", NULL, "risk_owner[]");
        echo "</td>\n";
        echo "<td><select class='assets-asset-groups-select' name='assets_asset_groups[1][]' multiple placeholder='" . $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']) . "'></select></td>\n";
        echo "</tr>\n";

        display_risk_score(false,  "", 5, 0);

        echo "</table>\n";
        echo "<img src=\"../images/plus.png\" class=\"add-delete-icon\" onclick=\"addRow('dataTable')\" width=\"15px\" height=\"15px\" />&nbsp;&nbsp;<img class=\"add-delete-icon\" src=\"../images/minus.png\" onclick=\"deleteRow('dataTable')\" width=\"15px\" height=\"15px\" /><br />\n";
        echo "<input type=\"submit\" name=\"add\" value=\"" . $escaper->escapeHtml($lang['AddQuestion']) . "\" />\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";

        // Get the assessment
        $assessment = get_assessment_with_scoring($assessment_id);

        // Print the assessment
        echo "<div class=\"row-fluid\">\n";
        echo "<div class=\"span12\">\n";
        echo "<div class=\"hero-unit\">\n";
        if(count($assessment)){
            echo "<h4>" . $escaper->escapeHtml($assessment_name) . " <button class=\"update-all pull-right\">". $lang['UpdateAll'] ."</button><div class=\"clearfix\"></div></h4>\n";
        }else{
            echo "<h4>" . $escaper->escapeHtml($assessment_name) . "</h4>\n";
        }
        display_edit_assessment_questions($assessment_id, $assessment);
        if(count($assessment)){
            echo "<div class=\"text-right\"><button class=\"update-all\">". $lang['UpdateAll'] ."</button></div>";
        }
        echo "</div>\n";
        echo "</div>\n";
        echo "</div>\n";

        echo "
            <script>
                var assets_and_asset_groups = [];

                $(document).ready(function(){
                    $.ajax({
                        url: '/api/asset-group/options',
                        type: 'GET',
                        dataType: 'json',
                        success: function(res) {
                            var data = res.data;
                            var len = data.length;
                            for (var i = 0; i < len; i++) {
                                var item = data[i];
                                item.id += '_' + item.class;

                                assets_and_asset_groups.push(item);
                            }

                            $('select.assets-asset-groups-select').each(function() {

                                var combined_assets_and_asset_groups = assets_and_asset_groups;
                                // Have to add the unverified assets to the list of options,
                                // but only for THIS widget
                                $(this).find('option[data-unverified]').each(function() {

                                    combined_assets_and_asset_groups.push({
                                        id: '' + $(this).data('id') + '_' + $(this).data('class'),
                                        name: $(this).data('name'),
                                        class: $(this).data('class')
                                    });
                                });

                                selectize_assessment_answer_affected_assets_widget($(this), combined_assets_and_asset_groups);

                            });
                        }
                    });
                });
            </script>
        ";
    }
}

/*******************************
 * FUNCTION: CREATE ASSESSMENT *
 *******************************/
function create_assessment($assessment_name)
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT INTO `assessments` (`name`) VALUES (:assessment_name);");
    $stmt->bindParam(":assessment_name", $assessment_name, PDO::PARAM_STR, 200);
        $stmt->execute();

    // Get the id of the last insert
    $id = $db->lastInsertId();

    // Close the database connection
    db_close($db);

    // Return the id
    return $id;
}

/*************************************
 * FUNCTION: ADD ASSESSMENT QUESTION *
 *************************************/
function add_assessment_question($id, $question, $answer, $submit_risk, $risk_subject, $risk_score, $risk_owner, $assets_asset_groups, $assessment_scoring_ids)
{
    // Open the database connection
    $db = db_open();

    // Get the questions in current order
    $stmt = $db->prepare("SELECT max(`order`) max_order FROM `assessment_questions` where assessment_id=:assessment_id;");
    $stmt->bindParam(":assessment_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Get the values
    $array = $stmt->fetch();
    $order = isset($array['max_order']) ? ($array['max_order']+1) : 0;

    // Add the question
    $stmt = $db->prepare("INSERT INTO `assessment_questions` (`assessment_id`, `question`, `order`) VALUES (:assessment_id, :question, :order);");
    $stmt->bindParam(":assessment_id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":question", $question, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":order", $order, PDO::PARAM_STR, 1000);
    $stmt->execute();

    // Get the id of the last insert
    $question_id = $db->lastInsertId();
    // For each answer provided
    foreach ($answer as $key=>$value)
    {
        // If the key is in the submit_risk array
        if (in_array($key, $submit_risk))
        {
            $submit = 1;
        }
        else $submit = 0;

        // Add the answer
        $stmt = $db->prepare("INSERT INTO `assessment_answers` (`assessment_id`, `question_id`, `answer`, `submit_risk`, `risk_subject`, `risk_score`, `assessment_scoring_id`, `risk_owner`, `order`) VALUES (:assessment_id, :question_id, :answer, :submit_risk, :risk_subject, :risk_score, :assessment_scoring_id, :risk_owner, :order);");
        $stmt->bindParam(":answer", $answer[$key], PDO::PARAM_STR, 200);
        $stmt->bindParam(":assessment_id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
        $stmt->bindParam(":submit_risk", $submit, PDO::PARAM_INT);
        $stmt->bindParam(":risk_subject", $risk_subject[$key], PDO::PARAM_STR, 1000);
        $stmt->bindParam(":risk_score", $risk_score[$key], PDO::PARAM_STR, 200);
        $stmt->bindParam(":assessment_scoring_id", $assessment_scoring_ids[$key], PDO::PARAM_STR);
        $stmt->bindParam(":risk_owner", $risk_owner[$key], PDO::PARAM_INT);
        $stmt->bindParam(":order", $key, PDO::PARAM_INT);
        $stmt->execute();

        $answer_id = $db->lastInsertId();

        $assets_and_groups = [];

        if ($assets_asset_groups && !empty($assets_asset_groups[$key]) && is_array($assets_asset_groups[$key])) {
            $assets_and_groups = $assets_asset_groups[$key];
        }

        process_selected_assets_asset_groups_of_type($answer_id, $assets_and_groups, 'assessment_answer');        
    }

    // Close the database connection
    db_close($db);
}

/****************************************
 * FUNCTION: UPDATE ASSESSMENT QUESTION *
 ****************************************/
function update_assessment_question($assessment_id, $question_id, $question, $answer, $submit_risk, $answer_id, $risk_subject, $risk_score, $risk_owner, $assets_asset_groups, $assessment_scoring_ids = false)
{
        // Open the database connection
        $db = db_open();

        // Update the question
        $stmt = $db->prepare("UPDATE `assessment_questions` SET question=:question WHERE `assessment_id`=:assessment_id AND `id`=:question_id;");
        $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->bindParam("question_id", $question_id, PDO::PARAM_INT);
        $stmt->bindParam(":question", $question, PDO::PARAM_STR, 1000);
        $stmt->execute();

        // For each answer provided
        foreach ($answer_id as $key=>$value) {
            // If the answer_id is in the submit risk array
            if (in_array($value, $submit_risk))
            {
                // Set the submit risk value
                $submit = 1;
            }
            else $submit = 0;

            // Update the answer
            if($assessment_scoring_ids === false){
                $stmt = $db->prepare("UPDATE `assessment_answers` SET `answer`=:answer, `submit_risk`=:submit_risk, `risk_subject`=:risk_subject, `risk_score`=:risk_score, `risk_owner`=:risk_owner WHERE `assessment_id`=:assessment_id AND `question_id`=:question_id AND `id`=:answer_id;");
            }else{
                $stmt = $db->prepare("UPDATE `assessment_answers` SET `answer`=:answer, `submit_risk`=:submit_risk, `risk_subject`=:risk_subject, `risk_score`=:risk_score, `risk_owner`=:risk_owner, `assessment_scoring_id`=:assessment_scoring_id WHERE `assessment_id`=:assessment_id AND `question_id`=:question_id AND `id`=:answer_id;");
            }
            $stmt->bindParam(":answer", $answer[$key], PDO::PARAM_STR, 200);
            $stmt->bindParam(":answer_id", $value, PDO::PARAM_INT);
            $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
            $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
            $stmt->bindParam(":submit_risk", $submit, PDO::PARAM_INT);
            $stmt->bindParam(":risk_subject", $risk_subject[$key], PDO::PARAM_STR, 1000);
            $stmt->bindParam(":risk_score", $risk_score[$key], PDO::PARAM_STR);
            $stmt->bindParam(":risk_owner", $risk_owner[$key], PDO::PARAM_INT);
            if($assessment_scoring_ids !== false){
                $stmt->bindParam(":assessment_scoring_id", $assessment_scoring_ids[$key], PDO::PARAM_INT);
            }
            $stmt->execute();

            $assets_and_groups = [];

            if ($assets_asset_groups && !empty($assets_asset_groups[$value]) && is_array($assets_asset_groups[$value])) {
                $assets_and_groups = $assets_asset_groups[$value];
            }

            process_selected_assets_asset_groups_of_type($value, $assets_and_groups, 'assessment_answer');
        }

        // Close the database connection
        db_close($db);
}

/***********************************************
 * FUNCTION: DISPLAY EDIT ASSESSMENT QUESTIONS *
 ***********************************************/
function  display_edit_assessment_questions($assessment_id, $assessment)
{
    global $escaper;
    global $lang;

    // If the user posted to delete the question
    if (isset($_POST['delete_question']))
    {
        // Get the question id to delete
        $question_id = (int)$_POST['question_id'];

        // Delete the question
        delete_question($assessment_id, $question_id);

        set_alert(true, "good", $escaper->escapeHtml($lang["DeletedSuccess"]));

        // Refresh current page
        header('Location: '.$_SERVER['REQUEST_URI']);
        exit;
    }

    // If the user posted to save the question
    if (isset($_POST['save']))
    {
        // Get the posted values
        $question_id = (int)$_POST['question_id'];
        $question = $_POST['question'];
        $answer = $_POST['answer'];
        $answer_id = $_POST['answer_id'];
        $risk_subject = $_POST['risk_subject'];
//        $risk_score = $_POST['risk_score'];
        $risk_owner = $_POST['risk_owner'];
        $assets_asset_groups = isset($_POST['assets_asset_groups']) ? $_POST['assets_asset_groups'] : [];

        // If the submit_risk parameter was posted
        if (isset($_POST['submit_risk']))
        {
            // Set the submit_risk variable
            $submit_risk = $_POST['submit_risk'];
        }
        else $submit_risk = array();

        // Get assessment score values as an array
        $assessment_scores = get_assessment_scores_array();

        // Update assessment scoring
        foreach($assessment_scores as $key => $assessment_score){

            if($_POST['assessment_scoring_id'][$key]){
                $risk_score[$key] = update_assessment_scoring($_POST['assessment_scoring_id'][$key], $assessment_score);
                $assessment_scoring_ids[] = $_POST['assessment_scoring_id'][$key];
            }
            else{
                list($assessment_scoring_ids[], $risk_score[]) = add_assessment_scoring($assessment_score);
            }
        }

        // Update the question
        update_assessment_question($assessment_id, $question_id, $question, $answer, $submit_risk, $answer_id, $risk_subject, $risk_score, $risk_owner, $assets_asset_groups, $assessment_scoring_ids);

        set_alert(true, "good", $escaper->escapeHtml($lang["SavedSuccess"]));

        // Refresh current page
        header('Location: '.$_SERVER['REQUEST_URI']);
        exit;
    }

    // If the user posted to move the question up
    if (isset($_POST['move_up']))
    {
        // Get the question id
        $question_id = (int)$_POST['question_id'];

        // Move the question up
        change_question_order($assessment_id, $question_id, "up");
    }

    // If the user posted to move the question down
    if (isset($_POST['move_down']))
    {
        // Get the question id
        $question_id = (int)$_POST['question_id'];

        // Move the question down
        change_question_order($assessment_id, $question_id, "down");
    }

    // Set a variable to track the current question
    $current_question = "";
    $questionHtmlArr = array();
    $affected_assets_placeholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']);

    // For each row in the array
    foreach ($assessment as $key=>$row) {
        $question = $row['question'];
        $question_id = $row['question_id'];
        if(empty($questionHtmlArr[$question_id])){
            $questionHtmlArr[$question_id] = array(
                'questionHtml' => "
                    <form name='question' method='POST' action=''>
                        <input type='hidden' name='action' value='edit' />
                        <input type='hidden' name='assessment_id' value='" . $escaper->escapeHtml($assessment_id) . "' />
                        <input type='hidden' name='question_id' value='" . $escaper->escapeHtml($question_id) . "' />

                        <table border='0' cellspacing='0' cellpadding='0' width='100%'>
                            <tr>
                                <td>" . $escaper->escapeHtml($lang['Question']) . ":&nbsp;&nbsp;</td>
                                <td width='100%'><input type='text' style='width: 99%; ' name='question' value='" . $escaper->escapeHtml($question) . "' /></td>
                            </tr>
                        </table>

                        <table class='answers-table' border='0' cellspacing='0' cellpadding='0' width='100%'>
                            <tr>
                                <th width=\"10%\">" . $escaper->escapeHtml($lang['Answer']) . "</th>
                                <th width=\"10%\" align='middle'>" . $escaper->escapeHtml($lang['SubmitRisk']) . "</th>
                                <th width=\"40%\" align='middle'>" . $escaper->escapeHtml($lang['Subject']) . "</th>
                                <!-- th align='middle'>" . $escaper->escapeHtml($lang['RiskScore']) . "</th -->
                                <th width=\"10%\" align='middle'>" . $escaper->escapeHtml($lang['Owner']) . "</th>
                                <th width=\"30%\" align='middle'>" . $escaper->escapeHtml($lang['AffectedAssets']) . "</th>
                            </tr>
                            ___Answers___
                         </table>
                        <button name='save' value='' title='Save' style='float: left;'><img src='../images/save.png' width='10' height='10' align='right' alt='Save' /></button>
                        <button name='move_up' title='Move Up' value='' style='float: left;'><img src='../images/arrow-up.png' width='10' height='10' align='right' alt='Move Up' /></button>
                        <button name='move_down' title='Move Down' value='' style='float: left;'><img src='../images/arrow-down.png' width='10' height='10' align='right' alt='Move Down' /></button>
                        <button name='delete_question' title='Delete Question' value='' style='float: left;'><img src='../images/X-100.png' width='10' height='10' align='right' alt='Delete Question' /></button>
                     </form>
                     <br>
                     <div class='clearfix'></div>
                     <hr />
                     <br>
                 ",
                'answerHtmlArr' => array(),
            );
        }

        // Set the answer values
        $answer = $row['answer'];
        $answer_id = $row['answer_id'];
        $submit_risk = $row['submit_risk'];
        $risk_subject = $row['risk_subject'];
        $risk_score = $row['risk_score'];
        $risk_owner = $row['risk_owner'];

        $answerHtml = "
            <tbody>
            <tr>
                <td><input type='text' name='answer[]' size='200' value='" . $escaper->escapeHtml($answer) . "' /></td>
                <td align='middle'><input type='checkbox' name='submit_risk[]' value='" . $escaper->escapeHtml($answer_id) . "'" . (($submit_risk == 1) ? " checked" : "") . " /><input type='hidden' name='answer_id[]' value='" . $escaper->escapeHtml($answer_id) . "' /></td>
                <td><input type='text' name='risk_subject[]' size='200' value='" . $escaper->escapeHtml($risk_subject) . "' /></td>
                <!-- td><input type='number' min='0' max='10' name='risk_score[]' value='" . $escaper->escapeHtml($risk_score) . "' step='0.1' /></td -->
                <td>
                ".create_dropdown("enabled_users", $risk_owner, "risk_owner[]", true, false, true)."   
                </td>
                <td>
                    <select class='assets-asset-groups-select' name='assets_asset_groups[" . $escaper->escapeHtml($answer_id) . "][]' multiple placeholder='$affected_assets_placeholder'>";

        $assets_asset_groups = get_assets_and_asset_groups_of_type($answer_id, 'assessment_answer');
        if ($assets_asset_groups){
            foreach($assets_asset_groups as $item) {
                $answerHtml .= "<option value='{$item['id']}_{$item['class']}' selected " . ($item['verified'] ? "" : "data-unverified data-id='{$item['id']}' data-class='{$item['class']}' data-name='" . $escaper->escapeJS($item['name']) . "'") . ">" . $escaper->escapeHtml($item['name']) . "</option>";
            }
        }

        $answerHtml .= "
                    </select>
                    <input type='hidden' id='assessment_scoring_id' name='assessment_scoring_id[]' value='". $row['assessment_scoring_id'] ."'> 
                </td>
            </tr>
        ";

        // If this answer has assessment scoring record.
        if($row['assessment_scoring_id'])
        {
            $answerHtml .= display_risk_score(true, "", $row['scoring_method'], $row['Custom'], $row['CLASSIC_likelihood'], $row['CLASSIC_impact'], $row['CVSS_AccessVector'], $row['CVSS_AccessComplexity'], $row['CVSS_Authentication'], $row['CVSS_ConfImpact'], $row['CVSS_IntegImpact'], $row['CVSS_AvailImpact'], $row['CVSS_Exploitability'], $row['CVSS_RemediationLevel'], $row['CVSS_ReportConfidence'], $row['CVSS_CollateralDamagePotential'], $row['CVSS_TargetDistribution'], $row['CVSS_ConfidentialityRequirement'], $row['CVSS_IntegrityRequirement'], $row['CVSS_AvailabilityRequirement'], $row['DREAD_DamagePotential'], $row['DREAD_Reproducibility'], $row['DREAD_Exploitability'], $row['DREAD_AffectedUsers'], $row['DREAD_Discoverability'], $row['OWASP_SkillLevel'], $row['OWASP_Motive'], $row['OWASP_Opportunity'], $row['OWASP_Size'], $row['OWASP_EaseOfDiscovery'], $row['OWASP_EaseOfExploit'], $row['OWASP_Awareness'], $row['OWASP_IntrusionDetection'], $row['OWASP_LossOfConfidentiality'], $row['OWASP_LossOfIntegrity'], $row['OWASP_LossOfAvailability'], $row['OWASP_LossOfAccountability'], $row['OWASP_FinancialDamage'], $row['OWASP_ReputationDamage'], $row['OWASP_NonCompliance'], $row['OWASP_PrivacyViolation']);
        }
        // If this answer doesn't have assessment scoring record, set default scoring values.
        else
        {
            $answerHtml .= display_risk_score(true);
        }

        $answerHtml .= "</tbody>";

        array_push($questionHtmlArr[$question_id]['answerHtmlArr'], $answerHtml);
    }

    $questionHtmls = array();
    foreach($questionHtmlArr as $questionHtmlObj){
        $answerHtmls = implode("", $questionHtmlObj['answerHtmlArr']);
        $questionHtmls[] = str_replace("___Answers___", $answerHtmls, $questionHtmlObj['questionHtml']);
    }
    echo implode("", $questionHtmls);
}

/*****************************
 * FUNCTION: DELETE QUESTION *
 *****************************/
function delete_question($assessment_id, $question_id)
{
    // Open the database connection
    $db = db_open();

    // Delete the junction table entries for the assessment answer - asset connections
    $stmt = $db->prepare("
        delete
            aata
        from
            `assessment_answers` aa
            INNER JOIN `assessment_answers_to_assets` aata ON `aata`.`assessment_answer_id` = `aa`.`id`
        where
            aa.assessment_id=:assessment_id;
    ");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete the junction table entries for the assessment answer - asset group connections
    $stmt = $db->prepare("
        delete
            aatag
        from
            `assessment_answers` aa
            INNER JOIN `assessment_answers_to_asset_groups` aatag ON `aatag`.`assessment_answer_id` = `aa`.`id`
        where
            aa.assessment_id=:assessment_id;
    ");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete answers for the question
    $stmt = $db->prepare("DELETE t1, t2 FROM `assessment_scoring` t1 INNER JOIN `assessment_answers` t2 on t1.id = t2.assessment_scoring_id  WHERE t2.assessment_id=:assessment_id AND t2.question_id=:question_id;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete the question
    $stmt = $db->prepare("DELETE FROM `assessment_questions` WHERE assessment_id=:assessment_id AND id=:question_id;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/***********************************
 * FUNCTION: CHANGE QUESTION ORDER *
 ***********************************/
function change_question_order($assessment_id, $question_id, $direction)
{
    // Open the database connection
    $db = db_open();

    // Get the questions in current order
    $stmt = $db->prepare("SELECT * FROM `assessment_questions` WHERE `assessment_id`=:assessment_id ORDER BY `order`, `id`;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->execute();

    // Get the values
    $array = $stmt->fetchAll();

    // For each row in the array
    foreach ($array as $key=>$row)
    {
        // Set the new order values
        $array[$key]['order'] = $key;

        // If this is the id we are looking for
        if ($row['id'] == $question_id)
        {
            // Capture it's key
            $question_key = $key;
        }
    }

    // If we are moving the question up
    if ($direction == "up")
    {
        // If the question key is not the first
        if ($question_key != 0)
        {
            // Get the values for the two rows
            $row1 = $array[$question_key-1];
            $row2 = $array[$question_key];

            // Swap the order of the two rows
            $array[$question_key-1]['order'] = $row2['order'];
            $array[$question_key]['order'] = $row1['order'];
        }
    }

    // If we are moving the question down
    if ($direction == "down")
    {
        // If the question key is not the last
        if ($question_key < count($array)-1)
        {
            // Get the values for the two rows
            $row1 = $array[$question_key];
            $row2 = $array[$question_key+1];

            // Swap the order of the two rows
            $array[$question_key]['order'] = $row2['order'];
            $array[$question_key+1]['order'] = $row1['order'];
        }
    }


    // For each row in the array
    foreach ($array as $row)
    {
        // Get the values
        $question_id = $row['id'];
        $order = $row['order'];

        // Update the order
            $stmt = $db->prepare("UPDATE `assessment_questions` SET `order`=:order WHERE `assessment_id`=:assessment_id AND `id`=:question_id;");
            $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
        $stmt->bindParam(":order", $order, PDO::PARAM_INT);
               $stmt->execute();
    }

        // Close the database connection
        db_close($db);

}

/*******************************
 * FUNCTION: DELETE ASSESSMENT *
 *******************************/
function delete_assessment($assessment_id)
{
        // Open the database connection
        $db = db_open();

        // Delete the assessment
        $stmt = $db->prepare("DELETE FROM `assessments` WHERE id=:assessment_id;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->execute();

    // Delete the assessment questions
    $stmt = $db->prepare("DELETE FROM `assessment_questions` WHERE assessment_id=:assessment_id;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    
    // Delete the junction table entries for the assessment answer - asset connections
    $stmt = $db->prepare("
        delete
            aata
        from
            `assessment_answers` aa
            INNER JOIN `assessment_answers_to_assets` aata ON `aata`.`assessment_answer_id` = `aa`.`id`
        where
            aa.assessment_id=:assessment_id;
    ");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete the junction table entries for the assessment answer - asset group connections
    $stmt = $db->prepare("
        delete
            aatag
        from
            `assessment_answers` aa
            INNER JOIN `assessment_answers_to_asset_groups` aatag ON `aatag`.`assessment_answer_id` = `aa`.`id`
        where
            aa.assessment_id=:assessment_id;
    ");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete the assessment answers
    $stmt = $db->prepare("DELETE FROM `assessment_answers` WHERE assessment_id=:assessment_id;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete the pending risks
    $stmt = $db->prepare("DELETE FROM `pending_risks` WHERE assessment_id=:assessment_id;");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*********************************************
 * FUNCTION: DISPLAY SEND ASSESSMENT OPTIONS *
 *********************************************/
function display_send_assessment_options()
{
    global $escaper;
    global $lang;

    echo "<div class=\"row-fluid\">\n";
    echo "<div class=\"span12\">\n";
    echo "<div class=\"hero-unit\">\n";
    echo "<h4>" . $escaper->escapeHtml($lang['SendAssessment']) . "</h4>\n";
    echo "<form name=\"assessment_question\" method=\"post\" action=\"\">\n";
    echo "<input type=\"hidden\" name=\"action\" value=\"send\" />\n";
    echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\">\n";
    echo "<tr>\n";
    echo "<td style=\"white-space:nowrap;\">" . $escaper->escapeHtml($lang['AssessmentName']) . ":&nbsp;&nbsp;</td>\n";
    echo "<td width=\"99%\">\n";
    echo "<select id=\"assessment\" name=\"assessment\">\n";

    // Get the assessment names
    $assessments = get_assessment_names();

    // For each assessment
    foreach ($assessments as $assessment)
    {
        echo "<option value=\"" . $escaper->escapeHtml($assessment['id']) . "\">" . $escaper->escapeHtml($assessment['name']) . "</option>\n";
    }

    echo "</select>\n";
    echo "</td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td style=\"white-space:nowrap;\">" . $escaper->escapeHtml($lang['SendTo']) . ":&nbsp;&nbsp;</td>\n";
    echo "<td width=\"99%\"><input type=\"text\" title=\"". $escaper->escapeHtml($lang['UseCommasToSeperateMultipleEmails']) ."\" name=\"email\" placeholer=\"" . $escaper->escapeHtml($lang['EmailAddress']) . "\" /></td>\n";
    echo "</tr>\n";
    echo "</table>\n";
    echo "<input type=\"submit\" name=\"send_assessment\" value=\"" . $escaper->escapeHtml($lang['Send']) . "\" />\n";
    echo "</form>\n";
    echo "</div>\n";
    echo "</div>\n";
    echo "</div>\n";
}

/*************************************
 * FUNCTION: PROCESS SENT ASSESSMENT *
 *************************************/
function process_sent_assessment()
{
    global $escaper;
    global $lang;

    // Get the assessment id
    $assessment_id = (int)$_POST['assessment'];

    // Get the assessment with that id
    $assessment = get_assessment_names($assessment_id);
    $assessment_name = $assessment['name'];

    // Get the email to send to
    $email = $_POST['email'];

    // Get who sent this assessment
    $sender = (int)$_SESSION['uid'];

    // Create a random key to access this assessment
    $key = generate_token(20);

    // Open the database connection
    $db = db_open();

    // Add the assessment tracking
    $stmt = $db->prepare("INSERT INTO `assessment_tracking` (`assessment_id`, `email`, `sender`, `key`) VALUES (:assessment_id, :email, :sender, :key);");
    $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
    $stmt->bindParam(":email", $email, PDO::PARAM_STR, 200);
    $stmt->bindParam(":sender", $sender, PDO::PARAM_INT);
    $stmt->bindParam(":key", $key, PDO::PARAM_STR, 20);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Create the message subject
    $subject = $escaper->escapeHtml($lang['RiskAssessmentQuestionnaire']);

    // Get the assessment URL
    $url = get_current_url();
    $pieces = explode("index.php", $url);
    $url = $pieces[0];

    // Create the message body
    $body = get_string_from_template($lang['EmailTemplateSendingAssessment'], array(
        'username' => $escaper->escapeHtml($_SESSION['name']),
        'assessment_name' => $assessment_name,
        'assessment_link' => $url . "assessment.php?key=" . $key,
    ));

    // Require the mail functions
    require_once(realpath(__DIR__ . '/../../includes/mail.php'));
    
    // Get multiple emails
    $emails = explode(",", $email);
    
    foreach($emails as $val){
        $val = trim($val);
        // Send the e-mail
        send_email($val, $val, $subject, $body);
    }

    // Display a message that the assessment was sent successfully
    set_alert(true, "good", "Assessment was sent to \"" . $email . "\".");
}

/*************************************
 * FUNCTION: IS VALID ASSESSMENT KEY *
 *************************************/
function is_valid_assessment_key($key)
{
    // Remove old assessments
    remove_old_assessments();

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `assessment_tracking` WHERE `key`=:key;");
    $stmt->bindParam(":key", $key, PDO::PARAM_STR, 20);
    $stmt->execute();

    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the query returned a value
    if (!empty($array))
    {
        return true;
    }
    else return false;
}

/***************************************
 * FUNCTION: GET ACTIVE QUESTIONNAIRES *
 **************************************/
function get_active_questionnaires()
{
    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT SQL_CALC_FOUND_ROWS t1.questionnaire_id, t1.token, t1.percent, t1.status tracking_status, t1.sent_at, t2.name questionnaire_name, t3.company contact_company, t3.name contact_name, t3.email contact_email
        FROM `questionnaire_tracking` t1 
            INNER JOIN `questionnaires` t2 ON t1.questionnaire_id=t2.id
            LEFT JOIN `assessment_contacts` t3 ON t1.contact_id=t3.id  
        WHERE
            t1.status=0;
    ";
    $stmt = $db->prepare($sql);

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();
    
    foreach($array as &$row){
        $row['contact_company'] = try_decrypt($row['contact_company']);
        $row['contact_name'] = try_decrypt($row['contact_name']);
        $row['contact_email'] = try_decrypt($row['contact_email']);
    }

    // Close the database connection
    db_close($db);

    return $array;
}

/***********************************
 * FUNCTION: GET ASSESSMENT BY KEY *
 ***********************************/
function get_assessment_by_key($key)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `assessment_tracking` WHERE ;");
    $stmt->bindParam(":key", $key, PDO::PARAM_STR, 20);
    $stmt->execute();

    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    return $array;
}

/*****************************************
 * FUNCTION: DELETE QUESTIONNAIRE BY KEY *
 *****************************************/
function delete_questionnaire_tracking_by_token($token)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        DELETE t1, t2, t3
          FROM `questionnaire_tracking` t1 LEFT JOIN `questionnaire_responses` t2 ON t1.id=t2.questionnaire_tracking_id
            LEFT JOIN `questionnaire_result_comments` t3 ON t1.id=t3.tracking_id
            WHERE t1.token=:token;
    ");
    $stmt->bindParam(":token", $token, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: REMOVE OLD ASSESSMENTS *
 ************************************/
function remove_old_assessments()
{
    // Get the assessment settings
    $configs = get_assessment_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("DELETE FROM `assessment_tracking` WHERE timestamp < (NOW() - INTERVAL :minutes MINUTE)");
    $stmt->bindParam(":minutes", $ASSESSMENT_MINUTES_VALID, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: REMOVE OLD QUESTIONNAIRES *
 ***************************************/
function remove_old_questionnaires()
{
    // Get the assessment settings
    $configs = get_assessment_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("DELETE FROM `questionnaire_tracking` WHERE status=0 AND sent_at>(updated_at - INTERVAL 3 SECOND) AND sent_at < (NOW() - INTERVAL :minutes MINUTE); ");
    $stmt->bindParam(":minutes", $ASSESSMENT_MINUTES_VALID, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/****************************************
 * FUNCTION: DISPLAY ACTIVE ASSESSMENTS *
 ****************************************/
function display_active_assessments()
{
    global $escaper;
    global $lang;

    // Remove any old questionnaires
    remove_old_questionnaires();

    // Get the list of active assessments
    $questionnaires = get_active_questionnaires();

    // Display the active assessments
    echo "<form method=\"post\" action=\"\">\n";
    echo "<p><h4>" . $escaper->escapeHtml($lang['ActiveAssessments']) . "</h4></p>\n";
    echo "<p><button type=\"submit\" name=\"delete_active_assessments\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Delete']) . "</button></p>\n";
    echo "<table class=\"table table-bordered table-condensed sortable\">\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" width=\"50px\"><input type=\"checkbox\" onclick=\"checkAll(this)\" />&nbsp;&nbsp;</th>\n";
    echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['QuestionnaireName']) ."</th>\n";
    echo "<th align=\"left\" width=\"\">". $escaper->escapeHtml($lang['SentTo']) ."</th>\n";
    echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['Key']) ."</th>\n";
    echo "<th align=\"left\" width=\"300px\">". $escaper->escapeHtml($lang['DateSubmitted']) ."</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    // For each assessment
    foreach ($questionnaires as $questionnaire)
    {
        echo "<tr>\n";
        echo "<td align=\"center\">\n";
        echo "<input type=\"checkbox\" name=\"tokens[]\" value=\"" . $escaper->escapeHtml($questionnaire['token']) . "\" />\n";
        echo "</td>\n";
        echo "<td align=\"left\" width=\"200px\">" . $escaper->escapeHtml($questionnaire['questionnaire_name']) . "</td>\n";
        echo "<td align=\"left\" width=\"150px\">" . $escaper->escapeHtml($questionnaire['contact_name']) . "</td>\n";
        echo "<td align=\"left\" width=\"300px\">" . $escaper->escapeHtml($questionnaire['token']) ."</td>\n";
        echo "<td align=\"left\" width=\"300px\">" . format_date($questionnaire['sent_at']) . "</td>\n";
        echo "</tr>\n";
    }

    echo "</tbody>\n";
    echo "</table>\n";
    echo "<p><button type=\"submit\" name=\"delete_active_assessments\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Delete']) . "</button></p>\n";
    echo "</form>\n";
}

/******************************************
 * FUNCTION: DELETE ACTIVE QUESTIONNAIRES *
 ******************************************/
function delete_active_questionnaires($tokens)
{
    // For each sent questionnaires
    foreach ($tokens as $token)
    {
        // Delete the assessment
        delete_questionnaire_tracking_by_token($token);
    }
}


/*********************************
 * FUNCTION: SUBMIT RISK SCORING *
 *********************************/
function add_assessment_scoring($data)
{
    // Risk scoring method
    // 1 = Classic
    // 2 = CVSS
    // 3 = DREAD
    // 4 = OWASP
    // 5 = Custom
    $scoring_method = (int)$data['scoring_method'];

    // Classic Risk Scoring Inputs
    $CLASSIC_likelihood = (int)$data['CLASSIClikelihood'];
    $CLASSIC_impact =(int) $data['CLASSICimpact'];

    // CVSS Risk Scoring Inputs
    $AccessVector = $data['CVSSAccessVector'];
    $AccessComplexity = $data['CVSSAccessComplexity'];
    $Authentication = $data['CVSSAuthentication'];
    $ConfImpact = $data['CVSSConfImpact'];
    $IntegImpact = $data['CVSSIntegImpact'];
    $AvailImpact = $data['CVSSAvailImpact'];
    $Exploitability = $data['CVSSExploitability'];
    $RemediationLevel = $data['CVSSRemediationLevel'];
    $ReportConfidence = $data['CVSSReportConfidence'];
    $CollateralDamagePotential = $data['CVSSCollateralDamagePotential'];
    $TargetDistribution = $data['CVSSTargetDistribution'];
    $ConfidentialityRequirement = $data['CVSSConfidentialityRequirement'];
    $IntegrityRequirement = $data['CVSSIntegrityRequirement'];
    $AvailabilityRequirement = $data['CVSSAvailabilityRequirement'];

    // DREAD Risk Scoring Inputs
    $DREADDamage = (int)$data['DREADDamage'];
    $DREADReproducibility = (int)$data['DREADReproducibility'];
    $DREADExploitability = (int)$data['DREADExploitability'];
    $DREADAffectedUsers = (int)$data['DREADAffectedUsers'];
    $DREADDiscoverability = (int)$data['DREADDiscoverability'];

    // OWASP Risk Scoring Inputs
    $OWASPSkill = (int)$data['OWASPSkillLevel'];
    $OWASPMotive = (int)$data['OWASPMotive'];
    $OWASPOpportunity = (int)$data['OWASPOpportunity'];
    $OWASPSize = (int)$data['OWASPSize'];
    $OWASPDiscovery = (int)$data['OWASPEaseOfDiscovery'];
    $OWASPExploit = (int)$data['OWASPEaseOfExploit'];
    $OWASPAwareness = (int)$data['OWASPAwareness'];
    $OWASPIntrusionDetection = (int)$data['OWASPIntrusionDetection'];
    $OWASPLossOfConfidentiality = (int)$data['OWASPLossOfConfidentiality'];
    $OWASPLossOfIntegrity = (int)$data['OWASPLossOfIntegrity'];
    $OWASPLossOfAvailability = (int)$data['OWASPLossOfAvailability'];
    $OWASPLossOfAccountability = (int)$data['OWASPLossOfAccountability'];
    $OWASPFinancialDamage = (int)$data['OWASPFinancialDamage'];
    $OWASPReputationDamage = (int)$data['OWASPReputationDamage'];
    $OWASPNonCompliance = (int)$data['OWASPNonCompliance'];
    $OWASPPrivacyViolation = (int)$data['OWASPPrivacyViolation'];

    // Custom Risk Scoring
    $custom = (float)$data['Custom'];

    // Open the database connection
    $db = db_open();

    // If the scoring method is Classic (1)
    if ($scoring_method == 1)
    {
        // Calculate the risk via classic method
        $calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

        // Create the database query
        $stmt = $db->prepare("INSERT INTO assessment_scoring (`scoring_method`, `calculated_risk`, `CLASSIC_likelihood`, `CLASSIC_impact`) VALUES (:scoring_method, :calculated_risk, :CLASSIC_likelihood, :CLASSIC_impact)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":CLASSIC_likelihood", $CLASSIC_likelihood, PDO::PARAM_INT);
        $stmt->bindParam(":CLASSIC_impact", $CLASSIC_impact, PDO::PARAM_INT);
    }
    // If the scoring method is CVSS (2)
    else if ($scoring_method == 2)
    {
        // Get the numeric values for the CVSS submission
        $AccessVectorScore = get_cvss_numeric_value("AV", $AccessVector);
        $AccessComplexityScore = get_cvss_numeric_value("AC", $AccessComplexity);
        $AuthenticationScore = get_cvss_numeric_value("Au", $Authentication);
        $ConfImpactScore = get_cvss_numeric_value("C", $ConfImpact);
        $IntegImpactScore = get_cvss_numeric_value("I", $IntegImpact);
        $AvailImpactScore = get_cvss_numeric_value("A", $AvailImpact);
        $ExploitabilityScore = get_cvss_numeric_value("E", $Exploitability);
        $RemediationLevelScore = get_cvss_numeric_value("RL", $RemediationLevel);
        $ReportConfidenceScore = get_cvss_numeric_value("RC", $ReportConfidence);
        $CollateralDamagePotentialScore = get_cvss_numeric_value("CDP", $CollateralDamagePotential);
        $TargetDistributionScore = get_cvss_numeric_value("TD", $TargetDistribution);
        $ConfidentialityRequirementScore = get_cvss_numeric_value("CR", $ConfidentialityRequirement);
        $IntegrityRequirementScore = get_cvss_numeric_value("IR", $IntegrityRequirement);
        $AvailabilityRequirementScore = get_cvss_numeric_value("AR", $AvailabilityRequirement);

        // Calculate the risk via CVSS method
        $calculated_risk = calculate_cvss_score($AccessVectorScore, $AccessComplexityScore, $AuthenticationScore, $ConfImpactScore, $IntegImpactScore, $AvailImpactScore, $ExploitabilityScore, $RemediationLevelScore, $ReportConfidenceScore, $CollateralDamagePotentialScore, $TargetDistributionScore, $ConfidentialityRequirementScore, $IntegrityRequirementScore, $AvailabilityRequirementScore);

        // Create the database query
        $stmt = $db->prepare("INSERT INTO assessment_scoring (`scoring_method`, `calculated_risk`, `CVSS_AccessVector`, `CVSS_AccessComplexity`, `CVSS_Authentication`, `CVSS_ConfImpact`, `CVSS_IntegImpact`, `CVSS_AvailImpact`, `CVSS_Exploitability`, `CVSS_RemediationLevel`, `CVSS_ReportConfidence`, `CVSS_CollateralDamagePotential`, `CVSS_TargetDistribution`, `CVSS_ConfidentialityRequirement`, `CVSS_IntegrityRequirement`, `CVSS_AvailabilityRequirement`) VALUES (:scoring_method, :calculated_risk, :CVSS_AccessVector, :CVSS_AccessComplexity, :CVSS_Authentication, :CVSS_ConfImpact, :CVSS_IntegImpact, :CVSS_AvailImpact, :CVSS_Exploitability, :CVSS_RemediationLevel, :CVSS_ReportConfidence, :CVSS_CollateralDamagePotential, :CVSS_TargetDistribution, :CVSS_ConfidentialityRequirement, :CVSS_IntegrityRequirement, :CVSS_AvailabilityRequirement)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":CVSS_AccessVector", $AccessVector, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AccessComplexity", $AccessComplexity, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_Authentication", $Authentication, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ConfImpact", $ConfImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_IntegImpact", $IntegImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AvailImpact", $AvailImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_Exploitability", $Exploitability, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_RemediationLevel", $RemediationLevel, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ReportConfidence", $ReportConfidence, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_CollateralDamagePotential", $CollateralDamagePotential, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_TargetDistribution", $TargetDistribution, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ConfidentialityRequirement", $ConfidentialityRequirement, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_IntegrityRequirement", $IntegrityRequirement, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AvailabilityRequirement", $AvailabilityRequirement, PDO::PARAM_STR, 3);
    }
    // If the scoring method is DREAD (3)
    else if ($scoring_method == 3)
    {
        // Calculate the risk via DREAD method
        $calculated_risk = ($DREADDamage + $DREADReproducibility + $DREADExploitability + $DREADAffectedUsers + $DREADDiscoverability)/5;

        // Create the database query
        $stmt = $db->prepare("INSERT INTO assessment_scoring (`scoring_method`, `calculated_risk`, `DREAD_DamagePotential`, `DREAD_Reproducibility`, `DREAD_Exploitability`, `DREAD_AffectedUsers`, `DREAD_Discoverability`) VALUES (:scoring_method, :calculated_risk, :DREAD_DamagePotential, :DREAD_Reproducibility, :DREAD_Exploitability, :DREAD_AffectedUsers, :DREAD_Discoverability)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":DREAD_DamagePotential", $DREADDamage, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Reproducibility", $DREADReproducibility, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Exploitability", $DREADExploitability, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_AffectedUsers", $DREADAffectedUsers, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Discoverability", $DREADDiscoverability, PDO::PARAM_INT);
    }
    // If the scoring method is OWASP (4)
    else if ($scoring_method == 4){
        $threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
        $vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

        // Average the threat agent and vulnerability factors to get the likelihood
        $OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;

        $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
        $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

        // Average the technical and business impacts to get the impact
        $OWASP_impact = ($technical_impact + $business_impact)/2;

        // Calculate the overall OWASP risk score
        $calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

        // Create the database query
        $stmt = $db->prepare("INSERT INTO assessment_scoring (`scoring_method`, `calculated_risk`, `OWASP_SkillLevel`, `OWASP_Motive`, `OWASP_Opportunity`, `OWASP_Size`, `OWASP_EaseOfDiscovery`, `OWASP_EaseOfExploit`, `OWASP_Awareness`, `OWASP_IntrusionDetection`, `OWASP_LossOfConfidentiality`, `OWASP_LossOfIntegrity`, `OWASP_LossOfAvailability`, `OWASP_LossOfAccountability`, `OWASP_FinancialDamage`, `OWASP_ReputationDamage`, `OWASP_NonCompliance`, `OWASP_PrivacyViolation`) VALUES (:scoring_method, :calculated_risk, :OWASP_SkillLevel, :OWASP_Motive, :OWASP_Opportunity, :OWASP_Size, :OWASP_EaseOfDiscovery, :OWASP_EaseOfExploit, :OWASP_Awareness, :OWASP_IntrusionDetection, :OWASP_LossOfConfidentiality, :OWASP_LossOfIntegrity, :OWASP_LossOfAvailability, :OWASP_LossOfAccountability, :OWASP_FinancialDamage, :OWASP_ReputationDamage, :OWASP_NonCompliance, :OWASP_PrivacyViolation)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":OWASP_SkillLevel", $OWASPSkill, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Motive", $OWASPMotive, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Opportunity",$OWASPOpportunity, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Size",$OWASPSize, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_EaseOfDiscovery",$OWASPDiscovery, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_EaseOfExploit",$OWASPExploit, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Awareness",$OWASPAwareness, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_IntrusionDetection",$OWASPIntrusionDetection, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfConfidentiality",$OWASPLossOfConfidentiality, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfIntegrity",$OWASPLossOfIntegrity, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfAvailability",$OWASPLossOfAvailability, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfAccountability",$OWASPLossOfAccountability, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_FinancialDamage",$OWASPFinancialDamage, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_ReputationDamage",$OWASPReputationDamage, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_NonCompliance",$OWASPNonCompliance, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_PrivacyViolation",$OWASPPrivacyViolation, PDO::PARAM_INT);
    }
    // If the scoring method is Custom (5)
    else if ($scoring_method == 5){
        // If the custom value is not between 0 and 10
        if (!(($custom >= 0) && ($custom <= 10)))
        {
            // Set the custom value to 10
            $custom = get_setting('default_risk_score');
        }

        // Calculated risk is the custom value
        $calculated_risk = $custom;

        // Create the database query
        $stmt = $db->prepare("INSERT INTO assessment_scoring (`scoring_method`, `calculated_risk`, `Custom`) VALUES (:scoring_method, :calculated_risk, :Custom)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":Custom", $custom, PDO::PARAM_STR, 5);
    }
    // Otherwise
    else
    {
        return false;
    }

    // Add the risk score
    $stmt->execute();
    
    // Close the database connection
    db_close($db);

    $last_insert_id = $db->lastInsertId();

    return array($last_insert_id, $calculated_risk);
}

/*********************************
 * FUNCTION: UPDATE RISK SCORING *
 *********************************/
function update_assessment_scoring($id, $data)
{
    // Risk scoring method
    // 1 = Classic
    // 2 = CVSS
    // 3 = DREAD
    // 4 = OWASP
    // 5 = Custom
    $scoring_method = (int)$data['scoring_method'];

    // Classic Risk Scoring Inputs
    $CLASSIC_likelihood = (int)$data['CLASSIClikelihood'];
    $CLASSIC_impact =(int) $data['CLASSICimpact'];

    // CVSS Risk Scoring Inputs
    $AccessVector = $data['CVSSAccessVector'];
    $AccessComplexity = $data['CVSSAccessComplexity'];
    $Authentication = $data['CVSSAuthentication'];
    $ConfImpact = $data['CVSSConfImpact'];
    $IntegImpact = $data['CVSSIntegImpact'];
    $AvailImpact = $data['CVSSAvailImpact'];
    $Exploitability = $data['CVSSExploitability'];
    $RemediationLevel = $data['CVSSRemediationLevel'];
    $ReportConfidence = $data['CVSSReportConfidence'];
    $CollateralDamagePotential = $data['CVSSCollateralDamagePotential'];
    $TargetDistribution = $data['CVSSTargetDistribution'];
    $ConfidentialityRequirement = $data['CVSSConfidentialityRequirement'];
    $IntegrityRequirement = $data['CVSSIntegrityRequirement'];
    $AvailabilityRequirement = $data['CVSSAvailabilityRequirement'];

    // DREAD Risk Scoring Inputs
    $DREADDamage = (int)$data['DREADDamage'];
    $DREADReproducibility = (int)$data['DREADReproducibility'];
    $DREADExploitability = (int)$data['DREADExploitability'];
    $DREADAffectedUsers = (int)$data['DREADAffectedUsers'];
    $DREADDiscoverability = (int)$data['DREADDiscoverability'];

    // OWASP Risk Scoring Inputs
    $OWASPSkill = (int)$data['OWASPSkillLevel'];
    $OWASPMotive = (int)$data['OWASPMotive'];
    $OWASPOpportunity = (int)$data['OWASPOpportunity'];
    $OWASPSize = (int)$data['OWASPSize'];
    $OWASPDiscovery = (int)$data['OWASPEaseOfDiscovery'];
    $OWASPExploit = (int)$data['OWASPEaseOfExploit'];
    $OWASPAwareness = (int)$data['OWASPAwareness'];
    $OWASPIntrusionDetection = (int)$data['OWASPIntrusionDetection'];
    $OWASPLossOfConfidentiality = (int)$data['OWASPLossOfConfidentiality'];
    $OWASPLossOfIntegrity = (int)$data['OWASPLossOfIntegrity'];
    $OWASPLossOfAvailability = (int)$data['OWASPLossOfAvailability'];
    $OWASPLossOfAccountability = (int)$data['OWASPLossOfAccountability'];
    $OWASPFinancialDamage = (int)$data['OWASPFinancialDamage'];
    $OWASPReputationDamage = (int)$data['OWASPReputationDamage'];
    $OWASPNonCompliance = (int)$data['OWASPNonCompliance'];
    $OWASPPrivacyViolation = (int)$data['OWASPPrivacyViolation'];

    // Custom Risk Scoring
    $custom = (float)$data['Custom'];


    // Open the database connection
    $db = db_open();

    // If the scoring method is Classic (1)
    if ($scoring_method == 1)
    {
            // Calculate the risk via classic method
            $calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

            // Create the database query
            $stmt = $db->prepare("UPDATE assessment_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, CLASSIC_likelihood=:CLASSIC_likelihood, CLASSIC_impact=:CLASSIC_impact WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":CLASSIC_likelihood", $CLASSIC_likelihood, PDO::PARAM_INT);
            $stmt->bindParam(":CLASSIC_impact", $CLASSIC_impact, PDO::PARAM_INT);
    }
    // If the scoring method is CVSS (2)
    else if ($scoring_method == 2)
    {
            // Get the numeric values for the CVSS submission
            $AccessVectorScore = get_cvss_numeric_value("AV", $AccessVector);
            $AccessComplexityScore = get_cvss_numeric_value("AC", $AccessComplexity);
            $AuthenticationScore = get_cvss_numeric_value("Au", $Authentication);
            $ConfImpactScore = get_cvss_numeric_value("C", $ConfImpact);
            $IntegImpactScore = get_cvss_numeric_value("I", $IntegImpact);
            $AvailImpactScore = get_cvss_numeric_value("A", $AvailImpact);
            $ExploitabilityScore = get_cvss_numeric_value("E", $Exploitability);
            $RemediationLevelScore = get_cvss_numeric_value("RL", $RemediationLevel);
            $ReportConfidenceScore = get_cvss_numeric_value("RC", $ReportConfidence);
            $CollateralDamagePotentialScore = get_cvss_numeric_value("CDP", $CollateralDamagePotential);
            $TargetDistributionScore = get_cvss_numeric_value("TD", $TargetDistribution);
            $ConfidentialityRequirementScore = get_cvss_numeric_value("CR", $ConfidentialityRequirement);
            $IntegrityRequirementScore = get_cvss_numeric_value("IR", $IntegrityRequirement);
            $AvailabilityRequirementScore = get_cvss_numeric_value("AR", $AvailabilityRequirement);

            // Calculate the risk via CVSS method
            $calculated_risk = calculate_cvss_score($AccessVectorScore, $AccessComplexityScore, $AuthenticationScore, $ConfImpactScore, $IntegImpactScore, $AvailImpactScore, $ExploitabilityScore, $RemediationLevelScore, $ReportConfidenceScore, $CollateralDamagePotentialScore, $TargetDistributionScore, $ConfidentialityRequirementScore, $IntegrityRequirementScore, $AvailabilityRequirementScore);
            

            // Create the database query
            $stmt = $db->prepare("UPDATE assessment_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, CVSS_AccessVector=:CVSS_AccessVector, CVSS_AccessComplexity=:CVSS_AccessComplexity, CVSS_Authentication=:CVSS_Authentication, CVSS_ConfImpact=:CVSS_ConfImpact, CVSS_IntegImpact=:CVSS_IntegImpact, CVSS_AvailImpact=:CVSS_AvailImpact, CVSS_Exploitability=:CVSS_Exploitability, CVSS_RemediationLevel=:CVSS_RemediationLevel, CVSS_ReportConfidence=:CVSS_ReportConfidence, CVSS_CollateralDamagePotential=:CVSS_CollateralDamagePotential, CVSS_TargetDistribution=:CVSS_TargetDistribution, CVSS_ConfidentialityRequirement=:CVSS_ConfidentialityRequirement, CVSS_IntegrityRequirement=:CVSS_IntegrityRequirement, CVSS_AvailabilityRequirement=:CVSS_AvailabilityRequirement WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":CVSS_AccessVector", $AccessVector, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_AccessComplexity", $AccessComplexity, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_Authentication", $Authentication, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_ConfImpact", $ConfImpact, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_IntegImpact", $IntegImpact, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_AvailImpact", $AvailImpact, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_Exploitability", $Exploitability, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_RemediationLevel", $RemediationLevel, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_ReportConfidence", $ReportConfidence, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_CollateralDamagePotential", $CollateralDamagePotential, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_TargetDistribution", $TargetDistribution, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_ConfidentialityRequirement", $ConfidentialityRequirement, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_IntegrityRequirement", $IntegrityRequirement, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_AvailabilityRequirement", $AvailabilityRequirement, PDO::PARAM_STR, 3);
    }
    // If the scoring method is DREAD (3)
    else if ($scoring_method == 3)
    {
            // Calculate the risk via DREAD method
            $calculated_risk = ($DREADDamage + $DREADReproducibility + $DREADExploitability + $DREADAffectedUsers + $DREADDiscoverability)/5;

            // Create the database query
            $stmt = $db->prepare("UPDATE assessment_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, DREAD_DamagePotential=:DREAD_DamagePotential, DREAD_Reproducibility=:DREAD_Reproducibility, DREAD_Exploitability=:DREAD_Exploitability, DREAD_AffectedUsers=:DREAD_AffectedUsers, DREAD_Discoverability=:DREAD_Discoverability WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":DREAD_DamagePotential", $DREADDamage, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_Reproducibility", $DREADReproducibility, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_Exploitability", $DREADExploitability, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_AffectedUsers", $DREADAffectedUsers, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_Discoverability", $DREADDiscoverability, PDO::PARAM_INT);
    }
    // If the scoring method is OWASP (4)
    else if ($scoring_method == 4)
    {
            $threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
            $vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

            // Average the threat agent and vulnerability factors to get the likelihood
            $OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;

            $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
            $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

            // Average the technical and business impacts to get the impact
            $OWASP_impact = ($technical_impact + $business_impact)/2;

            // Calculate the overall OWASP risk score
            $calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

            // Create the database query
            $stmt = $db->prepare("UPDATE assessment_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, OWASP_SkillLevel=:OWASP_SkillLevel, OWASP_Motive=:OWASP_Motive, OWASP_Opportunity=:OWASP_Opportunity, OWASP_Size=:OWASP_Size, OWASP_EaseOfDiscovery=:OWASP_EaseOfDiscovery, OWASP_EaseOfExploit=:OWASP_EaseOfExploit, OWASP_Awareness=:OWASP_Awareness, OWASP_IntrusionDetection=:OWASP_IntrusionDetection, OWASP_LossOfConfidentiality=:OWASP_LossOfConfidentiality, OWASP_LossOfIntegrity=:OWASP_LossOfIntegrity, OWASP_LossOfAvailability=:OWASP_LossOfAvailability, OWASP_LossOfAccountability=:OWASP_LossOfAccountability, OWASP_FinancialDamage=:OWASP_FinancialDamage, OWASP_ReputationDamage=:OWASP_ReputationDamage, OWASP_NonCompliance=:OWASP_NonCompliance, OWASP_PrivacyViolation=:OWASP_PrivacyViolation WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":OWASP_SkillLevel", $OWASPSkill, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Motive", $OWASPMotive, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Opportunity",$OWASPOpportunity, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Size",$OWASPSize, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_EaseOfDiscovery",$OWASPDiscovery, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_EaseOfExploit",$OWASPExploit, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Awareness",$OWASPAwareness, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_IntrusionDetection",$OWASPIntrusionDetection, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfConfidentiality",$OWASPLossOfConfidentiality, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfIntegrity",$OWASPLossOfIntegrity, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfAvailability",$OWASPLossOfAvailability, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfAccountability",$OWASPLossOfAccountability, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_FinancialDamage",$OWASPFinancialDamage, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_ReputationDamage",$OWASPReputationDamage, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_NonCompliance",$OWASPNonCompliance, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_PrivacyViolation",$OWASPPrivacyViolation, PDO::PARAM_INT);
    }
    // If the scoring method is Custom (5)
    else if ($scoring_method == 5)
    {
            // If the custom value is not between 0 and 10
            if (!(($custom >= 0) && ($custom <= 10)))
            {
                    // Set the custom value to 10
                    $custom = 10;
            }

            // Calculated risk is the custom value
            $calculated_risk = $custom;

            // Create the database query
            $stmt = $db->prepare("UPDATE assessment_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, Custom=:Custom WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":Custom", $custom, PDO::PARAM_STR, 5);
    }
    // Otherwise
    else
    {
            return false;
    }

    // Add the risk score
    $stmt->execute();

    // Close the database connection
    db_close($db);
    
    return $calculated_risk;
}

/***********************************************************
* FUNCTION: VIEW PRINT RISK SCORE FORMS IN EDIT ASSESSMENT *
************************************************************/
function display_score_html_from_assessment($key="", $scoring_method="5", $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector="N", $AccessComplexity="L", $Authentication="N", $ConfImpact="C", $IntegImpact="C", $AvailImpact="C", $Exploitability="ND", $RemediationLevel="ND", $ReportConfidence="ND", $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamagePotential="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkillLevel="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPEaseOfDiscovery="10", $OWASPEaseOfExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10", $custom=false, $ContributingLikelihood="", $ContributingImpacts=[]){
    global $escaper;
    global $lang;
    
    if($custom === false){
        $custom = get_setting("default_risk_score");
    }
    
    if(!$scoring_method)
        $scoring_method = 5;
        
    $html = "
        <div class='row-fluid' >
            <div class='span5 text-right'>". $escaper->escapeHtml($lang['RiskScoringMethod']) .": &nbsp;</div>
            <div class='span7'>"
            .create_dropdown("scoring_methods", $scoring_method, "scoring_method[{$key}]", false, false, true, " class='scoring-method' ").
            "
                <!-- select class='form-control' name='scoring_method' id='select' >
                    <option selected value='1'>Classic</option>
                    <option value='2'>CVSS</option>
                    <option value='3'>DREAD</option>
                    <option value='4'>OWASP</option>
                    <option value='5'>Custom</option>
                </select -->
            </div>
        </div>
        <div id='classic' class='classic-holder' style='display:". ($scoring_method == 1 ? "block" : "none") ."'>
            <div class='row-fluid'>
                <div class='span5 text-right'>". $escaper->escapeHtml($lang['CurrentLikelihood']) .":</div>
                <div class='span7'>". create_dropdown('likelihood', $CLASSIC_likelihood, 'likelihood['.$key.']', true, false, true) ."</div>
            </div>
            <div class='row-fluid'>
                <div class='span5 text-right'>". $escaper->escapeHtml($lang['CurrentImpact']) .":</div>
                <div class='span7'>". create_dropdown('impact', $CLASSIC_impact, 'impact['.$key.']', true, false, true) ."</div>
            </div>
        </div>
        <div id='cvss' style='display: ". ($scoring_method == 2 ? "block" : "none") .";' class='cvss-holder'>
            <div class='row-fluid'>
                <div class='span5 text-right'>&nbsp;</div>
                <div class='span7'><p><input type='button' name='cvssSubmit' id='cvssSubmit' value='Score Using CVSS' /></p></div>
            </div>
            <input type='hidden' name='AccessVector[{$key}]' id='AccessVector' value='{$AccessVector}' />
            <input type='hidden' name='AccessComplexity[{$key}]' id='AccessComplexity' value='{$AccessComplexity}' />
            <input type='hidden' name='Authentication[{$key}]' id='Authentication' value='{$Authentication}' />
            <input type='hidden' name='ConfImpact[{$key}]' id='ConfImpact' value='{$ConfImpact}' />
            <input type='hidden' name='IntegImpact[{$key}]' id='IntegImpact' value='{$IntegImpact}' />
            <input type='hidden' name='AvailImpact[{$key}]' id='AvailImpact' value='{$AvailImpact}' />
            <input type='hidden' name='Exploitability[{$key}]' id='Exploitability' value='{$Exploitability}' />
            <input type='hidden' name='RemediationLevel[{$key}]' id='RemediationLevel' value='{$RemediationLevel}' />
            <input type='hidden' name='ReportConfidence[{$key}]' id='ReportConfidence' value='{$ReportConfidence}' />
            <input type='hidden' name='CollateralDamagePotential[{$key}]' id='CollateralDamagePotential' value='{$CollateralDamagePotential}' />
            <input type='hidden' name='TargetDistribution[{$key}]' id='TargetDistribution' value='{$TargetDistribution}' />
            <input type='hidden' name='ConfidentialityRequirement[{$key}]' id='ConfidentialityRequirement' value='{$ConfidentialityRequirement}' />
            <input type='hidden' name='IntegrityRequirement[{$key}]' id='IntegrityRequirement' value='{$IntegrityRequirement}' />
            <input type='hidden' name='AvailabilityRequirement[{$key}]' id='AvailabilityRequirement' value='{$AvailabilityRequirement}' />
        </div>
        <div id='dread' style='display: ". ($scoring_method == 3 ? "block" : "none") .";' class='dread-holder'>
            <div class='row-fluid'>
                <div class='span5 text-right'>&nbsp;</div>
                <div class='span7'><p><input type='button' name='dreadSubmit' id='dreadSubmit' value='Score Using DREAD' onclick='javascript: popupdread();' /></p></div>
            </div>
            <input type='hidden' name='DREADDamage[{$key}]' id='DREADDamage' value='{$DREADDamagePotential}' />
            <input type='hidden' name='DREADReproducibility[{$key}]' id='DREADReproducibility' value='{$DREADReproducibility}' />
            <input type='hidden' name='DREADExploitability[{$key}]' id='DREADExploitability' value='{$DREADExploitability}' />
            <input type='hidden' name='DREADAffectedUsers[{$key}]' id='DREADAffectedUsers' value='{$DREADAffectedUsers}' />
            <input type='hidden' name='DREADDiscoverability[{$key}]' id='DREADDiscoverability' value='{$DREADDiscoverability}' />
        </div>
        <div id='owasp' style='display: ". ($scoring_method == 4 ? "block" : "none") .";' class='owasp-holder'>
            <div class='row-fluid'>
                <div class='span5 text-right'>&nbsp;</div>
                <div class='span7'><p><input type='button' name='owaspSubmit' id='owaspSubmit' value='Score Using OWASP' /></p></div>
            </div>
            <input type='hidden' name='OWASPSkillLevel[{$key}]' id='OWASPSkillLevel' value='{$OWASPSkillLevel}' />
            <input type='hidden' name='OWASPMotive[{$key}]' id='OWASPMotive' value='{$OWASPMotive}' />
            <input type='hidden' name='OWASPOpportunity[{$key}]' id='OWASPOpportunity' value='{$OWASPOpportunity}' />
            <input type='hidden' name='OWASPSize[{$key}]' id='OWASPSize' value='{$OWASPSize}' />
            <input type='hidden' name='OWASPEaseOfDiscovery[{$key}]' id='OWASPEaseOfDiscovery' value='{$OWASPEaseOfDiscovery}' />
            <input type='hidden' name='OWASPEaseOfExploit[{$key}]' id='OWASPEaseOfExploit' value='{$OWASPEaseOfExploit}' />
            <input type='hidden' name='OWASPAwareness[{$key}]' id='OWASPAwareness' value='{$OWASPAwareness}' />
            <input type='hidden' name='OWASPIntrusionDetection[{$key}]' id='OWASPIntrusionDetection' value='{$OWASPIntrusionDetection}' />
            <input type='hidden' name='OWASPLossOfConfidentiality[{$key}]' id='OWASPLossOfConfidentiality' value='{$OWASPLossOfConfidentiality}' />
            <input type='hidden' name='OWASPLossOfIntegrity[{$key}]' id='OWASPLossOfIntegrity' value='{$OWASPLossOfIntegrity}' />
            <input type='hidden' name='OWASPLossOfAvailability[{$key}]' id='OWASPLossOfAvailability' value='{$OWASPLossOfAvailability}' />
            <input type='hidden' name='OWASPLossOfAccountability[{$key}]' id='OWASPLossOfAccountability' value='{$OWASPLossOfAccountability}' />
            <input type='hidden' name='OWASPFinancialDamage[{$key}]' id='OWASPFinancialDamage' value='{$OWASPFinancialDamage}' />
            <input type='hidden' name='OWASPReputationDamage[{$key}]' id='OWASPReputationDamage' value='{$OWASPReputationDamage}' />
            <input type='hidden' name='OWASPNonCompliance[{$key}]' id='OWASPNonCompliance' value='{$OWASPNonCompliance}' />
            <input type='hidden' name='OWASPPrivacyViolation[{$key}]' id='OWASPPrivacyViolation' value='{$OWASPPrivacyViolation}' />
        </div>
        <div id='custom' style='display: ". ($scoring_method == 5 ? "block" : "none") .";' class='custom-holder'>
            <div class='row-fluid'>
                <div class='span5 text-right'>
                    ". $escaper->escapeHtml($lang['CustomValue']) .":
                </div>
                <div class='span7'>
                    <input type='number' min='0' step='0.1' max='10' name='Custom[{$key}]' id='Custom' value='{$custom}' /> 
                    <small>(Must be a numeric value between 0 and 10)</small>
                </div>
            </div>
        </div>
        <div id='contributing-risk' style='display: ". ($scoring_method == 6 ? "block" : "none") .";' class='contributing-risk-holder'>
            <table width='100%'>
                <tr>
                    <td width='41.7%'>&nbsp;</td>
                    <td><p><input type='button' name='contributingRiskSubmit' id='contributingRiskSubmit' value='". $escaper->escapeHtml($lang["ScoreUsingContributingRisk"]) ."' /></p></td>
                </tr>
            </table>
            <input type='hidden' name='ContributingLikelihood[{$key}]' id='contributing_likelihood' value='".($ContributingLikelihood ? $ContributingLikelihood : count(get_table("likelihood")))."' />";
            
            $max_impact_value = count(get_table("impact"));

            $contributing_risks = get_contributing_risks();
            foreach($contributing_risks as $contributing_risk){
                $html .= "<input type='hidden' class='contributing-impact' name='ContributingImpacts[{$contributing_risk['id']}][{$key}]' id='contributing_impact_{$contributing_risk['id']}' value='". (empty($ContributingImpacts[ $contributing_risk['id'] ]) ? $max_impact_value : $ContributingImpacts[ $contributing_risk['id'] ]) ."' />";
            }
            
            $html .= "
        </div>
    
    ";
    
    echo $html;
}

/****************************************
 * FUNCTION: DISPLAY EXPORT ASSESSMENTS *
 ****************************************/
function display_export_assessments()
{
    global $escaper;
    global $lang;

    echo "<form name=\"export\" id=\"export\" method=\"post\" action=\"\">\n";
    echo "<input type=\"hidden\" name=\"upload_type\" value=\"export\">";
    // Display export assessment form
    echo $escaper->escapeHtml($lang['SelectAssessmentToExport'])."<br />\n";
    echo "<div>";
    assessment_dropdown();
    echo "</div>";
    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"assessments_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['ExportAssessment']) . "</button>\n";
    echo "</div>\n";
    echo "</form>\n";
}

/*********************************
 * FUNCTION: ASSESSMENT DROPDOWN *
 *********************************/
function assessment_dropdown()
{
    global $escaper;
    global $lang;

    // Get the list of assessment
    list($totalCount, $templates) = get_assessment_questionnaire_templates();

    echo "<select name=\"assessment\" >\n";

    echo "<option value=\"\">--- " . $escaper->escapeHtml($lang['ALL']) . " ---</option>\n";
    // For each field
    foreach ($templates as $template)
    {
        echo "<option value=\"" . $escaper->escapeHtml($template['id']) . "\">" . $escaper->escapeHtml($template['name']) . "</option>\n";
    }

    echo "</select>\n";
}

function display_import_assessment_form(){
    global $escaper;
    global $lang;
    
    echo "<h4>". $escaper->escapeHtml($lang['ImportAssessments']) ."</h4>\n";
    echo "<form method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
        echo "<input type=\"hidden\" name=\"upload_type\" value=\"import\">";
        echo $escaper->escapeHtml($lang['ImportCsvXlsFile']).":<br />\n";
        echo "<input type=\"file\" name=\"file\" />\n";
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
        echo "<div class=\"form-actions\">\n";
        echo "<button type=\"submit\" name=\"import_assessment_csv\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
        echo "</div>\n";
    echo "</form>\n";
}

/***************************************
 * FUNCTION: DISPLAY IMPORT ASSESSMENT *
 ***************************************/
function display_import_assessments(){
    global $escaper;
    global $lang;

    // If a file has not been imported or mapped
    if (!isset($_POST['import_assessment_csv']) && !isset($_POST['assessment_csv_mapped']))
    {
        // Display import assessment form
        display_import_assessment_form();
    }
    // If a file has been imported and mapped
    else if (isset($_POST['assessment_csv_mapped']))
    {
        // Copy posted values into a new array
        $mappings = $_POST;

        // Remove the first value in the array (CSRF Token)
        array_shift($mappings);

        // Remove the last value in the array (Submit Button)
        array_pop($mappings);

        // Import using the mapping
        import_assessments_with_mapping($mappings);
        
        // Refresh current page
        header('Location: '.$_SERVER['REQUEST_URI']);
    }
    // If a file has been imported
    else
    {
        // Import the file
//        $display = import_csv($_FILES['file']);
        $filename = upload_assessment_import_file($_FILES['file']);

        // If the file import was successful
        if ($filename)
        {
            $header_columns = get_assessment_column_headers(sys_get_temp_dir() . "/" . $filename);

            echo "<form name=\"import\" id=\"import\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
                echo "<input type=\"hidden\" name=\"upload_type\" value=\"import\">";
                echo "<input type=\"checkbox\" name=\"import_first\" />&nbsp;Import First Row\n";
                echo "<br /><br />\n";
                echo "<table class=\"table table-bordered table-condensed sortable\">\n";
                echo "<thead>\n";
                echo "<tr>\n";
                echo "<th width=\"200px\">File Columns</th>\n";
                echo "<th>".$escaper->escapeHtml($lang['Spreadsheet'])."</th>\n";
                echo "</tr>\n";
                echo "</thead>\n";
                echo "<tbody>\n";

                // Column counter
                $col_counter = 0;

                // For each column in the file
                foreach ($header_columns as $column)
                {
                    echo "<tr>\n";
                    echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                    echo "<td>\n";
                    assessment_column_name_dropdown("col_" . $col_counter);
                    echo "</td>\n";
                    echo "</tr>\n";

                    // Increment the column counter
                    $col_counter++;
                }

                echo "</tbody>\n";
                echo "</table>\n";
                echo "<div><input type=\"hidden\" name=\"filename\" value=\"{$filename}\"></div>";
                echo "<div class=\"form-actions\">\n";
                echo "<button type=\"submit\" name=\"assessment_csv_mapped\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
                echo "</div>\n";
            echo "</form>\n";
        }
        // Otherwise, file import error
        else
        {
            // Get any alert messages
            //get_alert();
            refresh();
        }
    }
}

/*********************************************
 * FUNCTION: ASSESSMENT COLUMN NAME DROPDOWN *
 *********************************************/
function assessment_column_name_dropdown($name)
{
    global $escaper;

    // Get the list of asset fields
    $fields = assessment_fields();

    echo "<select class='mapping' name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" onchange=\"removeSelected(this.name, this.value)\">\n";
    echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

    // For each field
    foreach ($fields as $key => $value)
    {
        echo "<option value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
    }

    echo "</select>\n";
}

/*******************************
 * FUNCTION: ASSESSMENT FIELDS *
 *******************************/
function assessment_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'template_name'   =>$lang['QuestionnaireTemplateName'],
        'question_id'     =>$lang['QuestionID'],
        'question'         =>$lang['Question'],
        'has_file'         =>$lang['HasFile'],
        'question_ordering'=>$lang['QuestionOrdering'],
        'answer'           =>$lang['Answer'],
        'submit_risk'      =>$lang['SubmitRisk'],
        'risk_subject'     =>$lang['Subject'],
        'risks_owner'       =>$lang['Owner'],
        'assets'            =>$lang['AffectedAssets'],
        'sub_questions'     =>$lang['SubQuestions'],
        'riskscoring_scoring_method'                    =>$lang['RiskScoringMethod'],
        'riskscoring_calculated_risk'                   =>$lang['CalculatedRisk'],
        'riskscoring_CLASSIC_likelihood'                =>$lang['CurrentLikelihood'],
        'riskscoring_CLASSIC_impact'                    =>$lang['CurrentImpact'],
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
    );

    // Return the fields array
    return $fields;
}

/**********************************************
 * FUNCTION: GET QUESTIONNAIRE ID BY QUESTION *
 **********************************************/
function get_questionnaire_question_id_by_text($question)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `questionnaire_questions` WHERE `question`=:question;");
    $stmt->bindParam(":question", $question, PDO::PARAM_STR);
    $stmt->execute();

    $array = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    return empty($array) ? 0 : $array['id'];
}

/*********************************************
 * FUNCTION: IMPORT ASSESSMENTS WITH MAPPING *
 *********************************************/
function import_assessments_with_mapping($mappings)
{
    global $escaper;
    global $lang;

    $filename = $_POST['filename'];
    $filepath = sys_get_temp_dir() . "/" . $filename;

    // Open the temporary file for reading
    ini_set('auto_detect_line_endings', true);
    
    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

    // Open the database connection
    $db = db_open();

    // Detect first line
    $first_line = true;

    // If we can read the temporary file
//    if (($handle = fopen(sys_get_temp_dir() . '/import.csv', "r")) !== FALSE)
    if (($handle = fopen($filepath, "r")) !== FALSE)
    {
        // Get questionnaire template names
        list($cnt, $templates) = get_assessment_questionnaire_templates();
        $template_list = array();
        $assessment_question_list = array();
        foreach($templates as $template){
            $template_list[$template['id']] = $template['name'];
        }
        
        // Inserted question ID list
        $new_question_ids = [];
        
        // New question ids by old question ID Key
        $old_new_question_ids = [];
        
        // Existing question ids by old question ID Key
        $db_question_ids = [];
        
        // Old sub question ID list, key means Answer ID
        $old_sub_questions = [];
        
        // While we have lines in the file to read
        while (($csv_line = fgetcsv($handle)) !== FALSE)
        {
            // If we can import the first line or this is not the first line
            if (isset($_POST['import_first']) || $first_line == false)
            {
                // Get the name
                $template_name = core_get_mapping_value("template_", "name", $mappings, $csv_line);
                
                // If a template was imported, get template ID
                if($template_name)
                {
                    // If Assessment is not new one.
                    if(in_array($template_name, $template_list)){
                        $template_id = array_search($template_name, $template_list);
                    }else{
                        $template_id = add_questionnaire_template($template_name);
                        
                        $template_list[$template_id] = $template_name;
                    }
                }
                else
                {
                    $template_id = 0;
                }
                
                // Get question
                $question = core_get_mapping_value("question", "", $mappings, $csv_line);
                
                // Get question ID
                $question_id = core_get_mapping_value("question_", "id", $mappings, $csv_line);

                // If question was imported
                if($question && $question_id){
                    
                    if(!isset($db_question_ids[$question."__".$question_id])){
                        $db_question_ids[$question."__".$question_id] = get_questionnaire_question_id_by_text($question);
                    }
                    
                    if(!isset($new_question_ids[$question."__".$question_id]))
                    {
                        $has_file = (int)core_get_mapping_value("has_", "file", $mappings, $csv_line);
                        
                        $question_ordering = (int)core_get_mapping_value("question_", "ordering", $mappings, $csv_line);
                        
                        // Set the ID as new question ID there is question text in db
                        if($db_question_ids[$question."__".$question_id])
                        {
                            $new_question_id = $db_question_ids[$question."__".$question_id];
                        }
                        else
                        {
                            $new_question_id = add_questionnaire_question_answers(["question" => $question, "has_file" => $has_file]);
                        }
                        

                        // Build old key and new value question relationship
                        $old_new_question_ids[$question_id] = $new_question_id;
                        
                        $new_question_ids[$question."__".$question_id] = $new_question_id;

                        // Set relationship between template and question
                        if($template_id)
                        {
                            // Delete existing relation between template and question
                            $stmt = $db->prepare("DELETE FROM `questionnaire_template_question` WHERE `questionnaire_template_id`=:questionnaire_template_id AND `questionnaire_question_id`=:questionnaire_question_id;");
                            $stmt->bindParam(":questionnaire_template_id", $template_id, PDO::PARAM_INT);
                            $stmt->bindParam(":questionnaire_question_id", $new_question_id, PDO::PARAM_INT);
                            $stmt->execute();
                            
                            // Query the database
                            $stmt = $db->prepare("INSERT INTO `questionnaire_template_question` (`questionnaire_template_id`, `questionnaire_question_id`, `ordering`) VALUES (:questionnaire_template_id, :questionnaire_question_id, :ordering);");
                            $stmt->bindParam(":questionnaire_template_id", $template_id, PDO::PARAM_INT);
                            $stmt->bindParam(":questionnaire_question_id", $new_question_id, PDO::PARAM_INT);
                            $stmt->bindParam(":ordering", $question_ordering, PDO::PARAM_INT);
                            
                            // Create a relation
                            $stmt->execute();
                        }
                    }
                    else
                    {
                        $new_question_id = $new_question_ids[$question."__".$question_id];
                    }
                    
                    // If question text already exists in db.
                    if($db_question_ids[$question."__".$question_id])
                    {
                        continue;
                    }

                    /************ Save assessment scoring *************/
                    // Get the risk scoring method
                    $scoring_method = core_get_mapping_value("riskscoring_", "scoring_method", $mappings, $csv_line);
                    

                    // Get the scoring method id
                    $scoring_method_id = get_value_by_name("scoring_methods", $scoring_method);

                    // If the scoring method is null
                    if (is_null($scoring_method_id))
                    {
                        // Set the scoring method to Classic
                        $scoring_method_id = 5;
                    }

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
                    $ContributingLikelihoodName = core_get_mapping_value("riskscoring_", "Contributing_Likelihood", $mappings, $csv_line);
                    $ContributingLikelihood = (int) get_value_by_name('likelihood', $ContributingLikelihoodName);
                    $Contributing_Subjects_Impacts = core_get_mapping_value("riskscoring_", "Contributing_Subjects_Impacts", $mappings, $csv_line);
                    $ContributingImpacts = get_contributing_impacts_by_subjectimpact_names($Contributing_Subjects_Impacts);

                    // Set null values to default
                    if (is_null($CLASSIClikelihood)) $CLASSIClikelihood = "";
                    if (is_null($CLASSICimpact)) $CLASSICimpact = "";
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
                    if (is_null($custom)) $custom = false;
                    if (is_null($ContributingLikelihood)) $ContributingLikelihood = "";
                    if (is_null($ContributingImpacts)) $ContributingImpacts = [];
                        
                    $scoringData = array(
                        'scoring_method' => $scoring_method_id,

                        // Classic Risk Scoring Inputs
                        'CLASSIClikelihood' => $CLASSIClikelihood,
                        'CLASSICimpact' =>  $CLASSICimpact,

                        // CVSS Risk Scoring Inputs
                        'CVSSAccessVector' => $CVSSAccessVector,
                        'CVSSAccessComplexity' => $CVSSAccessComplexity,
                        'CVSSAuthentication' => $CVSSAuthentication,
                        'CVSSConfImpact' => $CVSSConfImpact,
                        'CVSSIntegImpact' => $CVSSIntegImpact,
                        'CVSSAvailImpact' => $CVSSAvailImpact,
                        'CVSSExploitability' => $CVSSExploitability,
                        'CVSSRemediationLevel' => $CVSSRemediationLevel,
                        'CVSSReportConfidence' => $CVSSReportConfidence,
                        'CVSSCollateralDamagePotential' => $CVSSCollateralDamagePotential,
                        'CVSSTargetDistribution' => $CVSSTargetDistribution,
                        'CVSSConfidentialityRequirement' => $CVSSConfidentialityRequirement,
                        'CVSSIntegrityRequirement' => $CVSSIntegrityRequirement,
                        'CVSSAvailabilityRequirement' => $CVSSAvailabilityRequirement,
                        // DREAD Risk Scoring Inputs
                        'DREADDamage' => $DREADDamage,
                        'DREADReproducibility' => $DREADReproducibility,
                        'DREADExploitability' => $DREADExploitability,
                        'DREADAffectedUsers' => $DREADAffectedUsers,
                        'DREADDiscoverability' => $DREADDiscoverability,
                        // OWASP Risk Scoring Inputs
                        'OWASPSkillLevel' => $OWASPSkillLevel,
                        'OWASPMotive' => $OWASPMotive,
                        'OWASPOpportunity' => $OWASPOpportunity,
                        'OWASPSize' => $OWASPSize,
                        'OWASPEaseOfDiscovery' => $OWASPEaseOfDiscovery,
                        'OWASPEaseOfExploit' => $OWASPEaseOfExploit,
                        'OWASPAwareness' => $OWASPAwareness,
                        'OWASPIntrusionDetection' => $OWASPIntrusionDetection,
                        'OWASPLossOfConfidentiality' => $OWASPLossOfConfidentiality,
                        'OWASPLossOfIntegrity' => $OWASPLossOfIntegrity,
                        'OWASPLossOfAvailability' => $OWASPLossOfAvailability,
                        'OWASPLossOfAccountability' => $OWASPLossOfAccountability,
                        'OWASPFinancialDamage' => $OWASPFinancialDamage,
                        'OWASPReputationDamage' => $OWASPReputationDamage,
                        'OWASPNonCompliance' => $OWASPNonCompliance,
                        'OWASPPrivacyViolation' => $OWASPPrivacyViolation,

                        // Custom Risk Scoring
                        'Custom' => $custom,

                        // Contributing Risk Scoring
                        'ContributingLikelihood' => $ContributingLikelihood,
                        'ContributingImpacts' => $ContributingImpacts,
                    );
                    
                    list($questionnaire_scoring_id, $calculated_risk) = add_assessment_questionnaire_scoring($scoringData);
                    /************ End saving assessment scoring *************/
                        

                    $answer         = core_get_mapping_value("answer", "", $mappings, $csv_line);

                    if($answer)
                    {
                        $submit_risk    = core_get_mapping_value("submit_risk", "", $mappings, $csv_line);
                        $risk_subject   = core_get_mapping_value("risk_subject", "", $mappings, $csv_line);
                        $risk_owner     = core_get_or_add_user("owner", $mappings, $csv_line);
                        $assets         = core_get_mapping_value("assets", "", $mappings, $csv_line);
                        $sub_questions  = core_get_mapping_value("sub_", "questions", $mappings, $csv_line);
                        
                        // Add the question
                        $stmt = $db->prepare("INSERT INTO `questionnaire_answers` SET `question_id` = :question_id, `answer` = :answer, submit_risk=:submit_risk, risk_subject=:risk_subject, risk_owner=:risk_owner, `questionnaire_scoring_id`=:questionnaire_scoring_id; ");
                        $stmt->bindParam(":question_id", $new_question_id, PDO::PARAM_INT);
                        $stmt->bindParam(":answer", $answer, PDO::PARAM_STR);
                        $stmt->bindParam(":submit_risk", $submit_risk, PDO::PARAM_STR);
                        $stmt->bindParam(":risk_subject", $risk_subject, PDO::PARAM_STR);
                        $stmt->bindParam(":risk_owner", $risk_owner, PDO::PARAM_STR);
                        
                        $stmt->bindParam(":questionnaire_scoring_id", $questionnaire_scoring_id, PDO::PARAM_INT);
                        $stmt->execute();

                        $answer_id = $db->lastInsertId();

                        import_assets_asset_groups_for_type($answer_id, $assets, 'questionnaire_answer');

                        if($sub_questions && $question_id)
                        {
                            $old_sub_questions[$answer_id] = $sub_questions;
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
        
        // Update sub questions
        foreach($old_sub_questions as $answer_id => $old_sub_question){
            $old_ids = explode(",", $old_sub_question);
            $new_ids = [];
            foreach($old_ids as $old_id){
                $new_ids[] = $old_new_question_ids[$old_id];
            }
            $new_sub_question = implode(",", $new_ids);
            
            // Update sub questions for new question
            $stmt = $db->prepare("UPDATE `questionnaire_answers` SET `sub_questions`=:sub_questions WHERE `id` = :answer_id; ");
            $stmt->bindParam(":sub_questions", $new_sub_question, PDO::PARAM_STR);
            $stmt->bindParam(":answer_id", $answer_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        set_alert(true, "good", $lang['AssessmentSuccessImport']);

    }else{
    
        set_alert(true, "bad", $lang['AssessmentFileRequired']);
    
    }
    
    // Close the temporary file
    fclose($handle);
    
    // Close the database connection
    db_close($db);

}

/*************************************
 * FUNCTION: GET ASSESSMENT CONTACTS *
 *************************************/
function get_assessment_contacts($filter_text="", $columnName=false, $columnDir=false){
    // Open the database connection
    $db = db_open();

    $sql = "SELECT t1.*, t2.name as manager_name FROM `assessment_contacts` t1 LEFT JOIN `user` t2 on t1.manager=t2.value";
    
    if($filter_text){
        $sql .= " WHERE t1.company like :filter_text OR t1.name like :filter_text OR t1.email like :filter_text OR t1.phone like :filter_text OR t2.name like :filter_text ";
    }
    
    if($columnName == "company"){
        $sql .= " ORDER BY t1.company {$columnDir} ";
    }elseif($columnName == "name"){
        $sql .= " ORDER BY t1.name {$columnDir} ";
    }elseif($columnName == "email"){
        $sql .= " ORDER BY t1.email {$columnDir} ";
    }elseif($columnName == "phone"){
        $sql .= " ORDER BY t1.phone {$columnDir} ";
    }elseif($columnName == "manager"){
        $sql .= " ORDER BY t2.name {$columnDir} ";
    }

    $stmt = $db->prepare($sql);
    
    if($filter_text){
        $filter_text = "%{$filter_text}%";
        $stmt->bindParam(":filter_text", $filter_text, PDO::PARAM_STR, 100);
    }
    
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);
    
    foreach($array as &$row){
        $row['company'] = try_decrypt($row['company']);
        $row['name'] = try_decrypt($row['name']);
        $row['email'] = try_decrypt($row['email']);
        $row['phone'] = try_decrypt($row['phone']);
        $row['details'] = try_decrypt($row['details']);
    }

    return $array;
}

/**********************************************
 * FUNCTION: DISPLAY ASSESSMENT CONTACTS HTML *
 **********************************************/
function display_assessment_contacts(){
    global $lang;
    global $escaper;

    $tableID = "assessment-contacts-table";
    
    echo "
        <table class=\"table risk-datatable assessment-datatable table-bordered table-striped table-condensed  \" width=\"100%\" id=\"{$tableID}\" >
            <thead >
                <tr>
                    <th>Company</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Contact Manager</th>
                    <th width=\"78px\">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <script>
            var pageLength = 10;
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                pagingType: 'full_numbers',
                dom : 'flrtip',
                pageLength: pageLength,
                dom : 'flrti<\"#view-all.view-all\">p',
                ajax: {
                    url: BASE_URL + '/api/assessment/contacts',
                    data: function(d){
                        d.filter_text = \$('#filter_by_text').val();
                    },
                    complete: function(response){
                    }
                }
            });
            
            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })
            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw()
                $(this).addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw()
                }
                
            })
            
        </script>
    ";
    

    // MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
    echo "
        <div id=\"aseessment-contact--delete\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
          <div class=\"modal-body\">

            <form class=\"\" action=\"\" method=\"post\">
              <div class=\"form-group text-center\">
                <label for=\"\">".$escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisContact'])."</label>
              </div>

              <input type=\"hidden\" name=\"contact_id\" value=\"\" />
              <div class=\"form-group text-center control-delete-actions\">
                <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                <button type=\"submit\" name=\"delete_contact\" class=\"delete_control btn btn-danger\">".$escaper->escapeHtml($lang['Yes'])."</button>
              </div>
            </form>

          </div>
        </div>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.contact-delete-btn', function(){
                \$('#aseessment-contact--delete [name=contact_id]').val(\$(this).data('id'));
            })
            // Redraw Contacts table
            function redrawContacts(){
                \$(\"#{$tableID}\").DataTable().draw();
            } 
            
            // timer identifier
            var typingTimer;                
            // time in ms (1 second)
            var doneTypingInterval = 1000;  

            // Search filter event
            \$('#filter_by_text').keyup(function(){
                clearTimeout(typingTimer);
                typingTimer = setTimeout(redrawContacts, doneTypingInterval);
            });        
        </script>
    ";
}

/**************************************************
 * FUNCTION: DISPLAY ASSESSMENT CONTACTS ADD FORM *
 **************************************************/
function display_assessment_contacts_add(){
    global $lang;
    global $escaper;
    
    echo "
        <form name=\"add_user\" method=\"post\" action=\"\">
            <table cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                <tbody>
                <tr>
                    <td colspan=\"2\"><h4>". $escaper->escapeHtml($lang['AddNewAssessmentContact']) ."</h4></td></tr>
                <tr>
                    <td>Company:&nbsp;</td>
                    <td>
                        <input required name=\"company\" maxlength=\"255\" size=\"100\" value=\"\" type=\"text\">
                    </td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['Name']) .":&nbsp;</td><td><input required name=\"name\" maxlength=\"255\" size=\"100\" value=\"\" type=\"text\"></td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['EmailAddress']) .":&nbsp;</td><td><input name=\"email\" maxlength=\"200\" value=\"\" size=\"100\" type=\"email\" required></td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['Phone']) .":&nbsp;</td><td><input name=\"phone\" maxlength=\"200\" value=\"\" size=\"100\" type=\"text\" required></td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['ContactManager']) .":&nbsp;</td>
                    <td>". create_dropdown("enabled_users", NULL, "manager", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) ."</td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['Details']) .":&nbsp;</td>
                    <td><textarea name='details' class='full-width'></textarea></td>
                </tr>
                </tbody>
            </table>
            <br>
            <input value=\"". $escaper->escapeHtml($lang['Add']) ."\" name=\"add_contact\" type=\"submit\">
        </form>    
    ";
    
}

/***************************************************
 * FUNCTION: DISPLAY ASSESSMENT CONTACTS EDIT FORM *
 ***************************************************/
function display_assessment_contacts_edit($id){
    global $lang;
    global $escaper;
    
    $id = (int)$id;
    
    $assessment_contact = get_assessment_contact($id);
    
    echo "
        <form name=\"add_user\" method=\"post\" action=\"\">
            <table cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                <tbody>
                <tr>
                    <td colspan=\"2\"><h4>". $escaper->escapeHtml($lang['UpdateAssessmentContact']) ."</h4></td></tr>
                <tr>
                    <td>Company:&nbsp;</td>
                    <td>
                        <input required name=\"company\" maxlength=\"255\" size=\"100\" value=\"".$escaper->escapeHtml($assessment_contact['company'])."\" type=\"text\">
                    </td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['Name']) .":&nbsp;</td><td><input required name=\"name\" maxlength=\"255\" size=\"100\" value=\"".$escaper->escapeHtml($assessment_contact['name'])."\" type=\"text\"></td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['EmailAddress']) .":&nbsp;</td><td><input name=\"email\" maxlength=\"200\" value=\"".$escaper->escapeHtml($assessment_contact['email'])."\" size=\"100\" type=\"email\" required></td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['Phone']) .":&nbsp;</td><td><input name=\"phone\" maxlength=\"200\" value=\"".$escaper->escapeHtml($assessment_contact['phone'])."\" size=\"100\" type=\"text\" required></td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['ContactManager']) .":&nbsp;</td>
                    <td>". create_dropdown("enabled_users", (int)$assessment_contact['manager'], "manager", true, false, true, "", $escaper->escapeHtml($lang['Unassigned'])) ."</td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['Details']) .":&nbsp;</td>
                    <td><textarea name='details' class='full-width'>".$escaper->escapeHtml($assessment_contact['details'])."</textarea></td>
                </tr>
                </tbody>
            </table>
            <br>
            <input value=\"". $escaper->escapeHtml($lang['Update']) ."\" name=\"update_contact\" type=\"submit\">
        </form>    
    ";
    
}

/******************************************
 * FUNCTION: GET ASSESSMENT CONTACT BY ID *
 ******************************************/
function get_assessment_contact($id){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `assessment_contacts` WHERE `id`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);
    
    $array['company'] = try_decrypt($array['company']);
    $array['name'] = try_decrypt($array['name']);
    $array['email'] = try_decrypt($array['email']);
    $array['phone'] = try_decrypt($array['phone']);
    $array['details'] = try_decrypt($array['details']);

    return $array;    
}

/*********************************************
 * FUNCTION: GET ASSESSMENT CONTACT BY EMAIL *
 *********************************************/
function get_assessment_contact_by_email($email){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `assessment_contacts` WHERE `email`=:email;");
    $stmt->bindParam(":email", $email, PDO::PARAM_STR, 100);
    $stmt->execute();

    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // If the array is not empty
    if (!empty($array))
    {
        $array['company'] = try_decrypt($array['company']);
        $array['name'] = try_decrypt($array['name']);
        $array['email'] = try_decrypt($array['email']);
        $array['phone'] = try_decrypt($array['phone']);
        $array['details'] = try_decrypt($array['details']);
    }

    return $array;    
}

/******************************************
 * FUNCTION: CHECK IF EXIST CONTACT EMAIL *
 ******************************************/
function check_exist_contact_email($email, $contact_id=false)
{
    // Check for adding a contact
    if($contact_id === false){
        // Return true if contact email exists
        if(get_assessment_contact_by_email($email))
        {
            return true;
        }
        // Return true if contact email no exist
        else
        {
            return false;
        }
    }
    // Check for updating a contact
    else
    {
        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("SELECT id FROM `assessment_contacts` WHERE `email`=:email and id<>:contact_id;");
        $stmt->bindParam(":email", $email, PDO::PARAM_STR, 100);
        $stmt->bindParam(":contact_id", $contact_id, PDO::PARAM_INT);
        $stmt->execute();

        $array = $stmt->fetch();

        // Close the database connection
        db_close($db);
        
        // Return true if contact email exists
        if($array)
        {
            return true;
        }
        // Return true if contact email no exist
        else
        {
            return false;
        }
        
    }
}

/******************************************
 * FUNCTION: ADD A NEW ASSESSMENT CONTACT *
 ******************************************/
function add_assessment_contact($company, $name, $email, $phone, $manager, $details){
    if($company && $name && $email){

        $company_enc = try_encrypt($company);
        $name_enc = try_encrypt($name);
        $email_enc = try_encrypt($email);
        $phone_enc = try_encrypt($phone);
        $details_enc = try_encrypt($details);

        // Create a unique salt for this contact
        $salt = generate_token(20);

        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("INSERT IGNORE INTO `assessment_contacts` SET `company` = :company, `name` = :name, `email` = :email, `phone` = :phone, `manager`=:manager, `salt`=:salt, `details`=:details;");
        $stmt->bindParam(":company", $company_enc, PDO::PARAM_STR, 255);
        $stmt->bindParam(":name", $name_enc, PDO::PARAM_STR, 255);
        $stmt->bindParam(":email", $email_enc, PDO::PARAM_STR, 255);
        $stmt->bindParam(":phone", $phone_enc, PDO::PARAM_STR, 255);
        $stmt->bindParam(":manager", $manager, PDO::PARAM_INT);
        $stmt->bindParam(":salt", $salt, PDO::PARAM_STR);
        $stmt->bindParam(":details", $details_enc, PDO::PARAM_STR);
        $stmt->execute();
        
        // Get the id of the last insert
        $contact_id = $db->lastInsertId();
        
        // Close the database connection
        db_close($db);
        
        $message = "Assessment contact \"{$name}\" was added by user \"" . $_SESSION['user']."\".";
        write_log($contact_id+1000, $_SESSION['uid'], $message, 'contact');
    }
    else{
        $contact_id = false;
    }

    return $contact_id;
}

/*****************************************
 * FUNCTION: UPDATE A ASSESSMENT CONTACT *
 *****************************************/
function update_assessment_contact($contact_id, $company, $name, $email, $phone, $manager, $details){
    if($company && $name && $email && $phone){
        
        $company_enc = try_encrypt($company);
        $name_enc = try_encrypt($name);
        $email_enc = try_encrypt($email);
        $phone_enc = try_encrypt($phone);
        $details_enc = try_encrypt($details);
        
        // Open the database connection
        $db = db_open();

        // Query the database
        $stmt = $db->prepare("UPDATE `assessment_contacts` SET `company` = :company, `name` = :name, `email` = :email, `phone` = :phone, `manager`=:manager, `details`=:details WHERE id=:id;");
        $stmt->bindParam(":company", $company_enc, PDO::PARAM_STR, 255);
        $stmt->bindParam(":name", $name_enc, PDO::PARAM_STR, 255);
        $stmt->bindParam(":email", $email_enc, PDO::PARAM_STR, 255);
        $stmt->bindParam(":phone", $phone_enc, PDO::PARAM_STR, 255);
        $stmt->bindParam(":manager", $manager, PDO::PARAM_INT);
        $stmt->bindParam(":details", $details_enc, PDO::PARAM_STR);
        $stmt->bindParam(":id", $contact_id, PDO::PARAM_INT);
        $stmt->execute();

        // Close the database connection
        db_close($db);

        $message = "Assessment contact \"{$name}\" was updated by user \"" . $_SESSION['user']."\".";
        write_log($contact_id+1000, $_SESSION['uid'], $message, 'contact');

        return true;
    }
    else{
        return false;
    }
}

/*********************************************
 * FUNCTION: DELETE ASSESSMENT CONTACT BY ID *
 *********************************************/
function delete_assessment_contact($id){
    // Open the database connection
    $db = db_open();
    
    $assessment_contact = get_assessment_contact($id);

    // Delete answers for the question
    $stmt = $db->prepare("DELETE FROM `assessment_contacts` WHERE `id`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    // Get the list of `questionnaire_id_template` that is associated to the contact
    $stmt = $db->prepare("SELECT * FROM `questionnaire_id_template` WHERE FIND_IN_SET(:contact_id, `contact_ids`);");
    $stmt->bindParam(":contact_id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $questionnaire_id_templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Iterate through the list and remove the contact
    foreach($questionnaire_id_templates as $questionnaire_id_template) {
        $contact_ids = explode(',', $questionnaire_id_template['contact_ids']);
        
        // If it's the last contact, we're removing the association
        if (count($contact_ids) == 1) {
            $stmt = $db->prepare("DELETE FROM `questionnaire_id_template` WHERE `id`=:id;");
            $stmt->bindParam(":id", $questionnaire_id_template['id'], PDO::PARAM_INT);
            $stmt->execute();
        } else {
            // Removing the contact id from the list of ids
            if (($key = array_search($id, $contact_ids)) !== false) {
                unset($contact_ids[$key]);
            }

            // Saving the updated list of contact ids
            $stmt = $db->prepare("UPDATE `questionnaire_id_template` SET `contact_ids`=:contact_ids WHERE `id`=:id;");
            $stmt->bindParam(":id", $questionnaire_id_template['id'], PDO::PARAM_INT);
            $stmt->bindParam(":contact_ids", implode(',', $contact_ids), PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    // Delete questionnaire responses with contact ID 
    $stmt = $db->prepare("DELETE t1 FROM `questionnaire_responses` t1, `questionnaire_tracking` t2 WHERE t1.questionnaire_tracking_id=t2.id and t2.`contact_id`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Delete sent questionnaires with contact ID 
    $stmt = $db->prepare("DELETE FROM `questionnaire_tracking` WHERE `contact_id`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $message = "Assessment contact \"{$assessment_contact['name']}\" and related responses and tracking infos were deleted by user \"" . $_SESSION['user']."\".";
    write_log($id+1000, $_SESSION['uid'], $message, 'contact');

    // Close the database connection
    db_close($db);
}

/************************************************
 * FUNCTION: DISPLAY IMPORTING ASSEMSSENTS HTML *
 ************************************************/
function display_import_of_assessment()
{
    global $escaper, $lang;
    
    echo "<h4>" . $escaper->escapeHtml($lang['Import']) . "</h4>\n";
    echo "<div>";
    echo "<b>" . $escaper->escapeHtml($lang['Select']) . ":</b>&nbsp;&nbsp;<select name=\"select-import\" id=\"select-import-assessment\" >\n";
        echo "<option value='import_assessment_contacts' " . ((isset($_POST['import_assessment_contacts']) || isset($_POST['mapping_assessment_contacts'])) ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ImportAssessmentContacts']) . "</option>\n";
        echo "<option value='import_assessment_questionnaire_questions' " . ((isset($_POST['import_assessment_questionnaire_questions']) || isset($_POST['mapping_assessment_questionnaire_questions'])) ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ImportAssessmentQuestionnaireQuestions']) . "</option>\n";
        echo "<option value='import_assessment_templates' " . ((isset($_POST['import_assessment_templates']) || isset($_POST['mapping_questionnaire_templates'])) ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ImportAssessmentQuestionnaireTemplates']) . "</option>\n";
        echo "<option value='import_assessment' " . ((isset($_POST['import_assessment_csv']) || isset($_POST['assessment_csv_mapped'])) ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ImportAssessments']) . "</option>\n";
    echo "</select>\n";
    echo "</div>";

    echo "<div class='import-assessment-holder' id='import_assessment_contacts' style='".((!empty($_POST) && $_POST['upload_type']=="import" && !isset($_POST['import_assessment_contacts']) && !isset($_POST['mapping_assessment_contacts'])) ? "display:none;" : "") ."'>";
        echo display_assessment_contacts_import();
    echo "</div>";
    
    echo "<div class='import-assessment-holder' id='import_assessment_questionnaire_questions' style='".((!isset($_POST['import_assessment_questionnaire_questions']) && !isset($_POST['import_assessment_questionnaire_questions'])) ? "display:none;" : "") ."'>";
        echo display_questionnaire_questions_import();
    echo "</div>";
    
    echo "<div class='import-assessment-holder' id='import_assessment_templates' style='".((!isset($_POST['import_assessment_templates']) && !isset($_POST['mapping_questionnaire_templates'])) ? "display:none;" : "") ."'>";
        echo display_questionnaire_template_import();
    echo "</div>";
    
    echo "<div class='import-assessment-holder' id='import_assessment' style='".((!isset($_POST['import_assessment_csv']) && !isset($_POST['assessment_csv_mapped'])) ? "display:none;" : "") ."'>";
        echo display_import_assessments();
    echo "</div>";

    echo "
        <script>
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
        
            $(document).ready(function(){

                $('#select-import-assessment').change(function(){
                    $('.import-assessment-holder').hide();
                    $('#' + $(this).val()).show();
                });

                $(\".import-assessment-holder form input[type='file']\").prop('required', true);
                $('.import-assessment-holder form').submit(function(event) {
                    if (" . get_setting('max_upload_size') . " <= $(this).find(\"input[type='file']\")[0].files[0].size) {
                        toastr.error(\"" . $escaper->escapeHtml($lang['FileIsTooBigToUpload']) . "\");
                        event.preventDefault();
                    }
                });
            });
        </script>
    ";
}

/************************************************
 * FUNCTION: DISPLAY EXPORTING ASSEMSSENTS HTML *
 ************************************************/
function display_export_of_assessment()
{
    global $escaper, $lang;
    
    echo "<h4>" . $escaper->escapeHtml($lang['Export']) . "</h4>\n";
    echo "<div>";
    echo "<b>" . $escaper->escapeHtml($lang['Select']) . ":</b>&nbsp;&nbsp;<select name=\"select-export\" id=\"select-export-assessment\" >\n";
        echo "<option value='questionnaire_template_export' " . (isset($_POST['questionnaire_template_export'])  ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ExportQuestionnaireTemplate']) . "</option>\n";
        echo "<option value='assessments_export' " . (isset($_POST['assessments_export'])  ? " selected" : "") . ">" . $escaper->escapeHtml($lang['ExportAssessment']) . "</option>\n";
    echo "</select>\n";
    echo "</div>";

    echo "<div class='export-assessment-holder' id='questionnaire_template_export' style='".((!empty($_POST) && $_POST['upload_type']=="export" && !isset($_POST['questionnaire_template_export'])) ? "display:none;" : "") ."'>";
        echo display_questionnaire_template_export();
    echo "</div>";
    
    echo "<div class='export-assessment-holder' id='assessments_export' style='".( !isset($_POST['assessments_export'] ) ? "display:none;" : "") ."'>";
        echo display_export_assessments();
    echo "</div>";
    
    
    echo "
        <script>
            $(document).ready(function(){
                $('#select-export-assessment').change(function(){
                    $('.export-assessment-holder').hide();
                    $('#' + $(this).val()).show();
                })
            })
        </script>
    ";
    
}

/*********************************************
 * FUNCTION: DISPLAY IMPORTING CONTACTS HTML *
 *********************************************/
function display_assessment_contacts_import(){
    global $escaper, $lang;
    
    echo "<h4>" . $escaper->escapeHtml($lang['ImportAssessmentContacts']) . "</h4>\n";
    
    // Check if a file not uploaded or failed to upload the file.
    if(!isset($_POST['import_assessment_contacts']) || !($filename = upload_assessment_import_file($_FILES['file']))){
        echo "<form method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
            echo "<input type=\"hidden\" name=\"upload_type\" value=\"import\">";
            echo $escaper->escapeHtml($lang['ImportCsvXlsFile']).":<br />\n";
            echo "<input type=\"file\" name=\"file\" />\n";
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"import_assessment_contacts\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
        echo "</form>\n";
    }
    // Check if a file uploaded
    else{
        echo "<form method=\"post\" action=\"\" >\n";
            echo "<input type=\"hidden\" name=\"upload_type\" value=\"import\">";
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
            
            $header_columns = get_assessment_column_headers(sys_get_temp_dir() . "/" . $filename);
            
            
            // For each column in the file
            foreach ($header_columns as $column)
            {
                echo "<tr>\n";
                    echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                    echo "<td>\n";
                    assessment_contact_column_name_dropdown("col_" . $col_counter);
                    echo "</td>\n";
                echo "</tr>\n";

                // Increment the column counter
                $col_counter++;
            }

            echo "</tbody>\n";
            echo "</table>\n";
            echo "<div><input type=\"hidden\" name=\"filename\" value=\"{$filename}\"></div>";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"mapping_assessment_contacts\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
        echo "</form>\n";
    }
}

/*************************************************
 * FUNCTION: DISPLAY IMPORING QUESTIONNAIRE HTML *
 *************************************************/
function display_questionnaire_questions_import(){
    global $escaper, $lang;
    
    echo "<h4>" . $escaper->escapeHtml($lang['ImportAssessmentQuestionnaireQuestions']) . "</h4>\n";
    
    // Check if a file not uploaded or failed to upload the file.
    if(!isset($_POST['import_assessment_questionnaire_questions']) || !($filename = upload_assessment_import_file($_FILES['file']))){
        echo "<form method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
            echo "<input type=\"hidden\" name=\"upload_type\" value=\"import\">";
            echo $escaper->escapeHtml($lang['ImportCsvXlsFile']).":<br />\n";
            echo "<input type=\"file\" name=\"file\" />\n";
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"import_assessment_questionnaire_questions\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
        echo "</form>\n";
    }
    // Check if a file uploaded
    else{
        echo "<form method=\"post\" action=\"\" >\n";
            echo "<input type=\"hidden\" name=\"upload_type\" value=\"import\">";
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
            
            $header_columns = get_assessment_column_headers(sys_get_temp_dir() . "/" . $filename);
            
            
            // For each column in the file
            foreach ($header_columns as $column)
            {
                echo "<tr>\n";
                    echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                    echo "<td>\n";
                    assessment_questionnaire_column_name_dropdown("col_" . $col_counter);
                    echo "</td>\n";
                echo "</tr>\n";

                // Increment the column counter
                $col_counter++;
            }

            echo "</tbody>\n";
            echo "</table>\n";
            echo "<div><input type=\"hidden\" name=\"filename\" value=\"{$filename}\"></div>";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"mapping_assessment_questionnaire_questions\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
        echo "</form>\n";
    }
}

/**********************************************************
 * FUNCTION: DISPLAY IMPORING QUESTIONNAIRE TEMPLATE HTML *
 **********************************************************/
function display_questionnaire_template_import(){
    global $escaper, $lang;
    
    echo "<h4>" . $escaper->escapeHtml($lang['ImportAssessmentQuestionnaireTemplates']) . "</h4>\n";
    
    // Check if a file not uploaded or failed to upload the file.
    if(!isset($_POST['import_assessment_templates']) || !($filename = upload_assessment_import_file($_FILES['file'])))
    {
        echo "<form method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
            echo "<input type=\"hidden\" name=\"upload_type\" value=\"import\">";
            echo $escaper->escapeHtml($lang['ImportCsvXlsFile']).":<br />\n";
            echo "<input type=\"file\" name=\"file\" />\n";
            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"import_assessment_templates\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
        echo "</form>\n";
    }
    // Check if a file uploaded
    else
    {
        echo "<form method=\"post\" action=\"\" >\n";
            echo "<input type=\"hidden\" name=\"upload_type\" value=\"import\">";
            echo "<input type=\"checkbox\" name=\"import_first\" />&nbsp;Import First Row\n";
            echo "<br /><br />\n";
            echo "<table class=\"table table-bordered table-condensed \">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            echo "<th width=\"200px\">File Columns</th>\n";
            echo "<th>" . $escaper->escapeHtml($lang['QuestionnaireTemplateColumnMapping']) . "</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";

            // Column counter
            $col_counter = 0;
            
            $header_columns = get_assessment_column_headers(sys_get_temp_dir() . "/" . $filename);
            
            
            // For each column in the file
            foreach ($header_columns as $column)
            {
                echo "<tr>\n";
                    echo "<td style=\"vertical-align:middle;\" width=\"200px\">" . $escaper->escapeHtml($column) . "</td>\n";
                    echo "<td>\n";
                    questionnaire_template_column_name_dropdown("col_" . $col_counter);
                    echo "</td>\n";
                echo "</tr>\n";

                // Increment the column counter
                $col_counter++;
            }

            echo "</tbody>\n";
            echo "</table>\n";
            echo "<div><input type=\"hidden\" name=\"filename\" value=\"{$filename}\"></div>";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"mapping_questionnaire_templates\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Import']) . "</button>\n";
            echo "</div>\n";
        echo "</form>\n";
    }
}

/*********************************************************
 * FUNCTION: GET ASSESSMENT CONTACT COLUMN NAME DROPDOWN *
 *********************************************************/
function assessment_contact_column_name_dropdown($name){
    global $escaper;

    // Get the list of SimpleRisk fields
    $fields = assessment_contact_fields();

    echo "<select class='mapping' name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" onchange=\"removeSelected(this.name, this.value)\">\n";
    echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

    // For each field
    foreach ($fields as $key => $value)
    {
        if(isset($mappings[$name]) && $mappings[$name] == $key){
            $selected = "selected";
        }else{
            $selected = "";
        }
        echo "<option {$selected} value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
    }

    echo "</select>\n";
}

/*********************************************************
 * FUNCTION: GET ASSESSMENT CONTACT COLUMN NAME DROPDOWN *
 *********************************************************/
function assessment_questionnaire_column_name_dropdown($name){
    global $escaper;

    // Get the list of SimpleRisk fields
    $fields = assessment_questionnaire_fields();

    echo "<select class='mapping' name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" onchange=\"removeSelected(this.name, this.value)\">\n";
    echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

    // For each field
    foreach ($fields as $key => $value)
    {
        if(isset($mappings[$name]) && $mappings[$name] == $key){
            $selected = "selected";
        }else{
            $selected = "";
        }
        echo "<option {$selected} value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
    }

    echo "</select>\n";
}

/*************************************************************
 * FUNCTION: GET QUESTIONNAIRE TEMPLATE COLUMN NAME DROPDOWN *
 *************************************************************/
function questionnaire_template_column_name_dropdown($name){
    global $escaper;

    // Get the list of questionnaire template fields
    $fields = questionnaire_template_fields();

    echo "<select name=\"" . $escaper->escapeHtml($name) . "\" id=\"" . $escaper->escapeHtml($name) . "\" >\n";
    echo "<option value=\"\" selected=\"selected\">No mapping selected</option>\n";

    // For each field
    foreach ($fields as $key => $value)
    {
        if(isset($mappings[$name]) && $mappings[$name] == $key){
            $selected = "selected";
        }else{
            $selected = "";
        }
        echo "<option {$selected} value=\"" . $escaper->escapeHtml($key) . "\">" . $escaper->escapeHtml($value) . "</option>\n";
    }

    echo "</select>\n";
}

/*******************************
 * FUNCTION: SIMPLERISK FIELDS *
 *******************************/
function assessment_contact_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'contact_company'      => $lang['Company'],
        'contact_name'  => $lang['Name'],
        'contact_email' => $lang['EmailAddress'],
        'contact_phone' => $lang['Phone'],
        'contact_manager' => $lang['ContactManager'],
        'contact_details' => $lang['Details'],
    );

    // Return the fields array
    return $fields;
}

/*******************************
 * FUNCTION: SIMPLERISK FIELDS *
 *******************************/
function assessment_questionnaire_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'questionnaire_question'                        => $lang['Question'],
        'questionnaire_answers'                         => $lang['Answers'],
        'riskscoring_scoring_method'                    =>$lang['RiskScoringMethod'],
        'riskscoring_calculated_risk'                   =>$lang['CalculatedRisk'],
        'riskscoring_CLASSIC_likelihood'                =>$lang['CurrentLikelihood'],
        'riskscoring_CLASSIC_impact'                    =>$lang['CurrentImpact'],
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
    );

    // Return the fields array
    return $fields;
}

/*********************************************
   * FUNCTION: QUESTIONNAIRE TEMPLATE FIELDS *
 *********************************************/
function questionnaire_template_fields()
{
    // Include the language file
    require_once(language_file());

    global $lang;

    // Create an array of fields
    $fields = array(
        'template'  => $lang['Template'],
        'question'  => $lang['Question'],
        'ordering'  => $lang['Ordering'],
    );

    // Return the fields array
    return $fields;
}

/**********************************************
 * FUNCTION: GET ASSESSMENT HEADERS FROM FILE *
 **********************************************/
function get_assessment_column_headers($filepath){
    // Set PHP to auto detect line endings
    ini_set('auto_detect_line_endings', true);

    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

    // Check if file format is csv.
    if($extension == "csv"){
        // If we can read the file
        if (($handle = fopen($filepath, 'rb')) !== FALSE)
        {
            // If we can get the first line in the file
            if (($headers = fgetcsv($handle, 0, ",")) !== FALSE)
            {
                // Close the file
                fclose($handle);
                return $headers;
            }

            // Close the file
            fclose($handle);
        }
    }
    
    // Check if file format is xls or xlsx.
    elseif($extension == "xls" || $extension == "xlsx"){
        require_once(realpath(__DIR__ . '/includes/PHPOffice/autoload.php'));
        $reader = PhpOffice\PhpSpreadsheet\IOFactory:: createReader(ucfirst($extension));
        $spreadsheet = $reader->load($filepath);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null,true,true,true);
        return isset($sheetData[1]) ? array_values($sheetData[1]) : FALSE;
    }
    
    // Return false
    return FALSE;
}

/****************************************************
 * FUNCTION: UPLOAD A FILE FOR IMPORTING ASSESSMENT *
 ****************************************************/
function upload_assessment_import_file($file){
    
    if (isset($file) && $file['name'] != "")
    {
        // Allowed file types
        $allowed_types = get_file_types();
        
        // If the file type is appropriate
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
                    $unique_name = generate_token(20);
                    
                    $filename = $unique_name. "." .pathinfo($file['name'], PATHINFO_EXTENSION);
                    
                    $target_path = sys_get_temp_dir() . '/' . $filename;
                    
                    // Rename the file
                    move_uploaded_file($file['tmp_name'], $target_path);

                    // Return the CSV column headers
                    return $filename;
                }
                // Otherwise, file upload error
                else
                {
                    // Display an alert
                    set_alert(true, "bad", "There was an error with the file upload.");
                    return 0;
                }
            }
            // Otherwise, file too big
            else
            {
                // Display an alert
                set_alert(true, "bad", "The uploaded file was too big.");
                return 0;
            }
        }
        // Otherwise, file type not supported
        else
        {
            // Display an alert
            set_alert(true, "bad", "The file type of the uploaded file (" . $file['type'] . ") is not supported.");
            return 0;
        }
    }
    // Otherwise, upload error
    else
    {
        // Display an alert
        set_alert(true, "bad", "There was an error with the uploaded file.");
        return 0;
    }
    
}

/******************************************************
 * FUNCTION: IMPORT ASSESSMENT CONTACTS WITH MAPPINGS *
 ******************************************************/
function mapping_assessment_contacts(){
    $filename = $_POST['filename'];
    $filepath = sys_get_temp_dir() . "/" . $filename;
    
    // Set PHP to auto detect line endings
    ini_set('auto_detect_line_endings', true);

    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

    // Detect first line
    $first_line = isset($_POST['import_first']) ? true : false;
    // If we can read the temporary file
    if (($handle = fopen($filepath, "r")) !== FALSE)
    {
        $rows = array();
        if($extension == "csv"){
            while (($csv_line = fgetcsv($handle)) !== FALSE)
            {
                $rows[] = $csv_line;
            }
        }else{
            require_once(realpath(__DIR__ . '/includes/PHPOffice/autoload.php'));
            $reader = PhpOffice\PhpSpreadsheet\IOFactory:: createReader(ucfirst($extension));
            $spreadsheet = $reader->load($filepath);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null,true,true,true);
            foreach($sheetData as $row){
                $rows[] = array_values($row);
            }
        }
        // If we don't import the first line
        if(!$first_line){
            // Remove first row
            array_shift($rows);
        }
        
        // Copy posted values into a new array
        $mappings = $_POST;
        
        // While we have lines in the file to read
        foreach ($rows as $csv_line)
        {
            // Get the name
            $company = core_get_mapping_value("contact_", "company", $mappings, $csv_line);
            $name = core_get_mapping_value("contact_", "name", $mappings, $csv_line);
            $email = core_get_mapping_value("contact_", "email", $mappings, $csv_line);
            $phone = core_get_mapping_value("contact_", "phone", $mappings, $csv_line);

            /*****************
             *** ADD ASSET ***
             *****************/
            // If the name is not null (we don't want to add assets without a name)
            if (!is_null($company) || !is_null($name) || !is_null($email) || !is_null($phone))
            {
                // If the contact email no exists
                if(!check_exist_contact_email($email)){
                    // Get the asset values
                    $contact_manager    = core_get_mapping_value("contact_", "manager", $mappings, $csv_line);
                    $contact_manager_id = get_value_by_name("user", $contact_manager);
                    
                    $details = core_get_mapping_value("contact_", "details", $mappings, $csv_line);

                    add_assessment_contact($company, $name, $email, $phone, $contact_manager_id, $details);
                }
            }
        }
    }

    // Close the temporary file
    fclose($handle);
}

/************************************************************
 * FUNCTION: IMPORT ASSESSMENT QUESTIONNAIRES WITH MAPPINGS *
 ************************************************************/
function mapping_assessment_questionnaire_questions(){
    global $escaper, $lang;
    
    $filename = $_POST['filename'];
    $filepath = sys_get_temp_dir() . "/" . $filename;
    
    // Set PHP to auto detect line endings
    ini_set('auto_detect_line_endings', true);

    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

    // Detect first line
    $first_line = isset($_POST['import_first']) ? true : false;
    
    // If we can read the temporary file
    if (($handle = fopen($filepath, "r")) !== FALSE)
    {
        $rows = array();
        if($extension == "csv"){
            while (($csv_line = fgetcsv($handle)) !== FALSE)
            {
                $rows[] = $csv_line;
            }
        }else{
            require_once(realpath(__DIR__ . '/includes/PHPOffice/autoload.php'));
            $reader = PhpOffice\PhpSpreadsheet\IOFactory:: createReader(ucfirst($extension));
            $spreadsheet = $reader->load($filepath);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null,true,true,true);
            foreach($sheetData as $row){
                $rows[] = array_values($row);
            }
        }
        // If we don't import the first line
        if(!$first_line){
            // Remove first row
            array_shift($rows);
        }
        
        // Copy posted values into a new array
        $mappings = $_POST;
        
        // While we have lines in the file to read
        foreach ($rows as $csv_line)
        {
            // Get the subject
            $question = core_get_mapping_value("questionnaire_", "question", $mappings, $csv_line);

            if(!$question){
//                continue;
            }
            
            /************ Save risk scoring *************/
                // Get the risk scoring method
                $scoring_method = core_get_mapping_value("riskscoring_", "scoring_method", $mappings, $csv_line);

                // Get the scoring method id
                $scoring_method_id = get_value_by_name("scoring_methods", $scoring_method);

                // If the scoring method is null
                if (is_null($scoring_method_id))
                {
                    // Set the scoring method to Classic
                    $scoring_method_id = 1;
                }

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

                // Custom Risk Scoring
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
                if (is_null($ContributingLikelihood)) $ContributingLikelihood = "";
                if (is_null($ContributingImpacts)) $ContributingImpacts = [];

                // Submit risk scoring
                list($questionnaire_scoring_id, $calculated_risk) = add_assessment_questionnaire_scoring(array(
                    'scoring_method' => $scoring_method_id,
                    'CLASSIClikelihood' => $CLASSIClikelihood,
                    'CLASSICimpact' => $CLASSICimpact,
                    
                    'CVSSAccessVector' => $CVSSAccessVector,
                    'CVSSAccessComplexity' => $CVSSAccessComplexity,
                    'CVSSAuthentication' => $CVSSAuthentication,
                    'CVSSConfImpact' => $CVSSConfImpact,
                    
                    'CVSSIntegImpact' => $CVSSIntegImpact,
                    'CVSSAvailImpact' => $CVSSAvailImpact,
                    'CVSSExploitability' => $CVSSExploitability,
                    'CVSSRemediationLevel' => $CVSSRemediationLevel,
                    'CVSSReportConfidence' => $CVSSReportConfidence,
                    'CVSSCollateralDamagePotential' => $CVSSCollateralDamagePotential,
                    'CVSSTargetDistribution' => $CVSSTargetDistribution,
                    'CVSSConfidentialityRequirement' => $CVSSConfidentialityRequirement,
                    'CVSSIntegrityRequirement' => $CVSSIntegrityRequirement,
                    'CVSSAvailabilityRequirement' => $CVSSAvailabilityRequirement,
                    
                    'DREADDamage' => $DREADDamage,
                    'DREADReproducibility' => $DREADReproducibility,
                    'DREADExploitability' => $DREADExploitability,
                    'DREADAffectedUsers' => $DREADAffectedUsers,
                    'DREADDiscoverability' => $DREADDiscoverability,
                    
                    'OWASPSkillLevel' => $OWASPSkillLevel,
                    'OWASPMotive' => $OWASPMotive,
                    'OWASPOpportunity' => $OWASPOpportunity,
                    'OWASPSize' => $OWASPSize,
                    'OWASPEaseOfDiscovery' => $OWASPEaseOfDiscovery,
                    'OWASPEaseOfExploit' => $OWASPEaseOfExploit,
                    'OWASPAwareness' => $OWASPAwareness,
                    'OWASPIntrusionDetection' => $OWASPIntrusionDetection,
                    'OWASPLossOfConfidentiality' => $OWASPLossOfConfidentiality,
                    'OWASPLossOfIntegrity' => $OWASPLossOfIntegrity,
                    'OWASPLossOfAvailability' => $OWASPLossOfAvailability,
                    'OWASPLossOfAccountability' => $OWASPLossOfAccountability,
                    'OWASPFinancialDamage' => $OWASPFinancialDamage,
                    'OWASPReputationDamage' => $OWASPReputationDamage,
                    'OWASPNonCompliance' => $OWASPNonCompliance,
                    'OWASPPrivacyViolation' => $OWASPPrivacyViolation,
                    'Custom' => $custom,

                    // Contributing Risk Scoring
                    'ContributingLikelihood' => $ContributingLikelihood,
                    'ContributingImpacts' => $ContributingImpacts,
                ));
                
            // Get the name
            $question = core_get_mapping_value("questionnaire_", "question", $mappings, $csv_line);
            $answers = core_get_mapping_value("questionnaire_", "answers", $mappings, $csv_line);
            
            // Split answers string by "::"
            if($answers){
                $answers_array = explode("::", $answers);
            }else{
                $answers_array = array();
            }

            $questionData = array(
                'question' => $question,
                'questionnaire_scoring_id' => $questionnaire_scoring_id,
            );

            add_questionnaire_question_answers($questionData, $answers_array);
            /************ End saving risk scoring *************/

        }
    }

    // Close the temporary file
    fclose($handle);
    
    return true;
}

/******************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE EXPORT *
 ******************************************/
function display_questionnaire_template_export()
{
    global $escaper, $lang;

    echo "<h4>".$escaper->escapeHtml($lang['ExportQuestionnaireTemplate'])."</h4>";
    // Show the export form
    echo "<form method=\"POST\" >";
    echo "<input type=\"hidden\" name=\"upload_type\" value=\"export\">";
    echo "Export to a CSV file by clicking below:<br />\n";
    echo "<div class=\"form-actions\">\n";
    echo "<button type=\"submit\" name=\"questionnaire_template_export\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Export']) . "</button>\n";
    echo "</div>\n";
    echo "</form>\n";
}

/**********************************************************
 * FUNCTION: IMPORT QUESTIONNAIRE TEMPLATES WITH MAPPINGS *
 **********************************************************/
function mapping_questionnaire_templates(){
    global $escaper, $lang;
    
    $filename = $_POST['filename'];
    $filepath = sys_get_temp_dir() . "/" . $filename;
    
    // Set PHP to auto detect line endings
    ini_set('auto_detect_line_endings', true);

    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

    // Detect first line
    $first_line = isset($_POST['import_first']) ? true : false;
    
    // If we can read the temporary file
    if (($handle = fopen($filepath, "r")) !== FALSE)
    {
        $rows = array();
        if($extension == "csv"){
            while (($csv_line = fgetcsv($handle)) !== FALSE)
            {
                $rows[] = $csv_line;
            }
        }else{
            require_once(realpath(__DIR__ . '/includes/PHPOffice/autoload.php'));
            $reader = PhpOffice\PhpSpreadsheet\IOFactory:: createReader(ucfirst($extension));
            $spreadsheet = $reader->load($filepath);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null,true,true,true);
            foreach($sheetData as $row){
                $rows[] = array_values($row);
            }
        }
        // If we don't import the first line
        if(!$first_line){
            // Remove first row
            array_shift($rows);
        }
        
        // Copy posted values into a new array
        $mappings = $_POST;
        
        $templates = [];
        
        // Create template array
        foreach ($rows as $csv_line)
        {
            // Get the template name
            $template = core_get_mapping_value("template", "", $mappings, $csv_line);
            
            // If template is empty, skip this row
            if(!$template){
                continue;
            }
            
            if(!isset($templates[$template])){
                $templates[$template] = array(
                    "questions" => [],
                    "orderings" => [],
                );
            }
            
            $question = core_get_mapping_value("question", "", $mappings, $csv_line);
            if(!$question)
            {
                continue;
            }
            $db_question = get_questionnaire_question_by_text($question);
            
            // Add new question if this doesn't exist
            if(empty($db_question['id']))
            {
                $question_id = add_questionnaire_question_answers(['question' => $question, 'has_file' => 0], ['answer' => []], []);
            }
            else
            {
                $question_id = $db_question['id'];
            }
            $templates[$template]['questions'][$question_id] = $question_id;
            
            $ordering = core_get_mapping_value("ordering", "", $mappings, $csv_line);
            $ordering = $ordering ? (int)$ordering : 0;
            $templates[$template]['orderings'][$question_id] = $ordering;
        }
        
        foreach($templates as $name => $template){
            // Add questionnaire template
            add_questionnaire_template($name, $template['questions'], $template['orderings']);
        }
    }

    // Close the temporary file
    fclose($handle);
    
    return true;
}

/********************************************
 * FUNCTION: DISPLAY QUESTINNAIRE QUESTIONS *
 ********************************************/
function display_questionnaire_questions(){
    global $lang;
    global $escaper;

    $tableID = "assessment-questionnaire-questions-table";

    echo "
        <form method='GET'>
            <div class='well well-sm'>
                <div class='row-fluid'>
                    <div class='span1'>
                        <strong>" . $escaper->escapeHtml($lang['FilterBy']) . "</strong>
                    </div>
                    <div class='span11'>&nbsp;</div>
                </div>
                <div class='row-fluid'>
                    <div class='span1'>&nbsp;</div>
                    <div class='span1'>
                        <strong>" . $escaper->escapeHtml($lang['Question']) . ":</strong>
                    </div>
                    <div class='span3'>
                        <input type='text' id='filter_by_question' />
                    </div>
                    <div class='span1'>
                        <strong>" . $escaper->escapeHtml($lang['Template']) . ":</strong>
                    </div>
                    <div class='span3'>
                        <select multiple id='filter_by_templates' style='max-width: none;'>";

    list($recordsTotal, $templates) = get_assessment_questionnaire_templates();
    foreach($templates as $template){
        echo "
                            <option value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
    }

    echo "
                        </select>
                    </div>
                    <div class='span1'>&nbsp;</div>
                </div>
            </div>
            <div class='row-fluid'>
                <div class='span10'>&nbsp;</div>
                <div class='span2 text-right'>
                    <a class='btn' href='questionnaire_questions.php?action=add_question'>" . $escaper->escapeHtml($lang['AddNewQuestion']) . "</a>
                </div>
            </div>
        </form>";

    echo "
        <div class='row-fluid'>
            <table class=\"table risk-datatable assessment-datatable table-bordered table-striped table-condensed  \" width=\"100%\" id=\"{$tableID}\" >
                <thead>
                    <tr >
                        <th>". $escaper->escapeHtml($lang['QuestionnaireQuestions']) ."</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            <br>
            <script>

                function throttledDatatableRefresh() {
                    clearTimeout(changeTimer);
                    changeTimer = setTimeout(function(){
                        $(\"#{$tableID}\").DataTable().draw();
                    }, 1000);
                }

                var pageLength = 10;
                var form = $('#{$tableID}').parents('form');
                var datatableInstance;
                var changeTimer;
                
                $(document).ready(function() {
                    datatableInstance = $('#{$tableID}').DataTable({
                        bFilter: false,
                        bLengthChange: false,
                        processing: true,
                        serverSide: true,
                        bSort: false,
                        pagingType: 'full_numbers',
                        pageLength: pageLength,
                        dom : 'i<\"#view-all.view-all\">pflrti<\"#view-all.view-all\">p',
                        ajax: {
                            url: BASE_URL + '/api/assessment/questionnaire_questions',
                            data: function(d){
                                d.filter_by_question = $('#filter_by_question').val();
                                d.filter_by_templates = $('#filter_by_templates').val();
                            },
                            complete: function(response){
                            }
                        }
                    });

                    // Add paginate options
                    datatableInstance.on('draw', function(e, settings){
                        $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                        $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                        $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                        $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
                    });

                    // Add all text to View All button on bottom
                    $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

                    // View All
                    $(\".view-all\").click(function(){
                        var oSettings =  datatableInstance.settings();
                        oSettings[0]._iDisplayLength = -1;
                        datatableInstance.draw()
                        $(this).addClass(\"current\");
                    });

                    // Page event
                    $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                        var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                        var oSettings =  datatableInstance.settings();
                        if(oSettings[0]._iDisplayLength == -1){
                            $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                            oSettings[0]._iDisplayLength = pageLength;
                            datatableInstance.draw()
                        }
                    });

                    // Search filter event
                    $('#filter_by_question').keyup(throttledDatatableRefresh);

                    $('#filter_by_templates').multiselect({
                        allSelectedText: '".$escaper->escapeHtml($lang['ALL'])."',
                        maxHeight: 250,
                        enableFiltering: true,
                        enableCaseInsensitiveFiltering: true,
                        filterPlaceholder: '".$escaper->escapeHtml($lang['Template'])."',
                        buttonWidth: '100%',
                        includeSelectAllOption: true,
                        onChange: throttledDatatableRefresh,
                        onSelectAll: throttledDatatableRefresh,
                        onDeselectAll: throttledDatatableRefresh,
                        optionClass: function(element) {
                            return $(element).data('class');
                        }
                    });
                });
            </script>
    ";

    // MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
    echo "
            <div id=\"aseessment-questionnaire-question--delete\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
              <div class=\"modal-body\">

                <form class=\"\" action=\"\" method=\"post\">
                  <div class=\"form-group text-center\">
                    <label for=\"\">".$escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisQuestion'])."</label>
                  </div>

                  <input type=\"hidden\" name=\"question_id\" value=\"\" />
                  <div class=\"form-group text-center \">
                    <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                    <button type=\"submit\" name=\"delete_questionnaire_question\" class=\"delete_control btn btn-danger\">".$escaper->escapeHtml($lang['Yes'])."</button>
                  </div>
                </form>
              </div>
            </div>
    ";
    
    echo "
            <script>
                \$('body').on('click', '.delete-btn', function(){
                    \$('#aseessment-questionnaire-question--delete [name=question_id]').val(\$(this).data('id'));
                })
            </script>
        </div>
    ";
}

/***************************************************
 * FUNCTION: GET ASSESSMENT QUESTINNAIRE QUESTIONS *
 ***************************************************/
function get_assessment_questionnaire_questions($start=0, $length=-1, $filter_by_question=false, $filter_by_templates=false){

    // Open the database connection
    $db = db_open();

    /*** Get questions by $start and $lengh ***/
    $sql = "
        SELECT
            SQL_CALC_FOUND_ROWS `qq`.`id`,
            `qq`.`question`,
            `qq`.`mapped_controls`,
            GROUP_CONCAT(DISTINCT `fc`.`short_name` ORDER BY `fc`.`short_name` SEPARATOR ', ') mapped_control_names
        FROM
            `questionnaire_questions` qq
            LEFT JOIN `framework_controls` fc ON FIND_IN_SET(`fc`.`id`, `qq`.`mapped_controls`) AND `fc`.`deleted`=0
            " . ($filter_by_templates ? "INNER JOIN `questionnaire_template_question` qtq ON `qq`.`id`=`qtq`.`questionnaire_question_id` AND `qtq`.`questionnaire_template_id` IN (".implode(",", $filter_by_templates).")" : "") . "
            " . ($filter_by_question ? "WHERE `qq`.`question` LIKE CONCAT('%', :filter_by_question, '%')" : "") . "
        GROUP BY
            `qq`.`id`
        ORDER BY
            " . ($filter_by_templates ? "`qtq`.`ordering`" : "`qq`.`question`") . "
        " . ($length != -1 ? "LIMIT {$start}, {$length}" : "") . ";";

    $stmt = $db->prepare($sql);
    if ($filter_by_question)
        $stmt->bindParam(":filter_by_question", $filter_by_question, PDO::PARAM_STR);

    $stmt->execute();

    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    /******************************************/

    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $recordsTotal = $stmt->fetchColumn();

    if ((int)$recordsTotal === 0)
        return array(0, []);

    $question_ids = array();
    $fullQuestions = array();
    foreach($questions as $question){
        $question_ids[] = $question['id'];
        $fullQuestions[$question['id']] = $question;
        $fullQuestions[$question['id']]['answers'] = [];
    }
    
    /*** Get answers by question IDs ***/
    $sql = "SELECT * FROM `questionnaire_answers`";
    if($question_ids){
        $sql .= " WHERE question_id in (". implode(",", $question_ids) .") ";
    }
    $sql .= " ORDER BY ordering; ";
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    /*****************************************/
    
    foreach($answers as $answer){
        $question_id = $answer['question_id'];
        if(isset($fullQuestions[$question_id]['answers'])){
            $fullQuestions[$question_id]['answers'][] = $answer;
        }else{
            $fullQuestions[$question_id]['answers'] = array($answer);
        }
    }
    
    // Close the database connection
    db_close($db);
    return array($recordsTotal, $fullQuestions);
}

/****************************************************
 * FUNCTION: DISPLAY QUESTINNAIRE QUESTION ADD FORM *
 ****************************************************/
function display_questionnaire_question_add(){
    global $lang, $escaper;

    $configs = get_assessment_settings();

    list($recordsTotal, $templates) = get_assessment_questionnaire_templates();

    foreach ($configs as $config ) {
         ${$config['name']} = $config['value'];
    }

    // Get questions
    list($subQuestionsCount, $sub_questions) = get_assessment_questionnaire_questions();
    $affected_assets_placeholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']);
    echo "
        <style>
            .risk-element{
                display: none;
            }
        </style>
    ";
    
    echo "
        <form id='questionnaire_new_question_form' method='post' autocomplete='off'>
            <h4>".$escaper->escapeHtml($lang['NewQuestionnaireQuestion'])." <button type='submit' name='add_questionnaire_question' class='btn pull-right'>". $escaper->escapeHtml($lang['Add']) ."</button></h4>
            <div class='clearfix'></div>
            <br>
            <div class='row-fluid'>
                <strong class='span2'>". $escaper->escapeHtml($lang['Question']) .":&nbsp; </strong>
                <div class='span10'><input placeholder='". $escaper->escapeHtml($lang['Question']) ."' type='text' name='question' required class='form-control' style='max-width: none'></div>
            </div>
            <div class='row-fluid' style='padding-bottom: 10px;'>
                <strong class='span2'>". $escaper->escapeHtml($lang['ComplianceAudit']) .":&nbsp; </strong>
                <div class='span10'><input type='checkbox' id='compliance_audit' name='compliance_audit' value='1'></div>
            </div>
            <div class='row-fluid' id='mapped-controls-holder' style='display: none;'>
                <strong class='span2'>". $escaper->escapeHtml($lang['MappedControls']) .":&nbsp; </strong>
                <div class='span10'>
                    ";
                        mitigation_controls_dropdown();
                    echo "
                </div>
            </div>
            <div class='row-fluid'>
                <strong class='span2'>". $escaper->escapeHtml($lang['HasFile']) .":&nbsp; </strong>
                <div class='span10'><input type='checkbox' name='has_file' value='1'></div>
            </div>
            ";
            echo "
            <div id='questionnaire-answers-container'>
                <br>
                <table class='answers-table' width='100%' caleespacing='0' cellpadding='0'>
                    <tbody>
                    <tr>
                        <th width='10%'>".$escaper->escapeHtml($lang['Answer'])."</th>
                        <th width='10%'>".$escaper->escapeHtml($lang['FailControl'])."</th>
                        <th width='10%'>".$escaper->escapeHtml($lang['SubmitRisk'])."</th>
                        <th ><span class='risk-element' >".$escaper->escapeHtml($lang['Subject'])."</span></th>
                        <th width='10%'><span class='risk-element' >".$escaper->escapeHtml($lang['Owner'])."</span></th>
                        <th width='20%'><span class='risk-element' >".$escaper->escapeHtml($lang['AffectedAssets'])."</span></th>
                        <th width='50px'>&nbsp;</th>
                    </tr>
                    <tr>
                        <td>
                            <input type='text' placeholder='".$escaper->escapeHtml($lang['Answer'])."' required name='answers[answer][]' style='max-width: none' >
                        </td>
                        <td align='center'>
                            <input type=\"checkbox\" class=\"exist\" name=\"answers[fail_control][]\" value=\"0\" />
                        </td>
                        <td align='center'>
                            <input type=\"checkbox\" class=\"submit-risk\" name=\"answers[submit_risk][]\" value=\"0\" />
                        </td>
                        <td align='center'>
                            <div class=\"risk-element\" >
                                <input class=\"risk-element\" type=\"text\" name=\"answers[risk_subject][]\" size=\"200\" placeholder=\"Enter Risk Subject\" style='max-width: none' />
                            </div>
                        </td>
                        <td align='center'>
                            ".create_dropdown("enabled_users", NULL, "answers[risk_owner][]", true, false, true, " class=\"risk-element\" ")."
                        </td>
                        <td>
                            <div class=\"ui-widget risk-element\">
                                <select class='assets-asset-groups-select' name='answers[assets_asset_groups][0][]' multiple placeholder='{$affected_assets_placeholder}'></select>
                            </div>
                        </td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr class=\"risk-element\">
                        <td colspan='6'>
                            <table width='100%'>
                                <tr>
                                    <td class='risk-scoring-container'>";
                                        display_score_html_from_assessment();
                                    echo "</td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class='row-fluid'>
                                            <div class='span5 text-right'>
                                                ". $escaper->escapeHtml($lang['Tags']) .":
                                            </div>
                                            <div class='span7'>
                                                <input type=\"text\" readonly class=\"tags\" name=\"answers[tags][]\" value=\"\" >
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td >&nbsp;</td>
                        <td align='right'>".$escaper->escapeHtml($lang["SubTemplate"]).":</td>
                        <td colspan='4'>
                            <select class='template-by-answer' style='max-width: none;'>
                                <option value=''>--</option>";
                            foreach($templates as $template){
                                echo "<option value='".$template['question_ids']."'>".$escaper->escapeHtml($template['name'])."</option>";
                            }
                            echo "</select>
                        </td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td >&nbsp;</td>
                        <td align='right'>".$escaper->escapeHtml($lang["SubQuestions"]).":</td>
                        <td colspan='4'>
                            <select class=\"sub_questions\" name=\"answers[sub_questions][0][]\" multiple=\"multiple\" style='max-width: none;'>";
                                foreach($sub_questions as $sub_question){
                                    echo "<option value='{$sub_question['id']}' >".$escaper->escapeHtml($sub_question['question'])."</option>";
                                }
                            echo "</select>
                        </td>
                        <td>&nbsp;</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan='6'>&nbsp;</td>
                        <td><a href='#' class='add-row'><img class=\"add-delete-icon\" src=\"../images/plus.png\" width=\"15px\" height=\"15px\" /></a></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </form>
        <table class='hide' id='adding-row'>
            <tbody>
                <tr>
                    <th width='10%'>".$escaper->escapeHtml($lang['Answer'])."</th>
                    <th width='10%'>".$escaper->escapeHtml($lang['FailControl'])."</th>
                    <th width='10%'>".$escaper->escapeHtml($lang['SubmitRisk'])."</th>
                    <th ><span class='risk-element' >".$escaper->escapeHtml($lang['Subject'])."</span></th>
                    <th width='10%'><span class='risk-element' >".$escaper->escapeHtml($lang['Owner'])."</span></th>
                    <th width='20%'><span class='risk-element' >".$escaper->escapeHtml($lang['AffectedAssets'])."</span></th>
                    <th width='50px'>&nbsp;</th>
                </tr>
                <tr >
                    <td>
                        <input type='text' placeholder='".$escaper->escapeHtml($lang['Answer'])."' value='' required name='answers[answer][]' style='max-width: none' >
                    </td>
                    <td align='center'>
                        <input type=\"checkbox\" class=\"exist\" name=\"answers[fail_control][]\" value=\"1\" />
                    </td>
                    <td align='center'>
                        <input type=\"checkbox\" class=\"submit-risk\" name=\"answers[submit_risk][]\" value=\"\"  />
                    </td>
                    <td align='center'>
                        <div class=\"risk-element\" >
                            <input type=\"text\" name=\"answers[risk_subject][]\" size=\"200\" placeholder=\"Enter Risk Subject\" style='max-width: none' />
                        </div>
                    </td>
                    <td align='center'>
                        ".create_dropdown("enabled_users", NULL, "answers[risk_owner][]", true, false, true, " class=\"risk-element\" ")."
                    </td>
                    <td>
                        <div class=\"ui-widget risk-element\">
                            <select class='assets-asset-groups-select' name='answers[assets_asset_groups][][]' multiple placeholder='{$affected_assets_placeholder}'></select>
                        </div>
                    </td>
                    <td><a href='#' class='delete-row'><img class=\"add-delete-icon\" src=\"../images/minus.png\" width=\"15px\" height=\"15px\" /></a></td>
                </tr>
                <tr class=\"risk-element\">
                    <td colspan='6'>
                        <table width='100%'>
                            <tr>
                                <td class='risk-scoring-container'>";
                                    display_score_html_from_assessment();
                                echo "</td>
                            </tr>
                            <tr>
                                <td>
                                    <div class='row-fluid'>
                                        <div class='span5 text-right'>
                                            ". $escaper->escapeHtml($lang['Tags']) .":
                                        </div>
                                        <div class='span7'>
                                            <input type=\"text\" readonly class=\"tags\" name=\"answers[tags][]\" value=\"\">
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td >&nbsp;</td>
                    <td align='right'>".$escaper->escapeHtml($lang["SubTemplate"]).":</td>
                    <td colspan='4'>
                        <select class='template-by-answer' style='max-width: none;'>
                            <option value=''>--</option>";
                        foreach($templates as $template){
                            echo "<option value='".$template['question_ids']."'>".$escaper->escapeHtml($template['name'])."</option>";
                        }
                        echo "</select>
                    </td>
                    <td>&nbsp;</td>
                </tr>
                <tr>
                    <td >&nbsp;</td>
                    <td align='right'>".$escaper->escapeHtml($lang["SubQuestions"]).":</td>
                    <td colspan='4'>
                        <select class=\"sub_questions\" multiple=\"multiple\" style='max-width: none;'>";
                            foreach($sub_questions as $sub_question){
                                echo "<option value='{$sub_question['id']}' >".$escaper->escapeHtml($sub_question['question'])."</option>";
                            }
                        echo "</select>
                    </td>
                    <td>&nbsp;</td>
                </tr>
            </tbody>
        </table>
    ";

    echo "
        <script>
            function createTagsInstance(\$e)
            {
                \$e.selectize({
                    plugins: ['remove_button', 'restore_on_backspace'],
                    delimiter: '+++',
                    create: function (input){
                        return {value: 'new_tag_' + input, label:input};
                    },
                    valueField: 'value',
                    labelField: 'label',
                    searchField: 'label',
                    preload: true,
                    load: function(query, callback) {
                        if (query.length) return callback();
                        $.ajax({
                            url: BASE_URL + '/api/management/tag_options_of_type?type=risk',
                            type: 'GET',
                            dataType: 'json',
                            error: function() {
                                console.log('Error loading!');
                                callback();
                            },
                            success: function(res) {
                                callback(res.data);
                            }
                        });
                    }
                });
            }
            createTagsInstance($('.tags', '#questionnaire-answers-container .answers-table'));
        </script>    
        <script>
            function set_index(name, parent){
                var key = 0;
                $('[name=\"'+name+'\"]', parent).each(function(){
                    $(this).val(key);
                    key++;
                })
            }
            function set_new_sub_question_names(parent){
                var key = 0;
                $('.sub_questions', parent).each(function(){
                    $(this).attr('name', 'answers[sub_questions]['+key+'][]');
                    key++;
                })
            }

            function refresh_affected_assets_widget_names(){
                $('#questionnaire_new_question_form select.assets-asset-groups-select').each(function(index, element){
                    $(element).attr('name', 'answers[assets_asset_groups][' + index + '][]');
                })
            }

            var assets_and_asset_groups = [];

            \$(document).ready(function(){
                \$('body').on('change', '.template-by-answer', function(){
                    var val = $(this).val();
                    if(val == '')
                        return;
                    
                    var valarray = val.split(',');
                    
                    var parent = $(this).closest('tr');
                    
                    $('.sub_questions', parent.next('tr')).val(valarray);
                    $('.sub_questions', parent.next('tr')).multiselect('refresh');
                });

                \$('.add-row').click(function(e){
                    e.preventDefault();
                    var appended_row= $($('#adding-row tbody').html()).appendTo('#questionnaire-answers-container > table.answers-table > tbody');
                    set_index('answers[submit_risk][]', '#questionnaire-answers-container');
                    set_index('answers[fail_control][]', '#questionnaire-answers-container');

                    refresh_affected_assets_widget_names();

                    selectize_assessment_answer_affected_assets_widget($('#questionnaire_new_question_form select.assets-asset-groups-select').not('.selectized'), assets_and_asset_groups);

                    set_new_sub_question_names('#questionnaire-answers-container');
                    $('.sub_questions', appended_row).multiselect({
                        enableFiltering: true,
                        enableCaseInsensitiveFiltering: true,
                        buttonWidth: '100%',
                        numberDisplayed: 1,
                        filterPlaceholder: '".$escaper->escapeHtml($lang['SearchForQuestion'])."'
                    })
                    createTagsInstance($('.tags', appended_row))
                })
                \$('body').on('click', '.submit-risk', function(){
                    if($(this).is(':checked')){
                        $(this).closest('tr').find('.risk-element').show();
                        $(this).parents('tr').next('.risk-element').show();
                        $(this).parents('tr').prev().find('.risk-element').show();
                    }else{
                        $(this).closest('tr').find('.risk-element').hide();
                        $(this).parents('tr').next('.risk-element').hide();
                        $(this).parents('tr').prev().find('.risk-element').hide();
                    }
                });
                \$('body').on('click', '.delete-row', function(){
                    \$(this).parents('tr').prev().remove();
                    \$(this).parents('tr').next().remove();
                    \$(this).parents('tr').next().remove();
                    \$(this).parents('tr').next().remove();
                    \$(this).parents('tr').remove();
                    set_index('answers[submit_risk][]', '#questionnaire-answers-container');
                    set_index('answers[fail_control][]', '#questionnaire-answers-container');
                    set_new_sub_question_names('#questionnaire-answers-container');

                    refresh_affected_assets_widget_names();
                });
                \$('.answers-table .sub_questions').multiselect({
                    enableFiltering: true,
                    enableCaseInsensitiveFiltering: true,
                    buttonWidth: '100%',
                    numberDisplayed: 1,
                    filterPlaceholder: '".$escaper->escapeHtml($lang['SearchForQuestion'])."'
                });
                \$('#compliance_audit').click(function(){
                    var compliance_audit = this.checked;
                    if(compliance_audit){
                        $('#mapped-controls-holder').show();
                    }else{
                        $('#mapped-controls-holder').hide();
                    }
                });

                $.ajax({
                    url: '/api/asset-group/options',
                    type: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        var data = res.data;
                        var len = data.length;
                        for (var i = 0; i < len; i++) {
                            var item = data[i];
                            item.id += '_' + item.class;

                            assets_and_asset_groups.push(item);
                        }

                        $('#questionnaire_new_question_form select.assets-asset-groups-select').each(function() {
                            selectize_assessment_answer_affected_assets_widget($(this), assets_and_asset_groups);
                        });
                    }
                });
            })
        </script>
    ";
    

}

/**************************************************
 * FUNCTION: ADD ASSESSMENT QUESTIONNAIRE SCORING *
 **************************************************/
function add_assessment_questionnaire_scoring($data){

    // Risk scoring method
    // 1 = Classic
    // 2 = CVSS
    // 3 = DREAD
    // 4 = OWASP
    // 5 = Custom
    $scoring_method = (int)$data['scoring_method'];

    // Classic Risk Scoring Inputs
    $CLASSIC_likelihood = (int)$data['CLASSIClikelihood'];
    $CLASSIC_impact =(int) $data['CLASSICimpact'];

    // CVSS Risk Scoring Inputs
    $AccessVector = $data['CVSSAccessVector'];
    $AccessComplexity = $data['CVSSAccessComplexity'];
    $Authentication = $data['CVSSAuthentication'];
    $ConfImpact = $data['CVSSConfImpact'];
    $IntegImpact = $data['CVSSIntegImpact'];
    $AvailImpact = $data['CVSSAvailImpact'];
    $Exploitability = $data['CVSSExploitability'];
    $RemediationLevel = $data['CVSSRemediationLevel'];
    $ReportConfidence = $data['CVSSReportConfidence'];
    $CollateralDamagePotential = $data['CVSSCollateralDamagePotential'];
    $TargetDistribution = $data['CVSSTargetDistribution'];
    $ConfidentialityRequirement = $data['CVSSConfidentialityRequirement'];
    $IntegrityRequirement = $data['CVSSIntegrityRequirement'];
    $AvailabilityRequirement = $data['CVSSAvailabilityRequirement'];

    // DREAD Risk Scoring Inputs
    $DREADDamage = (int)$data['DREADDamage'];
    $DREADReproducibility = (int)$data['DREADReproducibility'];
    $DREADExploitability = (int)$data['DREADExploitability'];
    $DREADAffectedUsers = (int)$data['DREADAffectedUsers'];
    $DREADDiscoverability = (int)$data['DREADDiscoverability'];

    // OWASP Risk Scoring Inputs
    $OWASPSkill = (int)$data['OWASPSkillLevel'];
    $OWASPMotive = (int)$data['OWASPMotive'];
    $OWASPOpportunity = (int)$data['OWASPOpportunity'];
    $OWASPSize = (int)$data['OWASPSize'];
    $OWASPDiscovery = (int)$data['OWASPEaseOfDiscovery'];
    $OWASPExploit = (int)$data['OWASPEaseOfExploit'];
    $OWASPAwareness = (int)$data['OWASPAwareness'];
    $OWASPIntrusionDetection = (int)$data['OWASPIntrusionDetection'];
    $OWASPLossOfConfidentiality = (int)$data['OWASPLossOfConfidentiality'];
    $OWASPLossOfIntegrity = (int)$data['OWASPLossOfIntegrity'];
    $OWASPLossOfAvailability = (int)$data['OWASPLossOfAvailability'];
    $OWASPLossOfAccountability = (int)$data['OWASPLossOfAccountability'];
    $OWASPFinancialDamage = (int)$data['OWASPFinancialDamage'];
    $OWASPReputationDamage = (int)$data['OWASPReputationDamage'];
    $OWASPNonCompliance = (int)$data['OWASPNonCompliance'];
    $OWASPPrivacyViolation = (int)$data['OWASPPrivacyViolation'];

    // Custom Risk Scoring
    $Custom = (float)$data['Custom'];

    // Custom Risk Scoring
    $ContributingLikelihood = (int)$data['ContributingLikelihood'];
    $ContributingImpacts = $data['ContributingImpacts'];

    // Open the database connection
    $db = db_open();

    // If the scoring method is Classic (1)
    if ($scoring_method == 1)
    {
        // Calculate the risk via classic method
        $calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

        // Create the database query
        $stmt = $db->prepare("INSERT INTO questionnaire_scoring (`scoring_method`, `calculated_risk`, `CLASSIC_likelihood`, `CLASSIC_impact`) VALUES (:scoring_method, :calculated_risk, :CLASSIC_likelihood, :CLASSIC_impact)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":CLASSIC_likelihood", $CLASSIC_likelihood, PDO::PARAM_INT);
        $stmt->bindParam(":CLASSIC_impact", $CLASSIC_impact, PDO::PARAM_INT);

        // Add the risk score
        $stmt->execute();
        $last_insert_id = $db->lastInsertId();
    }
    // If the scoring method is CVSS (2)
    else if ($scoring_method == 2)
    {
        // Get the numeric values for the CVSS submission
        $AccessVectorScore = get_cvss_numeric_value("AV", $AccessVector);
        $AccessComplexityScore = get_cvss_numeric_value("AC", $AccessComplexity);
        $AuthenticationScore = get_cvss_numeric_value("Au", $Authentication);
        $ConfImpactScore = get_cvss_numeric_value("C", $ConfImpact);
        $IntegImpactScore = get_cvss_numeric_value("I", $IntegImpact);
        $AvailImpactScore = get_cvss_numeric_value("A", $AvailImpact);
        $ExploitabilityScore = get_cvss_numeric_value("E", $Exploitability);
        $RemediationLevelScore = get_cvss_numeric_value("RL", $RemediationLevel);
        $ReportConfidenceScore = get_cvss_numeric_value("RC", $ReportConfidence);
        $CollateralDamagePotentialScore = get_cvss_numeric_value("CDP", $CollateralDamagePotential);
        $TargetDistributionScore = get_cvss_numeric_value("TD", $TargetDistribution);
        $ConfidentialityRequirementScore = get_cvss_numeric_value("CR", $ConfidentialityRequirement);
        $IntegrityRequirementScore = get_cvss_numeric_value("IR", $IntegrityRequirement);
        $AvailabilityRequirementScore = get_cvss_numeric_value("AR", $AvailabilityRequirement);

        // Calculate the risk via CVSS method
        $calculated_risk = calculate_cvss_score($AccessVectorScore, $AccessComplexityScore, $AuthenticationScore, $ConfImpactScore, $IntegImpactScore, $AvailImpactScore, $ExploitabilityScore, $RemediationLevelScore, $ReportConfidenceScore, $CollateralDamagePotentialScore, $TargetDistributionScore, $ConfidentialityRequirementScore, $IntegrityRequirementScore, $AvailabilityRequirementScore);

        // Create the database query
        $stmt = $db->prepare("INSERT INTO questionnaire_scoring (`scoring_method`, `calculated_risk`, `CVSS_AccessVector`, `CVSS_AccessComplexity`, `CVSS_Authentication`, `CVSS_ConfImpact`, `CVSS_IntegImpact`, `CVSS_AvailImpact`, `CVSS_Exploitability`, `CVSS_RemediationLevel`, `CVSS_ReportConfidence`, `CVSS_CollateralDamagePotential`, `CVSS_TargetDistribution`, `CVSS_ConfidentialityRequirement`, `CVSS_IntegrityRequirement`, `CVSS_AvailabilityRequirement`) VALUES (:scoring_method, :calculated_risk, :CVSS_AccessVector, :CVSS_AccessComplexity, :CVSS_Authentication, :CVSS_ConfImpact, :CVSS_IntegImpact, :CVSS_AvailImpact, :CVSS_Exploitability, :CVSS_RemediationLevel, :CVSS_ReportConfidence, :CVSS_CollateralDamagePotential, :CVSS_TargetDistribution, :CVSS_ConfidentialityRequirement, :CVSS_IntegrityRequirement, :CVSS_AvailabilityRequirement)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":CVSS_AccessVector", $AccessVector, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AccessComplexity", $AccessComplexity, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_Authentication", $Authentication, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ConfImpact", $ConfImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_IntegImpact", $IntegImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AvailImpact", $AvailImpact, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_Exploitability", $Exploitability, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_RemediationLevel", $RemediationLevel, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ReportConfidence", $ReportConfidence, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_CollateralDamagePotential", $CollateralDamagePotential, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_TargetDistribution", $TargetDistribution, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_ConfidentialityRequirement", $ConfidentialityRequirement, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_IntegrityRequirement", $IntegrityRequirement, PDO::PARAM_STR, 3);
        $stmt->bindParam(":CVSS_AvailabilityRequirement", $AvailabilityRequirement, PDO::PARAM_STR, 3);

        // Add the risk score
        $stmt->execute();
        $last_insert_id = $db->lastInsertId();
    }
    // If the scoring method is DREAD (3)
    else if ($scoring_method == 3)
    {
        // Calculate the risk via DREAD method
        $calculated_risk = ($DREADDamage + $DREADReproducibility + $DREADExploitability + $DREADAffectedUsers + $DREADDiscoverability)/5;

        // Create the database query
        $stmt = $db->prepare("INSERT INTO questionnaire_scoring (`scoring_method`, `calculated_risk`, `DREAD_DamagePotential`, `DREAD_Reproducibility`, `DREAD_Exploitability`, `DREAD_AffectedUsers`, `DREAD_Discoverability`) VALUES (:scoring_method, :calculated_risk, :DREAD_DamagePotential, :DREAD_Reproducibility, :DREAD_Exploitability, :DREAD_AffectedUsers, :DREAD_Discoverability)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":DREAD_DamagePotential", $DREADDamage, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Reproducibility", $DREADReproducibility, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Exploitability", $DREADExploitability, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_AffectedUsers", $DREADAffectedUsers, PDO::PARAM_INT);
        $stmt->bindParam(":DREAD_Discoverability", $DREADDiscoverability, PDO::PARAM_INT);

        // Add the risk score
        $stmt->execute();
        $last_insert_id = $db->lastInsertId();
    }
    // If the scoring method is OWASP (4)
    else if ($scoring_method == 4){
        $threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
        $vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

        // Average the threat agent and vulnerability factors to get the likelihood
        $OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;

        $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
        $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

        // Average the technical and business impacts to get the impact
        $OWASP_impact = ($technical_impact + $business_impact)/2;

        // Calculate the overall OWASP risk score
        $calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

        // Create the database query
        $stmt = $db->prepare("INSERT INTO questionnaire_scoring (`scoring_method`, `calculated_risk`, `OWASP_SkillLevel`, `OWASP_Motive`, `OWASP_Opportunity`, `OWASP_Size`, `OWASP_EaseOfDiscovery`, `OWASP_EaseOfExploit`, `OWASP_Awareness`, `OWASP_IntrusionDetection`, `OWASP_LossOfConfidentiality`, `OWASP_LossOfIntegrity`, `OWASP_LossOfAvailability`, `OWASP_LossOfAccountability`, `OWASP_FinancialDamage`, `OWASP_ReputationDamage`, `OWASP_NonCompliance`, `OWASP_PrivacyViolation`) VALUES (:scoring_method, :calculated_risk, :OWASP_SkillLevel, :OWASP_Motive, :OWASP_Opportunity, :OWASP_Size, :OWASP_EaseOfDiscovery, :OWASP_EaseOfExploit, :OWASP_Awareness, :OWASP_IntrusionDetection, :OWASP_LossOfConfidentiality, :OWASP_LossOfIntegrity, :OWASP_LossOfAvailability, :OWASP_LossOfAccountability, :OWASP_FinancialDamage, :OWASP_ReputationDamage, :OWASP_NonCompliance, :OWASP_PrivacyViolation)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":OWASP_SkillLevel", $OWASPSkill, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Motive", $OWASPMotive, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Opportunity",$OWASPOpportunity, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Size",$OWASPSize, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_EaseOfDiscovery",$OWASPDiscovery, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_EaseOfExploit",$OWASPExploit, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_Awareness",$OWASPAwareness, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_IntrusionDetection",$OWASPIntrusionDetection, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfConfidentiality",$OWASPLossOfConfidentiality, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfIntegrity",$OWASPLossOfIntegrity, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfAvailability",$OWASPLossOfAvailability, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_LossOfAccountability",$OWASPLossOfAccountability, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_FinancialDamage",$OWASPFinancialDamage, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_ReputationDamage",$OWASPReputationDamage, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_NonCompliance",$OWASPNonCompliance, PDO::PARAM_INT);
        $stmt->bindParam(":OWASP_PrivacyViolation",$OWASPPrivacyViolation, PDO::PARAM_INT);

        // Add the risk score
        $stmt->execute();
        $last_insert_id = $db->lastInsertId();
    }
    // If the scoring method is Custom (5)
    else if ($scoring_method == 5){
        // If the custom value is not between 0 and 10
        if (!(($Custom >= 0) && ($Custom <= 10)))
        {
            // Set the custom value to 10
            $Custom = get_setting('default_risk_score');
        }

        // Calculated risk is the custom value
        $calculated_risk = $Custom;

        // Create the database query
        $stmt = $db->prepare("INSERT INTO `questionnaire_scoring` (`scoring_method`, `calculated_risk`, `Custom`) VALUES (:scoring_method, :calculated_risk, :Custom)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":Custom", $Custom, PDO::PARAM_STR, 5);

        // Add the risk score
        $stmt->execute();

        $last_insert_id = $db->lastInsertId();
    }
    // If the scoring method is Contributing Risk (6)
    else if ($scoring_method == 6){
        $max_likelihood = count(get_table("likelihood"));
        $max_impact = count(get_table("impact"));
        
        $ImpactSum = 0;
        foreach($ContributingImpacts as $contributing_risk_id => $ContributingImpact){
            $weight = get_contributing_weight_by_id($contributing_risk_id);
            $ImpactSum += $weight * $ContributingImpact;
        }

        // Set default Contributing Likelihood value
        $ContributingLikelihood = $ContributingLikelihood ? $ContributingLikelihood : $max_likelihood;
        // Set default Contributing Impact value
        $ImpactSum = $ImpactSum ? $ImpactSum : $max_impact;
        
        $calculated_risk = round(($ContributingLikelihood + $ImpactSum) / ($max_likelihood + $max_impact) * 10, 2);
        
        // Create the database query
        $stmt = $db->prepare("INSERT INTO `questionnaire_scoring` (`scoring_method`, `calculated_risk`, `Contributing_Likelihood`) VALUES (:scoring_method, :calculated_risk, :Contributing_Likelihood)");
        $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
        $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
        $stmt->bindParam(":Contributing_Likelihood", $ContributingLikelihood, PDO::PARAM_INT);
        $stmt->execute();
        
        $last_insert_id = $db->lastInsertId();
        
        // Save contributing impacts and contributing risk IDs
        foreach($ContributingImpacts as $contributing_risk_id => $ContributingImpact){
            // Create the database query
            $stmt = $db->prepare("INSERT INTO `questionnaire_scoring_contributing_impacts` (`questionnaire_scoring_id`, `contributing_risk_id`, `impact`) VALUES (:last_insert_id, :contributing_risk_id, :impact)");
            $stmt->bindParam(":last_insert_id", $last_insert_id, PDO::PARAM_INT);
            $stmt->bindParam(":contributing_risk_id", $contributing_risk_id, PDO::PARAM_INT);
            $stmt->bindParam(":impact", $ContributingImpact, PDO::PARAM_INT);
            $stmt->execute();
        }
    }
    // Otherwise
    else
    {
        return false;
    }
    
    // Close the database connection
    db_close($db);

    return array($last_insert_id, $calculated_risk);
}

/*****************************************************
 * FUNCTION: UPDATE ASSESSMENT QUESTIONNAIRE SCORING *
 *****************************************************/
function update_assessment_questionnaire_scoring($id, $data)
{
    // Risk scoring method
    // 1 = Classic
    // 2 = CVSS
    // 3 = DREAD
    // 4 = OWASP
    // 5 = Custom
    $scoring_method = (int)$data['scoring_method'];

    // Classic Risk Scoring Inputs
    $CLASSIC_likelihood = (int)$data['CLASSIClikelihood'];
    $CLASSIC_impact =(int) $data['CLASSICimpact'];

    // CVSS Risk Scoring Inputs
    $AccessVector = $data['CVSSAccessVector'];
    $AccessComplexity = $data['CVSSAccessComplexity'];
    $Authentication = $data['CVSSAuthentication'];
    $ConfImpact = $data['CVSSConfImpact'];
    $IntegImpact = $data['CVSSIntegImpact'];
    $AvailImpact = $data['CVSSAvailImpact'];
    $Exploitability = $data['CVSSExploitability'];
    $RemediationLevel = $data['CVSSRemediationLevel'];
    $ReportConfidence = $data['CVSSReportConfidence'];
    $CollateralDamagePotential = $data['CVSSCollateralDamagePotential'];
    $TargetDistribution = $data['CVSSTargetDistribution'];
    $ConfidentialityRequirement = $data['CVSSConfidentialityRequirement'];
    $IntegrityRequirement = $data['CVSSIntegrityRequirement'];
    $AvailabilityRequirement = $data['CVSSAvailabilityRequirement'];

    // DREAD Risk Scoring Inputs
    $DREADDamage = (int)$data['DREADDamage'];
    $DREADReproducibility = (int)$data['DREADReproducibility'];
    $DREADExploitability = (int)$data['DREADExploitability'];
    $DREADAffectedUsers = (int)$data['DREADAffectedUsers'];
    $DREADDiscoverability = (int)$data['DREADDiscoverability'];

    // OWASP Risk Scoring Inputs
    $OWASPSkill = (int)$data['OWASPSkillLevel'];
    $OWASPMotive = (int)$data['OWASPMotive'];
    $OWASPOpportunity = (int)$data['OWASPOpportunity'];
    $OWASPSize = (int)$data['OWASPSize'];
    $OWASPDiscovery = (int)$data['OWASPEaseOfDiscovery'];
    $OWASPExploit = (int)$data['OWASPEaseOfExploit'];
    $OWASPAwareness = (int)$data['OWASPAwareness'];
    $OWASPIntrusionDetection = (int)$data['OWASPIntrusionDetection'];
    $OWASPLossOfConfidentiality = (int)$data['OWASPLossOfConfidentiality'];
    $OWASPLossOfIntegrity = (int)$data['OWASPLossOfIntegrity'];
    $OWASPLossOfAvailability = (int)$data['OWASPLossOfAvailability'];
    $OWASPLossOfAccountability = (int)$data['OWASPLossOfAccountability'];
    $OWASPFinancialDamage = (int)$data['OWASPFinancialDamage'];
    $OWASPReputationDamage = (int)$data['OWASPReputationDamage'];
    $OWASPNonCompliance = (int)$data['OWASPNonCompliance'];
    $OWASPPrivacyViolation = (int)$data['OWASPPrivacyViolation'];

    // Custom Risk Scoring
    $custom = (float)$data['Custom'];

    // Contributing Risk Scoring
    $ContributingLikelihood = (int)$data['ContributingLikelihood'];
    $ContributingImpacts = $data['ContributingImpacts'];

    // Open the database connection
    $db = db_open();

    // If the scoring method is Classic (1)
    if ($scoring_method == 1)
    {
            // Calculate the risk via classic method
            $calculated_risk = calculate_risk($CLASSIC_impact, $CLASSIC_likelihood);

            // Create the database query
            $stmt = $db->prepare("UPDATE questionnaire_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, CLASSIC_likelihood=:CLASSIC_likelihood, CLASSIC_impact=:CLASSIC_impact WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":CLASSIC_likelihood", $CLASSIC_likelihood, PDO::PARAM_INT);
            $stmt->bindParam(":CLASSIC_impact", $CLASSIC_impact, PDO::PARAM_INT);
        $stmt->execute();
    }
    // If the scoring method is CVSS (2)
    else if ($scoring_method == 2)
    {
            // Get the numeric values for the CVSS submission
            $AccessVectorScore = get_cvss_numeric_value("AV", $AccessVector);
            $AccessComplexityScore = get_cvss_numeric_value("AC", $AccessComplexity);
            $AuthenticationScore = get_cvss_numeric_value("Au", $Authentication);
            $ConfImpactScore = get_cvss_numeric_value("C", $ConfImpact);
            $IntegImpactScore = get_cvss_numeric_value("I", $IntegImpact);
            $AvailImpactScore = get_cvss_numeric_value("A", $AvailImpact);
            $ExploitabilityScore = get_cvss_numeric_value("E", $Exploitability);
            $RemediationLevelScore = get_cvss_numeric_value("RL", $RemediationLevel);
            $ReportConfidenceScore = get_cvss_numeric_value("RC", $ReportConfidence);
            $CollateralDamagePotentialScore = get_cvss_numeric_value("CDP", $CollateralDamagePotential);
            $TargetDistributionScore = get_cvss_numeric_value("TD", $TargetDistribution);
            $ConfidentialityRequirementScore = get_cvss_numeric_value("CR", $ConfidentialityRequirement);
            $IntegrityRequirementScore = get_cvss_numeric_value("IR", $IntegrityRequirement);
            $AvailabilityRequirementScore = get_cvss_numeric_value("AR", $AvailabilityRequirement);

            // Calculate the risk via CVSS method
            $calculated_risk = calculate_cvss_score($AccessVectorScore, $AccessComplexityScore, $AuthenticationScore, $ConfImpactScore, $IntegImpactScore, $AvailImpactScore, $ExploitabilityScore, $RemediationLevelScore, $ReportConfidenceScore, $CollateralDamagePotentialScore, $TargetDistributionScore, $ConfidentialityRequirementScore, $IntegrityRequirementScore, $AvailabilityRequirementScore);
            

            // Create the database query
            $stmt = $db->prepare("UPDATE questionnaire_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, CVSS_AccessVector=:CVSS_AccessVector, CVSS_AccessComplexity=:CVSS_AccessComplexity, CVSS_Authentication=:CVSS_Authentication, CVSS_ConfImpact=:CVSS_ConfImpact, CVSS_IntegImpact=:CVSS_IntegImpact, CVSS_AvailImpact=:CVSS_AvailImpact, CVSS_Exploitability=:CVSS_Exploitability, CVSS_RemediationLevel=:CVSS_RemediationLevel, CVSS_ReportConfidence=:CVSS_ReportConfidence, CVSS_CollateralDamagePotential=:CVSS_CollateralDamagePotential, CVSS_TargetDistribution=:CVSS_TargetDistribution, CVSS_ConfidentialityRequirement=:CVSS_ConfidentialityRequirement, CVSS_IntegrityRequirement=:CVSS_IntegrityRequirement, CVSS_AvailabilityRequirement=:CVSS_AvailabilityRequirement WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":CVSS_AccessVector", $AccessVector, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_AccessComplexity", $AccessComplexity, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_Authentication", $Authentication, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_ConfImpact", $ConfImpact, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_IntegImpact", $IntegImpact, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_AvailImpact", $AvailImpact, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_Exploitability", $Exploitability, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_RemediationLevel", $RemediationLevel, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_ReportConfidence", $ReportConfidence, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_CollateralDamagePotential", $CollateralDamagePotential, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_TargetDistribution", $TargetDistribution, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_ConfidentialityRequirement", $ConfidentialityRequirement, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_IntegrityRequirement", $IntegrityRequirement, PDO::PARAM_STR, 3);
            $stmt->bindParam(":CVSS_AvailabilityRequirement", $AvailabilityRequirement, PDO::PARAM_STR, 3);
        $stmt->execute();
    }
    // If the scoring method is DREAD (3)
    else if ($scoring_method == 3)
    {
            // Calculate the risk via DREAD method
            $calculated_risk = ($DREADDamage + $DREADReproducibility + $DREADExploitability + $DREADAffectedUsers + $DREADDiscoverability)/5;

            // Create the database query
            $stmt = $db->prepare("UPDATE questionnaire_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, DREAD_DamagePotential=:DREAD_DamagePotential, DREAD_Reproducibility=:DREAD_Reproducibility, DREAD_Exploitability=:DREAD_Exploitability, DREAD_AffectedUsers=:DREAD_AffectedUsers, DREAD_Discoverability=:DREAD_Discoverability WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":DREAD_DamagePotential", $DREADDamage, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_Reproducibility", $DREADReproducibility, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_Exploitability", $DREADExploitability, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_AffectedUsers", $DREADAffectedUsers, PDO::PARAM_INT);
            $stmt->bindParam(":DREAD_Discoverability", $DREADDiscoverability, PDO::PARAM_INT);
        $stmt->execute();
    }
    // If the scoring method is OWASP (4)
    else if ($scoring_method == 4)
    {
            $threat_agent_factors = ($OWASPSkill + $OWASPMotive + $OWASPOpportunity + $OWASPSize)/4;
            $vulnerability_factors = ($OWASPDiscovery + $OWASPExploit + $OWASPAwareness + $OWASPIntrusionDetection)/4;

            // Average the threat agent and vulnerability factors to get the likelihood
            $OWASP_likelihood = ($threat_agent_factors + $vulnerability_factors)/2;

            $technical_impact = ($OWASPLossOfConfidentiality + $OWASPLossOfIntegrity + $OWASPLossOfAvailability + $OWASPLossOfAccountability)/4;
            $business_impact = ($OWASPFinancialDamage + $OWASPReputationDamage + $OWASPNonCompliance + $OWASPPrivacyViolation)/4;

            // Average the technical and business impacts to get the impact
            $OWASP_impact = ($technical_impact + $business_impact)/2;

            // Calculate the overall OWASP risk score
            $calculated_risk = round((($OWASP_impact * $OWASP_likelihood) / 10), 1);

            // Create the database query
            $stmt = $db->prepare("UPDATE questionnaire_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, OWASP_SkillLevel=:OWASP_SkillLevel, OWASP_Motive=:OWASP_Motive, OWASP_Opportunity=:OWASP_Opportunity, OWASP_Size=:OWASP_Size, OWASP_EaseOfDiscovery=:OWASP_EaseOfDiscovery, OWASP_EaseOfExploit=:OWASP_EaseOfExploit, OWASP_Awareness=:OWASP_Awareness, OWASP_IntrusionDetection=:OWASP_IntrusionDetection, OWASP_LossOfConfidentiality=:OWASP_LossOfConfidentiality, OWASP_LossOfIntegrity=:OWASP_LossOfIntegrity, OWASP_LossOfAvailability=:OWASP_LossOfAvailability, OWASP_LossOfAccountability=:OWASP_LossOfAccountability, OWASP_FinancialDamage=:OWASP_FinancialDamage, OWASP_ReputationDamage=:OWASP_ReputationDamage, OWASP_NonCompliance=:OWASP_NonCompliance, OWASP_PrivacyViolation=:OWASP_PrivacyViolation WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":OWASP_SkillLevel", $OWASPSkill, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Motive", $OWASPMotive, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Opportunity",$OWASPOpportunity, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Size",$OWASPSize, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_EaseOfDiscovery",$OWASPDiscovery, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_EaseOfExploit",$OWASPExploit, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_Awareness",$OWASPAwareness, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_IntrusionDetection",$OWASPIntrusionDetection, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfConfidentiality",$OWASPLossOfConfidentiality, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfIntegrity",$OWASPLossOfIntegrity, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfAvailability",$OWASPLossOfAvailability, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_LossOfAccountability",$OWASPLossOfAccountability, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_FinancialDamage",$OWASPFinancialDamage, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_ReputationDamage",$OWASPReputationDamage, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_NonCompliance",$OWASPNonCompliance, PDO::PARAM_INT);
            $stmt->bindParam(":OWASP_PrivacyViolation",$OWASPPrivacyViolation, PDO::PARAM_INT);
        $stmt->execute();
    }
    // If the scoring method is Custom (5)
    else if ($scoring_method == 5)
    {
            // If the custom value is not between 0 and 10
            if (!(($custom >= 0) && ($custom <= 10)))
            {
                    // Set the custom value to 10
                    $custom = 10;
            }

            // Calculated risk is the custom value
            $calculated_risk = $custom;

            // Create the database query
            $stmt = $db->prepare("UPDATE questionnaire_scoring SET scoring_method=:scoring_method, calculated_risk=:calculated_risk, Custom=:Custom WHERE id=:id");
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":scoring_method", $scoring_method, PDO::PARAM_INT);
            $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
            $stmt->bindParam(":Custom", $custom, PDO::PARAM_STR, 5);
        $stmt->execute();
    }
    // If the scoring method is Contributing Risk (6)
    else if ($scoring_method == 6)
    {
        $calculated_risk = update_contributing_questionnaire_score($id, $ContributingLikelihood, $ContributingImpacts);
    }
    // Otherwise
    else
    {
        return false;
    }


    // Close the database connection
    db_close($db);
    
    return $calculated_risk;
}

/*****************************************************
 * FUNCTION: UPDATE CONTRIBUTING QUESTIONNAIRE SCORE *
 *****************************************************/
function update_contributing_questionnaire_score($questionnaire_scoring_id, $ContributingLikelihood="", $ContributingImpacts=[])
{
    // Open the database connection
    $db = db_open();

    $max_likelihood = count(get_table("likelihood"));
    $max_impact = count(get_table("impact"));
    
    $ImpactSum = 0;
    foreach($ContributingImpacts as $contributing_risk_id => $ContributingImpact){
        $weight = get_contributing_weight_by_id($contributing_risk_id);
        $ImpactSum += $weight * $ContributingImpact;
    }
    
    // Set default Contributing Likelihood value
    $ContributingLikelihood = $ContributingLikelihood ? $ContributingLikelihood : $max_likelihood;
    // Set default Contributing Impact value
    $ImpactSum = $ImpactSum ? $ImpactSum : $max_impact;
    
    $calculated_risk = round(($ContributingLikelihood + $ImpactSum) / ($max_likelihood + $max_impact) * 10, 2);

    // Create the database query
    $stmt = $db->prepare("UPDATE questionnaire_scoring SET calculated_risk=:calculated_risk, Contributing_Likelihood=:Contributing_Likelihood WHERE id=:id");
    $stmt->bindParam(":id", $questionnaire_scoring_id, PDO::PARAM_INT);
    $stmt->bindParam(":calculated_risk", $calculated_risk, PDO::PARAM_STR);
    $stmt->bindParam(":Contributing_Likelihood", $ContributingLikelihood, PDO::PARAM_INT);
    // Add the risk score
    $stmt->execute();
    
    // Create the database query
    $stmt = $db->prepare("DELETE from questionnaire_scoring_contributing_impacts WHERE questionnaire_scoring_id=:id");
    $stmt->bindParam(":id", $questionnaire_scoring_id, PDO::PARAM_INT);
    // Delete existing all questionnaire scoring contributing impacts
    $stmt->execute();
    // Save contributing impacts and contributing risk IDs
    foreach($ContributingImpacts as $contributing_risk_id => $ContributingImpact){
        // Create the database query
        $stmt = $db->prepare("INSERT INTO `questionnaire_scoring_contributing_impacts` (`questionnaire_scoring_id`, `contributing_risk_id`, `impact`) VALUES (:questionnaire_scoring_id, :contributing_risk_id, :impact)");
        $stmt->bindParam(":questionnaire_scoring_id", $questionnaire_scoring_id, PDO::PARAM_INT);
        $stmt->bindParam(":contributing_risk_id", $contributing_risk_id, PDO::PARAM_INT);
        $stmt->bindParam(":impact", $ContributingImpact, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);

    return $calculated_risk;
}

/****************************************************
 * FUNCTION: ADD QUESTIONNAIRE QUESTION AND ANSWERS *
 ****************************************************/
function add_questionnaire_question_answers($question, $answers=[], $assessment_scores=[]){
    // Open the database connection
    $db = db_open();
    // Add a question
    $stmt = $db->prepare("INSERT INTO `questionnaire_questions` SET `question` = :question, `compliance_audit`=:compliance_audit, `has_file`=:has_file, `mapped_controls`=:mapped_controls; ");
    $stmt->bindParam(":question", $question['question'], PDO::PARAM_STR);
    $stmt->bindParam(":has_file", $question['has_file'], PDO::PARAM_INT);
    $stmt->bindParam(":compliance_audit", $question['compliance_audit'], PDO::PARAM_INT);
    $stmt->bindParam(":mapped_controls", $question['mapped_controls'], PDO::PARAM_STR);
    $stmt->execute();
    $question_id = $db->lastInsertId();
    
    // If answers param was set
    if($answers){
        // Add answers
        foreach($answers['answer'] as $key => $answer){
            if(!$answer)
                continue;

            list($questionnaire_scoring_id, $risk_score) = add_assessment_questionnaire_scoring($assessment_scores[$key]);

            $submit_risk = (!empty($answers['submit_risk']) && in_array($key, $answers['submit_risk'])) ? 1 : 0;
            $fail_control = (!empty($answers['fail_control']) && in_array($key, $answers['fail_control'])) ? 1 : 0;
            $sub_questions = empty($answers['sub_questions'][$key]) ? "" : implode(",", $answers['sub_questions'][$key]);
            $tags = $answers['tags'][$key] ? $answers['tags'][$key] : "";
            
            $tags = create_new_tag_from_string($tags, "+++");
            
            $stmt = $db->prepare("INSERT INTO `questionnaire_answers` SET `question_id` = :question_id, `answer` = :answer, fail_control=:fail_control, submit_risk=:submit_risk, risk_subject=:risk_subject, risk_owner=:risk_owner, `questionnaire_scoring_id`=:questionnaire_scoring_id, `ordering` = :ordering, `sub_questions` = :sub_questions, `tag_ids`=:tags; ");
            $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
            $stmt->bindParam(":answer", $answers['answer'][$key], PDO::PARAM_STR);
            $stmt->bindParam(":fail_control", $fail_control, PDO::PARAM_STR);
            $stmt->bindParam(":submit_risk", $submit_risk, PDO::PARAM_STR);
            $stmt->bindParam(":risk_subject", $answers['risk_subject'][$key], PDO::PARAM_STR);
            $answers['risk_owner'][$key] = (int)$answers['risk_owner'][$key];
            $stmt->bindParam(":risk_owner", $answers['risk_owner'][$key], PDO::PARAM_INT);
            $stmt->bindParam(":questionnaire_scoring_id", $questionnaire_scoring_id, PDO::PARAM_INT);
            $stmt->bindParam(":ordering", $key, PDO::PARAM_INT);
            $stmt->bindParam(":sub_questions", $sub_questions, PDO::PARAM_STR);
            $stmt->bindParam(":tags", $tags, PDO::PARAM_STR);
            $stmt->execute();
            
            $answer_id = $db->lastInsertId();
            
            $assets_and_groups = [];

            if ($answers['assets_asset_groups'] && !empty($answers['assets_asset_groups'][$key]) && is_array($answers['assets_asset_groups'][$key])) {
                $assets_and_groups = $answers['assets_asset_groups'][$key];
            }

            process_selected_assets_asset_groups_of_type($answer_id, $assets_and_groups, 'questionnaire_answer');
        }
    }
    
    $message = "A questionnaire question, \"{$question['question']}\" was added by username \"" . $_SESSION['user']."\".";
    write_log($question_id+1000, $_SESSION['uid'], $message, 'questionnaire_question');
    
    return $question_id;
}

/****************************************************
 * FUNCTION: ADD QUESTIONNAIRE QUESTION AND ANSWERS *
 ****************************************************/
function update_questionnaire_question_answers($question_id, $question, $answers, $assessment_scores){
    // Open the database connection
    $db = db_open();

    // Add a question
    $stmt = $db->prepare("UPDATE `questionnaire_questions` SET `question` = :question, `compliance_audit`=:compliance_audit, `has_file`=:has_file, `mapped_controls`=:mapped_controls WHERE id=:question_id; ");
    $stmt->bindParam(":question", $question['question'], PDO::PARAM_STR);
    $stmt->bindParam(":has_file", $question['has_file'], PDO::PARAM_INT);
    $stmt->bindParam(":compliance_audit", $question['compliance_audit'], PDO::PARAM_INT);
    $stmt->bindParam(":mapped_controls", $question['mapped_controls'], PDO::PARAM_STR);
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();

    $ordering = 0;

    $updated_answer_ids = [];
    
    // Add answers
    foreach($answers['answer'] as $key => $answer){
        if(!$answer)
            continue;

        // Had to make the in_array() call strict, because the 0 returned as key for new answers caused issues.
        // Also added strval($key) to make sure they're the same type.
        // The above mentioned 0 was treated as int and that's why it was causing issues with in_array().
        $submit_risk = (!empty($answers['submit_risk']) && in_array(strval($key), $answers['submit_risk'], true)) ? 1 : 0;
        $fail_control = (!empty($answers['fail_control']) && in_array($key, $answers['fail_control'])) ? 1 : 0;
        $sub_questions = empty($answers['sub_questions'][$key]) ? "" : implode(",", $answers['sub_questions'][$key]);
        $risk_owner = ctype_digit($answers['risk_owner'][$key]) ? $answers['risk_owner'][$key] : null;
        $tags = $answers['tags'][$key] ? $answers['tags'][$key] : '';
        $tags = create_new_tag_from_string($tags, "+++");
        
        // If this is new answer
        if(stripos($key, "id_") === false){
            list($questionnaire_scoring_id, $risk_score) = add_assessment_questionnaire_scoring($assessment_scores[$key]);
            $stmt = $db->prepare("INSERT INTO `questionnaire_answers` SET `question_id` = :question_id, `answer` = :answer, `submit_risk`=:submit_risk, `fail_control`=:fail_control, `risk_subject`=:risk_subject, `risk_owner`=:risk_owner, `questionnaire_scoring_id`=:questionnaire_scoring_id, `ordering` = :ordering, `sub_questions`=:sub_questions, `tag_ids`=:tags; ");
            $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
            $stmt->bindParam(":answer", $answers['answer'][$key], PDO::PARAM_STR);
            $stmt->bindParam(":fail_control", $fail_control, PDO::PARAM_INT);
            $stmt->bindParam(":submit_risk", $submit_risk, PDO::PARAM_INT);
            $stmt->bindParam(":risk_subject", $answers['risk_subject'][$key], PDO::PARAM_STR);
            $stmt->bindParam(":risk_owner", $risk_owner, PDO::PARAM_STR);
            $stmt->bindParam(":questionnaire_scoring_id", $questionnaire_scoring_id, PDO::PARAM_INT);
            $stmt->bindParam(":ordering", $ordering, PDO::PARAM_INT);
            $stmt->bindParam(":sub_questions", $sub_questions, PDO::PARAM_STR);
            $stmt->bindParam(":tags", $tags, PDO::PARAM_STR);
            $stmt->execute();

            // Get the answer id of the last insert
            $answer_id = $db->lastInsertId();
            
            $assets_and_groups = [];

            if ($answers['assets_asset_groups'] && !empty($answers['assets_asset_groups'][$key]) && is_array($answers['assets_asset_groups'][$key])) {
                $assets_and_groups = $answers['assets_asset_groups'][$key];
            }

            process_selected_assets_asset_groups_of_type($answer_id, $assets_and_groups, 'questionnaire_answer');
            
            $updated_answer_ids[] = $answer_id;
        }
        // If this is existing answer
        else{
            $answer_id = trim($key, "id_");
            $updated_answer_ids[] = $answer_id;
            if(empty($answers['scoring_id'][$key]))
            {
                list($questionnaire_scoring_id, $risk_score) = add_assessment_questionnaire_scoring($assessment_scores[$key]);
            }
            else
            {
                $questionnaire_scoring_id = $answers['scoring_id'][$key];
                $risk_score = update_assessment_questionnaire_scoring($answers['scoring_id'][$key], $assessment_scores[$key]);
            }

            $stmt = $db->prepare("UPDATE `questionnaire_answers` SET `question_id` = :question_id, `answer` = :answer, `fail_control`=:fail_control, `submit_risk`=:submit_risk, `risk_subject`=:risk_subject, `risk_owner`=:risk_owner, `questionnaire_scoring_id`=:questionnaire_scoring_id, `ordering` = :ordering, `sub_questions`=:sub_questions, `tag_ids`=:tags WHERE `id`=:id; ");
            $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
            $stmt->bindParam(":answer", $answers['answer'][$key], PDO::PARAM_STR);
            $stmt->bindParam(":fail_control", $fail_control, PDO::PARAM_INT);
            $stmt->bindParam(":submit_risk", $submit_risk, PDO::PARAM_INT);
            $stmt->bindParam(":risk_subject", $answers['risk_subject'][$key], PDO::PARAM_STR);
            $stmt->bindParam(":risk_owner", $risk_owner, PDO::PARAM_STR);
            $stmt->bindParam(":questionnaire_scoring_id", $questionnaire_scoring_id, PDO::PARAM_INT);
            $stmt->bindParam(":ordering", $ordering, PDO::PARAM_INT);
            $stmt->bindParam(":sub_questions", $sub_questions, PDO::PARAM_STR);
            $stmt->bindParam(":tags", $tags, PDO::PARAM_STR);
            $stmt->bindParam(":id", $answer_id, PDO::PARAM_INT);
            $stmt->execute();

            $assets_and_groups = [];

            if (!empty($answers['assets_asset_groups'][$key]) && $answers['assets_asset_groups'] && is_array($answers['assets_asset_groups'][$key])) {
                $assets_and_groups = $answers['assets_asset_groups'][$key];
            }

            process_selected_assets_asset_groups_of_type($answer_id, $assets_and_groups, 'questionnaire_answer');
        }

        $ordering++;
    }

    // Delete answers removed by user
    $stmt = $db->prepare("DELETE FROM `questionnaire_answers` WHERE question_id=:question_id AND id NOT IN (".implode(",", $updated_answer_ids)."); ");
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    cleanup_questionnaire_answer_junction_tables();
    
    $message = "A questionnaire question, \"{$question['question']}\" was updated by username \"" . $_SESSION['user']."\".";
    write_log($question_id+1000, $_SESSION['uid'], $message, 'questionnaire_question');
}


function cleanup_questionnaire_answer_junction_tables() {

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        DELETE
            `qata`
        FROM
            `questionnaire_answers_to_assets` qata
            LEFT OUTER JOIN `questionnaire_answers` qa ON `qata`.`questionnaire_answer_id` = `qa`.`id`
        WHERE
            `qa`.`id` IS NULL;
    ");
    $stmt->execute();

    $stmt = $db->prepare("
        DELETE
            `qatag`
        FROM
            `questionnaire_answers_to_asset_groups` qatag
            LEFT OUTER JOIN `questionnaire_answers` qa ON `qatag`.`questionnaire_answer_id` = `qa`.`id`
        WHERE
            `qa`.`id` IS NULL;
    ");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*******************************************
 * FUNCTION: DELETE QUESTIONNAIRE QUESTION *
 *******************************************/
function delete_questionnaire_question($question_id){
    $question = get_questionnaire_question($question_id);
    
    // Open the database connection
    $db = db_open();

    // Delete questionnaire scoring that has question_id
    $stmt = $db->prepare("DELETE t2 FROM `questionnaire_answers` t1 INNER JOIN questionnaire_scoring t2 ON t1.questionnaire_scoring_id = t2.id
        WHERE t1.question_id=:question_id;");
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Delete questionnaire answers that has question_id
    $stmt = $db->prepare("DELETE FROM `questionnaire_answers` WHERE question_id=:question_id;");
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete questionnaire tempalte relations that has question_id
    $stmt = $db->prepare("DELETE FROM `questionnaire_template_question` WHERE questionnaire_question_id=:question_id;");
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete questionnaire answers that has question_id
    $stmt = $db->prepare("DELETE FROM `questionnaire_questions` WHERE id=:question_id;");
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    cleanup_questionnaire_answer_junction_tables();

    $message = "A questionnaire question, \"{$question['question']}\" was deleted by username \"" . $_SESSION['user']."\".";
    write_log($question_id+1000, $_SESSION['uid'], $message, 'questionnaire_question');
}

/*****************************************************
 * FUNCTION: GET QUESTIONNAIRE ANSWER BY ANSWER TEXT *
 *****************************************************/
function get_questionnaire_answer_by_id($answer_id)
{
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT question_id, answer, risk_subject, ordering, questionnaire_scoring_id, submit_risk, fail_control, risk_owner, sub_questions, tag_ids
        FROM `questionnaire_answers`  
        WHERE 
            id = :answer_id
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":answer_id", $answer_id, PDO::PARAM_INT);
    $stmt->execute();
    $answer = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $answer;
}

/*********************************************************
 * FUNCTION: GET QUESTIONNAIRE QUESTION BY QUESTION TEXT *
 *********************************************************/
function get_questionnaire_question_by_text($question){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.id, t1.question, t1.has_file
        FROM `questionnaire_questions` t1 
        WHERE 
            t1.question = :question
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":question", $question, PDO::PARAM_STR);
    $stmt->execute();
    $question = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $question;
}

/*******************************************************
 * FUNCTION: GET QUESTIONNAIRE QUESTION BY QUESTION ID *
 *******************************************************/
function get_questionnaire_question($question_id){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.id, t1.question, t1.compliance_audit, t1.has_file, t1.mapped_controls
        FROM `questionnaire_questions` t1 
        WHERE 
            t1.id = :question_id
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();
    $question = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql = "
        SELECT t1.id, t1.answer, t1.submit_risk, t1.fail_control , t1.risk_subject, t1.risk_owner, t1.questionnaire_scoring_id, t1.sub_questions, GROUP_CONCAT(DISTINCT CONCAT(t3.id, '---', t3.tag) SEPARATOR '+++') tags,
            t2.scoring_method, t2.calculated_risk, t2.CLASSIC_likelihood, t2.CLASSIC_impact, t2.CVSS_AccessVector, t2.CVSS_AccessComplexity, t2.CVSS_Authentication, t2.CVSS_ConfImpact, t2.CVSS_IntegImpact, t2.CVSS_AvailImpact, t2.CVSS_Exploitability, t2.CVSS_RemediationLevel, t2.CVSS_ReportConfidence, t2.CVSS_CollateralDamagePotential, t2.CVSS_TargetDistribution, t2.CVSS_ConfidentialityRequirement, t2.CVSS_IntegrityRequirement, t2.CVSS_AvailabilityRequirement, t2.DREAD_DamagePotential, t2.DREAD_Reproducibility, t2.DREAD_Exploitability, t2.DREAD_AffectedUsers, t2.DREAD_Discoverability, t2.OWASP_SkillLevel, t2.OWASP_Motive, t2.OWASP_Opportunity, t2.OWASP_Size, t2.OWASP_EaseOfDiscovery, t2.OWASP_EaseOfExploit, t2.OWASP_Awareness, t2.OWASP_IntrusionDetection, t2.OWASP_LossOfConfidentiality, t2.OWASP_LossOfIntegrity, t2.OWASP_LossOfAvailability, t2.OWASP_LossOfAccountability, t2.OWASP_FinancialDamage, t2.OWASP_ReputationDamage, t2.OWASP_NonCompliance, t2.OWASP_PrivacyViolation, t2.Custom, t2.Contributing_Likelihood, group_concat(distinct CONCAT_WS('_', rsci.contributing_risk_id, rsci.impact)) as Contributing_Risks_Impacts
        FROM `questionnaire_answers` t1
            LEFT JOIN `questionnaire_scoring` t2 ON t1.questionnaire_scoring_id=t2.id
            LEFT JOIN questionnaire_scoring_contributing_impacts rsci ON t2.scoring_method=6 AND t2.id=rsci.questionnaire_scoring_id
            LEFT JOIN `tags` t3 ON FIND_IN_SET(t3.id, t1.tag_ids)
        WHERE 
            t1.question_id = :question_id
        GROUP BY
            t1.id
        ORDER BY
            t1.ordering
        ;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
    $stmt->execute();
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    $question['answers'] = $answers;
    
    return $question;    
}

/******************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE QUESTION EDIT FORM *
 ******************************************************/
function display_questionnaire_question_edit($question_id){
    global $lang, $escaper;
    
    // Get questions
    list($subQuestionsCount, $sub_questions) = get_assessment_questionnaire_questions();
    
    $configs = get_assessment_settings();
    
    list($recordsTotal, $templates) = get_assessment_questionnaire_templates();

    
    foreach ($configs as $config ) {
         ${$config['name']} = $config['value'];
    }

    // Get a question and answers by question ID
    $question = get_questionnaire_question($question_id);
    
    $affected_assets_placeholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']);

    echo "
        <style>
            .risk-element{
                display: none;
            }
        </style>
    ";

    echo "
        <form id='questionnaire_edit_form' method='post' autocomplete='off'>
            <h4>".$escaper->escapeHtml($lang['EditQuestionnaireQuestion'])." <button type='submit' name='edit_questionnaire_question' class='btn pull-right'>". $escaper->escapeHtml($lang['Update']) ."</button></h4>
            <div class='clearfix'></div>
            <br>
            <div class='row-fluid'>
                <strong class='span2'>". $escaper->escapeHtml($lang['Question']) .":&nbsp; </strong>
                <div class='span10'><input placeholder='". $escaper->escapeHtml($lang['Question']) ."' type='text' name='question' required class='form-control' value='". $escaper->escapeHtml($question['question']) ."' style='max-width: none'></div>
            </div>
            <div class='row-fluid' style='padding-bottom: 10px;'>
                <strong class='span2'>". $escaper->escapeHtml($lang['ComplianceAudit']) .":&nbsp; </strong>
                <div class='span10'><input type='checkbox' id='compliance_audit' name='compliance_audit' ".(!empty($question['compliance_audit']) ? "checked" : "")." value='1'></div>
            </div>
            <div class='row-fluid' id='mapped-controls-holder' style='" . (empty($question['compliance_audit']) ? "display: none" : "") . "'>
                <strong class='span2'>". $escaper->escapeHtml($lang['MappedControls']) .":&nbsp; </strong>
                <div class='span10'>
                    ";
                        mitigation_controls_dropdown($question['mapped_controls']);
                    echo "
                </div>
            </div>
            <div class='row-fluid'>
                <strong class='span2'>". $escaper->escapeHtml($lang['HasFile']) .":&nbsp; </strong>
                <div class='span10'><input type='checkbox' id='has_file' name='has_file' ".($question['has_file'] ? "checked" : "")." value='1'></div>
            </div>
            ";
            echo "
            <div id='questionnaire-answers-container'>
            <br>
                <table class='answers-table' width='100%' caleespacing='0' cellpadding='0'>
                    <tbody>
            ";
                        
                foreach($question['answers'] as $key => $answer){
                    
                    if($answer['tags'])
                    {
                        $tags = array_map(function($id_name) use($escaper){
                            $id_name_arr = explode("---", $id_name);
                            return ['label' => $escaper->escapeHtml($id_name_arr[1]), 'value' => $id_name_arr[0]];
                        }, explode("+++", $answer['tags']) );
                        $tag_string = json_encode($tags);
                    }
                    else
                    {
                        $tag_string = "";
                    }
                    
                    
                    echo "
                    <tr>
                        <th width='10%'>".$escaper->escapeHtml($lang['Answer'])."</th>
                        <th width='10%'>".$escaper->escapeHtml($lang['FailControl'])."</th>
                        <th width='10%'>".$escaper->escapeHtml($lang['SubmitRisk'])."</th>
                        <th ><span class='risk-element' style=\" ". ($answer['submit_risk'] ? "display:block;" : "display:none;") ." \">".$escaper->escapeHtml($lang['Subject'])."</span></th>
                        <th width='10%'><span class='risk-element' style=\" ". ($answer['submit_risk'] ? "display:block;" : "display:none;") ." \">".$escaper->escapeHtml($lang['Owner'])."</span></th>
                        <th width='20%'><span class='risk-element' style=\" ". ($answer['submit_risk'] ? "display:block;" : "display:none;") ." \">".$escaper->escapeHtml($lang['AffectedAssets'])."</span></th>
                        <th width='50px'>&nbsp;</th>
                    </tr>
                    <tr>
                        <td>
                            <input type='hidden' name='answers[scoring_id][id_{$answer['id']}]' value='{$answer['questionnaire_scoring_id']}'>
                            <input type='text' placeholder='".$escaper->escapeHtml($lang['Answer'])."' value=\"". $escaper->escapeHtml($answer['answer']) ."\" required name='answers[answer][id_{$answer['id']}]' style='max-width: none' >
                        </td>
                        <td align='center'>
                            <input type=\"checkbox\" class=\"exist\" name=\"answers[fail_control][id_{$answer['id']}]\" value=\"id_{$answer['id']}\" ".($answer['fail_control'] ? "checked" : "")." />
                        </td>
                        <td align='center'>
                            <input type=\"checkbox\" class=\"exist submit-risk\" name=\"answers[submit_risk][id_{$answer['id']}]\" value=\"id_{$answer['id']}\" ".($answer['submit_risk'] ? "checked" : "")." />
                        </td>
                        <td align='center'>
                            <div class=\"risk-element\" style=\" ". ($answer['submit_risk'] ? "display:block;" : "display:none;") ." \" >
                                <input type=\"text\" name=\"answers[risk_subject][id_{$answer['id']}]\" size=\"200\" placeholder=\"Enter Risk Subject\" value=\"".$escaper->escapeHtml($answer['risk_subject'])."\" style='max-width: none' />
                            </div>
                        </td>
                        <td align='center'>
                            ".create_dropdown("enabled_users", $answer['risk_owner'], "answers[risk_owner][id_{$answer['id']}]", true, false, true, " class=\"risk-element\" style=\" ". ($answer['submit_risk'] ? "display:block;" : "display:none;") ." \"  ")."
                        </td>
                        <td>
                            <div class=\"ui-widget risk-element\" style='". ($answer['submit_risk'] ? "display:block;" : "display:none;") ."'>
                                <select class='assets-asset-groups-select' name='answers[assets_asset_groups][id_{$answer['id']}][]' multiple placeholder='{$affected_assets_placeholder}'>";

                                    $assets_asset_groups = get_assets_and_asset_groups_of_type($answer['id'], 'questionnaire_answer');
                                    if ($assets_asset_groups){
                                        foreach($assets_asset_groups as $item) {
                                            echo "<option value='{$item['id']}_{$item['class']}' selected " . ($item['verified'] ? "" : "data-unverified data-id='{$item['id']}' data-class='{$item['class']}' data-name='" . $escaper->escapeJS($item['name']) . "'") . ">" . $escaper->escapeHtml($item['name']) . "</option>";
                                        }
                                    }
                    echo "        
                                </select>
                            </div>
                        </td>
                        <td>".($key == 0 ? "&nbsp;" : "<a href='#' class='delete-row'><img class=\"add-delete-icon\" src=\"../images/minus.png\" width=\"15px\" height=\"15px\" /></a>")."</td>
                    </tr>
                    <tr class=\"risk-element\" style=\" ". ($answer['submit_risk'] ? "display:table-row;" : "display:none;") ." \" >
                        <td colspan='6'>
                            <table width='100%'>
                                <tr>
                                    <td  class='risk-scoring-container'>";
                                        $ContributingImpacts = get_contributing_impacts_by_subjectimpact_values($answer['Contributing_Risks_Impacts']);
                                        display_score_html_from_assessment("id_".$answer['id'], $answer['scoring_method'], $answer['CLASSIC_likelihood'], $answer['CLASSIC_impact'], $answer['CVSS_AccessVector'], $answer['CVSS_AccessComplexity'], $answer['CVSS_Authentication'], $answer['CVSS_ConfImpact'], $answer['CVSS_IntegImpact'], $answer['CVSS_AvailImpact'], $answer['CVSS_Exploitability'], $answer['CVSS_RemediationLevel'], $answer['CVSS_ReportConfidence'], $answer['CVSS_CollateralDamagePotential'], $answer['CVSS_TargetDistribution'], $answer['CVSS_ConfidentialityRequirement'], $answer['CVSS_IntegrityRequirement'], $answer['CVSS_AvailabilityRequirement'], $answer['DREAD_DamagePotential'], $answer['DREAD_Reproducibility'], $answer['DREAD_Exploitability'], $answer['DREAD_AffectedUsers'], $answer['DREAD_Discoverability'], $answer['OWASP_SkillLevel'], $answer['OWASP_Motive'], $answer['OWASP_Opportunity'], $answer['OWASP_Size'], $answer['OWASP_EaseOfDiscovery'], $answer['OWASP_EaseOfExploit'], $answer['OWASP_Awareness'], $answer['OWASP_IntrusionDetection'], $answer['OWASP_LossOfConfidentiality'], $answer['OWASP_LossOfIntegrity'], $answer['OWASP_LossOfAvailability'], $answer['OWASP_LossOfAccountability'], $answer['OWASP_FinancialDamage'], $answer['OWASP_ReputationDamage'], $answer['OWASP_NonCompliance'], $answer['OWASP_PrivacyViolation'], $answer['Custom'], $answer['Contributing_Likelihood'], $ContributingImpacts);
                                    echo "</td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class='row-fluid'>
                                            <div class='span5 text-right'>
                                                ". $escaper->escapeHtml($lang['Tags']) .":
                                            </div>
                                            <div class='span7'>
                                                <input type=\"text\" readonly class=\"tags\" name=\"answers[tags][id_{$answer['id']}]\" value=\"\" data-selectize-value='{$tag_string}'>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td >&nbsp;</td>
                        <td align='right'>".$escaper->escapeHtml($lang["SubTemplate"]).":</td>
                        <td colspan='4'>
                            <select class='template-by-answer' style='max-width: none;'>
                                <option value=''>--</option>";
                            foreach($templates as $template){
                                echo "<option value='".$template['question_ids']."'>".$escaper->escapeHtml($template['name'])."</option>";
                            }
                            echo "</select>
                        </td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td >&nbsp;</td>
                        <td align='right'>".$escaper->escapeHtml($lang["SubQuestions"]).":</td>
                        <td colspan='4'>
                            <select class=\"sub_questions\" name=\"answers[sub_questions][id_{$answer['id']}][]\" multiple=\"multiple\" style='max-width: none;'>";
                                $saved_sub_questions = $answer['sub_questions'] ? explode(",", $answer['sub_questions']) : [];
                                foreach($sub_questions as $sub_question){
                                    if($sub_question['id'] != $question_id){
                                        if(in_array($sub_question['id'], $saved_sub_questions))
                                        {
                                            echo "<option value='{$sub_question['id']}' selected>".$escaper->escapeHtml($sub_question['question'])."</option>";
                                        }
                                        else
                                        {
                                            echo "<option value='{$sub_question['id']}' >".$escaper->escapeHtml($sub_question['question'])."</option>";
                                        }
                                    }
                                }
                            echo "</select>
                        </td>
                        <td>&nbsp;</td>
                    </tr>
                    ";
                }
            echo "
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan='6'>&nbsp;</td>
                        <td><a href='#' class='add-row'><img class=\"add-delete-icon\" src=\"../images/plus.png\" width=\"15px\" height=\"15px\" /></a></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </form>
        <table class='hide' id='adding-row'>
            <tbody>
                <tr>
                    <th width='10%'>".$escaper->escapeHtml($lang['Answer'])."</th>
                    <th width='10%'>".$escaper->escapeHtml($lang['FailControl'])."</th>
                    <th width='10%'>".$escaper->escapeHtml($lang['SubmitRisk'])."</th>
                    <th ><span class='risk-element' >".$escaper->escapeHtml($lang['Subject'])."</span></th>
                    <th width='10%'><span class='risk-element' >".$escaper->escapeHtml($lang['Owner'])."</span></th>
                    <th width='20%'><span class='risk-element' >".$escaper->escapeHtml($lang['AffectedAssets'])."</span></th>
                    <th width='50px'>&nbsp;</th>
                </tr>
                    
                <tr>
                    <td>
                        <input type='text' placeholder='".$escaper->escapeHtml($lang['Answer'])."' value='' required name='answers[answer][]' style='max-width: none' >
                    </td>
                    <td align='center'>
                        <input type=\"checkbox\" class=\"exist\" name=\"answers[fail_control][]\" value=\"1\"  />
                    </td>
                    <td align='center'>
                        <input type=\"checkbox\" class=\"exist submit-risk\" name=\"answers[submit_risk][]\" value=\"1\"  />
                    </td>
                    <td align='center'>
                        <div class=\"risk-element\">
                            <input type=\"text\" name=\"answers[risk_subject][]\" size=\"200\" placeholder=\"Enter Risk Subject\" style='max-width: none' />
                        </div>
                        
                    </td>
                    <td align='center'>
                        ".create_dropdown("enabled_users", NULL, "answers[risk_owner][]", true, false, true, " class=\"risk-element\" ")."
                    </td>
                    <td>
                        <div class=\"ui-widget risk-element\">
                            <select class='assets-asset-groups-select assets-asset-groups-select-template' name='answers[assets_asset_groups][][]' multiple placeholder='{$affected_assets_placeholder}'></select>
                        </div>
                    </td>
                    <td><a href='#' class='delete-row'><img class=\"add-delete-icon\" src=\"../images/minus.png\" width=\"15px\" height=\"15px\" /></a></td>
                </tr>
                <tr class=\"risk-element\">
                    <td colspan='6'>
                        <table width='100%'>
                            <tr>
                                <td class='risk-scoring-container'>";
                                    display_score_html_from_assessment();
                                echo "</td>
                            </tr>
                            <tr>
                                <td>
                                    <div class='row-fluid'>
                                        <div class='span5 text-right'>
                                            ". $escaper->escapeHtml($lang['Tags']) .":
                                        </div>
                                        <div class='span7'>
                                            <input type=\"text\" readonly class=\"tags\" name=\"answers[tags][]\" value=\"\">
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td >&nbsp;</td>
                    <td align='right'>".$escaper->escapeHtml($lang["SubTemplate"]).":</td>
                    <td colspan='4'>
                        <select class='template-by-answer' style='max-width: none;'>
                            <option value=''>--</option>";
                        foreach($templates as $template){
                            echo "<option value='".$template['question_ids']."'>".$escaper->escapeHtml($template['name'])."</option>";
                        }
                        echo "</select>
                    </td>
                    <td>&nbsp;</td>
                </tr>
                <tr>
                    <td >&nbsp;</td>
                    <td align='right'>".$escaper->escapeHtml($lang["SubQuestions"]).":</td>
                    <td colspan='4'>
                        <select class=\"new_sub_questions\" name=\"answers[sub_questions][]\" multiple=\"multiple\" style='max-width: none;'>";
                            foreach($sub_questions as $sub_question){
                                if($sub_question['id'] != $question_id){
                                    echo "<option value='{$sub_question['id']}' >".$escaper->escapeHtml($sub_question['question'])."</option>";
                                }
                            }
                        echo "</select>
                        
                    </td>
                    <td>&nbsp;</td>
                </tr>
            </tbody>
        </table>
    ";
                                 
    echo "
        <script>
            function createTagsInstance(\$e)
            {
                \$e.selectize({
                    plugins: ['remove_button', 'restore_on_backspace'],
                    delimiter: '+++',
                    create: function (input){
                        return {value: 'new_tag_' + input, label:input};
                    },
                    valueField: 'value',
                    labelField: 'label',
                    searchField: 'label',
                    preload: true,
                    onInitialize: function() {
                        var json_string = this.\$input.attr('data-selectize-value');
                        if(!json_string)
                            return;
                        var existingOptions = JSON.parse(json_string);
                        var self = this;
                        if(Object.prototype.toString.call( existingOptions ) === \"[object Array]\") {
                            existingOptions.forEach( function (existingOption) {
                                self.addOption(existingOption);
                                self.addItem(existingOption[self.settings.valueField]);
                            });
                        }
                        else if (typeof existingOptions === 'object') {
                            self.addOption(existingOptions);
                            self.addItem(existingOptions[self.settings.valueField]);
                        }
                    },
                    load: function(query, callback) {
                        if (query.length) return callback();
                        $.ajax({
                            url: BASE_URL + '/api/management/tag_options_of_type?type=risk',
                            type: 'GET',
                            dataType: 'json',
                            error: function() {
                                console.log('Error loading!');
                                callback();
                            },
                            success: function(res) {
                                callback(res.data);
                            }
                        });
                    }
                });
            }
            createTagsInstance($('.tags', '#questionnaire-answers-container .answers-table'));
        </script>    
        <script>
            function set_index(name, parent){
                var key = 0;
                $('.exist[name=\"'+name+'\"]', parent).each(function(){
                    $(this).val(key);
                    key++;
                })
            }
            function set_new_sub_question_names(parent){
                var key = 0;
                $('.new_sub_questions', parent).each(function(){
                    $(this).attr('name', 'answers[sub_questions]['+key+'][]');
                    key++;
                })
            }

            function refresh_affected_assets_widget_names(){
                $('#questionnaire_edit_form select.assets-asset-groups-select-template').each(function(index, element){
                    $(element).attr('name', 'answers[assets_asset_groups][' + index + '][]');
                })
            }
            
            var assets_and_asset_groups = [];

            \$(document).ready(function(){
                \$('body').on('change', '.template-by-answer', function(){
                    var val = $(this).val();
                    if(val == '')
                        return;
                    
                    var valarray = val.split(',');
                    
                    var parent = $(this).closest('tr');
                    
                    $('.sub_questions, .new_sub_questions', parent.next('tr')).val(valarray);
                    $('.sub_questions, .new_sub_questions', parent.next('tr')).multiselect('refresh');
                });
                
                \$('.add-row').click(function(e){
                    e.preventDefault();
                    var appended_row= $($('#adding-row tbody').html()).appendTo('#questionnaire-answers-container > table.answers-table > tbody');
                    set_index('answers[submit_risk][]', '#questionnaire-answers-container');
                    set_index('answers[fail_control][]', '#questionnaire-answers-container');

                    refresh_affected_assets_widget_names();

                    var select = $('#questionnaire_edit_form select.assets-asset-groups-select').not('.selectized');
                    selectize_assessment_answer_affected_assets_widget(select, assets_and_asset_groups);

                    set_new_sub_question_names('#questionnaire-answers-container');
                    $('.new_sub_questions', appended_row).multiselect({
                        enableFiltering: true,
                        enableCaseInsensitiveFiltering: true,
                        buttonWidth: '100%',
                        numberDisplayed: 1,
                        filterPlaceholder: '".$escaper->escapeHtml($lang['SearchForQuestion'])."'
                    })
                    
                    createTagsInstance($('.tags', appended_row))
                })
                \$('body').on('click', '.submit-risk', function(){
                    if($(this).is(':checked')){
                        $(this).parents('tr').find('.risk-element').show();
                        $(this).parents('tr').next('.risk-element').show();
                        $(this).parents('tr').prev().find('.risk-element').show();
                    }else{
                        $(this).parents('tr').find('.risk-element').hide();
                        $(this).parents('tr').next('.risk-element').hide();
                        $(this).parents('tr').prev().find('.risk-element').hide();
                    }
                });
                \$('body').on('click', '.delete-row', function(e){
                    e.preventDefault();
                    \$(this).parents('tr').prev().remove();
                    \$(this).parents('tr').next().remove();
                    \$(this).parents('tr').next().remove();
                    \$(this).parents('tr').next().remove();
                    \$(this).parents('tr').remove();
                    set_index('answers[fail_control][]', '#questionnaire-answers-container');
                    set_index('answers[submit_risk][]', '#questionnaire-answers-container');
                    set_new_sub_question_names('#questionnaire-answers-container');
                    refresh_affected_assets_widget_names();
                })
                \$('.answers-table .sub_questions').multiselect({
                    enableFiltering: true,
                    enableCaseInsensitiveFiltering: true,
                    buttonWidth: '100%',
                    numberDisplayed: 1,
                    filterPlaceholder: '".$escaper->escapeHtml($lang['SearchForQuestion'])."'
                });
                \$('#compliance_audit').click(function(){
                    var compliance_audit = this.checked;
                    if(compliance_audit){
                        $('#mapped-controls-holder').show();
                    }else{
                        $('#mapped-controls-holder').hide();
                    }
                });

                $.ajax({
                    url: '/api/asset-group/options',
                    type: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        var data = res.data;
                        var len = data.length;
                        for (var i = 0; i < len; i++) {
                            var item = data[i];
                            item.id += '_' + item.class;

                            assets_and_asset_groups.push(item);
                        }

                        $('#questionnaire_edit_form select.assets-asset-groups-select').each(function() {

                            var combined_assets_and_asset_groups = assets_and_asset_groups;
                            // Have to add the unverified assets to the list of options,
                            // but only for THIS widget
                            $(this).find('option[data-unverified]').each(function() {

                                combined_assets_and_asset_groups.push({
                                    id: '' + $(this).data('id') + '_' + $(this).data('class'),
                                    name: $(this).data('name'),
                                    class: $(this).data('class')
                                });
                            });

                            selectize_assessment_answer_affected_assets_widget($(this), combined_assets_and_asset_groups);
                        });
                    }
                });
            })
        </script>
    ";
}

/*****************************************
 * FUNCTION: PROCESS ASSESSMENT CONTACTS *
 *****************************************/
function process_assessment_contact(){
    global $lang, $escaper;
    
    $process = false;
    
    // Check if new contact was sent
    if(isset($_POST['add_contact'])){
        $company  = $_POST['company'];
        $name     = $_POST['name'];
        $email    = $_POST['email'];
        $phone    = $_POST['phone'];
        $manager  = (int)$_POST['manager'];
        $details  = $_POST['details'];
        
        // If contact email no exists in table
        if(!check_exist_contact_email($email)){
            // Check if success to add a contact
            if(add_assessment_contact($company, $name, $email, $phone, $manager, $details)){
                set_alert(true, "good", $escaper->escapeHtml($lang['AssessmentContactCreated']));
            }else{
                set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidInformations']));
            }
        }
        // If contact email exists in table
        else
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['ContactEmailAlreadyInUse']));
        }
        $process = true;
    }
    // Check if a contact was edited
    elseif(isset($_POST['update_contact'])){
        $id = (int)$_GET['id'];
        $company  = $_POST['company'];
        $name     = $_POST['name'];
        $email    = $_POST['email'];
        $phone    = $_POST['phone'];
        $manager  = (int)$_POST['manager'];
        $details  = $_POST['details'];
        
        // If contact email no exists in table
        if(!check_exist_contact_email($email, $id)){
            // Check if success to update a contact
            if(update_assessment_contact($id, $company, $name, $email, $phone, $manager, $details)){
                set_alert(true, "good", $escaper->escapeHtml($lang['AssessmentContactUpdated']));
            }else{
                set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidInformations']));
            }
        }
        // If contact email exists in table
        else
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['ContactEmailAlreadyInUse']));
        }

        $process = true;
    }
    // Check if new contact was deleted
    elseif(isset($_POST['delete_contact'])){
        $contact_id = (int)$_POST['contact_id'];
        // Delete an assessment contact
        delete_assessment_contact($contact_id);
        set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));
        $process = true;
    }
    return $process;
}

/********************************************
 * FUNCTION: ADD QUESTIONNAIRE PENDING RISK *
 ********************************************/
function add_questionnaire_pending_risk($questionnaire_tracking_id, $questionnaire_scoring_id, $subject, $owner, $affected_assets, $comment, $tag_ids)
{
    // Open the database connection
    $db = db_open();

    // Get the assessment questions and answers
    $stmt = $db->prepare("INSERT INTO `questionnaire_pending_risks` (`questionnaire_tracking_id`, `questionnaire_scoring_id`, `subject`, `owner`, `affected_assets`, `comment`, `tag_ids`) VALUES (:questionnaire_tracking_id, :questionnaire_scoring_id, :subject, :owner, :affected_assets, :comment, :tag_ids);");
    $stmt->bindParam(":questionnaire_tracking_id", $questionnaire_tracking_id, PDO::PARAM_INT);
    $stmt->bindParam(":questionnaire_scoring_id", $questionnaire_scoring_id, PDO::PARAM_INT);
    $stmt->bindParam(":subject", $subject, PDO::PARAM_STR, 1000);
    $stmt->bindParam(":owner", $owner, PDO::PARAM_INT);
    $stmt->bindParam(":affected_assets", $affected_assets, PDO::PARAM_STR, 200);
    $stmt->bindParam(":comment", $comment, PDO::PARAM_STR, 500);
    $stmt->bindParam(":tag_ids", $tag_ids, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*************************************************
 * FUNCTION: GET PENDING RISK BY PENDING RISK ID *
 *************************************************/
function get_pending_risk_by_id($id)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            *
        FROM
            `questionnaire_pending_risks`
        WHERE
            `id` = :id;
    ");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    $pending_risk = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);

    return $pending_risk;
}

/***********************************************
 * FUNCTION: CREATE QUESTIONNAIRE PEDNING RISK *
 ***********************************************/
function create_risk_from_questionnaire_pending_risk($pending_risk_id, $submission_date, $subject, $owner, $notes, $assets_asset_groups, $tag_ids, $scoring)
{
    // Set the other risk values
    $status = "New";
    $reference_id = "";
    $regulation = 0;
    $control_number = "";
    $location = "";
    $source = 0;
    $category = 0;
    $team = "";
    $technology = "";
    $manager = 0;
    $assessment = "";
    
    // Submit the pending risk
    $last_insert_id = submit_risk($status, $subject, $reference_id, $regulation, $control_number, $location, $source, $category, $team, $technology, $owner, $manager, $assessment, $notes);
//    if($tag_ids){
//        $tagArr = explode(",", $tag_ids);
//        add_tagges($tagArr, $last_insert_id, "risk");
//    }
    
    $tag_ids = create_new_tag_from_string($tag_ids, "+++", "risk", $last_insert_id);

    // If the encryption extra is enabled, updates order_by_subject
    if (encryption_extra())
    {
        //Include the encryption extra
        require_once(realpath(__DIR__ . '/../encryption/index.php'));
        create_subject_order($_SESSION['encrypted_pass']);
    }
    
    $pending_risk = get_pending_risk_by_id($pending_risk_id);
    
    if(isset($scoring['scoring_method'][0]) && $scoring['scoring_method'][0]){
        // Get first element from POST data
        $key = 0;
        
        $scoring_method = $scoring['scoring_method'][$key];
        
        // Classic Risk Scoring Inputs
        $CLASSIClikelihood = $scoring['likelihood'][$key];
        $CLASSICimpact = $scoring['impact'][$key];
        
        // CVSS Risk Scoring Inputs
        $CVSSAccessVector = $scoring['AccessVector'][$key];
        $CVSSAccessComplexity = $scoring['AccessComplexity'][$key];
        $CVSSAuthentication = $scoring['Authentication'][$key];
        $CVSSConfImpact = $scoring['ConfImpact'][$key];
        $CVSSIntegImpact = $scoring['IntegImpact'][$key];
        $CVSSAvailImpact = $scoring['AvailImpact'][$key];
        $CVSSExploitability = $scoring['Exploitability'][$key];
        $CVSSRemediationLevel = $scoring['RemediationLevel'][$key];
        $CVSSReportConfidence = $scoring['ReportConfidence'][$key];
        $CVSSCollateralDamagePotential = $scoring['CollateralDamagePotential'][$key];
        $CVSSTargetDistribution = $scoring['TargetDistribution'][$key];
        $CVSSConfidentialityRequirement = $scoring['ConfidentialityRequirement'][$key];
        $CVSSIntegrityRequirement = $scoring['IntegrityRequirement'][$key];
        $CVSSAvailabilityRequirement = $scoring['AvailabilityRequirement'][$key];
        // DREAD Risk Scoring Inputs
        $DREADDamage = $scoring['DREADDamage'][$key];
        $DREADReproducibility = $scoring['DREADReproducibility'][$key];
        $DREADExploitability = $scoring['DREADExploitability'][$key];
        $DREADAffectedUsers = $scoring['DREADAffectedUsers'][$key];
        $DREADDiscoverability = $scoring['DREADDiscoverability'][$key];
        // OWASP Risk Scoring Inputs
        $OWASPSkillLevel = $scoring['OWASPSkillLevel'][$key];
        $OWASPMotive = $scoring['OWASPMotive'][$key];
        $OWASPOpportunity = $scoring['OWASPOpportunity'][$key];
        $OWASPSize = $scoring['OWASPSize'][$key];
        $OWASPEaseOfDiscovery = $scoring['OWASPEaseOfDiscovery'][$key];
        $OWASPEaseOfExploit = $scoring['OWASPEaseOfExploit'][$key];
        $OWASPAwareness = $scoring['OWASPAwareness'][$key];
        $OWASPIntrusionDetection = $scoring['OWASPIntrusionDetection'][$key];
        $OWASPLossOfConfidentiality = $scoring['OWASPLossOfConfidentiality'][$key];
        $OWASPLossOfIntegrity = $scoring['OWASPLossOfIntegrity'][$key];
        $OWASPLossOfAvailability = $scoring['OWASPLossOfAvailability'][$key];
        $OWASPLossOfAccountability = $scoring['OWASPLossOfAccountability'][$key];
        $OWASPFinancialDamage = $scoring['OWASPFinancialDamage'][$key];
        $OWASPReputationDamage = $scoring['OWASPReputationDamage'][$key];
        $OWASPNonCompliance = $scoring['OWASPNonCompliance'][$key];
        $OWASPPrivacyViolation = $scoring['OWASPPrivacyViolation'][$key];
        
        // Custom Risk Scoring
        $Custom = $scoring['Custom'][$key];
        
        // Contributing Risk Scoring
        $ContributingLikelihood = $scoring['ContributingLikelihood'][$key];
        $ContributingImpacts = empty($scoring['ContributingImpacts']) ? [] : get_contributing_impacts_by_key_from_multi($scoring['ContributingImpacts'], $key);
        
        // Submit risk scoring
        submit_risk_scoring($last_insert_id, $scoring_method, $CLASSIClikelihood, $CLASSICimpact, $CVSSAccessVector, $CVSSAccessComplexity, $CVSSAuthentication, $CVSSConfImpact, $CVSSIntegImpact, $CVSSAvailImpact, $CVSSExploitability, $CVSSRemediationLevel, $CVSSReportConfidence, $CVSSCollateralDamagePotential, $CVSSTargetDistribution, $CVSSConfidentialityRequirement, $CVSSIntegrityRequirement, $CVSSAvailabilityRequirement, $DREADDamage, $DREADReproducibility, $DREADExploitability, $DREADAffectedUsers, $DREADDiscoverability, $OWASPSkillLevel, $OWASPMotive, $OWASPOpportunity, $OWASPSize, $OWASPEaseOfDiscovery, $OWASPEaseOfExploit, $OWASPAwareness, $OWASPIntrusionDetection, $OWASPLossOfConfidentiality, $OWASPLossOfIntegrity, $OWASPLossOfAvailability, $OWASPLossOfAccountability, $OWASPFinancialDamage, $OWASPReputationDamage, $OWASPNonCompliance, $OWASPPrivacyViolation, $Custom, $ContributingLikelihood, $ContributingImpacts);

        
        
        $questionnaire_scoring = [
            'scoring_method' => $scoring_method,

            // Classic Risk Scoring Inputs
            'CLASSIClikelihood' => $CLASSIClikelihood,
            'CLASSICimpact' => $CLASSICimpact,

            // CVSS Risk Scoring Inputs
            'CVSSAccessVector' => $CVSSAccessVector,
            'CVSSAccessComplexity' => $CVSSAccessComplexity,
            'CVSSAuthentication' => $CVSSAuthentication,
            'CVSSConfImpact' => $CVSSConfImpact,
            'CVSSIntegImpact' => $CVSSIntegImpact,
            'CVSSAvailImpact' => $CVSSAvailImpact,
            'CVSSExploitability' => $CVSSExploitability,
            'CVSSRemediationLevel' => $CVSSRemediationLevel,
            'CVSSReportConfidence' => $CVSSReportConfidence,
            'CVSSCollateralDamagePotential' => $CVSSCollateralDamagePotential,
            'CVSSTargetDistribution' => $CVSSTargetDistribution,
            'CVSSConfidentialityRequirement' => $CVSSConfidentialityRequirement,
            'CVSSIntegrityRequirement' => $CVSSIntegrityRequirement,
            'CVSSAvailabilityRequirement' => $CVSSAvailabilityRequirement,

            // DREAD Risk Scoring Inputs
            'DREADDamage' => $DREADDamage,
            'DREADReproducibility' => $DREADReproducibility,
            'DREADExploitability' => $DREADExploitability,
            'DREADAffectedUsers' => $DREADAffectedUsers,
            'DREADDiscoverability' => $DREADDiscoverability,

            // OWASP Risk Scoring Inputs
            'OWASPSkillLevel' => $OWASPSkillLevel,
            'OWASPMotive' => $OWASPMotive,
            'OWASPOpportunity' => $OWASPOpportunity,
            'OWASPSize' => $OWASPSize,
            'OWASPEaseOfDiscovery' => $OWASPEaseOfDiscovery,
            'OWASPEaseOfExploit' => $OWASPEaseOfExploit,
            'OWASPAwareness' => $OWASPAwareness,
            'OWASPIntrusionDetection' => $OWASPIntrusionDetection,
            'OWASPLossOfConfidentiality' => $OWASPLossOfConfidentiality,
            'OWASPLossOfIntegrity' => $OWASPLossOfIntegrity,
            'OWASPLossOfAvailability' => $OWASPLossOfAvailability,
            'OWASPLossOfAccountability' => $OWASPLossOfAccountability,
            'OWASPFinancialDamage' => $OWASPFinancialDamage,
            'OWASPReputationDamage' => $OWASPReputationDamage,
            'OWASPNonCompliance' => $OWASPNonCompliance,
            'OWASPPrivacyViolation' => $OWASPPrivacyViolation,

            // Custom Risk Scoring
            'Custom' => $Custom,

            // Contributing Risk Scoring
            'ContributingLikelihood' => $ContributingLikelihood,
            'ContributingImpacts' => $ContributingImpacts,
        ];
        // Update questionnaire scoring for pending risk
        update_assessment_questionnaire_scoring($pending_risk['questionnaire_scoring_id'], $questionnaire_scoring);
        
    }else{
        submit_risk_scoring($last_insert_id);
    }

    // We're using the same function that's used for import as we're used the
    // same format in the pending_risks table's affected_assets field
    if ($assets_asset_groups)
        import_assets_asset_groups_for_type($last_insert_id, $assets_asset_groups, 'risk');

    // If a file was submitted
    if (!empty($_FILES))
    {
        // Upload any file that is submitted
        upload_file($last_insert_id, $_FILES['file'], 1);
    }

    // If the notification extra is enabled
    if (notification_extra())
    {
        // Include the team separation extra
        require_once(realpath(__DIR__ . '/../notification/index.php'));

        // Send the notification
        notify_new_risk($last_insert_id, $subject);
    }

    // There is an alert message
    $risk_id = (int)$last_insert_id + 1000;

    // Marked as added risk
    delete_questionnaire_pending_risk($pending_risk_id, 1);
    
    return $risk_id;    
}

/*********************************************
 * FUNCTION: PUSH QUESTIONNAIRE PENDING RISK *
 *********************************************/
function push_risk_from_questionnaire_pending_risk($postData)
{
    $subject = $postData['subject'];

    if (!$subject) {
        return false;
    }
    
    // Get the risk id to push
    $pending_risk_id    = (int)$postData['pending_risk_id'];

    // Get the posted risk values
    $submission_date    = $postData['submission_date'];

    $owner              = (int)$postData['owner'];
    $notes              = $postData['note'];
    $tag_ids            = $postData['tags'];
    $assets_asset_groups = isset($postData['assets_asset_groups']) ? implode(',', $postData['assets_asset_groups']) : "";
    
    $risk_id = create_risk_from_questionnaire_pending_risk($pending_risk_id, $submission_date, $subject, $owner, $notes, $assets_asset_groups, $tag_ids, $postData);

    return $risk_id;
}

/***********************************************
 * FUNCTION: DELETE QUESTIONNAIRE PENDING RISK *
 * status: 
 *         0: pending, 1: added, 2: rejected
 ***********************************************/
function delete_questionnaire_pending_risk($pending_risk_id, $status)
{
    // Open the database connection
    $db = db_open();

    // Delete the pending risk
    $stmt = $db->prepare("UPDATE `questionnaire_pending_risks` SET status=:status WHERE id=:pending_risk_id;");
    $stmt->bindParam(":pending_risk_id", $pending_risk_id, PDO::PARAM_INT);
    $stmt->bindParam(":status", $status, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/***********************************************
 * FUNCTION: DELETE QUESTIONNAIRE PENDING RISK *
 ***********************************************/
function delete_all_questionnaire_pending_risks($token)
{
    $tracking_info = get_questionnaire_tracking_by_token($token)  ;
  
    // Get the pending risks
    $risks = get_questionnaire_pending_risks($tracking_info['tracking_id']);
    $risk_ids = [];
        
    foreach($risks as $risk)
    {
        $risk_ids[] = $risk['id'];
    }
    
    $risk_ids_string = implode(",", $risk_ids);

    // Open the database connection
    $db = db_open();

    // Mark the pending risks as rejected
    $stmt = $db->prepare("UPDATE `questionnaire_pending_risks` SET status=2 WHERE FIND_IN_SET(id, :risk_ids_string);");
    $stmt->bindParam(":risk_ids_string", $risk_ids_string, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*************************************************
 * FUNCTION: PROCESS QUESTIONNAIRE PENDING RISKS *
 *************************************************/

function process_questionnaire_pending_risks(){
    global $lang, $escaper;
    
    $process = false;

    // Check if add risk
    if(isset($_POST['add'])) {
        if($risk_id = push_risk_from_questionnaire_pending_risk()){
            // Set the alert message
            set_alert(true, "good", "Risk ID " . $risk_id . " submitted successfully!");
            
            $tracking_id = (int) $_POST['tracking_id'];
            $message = _lang('PendingRiskAddAuditLog', array(
                'subject' => truncate_to($_POST['subject'], 50),
                'risk_id' => $risk_id,
                'user_name' => $_SESSION['user']
            ), false);

            write_log($tracking_id+1000, $_SESSION['uid'], $message, 'questionnaire_tracking');
        }
        $process = true;
    }
    // Check if delete risk
    elseif(isset($_POST['delete'])) {
        $pending_risk_id = (int)$_POST['pending_risk_id'];
        $tracking_id = (int) $_POST['tracking_id'];

        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("
            SELECT
                `subject`
            FROM
                `questionnaire_pending_risks`
            WHERE
                `id` = :pending_risk_id;
        ");
        $stmt->bindParam(":pending_risk_id", $pending_risk_id, PDO::PARAM_INT);
        $stmt->execute();
        $subject = $stmt->fetchColumn();

        // Close the database connection
        db_close($db);

        // Mark pending risk as rejected
        delete_questionnaire_pending_risk($pending_risk_id, 2);
        
        set_alert(true, "good", $escaper->escapeHtml($lang['PendingRiskDeleted']));

        $questionnaire_tracking_info = get_questionnaire_tracking_by_id($tracking_id);

        $message = _lang('PendingRiskDeleteAuditLog', array(
            'subject' => truncate_to($subject, 50),
            'questionnaire_name' => $questionnaire_tracking_info['questionnaire_name'],
            'contact_name' => $questionnaire_tracking_info['contact_name'],
            'date' => format_datetime($questionnaire_tracking_info['sent_at']),
            'user_name' => $_SESSION['user']
        ), false);

        write_log($tracking_id+1000, $_SESSION['uid'], $message, 'questionnaire_tracking');

        $process = true;
    }
    // Check if delete all pending risks
    elseif(isset($_POST['delete_all_pending_risks']))
    {
        $token = $_GET['token'];
        
        $questionnaire_tracking_info = get_questionnaire_tracking_by_token($token);
        
        delete_all_questionnaire_pending_risks($token);
        set_alert(true, "good", $escaper->escapeHtml($lang['AllPendingRisksDeleted']));

        $message = _lang('PendingRiskDeleteAllAuditLog', array(
            'questionnaire_name' => $questionnaire_tracking_info['questionnaire_name'],
            'contact_name' => $questionnaire_tracking_info['contact_name'],
            'date' => format_datetime($questionnaire_tracking_info['sent_at']),
            'user_name' => $_SESSION['user']
        ), false);

        write_log($tracking_id+1000, $_SESSION['uid'], $message, 'questionnaire_tracking');
        
        $process = true;
    }
    
    return $process;
}

/********************************************************
 * FUNCTION: PROCESS ASSESSMENT QUESTIONNAIRE QUESTIONS *
 ********************************************************/
function process_assessment_questionnaire_questions(){
    global $lang, $escaper;

    $process = false;
    
    // Check if add questionnaire question
    if(isset($_POST['add_questionnaire_question'])){
        $question_text = $_POST['question'];
        $answers = $_POST['answers'];
        $has_file = empty($_POST['has_file']) ? 0 : 1;
        $compliance_audit = empty($_POST['compliance_audit']) ? 0 : 1;
        $mapped_controls = empty($_POST['mitigation_controls']) ? [] : $_POST['mitigation_controls'];
        
        // Check if a question and at least an answer exists.
        if($question_text && !empty($answers['answer']) && count($answers['answer'])){
            
            $questionData = array(
                'question' => $question_text,
                'has_file' => $has_file,
                'compliance_audit' => $compliance_audit,
                'mapped_controls' => implode(",", $mapped_controls)
            );

            // Create a questionnaire scoring
            $assessment_scores = get_assessment_scores_array();
            
            add_questionnaire_question_answers($questionData, $answers, $assessment_scores);
            
            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidQuestionOrAnswers']));
        }
        $process = true;
    }
    // Check if edit questionnaire question
    elseif(isset($_POST['edit_questionnaire_question'])){
        $querstion_id    = (int)$_GET['id'];
        $question_text   = $_POST['question'];
        $compliance_audit= empty($_POST['compliance_audit']) ? 0 : 1;
        $has_file        = empty($_POST['has_file']) ? 0 : 1;
        $answers         = $_POST['answers'];
        $mapped_controls = empty($_POST['mitigation_controls']) ? [] : $_POST['mitigation_controls'];
        
        // Check if a question and at least an answer exists.
        if($question_text && !empty($answers['answer']) && count($answers['answer'])){
            // Update questionnaire question and answers
            $questionData = array(
                'question' => $question_text,
                'has_file' => $has_file,
                'compliance_audit' => $compliance_audit,
                'mapped_controls' => implode(",", $mapped_controls),
            );
            
            // Create a questionnaire scoring
            $assessment_scores = get_assessment_scores_array();
            
            update_questionnaire_question_answers($querstion_id, $questionData, $answers, $assessment_scores);
            
            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidQuestionOrAnswers']));
        }
        $process = true;
    }
    // Check if delete a questionnaire question
    elseif(isset($_POST['delete_questionnaire_question'])){
        // Check if a question and at least an answer exists.
        if($question_id = (int)$_POST['question_id']){
            delete_questionnaire_question($question_id);
            set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidInformations']));
        }
        $process = true;
    }
    
    return $process;
}

/***************************************
 * FUNCTION: PROCESS ASSESSMENT IMPORT *
 ***************************************/
function process_assessment_import(){
    global $lang, $escaper;
    
    $process = false;
    
    // If a file for assessment contact has been imported
    if(isset($_POST['mapping_assessment_contacts']))
    {
        mapping_assessment_contacts();

        // Display an alert
        set_alert(true, "good", $escaper->escapeHtml($lang['AssessmentContactsImported']));
        
        $process = true;
    }
    // If a file for assessment questionnaires has been imported
    elseif(isset($_POST['mapping_assessment_questionnaire_questions'])){
        mapping_assessment_questionnaire_questions();
        
        // Display an alert
        set_alert(true, "good", $escaper->escapeHtml($lang['AssessmentQuestionnaireQuestionsAndAnwersImported']));
        
        $process = true;
    }
    // If a file for questionnaire template has been imported
    elseif(isset($_POST['mapping_questionnaire_templates'])){
        mapping_questionnaire_templates();
        
        // Display an alert
        set_alert(true, "good", $escaper->escapeHtml($lang['QuestionnaireTemplatesImported']));
        
        $process = true;
    }
    // If export questionnaire template
    elseif(isset($_POST['questionnaire_template_export'])){
        export_questionnaire_csv("questionnaire_template");
        
        // Display an alert
        set_alert(true, "good", $escaper->escapeHtml($lang['QuestionnaireTemplatesImported']));
        
        $process = true;
    }
    // If export questionnaire template
    elseif(isset($_POST['assessments_export'])){
        export_questionnaire_csv("assessments");
        
        // Display an alert
        set_alert(true, "good", $escaper->escapeHtml($lang['QuestionnaireTemplatesImported']));
        
        $process = true;
    }
    
    return $process;
}

/*************************************************************
 * FUNCTION: GET ARRAY FOR EXPORTING QUESTIONNAIRE TEMPLATES *
 *************************************************************/
function get_questionnaire_templates_array()
{
    // Open the database connection
    $db = db_open();

    // Get the assessment questions and answers
    $sql = "
        SELECT t1.name template, t3.question, t2.ordering
        FROM questionnaire_templates t1 
            LEFT JOIN questionnaire_template_question t2 ON t1.id=t2.questionnaire_template_id
            LEFT JOIN questionnaire_questions t3 ON t2.questionnaire_question_id=t3.id
        ORDER BY
            t1.id, t2.ordering
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);
    
    $results = [];
    
    // Create formatted array
    foreach($array as $row){
        $results[] = array(
            $row['template'],
            $row['question'],
            $row['ordering'],
        );
    }
    
    return $results;
}


/***********************************
 * FUNCTION: GET ASSESSMENTS ARRAY *
 ***********************************/
function get_assessments_array($template_id){
    if($template_id){
        $where = " t3.id=:template_id ";
    }
    else{
        $where = " 1 ";
    }

    // Query the database
    $db = db_open();
    
    // Get questions
    $query = "
        SELECT 
            t4.*, t1.id question_id, t1.question, t1.has_file, t2.ordering as question_ordering, t3.name as template_name, t5.name as owner_name,
            t7.name AS scoring_method, t6.calculated_risk, t8.name AS CLASSIC_likelihood, t9.name AS CLASSIC_impact, t6.CVSS_AccessVector, t6.CVSS_AccessComplexity, t6.CVSS_Authentication, t6.CVSS_ConfImpact, t6.CVSS_IntegImpact, t6.CVSS_AvailImpact, t6.CVSS_Exploitability, t6.CVSS_RemediationLevel, t6.CVSS_ReportConfidence, t6.CVSS_CollateralDamagePotential, t6.CVSS_TargetDistribution, t6.CVSS_ConfidentialityRequirement, t6.CVSS_IntegrityRequirement, t6.CVSS_AvailabilityRequirement, t6.DREAD_DamagePotential, t6.DREAD_Reproducibility, t6.DREAD_Exploitability, t6.DREAD_AffectedUsers, t6.DREAD_Discoverability, t6.OWASP_SkillLevel, t6.OWASP_Motive, t6.OWASP_Opportunity, t6.OWASP_Size, t6.OWASP_EaseOfDiscovery, t6.OWASP_EaseOfExploit, t6.OWASP_Awareness, t6.OWASP_IntrusionDetection, t6.OWASP_LossOfConfidentiality, t6.OWASP_LossOfIntegrity, t6.OWASP_LossOfAvailability, t6.OWASP_LossOfAccountability, t6.OWASP_FinancialDamage, t6.OWASP_ReputationDamage, t6.OWASP_NonCompliance, t6.OWASP_PrivacyViolation, t6.Custom, cl.name Contributing_Likelihood_name, group_concat(distinct CONCAT_WS('_', cr.subject, ci.name)) as Contributing_Risksubjects_Impactnames
        FROM 
            `questionnaire_questions` t1
            LEFT JOIN `questionnaire_template_question` t2 ON t1.id=t2.questionnaire_question_id
            LEFT JOIN `questionnaire_templates` t3 ON t2.questionnaire_template_id=t3.id
            LEFT JOIN `questionnaire_answers` t4 ON t1.id=t4.question_id
            LEFT JOIN `user` t5 ON t4.risk_owner=t5.value
            LEFT JOIN `questionnaire_scoring` t6 ON t4.questionnaire_scoring_id=t6.id
            LEFT JOIN `scoring_methods` t7 ON t6.scoring_method=t7.value
            LEFT JOIN `likelihood` t8 ON t6.CLASSIC_likelihood=t8.value
            LEFT JOIN `impact` t9 ON t6.CLASSIC_impact=t9.value
            
            LEFT JOIN questionnaire_scoring_contributing_impacts rsci ON t6.scoring_method=6 AND t6.id=rsci.questionnaire_scoring_id
            LEFT JOIN contributing_risks cr ON rsci.contributing_risk_id=cr.id
            LEFT JOIN impact ci ON rsci.impact=ci.value
            LEFT JOIN likelihood cl ON t6.Contributing_Likelihood=cl.value
        WHERE
            ". $where ."
        GROUP BY
            t3.id, t1.id, t4.id
        ORDER BY 
            t3.id, t1.id, t4.id";

    $stmt = $db->prepare($query);
    if($template_id){
        $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    db_close($db);

    // Questions
    $assessments1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sub_question_ids = [];
    $question_ids = [];
    
    // For each row
    foreach ($assessments1 as $key => $row)
    {
        $question_ids[] = $row['question_id'];
        $sub_question_ids = array_merge($sub_question_ids, explode(",", $row['sub_questions'])) ;
    }
    $question_ids = array_unique($question_ids);
    $sub_question_ids = array_unique($sub_question_ids);
    
    // Get sub question IDs that weren't pulled
    $new_sub_question_ids = [];
    foreach($sub_question_ids as $sub_question_id){
        if(!in_array($sub_question_id, $question_ids)){
            $new_sub_question_ids[] = $sub_question_id;
        }
    }
    // Get sub questions
    $query = "
        SELECT 
            t4.*, '' as sub_questions, t1.id question_id, t1.question, t1.has_file, 0 as question_ordering, '' as template_name, t5.name as owner_name,
            t7.name AS scoring_method, t6.calculated_risk, t8.name AS CLASSIC_likelihood, t9.name AS CLASSIC_impact, t6.CVSS_AccessVector, t6.CVSS_AccessComplexity, t6.CVSS_Authentication, t6.CVSS_ConfImpact, t6.CVSS_IntegImpact, t6.CVSS_AvailImpact, t6.CVSS_Exploitability, t6.CVSS_RemediationLevel, t6.CVSS_ReportConfidence, t6.CVSS_CollateralDamagePotential, t6.CVSS_TargetDistribution, t6.CVSS_ConfidentialityRequirement, t6.CVSS_IntegrityRequirement, t6.CVSS_AvailabilityRequirement, t6.DREAD_DamagePotential, t6.DREAD_Reproducibility, t6.DREAD_Exploitability, t6.DREAD_AffectedUsers, t6.DREAD_Discoverability, t6.OWASP_SkillLevel, t6.OWASP_Motive, t6.OWASP_Opportunity, t6.OWASP_Size, t6.OWASP_EaseOfDiscovery, t6.OWASP_EaseOfExploit, t6.OWASP_Awareness, t6.OWASP_IntrusionDetection, t6.OWASP_LossOfConfidentiality, t6.OWASP_LossOfIntegrity, t6.OWASP_LossOfAvailability, t6.OWASP_LossOfAccountability, t6.OWASP_FinancialDamage, t6.OWASP_ReputationDamage, t6.OWASP_NonCompliance, t6.OWASP_PrivacyViolation, t6.Custom, cl.name Contributing_Likelihood_name, group_concat(distinct CONCAT_WS('_', cr.subject, ci.name)) as Contributing_Risksubjects_Impactnames
        FROM 
            `questionnaire_questions` t1
            LEFT JOIN `questionnaire_answers` t4 ON t1.id=t4.question_id
            LEFT JOIN `user` t5 ON t4.risk_owner=t5.value
            LEFT JOIN `questionnaire_scoring` t6 ON t4.questionnaire_scoring_id=t6.id
            LEFT JOIN `scoring_methods` t7 ON t6.scoring_method=t7.value
            LEFT JOIN `likelihood` t8 ON t6.CLASSIC_likelihood=t8.value
            LEFT JOIN `impact` t9 ON t6.CLASSIC_impact=t9.value

            LEFT JOIN questionnaire_scoring_contributing_impacts rsci ON t6.scoring_method=6 AND t6.id=rsci.questionnaire_scoring_id
            LEFT JOIN contributing_risks cr ON rsci.contributing_risk_id=cr.id
            LEFT JOIN impact ci ON rsci.impact=ci.value
            LEFT JOIN likelihood cl ON t6.Contributing_Likelihood=cl.value
        WHERE
            FIND_IN_SET(t1.id, :new_sub_question_ids)
        GROUP BY
            t1.id, t4.id
        ORDER BY 
            t1.id, t4.id;";

    $new_sub_question__ids = implode(",", $new_sub_question_ids);
    $stmt = $db->prepare($query);
    $stmt->bindParam(":new_sub_question_ids", $new_sub_question__ids, PDO::PARAM_STR);
    $stmt->execute();
    
    // Store assessments for sub question IDs
    $assessments2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $assessments = array_merge($assessments1, $assessments2);

    $array = [];
    foreach ($assessments as $key => $row) {
        
        $affected_assets = implode(',', array_map(function($item) {
                return $item['class'] == 'asset' ? $item['name'] : "[{$item['name']}]";
            }, get_assets_and_asset_groups_of_type($row['id'], 'questionnaire_answer')));

        $array[] = array(
            $row['template_name'],
            $row['question_id'],
            $row['question'],
            $row['has_file'],
            $row['question_ordering'],
            $row['answer'],
            $row['submit_risk'],
            $row['risk_subject'],
            $row['owner_name'],
            $affected_assets,
            $row['sub_questions'],
            $row['scoring_method'],
            $row['calculated_risk'],
            $row['CLASSIC_likelihood'],
            $row['CLASSIC_impact'],
            $row['CVSS_AccessVector'] , 
            $row['CVSS_AccessComplexity'] , 
            $row['CVSS_Authentication'] , 
            $row['CVSS_ConfImpact'] , 
            $row['CVSS_IntegImpact'] , 
            $row['CVSS_AvailImpact'] , 
            $row['CVSS_Exploitability'] , 
            $row['CVSS_RemediationLevel'] , 
            $row['CVSS_ReportConfidence'] , 
            $row['CVSS_CollateralDamagePotential'] , 
            $row['CVSS_TargetDistribution'] , 
            $row['CVSS_ConfidentialityRequirement'] , 
            $row['CVSS_IntegrityRequirement'] , 
            $row['CVSS_AvailabilityRequirement'] , 
            $row['DREAD_DamagePotential'] , 
            $row['DREAD_Reproducibility'] , 
            $row['DREAD_Exploitability'] , 
            $row['DREAD_AffectedUsers'] , 
            $row['DREAD_Discoverability'] , 
            $row['OWASP_SkillLevel'] , 
            $row['OWASP_Motive'] , 
            $row['OWASP_Opportunity'] , 
            $row['OWASP_Size'] , 
            $row['OWASP_EaseOfDiscovery'] , 
            $row['OWASP_EaseOfExploit'] , 
            $row['OWASP_Awareness'] , 
            $row['OWASP_IntrusionDetection'] , 
            $row['OWASP_LossOfConfidentiality'] , 
            $row['OWASP_LossOfIntegrity'] , 
            $row['OWASP_LossOfAvailability'] , 
            $row['OWASP_LossOfAccountability'] , 
            $row['OWASP_FinancialDamage'] , 
            $row['OWASP_ReputationDamage'] , 
            $row['OWASP_NonCompliance'] , 
            $row['OWASP_PrivacyViolation'] , 
            $row['Custom'] , 
            $row['Contributing_Likelihood_name'] , 
            $row['Contributing_Risksubjects_Impactnames'] , 
        );
    }

    db_close($db);

    // Return the risks array
    return $array;
}

/**************************************
 * FUNCTION: EXPORT QUESTIONNAIRE CSV *
 **************************************/
function export_questionnaire_csv($type)
{
    // Include the language file
    require_once(language_file());

    global $lang;
    global $escaper;

    switch ($type)
    {
        // Combine risks, mitigations, and reviews
        case "questionnaire_template":
            $header = array($lang['Template'], $lang['Question'], $lang['Ordering']);
            $risks = get_questionnaire_templates_array();
            $filename = "simplerisk_questionnaire_template_export.csv";
            break;
        case "assessments":
            $header = array($lang['QuestionnaireTemplateName'], $lang['QuestionID'], $lang['Question'], $lang['HasFile'], $lang['QuestionOrdering'], $lang['Answer'], $lang['SubmitRisk'], $lang['Subject'], $lang['Owner'], $lang['AffectedAssets'], $lang['SubQuestions'], $lang['RiskScoringMethod'], $lang['CalculatedRisk'], 'Classic-'.$lang['Likelihood'], 'Classic-'.$lang['Impact'], 'CVSS-'.$lang['AttackVector'], 'CVSS-'.$lang['AttackComplexity'], 'CVSS-'.$lang['Authentication'], 'CVSS-'.$lang['ConfidentialityImpact'], 'CVSS-'.$lang['IntegrityImpact'], 'CVSS-'.$lang['AvailabilityImpact'], 'CVSS-'.$lang['Exploitability'], 'CVSS-'.$lang['RemediationLevel'], 'CVSS-'.$lang['ReportConfidence'], 'CVSS-'.$lang['CollateralDamagePotential'], 'CVSS-'.$lang['TargetDistribution'], 'CVSS-'.$lang['ConfidentialityRequirement'], 'CVSS-'.$lang['IntegrityRequirement'], 'CVSS-'.$lang['AvailabilityRequirement'], 'DREAD-'.$lang['DamagePotential'], 'DREAD-'.$lang['Reproducibility'], 'DREAD-'.$lang['Exploitability'], 'DREAD-'.$lang['AffectedUsers'], 'DREAD-'.$lang['Discoverability'], 'OWASP-'.$lang['SkillLevel'], 'OWASP-'.$lang['Motive'], 'OWASP-'.$lang['Opportunity'], 'OWASP-'.$lang['Size'], 'OWASP-'.$lang['EaseOfDiscovery'], 'OWASP-'.$lang['EaseOfExploit'], 'OWASP-'.$lang['Awareness'], 'OWASP-'.$lang['IntrusionDetection'], 'OWASP-'.$lang['LossOfConfidentiality'], 'OWASP-'.$lang['LossOfIntegrity'], 'OWASP-'.$lang['LossOfAvailability'], 'OWASP-'.$lang['LossOfAccountability'], 'OWASP-'.$lang['FinancialDamage'], 'OWASP-'.$lang['ReputationDamage'], 'OWASP-'.$lang['NonCompliance'], 'OWASP-'.$lang['PrivacyViolation'], $lang['CustomValue'], $lang['ContributingLikelihood'], $lang['ContributingSubjectsImpacts']);
            
            // Assessment to be exported
            $template_id = $_POST['assessment'];
            
            $risks = get_assessments_array($template_id);
            $filename = "simplerisk_assessments_export.csv";
            break;
    }

    // Tell the browser it's going to be a CSV file
    header('Content-Type: application/csv; charset=UTF-8');

    // Tell the browser we want to save it instead of displaying it
    header('Content-Disposition: attachement; filename="' . $escaper->escapeUrl($filename) . '";');
    // Open memory as a file so no temp file needed
    $f = fopen('php://output', 'w');


    fputcsv($f, $header);
    foreach ($risks as $risk)
    {
        fputcsv($f, $risk);
    }

    // Close the file
    fclose($f);

    // Exit so that page content is not included in the results
    exit(0);
    
}

/********************************************************
 * FUNCTION: PROCESS ASSESSMENT QUESTIONNAIRE TEMPLATES *
 ********************************************************/
function process_assessment_questionnaire_templates(){
    global $lang, $escaper;

    $process = false;
    
    // Check if add questionnaire template
    if(isset($_POST['add_questionnaire_template'])){
        $name               = $_POST['name'];
        $template_questions = isset($_POST['template_questions']) ? $_POST['template_questions'] : array();
        $orderings     = empty($_POST['ordering']) ? [] : $_POST['ordering'];
        // Check if a name exists.
        if($name){
            add_questionnaire_template($name, $template_questions, $orderings);
            
            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['TemplateNameRequired']));
        }
        $process = true;
    }
    // Check if edit questionnaire question
    elseif(isset($_POST['edit_questionnaire_template'])){
        $template_id   = (int)$_GET['id'];
        $name          = $_POST['name'];
        $questions     = empty($_POST['template_questions']) ? [] : $_POST['template_questions'];
        $orderings     = empty($_POST['ordering']) ? [] : $_POST['ordering'];
        
        
        // Check if template name exists.
        if($name){
            // Update questionnaire template
            update_questionnaire_template($template_id, $name, $questions, $orderings);
            
            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['TemplateNameRequired']));
        }
        $process = true;
    }
    // Check if delete a questionnaire template
    elseif(isset($_POST['delete_questionnaire_template'])){
        // Check if a question and at least an answer exists.
        if($template_id = (int)$_POST['template_id']){
            delete_questionnaire_template($template_id);
            set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidInformations']));
        }
        $process = true;
    }
    
    return $process;
}

/***************************************
 * FUNCTION: ADD QUESTINNAIRE TEMPLATE *
 ***************************************/
function add_questionnaire_template($name, $question_ids=[], $orderings=[]){
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT INTO `questionnaire_templates` (`name`) VALUES (:name);");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    
    // Create a template
    $stmt->execute();
    
    // Get the template id of the last insert
    $template_id = $db->lastInsertId();
    
    foreach($question_ids as $question_id){
        $question_id = (int)$question_id;
//        $ordering = empty($orderings[$question_id])? 0 : $orderings[$question_id];
        $ordering = array_search($question_id, array_keys($orderings));

        // Query the database
        $stmt = $db->prepare("INSERT INTO `questionnaire_template_question` (`questionnaire_template_id`, `questionnaire_question_id`, `ordering`) VALUES (:questionnaire_template_id, :questionnaire_question_id, :ordering);");
        $stmt->bindParam(":questionnaire_template_id", $template_id, PDO::PARAM_INT);
        $stmt->bindParam(":questionnaire_question_id", $question_id, PDO::PARAM_INT);
        $stmt->bindParam(":ordering", $ordering, PDO::PARAM_INT);
        
        // Create a relation
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);

    $message = "A questionnaire template named \"{$name}\" was added by username \"" . $_SESSION['user']."\".";
    write_log($template_id+1000, $_SESSION['uid'], $message, 'questionnaire_template');

    // Return the template id
    return $template_id;
}

/******************************************
 * FUNCTION: UPDATE QUESTINNAIRE TEMPLATE *
 ******************************************/
function update_questionnaire_template($template_id, $name, $question_ids=[], $orderings=[]){
    
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("UPDATE `questionnaire_templates` SET `name`=:name WHERE id=:template_id;");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
    
    // Update a template
    $stmt->execute();
    
    // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaire_template_question` WHERE questionnaire_template_id=:template_id;");
    $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
    
    // Delete all relations with template ID
    $stmt->execute();

    foreach($question_ids as $question_id){
        $question_id = (int)$question_id;
//        $ordering = empty($orderings[$question_id])? 0 : $orderings[$question_id];
        $ordering = array_search($question_id, array_keys($orderings));
        // Query the database
        $stmt = $db->prepare("INSERT INTO `questionnaire_template_question` (`questionnaire_template_id`, `questionnaire_question_id`, `ordering`) VALUES (:questionnaire_template_id, :questionnaire_question_id, :ordering);");
        $stmt->bindParam(":questionnaire_template_id", $template_id, PDO::PARAM_INT);
        $stmt->bindParam(":questionnaire_question_id", $question_id, PDO::PARAM_INT);
        $stmt->bindParam(":ordering", $ordering, PDO::PARAM_INT);
        
        // Create a relation
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);

    $message = "A questionnaire template named \"{$name}\" was updated by username \"" . $_SESSION['user']."\".";
    write_log($template_id+1000, $_SESSION['uid'], $message, 'questionnaire_template');
    
    // Return 
    return true;
}

/******************************************
 * FUNCTION: DELETE QUESTINNAIRE TEMPLATE *
 ******************************************/
function delete_questionnaire_template($id){
    $id = (int)$id;
    
    $template = get_assessment_questionnaire_template_by_id($id);
    
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaire_template_question` WHERE questionnaire_template_id=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    
    // Delete a questionnaire template questions by template ID
    $stmt->execute();

    // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaire_templates` WHERE id=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    
    // Delete a questionnaire template
    $stmt->execute();
    
    // Delete questionnaire and template relations by template ID
    $stmt = $db->prepare("DELETE FROM `questionnaire_id_template` WHERE `template_id`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();  
    
    // Close the database connection
    db_close($db);

    $message = "A questionnaire template named \"{$template['name']}\" was deleted by username \"" . $_SESSION['user']."\".";
    write_log($id+1000, $_SESSION['uid'], $message, 'questionnaire_template');
}

/********************************************
 * FUNCTION: DISPLAY QUESTINNAIRE TEMPLATES *
 ********************************************/
function display_questionnaire_templates(){
    global $lang;
    global $escaper;

    $tableID = "assessment-questionnaire-templates-table";
    
    echo "
        <table class=\"table risk-datatable assessment-datatable table-bordered table-striped table-condensed  \" width=\"100%\" id=\"{$tableID}\" >
            <thead>
                <tr >
                    <th>". $escaper->escapeHtml($lang['QuestionnaireTemplates']) ."</th>
                    <th width='105px'>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <script>
            var pageLength = 10;
            var form = $('#{$tableID}').parents('form');
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: false,
                pagingType: 'full_numbers',
                dom : 'flrtip',
                pageLength: pageLength,
                dom : 'flrti<\"#view-all.view-all\">p',
                ajax: {
                    url: BASE_URL + '/api/assessment/questionnaire/template/dynamic',
                    data: function(d){
                    },
                    complete: function(response){
                    }
                }
            });
            
            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })
            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw()
                $(this).addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw()
                }
            })
            
            $(\"body\").on(\"click\", \".copy-btn\", function(){
                var template_id = $(this).data('id');
                $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/assessment/template/copy',
                    data: {
                        id: template_id
                    },
                    success: function(data){
                        datatableInstance.draw();
                    }
                }).fail(function(xhr, textStatus){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }

                });

            })            
            
        </script>
    ";
    

    // MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
    echo "
        <div id=\"aseessment-questionnaire-template--delete\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
          <div class=\"modal-body\">

            <form class=\"\" action=\"\" method=\"post\">
              <div class=\"form-group text-center\">
                <label for=\"\">".$escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisTemplate'])."</label>
              </div>

              <input type=\"hidden\" name=\"template_id\" value=\"\" />
              <div class=\"form-group text-center \">
                <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                <button type=\"submit\" name=\"delete_questionnaire_template\" class=\"delete_control btn btn-danger\">".$escaper->escapeHtml($lang['Yes'])."</button>
              </div>
            </form>
          </div>
        </div>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.delete-btn', function(){
                \$('#aseessment-questionnaire-template--delete [name=template_id]').val(\$(this).data('id'));
            })
        </script>
    ";
}


/*********************************************
 * FUNCTION: COPY QUESTIONNAIRE TEMPLATE API *
 *********************************************/
function copyTemplateAPI()
{
    global $escaper, $lang;

    $template_id = (int)$_POST['id'];

    // Get questionnaire template by ID
    $template = get_assessment_questionnaire_template_by_id($template_id);

    $question_ids = [];
    $orderings = [];
    foreach($template['questions'] as $question){
        $question_ids[] = $question['question_id'];
        $orderings[$question['question_id']] = $question['ordering'];
    }
    
    sort($orderings);

    add_questionnaire_template($template['name']." (Copy)", $question_ids, $orderings);

    $message = "A questionnaire template named \"{$template['name']}\" was copied by username \"" . $_SESSION['user']."\".";
    write_log($template_id+1000, $_SESSION['uid'], $message, 'questionnaire_template');

    $result = ['status' => true];
    json_response(200, "Copy Questionnaire Tempalted", $result);
}

/***************************************************
 * FUNCTION: GET ASSESSMENT QUESTINNAIRE TEMPLATES *
 ***************************************************/
function get_assessment_questionnaire_templates($start=0, $length=-1){
    // Open the database connection
    $db = db_open();
    
    /*** Get questionnaire templates by $start and $lengh ***/
    $sql = "
        SELECT SQL_CALC_FOUND_ROWS t1.id, t1.name, GROUP_CONCAT(DISTINCT t2.questionnaire_question_id) question_ids
        FROM `questionnaire_templates` t1
            LEFT JOIN  questionnaire_template_question t2 ON t1.id=t2.questionnaire_template_id
        GROUP By
            t1.id
        ORDER BY t1.name
    ";
    if($length != -1){
        $sql .= " LIMIT {$start}, {$length}; ";
    }
    
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $recordsTotal = $stmt->fetchColumn();
    
    // Close the database connection
    db_close($db);
    
    return array($recordsTotal, $templates);
}

/****************************************************
 * FUNCTION: DISPLAY QUESTINNAIRE TEMPLATE ADD FORM *
 ****************************************************/
function display_questionnaire_template_add(){
    global $lang, $escaper;
    
    // Get questionnaire questions
    list($recordsTotal, $questions) = get_assessment_questionnaire_questions();

    echo "<form method='post' class='risk-scoring-container' autocomplete='off'>";
    echo "
            <h4>".$escaper->escapeHtml($lang['NewQuestionnaireTemplate'])." <button type='submit' name='add_questionnaire_template' class='btn pull-right'>". $escaper->escapeHtml($lang['Add']) ."</button></h4>
            <div class='clearfix'></div>
            <br>
            <div class='row-fluid'>
                <strong class='span1'>". $escaper->escapeHtml($lang['Name']) .":&nbsp; </strong>
                <div class='span11'><input placeholder='". $escaper->escapeHtml($lang['QuestionnaireTemplateName']) ."' type='text' name='name' required class='form-control' style='max-width: none'></div>
            </div>
            <div class='row-fluid'>
                <strong class='span1'>". $escaper->escapeHtml($lang['Questions']) .":&nbsp; </strong>
                <div class='span11'>
                    <select id=\"template_questions\" name=\"template_questions[]\" multiple=\"multiple\" style='max-width: none;'>";
                    foreach($questions as $question){
                        echo "<option value='".$question['id']."'>".$escaper->escapeHtml($question['question'])."</option>";
                    }
                echo "</select>
                </div>
            </div>
    ";
    display_questionnaire_template_questions_datatable();
    echo "
        <script>
            \$(document).ready(function(){
                $('#template_questions').multiselect({
                    enableFiltering: true,
                    enableCaseInsensitiveFiltering: true,
                    buttonWidth: '100%',
                    maxHeight: 250,
                    filterPlaceholder: '".$escaper->escapeHtml($lang['SearchForQuestion'])."',
                    onDropdownHide: function(){
                        redraw();
                    }
                });
                \$('body').on('click', '.delete-row', function(){
                    \$(this).parents('.answer-row').remove();
                })
            })
        </script>
    ";
    echo "</form>";
}

/*************************************************
 * FUNCTION: DISPLAY QUESTINNAIRE TEMPLATE BY ID *
 *************************************************/
function get_assessment_questionnaire_template_by_id($template_id){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.id template_id, t1.name template_name
        FROM `questionnaire_templates` t1
        WHERE
            t1.id=:template_id;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":template_id", $template_id);
    
    $stmt->execute();

    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql = "
        SELECT t1.id question_id, t1.question, t2.ordering
        FROM `questionnaire_questions` t1 
            INNER JOIN `questionnaire_template_question` t2 ON t1.id=t2.questionnaire_question_id
        WHERE
            t2.questionnaire_template_id=:template_id
        ORDER BY t2.ordering, t1.question;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":template_id", $template_id);
    
    $stmt->execute();

    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);
    
    if(!$template){
        return false;
    }else{
        $result = array(
            'id' => $template['template_id'],
            'name' => $template['template_name'],
            'questions' => $questions,
        );
        return $result;
    }
}

/***************************************************************
 * FUNCTION: DISPLAY QUESTINNAIRE TEMPLATE QUESTIONS DATATABLE *
 ***************************************************************/
function display_questionnaire_template_questions_datatable($template_id=0)
{
    global $escaper, $lang;
    
    $tableID = "template-questions";
    echo "
        <table class=\"table risk-datatable assessment-datatable table-bordered table-striped table-condensed  \" width=\"100%\" id=\"{$tableID}\" >
            <thead >
                <tr>
                    <th>".$escaper->escapeHtml($lang['Question'])."</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <script>
            var reorder = false;
            var pageLength = 1;
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                pagingType: 'full_numbers',
                dom : 'flrtip',
                pageLength: pageLength,
                paging: false,
                ordering: false,
                rowReorder: {
                  update: false
                },
                dom : 'flrt',
                columnDefs: [
                  { className: 'reorder', 'targets': [ 0 ] }
                ],
                ajax: {
                    url: BASE_URL + '/api/assessment/questionnaire/template_questions/dynamic',
                    data: function(d){
                        d.template_id = '{$template_id}';
                        d.selected_ids = \$(\"#template_questions\").val();
                        d.reorder = reorder;
                    },
                    complete: function(response){
                        reorder = false;
                    }
                }
            });
            datatableInstance.on('row-reorder', function(){
                reorder = true
            })
            function redraw(){
                \$(\"#{$tableID}\").DataTable().draw();
            } 
        </script>
    ";
}

/*****************************************************
 * FUNCTION: DISPLAY QUESTINNAIRE TEMPLATE EDIT FORM *
 *****************************************************/
function display_questionnaire_template_edit(){
    global $lang, $escaper;
    
    // Template ID to edit
    $template_id = (int)$_GET['id'];
    
    // Get questionnaire questions
    list($recordsTotal, $questions) = get_assessment_questionnaire_questions();
    
    // Get questionnaire template by ID
    $template = get_assessment_questionnaire_template_by_id($template_id);

    // Create question ids array
    $question_ids = array();
    foreach($template['questions'] as $question){
        $question_ids[] = $question['question_id'];
    }

    echo "<form method='post' autocomplete='off'>";
    echo "
        <h4>".$escaper->escapeHtml($lang['EditQuestionnaireTemplate'])." <button type='submit' name='edit_questionnaire_template' class='btn pull-right'>". $escaper->escapeHtml($lang['Update']) ."</button></h4>
        <div class='clearfix'></div>
        <br>
        <div class='row-fluid'>
            <strong class='span1'>". $escaper->escapeHtml($lang['Name']) .":&nbsp; </strong>
            <div class='span11'><input placeholder='". $escaper->escapeHtml($lang['Question']) ."' type='text' name='name' required class='form-control' style='max-width: none' value=\"".$escaper->escapeHtml($template['name'])."\"></div>
        </div>
        <div class='row-fluid'>
            <strong class='span1'>". $escaper->escapeHtml($lang['Questions']) .":&nbsp; </strong>
            <div class='span11'>
                <select id=\"template_questions\" name=\"template_questions[]\" multiple=\"multiple\" style='max-width: none;'>";
                foreach($questions as $question){
                    if(in_array($question['id'], $question_ids)){
                        echo "<option value='".$question['id']."' selected>".$escaper->escapeHtml($question['question'])."</option>";
                    }else{
                        echo "<option value='".$question['id']."'>".$escaper->escapeHtml($question['question'])."</option>";
                    }
                }
            echo "</select>
            </div>
        </div>
    ";
    
    display_questionnaire_template_questions_datatable($template_id);
    
    echo "
        <script>
            
            \$(document).ready(function(){
                $('#template_questions').multiselect({
                    enableFiltering: true,
                    enableCaseInsensitiveFiltering: true,
                    buttonWidth: '100%',
                    filterPlaceholder: '".$escaper->escapeHtml($lang['SearchForQuestion'])."',
                    onDropdownHide: function(){
                        redraw();
                    }
                });
                \$('body').on('click', '.delete-row', function(){
                    \$(this).parents('.answer-row').remove();
                })
            })
        </script>
    ";

    echo "</form>";
}

/******************************************
 * FUNCTION: CHECK QUESTIONNAIRE WAS SENT *
 ******************************************/
function is_sent_questionnare($questionnaire_id)
{
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT *
        FROM
            `questionnaire_tracking`
        WHERE
            questionnaire_id=:questionnaire_id;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":questionnaire_id", $questionnaire_id);
    
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $results ? true : false;
}

/********************************
 * FUNCTION: SEND QUESTIONNAIRE *
 ********************************/
function sendQuestionnaireAPI() {
    global $lang, $escaper;
    
    $id = (int) $_POST['id'];
    $pre_population = (int) $_POST['pre_population'];

    if(send_questionnaire($id, $pre_population)) {
        set_alert(true, "good", $lang['SentQuestionnaire']);
        json_response(200, get_alert(true), null);
    } else {
        set_alert(true, "bad", $lang['QuestionnaireHasNoContacts']);
        json_response(400, get_alert(true), null);
    }
}

/***********************************************
 * FUNCTION: PROCESS ASSESSMENT QUESTIONNAIRES *
 ***********************************************/
function process_assessment_questionnaires(){
    global $lang, $escaper;

    $process = false;
    
    // Check if add questionnaire
    if(isset($_POST['add_questionnaire'])){
        $name                       = $_POST['name'];
        if(get_questionnaire_by_name($name)){
            set_alert(true, "bad", $escaper->escapeHtml($lang['DuplicatedQuestionnaireName']));
            return false;
        }
        $user_instructions          = isset($_POST['user_instructions']) ? trim($_POST['user_instructions']) : "";
        $questionnaire_templates    = isset($_POST['questionnaire_templates']) ? $_POST['questionnaire_templates'] : array();
        $assessment_contacts        = isset($_POST['assessment_contacts']) ? $_POST['assessment_contacts'] : array();

        // Check if a name exists.
        if($name){
            add_questionnaire($name, $questionnaire_templates, $assessment_contacts, $user_instructions);
            
            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['QuestionnaireNameRequired']));
        }
        $process = true;
    }
    // Check if edit questionnaire
    elseif(isset($_POST['edit_questionnaire'])){
        $name               = $_POST['name'];
        $questionnaire_id   = (int)$_GET['id'];
        
        // Check if the questionnaire name exists
        $questionnaires = get_questionnaire_by_name($name);
        foreach($questionnaires as $questionnaire){
            if($questionnaire['name'] == $name && $questionnaire['id'] != $questionnaire_id){
                set_alert(true, "bad", $escaper->escapeHtml($lang['DuplicatedQuestionnaireName']));
                return true;
            }
        }

        $name                       = $_POST['name'];
        $user_instructions          = isset($_POST['user_instructions']) ? trim($_POST['user_instructions']) : "";
        $questionnaire_templates    = empty($_POST['questionnaire_templates']) ? [] : $_POST['questionnaire_templates'];
        $assessment_contacts        = empty($_POST['assessment_contacts']) ? [] : $_POST['assessment_contacts'];
        
        // Check questionnaire was already sent
        if(is_sent_questionnare($questionnaire_id))
        {
            set_alert(true, "bad", $escaper->escapeHtml($lang['QuestionnaireAlreadySent']));
        }
        // Check if questionnaire name exists.
        elseif($name){
            
            // Update questionnaire
            update_questionnaire($questionnaire_id, $name, $questionnaire_templates, $assessment_contacts, $user_instructions);
            
            set_alert(true, "good", $escaper->escapeHtml($lang['SavedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['QuestionnaireNameRequired']));
        }
        $process = true;
    }
    // Check if delete a questionnaire
    elseif(isset($_POST['delete_questionnaire'])){
        // Check if a question and at least an answer exists.
        if($questionnaire_id = (int)$_POST['questionnaire_id']){
            delete_questionnaire($questionnaire_id);
            set_alert(true, "good", $escaper->escapeHtml($lang['DeletedSuccess']));
        }
        else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidInformations']));
        }
        $process = true;
    }
    
    return $process;
}

/*****************************************************************
 * FUNCTION: GET QUESTIONNAIRE AND TEMPLATES BY QUESTIONNAIRE ID *
 *****************************************************************/
function get_questionnaire_by_id($questionnaire_id){
    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t1.id questionnaire_id, t1.name questionnaire_name, t1.user_instructions, t2.template_id, t2.contact_ids, t3.name template_name
        FROM `questionnaires` t1
            LEFT JOIN `questionnaire_id_template` t2 ON t1.id=t2.questionnaire_id
            LEFT JOIN `questionnaire_templates` t3 ON t2.template_id=t3.id
        WHERE
            t1.id=:questionnaire_id
        ORDER BY t3.name;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":questionnaire_id", $questionnaire_id);
    
    $stmt->execute();

    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Close the database connection
    db_close($db);
    
    if(!$templates){
        return false;
    }else{
        $array = [];
        foreach($templates as &$template){
            $template['contacts'] = [];
            
            if (isset($template['contact_ids']) && $template['contact_ids']) {
                foreach(explode(',', $template['contact_ids']) as $contact_id) {
                    $contact = get_assessment_contact($contact_id);
                    
                    if ($contact)
                        $template['contacts'][] = $contact;
                }
            }

            if($template['template_id'])
                $array[] = $template;
        }
        $questionnaire = array(
            'id' => $templates[0]['questionnaire_id'],
            'name' => $templates[0]['questionnaire_name'],
            'user_instructions' => $templates[0]['user_instructions'],
            'templates' => $array,
        );
        return $questionnaire;
    }
}

/********************************
 * FUNCTION: SEND QUESTIONNAIRE *
 ********************************/
function send_questionnaire($questionnaire_id, $pre_population){
    global $lang, $escaper;
    
    $questionnaire = get_questionnaire_by_id($questionnaire_id);
    
    $contacts = array();
    foreach($questionnaire['templates'] as $template) {
        if(!$template['contact_ids']) {
            continue;
        }

        foreach($template['contacts'] as $contact) {
            $contacts[$contact['id']] = $contact;
        }
    }

    // Check if contacts for this questionnaire exist
    if($contacts){
        // Open the database connection
        $db = db_open();

        // Require the mail functions
        require_once(realpath(__DIR__ . '/../../includes/mail.php'));

        // Create the message subject
        $subject = $escaper->escapeHtml($lang['RiskAssessmentQuestionnaire']);

        foreach($contacts as $contact){
            // Generate token for unique link
            $token = generate_token(40);

            // Create the message body
            $body = get_string_from_template($lang['EmailTemplateSendingAssessment'], array(
                'username' => $escaper->escapeHtml($_SESSION['name']),
                'assessment_name' => $questionnaire['name'],
                'assessment_link' => $_SESSION['base_url'] . "/assessments/questionnaire.index.php?token=" . $token,
            ));
            
            send_email($contact['name'], $contact['email'], $subject, $body);
            $sent_at = date("Y-m-d H:i:s");

            // Query the database
            $stmt = $db->prepare("INSERT INTO `questionnaire_tracking`(questionnaire_id, pre_population, contact_id, token, sent_at) VALUES(:questionnaire_id, :pre_population, :contact_id, :token, :sent_at); ");

            $stmt->bindParam(":questionnaire_id", $questionnaire_id, PDO::PARAM_INT);
            $stmt->bindParam(":pre_population", $pre_population, PDO::PARAM_INT);
            $stmt->bindParam(":contact_id", $contact['id'], PDO::PARAM_INT);
            $stmt->bindParam(":token", $token, PDO::PARAM_STR, 100);
            $stmt->bindParam(":sent_at", $sent_at, PDO::PARAM_STR, 20);

            // Create a track for sending questionnaire
            $stmt->execute();
            $tracking_id = $db->lastInsertId();
            
            $message = _lang('QuestionnaireSentAuditLog', array(
                'questionnaire_name' => $questionnaire['name'],
                'contact_name' => $contact['name'],
                'user_name' => $_SESSION['user']
            ), false);

            //write_log($questionnaire_id+1000, $_SESSION['uid'], $message, 'questionnaire');
            write_log($tracking_id+1000, $_SESSION['uid'], $message, 'questionnaire_tracking');
        }
        // Close the database connection
        db_close($db);
        
        return true;
    }else{
        return false;
    }
}

/***********************************
 * FUNCTION: DISPLAY QUESTINNAIRES *
 ***********************************/
function display_questionnaires(){
    global $lang;
    global $escaper;

    $tableID = "assessment-questionnaires-table";
    
    echo "
        <table class=\"table risk-datatable assessment-datatable table-bordered table-striped table-condensed  \" width=\"100%\" id=\"{$tableID}\" >
            <thead>
                <tr >
                    <th>". $escaper->escapeHtml($lang['Questionnaires']) ."</th>
                    <th width='80px'>&nbsp;</th>
                    <th width='100px'>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <div id=\"send-questionnaire-modal-container\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
          <div class=\"modal-body\">

              <div class=\"form-group text-center\">
                <label for=\"\">".$escaper->escapeHtml($lang['PrePopulateWithAnswersFromLastAssessment'])."</label>
              </div>

              <input type=\"hidden\" class=\"questionnaire_id\" value=\"\" />
              <div class=\"form-group text-center control-delete-actions\">
                <button id=\"pre-populate-yes\"  class=\"btn btn-danger\">".$escaper->escapeHtml($lang['Yes'])."</button>&nbsp;&nbsp;&nbsp;
                <button id=\"pre-populate-no\" class=\"btn btn-danger\" >".$escaper->escapeHtml($lang['No'])."</button>&nbsp;&nbsp;&nbsp;
                <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
              </div>

          </div>
        </div>
        
        <script>
            var pageLength = 10;
            var form = $('#{$tableID}').parents('form');
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: false,
                pagingType: 'full_numbers',
                dom : 'flrtip',
                pageLength: pageLength,
                dom : 'flrti<\"#view-all.view-all\">p',
                ajax: {
                    url: BASE_URL + '/api/assessment/questionnaire/dynamic',
                    data: function(d){
                    },
                    complete: function(response){
                    }
                }
            });
            
            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })
            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw()
                $(this).addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw()
                }
            })
            
            // Copy questionnaire
            $(\"body\").on(\"click\", \".copy-btn\", function(){
                var questionnaire_id = $(this).data('id');
                $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/assessment/questionnaire/copy',
                    data: {
                        id: questionnaire_id
                    },
                    success: function(data){
                        datatableInstance.draw();
                    }
                }).fail(function(xhr, textStatus){
                    if(!retryCSRF(xhr, this))
                    {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }

                });

            })
            
        </script>
    ";
    

    // MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
    echo "
        <div id=\"aseessment-questionnaire--delete\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
          <div class=\"modal-body\">

            <form class=\"\" action=\"\" method=\"post\">
              <div class=\"form-group text-center\">
                <label for=\"\">".$escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisQestionnaire'])."</label>
              </div>

              <input type=\"hidden\" name=\"questionnaire_id\" value=\"\" />
              <div class=\"form-group text-center \">
                <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                <button type=\"submit\" name=\"delete_questionnaire\" class=\"delete_control btn btn-danger\">".$escaper->escapeHtml($lang['Yes'])."</button>
              </div>
            </form>
          </div>
        </div>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.delete-btn', function(){
                \$('#aseessment-questionnaire--delete [name=questionnaire_id]').val(\$(this).data('id'));
            });

            \$('body').on('click', '.send-questionnaire', function(){
                $('#send-questionnaire-modal-container .questionnaire_id').val($(this).data('id'));
                $('#send-questionnaire-modal-container').modal()
            });
            var loading={
            show:function(el)
            {
                this.getID(el).style.display='';
            },
            hide:function(el)
            {
                this.getID(el).style.display='none';
            },
            getID:function(el)
            {
                return document.getElementById(el);
            }
          }
            
            function sendQuestionnaire(id, pre_population)
            {
                loading.show('load')
                $.ajax({
                    url: BASE_URL + '/api/assessment/send_questionnaire',
                    type: 'POST',
                    data: {
                        id: id,
                        pre_population: pre_population,
                    },
                    success : function (res){
                        loading.hide('load');
                        if(res.status_message){
                            showAlertsFromArray(res.status_message);
                        }
                    },
                    error: function(xhr,status,error){
                        loading.hide('load');
                        if(!retryCSRF(xhr, this))
                        {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }
                    },
                    complete : function (res){
                        loading.hide('load');
                        $('#send-questionnaire-modal-container').modal('hide');
                    }
                });
            }
            
            \$('#pre-populate-yes').click(function(){
                var id = $('#send-questionnaire-modal-container .questionnaire_id').val();
                sendQuestionnaire(id, 1);
            });

            \$('#pre-populate-no').click(function(){
                var id = $('#send-questionnaire-modal-container .questionnaire_id').val();
                sendQuestionnaire(id, 0);
            });
        </script>
    ";
}

/******************************************
 * FUNCTION: GET ASSESSMENT QUESTINNAIRES *
 ******************************************/
function get_assessment_questionnaires($start=0, $length=-1){
    // Open the database connection
    $db = db_open();
    
    /*** Get questionnaires by $start and $lengh ***/
    $sql = "
        SELECT
            SQL_CALC_FOUND_ROWS q.id, q.name, count(qit.template_id) as template_count
        FROM
            `questionnaires` q
            LEFT JOIN `questionnaire_id_template` qit ON qit.questionnaire_id = q.id
        GROUP BY
            q.id
        ORDER BY
            q.name
    ";
    if($length != -1){
        $sql .= " LIMIT {$start}, {$length}; ";
    }
        // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaire_id_template` WHERE questionnaire_id=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    
    // Delete questionnaire and template relations by questionnaire ID
    $stmt->execute();
    $stmt = $db->prepare($sql);
    
    $stmt->execute();

    $questionnaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $recordsTotal = $stmt->fetchColumn();
    
    // Close the database connection
    db_close($db);
    
    return array($recordsTotal, $questionnaires);
}

/*********************************
 * FUNCTION: DELETE QUESTINNAIRE *
 *********************************/
function delete_questionnaire($id){
    $id = (int)$id;
    
    $questionnaire = get_questionnaire_by_id($id);
    
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaire_id_template` WHERE questionnaire_id=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    
    // Delete questionnaire and template relations by questionnaire ID
    $stmt->execute();

    // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaires` WHERE id=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    
    // Delete a questionnaire
    $stmt->execute();
    
    // Close the database connection
    db_close($db);

    $message = "A questionnaire named \"{$questionnaire['name']}\" was deleted by username \"" . $_SESSION['user']."\".";
    write_log($id+1000, $_SESSION['uid'], $message, 'questionnaire');
}

/********************************************
 * FUNCTION: DISPLAY QUESTINNAIRE EDIT FORM *
 ********************************************/
function display_questionnaire_edit(){
    global $lang;
    global $escaper;
    
    // Get questionnaire templates
    list($recordsTotal, $templates) = get_assessment_questionnaire_templates();

    // Get assessment contacts
    $contacts = get_assessment_contacts();
    
    // Get questionnaire and related templates by questionnaire ID
    $questionnaire_id = $_GET['id'];
    $questionnaire = get_questionnaire_by_id($questionnaire_id);
    
    if(!$questionnaire){
        echo "Invalid parameter";
        return;
    }
    
    echo "
        <form id='edit_questionnaire_form' method='post' class='' autocomplete='off'>
            <div class='hero-unit'>
                <h4 class='content-header-height'>".$escaper->escapeHtml($lang['Settings'])." <button type='submit' name='edit_questionnaire' class='btn pull-right'>". $escaper->escapeHtml($lang['Save']) ."</button></h4>
                <div class='row-fluid'>
                    <strong class='span1'>". $escaper->escapeHtml($lang['Name']) .":&nbsp; </strong>
                    <div class='span11'><input placeholder='". $escaper->escapeHtml($lang['QuestionnaireName']) ."' type='text' name='name' required class='form-control' style='max-width: none' value='".(isset($questionnaire['name']) ? $escaper->escapeHtml($questionnaire['name']) : "")."'></div>
                </div>
                <div class='row-fluid'>
                    <strong class='span1'>". $escaper->escapeHtml($lang['UserInstructions']) .":&nbsp; </strong>
                    <div class='span11'>
                        <textarea class='form-control' style='max-width: none; width: 100%;' name='user_instructions' rows='3' id='user_instructions'>".(isset($questionnaire['user_instructions']) ? $escaper->escapeHtml($questionnaire['user_instructions']) : "")."</textarea>
                    </div>
                </div>                
            </div>    
            <div class='hero-unit'>                
                <h4 class='content-header-height'>".$escaper->escapeHtml($lang['Templates'])."</h4>
                <div id='template-contacts-container'>";
                    if(is_array($questionnaire['templates'])){
                        foreach($questionnaire['templates'] as $key => $questionnaire_template){
                            $hideClass = ($key == 0 ? "hide" : "");
                            echo "
                                <div class='row-fluid template-contact-row'>
                                    <strong class='span1'>". $escaper->escapeHtml($lang['Template']) .":&nbsp; </strong>
                                    <div class='span5'>
                                        <select name=\"questionnaire_templates[]\" style='max-width: none;'>
                                            <option value=''>--</option>";
                                        foreach($templates as $template){
                                            if($questionnaire_template['template_id'] == $template['id']){
                                                echo "<option selected value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                                            }else{
                                                echo "<option value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                                            }
                                        }
                                        echo "</select>
                                    </div>
                                    <strong class='span2'>". $escaper->escapeHtml($lang['AssessmentContacts']) .":&nbsp; </strong>
                                    <div class='span3'>
                                        <select multiple='multiple' required class='assessment_contacts' style='max-width: none;'>";
                                        
                                        $contact_ids = [];
                                        if (isset($questionnaire_template['contact_ids']) && $questionnaire_template['contact_ids']) {
                                            $contact_ids = explode(',', $questionnaire_template['contact_ids']);
                                        }

                                        foreach($contacts as $contact){
                                            if(in_array($contact['id'], $contact_ids)){
                                                echo "<option selected value='".$contact['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                                            }else{
                                                echo "<option value='".$contact['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                                            }
                                        }
                                        echo "</select>
                                    </div>
                                    <div class='span1 text-center'>
                                        <a class='delete-row {$hideClass}' href=''><img class=\"add-delete-icon\" src=\"../images/minus.png\" width=\"15px\" height=\"15px\"></a>
                                    </div>
                                </div>
                            ";
                        }
                    }else{
                        
                    }
                echo "</div>
                <div class='row-fluid'>
                    <div class='span11'>
                    </div>
                    <div class='span1 text-center'>
                        <a class='add-row' href=''><img class=\"add-delete-icon\" src=\"../images/plus.png\" width=\"15px\" height=\"15px\"></a>
                    </div>
                </div>
            </div>
        </form>
        <div id='contact-row-template-container' style='display:none'>
            <div class='row-fluid template-contact-row'>
                <strong class='span1'>". $escaper->escapeHtml($lang['Template']) .":&nbsp; </strong>
                <div class='span5'>
                    <select required name=\"questionnaire_templates[]\" style='max-width: none;'>
                        <option value=''>--</option>";
                    foreach($templates as $template){
                        echo "<option value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                    }
                    echo "</select>
                </div>
                <strong class='span2'>". $escaper->escapeHtml($lang['AssessmentContacts']) .":&nbsp; </strong>
                <div class='span3'>
                    <select multiple='multiple' required class='assessment_contacts' style='max-width: none;'>";
                    foreach($contacts as $contact){
                        echo "<option value='".$contact['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                    }
                    echo "</select>
                </div>
                <div class='span1 text-center'>
                    <a class='delete-row' href=''><img class=\"add-delete-icon\" src=\"../images/minus.png\" width=\"15px\" height=\"15px\"></a>
                </div>
            </div>
        </div>
    ";
    
    echo "
        <script>
            function refresh_assessment_contacts_widget_names(){
                $('#edit_questionnaire_form select.assessment_contacts').each(function(index, element){
                    $(element).attr('name', 'assessment_contacts[' + index + '][]');
                });
            }
            
            function setup_multiselect(target) {
                target.multiselect({
                    includeSelectAllOption: true,
                    enableFiltering: true,
                    enableCaseInsensitiveFiltering: true,
                    buttonWidth: '100%',
                    maxHeight: 150
                });
            }

            \$('body').on('click', '.add-row', function(e){
                e.preventDefault();
                var appended_row = $('#template-contacts-container').append($('#contact-row-template-container').html());
                refresh_assessment_contacts_widget_names();
                setup_multiselect($(\"select.assessment_contacts\", appended_row));
            });

            \$('body').on('click', '.delete-row', function(e){
                e.preventDefault();
                \$(this).parents('.template-contact-row').remove();
                refresh_assessment_contacts_widget_names();
            });
            
            $(document).ready(function(){
                refresh_assessment_contacts_widget_names();
                $(\"#edit_questionnaire_form select.assessment_contacts\").each(function() {
                    setup_multiselect($(this));
                });
            });
        </script>
    ";
}

/*******************************************
 * FUNCTION: DISPLAY QUESTINNAIRE ADD FORM *
 *******************************************/
function display_questionnaire_add(){
    global $lang;
    global $escaper;
    
    // Get questionnaire templates
    list($recordsTotal, $templates) = get_assessment_questionnaire_templates();

    // Get assessment contacts
    $contacts = get_assessment_contacts();

    echo "
        <form id='add_questionnaire_form' method='post' class='' autocomplete='off'>
            <div class='hero-unit'>
                <h4 class='content-header-height'>".$escaper->escapeHtml($lang['Settings'])." <button type='submit' name='add_questionnaire' class='btn pull-right'>". $escaper->escapeHtml($lang['Save']) ."</button></h4>
                <div class='row-fluid'>
                    <strong class='span1'>". $escaper->escapeHtml($lang['Name']) .":&nbsp; </strong>
                    <div class='span11'><input placeholder='". $escaper->escapeHtml($lang['QuestionnaireName']) ."' type='text' name='name' required class='form-control' style='max-width: none' value='".(isset($_POST['name']) ? $_POST['name'] : "")."'></div>
                </div>
                <div class='row-fluid'>
                    <strong class='span1'>". $escaper->escapeHtml($lang['UserInstructions']) .":&nbsp; </strong>
                    <div class='span11'>
                        <textarea class='form-control' style='max-width: none; width: 100%;' name='user_instructions' rows='3' id='user_instructions'></textarea>                    
                    </div>
                </div>                
            </div>
            <div class='hero-unit'>                
                <h4 class='content-header-height'>".$escaper->escapeHtml($lang['Templates'])."</h4>
                <div id='template-contacts-container'>";
    if(isset($_POST['questionnaire_templates'])){
        foreach($_POST['questionnaire_templates'] as $key => $questionnaire_template){
            $hideClass = ($key == 0 ? "hide" : "");
            echo "
                    <div class='row-fluid template-contact-row'>
                        <strong class='span1'>". $escaper->escapeHtml($lang['Template']) .":&nbsp; </strong>
                        <div class='span5'>
                            <select required name=\"questionnaire_templates[]\" style='max-width: none;'>
                                <option value=''>--</option>";
                            foreach($templates as $template){
                                if($questionnaire_template == $template['id']){
                                    echo "<option selected value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                                }else{
                                    echo "<option value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                                }
                            }
                            echo "</select>
                        </div>
                        <strong class='span2'>". $escaper->escapeHtml($lang['AssessmentContacts']) .":&nbsp; </strong>
                        <div class='span3'>
                            <select multiple='multiple' required class='assessment_contacts' style='max-width: none;'>";
                            foreach($contacts as $contact){
                                if(in_array($contact['id'], $_POST['assessment_contacts'][$key])){
                                    echo "<option selected value='".$contact['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                                }else{
                                    echo "<option value='".$contact['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                                }
                            }
                            echo "</select>
                        </div>
                        <div class='span1 text-center'>
                            <a class='delete-row {$hideClass}' href=''><img class=\"add-delete-icon\" src=\"../images/minus.png\" width=\"15px\" height=\"15px\"></a>
                        </div>
                    </div>
            ";
        }
    }else{
        echo "
                    <div class='row-fluid'>
                        <strong class='span1'>". $escaper->escapeHtml($lang['Template']) .":&nbsp; </strong>
                        <div class='span5'>
                            <select required name=\"questionnaire_templates[]\" style='max-width: none;'>
                                <option value=''>--</option>";
                            foreach($templates as $template){
                                echo "<option value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                            }
                            echo "</select>
                        </div>
                        <strong class='span2'>". $escaper->escapeHtml($lang['AssessmentContacts']) .":&nbsp; </strong>
                        <div class='span3'>";
                            // echo "<select multiple='multiple' required class='assessment_contacts' style='max-width: none;'>";
                            // foreach($contacts as $contact){
                            //     echo "<option value='".$contact['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                            // }
                            // echo "</select>";
                            echo "<select class='assessment_contacts_select' name='assessment_contacts[]' multiple placeholder='" . $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']) . "'>";
                            echo "</select>\n";
                        echo "</div>
                        <div class='span1'></div>
                    </div>";
    }

    echo "
                </div>
                <div class='row-fluid'>
                    <div class='span11'>
                    </div>
                    <div class='span1 text-center'>
                        <a class='add-row' href=''><img class=\"add-delete-icon\" src=\"../images/plus.png\" width=\"15px\" height=\"15px\"></a>
                    </div>
                </div>
            </div>
        </form>
        <div id='contact-row-template-container' style='display:none'>
            <div class='row-fluid template-contact-row'>
                <strong class='span1'>". $escaper->escapeHtml($lang['Template']) .":&nbsp; </strong>
                <div class='span5'>
                    <select required name=\"questionnaire_templates[]\" style='max-width: none;'>
                        <option value=''>--</option>";
                    foreach($templates as $template){
                        echo "<option value='".$template['id']."'>".$escaper->escapeHtml($template['name'])."</option>";
                    }
                    echo "</select>
                </div>
                <strong class='span2'>". $escaper->escapeHtml($lang['AssessmentContacts']) .":&nbsp; </strong>
                <div class='span3'>";
                    echo "<select multiple='multiple' required class='assessment_contacts' style='max-width: none;'>";
                    foreach($contacts as $contact){
                        echo "<option value='".$contact['id']."'>".$escaper->escapeHtml($contact['name'])."</option>";
                    }
                    echo "</select>";
                    // echo "<select class='assessment_contacts_select' name='assessment_contacts[]' multiple placeholder='" . $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']) . "'>";
                    // echo "</select>\n";
                echo "</div>
                <div class='span1 text-center'>
                    <a class='delete-row' href=''><img class=\"add-delete-icon\" src=\"../images/minus.png\" width=\"15px\" height=\"15px\"></a>
                </div>
            </div>
        </div>
    ";
    
    echo "
        <script>
            function assessmentContacts(select_tag, risk_id) {
                // Giving a default value here because IE can't handle
                // function parameter default values...
                risk_id = risk_id || 0;
                
                if (!select_tag.length)
                    return;

                var data = ".json_encode($contacts).";
                console.log(data);

                var select = select_tag.selectize({
                    sortField: 'text',
                    plugins: ['optgroup_columns', 'remove_button', 'restore_on_backspace'],
                    delimiter: ',',
                    create: function (input){
                        return { id:'new_asset_' + input, name:input };
                    },
                    persist: false,
                    valueField: 'id',
                    labelField: 'name',
                    searchField: 'name',
                    sortField: 'name',
                    // optgroups: [
                    //     {class: 'asset', name: 'Standard Assets'},
                    //     {class: 'group', name: 'Asset Groups'}
                    // ],
                    optgroupField: 'class',
                    optgroupLabelField: 'name',
                    optgroupValueField: 'class',
                    preload: true,
                    render: {
                        item: function(item, escape) {
                            return '<div class=\"' + item.class + '\">' + escape(item.name) + '</div>';
                        }
                    },
                    load: function(query, callback) {
                        if (query.length) return callback();
                        var control = select[0].selectize;
                        var selected_ids = [];
                        len = data.length;
                        for (var i = 0; i < len; i++) {
                            var item = data[i];
                            item.id += '_' + item.class;
                            control.registerOption(item);
                            if (item.selected == '1') {
                                selected_ids.push(item.id);
                            }
                        }
                        if (selected_ids.length)
                            control.setValue(selected_ids);
                    }
                });
            }

            assessmentContacts($('.assessment_contacts_select'));

            function refresh_assessment_contacts_widget_names(){
                $('#add_questionnaire_form select.assessment_contacts').each(function(index, element){
                    $(element).attr('name', 'assessment_contacts[' + index + '][]');
                });
            }

            function setup_multiselect(target) {
                target.multiselect({
                    includeSelectAllOption: true,
                    enableFiltering: true,
                    enableCaseInsensitiveFiltering: true,
                    buttonWidth: '100%',
                    maxHeight: 150
                });
            }

            $(document).ready(function(){
                refresh_assessment_contacts_widget_names();

                \$('body').on('click', '.add-row', function(e){
                    e.preventDefault();
                    var appended_row = $('#template-contacts-container').append($('#contact-row-template-container').html());
                    refresh_assessment_contacts_widget_names();
                    setup_multiselect($(\"select.assessment_contacts\", appended_row));
                });

                \$('body').on('click', '.delete-row', function(e){
                    e.preventDefault();
                    \$(this).parents('.template-contact-row').remove();
                    refresh_assessment_contacts_widget_names();
                });

                $(\"#add_questionnaire_form select.assessment_contacts\").each(function() {
                    setup_multiselect($(this));
                });
            });

        </script>
    ";
}

/**************************************
 * FUNCTION: GET QUESTINNAIRE BY NAME *
 **************************************/
function get_questionnaire_by_name($name){
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `questionnaires` WHERE `name`=:name;");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR, 100);
    $stmt->execute();

    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/******************************
 * FUNCTION: ADD QUESTINNAIRE *
 ******************************/
function add_questionnaire($name, $template_ids, $all_contact_ids, $user_instructions){
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT INTO `questionnaires` (`name`, `user_instructions`) VALUES (:name, :user_instructions);");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":user_instructions", $user_instructions, PDO::PARAM_STR);
        
    // Create a template
    $stmt->execute();
    
    // Get the questionnaire id of the last insert
    $questionnaire_id = $db->lastInsertId();
    
    foreach($template_ids as $key=>$template_id){

        $template_id = (int)$template_id;
        $contact_ids = isset($all_contact_ids[$key]) && is_array($all_contact_ids[$key]) ? implode(',', $all_contact_ids[$key]) : "";

        // Query the database
        $stmt = $db->prepare("INSERT INTO `questionnaire_id_template` (`questionnaire_id`, `template_id`, `contact_ids`) VALUES (:questionnaire_id, :template_id, :contact_ids);");
        $stmt->bindParam(":questionnaire_id", $questionnaire_id, PDO::PARAM_INT);
        $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
        $stmt->bindParam(":contact_ids", $contact_ids, PDO::PARAM_STR);
        
        // Create a relation
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);

    $message = "A questionnaire named \"{$name}\" was added by username \"" . $_SESSION['user']."\".";
    write_log($questionnaire_id+1000, $_SESSION['uid'], $message, 'questionnaire');
    
    // Return the questionnaire id
    return $questionnaire_id;
}

/******************************
 * FUNCTION: ADD QUESTINNAIRE *
 ******************************/
function update_questionnaire($questionnaire_id, $name, $template_ids, $all_contact_ids, $user_instructions){
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("UPDATE `questionnaires` SET `name`=:name, `user_instructions`=:user_instructions WHERE id=:id;");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":id", $questionnaire_id, PDO::PARAM_INT);
    $stmt->bindParam(":user_instructions", $user_instructions, PDO::PARAM_STR);
    
    // Update a questionnaire by questionnaire ID
    $stmt->execute();
    
    // Query the database
    $stmt = $db->prepare("DELETE FROM `questionnaire_id_template` WHERE questionnaire_id=:questionnaire_id;");
    $stmt->bindParam(":questionnaire_id", $questionnaire_id, PDO::PARAM_INT);
    
    // Delete all questionnaire and template relations by questionnaire ID
    $stmt->execute();
    
    foreach($template_ids as $key=>$template_id){
        $template_id = (int)$template_id;
        $contact_ids = isset($all_contact_ids[$key]) && is_array($all_contact_ids[$key]) ? implode(',', $all_contact_ids[$key]) : "";

        // Query the database
        $stmt = $db->prepare("INSERT INTO `questionnaire_id_template` (`questionnaire_id`, `template_id`, `contact_ids`) VALUES (:questionnaire_id, :template_id, :contact_ids);");
        $stmt->bindParam(":questionnaire_id", $questionnaire_id, PDO::PARAM_INT);
        $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
        $stmt->bindParam(":contact_ids", $contact_ids, PDO::PARAM_STR);
        
        // Create a relation
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);

    $message = "A questionnaire named \"{$name}\" was updated by username \"" . $_SESSION['user']."\".";
    write_log($questionnaire_id+1000, $_SESSION['uid'], $message, 'questionnaire');
    
    // Return the questionnaire id
    return $questionnaire_id;
}

/***************************************
 * FUNCTION: IS VALID ASSESSMENT TOKEN *
 ***************************************/
function is_valid_questionnaire_token($token)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `questionnaire_tracking` WHERE `token`=:token;");
    $stmt->bindParam(":token", $token, PDO::PARAM_STR, 40);
    $stmt->execute();

    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the query returned a value
    if (!empty($array))
    {
        return true;
    }
    else return false;
}

/**********************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE QUESTIONS FOR CONTACTS *
 **********************************************************/
function display_questionnaire_index(){
    global $escaper;
    //check_contact_authentication();
    display_contact_questionnaire();
}

/******************************************
 * FUNCTION: CHECK CONTACT AUTHENTICATION *
 ******************************************/
function check_contact_authentication()
{
    global $lang, $escaper;
    $token      = $_GET['token'];

    // Set session
    $contact_id = get_questionnaire_contact_id_by_token($token);
    $_SESSION['contact_id'] = $contact_id;
    $_SESSION['token']      = $token;
    // Get base url
    $base_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}";
    $base_url = htmlspecialchars( $base_url, ENT_QUOTES, 'UTF-8' );
    $base_url = pathinfo($base_url)['dirname'];
    $base_url = dirname($base_url);
    // Set the permissions
    $_SESSION['base_url'] = $base_url;
}

/**********************************************
 * FUNCTION: DISPLAY QUESTIONNARE AFTER LOGIN *
 **********************************************/
function display_contact_questionnaire(){
    global $lang, $escaper;
    
    // Check contact authentication
    check_contact_authentication();

    $token      = $_GET['token'];
    $questionnaire_tracking_info = get_questionnaire_tracking_by_token($token);
    $questionnaire_status = $questionnaire_tracking_info['questionnaire_status'];
    echo "
        <h1>".$escaper->escapeHtml($lang['Questionnaire']).": ".$escaper->escapeHtml($questionnaire_tracking_info['questionnaire_name'])."</h1>
    ";
    $db_responses = get_questionnaire_responses($token);
    if(!$db_responses && $questionnaire_tracking_info['pre_population']){
        $db_responses = get_previous_questionnaire_response($token);
    }
    
    $questionnaire_id = $questionnaire_tracking_info['questionnaire_id'];
    $templates = get_questionnaire_templates_sent_by_tracking_id($questionnaire_tracking_info['tracking_id']);

    $questionnaire = get_questionnaire_by_id($questionnaire_id);
    
    if ($questionnaire && isset($questionnaire['user_instructions']) && trim($questionnaire['user_instructions'])) {
        
        echo "<br/><h4>" . $escaper->escapeHtml($lang['Instructions']) . "</h4>";
        echo "
            <div class='row-fluid'>
                <div class='span12'>".$escaper->escapeHtml($questionnaire['user_instructions'])."</div>
            </div>
            <br/>
        ";        
    }
    
    if($templates){
        $show_autocomplete = get_setting("ASSESSMENT_ASSET_SHOW_AVAILABLE");

        if ($show_autocomplete)
            $AffectedAssetsWidgetPlaceholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']);
        else
            $AffectedAssetsWidgetPlaceholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetNoDropdownPlaceholder']);

        echo "<form method=\"POST\" enctype=\"multipart/form-data\" name=\"questionnaire_response_form\" >";
        $templateIndex = 1;
        echo "
            <br>
            <div class='row-fluid'>
                <div class='span2'>
                    <strong>".$escaper->escapeHtml($lang['AssetName']).":</strong>
                </div>
                <div class='span10'>
                    <select class='assets-asset-groups-select' name='assets_asset_groups[]' multiple placeholder='$AffectedAssetsWidgetPlaceholder'>";

            if (trim($questionnaire_tracking_info['affected_assets'])) {
            foreach(explode(',', trim($questionnaire_tracking_info['affected_assets'])) as $name) {
                $value = $name = trim($name);
                if (preg_match('/^\[(.+)\]$/', $name, $matches)) {
                    $name = trim($matches[1]);
                    $type = 'group';
                } else $type = 'asset';

                $name = $escaper->escapeHtml($name);

                echo "      <option data-class='{$type}' value='{$value}' selected >{$name}</option>";
            }
        }
        echo "      </select>
                    <script>
                        var assets_and_asset_groups = [];

                        $(document).ready(function(){";

        if ($show_autocomplete)
            echo "
                            $.ajax({
                                url: '/api/asset-group/options?token=" . $questionnaire_tracking_info['token'] . "',
                                type: 'GET',
                                dataType: 'json',
                                success: function(res) {
                                    var data = res.data;
                                    var len = data.length;
                                    for (var i = 0; i < len; i++) {
                                        var item = data[i];
                                        if (item.class == 'group')
                                            item.id = '[' + item.name + ']';
                                        else
                                            item.id = item.name;

                                        assets_and_asset_groups.push(item);
                                    }";

        echo "
                                    // Have to add the selected assets to the list of options,
                                    // but only for THIS widget
                                    $('select.assets-asset-groups-select option').each(function() {

                                        assets_and_asset_groups.push({
                                            id: $(this).val(),
                                            name: $(this).text(),
                                            class: $(this).data('class')
                                        });
                                    });

                                    selectize_pending_risk_affected_assets_widget($('select.assets-asset-groups-select'), assets_and_asset_groups);";
        if ($show_autocomplete)
            echo "
                                }
                            });";

        echo "
                        });
                    </script>
                </div>
            </div>
        ";
        
        foreach($templates as $key => $template){
            echo "<div><h3>".$templateIndex.".&nbsp;".$escaper->escapeHtml($template['template_name'])."</h3></div>";

            // Get questions of this template
            $template_id = $template['template_id'];
            $template_in_detail = get_assessment_questionnaire_template_by_id($template['template_id']);
            if(isset($template_in_detail['questions'])){
                $questions = $template_in_detail['questions'];
            }else{
                $questions = [];
            }
            
            foreach($questions as $questionIndex => &$question)
            {
                $question_id = $question['question_id'];
                display_contact_questionnaire_question($question_id, $template_id, $questionIndex, $db_responses, $questionnaire_status);
            }
            
            $templateIndex++;
        }
        
        // Check if questionnaire completed
        if($questionnaire_status){
            echo "
                <div class='row-fluid attachment-container'>
                    <div ><strong>".$escaper->escapeHtml($lang['AttachmentFiles']).":&nbsp;&nbsp; </strong></div>
                    <div>
                        <div class=\"file-uploader\">
                            <ul class=\"exist-files\">
                                ";
                                display_assessment_files($questionnaire_status);
                            echo "
                            </ul>
                        </div>
                    </div>
                </div>
            ";
        }else{
            echo "
                <div class='row-fluid attachment-container'>
                    <div class='pull-left'><strong>".$escaper->escapeHtml($lang['QuestionnaireFiles']).":&nbsp;&nbsp; </strong></div>
                    <div class='pull-left'>
                        <div class=\"file-uploader\">
                            <label for=\"file-upload\" class=\"btn\">Choose File</label>
                            <span class=\"file-count-html\"> <span class=\"file-count\">0</span> File Added</span>";
                            echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
                            echo "<ul class=\"exist-files\">
                                ";
                                display_assessment_files($questionnaire_status);
                            echo "
                            </ul>
                            <ul class=\"file-list\">
                            </ul>
                            <input type=\"file\" id=\"file-upload\" name=\"file[]\" class=\"hidden-file-upload active\" />
                        </div>
                    </div>
                </div>
            ";
            
        }
        
        
        // Check if this questionnaire is not completed
        if(!$questionnaire_status){
            echo "<div class='button-container'>";
                echo "<button type='reset' class='btn' >".$escaper->escapeHtml($lang['ClearForm'])."</button>";
                echo "&nbsp;&nbsp;";
                echo "<button class='btn' id='draft_questionnaire' name='draft_questionnaire' formnovalidate>".$escaper->escapeHtml($lang['Draft'])."</button>";
                echo "&nbsp;&nbsp;";
                echo "<button class='btn' id='complete_questionnaire' name='complete_questionnaire'>".$escaper->escapeHtml($lang['Complete'])."</button>";
            echo "</div>";
        }
        echo "</form>";
    }
    
    echo "
      <script>
        \$('.answer').click(function(){
            var self = \$(this);
            \$.ajax({
                type: \"GET\",
                url:  \"{$_SESSION['base_url']}/assessments/questionnaire.index.php\",
                dataType: 'json',
                data: {
                    action: 'get_sub_questions_by_answer',
                    answer_id: $(this).val(),
                    template_id: $(this).data('template'),
                    token: '{$token}',
                },
                success: function(data){
                console.log(data)
                    self.parents('.questionnaire-answers').find('.questionnaire-questions-container-by-answer').html(data.html)
                }
            })
        })
      </script>
    ";
        
}

/*****************************************************************
 * FUNCTION: CHECK IF CONTACTER HAS PERMISSION FOR QUESTIONNAIRE *
 *****************************************************************/
function check_contact_permission_for_questionnaire($token){
    global $escaper, $lang;

    // Check if contact is authenticated
    if(!isset($_SESSION['contact_id'])){
        return false;
    }

    $contact_id = $_SESSION['contact_id'];

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `questionnaire_tracking` WHERE `token`=:token and `contact_id`=:contact_id;");
    $stmt->bindParam(":token", $token, PDO::PARAM_STR);
    $stmt->bindParam(":contact_id", $contact_id, PDO::PARAM_INT);
    $stmt->execute();

    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the query returned a value
    if (!empty($array))
    {
        return true;
    }
    else 
    {
        logout_contact();
    
        return false;
    }
    
}

/*************************************************
 * FUNCTION: GET PREVIOUS QUESTIONNAIRE RESPONSE *
 *************************************************/
function get_previous_questionnaire_response($current_token)
{
    $db = db_open();

    $stmt = $db->prepare("
        SELECT t2.token
        FROM `questionnaire_responses` t1 
            INNER JOIN `questionnaire_tracking` t2 ON t1.questionnaire_tracking_id=t2.id 
            INNER JOIN `questionnaire_tracking` t3 ON t2.questionnaire_id=t3.questionnaire_id AND t3.token=:token
        WHERE
            t2.token<>:token
        ORDER BY t1.`updated_at` desc
        ;
    ");
    $stmt->bindParam(":token", $current_token, PDO::PARAM_STR);
    $stmt->execute();

    $latest_response = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    if($latest_response){
        return get_questionnaire_responses($latest_response['token']);
    }else{
        return array();
    }
}

/*****************************************
 * FUNCTION: GET QUESTIONNAIRE RESPONSES *
 *****************************************/
function get_questionnaire_responses($token)
{
    $db = db_open();

    $stmt = $db->prepare("
        SELECT t1.template_id, t1.question_id, t1.additional_information, t1.answer, t1.parent_question_id
        FROM `questionnaire_responses` t1 
            INNER JOIN `questionnaire_tracking` t2 ON t1.questionnaire_tracking_id=t2.id 
        WHERE t2.`token`=:token;
    ");
    $stmt->bindParam(":token", $token, PDO::PARAM_STR);
    $stmt->execute();

    $array = $stmt->fetchAll();
    
    // Close the database connection
    db_close($db);
    
    // Two-dimensional array: [template_id][question_id]
    $responses = array();
    
    foreach($array as $row){
        $template_id = $row['template_id'];
        $question_id = $row['question_id']."_".$row['parent_question_id'];
        if(!isset($responses[$template_id])){
            $responses[$template_id] = array();
        }
        $responses[$template_id][$question_id] = array(
            'additional_information' => try_decrypt($row['additional_information']),
            'answer' => try_decrypt($row['answer']),
        );
    }
    
    return $responses;
}

/**********************************
 * FUNCTION: GET ASSESSMENT FILES *
 **********************************/
function get_assessment_files($tracking_id, $template_id=0, $question_id=0){
    // Open the database connection
    $db = db_open();
    
    // Get all files by tracking ID
    if($template_id == -1 && $question_id == -1){
        $stmt = $db->prepare("SELECT t1.* FROM `questionnaire_files` t1 WHERE t1.`tracking_id`=:tracking_id;");
        $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
    }
    // Get by template and question ID
    else{
        $stmt = $db->prepare("SELECT t1.* FROM `questionnaire_files` t1 WHERE t1.`tracking_id`=:tracking_id and t1.`template_id`=:template_id and t1.`question_id`=:question_id;");
        $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
        $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
        $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
        
    }
    $stmt->execute();

    $files = $stmt->fetchAll();

    // Close the database connection
    db_close($db);
    
    return $files;
}

/**************************************
 * FUNCTION: DISPLAY ASSESSMENT FILES *
 **************************************/
function display_assessment_files($onlyView=false, $token=false){
    global $lang, $escaper;
    
    // Set token from GET param if token is false
    if($token === false){
        $token      = $_GET['token'];
    }
    
    // Get questionnaire tracking info from token
    $questionnaire_tracking_info = get_questionnaire_tracking_by_token($token);

    $files = get_assessment_files($questionnaire_tracking_info['tracking_id']);
    
    $html = "";
    
    foreach($files as $file){
        // If only view, show only file name
        if($onlyView){
            $html .= "
                <li>            
                    <div class=\"file-name\"><a href=\"".$_SESSION['base_url']."/assessments/download.php?id=".$file['unique_name']."\" >".$escaper->escapeHtml($file['name'])."</a></div>
                </li>            
            ";
        }
        else{
            $html .= "
                <li>            
                    <div class=\"file-name\"><a href=\"".$_SESSION['base_url']."/assessments/download.php?id=".$file['unique_name']."\" >".$escaper->escapeHtml($file['name'])."</a></div>
                    <a href=\"#\" class=\"remove-file\" data-id=\"file-upload-0\"><i class=\"fa fa-remove\"></i></a>
                    <input name=\"unique_names[]\" value=\"{$file['unique_name']}\" type=\"hidden\">
                </li>            
            ";
        }
    }
    
    echo $html;
}

/**************************************
 * FUNCTION: DISPLAY ASSESSMENT FILES *
 **************************************/
function display_assessment_question_files($template_id, $question_id, $parent_question_id, $onlyView=false, $token=false){
    global $lang, $escaper;
    
    // Set token from GET param if token is false
    if($token === false)
        $token = $_GET['token'];
    
    // Get questionnaire tracking info from token
    $questionnaire_tracking_info = get_questionnaire_tracking_by_token($token);

    $files = get_assessment_files($questionnaire_tracking_info['tracking_id'], $template_id, $question_id);
    
    $html = "";
    
    foreach($files as $file){
        // If only view, show only file name
        if($onlyView){
            $html .= "
                <li>            
                    <div class=\"file-name\"><a href=\"".$_SESSION['base_url']."/assessments/download.php?id=".$file['unique_name']."\" >".$escaper->escapeHtml($file['name'])."</a></div>
                </li>            
            ";
        }
        else{
            $html .= "
                <li>            
                    <div class=\"file-name\"><a href=\"".$_SESSION['base_url']."/assessments/download.php?id=".$file['unique_name']."\" >".$escaper->escapeHtml($file['name'])."</a></div>
                    <a href=\"#\" class=\"remove-file\" data-id=\"file-upload-0\"><i class=\"fa fa-remove\"></i></a>
                    <input name=\"unique_names[]\" value=\"{$file['unique_name']}\" type=\"hidden\">
                </li>            
            ";
        }
    }
    
    echo $html;
}

/*************************************************************
 * FUNCTION: GET TEMPLATES SENT BY QUESTIONNAIRE TRACKING ID *
 *************************************************************/
function get_questionnaire_templates_sent_by_tracking_id($tracking_id){
    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t3.id template_id, t3.name template_name, t2.contact_id, t4.name contact_name, t4.email contact_email
        FROM `questionnaire_id_template` t1 
            INNER JOIN `questionnaire_tracking` t2 ON t1.questionnaire_id=t2.questionnaire_id and FIND_IN_SET(t2.contact_id, t1.contact_ids)
            INNER JOIN `questionnaire_templates` t3 ON t1.template_id=t3.id
            INNER JOIN `assessment_contacts` t4 ON t4.id=t2.contact_id
        WHERE
            t2.id=:tracking_id
        ORDER BY t3.name;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":tracking_id", $tracking_id);

    $stmt->execute();

    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    foreach($templates as &$template){
        $template['contact_name']   = try_decrypt($template['contact_name']);
        $template['contact_email']  = try_decrypt($template['contact_email']);
    }

    return $templates;
}

/****************************************************************
 * FUNCTION: DISPLAY ONE QUESTION AT CONTACT QUESTIONNAIRE FORM *
 ****************************************************************/
function display_contact_questionnaire_question($question_id, $template_id, $questionIndex, $db_responses, $questionnaire_status, $is_sub_question=false, $parent_question_id=0)
{
    global $lang, $escaper;
    
    $sub_questions = [];
    if($is_sub_question){
        $answer_class = "";
    }
    else
    {
        $answer_class = "answer";
    }
    $question = get_questionnaire_question($question_id);
    echo "<div class='questionnaire-question ".($is_sub_question ? "sub" : "parent")."'>";
        echo "<p>".$escaper->escapeHtml($question['question'])."</p>";
        echo "<div class='questionnaire-answers'>";
        foreach($question['answers'] as $answer){
            // Check if this answer was already posted
            if(isset($db_responses[$template_id][$question_id."_".$parent_question_id]['answer']) && $db_responses[$template_id][$question_id."_".$parent_question_id]['answer'] == $answer['answer']){
                echo "<label><input class='{$answer_class}' data-template='{$template_id}' type='radio' required name='answer[{$template_id}][{$question_id}_{$parent_question_id}]' checked value=\"{$answer['id']}\">&nbsp;&nbsp;<span class='answer-text'>" . $escaper->escapeHtml($answer['answer']) . "</span></label>";
                $sub_questions = $answer['sub_questions'] ? explode(",", $answer['sub_questions']) : [];
            }
            else
            {
                echo "<label><input class='{$answer_class}' data-template='{$template_id}' type='radio' required name='answer[{$template_id}][{$question_id}_{$parent_question_id}]' value=\"{$answer['id']}\">&nbsp;&nbsp;<span class='answer-text'>" . $escaper->escapeHtml($answer['answer']) . "</span></label>";
            }
        }
        echo "<textarea name='additional_information[{$template_id}][{$question_id}_{$parent_question_id}]' style='width: 100%' placeholder='".$escaper->escapeHtml($lang['AdditionalInformation'])."'>".(isset($db_responses[$template_id][$question_id."_".$parent_question_id]['additional_information']) ? $escaper->escapeHtml($db_responses[$template_id][$question_id."_".$parent_question_id]['additional_information']) : "")."</textarea>";
            if($question['has_file']){
                echo "
                    <div class='row-fluid attachment-container'>
                        <div class='pull-left'><strong>".$escaper->escapeHtml($lang['Attachment']).":&nbsp;&nbsp; </strong></div>
                        <div class='pull-left'>
                            <div class=\"file-uploader\">
                                <label for=\"question-file-upload{$template_id}-{$question_id}-{$parent_question_id}\" class=\"btn\">Choose File</label>
                                <span class=\"file-count-html\"> <span class=\"file-count\">0</span> File Added</span>";
                                echo "<p><font size=\"2\"><strong>Max ". round(get_setting('max_upload_size')/1024/1024) ." Mb</strong></font></p>";
                                echo "
                                <ul class=\"exist-files\">
                                    ";
                                    display_assessment_question_files($template_id, $question_id, $parent_question_id, $questionnaire_status);
                                echo "
                                </ul>
                                <ul class=\"file-list\">
                                </ul>
                                <input type=\"file\" id=\"question-file-upload{$template_id}-{$question_id}-{$parent_question_id}\" name=\"question_file[{$template_id}_{$question_id}_{$parent_question_id}][]\" class=\"hidden-file-upload active\" />
                                <input type=\"hidden\" class='file_name' data-file='question_file[{$template_id}_{$question_id}_{$parent_question_id}]' />
                            </div>
                        </div>
                    </div>
                ";
            }
            if(!$is_sub_question){
                echo "<div class='questionnaire-questions-container-by-answer'>";
                    foreach($sub_questions as $index=>$sub_question_id){
                        display_contact_questionnaire_question($sub_question_id, $template_id, $index, $db_responses, $questionnaire_status, true, $question_id);
                    }
                echo "</div>";
            }
        echo "</div>";
        
    echo "</div>";
}

/*****************************************************************
 * FUNCTION: CHECK IF CONTACTER HAS PERMISSION FOR QUESTIONNAIRE *
 *****************************************************************/
function check_contact_permission_for_template($token , $template_id){
    global $escaper, $lang;
    $contact_id = (int)$_SESSION['contact_id'];

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            t1.*
        FROM
            `questionnaire_id_template` t1
            INNER JOIN `questionnaire_tracking` t2 ON t1.questionnaire_id=t2.questionnaire_id AND FIND_IN_SET(t2.contact_id, t1.contact_ids)
        WHERE
            t2.`token`=:token AND
            t2.`contact_id`=:contact_id AND
            t1.template_id=:template_id;
    ");
    $stmt->bindParam(":token", $token, PDO::PARAM_STR);
    $stmt->bindParam(":contact_id", $contact_id, PDO::PARAM_INT);
    $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
    $stmt->execute();

    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // If the query returned a value
    if (!empty($array))
    {
        return true;
    }
    else return false;
    
}

/**********************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE TEMPLATE FOR CONTACTER *
 **********************************************************/
/*function display_contact_questionnaire_template(){
    global $lang, $escaper;
    $token = $_GET['token'];
    $tempalte_id = (int)$_GET['id'];
    
    // Check if contacer has a permission for this template and questionnaire
    if(!check_contact_permission_for_template($token, $tempalte_id))
    {
        echo "<br>";
        echo "<div class='alert alert-error'>".$escaper->escapeHtml($lang['NoPermissionForTemplate'])."</div>";
    }
    else
    {
        
        $questionnaire_and_contact = get_questionnaire_tracking_by_token($token);
        
        echo "
            <h1>".$escaper->escapeHtml($lang['Questionnaire']).": ".$escaper->escapeHtml($questionnaire_and_contact['questionnaire_name'])."</h1>
            <br>
            <h4>".$escaper->escapeHtml($lang['CompleteYourQuestionnaireTemplate'])."</h4>
        ";
        
        $questinnaire = get_questionnaire_by_id($questionnaire_and_contact['questionnaire_id']);

        if(isset($questinnaire['templates'])){
            echo "<ul>";
            foreach($questinnaire['templates'] as $template){
                $link = $_SESSION['base_url']."/assessments/questionnaire.index.php?token=".$token."&page=template&id=".$template['template_id'];
                echo "<li><a href='{$link}'>".$escaper->escapeHtml($template['template_name'])."</a></li>";
            }
            echo "</ul>";
        }
    }
    
}*/

/********************************************************
 * FUNCTION: DISPLAY CONTACT QUESTIONNAIRE LANDING PAGE *
 ********************************************************/
/*function display_contact_questionnaire_index(){
    global $lang, $escaper;
    
    $token = $_GET['token'];
    $questionnaire_and_contact = get_questionnaire_tracking_by_token($token);
    
    echo "
        <h1>".$escaper->escapeHtml($lang['Questionnaire']).": ".$escaper->escapeHtml($questionnaire_and_contact['questionnaire_name'])."</h1>
        <br>
        <h4>".$escaper->escapeHtml($lang['CompleteYourQuestionnaireTemplate'])."</h4>
    ";
    
    $questinnaire = get_questionnaire_by_id($questionnaire_and_contact['questionnaire_id']);

    if(isset($questinnaire['templates'])){
        echo "<ul>";
        foreach($questinnaire['templates'] as $template){
            $link = $_SESSION['base_url']."/assessments/questionnaire.index.php?token=".$token."&page=template&id=".$template['template_id'];
            echo "<li><a href='{$link}'>".$escaper->escapeHtml($template['template_name'])."</a></li>";
        }
        echo "</ul>";
    }
}
*/
/****************************************
 * FUNCTION: DISPLAY CONTACT LOGIN FORM *
 ****************************************/
function display_contact_login($contact_id){
    global $lang, $escaper;
    
    $contact = get_assessment_contact($contact_id);
    
    echo "
        <div class=\"login-wrapper clearfix\">
            <form method=\"post\" action=\"\" class=\"loginForm\">
                
                <table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                    <tbody>
                        <tr>
                            <td colspan=\"2\"><label class=\"login--label\">".$escaper->escapeHtml($lang['Login'])."</label></td>
                        </tr>
                        <tr>
                            <td width=\"30%\"><label for=\"\">Password:&nbsp;</label></td><td class=\"80%\"><input required class=\"form-control input-medium\" name=\"pass\" id=\"pass\" autocomplete=\"off\" type=\"password\"></td>
                        </tr>
                    </tbody>
                </table>
                <div class=\"form-actions\">
                    <button type=\"submit\" name=\"login_for_contact\" class=\"btn btn-primary pull-right\">".$escaper->escapeHtml($lang['Submit'])."</button>
                </div>
            </form>
        </div>    
    ";
}

/***********************************************
 * FUNCTION: DISPLAY SET CONTACT PASSWORD FORM *
 ***********************************************/
function display_set_contact_password($contact_id){
    global $lang, $escaper;
    
    $contact = get_assessment_contact($contact_id);
    echo "
        <div class=\"login-wrapper clearfix\">
            <form method=\"post\" action=\"\" class=\"loginForm\">
                
                <table width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                    <tbody>
                        <tr>
                            <td colspan=\"2\"><label class=\"login--label\">".$escaper->escapeHtml($lang['SetPassword'])."</label></td>
                        </tr>
                        <tr>
                            <td width=\"30%\"><label for=\"\">Password:&nbsp;</label></td><td class=\"80%\"><input required class=\"form-control input-medium\" name=\"pass\" id=\"pass\" autocomplete=\"off\" type=\"password\"></td>
                        </tr>
                        <tr>
                            <td width=\"30%\"><label for=\"\">".$escaper->escapeHtml($lang['ConfirmPassword']).":&nbsp;</label></td><td class=\"80%\"><input required class=\"form-control input-medium\" name=\"confirmPass\" id=\"user\" type=\"password\"></td>
                        </tr>
                    </tbody>
                </table>
                <div class=\"form-actions\">
                    <button type=\"submit\" name=\"set_contact_password\" class=\"btn btn-primary pull-right\">".$escaper->escapeHtml($lang['Submit'])."</button>
                </div>
            </form>
        </div>    
    ";
}

/*****************************************
 * FUNCTION: UPDATE A ASSESSMENT CONTACT *
 *****************************************/
function set_assessment_contact_password($contact_id, $pass){
    // Get contact by contact ID
    $contact = get_assessment_contact($contact_id);
    
    // Check if salt exits
    if($contact['salt']){
        $salt = $contact['salt'];
    }
    else{
        // Create a unique salt for this contact
        $salt = generate_token(20);
    }
    
    // Hash the salt
    $salt_hash = oldGenerateSalt($salt);

    // Generate the password hash
    $hash = generateHash($salt_hash, $pass);

    // Open the database connection
    $db = db_open();
    
    $stmt = $db->prepare("UPDATE `assessment_contacts` SET `password` = :pass, `salt` = :salt WHERE id=:id;");
    $stmt->bindParam(":pass", $hash, PDO::PARAM_LOB);
    $stmt->bindParam(":salt", $salt, PDO::PARAM_STR);
    $stmt->bindParam(":id", $contact_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Close the database connection
    db_close($db);
    
    return true;
}

/*****************************************
 * FUNCTION: PROCESS QUESTIONNAIRE INDEX *
 *****************************************/
function process_questionnaire_index(){
    global $lang, $escaper;
    
    $process = false;
    
    $token =  $_GET['token'];

    // Check if set contact password
    if(isset($_POST['set_contact_password'])){
        $pass           = $_POST['pass'];
        $confirmPass    = $_POST['confirmPass'];
        if($pass != $confirmPass){
            $process = false;
            set_alert(true, "bad", $lang['NoMatchPassword']);
        }else{
            
            $questionnaire_contact = get_questionnaire_tracking_by_token($token);
            
            
            // Create password for assessment contact
            set_assessment_contact_password($questionnaire_contact['contact_id'], $pass);
            
            $_SESSION['contact_id'] = $questionnaire_contact['contact_id'];

            // If encryption is enabled
            if(encryption_extra()){
                //Include the encryption extra
                require_once(realpath(__DIR__ . '/../encryption/index.php'));
            }
            
            $process = true;
            set_alert(true, "good", $lang['SetPasswordSuccess']);
        }
        
    }
    // Check if submitted for login
    elseif(isset($_POST['login_for_contact'])){
        $pass   = $_POST['pass'];
        
        // Check if password is correct
        if(is_valid_contact_user($token, $pass)){
            $questionnaire_contact  = get_questionnaire_tracking_by_token($token);
            $_SESSION['contact_id'] = $questionnaire_contact['contact_id'];
            $_SESSION['token']      = $token;

            // Get base url
            $base_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}";
            $base_url = htmlspecialchars( $base_url, ENT_QUOTES, 'UTF-8' );
            $base_url = pathinfo($base_url)['dirname'];
            $base_url = dirname($base_url);

            // Set the permissions
            $_SESSION['base_url'] = $base_url;
            
            // If encryption is enabled
            if(encryption_extra()){
                //Include the encryption extra
                require_once(realpath(__DIR__ . '/../encryption/index.php'));
            }
        }else{
            set_alert(true, "bad", $lang['InvalidPassword']);
        }
        $process = true;
    }
    // Check if questionnaire form was submitt for draft
    elseif(isset($_POST['draft_questionnaire'])){
        // Check contact authentication
        check_contact_authentication();
      
        if(!check_contact_permission_for_questionnaire($token)){
            set_alert(true, "bad", $lang['NoPermissionForQuestionnaire']);
        }else{
            $process = true;

            // Check if saved successfully
            if(save_questionnaire_response()){
                set_alert(true, "good", $escaper->escapeHtml($lang['QuestionnaireDraftSuccess']));
            }
        }
    }
    // Check if questionnaire form was submitt for complete
    elseif(isset($_POST['complete_questionnaire'])){
        // Check contact authentication
        check_contact_authentication();

        if(!check_contact_permission_for_questionnaire($token)){
            set_alert(true, "bad", $lang['NoPermissionForQuestionnaire']);
        }else{
            $process = true;
            
            // Check if saved successfully
            if(save_questionnaire_response(true)){
                set_alert(true, "good", $escaper->escapeHtml($lang['QuestionnaireCompletedSuccess']));
            }
        }
    }
    
    return $process;
}

/************************************
 * FUNCTION: DELETE ASSESSMENT FILE *
 ************************************/
function delete_assessment_file($file_id){
    // Open the database connection
    $db = db_open();

    // Delete a file from questionnaire_files table
    $stmt = $db->prepare("DELETE FROM `questionnaire_files` WHERE id=:file_id; ");
    $stmt->bindParam(":file_id", $file_id, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/*****************************************
 * FUNCTION: SAVE QUESTIONNAIRE RESPONSE *
 *****************************************/
function save_questionnaire_response($complete=false){
    global $lang, $escaper;

    $token = $_GET['token'];
    
    // Error variable
    $error = false;
    
    // Get tracking by token
    $tracking = get_questionnaire_tracking_by_token($token);
    
    // Check if questionnaire was already completed
    if($tracking['questionnaire_status']){
        return false;
    }
    $tracking_id = $tracking['tracking_id'];
    $company = $tracking['contact_company'];
    // Added contact related company as a tag and get the id
    $company_tag_id = add_tag($company);
    
    $answers = isset($_POST['answer']) ? $_POST['answer'] : array();
    $additional_informations = isset($_POST['additional_information']) ? $_POST['additional_information'] : array();
    $assets_asset_groups = isset($_POST['assets_asset_groups']) ? implode(',', $_POST['assets_asset_groups']) : "";

    // Open the database connection
    $db = db_open();

    // Delete all questionnaire responses by token
    $stmt = $db->prepare("DELETE FROM `questionnaire_responses` WHERE questionnaire_tracking_id=:questionnaire_tracking_id; ");
    $stmt->bindParam(":questionnaire_tracking_id", $tracking_id, PDO::PARAM_INT);
    $stmt->execute();

    foreach($additional_informations as $template_id => $response){
        foreach($response as $question_key => $additional_information){
            if(!isset($answers[$template_id][$question_key]) || empty($answers[$template_id][$question_key])){
                continue;
            }
            $question_id = (int)explode("_", $question_key)[0];
            $parent_question_id = (int)explode("_", $question_key)[1];
            
            $answer_id = $answers[$template_id][$question_key];
            $answer = get_questionnaire_answer_by_id($answer_id);
//            print_r($answer);exit;
            $answer_text = try_encrypt($answer["answer"]);
            $additional_information = try_encrypt($additional_information);
            
            $sql = "INSERT INTO `questionnaire_responses`(`questionnaire_tracking_id`, `template_id`, `question_id`, `parent_question_id`, `additional_information`, `answer`, `submit_risk`, `fail_control`) VALUES(:questionnaire_tracking_id, :template_id, :question_id, :parent_question_id, :additional_information, :answer, :submit_risk, :fail_control);";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(":questionnaire_tracking_id", $tracking_id, PDO::PARAM_INT);
            $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
            $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
            $stmt->bindParam(":parent_question_id", $parent_question_id, PDO::PARAM_INT);
            $stmt->bindParam(":additional_information", $additional_information, PDO::PARAM_STR);
            $stmt->bindParam(":answer", $answer_text, PDO::PARAM_STR);
            $stmt->bindParam(":submit_risk", $answer['submit_risk'], PDO::PARAM_INT);
            $stmt->bindParam(":fail_control", $answer['fail_control'], PDO::PARAM_INT);
            
            $stmt->execute();

            // Save pending risk
            if($complete !== false)
            {
                $answer_detail = get_questionnaire_answer_by_id($answer_id);
                if($answer_detail['tag_ids'])
                {
                    $answer_detail['tag_ids'] .= "," . $company_tag_id;
                }
                else
                {
                    $answer_detail['tag_ids'] = $company_tag_id;
                }
                
                if($answer_detail['submit_risk']){
                    
                    // If an asset was specified in the processed questionnaire
                    // then we use those affected assets and not those on the answer
                    if (!$assets_asset_groups) {
                        $affected_assets = get_assets_and_asset_groups_of_type_as_string($answer_id, 'questionnaire_answer');
                    } else {
                        $affected_assets = $assets_asset_groups;
                    }

                    add_questionnaire_pending_risk($tracking_id, $answer_detail['questionnaire_scoring_id'], $answer_detail['risk_subject'], $answer_detail['risk_owner'], $affected_assets, $additional_information, $answer_detail['tag_ids']);

                    $message = _lang('PendingRiskCreationAuditLog', array(
                        'questionnaire_name' => $tracking['questionnaire_name'],
                        'subject' => truncate_to($answer_detail['risk_subject'], 50)
                    ), false);

                    write_log($tracking_id+1000, $_SESSION['uid'], $message, 'questionnaire_tracking');
                }
            }
        }
    }
    
    // Get questionnaire response percent based on answers
    $percent    = calc_questionnaire_response_percent($token, $answers);

    $sql = "UPDATE `questionnaire_tracking` SET  `affected_assets`=:affected_assets, `percent`=:percent WHERE id=:tracking_id;";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":affected_assets", $assets_asset_groups, PDO::PARAM_STR);
    $stmt->bindParam(":percent", $percent, PDO::PARAM_INT);
    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
    
    // Set percent for this questionnaire
    $stmt->execute();
    
    // Check if user already attached files 
    $unique_names = empty($_POST['unique_names']) ? [] : $_POST['unique_names'];
    
    $files = get_assessment_files($tracking_id, -1, -1);
    
    foreach($files as $file){
        // Check if file is deleted
        if(!in_array($file['unique_name'], $unique_names)){
            delete_assessment_file($file['id']);
        }
    }
        
    
    // Save files
    if(!empty($_FILES['file'])){
        $files = $_FILES['file'];
        $result = upload_questionnaire_files($tracking_id, $files);
        
        // Check if error was happened in uploading files
        if($result !== true && is_array($result)){
            $error = true;
            $error_string = implode(", ", $result);
            set_alert(true, "bad", $error_string);
        }
    }
    
    // Save files
    if(!empty($_FILES['question_file'])){
        $question_files = $_FILES['question_file'];
        
        $files = [];
        foreach($question_files['name'] as $template_question_id => $question_file_names){
            $files[$template_question_id] = array(
                'name' => [],
                'type' => [],
                'tmp_name' => [],
                'error' => [],
                'size' => []
            );
            foreach($question_file_names as $key => $question_file_name){
                $files[$template_question_id]['name'][$key] = $question_files['name'][$template_question_id][$key];
                $files[$template_question_id]['type'][$key] = $question_files['type'][$template_question_id][$key];
                $files[$template_question_id]['tmp_name'][$key] = $question_files['tmp_name'][$template_question_id][$key];
                $files[$template_question_id]['error'][$key] = $question_files['error'][$template_question_id][$key];
                $files[$template_question_id]['size'][$key] = $question_files['size'][$template_question_id][$key];
            }
        }
        foreach($files as $template_question_id => $file){
            $template_id = explode("_", $template_question_id)[0];
            $question_id = explode("_", $template_question_id)[1];
            $parent_question_id = explode("_", $template_question_id)[2];

            $result = upload_questionnaire_files($tracking_id, $file, $template_id, $question_id, $parent_question_id);
            
            // Check if error was happened in uploading files
            if($result !== true && is_array($result)){
                $error = true;
                $error_string = implode(", ", $result);
                set_alert(true, "bad", $error_string);
            }
        }
    }
    
    
    // Check if contact completed questionnaire
    if(!$error && $complete !== false && $percent == 100){
        $sql = "UPDATE `questionnaire_tracking` SET `status`=1 WHERE id=:tracking_id;";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
        
        // Set questionnaire as complete
        $stmt->execute();

        $message = _lang('QuestionnaireCompletedAuditLog', array(
            'questionnaire_name' => $tracking['questionnaire_name'],
            'contact_name' => $tracking['contact_name']
        ), false);
        write_log($tracking_id + 1000, $_SESSION['contact_id'], $message, 'questionnaire_tracking');

        /********** Start sending a notification to contact manager ***********/
            $contact_manager = get_user_by_id($tracking['contact_manager']);
            
            // Create the message body
            $body = get_string_from_template($lang['EmailTemplateCompleteQuestionnaire'], array(
                'conact_name' => $escaper->escapeHtml($tracking['contact_name']),
                'questionnaire_name' => $escaper->escapeHtml($tracking['questionnaire_name']),
            ));
            
            $subject = "Notification of Completed Questionnaire";
            
            // Require the mail functions
            require_once(realpath(__DIR__ . '/../../includes/mail.php'));
            
            // Send the email
            send_email($contact_manager['name'], $contact_manager['email'], $subject, $body);
        /********** End sending a notification to contact manager ***********/
        
    }
    // If questionnaire was saved
    else{
        $message = _lang('QuestionnaireDraftAuditLog', array(
            'questionnaire_name' => $tracking['questionnaire_name'],
            'contact_name' => $tracking['contact_name']
        ), false);
        write_log($tracking_id + 1000, $_SESSION['contact_id'], $message, 'questionnaire_tracking');
    }
    
    // Close the database connection
    db_close($db);
    
    // If process is success
    if(!$error)
        return true;
    // If error was happened
    else
        return false;
}

/****************************************
 * FUNCTION: UPLOAD QUESTIONNAIRE FILES *
 ****************************************/
function upload_questionnaire_files($tracking_id, $files, $template_id=0, $question_id=0, $parent_question_id=0){
    // Open the database connection
    $db = db_open();
    
    // Get the list of allowed file types
    $stmt = $db->prepare("SELECT `name` FROM `file_types`");
    $stmt->execute();

    // Get the result
    $result = $stmt->fetchAll();

    // Create an array of allowed types
    foreach ($result as $key => $row)
    {
        $allowed_types[] = $row['name'];
    }
    
    $errors = array();
    

    foreach($files['name'] as $key => $name){
        if(!$name)
            continue;
            
        $file = array(
            'name' => $files['name'][$key],
            'type' => $files['type'][$key],
            'tmp_name' => $files['tmp_name'][$key],
            'size' => $files['size'][$key],
            'error' => $files['error'][$key],
        );

        // If the file type is appropriate
        if (in_array($file['type'], $allowed_types))
        {
            // Get the maximum upload file size
            $max_upload_size = get_setting("max_upload_size");

            // If the file size is less than max size
            if ($file['size'] < $max_upload_size)
            {
                // If there was no error with the upload
                if ($file['error'] == 0)
                {
                    // Read the file
                    $content = fopen($file['tmp_name'], 'rb');

                    // Create a unique file name
                    $unique_name = generate_token(30);

                    // Store the file in the database
                    $stmt = $db->prepare("INSERT `questionnaire_files` (tracking_id, template_id, question_id, parent_question_id, name, unique_name, type, size, content) VALUES (:tracking_id, :template_id, :question_id, :parent_question_id, :name, :unique_name, :type, :size, :content)");
                    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
                    $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
                    $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
                    $stmt->bindParam(":parent_question_id", $parent_question_id, PDO::PARAM_INT);
                    $stmt->bindParam(":name", $file['name'], PDO::PARAM_STR, 30);
                    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
                    $stmt->bindParam(":type", $file['type'], PDO::PARAM_STR, 30);
                    $stmt->bindParam(":size", $file['size'], PDO::PARAM_INT);
                    $stmt->bindParam(":content", $content, PDO::PARAM_LOB);
                    $stmt->execute();
                }
                // Otherwise
                else
                {
                    switch ($file['error'])
                    {
                        case 1:
                            $errors[] = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                            break;
                        case 2:
                            $errors[] = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                            break;
                        case 3:
                            $errors[] = "The uploaded file was only partially uploaded.";
                            break;
                        case 4:
//                            $errors[] = "No file was uploaded.";
                            break;
                        case 6:
                            $errors[] = "Missing a temporary folder.";
                            break;
                        case 7:
                            $errors[] = "Failed to write file to disk.";
                            break;
                        case 8:
                            $errors[] = "A PHP extension stopped the file upload.";
                            break;
                        default:
                            $errors[] = "There was an error with the file upload.";
                    }
                }
            }
            else $errors[] = "The uploaded file was too big to store in the database.  A SimpleRisk administrator can modify the maximum file upload size under \"File Upload Settings\" under the \"Configure\" menu.  You may also need to modify the 'upload_max_filesize' and 'post_max_size' values in your php.ini file.";
        }
        else $errors[] = "The file type of the uploaded file (" . $file['type'] . ") is not supported.  A SimpleRisk administrator can add it under \"File Upload Settings\" under the \"Configure\" menu.";
    }

    // Close the database connection
    db_close($db);
    
    if($errors)
        return array_unique($errors);
    else
        return true;
}

/*************************************************
 * FUNCTION: CALC QUESTIONNAIRE RESPONSE PERCENT *
 *************************************************/
function calc_questionnaire_response_percent($token, $answers){
    // Total questions to this questionnaire
    $totalQuestions = 0;
    
    // Processed questions
    $processedQuestions = 0;
    
    $tracking_info = get_questionnaire_tracking_by_token($token);

//    $questionnaire_id = $tracking_info['questionnaire_id'];
//    $questinnaire = get_questionnaire_by_id($questionnaire_id);
    $templates = get_questionnaire_templates_sent_by_tracking_id($tracking_info['tracking_id']);
    
    if($templates){
        foreach($templates as $key => $template){

            // Get questions of this template
            $template_id = $template['template_id'];
            $template_in_detail = get_assessment_questionnaire_template_by_id($template['template_id']);
            if(isset($template_in_detail['questions'])){
                $questions = $template_in_detail['questions'];
            }else{
                $questions = [];
            }
            
            foreach($questions as $question){
                $question_id = $question['question_id'];
                if(isset($answers[$template_id][$question_id."_0"]) && $answers[$template_id][$question_id."_0"]){
                    $processedQuestions++;
                }
            }
            
            $totalQuestions += count($questions);
        }
    }
    
    if($totalQuestions == 0){
        $percent = 0;
    }else{
        $percent = round(($processedQuestions / $totalQuestions) * 100);
    }
    
    return $percent;
}

/********************************************
 * FUNCTION: GET CONTACT ID BY UNIQUE TOKEN *
 ********************************************/
function get_questionnaire_contact_id_by_token($token){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT t1.contact_id
        FROM 
            questionnaire_tracking t1
        WHERE
            t1.token = :token
    ";
    
    $stmt = $db->prepare($sql);
    
    $stmt->bindParam(":token", $token, PDO::PARAM_STR, 40);
    $stmt->execute();

    // Get contact ID
    $contact_id = $stmt->fetchColumn(0);

    // Close the database connection
    db_close($db);
    
    return $contact_id;
}

/***********************************************************
 * FUNCTION: GET QUESTIONNAIRE AND CONTACT BY UNIQUE TOKEN *
 ***********************************************************/
function get_questionnaire_tracking_by_token($token){
    return get_questionnaire_tracking_by_token_or_id($token, 'token');
}
function get_questionnaire_tracking_by_id($tracking_id){
    return get_questionnaire_tracking_by_token_or_id($tracking_id, 'tracking_id');
}
function get_questionnaire_tracking_by_token_or_id($value, $type){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT
            t1.id tracking_id,
            t1.token,
            t1.sent_at,
            t1.affected_assets,
            t1.percent response_percent,
            t1.status questionnaire_status,
            t1.approver IS NOT NULL AND t1.approver <> 0 approved,
            t1.approver,
            t1.approved_at,
            t1.pre_population,
            t2.id questionnaire_id,
            t2.name questionnaire_name,
            t3.id contact_id,
            t3.name contact_name,
            t3.company contact_company,
            t3.email contact_email,
            t3.phone contact_phone,
            t3.salt contact_salt,
            t3.manager contact_manager
        FROM 
            questionnaire_tracking t1
            LEFT JOIN  questionnaires t2 on t1.questionnaire_id=t2.id
            LEFT JOIN  assessment_contacts t3 on t1.contact_id=t3.id
        WHERE
            " . ($type==='token' ? "t1.token = :token" : "t1.id = :id") . ";
    ";
    
    $stmt = $db->prepare($sql);
    if ($type==='token')
        $stmt->bindParam(":token", $value, PDO::PARAM_STR, 40);
    else
        $stmt->bindParam(":id", $value, PDO::PARAM_INT);
    $stmt->execute();

    // Get questionnaire and contact 
    $questionnaire_contact = $stmt->fetch();
    
    // Close the database connection
    db_close($db);
    
    $questionnaire_contact['contact_name'] = empty($questionnaire_contact['contact_name']) ? "" : try_decrypt($questionnaire_contact['contact_name']);
    $questionnaire_contact['contact_company'] = empty($questionnaire_contact['contact_company']) ? "" : try_decrypt($questionnaire_contact['contact_company']);
    $questionnaire_contact['contact_email'] = empty($questionnaire_contact['contact_email']) ? "" : try_decrypt($questionnaire_contact['contact_email']);
    $questionnaire_contact['contact_phone'] = empty($questionnaire_contact['contact_phone']) ? "" : try_decrypt($questionnaire_contact['contact_phone']);
    
    return $questionnaire_contact;
}

/***********************************
 * FUNCTION: IS VALID CONTACT USER *
 ***********************************/
function is_valid_contact_user($token, $pass)
{
    $questionnaire_contact = get_questionnaire_tracking_by_token($token);
    $email  = $questionnaire_contact['contact_email'];
    $salt   = $questionnaire_contact['contact_salt'];

    // Hash the salt
    $salt_hash = oldGenerateSalt($salt);
    $providedPassword = generateHash($salt_hash, $pass);

    // Get the stored password
    $storedPassword = $questionnaire_contact['contact_password'];

    // If the passwords are equal
    if ( $providedPassword == $storedPassword)
    {
        return true;
    }
    else return false;
}

/*******************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE RESULTS *
 *******************************************/
function display_questionnaire_results(){
    global $lang;
    global $escaper;

    $tableID = "questionnaire-results-table";
    echo "
        <div class='well' id='questionnaire_result_filter_form'>
            <form method='GET'>
                <div class='row-fluid'>
                    <div class='span3'>
                        <div class='well'>
                            <h4>".$escaper->escapeHtml($lang['Company']).":</h4>
                            <input type='text' id='company' >
                        </div>
                    </div>
                    <div class='span3'>
                        <div class='well'>
                            <h4>".$escaper->escapeHtml($lang['Contact']).":</h4>
                            <input type='text' id='contact' >
                        </div>
                    </div>
                    <div class='span3'>
                        <div class='well'>
                            <h4>".$escaper->escapeHtml($lang['DateSent']).":</h4>
                            <input type='text' id='date_sent' class='datepicker'>
                        </div>
                    </div>
                    <div class='span3'>
                        <div class='well'>
                            <h4>".$escaper->escapeHtml($lang['Status']).":</h4>
                            <select id='status'>
                                <option value='all'>".$escaper->escapeHtml($lang['ALL'])."</option>
                                <option value='0'>".$escaper->escapeHtml($lang['Incomplete'])."</option>
                                <option value='1'>".$escaper->escapeHtml($lang['Completed'])."</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    ";
    
    echo "
        <script>
            $(\".datepicker\").datepicker();
            var form = $(\"#questionnaire_result_filter_form\");
            $(document).ready(function(){
                $(\"#questionnaire_result_filter_form input, #questionnaire_result_filter_form select\").change(function(){
                    $(\"#{$tableID}\").DataTable().draw();
                })
            })
        </script>
    ";
    
    echo "
        <table class=\"table risk-datatable table-bordered table-striped table-condensed  \" width=\"100%\" id=\"{$tableID}\" >
            <thead >
                <tr>
                    <th >".$escaper->escapeHtml($lang['QuestionnaireName'])."</th>
                    <th >".$escaper->escapeHtml($lang['Company'])."</th>
                    <th >".$escaper->escapeHtml($lang['Contact'])."</th>
                    <th width='150px'>".$escaper->escapeHtml($lang['PercentCompleted'])."</th>
                    <th width='100px'>".$escaper->escapeHtml($lang['DateSent'])."</th>
                    <th width='100px'>".$escaper->escapeHtml($lang['Status'])."</th>
                    <th width='100px'>".$escaper->escapeHtml($lang['Approved'])."</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <br>
        <script>
            
            var pageLength = 10;
            var datatableInstance = $('#{$tableID}').DataTable({
                bFilter: false,
                bLengthChange: false,
                processing: true,
                serverSide: true,
                bSort: true,
                pagingType: 'full_numbers',
                dom : 'flrtip',
                pageLength: pageLength,
                dom : 'flrti<\"#view-all.view-all\">p',
                order: [[4, 'desc']],
                ajax: {
                    url: BASE_URL + '/api/assessment/questionnaire/results/dynamic',
                    data: function(d){
                        d.company   = $('#company').val();
                        d.contact   = $('#contact').val();
                        d.date_sent = $('#date_sent').val();
                        d.status    = $('#status').val();
                    },
                }
            });
            
            // Add paginate options
            datatableInstance.on('draw', function(e, settings){
                $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
            })
            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var oSettings =  datatableInstance.settings();
                oSettings[0]._iDisplayLength = -1;
                datatableInstance.draw()
                $(this).addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  datatableInstance.settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    datatableInstance.draw()
                }
                
            })
            
        </script>
    ";
    

    // MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
    echo "
        <div id=\"aseessment-contact--delete\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
          <div class=\"modal-body\">

            <form class=\"\" action=\"\" method=\"post\">
              <div class=\"form-group text-center\">
                <label for=\"\">".$escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisContact'])."</label>
              </div>

              <input type=\"hidden\" name=\"contact_id\" value=\"\" />
              <div class=\"form-group text-center control-delete-actions\">
                <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                <button type=\"submit\" name=\"delete_contact\" class=\"delete_control btn btn-danger\">".$escaper->escapeHtml($lang['Yes'])."</button>
              </div>
            </form>

          </div>
        </div>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.contact-delete-btn', function(){
                \$('#aseessment-contact--delete [name=contact_id]').val(\$(this).data('id'));
            })
        </script>
    ";
}

/*****************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE TRAIL *
 *****************************************/
function display_questionnaire_trail(){
    global $lang;
    global $escaper;
    
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    echo "<h4>".$escaper->escapeHtml($lang['QuestionnaireAuditTrail'])."</h4>";
    
    echo "<div class=\"audit-option-container\">";
        echo "
            
            <div class=\"audit-select-folder\">
                <select name=\"days\" id=\"days\" onchange=\"javascript: submit()\">
                    <option value=\"7\" ".(($days == 7) ? " selected" : "").">Past Week</option>
                    <option value=\"30\" ".(($days == 30) ? " selected" : "").">Past Month</option>
                    <option value=\"90\" ".(($days == 90) ? " selected" : "").">Past Quarter</option>
                    <option value=\"180\" ".(($days == 180) ? " selected" : "").">Past 6 Months</option>
                    <option value=\"365\" ".(($days == 365) ? " selected" : "").">Past Year</option>
                    <option value=\"3650\" ".(($days == 3650) ? " selected" : "").">All Time</option>
                </select>
            </div>
        ";
        // If import/export extra is enabled and admin user, shows export audit log button
        if (import_export_extra() && is_admin())
        {
            // Include the Import-Export Extra
            require_once(realpath(__DIR__ . '/../import-export/index.php'));

            display_audit_download_btn();
        }
        echo "<div class=\"clearfix\"></div>";
    echo "</div>";
    
    echo "
        <script>
            \$('#days').change(function(){
                document.location.href = BASE_URL + '/assessments/questionnaire_trail.php?days=' + \$(this).val();
            })
        </script>
    ";
    
    get_audit_trail_html(NULL, $days, ['contact', 'questionnaire_question', 'questionnaire_template', 'questionnaire', 'questionnaire_tracking', 'questionnaire_template', 'questionnaire_question']);
}

/*******************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE RESULTS *
 *******************************************/
function get_assessment_questionnaire_results($start=0, $length=-1, $filters=false, $columnName=false, $columnDir=false){
    // Open the database connection
    $db = db_open();
    
    $sql = "
        SELECT
            SQL_CALC_FOUND_ROWS t1.questionnaire_id,
            t1.token,
            t1.percent,
            t1.status tracking_status,
            t1.sent_at,
            t1.approver IS NOT NULL AND t1.approver <> 0 approved,
            t2.name questionnaire_name,
            t3.company contact_company,
            t3.name contact_name,
            t3.email contact_email
        FROM `questionnaire_tracking` t1 
            INNER JOIN `questionnaires` t2 ON t1.questionnaire_id=t2.id
            LEFT JOIN `assessment_contacts` t3 ON t1.contact_id=t3.id  
    ";
    if($filters !== false && is_array($filters)){
        $wheres = array();
//        if($filters['company']){
//            $wheres[] = "t3.company like :company";
//        }
//        if($filters['contact']){
//            $wheres[] = "t3.name like :contact";
//        }
        if($filters['date_sent']){
            $wheres[] = "t1.sent_at like :date_sent";
        }
        if($filters['status'] != "all"){
            $wheres[] = "t1.status=:status";
        }
        if($wheres){
            $sql .= " WHERE ". implode(" and ", $wheres) . " ";
        }
    }
    
    if($columnName == "questionnaire_name"){
        $sql .= " ORDER BY t2.name {$columnDir} ";
    }
    elseif($columnName == "company"){
        $sql .= " ORDER BY t3.company {$columnDir} ";
    }
    elseif($columnName == "contact"){
        $sql .= " ORDER BY t3.name {$columnDir} ";
    }
    elseif($columnName == "percent"){
        $sql .= " ORDER BY t1.percent {$columnDir} ";
    }
    elseif($columnName == "date_sent"){
        $sql .= " ORDER BY t1.sent_at {$columnDir} ";
    }
    elseif($columnName == "status"){
        $sql .= " ORDER BY t1.status {$columnDir} ";
    }
    elseif($columnName == "approved"){
        $sql .= " ORDER BY approved {$columnDir} ";
    }
    else{
        $sql .= " ORDER BY t1.sent_at DESC ";
    }
    
    
//    if($length != -1){
//        $sql .= " LIMIT {$start}, {$length}; ";
//    }

    $stmt = $db->prepare($sql);

    if($filters !== false && is_array($filters)){
//        if($filters['company']){
//            $filterCompany = "%".$filters['company']."%";
//            $stmt->bindParam(":company", $filterCompany, PDO::PARAM_STR, 100);
//        }
//        if($filters['contact']){
//            $filterContact = "%".$filters['contact']."%";
//            $stmt->bindParam(":contact", $filterContact, PDO::PARAM_STR, 100);
//        }
        if($filters['date_sent']){
            $filterDateSent = "%".$filters['date_sent']."%";
            $stmt->bindParam(":date_sent", $filterDateSent, PDO::PARAM_STR, 100);
        }
        if($filters['status'] != "all"){
            $filters['status'] = (int)$filters['status'];
            $stmt->bindParam(":status", $filters['status'], PDO::PARAM_INT);
        }
    }

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();
    
    $filtered_total_results = [];
    foreach($array as &$row){
        $row['contact_company'] = try_decrypt($row['contact_company']);
        $row['contact_name'] = try_decrypt($row['contact_name']);
        $row['contact_email'] = try_decrypt($row['contact_email']);
        
        if(!empty($filters['company']) && stripos($row['contact_company'], $filters['company'])===false){
            continue;
        }
        if(!empty($filters['contact']) && stripos($row['contact_name'], $filters['contact'])===false){
            continue;
        }
        $filtered_total_results[] = $row;
    }
    
    $recordsTotal = count($filtered_total_results);
    
    $filtered_results = [];
    // If all is not selected, show by page limit
    if($length != -1)
    {
        $filtered_results = array_slice($filtered_total_results, $start, $length);
    }
    // If all is selected, show all
    else
    {
        $filtered_results = $filtered_total_results;
    }
    


//    $stmt = $db->prepare("SELECT FOUND_ROWS();");
//    $stmt->execute();
//    $recordsTotal = $stmt->fetchColumn();
    
    // Close the database connection
    db_close($db);
    
    return array($recordsTotal, $filtered_results);
}

/*******************************************************
 * FUNCTION: DISPLAY ONE QUESTIONNAIRE QUESTION RESULT *
 *******************************************************/
function display_questionniare_question_fullview($question_id, $template_id, $questionIndex, $db_responses, $is_sub_question=false, $parent_question_id=0, $token=false)
{
    global $lang, $escaper;
    
    // Set token from GET param if token is false
    if($token === false)
        $token = $_GET['token'];
    
    $sub_questions = [];

    $question = get_questionnaire_question($question_id);
    echo "<div class='questionnaire-question ".($is_sub_question ? "sub" : "parent")."'>";
        echo "<p class='question-title'>".$escaper->escapeHtml($question['question'])."</p>";
        echo "<div class='questionnaire-answers left-offset-30'>";
            echo "<p>- Answers</p>";
            echo "<ul>";
            foreach($question['answers'] as $answer){
                // Check if this answer was already posted
                if(isset($db_responses[$template_id][$question_id."_".$parent_question_id]['answer']) && $db_responses[$template_id][$question_id."_".$parent_question_id]['answer'] == $answer['answer']){
                    echo "<li><strong class='answer-text'>".$escaper->escapeHtml($answer['answer'])."</strong></li>";
                    $sub_questions = $answer['sub_questions'] ? explode(",", $answer['sub_questions']) : [];
                }
                else
                {
                    echo "<li><span class='answer-text'>".$escaper->escapeHtml($answer['answer'])."</span></li>";
                }
            }
            echo "</ul>";

            if(!empty($db_responses[$template_id][$question_id."_".$parent_question_id]['additional_information'])){
                echo "<p>- ".$escaper->escapeHtml($lang['AdditionalInformation']).":</p>";
                
                echo "<p>".(isset($db_responses[$template_id][$question_id."_".$parent_question_id]['additional_information']) ? $escaper->escapeHtml($db_responses[$template_id][$question_id."_".$parent_question_id]['additional_information']) : "")."</p>";
            }
            if($question['has_file']){
                echo "
                    <div class='row-fluid attachment-container'>
                        <p >- ".$escaper->escapeHtml($lang['AttachmentFiles']).":&nbsp;&nbsp; </p>
                        <div style='padding-left: 10px'>
                            <div class=\"file-uploader\">
                                <ul class=\"exist-files\">
                                    ";
                                    display_assessment_question_files($template_id, $question_id, $parent_question_id, true, $token);
                                echo "
                                </ul>
                            </div>
                        </div>
                    </div>
                ";
            }
            
            if(!$is_sub_question){
                echo "<div class='questionnaire-questions-container-by-answer'>";
                    foreach($sub_questions as $index=>$sub_question_id){
                        display_questionniare_question_fullview($sub_question_id, $template_id, $index, $db_responses, true, $question_id, $token);
                    }
                echo "</div>";
            }

        echo "</div>";

    echo "</div>";
}

/***************************************************************
 * FUNCTION: DISPLAY ONE QUESTIONNAIRE QUESTION COMPARE RESULT *
 ***************************************************************/
function display_questionniare_question_compareview($token1, $token2, $question_id, $template_id, $questionIndex, $db_responses1, $db_responses2, $is_sub_question=false, $parent_question_id=0)
{
    global $lang, $escaper;
    
    if($is_sub_question){
        $left_offset_class = "left-offset-60";
    }else{
        $left_offset_class = "left-offset-30";
    }
    
    $question = get_questionnaire_question($question_id);
    echo "<div class=''>";
        // Question title
        echo "<div class='row-fluid'>";
            echo "<div class='span6'>";
                if($db_responses1 !== false){
                    echo "<p class='question-title questionnaire-question left ".($is_sub_question ? "sub" : "parent")."'>".$escaper->escapeHtml($question['question'])."</p>";
                }else{
                    echo "&nbsp;";
                }
            echo "</div>";
            echo "<div class='span6'>";
                if($db_responses2 !== false){
                    echo "<p class='question-title questionnaire-question right ".($is_sub_question ? "sub" : "parent")."'>".$escaper->escapeHtml($question['question'])."</p>";
                }else{
                    echo "&nbsp;";
                }
            echo "</div>";
        echo "</div>";

        $answer1 = empty($db_responses1[$template_id][$question_id."_".$parent_question_id]['answer']) ? "" : $db_responses1[$template_id][$question_id."_".$parent_question_id]['answer'];
        $answer2 = empty($db_responses2[$template_id][$question_id."_".$parent_question_id]['answer']) ? "" : $db_responses2[$template_id][$question_id."_".$parent_question_id]['answer'];
        if($answer1 == $answer2){
            $is_equal_answer = true;
        }else{
            $is_equal_answer = false;
        }
        
            echo "<div class='row-fluid'>";
                echo "<div class='span6'>";
                    if($db_responses1 !== false){
                        echo "<div class='{$left_offset_class}'>";
                            echo "<p>- Answers</p>";
                            echo "<ul>";
                            
                            $sub_questions1 = [];
                            foreach($question['answers'] as $answer){
                                // Check if this answer was already posted
                                if($answer1 == $answer['answer']){
                                    if($is_equal_answer){
                                        echo "<li><strong class='answer-text '>".$escaper->escapeHtml($answer['answer'])."</strong></li>";
                                    }else{
                                        echo "<li class='differency'><strong class='answer-text'>".$escaper->escapeHtml($answer['answer'])."</strong></li>";
                                    }
                                    $sub_questions1 = $answer['sub_questions'] ? explode(",", $answer['sub_questions']) : [];
                                }
                                else
                                {
                                    echo "<li><span class='answer-text'>".$escaper->escapeHtml($answer['answer'])."</span></li>";
                                }
                            }
                            echo "</ul>";
                        echo "</div>";
                    }
                    else{
                        echo "&nbsp;";
                    }
                echo "</div>";
                echo "<div class='span6'>";
                    if($db_responses2 !== false){
                        echo "<div class='{$left_offset_class}'>";
                            echo "<p>- Answers</p>";
                            echo "<ul>";
                            
                            $sub_questions2 = [];
                            foreach($question['answers'] as $answer){
                                // Check if this answer was already posted
                                if($answer2 == $answer['answer']){
                                    if($is_equal_answer){
                                        echo "<li><strong class='answer-text'>".$escaper->escapeHtml($answer['answer'])."</strong></li>";
                                    }else{
                                        echo "<li class='differency'><strong class='answer-text'>".$escaper->escapeHtml($answer['answer'])."</strong></li>";
                                    }
                                    $sub_questions2 = $answer['sub_questions'] ? explode(",", $answer['sub_questions']) : [];
                                }
                                else
                                {
                                    echo "<li><span class='answer-text'>".$escaper->escapeHtml($answer['answer'])."</span></li>";
                                }
                            }
                            echo "</ul>";
                        echo "</div>";
                    }else{
                        echo "&nbsp;";
                    }
                echo "</div>";
            echo "</div>";

            $additional_information1 = empty($db_responses1[$template_id][$question_id."_".$parent_question_id]['additional_information']) ? "" : $db_responses1[$template_id][$question_id."_".$parent_question_id]['additional_information'];
            $additional_information2 = empty($db_responses2[$template_id][$question_id."_".$parent_question_id]['additional_information']) ? "" : $db_responses2[$template_id][$question_id."_".$parent_question_id]['additional_information'];
            if($additional_information1 == $additional_information2){
                $differency_class = "";
            }else{
                $differency_class = "differency";
            }
            
            // Show if one additional information exists at least
            if($additional_information1 || $additional_information2){
                echo "<div class='row-fluid'>";
                    echo "<div class='span6'>";
                        echo "<div class='{$left_offset_class}'>";
                        if($additional_information1){
                            echo "<p>- ".$escaper->escapeHtml($lang['AdditionalInformation']).":</p>";
                            
                            echo "<p class='{$differency_class}'>".$escaper->escapeHtml($additional_information1)."</p>";
                        }else{
                            echo "&nbsp;";
                        }
                        echo "</div>";
                    echo "</div>";
                    echo "<div class='span6'>";
                        echo "<div class='{$left_offset_class}'>";
                        if($additional_information2){
                            echo "<p>- ".$escaper->escapeHtml($lang['AdditionalInformation']).":</p>";
                            
                            echo "<p class='{$differency_class}'>".$escaper->escapeHtml($additional_information2)."</p>";
                        }else{
                            echo "&nbsp;";
                        }
                        echo "</div>";
                    echo "</div>";
                echo "</div>";
            }
            
            // Shows files if question was set to have attach files
            if($question['has_file']){
                echo "<div class='row-fluid'>";
                    echo "<div class='span6'>";
                        if( $db_responses1 !== false){
                            echo "
                                <div class='row-fluid attachment-container {$left_offset_class}'>
                                    <p >- ".$escaper->escapeHtml($lang['AttachmentFiles']).":&nbsp;&nbsp; </p>
                                    <div style='padding-left: 10px'>
                                        <div class=\"file-uploader\">
                                            <ul class=\"exist-files\">
                                                ";
                                                display_assessment_question_files($template_id, $question_id, $parent_question_id, true, $token1);
                                            echo "
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            ";
                        }else{
                            echo "&nbsp;";
                        }
                    echo "</div>";
                    echo "<div class='span6'>";
                        if($db_responses2 !== false){
                            echo "
                                <div class='row-fluid attachment-container {$left_offset_class}'>
                                    <p >- ".$escaper->escapeHtml($lang['AttachmentFiles']).":&nbsp;&nbsp; </p>
                                    <div style='padding-left: 10px'>
                                        <div class=\"file-uploader\">
                                            <ul class=\"exist-files\">
                                                ";
                                                display_assessment_question_files($template_id, $question_id, $parent_question_id, true, $token2);
                                            echo "
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            ";
                        }else{
                            echo "&nbsp;";
                        }
                    echo "</div>";
                echo "</div>";
            }
            
            if(!$is_sub_question){
                echo "<div class='questionnaire-questions-container-by-answer'>";
                if($is_equal_answer){
                    foreach($sub_questions1 as $index=>$sub_question_id){
                        display_questionniare_question_compareview($token1, $token2, $sub_question_id, $template_id, $index, $db_responses1, $db_responses2, true, $question_id);
                    }
                }else{
                    foreach($sub_questions1 as $index=>$sub_question_id){
                        display_questionniare_question_compareview($token1, $token2, $sub_question_id, $template_id, $index, $db_responses1, false, true, $question_id);
                    }
                    foreach($sub_questions2 as $index=>$sub_question_id){
                        display_questionniare_question_compareview($token1, $token2, $sub_question_id, $template_id, $index, false, $db_responses2, true, $question_id);
                    }
                    
                }
                echo "</div>";
            }


    echo "</div>";
}

/*************************************************
 * FUNCTION: GET TRACKINGS MATCHING TEMPLATE IDS *
 *************************************************/
function get_trackings_by_templates($template_ids)
{ 
    // Open the database connection
    $db = db_open();

    $template_id_set = implode(",", $template_ids);
    $havings = [];
    foreach($template_ids as $template_id)
    {
        $template_id = (int)$template_id;
        $havings[] = "FIND_IN_SET({$template_id}, template_ids)";
    }
    
    if($havings){
        $havings_query = " HAVING ".implode(" AND ", $havings);
    }
    else{
        $havings_query = "";
    }

    $sql = "
        SELECT
            t1.*, t2.token, t2.sent_at
        FROM
        (
            SELECT
                t1.questionnaire_id, t3.name questionnaire_name, t2.id contact_id, t2.name contact_name, group_concat(distinct t1.template_id) template_ids
            FROM
                questionnaire_id_template t1
                LEFT JOIN assessment_contacts t2 ON FIND_IN_SET(t2.id, t1.contact_ids)
                LEFT JOIN questionnaires t3 ON t1.questionnaire_id=t3.id
            WHERE
                FIND_IN_SET(t1.template_id, :template_id_set) 
            GROUP BY
                t1.questionnaire_id, t2.id
            {$havings_query}
        ) t1 
        INNER JOIN questionnaire_tracking t2 ON t1.questionnaire_id=t2.questionnaire_id AND t1.contact_id=t2.contact_id
        ORDER BY
            t1.questionnaire_name, t1.contact_name
    ";

    $stmt = $db->prepare($sql);

    $stmt->bindParam(":template_id_set", $template_id_set, PDO::PARAM_STR);

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);
    
    foreach($array as &$row){
        $row['contact_name'] = try_decrypt($row['contact_name']);
    }
    
    // Return the pending risks
    return $array;
    
}

/*****************************************************
 * FUNCTION: DISPLAY COMPARING QUESTIONNAIRE RESULTS *
 *****************************************************/
function display_compare_questionnaire_results($templates)
{
    global $lang, $escaper;
    
    $token1 = empty($_GET['token1']) ? "" : $_GET['token1'];
    $token2 = empty($_GET['token2']) ? "" : $_GET['token2'];
    $template_ids = explode(",", $templates);
    
    $trackings = get_trackings_by_templates($template_ids);
    
    echo "
        <div class='well'>
            <form method='GET'>
                <input type='hidden' name='templates' value='".$escaper->escapeHtml($templates)."'>
                <h2>".$lang["CompareResults"]." <button class='btn pull-right'>".$escaper->escapeHtml($lang['Compare'])."</button></h2>
                <br>
                <h5>".$lang['SelectQuestionnairesCompare']."</h5>
                <div class='row-fluid'>
                    <div class='span6'>
                        <select required class='full-width' name='token1'>
                            <option value=''>--</option>";
                            foreach($trackings as $tracking)
                            {
                                if($tracking['token'] == $token1){
                                    echo "<option value='{$tracking['token']}' selected>".$escaper->escapeHtml($tracking['questionnaire_name'])." by ".$escaper->escapeHtml($tracking['contact_name']) ." at ".$tracking['sent_at']."</option>";
                                }else{
                                    echo "<option value='{$tracking['token']}' >".$escaper->escapeHtml($tracking['questionnaire_name'])." by ".$escaper->escapeHtml($tracking['contact_name']) ." at ".$tracking['sent_at']."</option>";
                                }
                            }
                        echo "</select>
                    </div>
                    <div class='span6'>
                        <select required class='full-width' name='token2'>
                            <option value=''>--</option>";
                            foreach($trackings as $tracking)
                            {
                                if($tracking['token'] == $token2){
                                    echo "<option title='' value='{$tracking['token']}' selected>".$escaper->escapeHtml($tracking['questionnaire_name'])." by ".$escaper->escapeHtml($tracking['contact_name']) ." at ".$tracking['sent_at']."</option>";
                                }else{
                                    echo "<option value='{$tracking['token']}' >".$escaper->escapeHtml($tracking['questionnaire_name'])." by ".$escaper->escapeHtml($tracking['contact_name']) ." at ".$tracking['sent_at']."</option>";
                                }
                            }
                        echo "</select>
                    </div>
                </div>
            </form>
        </div>
    ";
    
    // If token1 or token2 no slected, stop compare
    if(!$token1 || !$token2)
    {
        return;
    }
    
    $questionnaire_tracking_info1 = get_questionnaire_tracking_by_token($token1);
    $questionnaire_tracking_info2 = get_questionnaire_tracking_by_token($token2);
    $templates = get_questionnaire_templates_sent_by_tracking_id($questionnaire_tracking_info1['tracking_id']);
    $db_responses1 = get_questionnaire_responses($token1);
    $db_responses2 = get_questionnaire_responses($token2);
    
    echo "
        <div class=\"well\">
            <div class='questionnaire-compare-result-container'>
                <div class='row-fluid'>
                    <div class='span6'>
                        <h2>".$escaper->escapeHtml($lang['Questionnaire']).": ".$escaper->escapeHtml($questionnaire_tracking_info1['questionnaire_name'])."</h2>
                    </div>
                    <div class='span6'>
                        <h2>".$escaper->escapeHtml($lang['Questionnaire']).": ".$escaper->escapeHtml($questionnaire_tracking_info2['questionnaire_name'])."</h2>
                    </div>
                </div>
                ";

                if($templates){
                    $templateIndex = 1;
                    foreach($templates as $key => $template){
                        echo "<div class='row-fluid'>";
                            echo "<div class='span6'>";
                                echo "<h3>".$templateIndex.".&nbsp;".$escaper->escapeHtml($template['template_name'])."</h3>";
                            echo "</div>";
                            echo "<div class='span6'>";
                                echo "<h3>".$templateIndex.".&nbsp;".$escaper->escapeHtml($template['template_name'])."</h3>";
                            echo "</div>";
                        echo "</div>";
                      

                        // Get questions of this template
                        $template_id = $template['template_id'];
                        $template_in_detail = get_assessment_questionnaire_template_by_id($template['template_id']);
                        if(isset($template_in_detail['questions'])){
                            $questions = $template_in_detail['questions'];
                        }else{
                            $questions = [];
                        }
                        
                        foreach($questions as $questionIndex => &$question)
                        {
                            $question_id = $question['question_id'];
                            display_questionniare_question_compareview($token1, $token2, $question_id, $template_id, $questionIndex, $db_responses1, $db_responses2, false, 0);
                        }
                        $templateIndex++;
                    }
                    
                    echo "<div class='row-fluid'>";
                        echo "<div class='span6'>";
                            if(get_assessment_files($questionnaire_tracking_info1['tracking_id'])){
                                echo "
                                    <div class='row-fluid attachment-container'>
                                        <div ><strong>".$escaper->escapeHtml($lang['AttachmentFiles']).":&nbsp;&nbsp; </strong></div>
                                        <div style='padding-left: 10px;'>
                                            <div class=\"file-uploader\">
                                                <ul class=\"exist-files\">
                                                    ";
                                                    display_assessment_files(true, $token1);
                                                echo "
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                ";
                            }else{
                                echo "&nbsp;";
                            }
                        echo "</div>";
                        echo "<div class='span6'>";
                            if(get_assessment_files($questionnaire_tracking_info1['tracking_id'])){
                                echo "
                                    <div class='row-fluid attachment-container'>
                                        <div ><strong>".$escaper->escapeHtml($lang['AttachmentFiles']).":&nbsp;&nbsp; </strong></div>
                                        <div style='padding-left: 10px;'>
                                            <div class=\"file-uploader\">
                                                <ul class=\"exist-files\">
                                                    ";
                                                    display_assessment_files(true, $token1);
                                                echo "
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                ";
                            }else{
                                echo "&nbsp;";
                            }
                        echo "</div>";
                    echo "</div>";

                }
            echo "</div>";
        
        echo "</div>";
}

/*************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE RISK ANALYSIS *
 *************************************************/
function display_questionnaire_risk_analysis()
{
    global $lang, $escaper;
    
    $report = !empty($_GET['report']) ? $_GET['report'] : "all-risks";
    $group = !empty($_GET['group']) ? $_GET['group'] : "";
    
    echo "
        <div class='well'>
            <form name='analysisform' id='analysisform' method='GET'>
                <input type='hidden' name='report' >
                <input type='hidden' name='group' >
            </form>
            <table id='analysis-header' cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                <tbody>
                    <tr>
                        <td>". $escaper->escapeHtml($lang['Report']) .":&nbsp;&nbsp;</td>
                        <td>
                            <select id=\"report-dropdown\" name=\"report\" onchange=\"javascript: submit()\" style=\"margin-bottom: 0px\">
                                <option ". ($report=="all-risks" ? " selected " : "") ."  value=\"all-risks\">". $escaper->escapeHtml($lang['AllRisksFromQuestionnaires']) ."</option>
                                <option ". ($report=="added-risks" ? " selected " : "") ." value=\"added-risks\">". $escaper->escapeHtml($lang['AllAddedRisksFromQuestionnaires']) ."</option>
                                <option ". ($report=="pending-risks" ? " selected " : "") ." value=\"pending-risks\">". $escaper->escapeHtml($lang['AllPendingRisksFromQuestionnaires']) ."</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class='well'>
            <div id='analysis-content-container'>
                <div class='row-fluid'>
                    <div class='span12'>
                        <table cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                            <tbody>
                                <tr>
                                    <td>". $escaper->escapeHtml($lang['GroupBy']) .":&nbsp;&nbsp;</td>
                                    <td>
                                        <select id=\"group-dropdown\" name=\"group\" onchange=\"javascript: submit()\" style=\"margin-bottom: 0px\">
                                            <option ". ($group=="" ? " selected " : "") ."  value=\"\">--</option>
                                            <option ". ($group=="risk-score" ? " selected " : "") ."  value=\"risk-score\">". $escaper->escapeHtml($lang['RiskScore']) ."</option>
                                            <option ". ($group=="questionnaire" ? " selected " : "") ."  value=\"questionnaire\">". $escaper->escapeHtml($lang['Questionnaire']) ."</option>
                                            <option ". ($group=="company" ? " selected " : "") ."  value=\"company\">". $escaper->escapeHtml($lang['Company']) ."</option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <br>
                ";
                
                display_questionnaire_analysis_tables($report, $group);
                
                echo "
            </div>
        </div>
    ";
    
    echo "
        <div id=\"pending-risk-detail-modal\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
          <div class=\"modal-body\">

            <form class=\"\" action=\"\" method=\"post\">

              <div class=\"form-group content-container\">
              </div>
              
              <div class='button-container text-center'>
                <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">". $escaper->escapeHtml($lang['Close']) ."</button>
              </div>
            </form>

          </div>
        </div>
    ";
    
    echo "
        <style>
            #pending-risk-detail-modal .form-actions{
                text-align: center;
            }
        </style>
    ";
    
    echo "
        <script>
            function submit()
            {
                $('[name=report]', '#analysisform').val($('#report-dropdown').val());
                $('[name=group]', '#analysisform').val($('#group-dropdown').val());
                document.analysisform.submit()
            }
            function createTagsInstance(\$e)
            {
                \$e.selectize({
                    plugins: ['remove_button', 'restore_on_backspace'],
                    delimiter: '+++',
                    create: function (input){
                        return {value: 'new_tag_' + input, label:input};
                    },
                    valueField: 'value',
                    labelField: 'label',
                    searchField: 'label',
                    preload: true,
                    onInitialize: function() {
                        var json_string = this.\$input.attr('data-selectize-value');
                        if(!json_string)
                            return;
                        var existingOptions = JSON.parse(json_string);
                        var self = this;
                        if(Object.prototype.toString.call( existingOptions ) === \"[object Array]\") {
                            existingOptions.forEach( function (existingOption) {
                                self.addOption(existingOption);
                                self.addItem(existingOption[self.settings.valueField]);
                            });
                        }
                        else if (typeof existingOptions === 'object') {
                            self.addOption(existingOptions);
                            self.addItem(existingOptions[self.settings.valueField]);
                        }
                    },
                    load: function(query, callback) {
                        if (query.length) return callback();
                        $.ajax({
                            url: BASE_URL + '/api/management/tag_options_of_type?type=risk',
                            type: 'GET',
                            dataType: 'json',
                            error: function() {
                                console.log('Error loading!');
                                callback();
                            },
                            success: function(res) {
                                callback(res.data);
                            }
                        });
                    }
                });
            }
            $(document).ready(function(){
                $('body').on('click', '.view-detail-risk', function(){
                    var modalContainer = $('#pending-risk-detail-modal');
                    $('#pending-risk-detail-modal .content-container').html($(this).closest('td').find('.inner-content-container').html())
                    
                    $('.form-actions', modalContainer).append('<button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">Cancel</button>');
                    if($(this).data('status') == '1'){
                        $('.form-actions', modalContainer).hide()
                        $('.button-container', modalContainer).show()
                    }else{
                        $('.form-actions', modalContainer).show()
                        $('.button-container', modalContainer).hide()
                    }
                    createTagsInstance($('.tags', modalContainer));
                    
                    var assets_and_asset_groups = [];
                    $.ajax({
                        url: BASE_URL + '/api/asset-group/options',
                        type: 'GET',
                        dataType: 'json',
                        success: function(res) {
                            var data = res.data;
                            var len = data.length;
                            for (var i = 0; i < len; i++) {
                                var item = data[i];
                                if (item.class == 'group')
                                    item.id = '[' + item.name + ']';
                                else
                                    item.id = item.name;

                                assets_and_asset_groups.push(item);
                            }

                            $('select.assets-asset-groups-select', modalContainer).each(function() {

                                // Need the .slice to force create a new array, 
                                // so we're not adding the items to the original
                                var combined_assets_and_asset_groups = assets_and_asset_groups.slice();
                                // Have to add the selected assets to the list of options,
                                // but only for THIS widget
                                $(this).find('option').each(function() {

                                    combined_assets_and_asset_groups.push({
                                        id: $(this).val(),
                                        name: $(this).text(),
                                        class: $(this).data('class')
                                    });
                                });

                                selectize_pending_risk_affected_assets_widget($(this), combined_assets_and_asset_groups);

                                //Need it to make it as wide as the textbox below
                                $(this).parent().find('.selectize-control>div').css('width', '97%');

                            });
                        }
                    });

                    $('#pending-risk-detail-modal').modal()
                })
            })
        </script>
    ";
}

/*******************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE ANALYSIS DATATABLES *
 *******************************************************/
function display_questionnaire_analysis_tables($report, $group)
{
    global $escaper, $lang;

    if(empty($group))
    {
        echo "
            <table width=\"100%\" class=\"analysis-datatable risk-datatable table table-bordered table-striped table-condensed\">
                <thead >
                    <tr >
                        <th align=\"left\" width=\"200px\" valign=\"top\">".$escaper->escapeHtml($lang['Subject'])."</th>
                        <th align=\"left\" valign=\"top\">".$escaper->escapeHtml($lang['RiskScore'])."</th>
                        <th align=\"left\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['Questionnaire'])."</th>
                        <th align=\"left\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['Company'])."</th>
                        <th align=\"left\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['SubmissionDate'])."</th>
                        <th align=\"left\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['Type'])."</th>
                        <th align=\"left\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['Details'])."</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            <br>
        ";
    }
    else
    {
        $group_values = getQuestionnaireAnalysisGroupValues($report, $group);

        foreach($group_values as $group_value)
        {
            $base64_group_value = base64_encode($group_value['group_value']);
            
            if($group == "company")
            {
                $group_text = try_decrypt($group_value['group_text']);
            }
            else
            {
                $group_text = $group_value['group_text'];
            }
            
            echo "
                <table width=\"100%\" data-groupvalue=\"{$base64_group_value}\" class=\"analysis-datatable risk-datatable table table-bordered table-striped table-condensed\" >
                    <thead >
                        <tr >
                            <th align=\"center\" colspan=\"7\" valign=\"top\" style=\"text-align: center\">".$escaper->escapeHtml($group_text)."</th>
                        </tr>
                        <tr >
                            <th align=\"left\" width=\"200px\" valign=\"top\">".$escaper->escapeHtml($lang['Subject'])."</th>
                            <th align=\"left\" valign=\"top\">".$escaper->escapeHtml($lang['RiskScore'])."</th>
                            <th align=\"left\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['Questionnaire'])."</th>
                            <th align=\"left\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['Company'])."</th>
                            <th align=\"left\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['SubmissionDate'])."</th>
                            <th align=\"left\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['Type'])."</th>
                            <th align=\"left\" width=\"150px\" valign=\"top\">".$escaper->escapeHtml($lang['Details'])."</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
                <br>
            ";
        }
    }
    
    echo "
        <script>
            var pageLength = 10;
            var analysisDataTables = [];
            $(\".analysis-datatable\").each(function(index){
                var \$this = $(this);
                var datatableInstance = \$this.DataTable({
                    bFilter: false,
                    bLengthChange: false,
                    processing: true,
                    serverSide: true,
                    bSort: true,
                    pagingType: \"full_numbers\",
                    dom : \"flrtip\",
                    pageLength: pageLength,
                    dom : \"flrti<'#view-all-\"+ index +\".view-all'>p\",
                    createdRow: function(row, data, index){
                        var background = $('.background-class', $(row)).data('background');
                        $(row).find('td').addClass(background)
                    },
                    ajax: {
                        url: BASE_URL + '/api/assessment/questionnaire/analysis/dynamic',
                        data: function(d){
                            d.report    = $('#report-dropdown').val()
                            d.group     = $('#group-dropdown').val()
                            d.groupvalue = \$this.data('groupvalue')
                        },
                        complete: function(response){
                        }
                    }
                });
                
                // Add paginate options
                datatableInstance.on('draw', function(e, settings){
                    $('.paginate_button.first').html('<i class=\"fa fa-chevron-left\"></i><i class=\"fa fa-chevron-left\"></i>');
                    $('.paginate_button.previous').html('<i class=\"fa fa-chevron-left\"></i>');

                    $('.paginate_button.last').html('<i class=\"fa fa-chevron-right\"></i><i class=\"fa fa-chevron-right\"></i>');
                    $('.paginate_button.next').html('<i class=\"fa fa-chevron-right\"></i>');
                })
                
                analysisDataTables.push(datatableInstance);
            })
            
                            
            // Add all text to View All button on bottom
            $('.view-all').html(\"".$escaper->escapeHtml($lang['ALL'])."\");

            // View All
            $(\".view-all\").click(function(){
                var \$this = $(this);
                var index = $(this).attr('id').replace(\"view-all-\", \"\");
                console.log(index)
                var oSettings =  analysisDataTables[index].settings();
                oSettings[0]._iDisplayLength = -1;
                analysisDataTables[index].draw()
                \$this.addClass(\"current\");
            })
            
            // Page event
            $(\"body\").on(\"click\", \"span > .paginate_button\", function(){
                var index = $(this).attr('aria-controls').replace(\"DataTables_Table_\", \"\");

                var oSettings =  analysisDataTables[index].settings();
                if(oSettings[0]._iDisplayLength == -1){
                    $(this).parents(\".dataTables_wrapper\").find('.view-all').removeClass('current');
                    oSettings[0]._iDisplayLength = pageLength;
                    analysisDataTables[index].draw()
                }
            })
            
        </script>
    ";
}

/*******************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE RESULTS *
 *******************************************/
function display_questionnaire_fullview(){
    global $escaper, $lang;
    
    $token = $_GET['token'];
    $questionnaire_tracking_info = get_questionnaire_tracking_by_token($token);
    $templates = get_questionnaire_templates_sent_by_tracking_id($questionnaire_tracking_info['tracking_id']);
    
    // Get template IDs
    $template_ids = [];
    foreach($templates as $template){
        $template_ids[] = $template['template_id'];
    }

    $approved = $questionnaire_tracking_info['approved'];
    $completed = $questionnaire_tracking_info['questionnaire_status'];

    echo "
        <div class=\"well questionnaire-result-container\">
            <h2>".$escaper->escapeHtml($lang['Questionnaire']).": ".$escaper->escapeHtml($questionnaire_tracking_info['questionnaire_name'])."
                <table class='pull-right'>
                    <tbody>
                        <tr>" . (!$approved && $completed ?
                            "<td><button id='approve_result' class='btn btn-success pull-right'>".$escaper->escapeHtml($lang['Approve'])."</button></td>
                            <td><button id='reject_result' class='btn btn-danger pull-right'>".$escaper->escapeHtml($lang['Reject'])."</button></td>"
                            : "")
                            . "<td>
                                <a href='".$_SESSION['base_url']."/assessments/questionnaire_compare.php?templates=".implode(",", $template_ids)."&token1={$token}' class='btn '>".$escaper->escapeHtml($lang["CompareResults"])."</a> &nbsp;&nbsp;&nbsp;
                                <a href='".$_SESSION['base_url']."/assessments/questionnaire_audit.php?token={$token}' class='btn '>".$escaper->escapeHtml($lang["ControlAudit"])."</a> 
                            </td>
                        </tr>
                    </tbody>
                </table>
            </h2>
    ";

    if ($approved) {
        $message = $escaper->escapeHtml(getQuestionnaireResultApprovedMessage($questionnaire_tracking_info['tracking_id']));
        echo "
            <div class='result-approved-message'>$message</div>";
    } elseif($completed) {

        // MODEL WINDOW FOR CONTROL DELETE CONFIRM -->
        echo "
            <div style='display: none;' class='result-approved-message'></div>
            <div id='completed-assessment--approve' class='modal hide fade' tabindex='-1' role='dialog' aria-hidden='true'>
              <div class='modal-body'>

                <form class='' action='' method='post'>
                  <div class='form-group text-center'>
                    <label for=''>".$escaper->escapeHtml($lang['AreYouSureYouWantToApproveThisResult'])."</label>
                  </div>

                  <input type='hidden' name='tracking_id' value='{$questionnaire_tracking_info['tracking_id']}' />
                  <div class='form-group text-center control-delete-actions'>
                    <button class='btn btn-default' data-dismiss='modal' aria-hidden='true'>".$escaper->escapeHtml($lang['Cancel'])."</button>
                    <button type='submit' name='approve_result' class='btn btn-danger'>".$escaper->escapeHtml($lang['Yes'])."</button>
                  </div>
                </form>

              </div>
            </div>
            
            <div id='completed-assessment--reject' class='modal hide fade' tabindex='-1' role='dialog' aria-hidden='true'>
              <div class='modal-body'>

                <form class='' action='' method='post'>
                  <div class='form-group text-center'>
                    <label for=''>".$escaper->escapeHtml($lang['AreYouSureYouWantToRejectThisResult'])."</label>
                  </div>
                  <div class='form-group'>
                    <label for=''>".$escaper->escapeHtml($lang['RejectComment']).":</label>
                    <textarea name='reject_comment' style='width: 97%;' cols='50' rows='3' id='reject_comment'></textarea>
                  </div>

                  <input type='hidden' name='tracking_id' value='{$questionnaire_tracking_info['tracking_id']}' />
                  <div class='form-group text-center control-delete-actions'>
                    <button class='btn btn-default' data-dismiss='modal' aria-hidden='true'>".$escaper->escapeHtml($lang['Cancel'])."</button>
                    <button type='submit' name='reject_result' class='btn btn-danger'>".$escaper->escapeHtml($lang['Yes'])."</button>
                  </div>
                </form>

              </div>
            </div>
        ";
        
        echo "
            <script>
                $('#approve_result').click(function() {
                    $('#completed-assessment--approve').modal();
                });

                $('#reject_result').click(function() {
                    $('#completed-assessment--reject').modal();
                });

                $('#completed-assessment--approve form').submit(function(event) {
                    event.preventDefault();
                    $('#completed-assessment--approve').modal('hide');

                    $.ajax({
                        type: 'POST',
                        url: BASE_URL + '/api/assessment/questionnaire/result/approve',
                        data: new FormData($('#completed-assessment--approve form')[0]),
                        async: true,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function(data){
                            if(data.status_message){
                                showAlertsFromArray(data.status_message);
                            }

                            $('#approve_result, #reject_result').hide();
                            $('.result-approved-message').text(data.data);
                            $('.result-approved-message').show();

                            refreshAuditLogsIfOpen();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)) {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    return false;
                });

                $('#completed-assessment--reject form').submit(function(event) {
                    event.preventDefault();
                    $('#completed-assessment--reject').modal('hide');

                    $.ajax({
                        type: 'POST',
                        url: BASE_URL + '/api/assessment/questionnaire/result/reject',
                        data: new FormData($('#completed-assessment--reject form')[0]),
                        async: true,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function(data){
                            if(data.status_message){
                                showAlertsFromArray(data.status_message);
                            }

                            $('#approve_result, #reject_result').hide();

                            refreshAuditLogsIfOpen();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this)) {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    return false;
                });
            </script>
        ";
    }
    $db_responses = get_questionnaire_responses($token);
    
    $templates = get_questionnaire_templates_sent_by_tracking_id($questionnaire_tracking_info['tracking_id']);

    if($templates){
        $templateIndex = 1;
        foreach($templates as $key => $template){
            
            echo "<div><h3>".$templateIndex.".&nbsp;".$escaper->escapeHtml($template['template_name'])."</h3></div>";

            // Get questions of this template
            $template_id = $template['template_id'];
            $template_in_detail = get_assessment_questionnaire_template_by_id($template['template_id']);
            if(isset($template_in_detail['questions'])){
                $questions = $template_in_detail['questions'];
            }else{
                $questions = [];
            }
            
            foreach($questions as $questionIndex => &$question)
            {
                $question_id = $question['question_id'];
                display_questionniare_question_fullview($question_id, $template_id, $questionIndex, $db_responses, false, 0);
            }
            $templateIndex++;
        }

        if(get_assessment_files($questionnaire_tracking_info['tracking_id'])){
            echo "
                <div class='row-fluid attachment-container'>
                    <div ><strong>".$escaper->escapeHtml($lang['AttachmentFiles']).":&nbsp;&nbsp; </strong></div>
                    <div style='padding-left: 10px;'>
                        <div class=\"file-uploader\">
                            <ul class=\"exist-files\">
                                ";
                                display_assessment_files(true);
                            echo "
                            </ul>
                        </div>
                    </div>
                </div>
            ";
        }
        echo "</div>";
        
        // Analysis
        display_questionnaire_analysis($questionnaire_tracking_info['tracking_id']);
        
        // Pending risks
        display_questionnaire_pending_risks($questionnaire_tracking_info['tracking_id']);
        
        // Comment
        display_questionnaire_result_comment($questionnaire_tracking_info['tracking_id']);

        // Audit Logs
        display_questionnaire_result_audit_logs($questionnaire_tracking_info['tracking_id']);

    }
}

/*************************************************
 * FUNCTION: GET QUESTIONNAIRE CONTROLS BY TOKEN *
 *************************************************/
function get_questionnaire_controls_by_token($token)
{
    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t5.*, FIND_IN_SET(1, GROUP_CONCAT(t6.fail_control)) is_failed
        FROM `questionnaire_tracking` t1
            INNER JOIN `questionnaire_id_template` t2 ON t1.questionnaire_id=t2.questionnaire_id
            INNER JOIN `questionnaire_template_question` t3 ON t2.template_id=t3.questionnaire_template_id
            INNER JOIN `questionnaire_questions` t4 ON t3.questionnaire_question_id=t4.id
            INNER JOIN `framework_controls` t5 ON FIND_IN_SET(t5.id, t4.mapped_controls)
            LEFT JOIN `questionnaire_responses` t6 ON t1.id=t6.questionnaire_tracking_id 
                                                AND t2.template_id=t6.template_id 
                                                AND t3.questionnaire_question_id=t6.question_id
                                                /* AND t6.submit_risk=1 */
        WHERE
            t1.token=:token
        GROUP BY
            t5.id
        ;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":token", $token);

    $stmt->execute();

    $controls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $controls;
}

/***************************************************
 * FUNCTION: GET QUESTIONNAIRE FRAMEWORKS BY TOKEN *
 ***************************************************/
function get_questionnaire_frameworks_by_token($token)
{
    // Open the database connection
    $db = db_open();

    $sql = "
        SELECT t6.*
        FROM `questionnaire_tracking` t1 
            INNER JOIN `questionnaire_id_template` t2 ON t1.questionnaire_id=t2.questionnaire_id
            INNER JOIN `questionnaire_template_question` t3 ON t2.template_id=t3.questionnaire_template_id
            INNER JOIN `questionnaire_questions` t4 ON t3.questionnaire_question_id=t4.id
            INNER JOIN `framework_controls` t5 ON FIND_IN_SET(t5.id, t4.mapped_controls)
            INNER JOIN `frameworks` t6 ON FIND_IN_SET(t6.value, t5.framework_ids)
        WHERE
            t1.token=:token
        GROUP BY
            t6.value
        ;
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":token", $token);

    $stmt->execute();

    $frameworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    return $frameworks;
}

/**************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE CONTROL AUDITS *
 **************************************************/
function display_questionnaire_control_audits()
{
    global $escaper, $lang;
    
    $token = $_GET['token'];
    $questionnaire_tracking_info = get_questionnaire_tracking_by_token($token)  ;
    $associated_controls = get_questionnaire_controls_by_token($token);
    $associated_frameworks = get_questionnaire_frameworks_by_token($token);

    echo "
        <div class=\"well\" id=\"questionnaire-audit-container\">
            <h2>
                <small><a href='". $_SESSION['base_url'] ."/assessments/questionnaire_results.php?action=full_view&token=".$token."'>".$escaper->escapeHtml($lang["QuestionnaireResult"])."</a> - </small>"
                .$escaper->escapeHtml($lang['ControlAudit'])." - ".$escaper->escapeHtml($questionnaire_tracking_info['questionnaire_name'])."
            </h2>
            <div class=\"well\">
                <h3>". $escaper->escapeHtml($lang['AssociatedControls']) ."</h3>
                <table width='100%' height='100%' class='table table-bordered table-condensed'>
                    <tr>
                        <th>".$escaper->escapeHtml($lang['Control'])."</th>
                        <th width='100px'>".$escaper->escapeHtml($lang['Status'])."</th>
                    </tr>
                    ";
                    foreach($associated_controls as $associated_control)
                    {
                        echo "
                            <tr>
                                <td>". $escaper->escapeHtml($associated_control['short_name']) ."</td>
                                <td class='". ($associated_control['is_failed'] ? "audit-fail" : "audit-pass") ."' ><span  >". $escaper->escapeHtml($associated_control['is_failed'] ? $lang['Fail'] : $lang['Pass']) ."</span></td>
                            </tr>
                        ";
                    }
                    echo "
                </table>
            </div>
            <div class=\"well\">
                <h3>". $escaper->escapeHtml($lang['AssociatedFrameworks']) ."</h3>
                <table width='100%' height='100%' class='table table-bordered table-condensed'>
                    ";
                    foreach($associated_frameworks as $associated_famework)
                    {
                        echo "
                            <tr>
                                <td>". $escaper->escapeHtml(try_decrypt($associated_famework['name'])) ."</td>
                            </tr>
                        ";
                    }
                    echo "
                </table>

            </div>
        </div>
    ";
}
 
/*********************************************
 * FUNCTION: GET QUESTIONNAIRE PENDING RISKS *
 *********************************************/
function get_questionnaire_pending_risks($tracking_id)
{
    // Open the database connection
    $db = db_open();

    // Get the pending risks
    $stmt = $db->prepare("
        SELECT 
            t4.*,
            t1.id,
            t1.subject,
            t1.owner,
            t1.affected_assets,
            t1.comment,
            t1.tag_ids,
            GROUP_CONCAT(DISTINCT CONCAT(t5.id, '---', t5.tag) SEPARATOR '+++') tags,
            t1.submission_date,
            t3.name questionnaire_name,
            group_concat(distinct CONCAT_WS('_', rsci.contributing_risk_id, rsci.impact)) as Contributing_Risks_Impacts
        FROM 
            `questionnaire_pending_risks` t1 
            INNER JOIN `questionnaire_tracking` t2 on t1.questionnaire_tracking_id=t2.id
            INNER JOIN `questionnaires` t3 on t2.questionnaire_id=t3.id
            LEFT JOIN `questionnaire_scoring` t4 on t1.questionnaire_scoring_id=t4.id
            LEFT JOIN questionnaire_scoring_contributing_impacts rsci ON t4.scoring_method=6 AND t4.id=rsci.questionnaire_scoring_id
            LEFT JOIN `tags` t5 ON FIND_IN_SET(t5.id, t1.tag_ids)
        WHERE 
            t1.questionnaire_tracking_id=:tracking_id AND t1.status=0
        GROUP BY
            t1.id
    ;");

    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    // Return the pending risks
    return $array;
}

/************************************************************
 * FUNCTION: GET RISK ANALYSIS BY QUESTIONNAIRE TRACKING ID *
 ************************************************************/
function get_analysis_by_queaionnaire_tracking_id($tracking_id)
{
    // Open the database connection
    $db = db_open();

    // Get Alls Risks
    $stmt = $db->prepare("
        SELECT 
            'AllRisks' as section_name, count(*) total_number, ROUND(sum(t2.calculated_risk), 2) cumulative_score, ROUND(avg(t2.calculated_risk), 2) average_score
        FROM `questionnaire_pending_risks` t1
            LEFT JOIN `questionnaire_scoring` t2 ON t1.questionnaire_scoring_id=t2.id
        WHERE 
            questionnaire_tracking_id=:tracking_id AND (t1.status=0 OR t1.status=1 OR t1.status=2) /* Pending or Added risks */

        UNION 

        SELECT 
            'AddedRisks' as section_name, count(*) total_number, ROUND(sum(t2.calculated_risk), 2) cumulative_score, ROUND(avg(t2.calculated_risk), 2) average_score
        FROM `questionnaire_pending_risks` t1
            LEFT JOIN `questionnaire_scoring` t2 ON t1.questionnaire_scoring_id=t2.id
        WHERE 
            questionnaire_tracking_id=:tracking_id AND t1.status=1 /* Added risks */
        
        UNION    
        
        SELECT 
            'PendingRisks' as section_name, count(*) total_number, ROUND(sum(t2.calculated_risk), 2) cumulative_score, ROUND(avg(t2.calculated_risk), 2) average_score
        FROM `questionnaire_pending_risks` t1
            LEFT JOIN `questionnaire_scoring` t2 ON t1.questionnaire_scoring_id=t2.id
        WHERE 
            questionnaire_tracking_id=:tracking_id AND t1.status=0 /* Pending risks */
        
        UNION    
        
        SELECT 
            'RejectedRisks' as section_name, count(*) total_number, ROUND(sum(t2.calculated_risk), 2) cumulative_score, ROUND(avg(t2.calculated_risk), 2) average_score
        FROM `questionnaire_pending_risks` t1
            LEFT JOIN `questionnaire_scoring` t2 ON t1.questionnaire_scoring_id=t2.id
        WHERE 
            questionnaire_tracking_id=:tracking_id AND t1.status=2 /* Rejected risks */
        ;
    ");
    
    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
    $stmt->execute();
    $analysis_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $analysis_list;
}

/********************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE ANALYSIS *
 ********************************************/
function display_questionnaire_analysis($tracking_id)
{
    global $escaper, $lang;
    
    $analysis_data = get_analysis_by_queaionnaire_tracking_id($tracking_id);
    
    $named_analysis_data = [];
    foreach($analysis_data as $analysis)
    {
        $named_analysis_data[$analysis['section_name']] = $analysis;
    }
    
    echo "
        <div class='well' id='pending-risk-container' style='padding: 5px 20px'>
            <h4 class=\"\">
                ". $escaper->escapeHtml($lang["Analysis"]) ."
            </h4>
            <table width='100%' class='table table-bordered table-condensed'>
                <thead
                    <tr>
                        <th>&nbsp;</th>
                        <th class='text-center'>". $escaper->escapeHtml($lang['TotalNumber']) ."</th>
                        <th class='text-center'>". $escaper->escapeHtml($lang['CumulativeScore']) ."</th>
                        <th class='text-center'>". $escaper->escapeHtml($lang['AverageScore']) ."</th>
                    </tr>
                </thead>
                <tbody>
                ";
                if(isset($named_analysis_data['AllRisks'])){
                    $analysis_all_risks = $named_analysis_data['AllRisks'];
                    echo "
                        <tr>
                            <td class='text-center'>". $escaper->escapeHtml($lang[ $analysis_all_risks['section_name'] ]) ."</td>
                            <td class='text-center'>". $analysis_all_risks['total_number'] ."</td>
                            <td class='text-right'>". ($analysis_all_risks['cumulative_score'] ? $analysis_all_risks['cumulative_score'] : 0) ."</td>
                            <td class='text-right'>". ($analysis_all_risks['average_score'] ? $analysis_all_risks['average_score'] : 0) ."</td>
                        </tr>
                    ";
                }

                if(isset($named_analysis_data['AddedRisks'])){
                    $analysis_added_risks = $named_analysis_data['AddedRisks'];
                    echo "
                        <tr>
                            <td class='text-center'>". $escaper->escapeHtml($lang[ $analysis_added_risks['section_name'] ]) ."</td>
                            <td class='text-center'>". $analysis_added_risks['total_number'] ."</td>
                            <td class='text-right'>". ($analysis_added_risks['cumulative_score'] ? $analysis_added_risks['cumulative_score'] : 0) ."</td>
                            <td class='text-right'>". ($analysis_added_risks['average_score'] ? $analysis_added_risks['average_score'] : 0) ."</td>
                        </tr>
                    ";
                }

                if(isset($named_analysis_data['PendingRisks'])){
                    $analysis_pending_risks = $named_analysis_data['PendingRisks'];
                    echo "
                        <tr>
                            <td class='text-center'>". $escaper->escapeHtml($lang[ $analysis_pending_risks['section_name'] ]) ."</td>
                            <td class='text-center'>". $analysis_pending_risks['total_number'] ."</td>
                            <td class='text-right'>". ($analysis_pending_risks['cumulative_score'] ? $analysis_pending_risks['cumulative_score'] : 0) ."</td>
                            <td class='text-right'>". ($analysis_pending_risks['average_score'] ? $analysis_pending_risks['average_score'] : 0) ."</td>
                        </tr>
                    ";
                }

                if(isset($named_analysis_data['RejectedRisks'])){
                    $analysis_rejected_risks = $named_analysis_data['RejectedRisks'];
                    echo "
                        <tr>
                            <td class='text-center'>". $escaper->escapeHtml($lang[ $analysis_rejected_risks['section_name'] ]) ."</td>
                            <td class='text-center'>". $analysis_rejected_risks['total_number'] ."</td>
                            <td class='text-right'>". ($analysis_rejected_risks['cumulative_score'] ? $analysis_rejected_risks['cumulative_score'] : 0) ."</td>
                            <td class='text-right'>". ($analysis_rejected_risks['average_score'] ? $analysis_rejected_risks['average_score'] : 0) ."</td>
                        </tr>
                    ";
                }
                echo "</tbody>
            </table>
        </div>
    ";
}

/*************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE PENDING RISKS *
 *************************************************/
function display_questionnaire_pending_risks($tracking_id)
{
    global $escaper, $lang;
    
    $tracking_id = (int)$tracking_id;

    // Get the pending risks
    $risks = get_questionnaire_pending_risks($tracking_id);
    $affected_assets_placeholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']);
    $questionnaire_tracking_info = get_questionnaire_tracking_by_id($tracking_id);
    echo "
        <div class='well' id='pending-risk-container' style='padding: 5px 20px'>
            <h4 class=\"collapsible--toggle clearfix\">
                <span><i class=\"fa  fa-caret-right\"></i>".$escaper->escapeHtml($lang['PendingRisks'])."</span>"
                . (count($risks) ? "<a class='delete-all btn pull-right' style='display: none' href='#all-pending-risks--delete' data-toggle='modal'>".$escaper->escapeHtml($lang['DeleteAll'])."</a>" : "") 
                . (count($risks) ? "<button class='add-all pull-right' style='display: none'>".$escaper->escapeHtml($lang['AddAll'])."</button>" : "") .
            "</h4>
            <div class=\"collapsible\" style='display:none'>
                <table width='100%'>
                    <tr>
                        <td>";
                        // For each pending risk
                        foreach($risks as $risk)
                        {
                            if($risk['tags'])
                            {
                                $tags = array_map(function($id_name) use($escaper){
                                    $id_name_arr = explode("---", $id_name);
                                    return ['label' => $escaper->escapeHtml($id_name_arr[1]), 'value' => $id_name_arr[0]];
                                }, explode("+++", $risk['tags']) );
                                $tag_string = json_encode($tags);
                            }
                            else
                            {
                                $tag_string = "";
                            }
                            
                            echo "<div class=\"hero-unit questionnaire-pending-risk-form\">\n";
                            echo "<form name=\"submit_risk\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
                            echo "<input type=\"hidden\" name=\"pending_risk_id\" value=\"" . $escaper->escapeHtml($risk['id']) . "\" />\n";
                            echo "<input type='hidden' name='tracking_id' value='{$tracking_id}'>";
                            echo "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
                            echo "<tr>\n";
                            echo "<td style=\"white-space: nowrap;\">".$lang['SubmissionDate'] . ":&nbsp;&nbsp;</td>\n";
                            echo "<td width=\"99%\"><input type=\"text\"  style=\"width: 97%;\" name=\"submission_date\" value=\"" . $escaper->escapeHtml($risk['submission_date']) . "\" /></td>\n";
                            echo "</tr>\n";
                            echo "<tr>\n";
                            echo "<td style=\"white-space: nowrap;\">".$lang['Subject'] . ":&nbsp;&nbsp;</td>\n";
                            echo "<td width=\"99%\"><input type=\"text\" style=\"width: 97%;\" name=\"subject\" value=\"" . $escaper->escapeHtml($risk['subject']) . "\" /></td>\n";
                            echo "</tr>\n";
                            if($risk['scoring_method']){
                                $Contributing_Impacts = get_contributing_impacts_by_subjectimpact_values($risk['Contributing_Risks_Impacts']);
                                display_score_html_from_pending_risk($risk['scoring_method'], $risk['Custom'], $risk['CLASSIC_likelihood'], $risk['CLASSIC_impact'], $risk['CVSS_AccessVector'], $risk['CVSS_AccessComplexity'], $risk['CVSS_Authentication'], $risk['CVSS_ConfImpact'], $risk['CVSS_IntegImpact'], $risk['CVSS_AvailImpact'], $risk['CVSS_Exploitability'], $risk['CVSS_RemediationLevel'], $risk['CVSS_ReportConfidence'], $risk['CVSS_CollateralDamagePotential'], $risk['CVSS_TargetDistribution'], $risk['CVSS_ConfidentialityRequirement'], $risk['CVSS_IntegrityRequirement'], $risk['CVSS_AvailabilityRequirement'], $risk['DREAD_DamagePotential'], $risk['DREAD_Reproducibility'], $risk['DREAD_Exploitability'], $risk['DREAD_AffectedUsers'], $risk['DREAD_Discoverability'], $risk['OWASP_SkillLevel'], $risk['OWASP_Motive'], $risk['OWASP_Opportunity'], $risk['OWASP_Size'], $risk['OWASP_EaseOfDiscovery'], $risk['OWASP_EaseOfExploit'], $risk['OWASP_Awareness'], $risk['OWASP_IntrusionDetection'], $risk['OWASP_LossOfConfidentiality'], $risk['OWASP_LossOfIntegrity'], $risk['OWASP_LossOfAvailability'], $risk['OWASP_LossOfAccountability'], $risk['OWASP_FinancialDamage'], $risk['OWASP_ReputationDamage'], $risk['OWASP_NonCompliance'], $risk['OWASP_PrivacyViolation'], $risk['Contributing_Likelihood'], $Contributing_Impacts);
                            }
                            else{
                                display_score_html_from_pending_risk(5, $risk['Custom']);
                            }
                            echo "<tr>\n";
                            echo "<td style=\"white-space: nowrap;\">".$escaper->escapeHtml($lang['Owner']) . ":&nbsp;&nbsp;</td>\n";
                            echo "<td width=\"99%\">\n";
                            create_dropdown("enabled_users", $risk['owner'], "owner");
                            echo "</td>\n";
                            echo "</tr>\n";
                            echo "<tr>\n";
                            echo "<td style=\"white-space: nowrap;\">".$lang['AffectedAssets'] . ":&nbsp;&nbsp;</td>\n";
                            echo "<td width=\"99%\">";

                            echo "<select class='assets-asset-groups-select' name='assets_asset_groups[]' multiple placeholder='$affected_assets_placeholder'>";

                            if ($risk['affected_assets']){
                                foreach(explode(',', $risk['affected_assets']) as $value) {

                                    $value = $name = trim($value);

                                    if (preg_match('/^\[(.+)\]$/', $name, $matches)) {
                                        $name = trim($matches[1]);
                                        $type = 'group';
                                    } else $type = 'asset';

                                    echo "<option value='" . $escaper->escapeHtml($value) . "' selected data-class='$type'>" . $escaper->escapeHtml($name) . "</option>";
                                }
                            }

                            echo "</select>";

                            echo "</td>\n";
                            echo "</tr>\n";
                            echo "<tr>\n";
                                echo "<td style=\"white-space: nowrap;\">".$escaper->escapeHtml($lang['AdditionalNotes']) . ":&nbsp;&nbsp;</td>\n";
                                echo "<td ><textarea name=\"note\" style=\"width: 97%;\" cols=\"50\" rows=\"3\" id=\"note\">Risk created using the &quot;" . $escaper->escapeHtml($risk['questionnaire_name']) . "&quot; questionnaire.\n".$escaper->escapeHtml(try_decrypt($risk['comment']))."</textarea></td>\n";
                            echo "</tr>\n";
                            echo "<tr>\n";
                                echo "<td>". $escaper->escapeHtml($lang['Tags']) ."</td>\n";
                                echo "<td><input type=\"text\" readonly class=\"tags\" name=\"tags\" value=\"\" data-selectize-value='{$tag_string}' /></td>\n";
                            echo "</tr>\n";
                            echo "</table>\n";
                            echo "<div class=\"form-actions\">\n";
                                echo "<button type=\"button\" name=\"add\" class=\"btn btn-danger pending_risk_add\">" . $escaper->escapeHtml($lang['Add']) . "</button>\n";
                                echo "<button type=\"button\" name=\"delete\" class=\"btn pending_risk_delete\">" . $escaper->escapehtml($lang['Delete']) . "</button>\n";
                            echo "</div>\n";
                            echo "</form>\n";
                            echo "</div>\n";
                        }
                        echo "</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- MODEL WINDOW FOR PROJECT DELETE CONFIRM -->
        <div id=\"all-pending-risks--delete\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">
          <div class=\"modal-body\">

            <form class=\"\" action=\"\" method=\"post\">
              <div class=\"form-group text-center\">
                <label for=\"\">".$escaper->escapeHtml($lang['AreYouSureToDeleteAllPendingRisks'])."</label>
                <input type='hidden' name='token' value='".$questionnaire_tracking_info['token']."'>
                <input type='hidden' name='tracking_id' value='{$tracking_id}'>
              </div>
              <div class=\"form-group text-center project-delete-actions\">
                <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                <button type=\"submit\" name=\"delete_all_pending_risks\" class=\"btn btn-danger\">".$escaper->escapeHtml($lang['Yes'])."</button>
              </div>
            </form>

          </div>
        </div>
    ";
    echo "
        <style>
            #pending-risk-container .add-all{
                margin-right: 10px;
            }
        </style>
    ";
    echo "
        <script>
            function createTagsInstance(\$e)
            {
                \$e.selectize({
                    plugins: ['remove_button', 'restore_on_backspace'],
                    delimiter: '+++',
                    create: function (input){
                        return {value: 'new_tag_' + input, label:input};
                    },
                    valueField: 'value',
                    labelField: 'label',
                    searchField: 'label',
                    preload: true,
                    onInitialize: function() {
                        var json_string = this.\$input.attr('data-selectize-value');
                        if(!json_string)
                            return;
                        var existingOptions = JSON.parse(json_string);
                        var self = this;
                        if(Object.prototype.toString.call( existingOptions ) === \"[object Array]\") {
                            existingOptions.forEach( function (existingOption) {
                                self.addOption(existingOption);
                                self.addItem(existingOption[self.settings.valueField]);
                            });
                        }
                        else if (typeof existingOptions === 'object') {
                            self.addOption(existingOptions);
                            self.addItem(existingOptions[self.settings.valueField]);
                        }
                    },
                    load: function(query, callback) {
                        if (query.length) return callback();
                        $.ajax({
                            url: BASE_URL + '/api/management/tag_options_of_type?type=risk',
                            type: 'GET',
                            dataType: 'json',
                            error: function() {
                                console.log('Error loading!');
                                callback();
                            },
                            success: function(res) {
                                callback(res.data);
                            }
                        });
                    }
                });
            }
            createTagsInstance($('.questionnaire-pending-risk-form .tags'));
        </script>    

      <script>
        var assets_and_asset_groups = [];

        \$('body').on('click', '#pending-risk-container .collapsible--toggle span', function(event) {
            event.preventDefault();
            \$('#pending-risk-container .add-all, #pending-risk-container .delete-all').toggle();
            \$(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
            \$(this).find('i').toggleClass('fa-caret-right fa-caret-down');
        });

        \$(\".delete-all\").click(function(e){
            e.preventDefault();
        })

        var loading = {
            ajax:function(st)
            {
                this.show('load');
            },
            show: function(el) {
                this.getID(el).style.display='';
            },
            hide: function(el) {
                this.getID(el).style.display='none';
            },
            getID: function(el) {
                return document.getElementById(el);
            }
        }

        $('.pending_risk_add').click(function() {
            var \$btnObj = \$(this);
            var form_data = \$(this).parents('.questionnaire-pending-risk-form').find('form').serialize();

            loading.show('load');

            $.ajax({
                type: 'POST',
                url: BASE_URL + '/api/assessment/questionnaire/pending_risk',
                data: form_data,
                success: function(res) {
                    \$btnObj.parents('.questionnaire-pending-risk-form').remove();
                    loading.hide('load');
                    showAlertsFromArray(res.status_message, true);
                },
                error: function(xhr, status, error) {
                    if (!retryCSRF(xhr, this)) {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                },
                complete: function(res) {
                    loading.hide('load');
                }
            })
        });
        
        var btnObj;
        function delete_pending_risk() {
            var form_data = btnObj.parents('.questionnaire-pending-risk-form').find('form').serialize();
            loading.show('load');
            $.ajax({
                type: 'POST',
                url: BASE_URL + '/api/assessment/questionnaire/delete_pending_risk',
                data: form_data,
                success: function(data) {
                    btnObj.parents('.questionnaire-pending-risk-form').remove();
                    loading.hide('load');
                    if (data.status_message) {
                        showAlertsFromArray(data.status_message, true);
                    }
                },
                error: function(xhr, status, error) {
                    if(!retryCSRF(xhr, this)) {
                        if(xhr.responseJSON && xhr.responseJSON.status_message){
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        }
                    }
                },
                complete: function(res) {
                    loading.hide('load');
                }
            });
        }

        $('.pending_risk_delete').click(function() {
            btnObj = $(this);
            confirm(\"Are you sure to delete this pending risk?\", \"delete_pending_risk()\");
        });

        \$(\".add-all\").click(function(){
            var \$forms = \$(this).parents('#pending-risk-container').find('form');
            var pending_risks = [];

            \$forms.each(function(){
                var ContributingImpacts = {};
                $(this).find('.contributing-impact').each(function(){
                    ContributingImpacts[$(this).attr('id').replace('contributing_impact_', '')] = [$(this).val()];
                })

                var pending_risk = {
                    tracking_id: \$(this).find(\"[name=tracking_id]\").val(),
                    pending_risk_id: \$(this).find(\"[name=pending_risk_id]\").val(),
                    submission_date: \$(this).find(\"[name=submission_date]\").val(),
                    subject: \$(this).find(\"[name=subject]\").val(),
                    owner: \$(this).find(\"[name=owner]\").val(),
                    note: \$(this).find(\"[name=note]\").val(),
                    tags: \$(this).find(\"[name=tags]\").val(),

                    assets_asset_groups: \$(this).find('select[name^=\"assets_asset_groups\"]').val(),

                    scoring_method: \$(this).find('[name=\"scoring_method[]\"]').val(),

                    likelihood: \$(this).find('[name=\"likelihood[]\"]').val(),
                    impact: \$(this).find('[name=\"impact[]\"]').val(),
                    AccessVector: \$(this).find('[name=\"AccessVector[]\"]').val(),
                    AccessComplexity: \$(this).find('[name=\"AccessComplexity[]\"]').val(),
                    Authentication: \$(this).find('[name=\"Authentication[]\"]').val(),
                    ConfImpact: \$(this).find('[name=\"ConfImpact[]\"]').val(),
                    IntegImpact: \$(this).find('[name=\"IntegImpact[]\"]').val(),
                    AvailImpact: \$(this).find('[name=\"AvailImpact[]\"]').val(),
                    Exploitability: \$(this).find('[name=\"Exploitability[]\"]').val(),
                    RemediationLevel: \$(this).find('[name=\"RemediationLevel[]\"]').val(),
                    ReportConfidence: \$(this).find('[name=\"ReportConfidence[]\"]').val(),
                    CollateralDamagePotential: \$(this).find('[name=\"CollateralDamagePotential[]\"]').val(),
                    TargetDistribution: \$(this).find('[name=\"TargetDistribution[]\"]').val(),
                    ConfidentialityRequirement: \$(this).find('[name=\"ConfidentialityRequirement[]\"]').val(),
                    IntegrityRequirement: \$(this).find('[name=\"IntegrityRequirement[]\"]').val(),
                    AvailabilityRequirement: \$(this).find('[name=\"AvailabilityRequirement[]\"]').val(),

                    DREADDamage: \$(this).find('[name=\"DREADDamage[]\"]').val(),
                    DREADReproducibility: \$(this).find('[name=\"DREADReproducibility[]\"]').val(),
                    DREADExploitability: \$(this).find('[name=\"DREADExploitability[]\"]').val(),
                    DREADAffectedUsers: \$(this).find('[name=\"DREADAffectedUsers[]\"]').val(),
                    DREADDiscoverability: \$(this).find('[name=\"DREADDiscoverability[]\"]').val(),

                    OWASPSkillLevel: \$(this).find('[name=\"OWASPSkillLevel[]\"]').val(),
                    OWASPMotive: \$(this).find('[name=\"OWASPMotive[]\"]').val(),
                    OWASPOpportunity: \$(this).find('[name=\"OWASPOpportunity[]\"]').val(),
                    OWASPSize: \$(this).find('[name=\"OWASPSize[]\"]').val(),
                    OWASPEaseOfDiscovery: \$(this).find('[name=\"OWASPEaseOfDiscovery[]\"]').val(),
                    OWASPEaseOfExploit: \$(this).find('[name=\"OWASPEaseOfExploit[]\"]').val(),
                    OWASPAwareness: \$(this).find('[name=\"OWASPAwareness[]\"]').val(),
                    OWASPIntrusionDetection: \$(this).find('[name=\"OWASPIntrusionDetection[]\"]').val(),
                    OWASPLossOfConfidentiality: \$(this).find('[name=\"OWASPLossOfConfidentiality[]\"]').val(),
                    OWASPLossOfIntegrity: \$(this).find('[name=\"OWASPLossOfIntegrity[]\"]').val(),
                    OWASPLossOfAvailability: \$(this).find('[name=\"OWASPLossOfAvailability[]\"]').val(),
                    OWASPLossOfAccountability: \$(this).find('[name=\"OWASPLossOfAccountability[]\"]').val(),
                    OWASPFinancialDamage: \$(this).find('[name=\"OWASPFinancialDamage[]\"]').val(),
                    OWASPReputationDamage: \$(this).find('[name=\"OWASPReputationDamage[]\"]').val(),
                    OWASPNonCompliance: \$(this).find('[name=\"OWASPNonCompliance[]\"]').val(),
                    OWASPPrivacyViolation: \$(this).find('[name=\"OWASPPrivacyViolation[]\"]').val(),

                    Custom: \$(this).find('[name=\"Custom[]\"]').val(),

                    ContributingLikelihood: \$(this).find('[name=\"ContributingLikelihood[]\"]').val(),
                    ContributingImpacts: ContributingImpacts
                };

                pending_risks.push(pending_risk);
            })
            loading.ajax();
            \$.ajax({
                type: \"POST\",
                url: BASE_URL + \"/api/assessment/questionnaire/pending_risks\",
                data: {
                    pending_risks: pending_risks
                },
                success: function(data){
                    document.location.reload();
                },
                error: function(xhr,status,error){
                    if(!retryCSRF(xhr, this))
                    {
                    }
                }
            })
        });


            $.ajax({
                url: '/api/asset-group/options',
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    var data = res.data;
                    var len = data.length;
                    for (var i = 0; i < len; i++) {
                        var item = data[i];
                        if (item.class == 'group')
                            item.id = '[' + item.name + ']';
                        else
                            item.id = item.name;

                        assets_and_asset_groups.push(item);
                    }

                    $('select.assets-asset-groups-select').each(function() {

                        // Need the .slice to force create a new array, 
                        // so we're not adding the items to the original
                        var combined_assets_and_asset_groups = assets_and_asset_groups.slice();
                        // Have to add the selected assets to the list of options,
                        // but only for THIS widget
                        $(this).find('option').each(function() {

                            combined_assets_and_asset_groups.push({
                                id: $(this).val(),
                                name: $(this).text(),
                                class: $(this).data('class')
                            });
                        });

                        selectize_pending_risk_affected_assets_widget($(this), combined_assets_and_asset_groups);

                        //Need it to make it as wide as the textbox below
                        $(this).parent().find('.selectize-control>div').css('width', '97%');

                    });
                }
            });
        </script>
    ";

    return true;
}

/**************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE RESULT COMMENT *
 **************************************************/
function display_questionnaire_result_comment($tracking_id)
{
    global $escaper, $lang;
    
    $tracking_id = (int)$tracking_id;
    
    echo "
        <div class=\"row-fluid comments--wrapper\" style='margin-top: 0px'>

            <div class=\"well\">
                <h4 class=\"collapsible--toggle clearfix\">
                    <span><i class=\"fa  fa-caret-right\"></i>".$escaper->escapeHtml($lang['Comments'])."</span>
                    <a href=\"#\" class=\"add-comments pull-right\"><i class=\"fa fa-plus\"></i></a>
                </h4>

                <div class=\"collapsible\" style='display:none'>
                    <div class=\"row-fluid\">
                        <div class=\"span12\">

                            <form id=\"comment\" class=\"comment-form\" name=\"add_comment\" method=\"post\" action=\"/management/comment.php?id={$tracking_id}\">
                                <input type='hidden' name='id' value='{$tracking_id}'>
                                <textarea style=\"width: 100%; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; box-sizing: border-box;\" name=\"comment\" cols=\"50\" rows=\"3\" id=\"comment-text\" class=\"comment-text\"></textarea>
                                <div class=\"form-actions text-right\" id=\"comment-div\">
                                    <input class=\"btn\" id=\"rest-btn\" value=\"".$escaper->escapeHtml($lang['Reset'])."\" type=\"reset\" />
                                    <button id=\"comment-submit\" type=\"submit\" name=\"submit\" class=\"comment-submit btn btn-primary\" >".$escaper->escapeHtml($lang['Submit'])."</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class=\"row-fluid\">
                        <div class=\"span12\">
                            <div class=\"comments--list clearfix\">
                                ".get_questionnaire_result_comment_list($tracking_id)."
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    ";
    
    echo "
        <script>
            \$('body').on('click', '.comments--wrapper .collapsible--toggle span', function(event) {
                event.preventDefault();
                var container = \$(this).parents('.comments--wrapper');
                \$(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
                \$(this).find('i').toggleClass('fa-caret-right fa-caret-down');
                if($('.collapsible', container).is(':visible') && $('.add-comments', container).hasClass('rotate')){
                    $('.add-comments', container).click()
                }
            });

            $('body').on('click', '.add-comments', function(event) {
                event.preventDefault();
                var container = \$(this).parents('.comments--wrapper');
                if(!$('.collapsible', container).is(':visible')){
                    $(this).parents('.collapsible--toggle').next('.collapsible').slideDown('400');
                    $(this).parent().find('span i').removeClass('fa-caret-right');
                    $(this).parent().find('span i').addClass('fa-caret-down');
                }
                $(this).toggleClass('rotate');
                $('.comment-form', container).fadeToggle('100');
            });

            $('body').on('click', '.comment-submit', function(e){
                e.preventDefault();
                var container = $('.comments--wrapper');
                
                if(!$('.comment-text', container).val()){
                    $('.comment-text', container).focus();
                    return;
                }
                
                var risk_id = $('.large-text', container).html();
                
                var getForm = \$(this).parents('form', container);
                var form = new FormData($(getForm)[0]);

                $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/assessment/questionnaire/save_result_comment',
                    data: form,
                    contentType: false,
                    processData: false,
                    success: function(data){
                        $('.comments--list', container).html(data.data);
                        $('.comment-text', container).val('')
                        $('.comment-text', container).focus()
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this))
                        {
                        }
                    }
                })
            })
        
        </script>
    ";
      
    return true;
}

/*******************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE RESULT COMMENT LIST *
 *******************************************************/
function get_questionnaire_result_comment_list($tracking_id)
{
    global $escaper;

    // Open the database connection
    $db = db_open();

    // Get the comments
    $stmt = $db->prepare("SELECT a.date, a.comment, b.name FROM questionnaire_result_comments a LEFT JOIN user b ON a.user = b.value WHERE a.tracking_id=:tracking_id ORDER BY a.date DESC");

    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);

    $stmt->execute();

    // Store the list in the array
    $comments = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    $returnHTML = "";
    foreach ($comments as $comment)
    {
        $text = try_decrypt($comment['comment']);
        $date = date(get_default_datetime_format("g:i A T"), strtotime($comment['date']));
        $user = $comment['name'];
        
        if($text != null){
            $returnHTML .= "<p class=\"comment-block\">\n";
            $returnHTML .= "<b>" . $escaper->escapeHtml($date) ." by ". $escaper->escapeHtml($user) ."</b><br />\n";
            $returnHTML .= $escaper->escapeHtml($text);
            $returnHTML .= "</p>\n";
        }
    }

    return $returnHTML;
    
}

/**********************************************
 * FUNCTION: SAVE QUESTIONNARE RESULT COMMENT *
 **********************************************/
function save_questionnaire_result_comment($tracking_id, $comment){
    $user           = $_SESSION['uid'];
    $comment_enc    = try_encrypt($comment);

    // Open the database connection
    $db = db_open();
    
    $sql = "
        INSERT INTO `questionnaire_result_comments`(`tracking_id`, `user`, `comment`) VALUES(:tracking_id, :user, :comment);
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_STR);
    $stmt->bindParam(":comment", $comment_enc, PDO::PARAM_STR);
    $stmt->bindParam(":user", $user, PDO::PARAM_INT);
    
    // Insert a test result
    $stmt->execute();
    
    // Close the database connection
    db_close($db);

    $questionnaire_tracking_info = get_questionnaire_tracking_by_id($tracking_id);

    $message = _lang('QuestionnaireResultCommentAuditLog', array(
        'user_name' => $_SESSION['user'],
        'questionnaire_name' => $questionnaire_tracking_info['questionnaire_name'],
        'contact_name' => $questionnaire_tracking_info['contact_name'],
        'date' => format_datetime($questionnaire_tracking_info['sent_at'])
    ), false);
    write_log($tracking_id + 1000, $_SESSION['uid'], $message, 'questionnaire_tracking');
}

/****************************************************
 * FUNCTION: DISPLAY QUESTIONNAIRE RESULT AUDIT LOG *
 ****************************************************/
function display_questionnaire_result_audit_logs($tracking_id) {

    global $escaper, $lang;
    
    $tracking_id = (int)$tracking_id;

    echo "
        <div class='row-fluid audit-log-wrapper'>
            <div class='well' style='padding: 5px 20px;'>
                <h4 class='collapsible--toggle'>
                    <span><i class='fa fa-caret-right'></i>".$escaper->escapeHtml($lang['AuditTrail'])."</span>
                    <a href='#' class='refresh-audit-trail pull-right'><i class='fa fa-refresh'></i></a>
                </h4>
                <div class='collapsible' style='display: none;'>
                    <div class='row-fluid'>
                        <div class='span12 audit-trail'>
                            <div class='audit-option-container'>
                                <div class='audit-select-folder'>
                                    <select name='days' class='audit-select-days'>
                                        <option value='7' selected >Past Week</option>
                                        <option value='30'>Past Month</option>
                                        <option value='90'>Past Quarter</option>
                                        <option value='180'>Past 6 Months</option>
                                        <option value='365'>Past Year</option>
                                        <option value='36500'>All Time</option>
                                    </select>
                                </div>";

    // If import/export extra is enabled and is an admin user, shows the export audit log button
    if (import_export_extra() && is_admin()) {
        require_once(realpath(__DIR__ . '/../import-export/index.php'));
        display_audit_download_btn(array('tracking_id' => $tracking_id));
    }

    echo "
                                <div class='clearfix'></div>
                            </div>
                            <div class='audit-contents'></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Refresh audit logs if the log section is not collapsed
            // if it is, mark it for refresh on the next time it's opened
            function refreshAuditLogsIfOpen() {
                if ($('.audit-log-wrapper .collapsible--toggle > span > i.fa-caret-down').length)
                    refreshAuditLogs();
                else $('.audit-log-wrapper .collapsible--toggle > span > i').data('need-refresh', true);
            }

            function refreshAuditLogs() {
                $.ajax({
                    type: 'POST',
                    url: BASE_URL + '/api/assessment/questionnaire/result/audit_log',
                    data: {
                        tracking_id:".$tracking_id.",
                        days: $('.audit-trail select.audit-select-days').val()
                    },
                    async: true,
                    cache: false,
                    success: function(data) {
                        var div = $('<div>');
                        $.each( data.data, function( key, value ) {
                            div.append($('<p>' + value.timestamp + ' > ' + value.message + '</p>' ));
                        });
                        $('.audit-trail>div.audit-contents').html(div.html());
                        $('.audit-log-wrapper .collapsible--toggle > span > i').data('need-refresh', false);
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this)) {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }
                    }
                });
            }

            $(document).ready(function(){

                $('.audit-log-wrapper .collapsible--toggle span').click(function(event) {
                    event.preventDefault();

                    if ($('.audit-log-wrapper .collapsible--toggle > span > i.fa-caret-right').length && $('.audit-log-wrapper .collapsible--toggle > span > i').data('need-refresh'))
                        refreshAuditLogs();

                    $(this).parents('.audit-log-wrapper .collapsible--toggle').next('.collapsible').slideToggle('400');
                    $(this).find('i').toggleClass('fa-caret-right fa-caret-down');
                });

                $('.refresh-audit-trail').click(function(event) {
                    event.preventDefault();
                    refreshAuditLogs();
                });

                $('.audit-trail select.audit-select-days').change(refreshAuditLogs);

                refreshAuditLogs();
            });
        </script>
    ";
}

function get_questionnaire_result_audit_log($tracking_id, $days) {

    $db = db_open();

    $stmt = $db->prepare("SELECT timestamp, message FROM audit_log WHERE risk_id=:tracking_id AND (`timestamp` > CURDATE()-INTERVAL :days DAY) AND log_type='questionnaire_tracking' ORDER BY timestamp DESC");
    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
    $stmt->bindParam(":days", $days, PDO::PARAM_INT);

    $stmt->execute();

    $logs = $stmt->fetchAll();

    db_close($db);

    return $logs;
}

/*****************************************
 * FUNCTION: DOWNLOAD QUESTIONNAIRE FILE *
 *****************************************/
function download_questionnaire_file($unique_name){
    global $escaper;

    // Open the database connection
    $db = db_open();

    // Get the file from the database
    $stmt = $db->prepare("SELECT * FROM questionnaire_files WHERE BINARY unique_name=:unique_name");
    $stmt->bindParam(":unique_name", $unique_name, PDO::PARAM_STR, 30);
    $stmt->execute();

    // Store the results in an array
    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // If the array is empty
    if (empty($array))
    {
        // Do nothing
        exit;
    }
    else
    {
        header("Content-length: " . $array['size']);
        header("Content-type: " . $array['type']);
        header("Content-Disposition: attachment; filename=" . $escaper->escapeUrl($array['name']));
        echo $array['content'];
        exit;
    }
}

/****************************
 * FUNCTION: LOGOUT CONTACT *
 ****************************/
function logout_contact()
{
    // Deny access
    unset($_SESSION["contact_id"]);

    // Reset the session data
    $_SESSION = array();

    // Send a Set-Cookie to invalidate the session cookie
    if (ini_get("session.use_cookies"))
    {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
    }

    // Destroy the session
    session_destroy();    
}

/********************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT CONTACTS DATATABLE *
 ********************************************************/
function getAssessmentContacts() {

    global $lang;
    global $escaper;

    $draw = $_GET['draw'];
    
    $filter_text = $_GET['filter_text'];
    $orderColumn = (int)$_GET['order'][0]['column'];
    $orderDir = $_GET['order'][0]['dir'];
    
    $columnNames = array(
        "company",
        "name",
        "email",
        "phone",
        "manager",
    );

    $assessment_contacts = get_assessment_contacts($filter_text, $columnNames[$orderColumn], $orderDir);
 
    $recordsTotal = count($assessment_contacts);
    
    $data = array();
    
    foreach ($assessment_contacts as $key=>$contact)
    {
        // If it is not requested to view all
        if($_GET['length'] != -1){
            if($key < $_GET['start']){
                continue;
            }
            if($key >= ($_GET['start'] + $_GET['length'])){
                break;
            }
        }
        
        $data[] = [
            $escaper->escapeHtml($contact['company']),
            $escaper->escapeHtml($contact['name']),
            $escaper->escapeHtml($contact['email']),
            $escaper->escapeHtml($contact['phone']),
            $escaper->escapeHtml($contact['manager_name']),
            "<a href=\"#aseessment-contact--delete\" data-toggle=\"modal\" class=\"control-block--delete contact-delete-btn pull-right\" data-id=\"". $contact['id'] ."\"><i class=\"fa fa-trash\"></i></a><a href=\"contacts.php?action=edit&id=". $contact['id'] ."\" class=\"pull-right\" data-id=\"1\"><i class=\"fa fa-pencil-square-o\"></i></a>",
        ];
    }
    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
    );
    echo json_encode($result);
    exit;    
}

/******************************************
 * FUNCTION: GET QUESTIONS HTML BY ANSWER *
 ******************************************/
function getAssessmentQuestionnaireQuestionsByAnswer()
{
    $answer_id    = (int)$_GET['answer_id'];
    $template_id  = (int)$_GET['template_id'];
    $token        = $_GET['token'];

    $questionnaire_tracking_info = get_questionnaire_tracking_by_token($token);
    $questionnaire_status = $questionnaire_tracking_info['questionnaire_status'];

    $db_responses = get_questionnaire_responses($token);

    $answer = get_questionnaire_answer_by_id($answer_id);
    $sub_question_ids = $answer['sub_questions'] ? explode(",", $answer['sub_questions']) : [];
    
    ob_start();

    foreach($sub_question_ids as $index => $sub_question_id){
        display_contact_questionnaire_question($sub_question_id, $template_id, $index, $db_responses, $questionnaire_status, true, $answer['question_id']);
    }
    $viewhtml = ob_get_contents();
    ob_end_clean();
    
    $result = ['html' => $viewhtml];
    echo json_encode($result);
}

/**********************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE QUESTIONS DATATABLE *
 **********************************************************************/
function getAssessmentQuestionnaireQuestions(){
    global $lang;
    global $escaper;

    $draw = $_GET['draw'];
    $filter_by_question = trim($_GET['filter_by_question']);
    $filter_by_templates = array_map(
        'intval',
        isset($_GET['filter_by_templates']) && is_array($_GET['filter_by_templates']) ? $_GET['filter_by_templates'] : []);

    list($recordsTotal, $questionnaire_questions)= get_assessment_questionnaire_questions($_GET['start'], $_GET['length'], $filter_by_question, $filter_by_templates);

    $data = array();

    foreach ($questionnaire_questions as $key=>$questionnaire_question)
    {
        $answersHtml = "";
        foreach($questionnaire_question['answers'] as $answer){
            $answersHtml .= "<li>". $escaper->escapeHtml($answer['answer']) ."</li>";
        }
        
        $data[] = [
            "
            <div class='row-fluid'>
                <div class='span2'>&nbsp;</div>
                <div class='span10'>
                    <a href=\"#aseessment-questionnaire-question--delete\" data-toggle=\"modal\" class=\"control-block--delete delete-btn pull-right\" data-id=\"". $questionnaire_question['id'] ."\"><i class=\"fa fa-trash\"></i></a><a href=\"questionnaire_questions.php?action=edit_question&id=". $questionnaire_question['id'] ."\" class=\"pull-right\" data-id=\"1\"><i class=\"fa fa-pencil-square-o\"></i></a>
                </div>
            </div>
            <div class='row-fluid'>
                <div class='span2'><strong>".$escaper->escapeHtml($lang['Question']).":</strong> </div>
                <div class='span10'>". $escaper->escapeHtml($questionnaire_question['question']) ."&nbsp;</div>
            </div>
            <div class='row-fluid'>
                <div class='span2'><strong>".$escaper->escapeHtml($lang['MappedControls']).":</strong> </div>
                <div class='span10'>". $escaper->escapeHtml($questionnaire_question['mapped_control_names']) ."&nbsp;</div>
            </div>
            <div class='row-fluid'>
                <div class='span2'><strong>".$escaper->escapeHtml($lang['Answers']).":</strong> </div>
                <div class='span10'>
                    <ul>
                        {$answersHtml}
                    </ul>
                        
                </div>
            </div>
            ",
        ];
    }
    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
    );
    echo json_encode($result);
    exit;    
}

/*********************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE TEMPLATE DATATABLE *
 *********************************************************************/
function questionnaireTemplateDynamicAPI(){
    global $lang;
    global $escaper;

    $draw = $_GET['draw'];
    
    list($recordsTotal, $questionnaire_templates) = get_assessment_questionnaire_templates($_GET['start'], $_GET['length']);
 
    $data = array();
    
    foreach ($questionnaire_templates as $key=>$questionnaire_template)
    {
        $data[] = [
            $escaper->escapeHtml($questionnaire_template['name']),
            "
            <div style='text-align: center'>
            <a href=\"questionnaire_templates.php?action=edit_template&id=". $questionnaire_template['id'] ."\" class=\"edit-btn\" data-id=\"1\"><i class=\"fa fa-pencil-square-o\"></i></a>&nbsp;&nbsp;&nbsp;&nbsp;
            <a href=\"#\" class=\"copy-btn \" data-id=\"". $questionnaire_template['id'] ."\"><i class=\"fa fa-copy\"></i></a>&nbsp;&nbsp;&nbsp;&nbsp;
            <a href=\"#aseessment-questionnaire-template--delete\" data-toggle=\"modal\" class=\"control-block--delete delete-btn \" data-id=\"". $questionnaire_template['id'] ."\"><i class=\"fa fa-trash\"></i></a>
            </div>
            "
        ];
    }
    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
    );

    echo json_encode($result);
    exit;    
}

/************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE DATATABLE *
 ************************************************************/
function questionnaireDynamicAPI(){
    global $lang;
    global $escaper;

    $draw = $_GET['draw'];
    
    list($recordsTotal, $questionnaires) = get_assessment_questionnaires($_GET['start'], $_GET['length']);

    $data = array();
    
    foreach ($questionnaires as $key=>$questionnaire) {
        $usable = $questionnaire['template_count'];
        $data[] = [
            $escaper->escapeHtml($questionnaire['name']),
            "
            <a href=\"questionnaires.php?action=edit&id=". $questionnaire['id'] ."\" class=\"edit-btn\" data-id=\"1\"><i class=\"fa fa-pencil-square-o\"></i></a>&nbsp;&nbsp;&nbsp;&nbsp;
            <a href=\"#\" class=\"copy-btn \" data-id=\"". $questionnaire['id'] ."\"><i class=\"fa fa-copy\"></i></a>&nbsp;&nbsp;&nbsp;&nbsp;
            <a href=\"#aseessment-questionnaire--delete\" data-toggle=\"modal\" class=\"delete-btn\" data-id=\"". $questionnaire['id'] ."\"><i class=\"fa fa-trash\"></i></a>
            ",
            "
            <div class='text-center'>
                <button class='btn send-questionnaire'" . (!$usable ? " disabled title='".$escaper->escapeHtml($lang['QuestionnaireHasNoTemplates'])."'" : "") . " data-id='{$questionnaire['id']}' >".$escaper->escapeHtml($lang['Send'])."</button>
            </div>",
        ];
    }
    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
    );

    echo json_encode($result);
    exit;    
}

/********************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE RESULTS DATATABLE *
 ********************************************************************/
function questionnaireResultsDynamicAPI(){
    global $lang;
    global $escaper;

    $draw = $_GET['draw'];
    
    $orderColumn = (int)$_GET['order'][0]['column'];
    $orderDir = $_GET['order'][0]['dir'];
    
    $columnNames = array(
        "questionnaire_name",
        "company",
        "contact",
        "percent",
        "date_sent",
        "status",
        "approved"
    );
    
    // Filter params
    $filters = array(
        "company"   => $_GET['company'],
        "contact"   => $_GET['contact'],
        "date_sent" => $_GET['date_sent'] ? get_standard_date_from_default_format($_GET['date_sent']) : "",
        "status"    => $_GET['status'],
    );
    
    list($recordsTotal, $questionnaire_results) = get_assessment_questionnaire_results($_GET['start'], $_GET['length'], $filters, $columnNames[$orderColumn], $orderDir);
    
    $data = array();
    
    foreach ($questionnaire_results as $key=>$questionnaire_result) {

        $data[] = [
            "<a class='text-left' href='".$_SESSION['base_url']."/assessments/questionnaire_results.php?action=full_view&token=".$questionnaire_result['token']."'>".$escaper->escapeHtml($questionnaire_result['questionnaire_name'])."</a>",
            $escaper->escapeHtml($questionnaire_result['contact_company']),
            $escaper->escapeHtml($questionnaire_result['contact_name']),
            "<div class='text-right'>{$questionnaire_result['percent']}%</div>",
            "<div class='text-center'>".format_date($questionnaire_result['sent_at'])."</div>",
            $escaper->escapeHtml($questionnaire_result['tracking_status'] ? $lang['Completed'] : $lang['Incomplete']),
            $escaper->escapeHtml(localized_yes_no($questionnaire_result['approved'])),
        ];
    }
    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsTotal,
    );

    echo json_encode($result);
    exit;    
}

/****************************
 * FUNCTION: APPROVE RESULT *
 ****************************/
function approveResultAPI(){
    global $escaper, $lang;
    
    $tracking_id =  (int)$_POST['tracking_id'];

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        UPDATE
            `questionnaire_tracking`
        SET
            `approver`=:approver,
            `approved_at`=now()
        WHERE
            `id` = :tracking_id;
    ");

    $stmt->bindParam(":approver", $_SESSION['uid'], PDO::PARAM_INT);
    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Close the database connection
    db_close($db);

    $questionnaire_tracking_info = get_questionnaire_tracking_by_id($tracking_id);

    $message = _lang('QuestionnaireResultApprovedAuditLog', array(
        'questionnaire_name' => $questionnaire_tracking_info['questionnaire_name'],
        'contact_name' => $questionnaire_tracking_info['contact_name'],
        'date' => format_datetime($questionnaire_tracking_info['sent_at']),
        'user_name' => $_SESSION['user']
    ), false);
    write_log($tracking_id + 1000, $_SESSION['uid'], $message, 'questionnaire_tracking');

    set_alert(true, "good", $lang['QuestionnaireResultApprovedSuccessfully']);    
    json_response(200, get_alert(true), $escaper->escapeHtml(getQuestionnaireResultApprovedMessage($tracking_id)));
}

/***************************************************
 * FUNCTION: QUESTIONNAIRE RESULT APPROVED MESSAGE *
 ***************************************************/
function getQuestionnaireResultApprovedMessage($tracking_id) {

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            `u`.`name` user,
            `qt`.`approved_at`
        FROM
            `questionnaire_tracking` qt
            INNER JOIN `user` u ON `qt`.`approver` = `u`.`value`
        WHERE
            `qt`.`id` = :tracking_id;
    ");

    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();

    // Close the database connection
    db_close($db);

    return _lang(
        'QuestionnaireResultApprovedMessage',
        array(
            'user' => $result['user'],
            'timestamp' => format_datetime($result['approved_at'])
        ),
        false
    );
}

/***************************
 * FUNCTION: REJECT RESULT *
 ***************************/
function rejectResultAPI(){
    global $escaper, $lang;
    
    $tracking_id =      (int)$_POST['tracking_id'];
    $reject_comment =   $_POST['reject_comment'];

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        UPDATE
            `questionnaire_tracking`
        SET
            `status` = 0,
            `updated_at` = now()
        WHERE id=:tracking_id;
    ");
    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $stmt = $db->prepare("
        SELECT
            `qt`.`token`,
            `q`.`name` questionnaire_name,
            `ac`.`name` contact_name,
            `ac`.`email` contact_email
        FROM
            `questionnaire_tracking` qt
            INNER JOIN `questionnaires` q ON `qt`.`questionnaire_id`=`q`.`id`
            LEFT JOIN `assessment_contacts` ac ON `qt`.`contact_id`=`ac`.`id`
        WHERE
            `qt`.`id` = :tracking_id;
    ");

    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();

    $result['contact_name'] = try_decrypt($result['contact_name']);
    $result['contact_email'] = try_decrypt($result['contact_email']);

    // Delete all existing pending risks on reject
    delete_all_questionnaire_pending_risks($token);

    // Require the mail functions
    require_once(realpath(__DIR__ . '/../../includes/mail.php'));

    $subject = $escaper->escapeHtml($lang['RiskAssessmentQuestionnaire']);
    $body = get_string_from_template($lang['EmailTemplateRejectedQuestionnaireResult'], array(
        'username' => $_SESSION['name'],
        'assessment_name' => $result['questionnaire_name'],
        'assessment_link' => $_SESSION['base_url'] . "/assessments/questionnaire.index.php?token=" . $result['token'],
        'reject_comment' => $reject_comment
    ));

    send_email($result['contact_name'], $result['contact_email'], $subject, $body);

    $stmt = $db->prepare("
        UPDATE
            `questionnaire_tracking`
        SET
            `updated_at` = now(),
            `sent_at` = now()
        WHERE id=:tracking_id;
    ");
    $stmt->bindParam(":tracking_id", $tracking_id, PDO::PARAM_INT);
    $stmt->execute();

    $message = _lang('QuestionnaireResultRejectedAuditLog', array(
        'questionnaire_name' => $result['questionnaire_name'],
        'contact_name' => $result['contact_name'],
        'user_name' => $_SESSION['user'],
        'reject_comment' => $reject_comment
    ), false);

    write_log($tracking_id + 1000, $_SESSION['uid'], $message, 'questionnaire_tracking');

    // Close the database connection
    db_close($db);

    set_alert(true, "good", $lang['QuestionnaireResultRejectedSuccessfully']);
    json_response(200, get_alert(true), null);
}


/**********************************************
 * FUNCTION: SAVE QUESTIONNARE RESULT COMMENT *
 **********************************************/
function saveQuestionnaireResultCommentAPI(){
    global $escaper, $lang;
    
    $tracking_id =  (int)$_POST['id'];
    $comment =  $_POST['comment'];
    
    // Save comment
    save_questionnaire_result_comment($tracking_id, $comment);
    
    $commentList = get_questionnaire_result_comment_list($tracking_id);

    json_response(200, "Comment List", $commentList);
}

/**********************************************************
 * FUNCTION: CREATE RISKS FROM QUESTIONNARE PENDING RISKS *
 **********************************************************/
function createRisksFromQuestionnairePendingRisksAPI() {
    global $escaper, $lang;
    $pending_risks = $_POST['pending_risks'];

    foreach ($pending_risks as $pending_risk) {
        $pending_risk_id  = $pending_risk['pending_risk_id'];
        $submission_date  = $pending_risk['submission_date'];
        $subject          = $pending_risk['subject'];
        $owner            = $pending_risk['owner'];
        $notes            = $pending_risk['note'];
        $tag_ids          = $pending_risk['tags']; // Sample format: 1,3,4
        
        $assets_asset_groups = isset($pending_risk['assets_asset_groups']) ? implode(',',$pending_risk['assets_asset_groups']) : "";

        $scoring = [
             'scoring_method' => [$pending_risk['scoring_method']],
             
             'likelihood' => [$pending_risk['likelihood']],
             'impact' => [$pending_risk['impact']],
             
             'AccessVector' => [$pending_risk['AccessVector']],
             'AccessComplexity' => [$pending_risk['AccessComplexity']],
             'Authentication' => [$pending_risk['Authentication']],
             'ConfImpact' => [$pending_risk['ConfImpact']],
             'IntegImpact' => [$pending_risk['IntegImpact']],
             'AvailImpact' => [$pending_risk['AvailImpact']],
             'Exploitability' => [$pending_risk['Exploitability']],
             'RemediationLevel' => [$pending_risk['RemediationLevel']],
             'ReportConfidence' => [$pending_risk['ReportConfidence']],
             'CollateralDamagePotential' => [$pending_risk['CollateralDamagePotential']],
             'TargetDistribution' => [$pending_risk['TargetDistribution']],
             'ConfidentialityRequirement' => [$pending_risk['ConfidentialityRequirement']],
             'IntegrityRequirement' => [$pending_risk['IntegrityRequirement']],
             'AvailabilityRequirement' => [$pending_risk['AvailabilityRequirement']],
             
             'DREADDamage' => [$pending_risk['DREADDamage']],
             'DREADReproducibility' => [$pending_risk['DREADReproducibility']],
             'DREADExploitability' => [$pending_risk['DREADExploitability']],
             'DREADAffectedUsers' => [$pending_risk['DREADAffectedUsers']],
             'DREADDiscoverability' => [$pending_risk['DREADDiscoverability']],
             
             'OWASPSkillLevel' => [$pending_risk['OWASPSkillLevel']],
             'OWASPMotive' => [$pending_risk['OWASPMotive']],
             'OWASPOpportunity' => [$pending_risk['OWASPOpportunity']],
             'OWASPSize' => [$pending_risk['OWASPSize']],
             'OWASPEaseOfDiscovery' => [$pending_risk['OWASPEaseOfDiscovery']],
             'OWASPEaseOfExploit' => [$pending_risk['OWASPEaseOfExploit']],
             'OWASPAwareness' => [$pending_risk['OWASPAwareness']],
             'OWASPIntrusionDetection' => [$pending_risk['OWASPIntrusionDetection']],
             'OWASPLossOfConfidentiality' => [$pending_risk['OWASPLossOfConfidentiality']],
             'OWASPLossOfIntegrity' => [$pending_risk['OWASPLossOfIntegrity']],
             'OWASPLossOfAvailability' => [$pending_risk['OWASPLossOfAvailability']],
             'OWASPLossOfAccountability' => [$pending_risk['OWASPLossOfAccountability']],
             'OWASPFinancialDamage' => [$pending_risk['OWASPFinancialDamage']],
             'OWASPReputationDamage' => [$pending_risk['OWASPReputationDamage']],
             'OWASPNonCompliance' => [$pending_risk['OWASPNonCompliance']],
             'OWASPPrivacyViolation' => [$pending_risk['OWASPPrivacyViolation']],
             
             'Custom' => [$pending_risk['Custom']],
             
             'ContributingLikelihood' => [empty($pending_risk['ContributingLikelihood']) ? [] : $pending_risk['ContributingLikelihood']],
             'ContributingImpacts' => empty($pending_risk['ContributingImpacts']) ? [] : $pending_risk['ContributingImpacts'],
        ];
        
        create_risk_from_questionnaire_pending_risk($pending_risk_id, $submission_date, $subject, $owner, $notes, $assets_asset_groups, $tag_ids, $scoring);
    }
    
    $status_message = $escaper->escapeHtml($lang['CreatedRisksFromPendingRisks']);
    set_alert(true, "good", $status_message);
}

function createARiskFromQuestionnairePendingRisksAPI() {
    global $lang, $escaper;

    if ($risk_id = push_risk_from_questionnaire_pending_risk($_POST)) {
        $tracking_id = (int)$_POST['tracking_id'];
        $questionnaire_tracking_info = get_questionnaire_tracking_by_id($tracking_id);

        $message = _lang('PendingRiskAddAuditLog', array(
            'subject' => truncate_to($_POST['subject'], 50),
            'risk_id' => $risk_id,
            'user_name' => $_SESSION['user'],
            'questionnaire_name' => $questionnaire_tracking_info['questionnaire_name'],
            'contact_name' => $questionnaire_tracking_info['contact_name'],
            'date' => format_datetime($questionnaire_tracking_info['sent_at']),
        ), false);

        write_log($tracking_id+1000, $_SESSION['uid'], $message, 'questionnaire_tracking');
        
        $status_message = "Risk ID " . $risk_id . " submitted successfully!";
        json_response(200, $status_message, 'success');
    } else {
        $status_message = $lang['SubjectRiskCannotBeEmpty'];
        json_response(400, $status_message, NULL);
    }
}

function deleteRiskFromQuestionnairePendingRisksAPI() {
    global $lang, $escaper;

    $pending_risk_id = (int)$_POST['pending_risk_id'];
    $tracking_id = (int)$_POST['tracking_id'];

    // Open the database connection
    $db = db_open();
    $stmt = $db->prepare("SELECT `subject` FROM `questionnaire_pending_risks` WHERE `id` = :pending_risk_id;");
    $stmt->bindParam(":pending_risk_id", $pending_risk_id, PDO::PARAM_INT);
    $stmt->execute();
    $subject = $stmt->fetchColumn();
    db_close($db);

    // Mark pending risk as rejected
    delete_questionnaire_pending_risk($pending_risk_id, 2);
    $questionnaire_tracking_info = get_questionnaire_tracking_by_id($tracking_id);

    $message = _lang('PendingRiskDeleteAuditLog', array(
        'subject' => truncate_to($subject, 50),
        'questionnaire_name' => $questionnaire_tracking_info['questionnaire_name'],
        'contact_name' => $questionnaire_tracking_info['contact_name'],
        'date' => format_datetime($questionnaire_tracking_info['sent_at']),
        'user_name' => $_SESSION['user']
    ), false);

    write_log($tracking_id+1000, $_SESSION['uid'], $message, 'questionnaire_tracking');

    $status_message = $escaper->escapeHtml($lang['PendingRiskDeleted']);
    json_response(200, $status_message, NULL);
}

/********************************
 * FUNCTION: COPY QUESTIONNAIRE *
 ********************************/
function copyQuestionnaireAPI(){
    global $escaper, $lang;

    $questionnaire_id = (int)$_POST['id'];

    $questionnaire = get_questionnaire_by_id($questionnaire_id);
    $template_ids = [];
    $contact_ids = [];
    foreach($questionnaire['templates'] as $template){
        $template_ids[] = $template['template_id'];
        $contact_ids[] = ($template['contact_ids'] ? explode(',', $template['contact_ids']) : []);
    }

    add_questionnaire($questionnaire['name']." (Copy)", $template_ids, $contact_ids, $questionnaire['user_instructions']);

    $result = ['status' => true];
    json_response(200, "Copy Questionnaire", $result);
}

/***************************************
 * FUNCTION: FETCH TMP ASSESSMENT PASS *
 ***************************************/
function fetch_assessment_tmp_pass()
{
    if(!file_exists(realpath(__DIR__ . '/includes/init.php'))){
        return false;
    }
    // Load the init file
    require_once(realpath(__DIR__ . '/includes/init.php'));

    // If the TMP_ASSESSMENT_PASS is defined
    if (defined('TMP_ASSESSMENT_PASS'))
    {
        // Return the temporary password
        return TMP_ASSESSMENT_PASS;
    }
    // If the ENCODED_TMP_ASSESSMENT_PASS is defined
    else if (defined('ENCODED_TMP_ASSESSMENT_PASS'))
    {
        // Return the base64 decoded temporary password
        return base64_decode(ENCODED_TMP_ASSESSMENT_PASS);
    }
    // Otherwise return false
    else return false;
}

/********************************************
 * FUNCTION: GET CONTACT SALT BY CONTACT ID *
 ********************************************/
function get_contact_salt_by_id($contact_id)
{
    // Open the database connection
    $db = db_open();

    // Get the salt
    $stmt = $db->prepare("SELECT salt FROM `assessment_contacts` WHERE id = :contact_id");

    $stmt->bindParam(":contact_id", $contact_id, PDO::PARAM_INT);
    $stmt->execute();
    $value = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the salt
    return $value[0]['salt'];
}

/*****************************************
 * FUNCTION: AVAILABLE ASSESSMENTS ARRAY *
 *****************************************/
function available_assessments_array()
{
    // Connect to the database
    $db = db_open();

    $upgraded_assessment_answer_asset_handling = !field_exists_in_table('assets', 'assessment_answers');

    // Create an array to store all of the questions and answers
    $assessment_questions_and_answers = array();

    // Get the list of current assessments
    $stmt = $db->prepare("SELECT * FROM assessments;");
    $stmt->execute();
    $assessments = $stmt->fetchAll();

    // For each entry in the assessments array
    foreach ($assessments as $assessment)
    {
        // Get the assessment values
        $assessment_id = $assessment['id'];
        $assessment_name = $assessment['name'];
        $assessment_created = $assessment['created'];

        // Get the list of questions for that assessment
        $stmt = $db->prepare("SELECT * FROM assessment_questions WHERE assessment_id=:assessment_id;");
        $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
        $stmt->execute();
        $assessment_questions = $stmt->fetchAll();

        // For each entry in the assessment questions array
        foreach ($assessment_questions as $assessment_question)
        {
            // Get the assessment question values
            $assessment_question_id = $assessment_question['id'];
            $assessment_question_assessment_id = $assessment_question['assessment_id'];
            $assessment_question_question = $assessment_question['question'];
            $assessment_question_order = $assessment_question['order'];

            // Get the list of answers for the assessment question
            $stmt = $db->prepare("SELECT * FROM assessment_answers WHERE assessment_id=:assessment_id AND question_id=:question_id;");
            $stmt->bindParam(":assessment_id", $assessment_id, PDO::PARAM_INT);
            $stmt->bindParam(":question_id", $assessment_question_id, PDO::PARAM_INT);
            $stmt->execute();
            $assessment_answers = $stmt->fetchAll();

            // For each entry in the assessment answers array
            foreach ($assessment_answers as $assessment_answer)
            {
                // Get the assessment answer values
                $assessment_answer_id = $assessment_answer['id'];
                $assessment_answer_assessment_id = $assessment_answer['assessment_id'];
                $assessment_answer_question_id = $assessment_answer['question_id'];
                $assessment_answer_answer = $assessment_answer['answer'];
                $assessment_answer_submit_risk = $assessment_answer['submit_risk'];
                $assessment_answer_risk_subject = $assessment_answer['risk_subject'];
                $assessment_answer_assessment_scoring_id = $assessment_answer['assessment_scoring_id'];
                $assessment_answer_risk_owner = $assessment_answer['risk_owner'];
                $assessment_answer_order = $assessment_answer['order'];

                if ($upgraded_assessment_answer_asset_handling) {
                    $assessment_answer_assets = get_assets_and_asset_groups_of_type_as_string($assessment_answer_id, 'assessment_answer');
                } else {
                    $assessment_answer_assets = $assessment_answer['assets'];
                }

                // Add the value to the assessment questions and answers array
                $assessment_questions_and_answers[] = array(
                    'assessment_id' => $assessment_id,
                    'assessment_name' => $assessment_name,
                    'assessment_created' => $assessment_created,
                    'assessment_question_id' => $assessment_question_id,
                    'assessment_question_assessment_id' => $assessment_question_assessment_id,
                    'assessment_question_question' => $assessment_question_question,
                    'assessment_question_order' => $assessment_question_order,
                    'assessment_answer_id' => $assessment_answer_id,
                    'assessment_answer_assessment_id' => $assessment_answer_assessment_id,
                    'assessment_answer_question_id' => $assessment_answer_question_id,
                    'assessment_answer_answer' => $assessment_answer_answer,
                    'assessment_answer_submit_risk' => $assessment_answer_submit_risk,
                    'assessment_answer_risk_subject' => $assessment_answer_risk_subject,
                    'assessment_answer_assessment_scoring_id' => $assessment_answer_assessment_scoring_id,
                    'assessment_answer_risk_owner' => $assessment_answer_risk_owner,
                    'assessment_answer_assets' => $assessment_answer_assets,
                    'assessment_answer_order' => $assessment_answer_order,
                );
            }
        }
    }

    // Disconnect from the database
    db_close($db);

  // Return the array
  return $assessment_questions_and_answers;
}

/*****************************************************************
 * FUNCTION: GET QUESTIONS BY TEMPLATE ID AND SELECTED QUESTIONS *
 *****************************************************************/
function get_questionnaire_template_questions($selected_question_ids, $template_id=0)
{
    // Open the database connection
    $db = db_open();

    // Get the assessment questions and answers
    $sql = "
        SELECT t1.id, t1.question, t2.ordering
        FROM questionnaire_questions t1 
            LEFT JOIN questionnaire_template_question t2 ON t1.id=t2.questionnaire_question_id AND t2.questionnaire_template_id=:template_id
        WHERE
            FIND_IN_SET(t1.id, :question_ids)
        ORDER BY 
            t2.ordering, t1.question
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":template_id", $template_id, PDO::PARAM_INT);
    $question_ids = implode(",", $selected_question_ids);
    $stmt->bindParam(":question_ids", $question_ids, PDO::PARAM_STR);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);
    
    return $array;
    
}

/*******************************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE TEMPLATE QUESTIONS DATATABLE *
 *******************************************************************************/
function questionnaireTemplateQuestionsDynamicAPI(){
    global $lang;
    global $escaper;

    $reorder = $_GET['reorder'];
    if($reorder)
    {
//        $selected_ids = $_GET['selected_ids'];
        
    }
    
    $draw = $_GET['draw'];
    
    $template_id = (int)$_GET['template_id'];
    $selected_question_ids = empty($_GET['selected_ids']) ? [] : $_GET['selected_ids'];
    
    $questions = get_questionnaire_template_questions($selected_question_ids, $template_id);
 
    $data = array();
    
    foreach ($questions as $key=>$question)
    {
        $data[] = [
            "<span data='".$_SESSION['base_url']."/assessments/questionnaire_questions.php?action=edit_question&id={$question['id']}'>".$escaper->escapeHtml($question['question'])."</span>"."&nbsp; <input type='hidden' name='ordering[{$question['id']}]' value='{$question['ordering']}'>",
        ];
    }
    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => count($questions),
        'recordsFiltered' => count($questions),
    );

    echo json_encode($result);
    exit;    
}

/****************************************************************
 * FUNCTION: GET GROUP VALUES FOR QUESTIONNAIRE ANALYSIS TABLES *
 ****************************************************************/
function getQuestionnaireAnalysisGroupValues($report, $group)
{
    $report = !empty($report) ? $report : "all-risks";
    
    if($report == "all-risks")
    {
        $where = " (t1.status=0 OR t1.status=1) ";
    }
    elseif($report == "added-risks")
    {
        $where = " (t1.status=1) ";
    }
    elseif($report == "pending-risks")
    {
        $where = " (t1.status=0) ";
    }
    else
    {
        $where = " 0 ";
    }
    
    
    if(!$group){
        return false;
    }
    elseif($group == "risk-score"){
        $group_value_field = "t4.calculated_risk";
        $group_text_field = "t4.calculated_risk";
    }
    elseif($group == "questionnaire"){
        $group_value_field = "t3.id";
        $group_text_field = "t3.name";
    }
    elseif($group == "company"){
        $group_value_field = "t6.company";
        $group_text_field = "t6.company";
    }
    else{
        return false;
    }
    
    // Open the database connection
    $db = db_open();

    // Get the pending risks
    $stmt = $db->prepare("
        SELECT 
            ifnull({$group_value_field}, 'Undefined') as group_value, ifnull({$group_text_field}, 'Undefined') as group_text
        FROM 
            `questionnaire_pending_risks` t1 
            INNER JOIN `questionnaire_tracking` t2 on t1.questionnaire_tracking_id=t2.id
            INNER JOIN `questionnaires` t3 on t2.questionnaire_id=t3.id
            LEFT JOIN `questionnaire_scoring` t4 on t1.questionnaire_scoring_id=t4.id
            LEFT JOIN questionnaire_scoring_contributing_impacts rsci ON t4.scoring_method=6 AND t4.id=rsci.questionnaire_scoring_id
            LEFT JOIN `tags` t5 ON FIND_IN_SET(t5.id, t1.tag_ids)
            LEFT JOIN `assessment_contacts` t6 ON t2.contact_id=t6.id
        WHERE 
            {$where}
        GROUP BY
            {$group_value_field}
            ;
    ");

    $stmt->execute();

    // Store the list in the array
    $group_values = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $group_values;
}

/*********************************************************************
 * FUNCTION: GET DATA FOR ASSESSMENT QUESIONNAIRE ANALYSIS DATATABLE *
 *********************************************************************/
function questionnaireAanalysisDynamicAPI(){
    global $lang;
    global $escaper;

    $draw = $_GET['draw'];
    
    $report     = !empty($_GET['report']) ? $_GET['report'] : "all-risks";
    $group      = !empty($_GET['group']) ? $_GET['group'] : "";
    $start      = (int)$_GET['start'];
    $length     = (int)$_GET['length'];
    
    if($report == "all-risks")
    {
        $where = " (t1.status=0 OR t1.status=1) ";
    }
    elseif($report == "added-risks")
    {
        $where = " (t1.status=1) ";
    }
    elseif($report == "pending-risks")
    {
        $where = " (t1.status=0) ";
    }
    else
    {
        $where = " 0 ";
    }
    
    // If group has empty value, shows all analysis data
    if(!empty($group))
    {
        $groupvalue = base64_decode($_GET['groupvalue']);
        if($group == "risk-score"){
            $group_value_field = "t4.calculated_risk";
            $group_text_field = "t4.calculated_risk";
        }
        elseif($group == "questionnaire"){
            $group_value_field = "t3.id";
            $group_text_field = "t3.name";
        }
        elseif($group == "company"){
            $group_value_field = "t6.company";
            $group_text_field = "t6.company";
        }

        if($groupvalue == "Undefined")
        {
            $where .= " AND {$group_value_field} IS NULL ";
        }
        else
        {
            if($group == "risk-score")
            {
                $where .= " AND abs({$group_value_field} - :group_value) < 0.001 ";
            }
            else
            {
                $where .= " AND {$group_value_field} = :group_value ";
            }
        }
    }
    
    // Open the database connection
    $db = db_open();

    // Get the pending risks
    $stmt = $db->prepare("
        SELECT 
            SQL_CALC_FOUND_ROWS
            t4.*,
            t1.id,
            t1.subject,
            t1.owner,
            t1.affected_assets,
            t1.comment,
            t1.tag_ids,
            t1.status,
            t1.questionnaire_tracking_id,
            GROUP_CONCAT(DISTINCT CONCAT(t5.id, '---', t5.tag) SEPARATOR '+++') tags,
            t1.submission_date,
            t3.name questionnaire_name,
            group_concat(distinct CONCAT_WS('_', rsci.contributing_risk_id, rsci.impact)) as Contributing_Risks_Impacts,
            t6.company
        FROM 
            `questionnaire_pending_risks` t1 
            INNER JOIN `questionnaire_tracking` t2 on t1.questionnaire_tracking_id=t2.id
            INNER JOIN `questionnaires` t3 on t2.questionnaire_id=t3.id
            LEFT JOIN `questionnaire_scoring` t4 on t1.questionnaire_scoring_id=t4.id
            LEFT JOIN questionnaire_scoring_contributing_impacts rsci ON t4.scoring_method=6 AND t4.id=rsci.questionnaire_scoring_id
            LEFT JOIN `tags` t5 ON FIND_IN_SET(t5.id, t1.tag_ids)
            LEFT JOIN `assessment_contacts` t6 ON t2.contact_id=t6.id
        WHERE 
            {$where}
        GROUP BY
            t1.id
        ORDER BY
            t1.submission_date desc
        ". ($length!=-1 ?  " Limit {$start}, {$length}" : "") . ";
    ");
    if(!empty($group) && $groupvalue != "Undefined")
    {
        $stmt->bindParam(":group_value", $groupvalue);
    }
    
    $stmt->execute();

    // Store the list in the array
    $risks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $totalCount = $stmt->fetchColumn();
    
    // Close the database connection
    db_close($db);

    $affected_assets_placeholder = $escaper->escapeHtml($lang['AffectedAssetsWidgetPlaceholder']);

    $data = array();
    
    foreach ($risks as $key=>$risk)
    {
        $pending_risk_html = "";
        
        if($risk['tags'])
        {
            $tags = array_map(function($id_name) use($escaper){
                $id_name_arr = explode("---", $id_name);
                return ['label' => $escaper->escapeHtml($id_name_arr[1]), 'value' => $id_name_arr[0]];
            }, explode("+++", $risk['tags']) );
            $tag_string = json_encode($tags);
        }
        else
        {
            $tag_string = "";
        }
        
        $pending_risk_html .= "<input type='hidden' name='tracking_id' value='{$risk['questionnaire_tracking_id']}'>";
        $pending_risk_html .= "<div class=\"hero-unit questionnaire-pending-risk-form\">\n";
        $pending_risk_html .= "<form name=\"submit_risk\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">\n";
        $pending_risk_html .= "<input type=\"hidden\" name=\"pending_risk_id\" value=\"" . $escaper->escapeHtml($risk['id']) . "\" />\n";
        $pending_risk_html .= "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
        $pending_risk_html .= "<tr>\n";
        $pending_risk_html .= "<td style=\"white-space: nowrap;\">".$lang['SubmissionDate'] . ":&nbsp;&nbsp;</td>\n";
        $pending_risk_html .= "<td width=\"99%\"><input type=\"text\"  style=\"width: 97%;\" name=\"submission_date\" value=\"" . $escaper->escapeHtml($risk['submission_date']) . "\" /></td>\n";
        $pending_risk_html .= "</tr>\n";
        $pending_risk_html .= "<tr>\n";
        $pending_risk_html .= "<td style=\"white-space: nowrap;\">".$lang['Subject'] . ":&nbsp;&nbsp;</td>\n";
        $pending_risk_html .= "<td width=\"99%\"><input type=\"text\" style=\"width: 97%;\" required name=\"subject\" value=\"" . $escaper->escapeHtml($risk['subject']) . "\" /></td>\n";
        $pending_risk_html .= "</tr>\n";
        ob_start();

        if($risk['scoring_method']){
            $Contributing_Impacts = get_contributing_impacts_by_subjectimpact_values($risk['Contributing_Risks_Impacts']);
            display_score_html_from_pending_risk($risk['scoring_method'], $risk['Custom'], $risk['CLASSIC_likelihood'], $risk['CLASSIC_impact'], $risk['CVSS_AccessVector'], $risk['CVSS_AccessComplexity'], $risk['CVSS_Authentication'], $risk['CVSS_ConfImpact'], $risk['CVSS_IntegImpact'], $risk['CVSS_AvailImpact'], $risk['CVSS_Exploitability'], $risk['CVSS_RemediationLevel'], $risk['CVSS_ReportConfidence'], $risk['CVSS_CollateralDamagePotential'], $risk['CVSS_TargetDistribution'], $risk['CVSS_ConfidentialityRequirement'], $risk['CVSS_IntegrityRequirement'], $risk['CVSS_AvailabilityRequirement'], $risk['DREAD_DamagePotential'], $risk['DREAD_Reproducibility'], $risk['DREAD_Exploitability'], $risk['DREAD_AffectedUsers'], $risk['DREAD_Discoverability'], $risk['OWASP_SkillLevel'], $risk['OWASP_Motive'], $risk['OWASP_Opportunity'], $risk['OWASP_Size'], $risk['OWASP_EaseOfDiscovery'], $risk['OWASP_EaseOfExploit'], $risk['OWASP_Awareness'], $risk['OWASP_IntrusionDetection'], $risk['OWASP_LossOfConfidentiality'], $risk['OWASP_LossOfIntegrity'], $risk['OWASP_LossOfAvailability'], $risk['OWASP_LossOfAccountability'], $risk['OWASP_FinancialDamage'], $risk['OWASP_ReputationDamage'], $risk['OWASP_NonCompliance'], $risk['OWASP_PrivacyViolation'], $risk['Contributing_Likelihood'], $Contributing_Impacts);
        }
        else{
            display_score_html_from_pending_risk(5, $risk['Custom']);
        }
        $pending_risk_html .= ob_get_contents();
        ob_end_clean();

        $pending_risk_html .= "<tr>\n";
        $pending_risk_html .= "<td style=\"white-space: nowrap;\">".$escaper->escapeHtml($lang['Owner']) . ":&nbsp;&nbsp;</td>\n";
        $pending_risk_html .= "<td width=\"99%\">\n";
        
        ob_start();
        create_dropdown("enabled_users", $risk['owner'], "owner");
        $pending_risk_html .= ob_get_contents();
        ob_end_clean();

        $pending_risk_html .= "</td>\n";
        $pending_risk_html .= "</tr>\n";
        $pending_risk_html .= "<tr>\n";
        $pending_risk_html .= "<td style=\"white-space: nowrap;\">".$lang['AffectedAssets'] . ":&nbsp;&nbsp;</td>\n";
        $pending_risk_html .= "<td width=\"99%\">";

        $pending_risk_html .= "<select class='assets-asset-groups-select' name='assets_asset_groups[]' multiple placeholder='{$affected_assets_placeholder}'>";

        if ($risk['affected_assets']){
            foreach(explode(',', $risk['affected_assets']) as $value) {

                $value = $name = trim($value);

                if (preg_match('/^\[(.+)\]$/', $name, $matches)) {
                    $name = trim($matches[1]);
                    $type = 'group';
                } else $type = 'asset';

                $pending_risk_html .= "<option value='" . $escaper->escapeHtml($value) . "' selected data-class='$type'>" . $escaper->escapeHtml($name) . "</option>";
            }
        }

        $pending_risk_html .= "</select>";

        $pending_risk_html .= "</td>\n";
        $pending_risk_html .= "</tr>\n";
        $pending_risk_html .= "<tr>\n";
            $pending_risk_html .= "<td style=\"white-space: nowrap;\">".$escaper->escapeHtml($lang['AdditionalNotes']) . ":&nbsp;&nbsp;</td>\n";
            $pending_risk_html .= "<td ><textarea name=\"note\" style=\"width: 97%;\" cols=\"50\" rows=\"3\" id=\"note\">Risk created using the &quot;" . $escaper->escapeHtml($risk['questionnaire_name']) . "&quot; questionnaire.\n".$escaper->escapeHtml(try_decrypt($risk['comment']))."</textarea></td>\n";
        $pending_risk_html .= "</tr>\n";
        $pending_risk_html .= "<tr>\n";
            $pending_risk_html .= "<td>". $escaper->escapeHtml($lang['Tags']) ."</td>\n";
            $pending_risk_html .= "<td><input type=\"text\" readonly class=\"tags\" name=\"tags\" value=\"\" data-selectize-value='{$tag_string}' /></td>\n";
        $pending_risk_html .= "</tr>\n";
        $pending_risk_html .= "</table>\n";
        $pending_risk_html .= "<div class=\"form-actions\">\n";
        $pending_risk_html .= "<button type=\"submit\" name=\"add\" class=\"btn btn-danger\">" . $escaper->escapeHtml($lang['Add']) . "</button>\n";
        $pending_risk_html .= "<button type=\"submit\" name=\"delete\" class=\"btn\">" . $escaper->escapehtml($lang['Delete']) . "</button>\n";
        $pending_risk_html .= "</div>\n";
        $pending_risk_html .= "</form>\n";
        $pending_risk_html .= "</div>\n";

        
        $data[] = [
            $risk['subject'],
            $risk['calculated_risk'],
            $escaper->escapeHtml($risk['questionnaire_name']),
            $escaper->escapeHtml(try_decrypt($risk['company'])),
            format_datetime($risk['submission_date']),
            $escaper->escapeHtml($risk['status'] == 0 ? $lang['PendingRisk'] : $lang['AddedRisk']),
            '<div class="text-center"><button class="view-detail-risk" data-status="'. $risk['status'] .'">View</button></div> <div class="inner-content-container" style="display: none">'. $pending_risk_html .'</div> '
            
        ];
    }
    $result = array(
        'draw' => $draw,
        'data' => $data,
        'recordsTotal' => $totalCount,
        'recordsFiltered' => $totalCount,
    );

    echo json_encode($result);
    exit;    
}

?>

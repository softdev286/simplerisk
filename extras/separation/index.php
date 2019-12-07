<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to       *
 * enforce that users only see the risks for the teams that they    *
 * have been added as a member of.                                  *
 ********************************************************************/

// Extra Version
define('SEPARATION_EXTRA_VERSION', '20191130-001');
    
// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));

require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_separation_extra_database();

/******************************************
 * FUNCTION: ENABLE TEAM SEPARATION EXTRA *
 ******************************************/
function enable_team_separation_extra()
{
    prevent_extra_double_submit("team_separation", true);

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'team_separation', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
    $stmt->execute();

    // Enable all permissions to true 
    $permissions = array(
        'allow_owner_to_risk'                           => 1,
        'allow_ownermanager_to_risk'                    => 1,
        'allow_submitter_to_risk'                       => 1,
        'allow_team_member_to_risk'                     => 1,
        'allow_stakeholder_to_risk'                     => 1,
        'allow_all_to_risk_noassign_team'               => 0,
        
        'allow_control_owner_to_see_test_and_audit'     => 1,
        'allow_tester_to_see_test_and_audit'            => 1,
        'allow_stakeholders_to_see_test_and_audit'      => 1,
        'allow_team_members_to_see_test_and_audit'      => 1,
        'allow_everyone_to_see_test_and_audit'          => 1,
        
    );
    
    update_permission_settings($permissions);
    
    // Audit log entry for Extra turned on
    $message = "Team-Based Separation Extra was toggled on by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');
    
    // Close the database connection
    db_close($db);
}

/*******************************************
 * FUNCTION: DISABLE TEAM SEPARATION EXTRA *
 *******************************************/
function disable_team_separation_extra()
{
    prevent_extra_double_submit("team_separation", false);

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'team_separation'");
    $stmt->execute();

    // Disable all permissions to true 
    $permissions = array(
        'allow_owner_to_risk'                           => 0,
        'allow_ownermanager_to_risk'                    => 0,
        'allow_submitter_to_risk'                       => 0,
        'allow_team_member_to_risk'                     => 0,
        'allow_stakeholder_to_risk'                     => 0,
        'allow_all_to_risk_noassign_team'               => 0,

        'allow_control_owner_to_see_test_and_audit'     => 0,
        'allow_tester_to_see_test_and_audit'            => 0,
        'allow_stakeholders_to_see_test_and_audit'      => 0,
        'allow_team_members_to_see_test_and_audit'      => 0,
        'allow_everyone_to_see_test_and_audit'          => 0,
    );
    update_permission_settings($permissions);

    // Audit log entry for Extra turned off
    $message = "Team-Based Separation Extra was toggled off by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);
}

function update_permission_settings($permissions){
    // Open the database connection
    $db = db_open();

    foreach($permissions as $key => $value){
        // Add or Update the permission to risk.
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = :name, `value` = :value ON DUPLICATE KEY UPDATE `value` = :value");
        $stmt->bindParam(":name", $key, PDO::PARAM_STR, 50);
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);

        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
}

/***************************
 * FUNCTION: GET RISK TEAM *
 ***************************/
function get_risk_team($risk_id) 
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT team FROM `risks` WHERE `id` = :risk_id");
    $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();

    // If the risk has no team
    if ($array['team'] == 0)
    {
        // Make it viewable to everyone
        $team = "all";
    }
    // Otherwise
    else
    {
        // Get the team id
        $team = $array['team'];
    }

    // Close the database connection
    db_close($db);

    return $team;
}

/********************************
 * FUNCTION: EXTRA GRANT ACCESS *
 ********************************/
function extra_grant_access($user_id, $risk_id)
{
    if(is_admin($user_id)){
        return true;
    }

    // Subtract 1000 to get the actual ID
    $risk_id = (int)$risk_id - 1000;

    // Get Risk By Id

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT owner, manager, team ,additional_stakeholders, submitted_by  FROM risks where id=:id;");
    $stmt->bindParam(":id", $risk_id, PDO::PARAM_INT);
    $stmt->execute();
    $risk = $stmt->fetch();

    // Close the database connection
    db_close($db);

    if(get_setting('allow_all_to_risk_noassign_team')){
        // If risk has no asssigned team, allow all users to this risk
        if($risk['team'] == ""){
            return true;
        }
    }
    
    if(get_setting('allow_owner_to_risk')){
        if($risk['owner'] == $user_id){
            return true;
        }
    }
    if(get_setting('allow_ownermanager_to_risk')){
        if($risk['manager'] == $user_id){
            return true;
        }
    }
    if(get_setting('allow_submitter_to_risk')){
        if($risk['submitted_by'] == $user_id){
            return true;
        }
    }
    
    if(get_setting('allow_stakeholder_to_risk')){
        if(in_array($user_id, explode(",", $risk['additional_stakeholders']))){
            return true;
        }
    }
    
    if(get_setting('allow_team_member_to_risk')){
        // Get the teams the user is assigned to
        $user_teams = get_user_teams($user_id);

        // Get the team assigned to the risk
        $risk_team = get_risk_team($risk_id);

        // If the user has access to every team or the risk does not have a team assigned
        if ($user_teams == "all" || $risk_team == "all")
        {
            return true;
        }

        // If the user has access to no teams
        if ($user_teams == "none")
        {
            return false;
        }

        $risk_teams = explode(",", $risk_team);
        
        foreach($risk_teams as $val){
            // Pattern is a team id surrounded by colons
            $regex_pattern = "/:" . $val .":/";

            // Check if the risk team is in the user teams
            if (preg_match($regex_pattern, $user_teams))
            {
                return true;
            }
        }
    }
    
    return false;
}

/***********************************
 * FUNCTION: STRIP NO ACCESS RISKS *
 ***********************************/
function strip_no_access_risks($risks, $user_id = null) {

    // Return empty response in case of invalid input
    if (!$risks)
        return [];

    //If no user id is presented, using the session's user
    if ($user_id === null)
        $user_id = $_SESSION['uid'];
    
    // Initialize the access array
    $access_array = array();
    // For each risk
    foreach ($risks as $risk)
    {
        if(!isset($risk['id'])){
            continue;
        }
        // Risk ID is the actual ID plus 1000
        $risk_id = (int)$risk['id'] + 1000;

        // If the user should have access to the risk
        if (extra_grant_access($user_id, $risk_id))
        {
            // Add the risk to the access array
            $access_array[] = $risk;
        }
    }

    return $access_array;
}

/***********************************************
 * FUNCTION: STRIP NO ACCESS OPEN RISK SUMMARY *
 ***********************************************/
function strip_no_access_open_risk_summary($veryhigh, $high, $medium, $low, $teams = false)
{
    if($teams !== false){
        if($teams == ""){
            $teams_query = " AND 0 ";
        }else{
            $options = explode(",", $teams);
            $teams_query = generate_or_query($options, 'team', 'b');
            if(in_array("0", $options)){
                $teams_query .= " OR b.team='' ";
            }
            $teams_query = " AND ( {$teams_query} ) ";
        }
    }else{
        $teams_query = "";
    }
    $very_high_display_name = get_risk_level_display_name('Very High');
    $high_display_name      = get_risk_level_display_name('High');
    $medium_display_name    = get_risk_level_display_name('Medium');
    $low_display_name       = get_risk_level_display_name('Low');
    $insignificant_display_name = get_risk_level_display_name('Insignificant');

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("select a.id, CASE WHEN residual_risk >= :veryhigh THEN :very_high_display_name WHEN residual_risk < :veryhigh AND residual_risk >= :high THEN :high_display_name WHEN residual_risk < :high AND residual_risk >= :medium THEN :medium_display_name WHEN residual_risk < :medium AND residual_risk >= :low THEN :low_display_name WHEN residual_risk < :low AND residual_risk >= 0 THEN :insignificant_display_name END AS level 
        FROM (
            select a.calculated_risk, ROUND((a.calculated_risk - (a.calculated_risk * GREATEST(IFNULL(c.mitigation_percent,0), IFNULL(MAX(fc.mitigation_percent), 0)) / 100)), 2) as residual_risk, b.id 
            FROM `risk_scoring` a 
                JOIN `risks` b ON a.id = b.id 
                LEFT JOIN mitigations c ON b.id = c.risk_id 
                LEFT JOIN framework_controls fc ON FIND_IN_SET(fc.id, c.mitigation_controls) AND fc.deleted=0
            WHERE b.status != \"Closed\" {$teams_query} GROUP BY b.id
        ) AS a 
        ORDER BY 
            a.residual_risk DESC; 
    ");

    $stmt->bindParam(":veryhigh", $veryhigh, PDO::PARAM_STR, 4);
    $stmt->bindParam(":high", $high, PDO::PARAM_STR, 4);
    $stmt->bindParam(":medium", $medium, PDO::PARAM_STR, 4);
    $stmt->bindParam(":low", $low, PDO::PARAM_STR, 4);
    $stmt->bindParam(":very_high_display_name", $very_high_display_name, PDO::PARAM_STR);
    $stmt->bindParam(":high_display_name", $high_display_name, PDO::PARAM_STR);
    $stmt->bindParam(":medium_display_name", $medium_display_name, PDO::PARAM_STR);
    $stmt->bindParam(":low_display_name", $low_display_name, PDO::PARAM_STR);
    $stmt->bindParam(":insignificant_display_name", $insignificant_display_name, PDO::PARAM_STR);
    
    $stmt->execute();

    // Store the list in the array
    $risks = $stmt->fetchAll();

    // Initialize the access array
    $access_array = array();

    // For each risk
    foreach ($risks as $risk)
    {
        // Risk ID is the actual ID plus 1000
        $risk_id = (int)$risk['id'] + 1000;

        // If the user should have access to the risk
        if (extra_grant_access($_SESSION['uid'], $risk_id))
        {
            // Add the risk to the access array
            $access_array[] = $risk;
        }
    }
    
    // Close the database connection
    db_close($db);

    // Set the level to empty
    $level = "";
    $level_count = 0;
    
    // Count the number of risks at each level
    foreach ($access_array as $risk)
    {
        // Get the current level
        $current_level = $risk['level'];

        // If the level is not new
        if ($current_level == $level)
        {
            $level_count++;
        }
        else
        {
            // If the level is not empty
            if ($level != "")
            {
                // Add the previous level to the array
                $level_array[] = array('level'=>$level, 'num'=>$level_count);
            }

            // Set the new level and reset the count
            $level = $current_level;
            $level_count = 1;
        }
    }

    // Update the final level
    $level_array[] = array('level'=>$level, 'num'=>$level_count);

    return $level_array;
}

/**************************************
 * FUNCTION: STRIP NO ACCESS RISK PIE *
 **************************************/
function strip_no_access_risk_pie($pie, $teams = false)
{
    if($teams !== false){
        if($teams == ""){
            $teams_query = " AND 0 ";
        }else{
            $options = explode(",", $teams);
            if($pie == "close_reason"){
                $teams_query = generate_or_query($options, 'team', 'c');
                if(in_array("0", $options)){
                    $teams_query .= " OR c.team='' ";
                }
            }else{
                $teams_query = generate_or_query($options, 'team', 'a');
                if(in_array("0", $options)){
                    $teams_query .= " OR a.team='' ";
                }
            }
            $teams_query = " AND ( {$teams_query} ) ";
        }
    }else{
        $teams_query = "";
    }

    // Open the database connection
    $db = db_open();

    switch($pie)
    {
        case 'status':
            $field = "status";
            $stmt = $db->prepare("SELECT id, status FROM `risks` a WHERE a.status != \"Closed\" {$teams_query} ORDER BY a.status DESC");
            $stmt->execute();
            break;
        case 'location':
            $field = "name";
            $stmt = $db->prepare("SELECT id, GROUP_CONCAT(b.name separator '; ') name  FROM `risks` a LEFT JOIN `location` b ON FIND_IN_SET(b.value, a.location) WHERE status != \"Closed\" {$teams_query} GROUP BY a.id ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'source':
            $field = "name";
            $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `source` b ON a.source = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'category':
            $field = "name";
            $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `category` b ON a.category = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'team':
            $field = "name";
            $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `team` b ON a.team = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'technology':
            $field = "name";
            $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `technology` b ON a.technology = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'owner':
            $field = "name";
            $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `user` b ON a.owner = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'manager':
            $field = "name";
            $stmt = $db->prepare("SELECT id, b.name FROM `risks` a LEFT JOIN `user` b ON a.manager = b.value WHERE status != \"Closed\" {$teams_query} ORDER BY b.name DESC");
            $stmt->execute();
            break;
        case 'scoring_method':
            $field = "name";
            $stmt = $db->prepare("SELECT a.id, CASE WHEN scoring_method = 5 THEN 'Custom' WHEN scoring_method = 4 THEN 'OWASP' WHEN scoring_method = 3 THEN 'DREAD' WHEN scoring_method = 2 THEN 'CVSS' WHEN scoring_method = 1 THEN 'Classic' END AS name, COUNT(*) AS num FROM `risks` a LEFT JOIN `risk_scoring` b ON a.id = b.id WHERE status != \"Closed\" {$teams_query} ORDER BY b.scoring_method DESC");
            $stmt->execute();
            break;
        case 'close_reason':
            $field = "name";
            $stmt = $db->prepare("SELECT a.close_reason, a.risk_id as id, b.name, MAX(closure_date) FROM `closures` a JOIN `close_reason` b ON a.close_reason = b.value JOIN `risks` c ON a.risk_id = c.id WHERE c.status = \"Closed\" {$teams_query} GROUP BY risk_id ORDER BY name DESC;");
            $stmt->execute();
            break;
        default:
            break;
    }

    // Store the list in the array
    $risks = $stmt->fetchAll();

    // Initialize the access array
    $access_array = array();

    // For each risk
    foreach ($risks as $risk)
    {
        // Risk ID is the actual ID plus 1000
        $risk_id = (int)$risk['id'] + 1000;

        // If the user should have access to the risk
        if (extra_grant_access($_SESSION['uid'], $risk_id))
        {
            // Add the risk to the access array
            $access_array[] = $risk;
        }
    }

    // Close the database connection
    db_close($db);

    // Set the value to empty
    $value = "";
    $value_count = 0;

    // Count the number of risks for each value
    foreach ($access_array as $risk)
    {
        // Get the current value
        $current_value = $risk[$field];

        // If the value is not new
        if ($current_value == $value)
        {
            $value_count++;
        }
        else
        {
            // If the value is not empty
            if ($value != "")
            {
                    // Add the previous value to the array
                    $value_array[] = array($field=>$value, 'num'=>$value_count);
            }

            // Set the new value and reset the count
            $value = $current_value;
            $value_count = 1;
        }
    }

    // Update the final value
    if ($value == null) $value = "Unassigned";
    $value_array[] = array($field=>$value, 'num'=>$value_count);

    return $value_array;
}

/*************************************
 * FUNCTION: TEAM SEPARATION VERSION *
 *************************************/
function team_separation_version()
{
    // Return the version
    return SEPARATION_EXTRA_VERSION;
}

/*********************************
 * FUNCTION: GET USERS WITH TEAM *
 *********************************/
function get_users_with_team($team)
{
        // Pattern is a team id surrounded by colons
        $team = "%:" . $team .":%";

        // Open the database connection
        $db = db_open();

        // Get the list of all teams
        $stmt = $db->prepare("SELECT username FROM user where teams LIKE :team ORDER BY username");
    $stmt->bindParam(":team", $team, PDO::PARAM_STR, 200);
        $stmt->execute();

        // Store the list in the array
        $users = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

    // Return the users array
    return $users;
}

/******************************************
 * FUNCTION: GET NUMBER OF RISKS FOR TEAM *
 ******************************************/
function get_number_of_risks_for_team($team)
{
    // Open the database connection
    $db = db_open();

    // Get the list of all teams
    $stmt = $db->prepare("SELECT count(*) as count FROM risks WHERE team = :team");
    $stmt->bindParam(":team", $team, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);

    // Return the count
    $array = $stmt->fetch();
    return $array['count'];
}

/*************************************
 * FUNCTION: DISPLAY TEAMS AND USERS *
 *************************************/
function display_teams_and_users()
{
    global $escaper;
    global $lang;

        // Open the database connection
        $db = db_open();

    // Get the list of all teams
    $stmt = $db->prepare("SELECT * FROM team ORDER BY name");
    $stmt->execute();

    // Store the list in the array
        $teams = $stmt->fetchAll();

    // For each team
    foreach ($teams as $team)
    {
        // Display the table header
        echo "<table class=\"table table-bordered table-striped table-condensed sortable table-margin-top\">\n";
        echo "<thead>\n";
        echo "<tr>\n";
        echo "<th>" . $escaper->escapeHtml($team['name']) . "</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";

        // Get the list of users for this team
        $users = get_users_with_team($team['value']);

        // If there are no users
        if (empty($users))
        {
            // Get the number of risks for the team
            $count = get_number_of_risks_for_team($team['value']);
            echo "<tr><td><font color=\"red\"><b>" . $count . " RISK(S) AND NO USERS ASSIGNED TO TEAM</b></font></td></tr>\n";
        }
        else
        {
            // For each user
            foreach ($users as $user)
            {
                echo "<tr><td>" . $escaper->escapeHtml($user['username']) . "</td></tr>\n";
            }
        }

        echo "</tbody>\n";
        echo "</table>\n";
    }

        // Close the database connection
        db_close($db);
}

/*************************************
 * FUNCTION: DISPLAY TEAM SEPARATION *
 *************************************/
function display_team_separation()
{
    global $escaper;
    global $lang;

    echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . team_separation_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";

    display_group_permissions();
    
}

/**************************************
 * FUNCTION: DISPLAY TEAM PERMISSIONS *
 **************************************/
function display_group_permissions()
{
    global $escaper;
    global $lang;
    
    echo "  <form method=\"POST\">\n";
    echo "      <h3>Risk Permissions</h3>\n";
    echo "      <p><input ".(get_setting('allow_owner_to_risk') ? "checked": "")." name=\"allow_owner_to_risk\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_owner_to_risk\" type=\"checkbox\">  <label for=\"allow_owner_to_risk\">&nbsp;&nbsp; ".$lang['AllowOwnerToSeeRiskDetails']."</label></p>\n";
    echo "      <p><input ".(get_setting('allow_ownermanager_to_risk') ? "checked": "")." name=\"allow_ownermanager_to_risk\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_ownermanager_to_risk\" type=\"checkbox\">  <label for=\"allow_ownermanager_to_risk\">&nbsp;&nbsp; ".$lang['AllowOwnerManagerToSeeRiskDetails']."</label></p>\n";
    echo "      <p><input ".(get_setting('allow_submitter_to_risk') ? "checked": "")." name=\"allow_submitter_to_risk\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_submitter_to_risk\" type=\"checkbox\">  <label for=\"allow_submitter_to_risk\">&nbsp;&nbsp; ".$lang['AllowRiskSubmitterToSeeRiskDetails']."</label></p>\n";
    echo "      <p><input ".(get_setting('allow_team_member_to_risk') ? "checked": "")." name=\"allow_team_member_to_risk\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_team_member_to_risk\" type=\"checkbox\">  <label for=\"allow_team_member_to_risk\">&nbsp;&nbsp; ".$lang['AllowTeamMembersToSeeRiskDetails']."</label></p>\n";
    echo "      <p><input ".(get_setting('allow_stakeholder_to_risk') ? "checked": "")." name=\"allow_stakeholder_to_risk\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_stakeholder_to_risk\" type=\"checkbox\">  <label for=\"allow_stakeholder_to_risk\">&nbsp;&nbsp; ".$lang['AllowAdditionalStakeholdersToSeeRiskDetails']."</label></p>\n";
    echo "      <p><input ".(get_setting('allow_all_to_risk_noassign_team') ? "checked": "")." name=\"allow_all_to_risk_noassign_team\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_all_to_risk_noassign_team\" type=\"checkbox\">  <label for=\"allow_all_to_risk_noassign_team\">&nbsp;&nbsp; ".$lang['AllowAllUsersToSeeRisksNotAssignedToTeam']."</label></p>\n";

    echo "      <br>";
    echo "      <h3>Test/Audit Permissions</h3>\n";
    echo "      <p><input ".(get_setting('allow_control_owner_to_see_test_and_audit') ? "checked": "")." name=\"allow_control_owner_to_see_test_and_audit\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_control_owner_to_see_test_and_audit\" type=\"checkbox\">  <label for=\"allow_control_owner_to_see_test_and_audit\">&nbsp;&nbsp; ".$lang['AllowControlOwnerToSeeTestAndAuditDetails']."</label></p>\n";
    echo "      <p><input ".(get_setting('allow_tester_to_see_test_and_audit') ? "checked": "")." name=\"allow_tester_to_see_test_and_audit\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_tester_to_see_test_and_audit\" type=\"checkbox\">  <label for=\"allow_tester_to_see_test_and_audit\">&nbsp;&nbsp; ".$lang['AllowTesterToSeeTestAndAuditDetails']."</label></p>\n";
    echo "      <p><input ".(get_setting('allow_stakeholders_to_see_test_and_audit') ? "checked": "")." name=\"allow_stakeholders_to_see_test_and_audit\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_stakeholders_to_see_test_and_audit\" type=\"checkbox\">  <label for=\"allow_stakeholders_to_see_test_and_audit\">&nbsp;&nbsp; ".$lang['AllowStakeholdersToSeeTestAndAuditDetails']."</label></p>\n";
    echo "      <p><input ".(get_setting('allow_team_members_to_see_test_and_audit') ? "checked": "")." name=\"allow_team_members_to_see_test_and_audit\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_team_members_to_see_test_and_audit\" type=\"checkbox\">  <label for=\"allow_team_members_to_see_test_and_audit\">&nbsp;&nbsp; ".$lang['AllowAssignedTeamsMembersToSeeTestAndAuditDetails']."</label></p>\n";    
    echo "      <p><input ".(get_setting('allow_everyone_to_see_test_and_audit') ? "checked": "")." name=\"allow_everyone_to_see_test_and_audit\" class=\"hidden-checkbox\" size=\"2\" value=\"90\" id=\"allow_everyone_to_see_test_and_audit\" type=\"checkbox\">  <label for=\"allow_everyone_to_see_test_and_audit\">&nbsp;&nbsp; ".$lang['AllowEveryoneToSeeTestAndAuditDetails']."</label></p>\n";
    
    echo "      <br>";
    echo "      <p><input value=\"".$lang['Update']."\" name=\"update_permissions\" type=\"submit\"></p>";
    echo "  </form>\n";
}

/************************************
 * FUNCTION: STRIP OPEN CLOSED PIE  *
 ************************************/
function strip_open_closed_pie()
{
//    // Get the teams the user is assigned to
//    $user_teams = get_user_teams($_SESSION['uid']);

        // If the user has access to every team
//        if ($user_teams == "all")
//        {
        // Open the database connection
//        $db = db_open();

        // Query the database
//        $stmt = $db->prepare("SELECT id, CASE WHEN status = \"Closed\" THEN 'Closed' WHEN status != \"Closed\" THEN 'Open' END AS name FROM `risks` ORDER BY name");
//        $stmt->execute();

        // Store the list in the array
//        $array = $stmt->fetchAll();

        // Close the database connection
//        db_close($db);
//        }
    // If the user has access to no teams
//    else if ($user_teams == "none")
//    {
        // Return an empty array
//        $array = array();
//    }
    // Otherwise
//    else
//    {
        // Get the team query string
//        $string = get_team_query_string($user_teams);

        // Open the database connection
//        $db = db_open();

        // Query the database
//        $stmt = $db->prepare("SELECT id, CASE WHEN status = \"Closed\" THEN 'Closed' WHEN status != \"Closed\" THEN 'Open' END AS name FROM `risks` WHERE " . $string . " ORDER BY name");
//        $stmt->execute();

        // Store the list in the array
//        $array = $stmt->fetchAll();

        // Close the database connection
//        db_close($db);
//    }

    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT id, CASE WHEN status = \"Closed\" THEN 'Closed' WHEN status != \"Closed\" THEN 'Open' END AS name FROM `risks` WHERE " . $separation_query . " ORDER BY name");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the array
    return $array;
}

/****************************************
 * FUNCTION: STRIP OPEN MITIGATION PIE  *
 ****************************************/
function strip_open_mitigation_pie()
{
//        // Get the teams the user is assigned to
//        $user_teams = get_user_teams($_SESSION['uid']);

        // If the user has access to every team
//        if ($user_teams == "all")
//        {
                // Open the database connection
//                $db = db_open();

                // Query the database
//        $stmt = $db->prepare("SELECT id, CASE WHEN mitigation_id = 0 THEN 'Unmitigated' WHEN mitigation_id != 0 THEN 'Mitigated' END AS name FROM `risks` WHERE status != \"Closed\" ORDER BY name");
//                $stmt->execute();

                // Store the list in the array
//                $array = $stmt->fetchAll();

                // Close the database connection
//                db_close($db);
//        }
        // If the user has access to no teams
//        else if ($user_teams == "none")
//        {
                // Return an empty array
//                $array = array();
//        }
        // Otherwise
//        else
//        {
                // Get the team query string
//                $string = get_team_query_string($user_teams);

                // Open the database connection
//                $db = db_open();

                // Query the database
//        $stmt = $db->prepare("SELECT id, CASE WHEN mitigation_id = 0 THEN 'Unmitigated' WHEN mitigation_id != 0 THEN 'Mitigated' END AS name FROM `risks` WHERE status != \"Closed\" AND (" . $string . ") ORDER BY name");
//                $stmt->execute();

                // Store the list in the array
//                $array = $stmt->fetchAll();

                // Close the database connection
//                db_close($db);
//        }

    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT id, CASE WHEN mitigation_id = 0 THEN 'Unmitigated' WHEN mitigation_id != 0 THEN 'Mitigated' END AS name FROM `risks` WHERE status != \"Closed\" AND (" . $separation_query . ") ORDER BY name");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);



    // Return the array
    return $array;
}

/************************************
 * FUNCTION: STRIP OPEN REVIEW PIE  *
 ************************************/
function strip_open_review_pie()
{
//        // Get the teams the user is assigned to
//        $user_teams = get_user_teams($_SESSION['uid']);

        // If the user has access to every team
//        if ($user_teams == "all")
//        {
                // Open the database connection
//                $db = db_open();

                // Query the database
//        $stmt = $db->prepare("SELECT id, CASE WHEN mgmt_review = 0 THEN 'Unreviewed' WHEN mgmt_review != 0 THEN 'Reviewed' END AS name FROM `risks` WHERE status != \"Closed\" ORDER BY name");
//                $stmt->execute();

                // Store the list in the array
//                $array = $stmt->fetchAll();

                // Close the database connection
//                db_close($db);
//        }
        // If the user has access to no teams
//        else if ($user_teams == "none")
//        {
                // Return an empty array
//                $array = array();
//        }
        // Otherwise
//        else
//        {
                // Get the team query string
//                $string = get_team_query_string($user_teams);

                // Open the database connection
//                $db = db_open();

                // Query the database
//        $stmt = $db->prepare("SELECT id, CASE WHEN mgmt_review = 0 THEN 'Unreviewed' WHEN mgmt_review != 0 THEN 'Reviewed' END AS name FROM `risks` WHERE status != \"Closed\" AND (" . $string . ") ORDER BY name");
//                $stmt->execute();

                // Store the list in the array
//                $array = $stmt->fetchAll();

                // Close the database connection
//                db_close($db);
//        }

    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT id, CASE WHEN mgmt_review = 0 THEN 'Unreviewed' WHEN mgmt_review != 0 THEN 'Reviewed' END AS name FROM `risks` WHERE status != \"Closed\" AND (" . $separation_query . ") ORDER BY name");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the array
    return $array;
}

/***********************************
 * FUNCTION: STRIP GET OPEN RISKS  *
 ***********************************/
function strip_get_open_risks($teams = false)
{
    if($teams !== false){
        if($teams == ""){
            $teams_query = " AND 0 ";
        }else{
            $options = explode(",", $teams);
            $teams_query = generate_or_query($options, 'team');
            if(in_array("0", $options)){
                $teams_query .= " OR team='' ";
            }
            $teams_query = " AND ( {$teams_query} ) ";
        }
    }else{
        $teams_query = "";
    }
    /*
        // Get the teams the user is assigned to
        $user_teams = get_user_teams($_SESSION['uid']);

        // If the user has access to every team
        if ($user_teams == "all")
        {
            // Open the database connection
            $db = db_open();

            // Query the database
    $stmt = $db->prepare("SELECT id FROM `risks` WHERE status != \"Closed\"");
            $stmt->execute();

            // Store the list in the array
            $array = $stmt->fetchAll();

            // Close the database connection
            db_close($db);
        }
        // If the user has access to no teams
        else if ($user_teams == "none")
        {
            // Return an empty array
            $array = array();
        }
        // Otherwise
        else
        {
            // Get the team query string
            $string = get_team_query_string($user_teams);

            // Open the database connection
            $db = db_open();

            // Query the database
    $stmt = $db->prepare("SELECT id FROM `risks` WHERE status != \"Closed\" AND (" . $string . ")");
            $stmt->execute();

            // Store the list in the array
            $array = $stmt->fetchAll();

            // Close the database connection
            db_close($db);
        }
    */
    
    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT id FROM `risks` WHERE status != \"Closed\" AND (" . $separation_query . ") {$teams_query};");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

   // Return the array
    return $array;
}

/*************************************
 * FUNCTION: STRIP GET CLOSED RISKS  *
 *************************************/
function strip_get_closed_risks($teams = false)
{
    if($teams !== false){
        if($teams == ""){
            $teams_query = " AND 0 ";
        }else{
            $options = explode(",", $teams);
            $teams_query = generate_or_query($options, 'team');
            if(in_array("0", $options)){
                $teams_query .= " OR team='' ";
            }
            $teams_query = " AND ( {$teams_query} ) ";
        }
    }else{
        $teams_query = "";
    }
    
    /*
        // Get the teams the user is assigned to
        $user_teams = get_user_teams($_SESSION['uid']);

        // If the user has access to every team
        if ($user_teams == "all")
        {
                // Open the database connection
                $db = db_open();

                // Query the database
        $stmt = $db->prepare("SELECT id FROM `risks` WHERE status = \"Closed\" {$teams_query} ");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();

                // Close the database connection
                db_close($db);
        }
        // If the user has access to no teams
        else if ($user_teams == "none")
        {
                // Return an empty array
                $array = array();
        }
        // Otherwise
        else
        {
                // Get the team query string
                $string = get_team_query_string($user_teams);

                // Open the database connection
                $db = db_open();

                // Query the database
        $stmt = $db->prepare("SELECT id FROM `risks` WHERE status = \"Closed\" AND (" . $string . ") {$teams_query}");
                $stmt->execute();

                // Store the list in the array
                $array = $stmt->fetchAll();

                // Close the database connection
                db_close($db);
        }
    */
    
    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();
    // Query the database
    $stmt = $db->prepare("SELECT id FROM `risks` WHERE status = \"Closed\" AND (" . $separation_query . ") {$teams_query} ");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

   // Return the array
    return $array;
}    

/******************************************
 * FUNCTION: STRIP GET OPENED RISKS ARRAY *
 ******************************************/
function strip_get_opened_risks_array()
{
//    // Get the teams the user is assigned to
//    $user_teams = get_user_teams($_SESSION['uid']);

    // If the user has access to every team
//    if ($user_teams == "all")
//    {
        // Open the database connection
//        $db = db_open();

        // Query the database
//        $stmt = $db->prepare("SELECT id, submission_date FROM risks ORDER BY submission_date;");
//        $stmt->execute();

        // Store the list in the array
//        $array = $stmt->fetchAll();

        // Close the database connection
//        db_close($db);
//    }
    // If the user has access to no teams
//    else if ($user_teams == "none")
//    {
        // Return an empty array
//        $array = array();
//    }
    // Otherwise
//    else
//    {
        // Get the team query string
//        $string = get_team_query_string($user_teams);

        // Open the database connection
//        $db = db_open();

        // Query the database
//        $stmt = $db->prepare("SELECT id, submission_date FROM risks WHERE " . $string . " ORDER BY submission_date;");
//        $stmt->execute();

        // Store the list in the array
//        $array = $stmt->fetchAll();

        // Close the database connection
//        db_close($db);
//    }

    // Get query by permission setting    
    $separation_query = get_user_teams_query();

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT id, submission_date FROM risks WHERE " . $separation_query . " ORDER BY submission_date;");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the array
    return $array;
}

/******************************************
 * FUNCTION: STRIP GET CLOSED RISKS ARRAY *
 ******************************************/
function strip_get_closed_risks_array()
{
//    // Get the teams the user is assigned to
//    $user_teams = get_user_teams($_SESSION['uid']);

    // If the user has access to every team
//    if ($user_teams == "all")
//    {
        // Open the database connection
//        $db = db_open();

        // Query the database
//$stmt = $db->prepare("SELECT a.risk_id as id, a.closure_date, c.status FROM closures a LEFT JOIN risks c ON a.risk_id=c.id WHERE a.closure_date=(SELECT max(b.closure_date) FROM closures b WHERE a.risk_id=b.risk_id) AND c.status='Closed' order by closure_date;");
//        $stmt->execute();

        // Store the list in the array
//        $array = $stmt->fetchAll();

        // Close the database connection
//        db_close($db);
//    }
    // If the user has access to no teams
//    else if ($user_teams == "none")
//    {
        // Return an empty array
//        $array = array();
//    }
    // Otherwise
//    else
//    {
        // Get the team query string
//        $string = get_team_query_string($user_teams);

        // Open the database connection
//        $db = db_open();

        // Query the database
//$stmt = $db->prepare("SELECT a.risk_id as id, a.closure_date, c.status FROM closures a LEFT JOIN risks c ON a.risk_id=c.id WHERE a.closure_date=(SELECT max(b.closure_date) FROM closures b WHERE a.risk_id=b.risk_id) AND c.status='Closed' AND (" . $string . ") order by closure_date;");
//        $stmt->execute();

        // Store the list in the array
//        $array = $stmt->fetchAll();

        // Close the database connection
//        db_close($db);
//    }

    // Get query by permission setting    
    $separation_query = get_user_teams_query("t1");

    // Open the database connection
    $db = db_open();

    // Query the database
//    $stmt = $db->prepare("SELECT a.risk_id as id, a.closure_date, c.status FROM closures a LEFT JOIN risks c ON a.risk_id=c.id WHERE a.closure_date=(SELECT max(b.closure_date) FROM closures b WHERE a.risk_id=b.risk_id) AND c.status='Closed' AND (" . $separation_query . ") GROUP BY a.risk_id ORDER BY closure_date;");
    $stmt = $db->prepare("
        SELECT t1.id, IFNULL(t2.closure_date, NOW()) closure_date, t1.status 
        FROM `risks` t1 LEFT JOIN `closures` t2 ON t1.close_id=t2.id
        WHERE t1.status='Closed'  AND (" . $separation_query . ")
        ORDER BY IFNULL(t2.closure_date, NOW());
    ");

    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);


    // Return the array
    return $array;
}

/**********************************
 * FUNCTION: GET USER TEAMS QUERY *
 **********************************/
function get_user_teams_query($rename = false, $where = false, $and = false, $onlyTeam = false)
{
    // If this is called from the command line
    if(PHP_SAPI === 'cli'){
        return "";
    }
    
    // If this user is admin, give all permission
    if(is_admin())
    {
        $string = "1";
    }
    // If this is not admin user, make query
    else
    {
        $orWheres = array();
        if($onlyTeam === false){
            // If this setting is enabled, allow all users to see risks unassigned teams
            if(get_setting('allow_all_to_risk_noassign_team')){
                if ($rename != false){
                    $query = $rename.".team=''";
                }else{
                    $query = "team=''";
                }
                $query = " ({$query}) ";
                array_push($orWheres, $query);
            }
            
            // Allow owner to see risk
            if(get_setting('allow_owner_to_risk')){
                if ($rename != false){
                    $query = $rename.".owner=".$_SESSION['uid'];
                }else{
                    $query = "owner=".$_SESSION['uid'];
                }
                $query = " ({$query}) ";
                array_push($orWheres, $query);
            }
            
            // Allow to owner's manager to see risk
            if(get_setting('allow_ownermanager_to_risk')){
                if ($rename != false){
                    $query = $rename.".manager=".$_SESSION['uid'];
                }else{
                    $query = "manager=".$_SESSION['uid'];
                }
                $query = " ({$query}) ";
                array_push($orWheres, $query);
            }
            
            // Allow submitter to see risk
            if(get_setting('allow_submitter_to_risk')){
                if ($rename != false){
                    $query = $rename.".submitted_by=".$_SESSION['uid'];
                }else{
                    $query = "submitted_by=".$_SESSION['uid'];
                }
                $query = " ({$query}) ";
                array_push($orWheres, $query);
            }
            
            // Allow stakeholder to see risk
            if(get_setting('allow_stakeholder_to_risk')){
                if ($rename != false){
                    $query = "FIND_IN_SET({$_SESSION['uid']}, {$rename}.`additional_stakeholders`)";
                }else{
                    $query = "FIND_IN_SET({$_SESSION['uid']}, `additional_stakeholders`)";
                }
                $query = " ({$query}) ";
                array_push($orWheres, $query);
            }
        }
        
        if(get_setting('allow_team_member_to_risk')){
            // Get the teams the user is assigned to
            $user_teams = get_user_teams($_SESSION['uid']);
            
            if ($user_teams == "all")
            {
                $user_teams = get_all_teams();
            }

            // Get the team query string
            $query = get_team_query_string($user_teams, $rename);
            $query = " ($query) ";
            array_push($orWheres, $query);
        }
        if(count($orWheres)){
            $string = implode(" OR ", $orWheres);
        }else{
            $string = " 0 ";
        }
    }
    

    // String with an empty query string
    $query_string = "";

    // If we should have a where clause
    if ($where)
    {
        $query_string .= " WHERE " . $string . " ";
    }
    // If we should have an and clause
    else if ($and)
    {
        $query_string .= " AND (" . $string . ") ";
    }
    // Otherwise just use the string
    else $query_string = $string;

    // Return the query string
    return $query_string;
}



/********************************************   
 * Check f user is an admin or everyone is  *
 * allowed to access the tests/audits       *
 ********************************************/
function should_skip_test_and_audit_permission_check() {
    if(is_admin() || get_setting('allow_everyone_to_see_test_and_audit')) {
        return true;
    }
    return false;
}

/************************************************************
 * FUNCTION: HAS USER PERMISSION TO ACCESS TEST OR AUDIT    *
 * Checks if the `user` has permission to see `item`        *
 * $user_id: Id of the `user`                               *
 * $item_id: Id of the `item` we're checking                *
 * $type: Type of the `item`. Can be 'test' or 'audit'      *
 ************************************************************/
function is_user_allowed_to_access($user_id, $item_id, $type) {

    if ($type !== 'test' && $type !== 'audit')
        return false;

    if(should_skip_test_and_audit_permission_check()) {
        return true;
    }

    // Open the database connection
    $db = db_open();

    switch($type) {
        case 'test':
            $sql = "
                SELECT
                    `fc`.`control_owner`,
                    `fct`.`tester`,
                    `fct`.`additional_stakeholders`,
                    GROUP_CONCAT(DISTINCT `itt`.`team_id`) teams
                FROM
                    `framework_control_tests` fct
                    LEFT JOIN `framework_controls` fc ON `fct`.`framework_control_id` = `fc`.`id`
                    LEFT JOIN `items_to_teams` itt ON `itt`.`item_id` = `fct`.`id` and `itt`.`type` = 'test'
                WHERE
                    `fct`.`id`=:id;
            ";
        break;
        case 'audit':
            $sql = "
                SELECT
                    `fc`.`control_owner`,
                    `fcta`.`tester`,
                    `fct`.`additional_stakeholders`,
                    GROUP_CONCAT(DISTINCT `itt`.`team_id`) teams
                FROM
                    `framework_control_test_audits` fcta
                    LEFT JOIN `framework_controls` fc ON `fcta`.`framework_control_id` = `fc`.`id`
                    LEFT JOIN `framework_control_tests` fct ON `fct`.`id` = `fcta`.`test_id`
                    LEFT JOIN `items_to_teams` itt ON `itt`.`item_id` = `fcta`.`id` and `itt`.`type` = 'audit'
                WHERE
                    `fcta`.`id`=:id;
            ";
        break;
        default:
            return false;
    }

    $stmt = $db->prepare($sql);
    $stmt->bindParam(":id", $item_id, PDO::PARAM_INT);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    if(get_setting('allow_control_owner_to_see_test_and_audit')) {
        if($item['control_owner'] == $user_id) {
            return true;
        }
    }

    if(get_setting('allow_tester_to_see_test_and_audit')) {
        if($item['tester'] == $user_id) {
            return true;
        }
    }

    if(get_setting('allow_stakeholders_to_see_test_and_audit')) {
        if(in_array($user_id, explode(",", $item['additional_stakeholders']))) {
            return true;
        }
    }

    if(get_setting('allow_team_members_to_see_test_and_audit')) {
        // Get the teams the user is assigned to
        $user_teams = get_user_teams($user_id);

        // If the user has access to every team
        if ($user_teams == "all") {
            return true;
        }

        // If the user has access to no teams
        if ($user_teams == "none") {
            return false;
        }

        foreach(explode(",", $item['teams']) as $team) {
            // Pattern is a team id surrounded by colons
            $regex_pattern = "/:" . $team .":/";

            // Check if the team is in the user's teams
            if (preg_match($regex_pattern, $user_teams)) {
                return true;
            }
        }
    }

    return false;
}

function get_compliance_separation_access_info() {

    if(should_skip_test_and_audit_permission_check()) {
        return [];
    }

    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("
        SELECT
            `f`.`value` framework,
            `fc`.`id` framework_control,
            `fct`.`id` framework_control_test,
            `fc`.`control_owner`,
            `fct`.`tester`,
            `fct`.`additional_stakeholders`,
            GROUP_CONCAT(DISTINCT `itt`.`team_id`) teams
        FROM
            `frameworks` f
            LEFT JOIN `framework_controls` fc ON FIND_IN_SET(`f`.`value`, `fc`.`framework_ids`) AND `fc`.`deleted` = 0
            LEFT JOIN `framework_control_tests` fct ON `fct`.`framework_control_id` = `fc`.`id`
            LEFT JOIN `items_to_teams` itt ON `itt`.`item_id` = `fct`.`id` and `itt`.`type` = 'test'
        WHERE
            `f`.`status` = 1 AND
            `fct`.`id` IS NOT NULL
        GROUP BY
            `f`.`value`,
            `fc`.`id`,
            `fct`.`id`
        ORDER BY
            `f`.`value`,
            `fc`.`id`,
            `fct`.`id`;
    ");

    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Close the database connection
    db_close($db);

    $settings = get_settings(['allow_control_owner_to_see_test_and_audit',
        'allow_tester_to_see_test_and_audit',
        'allow_stakeholders_to_see_test_and_audit',
        'allow_team_members_to_see_test_and_audit']);

    $user_id = $_SESSION['uid'];
    $user_teams = array_values(array_filter(explode(':', get_user_teams($user_id))));

    $list_of_accessible_items = array(
        'frameworks' => [],
        'framework_controls' => [],
        'framework_control_tests' => []
    );
    $checked_tests = [];

    foreach($items as $item) {

        // A test should be checked only once, even if there're more results of the same test
        // (Caused by the fact that a control can be on more frameworks)
        if (in_array($item['framework_control_test'], $checked_tests))
            continue;

        $access = 
            ($settings['allow_control_owner_to_see_test_and_audit']
            && $item['control_owner'] == $user_id)
            ||
            ($settings['allow_tester_to_see_test_and_audit']
            && $item['tester'] == $user_id)
            ||
            ($settings['allow_stakeholders_to_see_test_and_audit']
            && $item['additional_stakeholders']
            && in_array($user_id, explode(",", $item['additional_stakeholders'])))
            ||
            ($settings['allow_team_members_to_see_test_and_audit']
            && (
                $user_teams == "all"
                || array_intersect($user_teams, explode(",", $item['teams']))
            ))
        ;

        if ($access) {
            $list_of_accessible_items['frameworks'][] = $item['framework'];
            $list_of_accessible_items['framework_controls'][] = $item['framework_control'];
            $list_of_accessible_items['framework_control_tests'][] = $item['framework_control_test'];
        }

        $checked_tests[] = $item['framework_control_test'];
    }

    return $list_of_accessible_items;
}

?>

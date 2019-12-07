<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC.    *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to       *
 * automatically upgrade the application and database.              *
 ********************************************************************/

// Extra Version
define('UPGRADE_EXTRA_VERSION', '20191130-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../includes/config.php'));
require_once(realpath(__DIR__ . '/../../includes/services.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// If we want to enable the Content Security Policy (CSP) - This may break Chrome
if (csp_enabled())
{
    // Add the Content-Security-Policy header
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' *.highcharts.com *.googleapis.com *.gstatic.com *.jquery.com;");
}

if (!isset($_SESSION))
{
    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start the session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

    session_name('SimpleRisk');
    session_start();
}


// Include the language file
require_once(language_file());
// If a POST was submitted
if (isset($_POST['backup']) || isset($_POST['app_upgrade']) || isset($_POST['db_upgrade']))
{

    // Don't use CSRF Magic for backup
    if (!isset($_POST['backup']))
    {
        require_once(realpath(__DIR__ . '/../../includes/csrf-magic/csrf-magic.php'));
    }

    // Check for session timeout or renegotiation
    session_check();
    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
        exit(0);
    }

    // Check if access is authorized
    if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
    {
        exit(0);
    }

    // If the user posted to backup the database
    if (isset($_POST['backup']))
    {
        // Backup the database
        if(!backup_database()){
            header("Location: ".$_SERVER['HTTP_REFERER']);
            exit(0);
        }
    }
}

/******************************
 * FUNCTION: DISPLAY UPGRADES *
 ******************************/
function display_upgrades()
{
    global $escaper;
    global $lang;

    echo $escaper->escapeHtml($lang['UpgradeInstructions']);
    echo "<br />\n";

    echo "<form name=\"upgrade_simplerisk\" method=\"post\" action=\"".$_SESSION['base_url']."/extras/upgrade/index.php\" target=\"_blank\" style='margin-bottom: 0px'>\n";
    echo "<input type=\"submit\" name=\"backup\" id=\"backup\" value=\"" . $escaper->escapeHtml($lang['BackupDatabaseButton']) . "\" />\n";
    echo "<input type=\"button\" name=\"app_upgrade\" id=\"app_upgrade\" value=\"" . $escaper->escapeHtml($lang['UpgradeApplication']) . "\" />\n";
    echo "</form>";

    echo "
        <div class='progress-wrapper' style='display: none'>
            <div class='progress-window'>
            </div>
        </div>
    ";
}



function extra_current_version($extra) {

    global $available_extras;

    if (!in_array($extra, $available_extras))
        return "N/A";

    // Get the extra path
    $path = realpath(__DIR__ . "/../$extra/index.php");

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        switch ($extra) {
            case "upgrade":
                return UPGRADE_EXTRA_VERSION;
            case "authentication":
                return AUTHENTICATION_EXTRA_VERSION;
            case "encryption":
                return ENCRYPTION_EXTRA_VERSION;
            case "import-export":
                return IMPORTEXPORT_EXTRA_VERSION;
            case "notification":
                return NOTIFICATION_EXTRA_VERSION;
            case "separation":
                return SEPARATION_EXTRA_VERSION;
            case "assessments":
                return ASSESSMENTS_EXTRA_VERSION;
            case "api":
                return API_EXTRA_VERSION;
            case "complianceforge":
                return COMPLIANCEFORGE_EXTRA_VERSION;
            case "complianceforgescf":
                return COMPLIANCEFORGE_SCF_EXTRA_VERSION;
            case "governance":
                return GOVERNANCE_EXTRA_VERSION;
            case "customization":
                return CUSTOMIZATION_EXTRA_VERSION;
            case "advanced_search":
                return ADVANCED_SEARCH_EXTRA_VERSION;
            case "jira":
                return JIRA_EXTRA_VERSION;
            default:
                return "N/A";
        }
    }
    else return "N/A";
}

function gather_extra_upgrades() {

    global $available_extras;

    $upgradeable = [];
    foreach($available_extras as $extra) {
        if (is_purchased($extra) &&
            is_installed($extra) &&
            extra_current_version($extra) < latest_version($extra)) {
            // Have to be upgraded    
            $upgradeable[] = $extra;
        }
    }
    
    return $upgradeable;
}

function upgrade_extras($extras_to_upgrade = false) {

    global $lang;

    if ($extras_to_upgrade === false)
        $extras_to_upgrade = gather_extra_upgrades();

    stream_write($lang['UpdateExtrasStarted']);
    foreach($extras_to_upgrade as $extra) {
        stream_write(_lang('UpdateExtrasExtraUpdateStarted', array('extra' => $extra)));
        if (!download_extra($extra, true)) {
            stream_write_error(_lang('UpdateExtrasUpdateExtraFailed', array('extra' => $extra)));
            return 0;
        }
    }
    stream_write($lang['UpdateExtrasSuccessful']);
    return 1;
}


/************************************
 * FUNCTION: DISPLAY UPGRADE EXTRAS *
 ************************************/
function display_upgrade_extras()
{
    global $escaper;
    global $lang;

    echo "<p><h4>" . $escaper->escapeHtml($lang['UpgradeCustomExtras']) . "</h4></p>\n";
    echo "<table width=\"100%\" class=\"table table-bordered table-condensed\">\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b><u>Extra Name</u></b></td>\n";
    echo "  <td width=\"10px\"><b><u>Purchased</u></b></td>\n";
    echo "  <td width=\"10px\"><b><u>Installed</u></b></td>\n";
    echo "  <td width=\"10px\"><b><u>Activated</u></b></td>\n";
    echo "  <td width=\"60px\"><b><u>Version</u></b></td>\n";
    echo "  <td width=\"60px\"><b><u>Latest Version</u></b></td>\n";
    echo "  <td width=\"60px\"><b><u>Action</u></b></td>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    // Upgrade Extra
    $purchased = true;
    $installed = is_installed("upgrade");
    $activated = true;
    $version = extra_current_version("upgrade");
    $latest_version = latest_version("upgrade");
    $action_button = get_action_button("upgrade", $purchased, $installed, $activated, $version, $latest_version);
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>Upgrade</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\" checked /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\" checked /></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";

    // ComplianceForge SCF Extra
    $purchased = true;
    $installed = is_installed("complianceforgescf");
    $activated = complianceforge_scf_extra();
    $version = extra_current_version("complianceforgescf");
    $latest_version = latest_version("complianceforgescf");
    $action_button = get_action_button("complianceforgescf", $purchased, $installed, $activated, $version, $latest_version);
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>ComplianceForge SCF</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\" checked /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\" checked /></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";
    
    // Custom Authentication Extra
    $purchased = is_purchased("authentication");
    $installed = is_installed("authentication");
    $activated = custom_authentication_extra();
    $version = ($purchased ? extra_current_version("authentication") : "N/A");
    $latest_version = ($purchased ? latest_version("authentication") : "N/A");
    $action_button = get_action_button("authentication", $purchased, $installed, $activated, $version, $latest_version);
    if ($purchased && $activated)
    {
        $activated_link = "&nbsp;&nbsp;<a href=\"authentication.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>";
    }
    else $activated_link = "";
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>Custom Authentication</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " />" . $activated_link . "</td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";

    // Customization Extra
    $purchased = is_purchased("customization");
    $installed = is_installed("customization");
    $activated = customization_extra();
    $version = ($purchased ? extra_current_version("customization") : "N/A");
    $latest_version = ($purchased ? latest_version("customization") : "N/A");
    $action_button = get_action_button("customization", $purchased, $installed, $activated, $version, $latest_version);
    if ($purchased && $activated)
    {
        $activated_link = "&nbsp;&nbsp;<a href=\"customization.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>";
    }
    else $activated_link = "";
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>Customization</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " />" . $activated_link . "</td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";

    // Encrypted Database Extra
    $purchased = is_purchased("encryption");
    $installed = is_installed("encryption");
    $activated = encryption_extra();
    $version = ($purchased ? extra_current_version("encryption") : "N/A");
    $latest_version = ($purchased ? latest_version("encryption") : "N/A");
    $action_button = get_action_button("encryption", $purchased, $installed, $activated, $version, $latest_version);
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>Encrypted Database</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";

    // Import-Export Extra
    $purchased = is_purchased("import-export");
    $installed = is_installed("import-export");
    $activated = import_export_extra();
    $version = ($purchased ? extra_current_version("import-export") : "N/A");
    $latest_version = ($purchased ? latest_version("import-export") : "N/A");
    $action_button = get_action_button("import-export", $purchased, $installed, $activated, $version, $latest_version);
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>Import / Export</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";

    // Notification Extra
    $purchased = is_purchased("notification");
    $installed = is_installed("notification");
    $activated = notification_extra();
    $version = ($purchased ? extra_current_version("notification") : "N/A");
    $latest_version = ($purchased ? latest_version("notification") : "N/A");
    $action_button = get_action_button("notification", $purchased, $installed, $activated, $version, $latest_version);
    if ($purchased && $activated)
    {
    	$activated_link = "&nbsp;&nbsp;<a href=\"notification.php\">". $escaper->escapeHtml($lang['Configure']) ."</a>";
    }
    else $activated_link = "";
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>E-mail Notification</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " />" . $activated_link . "</td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";

    // Separation Extra
    $purchased = is_purchased("separation");
    $installed = is_installed("separation");
    $activated = team_separation_extra();
    $version = ($purchased ? extra_current_version("separation") : "N/A");
    $latest_version = ($purchased ? latest_version("separation") : "N/A");
    $action_button = get_action_button("separation", $purchased, $installed, $activated, $version, $latest_version);
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>Team-Based Separation</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";

    // Assessments Extra
    $purchased = is_purchased("assessments");
    $installed = is_installed("assessments");
    $activated = assessments_extra();
    $version = ($purchased ? extra_current_version("assessments") : "N/A");
    $latest_version = ($purchased ? latest_version("assessments") : "N/A");
    $action_button = get_action_button("assessments", $purchased, $installed, $activated, $version, $latest_version);
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>Risk Assessments</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";

	// ComplianceForge Digital Security Program
	/*
        $purchased = is_purchased("complianceforge");
        $installed = is_installed("complianceforge");
        $activated = complianceforge_extra();
        $version = ($purchased ? extra_current_version("complianceforge") : "N/A");
        $latest_version = ($purchased ? latest_version("complianceforge") : "N/A");
        $action_button = get_action_button("complianceforge", $purchased, $installed, $activated, $version, $latest_version);
        echo "<tr>\n";
        echo "  <td width=\"115px\"><b>ComplianceForge DSP</b></td>\n";
        echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
        echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
        echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
        echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
        echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
        echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
        echo "</tr>\n";
	*/
        // Governance Extra
/*
        $purchased = is_purchased("governance");
        $installed = is_installed("governance");
        $activated = governance_extra();
        $version = ($purchased ? extra_current_version("governance") : "N/A");
        $latest_version = ($purchased ? latest_version("governance") : "N/A");
        $action_button = get_action_button("governance", $purchased, $installed, $activated, $version, $latest_version);
        echo "<tr>\n";
        echo "  <td width=\"115px\"><b>Governance</b></td>\n";
        echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
        echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
        echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
        echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
        echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
        echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
        echo "</tr>\n";
*/
    // API Extra
    $purchased = is_purchased("api");
    $installed = is_installed("api");
    $activated = api_extra();
    $version = ($purchased ? extra_current_version("api") : "N/A");
    $latest_version = ($purchased ? latest_version("api") : "N/A");
    $action_button = get_action_button("api", $purchased, $installed, $activated, $version, $latest_version);
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>API</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";
    
    // Advanced Search Extra
    $purchased = is_purchased("advanced_search");
    $installed = is_installed("advanced_search");
    $activated = advanced_search_extra();
    $version = ($purchased ? extra_current_version("advanced_search") : "N/A");
    $latest_version = ($purchased ? latest_version("advanced_search") : "N/A");
    $action_button = get_action_button("advanced_search", $purchased, $installed, $activated, $version, $latest_version);
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>Advanced Search</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";

    // Jira Extra
    $purchased = is_purchased("jira");
    $installed = is_installed("jira");
    $activated = jira_extra();
    $version = ($purchased ? extra_current_version("jira") : "N/A");
    $latest_version = ($purchased ? latest_version("jira") : "N/A");
    $action_button = get_action_button("jira", $purchased, $installed, $activated, $version, $latest_version);
    echo "<tr>\n";
    echo "  <td width=\"115px\"><b>Jira</b></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($purchased ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($installed ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"10px\"><input type=\"checkbox\"" . ($activated ? " checked" : "") . " /></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $escaper->escapeHtml($latest_version) . "</b></td>\n";
    echo "  <td width=\"60px\"><b>" . $action_button . "</b></td>\n";
    echo "</tr>\n";

    echo "</tbody>\n";
    echo "</table>\n";
}

/*****************************
 * FUNCTION: BACKUP DATABASE *
 *****************************/
function backup_database()
{
    global $lang;
    // Get and check mysqldump service is available.
    if(!is_process('mysqldump')){
        $mysqldump_path = get_setting('mysqldump_path');
    }else{
        $mysqldump_path = "mysqldump";
    }
    
    if(!is_process($mysqldump_path)){
        set_alert(true, "bad", $lang['UnavailableMysqldumpService']);
        return false;
    }

    // Export filename
    $filename = "simplerisk-" . date('Ymd') . ".sql";

    header("Pragma: public", true);
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");
    header("Content-Disposition: attachment; filename=".$filename);
    header("Content-Transfer-Encoding: binary");
    // Sanitize the mysqldump command
    $cmd = $mysqldump_path." --opt --lock-tables=false --skip-add-locks -h " . escapeshellarg(DB_HOSTNAME) . " -u " . escapeshellarg(DB_USERNAME) . " -p" . escapeshellarg(DB_PASSWORD) . " " . escapeshellarg(DB_DATABASE);
    // Execute the mysqldump
    $mysqldump = system($cmd);

    // Open memory as a file so no temp file needed
    $f = fopen('php://output', 'w');

    // Write the dump to the file
    fwrite($f, $mysqldump);

    // Close the file
    fclose($f);

    // Exit so that page content is not included in the results
    exit(0);
}

/*******************************
 * FUNCTION: IS UPGRADE NEEDED *
 *******************************/
function is_upgrade_needed()
{
    // Get the current application version
    $current_version = current_version("app");

    // Get the next application version
    $next_version = next_version($current_version);

    // If the current version is not the latest
    if ($next_version != "")
    {
        // An upgrade is needed
        return true;
    }
    // An upgrade is not needed
    else return false;
}

/**********************************
 * FUNCTION: IS DB UPGRADE NEEDED *
 **********************************/
function is_db_upgrade_needed()
{
    // Get the current application version
    $app_version = current_version("app");

    // Get the current database version
    $db_version = current_version("db");

    // If the app version is not the same as the database version
    if ($app_version != $db_version)
    {
        // An upgrade is needed
        return true;
    }
    // An upgrade is not needed
    else return false;
}

/*  Functions to write to the response stream.
    The delay is there to make sure the response is flushed and we're not
    just sending the response in a single line.
*/
function stream_write($text) {

    global $escaper;

    echo $escaper->escapeHtml($text);
    usleep(500000);
}
function stream_write_error($text) {

    global $escaper;

    echo "<div class='error_message'>" . $escaper->escapeHtml($text) . "</div>";
    usleep(500000);
}

/************************************************************
 * Function BACKUP                                          *
 * Doing the Application and DB backup.                     *
 * ATM the Application backup is just simply copying over,  *
 * we can improve on it later.                              *
 ************************************************************/
function backup() {

    global $lang;

    stream_write($lang['BackupStart']);

    $source = $simplerisk_dir = realpath(__DIR__) . '/../';
    $timestamp = date("Y-m-d--H-i-s");
    $target_dir_root = sys_get_temp_dir() . '/simplerisk-backup-' . $timestamp;
    $target_dir_app = $target_dir_root . '/app';
    $target_dir_db = $target_dir_root . '/db';

    stream_write($lang['BackupCheckingPreRequisites']);
    // If the backup directory does not exist
	if (!is_dir($target_dir_root)) {

		// If the temp directory is not writeable
		if (!is_writeable(sys_get_temp_dir())) {
            stream_write_error(_lang('BackupDirectoryNotWriteable', array('location' => sys_get_temp_dir()), false));
			return false;
		}

		// If the backup directory structure can not be created
		if (!mkdir($target_dir_root) || !mkdir($target_dir_app) || !mkdir($target_dir_db)) {
            stream_write_error(_lang('BackupFailedToCreateDirectories', array('location' => sys_get_temp_dir()), false));
			return false;
		}
	}

    // Get and check mysqldump service is available.    
    if(!is_process('mysqldump')) {
        $mysqldump_path = get_setting('mysqldump_path');
    } else {
        $mysqldump_path = "mysqldump";
    }

    $db_backup_file = $target_dir_db . '/simplerisk-db-backup-' . $timestamp . '.sql';

    // Sanitize the mysqldump command
    $db_backup_cmd = $mysqldump_path . ' --opt --lock-tables=false --skip-add-locks -h ' . escapeshellarg(DB_HOSTNAME) . ' -u ' . escapeshellarg(DB_USERNAME) . ' -p' . escapeshellarg(DB_PASSWORD) . ' ' . escapeshellarg(DB_DATABASE) . ' > ' . $db_backup_file;

    stream_write($lang['BackupCheckingPreRequisitesDone']);

    //Copy the app files over
    stream_write($lang['BackupApplicationFiles']);
    recurse_copy($source, $target_dir_app);
    stream_write($lang['BackupApplicationFilesDone']);

    // Backup the database
    stream_write($lang['BackupDatabase']);
    $mysqldump = system($db_backup_cmd);
    stream_write($lang['BackupDatabaseDone']);

    stream_write($lang['BackupSuccessful']);
    return true;
}

/*********************************
 * FUNCTION: UPGRADE APPLICATION *
 *********************************/
function upgrade_application()
{
    // Get the current application version
    $current_version = current_version("app");

    // Get the next application version
    $next_version = next_version($current_version);

    // If the current version is not the latest
    if ($next_version != "")
    {
        echo "Update required<br />\n";

        // Get the file name for the next version to upgrade to
        $file_name = "simplerisk-" . $next_version;

        // Delete current files
        echo "Deleting existing files.<br />\n";
        if (file_exists(sys_get_temp_dir() . "/" . $file_name . ".tgz")) unlink(sys_get_temp_dir() . "/" . $file_name . ".tgz");
        if (file_exists(sys_get_temp_dir() . "/" . $file_name . ".tar")) unlink(sys_get_temp_dir() . "/" . $file_name . ".tar");
        if (file_exists(sys_get_temp_dir() . "/config.php")) unlink(sys_get_temp_dir() . "/config.php");

        // Download the file to tmp
        echo "Downloading the latest version.<br />\n";
        file_put_contents(sys_get_temp_dir() . "/" . $file_name . ".tgz", fopen("https://github.com/simplerisk/bundles/raw/master/" . $file_name . ".tgz", 'r'));

        // Path to the SimpleRisk directory
        $simplerisk_dir = realpath(__DIR__ . "/../../");

        // Backup the config file to tmp
        echo "Backing up the config file.<br />\n";
        copy ($simplerisk_dir . "/includes/config.php", sys_get_temp_dir() . "/config.php");

        // Decompress from gz
        echo "Decompressing the downloaded file.<br />\n";
        $p = new PharData(sys_get_temp_dir() . "/" . $file_name . ".tgz");
        $p->decompress();

        // Extract the tar to the tmp directory
        echo "Extracting the downloaded file.<br />\n";
        $phar = new PharData(sys_get_temp_dir() . "/" . $file_name . ".tar");
        $phar->extractTo(sys_get_temp_dir(), null, true);

        // Overwrite the old version with the new version
        echo "Copying the new files over the old.<br />\n";
        recurse_copy(sys_get_temp_dir() . "/simplerisk", $simplerisk_dir);

        // Copy the old config file back
        echo "Replacing the config file with the original.<br />\n";
        copy (sys_get_temp_dir() . "/config.php", $simplerisk_dir . "/includes/config.php");

        // Clean up files
        echo "Cleaning up temporary files.<br />\n";
        unlink(sys_get_temp_dir() . "/" . $file_name . ".tgz");
        unlink(sys_get_temp_dir() . "/" . $file_name . ".tar");
        delete_dir(sys_get_temp_dir() . "/simplerisk");
        unlink(sys_get_temp_dir() . "/config.php");
    }
    else echo "You are already at the latest version of SimpleRisk.\n";
}

/**************************
 * FUNCTION: IS PURCHASED *
 **************************/
function is_purchased($extra)
{
    //They're purchased by default
    if (in_array($extra, ['upgrade', 'complianceforgescf']))
        return true;

    if (!empty($GLOBALS['purchased_extras'])) {
        if (in_array($extra, $GLOBALS['purchased_extras'])) {
            return true;
        }
    } else {
        $GLOBALS['purchased_extras'] = [];
    }
    
    // Get the instance identifier
    $instance_id = get_setting("instance_id");

    // Get the services API key
    $services_api_key = get_setting("services_api_key");

    // Create the data to send
    $data = array(
        'action' => 'check_purchase',
        'instance_id' => $instance_id,
        'api_key' => $services_api_key,
        'extra_name' => $extra,
    );

    // Ask the service if the extra is purchased
    $results = simplerisk_service_call($data);
    $regex_pattern = "/<result>1<\/result>/";

    foreach ($results as $line)
    {
        // If the service returned a success
        if (preg_match($regex_pattern, $line, $matches))
        {
            $GLOBALS['purchased_extras'][] = $extra;
            return true;
        }
        else return false;
    }
}

/*************************************************
 * FUNCTION: CHECK IF THIS APP IS LATEST VERSION *
 *************************************************/
function check_latest_version()
{
    $current_app_version = current_version("app");
    $next_app_version = next_version($current_app_version);
    $db_version = current_version("db");

    $need_update_app = false;
    $need_update_db = false;
    
    // If the current version is not the latest
    if ($next_app_version != "") {
        $need_update_app = true;
    }
    
    // If the app version is not the same as the database version
    if ($current_app_version != $db_version) {
        $need_update_db = true;
    } elseif ($need_update_app && $next_app_version != $db_version) {
        $need_update_db = true;
    }
    
    // Check if there are update app or db version
    if($need_update_app || $need_update_db)
    {
        return false;
    }
    else
    {
        return true;
    }
}

/**************************
 * FUNCTION: NEXT VERSION *
 **************************/
function next_version($current_version)
{
    $version_page = file('https://updates.simplerisk.com/upgrade_path.xml');

    $regex_pattern = "/<simplerisk-" . $current_version . ">(.*)<\/simplerisk-" . $current_version . ">/";

    foreach ($version_page as $line)
    {
        if (preg_match($regex_pattern, $line, $matches))
        {
            $next_version = $matches[1];
        }
    }

    // If the next version is set
    if (isset($next_version))
    {
        // Return the next version
        return $next_version;
    }
    else return "";
}

/**************************
 * FUNCTION: IS INSTALLED *
 **************************/
function is_installed($extra_name)
{
    // Check the Extra Name
    switch ($extra_name)
    {
        case "upgrade":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../upgrade/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
            else return false;
        case "complianceforge":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../complianceforgescf/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
            else return false;
        case "complianceforgescf":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../complianceforgescf/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
            else return false;
        case "authentication":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../authentication/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
            else return false;
        case "encryption":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../encryption/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
            else return false;
        case "import-export":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../import-export/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
            else return false;
        case "notification":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../notification/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
            else return false;
        case "separation":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../separation/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
            else return false;
        case "assessments":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../assessments/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
                else return false;
        case "governance":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../governance/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
                else return false;
        case "api":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../api/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
            else return false;
        case "customization":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../customization/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
            else return false;
        case "advanced_search":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../advanced_search/index.php')))
            {
                // Return true
                return true;
            }
        case "jira":
            // If the extra exists
            if (file_exists(realpath(__DIR__ . '/../jira/index.php')))
            {
                // Return true
                return true;
            }
            // Otherwise, return false
            else return false;
        default:
            return false;
    }
}

/*******************************
 * FUNCTION: GET ACTION BUTTON *
 *******************************/
function get_action_button($extra_name, $purchased, $installed, $activated, $version, $latest_version)
{
    global $escaper;
    global $lang;

    // Default button is N/A
    $action_button = "N/A";

    // Check the Extra Name
    switch ($extra_name)
    {
        case "upgrade":
            $button_name = "get_upgrade_extra";
            break;
        case "complianceforge":
            $button_name = "get_complianceforge_extra";
            $action_link = "complianceforge.php";
            break;
        case "complianceforgescf":
            $button_name = "get_complianceforge_scf_extra";
            $action_link = "complianceforge_scf.php";
            break;
        case "authentication":
            $button_name = "get_authentication_extra";
            $action_link = "authentication.php";
            break;
        case "encryption":
            $button_name = "get_encryption_extra";
            $action_link = "encryption.php";
            break;
        case "import-export":
            $button_name = "get_importexport_extra";
            $action_link = "importexport.php";
            break;
        case "notification":
            $button_name = "get_notification_extra";
            $action_link = "notification.php";
            break;
        case "separation":
            $button_name = "get_separation_extra";
            $action_link = "separation.php";
            break;        
        case "assessments":
            $button_name = "get_assessments_extra";
            $action_link = "assessments.php";
            break;
        case "governance":
            $button_name = "get_governance_extra";
            $action_link = "governance.php";
            break;
        case "api":
            $button_name = "get_api_extra";
            $action_link = "api.php";
            break;
        case "customization":
            $button_name = "get_customization_extra";
            $action_link = "customization.php";
            break;
        case "advanced_search":
            $button_name = "get_advanced_search_extra";
            $action_link = "advanced_search.php";
            break;
        case "jira":
            $button_name = "get_jira_extra";
            $action_link = "jira.php";
            break;
    }

    // If the Extra has been purchased
    if ($purchased)
    {
        // If the Extra is not installed
        if (!$installed)
        {
            // Make the Install action button
            $action_button = "<form style=\"display: inline;\" name=\"install_extras\" method=\"post\" action=\"\"><button type=\"submit\" name=\"" . $button_name . "\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['Install']) ."</button></form>";
        }
        // Otherwise, the Extra is installed
        else
        {
            // If the current version is not the latest
            if ($version < $latest_version)
            {
                // Make the Upgrade action button
                $action_button = "<form style=\"display: inline;\" name=\"install_extras\" method=\"post\" action=\"\"><button type=\"submit\" name=\"" . $button_name . "\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['Upgrade']) ."</button></form>";
            }
            // Otherwise, the Extra is the latest version
            else
            {
                // If the Extra is not activated
                if (!$activated)
                {
                    $action_button = "<form style=\"display: inline;\" name=\"install_extras\" method=\"post\" action=\"" . $action_link . "\"><button type=\"submit\" name=\"activate_extra\" class=\"btn btn-primary\">". $escaper->escapeHtml($lang['Activate']) ."</button></form>";
                }
            }
        }
    }
    // Otherwise, the Extra has not been purchased
    else
    {
        $action_button = "<form style=\"display: inline;\" action=\"https://www.simplerisk.com/extras\" target=\"_blank\" method=\"post\"><button type=\"submit\" name=\"purchase_extra\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Purchase']) . "</button></form>";
    }

    // Return the action button
    return $action_button;
}


/*******************************************************************************
*
*   Leaving these version querying functions here for backward compatibility
*   2019/07/23
*
*
********************************************************************************/

/***********************************
 * FUNCTION: UPGRADE EXTRA VERSION *
 ***********************************/
function upgrade_extra_version()
{
    return UPGRADE_EXTRA_VERSION;
}

/******************************************
 * FUNCTION: AUTHENTICATION EXTRA VERSION *
 ******************************************/
function authentication_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../authentication/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return AUTHENTICATION_EXTRA_VERSION;
	}
	else return "N/A";
}

/**************************************
 * FUNCTION: ENCRYPTION EXTRA VERSION *
 **************************************/
function encryption_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../encryption/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return ENCRYPTION_EXTRA_VERSION;
    }
    else return "N/A";
}

/****************************************
 * FUNCTION: IMPORTEXPORT EXTRA VERSION *
 ****************************************/
function importexport_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../import-export/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return IMPORTEXPORT_EXTRA_VERSION;
    }
    else return "N/A";
}

/****************************************
 * FUNCTION: NOTIFICATION EXTRA VERSION *
 ****************************************/
function notification_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../notification/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return NOTIFICATION_EXTRA_VERSION;
    }
    else return "N/A";
}

/**************************************
 * FUNCTION: SEPARATION EXTRA VERSION *
 **************************************/
function separation_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../separation/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return SEPARATION_EXTRA_VERSION;
    }
    else return "N/A";
}

/***************************************
 * FUNCTION: ASSESSMENTS EXTRA VERSION *
 ***************************************/
function assessments_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../assessments/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return ASSESSMENTS_EXTRA_VERSION;
    }
    else return "N/A";
}

/*******************************
 * FUNCTION: API EXTRA VERSION *
 *******************************/
function api_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../api/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return API_EXTRA_VERSION;
    }
    else return "N/A";
}

/*******************************************
 * FUNCTION: COMPLIANCEFORGE EXTRA VERSION *
 *******************************************/
function complianceforge_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../complianceforge/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return COMPLIANCEFORGE_EXTRA_VERSION;
    }
    else return "N/A";
}

/***********************************************
 * FUNCTION: COMPLIANCEFORGE SCF EXTRA VERSION *
 ***********************************************/
function complianceforge_scf_extra_version()
{
	// Get the extra path
	$path = realpath(__DIR__ . '/../complianceforgescf/index.php');

	// If the extra is installed
	if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return COMPLIANCEFORGE_SCF_EXTRA_VERSION;
    }
    else return "N/A";
}

/**************************************
 * FUNCTION: GOVERNANCE EXTRA VERSION *
 **************************************/
function governance_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../governance/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return GOVERNANCE_EXTRA_VERSION;
    }
    else return "N/A";
}

/*****************************************
 * FUNCTION: CUSTOMIZATION EXTRA VERSION *
 *****************************************/
function customization_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../customization/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return CUSTOMIZATION_EXTRA_VERSION;
    }
    else return "N/A";
}

/*******************************************
 * FUNCTION: ADVANCED SEARCH EXTRA VERSION *
 *******************************************/
function advanced_search_extra_version()
{
    // Get the extra path
    $path = realpath(__DIR__ . '/../advanced_search/index.php');

    // If the extra is installed
    if (file_exists($path))
    {
        // Include the extra
        require_once($path);

        // Return the version
        return ADVANCED_SEARCH_EXTRA_VERSION;
    }
    else return "N/A";
}


?>

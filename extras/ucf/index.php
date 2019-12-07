<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the controls and frameworks        *
 * that are associated with the Unified Compliance Framework.       *
 ********************************************************************/

// Extra Version
define('UCF_EXTRA_VERSION', '20191130-001');

// UCF Server URL
define('UCF_SERVER_URL', 'https://api.unifiedcompliance.com/');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/governance.php'));
require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_ucf_extra_database();

/******************************
 * FUNCTION: ENABLE UCF EXTRA *
 ******************************/
function enable_ucf_extra()
{
	prevent_extra_double_submit("ucf", true);

	update_or_insert_setting('ucf', true);

	$GLOBALS['ucf_extra'] = true;

	// Open the database connection
	$db = db_open();

        // Add the table for UCF Authority Document lists
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `ucf_ad_lists` (`id` int(11) NOT NULL PRIMARY KEY, `name` text DEFAULT NULL, `whitebox_share` varchar(1) DEFAULT NULL, `publish` varchar(1) DEFAULT NULL, `publish_status` varchar(1) DEFAULT NULL, `restricted` varchar(1) DEFAULT NULL, `active_status` varchar(1) DEFAULT NULL, `outofdate` varchar(1) DEFAULT NULL, time_created text DEFAULT NULL, date_created DATE DEFAULT NULL, time_updated text DEFAULT NULL, date_updated DATE DEFAULT NULL, `framework_id` int(11) DEFAULT NULL, `selected` BOOL DEFAULT 1, `simplerisk_framework_id` int(11) DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        // Add the table for UCF Authority Documents
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `ucf_authority_documents` (id int(11) NOT NULL PRIMARY KEY, live text DEFAULT NULL, deprecated_by text DEFAULT NULL, deprecation_notes text DEFAULT NULL, time_created text DEFAULT NULL, date_added DATE DEFAULT NULL, time_updated text DEFAULT NULL, date_modified DATE DEFAULT NULL, language text DEFAULT NULL, license_info text DEFAULT NULL, sort_value int(11) DEFAULT NULL, common_name text DEFAULT NULL, published_name text DEFAULT NULL, published_version text DEFAULT NULL, official_name text DEFAULT NULL, type text DEFAULT NULL, url text DEFAULT NULL, description text DEFAULT NULL, title_type text DEFAULT NULL, availability text DEFAULT NULL, parent_category text DEFAULT NULL, originator text DEFAULT NULL, status text DEFAULT NULL, effective_date DATE DEFAULT NULL, release_date DATE DEFAULT NULL, citation_format text DEFAULT NULL, tab_category text DEFAULT NULL, will_supercede_id text DEFAULT NULL, subject_matter text DEFAULT NULL, request_id text DEFAULT NULL, genealogy text DEFAULT NULL, sort_id text DEFAULT NULL, release_availability text DEFAULT NULL,  price text DEFAULT NULL, `selected` BOOL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

	// Close the database connection
	db_close($db);

	// Audit log entry for Extra turned on
	$message = "Unified Compliance Framework (UCF) was toggled on by username \"" . $_SESSION['user'] . "\".";
	write_log(1000, $_SESSION['uid'], $message, 'extra');
}

/*******************************
 * FUNCTION: DISABLE UCF EXTRA *
 *******************************/
function disable_ucf_extra()
{
    prevent_extra_double_submit("ucf", false);

    update_or_insert_setting('ucf', false);

    $GLOBALS['ucf_extra'] = false;

    // Open the database connection
    $db = db_open();

    // Remove the ucf_ad_lists table
    $stmt = $db->prepare("DROP TABLE IF EXISTS `ucf_ad_lists`;");
    $stmt->execute();

    // Remove the ucf_authority_documents table
    $stmt = $db->prepare("DROP TABLE IF EXISTS `ucf_authority_documents`;");
    $stmt->execute();
    
    // Close the database connection
    db_close($db);

    // Audit log entry for Extra turned off
    $message = "Unified Compliance Framework (UCF) was toggled off by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');
}

/*************************
 * FUNCTION: UCF VERSION *
 *************************/
function ucf_version()
{
    // Return the version
    return UCF_EXTRA_VERSION;
}

/*************************
 * FUNCTION: DISPLAY UCF *
 *************************/
function display_ucf()
{
    global $escaper;
    global $lang;

    echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . ucf_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";
}

/***************************************
 * FUNCTION: DISPLAY UCF EXTRA OPTIONS *
 ***************************************/
function display_ucf_extra_options()
{
	global $escaper, $lang;

	$UCFAPIKey = get_setting('UCFAPIKey');

	$result = get_ucf_my_account($UCFAPIKey);
	$json = json_decode($result, true);

	if (isset($json['success']) && $json['$success'] == false)
	{
		$valid = false;
	}
	else $valid = true;

	$UCFAPIKey = $escaper->escapeHtml($UCFAPIKey);

	// If the connection is not valid
	if (!$valid)
	{
		echo "<div class='alert alert-danger'>" . $escaper->escapeHtml($lang['UCFConnectionSettingsWarning']) . "</div>";
	}
	else
	{
		echo "<div class='alert alert-success'>" . $escaper->escapeHtml($lang['UCFConnectionSettingsSuccess']) . "</div>";
	}

	echo "
		<form method='POST'>
		<h4>Connection Settings</h4>
		<div class='row-fluid'>
			<div class='span2'>UCF API Key</div>
			<div class='span3'><input type='password' name='UCFAPIKey' required value='{$UCFAPIKey}' /></div>
		</div>
		<p><input value='".$escaper->escapeHtml($lang['Update'])."' name='update_connection_settings' type='submit'></p>
		</form>
		<br/>
	";

	// If the connection is valid
	if ($valid)
	{
		display_ucf_selections();
	}
}

/********************************************
 * FUNCTION: UPDATE UCF CONNECTION SETTINGS *
 ********************************************/
function update_ucf_connection_settings()
{
	global $lang;

	$UCFAPIKey = isset($_POST['UCFAPIKey']) ? trim($_POST['UCFAPIKey']) : '';

	// If the API Key doesn't exist
	if (!$UCFAPIKey)
	{
		set_alert(true, "bad", $lang['UCFAPIKeyIsRequired']);
		return false;
	}

	update_or_insert_setting('UCFAPIKey', $UCFAPIKey);
}

/********************************
 * FUNCTION: GET UCF MY ACCOUNT *
 ********************************/
function get_ucf_my_account($UCFAPIKey)
{
	// If we don't have a UCF API Key
	if (!$UCFAPIKey)
	{
		return false;
	}

	// Create the request URL
	$url = UCF_SERVER_URL . "my-account";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	$headers = [
		'Authorization: Bearer '. $UCFAPIKey
	];

	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	$result = curl_exec($ch);

	curl_close($ch);

	// Log the URL we called
	write_debug_log("HTTP GET Request made to: " . $url);

	// Return the result
	return $result;
}

/******************************
 * FUNCTION: GET UCF AD LISTS *
 ******************************/
function get_ucf_ad_lists()
{
        // Get the API key
        $UCFAPIKey = get_setting('UCFAPIKey');

	// Get the UCF account data so we can get the list of authority documents
	$result = get_ucf_my_account($UCFAPIKey);
	$json = json_decode($result, true);

	// If we successfully obtained the account data
        if (!(isset($json['success']) && $json['$success'] == false))
        {
		// Get the list of authority documents
		$ad_lists = $json['ad_lists'];

		// Sort the list of authority documents by name
		foreach($ad_lists as $key => $row)
		{
			$ad_lists_name[$key] = $row['name'];
		}
		array_multisort($ad_lists_name, SORT_ASC, $ad_lists);

		// Return the ad lists
		return $ad_lists;
        }
	else return false;
}

/***************************************
 * FUNCTION: GET UCF AD LISTS SELECTED *
 ***************************************/
function get_ucf_ad_lists_selected()
{
        // Open the database connection
        $db = db_open();

        // Get selected authority document lists
        $stmt = $db->prepare("SELECT * FROM `ucf_ad_lists` WHERE selected=1 ORDER BY name;");
        $stmt->execute();
        $ucf_ad_lists = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

	// Return the selected ucf ad lists
	return $ucf_ad_lists;
}

/*******************************************
 * FUNCTION: GET UCF AD LISTS NOT SELECTED *
 *******************************************/
function get_ucf_ad_lists_not_selected()
{
        // Open the database connection
        $db = db_open();

        // Get not selected authority document lists
        $stmt = $db->prepare("SELECT * FROM `ucf_ad_lists` WHERE selected=0 ORDER BY name;");
        $stmt->execute();
        $ucf_ad_lists = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // Return the not selected ucf ad lists
        return $ucf_ad_lists;
}

/**************************************************
 * FUNCTION: GET UCF AUTHORITY DOCUMENTS SELECTED *
 **************************************************/
function get_ucf_authority_documents_selected()
{
        // Open the database connection
        $db = db_open();

        // Get selected authority documents
        $stmt = $db->prepare("SELECT * FROM `ucf_authority_documents` WHERE selected=1 ORDER BY common_name;");
        $stmt->execute();
        $ucf_authority_documents = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // Return the selected ucf authority documents
        return $ucf_authority_documents;
}

/******************************************************
 * FUNCTION: GET UCF AUTHORITY DOCUMENTS NOT SELECTED *
 ******************************************************/
function get_ucf_authority_documents_not_selected()
{
        // Open the database connection
        $db = db_open();

        // Get not selected authority documents
        $stmt = $db->prepare("SELECT * FROM `ucf_authority_documents` WHERE selected=0 ORDER BY common_name;");
        $stmt->execute();
        $ucf_authority_documents = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // Return the selected ucf authority documents
        return $ucf_authority_documents;
}

/*********************************
 * FUNCTION: UPDATE UCF AD LISTS *
 *********************************/
function update_ucf_ad_lists()
{
	global $lang, $escaper;

        // Open the database connection
        $db = db_open();

        // Add the table for UCF Authority Document lists
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `ucf_ad_lists` (`id` int(11) NOT NULL PRIMARY KEY, `name` text DEFAULT NULL, `whitebox_share` varchar(1) DEFAULT NULL, `publish` varchar(1) DEFAULT NULL, `publish_status` varchar(1) DEFAULT NULL, `restricted` varchar(1) DEFAULT NULL, `active_status` varchar(1) DEFAULT NULL, `outofdate` varchar(1) DEFAULT NULL, time_created text DEFAULT NULL, date_created DATE DEFAULT NULL, time_updated text DEFAULT NULL, date_updated DATE DEFAULT NULL, `framework_id` int(11) DEFAULT NULL, `selected` BOOL DEFAULT 1, `simplerisk_framework_id` int(11) DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        // Get the UCF authority document lists
        $ad_lists = get_ucf_ad_lists();

        // For each entry in the list of authority documents
        foreach ($ad_lists as $ad_list)
        {
                $id = $ad_list['id'];
                $name = $ad_list['name'];
                $whitebox_share = $ad_list['whitebox_share'];
                $publish = $ad_list['publish'];
                $publish_status = $ad_list['publish_status'];
                $restricted = $ad_list['restricted'];
                $active_status = $ad_list['active_status'];
                $outofdate = $ad_list['outofdate'];
                $time_created = $ad_list['time_created'];
                $date_created = $ad_list['date_created'];
                $time_updated = $ad_list['time_updated'];
                $date_updated = $ad_list['date_updated'];

                // Insert or update the value into the database
                $stmt = $db->prepare("INSERT INTO `ucf_ad_lists` VALUES (:id, :name, :whitebox_share, :publish, :publish_status, :restricted, :active_status, :outofdate, :time_created, :date_created, :time_updated, :date_updated, null, 0, null) ON DUPLICATE KEY UPDATE whitebox_share=:whitebox_share, publish=:publish, publish_status=:publish_status, restricted=:restricted, active_status=:active_status, outofdate=:outofdate, time_created=:time_created, date_created=:date_created, time_updated=:time_updated, date_updated=:date_updated;");
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->bindParam(":name", $name, PDO::PARAM_STR);
                $stmt->bindParam(":whitebox_share", $whitebox_share);
                $stmt->bindParam(":publish", $publish);
                $stmt->bindParam(":publish_status", $publish_status);
                $stmt->bindParam(":restricted", $restricted);
                $stmt->bindParam(":active_status", $active_status);
                $stmt->bindParam(":outofdate", $outofdate);
                $stmt->bindParam(":time_created", $time_created);
                $stmt->bindParam(":date_created", $date_created);
                $stmt->bindParam(":time_updated", $time_updated);
                $stmt->bindParam(":date_updated", $date_updated);
                $stmt->execute();
        }

        // Close the database connection
        db_close($db);
}

/********************************************
 * FUNCTION: UPDATE UCF AUTHORITY DOCUMENTS *
 ********************************************/
function update_ucf_authority_documents()
{
        global $lang, $escaper;

	// Get the UCF API Key
	$UCFAPIKey = get_setting('UCFAPIKey');

        // Open the database connection
        $db = db_open();

        // Add the table for UCF Authority Documents
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `ucf_authority_documents` (id int(11) NOT NULL PRIMARY KEY, live text DEFAULT NULL, deprecated_by text DEFAULT NULL, deprecation_notes text DEFAULT NULL, time_created text DEFAULT NULL, date_added DATE DEFAULT NULL, time_updated text DEFAULT NULL, date_modified DATE DEFAULT NULL, language text DEFAULT NULL, license_info text DEFAULT NULL, sort_value int(11) DEFAULT NULL, common_name text DEFAULT NULL, published_name text DEFAULT NULL, published_version text DEFAULT NULL, official_name text DEFAULT NULL, type text DEFAULT NULL, url text DEFAULT NULL, description text DEFAULT NULL, title_type text DEFAULT NULL, availability text DEFAULT NULL, parent_category text DEFAULT NULL, originator text DEFAULT NULL, status text DEFAULT NULL, effective_date DATE DEFAULT NULL, release_date DATE DEFAULT NULL, citation_format text DEFAULT NULL, tab_category text DEFAULT NULL, will_supercede_id text DEFAULT NULL, subject_matter text DEFAULT NULL, request_id text DEFAULT NULL, genealogy text DEFAULT NULL, sort_id text DEFAULT NULL, release_availability text DEFAULT NULL,  price text DEFAULT NULL, `selected` BOOL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

	// Get the list of selected document lists
	$ucf_ad_lists = get_ucf_ad_lists_selected();

        // For each item in the ucf ad lists
        foreach($ucf_ad_lists as $ad_list)
        {
                $id = $ad_list['id'];
                $name = $ad_list['name'];

        	// If we have a UCF API Key
        	if ($UCFAPIKey)
        	{
                	// Create the request URL
                	$url = UCF_SERVER_URL . "cch-ad-list/" . $escaper->escapeHtml($id) . "/authority-documents";

                	// Make the request
                	$ch = curl_init();
                	curl_setopt($ch, CURLOPT_URL, $url);
                	curl_setopt($ch, CURLOPT_POST, 0);
                	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                	$headers = [
                	        'Authorization: Bearer '. $UCFAPIKey
                	];
                	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                	$result = curl_exec($ch);
                	curl_close($ch);

                	// Log the URL we called
                	write_debug_log("HTTP GET Request made to: " . $url);

                	// Get the json result in an array
                	$json_array = json_decode($result, true);

			// For each entry in the json array
			foreach ($json_array as $authority_document)
			{
                		$id = (isset($authority_document['id']) ? $authority_document['id'] : null);
				$live = (isset($authority_document['live']) ? $authority_document['live'] : null);
				$deprecated_by = (isset($authority_document['deprecated_by']) ? $authority_document['deprecated_by'] : null);
				$deprecation_notes = (isset($authority_document['deprecation_notes']) ? $authority_document['deprecation_notes'] : null);
                                $time_created = (isset($authority_document['time_created']) ? $authority_document['time_created'] : null);
                                $date_added = (isset($authority_document['date_added']) ? $authority_document['date_added'] : null);
                                $time_updated = (isset($authority_document['time_updated']) ? $authority_document['time_updated'] : null);
                                $date_modified = (isset($authority_document['date_modified']) ? $authority_document['date_modified'] : null);
                                $language = (isset($authority_document['language']) ? $authority_document['language'] : null);
                                $license_info = (isset($authority_document['license_info']) ? $authority_document['license_info'] : null);
                                $sort_value = (isset($authority_document['sort_value']) ? $authority_document['sort_value'] : null);
                                $common_name = (isset($authority_document['common_name']) ? $authority_document['common_name'] : null);
                                $published_name = (isset($authority_document['published_name']) ? $authority_document['published_name'] : null);
                                $published_version = (isset($authority_document['published_version']) ? $authority_document['published_version'] : null);
                                $official_name = (isset($authority_document['official_name']) ? $authority_document['official_name'] : null);
                                $type = (isset($authority_document['type']) ? $authority_document['type'] : null);
                                $url = (isset($authority_document['url']) ? $authority_document['url'] : null);
                                $description = (isset($authority_document['description']) ? $authority_document['description'] : null);
                                $title_type = (isset($authority_document['title_type']) ? $authority_document['title_type'] : null);
                                $availability = (isset($authority_document['availability']) ? $authority_document['availability'] : null);
                                $parent_category = (isset($authority_document['parent_category']) ? $authority_document['parent_category'] : null);
                                $originator = (isset($authority_document['originator']) ? $authority_document['originator'] : null);
                                $status = (isset($authority_document['status']) ? $authority_document['status'] : null);
                                $effective_date = (isset($authority_document['effective_date']) ? $authority_document['effective_date'] : null);
                                $release_date = (isset($authority_document['release_date']) ? $authority_document['release_date'] : null);
                                $citation_format = (isset($authority_document['citation_format']) ? $authority_document['citation_format'] : null);
                                $tab_category = (isset($authority_document['tab_category']) ? $authority_document['tab_category'] : null);
                                $will_supercede_id = (isset($authority_document['will_supercede_id']) ? $authority_document['will_supercede_id'] : null);
                                $subject_matter = (isset($authority_document['subject_matter']) ? $authority_document['subject_matter'] : null);
                                $request_id = (isset($authority_document['request_id']) ? $authority_document['request_id'] : null);
                                $genealogy = (isset($authority_document['genealogy']) ? $authority_document['genealogy'] : null);
                                $sort_id = (isset($authority_document['sort_id']) ? $authority_document['sort_id'] : null);
                                $release_availability = (isset($authority_document['release_availability']) ? $authority_document['release_availability'] : null);
                                $price = (isset($authority_document['price']) ? $authority_document['price'] : null);

                		// Insert or update the value into the database
                		$stmt = $db->prepare("INSERT INTO `ucf_authority_documents` VALUES (:id, :live, :deprecated_by, :deprecation_notes, :time_created, :date_added, :time_updated, :date_modified, :language, :license_info, :sort_value, :common_name, :published_name, :published_version, :official_name, :type, :url, :description, :title_type, :availability, :parent_category, :originator, :status, :effective_date, :release_date, :citation_format, :tab_category, :will_supercede_id, :subject_matter, :request_id, :genealogy, :sort_id, :release_availability, :price, 0) ON DUPLICATE KEY UPDATE live=:live, deprecated_by=:deprecated_by, deprecation_notes=:deprecation_notes, time_created=:time_created, date_added=:date_added, time_updated=:time_updated, date_modified=:date_modified, language=:language, license_info=:license_info, sort_value=:sort_value, common_name=:common_name, published_name=:published_name, published_version=:published_version, official_name=:official_name, type=:type, url=:url, description=:description, title_type=:title_type, availability=:availability, parent_category=:parent_category, originator=:originator, status=:status, effective_date=:effective_date, release_date=:release_date, citation_format=:citation_format, tab_category=:tab_category, will_supercede_id=:will_supercede_id, subject_matter=:subject_matter, request_id=:request_id, genealogy=:genealogy, sort_id=:sort_id, release_availability=:release_availability, price=:price;");
                		$stmt->bindParam(":id", $id, PDO::PARAM_INT);
				$stmt->bindParam(":live", $live, PDO::PARAM_STR);
                                $stmt->bindParam(":deprecated_by", $deprecated_by, PDO::PARAM_STR);
                                $stmt->bindParam(":deprecation_notes", $deprecation_notes, PDO::PARAM_STR);
                                $stmt->bindParam(":time_created", $time_created, PDO::PARAM_STR);
                                $stmt->bindParam(":date_added", $date_added, PDO::PARAM_STR);
                                $stmt->bindParam(":time_updated", $time_updated, PDO::PARAM_STR);
                                $stmt->bindParam(":date_modified", $date_modified, PDO::PARAM_STR);
                                $stmt->bindParam(":language", $language, PDO::PARAM_STR);
                                $stmt->bindParam(":license_info", $license_info, PDO::PARAM_STR);
                                $stmt->bindParam(":sort_value", $sort_value, PDO::PARAM_STR);
                                $stmt->bindParam(":common_name", $common_name, PDO::PARAM_STR);
                                $stmt->bindParam(":published_name", $published_name, PDO::PARAM_STR);
                                $stmt->bindParam(":published_version", $published_version, PDO::PARAM_STR);
                                $stmt->bindParam(":official_name", $official_name, PDO::PARAM_STR);
                                $stmt->bindParam(":type", $type, PDO::PARAM_STR);
                                $stmt->bindParam(":url", $url, PDO::PARAM_STR);
                                $stmt->bindParam(":description", $description, PDO::PARAM_STR);
                                $stmt->bindParam(":title_type", $title_type, PDO::PARAM_STR);
                                $stmt->bindParam(":availability", $availability, PDO::PARAM_STR);
                                $stmt->bindParam(":parent_category", $parent_category, PDO::PARAM_STR);
                                $stmt->bindParam(":originator", $originator, PDO::PARAM_STR);
                                $stmt->bindParam(":status", $status, PDO::PARAM_STR);
                                $stmt->bindParam(":effective_date", $effective_date, PDO::PARAM_STR);
                                $stmt->bindParam(":release_date", $release_date, PDO::PARAM_STR);
                                $stmt->bindParam(":citation_format", $citation_format, PDO::PARAM_STR);
                                $stmt->bindParam(":tab_category", $tab_category, PDO::PARAM_STR);
                                $stmt->bindParam(":will_supercede_id", $will_supercede_id, PDO::PARAM_STR);
                                $stmt->bindParam(":subject_matter", $subject_matter, PDO::PARAM_STR);
                                $stmt->bindParam(":request_id", $request_id, PDO::PARAM_STR);
                                $stmt->bindParam(":genealogy", $genealogy, PDO::PARAM_STR);
                                $stmt->bindParam(":sort_id", $sort_id, PDO::PARAM_STR);
                                $stmt->bindParam(":release_availability", $release_availability, PDO::PARAM_STR);
                                $stmt->bindParam(":price", $price, PDO::PARAM_STR);
				$stmt->execute();
			}
		}
        }
	
	
        // Close the database connection
        db_close($db);
}

/***********************************
 * FUNCTION: DISPLAY UCF SELECTION *
 ***********************************/
function display_ucf_selections()
{
	global $lang, $escaper;

        // Update the UCF authority document lists
        update_ucf_ad_lists();

	// Update the UCF authority documents
	update_ucf_authority_documents();

	// Open the database connection
	$db = db_open();

	echo "<div class=\"ucf_ad_lists\">\n";
	echo "<form name=\"ucf_ad_lists\" id=\"ucf_ad_lists\" method=\"POST\" action=\"\">\n";
	echo "<h4>UCF Authority Document Lists</h4>\n";
	echo "<table width=\"700px\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
	echo "<tr>\n";
	echo "<td width=\"300px\" valign=\"middle\">\n";

	echo "<div class=\"ad-list-box-1\">\n";
	echo "<h6>Disabled</h6>\n";
	echo "<select multiple=\"multiple\" id=\"ucf_ad_list_disabled\" name=\"ucf_ad_list_disabled[]\" class=\"form-control\">\n";

        // Get not selected authority document lists
        $ucf_ad_lists = get_ucf_ad_lists_not_selected();

	// For each item in the ucf ad lists
	foreach($ucf_ad_lists as $ad_list)
	{
		$id = $ad_list['id'];
		$name = $ad_list['name'];

		echo "<option value=\"" . $escaper->escapehtml($id) . "\">" . $escaper->escapehtml($name) . "</option>\n";
	}

	echo "</select>\n";
	echo "</div>\n";

	echo "</td>\n";
	echo "<td width=\"100px\" valign=\"middle\">\n";

	echo "&nbsp;";
	echo "<div class=\"subject-info-arrows text-center\">\n";
	//echo "<input style=\"width:75px;\" type=\"button\" id=\"btnAllRight\" value=\">>\" class=\"btn btn-default\" /><br />\n";
	echo "<input style=\"width:75px;\" type=\"submit\" name=\"ucf_ad_list_enable\" id=\"adListBtnRight\" value=\">\" class=\"btn btn-default\" /><br />\n";
	echo "<input style=\"width:75px;\" type=\"submit\" name=\"ucf_ad_list_disable\" id=\"adListBtnLeft\" value=\"<\" class=\"btn btn-default\" /><br />\n";
	//echo "<input style=\"width:75px;\" type=\"button\" id=\"btnAllLeft\" value=\"<<\" class=\"btn btn-default\" /><br />\n";
	echo "</div>\n";

	echo "</td>\n";
	echo "<td width=\"300px\" valign=\"middle\">\n";

	echo "<h6>Enabled</h6>\n";
	echo "<div class=\"ad-list-box-2\">\n";
	echo "<select multiple=\"multiple\" id=\"ucf_ad_list_enabled\" name=\"ucf_ad_list_enabled[]\" class=\"form-control\">\n";

        // Get selected authority document lists
        $ucf_ad_lists = get_ucf_ad_lists_selected();

        // For each item in the ucf ad lists
        foreach($ucf_ad_lists as $ad_list)
        {
                $id = $ad_list['id'];
                $name = $ad_list['name'];

                echo "<option value=\"" . $escaper->escapehtml($id) . "\">" . $escaper->escapehtml($name) . "</option>\n";
        }

        echo "</select>\n";
        echo "</div>\n";

	echo "<div class=\"clearfix\"></div>\n";
	echo "</td></tr>\n";
	echo "</table>\n";
	echo "</form>\n";
	echo "</div>\n";

	// If we have at least one enabled authority document list
	if (count($ucf_ad_lists) > 0)
	{
	        echo "<div class=\"ucf_authority_documents\">\n";
	        echo "<form name=\"ucf_authority_documents\" id=\"ucf_authority_documents\" method=\"POST\" action=\"\">\n";
	        echo "<h4>UCF Authority Documents</h4>\n";
	        echo "<table width=\"700px\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
	        echo "<tr>\n";
	        echo "<td width=\"300px\" valign=\"middle\">\n";

	        echo "<div class=\"authority-document-box-1\">\n";
	        echo "<h6>Disabled</h6>\n";
	        echo "<select multiple=\"multiple\" id=\"ucf_authority_documents_disabled\" name=\"ucf_authority_documents_disabled[]\" class=\"form-control\">\n";

	        // Get not selected authority documents
	        $ucf_authority_documents = get_ucf_authority_documents_not_selected();

	        // For each item in the ucf ad lists
	        foreach($ucf_authority_documents as $authority_document)
	        {
	                $id = $authority_document['id'];
	                $name = $authority_document['common_name'];

	                echo "<option value=\"" . $escaper->escapehtml($id) . "\">" . $escaper->escapehtml($name) . "</option>\n";
	        }

	        echo "</select>\n";
	        echo "</div>\n";

	        echo "</td>\n";
	        echo "<td width=\"100px\" valign=\"middle\">\n";

	        echo "&nbsp;";
	        echo "<div class=\"subject-info-arrows text-center\">\n";
	        //echo "<input style=\"width:75px;\" type=\"button\" id=\"btnAllRight\" value=\">>\" class=\"btn btn-default\" /><br />\n";
	        echo "<input style=\"width:75px;\" type=\"submit\" name=\"ucf_authority_documents_enable\" id=\"adBtnRight\" value=\">\" class=\"btn btn-default\" /><br />\n";
	        echo "<input style=\"width:75px;\" type=\"submit\" name=\"ucf_authority_documents_disable\" id=\"adBtnLeft\" value=\"<\" class=\"btn btn-default\" /><br />\n";
	        //echo "<input style=\"width:75px;\" type=\"button\" id=\"btnAllLeft\" value=\"<<\" class=\"btn btn-default\" /><br />\n";
	        echo "</div>\n";

	        echo "</td>\n";
	        echo "<td width=\"300px\" valign=\"middle\">\n";

	        echo "<h6>Enabled</h6>\n";
	        echo "<div class=\"authority-document-box-2\">\n";
	        echo "<select multiple=\"multiple\" id=\"ucf_authority_documents_enabled\" name=\"ucf_authority_documents_enabled[]\" class=\"form-control\">\n";
	
                // Get selected authority documents
                $ucf_authority_documents = get_ucf_authority_documents_selected();

                // For each item in the ucf ad lists
                foreach($ucf_authority_documents as $authority_document)
                {
                        $id = $authority_document['id'];
                        $name = $authority_document['common_name'];

                        echo "<option value=\"" . $escaper->escapehtml($id) . "\">" . $escaper->escapehtml($name) . "</option>\n";
                }

	        echo "</select>\n";
	        echo "</div>\n";

	        echo "<div class=\"clearfix\"></div>\n";
	        echo "</td></tr>\n";
	        echo "</table>\n";
	        echo "</form>\n";
	        echo "</div>\n";
	}


        // Close the database connection
        db_close($db);
}

/************************************
 * FUNCTION: INSTALL UCF FRAMEWORKS *
 ************************************/
function install_ucf_frameworks()
{
	// Get the list of selected frameworks
	$frameworks = $_POST['frameworks'];

        // Open the database connection
        $db = db_open();

        // For each selected framework
        foreach ($frameworks as $framework)
        {
		$framework_id = (int) $framework;

		// Install the framework
		install_ucf_framework($framework_id);

		// Update the AD list as selected
		$stmt = $db->prepare("UPDATE `ucf_ad_lists` SET selected=1 WHERE id=:id;");
		$stmt->bindParam(":id", $framework_id, PDO::PARAM_INT);
		$stmt->execute();
        }

	// Close the database connection
	db_close($db);
}

/**************************************
 * FUNCTION: UNINSTALL UCF FRAMEWORKS *
 **************************************/
function uninstall_ucf_frameworks($simplerisk_framework_id = null)
{
        // Open the database connection
        $db = db_open();

	// If the simplerisk framework id is null
	if ($simplerisk_framework_id == null)
	{
        	// Get the list of selected frameworks from the POST
        	$frameworks = $_POST['frameworks'];
		
	}
	else
	{
		// Get the ucf AD list id associated with the simplerisk framework id
		$stmt = $db->prepare("SELECT `id` FROM `ucf_ad_lists` WHERE simplerisk_framework_id=:simplerisk_framework_id;");
		$stmt->bindParam(":simplerisk_framework_id", $simplerisk_framework_id, PDO::PARAM_INT);
		$stmt->execute();
		$frameworks = $stmt->fetch();
	}

        // For each selected framework
        foreach ($frameworks as $framework)
        {
                $framework_id = (int) $framework;

		// Get the SimpleRisk framework id associated with the ucf framework
		$stmt = $db->prepare("SELECT `simplerisk_framework_id` FROM `ucf_ad_lists` WHERE id=:id;");
		$stmt->bindParam(":id", $framework_id, PDO::PARAM_INT);
		$stmt->execute();
		$simplerisk_framework = $stmt->fetch();

		// Delete the SimpleRisk framework id
		delete_frameworks($simplerisk_framework['simplerisk_framework_id']);

                // Update the framework as not selected
                $stmt = $db->prepare("UPDATE `ucf_ad_lists` SET selected=0, simplerisk_framework_id=null WHERE id=:id;");
                $stmt->bindParam(":id", $framework_id, PDO::PARAM_INT);
                $stmt->execute();
        }

        // Close the database connection
        db_close($db);
}

/***********************************
 * FUNCTION: INSTALL UCF FRAMEWORK *
 ***********************************/
function install_ucf_framework($framework_id)
{
	global $escaper;

	// Get the UCF API Key
	$UCFAPIKey = get_setting('UCFAPIKey');

        // If we have a UCF API Key
        if ($UCFAPIKey)
        {
	        // Open the database connection
	        $db = db_open();

	        // Create the request URL
	        $url = UCF_SERVER_URL . "cch-ad-list/" . $escaper->escapeHtml($framework_id) . "/authority-documents";

		// Make the request
	        $ch = curl_init();
	        curl_setopt($ch, CURLOPT_URL, $url);
	        curl_setopt($ch, CURLOPT_POST, 0);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	        $headers = [
	                'Authorization: Bearer '. $UCFAPIKey
	        ];
	        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	        $result = curl_exec($ch);
	        curl_close($ch);

	        // Log the URL we called
	        write_debug_log("HTTP GET Request made to: " . $url);

		// Get the json result in an array
		$json_array = json_decode($result, true);
		$id = (isset($json_array[0]['id']) ? $json_array[0]['id'] : null);
		$live = (isset($json_array[0]['live']) ? $json_array[0]['live'] : null);
		$deprecated_by = (isset($json_array[0]['depricated_by']) ? $json_array[0]['depricated_by'] : null);
		$deprecation_notes = (isset($json_array[0]['deprication_notes']) ? $json_array[0]['deprication_notes'] : null);
		$time_created = (isset($json_array[0]['time_created']) ? $json_array[0]['time_created'] : null);
		$date_added = (isset($json_array[0]['date_added']) ? $json_array[0]['date_added'] : null);
		$time_updated = (isset($json_array[0]['time_update']) ? $json_array[0]['time_update'] : null);
		$date_modified = (isset($json_array[0]['date_modified']) ? $json_array[0]['date_modified'] : null);
		$language = (isset($json_array[0]['language']) ? $json_array[0]['language'] : null);
		$license_info = (isset($json_array[0]['license_info']) ? $json_array[0]['license_info'] : null);
		$sort_value = (isset($json_array[0]['sort_value']) ? $json_array[0]['sort_value'] : null);
		$common_name = (isset($json_array[0]['common_name']) ? $json_array[0]['common_name'] : null);
		$published_name = (isset($json_array[0]['published_name']) ? $json_array[0]['published_name'] : null);
		$published_version = (isset($json_array[0]['published_version']) ? $json_array[0]['published_version'] : null);
		$official_name = (isset($json_array[0]['official_name']) ? $json_array[0]['official_name'] : null);
		$type = (isset($json_array[0]['type']) ? $json_array[0]['type'] : null);
		$url = (isset($json_array[0]['url']) ? $json_array[0]['url'] : null);
		$description = (isset($json_array[0]['description']) ? $json_array[0]['description'] : null);
		$title_type = (isset($json_array[0]['title_type']) ? $json_array[0]['title_type'] : null);
		$availability = (isset($json_array[0]['availability']) ? $json_array[0]['availability'] : null);
		$parent_category = (isset($json_array[0]['parent_category']) ? $json_array[0]['parent_category'] : null);
		$originator = (isset($json_array[0]['originator']) ? $json_array[0]['originator'] : null);
		$status = (isset($json_array[0]['status']) ? $json_array[0]['status'] : null);
		$effective_date = (isset($json_array[0]['effective_date']) ? $json_array[0]['effective_date'] : null);
		$release_date = (isset($json_array[0]['release_date']) ? $json_array[0]['release_date'] : null);
		$citation_format = (isset($json_array[0]['citation_format']) ? $json_array[0]['citation_format'] : null);
		$tab_category = (isset($json_array[0]['tab_category']) ? $json_array[0]['tab_category'] : null);
		$will_supercede_id = (isset($json_array[0]['will_supercede_id']) ? $json_array[0]['will_supercede_id'] : null);
		$subject_matter = (isset($json_array[0]['subject_matter']) ? $json_array[0]['subject_matter'] : null);
		$request_id = (isset($json_array[0]['request_id']) ? $json_array[0]['request_id'] : null);
		$genealogy = (isset($json_array[0]['genealogy']) ? $json_array[0]['genealogy'] : null);
		$sort_id = (isset($json_array[0]['sort_id']) ? $json_array[0]['sort_id'] : null);
		$release_availability = (isset($json_array[0]['release_availability']) ? $json_array[0]['release_availability'] : null);
		$price = (isset($json_array[0]['price']) ? $json_array[0]['price'] : null);

		// Add the new framework
		$simplerisk_framework_id = add_framework($official_name, $description);
                $stmt = $db->prepare("UPDATE `ucf_ad_lists` SET simplerisk_framework_id=:simplerisk_framework_id WHERE id=:id;");
		$stmt->bindParam(":simplerisk_framework_id", $simplerisk_framework_id, PDO::PARAM_INT);
                $stmt->bindParam(":id", $framework_id, PDO::PARAM_INT);
                $stmt->execute();

                // Create the request URL
                $url = UCF_SERVER_URL . "cch-ad-list/" . $escaper->escapeHtml($framework_id) . "/tracked-controls/details";

                // Make the request
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $headers = [
                        'Authorization: Bearer '. $UCFAPIKey
                ];
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $result = curl_exec($ch);
                curl_close($ch);

	        // Log the URL we called
	        write_debug_log("HTTP GET Request made to: " . $url);

                // Get the json result in an array
                $json_array = json_decode($result, true);

	        // Close the database connection
	        db_close($db);
	}
}

/*********************************
 * FUNCTION: ENABLE UCF AD LISTS *
 *********************************/
function enable_ucf_ad_lists()
{
	// If we were provided with an item from the disabled list
	if (isset($_POST['ucf_ad_list_disabled']))
	{
                // Open the database connection
                $db = db_open();

		// Get the selected ad lists
		$ad_lists = $_POST['ucf_ad_list_disabled'];
        
		// For each ad list
		foreach ($ad_lists as $ad_list)
		{
			$id = (int) $ad_list;
                
                	// Update the AD list as selected
                	$stmt = $db->prepare("UPDATE `ucf_ad_lists` SET selected=1 WHERE id=:id;");
                	$stmt->bindParam(":id", $id, PDO::PARAM_INT);
                	$stmt->execute();
        	}

                // Close the database connection
                db_close($db);
	}
}

/**********************************
 * FUNCTION: DISABLE UCF AD LISTS *
 **********************************/
function disable_ucf_ad_lists()
{
	// If we were provided with an item from the enabled list
	if (isset($_POST['ucf_ad_list_enabled']))
	{
                // Open the database connection
                $db = db_open();

                // Get the selected ad lists
                $ad_lists = $_POST['ucf_ad_list_enabled'];

                // For each ad list
                foreach ($ad_lists as $ad_list)
                {
                        $id = (int) $ad_list;

                        // Update the AD list as unselected
                        $stmt = $db->prepare("UPDATE `ucf_ad_lists` SET selected=0 WHERE id=:id;");
                        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                        $stmt->execute();
                }

                // Close the database connection
                db_close($db);
	}
}

/********************************************
 * FUNCTION: ENABLE UCF AUTHORITY DOCUMENTS *
 ********************************************/
function enable_ucf_authority_documents()
{
        // If we were provided with an item from the disabled list
        if (isset($_POST['ucf_authority_documents_disabled']))
        {
                // Open the database connection
                $db = db_open();

                // Get the selected authority_documents
                $authority_documents = $_POST['ucf_authority_documents_disabled'];

                // For each authority document
                foreach ($authority_documents as $authority_document)
                {
                        $id = (int) $authority_document;

                        // Update the authority document as selected
                        $stmt = $db->prepare("UPDATE `ucf_authority_documents` SET selected=1 WHERE id=:id;");
                        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                        $stmt->execute();
                }

                // Close the database connection
                db_close($db);
        }
}

/*********************************************
 * FUNCTION: DISABLE UCF AUTHORITY DOCUMENTS *
 *********************************************/
function disable_ucf_authority_documents()
{
        // If we were provided with an item from the enabled list
        if (isset($_POST['ucf_authority_documents_enabled']))
        {
                // Open the database connection
                $db = db_open();

                // Get the selected authority_documents
                $authority_documents = $_POST['ucf_authority_documents_enabled'];

                // For each authority document
                foreach ($authority_documents as $authority_document)
                {
                        $id = (int) $authority_document;

                        // Update the authority document as selected
                        $stmt = $db->prepare("UPDATE `ucf_authority_documents` SET selected=0 WHERE id=:id;");
                        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                        $stmt->execute();
                }

                // Close the database connection
                db_close($db);
        }
}

?>

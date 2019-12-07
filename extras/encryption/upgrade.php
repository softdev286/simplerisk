<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

// Name of the version value in the settings table
//define('VERSION_NAME', 'encryption_extra_version');

global $encryption_updates;
$encryption_updates = array(
    'upgrade_encryption_extra_20171020001',
    'upgrade_encryption_extra_20181119001',
    'upgrade_encryption_extra_20190119001',
    'upgrade_encryption_extra_20190127001',
    'upgrade_encryption_extra_20190718001'
);

/***********************************************
 * FUNCTION: UPGRADE ENCRYPTION EXTRA DATABASE *
 ***********************************************/
function upgrade_encryption_extra_database()
{
    global $encryption_updates;

    $version_name = 'encryption_extra_version';

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
    if (array_key_exists($db_version, $encryption_updates))
    {
        // Get the function to upgrade to the next version
        $function = $encryption_updates[$db_version];

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
            upgrade_encryption_extra_database();
        }
    }
}

/**************************************************
 * FUNCTION: UPGRADE ENCRYPTION EXTRA 20170925001 *
 **************************************************/
function upgrade_encryption_extra_20171020001(){
    if(encryption_extra()){
        // Create encrypted framework table
        create_encrypted_framework($_SESSION['encrypted_pass']);
    }
}

/**************************************************
 * FUNCTION: UPGRADE ENCRYPTION EXTRA 20181119001 *
 **************************************************/
function upgrade_encryption_extra_20181119001()
{
    if(encryption_extra()){

    // If the login form was posted
    if (isset($_POST['submit']))
    {
        write_debug_log("Upgrade is being run after a login event.");
        $user = $_POST['user'];
        $pass = $_POST['pass'];

        write_debug_log("Running check_user_enc to set up mcrypt encryption.");
        check_user_enc($user, $pass);
    }

    // Open the database connection
    $db = db_open();

    // Create a new encrypted_values table
    write_debug_log("Creating the encrypted fields table.");
    $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `encrypted_fields` (`mcrypt_name` VARCHAR(100),`table` VARCHAR(100), `field` VARCHAR(100), `encrypted` boolean, `method` VARCHAR(20) DEFAULT 'mcrypt', UNIQUE(`mcrypt_name`, `table`, `field`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // List of all encrypted database fields
    $encrypted_fields = array();

    // If the assessment_contacts table exists
    if (table_exists("assessment_contacts"))
    {
        // Add the assessment extra fields to the encrypted fields list
        $encrypted_fields['enc_assessment_contact_company'] = array("table" => "assessment_contacts", "field" => "company");
        $encrypted_fields['enc_assessment_contact_details'] = array("table" => "assessment_contacts", "field" => "details");
        $encrypted_fields['enc_assessment_contact_email'] = array("table" => "assessment_contacts", "field" => "email");
        $encrypted_fields['enc_assessment_contact_name'] = array("table" => "assessment_contacts", "field" => "name");
        $encrypted_fields['enc_assessment_contact_phone'] = array("table" => "assessment_contacts", "field" => "phone");
    }

    // If the questionnaire_responses table exists
    if (table_exists("questionnaire_responses"))
    {
        $encrypted_fields['enc_questionnaire_responses_additional_infromation'] = array("table" => "questionnaire_responses", "field" => "additional_information");
        $encrypted_fields['enc_questionnaire_responses_answer'] = array("table" => "questionnaire_responses", "field" => "answer");
    }

    $encrypted_fields['enc_assets_details'] = array("table" => "assets", "field" => "details");
    $encrypted_fields['enc_audit_log_message'] = array("table" => "audit_log", "field" => "message");
    $encrypted_fields['enc_comments_comment'] = array("table" => "comments", "field" => "comment");
    $encrypted_fields['enc_frameworks_description'] = array("table" => "frameworks", "field" => "description");
    $encrypted_fields['enc_frameworks_name'] = array("table" => "frameworks", "field" => "name");
    $encrypted_fields['enc_mgmt_reviews_comment'] = array("table" => "mgmt_reviews", "field" => "comments");
    $encrypted_fields['enc_mitigations_current_solution'] = array("table" => "mitigations", "field" => "current_solution");
    $encrypted_fields['enc_mitigations_security_recommendations'] = array("table" => "mitigations", "field" => "security_recommendations");
    $encrypted_fields['enc_mitigations_security_requirements'] = array("table" => "mitigations", "field" => "security_requirements");
    $encrypted_fields['enc_projects_name'] = array("table" => "projects", "field" => "name");
    $encrypted_fields['enc_risks_assessment'] = array("table" => "risks", "field" => "assessment");
    $encrypted_fields['enc_risks_notes'] = array("table" => "risks", "field" => "notes");
    $encrypted_fields['enc_risks_subject'] = array("table" => "risks", "field" => "subject");

    // For each of the encrypted database values
    foreach($encrypted_fields as $name => $array)
    {
            // Get the table and field
            $table = $array['table'];
            $field = $array['field'];

            // Get the current value
            $encrypted = get_setting($name);

            // Add it to the table
            $stmt = $db->prepare("INSERT IGNORE INTO `encrypted_fields` (`mcrypt_name`, `table`, `field`, `encrypted`) VALUES (:mcrypt_name, :table, :field, :encrypted);");
            $stmt->bindParam(":mcrypt_name", $name, PDO::PARAM_STR);
            $stmt->bindParam(":table", $table, PDO::PARAM_STR);
            $stmt->bindParam(":field", $field, PDO::PARAM_STR);
            $stmt->bindParam(":encrypted", $encrypted, PDO::PARAM_LOB);
            $stmt->execute();
    }

    write_debug_log("Finished adding all values into the encrypted_fields table.");

    // If the mcrypt and openssl extensions are loaded
    if (installed_mcrypt() && installed_openssl())
    {
        write_debug_log("MCrypt and OpenSSL are both installed.");

            // Get and check mysqldump service is available.
            if(!is_process('mysqldump'))
        {
            $mysqldump_path = get_setting('mysqldump_path');
        }
        else
        {
            $mysqldump_path = "mysqldump";
        }

        // Sanitize the mysqldump command
        $cmd = $mysqldump_path." --opt --lock-tables=false -h " . escapeshellarg(DB_HOSTNAME) . " -u " . escapeshellarg(DB_USERNAME) . " -p" . escapeshellarg(DB_PASSWORD) . " " . escapeshellarg(DB_DATABASE) . " > " . sys_get_temp_dir() . "/simplerisk-" . date('Ymd') . ".sql";
        
        // Execute the mysqldump
        $mysqldump = system($cmd);
        write_debug_log("Performed a mysqldump of the database.");
        
        // If the encrypted pass is set in the session
        if (isset($_SESSION['encrypted_pass']))
        {
            write_debug_log("Verified that the encrypted password is set in the session.");

            // Get the encrypted password from the session
            $password = $_SESSION['encrypted_pass'];
            write_debug_log("The encrypted password is set to: " . $password);

            write_debug_log("Creating encrypted_fields entries for asset ip, asset name, and framework_control_test_comments comment fields.");

            // Enable encryption for asset ip and name fields
            $stmt = $db->prepare("INSERT IGNORE INTO `encrypted_fields` (`mcrypt_name`, `table`, `field`, `encrypted`, `method`) VALUES ('enc_assets_ip', 'assets', 'ip', 0, 'openssl');");
            $stmt->execute();
            $stmt = $db->prepare("INSERT IGNORE INTO `encrypted_fields` (`mcrypt_name`, `table`, `field`, `encrypted`, `method`) VALUES ('enc_assets_name', 'assets', 'name', 0, 'openssl');");
            $stmt->execute();

            // Enable encryption for framework_control_test_comments comment field
            $stmt = $db->prepare("INSERT IGNORE INTO `encrypted_fields` (`mcrypt_name`, `table`, `field`, `encrypted`, `method`) VALUES ('enc_framework_control_test_comments_comment', 'framework_control_test_comments', 'comment', 0, 'openssl');");
            $stmt->execute();

            // Disable encryption
            write_debug_log("Disabling mcrypt encryption for all fields and tables using password: " . $password);
            remove_encrypted_comments($password);
            remove_encrypted_mgmt_reviews($password);
            remove_encrypted_mitigations($password);
            remove_encrypted_projects($password);
            remove_encrypted_risks($password);
            remove_subject_order();
            remove_encrypted_audit($password);
            remove_encrypted_frameworks($password);
            remove_encrypted_assets($password);
            remove_encrypted_questionnaire_responses($password);
            remove_encrypted_assessment_contacts($password);

            // Delete the encryption iv from settings
            delete_setting("encryption_iv");

            // Delete the encryption level from settings
            delete_setting("ENCRYPTION_LEVEL");

            // Delete the encryption method from settings
            delete_setting("encryption_method");    

            // Delete the user encryption table
            $stmt = $db->prepare("DROP TABLE `user_enc`;");
            $stmt->execute();

            // Check if the init.php file exists
            if (is_file(__DIR__ . "/includes/init.php"))
            {
                // Make a backup copy of the init.php file
                $copy_result = copy (__DIR__ . "/includes/init.php", sys_get_temp_dir() . "/init-enc-" . date('Ymd') . ".php");

                // If the copy was successful
                if ($copy_result)
                {
                    // Delete the init.php file or return an error
                    if (!unlink(__DIR__ . "/includes/init.php"))
                    {
                        set_alert(true, "bad", "Unable to delete the encryption initialization file located at " . __DIR__ . "/includes/init.php");
                    }
                }
            }

            // Check if the assessments init.php file exists
            if (is_file(__DIR__ . "/../assessments/includes/init.php"))
            {
                // Make a backup copy of the init.php file
                $copy_result = copy (__DIR__ . "/../assessments/includes/init.php", sys_get_temp_dir() . "/init-ass-" . date('Ymd') . ".php");

                // If the copy was successful
                if ($copy_result)
                {
                    // Delete the init.php file or return an error
                    if (!unlink(__DIR__ . "/../assessments/includes/init.php"))
                    {
                        set_alert(true, "bad", "Unable to delete the encryption initialization file located at " . __DIR__ . "/../assessments/includes/init.php");
                    }
                }
            }
        }

        // Set the encryption method to openssl
        add_setting("encryption_method", "openssl");

        // Set the encryption method in the session
        $_SESSION['encryption_method'] = "openssl";

        // Create the encryption initialization file
        write_debug_log("Creating the new encryption initialization file.");
        $success = create_init_file();

        // If we were able to create the encryption initialization file
        if ($success)
        {
            // Fetch the key
            $key = fetch_key();
            write_debug_log("The key found in the file was: " . $key);

            // Set the encrypted password in the session
            $_SESSION['encrypted_pass'] = $key;

            // Enable encryption
            write_debug_log("Enabling openssl encryption for all fields and tables using password: " . $key);
            
            create_encrypted_comments($key);
            create_encrypted_mgmt_reviews($key);
            create_encrypted_mitigations($key);
            create_encrypted_projects($key);
            create_encrypted_risks($key);
            create_encrypted_audit($key);
            create_subject_order($key);
            create_encrypted_framework($key);
            create_encrypted_asset($key);
            create_encrypted_questionnaire_responses($key);
            create_encrypted_assessment_contacts($key);
            create_encrypted_framework_control_test_comments($key);
        }
    }
    else
    {
        // If it is the mcrypt extension that is missing
        if (!installed_mcrypt())
        {
                    set_alert(true, "bad", $lang['mCryptWarning']);

                    // Return an error
                    return 0;
        }

        // If it is the openssl extension that is missing
        if (!installed_openssl())
        {
            set_alert(true, "bad", $lang['OpensslWarning']);

            // Return an error
            return 0;
        }
    }

    // Close the database connection
    db_close($db);
    }
}

/**************************************************
 * FUNCTION: UPGRADE ENCRYPTION EXTRA 20190119001 *
 **************************************************/
function upgrade_encryption_extra_20190119001()
{
    // Find candidate backup files
    $candidates = glob(sys_get_temp_dir() . "/simplerisk-*.sql");
    $candidate_count = count($candidates);

    // If there are not any candidates
    if ($candidate_count == 0)
    {
        return false;
    }
    // If there is one candidate
    else if ($candidate_count == 1)
    {
        // Set the backup file to that name
        $backup_file = $candidates[0];
    }
    // If there are more than one candidate
    else if ($candidate_count > 1)
    {
        // Set the backup_file_date and backup_file
        $backup_file = "";
        $backup_file_date = 0;

        // For each of the candidates
        foreach($candidates as $candidate)
        {
            // Get the last modified date of the file
            $last_modified_date = filemtime($candidate);

            // If the last modified date is later than the current backup file date
            if ($last_modified_date > $backup_file_date)
            {
                // Set the backup file and backup file date
                $backup_file = $candidate;
                $backup_file_date = $last_modified_date;
            }
        }
    }

    // Open the database connection
    $db = db_open();

    // Get the contents from the backup file
    $contents = file_get_contents($backup_file);

    // Get the comments insert command from the file
    $search_pattern = "/INSERT INTO `comments`(.*)\);/";
    preg_match($search_pattern, $contents, $matches);

    // If we have a match on the pattern
    if ($matches)
    {
        $insert_command = $matches[0];

        // Remove the insert part
        $search_pattern = "/\((.*)\)/";
        preg_match($search_pattern, $insert_command, $matches);
        $inserted_values = $matches[0];

        // Break apart the comma-separated values
        $values_array = explode("),(", $inserted_values);

        // For each of the comments
        foreach($values_array as $value)
        {       
            // Remove any parentheses
            $value = trim($value, "(");
            $value = trim($value, ")");

            // Break apart the comma-separated values
            $data_array = explode(",", $value);

            // Get the ID and timestamp
            $id = $data_array[0];
            $timestamp = $data_array[2];
            $timestamp = trim($timestamp, "'\"");

            // Update the comments
            $stmt = $db->prepare("UPDATE `comments` SET date=:timestamp WHERE id=:id;");
            $stmt->bindParam(":timestamp", $timestamp, PDO::PARAM_STR);
            $stmt->bindParam(":id", $id, PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    // Get the framework_control_test_comments insert command from the file
    $search_pattern = "/INSERT INTO `framework_control_test_comments`(.*)\);/";
    preg_match($search_pattern, $contents, $matches);

    // If we have a match on the pattern
    if ($matches)
    {
        $insert_command = $matches[0];

        // Remove the insert part
        $search_pattern = "/\((.*)\)/";
        preg_match($search_pattern, $insert_command, $matches);
        $inserted_values = $matches[0];

        // Break apart the comma-separated values
        $values_array = explode("),(", $inserted_values);

        // For each of the comments
        foreach($values_array as $value)
        {
            // Remove any parentheses
            $value = trim($value, "(");
            $value = trim($value, ")");

            // Break apart the comma-separated values
            $data_array = explode(",", $value);

            // Get the ID and timestamp
            $id = $data_array[0];
            $timestamp = $data_array[2];
            $timestamp = trim($timestamp, "'\"");

            // Update the comments
            $stmt = $db->prepare("UPDATE `framework_control_test_comments` SET date=:timestamp WHERE id=:id;");
            $stmt->bindParam(":timestamp", $timestamp, PDO::PARAM_STR);
            $stmt->bindParam(":id", $id, PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    // Close the database connection
    db_close($db);
}

/**************************************************
 * FUNCTION: UPGRADE ENCRYPTION EXTRA 20190127001 *
 **************************************************/
function upgrade_encryption_extra_20190127001()
{
    if(import_export_extra())
    {
        // Get the encryption key
        $key = fetch_key();

        // Create encrypted import_export_integration_assets table
        create_encrypted_import_export_integration_assets($key);
    }
}

/**************************************************
 * FUNCTION: UPGRADE ENCRYPTION EXTRA 20190718001 *
 **************************************************/
function upgrade_encryption_extra_20190718001()
{
    // Open the database connection
    $db = db_open();

    // If the order_by_name field doesn't exist, add the field to the assets table
    if(!field_exists_in_table('order_by_name', 'assets')){
        $stmt = $db->prepare("ALTER TABLE `assets` ADD `order_by_name` INT NULL DEFAULT NULL;");
        $stmt->execute();

        create_asset_name_order($_SESSION['encrypted_pass']);
    }

    // Close the database connection
    db_close($db);
}
?>

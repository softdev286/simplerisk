<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to store *
 * text data fields as encrypted values and retrieve them as clear  *
 * text from the database.                                          *
 ********************************************************************/

// Extra Version
define('ENCRYPTION_EXTRA_VERSION', '20191130-001');

// Define encryption options
define('CIPHER', 'MCRYPT_RIJNDAEL_256');
define('MODE', 'MCRYPT_MODE_CBC');
define('OPENSSL_CIPHER', 'aes-256-cbc');
define('OPENSSL_HASH', 'sha256');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));
require_once(realpath(__DIR__ . '/upgrade.php'));

// If the encryption extra is enabled
if (encryption_extra())
{
    // Upgrade extra database version
    upgrade_encryption_extra_database();
}

/*************************************
 * FUNCTION: ENABLE ENCRYPTION EXTRA *
 *************************************/
function enable_encryption_extra()
{
    global $lang, $escaper;

    prevent_extra_double_submit("encryption", true);    

    // Check if the openssl extension is loaded
    if (!installed_openssl())
    {
        set_alert(true, "bad", $lang['OpensslWarning']);

        // Return an error
        return 0;
    }

    // Get and check mysqldump service is available.
    if(!is_process('mysqldump'))
    {       
        $mysqldump_path = get_setting('mysqldump_path');
    }
    else
    {
        $mysqldump_path = "mysqldump";
    }

    $fileName = "simplerisk-unencrypted_backup-" . date("Y-m-d--H-i-s") . ".sql";

    // Sanitize the mysqldump command
    $cmd = $mysqldump_path." --opt --lock-tables=false --skip-add-locks -h " . escapeshellarg(DB_HOSTNAME) . " -u " . escapeshellarg(DB_USERNAME) . " -p" . escapeshellarg(DB_PASSWORD) . " " . escapeshellarg(DB_DATABASE) . " > " . sys_get_temp_dir() . "/" . $fileName;

    // Backup the unencrypted database to a temporary location
    $mysqldump = system($cmd);

    // Set the initial version so that no updates run on activation
    update_setting('encryption_extra_version', 3);

    //Storing the export file's name
    update_setting('unencrypted_backup_file_name', $fileName);

    // Set the encryption method in the session
    $_SESSION['encryption_method'] = "openssl";

    // Create the encryption initialization file
    $success = create_init_file();

    // If we were able to create the encryption initialization file
    if ($success)
    {
        // Open the database connection
        $db = db_open();

        // Set the encryption extra as activated
        $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'encryption', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
        $stmt->execute();

        // Set the global variable
        $GLOBALS['encryption_extra'] = true;

        // Set the encryption extra as activated
        $stmt = $db->prepare("DELETE FROM `settings` WHERE `name` = 'ENCRYPTION_LEVEL' ");
        $stmt->execute();

        // Set the encryption method to openssl
        $stmt = $db->prepare("INSERT INTO `settings` (`name`,`value`) VALUES ('encryption_method', 'openssl') ON DUPLICATE KEY UPDATE value='openssl';");
        $stmt->execute();

        // Create the table to track encrypted values
        $stmt = $db->prepare("CREATE TABLE IF NOT EXISTS `encrypted_fields` (`mcrypt_name` VARCHAR(100),`table` VARCHAR(100), `field` VARCHAR(100), `encrypted` boolean, `method` VARCHAR(20) DEFAULT 'openssl', UNIQUE(`mcrypt_name`, `table`, `field`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $stmt->execute();

        // If the order_by_name field doesn't exist, add the field to the assets table
        if(!field_exists_in_table('order_by_name', 'assets')){
            $stmt = $db->prepare("ALTER TABLE `assets` ADD `order_by_name` INT NULL DEFAULT NULL;");
            $stmt->execute();
        }

        // List of all encrypted database fields
        $encrypted_fields = array();

        // If the assessment_contacts table exists
        if (table_exists("assessment_contacts"))
        {
            // Include the encrypted fields for the assessment extra
            $encrypted_fields['enc_assessment_contact_company'] = array("table" => "assessment_contacts", "field" => "company");
            $encrypted_fields['enc_assessment_contact_details'] = array("table" => "assessment_contacts", "field" => "details");
            $encrypted_fields['enc_assessment_contact_email'] = array("table" => "assessment_contacts", "field" => "email");
            $encrypted_fields['enc_assessment_contact_name'] = array("table" => "assessment_contacts", "field" => "name");
            $encrypted_fields['enc_assessment_contact_phone'] = array("table" => "assessment_contacts", "field" => "phone");
        }

        // If the questionnaire_result_comments table exists
        if (table_exists("questionnaire_result_comments"))
        {
            $encrypted_fields['enc_questionnaire_result_comments_comment'] = array("table" => "questionnaire_result_comments", "field" => "comment");
        }

        // If the questionnaire_responses table exists
        if (table_exists("questionnaire_responses"))
        {
            $encrypted_fields['enc_questionnaire_responses_additional_infromation'] = array("table" => "questionnaire_responses", "field" => "additional_information");
            $encrypted_fields['enc_questionnaire_responses_answer'] = array("table" => "questionnaire_responses", "field" => "answer");
        }

        // If the import_export_integration_assets table exists
        if (table_exists("import_export_integration_assets"))
        {
            // Include the encrypted fields for the import export extra
            $encrypted_fields['enc_import_export_integration_assets_ipv4'] = array("table" => "import_export_integration_assets", "field" => "ipv4");
            $encrypted_fields['enc_import_export_integration_assets_fqdn'] = array("table" => "import_export_integration_assets", "field" => "fqdn");
            $encrypted_fields['enc_import_export_integration_assets_operating_system'] = array("table" => "import_export_integration_assets", "field" => "operating_system");
            $encrypted_fields['enc_import_export_integration_assets_netbios_name'] = array("table" => "import_export_integration_assets", "field" => "netbios_name");
            $encrypted_fields['enc_import_export_integration_assets_agent_name'] = array("table" => "import_export_integration_assets", "field" => "agent_name");
            $encrypted_fields['enc_import_export_integration_assets_aws_ec2_name'] = array("table" => "import_export_integration_assets", "field" => "aws_ec2_name");
            $encrypted_fields['enc_import_export_integration_assets_mac_address'] = array("table" => "import_export_integration_assets", "field" => "mac_address");
        }
        
        // If custom_risk_data table exists, encrypt risk data
        if(table_exists('custom_risk_data'))
        {
            $encrypted_fields['enc_custom_risk_data_value'] = array("table" => "custom_risk_data", "field" => "value");
        }

        // If custom_asset_data table exists, encrypt asset data
        if(table_exists('custom_asset_data'))
        {
            $encrypted_fields['enc_custom_asset_data_value'] = array("table" => "custom_asset_data", "field" => "value");
        }

        $encrypted_fields['enc_assets_ip'] = array("table" => "assets", "field" => "ip");
        $encrypted_fields['enc_assets_name'] = array("table" => "assets", "field" => "name");
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
        $encrypted_fields['enc_framework_control_test_comments_comment'] = array("table" => "framework_control_test_comments", "field" => "comment");

        // For each of the encrypted database values
        foreach($encrypted_fields as $name => $array)
        {
            // Get the table and field
            $table = $array['table'];
            $field = $array['field'];

            // Add it to the table
            $stmt = $db->prepare("INSERT IGNORE INTO `encrypted_fields` (`mcrypt_name`, `table`, `field`, `encrypted`) VALUES (:mcrypt_name, :table, :field, 0);");
            $stmt->bindParam(":mcrypt_name", $name, PDO::PARAM_STR);
            $stmt->bindParam(":table", $table, PDO::PARAM_STR);
            $stmt->bindParam(":field", $field, PDO::PARAM_STR);
            $stmt->execute();
        }

        // Fetch the 256-bit key to encrypt data with
        $password = fetch_key();       

        // Create a new encrypted comments table
        create_encrypted_comments($password);

        // Create a new encrypted management reviews table
        create_encrypted_mgmt_reviews($password);

        // Create a new encrypted mitigations table
        create_encrypted_mitigations($password);

        // Create a new encrypted projects table
        create_encrypted_projects($password);

        // Create a new encrypted risks table
        create_encrypted_risks($password);

        // Create a new encrypted audit log table
        create_encrypted_audit($password);

        // Set order_by_subject field
        create_subject_order($password);
        
        // Create a new encrypted framework table
        create_encrypted_framework($password);

        // Create a new encrypted asset table
        create_encrypted_asset($password);

        // Create a new encrypted questionnaire_result_comments table
        create_encrypted_questionnaire_result_comments($password);

        // Create a new encrypted questionnaire_responses table
        create_encrypted_questionnaire_responses($password);

        // Create a new encrypted assessment contact table
        create_encrypted_assessment_contacts($password);

        // Create a new encrypted framework control test comments table
        create_encrypted_framework_control_test_comments($password);

        // Create a new encrypted import_export_integration_assets table
        create_encrypted_import_export_integration_assets($password);

        // Set order_by_name field
        create_asset_name_order($password);
        
        // Encrypt custom risk data
        create_encrypted_custom_risk_data($password);

        // Encrypt custom asset data
        create_encrypted_custom_asset_data($password);

        // Close the database connection
        db_close($db);

        // Set the encrypted pass in the session
        $_SESSION['encrypted_pass'] = $password;
        
        // Set the encrytion method in the session
        $_SESSION['encryption_method'] = get_setting('encryption_method');
        // Open database connection
        db_open($db);
        
        // Audit log entry for Extra turned on
        $message = "Encryption Extra was toggled on by username \"" . $_SESSION['user'] . "\".";
        write_log(1000, $_SESSION['uid'], $message, 'extra');
        
        //Closing DB connection
        db_close($db);
        
        // Display an alert
        set_alert(true, "good", "Your SimpleRisk database has been encrypted successfully.  If you have SimpleRisk running on a cluster, don't forget to copy the init.php file to all nodes in the cluster or you will see garbage text when accessing through the servers that do not have it.");
    }
    // Otherwise, we weren't able to create the init file
    else
    {
        set_alert(true, "bad", "Unable to create the encrypted database file.  Check your file permissions and try again.");

        // Close the database connection
        db_close($db);
    }
}
/************************************
 * FUNCTION: SET ORDER BY SUBJECT *
 ************************************/
function create_subject_order($password){

    // Open the database connection
    $db = db_open();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, subject FROM risks;");
    $stmt->execute();
    $risks = $stmt->fetchAll();
    
    // Decrypt subject
    foreach($risks as &$risk){
        $risk['subject'] = trim(decrypt($password, $risk['subject']));
    }
    unset($risk);
    
    // Re-order by decrypted subject
    usort($risks, function($a, $b)
        {
            return strcasecmp($a['subject'], $b['subject']);
        }
    );
    
    // Check if order_by_subject column exists
    $stmt = $db->prepare("
        SHOW COLUMNS FROM `risks` LIKE 'order_by_subject';
    ");
    $stmt->execute();
    $order_by_subject_column = $stmt->fetchObject();
    
    // If the order_by_subject field doesn't exist, add the field to risks table
    if(!$order_by_subject_column){
        $stmt = $db->prepare("ALTER TABLE `risks` ADD `order_by_subject` INT NULL DEFAULT NULL;");
        $stmt->execute();
    }
    
    // Update order_by_subject field of all records in risks table
    foreach($risks as $key => $risk){
        $int = $key+1;
        $stmt = $db->prepare("UPDATE `risks` SET `order_by_subject` = :order_by_subject WHERE `id` = :risk_id; ");
        $stmt->bindParam(":risk_id", $risk['id'], PDO::PARAM_INT, 11);
        $stmt->bindParam(":order_by_subject", $int, PDO::PARAM_INT, 11);
        $stmt->execute();
    }
    
    // Clear buffer sql    
    $stmt = $db->prepare("SELECT 'clear'; ");
    $stmt->execute();
    $stmt->fetchAll();
    // Close the database connection
    db_close($db);
}


/*************************************
 * FUNCTION: SET ORDER BY ASSET NAME *
 *************************************/
function create_asset_name_order($password) {

    // Open the database connection
    $db = db_open();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, name FROM assets;");
    $stmt->execute();
    $assets = $stmt->fetchAll();
    
    // Decrypt names
    foreach($assets as &$asset){
        $asset['name'] = trim(decrypt($password, $asset['name']));
    }
    unset($asset);
    
    // Re-order by decrypted name
    usort($assets, function($a, $b)
        {
            return strcasecmp($a['name'], $b['name']);
        }
    );

    // Update order_by_name field of all records in risks table
    foreach($assets as $key => $asset){
        $order = $key+1;
        $stmt = $db->prepare("UPDATE `assets` SET `order_by_name` = :order_by_name WHERE `id` = :asset_id; ");
        $stmt->bindParam(":asset_id", $asset['id'], PDO::PARAM_INT, 11);
        $stmt->bindParam(":order_by_name", $order, PDO::PARAM_INT, 11);
        $stmt->execute();
    }
    
    // Clear buffer sql    
    $stmt = $db->prepare("SELECT 'clear'; ");
    $stmt->execute();
    $stmt->fetchAll();
    // Close the database connection
    db_close($db);
}


/************************************
 * FUNCTION: ENABLE FILE ENCRYPTION *
 ************************************/
/*
function enable_file_encryption()
{
    global $lang, $escaper;
    // Check if the mcrypt extension is loaded
    if (!installed_mcrypt())
    {
        set_alert(true, "bad", $lang['mCryptWarning']);

        // Return an error
        return 0;
    }

    // If the initialization file exists
    if (is_file(__DIR__ . "/includes/init.php"))
    {
        // Open the database connection
        $db = db_open();

        // Get the encrypted password from the session
        $password = $_SESSION['encrypted_pass'];

        // Get the current users
        $stmt = $db->prepare("SELECT value, username, salt FROM user");
        $stmt->execute();
        $users = $stmt->fetchAll();

        // For each user
        foreach ($users as $user)
        {
            // Get the current values
            $value = $user['value'];
            $salt = $user['salt'];

            // Encrypt the master password with the temporary password plus salt
            $tmp_pass = fetch_tmp_pass() . ":" . $salt;
            $encrypted_pass = encrypt($tmp_pass, $password);

            // Update the user encryption table
            $stmt = $db->prepare("UPDATE `user_enc` SET `encrypted_pass` = :encrypted_pass, `activated` = 1 WHERE `value` = :value");
            $stmt->bindParam(":value", $value, PDO::PARAM_INT, 11);
            $stmt->bindParam(":encrypted_pass", $encrypted_pass, PDO::PARAM_LOB);
            $stmt->execute();
        }
        
        // Close the database connection
        db_close($db);

        // Display an alert
        set_alert(true, "good", "Your encryption level has been changed to use a file system encryption key.");
    }
}
*/

/**************************************
 * FUNCTION: DEACTIVATE ALL ENC USERS *
 **************************************/
function deactivate_all_enc_users()
{
    // Open the database connection
    $db = db_open();

    // Update the user encryption table
    $stmt = $db->prepare("UPDATE `user_enc` SET `activated` = 0");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**************************************
 * FUNCTION: DISABLE ENCRYPTION EXTRA *
 **************************************/
function disable_encryption_extra()
{
    global $lang, $escaper;

    prevent_extra_double_submit("encryption", false);

    // If the encryption method is mcrypt
    if ($_SESSION['encryption_method'] == "mcrypt")
    {
      // Check if the mcrypt extension is loaded
      if (!installed_mcrypt())
      {
        set_alert(true, "bad", $lang['mCryptWarning']);

        // Return an error
        return 0;
      }
    }

    // Get the encrypted password from the session
    $password = $_SESSION['encrypted_pass'];

    // Remove the encrypted comments table
    remove_encrypted_comments($password);

    // Remove the encrypted management reviews table
    remove_encrypted_mgmt_reviews($password);

    // Remove the encrypted mitigations table
    remove_encrypted_mitigations($password);

    // Remove the encrypted projects table
    remove_encrypted_projects($password);

    // Remove the encrypted risks table
    remove_encrypted_risks($password);

    // Remove the order_by_subject field from risks table
    remove_subject_order();

    // Remove the encrypted audit log table
    remove_encrypted_audit($password);

    // Remove the encrypted frameworks table
    remove_encrypted_frameworks($password);

    // Remove the encrypted assets table
    remove_encrypted_assets($password);

    // Remove the encrypted questionnaire_responses table
    remove_encrypted_questionnaire_responses($password);

    // Remove the encrypted questionnaire_result_comments table
    remove_encrypted_questionnaire_result_comments($password);

    // Remove the encrypted assessment_contacts table
    remove_encrypted_assessment_contacts($password);

    // Remove the encrypted framework_control_test_comments table
    remove_encrypted_framework_control_test_comments($password);

    // Remove the encrypted custom_risk_data table
    remove_encrypted_custom_risk_data($password);

    // Remove the encrypted custom_asset_data table
    remove_encrypted_custom_asset_data($password);

    // Delete the encryption_extra_version from settings
    delete_setting("encryption_extra_version");

    // Delete the encryption iv from settings
    delete_setting("encryption_iv");

    // Delete the encryption level from settings
    delete_setting("ENCRYPTION_LEVEL");

    // Delete the encryption method from settings
    delete_setting("encryption_method");

    // Delete backup file's name
    delete_setting('unencrypted_backup_file_name');
    
    // Open the database connection
    $db = db_open();

    // Delete the user encryption table
    $stmt = $db->prepare("DROP TABLE IF EXISTS `user_enc`;");
    $stmt->execute();

    // Delete the contacts encryption table
    $stmt = $db->prepare("DROP TABLE IF EXISTS `assessment_contacts_enc`;");
    $stmt->execute();

    // Set the enryption extra as deactivated
    $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'encryption'");
    $stmt->execute();

    // Set the global variable
    unset($GLOBALS['encryption_extra']);
    
    // Audit log entry for Extra turned off
    $message = "Encryption Extra was toggled off by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);

    // Check if the init.php file exists
    if (is_file(__DIR__ . "/includes/init.php"))
    {
        // Delete the init.php file or return an error
        if (!unlink(__DIR__ . "/includes/init.php"))
        {
            set_alert(true, "bad", "Unable to delete the encryption initialization file located at " . __DIR__ . "/includes/init.php");
        }
    }

    // Check if the assessments init.php file exists
    if (is_file(__DIR__ . "/../assessments/includes/init.php"))
    {
        // Delete the init.php file or return an error
        if (!unlink(__DIR__ . "/../assessments/includes/init.php"))
        {
            set_alert(true, "bad", "Unable to delete the encryption initialization file located at " . __DIR__ . "/../assessments/includes/init.php");
        }
    }

    // Display an alert
    set_alert(true, "good", "Your SimpleRisk database has been decrypted successfully.");
}

/************************************
 * REMOVE SUBJECT ORDER FROM RISKS*
 ***********************************/
function remove_subject_order(){
    // Open the database connection
    $db = db_open();

    // Check if order_by_subject column exists
    $stmt = $db->prepare("
        SHOW COLUMNS FROM `risks` LIKE 'order_by_subject';
    ");
    $stmt->execute();
    $order_by_subject_column = $stmt->fetchObject();
    
    // If the order_by_subject field doesn't exist, add the field to risks table
    if($order_by_subject_column){
        $stmt = $db->prepare("ALTER TABLE `risks` DROP `order_by_subject`");
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
    
    return;
}

/*********************
 * FUNCTION: AES KEY *
 *********************/
function aes_key($key)
{
    $new_key = str_repeat(chr(0), 16);

    for ($i=0,$len=strlen($key);$i<$len;$i++)
    {
        $new_key[$i%16] = $new_key[$i%16] ^ $key[$i];
    }

    return $new_key;
}

/***********************
 * FUNCTION: CREATE IV *
 ***********************/
function create_iv()
{
    // Get the IV size
    $size = mcrypt_get_iv_size(CIPHER, MODE);

    // Create the initialization vector
    $iv = generate_token($size);

    // Store the initialization vector
    store_iv($iv);
}

/******************************
 * FUNCTION: CREATE INIT FILE *
 ******************************/
function create_init_file($key = null)
{
    // If the key is null
    if ($key == null)
    {
        write_debug_log("No key was provided or the provided key was null.");

        // Generate a random 256-bit key
        $key = openssl_random_pseudo_bytes(32);
    write_debug_log("Created a random 256-bit key: " . $key);

        // Base64 encode the key
        $encoded_key = base64_encode($key);
    write_debug_log("Base64 encoded key: " . $encoded_key);
    }

    // If the includes directory does not exist
    if (!is_dir(__DIR__ . "/includes"))
    {
        write_debug_log("The includes directory does not exist so we will create it.");

        // Create the includes directory
        $success = mkdir(__DIR__ . "/includes");
    }
    // Otherwise
    else
    {
        $success = true;
    }

    // If we were able to make the directory or it already exists
    if ($success)
    {
        // Check if the init.php file exists
        if (is_file(__DIR__ . "/includes/init.php"))
        {
            write_debug_log("Deleting the existing init.php file.");
            unlink(__DIR__ . "/includes/init.php");
        }

        // Write the key to the init file
        $f = fopen(__DIR__ . "/includes/init.php", "w");
        fwrite($f, "<?php\n\n");
        fwrite($f, "define('ENCODED_KEY', '" . $encoded_key . "'); \n\n");
        fwrite($f, "?>");
        fclose($f);
    write_debug_log("Wrote the encoded key to the init.php file: " . $encoded_key);

        // Return true
        return true;
    }
    // Otherwise
    else
    {
        set_alert(true, "bad", "There was a problem creating the includes directory at " . __DIR__ . "/includes/init.php");

        // Return an error
        return 0;
    }

}

/*****************************************
 * FUNCTION: CREATE ASSESSMENT INIT FILE *
 *****************************************/
function create_assessment_init_file($password = null)
{
    // If the password is null
    if ($password == null)
    {
        // Generate a 50 character password
        //$password = generate_token(50);
        // Generate a random 256-bit key
        $password = openssl_random_pseudo_bytes(32);

        // Base64 encode the password
        $encoded_password = base64_encode($password);
    }

    // Creates assessment encryption
    if(assessments_extra())
    {
        // Check if the includes directory exists
        if (!is_dir(__DIR__ . "/../assessments/includes"))
        {
            // Create the includes directory
            $success = mkdir(__DIR__ . "/../assessments/includes");
        }
        // Otherwise
        else
        {
            $success = true;
        }

        // If we were able to make the directory or it already exists
        if ($success)
        {
            // Check if the init.php file exists
            if (is_file(__DIR__ . "/../assessments/includes/init.php"))
            {
                unlink(__DIR__ . "/../assessments/includes/init.php");
            }

            // Write the iv and password to the init file
            $f = fopen(__DIR__ . "/../assessments/includes/init.php", "w");
            fwrite($f, "<?php\n\n");
            fwrite($f, "define('ENCODED_TMP_ASSESSMENT_PASS', '" . $encoded_password . "'); \n\n");
            fwrite($f, "?>");
            fclose($f);

            // Return true
            return $password;
        }
        // Otherwise
        else
        {
            echo "There was a problem creating the includes directory.<br />\n";

            // Return an error
            return 0;
        }
    }

}

/**********************
 * FUNCTION: STORE IV *
 **********************/
function store_iv($iv)
{
    // Open the database connection
    $db = db_open();

    // Write the iv into the settings table
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` (name, value) VALUES ('encryption_iv', :iv);");
    $stmt->bindParam(":iv", $iv);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**********************
 * FUNCTION: FETCH IV *
 **********************/
function fetch_iv()
{
    // Open the database connection
    $db = db_open();

    // Get the iv from the settings table
    $stmt = $db->prepare("SELECT value FROM `settings` WHERE name='encryption_iv';");
    $stmt->execute();
    $iv = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // Return the IV
    return $iv['value'];
}

/****************************
 * FUNCTION: FETCH TMP PASS *
 ****************************/
function fetch_tmp_pass()
{
    // Load the init file
    require_once(realpath(__DIR__ . '/includes/init.php'));

    // If the TMP_PASS is defined
    if (defined('TMP_PASS'))
    {
        // Return the temporary password
        return TMP_PASS;
    }
    // If the ENCODED_TMP_PASS is defined
    else if (defined('ENCODED_TMP_PASS'))
    {
        // Return the base64 decoded temporary password
        return base64_decode(ENCODED_TMP_PASS);
    }
    // Otherwise return false
    else return false;
}

/***********************
 * FUNCTION: FETCH KEY *
 ***********************/
function fetch_key()
{
    // If the init.php file is already loaded
    if (in_array(realpath(__DIR__ . '/includes/init.php'), get_included_files()))
    {
                // We can't reload the init file since it is already loaded so we need to get the ENCODED_KEY another way
                $path = realpath(__DIR__ . '/includes/init.php');
                $file = file_get_contents($path);

                // If the define string is in the init.php file
                if (preg_match('/define(.*);/', $file, $define))
                {       
                        // Split the string into pieces
                        $array = explode('\'', $define[0]);

                        // The fourth item in the array is the encoded key
            $encoded_key = $array[3];
                        
            // Return the base64 decoded key
            return base64_decode($encoded_key);
                }

    }
    // Otherwise load the init file
    else require_once(realpath(__DIR__ . '/includes/init.php'));

    write_debug_log("Path to init.php file: " . realpath(__DIR__ . '/includes/init.php'));

    // If the ENCODED_KEY is defined
    if (defined('ENCODED_KEY'))
    {
        write_debug_log("Encoded key is defined.");

        // Return the base64 decoded key
        return base64_decode(ENCODED_KEY);
    }
    // Otherwise return false
    else return false;
}

/*********************
 * FUNCTION: ENCRYPT *
 *********************/
function encrypt($password, $cleartext) {

    // Try to get it from the session if there IS one.
    // If there's not, then get the value directly from the settings
    $encryption_method =
        isset($_SESSION['encryption_method']) ?
        $_SESSION['encryption_method'] :
        get_setting('encryption_method', false);

    // As the last resort we can check the DB
    if (!$encryption_method) {

        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("select 1 from `encrypted_fields` where `method` = 'mcrypt' LIMIT 1;");
        $stmt->execute();
        $mcrypt = $stmt->fetchColumn();
        db_close($db);

        if ($mcrypt) {
            $encryption_method = "mcrypt";
        } else {
            $encryption_method = "openssl";
        }
    }

    // If the encryption method is mcrypt
    if ($encryption_method == "mcrypt")
    {
        // Encrypt with mcrypt
        return encrypt_with_mcrypt($password, $cleartext);
    }
    // If the encryption method is openssl
    elseif ($encryption_method == "openssl")
    {
        // Encrypt with openssl
        return encrypt_with_openssl($password, $cleartext);
    }
}

/*********************************
 * FUNCTION: ENCRYPT WITH MCRYPT *
 *********************************/
function encrypt_with_mcrypt($password, $cleartext)
{
    // Get the initialization vector
    $iv = fetch_iv();

    // Make the key a multiple of 16 bytes
    $key = aes_key($password);

    // Encrypt the text
    $encryptedtext = @mcrypt_encrypt(CIPHER, $key, $cleartext, MODE, $iv);

    // Return the Base64 encoded encrypted data
    return base64_encode($encryptedtext);
}

/**********************************
 * FUNCTION: ENCRYPT WITH OPENSSL *
 **********************************/
function encrypt_with_openssl($key, $data)
{
    // Generate an initialization vector
    $iv = generate_iv();

        // Encrypt the data using openssl encrypt
        $ciphertext_raw = openssl_encrypt($data, OPENSSL_CIPHER, $key, 0, $iv);

        // Calculate the SHA256 hash for the raw ciphertext
        $hmac = hash_hmac(OPENSSL_HASH, $ciphertext_raw, $key, true);

    // Base64 encode the IV, hash, and raw ciphertext together
    $ciphertext = base64_encode( $iv.$hmac.$ciphertext_raw );

    // Return the base64 encoded cipher text
    return $ciphertext;
}

/*********************
 * FUNCTION: DECRYPT *
 *********************/
function decrypt($password, $encryptedtext) {

    // Try to get it from the session if there IS one.
    // If there's not, then get the value directly from the settings
    $encryption_method =
        isset($_SESSION['encryption_method']) ?
        $_SESSION['encryption_method'] :
        get_setting('encryption_method', false);

    // As the last resort we can check the DB
    if (!$encryption_method) {

        // Open the database connection
        $db = db_open();

        $stmt = $db->prepare("select 1 from `encrypted_fields` where `method` = 'mcrypt' LIMIT 1;");
        $stmt->execute();
        $mcrypt = $stmt->fetchColumn();
        db_close($db);

        if ($mcrypt) {
            $encryption_method = "mcrypt";
        } else {
            $encryption_method = "openssl";
        }
    }

    // If the encryption method is mcrypt
    if ($encryption_method == "mcrypt")
    {
        // Decrypt with mcrypt
        return decrypt_with_mcrypt($password, $encryptedtext);
    }
    // If the encryption method is openssl
    elseif ($encryption_method == "openssl")
    {
        // Decrypt with openssl
        return decrypt_with_openssl($password, $encryptedtext);
    }
}

/*********************************
 * FUNCTION: DECRYPT WITH MCRYPT *
 *********************************/
function decrypt_with_mcrypt($password, $encryptedtext)
{
    // If the encryptedtext is not an array
    if (!is_array($encryptedtext))
    {
        // If the encryptedtext is not null or N/A
        if ($encryptedtext != "" && $encryptedtext != "N/A")
        {
            // Base64 decode the encrypted data
            $encryptedtext = base64_decode($encryptedtext);

            // Get the initialization vector
            $iv = fetch_iv();

            // Make the key a multiple of 16 bytes
            $key = aes_key($password);

            // Decrypt the text
            $cleartext = @mcrypt_decrypt(CIPHER, $key, $encryptedtext, MODE, $iv);

            // Trim null characters
            $cleartext = rtrim($cleartext, "\0");

            // Return the cleartext
            return $cleartext;
        }
        // Otherwise return the value
        else return $encryptedtext;
    }
    // If the encryptedtext is an array
    else if (is_array($encryptedtext))
    {
        // For each entry in the encryptedtext array
        foreach ($encryptedtext as $key => $entry)
        {
            // Decrypt the value
            $encryptedtext[$key] = decrypt($password, $entry);
        }

        // Return the decrypted array
        return $encryptedtext;
    }
    // Otherwise, it's not an encrypted value so just return it
    else return $encryptedtext;
}

/**********************************
 * FUNCTION: DECRYPT WITH OPENSSL *
 **********************************/
function decrypt_with_openssl($password, $encryptedtext)
{
    write_debug_log("Decrypting with OpenSSL: " . $encryptedtext);

    // If the encryptedtext is not an array
    if (!is_array($encryptedtext))
    {   
        write_debug_log("Decrypting a string");

            // If the encryptedtext is not null or N/A 
            if ($encryptedtext != "" && $encryptedtext != "N/A")
            {   
            write_debug_log("Encrypted text is not null or N/A");

            // Base64 decode the encrypted text
            $c = base64_decode($encryptedtext);
            write_debug_log("Base64 decoded text: " . $c);

            // Get the length of the IV
            $ivlen = openssl_cipher_iv_length(OPENSSL_CIPHER);

            // Get the IV part of the string
            $iv = substr($c, 0, $ivlen);

            write_debug_log("IV: " . $iv);
            write_debug_log("IV Length: " . strlen($iv));

            // Calculate the hmac length
            $hmaclen = strlen(hash_hmac(OPENSSL_HASH, "", "", true));

            write_debug_log("HMAC Length: " . $hmaclen);

            // Get the hash part of the string
            $hmac = (string)substr($c, $ivlen, $hmaclen);

            write_debug_log("HMAC: " . $hmac);

            // Get the ciphertext part of the string
            $ciphertext_raw = substr($c, $ivlen+$hmaclen);

            write_debug_log("Raw Ciphertext: " . $ciphertext_raw);

            // Decrypt the ciphertext to plaintext
            $plaintext = openssl_decrypt($ciphertext_raw, OPENSSL_CIPHER, $password, 0, $iv);

            write_debug_log("Plaintext: " . $plaintext);

            // Calculate the SHA256 hash for the ciphertext
            $calcmac = (string)hash_hmac(OPENSSL_HASH, $ciphertext_raw, $password, true);

            // Compare the stored hash to the calcualted hash
            if (hash_equals($hmac, $calcmac))
            {
                // Return the plain text value
                return $plaintext;
            }
            else return "Unable to validate hash. Data may have been corrupted or tampered with.";
            }
            // Otherwise return the value
            else return $encryptedtext;
    }
    // If the encryptedtext is an array
    else if (is_array($encryptedtext))
    {
        // For each entry in the encryptedtext array
        foreach ($encryptedtext as $key => $entry)
        {
            // Decrypt the value
            $encryptedtext[$key] = decrypt($password, $entry);
        }

        // Return the decrypted array
        return $encryptedtext;
    }
    // Otherwise, it's not an encrypted value so just return it
    else return $encryptedtext;
}

/**************************
 * FUNCTION: GET ENC PASS *
 **************************/
function get_enc_pass($user, $password)
{
    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
        // Get the users salt and encrypted password
        $stmt = $db->prepare("SELECT a.salt, b.encrypted_pass FROM `user` a JOIN `user_enc` b ON a.value = b.value WHERE LOWER(convert(`b`.`username` using utf8)) = LOWER(:user)");
    }
    else
    {
        // Get the users salt and encrypted password
        $stmt = $db->prepare("SELECT a.salt, b.encrypted_pass FROM `user` a JOIN `user_enc` b ON a.value = b.value WHERE b.username = :user");
    }

    $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);
    $stmt->execute();
    $value = $stmt->fetchAll();

    // Decrypt the encrypted password
    $password = $password . ":" . $value[0]['salt'];
    $password = decrypt($password, $value[0]['encrypted_pass']);

    // Close the database connection
    db_close($db);

    // Return the password
    return $password;
}

/**************************
 * FUNCTION: SET ENC PASS *
 **************************/
function set_enc_pass($username, $password, $encrypted_pass = null)
{
    // Open the database connection
    $db = db_open();

    // Get the users salt
    $salt = get_salt_by_username($username);

    // Get the encryption level
    $encryption_level = get_setting("ENCRYPTION_LEVEL", "file");

    // If the encryption level is file
    if ($encryption_level == "file")
    {
        // Get the temporary password
        $tmp_pass = fetch_tmp_pass();

        // Set the password to the temporary password plus salt
        $password = $tmp_pass . ":" . $salt;

        // Encrypt the master password
        $encrypted_pass = encrypt($password, $_SESSION['encrypted_pass']);
    }
    // If the encryption level is user and the encrypted password is not null
    if (($encryption_level == "user") && ($encrypted_pass != null))
    {
        // Encrypt the master password with the temporary password plus salt
        $password = $password . ":" . $salt;
        $encrypted_pass = encrypt($password, $encrypted_pass);
    }

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
        // Update the encrypted password in the database
        $stmt = $db->prepare("UPDATE `user_enc` SET activated = '1', encrypted_pass = :encrypted_pass WHERE LOWER(convert(`username` using utf8)) = LOWER(:username)");
    }
    else
    {
         // Update the encrypted password in the database
         $stmt = $db->prepare("UPDATE `user_enc` SET activated = '1', encrypted_pass = :encrypted_pass WHERE username = :username");
    }

    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->bindParam(":encrypted_pass", $encrypted_pass, PDO::PARAM_LOB);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**********************************
 * FUNCTION: GET SALT BY USERNAME *
 **********************************/
function get_salt_by_username($username)
{
    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
        // Get the salt
        $stmt = $db->prepare("SELECT salt FROM `user` WHERE LOWER(convert(`username` using utf8)) = LOWER(:username)");
    }
    else
    {
        // Get the salt
        $stmt = $db->prepare("SELECT salt FROM `user` WHERE username = :username");
    }

    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->execute();
    $value = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // Return the salt
    return $value[0]['salt'];
}

/****************************
 * FUNCTION: ACTIVATED USER *
 ****************************/
function activated_user($user)
{
        // Open the database connection
        $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
            // Get the users salt and encrypted password
            $stmt = $db->prepare("SELECT activated FROM user_enc WHERE LOWER(convert(`username` using utf8)) = LOWER(:user)");
    }
    else
    {
        // Get the users salt and encrypted password
        $stmt = $db->prepare("SELECT activated FROM user_enc WHERE username = :user");
    }

        $stmt->bindParam(":user", $user, PDO::PARAM_STR, 200);
        $stmt->execute();
    $value = $stmt->fetchAll();

        // Close the database connection
        db_close($db);

        // Return the password
        return $value[0]['activated'];
}

/**************************************************************
 * FUNCTION: CREATE ENCRYPTED FRAMEWORK CONTROL TEST COMMENTS *
 **************************************************************/
function create_encrypted_framework_control_test_comments($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted framework_control_test_comments table
    $stmt = $db->prepare("CREATE TABLE framework_control_test_comments_enc LIKE framework_control_test_comments; INSERT framework_control_test_comments_enc SELECT * FROM framework_control_test_comments; ");
    $stmt->execute();

    // Change the comment field to a blob to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `framework_control_test_comments_enc` CHANGE `comment` `comment` BLOB NOT NULL; ");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, date, comment FROM framework_control_test_comments; ");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
        $stmt = $db->prepare("UPDATE `framework_control_test_comments_enc` SET `date` = :date, `comment` = :comment WHERE id = :id; ");
        $encrypted_comment = encrypt($password, $comment['comment']);
    $stmt->bindParam(":date", $comment['date'], PDO::PARAM_STR);
        $stmt->bindParam(":comment", $encrypted_comment, PDO::PARAM_STR);
        $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the encrypted framework_control_test_comments table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE framework_control_test_comments; CREATE TABLE framework_control_test_comments LIKE framework_control_test_comments_enc; INSERT framework_control_test_comments SELECT * FROM framework_control_test_comments_enc; DROP TABLE framework_control_test_comments_enc; ");
    $stmt->execute();

    // Clear buffer sql    
    $stmt = $db->prepare("SELECT 'clear'; ");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_encrypted_field("enc_framework_control_test_comments_comment", "framework_control_test_comments", "comment", "openssl");

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: CREATE ENCRYPTED COMMENTS *
 ***************************************/
function create_encrypted_comments($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted comments table
    $stmt = $db->prepare("CREATE TABLE comments_enc LIKE comments; INSERT comments_enc SELECT * FROM comments; ");
    $stmt->execute();

    // Change the comment field to a blob to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `comments_enc` CHANGE `comment` `comment` BLOB NOT NULL; ");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, date, comment FROM comments; ");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
        $stmt = $db->prepare("UPDATE `comments_enc` SET `date` = :date, `comment` = :comment WHERE id = :id; ");
        $encrypted_comment = encrypt($password, $comment['comment']);
    $stmt->bindParam(":date", $comment['date'], PDO::PARAM_STR);
        $stmt->bindParam(":comment", $encrypted_comment, PDO::PARAM_STR);
        $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Move the encrypted comments table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE comments; CREATE TABLE comments LIKE comments_enc; INSERT comments SELECT * FROM comments_enc; DROP TABLE comments_enc; ");
    $stmt->execute();
    
    // Clear buffer sql    
    $stmt = $db->prepare("SELECT 'clear'; ");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_encrypted_field("enc_comments_comment", "comments", "comment", "openssl");

    // Close the database connection
    db_close($db);
}

/*****************************************
 * FUNCTION: CREATE ENCRYPTED FRAMEWORKS *
 *****************************************/
function create_encrypted_framework($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted frameworks table
    $stmt = $db->prepare("CREATE TABLE frameworks_enc LIKE frameworks; INSERT frameworks_enc SELECT * FROM frameworks");
    $stmt->execute();

    // Get all of the frameworks
    $stmt = $db->prepare("SELECT value, name, description FROM frameworks");
    $stmt->execute();
    $frameworks = $stmt->fetchAll();

    // For each framework
    foreach ($frameworks as $framework)
    {
        $encrypt_name = encrypt($password, $framework['name']);
        $encrypt_description = encrypt($password, $framework['description']);

        $stmt = $db->prepare("UPDATE `frameworks_enc` SET `name` = :name, `description` = :description  WHERE value = :value");
        $stmt->bindParam(":name", $encrypt_name, PDO::PARAM_STR);
        $stmt->bindParam(":description", $encrypt_description, PDO::PARAM_STR);
        $stmt->bindParam(":value", $framework['value'], PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Move the encrypted frameworks table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE frameworks; CREATE TABLE frameworks LIKE frameworks_enc; INSERT frameworks SELECT * FROM frameworks_enc; DROP TABLE frameworks_enc;");
    $stmt->execute();
    
    // Clear buffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_encrypted_field("enc_frameworks_name", "frameworks", "name", "openssl");
    add_encrypted_field("enc_frameworks_description", "frameworks", "description", "openssl");

    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: CREATE ENCRYPTED ASSET *
 ************************************/
function create_encrypted_asset($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted asset table with unique value on name removed
    $stmt = $db->prepare("
        CREATE TABLE `assets_enc` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `ip` BLOB,
          `name` BLOB,
          `value` int(11) DEFAULT '5',
          `location` int(11) NOT NULL,
          `team` int(11) NOT NULL,
          `details` BLOB,
          `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `verified` TINYINT NOT NULL DEFAULT 0,
          `order_by_name` INT NULL DEFAULT NULL,
           PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8
    ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT assets_enc SELECT * FROM assets");
    $stmt->execute();

    // Get all of the assets
    $stmt = $db->prepare("SELECT * FROM assets");
    $stmt->execute();
    $assets = $stmt->fetchAll();

    // For each asset
    foreach ($assets as $asset)
    {
        $encrypt_ip = encrypt($password, $asset['ip']);
        $encrypt_name = encrypt($password, $asset['name']);
        $encrypt_details = encrypt($password, $asset['details']);
        
        $stmt = $db->prepare("UPDATE `assets_enc` SET `ip` = :ip, `name` = :name, `details` = :details WHERE id = :id");
        $stmt->bindParam(":ip", $encrypt_ip, PDO::PARAM_STR);
        $stmt->bindParam(":name", $encrypt_name, PDO::PARAM_STR);
        $stmt->bindParam(":details", $encrypt_details, PDO::PARAM_STR);
        $stmt->bindParam(":id", $asset['id'], PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Move the encrypted assets table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE assets; CREATE TABLE assets LIKE assets_enc; INSERT assets SELECT * FROM assets_enc; DROP TABLE assets_enc;");
    $stmt->execute();
    
    // Clear buffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();

    // Add settings to show tables were encrypted
    add_encrypted_field("enc_assets_ip", "assets", "ip", "openssl");
    add_encrypted_field("enc_assets_name", "assets", "name", "openssl");
    add_encrypted_field("enc_assets_details", "assets", "details", "openssl");

    // Close the database connection
    db_close($db);
}

/******************************************************
 * FUNCTION: CREATE ENCRYPTED QUESTIONNAIRE RESPONSES *
 ******************************************************/
function create_encrypted_questionnaire_responses($password)
{
    // If the questionnaire_responses table exists
    if (table_exists("questionnaire_responses"))
    {
        // Open the database connection
        $db = db_open();

        // Create the new encrypted questionnaire responses table
        $stmt = $db->prepare("CREATE TABLE questionnaire_responses_enc LIKE questionnaire_responses; INSERT questionnaire_responses_enc SELECT * FROM questionnaire_responses");
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
            if($enc_additional_information_arr['encrypted'])
            {
                $encrypt_additional_information = $questionnaire_response['additional_information'];
            }
            else
            {
                $encrypt_additional_information = encrypt($password, $questionnaire_response['additional_information']);
            }
            
            if($enc_answer_arr['encrypted'])
            {
                $encrypt_answer = $questionnaire_response['answer'];
            }
            else
            {
                $encrypt_answer = encrypt($password, $questionnaire_response['answer']);
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

        // Close the database connection
        db_close($db);
    }
}

/***********************************************
 * FUNCTION: CREATE ENCRYPTED CUSTOM RISK DATA *
 ***********************************************/
function create_encrypted_custom_risk_data($password)
{
    // If the custom_risk_data table exists
    if (table_exists("custom_risk_data"))
    {
        // Open the database connection
        $db = db_open();

        // Create the new encrypted custom_risk_data table
        $stmt = $db->prepare("CREATE TABLE custom_risk_data_enc LIKE custom_risk_data; INSERT custom_risk_data_enc SELECT * FROM custom_risk_data");
        $stmt->execute();

        // Change the text fields to blobs to store encrypted text
        $stmt = $db->prepare("ALTER TABLE `custom_risk_data_enc` CHANGE `value` `value` BLOB;");
        $stmt->execute();

        // Get all of the custom_risk_data
        $stmt = $db->prepare("
            SELECT t2.*, t1.value, t1.id risk_data_id
            FROM custom_risk_data t1 
                INNER JOIN custom_fields t2 ON t1.field_id=t2.id
            WHERE t2.encryption=1; 
        ");
        $stmt->execute();
        $fields = $stmt->fetchAll();
        
        $enc_custom_risk_data_value = is_field_encrypted(null, "custom_risk_data", "value");

        // For each questionnaire response 
        foreach ($fields as $field)
        {
            if(!$enc_custom_risk_data_value['encrypted'])
            {
                $encrypt_value = encrypt_custom_value($field, $field['value'], $password);
                
                $stmt = $db->prepare("UPDATE `custom_risk_data_enc` SET `value` = :value WHERE id = :id");
                $stmt->bindParam(":value", $encrypt_value);
                $stmt->bindParam(":id", $field['risk_data_id'], PDO::PARAM_INT);
                $stmt->execute();
            }
            
        }
    
        // Move the encrypted custom_risk_data table in place of the unencrypted one
        $stmt = $db->prepare("DROP TABLE custom_risk_data; CREATE TABLE custom_risk_data LIKE custom_risk_data_enc; INSERT custom_risk_data SELECT * FROM custom_risk_data_enc; DROP TABLE custom_risk_data_enc;");
        $stmt->execute();
    
        // Clear buffer sql    
        $stmt = $db->prepare("SELECT 'clear' ");
        $stmt->execute();
        $stmt->fetchAll();

        // Add settings to show tables were encrypted
        add_encrypted_field("enc_custom_risk_data_value", "custom_risk_data", "value", "openssl");

        // Close the database connection
        db_close($db);
    }
}

/************************************************
 * FUNCTION: CREATE ENCRYPTED CUSTOM ASSET DATA *
 ************************************************/
function create_encrypted_custom_asset_data($password)
{
    // If the custom_asset_data table exists
    if (table_exists("custom_asset_data"))
    {
        // Open the database connection
        $db = db_open();

        // Create the new encrypted custom_asset_data table
        $stmt = $db->prepare("CREATE TABLE custom_asset_data_enc LIKE custom_asset_data; INSERT custom_asset_data_enc SELECT * FROM custom_asset_data");
        $stmt->execute();

        // Change the text fields to blobs to store encrypted text
        $stmt = $db->prepare("ALTER TABLE `custom_asset_data_enc` CHANGE `value` `value` BLOB;");
        $stmt->execute();

        // Get all of the custom_asset_data
        $stmt = $db->prepare("
            SELECT t2.*, t1.value, t1.id asset_data_id
            FROM custom_asset_data t1 
                INNER JOIN custom_fields t2 ON t1.field_id=t2.id
            WHERE t2.encryption=1; 
        ");
        $stmt->execute();
        $fields = $stmt->fetchAll();
        
        $enc_custom_asset_data_value = is_field_encrypted(null, "custom_asset_data", "value");

        foreach ($fields as $field)
        {
            if(!$enc_custom_asset_data_value['encrypted'])
            {
                $encrypt_value = encrypt_custom_value($field, $field['value'], $password);
                
                $stmt = $db->prepare("UPDATE `custom_asset_data_enc` SET `value` = :value WHERE id = :id");
                $stmt->bindParam(":value", $encrypt_value);
                $stmt->bindParam(":id", $field['asset_data_id'], PDO::PARAM_INT);
                $stmt->execute();
            }
            
        }
    
        // Move the encrypted custom_asset_data table in place of the unencrypted one
        $stmt = $db->prepare("DROP TABLE custom_asset_data; CREATE TABLE custom_asset_data LIKE custom_asset_data_enc; INSERT custom_asset_data SELECT * FROM custom_asset_data_enc; DROP TABLE custom_asset_data_enc;");
        $stmt->execute();
    
        // Clear buffer sql    
        $stmt = $db->prepare("SELECT 'clear' ");
        $stmt->execute();
        $stmt->fetchAll();

        // Add settings to show tables were encrypted
        add_encrypted_field("enc_custom_asset_data_value", "custom_asset_data", "value", "openssl");

        // Close the database connection
        db_close($db);
    }
}

/************************************************************
 * FUNCTION: CREATE ENCRYPTED QUESTIONNAIRE RESULT COMMENTS *
 ************************************************************/
function create_encrypted_questionnaire_result_comments($password)
{
    // If the questionnaire_result_comments table exists
    if (table_exists("questionnaire_result_comments"))
    {
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
                $encrypted_comment = encrypt($password, $questionnaire_result_comment['comment']);
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

/**********************************************
 * FUNCTION: CREATE ENCRYPTED RISKS TO ASSETS *
 **********************************************/
function create_encrypted_risks_to_assets($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted asset table with unique value on name removed
    $stmt = $db->prepare("
    CREATE TABLE `risks_to_assets_enc` (
      `risk_id` int(11) DEFAULT NULL,
      `asset_id` int(11) NOT NULL,
      `asset` BLOB
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT risks_to_assets_enc SELECT * FROM risks_to_assets");
    $stmt->execute();

    // Get all of the risks_to_assets
    $stmt = $db->prepare("SELECT * FROM risks_to_assets");
    $stmt->execute();
    $risks_to_assets = $stmt->fetchAll();

    // For each risks_to_assets
    foreach ($risks_to_assets as $row)
    {
        $encrypt_asset = encrypt($password, $row['asset']);
        $stmt = $db->prepare("UPDATE `risks_to_assets_enc` SET `asset` = :encrypt_asset WHERE risk_id = :risk_id AND asset_id = :asset_id");
        $stmt->bindParam(":encrypt_asset", $encrypt_asset, PDO::PARAM_STR);
        $stmt->bindParam(":risk_id", $row['risk_id'], PDO::PARAM_INT);
        $stmt->bindParam(":asset_id", $row['asset_id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the encrypted risks_to_assets table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE risks_to_assets; CREATE TABLE risks_to_assets LIKE risks_to_assets_enc; INSERT risks_to_assets SELECT * FROM risks_to_assets_enc; DROP TABLE risks_to_assets_enc;");
    $stmt->execute();

    // Clear buffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_encrypted_field("enc_risks_to_assets_asset", "risks_to_assets", "asset", "openssl");

    // Close the database connection
    db_close($db);
}

/**************************************************
 * FUNCTION: CREATE ENCRYPTED ASSESSMENT CONTACTS *
 **************************************************/
function create_encrypted_assessment_contacts($password)
{
    // If the assessment_contacts table exists
    if (table_exists("assessment_contacts"))
    {
        // Open the database connection
        $db = db_open();

        // Create the new encrypted assessment contacts table
        $stmt = $db->prepare("CREATE TABLE assessment_contacts_tmp LIKE assessment_contacts; INSERT assessment_contacts_tmp SELECT * FROM assessment_contacts");
        $stmt->execute();

        // Change the text fields to blobs to store encrypted text
        $stmt = $db->prepare("ALTER TABLE `assessment_contacts_tmp` CHANGE `company` `company` BLOB, CHANGE `name` `name` BLOB, CHANGE `email` `email` BLOB, CHANGE `phone` `phone` BLOB, CHANGE `details` `details` BLOB;");
        $stmt->execute();

        // Get all of the assessment contacts
        $stmt = $db->prepare("SELECT * FROM assessment_contacts");
        $stmt->execute();
        $assessment_contacts = $stmt->fetchAll();

        // For each contact
        foreach ($assessment_contacts as $assessment_contact)
        {
            $encrypt_company = encrypt($password, $assessment_contact['company']);
            $encrypt_name = encrypt($password, $assessment_contact['name']);
            $encrypt_email = encrypt($password, $assessment_contact['email']);
            $encrypt_phone = encrypt($password, $assessment_contact['phone']);
            $encrypt_details = encrypt($password, $assessment_contact['details']);

            $stmt = $db->prepare("UPDATE `assessment_contacts_tmp` SET `company` = :company, `name` = :name, `email` = :email, `phone` = :phone, `details` = :details WHERE id = :id");
            $stmt->bindParam(":company", $encrypt_company, PDO::PARAM_STR);
            $stmt->bindParam(":name", $encrypt_name, PDO::PARAM_STR);
            $stmt->bindParam(":email", $encrypt_email, PDO::PARAM_STR);
            $stmt->bindParam(":phone", $encrypt_phone, PDO::PARAM_STR);
            $stmt->bindParam(":details", $encrypt_details, PDO::PARAM_STR);
            $stmt->bindParam(":id", $assessment_contact['id'], PDO::PARAM_INT);
            $stmt->execute();
        }
    
        // Move the encrypted assessment contacts table in place of the unencrypted one
        $stmt = $db->prepare("DROP TABLE assessment_contacts; CREATE TABLE assessment_contacts LIKE assessment_contacts_tmp; INSERT assessment_contacts SELECT * FROM assessment_contacts_tmp; DROP TABLE assessment_contacts_tmp;");
        $stmt->execute();
    
        // Clear buffer sql    
        $stmt = $db->prepare("SELECT 'clear'; ");
        $stmt->execute();
        $stmt->fetchAll();

        // Add settings to show tables were encrypted
        add_encrypted_field("enc_assessment_contact_company", "assessment_contacts", "company", "openssl");
        add_encrypted_field("enc_assessment_contact_name", "assessment_contacts", "name", "openssl");
        add_encrypted_field("enc_assessment_contact_email", "assessment_contacts", "email", "openssl");
        add_encrypted_field("enc_assessment_contact_phone", "assessment_contacts", "phone", "openssl");
        add_encrypted_field("enc_assessment_contact_details", "assessment_contacts", "details", "openssl");

        // Close the database connection
        db_close($db);
    }
}

/**************************************************************
 * FUNCTION: REMOVE ENCRYPTED FRAMEWORK CONTROL TEST COMMENTS *
 **************************************************************/
function remove_encrypted_framework_control_test_comments($password)
{
    // Open the database connection
    $db = db_open();
    
    // Create the new decrypted framework_control_test_comments table
    $stmt = $db->prepare("CREATE TABLE framework_control_test_comments_dec LIKE framework_control_test_comments; INSERT framework_control_test_comments_dec SELECT * FROM framework_control_test_comments");
    $stmt->execute();
    
    // Get all of the comments
    $stmt = $db->prepare("SELECT id, comment FROM framework_control_test_comments");
    $stmt->execute();
    $comments = $stmt->fetchAll();
    
    // Change the comment field back to mediumtext
    $stmt = $db->prepare("ALTER TABLE `framework_control_test_comments_dec` CHANGE `comment` `comment` MEDIUMTEXT NOT NULL ;");
    $stmt->execute();
    
    // For each comment
    foreach ($comments as $comment)
    {   
        $decrypt_comment = decrypt($password, $comment['comment']);
        
        $stmt = $db->prepare("UPDATE `framework_control_test_comments_dec` SET `comment` = :comment WHERE id = :id");
        $stmt->bindParam(":comment", $decrypt_comment, PDO::PARAM_STR);
        $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // Move the decrypted framework_control_test_comments table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE framework_control_test_comments;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE framework_control_test_comments LIKE framework_control_test_comments_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT framework_control_test_comments SELECT * FROM framework_control_test_comments_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE framework_control_test_comments_dec;");
    $stmt->execute();

    
    // Delete the setting
    delete_setting("enc_framework_control_test_comments_comment");
    disable_encryption("enc_framework_control_test_comments_comment", null, null);
    
    // Close the database connection
    db_close($db);
}

/*****************************************************
 * FUNCTION: REMOVE ENCRYPTED CUSTOM RISK DATA TABLE *
 *****************************************************/
function remove_encrypted_custom_risk_data($password)
{
    // If the custom_risk_data table exists
    if (table_exists("custom_risk_data"))
    {
        // Open the database connection
        $db = db_open();

        // Create the new decrypted custom_risk_data table
        $stmt = $db->prepare("CREATE TABLE custom_risk_data_dec LIKE custom_risk_data; INSERT custom_risk_data_dec SELECT * FROM custom_risk_data");
        $stmt->execute();

        // Change the text fields to blobs to store decrypted text
        $stmt = $db->prepare("ALTER TABLE `custom_risk_data_dec` CHANGE `value` `value` TEXT;");
        $stmt->execute();

        // Get all of the custom_risk_data
        $stmt = $db->prepare("
            SELECT t2.*, t1.value, t1.id risk_data_id
            FROM custom_risk_data t1 
                INNER JOIN custom_fields t2 ON t1.field_id=t2.id
            WHERE t2.encryption=1; 
        ");
        $stmt->execute();
        $fields = $stmt->fetchAll();
        
        $enc_custom_risk_data_value = is_field_encrypted(null, "custom_risk_data", "value");

        // For each questionnaire response 
        foreach ($fields as $field)
        {
            if($enc_custom_risk_data_value['encrypted'])
            {
                $decrypt_value = decrypt_custom_value($field, $field['value'], $password);
                
                $stmt = $db->prepare("UPDATE `custom_risk_data_dec` SET `value` = :value WHERE id = :id");
                $stmt->bindParam(":value", $decrypt_value);
                $stmt->bindParam(":id", $field['risk_data_id'], PDO::PARAM_INT);
                $stmt->execute();
            }
            
        }
    
        // Move the encrypted questionnaire respponses table in place of the unencrypted one
        $stmt = $db->prepare("DROP TABLE custom_risk_data; CREATE TABLE custom_risk_data LIKE custom_risk_data_dec; INSERT custom_risk_data SELECT * FROM custom_risk_data_dec; DROP TABLE custom_risk_data_dec;");
        $stmt->execute();
    
        // Clear buffer sql    
        $stmt = $db->prepare("SELECT 'clear' ");
        $stmt->execute();
        $stmt->fetchAll();

        // Add settings to show tables were encrypted
        disable_encryption("enc_custom_risk_data_value", null, null);

        // Close the database connection
        db_close($db);
    }
    
}

/******************************************************
 * FUNCTION: REMOVE ENCRYPTED CUSTOM ASSET DATA TABLE *
 ******************************************************/
function remove_encrypted_custom_asset_data($password)
{
    // If the custom_asset_data table exists
    if (table_exists("custom_asset_data"))
    {
        // Open the database connection
        $db = db_open();

        // Create the new decrypted custom_asset_data table
        $stmt = $db->prepare("CREATE TABLE custom_asset_data_dec LIKE custom_asset_data; INSERT custom_asset_data_dec SELECT * FROM custom_asset_data");
        $stmt->execute();

        // Change the text fields to blobs to store decrypted text
        $stmt = $db->prepare("ALTER TABLE `custom_asset_data_dec` CHANGE `value` `value` TEXT;");
        $stmt->execute();

        // Get all of the custom_asset_data
        $stmt = $db->prepare("
            SELECT t2.*, t1.value, t1.id asset_data_id
            FROM custom_asset_data t1 
                INNER JOIN custom_fields t2 ON t1.field_id=t2.id
            WHERE t2.encryption=1; 
        ");
        $stmt->execute();
        $fields = $stmt->fetchAll();
        
        $enc_custom_asset_data_value = is_field_encrypted(null, "custom_asset_data", "value");

        // For each questionnaire response 
        foreach ($fields as $field)
        {
            if($enc_custom_asset_data_value['encrypted'])
            {
                $decrypt_value = decrypt_custom_value($field, $field['value'], $password);
                
                $stmt = $db->prepare("UPDATE `custom_asset_data_dec` SET `value` = :value WHERE id = :id");
                $stmt->bindParam(":value", $decrypt_value);
                $stmt->bindParam(":id", $field['asset_data_id'], PDO::PARAM_INT);
                $stmt->execute();
            }
        }
    
        // Move the encrypted questionnaire respponses table in place of the unencrypted one
        $stmt = $db->prepare("DROP TABLE custom_asset_data; CREATE TABLE custom_asset_data LIKE custom_asset_data_dec; INSERT custom_asset_data SELECT * FROM custom_asset_data_dec; DROP TABLE custom_asset_data_dec;");
        $stmt->execute();
    
        // Clear buffer sql    
        $stmt = $db->prepare("SELECT 'clear' ");
        $stmt->execute();
        $stmt->fetchAll();

        // Add settings to show tables were encrypted
        disable_encryption("enc_custom_asset_data_value", null, null);

        // Close the database connection
        db_close($db);
    }
    
}

/***************************************************************
 * FUNCTION: CREATE ENCRYPTED IMPORT EXPORT INTEGRATION ASSETS *
 ***************************************************************/
function create_encrypted_import_export_integration_assets($password)
{
    // If the import_export_integration_assets table exists
    if (table_exists("import_export_integration_assets"))
    {
        // Open the database connection
        $db = db_open();

        // Get all of the import export integration assets
        $stmt = $db->prepare("SELECT * FROM import_export_integration_assets");
        $stmt->execute();
        $import_export_integration_assets = $stmt->fetchAll();

        // For each import_export_integration_assets
        foreach ($import_export_integration_assets as $asset)
        {
            $encrypt_ipv4 = encrypt($password, $asset['ipv4']);
            $encrypt_fqdn = encrypt($password, $asset['fqdn']);
            $encrypt_operating_system = encrypt($password, $asset['operating_system']);
            $encrypt_netbios_name = encrypt($password, $asset['netbios_name']);
            $encrypt_agent_name = encrypt($password, $asset['agent_name']);
            $encrypt_aws_ec2_name = encrypt($password, $asset['aws_ec2_name']);
            $encrypt_mac_address = encrypt($password, $asset['mac_address']);

            $stmt = $db->prepare("UPDATE `import_export_integration_assets` SET `ipv4` = :ipv4, `fqdn` = :fqdn, `operating_system` = :operating_system, `netbios_name` = :netbios_name, `agent_name` = :agent_name, `aws_ec2_name` = :aws_ec2_name, `mac_address` = :mac_address  WHERE id = :id");
            $stmt->bindParam(":ipv4", $encrypt_ipv4, PDO::PARAM_STR);
            $stmt->bindParam(":fqdn", $encrypt_fqdn, PDO::PARAM_STR);
            $stmt->bindParam(":operating_system", $encrypt_operating_system, PDO::PARAM_STR);
            $stmt->bindParam(":netbios_name", $encrypt_netbios_name, PDO::PARAM_STR);
            $stmt->bindParam(":agent_name", $encrypt_agent_name, PDO::PARAM_STR);
            $stmt->bindParam(":aws_ec2_name", $encrypt_aws_ec2_name, PDO::PARAM_STR);
            $stmt->bindParam(":mac_address", $encrypt_mac_address, PDO::PARAM_STR);
            $stmt->bindParam(":id", $asset['id'], PDO::PARAM_INT);
            $stmt->execute();
        }

        // Clear buffer sql    
        $stmt = $db->prepare("SELECT 'clear' ");
        $stmt->execute();
        $stmt->fetchAll();

        // Add settings to show tables were encrypted
        add_encrypted_field("enc_import_export_integration_assets_ipv4", "import_export_integration_assets", "ipv4", "openssl");
        add_encrypted_field("enc_import_export_integration_assets_fqdn", "import_export_integration_assetss", "fqdn", "openssl");
        add_encrypted_field("enc_import_export_integration_assets_operating_system", "import_export_integration_assets", "operating_system", "openssl");
        add_encrypted_field("enc_import_export_integration_assets_netbios_name", "import_export_integration_assets", "netbios_name", "openssl");
        add_encrypted_field("enc_import_export_integration_assets_agent_name", "import_export_integration_assets", "agent_name", "openssl");
    add_encrypted_field("enc_import_export_integration_assets_aws_ec2_name", "import_export_integration_assets", "aws_ec2_name", "openssl");
    add_encrypted_field("enc_import_export_integration_assets_mac_address", "import_export_integration_assets", "mac_address", "openssl");

        // Close the database connection
        db_close($db);
    }
}

/***************************************************************
 * FUNCTION: REMOVE ENCRYPTED IMPORT EXPORT INTEGRATION ASSETS *
 ***************************************************************/
function remove_encrypted_import_export_integration_assets($password)
{
        // If the import_export_integration_assets table exists
        if (table_exists("import_export_integration_assets"))
        {
                // Open the database connection
                $db = db_open();

                // Get all of the import_export_integration_assets
                $stmt = $db->prepare("SELECT * FROM import_export_integration_assets");
                $stmt->execute();
                $import_export_integration_assets = $stmt->fetchAll();

                // For each import_export_integration_assets
                foreach ($import_export_integration_assets as $asset)
                {
            $decrypt_ipv4 = decrypt($password, $asset['ipv4']);
            $decrypt_fqdn = decrypt($password, $asset['fqdn']);
            $decrypt_operating_system = decrypt($password, $asset['operating_system']);
            $decrypt_netbios_name = decrypt($password, $asset['netbios_name']);
            $decrypt_agent_name = decrypt($password, $asset['agent_name']);
            $decrypt_aws_ec2_name = decrypt($password, $asset['aws_ec2_name']);
            $decrypt_mac_address = decrypt($password, $asset['mac_address']);

            $stmt = $db->prepare("UPDATE `import_export_integration_assets` SET `ipv4` = :ipv4, `fqdn` = :fqdn, `operating_system` = :operating_system, `netbios_name` = :netbios_name, `agent_name` = :agent_name, `aws_ec2_name` = :aws_ec2_name, `mac_address` = :mac_address  WHERE id = :id");
            $stmt->bindParam(":ipv4", $decrypt_ipv4, PDO::PARAM_STR);
            $stmt->bindParam(":fqdn", $decrypt_fqdn, PDO::PARAM_STR);
            $stmt->bindParam(":operating_system", $decrypt_operating_system, PDO::PARAM_STR);
            $stmt->bindParam(":netbios_name", $decrypt_netbios_name, PDO::PARAM_STR);
            $stmt->bindParam(":agent_name", $decrypt_agent_name, PDO::PARAM_STR);
            $stmt->bindParam(":aws_ec2_name", $decrypt_aws_ec2_name, PDO::PARAM_STR);
            $stmt->bindParam(":mac_address", $decrypt_mac_address, PDO::PARAM_STR);
            $stmt->bindParam(":id", $asset['id'], PDO::PARAM_INT);
            $stmt->execute();
                }

                // Delete the setting
                disable_encryption("enc_import_export_integration_assets_ipv4", null, null);
                disable_encryption("enc_import_export_integration_assets_fqdn", null, null);
        disable_encryption("enc_import_export_integration_assets_operating_system", null, null);
        disable_encryption("enc_import_export_integration_assets_netbios_name", null, null);
        disable_encryption("enc_import_export_integration_assets_agent_name", null, null);
        disable_encryption("enc_import_export_integration_assets_aws_ec2_name", null, null);
        disable_encryption("enc_import_export_integration_assets_mac_address", null, null);

                // Close the database connection
                db_close($db);
    }
}

/***************************************
 * FUNCTION: REMOVE ENCRYPTED COMMENTS *
 ***************************************/
function remove_encrypted_comments($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted comments table
    $stmt = $db->prepare("CREATE TABLE comments_dec LIKE comments; INSERT comments_dec SELECT * FROM comments");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, date, comment FROM comments");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // Change the comment field back to mediumtext
    $stmt = $db->prepare("ALTER TABLE `comments_dec` CHANGE `comment` `comment` MEDIUMTEXT NOT NULL ;");
    $stmt->execute();

    // For each comment
    foreach ($comments as $comment)
    {
        $decrypt_comment = decrypt($password, $comment['comment']);

        $stmt = $db->prepare("UPDATE `comments_dec` SET `date` = :date, `comment` = :comment WHERE id = :id");
    $stmt->bindParam(":date", $comment['date'], PDO::PARAM_STR);
        $stmt->bindParam(":comment", $decrypt_comment, PDO::PARAM_STR);
        $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the decrypted comments table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE comments;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE comments LIKE comments_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT comments SELECT * FROM comments_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE comments_dec;");
    $stmt->execute();


    // Delete the setting
    delete_setting("enc_comments_comment");
    disable_encryption("enc_comments_comment", null, null);

    // Close the database connection
    db_close($db);
}

/*****************************************
 * FUNCTION: REMOVE ENCRYPTED FRAMEWORKS *
 *****************************************/
function remove_encrypted_frameworks($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted frameworks table
    $stmt = $db->prepare("CREATE TABLE frameworks_dec LIKE frameworks; INSERT frameworks_dec SELECT * FROM frameworks");
    $stmt->execute();

    // Get all of the frameworks
    $stmt = $db->prepare("SELECT value, name, description FROM frameworks");
    $stmt->execute();
    $frameworks = $stmt->fetchAll();

    // For each framework
    foreach ($frameworks as $framework)
    {
        $decrypt_name = decrypt($password, $framework['name']);
        $decrypt_description = decrypt($password, $framework['description']);

        $stmt = $db->prepare("UPDATE `frameworks_dec` SET `name` = :name, `description` = :description WHERE value = :value");
        $stmt->bindParam(":name", $decrypt_name, PDO::PARAM_STR);
        $stmt->bindParam(":description", $decrypt_description, PDO::PARAM_STR);
        $stmt->bindParam(":value", $framework['value'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the decrypted frameworks table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE frameworks;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE frameworks LIKE frameworks_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT frameworks SELECT * FROM frameworks_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE frameworks_dec;");
    $stmt->execute();


    // Delete the setting
    delete_setting("enc_frameworks_name");
    disable_encryption("enc_frameworks_name", null, null);
    delete_setting("enc_frameworks_description");
    disable_encryption("enc_frameworks_description", null, null);

    // Close the database connection
    db_close($db);
}

/*************************************
 * FUNCTION: REMOVE ENCRYPTED ASSETS *
 *************************************/
function remove_encrypted_assets($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted assets table
    $stmt = $db->prepare("CREATE TABLE assets_dec LIKE assets; INSERT assets_dec SELECT * FROM assets");
    $stmt->execute();

    // Get all of the assets
    $stmt = $db->prepare("SELECT * FROM assets");
    $stmt->execute();
    $assets = $stmt->fetchAll();

    // For each asset
    foreach ($assets as $asset)
    {
        // Check whether the fields are encrypted
        $enc_ip_array = is_field_encrypted(null, "assets", "ip");
        $enc_name_array = is_field_encrypted(null, "assets", "name");
        $enc_details_array = is_field_encrypted(null, "assets", "details");

        // If the ip is encrypted
        if ($enc_ip_array['encrypted'] == true)
        {
            $decrypt_ip = decrypt($password, $asset['ip']);
            $stmt = $db->prepare("UPDATE `assets_dec` SET `ip` = :ip WHERE id = :id");
            $stmt->bindParam(":ip", $decrypt_ip, PDO::PARAM_STR);
            $stmt->bindParam(":id", $asset['id'], PDO::PARAM_INT);
            $stmt->execute();
        }

        // If the name is encrypted
        if ($enc_name_array['encrypted'] == true)
        {
            $decrypt_name = decrypt($password, $asset['name']);
            $stmt = $db->prepare("UPDATE `assets_dec` SET `name` = :name WHERE id = :id");
            $stmt->bindParam(":name", $decrypt_name, PDO::PARAM_STR);
            $stmt->bindParam(":id", $asset['id'], PDO::PARAM_INT);
            $stmt->execute();
        }

        // If the details are encrypted
        if ($enc_details_array['encrypted'] == true)
        {
            $decrypt_details = decrypt($password, $asset['details']);
            $stmt = $db->prepare("UPDATE `assets_dec` SET `details` = :details WHERE id = :id");
            $stmt->bindParam(":details", $decrypt_details, PDO::PARAM_STR);
            $stmt->bindParam(":id", $asset['id'], PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    // Change the field types back to original
    $stmt = $db->prepare("ALTER TABLE `assets_dec` CHANGE `ip` `ip` varchar(15) DEFAULT NULL;");
    $stmt->execute();
    $stmt = $db->prepare("ALTER TABLE `assets_dec` CHANGE `name` `name` varchar(200) NOT NULL;");
    $stmt->execute();
    $stmt = $db->prepare("ALTER TABLE `assets_dec` CHANGE `details` `details` LONGTEXT;");
    $stmt->execute();

    // Make the name field unique
    $stmt = $db->prepare("ALTER TABLE `assets_dec` ADD UNIQUE (`name`);");
    $stmt->execute();

    // Move the decrypted assets table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE assets;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE assets LIKE assets_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT assets SELECT * FROM assets_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE assets_dec;");
    $stmt->execute();

    // Delete the setting
    delete_setting("enc_assets_ip");
    disable_encryption("enc_assets_ip", null, null);
    delete_setting("enc_assets_name");
    disable_encryption("enc_assets_name", null, null);
    delete_setting("enc_assets_details");
    disable_encryption("enc_assets_details", null, null);

    // Close the database connection
    db_close($db);
}

/**********************************************
 * FUNCTION: REMOVE ENCRYPTED RISKS TO ASSETS *
 **********************************************/
function remove_encrypted_risks_to_assets($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted risks_to_assets table
    $stmt = $db->prepare("
    CREATE TABLE `risks_to_assets_dec` (
      `risk_id` int(11) DEFAULT NULL,
      `asset_id` int(11) NOT NULL,
      `asset` varchar(200) NOT NULL,
      UNIQUE KEY `risk_id` (`risk_id`,`asset`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT risks_to_assets_dec SELECT * FROM risks_to_assets");
    $stmt->execute();

    // Get all of the risks_to_assets
    $stmt = $db->prepare("SELECT * FROM risks_to_assets");
    $stmt->execute();
    $risks_to_assets = $stmt->fetchAll();

    // For each risks_to_assets
    foreach ($risks_to_assets as $row)
    {
        // Check whether the fields are encrypted
        $enc_asset_array = is_field_encrypted(null, "risks_to_assets", "asset");

        // If the asset is encrypted
        if ($enc_asset_array['encrypted'] == true)
        {
            $decrypt_asset = decrypt($password, $row['asset']);
            $stmt = $db->prepare("UPDATE `risks_to_assets_dec` SET `asset` = :asset WHERE risk_id = :risk_id AND asset_id = :asset_id");
            $stmt->bindParam(":asset", $decrypt_asset, PDO::PARAM_STR);
            $stmt->bindParam(":risk_id", $row['risk_id'], PDO::PARAM_INT);
            $stmt->bindParam(":asset_id", $row['asset_id'], PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    // Move the decrypted risks_to_assets table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE risks_to_assets;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE risks_to_assets LIKE risks_to_assets_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT risks_to_assets SELECT * FROM risks_to_assets_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE risks_to_assets_dec;");
    $stmt->execute();


    // Delete the setting
    delete_setting("enc_risks_to_assets_asset");
    disable_encryption("enc_risks_to_assets_asset", null, null);

    // Close the database connection
    db_close($db);
}

/***********************************************************
 * FUNCTION: REMOVE ENCRYPTED QUESTIONNARE RESULT COMMENTS *
 ***********************************************************/
function remove_encrypted_questionnaire_result_comments($password)
{
    // If the questionnaire_result_comments table exists
    if (table_exists("questionnaire_result_comments"))
    {
        // Open the database connection
        $db = db_open();

        // Create the new decrypted questionnaire result comments table
        $stmt = $db->prepare("CREATE TABLE questionnaire_result_comments_dec LIKE questionnaire_result_comments; INSERT questionnaire_result_comments_dec SELECT * FROM questionnaire_result_comments");
        $stmt->execute();

        // Change the field types back to original
        $stmt = $db->prepare("ALTER TABLE `questionnaire_result_comments_dec` CHANGE `comment` `comment` MEDIUMTEXT;");
        $stmt->execute();

        // Get all of the questionnaire result comments
        $stmt = $db->prepare("SELECT * FROM questionnaire_result_comments; ");
        $stmt->execute();
        $questionnaire_result_comments = $stmt->fetchAll();

        // Decrypt only if comment field is encrypted
        $enc_comment_arr = is_field_encrypted(null, "questionnaire_result_comments", "comment");
        
        // For each questionnaire result comments
        foreach ($questionnaire_result_comments as $questionnaire_result_comment)
        {
            if($enc_comment_arr['encrypted'])
            {
                $decrypt_comment = decrypt($password, $questionnaire_result_comment['comment']);
            }
            else
            {
                $decrypt_comment = $questionnaire_result_comment['comment'];
            }

            $stmt = $db->prepare("UPDATE `questionnaire_result_comments_dec` SET `comment` = :comment WHERE id = :id");
            $stmt->bindParam(":comment", $decrypt_comment, PDO::PARAM_STR);
            $stmt->bindParam(":id", $questionnaire_result_comment['id'], PDO::PARAM_INT);
            $stmt->execute();
        }

        // Move the decrypted questionnaire result comments table in place of the encrypted one
        $stmt = $db->prepare("DROP TABLE questionnaire_result_comments;");
        $stmt->execute();
        $stmt = $db->prepare("CREATE TABLE questionnaire_result_comments LIKE questionnaire_result_comments_dec;");
        $stmt->execute();
        $stmt = $db->prepare("INSERT questionnaire_result_comments SELECT * FROM questionnaire_result_comments_dec;");
        $stmt->execute();
        $stmt = $db->prepare("DROP TABLE questionnaire_result_comments_dec;");
        $stmt->execute();

        // Delete the setting
        delete_setting("enc_questionnaire_result_comments_comment");
        disable_encryption("enc_questionnaire_result_comments_comment", null, null);

        // Close the database connection
        db_close($db);
    }
}

/*****************************************************
 * FUNCTION: REMOVE ENCRYPTED QUESTIONNARE RESPONSES *
 *****************************************************/
function remove_encrypted_questionnaire_responses($password)
{
    // If the questionnaire_responses table exists
    if (table_exists("questionnaire_responses"))
    {
        // Open the database connection
        $db = db_open();

        // Create the new decrypted questionnaire responses table
        $stmt = $db->prepare("CREATE TABLE questionnaire_responses_dec LIKE questionnaire_responses; INSERT questionnaire_responses_dec SELECT * FROM questionnaire_responses");
        $stmt->execute();

        // Change the field types back to original
        $stmt = $db->prepare("ALTER TABLE `questionnaire_responses_dec` CHANGE `additional_information` `additional_information` TEXT, CHANGE `answer` `answer` blob;");
        $stmt->execute();

        // Get all of the questionnaire responses
        $stmt = $db->prepare("SELECT * FROM questionnaire_responses");
        $stmt->execute();
        $questionnaire_responses = $stmt->fetchAll();
        
        // Check if fields are encrypted
        $enc_additional_infromation_arr = is_field_encrypted(null, "questionnaire_responses", "additional_information");
        $enc_answer_arr = is_field_encrypted(null, "questionnaire_responses", "answer");


        // For each questionnaire response
        foreach ($questionnaire_responses as $questionnaire_response)
        {
            if($enc_additional_infromation_arr['encrypted'])
            {
                $decrypt_additional_information = decrypt($password, $questionnaire_response['additional_information']);
            }
            else
            {
                $decrypt_additional_information = $questionnaire_response['additional_information'];
            }
            
            if($enc_answer_arr['encrypted'])
            {
                $decrypt_answer = decrypt($password, $questionnaire_response['answer']);
            }
            else
            {
                $decrypt_answer = $questionnaire_response['answer'];
            }

            $stmt = $db->prepare("UPDATE `questionnaire_responses_dec` SET `additional_information` = :additional_information, `answer` = :answer WHERE id = :id");
            $stmt->bindParam(":additional_information", $decrypt_additional_information, PDO::PARAM_STR);
            $stmt->bindParam(":answer", $decrypt_answer, PDO::PARAM_STR);
            $stmt->bindParam(":id", $questionnaire_response['id'], PDO::PARAM_INT);
            $stmt->execute();
        }

        // Move the decrypted questionnaire responses table in place of the encrypted one
        $stmt = $db->prepare("DROP TABLE questionnaire_responses;");
        $stmt->execute();
        $stmt = $db->prepare("CREATE TABLE questionnaire_responses LIKE questionnaire_responses_dec;");
        $stmt->execute();
        $stmt = $db->prepare("INSERT questionnaire_responses SELECT * FROM questionnaire_responses_dec;");
        $stmt->execute();
        $stmt = $db->prepare("DROP TABLE questionnaire_responses_dec;");
        $stmt->execute();

        // Delete the setting
        delete_setting("enc_questionnaire_responses_additional_infromation");
        disable_encryption("enc_questionnaire_responses_additional_infromation", null, null);
        delete_setting("enc_questionnaire_responses_answer");
        disable_encryption("enc_questionnaire_responses_answer", null, null);

        // Close the database connection
        db_close($db);
    }
}

/**************************************************
 * FUNCTION: REMOVE ENCRYPTED ASSESSMENT CONTACTS *
 **************************************************/
function remove_encrypted_assessment_contacts($password)
{
    // If the assessment_contacts table exists
    if (table_exists("assessment_contacts"))
    {
        // Open the database connection
        $db = db_open();
        
        // Create the new decrypted assessment_contacts table
        $stmt = $db->prepare("CREATE TABLE assessment_contacts_dec LIKE assessment_contacts; INSERT assessment_contacts_dec SELECT * FROM assessment_contacts");
        $stmt->execute();

        // Change the field types back to original
        $stmt = $db->prepare("ALTER TABLE `assessment_contacts_dec` CHANGE `company` `company` VARCHAR(255), CHANGE `name` `name` VARCHAR(255), CHANGE `email` `email` VARCHAR(255), CHANGE `phone` `phone` VARCHAR(255), CHANGE `details` `details` TEXT;");
        $stmt->execute();

        // Get all of the assessment contacts
        $stmt = $db->prepare("SELECT * FROM assessment_contacts; ");
        $stmt->execute();
        $assessment_contacts = $stmt->fetchAll();

        // For each contact
        foreach ($assessment_contacts as $assessment_contact)
        {
            $decrypt_company = decrypt($password, $assessment_contact['company']);
            $decrypt_name = decrypt($password, $assessment_contact['name']);
            $decrypt_email = decrypt($password, $assessment_contact['email']);
            $decrypt_phone = decrypt($password, $assessment_contact['phone']);
            $decrypt_details = decrypt($password, $assessment_contact['details']);
        
            $stmt = $db->prepare("UPDATE `assessment_contacts_dec` SET `company` = :company, `name` = :name, `email` = :email, `phone` = :phone, `details` = :details WHERE id = :id; ");
            $stmt->bindParam(":company", $decrypt_company, PDO::PARAM_STR);
            $stmt->bindParam(":name", $decrypt_name, PDO::PARAM_STR);
            $stmt->bindParam(":email", $decrypt_email, PDO::PARAM_STR);
            $stmt->bindParam(":phone", $decrypt_phone, PDO::PARAM_STR);
            $stmt->bindParam(":details", $decrypt_details, PDO::PARAM_STR);
            $stmt->bindParam(":id", $assessment_contact['id'], PDO::PARAM_INT);
            $stmt->execute();
        }

        // Move the decrypted assessment contacts table in place of the encrypted one
        $stmt = $db->prepare("DROP TABLE assessment_contacts;");
        $stmt->execute();
        $stmt = $db->prepare("CREATE TABLE assessment_contacts LIKE assessment_contacts_dec;");
        $stmt->execute();
        $stmt = $db->prepare("INSERT assessment_contacts SELECT * FROM assessment_contacts_dec;");
        $stmt->execute();
        $stmt = $db->prepare("DROP TABLE assessment_contacts_dec;");
        $stmt->execute();

        // Delete the setting
        delete_setting("enc_assessment_contact_company");
        disable_encryption("enc_assessment_contact_company", null, null);
        delete_setting("enc_assessment_contact_name");
        disable_encryption("enc_assessment_contact_name", null, null);
        delete_setting("enc_assessment_contact_email");
        disable_encryption("enc_assessment_contact_email", null, null);
        delete_setting("enc_assessment_contact_phone");
        disable_encryption("enc_assessment_contact_phone", null, null);
        delete_setting("enc_assessment_contact_details");
        disable_encryption("enc_assessment_contact_details", null, null);

        // Close the database connection
        db_close($db);
    }
}

/*******************************************
 * FUNCTION: CREATE ENCRYPTED MGMT REVIEWS *
 *******************************************/
function create_encrypted_mgmt_reviews($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted comments table
    $stmt = $db->prepare("CREATE TABLE mgmt_reviews_enc LIKE mgmt_reviews; INSERT mgmt_reviews_enc SELECT * FROM mgmt_reviews");
    $stmt->execute();

    // Change the comment field to a blob to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `mgmt_reviews_enc` CHANGE `comments` `comments` BLOB NOT NULL ;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, comments FROM mgmt_reviews");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
            $encrypt_comments = encrypt($password, $comment['comments']);

            $stmt = $db->prepare("UPDATE `mgmt_reviews_enc` SET `comments` = :comment WHERE id = :id");
            $stmt->bindParam(":comment", $encrypt_comments);
            $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
            $stmt->execute();
    }

    // Move the encrypted mgmt_reviews table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE mgmt_reviews; CREATE TABLE mgmt_reviews LIKE mgmt_reviews_enc; INSERT mgmt_reviews SELECT * FROM mgmt_reviews_enc; DROP TABLE mgmt_reviews_enc;");
    $stmt->execute();

    // Clear buffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_encrypted_field("enc_mgmt_reviews_comment", "mgmt_reviews", "comments", "openssl");

    // Close the database connection
    db_close($db);
}

/*******************************************
 * FUNCTION: REMOVE ENCRYPTED MGMT REVIEWS *
 *******************************************/
function remove_encrypted_mgmt_reviews($password)
{
        // Open the database connection
        $db = db_open();

        // Create the new encrypted comments table
        $stmt = $db->prepare("CREATE TABLE mgmt_reviews_dec LIKE mgmt_reviews; INSERT mgmt_reviews_dec SELECT * FROM mgmt_reviews");
        $stmt->execute();

        // Change the comment field back to medium text
        $stmt = $db->prepare("ALTER TABLE `mgmt_reviews_dec` CHANGE `comments` `comments` MEDIUMTEXT NOT NULL ;");
        $stmt->execute();

        // Get all of the comments
        $stmt = $db->prepare("SELECT id, comments FROM mgmt_reviews");
        $stmt->execute();
        $comments = $stmt->fetchAll();

        // For each comment
        foreach ($comments as $comment)
        {
                $decrypt_comments = decrypt($password, $comment['comments']);

                $stmt = $db->prepare("UPDATE `mgmt_reviews_dec` SET `comments` = :comment WHERE id = :id");
                $stmt->bindParam(":comment", $decrypt_comments);
                $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
                $stmt->execute();
        }

        // Move the decrypted mgmt_reviews table in place of the encrypted one
        $stmt = $db->prepare("DROP TABLE mgmt_reviews;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE mgmt_reviews LIKE mgmt_reviews_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT mgmt_reviews SELECT * FROM mgmt_reviews_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE mgmt_reviews_dec;");
        $stmt->execute();

    // Delete the setting
        delete_setting("enc_mgmt_reviews_comment");
    disable_encryption("enc_mgmt_reviews_comment", null, null);

        // Close the database connection
        db_close($db);
}

/******************************************
 * FUNCTION: CREATE ENCRYPTED MITIGATIONS *
 ******************************************/
function create_encrypted_mitigations($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted comments table
    $stmt = $db->prepare("CREATE TABLE mitigations_enc LIKE mitigations; INSERT mitigations_enc SELECT * FROM mitigations");
    $stmt->execute();

    // Change the comment field to a blob to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `mitigations_enc` CHANGE `current_solution` `current_solution` BLOB NOT NULL, CHANGE `security_requirements` `security_requirements` BLOB NOT NULL, CHANGE `security_recommendations` `security_recommendations` BLOB NOT NULL;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, current_solution, security_requirements, security_recommendations FROM mitigations");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
            $encrypt_current_solution = encrypt($password, $comment['current_solution']);
            $encrypt_security_requirements = encrypt($password, $comment['security_requirements']);
            $encrypt_security_recommendations = encrypt($password, $comment['security_recommendations']);

            $stmt = $db->prepare("UPDATE `mitigations_enc` SET `current_solution` = :current_solution, `security_requirements` = :security_requirements, `security_recommendations` = :security_recommendations WHERE id = :id");
            $stmt->bindParam(":current_solution", $encrypt_current_solution, PDO::PARAM_STR);
            $stmt->bindParam(":security_requirements", $encrypt_security_requirements, PDO::PARAM_STR);
            $stmt->bindParam(":security_recommendations", $encrypt_security_recommendations, PDO::PARAM_STR);
            $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
            $stmt->execute();
    }

    // Move the encrypted mitigations table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE mitigations; CREATE TABLE mitigations LIKE mitigations_enc; INSERT mitigations SELECT * FROM mitigations_enc; DROP TABLE mitigations_enc;");
    $stmt->execute();

        // Clear buffer sql    
        $stmt = $db->prepare("SELECT 'clear' ");
        $stmt->execute();
        $comments = $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_encrypted_field("enc_mitigations_security_requirements", "mitigations", "security_requirements", "openssl");
    add_encrypted_field("enc_mitigations_security_recommendations", "mitigations", "security_recommendations", "openssl");
    add_encrypted_field("enc_mitigations_current_solution", "mitigations", "current_solution", "openssl");

    // Close the database connection
    db_close($db);
}

/******************************************
 * FUNCTION: REMOVE ENCRYPTED MITIGATIONS *
 ******************************************/
function remove_encrypted_mitigations($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted comments table
    $stmt = $db->prepare("CREATE TABLE mitigations_dec LIKE mitigations; INSERT mitigations_dec SELECT * FROM mitigations");
    $stmt->execute();

    // Change the fields back to mediumtext
    $stmt = $db->prepare("ALTER TABLE `mitigations_dec` CHANGE `current_solution` `current_solution` MEDIUMTEXT NOT NULL, CHANGE `security_requirements` `security_requirements` MEDIUMTEXT NOT NULL, CHANGE `security_recommendations` `security_recommendations` MEDIUMTEXT NOT NULL;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, current_solution, security_requirements, security_recommendations FROM mitigations");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
        $decrypt_current_solution =  decrypt($password, $comment['current_solution']);
        $decrypt_security_requirements =  decrypt($password, $comment['security_requirements']);
        $decrypt_security_recommendations =  decrypt($password, $comment['security_recommendations']);

        $stmt = $db->prepare("UPDATE `mitigations_dec` SET `current_solution` = :current_solution, `security_requirements` = :security_requirements, `security_recommendations` = :security_recommendations WHERE id = :id");
        $stmt->bindParam(":current_solution", $decrypt_current_solution, PDO::PARAM_STR);
        $stmt->bindParam(":security_requirements", $decrypt_security_requirements, PDO::PARAM_STR);
        $stmt->bindParam(":security_recommendations", $decrypt_security_recommendations, PDO::PARAM_STR);
        $stmt->bindParam(":id", $comment['id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the decrypted mitigations table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE mitigations;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE mitigations LIKE mitigations_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT mitigations SELECT * FROM mitigations_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE mitigations_dec;");
    $stmt->execute();

    // Delete the settings
    delete_setting("enc_mitigations_security_requirements");
    disable_encryption("enc_mitigations_security_requirements", null, null);
    delete_setting("enc_mitigations_security_recommendations");
    disable_encryption("enc_mitigations_security_recommendations", null, null);
    delete_setting("enc_mitigations_current_solution");
    disable_encryption("enc_mitigations_current_solution", null, null);

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: CREATE ENCRYPTED PROJECTS *
 ***************************************/
function create_encrypted_projects($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted projects table
    $stmt = $db->prepare("CREATE TABLE projects_enc LIKE projects; INSERT projects_enc SELECT * FROM projects");
    $stmt->execute();

    // Change the comment field to a blob to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `projects_enc` CHANGE `name` `name` BLOB NOT NULL;");
    $stmt->execute();

    // Set the value of the Unassigned Risks table back to 0
    $stmt = $db->prepare("UPDATE projects_enc SET value=0 WHERE `order`=1 AND `status`=1;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT value, name FROM projects");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
            $encrypt_name = encrypt($password, $comment['name']);

            $stmt = $db->prepare("UPDATE `projects_enc` SET `name` = :name WHERE value = :value");
            $stmt->bindParam(":name", $encrypt_name, PDO::PARAM_STR);
            $stmt->bindParam(":value", $comment['value'], PDO::PARAM_INT);
            $stmt->execute();
    }

    // Move the encrypted projects table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE projects; CREATE TABLE projects LIKE projects_enc; INSERT projects SELECT * FROM projects_enc; DROP TABLE projects_enc;");
    $stmt->execute();

    // Set the value of the Unassigned Risks table back to 0
    $stmt = $db->prepare("UPDATE projects SET value=0 WHERE `order`=1 AND `status`=1;");
    $stmt->execute();

    // Add settings to show tables were encrypted
    add_encrypted_field("enc_projects_name", "projects", "name", "openssl");

    // Close the database connection
    db_close($db);
}

/***************************************
 * FUNCTION: REMOVE ENCRYPTED PROJECTS *
 ***************************************/
function remove_encrypted_projects($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted projects table
    $stmt = $db->prepare("CREATE TABLE projects_dec LIKE projects; INSERT projects_dec SELECT * FROM projects");
    $stmt->execute();

    // Set the value of the Unassigned Risks table back to 0
    $stmt = $db->prepare("UPDATE projects_dec SET value=0 WHERE `order`=1 AND `status`=1;");
    $stmt->execute();

    // Get all of the projects
    $stmt = $db->prepare("SELECT value, name FROM projects");
    $stmt->execute();
    $comments = $stmt->fetchAll();

    // For each comment
    foreach ($comments as $comment)
    {
        $decrypt_name = decrypt($password, $comment['name']);

        $stmt = $db->prepare("UPDATE `projects_dec` SET `name` = :name WHERE value = :value");
        $stmt->bindParam(":name", $decrypt_name, PDO::PARAM_STR);
        $stmt->bindParam(":value", $comment['value'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Change the name field back to a VARCHAR(100)
    $stmt = $db->prepare("ALTER TABLE `projects_dec` CHANGE `name` `name` VARCHAR(100) NOT NULL;");
    $stmt->execute();    

    // Move the decrypted projects table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE projects;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE projects LIKE projects_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT projects SELECT * FROM projects_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE projects_dec;");
    $stmt->execute();

    // Set the value of the Unassigned Risks table back to 0
    $stmt = $db->prepare("UPDATE projects SET value=0 WHERE `order`=1 AND `status`=1;");
    $stmt->execute();

    // Delete the settings
    delete_setting("enc_projects_name");
    disable_encryption("enc_projects_name", null, null);

    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: CREATE ENCRYPTED RISKS *
 ************************************/
function create_encrypted_risks($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted risks table
    $stmt = $db->prepare("CREATE TABLE risks_enc LIKE risks; INSERT risks_enc SELECT * FROM risks");
    $stmt->execute();

    // Change the text fields to blobs to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `risks_enc` CHANGE `subject` `subject` BLOB NOT NULL, CHANGE `assessment` `assessment` BLOB NOT NULL, CHANGE `notes` `notes` BLOB NOT NULL;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, subject, assessment, notes FROM risks");
    $stmt->execute();
    $risks = $stmt->fetchAll();

    // For each comment
    foreach ($risks as $risk)
    {
            $encrypt_subject = encrypt($password, $risk['subject']);
            $encrypt_assessment = encrypt($password, $risk['assessment']);
            $encrypt_notes = encrypt($password, $risk['notes']);

            $stmt = $db->prepare("UPDATE `risks_enc` SET `subject` = :subject, `assessment` = :assessment, `notes` = :notes WHERE id = :id");
            $stmt->bindParam(":subject", $encrypt_subject, PDO::PARAM_STR);
            $stmt->bindParam(":assessment", $encrypt_assessment, PDO::PARAM_STR);
            $stmt->bindParam(":notes", $encrypt_notes, PDO::PARAM_STR);
            $stmt->bindParam(":id", $risk['id'], PDO::PARAM_INT);
            $stmt->execute();
    }

    // Move the encrypted risks table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE risks; CREATE TABLE risks LIKE risks_enc; INSERT risks SELECT * FROM risks_enc; DROP TABLE risks_enc;");
    $stmt->execute();

    // Clear buffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_encrypted_field("enc_risks_subject", "risks", "subject", "openssl");
    add_encrypted_field("enc_risks_assessment", "risks", "assessment", "openssl");
    add_encrypted_field("enc_risks_notes", "risks", "notes", "openssl");

    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: REMOVE ENCRYPTED RISKS *
 ************************************/
function remove_encrypted_risks($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new decrypted risks table
    $stmt = $db->prepare("CREATE TABLE risks_dec LIKE risks; INSERT risks_dec SELECT * FROM risks");
    $stmt->execute();

    // Change the field types back to original
    $stmt = $db->prepare("ALTER TABLE `risks_dec` CHANGE `subject` `subject` BLOB NOT NULL, CHANGE `assessment` `assessment` LONGTEXT NOT NULL, CHANGE `notes` `notes` LONGTEXT NOT NULL;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT id, subject, assessment, notes FROM risks");
    $stmt->execute();
    $risks = $stmt->fetchAll();

    // For each comment
    foreach ($risks as $risk)
    {
        $decrypt_subject = decrypt($password, $risk['subject']);
        $decrypt_assessment = decrypt($password, $risk['assessment']);
        $decrypt_notes = decrypt($password, $risk['notes']);

        $stmt = $db->prepare("UPDATE `risks_dec` SET `subject` = :subject, `assessment` = :assessment, `notes` = :notes WHERE id = :id");
        $stmt->bindParam(":subject", $decrypt_subject, PDO::PARAM_STR);
        $stmt->bindParam(":assessment", $decrypt_assessment, PDO::PARAM_STR);
        $stmt->bindParam(":notes", $decrypt_notes, PDO::PARAM_STR);
        $stmt->bindParam(":id", $risk['id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    // Move the decrypted risks table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE risks;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE risks LIKE risks_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT risks SELECT * FROM risks_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE risks_dec;");
    $stmt->execute();

    // Delete the settings
    delete_setting("enc_risks_subject");
    disable_encryption("enc_risks_subject", null, null);
    delete_setting("enc_risks_assessment");
    disable_encryption("enc_risks_assessment", null, null);
    delete_setting("enc_risks_notes");
    disable_encryption("enc_risks_notes", null, null);

    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: CREATE ENCRYPTED AUDIT *
 ************************************/
function create_encrypted_audit($password)
{
    // Open the database connection
    $db = db_open();

    // Create the new encrypted audit table
    $stmt = $db->prepare("DROP TABLE IF EXISTS audit_log_enc; CREATE TABLE audit_log_enc LIKE audit_log; /*INSERT audit_log_enc SELECT * FROM audit_log*/");
    $stmt->execute();

    // Change the text fields to blobs to store encrypted text
    $stmt = $db->prepare("ALTER TABLE `audit_log_enc` CHANGE `message` `message` BLOB NOT NULL;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT * FROM audit_log");
    $stmt->execute();
    $audit_logs = $stmt->fetchAll();

    // For each log
    $index = 0;
    foreach ($audit_logs as $key => $audit_log)
    {
        if($index == 0){
            $sql = "Insert into audit_log_enc (risk_id, user_id, message, timestamp, log_type) VALUES ";
            $valueArray = array();
            $params = array();
        }

        $valueArray[] = "(:risk_id{$key}, :user_id{$key}, :message{$key}, :timestamp{$key}, :log_type{$key})";

        $params[] = array(
            "risk_id" => array('label'=>":risk_id{$key}", 'value'=>$audit_log['risk_id']) ,
            "user_id" => array('label'=>":user_id{$key}", 'value'=>$audit_log['user_id']) ,
            "message" => array('label'=>":message{$key}", 'value'=>encrypt($password,  $audit_log['message'])) ,
            "timestamp" => array('label'=>":timestamp{$key}", 'value'=>$audit_log['timestamp']) ,
            "log_type" => array('label'=>":log_type{$key}", 'value'=>$audit_log['log_type']) ,
        );
        

        $index++;
        if($index == 100 || $key == (count($audit_logs) - 1)){
            $sql .= implode(", ", $valueArray);
            $sql .= ";";
            $stmt = $db->prepare($sql);
            
            // set params
            foreach($params as $param){
                $stmt->bindParam($param['risk_id']['label'], $param['risk_id']['value'], PDO::PARAM_INT, 11);
                $stmt->bindParam($param['user_id']['label'], $param['user_id']['value'], PDO::PARAM_INT, 11);
                $stmt->bindParam($param['message']['label'], $param['message']['value'], PDO::PARAM_STR);
                $stmt->bindParam($param['timestamp']['label'], $param['timestamp']['value']);
                $stmt->bindParam($param['log_type']['label'], $param['log_type']['value']);
            }
            
            $stmt->execute();
            $index = 0;
        }

    }

    // Move the encrypted audit table in place of the unencrypted one
    $stmt = $db->prepare("DROP TABLE audit_log; CREATE TABLE audit_log LIKE audit_log_enc; INSERT audit_log SELECT * FROM audit_log_enc; DROP TABLE audit_log_enc;");
    $stmt->execute();

    // Clear buffer sql    
    $stmt = $db->prepare("SELECT 'clear' ");
    $stmt->execute();
    $stmt->fetchAll();

    // Add settings to show tables were encrypted
    add_encrypted_field("enc_audit_log_message", "audit_log", "message", "openssl");

    // Close the database connection
    db_close($db);
}

/************************************
 * FUNCTION: REMOVE ENCRYPTED AUDIT *
 ************************************/
function remove_encrypted_audit($password)
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("DROP TABLE IF EXISTS `audit_log_dec`;");
    $stmt->execute();

    // Create the new decrypted audit table
    $stmt = $db->prepare("CREATE TABLE audit_log_dec LIKE audit_log; /*INSERT audit_log_dec SELECT * FROM audit_log*/");
    $stmt->execute();

    // Change the message field type back to medium text
    $stmt = $db->prepare("ALTER TABLE `audit_log_dec` CHANGE `message` `message` MEDIUMTEXT NOT NULL;");
    $stmt->execute();

    // Get all of the comments
    $stmt = $db->prepare("SELECT * FROM audit_log");
    $stmt->execute();
    $audit_logs = $stmt->fetchAll();
    // For each comment
    $index = 0;
    
    foreach ($audit_logs as $key => $audit_log)
    {
        if($index == 0){
            $sql = "Insert into audit_log_dec (risk_id, user_id, message, timestamp, log_type) VALUES ";
            $valueArray = array();
            $params = array();
        }

        $valueArray[] = "(:risk_id{$key}, :user_id{$key}, :message{$key}, :timestamp{$key}, :log_type{$key})";

        $params[] = array(
            "risk_id" => array('label'=>":risk_id{$key}", 'value'=>$audit_log['risk_id']) ,
            "user_id" => array('label'=>":user_id{$key}", 'value'=>$audit_log['user_id']) ,
            "message" => array('label'=>":message{$key}", 'value'=>decrypt($password,  $audit_log['message'])) ,
            "timestamp" => array('label'=>":timestamp{$key}", 'value'=>$audit_log['timestamp']) ,
            "log_type" => array('label'=>":log_type{$key}", 'value'=>$audit_log['log_type']) ,
        );

        $index++;
        if($index == 100 || $key == (count($audit_logs) - 1)){
            $sql .= implode(", ", $valueArray);
            $sql .= ";";
            $stmt = $db->prepare($sql);
            
            // set params
            foreach($params as $param){
                $stmt->bindParam($param['risk_id']['label'], $param['risk_id']['value'], PDO::PARAM_INT, 11);
                $stmt->bindParam($param['user_id']['label'], $param['user_id']['value'], PDO::PARAM_INT, 11);
                $stmt->bindParam($param['message']['label'], $param['message']['value'], PDO::PARAM_STR);
                $stmt->bindParam($param['timestamp']['label'], $param['timestamp']['value']);
                $stmt->bindParam($param['log_type']['label'], $param['log_type']['value']);
            }
            
            $stmt->execute();
            $index = 0;
        }
        
    }

    // Move the decrypted audit table in place of the encrypted one
    $stmt = $db->prepare("DROP TABLE audit_log;");
    $stmt->execute();
    $stmt = $db->prepare("CREATE TABLE audit_log LIKE audit_log_dec;");
    $stmt->execute();
    $stmt = $db->prepare("INSERT audit_log SELECT * FROM audit_log_dec;");
    $stmt->execute();
    $stmt = $db->prepare("DROP TABLE IF EXISTS `audit_log_dec`;");
    $stmt->execute();

    // Delete the setting
    delete_setting("enc_audit_log_message");
    disable_encryption("enc_audit_log_message", null, null);

    // Close the database connection
    db_close($db);
}

/*********************************
 * FUNCTION: CHECK ALL ACTIVATED *
 *********************************/
function check_all_activated()
{
    // Open the database connection
    $db = db_open();

    // Find any users who are not yet activated
    $stmt = $db->prepare("SELECT activated from user_enc where activated=0;");
    $stmt->execute();
    $activated = $stmt->fetchAll();

    // If no unactivated users are left
    if (get_setting("ENCRYPTION_LEVEL", "file")=="user" && !$activated)
    {
        // Check if the init.php file exists
        if (is_file(__DIR__ . "/includes/init.php"))
        {
            // Delete the init.php file or return an error
            if (!unlink(__DIR__ . "/includes/init.php"))
            {
                set_alert(true, "bad", "Unable to delete the encryption initialization file located at " . __DIR__ . "/includes/init.php");
            }
        }

        // Check if the includes directory exists
        if (is_dir(__DIR__ . "/includes"))
        {
            // Delete the includes directory or return an error
            if (!rmdir(__DIR__ . "/includes"))
            {
                set_alert(true, "bad", "Unable to delete the encryption includes directory located at " . __DIR__ . "/includes");
            }
        }
    }
    
    // Close the database connection
    db_close($db);
}

/****************************
 * FUNCTION: CHECK USER ENC *
 ****************************/
function check_user_enc($user, $pass)
{
    $ENCRYPTION_LEVEL = get_setting("ENCRYPTION_LEVEL", "file");

    // Get the encryption method
    $encryption_method = get_setting('encryption_method', 'mcrypt');
    write_debug_log("Encryption method: " . $encryption_method);

    // Set the encrytion method in the session
    $_SESSION['encryption_method'] = $encryption_method;

    // If the encryption method is openssl
    if ($encryption_method == "openssl")
    {
        // Fetch the encryption pass
        $encrypted_pass = fetch_key();
    }
    // Otherwise the encryption method is mcrypt
    else
    {
        // If the user has been activated
        if (activated_user($user))
        {
            write_debug_log("User is activated.");

            // If the encryption level is user
            if ($ENCRYPTION_LEVEL == "user")
            {
                write_debug_log("Encryption level is user.");
                $encrypted_pass = get_enc_pass($user, $pass);
        write_debug_log("Encrypted pass is: " . $encrypted_pass);
            }
            // If the encryption level is file
            else if ($ENCRYPTION_LEVEL == "file")
            {
                write_debug_log("Encryption level is file.");
                $encrypted_pass = get_enc_pass($user, fetch_tmp_pass());
        write_debug_log("Encrypted pass is: " . $encrypted_pass);
            }
        }
        // The user has not yet been activated
        else
        {
            write_debug_log("User is NOT activated.");

            // Get the current password encrypted with the temp key
            $encrypted_pass = get_enc_pass($user, fetch_tmp_pass());
            write_debug_log("Encrypted pass is: " . $encrypted_pass);

            // If the encryption level is user
            if ($ENCRYPTION_LEVEL == "user")
            {
                write_debug_log("Encryption level is user.");

                // Set the new encrypted password
                set_enc_pass($user, $pass, $encrypted_pass);

                // Check to see if all users have now been activated
                check_all_activated();
            }
        }
    }

    // Set the encrypted pass in the session
    $_SESSION['encrypted_pass'] = $encrypted_pass;
    write_debug_log("Set the session encrypted password to: " . $encrypted_pass);
}

/**************************
 * FUNCTION: ADD USER ENC *
 **************************/
function add_user_enc($pass, $salt, $user)
{
    // Open the database connection
    $db = db_open();

    // Get the id for the user
    $value = get_id_by_user($user);

    // Set an empty encrypted password
    $encrypted_pass = "";

    // Insert a stub entry into the user encryption table
    $stmt = $db->prepare("INSERT INTO `user_enc` (`value`, `username`, `activated`, `encrypted_pass`) VALUES (:value, :username, 1, :encrypted_pass)");
    $stmt->bindParam(":value", $value, PDO::PARAM_INT, 11);
    $stmt->bindParam(":username", $user, PDO::PARAM_STR, 200);
    $stmt->bindParam(":encrypted_pass", $encrypted_pass, PDO::PARAM_LOB);
    $stmt->execute();

    $ENCRYPTION_LEVEL = get_setting("ENCRYPTION_LEVEL", "file");

    // If the encryption level is user
    if ($ENCRYPTION_LEVEL == "user")
    {
        // Set the encrypted password for the user
        set_enc_pass($user, $pass, $_SESSION['encrypted_pass']);
    }
    // If the encryption level is file
    else if ($ENCRYPTION_LEVEL == "file")
    {
        // Set the encrypted password for the user
        set_enc_pass($user, fetch_tmp_pass(), $_SESSION['encrypted_pass']);
    }

    // Close the database connection
    db_close($db);
}

/*****************************
 * FUNCTION: DELETE USER ENC *
 *****************************/
function delete_user_enc($value)
{
    // Open the database connection
    $db = db_open();

    // Delete the value from the user_enc table
    $stmt = $db->prepare("DELETE FROM `user_enc` WHERE value=:value");
    $stmt->bindParam(":value", $value, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/********************************
 * FUNCTION: ENCRYPTION VERSION *
 ********************************/
function encryption_version()
{
    // Return the version
    return ENCRYPTION_EXTRA_VERSION;
}

/*************************************
 * FUNCTION: GET ENCRYPTION SETTINGS *
 *************************************/
function get_encryption_settings()
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `settings` WHERE `name` = 'ENCRYPTION_LEVEL'");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/********************************************
 * FUNCTION: INITIALIZE ENCRYPTION SETTINGS *
 ********************************************/
function initialize_encryption_settings()
{
    // Open the database connection
    $db = db_open();

    // Set the encryption extra as activated
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'ENCRYPTION_LEVEL', `value` = 'file'");
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/****************************************
 * FUNCTION: UPDATE ENCRYPTION SETTINGS *
 ****************************************/
/*
function update_encryption_settings($configs)
{
    // Open the database connection
    $db = db_open();

    // If the ENCRYPTION_LEVEL value is file or user
    if ($configs['ENCRYPTION_LEVEL'] == "file" || $configs['ENCRYPTION_LEVEL'] == "user")
    {
        // Update the ENCRYPTION_LEVEL value
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'ENCRYPTION_LEVEL'");
        $stmt->bindParam(":value", $configs['ENCRYPTION_LEVEL']);
        $stmt->execute();


        // If the encryption file does not already exist
        if (!is_file(__DIR__ . "/includes/init.php"))
        {
            // Create the encryption file with the encrypted password
            create_init_file($_SESSION['encrypted_pass']);
        }

        // If the encryption level is file
        if ($configs['ENCRYPTION_LEVEL'] == "file")
        {
            // Enable the file encryption
            enable_file_encryption();
        }
        // If the encryption level is user
        else if ($configs['ENCRYPTION_LEVEL'] == "user")
        {
            // Mark all user as deactivated
            deactivate_all_enc_users();

            // If assessment extra is enabled
            if(assessments_extra()){
                // Mark all contact as deactivated
                deactivate_all_enc_contacts();
            }
        }
    }

    // Close the database connection
    db_close($db);

    // Display a message
    set_alert(true, "good", "The configuration was updated successfully.");

    // Return true;
    return true;
}
*/

/********************************
 * FUNCTION: DISPLAY ENCRYPTION *
 ********************************/
function display_encryption()
{
    global $escaper;
    global $lang;

    // If the form was posted
    /*
    if (isset($_POST['encryption_extra']))
    {
        // Get the posted values
        $configs['ENCRYPTION_LEVEL'] = isset($_POST['encryption_level']) ? $_POST['encryption_level'] : '';

        // Update the encryption settings
        update_encryption_settings($configs);
    }

    // Get the encryption settings
    $configs = get_encryption_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }
    */

    echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . encryption_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";

    $fileName = get_setting('unencrypted_backup_file_name');

    // If an unencrypted backup exists
    if ($fileName && file_exists(sys_get_temp_dir() . "/" . $fileName))
    {
        echo "<table border=\"1\" width=\"800\" cellpadding=\"10px\">\n";
        echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
        echo "<tr><td><font color=\"red\"><b>" . $escaper->escapeHtml($lang['EncryptionBackupFileExists']) . "</b></font></td></tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr><td><font color=\"red\"><b>" . $escaper->escapeHtml($lang['BackupLocation']) . ":</b>&nbsp;&nbsp;" . $escaper->escapeHtml(sys_get_temp_dir() . "/" . $fileName) . "</font></td></tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        echo "<tr><td><form method=\"post\"><input type=\"submit\" name=\"delete_backup_file\" value=\"" . $escaper->escapeHtml($lang['Delete']) . "\" />&nbsp;<input type=\"submit\" name=\"revert_to_unencrypted_backup\" value=\"" . $escaper->escapeHtml($lang['RevertToUnencryptedBackup']) . "\" /></form></td></tr>\n";
        echo "</table>\n";
        echo "</td></tr>\n";
        echo "</table>\n";
    }
}

/********************************************
 * FUNCTION: CHECK ENCRYPTION FROM EXTERNAL *
 ********************************************/
function check_encryption_from_external($username)
{
    $ENCRYPTION_LEVEL = get_setting("ENCRYPTION_LEVEL", "file");

    if ($ENCRYPTION_LEVEL == "user" && activated_user($username))
    {
        return false;
    }else{
        return true;
    }
}

/************************************************
 * FUNCTION: GET USERNAME TO GET ENCRYPTED PASS *
 ************************************************/
function get_username_for_encrypted_pass(){
    $ENCRYPTION_LEVEL = get_setting("ENCRYPTION_LEVEL", "file");

    if ($ENCRYPTION_LEVEL == "user")
    {
        $query = "SELECT username FROM `user_enc` WHERE activated=0;";
    }
    else if($ENCRYPTION_LEVEL == "file")
    {
        $query = "SELECT username FROM `user_enc`;";
    }
    else
    {
        return false;
    }
    
    // Open the database connection
    $db = db_open();

    // Get the current users
    $stmt = $db->prepare($query);
    $stmt->execute();
    $user_enc = $stmt->fetch();

    // Close the database connection
    db_close($db);
    
    return $user_enc ? $user_enc['username'] : false;
}

/*********************************
 * FUNCTION: ADD ENCRYPTED FIELD *
 *********************************/
function add_encrypted_field($mcrypt_name = null, $table = null, $field = null, $method = null)
{
    // Open the database connection
    $db = db_open();

    // Add it to the table
    $stmt = $db->prepare("INSERT INTO `encrypted_fields` (`mcrypt_name`, `table`, `field`, `encrypted`, `method`) VALUES (:mcrypt_name, :table, :field, 1, :method) ON DUPLICATE KEY UPDATE encrypted=1, method=:method;");
    $stmt->bindParam(":mcrypt_name", $mcrypt_name, PDO::PARAM_STR);
    $stmt->bindParam(":table", $table, PDO::PARAM_STR);
    $stmt->bindParam(":field", $field, PDO::PARAM_STR);
    $stmt->bindParam(":method", $method, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/********************************
 * FUNCTION: DISABLE ENCRYPTION *
 ********************************/
function disable_encryption($mcrypt_name = null, $table = null, $field = null)
{
    // Open the database connection
    $db = db_open();

    // If the mcrypt name is not null
    if ($mcrypt_name != null)
    {
        // Set the encrypted value to 0
        $stmt = $db->prepare("UPDATE `encrypted_fields` SET encrypted=0 WHERE mcrypt_name=:mcrypt_name;");
        $stmt->bindParam(":mcrypt_name", $mcrypt_name, PDO::PARAM_STR);
        $stmt->execute();
    }
    // If the table and field are not null
    else if ($table != null && $field != null)
    {
        // Set the encrypted value to 0
        $stmt = $db->prepare("UPDATE `encrypted_fields` SET encrypted=0 WHERE table=:table AND field=:field;");
        $stmt->bindParam(":table", $table, PDO::PARAM_STR);
        $stmt->bindParam(":field", $field, PDO::PARAM_STR);
        $stmt->execute();
    }

    // Close the database connection
    db_close($db);
}

/********************************
 * FUNCTION: IS FIELD ENCRYPTED *
 ********************************/
function is_field_encrypted($mcrypt_name = null, $table = null, $field = null)
{
    write_debug_log("Checking if field is encrypted: " . $mcrypt_name . " - " . $table . " - " . $field);
    
    if(!table_exists("encrypted_fields"))
    {
        // Return false
        return array("encrypted" => false, "method" => false);
    }
    
    // Open the database connection
    $db = db_open();

    // If the table and field are not null
    if ($table != null && $field != null)
    {
        // Get the encrypted and method values
        $stmt = $db->prepare("SELECT encrypted, method FROM `encrypted_fields` WHERE `table`=:table AND `field`=:field;");
        $stmt->bindParam(":table", $table, PDO::PARAM_STR);
        $stmt->bindParam(":field", $field, PDO::PARAM_STR);
        $stmt->execute();
    }
    // If the mcrypt name is not null
    else if ($mcrypt_name != null)
    {
        // Get the encrypted and method values
        $stmt = $db->prepare("SELECT encrypted, method FROM `encrypted_fields` WHERE mcrypt_name=:mcrypt_name;");
        $stmt->bindParam(":mcrypt_name", $mcrypt_name, PDO::PARAM_STR);
        $stmt->execute();
    }
    // If all are null
    else if ($mcrypt_name == null && $table == null && $field == null)
    {
        // Close the database connection
        db_close($db);

        // Return false
        return array("encrypted" => false, "method" => false);
    }

    // Get the encrypted and method values
    $encrypted_fields = $stmt->fetch();

    $encrypted  = !empty($encrypted_fields['encrypted']) ? $encrypted_fields['encrypted'] : false;
    $method     = !empty($encrypted_fields['method']) ? $encrypted_fields['method'] : false;

    write_debug_log("Encrypted: " . $encrypted);
    write_debug_log("Method: " . $method);

    // Close the database connection
    db_close($db);

    // If the field is encrypted
    if ($encrypted == 1)
    {
        // Return the method
        return array("encrypted" => true, "method" => $method);
    }
    // Otherwise the field is not encrypted
    else return array("encrypted" => false, "method" => $method);;
}

/*************************
 * FUNCTION: GENERATE IV *
 *************************/
function generate_iv()
{
        $efforts = 0;
        $maxEfforts = 50;
        $wasItSecure = false;

        // Calculate the initialization vector length
        $ivlen = openssl_cipher_iv_length(OPENSSL_CIPHER);

        do
        {
                $efforts += 1;

                // Generate an initialization vector
                $iv = openssl_random_pseudo_bytes($ivlen, $wasItSecure);

                if ($efforts == $maxEfforts)
                {
                        throw new Exception('Unable to generate secure iv.');
                        break;
                }
        } while (!$wasItSecure);

        // Return the IV
        return $iv;
}

/********************************************
 * FUNCTION: CONVERT FROM MCRYPT TO OPENSSL *
 ********************************************/
function convert_from_mcrypt_to_openssl()
{
    // Open the database connection
    $db = db_open();

    // Get the list of encrypted fields
    $stmt = $db->prepare("SELECT `table`, `field` FROM `encrypted_fields` WHERE encrypted=1 AND method='mcrypt';");
    $stmt->execute();
    $encrypted_fields = $stmt->fetchAll();

    // Foreach of the encrypted fields
    foreach ($encrypted_fields as $encrypted_field)
    {
        $table = $encrypted_field['table'];
        $field = $encrypted_field['field'];

        // Get the table
        $stmt = $db->prepare("SELECT ${field} FROM ${table};");
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // For each row in the table
        foreach ($rows as $row)
        {
            // Get the mcrypt encrypted string
            $mcrypt_encrypted_text = $row[$field];

            // Decrypt the string
            $decrypted_text = trim(decrypt($_SESSION['encrypted_pass'], $mcrypt_encrypted_text));

            // Encrypt the string with openssl
            $openssl_encrypted_text = encrypt_with_openssl($_SESSION['encrypted_pass'], $decrypted_text);

            // Update the mcrypt value in the database with the openssl value
            $stmt = $db->prepare("UPDATE ${table} SET ${field}=:openssl_encrypted_text WHERE ${field}=:mcrypt_encrypted_text;");
            $stmt->bindParam(":mcrypt_encrypted_text", $mcrypt_encrypted_text, PDO::PARAM_STR);
            $stmt->bindParam(":openssl_encrypted_text", $openssl_encrypted_text, PDO::PARAM_STR);
            $stmt->execute();
        }

        // Update the encrypted fieds to show method as openssl
        $stmt = $db->prepare("UPDATE `encrypted_fields` SET method='openssl' WHERE `table`=:table AND `field`=:field;");
        $stmt->bindParam(":table", $table, PDO::PARAM_STR);
        $stmt->bindParam(":field", $field, PDO::PARAM_STR);
        $stmt->execute();
    }

    // Change the encryption method to openssl in the settings table
    $stmt = $db->prepare("UPDATE settings SET value='openssl' WHERE name='encryption_method';");
    $stmt->execute();

    // Set the session encryption method to openssl
    $_SESSION['encryption_method'] = "openssl";

    // Delete the old settings
    delete_setting("enc_assessment_contact_company");
    delete_setting("enc_assessment_contact_details");
    delete_setting("enc_assessment_contact_email");
    delete_setting("enc_assessment_contact_name");
    delete_setting("enc_assessment_contact_phone");
    delete_setting("enc_assets_details");
    delete_setting("enc_assets_name");
    delete_setting("enc_audit_log_message");
    delete_setting("enc_comments_comment");
    delete_setting("enc_frameworks_description");
    delete_setting("enc_frameworks_name");
    delete_setting("enc_mgmt_reviews_comment");
    delete_setting("enc_mitigations_current_solution");
    delete_setting("enc_mitigations_security_recommendations");
    delete_setting("enc_mitigations_security_requirements");
    delete_setting("enc_projects_name");
    delete_setting("enc_questionnaire_responses_additional_infromation");
    delete_setting("enc_questionnaire_responses_answer");
    delete_setting("enc_risks_assessment");
    delete_setting("enc_risks_notes");
    delete_setting("enc_risks_subject");

    // Close the database connection
    db_close($db);
}

/********************************
 * FUNCTION: DELETE BACKUP FILE *
 ********************************/
function delete_backup_file()
{
    global $lang, $escaper;

    // Delete the backup file
    if (@unlink(sys_get_temp_dir() . "/" . get_setting('unencrypted_backup_file_name')) !== true)
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['ErrorDeletingFile']));
    }
    else {
        set_alert(true, "good", $escaper->escapeHtml($lang['FileDeletedSuccessfully']));
        delete_setting('unencrypted_backup_file_name');
    }
}

/******************************************
 * FUNCTION: REVERT TO UNENCRYPTED BACKUP *
 ******************************************/
function revert_to_unencrypted_backup()
{
    global $lang, $escaper;

    $fileName = get_setting('unencrypted_backup_file_name');

    // If an unencrypted backup exists
    if ($fileName && file_exists(sys_get_temp_dir() . "/" . $fileName)) {

        $fileName = sys_get_temp_dir() . "/" . $fileName;

        // Sanitize the mysql command
        $cmd = "mysql --user=" . escapeshellarg(DB_USERNAME) . " --password=" . escapeshellarg(DB_PASSWORD) . " -h " . escapeshellarg(DB_HOSTNAME) . " " . escapeshellarg(DB_DATABASE) . " < " . $fileName;

        // Revert to the unencrypted backup
        system($cmd, $retval);

        // Check if everything went ok
        if ($retval == 0)
        {
            set_alert(true, "good", $escaper->escapeHtml($lang['SuccessfullyRevertedToUnencryptedBackup']));
        }
        else {
            set_alert(true, "bad", $escaper->escapeHtml($lang['FailedToRevertToUnencryptedBackup']));
        }
    }
}

/************************************
 * FUNCTION: ENCRYPTED ASSET EXISTS *
 ************************************/
function encrypted_asset_exists($name)
{
    // Open the database connection
    $db = db_open();

    // Get all assets from the database
    $stmt = $db->prepare("SELECT * FROM `assets`;");
    $stmt->execute();
    $assets = $stmt->fetchAll();

    // For each asset
    foreach ($assets as $asset)
    {
        // Decrypt the name of the asset
        $asset_name = try_decrypt($asset['name']);

        // If the name of the asset matches
        if ($asset_name == $name)
        {
            write_debug_log("Asset was found");
            return $asset['id'];
        }
    }

    // If no asset was found
    write_debug_log("Asset was not found");
    return false;
}

/*******************************************
 * FUNCTION: ENCRYPTED ASSET EXISTS (EXACT)*
 *******************************************/
function encrypted_asset_exists_exact($ip, $name, $value, $location, $teams, $details, $verified)
{
    // Open the database connection
    $db = db_open();

    // Get all assets from the database
    $stmt = $db->prepare("SELECT * FROM `assets`;");
    $stmt->execute();
    $assets = $stmt->fetchAll();

    // For each asset
    foreach ($assets as $asset)
    {
        // If the values of the asset matches
        // Using short circuit evaluation to save time on multiple decryptings
        if (try_decrypt($asset['name']) == $name
            && try_decrypt($asset['details']) == $details
            && try_decrypt($asset['ip']) == $ip
            && $asset['value'] == $value && $asset['location'] == $location
            && $asset['teams'] == $teams && $asset['verified'] == $verified)
        {
            write_debug_log("Asset was found");
            return true;
        }
    }

    // If no asset was found
    write_debug_log("Asset was not found");
    return false;
}

/********************************************
 * FUNCTION: ENCRYPTION GET RISK BY SUBJECT *
 ********************************************/
function encryption_get_risk_by_subject($subject)
{
    // Open the database connection
    $db = db_open();

    // Get all risks from the database
    $stmt = $db->prepare("SELECT * FROM `risks`;");
    $stmt->execute();
    $risks = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    // For each risk
    foreach ($risks as $risk)
    {
        // If the risk subject matches
        if (try_decrypt($risk['subject']) == $subject)
        {
            // Return the risk id
            return $risk['id'];
        }
    }

        // Return false
        return false;
}

/********************************************
 * FUNCTION: ENCRYPT VALUE FOR CUSTOM EXTRA *
 ********************************************/
function encrypt_custom_value($custom_field, $custom_value, $password = NULL)
{
    if($custom_field['encryption'] == "1")
    {
        if($custom_field['type'] == "shorttext" || $custom_field['type'] == "longtext")
        {
            if($password === NULL)
            {
                $custom_value = try_encrypt($custom_value);
            }
            else
            {
                $custom_value = encrypt($password, $custom_value);
            }
        }
    }
    
    return $custom_value;
}

/********************************************
 * FUNCTION: DECRYPT VALUE FOR CUSTOM EXTRA *
 ********************************************/
function decrypt_custom_value($custom_field, $custom_value, $password = NULL)
{
    if($custom_field['encryption'] == "1")
    {
        if($custom_field['type'] == "shorttext" || $custom_field['type'] == "longtext")
        {
            if($password === NULL)
            {
                $custom_value = try_decrypt($custom_value);
            }
            else
            {
                $custom_value = decrypt($password, $custom_value);
            }
        }
    }
    
    return $custom_value;
}

/***************************************
 * FUNCTION: ENCRYPT CUSTOM FIELD DATA *
 ***************************************/
function encrypt_custom_field_data($field_id)
{
    $field = get_field_by_id($field_id);
    
    if(($field['type'] == "shorttext" || $field['type'] == "longtext"))
    {
        // Open the database connection
        $db = db_open();

        if($field['fgroup'] == "risk")
        {
            // Get all of the custom risk data by field_id
            $stmt = $db->prepare("SELECT * FROM `custom_risk_data` WHERE field_id=:field_id;");
            $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        }
        elseif($field['fgroup'] == "asset")
        {
            // Get all of the custom asset data by field_id
            $stmt = $db->prepare("SELECT * FROM `custom_asset_data` WHERE field_id=:field_id;");
            $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        }
        
        foreach($rows as $row)
        {
            $custom_value = try_encrypt($row['value']);
            $id = $row['id'];
            
            if($field['fgroup'] == "risk")
            {
                $stmt = $db->prepare("UPDATE `custom_risk_data` SET value=:custom_value WHERE id=:id;");
                $stmt->bindParam(":custom_value", $custom_value);
                $stmt->bindParam(":id", $id);
                $stmt->execute();
            }
            elseif($field['fgroup'] == "asset")
            {
                $stmt = $db->prepare("UPDATE `custom_asset_data` SET value=:custom_value WHERE id=:id;");
                $stmt->bindParam(":custom_value", $custom_value);
                $stmt->bindParam(":id", $id);
                $stmt->execute();
            }
        }
        
    }
}

/***************************************
 * FUNCTION: DECRYPT CUSTOM FIELD DATA *
 ***************************************/
function decrypt_custom_field_data($field_id)
{
    $field = get_field_by_id($field_id);
    
    if(($field['type'] == "shorttext" || $field['type'] == "longtext"))
    {
        // Open the database connection
        $db = db_open();

        if($field['fgroup'] == "risk")
        {
            // Get all of the custom risk data by field_id
            $stmt = $db->prepare("SELECT * FROM `custom_risk_data` WHERE field_id=:field_id;");
            $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        }
        elseif($field['fgroup'] == "asset")
        {
            // Get all of the custom asset data by field_id
            $stmt = $db->prepare("SELECT * FROM `custom_asset_data` WHERE field_id=:field_id;");
            $stmt->bindParam(":field_id", $field_id, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        }
        
        foreach($rows as $row)
        {
            $custom_value = try_decrypt($row['value']);
            $id = $row['id'];
            
            if($field['fgroup'] == "risk")
            {
                $stmt = $db->prepare("UPDATE `custom_risk_data` SET value=:custom_value WHERE id=:id;");
                $stmt->bindParam(":custom_value", $custom_value);
                $stmt->bindParam(":id", $id);
                $stmt->execute();
            }
            elseif($field['fgroup'] == "asset")
            {
                $stmt = $db->prepare("UPDATE `custom_asset_data` SET value=:custom_value WHERE id=:id;");
                $stmt->bindParam(":custom_value", $custom_value);
                $stmt->bindParam(":id", $id);
                $stmt->execute();
            }
        }
        
    }
}

?>

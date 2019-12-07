<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to add   *
 * users who use LDAP credentials for authentication as well as the *
 * ability to add a second factor of authentication with Duo        *
 * Security.
 ********************************************************************/

// Extra Version
define('AUTHENTICATION_EXTRA_VERSION', '20191130-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));
require_once(realpath(__DIR__ . '/duo_php/duo_web.php'));
require_once(realpath(__DIR__ . '/toopher-php/lib/toopher_api.php'));
require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_authentication_extra_database();

// If the user wants to test ldap
if (isset($_POST['test_ldap_configuration']))
{
    // Test LDAP
    test_ldap_configuration();
}

/*****************************************
 * FUNCTION: ENABLE AUTHENTICATION EXTRA *
 *****************************************/
function enable_authentication_extra()
{
    prevent_extra_double_submit("custom_authentication", true);

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'custom_auth', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
    $stmt->execute();

    // Add default values
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'TRUSTED_DOMAINS', `value` = 'sts.windows.net, login.windows.net, dev.simplerisk.com'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'BIND_FIRST', `value` = 'false'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'BIND_ACCOUNT', `value` = 'CN=username,OU=Users,DC=Company,DC=Corp,DC=Domain,DC=COM'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'BIND_ACCOUNT_PASS', `value` = ''");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'TLS', `value` = 'false'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'SASL', `value` = 'false'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'LDAP_VERSION', `value` = 'false'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CHASE_REFERRALS', `value` = '3'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'LDAPHOST', `value` = 'yourldaphost.yourdomain.com'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'LDAPPORT', `value` = '389'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'GROUPDN', `value` = 'OU=Users,DC=Company,DC=Corp,DC=Domain,DC=COM'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'LDAP_GROUP_ATTRIBUTE', `value` = 'cn'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'LDAP_MEMBER_ATTRIBUTE', `value` = 'uniquemember'; ");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'USERDN', `value` = 'OU=Users,DC=Company,DC=Corp,DC=Domain,DC=COM'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTHENTICATION_LDAP_USER_ATTRIBUTE', `value` = 'dn'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'IKEY', `value` = ''");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'SKEY', `value` = ''");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'HOST', `value` = ''");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CONSUMERKEY', `value` = ''");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'CONSUMERSECRET', `value` = ''");
    $stmt->execute();
    $stmt = $db->prepare("DELETE FROM `settings` WHERE `name` = 'IDP'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'USERNAME_ATTRIBUTE', `value` = 'uid'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'SAML_METADATA_URL', `value` = 'https://your.saml.provider.com/sso/saml/metadata'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'SAML_METADATA_XML', `value` = ''");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'SAML_USERNAME_MATCH', `value` = 'attribute'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'GO_TO_SSO_LOGIN', `value` = '1'");
    $stmt->execute();
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'AUTHENTICATION_ADD_NEW_USERS', `value` = '0'");
    $stmt->execute();

    // Import an existing configuration file and remove it
    import_and_remove_authentication_config_file();
    
    // Audit log entry for Extra turned on
    $message = "Custom Authentication Extra was toggled on by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);

    // Create a Duo Auth application secret key
    create_duo_akey();

    // Add simplesamlphp log directory
    add_simplesamlphp_log_dir();
}

/******************************************
 * FUNCTION: DISABLE AUTHENTICATION EXTRA *
 ******************************************/
function disable_authentication_extra()
{
    prevent_extra_double_submit("custom_authentication", false);

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'custom_auth'");
    $stmt->execute();

    // Audit log entry for Extra turned off
    $message = "Custom Authentication Extra was toggled off by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);
}

/*****************************
 * FUNCTION: UPDATE SETTINGS *
 *****************************/
if (!function_exists('update_settings')) {
function update_settings($configs)
{
    // Open the database connection
    $db = db_open();

    // If the TRUSTED_DOMAINS value is not empty
    if ($configs['TRUSTED_DOMAINS'] != "")
    {
        // Update the BIND_FIRST value
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'TRUSTED_DOMAINS'");
        $stmt->bindParam(":value", $configs['TRUSTED_DOMAINS']);
        $stmt->execute();
    }

    // If the BIND_FIRST value is not empty
    if ($configs['BIND_FIRST'] != "")
    {
        // Update the BIND_FIRST value
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'BIND_FIRST'");
        $stmt->bindParam(":value", $configs['BIND_FIRST']);
        $stmt->execute();
    }

    // If the BIND_ACCOUNT value is not empty
    if ($configs['BIND_ACCOUNT'] != "")
    {
        // Update the BIND_ACCOUNT value
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'BIND_ACCOUNT'");
        $stmt->bindParam(":value", $configs['BIND_ACCOUNT']);
        $stmt->execute();
    }

    // If the BIND_ACCOUNT_PASS value is not empty
    if ($configs['BIND_ACCOUNT_PASS'] != "")
    {
        // Update the BIND_ACCOUNT_PASS value
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'BIND_ACCOUNT_PASS'");
        $stmt->bindParam(":value", $configs['BIND_ACCOUNT_PASS']);
        $stmt->execute();
    }

        // If the TLS value is not empty
    if ($configs['TLS'] != "")
    {
        // Update the TLS value
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'TLS'");
        $stmt->bindParam(":value", $configs['TLS']);
        $stmt->execute();
    }

    // If the SASL value is not empty
    if ($configs['SASL'] != "")
    {
        // Update the SASL value
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'SASL'");
        $stmt->bindParam(":value", $configs['SASL']);
        $stmt->execute();
    }

    // If the LDAP VERSION is not empty
    if ($configs['LDAP_VERSION'] != "")
    {
        // Update the LDAP VERSION
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'LDAP_VERSION'");
        $stmt->bindParam(":value", $configs['LDAP_VERSION']);
        $stmt->execute();
    }

    // If CHASE REFERRALS is not empty
    if ($configs['CHASE_REFERRALS'] != "")
    {
        // Update CHASE REFERRALS
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CHASE_REFERRALS'");
        $stmt->bindParam(":value", $configs['CHASE_REFERRALS']);
        $stmt->execute();
    }

    // If the LDAP HOST is not empty
    if ($configs['LDAPHOST'] != "")
    {
        // Update the LDAP HOST
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'LDAPHOST'");
        $stmt->bindParam(":value", $configs['LDAPHOST']);
        $stmt->execute();
    }

    // If the LDAP PORT is not empty
    if (isset($configs['LDAPPORT']))
    {
        if($configs['LDAPPORT'])
        {
          // Update the LDAP PORT
          $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'LDAPPORT'");
          $stmt->bindParam(":value", $configs['LDAPPORT']);
          $stmt->execute();
        }
        else
        {
          // Update the LDAP PORT
          $stmt = $db->prepare("UPDATE `settings` SET `value` = NULL WHERE `name` = 'LDAPPORT'");
          $stmt->execute();
        }
    }

    // If the GROUPDN is not empty
    if ($configs['GROUPDN'] != "")
    {
        // Update the GROUPDN
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'GROUPDN'");
        $stmt->bindParam(":value", $configs['GROUPDN']);
        $stmt->execute();
    }

    // If the LDAP_GROUP_ATTRIBUTE is not empty
    if ($configs['LDAP_GROUP_ATTRIBUTE'] != "")
    {
        // Update the LDAP_GROUP_ATTRIBUTE
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'LDAP_GROUP_ATTRIBUTE'; ");
        $stmt->bindParam(":value", $configs['LDAP_GROUP_ATTRIBUTE']);
        $stmt->execute();
    }

    // If the LDAP_MEMBER_ATTRIBUTE is not empty
    if ($configs['LDAP_MEMBER_ATTRIBUTE'] != "")
    {
        // Update the LDAP_MEMBER_ATTRIBUTE
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'LDAP_MEMBER_ATTRIBUTE'; ");
        $stmt->bindParam(":value", $configs['LDAP_MEMBER_ATTRIBUTE']);
        $stmt->execute();
    }

    // If the USERDN is not empty
    if ($configs['USERDN'] != "")
    {
        // Update the USERDN
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'USERDN'");
        $stmt->bindParam(":value", $configs['USERDN']);
        $stmt->execute();
    }

    // If the AUTHENTICATION_LDAP_USER_ATTRIBUTE is dn or email
    if ($configs['AUTHENTICATION_LDAP_USER_ATTRIBUTE'] == "dn" || $configs['AUTHENTICATION_LDAP_USER_ATTRIBUTE'] == "email")
    {
        // Update the AUTHENTICATION_LDAP_USER_ATTRIBUTE
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'AUTHENTICATION_LDAP_USER_ATTRIBUTE'");
        $stmt->bindParam(":value", $configs['AUTHENTICATION_LDAP_USER_ATTRIBUTE']);
        $stmt->execute();
    }

    // If the IKEY is not empty
    if ($configs['IKEY'] != "")
    {
        // Update the IKEY
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'IKEY'");
        $stmt->bindParam(":value", $configs['IKEY']);
        $stmt->execute();
    }

    // If the SKEY is not empty
    if ($configs['SKEY'] != "")
    {
        // Update the SKEY
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'SKEY'");
        $stmt->bindParam(":value", $configs['SKEY']);
        $stmt->execute();
    }

    // If the HOST is not empty
    if ($configs['HOST'] != "")
    {
        // Update the HOST
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'HOST'");
        $stmt->bindParam(":value", $configs['HOST']);
        $stmt->execute();
    }

    // If the CONSUMERKEY is not empty
    if ($configs['CONSUMERKEY'] != "")
    {
        // Update the CONSUMERKEY
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CONSUMERKEY'");
        $stmt->bindParam(":value", $configs['CONSUMERKEY']);
        $stmt->execute();
    }

    // If the CONSUMERSECRET is not empty
    if ($configs['CONSUMERSECRET'] != "")
    {
        // Update the CONSUMERSECRET
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'CONSUMERSECRET'");
        $stmt->bindParam(":value", $configs['CONSUMERSECRET']);
        $stmt->execute();
    }

    // If the USERNAME_ATTRIBUTE is not empty
    if ($configs['USERNAME_ATTRIBUTE'] != "")
    {
        // Update the USERNAME_ATTRIBUTE
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'USERNAME_ATTRIBUTE'");
        $stmt->bindParam(":value", $configs['USERNAME_ATTRIBUTE']);
        $stmt->execute();
    }

    // If the SAML_METADATA_URL is not empty
    if (isset($configs['SAML_METADATA_URL']))
    {
        // Update the SAML_METADATA_URL
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'SAML_METADATA_URL'");
        $stmt->bindParam(":value", $configs['SAML_METADATA_URL']);
        $stmt->execute();
    }

    // If the SAML_METADATA_XML is not empty
    if (isset($configs['SAML_METADATA_XML']))
    {
        // Update the SAML_METADATA_XML
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'SAML_METADATA_XML'");
        $stmt->bindParam(":value", $configs['SAML_METADATA_XML']);
        $stmt->execute();
    }

    // If the SAML_USERNAME_MATCH is not empty
    if ($configs['SAML_USERNAME_MATCH'] != "")
    {
        // Update the SAML_USERNAME_MATCH
        $stmt = $db->prepare("UPDATE `settings` SET `value` = :value WHERE `name` = 'SAML_USERNAME_MATCH'");
        $stmt->bindParam(":value", $configs['SAML_USERNAME_MATCH']);
        $stmt->execute();
    }

    // If the GO_TO_SSO_LOGIN is not empty
    if (isset($configs['GO_TO_SSO_LOGIN']))
    {
        // Update the GO_TO_SSO_LOGIN
        update_or_insert_setting("GO_TO_SSO_LOGIN", $configs['GO_TO_SSO_LOGIN']);
    }

    // If the AUTHENTICATION_ADD_NEW_USERS is not empty
    if (isset($configs['AUTHENTICATION_ADD_NEW_USERS']))
    {
        // Update the AUTHENTICATION_ADD_NEW_USERS
        update_or_insert_setting("AUTHENTICATION_ADD_NEW_USERS", $configs['AUTHENTICATION_ADD_NEW_USERS']);
    }

    // If the LDAP_FILTER_FOR_GROUP is not empty
    if (isset($configs['LDAP_FILTER_FOR_GROUP']))
    {
        // Update the LDAP_FILTER_FOR_GROUP
        update_setting("LDAP_FILTER_FOR_GROUP", $configs['LDAP_FILTER_FOR_GROUP']);
    }

    // If the LDAP_MANAGER_ATTRIBUTE is not empty
    if (isset($configs['LDAP_MANAGER_ATTRIBUTE']))
    {
        // Update the LDAP_MANAGER_ATTRIBUTE
        update_or_insert_setting("LDAP_MANAGER_ATTRIBUTE", $configs['LDAP_MANAGER_ATTRIBUTE']);
    }

    // If the AUTHENTICATION_ADD_NEW_MANAGER is not empty
    if (isset($configs['AUTHENTICATION_ADD_NEW_MANAGER']))
    {
        // Update the AUTHENTICATION_ADD_NEW_MANAGER
        update_or_insert_setting("AUTHENTICATION_ADD_NEW_MANAGER", $configs['AUTHENTICATION_ADD_NEW_MANAGER']);
    }

    // Close the database connection
    db_close($db);

    // Return true;
    return true;
}
}

/*****************************************
 * FUNCTION: GET AUTHENTICATION SETTINGS *
 *****************************************/
function get_authentication_settings()
{
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT * FROM `settings` WHERE `name` = 'TRUSTED_DOMAINS' OR `name` = 'BIND_FIRST' OR `name` = 'BIND_ACCOUNT' OR `name` = 'BIND_ACCOUNT_PASS' OR `name` = 'TLS' OR `name` = 'SASL' OR `name` = 'LDAP_VERSION' OR `name` = 'CHASE_REFERRALS' OR `name` = 'LDAPHOST' OR `name` = 'LDAPPORT' OR `name` = 'GROUPDN' OR `name` = 'LDAP_GROUP_ATTRIBUTE' OR `name` = 'LDAP_MEMBER_ATTRIBUTE' OR `name` = 'USERDN' OR `name` = 'AUTHENTICATION_LDAP_USER_ATTRIBUTE' OR `name` = 'IKEY' OR `name` = 'SKEY' OR `name` = 'HOST' OR `name` = 'CONSUMERKEY' OR `name` = 'CONSUMERSECRET' OR `name` = 'USERNAME_ATTRIBUTE' OR `name` = 'SAML_METADATA_URL' OR `name` = 'SAML_METADATA_XML' OR `name` = 'SAML_USERNAME_MATCH' OR `name` = 'GO_TO_SSO_LOGIN' OR `name` = 'AUTHENTICATION_ADD_NEW_USERS' OR `name` = 'LDAP_FILTER_FOR_GROUP' OR `name` = 'LDAP_MANAGER_ATTRIBUTE' OR `name` = 'AUTHENTICATION_ADD_NEW_MANAGER'; ");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array;
}

/******************************
 * FUNCTION: IS VALID AD USER *
 ******************************/
function is_valid_ad_user($user, $pass)
{
    // Do not allow blank passwords or the server may think we are performing an anonymous simple bind
    if ($pass == "") return false;

    // Get the authentication settings
    $configs = get_authentication_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we need to use the BIND account first to authenticate
    if ($BIND_FIRST == "true")
    {
        // Use the BIND account to authenticate with LDAP and then authenticate the user/pass
        list($ldapbind, $dn, $attributes) = bind_authentication($user, $pass, $configs);
    }
    // Otherwise
    else
    {
        // Authenticate the user by attemping to bind with just the provided user/pass
        list($ldapbind, $dn, $attributes) = direct_authentication($user, $pass, $configs);
    }

    // If we are bound to the LDAP server
    if ($ldapbind)
    {
        // Write the debug log
        write_debug_log("The user has been bound to the LDAP server.");

        // Return that it is a valid user
        return array(true, $dn, $attributes);
    }
    // Otherwise, it's not a valid user
    else
    {
        // Write the debug log
        write_debug_log("Unable to bind the user to the LDAP server.");

        // Return that it is not a valid user
        return array(false, $dn, $attributes);
    }
}

/*********************************
 * FUNCTION: BIND AUTHENTICATION *
 *********************************/
function bind_authentication($user, $pass, $configs)
{
    global $lang, $escaper;
    // Write the debug log
    write_debug_log("Using a BIND account to authenticate with LDAP.");

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Default is not bound
    $ldapbind = false;

    // Connect to the LDAP server
    $ds = ldap_connect($LDAPHOST, $LDAPPORT)
        or die ("Could not connect to LDAP server.");

    // Set the LDAP protocol version
    write_debug_log("Using LDAP protocol version " . $LDAP_VERSION);
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $LDAP_VERSION);

    // Set whether to chase referrals
    write_debug_log("Setting chase referrals to " . $CHASE_REFERRALS);
    ldap_set_option($ds, LDAP_OPT_REFERRALS, $CHASE_REFERRALS);

    // If we should use TLS
    if ($TLS == "true")
    {
        // Start TLS
        write_debug_log("Starting TLS.");
        ldap_start_tls($ds);
    }

    // If SASL is enabled
    if ($SASL == "true")
    {
        // Bind to the LDAP server
        write_debug_log("Performing a LDAP SASL bind.");
        $bind = ldap_sasl_bind($ds, $BIND_ACCOUNT, $BIND_ACCOUNT_PASS, 'DIGEST-MD5');
    }
    // Otherwise
    else
    {
        // Bind to the LDAP server
        write_debug_log("Performing a LDAP bind.");
        $bind = @ldap_bind($ds, $BIND_ACCOUNT, $BIND_ACCOUNT_PASS);
    }
    
    $dn = "";
    
    // Attribute array for login user
    $attributes = array();

    // If we were able to bind using the BIND account
    if ($bind)
    {
        // Set the sAMAccountName we are looking for
        $samaccountname = "sAMAccountName=".$user;
        $filter = "(&(objectCategory=Person)($samaccountname))";

//        $filter = "(uid={$user})";

        // Write the debug log
        write_debug_log("The BIND was successful.");
        write_debug_log("Running a search for " . $samaccountname);

        // Search LDAP for the user
        $result = @ldap_search($ds, $USERDN, $filter);
        if(!$result){
            set_alert(true, "bad", $escaper->escapeHtml($lang['ErrorInSearchQuery']) .": ". ldap_error($ds));
            refresh();
        }

        // If the user was found
        if ($result != false)
        {
            // Write the debug log
            write_debug_log("The user was found.");

            // Get the entries for that result
            $data = ldap_get_entries($ds, $result);

            // Write the debug log
            write_debug_log("Obtained the following values for the user:\n" . print_r($data, true));

            // If we found an entry then we have the full path to the user
            if ($data['count'] > 0)
            {
                // If the username attribute is dn
                if ($AUTHENTICATION_LDAP_USER_ATTRIBUTE == "dn")
                {
                    // Get the user dn
                    $bind_user = $data[0]['dn'];
                }
                // If the username attribute is email
                else if ($AUTHENTICATION_LDAP_USER_ATTRIBUTE == "email")
                {
                    // Get the user email
                    $bind_user = $data[0]['email'];
                }
                
                $dn = $data[0]['dn'];
                
                if(!empty($data[0]['mail'])){
                    $attributes['ldap_login_email'] = $data[0]['mail'][0];
                }

                if(!empty($data[0]['displayname'])){
                    $attributes['ldap_login_displayname'] = $data[0]['displayname'][0];
                }

                $attributes['manager'] = isset($data[0][$LDAP_MANAGER_ATTRIBUTE][0]) ? $data[0][$LDAP_MANAGER_ATTRIBUTE][0] : "";

                // Write the debug log
                write_debug_log("Closing the current connection to the LDAP server and opening a new one.");

                // Close the connections to the LDAP server
                ldap_close($ds);

                // Default is not bound
                $ldapbind = false;

                // Connect to the LDAP server
                $ds = ldap_connect($LDAPHOST, $LDAPPORT)
                    or die ("Could not connect to LDAP server.");

                // Set the LDAP protocol version
                write_debug_log("Using LDAP protocol version " . $LDAP_VERSION);
                ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $LDAP_VERSION);

                // Set whether to chase referrals
                write_debug_log("Setting chase referrals to " . $CHASE_REFERRALS);
                ldap_set_option($ds, LDAP_OPT_REFERRALS, $CHASE_REFERRALS);

                // Write the debug log
                write_debug_log("Attempting to BIND using \"" . $bind_user . "\".");

                // If we should use TLS
                if ($TLS == "true")
                {       
                    // Start TLS
                    write_debug_log("Starting TLS.");
                    ldap_start_tls($ds);
                }
                

                // If SASL is enabled
                if ($SASL == "true")
                {
                    // Try to bind with the newly found user and password
                    write_debug_log("Performing a LDAP SASL bind.");
                    $ldapbind = @ldap_sasl_bind($ds, $bind_user, $pass, 'DIGEST-MD5');
                }
                // Otherwise
                else
                {
                    // Try to bind with the newly found user and password
                    write_debug_log("Performing a LDAP bind.");
                    $ldapbind = @ldap_bind($ds, $bind_user, $pass);
                }
            }
        }
        // Otherwise, the user was not found
        else
        {
            // Write the debug log
            write_debug_log("The user was not found.");
        }
    }
    // Otherwise, unable to bind with the bind account
    else
    {
        // Write the debug log
        write_debug_log("Unable to bind using the bind account.");
    }

    // Close the connections to the LDAP server
    ldap_close($ds);

    // Return whether or not we were bound to ldap
    return array($ldapbind, $dn, $attributes);
}

/***********************************
 * FUNCTION: DIRECT AUTHENTICATION *
 ***********************************/
function direct_authentication($user, $pass, $configs)
{
    global $lang, $escaper;
    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Default is not bound
    $ldapbind = false;

    // Connect to the LDAP server
    $ds = ldap_connect($LDAPHOST, $LDAPPORT)
            or die ("Could not connect to LDAP server.");

    // Set the LDAP protocol version
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $LDAP_VERSION);

    // Set whether to chase referrals
    ldap_set_option($ds, LDAP_OPT_REFERRALS, $CHASE_REFERRALS);

    // If we should use TLS
    if ($TLS == "true")
    {
        // Start TLS
        ldap_start_tls($ds);
    }

    // Get the user DN
    $dn = "CN=" . $user . "," . $USERDN;

    // If SASL is enabled
    if ($SASL == "true")
    {
        $ldapbind = @ldap_sasl_bind($ds, $dn, $pass, 'DIGEST-MD5', NULL, $user);
    }
    // Otherwise
    else
    {
        // Bind to the LDAP server
        $ldapbind = @ldap_bind($ds, $dn, $pass);
    }
    
    $attributes = array();

    if($ldapbind)
    {
        // Search LDAP for the user
        $samaccountname = "sAMAccountName=".$user;
        $filter = "(&(objectCategory=Person)($samaccountname))";

        // Write the debug log
        write_debug_log("The BIND was successful.");
        write_debug_log("Running a search for " . $samaccountname);

        // Search LDAP for the user
        $result = @ldap_search($ds, $USERDN, $filter);
        if(!$result){
            set_alert(true, "bad", $escaper->escapeHtml($lang['ErrorInSearchQuery']). ": " . ldap_error($ds));
            refresh();
        }

        // If the user was found
        if ($result != false)
        {
            // Write the debug log
            write_debug_log("The user was found.");

            // Get the entries for that result
            $data = ldap_get_entries($ds, $result);

            // Write the debug log
            write_debug_log("Obtained the following values for the user:\n" . print_r($data, true));
 
            // If we found an entry then we have the full path to the user
            if ($data['count'] > 0)
            {
                // Get the email            
                if(!empty($data[0]['mail'])){
                    write_debug_log("Email: " . $data[0]['mail'][0]);
                    $attributes['ldap_login_email'] = $data[0]['mail'][0];
                }

                // Get the displayname
                if(!empty($data[0]['displayname'])){
                    write_debug_log("Name: " . $data[0]['displayname'][0]);
                    $attributes['ldap_login_displayname'] = $data[0]['mail'][0];
                }
                
                $attributes['manager'] = isset($data[0][$LDAP_MANAGER_ATTRIBUTE][0]) ? $data[0][$LDAP_MANAGER_ATTRIBUTE][0] : "";
            }
        }
    }

    // Close the connection to the LDAP server
    ldap_close($ds);
    
    // Return whether or not we were bound to ldap
    return array($ldapbind, $dn, $attributes);
}

/*****************************
 * FUNCTION: CREATE DUO AKEY *
 *****************************/
function create_duo_akey()
{
    $akey = generate_token(40);

    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name`='duo_akey', `value`= :akey");
        $stmt->bindParam(":akey", $akey, PDO::PARAM_STR, 40);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/**************************
 * FUNCTION: GET DUO AKEY *
 **************************/
function get_duo_akey()
{
    // Open the database connection
    $db = db_open();

    // Query the database
    $stmt = $db->prepare("SELECT value FROM `settings` WHERE `name`='duo_akey'");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array[0]['value'];
}

/*************************************************
 * FUNCTION: MULTI FACTOR AUTHENTICATION OPTIONS *
 *************************************************/
function multi_factor_authentication_options($current_value)
{
    global $escaper;
    global $lang;

    echo "<input id=\"duo\"  type=\"radio\" name=\"multi_factor\" value=\"2\"" . ($current_value == 2 ? ' checked' : '') . " />&nbsp;Duo Security<br />\n";
    echo "<!--\n";
    echo "<input id=\"toopher\"  type=\"radio\" name=\"multi_factor\" value=\"3\"" . ($current_value == 3 ? ' checked' : '') . " />&nbsp;Toopher<br />\n";
    echo "-->\n";
    echo "<br />\n";
/*
    echo "<input class=\"hidden-radio\" id=\"duo\" type=\"radio\" name=\"multi_factor\" value=\"2\"";
    if ($current_value == 2) echo " checked";
    echo " /><label for=\"duo\">&nbsp;Duo Security</label><br />\n";
    echo "<!--\n";
    echo "<input class=\"hidden-radio\" id=\"toopher\" type=\"radio\" name=\"multi_factor\" value=\"3\"";
    if ($current_value == 3) echo " checked";
    echo " /><label for=\"toopher\">&nbsp;Toopher</label><br />\n";
    echo "-->\n";
*/
}

/**************************
 * FUNCTION: ENABLED AUTH *
 **************************/
function enabled_auth($username)
{
    // Open the database connection
    $db = db_open();

    // If strict user validation is disabled
    if (get_setting('strict_user_validation') == 0)
    {
    // Query the database
    $stmt = $db->prepare("SELECT multi_factor FROM user WHERE LOWER(convert(`username` using utf8)) = LOWER(:username)");
    }
    else
    {
    // Query the database
    $stmt = $db->prepare("SELECT multi_factor FROM user WHERE `username`= :username");
    }

    $stmt->bindParam(":username", $username, PDO::PARAM_STR, 200);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetchAll();

    // Close the database connection
    db_close($db);

    return $array[0]['multi_factor'];
}

/********************************
 * FUNCTION: DUO AUTHENTICATION *
 ********************************/
function duo_authentication($username)
{
    // Get the authentication settings
    $configs = get_authentication_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    //generate sig request and then load up Duo javascript and iframe
    $sig_request = Duo\Web::signRequest($IKEY, $SKEY, get_duo_akey(), $username);

    echo "<script src=\"extras/authentication/duo_php/js/Duo-Web-v2.js\"></script>\n";
    echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"extras/authentication/duo_php/css/Duo-Frame.css\">\n";
    echo "<iframe id=\"duo_iframe\"
        data-host=\"" . $HOST . "\"
        data-sig-request=\"" . $sig_request . "\"
    ></iframe>\n";
}

/************************************
 * FUNCTION: TOOPHER AUTHENTICATION *
 ************************************/
function toopher_authentication($username)
{
        // Get the authentication settings
        $configs = get_authentication_settings();

        // For each configuration
        foreach ($configs as $config)
        {
                // Set the name value pair as a variable
                ${$config['name']} = $config['value'];
        }

    $toopher = new ToopherAPI($CONSUMERKEY, $CONSUMERSECRET);
}

/******************************************
 * FUNCTION: UPDATE AUTHENTICATION CONFIG *
 ******************************************/
function update_authentication_config()
{
    $configs['TRUSTED_DOMAINS'] = isset($_POST['trusted_domains']) ? $_POST['trusted_domains'] : '';
    $configs['BIND_FIRST'] = isset($_POST['bind_first']) ? 'true' : 'false';
    $configs['BIND_ACCOUNT'] = $_POST['bind_account'];
    $configs['BIND_ACCOUNT_PASS'] = $_POST['bind_account_pass'];
    $configs['TLS'] = isset($_POST['tls']) ? 'true' : 'false';
    $configs['SASL'] = isset($_POST['sasl']) ? 'true' : 'false';
    $configs['CHASE_REFERRALS'] = isset($_POST['chase_referrals']) ? '1' : '0';
    $configs['LDAP_VERSION'] = (int)$_POST['ldap_version'];
    $configs['LDAPHOST'] = $_POST['ldap_host'];
    $configs['LDAPPORT'] = (int)$_POST['ldap_port'];
    $configs['GROUPDN'] = $_POST['groupdn'];
    $configs['LDAP_GROUP_ATTRIBUTE'] = $_POST['ldap_group_attribute'];
    $configs['LDAP_MEMBER_ATTRIBUTE'] = $_POST['ldap_member_attribute'];
    
    $configs['LDAP_FILTER_FOR_GROUP'] = $_POST['ldap_filter_for_group'];
    $configs['LDAP_MANAGER_ATTRIBUTE'] = $_POST['ldap_manager_attribute'];
    
    $configs['USERDN'] = $_POST['userdn'];
    $configs['AUTHENTICATION_LDAP_USER_ATTRIBUTE'] = $_POST['ldap_user_attribute'];
    $configs['IKEY'] = $_POST['ikey'];
    $configs['SKEY'] = $_POST['skey'];
    $configs['HOST'] = $_POST['host'];
    $configs['CONSUMERKEY'] = $_POST['consumer_key'];
    $configs['CONSUMERSECRET'] = $_POST['consumer_secret'];
    $configs['USERNAME_ATTRIBUTE'] = $_POST['username_attribute'];
    $configs['SAML_METADATA_URL'] = $_POST['saml_metadata_url'];
    $configs['SAML_METADATA_XML'] = $_POST['saml_metadata_xml'];
    $configs['GO_TO_SSO_LOGIN'] = empty($_POST['go_to_sso_login']) ? 0 : 1;
    $configs['AUTHENTICATION_ADD_NEW_USERS'] = empty($_POST['add_new_users']) ? 0 : 1;
    $configs['AUTHENTICATION_ADD_NEW_MANAGER'] =  empty($_POST['add_new_manager']) ? 0: 1;

    // If there is a file uploading.
    if(isset($_FILES['saml_metadata_file']) && $_FILES['saml_metadata_file']['size'])
    {
        $file = $_FILES['saml_metadata_file'];
        $contents = file_get_contents($file['tmp_name']);
        $doc = @simplexml_load_string($contents);
        if($doc)
        {
            $configs['SAML_METADATA_XML'] = $contents;
        }
    }
    
    $configs['SAML_USERNAME_MATCH'] = $_POST['username_match'];

    // Update the settings
    update_settings($configs);
}

/*******************************************
 * FUNCTION: CUSTOM AUTHENTICATION VERSION *
 *******************************************/
function custom_authentication_version()
{
    // Return the version
    return AUTHENTICATION_EXTRA_VERSION;
}

/************************************
 * FUNCTION: DISPLAY AUTHENTICATION *
 ************************************/
function display_authentication()
{
  global $escaper;
  global $lang;

  echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . custom_authentication_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";

    // Get the authentication settings
    $configs = get_authentication_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    echo "<script>\n";
    echo "  function checkbox_bind_first()\n";
    echo "  {\n";
    echo "    elements = document.getElementsByClassName(\"bind_first\");\n";
    echo "    checkbox = document.getElementById(\"bind_first\");\n";
    echo "    if(checkbox.checked)\n";
    echo "    {\n";
    echo "      for(i=0; i<elements.length; i++)\n";
    echo "      {\n";
    echo "        elements[i].style.display = \"\";\n";
    echo "      }\n";
    echo "    }\n";
    echo "    else\n";
    echo "    {\n";
    echo "      for(i=0; i<elements.length; i++)\n";
    echo "      {\n";
    echo "        elements[i].style.display = \"none\";\n";
    echo "      }\n";
    echo "    }\n";
    echo "  }\n";
        echo "  function checkbox_username_match()\n";
        echo "  {\n";
        echo "    var radios = document.getElementsByName(\"username_match\");\n";
    echo "    var attribute_tr = document.getElementsByClassName(\"username_attribute\");\n";
    echo "    for (var i = 0, length = radios.length; i < length; i++) {\n";
    echo "      if (radios[i].checked) {\n";
    echo "        if (radios[i].value == 'attribute') {\n";
    echo "          attribute_tr[0].style.display = \"\";\n";
    echo "        } else attribute_tr[0].style.display = \"none\";\n";
    echo "        break;\n";
    echo "      }\n";
    echo "    }\n";
        echo "  }\n";
    echo "</script>\n";
    
    echo "<form name=\"authentication_extra\" enctype=\"multipart/form-data\" method=\"post\" action=\"\">\n";
        echo "<table border=\"1\" width=\"800\" cellpadding=\"10px\">\n";
            echo "<tr><td>\n";
                echo "<table border=\"0\" width=\"100%\">\n";
            echo "<tr><td colspan=\"2\"><u><strong>" . $escaper->escapeHtml($lang['Settings']) . "</strong></u></tr>\n";
            echo "<tr>\n";
            echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"go_to_sso_login\" " . ($GO_TO_SSO_LOGIN ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['GoToSSOLogin']) . "</td>\n";
            echo "</tr>";
    echo "<tr>\n";
    echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"add_new_users\" " . ($AUTHENTICATION_ADD_NEW_USERS ? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['AutomaticallyAddNewlyAuthenticatedUsersWithDefaultRole']) . "</td>\n";
    echo "</tr>";
    echo "<tr>\n";
    echo "<td colspan=\"2\"><input type=\"checkbox\" name=\"add_new_manager\" " . ($AUTHENTICATION_ADD_NEW_MANAGER? " checked=\"yes\"" : "") . " />&nbsp;&nbsp;" . $escaper->escapeHtml($lang['AutomaticallyAddNewUserForManagerIfTheyNoExist']) . "</td>\n";
    echo "</tr>";
                echo "<tr><td colspan=\"2\">\n";
                    echo "<div class=\"form-actions\">\n";
                        echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
                    echo "</div>\n";
                echo "</td></tr>\n";
                echo "</table>";
            echo "</td></tr>\n";
        echo "</table>\n";
        echo "<br>";
        echo "<table border=\"1\" width=\"800\" cellpadding=\"10px\">\n";
            echo "<tr><td>\n";
            echo "<table border=\"0\" width=\"100%\">\n";
            echo "<tr>\n";
            echo "<td colspan=\"2\"><u><strong>LDAP</strong></u><span style=\"float: right;\"><button type=\"submit\" name=\"test_ldap_configuration\" class=\"btn btn-primary\">TEST LDAP CONFIGURATION</button></span></td>\n";
            echo "</tr>\n";
          echo "<tr>\n";
            echo "<td width='170px'>BIND FIRST:</td>\n";
            echo "<td><input type=\"checkbox\" name=\"bind_first\" id=\"bind_first\"" . ($BIND_FIRST == "true" ? " checked=\"yes\"" : "") . " onchange=\"javascript: checkbox_bind_first()\" /></td>\n";
            echo "</tr>\n";
            echo "<tr class=\"bind_first\"" . ($BIND_FIRST == "false" ? " style=\"display: none;\"" : "") . ">\n";
            echo "<td>BIND ACCOUNT:</td>\n";
            echo "<td><input type=\"text\" name=\"bind_account\" value=\"" . $escaper->escapeHtml($BIND_ACCOUNT) . "\" /></td>\n";
            echo "</tr>\n";
          echo "<tr class=\"bind_first\"" . ($BIND_FIRST == "false" ? " style=\"display: none;\"" : "") . ">\n";
          echo "<td>BIND ACCOUNT PASS:</td>\n";
          echo "<td><input type=\"password\" name=\"bind_account_pass\" value=\"\" placeholder=\"Change Current Value\" /></td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>TLS:</td>\n";
          echo "<td><input type=\"checkbox\" name=\"tls\" id=\"tls\"" . ($TLS == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>SASL:</td>\n";
          echo "<td><input type=\"checkbox\" name=\"sasl\" id=\"sasl\"" . ($SASL == "true" ? " checked=\"yes\"" : "") . " /></td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>CHASE REFERRALS:</td>\n";
          echo "<td><input type=\"checkbox\" name=\"chase_referrals\" id=\"chase_referrals\"" . ($CHASE_REFERRALS == "1" ? " checked=\"yes\"" : "") . " /></td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>LDAP VERSION:</td>\n";
          echo "<td>\n";
          echo "<select name=\"ldap_version\" id=\"ldap_version\">\n";
          echo "<option value=\"3\"" . ($LDAP_VERSION == "3" ? " selected" : "") . ">3</option>\n";
          echo "<option value=\"2\"" . ($LDAP_VERSION == "2" ? " selected" : "") . ">2</option>\n";
          echo "</select>\n";
          echo "</td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>LDAP HOST:</td>\n";
          echo "<td><input type=\"text\" name=\"ldap_host\" value=\"" . $escaper->escapeHtml($LDAPHOST) . "\" /></td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>LDAP PORT:</td>\n";
          echo "<td><input type=\"text\" name=\"ldap_port\" value=\"" . $escaper->escapeHtml($LDAPPORT) . "\" /></td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>USER DN:</td>\n";
          echo "<td><input type=\"text\" name=\"userdn\" value=\"" . $escaper->escapeHtml($USERDN) . "\" /></td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>USERNAME ATTRIBUTE:</td>\n";
          echo "<td>\n";
          echo "<select name=\"ldap_user_attribute\" id=\"ldap_user_attribute\">\n";
          echo "<option value=\"dn\"" . ($AUTHENTICATION_LDAP_USER_ATTRIBUTE == "dn" ? " selected" : "") . ">Distinguished Name (DN)</option>\n";
          echo "<option value=\"email\"" . ($AUTHENTICATION_LDAP_USER_ATTRIBUTE == "email" ? " selected" : "") . ">Email</option>\n";
          echo "</select>\n";
          echo "</td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>GROUP DN:</td>\n";
          echo "<td><input type=\"text\" name=\"groupdn\" value=\"" . $escaper->escapeHtml($GROUPDN) . "\" /></td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>GROUP ATTRIBUTE:</td>\n";
          echo "<td><input type=\"text\" name=\"ldap_group_attribute\" value=\"" . $escaper->escapeHtml($LDAP_GROUP_ATTRIBUTE) . "\" /></td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>MEMBER ATTRIBUTE:</td>\n";
          echo "<td><input type=\"text\" name=\"ldap_member_attribute\" value=\"" . $escaper->escapeHtml($LDAP_MEMBER_ATTRIBUTE) . "\" /></td>\n";
          echo "</tr>\n";
          // Filter for group
          echo "<tr>\n";
          echo "<td>".$escaper->escapeHtml($lang['FitlerForGroup']).":</td>\n";
          echo "<td>
                <textarea name=\"ldap_filter_for_group\" class=\"form-control\" style=\"width: 100%\">{$LDAP_FILTER_FOR_GROUP}</textarea>
          \n";
          echo "</tr>\n";
          // 
          echo "<tr>\n";
          echo "<td>".$escaper->escapeHtml($lang['ManagerAttribute']).":</td>\n";
          echo "<td><input type=\"text\" name=\"ldap_manager_attribute\" value=\"" . $escaper->escapeHtml($LDAP_MANAGER_ATTRIBUTE) . "\" /></td>\n";
          echo "</tr>\n";
        
          echo "<tr><td colspan=\"2\">\n";
          echo "<div class=\"form-actions\">\n";
          echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
          echo "</div>\n";
        echo "</td></tr>\n";

      echo "<tr>\n";
        echo "<td colspan='2'>
            <table width=\"100%\">
                <tr>
                    <td colspan='3'>&nbsp;</td>
                </tr>
                <tr>
                    <td width=\"170px\">". $escaper->escapeHtml($lang['Team']) .":</td>
                    <td>";
                        ldap_team_dropdown("ldap_team");
                    echo "</td>
                    <td valign='top' align='center'>&nbsp;</td>
                </tr>
                <tr>
                    <td colspan=\"2\">
                        <a class=\"btn\" role=\"button\" data-toggle=\"modal\" id=\"map-to-ldap-group-modal-btn\" href=\"#map-to-ldap-group-modal\">".$escaper->escapeHtml($lang['MapToLDAPGroup'])."</a>
                        <!-- MODEL WINDOW FOR MAP TO LDAP GROUP -->
                        <div id=\"map-to-ldap-group-modal\" class=\"modal hide fade\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"map-to-ldap-group-modal\" aria-hidden=\"true\">
                          <div class=\"modal-body\">
                            <form class=\"\" action=\"#\" method=\"post\" autocomplete=\"off\">
                                <table width=\"100%\" >
                                  <tr class=\"form-group\">
                                    <td width=\"150px\"><label>".$escaper->escapeHtml($lang['Team']).":</label></td>
                                    <td>
                                        <span class=\"team_name view-field\"></span>
                                        <input class=\"team_value\" type=\"hidden\" name=\"ldap_team\">
                                    </td>
                                  </tr>
                                  <tr class=\"form-group\">
                                    <td><label>".$escaper->escapeHtml($lang['LdapGroup']).":</label></td>
                                    <td id=\"ldap_group_dropdown_container\">
                                        <select id='ldap_group' name='ldap_group'><option>--</option></select>
                                    </td>
                                  </tr>
                                  <tr>
                                    <td colspan=\"2\">
                                        &nbsp;
                                    </td>
                                  </tr>
                                  <tr class=\"form-group text-right\">
                                    <td colspan=\"2\">
                                        <button class=\"btn btn-default\" data-dismiss=\"modal\" aria-hidden=\"true\">".$escaper->escapeHtml($lang['Cancel'])."</button>
                                        <button type=\"submit\" name=\"map_ldap_group_and_team\" class=\"btn btn-danger\">".$escaper->escapeHtml($lang['Add'])."</button>
                                    </td>
                                  </tr>
                                <table>
                            </form>
                            <script>
                                $(document).ready(function(){
                                    $('#map-to-ldap-group-modal-btn').click(function(e){
                                        if($('#ldap_team').val()){
                                            $(\"#map-to-ldap-group-modal #ldap_group\").prop('disabled', true);
                                            $(\"#map-to-ldap-group-modal .team_name\").html($('#ldap_team option:selected').text());
                                            $.ajax({
                                                url: BASE_URL + '/api/authentication/ldap_group_dropdown',
                                                type: 'GET',
                                                success : function (res){
                                                    $(\"#map-to-ldap-group-modal .team_value\").val($('#ldap_team').val());
                                                    $(\"#map-to-ldap-group-modal #ldap_group_dropdown_container\").html(res.data.ldap_group);
                                                    $(\"#map-to-ldap-group-modal #ldap_group\").prop('disabled', false);
                                                }
                                            });
                                        }else{
                                            e.preventDefault();
                                            alert('".$escaper->escapeHtml($lang['SelectMappingTeam'])."')
                                            return false;
                                        }
                                    })
                                })
                            </script>
                          </div>
                        </div>

                    </td>
                </tr>
                
            </table>
        </td>";
      echo "</tr>\n";
      
      echo "<tr>\n";
        echo "<td colspan='2'>
            <table width=\"100%\">
                <tr>
                    <td colspan='3'>&nbsp;</td>
                </tr>
                <tr>
                    <td>". $escaper->escapeHtml($lang['ExistingMappings']) ."<br>". $escaper->escapeHtml($lang['TeamGroup']) ."</td>
                    <td>";
                        existing_ldap_group_team_dropdown("existing_mappings");
                    echo "</td>
                    <td valign='top' align='center'>&nbsp;</td>
                </tr>
                <tr>
                    <td width='170px'>
                        <button class=\"btn\" id=\"delete_existing_mappings\" name=\"delete_existing_mappings\">". $escaper->escapeHtml($lang['Delete']) ."</button>
                    </td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            </table>
        </td>";
      echo "</tr>\n";
      

        echo "</table>\n";
        echo "</td></tr>\n";
        echo "</table>\n";

        echo "<br />\n";

        echo "<table border=\"1\" width=\"800\" cellpadding=\"10px\">\n";
            echo "<tr><td>\n";
            echo "<table border=\"0\" width=\"100%\">\n";
          echo "<tr>\n";
          echo "<td colspan=\"2\"><u><strong>SAML</strong></u></td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
              echo "<td>".$lang['TrustedDomains'].":</td>\n";
              echo "<td><input name=\"trusted_domains\" value=\"" . (isset($TRUSTED_DOMAINS) ? $escaper->escapeHtml($TRUSTED_DOMAINS) : "") . "\" type=\"text\"></td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>METADATA URL:</td>\n";
          echo "<td><input type=\"text\" name=\"saml_metadata_url\" value=\"" . $escaper->escapeHtml($SAML_METADATA_URL) . "\" /></td>\n";
          echo "<tr>\n";
          echo "<td>METADATA XML:</td>\n";
          echo "<td><textarea class=\"saml_metadata_xml\" name=\"saml_metadata_xml\">". $escaper->escapeHtml($SAML_METADATA_XML) ."</textarea></td>\n";
          echo "</tr>\n";
          echo "<tr>\n";
          echo "<td>&nbsp;</td>\n";
          echo "<td><input type=\"file\" name=\"saml_metadata_file\"></td>\n";
          echo "</tr>\n";
        
            echo "<tr>\n";
            echo "<td>USERNAME MATCH:</td>\n";
            echo "<td><input type=\"radio\" name=\"username_match\" id=\"username_match\"" . ($SAML_USERNAME_MATCH == "username" ? " checked=\"checked\"" : "") . " value=\"username\" onchange=\"javascript: checkbox_username_match()\" />&nbsp;Authenticated Username&nbsp;&nbsp;<input type=\"radio\" name=\"username_match\" id=\"username_match\"" . ($SAML_USERNAME_MATCH == "attribute" ? " checked=\"checked\"" : "") . " value=\"attribute\" onchange=\"javascript: checkbox_username_match()\" />&nbsp;Authenticated Attribute</td>\n";
            echo "</tr>\n";
            echo "<tr class=\"username_attribute\"". ($SAML_USERNAME_MATCH == "username" ? " style=\"display: none;\"" : "") . ">\n";
            echo "<td>USERNAME ATTRIBUTE:</td>\n";
            echo "<td><input type=\"text\" name=\"username_attribute\" value=\"" . $escaper->escapeHtml($USERNAME_ATTRIBUTE) . "\" /></td>\n";
            echo "</tr>\n";
            echo "<tr><td colspan=\"2\">\n";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
            echo "</div>\n";
            echo "</td></tr>\n";
            echo "</table>\n";
    echo "</td></tr>\n";
    echo "</table>\n";

    echo "<br />\n";

        echo "<table border=\"1\" width=\"800\" cellpadding=\"10px\">\n";
        echo "<tr><td>\n";
        echo "<table border=\"0\" width=\"100%\">\n";
            echo "<tr>\n";
            echo "<td colspan=\"2\"><u><strong>Duo Security</strong></u></td>\n";
            echo "</tr>\n";
            echo "<tr>\n";
            echo "<td>IKEY:</td>\n";
            echo "<td><input type=\"text\" name=\"ikey\" value=\"" . $escaper->escapeHtml($IKEY) . "\" /></td>\n";
            echo "</tr>\n";
            echo "<tr>\n";
            echo "<td>SKEY:</td>\n";
            echo "<td><input type=\"password\" name=\"skey\" value=\"\" placeholder=\"Change Current Value\" /></td>\n";
            echo "</tr>\n";
            echo "<tr>\n";
            echo "<td>HOST:</td>\n";
            echo "<td><input type=\"text\" name=\"host\" value=\"" . $escaper->escapeHtml($HOST) . "\" /></td>\n";
            echo "</tr>\n";
            echo "<tr><td colspan=\"2\">\n";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
            echo "</div>\n";
            echo "</td></tr>\n";
            echo "</table>\n";
            echo "</td></tr>\n";
            echo "</table>\n";

            echo "<br />\n";

            echo "<table border=\"1\" width=\"800\" cellpadding=\"10px\">\n";
            echo "<tr><td>\n";
            echo "<table border=\"0\" width=\"100%\">\n";
            echo "<tr>\n";
            echo "<td colspan=\"2\"><u><strong>Toopher</strong></u></td>\n";
            echo "</tr>\n";
            echo "<tr>\n";
            echo "<td>CONSUMER KEY:</td>\n";
            echo "<td><input type=\"text\" name=\"consumer_key\" value=\"" . $escaper->escapeHtml($CONSUMERKEY) . "\" /></td>\n";
            echo "</tr>\n";
            echo "<tr>\n";
            echo "<td>CONSUMER SECRET:</td>\n";
            echo "<td><input type=\"password\" name=\"consumer_secret\" value=\"\" placeholder=\"Change Current Value\" /></td>\n";
            echo "</tr>\n";
            echo "<tr><td colspan=\"2\">\n";
            echo "<div class=\"form-actions\">\n";
            echo "<button type=\"submit\" name=\"submit\" class=\"btn btn-primary\">" . $escaper->escapeHtml($lang['Update']) . "</button>\n";
            echo "</div>\n";
            echo "</td></tr>\n";
            echo "</table>\n";
        echo "</td></tr>\n";
        echo "</table>\n";
    echo "</form>\n";
    
    display_authentication_script();
}

/*******************************************
 * FUNCTION: DISPLAY AUTHENTICATION SCRIPT *
 *******************************************/
function display_authentication_script()
{
    echo "
        <script>
            $(document).ready(function(){
                $('#map_ldap_group_and_team').click(function(e){
                    if($('#ldap_team').val() && $('#ldap_group').val()){
                        return true;
                    }else{
                        return false;
                    }
                })
                $('#delete_existing_mappings').click(function(e){
                    if($('#existing_mappings').val()){
                        return true;
                    }else{
                        return false;
                    }
                })
            })
        </script>
    ";
}

/*******************************************
 * FUNCTION: GET TEAMS BY LDAP GROUP VALUE *
 *******************************************/
function getTeamsByLdapGroup($name)
{
    // Open the database connection
    $db = db_open();

    // Get the SAML metadata URL
    $stmt = $db->prepare("SELECT team_ids FROM `ldap_groups` WHERE `name` = :name; ");
    $stmt->bindParam(":name", $name);
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch(PDO::FETCH_COLUMN);

    // Close the database connection
    db_close($db);
    
    return $array ? explode(",", $array) : [];
}

/*************************************
 * FUNCTION: SET LDAP TEAM AND GROUP *
 *************************************/
function setLdapTeamAndGroup($group_name, $team_id)
{
    // Open the database connection
    $db = db_open();

    // Delete existing ldap group
    $stmt = $db->prepare("DELETE FROM `ldap_group_and_teams` WHERE group_name=:group_name OR team_id=:team_id; ");
    $stmt->bindParam(":group_name", $group_name, PDO::PARAM_STR);
    $stmt->bindParam(":team_id", $team_id, PDO::PARAM_INT);
    $stmt->execute();

    // Map ldap group and simplerisk team
    $stmt = $db->prepare("INSERT INTO `ldap_group_and_teams`(team_id, group_name) VALUES(:team_id, :group_name); ");
    $stmt->bindParam(":team_id", $team_id, PDO::PARAM_INT);
    $stmt->bindParam(":group_name", $group_name, PDO::PARAM_STR);
    $stmt->execute();

    // Close the database connection
    db_close($db);
    
    return ;
}

/*******************************************
 * FUNCTION: DELETE EXISTING LDAP MAPPINGS *
 *******************************************/
function deleteLdapGroupAndTeamByValue($value)
{
    // Open the database connection
    $db = db_open();

    // Delete existing ldap group
    $stmt = $db->prepare("DELETE FROM `ldap_group_and_teams` WHERE value=:value; ");
    $stmt->bindParam(":value", $value, PDO::PARAM_INT);
    $stmt->execute();

    // Close the database connection
    db_close($db);
}

/******************************
 * FUNCTION: READ CONFIG FILE *
 ******************************/
if (!function_exists('read_config_file')) {
function read_config_file()
{
        // Location of the configuration file
        $config_file = realpath(__DIR__ . '/includes/config.php');

        // Open the file for reading
        $handle = fopen($config_file, 'r');

        // If we can read the file
        if ($handle)
        {
                // Create a configuration array
                $config_array = array();

                // Read each line in the file
                while ($line = fgets($handle))
                {
                        // If the line begins with define
                        if (preg_match('/^define\(\'*\'*/', $line))
                        {
                                // Grab the parameter and value
                                preg_match('/\((.*?)\,(.*?)\)/s', $line, $matches);
                                $param_name = $matches[1];
                                $param_value = $matches[2];

                                // Remove any double quotes
                                $param_name = str_replace('"', "", $param_name);
                                $param_value = str_replace('"', "", $param_value);

                                // Remove any single quotes
                                $param_name = str_replace('\'', "", $param_name);
                                $param_value = str_replace('\'', "", $param_value);

                                // Remove any spaces
                                $param_name = str_replace(' ', "", $param_name);
                                $param_value = str_replace(' ', "", $param_value);

                                $config_array[$param_name] = $param_value;
                        }
                }

                // Close the file
                fclose($handle);

                // Return the configuration array
                return $config_array;
        }
        else
        {
                // Return an error
                return 0;
        }
}
}

/**********************************************************
 * FUNCTION: IMPORT AND REMOVE AUTHENTICATION CONFIG FILE *
 **********************************************************/
function import_and_remove_authentication_config_file()
{
        global $escaper;

        // Location of the configuration file
        $config_file = realpath(__DIR__ . '/includes/config.php');

        // If a configuration file exists
        if (file_exists($config_file))
        {
                // Read the configuration file
                $configs = read_config_file();

                // Update the configuration in the settings table
                if (update_settings($configs))
                {
                        // Remove the configuration file
                        if (!delete_file($config_file))
                        {
                // Display an alert
                set_alert(true, "bad", "ERROR: Could not remove " . $config_file);
                // Get any alert messages
                //get_alert();
                        }
                }
        }
}

/********************************
 * FUNCTION: IS VALID SAML USER *
 ********************************/
function is_valid_saml_user($user)
{
    // Load the SimpleSAMLphp autoloader
    require_once(realpath(__DIR__ . '/simplesamlphp/lib/_autoload.php'));

    // Select the default authentication source
    $as = new SimpleSAML_Auth_Simple('default-sp');

    // Require authentication
    $as->requireAuth();

    // If no authentication took place
    if (!$as->isAuthenticated())
    {
                // Write the debug log
                write_debug_log("SAML user not authenticated.");

        return false;
    }
    // Otherwise
    else
    {
        // Write the debug log
        write_debug_log("SAML user authenticated.");

            // Get the authentication settings
            $configs = get_authentication_settings();

            // For each configuration
            foreach ($configs as $config)
            {
                    // Set the name value pair as a variable
                    ${$config['name']} = $config['value'];
            }

        // Get the attributes
        $attributes = $as->getAttributes();
        $attribute_value = $attributes[$USERNAME_ATTRIBUTE];

        // Get the name the user authenticated with
        $saml_username = $as->getAuthData('saml:sp:NameID');
        $saml_username = $saml_username[0];

                // Write the debug log
        write_debug_log("Username Attribute: " . $USERNAME_ATTRIBUTE);
                write_debug_log("Attribute Value: " . $attribute_value);
                write_debug_log("Authentication Username: " . $saml_username);
                write_debug_log("Username to Match: " . $user);

        // If we are supposed to authenticate the username
        if ($SAML_USERNAME_MATCH == "username")
        {
            // If the saml username is the same as the username
            if (strict_user_validation($saml_username) == strict_user_validation($user))
            {
                return true;
            }
            else return false;
        }
        // If we are supposed to authenticate the attribute
        else if ($SAML_USERNAME_MATCH == "attribute")
        {
            // If the username attribute is the same as the username
            if (strict_user_validation($attribute_value) == strict_user_validation($user))
            {
                return true;
            }
            else return false;
        }
        else return false;
    }
}

/*************************
 * FUNCTION: SAML LOGOUT *
 *************************/
function saml_logout()
{
        // Load the SimpleSAMLphp autoloader
        require_once(realpath(__DIR__ . '/simplesamlphp/lib/_autoload.php'));

        // Select the default authentication source
        $as = new SimpleSAML_Auth_Simple('default-sp');

    // Log the user out
    $as->logout();
}

/***********************************
 * FUNCTION: GET SAML METADATA URL *
 ***********************************/
function get_saml_metadata_url()
{
    // Open the database connection
    $db = db_open();

    // Get the SAML metadata URL
    $stmt = $db->prepare("SELECT value FROM `settings` WHERE `name` = 'SAML_METADATA_URL'");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // Create the metadata_url_for array
    $metadata_url_for = array('default-sp' => $array['value']);

    // Return the metadata URL
    return $metadata_url_for;
}

/***********************************
 * FUNCTION: GET SAML METADATA XML *
 ***********************************/
function get_saml_metadata_xml(){
    // Open the database connection
    $db = db_open();

    // Get the SAML metadata URL
    $stmt = $db->prepare("SELECT value FROM `settings` WHERE `name` = 'SAML_METADATA_XML'");
    $stmt->execute();

    // Store the list in the array
    $array = $stmt->fetch();

    // Close the database connection
    db_close($db);

    // Create the metadata_url_for array
    $metadata_xml_for = array('default-sp' => $array['value']);

    // Return the metadata URL
    return $metadata_xml_for;
}

/***************************************
 * FUNCTION: ADD SIMPLESAMLPHP LOG DIR *
 ***************************************/
function add_simplesamlphp_log_dir()
{
    // Set the path to simplesamlphp directory
    $simplesamlphp_dir = realpath(__DIR__ . "/simplesamlphp/");

    // If the simplesamlphp directory exists and is writeable
    if (is_writeable($simplesamlphp_dir))
    {
        // Set the path to the simplesamlphp log directory
        $simplesamlphp_log_dir = $simplesamlphp_dir . "/log/";

        // If the log directory doesn't already exist
        if (!is_writeable($simplesamlphp_log_dir))
        {
            // Add the simplesamlphp log directory
            return mkdir($simplesamlphp_log_dir);
        }
        else return false;
    }
    else return false;
}

/*************************************
 * FUNCTION: TEST LDAP CONFIGURATION *
 *************************************/
function test_ldap_configuration()
{
    global $escaper;

    // Get the authentication settings
    $configs = get_authentication_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // If we need to use the BIND account first to authenticate
    if ($BIND_FIRST == "true")
    {
        write_debug_log("Bind first is enabled.");

        // Default is not bound
        $ldapbind = false;

        write_debug_log("LDAP HOST is set to \"" . $escaper->escapeHtml($LDAPHOST) . "\".");
        write_debug_log("This field supports using a hostname or, with OpenLDAP 2.x.x and later, a full LDAP URI of the form ldap://hostname:port or ldaps://hostname:port for SSL encryption.");
write_debug_log("Note that hostname:port is not a supported LDAP URI as the schema is missing.");

        write_debug_log("LDAP PORT is set to \"" . $escaper->escapeHtml($LDAPPORT) . "\".");
        write_debug_log("Note that this should not be set when using LDAP URIs.");

        write_debug_log("Checking that the provided hostname/port combination or LDAP URI seems plausible.");

        // Check that the LDAP host/port is plausible
        $ds = ldap_connect($LDAPHOST, $LDAPPORT);

        // If the ldap_connect is not plausible
        if (!$ds)
        {
            write_debug_log("There is an issue with the configured LDAP hostname or port.");
            set_alert(true, "bad", "There was an issue with the configured LDAP hostname or port.");
        }
        // The ldap_connect looks plausible
        else
        {
            write_debug_log("The provided hostname/port combination or LDAP URI seems plausible.");
            write_debug_log("Setting LDAP connection options.");

            // Set the LDAP protocol version
            write_debug_log("Setting the LDAP protocol version to \"" . $escaper->escapeHtml($LDAP_VERSION) . "\".");
            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $LDAP_VERSION);

            // Set whether to chase referrals
            write_debug_log("Setting the LDAP chase referrals to \"" . $escaper->escapeHtml($CHASE_REFERRALS) . "\".");
            ldap_set_option($ds, LDAP_OPT_REFERRALS, $CHASE_REFERRALS);

            // If we should use TLS
            if ($TLS == "true")
            {
                write_debug_log("Attempting to start a TLS connection for LDAP.");
                $success = ldap_start_tls($ds);

                // If the TLS start was not successful
                if (!$success)
                {
                    write_debug_log("Starting a TLS connection for LDAP was not successful.");
                    set_alert(true, "bad", "Starting a TLS connection for LDAP was not successful.");
                }
                else
                {
                    write_debug_log("Starting a TLS connection for LDAP was successful.");
                }
            }

            // If SASL is enabled
            if ($SASL == "true")
            {
                // Bind to the LDAP server
                write_debug_log("Attempting to bind to the LDAP server using SASL.");
                write_debug_log("Using bind account \"" . $escaper->escapeHtml($BIND_ACCOUNT) . "\".");
                $bind = ldap_sasl_bind($ds, $BIND_ACCOUNT, $BIND_ACCOUNT_PASS, 'DIGEST-MD5');

                // If the bind was not successful
                if (!$bind)
                {
                    write_debug_log("The bind to the LDAP server using SALS was not successful.");
                    write_debug_log("Are you sure that PHP was compiled with \"with-ldap-sasl\"?");
                    write_debug_log("Also, please verify that you are using LDAP version 3.");
                    set_alert(true, "bad", "The bind to the LDAP server using SALS was not successful.");
                }
                else
                {
                    write_debug_log("The bind to the LDAP server using SALS was successful.");
                    set_alert(true, "good", "The bind to the LDAP server using SALS was successful.");
                }
            }
            // Otherwise
            else
            {
                // Bind to the LDAP server
                write_debug_log("Attempting to bind to the LDAP server.");
                write_debug_log("Using bind account \"" . $escaper->escapeHtml($BIND_ACCOUNT) . "\".");
                $bind = @ldap_bind($ds, $BIND_ACCOUNT, $BIND_ACCOUNT_PASS);

                // If the bind was not successful
                if (!$bind)
                {
                    write_debug_log("The bind to the LDAP server was not successful.");
                    set_alert(true, "bad", "The bind to the LDAP server was not successful.");
                }
                else
                {
                    write_debug_log("The bind to the LDAP server was successful.");
                    set_alert(true, "good", "The bind to the LDAP server was successful.");
                }
            }
        }
    }
    // Otherwise, we are authenticating using direct authentication
    else
    {
        write_debug_log("Unable to test the LDAP configuration with direct authentication.");
        set_alert(true, "bad", "Unable to test the LDAP configuration with direct authentication.");
    }
}

/*****************************************
 * FUNCTION: AUTHENTICATION ADD NEW USER *
 *****************************************/
function authentication_add_new_user($type, $username, $attributes=[])
{
    // Write the debug log
    write_debug_log("Adding the new user with a default role.");

    // Generate a salt for the user
    $salt = generate_token(20);

    // Hash the salt
    $salt_hash = '$2a$15$' . md5($salt);

    // Generate the password hash
    $hash = generateHash($salt_hash, "");

    // Get the default user role
    $default_user_role = get_setting("default_user_role");
    
    // If the default role is false
    if ($default_user_role == false)
    {
        // Set the values to empty defaults
        $team = "";
        $role_id = 0;
        $governance = 0;
        $riskmanagement = 0;
        $compliance = 0;
        $assessments = 0;
        $asset = 0;
        $admin = 0;
        $review_veryhigh = 0;
        $accept_mitigation = 0;
        $review_high = 0;
        $review_medium = 0;
        $review_low = 0;
        $review_insignificant = 0;
        $submit_risks = 0;
        $modify_risks = 0;
        $plan_mitigations = 0;
        $close_risks = 0;
        $multi_factor = 1;
        $change_password = 0;
        $add_new_frameworks = 0;
        $modify_frameworks = 0;
        $delete_frameworks = 0;
        $add_new_controls = 0;
        $modify_controls = 0;
        $delete_controls = 0;
        $other_options = [
            "add_documentation" => 0,
            "modify_documentation" => 0,
            "delete_documentation" => 0,
        ];
    }
    // Otherwise, if there is a default role specified
    else
    {
        // Get the responsibilities for the default user role
        $responsibilities = array_flip(get_responsibilites_by_role_id($default_user_role));

        // Set the values according to the default user role
        $team = "";
        $role_id = $default_user_role;
        $governance = array_key_exists('governance', $responsibilities) ? 1 : 0;
        $riskmanagement = array_key_exists('riskmanagement', $responsibilities) ? 1 : 0;
        $compliance = array_key_exists('compliance', $responsibilities) ? 1 : 0;
        $assessments = array_key_exists('assessments', $responsibilities) ? 1 : 0;
        $asset = array_key_exists('asset', $responsibilities) ? 1 : 0;
        $admin = array_key_exists('admin', $responsibilities) ? 1 : 0;
        $review_veryhigh = array_key_exists('review_veryhigh', $responsibilities) ? 1 : 0;
        $accept_mitigation = array_key_exists('accept_mitigation', $responsibilities) ? 1 : 0;
        $review_high = array_key_exists('review_high', $responsibilities) ? 1 : 0;
        $review_medium = array_key_exists('review_medium', $responsibilities) ? 1 : 0;
        $review_low = array_key_exists('review_low', $responsibilities) ? 1 : 0;
        $review_insignificant = array_key_exists('review_insignificant', $responsibilities) ? 1 : 0;
        $submit_risks = array_key_exists('submit_risks', $responsibilities) ? 1 : 0;
        $modify_risks = array_key_exists('modify_risks', $responsibilities) ? 1 : 0;
        $plan_mitigations = array_key_exists('plan_mitigations', $responsibilities) ? 1 : 0;
        $close_risks = array_key_exists('close_risks', $responsibilities) ? 1 : 0;
        $multi_factor = 1;
        $change_password = 0;
        $add_new_frameworks = array_key_exists('add_new_frameworks', $responsibilities) ? 1 : 0;
        $modify_frameworks = array_key_exists('modify_frameworks', $responsibilities) ? 1 : 0;
        $delete_frameworks = array_key_exists('delete_frameworks', $responsibilities) ? 1 : 0;
        $add_new_controls = array_key_exists('add_new_controls', $responsibilities) ? 1 : 0;
        $modify_controls = array_key_exists('modify_controls', $responsibilities) ? 1 : 0;
        $delete_controls = array_key_exists('delete_controls', $responsibilities) ? 1 : 0;
        $add_documentation = array_key_exists('add_documentation', $responsibilities) ? 1 : 0;
        $modify_documentation = array_key_exists('modify_documentation', $responsibilities) ? 1 : 0;
        $delete_documentation = array_key_exists('delete_documentation', $responsibilities) ? 1 : 0;
        $other_options = [
            "add_documentation" => $add_documentation,
            "modify_documentation" => $modify_documentation,
            "delete_documentation" => $delete_documentation,
        ];
    }
    
    if(!empty($attributes['ldap_login_email']))
    {
        $email = $attributes['ldap_login_email'];
    }
    else
    {
        $email = "";
    }

    if(!empty($attributes['ldap_login_displayname']))
    {
        $name = $attributes['ldap_login_displayname'];
    }
    else
    {
        $name = $username;
    }
    
    // If there is manager field in attributes, set the manager
    if(!empty($attributes['manager']))
    {
        $manager_id = get_id_by_user($attributes['manager']);
        
        // If there is manager's username in user list, set the ID
        if($manager_id)
        {
            $other_options['manager'] = $manager_id;
        }
        // If "Automatically add a new user for a manager if they do not exist" setting is enabled, add a new user
        elseif(get_setting("AUTHENTICATION_ADD_NEW_MANAGER") == 1)
        {
            $manager_id = authentication_add_new_user("ldap", $attributes['manager']);
            $other_options['manager'] = $manager_id;
        }
    }
    

    // If the username doesn't already exist
    if (!user_exist($username))
    {
        // Add a new user with the proper responsibilities
        $user_id = add_user($type, $username, $email, $name, $salt, $hash, $team, $role_id, $governance, $riskmanagement, $compliance, $assessments, $asset, $admin, $review_veryhigh, $accept_mitigation, $review_high, $review_medium, $review_low, $review_insignificant, $submit_risks, $modify_risks, $plan_mitigations, $close_risks, $multi_factor, $change_password, $add_new_frameworks, $modify_frameworks, $delete_frameworks, $add_new_controls, $modify_controls, $delete_controls, $other_options);

        if($type == "saml")
        {
            // Try to login again
            header("Location: login.php");
        }
        else
        {
            return $user_id;
        }
    }
    // Otherwise, redirect back to the index page
    else header("Location: ../../");
}

/***********************************************
 * FUNCTION: GET TEAM LIST BY LDAP GROUP NAMES *
 ***********************************************/
function get_teams_by_ldap_groupnames($group_names)
{
    $group_names_query_string = implode(",", $group_names);
    
    // Open the database connection
    $db = db_open();

    $stmt = $db->prepare("SELECT team_id FROM `ldap_group_and_teams` WHERE FIND_IN_SET(`group_name`, :group_names_query_string); ");
    $stmt->bindParam(":group_names_query_string", $group_names_query_string, PDO::PARAM_STR);
    $stmt->execute();

    // Store the list in the array
    $team_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Close the database connection
    db_close($db);

    return $team_ids ? $team_ids : [];
}

/*************************************
 * FUNCTION: SET TEAMS FOR LDAP USER *
 *************************************/
function set_team_to_ldap_user($user_id, $dn, $pass)
{
    // Open the database connection
    $db = db_open();

    // Get LDAP group names for this user
    $ldap_group_names = get_ldap_group_by_dn($dn);

    $team_ids = [];
    
    // If group names were found.
    if($ldap_group_names)
    {
        
        $team_ids = get_teams_by_ldap_groupnames($ldap_group_names);

        if($team_ids)
        {
            $stmt = $db->prepare("UPDATE `user` SET `teams`=:teams WHERE `value` = :value; ");
            $teams = ":".implode("::", $team_ids).":";
            $stmt->bindParam(":teams", $teams, PDO::PARAM_STR);
            $stmt->bindParam(":value", $user_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    // Close the database connection
    db_close($db);
    
    return;    
}

/****************************************
 * FUNCTION: DISPLAY LDAP TEAM DROPDOWN *
 ****************************************/
function ldap_team_dropdown($name)
{
    global $lang, $escaper;
    
    $all_teams = get_table("team");
    $group_and_teams = get_table("ldap_group_and_teams");
    
    $teams = [];
    // Strip already existing group name
    foreach($all_teams as $team){
        $exist = false;
        foreach($group_and_teams as $group_and_team){
            if($team['value'] == $group_and_team['team_id']){
                $exist = true;
                break;
            }
        }
        if(!$exist){
            $teams[] = $team;
        }
    }
    
    echo "<select id=\"".$escaper->escapeHtml($name)."\" name=\"".$escaper->escapeHtml($name)."\">\n";
    echo "<option value=\"\">--</option>\n";
    foreach($teams as $team){
        echo "<option value='".$escaper->escapeHtml($team['value'])."'>".$escaper->escapeHtml($team['name'])."</option>\n";
    }
    echo "</select>\n";
}

/******************************************
 * FUNCTION: DISPLAY LDAP GROUPS DROPDOWN *
 ******************************************/
function ldap_groups_dropdown($name)
{
    global $lang, $escaper;
    
    $group_and_teams = get_table("ldap_group_and_teams");
    
    $group_names = get_ldap_group_names();
    
    // Strip already existing group name
    foreach($group_and_teams as $group_and_team){
        $key = array_search($group_and_team['group_name'], $group_names);
        if($key !== false){
            array_splice($group_names, $key, 1);
        }
    }
    
    $html = "";
    
    $html .= "<select id=\"".$escaper->escapeHtml($name)."\" name=\"".$escaper->escapeHtml($name)."\">\n";
    $html .= "<option value=\"\">--</option>\n";
    foreach($group_names as $group_name){
        $html .= "<option value='".$escaper->escapeHtml($group_name)."'>".$escaper->escapeHtml($group_name)."</option>\n";
    }
    $html .= "</select>\n";
    
    return $html;
}

/***************************************************
 * FUNCTION: EXISTING LDAP GROUP AND TEAM DROPDOWN *
 ***************************************************/
function existing_ldap_group_team_dropdown($name)
{
    global $lang, $escaper;
    
    $group_and_teams = get_table("ldap_group_and_teams");
    
    echo "<select id=\"".$escaper->escapeHtml($name)."\" name=\"".$escaper->escapeHtml($name)."\">\n";
    foreach($group_and_teams as $group_and_team){
        echo "<option value='".$escaper->escapeHtml($group_and_team['value'])."'>".$escaper->escapeHtml($group_and_team['team_name']). " <--> ".  $escaper->escapeHtml($group_and_team['group_name']) ."</option>\n";
    }
    echo "</select>\n";
}

/**********************************
 * FUNCTION: GET LDAP GROUP NAMES *
 **********************************/
function get_ldap_group_names()
{
    global $lang, $escaper;
    
    // Write the debug log
    write_debug_log("Using a BIND account to get LDAP group names.");

    // Get the authentication settings
    $configs = get_authentication_settings();
    
    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Connect to the LDAP server
    $ds = @ldap_connect($LDAPHOST, $LDAPPORT);
    if(!$ds){
        return [];
    }

    // Set the LDAP protocol version
    write_debug_log("Using LDAP protocol version " . $LDAP_VERSION);
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $LDAP_VERSION);

    // Set whether to chase referrals
    write_debug_log("Setting chase referrals to " . $CHASE_REFERRALS);
    ldap_set_option($ds, LDAP_OPT_REFERRALS, $CHASE_REFERRALS);

    // Write the debug log
//    write_debug_log("Attempting to BIND using \"" . $dn . "\".");
        
    // If we should use TLS
    if ($TLS == "true")
    {       
        // Start TLS
        write_debug_log("Starting TLS.");
        ldap_start_tls($ds);
    }

    // Set the dn we are looking for
//    $filter = "(uniqueMember=*)";
    $filter = get_setting("LDAP_FILTER_FOR_GROUP");
//    $filter = "uid=*";
//    $filter = "(&(|(|(|(objectClass=posixGroup)(objectClass=groupOfUniqueNames))(objectClass=groupOfNames))(objectClass=group))(uniquemember=uid=tesla,dc=example,dc=com))";
    // Write the debug log
    write_debug_log("The BIND was successful.");

    // Search LDAP for the user
    $result = @ldap_search($ds, $GROUPDN, $filter, array($LDAP_GROUP_ATTRIBUTE));
    if(!$result){
        set_alert(true, "bad", $escaper->escapeHtml($lang['ErrorInSearchQuery']). ": " . ldap_error($ds));
        refresh();
    }

    
    if(!$result){
        return [];
    }

    $group_names = [];

    // If the group was found
    if ($result != false)
    {
        // Write the debug log
        write_debug_log("The group was found.");
        
        // Get the entries for that result
        $data = ldap_get_entries($ds, $result);
        // If we found an entry then we have the full path to the user
        if ($data['count'] > 0)
        {
            for($i=0; $i<$data['count']; $i++){
                if(isset($data[$i][$LDAP_GROUP_ATTRIBUTE][0])){
                    $group_names[] = $data[$i][$LDAP_GROUP_ATTRIBUTE][0];
                }
                
            }
        }
    }
    $group_names = array_unique($group_names);
    return $group_names;
}

/***************************************
 * FUNCTION: GET LDAP GROUP BY USER DN *
 ***************************************/
function get_ldap_group_by_dn($dn)
{
    global $lang, $escaper;
    
    // Write the debug log
    write_debug_log("Using a BIND account to get LDAP group by dn and pass.");

    // Get the authentication settings
    $configs = get_authentication_settings();

    // For each configuration
    foreach ($configs as $config)
    {
        // Set the name value pair as a variable
        ${$config['name']} = $config['value'];
    }

    // Connect to the LDAP server
    $ds = ldap_connect($LDAPHOST, $LDAPPORT)
        or die ("Could not connect to LDAP server.");

    // Set the LDAP protocol version
    write_debug_log("Using LDAP protocol version " . $LDAP_VERSION);
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $LDAP_VERSION);

    // Set whether to chase referrals
    write_debug_log("Setting chase referrals to " . $CHASE_REFERRALS);
    ldap_set_option($ds, LDAP_OPT_REFERRALS, $CHASE_REFERRALS);

    // Write the debug log
    write_debug_log("Attempting to BIND using \"" . $dn . "\".");

    // If we should use TLS
    if ($TLS == "true")
    {       
        // Start TLS
        write_debug_log("Starting TLS.");
        ldap_start_tls($ds);
    }

    // Set the dn we are looking for
    //        $filter = "(".$LDAP_MEMBER_ATTRIBUTE."={$dn})";
    $filter = "(&".get_setting("LDAP_FILTER_FOR_GROUP")."(".$LDAP_MEMBER_ATTRIBUTE."=".$dn."))";
    
    // Write the debug log
    write_debug_log("The BIND was successful.");
    write_debug_log("Running a search for " . $dn);

    // Search LDAP for the user
    $result = @ldap_search($ds, $USERDN, $filter, array($LDAP_GROUP_ATTRIBUTE));
    if(!$result){
        set_alert(true, "bad", $escaper->escapeHtml($lang['ErrorInSearchQuery']). ": " . ldap_error($ds));
        refresh();
    }
    
    $group_names = [];
    // If the group was found
    if ($result != false)
    {
        // Write the debug log
        write_debug_log("The group was found.");
        
        // Get the entries for that result
        $data = ldap_get_entries($ds, $result);

        // If we found an entry then we have the full path to the user
        if ($data['count'] > 0)
        {
            for($i=0; $i<$data['count']; $i++){
                if(isset($data[$i][$LDAP_GROUP_ATTRIBUTE][0])){
                    $group_names[] = $data[$i][$LDAP_GROUP_ATTRIBUTE][0];
                }
            }
        }
    }
    else
    {
        // Write the debug log
        write_debug_log("The group was not found.");
    }
    
    return $group_names;
}

?>

<?php

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../../../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/****************************************
 * FUNCTION: SHOW TENABLE CONFIGURATION *
 ****************************************/
function show_tenable_configuration()
{
	global $escaper;

	// Get the tenable configuration
	$integration_tenable = get_setting('integration_tenable');
	$tenable_access_key = try_decrypt(get_setting('tenable_access_key'));
	$tenable_secret_key = try_decrypt(get_setting('tenable_secret_key'));

	// Display the tenable configuration
	echo "<div id=\"tenable_display\" " . ($integration_tenable != 1 ? " style=\"display:none;\"" : "") . ">\n";
	echo "<br /><b><u>Tenable Settings</u></b>\n";
	echo "<table border=\"0\">\n";
	echo "<tr>\n";
	echo "<td>Access Key:&nbsp;&nbsp;</td><td><input type=\"text\" size=\"100\" name=\"tenable_access_key\" id=\"tenable_access_key\" value=\"" . $escaper->escapeHtml($tenable_access_key) . "\" placeholder=\"Tenable Access Key\" /></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td>Secret Key:&nbsp;&nbsp;</td><td><input type=\"password\" size=\"100\" name=\"tenable_secret_key\" id=\"tenable_secret_key\" value=\"" . $escaper->escapeHtml($tenable_secret_key) . "\" placeholder=\"Tenable Secret Key\" /></td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "</div>\n";
}

/************************************************
 * FUNCTION: PROCESS TENABLE INTEGRATION UPDATE *
 ************************************************/
function process_tenable_integration_update()
{
	// Set the Tenable.io values
	update_setting('integration_tenable', (isset($_POST['tenable']) ? '1' : '0'));
	update_setting('tenable_access_key', (isset($_POST['tenable_access_key']) ? try_encrypt($_POST['tenable_access_key']) : ''));
	update_setting('tenable_secret_key', (isset($_POST['tenable_secret_key']) ? try_encrypt($_POST['tenable_secret_key']) : ''));
}

/***********************************
 * FUNCTION: IMPORT TENABLE ASSETS *
 ***********************************/
function import_tenable_assets()
{
        global $escaper;

        // Get the access and secret keys
        $access_key = try_decrypt(get_setting('tenable_access_key'));
        $secret_key = try_decrypt(get_setting('tenable_secret_key'));

        // Make the call to the tenable API
        $opts = array(
                'http'=>array(
                        'method'=>"GET",
                        'header'=>"content-type: application/json\r\n" .
                        "x-apikeys: accessKey=" . $access_key . "; secretKey=" . $secret_key . "\r\n"
                )
        );
        $context = stream_context_create($opts);

        $assets_json = @file_get_contents("https://cloud.tenable.com/workbenches/assets", false, $context);

        // If file_get_contents didn't return false
        if ($assets_json != false)
        {
                // Convert the assets into a json decoded string
                $assets_array = json_decode($assets_json, true);
                $assets = $assets_array['assets'];
                $total = $assets_array['total'];
                $assets_array = array();

                write_debug_log("Importing Assets from Tenable.io");

                foreach($assets as $key=>$value)
                {
                        // Get the values from tenable
                        $uuid = (isset($value['id']) ? $value['id'] : '');
                        $has_agent = (isset($value['has_agent']) ? $value['has_agent'] : '');
                        $last_seen = (isset($value['last_seen']) ? $value['last_seen'] : '');
                        $source_name = (isset($value['sources'][0]['name']) ? $value['sources'][0]['name'] : '');
                        $first_seen = (isset($value['sources'][0]['first_seen']) ? $value['sources'][0]['first_seen'] : '');
                        $ipv4 = (isset($value['ipv4'][0]) ? $value['ipv4'][0] : '');
                        $ipv6 = (isset($value['ipv6'][0]) ? $value['ipv6'][0] : '');
                        $fqdn = (isset($value['fqdn'][0]) ? $value['fqdn'][0] : '');
                        $netbios_name = (isset($value['netbios_name'][0]) ? $value['netbios_name'][0] : '');
                        $operating_system = (isset($value['operating_system'][0]) ? $value['operating_system'][0] : '');
                        $agent_name = (isset($value['agent_name'][0]) ? $value['agent_name'][0] : '');
                        $aws_ec2_name = (isset($value['aws_ec2_name'][0]) ? $value['aws_ec2_name'][0] : '');
                        $mac_address = (isset($value['mac_address'][0]) ? $value['mac_address'][0] : '');

                        write_debug_log("Importing asset UUID: " . $uuid);
                        write_debug_log("Importing asset Has Agent: " . $has_agent);
                        write_debug_log("Importing asset First Seen: " . $first_seen);
                        write_debug_log("Importing asset Last Seen: " . $last_seen);
                        write_debug_log("Importing asset IPV4: " . $ipv4);
                        write_debug_log("Importing asset FQDN: " . $fqdn);
                        write_debug_log("Importing asset OS: " . $operating_system);
                        write_debug_log("Importing asset Source: " . $source_name);
                        write_debug_log("Importing asset Netbios: " . $netbios_name);
                        write_debug_log("Importing asset Agent: " . $agent_name);
                        write_debug_log("Importing asset AWS: " . $aws_ec2_name);
                        write_debug_log("Importing asset MAC: " . $mac_address);
                        //echo "Key: " . $key . "<br>";
                        //echo "Value: " . print_r($value) . "<br>";

                        // Open the database connection
                        $db = db_open();

                        // Get the asset values
                        $value = get_default_asset_valuation();
                        $location = 0;
                        $team = 0;
                        $tags = "";
                        $verified = 1;
                        $details = "UUID: " . $uuid . "\nOS: " . $operating_system . "\nNETBIOS NAME: " . $netbios_name . "\nFQDN: " . $fqdn . "\nFIRST SEEN: " . $first_seen . "\nLAST SEEN: " . $last_seen;

                        // Set the asset name to the FQDN
                        $asset_name = $fqdn;

                        // If the asset name is empty
                        if (empty($asset_name))
                        {
                            // Set the asset name to the netbios name
                            $asset_name = $netbios_name;

                            // If the asset name is empty
                            if (empty($asset_name))
                            {
                                // Set the asset name to the ip
                                $asset_name = $ipv4;
                            }
                        }

                        // Import the asset
                        $asset_id = import_asset($ipv4, $asset_name, $value, $location, $team, $details, $tags, $verified);

                        // Add or update the assets into the database
                        $stmt = $db->prepare("INSERT IGNORE INTO `import_export_integration_assets` SET `integration_name` = 'tenable', `asset_id` = :asset_id, `uuid` = :uuid, `has_agent` = :has_agent, `source_name` = :source_name, `first_seen` = :first_seen, `last_seen` = :last_seen, `ipv4` = :ipv4, `fqdn` = :fqdn, `operating_system` = :operating_system, `netbios_name` = :netbios_name, `agent_name` = :agent_name, `aws_ec2_name` = :aws_ec2_name, `mac_address` = :mac_address ON DUPLICATE KEY UPDATE `integration_name` = 'tenable', `asset_id` = :asset_id, `uuid` = :uuid, `has_agent` = :has_agent, `source_name` = :source_name, `first_seen` = :first_seen, `last_seen` = :last_seen, `ipv4` = :ipv4, `fqdn` = :fqdn, `operating_system` = :operating_system, `netbios_name` = :netbios_name, `agent_name` = :agent_name, `aws_ec2_name` = :aws_ec2_name, `mac_address` = :mac_address");
                        $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
                        $stmt->bindParam(":uuid", $uuid, PDO::PARAM_STR);
                        $stmt->bindParam(":has_agent", $has_agent, PDO::PARAM_STR);
                        $stmt->bindParam(":source_name", $source_name, PDO::PARAM_STR);
                        $stmt->bindParam(":first_seen", $first_seen, PDO::PARAM_STR);
                        $stmt->bindParam(":last_seen", $last_seen, PDO::PARAM_STR);
                        $encrypted_ipv4 = try_encrypt($ipv4);
                        $stmt->bindParam(":ipv4", $encrypted_ipv4, PDO::PARAM_STR);
                        $encrypted_fqdn = try_encrypt($fqdn);
                        $stmt->bindParam(":fqdn", $encrypted_fqdn, PDO::PARAM_STR);
                        $encrypted_operating_system = try_encrypt($operating_system);
                        $stmt->bindParam(":operating_system", $encrypted_operating_system, PDO::PARAM_STR);
                        $encrypted_netbios_name = try_encrypt($netbios_name);
                        $stmt->bindParam(":netbios_name", $encrypted_netbios_name, PDO::PARAM_STR);
                        $encrypted_agent_name = try_encrypt($agent_name);
                        $stmt->bindParam(":agent_name", $encrypted_agent_name, PDO::PARAM_STR);
                        $encrypted_aws_ec2_name = try_encrypt($aws_ec2_name);
                        $stmt->bindParam(":aws_ec2_name", $encrypted_aws_ec2_name, PDO::PARAM_STR);
                        $encrypted_mac_address = try_encrypt($mac_address);
                        $stmt->bindParam(":mac_address", $encrypted_mac_address, PDO::PARAM_STR);
                        $stmt->execute();

                        // Close the database connection
                        db_close($db);

                        // Add to the assets array
                        $assets_array[] = array("uuid"=>$uuid, "has_agent"=>$has_agent, "source_name"=>$source_name, "first_seen"=>$first_seen, "last_seen"=>$last_seen, "ipv4"=>$ipv4, "fqdn"=>$fqdn, "operating_system"=>$operating_system, "netbios_name"=>$netbios_name, "agent_name"=>$agent_name, "aws_ec2_name"=>$aws_ec2_name, "mac_address"=>$mac_address);
                }

                // Return the array of assets
                return $assets_array;
        }
        // If there was a problem making the request
        else
        {
                $error = error_get_last();
                $error = explode(': ', $error['message']);
                $error = trim($error[2]) . PHP_EOL;

                // Display an alert
                set_alert(true, "bad", $escaper->escapeJs($error));

                // Return false
                return false;
        }
}

/********************************************
 * FUNCTION: IMPORT TENABLE VULNERABILITIES *
 ********************************************/
function import_tenable_vulnerabilities()
{
        // Clear any risks to assets mappings for tenable assets
	clear_asset_associations();

        // Re-import tenable assets
        import_tenable_assets();

        global $escaper;

        // Get the access and secret keys
        $access_key = try_decrypt(get_setting('tenable_access_key'));
        $secret_key = try_decrypt(get_setting('tenable_secret_key'));

        // Make the call to the tenable API
        $opts = array(
                'http'=>array(
                        'method'=>"GET",
                        'header'=>"content-type: application/json\r\n" .
                        "x-apikeys: accessKey=" . $access_key . "; secretKey=" . $secret_key . "\r\n"
                )
        );
        $context = stream_context_create($opts);

        $vulnerabilities_json = @file_get_contents("https://cloud.tenable.com/workbenches/vulnerabilities", false, $context);

        // If file_get_contents didn't return false
        if ($vulnerabilities_json != false)
        {
                // Convert the vulnerabilities into a json decoded string
                $vulnerabilities_array = json_decode($vulnerabilities_json, true);
                $vulnerabilities = $vulnerabilities_array['vulnerabilities'];
                $total = $vulnerabilities_array['total_vulnerability_count'];
                $vulnerabilities_array = array();

                write_debug_log("Importing Vulnerabilities from Tenable.io");

                foreach($vulnerabilities as $key=>$value)
                {
                        // Get the values from Tenable
                        $count = (isset($value['count']) ? $value['count'] : '');
                        $plugin_family = (isset($value['plugin_family']) ? $value['plugin_family'] : '');
                        $plugin_id = (isset($value['plugin_id']) ? $value['plugin_id'] : '');
                        $plugin_name = (isset($value['plugin_name']) ? $value['plugin_name'] : '');
                        $vulnerability_state = (isset($value['vulnerability_state']) ? $value['vulnerability_state'] : '');
                        $accepted_count = (isset($value['accepted_count']) ? $value['accepted_count'] : '');
                        $recasted_count = (isset($value['recasted_count']) ? $value['recasted_count'] : '');
                        $counts_by_severity_count = (isset($value['counts_by_severity'][0]['count']) ? $value['counts_by_severity'][0]['count'] : '');
                        $counts_by_severity_value = (isset($value['counts_by_severity'][0]['value']) ? $value['counts_by_severity'][0]['value'] : '');
                        $severity = (isset($value['severity']) ? $value['severity'] : '');
                        //echo "Key: " . $key . "<br>";
                        //echo "Value: " . print_r($value) . "<br>";

                        // Get the vulnerability info for the plugin id
                        $vulnerability_info = import_tenable_vulnerability_info($plugin_id);
                        $description = (isset($vulnerability_info['description']) ? $vulnerability_info['description'] : '');
                        $synopsis = (isset($vulnerability_info['synopsis']) ? $vulnerability_info['synopsis'] : '');
                        $solution = (isset($vulnerability_info['solution']) ? $vulnerability_info['solution'] : '');
                        $discovery_seen_first = (isset($vulnerability_info['discovery']['seen_first']) ? $vulnerability_info['discovery']['seen_first'] : '');
                        $discovery_seen_last = (isset($vulnerability_info['discovery']['seen_last']) ? $vulnerability_info['discovery']['seen_last'] : '');
                        $severity = (isset($vulnerability_info['severity']) ? $vulnerability_info['severity'] : '');
                        $plugin_details_family = (isset($vulnerability_info['plugin_details']['family']) ? $vulnerability_info['plugin_details']['family'] : '');
                        $plugin_details_modification_date = (isset($vulnerability_info['plugin_details']['modification_date']) ? $vulnerability_info['plugin_details']['modification_date'] : '');
                        $plugin_details_name = (isset($vulnerability_info['plugin_details']['name']) ? $vulnerability_info['plugin_details']['name'] : '');
                        $plugin_details_publication_date = (isset($vulnerability_info['plugin_details']['publication_date']) ? $vulnerability_info['plugin_details']['publication_date'] : '');
                        $plugin_details_type = (isset($vulnerability_info['plugin_details']['type']) ? $vulnerability_info['plugin_details']['type'] : '');
                        $plugin_details_version = (isset($vulnerability_info['plugin_details']['version']) ? $vulnerability_info['plugin_details']['version'] : '');
                        $plugin_details_severity = (isset($vulnerability_info['plugin_details']['severity']) ? $vulnerability_info['plugin_details']['severity'] : '');
                        $risk_information_risk_factor = (isset($vulnerability_info['risk_information']['risk_factor']) ? $vulnerability_info['risk_information']['risk_factor'] : '');
                        $risk_information_cvss_vector = (isset($vulnerability_info['risk_information']['cvss_vector']) ? $vulnerability_info['risk_information']['cvss_vector'] : '');
                        $risk_information_cvss_base_score = (isset($vulnerability_info['risk_information']['cvss_base_score']) ? $vulnerability_info['risk_information']['cvss_base_score'] : '');
                        $risk_information_cvss_temporal_vector = (isset($vulnerability_info['risk_information']['cvss_temporal_vector']) ? $vulnerability_info['risk_information']['cvss_temporal_vector'] : '');
                        $risk_information_cvss_temporal_score = (isset($vulnerability_info['risk_information']['cvss_temporal_score']) ? $vulnerability_info['risk_information']['cvss_temporal_score'] : '');
                        $risk_information_cvss3_vector = (isset($vulnerability_info['risk_information']['cvss3_vector']) ? $vulnerability_info['risk_information']['cvss3_vector'] : '');
                        $risk_information_cvss3_base_score = (isset($vulnerability_info['risk_information']['cvss3_base_score']) ? $vulnerability_info['risk_information']['cvss3_base_score'] : '');
                        $risk_information_cvss3_temporal_vector = (isset($vulnerability_info['risk_information']['cvss3_temporal_vector']) ? $vulnerability_info['risk_information']['cvss3_temporal_vector'] : '');
                        $risk_information_cvss3_temporal_score = (isset($vulnerability_info['risk_information']['cvss3_temporal_score']) ? $vulnerability_info['risk_information']['cvss3_temporal_score'] : '');

                        $vulnerabilities_array[] = array("count"=>$count, "plugin_family"=>$plugin_family, "plugin_id"=>$plugin_id, "plugin_name"=>$plugin_name, "vulnerability_state"=>$vulnerability_state, "accepted_count"=>$accepted_count, "recasted_count"=>$recasted_count, "counts_by_severity_count"=>$counts_by_severity_count, "counts_by_severity_value"=>$counts_by_severity_value, "severity"=>$severity, "description"=>$description, "synopsis"=>$synopsis, "solution"=>$solution, "discovery_seen_first"=>$discovery_seen_first, "discovery_seen_last"=>$discovery_seen_last, "severity"=>$severity, "plugin_details_family"=>$plugin_details_family, "plugin_details_modification_date"=>$plugin_details_modification_date, "plugin_details_name"=>$plugin_details_name, "plugin_details_publication_date"=>$plugin_details_publication_date, "plugin_details_type"=>$plugin_details_type, "plugin_details_version"=>$plugin_details_version, "plugin_details_severity"=>$plugin_details_severity, "risk_information_risk_factor"=>$risk_information_risk_factor, "risk_information_cvss_vector"=>$risk_information_cvss_vector, "risk_information_cvss_base_score"=>$risk_information_cvss_base_score, "risk_information_cvss_temporal_vector"=>$risk_information_cvss_temporal_vector, "risk_information_cvss_temporal_score"=>$risk_information_cvss_temporal_score, "risk_information_cvss3_vector"=>$risk_information_cvss3_vector, "risk_information_cvss3_base_score"=>$risk_information_cvss3_base_score, "risk_information_cvss3_temporal_vector"=>$risk_information_cvss3_temporal_vector);

                        // Create this vulnerability as a risk
                        $subject = $synopsis;
                        $assessment = $description;
                        $notes = $solution;
                        $scoring_method = 2; // CVSS Scoring
                        $risk_score = $risk_information_cvss_temporal_score;
                        $status = "New";

                        // See if we have any other risks with this subject
                        $risk_id = get_risk_by_subject($subject);

                        // If the risk subject doesn't exist
                        if ($risk_id == false)
                        {
                                // Submit the risk
                                $risk_id = submit_risk($status, $subject, $reference_id = "", $regulation = "", $control_number = "", $location = "", $source = "",  $category = "", $team = "", $technology = "", $owner = "", $manager = "", $assessment, $notes, $project_id = 0, $submitted_by=0, $submission_date=false, $additional_stakeholders="");

                                // Split the CVSS base vector by the slash
                                $cvss = explode("/", $risk_information_cvss_vector);

                                // For each CVSS value
                                foreach ($cvss as $vector)
                                {
                                        // Split the vector by the colon
                                        $vector_split = explode(":", $vector);
                                        $vector_name = $vector_split[0];
                                        $vector_value = (isset($vector_split[1]) ? $vector_split[1] : null);

                                        switch($vector_name)
                                        {
                                                case "AV":
                                                        $AccessVector = (isset($vector_value) ? $vector_value : "N");
                                                        break;
                                                case "AC":
                                                        $AccessComplexity = (isset($vector_value) ? $vector_value : "L");
                                                        break;
                                                case "Au":
                                                        $Authentication = (isset($vector_value) ? $vector_value : "N");
                                                        break;
                                                case "C":
                                                        $ConfImpact = (isset($vector_value) ? $vector_value : "C");
                                                        break;
                                                case "I":
                                                        $IntegImpact = (isset($vector_value) ? $vector_value : "C");
                                                        break;
                                                case "A":
                                                        $AvailImpact = (isset($vector_value) ? $vector_value : "C");
                                                        break;
                                        }
                                }

                                // If the CVSS temporal vector is set
                                if (isset($risk_information_cvss_temporal_vector))
                                {
                                        // Split the CVSS temporal vector by the slash
                                        $cvss = explode("/", $risk_information_cvss_temporal_vector);

                                        // For each CVSS value
                                        foreach ($cvss as $vector)
                                        {
                                                // Split the vector by the colon
                                                $vector_split = explode(":", $vector);
                                                $vector_name = $vector_split[0];
                                                $vector_value = (isset($vector_split[1]) ? $vector_split[1] : null);

                                                switch($vector_name)
                                                {
                                                        case "E":
                                                                $Exploitability = (isset($vector_value) ? $vector_value : "ND");
                                                                break;
                                                        case "RL":
                                                                $RemediationLevel = (isset($vector_value) ? $vector_value : "ND");
                                                                break;
                                                        case "RC":
                                                                $ReportConfidence = (isset($vector_value) ? $vector_value : "ND");
                                                                break;
                                                }
                                        }
                                }
                                // If the temporal vector is not set
                                else
                                {
                                        // Set the default temporal values
                                        $Exploitability = "ND";
                                        $RemediationLevel = "ND";
                                        $ReportConfidence = "ND";
                                }

                                // Submit the risk scoring
                                submit_risk_scoring($risk_id, $scoring_method, $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamage="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkill="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPDiscovery="10", $OWASPExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10", $custom="10", $ContributingLikelihood="", $ContributingImpacts=[]);
                        }

                        // Get the assets for this vulnerability
                        $assets = import_tenable_vulnerability_assets($plugin_id);

                        // For each asset
                        foreach($assets as $asset)
                        {
                                // Get the asset values
                                $hostname = $asset['hostname'];
                                $id = $asset['id'];
                                $uuid = $asset['uuid'];
                                $netbios_name = $asset['netbios_name'];
                                $fqdn = $asset['fqdn'];
                                $ipv4 = $asset['ipv4'];
                                $first_seen = $asset['first_seen'];
                                $last_seen = $asset['last_seen'];
                                $value = get_default_asset_valuation();
                                $location = 0;
                                $team = 0;
                                $tags = "";
                                $verified = 1;
                                $details = "UUID: " . $uuid . "\nHOSTNAME: " . $hostname . "\nNETBIOS NAME: " . $netbios_name . "\nFQDN: " . $fqdn . "\nFIRST SEEN: " . $first_seen . "\nLAST SEEN: " . $last_seen;

                                // Set the asset name to the FQDN
                                $asset_name = $fqdn;

                                // If the asset name is empty
                                if (empty($asset_name))
                                {
                                        // Set the asset name to the netbios name
                                        $asset_name = $netbios_name;

                                        // If the asset name is empty
                                        if (empty($asset_name))
                                        {
                                                // Set the asset name to the hostname
                                                $asset_name = $hostname;

                                                // If the asset name is empty
                                                if (empty($asset_name))
                                                {
                                                        // Set the asset name to the ip
                                                        $asset_name = $ipv4;
                                                }
                                        }
                                }


                                // Import the asset
                                $asset_id = import_asset($ipv4, $asset_name, $value, $location, $team, $details, $tags, $verified);

                                // If the asset exists
                                if ($asset_id != 0)
                                {
                                        // Map the vulnerability to the asset
                                        $db = db_open();
                                        $stmt = $db->prepare("INSERT IGNORE INTO `risks_to_assets` (`risk_id`, `asset_id`) VALUES (:risk_id, :asset_id);");
                                        $stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
                                        $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
                                        $stmt->execute();
                                        db_close($db);
                                }
                        }
                }

                // Return the array of vulnerabilities
                return $vulnerabilities_array;
        }
        // If there was a problem making the request
        else
        {
                $error = error_get_last();
                $error = explode(': ', $error['message']);
                $error = trim($error[2]) . PHP_EOL;

                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($error));

                // Return false
                return false;
        }
}

/***********************************************
 * FUNCTION: IMPORT TENABLE VULNERABILITY INFO *
 ***********************************************/
function import_tenable_vulnerability_info($plugin_id)
{
        global $escaper;

        // Get the access and secret keys
        $access_key = try_decrypt(get_setting('tenable_access_key'));
        $secret_key = try_decrypt(get_setting('tenable_secret_key'));

        // Make the call to the tenable API
        $opts = array(
                'http'=>array(
                        'method'=>"GET",
                        'header'=>"content-type: application/json\r\n" .
                        "x-apikeys: accessKey=" . $access_key . "; secretKey=" . $secret_key . "\r\n"
                )
        );
        $context = stream_context_create($opts);

        $vulnerabilities_json = @file_get_contents("https://cloud.tenable.com/workbenches/vulnerabilities/" . $plugin_id . "/info", false, $context);

        // If file_get_contents didn't return false
        if ($vulnerabilities_json != false)
        {
                // Convert the vulnerabilities into a json decoded string
                $vulnerabilities_array = json_decode($vulnerabilities_json, true);
                $vulnerabilities = $vulnerabilities_array['info'];
                return $vulnerabilities;
        }
        // If there was a problem making the request
        else
        {
                $error = error_get_last();
                $error = explode(': ', $error['message']);
                $error = trim($error[2]) . PHP_EOL;

                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($error));

                // Return false
                return false;
        }
}

/*************************************************
 * FUNCTION: IMPORT TENABLE VULNERABILITY ASSETS *
 *************************************************/
function import_tenable_vulnerability_assets($plugin_id)
{
        global $escaper;

        // Get the access and secret keys
        $access_key = try_decrypt(get_setting('tenable_access_key'));
        $secret_key = try_decrypt(get_setting('tenable_secret_key'));

        // Make the call to the tenable API
        $opts = array(
                'http'=>array(
                        'method'=>"GET",
                        'header'=>"content-type: application/json\r\n" .
                        "x-apikeys: accessKey=" . $access_key . "; secretKey=" . $secret_key . "\r\n"
                )
        );
        $context = stream_context_create($opts);

        $vulnerabilities_json = @file_get_contents("https://cloud.tenable.com/workbenches/vulnerabilities/" . $plugin_id . "/outputs", false, $context);

        // If file_get_contents didn't return false
        if ($vulnerabilities_json != false)
        {
                // Convert the vulnerabilities into a json decoded string
                $vulnerabilities_array = json_decode($vulnerabilities_json, true);
                $assets = $vulnerabilities_array['outputs'][0]['states'][0]['results'][0]['assets'];
                return $assets;
        }
        // If there was a problem making the request
        else
        {
                $error = error_get_last();
                $error = explode(': ', $error['message']);
                $error = trim($error[2]) . PHP_EOL;

                // Display an alert
                set_alert(true, "bad", $escaper->escapeHtml($error));

                // Return false
                return false;
        }
}

/**********************************************
 * FUNCTION: CLEAR TENABLE ASSET ASSOCIATIONS *
 **********************************************/
function clear_tenable_asset_associations()
{
        // Open the database connection
        $db = db_open();

        // Get the list of tenable assets
        $stmt = $db->prepare("DELETE FROM `risks_to_assets` WHERE `asset_id` IN (SELECT `asset_id` FROM `import_export_integration_assets` WHERE `integration_name`='tenable');");
        $stmt->execute();

        // Close the database connection
        db_close($db);
}

?>

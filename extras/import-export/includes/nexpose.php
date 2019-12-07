<?php

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../../../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/****************************************
 * FUNCTION: SHOW NEXPOSE CONFIGURATION *
 ****************************************/
function show_nexpose_configuration()
{
	global $escaper;

        // Get the nexpose configuration
        $integration_nexpose = get_setting('integration_nexpose');
	$nexpose_server_name = try_decrypt(get_setting('nexpose_server_name'));
	$nexpose_server_port = try_decrypt(get_setting('nexpose_server_port'));
	$nexpose_user_id = try_decrypt(get_setting('nexpose_user_id'));
	$nexpose_password = try_decrypt(get_setting('nexpose_password'));

        // Display the nexpose configuration
        echo "<div id=\"nexpose_display\" " . ($integration_nexpose != 1 ? " style=\"display:none;\"" : "") . ">\n";
        echo "<br /><b><u>Nexpose Settings</u></b>\n";
        echo "<table border=\"0\">\n";
        echo "<tr>\n";
	echo "<td>Nexpose Server Name:&nbsp;&nbsp;</td><td><input type=\"text\" size=\"100\" name=\"nexpose_server_name\" id=\"nexpose_server_name\" value=\"" . $escaper->escapeHtml($nexpose_server_name) . "\" placeholder=\"servername.domain.com\" /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
	echo "<td>Nexpose Server Port:&nbsp;&nbsp;</td><td><input type=\"text\" size=\"100\" name=\"nexpose_server_port\" id=\"nexpose_server_port\" value=\"" . $escaper->escapeHtml($nexpose_server_port) . "\" placeholder=\"3780\" /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
	echo "<td>Nexpose User ID:&nbsp;&nbsp;</td><td><input type=\"text\" size=\"100\" name=\"nexpose_user_id\" id=\"nexpose_user_id\" value=\"" . $escaper->escapeHtml($nexpose_user_id) . "\" placeholder=\"Nexpose User ID\" /></td>\n";
        echo "</tr>\n";
        echo "<tr>\n";
	echo "<td>Nexpose Password:&nbsp;&nbsp;</td><td><input type=\"password\" size=\"100\" name=\"nexpose_password\" id=\"nexpose_password\" value=\"" . $escaper->escapeHtml($nexpose_password) . "\" placeholder=\"Nexpose Password\" /></td>\n";
        echo "</tr>\n";
        echo "</table>\n";
        echo "</div>\n";
}

/************************************************
 * FUNCTION: PROCESS NEXPOSE INTEGRATION UPDATE *
 ************************************************/
function process_nexpose_integration_update()
{
	// Set the Rapid7 Nexpose values
	update_setting('integration_nexpose', (isset($_POST['nexpose']) ? '1' : '0'));
	update_setting('nexpose_server_name', (isset($_POST['nexpose_server_name']) ? try_encrypt($_POST['nexpose_server_name']) : ''));
	update_setting('nexpose_server_port', (isset($_POST['nexpose_server_port']) ? try_encrypt($_POST['nexpose_server_port']) : ''));
	update_setting('nexpose_user_id', (isset($_POST['nexpose_user_id']) ? try_encrypt($_POST['nexpose_user_id']) : ''));
	update_setting('nexpose_password', (isset($_POST['nexpose_password']) ? try_encrypt($_POST['nexpose_password']) : ''));
}

/*********************************
 * FUNCTION: GET NEXPOSE SESSION *
 *********************************/
function get_nexpose_session()
{
	// Get the Nexpose configuration values
	$nexpose_server_name = try_decrypt(get_setting('nexpose_server_name'));
        $nexpose_server_port = try_decrypt(get_setting('nexpose_server_port'));
        $nexpose_user_id = try_decrypt(get_setting('nexpose_user_id'));
        $nexpose_password = try_decrypt(get_setting('nexpose_password'));

	// Create the XML for the login request
	$xml = '<?xml version="1.0" encoding="UTF-8" ?>';
	$xml .= '<LoginRequest sync-id="' . nexpose_get_sync_id() . '" user-id="' . $nexpose_user_id . '" password="' . $nexpose_password . '"/>';

	// Make the API request
	$result = nexpose_make_api_request($server, $port, $xml);

	// Parse the result data
	preg_match_all('/".*?"|\'.*?\'/', $result, $matches);
	$success = str_replace('"', "", $matches[0][0]);

	// If the login was successful
	if ($success)
	{
		// Get the session value
		$session = str_replace('"', "", $matches[0][1]);

		// Return the session value
		return $session;
	}

	// Login was not successful so return false
	return false;
}

/***********************************
 * FUNCTION: CLOSE NEXPOSE SESSION *
 ***********************************/
function close_nexpose_session($nexpose_session_id)
{
	// Get the Nexpose configuration values
        $nexpose_server_name = try_decrypt(get_setting('nexpose_server_name'));
        $nexpose_server_port = try_decrypt(get_setting('nexpose_server_port'));

	// Create the XML for the logout request
	$xml = '<?xml version="1.0" encoding="UTF-8" ?>';
	$xml .= '<LogoutRequest session-id="' . $nexpose_session_id . '" sync-id="' . nexpose_get_sync_id() . '" />';

	// Make the API request
	$result = nexpose_make_api_request($nexpose_server_name, $nexpose_server_port, $xml);

	// Return the result of the logout request
	return $result;
}

/**************************************
 * FUNCTION: NEXPOSE MAKE API REQUEST *
 **************************************/
function nexpose_make_api_request($server, $port, $request)
{
        // Create the request URL
        $url = "https://" . $server . ":" . $port . "/api/1.1/xml";

        // Create the options array
        $options = array(
                'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                ),
                'http' => array(
                        'header' => 'Content-Type:text/xml',
                        'method' => 'POST',
                        'content' => $request,
                )
        );

        // Fetch the API content
        $context = stream_context_create($options);
        $result = file_get_contents($url, NULL, $context);

        // Return the result
        return $result;
}

/*********************************************
 * FUNCTION: NEXPOSE GET SITE DEVICE LISTING *
 *********************************************/
function nexpose_get_site_device_listing($server, $port, $session)
{
        // XML for site listing
        $xml = '<?xml version="1.0" encoding="UTF-8" ?>';
        $xml .= '<SiteDeviceListingRequest session-id="' . $session . '" sync-id="' . nexpose_get_sync_id() . '" />';

        // Make the API request
        $result = nexpose_make_api_request($server, $port, $xml);

        // Return the result
        return $result;
}

/************************************
 * FUNCTION: NEXPOSE GET SITE ARRAY *
 ************************************/
function nexpose_get_site_array($server, $port, $session)
{
        // Get the site listing
        $result = nexpose_get_site_listing($server, $port, $session);

        // Get all key value pairs in the result
        preg_match_all("/id=\"(\d+)\"/", $result, $ids);
        preg_match_all("/name=\".*\"/", $result, $names);
        preg_match_all("/description=\".*\"/", $result, $descriptions);
        preg_match_all("/riskfactor=\".*\"/", $result, $riskfactors);
        preg_match_all("/riskscore=\".*\"/", $result, $riskscores);

        // Create the sites array
        $sites = array($ids, $names, $descriptions, $riskfactors, $riskscores);

        // Return the sites array
        return $sites;
}

/**************************************
 * FUNCTION: NEXPOSE GET SITE LISTING *
 **************************************/
function nexpose_get_site_listing($server, $port, $session)
{
        // XML for site listing
        $xml = '<?xml version="1.0" encoding="UTF-8" ?>';
        $xml .= '<SiteListingRequest session-id="' . $session . '" sync-id="' . nexpose_get_sync_id() . '" />';

        // Make the API request
        $result = nexpose_make_api_request($server, $port, $xml);

        // Return the result
        return $result;
}

/*********************************
 * FUNCTION: NEXPOSE GET SYNC ID *
 *********************************/
function nexpose_get_sync_id()
{
        // Generate session synchronization ID for client
        $sync_id = date("Uu");
}

/***********************************
 * FUNCTION: IMPORT NEXPOSE ASSETS *
 ***********************************/
function import_nexpose_assets()
{
	global $escaper;

	// Get the nexpose values
        $nexpose_server_name = try_decrypt(get_setting('nexpose_server_name'));
        $nexpose_server_port = try_decrypt(get_setting('nexpose_server_port'));
        $nexpose_user_id = try_decrypt(get_setting('nexpose_user_id'));
        $nexpose_password = try_decrypt(get_setting('nexpose_password'));	

	// Create the nexpose authentication string
	$authentication_string = $nexpose_user_id . ":" . $nexpose_password;
	$authentication_string_base64 = base64_encode($authentication_string);

	$current_page = 0;
	$page_size = 10;
	$url = "https://" . $nexpose_server_name . ":" . $nexpose_server_port . "/api/3/assets?size=".$page_size;

	// Make the call to the Nexpose API
	$opts = array(
		'ssl'=>array(
			'verify_peer'=>false,
			'verify_peer_name'=>false,
		),
		'http'=>array(
			'method'=>"GET",
			'header'=>"content-type: application/json\r\n" .
			"Authorization: Basic " . $authentication_string_base64 . "\r\n"
		)
	);
	$context = stream_context_create($opts);

        $assets_json = @file_get_contents($url."&page=".$current_page, false, $context);
        $assets_array = json_decode($assets_json, true);
        $page_number = $assets_array['page']['number'];
        $page_size = $assets_array['page']['size'];
        $page_total_resources = $assets_array['page']['totalResources'];
        $page_total_pages = $assets_array['page']['totalPages'];

	write_debug_log("Importing Assets from Nexpose");

        for ($current_page = 0; $current_page < $page_total_pages; $current_page++)
        {
		write_debug_log("Importing page " . $current_page . " / " . $page_total_pages . " of assets from Nexpose.");

                // Get the contents for that page
                $assets_json = @file_get_contents($url."&page=".$current_page, false, $context);

                // If file_get_contents didn't return false
                if ($assets_json != false)
                {
                        // Convert the assets into a json decoded string
                        $assets_array = json_decode($assets_json, true);
                        $assets = $assets_array['resources'];
                        $assets_array = array();

                        foreach($assets as $key=>$value)
                        {
                                $ipv4 = (isset($value['ip']) ? $value['ip'] : '');
                                $hostname = (isset($value['hostName']) ? $value['hostName'] : '');
                                $id = (isset($value['id']) ? $value['id'] : '');
                                $os = (isset($value['os']) ? $value['os'] : '');

				write_debug_log("Importing asset ID: " . $id);
				write_debug_log("Importing asset IP: " . $ipv4);
				write_debug_log("Importing asset Hostname: " . $hostname);
				write_debug_log("Importing asset OS: " . $os);

                        	// Open the database connection
                        	$db = db_open();

                        	// Get the asset values
                        	$value = get_default_asset_valuation();
                        	$location = 0;
                        	$team = 0;
                            $tags = "";
                        	$verified = 1;
                        	$details = "ID: " . $id . "\nOS: " . $os;

                        	// Set the asset name to the hostname
                        	$asset_name = $hostname;

                        	// If the asset name is empty
                        	if (empty($asset_name))
                        	{
                                        // Set the asset name to the ip
                                        $asset_name = $ipv4;
                                }

				// Import the asset
				$asset_id = import_asset($ipv4, $asset_name, $value, $location, $team, $details, $tags, $verified);

				// Add or update the assets into the database
                                $stmt = $db->prepare("INSERT IGNORE INTO `import_export_integration_assets` SET `integration_name` = 'nexpose', `asset_id` = :asset_id, `uuid` = :uuid, `ipv4` = :ipv4, `operating_system` = :operating_system ON DUPLICATE KEY UPDATE `integration_name` = 'nexpose', `asset_id` = :asset_id, `uuid` = :uuid, `ipv4` = :ipv4, `operating_system` = :operating_system");
                                $stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
                                $stmt->bindParam(":uuid", $id, PDO::PARAM_STR);
                                $encrypted_ipv4 = try_encrypt($ipv4);
                                $stmt->bindParam(":ipv4", $encrypted_ipv4, PDO::PARAM_STR);
                                $encrypted_operating_system = try_encrypt($os);
                                $stmt->bindParam(":operating_system", $encrypted_operating_system, PDO::PARAM_STR);
                                $stmt->execute();

	                        // Close the database connection
	                        db_close($db);
                        }
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
}

/********************************************
 * FUNCTION: IMPORT NEXPOSE VULNERABILITIES *
 ********************************************/
function import_nexpose_vulnerabilities()
{
	// Clear any risks to assets mappings for nexpose assets
	clear_nexpose_asset_associations();

        global $escaper;

        // Get the nexpose values
        $nexpose_server_name = try_decrypt(get_setting('nexpose_server_name'));
        $nexpose_server_port = try_decrypt(get_setting('nexpose_server_port'));
        $nexpose_user_id = try_decrypt(get_setting('nexpose_user_id'));
        $nexpose_password = try_decrypt(get_setting('nexpose_password'));

        // Create the nexpose authentication string
        $authentication_string = $nexpose_user_id . ":" . $nexpose_password;
        $authentication_string_base64 = base64_encode($authentication_string);

        $current_page = 0;
        $page_size = 10;
        $url = "https://" . $nexpose_server_name . ":" . $nexpose_server_port . "/api/3/vulnerabilities?size=".$page_size;

        // Make the call to the Nexpose API
        $opts = array(
                'ssl'=>array(
                        'verify_peer'=>false,
                        'verify_peer_name'=>false,
                ),
                'http'=>array(
                        'method'=>"GET",
                        'header'=>"content-type: application/json\r\n" .
                        "Authorization: Basic " . $authentication_string_base64 . "\r\n"
                )
        );
        $context = stream_context_create($opts);

        $vulnerabilities_json = @file_get_contents($url."&page=".$current_page, false, $context);
	$vulnerabilities_array = json_decode($vulnerabilities_json, true);
        $page_number = $vulnerabilities_array['page']['number'];
        $page_size = $vulnerabilities_array['page']['size'];
        $page_total_resources = $vulnerabilities_array['page']['totalResources'];
        $page_total_pages = $vulnerabilities_array['page']['totalPages'];

	write_debug_log("Importing Vulnerabilities from Nexpose");

	for ($current_page = 0; $current_page < $page_total_pages; $current_page++)
	{
		write_debug_log("Importing page " . $current_page . " / " . $page_total_pages . " of vulnerabilities from Nexpose.");
		// Get the contents for that page
		write_debug_log("Making a web query for " . $url."&page=".$current_page);
		$vulnerabilities_json = @file_get_contents($url."&page=".$current_page, false, $context);

        	// If file_get_contents didn't return false
        	if ($vulnerabilities_json != false)
        	{
                	// Convert the vulnerabilities into a json decoded string
                	$vulnerabilities_array = json_decode($vulnerabilities_json, true);
                	$vulnerabilities = $vulnerabilities_array['resources'];
                	$vulnerabilities_array = array();

               	 	write_debug_log("Importing Vulnerabilities from Nexpose");

                	foreach($vulnerabilities as $key=>$value)
                	{
				// Get the values from Nexpose
				$id = (isset($value['id']) ? $value['id'] : '' );
				write_debug_log("ID: " . $id);
				$description_text = (isset($value['description']['text']) ? $value['description']['text'] : '');
				write_debug_log("Description: " . $description_text);
				$added = (isset($value['added']) ? $value['added'] : '');
				write_debug_log("Added: " . $added);
				$categories = (isset($value['categories']) ? $value['categories'] : array());
				$cves = (isset($value['cves']) ? $value['cves'] : '');
				$cvss_accessComplexity = (isset($value['cvss']['v2']['accessComplexity']) ? $value['cvss']['v2']['accessComplexity'] : '');
				$cvss_accessVector = (isset($value['cvss']['v2']['accessVector']) ? $value['cvss']['v2']['accessVector'] : '');
				$cvss_authentication = (isset($value['cvss']['v2']['authentication']) ? $value['cvss']['v2']['authentication'] : '');
				$cvss_availabilityImpact = (isset($value['cvss']['v2']['availabilityImpact']) ? $value['cvss']['v2']['availabilityImpact'] : '');
				$cvss_confidentialityImpact = (isset($value['cvss']['v2']['confidentialityImpact']) ? $value['cvss']['v2']['confidentialityImpact'] : '');
				$cvss_exploitScore = (isset($value['cvss']['v2']['exploitScore']) ? $value['cvss']['v2']['exploitScore'] : '');
				$cvss_impactScore = (isset($value['cvss']['v2']['impactScore']) ? $value['cvss']['v2']['impactScore'] : '');
				$cvss_integrityImpact = (isset($value['cvss']['v2']['integrityImpact']) ? $value['cvss']['v2']['integrityImpact'] : '');
				$cvss_score = (isset($value['cvss']['v2']['score']) ? $value['cvss']['v2']['score'] : '');
				$cvss_vector = (isset($value['cvss']['v2']['vector']) ? $value['cvss']['v2']['vector'] : '');
				$denialOfService = (isset($value['denialOfService']) ? $value['denialOfService'] : '');

				$vulnerabilities_array[] = array("added"=>$added, "categories"=>$categories, "cves"=>$cves, "accessComplexity"=>$cvss_accessComplexity, "accessVector"=>$cvss_accessVector, "authentication"=>$cvss_authentication, "availabilityImpact"=>$cvss_availabilityImpact, "confidentialityImpact"=>$cvss_confidentialityImpact, "exploitScore"=>$cvss_exploitScore, "impactScore"=>$cvss_impactScore, "integrityImpact"=>$cvss_integrityImpact, "score"=>$cvss_score, "vector"=>$cvss_vector, "denialOfService"=>$denialOfService, "description"=>$description_text, "id"=>$id);

                        	// Create this vulnerability as a risk
                        	$subject = $value['title'];
				write_debug_log("Subject: " . $subject);
                        	$assessment = $value['description']['text'];
				write_debug_log("Assessment: " . $assessment);
                        	$scoring_method = 2; // Score with CVSS
                        	$risk_score = $value['severityScore'];
				write_debug_log("Risk Score: " . $risk_score);
                        	$status = "New";

                        	// See if we have any other risks with this subject
                        	$risk_id = get_risk_by_subject($subject);

                        	// If the risk subject doesn't exist
                        	if ($risk_id == false)
                        	{
                        	        // Submit the risk
                        	        $risk_id = submit_risk($status, $subject, $reference_id = "", $regulation = "", $control_number = "", $location = "", $source = "",  $category = "", $team = "", $technology = "", $owner = "", $manager = "", $assessment, $notes = "", $project_id = 0, $submitted_by=0, $submission_date=false, $additional_stakeholders="");

                                	// For each category
                                	foreach ($categories as $category)
                                	{
						$id = $risk_id + 1000;
						// Log the names of the categories to add as a tag for the risk
						write_debug_log("Adding category \"" . $category . "\" as a tag to risk ID " . $id);
                                	}

					// If the categories array is not empty
					if (!empty($categories))
					{
						// Create a comma separated string from the categories
						//$tags = "'";
						//$tags .= implode("', '", $categories);
						//$tags .= "'";
						//write_debug_log("Tags: [" . $tags . "]");

						// Add the tags to the risk
						//updateTagsOfType($risk_id, 'risk', [$tags]);
						updateTagsOfType($risk_id, 'risk', $categories);
					}

                        	        // Split the CVSS base vector by the slash
					$vector = $value['cvss']['v2']['vector'];
					write_debug_log("Vector: " . $vector);
                        	        $cvss = explode("/", $vector);

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

                                	// Set the default temporal values
                                	$Exploitability = "ND";
                                	$RemediationLevel = "ND";
                                	$ReportConfidence = "ND";

                                	// Submit the risk scoring
                                	submit_risk_scoring($risk_id, $scoring_method, $CLASSIC_likelihood="", $CLASSIC_impact="", $AccessVector, $AccessComplexity, $Authentication, $ConfImpact, $IntegImpact, $AvailImpact, $Exploitability, $RemediationLevel, $ReportConfidence, $CollateralDamagePotential="ND", $TargetDistribution="ND", $ConfidentialityRequirement="ND", $IntegrityRequirement="ND", $AvailabilityRequirement="ND", $DREADDamage="10", $DREADReproducibility="10", $DREADExploitability="10", $DREADAffectedUsers="10", $DREADDiscoverability="10", $OWASPSkill="10", $OWASPMotive="10", $OWASPOpportunity="10", $OWASPSize="10", $OWASPDiscovery="10", $OWASPExploit="10", $OWASPAwareness="10", $OWASPIntrusionDetection="10", $OWASPLossOfConfidentiality="10", $OWASPLossOfIntegrity="10", $OWASPLossOfAvailability="10", $OWASPLossOfAccountability="10", $OWASPFinancialDamage="10", $OWASPReputationDamage="10", $OWASPNonCompliance="10", $OWASPPrivacyViolation="10", $custom="10", $ContributingLikelihood="", $ContributingImpacts=[]);
                        	}

                        	// Get the assets for this vulnerability
				$assets = nexpose_get_assets_for_vulnerability_id($id);

				// If the array of assets isn't empty
				if (!empty($assets))
				{
					write_debug_log("Found assets associated with " . $id);

					// For each asset id in the array
					foreach ($assets as $uuid)
					{
						// Get the asset id if the nexpose asset exists
						$asset_id = nexpose_asset_exists($uuid);

						// If the asset id has not already been added
						if ($asset_id == false)
						{
							// Add the new asset
							$asset_id = nexpose_add_asset_by_id($uuid);
						}

						// Open the database connection
						$db = db_open();

						// Add the asset to the risk
						$stmt = $db->prepare("INSERT INTO `risks_to_assets` (`risk_id`, `asset_id`) VALUES (:risk_id, :asset_id)");
						$stmt->bindParam(":risk_id", $risk_id, PDO::PARAM_INT);
						$stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
						$stmt->execute();

						// Close the database connection
						db_close($db);
					}
				}
                	}

                	// Return the array of vulnerabilities
                	//return $vulnerabilities_array;
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
}

/*****************************************************
 * FUNCTION: NEXPOSE GET ASSETS FOR VULNERABILITY ID *
 *****************************************************/
function nexpose_get_assets_for_vulnerability_id($vulnerability_id)
{
        global $escaper;

        // Get the nexpose values
        $nexpose_server_name = try_decrypt(get_setting('nexpose_server_name'));
        $nexpose_server_port = try_decrypt(get_setting('nexpose_server_port'));
        $nexpose_user_id = try_decrypt(get_setting('nexpose_user_id'));
        $nexpose_password = try_decrypt(get_setting('nexpose_password'));

        // Create the nexpose authentication string
        $authentication_string = $nexpose_user_id . ":" . $nexpose_password;
        $authentication_string_base64 = base64_encode($authentication_string);

        $url = "https://" . $nexpose_server_name . ":" . $nexpose_server_port . "/api/3/vulnerabilities/" . $vulnerability_id . "/assets";

        // Make the call to the Nexpose API
        $opts = array(
                'ssl'=>array(
                        'verify_peer'=>false,
                        'verify_peer_name'=>false,
                ),
                'http'=>array(
                        'method'=>"GET",
                        'header'=>"content-type: application/json\r\n" .
                        "Authorization: Basic " . $authentication_string_base64 . "\r\n"
                )
        );
        $context = stream_context_create($opts);

	write_debug_log("Fetching the list of assets for vulnerability ID \"" . $vulnerability_id . "\"");

	// Fetch the list of assets
	$assets_json = @file_get_contents($url, false, $context);

	// Turn the json into a PHP array
	$assets_array = json_decode($assets_json, true);

	// If a list of asset ids exists
	if (isset($assets_array['resources']))
	{
		// Get the list of asset ids as an array
		$asset_ids_array = $assets_array['resources'];
	}
	else $asset_ids_array = null;

	// Return the array of asset ids
	return $asset_ids_array;
}

/**********************************
 * FUNCTION: NEXPOSE ASSET EXISTS *
 **********************************/
function nexpose_asset_exists($uuid)
{
	// Open the database connection
	$db = db_open();

	// Check if an asset with that id already exists
	$stmt = $db->prepare("SELECT asset_id FROM `import_export_integration_assets` WHERE `integration_name` = 'nexpose' AND `uuid` = :uuid");
	$stmt->bindParam(":uuid", $uuid, PDO::PARAM_INT);
	$stmt->execute();
	$asset = $stmt->fetchAll();

	// Close the database connection
	db_close($db);

	// If the asset array is not empty
	if (!empty($asset))
	{
		// Return the asset id
		return $asset[0]['asset_id'];
	}
	else return false;
}

/*************************************
 * FUNCTION: NEXPOSE ADD ASSET BY ID *
 *************************************/
function nexpose_add_asset_by_id($asset_id)
{
        global $escaper;

        // Get the nexpose values
        $nexpose_server_name = try_decrypt(get_setting('nexpose_server_name'));
        $nexpose_server_port = try_decrypt(get_setting('nexpose_server_port'));
        $nexpose_user_id = try_decrypt(get_setting('nexpose_user_id'));
        $nexpose_password = try_decrypt(get_setting('nexpose_password'));

        // Create the nexpose authentication string
        $authentication_string = $nexpose_user_id . ":" . $nexpose_password;
        $authentication_string_base64 = base64_encode($authentication_string);

        $url = "https://" . $nexpose_server_name . ":" . $nexpose_server_port . "/api/3/assets/" . $asset_id;

        // Make the call to the Nexpose API
        $opts = array(
                'ssl'=>array(
                        'verify_peer'=>false,
                        'verify_peer_name'=>false,
                ),
                'http'=>array(
                        'method'=>"GET",
                        'header'=>"content-type: application/json\r\n" .
                        "Authorization: Basic " . $authentication_string_base64 . "\r\n"
                )
        );
        $context = stream_context_create($opts);

        write_debug_log("Fetching the information for asset ID \"" . $asset_id . "\"");

        // Fetch the list of assets
        $asset_json = @file_get_contents($url, false, $context);

        // Turn the json into a PHP array
        $asset_array = json_decode($asset_json, true);

        $ipv4 = (isset($asset_array['ip']) ? $asset_array['ip'] : '');
        $hostname = (isset($asset_array['hostName']) ? $asset_array['hostName'] : '');
        $id = (isset($asset_array['id']) ? $asset_array['id'] : '');
        $os = (isset($asset_array['os']) ? $asset_array['os'] : '');

        write_debug_log("Importing asset ID: " . $id);
        write_debug_log("Importing asset IP: " . $ipv4);
        write_debug_log("Importing asset Hostname: " . $hostname);
        write_debug_log("Importing asset OS: " . $os);

	// Open the database connection
	$db = db_open();

	// Get the asset values
	$value = get_default_asset_valuation();
	$location = 0;
	$team = 0;
    $tags = "";
	$verified = 1;
	$details = "ID: " . $id . "\nOS: " . $os;

	// Set the asset name to the hostname
	$asset_name = $hostname;

	// If the asset name is empty
	if (empty($asset_name))
	{
		// Set the asset name to the ip
		$asset_name = $ipv4;
	}

	// Import the asset
	$asset_id = import_asset($ipv4, $asset_name, $value, $location, $team, $details, $tags, $verified);

	// Add or update the assets into the database
	$stmt = $db->prepare("INSERT IGNORE INTO `import_export_integration_assets` SET `integration_name` = 'nexpose', `asset_id` = :asset_id, `uuid` = :uuid, `ipv4` = :ipv4, `operating_system` = :operating_system ON DUPLICATE KEY UPDATE `integration_name` = 'nexpose', `asset_id` = :asset_id, `uuid` = :uuid, `ipv4` = :ipv4, `operating_system` = :operating_system");
	$stmt->bindParam(":asset_id", $asset_id, PDO::PARAM_INT);
	$stmt->bindParam(":uuid", $id, PDO::PARAM_STR);
	$encrypted_ipv4 = try_encrypt($ipv4);
	$stmt->bindParam(":ipv4", $encrypted_ipv4, PDO::PARAM_STR);
	$encrypted_operating_system = try_encrypt($os);
	$stmt->bindParam(":operating_system", $encrypted_operating_system, PDO::PARAM_STR);
	$stmt->execute();

	// Close the database connection
	db_close($db);

	// Return the asset ID
	return $asset_id;
}

/**********************************************
 * FUNCTION: CLEAR NEXPOSE ASSET ASSOCIATIONS *
 **********************************************/
function clear_nexpose_asset_associations()
{
        // Open the database connection
        $db = db_open();

        // Get the list of tenable assets
        $stmt = $db->prepare("DELETE FROM `risks_to_assets` WHERE `asset_id` IN (SELECT `asset_id` FROM `import_export_integration_assets` WHERE `integration_name`='nexpose');");
        $stmt->execute();

        // Close the database connection
        db_close($db);
}

?>

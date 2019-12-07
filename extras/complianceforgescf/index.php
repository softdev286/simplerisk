<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the controls and frameworks        *
 * that are associated with the ComplianceForge Secure Controls     *
 * Framework (SCF).                                                 *
 ********************************************************************/

// Extra Version
define('COMPLIANCEFORGE_SCF_EXTRA_VERSION', '20191130-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/governance.php'));
require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_complianceforge_scf_extra_database();

/**********************************************
 * FUNCTION: ENABLE COMPLIANCEFORGE SCF EXTRA *
 **********************************************/
function enable_complianceforge_scf_extra()
{
    prevent_extra_double_submit("complianceforge_scf", true);

    // Open the database connection
    $db = db_open();

    // Enable the complianceforge scf extra
    $stmt = $db->prepare("INSERT IGNORE INTO `settings` SET `name` = 'complianceforge_scf', `value` = 'true' ON DUPLICATE KEY UPDATE `value` = 'true'");
    $stmt->execute();

    // Create the ComplianceForge SCF table
    create_complianceforge_scf_table();

    // Audit log entry for Extra turned on
    $message = "Compliance Forge SCF was toggled on by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);
}

/***********************************************
 * FUNCTION: DISABLE COMPLIANCEFORGE SCF EXTRA *
 ***********************************************/
function disable_complianceforge_scf_extra()
{
    prevent_extra_double_submit("complianceforge_scf", false);

    // Open the database connection
    $db = db_open();

    // Delete the ComplianceForge SCF table
    delete_complianceforge_scf_table();

    // Disable the complianceforge scf extra
    $stmt = $db->prepare("UPDATE `settings` SET `value` = 'false' WHERE `name` = 'complianceforge_scf'");
    $stmt->execute();

    // Audit log entry for Extra turned off
    $message = "Compliance Forge SCF was toggled off by username \"" . $_SESSION['user'] . "\".";
    write_log(1000, $_SESSION['uid'], $message, 'extra');

    // Close the database connection
    db_close($db);
}

/*****************************************
 * FUNCTION: COMPLIANCEFORGE SCF VERSION *
 *****************************************/
function complianceforge_scf_version()
{
    // Return the version
    return COMPLIANCEFORGE_SCF_EXTRA_VERSION;
}

/*****************************************
 * FUNCTION: DISPLAY COMPLIANCEFORGE SCF *
 *****************************************/
function display_complianceforge_scf()
{
    global $escaper;
    global $lang;

    // Open the database connection
    $db = db_open();

    // If the frameworks were updated
    if (isset($_POST['update_frameworks']))
    {
        // De-select all frameworks except ComplianceForge SCF
        $stmt = $db->prepare("UPDATE `complianceforge_scf_frameworks` SET selected=false WHERE id != 1;");
        $stmt->execute();

        // Set all frameworks to disabled
        $stmt = $db->prepare("UPDATE `frameworks` SET status=2 WHERE value IN (SELECT framework_id FROM `complianceforge_scf_frameworks` WHERE selected=0);");
        $stmt->execute();

        $scf_framework_ids = empty($_POST['complianceforge_scf_frameworks']) ? [] : $_POST['complianceforge_scf_frameworks'];
        
        // For each framework
        foreach ($scf_framework_ids as $scf_framework_id)
        {
            // Set the framework to selected
            $stmt = $db->prepare("UPDATE `complianceforge_scf_frameworks` SET selected=true WHERE id=:id;");
            $stmt->bindParam(":id", $scf_framework_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Set the governance framework to active
            $stmt = $db->prepare("UPDATE `frameworks` SET status=1 WHERE value IN (SELECT framework_id FROM `complianceforge_scf_frameworks` WHERE id=:id);");
            $stmt->bindParam(":id", $scf_framework_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    echo "<form name=\"deactivate\" method=\"post\"><font color=\"green\"><b>" . $escaper->escapeHtml($lang['Activated']) . "</b></font> [" . complianceforge_scf_version() . "]&nbsp;&nbsp;<input type=\"submit\" name=\"deactivate\" value=\"" . $escaper->escapeHtml($lang['Deactivate']) . "\" /></form>\n";

    // Fetch the ComplianceForge controls list
    $stmt = $db->prepare("
        SELECT t1.*, t2.parent, t2.status framework_status
        FROM `complianceforge_scf_frameworks` t1
            INNER JOIN `frameworks` t2 ON t1.framework_id=t2.value
        ;
    ");
    $stmt->execute();
    $array = $stmt->fetchAll();

    echo "<form name=\"select_controls\" method=\"post\">\n";
    echo "<h6>" . $escaper->escapeHtml($lang['SelectYourControlFrameworks']) . ":</h6>\n";

    echo "<div id=\"scf_frameworks_tree\">\n";
        echo "<ul>";
            echo "<li class=\"collapsed\">";
                echo "<input type='checkbox' checked /><span>&nbsp;".$escaper->escapeHtml($lang['SelectAll'])."</span>";
                $saved_ids = [];
                echo make_scf_frameworks_tree($array, 0, $saved_ids);
            echo "</li>";
        echo "</ul>";
    echo "</div>\n";

    echo "<input type=\"submit\" name=\"update_frameworks\" value=\"" . $escaper->escapehtml($lang['Update']) . "\" />\n";
    echo "</form>\n";

    // Close the database connection
    db_close($db);
    
    
    echo "
        <script type=\"text/javascript\">
            $(document).ready(function() {
                $('#scf_frameworks_tree').tree({
                    collapseUiIcon: 'ui-icon-triangle-1-e',
                    expandUiIcon: 'ui-icon-triangle-1-se',
                });
            });
        </script>
    ";
    
}

/*******************************************
 * FUNCTION: MAKE SCF FRAMEWORKS TREE HTML *
 *******************************************/
function make_scf_frameworks_tree($frameworks, $parent, &$saved_ids)
{
    global $escaper, $lang;
    $html = "";
    foreach($frameworks as $framework){
        if($framework['parent'] == $parent){
            if(in_array($framework['framework_id'], $saved_ids)){
                continue;
            }

            $hasChild = true;
            $html .= "    <li >";
            // If framework is active
            if($framework['framework_status'] == 1)
            {
                $html .= "<input name='complianceforge_scf_frameworks[]' type='checkbox' value='".(int)$framework['id']."' checked /><span>&nbsp;".$escaper->escapeHtml($framework['name'])."</span>";
            }
            // If framework is inactive
            else
            {
                $html .= "<input name='complianceforge_scf_frameworks[]' type='checkbox' value='".(int)$framework['id']."' /><span>&nbsp;".$escaper->escapeHtml($framework['name'])."</span>";
            }
            $saved_ids[] = $framework['framework_id'];
            $html .= make_scf_frameworks_tree($frameworks, (int)$framework['framework_id'], $saved_ids);
            $html .= "</li>\n";
        }
    }
    if($html){
        $html = "<ul>\n".$html."</ul>\n";
    }

    return $html;
}

/**********************************************
 * FUNCTION: CREATE COMPLIANCEFORGE SCF TABLE *
 **********************************************/
function create_complianceforge_scf_table()
{
        // Open the database connection
        $db = db_open();

        // Create the complianceforge scf framework table
        $stmt = $db->prepare("
CREATE TABLE `complianceforge_scf` (
  `SCF Domain` text,
  `SCF Control` text,
  `SCF #` text,
  `Secure Controls Framework (SCF) Control Description` text,
  `Methods To Comply With SCF Controls` text,
  `SCF-B Business Mergers & Acquisitions` text,
  `SCF-E Embedded Technology` text,
  `SCF-G US Government Contracts` text,
  `SCF-H Healthcare Industry` text,
  `SCF-M Continuous Monitoring` text,
  `SCF-P Privacy Implications` text,
  `SCF-T Third-Party Risk` text,
  `Relative Control Weighting (1-10)` text,
  `AICPA SOC 2 (2016)` text,
  `AICPA SOC 2 (2017)` text,
  `CIS CSC v6.1` text,
  `CIS CSC v7 [draft]` text,
  `COBIT v5` text,
  `COSO v2013` text,
  `CSA CCM v3.0.1` text,
  `ENISA v2.0` text,
  `GAPP` text,
  `ISO 27001 v2013` text,
  `ISO 27002 v2013` text,
  `ISO 27018 v2014` text,
  `ISO 31000 v2009` text,
  `ISO 31010 v2009` text,
  `NIST 800-37` text,
  `NIST 800-39` text,
  `NIST 800-53 rev 4` text,
  `NIST 800-53 rev 5` text,
  `NIST 800-160` text,
  `NIST 800-171 rev 1` text,
  `NIST CSF` text,
  `OWASP Top 10 v2017` text,
  `PCI DSS v3.2` text,
  `UL 2900-1` text,
  `US COPPA1` text,
  `US DFARS 252.204-70xx` text,
  `US FACTA` text,
  `US FAR 52.204-21` text,
  `US FDA 21 CFR Part 11` text,
  `US FedRAMP [moderate]` text,
  `US FERPA` text,
  `US FFIEC` text,
  `US FINRA` text,
  `US FTC Act` text,
  `US GLBA` text,
  `US HIPAA` text,
  `US NERC CIP` text,
  `US NISPOM` text,
  `US Privacy Shield` text,
  `US SOX` text,
  `US CJIS Security Policy` text,
  `US - CA SB1386` text,
  `US - MA 201 CMR 17.00` text,
  `US - NY DFS 23 NYCRR500` text,
  `US - OR 646A` text,
  `US - TX BC521` text,
  `US-TX Cybersecurity Act` text,
  `EMEA EU ePrivacy [draft]` text,
  `EMEA EU GDPR` text,
  `EMEA EU PSD2` text,
  `EMEA Austria` text,
  `EMEA Belgium` text,
  `EMEA Czech Republic` text,
  `EMEA Denmark` text,
  `EMEA Finland` text,
  `EMEA France` text,
  `EMEA Germany` text,
  `EMEA Germany C5` text,
  `EMEA Greece` text,
  `EMEA Hungary` text,
  `EMEA Ireland` text,
  `EMEA Israel` text,
  `EMEA Italy` text,
  `EMEA Luxembourg` text,
  `EMEA Netherlands` text,
  `EMEA Norway` text,
  `EMEA Poland` text,
  `EMEA Portugal` text,
  `EMEA Russia` text,
  `EMEA Slovak Republic` text,
  `EMEA South Africa` text,
  `EMEA Spain` text,
  `EMEA Sweden` text,
  `EMEA Switzerland` text,
  `EMEA Turkey` text,
  `EMEA UAE` text,
  `EMEA UK` text,
  `APAC Australia` text,
  `APAC Australia ISM 2017` text,
  `APAC China DNSIP` text,
  `APAC Hong Kong` text,
  `APAC India ITR` text,
  `APAC Indonesia` text,
  `APAC Japan` text,
  `APAC Malaysia` text,
  `APAC New Zealand` text,
  `APAC New Zealand NZISM` text,
  `APAC Philippines` text,
  `APAC Singapore` text,
  `APAC Singapore MAS TRM` text,
  `APAC South Korea` text,
  `APAC Taiwan` text,
  `Americas Argentina` text,
  `Americas Bahamas` text,
  `Americas Canada PIPEDA` text,
  `Americas Chile` text,
  `Americas Columbia` text,
  `Americas Costa Rica` text,
  `Americas Mexico` text,
  `Americas Peru` text,
  `Americas Uruguay` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");
        $stmt->execute();

        // Load the complianceforge scf framework values
        $stmt = $db->prepare("
INSERT INTO complianceforge_scf VALUES ('Security & Privacy Governance','Security & Privacy Governance Program ','GOV-01','Mechanisms exist to facilitate the implementation of cybersecurity and privacy governance controls.','- Steering committee
- Digital Security Program (DSP)
- Written Information Security Program (WISP)','X','X','X','X','','X','X',10,'','','','','APO13.01 APO13.02','Principle 1',' ','','8.2.1','5.1','5.1.1 ','','','','','','PM-1','PM-1','','','','','12.1 
12.1.1 ','','','252.204-7008
252.204-7012','','','','','§ 1232h','','S-P (17 CFR §248.30)','','6801(b)(1) ','164.308(a)(1)(i) 
164.316(a)-(b) ','','8-100','','','5.1.1.1','','17.03(1)
17.04
17.03(2)(b)(2)',500.02,'','Sec. 521.052','Sec 10','','Art 32','Art 3','Sec 14
Sec 15','Art 16','Art 13','Art 41 ','Sec 5
Sec 32
Sec 33
Sec 34
Sec 35','Art 34','Sec 9
Sec 9a
Annex','OIS-01','Art 10','Sec 7','Sec 2','Sec 16
Sec 17','Sec 31
Sec 33
Sec 34
Sec 35','Art 3
Art 4','Sec 12
Sec 13
Sec 14','Sec 13
Sec 14','Art 1
Art 36','Art 14
Art 15
Art 16
Art 17','Art 7
Art 19','','Sec 19
Sec 21','','Sec 31','Art 7','Art 12','Sec 15
Sec 16','','APP Part 1
APP Part 11','','Sec 4','Principle 4','Sec 8','Art 9
Art 10
Art 12
Art 13
Art 14
Art 15
Art 16
Art 17
Art 18
Art 19
Art 20
Art 21
Art 22
Art 23
Art 24
Art 28','Art 20','Sec 9','Principle 4 ','5.1','Sec 25
Sec 27
Sec 28','Sec 12
Sec 24','3.2.2','Art 3
Art 29
Art 30','Art 27','Art 9
Art 30','Sec 6','Principle 7','Art 7','Art 4','Art 10','Art 19','Art 9
Art 16
Art 17','');
INSERT INTO complianceforge_scf VALUES ('Security & Privacy Governance','Publishing Security Policies ','GOV-02','Mechanisms exist to establish, maintain and disseminate cybersecurity and privacy policies, standards and procedures.','- Steering committee
- Digital Security Program (DSP)
- Written Information Security Program (WISP)
- Governance, Risk and Compliance Solution (GRC) tool (ZenGRC, Archer, RSAM, Metric stream, etc.)
- Wiki
- SharePoint','X','','X','X','','X','X',10,'','','','','APO13.01 APO13.02','Principle 12','AIS-04
GRM-05
GRM-06 ','SO1','8.2.1','5.2','5.1.1','','','','','','PM-1','PM-1','','','ID.GV-1','','12.1 
12.1.1 ','','','252.204-7008
252.204-7012','','','','','§ 1232h','D1.G.SP.B.4','S-P (17 CFR §248.30)','','6801(b)(1) ','164.308(a)(1)(i) 
164.316','','','','','5.1.1.1','','17.03(1)
17.04
17.03(2)(b)(2)',500.03,'','','Sec 10','','Art 32','Art 3','Sec 14
Sec 15','Art 16','','','','','','OIS-02
SA-01','','','','','','','','','','','','','','','','','','','','','0039
0040
0041
0042
0043
0886
0044
0787
0885
0046
0047
0887
1153
0888
1154
0049
0890','','','','','','','','5.2','','','3.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security & Privacy Governance','Periodic Review & Update of Security Documentation','GOV-03','Mechanisms exist to review cybersecurity and privacy policies, standards and procedures at planned intervals or if significant changes occur to ensure their continuing suitability, adequacy and effectiveness. ','- Governance, Risk and Compliance Solution (GRC) tool (ZenGRC, Archer, RSAM, Metric stream, etc.)
- Steering committee','X','','X','X','X','X','X',7,'CC7.2 ','CC7.2 ','','','APO13.03 ','Principle 12','GRM-08
GRM-09 ','SO1','8.2.1','','5.1.2 ','','','','','','PM-1','PM-1','','','','','','','','','','','','','§ 1232h','','','','','','CIP-003-6
R1','','','','5.1.1.1','','','','','','Sec 10','','Art 32','Art 3','Sec 14
Sec 15','Art 16','','','','','','SA-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','3.0.2
3.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security & Privacy Governance','Assigned Security Responsibilities ','GOV-04','Mechanisms exist to assign a qualified individual with the mission and resources to centrally-manage coordinate, develop, implement and maintain an enterprise-wide cybersecurity and privacy program. ','- NIST NICE Framework
- Chief Information Security Officer (CISO)','X','','X','X','','X','X',10,'CC1.1 ','CC1.1 ','','','APO01.06','Principle 2','GRM-05 ','','8.2.7','5.3','','','','','','','PL-9
PM-2
PM-6','PL-9
PM-2
PM-6','','','ID.AM-6','','12.5-12.5.5 ','','','','','','','','','D1.R.St.B.1
D1.TC.Cu.B.1','','','Safeguards Rule ','164.308(a)(2)
164.308(a)(3)
164.308(a)(4)
164.308(b)(1)
164.314','CIP-003-6
R3 & R4','8-101
8-311','','','5.10.1.5','','17.03(2)(a) ',500.04,'622(2)(d)(A)(i)','','Sec 9','','','','Sec 14
Sec 15','Art 16','','','','','','OIS-03','','','','','','','','','','','','','','','','','','','','','0013
0025
0027
0714
0741
0768
1071
1072','','','','','','','','3.1
3.2
3.3','','','3.1.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security & Privacy Governance','Measures of Performance ','GOV-05','Mechanisms exist to develop, report and monitor cybersecurity and privacy program measures of performance.','- Metrics
- Governance, Risk and Compliance Solution (GRC) tool (ZenGRC, Archer, RSAM, Metric stream, etc.)
- Enterprise Risk Management (ERM) solution','X','','X','X','X','X','X',6,'','','','','EDM02.03 APO01.04 EDM05.02 EDM05.03 MEA01.01 MEA01.03 MEA01.04 MEA01.05 ','Principle 5
Principle 9
Principle 13
Principle 14
Principle 15','','SO11
S12
S13
S14
S15','','9.1','','','5.6','','','','PM-6','PM-6 ','3.3.7
3.3.8','','PR.IP-8','','','','','','','','','','','D2.IS.Is.B.1
D2.IS.Is.E.2','','','','164.308(a)(6)(ii)
164.308(a)(8)','','8-311','','Sec 404 ','','','17.03(2)(j)','','622(2)(d)(A)(vi) 
622(2)(d)(B)(iii)','','Sec 10
Sec 11','','','Art 3','','','','','','','','SPN-01','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security & Privacy Governance','Key Performance Indicators (KPIs)','GOV-05.1','Mechanisms exist to develop, report and monitor Key Performance Indicators (KPIs) to assist organizational management in performance monitoring and trend analysis of the cybersecurity and privacy program.','- Key Performance Indicators (KPIs)','X','','','','X','','',6,'','','','','MEA01.01 MEA01.02','Principle 5
Principle 9
Principle 13
Principle 14','','','','','','','5.6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security & Privacy Governance','Key Risk Indicators (KRIs)','GOV-05.2','Mechanisms exist to develop, report and monitor Key Risk Indicators (KRIs) to assist senior management in performance monitoring and trend analysis of the cybersecurity and privacy program.','- Key Risk Indicators (KRIs)','X','','','','X','','',6,'','','','','MEA01.01','Principle 5
Principle 9
Principle 13
Principle 14','','','','','','','5.6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security & Privacy Governance','Contacts With Authorities ','GOV-06','Mechanisms exist to identify and document appropriate contacts within relevant law enforcement and regulatory bodies.','- Threat intelligence personnel
- Integrated Security Incident Response Team (ISIRT)','X','','X','X','X','X','X',5,'','','','','','Principle 15','','','','','6.1.3','','','','','','IR-6','IR-6','','','','','','','','','','','','','','','','','','','','1-303
4-218','','','5.3.1
5.10.1.5','','','','','Sec. 521.053','Sec 5
Sec 11','','Art 31
Art 41
Art 42
Art 43
Art 50','','','','','','','','','OIS-05','','','','','','','','','','','','','','','','','','','','','0879','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security & Privacy Governance','Contacts With Groups & Associations ','GOV-07','Mechanisms exist to establish contact with selected groups and associations within the cybersecurity & privacy communities to: 
 ▪ Facilitate ongoing cybersecurity and privacy education and training for organizational personnel;
 ▪ Maintain currency with recommended cybersecurity and privacy practices, techniques and technologies; and
 ▪ Share current security-related information including threats, vulnerabilities and incidents.
','- SANS
- CISO Executive Network
- ISACA chapters
- IAPP chapters
- ISAA chapters','X','','X','X','X','X','X',7,'','','','','BAI08.02','Principle 15','','','','','6.1.4 ','','','','','','AT-5
PM-15','AT-5
PM-15','','','','','5.1.2
6.1 ','','','','','','','','','','','','','164.308(A)(5)(ii) (ii)(A)','','8-101','','','','','','','','','Sec 5
Sec 11','','Art 40
Art 41','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Asset Governance ','AST-01','Mechanisms exist to facilitate the implementation of asset management controls.','- Generally Accepted Accounting Principles (GAAP)
- ITIL - Configuration Management Database (CMDB)','X','X','X','X','','X','X',10,'','','','2.6','','','','SO15','','','','','','','','','PM-5','PM-5 ','','','','','12.3.3
12.3.4
12.3.7 ','','','','','','','','','','','','','','','8-311','','','','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','AM-03','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Asset Inventories ','AST-02','Mechanisms exist to inventory system components that:
 ▪ Accurately reflects the current system; 
 ▪ Is at the level of granularity deemed necessary for tracking and reporting;
 ▪ Includes organization-defined information deemed necessary to achieve effective property accountability; and
 ▪ Is available for review and audit by designated organizational officials.
','- ManageEngine AssetExplorer
- LANDesk IT Asset Management Suite
- ServiceNow
- Solarwinds
- CrowdStrike
- JAMF
- ITIL - Configuration Management Database (CMDB)','X','','X','X','X','X','X',10,'','','1.4','1.6
2.1
2.5
12.9
16.12','BAI09.01
BAI09.05','','','SO15','','','8.1.1 ','','','','','','CM-8
PM-5','CM-8
PM-5','','3.4.1
3.4.2','ID.AM-1
ID.AM-2
ID.AM-4','','1.1.2 
2 2.4 ','','','','','','','CM-8','','D1.G.IT.B.1
D4.RM.Dd.B.2
D4.C.Co.B.3','','','','164.308(a)(1)(ii)(A)
164.308(a)(4)(ii)(A)
164.308(a)(7)(ii)(E )
164.308(b)
164.310(d)
164.310(d)(2)(iii)
164.314(a)(1)
164.314(a)(2)(i)(B)
164.314(a)(2)(ii)
164.316(b)(2)','','','','','5.7.2','','','','','','','','','','Sec 14
Sec 15','Art 16','','','','','','AM-01','','','','','','','','','','','','','','','','','','','','','0159
0336
1243
1301
1303','','','','','','','','','','','9.2.1
9.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Updates During Installations / Removals','AST-02.1','Mechanisms exist to update asset inventories as part of component installations, removals and asset upgrades. ','- CrowdStrike
- JAMF
- ITIL - Configuration Management Database (CMDB)','','','X','','X','','',7,'','','','','','','','','','','','','','','','','CM-8(1)','CM-8(1) ','','3.4.1 
3.4.2','','','','','','','','','','CM-8(1) ','','','','','','','','','','','5.7.2','','','','','','','','','','','','','','','','','AM-08','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Automated Unauthorized Component Detection','AST-02.2','Automated mechanisms exist to detect and respond to the presence of unauthorized hardware, software and firmware components.','- Discovery tools
- Vectra
- Tripwire
- Puppet
- Chef
- Microsoft SCCM
- NNT Change Tracker','X','','X','','X','','',3,'','','1.1
1.3','1.1
1.3
1.8','','','','','','','','','','','','','CM-8(3)','CM-8(3) ','','','','','','','','','','','','CM-8(3) ','','','','','','','','','','','5.7.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Component Duplication Avoidance ','AST-02.3','Mechanisms exist to prevent system components from being duplicated in other asset inventories. ','- ITIL - Configuration Management Database (CMDB)
- Manual or automated process','','','X','','X','','',2,'','','','','','','','','','','','','','','','','CM-8(5)','CM-8(5) ','','NFO','','','','','','','','','','CM-8(5) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Approved Deviations','AST-02.4','Mechanisms exist to track instances of approved deviations from the standardized baseline configuration. ','- NNT Change Tracker
- Tripwire
- BigFix
- SCCM
- Tripwire
- Puppet
- Chef
- Microsoft SCCM','X','','X','','X','','X',8,'','','','2.10','','','','','','','','','','','','','CM-8(6)','CM-8(6)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','SA-03','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Network Access Control (NAC)','AST-02.5','Network Access Control (NAC) mechanisms exist to detect unauthorized devices and disable network access to those unauthorized devices.','- Cisco NAC
- Aruba Networks
- Juniper NAC
- Packet Fence
- Symantec NAC
- Sophos NAC
- Bradford Networks NAC Director
- Cisco ISE
- ForeScout','X','','','','','','',4,'','','1.5
1.6','1.4
1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Dynamic Host Configuration Protocol (DHCP) Server Logging','AST-02.6','Mechanisms exist to enable Dynamic Host Configuration Protocol (DHCP) server logging to improve asset inventories and assist in detecting unknown systems. ','- Splunk
- Manual Process
- Build Automation Tools
- Chef
- Puppet
- Tripwire','X','','','','','','',3,'','','1.2','1.2
1.7','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Software Licensing Restrictions','AST-02.7','Mechanisms exist to ensure compliance with software licensing restrictions.','- Manual Process
- Tripwire','X','','','','','','X',8,'','','','2.1
2.10','BAI09.05','','','','','','18.1.2 ','','','','','','SC-18(2)','SC-18(2)','','','','','','','','','','','','','','','','','','','','','','','5.13.4.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Data Action Mapping','AST-02.8','Mechanisms exist to create and maintain a map of systems of where Personal Information (PI) is stored, transmitted or processed.','- Visio
- LucidChart','','','X','X','X','X','X',10,'','','','','','','','','','','','','','','','','CM-8(10)','CM-8(10)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Assigning Ownership of Assets ','AST-03','Mechanisms exist to assign asset ownership to a department, team or individual. ','','X','','','X','','','',8,'','','','','','','','','','','8.1.2 ','','','','','','','','','','','','2.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','Sec 14
Sec 15','Art 16','','','','','','AM-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','3.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Network Diagrams & Data Flow Diagrams (DFDs)','AST-04','Mechanisms exist to maintain network architecture diagrams that: 
 ▪ Contain sufficient detail to assess the security of the network''s architecture;
 ▪ Reflect the current state of the network environment; and
 ▪ Document all sensitive data flows.','- High-Level Diagram (HLD)
- Low-Level Diagram (LLD)
- Data Flow Diagram (DFD)
- SolarWinds
- Paessler
- PRTG','X','','X','X','X','X','X',10,'','','','12.9
16.12','','','IVS-13','','','','','','','','','','PL-2
SA-5(1)
SA-5(2)
SA-5(3)
SA-5(4)','PL-2
SA-4(1)
SA-4(2)','','','ID.AM-3','','1.1.2 
1.1.3 ','4.1
5.1
6.1
6.2
6.3
6.4
6.5
6.6
6.7
6.8
6.9','','','','','','','','D4.C.Co.B.4
D4.C.Co.Int.1','','','','164.308(a)(1)(ii)(A)
164.308(a)(3)(ii)(A)
164.308(a)(8)
164.310(d)','','','','','5.1.1.1
5.10.1.5','','','','','','','Art 9','Art 30','','Sec 14
Sec 15','Art 16','','','','','','KOS-06','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Security of Assets & Media','AST-05','Mechanisms exist to maintain strict control over the internal or external distribution of any kind of sensitive media. ','- ITIL - Configuration Management Database (CMDB)
- Definitive Software Library (DSL)','X','','','X','','','',8,'','','','','','','','','','','11.2.6 ','','','','','','','','','','','','9.6-9.6.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0161
0162
1298
1087
1299
1088
1300
0865
0685','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Unattended End-User Equipment ','AST-06','Mechanisms exist to implement enhanced protection measures for unattended systems to protect against tampering and unauthorized access.','- File Integrity Monitoring (FIM)
- Lockable casings
- Tamper detection tape
- Full Disk Encryption (FDE) 
- NNT Change Tracker','X','','','X','','','X',10,'','','','','','','','','','','11.2.8','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0161
1298
1087
1299
1088
1300
0865
0685','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Laptop Storage In Automobiles','AST-06.1','Mechanisms exist to educate users on the need to physically secure laptops and other mobile devices out of site when traveling, preferably in the trunk of a vehicle.','- Security awareness training
- Gamification','','','','X','','','',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','AM-03','','','','','','','','','','','','','','','','','','','','','0870','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Kiosks & Point of Sale (PoS) Devices','AST-07','Mechanisms exist to protect devices that capture sensitive data via direct physical interaction from tampering and substitution. ','- File Integrity Monitoring (FIM)
- Lockable casings
- Tamper detection tape
- Chip & PIN','','','','','','','X',10,'','','','','','','','','','','','','','','','','','','','','','','9.9-9.9.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Tamper Detection','AST-08','Mechanisms exist to inspect mobile devices for evidence of tampering upon return from geographic regions of concern or other known hostile environments that could lead to device compromise.','- \"Burner\" phones & laptops
- Tamper tape','','','','X','','','X',9,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.5','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Secure Disposal or Re-Use of Equipment ','AST-09','Mechanisms exist to securely destroy media when it is no longer needed for business or legal reasons. ','- Shred-it
- IronMountain
- sdelete (sysinternals)
- Bootnukem','X','','','X','','','X',10,'','','','','','','DCS-06','','','','11.2.7 ','','','','','','','','3.4.14','','','','9.8-9.8.2 ','','','','','','','','','','','','','','','','','','','','','','','Sec. 521.052(b)','','','','Art 24','','','','','','','','PS-05
PI-05','','','','','','','','','','','','','','','','','','','','','0311
0312
0313
0315
0316
0317
0318
0319
0321
0322
0329
0350
0361
0362
0363
0364
0366
0368
0370
0371
0372
0373
0374
0375
0378
0838
0839
0840
1069
1076
1160
1217
1218
1219
1220
1221
1222
1223
1224
1225
1226
1347
1360
1361
1455
','','','','','','','','12.6
13.5','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Return of Assets ','AST-10','Mechanisms exist to ensure that employees and third-party users return all organizational assets in their possession upon termination of employment, contract or agreement.','- Termination checklist
- Manual Process
- Native OS and Device Asset Tracking capabilities','X','','','X','','','X',10,'','','','','','','HRS-01','','','','8.1.4 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','AM-04','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Removal of Assets ','AST-11','Mechanisms exist to authorize, control and track systems entering and exiting organizational facilities. ','- RFID asset tagging
- RFID proximity sensors at access points
- Asset management software','X','','','X','','','X',8,'','','','','','','DCS-04','','','','11.2.5 ','','','','','','','','','','PR.DS-3','','','','','','','','','','','D1.G.IT.E.3
D1.G.IT.E.2','','','','164.308(a)(1)(ii)(A)
164.310(a)(2)(ii)
164.310(a)(2)(iii)
164.310(a)(2)(iv)
164.310(d)(1)
164.310(d)(2)','','','','','','','','','622(2)(d)(C)(ii)','','','','','','','','','','','','','AM-08','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Use of Personal Devices','AST-12','Mechanisms exist to restrict the possession and usage of personally-owned technology devices within organization-controlled facilities.','- BYOD policy','X','','','X','','','X',10,'','','','','','','MOS-04
MOS-12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Use of Third-Party Devices','AST-13','Mechanisms exist to reduce the risk associated with third-party assets that are attached to the network from harming organizational assets or exfiltrating organizational data.','- NAC
- Separate SSIDs for wireless networks
- SIEM monitoring/alerting
- Manual process to disable network all unused ports
- Network Access Control (NAC)
- Mobile Device Management (MDM) software
- Data Loss Prevention (DLP)','','','','','','','',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Usage Parameters','AST-14','Mechanisms exist to enforce and monitor usage parameters that limit the potential damage caused from the unauthorized alteration of system parameters. 
','- NNT Change Tracker','X','','X','','','','',7,'','','','2.10','','','','','','','','','','','','','SC-43','SC-43','','','','','','','','','','','','','','','','','','','','','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Tamper Protection','AST-15','Mechanisms exist to validate the integrity of configuration settings of critical systems, system components or services throughout all phases of the System Development lifecycle (SDLC).','- Tamper detection tape
- File Integrity Monitoring (FIM)
- NNT Change Tracker
- Tripwire','','','X','','','','',6,'','','','','','','','','','','','','','','','','SA-18','SA-18','','','','','','','','','','','','','','','','','','','','8-308','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Inspection of Systems, Components & Devices ','AST-15.1','Mechanisms exist to physically and logically inspect critical systems to detect evidence of tampering. ','- Tamper detection tape
- File Integrity Monitoring (FIM)
- NNT Change Tracker
- Tripwire','','','X','','','','',6,'','','','','','','','','','','','','','','','','SA-18(2)','SA-18(2)','','','','','9.1, 9.1.1, 9.9 9.9.1-9.9.3 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Asset Management','Bring Your Own Device (BYOD) Usage ','AST-16','Mechanisms exist to implement and govern a Bring Your Own Device (BYOD) program to reduce risk associated with personally-owned devices in the workplace.','- AirWatch
- SCCM
- Casper
- BYOD policy','X','','','','','','',10,'','','','','','','MOS-08
MOS-13
MOS-14
MOS-16
MOS-17
MOS-18
MOS-20','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','21.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Contingency Plan ','BCD-01','Mechanisms exist to facilitate the implementation of contingency planning controls.','- Business Continuity Plan (BCP)
- Disaster Recovery Plan (DRP)
- Continuity of Operations Plan (COOP)
- Business Impact Analysis (BIA)
- Criticality assessments','X','X','X','X','','X','X',10,'A1.3 ','A1.3 ','','','DSS04.01 DSS04.02 DSS04.03','','BCR-01
BCR-07 ','SO19
SO20','','','17.1.2 ','','','','','','CP-1
CP-2
IR-4(3)
PM-8','CP-1
CP-2
IR-4(3)
PM-8','','','RC.RP-1','','','','','','','','','CP-1 
CP-2 ','','D5.IR.Pl.B.6','','','','164.308(a)(7)
164.308(a)(7)(i)
164.308(a)(7)(ii)
164.308(a)(7)(ii)(C) 
164.310(a)(2)(i)
164.312(a)(2)(ii)','','8-104
8-603
8-614','','','5.3.2.1
5.3.2.2
5.10.1.5','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','BCM-01
BCM-02','','','','','','','','','','','','','','','','','','','','','0062
1159
0118
0119
0913
0914','','','','','','','','6.4','','','7.3.2
8.0.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Coordinate with Related Plans ','BCD-01.1','Mechanisms exist to coordinate contingency plan development with internal and external elements responsible for related plans. ','- Cybersecurity Incident Response Plan (CIRP)','','','X','','','X','',5,'','','','','','','','','','','','','','','','','CP-2(1)','CP-2(1) ','','','','','','','','','','','','CP-2(1) ','','','','','','','','','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Coordinate With External Service Providers','BCD-01.2','Mechanisms exist to coordinate internal contingency plans with the contingency plans of external service providers to ensure that contingency requirements can be satisfied.','- Business Continuity Plan (BCP)
- Disaster Recovery Plan (DRP)
- Continuity of Operations Plan (COOP)','','','X','','','X','',5,'','','','','','','','','','','','','','','','','CP-2(7)','CP-2(7) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1433
1434','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Identify Critical Assets ','BCD-02','Mechanisms exist to identify and document the critical systems, applications and services that support essential missions and business functions.','- Business Impact Analysis (BIA)
- Criticality assessments','X','','X','','','X','',9,'','','','','BAI09.02','','','SO20','','','','','','','','','CP-2(8)','CP-2(8) ','','','','','','','','','','','','CP-2(8) ','','','','','','','CIP-002-5.1a 
R1 & R2','','','','5.10.1.5','','','','','','','','','','','','','','','','','BCM-02','','','','','','','','','','','','','','','','','','','','','1458','','','','','','','','','','','8.3.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Resume All Missions & Business Functions','BCD-02.1','Mechanisms exist to plan for the resumption of all missions and business functions within Recovery Time Objectives (RTOs) of the contingency plan''s activation.','- Disaster Recovery Plan (DRP)
- Continuity of Operations Plan (COOP)
- Disaster recovery software','','','X','','','X','',8,'','','','','','','','','','','','','','','','','CP-2(4)','CP-2(4) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.3.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Continue Essential Mission & Business Functions','BCD-02.2','Mechanisms exist to plan for the continuance of essential missions and business functions with little or no loss of operational continuity and sustain that continuity until full system restoration at primary processing and/or storage sites.','- Disaster Recovery Plan (DRP)
- Continuity of Operations Plan (COOP)','','','X','','','X','',8,'','','','','','','','','','','','','','','','','CP-2(5)','CP-2(5) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.3.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Resume Essential Missions & Business Functions ','BCD-02.3','Mechanisms exist to resume essential missions and business functions within an organization-defined time period of contingency plan activation. ','- Business Continuity Plan (BCP)
- Disaster Recovery Plan (DRP)
- Continuity of Operations Plan (COOP)','','','X','','','X','',8,'','','','','','','','','','','','','','','','','CP-2(3)','CP-2(3) ','','','','','','','','','','','','CP-2(3) ','','','','','','','CIP-009-6
R1','','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.3.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Contingency Training','BCD-03','Mechanisms exist to train applicable contingency personnel in their contingency roles and responsibilities. ','- NIST NICE Framework
- Tabletop exercises','X','','X','','X','X','',5,'','','','','DSS04.04','','','','','','','','','','','','CP-3','CP-3','','','','','','','','','','','','CP-3 ','','','','','','','','8-615','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.3.3
8.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Simulated Events','BCD-03.1','Mechanisms exist to incorporate simulated events into contingency training to facilitate effective response by personnel in crisis situations.','- Tabletop exercises','','','X','','X','X','',3,'','','','','','','','','','','','','','','','','CP-3(1)','CP-3(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Automated Training Environments','BCD-03.2','Automated mechanisms exist to provide a more thorough and realistic contingency training environment.','','','','X','','','X','',1,'','','','','','','','','','','','','','','','','CP-3(2)','CP-3(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Contingency Plan Testing & Exercises ','BCD-04','Mechanisms exist to conduct tests and/or exercises to determine the contingency plan''s effectiveness and the organization’s readiness to execute the plan. ','- Simulated disasters / emergencies','X','','X','X','X','X','',6,'','','','','DSS04.04','','BCR-02 ','SO22','','','17.1.3 ','','','','','','CP-4','CP-4 ','','','','','','','','','','','','CP-4 ','','','','','','164.308(a)(7)(ii)(D)','CIP-009-6
R2','8-615','','','','','','','','','','','','','','','','','','','','BCM-04','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.1
8.3.2
8.3.3
8.3.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Coordinated Testing with Related Plans ','BCD-04.1','Mechanisms exist to coordinate contingency plan testing with internal and external elements responsible for related plans. ','- Playbooks
- Enterprise-wide Continuity of Operations Plan (COOP)','','','X','','X','X','',3,'','','','','','','','SO22','','','','','','','','','CP-4(1)','CP-4(1) ','','','','','','','','','','','','CP-4(1) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.1
8.3.5','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Contingency Plan Root Cause Analysis (RCA) & Lessons Learned','BCD-05','Mechanisms exist to conduct a Root Cause Analysis (RCA) and \"lessons learned\" activity every time the contingency plan is activated.','- Standardized Operating Procedures (SOP)
- Disaster Recovery Plan (DRP)
- Business Continuity Plan (BCP)
- Continuity of Operations Plan (COOP)','X','','X','X','','X','',9,'','','','','DSS04.05 DSS04.08','','','SO20
SO22','','','','','','','','','CP-4','CP-4 ','','','RC.IM-1','','','','','','','','','','','D5.IR.Pl.Int.4','','','','164.308(a)(7)(ii)(D)
164.308(a)(8)
164.316(b)(2)(iii)','','8-615','','','','','','','','','','','','','','','','','','','','BCM-04','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.3.12','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Contingency Plan Update','BCD-06','Mechanisms exist to keep contingency plans current with business needs and technology changes. ','- Offline / offsite documentation','X','','X','X','','','',10,'','','','','DSS04.08','','','SO19
SO20 ','','','','','','','','','CP-2','CP-2','','','RC.IM-2','','','','','','','','','','','D5.IR.Pl.Int.4
D5.IR.Te.Int.5','','','','164.308(a)(7)(ii)(D)
164.308(a)(8)','CIP-009-6
R3','8-614','','','','','','','','','','','','','','','','','','','','BCM-04','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Alternative Security Measures ','BCD-07','Mechanisms exist to implement alternative or compensating controls to satisfy security functions when the primary means of implementing the security function is unavailable or compromised. ','- Business Impact Analysis (BIA)
- Criticality assessments','','','X','','','','',9,'','','','','','','','','','','','','','','','','CP-13','CP-13','','','','','','','','','','','','','','','','','','','','8-605
8-607
8-610','','','','','','','','','','','','','','','','','','','','BCM-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Alternate Storage Site','BCD-08','Mechanisms exist to establish an alternate storage site that includes both the assets and necessary agreements to permit the storage and recovery of system backup information. ','- SunGard
- AWS
- Azure','X','','X','X','','','',9,'','','','','','','','','','','17.1.3
17.2.1 ','','','','','','CP-6','CP-6 ','','','','','','','','','','','','CP-6 ','','','','','','164.310(a)(2)(i)','','8-603','','','5.1.1.1
5.8.1','','','','','','','','','','','','','','','','','RB-03
BCM-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.5
8.2.6
8.2.7','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Separation from Primary Site ','BCD-08.1','Mechanisms exist to separate the alternate storage site from the primary storage site to reduce susceptibility to similar threats.','- SunGard
- AWS
- Azure','','','X','','','','',7,'','','','','','','','','','','','','','','','','CP-6(1)','CP-6(1) ','','','','','','','','','','','','CP-6(1) ','','','','','','','','','','','5.10.1.5','','','','','','','','','','','','','','','','','RB-09','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.5
8.2.6
8.2.7','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Accessibility ','BCD-08.2','Mechanisms exist to identify and mitigate potential accessibility problems to the alternate storage site in the event of an area-wide disruption or disaster.','- SunGard
- AWS
- Azure','','','X','','','','',5,'','','','','','','','','','','','','','','','','CP-6(3)','CP-6(3) ','','','','','','','','','','','','CP-6(3) ','','','','','','','','','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.5
8.2.6
8.2.7','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Alternate Processing Site','BCD-09','Mechanisms exist to establish an alternate processing site that provides security measures equivalent to that of the primary site.','- SunGard
- AWS
- Azure','X','','X','','','','',9,'','','','','','','','','','','17.1.1 ','','','','','','CP-7','CP-7 ','','','','','','','','','','','','CP-7 ','','','','','','','','8-603','','','5.1.1.1
5.8.1
5.10.1.5','','','','','','','','','','','','','','','','','RB-03
BCM-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.5
8.2.6
8.2.7','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Separation from Primary Site','BCD-09.1','Mechanisms exist to separate the alternate processing site from the primary processing site to reduce susceptibility to similar threats.','- SunGard
- AWS
- Azure','','','X','','','','',7,'','','','','','','','','','','','','','','','','CP-7(1)','CP-7(1) ','','','','','','','','','','','','CP-7(1) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','RB-09','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.5
8.2.6
8.2.7','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Accessibility','BCD-09.2','Mechanisms exist to identify potential accessibility problems to the alternate processing site and possible mitigation actions, in the event of an area-wide disruption or disaster.','- Business Continuity Plan (BCP)
- Continuity of Operations Plan (COOP)','','','X','','','','',5,'','','','','','','','','','','','','','','','','CP-7(2)','CP-7(2) ','','','','','','','','','','','','CP-7(2) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.5
8.2.6
8.2.7','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Priority of Service','BCD-09.3','Mechanisms exist to address priority-of-service provisions in alternate processing and storage sites that support availability requirements, including Recovery Time Objectives (RTOs). ','- Hot / warm / cold site contracts','','','X','','','','',6,'','','','','','','','','','','','','','','','','CP-7(3)','CP-7(3) ','','','','','','','','','','','','CP-7(3) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.5
8.2.6
8.2.7','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Telecommunications Services Availability','BCD-10','Mechanisms exist to reduce the likelihood of a single point of failure with primary telecommunications services.','- Alternate telecommunications services are maintained with multiple ISP / network providers','','','X','','','','',6,'','','','','','','','','','','','','','','','','CP-8
CP-8(2)
CP-11','CP-8
CP-8(2)
CP-11','','','','','','','','','','','','CP-8
CP-8(2)','','','','','','','','8-601
8-603
8-615','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1190','','','','','','','','','','','8.4.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Priority of Service Provisions','BCD-10.1','Mechanisms exist to formalize primary and alternate telecommunications service agreements to contain priority-of-service provisions that support availability requirements, including Recovery Time Objectives (RTOs). ','- Hot / warm / cold site contracts','','','X','','','','',6,'','','','','','','','','','','','','','','','','CP-8(1)','CP-8(1) ','','','','','','','','','','','','CP-8(1) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Data Backups','BCD-11','Mechanisms exist to create recurring backups of data, software and system images to ensure the availability of the data.','- Backup technologies & procedures
- Offline storage','X','','X','X','','','',10,'','','10.1','10.1','DSS04.07','','','','','','12.3.1 ','','','','','','CP-9
SC-28(2)','CP-9
SC-28(2)','','3.8.9','PR.IP-4','','','','','','','','','CP-9 ','','','','','','164.308(a)(7)(ii)(A)
164.308(a)(7)(ii)(B)
164.308(a)(7)(ii)(D)
164.310(a)(2)(i)
164.310(d)(2)(iv)','','8-603
8-612','','','5.10.1.2.2
5.10.1.2.3
5.10.1.5','','','','','','','','','','','','','','','','','RB-06','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.3
8.4.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Testing for Reliability & Integrity ','BCD-11.1','Mechanisms exist to routinely test backups that verifies the reliability of the backup process, as well as the integrity and availability of the data. ','','X','','X','','X','','',9,'','','10.2','10.2','','','','SO22','','','','','','','','','CP-9(1)','CP-9(1) ','','','','','','','','','','','','CP-9(1) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','RB-07
RB-08','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.3
8.3.4
8.4.1
8.4.2
8.4.3
8.4.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Separate Storage for Critical Information ','BCD-11.2','Mechanisms exist to store backup copies of critical software and other security-related information in a separate facility or in a fire-rated container that is not collocated with the system being backed up.','- IronMountain','X','','X','','','','',8,'','','','10.3
10.4','','','','','','','','','','','','','CP-9(3)','CP-9(3) ','','','','','','','','','','','','CP-9(3) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','RB-03
RB-09','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Information System Imaging','BCD-11.3','Capabilities exist to reimage assets from configuration-controlled and integrity-protected images that represent a secure, operational state.','- Acronis
- Docker
- VMWare','X','','','','','','',8,'','','','5.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Cryptographic Protection','BCD-11.4','Cryptographic mechanisms exist to prevent the unauthorized disclosure and modification of backup information.','- Backup technologies & procedures','X','','X','','','','',10,'','','','10.3','','','','','','','','','','','','','CP-9(8)','CP-9(8) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.2.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Information System Recovery & Reconstitution','BCD-12','Mechanisms exist to ensure the recovery and reconstitution of systems to a known state after a disruption, compromise or failure. ','','X','','X','X','','','',10,'','','','10.5','','','','','','','','','','','','','CP-10','CP-10 ','','','PR.IP-4','','','','','','','','','CP-10 ','','D5.IR.Pl.B.5
D5.IR.Te.E.3','','','','164.308(a)(7)(ii)(B)','','8-613','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.1.2
8.1.3
8.2.3
8.2.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Transaction Recovery','BCD-12.1','Mechanisms exist to utilize specialized backup mechanisms that will allow transaction recovery for transaction-based applications and services in accordance with Recovery Point Objectives (RPOs).','','','','X','','','','',9,'','','','','','','','','','','','','','','','','CP-10(2)','CP-10(2) ','','','','','','','','','','','','CP-10(2) ','','','','','','','','','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.1.2
8.1.3
8.2.3
8.2.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Failover Capability','BCD-12.2','Mechanisms exist to implement real-time or near-real-time failover capability to maintain availability of critical systems.','- Load balancers
- High Availability (HA) firewalls','','','X','','','','',8,'','','','','','','','','','','','','','','','','CP-10(5)','CP-10(5)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.1.2
8.1.3
8.2.3
8.2.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Electronic Discovery (eDiscovery)','BCD-12.3','Mechanisms exist to utilize electronic discovery (eDiscovery) that covers current and archived communication transactions.','','','','','','','','',8,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.1.2
8.1.3
8.2.3
8.2.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Business Continuity & Disaster Recovery','Backup & Restoration Hardware Protection ','BCD-13','Mechanisms exist to protect backup and restoration hardware and software.','','X','','X','','','','',8,'','','10.3 
10.4','10.3','','','','','','','','','','','','','CP-10(6)','CP-10(6)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','BCM-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Capacity & Performance Planning','Capacity & Performance Management ','CAP-01','Mechanisms exist to facilitate the implementation of capacity management controls to ensure optimal system performance for future capacity requirements.','- Splunk
- Resource monitoring','X','X','X','X','','X','X',8,'A1.1 ','A1.1 ','','','','','IVS-04','','','','12.1.3 ','','','','','','SC-5
SC-5(3)','SC-5
SC-5(3)','','','PR.DS-4','','','','','','','','','','','D5.IR.Pl.B.5
D5.IR.Pl.B.6
D5.IR.Pl.E.3
D3.PC.Im.E.4','','','','164.308(a)(1)(ii)(A)
164.308(a)(1)(ii)(B)
164.308(a)(7)
164.310(a)(2)(i)
164.310(d)(2)(iv)
164.312(a)(2)(ii)','','8-701','','','5.10.1.1
5.10.1.5','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','RB-01
RB-02','','','','','','','','','','','','','','','','','','','','','1438
1439
1440','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Capacity & Performance Planning','Resource Priority','CAP-02','Mechanisms exist to control resource utilization of systems that are susceptible to Denial of Service (DoS) attacks to limit and prioritize the use of resources.','- Splunk
- Resource monitoring','','','X','','','','',8,'','','','','','','','','','','','','','','','','SC-5
SC-5(1)
SC-5(2)
SC-6','SC-5
SC-5(1)
SC-5(2)
SC-6','','','','','','','','','','','','SC-6 ','','','','','','','','','','','5.10.1.1
5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Capacity & Performance Planning','Capacity Planning ','CAP-03','Mechanisms exist to conducted capacity planning so that necessary capacity for information processing, telecommunications and environmental support will exist during contingency operations. ','','','','X','','','','',8,'','','','','','','','','','','','','','','','','SC-5
SC-5(2)
CP-2(2)','SC-5
SC-5(2)
CP-2(2)','','','','','','','','','','','','CP-2(2) ','','','','','','','','','','','5.10.1.1
5.10.1.5','','','','','','','','','','','','','','','','','RB-01
RB-02','','','','','','','','','','','','','','','','','','','','','1438
1439
1440','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Change Management Program ','CHG-01','Mechanisms exist to facilitate the implementation of change management controls.','- VisibleOps methodology 
- ITIL infrastructure library
- NNT Change Tracker
- ServiceNow
- Remedy
- Tripwire
- Chef
- Puppet','X','X','X','X','','X','X',10,'CC7.3 ','CC7.3 ','','','','','','SO14','','','12.1.2 ','','','','','','CM-3','CM-3 ','3.4.10
3.4.13','','','','','','','','','','','','','','','','','','CIP-010-2
R1','8-103
8-104
8-311
8-610','','','5.7.1
5.7.1.1
5.10.4.1
5.13.4
5.13.4.1','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','BEI-03','','','','','','','','','','','','','','','','','','','','','1211
0912
0115
0117
0809','','','','','','','','6.3','','','6.2.3
7.1.1
7.1.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Configuration Change Control ','CHG-02','Mechanisms exist to govern the technical configuration change control processes.','- Change Control Board (CCB)
- Change Management Database (CMDB)
- Tripwire Enterprise
- Chef
- Puppet
- Solarwinds
- Docker
- VisibleOps methodology 
- ITIL infrastructure library','X','X','X','X','','X','X',10,'','','','','','','MOS-15','SO14','','','14.2.2 ','','','','','','CM-3','CM-3 ','3.4.10
3.4.13','3.4.3','PR.IP-3','','6.4-6.4.6','','','','','','','CM-3 ','','D1.G.IT.B.4','','','','','','8-103
8-104
8-311
8-610','','','5.7.1
5.7.1.1
5.10.4.1
5.13.4
5.13.4.1','','','','','','','','','','','','','','','','','BEI-06
BEI-08
BEI-10','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.2.3
7.1.6
7.1.7','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Prohibition Of Changes','CHG-02.1','Mechanisms exist to prohibit unauthorized changes, unless designated approvals are received.','- VisibleOps methodology 
- ITIL infrastructure library
- Manual processes/workflows
- Application whitelisting','','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','CM-3(1)','CM-3(1)','','','','','','','','','','','','','','','','','','','','','','','5.13.4','','','','','','','','','','','','','','','','','BEI-10','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.1.2
7.1.5
8.3.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Test, Validate & Document Changes ','CHG-02.2','Mechanisms exist to test and document proposed changes in a non-production environment before changes are implemented in a production environment.','- VisibleOps methodology 
- ITIL infrastructure library
- NNT Change Tracker
- VMware
- Docker','X','X','X','X','X','X','X',9,'CC7.4','CC7.4','','','','','','','1.2.6','','14.2.3 ','','','','','','CM-3(2)
CM-5(2)','CM-3(2)','','NFO','','','','','','','','','','','','','','','','','','','','','5.7.1
5.7.2
5.13.4','','','','','','','','','','','','','','','','','BEI-07
BEI-09','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.1.4
8.3.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Security Representative for Change','CHG-02.3','Mechanisms exist to include a cybersecurity representative in the configuration change control review process.','- Change Control Board (CCB)
- Change Advisory Board (CAB)
- VisibleOps methodology 
- ITIL infrastructure library','X','X','X','X','X','X','X',7,'','CC3.3','','','','','','','','','','','','','','','CM-3(4)','CM-3(4)','','3.4.3','','','','','','','','','','','','','','','','','','','','','5.11.3','','','','','','','','','','','','','','','','','BEI-04','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.1.3
8.3.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Security Impact Analysis for Changes ','CHG-03','Mechanisms exist to analyze proposed changes for potential security impacts, prior to the implementation of the change.','- VisibleOps methodology 
- ITIL infrastructure library
- Change management software','X','X','X','X','','X','X',9,'','','','','','','CCC-05','','1.2.6','','','','','','','','CM-4','CM-4 ','3.4.10
3.4.13','3.4.4','','','6.4-6.4.5.4 ','','','','','','','CM-4 ','','','','','','','','8-103
8-104
8-311
8-610','','','5.7.1
5.10.4.1
5.13.4.1','','','','','','','','','','','','','','','','','BEI-04
BEI-05','','','','','','','','','','','','','','','','','','','','','0809','','','','','','','','','','','7.1.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Access Restriction For Change','CHG-04','Mechanisms exist to enforce configuration restrictions in an effort to restrict the ability of users to conduct unauthorized changes.','- VisibleOps methodology 
- ITIL infrastructure library
- Role-based permissions
- Mandatory Access Control (MAC)
- Application whitelisting','X','X','X','X','','X','X',8,'','','','','','','','','','','','','','','','','CM-5','CM-5 ','3.4.10
3.4.13','3.4.5','','','','','','','','','','CM-5 ','','','','','','','','8-311
8-610','','','5.7.2','','','','','','','','','','','','','','','','','BEI-10','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Automated Access Enforcement / Auditing ','CHG-04.1','Mechanisms exist to perform after-the-fact reviews of configuration change logs to discover any unauthorized changes.','- VisibleOps methodology 
- ITIL infrastructure library
- NNT Change Tracker
- Manual review processes
- Tripwire
- Puppet
- Chef','','','X','','X','','',3,'','','','','','','','','','','','','','','','','CM-5(1)','CM-5(1) ','','','','','','','','','','','','CM-5(1) ','','','','','','','','','','','5.7.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.2.3
7.2.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Signed Components ','CHG-04.2','Mechanisms exist to prevent the installation of software and firmware components without verification that the component has been digitally signed using a certificate that is recognized and approved by the organization. ','- Privileged Account Management (PAM)
- Patch management tools
- OS configuration standards','','','X','','','','',3,'','','','','','','','','','','','','','','','','CM-5(3)','CM-5(3) ','','','','','','','','','','','','CM-5(3) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Dual Authorization for Change','CHG-04.3','Mechanisms exist to enforce a two-person rule for implementing changes to critical assets.','- Separation of Duties (SoD)','X','','X','','','','',6,'','','','','','','IAM-05','','','','','','','','','','AC-5
CM-5(4)','AC-5
CM-5(4)','','','','','','','','','','','','','','','','','','','','8-611','','','5.5.1
5.5.2
5.5.2.1
5.5.2.2
5.5.2.3
5.5.2.4
5.13.6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.2.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Limit Production / Operational Privileges (Incompatible Roles)','CHG-04.4','Mechanisms exist to limit operational privileges for implementing changes.','- Separation of Duties (SoD)
- Privileged Account Management (PAM)','','','X','','','','',6,'','','','','','','','','','','','','','','','','CM-5(5)','CM-5(5) ','','','','','','','','','','','','CM-5(5) ','','','','','','','','','','','5.7.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','11.1.6','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Library Privileges','CHG-04.5','Mechanisms exist to restrict software library privileges to those individuals with a pertinent business need for access. ','- Privileged Account Management (PAM)','','','X','','','','',8,'','','','','','','','','','','','','','','','','CM-5(6)','CM-5(6)','','','','','','','','','','','','','','','','','','','','','','','5.7.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Stakeholder Notification of Changes ','CHG-05','Mechanisms exist to ensure stakeholders are made aware of and understand the impact of proposed changes. ','- Change management procedures
- VisibleOps methodology 
- ITIL infrastructure library','X','','X','','','','X',9,'CC2.6','CC2.6','','','','','','','','','','','','','','','CM-9','CM-9','3.4.10
3.4.13','NFO','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','RB-20','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Security Functionality Verification','CHG-06','Mechanisms exist to verify the functionality of security controls when anomalies are discovered.','- Control Validation Testing (CVT)
- Security Test & Evaluation (STE)','X','','X','','X','X','',9,'','','','','','','','','','','','','','','','','CM-3(2)
SI-6','CM-3(2)
SI-6 ','3.4.10
3.4.13','','','','','','','','','','','SI-6 ','','','','','','','','8-613','','','5.7.1
5.13.4','','','','','','','','','','','','','','','','','BEI-08','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.1.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Change Management','Report Verification Results','CHG-06.1','Mechanisms exist to report the results of security and privacy function verification to senior management.','','','','X','','','X','',5,'','','','','','','','','','','','','','','','','','SI-6(3)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cloud Security','Cloud Services','CLD-01','Mechanisms exist to facilitate the implementation of cloud management controls to ensure cloud instances are secure and in-line with industry practices. ','- Data Protection Impact Assessment (DPIA)','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','2.6 
12.8.1','','','','',4,'','','','','','','','','','','','','','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','PI-03','','','','','','','','','','','','','','','','','','','','','1210
1395
1396
1397
1437
1438
1439
1440','','','','','','','','22.1','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cloud Security','Cloud Security Architecture ','CLD-02','Mechanisms exist to ensure the cloud security architecture supports the organization''s technology strategy to securely design, configure and maintain cloud employments. ','- Architectural review board
- System Security Plan (SSP)
- Security architecture roadmaps','X','','','','','','',10,'','','','','','','STA-03','','','','','','','','','','','','','','','','','','','','',4,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1437
1438
1439
1440','','','','','','','','','','','E.2.1
E.2.2
E.2.3
E.2.4
E.2.5
E.2.6
E.2.7','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cloud Security','Security Management Subnet ','CLD-03','Mechanisms exist to host security-specific technologies in a dedicated subnet.','- Security management subnet','X','','','','','','',6,'','','','11.7','','','','','','','','','','','','','','','','','','','','','','','',4,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','KOS-04','','','','','','','','','','','','','','','','','','','','','1385','','','','','','','','22.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cloud Security','Application & Program Interface (API) Security ','CLD-04','Mechanisms exist to ensure support for secure interoperability between components.','- Use only open and published APIs','','','','','','','',9,'','','','','','','','','','','','','','','','','','','','','','','','','','','',4,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','PI-01','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cloud Security','Virtual Machine Images ','CLD-05','Mechanisms exist to ensure the integrity of virtual machine images at all times. ','- File Integrity Monitoring (FIM)
- Docker
- NNT Change Tracker','X','','','','','','',8,'','','','5.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','22.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cloud Security','Multi-Tenant Environments ','CLD-06','Mechanisms exist to ensure multi-tenant owned or managed assets (physical and virtual) are designed, and governed such that provider and customer (tenant) user access is appropriately segmented from other tenant users.','- Security architecture review
- Defined processes to segment at the network, application, databases layers','','','','','','','',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','',4,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','RB-23','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cloud Security','Data Handling & Portability','CLD-07','Mechanisms exist to ensure cloud providers use secure protocols for the import, export and management of data in cloud-based services. ','- Data Protection Impact Assessment (DPIA)
- Security architecture review
- Encrypted data transfers (e.g. TLS or VPNs)','','','','','','','',4,'','','','','','','','','','','','','','','','','','','','','','','','','','','',4,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','PI-02
PI-03
PI-04','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cloud Security','Standardized Virtualization Formats ','CLD-08','Mechanisms exist to ensure interoperability by requiring cloud providers to use industry-recognized formats and provide documentation of custom changes for review.','- Data Protection Impact Assessment (DPIA)
- Manual review process
- Vendor risk assessments
- Independent vendor compliance assessments ','X','','','','','','',4,'','','','','','','IPY-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','22.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cloud Security','Geolocation Requirements for Processing, Storage and Service Locations','CLD-09','Mechanisms exist to control the location of cloud processing/storage based on business requirements, as well as statutory, regulatory and contractual obligations. ','- Data Protection Impact Assessment (DPIA)
','X','','X','','','X','',10,'','','','','','','DSA-02','','','','','','','','','','SA-9(5)','SA-9(5) ','','','','','','','','','',4,'','','','','','','','','','','','','','','','','','','','Art 6
Art 9','','','','','','','','','','UP-02
RB-03','','','','','','','','','','','','','','','','','','','','','','','','','','','','','20.1','','','','','','','','','','','','','','Art 23');
INSERT INTO complianceforge_scf VALUES ('Cloud Security','Sensitive Data In Public Cloud Providers','CLD-10','Mechanisms exist to limit and manage the storage of sensitive data in public cloud providers. ','- Data Protection Impact Assessment (DPIA)
- Security and network architecture diagrams
- Data Flow Diagram (DFD)','','','','','','','',6,'','','','','','','','','','','','','','','','','','','','','','','','','','','',4,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cloud Security','Cloud Access Point (CAP)','CLD-11','Mechanisms exist to utilize Cloud Access Points (CAPs) to provide boundary protection and monitoring functions that both provide access to the cloud and protect the organization from the cloud.','- Next Generation Firewall (NGF)
- Web Application Firewall (WAF)
- Network Routing / Switching
- Intrusion Detection / Protection (IDS / IPS)
- Data Loss Prevention (DLP)
- Full Packet Capture','X','','','','','','',7,'','','','12.9','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','19.1-2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Compliance','Statutory, Regulatory & Contractual Compliance ','CPL-01','Mechanisms exist to facilitate the implementation of relevant legislative statutory, regulatory and contractual controls.','- Governance, Risk and Compliance Solution (GRC) tool (ZenGRC, Archer, RSAM, Metric stream, etc.)
- Steering committee','X','X','X','X','','X','X',10,'','','','','MEA03.01 MEA03.02','','','SO25','','','18.1.1 ','','','','','','PM-8','PM-8','3.3
3.3.3
3.3.4
3.4
3.4.1
3.4.2
3.4.3','','ID.GV-3
PR.IP-5','','12.1','',6502,'','','','§ 11.10','','','D1.G.Ov.E.2
D3.PC.Am.B.11','','','6801(b)(3)','164.306
164.308
164.308(a)(7)(i)
164.308(a)(7)(ii)(C)
164.308(a)(8)
164.310
164.312
164.314
164.316
164.316(b)(2)(iii)','','8-104','','','','','',500.19,'','','','Art 2
Art 3
Art 15','Art 1
Art 2
Art 3
Art 23
Art 32
Art 42
Art 43
Art 50','Art 3
Art 29','Sec 14
Sec 15','Art 16','Art 13','Art 5
Art 41 ','Sec 5
Sec 32
Sec 33
Sec 34
Sec 35','Art 34','Sec 9
Sec 9a
Annex','UP-04
COM-01','Art 10','Sec 7','Sec 2','Sec 16
Sec 17','Sec 26
Sec 31
Sec 33
Sec 34
Sec 35','Art 3
Art 4','Sec 12
Sec 13
Sec 14','Sec 13
Sec 14','Art 1
Art 36','Art 14
Art 15
Art 16
Art 17','Art 7
Art 19','','Sec 9
Sec 19
Sec 21','','Sec 31','Art 7','Art 12','Sec 15
Sec 16','','APP Part 11','0007
0008
1353
1354
1355','Sec 4','Principle 4','Sec 8','','Art 20','Sec 9','Principle 4','','Sec 25','Sec 24','','Art 3
Art 29','Art 27','Art 9','Sec 6','Principle 7','Art 7','Art 4','Art 10','Art 19','Art 9
Art 16
Art 17','Art 23');
INSERT INTO complianceforge_scf VALUES ('Compliance','Security Controls Oversight ','CPL-02','Mechanisms exist to provide a security controls oversight function.','- Governance, Risk and Compliance Solution (GRC) tool (ZenGRC, Archer, RSAM, Metric stream, etc.)
- Steering committee
- Formalized SDLC program
- Formalized DevOps program
- Control Validation Testing (CVT)
- Security Test & Evaluation (STE)','X','','X','X','X','X','',10,'','','','','APO01.03
DSS01.04 DSS06.04 MEA02.01 MEA02.02 ','','AAC-02
AAC-03 ','SO25','8.2.7','9.3','','','','','3.6','','CA-7
CA-7(1)
PM-14','CA-7
CA-7(1)
PM-14','3.3.8','3.12.1
3.12.2
3.12.3
3.12.4
NFO','DE.DP-5
PR.IP-7','','12.11 
12.11.1 ','','','','','','§ 11.10','CA-7
CA-7(1) ','','D5.IR.Pl.Int.3
D1.RM.RMP.E.2
D1.G.Ov.A.2','','','','164.306(e)
164.308(a)(7)(ii)(D)
164.308(a)(8)
164.316(b)(2)(iii)','','8-202
8-302
8-610
8-614','','','5.4.1
5.4.1.1
5.4.3
5.11.1.1
5.11.3','','','','622(2)(B)(iii)','','Sec 10
Sec 11','','Art 5','Art 3','','','Art 13','Art 41 ','Sec 5
Sec 32
Sec 33
Sec 34
Sec 35','Art 34','Sec 9
Sec 9a
Annex','UP-04
SA-03','Art 10','Sec 7','Sec 2','Sec 16
Sec 17','Sec 31
Sec 33
Sec 34
Sec 35','Art 3
Art 4','Sec 12
Sec 13
Sec 14','Sec 13
Sec 14','Art 1
Art 36','Art 14
Art 15
Art 16
Art 17','Art 7
Art 19','','Sec 19
Sec 21','','Sec 31','Art 7','Art 12','Sec 15
Sec 16','','APP Part 11','0001
0003
0710
0711
0713
0876
1061
1379','Sec 4','Principle 4','Sec 8','','Art 20','Sec 9','Principle 4','6.1','Sec 25
Sec 29','Sec 24','3.2.3','','Art 27','Art 9','','Principle 7','Art 7','','','','','');
INSERT INTO complianceforge_scf VALUES ('Compliance','Security Assessments ','CPL-03','Mechanisms exist to ensure managers regularly review the processes and documented procedures within their area of responsibility to adhere to appropriate security policies, standards and other applicable requirements.','- Control Validation Testing (CVT)
- Security Test & Evaluation (STE)
- Governance, Risk and Compliance Solution (GRC) tool (ZenGRC, Archer, RSAM, Metric stream, etc.)','X','','X','X','X','X','',10,'','','','','MEA03.03','','','','','9.2','18.2.2 ','','','','','','CA-2','CA-2','3.4.9','','','','','','','','','','§ 11.10','','','','','','','','','8-610','','','5.11.1.1
5.11.1.2
5.11.2','','17.03(2)(h) ','','622(2)(B)(i)-(iv) ','','Sec 11','','Art 5','Art 3
Art 29','','','Art 13','Art 41 ','','Art 34','','UP-04
SPN-02','','Sec 7','Sec 2','Sec 16
Sec 17','Sec 31','Art 3
Art 4','Sec 12
Sec 13
Sec 14','Sec 13
Sec 14','Art 1
Art 36','Art 14
Art 15','Art 7','','Sec 19
Sec 21','','Sec 31','','','','','','0007
0008','','','','','Art 20','Sec 9','','4.1
4.2
4.3
4.4
4.5','Sec 25','Sec 24','3.2.3','','','Art 9','','','Art 7','','','','','');
INSERT INTO complianceforge_scf VALUES ('Compliance','Independent Assessors ','CPL-03.1','Mechanisms exist to utilize independent assessors at planned intervals or when the system, service or project undergoes significant changes.','- Control Validation Testing (CVT)
- Security Test & Evaluation (STE)','X','','','','','','',6,'','','','','MEA03.04','','','','','9.2','18.2.1','','','','','','','','3.4.9','','','','','','','','','','','','','','','','','','','','','','','','','','','','Sec 11','','','Art 3','','','','','','','','UP-04
SPN-03
COM-03','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Compliance','Functional Review Of Security Controls ','CPL-03.2','Mechanisms exist to regularly review assets for compliance with the organization’s cybersecurity and privacy policies and standards. ','- Internal audit program
- NNT Change Tracker
- Operational review processes
- Regular/yearly policy and standards review process
- Governance, Risk and Compliance Solution (GRC) (ZenGRC, Archer, RSAM, Metric stream, etc.)','X','','X','','X','','X',8,'CC4.1','CC4.1','','','MEA02.02 MEA02.03','','','','','','18.2.3 ','','','','','','CA-2','CA-2','3.4.9','','','','','','','','','','','','','','','','','','','8-610','','','5.11.1.1
5.11.1.2
5.11.2','','','','','','Sec 11','','','Art 3','','','','','','','','COM-01','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Compliance','Audit Activities ','CPL-04','Mechanisms exist to plan audits that minimize the impact of audit activities on business operations.','- Internal audit program','X','','','','','','',5,'','','','','MEA02.03','','AAC-01 ','','','9.2','12.7.1 ','','','','','','','','3.4.9','','','','','','','','','','','','','','','','','','CIP-014-2
R2','','','','','','','','','','','','','Art 3','','','','','','','','SPN-02
COM-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Configuration Management Program','CFG-01','Mechanisms exist to facilitate the implementation of configuration management controls.','- NNT Change Tracker
- Change Management Database (CMDB)
- Baseline hardening standards
- Formalized DevOps program
- Control Validation Testing (CVT)
- Security Test & Evaluation (STE)','X','X','X','X','','X','X',10,'','','','5.5','BAI10.01','','','','','','','','','','','','CM-1
CM-9','CM-1
CM-9 ','3.3.5
3.4.7
3.4.8','NFO','','','1.1.5 ','','','','','','','CM-1
CM-9 ','','','','','','','','8-311
8-610','','','5.1.1.1
5.7.1
5.7.2
5.13.4','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','','','','','','','','','','','','','','','','','','','','','','0289
0290
0291
0292','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','System Hardening Through Baseline Configurations ','CFG-02','Mechanisms exist to develop, document and maintain secure baseline configurations for technology platform that are consistent with industry-accepted system hardening standards. ','- Defense Information Security Agency (DISA) Secure Technology Implementation Guides (STIGs)
- Center for Internet Security (CIS) Benchmarks
- NNT Change Tracker','X','X','X','X','X','','',10,'','','3.1','5.1
5.2
5.3
5.5
6.2
8.3
8.6
9.1
11.1
13.4
13.9
14.8
15.7
15.8','BAI10.02','','GRM-01
IVS-07','','','','14.1.1','','','','','','CM-2
CM-6
SA-8','CM-2
CM-6 
PL-10','3.4.7
3.4.8','3.4.1
3.4.2','PR.IP-1
PR.IP-3','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','1.1
1.1.1 
2.2-2.2.4','','','252.204-7008 ','','','','CM-2
CM-6 
SA-8','','D3.PC.Im.B.5
D1.G.IT.B.4','','','','164.308(a)(8)
164.308(a)(7)(i)
164.308(a)(7)(ii)','','8-202
8-311
8-610','','','5.7.1
5.7.1.1
5.7.2
5.13.4','','','','','','','','','','','','','','','','','RB-22','','','','','','','','','','','','','','','','','','','','','1406
1407
1408
1409
1467
0383
0380
1469
1410
0382
1345
1411
1412
1470
1245
1246
1247
1248
1249
1250
1251','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Reviews & Updates','CFG-02.1','Mechanisms exist to review and update baseline configurations:
 ▪ At least annually;
 ▪ When required due to so; or
 ▪ As part of system component installations and upgrades.','- Defense Information Security Agency (DISA) Secure Technology Implementation Guides (STIGs)
- Center for Internet Security (CIS) Benchmarks
- NNT Change Tracker','X','','X','','X','','',8,'','','','','BAI10.04
BAI10.05','','','','','','','','','','','','CM-2(1)','CM-2
CM-2(1)','','NFO','','','','','','','','','','CM-2(1)','','','','','','','','','','','5.13.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Automated Central Management & Verification ','CFG-02.2','Automated mechanisms are utilized to govern and report on baseline configurations of the systems. ','- NNT Change Tracker','X','','X','','X','','',7,'','','2.1
3.6
9.3
11.3','5.4
11.3','BAI10.03','','','','','','','','','','','','CM-2(2)
CM-6(1)','CM-2(2)
CM-6(1)','','','','','','','','','','','','CM-2(2)
CM-6(1)','','','','','','','CIP-010-2
R2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Retention Of Previous Configurations ','CFG-02.3','Mechanisms exist to retain previous versions of baseline configuration to support roll back. ','','X','','X','','','','',3,'','','3.3','','','','','','','','','','','','','','CM-2(3)','CM-2(3)','','','','','','','','','','','','CM-2(3)','','','','','','','','','','','5.13.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Development & Test Environment Configurations','CFG-02.4','Mechanisms exist to manage baseline configurations for development and test environments separately from operational baseline configurations to minimize the risk of accidental changes.','- NNT Change Tracker','','','X','X','','','',5,'','','','','','','','','','','','','','','','','CM-2(6)','CM-2(6)','','','','','6.4.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Configure Systems, Components or Services for High-Risk Areas ','CFG-02.5','Mechanisms exist to configure systems utilized in high-risk areas with more restrictive baseline configurations.','','X','','X','','','','',8,'','','11.1','','','','','','','','','','','','','','CM-2(7)','CM-2(7) ','','NFO','','','','','','','','','','CM-2(7) ','','','','','','','','','','','5.13.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Network Device Configuration File Synchronization','CFG-02.6','Mechanisms exist to configure network devices to synchronize startup and running configuration files. ','','X','','','X','','','',7,'','','3.7','','','','','','','','','','','','','','','','','','','','1.2.2 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Approved Deviations ','CFG-02.7','Mechanisms exist to document and manage approved deviations to standardized configurations.','- NNT Change Tracker','X','','','','','','',9,'','','11.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0291
0292','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Respond To Unauthorized Changes ','CFG-02.8','Mechanisms exist to respond to unauthorized changes to configuration settings as security incidents. ','- Service Level Agreements (SLAs)
- NNT Change Tracker','X','','X','','','','',9,'','','3.2','','','','','','','','','','','','','','CM-6(2)','CM-6(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Baseline Tailoring','CFG-02.9','Mechanisms exist to allow baseline controls to be specialized or customized by applying a defined set of tailoring actions that are specific to:
 ▪ Mission / business functions;
 ▪ Operational environment;
 ▪ Specific threats or vulnerabilities; or
 ▪ Other conditions or situations that could affect mission / business success.','- DISA STIGs','X','X','X','','','','',9,'','','','','','','','','','','','','','','','','','PL-11','','','','A5
A6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1414
1415','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Least Functionality','CFG-03','Mechanisms exist to configure systems to provide only essential capabilities by specifically prohibiting or restricting the use of ports, protocols, and/or services. ','- NNT Change Tracker','X','X','X','X','','','',10,'','','9.1','9.1
9.5
15.7
15.8','','','IAC-03 ','','','','','','','','','','CM-7','CM-7 ','','3.4.6','PR.PT-3','A6','1.1.5
1.2.1
2.2.2
2.2.4
2.2.5','','','','',2,'','CM-7 ','','D3.PC.Am.B.7
D3.PC.Am.B.4
D3.PC.Am.B.3
D4.RM.Om.Int.1','','','','164.308(a)(3)
164.308(a)(4)
164.310(a)(2)(iii)
164.310(b)
164.310(c)
164.312(a)(1)
164.312(a)(2)(i)
164.312(a)(2)(ii)
164.312(a)(2)(iv)','','','','','5.7.1.1','','17.03(2)(a) 
17.03(2)(g)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Periodic Review','CFG-03.1','Mechanisms exist to periodically review system configurations to identify and disable unnecessary and/or non-secure functions, ports, protocols and services.','- NNT Change Tracker','X','','X','','X','','',8,'','','','9.5','MEA02.03','','','','','','9.2.5
9.2.6','','','','','','CM-7(1)','CM-7(1) ','','3.4.7','','','','','','','','','','CM-7(1) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Prevent Program Execution','CFG-03.2','Automated mechanisms exist to prevent the execution of unauthorized software programs. ','- NNT Change Tracker','X','','X','','','','',7,'','','','8.6','','','','','','','','','','','','','CM-7(2)','CM-7(2) ','','3.4.7','','','','','','','','','','CM-7(2) ','','','','','','','','','','','5.7.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Unauthorized or Authorized Software (Blacklisting or Whitelisting)','CFG-03.3','Mechanisms exist to whitelist or blacklist applications in an order to limit what is authorized to execute on systems.','- NNT Change Tracker','X','','X','X','','','',5,'','','2.2','2.2
2.7
2.8','','','','','','','','','','','','','CM-7(4)
CM-7(5)
SC-18(4)','CM-7(5) 
SC-18(4)','','3.4.8','','','','8.5','','','','','','CM-7(5)','','','','','','','','','','','5.7.1.1
5.13.4.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0843
1413
0845
0846
0955
1471
1392
1391
0957','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Software Usage Restrictions ','CFG-04','Mechanisms exist to enforce software usage restrictions to comply with applicable contract agreements and copyright laws.','','X','','X','','','','',9,'','','2.3','2.3','','','','','','','','','','','','','CM-10','CM-10 ','','','','','','','','','','','','CM-10 ','','','','','','','','','','','5.2.1.4
5.7.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Open Source Software','CFG-04.1','Mechanisms exist to establish parameters for the secure use of open source software. ','- Acceptable Use Policy (AUP)','','','X','','','','',9,'','','','','','','','','','','','','','','','','CM-10(1)','CM-10(1)','','','','','','','','','','','','CM-10(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Unsupported Internet Browsers & Email Clients ','CFG-04.2','Mechanisms exist to allow only approved Internet browsers and email clients to run on systems.','','X','','','','','','',7,'','','7.1
7.2
7.3
7.5','7.1
7.2
7.3
7.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','User-Installed Software','CFG-05','Mechanisms exist to restrict the ability of non-privileged users to install unauthorized software.','- Privileged Account Management (PAM)','','','X','','','','',10,'','','','','','','','','','','','','','','','','CM-11','CM-11 ','','3.4.9','','','','','','','','','','CM-11 ','','','','','','','','','','','5.7.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Unauthorized Installation Alerts','CFG-05.1','Mechanisms exist to configure systems to generate an alert when the unauthorized installation of software is detected. ','- NNT Change Tracker','','','X','','','','',8,'','','','','','','','','','','','','','','','','CM-11(1)','CM-11(1)
CM-8(3)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Prohibit Installation Without Privileged Status ','CFG-05.2','Mechanisms exist to prohibit the installation of software, unless the action is performed by a privileged user or service.','','X','','X','','','','',10,'','','','','','','CCC-04','','','','','','','','','','CM-11(2)','CM-11(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Configuration Management','Split Tunneling','CFG-02.10','Mechanisms exist to prevent systems from creating split tunneling connections or similar techniques that could be used to exfiltrate data.','','X','','X','X','','','X',8,'','','','12.12','','','','','','','','','','','','','SC-7(7)','SC-7(7) ','','3.13.7','','','','','','','','','','SC-7(7) ','','','','','','','','','','','5.10.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Continuous Monitoring','MON-01','Mechanisms exist to facilitate the implementation of enterprise-wide monitoring controls.','- Splunk','X','X','X','X','X','X','X',10,'','','4.6','6.2
14.7','DSS01.03 DSS05.07','','IVS-06 ','SO21','','','12.4.1 ','','','','','','AU-1
SI-4','AU-1
SI-4','','NFO','DE.CM-1
DE.DP-1
DE.DP-2
PR.PT-1','A2
A5
A10','10.1
10.6-10.6.3 
10.8-10.8.1 ','','','','',10,'§ 11.10','AU-1 ','','D3.DC.An.B.2
D3.DC.An.B.3
D1.G.SP.B.3
D2.MA.Ma.B.1
D2.MA.Ma.B.2
D3.DC.Ev.B.4
D1.G.Ov.E.2','','','','164.308(a)(1)(i)
164.308(a)(1)(ii)(D)
164.308(a)(5)(ii)(B)
164.308(a)(5)(ii)(C)
164.308(a)(2)
164.308(a)(3)(ii)(A)
164.308(a)(3)(ii)(B)
164.308(a)(4)
164.308(a)(8)
164.310(a)(2)(iii)
164.310(a)(2)(iv)
164.310(d)(2)(iii)
164.312(a)(1)
164.312(a)(2)(ii)
164.312(b)
164.312(e)(2)(i)','CIP-007-6
R4','8-602','','','5.10.1.3','','',500.06,'','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','RB-10','','','','','','','','','','','','','','','','','','','','','0120
0121
0580
0109
1228
1435','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Intrusion Detection & Prevention Systems (IDS & IPS)','MON-01.1','Mechanisms exist to implement Intrusion Detection / Prevention Systems (IDS / IPS) technologies on critical systems, key network segments and network choke points.','','X','X','X','','X','','',10,'','','','','','','','','','','','','','','','','SI-4(1)','SI-4(1) ','','','','A2
A5
A10','','','','','','','','SI-4(1) ','','','','','','','','','','','5.10.1.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Automated Tools for Real-Time Analysis ','MON-01.2','Mechanisms exist to utilize a Security Incident Event Manager (SIEM) or similar automated tool, to support near real-time analysis and the escalation of events. ','','X','X','X','X','X','','',10,'','','','6.2
6.4
6.5','','','','','','','','','','','','','SI-4(2)','SI-4(2) ','','','','A2
A5
A10','10.6-10.6.3','','','','','','','SI-4(2) ','','','','','','','','','','','5.10.1.3','','','','','','','','','','','','','','','','','SIM-05','','','','','','','','','','','','','','','','','','','','','0109
1228','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Inbound & Outbound Communications Traffic ','MON-01.3','Mechanisms exist to continuously monitor inbound and outbound communications traffic for unusual or unauthorized activities or conditions.','','X','X','X','','X','','',9,'','','','','','','','','','','','','','','','','SI-4(4)','SI-4(4) ','','3.14.6','','A2
A5
A10','','','','','','','','SI-4(4) ','','','','','','','','','','','5.10.1.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','System Generated Alerts ','MON-01.4','Mechanisms exist to monitor, correlate and respond to alerts from physical, cybersecurity, privacy and supply chain activities to achieve integrated situational awareness. ','','X','X','X','','X','','',7,'','','','14.7','','','','','','','','','','','','','SI-4(5)','SI-4(5) ','','NFO','','A2
A5
A10','','','','','','','','SI-4(5) ','','','','','','','','','','','5.10.1.3','','','','','','','','','','','','','','','','','RB-12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Wireless Intrusion Detection System (WIDS)','MON-01.5','Mechanisms exist to utilize Wireless Intrusion Detection / Protection Systems (WIDS / WIPS) to identify rogue wireless devices and to detect attack attempts via wireless networks. ','','','','X','','X','','',5,'','','','','','','','','','','','','','','','','SI-4(14)
SI-4(15)','SI-4(14)
SI-4(15)','','','','','11.1','','','','','','','SI-4(14) ','','','','','','','','','','','5.13.1
5.13.1.1
5.13.1.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Host-Based Devices ','MON-01.6','Mechanisms exist to utilize Host-based Intrusion Detection / Prevention Systems (HIDS/HIPS) to actively alert on or block unwanted activities and send logs to a Security Incident Event Manager (SIEM), or similar automated tool, to maintain situational awareness.','- NNT Change Tracker','','','X','','X','','',8,'','','','','','','','','','','','','','','','','SI-4(23)','SI-4(23) ','','','','','','','','','','','','SI-4(23) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','File Integrity Monitoring (FIM)','MON-01.7','Mechanisms exist to utilize a File Integrity Monitor (FIM) or similar change-detection technology on critical assets to generate alerts for unauthorized modifications. ','- NNT Change Tracker','X','X','X','X','X','X','',9,'','','','','','','','SO12','','','','','','','','','','SI-4(25)','','','','A3
A4
A5
AT
A8
A10','11.5-11.5.1 ','','','','','','','','','','','','','','','8-613','','','','','','','','','','','','','','','','','','','','RB-12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Reviews & Updates ','MON-01.8','Mechanisms exist to review event logs on an ongoing basis and escalate incidents in accordance with established timelines and procedures.','- Security Incident Event Manager (SIEM)
- Splunk','X','X','X','','X','','',10,'','','','6.2
6.4
6.5','','','','','','','','','','','','','AU-2(3)','AU-2(3) ','','3.3.3','','A2
A5
A10','','','','','','','','AU-2(3) ','','','','','','','','','','','5.4.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Proxy Logging ','MON-01.9','Mechanisms exist to log all Internet-bound requests, in order to identify prohibited activities and assist incident handlers with identifying potentially compromised systems. ','','X','','','','','','',8,'','','7.4','7.4
12.5
13.5
13.6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Deactivated Account Activity ','MON-01.10','Mechanisms exist to monitor deactivated accounts for attempted usage.','- Security Incident Event Manager (SIEM)
- Splunk','X','X','','','','','',9,'','','16.8','16.6','','','','','','','','','','','','','','','','','','A10','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Centralized Collection of Security Event Logs','MON-02','Mechanisms exist to utilize a Security Incident Event Manager (SIEM) or similar automated tool, to support the centralized collection of security-related event logs.','- Security Incident Event Manager (SIEM)
- Splunk','X','X','X','X','X','','',10,'','','','6.2
6.4
6.5
6.6','','','','SO17
SO20
SO21','','','','','','','','','AU-2
AU-2(3)
AU-6
SI-4','AU-2
AU-2(3)
AU-6
SI-4 ','','3.3.1
3.3.2
3.14.6
3.14.7','','A10','10.2.1-10.2.7 11.4 ','','','','','','§ 11.10','AU-2
AU-6
SI-4 ','','','','','','164.308(a)(1)(ii)(D) 164.308(a)(5)(ii)(c) ','','8-602','','','5.4.1
5.4.1.1
5.4.3
5.10.1.3','','17.03(2)(b)(3)
17.04(4)','','622(2)(d)(B)(iii)','','','','','','','','','','','','','RB-13','','','','','','','','','','','','','','','','','','','','','1405
1344
0587
0988','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Correlate Monitoring Information','MON-02.1','Automated mechanisms exist to correlate logs from across the enterprise by a Security Incident Event Manager (SIEM) or similar automated tool, to maintain situational awareness.','- Security Incident Event Manager (SIEM)
- Splunk','X','X','X','','X','','',9,'','','6.6','6.7','DSS05.07','','','','','','','','','','','','AU-6(3)
IR-4(4)
SI-4(16)','AU-6(3)
IR-4(4)
SI-4(16)','','3.3.5','','A10','','','','','','','','AU-6(3)
SI-4(16) ','','','','','','','','','','','5.3.2.1
5.3.2.2
5.4.1
5.4.3','','','','','','','','','','','','','','','','','RB-12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Content of Audit Records ','MON-03','Mechanisms exist to configure systems to produce audit records that contain sufficient information to, at a minimum:
 ▪ Establish what type of event occurred;
 ▪ When (date and time) the event occurred;
 ▪ Where the event occurred;
 ▪ The source of the event;
 ▪ The outcome (success or failure) of the event; and 
 ▪ The identity of any user/subject associated with the event. ','','X','X','X','X','','','',10,'','','6.2','6.8','','','','','','','','','','','','','AU-3','AU-3 ','','3.3.1
3.3.2','','A10','10.3-10.3.6 ','','','','','','§ 11.10','AU-3 ','','','','','','','','8-602','','','5.4.1','','',500.06,'','','','','','','','','','','','','','RB-14','','','','','','','','','','','','','','','','','','','','','0582
0583
1176
0584
0987
0585','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Sensitive Audit Information','MON-03.1','Mechanisms exist to protect sensitive data contained in log files. ','','X','X','X','','','','',10,'','','','6.8','','','','','','','','','','','','','AU-3(1)
AU-6(1)','AU-3(1)
AU-6(1) ','','3.3.1 
3.3.2','','','','','','','','','','AU-3(1)
AU-6(1) ','','','','','','','','','','','5.4.1
5.4.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Audit Trails','MON-03.2','Mechanisms exist to link system access to individual users or service accounts.','','X','X','','X','','','',10,'','','','6.8','DSS06.05','','','','','','','','','','','','','','','','','A10','10.1','','','','','','§ 11.10','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Privileged Functions Logging ','MON-03.3','Mechanisms exist to log and review the actions of users and/or services with elevated privileges.','- Security Incident Event Manager (SIEM)
- Splunk','X','X','X','X','X','','',10,'','','5.4
5.5','4.3
4.4
6.8','','','','','','','12.4.3 ','','','','','','AU-6(8)','AU-6(8) ','','','','A10','10.2-10.2.7','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','RB-15','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Verbosity Logging for Boundary Devices ','MON-03.4','Mechanisms exist to verbosely log all traffic (both allowed and blocked) arriving at network boundary devices, including firewalls, Intrusion Detection / Prevention Systems (IDS/IPS) and inbound and outbound proxies.','','X','X','','','','','',5,'','','6.5','6.8
12.2
12.8','','','','','','','','','','','','','','','','','','A10','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Limit Personally Identifiable Information (PII) In Audit Records','MON-03.5','Mechanisms exist to limit Personal Information (PI) contained in audit records to the elements identified in the privacy risk assessment','- Data Protection Impact Assessment (DPIA)','','X','X','X','','X','',10,'','','','','','','','','','','','','','','','','','AU-3(3)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Audit Storage Capacity ','MON-04','Mechanisms exist to allocate and proactively manage sufficient audit record storage capacity to reduce the likelihood of such capacity being exceeded. ','','X','','X','','','','',8,'','','6.3','6.3','','','','','','','','','','','','','AU-4
AU-5(1)','AU-4 ','','','','','','','','','','','','AU-4 ','','','','','','','','8-602','','','5.4.6
5.4.7','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Response To Audit Processing Failures','MON-05','Mechanisms exist to alert appropriate personnel in the event of a log processing failure and take actions to remedy the incident.','- Security Incident Event Manager (SIEM)
- Splunk','X','X','X','','','','',8,'','','','','','','','','','','','','','','','','AU-5','AU-5 ','','3.3.4','','A10','','','','','','','','AU-5 ','','','','','','','','8-602','','','5.4.2','','','','','','','','','','','','','','','','','RB-16','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Real-Time Alerts of Audit Failure','MON-05.1','Mechanisms exist to provide 24x7x365 near real-time alerting capability when a log processing failure occurs. ','- Security Incident Event Manager (SIEM)
- Splunk','','','X','','X','','',6,'','','','','','','','','','','','','','','','','AU-5(2)
SI-4(12)','AU-5(2)
SI-4(12)','','','','','','','','','','','','','','','','','','','','','','','5.4.2
5.10.1.3','','','','','','','','','','','','','','','','','RB-16','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Monitoring Reporting ','MON-06','Mechanisms exist to provide an event log report generation capability to aid in detecting and assessing anomalous activities. ','- Security Incident Event Manager (SIEM)
- Splunk','X','','X','X','','','',7,'','','6.4','','','','','','','','','','','','','','AU-7
AU-7(1)
AU-12','AU-7
AU-7(1)
AU-12','','3.3.1
3.3.2
3.3.6','DE.DP-4','','','','','','','','','AU-7
AU-7(1)
AU-12','','D3.DC.Ev.B.2
D5.ER.Is.B.1
D5.ER.Is.E.1','','','','164.308(a)(6)(ii)
164.314(a)(2)(i)(C)
164.314(a)(2)(iii)','','8-602','','','5.4.1
5.4.1.1
5.4.1.1.1
5.4.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Query Parameter Audits of Personally Identifiable Information (PII)','MON-06.1','Mechanisms exist to provide and implement the capability for auditing the parameters of user query events for data sets containing Personal Information (PI).','','X','X','X','','','','',3,'','','','','','','','','','','','','','','','','','AU-12(4)','','','','A10','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Time Stamps ','MON-07','Mechanisms exist to configure systems to use internal system clocks to generate time stamps for audit records. ','','X','X','X','X','','','',10,'','','','6.1','','','IVS-03','','','','','','','','','','AU-8','AU-8 ','','3.3.7','','A10','10.4-10.4.3 ','','','','','','§ 11.10','AU-8 ','','','','','','','','8-602','','','5.4.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1305','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Synchronization With Authoritative Time Source','MON-07.1','Mechanisms exist to synchronize internal system clocks with an authoritative time source. ','- Network Time Protocol (NTP)','X','X','X','X','','','',10,'','','6.1','6.1','','','','','','','','','','','','','AU-8(1)','AU-8(1) ','','3.3.7','','A10','','','','','','','§ 11.10','AU-8 ','','','','','','','','','','','5.4.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1305','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Protection of Audit Information ','MON-08','Mechanisms exist to protect event logs and audit tools from unauthorized access, modification and deletion.','- Security Incident Event Manager (SIEM)
- Splunk','X','X','X','X','','','',10,'','','','','','','IVS-01','','','','12.4.2 ','','','','','','AU-9','AU-9','','3.3.8','','A5
A10','10.5-10.5.5 ','','','','','','§ 11.10','AU-9','','','','','','164.312(c)(1)','','8-602','','','5.4.5','','','','','','','','','','','','','','','','','RB-15','','','','','','','','','','','','','','','','','','','','','0586
0989','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Audit Backup on Separate Physical Systems / Components ','MON-08.1','Mechanisms exist to back up audit records onto a physically different system or system component than the Security Incident Event Manager (SIEM) or similar automated tool.','- Security Incident Event Manager (SIEM)
- Splunk','X','X','X','','','','',5,'','','','','','','','','','','','','','','','','AU-4(1)
AU-9(2)','AU-4(1) 
AU-9(2)','','','','A10','','','','','','','','AU-9(2)','','','','','','','','','','','5.4.6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Access by Subset of Privileged Users ','MON-08.2','Mechanisms exist to restrict access to the management of event logs to privileged users with a specific business need.','- Security Incident Event Manager (SIEM)
- Splunk','X','X','X','','','','',8,'','','','','','','','','','','','','','','','','AU-9(4)','AU-9(4)','','3.3.9','','A10','','','','','','','','AU-9(4)','','','','','','','','','','','5.4.5','','','','','','','','','','','','','','','','','RB-15','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Non-Repudiation','MON-09','Mechanisms exist to utilize a non-repudiation capability to protect against an individual falsely denying having performed a particular action. ','','','','X','X','','','',8,'','','','','','','','','','','','','','','','','AU-10','AU-10','','','','','','','','','','','','','','','','','','164.312(c)(2) ','','8-602','','','','','','','','','','','','Art 26','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Audit Record Retention','MON-10','Mechanisms exist to retain audit records for a time period consistent with records retention requirements to provide support for after-the-fact investigations of security incidents and to meet statutory, regulatory and contractual retention requirements. ','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','AU-11','AU-11 ','','3.3.1','','A10','10.7','','','','','','','AU-11 ','','','','','','','','8-602','','','5.4.6
5.4.7','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0859
0991','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Monitoring For Information Disclosure','MON-11','Mechanisms exist to monitor for evidence of unauthorized exfiltration or disclosure of non-public information. ','- Content filtering solution
- Review of social media outlets','','','X','','','','',8,'','','','','','','','','','','','','','','','','AU-13','AU-13','','','','','','','','','','','','','','','','','','','','8-602','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Session Audit ','MON-12','Mechanisms exist to provide session audit capabilities that: 
 ▪ Capture and log all content related to a user session; and
 ▪ Remotely view all content related to an established user session in real time.','','X','','X','','','','',7,'','','14.6','','','','','','','','','','','','','','AU-14','AU-14','','','','','','','','','','','','','','','','','','','','8-602','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Alternate Audit Capability ','MON-13','Mechanisms exist to provide an alternate audit capability in the event of a failure in primary audit capability.','','','','X','','','','',3,'','','','','','','','','','','','','','','','','AU-15','AU-15','','','','','','','','','','','','','','','','','','','','8-602','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Cross-Organizational Monitoring ','MON-14','Mechanisms exist to coordinate sanitized audit information among external organizations to identify anomalous events when audit information is shared across organizational boundaries, without giving away sensitive or critical business data.','','','','X','','','','',3,'','','','','','','','','','','','','','','','','AU-16','AU-16','','','','','','','','','','','','','','','','','','','','8-602','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Covert Channel Analysis ','MON-15','Mechanisms exist to conduct covert channel analysis to identify aspects of communications that are potential avenues for covert channels.','','X','','X','','','','',3,'','','12.10','','','','','','','','','','','','','','SC-31','SC-31 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Anomalous Behavior','MON-16','Mechanisms exist to detect and respond to anomalous behavior that could indicate account compromise or other malicious activities.','','X','X','X','X','X','X','X',10,'','','16.10','16.8','','','','','','','','','','','','','SI-4(11)','SI-4(11)','','','DE.AE-1','','10.6-10.6.2 ','','','','','','','','','D3.DC.Ev.B.1
D4.C.Co.B.4','','','','164.308(a)(1)(ii)(D)
164.312(b)','','','','','5.10.1.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0431','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Insider Threats','MON-16.1','Mechanisms exist to monitor internal personnel activity for potential security incidents.','','X','','','X','','','',8,'','','','','','','','','','','','','','','','','','','','','DE.CM-3','','','','','','','','','','','D3.DC.An.A.3','','','','164.308(a)(1)(ii)(D)
164.308(a)(3)(ii)(A)
164.308(a)(5)(ii)(C)
164.312(a)(2)(i)
164.312(b)
164.312(d)
164.312(e) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Third-Party Threats','MON-16.2','Mechanisms exist to monitor third-party personnel activity for potential security incidents.','','X','','','X','','','',8,'','','','','','','','','','','','','','','','','','','','','DE.CM-6','','','','','','','','','','','D4.RM.Om.Int.1','','','','164.308(a)(1)(ii)(D)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Monitoring','Unauthorized Activities','MON-16.3','Mechanisms exist to monitor for unauthorized activities, accounts, connections, devices, and software.','','X','','','X','','','',8,'','','','','','','','','','','','','','','','','','','','','DE.CM-7','','','','','','','','','','','D3.DC.Ev.B.3','','','','164.308(a)(1)(ii)(D)
164.308(a)(5)(ii)(B)
164.308(a)(5)(ii)(C)
164.310(a)(1)
164.310(a)(2)(ii)
164.310(a)(2)(iii)
164.310(b)
164.310(c)
164.310(d)(1)
164.310(d)(2)(iii)
164.312(b)
164.314(b)(2)(i)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Use of Cryptographic Controls ','CRY-01','Mechanisms exist to facilitate the implementation of cryptographic protections controls using known public standards and trusted cryptographic technologies.','- Key and certificate management solutions
- BitLocker and EFS
- dm- crypt, LUKS','X','X','X','X','','X','X',10,'','','','11.4
13.2
14.2
14.5','','','EKM-03
EKM-04','','','','10.1.1 ','','','','','','SC-8(2)
SC-13
SC-13(1)
SI-7(6)','SC-8(2)
SC-13
SI-7(6)','','3.13.11','','','2.2.3 
4.1 ','10.3','','','','','§ 11.10','SC-13 ','','','','','','164.312(e)(2)(ii)
164.314(b)(1)-(2)','','9-400','','','5.10.1.2
5.10.1.2.1
5.10.1.2.2
5.10.1.2.3
5.10.1.5','','',500.15,'','','','Art 5','Art 5
Art 32','Art 20
Art 30','Sec 14
Sec 15','Art 16','','','','','','KRY-01','','','','','','','','','','','','','','','','','','','','','1161
0457
0460
0481','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Alternate Physical Protection ','CRY-01.1','Cryptographic mechanisms exist to prevent unauthorized disclosure of information as an alternate to physical safeguards. ','','','','X','X','','','',5,'','','','','','','','','','','','','','','','','SC-8(1)','SC-8(1) ','','3.13.8','','','','','','','','','','SC-8(1) ','','','','','','164.312(e)(2)(i)
164.312(e)(1) 
164.312(e)(2)(i)','','','','','5.10.1.2
5.10.1.2.1','','17.04(3)','','622(2)(d)(C)(iii)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Export-Controlled Technology','CRY-01.2','Mechanisms exist to address the exporting of cryptographic technologies in compliance with relevant statutory and regulatory requirements.','','X','','X','','','','',5,'','','','','','','','','','','18.1.5','','','','','','SC-13','SC-13 ','','','','','','','','','','','','','','','','','','','','9-400','','','5.10.1.2
5.10.1.2.1
5.10.1.2.2
5.10.1.2.3
5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Cryptographic Module Authentication','CRY-02','Cryptographic mechanisms enable systems to authenticate to a cryptographic module.','- Yubikey HSM','X','','X','','','','',8,'','','','16.10
16.11','','','','','','','','','','','','','IA-7','IA-7','','','','','8.2.1','','','','','','','','','','','','','','','','','','5.10.1.2
5.10.1.2.1
5.10.1.2.2
5.10.1.2.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Transmission Confidentiality ','CRY-03','Cryptographic mechanisms are utilized to protect the confidentiality of data being transmitted. ','- SSL / TLS protocols
- IPSEC Tunnels
- Native MPLS encrypted tunnel configurations
- Custom encrypted payloads','X','X','X','X','','X','X',10,'','','','11.4
13.2
14.2','','','','','8.2.5','','13.2.3 ','','','','','','SC-8
SC-9','SC-8','','','PR.DS-2','','','','','','','','§ 11.10','','','D3.PC.Am.B.13
D3.PC.Am.E.5
D3.PC.Am.Int.7','','','','164.308(b)(1)
164.308(b)(2)
164.312(e)(1)
164.312(e)(2)(i)
164.312(e)(2)(ii)
164.314(b)(2)(i)','','8-605','','','5.10.1.2
5.10.1.2.1
5.10.1.5','','17.04(3) ',500.15,'622(2)(d)(C)(iii)','','','Art 5','Art 5','Art 20
Art 30','','','','','','','','KRY-02','','','','','','','','','','','','','','','','','','','','','0419
0462
0465
0467
0469
0482
0484
0485
0486
0487
0488
0489
0490
0494
0495
0496
0497
0498
0997
0998
0999
1000
1001
1139
1162
1233
1369
1370
1371
1372
1373
1374
1375
1447
1448
1449
1453','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Transmission Integrity ','CRY-04','Cryptographic mechanisms are utilized to protect the integrity of data being transmitted. ','','X','X','X','X','','X','X',10,'','','','14.2','','','','','','','14.1.3 ','','','','','','SC-8
SC-16(1)
SC-28(1)','SC-8
SC-16(1)
SC-28(1)','','3.8.6
3.13.8
3.13.16','PR.DS-8','','3.4
3.4.1
4.1
9.8.2','','','','','','§ 11.10','MP-5(4)
SC-8
SC-28(1) ','','','','','','164.312(e)(2)(i)
164.312(e)(1) 
164.312(e)(2)(i)','','8-605','','','5.10.1.2
5.10.1.2.1
5.10.1.2.2
5.10.1.5','','17.04(3)','','622(2)(d)(C)(iii)','','','','Art 5','Art 20
Art 30','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0419','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Encrypting Data At Rest ','CRY-05','Cryptographic mechanisms are utilized on systems to prevent unauthorized disclosure of information at rest. ','','X','X','X','X','','X','X',10,'','','14.5','13.2
13.10
14.5','','','','','','','10.1.1','','','','','','SC-13
SC-28(2)','SC-13
SC-28(2)','','','PR.DS-1','','3.4 
3.4.1 ','','','','','','§ 11.10','','','D1.G.IT.B.13
D3.PC.Am.B.14
D4.RM.Co.B.1
D3.PC.Am.A.1','','','','164.308(a)(1)(ii)(D)
164.308(b)(1)
164.310(d)
164.312(a)(1)
164.312(a)(2)(iii)
164.312(a)(2)(iv)
164.312(b)
164.312(c)
164.314(b)(2)(i)
164.312(d)','','9-400','','','5.10.1.2
5.10.1.2.1
5.10.1.2.2
5.10.1.2.3
5.10.1.5','','17.04(5)',500.15,'622(2)(d)(C)(iii)','','','','Art 5','','','','','','','','','KRY-03','','','','','','','','','','','','','','','','','','','','','0459
0461
1080
0455
0456
1464
1465
1466
0471
0994
0472
1475
0473
1476
1446
0474
0475
0476
1477
0477
1054
0479
0480
1468
1231
1232','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Storage Media','CRY-05.1','Cryptographic mechanisms are utilized on storage media to protect the confidentiality and integrity of the information being stored. ','- Native Storage Area Network (SAN) encryption functionality
- BitLocker and EFS','X','X','X','X','','X','X',8,'','','13.2','13.2
13.10
14.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','KRY-03','','','','','','','','','','','','','','','','','','','','','1464
1465
1466','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Non-Console Administrative Access','CRY-06','Cryptographic mechanisms exist to protect the confidentiality and integrity of non-console administrative access.','','X','X','X','X','','X','X',10,'','','','11.4','','','','','','','','','','','','','','','','','','','2.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Wireless Access Authentication & Encryption ','CRY-07','Mechanisms exist to protect wireless access via secure authentication and encryption.','','','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','AC-18
SC-40','AC-18
SC-40','','','','','4.1.1','','','','','','','','','','','','','','','8-311','','','5.13.1
5.13.1.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0536
0543
1013
1314
1315
1316
1317
1318
1319
1320
1321
1322
1323
1324
1325
1326
1327
1328
1329
1330
1331
1332
1333
1334
1335
1336
1337
1338
1443
1444
1445
1454','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Public Key Infrastructure (PKI) ','CRY-08','Mechanisms exist to implement an internal Public Key Infrastructure (PKI) infrastructure or obtain PKI services from a reputable PKI service provider. ','- Vault (Hashicorp)','','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','SC-12
SC-12(4)
SC-12(5)
SC-17','SC-12
SC-17 ','','3.13.10','','','','','','','','','§ 11.100','SC-12
SC-17 ','','','','','','','','8-303','','','5.10.1.2
5.10.1.2.1
5.10.1.2.2
5.10.1.2.3
5.10.1.5
5.10.1.2
5.10.1.2.3
5.10.1.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Cryptographic Key Management ','CRY-09','Mechanisms exist to facilitate cryptographic key management controls to protect the confidentiality, integrity and availability of keys.','','X','X','X','X','','X','X',10,'','','','','','','EKM-02','','','','10.1.2 ','','','','','','','','','','','','3.5-3.5.4 
3.6-3.6.8 ','','','','','','§ 11.50
§ 11.70
§ 11.100','','','','','','','','','','','','','','','','','','','','','','','','','','','','','KRY-04','','','','','','','','','','','','','','','','','','','','','1091
1393
0499
1002
0500
0501
0502
0503
0504
1003
1004
0505
0506
0507
0509
0510
0511
1005','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Symmetric Keys','CRY-09.1','Mechanisms exist to facilitate the production and management of symmetric cryptographic keys using Federal Information Processing Standards (FIPS)-compliant key management technology and processes. ','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','SC-12(2)','SC-12(2) ','','','','','','10.4','','','','','','SC-12(2) ','','','','','','','','','','','5.10.1.2
5.10.1.2.1
5.10.1.2.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Asymmetric Keys','CRY-09.2','Mechanisms exist to facilitate the production and management of asymmetric cryptographic keys using approved key management technology and processes that protect the user’s private key. ','','','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','SC-12(3)','SC-12(3) ','','','','','','','','','','','','SC-12(3) ','','','','','','','','','','','5.10.1.2
5.10.1.2.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Cryptographic Key Loss or Change','CRY-09.3','Mechanisms exist to ensure the availability of information in the event of the loss of cryptographic keys by individual users. ','- Escrowing of encryption keys is a common practice for ensuring availability in the event of loss of keys. ','','','X','X','','','X',8,'','','','','','','','','','','','','','','','','SC-12(1)','SC-12(1)','','','','','3.6.4 
3.6.5','','','','','','','','','','','','','','','','','','5.10.1.2
5.10.1.2.1
5.10.1.2.2
5.10.1.2.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Control & Distribution of Cryptographic Keys','CRY-09.4','Mechanisms exist to facilitate the secure distribution of symmetric and asymmetric cryptographic keys using industry recognized key management technology and processes. ','','','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','3.6.6-3.6.8','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Assigned Owners ','CRY-09.5','Mechanisms exist to ensure cryptographic keys are bound to individual identities. ','','X','X','X','X','','X','X',8,'','','','','','','EKM-01','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Cryptographic Protections ','Transmission of Security & Privacy Attributes ','CRY-10','Mechanisms exist to ensure systems associate security attributes with information exchanged between systems. ','- Integrity checking','','','X','','','X','',5,'','','','','','','','','','','','','','','','','SC-16
SC-16(1)','SC-16
SC-16(1)','','','','','','','','','','','','','','','','','','','','8-700','','','5.6.1.1
5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Data Protection ','DCH-01','Mechanisms exist to facilitate the implementation of data protection controls. ','','X','X','X','X','','X','X',10,'C1.1','C1.1','','13.1
14.2','DSS06.02','','','','','','8.2
8.3.3 ','','','','3.1','','MP-1','MP-1 ','3.3.6','NFO','','','9.7-9.7.1 ','10.1','','','','','§ 11.2
§ 11.10','MP-1 ','§ 1232g
§ 1232h','','','','','164.308(a)(4)(ii)(B)','CIP-002-5.1a 
R1 & R2','8-306
8-309','','','5.10.1.5','','17.03(2)(c) ','','','','Sec 13','','Art 5
Art 32','','Sec 14
Sec 15','Art 16','Art 13
Art 27','Art 27
Art 41 ','Sec 5
Sec 32
Sec 33
Sec 34
Sec 35','Art 34','Sec 4b
Sec 9
Sec 9a
Sec 16
Annex','AM-07
KOS-07','Art 9','Sec 7
Sec 8','Sec 2','Sec 16
Sec 17','Sec 31
Sec 33
Sec 34
Sec 35
Sec 42','Art 3
Art 4','Sec 12
Sec 13
Sec 14','Sec 13
Sec 14
Sec 29','Art 1
Art 36
Art 47','Art 14
Art 15
Art 16
Art 17
Art 18
Art 19','Art 7
Art 12
Art 19','','Sec 19
Sec 21','','Sec 31
Sec 33','Art 6
Art 7','Art 8
Art 12','Sec 11
Sec 15
Sec 16','','APP Part 8
APP Part 11','0293
0294
0296
0337
0338
0341
0342
0343
0344
0345
0346
0347
0657
0658
0661
0662
0663
0664
0665
0669
0678
0831
0832
0870
1059
1168
1169
1187','Sec 4','Principle 4
Sec 33','Sec 7
Sec 8','','Art 20
Art 23','Sec 9','Principle 4','12.3
13.2','Sec 25','Sec 24
Sec 26','9.1.3
9.1.4
9.1.5','','Art 21','Art 9
Art 12','','Principle 7','Art 7','','','','','Art 11
Art 12');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Data Stewardship ','DCH-01.1','Mechanisms exist to ensure data stewardship is assigned, documented and communicated. ','','X','','X','X','','X','X',10,'','','','13.1','','','DSI-01
DSI-06
DCS-01','','','','','','','','3.1','','','','','','','','','','','','','','','','','','','','','','CIP-011-2
R1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0338
0661
0870','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Data & Asset Classification ','DCH-02','Mechanisms exist to ensure data and assets are categorized in accordance with applicable statutory, regulatory and contractual requirements. ','','X','','X','X','','X','X',10,'','','13.1','13.1','BAI08.03 ','','DSI-01
DCS-01','','','','8.2.1 ','','','','3.1','','','','','','ID.AM-5','','9.6.1 ','10.2','','','','','','','','D1.G.IT.B.2','','','','164.308(a)(7)(ii)(E )','','','','','','','','','','','','','','','','','','','','','','AM-03
AM-05
SIM-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','12.3
13.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Media Access ','DCH-03','Mechanisms exist to control and restrict access to digital and non-digital media to authorized individuals. ','- Data Loss Prevention (DLP)','X','','X','X','','X','X',8,'C1.2','C1.2','','','','','','','','','','','','','','','MP-2','MP-2 ','','3.8.1
3.8.2
3.8.3','','','','','','','','','','MP-2 ','§ 1232h','','','','','164.308(a)(4)(ii)(C) ','','8-310','','','5.8.1
5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','13.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Disclosure of Information','DCH-03.1','Mechanisms exist to limit the disclosure of data to authorized parties. ','','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','','','','252.204-7000','','','','','§ 1232h','','','','','','','','','','','','','','','','','','','','','','','','','','','UP-03','','','','','','','','','','','','','','','','','','','','','0664','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Masking Displayed Data ','DCH-03.2','Mechanisms exist to apply data masking to sensitive information that is displayed or printed. ','','','','X','X','','X','X',7,'','','','','','','','','','','','','','','','','','','','','','','3.3','','','','','','','','','','','','','','','','','','','','','','','','Sec 4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Media Marking ','DCH-04','Mechanisms exist to mark media in accordance with data protection requirements so that personnel are alerted to distribution limitations, handling caveats and applicable security requirements. ','','X','','X','X','','X','X',7,'','','','13.1','','','DSI-04','','1.2.3','','8.2.2 ','','','','','','MP-3','MP-3 ','','3.8.4','','','','','','','','','','MP-3 ','','','','','','','','8-306
8-310','','','5.8.1','','','','','','','','','','','','','','','','','AM-06','','','','','','','','','','','','','','','','','','','','','0294
1168
0296
0323
0325
0330
0331
0332
0333
0334
0335','','','','','','','','12.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Automated Marking','DCH-04.1','Automated mechanisms exist to mark media and system output to indicate the distribution limitations, handling requirements and applicable security markings (if any) of the information to aide Data Loss Prevention (DLP) technologies. ','','','','X','','','','',2,'','','','','','','','','','','','','','','','','AC-15','MP-3 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Security & Privacy Attributes','DCH-05','Mechanisms exist to bind security attributes to information as it is stored, transmitted and processed.','','','','X','','','X','',2,'','','','','','','','','','','','','','','','','AC-16','AC-16 ','','','','','','','','','','','','','','','','','','','','8-306','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Dynamic Attribute Association','DCH-05.1','Mechanisms exist to dynamically associate security and privacy attributes with individuals and objects as information is created, combined, or transformed, in accordance with organization-defined cybersecurity and privacy policies.','','','','X','','','X','',2,'','','','','','','','','','','','','','','','','','AC-16(1) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Attribute Value Changes By Authorized Individuals','DCH-05.2','Mechanisms exist to provide authorized individuals (or processes acting on behalf of individuals) the capability to define or change the value of associated security and privacy attributes.','','','','X','','','X','',8,'','','','','','','','','','','','','','','','','','AC-16(2) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Maintenance of Attribute Associations By System','DCH-05.3','Mechanisms exist to maintain the association and integrity of security and privacy attributes to individuals and objects.','','','','X','','','X','',2,'','','','','','','','','','','','','','','','','','AC-16(3) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Association of Attributes By Authorized Individuals','DCH-05.4','Mechanisms exist to provide the capability to associate security and privacy attributes with individuals and objects by authorized individuals (or processes acting on behalf of individuals).','','','','X','','','X','',2,'','','','','','','','','','','','','','','','','','AC-16(4) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Attribute Displays for Output Devices','DCH-05.5','Mechanisms exist to display security and privacy attributes in human-readable form on each object that the system transmits to output devices to identify special dissemination, handling or distribution instructions using human-readable, standard naming conventions.','','','','X','','','X','',8,'','','','','','','','','','','','','','','','','','AC-16(5) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Maintenance of Attribute Association By Organization','DCH-05.6','Mechanisms exist to require personnel to associate and maintain the association of security and privacy attributes with individuals and objects in accordance with security and privacy policies.','','','','X','','','X','',2,'','','','','','','','','','','','','','','','','','AC-16(6) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Consistent Attribute Interpretation','DCH-05.7','Mechanisms exist to provide a consistent interpretation of security and privacy attributes transmitted between distributed system components.','','','','X','','','X','',2,'','','','','','','','','','','','','','','','','','AC-16(7) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Association Techniques & Technologies','DCH-05.8','Mechanisms exist to associate security and privacy attributes to information.','','','','X','','','X','',2,'','','','','','','','','','','','','','','','','','AC-16(8) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Attribute Reassignment','DCH-05.9','Mechanisms exist to re-classify data as required, due to changing business/technical requirements.','','','','X','','','X','',7,'','','','','','','','','','','','','','','','','','AC-16(9) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0330
0331','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Attribute Configuration By Authorized Individuals','DCH-05.10','Mechanisms exist to provide authorized individuals the capability to define or change the type and value of security and privacy attributes available for association with subjects and objects.','','','','X','','','X','',8,'','','','','','','','','','','','','','','','','','AC-16(10) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Audit Changes','DCH-05.11','Mechanisms exist to audit changes to security and privacy attributes and respond to them in a timely manner.','','','','X','','','X','',7,'','','','','','','','','','','','','','','','','','AC-16(11) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Media Storage','DCH-06','Mechanisms exist to: 
 ▪ Physically control and securely store digital and non-digital media within controlled areas using organization-defined security measures; and
 ▪ Protect system media until the media are destroyed or sanitized using approved equipment, techniques and procedures.','','','','X','X','','X','X',8,'','','','','','','','','','','','','','','','','MP-4','MP-4 ','','3.8.1
3.8.2
3.8.3','','','9.5
9.5.1
9.6-9.6.2
9.7 9.','','','','','','','MP-4 ','','','','','','164.310(d)(2)(iv)','','8-308','','','5.8.1
5.10.1.5','','17.03(2)(c) ','','622(2)(d)(C)(i) 
620','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0338','','','','','','','','13.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Physically Secure All Media','DCH-06.1','Mechanisms exist to physically secure all media that contains sensitive information.','- Lockbox','','','X','X','','X','X',9,'','','','','','','','','','','','','','','','','','','','','','','9.5 
9.5.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0338','','','','','','','','13.2
13.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Sensitive Data Inventories','DCH-06.2','Mechanisms exist to maintain inventory logs of all sensitive media and conduct sensitive media inventories at least annually. ','','X','','X','X','','X','X',9,'','','','13.1','','','','','','','','','','','','','','','','','','','9.7.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Periodic Scans for Sensitive Data','DCH-06.3','Mechanisms exist to periodically scan unstructured data sources for sensitive data or data requiring special protection measures by statutory, regulatory or contractual obligations. ','','X','','','','','','',7,'','','13.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Making Sensitive Data Unreadable In Storage','DCH-06.4','Mechanisms exist to ensure sensitive data is rendered human unreadable anywhere sensitive data is stored. ','','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','3.4-3.4.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Storing Authentication Data','DCH-06.5','Mechanisms exist to prohibit the storage of sensitive authentication data after authorization. ','','','','','','','','',5,'','','','','','','','','','','','','','','','','','','','','','','3.2-3.2.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Media Transportation ','DCH-07','Mechanisms exist to protect and control digital and non-digital media during transport outside of controlled areas using appropriate security measures.','- Assigned couriers','X','','X','X','','X','X',10,'CC5.7','CC5.7','','','','','','','8.2.6','','','','','','','','MP-5','MP-5 ','','3.8.5','','','9.6
9.6.2
9.6.3 
9.7 ','','','','','','','MP-5 ','','','','','','164.310(d)(1)','','8-605','','','5.8.2
5.8.2.1
5.8.2.2
5.10.1.5','','17.03(2)(c) ','','620','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','13.2
13.3','','','9.1.4
9.1.5
9.1.6','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Custodians','DCH-07.1','Mechanisms exist to identify custodians throughout the transport of system media. ','- Chain of custody','X','','X','X','','X','X',9,'','','','','','','','','','','8.2.3','','','','','','MP-5(3)','MP-5(3) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Physical Media Disposal','DCH-08','Mechanisms exist to securely dispose of media when it is no longer required, using formal procedures. ','- Shred-it
- IronMountain
- DoD-strength data erasers','X','','X','X','','X','X',10,'C1.8 ','C1.8 ','','','','','DSI-07 ','','','','8.3.2 ','','','','','','MP-6','MP-6','3.4.14','','PR.IP-6','','','','','','','','','','','D1.G.IT.B.19','','','','164.310(d)(2)(i)
164.310(d)(2)(ii)','','8-301
8-608','','','5.8.4
5.10.1.5','','','','','Sec. 521.052(b)','','','','Art 24','','','','','','','','PI-05','','','','','','','','','','','','','','','','','','','Chapter29-Schedule1-Part1-Principle 5','','0311
0312
0313
0315
0316
0317
0318
0319
0321
0322
0329
0350
0361
0362
0363
0364
0366
0368
0370
0371
0372
0373
0374
0375
0378
0838
0839
0840
1069
1076
1160
1217
1218
1219
1220
1221
1222
1223
1224
1225
1226
1347
1360
1361
1455','','','','','','','','12.6
13.5','','','9.1.7','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Digital Media Sanitization','DCH-09','Mechanisms exist to sanitize media, both digital and non-digital, with the strength and integrity commensurate with the classification or sensitivity of the information prior to disposal, release out of organizational control or release for reuse.','','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','MP-6
MP-6(3)','MP-6
MP-6(3)','','3.8.1
3.8.2
3.8.3','','','9.8-9.8.2 ','','','','',7,'','MP-6 ','','','','','','164.310(d)(2)(i) ','CIP-011-2
R2','8-301
8-608','','','5.8.3
5.8.4
5.10.1.5','','','','622(2)(d)(C)(i) 
622(2)(d)(C)(iv) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0311
0312
0313
0315
0316
0317
0318
0319
0321
0322
0335
0348
0351
0352
0353
0354
0356
0357
0358
0359
0360
0835
0836
0947
1065
1066
1067
1068
1076
1217
1218
1219
1220
1221
1222
1223
1224
1225
1226
1455','','','','','','','','12.6
13.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Media Sanitization Documentation','DCH-09.1','Mechanisms exist to track, document and verify media sanitization and disposal actions. ','- Certificate of destruction','','','X','X','','X','X',7,'','','','','','','','','','','','','','','','','MP-6(1)','MP-6(1) ','','','','','9.7.1','','','','','','','','','','','','','164.310(d)(2)(ii)','','','','','5.8.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0335','','','','','','','','12.6
13.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Equipment Testing','DCH-09.2','Mechanisms exist to test sanitization equipment and procedures to verify that the intended result is achieved. ','','','','X','','','','',5,'','','','','','','','','','','','','','','','','MP-6(2)','MP-6(2) ','','','','','','','','','','','','MP-6(2) ','','','','','','','','','','','5.8.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Destruction of Personally Identifiable Information (PII)','DCH-09.3','Mechanisms exist to facilitate the destruction of Personal Information (PI).','- De-identifying PII','X','','X','','','X','',10,'','','','','','','','','','','','','','','','','MP-6(9)','MP-6(9) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 5','Art 24','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','12.6
13.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Media Use','DCH-10','Mechanisms exist to restrict the use of types of digital media on systems or system components. ','','X','','X','','','','',10,'','','','','','','','','','','8.3.1 ','','','','','','MP-7
SC-8(2)','MP-7 
SC-8(2)','','3.8.7
3.8.8','','','','','','','','','','MP-7 
MP-7(1) ','','','','','','','','8-306
8-310','','','5.10.1.2
5.10.1.5
5.10.1.2.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0341
0342
0343
0344
0345','','','','','','','','13.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Limitations on Use ','DCH-10.1','Mechanisms exist to restrict the use and distribution of sensitive data. ','','','','','','','','',10,'','','','','','','','','','','','','','','','','','','','','','','','','','252.204-7009','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1169
0346','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Media Downgrading ','DCH-11','Mechanisms exist to downgrade system media commensurate with the security category and/or classification level of the information.','','','','X','','','','',8,'','','','','','','','','','','','','','','','','MP-8','MP-8','','','','','','','','','','','','','','','','','','','','8-310','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Removable Media Security','DCH-12','Mechanisms exist to restrict removable media in accordance with data handling and acceptable usage parameters.','','X','','','X','','X','',10,'','','','13.4','','','','','','','8.3.1','','','','','','','','','','PR.PT-2','','','','','','','','','','','D1.G.SP.B.4
D3.PC.De.B.1
D3.PC.Im.E.3','','','','164.308(a)(3)(i)
164.308(a)(3)(ii)(A)
164.310(d)(1)
164.310(d)(2)
164.312(a)(1)
164.312(a)(2)(iv)
164.312(b)','CIP-010-2
R4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1359','','','','','','','','13.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Use of External Information Systems ','DCH-13','Mechanisms exist to govern how external parties, systems and services are used to securely store, process and transmit data. ','','X','','X','','','','',10,'','','','','','','AIS-02','','','','','','','','','','AC-20','AC-20 ','','3.1.20','','','','','','','',3,'','AC-20 ','','','','','','','','8-700','','','5.10.1
5.10.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0071','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Limits of Authorized Use ','DCH-13.1','Mechanisms exist to prohibit external parties, systems and services from storing, processing and transmitting data unless authorized individuals first: 
 ▪ Verifying the implementation of required security controls; or
 ▪ Retaining a processing agreement with the entity hosting the external systems or service.','','','','X','','','','',8,'','','','','','','','','','','','','','','','','AC-20(1)','AC-20(1) ','','3.1.20','','','','','','','','','','AC-20(1) ','','','','','','','','','','','5.10.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Portable Storage Devices','DCH-13.2','Mechanisms exist to restrict or prohibit the use of portable storage devices by users on external systems. ','','','','X','','','X','',10,'','','','','','','','','','','','','','','','','AC-20(2)','AC-20(2) ','','3.1.21','','','','','','','','','','AC-20(2) ','','','','','','','','','','','5.8.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','13.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Protecting Sensitive Information on External Systems','DCH-13.3','Mechanisms exist to ensure that the requirements for the protection of sensitive information processed, stored or transmitted on external systems, are implemented in accordance with applicable statutory, regulatory and contractual obligations.','- NIST 800-171 Compliance Criteria (NCC) (ComplianceForge)','','','X','','','','',10,'','','','','','','','','','','','','','','','','','PM-17','','','','','','','','','',3,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Information Sharing ','DCH-14','Mechanisms exist to utilize a process to assist users in making information sharing decisions to ensure data is appropriately protected.','- ShareFile
- SmartVault','X','','X','','','X','X',10,'C1.3','C1.3','','','','','','','','','13.2-13.2.2 ','','','','','','AC-21','AC-21 ','','','','','','','','','','','','AC-21 ','','','','','','','','','','','5.1
5.1.1.1
5.1.1.2
5.1.1.3
5.1.1.4
5.1.1.5
5.1.1.6
5.1.1.8','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','13.2
20.2','','','','','','','','','','','','','','Art 23');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Publicly Accessible Content','DCH-15','Mechanisms exist to control publicly-accessible content.','- Designate individuals authorized to post information onto systems that are publicly accessible.
- Train authorized individuals to ensure that publicly accessible information does not contain nonpublic information.
- Review the proposed content of publicly accessible information for nonpublic information prior to posting.
- Remove nonpublic information from the publicly accessible system.','','','X','','X','','',10,'','','','','','','','','','','','','','','','','AC-22','AC-22 ','','3.1.22','','','','','','','','','','AC-22 ','','','','','','','','','','','5.5.4
5.5.6.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Data Mining Protection','DCH-16','Mechanisms exist to protect data storage objects against unauthorized data mining and data harvesting techniques. ','','','','X','','','','',7,'','','','','','','','','','','','','','','','','AC-23','AC-23','','','','','','','','','','','','','','','','','','','','','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Ad-Hoc Transfers ','DCH-17','Mechanisms exist to secure ad-hoc exchanges of large digital files with internal or external parties.','- ShareFile
- Box','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0831
0832
1059
0347','','','','','','','','20.1','','','','','','','','','','','','','','Art 23');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Media & Data Retention ','DCH-18','Mechanisms exist to retain media and data in accordance with applicable statutory, regulatory and contractual obligations. ','- Data Protection Impact Assessment (DPIA)','X','','X','X','','X','X',10,'PI1.4 
PI1.5
PI1.6','PI1.4 
PI1.5
PI1.6','','14.6','','','BCR-11 ','','','','8.3
18.1.3 ','','','','','','MP-7
SI-12','MP-7
SI-12 ','','','','','3.1
3.2-3.2.3 
10.7 ','','','','','','§ 11.10','SI-12 ','','','Securities Exchange Act of 1934 (17 CFR §240.17a-4(f))','','','','','8-306
8-310','','','5.10.4.5','','',500.12,'622(2)(C)(i) (iv) ','','','','Art 5','','','','','','','','','','','','','','','','','','','','','','','','','','','','Chapter29-Schedule1-Part1-Principle 3 & 5','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Limit Personally Identifiable Information (PII) Elements In Testing, Training & Research','DCH-18.1','Mechanisms exist to limit Personal Information (PI) being processed in the information lifecycle to elements identified in the Data Protection Impact Assessment (DPIA).','- Data Protection Impact Assessment (DPIA)','X','','X','','','X','',10,'','','','','','','','','','','','','','','','','','SI-12(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 35','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Minimize Personally Identifiable Information (PII)','DCH-18.2','Mechanisms exist to minimize the use of Personal Information (PI) for research, testing, or training, in accordance with the Data Protection Impact Assessment (DPIA).','- Data Protection Impact Assessment (DPIA)','X','','X','','','X','',8,'','','','','','','','','','','','','','','','','','SI-12(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 5
Art 35','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Geographic Location of Data','DCH-19','Mechanisms exist to inventory, document and maintain data flows for data that is resident (permanently or temporarily) within a service''s geographically distributed applications (physical and virtual), infrastructure, systems components and/or shared with other third-parties.','','X','','X','','','X','',10,'','','','','','','DSA-02','','','','','','','','','','SA-9(5)','SA-9(5) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 23');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Archived Data Sets ','DCH-20','Mechanisms exist to protect archived data in accordance with applicable statutory, regulatory and contractual obligations. ','','X','','','','','','',8,'','','14.7','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Information Disposal','DCH-21','Mechanisms exist to securely dispose of, destroy or erase information.','- Shred-it
- IronMountain','X','','X','','','X','',10,'','','14.4','','','','','','','','','','','','','','DM-2','SI-18','3.4.14','','','','','','','','','','','','','','','','','','','','','','','','','','','Sec. 521.052(b)','','','','Art 24','','','','','','','','PI-05','','','','','','','','','','','','','','','','','','','','','0311
0312
0313
0315
0316
0317
0318
0319
0321
0322
0329
0350
0361
0362
0363
0364
0366
0368
0370
0371
0372
0373
0374
0375
0378
0838
0839
0840
1069
1076
1160
1217
1218
1219
1220
1221
1222
1223
1224
1225
1226
1347
1360
1361
1455','','','','','','','','13.5','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Data Quality Operations','DCH-22','Mechanisms exist to check for the accuracy, relevance, timeliness, impact, completeness, and de-identification of information across the information lifecycle.','- Data Protection Impact Assessment (DPIA)','X','','X','','','X','',5,'','','','','','','','','9.2.1','','','A.6','','','','','DI-1','SI-19','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Chapter29-Schedule1-Part1-Principle 1','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Updating & Correcting Personally Identifiable Information (PII)','DCH-22.1','Mechanisms exist to utilize technical controls to correct Personal Information (PI) that is inaccurate or outdated, incorrectly determined regarding impact, or incorrectly de-identified.','- Data Protection Impact Assessment (DPIA)','','','X','','','X','',6,'','','','','','','','','','','','','','','','','','SI-19(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Data Tags','DCH-22.2','Mechanisms exist to utilize data tags to automate tracking of Personal Information (PI) across the information lifecycle.','- Data Protection Impact Assessment (DPIA)','','','X','','','X','',3,'','','','','','','','','','','','','','','','','','SI-19(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Personally Identifiable Information (PII) Collection','DCH-22.3','Mechanisms exist to collect Personal Information (PI) directly from the individual. ','- Data Protection Impact Assessment (DPIA)','','','X','','','X','',8,'','','','','','','','','','','','','','','','','','SI-19(3)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','De-Identification','DCH-23','Mechanisms exist to remove Personal Information (PI) from datasets.','- Data Protection Impact Assessment (DPIA)','','','X','','','X','',8,'','','','','','','','','','','','','','','','','','SI-20','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Collection','DCH-23.1','Mechanisms exist to de-identify the dataset upon collection by not collecting Personal Information (PI).','- Data Protection Impact Assessment (DPIA)','','','X','','','X','',8,'','','','','','','','','','','','','','','','','','SI-20(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Archiving','DCH-23.2','Mechanisms exist to refrain from archiving Personal Information (PI) elements if those elements in a dataset will not be needed after the dataset is archived.','- Data Protection Impact Assessment (DPIA)','','','X','','','X','',8,'','','','','','','','','','','','','','','','','','SI-20(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Release','DCH-23.3','Mechanisms exist to remove Personal Information (PI) elements from a dataset prior to its release if those elements in the dataset do not need to be part of the data release.','- Data Protection Impact Assessment (DPIA)','','','X','','','X','',8,'','','','','','','','','','','','','','','','','','SI-20(3)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Removal, Masking, Encryption, Hashing or Replacement of Direct Identifiers','DCH-23.4','Mechanisms exist to remove, mask, encrypt, hash or replace direct identifiers in a dataset.','- Data Protection Impact Assessment (DPIA)','','','X','','','X','',8,'','','','','','','','','','','','','','','','','','SI-20(4)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Statistical Disclosure Control','DCH-23.5','Mechanisms exist to manipulate numerical data, contingency tables and statistical findings so that no person or organization is identifiable in the results of the analysis.','','','','X','','','X','',1,'','','','','','','','','','','','','','','','','','SI-20(5)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Differential Privacy','DCH-23.6','Mechanisms exist to prevent disclosure of Personal Information (PI) by adding non-deterministic noise to the results of mathematical operations before the results are reported.','- Data Protection Impact Assessment (DPIA)','','','X','','','X','',1,'','','','','','','','','','','','','','','','','','SI-20(6)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Validated Software','DCH-23.7','Mechanisms exist to perform de-identification using validated algorithms and software to implement the algorithms.','- Data Protection Impact Assessment (DPIA)','','','X','','','X','',1,'','','','','','','','','','','','','','','','','','SI-20(7)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Motivated Intruder','DCH-23.8','Mechanisms exist to perform a motivated intruder test on the de-identified dataset to determine if the identified data remains or if the de-identified data can be re-identified.','','','','X','','','X','',3,'','','','','','','','','','','','','','','','','','SI-20(8)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Information Location','DCH-24','Mechanisms exist to identify and document the location of information and the specific system components on which the information resides.','- Data Flow Diagram (DFD)','X','','X','','','X','',10,'','','','','','','','','','','','','','','','','','CM-12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 6
Art 26
Art 27
Art 28
Art 29
Art 44
Art 45
Art 46
Art 47
Art 48
Art 49','','Sec 10','Chapter 4 - Art 16','Art 14
Art 16
Art 27','Art 41 ','','Art 34','','','','Sec 7','Sec 2','Sec 16
Sec 17','Sec 31','Art 3
Art 4','Sec 12
Sec 13
Sec 14','Sec 13
Sec 14','Art 1
Art 36','Art 14
Art 15','Art 7','','Sec 19
Sec 21','','Sec 31','','','','','','','','','','Art 1','Art 20','Sec 9','','2.2
2.3
4.4
20.1
20.2','Sec 25','Sec 24
Sec 26','','Art 17
Art 27','','Art 9
Art 26','','Sec 20','Art 7','Art 26','','','','Art 23');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Automated Tools to Support Information Location','DCH-24.1','Automated mechanisms exist to identify by data classification type to ensure adequate security and privacy controls are in place to protect organizational information and individual privacy.','','X','','X','','','X','',6,'','','','','','','','','','','','','','','','','','CM-12(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Sec 10','Chapter 4 - Art 16','Art 14
Art 16
Art 27','Art 41 ','','Art 34','','','','Sec 7','Sec 2','Sec 16
Sec 17','Sec 31','Art 3
Art 4','Sec 12
Sec 13
Sec 14','Sec 13
Sec 14','Art 1
Art 36','Art 14
Art 15','Art 7','','Sec 19
Sec 21','','Sec 31','','','','','','','','','','','Art 20','Sec 9','','2.2
2.3
4.4
20.1
20.2','Sec 25','Sec 24
Sec 26','','Art 17
Art 27','','Art 9
Art 26','','Sec 20','Art 7','Art 26','','','','');
INSERT INTO complianceforge_scf VALUES ('Data Classification & Handling ','Transfer of Personal Information','DCH-25','Mechanisms exist to restrict and govern the transfer of data to third-countries or international organizations.','- Model contracts
- Privacy Shield
- Binding Corporate Rules (BCR)','X','','','','','X','',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 44
Art 45
Art 46
Art 47
Art 48
Art 49','','Sec 10','','Art 14
Art 27','','','','','','','','','','','','','','','','','','','','','','','','','','0831
0832
1059
0347
0678','','','','','','Sec 9','','2.2
2.3
4.4
20.1
20.2','','Sec 24
Sec 26','','Art 17
Art 26
Art 27','','','','Sec 20','','Art 26','','','','');
INSERT INTO complianceforge_scf VALUES ('Embedded Technology ','Embedded Technology Security Program ','EMB-01','Mechanisms exist to facilitate the implementation of embedded technology controls. ','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','11.5
11.6','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Embedded Technology ','Internet of Things (IOT) ','EMB-02','Mechanisms exist to proactively manage the cybersecurity and privacy risks associated with Internet of Things (IoT).','','','','','','','','',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','11.5
11.6','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Embedded Technology ','Operational Technology (OT) ','EMB-03','Mechanisms exist to proactively manage the cybersecurity and privacy risks associated with Operational Technology (OT).','','','','','','','','',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Endpoint  Security ','END-01','Mechanisms exist to facilitate the implementation of endpoint security controls.','- Group Policy Objects (GPOs)
- Antimalware technologies
- Software firewalls
- Host-based IDS/IPS technologies
- NNT Change Tracker','X','X','X','X','','X','X',10,'','','','','DSS05.03','','HRS-11','','','','11.2.9 ','','','','','','MP-2','MP-2','','','','','','','','','','','','','','','','','','','','8-310','','','','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','','','','','','','','','','','','','','','','','','','','','','1418
0591
0593
1457
0594','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Endpoint Protection Measures ','END-02','Mechanisms exist to protect the confidentiality, integrity, availability and safety of endpoint devices.','- NNT Change Tracker','','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','SC-28','SC-28','','3.13.16','','','3.4-3.4.1 ','','','','','','','SC-28 ','','','','','','164.312(a)(b)(iv) ','','8-604','','','','','17.04(5) ','','622(2)(d)(C)(iii) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0221
0222
0223
0224
1058
1155
1166
1167
0830
0225
0829
0929
0591
0593
1457
0594','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Prohibit Installation Without Privileged Status ','END-03','Mechanisms exist to prohibit user installation of software without explicitly assigned privileged status. ','- Removal of local admin rights
- Privileged Account Management (PAM)
- NNT Change Tracker','X','X','X','X','','X','X',10,'','','','','','','','','','','12.6.2 ','','','','','','CM-11
CM-11(2)','CM-11
CM-11(2)','','','','','','','','','','','','','','','','','','','','','','','5.7.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Unauthorized Installation Alerts','END-03.1','Mechanisms exist to alert personnel when an unauthorized installation of software is detected. ','- NNT Change Tracker','X','','X','','','','',8,'','','8.3','','','','','','','','','','','','','','CM-11(1)','CM-8(3)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Access Restriction for Change','END-03.2','Mechanism exist to define, document, approve, and enforce access restrictions associated with changes to systems.','','X','','X','X','','X','X',8,'','','','','','','','','','','12.5.1 ','','','','','','CM-5','CM-5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Malicious Code Protection (Anti-Malware) ','END-04','Mechanisms exist to utilize antimalware technologies to detect and eradicate malicious code.','- Antimalware software
- NNT Change Tracker','X','','X','X','','X','X',10,'CC5.8 ','CC5.8 ','8.1','8.1
8.6
8.8','DSS05.01','','TVM-01 ','SO12','','','12.2.1 ','','','','','','SI-3','SI-3 ','','3.14.1
3.14.2
3.14.3
3.14.4
3.14.5','DE.CM-4','','5.1-5.1.2
5.2 
5.3','14.1
14.2','','','',13,'','SI-3 ','','D3.DC.Th.B.2','','','','164.308(a)(1)(ii)(D)
164.308(a)(5)(ii)(B)','CIP-007-6
R3','8-305','','','5.10.4.2
5.13.4.2','','17.04(7)','','','','','','','','','','','','','','','RB-05','','','','','','','','','','','','','','','','','','','','','1417
1033
1390','','','','','','','','','','','9.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Automatic Updates','END-04.1','Mechanisms exist to automatically update antimalware technologies, including signature definitions. ','- Antimalware software','X','','X','X','','X','X',10,'','','','8.2','','','','','','','','','','','','','SI-3(2)','SI-2
SI-3(2)','','','','','5.2','','','','',14,'','SI-3(2)','','','','','','','','','','','5.10.4.2
5.13.4.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Documented Protection Measures','END-04.2','Mechanisms exist to document antimalware technologies.','','','','','','','','',3,'','','','','','','','','','','','','','','','','','','','','','','5.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Centralized Management','END-04.3','Mechanisms exist to centrally-manage antimalware technologies.','- Antimalware software','X','','X','X','','X','X',8,'','','8.2','8.5','','','','','','','','','','','','','SI-3(1)','SI-3(1) ','','','','','','','','','','','','SI-3(1) ','','','','','','','','','','','5.10.4.2
5.13.4.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Heuristic / Nonsignature-Based Detection','END-04.4','Mechanisms exist to utilize heuristic / nonsignature-based antimalware detection capabilities.','- Antimalware software','','','X','X','','X','X',8,'','','','','','','','','','','','','','','','','SI-3(7)','SI-3(1) ','','','','','','','','','','','','SI-3(7) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Malware Protection Mechanism Testing','END-04.5','Mechanisms exist to test antimalware technologies by introducing a known benign, non-spreading test case into the system and subsequently verifying that both detection of the test case and associated incident reporting occurs. ','- EICAR test file','','','X','','','','',5,'','','','','','','','','','','','','','','','','SI-3(6)','SI-3(6)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Evolving Malware Threats','END-04.6','Mechanisms exist to perform periodic evaluations evolving malware threats to assess systems that are generally not considered to be commonly affected by malicious software. ','','','','','','','','',3,'','','','','','','','','','','','','','','','','','','','','','','5.1.2 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Always On Protection','END-04.7','Mechanisms exist to ensure that anti-malware technologies are continuously running and cannot be disabled or altered by non-privileged users, unless specifically authorized by management on a case-by-case basis for a limited time period. ','- Antimalware software','X','','X','X','','X','X',10,'','','','8.8','','','','','','','','','','','','','','','','','','','5.3','','','','',15,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Software Firewall ','END-05','Mechanisms exist to utilize a host-based firewall software on all laptop computers and other portable workstations capable of implementing a host-based firewall.','- NNT Change Tracker','X','','X','X','','X','X',10,'','','9.2','9.1
9.2
9.4','','','','','','','14.1.2 ','','','','','','','','','','','','1.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1416','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','File Integrity Monitoring (FIM) ','END-06','Mechanisms exist to utilize File Integrity Monitor (FIM) technology to detect and report unauthorized changes to system files and configurations.','- NNT Change Tracker
- File Integrity Monitor (FIM)','X','','X','X','X','','',8,'','','3.5','','','','','SO12','','','','','','','','','SI-7','SI-7 ','','','PR.DS-6','','11.5-11.5.1 ','','','','','','','SI-7 ','','D3.PC.Se.Int.3
D3.PC.De.Int.2','','','','164.308(a)(1)(ii)(D)
164.312(b)
164.312(c)(1)
164.312(c)(2)
164.312(e)(2)(i)','','8-302','','','5.10.1.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Integrity Checks ','END-06.1','Mechanisms exist to validate configurations through integrity checking of software and firmware.','- NNT Change Tracker
- File Integrity Monitor (FIM)','X','','X','','','','X',6,'PI1.1','PI1.1','','','','','','','','','','','','','','','SI-7(1)','SI-7(1) ','','','','','','','','','','','','SI-7(1) ','','','','','','','','','','','5.10.1.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Integration of Detection & Response ','END-06.2','Mechanisms exist to detect and respond to unauthorized configuration changes as cybersecurity incidents.','- NNT Change Tracker
- File Integrity Monitor (FIM)','','','X','','X','','',9,'','','','','','','','','','','','','','','','','SI-7(7)','SI-7(7) ','','','','','','','','','','','','SI-7(7) ','','','','','','','','','','','5.10.1.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Host Intrusion Detection and Prevention Systems (HIDS / HIPS) ','END-07','Mechanisms exist to utilize Host-based Intrusion Detection / Prevention Systems (HIDS / HIPS) on sensitive systems.','- NNT Change Tracker
- File Integrity Monitor (FIM)','X','','','','','','',9,'','','8.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1341
1034','','','','','','','','18.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Phishing & Spam Protection ','END-08','Mechanisms exist to utilize anti-phishing and spam protection technologies to detect and take action on unsolicited messages transported by electronic mail.','','X','','X','X','','X','X',10,'','','7.7
7.8','7.7
7.8
7.10','','','','','','','','','','','','','SI-8','SI-8 ','','','','','','','','','','','','SI-8 ','','','','','','','','8-302','','','5.10.4.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Central Management','END-08.1','Mechanisms exist to centrally-manage anti-phishing and spam protection technologies.','','','','X','X','','X','X',5,'','','','','','','','','','','','','','','','','SI-8(1)','SI-8(1)','','','','','','','','','','','','SI-8(1)','','','','','','','','','','','5.10.4.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Automatic Updates','END-08.2','Mechanisms exist to automatically update anti-phishing and spam protection technologies when new releases are available in accordance with configuration and change management practices.','','','','X','X','','X','X',8,'','','','','','','','','','','','','','','','','SI-8(2)','SI-8(2) ','','','','','','','','','','','','SI-8(2) ','','','','','','','','','','','5.10.4.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Trusted Path','END-09','Mechanisms exist to establish a trusted communications path between the user and the security functions of the operating system.','- Active Directory (AD) Ctrl+Alt+Del login process','','','X','','','','',9,'','','','','','','','','','','','','','','','','SC-11','SC-11','','','','','','','','','','','','','','','','','','','','','','','5.10.1.2
5.10.1.2.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Mobile Code','END-10','Mechanisms exist to address mobile code / operating system-independent applications. ','','X','','X','X','','','',4,'','','','','','','TVM-03','','','','','','','','','','SC-18
SC-18(1)
SC-18(2)
SC-18(3)
SC-18(4)
SC-27','SC-18
SC-18(1)
SC-18(2)
SC-18(3)
SC-18(4)
SC-27','','3.13.13','DE.CM-5','','','','','','','','','SC-18','','D3.PC.De.E.5','','','','164.308(a)(1)(ii)(D)
164.308(a)(5)(ii)(B)','','','','','5.13.4.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Thin Nodes','END-11','Mechanisms exist to configure thin nodes to have minimal functionality and information storage. ','','','','X','','','','',4,'','','','','','','','','','','','','','','','','SC-25','SC-25','','','','','','','','','','','','','','','','','','','','8-613','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Port & I / O Device Access ','END-12','Mechanisms exist to physically disable or remove unnecessary connection ports or input/output devices from sensitive systems.','','','X','X','','','','',6,'','','','','','','','','','','','','','','','','SC-41','SC-41','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0533
0534','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Sensor Capability','END-13','Mechanisms exist to configure embedded sensors on systems to: 
 ▪ Prohibit the remote activation of sensing capabilities; and
 ▪ Provide an explicit indication of sensor use to users.','','','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','SC-42','SC-42','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Authorized Use','END-13.1','Mechanisms exist to utilize organization-defined measures so that data or information collected by sensors is only used for authorized purposes.','','','X','X','','','X','',8,'','','','','','','','','','','','','','','','','SC-42(2)','SC-42(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Notice of Collection','END-13.2','Mechanisms exist to notify individuals that Personal Information (PI) is collected by sensors.','- Visible or auditory alert
- Data Protection Impact Assessment (DPIA)','','X','X','','','X','',10,'','','','','','','','','','','','','','','','','SC-42(4)','SC-42(4)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Collection Minimization','END-13.3','Mechanisms exist to utilize sensors that are configured to minimize the collection of information about individuals.','','','X','X','','','X','',8,'','','','','','','','','','','','','','','','','SC-42(5)','SC-42(5)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Collaborative Computing Devices ','END-14','Mechanisms exist to unplug or prohibit the remote activation of collaborative computing devices with the following exceptions: 
 ▪ Networked whiteboards; 
 ▪ Video teleconference cameras; and 
 ▪ Teleconference microphones. ','- Unplug devices when not needed','','X','X','X','','X','X',9,'','','','','','','','','','','','','','','','','SC-15
SC-15(1)','SC-15
SC-15(1)','','3.13.12','','','','','','','','','','SC-15 ','','','','','','','','','','','5.10.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0559
1450','','','','','','','','18.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Hypervisor Access ','END-15','Mechanisms exist to restrict access to hypervisor management functions or administrative consoles for systems hosting virtualized systems.','','X','','','','','','',9,'','','','','','','IVS-11','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','22.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Security Function Isolation ','END-16','Mechanisms exist to ensure system configurations isolate security functions from non-security functions. ','- Windows Defender Device Guard','X','','','','','','',7,'','','14.2','','','','IVS-06','','','','13.1.3 ','','','','','','','','','','','','1.2
1.3.1
2.2.1 
11.3.4 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Endpoint Security','Host-Based Security Function Isolation','END-16.1','Mechanisms exist to implement underlying software separation mechanisms to facilitate security function isolation. ','- Windows Defender Device Guard','','','X','','','','',7,'','','','','','','','','','','','','','','','','SC-7(12)','SC-7(12)','','','','','1.4','','','','','','','SC-7(12)','','','','','','','','','','','5.10.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Human Resources Security Management','HRS-01','Mechanisms exist to facilitate the implementation of personnel security controls.','','X','','X','X','','X','X',10,'','','','','APO04.01 ','','','SO7
SO8','','','','','','','','','PS-1','PS-1 ','3.2.4','NFO','PR.IP-11','','','','','','','','','PS-1 ','','D1.R.St.E.4','','','','164.308(a)(1)(ii)(C)
164.308(a)(3)','','8-307','','','5.1.1.7
5.10.1.5','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.2','','','3.3.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Position Categorization ','HRS-02','Mechanisms exist to manage personnel security risk by assigning a risk designation to all positions and establishing screening criteria for individuals filling those positions.','','','','X','X','X','X','X',8,'','','','','','','','','','','','','','','','','PS-2','PS-2 ','','','','','12.4 
12.4.1','','','','','','','PS-2 ','','','','','','164.308(a)(3)(i) (ii) (A)','','8-307','','','5.1.1.7
5.12.1.1
5.12.1.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Users With Elevated Privileges','HRS-02.1','Mechanisms exist to ensure that every user accessing a system that processes, stores, or transmits sensitive information is cleared and regularly trained to handle the information in question.','','','','X','X','','','X',10,'','','','','','','','SO3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Roles & Responsibilities ','HRS-03','Mechanisms exist to define cybersecurity responsibilities for all personnel. ','- NIST NICE framework
- RACI diagram','X','','X','X','','X','X',10,'CC1.2 
 CC2.3 ','CC1.2 
 CC2.3 ','','','DSS06.03','','HRS-04
HRS-07 ','SO3','','','6.1.1
7.2 ','','','','','','PM-13','PM-13','','','','','12.4 
12.4.1 ','','','','','','','','','','','','','','','8-103
8-307','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.2','','','3.3.2','','','','','','','','','','','Art 12');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','User Awareness ','HRS-03.1','Mechanisms exist to communicate with users about their roles and responsibilities to maintain a safe and secure working environment.','','X','','X','X','','X','X',9,'','','','','','','HRS-10
SEF-03','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','HR-03','','','','','','','','','','','','','','','','','','','','','0252','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Competency Requirements for Security-Related Positions','HRS-03.2','Mechanisms exist to ensure that all security-related positions are staffed by qualified individuals who have the necessary skill set. ','','X','','X','X','','X','X',9,'CC1.3 ','CC1.3 ','17.1','','','Principle 4','','SO6','','','','','','','','','PS-2','PS-2','','','','','','','','','','','','','','','','','','','','8-307','','','5.1.1.7
5.12.1.1
5.12.1.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Personnel Screening ','HRS-04','Mechanisms exist to manage personnel security risk by screening individuals prior to authorizing access.','- Criminal, education and employment background checks','X','','X','X','','X','X',10,'','','','','','','HRS-02 ','SO5','','','7.1.1 ','','','','','','PS-3','PS-3','','3.9.1
3.9.2','','','12.7','','','','','','','PS-3 ','','','','','','164.308(a)(3)(ii) (B)','','8-103
8-104
8-307','','','5.1.1.7
5.1.3
5.1.4
5.10.1.5
5.12.1.1
5.12.1.2','','','','','','','','','','','','','','','','','HR-01','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Roles With Special Protection Measures','HRS-04.1','Mechanisms exist to ensure that individuals accessing a system that stores, transmits or processes information requiring special protection satisfy organization-defined personnel screening criteria.','- Security clearances for classified information.','X','','X','X','','X','X',9,'','','','','APO07.03 ','','','SO5','','','','','','','','','PS-3(1)
PS-3(3)','PS-3(1)
PS-3(3)','','','','','','','','','','','','PS-3(3) ','','','','','','','CIP-004-6
R3','','','','5.12.1.1','','','','','','','','','','','','','','','','','HR-01','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Formal Indoctrination','HRS-04.2','Mechanisms exist to verify that individuals accessing a system processing, storing, or transmitting sensitive information are formally indoctrinated for all the relevant types of information to which they have access on the system.','','','','X','X','','X','X',7,'','','','','','','','','','','','','','','','','PS-3(2)','PS-3(2)','','','','','','','','','','','','','','','','','','','','','','','5.12.1.1','','','','','','','','','','','','','','','','','HR-03','','','','','','','','','','','','','','','','','','','','','0256','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Terms of Employment ','HRS-05','Mechanisms exist to require all employees and contractors to apply security and privacy principles in their daily work.','- Acceptable Use Policy (AUP)
- Rules of behavior','X','','X','X','','X','X',10,'','','','','APO07.06 ','','HRS-03','','','','7.1.2 
7.2.1 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','HR-02','','','','','','','','','','','','','','','','','','','','','0818','','','','','','','','9.2
9.3','','','3.3.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Rules of Behavior','HRS-05.1','Mechanisms exist to define acceptable and unacceptable rules of behavior for the use of technologies, including consequences for unacceptable behavior.','- Acceptable Use Policy (AUP)
- Rules of behavior','X','','X','X','','X','X',10,'CC1.4 ','CC1.4 ','','','','','HRS-08','','','','7.2.1
8.1.3 ','','','','','','PL-4','PL-4','','NFO','','','4.2
12.3-12.3.2
12.3.5-.6
12.3.10
12.4 ','','','','','','§ 11.10','PL-4
PL-4(1) ','','','','','','164.310(b)','','8-103','','','5.2.1
5.2.1.2
5.2.1.3
5.2.2
5.10.1.5','','17.03(2)(b)(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0817
0818
0819
0820
0871
1146
1147
0821
1148
0823
0824','','','','','','','','9.2
9.3
13.3
14.3','','','3.3.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Social Media & Social Networking Restrictions','HRS-05.2','Rules of behavior contain explicit restrictions on the use of social media and networking sites, posting information on commercial websites and sharing account information. ','- Acceptable Use Policy (AUP)
- Rules of behavior','','','X','X','','X','X',9,'','','','','','','','','','','','','','','','','PL-4(1)','PL-4(1) ','','NFO','','','','','','','',4,'','','','','','','','','','','','','5.2.1
5.2.1.2
5.2.1.3
5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0820
1146
1147
0821
1148','','','','','','','','9.2
9.3
13.3
14.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Use of Communications Technology','HRS-05.3','Mechanisms exist to establish usage restrictions and implementation guidance for communications technologies based on the potential to cause damage to systems, if used maliciously. ','- Acceptable Use Policy (AUP)
- Rules of behavior','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','SC-19','SC-19 ','','','','','','','','','','','','SC-19 ','','','','','','','','8-700','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0229
0230
0231
0232
0233
0234
0235
0236
0237
0238
0241
0242
0244
0245
0264
0266
0267
0269
0270
0271
0272
0273
0275
0278
0563
0564
0565
0588
0589
0590
0817
0822
0823
0824
0852
0871
0931
0967
0968
0969
1022
1023
1036
1075
1078
1089
1092
1340
1368','','','','','','','','9.2
9.3
13.3
14.3
15.1','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Use of Critical Technologies ','HRS-05.4','Mechanisms exist to govern usage policies for critical technologies. ','','','','','','','','',9,'','','','','','','','','','','','','','','','','','','','','','','12.3-12.3.10','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.2
9.3
13.3
14.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Use of Mobile Devices','HRS-05.5','Mechanisms exist to manage business risks associated with permitting mobile device access to organizational resources.','- Acceptable Use Policy (AUP)
- Rules of behavior
- BYOD policy','X','','','','','','',9,'','','13.5','','','','HRS-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.2
9.3
13.3
14.3
15.1','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Access Agreements ','HRS-06','Mechanisms exist to require internal and third-party users to sign appropriate access agreements prior to being granted access. ','','X','','X','X','','X','X',10,'','','','','','','','','','','13.2.2 ','','','','','','PS-6
PS-6(2)','PS-6
PS-6(2)','','NFO','','','','','','','','','','PS-6 ','','','','','','164.308(a)(4)(i)','','8-103
8-104
8-105','','','5.1.1.7
5.1.3
5.1.4
5.12.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Confidentiality Agreements','HRS-06.1','Mechanisms exist to require Non-Disclosure Agreements (NDAs) or similar confidentiality agreements that reflect the needs to protect data and operational details, or both employees and third-parties.','- Non-Disclosure Agreements (NDAs)','X','','X','X','','X','X',10,'','','','','','','HRS-06','','','','','','','','','','PS-6
PS-6(2)','PS-6
PS-6(2)','','','','','','','','','','','','','','','','','','','','8-103
8-104
8-105','','','5.1.1.7
5.1.3
5.1.4
5.12.1.1','','','','','','','','','','','','','','','','','IDM-07
KOS-08','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Personnel Sanctions','HRS-07','Mechanisms exist to sanction personnel failing to comply with established security policies, standards and procedures. ','','X','','X','X','','X','X',10,'','','','','APO07.04 ','','GRM-07 ','SO8','','','7.2.3 ','','','','','','PS-8','PS-8 ','','NFO','','','','','','','','','','PS-8 ','','','','','','164.308(a)(1)(ii)(C) ','','1-304','','','5.12.4','','17.03(2)(d)','','','','','','','','','','','','','','','HR-04','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Workplace Investigations','HRS-07.1','Mechanisms exist to conduct employee misconduct investigations when there is reasonable assurance that a policy has been violated. ','','','','X','X','','X','X',8,'','','','','','','','','','','','','','','','','','','','','','','','','','','FACTA','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Personnel Transfer','HRS-08','Mechanisms exist to adjust logical and physical access authorizations to systems and facilities upon personnel reassignment or transfer, in a timely manner.','','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','PS-5','PS-5 ','','3.9.1 
3.9.2','','','','','','','','','','PS-5 ','','','','','','','','8-303
5-309','','','5.12.3','','','','','','','','','','','','','','','','','HR-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Personnel Termination ','HRS-09','Mechanisms exist to govern the termination of individual employment.','','X','','X','X','','X','X',10,'','','','','','','','','','','7.3.1 ','','','','','','PS-4','PS-4 ','','3.9.1
3.9.2','','','9.3','','','','','','','PS-4 ','','','','','','164.308(a)(3)(ii) (C) ','','8-303
5-309','','','5.12.2','','17.03(2)(e)','','','','','','','','','','','','','','','HR-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Asset Collection','HRS-09.1','Mechanisms exist to retrieve organization-owned assets upon termination of an individual''s employment.','','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','High-Risk Terminations','HRS-09.2','Mechanisms exist to expedite the process of removing \"high risk\" individual’s access to systems and applications upon termination, as determined by management.','','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','AC-2(13)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Post-Employment Requirements ','HRS-09.3','Mechanisms exist to govern third-party personnel by notifying terminated individuals of applicable, legally binding post-employment requirements for the protection of organizational information.','- Non-Disclosure Agreements (NDAs)','','','X','X','','X','X',8,'','','','','','','','','','','','','','','','','','PS-4(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Third-Party Personnel Security','HRS-10','Mechanisms exist to govern third-party personnel by reviewing and monitoring third-party cybersecurity and privacy roles and responsibilities.','- Independent background check service','','','X','X','X','X','X',10,'','','','','','','','','','','','','','','','','PS-7','PS-7 ','','NFO','','','','','','','','','','PS-7 ','','','','','','','','8-304','','','5.1.1.7
5.1.3
5.1.4
5.10.1.5
5.12.1.1
5.12.1.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Separation of Duties','HRS-11','Mechanisms exist to implement and maintain Separation of Duties (SoD) to prevent potential malevolent activity without collusion.','','X','','X','X','','X','X',7,'','','','','APO07.02','','','','','','','','','','','','AC-5','AC-5','','3.1.4','','','6.4.2 ','','','','','','','AC-5 ','','','','','','','','8-611','','','5.5.1
5.5.2
5.5.2.1
5.5.2.2
5.5.2.3
5.5.2.4
5.13.6','','','','','','','','','','','','','','','','','OIS-04
BEI-12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Incompatible Roles ','HRS-12','Mechanisms exist to avoid incompatible development-specific roles through limiting and reviewing developer privileges to change hardware, software, and firmware components within a production/operational environment.','','X','','X','X','X','X','X',8,'','','','','','','','','','','6.1.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','BEI-12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Human Resources Security','Two-Person Rule','HRS-12.1','Mechanisms exist to enforce a two-person rule for implementing changes to sensitive systems.','','','','X','X','','X','X',7,'','','','','','','','','','','','','','','','','AC-3(2)','AC-3(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Identity & Access Management (IAM) ','IAC-01','Mechanisms exist to facilitate the implementation of identification and access management controls.','','X','X','X','X','','X','X',10,'CC5.1 ','CC5.1 ','','16.7
16.10
16.11
16.12','DSS05.04','','IAM-02
IAM-08
IAM-12 ','SO11','8.2.2','','9.1.1 ','','','','','','AC-1
IA-1
SI-9','AC-1
IA-1 ','','NFO','','A2
A5','8.1 8.4 ','8.1
8.6','','','','','§ 11.10','AC-1
IA-1 ','','','','','','','CIP-004-6
R4','8-101
8-606
8-607','','','5.6.1
5.6.2
5.10.1.5
5.13.7
5.13.7.1','','',500.07,'','','','','Art 32','Art 4','Sec 14
Sec 15','Art 16','','','','','','IDM-01','','','','','','','','','','','','','','','','','','','','','0432
0405
0407
0413
0434
0435
0440
0441
0442
0443
0078
0854
0409
0411
0816
0856','','','','','','','','16.1
20.4','','','9.0.2
11.1.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Identification & Authentication for Organizational Users ','IAC-02','Mechanisms exist to uniquely identify and authenticate organizational users and processes acting on behalf of organizational users. ','','X','','X','X','','X','X',10,'CC5.3','CC5.3','','16.7
16.10
16.11
16.13','','','','','','','','','','','','','IA-2','IA-2 ','','3.5.1
3.5.2','','','8.1.1 8.2 ','','','','',5,'§ 11.10','IA-2 ','','','','','','','','8-607','','','5.6.1
5.6.2
5.10.1.5
5.13.7
5.13.7.1','','','','','','','','','','','','','','','','','IDM-02','','','','','','','','','','','','','','','','','','','','','0414
0420
0975','','','','','','','','16.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Group Authentication ','IAC-02.1','Mechanisms exist to require individuals to be authenticated with an individual authenticator when a group authenticator is utilized. ','','X','X','X','X','','X','X',7,'','','','','','','','','','','','','','','','','IA-2(5)','IA-2(1)
IA-2(2)
IA-2(5)','','','','A2','','8.9','','','','','','IA-2(5)','','','','','','','','','','','5.6.1
5.13.7.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0973
0415
0416','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Network Access to Privileged Accounts - Replay Resistant','IAC-02.2','Replay-resistant authentication mechanisms exist to protect network access.','','X','X','X','','','','',9,'','','','','','','','','','','','','','','','','IA-2(8)
IA-2(9)','IA-2(8)
IA-2(9) ','','3.5.4','','A2','','','','','','','','IA-2(8) ','','','','','','','','','','','5.6.2
5.6.2.1.3
5.13.7','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Acceptance of PIV Credentials ','IAC-02.3','Mechanisms exist to accept and electronically verify organizational Personal Identity Verification (PIV) credentials. ','- Personal Identity Verification (PIV) credentials','X','X','X','','','','',2,'','','','','','','','','','','','','','','','','IA-2(12)
IA-8(5)','IA-2(12)
IA-8(5)','','','','A2','','','','','','','','IA-2(12) ','','','','','','','','','','','5.6.2.1.3
5.6.4
5.10.1.2.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Identification & Authentication for Non-Organizational Users ','IAC-03','Mechanisms exist to uniquely identify and authenticate third-party users and processes that provide services to the organization.','','X','X','X','X','','X','X',10,'CC5.3','CC5.3','','16.7
16.10
16.11
16.13','','','','','','','','','','','','','IA-8','IA-8 ','','','','A2','','','','','','','','IA-8 ','','','','','','','','8-607','','','5.6.3
5.6.3.1','','','','','','','','','Art 4','','','','','','','','IDM-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','11.1.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Acceptance of PIV Credentials from Other Organizations ','IAC-03.1','Mechanisms exist to accept and electronically verify Personal Identity Verification (PIV) credentials from third-parties.','','X','X','X','','','','',2,'','','','','','','','','','','','','','','','','IA-8(1)','IA-8(1) ','','','','A2','','','','','','','','IA-8(1) ','','','','','','','','','','','5.6.4
5.10.1.2.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Acceptance of Third-Party Credentials','IAC-03.2','Authentication mechanisms for accept Federal Identity, Credential and Access Management (FICAM)-approved third-party credentials. ','','X','X','X','','','','',2,'','','','','','','','','','','','','','','','','IA-8(2)','IA-8(2)','','','','A2','','','','','','','','IA-8(2) 
IA-8(3) ','','','','','','','','','','','5.6.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Use of FICAM-Issued Profiles','IAC-03.3','Mechanisms exist to conform systems to Federal Identity, Credential and Access Management (FICAM)-issued profiles. ','','','','X','','','','',2,'','','','','','','','','','','','','','','','','IA-8(4)','IA-8(4) ','','','','','','','','','','','','IA-8(4) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Disassociability','IAC-03.4','Mechanisms exist to disassociate user attributes or credential assertion relationships among individuals, credential service providers, and relying parties.','','','','X','','','X','',2,'','','','','','','','','','','','','','','','','','IA-8(6) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Identification & Authentication for Devices','IAC-04','Mechanisms exist to uniquely identify and authenticate devices before establishing a connection. ','- Active Directory (AD) Kerberos','X','','X','X','','X','X',9,'','','','16.7
16.10
16.11
16.13','','','','','','','','','','','','','IA-3
IA-3(1)
IA-3(4)','IA-3 ','','3.5.1','','','','8.9','','','','','§ 11.10','IA-3 ','','','','','','','','8-607','','','5.6.2
5.6.2.2
5.6.2.2.1
5.6.2.2.2
5.13.7
5.13.7.2
5.13.7.2.1
5.13.7.3','','','','','','','','','Art 25','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Identification & Authentication for Third Party Systems & Services','IAC-05','Mechanisms exist to identify and authenticate third-party systems and services.','','X','','X','X','','X','X',10,'','','','16.7
16.10
16.11
16.13','','','','','','','','','','','','','IA-9','IA-9','','','','','','','','','','','','','','','','','','','','8-607','','','','','','','','','','','','Art 4','','','','','','','','IDM-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','11.1.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Multi-Factor Authentication (MFA)','IAC-06','Mechanisms exist to require Multi-Factor Authentication (MFA) for remote network access. ','- Multi-Factor Authentication (MFA)','X','X','X','X','','X','X',9,'','','5.6
5.7
11.4
12.6
16.11
16.12','11.4
12.6
15.6
16.9','','','','','','','11.1.2','','','','','','IA-2(11)','IA-2(1)
IA-2(2) ','','','','A2','8.3-8.3.2 ','','','','','','','IA-2(11) ','','','','','','','','','','','5.6.2.1.3
5.6.2.2
5.6.2.2.1
5.6.2.2.2
5.13.7
5.13.7.2','','',500.12,'','','','','','Art 4','','','','','','','','IDM-08','','','','','','','','','','','','','','','','','','','','','1384','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Network Access to Privileged Accounts','IAC-06.1','Mechanisms exist to utilize Multi-Factor Authentication (MFA) to authenticate network access for privileged accounts. ','- Multi-Factor Authentication (MFA)','X','X','X','','','','',9,'','','','4.5','','','','','','','','','','','','','IA-2(1)
IA-2(4)
IA-2(13)','IA-2(1) ','','3.5.3','','A2','','','','','','','','IA-2(1) ','','','','','','','','','','','5.6.2.1.3
5.6.2.2
5.6.2.2.1
5.6.2.2.2
5.13.7
5.13.7.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1384','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Network Access to Non-Privileged Accounts ','IAC-06.2','Mechanisms exist to utilize Multi-Factor Authentication (MFA) to authenticate network access for non-privileged accounts. ','- Multi-Factor Authentication (MFA)','X','X','X','','','','',7,'','','','','','','','','','','','','','','','','IA-2(2)
IA-2(4)
IA-2(13)','IA-2(2) ','','3.5.3','','A2','','','','','','','','IA-2(2) ','','','','','','','','','','','5.6.2.1.3
5.6.2.2
5.6.2.2.1
5.6.2.2.2
5.13.7
5.13.7.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1384','','','','','','','','16.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Local Access to Privileged Accounts ','IAC-06.3','Mechanisms exist to utilize Multi-Factor Authentication (MFA) to authenticate local access for privileged accounts. ','- Multi-Factor Authentication (MFA)','X','X','X','','','','',5,'','','','','','','','','','','','','','','','','IA-2(3)','IA-2(1)
IA-2(2)
IA-2(3)','','3.5.3','','A2','','','','','','','','IA-2(3) ','','','','','','','','','','','5.6.2.1.3
5.6.2.2
5.6.2.2.1
5.6.2.2.2
5.13.7
5.13.7.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1384','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','User Provisioning & De-Provisioning ','IAC-07','Mechanisms exist to utilize a formal user registration and de-registration process that governs the assignment of access rights. ','','X','X','X','X','','X','X',10,'CC5.2 ','CC5.2 ','','16.3','','','IAC-09
IAC-11 ','SO7','','','9.2.1-9.2.2 ','','','','','','IA-5(3)','IA-12(4)','','','PR.AC-6','A5','','','','','','','','IA-5(3)','','','','','','','CIP-004-6
R5','','','','5.6.2.1.3
5.6.3.1','','','','','','','','','','','','','','','','','IDM-02
IDM-03
IDM-09','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Change of Roles & Duties','IAC-07.1','Mechanisms exist to revoke user access rights following changes in personnel roles and duties, if no longer necessary or permitted. ','','X','X','X','X','','X','X',10,'','','','','','','','SO7','','','9.2.5','','','','','','','','','','','A5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','IDM-04','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Termination of Employment','IAC-07.2','Mechanisms exist to revoke user access rights in a timely manner, upon termination of employment or contract.','','X','X','X','X','','X','X',10,'','','16.3','','','','','','','','9.2.5','','','','','','AC-2(10)','AC-2(10)','','','','A5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','IDM-04','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Role-Based Access Control (RBAC) ','IAC-08','Mechanisms exist to enforce a Role-Based Access Control (RBAC) policy over users and resources.','- Role-Based Access Control (RBAC)','X','X','X','X','','X','X',9,'','','14.4','14.4','','','IAC-04','','','','','','','','','','AC-2(7)','AC-2(7) ','','','','A5','7.1-7.1.4
7.2-7.2.3','8.4
8.6','','','','','§ 11.10','AC-2(7) ','','','','','','164.308(a)(4(ii)(A) (B) &(C) ','','','','','5.5.2
5.5.2.1
5.5.2.2
5.5.2.3
5.5.2.4','','','','','','','','','','','','','','','','','IDM-10','','','','','','','','','','','','','','','','','','','','','','','','','','','','','16.3
20.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Identifier Management (User Names)','IAC-09','Mechanisms exist to govern naming standards for usernames and systems.','','X','X','X','X','X','X','X',9,'','','','','','','','','','','','','','','','','IA-4','IA-4 ','','3.5.5
3.5.6','','A2
A5','','','','','','','','IA-4 ','','','','','','164.312(a)(2)(i)','','8-607','','','5.6.3
5.6.3.1','','17.04(1)(d)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','User Identity (ID) Management ','IAC-09.1','Mechanisms exist to ensure proper user identification management for non-consumer users and administrators. ','','X','X','X','X','X','X','X',9,'','','','','','','','','','','9.2.1 ','','','','','','IA-4(4)','IA-4(4)','','','','A5','8.1-8.1.8','','','','','','','IA-4(4)','','','','','','','','','','','5.6.3
5.6.3.1','','','','','','','','','','','','','','','','','IDM-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Identity User Status','IAC-09.2','Mechanisms exist to identify contractor and other third-party users through unique username characteristics. ','','X','X','X','X','X','X','X',7,'','','','','','','','','','','','','','','','','IA-4(4)','IA-4(4)','','','','A5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Dynamic Management','IAC-09.3','Mechanisms exist to dynamically manage usernames and system identifiers. ','- Microsoft Active Directory (AD)','X','X','X','','X','','',5,'','','','','','','','','','','','','','','','','IA-4(5)
IA-5(2)
IA-5(10)','IA-4(5)
IA-5(2)
IA-5(10)','','','','A5','','','','','','','','IA-5(2)','','','','','','','','','','','5.6.2.2
5.6.2.2.1
5.6.2.2.2
5.10.1.2.3
5.13.7
5.13.7.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Cross-Organization Management','IAC-09.4','Mechanisms exist to coordinate username identifiers with external organizations for cross-organization management of identifiers. ','','','X','X','','X','','',5,'','','','','','','','','','','','','','','','','IA-4(6)','IA-4(6) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','IDM-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Privileged Account Identifiers','IAC-09.5','Mechanisms exist to uniquely manage privileged accounts to identify the account as a privileged user or service.','','X','X','X','X','X','X','X',9,'','','','16.13','','','','','','','','','','','','','IA-5(8)','IA-5(8)','','','','A5','','','','','','','','','','','','','','','','','','','5.6.3
5.6.3.1
5.6.3.2','','','','','','','','','','','','','','','','','IDM-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','16.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Pairwise Pseudonymous Identifiers','IAC-09.6','Mechanisms exist to generate pairwise pseudonymous identifiers with no identifying information about a subscriber to discourage activity tracking and profiling of the subscriber.','','X','X','X','','','X','',1,'','','','','','','','','','','','','','','','','','IA-4(8)','','','','A5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Authenticator Management (Passwords)','IAC-10','Mechanisms exist to securely manage passwords for users and devices.','','X','X','X','X','','X','X',10,'','','','4.7','','','','','','','9.2.3
9.2.4
9.4.3','','','','','','IA-5
IA-5(4)','IA-5
IA-5(4)','','3.5.1
3.5.2','','','8.1.2
8.2-8.2.6','8.3','','','',6,'§ 11.300','IA-5
IA-5(4)','','','','','','164.308(a)(5)(ii)(D)','CIP-007-6
R5','8-607','','','5.6.2.1.1
5.6.2.1.2
5.6.2.1.3
5.6.3
5.6.3.2
5.13.1.4','','17.04(1)(b)-(e) 
17.04(2)(b)','','','','','','','Art 4','','','','','','','','IDM-11','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','11.1.5','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Password-Based Authentication ','IAC-10.1','For password-based authentication, mechanisms exist to enforce password complexity to ensure strong passwords.','','X','X','X','X','','X','X',10,'','','','4.7','','','','','','','','','','','','','IA-5(1)','IA-5(1) ','','3.5.7
3.5.8
3.5.9','','A2','','8.3','','','','','§ 11.300','IA-5(1) ','','','','','','','','','','','5.5.3
5.6.2.1.1
5.6.2.1.2
5.6.2.1.3
5.13.1.4','','','','','','','','','Art 4','','','','','','','','IDM-08
IDM-11','','','','','','','','','','','','','','','','','','','','','0417
0421
0422
1426
0974
1173
1401
1357
0423
1403
0976
1227
1055
0418
1402','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','PKI-Based Authentication','IAC-10.2','For PKI-based authentication, mechanisms exist to validate certificates by constructing and verifying a certification path to an accepted trust anchor including checking certificate status information.','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','IA-5(2)','IA-5(2) ','','','','A2','','','','','','','§ 11.200','','','','','','','','','','','','5.6.2.2
5.6.2.2.1
5.6.2.2.2
5.10.1.2.3
5.13.7
5.13.7.2','','','','','','','','','','','','','','','','','IDM-08','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','In-Person or Trusted Third-Party Registration','IAC-10.3','Mechanisms exist to conduct in-person or trusted third-party identify verification before user accounts for third-parties are created.','','','','X','','','','',9,'','','','','','','','','','','','','','','','','IA-5(3)','IA-5(3) ','','','','','','','','','','','','','','','','','','','','','','','5.6.2.1.3
5.6.3.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Automated Support For Password Strength','IAC-10.4','Automated mechanisms exist to determine if password authenticators are sufficiently strong enough to satisfy organization-defined password length and complexity requirements. ','','X','X','X','X','','','',5,'','','','','','','','','','','','','','','','','IA-5(4)','IA-5
IA-5(4)','','','','A2','','','','','','','§ 11.300','','','','','','','','','','','','5.6.2.1.1
5.6.2.1.2
5.6.2.1.3
5.13.1.4','','','','','','','','','Art 19','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Protection of Authenticators','IAC-10.5','Mechanisms exist to protect authenticators commensurate with the sensitivity of the information to which use of the authenticator permits access. ','','','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','IA-5(6)','IA-5(6) ','','','','','','','','','','','§ 11.300','','','','','','','','','','','','5.6.3.2','','','','','','','','','Art 19
Art 22','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','No Embedded Unencrypted Static Authenticators','IAC-10.6','Mechanisms exist to ensure that unencrypted, static authenticators are not embedded in applications, scripts or stored on function keys. ','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','IA-5(7)','IA-5(7)','','','','A2','','','','','','','','IA-5(7)','','','','','','','','','','','5.10.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Hardware Token-Based Authentication','IAC-10.7','For hardware token-based authentication, mechanisms exist to ensure organization-defined token quality requirements are satisfied. ','- Tokens are sufficiently encrypted or do not reveal credentials or passwords within the token.','','X','X','','','','',9,'','','','','','','','','','','','','','','','','IA-5(11)','IA-5(1)
IA-5(2)','','','','','','','','','','','','','','','','','','','','','','','5.6.2.1.3
5.6.2.2
5.6.2.2.1
5.6.2.2.2
5.10.1.2.3
5.13.7
5.13.7.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Vendor-Supplied Defaults','IAC-10.8','Mechanisms exist to ensure vendor-supplied defaults are changed as part of the installation process.','','X','X','X','X','','X','X',10,'','','5.3','4.2','','','','','','','','','','','','','IA-5
IA-5(5)','IA-5
IA-5(5)','','','','A2','2.1-2.1.1 
8.3 ','','','','','','','','','','','','','','','8-607','','','5.6.2.1.1
5.6.2.1.2
5.6.2.1.3
5.6.3
5.6.3.2
5.13.1.4','','','','','','','','','','','','','','','','','IDM-11','','','','','','','','','','','','','','','','','','','','','1304','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Authenticator Feedback','IAC-11','Mechanisms exist to obscure the feedback of authentication information during the authentication process to protect the information from possible exploitation/use by unauthorized individuals. ','','X','X','X','','','','',6,'','','','','','','','','','','','','','','','','IA-6','IA-6 ','','3.5.11','','','','8.3','','','','','','IA-6 ','','','','','','','','8-607','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Cryptographic Module Authentication ','IAC-12','Mechanisms exist to ensure cryptographic modules adhere to applicable statutory, regulatory and contractual requirements for security strength.','- FIPS 140-2','X','X','X','','','','',8,'','','16.13 
16.14','','','','','','','','','','','','','','IA-7','IA-7','','','','','8.2.1 ','8.3','','','','','','IA-7','','','','','','','','8-607','','','5.10.1.2
5.10.1.2.1
5.10.1.2.2
5.10.1.2.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Adaptive Identification & Authentication ','IAC-13','Under specific circumstances or situations, mechanisms exist to allow individuals to utilize alternative methods of authentication.','','X','X','X','','','','',5,'','','','','','','','','','','','','','','','','IA-10','IA-10','','','','','','8.9','','','','','','','','','','','','','','8-607','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Re-Authentication ','IAC-14','Mechanisms exist to force users and devices to re-authenticate according to organization-defined circumstances that necessitate re-authentication. ','','X','X','X','X','','X','X',8,'','','','','','','','','','','','','','','','','IA-11','IA-11','','','','A2','8.1.8','8.2
8.8','','','','','','','','','','','','','','8-607','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Account Management ','IAC-15','Mechanisms exist to proactively govern account management of individual, group, system, application, guest and temporary accounts.','- Service accounts prohibit interactive login - users cannot log into systems with those accounts.','X','X','X','X','X','X','X',10,'','','','16.1
16.4
16.13','','','IAC-10','','8.2.2','','','','','','','','AC-2','AC-2 ','','3.1.1
3.1.2','PR.AC-1','','8.1.3-8.1.5
8.2.2
8.5-8.5.1
8.6
8.7 ','8.3','','','','','','AC-2 ','','D3.PC.Im.B.7
D3.PC.Am.B.6','','','','164.308(a)(3)(ii)(B)
164.308(a)(3)(ii)(C)
164.308(a)(4)(i)
164.308(a)(4)(ii)(B)
164.308(a)(4)(ii)(C )
164.312(a)(2)(i)
164.312(a)(2)(ii)
164.312(a)(2)(iii)
164.312(d)','','8-606','','','5.5.1
5.5.2
5.5.2.1
5.5.2.2
5.5.2.3
5.5.2.4','','17.04(1)(a) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','16.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Automated System Account Management ','IAC-15.1','Automated mechanisms exist to support the management of system accounts. ','- Service accounts prohibit interactive login - users cannot log into systems with those accounts.','X','','X','','','','',5,'','','16.9','','','','','','','','','','','','','','AC-2(1)','AC-2(1) ','','','','','','','','','','','','AC-2(1) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Removal of Temporary / Emergency Accounts','IAC-15.2','Automated mechanisms exist to disable or remove temporary and emergency accounts after an organization-defined time period for each type of account. ','','X','X','X','X','X','X','X',10,'','','','16.2','','','','','','','','','','','','','AC-2(2)','AC-2(2) ','','','','','','','','','','','','AC-2(2) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','IDM-09','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Disable Inactive Accounts','IAC-15.3','Automated mechanisms exist to disable inactive accounts after an organization-defined time period. ','','X','X','X','X','X','X','X',10,'','','16.2
16.6','16.5','','','','','','','','','','','','','AC-2(3)','AC-2(3) ','','','','','','','','','','','','AC-2(3) ','','','','','','','','','','','5.6.3.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Automated Audit Actions','IAC-15.4','Automated mechanisms exist to audit account creation, modification, enabling, disabling and removal actions and notify organization-defined personnel or roles. ','','','','X','','X','','',5,'','','','','','','','','','','','','','','','','AC-2(4)','AC-2(4) ','','','','','','','','','','','','AC-2(4) ','','','','','','','','','','','5.5.2
5.5.2.1
5.5.2.2
5.5.2.3
5.5.2.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Restrictions on Shared Groups / Accounts','IAC-15.5','Mechanisms exist to authorize the use of shared/group accounts only under certain organization-defined conditions.','','','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','AC-2(9)','AC-2(9)','','','','','','','','','','','','AC-2(9)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Account Disabling for High Risk Individuals','IAC-15.6','Mechanisms exist to disable accounts immediately upon notification for users posing a significant risk to the organization.','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','AC-2(13)','AC-2(13) ','','','','A5','','','','','','','','AC-2(13) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','System Accounts','IAC-15.7','Mechanisms exist to review all system accounts and disable any account that cannot be associated with a business process and owner. ','','X','','X','X','X','X','X',10,'','','16.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Privileged Account Management (PAM) ','IAC-16','Mechanisms exist to restrict and control privileged access rights for users and services.','','X','','X','X','','X','X',10,'','','5.1','4.1
4.3','','','','','','','9.2.3 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','IDM-06','','','','','','','','','','','','','','','','','','','','','1175
0445
0446
0447
0448','','','','','','','','16.3','','','11.2.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Privileged Account Inventories ','IAC-16.1','Mechanisms exist to inventory all privileged accounts and validate that each person with elevated privileges is authorized by the appropriate level of organizational management. ','','X','X','X','X','','X','X',10,'','','5.2','4.1','','','','','','','','','','','','','','','','','','A5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','16.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Periodic Review ','IAC-17','Mechanisms exist to periodically review the privileges assigned to users to validate the need for such privileges; and reassign or remove privileges, if necessary, to correctly reflect organizational mission and business needs.','','X','X','X','X','X','X','X',10,'','','','4.3
16.4
16.13','','','','','','','','','','','','','AC-6(7)','AC-6(7)','','','','A5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','IDM-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','11.1.4
11.2.1
11.2.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','User Responsibilities for Account Management','IAC-18','Mechanisms exist to compel users to follow accepted practices in the use of authentication mechanisms (e.g., passwords, passphrases, physical or logical security tokens, smart cards, certificates, etc.). ','- Employment contract
- Rules of Behavior
- Formalized password policy','X','','X','X','','X','X',10,'','','','','','','','','','','9.3.1 ','','','','','','IA-5(6)','IA-5(6)','','','','','8.6','','','','','','','IA-5(6)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Credential Sharing ','IAC-19','Mechanisms exist to prevent the sharing of generic IDs, passwords or other generic authentication methods.','','','','','','','','',10,'','','','','','','','','','','','','','','','','','','','','','','8.5-8.5.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Access Enforcement ','IAC-20','Mechanisms exist to enforce logical access permissions through the principle of \"least privilege.\"','','X','X','X','X','','','',10,'','','','','','','','','','','9.2.6
9.4 ','','','','','','AC-3','AC-3 ','','3.1.1
3.1.2','','A5','7.1-7.1.4
7.2-7.2.1
7.2.3 ','','','','','','','AC-3 ','','','','','','164.308(a)(4(i) (ii)','','8-606','','','','','17.04(1)(b) 
17.04(2)(a)','','622(2)(d)(C)(iii)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Access To Sensitive Data','IAC-20.1','Mechanisms exist to limit access to sensitive data to only those individuals whose job requires such access. ','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','A5','7.1-7.1.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Database Access','IAC-20.2','Mechanisms exist to restrict access to database containing sensitive data to only necessary services or those individuals whose job requires such access. ','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','A5','8.7','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1252
1256
1425
0393
1255
1258
1260
1262
1261
1263
1264
1266
1268','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Use of Privileged Utility Programs','IAC-20.3','Mechanisms exist to restrict and tightly control utility programs that are capable of overriding system and application controls.','','X','X','X','X','','X','X',9,'','','','','','','IAC-01
IAC-13','','','','9.4.4 ','','','','','','','','','','','A5','7.1-7.1.4
7.2-7.2.1 
7.2.3 ','','','','','','','','','','','','','164.308(a)(4(i) (ii)','','','','','','','17.04(1)(b) 
17.04(2)(a)','','622(2)(d)(C)(iii)','','','','','','','','','','','','','IDM-12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Dedicated Administrative Machines','IAC-20.4','Mechanisms exist to restrict executing administrative tasks or tasks requiring elevated access to a dedicated machine.','- Jump hosts','X','','','','','','',8,'','','5.9
11.6','4.6
11.6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1380
1473
1381
1382
1383
1442
1387
1388','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Least Privilege ','IAC-21','Mechanisms exist to utilize the concept of least privilege, allowing only authorized access to processes necessary to accomplish assigned tasks in accordance with organizational business functions. ','','X','X','X','X','','X','X',10,'CC5.6 ','CC5.6 ','','14.4','','','','SO11','','','9.1.2 ','','','','','','AC-6','AC-6 ','','3.1.5','PR.AC-4','A5','','8.7','','1','','','§ 11.10','AC-6 ','','D3.PC.Am.B.1
D3.PC.Am.B.2
D3.PC.Am.B.5','','','','164.308(a)(3)
164.308(a)(4)
164.310(a)(2)(iii)
164.310(b)
164.312(a)(1)
164.312(a)(2)(i)
164.312(a)(2)(ii)','','8-303','','','5.5.2
5.5.2.1
5.5.2.2
5.5.2.3
5.5.2.4
5.13.6','','','','622(2)(d)(C)(iii)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','11.1.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Authorize Access to Security Functions ','IAC-21.1','Mechanisms exist to limit access to security functions to explicitly-authorized privileged users.','','X','X','X','','','','',9,'','','','','','','','','','','','','','','','','AC-6(1)','AC-6(1)','','3.1.5','','A5','','','','','','','','AC-6(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Non-Privileged Access for Non-Security Functions ','IAC-21.2','Mechanisms exist to prohibit privileged users from using privileged accounts, while performing non-security functions. ','','X','X','X','','','','',9,'','','5.8','4.8','','','','','','','','','','','','','AC-6(2)','AC-6(2)','','3.1.6','','A5','','','','','','','','AC-6(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Privileged Accounts ','IAC-21.3','Mechanisms exist to restrict the assignment of privileged accounts to organization-defined personnel or roles without management approval.','','X','X','X','','','','',10,'','','','4.8','','','','','','','','','','','','','AC-6(5)','AC-6(5)','','3.1.5','','A5','','','','','','','','AC-6(5)','','','','','','','','','','','5.5.2
5.5.2.1
5.5.2.2
5.5.2.3
5.5.2.4
5.13.6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Auditing Use of Privileged Functions ','IAC-21.4','Mechanisms exist to audit the execution of privileged functions. ','','X','X','X','','','','',9,'','','','4.3','','','','','','','','','','','','','AC-6(9)','AC-6(9)','','3.1.7','','A5','10.2-10.2.7','','','','','','','AC-6(9)','','','','','','','','','','','5.5.2
5.5.2.1
5.5.2.2
5.5.2.3
5.5.2.4
5.13.6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Prohibit Non-Privileged Users from Executing Privileged Functions ','IAC-21.5','Mechanisms exist to prevent non-privileged users from executing privileged functions to include disabling, circumventing or altering implemented security safeguards/countermeasures. ','','X','X','X','X','','X','X',9,'','','','4.8','','','','','','','','','','','','','AC-6(10)','AC-6(10) ','','3.1.7','','A5','','','','','','','','AC-6(10) ','','','','','','','','','','','5.5.2
5.5.2.1
5.5.2.2
5.5.2.3
5.5.2.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Account Lockout ','IAC-22','Mechanisms exist to enforce a limit for consecutive invalid login attempts by a user during an organization-defined time period and automatically locks the account when the maximum number of unsuccessful attempts is exceeded.','','X','','X','X','','X','X',10,'','','16.7','','','','','','','','6.2.1','','','','','','AC-7','AC-7 ','','3.1.8','','','8.1.6 
8.1.7 ','','','','','','','AC-7 ','','','','','','','','8-609','','','5.5.3','','17.04(1)(e) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0428
0430
1404','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Concurrent Session Control','IAC-23','Mechanisms exist to limit the number of concurrent sessions for each system account. ','','','','X','','','','',6,'','','','','','','','','','','','','','','','','AC-10','AC-10 ','','','','','','','','','','','','AC-10 ','','','','','','','','8-609','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Session Lock ','IAC-24','Mechanisms exist to initiate a session lock after an organization-defined time period of inactivity, or upon receiving a request from a user, and retain the session lock until the user reestablishes access using established identification and authentication methods.','','X','X','X','X','','X','X',10,'','','16.4
16.5','','','','','','','','','','','','','','AC-2(5)
AC-11','AC-2(5) 
AC-11 ','','3.1.10','','A5','','8.2','','','','','','AC-2(5) 
AC-11 ','','','','','','','','8-609','','','5.5.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0428
0430
1404','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Pattern-Hiding Displays ','IAC-24.1','Mechanisms exist to implement pattern-hiding displays to conceal information previously visible on the display during the session lock. ','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','AC-11(1)','AC-11(1)','','3.1.10','','A5','','8.2','','','','','','AC-11(1)','','','','','','','','','','','5.5.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Session Termination ','IAC-25','Automated mechanisms exist to log out users, both locally on the network and for remote sessions, at the end of the session or after an organization-defined period of inactivity. ','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','AC-12','AC-12 ','','3.1.11','','A5','8.1.8','8.2','','','','','','AC-12 ','','','','','','164.312(a)(2)(iii)','','8-311
8-609','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Permitted Actions Without Identification or Authorization','IAC-26','Mechanisms exist to identify and document the supporting rationale for specific user actions that can be performed on a system without identification or authentication.','','X','X','X','','','','',8,'','','','','','','','','','','','','','','','','AC-14','AC-14','','','','A5','','8.9','','','','','','AC-14','','','','','','','','8-501
8-504
8-505','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Reference Monitor','IAC-27','Mechanisms exist to implement a reference monitor that is tamperproof, always-invoked, small enough to be subject to analysis / testing and the completeness of which can be assured.','','','','X','','','','',1,'','','','','','','','','','','','','','','','','AC-25','AC-25','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Identity Proofing','IAC-28','Mechanisms exist to collect, validate and verify identity evidence of a user.','- Professional references
- Education / certification transcripts
- Driver''s license
- Passport','','','X','','','','',10,'','','','','','','','','','','','','','','','','','IA-12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Supervisor Authorization','IAC-28.1','Mechanisms exist to require the registration process to receive supervisor or sponsor authorization for new accounts.','','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','AC-24
IA-4(2)','IA-12(1)','','','','','','','','','','','','','','','','','','','','','','','5.6.3
5.6.3.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Identity Evidence','IAC-28.2','Mechanisms exist to require evidence of individual identification to be presented to the registration authority.','- Driver''s license
- Passport','','','X','','','','',5,'','','','','','','','','','','','','','','','','','IA-12(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Identity Evidence Validation & Verification','IAC-28.3','Mechanisms exist to require that the presented identity evidence be validated and verified through organizational-defined methods of validation and verification.','- Employment verification
- Credit check
- Criminal history check
- Education verification','','','X','','','','',5,'','','','','','','','','','','','','','','','','','IA-12(3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','In-Person Validation & Verification','IAC-28.4','Mechanisms exist to require that the validation and verification of identity evidence be conducted in person before a designated registration authority.','- In-person validation of government-issued photograph identification','','','X','','','','',5,'','','','','','','','','','','','','','','','','IA-5(3)','IA-12(4)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Identification & Authentication','Address Confirmation','IAC-28.5','Mechanisms exist to require that a notice of proofing be delivered through an out-of-band channel to verify the user''s address (physical or digital).','','','','X','','','','',1,'','','','','','','','','','','','','','','','','','IA-12(5)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Management of Security Incidents','IRO-01','Mechanisms exist to facilitate the implementation of incident response controls.','','X','X','X','X','','X','X',10,'','','','','','','','SO16
SO18','1.2.7','','16.1.1 ','','','','','','IR-1','IR-1','','NFO','PR.IP-9','','','','','','','','','IR-1 ','','D5.IR.Pl.B.1','','','','164.308(a)(6)
164.308(a)(6)(i)
164.308(a)(7)
164.310(a)(2)(i)
164.312(a)(2)(ii)','CIP-008-5
R1','8-101
8-103','','','5.3.2
5.3.2.1
5.3.2.2
5.10.1.5
5.13.5','','',500.16,'','','Sec 8','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','SIM-01','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.3','','','7.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Incident Handling ','IRO-02','Incident handling mechanisms exist to cover preparation, detection and analysis, containment, eradication and recovery.','- ITIL Infrastructure Library - Incident and problem management','X','','X','X','','X','X',10,'','','','','DSS02.03 DSS02.04 DSS02.05 DSS02.06. DSS03.01 DSS03.02 DSS03.04 DSS03.05','','','','1.2.7','','16.1.4 ','','','','','','IR-4','IR-4 ','','3.6.1
3.6.2','DE.AE-2
DE.AE-4
DE.AE-5
RS.AN-1
RS.AN-4
RS.MI-1
RS.MI-2
RS.RP-1','','12.5.3 
12.10 ','','','','','','','IR-4 ','','D5.IR.Pl.Int.4
D5.IR.Te.E.1
D5.ER.Es.E.1
D1.RM.RMP.A.4
D5.DR.De.B.1
D3.DC.An.E.4
D3.DC.An.Int.3
D5.IR.Pl.B.1
D5.DR.De.B.3
D5.DR.De.Int.3
D5.ER.Es.B.4
D5.DR.Re.E.1
D5.DR.Re.B.1
D5.DR.Re.E.4
D5.DR.Re.E.2
D5.DR.Re.E.3
D5.DR.De.B.1
D5.DR.Re.E.3
D3.PC.Im.E.4','','','','164.308(a)(1)(i)
164.308(a)(1)(ii)(D)
164.308(a)(5)(ii)(B)
164.308(a)(5)(ii)(C)
164.308(6)(i)
164.308(a)(6)(i)
164.308(a)(6)(ii)
164.308(a)(7)(i)
164.308(a)(7)(ii)(A)
164.308(a)(7)(ii)(B)
164.308(a)(7)(ii)(C)
164.310(a)(2)(i)
164.312(a)(2)(ii)
164.312(b)','','1-303
4-218','','','5.3.2.1
5.3.2.2
5.13.5','','','','','','Sec 8','','','','','','','','','','','SIM-03
SIM-05','','','','','','','','','','','','','','','','','','','','','0917
1212
0137','','','','','','','','','','','7.3.3
7.3.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Automated Incident Handling Processes','IRO-02.1','Automated mechanisms exist to support the incident handling process. ','','','','X','','X','','',1,'','','','','','','','','','','','','','','','','IR-4(1)
SI-4(7)','IR-4(1)
SI-4(7)','','','','','','','','','','','','IR-4(1) ','','','','','','','','','','','5.3.1
5.3.2.1
5.3.2.2
5.10.1.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Identity Theft Protection Program (ITPP)','IRO-02.2','Mechanisms exist to prevent identity theft from occurring. ','','','','X','X','','X','X',5,'','','','','','','','','','','','','','','','','','','','','','','','','','','Red Flags Rule ','','','','','','S-ID (17 CFR §248.201-202)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Indicators of Compromise (IOC)','IRO-03','Mechanisms exist to define specific Indicators of Compromise (IOC) that identify the potential impact of likely cybersecurity events.','- Indicators of Compromise (IoC)','X','','X','X','','X','X',8,'','','','','','','','','','','','','','','','','','','','','RS.AN-2','','','','','','','','','','','D1.RM.RMP.A.4
D5.IR.Te.E.1
D5.ER.Es.E.1','','','','164.308(a)(6)(ii)
164.308(a)(7)(ii)(B)
164.308(a)(7)(ii)(C)
164.308(a)(7)(ii)€','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Incident Response Plan (IRP) ','IRO-04','Mechanisms exist to maintain and make available a current and viable Incident Response Plan (IRP) to all stakeholders.','- Incident Response Plan (IRP)
- Hard copy of IRP','X','','X','X','X','X','X',10,'CC6.2 ','CC6.2 ','19.1
19.2
19.3 ','','DSS02.01 DSS02.02','','SEF-02 ','SO16','1.2.7','','16.1.5','','','','','','IR-8','IR-8 ','','NFO','','','12.8.3
12.10-12.10.6 ','','','','','','','IR-8 ','','','','','','164.308(a)(6)(ii)','','8-103
1-302','','','5.1.1.1
5.3.1
5.3.2
5.3.2.1
5.3.2.2
5.5.1
5.10.1.5
5.13.5','','',500.16,'622(2)(d)(B)(iii)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0058
0059
0122','','','','','','','','','','','7.3.4
7.3.5','Art 34','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Personally Identifiable Information (PII) Processes','IRO-04.1','Incident response mechanisms include processes involving Personal Information (PI).','','X','','X','X','','X','X',8,'','','','','','','','','1.2.7
7.2.4','','','A.9.1','','','','','SE-2','IR-8(1)','','','','','','','','252.204-7012','','','','','','','','','','','','','','','','','','','','Sec. 521.053','Sec 8','','Art 33','','','','Art 8
Art 17','','','','','SIM-02','','','','','','Art 3','','','','','','','Sec 22','','','Art 12','','','Chapter29-Schedule1-Part1-Principles 7','','','','','','','','','','5.6
5.7
7.3','Sec 38','','7.3.4
7.3.5
7.3.13
7.4.2
7.4.3','Art 34','Art 12','','','','','','','Art 20','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','IRP Update','IRO-04.2','Mechanisms exist to regularly update incident response strategies to keep current with business needs, technology changes and regulatory requirements. ','','X','','X','X','','X','X',8,'','','','','','','','','','','','','','','','','IR-1','IR-1','','NFO','RS.IM-2','','','','','','','','','','','D5.IR.Pl.Int.4
D5.IR.Te.Int.5','','','','164.308(a)(7)(ii)(D)
164.308(a)(8)','CIP-008-5
R3','8-101
8-103','','','5.3.2
5.3.2.1
5.3.2.2
5.10.1.5
5.13.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.4.4
7.5.1
7.5.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Incident Response Training ','IRO-05','Mechanisms exist to train personnel in their incident response roles and responsibilities.','- ITIL Infrastructure Library - Incident and problem management','X','','X','X','X','X','X',9,'','','19.7','','','','','','','','','','','','','','IR-2','IR-2 ','','3.6.1
3.6.2','','','12.10.4 ','','','','','','','IR-2 ','','','','','','','','8-103
8-104','','','5.3.3
5.13.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Incident Response Testing','IRO-06','Mechanisms exist to formally test incident response capabilities through realistic exercises to determine the operational effectiveness of those capabilities.','- EICAR test file
- \"red team vs blue team\" exercises','','','X','X','X','X','X',9,'','','','','','','','','','','','','','','','','IR-3
SI-4(9)','IR-3
SI-4(9)','','3.6.3','','','12.10.2','','','','','','','IR-3','','','','','','','CIP-008-5
R2','8-104','','','5.3.3
5.10.1.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Coordination with Related Plans ','IRO-06.1','Mechanisms exist to coordinate incident response testing with organizational elements responsible for related plans. ','','X','','X','X','X','X','',7,'','','','','','','','','1.2.7','','','','','','','','IR-3(2)','IR-3(2) ','','','PR.IP-10','','','','','','','','','IR-3(2) ','','D5.IR.Te.B.1
D5.IR.Te.B.3','','','','164.308(a)(7)(ii)(D)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Integrated Security Incident Response Team (ISIRT)','IRO-07','Mechanisms exist to establish an integrated team of  cybersecurity, IT and business function representatives that are capable of addressing cybersecurity and privacy incident response operations.','- Full-time employees only','X','','X','X','','X','X',10,'','','','','DSS02.05','','','SO16','','','16.1.4 ','','','','','','IR-10','IR-10','','','RC.CO-1
RC.CO-2
RC.CO-3
RS.CO-1
RS.CO-4','','12.10.3 ','','','','','','','IR-7(2)','','D5.ER.Es.Int.3
D5.IR.Pl.Int.1
D5.IR.Pl.B.3
D5.ER.Is.B.1
D5.IR.Pl.Int.1','','','','164.308(a)(2)
164.308(a)(6)
164.308(a)(6)(i)
164.308(a)(6)(ii)
164.308(a)(7)
164.308(a)(7)(ii)(A)
164.308(a)(7)(ii)(B)
164.308(a)(7)(ii)(C)
164.310(a)(2)(i)
164.312(a)(2)(ii)
164.314(a)(2)(i)©','','','','','','','','','','Sec. 521.053','Sec 8
Sec 9','','Art 34','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.3','','','7.3.5
7.3.6
7.3.13
7.4.2
7.4.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Chain of Custody & Forensics','IRO-08','Mechanisms exist to perform digital forensics and maintain the integrity of the chain of custody. ','- Chain of custody procedures
- Encase
- Forensic Tool Kit (FTK)','X','','X','X','','X','X',10,'','','','','','','','','','','16.1.7 ','','','','','','','','','','RS.AN-3','','','','','','','','','','','D3.CC.Re.Int.3
D3.CC.Re.Int.4','','','','164.308(a)(6)','','','','','','','','','','','','','','','','','','','','','','SIM-04','','','','','','','','','','','','','','','','','','','','','0138','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Incident Monitoring & Tracking','IRO-09','Mechanisms exist to document, monitor and report cybersecurity and privacy incidents. ','','X','','X','X','','X','',8,'','','','','','','SEF-05 ','SO17','1.2.7','','','','','','','','IR-5','IR-5 ','','3.6.1 
3.6.2','DE.AE-3','','12.5.2 
12.10.5 ','','','','','','','IR-5 ','','D3.DC.Ev.E.1','','','','164.308(a)(1)(ii)(D)
164.308(a)(5)(ii)(B)
164.308(a)(5)(ii)(C)
164.308(a)(6)(ii)
164.308(a)(8)
164.310(d)(2)(iii)
164.312(b)
164.314(a)(2)(i)(C)
164.314(a)(2)(iii)','','1-303
4-218','','','5.3.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0125
0126
0916','','','','','','','','7.1','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Automated Tracking, Data Collection & Analysis','IRO-09.1','Automated mechanisms exist to assist in the tracking, collection and analysis of information from actual and potential security and privacy incidents.','','','','X','','','','',1,'','','','','','','','','','','','','','','','','IR-5(1)','IR-5(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Incident Reporting ','IRO-10','Mechanisms exist to report incidents:
 ▪ Internally to organizational incident response personnel within organization-defined time-periods; and
 ▪ Externally to regulatory authorities and affected parties, as necessary.','','X','','X','X','X','X','X',9,'CC2.5 ','CC2.5 ','19.4
19.6 ','','DSS02.07 DSS03.03','','','SO18','1.2.7','','16.1.2
16.1.3 ','','','','','','IR-6','IR-6','','3.6.1
3.6.2','RS.CO-2
RS.CO-3
RS.CO-5','','12.5.2 
12.8.3 ','','','252.204-7012','',12,'','IR-6','','D5.IR.Pl.B.2
D5.DR.Re.B.4
D5.DR.Re.E.6
D5.ER.Es.B.4
D5.ER.Es.B.2
D2.IS.Is.B.3
D2.IS.Is.E.2','','','','164.308(a)(5)(ii)(B)
164.308(a)(5)(ii)(C)
164.308(a)(6)
164.308(a)(6)(ii)
164.314(a)(2)(i)(C)
164.314(a)(2)(iii)','','1-303
4-218','','','5.3.1
5.10.1.5','SEC2-Section 1798.29','17.03(2)(j)',500.17,'604(1)-(5)','Sec. 521.053','Sec 8','','Art 33
Art 34','','','','','','','','','SIM-04
SIM-06','','','','','','','','','','','','','','','','','','','','','0123
0124
0139
0140
0141
0142
0143','','','','','','','','7.2','','','7.3.7
7.3.9
7.3.11','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Automated Reporting','IRO-10.1','Automated mechanisms exist to assist in the reporting of security and privacy incidents.','','','','X','','X','','',9,'','','','','','','','','','','','','','','','','IR-6(1)','IR-6(1)','','','','','','','','','','','','IR-6(1)','','','','','','','','','','','5.3.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.3.9','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Cyber Incident Reporting for Sensitive Data','IRO-10.2','Mechanisms exist to report sensitive data incidents in a timely manner.','','','','','','','','',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Vulnerabilities Related To Incidents','IRO-10.3','Mechanisms exist to report system vulnerabilities associated with reported security and privacy incidents to organization-defined personnel or roles.','','','','X','','X','','',10,'','','','','','','','','','','','','','','','','IR-6(2)','IR-6(2)','','','','','','','','','','','','','','','','','','','','','','','5.3.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.3.9','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Supply Chain Coordination','IRO-10.4','Mechanisms exist to provide security and privacy incident information to the provider of the product or service and other organizations involved in the supply chain for systems or system components related to the incident.','','','','X','','X','','',7,'','','','','','','','','','','','','','','','','IR-6(3)','IR-6(3)','','','','','','','','252.204-7012','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.3.9','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Incident Reporting Assistance ','IRO-11','Mechanisms exist to provide incident response advice and assistance to users of systems for the handling and reporting of actual and potential security and privacy incidents. ','- ITIL Infrastructure Library - Incident and problem management','X','','X','','','X','',5,'','','19.5','','','','','','1.2.7','','','','','','','','IR-7','IR-7 ','','3.6.1 
3.6.2','','','','','','','','','','IR-7 ','','','','','','','','','','','5.3.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0915','','','','','','','','','','','7.3.8','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Automation Support of Availability of Information / Support ','IRO-11.1','Automated mechanisms exist to increase the availability of incident response-related information and support. ','','','','X','','','','',1,'','','','','','','','','','','','','','','','','IR-7(1)','IR-7(1)','','','','','','','','','','','','IR-7(1)','','','','','','','','','','','5.3.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Coordination With External Providers','IRO-11.2','Mechanisms exist to establish a direct, cooperative relationship between the organization''s incident response capability and external service providers.','','X','','X','X','','X','X',5,'','','','','','','','','','','','','','','','','IR-7(2)','IR-7(2)','','','','','','','','','','','','','','','','','','','','','','','5.3.1','','','','','Sec. 521.053','','','Art 34','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','7.3.8','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Information Spillage Response','IRO-12','Mechanisms exist to respond to sensitive information spills.','','','','X','','','','',8,'','','','','','','','','','','','','','','','','IR-9','IR-9 ','','','','','','','','252.204-7012','','','','IR-9 ','','','','','','','','8-103','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0129
0130
0131
0132
0133
0134
0135
0136','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Responsible Personnel','IRO-12.1','Mechanisms exist to formally assign personnel or roles with responsibility for responding to sensitive information spills. ','','X','','X','','','X','',8,'','','','','','','','','','','','','','','','','IR-9(1)','IR-9(1) ','','','','','','','','','','','','IR-9(1) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Training','IRO-12.2','Mechanisms exist to ensure incident response training material provides coverage for sensitive information spillage response.','','','','X','','','','',8,'','','','','','','','','','','','','','','','','IR-9(2)','IR-9(2) ','','','','','','','','','','','','IR-9(2) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Post-Spill Operations','IRO-12.3','Mechanisms exist to ensure that organizational personnel impacted by sensitive information spills can continue to carry out assigned tasks while contaminated systems are undergoing corrective actions. ','','','','X','','','','',8,'','','','','','','','','','','','','','','','','IR-9(3)','IR-9(3) ','','','','','','','','','','','','IR-9(3) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1213','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Exposure to Unauthorized Personnel','IRO-12.4','Mechanisms exist to address security safeguards for personnel exposed to sensitive information that is not within their assigned access authorizations. ','','','','X','','','','',8,'','','','','','','','','','','','','','','','','IR-9(4)','IR-9(4) ','','','','','','','','','','','','IR-9(4) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Root Cause Analysis (RCA) & Lessons Learned','IRO-13','Mechanisms exist to incorporate lessons learned from analyzing and resolving cybersecurity and privacy incidents to reduce the likelihood or impact of future incidents. ','','X','','X','X','','X','X',10,'','','','','DSS03.04','','','SO18','','','16.1.6 ','','','','','','IR-1','IR-1','','NFO','RS.IM-1','','12.10.6 ','','','','','','','','','D5.IR.Pl.Int.4','','','','164.308(a)(7)(ii)(D)
164.308(a)(8)
164.316(b)(2)(iii)','CIP-008-5
R3','8-101
8-103','','','5.3.2
5.3.2.1
5.3.2.2
5.10.1.5
5.13.5','','','','','','','','','','','','','','','','','SIM-07','','','','','','','','','','','','','','','','','','','','','1213','','','','','','','','','','','7.3.10
7.3.12
7.4.4
7.5.1
7.5.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Regulatory & Law Enforcement Contacts ','IRO-14','Mechanisms exist to maintain incident response contacts with applicable regulatory and law enforcement agencies. ','','X','','X','X','X','X','X',9,'','','','','','','SEF-01','','','','6.1.3','','','','','','IR-6','IR-6','','','','','','','','','','','','','','','','','','','','','','','5.3.1
5.10.1.5','','',500.17,'','','','','Art 31','','Sec 10','','Art 14
Art 27','','','','','OIS-05','','','','','','','','','','','','','','','','','','','','','0915','','','','','','Sec 9','','4.4','','Sec 11','','','','Art 26','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Incident Response','Detonation Chambers ','IRO-15','Mechanisms exist to utilize a detonation chamber capability for incident response operations.','- Separate network with \"sacrificial\" systems where potential malware can be evaluated without impacting the production network.','','','X','','','','',5,'','','','','','','','','','','','','','','','','SC-44','SC-44','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Information Assurance ','Information Assurance (IA) Operations','IAO-01','Mechanisms exist to facilitate the implementation of cybersecurity and privacy assessment and authorization controls. ','- Information Assurance (IA) program
- VisibleOps security management','X','X','X','X','','X','X',10,'','','','','MEA02.07 MEA02.08','Principle 16','','SO23
SO24','','','','','','','3.4','3.2','CA-1
PM-10','CA-1
PM-10','','NFO','','','','','','','','','§ 11.10','CA-1 ','','','','','','','','8-200
8-201
8-202
8-303
8-610','','','','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','','','','','','','','','','','','','','','','','','','','','','0791
0064
0076
0077
0793
0082
0069
0070
0795','','','','','','','','','','','6.0.1
6.0.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Information Assurance ','Assessments ','IAO-02','Mechanisms exist to formally assess the cybersecurity and privacy controls in systems, applications and services through  Control Validation Testing (CVT) activities to determine the extent to which the controls are implemented correctly, operating as intended and producing the desired outcome with respect to meeting expected requirements.','- Information Assurance (IA) program
- VisibleOps security management
- Control Validation Testing (CVT) ','X','','X','X','X','X','X',10,'','','','','MEA02.03 MEA02.06 MEA02.07','Principle 16','','SO23','','','14.2.8 ','','','5.3.2','3.4','3.2','CA-2','CA-2','','3.12.1
3.12.2
3.12.3
3.12.4','','','','','','','','','','CA-2 ','','','','','','','','8-610','','','5.11.1.1
5.11.1.2
5.11.2','','17.03(2)(h) ','','622(2)(B)(i)-(iv)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.0.1
6.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Information Assurance ','Assessor Independence','IAO-02.1','Mechanisms exist to ensure assessors or assessment teams have the appropriate independence to conduct cybersecurity and privacy control assessments. ','- Information Assurance (IA) program
- VisibleOps security management','X','','X','','X','','',9,'','','','','MEA02.05','Principle 16','','','','','','','','6.1
6.2
6.3
6.4
6.5','3.4','','CA-2(1)','CA-2(1) ','','NFO','','','','','','','','','','CA-2(1) ','','','','','','','','','','','5.11.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0902','','','','','','','','','','','6.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Information Assurance ','Specialized Assessments','IAO-02.2','Mechanisms exist to conduct specialized assessments for: 
 ▪ Statutory, regulatory and contractual compliance obligations;
 ▪ Monitoring capabilities; 
 ▪ Mobile devices;
 ▪ Databases;
 ▪ Application security;
 ▪ Embedded technologies (e.g., IoT, OT, etc.);
 ▪ Vulnerability management; 
 ▪ Malicious code; 
 ▪ Insider threats and
 ▪ Performance/load testing. ','- Information Assurance (IA) program
- VisibleOps security management','X','X','X','X','X','X','X',9,'','','18.7','','MEA02.06','Principle 16','MOS-07 ','','','','','','','5.3.2
6.1
6.2
6.3
6.4
6.5','3.4','3.2','CA-2(2)','CA-2(2) ','','','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','','','','','','','','CA-2(2) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0100
1459','','','','','','','','','','','6.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Information Assurance ','External Organizations','IAO-02.3','Mechanisms exist to accept and respond to the results of external assessments that are performed by impartial, external organizations. ','- Audit steering committee
- Information Assurance (IA) program
- VisibleOps security management','X','','X','','X','','',9,'','','','','','Principle 16','','','','','','','','','3.4','','CA-2(3)','CA-2(3) ','','','','','','','','','','','','CA-2(3) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Information Assurance ','System Security Plans (SSP)','IAO-03','System Security Plans (SSPs) or similar Mechanisms, are used to identify and maintain key architectural information on each critical system, application or service.','- Information Assurance (IA) program
- VisibleOps security management','X','','X','X','X','X','X',10,'CC2.4 ','CC2.4 ','','','','','BCR-04','','','','','','','5.5','3.3','','PL-2','PL-2 ','','3.12.1
3.12.2
3.12.3
3.12.4','','','','','','','','','','PL-2 ','','','','','','','CIP-003-6
R2','8-311
8-610','','','5.1.1.1
5.10.1.5','','','','','','','','','','','','','','','','','UP-01','','','','','','','','','','','','','','','','','','','','','0895
0067','','','','','','','','5.4','','','6.2.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Information Assurance ','Plan / Coordinate with Other Organizational Entities','IAO-03.1','Mechanisms exist to plan and coordinate Control Validation Testing (CVT) activities with affected stakeholders before conducting such activities in order to reduce the potential impact on operations. ','- Audit steering committee
- Information Assurance (IA) program
- VisibleOps security management
-  Control Validation Testing (CVT) ','','','X','','','','',5,'','','','','','','','','','','','','','','','','PL-2(3)','PL-2(3) ','','NFO','','','','','','','','','','PL-02(3) ','','','','','','','','','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Information Assurance ','Adequate Security for Sensitive Data In Support of Contracts','IAO-03.2','Mechanisms exist to protect sensitive data that is collected, developed, received, transmitted, used or stored in support of the performance of a contract. ','- Information Assurance (IA) program
- VisibleOps security management','','','','','','','',10,'','','','','','','','','','','','','','','3.2','','','','','','','','','','','252.204-7012','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','KOS-08','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Information Assurance ','Threat Analysis & Flaw Remediation During Development','IAO-04','Mechanisms exist to require system developers and integrators to create and execute a Security Test and Evaluation (ST&E) plan to identify and remediate flaws during development.','- Information Assurance (IA) program
- VisibleOps security management
- Security Test & Evaluation (ST&E)','X','X','','','','','',10,'','','','','','Principle 17','','','','10.1','','','','5.3.2
5.3.3
5.3.4
5.3.5
5.3.6','3.3','','','','','','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','6.6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.2.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Information Assurance ','Plan of Action & Milestones (POA&M)','IAO-05','A Plan of Action and Milestones (POA&M) or similar mechanism, exists to document planned remedial actions to correct weaknesses or deficiencies noted during the assessment of the security controls and to reduce or eliminate known vulnerabilities.','- Information Assurance (IA) program
- VisibleOps security management
- Plan of Action & Milestones (POA&M)','X','','X','X','X','X','X',10,'','','','','MEA02.04','Principle 17','','','','','','','5.6
5.7','5.5','3.3','3.2
3.3
3.4','CA-5
PM-4','CA-5
PM-4','','3.12.1
3.12.2
3.12.3
3.12.4','','','','','','','','','','CA-5 ','','','','','','','','8-311
8-610','','','5.11.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Information Assurance ','Technical Verification','IAO-06','Mechanisms exist to perform Control Validation Testing (CVT) activities to evaluate the design, implementation and effectiveness of technical security and privacy controls.','- Information Assurance (IA) program
- VisibleOps security management
-  Control Validation Testing (CVT) ','X','','X','X','X','X','X',10,'','','','','','Principle 16','','SO24','','','','','','5.3.2
5.3.3
5.3.4
5.3.5
5.3.6','3.4','','CA-2
CM-4(2)','CA-2','','','','','','','','','','','','','','','','','','','','8-610','','','5.7.1
5.11.1.1
5.11.1.2
5.11.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1141
1142
0807
0100
1459
0902
0797
0904
0798
0805
0806
1140','','','','','','','','','','','6.2.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Information Assurance ','Security Authorization ','IAO-07','Mechanisms exist to ensure systems, projects and services are officially authorized prior to \"go live\" in a production environment.','- Information Assurance (IA) program
- VisibleOps security management','X','','X','X','X','X','X',10,'','','','','','Principle 17','CCC-01','','','','14.2.9 ','','','5.5','3.5','','CA-6','CA-6 ','','','','','','','','','','','','CA-6 ','','','','','','','','8-202
8-610
8-614','','','5.11.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0808
1229
1230','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Maintenance Operations ','MNT-01','Mechanisms exist to develop, disseminate, review & update procedures to facilitate the implementation of maintenance controls across the enterprise.','','X','X','X','X','','X','X',10,'','','','','','','','','','','11.2.4 ','','','','','','MA-1','MA-1 ','3.4.13','NFO','','A9','','','','','','','§ 11.10','MA-1 ','','','','','','164.310(a)(2)(iv)','','8-304','','','5.10.1.5','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','PS-05','','','','','','','','','','','','','','','','','','','','','1079
0305
0307
0306
0308
0943
0310
0944','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Controlled Maintenance ','MNT-02','Mechanisms exist to conduct controlled maintenance activities throughout the lifecycle of the system, application or service.','- VisibleOps security management','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','MA-2','MA-2','3.4.13','3.7.1
3.7.2
3.7.3','PR.MA-1','A9','','','','','','','','MA-2','','D3.CC.Re.Int.5
D3.CC.Re.Int.6','','','','164.308(a)(3)(ii)(A)
164.310(a)(2)(iv)','','8-304','','','5.7.1
5.8.3','','','','','','','','','','','','','','','','','PS-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Timely Maintenance','MNT-03','Mechanisms exist to obtain maintenance support and/or spare parts for systems within a defined Recovery Time Objective (RTO).','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','MA-6','MA-6','3.4.13','','','A9','','','','','','','','','','','','','','','','8-304','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Maintenance Tools','MNT-04','Mechanisms exist to control and monitor the use of system maintenance tools. ','- VisibleOps security management','X','X','X','','','','',5,'','','','','','','','','','','','','','','','','MA-3','MA-3 ','3.4.13','3.7.1
3.7.2','','','','','','','','','','MA-3 ','','','','','','','','8-304','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Inspect Tools ','MNT-04.1','Mechanisms exist to inspect maintenance tools carried into a facility by maintenance personnel for improper or unauthorized modifications. ','','','','X','','','','',5,'','','','','','','','','','','','','','','','','MA-3(1)','MA-3(1) ','','3.7.1
3.7.2','','','','','','','','','','MA-3(1) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Inspect Media ','MNT-04.2','Mechanisms exist to check media containing diagnostic and test programs for malicious code before the media are used. ','','','','X','','','','',5,'','','','','','','','','','','','','','','','','MA-3(2)','MA-3(2) ','','3.7.1
3.7.2
3.7.4','','','','','','','','','','MA-3(2) ','','','','','','','','','','','5.10.4.2
5.13.4.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Prevent Unauthorized Removal ','MNT-04.3','Mechanisms exist to prevent or control the removal of equipment undergoing maintenance that containing organizational information.','','','','X','','','','',9,'','','','','','','','','','','','','','','','','MA-3(3)','MA-3(3) ','','','','','','','','','','','','MA-3(3) ','','','','','','','','','','','5.8.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0310
0944','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Non-Local Maintenance','MNT-05','Mechanisms exist to authorize, monitor and control non-local maintenance and diagnostic activities.','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','MA-4','MA-4','3.4.13','3.7.5','PR.MA-2','','','','','','','','','MA-4 ','','D3.PC.Im.B.7','','','','164.308(a)(3)(ii)(A)
164.310(d)(1)
164.310(d)(2)(ii)
164.310(d)(2)(iii)
164.312(a)
164.312(a)(2)(ii)
164.312(a)(2)(iv)
164.312(b)
164.312(d)
164.312(e)
164.308(a)(1)(ii)(D)','','','','','5.6.2.2
5.6.2.2.1
5.6.2.2.2
5.13.7
5.13.7.2','','','','','','','','','','','','','','','','','PS-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Auditing','MNT-05.1','Mechanisms exist to audit non-local maintenance and diagnostic sessions and review the maintenance records of the sessions. ','','','X','X','X','X','X','X',9,'','','','','','','','','','','','','','','','','MA-4(1)','MA-4(1)
MA-4(2) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Notification of Non-Local Maintenance','MNT-05.2','Mechanisms exist to require maintenance personnel to notify organization-defined personnel when non-local maintenance is planned (e.g., date/time).','','','','X','X','','','X',9,'','','','','','','','','','','','','','','','','MA-4(2)','MA-4(2) ','','NFO','','','','','','','','','','MA-4(2) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Cryptographic Protection','MNT-05.3','Cryptographic mechanisms exist to protect the integrity and confidentiality of non-local maintenance and diagnostic communications. ','','','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','MA-4(6)','MA-4(6) ','','','','','2.3','','','','','','','','','','','','','','','','','','5.10.1.2
5.10.1.2.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Remote Disconnect Verification','MNT-05.4','Mechanisms exist to provide remote disconnect verification to ensure non-local maintenance and diagnostic sessions are properly terminated.','','X','','X','','','','',9,'','','','12.7','','','','','','','','','','','','','MA-4(7)','MA-4(7) ','','','','','','','','','','','','','','','','','','','','','','','5.9.1.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','16.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Pre-Approval of Non-Local Maintenance','MNT-05.5','Mechanisms exist to require maintenance personnel to obtain pre-approval and scheduling for non-local maintenance sessions.','- VisibleOps security management','','','X','X','','','X',7,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Authorized Maintenance Personnel','MNT-06','Mechanisms exist to maintain a current list of authorized maintenance organizations or personnel.','- VisibleOps security management','X','','X','X','','','X',9,'','','','','','','','','','','','','','','','','MA-5','MA-5 ','3.4.13','3.7.6','','','','','','','','','','MA-5 ','','','','','','','','8-304','','','5.7.1
5.9.1.2
5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0307
0306
0308
0943','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Maintenance','Maintenance Personnel Without Appropriate Access ','MNT-06.1','Mechanisms exist to ensure the risks associated with maintenance personnel who do not have appropriate access authorizations, clearances or formal access approvals are appropriately mitigated.','- VisibleOps security management','','','X','X','','','X',7,'','','','','','','','','','','','','','','','','MA-5(1)
MA-5(2)
MA-5(3)
MA-5(4)','MA-5(1)
MA-5(2)
MA-5(3)
MA-5(4)','','','','','','','','','','','','MA-5(1) ','','','','','','','','','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0307
0306
0308
0943','','','','','','','','9.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Mobile Device Management','Centralized Management Of Mobile Devices ','MDM-01','Mechanisms exist to develop, govern & update procedures to facilitate the implementation of mobile device management controls.','','X','X','X','X','','X','X',10,'','','','','','','MOS-02
MOS-05
MOS-09
MOS-10','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Sec 14
Sec 15','Art 16','','','','','','MDM-01','','','','','','','','','','','','','','','','','','','','','1082
1398
1195
0687
1083
1145
0682
1196
1198
1199
1197
1202
1200
1201
0862
0863
0864
1365
1366
1367
0874
0705
0240
1356','','','','','','','','11.4
21.1','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Mobile Device Management','Access Control For Mobile Devices','MDM-02','Access control mechanisms for mobile devices exist to enforce requirements for the connection of mobile devices to organizational systems.','','X','','X','X','','X','X',9,'','','','','','','','','','','6.2.1 ','','','','','','AC-19','AC-19 ','','3.1.18','','','','','','','','','','AC-19 ','','','','','','','','8-610','','','5.13.1.2
5.13.1.2.1
5.13.1.2.2
5.13.1.4
5.13.2
5.13.3
5.13.6
5.13.7.2.1
5.13.7.3','','','','','','','','','','','','','','','','','MDM-01','','','','','','','','','','','','','','','','','','','','','1365
1366
1367','','','','','','','','21.1','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Mobile Device Management','Full Device & Container-Based Encryption ','MDM-03','Cryptographic mechanisms are utilized to protect the confidentiality and integrity of information on mobile devices through full-device or container encryption.','','','','X','X','','X','X',9,'','','','','','','','','','','','','','','','','AC-19(5)','AC-19(5) ','','3.1.19','','','','','','','','','','AC-19(5) ','','','','','','','','','','','5.13.1.2
5.13.1.2.1
5.13.1.2.2
5.13.2
5.13.3
5.13.6','','','','','','','','','','','','','','','','','MDM-01','','','','','','','','','','','','','','','','','','','','','0869
1084
1085','','','','','','','','21.1','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Mobile Device Management','Mobile Device Tampering','MDM-04','Mechanisms exist to protect mobile devices from tampering through inspecting devices returning from locations that the organization deems to be of significant risk, prior to the device being connected to the organization’s network.','','X','','X','X','','X','X',9,'','','','','','','','','','','11.1.6','','','','','','PE-3(5)','PE-3(5)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','MDM-01','','','','','','','','','','','','','','','','','','','','','1365
1366
1367','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Mobile Device Management','Remote Purging','MDM-05','Mechanisms exist to remotely purge selected information from mobile devices. ','','X','','X','X','','X','X',9,'','','','','','','','','','','6.1.2
6.2.1','','','','','','AC-7(2)
MP-6(8)','AC-7 (2)
MP-6(8)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0700
0701
0702','','','','','','','','21.1','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Mobile Device Management','Personally-Owned Mobile Devices ','MDM-06','Mechanisms exist to restrict the connection of personally-owned, mobile devices to organizational systems and networks. ','','X','','X','X','','X','X',10,'','','','','','','MOS-04
MOS-12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','MDM-01','','','','','','','','','','','','','','','','','','','','','1399
1400
1047
0693
0694
0172
1297','','','','','','','','21.1','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Mobile Device Management','Organization-Owned Mobile Devices ','MDM-07','Mechanisms exist to prohibit the installation of non-approved applications or approved applications not obtained through the organization-approved application store.','','X','','X','X','','X','X',10,'','','','','','','MOS-03
MOS-12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','MDM-01','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Mobile Device Management','Mobile Device Data Retention Limitations','MDM-08','Mechanisms exist to limit data retention on mobile devices to the smallest usable dataset and timeframe.','','','','','','','','',7,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','11.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Network Security Management ','NET-01','Mechanisms exist to develop, govern & update procedures to facilitate the implementation of network security controls.','','X','X','X','X','','X','X',10,'','','','11.1
11.2','DSS05.02','','','','','','13.1.1
13.1.2 ','','','','','','SC-1','SC-1 ','','NFO','PR.PT-4','','','','','','','','','SC-1 ','','D3.PC.Im.B.1
D3.PC.Am.B.11
D3.PC.Im.Int.1','','','','164.308(a)(1)(ii)(D)
164.312(a)(1)
164.312(b)
164.312€','CIP-005-5
R1','8-101
8-605','','','','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','','','','','','','','','','','','','','','','','','','','','','0513
0514
0515
0516
0518
1177
1178
1180
1269
1270
1271
1272
1301
1303
0546
0547
0548
0554
0553
0555
0551
0552
1014
0549
0550
0556
0557','','','','','','','','11.1
15.2
18.1
18.5-6','','','9.3.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Layered Network Defenses ','NET-02','Mechanisms exist to implement security functions as a layered structure that minimizes interactions between layers of the design and avoiding any dependence by lower layers on the functionality or correctness of higher layers. ','- NNT Change Tracker','X','','X','X','','','X',9,'','','9.5','','','','','','','','','','','','','','','','','','PR.AC-5','','1.3.7','','','','',11,'','','','D3.DC.Im.B.1
D3.DC.Im.Int.1','','','','164.308(a)(4)(ii)(B)
164.310(a)(1)
164.310(b)
164.312(a)(1)
164.312(b)
164.312(c)
164.312€','CIP-005-5
R1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1275
1276
1277
1278
1234
0561
1057
0574
1183
1151
1152
0861
1025
1026
1027
1181
0385
1460
1461
1462
1463
1006
0520
1182
1427','','','','','','','','','','','9.0.1
9.3.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Denial of Service (DoS) Protection','NET-02.1','Mechanism exist to protect against or limit the effects of denial of service attacks. ','','','','X','','','','',9,'','','','','','','','','','','','','','','','','SC-5','SC-5','','','','','','','','','','','','SC-5 ','','','','','','','','','','','5.10.1.1
5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1431
1441
1019','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Guest Networks','NET-02.2','Mechanisms exist to implement and manage a secure guest network. ','','X','','X','X','','X','X',10,'','','15.9','15.9
15.10','','','','','','','','','','','','','','','','','','','1.2.3 ','','','','',11,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Boundary Protection ','NET-03','Boundary protection mechanisms are utilized to monitor and control communications at the external network boundary and at key internal boundaries within the network.','','X','','X','X','X','','X',10,'','','9.6','12.8
12.9
12.12','','','','','','','','','','','','','SC-7
SC-7(9)
SC-7(11)','SC-7
SC-7(9)
SC-7(11)','','3.13.1
3.13.2
3.13.5','','','1.1.3
1.1.4
1.2.1
1.2.3
1.3 ','','','','','','','SC-7 ','','','','','','','','8-701','','','5.10.1
5.10.1.3
5.10.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0566
0567
0568
0569
0570
0571
0572
0598
0605
0607
0608
0609
0610
0611
0612
0613
0616
0617
0619
0620
0622
0624
0625
0628
0629
0631
0634
0637
0639
0641
0642
1024
1037
1039
1041
1192
1193
1194','','','','','','','','19.1-3','','','9.3.4
D.2.1
D.2.2
D.2.3
D.3.2
D.4.1
D.4.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Access Points','NET-03.1','Mechanisms exist to limit the number of external network connections that a system will simultaneously accept. ','','X','','X','X','','','X',10,'','','','12.9
12.12
15.10','','','','','','','','','','','','','SC-7(3)','SC-7(3) ','','NFO','','','','','','','','','','SC-7(3) ','','','','','','','','','','','5.10.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.3.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','External Telecommunications Services ','NET-03.2','Mechanisms exist to maintain a managed interface for each external telecommunication service that protects the confidentiality and integrity of the information being transmitted across each interface.','- Outbound content filtering','','','X','X','','','X',10,'','','','','','','','','','','','','','','','','SC-7(4)
SC-7(9)','SC-7(4) 
SC-7(9)','','NFO','','','','','','','','','','SC-7(4) ','','','','','','','','','','','5.7.1.2
5.10.1
5.10.1.1
5.10.1.3','','','','','','','','','','','','','','','','','KOS-02','','','','','','','','','','','','','','','','','','','','','1234
0561
1057
0574
1183
1151
1152
0861
1025
1026
1027','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Internal Network Address Space','NET-03.3','Mechanisms exist to prevent the public disclosure of internal address information. ','','','','X','X','','','X',7,'','','','','','','','','','','','','','','','','','','','','','','1.3.8','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Personally Identifiable Information (PII)','NET-03.4','Mechanisms exist to apply network-based processing rules to data elements of Personal Information (PI).','- Data Loss Prevention (DLP)','','','X','X','','X','X',7,'','','','','','','','','','','','','','','','','','SC-7(24)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Data Flow Enforcement – Access Control Lists (ACLs)','NET-04','Mechanisms exist to design, implement and review firewall and router configurations to restrict connections between untrusted networks and internal systems. ','- NNT Change Tracker','X','','X','X','X','X','X',10,'','','','11.2
12.12
14.4
14.8','','','','','','','9.4.1 
13.11
14.1.2','','','','','','AC-4','AC-4 ','','3.1.3','','','1.1-1.1.7
1.2-1.2.3
1.3.3
1.3.5
7.2-7.2.3 ','','','','','','','AC-4 ','','','','','','','CIP-007-6
R1','','','','5.10.1','','','','622(2)(d)(C)(iii)','','','','','','','','','','','','','KOS-02','','','','','','','','','','','','','','','','','','','','','1386
1474
0521
1186
1428
1429
1430
0525
1311
1312
1193
0639
1194
0641
0642
0643
0645
1157
1158
0646
0647
0648','','','','','','','','19.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Deny Traffic by Default & Allow Traffic by Exception','NET-04.1','Mechanisms exist to configure firewall and router configurations to deny network traffic by default and allow network traffic by exception (e.g., deny all, permit by exception). ','','X','','X','X','','X','X',10,'','','','11.2
12.10','','','','','','','13.2.1 ','','','','','','CA-3(5)
SC-7(5)
SC-7(11)','CA-3(5)
SC-7(5)
SC-7(11)','','3.13.6
NFO','','','1.2.1
1.3-1.3.7 ','','','','','','','CA-3(5) 
SC-7(5) ','','','','','','','','','','','5.10.1
5.10.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Object Security Attributes ','NET-04.2','Mechanisms exist to associate security attributes with information, source and destination objects to enforce defined information flow control configurations as a basis for flow control decisions. ','- NNT Change Tracker','','','X','','','','',5,'','','','','','','','','','','','','','','','','AC-4(1)','AC-4(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Content Check for Encrypted Data','NET-04.3','Mechanisms exist to prevent encrypted data from bypassing content-checking mechanisms. ','','X','','X','','','','',4,'','','','12.11
13.6','','','','','','','','','','','','','AC-4(4)','AC-4(4)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Embedded Data Limitations','NET-04.4','Mechanisms exist to enforce limitations on embedding data within other data types. ','- Prevent exfiltration through steganography','X','','X','','','','',2,'','','','12.11
13.6','','','','','','','','','','','','','AC-4(5)','AC-4(5)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Metadata ','NET-04.5','Mechanisms exist to enforce information flow controls based on metadata. ','','','','X','','','','',2,'','','','','','','','','','','','','','','','','AC-4(6)','AC-4(6)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','RB-11','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Human Reviews','NET-04.6','Mechanisms exist to enforce the use of human reviews for Access Control Lists (ACLs) and similar rulesets on a routine basis. ','','','','X','X','X','','X',10,'','','','','','','','','','','','','','','','','AC-4(9)','AC-4(9)','','','','','1.1.7','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','KOS-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','System Interconnections','NET-05','Mechanisms exist to authorize connections from systems to other systems using Interconnection Security Agreements (ISAs) that document, for each interconnection, the interface characteristics, security and privacy requirements and the nature of the information communicated.','- VisibleOps security management','','','X','','','','',9,'','','','','','','','','','','','','','','','','CA-3
CA-3(1)
CA-3(2)','CA-3','','NFO','','','','','','','','','','CA-3 ','','','','','','','','8-610','','','5.1
5.1.1.2
5.1.1.3
5.1.1.4
5.1.1.5
5.1.1.6
5.1.1.8
5.7.1.2
5.10.1
5.10.1.1
5.11.3','','','','','','','','','','','','','','','','','KOS-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','External System Connections','NET-05.1','Mechanisms exist to prohibit the direct connection of a sensitive system to an external network without the use of an organization-defined boundary protection device. ','','X','','X','X','','X','X',10,'','','','12.12','','','','','','','','','','','','','CA-3(3)','CA-3(3) ','','','','','1.3
1.3.3
1.3.5 ','','','','','','','CA-3(3) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0071
1433
1434
0626
0597
0627
0670
0635
0675','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Internal System Connections','NET-05.2','Mechanisms exist to control internal system connections through authorizing internal connections of systems and documenting, for each internal connection, the interface characteristics, security requirements and the nature of the information communicated.','','','','X','','','','',7,'','','','','','','','','','','','','','','','','CA-9','CA-9','','NFO','','','','','','','','','','CA-9','','','','','','','','8-610
8-700','','','5.7.1.2
5.10.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Network Segmentation','NET-06','Mechanisms exist to logically or physically segment information flows to accomplish network segmentation.','- Subnetting
- VLANs','X','','X','X','','X','X',10,'','','','11.7
14.1','','','','','','','','','','','','','AC-4(21)','AC-4(21) ','','','','','','','','','','','','AC-4(21) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','KOS-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Security Management Subnets','NET-06.1','Mechanisms exist to implement security management subnets to isolate security tools and support components from other internal system components by implementing separate subnetworks with managed interfaces to other components of the system. ','','X','','X','X','','X','X',9,'','','11.7','11.7','','','','','','','','','','','','','SC-7(13)','SC-7(13) ','','','','','','','','','','','','SC-7(13) ','','','','','','','','','','','5.10.1.1','','','','','','','','','','','','','','','','','KOS-04','','','','','','','','','','','','','','','','','','','','','1385','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Virtual Local Area Network (VLAN) Separation','NET-06.2','Mechanisms exist to enable Virtual Local Area Networks (VLANs) to limit the ability of devices on a network to directly communicate with other devices on the subnet and limit an attacker''s ability to laterally move to compromise neighboring systems. ','- Virtual Local Area Network (VLAN)','X','','','','','','',9,'','','14.1
14.3','11.7
14.1
14.3','','','','','','','','','','','','','','','','','','','','','','','',11,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1310
0529
1364
0535
0530','','','','','','','','22.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Network Disconnect','NET-07','Mechanisms exist to terminate remote sessions at the end of the session or after an organization-defined time period of inactivity. ','','X','','X','','','','',10,'','','','','','','','','','','','','','','','','SC-10','SC-10 ','','3.13.9','','','8.1.8','8.8','','','','','','SC-10 ','','','','','','','','8-609','','','5.10.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Network Intrusion Detection / Prevention Systems (NIDS / NIPS)','NET-08','Network Intrusion Detection / Prevention Systems (NIDS/NIPS) are used to detect and/or prevent intrusions into the network. ','','X','','X','X','','','X',10,'','','8.5
12.4','12.3
12.4','','','','','','','','','','','','','','','','','','','11.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0576
0577
1028
1029
1030
1185','','','','','','','','18.4','','','9.3.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','DMZ Networks','NET-08.1','Mechanisms exist to require De-Militarized Zone (DMZ) network segments to separate untrusted networks from trusted networks.','- Architectural review board
- System Security Plan (SSP)','X','','X','X','','X','X',10,'','','12.2
12.3
12.9','','','','','','','','','','','','','','','','','','','','','','','','',11,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1275
1276
1277
1278
0643
0645
1157
1158
0646
0647
0648','','','','','','','','19.2-3','','','9.3.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Wireless Intrusion Detection / Prevention Systems (WIDS / WIPS)','NET-08.2','Mechanisms exist to require wireless network segments to implement Wireless Intrusion Detection / Prevention Systems (WIDS/WIPS) technologies.','','X','','','','','','',8,'','','15.3','15.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','18.4','','','9.3.4
9.3.5','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Session Authenticity ','NET-09','Mechanisms exist to protect the authenticity of communications sessions. ','- PKI for non-repudiation','','','X','','','','',10,'','','','','','','','','','','','','','','','','SC-23','SC-23 ','','3.13.15','','','','','','','','','','SC-23 ','','','','','','','','8-609','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Domain Name Service (DNS) Resolution ','NET-10','Mechanisms exist to ensure Domain Name Service (DNS) resolution is designed, implemented and managed to protect the security of name / address resolution.','','X','','X','X','','X','X',10,'','','8.6','','','','','','','','','','','','','','SC-20','SC-20 ','','NFO','','','','','','','','','','SC-20 ','','','','','','','','','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Architecture & Provisioning for Name / Address Resolution Service','NET-10.1','Mechanisms exist to ensure systems that collectively provide Domain Name Service (DNS) resolution service for are fault-tolerant and implement internal/external role separation. ','','','','X','','','','',9,'','','','','','','','','','','','','','','','','SC-22','SC-22 ','','NFO','','','','','','','','','','SC-22 ','','','','','','','','','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Secure Name / Address Resolution Service (Recursive or Caching Resolver)','NET-10.2','Mechanisms exist to perform data origin authentication and data integrity verification on the Domain Name Service (DNS) resolution responses received from authoritative sources when requested by client systems. ','','X','','X','','','','',9,'','','','8.4','','','','','','','','','','','','','SC-21','SC-21 ','','NFO','','','','','','','','','','SC-21 ','','','','','','','','','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Out-of-Band Channels ','NET-11','Mechanisms exist to utilize out-of-band channels for the electronic transmission of information and/or the physical shipment of system components or devices to authorized individuals. ','- Signature delivery (courier service)','X','','X','','','','',9,'','','12.8','','','','','','','','','','','','','','SC-37
SC-37(1)','SC-37
SC-37(1)','','','','','','','','','','','','','','','','','','','','','','','5.6.2.1.3
5.6.2.2
5.13.7
5.13.7.2','','','','','','','','','Art 22','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Safeguarding Data Over Open Networks ','NET-12','Cryptographic mechanisms use strong cryptography and security protocols to safeguard sensitive data during transmission over open, public networks. ','','X','','X','X','','X','X',10,'','','','','','','DSI-03','','','','','','','','','','SC-14','SC-14 ','','','','','4.1 -4.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Wireless Link Protection','NET-12.1','Mechanisms exist to protect external and internal wireless links from signal parameter attacks through monitoring for unauthorized wireless connections, including scanning for unauthorized wireless access points and taking appropriate action, if an unauthorized connection is discovered.','','X','','X','','','','',10,'','','','14.2','','','','','','','','','','','','','SC-40','SC-40 ','','','','','11.1-11.1.2','','','','','','','','','','','','','','','','','','5.13.1.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0536
0543
1013
1314
1315
1316
1317
1318
1319
1320
1321
1322
1323
1324
1325
1326
1327
1328
1329
1330
1331
1332
1333
1334
1335
1336
1337
1338
1443
1444
1445
1454
','','','','','','','','11.1','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','End-User Messaging Technologies','NET-12.2','Mechanisms exist to prohibit the transmission of unprotected sensitive data by end-user messaging technologies. ','- Acceptable Use Policy (AUP)
- Data Loss Prevention (DLP)','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','4.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Electronic Messaging','NET-13','Mechanisms exist to protect information involved in electronic messaging communications.','','X','','X','X','','X','X',10,'','','','','','','','','','','13.2.3 ','','','','','','SC-8(3)
SC-19','SC-8(3)
SC-19 ','','3.13.14','','','','','','','','','','SC-19 ','','','','','','','','8-700','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Remote Access ','NET-14','Mechanisms exist to define, control and review remote access methods.','','X','','X','X','X','X','X',10,'','','12.7','12.6
12.7','','','','','','','6.2.2 ','','','','','','AC-17
AC-17(6)','AC-17 ','','3.1.1
3.1.2','PR.AC-3','','12.3.8 
12.3.9 ','9.1','','','','','','AC-17 ','','D3.PC.Am.B.15
D3.PC.De.E.7
D3.PC.Im.Int.2','','','','164.308(a)(4)(i)
164.308(b)(1)
164.308(b)(3)
164.310(b)
164.312(e)(1)
164.312(e)(2)(ii)','CIP-005-5
R2','','','','5.5.6
5.5.6.1
5.5.6.2
5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','16.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Automated Monitoring & Control ','NET-14.1','Automated mechanisms exist to monitor and control remote access sessions. ','','X','','X','','','','',1,'','','','12.7','','','','','','','','','','','','','AC-17(1)','AC-17(1) ','','3.1.12','','','','','','','','','','AC-17(1) ','','','','','','','','','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Protection of Confidentiality / Integrity Using Encryption','NET-14.2','Cryptographic mechanisms exist to protect the confidentiality and integrity of remote access sessions. ','','X','','X','','','','',10,'','','3.4','12.7','','','','','','','','','','','','','AC-17(2)','AC-17(2) ','','3.1.13','','','','9.1','','','','','','AC-17(2) ','','','','','','','','','','','5.10.1.2
5.10.1.2.1
5.10.1.2.2
5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Managed Access Control Points','NET-14.3','Mechanisms exist to route all remote accesses through managed network access control points (e.g., VPN concentrator).','','X','','X','','','','',10,'','','','12.7','','','','','','','','','','','','','AC-17(3)','AC-17(3) ','','3.1.14','','','','','','','','','','AC-17(3) ','','','','','','','','','','','5.5.6
5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Remote Privileged Commands & Sensitive Data Access','NET-14.4','Mechanisms exist to restrict the execution of privileged commands and access to security-relevant information via remote access only for compelling operational needs. ','','X','','X','','','','',8,'','','','12.7','','','','','','','','','','','','','AC-17(4)','AC-17(4) ','','3.1.15','','','','','','','','','','AC-17(4) ','','','','','','','','','','','5.5.6
5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0985','','','','','','','','16.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Telecommuting ','NET-14.5','Mechanisms exist to govern remote access to systems and data for remote workers. ','','X','','X','X','','X','X',10,'','','','12.7','','','','','','','6.2.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0685
0865
0866','','','','','','','','21.2-3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Third-Party Remote Access Governance','NET-14.6','Mechanisms exist to proactively control and monitor third-party accounts used to access, support, or maintain system components via remote access.','','X','','X','X','','X','X',10,'','','','12.7','','','','','','','','','','','','','','','','','','','8.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','16.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Endpoint Security Validation ','NET-14.7','Mechanisms exist to validate software versions/patch levels and control remote devices connecting to corporate networks or storing and accessing organization information. ','','X','','','','','','',6,'','','','12.7','','','MOS-19','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1307','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Expeditious Disconnect / Disable Capability ','NET-14.8','Mechanisms exist to provide the capability to expeditiously disconnect or disable a user''s remote access session.','','X','','X','','','','',8,'','','','12.7','','','','','','','','','','','','','AC-17(9)','AC-17(9)','','','','','','','','','','','','AC-17(9)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Wireless Networking ','NET-15','Mechanisms exist to control authorized wireless usage and monitor for unauthorized wireless access.','','X','','X','X','','X','X',10,'','','15.5
15.6 ','','','','IVS-12','','','','','','','','','','AC-18','AC-18 ','','3.1.16','','','','','','','','','','AC-18 ','','','','','','','','8-311','','','5.13.1
5.13.1.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0536
0543
1013
1314
1315
1316
1317
1318
1319
1320
1321
1322
1323
1324
1325
1326
1327
1328
1329
1330
1331
1332
1333
1334
1335
1336
1337
1338
1443
1444
1445
1454','','','','','','','','18.2','','','9.3.5','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Authentication & Encryption','NET-15.1','Authentication and cryptographic mechanisms exist to protect wireless access.','','X','X','X','X','','X','X',10,'','','','15.5
15.6','','','','','','','','','','','','','AC-18(1)','AC-18(1) ','','3.1.17','','','4.1.1','','','','','','','AC-18(1) ','','','','','','','','','','','5.13.1.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Disable Wireless Networking','NET-15.2','Mechanisms exist to disable unnecessary wireless networking capabilities that are internally embedded within system components prior to issuance to end users. ','','X','X','X','','','','',5,'','','','15.1','','','','','','','','','','','','','AC-18(3)','AC-18(3)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Restrict Configuration By Users','NET-15.3','Mechanisms exist to identify and explicitly authorize users who are allowed to independently configure wireless networking capabilities. ','','X','X','X','','','','',8,'','','15.1','','','','','','','','','','','','','','AC-18(4)','AC-18(4)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Wireless Boundaries','NET-15.4','Mechanisms exist to confine wireless communications to organization-controlled boundaries. ','','X','X','X','','','','',5,'','','15.4
15.7
15.8','15.4
15.7
15.8','','','','','','','','','','','','','AC-18(5)','AC-18(5)','','','','','','','','','','','','','','','','','','','','','','','5.13.1.1
5.13.1.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Rogue Wireless Detection','NET-15.5','Mechanisms exist to test for the presence of Wireless Access Points (WAPs) and identify all authorized and unauthorized WAPs within the facility(ies). ','','X','','','','','','',8,'','','15.2','15.2','','','','','','','','','','','','','','','','','','','11.1-11.1.2 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Intranets','NET-16','Mechanisms exist to establish trust relationships with other organizations owning, operating, and/or maintaining intranet systems, allowing authorized individuals to: 
 ▪ Access the intranet from external systems; and
 ▪ Process, store, and/or transmit organization-controlled information using the external systems.','','X','','X','X','','X','X',10,'','','','','','','','','','','14.1.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Data Loss Prevention (DLP) ','NET-17','Data Loss Prevention (DLP) mechanisms exist to protect sensitive information as it is stored, transmitted and processed.','- Data Loss Prevention (DLP)','X','','','','','','',8,'','','13.3
13.6
13.7
13.9','13.8','','','','','','','8.3.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.0.2
9.1.1
9.1.2
9.1.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Content Filtering ','NET-18','Mechanisms exist to force Internet-bound network traffic through a proxy device for URL content filtering to limit a user''s ability to connect to prohibited content.','','X','','X','X','','X','X',10,'','','7.6
12.1
12.5
13.8','7.6
7.7
7.8
7.9
12.1
12.5
13.5
13.6
13.7','','','','','','','','','','','','','SC-7(8)
SC-18(3)','SC-7(8)
SC-18(3)','','','','','','','','','','','','','','','','','','','','','','','5.10.1
5.10.1.1
5.13.4.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0258
0260
0261
0263
0649
0650
0651
0652
0659
0660
0667
0673
0677
0958
0959
0960
0961
0963
0995
0996
1077
1170
1171
1235
1236
1237
1284
1285
1286
1287
1288
1289
1290
1291
1292
1293
1294
1295
1389','','','','','','','','14.3
20.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Network Security','Route Traffic to Proxy Servers','NET-18.1','Mechanisms exist to route internal communications traffic to external networks through organization-approved proxy servers at managed interfaces. ','','X','','X','','X','','',9,'','','','12.5','','','','','','','','','','','','','SC-7(8)','SC-7(8) ','','','','','1.3','','','','','','','SC-7(8) ','','','','','','','','','','','5.10.1
5.10.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','20.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Physical & Environmental Protections','PES-01','Mechanisms exist to facilitate the operation of physical and environmental protection controls. ','','X','X','X','X','','X','X',10,'A1.2 ','A1.2 ','','','DSS01.05','','','SO9','8.2.3
8.2.4','','','','','','','','PE-1','PE-1 ','','NFO','','','','','','','','','','PE-1 ','','','','','','164.310(a)(1)','CIP-006-6
R1 & R3','8-308','','','5.9.1
5.9.1.1
5.10.1.5','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','PS-01','','','','','','','','','','','','','','','','','','','','','1015
0558','','','','','','','','8.1-4
11.7','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Physical Access Authorizations ','PES-02','Physical access control mechanisms exist to maintain a current list of personnel with authorized access to organizational facilities (except for those areas within the facility officially designated as publicly accessible).','','X','','X','X','','','X',7,'CC5.5','CC5.5','','','','','','','','','11.1.1 ','','','','','','PE-2','PE-2 ','','3.10.1
3.10.2','','','9.2','','','','','','','PE-2 ','','','','','','164.310(a)(2)(ii) ','','8-308
5-306
5-308
6-104','','','5.9.1.2
5.9.2
5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.1-4','','','10.2.1
10.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Role-Based Physical Access','PES-02.1','Physical access control mechanisms exist to authorize physical access to facilities based on the position or role of the individual.','','X','','X','X','','','X',10,'','','','','','','DCS-09','','','','','','','','','','PE-2(1)','PE-2(1)','','','','','','','','','','','','','','','','','','164.310(a)(2)(iii)','','','','','5.9.1.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.1-4','','','10.2.1
10.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Physical Access Control ','PES-03','Physical access control mechanisms exist to enforce physical access authorizations for all physical access points (including designated entry/exit points) to facilities (excluding those areas within the facility officially designated as publicly accessible).','- Security guards
- Verify individual access authorizations before granting access to the facility.
-  Control entry to the facility containing the system using physical access devices and/or guards.
- Control access to areas officially designated as publicly accessible in accordance with the organization’s assessment of risk.
-  Secure keys, combinations and other physical access devices.
- Change combinations and keys and when keys are lost, combinations are compromised or individuals are transferred or terminated.','X','','X','X','','X','X',10,'','','','','DSS05.05 DSS05.06','','DCS-02 ','SO9','','','9.1.1 ','','','','','','PE-3
PE-3(2)
PE-3(3)','PE-3
PE-3(2)
PE-3(3)','','3.10.3
3.10.4
3.10.5','PR.AC-2','','9.1-9.1.2
9.2
9.4.2
9.4.3','','','','',8,'','PE-3 ','','D3.PC.Am.B.11
D3.PC.Am.B.17','','','','164.308(a)(1)(ii)(B)
164.308(a)(7)(i)
164.308(a)(7)(ii)(A)
164.310(a)(1)
164.310(a)(2)(i)
164.310(a)(2)(ii)
164.310(a)(2)(iii)
164.310(a)(2)(iv)
164.310(b)
164.310(c)
164.310(d)(1)
164.310(d)(2)(iii)','','5-300
6-104','','','5.1.1.7
5.9.1.2
5.9.1.3
5.9.1.6
5.9.1.7
5.10.1.1
5.10.1.5','','17.03(2)(g)','','622(2)(d)(C)(ii)','','','','','','','','','','','','','PS-02','','','','','','','','','','','','','','','','','','','','','1296
1053
0813
1074
0150','','','','','','','','8.1-4
11.7','','','10.2.1
10.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Controlled Ingress & Egress Points','PES-03.1','Physical access control mechanisms exist to limit and monitor physical access through controlled ingress and egress points.','','X','','X','X','X','X','X',9,'','','','','','','DCS-07
DCS-08','','','','','','','','','','','','','','','','9.1-9.1.3 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Lockable Physical Casings','PES-03.2','Physical access control mechanisms exist to protect system components from unauthorized physical access (e.g., lockable physical casings). ','- CCTV
- Lockable server/network racks
- Logged access badges to access server rooms','','','X','','','','',10,'','','','','','','','','','','','','','','','','PE-3(4)
SC-7(14)','PE-3(4)
SC-7(14)','','','','','','','','','','','','','','','','','','','','','','','5.10.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Physical Access Logs ','PES-03.3','Physical access control mechanisms exist to generate a log entry for each access through controlled ingress and egress points.','','','','X','X','X','','X',10,'','','','','','','','','','','','','','','','','PE-8','PE-8 ','','NFO','','','9.4.4 ','','','','','','','PE-8 ','','','','','','164.310(c) ','','','','','5.9.1.8','','','','622(2)(d)(C)(ii)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Physical Security of Offices, Rooms & Facilities','PES-04','Physical access control mechanisms are designed and implemented for offices, rooms and facilities.','- \"clean desk\" policy
- Management spot checks','X','','X','X','','X','X',10,'','','','','','','DCS-06','','','','11.1.1
11.1.3 ','','','','','','','','','','','','9.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','PS-01
PS-03','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Working in Secure Areas','PES-04.1','Physical access control mechanisms ensure that only authorized personnel are allowed access to secure areas. ','- Visitor escorts','X','','X','X','','X','X',10,'','','','','','','','','','','11.1.2
11.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Monitoring Physical Access','PES-05','Physical access control mechanisms exist to monitor for, detect and respond to physical security incidents.','','X','','X','X','X','','',10,'','','','','DSS05.07','','','SO9','','','','','','','','','PE-6','PE-6 ','','3.10.1
3.10.2','DE.CM-2','','9.1 -9.1.1 ','','','','','','','PE-6 ','','D3.PC.Am.E.4
D3.Dc.Ev.B.5','','','','164.310(a)(2)(ii)
164.310(a)(2)(iii)
164.310(c)','','5-300','','','5.9.1.6','','','','622(2)(d)(C)(ii)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','10.2.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Intrusion Alarms / Surveillance Equipment ','PES-05.1','Physical access control mechanisms exist to monitor physical intrusion alarms and surveillance equipment. ','','','','X','','X','','',9,'','','','','','','','','','','','','','','','','PE-6(1)','PE-6(1) ','','NFO','','','','','','','','','','PE-6(1) ','','','','','','','','','','','5.9.1.6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','10.2.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Visitor Control','PES-06','Physical access control mechanisms exist to identify, authorize and monitor visitors before allowing access to the facility (other than areas designated as publicly accessible). ','','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','9.4-9.4.4','','','','',9,'','','','','','','','','CIP-006-6
R2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.4','','','10.2.1
10.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Distinguish Visitors from On-Site Personnel','PES-06.1','Physical access control mechanisms exist to easily distinguish between onsite personnel and visitors, especially in areas where sensitive data is accessible. ','- Visible badges for visitors that are different from organizational personnel','','','X','X','','','X',10,'','','','','','','','','','','','','','','','','','','','','','','9.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Identification Requirement','PES-06.2','Physical access control mechanisms exist to requires at least one (1) form of government-issued photo identification to authenticate individuals before they can gain access to the facility.','','','','X','X','X','','X',8,'','','','','','','','','','','','','','','','','PE-2(2)','PE-2(2)','','','','','9.4-9.4.3 ','','','','','','','','','','','','','','','','','','','','','','622(2)(d)(C)(ii)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Restrict Unescorted Access','PES-06.3','Physical access control mechanisms exist to restrict unescorted access to facilities to personnel with required security clearances, formal access authorizations and validated the need for access. ','','','','X','X','X','','X',10,'','','','','','','','','','','','','','','','','PE-2(3)','PE-2(3)','','','','','9.3','','','','',9,'','','','','','','','','','','','','5.9.1.7','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','9.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Supporting Utilities ','PES-07','Facility security mechanisms exist to protect power equipment and power cabling for the system from damage and destruction. ','','X','','X','','','','X',9,'','','','','','','BCR-03 ','SO10','','','11.2.2
11.2.3 ','','','','','','PE-9','PE-9 ','','','','','','','','','','','','PE-9 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','PS-04','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','10.3.1
10.3.2
10.3.3
10.3.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Automatic Voltage Controls','PES-07.1','Facility security mechanisms exist to utilize automatic voltage controls for critical system components. ','','X','','X','','','','X',8,'','','','','','','','','','','11.2.2','','','','','','PE-9(2)','PE-9(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','PS-04','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Emergency Shutoff','PES-07.2','Facility security mechanisms provide the capability of shutting off power in emergency situations by:
 ▪ Placing emergency shutoff switches or devices in close proximity to systems or system components to facilitate safe and easy access for personnel; and
 ▪ Protecting emergency power shutoff capability from unauthorized activation.','','','','X','','','','X',8,'','','','','','','','','','','','','','','','','PE-10','PE-10 ','','','','','','','','','','','','PE-10 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Emergency Power','PES-07.3','Facility security mechanisms provide a long-term alternate power supply for systems that is self-contained and not reliant on external power generation. ','','','','X','','','','X',8,'','','','','','','','','','','','','','','','','PE-11','PE-11 ','','','','','','','','','','','','PE-11 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','PS-04','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','10.3.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Emergency Lighting','PES-07.4','Facility security mechanisms exist to utilize and maintain automatic emergency lighting that activates in the event of a power outage or disruption and that covers emergency exits and evacuation routes within the facility. ','','','','X','','','','X',7,'','','','','','','','','','','','','','','','','PE-12','PE-12 ','','','','','','','','','','','','PE-12 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Water Damage Protection','PES-07.5','Facility security mechanisms exist to protect systems from damage resulting from water leakage by providing master shutoff valves that are accessible, working properly and known to key personnel. ','- Water leak sensors
- Humidity sensors','','','X','','','','X',8,'','','','','','','','','','','','','','','','','PE-15','PE-15 ','','','','','','','','','','','','PE-15 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Fire Protection','PES-08','Facility security mechanisms exist to utilize and maintain fire suppression and detection devices/systems for the system that are supported by an independent energy source. ','','','','X','','','','X',7,'','','','','','','','','','','','','','','','','PE-13','PE-13 ','','','','','','','','','','','','PE-13 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','10.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Fire Detection Devices','PES-08.1','Facility security mechanisms exist to utilize and maintain fire detection devices/systems that activate automatically and notify organizational personnel and emergency responders in the event of a fire. ','','','','X','','','','X',9,'','','','','','','','','','','','','','','','','PE-13(1)','PE-13(1) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','10.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Fire Suppression Devices','PES-08.2','Facility security mechanisms exist to utilize fire suppression devices/systems that provide automatic notification of any activation to organizational personnel and emergency responders. ','','','','X','','','','X',3,'','','','','','','','','','','','','','','','','PE-13(2)
PE-13(2)','PE-13(2) ','','','','','','','','','','','','PE-13(2) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','10.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Temperature & Humidity Controls','PES-09','Facility security mechanisms exist to maintain and monitor temperature and humidity levels within the facility.','','','','X','','X','','X',9,'','','','','','','','','','','','','','','','','PE-14','PE-14 ','','','','','','','','','','','','PE-14 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','10.3.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Monitoring with Alarms / Notifications','PES-09.1','Facility security mechanisms provides an alarm or notification of temperature and humidity changes that be potentially harmful to personnel or equipment. ','','','','X','','X','','X',10,'','','','','','','','','','','','','','','','','PE-14(2)','PE-14(2) ','','','','','','','','','','','','PE-14(2) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','10.3.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Delivery & Removal ','PES-10','Physical security mechanisms exist to isolate information processing facilities from points such as delivery and loading areas and other points to avoid unauthorized access. ','','X','','X','','','','X',10,'','','','','','','','','','','11.1.6 ','','','','','','PE-16','PE-16 ','','NFO','','','','','','','','','','PE-16 ','','','','','','','','','','','','','','','622(2)(d)(C)(ii)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Alternate Work Site','PES-11','Physical security mechanisms exist to utilize appropriate management, operational, and technical controls at alternate work sites.','','','','X','X','','','X',8,'','','','','','','','','','','','','','','','','PE-17','PE-17 ','','3.10.6','','','','','','','','','','PE-17 ','','','','','','','','','','','5.3.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Equipment Siting & Protection ','PES-12','Physical security mechanisms exist to locate system components within the facility to minimize potential damage from physical and environmental hazards and to minimize the opportunity for unauthorized access. ','','X','','X','X','','X','X',10,'','','','','DSS05.06','','BCR-06','','','','11.2.1
11.2.3 ','','','','','','PE-18
PE-18(1)
SC-7(14)','PE-18
PE-18(1)
SC-7(14)','','','','','','','','','','','','','','','','','','','','','','','5.10.1.1
5.10.1.5
5.13.7.2.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1015
0558','','','','','','','','8.1
8.2
8.3
8.4
10.1-6','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Access Control for Transmission Medium','PES-12.1','Physical security mechanisms exist to protect power and telecommunications cabling carrying data or supporting information services from interception, interference or damage. ','','X','','X','X','','X','X',10,'','','','','','','','','','','11.2.3 ','','','','','','PE-4
SC-7(14)','PE-4
SC-7(14)','','3.10.2','','','9.1.2 
9.1.3 ','','','','','','','PE-4 ','','','','','','','CIP-014-2
R5','8-605','','','5.9.1.4
5.10.1.1','','','','622(2)(d)(C)(ii) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0181
0182
0184
0186
0187
0189
0190
0194
0195
0196
0198
0201
0202
0203
0204
0205
0206
0207
0208
0209
0210
0211
0213
0214
0215
0216
0217
0218
0825
0826
0827
0828
0926
1093
1094
1095
1096
1097
1098
1099
1100
1101
1102
1103
1104
1105
1106
1107
1108
1109
1110
1111
1112
1114
1115
1116
1117
1118
1119
1120
1121
1122
1123
1124
1125
1126
1127
1128
1129
1130
1131
1132
1133
1134
1135
1136
1164
1165
1215
1216','','','','','','','','8.1
8.2
8.3
8.4
10.1-6','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Access Control for Output Devices','PES-12.2','Physical security mechanisms exist to restrict access to printers and other system output devices to prevent unauthorized individuals from obtaining the output. ','- Printer management (print only when at the printer with proximity card or code)','X','','X','X','','X','X',10,'','','','','DSS05.06','','','','','','','','','','','','PE-5','PE-5 ','','3.10.1
3.10.2','','','','','','','','','','PE-5 ','','','','','','','','8-310','','','5.9.1.5
5.9.1.6
5.9.2','','','','622(2)(d)(C)(ii)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','8.1
8.2
8.3
8.4','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Information Leakage Due To Electromagnetic Signals Emanations','PES-13','Facility security mechanisms exist to protect the system from information leakage due to electromagnetic signals emanations. ','','X','','X','X','','','',5,'','','','','','','','','','','','','','','','','','PE-19','','','PR.DS-5','','','','','','','','','','','D3.PC.Am.B.15
D3.PC.Am.Int.1
D3.PC.De.Int.1
D3.DC.Ev.Int.1','','','','164.308(a)(1)(ii)(D)
164.308(a)(3)
164.308(a)(4)
164.310(b)
164.310(c)
164.312(a)
164.312€','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0247
0248
1137
0932
0249
0246
0250','','','','','','','','10.7','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Asset Monitoring and Tracking','PES-14','Physical security mechanisms utilize asset location technologies to track and monitor the location and movement of organization-defined assets within organization-defined controlled areas.','- RFID tagging','','','X','','','','',6,'','','','','','','','','','','','','','','','','','PE-20','','','','','','','','','','','','','','','','','','','','','','','5.13.7.2.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Electromagnetic Pulse (EMP) Protection','PES-15','Physical security mechanisms utilize organization-defined security safeguards against electromagnetic pulse damage for systems and system components.','- EMP shielding (Faraday cages)','','','X','','','','',1,'','','','','','','','','','','','','','','','','','PE-21','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Physical & Environmental Security ','Component Marking','PES-16','Physical security mechanisms exist to mark system hardware components indicating the impact or classification level of the information permitted to be processed, stored or transmitted by the hardware component.','','','','X','','','','',3,'','','','','','','','','','','','','','','','','','PE-22','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Privacy Program','PRI-01','Mechanisms exist to facilitate the implementation and operation of privacy controls. ','','X','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','IP-1
PA-1
PM-18','','','','','','','','','','','','','§ 1232h','','','','','','','','','','','','','','','','','Inferred
Expectation','Art 32','','Sec 14
Sec 15','Art 4','Inferred
Expectation','Inferred
Expectation','Sec 8
Sec 9','Inferred
Expectation','Inferred
Expectation','','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Art 43','Sec 23','Inferred
Expectation','Inferred
Expectation','Inferred
Expectation','Sec 12','Inferred
Expectation','Art 3
Art 30','Inferred
Expectation','Art 2','Sec 6','Principle 1
Principle 8','Inferred
Expectation','Art 4','Art 10','Art 6
Art 14
Art 30','Art 12
Art 31','Art 5');
INSERT INTO complianceforge_scf VALUES ('Privacy','Chief Privacy Officer (CPO)','PRI-01.1','Mechanisms exist to appoints a Chief Privacy Officer (CPO) or similar role, with the authority, mission, accountability and resources to coordinate, develop and implement, applicable privacy requirements and manage privacy risks through the organization-wide privacy program.','','X','','X','X','','X','X',3,'','','','','','','','','1.1.0
1.1.2
1.2.1
1.2.2
1.2.8
1.2.9
2.1.0
4.2.3
8.2.1','','18.1.4','A.10.11
A.10.12','','','','','AR-1','PM-19','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 37
Art 38
Art 39','','','','','Art 6','','Art 32','Sec 4d
Sec 4f
Sec 4g','','','Sec 24','Sec 2','Sec 16
Sec 17','Sec 30','','','','Art 46','','Art 23','','Sec 48','','Sec 30
Sec 38','','','Sec 21','','','','','','','','Art 21','','','','','Sec 11','','Art 31','','Art 10','','','Art 7
Art 11','Art 17
Art 18','Art 11','','','Art 12');
INSERT INTO complianceforge_scf VALUES ('Privacy','Privacy Act Statements','PRI-01.2','Mechanisms exist to provide additional formal notice to individuals from whom the information is being collected that includes:
 ▪ Notice of the authority of organizations to collect Personal Information (PI); 
 ▪ Whether providing Personal Information (PI) is mandatory or optional; 
 ▪ The principal purpose or purposes for which the Personal Information (PI) is to be used; 
 ▪ The intended disclosures or routine uses of the information; and 
 ▪ The consequences of not providing all or some portion of the information requested.','','X','','X','','','X','',2,'','','','','','','','','10.2.3','','','','','','','','TR-2','IP-5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Chapter29-Schedule1-Part1-Principles 8','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Dissemination of Privacy Program Information ','PRI-01.3','Mechanisms exist to: 
 ▪ Ensure that the public has access to information about organizational privacy activities and can communicate with its Chief Privacy Officer (CPO) or similar role;
 ▪ Ensure that organizational privacy practices are publicly available through organizational websites or otherwise; and
 ▪ Utilize publicly facing email addresses and/or phone lines to enable the public to provide feedback and/or direct questions to privacy offices regarding privacy practices.','','X','','X','','','X','',5,'','','','','','','','','2.1.1
2.2.1
2.2.2
2.2.3
3.1.0
3.1.1
3.1.2
4.1.0
4.1.1
4.2.4
5.1.0
5.1.1
6.1.0
7.1.0
7.1.1
8.1.0
8.1.1
9.1.0
9.1.1
10.1.0
10.1.1','','','','','','','','TR-3','PM-21','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Data Protection Officer (DPO)','PRI-01.4','Mechanisms exist to appoint a Data Protection Officer (DPO):
 ▪ Based on the basis of professional qualities; and
 ▪ To be involved in all issues related to the protection of personal data.','','X','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 37
Art 38
Art 39','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Sec 6','','','','','','Art 12');
INSERT INTO complianceforge_scf VALUES ('Privacy','Notice ','PRI-02','Mechanisms exist to:
 ▪ Make privacy notice(s) available to individuals upon first interacting with an organization and subsequently as necessary.
 ▪ Ensure that privacy notices are clear and easy-to-understand, expressing information about Personal Information (PI) processing in plain language.','','X','','X','X','','X','X',10,'P1.1','P1.1','','','','','','','2.1.1
2.2.1
2.2.2
2.2.3
3.1.0
3.1.1
3.1.2
4.1.0
4.1.1
4.2.4
5.1.0
5.1.1
6.1.0
7.1.0
7.1.1
8.1.0
8.1.1
9.1.0
9.1.1
10.1.0
10.1.1','','','A.2.1
A.2.2','','','','','TR-1','IP-4','','','','','','',6502,'','','','','','§ 1232g','','','','','','','','Principle 1
Principle 3','','','','','','','','','','Art 12
Art 13
Art 14','','','Art 9','Art 5
Art 11
Art 16','Art 6
Art 31','','','Sec 4
Sec 19','','','','','','Sec 11
Sec 13
Sec 37','','Sec 8
Sec 27','Sec 31','Art 23','Art 5','Art 22','','Sec 18','Art 8','Sec 26','','Art 10','','','APP Part 5','','','','','','Art 15','Sec 7','','','','Sec 14','','Art 3
Art 4','Art 5','Art 6','','Principle 2','Art 5','Art 12','Art 5','Art 7
Art 16
Art 17
Art 18','Art 7','Art 5
Art 13');
INSERT INTO complianceforge_scf VALUES ('Privacy','Purpose Specification','PRI-02.1','Mechanisms exist to identify and document the purpose(s) for which Personal Information (PI) is collected, used, maintained and shared in its privacy notices.','','X','','X','X','','X','X',10,'P2.1','P2.1','','','','','','','4.2.1','','','','','','','','AP-2','PA-3','','','','','','','','','','','','','§ 1232g','','','','','','','','','','','','','','','','','','Art 12
Art 13
Art 14','','Sec 6','Art 4-7','Art 5','','','Art 7','','','','','Sec 2','Sec 8','Sec 13','','','Sec 32','Art 23','Art 5','Art 5','','Sec 13','','Sec 26','','Art 10','','','APP Part 3','','','Principle 1','','','Art 15','','','','Sec 19','Sec 14
Sec 19
Sec 20','','Art 3
Art 4','Art 5
Art 19','Art 6','Sec 6','Sec 5
Principle 2','Art 5','Art 4','','Art 7
Art 16
Art 17
Art 18','Art 6','Art 5
Art 8');
INSERT INTO complianceforge_scf VALUES ('Privacy','Automation','PRI-02.2','Automated mechanisms exist to support records management of authorizing policies and procedures for Personal Information (PI).','','X','','X','','','X','',1,'','','','','','','','','','','','','','','','','','PA-3(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 22','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 5','','','','','','','Art 7','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Computer Matching Agreements (CMA) ','PRI-02.3','Mechanisms exist to publish Computer Matching Agreements (CMA) on the public website of the organization.','','','','X','','','X','',1,'','','','','','','','','','','','','','','','','','PM-25(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Choice & Consent','PRI-03','Mechanisms exist to authorize the processing of their Personal Information (PI) prior to its collection that:
 ▪ Uses plain language and provide examples to illustrate the potential privacy risks of the authorization; and
 ▪ Provides a means for users to decline the authorization.','- \"opt in\" vs \"opt out\" user selections','X','','X','X','','X','X',10,'P3.2','P3.2','','','','','','','3.2.1
3.2.2
3.2.3
3.2.4','','','A.1.1','','','','','IP-1','IP-2','','','','','','','','','','','','','§ 1232g','','','','','','','','Principle 2','','','','','','','','','Art 8
Art 9','Art 6
Art 7
Art 8
Art 12','','Sec 8','Art 4-7','Art 5','','','Art 7','Sec 4a
Sec 11','','Art 5','Sec 6','Sec 2','','Sec 23
Sec 24','','Sec 8','','Art 23','Art 6','Art 6
Art 9','','Sec 11','Art 8
Art 12','Sec 10','','Art 10','Sec 9','','APP Part 3','','','','Sec 5','','','Sec 7','Principle 2','','Sec 19','Sec 13','','Art 3
Art 4
Art 22','Art 5','Art 5','','Sec 6
Sec 7
Principle 3','Art 4','Art 4','Art 5','Art 8
Art 10','Art 5
Art 13
Art 14','Art 5
Art 9
Art 13');
INSERT INTO complianceforge_scf VALUES ('Privacy','Attribute Management','PRI-03.1','Mechanisms exist to allow data subjects to tailor use permissions to selected attributes.','','X','','X','','','X','',1,'','','','','','','','','','','','','','','','','','IP-2(1)','','','','','','',6502,'','','','','','','','','','','','','','','','','','','','','','','Art 8
Art 10','Art 7
Art 12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 5','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Just-In-Time Notice & Consent','PRI-03.2','Mechanisms exist to present authorizations to process Personal Information (PI) in conjunction with the data action, when:
▪ The original circumstances under which an individual gave consent have changed; or
▪ A significant amount of time has passed since an individual gave consent.','','X','','X','','','X','',1,'','','','','','','','','','','','','','','','','','IP-2(2)
IP-4(1)','','','','','','','','','','','','','','','','','','','','','Principle 2','','','','','','','','','','Art 7
Art 8
Art 12','','Sec 8','','Art 16','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 22','Art 5','Art 5','','Sec 6
Sec 7
Principle 3','','','','Art 7','','Art 5
Art 9');
INSERT INTO complianceforge_scf VALUES ('Privacy','Collection','PRI-04','Mechanisms exist to collect Personal Information (PI) only for the purposes identified in the privacy notice. ','','X','','X','X','','X','X',10,'P3.1 ','P3.1 ','','','','','','','4.1.2
9.2.2','','','','','','','','AP-1','PA-1
PA-2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 5
Art 8','Art 5','','Sec 6','Art 4-7','Art 9','Art 6','Sec 6
Sec 7','Art 7','Sec 4','','Art 4','Sec 4
Sec 5','Sec 2','','Sec 11','','Sec 8','','Art 23','Art 5','Art 5','','','Art 8','Sec 10','Art 4','Art 10','Sec 9','','APP Part 3','','','','Sec 5','','Art 17','','Principle 2','','Sec 19','Sec 17','','Art 3
Art 15
Art 22','Art 5
Art 19','Art 5','Sec 6','Sec 5
Principle 4','','Art 4','Art 6','Art 7','Art 4
Art 14','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Authority To Collect','PRI-04.1','Mechanisms exist to determine and document the legal authority that permits the collection, use, maintenance and sharing of Personal Information (PI), either generally or in support of a specific program or system need.','','X','','X','X','','X','X',10,'','','','','','','','','1.2.5
1.2.11
4.2.2','','','','','','','','AP-1','PA-1
PA-2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 6','Art 5','','Sec 6','Art 4-7','Art 9','Art 6','Sec 6
Sec 7','Art 7','Sec 4','','Art 4','Sec 4
Sec 5','Sec 2','','Sec 11','','Sec 8','','Art 23','Art 5','Art 5','','','Art 8','Sec 10','Art 4','Art 10','Sec 9','','APP Part 3','','','','Sec 5','','Art 17','','Principle 2','','Sec 19','Sec 17','','Art 3
Art 15','Art 5
Art 19','Art 5','Sec 6','Sec 5
Principle 4','','Art 4','','Art 7','Art 4
Art 14','Art 5');
INSERT INTO complianceforge_scf VALUES ('Privacy','Use, Retention & Disposal','PRI-05','Mechanisms exist to: 
 ▪ Retain Personal Information (PI), including metadata, for an organization-defined time period to fulfill the purpose(s) identified in the notice or as required by law;
 ▪ Disposes of, destroys, erases, and/or anonymizes the PI, regardless of the method of storage; and
 ▪ Uses organization-defined techniques or methods to ensure secure deletion or destruction of PI (including originals, copies and archived records).','','X','X','X','X','','X','X',10,'','','','','','','','','4.1.2
5.2.2
5.2.3','','','A.4.1
A.9.2
A.9.3
A.10.2
A.10.7 
A.10.8
A.10.9
A.10.10
A.10.13 ','','','','','DM-2','SI-18
AC-23','3.4.14','','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','3.2-3.2.3','','','','','','','','§ 1232g','','','','','','','','Principle 5','','','','',500.13,'','Sec. 521.052(b)','','Art 5
Art 7
Art 8
Art 13','Art 5
Art 18','Art 24','Sec 7','Art 4-7
Art 21','Art 5
Art 6
Art 20','Art 6','Sec 11
Sec 21
Sec 34
Sec 35','Art 6
Art 36','Sec 3a
Sec 5
Sec 13
Sec 14
Sec 20','RB-11
PI-05','Art 4
Art 7','Sec 5','Sec 2','Sec 8','Sec 11','Art 5-1','Sec 6
Sec 7
Sec 9
Sec 10
Sec 12','Sec 8
Sec 11
Sec 15
Sec 27
Sec 28','Art 23
Art 26','Art 5','Art 5','Sec 5
Sec 6','Sec 4
Sec 14
Sec 16','Art 8
Art 22','Sec 9','Art 4','Art 5
Art 7','Sec 8','Chapter29-Schedule1-Part1-Principle 5','APP Part 3
APP Part 6','0311
0312
0313
0315
0316
0317
0318
0319
0321
0322
0329
0350
0361
0362
0363
0364
0366
0368
0370
0371
0372
0373
0374
0375
0378
0838
0839
0840
1069
1076
1160
1217
1218
1219
1220
1221
1222
1223
1224
1225
1226
1347
1360
1361
1455','','
Principle 2
Sec 26
Principle 3
Sec 4','Sec 5','Art 46','Art 15
Art 44','Sec 5
Sec 6
Sec 10','Principle 1
Principle 4
Principle 9
Principle 11','12.6
13.5','Sec 19
Sec 21','Sec 23
Sec 25','','Art 3
Art 4
Art 15
Art 19
Art 21
Art 37','Art 5
Art 19','Art 4','Sec 6
Sec 12','Sec 7
Sec 8
Principle 5
Principle 6','Art 9','Art 4','Art 6','Art 7
Art 8
Art 9
Art 11
Art 12
Art 13
Art 14','Art 7
Art 8
Art 14','Art 5
Art 6
Art 20
Art 21
Art 22');
INSERT INTO complianceforge_scf VALUES ('Privacy','Internal Use','PRI-05.1','Mechanisms exist to address the use of Personal Information (PI) for internal testing, training and research that:
 ▪ Takes measures to limit or minimize the amount of PI used for internal testing, training and research purposes; and
 ▪ Authorizes the use of PI when such information is required for internal testing, training and research.','','X','','X','X','','X','X',10,'','','','','','','','','4.1.2
7.2.2
9.2.1
9.2.2','','','A.3','','','','','DM-1
DM-3','PM-26','','','','','3.3','','','','','','','','','','','','','','','','','','','','','','','','','Art 5
Art 7','Art 5
Art 11
Art 18','','','','','','','','','','','','','','','','Sec 11','Sec 11
Sec 27','Art 26','','','','Sec 10','Art 8','','','','Sec 8','Chapter29-Schedule1-Part1-Principle 3','','','','','','','','','','','Sec 19','','','Art 3','','Art 4','Sec 6','','','Art 4','','','','Art 5
Art 6
Art 20
Art 21
Art 22');
INSERT INTO complianceforge_scf VALUES ('Privacy','Data Integrity','PRI-05.2','Mechanisms exist to confirm the accuracy and relevance of Personal Information (PI), as data is obtained and used across the information lifecycle.','','X','','X','X','','X','X',5,'','','','','','','','','9.2.1','','','','','','','','DI-2','PM-25','','','','','','','','','','','','','','','','','','','','','Principle 5','','','','','','','','','Art 7','Art 5','','','','','','','','','','','','','','','','Sec 11','Sec 11','','','','','','Art 8','','','','Sec 8','','APP Part 10','','','','','','Art 19','Sec 11','Principle 4','','','Sec 23','','Art 3','','Art 4','Sec 6','Principle 6','','Art 4','Art 6','Art 9','Art 8','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Data Masking','PRI-05.3','Mechanisms exist to mask sensitive information that is displayed or printed. ','','X','','X','X','','X','X',8,'','','','','','','','','','','','','','','','','','SI-20(4)','','','','','3.3','','','','','','','','','','','','','','','','','','','','','','','','','Art 7','Art 5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','APP Part 2','','','','','','','','','','','','','Art 3','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Usage Restrictions of Personally Identifiable Information (PII)','PRI-05.4','Mechanisms exist to restrict the use of Personal Information (PI) to only the authorized purpose(s) consistent with applicable laws, regulations and in privacy notices. ','','X','','X','X','','X','X',10,'','','','','','','','','5.2.1','','','','','','','','UL-1','PA-3(1)','','','','','','',6502,'','','','','','§ 1232g','','','','','','','','Principle 5','','','','','','','','','','Art 5
Art 9
Art 10
Art 11','','Sec 12','Art 4-7
Art 21','Art 9
Art 13','','','Art 8
Art 9','','','','Sec 9','','Sec 8','Sec 13
Sec 20','','Sec 9','Sec 9','Art 27','Art 7','Art 6
Art 10','','Sec 15
Sec 26','','Sec 13','','Art 6','Sec 10','','APP Part 3','','','','','','Art 16','Sec 34','Principle 10
Principle 12','','Sec 19
Sec 22
Sec 34','Sec 14','','Art 16
Art 18
Art 23','Art 5','Art 6
Art 7','Sec 12','','Art 10','Art 4
Art 5
Art 6
Art 7','Art 9','Art 7
Art 9','','Art 5
Art 6
Art 18
Art 19
Art 20
Art 21
Art 22');
INSERT INTO complianceforge_scf VALUES ('Privacy','Inventory of Personally Identifiable Information (PII)','PRI-05.5','Mechanisms exist to establish, maintain, and update an inventory that contains a listing of all programs and systems identified as collecting, using, maintaining, or sharing Personal Information (PI). ','','X','','X','X','','X','X',10,'','','','13.3','','','','','7.2.2','','','','','','','','SE-1','PM-29','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 3','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 33','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Personally Identifiable Information (PII) Inventory Automation Support','PRI-05.6','Automated mechanisms exist to determine if Personal Information (PI) is maintained in electronic form.','','','','X','','','X','',1,'','','','','','','','','','','','','','','','','','PM-29(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 13','Art 16','','','','Sec 29','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Right of Access','PRI-06','Mechanisms exist to provide individuals the ability to access their Personal Information (PI) maintained in organizational systems of records.','','X','','X','X','','X','X',10,'P5.1 
 P6.8 ','P5.1 
 P6.8 ','','','','','','','6.2.1
6.2.2
6.2.3
6.2.4
6.2.5
6.2.6','','','A.8','','','','','IP-2','IP-6
PM-27','','','','','','','','','','','','','§ 1232g','','','','','','','','Principle 6','','','','','','','','','','Art 12
Art 13
Art 14
Art 15','','Sec 26','Art 10
Art 12','Art 12','','Sec 24
Sec 26','Art 39','Sec 19','','Art 11
Art 12','Sec 14
Sec 15','Sec 2','Sec 13','Sec 7','','Sec 35','Sec 18','Art 32','Art 10
Art 11','Art 14','','Sec 23','Art 23
Art 24
Art 27
Art 28
Art 29','','Art 8','Art 11','Sec 17','','APP Part 12','','','Principle 6
Sec 17A
Sec 18','','','','Sec 12
Sec 30','Principle 6','','Sec 34','Sec 21','','Art 4
Art 35','Art 3','Art 14
Art 15','Sec 8','Principle 8
Principle 9','Art 12','Art 8
Art 11','Art 7','Art 15
Art 22
Art 23
Art 25','Art 10
Art 18
Art 19','Art 14');
INSERT INTO complianceforge_scf VALUES ('Privacy','Redress','PRI-06.1','Mechanisms exist to establish and implement a process for:
 ▪ Individuals to have inaccurate Personal Information (PI) maintained by the organization corrected or amended; and
 ▪ Disseminating corrections or amendments of PI to other authorized users of the PI.','','X','','X','X','','X','X',10,'P5.2 
P8.1','P5.2 
P8.1','','','','','','','6.2.5
6.2.6
10.2.1
10.2.2','','','','','','','','IP-3','IP-3','','','','','','','','','','','','','','','','','','','','','Principle 7','','','','','','','','','','Art 12
Art 16
Art 18','','Sec 27','Art 10
Art 12','Art 21','Art 24
Art 37','Sec 29','Art 38
Art 39','Sec 20','','Art 13','Sec 14
Sec 15
Sec 17','Sec 2','Sec 14','Sec 7','','Sec 36','Sec 27','Art 32','Art 12','Art 17','','Sec 24','Art 23
Art 24
Art 31
Art 32','Sec 28','Art 5','','Sec 17','','APP Part 13','','Sec 8','Sec 22','','','Art 26','Sec 34','Principle 7','','Sec 34','Sec 22','','Art 4
Art 36','Art 3','Art 16','Sec 10','Principle 10','Art 13','Art 8
Art 11','Art 7','Art 24
Art 28
Art 29','Art 20','Art 15
Art 16');
INSERT INTO complianceforge_scf VALUES ('Privacy','Notice of Correction of Amendment','PRI-06.2','Mechanisms exist to notify affected individuals if their Personal Information (PI) has been corrected or amended.','','X','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','IP-3(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 12
Art 19','','','','Art 16','Art 37','','Art 39','','','','Sec 14
Sec 15
Sec 17
Sec 18','Sec 2','','Sec 10','','Sec 38','','Art 32','','Art 18','','','Art 23
Art 24
Art 31
Art 32','','','','','','APP Part 13','','','','','','','','','','Sec 34','Sec 23','','Art 4
Art 36','','Art 16','Sec 11','','','Art 8
Art 11','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Appeal ','PRI-06.3','Mechanisms exist to provide an organization-defined process for individuals to appeal an adverse decision and have incorrect information amended.','','X','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','IP-3(2)','','','','','','','','','','','','','','','','','','','','','Principle 7','','','','','','','','','','Art 21','','Sec 28','','','Art 35','','Art 38
Art 39','','','Art 13','Sec 14
Sec 15
Sec 17
Sec 18','Sec 2','','','','Sec 40','','','','Art 17','','','Art 23
Art 24','','','','Sec 17','','','','','','','','','','','','Sec 34','','','Art 38','','','','Sec 11','','Art 15','','','Art 22','Art 16');
INSERT INTO complianceforge_scf VALUES ('Privacy','User Feedback Management','PRI-06.4','Mechanisms exist to implement a process for receiving and responding to complaints, concerns or questions from individuals about the organizational privacy practices.','','X','','X','X','','X','X',10,'P5.2 
P8.1','P5.2 
P8.1','','','','','','','6.2.5
6.2.6
7.1.2
10.2.1
10.2.2','','','','','','','','IP-4','PM-28','','','','','','','','','','','','','','','','','','','','','Principle 7','','','','','','','','','','Art 19
Art 21
Art 22','','','','Art 21','','','','','','','Sec 14
Sec 15
Sec 17','','','Sec 9','','','','','','','','','Art 26','','','','','','APP Part 13','','','','','','Art 31','','','','','','','Art 37','','','Sec 11','','','Art 12
Art 15','','Art 30','Art 10','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Right to Erasure','PRI-06.5','Mechanisms exist to erase personal data of an individual, without delay.','','X','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 17','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Data Portability','PRI-06.6','Mechanisms exist to export Personal Information (PI) in a structured, commonly used and machine-readable format that allows the data subject to transmit the data to another controller without hindrance.','','X','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 20','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Information Sharing With Third Parties','PRI-07','Mechanisms exist to discloses Personal Information (PI) to third-parties only for the purposes identified in the privacy notice and with the implicit or explicit consent of the individual. ','','X','','X','X','','X','X',10,'','','','','','','','','7.2.1
7.2.2
7.2.3','','','A.5.1','','','','','UL-2','PA-4','','','','','','','','','','','','','§ 1232g','','','','','','','','Principle 3','','','','','','','','','','Art 6
Art 26
Art 44
Art 45
Art 46
Art 47
Art 48
Art 49','','Sec 10','','Art 14
Art 27','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Sec 9','','2.2
2.3
20.1
20.2','','Sec 26','','Art 17
Art 26
Art 27','','','','Sec 20
Sec 23','','Art 26','','','','Art 17
Art 23');
INSERT INTO complianceforge_scf VALUES ('Privacy','Privacy Requirements for Contractors & Service Providers ','PRI-07.1','Mechanisms exist to includes privacy requirements in contracts and other acquisition-related documents that establish privacy roles and responsibilities for contractors and service providers. ','','X','X','X','X','','X','X',10,'','','','','','','','','4.2.3
7.2.4','','','A.7.1','','','','','AR-3','PA-4','','','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','','','','','','','','','§ 1232g','','','','','','','','Principle 3','','','','','','','','','','Art 6
Art 26
Art 27
Art 28
Art 29','','Sec 10','','Art 14
Art 27','','','','','KOS-08','','','','','','','','','','','','','','','','','','','','','','','','','','','','','2.2
2.3
20.1
20.2','','','','Art 26
Art 27','','','','Sec 20
Sec 23','','','','','','Art 17
Art 23');
INSERT INTO complianceforge_scf VALUES ('Privacy','Testing, Training & Monitoring','PRI-08','Mechanisms exist to implement a process for ensuring that organizational plans for conducting security and privacy testing, training and monitoring activities associated with organizational systems are developed and performed.
','','X','','X','','','X','X',8,'P6.5','P6.5','','','','','','','1.2.6
10.2.3
10.2.5','','','A.10.3','','','','','AR-4','PM-14','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','System of Records Notice (SORN)','PRI-09','Mechanisms exist to utilize a System of Records Notices (SORN), or similar record of processing activities, to maintain a record of processing Personal Information (PI) under the organization''s responsibility.','','X','','X','','','X','',5,'','','','','','','','','','','','','','','','','','PM-20','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 30','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Data Quality Management','PRI-10','Mechanisms exist to issue guidelines ensuring and maximizing the quality, utility, objectivity, integrity, impact determination and de-identification of Personal Information (PI) across the information lifecycle.','','X','X','X','','','X','',5,'','','','','','','','','','','','','','','','','','PM-23','','','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 7');
INSERT INTO complianceforge_scf VALUES ('Privacy','Automation','PRI-10.1','Automated mechanisms exist to support the evaluation of data quality across the information lifecycle.','','X','','X','','','X','',1,'','','','','','','','','','','','','','','','','','PM-23(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 5
Art 22','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Data Tagging','PRI-11','Mechanisms exist to issue data modeling guidelines to support tagging of Personal Information (PI).','','','','X','','','X','',3,'','','','','','','','','','','','','','','','','','PM-23(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Updating Personally Identifiable Information (PII)','PRI-12','Mechanisms exist to develop processes to identify and record the method under which Personal Information (PI) is updated and the frequency that such updates occur.','','X','','X','','','X','',9,'','','','','','','','','','','','','','','','','','PM-23(3)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Data Management Board','PRI-13','Mechanisms exist to establish  a written charter for a Data Management Board (DMB) and assigned organization-defined roles to the DMB.','- Data Management Board (DMB)','X','','X','','','X','',3,'','','','','','','','','','','','','','','','','','PM-24','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 5
Art 30','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Privacy Reporting','PRI-14','Mechanisms exist to develop, disseminate and update reports to internal senior management, as well as external oversight bodies, as appropriate, to demonstrate accountability with specific statutory and regulatory privacy program mandates.','','X','','X','','','X','',8,'','','','','','','','','10.2.3
10.2.5','','','A.5.2','','','','','AR-6','PA-4
PM-30','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 31','','','','','','','','','','','','','','','Art 3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Accounting of Disclosures','PRI-14.1','Mechanisms exist to develop and maintain an accounting of disclosures of Personal Information (PI) held by the organization and make the accounting of disclosures available to the person named in the record, upon request.','','X','','X','X','','X','X',8,'','','','','','','','','7.2.1
7.2.4','','','','','','','','AR-8','PM-22','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 30','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Principle 11','','Sec 20','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Privacy','Register Database','PRI-15','Mechanisms exist to register databases containing Personal Information (PI) with the appropriate Data Authority, when necessary.','','X','','','','','X','',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 30','','Sec 16
Sec 17','Art 17','Art 16','Art 50','Sec 36','Art 25','Sec 4d
Sec 4e','','Art 6','Sec 65
Sec 66','Sec 17','Sec 8
Sec 9','Sec 26
Sec 37','','Sec 30','Sec 33','Art 40','','Art 23','','','Art 60','Sec 36','Art 11','Art 16','Sec 19','','','','','Sec 15','','Art 5
Art 37','Art 39','Sec 14
Sec 15','','','Sec 46
Sec 47
Sec 48','Sec 39','','Art 32','','Art 21','','','','Art 25','Art 21','','Art 29','Art 6
Art 29');
INSERT INTO complianceforge_scf VALUES ('Project & Resource Management','Security Portfolio Management','PRM-01','Mechanisms exist to facilitate the implementation of security and privacy-related resource planning controls.','','X','','X','X','','X','X',10,'','','','','APO05.05 APO06.02 APO06.03 EDM05.01 APO01.01 APO02.03 APO02.04 APO02.05 APO02.06 APO03.04 APO04.02 APO04.03 APO04.04 BAI05.03 
BAI09.04 DSS06.01','Principle 1','','','','6.1
6.2','6.1.5 ','','','4.3.1
4.3.2','2.1
2.2
2.3
2.4','','PL-1','PL-1','3.2
3.2.1
3.2.2
3.2.3
3.2.4
3.2.5
3.2.6
3.3
3.3.1
3.3.2','NFO','','','','11.1','','','','','','PL-1','','','','','','','','8-101
8-311','','','5.10.1.5','','','','','','Sec 12','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','3.1.2
3.1.3
6.1.1
6.1.2
6.1.3
6.1.4
9.2.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Project & Resource Management','Information Security Resource Management','PRM-02','Mechanisms exist to address all capital planning and investment requests, including the resources needed to implement the security & privacy programs and documents all exceptions to this requirement. ','','X','','X','X','','X','X',10,'','','','','EDM02.01 EDM02.02 EDM04.01 EDM04.02 EDM04.03 APO05.02 APO05.03 APO05.04 APO05.06 APO06.01 APO06.04 APO06.05 APO04.05','Principle 2','','','','6.1
6.2','','','','4.3.1
4.3.2','2.1
2.2
2.3
2.4','','PM-3','PM-3','3.3.2
3.3.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','Sec 12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','3.1.2
3.1.3
6.1.3
9.2.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Project & Resource Management','Allocation of Resources ','PRM-03','Mechanisms exist to identify and allocate resources for management, operational, technical and privacy requirements within business process planning for projects / initiatives.','','X','','X','X','','X','X',10,'','','','','BAI05.04 APO07.01 ','','','','','7.1','','','','4.3.1
4.3.2','2.1
2.2
2.3
2.4','','SA-2','SA-2 ','3.2
3.2.1
3.2.2
3.2.3
3.2.4
3.2.5
3.2.6
3.3
3.3.1
3.3.2','NFO','ID.BE-3','','','11.1','','','','','','SA-2 ','','D1.G.SP.E.2
D1.G.Ov.Int.5
D1.G.SP.Int.3','','','','164.308(a)(7)(ii)(B)
164.308(a)(7)(ii)(C)
164.308(a)(7)(ii)(D)
164.308(a)(7)(ii)(E)
164.310(a)(2)(i)
164.316','','8-100
8-200','','','5.1
5.1.1.2
5.1.1.3
5.1.1.4
5.1.1.5
5.1.1.6
5.1.1.8','','','','','','Sec 12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','3.1.2
3.1.3
9.2.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Project & Resource Management','Security In Project Management ','PRM-04','Mechanisms exist to assess security and privacy controls in system project development to determine the extent to which the controls are implemented correctly, operating as intended and producing the desired outcome with respect to meeting the requirements.','','X','','X','X','X','X','X',10,'','','','','BAI01.01 BAI01.08 BAI01.11 BAI01.12 BAI01.13 BAI01.14','Principle 5','','','','7.1
7.2
7.3
7.4
7.5','6.1.5 ','','','4.3.1
4.3.2','2.1
2.2
2.3
2.4','','CA-2','CA-2','3.4
3.4.1
3.4.2
3.4.3
3.4.4
3.4.5
3.4.6
3.4.7
3.4.8
3.4.9
3.4.10
3.4.11
3.4.12
3.4.13
3.4.14','','','','','11.1','','','','','','','','','','','','','','8-610','','','5.11.1.1
5.11.1.2
5.11.2','','17.03(2)(h) ','','622(2)(B)(i)-(iv)','','Sec 12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.0.2
6.1.1
6.1.2
6.1.3
6.1.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Project & Resource Management','Security Requirements Definition','PRM-05','Mechanisms exist to identify critical system components and functions by performing a criticality analysis for critical systems, system components or services at pre-defined decision points in the System Development Lifecycle (SDLC). ','- System Development Lifecycle (SDLC)','X','','X','X','','X','X',10,'CC2.2','CC2.2','','','DSS06.01','Principle 10
Principle 11','','','','','14.1','','','4.3.1
4.3.2','2.1
2.2
2.3
2.4','','SA-14','RA-9
SA-14','3.4
3.4.3
3.4.4
3.4.5
3.4.6','','ID.BE-4
ID.BE-5','','','','','','','','','','','D4.C.Co.B.1
D1.G.IT.B.2
D5.IR.Pl.B.5
D5.IR.Pl.E.3','','','','164.308(a)(1)(ii)(B)
164.308(a)(6)(ii)
164.308(a)(7)
164.308(a)(7)(i)
164.308.(a)(7)(ii)(E)
164.308(a)(8)
164.310(a)(2)(i)
164.312(a)(2)(ii)
164.314(a)(1)
164.314(b)(2)(i)','','','','','','','','','','','Sec 12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','B.2.1
B.2.2
B.2.3
B.2.4
B.3.1','','Art 27','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Project & Resource Management','Business Process Definition ','PRM-06','Mechanisms exist to define business processes with consideration for cybersecurity and privacy that determines: 
 ▪ The resulting risk to organizational operations, assets, individuals and other organizations; and
 ▪ Information protection needs arising from the defined business processes and revises the processes as necessary, until an achievable set of protection needs is obtained.','','X','','X','X','','X','X',10,'','','','','DSS06.01 EDM01.01 EDM01.02 APO02.01 DSS06.06','Principle 3','','','','','','','','4.3.1
4.3.2','2.1
2.2
2.3
2.4','','PM-11','PM-11 ','3.4
3.4.1
3.4.2','','','','','','','','','','','','','','','','','','','8-303','','','','','','','','','Sec 12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Project & Resource Management','System Development Life Cycle (SDLC) Management','PRM-07','Mechanisms exist to ensure changes to systems within the System Development lifecycle (SDLC) are controlled through formal change control procedures. ','','X','','X','X','','X','X',10,'CC7.1','CC7.1','','','APO04.06 BAI01.02 BAI01.03 BAI01.04 BAI01.05 BAI01.06 BAI01.07 BAI01.09 BAI03.01 BAI03.02 BAI03.06
BAI09.03','Principle 2','','','','7.1
7.2
7.3
7.4
7.5','14.2.2 ','','','4.3.1
4.3.2
6.1
6.2
6.3
6.4
6.5','2.1
2.2
2.3
2.4','','SA-3','SA-3 ','3.2.1','NFO','PR.IP-2','','','','','','','','','SA-3 ','','D3.PC.Se.B.1
D3.PC.Se.E.1','','','','164.308(a)(1)(i)','','8-311
8-610','','','','','','','','','Sec 12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','13.1','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Risk Management Program ','RSK-01','Mechanisms exist to facilitate the implementation of risk management controls.','- Risk Management Program (RMP)','X','','X','X','X','X','X',10,'','','','','','Principle 6','','SO2','','','11.1.4 ','','4.1
4.2
4.3
4.4
4.5
4.6','4.1
4.2
4.3.1
4.3.2
5.1','2.1','2.1
2.2
2.3
2.4
2.5
2.6
2.7
2.8','PM-9
RA-1','PM-9
RA-1 ','3.3.4','NFO','ID.GV-4
ID.RM-1
ID.RM-2
ID.RM-3','','12.2','','','','','','','RA-1 ','','D1.G.Ov.B.1
D1.G.Ov.B.3
D1.G.Ov.E.1
D1.G.SP.E.1
D1.G.Ov.Int.1
D1.G.Ov.Int.3
D1.G.SP.A.4','','','6801(b)(2)','164.308(a)(1)
164.308(a)(1)(ii)(B)
164.308(a)(1)(ii)(B)
164.308(a)(6)(ii)
164.308(a)(7)(i)
164.308(a)(7)(ii)(C)
164.308(a)(7)(ii)(E)
164.308(b)
164.310(a)(2)(i)','','8-103
8-610','','Sec 404 ','','','17.03(2)(b)',500.09,'622(2)(d)(A)(ii)','','Sec 7','Art 17','Art 32','','Sec 14
Sec 15','Art 16','','','','','','OIS-06','','','','','','','','','','','','','Sec 19','','','','','','','','','','','','Art 13','','','','5.3','','','3.1.1
4.0.1
4.1.1
4.1.2
4.4.6
4.5.1
4.5.2
4.5.3
4.5.4
10.1.2
10.1.3
10.1.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Risk Framing','RSK-01.1','Mechanisms exist to identify:
 ▪ Assumptions affecting risk assessments, risk response and risk monitoring;
 ▪ Constraints affecting risk assessments, risk response and risk monitoring;
 ▪ The organizational risk tolerance; and
 ▪ Priorities and trade-offs considered by the organization for managing risk.','- Risk Management Program (RMP)','X','','X','X','','X','X',10,'','','','3.5','','Principle 6','','','','','','','5.1
5.2
5.3','4.3.3','','3.1','','PM-32','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Risk-Based Security Categorization ','RSK-02','Mechanisms exist to categorizes systems and data in accordance with applicable local, state and Federal laws that:
 ▪ Document the security categorization results (including supporting rationale) in the security plan for systems; and
 ▪ Ensure the security categorization decision is reviewed and approved by the asset owner.','- Risk Management Program (RMP)','X','','X','X','X','X','X',10,'CC2.1','CC2.1','','3.5','','Principle 6','','','','','','','5.1
5.2
5.3','','','3.1','RA-2','RA-2 ','','','','','9.6.1 ','','','','','','','RA-2 ','','','','','','','','8-402','','','','','','','','','','Art 17','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Risk Identification','RSK-03','Mechanisms exist to identify and document risks, both internal and external. ','- Risk Management Program (RMP)','X','','X','X','','X','X',10,'','','','3.5','','Principle 7','','','','','','','5.1
5.2
5.3','5.2','','3.1
3.2','','','','','ID.RA-3','','','','','','','','','','','D3.DC.An.B.1
D2.MA.Ma.E.1
D2.MA.Ma.E.4
D2.MA.Ma.Int.2','','','','164.308(a)(1)(ii)(A)
164.308(a)(1)(ii)(D)
164.308(a)(3)
164.308(a)(4)
164.308(a)(5)(ii)(A)
164.310(a)(1)
164.310(a)(2)(iii)
164.312(a)(1)
164.312(c)
164.312(e)
164.314
164.316','','','','','','','','','','','Sec 7','Art 17','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1203','','','','','','','','','','','4.2.4
4.5.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Risk Assessment ','RSK-04','Mechanisms exist to conduct an annual assessment of risk that includes the likelihood and magnitude of harm, from  unauthorized access, use, disclosure, disruption, modification or destruction of the organization''s systems and data.','- Risk Management Program (RMP)','X','','X','X','X','X','X',10,'','','','3.5','DSS06.04','Principle 7
Principle 8','BCR-05
GRM-02
GRM-10 ','SO2','1.2.4','8.2','11.1.4 ','','5.4','4.3.4
5.3.1
5.3.4
5.3.5
5.3.6
5.4
5.5
6.7','','3.2','RA-3','RA-3 ','','3.11.1','ID.RA-5','','12.2','5.1','','','','','','RA-3 ','','D1.RM.RA.B.1
D1.RM.RA.E.2
D1.RM.RA.E.1','','','Safeguards Rule ','164.308(a)(1)(ii)(A)
164.308(a)(1)(ii)(B)
164.308(a)(1)(ii)(D)
164.308(a)(7)(ii)(D)
164.308(a)(7)(ii)(E)
164.316(a)','CIP-014-2
R1','8-402','','','5.1.2
5.1.2.1','','17.03(2)(b)','','622(b)(A)(ii) ','','Sec 7
Sec 11','Art 17','Art 35','','','','','','','','','OIS-07','','','','','','','','','','','','','','','','','','','','','0009
1204
1205
1206
1207
1208','','','','','','','','','','','4.3.1
4.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Risk Register','RSK-04.1','Mechanisms exist to maintain a risk register that facilitates monitoring and reporting of risks.','- Risk Management Program (RMP)
- Risk register
- Governance, Risk and Compliance Solution (GRC) tool (ZenGRC, Archer, RSAM, Metric stream, etc.)','X','','X','X','','X','X',10,'','','','3.5','','Principle 17','','','','8.3','','','5.6
5.7','4.3.6
5.5','','3.4','','','','','','','','7.1.2','','','','','','','','','','','','','','','','','','','','','','','Sec 7','Art 17','Art 35','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','4.5.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Risk Ranking ','RSK-05','Mechanisms exist to identify and assign a risk ranking to newly discovered security vulnerabilities that is based on industry-recognized practices. ','- Risk Management Program (RMP)','X','','X','X','','X','X',10,'','','','3.5','','Principle 9','','','','8.3','','','5.5','4.3.5','','3.3','','','','','','','6.1','7.1.3','','','','','','','','','','','','','','','','','','','','','','','','Art 17','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','4.3.1
4.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Risk Remediation ','RSK-06','Mechanisms exist to remediate risks to an acceptable level. ','- Risk Management Program (RMP)','X','','X','X','','X','X',10,'','','','','','Principle 9 ','GRM-11','','','8.3
10.1','','','5.5','4.3.5','','3.3','','','','','ID.RA-6','','','7.1.1','','','','','','','','D5.IR.Pl.B.1
D5.DR.Re.E.1
D5.IR.Pl.E.1','','','','164.308(a)(1)(ii)(B)
164.314(a)(2)(i)(C)
164.314(b)(2)(iv)','','','','','','','','','','','','Art 17','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','4.4.1
4.4.4
4.4.6','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Risk Response','RSK-06.1','Mechanisms exist to respond to findings from security and privacy assessments, incidents and audits to ensure proper remediation has been performed.','- Risk Management Program (RMP)','X','','X','X','','X','X',10,'','','','','','Principle 9','','','','8.3','','','5.5','4.3.5','','3.3','','RA-7','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 17','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Risk Assessment Update','RSK-07','Mechanisms exist to routinely update risk assessments and react accordingly upon identifying new security vulnerabilities, including using outside sources for security vulnerability information. ','- Risk Management Program (RMP)','X','','X','X','X','X','X',9,'','','','','','Principle 9','','SO2','','8.2','','','5.4
5.6','4.3.4','','3.2
3.3
3.4','RA-4','RA-4','','','','','6.1','','','','','','','','','','','','Safeguards Rule ','','','','','','','','17.03(2)(i) 
17.03(2)(b)(3)','','622(2)(A)(iv)','','Sec 7','Art 17','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Business Impact Analysis (BIAs) ','RSK-08','Mechanisms exist to conduct a Business Impact Analysis (BIAs). ','- Risk Management Program (RMP)
- Data Protection Impact Assessment (DPIA)
- Business Impact Analysis (BIA)','X','','X','X','','X','X',10,'','','','','BAI01.10 BAI02.03 ','Principle 7
Principle 8','BCR-08
BCR-09 ','','','8.2','','','5.4','4.3.4
5.3.3
5.5','','3.2','','','','','ID.RA-4','','','5.1','','','','','','','','D5.RE.Re.B.1
D5.ER.Er.Ev.1','','','','164.308(a)(1)(i)
164.308(a)(1)(ii)(A)
164.308(a)(1)(ii)(B)
164.308(a)(6)
164.308(a)(7)(ii)(E)
164.308(a)(8)
164.316(a)','','','','','','','','','','','','Art 17','Art 35
Art 36','','','Art 21','','','','','','BCM-02','','','','','','','','','','','','','Sec 19','','','','','','','','','','','','','','','','','','','','Art 33','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Supply Chain Risk Management Plan','RSK-09','Mechanisms exist to develop a plan for managing supply chain risks associated with the development, acquisition, maintenance and disposal of systems, system components and services.','- Risk Management Program (RMP)','X','','X','X','','X','X',10,'','','','','','Principle 7
Principle 8','','','','','','','5.1
5.2
5.3','4.3.3','','3.1','','PM-31','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','12.7','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Supply Chain Risk Assessment','RSK-09.1','Mechanisms exist to assess supply chain risks associated with systems, system components and services.','- Risk Management Program (RMP)
- Data Protection Impact Assessment (DPIA)','X','','X','X','','X','X',10,'','','','','','Principle 7
Principle 8','','','','8.2','','','5.4','4.3.4','','3.2','','RA-3(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 17','Art 35
Art 36','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','12.7','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Risk Management','Privacy Impact Assessments (PIAs) ','RSK-10','Mechanisms exist to conduct a Privacy Impact Assessment (PIA) on systems, applications and services to evaluate privacy implications.','- Risk Management Program (RMP)
- Data Protection Impact Assessment (DPIA)
- Privacy Impact Assessment (PIA)','X','','X','X','X','X','X',10,'','','','','','Principle 7','','','1.2.4
4.2.3','8.2','','A.11.1 
A.11.2 ','5.4','4.3.4
5.3.3
5.5','','3.2','AR-2
PL-5','RA-8','','','','','','5.1',6502,'','','','','','','','','','','','','','','','','','','','','','','Art 17','Art 35
Art 36','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 33','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Secure Engineering Principles ','SEA-01','Mechanisms exist to facilitate the implementation of industry-recognized security and privacy practices in the specification, design, development, implementation and modification of systems and services.','','X','X','X','X','','X','X',10,'CC3.2 ','CC3.2 ','','','DSS06.06','Principle 10
Principle 11','','SO12','4.2.3
6.2.2
7.2.2
7.2.3','','14.2.5 ','A.10.1
A.10.4
A.10.5
A.10.6','','','2.4
3.1
3.2','2.1
2.2
2.3
2.4
2.5
2.6
2.7
2.8','AR-7
SA-8
SA-13
SC-7(18)
SI-1','SA-8
SC-7(18)
SI-1','2.1
2.2
2.3
2.4','3.13.1
3.13.2
NFO','','A5
A6','2.2','','','','','','§ 11.30','SA-8
SC-7(18)
SI-01','','','','§45(a)
§45b(d)(1)','','','','8-101
8-302
8-311','Principle 4','','5.10.1.1
5.10.1.5','','','','','Sec. 521.052','','Art 10
Art 14','Art 5
Art 24
Art 25
Art 32
Art 40','','Sec 14
Sec 15','Art 16','Art 13
Art 27','Art 27
Art 41 ','Sec 5
Sec 32
Sec 33
Sec 34
Sec 35','Art 34','Sec 4b
Sec 9
Sec 9a
Sec 16
Annex','KOS-01
KOS-07','Art 9','Sec 7
Sec 8','Sec 2','Sec 16
Sec 17','Sec 31
Sec 33
Sec 34
Sec 35
Sec 42','Art 3
Art 4','Sec 12
Sec 13
Sec 14','Sec 13
Sec 14
Sec 29','Art 1
Art 36
Art 47','Art 14
Art 15
Art 16
Art 17
Art 18
Art 19','Art 7
Art 12
Art 19','','Sec 19
Sec 21','','Sec 31
Sec 33','Art 6
Art 7','Art 8
Art 12','Sec 11
Sec 15
Sec 16','','APP Part 8
APP Part 11','1406
1407
1408
1409
1467
0383
0380
1469
1410
0382
1345
1411
1412
1470','Sec 4','Principle 4
Sec 33','Sec 7
Sec 8','','Art 20
Art 23','Sec 9','Principle 4','10.1-6
15.2','Sec 25
Sec 29','Sec 24
Sec 26','5.1.3
6.2.1
13.1.1
13.1.2
13.1.3
13.1.4
13.1.5
13.1.6
13.1.7
13.1.8
13.1.9
13.2.2
13.2.3
B.2.1
B.2.2
B.2.3
B.2.4
B.3.1
E.2.1
E.2.2
E.2.3
E.2.4
E.2.5
E.2.6
E.2.7','Art 3
Art 29','Art 21','Art 9
Art 12','Sec 6
Sec 12','Principle 7','Art 7','Art 4
Art 26','Art 10
Art 14','Art 19
Art 36
Art 37','Art 9
Art 11
Art 15
Art 16
Art 17','Art 5
Art 10');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Centralized Management of Cybersecurity & Privacy Controls','SEA-01.1','Mechanisms exist to centrally-manage the organization-wide management and implementation of cybersecurity and privacy controls and related processes.','','X','X','X','X','','X','X',10,'','','','','','Principle 10
Principle 11','','','','','','','','','3.1
3.2','','PL-9','PL-9','3.4
3.4.3
3.4.4
3.4.5
3.4.6
3.4.7
3.4.8
3.4.9
3.4.10
3.4.11
3.4.12
3.4.13
3.4.14','','','A5
A6','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 5
Art 24
Art 25
Art 32
Art 40','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 5
Art 10');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Alignment With Enterprise Architecture ','SEA-02','Mechanisms exist to develop an enterprise architecture, aligned with industry-recognized leading practices, with consideration for cybersecurity and privacy principles that addresses risk to organizational operations, assets, individuals, other organizations. ','','X','','X','X','','X','X',10,'','','','','APO01.05 APO05.01 APO02.02 APO03.01 APO03.02 APO03.03 APO03.05','','','','','','14.1.1 ','','','','','2.7
2.8','PL-8
PM-7','PL-8
PM-7 ','3.4
3.4.1
3.4.2
3.4.3
3.4.4
3.4.5
3.4.6','NFO','','','2.2','','','','','','','PL-8','','','','','','','','8-103','','','5.10.1.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','B.2.1
B.2.2
B.2.3
B.2.4
B.3.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Standardized Terminology','SEA-02.1','Mechanisms exist to standardize technology and process terminology to reduce confusion amongst groups and departments. ','','X','','X','X','','X','X',3,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Defense-In-Depth (DiD) Architecture','SEA-03','Mechanisms exist to implement security functions as a layered structure minimizing interactions between layers of the design and avoiding any dependence by lower layers on the functionality or correctness of higher layers. ','','X','X','X','X','','','X',10,'','','','','','','','','','','','','','','','','PL-8(1)
SC-3(5)','PL-8(1)
SC-3(5)','3.4
3.4.1
3.4.2
3.4.3
3.4.4
3.4.5
3.4.6','','','A5
A6','1.3.7','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','System Partitioning ','SEA-03.1','Mechanisms exist to partition systems so that partitions reside in separate physical domains or environments. ','','X','','X','','','','',8,'','','2.4','2.4','','','','','','','','','','','','','SC-32','SC-32 ','','','','','','','','','','','','','','','','','','','','','','','5.10.1.5
5.10.3.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Application Partitioning','SEA-03.2','Mechanisms exist to separate user functionality (including user interface services) from system management functionality. ','- Separate interface for non-privileged users.','X','X','X','','','','',8,'','','','','','','','','','','','','','','','','SC-2
SC-2(1)','SC-2
SC-2(1)','','3.13.3','','A5
A6','11.3.4','','','','','','','SC-2 ','','','','','','','','','','','5.10.1.5
5.10.3
5.10.3.1
5.10.3.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Process Isolation ','SEA-04','Mechanisms exist to implement a separate execution domain for each executing process. ','','X','X','X','','','','',7,'','','','','','','','','','','','','','','','','SC-39','SC-39 ','','NFO','','A5
A6','','','','','','','','SC-39 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Security Function Isolation ','SEA-04.1','Mechanisms exist to isolate security functions from non-security functions. ','','X','X','X','','','','',7,'','','','','','','','','','','','','','','','','SC-3','SC-3 ','','','','A5
A6','1.2
1.3.1
2.2.1
11.3.4
11.3.4.1','','','','','','','','','','','','','','','8-105','','','5.10.1.5
5.10.3.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Hardware Separation','SEA-04.2','Mechanisms exist to implement underlying hardware separation mechanisms to facilitate process separation. ','','X','X','X','','','','',7,'','','','','','','','','','','','','','','','','SC-39(1)','SC-39(1)','','','','A5
A6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Thread Isolation','SEA-04.3','Mechanisms exist to maintain a separate execution domain for each thread in multi-threaded processing. ','','X','X','X','','','','',7,'','','','','','','','','','','','','','','','','SC-39(2)','SC-39(2)','','','','A5
A6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Information In Shared Resources ','SEA-05','Mechanisms exist to prevent unauthorized and unintended information transfer via shared system resources. ','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','SC-4','SC-4 ','','3.13.4','','A5
A6','','','','','','','','SC-4 ','','','','','','','','8-609','','','5.10.1.5
5.10.3
5.10.3.1
5.10.3.2','','','','','','','','','','','','','','','','','RB-23
KOS-05','','','','','','','','','','','','','','','','','','','','','','','','','','','','','20.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Prevent Program Execution','SEA-06','Automated mechanisms exist to prevent the execution of unauthorized software programs. ','','X','X','X','','','','',10,'','','','','','','','','','','','','','','','','CM-7(2)','CM-7(2)','','','','A5
A6','','','','','','','','','','','','','','','','','','','5.7.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Predictable Failure Analysis ','SEA-07','Mechanisms exist to determine the Mean Time to Failure (MTTF) for system components in specific environments of operation.','- Mean Time to Failure (MTTF)','','','X','','','','',5,'','','','','','','','','','','','','','','','','SI-13','SI-13','','','','','','','','','','','','','','','','','','','','','','','','','','','622(2)(d)(C)(iii)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Technology Lifecycle Management','SEA-07.1','Mechanisms exist to manage the usable lifecycles of systems. ','- Computer Lifecycle Program (CLP)
- Technology Asset Management (TAM)','','','X','X','','X','X',7,'','','','','','','','','','','','','','','','','SA-3','SA-3','','NFO','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','13.1','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Fail Secure','SEA-07.2','Mechanisms exist to enable systems to fail to an organization-defined known-state for types of failures, preserving system state information in failure. ','','X','X','X','','','','',8,'','','','','','','','','','','','','','','','','CP-12
SC-24','CP-12
SC-24 ','','','PR.PT-5','A5
A6','','','','','','','','','','','','','','','','8-615
8-702','','','5.10.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Fail Safe','SEA-07.3','Mechanisms exist to implement fail-safe procedures when failure conditions occur. ','','','','X','','','','',8,'','','','','','','','','','','','','','','','','SI-17','SI-17 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Non-Persistence ','SEA-08','Mechanisms exist to implement non-persistent system components and services that are initiated in a known state and terminated upon the end of the session of use or periodically at an organization-defined frequency. ','','X','X','X','','','','',9,'','','','','','','','','','','','','','','','','SI-14','SI-14','','','','A5
A6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Information Output Filtering ','SEA-09','Mechanisms exist to validate information output from software programs and/or applications to ensure that the information is consistent with the expected content. ','','X','','X','','','','',8,'','','','','','','AIS-03','','','','','','','','','','SI-15','SI-15','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Limit Personally Identifiable Information (PII) Dissemination','SEA-09.1','Mechanisms exist to limit the dissemination of Personal Information (PI) to organization-defined elements identified in the Data Protection Impact Assessment (DPIA) and consistent with authorized purposes.','- Data Protection Impact Assessment (DPIA)','','','X','','','X','',10,'','','','','','','','','','','','','','','','','','SI-15(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Memory Protection ','SEA-10','Mechanisms exist to implement security safeguards to protect system memory from unauthorized code execution. ','- Puppet
- Chef','','','X','','','','',10,'','','','','','','','','','','','','','','','','SI-16','SI-16 ','','NFO','','','','','','','','','','SI-16 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Honeypots ','SEA-11','Mechanisms exist to utilize honeypots that are specifically designed to be the target of malicious attacks for the purpose of detecting, deflecting, and analyzing such attacks. ','','','','X','','','','',3,'','','','','','','','','','','','','','','','','SC-26','SC-26','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Honeyclients ','SEA-12','Mechanisms exist to utilize honeyclients that proactively seek to identify malicious websites and/or web-based malicious code. ','','','','X','','','','',3,'','','','','','','','','','','','','','','','','SC-35','SC-35','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Heterogeneity ','SEA-13','Mechanisms exist to utilize a diverse set of technologies for system components to reduce the impact of technical vulnerabilities from the same Original Equipment Manufacturer (OEM). ','','','','X','','','','',3,'','','','','','','','','','','','','','','','','SC-29','SC-29','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Virtualization Techniques ','SEA-13.1','Mechanisms exist to utilize virtualization techniques to support the employment of a diversity of operating systems and applications.','','','','X','','','','',6,'','','','','','','','','','','','','','','','','SC-29(1)','SC-29(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','22.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Concealment & Misdirection ','SEA-14','Mechanisms exist to utilize concealment and misdirection techniques for systems to confuse and mislead adversaries. ','','','','X','','','','',2,'','','','','','','','','','','','','','','','','SC-30','SC-30','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Distributed Processing & Storage ','SEA-15','Mechanisms exist to distribute processing and storage across multiple physical locations. ','','X','','X','','','X','',4,'','','','','','','','','','','','','','','','','SC-36','SC-36','','','','','','','','','','','','','','','','','','','','','','','5.10.1.5','','','','','','','','Art 6
Art 26
Art 27
Art 28
Art 29
Art 44
Art 45
Art 46
Art 47
Art 48
Art 49','','Sec 10','Chapter 4 - Art 16','Art 14
Art 16
Art 27','Art 41 ','','Art 34','','','','Sec 7','Sec 2','Sec 16
Sec 17','Sec 31','Art 3
Art 4','Sec 12
Sec 13
Sec 14','Sec 13
Sec 14','Art 1
Art 36','Art 14
Art 15','Art 7','','Sec 19
Sec 21','','Sec 31','','','','','','','','','','Art 1','Art 20','Sec 9','','2.2
2.3
20.1
20.2','Sec 25','Sec 24
Sec 26','','Art 17
Art 27','','Art 9
Art 26','','Sec 20','Art 7','Art 26','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Non-Modifiable Executable Programs ','SEA-16','Mechanisms exist to utilize non-modifiable executable programs that load and execute the operating environment and applications from hardware-enforced, read-only media.','','','','X','','','','',1,'','','','','','','','','','','','','','','','','SC-34','SC-34','','','','','','','','','','','','','','','','','','','','8-302
8-304
8-311','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Secure Log-On Procedures ','SEA-17','Mechanisms exist to utilize a trusted communications path between the user and the security functions of the system.','- Active Directory (AD) Ctrl+Alt+Del login process','X','','X','X','','X','X',10,'','','','','','','','','','','9.4.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','System Use Notification (Logon Banner)','SEA-18','Mechanisms exist to utilize system use notification / logon banners that display an approved system use notification message or banner before granting access to the system that provides privacy and security notices.','- Logon banner
- System use notifications','','','X','X','','X','X',9,'','','','','','','','','','','','','','','','','AC-8','AC-8 ','','3.1.9','','','','','','','','','','AC-8 ','','','','','','','','8-609','','','5.5.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0408
0979
0980','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Standardized Microsoft Windows Banner','SEA-18.1','Mechanisms exist to utilize displays a system use notification / logon banner for Active Directory (AD) users on Microsoft Windows devices before granting access to the system that provides privacy and security notices.','- Active Directory (AD) Ctrl+Alt+Del login process
','','','X','X','','X','X',9,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0408
0979
0980','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Truncated Banner','SEA-18.2','Mechanisms exist to utilize a truncated system use notification / logon banner on systems not capable of displaying a logon banner from a centralized source, such as Active Directory.','- Logon banner
- System use notifications','','','X','X','','X','X',9,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0408
0979
0980','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Previous Logon Notification','SEA-19','Mechanisms exist to configure systems that process, store or transmit sensitive data to notify the user, upon successful logon, of the number of unsuccessful logon  attempts since the last successful logon.','- Network Time Protocol (NTP)','','','X','','','','',3,'','','','','','','','','','','','','','','','','AC-9','AC-9','','','','','','','','','','','','','','','','','','','','8-609','','','5.4.1
5.4.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Secure Engineering & Architecture ','Clock Synchronization','SEA-20','Mechanisms exist to utilize time-synchronization technology to synchronize all critical system clocks. ','- Network Time Protocol (NTP)','X','X','X','X','','X','X',10,'','','','','','','','','','','12.4.4 ','','','','','','AU-8','AU-8','','','','','10.4-10.4.3','','','','','','','','','','','','','','','','','','5.4.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Operations','Operations Security ','OPS-01','Mechanisms exist to facilitate the implementation of operational security controls.','- Standardized Operating Procedures (SOP)
- ITIL v4 
- COBIT 5','X','','X','X','','X','X',10,'','','','','DSS01.01 DSS03.05','','','SO13','','8.1','12.1.1','','','','','','SC-38','SC-38 ','3.4.12','','','','','','','252.204-7008
252.204-7012','','','§ 11.10','','','','','','','','CIP-003-6
R4','','','','5.10.1.5','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','5.5','','','D.2.1
D.2.2
D.2.3
D.3.2
D.4.1
D.4.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Operations','Standardized Operating Procedures (SOP)','OPS-01.1','Standardized Operating Procedures (SOP) or similar mechanisms, are used to identify and document day-to-day procedures to enable the proper execution of assigned tasks.','- Standardized Operating Procedures (SOP)','X','','X','X','','X','X',10,'','','','','DSS01.01 DSS03.05','','','','','7.5','12.1.1','','','','','','','','3.4.12','','','','','','','252.204-7008
252.204-7012','','','§ 11.10','','','','','','','','CIP-003-6
R4','','','','','','','','','','','','','','','','','','','','','SA-01','','','','','','','','','','','','','','','','','','','','','0051
0789
0790
0055
0056
0057','','','','','','','','5.5','','','D.2.1
D.2.2
D.2.3
D.3.2
D.4.1
D.4.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Operations','Security Concept Of Operations (CONOPS) ','OPS-02','Mechanisms exist to develop a security Concept of Operations (CONOPS) that documents management, operational and technical measures implemented to apply defense-in-depth techniques.','','X','','X','','','','X',9,'','','','','','','IVS-13 ','SO13','','8.1','12.1.1 ','','','','','','PL-7','PL-7 ','3.4.12','','','','','','','','','','','','','','','','','','','8-610','','','5.10.1.5','','',500.10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','D.2.1
D.2.2
D.2.3
D.3.2
D.4.1
D.4.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Operations','Service Delivery ','OPS-03','Mechanisms exist to define supporting business processes and implement appropriate governance and service management to ensure appropriate planning, delivery and support of the organization''s technology capabilities supporting business functions, workforce, and/or customers based on industry-recognized standards. ','- ITIL v4 
- COBIT 5','X','','','','','','X',7,'','','','','DSS01.02 DSS02.03 DSS02.06 DSS02.07 DSS03.05','','BCR-10','','','','','','','','','','','','3.4.12','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','F.2.1
F.2.2
F.2.3
F.2.4
F.2.5
F.2.6
F.3.1
F.3.2
F.3.3
F.3.4
F.3.5','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Awareness & Training ','Security & Privacy-Minded Workforce ','SAT-01','Mechanisms exist to facilitate the implementation of security workforce development and awareness controls. ','','X','','X','X','','X','X',10,'','','','','BAI08.04 BAI08.05 ','','HRS-09 ','SO6','','','7.2.2 ','','','','','2.7','AT-1
PM-13','AT-1 
PM-13','','NFO','PR.AT-1
PR.AT-3
PR.AT-4','','','','','','','','','AT-1','','D1.TC.Tr.B.2
D1.TC.Tr.B.4
D1.TC.Tr.Int.2
D1.TC.Tr.E.2','','','','164.308(a)(2)
164.308(a)(3)(i)
164.308(a)(5)
164.308(a)(5)(i)
164.308(a)(5)(ii)(A)
164.308(a)(5)(ii)(B)
164.308(a)(5)(ii)(C)
164.308(a)(5)(ii)(D)
164.308(b)
164.314(a)(1)
164.314(a)(2)(i)
164.314(a)(2)(ii)','CIP-004-6
R1','8-101
8-103
8-307','','','5.2.1','','',500.14,'','','Sec 6','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','HR-03','','','','','','','','','','','','','','','','','','','','','0252','','','','','','','','9.1','','','3.4.1
3.4.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Awareness & Training ','Security & Privacy Awareness ','SAT-02','Mechanisms exist to provide all employees and contractors appropriate awareness education and training that is relevant for their job function. ','','X','','X','X','X','X','X',10,'','','17.3','','','','MOS-01 ','SO7','','','7.2.2','','','','','','AT-2','AT-2','','3.2.1
3.2.2','','','12.6','','','','','','','AT-2 ','','','','','','164.308(a)(5)(i) 
164.308(a)(5)(ii)(A)','','8-101','','','5.2.1.1','','17.04(8) 
17.03(2)(b)(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0251','','','','','','','','9.1','','','3.4.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Awareness & Training ','Practical Exercises ','SAT-02.1','Mechanisms exist to simulate actual cyber-attacks through practical exercises.','','X','','X','','','','',3,'','','17.4
17.5 ','','','','','SO6','','','','','','','','','AT-2(1)','AT-2(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Awareness & Training ','Social Engineering & Mining','SAT-02.2','Mechanisms exist to include awareness training on recognizing and reporting potential and actual instances of social engineering and social mining.','','','','X','','','','',5,'','','','','','','','','','','','','','','','','','AT-2(3)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Awareness & Training ','Security & Privacy Training ','SAT-03','Mechanisms exist to provide role-based security-related training: 
 ▪ Before authorizing access to the system or performing assigned duties; 
 ▪ When required by system changes; and 
 ▪ Annually thereafter.','','X','','X','X','X','X','X',10,'','','17.2','','','','','SO6','','','','','','','','','AT-3','AT-3','','3.2.1
3.2.2','PR.AT-2
PR.AT-5','','12.6.1 ','','','','','','','AT-3 ','','D1.TC.Tr.E.3
D1.R.St.E.3','','','','164.308(a)(2)
164.308(a)(3)(i)
164.308(a)(5)(i)
164.308(a)(5)(ii)(A)
164.308(a)(5)(ii)(B)
164.308(a)(5)(ii)(C)
164.308(a)(5)(ii)(D)
164.530(b)(1)','CIP-004-6
R2','8-101
8-103
8-104','','','5.2.1.1
5.2.1.2
5.2.1.3
5.2.1.4','','17.04(8)','','622(2)(d)(A)(iv','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0253
0922
0255
0256','','','','','','','','9.1','','','3.4.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Awareness & Training ','Practical Exercises ','SAT-03.1','Mechanisms exist to include practical exercises in security and privacy training that reinforce training objectives.','','','','X','','','','',3,'','','','','','','','','','','','','','','','','','AT-3(3)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Awareness & Training ','Suspicious Communications & Anomalous System Behavior','SAT-03.2','Mechanisms exist to provide training to personnel on organization-defined indicators of malware to recognize suspicious communications and anomalous behavior.','','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','AT-3(4)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Awareness & Training ','Sensitive Information Storage, Handling & Processing','SAT-03.3','Mechanisms exist to ensure that every user accessing a system processing, storing or transmitting sensitive information is formally trained in data handling requirements.','','X','','','X','','X','X',10,'','','','','','','','','1.1.1
1.2.10','','','','','','','','AR-5','AT-3(5)','','','','','1.5
2.5
3.7
4.3
5.4
6.7
7.3
8.8
9.10
10.9
11.6
12.6-12.6.2
12.8.3
12.8.5
12.10.4','','','','','','','','','','','','','','CIP-004-6
R2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Awareness & Training ','Vendor Security & Privacy Training','SAT-03.4','Mechanisms exist to incorporate vendor-specific security training in support of new technology initiatives. ','','','','X','X','','','X',7,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','CIP-004-6
R2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Awareness & Training ','Privileged Users','SAT-03.5','Mechanisms exist to provides specific training for privileged users to ensure privileged users understand their unique roles and responsibilities ','','X','','X','X','','','X',10,'','','','','','','','','','','','','','','','','','','','','PR.AT-2
PR.AT-5','','','','','','','','§ 11.10','','','D1.TC.Tr.E.3
D1.R.St.E.3','','','','','CIP-004-6
R2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Security Awareness & Training ','Training Records ','SAT-04','Mechanisms exist to document, retain and monitor individual training activities, including basic security awareness training, ongoing awareness training and specific-system training.','','','','X','X','','X','X',9,'','','','','','','','','','','','','','','','','AT-4','AT-4','','NFO','','','12.6.2','','','','','','','AT-4 ','','','','','','','','8-103
8-104','','','5.2.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Technology Development & Acquisition','TDA-01','Mechanisms exist to facilitate the implementation of tailored development and acquisition strategies, contract tools and procurement methods to meet unique business needs.','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','3.1
3.1.1
3.1.2','','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','','11.1
11.2
11.3
11.4
11.5
11.6
11.7
11.8','','','','','','','','','','','','','','','','','','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','BEI-01','','','','','','','','','','','','','','','','','','','','','0279
0280
0282
0463
0464
0283
1342
1343
0285
0286
0937
0284
0287
0938','','','','','','','','12.1
14.4-5','','','6.2.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Product Management','TDA-01.1','Mechanisms exist to design and implement processes to update product software to correct security deficiencies.','','X','X','','','','','',10,'','','','','','','','','','','','','','','','','','','','','','','','11.5
11.6
11.7
11.8','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0938','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Integrity Mechanisms for Software / Firmware Updates ','TDA-01.2','Mechanisms exist to utilize integrity validation mechanisms for security updates.','- Checksum comparison','X','X','','','','','',5,'','','','','','','','','','','','','','','','','','','','','','','','11.6
11.7
11.8','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Malware Testing Prior to Release ','TDA-01.3','Mechanisms exist to utilize at least one (1) malware detection tool to identify if any known malware exists in the final binaries of the product or security update.','','X','X','','','','','',9,'','','','','','','','','','','','','','','','','','','','','','','','14.1
14.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Security Requirements ','TDA-02','Mechanisms exist to include technical and functional specifications, explicitly or by reference, in system acquisitions based on an assessment of risk.','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','SA-4','SA-4 ','','NFO','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','','4.1
5.1
8.1
8.2
8.3
8.4
8.5
8.6
8.7
8.8
8.9
12.1
12.2
12.3
12.4
12.5
12.6
12.7
12.8','','','','','','SA-4 ','','','','','','','','8-302
8-613','','','5.1
5.1.1.2
5.1.1.3
5.1.1.4
5.1.1.5
5.1.1.6
5.1.1.8','','','','','','','','','','','','','','','','','BEI-02','','','','','','','','','','','','','','','','','','','','','0463
0464
0283
1342','','','','','','','','14.4-5','','','6.2.1
6.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Ports, Protocols &Services In Use','TDA-02.1','Mechanisms exist to require the developers of systems, system components or services to identify early in the System Development lifecycle (SDLC), the functions, ports, protocols and services intended for use. ','- Ports, Protocols & Services (PPS)','X','X','X','X','','','X',10,'','','','9.5','','','','','','','','','','','','','SA-4(9)','SA-4(9) ','','NFO','','','','4.1','','','','','','SA-4(9) ','','','','','','','','','','','5.7.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Use of Approved PIV Products','TDA-02.2','Mechanisms exist to utilize only information technology products on the Federal Information Processing Standards (FIPS) 201-approved products list for Personal Identity Verification (PIV) capability implemented within organizational systems. ','- FIPS 201','','','X','','','','',2,'','','','','','','','','','','','','','','','','SA-4(10)
IA-5(11)','SA-4(10)','','NFO','','','','','','','','','','SA-4(10)
IA-5(11) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Commercial Off-The-Shelf (COTS) Security Solutions ','TDA-03','Mechanisms exist to utilize only Commercial Off-the-Shelf (COTS) security products. ','','','','X','X','','X','X',5,'','','','','','','','','','','','','','','','','SA-4(6)','SA-4(6)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Supplier Diversity','TDA-03.1','Mechanisms exist to obtain security and privacy technologies from different suppliers to minimize supply chain risk.','- Supplier diversity','','','X','X','','','X',3,'','','','','','','','','','','','','','','','','PL-8(2)','PL-8(2)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Documentation Requirements','TDA-04','Mechanisms exist to obtain, protect and distribute administrator documentation for systems that describe:
 ▪ Secure configuration, installation and operation of the system;
 ▪ Effective use and maintenance of security features/functions; and
 ▪ Known vulnerabilities regarding configuration and use of administrative (e.g., privileged) functions.','','X','X','X','X','','','X',10,'','','','','','','','','','','','','','','','','SA-5','SA-5 ','','NFO','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','','4.1
5.1
6.1
6.2
6.3
6.4
6.5
6.6
6.7
6.8
6.9','','','','','','SA-5 ','','','','','','','','8-202
8-320
8-610','','','5.7.2','','','','','','','','','','','','','','','','','BEI-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Functional Properties ','TDA-04.1','Mechanisms exist to require vendors/contractors to provide information describing the functional properties of the security controls to be utilized within systems, system components or services in sufficient detail to permit analysis and testing of the controls. ','- SSAE-16 SOC2 report','X','X','X','X','','','X',10,'','','','','','','','','','','','','','','','','SA-4(1)
SA-4(2)','SA-4(1)
SA-4(2) ','','NFO','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','','4.1
5.1
6.1
6.2
6.3
6.4
6.5
6.6
6.7
6.8
6.9','','','','','','SA-4(1)
SA-4(2) ','','','','','','','','','','','5.1
5.1.1.2
5.1.1.3
5.1.1.4
5.1.1.5
5.1.1.6
5.1.1.8','','','','','','','','','','','','','','','','','BEI-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Developer Architecture & Design ','TDA-05','Mechanisms exist to require the developers of systems, system components or services to produce a design specification and security architecture that: 
 ▪ Is consistent with and supportive of the organization’s security architecture which is established within and is an integrated part of the organization’s enterprise architecture;
 ▪ Accurately and completely describes the required security functionality and the allocation of security controls among physical and logical components; and
 ▪ Expresses how individual security functions, mechanisms and services work together to provide required security capabilities and a unified approach to protection.','','X','','X','X','','','X',10,'','','','','','','CCC-02','','','','','','','','','','SA-17','SA-17 ','','','','','','5.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','BEI-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','14.4-5','','','6.3.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Secure Coding ','TDA-06','Mechanisms exist to develop applications based on secure coding principles to develop applications. ','- OWASP','X','X','X','X','','X','X',10,'','','18.5
18.8
18.9','','','','','','','','14.2.1
14.2.5 ','','','','','','SA-1
SA-15','SA-1 
SA-4(3)
SA-15','','NFO','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','6.3-6.3.2
6.5-6.5.10 ','4.1
5.1','','','','','','SA-1 ','','','','','','','','','','','','','',500.08,'','','','','','','','','','','','','','BEI-02','','','','','','','','','','','','','','','','','','','','','0400
1419
1420
1421
1422
1238
0401
1423
0402
1239
1240
1241
1424
0971','','','','','','','','14.4-5','','','6.2.5
6.3.3
6.4.3
6.4.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Criticality Analysis','TDA-06.1','Mechanisms exist to require the developer of the system, system component or service to perform a criticality analysis at organization-defined decision points in the System Development Lifecycle (SDLC).','- System Development Lifecycle (SDLC)','X','','X','','','','',10,'','','','','','','','','','','','','','5.3.3','3.1','','','SA-15(3)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1458','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Secure Development Environments ','TDA-07','Mechanisms exist to maintain a segmented development network to ensure a secure development environment. ','','X','','X','X','','','X',10,'','','','','','','','','','','14.2.6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','BEI-02
BEI-11','','','','','','','','','','','','','','','','','','','','','1273
1274','','','','','','','','','','','7.2.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Separation of Development, Testing and Operational Environments ','TDA-08','Mechanisms exist to manage separate development, testing, and operational environments to reduce the risks of unauthorized access or changes to the operational environment and to ensure no impact to production systems.','','X','','X','X','','X','X',10,'','','18.6','','','','IVS-08','','','','12.1.4 ','','','','','','CM-4(1)','CM-4(1)','','','PR.DS-7','','6.4.1 ','','','','','','','','','D3.PC.Am.B.10','','','','164.308(a)(4)','','','','','5.10.4.1
5.13.4.1','','','','','','','','','','','','','','','','','BEI-11','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.2.5
7.2.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Security & Privacy Testing Throughout Development ','TDA-09','Mechanisms exist to require system developers/integrators consult with cybersecurity and privacy personnel to: 
 ▪ Create and implement a Security Test and Evaluation (ST&E) plan;
 ▪ Implement a verifiable flaw remediation process to correct weaknesses and deficiencies identified during the security testing and evaluation process; and
 ▪ Document the results of the security testing/evaluation and flaw remediation processes.','- Security Test & Evaluation (ST&E)','X','X','X','X','','X','X',10,'','','18.3','','','','CCC-03','','','','14.2.7
14.2.8 ','','','','','','SA-11','SA-11 ','','NFO','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','6.4
6.4.4 ','5.1
6.10
12.1
12.2
12.3
12.4
13.1
13.2
15.1
15.2
15.3
15.4
15.5
15.6
15.7
15.8
15.9
15.10
15.11','','','','','','SA-11 ','','','','','','','','8-302','','','5.10.4.1
5.13.4.1','',' 17.03(2)(d)(B)(i) ','','','','','','','','','','','','','','','BEI-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.2.3
6.2.4
6.3.4
6.4.2
6.4.3
6.4.4
A.1.1
A.1.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Continuous Monitoring Plan','TDA-09.1','Mechanisms exist to require the developers systems, system components or services to produce a plan for the continuous monitoring of security & privacy control effectiveness. ','','X','X','X','','','','',9,'','','','','','','','','','','','','','','','','SA-4(8)','SA-4(8) ','','','','','','4.1
5.1
6.1
6.2
6.3
6.4
6.5
6.6
6.7
6.8
6.9','','','','','','SA-4(8) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Static Code Analysis','TDA-09.2','Mechanisms exist to require the developers of systems, system components or services to employ static code analysis tools to identify and remediate common flaws and document the results of the analysis. ','','X','X','X','X','','','X',9,'','','','','','','','','','','','','','','','','SA-11(1)','SA-11(1) ','','','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','6.3-6.3.2 ','5.1
6.10
13.1
13.2
17.1
17.2
18.1
18.2
19.1
19.2','','','','','','SA-11(1) ','','','','','','','','','','','5.10.4.1
5.13.4.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.2.3
6.2.4
6.3.4
6.4.2
6.4.3
6.4.4
A.1.1
A.1.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Dynamic Code Analysis ','TDA-09.3','Mechanisms exist to require the developers of systems, system components or services to employ dynamic code analysis tools to identify and remediate common flaws and document the results of the analysis. ','','X','X','X','X','','','X',10,'','','18.4','','','','','','','','','','','','','','SA-11(8)','SA-11(8) ','','','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','','5.1
6.10
13.1
13.2
17.1
17.2','','','','','','SA-11(8) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.2.3
6.2.4
6.3.4
6.4.2
6.4.3
6.4.4
A.1.1
A.1.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Malformed Input Testing','TDA-09.4','Mechanisms exist to utilize testing methods to ensure systems, services and products continue to operate as intended when subject to invalid or unexpected inputs on its interfaces.','','X','X','','','','','',7,'','','','','','','','','','','','','','','','','','','','','','A1','','15.1
15.2
15.3
15.4
15.5
15.6
15.7
15.8
15.9
15.10
15.11','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Application Penetration Testing','TDA-09.5','Mechanisms exist to perform application-level penetration testing of custom-made applications and services.','','X','X','','','','','',9,'','','','','','','','','','','','','','','','','','','','','','A1
A2
A3
A4
A5
A6
A7
A8
A9
A10','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Use of Live Data ','TDA-10','Mechanisms exist to approve, document and control the use of live data in development and test environments.','','X','','X','','','X','',10,'','','','','','','DSI-05','','','','14.3.1 ','','','','','','SA-15(9)','SA-3(2)
SA-15(9)','','','','','6.4
6.4.3 ','','','','','','','','','','','','','','','','','','','','17.03(2)(d)(B)(i) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Test Data Integrity','TDA-10.1','Mechanisms exist to ensure the integrity of test data through existing security & privacy controls.','','','','','','','','',8,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Component Authenticity ','TDA-11','Mechanisms exist to govern component authenticity by developing and implementing anti-counterfeit procedures that include the means to detect and prevent counterfeit components.','','','X','X','X','','X','X',9,'','','','','','','','','','','','','','','','','SA-12(10)
SA-19','SA-12(10)
SA-19','','','','','','','','','','','','','','','','','','','','8-302','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Anti-Counterfeit Training','TDA-11.1','Mechanisms exist to train personnel to detect counterfeit system components, including hardware, software and firmware. ','','','','X','X','','','X',6,'','','','','','','','','','','','','','','','','SA-19(1)','SA-19(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Component Disposal','TDA-11.2','Mechanisms exist to dispose of system components using organization-defined techniques and methods to prevent such components from entering the gray market.','- Shred-it
- IronMountain','X','X','X','X','','','X',9,'','','','','','','','','','','','','','','','','SA-19(3)','SA-19(3)','3.4.14','','','','','','','','','','','','','','','','','','','','','','','','','','','Sec. 521.052(b)','','','','Art 24','','','','','','','','PS-05
PI-05','','','','','','','','','','','','','','','','','','','','','0311
0312
0313
0315
0316
0317
0318
0319
0321
0322
0329
0350
0361
0362
0363
0364
0366
0368
0370
0371
0372
0373
0374
0375
0378
0838
0839
0840
1069
1076
1160
1217
1218
1219
1220
1221
1222
1223
1224
1225
1226
1347
1360
1361
1455','','','','','','','','12.6
13.5','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Customized Development of Critical Components ','TDA-12','Mechanisms exist to custom-develop critical system components, when COTS solutions are unavailable.','- OWASP','','','X','','','','',8,'','','','','','','','','','','','','','','','','SA-20','SA-20','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Developer Screening ','TDA-13','Mechanisms exist to require the developers of systems, system components or services to satisfy personnel screening criteria and have appropriate access authorizations, as necessary.','','','','X','X','','','X',9,'','','','','','','','','','','','','','','','','SA-21','SA-21','','','','','','','','','','','','','','','','','','','CIP-004-6
R3','','','','','','','','','','','','','','','','','','','','','BEI-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Developer Configuration Management ','TDA-14','Mechanisms exist to require system developers and integrators to perform configuration management during system design, development, implementation and operation.','','X','','X','X','','','X',10,'','','','','','','','','','','14.2.4 ','','','','','','SA-10','SA-10 ','','NFO','','','','','','','','','','SA-10 ','','','','','','','','','','','5.7.1','',' 17.03(2)(d)(B)(i) ','','','','','','','','','','','','','','','BEI-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Software / Firmware Integrity Verification','TDA-14.1','Mechanisms exist to require developer of systems, system components or services to enable integrity verification of software and firmware components. ','','X','','X','','','','',8,'','','','','','','','SO12 ','','','','','','','','','SA-10(1)','SA-10(1) ','','','','','','11.5
11.6
11.7','','','','','','SA-10(1) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Developer Threat Analysis & Flaw Remediation','TDA-15','Mechanisms exist to require system developers and integrators to create a Security Test and Evaluation (ST&E) plan and implement the plan under the witness of an independent party. ','- Security Test and Evaluation (ST&E) plan','','','X','X','','','X',9,'','','','','','','','','','10.1','','','','','','','SA-11(2)','SA-11(2) ','','','','','6.6','','','','','','','SA-11(2) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','BEI-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Developer-Provided Training ','TDA-16','Mechanisms exist to require the developers of systems, system components or services to provide training on the correct use and operation of the system, system component or service.','','','','X','','','','',9,'','','','','','','','','','','','','','','','','SA-16','SA-16 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Unsupported Systems ','TDA-17','Mechanisms exist to prevent unsupported systems by:
 ▪ Replacing systems when support for the components is no longer available from the developer, vendor or manufacturer; and
 ▪ Requiring justification and documented approval for the continued use of unsupported system components required to satisfy mission/business needs.','','X','X','X','X','','','X',10,'','','18.1','2.9','','','','','','','','','','','','','SA-22','SA-22 ','','','','','','','','','','','','','','','','','','','','8-302','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','0304','','','','','','','','','','','9.2.2
9.2.3','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Alternate Sources for Continued Support','TDA-17.1','Mechanisms exist to provide in-house support or contract external providers for support with unsupported system components. ','','','X','X','X','','','X',8,'','','','','','','','','','','','','','','','','SA-22(1)','SA-22(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Input Data Validation ','TDA-18','Mechanisms exist to check the validity of information inputs. ','','X','X','X','','','','X',9,'PI1.2','PI1.2','','','','','','','','','','','','','','','SI-10','SC-14
SI-10 ','','','','','','','','','','','','SI-10 ','','','','','','','','','','','5.10.4.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Error Handling ','TDA-19','Mechanisms exist to handle error conditions by: 
 ▪ Identifying potentially security-relevant error conditions;
 ▪ Generating error messages that provide information necessary for corrective actions without revealing sensitive or potentially harmful information in error logs and administrative messages that could be exploited; and
 ▪ Revealing error messages only to authorized personnel.','','','X','X','','','','',9,'','','','','','','','','','','','','','','','','SI-11','SI-11 ','','','','','','','','','','','','SI-11 ','','','','','','','','','','','5.10.4.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Technology Development & Acquisition','Access to Program Source Code ','TDA-20','Mechanisms exist to limit privileges to change software resident within software libraries. ','- Source code escrow','X','','','','','','',9,'','','','','','','IAC-06','','','','9.4.5 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','IDM-13','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Third-Party Management ','TPM-01','Mechanisms exist to facilitate the implementation of third-party management controls.','- Procurement program
- Contract reviews','X','X','X','X','','X','X',10,'C1.5 ','C1.5 ','','','DSS01.02','','IAC-07
STA-05
STA-09 ','SO4','','','15.1.1 ','','','','','','SA-4','SA-4','','NFO','ID.SC-1','A3
A4','12.8','12.1','','','','','','','','','','','','','','','','','5.1
5.1.1.2
5.1.1.3
5.1.1.4
5.1.1.5
5.1.1.6
5.1.1.8','','',500.11,'','','','','Art 28
Art 32','','Sec 14
Sec 15','Art 16','','Art 42','','','','DLL-01','','','','','','','','','','','','','','','','','','','','','0873
0872
0072
1073
1451
1452','','','','','','','','','','','5.1.4
5.1.5
11.1.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Third-Party Criticality Assessments','TPM-02','Mechanisms exist to identify, prioritize and assess suppliers and partners of critical systems, components and services using a supply chain risk assessment process. ','- Data Protection Impact Assessment (DPIA)','X','','X','X','','','X',10,'','','','','','','','','','','','','','','','','SA-14','RA-9
SA-14','','','ID.BE-1
ID.SC-2','','','12.1','','','','','','','','D1.G.SP.A.3','','','','164.308(a)(1)(ii)(A)
164.308(a)(4)(ii)
164.308(a)(7)(ii)(C)
164.308(a)(7)(ii)(E)
164.308(a)(8)
164.310(a)(2)(i)
164.314
164.316','','8-302
8-311','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1458','','','','','','','','','','','5.1.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Supply Chain Protection ','TPM-03','Mechanisms exist to evaluate security risks associated with the services and product supply chain. ','- Data Protection Impact Assessment (DPIA)','X','X','X','X','','X','X',10,'','','','','','','STA-01
STA-06 ','SO10','','','15.1.3 ','','','','','','SA-12','SA-12','','','ID.SC-4 ','A3
A4','','12.1
12.2
12.3
12.4
12.5
12.6
12.7
12.8','','','','','','','','','','','','','','','','','','','','','','','','','Art 28','','','','','Art 42','','','','','','','','','','','','','Art 31','Art 18','','','Sec 20','Art 20
Art 21','','','','','','','','','','','Art 6','','','','12.7','Sec 25
Sec 43','','5.1.2
12.0.3
12.1.1
12.1.2
12.1.3
12.1.4
12.1.5
12.1.6
12.1.7
12.1.8
12.1.9
12.1.10
12.2.3
12.2.4
12.2.5','','','','','','','','','Art 21','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Acquisition Strategies, Tools & Methods','TPM-03.1','Mechanisms exist to utilize tailored acquisition strategies, contract tools and procurement methods for the purchase of unique systems, system components or services.','- Data Protection Impact Assessment (DPIA)','','','X','','','','',10,'','','','','','','','','','','','','','','','','SA-12(1)','SA-12(1)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Limit Potential Harm','TPM-03.2','Mechanisms exist to utilize security safeguards to limit harm from potential adversaries who identify and target the organization''s supply chain. ','- Data Protection Impact Assessment (DPIA)
- Liability clause in contracts','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','SA-12(5)','SA-12(5)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Processes To Address Weaknesses or Deficiencies','TPM-03.3','Mechanisms exist to address identified weaknesses or deficiencies in the security of the supply chain ','- Data Protection Impact Assessment (DPIA)','X','','X','X','','','X',10,'','','','','','','','','','','','','','','','','SA-12(15)','SA-12(15)','','','','','','12.1
12.2
12.3
12.4
12.5
12.6
12.7
12.8','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Third-Party Services ','TPM-04','Mechanisms exist to mitigate the risks associated with third-party access to the organization’s systems and data.','- Conduct an organizational assessment of risk prior to the acquisition or outsourcing of services.
- Maintain and implement policies and procedures to manage service providers (e.g., Software-as-a-Service (SaaS), web hosting companies, collocation providers, or email providers), through observation, review of policies and procedures and review of supporting documentation. 
- Maintain a program to monitor service providers’ control compliance status at least annually.
- Require providers of external system services to comply with organizational security requirements and employ appropriate security controls in accordance with applicable statutory, regulatory and contractual obligations.
- Define and document oversight and user roles and responsibilities with regard to external system services.','X','X','X','X','','X','X',10,'','','','','','','','','','','14.2.7
15.1.1 ','','','','','','SA-9','SA-9 ','','NFO','','A3
A4','12.8.2 
12.8.4 ','12.1
12.2
12.3
12.4
12.5
12.6
12.7
12.8','','','','','','SA-9 ','','','','','','','','8-700','','','5.1.2','','17.03(2)(f)(1)','','622(2)(d)(A)(v)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','5.2.2
5.2.3
5.2.4
5.2.5
12.0.3
12.1.1
12.1.2
12.1.3
12.1.4
12.1.5
12.1.6
12.1.7
12.1.8
12.1.9
12.1.10
12.2.3
12.2.4
12.2.5','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Third-Party Risk Assessments & Approvals','TPM-04.1','Mechanisms exist to conduct a risk assessment prior to the acquisition or outsourcing of technology-related services.','- Conduct an organizational assessment of risk prior to the acquisition or outsourcing of services.
- Maintain a list of service providers.
- Maintain and implement controls to manage security providers (e.g., backup tape storage facilities or security service providers), through observation, review of policies and procedures and review of supporting documentation.
- Maintain a written agreement that includes an acknowledgment that service providers are responsible for the security of data the service providers possess.
- Maintain a program to monitor service providers’ control compliance status, at least annually.
- Require that providers of external services comply with organizational digital security requirements and utilize appropriate security controls in accordance with all applicable laws and regulatory requirements.','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','SA-9(1)','SA-9(1) ','','','','A3
A4','2.4
12.8- 12.8.4 ','12.1
12.2
12.3
12.4
12.5
12.6
12.7
12.8','','','','','','SA-9(1) ','','','','','Safeguards Rule ','164.308(a)(b)(1)
164.308(a)(4)(1) 
164.314(a)','','','','','5.1.2','','17.03(2)(f)(2)','','622(2)(d)(A)(v)','','','','','','','','','','','','','DLL-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Identification of Functions, Ports, Protocols & Services','TPM-04.2','Mechanisms exist to require process owners to identify the ports, protocols and other services required for the use of such services. ','','X','X','X','X','','X','X',10,'','','','9.5','','','','','','','','','','','','','SA-9(2)','SA-9(2)','','NFO','','A3
A4','','','','','','','','SA-9(2','','','','','','','','','','','5.7.1.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Conflict of Interests','TPM-04.3','Mechanisms exist to ensure that the interests of third-party service providers are consistent with and reflect organizational interests.','- Third-party contract requirements for cybersecurity controls','','','X','X','','X','X',8,'','','','','','','','','','','','','','','','','SA-9(4)','SA-9(4) ','','','','','','','','','','','','SA-9(4) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Third-Party Processing, Storage and Service Locations','TPM-04.4','Mechanisms exist to restrict the location of information processing/storage based on business requirements. ','','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','SA-9(5)','SA-9(5) ','','','','A3
A4','12.9','','','','','','','SA-9(5) ','','','','','','','','','','','','','','','','','','','Art 6
Art 26
Art 27
Art 28
Art 29
Art 44
Art 45
Art 46
Art 47
Art 48
Art 49','','Sec 10','Chapter 4 - Art 16','Art 14
Art 27','Art 41 ','','Art 34','','','','Sec 7','Sec 2','Sec 16
Sec 17','Sec 31','Art 3
Art 4','Sec 12
Sec 13
Sec 14','Sec 13
Sec 14','Art 1
Art 36','Art 14
Art 15','Art 7','','Sec 19
Sec 21','','Sec 31','','','','','','','','','','Art 1','Art 20','Sec 9','','2.2
2.3
20.1
20.2','Sec 25','Sec 24
Sec 26','','Art 17
Art 27','','Art 9
Art 26','','Sec 20','Art 7','Art 26','','','','Art 23');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Third-Party Contract Requirements','TPM-05','Mechanisms exist to identify, regularly review and document third-party confidentiality, Non-Disclosure Agreements (NDAs) and other contracts that reflect the organization’s needs to protect systems and data.','- Non-Disclosure Agreements (NDAs)','X','','X','X','','X','X',10,'C1.4','C1.4','','','','','','','','','13.2.4
15.1.2 ','','','','','','SA-9(3)','SA-9(3) ','','','ID.SC-3','','2.6 
12.9 ','12.1','','','','','','','','','','','','164.308(b)(1)
164.314(a)(1)(i)-(ii)
164.314(a)(1)(ii)(A)-(B)
164.314(a)(2)(i)(A)-(D)
164.314(a)(2)(i)(A)-(D)
164.314(a)(2)(ii)(1)-(2)','','','','','','','','','','','','','Art 28
Art 29','','','','','Art 42','','','','KOS-08','','','','','','','','','Art 31','Art 18','','','Sec 20','Art 20
Art 21','','','','','','','','','','','Art 6','','','','','Sec 25
Sec 43','','5.1.7
5.1.8
5.1.9
5.1.10','','','','','','','','','Art 21','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Third-Party Personnel Security ','TPM-06','Mechanisms exist to control personnel security requirements including security roles and responsibilities for third-party providers.','','X','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','ID.GV-2','','','12.1','','','','','','','','D1.G.SP.B.7
D4.RM.Co.B.2
D4.RM.Co.B.5','','','','164.308(a)(1)(i)
164.308(a)(2)
164.308(a)(3)
164.308(a)(4)
164.308(b)
164.314','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','5.1.8','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Monitoring for Third-Party Information Disclosure ','TPM-07','Mechanisms exist to monitor for evidence of unauthorized exfiltration or disclosure of organizational information. ','','X','','X','X','','X','X',8,'','','','','','','','SO4','','','','','','','','','','','','','','','','12.1','','','','','','','','','','','','','','','','','','','17.04(3)','','','','','','','','','','','','','','','DLL-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','20.2','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Third-Party Service Reviews ','TPM-08','Mechanisms exist to monitor, regularly review and audit supplier service delivery for compliance with established contract agreements. ','','X','X','X','X','X','X','X',9,'','','','','','','STA-04
STA-07
STA-08 ','SO4','','','15.2.1 ','','','','','','SA-12(2)','SA-12(2)','','','','A3
A4','','12.1','','','','','','','','','','','','','','','','','5.1
5.1.1.2
5.1.1.3
5.1.1.4
5.1.1.5
5.1.1.6
5.1.1.8','','','','','','','','','','','','','','','','','DLL-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','5.1.6
5.1.9','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Third-Party Deficiency Remediation ','TPM-09','Mechanisms exist to address weaknesses or deficiencies in supply chain elements identified during independent or organizational assessments of such elements. ','','X','X','X','X','','X','X',10,'','','','','','','STA-02 ','SO10','','10.1','','','','','','','','','','','','A3
A4','','12.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','DLL-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Third-Party Service Changes','TPM-10','Mechanisms exist to control changes to services by suppliers,  taking into account  the criticality of business information, systems and processes that are in scope by the third-party.','- Contact requirement to report changes to service offerings that may impact the contract.','X','','X','X','','X','X',10,'C1.7','C1.7','','','','','','','','','15.2.2 ','','','','','','SA-4','SA-4','','NFO','','','','7.1
7.1.1
7.1.2
7.1.3','','','','','','','','','','','','','','','','','5.1
5.1.1.2
5.1.1.3
5.1.1.4
5.1.1.5
5.1.1.6
5.1.1.8','','17.03(2)(d)(B)(i) ','','','','','','','','','','','','','','','DLL-02','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','5.1.6','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Third-Party Management ','Third-Party Incident Response & Recovery Capabilities','TPM-11','Mechanisms exist to ensure response/recovery planning and testing are conducted with critical suppliers/providers. ','','X','','X','X','','X','X',8,'','','','','','','','','','','','','','','','','','','','','ID.SC-5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Threat Management','Threat Awareness Program','THR-01','Mechanisms exist to implement a threat awareness program that includes a cross-organization information-sharing capability. ','','X','','X','X','','X','X',10,'CC3.1 ','CC3.1 ','','','BAI08.01','','','','','','','','','','','','PM-16','AT-5
PM-15','','','ID.BE-2','','12.6','','','','','','','','','D1.G.SP.Inn.1','','','','164.308(a)(1)(ii)(A)
164.308(a)(4)(ii)
164.308(a)(7)(ii)(C)
164.308(a)(7)(ii)(E)
164.308(a)(8)
164.310(a)(2)(i)
164.314
164.316','CIP-014-2
R4','8-103','','','','','',500.10,'','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','10.1.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Threat Management','Indicators of Exposure (IOE)','THR-02','Mechanisms exist to develop Indicators of Exposure (IOE) to understand the potential attack vectors that attackers could use to attack the organization. ','- Indicators of Exposure (IoE)','','','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Threat Management','Threat Intelligence Feeds','THR-03','Mechanisms exist to maintain situational awareness of evolving threats.','- US-CERT mailing lists & feeds
- InfraGard
- Internal newsletters','X','','X','X','','','X',10,'','','4.4','','','','','','','','','','','','','','SI-5
SI-5(1)','SI-5
SI-5(1)','','3.14.1
3.14.2
3.14.3','ID.RA-2','','6.2 
12.4','','','','','','','SI-5 ','','D2.TI.Ti.B.1','','','','164.308(A)(5)(ii) (ii)(A)','','8-103','','','5.10.4.4','','','','622(2)(d)(B)(iii)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','10.1.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Threat Management','Insider Threat Program ','THR-04','Mechanisms exist to implement an insider threat program that includes a cross-discipline insider threat incident handling team. ','- Insider threat program','','','X','X','','','X',8,'','','','','','','','','','','','','','','','','PM-12','PM-12 ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Threat Management','Insider Threat Awareness','THR-05','Mechanisms exist to utilize security awareness training on recognizing and reporting potential indicators of insider threat.','','','','X','X','X','','X',8,'','','','','','','','','','','','','','','','','AT-2(2)','AT-2(2) ','','3.2.3','','','','','','','','','','AT-2(2) ','','','','','','','','','','','5.2.1.2
5.2.1.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Vulnerability & Patch Management Program (VPMP)','VPM-01','Mechanisms exist to facilitate the implementation and monitoring of vulnerability management controls.','- Vulnerability & Patch Management Program (ComplianceForge)','X','X','X','X','X','X','X',10,'CC6.1 ','CC6.1 ','','11.5','','','TVM-02','','','','12.6.1 ','','','','','','SI-2
SI-3(2)','SI-2','','','ID.RA-1
PR.IP-12','A6
A9','','','','','','','','','','D2.TI.Ti.B.2
D3.DC.Th.B.1
D1.RM.RA.E.2
D3.DC.Th.E.5
D3.DC.Th.A.1
D3.CC.Re.Ev.2','','','','164.308(a)(1)(i)
164.308(a)(1)(ii)(A)
164.308(a)(1)(ii)(B)
164.308(a)(7)(ii)(E)
164.308(a)(8)
164.310(a)(1)
164.312(a)(1)
164.316(b)(2)(iii)','','8-311
8-610','','','5.10.4.1
5.10.4.2
5.13.4.1
5.13.4.2','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','RB-17','','','','','','','','','','','','','','','','','','','','','1163
0909
0911
0112
0113','','','','','','','','6.2
12.4','','','9.5.1
9.5.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Vulnerability Remediation Process ','VPM-02','Mechanisms exist to ensure that vulnerabilities are properly identified, tracked and remediated.','','X','X','X','X','','X','X',10,'','','','11.5','','','','','','10.1','','','','','','','PM-4
SC-18(1)','PM-4
SC-18(1)','','','','A6
A9','','7.1','','','','','','','','','','','','','','','','','5.13.4.3','','17.03(2)(j)','','622(2)(d)(A)(i)','','','','','','','','','','','','','RB-17
RB-19','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Vulnerability Ranking ','VPM-03','Mechanisms exist to identify and assign a risk ranking to newly discovered security vulnerabilities using reputable outside sources for security vulnerability information. ','- US-CERT ','X','X','X','X','','X','X',10,'','','4.8','','','','','','','','','','','','','','','','','','','A6
A9','6.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Continuous Vulnerability Remediation Activities','VPM-04','Mechanisms exist to address new threats and vulnerabilities on an ongoing basis and ensure assets are protected against known attacks. ','','X','X','X','X','','X','X',10,'','','9.4','','','','','','','10.2','','','','','','','SC-18(1)','SC-18(1)','','','RS.MI-3','A6
A9','6.6','','','','','','','','','D1.RM.RA.E.1','','','','164.308(a)(1)(ii)(A)
164.308(a)(1)(ii)(B)
164.308(a)(6)(ii)','','','','','5.13.4.3','','','','','','','','','','','','','','','','','RB-17','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Stable Versions','VPM-04.1','Mechanisms exist to install the latest stable version of any security-related updates on all applicable systems.','','X','X','X','X','','X','X',8,'','','11.5','8.7
11.5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Flaw Remediation with Personally Identifiable Information (PII)','VPM-04.2','Mechanisms exist to identify and correct flaws related to the collection, usage, processing or dissemination of Personal Information (PI).','','X','X','X','X','X','X','X',8,'','','','','','','','','','10.1','','','','','','','SI-2(7)','SI-2(7) ','','','','A9','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 5','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Software Patching','VPM-05','Mechanisms exist to conduct software patching for all deployed operating systems, applications, and firmware.','- Patch management tools','X','X','X','X','X','X','X',10,'','','','3.3
3.7
8.7
11.5','','','','','','','12.6.1 ','','','','','','SI-2
SI-3(2)','SI-2','','3.14.1
3.14.2
3.14.3','','A9','6.1 
6.2 ','','','','','','','SI-2 ','','','','','','','CIP-007-6
R2','8-311
8-610','','','5.10.4.1
5.10.4.2
5.13.4.1
5.13.4.2','','17.04(6)','','622(2)(d)(B)(iii) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1143
0297
1144
0940
1472
0300
0298
0303
0941
0304','','','','','','','','12.4','','','9.5.1','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Centralized Management','VPM-05.1','Mechanisms exist to centrally-manage the flaw remediation process. ','- Patch management tools','X','X','X','X','X','X','X',9,'','','4.5','3.4','','','','','','','','','','','','','SI-2(1)','SI-2(1) ','','','','A9','6.2
6.4.5-6.4.5.4 6.4.6 ','','','','','','','','','','','','','','','','','','','','17.04(7)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Automated Remediation Status','VPM-05.2','Automated mechanisms exist to determine the state of system components with regard to flaw remediation. ','- Vulnerability scanning tools','X','X','X','X','X','X','X',9,'','','','3.4','','','','','','','','','','','','','SI-2(2)','SI-2(2) ','','','','A9','','','','','','','','SI-2(2) ','','','','','','','','','','','5.10.4.1
5.13.4.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Time To Remediate / Benchmarks For Corrective Action','VPM-05.3','Mechanisms exist to track the effectiveness of remediation operations through metrics reporting.','','X','X','X','','X','','',6,'','','','3.4','','','','','','','','','','','','','SI-2(3)','SI-2(3) ','','','','A9','','','','','','','','SI-2(3) ','','','','','','','','','','','5.10.4.1
5.13.4.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Vulnerability Scanning ','VPM-06','Mechanisms exist to detect vulnerabilities and configuration errors by recurring vulnerability scanning of systems and web applications.','','X','X','X','X','X','X','X',10,'','','4.1','3.1
3.2
9.3
9.5
11.3','','','IVS-05 ','','','','','','','','','','RA-5','RA-5 ','','3.11.2
3.11.3','DE.CM-8','A6
A9','11.2','','','','','','','RA-5 ','','D3.DC.Th.E.5','','','','164.308(a)(1)(i)
164.308(a)(8)','CIP-010-2
R3','8-614','','','5.10.4.1
5.13.4.1','','',500.05,'622(2)(B)(iii) 
622(2)(d)(A)(iii) ','','','','','','','','','','','','','RB-21','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.2.4
9.4.1
9.4.2','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Update Tool Capability','VPM-06.1','Mechanisms exist to update vulnerability scanning tools.','','','X','X','X','X','X','X',8,'','','','','','','','','','','','','','','','','RA-5(1)
RA-5(2)','RA-5(1) 
RA-5(2) ','','NFO','','','','','','','','','','RA-5(1) 
RA-5(2) ','','','','','','','','','','','5.10.4.1
5.13.4.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Breadth / Depth of Coverage ','VPM-06.2','Mechanisms exist to identify the breadth and depth of coverage for vulnerability scanning that define the system components scanned and types of vulnerabilities that are checked for. ','','','X','X','X','X','X','X',8,'','','','','','','','','','','','','','','','','RA-5(3)','RA-5(3) ','','','','','','','','','','','','RA-5(3) ','','','','','','','','','','','5.10.4.1
5.13.4.1','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Privileged Access','VPM-06.3','Mechanisms exist to implement privileged access authorization for selected vulnerability scanning activities. ','- Authenticated scans','X','','X','','X','','',9,'','','4.3','3.6','','','','','','','','','','','','','RA-5(5)','RA-5(5) ','','3.11.2','','','','','','','','','','RA-5(5) ','','','','','','','','','','','5.5.2
5.5.2.1
5.5.2.2
5.5.2.3
5.5.2.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Trend Analysis','VPM-06.4','Automated mechanisms exist to compare the results of vulnerability scans over time to determine trends in system vulnerabilities. ','','X','','X','','X','','',9,'','','4.7','','','','','','','','','','','','','','RA-5(6)','RA-5(6) ','','','','','','','','','','','','RA-5(6) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Review Historical Audit Logs','VPM-06.5','Mechanisms exist to review historical audit logs to determine if identified vulnerabilities have been previously exploited. ','','X','','X','','X','','',9,'','','4.2','','','','','','','','','','','','','','RA-5(8)','RA-5(8) ','','','','','','','','','','','','RA-5(8) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','External Vulnerability Assessment Scans','VPM-06.6','Mechanisms exist to performs quarterly external vulnerability scans for Payment Card Industry Data Security Standard (PCI DSS) compliance via an Approved Scanning Vendor (ASV) and includes rescans until passing results are obtained or all “High” vulnerabilities are resolved.','','','','','','','','',10,'','','','','','','','','','','','','','','','','','','','','','','11.2, 11.2.2 11.2.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Internal Vulnerability Assessment Scans','VPM-06.7','Mechanisms exist to performs quarterly internal vulnerability scans for Payment Card Industry Data Security Standard (PCI DSS) compliance and includes rescans until passing results are obtained or all “High” vulnerabilities are resolved.','','','','','','','','',10,'','','','','','','','','','','','','','','','','','','','','','','11.2
11.2.1 
11.2.3','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Penetration Testing ','VPM-07','Mechanisms exist to conduct penetration testing on systems and web applications.','','X','X','X','X','X','X','X',10,'','','20.1
20.2
20.4
20.6
20.8','','','','','','','','','','','','','','CA-8','CA-8','','','','','11.3-11.3.4 ','','','','','','','CA-8 ','','','','','','','','8-610
8-614','','','','','',500.05,'','','','','','','','','','','','','','RB-18','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','6.2.4
9.4.4','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Independent Penetration Agent or Team','VPM-07.1','Mechanisms exist to utilize an independent assessor or penetration team to perform penetration testing.','','','','X','','X','','',6,'','','','','','','','','','','','','','','','','CA-8(1)','CA-8(1) ','','','','','','','','','','','','CA-8(1) ','','','','','','','','','','','','','','','','','','','','','','','','','','','','RB-18','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Technical Surveillance Countermeasures Security ','VPM-08','Mechanisms exist to utilize a technical surveillance countermeasures survey.','- Facility sweeping for \"bugs\" or other unauthorized surveillance technologies.','','','X','','','','',1,'','','','','','','','','','','','','','','','','RA-6','RA-6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Reviewing Vulnerability Scanner Usage','VPM-09','Mechanisms exist to monitor logs associated with scanning activities and associated administrator accounts to ensure that those activities are limited to the timeframes of legitimate scans. ','','X','','','','','','',3,'','','4.6','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Vulnerability & Patch Management ','Red Team Exercises','VPM-10','Mechanisms exist to utilize \"red team\" exercises to simulate attempts by adversaries to compromise systems and applications in accordance with organization-defined rules of engagement. ','','X','','X','X','','','',3,'','','20.3
20.5
20.7','','','','','','','','','','','','','','CA-8(2)','CA-8(2)','','','DE.DP-3','','','','','','','','','','','D3.DC.Ev.Int.2','','','','164.306(e)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Web Security ','Web Security','WEB-01','Mechanisms exist to facilitate the implementation of an enterprise-wide web management policy, as well as associated standards, controls and procedures.','','X','X','X','X','','X','X',10,'','','','','','','','','','','13.1.3 ','','','','','','','','','','','','1.3.1
 1.3.2 
1.3.4','','','','','','','','','','','','','','','','','','','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','','','','','','','','','','','','','','','','','','','','','','1432
1436
1435','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Web Security ','Use of Demilitarized Zones (DMZ)','WEB-02','Mechanisms exist to utilize a Demilitarized Zone (DMZ) to restrict inbound traffic to authorized devices on certain services, protocols and ports.','','X','X','X','X','','X','X',10,'','','','','','','','','','','13.1.3 ','','','','','','','','','','','','1.3.1
 1.3.2 
1.3.4','','','','','','','','','','','','','','','','','','','','','','','','','','Art 32','','Sec 14
Sec 15','Art 16','','','','','','','','','','','','','','','','','','','','','','','','','','','1275
1276
1277
1278
0643
0645
1157
1158
0646
0647
0648','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Web Security ','Web Application Firewall (WAF)','WEB-03','Mechanisms exist to deploy Web Application Firewalls (WAFs) to provide defense-in-depth protection for application-specific threats. ','- Web Application Firewall (WAF)','X','','','','','','',8,'','','18.2','9.4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','1193
0639
1194
0641
0642','','','','','','','','19.3','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Web Security ','Client-Facing Web Services','WEB-04','Mechanisms exist to deploy reasonably-expected security controls to protect the confidentiality and availability of client data that is stored, transmitted or processed by the Internet-based service.','- OWASP','X','X','X','X','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','','8.2','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','E.2.1
E.2.2
E.2.3
E.2.4
E.2.5
E.2.6
E.2.7','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Web Security ','Cookie Management','WEB-05','Mechanisms exist to provide individuals with clear and precise information about cookies, in accordance with regulatory requirements for cookie management.','','','X','','','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','(25)','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
INSERT INTO complianceforge_scf VALUES ('Web Security ','Strong Customer Authentication (SCA)','WEB-06','Mechanisms exist to implement Strong Customer Authentication (SCA) for consumers prove their identity.','','','X','','','','X','X',10,'','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','Art 4','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','','');
    ");
    $stmt->execute();

        // Create the complianceforge scf frameworks table
        $stmt = $db->prepare("CREATE TABLE `complianceforge_scf_frameworks` (`id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY, `name` text DEFAULT NULL, `framework_id` int(11) DEFAULT NULL, `selected` BOOL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    $stmt->execute();

    // Create the ComplianceForge SCF Framework
    $complianceforge_scf_framework_id = add_framework("ComplianceForge SCF", "The ComplianceForge Secure Controls Framework (SCF) provides FREE cybersecurity and privacy control guidance to cover the strategic, operational and tactical needs of organizations, regardless of its size, industry or country of origin.  SimpleRisk has a reseller relationship with ComplianceForge wherein the cybersecurity and privacy-related policies, standards, and procedures can be purchased to go alongside these controls.  See https://www.securecontrolsframework.com/ for more information.", 0, 1);

    // Insert the complianceforge scf framework
    $stmt = $db->prepare("INSERT INTO `complianceforge_scf_frameworks` (`name`, `framework_id`) VALUES ('ComplianceForge SCF', :complianceforge_scf_framework_id);");
    $stmt->bindParam(":complianceforge_scf_framework_id", $complianceforge_scf_framework_id, PDO::PARAM_INT);
    $stmt->execute();

    // Create the array of complianceforge scf frameworks
    $frameworks = array(
        "AICPA SOC 2 (2016)",
        "AICPA SOC 2 (2017)",
        "CIS CSC v6.1",
        "CIS CSC v7 [draft]",
        "COBIT v5",
        "COSO v2013",
        "CSA CCM v3.0.1",
        "ENISA v2.0",
        "GAPP",
        "ISO 27001 v2013",
        "ISO 27002 v2013",
        "ISO 27018 v2014",
        "ISO 31000 v2009",
        "ISO 31010 v2009",
        "NIST 800-37",
        "NIST 800-39",
        "NIST 800-53 rev 4",
        "NIST 800-53 rev 5",
        "NIST 800-160",
        "NIST 800-171 rev 1",
        "NIST CSF",
        "OWASP Top 10 v2017",
        "PCI DSS v3.2",
        "UL 2900-1",
        "US COPPA1",
        "US DFARS 252.204-70xx",
        "US FACTA",
        "US FAR 52.204-21",
        "US FDA 21 CFR Part 11",
        "US FedRAMP [moderate]",
        "US FERPA",
        "US FFIEC",
        "US FINRA",
        "US FTC Act",
        "US GLBA",
        "US HIPAA",
        "US NERC CIP",
        "US NISPOM",
        "US Privacy Shield",
        "US SOX",
        "US CJIS Security Policy",
        "US - CA SB1386",
        "US - MA 201 CMR 17.00",
        "US - NY DFS 23 NYCRR500",
        "US - OR 646A",
        "US - TX BC521",
        "US-TX Cybersecurity Act",
        "EMEA EU ePrivacy [draft]",
        "EMEA EU GDPR",
        "EMEA EU PSD2",
        "EMEA Austria",
        "EMEA Belgium",
        "EMEA Czech Republic",
        "EMEA Denmark",
        "EMEA Finland",
        "EMEA France",
        "EMEA Germany",
        "EMEA Germany C5",
        "EMEA Greece",
        "EMEA Hungary",
        "EMEA Ireland",
        "EMEA Israel",
        "EMEA Italy",
        "EMEA Luxembourg",
        "EMEA Netherlands",
        "EMEA Norway",
        "EMEA Poland",
        "EMEA Portugal",
        "EMEA Russia",
        "EMEA Slovak Republic",
        "EMEA South Africa",
        "EMEA Spain",
        "EMEA Sweden",
        "EMEA Switzerland",
        "EMEA Turkey",
        "EMEA UAE",
        "EMEA UK",
        "APAC Australia",
        "APAC Australia ISM 2017",
        "APAC China DNSIP",
        "APAC Hong Kong",
        "APAC India ITR",
        "APAC Indonesia",
        "APAC Japan",
        "APAC Malaysia",
        "APAC New Zealand",
        "APAC New Zealand NZISM",
        "APAC Philippines",
        "APAC Singapore",
        "APAC Singapore MAS TRM",
        "APAC South Korea",
        "APAC Taiwan",
        "Americas Argentina",
        "Americas Bahamas",
        "Americas Canada PIPEDA",
        "Americas Chile",
        "Americas Columbia",
        "Americas Costa Rica",
        "Americas Mexico",
        "Americas Peru",
        "Americas Uruguay",
);

    // For each framework in the array
    foreach ($frameworks as $name)
    {
        // Add the framework with an empty description, complianceforge scf as parent, and set it to enabled
        $framework_id = add_framework($name, "", $complianceforge_scf_framework_id, 1);

        // Insert the complianceforge scf framework
        $stmt = $db->prepare("INSERT INTO `complianceforge_scf_frameworks` (`name`, `framework_id`) VALUES (:name, :framework_id);");
        $stmt->bindParam(":name", $name, PDO::PARAM_STR);
        $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Get the list of all frameworks
    $simplerisk_frameworks = get_frameworks();

    // Get the list of all complianceforge scf controls
    $stmt = $db->prepare("SELECT * FROM `complianceforge_scf`;");
    $stmt->execute();
    $array = $stmt->fetchAll();

    // For each complianceforge scf control
    foreach ($array as $complianceforge_scf_control)
    {
    /*
        // Check for the control class
        $control_class = $complianceforge_scf_control['SCF Domain'];
        $stmt = $db->prepare("SELECT value FROM `control_class` WHERE name=:control_class;");
        $stmt->bindParam(":control_class", $control_class, PDO::PARAM_STR);
        $stmt->execute();
        $control_class_array = $stmt->fetch();
        $control_class_id = $control_class_array['value'];

        // If the control class isn't found
        if (is_null($control_class_id))
        {
            // Insert the new control class
            $stmt = $db->prepare("INSERT INTO `control_class` (`name`) VALUES (:control_class);");
            $stmt->bindParam(":control_class", $control_class, PDO::PARAM_STR);
            $stmt->execute();
            $control_class_id = $db->lastInsertId();
        }
    */

    // Check for the control priority
    $control_priority = $complianceforge_scf_control['Relative Control Weighting (1-10)'];
    $stmt = $db->prepare("SELECT value FROM `control_priority` WHERE name=:control_priority;");
    $stmt->bindParam(":control_priority", $control_priority, PDO::PARAM_STR);
    $stmt->execute();
    $control_priority_array = $stmt->fetch();
    $control_priority_id = $control_priority_array['value'];

        // If the control priority isn't found
        if (is_null($control_priority_id))
        {
            // Insert the new control priority
            $stmt = $db->prepare("INSERT INTO `control_priority` (`name`) VALUES (:control_priority);");
            $stmt->bindParam(":control_priority", $control_priority, PDO::PARAM_STR);
            $stmt->execute();
            $control_priority_id = $db->lastInsertId();
        }

        // Check for the control family
        $control_family = $complianceforge_scf_control['SCF Domain'];
        $stmt = $db->prepare("SELECT value FROM `family` WHERE name=:control_family;");
        $stmt->bindParam(":control_family", $control_family, PDO::PARAM_STR);
        $stmt->execute();
        $control_family_array = $stmt->fetch();
        $control_family_id = $control_family_array['value'];

        // If the control family isn't found
        if (is_null($control_family_id))
        {
            // Insert the new control family
                $stmt = $db->prepare("INSERT INTO `family` (`name`) VALUES (:control_family);");
                $stmt->bindParam(":control_family", $control_family, PDO::PARAM_STR);
                $stmt->execute();
                $control_family_id = $db->lastInsertId();
        }

        // Initialize the framwork_ids array with the id of the complianceforge scf framework
        $framework_ids = array($complianceforge_scf_framework_id);

        // For each of the frameworks
        foreach ($frameworks as $framework)
        {
            // Check if the framework exists as a key in the complianceforge control array
            if (array_key_exists($framework, $complianceforge_scf_control))
            {
                // If the framework is included in this control
                if ($complianceforge_scf_control[$framework] != "")
                {
                    // For each framework
                    foreach ($simplerisk_frameworks as $row)
                    {
                        // If this is the framework we are looking for
                        if ($row['name'] == $framework)
                        {
                            // Add the framework id
                            $framework_ids[] = $row['value'];

                            // Break out of the foreach loop
                            break;
                        }
                    }
                }
            }
        }

        // Translate the complianceforge control to SimpleRisk control
        $control = array(
            'short_name'=>$complianceforge_scf_control['SCF Control'],
            'long_name'=>$complianceforge_scf_control['SCF Control'],
            'description'=>$complianceforge_scf_control['Secure Controls Framework (SCF) Control Description'],
            'supplemental_guidance'=>$complianceforge_scf_control['Methods To Comply With SCF Controls'],
            'framework_ids'=>$framework_ids,
            'control_owner'=>0,
            'control_class'=>0,
            'control_phase'=>0,
            'control_number'=>$complianceforge_scf_control['SCF #'],
            'control_priority'=>$control_priority_id,
            'family'=>$control_family_id,
            'mitigation_percent'=>0,
        );

    $short_name = isset($control['short_name']) ? $control['short_name'] : "";
    $long_name = isset($control['long_name']) ? $control['long_name'] : "";
    $description = isset($control['description']) ? $control['description'] : "";
    $supplemental_guidance = isset($control['supplemental_guidance']) ? $control['supplemental_guidance'] : "";
    $framework_ids = isset($control['framework_ids']) ? (is_array($control['framework_ids']) ? implode(",", $control['framework_ids']) : $control['framework_ids']) : "";
    $control_owner = isset($control['control_owner']) ? (int)$control['control_owner'] : 0;
    $control_class = isset($control['control_class']) ? (int)$control['control_class'] : 0;
    $control_phase = isset($control['control_phase']) ? (int)$control['control_phase'] : 0;
    $control_number = isset($control['control_number']) ? $control['control_number'] : "";
    $control_priority = isset($control['control_priority']) ? (int)$control['control_priority'] : 0;
    $family = isset($control['family']) ? (int)$control['family'] : 0;
    $mitigation_percent = isset($control['mitigation_percent']) ? (int)$control['mitigation_percent'] : 0;

        // Add the control to the framework
        add_framework_control($control);
    }

        // Close the database connection
        db_close($db);
}

/**********************************************
 * FUNCTION: DELETE COMPLIANCEFORGE SCF TABLE *
 **********************************************/
function delete_complianceforge_scf_table()
{
        // Open the database connection
        $db = db_open();

        // Delete the complianceforge scf table
        $stmt = $db->prepare("DROP TABLE `complianceforge_scf`;");
        $stmt->execute();


    // Delete all controls that are part of the complianceforge scf framework

    // Get the list of complianceforge dsp frameworks
    $stmt = $db->prepare("SELECT * FROM `complianceforge_scf_frameworks`;");
    $stmt->execute();
    $array = $stmt->fetchAll();

    // For each framework
    foreach ($array as $framework)
    {
        // Get the associated framework id
        $framework_id = $framework['framework_id'];

    // If this is the complianceforge scf framework
    if ($framework['name'] == "ComplianceForge SCF")
    {
        // Delete all controls that are part of the complianceforge scf framework
        $stmt = $db->prepare("DELETE FROM `framework_controls` WHERE FIND_IN_SET(:framework_id, framework_ids) != 0");
        $stmt->bindParam(":framework_id", $framework_id, PDO::PARAM_INT);
        $stmt->execute();
    }

        // Delete the framework
        delete_frameworks($framework_id);
    }

        // Delete the complianceforge scf frameworks table
        $stmt = $db->prepare("DROP TABLE `complianceforge_scf_frameworks`;");
        $stmt->execute();

        // Close the database connection
        db_close($db);

}

?>

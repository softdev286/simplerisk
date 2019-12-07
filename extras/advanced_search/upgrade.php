<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

global $advanced_search_updates;
$advanced_search_updates = array(
    'upgrade_advanced_search_extra_20191002001',
);

/****************************************************
 * FUNCTION: UPGRADE ADVANCED SEARCH EXTRA DATABASE *
 ****************************************************/
function upgrade_advanced_search_extra_database()
{
    global $advanced_search_updates;

    $version_name = 'advanced_search_extra_version';

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
    if (array_key_exists($db_version, $advanced_search_updates))
    {
        // Get the function to upgrade to the next version
        $function = $advanced_search_updates[$db_version];

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
            upgrade_advanced_search_extra_database();
        }
    }
}


/*******************************************************
 * FUNCTION: UPGRADE ADVANCED SEARCH EXTRA 20191002001 *
 *******************************************************/
function upgrade_advanced_search_extra_20191002001()
{
    // Connect to the database
    $db = db_open();

    $sql = "
        UPDATE
            risks t1 inner join 
            (SELECT c1.risk_id, c1.next_step, c2.date FROM mgmt_reviews c1 RIGHT JOIN (SELECT risk_id, MAX(submission_date) AS date FROM mgmt_reviews GROUP BY risk_id) AS c2 
                ON c1.risk_id = c2.risk_id AND c1.submission_date = c2.date ) t2 on t1.id=t2.risk_id
        SET t1.project_id=0
        WHERE
            t2.next_step<>2 AND t1.project_id>0;
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute();

    // Disconnect from the database
    db_close($db);
}
?>

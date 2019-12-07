<?php

/********************************************************************
 * COPYRIGHT NOTICE:                                                *
 * This Source Code Form is copyrighted 2019 to SimpleRisk, LLC and *
 * cannot be used or duplicated without express written permission. *
 ********************************************************************/

/********************************************************************
 * NOTES:                                                           *
 * This SimpleRisk Extra enables the ability of SimpleRisk to       *
 * expand the functionality of the topbar's search box to be able   *
 * to find risks by doing textual search in risk data               *
 ********************************************************************/

// Extra Version
define('ADVANCED_SEARCH_EXTRA_VERSION', '20191130-001');

// Include required functions file
require_once(realpath(__DIR__ . '/../../includes/functions.php'));
require_once(realpath(__DIR__ . '/../../includes/alerts.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

require_once(realpath(__DIR__ . '/upgrade.php'));

// Upgrade extra database version
upgrade_advanced_search_extra_database();

/******************************************
 * FUNCTION: ENABLE ADVANCED SEARCH EXTRA *
 ******************************************/
function enable_advanced_search_extra() {
    global $lang;

    prevent_extra_double_submit('advanced_search', true);

    update_or_insert_setting('advanced_search', true);

    $message = _lang('ExtraToggledOn', ['extra_name' => $lang['AdvancedSearch'], 'user' => $_SESSION['user']]);
    write_log(1000, $_SESSION['uid'], $message, 'extra');
}

/*******************************************
 * FUNCTION: DISABLE ADVANCED SEARCH EXTRA *
 *******************************************/
function disable_advanced_search_extra() {
    global $lang;

    prevent_extra_double_submit('advanced_search', false);

    update_or_insert_setting('advanced_search', false);

    $message = _lang('ExtraToggledOff', ['extra_name' => $lang['AdvancedSearch'], 'user' => $_SESSION['user']]);
    write_log(1000, $_SESSION['uid'], $message, 'extra');
}

/*************************************
 * FUNCTION: ADVANCED SEARCH VERSION *
 *************************************/
function advanced_search_version() {
    return ADVANCED_SEARCH_EXTRA_VERSION;
}

/********************************
 * FUNCTION: DO ADVANCED SEARCH *
 ********************************/
function do_advanced_search($q, $teaser_size = 100, $highlight = true, $escape_type = 'html') {
    
    if (encryption_extra()) {
        require_once(realpath(__DIR__ . '/../encryption/index.php'));

        $encryption = true;
    } else
        $encryption = false;

    if (team_separation_extra()) {
        require_once(realpath(__DIR__ . '/../separation/index.php'));

        $separation = true;
        $separation_fields = ", r.owner, r.manager, r.submitted_by, r.`additional_stakeholders`, r.team ";
        $separation_query = get_user_teams_query(false, true);
    } else {
        $separation = false;
        $separation_fields = "";
        $separation_query = "";
    }
    
    if(customization_extra()) {
        require_once(realpath(__DIR__ . '/../customization/index.php'));

        $customization = true;
    } else
        $customization = false;
    
    $db = db_open();
    
    $results = [];
    
    $id_order = 1;
    $subject_order = 2;
    $project_order = 3;
    $assessment_order = 4;
    $notes_order = 5;
    $current_solution_order = 6;
    $security_requirements_order = 7;
    $security_recommendations_order = 8;
    $comments_order = 9;
    $assets_order = 10;
    $tags_order = 11;
    $customization_order = 12;
    
    $list_valued_category_orders = [$assets_order, $tags_order];
    
    if (!$encryption) {
        $stmt = $db->prepare("
            SELECT id + 1000 as id, subject, field_value, category_field_name, category_order FROM (" .

                (ctype_digit($q) ? "
                SELECT
                    r.id,
                    r.subject,
                    r.id + 1000 as field_value,
                    'id' as category_field_name,
                    {$id_order} as category_order
                    {$separation_fields}
                FROM
                    risks r
                WHERE
                    r.id = $q - 1000

                UNION ALL
                " : "") . 

                "SELECT
                    r.id,
                    r.subject,
                    r.subject as field_value,
                    'subject' as category_field_name,
                    {$subject_order} as category_order
                    {$separation_fields}
                FROM
                    risks r
                WHERE
                    CAST(r.subject AS CHAR) LIKE CONCAT('%', :q, '%')

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        r.assessment as field_value,
                        'assessment' as category_field_name,
                        {$assessment_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                    WHERE
                        r.assessment LIKE CONCAT('%', :q, '%')

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        p.name as field_value,
                        'project' as category_field_name,
                        {$project_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN projects p ON r.project_id=p.value
                    WHERE
                        p.name LIKE CONCAT('%', :q, '%')

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        r.notes as field_value,
                        'notes' as category_field_name,
                        {$notes_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                    WHERE
                        r.notes LIKE CONCAT('%', :q, '%')

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        m.current_solution as field_value,
                        'current_solution' as category_field_name,
                        {$current_solution_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN mitigations m ON r.id = m.risk_id
                    WHERE
                        m.current_solution LIKE CONCAT('%', :q, '%')

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        m.security_requirements as field_value,
                        'security_requirements' as category_field_name,
                        {$security_requirements_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN mitigations m ON r.id = m.risk_id
                    WHERE
                        m.security_requirements LIKE CONCAT('%', :q, '%')

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        m.security_recommendations as field_value,
                        'security_recommendations' as category_field_name,
                        {$security_recommendations_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN mitigations m ON r.id = m.risk_id
                    WHERE
                        m.security_recommendations LIKE CONCAT('%', :q, '%')

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        mr.comments as field_value,
                        'comments' as category_field_name,
                        {$comments_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN mgmt_reviews mr ON r.mgmt_review = mr.id
                    WHERE
                        mr.comments LIKE CONCAT('%', :q, '%')

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        group_concat(DISTINCT a.name ORDER BY a.name ASC SEPARATOR ', ') as field_value,
                        'assets' as category_field_name,
                        {$assets_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN risks_to_assets rta_ff ON rta_ff.risk_id = r.id
                        INNER JOIN assets a_ff ON rta_ff.asset_id = a_ff.id
                        INNER JOIN risks_to_assets rta ON rta.risk_id = r.id
                        INNER JOIN assets a ON rta.asset_id = a.id
                    WHERE
                        a_ff.name LIKE CONCAT('%', :q, '%')
                    GROUP BY
                        r.id

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        group_concat(DISTINCT t.tag ORDER BY t.tag ASC SEPARATOR ', ') as field_value,
                        'tags' as category_field_name,
                        {$tags_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN tags_taggees tt ON tt.taggee_id = r.id and tt.type = 'risk'
                        INNER JOIN tags t ON tt.tag_id = t.id
                        INNER JOIN tags_taggees tt_ff ON tt_ff.taggee_id = r.id and tt_ff.type = 'risk'
                        INNER JOIN tags t_ff ON tt_ff.tag_id = t_ff.id
                    WHERE
                        t_ff.tag LIKE CONCAT('%', :q, '%')
                    GROUP BY
                        r.id

                " . ( $customization ?
                "UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        crd.value as field_value,
                        cf.name as category_field_name,
                        {$customization_order} as category_order
                        {$separation_fields} 
                    FROM risks r
                        INNER JOIN custom_risk_data crd ON r.id=crd.risk_id
                        INNER JOIN custom_fields cf ON crd.field_id=cf.id AND cf.fgroup = 'risk' AND cf.type IN ('shorttext', 'longtext')
                        INNER JOIN custom_template ct ON cf.id=ct.custom_field_id
                    WHERE crd.value LIKE CONCAT('%', :q, '%')
                " : "") .
            ") u
            {$separation_query}
            GROUP BY
                id
            ORDER BY
                category_order, id
            ;"
        );
        $stmt->bindParam(":q", $q, PDO::PARAM_STR);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("
            SELECT id + 1000 as id, subject, field_value, category_field_name, category_order FROM (" .

                (ctype_digit($q) ? "
                SELECT
                    r.id,
                    r.subject,
                    r.id + 1000 as field_value,
                    'id' as category_field_name,
                    {$id_order} as category_order
                    {$separation_fields}
                FROM
                    risks r
                WHERE
                    r.id = $q - 1000

                UNION ALL
                " : "") . 

                "SELECT
                    r.id,
                    r.subject,
                    r.subject as field_value,
                    'subject' as category_field_name,
                    {$subject_order} as category_order
                    {$separation_fields}
                FROM
                    risks r

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        r.assessment as field_value,
                        'assessment' as category_field_name,
                        {$assessment_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        p.name as field_value,
                        'project' as category_field_name,
                        {$project_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN projects p ON r.project_id=p.value

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        r.notes as field_value,
                        'notes' as category_field_name,
                        {$notes_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        m.current_solution as field_value,
                        'current_solution' as category_field_name,
                        {$current_solution_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN mitigations m ON r.id = m.risk_id

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        m.security_requirements as field_value,
                        'security_requirements' as category_field_name,
                        {$security_requirements_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN mitigations m ON r.id = m.risk_id

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        m.security_recommendations as field_value,
                        'security_recommendations' as category_field_name,
                        {$security_recommendations_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN mitigations m ON r.id = m.risk_id

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        mr.comments as field_value,
                        'comments' as category_field_name,
                        {$comments_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN mgmt_reviews mr ON r.mgmt_review = mr.id

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        group_concat(DISTINCT a.name ORDER BY a.order_by_name ASC) as field_value,
                        'assets' as category_field_name,
                        {$assets_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN risks_to_assets rta ON rta.risk_id = r.id
                        INNER JOIN assets a ON rta.asset_id = a.id
                    GROUP BY
                        r.id

                UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        group_concat(DISTINCT t.tag ORDER BY t.tag ASC) as field_value,
                        'tags' as category_field_name,
                        {$tags_order} as category_order
                        {$separation_fields}
                    FROM
                        risks r
                        INNER JOIN tags_taggees tt ON tt.taggee_id = r.id and tt.type = 'risk'
                        INNER JOIN tags t ON tt.tag_id = t.id
                        INNER JOIN tags_taggees tt_ff ON tt_ff.taggee_id = r.id and tt_ff.type = 'risk'
                        INNER JOIN tags t_ff ON tt_ff.tag_id = t_ff.id
                    WHERE
                        t_ff.tag LIKE CONCAT('%', :q, '%')
                    GROUP BY
                        r.id

                " . ( $customization ?
                "UNION ALL
                    SELECT
                        r.id,
                        r.subject,
                        crd.value as field_value,
                        cf.name as category_field_name,
                        {$customization_order} as category_order
                        {$separation_fields} 
                    FROM risks r
                        INNER JOIN custom_risk_data crd ON r.id=crd.risk_id
                        INNER JOIN custom_fields cf ON crd.field_id=cf.id AND cf.fgroup = 'risk' AND cf.type IN ('shorttext', 'longtext')
                        INNER JOIN custom_template ct ON cf.id=ct.custom_field_id
                    WHERE crd.value LIKE CONCAT('%', :q, '%')
                " : "") .
            ") u
            {$separation_query}
            ORDER BY
                category_order, id
            ;"
        );

        $stmt->bindParam(":q", $q, PDO::PARAM_STR);

        $non_encrypted_category_orders = [$id_order, $tags_order, $customization_order];
        $sql_filtered_category_orders = [$id_order, $tags_order];
        
        $stmt->execute();
        $unfiltered_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $matched_ids = [];
        foreach($unfiltered_results as &$unfiltered_result) {

            //If we're already found this risk we just won't check again
            if (in_array($unfiltered_result['id'], $matched_ids))
                continue;

            $encrypted_value = !in_array($unfiltered_result['category_order'], $non_encrypted_category_orders);
            $list_value = in_array($unfiltered_result['category_order'], $list_valued_category_orders);
            $sql_filtered = in_array($unfiltered_result['category_order'], $sql_filtered_category_orders);

            // Decrypt the field we're matching on(except those few that are not encrypted)
            if ($encrypted_value) {
                
                // In case the value is a list of encrypted names
                if ($list_value) {

                    // Decrypting the names into a list
                    $list = [];
                    foreach(explode(',', $unfiltered_result['field_value']) as $enc_value) {
                        $list[] = try_decrypt($enc_value);
                    }
                    
                    $unfiltered_result['field_value'] = $list;
                } else
                    $unfiltered_result['field_value'] = try_decrypt($unfiltered_result['field_value']);
                
            } // In case the value is a list of non-encrypted names, we just make a list out of the string
            elseif ($list_value) {
                $unfiltered_result['field_value'] = explode(',', $unfiltered_result['field_value']);
            }

            //If what we're looking for is in there(not checking if the category_field_name is 'id' as it is already matched)
            if ($sql_filtered
                || ($list_value && match_list($unfiltered_result['field_value'], $q))
                || (!$list_value && stripos($unfiltered_result['field_value'], $q) !== false)) {
                // then we decrypt the subject too(unless the category_field_name is 'subject', because then we already decrypted that)
                $unfiltered_result['subject'] = ($unfiltered_result['category_field_name'] == 'subject' ? $unfiltered_result['field_value'] : try_decrypt($unfiltered_result['subject']));

                // Making a string from the list
                if (in_array($unfiltered_result['category_order'], $list_valued_category_orders)){
                    $unfiltered_result['field_value'] = implode(', ', $unfiltered_result['field_value']);
                }

                // and save the result
                $results[] = $unfiltered_result;
                // and mark the id as 'matched'
                $matched_ids[] = $unfiltered_result['id'];
            }
        }
    }

    foreach($results as &$result) {

        if ($escape_type) {
            $result['subject'] = escapeValue($result['subject'], $escape_type);
            $result['field_value'] = escapeValue($result['field_value'], $escape_type);
            
            // Only have to escape it in this case as in all the other cases we're using pre-defined values
            if ($result['category_order'] == $customization_order)
                $result['category_field_name'] = escapeValue($result['category_field_name'], $escape_type);
        }
        
        $result['field_value'] = create_teaser($q, $result['field_value'], $teaser_size, $highlight);
    }

    return $results;
}

function escapeValue($value, $escape_type) {
    global $escaper;
    
    switch($escape_type) {
        case 'js':
            return $escaper->escapeJs($value);
        case 'html':
            return $escaper->escapeHtml($value);
    }
}

/********************************************************************
 * FUNCTION: MATCH LIST                                             *
 * Matches on a list of values. Returns true on the first match.    *
 * $list: List of the values the function has to match on           *
 * $q: The query                                                    *
 ********************************************************************/
function match_list($list, $q) {
    foreach($list as $name) {
        if (stripos($name, $q) !== false)
            return true;
    }

    return false;
}

/************************************************************************
 * FUNCTION: CREATE TEASER                                              *
 * $q: The query                                                        *
 * $text: The matched field's value it has to create the teaser from    *
 * $size: $size of the teaser pieces                                    *
 * $highlight: Turns highlighting on/off                                *
 ************************************************************************/
function create_teaser($q, $text, $size, $highlight) {
    
    $len_q = strlen($q);
    $len_text = strlen($text);
    $cuts_size = round(($size - $len_q) / 2);
    $pieces = [];
    $offset = 0;
    while($offset <= $len_text && ($pos = stripos($text, $q, $offset)) !== false) {

        $start = max($offset, $pos - $cuts_size);
        $length = $pos - $start + $len_q + $cuts_size;
        $end = min($len_text, $pos + $len_q + $cuts_size);
        $piece = ($start != 0 ? '...' : '') . trim(substr($text, $start, $length)) . ($end != $len_text ? '...':'');
        
        if ($highlight)
            $piece = preg_replace('/('. $q .')/i', "<span class='highlighted'>$1</span>", $piece);
        
        $pieces[] = $piece;

        $offset = $pos + $len_q + $cuts_size;
    }
    
    return implode(' ', $pieces);
}
?>

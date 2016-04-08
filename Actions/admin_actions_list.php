<?php
/*
 * @author Anakeen
*/

function admin_actions_list(Action & $action)
{
    $return = array(
        "success" => true,
        "error" => array() ,
        "body" => array()
    );
    
    try {
        $appId = $action->parent->id;
        if (!is_numeric($appId)) {
            throw new Exception(sprintf("unexpected application id: %s", var_export($appId, true)));
        }
        
        $appName = $action->parent->name;
        $body = array();
        $adminActions = array();
        
        $query = <<< "SQL"
SELECT
    action.name,
    action.short_name,
    action.long_name
FROM action
WHERE
    action.id_application = $appId
    AND action.name NOT IN ('ADMIN_ACTIONS_LIST','TENGINE_CLIENT_PARAMS', 'TENGINE_CLIENT_INFOS')
;
SQL;
        
        simpleQuery('', $query, $adminActions, false, false, true);
        
        foreach ($adminActions as $adminAction) {
            if (!$action->canExecute($adminAction["name"], $appId)) {
                $actionUrl = "?app=$appName&action=" . $adminAction["name"];
                $body[] = array(
                    "url" => $actionUrl,
                    "label" => _($adminAction["short_name"]) ,
                    "title" => (empty($adminAction["long_name"]) ? _($adminAction["short_name"]) : _($adminAction["long_name"]))
                );
            }
        }
        
        $sortFunction = function ($value1, $value2)
        {
            return strnatcasecmp($value1["label"], $value2["label"]);
        };
        
        usort($body, $sortFunction);
        
        $return["body"] = $body;
    }
    catch(Exception $e) {
        $return["success"] = false;
        $return["error"][] = $e->getMessage();
        unset($return["body"]);
    }
    
    $action->lay->template = json_encode($return);
    $action->lay->noparse = true;
    header('Content-type: application/json');
}

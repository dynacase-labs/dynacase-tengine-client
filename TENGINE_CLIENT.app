<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FDL
*/

global $app_desc, $action_desc, $app_acl;

$app_desc = array(
    "name" => "TENGINE_CLIENT",
    "short_name" => N_("tengine_client:short_name") ,
    "description" => N_("tengine_client:description") ,
    "icon" => "tengine_client.png",
    "displayable" => "Y",
    "tag" => "ADMIN SYSTEM"
);

$app_acl = array(
    array(
        "name" => "TENGINE_CLIENT",
        "description" => N_("tengine_client:acl:tengine_client") ,
        "admin" => true
    )
);

$action_desc = array(
    array(
        "name" => "ADMIN_ACTIONS_LIST",
        "short_name" => N_("tengine_client:action:admin_actions_list:short_name") ,
        "acl" => "TENGINE_CLIENT"
    ) ,
    array(
        "name" => "TENGINE_CLIENT_PARAMS",
        "acl" => "TENGINE_CLIENT",
        "short_name" => N_("tengine_client:action:tengine_client_params") ,
        "script" => "tengine_client_params.php",
        "function" => "tengine_client_params",
        "layout" => "tengine_client_params.html",
        "root" => "Y"
    ) ,
    array(
        "name" => "TENGINE_CLIENT_CONVERT_FILE",
        "acl" => "TENGINE_CLIENT",
        "short_name" => N_("tengine_client:action:tengine_client_convert") ,
        "script" => "tengine_client_convert.php",
        "function" => "tengine_client_convert",
        "layout" => "tengine_client_convert.html"
    ) ,
    array(
        "name" => "TENGINE_CLIENT_TASKS",
        "acl" => "TENGINE_CLIENT",
        "short_name" => N_("tengine_client:action:tengine_client_tasks") ,
        "script" => "tengine_client_tasks.php",
        "function" => "tengine_client_tasks",
        "layout" => "tengine_client_tasks.html"
    )
);

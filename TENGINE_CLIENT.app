<?php
/*
 * @author Anakeen
 * @package FDL
*/

global $app_desc, $action_desc, $app_acl;

$app_desc = array(
    "name" => "TENGINE_CLIENT",
    "short_name" => N_("tengine_client:short_name") ,
    "description" => N_("tengine_client:description") ,
    "icon" => "tengine_client.png"
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
        "name" => "TENGINE_CLIENT_INFOS",
        "short_name" => N_("tengine_client:action:admin_actions_infos:short_name") ,
        "short_name" => N_("tengine_client:action:admin_actions_infos:long_name") ,
        "acl" => "TENGINE_CLIENT",
        "script" => "tengine_client_infos.php"
    ),
    array(
        "name" => "TENGINE_CLIENT_PARAMS",
        "acl" => "TENGINE_CLIENT",
        "short_name" => N_("TE:Client:UI:X0000 short name (params)") ,
        "long_name" => N_("TE:Client:UI:X0000 long name (params)") ,
        "script" => "tengine_client_params.php",
        "function" => "tengine_client_params",
        "layout" => "tengine_client_params.html"
    ),
    array(
        "name" => "TENGINE_CLIENT_PARAMS_VALID",
        "acl" => "TENGINE_CLIENT",
        "openaccess" => "Y",
        "short_name" => N_("TE:Callback test") ,
        "long_name" => N_("TE:Callback test") ,
        "script" => "tengine_client_params.php",
        "function" => "tengine_client_params_valid"
    )
);

<?php

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
    )
);

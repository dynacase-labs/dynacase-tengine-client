<?php
/*
 * @author Anakeen
 * @package FDL
*/

function tengine_client_infos(Action & $action)
{
    require_once "FDL/editutil.php";
    $response = array( "success" => true, "message" => "", "info" => null); 

    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        $response['success'] = false;
        $response['message'] = $err;
    } else {
        $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
        $err = $te->retrieveServerInfo($info, true);
        if ($err != '') {
            $response['success'] = false;
            $response['message'] = $err;
        } else {
            $response['info'] = $info;
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
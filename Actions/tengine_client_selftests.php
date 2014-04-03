<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FDL
*/

function tengine_client_selftests(Action & $action)
{
    require_once "FDL/editutil.php";
    
    $response = array( "success" => false, "message" => "error", "info" => null); 

    $usage = new ActionUsage($action);
    $op = $usage->addOptionalParameter('op', 'Operation');
    $selftestid = $usage->addOptionalParameter('selftestid', 'Selectest id to execute');
    $usage->verify(true);
    
    editmode($action);
    switch ($op) {
        case '':
            $action->parent->AddCssRef("css/dcp/jquery-ui.css");
            $action->parent->AddCssRef("TENGINE_CLIENT:tengine_client.css");
            $action->parent->AddCssRef("TENGINE_CLIENT:tengine_client_selftests.css", true);
            $action->parent->AddJsRef("lib/jquery-ui/js/jquery-ui.js");
            $action->parent->AddJsRef("TENGINE_CLIENT:tengine_client_selftests.js", true);
            
            $action->lay->eSet('HTML_LANG', str_replace('_', '-', getParam('CORE_LANG', 'fr_FR')));
            $action->lay->eSet('ACTIONNAME', strtoupper($action->name));
            return;
            break;

        case 'engines':
            $response = _getengines($action);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
            break;

        case 'selftest':
            $response = _selftest($action, $selftestid);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
            break;

        default:
            $err = sprintf(_("tengine_client:action:tengine_client_tasks:Unknown op '%s'.") , $op);
    }
}

function _getengines(Action & $action)
{
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        $response = array( 'success' => false, 'message' => $err, 'info' => null );
    } else {
        $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
        $err = $te->retrieveSelftests($selftests);
        if ($err != '') {
            $response = array( 'success' => false, 'message' => $err, 'info' => null );
        } else {
            $response = array( 'success' => true, 'message' => $err, 'info' => $selftests );
        }
    }
    return $response;
}

function _selftest(Action & $action, $selftestid)
{
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        return $response = array( 'success' => false, 'message' => $err, 'info' => null );
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $err = $te->executeSelftest($result, $selftestid);
    if ($err != '') {
        $response = array( 'success' => false, 'message' => $err, 'info' => null );
    } else {
        $response = array( 'success' => true, 'message' => $err, 'info' => $result );
    }
    return $response;
}

function _tasks(Action & $action, $select)
{
    $json = new JSONCodec();
    try {
        $args = $json->decode($select, true);
    }
    catch(Exception $e) {
        return sprintf(_("Malformed JSON argument: %s") , $e->getMessage());
    }
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        return $err;
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $err = $te->retrieveTasks($tasks, $args['start'], $args['length'], $args['orderby'], $args['sort'], $args['filter']);
    if ($err != '') {
        return $err;
    }
    $action->lay->eSet('RESPONSE', print_r($tasks, true));
    $action->lay->set('SHOW_TASKS', true);
}

function _histo(Action & $action, $tid)
{
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        return $err;
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $err = $te->retrieveTaskHisto($histo, $tid);
    if ($err != '') {
        return $err;
    }
    $action->lay->eSet('RESPONSE', print_r($histo, true));
    $action->lay->set('SHOW_HISTO', true);
    return '';
}

function _abort(Action & $action, $tid)
{
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        return $err;
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    return $te->eraseTransformation($tid);
}

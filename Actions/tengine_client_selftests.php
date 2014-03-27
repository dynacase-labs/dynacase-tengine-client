<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FDL
*/

function tengine_client_selftests(Action & $action)
{
    require_once "FDL/editutil.php";
    
    $usage = new ActionUsage($action);
    $op = $usage->addOptionalParameter('op', 'Operation');
    $selftestid = $usage->addOptionalParameter('selftestid', 'Selectest id to execute');
    $usage->verify(true);
    
    editmode($action);
    $action->parent->AddCssRef("css/dcp/jquery-ui.css");
    $action->parent->AddJsRef("lib/jquery-ui/js/jquery-ui.js");
    $action->parent->AddJsRef("TENGINE_CLIENT/Layout/tengine_client_selftests.js");
    
    $action->lay->eSet('HTML_LANG', str_replace('_', '-', getParam('CORE_LANG', 'fr_FR')));
    $action->lay->eSet('ACTIONNAME', strtoupper($action->name));
    $action->lay->set('SHOW_MAIN', false);
    $action->lay->set('SHOW_SELFTEST', false);
    
    switch ($op) {
        case '':
            $err = _main($action);
            break;

        case 'selftest':
            $err = _selftest($action, $selftestid);
            break;

        default:
            $err = sprintf(_("tengine_client:action:tengine_client_tasks:Unknown op '%s'.") , $op);
    }
    
    $action->lay->set('ERROR', ($err != ''));
    $action->lay->eSet('ERRMSG', $err);
}

function _main(Action & $action)
{
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        return $err;
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $err = $te->retrieveServerInfo($serverInfo, true);
    if ($err != '') {
        return $err;
    }
    $action->lay->eSet('SERVER_INFO', print_r($serverInfo, true));
    $err = $te->retrieveSelftests($selftests);
    if ($err != '') {
        return $err;
    }
    $action->lay->eSet('SELFTESTS', print_r($selftests, true));
    $action->lay->set('SHOW_MAIN', true);
    return '';
}

function _selftest(Action & $action, $selftestid)
{
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        return $err;
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $err = $te->executeSelftest($result, $selftestid);
    if ($err != '') {
        return $err;
    }
    $action->lay->eSet('RESPONSE', print_r($result, true));
    $action->lay->set('SHOW_SELFTEST', true);
    return '';
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

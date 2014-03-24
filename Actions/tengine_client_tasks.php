<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FDL
*/

function tengine_client_tasks(Action & $action)
{
    require_once "FDL/editutil.php";
    
    $usage = new ActionUsage($action);
    $op = $usage->addOptionalParameter('op', 'Operation');
    $select = $usage->addOptionalParameter('select', 'JSON encoded search/pagination arguments');
    $tid = $usage->addOptionalParameter('tid', 'Task id for task history retrieving');
    $usage->verify(true);
    
    editmode($action);
    $action->parent->AddCssRef("css/dcp/jquery-ui.css");
    $action->parent->AddJsRef("lib/jquery-ui/js/jquery-ui.js");
    $action->parent->AddJsRef("TENGINE_CLIENT/Layout/tengine_client_tasks.js");
    
    $action->lay->eSet('HTML_LANG', str_replace('_', '-', getParam('CORE_LANG', 'fr_FR')));
    $action->lay->eSet('ACTIONNAME', strtoupper($action->name));
    $action->lay->set('SHOW_MAIN', false);
    $action->lay->set('SHOW_RESPONSE', false);
    
    switch ($op) {
        case '':
            $err = _main($action);
            break;

        case 'tasks':
            $err = _tasks($action, $select);
            break;

        case 'histo':
            $err = _histo($action, $tid);
            break;

        case 'abort':
            $err = _abort($action, $tid);
            break;

        case 'purge':
            $err = _purge($action, $select);
            break;

        default:
            $err = sprintf(_("tengine_client:action:tengine_client_tasks:Unknown op '%s'.") , $op);
    }
    
    $action->lay->set('ERROR', ($err != ''));
    $action->lay->eSet('ERRMSG', $err);
}

function _main(Action & $action)
{
    $action->lay->set('SHOW_MAIN', true);
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
    $te = new \Dcp\TransformationEngine\Client();
    $err = $te->retrieveTasks($tasks, $args['start'], $args['length'], $args['orderby'], $args['sort'], $args['filter']);
    if ($err != '') {
        return $err;
    }
    $action->lay->eSet('RESPONSE', print_r($tasks, true));
    $action->lay->set('SHOW_RESPONSE', true);
}

function _histo(Action & $action, $tid)
{
    $te = new \Dcp\TransformationEngine\Client();
    $err = $te->retrieveTaskHisto($histo, $tid);
    if ($err != '') {
        return $err;
    }
    $action->lay->eSet('RESPONSE', print_r($histo, true));
    $action->lay->set('SHOW_RESPONSE', true);
    return '';
}

function _abort(Action & $action, $tid)
{
    $te = new \Dcp\TransformationEngine\Client();
    return $te->eraseTransformation($tid);
}

function _purge(Action & $action, $select)
{
    $json = new JSONCodec();
    try {
        $args = $json->decode($select, true);
    }
    catch(Exception $e) {
        return sprintf(_("Malformed JSON argument: %s") , $e->getMessage());
    }
    $maxdays = isset($args['maxdays']) ? $args['maxdays'] : '';
    $status = isset($args['status']) ? $args['status'] : '';
    $te = new \Dcp\TransformationEngine\Client();
    $err = $te->purgeTasks($maxdays, $status);
    if ($err != '') {
        return $err;
    }
    $action->lay->eSet('RESPONSE', 'Done.');
    $action->lay->set('SHOW_RESPONSE', true);
}

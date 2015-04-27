<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FDL
*/

require_once "FDL/editutil.php";

function tengine_client_params(Action & $action)
{
    $validate = getHttpVars('validate', null);
    if ($validate === null) {
        tengine_client_params_ui($action);
    } else {
        tengine_client_params_validate($action);
    }
}

function tengine_client_params_ui(Action & $action)
{
    $action->lay->eSet('HTML_LANG', str_replace('_', '-', getParam('CORE_LANG', 'fr_FR')));
    
    editmode($action);
    $action->parent->AddCssRef("css/dcp/jquery-ui.css");
    $action->parent->AddJsRef("lib/jquery-ui/js/jquery-ui.js");
    $action->parent->AddJsRef("TENGINE_CLIENT:tengine_client.js", true);
    $action->parent->AddJsRef("TENGINE_CLIENT:tengine_client_params.js", true);
    $action->parent->AddCssRef("TENGINE_CLIENT:tengine_client.css");
}

function tengine_client_params_validate(Action & $action)
{
    require_once "FDL/editutil.php";
    $validate = getHttpVars('validate', '');
    
    switch ($validate) {
        case 'testConnect':
            testConnect($action);
            break;

        case 'newTask':
            newTask($action);
            break;

        case 'waitForTaskDone':
            waitForTaskDone($action);
            break;

        case 'taskCallback':
            taskCallback($action);
            break;

        case 'waitForCallback':
            waitForCallback($action);
            break;

        case 'clearTask':
            clearTask($action);
            break;
    }
    error(sprintf("Unknown state '%s'.", $validate));
}
/**
 * @param Action $action
 * @return \Dcp\TransformationEngine\Client
 */
function newTeClient(Action & $action)
{
    return new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST") , $action->getParam("TE_PORT"));
}
/**
 * @param $response
 */
function reply($response)
{
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
/**
 * @param string $message
 * @param null $info
 * @param null $next
 */
function success($message = '', $info = null, $next = null)
{
    $response = array(
        'success' => true,
        'message' => $message,
        'info' => $info,
        'next' => $next
    );
    reply($response);
}
/**
 * @param string $message
 * @param null $info
 * @param null $next
 */
function error($message = '', $info = null, $next = null)
{
    $response = array(
        'success' => false,
        'message' => $message,
        'info' => $info,
        'next' => $next
    );
    reply($response);
}
/**
 * @param Action $action
 */
function testConnect(Action & $action)
{
    $info = array();
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        error($err);
    } else {
        $te = newTeClient($action);
        $err = $te->retrieveServerInfo($info, true);
        if ($err != '') {
            error($err);
        }
    }
    success(sprintf(_("tengine_client:Successfully connected to server (version %s release %s)") , $info['version'], $info['release']) , $info, 'newTask');
}
/**
 * @param Action $action
 */
function newTask(Action & $action)
{
    include_once 'FDL/Lib.Vault.php';
    
    $te = newTeClient($action);
    $info = array();
    $te_name = 'utf8';
    $fkey = '';
    $tmpFile = tempnam(getTmpDir() , '');
    if ($tmpFile === false) {
        error(_("tengine_client:Could not create temporary file."));
    }
    if (file_put_contents($tmpFile, 'hello world.') === false) {
        error(sprintf(_("tengine_client:Error writing content to temporary file '%s'") , $tmpFile));
    }
    $appName = $action->parent->name;
    $actionName = $action->name;
    $urlindex = getOpenTeUrl(array(
        'app' => $appName,
        'action' => $actionName,
        'validate' => 'taskCallback'
    ));
    $callback = sprintf("%s&app=%s&action=%s&validate=taskCallback", $urlindex, $appName, $actionName);
    
    $err = $te->sendTransformation($te_name, $fkey, $tmpFile, $callback, $info);
    if ($err != '') {
        unlink($tmpFile);
        error($err);
    }
    unlink($tmpFile);
    success(sprintf(_("tengine_client:Created new task with tid '%s'.") , $info['tid']) , $info, 'waitForTaskDone');
}
/**
 * @param Action $action
 */
function waitForTaskDone(Action & $action)
{
    $tid = getHttpVars('tid', '');
    if ($tid == '') {
        error(sprintf(_("tengine_client:Missing tid argument.")));
    }
    $te = newTeClient($action);
    $info = array();
    $err = $te->getInfo($tid, $info);
    if ($err != '') {
        error($err);
    }
    switch ($info['status']) {
        case \Dcp\TransformationEngine\Client::TASK_STATE_ERROR:
            error(sprintf(_("tengine_client:Task failed (status '%s').") , $info['status']) , $info);
            break;

        case \Dcp\TransformationEngine\Client::TASK_STATE_INTERRUPTED:
            error(sprintf(_("tengine_client:Task was interrupted (status '%s').") , $info['status']) , $info);
            break;

        case \Dcp\TransformationEngine\Client::TASK_STATE_SUCCESS:
            success(sprintf(_("tengine_client:Task completed (status '%s').") , $info['status']) , $info, 'waitForCallback');
    }
    success(sprintf(_("tengine_client:Pending task (status '%s').") , $info['status']) , $info, 'waitForTaskDone');
}
/**
 * @param Action $action
 */
function taskCallback(Action & $action)
{
    print 'CALLBACK:OK';
    exit;
}
/**
 * @param Action $action
 */
function waitForCallback(Action & $action)
{
    $tid = getHttpVars('tid', '');
    if ($tid == '') {
        error(sprintf(_("tengine_client:Missing tid argument.")));
    }
    $te = newTeClient($action);
    $info = array();
    $err = $te->getInfo($tid, $info);
    if ($err != '') {
        error($err);
    }
    if (isset($info['callreturn']) && $info['callreturn'] != '') {
        if ($info['callreturn'] == 'CALLBACK:OK') {
            success(sprintf(_("tengine_client:Callback success.")) , $info, 'clearTask');
        } else {
            error(sprintf(_("tengine_client:Callback failed with message: %s") , $info['callreturn']));
        }
    }
    success(sprintf(_("tengine_client:Task's status is '%s' with comment '%s'.") , $info['status'], $info['comment']) , $info, 'waitForCallback');
}
/**
 * @param Action $action
 */
function clearTask(Action & $action)
{
    $tid = getHttpVars('tid', '');
    if ($tid == '') {
        error(sprintf(_("tengine_client:Missing tid argument.")));
    }
    $te = newTeClient($action);
    $err = $te->purgeTransformation($tid);
    if ($err != '') {
        error($err);
    }
    success(sprintf(_("tengine_client:Cleared task with tid '%s'.") , $tid));
}

<?php
/*
 * @author Anakeen
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

function tengine_client_params_valid(Action & $action)
{
    TeTesting::taskCallback($action);
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
    $validate = getHttpVars('validate', '');
    
    ini_set('display_errors', '0');
    
    switch ($validate) {
        case 'testConnect':
            TeTesting::testConnect($action);
            break;

        case 'newTask':
            TeTesting::newTask($action);
            break;

        case 'waitForTaskDone':
            TeTesting::waitForTaskDone($action);
            break;

        case 'taskCallback':
            TeTesting::taskCallback($action);
            break;

        case 'waitForCallback':
            TeTesting::waitForCallback($action);
            break;

        case 'clearTask':
            TeTesting::clearTask($action);
            break;
    }
    TeTesting::error(sprintf("Unknown state '%s'.", $validate));
}

class TeTesting
{
    /**
     * @param Action $action
     * @return \Dcp\TransformationEngine\Client
     */
    public static function newTeClient(Action & $action)
    {
        return new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST") , $action->getParam("TE_PORT"));
    }
    /**
     * @param $response
     */
    public static function reply($response)
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
    public static function success($message = '', $info = null, $next = null)
    {
        $response = array(
            'success' => true,
            'message' => $message,
            'info' => $info,
            'next' => $next
        );
        TeTesting::reply($response);
    }
    /**
     * @param string $message
     * @param null $info
     * @param null $next
     */
    public static function error($message = '', $info = null, $next = null)
    {
        $response = array(
            'success' => false,
            'message' => $message,
            'info' => $info,
            'next' => $next
        );
        TeTesting::reply($response);
    }
    /**
     * @param Action $action
     */
    public static function testConnect(Action & $action)
    {
        $info = array();
        $err = \Dcp\TransformationEngine\Manager::checkParameters();
        if ($err != '') {
            TeTesting::error($err);
        } else {
            $te = TeTesting::newTeClient($action);
            $err = $te->retrieveServerInfo($info, true);
            if ($err != '') {
                TeTesting::error($err);
            }
        }
        TeTesting::success(sprintf(_("tengine_client:Successfully connected to server (version %s release %s)") , $info['version'], $info['release']) , $info, 'newTask');
    }
    /**
     * @param Action $action
     */
    public static function newTask(Action & $action)
    {
        include_once 'FDL/Lib.Vault.php';
        
        $te = TeTesting::newTeClient($action);
        $info = array();
        $te_name = 'utf8';
        $fkey = '';
        $tmpFile = tempnam(getTmpDir() , '');
        if ($tmpFile === false) {
            TeTesting::error(_("tengine_client:Could not create temporary file."));
        }
        if (file_put_contents($tmpFile, 'hello world.') === false) {
            TeTesting::error(sprintf(_("tengine_client:Error writing content to temporary file '%s'") , $tmpFile));
        }
        $appName = $action->parent->name;
        $actionName = "TENGINE_CLIENT_PARAMS_VALID";
        $urlindex = getOpenTeUrl(array(
            'app' => $appName,
            'action' => $actionName, // Must be open access mode
            'validate' => 'taskCallback'
        ));
        $callback = sprintf("%s&app=%s&action=%s&validate=taskCallback", $urlindex, $appName, $actionName);
        
        $err = $te->sendTransformation($te_name, $fkey, $tmpFile, $callback, $info);
        if ($err != '') {
            unlink($tmpFile);
            TeTesting::error($err);
        }
        unlink($tmpFile);
        TeTesting::success(sprintf(_("tengine_client:Created new task with tid '%s'.") , $info['tid']) , $info, 'waitForTaskDone');
    }
    /**
     * @param Action $action
     */
    public static function waitForTaskDone(Action & $action)
    {
        $tid = getHttpVars('tid', '');
        if ($tid == '') {
            TeTesting::error(sprintf(_("tengine_client:Missing tid argument.")));
        }
        $te = TeTesting::newTeClient($action);
        $info = array();
        $err = $te->getInfo($tid, $info);
        if ($err != '') {
            TeTesting::error($err);
        }
        switch ($info['status']) {
            case \Dcp\TransformationEngine\Client::TASK_STATE_ERROR:
                TeTesting::error(sprintf(_("tengine_client:Task failed (status '%s').") , $info['status']) , $info);
                break;

            case \Dcp\TransformationEngine\Client::TASK_STATE_INTERRUPTED:
                TeTesting::error(sprintf(_("tengine_client:Task was interrupted (status '%s').") , $info['status']) , $info);
                break;

            case \Dcp\TransformationEngine\Client::TASK_STATE_SUCCESS:
                TeTesting::success(sprintf(_("tengine_client:Task completed (status '%s').") , $info['status']) , $info, 'waitForCallback');
        }
        TeTesting::success(sprintf(_("tengine_client:Pending task (status '%s').") , $info['status']) , $info, 'waitForTaskDone');
    }
    /**
     * @param Action $action
     */
    public static function taskCallback(Action & $action)
    {
        print 'CALLBACK:OK';
        exit;
    }
    /**
     * @param Action $action
     */
    public static function waitForCallback(Action & $action)
    {
        $tid = getHttpVars('tid', '');
        if ($tid == '') {
            TeTesting::error(sprintf(_("tengine_client:Missing tid argument.")));
        }
        $te = TeTesting::newTeClient($action);
        $info = array();
        $err = $te->getInfo($tid, $info);
        if ($err != '') {
            TeTesting::error($err);
        }
        if (isset($info['callreturn']) && $info['callreturn'] != '') {
            if ($info['callreturn'] == 'CALLBACK:OK') {
                TeTesting::success(sprintf(_("tengine_client:Callback success.")) , $info, 'clearTask');
            } else {
                TeTesting::error(sprintf(_("tengine_client:Callback failed with message: %s") , $info['callreturn']));
            }
        }
        TeTesting::success(sprintf(_("tengine_client:Task's status is '%s' with comment '%s'.") , $info['status'], $info['comment']) , $info, 'waitForCallback');
    }
    /**
     * @param Action $action
     */
    public static function clearTask(Action & $action)
    {
        $tid = getHttpVars('tid', '');
        if ($tid == '') {
            TeTesting::error(sprintf(_("tengine_client:Missing tid argument.")));
        }
        $te = TeTesting::newTeClient($action);
        $err = $te->purgeTransformation($tid);
        if ($err != '') {
            TeTesting::error($err);
        }
        TeTesting::success(sprintf(_("tengine_client:Cleared task with tid '%s'.") , $tid));
    }
}

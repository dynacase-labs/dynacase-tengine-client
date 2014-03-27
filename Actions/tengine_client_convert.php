<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FDL
*/

function tengine_client_convert(Action & $action)
{
    require_once "FDL/editutil.php";
    
    $usage = new ActionUsage($action);
    $op = $usage->addOptionalPArameter('op', 'Operation');
    $engine = $usage->addOptionalParameter('engine', 'Conversion engine name');
    $file = $usage->addOptionalParameter('file', 'File to convert');
    $tid = $usage->addOptionalParameter('tid', 'Task id');
    $usage->verify(true);
    
    editmode($action);
    $action->parent->AddCssRef("css/dcp/jquery-ui.css");
    $action->parent->AddJsRef("lib/jquery-ui/js/jquery-ui.js");
    $action->parent->AddJsRef("TENGINE_CLIENT/Layout/tengine_client_convert.js");
    
    $action->lay->eSet('HTML_LANG', str_replace('_', '-', getParam('CORE_LANG', 'fr_FR')));
    $action->lay->eSet('ACTIONNAME', strtoupper($action->name));
    $action->lay->set('SHOW_MAIN', false);
    $action->lay->set('SHOW_TASK', false);
    $action->lay->set('SHOW_DOWNLOAD', false);
    $action->lay->set('SHOW_KILLED', false);
    
    switch ($op) {
        case '':
            $err = _main($action);
            break;

        case 'convert':
            $err = _convert($action, $file, $engine);
            break;

        case 'info':
            $err = _info($action, $tid);
            break;

        case 'get':
            $err = _get($action, $tid);
            break;

        case 'abort':
            $err = _abort($action, $tid);
            break;

        default:
            $err = sprintf(_("tengine_client:action:tengine_client_convert:Unknown op '%s'.") , $op);
    }
    
    $action->lay->set('ERROR', ($err != ''));
    $action->lay->eSet('ERRMSG', $err);
}

function _uploadErrorConst($errorCode)
{
    foreach (get_defined_constants() as $const => $code) {
        if (strpos($const, 'UPLOAD_ERR_') === 0) {
            return $const;
        }
    }
    return $errorCode;
}

function _main(Action & $action)
{
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        return $err;
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $err = $te->retrieveEngines($engines);
    if ($err != '') {
        return $err;
    }
    $enginesList = array();
    foreach ($engines as & $engine) {
        $enginesList[$engine['name']] = 1;
    }
    $enginesList = array_keys($enginesList);
    array_walk($enginesList, function (&$el)
    {
        $el = array(
            'ENGINE_NAME' => $el
        );
    });
    $action->lay->eSetBlockData('ENGINES', $enginesList);
    $action->lay->set('SHOW_MAIN', true);
    return '';
}

function _convert(Action & $action, $filename, $engineName)
{
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        return $err;
    }
    global $_FILES;
    if (!isset($_FILES['file'])) {
        return sprintf(_("tengine_client:action:tengine_client_convert:Missing file."));
    }
    if (isset($_FILES['file']['error']) && $_FILES['file']['error'] != UPLOAD_ERR_OK) {
        return sprintf(_("tengine_client:action:tengine_client_convert:Error in file upload: %s") , _uploadErrorConst($_FILES['file']['error']));
    }
    if (($tmpfile = tempnam(getTmpDir() , '_convert_')) === false) {
        return sprintf(_("tengine_client:action:tengine_client_convert:Error creating temporary file."));
    }
    if (move_uploaded_file($_FILES['file']['tmp_name'], $tmpfile) === false) {
        return sprintf(_("tengine_client:action:tengine_client_convert:Error moving uploaded file to '%s'.") , getTmpDir());
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $taskInfo = array();
    $err = $te->sendTransformation($engineName, basename($tmpfile) , $tmpfile, '', $taskInfo);
    $action->lay->eSet('TID', $taskInfo['tid']);
    $action->lay->eSet('TASK', print_r($taskInfo, true));
    $action->lay->set('SHOW_TASK', true);
    return $err;
}

function _info(Action & $action, $tid)
{
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        return $err;
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $info = array();
    $err = $te->getInfo($tid, $info);
    if ($err != '') {
        return sprintf(_("tengine_client:action:tengine_client_convert:Error fetching task info with id '%s': %s") , $tid, $err);
    }
    $action->lay->eSet('TASK', print_r($info, true));
    switch ($info['status']) {
        case 'K':
            $te->eraseTransformation($tid);
            $action->lay->set('SHOW_KILLED', true);
            break;

        case 'D':
            $action->lay->set('SHOW_DOWNLOAD', true);
            break;

        default:
            $action->lay->set('SHOW_TASK', true);
    }
    $action->lay->eSet('TID', $tid);
    return '';
}

function _get(Action & $action, $tid)
{
    require_once ('WHAT/Lib.FileMime.php');
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        return $err;
    }    
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $info = array();
    $err = $te->getInfo($tid, $info);
    if ($err != '') {
        return sprintf(_("tengine_client:action:tengine_client_convert:Error fetching task info with id '%s': %s", $tid, $err));
    }
    if ($info['status'] !== 'D') {
        $this->lay->set('SHOW_TASK', $tid);
        $this->lay->set('TID', $tid);
        return sprintf(_("tengine_client:action:tengine_client_convert:Task '%s' is not finished.") , $tid);
    }
    $tmpfile = tempnam(getTmpDir() , '_get_');
    if ($tmpfile === false) {
        return sprintf(_("tengine_client:action:tengine_client_convert:Error creating temporary file."));
    }
    $te->getTransformation($tid, $tmpfile);
    if (filesize($tmpfile) <= 0) {
        unlink($tmpfile);
        return sprintf(_("tengine_client:action:tengine_client_convert:File is empty."));
    }
    
    $mimeType = getSysMimeFile($tmpfile);
    $ext = getExtension($mimeType);
    if ($ext == '') {
        $ext = 'bin';
    }
    Http_DownloadFile($tmpfile, sprintf('convert.%s', $ext) , $mimeType, false, true, true);
    exit();
}

function _abort(Action & $action, $tid)
{
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        return $err;
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $err = $te->eraseTransformation($tid);
    $action->lay->set('SHOW_MAIN', true);
    return $err;
}
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
    $action->parent->AddCssRef("TENGINE_CLIENT/Layout/tengine_client.css");
   
    $action->lay->eSet('HTML_LANG', str_replace('_', '-', getParam('CORE_LANG', 'fr_FR')));
    $action->lay->eSet('ACTIONNAME', strtoupper($action->name));
    
    switch ($op) {
        case '':
            $err = _main($action);
            $action->lay->set('ERROR', ($err != ''));
            $action->lay->eSet('ERRMSG', $err);
            break;

        case 'convert':
            $response = _convert($action, $file, $engine);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
            break;

        case 'info':
            $response = _info($action, $tid);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
           break;

        case 'get':
            $response = _get($action, $tid);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
            break;

        default:
            $err = sprintf(_("tengine_client:action:tengine_client_convert:Unknown op '%s'.") , $op);
    }
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
    $response = array( "success" => true, "message" => "", "info" => null); 
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        $response['success'] = false;
        $response['message'] = $err;
        return $response;
    }
    global $_FILES;
    if (!isset($_FILES['file'])) {
        $response['success'] = false;
        $response['message'] = sprintf(_("tengine_client:action:tengine_client_convert:Missing file."));
        return $response;
    }
    if (isset($_FILES['file']['error']) && $_FILES['file']['error'] != UPLOAD_ERR_OK) {
        $response['success'] = false;
        $response['message'] = 
            sprintf(
                _("tengine_client:action:tengine_client_convert:Error in file upload: %s") , 
                _uploadErrorConst($_FILES['file']['error']));
        return $response;
    }
    if (($tmpfile = tempnam(getTmpDir() , '_convert_')) === false) {
        $response['success'] = false;
        $response['message'] = sprintf(_("tengine_client:action:tengine_client_convert:Error creating temporary file."));
        return $response;
    }
    if (move_uploaded_file($_FILES['file']['tmp_name'], $tmpfile) === false) {
        $response['success'] = false;
        $response['message'] = sprintf(_("tengine_client:action:tengine_client_convert:Error moving uploaded file to '%s'.") , getTmpDir());
        return $response;
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $taskInfo = array();
    $err = $te->sendTransformation($engineName, basename($tmpfile) , $tmpfile, '', $taskInfo);
    $response['success'] = ($err != "" ? false : true);
    $response['message'] = $err;
    $response['info'] = $taskInfo;
    return $response;
}

function _info(Action & $action, $tid)
{
    $response = array( "success" => true, "message" => "", "info" => null); 
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        $response['success'] = false;
        $response['message'] = $err;
        return $response;
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $info = array();
    $err = $te->getInfo($tid, $info);
    if ($err != '') {
        $response['success'] = false;
        $response['message'] = sprintf(_("tengine_client:action:tengine_client_convert:Error fetching task info with id '%s': %s") , $tid, $err);
        return $response;
    }
    $response['info'] = $info;
    return $response;
}

function _get(Action & $action, $tid)
{
    $response = array( "success" => true, "message" => "", "info" => null); 
    require_once ('WHAT/Lib.FileMime.php');
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        $response['success'] = false;
        $response['message'] = $err;
        return $response;
    }    
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST"), $action->getParam("TE_PORT"));
    $info = array();
    $err = $te->getInfo($tid, $info);
    if ($err != '') {
        $response['success'] = false;
        $response['message'] = sprintf(_("tengine_client:action:tengine_client_convert:Error fetching task info with id '%s': %s"), $tid, $err);
        return $response;
    }
    if ($info['status'] !== 'D') {
        $response['success'] = false;
        $response['message'] = sprintf(_("tengine_client:action:tengine_client_convert:Task '%s' is not finished.") , $tid);
        return $response;
    }
    $tmpfile = tempnam(getTmpDir() , '_get_');
    if ($tmpfile === false) {
        $response['success'] = false;
        $response['message'] = sprintf(_("tengine_client:action:tengine_client_convert:Error creating temporary file."));
        return $response;
    }
    $te->getTransformation($tid, $tmpfile);
    if (filesize($tmpfile) <= 0) {
        unlink($tmpfile);
        $response['success'] = false;
        $response['message'] = sprintf(_("tengine_client:action:tengine_client_convert:File is empty."));
        return $response;
    }
    
    $mimeType = getSysMimeFile($tmpfile);
    $ext = getExtension($mimeType);
    if ($ext == '') {
        $ext = 'bin';
    }
    Http_DownloadFile($tmpfile, sprintf('convert.%s', $ext) , $mimeType, false, true, true);
    exit;
}


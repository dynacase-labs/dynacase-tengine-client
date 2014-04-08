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
    //    $usage->verify(true);
    editmode($action);
    
    switch ($op) {
        case '':
            
            $action->parent->AddJsRef("lib/jquery-dataTables/js/jquery.dataTables.js");
            $action->parent->AddCssRef("lib/jquery-dataTables/css/jquery.dataTables.css");
            
            $action->parent->AddCssRef("TENGINE_CLIENT:tengine_client.css");
            $action->parent->AddCssRef("TENGINE_CLIENT:tengine_client_tasks.css", true);
            $action->parent->AddJsRef("TENGINE_CLIENT:tengine_client_tasks.js", true);
            
            $action->lay->eSet('HTML_LANG', str_replace('_', '-', getParam('CORE_LANG', 'fr_FR')));
            $action->lay->eSet('ACTIONNAME', strtoupper($action->name));
            $action->lay->set('COMMENT', false);
            
            break;

        case 'tasks':
            $response = _tasks($action);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
            break;

        case 'histo':
            $tid = $usage->addOptionalParameter('tid', 'Task id for task history retrieving');
            $response = _histo($action, $tid);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
            break;

        case 'abort':
            $tid = $usage->addOptionalParameter('tid', 'Task id for task aborting');
            $response = _abort($action, $tid);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
            break;

        default:
            $err = sprintf(_("tengine_client:action:tengine_client_tasks:Unknown op '%s'.") , $op);
    }
}

function _tasks(Action & $action)
{
    $doctmp = new Doc();
    
    $sEcho = $action->getArgument('sEcho', 42);
    $iDisplayStart = $action->getArgument('iDisplayStart', 0);
    $iDisplayLength = $action->getArgument('iDisplayLength', 50);
    
    $response = array(
        'success' => false,
        'message' => '(init)',
        'sEcho' => $sEcho,
        'iTotalRecords' => 0,
        'iTotalDisplayRecords' => 0,
        'aaData' => array()
    );
    
    $err = \Dcp\TransformationEngine\Manager::isAccessible();
    if ($err) {
        $response['message'] = $err;
        return $response;
    }
    /*
      Sort return
    */
    $sortCol = "cdate";
    $sortDir = "desc";
    $iSortingCols = $action->getArgument('iSortingCols');
    if ($iSortingCols > 0) {
        $sortDir = $action->getArgument('sSortDir_0');
        $sCol = $action->getArgument('iSortCol_0');
        $sCol = $action->getArgument('iSortCol_0');
        if ($action->getArgument('mDataProp_' . $sCol, '') != '') {
            $sortCol = $action->getArgument('mDataProp_' . $sCol);
            if ($sortCol == "statuslabel") $sortCol = "status";
        }
    }
    
    $sDatas = array();
    $iColumns = $action->getArgument('iColumns', 0);
    for ($ic = 0; $ic < $iColumns; $ic++) {
        if ($action->getArgument('sSearch_' . $ic) != '') {
            $attr = $action->getArgument('mDataProp_' . $ic);
            if ($attr == 'statuslabel') $attr = 'status';
            $sDatas[$attr] = $action->getArgument('sSearch_' . $ic);
            error_log("SDATA []" . $attr . " : " . $action->getArgument('sSearch_' . $ic) . "]");
        }
    }
    
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST") , $action->getParam("TE_PORT"));
    $err = $te->retrieveTasks($tasks, $iDisplayStart, $iDisplayLength, $sortCol, $sortDir, $sDatas);
    if ($err != '') {
        $response['success'] = false;
        $response['message'] = $err;
        return $response;
    }
    
    $response = array(
        'success' => true,
        'message' => '',
        'sEcho' => $sEcho,
        'iTotalRecords' => $tasks['count_all'],
        'iTotalDisplayRecords' => $tasks['count_filter'],
        'aaData' => $tasks['tasks']
    );
    
    if (isset($tasks['tasks']) && is_array($tasks['tasks'])) {
        // Retrieve dynacase info for tasks
        $dynacaseDatas = $dynacaseTmpDatas = array();
        $tids = "";
        foreach ($tasks['tasks'] as $k => $v) $tids.= ($tids != "" ? ", " : "") . "'" . $tasks['tasks'][$k]['tid'] . "'";
        
        $dynacaseFilter = false;
        $dynacaseField = ['docid' => 'docread.id', 'doctitle' => 'docread.title', 'owner' => 'taskrequest.uname', 'filename' => 'vaultdiskstorage.name'];
        $uSearch = "";
        foreach ($sDatas as $k => $v) {
            if (isset($dynacaseField[$k])) {
                if ($k == 'doctitle') {
                    $uSearch.= sprintf(" AND ((%s::text ~* '%s') OR (%s::text ~* '%s'))", $dynacaseField[$k], $v, $dynacaseField['docid'], $v);
                } else {
                    $uSearch.= sprintf(" AND ( %s::text ~* '%s')", $dynacaseField[$k], $v);
                }
                $dynacaseFilter = true;
            }
        }
        
        $query = "       SELECT docread.id as docid, 
                                docread.title as doctitle, 
                                taskrequest.uname as owner, 
                                taskrequest.tid as tid, 
                                vaultdiskstorage.name as filename
                           FROM docread, docvaultindex, taskrequest, vaultdiskstorage 
                          WHERE taskrequest.fkey = vaultdiskstorage.id_file::text 
                            AND docvaultindex.vaultid = vaultdiskstorage.id_file
                            AND docread.id= docvaultindex.docid
                            AND taskrequest.tid in (" . $tids . ") 
                              " . $uSearch . " ;";
        //error_log(sprintf("Q.DYN [%s]",str_replace("\n","",$query)));
        simpleQuery('', $query, $dynacaseTmpDatas, false, false);
        
        if (!empty($dynacaseTmpDatas) && is_array($dynacaseTmpDatas)) {
            foreach ($dynacaseTmpDatas as $document) {
                $dynacaseDatas[$document['tid']] = $document;
            }
        }
        
        foreach ($tasks['tasks'] as $k => $v) {
            
            $s = '';
            switch ($v['status']) {
                case \Dcp\TransformationEngine\Client::TASK_STATE_BEGINNING:
                    $s = _('TE:Status:Begin');
                    break;

                case \Dcp\TransformationEngine\Client::TASK_STATE_TRANSFERRING:
                    $s = _('TE:Status:Transferring');
                    break;

                case \Dcp\TransformationEngine\Client::TASK_STATE_ERROR:
                    $s = _('TE:Status:Error');
                    break;

                case \Dcp\TransformationEngine\Client::TASK_STATE_SUCCESS:
                    $s = _('TE:Status:Success');
                    break;

                case \Dcp\TransformationEngine\Client::TASK_STATE_RECOVERED:
                    $s = _('TE:Status:Recovered');
                    break;

                case \Dcp\TransformationEngine\Client::TASK_STATE_PROCESSING:
                    $s = _('TE:Status:Processing');
                    break;

                case \Dcp\TransformationEngine\Client::TASK_WAITING:
                    $s = _('TE:Status:Waiting');
                    break;

                case \Dcp\TransformationEngine\Client::TASK_STATE_INTERRUPTED:
                    $s = _('TE:Status:Interrupted');
                    break;

                default:
                    $s = sprintf(_('TE:Status:Unknown [%s]') , $v['status']);
                    break;
            }
            $tasks['tasks'][$k]['statuslabel'] = $s;
            $tasks['tasks'][$k]['cdate'] = substr($tasks['tasks'][$k]['cdate'], 0, 19);
            unset($tasks['tasks'][$k]['comment']);
            
            $tid = $tasks['tasks'][$k]['tid'];
            if (isset($dynacaseDatas[$tid])) {
                $tasks['tasks'][$k]['owner'] = $dynacaseDatas[$tid]['owner'];
                $tasks['tasks'][$k]['doctitle'] = $doctmp->getDocAnchor($dynacaseDatas[$tid]['docid'], 'te-doclink-open', true, '', false, 'fixed', true);
                $tasks['tasks'][$k]['docid'] = $dynacaseDatas[$tid]['docid'];
                $tasks['tasks'][$k]['filename'] = $dynacaseDatas[$tid]['filename'];
            } else {
                if (!$dynacaseFilter) {
                    $tasks['tasks'][$k]['owner'] = '';
                    $tasks['tasks'][$k]['doctitle'] = '';
                    $tasks['tasks'][$k]['docid'] = '';
                    $tasks['tasks'][$k]['filename'] = '';
                } else {
                    unset($tasks['tasks'][$k]);
                }
            }
        }
        if ($dynacaseFilter) $tasks['count_filter'] = count($tasks['tasks']);
        $response = array(
            'success' => true,
            'message' => '',
            'sEcho' => $sEcho,
            'iTotalRecords' => $tasks['count_all'],
            'iTotalDisplayRecords' => $tasks['count_filter'], //count($tasks['tasks']),
            'aaData' => array_values($tasks['tasks'])
        );
    }
    return $response;
}

function _histo(Action & $action, $tid)
{
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        $response['success'] = false;
        $response['message'] = $err;
    } else {
        $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST") , $action->getParam("TE_PORT"));
        $err = $te->retrieveTaskHisto($histo, $tid);
        if ($err != '') {
            $response['success'] = false;
            $response['message'] = $err;
        } else {
            $response['success'] = true;
            $response['message'] = '';
            $response['data'] = $histo;
        }
    }
    return $response;
}

function _abort(Action & $action, $tid)
{
    $response = array(
        'success' => false,
        'message' => '(init)'
    );
    $err = \Dcp\TransformationEngine\Manager::checkParameters();
    if ($err != '') {
        $response['success'] = false;
        $response['message'] = $err;
        return $response;
    }
    $te = new \Dcp\TransformationEngine\Client($action->getParam("TE_HOST") , $action->getParam("TE_PORT"));
    $err = $te->eraseTransformation($tid);
    if ($err != '') {
        $response['success'] = false;
        $response['message'] = $err;
        return $response;
    }
    $response['success'] = true;
    $response['message'] = '';
    return $response;
}


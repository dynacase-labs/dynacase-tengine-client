<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FDL
 */

require_once "FDL/editutil.php";

function tengine_client_params(Action & $action)
{
    $action->lay->eSet('HTML_LANG', str_replace('_', '-', getParam('CORE_LANG', 'fr_FR')));
    
    editmode($action);
    $action->parent->AddCssRef("css/dcp/jquery-ui.css");
    $action->parent->AddJsRef("lib/jquery-ui/js/jquery-ui.js");
    $action->parent->AddJsRef("TENGINE_CLIENT/Layout/tengine_client_params.js");
}

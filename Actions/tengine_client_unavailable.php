<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FDL
*/

function tengine_client_unavailable(Action & $action)
{
    $action->parent->AddCssRef("TENGINE_CLIENT:tengine_client.css");
}
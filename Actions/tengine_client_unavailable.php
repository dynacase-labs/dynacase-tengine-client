<?php
/*
 * @author Anakeen
 * @package FDL
*/

function tengine_client_unavailable(Action & $action)
{
    $action->parent->AddCssRef("TENGINE_CLIENT:tengine_client.css");
}
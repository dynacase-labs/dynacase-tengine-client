<?php

function tengine_client_params(Action & $action) {
    $action->lay->eSet('HTML_LANG', str_replace('_', '-', getParam('CORE_LANG', 'fr_FR')));
}
#!/bin/bash

if [ -z "$WIFF_ROOT" ]; then
    echo "Error: undefined WIFF_ROOT"
    exit 1
fi

if [ -z "$WIFF_CONTEXT_ROOT" ]; then
    echo "Error: undefined WIFF_CONTEXT_ROOT"
    exit 1
fi

pgservice_core=$("$WIFF_ROOT/wiff" --getValue=core_db)
if [ -z "$pgservice_core" ]; then
    echo "Error: undefined or empty CORE_DB"
    exit 1
fi

function _save {
    PGSERVICE=$pgservice_core psql --set ON_ERROR_STOP=on -f - <<'EOF'
BEGIN;

DROP TABLE IF EXISTS tengine_client_migrate_parameters;

SELECT paramv.* INTO tengine_client_migrate_parameters FROM paramv, application
    WHERE paramv.appid = application.id
        AND application.name = 'FDL'
        AND paramv.name IN ('TE_HOST', 'TE_PORT', 'TE_ACTIVATE', 'TE_URLINDEX', 'TE_TIMEOUT', 'TE_FULLTEXT');

COMMIT;
EOF
    if [ $? -ne 0 ]; then
        echo "Error: could not save FDL:TE_* parameters"
        exit 1
    fi
}

function _restore {
    PGSERVICE=$pgservice_core psql --set ON_ERROR_STOP=on -f - <<'EOF'
BEGIN;

UPDATE paramv SET appid = newapp.id, val = oldparamv.val
    FROM tengine_client_migrate_parameters AS oldparamv, application AS oldapp,  application AS newapp
    WHERE paramv.name IN ('TE_HOST', 'TE_PORT', 'TE_ACTIVATE', 'TE_URLINDEX', 'TE_TIMEOUT', 'TE_FULLTEXT')
        AND oldparamv.appid = oldapp.id AND paramv.name = oldparamv.name
        AND paramv.appid = oldapp.id AND oldapp.name = 'FDL' AND newapp.name = 'TENGINE_CLIENT';

UPDATE paramdef SET appid = newapp.id
    FROM application AS oldapp, application AS newapp
    WHERE paramdef.name IN ('TE_HOST', 'TE_PORT', 'TE_ACTIVATE', 'TE_URLINDEX', 'TE_TIMEOUT', 'TE_FULLTEXT')
        AND paramdef.appid = oldapp.id AND oldapp.name = 'FDL' AND newapp.name = 'TENGINE_CLIENT';
DROP TABLE tengine_client_migrate_parameters;

COMMIT;
EOF
    if [ $? -ne 0 ]; then
        echo "Error: could not restore TENGINE_CLIENT:TE_* parameters"
        exit 1
    fi
}

function _main {
    case "$1" in
        save)
            _save
            ;;
        restore)
            _restore
            ;;
        *)
            echo "Error: unknown operation '$1'!"
            exit 1
    esac
}

_main "$@"
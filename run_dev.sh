#!/usr/bin/env bash

PORT=${PORT:-8000}

kill_port() {
    case "$(uname -s)" in
        MINGW*|MSYS*|CYGWIN*)
            # netstat reports the PID in the last column on Windows.
            for pid in $(netstat.exe -ano -p tcp 2>/dev/null | \
                awk -v port=":$PORT" '$2 ~ port && $4 == "LISTENING" { print $5 }' | sort -u); do
                taskkill.exe //PID "$pid" //F >/dev/null 2>&1 || true
            done
            ;;
        *)
            if command -v lsof >/dev/null 2>&1; then
                for pid in $(lsof -tiTCP:"$PORT" -sTCP:LISTEN 2>/dev/null | sort -u); do
                    kill "$pid" 2>/dev/null || true
                done
            else
                printf 'Warning: lsof is not available; port %s was not cleared.\n' "$PORT" >&2
            fi
            ;;
    esac
}

kill_port
cd "$(dirname "$0")" || exit 1
php -S 127.0.0.1:"$PORT" -t . src/cms/router.php

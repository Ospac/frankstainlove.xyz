#!/usr/bin/env bash

set -euo pipefail

REPOSITORY="Ospac/lichen-markdown-custom"
INSTALL_ROOT="${1:-$(cd "$(dirname "$0")" && pwd)}"
REQUESTED_VERSION="${2:-}"
TEMP_DIR=""
BACKUP_DIR=""
PREVIOUS_SRC=""
SWAPPED_SRC=0
COMMITTED=0

die() {
    printf 'Error: %s\n' "$1" >&2
    exit 1
}

cleanup() {
    if [ -n "$TEMP_DIR" ]; then
        rm -rf "$TEMP_DIR"
    fi
}

rollback() {
    if [ "$COMMITTED" -eq 1 ]; then
        return
    fi

    printf '\nUpdate failed. Restoring the previous installation...\n' >&2

    if [ "$SWAPPED_SRC" -eq 1 ] && [ -n "$PREVIOUS_SRC" ] && [ -d "$PREVIOUS_SRC" ]; then
        rm -rf "$INSTALL_ROOT/src"
        mv "$PREVIOUS_SRC" "$INSTALL_ROOT/src"
    fi

    if [ -n "$BACKUP_DIR" ]; then
        if [ -f "$BACKUP_DIR/VERSION" ]; then
            cp "$BACKUP_DIR/VERSION" "$INSTALL_ROOT/VERSION"
        fi
        if [ -d "$BACKUP_DIR/dist" ]; then
            rm -rf "$INSTALL_ROOT/dist"
            cp -a "$BACKUP_DIR/dist" "$INSTALL_ROOT/dist"
        fi
    fi
}

on_exit() {
    status=$?
    if [ "$status" -ne 0 ]; then
        rollback || true
    fi
    cleanup
    exit "$status"
}

trap on_exit EXIT

command -v curl >/dev/null 2>&1 || die "curl is required"
command -v tar >/dev/null 2>&1 || die "tar is required"
command -v php >/dev/null 2>&1 || die "PHP is required"

[ -d "$INSTALL_ROOT" ] || die "Installation folder does not exist: $INSTALL_ROOT"
[ -d "$INSTALL_ROOT/src/cms" ] || die "Missing core folder: $INSTALL_ROOT/src/cms"
[ -d "$INSTALL_ROOT/src/layout" ] || die "Missing core folder: $INSTALL_ROOT/src/layout"
[ -d "$INSTALL_ROOT/page" ] || die "Missing content folder: $INSTALL_ROOT/page"
[ -w "$INSTALL_ROOT" ] || die "Installation folder is not writable: $INSTALL_ROOT"

INSTALL_ROOT=$(cd "$INSTALL_ROOT" && pwd)

CURRENT_VERSION="unknown"
if [ -f "$INSTALL_ROOT/VERSION" ]; then
    CURRENT_VERSION=$(tr -d '[:space:]' < "$INSTALL_ROOT/VERSION")
fi

normalize_version() {
    printf '%s' "${1#v}"
}

valid_version() {
    [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]
}

version_greater_than() {
    local left right left_part right_part
    local left_major left_minor left_patch right_major right_minor right_patch
    left=$(normalize_version "$1")
    right=$(normalize_version "$2")
    IFS=. read -r left_major left_minor left_patch <<< "$left"
    IFS=. read -r right_major right_minor right_patch <<< "$right"

    if [ "$left_major" -ne "$right_major" ]; then
        [ "$left_major" -gt "$right_major" ]
    elif [ "$left_minor" -ne "$right_minor" ]; then
        [ "$left_minor" -gt "$right_minor" ]
    else
        [ "$left_patch" -gt "$right_patch" ]
    fi
}

if [ -z "$REQUESTED_VERSION" ]; then
    REQUESTED_VERSION=$(curl --fail --silent --show-error --location \
        "https://api.github.com/repos/$REPOSITORY/releases/latest" \
        | sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' \
        | head -n 1)
fi

TARGET_VERSION=$(normalize_version "$REQUESTED_VERSION")
valid_version "$TARGET_VERSION" || die "Invalid target version: $REQUESTED_VERSION"
if [ "$CURRENT_VERSION" != "unknown" ]; then
    CURRENT_VERSION=$(normalize_version "$CURRENT_VERSION")
    valid_version "$CURRENT_VERSION" || die "Invalid current VERSION: $CURRENT_VERSION"
    if [ "$CURRENT_VERSION" = "$TARGET_VERSION" ]; then
        printf 'Already running version %s.\n' "$CURRENT_VERSION"
        exit 0
    fi
    version_greater_than "$TARGET_VERSION" "$CURRENT_VERSION" \
        || die "Target version $TARGET_VERSION is not newer than $CURRENT_VERSION"
fi

TAG="v$TARGET_VERSION"
PACKAGE_NAME="lichen-markdown-custom-$TAG.tar.gz"
DOWNLOAD_BASE="https://github.com/$REPOSITORY/releases/download/$TAG"
TIMESTAMP=$(date '+%Y%m%d-%H%M%S')
TEMP_DIR="$INSTALL_ROOT/.update-temp-$TIMESTAMP-$$"
ARCHIVE="$TEMP_DIR/$PACKAGE_NAME"
CHECKSUM="$ARCHIVE.sha256"
EXTRACTED="$TEMP_DIR/extracted"

mkdir -p "$EXTRACTED"
printf 'Downloading %s...\n' "$TAG"
curl --fail --silent --show-error --location \
    "$DOWNLOAD_BASE/$PACKAGE_NAME" --output "$ARCHIVE"
curl --fail --silent --show-error --location \
    "$DOWNLOAD_BASE/$PACKAGE_NAME.sha256" --output "$CHECKSUM"

if command -v sha256sum >/dev/null 2>&1; then
    (cd "$TEMP_DIR" && sha256sum --check "$(basename "$CHECKSUM")")
else
    (cd "$TEMP_DIR" && shasum -a 256 --check "$(basename "$CHECKSUM")")
fi

if tar -tzf "$ARCHIVE" | grep -E '(^/|(^|/)\.\.(\/|$))' >/dev/null; then
    die "Archive contains an unsafe path"
fi

tar -xzf "$ARCHIVE" -C "$EXTRACTED"
[ -d "$EXTRACTED/src/cms" ] || die "Release is missing src/cms"
[ -d "$EXTRACTED/src/layout" ] || die "Release is missing src/layout"
[ -f "$EXTRACTED/VERSION" ] || die "Release is missing VERSION"

PACKAGE_VERSION=$(tr -d '[:space:]' < "$EXTRACTED/VERSION")
[ "$PACKAGE_VERSION" = "$TARGET_VERSION" ] \
    || die "Release VERSION ($PACKAGE_VERSION) does not match tag ($TARGET_VERSION)"

find "$EXTRACTED/src" -type f -name '*.php' -exec php -l {} \; >/dev/null

BACKUP_DIR="$INSTALL_ROOT/.update-backups/${CURRENT_VERSION:-unknown}-$TIMESTAMP"
mkdir -p "$BACKUP_DIR"
cp -a "$INSTALL_ROOT/src" "$BACKUP_DIR/src"
[ -d "$INSTALL_ROOT/dist" ] && cp -a "$INSTALL_ROOT/dist" "$BACKUP_DIR/dist"
[ -f "$INSTALL_ROOT/VERSION" ] && cp "$INSTALL_ROOT/VERSION" "$BACKUP_DIR/VERSION"
for file in run_build.sh run_dev.sh update.sh; do
    [ -f "$INSTALL_ROOT/$file" ] && cp "$INSTALL_ROOT/$file" "$BACKUP_DIR/$file"
done

printf 'Updating core from %s to %s...\n' "${CURRENT_VERSION:-unknown}" "$TARGET_VERSION"
PREVIOUS_SRC="$INSTALL_ROOT/.src-previous-$$"
cp -a "$EXTRACTED/src" "$INSTALL_ROOT/.src-candidate-$$"
mv "$INSTALL_ROOT/src" "$PREVIOUS_SRC"
SWAPPED_SRC=1
mv "$INSTALL_ROOT/.src-candidate-$$" "$INSTALL_ROOT/src"

if ! php "$INSTALL_ROOT/src/cms/build.php"; then
    die "Build failed"
fi

cp "$EXTRACTED/VERSION" "$INSTALL_ROOT/VERSION"
for file in run_build.sh run_dev.sh update.sh; do
    [ -f "$EXTRACTED/$file" ] && cp "$EXTRACTED/$file" "$INSTALL_ROOT/$file"
done

rm -rf "$PREVIOUS_SRC"
PREVIOUS_SRC=""
SWAPPED_SRC=0
COMMITTED=1

printf '\nUpdated lichen-markdown-custom to %s.\n' "$TARGET_VERSION"
printf 'Backup: %s\n' "$BACKUP_DIR"

#!/bin/bash
set -euo pipefail

VER=""

while getopts ":v:" opt; do
    case $opt in
        v) VER="$OPTARG"
        ;;
        \?) echo "Invalid option -$OPTARG" >&2
        exit 1
        ;;
        :) echo "Option -$OPTARG requires an argument." >&2
        exit 1
        ;;
    esac
done

progress() {
    local percent="$1"
    shift

    printf '__BL_PROGRESS__|%s|%s\n' "$percent" "$*"
}

log() {
    printf '%s\n' "$*"
}

require_command() {
    local command_name="$1"

    if command -v "$command_name" > /dev/null 2>&1; then
        return
    fi

    log "Required command is not available: $command_name"
    exit 1
}

require_php_extension() {
    local extension_name="$1"

    if php -r "exit(extension_loaded('$extension_name') ? 0 : 1);" > /dev/null 2>&1; then
        return
    fi

    log "Missing required PHP extension for the updater: $extension_name"
    log "Enable it for the CLI SAPI used by this script before retrying."
    exit 1
}

copy_file_if_exists() {
    local source="$1"
    local destination="$2"

    if [[ -f "$source" ]]; then
        cp -v "$source" "$destination"
        return
    fi

    log "Skipping missing file: $source"
}

copy_dir_contents_if_exists() {
    local source_dir="$1"
    local destination_dir="$2"

    mkdir -p "$destination_dir"

    if [[ -d "$source_dir" ]] && compgen -G "$source_dir/*" > /dev/null; then
        cp -rv "$source_dir"/. "$destination_dir"/
        return
    fi

    log "Skipping empty or missing directory: $source_dir"
}

run_yarn() {
    if command -v yarn > /dev/null 2>&1; then
        yarn "$@"
        return
    fi

    if command -v corepack > /dev/null 2>&1; then
        corepack yarn "$@"
        return
    fi

    log "Neither yarn nor corepack is available."
    exit 1
}

has_prebuilt_frontend_assets() {
    [[ -f "$RELEASE_DIR/public/build/entrypoints.json" && -f "$RELEASE_DIR/public/build/manifest.json" ]]
}

has_bundled_php_dependencies() {
    [[ -f "$RELEASE_DIR/vendor/autoload.php" ]]
}

install_frontend_dependencies() {
    if command -v yarn > /dev/null 2>&1 || command -v corepack > /dev/null 2>&1; then
        run_yarn install --frozen-lockfile
        return
    fi

    if command -v npm > /dev/null 2>&1; then
        if [[ -f package-lock.json ]]; then
            npm ci
            return
        fi

        npm install
        return
    fi

    log "No supported JavaScript package manager is available."
    exit 1
}

if [[ -z $VER ]]; then
    echo "No version provided via the -v argument"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PROJECT_PARENT_DIR="$(dirname "$PROJECT_DIR")"
RELEASE_DIR="$PROJECT_PARENT_DIR/BikeLog-$VER"
ARCHIVE_FILE="$PROJECT_PARENT_DIR/$VER.tar.gz"
BACKUP_FILE="$PROJECT_DIR/BikeLog_backup_before_$VER.tar.gz"
RELEASE_ASSET_URL="https://github.com/gztproject/BikeLog/releases/download/$VER/BikeLog-$VER-build.tar.gz"
SOURCE_ARCHIVE_URL="https://github.com/gztproject/BikeLog/archive/refs/tags/$VER.tar.gz"

cd "$PROJECT_DIR"

progress 5 "Preparing update workspace."
log "Updating BikeLog to $VER"

progress 10 "Checking update requirements."
require_command tar
require_command wget
require_command php
require_php_extension xml
require_php_extension simplexml

progress 15 "Creating a backup of the current installation."
tar -czf "$BACKUP_FILE" --exclude="./$(basename "$BACKUP_FILE")" .

progress 25 "Downloading the desired release."
if wget -O "$ARCHIVE_FILE" "$RELEASE_ASSET_URL"; then
    log "Using packaged release artifact: $RELEASE_ASSET_URL"
else
    log "Packaged release artifact is not available, falling back to the source archive."
    rm -f "$ARCHIVE_FILE"
    wget -O "$ARCHIVE_FILE" "$SOURCE_ARCHIVE_URL"
fi

progress 35 "Extracting the release archive."
rm -rf "$RELEASE_DIR"
tar -xf "$ARCHIVE_FILE" -C "$PROJECT_PARENT_DIR"
rm "$ARCHIVE_FILE"

if [[ ! -d "$RELEASE_DIR" ]]; then
    log "Expected release directory was not created: $RELEASE_DIR"
    exit 1
fi

progress 45 "Copying environment files into the new release."
copy_file_if_exists "$PROJECT_DIR/.env" "$RELEASE_DIR/.env"
copy_file_if_exists "$PROJECT_DIR/.env.local" "$RELEASE_DIR/.env.local"

progress 55 "Copying runtime data and user uploads."
copy_dir_contents_if_exists "$PROJECT_DIR/var/log" "$RELEASE_DIR/var/log"
copy_dir_contents_if_exists "$PROJECT_DIR/public/uploads" "$RELEASE_DIR/public/uploads"

cd "$RELEASE_DIR"
if has_bundled_php_dependencies; then
    progress 65 "Using PHP dependencies bundled with the release."
else
    progress 65 "Installing PHP dependencies."
    require_command composer
    composer clear-cache
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
fi

if has_prebuilt_frontend_assets; then
    progress 77 "Using frontend assets bundled with the release."
else
    progress 77 "Installing JavaScript dependencies."
    install_frontend_dependencies

    progress 87 "Building frontend assets."
    ./node_modules/.bin/encore production --progress
fi

progress 93 "Replacing application files."
cd "$PROJECT_DIR"
find "$PROJECT_DIR" -mindepth 1 -maxdepth 1 ! -name "$(basename "$BACKUP_FILE")" -exec rm -rf {} +
cp -a "$RELEASE_DIR"/. "$PROJECT_DIR"/
rm -rf "$RELEASE_DIR"

progress 97 "Running database migrations."
php bin/console doctrine:migrations:migrate -n

progress 99 "Clearing application cache."
php bin/console cache:clear

progress 100 "Update completed."
log "BikeLog $VER is now installed."

exit 0

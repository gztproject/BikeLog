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

if [[ -z $VER ]]; then
    echo "No version provided via the -v argument"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
RELEASE_DIR="$PROJECT_DIR/BikeLog-$VER"
ARCHIVE_FILE="$PROJECT_DIR/$VER.tar.gz"
BACKUP_FILE="$PROJECT_DIR/BikeLog_backup_before_$VER.tar.gz"

cd "$PROJECT_DIR"

progress 5 "Preparing update workspace."
log "Updating BikeLog to $VER"

progress 10 "Creating a backup of the current installation."
tar -czf "$BACKUP_FILE" --exclude="$(basename "$BACKUP_FILE")" *

progress 20 "Downloading the desired release."
wget -O "$ARCHIVE_FILE" "https://github.com/gztproject/BikeLog/archive/refs/tags/$VER.tar.gz"

progress 30 "Extracting the release archive."
tar -xf "$ARCHIVE_FILE"
rm "$ARCHIVE_FILE"

if [[ ! -d "$RELEASE_DIR" ]]; then
    log "Expected release directory was not created: $RELEASE_DIR"
    exit 1
fi

progress 40 "Copying environment files into the new release."
cp -v "$BACKUP_FILE" "$RELEASE_DIR/"
copy_file_if_exists "$PROJECT_DIR/.env" "$RELEASE_DIR/.env"
copy_file_if_exists "$PROJECT_DIR/.env.local" "$RELEASE_DIR/.env.local"

progress 50 "Copying runtime data and user uploads."
copy_dir_contents_if_exists "$PROJECT_DIR/var/log" "$RELEASE_DIR/var/log"
copy_dir_contents_if_exists "$PROJECT_DIR/public/uploads" "$RELEASE_DIR/public/uploads"

progress 60 "Replacing application files."
GLOBIGNORE=BikeLog-*
rm -rf *
unset GLOBIGNORE

cp -r "$RELEASE_DIR"/. .
rm -rf "$RELEASE_DIR"

progress 72 "Installing PHP dependencies."
composer clear-cache
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

progress 84 "Installing JavaScript dependencies."
run_yarn install --frozen-lockfile

progress 92 "Building frontend assets."
./node_modules/.bin/encore production --progress

progress 96 "Running database migrations."
php bin/console doctrine:migrations:migrate -n

progress 99 "Clearing application cache."
php bin/console cache:clear

progress 100 "Update completed."
log "BikeLog $VER is now installed."

exit 0

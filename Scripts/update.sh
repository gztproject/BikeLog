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

write_state() {
    {
        printf 'LAST_COMPLETED_STEP=%q\n' "$LAST_COMPLETED_STEP"
    } > "$STATE_FILE"
}

load_state() {
    if [[ ! -f "$STATE_FILE" ]]; then
        LAST_COMPLETED_STEP=0
        return
    fi

    # shellcheck disable=SC1090
    source "$STATE_FILE"
    LAST_COMPLETED_STEP="${LAST_COMPLETED_STEP:-0}"
}

normalize_state() {
    if (( LAST_COMPLETED_STEP >= 25 && LAST_COMPLETED_STEP < 35 )); then
        log "Saved update state points to an unverified download; restarting from the download step."
        LAST_COMPLETED_STEP=15
        write_state
        return
    fi

    if (( LAST_COMPLETED_STEP >= 35 && LAST_COMPLETED_STEP < 93 )) && [[ ! -d "$RELEASE_DIR" ]]; then
        log "Saved update state points to a missing extracted release; restarting from the download step."
        LAST_COMPLETED_STEP=15
        write_state
    fi
}

mark_step() {
    LAST_COMPLETED_STEP="$1"
    write_state
}

should_run_step() {
    local step="$1"
    (( LAST_COMPLETED_STEP < step ))
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

cleanup_download_dir() {
    if [[ -n "${DOWNLOAD_DIR:-}" && -d "$DOWNLOAD_DIR" ]]; then
        rm -rf "$DOWNLOAD_DIR"
    fi
}

# The archive is opened twice and then unlinked, so signature verification and
# extraction both read the same downloaded inode and nothing can swap the file
# behind its path between the two.
download_verify_and_extract_release() {
    local archive signature extract_dir

    DOWNLOAD_DIR="$(mktemp -d "$PROJECT_PARENT_DIR/.BikeLog-download-XXXXXXXX")"
    trap cleanup_download_dir EXIT
    archive="$DOWNLOAD_DIR/release.tar.gz"
    signature="$DOWNLOAD_DIR/release.tar.gz.sig"
    extract_dir="$DOWNLOAD_DIR/extract"

    if ! wget -O "$archive" "$RELEASE_ASSET_URL"; then
        log "Release artifact is not available: $RELEASE_ASSET_URL"
        exit 1
    fi
    log "Downloaded release artifact: $RELEASE_ASSET_URL"

    if ! wget -O "$signature" "$RELEASE_SIGNATURE_URL"; then
        log "Release signature is not available: $RELEASE_SIGNATURE_URL"
        exit 1
    fi

    exec 3< "$archive" 4< "$archive"
    rm -f "$archive"

    progress 30 "Verifying the release signature."
    if [[ ! -f "$RELEASE_PUBLIC_KEY" ]]; then
        exec 3<&- 4<&-
        log "Release signing key is missing: $RELEASE_PUBLIC_KEY"
        log "Refusing to install an update that cannot be verified."
        exit 1
    fi

    if ! openssl dgst -sha256 -verify "$RELEASE_PUBLIC_KEY" -signature "$signature" <&3 > /dev/null; then
        exec 3<&- 4<&-
        log "Release signature verification failed; refusing to install $VER."
        exit 1
    fi
    exec 3<&-
    log "Release signature verified with $RELEASE_PUBLIC_KEY."

    progress 35 "Extracting the release archive."
    mkdir "$extract_dir"
    tar -xzf - -C "$extract_dir" <&4
    exec 4<&-

    if [[ ! -d "$extract_dir/BikeLog-$VER" || -L "$extract_dir/BikeLog-$VER" ]]; then
        log "Release archive does not contain the expected BikeLog-$VER directory."
        exit 1
    fi

    rm -rf "$RELEASE_DIR"
    mv "$extract_dir/BikeLog-$VER" "$RELEASE_DIR"
    cleanup_download_dir
    trap - EXIT
}

resume_notice() {
    if (( LAST_COMPLETED_STEP > 0 && LAST_COMPLETED_STEP < 100 )); then
        log "Resuming interrupted update from step $LAST_COMPLETED_STEP."
    fi
}

if [[ -z $VER ]]; then
    echo "No version provided via the -v argument"
    exit 1
fi

if [[ ! $VER =~ ^v?[0-9]+\.[0-9]+\.[0-9]+([-+][0-9A-Za-z.-]+)?$ ]]; then
    echo "Invalid version: $VER"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CURRENT_SCRIPT_PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PROJECT_DIR="${BIKELOG_UPDATE_PROJECT_DIR:-$CURRENT_SCRIPT_PROJECT_DIR}"
PROJECT_PARENT_DIR="$(dirname "$PROJECT_DIR")"
RELEASE_DIR="$PROJECT_PARENT_DIR/BikeLog-$VER"
BACKUP_FILE="$PROJECT_PARENT_DIR/BikeLog_backup_before_$VER.tar.gz"
STATE_FILE="${BIKELOG_UPDATE_STATE_FILE:-$PROJECT_PARENT_DIR/.BikeLog-update-$VER.state}"
RELEASE_ASSET_URL="https://github.com/gztproject/BikeLog/releases/download/$VER/BikeLog-$VER-build.tar.gz"
RELEASE_SIGNATURE_URL="$RELEASE_ASSET_URL.sig"
RELEASE_PUBLIC_KEY="$PROJECT_DIR/config/update-release.pub"
DOWNLOAD_DIR=""

load_state
normalize_state

cd "$PROJECT_DIR"

progress 5 "Preparing update workspace."
log "Updating BikeLog to $VER"
resume_notice

if should_run_step 10; then
    progress 10 "Checking update requirements."
    require_command tar
    require_command wget
    require_command openssl
    require_command php
    require_php_extension xml
    require_php_extension simplexml
    mark_step 10
fi

if should_run_step 15; then
    progress 15 "Creating a backup of the current installation."
    tar -czf "$BACKUP_FILE" .
    mark_step 15
fi

if should_run_step 35; then
    progress 25 "Downloading the desired release."
    download_verify_and_extract_release
    mark_step 35
fi

if should_run_step 45; then
    progress 45 "Copying environment files into the new release."
    copy_file_if_exists "$PROJECT_DIR/.env" "$RELEASE_DIR/.env"
    copy_file_if_exists "$PROJECT_DIR/.env.local" "$RELEASE_DIR/.env.local"
    mark_step 45
fi

if should_run_step 55; then
    progress 55 "Copying runtime data and user uploads."
    copy_dir_contents_if_exists "$PROJECT_DIR/var/log" "$RELEASE_DIR/var/log"
    copy_dir_contents_if_exists "$PROJECT_DIR/public/uploads" "$RELEASE_DIR/public/uploads"
    mark_step 55
fi

cd "$RELEASE_DIR"

if should_run_step 65; then
    if has_bundled_php_dependencies; then
        progress 65 "Using PHP dependencies bundled with the release."
    else
        progress 65 "Installing PHP dependencies."
        require_command composer
        composer clear-cache
        composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    fi
    mark_step 65
fi

if has_prebuilt_frontend_assets; then
    if should_run_step 77; then
        progress 77 "Using frontend assets bundled with the release."
        mark_step 77
    fi

    if should_run_step 87; then
        mark_step 87
    fi
else
    if should_run_step 77; then
        progress 77 "Installing JavaScript dependencies."
        install_frontend_dependencies
        mark_step 77
    fi

    if should_run_step 87; then
        progress 87 "Building frontend assets."
        ./node_modules/.bin/encore production --progress
        mark_step 87
    fi
fi

if should_run_step 93; then
    progress 93 "Replacing application files."
    cd "$PROJECT_DIR"
    find "$PROJECT_DIR" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
    cp -a "$RELEASE_DIR"/. "$PROJECT_DIR"/
    rm -rf "$RELEASE_DIR"
    mark_step 93
fi

if should_run_step 97; then
    progress 97 "Running database migrations."
    cd "$PROJECT_DIR"
    php bin/console doctrine:migrations:migrate -n
    mark_step 97
fi

if should_run_step 99; then
    progress 99 "Clearing application cache."
    cd "$PROJECT_DIR"
    php bin/console cache:clear
    mark_step 99
fi

progress 100 "Update completed."
log "BikeLog $VER is now installed."
rm -f "$STATE_FILE"

exit 0

function normalizeVersion(version) {
    return String(version ?? '')
        .trim()
        .replace(/^v/i, '')
        .split('-')[0];
}

function parseVersion(version) {
    const normalized = normalizeVersion(version);

    if (!normalized) {
        return [0, 0, 0];
    }

    return normalized
        .split('.')
        .map((part) => Number.parseInt(part, 10))
        .map((part) => (Number.isFinite(part) ? part : 0));
}

function compareVersions(currentVersion, nextVersion) {
    const currentParts = parseVersion(currentVersion);
    const nextParts = parseVersion(nextVersion);
    const maxLength = Math.max(currentParts.length, nextParts.length);

    for (let index = 0; index < maxLength; index += 1) {
        const currentPart = currentParts[index] ?? 0;
        const nextPart = nextParts[index] ?? 0;

        if (currentPart === nextPart) {
            continue;
        }

        return currentPart < nextPart ? -1 : 1;
    }

    return 0;
}

function isNewerVersion(currentVersion, nextVersion) {
    return compareVersions(currentVersion, nextVersion) < 0;
}

module.exports = {
    compareVersions,
    isNewerVersion,
    normalizeVersion,
    parseVersion,
};

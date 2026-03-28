const test = require('node:test');
const assert = require('node:assert/strict');

const {
    compareVersions,
    isNewerVersion,
    normalizeVersion,
    parseVersion,
} = require('../../../../assets/js/common/update/version');

test('normalizeVersion strips leading v and prerelease suffixes', () => {
    assert.equal(normalizeVersion('v1.2.3-beta1'), '1.2.3');
});

test('parseVersion falls back to zeros for invalid or missing versions', () => {
    assert.deepEqual(parseVersion(''), [0, 0, 0]);
    assert.deepEqual(parseVersion('1.invalid.4'), [1, 0, 4]);
});

test('compareVersions respects major, minor and patch ordering', () => {
    assert.equal(compareVersions('1.9.9', '2.0.0'), -1);
    assert.equal(compareVersions('2.10.0', '2.2.0'), 1);
    assert.equal(compareVersions('v1.2.3', '1.2.3-beta'), 0);
});

test('isNewerVersion only returns true for a strictly newer release', () => {
    assert.equal(isNewerVersion('2.0.0', '1.9.9'), false);
    assert.equal(isNewerVersion('1.4.9', '1.5.0'), true);
});

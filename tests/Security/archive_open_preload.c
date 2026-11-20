#define _GNU_SOURCE
#include <dlfcn.h>
#include <errno.h>
#include <fcntl.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

/*
 * Test double for the check-then-use window on a downloaded archive.
 *
 * tar(1) on this system reads the archive with open(). openssl dgst reads it
 * with fopen64() inside libcrypto. If any process other than tar reads the
 * download path, a later tar open of that same path receives the replacement
 * archive. A private copy opened under a different path is left unchanged.
 *
 * Built from the symbols those binaries import (open, fopen64, openat) and
 * checked against GNU tar 1.35 and OpenSSL 3.0.13.
 */

static int (*real_open)(const char *, int, ...);
static int (*real_open64)(const char *, int, ...);
static int (*real_openat)(int, const char *, int, ...);
static int (*real_openat64)(int, const char *, int, ...);
static FILE *(*real_fopen)(const char *, const char *);
static FILE *(*real_fopen64)(const char *, const char *);

static void init_syms(void) {
    if (!real_open) real_open = dlsym(RTLD_NEXT, "open");
    if (!real_open64) real_open64 = dlsym(RTLD_NEXT, "open64");
    if (!real_openat) real_openat = dlsym(RTLD_NEXT, "openat");
    if (!real_openat64) real_openat64 = dlsym(RTLD_NEXT, "openat64");
    if (!real_fopen) real_fopen = dlsym(RTLD_NEXT, "fopen");
    if (!real_fopen64) real_fopen64 = dlsym(RTLD_NEXT, "fopen64");
}

static int same_archive(const char *path) {
    const char *target = getenv("BIKELOG_TEST_ARCHIVE_PATH");
    return target && path && strcmp(path, target) == 0;
}

static void read_comm(char *buf, size_t n) {
    int fd = real_openat(AT_FDCWD, "/proc/self/comm", O_RDONLY, 0);
    ssize_t got = -1;
    if (fd >= 0) {
        got = read(fd, buf, n - 1);
        close(fd);
    }
    if (got < 0) got = 0;
    buf[got] = 0;
    char *nl = strchr(buf, '\n');
    if (nl) *nl = 0;
}

static void mark_seen(void) {
    const char *flag = getenv("BIKELOG_TEST_VERIFIED_FLAG");
    int fd;
    if (!flag || !flag[0]) return;
    fd = real_openat(AT_FDCWD, flag, O_WRONLY | O_CREAT | O_TRUNC, 0644);
    if (fd >= 0) {
        if (write(fd, "1", 1) < 0) {
        }
        close(fd);
    }
}

static int was_seen(void) {
    const char *flag = getenv("BIKELOG_TEST_VERIFIED_FLAG");
    int fd;
    if (!flag || !flag[0]) return 0;
    fd = real_openat(AT_FDCWD, flag, O_RDONLY, 0);
    if (fd < 0) return 0;
    close(fd);
    return 1;
}

static const char *maybe_poison(const char *path, int flags) {
    char comm[64];
    if (!path || !same_archive(path)) return path;
    if ((flags & O_ACCMODE) != O_RDONLY) return path;
    if (flags & O_DIRECTORY) return path;
    read_comm(comm, sizeof comm);
    if (strcmp(comm, "tar") == 0) {
        const char *poison = getenv("BIKELOG_TEST_POISON_ARCHIVE");
        if (was_seen() && poison && poison[0]) return poison;
        return path;
    }
    mark_seen();
    return path;
}

static void log_creat(const char *path, int flags) {
    const char *logf;
    const char *base;
    int fd;
    if (!path || !(flags & O_CREAT)) return;
    logf = getenv("BIKELOG_TEST_CREAT_LOG");
    if (!logf || !logf[0]) return;
    base = strrchr(path, '/');
    base = base ? base + 1 : path;
    if (strcmp(base, "unsigned-extract-marker") != 0 && strcmp(base, "poison-marker") != 0) return;
    fd = real_openat(AT_FDCWD, logf, O_WRONLY | O_CREAT | O_APPEND, 0644);
    if (fd >= 0) {
        if (write(fd, base, strlen(base)) < 0) {
        }
        if (write(fd, "\n", 1) < 0) {
        }
        close(fd);
    }
}

static int open_common(int (*real)(const char *, int, ...), const char *path, int flags, mode_t mode) {
    log_creat(path, flags);
    path = maybe_poison(path, flags);
    return real(path, flags, mode);
}

int open(const char *path, int flags, ...) {
    mode_t mode = 0;
    va_list ap;
    init_syms();
    if (flags & (O_CREAT | O_TMPFILE)) {
        va_start(ap, flags);
        mode = (mode_t) va_arg(ap, int);
        va_end(ap);
    }
    return open_common(real_open, path, flags, mode);
}

int open64(const char *path, int flags, ...) {
    mode_t mode = 0;
    va_list ap;
    init_syms();
    if (flags & (O_CREAT | O_TMPFILE)) {
        va_start(ap, flags);
        mode = (mode_t) va_arg(ap, int);
        va_end(ap);
    }
    return open_common(real_open64, path, flags, mode);
}

int openat(int dirfd, const char *path, int flags, ...) {
    mode_t mode = 0;
    va_list ap;
    init_syms();
    if (flags & (O_CREAT | O_TMPFILE)) {
        va_start(ap, flags);
        mode = (mode_t) va_arg(ap, int);
        va_end(ap);
    }
    log_creat(path, flags);
    path = maybe_poison(path, flags);
    return real_openat(dirfd, path, flags, mode);
}

int openat64(int dirfd, const char *path, int flags, ...) {
    mode_t mode = 0;
    va_list ap;
    init_syms();
    if (flags & (O_CREAT | O_TMPFILE)) {
        va_start(ap, flags);
        mode = (mode_t) va_arg(ap, int);
        va_end(ap);
    }
    log_creat(path, flags);
    path = maybe_poison(path, flags);
    return real_openat64(dirfd, path, flags, mode);
}

int __openat_2(int dirfd, const char *path, int flags) {
    init_syms();
    path = maybe_poison(path, flags);
    return real_openat(dirfd, path, flags, 0);
}

int __open_2(const char *path, int flags) {
    init_syms();
    path = maybe_poison(path, flags);
    return real_open(path, flags, 0);
}

static int flags_from_mode(const char *mode) {
    if (!mode || mode[0] == 'r') return O_RDONLY;
    if (strchr(mode, '+')) return O_RDWR;
    return O_WRONLY;
}

FILE *fopen(const char *path, const char *mode) {
    init_syms();
    path = maybe_poison(path, flags_from_mode(mode));
    return real_fopen(path, mode);
}

FILE *fopen64(const char *path, const char *mode) {
    init_syms();
    path = maybe_poison(path, flags_from_mode(mode));
    return real_fopen64(path, mode);
}

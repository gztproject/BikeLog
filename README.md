# BikeLog

[![CI](https://github.com/gztproject/BikeLog/actions/workflows/php.yml/badge.svg)](https://github.com/gztproject/BikeLog/actions/workflows/php.yml)

A web based motorcycle maintenance logging software

Tagged releases also publish a deployable `BikeLog-<version>-build.tar.gz` GitHub release asset that includes production PHP dependencies and compiled frontend assets for the updater.

The updater only installs an archive whose detached `BikeLog-<version>-build.tar.gz.sig` signature (`openssl dgst -sha256 -sign`) verifies against the public key at `config/update-release.pub` in the running installation; it refuses the update when that key is missing. The release workflow signs archives with the private key stored in the `UPDATE_RELEASE_SIGNING_KEY` repository secret, which must match the committed `config/update-release.pub`.

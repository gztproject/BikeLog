# BikeLog

[![CI](https://github.com/gztproject/BikeLog/actions/workflows/php.yml/badge.svg)](https://github.com/gztproject/BikeLog/actions/workflows/php.yml)

A web based motorcycle maintenance logging software

## Updates

Production installs should be git clones with `origin` reaching GitHub. Tagging a SemVer release (`X.Y.Z` or `X.Y.Z-rc.N`) runs CI and creates a GitHub Release. Admins can check and apply updates from **Administrator → Updates**, which force-checkouts the selected tag, reinstalls PHP/JS dependencies, runs migrations, and clears the cache via `Update.sh`. The web user needs `git`, `php`, `composer`, `npm`, and `node` on `PATH`.

Keep production settings in `.env.local` (untracked). A force-checkout resets tracked files such as `.env`.

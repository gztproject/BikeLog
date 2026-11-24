/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import '../css/app.css';

// Need jQuery? Install it with "yarn add jquery", then uncomment to import it.
import $ from 'jquery';

// this "modifies" the jquery module: adding behavior to it
// the bootstrap module doesn't export/return anything
require('bootstrap');
require('./common/theme');

require('@fortawesome/fontawesome-free/css/all.min.css');
require('@fortawesome/fontawesome-free/js/all.js');

require('@mdi/font/css/materialdesignicons.min.css');

(function initUpdateBadge() {
    const badgeLink = document.querySelector('.js-update-badge');
    if (!badgeLink) {
        return;
    }

    const statusUrl = badgeLink.getAttribute('data-status-url');
    const dot = badgeLink.querySelector('.js-update-badge-dot');
    if (!statusUrl || !dot) {
        return;
    }

    fetch(statusUrl, { headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : null))
        .then((data) => {
            if (data && data.updateAvailable) {
                dot.hidden = false;
            }
        })
        .catch(() => {
            // Badge is optional; failures stay silent.
        });
})();

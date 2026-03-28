const STORAGE_KEY = 'bikelog-theme';
const VALID_PREFERENCES = ['light', 'dark', 'system'];
const mediaQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

function getStoredPreference() {
    try {
        const storedPreference = window.localStorage.getItem(STORAGE_KEY) || 'system';

        return VALID_PREFERENCES.includes(storedPreference) ? storedPreference : 'system';
    } catch (error) {
        return 'system';
    }
}

function persistPreference(preference) {
    try {
        if (preference === 'system') {
            window.localStorage.removeItem(STORAGE_KEY);
            return;
        }

        window.localStorage.setItem(STORAGE_KEY, preference);
    } catch (error) {
        // Ignore storage issues and keep the preference only for the current page.
    }
}

function resolveTheme(preference) {
    if (preference === 'system') {
        return mediaQuery && mediaQuery.matches ? 'dark' : 'light';
    }

    return preference;
}

function updateThemeButtons(preference) {
    document.querySelectorAll('[data-theme-switcher] [data-theme-mode]').forEach((button) => {
        const isActive = button.getAttribute('data-theme-mode') === preference;

        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

function applyTheme(preference) {
    const resolvedTheme = resolveTheme(preference);

    document.documentElement.dataset.themePreference = preference;
    document.documentElement.dataset.theme = resolvedTheme;
    updateThemeButtons(preference);
}

function initializeThemeSwitcher() {
    const preference = getStoredPreference();

    applyTheme(preference);

    document.querySelectorAll('[data-theme-switcher] [data-theme-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextPreference = button.getAttribute('data-theme-mode');

            if (!VALID_PREFERENCES.includes(nextPreference)) {
                return;
            }

            persistPreference(nextPreference);
            applyTheme(nextPreference);
        });
    });

    if (!mediaQuery) {
        return;
    }

    const onSystemThemeChanged = () => {
        if (getStoredPreference() === 'system') {
            applyTheme('system');
        }
    };

    if (typeof mediaQuery.addEventListener === 'function') {
        mediaQuery.addEventListener('change', onSystemThemeChanged);
        return;
    }

    if (typeof mediaQuery.addListener === 'function') {
        mediaQuery.addListener(onSystemThemeChanged);
    }
}

document.addEventListener('DOMContentLoaded', initializeThemeSwitcher);

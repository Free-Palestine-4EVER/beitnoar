let cachedVersion = null;

/**
 * Fetch the current menu version from the static version.json file.
 * Uses cache: 'no-cache' to ensure we always check freshness.
 *
 * @returns {Promise<string|null>} The version string or null on failure
 */
async function getMenuVersion() {
    try {
        const response = await fetch('/generated/menu/version.json', {
            cache: 'no-cache',
        });
        if (!response.ok) return null;
        const data = await response.json();
        return data.version || null;
    } catch {
        return null;
    }
}

/**
 * Fetch the full menu data from the static menu.json file.
 * Uses the version string as a cache buster to ensure fresh data
 * while still allowing the service worker to cache effectively.
 *
 * @returns {Promise<Array>} The menu categories array
 */
export async function fetchMenu() {
    const version = await getMenuVersion();
    const cacheBuster = version ? `?v=${version}` : `?v=${Date.now()}`;

    const response = await fetch(`/generated/menu/menu.json${cacheBuster}`, {
        headers: {
            'Accept': 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error('Failed to load menu: ' + response.statusText);
    }

    const json = await response.json();
    cachedVersion = version;

    return json.categories || json.data || json;
}

/**
 * Get the currently cached menu version string.
 *
 * @returns {string|null}
 */
export function getVersion() {
    return cachedVersion;
}

/**
 * Check if a new menu version is available.
 * Compares the remote version against the currently loaded version.
 *
 * @returns {Promise<{hasUpdate: boolean, newVersion: string|null}>}
 */
export async function checkMenuVersion() {
    const newVersion = await getMenuVersion();
    if (!newVersion) {
        return { hasUpdate: false, newVersion: null };
    }
    const hasUpdate = cachedVersion !== null && newVersion !== cachedVersion;
    return { hasUpdate, newVersion };
}

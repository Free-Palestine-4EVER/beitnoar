import { reactive, computed } from 'vue';
import { fetchMenu, checkMenuVersion } from '../services/api';

/** Version check interval: 60 seconds */
const VERSION_CHECK_INTERVAL = 60_000;

const state = reactive({
    menu: [],
    loading: true,
    error: null,
    lang: localStorage.getItem('menu_language') || 'en',
    /** Whether the device is currently offline */
    isOffline: !navigator.onLine,
    /** Whether we're showing stale (cached/offline) data */
    isShowingCached: false,
});

/** @type {number|null} */
let versionCheckTimer = null;

function toRelativeStorageUrl(url) {
    if (typeof url !== 'string' || !url) return url;
    const idx = url.indexOf('/storage/');
    if (idx !== -1) {
        return url.substring(idx);
    }
    return url;
}

function sanitizeMenuItems(items) {
    if (!Array.isArray(items)) return [];
    for (const item of items) {
        if (item.image) {
            item.image = toRelativeStorageUrl(item.image);
        }
        if (Array.isArray(item.products)) {
            for (const p of item.products) {
                p.image_url = toRelativeStorageUrl(p.image_url);
                p.video_url = toRelativeStorageUrl(p.video_url);
                p.video_poster_url = toRelativeStorageUrl(p.video_poster_url);
                p.model_glb_url = toRelativeStorageUrl(p.model_glb_url);
                p.model_usdz_url = toRelativeStorageUrl(p.model_usdz_url);
                p.image_url = null;
                p.video_poster_url = null;
            }
        }
        if (Array.isArray(item.children)) {
            sanitizeMenuItems(item.children);
        }
    }
    return items;
}

/**
 * Start listening for online/offline connectivity changes.
 * When coming back online, immediately check for menu updates.
 */
function setupConnectivityListeners() {
    window.addEventListener('online', () => {
        state.isOffline = false;
        // When connection returns, immediately check for updates
        checkForMenuUpdate();
    });

    window.addEventListener('offline', () => {
        state.isOffline = true;
    });
}

/**
 * Check if a new menu version is available and refresh data if needed.
 * Does not wipe existing menu on failure — always prefers last known good data.
 */
async function checkForMenuUpdate() {
    try {
        const { hasUpdate } = await checkMenuVersion();
        if (hasUpdate) {
            // New version available — re-fetch menu data
            const data = await fetchMenu();
            const list = Array.isArray(data) ? data : [];
            state.menu = sanitizeMenuItems(list);
            state.isShowingCached = false;
            state.error = null;
        }
    } catch {
        // If version check or re-fetch fails, keep current menu intact.
        // Do NOT wipe valid data.
    }
}

/**
 * Start periodic version checking.
 * Checks every VERSION_CHECK_INTERVAL ms for menu updates.
 */
function startVersionPolling() {
    stopVersionPolling();
    versionCheckTimer = setInterval(checkForMenuUpdate, VERSION_CHECK_INTERVAL);
}

/** Stop periodic version checking. */
function stopVersionPolling() {
    if (versionCheckTimer !== null) {
        clearInterval(versionCheckTimer);
        versionCheckTimer = null;
    }
}

export function useMenu() {
    const loadMenu = async (force = false) => {
        if (state.menu.length > 0 && !force) {
            state.loading = false;
            return;
        }

        state.loading = true;
        state.error = null;
        try {
            const data = await fetchMenu();
            const list = Array.isArray(data) ? data : [];
            state.menu = sanitizeMenuItems(list);
            state.isShowingCached = false;
        } catch (e) {
            // If we already have cached menu data, show it with offline indicator
            if (state.menu.length > 0) {
                state.isShowingCached = true;
                state.loading = false;
                return;
            }
            // If service worker returned cached data, the fetch won't fail.
            // This error state only hits if there's no cache AND no network.
            state.error = e.message;
        } finally {
            state.loading = false;
        }
    };

    const setLang = (newLang) => {
        state.lang = newLang;
        localStorage.setItem('menu_language', newLang);
        applyLang(newLang);
    };

    const toggleLang = () => {
        setLang(state.lang === 'ar' ? 'en' : 'ar');
    };

    const applyLang = (lang) => {
        document.documentElement.lang = lang;
        document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
        if (lang === 'ar') {
            document.body.classList.add('ar');
        } else {
            document.body.classList.remove('ar');
        }
    };

    const t = (item, field) => {
        if (!item) return '';
        if (state.lang === 'ar') {
            return item[`${field}_ar`] || item[`${field}_en`] || '';
        }
        return item[`${field}_en`] || item[`${field}_ar`] || '';
    };

    const findCategory = (id) => {
        const search = (cats) => {
            if (!Array.isArray(cats)) return null;
            for (const cat of cats) {
                if (String(cat.id) === String(id)) return cat;
                if (Array.isArray(cat.children) && cat.children.length > 0) {
                    const found = search(cat.children);
                    if (found) return found;
                }
            }
            return null;
        };
        return search(state.menu);
    };

    const findProduct = (id) => {
        const search = (cats) => {
            if (!Array.isArray(cats)) return null;
            for (const cat of cats) {
                if (Array.isArray(cat.products) && cat.products.length > 0) {
                    const p = cat.products.find((prod) => String(prod.id) === String(id));
                    if (p) return { product: p, category: cat };
                }
                if (Array.isArray(cat.children) && cat.children.length > 0) {
                    const found = search(cat.children);
                    if (found) return found;
                }
            }
            return null;
        };
        return search(state.menu);
    };

    const getSiblingProducts = (productId, categoryId, limit = 3) => {
        const cat = findCategory(categoryId);
        if (!cat || !cat.products) return [];
        return cat.products.filter((p) => String(p.id) !== String(productId)).slice(0, limit);
    };

    const ui = computed(() => {
        if (state.lang === 'ar') {
            return {
                menu: 'المنيو',
                drag: 'اسحب الطبق ليلف',
                also: 'قد يعجبك أيضاً',
                cur: 'د.أ',
                kcal: (n) => `${n} سعرة حرارية`,
                sig: 'بيت إيليا',
                brief: 'بيت إيليا مش مطعم، بيت. حجر ونار وحديقة، وسفرة لبنانية بتتحضّر على مهل. كل رغيف بيطلع من فرن البيت، وكل مشوي بيتسوّى على نار إيليا.',
                line: 'دايماً في محل إلك على سفرة إيليا.',
                loading: 'جاري تحميل المنيو...',
                error: 'تعذر تحميل المنيو، يرجى المحاولة مرة أخرى.',
                retry: 'إعادة المحاولة',
                back: 'رجوع',
                viewInAr: 'عرض بالواقع المعزز',
                quickView: 'عرض سريع',
                close: 'إغلاق',
                arBtn: 'عرض بالواقع المعزز',
                arNotSupported: 'تعذّر فتح الواقع المعزز. تأكد من أن هاتفك يدعم AR ثم أعد المحاولة.',
                offline: 'غير متصل — يتم عرض آخر نسخة محفوظة',
            };
        }
        return {
            menu: 'The Menu',
            drag: 'Drag the plate to turn it',
            also: 'You may also like',
            cur: 'JD',
            kcal: (n) => `${n} kcal`,
            sig: 'Beit Elia',
            brief: "Beit Elia is not a restaurant. It is a home — stone, fire and a garden, and a Lebanese table set slowly. Every loaf comes out of the house oven, and everything grilled is cooked over Elia's fire.",
            line: "There's always a place for you at Elia's table.",
            loading: 'Loading menu...',
            error: 'Failed to load menu. Please try again.',
            retry: 'Retry',
            back: 'Back',
            viewInAr: 'View in AR',
            quickView: 'Quick view',
            close: 'Close',
            arBtn: 'View in AR',
            arNotSupported: 'AR could not open. Make sure your phone supports AR, then try again.',
            offline: 'Offline — showing saved menu',
        };
    });

    return {
        state,
        loadMenu,
        setLang,
        toggleLang,
        applyLang,
        t,
        findCategory,
        findProduct,
        getSiblingProducts,
        ui,
        setupConnectivityListeners,
        startVersionPolling,
        stopVersionPolling,
    };
}

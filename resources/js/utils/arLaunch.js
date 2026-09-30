export function getNativeArLaunchMode({ userAgent = '', platform = '', maxTouchPoints = 0 } = {}) {
  if (/android/i.test(userAgent)) {
    return 'scene-viewer';
  }

  if (/iPhone|iPad|iPod/i.test(userAgent) || (platform === 'MacIntel' && maxTouchPoints > 1)) {
    return 'quick-look';
  }

  return 'unsupported';
}

export function resolveArModelUrl(modelUrl, origin = '') {
  if (typeof modelUrl !== 'string' || !modelUrl) return null;

  const storageIndex = modelUrl.indexOf('/storage/');
  const path = storageIndex !== -1 ? modelUrl.substring(storageIndex) : modelUrl;
  if (/^https?:\/\//i.test(path)) return path;

  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return origin ? new URL(normalizedPath, origin).href : normalizedPath;
}

export function buildAndroidSceneViewerIntent(glbUrl, title = 'Beit Elia Dish') {
  if (!glbUrl) return null;

  return `intent://arvr.google.com/scene-viewer/1.0?file=${encodeURIComponent(glbUrl)}&mode=ar_only&resizable=false&title=${encodeURIComponent(title)}#Intent;scheme=https;package=com.google.android.googlequicksearchbox;action=android.intent.action.VIEW;end;`;
}

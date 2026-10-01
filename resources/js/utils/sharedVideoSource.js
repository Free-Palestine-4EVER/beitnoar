const MAX_PARALLEL_DOWNLOADS = 6;
const MAX_ATTEMPTS = 3;

const sources = new Map();
const queue = [];
let activeDownloads = 0;
let nextOrder = 0;

const absoluteUrl = (url) => new URL(url, window.location.href).href;
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const downloadVideo = async (url) => {
  let lastError;

  for (let attempt = 0; attempt < MAX_ATTEMPTS; attempt += 1) {
    try {
      const response = await fetch(url, { cache: 'force-cache' });
      if (!response.ok) throw new Error(`Video request returned ${response.status}: ${url}`);

      const blob = await response.blob();
      if (!blob.size) throw new Error(`Video download was empty: ${url}`);
      return URL.createObjectURL(blob);
    } catch (error) {
      lastError = error;
      if (attempt + 1 < MAX_ATTEMPTS) await wait(250 * (attempt + 1));
    }
  }

  throw lastError;
};

const pumpQueue = () => {
  while (activeDownloads < MAX_PARALLEL_DOWNLOADS && queue.length) {
    queue.sort((a, b) => b.priority - a.priority || a.order - b.order);
    const entry = queue.shift();
    if (entry.state !== 'queued') continue;

    entry.state = 'loading';
    activeDownloads += 1;

    downloadVideo(entry.url)
      .then((objectUrl) => {
        entry.objectUrl = objectUrl;
        entry.state = 'ready';
        entry.resolve(objectUrl);
      })
      .catch((error) => {
        entry.state = 'failed';
        if (sources.get(entry.url) === entry) sources.delete(entry.url);
        entry.reject(error);
      })
      .finally(() => {
        activeDownloads -= 1;
        pumpQueue();
      });
  }
};

const getEntry = (url, priority = 0) => {
  let entry = sources.get(url);
  if (entry) {
    if (entry.state === 'queued' && priority > entry.priority) {
      entry.priority = priority;
      pumpQueue();
    }
    return entry;
  }

  let resolve;
  let reject;
  const promise = new Promise((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });

  entry = {
    url,
    order: nextOrder++,
    priority,
    state: 'queued',
    objectUrl: null,
    promise,
    resolve,
    reject,
  };

  sources.set(url, entry);
  queue.push(entry);
  pumpQueue();
  return entry;
};

export const prioritizeSharedVideoSource = (source) => {
  const url = absoluteUrl(source);
  const entry = sources.get(url);
  if (!entry || entry.state !== 'queued') return;
  entry.priority = 1;
  pumpQueue();
};

export const acquireSharedVideoSource = async (source, { priority = false } = {}) => {
  const url = absoluteUrl(source);
  const entry = getEntry(url, priority ? 1 : 0);
  const objectUrl = await entry.promise;

  // Object URLs stay alive for the page lifetime. Cards can pause, unmount,
  // and remount without releasing bytes or downloading the source again.
  return { src: objectUrl, release() {} };
};

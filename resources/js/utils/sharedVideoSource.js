const absoluteUrl = (url) => new URL(url, window.location.href).href;
const videoBlobs = new Map();

/**
 * Download each video completely once, then share its in-memory object URL
 * between cards and the product detail view. This keeps offscreen clips warm
 * and prevents a scroll or route change from fetching the same clip again.
 */
export const acquireSharedVideoSource = async (source) => {
  const url = absoluteUrl(source);
  let download = videoBlobs.get(url);

  if (!download) {
    download = fetch(url, { cache: 'force-cache' })
      .then((response) => {
        if (!response.ok) throw new Error(`Video download failed (${response.status}): ${url}`);
        return response.blob();
      })
      .then((blob) => URL.createObjectURL(blob))
      .catch((error) => {
        videoBlobs.delete(url);
        throw error;
      });
    videoBlobs.set(url, download);
  }

  const objectUrl = await download;

  return {
    src: objectUrl,
    release() {},
  };
};

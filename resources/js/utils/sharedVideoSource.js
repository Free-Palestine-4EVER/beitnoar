const absoluteUrl = (url) => new URL(url, window.location.href).href;

/**
 * Give native video elements the original URL so they can request byte ranges
 * and start playback before the full clip has downloaded.
 */
export const acquireSharedVideoSource = async (source) => {
  const url = absoluteUrl(source);

  return {
    src: url,
    release() {},
  };
};

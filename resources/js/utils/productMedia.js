export const hasProductVideo = (product) => {
  return typeof product?.video_url === 'string' && product.video_url.trim().length > 0;
};

export const getProductCardMedia = (product) => {
  if (hasProductVideo(product)) {
    return { type: 'video', src: product.video_url };
  }

  return { type: 'plate', src: null };
};

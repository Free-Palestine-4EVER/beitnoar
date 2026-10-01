import assert from 'node:assert/strict';
import test from 'node:test';
import { getProductCardMedia, hasProductImage, hasProductVideo } from '../../resources/js/utils/productMedia.js';

test('a product video takes precedence over its photo and poster', () => {
  const product = {
    video_url: '/storage/products/dish.mp4',
    image_url: '/storage/products/dish.jpg',
    video_poster_url: '/storage/products/dish-poster.jpg',
  };

  assert.equal(hasProductVideo(product), true);
  assert.deepEqual(getProductCardMedia(product), {
    type: 'video',
    src: '/storage/products/dish.mp4',
  });
});

test('a still photo is used when a product has no video', () => {
  const product = { image_url: '/storage/products/dish.webp' };

  assert.equal(hasProductImage(product), true);
  assert.deepEqual(getProductCardMedia(product), {
    type: 'image',
    src: '/storage/products/dish.webp',
  });
  assert.deepEqual(getProductCardMedia({ video_poster_url: '/storage/products/poster.jpg' }), {
    type: 'plate',
    src: null,
  });
});

test('products without a video or photo use plate artwork', () => {
  assert.deepEqual(getProductCardMedia({}), { type: 'plate', src: null });
  assert.equal(hasProductVideo({ video_url: '  ' }), false);
});

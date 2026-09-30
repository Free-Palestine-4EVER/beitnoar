import assert from 'node:assert/strict';
import test from 'node:test';
import { getProductCardMedia, hasProductVideo } from '../../resources/js/utils/productMedia.js';

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

test('still photos are not used when a product has no video', () => {
  assert.deepEqual(getProductCardMedia({ image_url: '/storage/products/dish.jpg' }), {
    type: 'plate',
    src: null,
  });
  assert.deepEqual(getProductCardMedia({ video_poster_url: '/storage/products/poster.jpg' }), {
    type: 'plate',
    src: null,
  });
});

test('products without a video use plate artwork', () => {
  assert.deepEqual(getProductCardMedia({}), { type: 'plate', src: null });
  assert.equal(hasProductVideo({ video_url: '  ' }), false);
});

import assert from 'node:assert/strict';
import test from 'node:test';
import { buildAndroidSceneViewerIntent, getNativeArLaunchMode, resolveArModelUrl } from '../../resources/js/utils/arLaunch.js';

test('uses Android Scene Viewer for Android browsers', () => {
  assert.equal(
    getNativeArLaunchMode({ userAgent: 'Mozilla/5.0 (Linux; Android 15) Chrome/140.0' }),
    'scene-viewer',
  );
});

test('uses Quick Look for iPhone and iPad browsers', () => {
  assert.equal(getNativeArLaunchMode({ userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)' }), 'quick-look');
  assert.equal(getNativeArLaunchMode({ userAgent: 'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X)' }), 'quick-look');
});

test('recognizes iPads using desktop mode', () => {
  assert.equal(getNativeArLaunchMode({ userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', platform: 'MacIntel', maxTouchPoints: 5 }), 'quick-look');
});

test('does not load native AR assets on desktop browsers', () => {
  assert.equal(getNativeArLaunchMode({ userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', platform: 'MacIntel', maxTouchPoints: 0 }), 'unsupported');
});

test('resolves stored AR models against the current app origin', () => {
  assert.equal(
    resolveArModelUrl('https://old.example/storage/products/models/dish.glb', 'https://menu.example'),
    'https://menu.example/storage/products/models/dish.glb',
  );
  assert.equal(resolveArModelUrl('models/dish.glb', 'https://menu.example'), 'https://menu.example/models/dish.glb');
  assert.equal(resolveArModelUrl(null, 'https://menu.example'), null);
});

test('builds an Android Scene Viewer link with an encoded model URL and title', () => {
  const intent = buildAndroidSceneViewerIntent('https://beitelia.com/storage/products/models/dish 1.glb', 'Eggs & Za’atar');

  assert.match(intent, /mode=ar_only/);
  assert.match(intent, /file=https%3A%2F%2Fbeitelia\.com%2Fstorage%2Fproducts%2Fmodels%2Fdish%201\.glb/);
  assert.match(intent, /title=Eggs%20%26%20Za%E2%80%99atar/);
  assert.equal(buildAndroidSceneViewerIntent(null), null);
});

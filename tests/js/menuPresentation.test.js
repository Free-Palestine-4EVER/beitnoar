import assert from 'node:assert/strict';
import test from 'node:test';
import { readFile } from 'node:fs/promises';

const readSource = (path) => readFile(new URL(path, import.meta.url), 'utf8');

test('dish selection opens its detail popup without exposing ordering controls', async () => {
  const [header, category, productCard, quickView] = await Promise.all([
    readSource('../../resources/js/components/AppHeader.vue'),
    readSource('../../resources/js/views/CategoryView.vue'),
    readSource('../../resources/js/components/ProductCard.vue'),
    readSource('../../resources/js/components/ProductQuickView.vue'),
  ]);

  assert.doesNotMatch(header, /cart|basket/i);
  assert.doesNotMatch(category, /useCart|addToCartAndOpen|add-to-cart/i);
  assert.doesNotMatch(productCard, /add.?to.?cart/i);
  assert.doesNotMatch(quickView, /addedToCart|Added to cart/i);
  assert.match(category, /@select="openQuickView\(product\)"/);
  assert.match(productCard, /view-ar/);
});

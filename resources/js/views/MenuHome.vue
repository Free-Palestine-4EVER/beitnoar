<template>
  <div class="view">
    <div class="brief">
      <p>{{ ui.brief }}</p>
      <div class="en">{{ ui.line }}</div>
    </div>

    <div class="rule">
      <span>{{ ui.menu }}</span>
    </div>

    <div class="groups" v-if="categories.length > 0">
      <SectionCard
        v-for="section in categories"
        :key="section.id"
        :category="section"
        @select="goToSection(section)"
      />
    </div>

    <div class="ver">Beit Elia · Digital Menu</div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useMenu } from '../stores/menu';
import SectionCard from '../components/SectionCard.vue';

const router = useRouter();
const { state, ui } = useMenu();

const categories = computed(() => state.menu);

const goToSection = (section) => {
  router.push({ name: 'category', params: { id: section.id } });
};
</script>

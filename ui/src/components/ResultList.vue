<template>
  <div class="grid gap-4">
    <div v-for="(item, i) in results" :key="i" class="card bg-base-200 shadow">
      <div class="card-body py-4">
        <h2 class="card-title text-base">{{ item.title || item.ti || '(kein Titel)' }}</h2>
        <p class="text-sm opacity-70">{{ item.author || item.au || '' }}</p>
        <p class="text-xs opacity-50">{{ item.year || item.yr || '' }} · {{ item.format || item.mt || '' }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps(['results'])
</script>

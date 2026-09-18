<template>
  <div class="navbar bg-base-200 shadow-sm px-4">
    <div class="flex-1">
      <span class="text-xl font-bold text-primary">Lukida Essential</span>
    </div>
    <div class="flex-none gap-2">
      <select class="select select-sm select-bordered" :value="currentTheme" @change="$emit('theme-change', $event.target.value)">
        <option v-for="t in themes" :key="t" :value="t">{{ t }}</option>
      </select>
    </div>
  </div>
</template>

<script setup>
defineProps(['currentTheme'])
defineEmits(['theme-change'])
const themes = [
  'light','dark','cupcake','bumblebee','emerald','corporate','synthwave',
  'retro','cyberpunk','valentine','halloween','garden','forest','aqua',
  'lofi','pastel','fantasy','wireframe','black','luxury','dracula','cmyk',
  'autumn','business','acid','lemonade','night','coffee','winter','nord'
]
</script>

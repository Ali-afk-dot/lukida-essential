<template>
  <div data-theme="nord" class="min-h-screen bg-base-100">
    <Navbar @theme-change="theme = $event" :current-theme="theme" />
    <main class="container mx-auto px-4 py-8 max-w-5xl">
      <SearchBar @search="doSearch" :loading="loading" />
      <ResultList v-if="results.length" :results="results" />
      <Welcome v-else-if="!loading" />
      <div v-if="loading" class="flex justify-center mt-16">
        <span class="loading loading-spinner loading-lg text-primary"></span>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import Navbar from './components/Navbar.vue'
import SearchBar from './components/SearchBar.vue'
import ResultList from './components/ResultList.vue'
import Welcome from './components/Welcome.vue'

const theme = ref('nord')
const loading = ref(false)
const results = ref([])

watch(theme, val => {
  document.querySelector('[data-theme]').setAttribute('data-theme', val)
})

async function doSearch(query) {
  if (!query.trim()) return
  loading.value = true
  results.value = []
  try {
    const res = await fetch('/api/5/user/config', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        apikey: '976c80f32840377b95c4ec991f77ec7b4836866812e02db85767e0615e1f5aef',
        client: 'Essential',
        q: query
      })
    })
    const data = await res.json()
    results.value = data.results || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="art-card p-5 flex-b mb-5 max-sm:mb-4">
    <div>
      <h2 class="text-2xl font-medium">关于项目</h2>
      <p class="text-g-700 mt-1">{{ systemName }} 是一款兼具设计美学与高效开发的后台系统</p>
      <p class="text-g-700 mt-1">使用了 webman + Vue3 + Element Plus 高性能、高颜值技术栈</p>
    </div>
    <img class="w-75 max-md:!hidden" src="@imgs/draw/draw1.png" alt="draw1" />
  </div>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { useBrandStore } from '@/store/modules/brand'

  const brandStore = useBrandStore()
  const systemName = computed(() => brandStore.site_name)
</script>

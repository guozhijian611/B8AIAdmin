<!-- 系统logo -->
<template>
  <div class="flex-cc">
    <img :style="logoStyle" :src="logoSrc" alt="logo" class="w-full h-full" />
  </div>
</template>

<script setup lang="ts">
  defineOptions({ name: 'ArtLogo' })

  interface Props {
    /** logo 大小 */
    size?: number | string
  }

  const props = withDefaults(defineProps<Props>(), {
    size: 36
  })

  import defaultLogo from '@/assets/img/common/logo.png'
  import { useBrandStore } from '@/store/modules/brand'

  const brandStore = useBrandStore()
  const logoSrc = computed(() => brandStore.site_logo || defaultLogo)
  const logoStyle = computed(() => ({ width: `${props.size}px`, objectFit: 'contain' as any }))
</script>

<template>
  <div class="adminer-page">
    <div class="adminer-toolbar">
      <div>
        <h2>Adminer 数据库管理</h2>
        <p>通过后台鉴权入口访问</p>
      </div>
      <div class="adminer-actions">
        <el-button :loading="loading" @click="loadAdminer">刷新</el-button>
        <el-button type="primary" :disabled="!iframeUrl" @click="openWindow">新窗口打开</el-button>
      </div>
    </div>

    <el-alert v-if="errorMessage" :title="errorMessage" type="error" show-icon :closable="false" />

    <div class="adminer-frame" v-loading="loading">
      <iframe
        v-if="iframeUrl"
        :key="iframeUrl"
        :src="iframeUrl"
        title="Adminer"
        frameborder="0"
        @load="loading = false"
      ></iframe>
    </div>
  </div>
</template>

<script setup lang="ts">
  import adminerApi from '@/api/safeguard/adminer'

  defineOptions({ name: 'SafeguardAdminer' })

  const loading = ref(false)
  const iframeUrl = ref('')
  const errorMessage = ref('')
  const { VITE_API_URL } = import.meta.env

  const buildUrl = (url: string): string => {
    if (/^https?:\/\//i.test(url)) return url
    return `${VITE_API_URL || ''}${url}`
  }

  const loadAdminer = async (): Promise<void> => {
    loading.value = true
    errorMessage.value = ''

    try {
      const data = await adminerApi.ticket()
      iframeUrl.value = buildUrl(data.url)
    } catch (error: any) {
      loading.value = false
      errorMessage.value = error?.message || 'Adminer 访问票据签发失败'
    }
  }

  const openWindow = (): void => {
    if (!iframeUrl.value) return
    window.open(iframeUrl.value, '_blank', 'noopener,noreferrer')
  }

  onMounted(() => {
    loadAdminer()
  })
</script>

<style scoped lang="scss">
  .adminer-page {
    display: flex;
    flex-direction: column;
    gap: 12px;
    height: calc(100vh - 112px);
    min-height: 640px;
  }

  .adminer-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 16px;
    background: var(--el-bg-color);
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 8px;

    h2 {
      margin: 0;
      font-size: 17px;
      font-weight: 600;
      color: var(--el-text-color-primary);
    }

    p {
      margin: 4px 0 0;
      font-size: 13px;
      color: var(--el-text-color-secondary);
    }
  }

  .adminer-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
  }

  .adminer-frame {
    flex: 1;
    min-height: 0;
    overflow: hidden;
    background: var(--el-bg-color);
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 8px;

    iframe {
      display: block;
      width: 100%;
      height: 100%;
      min-height: 560px;
      border: 0;
    }
  }

  @media (max-width: 768px) {
    .adminer-page {
      height: auto;
      min-height: 100vh;
    }

    .adminer-toolbar {
      align-items: flex-start;
      flex-direction: column;
    }

    .adminer-actions {
      width: 100%;

      :deep(.el-button) {
        flex: 1;
      }
    }
  }
</style>

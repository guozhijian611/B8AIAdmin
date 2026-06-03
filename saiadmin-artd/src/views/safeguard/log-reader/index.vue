<template>
  <div class="log-reader-page">
    <div class="log-reader-toolbar">
      <div>
        <h2>日志查看器</h2>
        <p>通过后台鉴权入口查看 Webman 运行日志</p>
      </div>
      <div class="log-reader-actions">
        <el-button :loading="loading" @click="loadLogReader">刷新</el-button>
        <el-button type="primary" :disabled="!iframeUrl" @click="openWindow">新窗口打开</el-button>
      </div>
    </div>

    <el-alert v-if="errorMessage" :title="errorMessage" type="error" show-icon :closable="false" />

    <div class="log-reader-frame" v-loading="loading">
      <iframe
        v-if="iframeUrl"
        :key="iframeUrl"
        :src="iframeUrl"
        title="日志查看器"
        frameborder="0"
        @load="loading = false"
      ></iframe>
    </div>
  </div>
</template>

<script setup lang="ts">
  import logReaderApi from '@/api/safeguard/log-reader'

  defineOptions({ name: 'SafeguardLogReader' })

  const loading = ref(false)
  const iframeUrl = ref('')
  const errorMessage = ref('')
  const { VITE_API_URL } = import.meta.env

  const buildUrl = (url: string): string => {
    if (/^https?:\/\//i.test(url)) return url
    return `${VITE_API_URL || ''}${url}`
  }

  const loadLogReader = async (): Promise<void> => {
    loading.value = true
    errorMessage.value = ''

    try {
      const data = await logReaderApi.ticket()
      iframeUrl.value = buildUrl(data.url)
    } catch (error: any) {
      loading.value = false
      errorMessage.value = error?.message || '日志查看器访问票据签发失败'
    }
  }

  const openWindow = (): void => {
    if (!iframeUrl.value) return
    window.open(iframeUrl.value, '_blank', 'noopener,noreferrer')
  }

  onMounted(() => {
    loadLogReader()
  })
</script>

<style scoped lang="scss">
  .log-reader-page {
    display: flex;
    flex-direction: column;
    gap: 12px;
    height: calc(100vh - 112px);
    min-height: 640px;
  }

  .log-reader-toolbar {
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

  .log-reader-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
  }

  .log-reader-frame {
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
    .log-reader-page {
      height: auto;
      min-height: 100vh;
    }

    .log-reader-toolbar {
      align-items: flex-start;
      flex-direction: column;
    }

    .log-reader-actions {
      width: 100%;

      :deep(.el-button) {
        flex: 1;
      }
    }
  }
</style>

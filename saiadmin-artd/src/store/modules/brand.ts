import { defineStore } from 'pinia'
import { fetchBrand } from '@/api/system/brand'

export const useBrandStore = defineStore('app-brand', {
  state: () => ({
    site_name: 'B8AIAdmin',
    site_logo: '',
    site_favicon: '',
    site_desc: '',
    site_keywords: '',
    site_copyright: '',
    site_record_number: '',
    loaded: false
  }),
  actions: {
    async loadBrand() {
      if (this.loaded) return
      try {
        // request.get 已解包为 data 本体
        const data = await fetchBrand()
        if (data) {
          this.site_name = data.site_name || 'B8AIAdmin'
          this.site_logo = data.site_logo || ''
          this.site_favicon = data.site_favicon || ''
          this.site_desc = data.site_desc || ''
          this.site_keywords = data.site_keywords || ''
          this.site_copyright = data.site_copyright || ''
          this.site_record_number = data.site_record_number || ''
        }
      } catch {
        // 静默回退默认品牌
      } finally {
        this.loaded = true
        this.applyDocumentBrand()
      }
    },
    applyDocumentBrand() {
      if (typeof document === 'undefined') return

      const current = document.title || ''
      if (!current || /B8AIAdmin|SaiAdmin/i.test(current)) {
        document.title = this.site_name
      } else if (!current.includes(this.site_name)) {
        document.title = current.replace(/B8AIAdmin|SaiAdmin/gi, this.site_name)
      }

      if (this.site_favicon) {
        let link = document.querySelector<HTMLLinkElement>("link[rel*='icon']")
        if (!link) {
          link = document.createElement('link')
          link.rel = 'icon'
          document.head.appendChild(link)
        }
        link.href = this.site_favicon
      }

      if (this.site_desc) {
        const metaDesc = document.querySelector<HTMLMetaElement>("meta[name='description']")
        if (metaDesc) metaDesc.content = this.site_desc
      }

      if (this.site_keywords) {
        let metaKw = document.querySelector<HTMLMetaElement>("meta[name='keywords']")
        if (!metaKw) {
          metaKw = document.createElement('meta')
          metaKw.name = 'keywords'
          document.head.appendChild(metaKw)
        }
        metaKw.content = this.site_keywords
      }
    }
  }
})

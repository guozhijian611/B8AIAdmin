import request from '@/utils/http'

export interface BrandInfo {
  site_name: string
  site_logo: string
  site_favicon: string
  site_desc: string
  site_keywords: string
  site_copyright: string
  site_record_number: string
}

/**
 * 获取品牌信息
 */
export function fetchBrand() {
  return request.get<BrandInfo>({
    url: '/core/system/brand',
    showErrorMessage: false
  })
}

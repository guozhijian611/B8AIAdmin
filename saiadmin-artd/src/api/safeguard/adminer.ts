import request from '@/utils/http'

/**
 * Adminer 数据库管理 API
 */
export default {
  /**
   * 签发 Adminer 访问票据
   */
  ticket() {
    return request.get<{ url: string; expires_in: number }>({
      url: '/core/adminer/ticket'
    })
  }
}

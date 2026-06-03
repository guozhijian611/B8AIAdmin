import request from '@/utils/http'

/**
 * 日志查看器 API
 */
export default {
  /**
   * 签发日志查看器访问票据
   */
  ticket() {
    return request.get<{ url: string; expires_in: number }>({
      url: '/core/log-reader/ticket'
    })
  }
}

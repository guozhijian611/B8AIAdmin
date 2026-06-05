import request from '@/utils/http'

export default {
  /**
   * 可用支付方式
   */
  paymentMethods() {
    return request.get<any>({
      url: '/app/saipay/api/demo/paymentMethods'
    })
  },

  /**
   * 支付宝扫码支付示例
   */
  alipayScan() {
    return request.get<any>({
      url: '/app/saipay/api/demo/alipayScan'
    })
  },

  /**
   * 微信扫码支付示例
   */
  wechatScan() {
    return request.get<any>({
      url: '/app/saipay/api/demo/wechatScan'
    })
  },

  /**
   * 扫码支付示例
   */
  manualScan() {
    return request.get<any>({
      url: '/app/saipay/api/demo/manualScan'
    })
  },

  /**
   * 继续支付
   */
  payOrder(params: Record<string, any>) {
    return request.post<any>({
      url: '/app/saipay/api/demo/payOrder',
      params
    })
  },

  /**
   * 用户确认扫码支付已付款
   */
  confirmManualPaid(params: Record<string, any>) {
    return request.post<any>({
      url: '/app/saipay/api/demo/confirmManualPaid',
      params
    })
  }
}

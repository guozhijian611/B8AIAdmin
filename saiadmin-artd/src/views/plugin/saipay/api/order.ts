import request from '@/utils/http'

/**
 * 订单记录 API接口
 */
export default {
  /**
   * 获取数据列表
   * @param params 搜索参数
   * @returns 数据列表
   */
  list(params: Record<string, any>) {
    return request.get<Api.Common.ApiPage>({
      url: '/app/saipay/admin/Order/index',
      params
    })
  },

  /**
   * 读取数据
   * @param id 数据ID
   * @returns 数据详情
   */
  read(id: number | string) {
    return request.get<Api.Common.ApiData>({
      url: '/app/saipay/admin/Order/read?id=' + id
    })
  },

  /**
   * 刷新回调
   * @param params 数据参数
   * @returns 执行结果
   */
  refresh(params: Record<string, any>) {
    return request.post<any>({
      url: '/app/saipay/admin/Order/refresh',
      data: params
    })
  },

  /**
   * 管理员确认扫码支付到账
   * @param params 数据参数
   * @returns 执行结果
   */
  confirmManualPaid(params: Record<string, any>) {
    return request.post<any>({
      url: '/app/saipay/admin/Order/confirmManualPaid',
      data: params
    })
  },

  /**
   * 管理员驳回扫码支付付款确认
   * @param params 数据参数
   * @returns 执行结果
   */
  rejectManualPaid(params: Record<string, any>) {
    return request.post<any>({
      url: '/app/saipay/admin/Order/rejectManualPaid',
      data: params
    })
  },

  /**
   * 删除数据
   * @param params 数据参数
   * @returns 执行结果
   */
  delete(params: Record<string, any>) {
    return request.del<any>({
      url: '/app/saipay/admin/Order/destroy',
      data: params
    })
  }
}

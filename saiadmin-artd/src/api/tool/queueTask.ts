import request from '@/utils/http'

export default {
  list(params: Record<string, any>) {
    return request.get<Api.Common.ApiPage>({
      url: '/tool/queueTask/index',
      params
    })
  },

  read(id: number | string) {
    return request.get<Api.Common.ApiData>({
      url: '/tool/queueTask/read',
      params: { id }
    })
  },

  delete(params: Record<string, any>) {
    return request.del<any>({
      url: '/tool/queueTask/destroy',
      data: params
    })
  },

  retry(params: Record<string, any>) {
    return request.post<any>({
      url: '/tool/queueTask/retry',
      data: params
    })
  },

  cancel(params: Record<string, any>) {
    return request.post<any>({
      url: '/tool/queueTask/cancel',
      data: params
    })
  },

  clearCompleted(params: Record<string, any>) {
    return request.post<any>({
      url: '/tool/queueTask/clearCompleted',
      data: params
    })
  },

  stats() {
    return request.get<Api.Common.ApiData>({
      url: '/tool/queueTask/stats'
    })
  }
}

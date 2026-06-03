import request from '@/utils/http'

export default {
  list(params: Record<string, any>) {
    return request.get<Api.Common.ApiPage>({
      url: '/tool/queueMessage/index',
      params
    })
  },

  read(id: number | string) {
    return request.get<Api.Common.ApiData>({
      url: '/tool/queueMessage/read',
      params: { id }
    })
  },

  publish(params: Record<string, any>) {
    return request.post<any>({
      url: '/tool/queueMessage/publish',
      data: params
    })
  },

  delete(params: Record<string, any>) {
    return request.del<any>({
      url: '/tool/queueMessage/destroy',
      data: params
    })
  },

  retry(params: Record<string, any>) {
    return request.post<any>({
      url: '/tool/queueMessage/retry',
      data: params
    })
  },

  cancel(params: Record<string, any>) {
    return request.post<any>({
      url: '/tool/queueMessage/cancel',
      data: params
    })
  },

  clearPublished(params: Record<string, any>) {
    return request.post<any>({
      url: '/tool/queueMessage/clearPublished',
      data: params
    })
  },

  stats() {
    return request.get<Api.Common.ApiData>({
      url: '/tool/queueMessage/stats'
    })
  }
}

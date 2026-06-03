import request from '@/utils/http'

export default {
  list(params: Record<string, any>) {
    return request.get<Api.Common.ApiPage>({
      url: '/tool/queueConfig/index',
      params
    })
  },

  read(id: number | string) {
    return request.get<Api.Common.ApiData>({
      url: '/tool/queueConfig/read',
      params: { id }
    })
  },

  save(params: Record<string, any>) {
    return request.post<any>({
      url: '/tool/queueConfig/save',
      data: params
    })
  },

  update(params: Record<string, any>) {
    return request.put<any>({
      url: '/tool/queueConfig/update',
      data: params
    })
  },

  delete(params: Record<string, any>) {
    return request.del<any>({
      url: '/tool/queueConfig/destroy',
      data: params
    })
  },

  changeStatus(params: Record<string, any>) {
    return request.post<any>({
      url: '/tool/queueConfig/changeStatus',
      data: params
    })
  },

  options() {
    return request.get<Api.Common.ApiData[]>({
      url: '/tool/queueConfig/options'
    })
  }
}

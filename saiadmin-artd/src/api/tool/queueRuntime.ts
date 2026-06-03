import request from '@/utils/http'

export default {
  list(params: Record<string, any>) {
    return request.get<Api.Common.ApiPage>({
      url: '/tool/queueRuntime/index',
      params
    })
  },

  purge(params: Record<string, any>) {
    return request.post<any>({
      url: '/tool/queueRuntime/purge',
      data: params
    })
  }
}

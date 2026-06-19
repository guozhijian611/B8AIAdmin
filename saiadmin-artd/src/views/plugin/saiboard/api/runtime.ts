import request from '@/utils/http'

export default {
  screen(code: string, params: Record<string, any> = {}) {
    return request.get<any>({
      url: `/app/saiboard/api/screen/${code}`,
      params,
      showErrorMessage: false
    })
  },
  data(params: Record<string, any>) {
    return request.post<any>({
      url: '/app/saiboard/api/data',
      data: params,
      showErrorMessage: false
    })
  }
}

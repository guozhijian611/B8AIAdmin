import request from '@/utils/http'

export default {
  screen(code: string, token = '') {
    return request.get<any>({
      url: `/app/saiboard/api/screen/${code}`,
      params: token ? { token } : {},
      showErrorMessage: false
    })
  },
  data(params: Record<string, any>) {
    return request.get<any>({
      url: '/app/saiboard/api/data',
      params,
      showErrorMessage: false
    })
  }
}

import request from '@/utils/http'

export default {
  list(params: Record<string, any>) {
    return request.get<any>({ url: '/app/saiboard/admin/QueryTemplate/index', params })
  },
  read(id: number | string) {
    return request.get<any>({ url: '/app/saiboard/admin/QueryTemplate/read', params: { id } })
  },
  save(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/QueryTemplate/save', data })
  },
  update(data: Record<string, any>) {
    return request.put<any>({ url: '/app/saiboard/admin/QueryTemplate/update', data })
  },
  delete(data: Record<string, any>) {
    return request.del<any>({ url: '/app/saiboard/admin/QueryTemplate/destroy', data })
  },
  changeStatus(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/QueryTemplate/changeStatus', data })
  },
  preview(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/QueryTemplate/preview', data })
  },
  options(params: Record<string, any> = {}) {
    return request.get<any[]>({ url: '/app/saiboard/admin/QueryTemplate/options', params })
  }
}

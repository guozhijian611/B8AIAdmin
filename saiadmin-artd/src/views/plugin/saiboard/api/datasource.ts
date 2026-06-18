import request from '@/utils/http'

export default {
  list(params: Record<string, any>) {
    return request.get<any>({ url: '/app/saiboard/admin/Datasource/index', params })
  },
  read(id: number | string) {
    return request.get<any>({ url: '/app/saiboard/admin/Datasource/read', params: { id } })
  },
  save(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/Datasource/save', data })
  },
  update(data: Record<string, any>) {
    return request.put<any>({ url: '/app/saiboard/admin/Datasource/update', data })
  },
  delete(data: Record<string, any>) {
    return request.del<any>({ url: '/app/saiboard/admin/Datasource/destroy', data })
  },
  changeStatus(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/Datasource/changeStatus', data })
  },
  test(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/Datasource/test', data })
  },
  options() {
    return request.get<any[]>({ url: '/app/saiboard/admin/Datasource/options' })
  },
  schema(params: Record<string, any>) {
    return request.get<any>({ url: '/app/saiboard/admin/Datasource/schema', params })
  }
}

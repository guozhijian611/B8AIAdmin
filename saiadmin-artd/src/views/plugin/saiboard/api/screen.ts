import request from '@/utils/http'

export default {
  list(params: Record<string, any>) {
    return request.get<any>({ url: '/app/saiboard/admin/Screen/index', params })
  },
  read(id: number | string) {
    return request.get<any>({ url: '/app/saiboard/admin/Screen/read', params: { id } })
  },
  save(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/Screen/save', data })
  },
  update(data: Record<string, any>) {
    return request.put<any>({ url: '/app/saiboard/admin/Screen/update', data })
  },
  delete(data: Record<string, any>) {
    return request.del<any>({ url: '/app/saiboard/admin/Screen/destroy', data })
  },
  changeStatus(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/Screen/changeStatus', data })
  },
  saveLayout(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/Screen/saveLayout', data })
  },
  publish(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/Screen/publish', data })
  },
  copy(data: Record<string, any>) {
    return request.post<any>({ url: '/app/saiboard/admin/Screen/copy', data })
  }
}

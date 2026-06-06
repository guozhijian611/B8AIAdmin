import request from '@/utils/http'

export function createCrudApi(baseUrl: string) {
  return {
    list(params: Record<string, any>) {
      return request.get<any>({ url: `${baseUrl}/index`, params })
    },
    read(id: number | string) {
      return request.get<any>({ url: `${baseUrl}/read`, params: { id } })
    },
    save(data: Record<string, any>) {
      return request.post<any>({ url: `${baseUrl}/save`, data })
    },
    update(data: Record<string, any>) {
      return request.put<any>({ url: `${baseUrl}/update`, data })
    },
    delete(data: Record<string, any>) {
      return request.del<any>({ url: `${baseUrl}/destroy`, data })
    },
    changeStatus(data: Record<string, any>) {
      return request.post<any>({ url: `${baseUrl}/changeStatus`, data })
    }
  }
}

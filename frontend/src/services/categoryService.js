import httpClient from './httpClient'

export const categoryService = {
  getCategories: async () => {
    const response = await httpClient.get('/categories')
    return response.data?.data ?? response.data
  },
}

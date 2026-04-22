import httpClient from './httpClient'

export const productService = {
  getProducts: async (params = {}) => {
    const response = await httpClient.get('/products', { params })
    return response.data
  },
  getProductById: async (id) => {
    const response = await httpClient.get(`/product/${id}`)
    return response.data
  },
  getFeaturedProducts: async () => {
    const response = await httpClient.get('/products', { params: { featured: true, perPage: 8 } })
    return response.data
  },
}

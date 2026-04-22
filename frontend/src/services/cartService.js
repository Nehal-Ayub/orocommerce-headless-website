import httpClient from './httpClient'

export const cartService = {
  get() {
    return httpClient.get('/cart')
  },
  sync(items) {
    return httpClient.post('/cart', { items })
  },
  add(payload) {
    return httpClient.post('/cart', payload)
  },
  updateItem(itemId, payload) {
    return httpClient.put(`/cart/${itemId}`, payload)
  },
  removeItem(itemId) {
    return httpClient.delete(`/cart/${itemId}`)
  },
}

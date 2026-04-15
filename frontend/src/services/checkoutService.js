import httpClient from './httpClient'

export const checkoutService = {
  place(payload) {
    return httpClient.post('/checkout', payload)
  },
}

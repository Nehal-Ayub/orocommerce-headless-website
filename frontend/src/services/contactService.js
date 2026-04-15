import httpClient from './httpClient'

export const submitContactForm = async (payload) => {
  const response = await httpClient.post('/contact', payload)
  return response.data
}

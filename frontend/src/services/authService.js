import httpClient from './httpClient'

const authService = {
  async login(credentials) {
    const response = await httpClient.post('/auth/login', credentials)
    return response.data
  },
}

export default authService

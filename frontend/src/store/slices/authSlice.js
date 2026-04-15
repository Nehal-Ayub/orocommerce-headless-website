import { createAsyncThunk, createSlice } from '@reduxjs/toolkit'
import authService from '../../services/authService'

const initialState = {
  user: null,
  token: localStorage.getItem('auth_token') || null,
  status: 'idle',
  error: null,
}

export const loginThunk = createAsyncThunk(
  'auth/login',
  async (payload, { rejectWithValue }) => {
    try {
      return await authService.login(payload)
    } catch (error) {
      return rejectWithValue(error.response?.data?.message || 'Login failed')
    }
  },
)

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    hydrateAuthFromStorage(state) {
      const rawUser = localStorage.getItem('auth_user')
      if (rawUser) {
        try {
          state.user = JSON.parse(rawUser)
        } catch {
          localStorage.removeItem('auth_user')
        }
      }
    },
    logout(state) {
      state.user = null
      state.token = null
      state.error = null
      state.status = 'idle'
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
      localStorage.removeItem('auth_company_id')
      localStorage.removeItem('auth_company_name')
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(loginThunk.pending, (state) => {
        state.status = 'loading'
        state.error = null
      })
      .addCase(loginThunk.fulfilled, (state, action) => {
        const { token, user } = action.payload
        state.status = 'succeeded'
        state.token = token
        state.user = user
        localStorage.setItem('auth_token', token)
        localStorage.setItem('auth_user', JSON.stringify(user))
        localStorage.setItem('auth_company_id', String(user.companyId))
        localStorage.setItem('auth_company_name', user.companyName || '')
      })
      .addCase(loginThunk.rejected, (state, action) => {
        state.status = 'failed'
        state.error = action.payload || 'Login failed'
      })
  },
})

export const { logout, hydrateAuthFromStorage } = authSlice.actions
export const selectIsAuthenticated = (state) => Boolean(state.auth.token)
export default authSlice.reducer

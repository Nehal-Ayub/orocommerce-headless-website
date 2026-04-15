import { createAsyncThunk, createSlice } from '@reduxjs/toolkit'
import { checkoutService } from '../../services/checkoutService'

export const placeOrder = createAsyncThunk(
  'checkout/placeOrder',
  async (payload, thunkAPI) => {
    try {
      const response = await checkoutService.place(payload)
      return response.data?.data ?? response.data
    } catch (error) {
      return thunkAPI.rejectWithValue(
        error.response?.data?.message || 'Checkout request failed.',
      )
    }
  },
)

const checkoutSlice = createSlice({
  name: 'checkout',
  initialState: {
    status: 'idle',
    step: 'billing',
    placingOrder: false,
    error: null,
    latestOrder: null,
  },
  reducers: {
    setCheckoutStep: (state, action) => {
      state.step = action.payload
    },
    resetCheckoutState: (state) => {
      state.status = 'idle'
      state.step = 'billing'
      state.placingOrder = false
      state.error = null
      state.latestOrder = null
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(placeOrder.pending, (state) => {
        state.status = 'loading'
        state.placingOrder = true
        state.error = null
      })
      .addCase(placeOrder.fulfilled, (state, action) => {
        state.status = 'succeeded'
        state.placingOrder = false
        state.latestOrder = action.payload
      })
      .addCase(placeOrder.rejected, (state, action) => {
        state.status = 'failed'
        state.placingOrder = false
        state.error = action.payload || 'Unable to place order.'
      })
  },
})

export const { resetCheckoutState, setCheckoutStep } = checkoutSlice.actions
export default checkoutSlice.reducer

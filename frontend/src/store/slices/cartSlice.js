import { createAsyncThunk, createSlice } from '@reduxjs/toolkit'
import { cartService } from '../../services/cartService'

const initialState = {
  items: [],
  status: 'idle',
  error: null,
}

export const fetchCart = createAsyncThunk('cart/fetchCart', async (_, { rejectWithValue }) => {
  try {
    const response = await cartService.get()
    return response.data?.data ?? { items: [] }
  } catch (error) {
    return rejectWithValue(error.response?.data?.message ?? 'Failed to fetch cart')
  }
})

export const syncCart = createAsyncThunk('cart/syncCart', async (items, { rejectWithValue }) => {
  try {
    const response = await cartService.sync(items)
    return response.data?.data ?? { items: [] }
  } catch (error) {
    return rejectWithValue(error.response?.data?.message ?? 'Failed to sync cart')
  }
})

export const addItemToCart = createAsyncThunk(
  'cart/addItemToCart',
  async ({ product, quantity }, { rejectWithValue }) => {
    try {
      const response = await cartService.add({
        productId: product.id,
        sku: product.sku,
        name: product.name,
        price: product.price,
        currency: product.currency,
        priceListName: product.priceListName,
        quantity,
        image: product.images?.[0] ?? '',
      })
      return response.data?.data ?? { items: [] }
    } catch (error) {
      return rejectWithValue(error.response?.data?.message ?? 'Failed to add cart item')
    }
  },
)

export const updateCartQuantity = createAsyncThunk(
  'cart/updateCartQuantity',
  async ({ id, quantity }, { getState, rejectWithValue }) => {
    try {
      const currentItems = getState().cart.items
      const updatedItems = currentItems.map((item) =>
        item.id === id ? { ...item, quantity: Math.max(1, quantity) } : item,
      )
      const response = await cartService.sync(updatedItems)
      return response.data?.data ?? { items: [] }
    } catch (error) {
      return rejectWithValue(error.response?.data?.message ?? 'Failed to update cart item')
    }
  },
)

export const removeFromCart = createAsyncThunk(
  'cart/removeFromCart',
  async (id, { getState, rejectWithValue }) => {
    try {
      const remainingItems = getState().cart.items.filter((item) => item.id !== id)
      const response = await cartService.sync({ items: remainingItems })
      return response.data?.data ?? { items: [] }
    } catch (error) {
      return rejectWithValue(error.response?.data?.message ?? 'Failed to remove cart item')
    }
  },
)

const cartSlice = createSlice({
  name: 'cart',
  initialState,
  reducers: {
    clearCart(state) {
      state.items = []
      state.error = null
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchCart.pending, (state) => {
        state.status = 'loading'
        state.error = null
      })
      .addCase(fetchCart.fulfilled, (state, action) => {
        state.status = 'succeeded'
        state.items = action.payload.items ?? []
      })
      .addCase(fetchCart.rejected, (state, action) => {
        state.status = 'failed'
        state.error = action.payload ?? 'Failed to fetch cart'
      })
      .addCase(syncCart.fulfilled, (state, action) => {
        state.items = action.payload.items ?? []
      })
      .addCase(addItemToCart.fulfilled, (state, action) => {
        state.items = action.payload.items ?? []
      })
      .addCase(updateCartQuantity.fulfilled, (state, action) => {
        state.items = action.payload.items ?? []
      })
      .addCase(removeFromCart.fulfilled, (state, action) => {
        state.items = action.payload.items ?? []
      })
  },
})

export const selectCartItems = (state) => state.cart.items
export const selectCartTotal = (state) =>
  state.cart.items.reduce((sum, item) => sum + Number(item.price) * Number(item.quantity), 0)

export const { clearCart } = cartSlice.actions
export default cartSlice.reducer

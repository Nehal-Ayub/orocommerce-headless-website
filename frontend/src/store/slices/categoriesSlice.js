import { createAsyncThunk, createSlice } from '@reduxjs/toolkit'
import { categoryService } from '../../services/categoryService'

const initialState = {
  items: [],
  loading: false,
  error: null,
}

export const fetchCategories = createAsyncThunk(
  'categories/fetchCategories',
  async (_, { rejectWithValue }) => {
    try {
      const response = await categoryService.getCategories()
      return response.items ?? []
    } catch (error) {
      return rejectWithValue(error.response?.data?.message ?? 'Unable to load categories')
    }
  },
)

const categoriesSlice = createSlice({
  name: 'categories',
  initialState,
  reducers: {},
  extraReducers: (builder) => {
    builder
      .addCase(fetchCategories.pending, (state) => {
        state.loading = true
        state.error = null
      })
      .addCase(fetchCategories.fulfilled, (state, action) => {
        state.loading = false
        state.items = action.payload
      })
      .addCase(fetchCategories.rejected, (state, action) => {
        state.loading = false
        state.error = action.payload ?? 'Unable to load categories'
      })
  },
})

export default categoriesSlice.reducer

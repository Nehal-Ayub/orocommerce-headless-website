import { createAsyncThunk, createSlice } from '@reduxjs/toolkit'
import { productService } from '../../services/productService'

const initialState = {
  items: [],
  featured: [],
  categories: [],
  productDetail: null,
  relatedProducts: [],
  pagination: null,
  filters: {
    search: '',
    category: '',
    minPrice: '',
    maxPrice: '',
    page: 1,
    perPage: 12,
  },
  loading: false,
  detailLoading: false,
  error: null,
}

export const fetchProducts = createAsyncThunk(
  'products/fetchProducts',
  async (params = {}, { rejectWithValue }) => {
    try {
      return await productService.getProducts(params)
    } catch (error) {
      return rejectWithValue(error.response?.data?.message ?? 'Unable to fetch products')
    }
  },
)

export const fetchFeaturedProducts = createAsyncThunk(
  'products/fetchFeaturedProducts',
  async (_, { rejectWithValue }) => {
    try {
      const response = await productService.getFeaturedProducts()
      return response?.data ?? []
    } catch (error) {
      return rejectWithValue(error.response?.data?.message ?? 'Unable to fetch featured products')
    }
  },
)

export const fetchProductDetail = createAsyncThunk(
  'products/fetchProductDetail',
  async (id, { rejectWithValue }) => {
    try {
      return await productService.getProductById(id)
    } catch (error) {
      return rejectWithValue(error.response?.data?.message ?? 'Unable to fetch product details')
    }
  },
)

const productsSlice = createSlice({
  name: 'products',
  initialState,
  reducers: {
    setFilters(state, action) {
      state.filters = {
        ...state.filters,
        ...action.payload,
      }
    },
    resetProductDetail(state) {
      state.productDetail = null
      state.relatedProducts = []
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchProducts.pending, (state) => {
        state.loading = true
        state.error = null
      })
      .addCase(fetchProducts.fulfilled, (state, action) => {
        state.loading = false
        state.items = action.payload?.data ?? []
        state.pagination = action.payload?.meta ?? null
      })
      .addCase(fetchProducts.rejected, (state, action) => {
        state.loading = false
        state.error = action.payload ?? 'Failed to fetch products'
      })
      .addCase(fetchFeaturedProducts.fulfilled, (state, action) => {
        state.featured = action.payload
      })
      .addCase(fetchProductDetail.pending, (state) => {
        state.detailLoading = true
      })
      .addCase(fetchProductDetail.fulfilled, (state, action) => {
        state.detailLoading = false
        state.productDetail = action.payload?.data ?? null
        state.relatedProducts = action.payload?.relatedProducts ?? []
      })
      .addCase(fetchProductDetail.rejected, (state, action) => {
        state.detailLoading = false
        state.error = action.payload ?? 'Failed to fetch product detail'
      })
  },
})

export const { setFilters, resetProductDetail } = productsSlice.actions
export default productsSlice.reducer

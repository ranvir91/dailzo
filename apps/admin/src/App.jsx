import { useEffect, useMemo, useState } from 'react';
import ActivityFeed from './components/ActivityFeed';
import AnalyticsCard from './components/AnalyticsCard';
import AuthGate from './components/AuthGate';
import InventoryChart from './components/InventoryChart';
import PanelShell from './components/PanelShell';
import SearchBar from './components/SearchBar';
import StatsCard from './components/StatsCard';
import ThemeToggle from './components/ThemeToggle';
import { API_BASE, authHeaders, clearSession, getSession } from './lib/session';

const PAGE_SIZE = 10;

function formatOrderNumber(orderNumber) {
  return orderNumber ? String(orderNumber).padStart(6, '0') : '-';
}

function formatUserNumber(userNumber) {
  return userNumber ? String(userNumber).padStart(6, '0') : '-';
}

function toDateInputValue(value) {
  if (!value) return '';
  return String(value).slice(0, 10);
}

function formatCouponDiscount(coupon) {
  if (coupon.type === 'PERCENTAGE') {
    const cap = coupon.maxDiscountAmount ? ` (max ₹${coupon.maxDiscountAmount})` : '';
    return `${coupon.discount}% off${cap}`;
  }
  return `₹${coupon.discount} off`;
}

function formatCouponValidity(coupon) {
  const from = toDateInputValue(coupon.startsAt);
  const to = toDateInputValue(coupon.expiresAt);
  if (!from && !to) return 'No expiry';
  if (from && to) return `${from} to ${to}`;
  if (to) return `Until ${to}`;
  return `From ${from}`;
}
const ASSET_BASE_URL = API_BASE.replace('/api/v1', '');

const resolveAssetUrl = (path) => {
  if (!path || /^https?:\/\//i.test(path) || path.startsWith('data:')) {
    return path;
  }

  return `${ASSET_BASE_URL}/${path.replace(/^\//, '')}`;
};

async function apiFetch(path, options = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers: { ...(options.headers ?? {}), ...authHeaders() },
  });

  if (response.status === 401 || response.status === 403) {
    clearSession();
    window.location.reload();
  }

  return response;
}

function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(() => Boolean(getSession()?.accessToken));
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [orders, setOrders] = useState([]);
  const [users, setUsers] = useState([]);
  const [coupons, setCoupons] = useState([]);
  const [storeSettings, setStoreSettings] = useState({
    deliveryCharges: '29',
    minOrderValue: '0',
    minOrderValueEnabled: false,
    maintenanceMode: false,
    appVersion: '1.0.0',
    storeOpenTime: '09:00',
    storeCloseTime: '21:00',
    paymentMethods: ['COD', 'UPI', 'Cards'],
  });
  const [maintenanceToggleBusy, setMaintenanceToggleBusy] = useState(false);
  const [minOrderValueToggleBusy, setMinOrderValueToggleBusy] = useState(false);

  const [activeView, setActiveView] = useState('Overview');
  const [darkMode, setDarkMode] = useState(false);

  const [productForm, setProductForm] = useState({ name: '', description: '', categoryId: '', price: 0, discountedPrice: '', stock: 0, images: [], sortOrder: '0' });
  const [categoryForm, setCategoryForm] = useState({ name: '', description: '', iconUrl: '', isActive: true, sortOrder: '0' });
  const emptyCouponForm = {
    code: '',
    description: '',
    type: 'FIXED',
    discount: '',
    maxDiscountAmount: '',
    minOrderValue: '',
    startsAt: '',
    expiresAt: '',
    usageLimit: '',
    perUserLimit: '1',
    firstOrderOnly: false,
    isActive: true,
  };
  const [couponForm, setCouponForm] = useState(emptyCouponForm);
  const [productFormErrors, setProductFormErrors] = useState({});
  const [categoryFormErrors, setCategoryFormErrors] = useState({});
  const [couponFormErrors, setCouponFormErrors] = useState({});
  const [pincodeFormError, setPincodeFormError] = useState('');
  const [settingsFormErrors, setSettingsFormErrors] = useState({});
  const [editingProductId, setEditingProductId] = useState(null);
  const [editingCategoryId, setEditingCategoryId] = useState(null);
  const [editingCouponId, setEditingCouponId] = useState(null);

  const [productSearch, setProductSearch] = useState('');
  const [categorySearch, setCategorySearch] = useState('');
  const [orderSearch, setOrderSearch] = useState('');
  const [userSearch, setUserSearch] = useState('');
  const [userRoleFilter, setUserRoleFilter] = useState('ALL');
  const [couponSearch, setCouponSearch] = useState('');

  const [selectedCategory, setSelectedCategory] = useState('ALL');
  const [sortMode, setSortMode] = useState('displayOrder');
  const [showLowStockOnly, setShowLowStockOnly] = useState(false);
  const [orderFilter, setOrderFilter] = useState('ALL');
  const [settingsSaved, setSettingsSaved] = useState(false);

  const [servicePincodes, setServicePincodes] = useState([]);
  const [newPincode, setNewPincode] = useState('');
  const [pincodeSearch, setPincodeSearch] = useState('');

  const [productPage, setProductPage] = useState(1);
  const [categoryPage, setCategoryPage] = useState(1);
  const [orderPage, setOrderPage] = useState(1);
  const [customerPage, setCustomerPage] = useState(1);
  const [pincodePage, setPincodePage] = useState(1);
  const [couponPage, setCouponPage] = useState(1);

  const [modalState, setModalState] = useState({ open: false, type: 'product', item: null, loading: false });
  const [modalError, setModalError] = useState('');

  const theme = darkMode
    ? { background: '#020617', card: '#111827', text: '#f8fafc', muted: '#94a3b8' }
    : { background: '#f3f5fb', card: '#ffffff', text: '#0f172a', muted: '#64748b' };

  const getStatusBadgeStyle = (status) => {
    if (status === 'DELIVERED') return { background: '#dcfce7', color: '#166534' };
    if (status === 'CONFIRMED') return { background: '#dbeafe', color: '#1d4ed8' };
    if (status === 'CANCELLED') return { background: '#fee2e2', color: '#b91c1c' };
    return { background: '#fef3c7', color: '#92400e' };
  };

  const loadData = async () => {
    const [productsRes, categoriesRes, ordersRes, usersRes, settingsRes, pincodesRes, couponsRes] = await Promise.all([
      apiFetch(`/products`),
      apiFetch(`/categories`),
      apiFetch(`/orders`),
      apiFetch(`/users`),
      apiFetch(`/settings/store`),
      apiFetch(`/settings/pincodes`),
      apiFetch(`/coupons/admin`),
    ]);

    const productsJson = await productsRes.json();
    const categoriesJson = await categoriesRes.json();
    const ordersJson = await ordersRes.json();
    const usersJson = await usersRes.json();
    const settingsJson = await settingsRes.json();
    const pincodesJson = await pincodesRes.json();
    const couponsJson = await couponsRes.json();

    setProducts(productsJson.data ?? []);
    setCategories(categoriesJson.data ?? []);
    setOrders(ordersJson.data ?? []);
    setUsers(usersJson.data ?? []);
    setServicePincodes(pincodesJson.data ?? []);
    setCoupons(couponsJson.data ?? []);
    if (settingsJson.data) {
      setStoreSettings({
        deliveryCharges: String(settingsJson.data.deliveryCharges ?? '29'),
        minOrderValue: String(settingsJson.data.minOrderValue ?? '0'),
        minOrderValueEnabled: Boolean(settingsJson.data.minOrderValueEnabled),
        maintenanceMode: Boolean(settingsJson.data.maintenanceMode),
        appVersion: settingsJson.data.appVersion ?? '1.0.0',
        storeOpenTime: settingsJson.data.storeOpenTime ?? '09:00',
        storeCloseTime: settingsJson.data.storeCloseTime ?? '21:00',
        paymentMethods: settingsJson.data.paymentMethods ?? [],
      });
    }
  };

  useEffect(() => {
    if (isAuthenticated) {
      loadData();
    }
  }, [isAuthenticated]);

  const validateProductForm = () => {
    const errors = {};
    if (!productForm.name.trim()) errors.name = 'Product name is required';
    if (!productForm.categoryId) errors.categoryId = 'Category is required';
    if (productForm.price === '' || productForm.price === null || Number.isNaN(Number(productForm.price)) || Number(productForm.price) <= 0) {
      errors.price = 'Enter a valid price';
    }
    if (productForm.stock === '' || productForm.stock === null || Number.isNaN(Number(productForm.stock)) || Number(productForm.stock) < 0) {
      errors.stock = 'Enter a valid stock quantity';
    }
    setProductFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const validateCategoryForm = () => {
    const errors = {};
    if (!categoryForm.name.trim()) errors.name = 'Category name is required';
    setCategoryFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const validatePincodeForm = () => {
    const trimmed = newPincode.trim();
    if (!trimmed) {
      setPincodeFormError('Pincode is required');
      return false;
    }
    if (!/^\d{6}$/.test(trimmed)) {
      setPincodeFormError('Enter a valid 6-digit pincode');
      return false;
    }
    setPincodeFormError('');
    return true;
  };

  const validateCouponForm = () => {
    const errors = {};
    if (!couponForm.code.trim()) errors.code = 'Coupon code is required';
    if (!['FIXED', 'PERCENTAGE'].includes(couponForm.type)) errors.type = 'Select a coupon type';
    if (couponForm.discount === '' || couponForm.discount === null || Number.isNaN(Number(couponForm.discount)) || Number(couponForm.discount) <= 0) {
      errors.discount = 'Enter a valid discount value';
    } else if (couponForm.type === 'PERCENTAGE' && Number(couponForm.discount) > 100) {
      errors.discount = 'Percentage discount cannot exceed 100';
    }
    if (couponForm.startsAt && couponForm.expiresAt && new Date(couponForm.startsAt) > new Date(couponForm.expiresAt)) {
      errors.expiresAt = 'End date must be after the start date';
    }
    setCouponFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const validateStoreSettingsForm = () => {
    const errors = {};
    if (storeSettings.deliveryCharges === '' || Number.isNaN(Number(storeSettings.deliveryCharges)) || Number(storeSettings.deliveryCharges) < 0) {
      errors.deliveryCharges = 'Enter a valid delivery charge';
    }
    if (storeSettings.minOrderValueEnabled && (storeSettings.minOrderValue === '' || Number.isNaN(Number(storeSettings.minOrderValue)) || Number(storeSettings.minOrderValue) < 0)) {
      errors.minOrderValue = 'Enter a valid minimum order value';
    }
    if (!storeSettings.appVersion.trim()) errors.appVersion = 'App version is required';
    if (!storeSettings.storeOpenTime) errors.storeOpenTime = 'Store open time is required';
    if (!storeSettings.storeCloseTime) errors.storeCloseTime = 'Store close time is required';
    setSettingsFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const resetProductForm = () => {
    setEditingProductId(null);
    setProductForm({ name: '', description: '', categoryId: '', price: 0, discountedPrice: '', stock: 0, images: [], sortOrder: '0' });
    setProductFormErrors({});
  };

  const resetCategoryForm = () => {
    setEditingCategoryId(null);
    setCategoryForm({ name: '', description: '', iconUrl: '', isActive: true, sortOrder: '0' });
    setCategoryFormErrors({});
  };

  const resetCouponForm = () => {
    setEditingCouponId(null);
    setCouponForm(emptyCouponForm);
    setCouponFormErrors({});
  };

  const closeModal = () => {
    setModalState({ open: false, type: 'product', item: null, loading: false });
    setModalError('');
  };

  const openProductModal = (product = null) => {
    setProductFormErrors({});
    setModalError('');
    if (product) {
      setEditingProductId(product.id);
      setProductForm({
        name: product.name,
        description: product.description ?? '',
        categoryId: product.categoryId ?? product.category?.id ?? '',
        price: product.price,
        discountedPrice: product.discountedPrice ?? '',
        stock: product.stock,
        images: product.images ?? [],
        sortOrder: String(product.sortOrder ?? 0),
      });
    } else {
      resetProductForm();
    }
    setModalState({ open: true, type: 'product', item: product, loading: false });
  };

  const openCategoryModal = (category = null) => {
    setCategoryFormErrors({});
    setModalError('');
    if (category) {
      setEditingCategoryId(category.id);
      setCategoryForm({ name: category.name ?? '', description: category.description ?? '', iconUrl: category.iconUrl ?? '', isActive: category.isActive ?? true, sortOrder: String(category.sortOrder ?? 0) });
    } else {
      resetCategoryForm();
    }
    setModalState({ open: true, type: 'category', item: category, loading: false });
  };

  const openCouponModal = (coupon = null) => {
    setCouponFormErrors({});
    setModalError('');
    if (coupon) {
      setEditingCouponId(coupon.id);
      setCouponForm({
        code: coupon.code ?? '',
        description: coupon.description ?? '',
        type: coupon.type ?? 'FIXED',
        discount: coupon.discount ?? '',
        maxDiscountAmount: coupon.maxDiscountAmount ?? '',
        minOrderValue: coupon.minOrderValue ?? '',
        startsAt: toDateInputValue(coupon.startsAt),
        expiresAt: toDateInputValue(coupon.expiresAt),
        usageLimit: coupon.usageLimit ?? '',
        perUserLimit: coupon.perUserLimit ?? '',
        firstOrderOnly: Boolean(coupon.firstOrderOnly),
        isActive: coupon.isActive ?? true,
      });
    } else {
      resetCouponForm();
    }
    setModalState({ open: true, type: 'coupon', item: coupon, loading: false });
  };

  const openCustomerModal = (customer) => {
    setModalState({ open: true, type: 'customer', item: customer, loading: false });
  };

  const openOrderModal = async (orderId) => {
    setModalState({ open: true, type: 'order', item: null, loading: true });
    try {
      const response = await apiFetch(`/orders/${orderId}`);
      const json = await response.json();
      const fallbackOrder = orders.find((order) => order.id === orderId) ?? null;
      setModalState({ open: true, type: 'order', item: json.data ?? fallbackOrder, loading: false });
    } catch {
      const fallbackOrder = orders.find((order) => order.id === orderId) ?? null;
      setModalState({ open: true, type: 'order', item: fallbackOrder, loading: false });
    }
  };

  const uploadFile = async (file) => {
    const formData = new FormData();
    formData.append('file', file);
    const response = await apiFetch(`/uploads`, {
      method: 'POST',
      body: formData,
    });
    const json = await response.json();
    return json.data?.url ?? null;
  };

  const handleCategoryIconUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }
    const url = await uploadFile(file);
    if (url) {
      setCategoryForm((current) => ({ ...current, iconUrl: url }));
    }
    event.target.value = '';
  };

  const handleProductImagesUpload = async (event) => {
    const files = Array.from(event.target.files ?? []);
    if (!files.length) {
      return;
    }

    const urls = (await Promise.all(files.map((file) => uploadFile(file)))).filter(Boolean);
    if (urls.length) {
      setProductForm((current) => ({
        ...current,
        images: [...current.images, ...urls],
      }));
    }
    event.target.value = '';
  };

  const createProduct = async (event) => {
    event.preventDefault();
    if (!validateProductForm()) {
      return;
    }
    setModalError('');
    const payload = {
      ...productForm,
      price: Number(productForm.price),
      stock: Number(productForm.stock),
      categoryId: productForm.categoryId,
      images: productForm.images,
      sortOrder: productForm.sortOrder === '' ? 0 : Number(productForm.sortOrder),
    };

    payload.discountedPrice = productForm.discountedPrice === '' || productForm.discountedPrice === null
      ? null
      : Number(productForm.discountedPrice);

    const response = await apiFetch(`/products`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const json = await response.json();
    if (json.success) {
      resetProductForm();
      closeModal();
      loadData();
    } else {
      setModalError(json.message || 'Unable to create product. Please try again.');
    }
  };

  const updateProduct = async (event) => {
    event.preventDefault();
    if (!validateProductForm()) {
      return;
    }
    setModalError('');
    const payload = {
      ...productForm,
      price: Number(productForm.price),
      stock: Number(productForm.stock),
      categoryId: productForm.categoryId,
      images: productForm.images,
      sortOrder: productForm.sortOrder === '' ? 0 : Number(productForm.sortOrder),
    };

    payload.discountedPrice = productForm.discountedPrice === '' || productForm.discountedPrice === null
      ? null
      : Number(productForm.discountedPrice);

    const response = await apiFetch(`/products/${editingProductId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const json = await response.json();
    if (json.success) {
      resetProductForm();
      closeModal();
      loadData();
    } else {
      setModalError(json.message || 'Unable to save product. Please try again.');
    }
  };

  const deleteProduct = async (id) => {
    const confirmed = window.confirm('Delete this product?');
    if (!confirmed) {
      return;
    }

    const response = await apiFetch(`/products/${id}`, { method: 'DELETE' });
    const json = await response.json();
    if (json.success) {
      loadData();
    }
  };

  const createCategory = async (event) => {
    event.preventDefault();
    if (!validateCategoryForm()) {
      return;
    }
    setModalError('');
    const response = await apiFetch(`/categories`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: categoryForm.name, description: categoryForm.description || null, iconUrl: categoryForm.iconUrl || null, sortOrder: categoryForm.sortOrder === '' ? 0 : Number(categoryForm.sortOrder) }),
    });
    const json = await response.json();
    if (json.success) {
      resetCategoryForm();
      closeModal();
      loadData();
    } else {
      setModalError(json.message || 'Unable to create category. Please try again.');
    }
  };

  const updateCategory = async (event) => {
    event.preventDefault();
    if (!validateCategoryForm()) {
      return;
    }
    setModalError('');
    const response = await apiFetch(`/categories/${editingCategoryId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: categoryForm.name, description: categoryForm.description || null, iconUrl: categoryForm.iconUrl || null, isActive: categoryForm.isActive, sortOrder: categoryForm.sortOrder === '' ? 0 : Number(categoryForm.sortOrder) }),
    });
    const json = await response.json();
    if (json.success) {
      resetCategoryForm();
      closeModal();
      loadData();
    } else {
      setModalError(json.message || 'Unable to save category. Please try again.');
    }
  };

  const updateCategoryStatus = async (id, isActive) => {
    const response = await apiFetch(`/categories/${id}/status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ isActive }),
    });
    const json = await response.json();
    if (json.success) {
      loadData();
    }
  };

  const deleteCategory = async (id) => {
    const confirmed = window.confirm('Delete this category?');
    if (!confirmed) {
      return;
    }

    const response = await apiFetch(`/categories/${id}`, { method: 'DELETE' });
    const json = await response.json();
    if (json.success) {
      loadData();
    }
  };

  const buildCouponPayload = () => ({
    code: couponForm.code.trim(),
    description: couponForm.description || null,
    type: couponForm.type,
    discount: Number(couponForm.discount),
    maxDiscountAmount: couponForm.maxDiscountAmount === '' ? null : Number(couponForm.maxDiscountAmount),
    minOrderValue: couponForm.minOrderValue === '' ? 0 : Number(couponForm.minOrderValue),
    startsAt: couponForm.startsAt || null,
    expiresAt: couponForm.expiresAt || null,
    usageLimit: couponForm.usageLimit === '' ? null : Number(couponForm.usageLimit),
    perUserLimit: couponForm.perUserLimit === '' ? null : Number(couponForm.perUserLimit),
    firstOrderOnly: couponForm.firstOrderOnly,
    isActive: couponForm.isActive,
  });

  const createCoupon = async (event) => {
    event.preventDefault();
    if (!validateCouponForm()) {
      return;
    }
    setModalError('');
    const response = await apiFetch(`/coupons`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(buildCouponPayload()),
    });
    const json = await response.json();
    if (json.success) {
      resetCouponForm();
      closeModal();
      loadData();
    } else {
      setModalError(json.message || 'Unable to create coupon. Please try again.');
    }
  };

  const updateCoupon = async (event) => {
    event.preventDefault();
    if (!validateCouponForm()) {
      return;
    }
    setModalError('');
    const response = await apiFetch(`/coupons/${editingCouponId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(buildCouponPayload()),
    });
    const json = await response.json();
    if (json.success) {
      resetCouponForm();
      closeModal();
      loadData();
    } else {
      setModalError(json.message || 'Unable to save coupon. Please try again.');
    }
  };

  const updateCouponStatus = async (id, isActive) => {
    const response = await apiFetch(`/coupons/${id}/status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ isActive }),
    });
    const json = await response.json();
    if (json.success) {
      loadData();
    }
  };

  const deleteCoupon = async (id) => {
    const confirmed = window.confirm('Delete this coupon?');
    if (!confirmed) {
      return;
    }

    const response = await apiFetch(`/coupons/${id}`, { method: 'DELETE' });
    const json = await response.json();
    if (json.success) {
      loadData();
    }
  };

  const updateOrderStatus = async (orderId, status) => {
    const response = await apiFetch(`/orders/${orderId}/status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status }),
    });
    const json = await response.json();
    if (json.success) {
      loadData();
    }
  };

  const saveStoreSettings = async () => {
    if (!validateStoreSettingsForm()) {
      return;
    }

    const response = await apiFetch(`/settings/store`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        deliveryCharges: Number(storeSettings.deliveryCharges),
        minOrderValue: Number(storeSettings.minOrderValue),
        minOrderValueEnabled: storeSettings.minOrderValueEnabled,
        appVersion: storeSettings.appVersion,
        storeOpenTime: storeSettings.storeOpenTime,
        storeCloseTime: storeSettings.storeCloseTime,
        paymentMethods: storeSettings.paymentMethods,
      }),
    });
    const json = await response.json();
    if (json.success) {
      setStoreSettings((current) => ({
        ...current,
        deliveryCharges: String(json.data.deliveryCharges ?? current.deliveryCharges),
        minOrderValue: String(json.data.minOrderValue ?? current.minOrderValue),
        minOrderValueEnabled: Boolean(json.data.minOrderValueEnabled),
        appVersion: json.data.appVersion ?? current.appVersion,
        storeOpenTime: json.data.storeOpenTime ?? current.storeOpenTime,
        storeCloseTime: json.data.storeCloseTime ?? current.storeCloseTime,
        paymentMethods: json.data.paymentMethods ?? current.paymentMethods,
      }));
      setSettingsSaved(true);
      window.setTimeout(() => setSettingsSaved(false), 1800);
    }
  };

  const toggleMaintenanceMode = async () => {
    setMaintenanceToggleBusy(true);
    try {
      const nextValue = !storeSettings.maintenanceMode;
      const response = await apiFetch(`/settings/store/maintenance-mode`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ maintenanceMode: nextValue }),
      });
      const json = await response.json();
      if (json.success) {
        setStoreSettings((current) => ({ ...current, maintenanceMode: Boolean(json.data.maintenanceMode) }));
      }
    } finally {
      setMaintenanceToggleBusy(false);
    }
  };

  const toggleMinOrderValueEnabled = async () => {
    if (!storeSettings.minOrderValueEnabled) {
      const value = Number(storeSettings.minOrderValue);
      if (storeSettings.minOrderValue === '' || Number.isNaN(value) || value <= 0) {
        setSettingsFormErrors((current) => ({ ...current, minOrderValue: 'Enter a valid minimum order value before enabling' }));
        return;
      }
    }
    setMinOrderValueToggleBusy(true);
    try {
      const nextValue = !storeSettings.minOrderValueEnabled;
      const response = await apiFetch(`/settings/store/min-order-value`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ enabled: nextValue, minOrderValue: Number(storeSettings.minOrderValue) }),
      });
      const json = await response.json();
      if (json.success) {
        setSettingsFormErrors((current) => ({ ...current, minOrderValue: undefined }));
        setStoreSettings((current) => ({
          ...current,
          minOrderValueEnabled: Boolean(json.data.minOrderValueEnabled),
          minOrderValue: String(json.data.minOrderValue ?? current.minOrderValue),
        }));
      }
    } finally {
      setMinOrderValueToggleBusy(false);
    }
  };

  const addServicePincode = async (event) => {
    event.preventDefault();
    if (!validatePincodeForm()) {
      return;
    }
    const trimmed = newPincode.trim();

    const response = await apiFetch(`/settings/pincodes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ pincode: trimmed, isActive: true }),
    });
    const json = await response.json();
    if (json.success) {
      setNewPincode('');
      setPincodeFormError('');
      loadData();
    } else {
      setPincodeFormError(json.message || 'Unable to add pincode. Please try again.');
    }
  };

  const togglePincodeStatus = async (id, isActive) => {
    const response = await apiFetch(`/settings/pincodes/${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ isActive }),
    });
    const json = await response.json();
    if (json.success) {
      loadData();
    }
  };

  const deleteServicePincode = async (id) => {
    const confirmed = window.confirm('Remove this pincode?');
    if (!confirmed) {
      return;
    }

    const response = await apiFetch(`/settings/pincodes/${id}`, { method: 'DELETE' });
    const json = await response.json();
    if (json.success) {
      loadData();
    }
  };

  const visibleProducts = useMemo(() => {
    const filtered = products
      .filter((product) => product.name.toLowerCase().includes(productSearch.toLowerCase()))
      .filter((product) => selectedCategory === 'ALL' || product.categoryId === selectedCategory)
      .filter((product) => !showLowStockOnly || Number(product.stock) < 10);

    return [...filtered].sort((a, b) => {
      if (sortMode === 'price') return Number(a.price) - Number(b.price);
      if (sortMode === 'stock') return Number(a.stock) - Number(b.stock);
      if (sortMode === 'name') return a.name.localeCompare(b.name);
      return Number(a.sortOrder ?? 0) - Number(b.sortOrder ?? 0);
    });
  }, [products, productSearch, selectedCategory, showLowStockOnly, sortMode]);

  const visibleCategories = useMemo(() => {
    return categories.filter((category) => (category.name ?? '').toLowerCase().includes(categorySearch.toLowerCase()));
  }, [categories, categorySearch]);

  const productCategoryOptions = useMemo(() => {
    const activeCategories = categories.filter((category) => category.isActive !== false);
    const options = activeCategories.map((category) => ({ value: category.id, label: category.name }));
    const hasCurrentCategory = options.some((category) => category.value === productForm.categoryId);

    if (!hasCurrentCategory && productForm.categoryId) {
      const currentCategory = categories.find((category) => category.id === productForm.categoryId);
      options.unshift({ value: productForm.categoryId, label: currentCategory?.name ?? 'Selected category' });
    }

    return options;
  }, [categories, productForm.categoryId]);

  const visibleOrders = useMemo(() => {
    const query = orderSearch.trim().toLowerCase();
    return orders
      .filter((order) => !query || formatOrderNumber(order.orderNumber).toLowerCase().includes(query) || (order.id ?? '').toLowerCase().includes(query))
      .filter((order) => (orderFilter === 'ALL' ? true : order.status === orderFilter));
  }, [orders, orderSearch, orderFilter]);

  const paymentMethodsText = useMemo(() => storeSettings.paymentMethods.join(', '), [storeSettings.paymentMethods]);

  const visibleServicePincodes = useMemo(() => {
    return servicePincodes.filter((entry) => entry.pincode.toLowerCase().includes(pincodeSearch.toLowerCase()));
  }, [servicePincodes, pincodeSearch]);

  const visibleUsers = useMemo(() => {
    const query = userSearch.trim().toLowerCase();
    return users
      .filter((user) => !query || `${user.name ?? ''} ${user.phone ?? ''} ${formatUserNumber(user.userNumber)}`.toLowerCase().includes(query))
      .filter((user) => (userRoleFilter === 'ALL' ? true : user.role === userRoleFilter));
  }, [users, userSearch, userRoleFilter]);

  const visibleCoupons = useMemo(() => {
    const query = couponSearch.trim().toLowerCase();
    return coupons.filter((coupon) => !query || coupon.code.toLowerCase().includes(query) || (coupon.description ?? '').toLowerCase().includes(query));
  }, [coupons, couponSearch]);

  const pagedProducts = visibleProducts.slice((productPage - 1) * PAGE_SIZE, productPage * PAGE_SIZE);
  const pagedCategories = visibleCategories.slice((categoryPage - 1) * PAGE_SIZE, categoryPage * PAGE_SIZE);
  const pagedOrders = visibleOrders.slice((orderPage - 1) * PAGE_SIZE, orderPage * PAGE_SIZE);
  const pagedUsers = visibleUsers.slice((customerPage - 1) * PAGE_SIZE, customerPage * PAGE_SIZE);
  const pagedServicePincodes = visibleServicePincodes.slice((pincodePage - 1) * PAGE_SIZE, pincodePage * PAGE_SIZE);
  const pagedCoupons = visibleCoupons.slice((couponPage - 1) * PAGE_SIZE, couponPage * PAGE_SIZE);

  const productTotalPages = Math.max(1, Math.ceil(visibleProducts.length / PAGE_SIZE));
  const categoryTotalPages = Math.max(1, Math.ceil(visibleCategories.length / PAGE_SIZE));
  const orderTotalPages = Math.max(1, Math.ceil(visibleOrders.length / PAGE_SIZE));
  const customerTotalPages = Math.max(1, Math.ceil(visibleUsers.length / PAGE_SIZE));
  const pincodeTotalPages = Math.max(1, Math.ceil(visibleServicePincodes.length / PAGE_SIZE));
  const couponTotalPages = Math.max(1, Math.ceil(visibleCoupons.length / PAGE_SIZE));

  useEffect(() => {
    setProductPage(1);
  }, [productSearch, selectedCategory, showLowStockOnly, sortMode]);

  useEffect(() => {
    setCategoryPage(1);
  }, [categorySearch]);

  useEffect(() => {
    setOrderPage(1);
  }, [orderSearch, orderFilter]);

  useEffect(() => {
    setCustomerPage(1);
  }, [userSearch, userRoleFilter]);

  useEffect(() => {
    setPincodePage(1);
  }, [pincodeSearch]);

  useEffect(() => {
    setCouponPage(1);
  }, [couponSearch]);

  const renderProductsView = () => (
    <div style={{ color: theme.text, display: 'grid', gap: 24 }}>
      <section style={cardStyle}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12, gap: 10, flexWrap: 'wrap' }}>
          <h2 style={{ margin: 0 }}>Products</h2>
          <button style={primaryButton} onClick={() => openProductModal()}>Add Product</button>
        </div>
        <div style={{ display: 'grid', gap: 10, gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', marginBottom: 12 }}>
          <SearchBar value={productSearch} onChange={setProductSearch} placeholder="Search products" />
          <select value={selectedCategory} onChange={(e) => setSelectedCategory(e.target.value)} style={selectStyle}>
            <option value="ALL">All categories</option>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>{category.name}{category.isActive === false ? ' (Inactive)' : ''}</option>
            ))}
          </select>
          <select value={sortMode} onChange={(e) => setSortMode(e.target.value)} style={selectStyle}>
            <option value="displayOrder">Sort by display order (storefront)</option>
            <option value="name">Sort by name</option>
            <option value="price">Sort by price</option>
            <option value="stock">Sort by stock</option>
          </select>
          <label style={{ display: 'flex', alignItems: 'center', gap: 8, color: '#64748b', background: '#f8fafc', borderRadius: 10, padding: '0 12px' }}>
            <input type="checkbox" checked={showLowStockOnly} onChange={(e) => setShowLowStockOnly(e.target.checked)} />
            Low stock only
          </label>
        </div>
        <div style={{ overflowX: 'auto' }}>
          <table style={tableStyle}>
            <thead>
              <tr style={tableHeadRowStyle}>
                <th style={tableHeadCellStyle}>Order</th>
                <th style={tableHeadCellStyle}>Name</th>
                <th style={tableHeadCellStyle}>Description</th>
                <th style={tableHeadCellStyle}>Category</th>
                <th style={tableHeadCellStyle}>Price</th>
                <th style={tableHeadCellStyle}>Discounted</th>
                <th style={tableHeadCellStyle}>Stock</th>
                <th style={tableHeadCellStyle}>Images</th>
                <th style={tableHeadCellStyle}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {pagedProducts.map((product) => (
                <tr key={product.id} style={tableBodyRowStyle}>
                  <td style={{ ...tableCellStyle, color: '#64748b' }}>{product.sortOrder ?? 0}</td>
                  <td style={tableCellStyle}>
                    <strong>{product.name}</strong>
                  </td>
                  <td style={{ ...tableCellStyle, maxWidth: 240, color: '#64748b' }}>{product.description || '-'}</td>
                  <td style={tableCellStyle}>{product.category?.name ?? product.category ?? '-'}</td>
                  <td style={tableCellStyle}>₹{product.price}</td>
                  <td style={tableCellStyle}>{product.discountedPrice ? `₹${product.discountedPrice}` : '-'}</td>
                  <td style={tableCellStyle}>{product.stock}</td>
                  <td style={tableCellStyle}>
                    {product.images?.[0] ? (
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <img src={resolveAssetUrl(product.images[0])} alt={product.name} style={{ width: 32, height: 32, objectFit: 'cover', borderRadius: 10, border: '1px solid #e2e8f0' }} />
                        {product.images.length > 1 ? <span style={{ color: '#64748b', fontSize: 12 }}>+{product.images.length - 1}</span> : null}
                      </div>
                    ) : (
                      <div style={{ width: 32, height: 32, borderRadius: 10, background: '#eef2ff', display: 'grid', placeItems: 'center', color: '#4338ca', fontWeight: 700 }}>◌</div>
                    )}
                  </td>
                  <td style={tableCellStyle}>
                    <div style={{ display: 'flex', gap: 8 }}>
                      <button style={secondaryButton} onClick={() => openProductModal(product)}>Edit</button>
                      <button style={dangerButton} onClick={() => deleteProduct(product.id)}>Delete</button>
                    </div>
                  </td>
                </tr>
              ))}
              {!pagedProducts.length ? (
                <tr>
                  <td style={emptyCellStyle} colSpan={9}>No products found.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        <Pagination page={productPage} totalPages={productTotalPages} onPrev={() => setProductPage((page) => Math.max(1, page - 1))} onNext={() => setProductPage((page) => Math.min(productTotalPages, page + 1))} />
      </section>
    </div>
  );

  const renderCategoriesView = () => (
    <div style={{ color: theme.text, display: 'grid', gap: 24 }}>
      <section style={cardStyle}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12, gap: 10, flexWrap: 'wrap' }}>
          <h2 style={{ margin: 0 }}>Categories</h2>
          <button style={primaryButton} onClick={() => openCategoryModal()}>Add Category</button>
        </div>
        <div style={{ marginBottom: 12 }}>
          <SearchBar value={categorySearch} onChange={setCategorySearch} placeholder="Search categories" />
        </div>
        <div style={{ overflowX: 'auto' }}>
          <table style={tableStyle}>
            <thead>
              <tr style={tableHeadRowStyle}>
                <th style={tableHeadCellStyle}>Order</th>
                <th style={tableHeadCellStyle}>Icon</th>
                <th style={tableHeadCellStyle}>Name</th>
                <th style={tableHeadCellStyle}>Description</th>
                <th style={tableHeadCellStyle}>Status</th>
                <th style={tableHeadCellStyle}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {pagedCategories.map((category) => (
                <tr key={category.id} style={tableBodyRowStyle}>
                  <td style={{ ...tableCellStyle, color: '#64748b' }}>{category.sortOrder ?? 0}</td>
                  <td style={tableCellStyle}>
                    {category.iconUrl ? (
                      <img src={resolveAssetUrl(category.iconUrl)} alt={category.name} style={{ width: 32, height: 32, objectFit: 'cover', borderRadius: 10, border: '1px solid #e2e8f0' }} />
                    ) : (
                      <div style={{ width: 32, height: 32, borderRadius: 10, background: '#eef2ff', display: 'grid', placeItems: 'center', color: '#4338ca', fontWeight: 700 }}>◌</div>
                    )}
                  </td>
                  <td style={tableCellStyle}>
                    <strong>{category.name}</strong>
                    <div style={{ color: '#64748b', fontSize: 13, marginTop: 4 }}>{category.slug}</div>
                  </td>
                  <td style={{ ...tableCellStyle, maxWidth: 260, color: '#64748b' }}>{category.description || '-'}</td>
                  <td style={tableCellStyle}>
                    <span style={{ padding: '6px 10px', borderRadius: 999, fontWeight: 700, fontSize: 12, background: category.isActive ? '#dcfce7' : '#fee2e2', color: category.isActive ? '#166534' : '#b91c1c' }}>
                      {category.isActive ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td style={tableCellStyle}>
                    <div style={{ display: 'flex', gap: 8 }}>
                      <button style={secondaryButton} onClick={() => openCategoryModal(category)}>Edit</button>
                      <button style={secondaryButton} onClick={() => updateCategoryStatus(category.id, !category.isActive)}>{category.isActive ? 'Deactivate' : 'Activate'}</button>
                      <button style={dangerButton} onClick={() => deleteCategory(category.id)}>Delete</button>
                    </div>
                  </td>
                </tr>
              ))}
              {!pagedCategories.length ? (
                <tr>
                  <td style={emptyCellStyle} colSpan={6}>No categories found.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        <Pagination page={categoryPage} totalPages={categoryTotalPages} onPrev={() => setCategoryPage((page) => Math.max(1, page - 1))} onNext={() => setCategoryPage((page) => Math.min(categoryTotalPages, page + 1))} />
      </section>
    </div>
  );

  const renderOrdersView = () => (
    <div style={{ color: theme.text, display: 'grid', gap: 24 }}>
      <section style={cardStyle}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 12 }}>
          <h2 style={{ margin: 0 }}>Orders</h2>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <div style={{ minWidth: 220 }}>
              <SearchBar value={orderSearch} onChange={setOrderSearch} placeholder="Search by order ID" />
            </div>
            <select value={orderFilter} onChange={(e) => setOrderFilter(e.target.value)} style={selectStyle}>
              <option value="ALL">All</option>
              <option value="PENDING">Pending</option>
              <option value="CONFIRMED">Confirmed</option>
              <option value="PROCESSING">Processing</option>
              <option value="DELIVERED">Delivered</option>
              <option value="CANCELLED">Cancelled</option>
            </select>
          </div>
        </div>
        <div style={{ overflowX: 'auto' }}>
          <table style={tableStyle}>
            <thead>
              <tr style={tableHeadRowStyle}>
                <th style={tableHeadCellStyle}>Order ID</th>
                <th style={tableHeadCellStyle}>Total</th>
                <th style={tableHeadCellStyle}>Status</th>
                <th style={tableHeadCellStyle}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {pagedOrders.map((order) => (
                <tr key={order.id} style={tableBodyRowStyle}>
                  <td style={tableCellStyle}>
                    <button type="button" onClick={() => openOrderModal(order.id)} style={linkButtonStyle}>
                      {formatOrderNumber(order.orderNumber)}
                    </button>
                  </td>
                  <td style={tableCellStyle}>₹{order.total ?? '-'}</td>
                  <td style={tableCellStyle}><span style={{ ...getStatusBadgeStyle(order.status), padding: '6px 10px', borderRadius: 999, fontWeight: 700, fontSize: 12 }}>{order.status}</span></td>
                  <td style={tableCellStyle}>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                      <button style={secondaryButton} onClick={() => updateOrderStatus(order.id, 'CONFIRMED')}>Confirm</button>
                      <button style={secondaryButton} onClick={() => updateOrderStatus(order.id, 'CANCELLED')}>Cancel</button>
                      <button style={secondaryButton} onClick={() => updateOrderStatus(order.id, 'DELIVERED')}>Deliver</button>
                    </div>
                  </td>
                </tr>
              ))}
              {!pagedOrders.length ? (
                <tr>
                  <td style={emptyCellStyle} colSpan={4}>No orders found.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        <Pagination page={orderPage} totalPages={orderTotalPages} onPrev={() => setOrderPage((page) => Math.max(1, page - 1))} onNext={() => setOrderPage((page) => Math.min(orderTotalPages, page + 1))} />
      </section>
    </div>
  );

  const renderCustomersView = () => (
    <div style={{ color: theme.text, display: 'grid', gap: 24 }}>
      <section style={cardStyle}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12, gap: 10, flexWrap: 'wrap' }}>
          <h2 style={{ margin: 0 }}>Customers</h2>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <div style={{ width: 260 }}>
              <SearchBar value={userSearch} onChange={setUserSearch} placeholder="Search customers or ID" />
            </div>
            <select value={userRoleFilter} onChange={(e) => setUserRoleFilter(e.target.value)} style={selectStyle}>
              <option value="ALL">All roles</option>
              <option value="ADMIN">Admin</option>
              <option value="CUSTOMER">Customer</option>
            </select>
          </div>
        </div>
        <div style={{ overflowX: 'auto' }}>
          <table style={tableStyle}>
            <thead>
              <tr style={tableHeadRowStyle}>
                <th style={tableHeadCellStyle}>Customer ID</th>
                <th style={tableHeadCellStyle}>Customer</th>
                <th style={tableHeadCellStyle}>Role</th>
                <th style={tableHeadCellStyle}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {pagedUsers.map((user) => (
                <tr key={user.id} style={tableBodyRowStyle}>
                  <td style={tableCellStyle}>{formatUserNumber(user.userNumber)}</td>
                  <td style={tableCellStyle}>
                    <strong>{user.name ?? 'Customer'}</strong>
                    <div style={{ color: '#64748b', fontSize: 13, marginTop: 4 }}>{user.phone}</div>
                  </td>
                  <td style={tableCellStyle}>{user.role}</td>
                  <td style={tableCellStyle}><button style={secondaryButton} onClick={() => openCustomerModal(user)}>View</button></td>
                </tr>
              ))}
              {!pagedUsers.length ? (
                <tr>
                  <td style={emptyCellStyle} colSpan={4}>No customers found.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        <Pagination page={customerPage} totalPages={customerTotalPages} onPrev={() => setCustomerPage((page) => Math.max(1, page - 1))} onNext={() => setCustomerPage((page) => Math.min(customerTotalPages, page + 1))} />
      </section>
    </div>
  );

  const renderSettingsView = () => (
    <div style={{ color: theme.text, display: 'grid', gap: 24 }}>
      <section style={cardStyle}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 16 }}>
          <div>
            <h2 style={{ margin: 0 }}>Store Settings</h2>
            <p style={{ margin: '6px 0 0', color: '#64748b' }}>Configure operational values used across the admin and storefront.</p>
          </div>
          <button
            type="button"
            style={primaryButton}
            onClick={saveStoreSettings}
          >
            Save Settings
          </button>
        </div>
        {settingsSaved ? <div style={{ ...badgeStyle, marginBottom: 16, background: '#dcfce7', color: '#166534' }}>Settings saved successfully</div> : null}
        <div style={{ display: 'grid', gap: 16, gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))' }}>
          <SettingField label="Delivery Charges *" description="Flat delivery fee charged at checkout.">
            <input
              style={withFieldError(inputStyle, settingsFormErrors.deliveryCharges)}
              type="number"
              value={storeSettings.deliveryCharges}
              onChange={(e) => setStoreSettings({ ...storeSettings, deliveryCharges: e.target.value })}
            />
            <FieldError message={settingsFormErrors.deliveryCharges} />
          </SettingField>
          <SettingField label={storeSettings.minOrderValueEnabled ? 'Minimum Order Value *' : 'Minimum Order Value'} description="Minimum cart value required to place an order on the app.">
            <div style={{ display: 'flex', gap: 8, alignItems: 'flex-start' }}>
              <input
                style={{ ...withFieldError(inputStyle, settingsFormErrors.minOrderValue), flex: 1 }}
                type="number"
                value={storeSettings.minOrderValue}
                onChange={(e) => setStoreSettings({ ...storeSettings, minOrderValue: e.target.value })}
              />
              <button
                type="button"
                style={{
                  ...(storeSettings.minOrderValueEnabled ? dangerButton : primaryButton),
                  padding: '10px 12px',
                  whiteSpace: 'nowrap',
                }}
                onClick={toggleMinOrderValueEnabled}
                disabled={minOrderValueToggleBusy}
              >
                {minOrderValueToggleBusy ? 'Please wait…' : storeSettings.minOrderValueEnabled ? 'Disable' : 'Enable'}
              </button>
            </div>
            <FieldError message={settingsFormErrors.minOrderValue} />
            <span style={{ display: 'inline-block', marginTop: 6, padding: '4px 10px', borderRadius: 999, fontWeight: 700, fontSize: 12, background: storeSettings.minOrderValueEnabled ? '#dcfce7' : '#f1f5f9', color: storeSettings.minOrderValueEnabled ? '#166534' : '#64748b' }}>
              {storeSettings.minOrderValueEnabled ? `Enforced: orders below ₹${storeSettings.minOrderValue || 0} are blocked` : 'Not enforced'}
            </span>
          </SettingField>
          <SettingField label="App Version *" description="Current admin/store application version.">
            <input
              style={withFieldError(inputStyle, settingsFormErrors.appVersion)}
              value={storeSettings.appVersion}
              onChange={(e) => setStoreSettings({ ...storeSettings, appVersion: e.target.value })}
            />
            <FieldError message={settingsFormErrors.appVersion} />
          </SettingField>
          <SettingField label="Store Timings *" description="Store opening and closing hours.">
            <div style={{ display: 'grid', gap: 10, gridTemplateColumns: '1fr 1fr' }}>
              <div>
                <input
                  style={withFieldError(inputStyle, settingsFormErrors.storeOpenTime)}
                  type="time"
                  value={storeSettings.storeOpenTime}
                  onChange={(e) => setStoreSettings({ ...storeSettings, storeOpenTime: e.target.value })}
                />
                <FieldError message={settingsFormErrors.storeOpenTime} />
              </div>
              <div>
                <input
                  style={withFieldError(inputStyle, settingsFormErrors.storeCloseTime)}
                  type="time"
                  value={storeSettings.storeCloseTime}
                  onChange={(e) => setStoreSettings({ ...storeSettings, storeCloseTime: e.target.value })}
                />
                <FieldError message={settingsFormErrors.storeCloseTime} />
              </div>
            </div>
          </SettingField>
          <SettingField label="Payment Methods" description="Comma-separated methods available at checkout.">
            <textarea
              style={{ ...inputStyle, minHeight: 96, resize: 'vertical' }}
              value={paymentMethodsText}
              onChange={(e) => setStoreSettings({ ...storeSettings, paymentMethods: e.target.value.split(',').map((method) => method.trim()).filter(Boolean) })}
            />
          </SettingField>
        </div>
      </section>

      <section style={cardStyle}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
          <div>
            <h2 style={{ margin: 0 }}>Maintenance Mode</h2>
            <p style={{ margin: '6px 0 0', color: '#64748b' }}>
              {storeSettings.maintenanceMode
                ? 'The frontend app is currently disabled and customers will see a maintenance screen.'
                : 'The frontend app is live and accessible to customers.'}
            </p>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <span style={{ padding: '6px 10px', borderRadius: 999, fontWeight: 700, fontSize: 12, background: storeSettings.maintenanceMode ? '#fee2e2' : '#dcfce7', color: storeSettings.maintenanceMode ? '#b91c1c' : '#166534' }}>
              {storeSettings.maintenanceMode ? 'Disabled' : 'Live'}
            </span>
            <button
              type="button"
              style={storeSettings.maintenanceMode ? primaryButton : dangerButton}
              onClick={toggleMaintenanceMode}
              disabled={maintenanceToggleBusy}
            >
              {maintenanceToggleBusy ? 'Please wait…' : storeSettings.maintenanceMode ? 'Enable App' : 'Disable App'}
            </button>
          </div>
        </div>
      </section>

      {/* <section style={cardStyle}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
          <div>
            <h2 style={{ margin: 0 }}>Appearance</h2>
            <p style={{ margin: '6px 0 0', color: '#64748b' }}>Switch between light and dark mode for the admin panel.</p>
          </div>
          <ThemeToggle darkMode={darkMode} onToggle={() => setDarkMode((value) => !value)} />
        </div>
      </section> */}
    </div>
  );

  const renderServiceablePincodesView = () => (
    <div style={{ color: theme.text, display: 'grid', gap: 24 }}>
      <section style={cardStyle}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 16 }}>
          <div>
            <h2 style={{ margin: 0 }}>Serviceable Pincodes</h2>
            <p style={{ margin: '6px 0 0', color: '#64748b' }}>Pincodes enabled here are used by the storefront to allow or block Add to Cart.</p>
          </div>
          <div style={{ width: 220 }}>
            <SearchBar value={pincodeSearch} onChange={setPincodeSearch} placeholder="Search pincode" />
          </div>
        </div>
        <form onSubmit={addServicePincode} noValidate style={{ marginBottom: 16 }}>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <input
              style={{ ...withFieldError(inputStyle, pincodeFormError), flex: '1 1 200px' }}
              placeholder="Enter pincode e.g. 400001 *"
              value={newPincode}
              onChange={(e) => setNewPincode(e.target.value)}
            />
            <button type="submit" style={primaryButton}>Add Pincode</button>
          </div>
          <FieldError message={pincodeFormError} />
        </form>
        <div style={{ overflowX: 'auto' }}>
          <table style={tableStyle}>
            <thead>
              <tr style={tableHeadRowStyle}>
                <th style={tableHeadCellStyle}>Pincode</th>
                <th style={tableHeadCellStyle}>Status</th>
                <th style={tableHeadCellStyle}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {pagedServicePincodes.map((entry) => (
                <tr key={entry.id} style={tableBodyRowStyle}>
                  <td style={tableCellStyle}><strong>{entry.pincode}</strong></td>
                  <td style={tableCellStyle}>
                    <span style={{ padding: '6px 10px', borderRadius: 999, fontWeight: 700, fontSize: 12, background: entry.isActive ? '#dcfce7' : '#fee2e2', color: entry.isActive ? '#166534' : '#b91c1c' }}>
                      {entry.isActive ? 'Serviceable' : 'Disabled'}
                    </span>
                  </td>
                  <td style={tableCellStyle}>
                    <div style={{ display: 'flex', gap: 8 }}>
                      <button style={secondaryButton} onClick={() => togglePincodeStatus(entry.id, !entry.isActive)}>
                        {entry.isActive ? 'Disable' : 'Enable'}
                      </button>
                      <button style={dangerButton} onClick={() => deleteServicePincode(entry.id)}>Remove</button>
                    </div>
                  </td>
                </tr>
              ))}
              {!pagedServicePincodes.length ? (
                <tr>
                  <td style={emptyCellStyle} colSpan={3}>No service pincodes added yet.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        <Pagination page={pincodePage} totalPages={pincodeTotalPages} onPrev={() => setPincodePage((page) => Math.max(1, page - 1))} onNext={() => setPincodePage((page) => Math.min(pincodeTotalPages, page + 1))} />
      </section>
    </div>
  );

  const renderCouponsView = () => (
    <div style={{ color: theme.text, display: 'grid', gap: 24 }}>
      <section style={cardStyle}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12, gap: 10, flexWrap: 'wrap' }}>
          <div>
            <h2 style={{ margin: 0 }}>Coupons</h2>
            <p style={{ margin: '6px 0 0', color: '#64748b' }}>Create fixed or percentage discounts with date-range validity, minimum order value, and first-order/welcome eligibility.</p>
          </div>
          <button style={primaryButton} onClick={() => openCouponModal()}>Add Coupon</button>
        </div>
        <div style={{ marginBottom: 12 }}>
          <SearchBar value={couponSearch} onChange={setCouponSearch} placeholder="Search coupons" />
        </div>
        <div style={{ overflowX: 'auto' }}>
          <table style={tableStyle}>
            <thead>
              <tr style={tableHeadRowStyle}>
                <th style={tableHeadCellStyle}>Code</th>
                <th style={tableHeadCellStyle}>Discount</th>
                <th style={tableHeadCellStyle}>Validity</th>
                <th style={tableHeadCellStyle}>Min Order</th>
                <th style={tableHeadCellStyle}>Eligibility</th>
                <th style={tableHeadCellStyle}>Status</th>
                <th style={tableHeadCellStyle}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {pagedCoupons.map((coupon) => (
                <tr key={coupon.id} style={tableBodyRowStyle}>
                  <td style={tableCellStyle}>
                    <strong>{coupon.code}</strong>
                    {coupon.description ? <div style={{ color: '#64748b', fontSize: 13, marginTop: 4 }}>{coupon.description}</div> : null}
                  </td>
                  <td style={tableCellStyle}>{formatCouponDiscount(coupon)}</td>
                  <td style={tableCellStyle}>{formatCouponValidity(coupon)}</td>
                  <td style={tableCellStyle}>{Number(coupon.minOrderValue) > 0 ? `₹${coupon.minOrderValue}` : '-'}</td>
                  <td style={tableCellStyle}>
                    {coupon.firstOrderOnly ? (
                      <span style={{ padding: '6px 10px', borderRadius: 999, fontWeight: 700, fontSize: 12, background: '#eef2ff', color: '#4338ca' }}>First Order / Welcome</span>
                    ) : (
                      <span style={{ color: '#64748b' }}>All customers</span>
                    )}
                  </td>
                  <td style={tableCellStyle}>
                    <span style={{ padding: '6px 10px', borderRadius: 999, fontWeight: 700, fontSize: 12, background: coupon.isActive ? '#dcfce7' : '#fee2e2', color: coupon.isActive ? '#166534' : '#b91c1c' }}>
                      {coupon.isActive ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td style={tableCellStyle}>
                    <div style={{ display: 'flex', gap: 8 }}>
                      <button style={secondaryButton} onClick={() => openCouponModal(coupon)}>Edit</button>
                      <button style={secondaryButton} onClick={() => updateCouponStatus(coupon.id, !coupon.isActive)}>{coupon.isActive ? 'Deactivate' : 'Activate'}</button>
                      <button style={dangerButton} onClick={() => deleteCoupon(coupon.id)}>Delete</button>
                    </div>
                  </td>
                </tr>
              ))}
              {!pagedCoupons.length ? (
                <tr>
                  <td style={emptyCellStyle} colSpan={7}>No coupons found.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        <Pagination page={couponPage} totalPages={couponTotalPages} onPrev={() => setCouponPage((page) => Math.max(1, page - 1))} onNext={() => setCouponPage((page) => Math.min(couponTotalPages, page + 1))} />
      </section>
    </div>
  );

  const renderOverview = () => {
    const lowStockProducts = products.filter((product) => Number(product.stock) < 10);
    const fulfillmentRate = orders.length ? Math.round((orders.filter((order) => order.status === 'DELIVERED').length / orders.length) * 100) : 0;

    return (
      <div style={{ color: theme.text }}>
        <section style={{ background: 'linear-gradient(135deg, #4338ca 0%, #7c3aed 100%)', color: 'white', padding: 20, borderRadius: 20, boxShadow: '0 14px 30px rgba(79, 70, 229, 0.24)', marginBottom: 24 }}>
          <h2 style={{ margin: '0 0 6px' }}>Keep shelves stocked and orders moving</h2>
          <p style={{ margin: 0, opacity: 0.9 }}>Today priorities: complete pending orders, restock low inventory, and keep products organized.</p>
        </section>

        <div style={{ display: 'grid', gap: 16, gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', marginBottom: 24 }}>
          <StatsCard label="Products" value={products.length} />
          <StatsCard label="Categories" value={categories.length} />
          <StatsCard label="Orders" value={orders.length} />
          <StatsCard label="Customers" value={users.length} />
        </div>

        <div style={{ display: 'grid', gap: 16, gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', marginBottom: 24 }}>
          <AnalyticsCard title="Fulfillment" value={`${fulfillmentRate}%`} hint="Orders delivered" />
          <AnalyticsCard title="Low Stock" value={lowStockProducts.length} hint="Items below 10 units" />
          <AnalyticsCard title="Pending" value={orders.filter((order) => order.status !== 'DELIVERED').length} hint="Needs attention" />
          <AnalyticsCard title="Customers" value={users.length} hint="Active profiles" />
        </div>

        <div style={{ display: 'grid', gap: 16, gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', marginBottom: 24 }}>
          <section style={cardStyle}>
            <h2 style={{ marginTop: 0 }}>Quick Actions</h2>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
              <button style={primaryButton} onClick={loadData}>Refresh Data</button>
              <button style={secondaryButton} onClick={() => setActiveView('Products')}>Open Products</button>
              <button style={secondaryButton} onClick={() => setActiveView('Categories')}>Open Categories</button>
              <button style={secondaryButton} onClick={() => setActiveView('Orders')}>Open Orders</button>
            </div>
          </section>

          <section style={cardStyle}>
            <h2 style={{ marginTop: 0 }}>Recent Activity</h2>
            <ActivityFeed
              items={[
                'New product added to inventory.',
                'Order status moved to confirmed.',
                'Low-stock alert triggered for key items.',
                'Category updated for better grouping.',
              ]}
            />
          </section>
        </div>

        <section style={cardStyle}>
          <h2 style={{ marginTop: 0 }}>Inventory Alerts</h2>
          {lowStockProducts.slice(0, 4).map((product) => (
            <div key={product.id} style={{ borderBottom: '1px solid #eef2f7', padding: '8px 0', display: 'flex', justifyContent: 'space-between' }}>
              <span>{product.name}</span>
              <strong style={{ color: '#b91c1c' }}>{product.stock} left</strong>
            </div>
          ))}
          <InventoryChart products={products} />
        </section>
      </div>
    );
  };

  const renderBody = () => {
    if (activeView === 'Products') return renderProductsView();
    if (activeView === 'Categories') return renderCategoriesView();
    if (activeView === 'Orders') return renderOrdersView();
    if (activeView === 'Customers') return renderCustomersView();
    if (activeView === 'Serviceable Pincodes') return renderServiceablePincodesView();
    if (activeView === 'Coupons') return renderCouponsView();
    if (activeView === 'Settings') return renderSettingsView();
    return renderOverview();
  };

  if (!isAuthenticated) {
    return <AuthGate onLoginSuccess={() => setIsAuthenticated(true)} />;
  }

  const handleLogout = () => {
    clearSession();
    setIsAuthenticated(false);
  };

  return (
      <PanelShell
        title=""
        subtitle=""
        darkMode={darkMode}
        navItems={['Overview', 'Categories', 'Products', 'Orders', 'Customers', 'Serviceable Pincodes', 'Coupons', 'Settings']}
        activeView={activeView}
        onNavigate={setActiveView}
        onLogout={handleLogout}
      >
        {modalState.open ? (
          <div style={modalOverlayStyle} onClick={closeModal}>
            <div style={modalCardStyle} onClick={(event) => event.stopPropagation()}>
              {modalState.type === 'product' ? (
                <>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                    <div>
                      <h2 style={{ margin: 0 }}>{editingProductId ? 'Edit Product' : 'Create Product'}</h2>
                      <div style={{ color: '#64748b', marginTop: 4 }}>Manage product details and stock.</div>
                    </div>
                    <button type="button" style={secondaryButton} onClick={closeModal}>Close</button>
                  </div>
                  {modalError ? <div style={modalErrorBannerStyle}>{modalError}</div> : null}
                  <form onSubmit={editingProductId ? updateProduct : createProduct} noValidate style={{ display: 'grid', gap: 10 }}>
                    <input style={withFieldError(inputStyle, productFormErrors.name)} placeholder="Name *" value={productForm.name} onChange={(e) => setProductForm({ ...productForm, name: e.target.value })} />
                    <FieldError message={productFormErrors.name} />
                    <input style={inputStyle} placeholder="Description" value={productForm.description} onChange={(e) => setProductForm({ ...productForm, description: e.target.value })} />
                    <select style={withFieldError(inputStyle, productFormErrors.categoryId)} value={productForm.categoryId} onChange={(e) => setProductForm({ ...productForm, categoryId: e.target.value })}>
                      <option value="">Select a category *</option>
                      {productCategoryOptions.length ? (
                        productCategoryOptions.map((category) => (
                          <option key={category.value} value={category.value}>{category.label}</option>
                        ))
                      ) : (
                        <option value="" disabled>No categories available</option>
                      )}
                    </select>
                    <FieldError message={productFormErrors.categoryId} />
                    <div style={{ display: 'grid', gap: 10, gridTemplateColumns: '1fr 1fr' }}>
                      <div>
                        <input style={withFieldError(inputStyle, productFormErrors.price)} type="number" placeholder="Price *" value={productForm.price} onChange={(e) => setProductForm({ ...productForm, price: e.target.value })} />
                        <FieldError message={productFormErrors.price} />
                      </div>
                      <input style={inputStyle} type="number" placeholder="Discounted Price" value={productForm.discountedPrice} onChange={(e) => setProductForm({ ...productForm, discountedPrice: e.target.value })} />
                    </div>
                    <input style={withFieldError(inputStyle, productFormErrors.stock)} type="number" placeholder="Stock *" value={productForm.stock} onChange={(e) => setProductForm({ ...productForm, stock: e.target.value })} />
                    <FieldError message={productFormErrors.stock} />
                    <div>
                      <input style={inputStyle} type="number" placeholder="Display order (lower shows first)" value={productForm.sortOrder} onChange={(e) => setProductForm({ ...productForm, sortOrder: e.target.value })} />
                      <div style={{ color: '#64748b', fontSize: 13, marginTop: 4 }}>Controls where this product appears on the storefront. Lower numbers show first; defaults to 0.</div>
                    </div>
                    <label style={fileInputLabelStyle}>
                      Product images
                      <input type="file" multiple onChange={handleProductImagesUpload} style={fileInputStyle} />
                    </label>
                    <div style={imagePreviewGridStyle}>
                      {productForm.images.map((imageUrl) => (
                        <div key={imageUrl} style={imagePreviewCardStyle}>
                          <img src={resolveAssetUrl(imageUrl)} alt="Product preview" style={imagePreviewStyle} />
                          <button type="button" style={miniDangerButtonStyle} onClick={() => setProductForm((current) => ({ ...current, images: current.images.filter((image) => image !== imageUrl) }))}>Remove</button>
                        </div>
                      ))}
                    </div>
                    <div style={{ display: 'flex', gap: 8, marginTop: 4 }}>
                      <button type="submit" style={primaryButton}>{editingProductId ? 'Save Changes' : 'Create Product'}</button>
                      <button type="button" style={secondaryButton} onClick={() => { resetProductForm(); closeModal(); }}>Cancel</button>
                    </div>
                  </form>
                </>
              ) : null}

              {modalState.type === 'category' ? (
                <>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                    <div>
                      <h2 style={{ margin: 0 }}>{editingCategoryId ? 'Edit Category' : 'Create Category'}</h2>
                      <div style={{ color: '#64748b', marginTop: 4 }}>Manage product groupings.</div>
                    </div>
                    <button type="button" style={secondaryButton} onClick={closeModal}>Close</button>
                  </div>
                  {modalError ? <div style={modalErrorBannerStyle}>{modalError}</div> : null}
                  <form onSubmit={editingCategoryId ? updateCategory : createCategory} noValidate style={{ display: 'grid', gap: 10 }}>
                    <input style={withFieldError(inputStyle, categoryFormErrors.name)} placeholder="Category name *" value={categoryForm.name} onChange={(e) => setCategoryForm({ ...categoryForm, name: e.target.value })} />
                    <FieldError message={categoryFormErrors.name} />
                    <textarea style={{ ...inputStyle, minHeight: 72, resize: 'vertical' }} placeholder="Description (optional)" value={categoryForm.description} onChange={(e) => setCategoryForm({ ...categoryForm, description: e.target.value })} />
                    <div>
                      <input style={inputStyle} type="number" placeholder="Display order (lower shows first)" value={categoryForm.sortOrder} onChange={(e) => setCategoryForm({ ...categoryForm, sortOrder: e.target.value })} />
                      <div style={{ color: '#64748b', fontSize: 13, marginTop: 4 }}>Controls where this category appears on the storefront. Lower numbers show first; defaults to 0.</div>
                    </div>
                    <label style={fileInputLabelStyle}>
                      Category icon
                      <input type="file" accept="image/*" onChange={handleCategoryIconUpload} style={fileInputStyle} />
                    </label>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                      {categoryForm.iconUrl ? <img src={resolveAssetUrl(categoryForm.iconUrl)} alt="Category icon preview" style={categoryIconPreviewStyle} /> : <div style={defaultIconStyle}>◌</div>}
                      <div style={{ color: '#64748b', fontSize: 13 }}>Upload an icon. If none is selected, the default icon is shown.</div>
                    </div>
                    {editingCategoryId ? (
                      <button
                        type="button"
                        style={secondaryButton}
                        onClick={() => setCategoryForm((current) => ({ ...current, isActive: !current.isActive }))}
                      >
                        {categoryForm.isActive ? 'Mark as inactive' : 'Mark as active'}
                      </button>
                    ) : null}
                    <div style={{ display: 'flex', gap: 8 }}>
                      <button type="submit" style={primaryButton}>{editingCategoryId ? 'Save Changes' : 'Create Category'}</button>
                      <button type="button" style={secondaryButton} onClick={() => { resetCategoryForm(); closeModal(); }}>Cancel</button>
                    </div>
                  </form>
                </>
              ) : null}

              {modalState.type === 'coupon' ? (
                <>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                    <div>
                      <h2 style={{ margin: 0 }}>{editingCouponId ? 'Edit Coupon' : 'Create Coupon'}</h2>
                      <div style={{ color: '#64748b', marginTop: 4 }}>Configure discount type, validity window, and eligibility rules.</div>
                    </div>
                    <button type="button" style={secondaryButton} onClick={closeModal}>Close</button>
                  </div>
                  {modalError ? <div style={modalErrorBannerStyle}>{modalError}</div> : null}
                  <form onSubmit={editingCouponId ? updateCoupon : createCoupon} noValidate style={{ display: 'grid', gap: 10 }}>
                    <input
                      style={withFieldError(inputStyle, couponFormErrors.code)}
                      placeholder="Coupon code * (e.g. WELCOME50)"
                      value={couponForm.code}
                      onChange={(e) => setCouponForm({ ...couponForm, code: e.target.value.toUpperCase() })}
                    />
                    <FieldError message={couponFormErrors.code} />
                    <textarea
                      style={{ ...inputStyle, minHeight: 60, resize: 'vertical' }}
                      placeholder="Description (optional, e.g. Welcome discount for new users)"
                      value={couponForm.description}
                      onChange={(e) => setCouponForm({ ...couponForm, description: e.target.value })}
                    />
                    <div style={{ display: 'grid', gap: 10, gridTemplateColumns: '1fr 1fr' }}>
                      <div>
                        <select style={withFieldError(inputStyle, couponFormErrors.type)} value={couponForm.type} onChange={(e) => setCouponForm({ ...couponForm, type: e.target.value })}>
                          <option value="FIXED">Fixed amount (₹)</option>
                          <option value="PERCENTAGE">Percentage (%)</option>
                        </select>
                        <FieldError message={couponFormErrors.type} />
                      </div>
                      <div>
                        <input
                          style={withFieldError(inputStyle, couponFormErrors.discount)}
                          type="number"
                          placeholder={couponForm.type === 'PERCENTAGE' ? 'Discount % *' : 'Discount amount (₹) *'}
                          value={couponForm.discount}
                          onChange={(e) => setCouponForm({ ...couponForm, discount: e.target.value })}
                        />
                        <FieldError message={couponFormErrors.discount} />
                      </div>
                    </div>
                    {couponForm.type === 'PERCENTAGE' ? (
                      <input
                        style={inputStyle}
                        type="number"
                        placeholder="Max discount cap (₹, optional)"
                        value={couponForm.maxDiscountAmount}
                        onChange={(e) => setCouponForm({ ...couponForm, maxDiscountAmount: e.target.value })}
                      />
                    ) : null}
                    <input
                      style={inputStyle}
                      type="number"
                      placeholder="Minimum order value (₹, optional)"
                      value={couponForm.minOrderValue}
                      onChange={(e) => setCouponForm({ ...couponForm, minOrderValue: e.target.value })}
                    />
                    <div>
                      <label style={{ display: 'block', color: '#64748b', fontSize: 13, marginBottom: 6 }}>Validity window (date range, optional)</label>
                      <div style={{ display: 'grid', gap: 10, gridTemplateColumns: '1fr 1fr' }}>
                        <input style={inputStyle} type="date" value={couponForm.startsAt} onChange={(e) => setCouponForm({ ...couponForm, startsAt: e.target.value })} />
                        <div>
                          <input style={withFieldError(inputStyle, couponFormErrors.expiresAt)} type="date" value={couponForm.expiresAt} onChange={(e) => setCouponForm({ ...couponForm, expiresAt: e.target.value })} />
                          <FieldError message={couponFormErrors.expiresAt} />
                        </div>
                      </div>
                    </div>
                    <div style={{ display: 'grid', gap: 10, gridTemplateColumns: '1fr 1fr' }}>
                      <input
                        style={inputStyle}
                        type="number"
                        placeholder="Total usage limit (optional)"
                        value={couponForm.usageLimit}
                        onChange={(e) => setCouponForm({ ...couponForm, usageLimit: e.target.value })}
                      />
                      <input
                        style={inputStyle}
                        type="number"
                        placeholder="Per-user usage limit"
                        value={couponForm.perUserLimit}
                        onChange={(e) => setCouponForm({ ...couponForm, perUserLimit: e.target.value })}
                      />
                    </div>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, color: theme.text }}>
                      <input type="checkbox" checked={couponForm.firstOrderOnly} onChange={(e) => setCouponForm({ ...couponForm, firstOrderOnly: e.target.checked })} />
                      First order only (welcome coupon for new customers)
                    </label>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, color: theme.text }}>
                      <input type="checkbox" checked={couponForm.isActive} onChange={(e) => setCouponForm({ ...couponForm, isActive: e.target.checked })} />
                      Active
                    </label>
                    <div style={{ display: 'flex', gap: 8, marginTop: 4 }}>
                      <button type="submit" style={primaryButton}>{editingCouponId ? 'Save Changes' : 'Create Coupon'}</button>
                      <button type="button" style={secondaryButton} onClick={() => { resetCouponForm(); closeModal(); }}>Cancel</button>
                    </div>
                  </form>
                </>
              ) : null}

              {modalState.type === 'order' ? (
                <>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                    <div>
                      <h2 style={{ margin: 0 }}>Order Details</h2>
                      <div style={{ color: '#64748b', marginTop: 4 }}>Detailed summary of amount and items.</div>
                    </div>
                    <button type="button" style={secondaryButton} onClick={closeModal}>Close</button>
                  </div>
                  {modalState.loading ? (
                    <div style={{ color: '#64748b' }}>Loading order details...</div>
                  ) : modalState.item ? (
                    <div style={{ display: 'grid', gap: 12 }}>
                      <div style={{ display: 'grid', gap: 8 }}>
                        <div style={infoRowStyle}><span>Order ID</span><strong>{formatOrderNumber(modalState.item.orderNumber)}</strong></div>
                        <div style={infoRowStyle}><span>Status</span><strong>{modalState.item.status}</strong></div>
                        <div style={infoRowStyle}><span>Total Amount</span><strong>₹{modalState.item.total ?? '-'}</strong></div>
                        <div style={infoRowStyle}><span>Payment</span><strong>{modalState.item.paymentMethod ?? '-'}</strong></div>
                      </div>
                      <div>
                        <h3 style={{ marginBottom: 8 }}>Product Items</h3>
                        <div style={{ overflowX: 'auto' }}>
                          <table style={tableStyle}>
                            <thead>
                              <tr style={tableHeadRowStyle}>
                                <th style={tableHeadCellStyle}>Product</th>
                                <th style={tableHeadCellStyle}>Quantity</th>
                                <th style={tableHeadCellStyle}>Price</th>
                              </tr>
                            </thead>
                            <tbody>
                              {(modalState.item.items ?? []).map((item, index) => {
                                const itemProduct = item.product ?? products.find((product) => product.id === item.productId);
                                return (
                                  <tr key={`${item.productId ?? 'item'}-${index}`} style={tableBodyRowStyle}>
                                    <td style={tableCellStyle}>
                                      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                        {itemProduct?.images?.[0] ? (
                                          <img src={resolveAssetUrl(itemProduct.images[0])} alt={itemProduct.name} style={{ width: 28, height: 28, objectFit: 'cover', borderRadius: 8, border: '1px solid #e2e8f0' }} />
                                        ) : (
                                          <div style={{ width: 28, height: 28, borderRadius: 8, background: '#eef2ff', display: 'grid', placeItems: 'center', color: '#4338ca', fontWeight: 700, fontSize: 12 }}>◌</div>
                                        )}
                                        <span>{itemProduct?.name ?? 'Unknown product'}</span>
                                      </div>
                                    </td>
                                    <td style={tableCellStyle}>{item.quantity ?? '-'}</td>
                                    <td style={tableCellStyle}>₹{item.price ?? '-'}</td>
                                  </tr>
                                );
                              })}
                              {!modalState.item.items?.length ? (
                                <tr>
                                  <td style={emptyCellStyle} colSpan={3}>No product items available for this order.</td>
                                </tr>
                              ) : null}
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  ) : (
                    <div style={{ color: '#b91c1c' }}>Unable to load order details.</div>
                  )}
                </>
              ) : null}

              {modalState.type === 'customer' ? (
                <>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                    <h2 style={{ margin: 0 }}>{modalState.item?.name ?? 'Customer Details'}</h2>
                    <button type="button" style={secondaryButton} onClick={closeModal}>Close</button>
                  </div>
                  <div style={{ display: 'grid', gap: 10 }}>
                    <div style={infoRowStyle}><span>Customer ID</span><strong>{formatUserNumber(modalState.item?.userNumber)}</strong></div>
                    <div style={infoRowStyle}><span>Phone</span><strong>{modalState.item?.phone ?? '-'}</strong></div>
                    <div style={infoRowStyle}><span>Role</span><strong>{modalState.item?.role ?? 'Customer'}</strong></div>
                    <div style={infoRowStyle}><span>Orders</span><strong>{orders.filter((order) => order.userId === modalState.item?.id).length}</strong></div>
                  </div>
                </>
              ) : null}
            </div>
          </div>
        ) : null}

        {renderBody()}
      </PanelShell>
  );
}

function withFieldError(baseStyle, hasError) {
  if (!hasError) {
    return baseStyle;
  }
  return { ...baseStyle, borderColor: '#dc2626', background: '#fef2f2' };
}

function FieldError({ message }) {
  if (!message) {
    return null;
  }
  return <div style={{ color: '#dc2626', fontSize: 12, marginTop: -4 }}>{message}</div>;
}

function Pagination({ page, totalPages, onPrev, onNext }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 12, alignItems: 'center' }}>
      <button style={secondaryButton} disabled={page === 1} onClick={onPrev}>Prev</button>
      <span style={{ color: '#64748b' }}>Page {page} of {totalPages}</span>
      <button style={secondaryButton} disabled={page >= totalPages} onClick={onNext}>Next</button>
    </div>
  );
}

const cardStyle = {
  background: 'white',
  padding: 20,
  borderRadius: 16,
  boxShadow: '0 10px 30px rgba(15,23,42,0.06)',
};

const tableStyle = {
  width: '100%',
  borderCollapse: 'collapse',
};

const tableHeadRowStyle = {
  textAlign: 'left',
  color: '#64748b',
  fontSize: 12,
};

const tableHeadCellStyle = {
  paddingBottom: 8,
};

const tableBodyRowStyle = {
  borderTop: '1px solid #eef2f7',
};

const tableCellStyle = {
  padding: '10px 0',
};

const emptyCellStyle = {
  color: '#64748b',
  padding: '16px 0',
  textAlign: 'center',
};

const linkButtonStyle = {
  background: 'transparent',
  border: 'none',
  color: '#4f46e5',
  cursor: 'pointer',
  padding: 0,
  textDecoration: 'underline',
  fontWeight: 700,
};

const inputStyle = {
  padding: '10px 12px',
  borderRadius: 10,
  border: '1px solid #dbe2ea',
  background: '#f8fafc',
};

const selectStyle = {
  padding: '10px 12px',
  borderRadius: 10,
  border: '1px solid #dbe2ea',
  background: '#f8fafc',
};

const infoRowStyle = {
  display: 'flex',
  justifyContent: 'space-between',
  alignItems: 'center',
};

const badgeStyle = {
  display: 'inline-flex',
  alignItems: 'center',
  gap: 8,
  padding: '8px 12px',
  borderRadius: 999,
  fontSize: 13,
  fontWeight: 700,
};

const modalErrorBannerStyle = {
  color: '#b91c1c',
  background: '#fef2f2',
  border: '1px solid #fecaca',
  borderRadius: 12,
  padding: '10px 12px',
  marginBottom: 12,
  fontSize: 14,
};

const primaryButton = {
  padding: '10px 14px',
  background: '#4f46e5',
  color: 'white',
  border: 'none',
  borderRadius: 10,
  cursor: 'pointer',
};

const secondaryButton = {
  padding: '8px 12px',
  background: '#f1f5f9',
  color: '#0f172a',
  border: 'none',
  borderRadius: 10,
  cursor: 'pointer',
};

const dangerButton = {
  padding: '8px 12px',
  background: '#fee2e2',
  color: '#b91c1c',
  border: 'none',
  borderRadius: 10,
  cursor: 'pointer',
};

const modalOverlayStyle = {
  position: 'fixed',
  inset: 0,
  background: 'rgba(15, 23, 42, 0.6)',
  display: 'flex',
  alignItems: 'center',
  justifyContent: 'center',
  padding: 20,
  zIndex: 1000,
};

const modalCardStyle = {
  width: '100%',
  maxWidth: 620,
  background: 'white',
  borderRadius: 20,
  padding: 24,
  boxShadow: '0 20px 60px rgba(15, 23, 42, 0.24)',
  maxHeight: '90vh',
  overflowY: 'auto',
};

const fileInputLabelStyle = {
  display: 'grid',
  gap: 8,
  padding: '12px 14px',
  borderRadius: 12,
  border: '1px dashed #cbd5e1',
  background: '#f8fafc',
  color: '#0f172a',
  fontWeight: 600,
};

const fileInputStyle = {
  width: '100%',
};

const imagePreviewGridStyle = {
  display: 'grid',
  gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))',
  gap: 12,
};

const imagePreviewCardStyle = {
  display: 'grid',
  gap: 8,
  padding: 10,
  borderRadius: 14,
  border: '1px solid #e2e8f0',
  background: '#fff',
};

const imagePreviewStyle = {
  width: '100%',
  height: 88,
  objectFit: 'cover',
  borderRadius: 10,
};

const miniDangerButtonStyle = {
  padding: '6px 10px',
  borderRadius: 10,
  border: 'none',
  background: '#fee2e2',
  color: '#b91c1c',
  cursor: 'pointer',
  fontWeight: 700,
};

const categoryIconPreviewStyle = {
  width: 44,
  height: 44,
  objectFit: 'cover',
  borderRadius: 12,
  border: '1px solid #e2e8f0',
};

const defaultIconStyle = {
  width: 44,
  height: 44,
  borderRadius: 12,
  background: '#eef2ff',
  color: '#4338ca',
  display: 'grid',
  placeItems: 'center',
  fontWeight: 800,
};

function SettingField({ label, description, children }) {
  return (
    <div style={{ padding: 16, borderRadius: 16, border: '1px solid #e5e7eb', background: '#f8fafc', display: 'grid', gap: 10 }}>
      <div>
        <div style={{ fontWeight: 700, color: '#0f172a' }}>{label}</div>
        <div style={{ fontSize: 13, color: '#64748b', marginTop: 4 }}>{description}</div>
      </div>
      {children}
    </div>
  );
}

export default App;

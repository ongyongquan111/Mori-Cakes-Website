// Mori Cakes - Main JavaScript File
// Simplified version with essential features only

// Global variables
let cart = JSON.parse(localStorage.getItem('cart')) || [];
const deliveryThreshold = 100; // Free delivery threshold (RM)
const deliveryFee = 12; // Delivery fee (RM)

// DOM Elements
const elements = {
  menuContainer: document.getElementById('menu-container'),
  cartButton: document.getElementById('cart-button'),
  cartDrawer: document.getElementById('cart-drawer'),
  closeCartBtn: document.getElementById('close-cart-btn'),
  cartItems: document.getElementById('cart-items'),
  cartSummary: document.getElementById('cart-summary'),
  checkoutBtn: document.getElementById('checkout-btn'),
  cartCount: document.getElementById('cart-count'),
  overlay: document.getElementById('overlay'),
  userMenuButton: document.getElementById('user-menu-button'),
  userDropdown: document.getElementById('user-dropdown'),
  mobileMenuButton: document.getElementById('mobile-menu-button'),
  mobileNav: document.getElementById('mobile-nav'),
  notificationContainer: document.getElementById('notification-container')
};

// Initialize the application
function init() {
  console.log('Initializing Mori Cakes application...');
  console.log('Menu items available:', window.menuItems ? window.menuItems.length : 0);
  
  // Initialize menu display
  initializeMenu();
  
  // Initialize event listeners
  initializeEventListeners();
  
  // Update UI components
  updateCartUI();
  setMinDeliveryDate();
  updateUserUI();
  
  console.log('Initialization complete!');
}

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM loaded, starting initialization...');
  init();
});

// Initialize menu
function initializeMenu() {
  if (!elements.menuContainer) return;
  
  elements.menuContainer.innerHTML = '';
  
  if (!window.menuItems || window.menuItems.length === 0) {
    elements.menuContainer.innerHTML = '<div class="text-center py-8 text-gray-500 col-span-full"><i class="fa fa-birthday-cake text-4xl mb-2"></i><p>No menu items available</p><p class="text-sm">Please try again later</p></div>';
    return;
  }
  
  window.menuItems.forEach(item => {
    const menuItem = createMenuItem(item);
    elements.menuContainer.appendChild(menuItem);
  });
}

// Create menu item
function createMenuItem(item) {
  const menuItem = document.createElement('div');
  menuItem.className = `menu-item card-hover bg-white rounded-xl shadow overflow-hidden ${item.category}`;
  menuItem.setAttribute('data-category', item.category);
  
  const ratingHTML = createRatingStars(item.rating);
  
  menuItem.innerHTML = `
    <div class="relative">
      <img src="${item.image}" alt="${item.name}" class="w-full h-48 object-cover">
      <div class="absolute top-2 right-2 bg-primary text-white text-sm font-bold px-2 py-1 rounded">
        RM${item.price.toFixed(1)}
      </div>
    </div>
    <div class="p-4">
      <h3 class="text-lg font-bold mb-2">${item.name}</h3>
      <p class="text-gray-600 text-sm mb-3">${item.description}</p>
      <div class="flex items-center mb-3">
        <div class="flex mr-2">
          ${ratingHTML}
        </div>
        <span class="text-sm text-gray-500">(${item.reviewCount})</span>
      </div>
      <button class="add-to-cart-btn btn-primary w-full" data-id="${item.id}">
        <i class="fa fa-shopping-cart mr-2"></i>Add to Cart
      </button>
    </div>
  `;
  
  return menuItem;
}

// Create star rating HTML
function createRatingStars(rating) {
  const fullStars = Math.floor(rating);
  const halfStar = rating % 1 >= 0.5;
  const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
  
  let starsHTML = '';
  
  // Add full stars
  for (let i = 0; i < fullStars; i++) {
    starsHTML += '<i class="fa fa-star text-yellow-400"></i>';
  }
  
  // Add half star
  if (halfStar) {
    starsHTML += '<i class="fa fa-star-half-o text-yellow-400"></i>';
  }
  
  // Add empty stars
  for (let i = 0; i < emptyStars; i++) {
    starsHTML += '<i class="fa fa-star-o text-yellow-400"></i>';
  }
  
  return starsHTML;
}

// Initialize event listeners
function initializeEventListeners() {
  // Category filter buttons
  document.querySelectorAll('.category-btn').forEach(button => {
    button.addEventListener('click', function() {
      const category = this.getAttribute('data-category');
      filterMenuItems(category);
      
      // Update active button
      document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('active', 'bg-primary', 'text-white');
        btn.classList.add('bg-gray-200');
      });
      this.classList.add('active', 'bg-primary', 'text-white');
      this.classList.remove('bg-gray-200');
    });
  });
  
  // Cart button click event
  if (elements.cartButton) {
    elements.cartButton.addEventListener('click', function() {
      toggleCartDrawer();
    });
  }
  
  // Close cart button
  if (elements.closeCartBtn) {
    elements.closeCartBtn.addEventListener('click', function() {
      toggleCartDrawer();
    });
  }
  
  // Add to cart buttons (delegated event)
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('add-to-cart-btn') || e.target.closest('.add-to-cart-btn')) {
      const button = e.target.classList.contains('add-to-cart-btn') ? e.target : e.target.closest('.add-to-cart-btn');
      const itemId = parseInt(button.getAttribute('data-id'));
      addToCart(itemId);
    }
  });
  
  // Checkout button
  if (elements.checkoutBtn) {
    elements.checkoutBtn.addEventListener('click', function() {
      if (window.currentUserData) {
        openCheckoutModal();
      } else {
        showNotification('Please login to checkout', 'warning');
        openLoginModal();
      }
    });
  }
  
  // User menu button
  if (elements.userMenuButton) {
    elements.userMenuButton.addEventListener('click', function() {
      elements.userDropdown.classList.toggle('hidden');
    });
  }
  
  // Mobile menu button
  if (elements.mobileMenuButton) {
    elements.mobileMenuButton.addEventListener('click', function() {
      elements.mobileNav.classList.toggle('hidden');
    });
  }
  
  // Overlay click
  if (elements.overlay) {
    elements.overlay.addEventListener('click', function() {
      closeAllModals();
      toggleCartDrawer(false);
    });
  }
  
  // Close dropdowns when clicking outside
  document.addEventListener('click', function(e) {
    if (elements.userMenuButton && elements.userDropdown) {
      if (!elements.userMenuButton.contains(e.target) && !elements.userDropdown.contains(e.target)) {
        elements.userDropdown.classList.add('hidden');
      }
    }
  });
  
  // Form submissions
  handleFormSubmissions();
}

// Handle form submissions
function handleFormSubmissions() {
  // Login form
  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
      e.preventDefault();
      login();
    });
  }
  
  // Register form
  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    registerForm.addEventListener('submit', function(e) {
      e.preventDefault();
      register();
    });
  }
  
  // Checkout form
  const checkoutForm = document.getElementById('checkout-form');
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', function(e) {
      e.preventDefault();
      placeOrder();
    });
  }
  
  // Contact form
  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
      e.preventDefault();
      showNotification('Thank you for your message! We will get back to you soon.', 'success');
      contactForm.reset();
    });
  }
}

// Filter menu items by category
function filterMenuItems(category) {
  const menuItems = document.querySelectorAll('.menu-item');
  
  menuItems.forEach(item => {
    if (category === 'all' || item.getAttribute('data-category') === category) {
      item.style.display = 'block';
    } else {
      item.style.display = 'none';
    }
  });
}

// Add item to cart
function addToCart(itemId) {
  const item = window.menuItems.find(item => item.id === itemId);
  if (!item) return;
  
  const existingItem = cart.find(cartItem => cartItem.id === itemId);
  
  if (existingItem) {
    existingItem.quantity += 1;
  } else {
    cart.push({
      id: item.id,
      name: item.name,
      price: item.price,
      image: item.image,
      quantity: 1
    });
  }
  
  // Save to localStorage
  localStorage.setItem('cart', JSON.stringify(cart));
  
  // Update UI
  updateCartUI();
  
  // Show notification
  showNotification(`${item.name} has been added to your cart!`, 'success');
  
  // Animate cart icon
  animateCartIcon();
}

// Remove item from cart
function removeFromCart(itemId) {
  cart = cart.filter(item => item.id !== itemId);
  
  // Save to localStorage
  localStorage.setItem('cart', JSON.stringify(cart));
  
  // Update UI
  updateCartUI();
  
  showNotification('Item removed from cart', 'info');
}

// Update cart item quantity
function updateCartItemQuantity(itemId, quantity) {
  const item = cart.find(item => item.id === itemId);
  if (item) {
    if (quantity <= 0) {
      // Remove item if quantity is 0 or less
      removeFromCart(itemId);
    } else {
      item.quantity = quantity;
      
      // Save to localStorage
      localStorage.setItem('cart', JSON.stringify(cart));
      
      // Update UI
      updateCartUI();
    }
  }
}

// Animate cart icon when item is added
function animateCartIcon() {
  const cartButton = document.getElementById('cart-button');
  const cartCount = document.getElementById('cart-count');
  
  if (cartButton && cartCount) {
    // Add bounce animation to cart button
    cartButton.style.transform = 'scale(1.2)';
    cartButton.style.transition = 'transform 0.3s ease';
    
    // Add pulse animation to cart count
    cartCount.style.animation = 'none';
    cartCount.offsetHeight; // Trigger reflow
    cartCount.style.animation = 'pulse 0.6s ease-in-out';
    
    // Reset after animation
    setTimeout(() => {
      cartButton.style.transform = 'scale(1)';
    }, 300);
  }
}

// Update cart UI
function updateCartUI() {
  // Update cart count
  const cartCount = cart.reduce((total, item) => total + item.quantity, 0);
  elements.cartCount.textContent = cartCount;
  
  // Update cart items
  if (cart.length === 0) {
    elements.cartItems.innerHTML = `
      <div class="text-center py-8 text-gray-500">
        <i class="fa fa-shopping-cart text-4xl mb-4"></i>
        <p>Your cart is empty</p>
        <p class="text-sm">Add some delicious cakes to get started!</p>
      </div>
    `;
    elements.cartSummary.classList.add('hidden');
    elements.checkoutBtn.disabled = true;
    elements.checkoutBtn.classList.add('opacity-50', 'cursor-not-allowed');
    return;
  }
  
  // Show cart items
  elements.cartItems.innerHTML = cart.map(item => `
    <div class="flex items-center border-b pb-4 mb-4">
      <img src="${item.image}" alt="${item.name}" class="w-16 h-16 object-cover rounded-lg mr-4">
      <div class="flex-1">
        <h4 class="font-medium text-dark">${item.name}</h4>
        <p class="text-sm text-gray-600">RM${item.price.toFixed(1)}</p>
        <div class="flex items-center mt-2">
          <button class="quantity-btn px-2 py-1 border rounded-l" onclick="updateCartItemQuantity(${item.id}, ${item.quantity - 1})">
            <i class="fa fa-minus text-xs"></i>
          </button>
          <input type="number" value="${item.quantity}" class="w-10 text-center border-t border-b" 
                 onchange="updateCartItemQuantity(${item.id}, parseInt(this.value) || 0)">
          <button class="quantity-btn px-2 py-1 border rounded-r" onclick="updateCartItemQuantity(${item.id}, ${item.quantity + 1})">
            <i class="fa fa-plus text-xs"></i>
          </button>
          <button class="ml-auto text-red-500 hover:text-red-700" onclick="removeFromCart(${item.id})">
            <i class="fa fa-trash"></i>
          </button>
        </div>
      </div>
    </div>
  `).join('');
  
  // Show cart summary
  elements.cartSummary.classList.remove('hidden');
  elements.checkoutBtn.disabled = false;
  elements.checkoutBtn.classList.remove('opacity-50', 'cursor-not-allowed');
  
  // Calculate totals
  const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
  const deliveryFee = subtotal >= 100 ? 0 : 12;
  const total = subtotal + deliveryFee;
  
  document.getElementById('cart-subtotal').textContent = subtotal.toFixed(2);
  document.getElementById('delivery-fee').textContent = deliveryFee.toFixed(2);
  document.getElementById('cart-total').textContent = total.toFixed(2);
}

// Toggle cart drawer
function toggleCartDrawer(show = null) {
  const isOpen = !elements.cartDrawer.classList.contains('translate-x-full');
  
  if (show === null) {
    show = !isOpen;
  }
  
  if (show) {
    elements.cartDrawer.classList.remove('translate-x-full');
    elements.overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  } else {
    elements.cartDrawer.classList.add('translate-x-full');
    elements.overlay.classList.add('hidden');
    document.body.style.overflow = '';
  }
}

// Show notification
function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.className = `notification bg-white rounded-lg shadow-lg p-3 border-l-4 ${getNotificationClass(type)} transform transition-all duration-300 translate-x-full opacity-0`;
  
  notification.innerHTML = `
    <div class="flex items-center">
      <div class="flex-shrink-0 w-6 text-center">
        <i class="fa ${getNotificationIcon(type)} text-lg"></i>
      </div>
      <div class="ml-3 flex-1">
        <p class="text-sm font-medium text-gray-800 leading-relaxed">${message}</p>
      </div>
      <button class="ml-3 text-gray-400 hover:text-gray-600 transition-colors duration-200 p-1" onclick="this.parentElement.parentElement.remove()">
        <i class="fa fa-times"></i>
      </button>
    </div>
  `;
  
  // Add to container (new notifications appear at the top)
  elements.notificationContainer.appendChild(notification);
  
  // Animate in with bounce effect
  setTimeout(() => {
    notification.classList.remove('translate-x-full', 'opacity-0');
    notification.style.transform = 'translateX(-10px)';
    setTimeout(() => {
      notification.style.transform = 'translateX(0)';
      notification.style.transition = 'transform 0.2s ease-out';
    }, 200);
  }, 10);
  
  // Auto remove after 5 seconds
  setTimeout(() => {
    if (notification.parentNode) {
      notification.style.transform = 'translateX(100%)';
      notification.style.opacity = '0';
      setTimeout(() => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 300);
    }
  }, 5000);
  
  // Auto remove after 5 seconds
  setTimeout(() => {
    notification.classList.add('translate-x-full', 'opacity-0');
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }, 5000);
}

// Get notification class based on type
function getNotificationClass(type) {
  switch (type) {
    case 'success':
      return 'border-green-500';
    case 'error':
      return 'border-red-500';
    case 'warning':
      return 'border-yellow-500';
    default:
      return 'border-blue-500';
  }
}

// Get notification icon based on type
function getNotificationIcon(type) {
  switch (type) {
    case 'success':
      return 'fa-check-circle text-green-500';
    case 'error':
      return 'fa-exclamation-circle text-red-500';
    case 'warning':
      return 'fa-exclamation-triangle text-yellow-500';
    default:
      return 'fa-info-circle text-blue-500';
  }
}

// Modal functions
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('hidden');
    elements.overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Add animation
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
      modalContent.style.transform = 'scale(0.9) translateY(-20px)';
      modalContent.style.opacity = '0';
      setTimeout(() => {
        modalContent.style.transform = 'scale(1) translateY(0)';
        modalContent.style.opacity = '1';
      }, 10);
    }
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
      modalContent.style.transform = 'scale(0.9) translateY(-20px)';
      modalContent.style.opacity = '0';
      setTimeout(() => {
        modal.classList.add('hidden');
        elements.overlay.classList.add('hidden');
        document.body.style.overflow = '';
      }, 300);
    } else {
      modal.classList.add('hidden');
      elements.overlay.classList.add('hidden');
      document.body.style.overflow = '';
    }
  }
}

function closeAllModals() {
  const modals = ['login-modal', 'register-modal', 'checkout-modal', 'order-history-modal'];
  modals.forEach(modalId => closeModal(modalId));
}

// Specific modal functions
function openLoginModal() {
  closeModal('register-modal');
  openModal('login-modal');
}

function openRegisterModal() {
  closeModal('login-modal');
  openModal('register-modal');
}

function openCheckoutModal() {
  const checkoutForm = document.getElementById('checkout-form');
  if (checkoutForm && window.currentUserData) {
    // Pre-fill user information
    document.getElementById('checkout-name').value = window.currentUserData.full_name || '';
    document.getElementById('checkout-phone').value = '';
    document.getElementById('checkout-address').value = '';
  }
  
  // Update checkout summary
  updateCheckoutSummary();
  
  openModal('checkout-modal');
}

function openOrderHistoryModal() {
  loadOrderHistory();
  openModal('order-history-modal');
}

// Update checkout summary
function updateCheckoutSummary() {
  const summaryContainer = document.getElementById('checkout-summary');
  if (!summaryContainer) return;
  
  const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
  const deliveryFee = subtotal >= 100 ? 0 : 12;
  const total = subtotal + deliveryFee;
  
  summaryContainer.innerHTML = `
    ${cart.map(item => `
      <div class="flex justify-between">
        <span>${item.name} x ${item.quantity}</span>
        <span>RM${(item.price * item.quantity).toFixed(2)}</span>
      </div>
    `).join('')}
    <div class="border-t my-2 pt-2"></div>
    <div class="flex justify-between">
      <span>Subtotal:</span>
      <span>RM${subtotal.toFixed(2)}</span>
    </div>
    <div class="flex justify-between">
      <span>Delivery:</span>
      <span>RM${deliveryFee.toFixed(2)}</span>
    </div>
    <div class="border-t my-2 pt-2 font-medium">
      <div class="flex justify-between">
        <span>Total:</span>
        <span>RM${total.toFixed(2)}</span>
      </div>
    </div>
  `;
}

// Set minimum delivery date to tomorrow
function setMinDeliveryDate() {
  const checkoutDate = document.getElementById('checkout-date');
  if (checkoutDate) {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    checkoutDate.min = tomorrow.toISOString().split('T')[0];
  }
}

// User authentication functions
function login() {
  const username = document.getElementById('login-username').value;
  const password = document.getElementById('login-password').value;
  
  if (!username || !password) {
    showNotification('Please enter both username and password', 'error');
    return;
  }
  
  // Send login request to server
  fetch('index.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({
      action: 'login',
      username: username,
      password: password
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success && data.user) {
      window.currentUserData = data.user;
      
      // Update UI
      updateUserUI();
      
      closeModal('login-modal');
      showNotification('Login successful!', 'success');
      
      // Redirect admin to admin panel
      if (window.currentUserData.role === 'admin') {
        setTimeout(() => {
          window.location.href = 'admin.php?admin=true';
        }, 1000);
      }
    } else {
      showNotification(data.message || 'Invalid username or password', 'error');
    }
  })
  .catch(error => {
    console.error('Login error:', error);
    showNotification('Login failed. Please try again.', 'error');
  });
}

function register() {
  const name = document.getElementById('register-name').value;
  const username = document.getElementById('register-username').value;
  const email = document.getElementById('register-email').value;
  const password = document.getElementById('register-password').value;
  
  if (!name || !username || !email || !password) {
    showNotification('Please fill in all fields', 'error');
    return;
  }
  
  // Send registration request to server
  fetch('index.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({
      action: 'register',
      name: name,
      username: username,
      email: email,
      password: password
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showNotification('Registration successful! Please login.', 'success');
      closeModal('register-modal');
      openLoginModal();
    } else {
      showNotification(data.message || 'Registration failed. Please try again.', 'error');
    }
  })
  .catch(error => {
    console.error('Registration error:', error);
    showNotification('Registration failed. Please try again.', 'error');
  });
}

function logout() {
  window.currentUserData = null;
  updateUserUI();
  showNotification('Logged out successfully', 'info');
}

// Update user UI
function updateUserUI() {
  const userMenuButton = document.getElementById('user-menu-button');
  const userDropdown = document.getElementById('user-dropdown');
  
  if (userMenuButton && window.currentUserData) {
    const userText = userMenuButton.querySelector('span');
    if (userText) {
      userText.textContent = window.currentUserData.full_name;
    }
  }
  
  // Update dropdown menu
  if (userDropdown) {
    userDropdown.innerHTML = window.currentUserData ? `
      <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="showNotification('You are logged in as ${window.currentUserData.username}')">
        <i class="fa fa-user-circle mr-2"></i>Profile
      </a>
      <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="openOrderHistoryModal()">
        <i class="fa fa-history mr-2"></i>Order History
      </a>
      <hr class="my-1">
      <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="logout()">
        <i class="fa fa-sign-out mr-2"></i>Logout
      </a>
    ` : `
      <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="openLoginModal()">
        <i class="fa fa-sign-in mr-2"></i>Login
      </a>
      <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" onclick="openRegisterModal()">
        <i class="fa fa-user-plus mr-2"></i>Register
      </a>
    `;
  }
}

// Place order
function placeOrder() {
  const name = document.getElementById('checkout-name').value;
  const phone = document.getElementById('checkout-phone').value;
  const address = document.getElementById('checkout-address').value;
  const date = document.getElementById('checkout-date').value;
  
  if (!name || !phone || !address || !date) {
    showNotification('Please fill in all delivery information', 'error');
    return;
  }
  
  if (cart.length === 0) {
    showNotification('Your cart is empty', 'error');
    return;
  }
  
  // Calculate totals
  const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
  const deliveryFee = subtotal >= 100 ? 0 : 12;
  const total = subtotal + deliveryFee;
  
  const orderId = 'ORD-' + date.replace(/-/g, '') + '-' + Math.floor(Math.random() * 10000).toString().padStart(4, '0');
  
  // Clear cart
  cart = [];
  localStorage.removeItem('cart');
  updateCartUI();
  
  // Close modals
  closeModal('checkout-modal');
  toggleCartDrawer(false);
  
  // Show success notification
  showNotification(`Order #${orderId} placed successfully!`, 'success');
  
  // Show order details
  setTimeout(() => {
    alert(`Order Confirmation\n\nOrder ID: ${orderId}\nTotal: RM${total.toFixed(2)}\nDelivery Date: ${date}\n\nThank you for your order!`);
  }, 500);
}

// Load order history
function loadOrderHistory() {
  const orderHistoryContainer = document.getElementById('order-history-container');
  if (!orderHistoryContainer) return;
  
  orderHistoryContainer.innerHTML = '<div class="text-center py-8"><i class="fa fa-spinner fa-spin text-primary text-2xl"></i><p class="mt-2">Loading order history...</p></div>';
  
  // Sample order history data
  setTimeout(() => {
    const orderHistory = [
      {
        id: 'ORD-20240115-0001',
        date: '2024-01-15',
        total: 85.8,
        status: 'Delivered',
        items: [
          { name: 'New York Cheesecake', quantity: 1, price: 39.9 },
          { name: 'Strawberry Shortcake', quantity: 1, price: 36.9 }
        ]
      },
      {
        id: 'ORD-20240110-0002',
        date: '2024-01-10',
        total: 45.9,
        status: 'Delivered',
        items: [
          { name: 'Chocolate Indulgence Cake', quantity: 1, price: 45.9 }
        ]
      }
    ];
    
    if (orderHistory.length === 0) {
      orderHistoryContainer.innerHTML = '<div class="text-center py-8 text-gray-500"><i class="fa fa-shopping-basket text-4xl mb-2"></i><p>No orders found</p><p class="text-sm">Start shopping to place your first order!</p></div>';
      return;
    }
    
    orderHistoryContainer.innerHTML = orderHistory.map(order => `
      <div class="order-card border rounded-lg p-4">
        <div class="order-header">
          <div class="flex justify-between items-center">
            <div>
              <h4 class="font-bold">Order #${order.id}</h4>
              <p class="text-sm text-gray-600">Date: ${order.date}</p>
            </div>
            <span class="status-badge ${
              order.status === 'Delivered' ? 'status-delivered' : 
              order.status === 'Processing' ? 'status-processing' : 
              'status-cancelled'
            }">${order.status}</span>
          </div>
        </div>
        <div class="order-items mt-3 space-y-2">
          ${order.items.map(item => `
            <div class="order-item flex justify-between">
              <div>
                <p class="font-medium">${item.name}</p>
                <p class="text-sm text-gray-600">Quantity: ${item.quantity}</p>
              </div>
              <span class="font-medium">RM${(item.price * item.quantity).toFixed(2)}</span>
            </div>
          `).join('')}
          <div class="order-total border-t pt-3 mt-3 flex justify-between font-bold">
            <span>Total:</span>
            <span class="text-primary">RM${order.total.toFixed(2)}</span>
          </div>
        </div>
      </div>
    `).join('');
  }, 800);
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', init);
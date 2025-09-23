// E-commerce JavaScript functionality

class EcommerceApp {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeComponents();
        this.updateCartCount();
    }

    bindEvents() {
        // Add to cart buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.add-to-cart-btn, .add-to-cart-btn *')) {
                const button = e.target.closest('.add-to-cart-btn');
                if (button && !button.disabled) {
                    this.handleAddToCart(e, button);
                }
            }
        });

        // Quick view buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.quick-view-btn, .quick-view-btn *')) {
                e.preventDefault();
                const button = e.target.closest('.quick-view-btn');
                this.showQuickView(button.href);
            }
        });

        // Wishlist buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.wishlist-btn, .wishlist-btn *')) {
                const button = e.target.closest('.wishlist-btn');
                this.toggleWishlist(button);
            }
        });

        // Product image zoom
        document.querySelectorAll('.product-image-zoom').forEach(img => {
            img.addEventListener('mouseenter', this.enableImageZoom);
            img.addEventListener('mouseleave', this.disableImageZoom);
            img.addEventListener('mousemove', this.moveImageZoom);
        });

        // Quantity selectors
        document.addEventListener('click', (e) => {
            if (e.target.matches('.qty-btn')) {
                this.handleQuantityChange(e.target);
            }
        });

        // Filter toggles
        document.addEventListener('change', (e) => {
            if (e.target.matches('.filter-checkbox, .filter-radio, .filter-select')) {
                this.applyFilters();
            }
        });

        // Sort dropdown
        document.addEventListener('change', (e) => {
            if (e.target.matches('.sort-select')) {
                this.applySorting(e.target.value);
            }
        });

        // Search functionality
        const searchForm = document.querySelector('#product-search-form');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.performSearch();
            });
        }

        // Review form
        const reviewForm = document.querySelector('#review-form');
        if (reviewForm) {
            reviewForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitReview();
            });
        }

        // Newsletter subscription
        const newsletterForms = document.querySelectorAll('.newsletter-form');
        newsletterForms.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.subscribeNewsletter(form);
            });
        });
    }

    initializeComponents() {
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        // Initialize popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));

        // Initialize product image carousel
        this.initProductCarousel();

        // Initialize price range slider
        this.initPriceRangeSlider();

        // Initialize star rating
        this.initStarRating();

        // Initialize lazy loading for images
        this.initLazyLoading();

        // Initialize scroll animations
        this.initScrollAnimations();
    }

    async handleAddToCart(e, button) {
        e.preventDefault();
        
        const productId = button.dataset.productId;
        const variantId = button.dataset.variantId || null;
        const quantity = parseInt(button.dataset.quantity || 1);
        
        // Get quantity from form if exists
        const quantityInput = button.closest('.product-form')?.querySelector('input[name="quantity"]');
        const finalQuantity = quantityInput ? parseInt(quantityInput.value) : quantity;

        // Get variant from select if exists
        const variantSelect = button.closest('.product-form')?.querySelector('select[name="variant_id"]');
        const finalVariantId = variantSelect ? variantSelect.value : variantId;

        const originalContent = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';

        try {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', finalQuantity);
            if (finalVariantId) {
                formData.append('variant_id', finalVariantId);
            }

            const response = await fetch('/shop/cart/add', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccessMessage(data.message);
                this.updateCartCount();
                this.animateAddToCart(button);
            } else {
                this.showErrorMessage(data.message);
            }
        } catch (error) {
            this.showErrorMessage('Error adding product to cart');
        } finally {
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = originalContent;
            }, 2000);
        }
    }

    animateAddToCart(button) {
        button.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
        button.classList.add('btn-success');
        
        // Create flying cart animation
        const rect = button.getBoundingClientRect();
        const cartIcon = document.querySelector('.navbar .fa-shopping-cart');
        
        if (cartIcon) {
            const flyingIcon = document.createElement('i');
            flyingIcon.className = 'fas fa-shopping-cart position-fixed';
            flyingIcon.style.left = rect.left + 'px';
            flyingIcon.style.top = rect.top + 'px';
            flyingIcon.style.zIndex = '9999';
            flyingIcon.style.color = 'var(--primary-color)';
            flyingIcon.style.fontSize = '1.5rem';
            flyingIcon.style.transition = 'all 0.8s cubic-bezier(0.2, 1, 0.3, 1)';
            
            document.body.appendChild(flyingIcon);
            
            const cartRect = cartIcon.getBoundingClientRect();
            setTimeout(() => {
                flyingIcon.style.left = cartRect.left + 'px';
                flyingIcon.style.top = cartRect.top + 'px';
                flyingIcon.style.transform = 'scale(0)';
                flyingIcon.style.opacity = '0';
            }, 100);
            
            setTimeout(() => {
                document.body.removeChild(flyingIcon);
            }, 900);
        }
    }

    async showQuickView(productUrl) {
        try {
            const response = await fetch(productUrl.replace('/products/', '/products/ajax/quick-view/'));
            const html = await response.text();
            
            // Create modal
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Quick View</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${html}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                document.body.removeChild(modal);
            });
        } catch (error) {
            this.showErrorMessage('Error loading product details');
        }
    }

    async toggleWishlist(button) {
        const productId = button.dataset.productId;
        const isInWishlist = button.classList.contains('in-wishlist');
        
        try {
            const response = await fetch('/wishlist/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ product_id: productId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (data.in_wishlist) {
                    button.classList.add('in-wishlist');
                    button.innerHTML = '<i class="fas fa-heart text-danger"></i>';
                    this.showSuccessMessage('Added to wishlist');
                } else {
                    button.classList.remove('in-wishlist');
                    button.innerHTML = '<i class="far fa-heart"></i>';
                    this.showSuccessMessage('Removed from wishlist');
                }
            }
        } catch (error) {
            this.showErrorMessage('Error updating wishlist');
        }
    }

    initProductCarousel() {
        const carousels = document.querySelectorAll('.product-carousel');
        carousels.forEach(carousel => {
            new bootstrap.Carousel(carousel, {
                interval: false
            });
        });
    }

    initPriceRangeSlider() {
        const priceSlider = document.querySelector('#price-range-slider');
        if (priceSlider) {
            // Initialize price range slider (using noUiSlider or similar)
            // This would require including the noUiSlider library
        }
    }

    initStarRating() {
        const starRatings = document.querySelectorAll('.star-rating');
        starRatings.forEach(rating => {
            const stars = rating.querySelectorAll('.star');
            const input = rating.querySelector('input[type="hidden"]');
            
            stars.forEach((star, index) => {
                star.addEventListener('click', () => {
                    const value = index + 1;
                    input.value = value;
                    
                    stars.forEach((s, i) => {
                        if (i < value) {
                            s.classList.remove('far');
                            s.classList.add('fas', 'text-warning');
                        } else {
                            s.classList.remove('fas', 'text-warning');
                            s.classList.add('far');
                        }
                    });
                });
                
                star.addEventListener('mouseenter', () => {
                    const value = index + 1;
                    stars.forEach((s, i) => {
                        if (i < value) {
                            s.classList.add('text-warning');
                        } else {
                            s.classList.remove('text-warning');
                        }
                    });
                });
            });
            
            rating.addEventListener('mouseleave', () => {
                const currentValue = parseInt(input.value) || 0;
                stars.forEach((s, i) => {
                    if (i < currentValue) {
                        s.classList.add('text-warning');
                    } else {
                        s.classList.remove('text-warning');
                    }
                });
            });
        });
    }

    initLazyLoading() {
        if ('IntersectionObserver' in window) {
            const lazyImages = document.querySelectorAll('img[data-src]');
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            lazyImages.forEach(img => imageObserver.observe(img));
        }
    }

    initScrollAnimations() {
        if ('IntersectionObserver' in window) {
            const animatedElements = document.querySelectorAll('.animate-on-scroll');
            const animationObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animated');
                        animationObserver.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });

            animatedElements.forEach(el => animationObserver.observe(el));
        }
    }

    async updateCartCount() {
        try {
            const response = await fetch('/shop/cart/count');
            const data = await response.json();
            
            const cartCounts = document.querySelectorAll('.cart-count');
            cartCounts.forEach(count => {
                count.textContent = data.total_quantity || 0;
                count.style.display = data.total_quantity > 0 ? 'block' : 'none';
            });
        } catch (error) {
            console.log('Error updating cart count:', error);
        }
    }

    showSuccessMessage(message) {
        this.showToast(message, 'success');
    }

    showErrorMessage(message) {
        this.showToast(message, 'danger');
    }

    showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container') || this.createToastContainer();
        const toastId = 'toast-' + Date.now();
        
        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.role = 'alert';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${this.getToastIcon(type)} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    createToastContainer() {
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1060';
        document.body.appendChild(container);
        return container;
    }

    getToastIcon(type) {
        const icons = {
            'success': 'check-circle',
            'danger': 'exclamation-triangle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    // Image zoom functionality
    enableImageZoom(e) {
        const img = e.target;
        img.style.transform = 'scale(1.2)';
        img.style.cursor = 'zoom-in';
    }

    disableImageZoom(e) {
        const img = e.target;
        img.style.transform = 'scale(1)';
        img.style.cursor = 'default';
    }

    moveImageZoom(e) {
        // Implement image zoom on mouse move
        // This would create a magnifying glass effect
    }

    // Filter and search functionality
    applyFilters() {
        const form = document.querySelector('#product-filters');
        if (form) {
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            window.location.search = params.toString();
        }
    }

    applySorting(sortValue) {
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
    }

    performSearch() {
        const form = document.querySelector('#product-search-form');
        const query = form.querySelector('input[name="q"]').value;
        
        if (query.trim()) {
            window.location.href = `/shop/products/search?q=${encodeURIComponent(query)}`;
        }
    }

    async subscribeNewsletter(form) {
        const email = form.querySelector('input[type="email"]').value;
        const button = form.querySelector('button[type="submit"]');
        const originalText = button.innerHTML;
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';
        
        try {
            // Simulate newsletter subscription
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            this.showSuccessMessage('Successfully subscribed to newsletter!');
            form.reset();
        } catch (error) {
            this.showErrorMessage('Error subscribing to newsletter');
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.ecommerceApp = new EcommerceApp();
});

// Export for use in other scripts
export default EcommerceApp;
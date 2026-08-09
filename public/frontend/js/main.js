// ========================================
// Initialize AOS immediately (outside DOMContentLoaded)
// Scripts are at bottom of body, so DOM is already parsed
// ========================================
AOS.init({
  duration: 800,
  easing: 'slide',
  once: true
});

window.addEventListener('load', function () {
  AOS.refresh();
});

// ========================================
// Main Initialization
// ========================================
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  initPreloader();
  initTooltips();
  initMobileMenu();
  initStickyHeader();
  initHeroSlider();
  initTestimonialSlider();
  initBackToTop();
  initLightbox();
  initJarallax();
  initSearchBox();
  initPreventDefault();
  initSlideAnimations();
  initCategoryFilters();
  initProductQty();
  initCurrentYear();
  initCartPanel();
});

// ========================================
// Preloader
// ========================================
function initPreloader() {
  window.addEventListener('load', function () {
    var preloader = document.querySelector('.preloader');
    if (!preloader) return;
    preloader.style.transition = 'opacity 0.5s';
    preloader.style.opacity = '0';
    setTimeout(function () {
      preloader.remove();
    }, 500);
  });
}

// ========================================
// Bootstrap 5 Tooltips
// ========================================
function initTooltips() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(function (el) {
    new bootstrap.Tooltip(el);
  });
}

// ========================================
// Mobile Menu (replaces ClassyNav)
// ========================================
function initMobileMenu() {
  var toggler = document.querySelector('.navbarToggler');
  var closeBtn = document.querySelector('.classycloseIcon');
  var menu = document.querySelector('.classy-menu');
  if (!toggler || !menu) return;

  toggler.addEventListener('click', function () {
    menu.classList.add('menu-on');
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      menu.classList.remove('menu-on');
    });
  }

  // Handle dropdown toggles on mobile
  var dropdownParents = menu.querySelectorAll('.classynav > ul li');
  dropdownParents.forEach(function (li) {
    var submenu = li.querySelector('ul.dropdown');
    if (!submenu) return;

    var link = li.querySelector(':scope > a');
    if (!link) return;

    // Create dropdown indicator
    var indicator = document.createElement('span');
    indicator.className = 'dd-trigger';
    indicator.innerHTML = '<i class="fa fa-angle-down"></i>';
    li.style.position = 'relative';
    li.appendChild(indicator);

    indicator.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      submenu.classList.toggle('dropdown-open');
      indicator.classList.toggle('active');
    });
  });
}

// ========================================
// Sticky Header (replaces jQuery Sticky)
// ========================================
function initStickyHeader() {
  var mainMenu = document.querySelector('.famie-main-menu');
  if (!mainMenu) return;

  var stickyOffset = mainMenu.offsetTop;

  function handleScroll() {
    if (window.pageYOffset > stickyOffset) {
      mainMenu.classList.add('is-sticky');
      mainMenu.style.position = 'fixed';
      mainMenu.style.top = '0';
      mainMenu.style.left = '0';
      mainMenu.style.right = '0';
      mainMenu.style.zIndex = '9999';
    } else {
      mainMenu.classList.remove('is-sticky');
      mainMenu.style.position = '';
      mainMenu.style.top = '';
      mainMenu.style.left = '';
      mainMenu.style.right = '';
      mainMenu.style.zIndex = '';
    }
  }

  window.addEventListener('scroll', handleScroll);
  handleScroll();
}

// ========================================
// Hero Slider (Swiper, replaces Owl Carousel)
// ========================================
function initHeroSlider() {
  var heroEl = document.querySelector('.welcome-slides');
  if (!heroEl) return;

  wrapSwiperSlides(heroEl, 'single-welcome-slides');

  new Swiper('.welcome-slides', {
    slidesPerView: 1,
    loop: true,
    autoplay: {
      delay: 5000,
      disableOnInteraction: false
    },
    speed: 1000,
    effect: 'fade',
    fadeEffect: { crossFade: true }
  });
}

// ========================================
// Testimonial Slider (Swiper, replaces Owl Carousel)
// ========================================
function initTestimonialSlider() {
  var testimonialEl = document.querySelector('.testimonial-slides');
  if (!testimonialEl) return;

  wrapSwiperSlides(testimonialEl, 'single-slide');

  // Add navigation buttons
  var prevBtn = document.createElement('div');
  prevBtn.className = 'swiper-button-prev';
  prevBtn.innerHTML = '<i class="arrow_left"></i>';
  var nextBtn = document.createElement('div');
  nextBtn.className = 'swiper-button-next';
  nextBtn.innerHTML = '<i class="arrow_right"></i>';
  testimonialEl.appendChild(prevBtn);
  testimonialEl.appendChild(nextBtn);

  new Swiper('.testimonial-slides', {
    slidesPerView: 1,
    loop: true,
    autoplay: {
      delay: 5000,
      disableOnInteraction: false
    },
    speed: 1000,
    effect: 'fade',
    fadeEffect: { crossFade: true },
    navigation: {
      nextEl: '.swiper-button-next',
      prevEl: '.swiper-button-prev'
    }
  });
}

// ========================================
// Swiper Slide Wrapper Helper
// ========================================
function wrapSwiperSlides(container, slideClass) {
  if (container.querySelector('.swiper-wrapper')) return;

  var children = Array.from(container.children);
  var wrapper = document.createElement('div');
  wrapper.className = 'swiper-wrapper';

  children.forEach(function (child) {
    child.classList.add('swiper-slide');
    wrapper.appendChild(child);
  });

  container.appendChild(wrapper);
}

// ========================================
// Back to Top (replaces jQuery ScrollUp)
// ========================================
function initBackToTop() {
  // Create the button
  var scrollUp = document.createElement('a');
  scrollUp.id = 'scrollUp';
  scrollUp.href = '#';
  scrollUp.innerHTML = '<i class="arrow_up"></i>';
  scrollUp.style.display = 'none';
  scrollUp.style.position = 'fixed';
  document.body.appendChild(scrollUp);

  window.addEventListener('scroll', function () {
    if (window.pageYOffset > 300) {
      scrollUp.style.display = 'block';
    } else {
      scrollUp.style.display = 'none';
    }
  });

  scrollUp.addEventListener('click', function (e) {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

// ========================================
// Lightbox (GLightbox, replaces Magnific Popup)
// ========================================
function initLightbox() {
  if (typeof GLightbox === 'undefined') return;
  GLightbox({ selector: '.glightbox' });
}

// ========================================
// Jarallax (parallax backgrounds)
// ========================================
function initJarallax() {
  if (typeof jarallax === 'undefined') return;
  jarallax(document.querySelectorAll('.jarallax'), {
    speed: 0.2
  });
}

// ========================================
// Search Box Toggle
// ========================================
function initSearchBox() {
  var searchIcon = document.getElementById('searchIcon');
  var searchForm = document.querySelector('.search-form');
  var closeIcon = document.querySelector('.closeIcon');

  if (searchIcon && searchForm) {
    searchIcon.addEventListener('click', function () {
      searchForm.classList.toggle('search-active');
    });
  }

  if (closeIcon && searchForm) {
    closeIcon.addEventListener('click', function () {
      searchForm.classList.remove('search-active');
    });
  }
}

// ========================================
// Prevent Default on # Links
// ========================================
function initPreventDefault() {
  document.querySelectorAll('a[href="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
    });
  });
}

// ========================================
// Slide Animations (data-animation, data-delay, data-duration)
// ========================================
function initSlideAnimations() {
  document.querySelectorAll('[data-delay]').forEach(function (el) {
    el.style.animationDelay = el.getAttribute('data-delay');
  });

  document.querySelectorAll('[data-duration]').forEach(function (el) {
    el.style.animationDuration = el.getAttribute('data-duration');
  });
}

// ========================================
// Category Filters (shop + recipes)
// ========================================
function initCategoryFilters() {
  var filterBtns = document.querySelectorAll('.shop-filter-btn, .recipe-filter-btns .btn');
  if (!filterBtns.length) return;

  filterBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var filter = btn.getAttribute('data-filter');
      var parent = btn.closest('.shop-category-filters, .recipe-filter-btns');
      if (!parent) return;

      parent.querySelectorAll('.btn').forEach(function (b) {
        b.classList.remove('active');
      });
      btn.classList.add('active');

      var items;
      if (parent.classList.contains('shop-category-filters')) {
        items = document.querySelectorAll('.shop-area [data-category]');
      } else {
        items = document.querySelectorAll('.recipes-area [data-category]');
      }

      items.forEach(function (item) {
        if (filter === 'all' || item.getAttribute('data-category') === filter) {
          item.style.display = '';
        } else {
          item.style.display = 'none';
        }
      });
    });
  });
}

// ========================================
// Product Quantity Selector
// ========================================
function initProductQty() {
  var qtyWrapper = document.querySelector('.qty-selector');
  if (!qtyWrapper) return;

  var minusBtn = qtyWrapper.querySelector('.qty-minus');
  var plusBtn = qtyWrapper.querySelector('.qty-plus');
  var input = qtyWrapper.querySelector('.qty-input');
  if (!minusBtn || !plusBtn || !input) return;

  minusBtn.addEventListener('click', function () {
    var val = parseInt(input.value) || 1;
    if (val > 1) input.value = val - 1;
  });

  plusBtn.addEventListener('click', function () {
    var val = parseInt(input.value) || 1;
    input.value = val + 1;
  });
}

// ========================================
// Slide-Out Cart Panel
// ========================================
function initCartPanel() {
  var cartIcon = document.getElementById('cartIcon');
  var cartPanel = document.getElementById('cartPanel');
  var cartOverlay = document.getElementById('cartOverlay');
  var cartClose = document.getElementById('cartClose');
  if (!cartIcon || !cartPanel) return;

  function openCart() {
    cartPanel.classList.add('cart-open');
    if (cartOverlay) cartOverlay.classList.add('cart-open');
    document.body.style.overflow = 'hidden';
  }

  function closeCart() {
    cartPanel.classList.remove('cart-open');
    if (cartOverlay) cartOverlay.classList.remove('cart-open');
    document.body.style.overflow = '';
  }

  cartIcon.addEventListener('click', function (e) {
    e.preventDefault();
    openCart();
  });

  if (cartClose) {
    cartClose.addEventListener('click', closeCart);
  }

  if (cartOverlay) {
    cartOverlay.addEventListener('click', closeCart);
  }

  // Close on Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && cartPanel.classList.contains('cart-open')) {
      closeCart();
    }
  });

  // Cart quantity buttons
  cartPanel.querySelectorAll('.cart-item').forEach(function (item) {
    var minusBtn = item.querySelector('.cart-qty-minus');
    var plusBtn = item.querySelector('.cart-qty-plus');
    var qtyVal = item.querySelector('.cart-qty-val');
    var removeBtn = item.querySelector('.cart-item-remove');

    if (minusBtn && qtyVal) {
      minusBtn.addEventListener('click', function () {
        var val = parseInt(qtyVal.textContent) || 1;
        if (val > 1) {
          qtyVal.textContent = val - 1;
          updateCartSubtotal();
        }
      });
    }

    if (plusBtn && qtyVal) {
      plusBtn.addEventListener('click', function () {
        var val = parseInt(qtyVal.textContent) || 1;
        qtyVal.textContent = val + 1;
        updateCartSubtotal();
      });
    }

    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        item.style.transition = 'opacity 0.3s, transform 0.3s';
        item.style.opacity = '0';
        item.style.transform = 'translateX(30px)';
        setTimeout(function () {
          item.remove();
          updateCartSubtotal();
          updateCartCount();
        }, 300);
      });
    }
  });

  function updateCartCount() {
    var items = cartPanel.querySelectorAll('.cart-item');
    var count = items.length;
    var countEls = document.querySelectorAll('.cart-quantity, .cart-count');
    countEls.forEach(function (el) {
      el.textContent = count;
    });
  }

  function updateCartSubtotal() {
    var total = 0;
    cartPanel.querySelectorAll('.cart-item').forEach(function (item) {
      var priceEl = item.querySelector('.cart-item-price');
      var qtyEl = item.querySelector('.cart-qty-val');
      if (priceEl && qtyEl) {
        var price = parseFloat(priceEl.textContent.replace('$', '')) || 0;
        var qty = parseInt(qtyEl.textContent) || 1;
        total += price * qty;
      }
    });
    var subtotalEl = cartPanel.querySelector('.cart-subtotal-price');
    if (subtotalEl) {
      subtotalEl.textContent = '$' + total.toFixed(2);
    }
  }
}

// ========================================
// Dynamic Copyright Year
// ========================================
function initCurrentYear() {
  document.querySelectorAll('.current-year').forEach(function (el) {
    el.textContent = new Date().getFullYear();
  });
}

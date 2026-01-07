/**
 * Responsive Navigation Handler
 * EduManage Pro - School Management System
 *
 * Handles mobile menu toggle, overlay, and responsive behaviors
 */

(function() {
	'use strict';

	// Wait for DOM to be ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		// Create mobile menu toggle if it doesn't exist
		createMobileMenu();

		// Create mobile overlay if it doesn't exist
		createMobileOverlay();

		// Add close button to sidebar
		addSidebarCloseButton();

		// Setup event listeners
		setupEventListeners();

		// Handle window resize
		handleResize();
		window.addEventListener('resize', handleResize);

		// Handle orientation change
		window.addEventListener('orientationchange', function() {
			setTimeout(handleResize, 100);
		});
	}

	function createMobileMenu() {
		// Check if mobile menu already exists
		if (document.querySelector('.mobile-menu-toggle')) {
			return;
		}

		const mobileToggle = document.createElement('button');
		mobileToggle.className = 'mobile-menu-toggle';
		mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
		mobileToggle.setAttribute('aria-label', 'Toggle Mobile Menu');

		document.body.appendChild(mobileToggle);
	}

	function createMobileOverlay() {
		// Check if overlay already exists
		if (document.querySelector('.mobile-overlay')) {
			return;
		}

		const overlay = document.createElement('div');
		overlay.className = 'mobile-overlay';
		document.body.appendChild(overlay);
	}

	function addSidebarCloseButton() {
		const sidebar = document.querySelector('.sidebar');
		if (!sidebar) return;

		// Check if close button already exists
		if (sidebar.querySelector('.sidebar-close')) {
			return;
		}

		const closeBtn = document.createElement('button');
		closeBtn.className = 'sidebar-close';
		closeBtn.innerHTML = '<i class="fas fa-times"></i>';
		closeBtn.setAttribute('aria-label', 'Close Menu');

		sidebar.appendChild(closeBtn);
	}

	function setupEventListeners() {
		// Mobile menu toggle
		const mobileToggle = document.querySelector('.mobile-menu-toggle');
		if (mobileToggle) {
			mobileToggle.addEventListener('click', toggleMobileMenu);
		}

		// Mobile overlay click
		const overlay = document.querySelector('.mobile-overlay');
		if (overlay) {
			overlay.addEventListener('click', closeMobileMenu);
		}

		// Sidebar close button
		const closeBtn = document.querySelector('.sidebar-close');
		if (closeBtn) {
			closeBtn.addEventListener('click', closeMobileMenu);
		}

		// Sidebar menu items - close on click (mobile only)
		const sidebar = document.querySelector('.sidebar');
		if (sidebar) {
			const menuItems = sidebar.querySelectorAll('.sidebar-menu li');
			menuItems.forEach(item => {
				item.addEventListener('click', function() {
					if (window.innerWidth <= 768) {
						closeMobileMenu();
					}
				});
			});
		}

		// Close menu on escape key
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
				closeMobileMenu();
			}
		});

		// Handle table horizontal scroll indicator
		handleTableScroll();
	}

	function toggleMobileMenu() {
		const sidebar = document.querySelector('.sidebar');
		const overlay = document.querySelector('.mobile-overlay');
		const body = document.body;

		if (sidebar) {
			sidebar.classList.toggle('mobile-active');
		}

		if (overlay) {
			overlay.classList.toggle('active');
		}

		body.classList.toggle('sidebar-open');

		// Prevent body scroll when menu is open
		if (body.classList.contains('sidebar-open')) {
			body.style.overflow = 'hidden';
		} else {
			body.style.overflow = '';
		}
	}

	function closeMobileMenu() {
		const sidebar = document.querySelector('.sidebar');
		const overlay = document.querySelector('.mobile-overlay');
		const body = document.body;

		if (sidebar) {
			sidebar.classList.remove('mobile-active');
		}

		if (overlay) {
			overlay.classList.remove('active');
		}

		body.classList.remove('sidebar-open');
		body.style.overflow = '';
	}

	function handleResize() {
		const width = window.innerWidth;

		// Close mobile menu if window is resized to desktop
		if (width > 768) {
			closeMobileMenu();
		}

		// Update table scroll indicators
		handleTableScroll();
	}

	function handleTableScroll() {
		// Add scroll indicator for tables on mobile
		const tableCards = document.querySelectorAll('.table-card');

		tableCards.forEach(card => {
			const table = card.querySelector('.data-table');
			if (!table) return;

			// Check if table is scrollable
			const isScrollable = table.scrollWidth > card.clientWidth;

			if (isScrollable && window.innerWidth <= 768) {
				// Add scroll indicator
				if (!card.querySelector('.scroll-indicator')) {
					const indicator = document.createElement('div');
					indicator.className = 'scroll-indicator';
					indicator.innerHTML = '<i class="fas fa-chevron-right"></i> Scroll for more';
					indicator.style.cssText = 'position:absolute;top:10px;right:10px;background:rgba(0,0,0,0.7);color:white;padding:5px 10px;border-radius:5px;font-size:11px;pointer-events:none;';
					card.style.position = 'relative';
					card.appendChild(indicator);

					// Hide indicator after scroll
					card.addEventListener('scroll', function() {
						if (this.scrollLeft > 10 && indicator) {
							indicator.style.opacity = '0';
							setTimeout(() => indicator.remove(), 300);
						}
					});
				}
			}
		});
	}

	// Viewport height fix for mobile browsers
	function setVH() {
		const vh = window.innerHeight * 0.01;
		document.documentElement.style.setProperty('--vh', `${vh}px`);
	}

	setVH();
	window.addEventListener('resize', setVH);
	window.addEventListener('orientationchange', () => setTimeout(setVH, 100));

	// Touch support detection
	if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
		document.body.classList.add('touch-device');
	}

	// Add smooth scroll behavior
	if ('scrollBehavior' in document.documentElement.style) {
		document.documentElement.style.scrollBehavior = 'smooth';
	}

	// Expose functions globally if needed
	window.EduManageResponsive = {
		toggleMobileMenu: toggleMobileMenu,
		closeMobileMenu: closeMobileMenu,
		handleResize: handleResize
	};

})();

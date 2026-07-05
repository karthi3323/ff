// Base URL for AJAX calls and redirects
const BASE_URL = window.location.origin + '/ff/';


console.log('Current URL:', window.location.href);
console.log('BASE_URL:', BASE_URL);

// Layout Manager Class
class LayoutManager {
    constructor() {
        this.sidebar = $('#sidebar');
        this.mainContent = $('#mainContent');
        this.mainHeader = $('#mainHeader');
        this.headerToggle = $('#headerToggle');
        this.mobileOverlay = $('#mobileOverlay');
        this.mobileMenuToggle = $('#mobileMenuToggle');
        
        this.isCollapsed = false;
        this.isMobile = window.innerWidth <= 768;
        
        this.init();
    }
    
    init() {
        console.log('LayoutManager initialized');
        
        this.bindEvents();
        this.loadState();
        this.handleResponsive();
        this.updateUI();
        this.addTooltipAttributes(); // Add tooltip attributes
        
        // Initialize other components
        if (typeof initDataTables === 'function') {
            initDataTables();
        } else {
            console.log('initDataTables function not found');
        }
        if (typeof initDataTables === 'function') {
            initTooltips();
        } else {
            console.log('initTooltips function not found');
        }
    }
    
    bindEvents() {
        // Header toggle for sidebar
        this.headerToggle.on('click', (e) => {
            console.log('dddddd');
            e.preventDefault();
            e.stopPropagation();
            this.toggleSidebar();
        });
        
        // Mobile menu toggle
        this.mobileMenuToggle.on('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleMobileMenu();
        });
        
        // Mobile overlay click
        this.mobileOverlay.on('click', () => {
            this.closeMobileSidebar();
        });
        
        // Close sidebar when clicking on links (mobile)
        this.sidebar.on('click', 'a:not([data-bs-toggle="collapse"])', () => {
            if (this.isMobile) {
                this.closeMobileSidebar();
            }
        });
        
        // Window resize handler
        $(window).on('resize', () => {
            this.handleResize();
        });
        
        // Keyboard shortcuts
        $(document).on('keydown', (e) => {
            this.handleKeyboard(e);
        });
    }
    
    toggleSidebar() {
        if (this.isMobile) {
            this.toggleMobileSidebar();
        } else {
            this.toggleDesktopSidebar();
            console.log('ppppppp');
        }
    }
    
    toggleDesktopSidebar() {
        this.isCollapsed = !this.isCollapsed;
        
        if (this.isCollapsed) {
            // Collapse sidebar (show only icons)
            this.sidebar.addClass('collapsed');
            this.mainContent.addClass('collapsed');
            this.mainHeader.addClass('collapsed').removeClass('expanded');
        } else {
            // Expand sidebar (show full sidebar)
            this.sidebar.removeClass('collapsed');
            this.mainContent.removeClass('collapsed');
            this.mainHeader.addClass('expanded').removeClass('collapsed');
        }
        
        this.saveState();
        this.updateToggleIcon();
        
        console.log('Desktop sidebar toggled:', this.isCollapsed ? 'collapsed (icons only)' : 'expanded (full)');
    }
    
    toggleMobileSidebar() {
        if (this.sidebar.hasClass('mobile-open')) {
            this.closeMobileSidebar();
        } else {
            this.openMobileSidebar();
        }
    }
    
    openMobileSidebar() {
        this.sidebar.addClass('mobile-open');
        this.mobileOverlay.addClass('show');
        this.updateToggleIcon();
        
        // Add animation
        this.sidebar.addClass('slide-in-left');
        
        console.log('Mobile sidebar opened');
    }
    
    closeMobileSidebar() {
        this.sidebar.removeClass('mobile-open');
        this.mobileOverlay.removeClass('show');
        this.updateToggleIcon();
        
        console.log('Mobile sidebar closed');
    }
    
    toggleMobileMenu() {
        // Implement mobile menu functionality if needed
        console.log('Mobile menu toggled');
    }
    
    updateToggleIcon() {
        const icon = this.headerToggle.find('i');
        
        if (this.isMobile) {
            if (this.sidebar.hasClass('mobile-open')) {
                icon.removeClass('fa-bars').addClass('fa-times');
            } else {
                icon.removeClass('fa-times').addClass('fa-bars');
            }
        } else {
            if (this.isCollapsed) {
                icon.removeClass('fa-bars').addClass('fa-chevron-right');
            } else {
                icon.removeClass('fa-chevron-right').addClass('fa-bars');
            }
        }
    }
    
    handleResize() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth <= 768;
        
        if (wasMobile !== this.isMobile) {
            this.handleResponsive();
        }
    }
    
    handleResponsive() {
        if (this.isMobile) {
            // Mobile behavior
            this.sidebar.removeClass('collapsed');
            this.mainContent.removeClass('collapsed');
            this.mainHeader.removeClass('collapsed expanded');
            this.closeMobileSidebar();
        } else {
            // Desktop behavior
            this.sidebar.removeClass('mobile-open');
            this.mobileOverlay.removeClass('show');
            this.loadState();
        }
        
        this.updateToggleIcon();
        console.log('Responsive handled - Mobile:', this.isMobile);
    }
    
    saveState() {
        if (!this.isMobile) {
            localStorage.setItem('sidebarCollapsed', this.isCollapsed);
        }
    }
    
    loadState() {
        if (!this.isMobile) {
            const savedState = localStorage.getItem('sidebarCollapsed');
            this.isCollapsed = savedState === 'true';
            
            if (this.isCollapsed) {
                this.sidebar.addClass('collapsed');
                this.mainContent.addClass('collapsed');
                this.mainHeader.addClass('collapsed').removeClass('expanded');
            } else {
                this.sidebar.removeClass('collapsed');
                this.mainContent.removeClass('collapsed');
                this.mainHeader.addClass('expanded').removeClass('collapsed');
            }
            
            this.updateToggleIcon();
        }
    }
    
    // Add tooltip attributes to sidebar links
    addTooltipAttributes() {
        this.sidebar.find('.nav-link').each(function() {
            const $link = $(this);
            const text = $link.find('.nav-text').text().trim();
            if (text) {
                $link.attr('data-tooltip', text);
            }
        });
        
        // Add tooltip for logout link
        this.sidebar.find('.logout-link').attr('data-tooltip', 'Logout');
    }
    
    /* updateUI() {
        this.updateToggleIcon();
        
        // Add active state animations
        this.sidebar.find('.nav-link.active').parent().addClass('active-item');
    } */
    updateUI() {
        this.updateToggleIcon();
        
        // Remove any existing active-item classes first
        this.sidebar.find('.nav-item').removeClass('active-item');
        
        // Add active state animations only to the currently active link's parent
        this.sidebar.find('.nav-link.active').parent().addClass('active-item');
    }
    
    handleKeyboard(e) {
        // Ctrl/Cmd + B to toggle sidebar
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            this.toggleSidebar();
        }
        
        // Escape key to close mobile sidebar
        if (e.key === 'Escape' && this.isMobile && this.sidebar.hasClass('mobile-open')) {
            this.closeMobileSidebar();
        }
    }
    
    // ... rest of the methods remain the same
}

// SweetAlert confirmations
function confirmDelete(message, url) {
    Swal.fire({
        title: 'Are you sure?',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        background: '#fff',
        backdrop: 'rgba(0,0,0,0.4)',
        focusCancel: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = BASE_URL + url;
        }
    });
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return false;
    }
    return true;
}

// Loading state management
function setLoadingState(element, isLoading) {
    const $element = $(element);
    if (isLoading) {
        $element.addClass('loading').prop('disabled', true);
    } else {
        $element.removeClass('loading').prop('disabled', false);
    }
}

// Notification helper
function showNotification(message, type = 'success') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    
    Toast.fire({
        icon: type,
        title: message
    });
}

// Main initialization
$(document).ready(function() {
    console.log('Billing Software initialized');

    // Prevent forms from submitting on Enter key press
    $(document).on('keydown', 'form', function(event) {
        if (event.keyCode === 13 && event.target.tagName.toLowerCase() !== 'textarea') {
            event.preventDefault();
            return false;
        }
    });

    // The SidebarManager from sidebar.js is initialized automatically on document ready.
    // This file should only contain other page initializations and global helpers.

    // Initialize other components
    if (typeof initDataTables === 'function') {
        initDataTables();
    } else {
        console.log('initDataTables function not found');
    }
    if (typeof initTooltips === 'function') {
        initTooltips();
    }
    
    // Initialize layout manager
    window.layoutManager = new LayoutManager();
    
    // Make utility functions globally available
    window.confirmDelete = confirmDelete;
    window.validateForm = validateForm;
    window.setLoadingState = setLoadingState;
    window.showNotification = showNotification;
    
    // Auto-calculate functions for invoices
    window.autoCalculateAmounts = function() {
        console.log('Auto calculation triggered');
    };
    
    console.log('All components initialized successfully');
});
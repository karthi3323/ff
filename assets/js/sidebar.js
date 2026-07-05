// Enhanced Sidebar Functionality with Persistent State
class SidebarManager {
    constructor() {
        this.sidebar = $('#sidebar');
        this.mainContent = $('#mainContent');
        this.toggleBtn = $('#sidebarToggle');
        this.isCollapsed = false;
        this.isMobile = false;
        
        this.init();
    }
    
    init() {
        this.checkViewport();
        this.bindEvents();
        this.loadState();
        this.addDataTitles();
        
        // Apply initial state without transition to prevent flash
        setTimeout(() => {
            this.sidebar.addClass('initialized');
        }, 100);
    }
    
    checkViewport() {
        this.isMobile = $(window).width() <= 768;
    }
    
    bindEvents() {
        // Toggle button
        $(document).on('click', '#sidebarToggle', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggle();
        });
        
        // Close sidebar when clicking outside on mobile
        $(document).on('click', (e) => {
            if (this.isMobile && this.isOpen()) {
                if (!$(e.target).closest('#sidebar').length && 
                    !$(e.target).is('#sidebarToggle')) {
                    this.closeMobile();
                }
            }
        });
        
        // Handle window resize with debounce
        let resizeTimer;
        $(window).on('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                this.handleResize();
            }, 250);
        });
        
        // Prevent sidebar state change on menu clicks
        $('#sidebar .nav-link').on('click', (e) => {
            if (this.isMobile) {
                // Close mobile sidebar after delay
                setTimeout(() => this.closeMobile(), 300);
            }
            // Don't change collapsed/expanded state on desktop
        });
        
        // Handle dropdown toggles separately
        $('#sidebar .dropdown-toggle').on('click', (e) => {
            if (this.isMobile) {
                // Let Bootstrap handle the dropdown
                return true;
            }
            // On desktop, prevent the click from affecting sidebar state
            e.stopPropagation();
        });
    }
    
    toggle() {
        if (this.isMobile) {
            this.toggleMobile();
        } else {
            this.toggleDesktop();
        }
    }
    
    toggleMobile() {
        if (this.isOpen()) {
            this.closeMobile();
        } else {
            this.openMobile();
        }
    }
    
    toggleDesktop() {
        if (this.isCollapsed) {
            this.expand();
        } else {
            this.collapse();
        }
    }
    
    openMobile() {
        this.sidebar.addClass('show');
        $('body').addClass('sidebar-open-mobile');
        this.updateToggleIcon();
    }
    
    closeMobile() {
        this.sidebar.removeClass('show');
        $('body').removeClass('sidebar-open-mobile');
        this.updateToggleIcon();
    }
    
    collapse() {
        this.sidebar.addClass('collapsed');
        this.mainContent.addClass('expanded');
        this.isCollapsed = true;
        this.saveState();
        this.updateToggleIcon();
    }
    
    expand() {
        this.sidebar.removeClass('collapsed');
        this.mainContent.removeClass('expanded');
        this.isCollapsed = false;
        this.saveState();
        this.updateToggleIcon();
    }
    
    isOpen() {
        return this.sidebar.hasClass('show');
    }
    
    updateToggleIcon() {
        const icon = this.toggleBtn.find('i');
        if (this.isMobile) {
            icon.toggleClass('fa-bars fa-times', this.isOpen());
        } else {
            icon.toggleClass('fa-bars fa-chevron-left', this.isCollapsed);
        }
    }
    
    saveState() {
        // Only save desktop state
        if (!this.isMobile) {
            localStorage.setItem('sidebarCollapsed', this.isCollapsed);
            localStorage.setItem('sidebarStateSaved', 'true');
        }
    }
    
    loadState() {
        // Check if we should restore state
        const stateSaved = localStorage.getItem('sidebarStateSaved') === 'true';
        
        if (!this.isMobile && stateSaved) {
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                // Apply collapsed state immediately without transition
                this.sidebar.addClass('collapsed no-transition');
                this.mainContent.addClass('expanded no-transition');
                this.isCollapsed = true;
                
                // Remove no-transition after a delay
                setTimeout(() => {
                    this.sidebar.removeClass('no-transition');
                    this.mainContent.removeClass('no-transition');
                }, 100);
            } else {
                this.expand();
            }
        } else {
            // Default to expanded state
            this.expand();
        }
    }
    
    handleResize() {
        const wasMobile = this.isMobile;
        this.checkViewport();
        
        if (wasMobile !== this.isMobile) {
            if (this.isMobile) {
                // Switch to mobile - close sidebar and reset to expanded
                this.closeMobile();
                this.sidebar.removeClass('collapsed');
                this.mainContent.removeClass('expanded');
            } else {
                // Switch to desktop - restore saved state
                this.closeMobile();
                this.loadState();
            }
        }
    }
    
    addDataTitles() {
        $('#sidebar .nav-link').each(function() {
            const text = $(this).find('.nav-text').text().trim();
            $(this).attr('data-tooltip', text);
        });
    }
}

// Initialize sidebar manager
let sidebarManager;

$(document).ready(function() {
    sidebarManager = new SidebarManager();
    window.sidebarManager = sidebarManager;
    
    // Keyboard shortcut
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            sidebarManager.toggle();
        }
    });
});
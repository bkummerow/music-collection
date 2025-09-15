/**
 * Demo-specific functionality for Music Collection Manager
 * This module handles demo site features like welcome modal and demo reset
 */

class DemoManager {
    constructor(app) {
        this.app = app;
        this.init();
    }

    init() {
        // Check for welcome modal on demo site
        this.checkForWelcomeModal();
    }

    // Handle demo reset
    async handleDemoReset() {
        this.showResetDemoModal();
    }

    showResetDemoModal() {
        // Create modal if it doesn't exist
        let modal = document.getElementById('resetDemoModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'resetDemoModal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Reset Demo</h2>
                        <span class="close" id="resetModalClose">&times;</span>
                    </div>
                    <div class="modal-body">
                        <h3>Are you sure you want to reset the demo?</h3>
                        <div class="reset-info">
                            <p><strong>This will:</strong></p>
                            <ul>
                                <li>Restore password to <code>admin123</code></li>
                                <li>Replace all data with sample albums</li>
                                <li>Log out all current users</li>
                            </ul>
                            <p class="warning">This action cannot be undone.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="resetCancelBtn">Cancel</button>
                        <button type="button" class="btn btn-danger" id="resetConfirmBtn">Reset Demo</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Add event listeners
            document.getElementById('resetModalClose').addEventListener('click', () => {
                modal.style.display = 'none';
            });
            
            document.getElementById('resetCancelBtn').addEventListener('click', () => {
                modal.style.display = 'none';
            });
            
            document.getElementById('resetConfirmBtn').addEventListener('click', () => {
                this.confirmResetDemo();
                modal.style.display = 'none';
            });
            
            // Close modal when clicking outside
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
        
        // Show modal
        modal.style.display = 'block';
    }

    async confirmResetDemo() {
        try {
            const response = await fetch('api/music_api.php?action=reset_demo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.app.showMessage('Demo reset successfully! Password restored to admin123 and sample data loaded.', 'success');
                    // Reload the page to reflect changes
                    window.location.reload();
                } else {
                    this.app.showMessage('Failed to reset demo: ' + result.message, 'error');
                }
            } else {
                this.app.showMessage('Failed to reset demo. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Error resetting demo:', error);
            this.app.showMessage('Failed to reset demo. Please try again.', 'error');
        }
    }

    showDemoResetSuccessModal(notification) {
        // Create modal if it doesn't exist
        let modal = document.getElementById('demoResetSuccessModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'demoResetSuccessModal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Demo Reset Complete</h2>
                        <span class="close" id="demoSuccessModalClose">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="success-info">
                            <p><strong>Demo has been successfully reset!</strong></p>
                            <ul>
                                <li>Password restored to <code>admin123</code></li>
                                <li>Sample data refreshed with demo albums</li>
                                <li>All users have been logged out</li>
                            </ul>
                            <p class="info">Click "Continue" to reload the page and see the changes.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="demoSuccessContinueBtn">Continue</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Add event listeners
            document.getElementById('demoSuccessModalClose').addEventListener('click', () => {
                this.handleDemoResetSuccess();
            });
            
            document.getElementById('demoSuccessContinueBtn').addEventListener('click', () => {
                this.handleDemoResetSuccess();
            });
            
            // Close modal when clicking outside
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.handleDemoResetSuccess();
                }
            });
        }
        
        // Show modal
        modal.style.display = 'block';
    }

    handleDemoResetSuccess() {
        // Logout by clearing session
        fetch('api/music_api.php?action=logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(() => {
            // Reload the page
            window.location.reload();
        }).catch(() => {
            // Reload even if logout fails
            window.location.reload();
        });
    }

    checkForWelcomeModal() {
        // Check if this is the demo site and if user hasn't seen the welcome modal
        const isDemoSite = window.location.hostname.includes('railway.app') || 
                          window.location.hostname.includes('herokuapp.com') ||
                          window.location.hostname.includes('netlify.app') ||
                          window.location.hostname.includes('vercel.app');
        
        if (isDemoSite) {
            const hasSeenWelcome = localStorage.getItem('hasSeenWelcomeModal');
            if (!hasSeenWelcome) {
                // Show welcome modal after a short delay
                setTimeout(() => {
                    this.showWelcomeModal();
                }, 1000);
            }
        }
    }

    showWelcomeModal() {
        // Create modal if it doesn't exist
        let modal = document.getElementById('welcomeModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'welcomeModal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content welcome-modal-content">
                    <div class="modal-header">
                        <h2>Welcome to Music Collection Manager Demo</h2>
                        <span class="close" id="welcomeModalClose">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="welcome-info">
                            <div class="demo-features">
                                <h3>Getting Started</h3>
                                <ul>
                                    <li><strong>Login:</strong> Use password <code>admin123</code> to access editing features</li>
                                    <li><strong>Add/Edit/Delete Albums:</strong> Add new albums; edit and delete existing albums</li>
                                </ul>
                                
                                <h3>Features to Try</h3>
                                <ul>
                                    <li><strong>Search & Filter:</strong> Use the search bar and filter buttons</li>
                                    <li><strong>Statistics:</strong> View collection analytics in the sidebar or the dropdown</li>
                                    <li><strong>Settings:</strong> Click the gear icon to customize the interface</li>
                                </ul>
                                
                                <h3>Demo Reset</h3>
                                <ul>
                                    <li><strong>Reset Demo:</strong> Use the "Reset Demo" button in settings to restore original data and the <code>admin123</code> password</li>
                                </ul>
                            </div>
                            
                            <div class="demo-note">
                                <p><strong>Note:</strong> This is a shared demo environment. Other users may be using it simultaneously, so changes might be reset by other visitors.</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="welcomeGotItBtn">Got it, let's start!</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Add event listeners
            document.getElementById('welcomeModalClose').addEventListener('click', () => {
                this.closeWelcomeModal();
            });
            
            document.getElementById('welcomeGotItBtn').addEventListener('click', () => {
                this.closeWelcomeModal();
            });
            
            // Close modal when clicking outside
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeWelcomeModal();
                }
            });
        }
        
        // Show modal
        modal.style.display = 'block';
    }

    closeWelcomeModal() {
        // Mark as seen in localStorage
        localStorage.setItem('hasSeenWelcomeModal', 'true');
        
        // Hide modal
        const modal = document.getElementById('welcomeModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Method to handle demo reset notifications
    handleDemoResetNotification(notification) {
        this.showDemoResetSuccessModal(notification);
    }
}

// Export for use in main app
window.DemoManager = DemoManager;

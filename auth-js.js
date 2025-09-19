// ========================================
// FESTALAUREA - AUTHENTICATION JS
// ========================================

const API_URL = 'http://localhost/festalaurea/api';

// Handle Registration
document.getElementById('registerForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        firstName: document.getElementById('firstName').value,
        lastName: document.getElementById('lastName').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        password: document.getElementById('password').value,
        accountType: document.getElementById('accountType').value,
        businessName: document.getElementById('businessName')?.value,
        vatNumber: document.getElementById('vatNumber')?.value
    };
    
    // Validate password
    if (formData.password.length < 8) {
        showError('La password deve essere di almeno 8 caratteri');
        return;
    }
    
    // Validate terms
    if (!document.getElementById('terms').checked) {
        showError('Devi accettare i termini e condizioni');
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}/auth/register.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Show success modal
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            
            // Store auth data
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('user_data', JSON.stringify(data.user));
            
            // Redirect after 2 seconds
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 2000);
        } else {
            showError(data.message || 'Errore durante la registrazione');
        }
    } catch (error) {
        console.error('Registration error:', error);
        // For demo purposes
        handleDemoRegistration(formData);
    }
});

// Handle Demo Registration
function handleDemoRegistration(formData) {
    const mockUser = {
        id: Date.now(),
        name: `${formData.firstName} ${formData.lastName}`,
        email: formData.email,
        type: formData.accountType,
        businessName: formData.businessName
    };
    
    // Store in localStorage
    localStorage.setItem('auth_token', 'mock_token_' + Date.now());
    localStorage.setItem('user_data', JSON.stringify(mockUser));
    
    // Show success modal
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    successModal.show();
    
    setTimeout(() => {
        window.location.href = 'dashboard.html';
    }, 2000);
}

// Social Login Functions
async function loginWithGoogle() {
    // Load Google Sign-In API
    if (typeof gapi === 'undefined') {
        loadGoogleAPI();
        return;
    }
    
    gapi.auth2.getAuthInstance().signIn().then(function(googleUser) {
        const profile = googleUser.getBasicProfile();
        handleSocialLogin({
            provider: 'google',
            id: profile.getId(),
            name: profile.getName(),
            email: profile.getEmail(),
            image: profile.getImageUrl()
        });
    });
}

async function loginWithFacebook() {
    // Facebook SDK should be loaded
    if (typeof FB === 'undefined') {
        loadFacebookSDK();
        return;
    }
    
    FB.login(function(response) {
        if (response.authResponse) {
            FB.api('/me', {fields: 'name,email,picture'}, function(response) {
                handleSocialLogin({
                    provider: 'facebook',
                    id: response.id,
                    name: response.name,
                    email: response.email,
                    image: response.picture.data.url
                });
            });
        }
    }, {scope: 'public_profile,email'});
}

// Handle Social Login
async function handleSocialLogin(socialData) {
    try {
        const response = await fetch(`${API_URL}/auth/social-login.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(socialData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('user_data', JSON.stringify(data.user));
            window.location.href = 'dashboard.html';
        } else {
            showError(data.message);
        }
    } catch (error) {
        console.error('Social login error:', error);
        // Demo fallback
        handleDemoSocialLogin(socialData);
    }
}

// Demo Social Login
function handleDemoSocialLogin(socialData) {
    const mockUser = {
        id: Date.now(),
        name: socialData.name || 'Demo User',
        email: socialData.email || 'demo@festalaurea.eu',
        image: socialData.image,
        provider: socialData.provider,
        type: 'student'
    };
    
    localStorage.setItem('auth_token', 'social_token_' + Date.now());
    localStorage.setItem('user_data', JSON.stringify(mockUser));
    
    showSuccess('Login effettuato con successo!');
    setTimeout(() => {
        window.location.href = 'dashboard.html';
    }, 1500);
}

// Load Google API
function loadGoogleAPI() {
    const script = document.createElement('script');
    script.src = 'https://apis.google.com/js/platform.js';
    script.async = true;
    script.defer = true;
    script.onload = initGoogleAuth;
    document.head.appendChild(script);
}

// Initialize Google Auth
function initGoogleAuth() {
    gapi.load('auth2', function() {
        gapi.auth2.init({
            client_id: 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com'
        });
    });
}

// Load Facebook SDK
function loadFacebookSDK() {
    window.fbAsyncInit = function() {
        FB.init({
            appId: 'YOUR_FACEBOOK_APP_ID',
            cookie: true,
            xfbml: true,
            version: 'v12.0'
        });
    };
    
    const script = document.createElement('script');
    script.src = 'https://connect.facebook.net/it_IT/sdk.js';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
}

// Password Strength Checker
document.getElementById('password')?.addEventListener('input', function(e) {
    const password = e.target.value;
    const strength = checkPasswordStrength(password);
    updatePasswordStrengthIndicator(strength);
});

// Check Password Strength
function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    return strength;
}

// Update Password Strength Indicator
function updatePasswordStrengthIndicator(strength) {
    const indicator = document.getElementById('passwordStrength');
    if (!indicator) return;
    
    const strengths = ['Molto Debole', 'Debole', 'Media', 'Forte', 'Molto Forte'];
    const colors = ['danger', 'warning', 'info', 'success', 'success'];
    
    const level = Math.min(Math.floor(strength / 1.5), 4);
    
    indicator.innerHTML = `
        <div class="progress mt-2" style="height: 5px;">
            <div class="progress-bar bg-${colors[level]}" style="width: ${(level + 1) * 20}%"></div>
        </div>
        <small class="text-${colors[level]}">${strengths[level]}</small>
    `;
}

// Show Error Message
function showError(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show mt-3';
    alertDiv.innerHTML = `
        <i class="bi bi-exclamation-triangle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const form = document.querySelector('form');
    form.insertAdjacentElement('beforebegin', alertDiv);
    
    setTimeout(() => alertDiv.remove(), 5000);
}

// Show Success Message
function showSuccess(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show mt-3';
    alertDiv.innerHTML = `
        <i class="bi bi-check-circle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const form = document.querySelector('form');
    form.insertAdjacentElement('beforebegin', alertDiv);
    
    setTimeout(() => alertDiv.remove(), 5000);
}

// Toggle Business Fields
window.toggleBusinessFields = function() {
    const accountType = document.getElementById('accountType').value;
    const businessFields = document.getElementById('businessFields');
    
    if (accountType === 'venue') {
        businessFields.style.display = 'block';
        document.getElementById('businessName').required = true;
        document.getElementById('vatNumber').required = true;
    } else {
        businessFields.style.display = 'none';
        document.getElementById('businessName').required = false;
        document.getElementById('vatNumber').required = false;
    }
}

// Export functions
window.loginWithGoogle = loginWithGoogle;
window.loginWithFacebook = loginWithFacebook;
window.registerWithGoogle = loginWithGoogle;
window.registerWithFacebook = loginWithFacebook;
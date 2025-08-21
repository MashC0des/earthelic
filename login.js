document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const showRegister = document.getElementById('show-register');
    const showLogin = document.getElementById('show-login');
    const logo = document.getElementById('logo');
    
    showRegister.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Fade out logo
        logo.classList.add('fade-out');
        
        setTimeout(() => {
            // Animate forms
            loginForm.classList.remove('active');
            registerForm.classList.add('active');
            
            // Fade in logo
            setTimeout(() => {
                logo.classList.remove('fade-out');
            }, 300);
        }, 250); // Half of the transition time
    });
    
    showLogin.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Fade out logo
        logo.classList.add('fade-out');
        
        setTimeout(() => {
            // Animate forms
            registerForm.classList.remove('active');
            loginForm.classList.add('active');
            
            // Fade in logo
            setTimeout(() => {
                logo.classList.remove('fade-out');
            }, 300);
        }, 250); // Half of the transition time
    });
});
document.addEventListener('DOMContentLoaded', function() {
    // Animation code remains the same from previous version
    
    // Form Validation
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    // Login Form Validation
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            let isValid = true;
            
            // Validate Email
            const email = document.getElementById('loginEmail');
            const emailError = document.getElementById('loginEmailError');
            if (!validateEmail(email.value)) {
                emailError.textContent = 'Please enter a valid email address';
                email.parentElement.classList.add('invalid');
                isValid = false;
            } else {
                emailError.textContent = '';
                email.parentElement.classList.remove('invalid');
            }
            
            // Validate Password
            const password = document.getElementById('loginPassword');
            const passwordError = document.getElementById('loginPasswordError');
            if (password.value.length === 0) {
                passwordError.textContent = 'Password is required';
                password.parentElement.classList.add('invalid');
                isValid = false;
            } else {
                passwordError.textContent = '';
                password.parentElement.classList.remove('invalid');
            }
            
            if (isValid) {
                // Submit the form
                window.location.href = "home.html"; // Redirect to home page
                // loginForm.submit();
            }
        });
    }
    
    // Registration Form Validation
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            let isValid = true;
            
            // Validate Full Name
            const fullName = document.getElementById('fullName');
            const fullNameError = document.getElementById('fullNameError');
            if (fullName.value.trim().length === 0) {
                fullNameError.textContent = 'Full name is required';
                fullName.parentElement.classList.add('invalid');
                isValid = false;
            } else if (fullName.value.length > 20) {
                fullNameError.textContent = 'Name must be 20 characters or less';
                fullName.parentElement.classList.add('invalid');
                isValid = false;
            } else {
                fullNameError.textContent = '';
                fullName.parentElement.classList.remove('invalid');
            }
            
            // Validate Email
            const email = document.getElementById('email');
            const emailError = document.getElementById('emailError');
            if (!validateEmail(email.value)) {
                emailError.textContent = 'Please enter a valid email address';
                email.parentElement.classList.add('invalid');
                isValid = false;
            } else {
                emailError.textContent = '';
                email.parentElement.classList.remove('invalid');
            }
            
            // Validate Password
            const password = document.getElementById('password');
            const passwordError = document.getElementById('passwordError');
            const passwordValidation = validatePassword(password.value);
            if (!passwordValidation.isValid) {
                passwordError.textContent = passwordValidation.message;
                password.parentElement.classList.add('invalid');
                isValid = false;
            } else {
                passwordError.textContent = '';
                password.parentElement.classList.remove('invalid');
            }
            
            // Validate Confirm Password
            const confirmPassword = document.getElementById('confirmPassword');
            const confirmPasswordError = document.getElementById('confirmPasswordError');
            if (password.value !== confirmPassword.value) {
                confirmPasswordError.textContent = 'Passwords do not match';
                confirmPassword.parentElement.classList.add('invalid');
                isValid = false;
            } else if (confirmPassword.value.length === 0) {
                confirmPasswordError.textContent = 'Please confirm your password';
                confirmPassword.parentElement.classList.add('invalid');
                isValid = false;
            } else {
                confirmPasswordError.textContent = '';
                confirmPassword.parentElement.classList.remove('invalid');
            }
            
            if (isValid) {
                // Submit the form
                 window.location.href = "home.html";;
                // registerForm.submit();
            }
        });
    }
    
    // Helper Functions
    function validateEmail(email) {
        const re = /^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d@.]{1,30}$/ && /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function validatePassword(password) {
        if (password.length < 8 || password.length > 12) {
            return {
                isValid: false,
                message: 'Password must be 8-12 characters long'
            };
        }
        
        if (!/[A-Z]/.test(password)) {
            return {
                isValid: false,
                message: 'Must contain at least one uppercase letter'
            };
        }
        
        if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            return {
                isValid: false,
                message: 'Must contain at least one special character'
            };
        }
        
        return {
            isValid: true,
            message: ''
        };
    }
    
    // Animation code remains the same from previous version
});
   // Password toggle functionality
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Form switching functionality
        document.getElementById('show-register')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('login-form').classList.remove('active');
            document.getElementById('register-form').classList.add('active');
        });

        document.getElementById('show-login')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('register-form').classList.remove('active');
            document.getElementById('login-form').classList.add('active');
        });
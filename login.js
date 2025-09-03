document.addEventListener('DOMContentLoaded', function() {
    const loginFormWrapper = document.getElementById('login-form');
    const registerFormWrapper = document.getElementById('register-form');
    const showRegister = document.getElementById('show-register');
    const showLogin = document.getElementById('show-login');
    const logo = document.getElementById('logo');

    // Form switching functionality
    if (showRegister) {
        showRegister.addEventListener('click', function(e) {
            e.preventDefault();
            // Fade out logo
            logo.classList.add('fade-out');
            setTimeout(() => {
                loginFormWrapper.classList.remove('active');
                registerFormWrapper.classList.add('active');
                setTimeout(() => {
                    logo.classList.remove('fade-out');
                }, 300);
            }, 250);
        });
    }

    if (showLogin) {
        showLogin.addEventListener('click', function(e) {
            e.preventDefault();
            // Fade out logo
            logo.classList.add('fade-out');
            setTimeout(() => {
                registerFormWrapper.classList.remove('active');
                loginFormWrapper.classList.add('active');
                setTimeout(() => {
                    logo.classList.remove('fade-out');
                }, 300);
            }, 250);
        });
    }

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

    // Form Validation
    const loginForm = document.querySelector('#login-form form');
    const registerForm = document.querySelector('#register-form form');
    
    // Login Form Validation
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            // Clear previous errors
            clearErrors(loginForm);

            let isValid = true;
            
            // Validate Email
            const email = loginForm.querySelector('input[name="loginEmail"]');
            const emailError = document.getElementById('loginEmailError');
            if (!validateEmail(email.value)) {
                emailError.textContent = 'Please enter a valid email address.';
                email.closest('.input-group').classList.add('invalid');
                isValid = false;
            }
            
            // Validate Password
            const password = loginForm.querySelector('input[name="loginPassword"]');
            const passwordError = document.getElementById('loginPasswordError');
            if (password.value.length === 0) {
                passwordError.textContent = 'Password is required.';
                password.closest('.input-group').classList.add('invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
    
    // Registration Form Validation
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            // Clear previous errors
            clearErrors(registerForm);

            let isValid = true;
            
            // Validate Full Name
            const fullName = registerForm.querySelector('input[name="fullName"]');
            const fullNameError = document.getElementById('fullNameError');
            if (fullName.value.trim().length === 0) {
                fullNameError.textContent = 'Full name is required.';
                fullName.closest('.input-group').classList.add('invalid');
                isValid = false;
            } else if (fullName.value.length > 20) {
                fullNameError.textContent = 'Name must be 20 characters or less.';
                fullName.closest('.input-group').classList.add('invalid');
                isValid = false;
            }
            
            // Validate Email
            const email = registerForm.querySelector('input[name="email"]');
            const emailError = document.getElementById('emailError');
            if (!validateEmail(email.value)) {
                emailError.textContent = 'Please enter a valid email address.';
                email.closest('.input-group').classList.add('invalid');
                isValid = false;
            }
            
            // Validate Password
            const password = registerForm.querySelector('input[name="password"]');
            const passwordError = document.getElementById('passwordError');
            const passwordValidation = validatePassword(password.value);
            if (!passwordValidation.isValid) {
                passwordError.textContent = passwordValidation.message;
                password.closest('.input-group').classList.add('invalid');
                isValid = false;
            }
            
            // Validate Confirm Password
            const confirmPassword = registerForm.querySelector('input[name="confirmPassword"]');
            const confirmPasswordError = document.getElementById('confirmPasswordError');
            if (password.value !== confirmPassword.value) {
                confirmPasswordError.textContent = 'Passwords do not match.';
                confirmPassword.closest('.input-group').classList.add('invalid');
                isValid = false;
            } else if (confirmPassword.value.length === 0) {
                confirmPasswordError.textContent = 'Please confirm your password.';
                confirmPassword.closest('.input-group').classList.add('invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }

    // Helper Functions
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function validatePassword(password) {
        if (password.length < 8 || password.length > 12) {
            return {
                isValid: false,
                message: 'Password must be 8-12 characters long.'
            };
        }
        
        if (!/[A-Z]/.test(password)) {
            return {
                isValid: false,
                message: 'Must contain at least one uppercase letter.'
            };
        }
        
        if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            return {
                isValid: false,
                message: 'Must contain at least one special character.'
            };
        }
        
        return {
            isValid: true,
            message: ''
        };
    }

    function clearErrors(form) {
        form.querySelectorAll('.invalid').forEach(el => el.classList.remove('invalid'));
        form.querySelectorAll('.error-message').forEach(el => el.textContent = '');
    }
});
// Hamburger menu toggle
function toggleMenu() {
    const nav = document.querySelector('.nav-links');
    const burger = document.querySelector('.hamburger');
    nav.classList.toggle('active');
    burger.classList.toggle('active');
}


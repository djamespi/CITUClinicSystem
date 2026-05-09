// js/auth.js

// Toggle between Login and Register
function switchForm(formType) {
    document.querySelectorAll('.form-wrapper').forEach(form => form.classList.remove('active'));
    document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));

    if (formType === 'login') {
        document.getElementById('loginForm').classList.add('active');
        document.querySelectorAll('.toggle-btn')[0].classList.add('active');
    } else {
        document.getElementById('registerForm').classList.add('active');
        document.querySelectorAll('.toggle-btn')[1].classList.add('active');
    }
}

// Show specific form fields based on the chosen schema role
function showRoleFields() {
    const role = document.getElementById('userTypeSelect').value;

    // Hide all dynamic fields first
    document.querySelectorAll('.role-fields').forEach(field => field.classList.remove('active'));

    // Show the correct one
    if (role === 'Patient') document.getElementById('patientFields').classList.add('active');
    if (role === 'Admin') document.getElementById('adminFields').classList.add('active');
    if (role === 'Provider') document.getElementById('providerFields').classList.add('active');
}
// --- POP-UP TOAST LOGIC ---

// Function to close the toast when 'X' is clicked
function closeToast() {
    const toast = document.getElementById('toastBox');
    if (toast) {
        // Play the slide-out animation
        toast.style.animation = 'fadeOutRight 0.4s ease-out forwards';
        // Remove it from the page after animation finishes
        setTimeout(() => { toast.remove(); }, 400);
    }
}

// Automatically close the pop-up after 5 seconds
document.addEventListener('DOMContentLoaded', () => {
    const toast = document.getElementById('toastBox');
    if (toast) {
        setTimeout(closeToast, 5000); // 5000 milliseconds = 5 seconds
    }
});
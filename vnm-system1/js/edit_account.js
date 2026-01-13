// edit_account.js
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('editForm');

    form.addEventListener('submit', (e) => {
        const password = form.querySelector('input[name="password"]').value;
        
        // Confirmation dialog
        const confirmUpdate = confirm("Are you sure you want to update your profile details?");
        
        if (!confirmUpdate) {
            e.preventDefault();
            return;
        }

        // Basic validation for password length if they typed something
        if (password.length > 0 && password.length < 6) {
            alert("New password must be at least 6 characters long.");
            e.preventDefault();
        }
    });

    // Optional: Add a "change" listener to highlight modified fields
    const inputs = form.querySelectorAll('input, textarea');
    inputs.forEach(input => {
        input.addEventListener('change', () => {
            input.style.borderColor = 'var(--accent-color)';
        });
    });
});
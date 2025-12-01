function openDetailsModal(fullName, description, dailyRate, imagesJson) {
    const allImages = JSON.parse(imagesJson);
    // Update modal text
    document.getElementById('modalCarName').textContent = fullName;
    document.getElementById('modalDailyRate').textContent = `Daily Rate: ₱${dailyRate}`;
    document.getElementById('modalCarDescription').innerHTML = description;

    // Update images
    const imgIds = ['img1', 'img2', 'img3', 'img4'];
    for (let i = 0; i < imgIds.length; i++) {
        const imgElement = document.getElementById(imgIds[i]);
        if (allImages[i]) {
            imgElement.src = allImages[i];
            imgElement.alt = fullName + ' Image ' + (i + 1);
            imgElement.style.display = 'block';
        } else {
            imgElement.src = '';
            imgElement.alt = '';
            imgElement.style.display = 'none';
        }
    }

    // <-- Update Rent button dynamically
    const rentButton = document.querySelector('#view-details button a');
    rentButton.href = `../php/rent_form.php?car_id=${carId}`;
}

function handleLogout(answer) {
    const logoutPopover = document.getElementById('logout');
    
    if (answer === 'yes') {
        logoutPopover.hidePopover();
        window.location.href = '../php/landing.php'; 
    } else if (answer === 'no') {
        logoutPopover.hidePopover();
    }
}

window.openDetailsModal = openDetailsModal;
window.handleLogout = handleLogout;
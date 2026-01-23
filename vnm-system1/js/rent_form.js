// Define calculateReturnDate globally so flatpickr can access it
window.calculateReturnDate = function() {
    const pickupInput = document.getElementById('pickup');
    const durationInput = document.getElementById('duration');
    const estimatedReturnInput = document.getElementById('estimated_return');
    
    if (!pickupInput || !durationInput || !estimatedReturnInput) {
        console.log('Missing elements:', {pickupInput, durationInput, estimatedReturnInput});
        return;
    }

    const pickupDate = pickupInput.value;
    const duration = parseInt(durationInput.value) || 1;

    if (!pickupDate) {
        estimatedReturnInput.value = '';
        return;
    }

    // Calculate return date (pickup date + duration)
    const pickup = new Date(pickupDate);
    const returnDate = new Date(pickup);
    returnDate.setDate(pickup.getDate() + duration);

    // Format the date nicely
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    estimatedReturnInput.value = returnDate.toLocaleDateString('en-US', options);
    console.log('Calculated return date:', estimatedReturnInput.value);
};

// Wait for DOM before accessing elements
if (document.getElementById('car_id_input')) {
    document.getElementById('car_id_input').value = CAR_ID;
}
document.addEventListener('DOMContentLoaded', function() 
{
    if (typeof DAILY_RATE === 'undefined' || typeof CAR_ID === 'undefined' || typeof CAR_MODEL === 'undefined') {
        console.error("Required PHP variables (DAILY_RATE, CAR_ID, CAR_MODEL) were not initialized.");
        return;
    }

    const dailyRate = parseFloat(DAILY_RATE);
    const carId = CAR_ID;
    const carModel = CAR_MODEL;
    
    const form = document.querySelector('#rent-details form');
    const modelInput = document.querySelector('input[name="car_model"]');
    const durationInput = document.getElementById('duration'); 
    const priceInput = document.getElementById('price');
    
    if (modelInput) {
        modelInput.value = carModel;
    }

    if (form && !document.querySelector('input[name="car_id"]')) {
        const hiddenCarIdInput = document.createElement('input');
        hiddenCarIdInput.type = 'hidden';
        hiddenCarIdInput.name = 'car_id';
        hiddenCarIdInput.value = carId;
        form.appendChild(hiddenCarIdInput);
    }

    function calculatePrice() {
        if (!durationInput || !priceInput) return;

        let duration = parseInt(durationInput.value);

        if (isNaN(duration) || duration < 1) {
            duration = 1;
            durationInput.value = 1; 
        }

        const total = (dailyRate * duration).toFixed(2);
        priceInput.value = total;
    }

    if (durationInput && priceInput) {
        durationInput.value = 1;
        calculatePrice();
        
        durationInput.addEventListener('input', function() {
            calculatePrice();
            window.calculateReturnDate();
        });
    }
});
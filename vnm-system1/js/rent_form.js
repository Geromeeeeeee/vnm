
document.getElementById('car_id_input').value = CAR_ID;
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
        
        durationInput.addEventListener('input', calculatePrice);
    }
});
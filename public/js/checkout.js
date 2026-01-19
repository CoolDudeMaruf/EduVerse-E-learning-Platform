// Checkout Page JavaScript

// Terms agreement checkbox
const agreeTerms = document.getElementById('agreeTerms');
const completeOrderBtn = document.getElementById('completeOrderBtn');

if (agreeTerms && completeOrderBtn) {
    agreeTerms.addEventListener('change', function() {
        completeOrderBtn.disabled = !this.checked;
    });
}

// Coupon toggle
const couponToggle = document.getElementById('couponToggle');
const couponForm = document.getElementById('couponForm');

if (couponToggle && couponForm) {
    couponToggle.addEventListener('click', function() {
        if (couponForm.style.display === 'none' || couponForm.style.display === '') {
            couponForm.style.display = 'flex';
            couponToggle.innerHTML = '<i class="fas fa-tag"></i> Hide coupon';
        } else {
            couponForm.style.display = 'none';
            couponToggle.innerHTML = '<i class="fas fa-tag"></i> Have a coupon code?';
        }
    });
}

// Apply coupon
const applyCoupon = document.getElementById('applyCoupon');
const couponCode = document.getElementById('couponCode');
const couponMessage = document.getElementById('couponMessage');
const discountRow = document.getElementById('discountRow');
const discountAmount = document.getElementById('discountAmount');
const totalAmountEl = document.getElementById('totalAmount');

if (applyCoupon && couponCode) {
    applyCoupon.addEventListener('click', async function() {
        const code = couponCode.value.trim();
        
        if (!code) {
            showCouponMessage('Please enter a coupon code', 'error');
            return;
        }
        
        applyCoupon.disabled = true;
        applyCoupon.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
        
        try {
            const formData = new FormData();
            formData.append('code', code);
            formData.append('total', finalAmount || totalAmount);
            
            const response = await fetch(baseUrl + 'ajax/apply_coupon.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                const discount = parseFloat(data.discount);
                const newTotal = (finalAmount || totalAmount) - discount;
                
                discountAmount.textContent = discount.toFixed(2);
                discountRow.style.display = 'flex';
                totalAmountEl.textContent = currency + ' ' + newTotal.toFixed(2);
                
                showCouponMessage(data.message || 'Coupon applied successfully!', 'success');
                couponCode.disabled = true;
                applyCoupon.style.display = 'none';
                
                // Update global variables
                window.finalAmount = newTotal;
                window.discountAmount = discount;
                
                // Reload page after 1 second to reflect server-side changes
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showCouponMessage(data.message || 'Invalid coupon code', 'error');
            }
        } catch (error) {
            showCouponMessage('Failed to apply coupon. Please try again.', 'error');
        } finally {
            applyCoupon.disabled = false;
            applyCoupon.innerHTML = 'Apply';
        }
    });
}

function showCouponMessage(message, type) {
    if (couponMessage) {
        couponMessage.textContent = message;
        couponMessage.className = type;
        setTimeout(() => {
            if (type === 'error') {
                couponMessage.style.display = 'none';
            }
        }, 5000);
    }
}

// Card number formatting
const cardNumber = document.getElementById('cardNumber');
if (cardNumber) {
    cardNumber.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\s/g, '');
        let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
        e.target.value = formattedValue;
    });
}

// Expiry date formatting
const expiryDate = document.getElementById('expiryDate');
if (expiryDate) {
    expiryDate.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        e.target.value = value;
    });
}

// CVV validation
const cvv = document.getElementById('cvv');
if (cvv) {
    cvv.addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
    });
}

// Complete order
if (completeOrderBtn) {
    completeOrderBtn.addEventListener('click', async function() {
        if (!agreeTerms.checked) {
            alert('Please agree to the Terms of Service and Privacy Policy');
            return;
        }
        
        // Get current final amount
        const currentFinalAmount = window.finalAmount || finalAmount || totalAmount;
        
        // Validate payment information if total > 0
        if (currentFinalAmount > 0) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
            
            if (!paymentMethod) {
                alert('Please select a payment method');
                return;
            }
            
            if (paymentMethod === 'card') {
                if (!validateCardForm()) {
                    return;
                }
            }
            
            if (!validateBillingForm()) {
                return;
            }
        }
        
        // Show processing modal
        document.getElementById('processingModal').style.display = 'flex';
        
        try {
            const formData = new FormData();
            formData.append('payment_method', document.querySelector('input[name="payment_method"]:checked')?.value || 'free');
            formData.append('total_amount', window.finalAmount || finalAmount || totalAmount);
            formData.append('discount_amount', window.discountAmount || initialDiscountAmount || 0);
            
            // Add billing info
            formData.append('first_name', document.getElementById('firstName')?.value || '');
            formData.append('last_name', document.getElementById('lastName')?.value || '');
            formData.append('email', document.getElementById('email')?.value || '');
            formData.append('address', document.getElementById('address')?.value || '');
            formData.append('city', document.getElementById('city')?.value || '');
            formData.append('zip_code', document.getElementById('zipCode')?.value || '');
            formData.append('country', document.getElementById('country')?.value || '');
            
            // Add card info if applicable
            const currentFinalAmount = window.finalAmount || finalAmount || totalAmount;
            if (currentFinalAmount > 0 && formData.get('payment_method') === 'card') {
                formData.append('card_number', document.getElementById('cardNumber')?.value || '');
                formData.append('card_name', document.getElementById('cardName')?.value || '');
                formData.append('expiry_date', document.getElementById('expiryDate')?.value || '');
                formData.append('cvv', document.getElementById('cvv')?.value || '');
            }
            
            const response = await fetch(baseUrl + 'ajax/process_checkout.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            document.getElementById('processingModal').style.display = 'none';
            
            if (data.success) {
                document.getElementById('successModal').style.display = 'flex';
                
                // Redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = baseUrl + 'dashboard';
                }, 3000);
            } else {
                alert(data.message || 'Payment failed. Please try again.');
            }
        } catch (error) {
            document.getElementById('processingModal').style.display = 'none';
            alert('An error occurred. Please try again.');
        }
    });
}

function validateCardForm() {
    const cardNum = document.getElementById('cardNumber')?.value.replace(/\s/g, '');
    const cardName = document.getElementById('cardName')?.value;
    const expiry = document.getElementById('expiryDate')?.value;
    const cvvValue = document.getElementById('cvv')?.value;
    
    if (!cardNum || cardNum.length < 13) {
        alert('Please enter a valid card number');
        return false;
    }
    
    if (!cardName || cardName.trim().length < 3) {
        alert('Please enter cardholder name');
        return false;
    }
    
    if (!expiry || expiry.length < 5) {
        alert('Please enter expiry date (MM/YY)');
        return false;
    }
    
    if (!cvvValue || cvvValue.length < 3) {
        alert('Please enter CVV');
        return false;
    }
    
    return true;
}

function validateBillingForm() {
    const firstName = document.getElementById('firstName')?.value;
    const lastName = document.getElementById('lastName')?.value;
    const email = document.getElementById('email')?.value;
    const country = document.getElementById('country')?.value;
    
    if (!firstName || !lastName) {
        alert('Please enter your full name');
        return false;
    }
    
    if (!email || !email.includes('@')) {
        alert('Please enter a valid email address');
        return false;
    }
    
    if (!country) {
        alert('Please select your country');
        return false;
    }
    
    return true;
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
});

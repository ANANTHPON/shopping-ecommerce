<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout - VayaShop</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: 'Poppins', sans-serif; }
        
        .navbar-brand { font-weight: 800; color: #ff4757 !important; font-size: 26px; letter-spacing: -0.5px; }
        .checkout-header { background: #fff; padding: 20px 0; border-bottom: 1px solid #eaeaea; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        
        /* Layout Cards */
        .card-custom { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); background: #fff; padding: 30px; margin-bottom: 25px; }
        .section-title { font-weight: 700; color: #2f3542; margin-bottom: 25px; font-size: 1.25rem; display: flex; align-items: center; }
        .section-title i { color: #ff4757; margin-right: 12px; font-size: 1.4rem; }
        
        /* Form Styling */
        .form-floating > .form-control { border-radius: 12px; border: 1.5px solid #e2e8f0; background-color: #fdfdfd; }
        .form-floating > .form-control:focus { border-color: #ff4757; box-shadow: 0 0 0 0.25rem rgba(255, 71, 87, 0.15); background-color: #fff; }
        .form-floating > label { color: #a0aec0; font-weight: 500; }
        
        /* Payment Method Radio */
        .payment-option { border: 2px solid #e2e8f0; border-radius: 15px; padding: 20px; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; }
        .payment-option:hover { border-color: #ff4757; background: #fff0f1; }
        .payment-radio:checked + .payment-option { border-color: #ff4757; background: #fff0f1; }
        .payment-radio { display: none; }
        .payment-icon { font-size: 2rem; color: #2f3542; margin-right: 15px; }
        
        /* Order Summary */
        .summary-card { background: linear-gradient(180deg, #ffffff 0%, #fcfcfc 100%); }
        .summary-item { display: flex; align-items: center; margin-bottom: 20px; }
        .summary-img { width: 65px; height: 65px; border-radius: 12px; object-fit: cover; margin-right: 15px; border: 1px solid #f1f2f6; }
        .summary-title { font-weight: 600; font-size: 0.95rem; color: #2f3542; margin-bottom: 2px; }
        .summary-price { font-weight: 700; color: #ff4757; }
        
        .totals-row { display: flex; justify-content: space-between; margin-bottom: 15px; color: #57606f; }
        .totals-row.final { font-size: 1.4rem; font-weight: 800; color: #2f3542; border-top: 2px dashed #e2e8f0; padding-top: 20px; margin-top: 10px; }
        
        .btn-pay { background-color: #ff4757; border: none; border-radius: 12px; padding: 16px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 8px 20px rgba(255, 71, 87, 0.3); transition: all 0.3s ease; }
        .btn-pay:hover { background-color: #ff6b81; transform: translateY(-2px); box-shadow: 0 10px 25px rgba(255, 71, 87, 0.4); }
        
        /* Empty State */
        #emptyCheckout { display: none; text-align: center; padding: 100px 20px; }
    </style>
</head>
<body>

<!-- Minimal Header -->
<header class="checkout-header">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand text-decoration-none" href="index.php"><i class="fa-solid fa-store me-2"></i>VayaShop</a>
        <div class="text-muted fw-bold"><i class="fa-solid fa-lock me-2 text-success"></i>Secure Checkout</div>
    </div>
</header>

<!-- Empty Cart Warning (Hidden by default) -->
<div id="emptyCheckout">
    <i class="fa-solid fa-cart-arrow-down fa-4x text-muted mb-4 opacity-50"></i>
    <h2 class="fw-bold text-dark">Your cart is empty!</h2>
    <p class="text-muted mb-4">You can't checkout without any items.</p>
    <a href="index.php" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow-sm" style="background-color: #ff4757; border:none;">Return to Shop</a>
</div>

<!-- Main Checkout Form -->
<div class="container py-5" id="checkoutContent">
    <div class="row g-5">
        
        <!-- LEFT COLUMN: Forms -->
        <div class="col-lg-7">
            
            <!-- Shipping Information -->
            <div class="card-custom">
                <h3 class="section-title"><i class="fa-solid fa-truck-fast"></i> Shipping Details</h3>
                <form>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="fName" placeholder="First Name" required>
                                <label for="fName">First Name</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="lName" placeholder="Last Name" required>
                                <label for="lName">Last Name</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating">
                                <input type="email" class="form-control" id="email" placeholder="Email Address" required>
                                <label for="email">Email Address</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="address" placeholder="Address" required>
                                <label for="address">Full Address</label>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="city" placeholder="City" required>
                                <label for="city">City</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="state" required>
                                    <option value="" selected disabled>Select State...</option>
                                    <option>California</option><option>New York</option><option>Texas</option><option>Florida</option>
                                </select>
                                <label for="state">State</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="zip" placeholder="Zip" required>
                                <label for="zip">Zip Code</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Payment Method -->
            <div class="card-custom mt-4">
                <h3 class="section-title"><i class="fa-solid fa-credit-card"></i> Payment Method</h3>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="w-100">
                            <input type="radio" name="payment" class="payment-radio" checked>
                            <div class="payment-option">
                                <i class="fa-brands fa-cc-visa payment-icon" style="color: #1a1f71;"></i>
                                <div>
                                    <h6 class="fw-bold mb-0">Credit Card</h6>
                                    <small class="text-muted">Visa, Mastercard, Amex</small>
                                </div>
                            </div>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="w-100">
                            <input type="radio" name="payment" class="payment-radio">
                            <div class="payment-option">
                                <i class="fa-brands fa-paypal payment-icon" style="color: #00457C;"></i>
                                <div>
                                    <h6 class="fw-bold mb-0">PayPal</h6>
                                    <small class="text-muted">Fast & Secure</small>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Credit Card Form -->
                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="cardName" placeholder="Name on Card" required>
                            <label for="cardName">Name on Card</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="cardNum" placeholder="Card Number" maxlength="19" required>
                            <label for="cardNum">Card Number</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="expDate" placeholder="MM/YY" maxlength="5" required>
                            <label for="expDate">Expiry Date (MM/YY)</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-floating">
                            <input type="password" class="form-control" id="cvv" placeholder="CVV" maxlength="3" required>
                            <label for="cvv">CVV Code</label>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- RIGHT COLUMN: Order Summary -->
        <div class="col-lg-5">
            <div class="card-custom summary-card sticky-top" style="top: 100px;">
                <h3 class="section-title"><i class="fa-solid fa-bag-shopping"></i> Order Summary</h3>
                
                <!-- Items Injected Here by JS -->
                <div id="checkoutItemsContainer" class="mb-4" style="max-height: 350px; overflow-y: auto; padding-right:10px;">
                    <!-- Loading... -->
                </div>
                
                <div class="totals-row">
                    <span>Subtotal</span>
                    <span id="checkSubtotal" class="fw-bold text-dark">$0.00</span>
                </div>
                <div class="totals-row">
                    <span>Shipping</span>
                    <span class="text-success fw-bold">Free</span>
                </div>
                <div class="totals-row">
                    <span>Tax (8%)</span>
                    <span id="checkTax" class="fw-bold text-dark">$0.00</span>
                </div>
                
                <div class="totals-row final">
                    <span>Total</span>
                    <span id="checkTotal" style="color:#ff4757;">$0.00</span>
                </div>
                
                <button class="btn btn-primary w-100 btn-pay mt-3" onclick="placeOrder()">
                    <i class="fa-solid fa-lock me-2"></i> Pay <span id="btnTotal">$0.00</span>
                </button>
                <div class="text-center mt-3 text-muted small">
                    <i class="fa-solid fa-shield-halved me-1"></i> Payments are 256-bit encrypted & secure.
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
    // Fetch cart from LocalStorage
    let cart = JSON.parse(localStorage.getItem('vayaCart')) || [];
    
    document.addEventListener("DOMContentLoaded", () => {
        if(cart.length === 0) {
            document.getElementById('checkoutContent').style.display = 'none';
            document.getElementById('emptyCheckout').style.display = 'block';
            return;
        }
        
        const container = document.getElementById('checkoutItemsContainer');
        let subtotal = 0;
        
        cart.forEach(item => {
            subtotal += (item.price * item.qty);
            container.innerHTML += `
                <div class="summary-item">
                    <img src="${item.img}" class="summary-img">
                    <div class="flex-grow-1">
                        <div class="summary-title">${item.name}</div>
                        <div class="text-muted small">Qty: ${item.qty}</div>
                    </div>
                    <div class="summary-price">$${(item.price * item.qty).toFixed(2)}</div>
                </div>
            `;
        });
        
        const tax = subtotal * 0.08;
        const total = subtotal + tax;
        
        document.getElementById('checkSubtotal').innerText = '$' + subtotal.toFixed(2);
        document.getElementById('checkTax').innerText = '$' + tax.toFixed(2);
        document.getElementById('checkTotal').innerText = '$' + total.toFixed(2);
        document.getElementById('btnTotal').innerText = '$' + total.toFixed(2);
    });

    function placeOrder() {
        const btn = document.querySelector('.btn-pay');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Processing...';
        
        setTimeout(() => {
            // Empty the cart on successful checkout
            localStorage.removeItem('vayaCart');
            
            // Show success alert
            alert("Success! Your order has been placed. Thank you for shopping with VayaShop!");
            
            // Redirect back to home
            window.location.href = 'index.php';
        }, 1500);
    }
</script>

</body>
</html>

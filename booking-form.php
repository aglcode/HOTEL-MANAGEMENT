<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'database.php';
require_once 'email-config.php';

// Set timezone
date_default_timezone_set('Asia/Manila');
$currentDateTime = date("Y-m-d\TH:i");
$currentDisplay = date("F j, Y h:i A");

// Function to send booking email
if (!function_exists('sendBookingEmail')) {
    function sendBookingEmail($email, $guest_name, $booking_token, $booking_details) {
        // Email implementation here
        return true;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_booking'])) {
    $guest_name = htmlspecialchars(trim($_POST['guest_name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $telephone = htmlspecialchars(trim($_POST['telephone']));
    $address = htmlspecialchars(trim($_POST['address']));
    $age = (int)($_POST['age']);
    $num_people = (int)($_POST['num_people']);
    $room_number = (int)($_POST['room_number']);
    $duration = (int)($_POST['duration']);
    $start_date = $_POST['start_date'];
    $payment_mode = $_POST['payment_mode'];
    $amount_paid = (float)($_POST['amount_paid']);
    $reference_number = isset($_POST['reference_number']) ? htmlspecialchars(trim($_POST['reference_number'])) : '';
    
    // Validate check-in date is not in the past
    $start_datetime = new DateTime($start_date);
    $current_datetime = new DateTime();
    
    if ($start_datetime <= $current_datetime) {
        $error_message = "Check-in date and time cannot be in the past.";
    } else {
        // Calculate end date
        $end_datetime = clone $start_datetime;
        $end_datetime->add(new DateInterval('PT' . $duration . 'H'));
        $end_date = $end_datetime->format('Y-m-d H:i:s');
        
        // Get room details and calculate total price
        $room_stmt = $conn->prepare("SELECT * FROM rooms WHERE room_number = ? AND status = 'available'");
        $room_stmt->bind_param("i", $room_number);
        $room_stmt->execute();
        $room_result = $room_stmt->get_result();
        $room = $room_result->fetch_assoc();
        
        if ($room) {
            // Calculate total price based on duration
            if ($duration >= 48) {
                // For 2+ days, use 24hr rate multiplied by days
                $days = ceil($duration / 24);
                $total_price = $room['price_24hrs'] * $days;
            } else {
                // Standard hourly rates
                switch($duration) {
                    case 3:
                        $total_price = $room['price_3hrs'];
                        break;
                    case 6:
                        $total_price = $room['price_6hrs'];
                        break;
                    case 12:
                        $total_price = $room['price_12hrs'];
                        break;
                    case 24:
                        $total_price = $room['price_24hrs'];
                        break;
                    default:
                        $total_price = 0;
                }
            }
            
            // Check for conflicts
            $conflict_stmt = $conn->prepare("SELECT id FROM bookings WHERE room_number = ? AND status IN ('upcoming', 'active') AND NOT (end_date <= ? OR start_date >= ?)");
            $conflict_stmt->bind_param("iss", $room_number, $start_date, $end_date);
            $conflict_stmt->execute();
            $conflict_result = $conflict_stmt->get_result();
            
            if ($conflict_result->num_rows > 0) {
                $error_message = "Room is not available for the selected time period. Please choose a different time or room.";
            } else {
                // Generate booking token
                $booking_token = generateBookingToken();
                
                // Calculate change
                $change_amount = $amount_paid - $total_price;
                
                // Insert booking
                $insert_stmt = $conn->prepare("INSERT INTO bookings (guest_name, email, telephone, address, age, num_people, room_number, duration, start_date, end_date, total_price, payment_mode, amount_paid, change_amount, reference_number, booking_token, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming')");
                $insert_stmt->bind_param("ssssiiisssdsddss", $guest_name, $email, $telephone, $address, $age, $num_people, $room_number, $duration, $start_date, $end_date, $total_price, $payment_mode, $amount_paid, $change_amount, $reference_number, $booking_token);
                
                if ($insert_stmt->execute()) {
                    // Send email if email is provided
                    if (!empty($email)) {
                        $booking_details = [
                            'room' => $room_number,
                            'duration' => $duration,
                            'start_date' => $start_date,
                            'total_price' => $total_price
                        ];
                        sendBookingEmail($email, $guest_name, $booking_token, $booking_details);
                    }
                    
                    $_SESSION['success_msg'] = 'Booking created successfully! Token: ' . $booking_token;
                    echo "<script>window.location.reload();</script>";
                    exit();
                } else {
                    $error_message = "Error creating booking: " . $conn->error;
                }
            }
        } else {
            $error_message = "Invalid room selected or room is not available.";
        }
    }
}

// Fetch available rooms with pricing
$rooms_query = "SELECT * FROM rooms WHERE status = 'available' ORDER BY room_number";
$rooms_result = $conn->query($rooms_query);
?>

<!-- Add Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="bookingModalLabel">
                    <i class="fas fa-calendar-plus me-2"></i>Create New Booking
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="container-fluid py-4">
                    <div class="max-w-5xl mx-auto">
                        <?php if (isset($_SESSION['success_msg'])): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post" id="bookingForm" onsubmit="return validateBookingForm();">
                            <input type="hidden" name="create_booking" value="1">
                            
                            <!-- Booking Information Section -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card shadow-lg border-0 rounded-3">
                                        <div class="card-header bg-primary text-white py-3">
                                            <h5 class="mb-0 fw-semibold"><i class="fas fa-calendar-alt me-2"></i>Booking Information</h5>
                                        </div>
                                        <div class="card-body p-4">
                                            <div class="row g-4">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-medium text-dark">Check-in Date & Time *</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light border-end-0">
                                                            <i class="fas fa-calendar text-primary"></i>
                                                        </span>
                                                        <input type="datetime-local" name="start_date" id="start_date" 
                                                               class="form-control border-start-0 ps-0" 
                                                               min="<?= $currentDateTime ?>" required 
                                                               onchange="validateDateTime()">
                                                    </div>
                                                    <div class="form-text text-muted">Cannot select past date and time</div>
                                                    <div id="dateTimeMessage" class="mt-2"></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-medium text-dark">Stay Duration *</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light border-end-0">
                                                            <i class="fas fa-clock text-primary"></i>
                                                        </span>
                                                        <select name="duration" id="duration" 
                                                                class="form-select border-start-0 ps-0" required 
                                                                onchange="updatePriceAndAvailability()" disabled>
                                                            <option value="">Select Duration</option>
                                                            <option value="3">3 Hours</option>
                                                            <option value="6">6 Hours</option>
                                                            <option value="12">12 Hours</option>
                                                            <option value="24">24 Hours (1 Day)</option>
                                                            <option value="48">48 Hours (2 Days)</option>
                                                            <option value="72">72 Hours (3 Days)</option>
                                                            <option value="96">96 Hours (4 Days)</option>
                                                            <option value="120">120 Hours (5 Days)</option>
                                                            <option value="144">144 Hours (6 Days)</option>
                                                            <option value="168">168 Hours (7 Days)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row g-4 mt-2">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-medium text-dark">Room Number *</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light border-end-0">
                                                            <i class="fas fa-bed text-primary"></i>
                                                        </span>
                                                        <select name="room_number" id="room_number" 
                                                                class="form-select border-start-0 ps-0" required 
                                                                onchange="updatePriceAndAvailability()" disabled>
                                                            <option value="">Select Room</option>
                                                            <?php while ($room = $rooms_result->fetch_assoc()): ?>
                                                            <option value="<?= $room['room_number'] ?>" 
                                                                    data-price3="<?= $room['price_3hrs'] ?>"
                                                                    data-price6="<?= $room['price_6hrs'] ?>"
                                                                    data-price12="<?= $room['price_12hrs'] ?>"
                                                                    data-price24="<?= $room['price_24hrs'] ?>"
                                                                    data-type="<?= $room['room_type'] ?>">
                                                                Room <?= $room['room_number'] ?> - <?= ucfirst(str_replace('_', ' ', $room['room_type'])) ?>
                                                            </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-medium text-dark">Total Price</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-success text-white">
                                                            <i class="fas fa-peso-sign"></i>
                                                        </span>
                                                        <input type="text" id="totalPrice" 
                                                               class="form-control fw-bold text-success" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mt-3">
                                                <div class="col-12">
                                                    <div id="availabilityMessage"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Guest Information Section -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card shadow-lg border-0 rounded-3" id="guestInfoSection">
                                        <div class="card-header bg-light py-3">
                                            <h5 class="mb-0 fw-semibold text-muted"><i class="fas fa-user me-2"></i>Guest Information</h5>
                                        </div>
                                        <div class="card-body p-4">
                                            <div class="row g-4">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-medium text-dark">Guest Name *</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light border-end-0">
                                                            <i class="fas fa-user text-primary"></i>
                                                        </span>
                                                        <input type="text" name="guest_name" 
                                                               class="form-control border-start-0 ps-0" required disabled>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-medium text-dark">Email Address</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light border-end-0">
                                                            <i class="fas fa-envelope text-primary"></i>
                                                        </span>
                                                        <input type="email" name="email" 
                                                               class="form-control border-start-0 ps-0" 
                                                               placeholder="For booking confirmation" disabled>
                                                    </div>
                                                    <div class="form-text text-muted">Optional - for sending booking token</div>
                                                </div>
                                            </div>
                                            
                                            <div class="row g-4 mt-2">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-medium text-dark">Telephone *</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light border-end-0">
                                                            <i class="fas fa-phone text-primary"></i>
                                                        </span>
                                                        <input type="text" name="telephone" 
                                                               class="form-control border-start-0 ps-0" required 
                                                               pattern="\d{10,11}" 
                                                               placeholder="10 or 11 digit phone number" disabled>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-medium text-dark">Address *</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light border-end-0">
                                                            <i class="fas fa-map-marker-alt text-primary"></i>
                                                        </span>
                                                        <input type="text" name="address" 
                                                               class="form-control border-start-0 ps-0" required disabled>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row g-4 mt-2">
                                                <div class="col-md-4">
                                                    <label class="form-label fw-medium text-dark">Age *</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light border-end-0">
                                                            <i class="fas fa-birthday-cake text-primary"></i>
                                                        </span>
                                                        <input type="number" name="age" 
                                                               class="form-control border-start-0 ps-0" required 
                                                               min="18" disabled>
                                                    </div>
                                                    <div class="form-text text-muted">Must be at least 18</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-medium text-dark">No. of People *</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light border-end-0">
                                                            <i class="fas fa-users text-primary"></i>
                                                        </span>
                                                        <input type="number" name="num_people" 
                                                               class="form-control border-start-0 ps-0" required 
                                                               min="1" disabled>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Information Section -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card shadow-lg border-0 rounded-3" id="paymentInfoSection">
                                        <div class="card-header bg-light py-3">
                                            <h5 class="mb-0 fw-semibold text-muted"><i class="fas fa-credit-card me-2"></i>Payment Information</h5>
                                        </div>
                                        <div class="card-body p-4">
                                            <!-- Payment Method Selection -->
                                            <div class="row mb-4">
                                                <div class="col-12">
                                                    <label class="form-label fw-medium text-dark mb-3">Payment Method *</label>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <div class="card payment-option border-2 h-100" id="cash_option" onclick="selectPayment('cash')">
                                                                <div class="card-body text-center p-4">
                                                                    <div class="text-success mb-3">
                                                                        <i class="fas fa-money-bill-wave fa-3x"></i>
                                                                    </div>
                                                                    <h5 class="card-title fw-bold">Cash Payment</h5>
                                                                    <p class="card-text text-muted">Pay with cash upon booking</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="card payment-option border-2 h-100" id="gcash_option" onclick="selectPayment('gcash')">
                                                                <div class="card-body text-center p-4">
                                                                    <div class="text-primary mb-3">
                                                                        <i class="fas fa-mobile-alt fa-3x"></i>
                                                                    </div>
                                                                    <h5 class="card-title fw-bold">GCash Payment</h5>
                                                                    <p class="card-text text-muted">Pay using GCash mobile wallet</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="payment_mode" id="payment_mode" value="">
                                                </div>
                                            </div>
                                            
                                            <!-- Cash Payment Section -->
                                            <div id="cash_section" class="d-none">
                                                <div class="row g-4">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-medium text-dark">Amount Paid *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-success text-white">
                                                                <i class="fas fa-peso-sign"></i>
                                                            </span>
                                                            <input type="number" name="amount_paid" id="cash_amount_paid" 
                                                                   class="form-control" step="0.01" 
                                                                   oninput="calculateChange()" disabled>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-medium text-dark">Change</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-info text-white">
                                                                <i class="fas fa-peso-sign"></i>
                                                            </span>
                                                            <input type="text" id="change" 
                                                                   class="form-control fw-bold" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- GCash Payment Section -->
                                            <div id="gcash_section" class="d-none">
                                                <div class="row g-4">
                                                    <div class="col-md-6">
                                                        <div class="card bg-primary text-white">
                                                            <div class="card-body">
                                                                <h6 class="card-title"><i class="fas fa-info-circle me-2"></i>GCash Payment Instructions</h6>
                                                                <ol class="mb-0 ps-3">
                                                                    <li>Open your GCash app</li>
                                                                    <li>Send payment to: <strong>09123456789</strong></li>
                                                                    <li>Enter amount: <span id="gcash_amount_display" class="fw-bold">₱0.00</span></li>
                                                                    <li>Complete payment and note reference number</li>
                                                                </ol>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-medium text-dark">Amount Paid via GCash *</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-primary text-white">
                                                                    <i class="fas fa-peso-sign"></i>
                                                                </span>
                                                                <input type="number" name="amount_paid" id="gcash_amount_paid" 
                                                                       class="form-control" step="0.01" disabled>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-medium text-dark">GCash Reference Number *</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-primary text-white">
                                                                    <i class="fas fa-hashtag"></i>
                                                                </span>
                                                                <input type="text" name="reference_number" 
                                                                       class="form-control" 
                                                                       placeholder="Enter reference number" disabled>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="submit" form="bookingForm" class="btn btn-primary" id="submitBtn" disabled>
                    <i class="fas fa-save me-2"></i>Create Booking
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.payment-option {
    cursor: pointer;
    transition: all 0.3s ease;
    border-color: #dee2e6 !important;
}

.payment-option:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.payment-option.selected {
    border-color: #0d6efd !important;
    background-color: rgba(13, 110, 253, 0.05);
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.input-group-text {
    border-right: none;
}

.form-control {
    border-left: none;
}

.form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}
</style>

<script>
let step1Complete = false;
let step2Complete = false;
let roomAvailable = false;

// Validate date and time - cannot be in the past
function validateDateTime() {
    const startDate = document.getElementById('start_date').value;
    const messageDiv = document.getElementById('dateTimeMessage');
    const durationSelect = document.getElementById('duration');
    
    if (startDate) {
        const selectedDateTime = new Date(startDate);
        const currentDateTime = new Date();
        
        if (selectedDateTime <= currentDateTime) {
            messageDiv.innerHTML = '<div class="alert alert-danger alert-sm"><i class="fas fa-times-circle me-2"></i>Cannot select past date and time</div>';
            durationSelect.disabled = true;
            step1Complete = false;
        } else {
            messageDiv.innerHTML = '<div class="alert alert-success alert-sm"><i class="fas fa-check-circle me-2"></i>Valid date and time selected</div>';
            durationSelect.disabled = false;
            checkStep1Completion();
        }
    }
}

// Update price and check availability
function updatePriceAndAvailability() {
    const roomNumber = document.getElementById('room_number').value;
    const startDate = document.getElementById('start_date').value;
    const duration = document.getElementById('duration').value;
    
    if (roomNumber && startDate && duration) {
        updateBookingPrice();
        checkRoomAvailability();
    }
    
    // Enable room selection after duration is selected
    if (duration) {
        document.getElementById('room_number').disabled = false;
    } else {
        document.getElementById('room_number').disabled = true;
        document.getElementById('room_number').value = '';
    }
}

// Update price based on room and duration selection
function updateBookingPrice() {
    const roomSelect = document.getElementById('room_number');
    const durationSelect = document.getElementById('duration');
    const priceInput = document.getElementById('totalPrice');
    const gcashAmountDisplay = document.getElementById('gcash_amount_display');
    
    if (roomSelect.value && durationSelect.value) {
        const selectedRoom = roomSelect.options[roomSelect.selectedIndex];
        const duration = parseInt(durationSelect.value);
        let price = 0;
        
        if (duration >= 48) {
            // For 2+ days, use 24hr rate multiplied by days
            const days = Math.ceil(duration / 24);
            price = parseFloat(selectedRoom.dataset.price24) * days;
        } else {
            // Standard hourly rates
            switch(duration) {
                case 3:
                    price = parseFloat(selectedRoom.dataset.price3);
                    break;
                case 6:
                    price = parseFloat(selectedRoom.dataset.price6);
                    break;
                case 12:
                    price = parseFloat(selectedRoom.dataset.price12);
                    break;
                case 24:
                    price = parseFloat(selectedRoom.dataset.price24);
                    break;
            }
        }
        
        priceInput.value = price.toFixed(2);
        if (gcashAmountDisplay) {
            gcashAmountDisplay.textContent = '₱' + price.toFixed(2);
        }
    } else {
        priceInput.value = '';
        if (gcashAmountDisplay) {
            gcashAmountDisplay.textContent = '₱0.00';
        }
    }
}

// Check room availability
function checkRoomAvailability() {
    const roomNumber = document.getElementById('room_number').value;
    const startDate = document.getElementById('start_date').value;
    const duration = document.getElementById('duration').value;
    const messageDiv = document.getElementById('availabilityMessage');
    
    if (roomNumber && startDate && duration) {
        // AJAX call to check availability
        fetch('check_room_availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                room_number: roomNumber,
                start_date: startDate,
                duration: duration
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.available) {
                messageDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Room is available for selected time</div>';
                roomAvailable = true;
                checkStep1Completion();
            } else {
                messageDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Room is not available for this time period. Please select different time or room.</div>';
                roomAvailable = false;
                step1Complete = false;
                disableSubsequentSteps();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messageDiv.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Error checking availability</div>';
        });
    }
}

// Check if Step 1 is complete
function checkStep1Completion() {
    const startDate = document.getElementById('start_date').value;
    const duration = document.getElementById('duration').value;
    const roomNumber = document.getElementById('room_number').value;
    
    if (startDate && duration && roomNumber && roomAvailable) {
        step1Complete = true;
        enableGuestInfoSection();
    } else {
        step1Complete = false;
        disableSubsequentSteps();
    }
}

// Enable guest information section
function enableGuestInfoSection() {
    const guestInputs = document.querySelectorAll('#guestInfoSection input');
    guestInputs.forEach(input => {
        input.disabled = false;
        input.addEventListener('input', checkStep2Completion);
    });
    
    document.querySelector('#guestInfoSection .card-header').classList.remove('bg-light');
    document.querySelector('#guestInfoSection .card-header').classList.add('bg-warning', 'text-dark');
    document.querySelector('#guestInfoSection .card-header h5').classList.remove('text-muted');
}

// Check if Step 2 is complete
function checkStep2Completion() {
    const guestName = document.querySelector('input[name="guest_name"]').value;
    const telephone = document.querySelector('input[name="telephone"]').value;
    const address = document.querySelector('input[name="address"]').value;
    const age = document.querySelector('input[name="age"]').value;
    const numPeople = document.querySelector('input[name="num_people"]').value;
}

// Enable payment information section
function enablePaymentInfoSection() {
    document.querySelector('#paymentInfoSection .card-header').classList.remove('bg-light');
    document.querySelector('#paymentInfoSection .card-header').classList.add('bg-warning', 'text-dark');
    document.querySelector('#paymentInfoSection .card-header h5').classList.remove('text-muted');
    
    // Enable payment option selection
    document.getElementById('cash_option').style.pointerEvents = 'auto';
    document.getElementById('gcash_option').style.pointerEvents = 'auto';
    
    checkFormCompletion();
}

// Select payment method
function selectPayment(method) {
    // Remove previous selections
    document.getElementById('cash_option').classList.remove('selected');
    document.getElementById('gcash_option').classList.remove('selected');
    
    // Hide all payment sections
    document.getElementById('cash_section').classList.add('d-none');
    document.getElementById('gcash_section').classList.add('d-none');
    
    // Reset payment inputs
    document.querySelectorAll('#cash_section input, #gcash_section input').forEach(input => {
        input.disabled = true;
        input.value = '';
    });
    
    if (method === 'cash') {
        document.getElementById('cash_option').classList.add('selected');
        document.getElementById('cash_section').classList.remove('d-none');
        document.getElementById('payment_mode').value = 'Cash';
        
        // Enable cash inputs 
        document.querySelectorAll('#cash_section input').forEach(input => {
            input.disabled = false;
        });
    } else if (method === 'gcash') {
        document.getElementById('gcash_option').classList.add('selected');
        document.getElementById('gcash_section').classList.remove('d-none');
        document.getElementById('payment_mode').value = 'GCash';
        
        // Enable gcash inputs
        document.querySelectorAll('#gcash_section input').forEach(input => {
            input.disabled = false;
        });
        
        // Set GCash amount to total price
        const totalPrice = document.getElementById('totalPrice').value;
        document.getElementById('gcash_amount_paid').value = totalPrice;
    }
    
    checkFormCompletion();
}

// Calculate change for cash payment
function calculateChange() {
    const totalPrice = parseFloat(document.getElementById('totalPrice').value) || 0;
    const amountPaid = parseFloat(document.getElementById('cash_amount_paid').value) || 0;
    const change = amountPaid - totalPrice;
    
    document.getElementById('change').value = change >= 0 ? change.toFixed(2) : '0.00';
    checkFormCompletion();
}

// Check form completion
function checkFormCompletion() {
    const paymentMode = document.getElementById('payment_mode').value;
    let amountPaid = 0;
    let referenceValid = true;
    
    if (paymentMode === 'Cash') {
        amountPaid = parseFloat(document.getElementById('cash_amount_paid').value) || 0;
    } else if (paymentMode === 'GCash') {
        amountPaid = parseFloat(document.getElementById('gcash_amount_paid').value) || 0;
        const reference = document.querySelector('input[name="reference_number"]').value;
        referenceValid = reference.trim() !== '';
    }
    
    if (step1Complete && step2Complete && paymentMode && amountPaid > 0 && referenceValid) {
        document.getElementById('submitBtn').disabled = false;
    } else {
        document.getElementById('submitBtn').disabled = true;
    }
}

// Disable subsequent steps
function disableSubsequentSteps() {
    disableGuestInfoSection();
    disablePaymentSection();
}

// Disable guest info section
function disableGuestInfoSection() {
    const guestInputs = document.querySelectorAll('#guestInfoSection input');
    guestInputs.forEach(input => {
        input.disabled = true;
        input.value = '';
    });
    
    document.querySelector('#guestInfoSection .card-header').classList.remove('bg-warning', 'text-dark');
    document.querySelector('#guestInfoSection .card-header').classList.add('bg-light');
    document.querySelector('#guestInfoSection .card-header h5').classList.add('text-muted');
}

// Disable payment section
function disablePaymentSection() {
    document.querySelector('#paymentInfoSection .card-header').classList.remove('bg-warning', 'text-dark');
    document.querySelector('#paymentInfoSection .card-header').classList.add('bg-light');
    document.querySelector('#paymentInfoSection .card-header h5').classList.add('text-muted');
    
    // Disable payment option selection
    document.getElementById('cash_option').style.pointerEvents = 'none';
    document.getElementById('gcash_option').style.pointerEvents = 'none';
    
    // Reset payment selections
    document.getElementById('cash_option').classList.remove('selected');
    document.getElementById('gcash_option').classList.remove('selected');
    document.getElementById('cash_section').classList.add('d-none');
    document.getElementById('gcash_section').classList.add('d-none');
    document.getElementById('payment_mode').value = '';
    
    document.getElementById('submitBtn').disabled = true;
}

// Form validation
function validateBookingForm() {
    const age = parseInt(document.querySelector('input[name="age"]').value);
    const paymentMode = document.getElementById('payment_mode').value;
    const reference = document.querySelector('input[name="reference_number"]').value;
    const totalPrice = parseFloat(document.getElementById('totalPrice').value);
    let amountPaid = 0;
    
    if (paymentMode === 'Cash') {
        amountPaid = parseFloat(document.getElementById('cash_amount_paid').value);
    } else if (paymentMode === 'GCash') {
        amountPaid = parseFloat(document.getElementById('gcash_amount_paid').value);
    }
    
    if (!step1Complete) {
        alert('Please complete Step 1: Booking Details first.');
        return false;
    }
    
    if (!step2Complete) {
        alert('Please complete Step 2: Guest Information.');
        return false;
    }
    
    if (!roomAvailable) {
        alert('Selected room is not available for the chosen time period.');
        return false;
    }
    
    if (age < 18) {
        alert('Guest must be at least 18 years old.');
        return false;
    }
    
    if (paymentMode === 'GCash' && !reference.trim()) {
        alert('GCash reference number is required.');
        return false;
    }
    
    if (amountPaid < totalPrice) {
        alert('Amount paid cannot be less than total price.');
        return false;
    }
    
    return true;
}

// Reset form when modal is closed
document.getElementById('bookingModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('bookingForm').reset();
    document.getElementById('totalPrice').value = '';
    document.getElementById('change').value = '';
    document.getElementById('gcash_amount_display').textContent = '₱0.00';
    document.getElementById('availabilityMessage').innerHTML = '';
    document.getElementById('dateTimeMessage').innerHTML = '';
    
    // Reset payment sections
    document.getElementById('cash_section').classList.add('d-none');
    document.getElementById('gcash_section').classList.add('d-none');
    document.getElementById('cash_option').classList.remove('selected');
    document.getElementById('gcash_option').classList.remove('selected');
    
    // Reset step states
    step1Complete = false;
    step2Complete = false;
    roomAvailable = false;
    
    // Reset field states
    document.getElementById('duration').disabled = true;
    document.getElementById('room_number').disabled = true;
    disableSubsequentSteps();
});
</script>
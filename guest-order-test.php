<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Full Menu</title>

  <!-- ✅ Google Fonts: Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- ✅ Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <style>
    :root {
  --maroon: #800000;
  --maroon-dark: #5a0000;
      --matte-black: #1c1c1c;
      --text-gray: #6c757d;
      --card-bg: #f8f8f8ff;
      --hover-bg: #f3f3f3ff;
    }

    body {
      background-color: #ffffff;
      color: var(--matte-black);
      font-family: "Poppins", sans-serif;
    }

    /* ---------- Navbar ---------- */
    .navbar {
      background-color: #ffffff;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .navbar .input-group {
  min-width: 240px;
}

.navbar .form-control {
  font-size: 0.9rem;
  padding: 6px 10px;
}

.navbar button {
  white-space: nowrap;
}

    .navbar input {
      background-color: #f8f9fa;
      border: none;
      color: var(--matte-black);
    }

    .navbar input::placeholder {
      color: var(--text-gray);
    }

    .input-group-text {
      background-color: #f8f9fa;
      border: none;
      color: var(--text-gray);
    }

/* 🛒 CART BUTTON */
.btn-cart {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background-color: var(--maroon-dark);
  color: #fff;
  border: 2px solid var(--maroon-dark);
  padding: 6px 16px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

/* Animate cart icon */
.btn-cart i {
  transition: transform 0.3s ease;
}

.btn-cart:hover {
  background-color: #fff;
  color: var(--maroon);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
}

.btn-cart:hover i {
  transform: rotate(-15deg) scale(1.3);
  color: var(--maroon);
}

/* 👁️ VIEW ORDER BUTTON */
.btn-view {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background-color: var(--matte-black);
  color: #fff;
  border: 2px solid var(--matte-black);
  padding: 6px 16px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

/* Animate eye icon */
.btn-view i {
  transition: transform 0.3s ease;
}

.btn-view:hover {
  background-color: #fff;
  color: var(--matte-black);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.btn-view:hover i {
  transform: scale(1.2);
  color: var(--matte-black);
}

    /* ---------- Menu Section ---------- */
    h3 {
      font-weight: 700;
      color: var(--matte-black);
    }

    p.text-muted {
      color: var(--text-gray) !important;
    }

.menu-card {
  border: none;
  border-radius: 16px;
  overflow: hidden;
  background-color: var(--card-bg);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5); /* 🌟 Default soft shadow */
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.menu-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3); /* 🌟 Stronger on hover */
  background-color: var(--hover-bg);
}

    .category-badge {
      position: absolute;
      top: 12px;
      left: 12px;
      background-color: var(--maroon);
      color: #fff;
      font-size: 0.75rem;
      padding: 4px 10px;
      border-radius: 20px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .rating-badge {
      position: absolute;
      top: 12px;
      right: 12px;
      background-color: #fff;
      color: var(--matte-black);
      font-size: 0.8rem;
      padding: 3px 8px;
      border-radius: 20px;
      font-weight: 600;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .price {
      color: var(--maroon);
      font-weight: 700;
      font-size: 1.1rem;
      margin: 0;
    }

.btn-add {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background-color: #fff;
  color: #000; /* Black text */
  border: 2px solid #000; /* Black border */
  border-radius: 50px;
  padding: 6px 14px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

/* Add the + icon before the text */
.btn-add::before {
  content: "+";
  font-size: 1.1rem;
  font-weight: bold;
  color: #000; /* Black icon */
  transition: transform 0.3s ease, color 0.3s ease;
}

/* Hover animation */
.btn-add:hover {
  background-color: #000; /* Black background */
  color: #fff; /* White text */
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

/* Animate the + icon on hover */
.btn-add:hover::before {
  transform: rotate(180deg) scale(1.2);
  color: #fff;
}

/* Optional: small press-down effect */
.btn-add:active {
  transform: translateY(0);
  box-shadow: none;
}

    .card-body {
      padding: 1rem 1.25rem 1.25rem;
    }

    .card-img-top {
      height: 180px;
      object-fit: cover;
    }
  </style>
</head>
<body>

<!-- ==================== NAVBAR ==================== -->
<nav class="navbar navbar-light px-4 py-3">
  <div class="container-fluid d-flex justify-content-end align-items-center gap-1">

    <!-- Search Bar -->
    <form class="d-flex" style="width: 260px;">
      <div class="input-group">
        <span class="input-group-text border-end-0">
          <i class="bi bi-search"></i>
        </span>
        <input type="text" class="form-control border-start-0" placeholder="Search dishes..." />
      </div>
    </form>

    <!-- Buttons -->
    <button class="btn btn-cart ms-2">
      <i class="bi bi-cart-fill"></i> Cart
    </button>
    <button class="btn btn-view ms-2">
      <i class="bi bi-eye"></i> View Order
    </button>

  </div>
</nav>

  <!-- ==================== MENU SECTION ==================== -->
  <div class="container mt-5">
    <h3>Full Menu</h3>
    <p class="text-muted">Explore our complete selection</p>

    <div class="row g-4 mt-3">
      
      <!-- Card 1 -->
      <div class="col-md-4 col-lg-4">
        <div class="card menu-card position-relative">
          <span class="category-badge">Noodles</span>
          <span class="rating-badge"><i class="bi bi-star-fill text-warning"></i> 4.8</span>
          <img src="image/Lomi.jpg" class="card-img-top" alt="Lomi">
          <div class="card-body">
            <h6 class="fw-bold mb-1">Lomi</h6>
            <p class="text-muted small mb-3">Small ₱60 - Medium ₱70 - Large ₱80</p>
            <div class="d-flex justify-content-between align-items-center">
              <p class="price mb-0">₱60 - ₱70 - ₱80</p>
              <button class="btn btn-add">Add</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 2 -->
      <div class="col-md-4 col-lg-4">
        <div class="card menu-card position-relative">
          <span class="category-badge">Noodles</span>
          <span class="rating-badge"><i class="bi bi-star-fill text-warning"></i> 4.9</span>
          <img src="image/Mami.jpg" class="card-img-top" alt="Mami">
          <div class="card-body">
            <h6 class="fw-bold mb-1">Mami</h6>
            <p class="text-muted small mb-3">Savory chicken noodle soup</p>
            <div class="d-flex justify-content-between align-items-center">
              <p class="price mb-0">₱70</p>
              <button class="btn btn-add">Add</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 3 -->
      <div class="col-md-4 col-lg-4">
        <div class="card menu-card position-relative">
          <span class="category-badge">Instant Noodles</span>
          <span class="rating-badge"><i class="bi bi-star-fill text-warning"></i> 4.7</span>
          <img src="image/Nissin Beef.png" class="card-img-top" alt="Nissin Beef">
          <div class="card-body">
            <h6 class="fw-bold mb-1">Nissin Cup (Beef)</h6>
            <p class="text-muted small mb-3">Hot beef-flavored instant cup noodles</p>
            <div class="d-flex justify-content-between align-items-center">
              <p class="price mb-0">₱40</p>
              <button class="btn btn-add">Add</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 4 -->
      <div class="col-md-4 col-lg-4">
        <div class="card menu-card position-relative">
          <span class="category-badge">Instant Noodles</span>
          <span class="rating-badge"><i class="bi bi-star-fill text-warning"></i> 4.9</span>
          <img src="image/Nissin Chicken.png" class="card-img-top" alt="Nissin Chicken">
          <div class="card-body">
            <h6 class="fw-bold mb-1">Nissin Cup (Chicken)</h6>
            <p class="text-muted small mb-3">Classic chicken-flavored instant noodles</p>
            <div class="d-flex justify-content-between align-items-center">
              <p class="price mb-0">₱40</p>
              <button class="btn btn-add">Add</button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

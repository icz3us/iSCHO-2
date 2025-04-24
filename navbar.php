<?php
// navbar.php - Contains both HTML and CSS for the navbar
?>
<!DOCTYPE html>
<html lang="en">
    <head>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Add Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
*{
    font-family: 'Poppins', sans-serif;
}
  nav {
    width: 100%;
    background-color: #ffffff;
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    position: sticky;
    top: 0;
    z-index: 100;
  }

  .logo img {
    height: 40px;
    width: auto;
  }

  .nav-links {
    display: flex;
    gap: 1.5rem;
  }

  .nav-links a {
    text-decoration: none;
    color: #333;
    font-size: 1.2rem;
    transition: color 0.3s ease;
  }

  .nav-links a:hover {
    color: #007bff;
  }

  .menu-toggle {
    display: none;
    flex-direction: column;
    cursor: pointer;
  }

  .menu-toggle span {
    height: 2px;
    width: 25px;
    background: #333;
    margin: 4px 0;
    transition: all 0.3s;
  }

  @media (max-width: 768px) {
    .nav-links {
      position: absolute;
      top: 70px;
      left: 0;
      width: 100%;
      background-color: #ffffff;
      flex-direction: column;
      align-items: center;
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease-in-out;
    }

    .nav-links.open {
      max-height: 300px;
    }

    .menu-toggle {
      display: flex;
    }
  }
</style>

<nav>
  <div class="logo"><a href="home.php">
    <img src="./images/logo1.png" alt="Logo">
</a>
  </div>
  <div class="menu-toggle" id="menu-toggle">
    <span></span>
    <span></span>
    <span></span>
  </div>
  <div class="nav-links" id="nav-links">
    <a href="home.php">Home</a>
    <a href="about_us.php">About</a>
    <a href="#">Procedure</a>
    <a href="login.php">Login</a>
  </div>
</nav>

<script>
  const toggle = document.getElementById('menu-toggle');
  const navLinks = document.getElementById('nav-links');

  toggle.addEventListener('click', () => {
    navLinks.classList.toggle('open');
  });
</script>
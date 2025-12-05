<?php

?>
<!DOCTYPE html>
<html lang="en">
    <head>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
*{
    font-family: 'Poppins', sans-serif;
}
  nav {
    width: 100%;
    background: rgba(30, 27, 75, 0.85);
    backdrop-filter: blur(20px);
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    position: sticky;
    top: 0;
    z-index: 1000;
    transition: all 0.3s ease;
    border-bottom: 1px solid rgba(99, 102, 241, 0.3);
  }

  nav.scrolled {
    background: rgba(30, 27, 75, 0.95);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.4);
    padding: 0.75rem 2rem;
  }

  .logo img {
    height: 45px;
    width: auto;
    transition: transform 0.3s ease;
  }

  .logo:hover img {
    transform: scale(1.05);
  }

  .nav-links {
    display: flex;
    gap: 2rem;
    align-items: center;
  }

  .nav-links a {
    text-decoration: none;
    color: #f8fafc;
    font-size: 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
    position: relative;
    padding: 0.5rem 0;
  }

  .nav-links a::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, #667eea, #764ba2, #f093fb);
    transition: width 0.3s ease;
  }

  .nav-links a:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .nav-links a:hover::after {
    width: 100%;
  }

  .nav-links a:last-child {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    color: white;
    padding: 0.6rem 1.5rem;
    border-radius: 25px;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
  }

  .nav-links a:last-child::after {
    display: none;
  }

  .nav-links a:last-child:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
  }

  .menu-toggle {
    display: none;
    flex-direction: column;
    cursor: pointer;
  }

  .menu-toggle span {
    height: 2px;
    width: 25px;
    background: #f8fafc;
    margin: 4px 0;
    transition: all 0.3s;
  }

  @media (max-width: 768px) {
    nav {
      padding: 1rem 1.5rem;
    }

    .nav-links {
      position: absolute;
      top: 100%;
      left: 0;
      width: 100%;
      background: rgba(30, 27, 75, 0.98);
      backdrop-filter: blur(20px);
      flex-direction: column;
      align-items: stretch;
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.4s ease-in-out, padding 0.4s ease;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
      padding: 0;
      border-top: 1px solid rgba(99, 102, 241, 0.3);
    }

    .nav-links.open {
      max-height: 500px;
      padding: 1.5rem 0;
    }

    .nav-links a {
      padding: 1rem 2rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .nav-links a:last-child {
      margin: 0.5rem 2rem;
      border-radius: 25px;
    }

    .menu-toggle {
      display: flex;
    }
  }

  .logo {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .logo a {
    display: flex;
    align-items: center;
  }

  .logo h3 {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 50%, #f0abfc 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0;
  }

  @media (max-width: 768px) {
    .logo h3 {
        font-size: 1rem;
    }
  }

  @media (max-width: 576px) {
    .logo h3 {
        display: none;
    }
  }
</style>

<nav>
  <div class="logo">
    <a href="home.php">
      <img src="./images/logo1.png" alt="Logo">
    </a>
    <h3>Integrated Scholarship Application Portal</h3>
  </div>
  <div class="menu-toggle" id="menu-toggle">
    <span></span>
    <span></span>
    <span></span>
  </div>
  <div class="nav-links" id="nav-links">
    <a href="home.php#features">Features</a>
    <a href="home.php#announcements">Announcements</a>
    <a href="home.php#about">About</a>
    <a href="home.php#procedure">Procedure</a>
    <a href="login.php">Login</a>
  </div>
</nav>

<script>
  const toggle = document.getElementById('menu-toggle');
  const navLinks = document.getElementById('nav-links');
  const nav = document.querySelector('nav');

  toggle.addEventListener('click', () => {
    navLinks.classList.toggle('open');
    toggle.classList.toggle('active');
  });

  // Navbar scroll effect
  window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
      nav.classList.add('scrolled');
    } else {
      nav.classList.remove('scrolled');
    }
  });

  // Close mobile menu when clicking on a link
  navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      navLinks.classList.remove('open');
      toggle.classList.remove('active');
    });
  });
</script>
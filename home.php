<?php


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="icon" type="image/png" href="./images/logo1.png">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <title>iSCHO - Integrated Scholarship Application Portal</title>
  <style>
    :root {
      --primary-color: #4f46e5;
      --primary-hover: #4338ca;
      --text-color: #1f2937;
      --text-muted: #6b7280;
      --bg-light: #f9fafb;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--bg-light);
      color: var(--text-color);
      line-height: 1.6;
      scroll-behavior: smooth;
    }

    .hero {
      position: relative;
      height: 100vh;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: left;
      overflow: hidden;
      margin-top: -10vh; 
    }

    .hero img {
      position: absolute;
      top: 0;
      left: 0;
      height: 100%;
      width: 100%;
      object-fit: cover;
      z-index: 0;
    }

    .hero::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      height: 100%;
      width: 100%;
      background: linear-gradient(rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.7));
      z-index: 1;
    }

    .hero-content {
      position: relative;
      z-index: 2;
      color: var(--text-color);
      padding: 2rem;
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 4rem;
    }

    .hero-text {
      flex: 1;
      animation: fadeInLeft 1s ease;
    }

    .hero-image {
      flex: 1;
      display: flex;
      justify-content: center;
      animation: fadeInRight 1s ease;
      position: relative;
      padding: 2rem;
    }

    .hero-image img {
      width: 400px;
      height: auto;
      object-fit: contain;
      position: relative;
      z-index: 2;
    }

    .hero-image::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(79, 70, 229, 0.1) 0%, transparent 70%);
      border-radius: 50%;
      z-index: 1;
    }

    .hero-image::after {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 100px;
      height: 100px;
      background: radial-gradient(circle, rgba(99, 102, 241, 0.2) 0%, transparent 70%);
      border-radius: 50%;
      z-index: 1;
    }

    .hero h1 {
      font-size: 3.5rem;
      font-weight: 700;
      margin-bottom: 1.5rem;
      line-height: 1.2;
      background: linear-gradient(45deg, var(--primary-color), #6366f1);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .hero p {
      font-size: 1.1rem;
      margin-bottom: 2rem;
      color: var(--text-muted);
      max-width: 600px;
    }

    .cta-buttons {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
    }

    .button {
      background-color: var(--primary-color);
      color: #fff;
      border: none;
      padding: 1rem 2rem;
      font-size: 1rem;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 500;
    }

    .button:hover {
      background-color: var(--primary-hover);
      transform: translateY(-2px);
    }

    .button.secondary {
      background-color: transparent;
      border: 2px solid var(--primary-color);
      color: var(--primary-color);
    }

    .button.secondary:hover {
      background-color: var(--primary-color);
      color: white;
    }

    /* Scroll Down Button Styles */
    .scroll-down {
      position: absolute;
      bottom: 2rem;
      left: 50%;
      transform: translateX(-50%);
      z-index: 2;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-decoration: none; 
      color: var(--primary-color);
      font-size: 0.9rem;
      opacity: 0.8;
      transition: all 0.3s ease;
      animation: bounce 2s infinite;
    }

    .scroll-down:hover {
      opacity: 1;
      transform: translateX(-50%) translateY(-5px);
    }

    .scroll-down i {
      font-size: 1.5rem;
      margin-top: 0.5rem;
    }

    @keyframes bounce {
      0%, 20%, 50%, 80%, 100% {
        transform: translateX(-50%) translateY(0);
      }
      40% {
        transform: translateX(-50%) translateY(-10px);
      }
      60% {
        transform: translateX(-50%) translateY(-5px);
      }
    }

    .features {
      padding: 6rem 2rem;
      background-color: white;
    }

    .features-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
      margin-top: 3rem;
    }

    .feature-card {
      padding: 2rem;
      border-radius: 15px;
      background-color: var(--bg-light);
      transition: transform 0.3s ease;
    }

    .feature-card:hover {
      transform: translateY(-5px);
    }

    .feature-icon {
      font-size: 2rem;
      color: var(--primary-color);
      margin-bottom: 1rem;
    }

    .feature-card h3 {
      font-size: 1.5rem;
      margin-bottom: 1rem;
      color: var(--text-color);
    }

    .feature-card p {
      color: var(--text-muted);
    }

    .section-title {
      text-align: center;
      margin-bottom: 3rem;
    }

    .section-title h2 {
      font-size: 2.5rem;
      color: var(--text-color);
      margin-bottom: 1rem;
    }

    .section-title p {
      color: var(--text-muted);
      max-width: 600px;
      margin: 0 auto;
    }

    @keyframes fadeInLeft {
      from {
        opacity: 0;
        transform: translateX(-20px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes fadeInRight {
      from {
        opacity: 0;
        transform: translateX(20px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @media (max-width: 1024px) {
      .hero {
        min-height: 100vh;
        margin-top: 0;
        padding: 6rem 1rem 2rem 1rem;
      }

      .hero-content {
        flex-direction: column;
        text-align: center;
        gap: 2rem;
        padding: 0 1rem;
      }

      .hero h1 {
        font-size: 2.8rem;
      }

      .cta-buttons {
        justify-content: center;
      }

      .hero-image {
        order: -1;
        padding: 1rem;
      }

      .hero-image img {
        width: 300px;
      }

      .hero-image::before {
        width: 400px;
        height: 400px;
      }
    }

    @media (max-width: 768px) {
      .hero {
        padding: 2rem 1rem 2rem 1rem;
      }

      .hero-content {
        margin-top: -3rem;
      }

      .hero h1 {
        font-size: 2.2rem;
        margin-bottom: 1rem;
      }

      .hero p {
        font-size: 1rem;
        margin-bottom: 1.5rem;
      }

      .hero-image img {
        width: 250px;
      }

      .features {
        padding: 4rem 1.5rem;
      }

      .features-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }

      .section-title h2 {
        font-size: 2rem;
      }

      .section-title p {
        font-size: 0.95rem;
        padding: 0 1rem;
      }

      .feature-card {
        padding: 1.5rem;
      }
    }

    @media (max-width: 480px) {
      .hero {
        padding: 1.5rem 1rem 2rem 1rem;
      }

      .hero-content {
        margin-top: -2rem;
      }

      .hero h1 {
        font-size: 1.8rem;
        line-height: 1.3;
      }

      .hero p {
        font-size: 0.95rem;
        margin-bottom: 1.5rem;
      }

      .hero-image {
        padding: 0;
        margin-bottom: 0.5rem;
      }

      .hero-image img {
        width: 200px;
      }

      .hero-image::before {
        width: 250px;
        height: 250px;
      }

      .cta-buttons {
        flex-direction: column;
        gap: 0.8rem;
      }

      .button {
        width: 100%;
        justify-content: center;
        padding: 0.8rem 1.5rem;
        font-size: 0.95rem;
      }

      .features {
        padding: 3rem 1rem;
      }

      .section-title h2 {
        font-size: 1.8rem;
      }

      .feature-card {
        padding: 1.25rem;
      }

      .feature-card h3 {
        font-size: 1.2rem;
      }

      .feature-card p {
        font-size: 0.9rem;
      }

      .scroll-down {
        bottom: 1rem;
        font-size: 0.8rem;
      }
    }

    /* Add styles for very small screens */
    @media (max-width: 360px) {
      .hero h1 {
        font-size: 1.6rem;
      }

      .hero-image img {
        width: 180px;
      }

      .hero-image::before {
        width: 220px;
        height: 220px;
      }

      .button {
        padding: 0.7rem 1.2rem;
        font-size: 0.9rem;
      }
    }

    /* Fix for tall mobile screens */
    @media (max-height: 700px) and (max-width: 768px) {
      .hero {
        padding: 1rem 1rem 1rem 1rem;
      }

      .hero-content {
        margin-top: -1.5rem;
      }

      .hero-image img {
        width: 180px;
      }

      .hero h1 {
        font-size: 1.8rem;
        margin-bottom: 0.8rem;
      }

      .hero p {
        margin-bottom: 1rem;
      }
    }

    /* Ensure minimum content height */
    @media (min-height: 800px) {
      .hero {
        min-height: 100vh;
      }
    }
  </style>
</head>
<body>
  <?php include('navbar.php'); ?>

  <section class="hero">
    <img src="./images/bg.jpg" alt="Background">
    <div class="hero-content">
      <div class="hero-text">
        <h1>The Bridge Towards your Dreams</h1>
        <p>Welcome to iSCHO, your comprehensive Scholarship Application Portal. We simplify the scholarship process, making it easier for deserving students to access educational opportunities and achieve their academic dreams.</p>
        <div class="cta-buttons">
          <a href="login.php" class="button">
            <i class="fas fa-user-plus"></i>
            Get Started
          </a>
          <a href="about_us.php" class="button secondary">
            <i class="fas fa-info-circle"></i>
            Learn More
          </a>
        </div>
      </div>
      <div class="hero-image">
        <img src="./images/logo1.png" alt="iSCHO Logo" style="filter: drop-shadow(0 10px 20px rgba(79, 70, 229, 0.2));">
      </div>
    </div>
    <a href="#features" class="scroll-down">
      Scroll Down
      <i class="fas fa-chevron-down"></i>
    </a>
  </section>

  <section class="features" id="features">
    <div class="features-container">
      <div class="section-title">
        <h2>Why Choose iSCHO?</h2>
        <p>Discover the benefits of our integrated scholarship management system</p>
      </div>
      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-laptop"></i>
          </div>
          <h3>Easy Application</h3>
          <p>Simple and intuitive online application process with step-by-step guidance.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-clock"></i>
          </div>
          <h3>Real-time Updates</h3>
          <p>Stay informed about your application status with instant notifications.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-shield-alt"></i>
          </div>
          <h3>Secure Platform</h3>
          <p>Your data is protected with advanced security measures and encryption.</p>
        </div>
      </div>
    </div>
  </section>
</body>
</html>
<?php
// Main PHP file
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Home</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f8f9fa;
      color: #333;
    }

    .hero {
      position: relative;
      height: 100vh;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
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
      backdrop-filter: blur(5px);
      background: rgba(255, 255, 255, 0.3);
      z-index: 1;
    }

    .hero-content {
      position: relative;
      z-index: 2;
      color: #000;
      padding: 2rem;
    }

    .hero h1 {
      font-size: 2.5rem;
      font-weight: bold;
      margin-bottom: 1.5rem;
    }

    .hero p {
      font-size: 1rem;
      margin-bottom: 1.2rem; 
    }

    .button {
      background-color: #4f46e5;
      color: #fff;
      border: none;
      padding: 0.75rem 2rem;
      font-size: 1rem;
      border-radius: 10px;
      cursor: pointer;
      transition: background-color 0.3s ease;
      text-decoration: none; 
      display: inline-block; 
      margin-top: 1rem; 
    }

    .hero .button:hover {
      background-color: #0056b3;
    }

    @media (max-width: 768px) {
      .hero h1 {
        font-size: 1.8rem;
      }

      .hero p {
        font-size: 0.9rem;
        margin-bottom: 1.5rem; 
      }

      .hero .button {
        padding: 0.6rem 1.5rem;
        margin-top: 0.75rem; 
      }
    }

    @media (max-width: 480px) {
      .hero h1 {
        font-size: 1.5rem;
      }

      .hero p {
        font-size: 0.85rem;
      }

      .hero .button {
        padding: 0.5rem 1.2rem;
        margin-top: 0.5rem;
      }
    }
  </style>
</head>
<body>

  <?php include('navbar.php'); ?>

  <section class="hero">
    <img src="./images/gc1.jpg" alt="bg">
    <div class="hero-content">
      <h1>iSCHO: The Bridge Towards your Dreams</h1>
      <p>Welcome to iSCHO, your one-stop Scholarship Application Portal! Designed to simplify the scholarship process, iSCHO empowers students to easily apply for and manage scholarship opportunities. 
        With a user-friendly interface and seamless navigation, we connect you to your educational dreams—hassle-free.</p>
      <a href="login.php" class="button">Get Started</a>
    </div>
  </section>

</body>
</html>
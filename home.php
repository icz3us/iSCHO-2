<?php
require 'connect/connection.php';

// Fetch all announcements (notices) ordered by creation date
try {
    $stmt = $pdo->prepare("
        SELECT n.message, n.created_at, n.image_path, u.firstname, u.lastname 
        FROM notices n 
        JOIN users u ON n.user_id = u.id 
        WHERE u.role = 'Superadmin' 
        ORDER BY n.created_at DESC
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching announcements: " . $e->getMessage();
    $announcements = [];
}
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
      --primary-color: #6366f1;
      --primary-hover: #818cf8;
      --primary-light: rgba(99, 102, 241, 0.1);
      --secondary-color: #a855f7;
      --accent-color: #ec4899;
      --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
      --bg-gradient-light: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4c1d95 100%);
      --bg-gradient-card: linear-gradient(135deg, rgba(30, 27, 75, 0.9) 0%, rgba(49, 46, 129, 0.8) 100%);
      --bg-gradient-hero: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
      --bg-main: linear-gradient(180deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
      --text-color: #f8fafc;
      --text-muted: #cbd5e1;
      --text-bright: #ffffff;
      --card-bg: rgba(30, 27, 75, 0.8);
      --card-bg-hover: rgba(49, 46, 129, 0.9);
      --border-color: rgba(99, 102, 241, 0.3);
      --border-hover: rgba(99, 102, 241, 0.5);
      --shadow-sm: 0 2px 8px rgba(99, 102, 241, 0.1);
      --shadow-md: 0 4px 16px rgba(99, 102, 241, 0.15);
      --shadow-lg: 0 10px 30px rgba(99, 102, 241, 0.2);
      --shadow-xl: 0 20px 50px rgba(99, 102, 241, 0.25);
      --glass-bg: rgba(255, 255, 255, 0.25);
      --glass-border: rgba(255, 255, 255, 0.18);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg-main);
      background-attachment: fixed;
      color: var(--text-color);
      line-height: 1.6;
      scroll-behavior: smooth;
      overflow-x: hidden;
      min-height: 100vh;
    }

    /* Parallax Container */
    .parallax-container {
      position: relative;
      height: 100vh;
      overflow: hidden;
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

    /* Parallax Background Layers */
    .parallax-bg {
      position: absolute;
      top: -20%;
      left: -20%;
      width: 140%;
      height: 140%;
      object-fit: cover;
      z-index: 0;
      will-change: transform;
      transition: transform 0.1s ease-out;
    }

    .parallax-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: var(--bg-gradient-hero);
      opacity: 0.9;
      z-index: 1;
    }

    /* Animated Background Shapes */
    .hero::before {
      content: "";
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(
        circle at 30% 50%,
        rgba(99, 102, 241, 0.3) 0%,
        transparent 50%
      ),
      radial-gradient(
        circle at 70% 80%,
        rgba(139, 92, 246, 0.3) 0%,
        transparent 50%
      );
      z-index: 1;
      animation: rotate 20s linear infinite;
    }

    @keyframes rotate {
      from {
        transform: rotate(0deg);
      }
      to {
        transform: rotate(360deg);
      }
    }

    .hero::after {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      z-index: 1;
      opacity: 0.3;
    }

    .hero-content {
      position: relative;
      z-index: 2;
      color: white;
      padding: 2rem;
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 4rem;
      animation: fadeInUp 1s ease;
    }

    .hero-text {
      flex: 1;
      animation: fadeInLeft 1s ease;
      z-index: 3;
    }

    .hero-image {
      flex: 1;
      display: flex;
      justify-content: center;
      animation: fadeInRight 1s ease;
      position: relative;
      padding: 2rem;
      z-index: 3;
    }

    .hero-image img {
      width: 400px;
      height: auto;
      object-fit: contain;
      position: relative;
      z-index: 2;
      filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.3));
      animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% {
        transform: translateY(0px);
      }
      50% {
        transform: translateY(-20px);
      }
    }

    .hero-image::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
      border-radius: 50%;
      z-index: 1;
      animation: pulse 4s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 0.5;
      }
      50% {
        transform: translate(-50%, -50%) scale(1.1);
        opacity: 0.8;
      }
    }

    .hero-image::after {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 200px;
      height: 200px;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
      border-radius: 50%;
      z-index: 1;
      animation: float 8s ease-in-out infinite reverse;
    }

    .hero h1 {
      font-size: 3.5rem;
      font-weight: 700;
      margin-bottom: 1.5rem;
      line-height: 1.2;
      color: white;
      text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
      animation: slideInDown 1s ease;
    }

    .hero p {
      font-size: 1.2rem;
      margin-bottom: 2rem;
      color: rgba(255, 255, 255, 0.95);
      max-width: 600px;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
      line-height: 1.8;
    }

    .cta-buttons {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
    }

    .button {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #fff;
      border: none;
      padding: 1rem 2.5rem;
      font-size: 1rem;
      border-radius: 50px;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 600;
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
      position: relative;
      overflow: hidden;
    }

    .button::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.3);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }

    .button:hover::before {
      width: 300px;
      height: 300px;
    }

    .button:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
    }

    .button span, .button i {
      position: relative;
      z-index: 1;
    }

    .button.secondary {
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
      border: 2px solid rgba(255, 255, 255, 0.5);
      color: white;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .button.secondary:hover {
      background: rgba(255, 255, 255, 0.3);
      border-color: rgba(255, 255, 255, 0.8);
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
      padding: 8rem 2rem;
      background: transparent;
      position: relative;
    }

    .features::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100px;
      background: linear-gradient(180deg, transparent 0%, rgba(99, 102, 241, 0.05) 100%);
    }

    .features-container {
      max-width: 1200px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2.5rem;
      margin-top: 4rem;
    }

    .feature-card {
      padding: 2.5rem;
      border-radius: 24px;
      background: var(--bg-gradient-card);
      backdrop-filter: blur(10px);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      box-shadow: var(--shadow-md);
      border: 1px solid var(--border-color);
      position: relative;
      overflow: hidden;
    }

    .feature-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.4s ease;
    }

    .feature-card:hover::before {
      transform: scaleX(1);
    }

    .feature-card:hover {
      transform: translateY(-10px) scale(1.02);
      box-shadow: var(--shadow-xl);
      border-color: var(--primary-color);
      background: var(--card-bg-hover);
    }

    .feature-icon {
      font-size: 3rem;
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 1.5rem;
      display: inline-block;
      transition: transform 0.3s ease;
    }

    .feature-card:hover .feature-icon {
      transform: scale(1.1) rotate(5deg);
    }

    .feature-card h3 {
      font-size: 1.5rem;
      margin-bottom: 1rem;
      background: var(--bg-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .feature-card:hover h3 {
      background: var(--bg-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .feature-card p {
      color: var(--text-color);
      line-height: 1.7;
      font-size: 1rem;
      transition: color 0.3s ease;
    }

    .feature-card:hover p {
      color: var(--text-bright);
    }

    .section-title {
      text-align: center;
      margin-bottom: 3rem;
    }

    .section-title h2 {
      font-size: 2.5rem;
      background: var(--bg-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 1rem;
      font-weight: 700;
    }

    .section-title p {
      color: var(--text-muted);
      max-width: 600px;
      margin: 0 auto;
      font-size: 1.1rem;
    }

    /* Stats Section */
    .stats-section {
      padding: 6rem 2rem;
      background: var(--bg-gradient);
      position: relative;
      overflow: hidden;
    }

    .stats-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      opacity: 0.3;
    }

    .stats-container {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 3rem;
      position: relative;
      z-index: 1;
    }

    .stat-item {
      text-align: center;
      color: var(--text-bright);
      padding: 2rem;
      border-radius: 24px;
      background: var(--glass-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      transition: all 0.3s ease;
      box-shadow: 0 8px 32px rgba(99, 102, 241, 0.2);
    }

    .stat-item:hover {
      transform: translateY(-10px);
      background: rgba(99, 102, 241, 0.2);
      backdrop-filter: blur(20px);
      box-shadow: var(--shadow-xl);
      border-color: var(--primary-color);
    }

    .stat-number {
      font-size: 3.5rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
      text-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .stat-label {
      font-size: 1.1rem;
      opacity: 0.95;
      font-weight: 500;
    }

    /* Section Container */
    .section-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 6rem 2rem;
    }

    /* Announcements Section */
    .announcements-section {
      background: transparent;
      padding: 6rem 2rem;
    }

    .announcements-container {
      background: var(--bg-gradient-card);
      backdrop-filter: blur(10px);
      border-radius: 24px;
      box-shadow: var(--shadow-lg);
      padding: 2rem;
      border: 1px solid var(--border-color);
    }

    .announcement-item {
      border-bottom: 1px solid var(--border-color);
      padding: 1.5rem 0;
    }

    .announcement-item:last-child {
      border-bottom: none;
    }

    .announcement-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
    }

    .announcement-author strong {
      background: var(--bg-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      font-size: 1rem;
      font-weight: 600;
    }

    .announcement-date {
      color: var(--text-muted);
      font-size: 0.9rem;
    }

    .announcement-content {
      font-size: 1rem;
      line-height: 1.6;
      color: var(--text-color);
      margin-bottom: 1rem;
    }

    .announcement-image {
      margin-top: 1rem;
    }

    .announcement-image img {
      max-width: 100%;
      height: auto;
      border-radius: 8px;
      cursor: pointer;
      transition: transform 0.3s ease;
    }

    .announcement-image img:hover {
      transform: scale(1.02);
    }

    .empty-announcements {
      text-align: center;
      padding: 2rem;
      color: var(--text-muted);
    }

    /* About Section */
    .about-section {
      background: transparent;
      padding: 6rem 2rem;
    }

    .about-hero {
      text-align: center;
      margin-bottom: 4rem;
      padding: 3rem 2rem;
      background: var(--bg-gradient-hero);
      border-radius: 24px;
      box-shadow: var(--shadow-xl);
      position: relative;
      overflow: hidden;
    }

    .about-hero::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
      animation: rotate 20s linear infinite;
    }

    .about-hero h1 {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 1rem;
      color: var(--text-bright);
    }

    .about-hero p {
      font-size: 1.2rem;
      color: rgba(255, 255, 255, 0.9);
      max-width: 800px;
      margin: 0 auto;
      line-height: 1.8;
    }

    .contact-info {
      display: flex;
      justify-content: center;
      gap: 2rem;
      margin-bottom: 3rem;
      flex-wrap: wrap;
    }

    .info-card {
      background: var(--bg-gradient-card);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 20px;
      text-align: center;
      min-width: 250px;
      color: var(--text-color);
      box-shadow: var(--shadow-md);
      border: 1px solid var(--border-color);
      transition: all 0.3s ease;
    }

    .info-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
      border-color: var(--primary-color);
      background: var(--card-bg-hover);
    }

    .info-card:hover h3 {
      background: var(--bg-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .info-card:hover p,
    .info-card:hover a {
      color: var(--text-bright);
    }

    .info-icon {
      font-size: 2rem;
      margin-bottom: 1rem;
      color: var(--primary-color);
    }

    .info-card h3 {
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      background: var(--bg-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .info-card p, .info-card a {
      font-size: 1rem;
      color: var(--text-muted);
      text-decoration: none;
      transition: color 0.3s ease;
    }

    .info-card a:hover {
      color: var(--primary-color);
    }

    .sponsor-card {
      background: var(--bg-gradient-card);
      backdrop-filter: blur(10px);
      padding: 2.5rem;
      border-radius: 24px;
      box-shadow: var(--shadow-lg);
      text-align: center;
      margin-bottom: 3rem;
      border: 1px solid var(--border-color);
    }

    .sponsor-card h3 {
      font-size: 1.8rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      background: var(--bg-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .sponsor-card .avatar {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      margin: 0 auto 1.5rem;
      overflow: hidden;
      border: 3px solid var(--primary-color);
    }

    .sponsor-card .avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .sponsor-card p {
      color: var(--text-muted);
      line-height: 1.7;
      font-size: 1rem;
    }

    .team-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
      margin-top: 2rem;
    }

    .team-member {
      background: var(--bg-gradient-card);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 24px;
      box-shadow: var(--shadow-md);
      text-align: center;
      transition: all 0.3s ease;
      border: 1px solid var(--border-color);
    }

    .team-member:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
      border-color: var(--primary-color);
      background: var(--card-bg-hover);
    }

    .team-member:hover h3 {
      background: var(--bg-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .team-member:hover p {
      color: var(--text-bright);
    }

    .team-member .avatar {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      margin: 0 auto 1rem;
      overflow: hidden;
      border: 3px solid var(--primary-color);
    }

    .team-member .avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .team-member h3 {
      font-size: 1.5rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      background: var(--bg-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .team-member p {
      font-size: 1rem;
      color: var(--text-muted);
      line-height: 1.6;
    }

    /* Procedure Section */
    .procedure-section {
      background: transparent;
      padding: 6rem 2rem;
    }

    .procedure-section .section-title {
      text-align: center;
      margin-bottom: 4rem;
      padding: 2rem;
      background: var(--bg-gradient-hero);
      border-radius: 24px;
      box-shadow: var(--shadow-xl);
      position: relative;
      overflow: hidden;
    }

    .procedure-section .section-title::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
      animation: rotate 20s linear infinite;
    }

    .procedure-section .section-title h1 {
      font-size: 2.8rem;
      font-weight: 700;
      margin-bottom: 1rem;
      color: var(--text-bright);
      position: relative;
      z-index: 1;
    }

    .procedure-section .section-title p {
      font-size: 1.2rem;
      color: rgba(255, 255, 255, 0.9);
    }

    .procedure-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
    }

    .procedure-step {
      background: var(--bg-gradient-card);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 24px;
      box-shadow: var(--shadow-md);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
      border: 1px solid var(--border-color);
    }

    .procedure-step:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
      border-color: var(--primary-color);
      background: var(--card-bg-hover);
    }

    .procedure-step:hover .step-content h3 {
      background: var(--bg-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .procedure-step:hover .step-content p {
      color: var(--text-bright);
    }

    .step-number {
      width: 60px;
      height: 60px;
      background: var(--bg-gradient);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      font-weight: 600;
      margin-bottom: 1rem;
      box-shadow: var(--shadow-md);
    }

    .step-content h3 {
      font-size: 1.4rem;
      font-weight: 600;
      background: var(--bg-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .step-content h3 i {
      color: var(--primary-color);
      font-size: 1.2rem;
    }

    .step-content p {
      color: var(--text-muted);
      margin-bottom: 1.5rem;
      font-size: 1rem;
    }

    .step-content ul {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .step-content ul li {
      color: var(--text-muted);
      margin-bottom: 0.8rem;
      padding-left: 1.5rem;
      position: relative;
      font-size: 0.95rem;
    }

    .step-content ul li::before {
      content: '→';
      color: var(--primary-color);
      position: absolute;
      left: 0;
      font-weight: bold;
    }

    .important-note {
      background: var(--primary-light);
      border-left: 4px solid var(--primary-color);
      padding: 1.2rem;
      border-radius: 0 8px 8px 0;
      margin-top: auto;
    }

    .important-note p {
      color: var(--primary-color);
      font-weight: 500;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.9rem;
    }

    /* Image Modal */
    .image-modal {
      display: none;
      position: fixed;
      z-index: 10000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.95);
      overflow: auto;
    }

    .image-modal-content {
      display: block;
      margin: auto;
      max-width: 90%;
      max-height: 90%;
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
    }

    .image-modal-close {
      position: absolute;
      top: 20px;
      right: 35px;
      color: #f8fafc;
      font-size: 40px;
      font-weight: bold;
      cursor: pointer;
      z-index: 10001;
    }

    .image-modal-close:hover {
      color: #cbd5e1;
    }

    @keyframes fadeInLeft {
      from {
        opacity: 0;
        transform: translateX(-30px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes fadeInRight {
      from {
        opacity: 0;
        transform: translateX(30px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes slideInDown {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
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

  <section class="hero parallax-container">
    <img src="./images/bg.jpg" alt="Background" class="parallax-bg" id="parallax-bg">
    <div class="parallax-overlay"></div>
    <div class="hero-content">
      <div class="hero-text">
        <h1>The Bridge Towards your Dreams</h1>
        <p>Welcome to iSCHO, your comprehensive Scholarship Application Portal. We simplify the scholarship process, making it easier for deserving students to access educational opportunities and achieve their academic dreams.</p>
        <div class="cta-buttons">
          <a href="login.php" class="button">
            <i class="fas fa-user-plus"></i>
            <span>Get Started</span>
          </a>
          <a href="about_us.php" class="button secondary">
            <i class="fas fa-info-circle"></i>
            <span>Learn More</span>
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
          <p>Simple and intuitive online application process with step-by-step guidance. Complete your scholarship application in minutes, not hours.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-clock"></i>
          </div>
          <h3>Real-time Updates</h3>
          <p>Stay informed about your application status with instant notifications. Track your progress every step of the way.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-shield-alt"></i>
          </div>
          <h3>Secure Platform</h3>
          <p>Your data is protected with advanced security measures and encryption. We prioritize your privacy and data safety.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-file-alt"></i>
          </div>
          <h3>Document Management</h3>
          <p>Upload and manage all your required documents in one place. Our OCR technology makes verification seamless.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <h3>Track Progress</h3>
          <p>Monitor your application journey with detailed analytics and progress tracking. Know exactly where you stand.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-headset"></i>
          </div>
          <h3>24/7 Support</h3>
          <p>Get help whenever you need it. Our AI-powered chatbot and support team are always ready to assist you.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Stats Section -->
  <section class="stats-section">
    <div class="stats-container">
      <div class="stat-item">
        <div class="stat-number" data-target="1000">0</div>
        <div class="stat-label">Active Applicants</div>
      </div>
      <div class="stat-item">
        <div class="stat-number" data-target="500">0</div>
        <div class="stat-label">Scholarships Awarded</div>
      </div>
      <div class="stat-item">
        <div class="stat-number" data-target="95">0</div>
        <div class="stat-label">Success Rate %</div>
      </div>
      <div class="stat-item">
        <div class="stat-number" data-target="24">0</div>
        <div class="stat-label">Hours Support</div>
      </div>
    </div>
  </section>

  <!-- Announcements Section -->
  <section class="announcements-section" id="announcements">
    <div class="section-container">
      <div class="section-title">
        <h2>Announcements</h2>
        <p>Stay updated with the latest announcements from the administration</p>
      </div>
      <div class="announcements-container">
        <?php if (isset($error)): ?>
          <div class="empty-announcements">
            <p><?php echo htmlspecialchars($error); ?></p>
          </div>
        <?php elseif (empty($announcements)): ?>
          <div class="empty-announcements">
            <p>No announcements available at the moment.</p>
          </div>
        <?php else: ?>
          <?php foreach ($announcements as $announcement): ?>
            <div class="announcement-item">
              <div class="announcement-header">
                <div class="announcement-author">
                  <strong><?php echo htmlspecialchars($announcement['firstname'] . ' ' . $announcement['lastname']); ?></strong>
                </div>
                <div class="announcement-date">
                  <?php echo date('M d, Y \a\t g:i A', strtotime($announcement['created_at'])); ?>
                </div>
              </div>
              <div class="announcement-content">
                <?php echo nl2br(htmlspecialchars($announcement['message'])); ?>
              </div>
              <?php if (!empty($announcement['image_path']) && file_exists($announcement['image_path'])): ?>
                <div class="announcement-image">
                  <img src="<?php echo htmlspecialchars($announcement['image_path']); ?>" alt="Announcement Image" onclick="openImageModal('<?php echo htmlspecialchars($announcement['image_path']); ?>')">
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- About Us Section -->
  <section class="about-section" id="about">
    <div class="section-container">
      <div class="about-hero">
        <h1>About Us</h1>
        <p>iSCHO (Integrated Scholarship Application Portal) is your one-stop scholarship application portal designed to simplify the scholarship process. It empowers students to easily apply for and manage scholarship opportunities through a user-friendly interface and seamless navigation, connecting you to your educational dreams—hassle-free.</p>
      </div>
      
      <div class="contact-info">
        <div class="info-card">
          <div class="info-icon"><i class="fas fa-code"></i></div>
          <h3>Developed by</h3>
          <p>iSCHO Team</p>
        </div>
        <div class="info-card">
          <div class="info-icon"><i class="fas fa-envelope"></i></div>
          <h3>Contact Us, Edukalinga</h3>
          <a href="mailto:edukalinga@gmail.com">edukalinga@gmail.com</a>
        </div>
      </div>

      <div class="sponsor-card">
        <h3>Our Sponsor</h3>
        <div class="avatar">
          <img src="./images/khonghun.jpg" alt="Edukalinga Logo">
        </div>
        <h3>Edukalinga by Cong. Khonghun</h3>
        <p>Edukalinga is a scholarship program initiated by Hon. Khonghun aimed at providing educational support to deserving students in the region. The program is committed to empowering the youth through education and creating opportunities for academic excellence.</p>
      </div>

      <div class="team-grid">
        <div class="team-member">
          <div class="avatar">
            <img src="./images/lacuesta.jpg" alt="Lacuesta, Hans Marcus Roberto V.">
          </div>
          <h3>Lacuesta, Hans Marcus Roberto V.</h3>
          <p>I am Lacuesta, Hans Marcus Roberto V., a passionate Information Technology student and aspiring software developer. I believe in the power of technology to create efficient, user-centered systems that solve everyday problems. With iSCHO, I aspire to contribute to digital transformation in educational institutions by simplifying scholarship management and improving communication between students and administrators.</p>
        </div>
        <div class="team-member">
          <div class="avatar">
            <img src="./images/gonzales.jpg" alt="Gonzales, Icon Zeus R.">
          </div>
          <h3>Gonzales, Icon Zeus R.</h3>
          <p>I am Icon Zeus R. Gonzales, an Information Technology student and an aspiring developer dedicated to crafting innovative solutions that can positively impact our community. My passion for technology drives me to continuously learn and build systems that address real-world challenges. Through the development of iSCHO, I aim to help streamline scholarship applications and make financial aid more accessible to students.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Procedure Section -->
  <section class="procedure-section" id="procedure">
    <div class="section-container">
      <div class="section-title">
        <h1>Application Procedure</h1>
        <p>Follow these simple steps to complete your scholarship application</p>
      </div>
      <div class="procedure-grid">
        <div class="procedure-step">
          <div class="step-number">1</div>
          <div class="step-content">
            <h3><i class="fas fa-user-plus"></i>Create an Account</h3>
            <p>Begin your journey by creating your iSCHO account</p>
            <ul>
              <li>Valid email address</li>
              <li>Strong password</li>
              <li>Basic personal information</li>
            </ul>
          </div>
        </div>
        <div class="procedure-step">
          <div class="step-number">2</div>
          <div class="step-content">
            <h3><i class="fas fa-id-card"></i>Complete Personal Information</h3>
            <p>Tell us more about yourself</p>
            <ul>
              <li>Full name and contact details</li>
              <li>Date of birth and nationality</li>
              <li>Current address and emergency contacts</li>
            </ul>
          </div>
        </div>
        <div class="procedure-step">
          <div class="step-number">3</div>
          <div class="step-content">
            <h3><i class="fas fa-graduation-cap"></i>Educational Background</h3>
            <p>Share your academic journey</p>
            <ul>
              <li>Current school/college enrollment</li>
              <li>Academic achievements</li>
              <li>Relevant certifications</li>
            </ul>
          </div>
        </div>
        <div class="procedure-step">
          <div class="step-number">4</div>
          <div class="step-content">
            <h3><i class="fas fa-users"></i>Family Information</h3>
            <p>Help us understand your family background</p>
            <ul>
              <li>Parents' or guardians' details</li>
              <li>Family income information</li>
              <li>Number of dependents</li>
            </ul>
          </div>
        </div>
        <div class="procedure-step">
          <div class="step-number">5</div>
          <div class="step-content">
            <h3><i class="fas fa-file-upload"></i>Required Documents</h3>
            <p>Upload all necessary documentation</p>
            <ul>
              <li>COR (Certificate of Registration)</li>
              <li>Barangay Indigency</li>
              <li>Voter's ID/Certificate</li>
              <li>Recent passport-sized photo</li>
            </ul>
            <div class="important-note">
              <p><i class="fas fa-info-circle"></i>Documents must be clear and in PDF, JPG, or PNG format</p>
            </div>
          </div>
        </div>
        <div class="procedure-step">
          <div class="step-number">6</div>
          <div class="step-content">
            <h3><i class="fas fa-check-circle"></i>Review and Submit</h3>
            <p>Final check before submission</p>
            <ul>
              <li>Verify all information</li>
              <li>Check document uploads</li>
              <li>Confirm contact details</li>
            </ul>
            <div class="important-note">
              <p><i class="fas fa-exclamation-circle"></i>Applications can be edited until the deadline</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Image Modal -->
  <div id="imageModal" class="image-modal" onclick="closeImageModal(event)">
    <span class="image-modal-close" onclick="closeImageModal(event)">&times;</span>
    <img class="image-modal-content" id="modalImage">
  </div>
</body>
</html>
<script>
  // Parallax Scrolling Effect
  window.addEventListener('scroll', function() {
    const scrolled = window.pageYOffset;
    const parallaxBg = document.getElementById('parallax-bg');
    const hero = document.querySelector('.hero');
    
    if (parallaxBg && hero) {
      const heroHeight = hero.offsetHeight;
      const scrollRatio = scrolled / heroHeight;
      
      // Parallax effect for background image
      if (scrolled < heroHeight) {
        parallaxBg.style.transform = `translateY(${scrolled * 0.5}px) scale(1.1)`;
      }
      
      // Fade out hero content as user scrolls
      const heroContent = document.querySelector('.hero-content');
      if (heroContent) {
        const opacity = Math.max(0, 1 - scrollRatio * 2);
        heroContent.style.opacity = opacity;
      }
    }
  });

  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });

  // Intersection Observer for fade-in animations
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
      }
    });
  }, observerOptions);

  // Observe feature cards
  document.querySelectorAll('.feature-card').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(30px)';
    card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(card);
  });

  // Counter Animation
  function animateCounter(element, target, duration = 2000) {
    const start = 0;
    const increment = target / (duration / 16);
    let current = start;

    const updateCounter = () => {
      current += increment;
      if (current < target) {
        element.textContent = Math.floor(current);
        requestAnimationFrame(updateCounter);
      } else {
        element.textContent = target;
      }
    };

    updateCounter();
  }

  // Observe stats section for counter animation
  const statsObserver = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const statNumbers = entry.target.querySelectorAll('.stat-number');
        statNumbers.forEach(stat => {
          const target = parseInt(stat.getAttribute('data-target'));
          if (!stat.classList.contains('counted')) {
            stat.classList.add('counted');
            animateCounter(stat, target);
          }
        });
      }
    });
  }, { threshold: 0.5 });

  const statsSection = document.querySelector('.stats-section');
  if (statsSection) {
    statsObserver.observe(statsSection);
  }

  // Image Modal Functions
  function openImageModal(imageSrc) {
    document.getElementById('imageModal').style.display = 'block';
    document.getElementById('modalImage').src = imageSrc;
    document.body.style.overflow = 'hidden';
  }

  function closeImageModal(event) {
    if (event.target.classList.contains('image-modal') || event.target.classList.contains('image-modal-close')) {
      document.getElementById('imageModal').style.display = 'none';
      document.body.style.overflow = 'auto';
    }
  }

  // Close modal with ESC key
  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && document.getElementById('imageModal').style.display === 'block') {
      document.getElementById('imageModal').style.display = 'none';
      document.body.style.overflow = 'auto';
    }
  });

  // Real-time updates for announcements
  function initializeRealTimeUpdates() {
    setInterval(updateAnnouncements, 10000);
  }

  function updateAnnouncements() {
    fetch('ajax_public_updates.php?action=get_announcements')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const container = document.querySelector('.announcements-container');
          if (container) {
            if (data.announcements.length === 0) {
              container.innerHTML = '<div class="empty-announcements"><p>No announcements available at the moment.</p></div>';
            } else {
              let announcementsHTML = '';
              data.announcements.forEach(announcement => {
                announcementsHTML += `
                  <div class="announcement-item">
                    <div class="announcement-header">
                      <div class="announcement-author">
                        <strong>${escapeHtml(announcement.firstname + ' ' + announcement.lastname)}</strong>
                      </div>
                      <div class="announcement-date">
                        ${formatDate(announcement.created_at)}
                      </div>
                    </div>
                    <div class="announcement-content">
                      ${escapeHtml(announcement.message).replace(/\n/g, '<br>')}
                    </div>
                    ${announcement.image_path ? `
                      <div class="announcement-image">
                        <img src="${escapeHtml(announcement.image_path)}" alt="Announcement Image" onclick="openImageModal('${escapeHtml(announcement.image_path)}')">
                      </div>
                    ` : ''}
                  </div>
                `;
              });
              container.innerHTML = announcementsHTML;
            }
          }
        }
      })
      .catch(error => console.error('Error updating announcements:', error));
  }

  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>'"]/g, m => map[m]);
  }

  function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  // Start real-time updates
  document.addEventListener('DOMContentLoaded', function() {
    initializeRealTimeUpdates();
  });

  // Service Worker Registration
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('/service-worker.js')
        .then(function(registration) {
          console.log('ServiceWorker registration successful with scope: ', registration.scope);
        }, function(err) {
          console.log('ServiceWorker registration failed: ', err);
        });
    });
  }
</script>
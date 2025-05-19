<?php
// about_us.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="./images/logo1.png">
    <style>
        :root {
            --primary-color: #007bff; 
            --text-color: #333; 
            --text-muted: #6b7280;
            --background-color: #f9fafb;
            --card-bg: #ffffff;
            --gradient-start: #007bff;
            --gradient-end: #0056b3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-color);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 5rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            opacity: 0.5;
        }

        .hero-section h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
            animation: fadeInDown 1s ease-out;
        }

        .hero-section p {
            font-size: 1.2rem;
            font-weight: 300;
            max-width: 700px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            animation: fadeInUp 1s ease-out 0.3s;
            animation-fill-mode: backwards;
        }

        /* Team Section */
        .team-section {
            padding: 3rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .team-section h2 {
            font-size: 2.5rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 3rem;
            color: var(--text-color);
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .team-member {
            background-color: var(--card-bg);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .team-member:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .team-member .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
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
            color: var(--text-color);
        }

        .team-member p {
            font-size: 1rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2rem;
            }

            .hero-section p {
                font-size: 1rem;
            }

            .team-section h2 {
                font-size: 2rem;
            }

            .team-member .avatar {
                width: 100px;
                height: 100px;
            }
        }

        @media (max-width: 576px) {
            .hero-section {
                padding: 3rem 1rem;
            }

            .team-section {
                padding: 2rem 1rem;
            }

            .team-section h2 {
                font-size: 1.5rem;
            }

            .team-member h3 {
                font-size: 1.25rem;
            }

            .team-member p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Include the Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Hero Section -->
    <div class="hero-section">
        <h1>About Us</h1>
        <p>
        We are Icon Zeus Gonzales and Alan Michael Batac, Gordon College IT students and aspiring developers passionate about building digital solutions that address real-world problems. Our project, iSCHO: Integrated Scholarship Application & Management Portal, is designed to streamline and enhance the scholarship application process for both students and administrators through a secure, user-friendly, and efficient online platform.
        </p>
    </div>

    <!-- Team Section -->
    <div class="team-section">
        <h2>Meet Us</h2>
        <div class="team-grid">
            <!-- Batac, Alan Michael -->
            <div class="team-member">
                <div class="avatar">
                    <img src="./images/batac.png" alt="Batac, Alan Michael">
                </div>
                <h3>Batac, Alan Michael</h3>
                <p>
                I am Alan Michael Batac, a passionate Information Technology student and aspiring software developer. I believe in the power of technology to create efficient, user-centered systems that solve everyday problems. With iSCHO, I aspire to contribute to digital transformation in educational institutions by simplifying scholarship management and improving communication between students and administrators.
                </p>
            </div>

            <!-- Gonzales, Icon Zeus R. -->
            <div class="team-member">
                <div class="avatar">
                    <img src="./images/gonzales.jpg" alt="Gonzales, Icon Zeus R.">
                </div>
                <h3>Gonzales, Icon Zeus R.</h3>
                <p>
                I am Icon Zeus R. Gonzales, an Information Technology student and an aspiring developer dedicated to crafting innovative solutions that can positively impact our community. My passion for technology drives me to continuously learn and build systems that address real-world challenges. Through the development of iSCHO, I aim to help streamline scholarship applications and make financial aid more accessible to students.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
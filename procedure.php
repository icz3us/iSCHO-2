<?php
// Include the navbar.php file
$navbar_file = 'navbar.php';
if (!file_exists($navbar_file)) {
    $navbar_content = "<nav style='background-color: #4f46e5; padding: 1rem 0; width: 100%;'><div style='max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 0 2rem;'><a href='index.php' style='color: white; font-size: 1.5rem; font-weight: 600; text-decoration: none;'>iSCHO</a><ul style='list-style: none; display: flex; gap: 1.5rem; margin: 0; padding: 0;'><li><a href='index.php' style='color: white; text-decoration: none; font-size: 1rem;'>Home</a></li><li><a href='about_us.php' style='color: white; text-decoration: none; font-size: 1rem;'>About Us</a></li><li><a href='procedure.php' style='color: white; text-decoration: none; font-size: 1rem;'>Procedure</a></li><li><a href='login.php' style='color: white; text-decoration: none; font-size: 1rem;'>Login</a></li></ul></div></nav>";
} else {
    ob_start();
    include $navbar_file;
    $navbar_content = ob_get_clean();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Procedure - iSCHO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="./images/logo1.png">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-light: rgba(79, 70, 229, 0.1);
            --text-color: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --card-bg: #ffffff;
            --gradient-start: #4f46e5;
            --gradient-end: #6366f1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f9fafb;
            color: var(--text-color);
            line-height: 1.6;
            margin: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 4rem;
            padding: 2rem;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 20px;
            color: white;
            box-shadow: 0 10px 30px rgba(79, 70, 229, 0.2);
        }

        .header h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: white;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        .procedure-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            padding: 0;
            background: transparent;
            box-shadow: none;
        }

        .procedure-step {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .procedure-step:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
        }

        .step-content h3 {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--text-color);
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

        .important-note i {
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 2rem 1rem;
            }

            .header {
                padding: 1.5rem;
                margin-bottom: 2rem;
            }

            .header h1 {
                font-size: 2rem;
            }

            .header p {
                font-size: 1rem;
            }

            .procedure-section {
                grid-template-columns: 1fr;
            }

            .procedure-step {
                padding: 1.5rem;
            }
        }

        /* Animation classes */
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

        .animate {
            animation: fadeInUp 0.6s ease-out forwards;
            opacity: 0;
        }

        .delay-1 { animation-delay: 0.2s; }
        .delay-2 { animation-delay: 0.4s; }
        .delay-3 { animation-delay: 0.6s; }
        .delay-4 { animation-delay: 0.8s; }
        .delay-5 { animation-delay: 1s; }
        .delay-6 { animation-delay: 1.2s; }
    </style>
</head>
<body>
    <?php echo $navbar_content; ?>
    
    <div class="container">
        <div class="header animate">
            <h1>Application Procedure</h1>
            <p>Follow these simple steps to complete your scholarship application</p>
        </div>

        <div class="procedure-section">
            <div class="procedure-step animate delay-1">
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

            <div class="procedure-step animate delay-2">
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

            <div class="procedure-step animate delay-3">
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

            <div class="procedure-step animate delay-4">
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

            <div class="procedure-step animate delay-5">
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

            <div class="procedure-step animate delay-6">
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

<script>
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.animate');
            elements.forEach(element => {
                element.style.opacity = '0';
            });

            function checkScroll() {
                elements.forEach(element => {
                    const elementTop = element.getBoundingClientRect().top;
                    const elementVisible = 150;
                    
                    if (elementTop < window.innerHeight - elementVisible) {
                        element.style.opacity = '1';
                    }
                });
            }

            window.addEventListener('scroll', checkScroll);
            checkScroll();
});
</script>
</body>
</html>
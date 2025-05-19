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
            --text-color: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --card-bg: #ffffff;
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
            padding: 2rem 0;
        }

        nav {
            width: 100%;
            background-color: var(--primary-color);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        nav .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-size: 1rem;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 1.5rem;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            padding: 0;
        }

        .header h1 {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            width: 100%;
        }

        .header p {
            color: var(--text-muted);
            font-size: 1.1rem;
            max-width: 600px;
            width: 100%;
        }

        .procedure-section {
            background-color: var(--card-bg);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .procedure-step {
            display: flex;
            margin-bottom: 2rem;
            position: relative;
        }

        .procedure-step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 25px;
            top: 50px;
            bottom: -2rem;
            width: 2px;
            background-color: var(--border-color);
        }

        .step-number {
            width: 50px;
            height: 50px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 600;
            margin-right: 1.5rem;
            flex-shrink: 0;
        }

        .step-content {
            flex: 1;
        }

        .step-content h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        .step-content p {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .step-content ul {
            list-style-type: disc;
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }

        .step-content ul li {
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            line-height: 1.6;
        }

        .important-note {
            background-color: rgba(79, 70, 229, 0.1);
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 0 8px 8px 0;
        }

        .important-note p {
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem 0;
            }

            nav .nav-container {
                padding: 0 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            nav ul {
                flex-direction: column;
                align-items: center;
            }

            .header h1 {
                font-size: 2rem;
            }

            .procedure-step {
                flex-direction: column;
            }

            .step-number {
                margin-bottom: 1rem;
                margin-right: 0;
            }

            .procedure-step:not(:last-child)::after {
                display: none; 
            }

            .step-content {
                margin-top: 0;
            }

            .step-content ul {
                margin-left: 1.5rem;
                padding-left: 0;
            }
        }

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
        .header.animated {
            animation: fadeInDown 1s ease-out;
        }
        .procedure-section.animated {
            animation: fadeInUp 1s ease-out 0.3s;
            animation-fill-mode: backwards;
        }
    </style>
</head>
<body>
    <?php echo $navbar_content; ?>
    <div class="container">
        <div class="header">
            <h1>Application Procedure</h1>
            <p>A step-by-step guide to complete your scholarship application</p>
        </div>

        <div class="procedure-section">
            <div class="procedure-step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Create an Account</h3>
                    <p>Begin by creating your iSCHO account. You'll need to provide:</p>
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
                    <h3>Complete Personal Information</h3>
                    <p>Fill out your personal details including:</p>
                    <ul>
                        <li>Full name and contact information</li>
                        <li>Date of birth and nationality</li>
                        <li>Current address and emergency contacts</li>
                    </ul>
                </div>
            </div>

            <div class="procedure-step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Educational Background</h3>
                    <p>Provide details about your educational history:</p>
                    <ul>
                        <li>School/College currently enrolled in</li>
                        <li>Relevant certificates and qualifications</li>
                    </ul>
                </div>
            </div>

            <div class="procedure-step">
                <div class="step-number">4</div>
                <div class="step-content">
                    <h3>Family Information</h3>
                    <p>Submit information about your family:</p>
                    <ul>
                        <li>Parents' or guardians' details</li>
                        <li>Parents' Information</li>
                    </ul>
                </div>
            </div>

            <div class="procedure-step">
                <div class="step-number">5</div>
                <div class="step-content">
                    <h3>Required Documents</h3>
                    <p>Upload the following documents:</p>
                    <ul>
                        <li>COR (Certificate of Registration)</li>
                        <li>Barangay Indigency</li>
                        <li>Voter's ID/Certificate</li>
                        <li>Recent passport-sized photo</li>
                    </ul>
                    <div class="important-note">
                        <p><i class="fas fa-info-circle"></i> All documents must be clear and legible. Accepted formats: PDF, JPG, PNG</p>
                    </div>
                </div>
            </div>

            <div class="procedure-step">
                <div class="step-number">6</div>
                <div class="step-content">
                    <h3>Review and Submit</h3>
                    <p>Before final submission:</p>
                    <ul>
                        <li>Review all entered information</li>
                        <li>Ensure all required documents are uploaded</li>
                        <li>Verify contact information is correct</li>
                    </ul>
                    <div class="important-note">
                        <p><i class="fas fa-exclamation-circle"></i> Once submitted, you can still edit your application until the deadline. Please review carefully.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelector('.header').classList.add('animated');
    document.querySelector('.procedure-section').classList.add('animated');
});
</script>
</html>
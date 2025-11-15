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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Integrated Scholarship Application Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="./images/logo1.png">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --bg-color: #f9fafb;
            --card-bg: #ffffff;
            --text-color: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --error-color: #ef4444;
            --success-color: #22c55e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .header p {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .announcements-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 2rem;
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

        .announcement-date {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .announcement-content {
            font-size: 1rem;
            line-height: 1.6;
            color: var(--text-color);
        }

        .announcement-image {
            margin-top: 1rem;
        }

        .announcement-image img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .empty-announcements {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }

        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .announcements-container {
                padding: 1rem;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
        }
        /* Image Modal Styles */
    .image-modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
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
        color: #f1f1f1;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
        z-index: 10001;
    }
    
    .image-modal-close:hover {
        color: #bbb;
    }
    </style>
    
    
</head>
<body>
    <?php include('navbar.php'); ?>
    
    <div class="container">
        <a href="home.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
        
        <div class="header">
            <h1>Announcements</h1>
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
                            <div class="announcement-image" style="margin-top: 1rem;">
                                <img src="<?php echo htmlspecialchars($announcement['image_path']); ?>" alt="Announcement Image" style="max-width: 100%; height: auto; border-radius: 8px; cursor: pointer;" onclick="openImageModal('<?php echo htmlspecialchars($announcement['image_path']); ?>')">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- Image Modal -->
    <div id="imageModal" class="image-modal" onclick="closeImageModal(event)">
        <span class="image-modal-close" onclick="closeImageModal(event)">&times;</span>
        <img class="image-modal-content" id="modalImage">
    </div>

    <script>
    function openImageModal(imageSrc) {
        document.getElementById('imageModal').style.display = 'block';
        document.getElementById('modalImage').src = imageSrc;
        document.body.style.overflow = 'hidden';
    }
    
    function closeImageModal(event) {
        // Close only if clicking on the background or the close button
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
    </script>
</body>
</html>
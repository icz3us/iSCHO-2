<?php
/**
 * OCR FEATURE - INTERACTIVE TESTING PAGE
 * 
 * This page provides an interactive guide to test the OCR feature
 * in the iSCHO scholarship application.
 * 
 * How to use:
 * 1. Open this file in your browser: http://localhost/ischo2/OCR_TEST.php
 * 2. Follow the step-by-step instructions
 * 3. Check the console for debug messages (F12 > Console)
 */

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header('Location: login.php');
    exit;
}

// Get a test document from the database
require_once 'config.php';

$test_documents = [];
try {
    $stmt = $pdo->prepare("
        SELECT document_id, document_name, document_path, document_type 
        FROM submitted_documents 
        LIMIT 5
    ");
    $stmt->execute();
    $test_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $test_documents = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Feature - Testing Guide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .content {
            padding: 2rem;
        }

        .section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f9fafb;
            border-left: 4px solid #4f46e5;
            border-radius: 8px;
        }

        .section h2 {
            color: #1f2937;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section p {
            color: #4b5563;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .step-list {
            list-style: none;
            counter-reset: step-counter;
        }

        .step-list li {
            counter-increment: step-counter;
            margin-bottom: 1rem;
            padding-left: 2.5rem;
            position: relative;
            color: #374151;
            line-height: 1.6;
        }

        .step-list li:before {
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 0;
            background: #4f46e5;
            color: white;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .code-block {
            background: #1f2937;
            color: #f3f4f6;
            padding: 1rem;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            margin: 1rem 0;
        }

        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            color: #92400e;
        }

        .info-box {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            color: #1e40af;
        }

        .success-box {
            background: #dcfce7;
            border-left: 4px solid #22c55e;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            color: #166534;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #1f2937;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .document-list {
            margin-top: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
        }

        .document-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .document-item:last-child {
            border-bottom: none;
        }

        .document-info {
            flex: 1;
        }

        .document-name {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .document-type {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .console-output {
            background: #1f2937;
            color: #10b981;
            padding: 1rem;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 1rem;
        }

        .console-line {
            margin-bottom: 0.5rem;
            word-break: break-word;
        }

        .console-error {
            color: #ef4444;
        }

        .console-warn {
            color: #f59e0b;
        }

        .console-success {
            color: #10b981;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.875rem;
            }

            .document-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-microchip"></i>
                OCR Feature Testing
            </h1>
            <p>Step-by-step guide to verify your OCR implementation</p>
        </div>

        <div class="content">
            <!-- Overview Section -->
            <div class="section">
                <h2><i class="fas fa-info-circle"></i> Overview</h2>
                <p>
                    The OCR (Optical Character Recognition) feature allows you to automatically extract text from document images. 
                    This guide will help you test and verify that the feature is working correctly.
                </p>
                <div class="info-box">
                    <strong><i class="fas fa-lightbulb"></i> Tip:</strong> Open your browser's Developer Console (F12 > Console tab) to see debug messages while testing.
                </div>
            </div>

            <!-- Prerequisites Section -->
            <div class="section">
                <h2><i class="fas fa-checklist"></i> Prerequisites</h2>
                <p>Before testing, make sure you have:</p>
                <ul class="step-list">
                    <li>A valid Admin or Superadmin account (you already have this!)</li>
                    <li>A document uploaded in the system to test with</li>
                    <li>A stable internet connection (Tesseract downloads ~50MB on first run)</li>
                    <li>JavaScript enabled in your browser</li>
                    <li>Pop-ups not blocked by your browser</li>
                </ul>
            </div>

            <!-- Step-by-Step Testing -->
            <div class="section">
                <h2><i class="fas fa-tasks"></i> Testing Procedure</h2>
                
                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #374151;">Step 1: Navigate to Admin Dashboard</h3>
                <p>
                    Go to your Admin Dashboard and look for a list of documents or claims that have been submitted.
                    You'll need to have a document available to test the OCR feature.
                </p>

                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #374151;">Step 2: Open a Document</h3>
                <p>
                    Click on any document in the list to open it. A preview modal should appear showing:
                </p>
                <ul class="step-list">
                    <li>Document preview in the center</li>
                    <li>Document title at the top</li>
                    <li>A purple <strong>"Extract Text"</strong> button (with microchip icon) in the top-right corner</li>
                </ul>
                <div class="warning-box">
                    <strong><i class="fas fa-exclamation-triangle"></i> If you don't see the button:</strong> Make sure the document is fully loaded. Check browser console (F12) for errors.
                </div>

                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #374151;">Step 3: Click "Extract Text" Button</h3>
                <p>
                    Click the purple "Extract Text" button. A new modal should appear:
                </p>
                <ul class="step-list">
                    <li>A semi-transparent black overlay appears behind the modal</li>
                    <li>A white box appears in the center of the screen</li>
                    <li>The modal is titled "Document Text Extraction (OCR)"</li>
                    <li>A purple <strong>"Extract Text with OCR"</strong> button appears in the center</li>
                </ul>
                <div class="success-box">
                    <strong><i class="fas fa-check-circle"></i> Expected Output:</strong> Modal centered on screen with visible button and proper styling.
                </div>

                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #374151;">Step 4: Click "Extract Text with OCR" Button</h3>
                <p>
                    Click the purple "Extract Text with OCR" button to start the extraction process:
                </p>
                <ul class="step-list">
                    <li>The button should disappear</li>
                    <li>A loading spinner should appear</li>
                    <li>Text "Extracting text from document..." should display</li>
                    <li>Wait for processing (may take 10-60 seconds on first run)</li>
                </ul>
                <div class="warning-box">
                    <strong><i class="fas fa-clock"></i> First Run Note:</strong> The first time you use OCR, Tesseract.js downloads language model files (~50MB). This may take 30-60 seconds depending on your internet speed. Subsequent runs will be faster (10-30 seconds).
                </div>

                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #374151;">Step 5: Review Results</h3>
                <p>
                    After extraction completes, you should see:
                </p>
                <ul class="step-list">
                    <li><strong>Extracted Text:</strong> A text area with the recognized text</li>
                    <li><strong>Quality Assessment:</strong> 
                        <ul style="margin-top: 0.5rem;">
                            <li>Confidence score (0-100%)</li>
                            <li>Quality score (0-100 points)</li>
                            <li>Text length (number of characters)</li>
                            <li>Processing time (milliseconds)</li>
                        </ul>
                    </li>
                    <li><strong>Detected Information:</strong> Any detected names, IDs, or dates</li>
                    <li><strong>Action Buttons:</strong>
                        <ul style="margin-top: 0.5rem;">
                            <li>✓ "Accept & Save" - Save results to database</li>
                            <li>↻ "Retry" - Try extracting again</li>
                            <li>✕ "Discard" - Close without saving</li>
                        </ul>
                    </li>
                </ul>

                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #374151;">Step 6: Save Results</h3>
                <p>
                    Review the extracted text and click "Accept & Save" to store the results in your database.
                    You should see a success message confirming the save was successful.
                </p>
            </div>

            <!-- Console Debugging -->
            <div class="section">
                <h2><i class="fas fa-terminal"></i> Browser Console Messages</h2>
                <p>
                    When you open the browser Developer Console (F12 > Console tab), you should see debug messages 
                    like these if everything is working correctly:
                </p>

                <div class="code-block">
                    openOCRForCurrentDocument called<br>
                    Document URL set to: blob:http://localhost/abcd1234-...<br>
                    startOCRExtraction called<br>
                    Tesseract worker initialized<br>
                    Image data retrieved<br>
                    Starting OCR recognition...<br>
                    OCR recognition complete { time: 5234 }<br>
                    Extracted text length: 523 Confidence: 0.92<br>
                    OCR extraction complete
                </div>

                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #374151;">Common Error Messages</h3>

                <p><strong>Error:</strong> "No document URL available"</p>
                <div class="warning-box">
                    <strong>Cause:</strong> The document viewer doesn't have a source URL<br>
                    <strong>Fix:</strong> Make sure a document is fully loaded in the preview before clicking "Extract Text"
                </div>

                <p><strong>Error:</strong> "Failed to initialize Tesseract"</p>
                <div class="warning-box">
                    <strong>Cause:</strong> Can't load the Tesseract.js library<br>
                    <strong>Fix:</strong> Check your internet connection, try refreshing the page
                </div>

                <p><strong>Error:</strong> "Failed to convert document to image"</p>
                <div class="warning-box">
                    <strong>Cause:</strong> Document format not supported or document can't be read<br>
                    <strong>Fix:</strong> Try with a different document, ensure it's a valid PDF or image file
                </div>

                <p><strong>Error:</strong> Network errors or CORS issues</p>
                <div class="warning-box">
                    <strong>Cause:</strong> Can't download Tesseract model files from CDN<br>
                    <strong>Fix:</strong> Check internet connection, try a different network, check browser firewall settings
                </div>
            </div>

            <!-- Performance Information -->
            <div class="section">
                <h2><i class="fas fa-gauge-high"></i> Performance Information</h2>
                <p>Here's what to expect during OCR processing:</p>

                <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                    <tr style="background: #f3f4f6;">
                        <th style="padding: 0.75rem; text-align: left; border: 1px solid #e5e7eb;">Operation</th>
                        <th style="padding: 0.75rem; text-align: left; border: 1px solid #e5e7eb;">Expected Time</th>
                        <th style="padding: 0.75rem; text-align: left; border: 1px solid #e5e7eb;">Notes</th>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">Modal opens</td>
                        <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">< 100ms</td>
                        <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">Should be instant</td>
                    </tr>
                    <tr style="background: #f9fafb;">
                        <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">First OCR run</td>
                        <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">20-60 seconds</td>
                        <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">Downloads ~50MB model</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">Subsequent OCR runs</td>
                        <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">5-30 seconds</td>
                        <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">Model cached locally</td>
                    </tr>
                    <tr style="background: #f9fafb;">
                        <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">Save to database</td>
                        <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">< 1 second</td>
                        <td style="padding: 0.75rem; border: 1px solid #e5e7eb;">Quick database insert</td>
                    </tr>
                </table>
            </div>

            <!-- Quality Expectations -->
            <div class="section">
                <h2><i class="fas fa-star"></i> Quality Expectations</h2>
                <p>
                    OCR accuracy depends on document quality. Here's what to expect with different document types:
                </p>

                <div style="margin-top: 1rem;">
                    <h3 style="margin-bottom: 0.75rem; color: #374151;">Document Quality Guide</h3>

                    <div style="padding: 1rem; background: #dcfce7; border-radius: 6px; margin-bottom: 1rem;">
                        <strong style="color: #166534;"><i class="fas fa-check-circle"></i> Clear printed documents (ID cards, forms)</strong><br>
                        <span style="color: #166534;">Expected Confidence: 85-95%</span>
                    </div>

                    <div style="padding: 1rem; background: #dbeafe; border-radius: 6px; margin-bottom: 1rem;">
                        <strong style="color: #1e40af;"><i class="fas fa-file-alt"></i> Standard text documents</strong><br>
                        <span style="color: #1e40af;">Expected Confidence: 75-90%</span>
                    </div>

                    <div style="padding: 1rem; background: #fef3c7; border-radius: 6px; margin-bottom: 1rem;">
                        <strong style="color: #92400e;"><i class="fas fa-exclamation-circle"></i> Low quality or damaged documents</strong><br>
                        <span style="color: #92400e;">Expected Confidence: 20-60%</span>
                    </div>

                    <div style="padding: 1rem; background: #fee2e2; border-radius: 6px;">
                        <strong style="color: #991b1b;"><i class="fas fa-times-circle"></i> Not recommended: Handwritten text, very small fonts</strong><br>
                        <span style="color: #991b1b;">Expected Confidence: < 50%</span>
                    </div>
                </div>
            </div>

            <!-- Test Documents -->
            <?php if (!empty($test_documents)): ?>
            <div class="section">
                <h2><i class="fas fa-file-upload"></i> Available Test Documents</h2>
                <p>These documents are available in your system for testing:</p>
                <div class="document-list">
                    <?php foreach ($test_documents as $doc): ?>
                    <div class="document-item">
                        <div class="document-info">
                            <div class="document-name">
                                <i class="fas fa-file"></i>
                                <?php echo htmlspecialchars($doc['document_name']); ?>
                            </div>
                            <div class="document-type">
                                Type: <span class="badge badge-info"><?php echo htmlspecialchars($doc['document_type']); ?></span>
                                ID: #<?php echo $doc['document_id']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Troubleshooting -->
            <div class="section">
                <h2><i class="fas fa-wrench"></i> Troubleshooting</h2>
                <p>If you encounter issues, try these steps:</p>

                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #374151;">Issue: Modal appears but button not visible</h3>
                <ul class="step-list">
                    <li>Refresh the page (Ctrl+F5 or Cmd+Shift+R)</li>
                    <li>Clear browser cache (Ctrl+Shift+Delete)</li>
                    <li>Try a different browser (Chrome, Firefox, Edge)</li>
                </ul>

                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #374151;">Issue: OCR takes too long or appears stuck</h3>
                <ul class="step-list">
                    <li>Check internet connection speed</li>
                    <li>Check browser console for download progress messages</li>
                    <li>Wait longer (first run can take 60+ seconds)</li>
                    <li>Try a different document or smaller file size</li>
                </ul>

                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #374151;">Issue: Extracted text is gibberish or unreadable</h3>
                <ul class="step-list">
                    <li>Document quality may be too low</li>
                    <li>Try with a clearer, higher quality scan</li>
                    <li>Check that text is printed (not handwritten)</li>
                    <li>Ensure document is in a supported language</li>
                </ul>

                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #374151;">Issue: "Failed to initialize Tesseract" error</h3>
                <ul class="step-list">
                    <li>Check browser console for network errors</li>
                    <li>Check firewall/network settings</li>
                    <li>Try disabling VPN or proxy if using one</li>
                    <li>Try in an incognito/private browser window</li>
                </ul>
            </div>

            <!-- Additional Resources -->
            <div class="section">
                <h2><i class="fas fa-book"></i> Additional Resources</h2>
                <p>For more detailed information, check these files:</p>
                <ul class="step-list">
                    <li><strong>OCR_QUICK_START.php</strong> - Quick reference guide with usage examples</li>
                    <li><strong>OCR_FEATURE_GUIDE.php</strong> - Complete technical documentation</li>
                    <li><strong>ocr_verify_setup.php</strong> - Installation and setup verification</li>
                    <li><strong>OCR_DEBUG_GUIDE.md</strong> - Detailed debugging and troubleshooting</li>
                    <li><strong>utils/ocr_service.php</strong> - Core OCR service class documentation</li>
                </ul>
            </div>

            <!-- Next Steps -->
            <div class="section" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-left-color: #10b981;">
                <h2><i class="fas fa-arrow-right"></i> Next Steps</h2>
                <p>
                    Now that you understand how to test the OCR feature:
                </p>
                <ol class="step-list">
                    <li><strong>Test with your own documents:</strong> Use real documents from your system</li>
                    <li><strong>Verify database storage:</strong> Check that results are saved to the database</li>
                    <li><strong>Test edge cases:</strong> Try with various document types and qualities</li>
                    <li><strong>Review console logs:</strong> Monitor browser console for any issues</li>
                    <li><strong>Document results:</strong> Keep track of what works and what doesn't</li>
                </ol>

                <div class="button-group">
                    <a href="admindashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="OCR_QUICK_START.php" class="btn btn-secondary">
                        <i class="fas fa-book"></i> View Full Guide
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add some helpful console messages
        console.log('%cOCR Testing Guide Loaded', 'font-size: 16px; font-weight: bold; color: #4f46e5;');
        console.log('%cOpen Browser Console (F12) and look for OCR-related debug messages while testing', 'font-size: 12px; color: #6b7280;');
        console.log('%cTip: Check for messages like "Tesseract worker initialized", "OCR extraction complete", etc.', 'font-size: 12px; color: #6b7280;');
    </script>
</body>
</html>

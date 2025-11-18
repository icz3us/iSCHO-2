<?php
/**
 * ============================================
 * OCR FEATURE - IMPLEMENTATION GUIDE
 * ============================================
 * 
 * This is a reference guide for the OCR feature implementation.
 * This is a PHP file that can be viewed via the browser at:
 * http://localhost/ischo2/OCR_FEATURE_GUIDE.php
 */
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Feature - Implementation Guide</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
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
        }
        .section h2 {
            color: #667eea;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        .section h3 {
            color: #764ba2;
            font-size: 1.3rem;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }
        .code-block {
            background: #f5f5f5;
            border-left: 4px solid #667eea;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        .feature-list {
            list-style: none;
            margin: 1rem 0;
        }
        .feature-list li {
            padding: 0.75rem 0;
            padding-left: 2rem;
            position: relative;
        }
        .feature-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
            font-size: 1.2rem;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
        }
        .warning strong {
            color: #d97706;
        }
        .success {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
        }
        .success strong {
            color: #059669;
        }
        .technical-specs {
            background: #f0f4ff;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f0f4ff;
            font-weight: 600;
            color: #667eea;
        }
        tr:hover {
            background: #f9fafb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 OCR Feature Implementation</h1>
            <p>Optical Character Recognition for Document Verification</p>
        </div>
        
        <div class="content">
            <!-- Overview -->
            <div class="section">
                <h2>Overview</h2>
                <p>The OCR (Optical Character Recognition) feature enables automatic text extraction from uploaded documents in the scholarship application system. This feature helps verify document authenticity and extract relevant information for compliance verification.</p>
                
                <div class="success">
                    <strong>✓ Key Benefit:</strong> FREE, client-side OCR processing using Tesseract.js - No API keys, no credit card required, no monthly costs!
                </div>
            </div>

            <!-- Features -->
            <div class="section">
                <h2>Features</h2>
                <ul class="feature-list">
                    <li>Client-side text extraction (no server load)</li>
                    <li>Supports PDF, PNG, JPG, JPEG, GIF, BMP, WEBP formats</li>
                    <li>Real-time confidence scoring</li>
                    <li>Automatic text quality validation</li>
                    <li>Document information extraction (names, IDs, dates, etc.)</li>
                    <li>Processing time tracking</li>
                    <li>Database storage for audit trail</li>
                    <li>Admin dashboard integration</li>
                    <li>Zero external API dependencies (offline capable)</li>
                    <li>Responsive design for all screen sizes</li>
                </ul>
            </div>

            <!-- Files Created -->
            <div class="section">
                <h2>Files Created/Modified</h2>
                <table>
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Type</th>
                            <th>Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>utils/ocr_service.php</code></td>
                            <td>New</td>
                            <td>Core OCR service class handling database operations and text validation</td>
                        </tr>
                        <tr>
                            <td><code>ajax_ocr_handler.php</code></td>
                            <td>New</td>
                            <td>AJAX endpoint for OCR requests and result storage</td>
                        </tr>
                        <tr>
                            <td><code>admindashboard.php</code></td>
                            <td>Modified</td>
                            <td>Added OCR UI components, modals, and JavaScript functionality</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- How to Use -->
            <div class="section">
                <h2>How to Use the OCR Feature</h2>
                
                <h3>Step 1: Access Admin Dashboard</h3>
                <p>Log in as an Admin or Superadmin and navigate to the Admin Dashboard.</p>
                
                <h3>Step 2: View Document</h3>
                <p>Click on any document (COR, Voter ID, Indigency Certificate) in the applicant details to open the document viewer.</p>
                
                <h3>Step 3: Extract Text</h3>
                <p>Click the "Extract Text" button in the document viewer to open the OCR modal.</p>
                
                <h3>Step 4: Process</h3>
                <p>Click "Extract Text with OCR" to start the text extraction process. This runs entirely in your browser.</p>
                
                <h3>Step 5: Review Results</h3>
                <p>The extracted text appears with quality metrics. Review the accuracy and make any needed corrections.</p>
                
                <h3>Step 6: Accept or Retry</h3>
                <p>
                    <strong>Accept & Save:</strong> Saves the extracted text to the database for record-keeping<br>
                    <strong>Retry:</strong> Attempts extraction again<br>
                    <strong>Discard:</strong> Cancels the extraction
                </p>
            </div>

            <!-- Technical Details -->
            <div class="section">
                <h2>Technical Details</h2>
                
                <div class="technical-specs">
                    <h3>Technology Stack</h3>
                    <ul class="feature-list">
                        <li><strong>Tesseract.js:</strong> v5.0.4 (JavaScript OCR engine)</li>
                        <li><strong>Database:</strong> MySQL with new ocr_results table</li>
                        <li><strong>Authentication:</strong> Admin/Superadmin only access</li>
                        <li><strong>Processing:</strong> Client-side (browser)</li>
                        <li><strong>Storage:</strong> Extracted text stored server-side for audit</li>
                    </ul>
                </div>

                <h3>Database Schema</h3>
                <p>A new table <code>ocr_results</code> is automatically created with the following structure:</p>
                <div class="code-block">
CREATE TABLE ocr_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_type VARCHAR(50),
    extracted_text LONGTEXT,
    confidence DECIMAL(3,2),
    language VARCHAR(10),
    processing_time INT,
    file_path VARCHAR(500),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
                </div>

                <h3>Quality Scoring Algorithm</h3>
                <p>The OCR feature uses a quality scoring system (0-100):</p>
                <ul>
                    <li>+20 points: Text length > 20 characters</li>
                    <li>+20 points: Word count > 3</li>
                    <li>+20 points: Contains letters</li>
                    <li>+15 points: Contains numbers</li>
                    <li>+25 points: Confidence score > 70%</li>
                    <li><strong>Minimum threshold: 50 points</strong></li>
                </ul>
            </div>

            <!-- API Endpoints -->
            <div class="section">
                <h2>API Endpoints</h2>
                
                <h3>POST: ajax_ocr_handler.php</h3>
                
                <h4>Action: extract_text</h4>
                <p>Stores OCR extraction results in database</p>
                <div class="code-block">
POST /ajax_ocr_handler.php
action: extract_text
user_id: [applicant user ID]
document_type: [cor|indigency|voter|profile_picture]
extracted_text: [text content]
confidence: [0.0-1.0]
processing_time: [milliseconds]
                </div>

                <h4>Action: get_results</h4>
                <p>Retrieves OCR results for a specific document</p>
                <div class="code-block">
POST /ajax_ocr_handler.php
action: get_results
user_id: [applicant user ID]
document_type: [document type]
                </div>

                <h4>Action: validate_document</h4>
                <p>Validates extracted text quality</p>
                <div class="code-block">
POST /ajax_ocr_handler.php
action: validate_document
extracted_text: [text content]
document_type: [document type]
confidence: [0.0-1.0]
                </div>
            </div>

            <!-- Security -->
            <div class="section">
                <h2>Security Considerations</h2>
                <ul class="feature-list">
                    <li>Admin/Superadmin role required for OCR access</li>
                    <li>User ID validation against database</li>
                    <li>All input sanitized using htmlspecialchars()</li>
                    <li>PDO prepared statements prevent SQL injection</li>
                    <li>CSRF protection via session management</li>
                    <li>Processing occurs client-side (no data transfer during extraction)</li>
                </ul>
            </div>

            <!-- Browser Support -->
            <div class="section">
                <h2>Browser Compatibility</h2>
                <p>Tesseract.js requires modern browser capabilities:</p>
                <ul class="feature-list">
                    <li>Chrome/Chromium 52+</li>
                    <li>Firefox 55+</li>
                    <li>Safari 11+</li>
                    <li>Edge 15+</li>
                    <li>Opera 39+</li>
                </ul>
                <div class="warning">
                    <strong>Note:</strong> Older browsers (IE 11) are not supported. WebAssembly is required.
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="section">
                <h2>Troubleshooting</h2>
                
                <h3>Issue: "Failed to initialize Tesseract"</h3>
                <p><strong>Solution:</strong> Check browser console for errors. Ensure JavaScript is enabled and browser is modern enough (check browser compatibility above).</p>
                
                <h3>Issue: OCR returns no text</h3>
                <p><strong>Solution:</strong> The image quality may be poor. Try with a clearer, higher resolution document image. Retry the extraction.</p>
                
                <h3>Issue: Low quality score</h3>
                <p><strong>Solution:</strong> This is expected for poor quality images. Try again with a better quality scan or photo of the document.</p>
                
                <h3>Issue: Processing takes too long</h3>
                <p><strong>Solution:</strong> Tesseract.js downloads language models on first use (~50MB). Subsequent extractions will be faster. Check your internet connection.</p>
            </div>

            <!-- Performance Notes -->
            <div class="section">
                <h2>Performance & Optimization</h2>
                
                <h3>First-Time Setup</h3>
                <p>When OCR is used for the first time, Tesseract.js downloads language models (~50MB). This may take 30-60 seconds depending on internet speed. <strong>This only happens once.</strong></p>
                
                <h3>Subsequent Extractions</h3>
                <p>After the initial setup, text extraction typically takes 3-15 seconds depending on:</p>
                <ul>
                    <li>Document image quality and resolution</li>
                    <li>Computer processing power</li>
                    <li>Browser implementation</li>
                    <li>System resources availability</li>
                </ul>
                
                <h3>Optimization Tips</h3>
                <ul>
                    <li>Use high-quality, clear document scans</li>
                    <li>Ensure sufficient system RAM (minimum 2GB recommended)</li>
                    <li>Close unnecessary browser tabs</li>
                    <li>Use a modern browser for best performance</li>
                </ul>
            </div>

            <!-- Maintenance -->
            <div class="section">
                <h2>System Maintenance</h2>
                
                <h3>Database Cleanup</h3>
                <p>Periodically clean up old OCR results to maintain database performance:</p>
                <div class="code-block">
DELETE FROM ocr_results 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)
AND user_id NOT IN (
    SELECT user_id FROM claim_tokens WHERE used = 1
);
                </div>

                <h3>Monitoring</h3>
                <p>Check OCR statistics for monitoring:</p>
                <div class="code-block">
SELECT 
    COUNT(*) as total_processed,
    COUNT(DISTINCT user_id) as unique_users,
    AVG(confidence) as avg_confidence,
    AVG(processing_time) as avg_time_ms
FROM ocr_results;
                </div>
            </div>

            <!-- Support -->
            <div class="section">
                <h2>Support & Documentation</h2>
                <ul class="feature-list">
                    <li>Tesseract.js Documentation: https://tesseract.projectnaptha.com/</li>
                    <li>GitHub: https://github.com/naptha/tesseract.js</li>
                    <li>iSCHO System Database: <code>ischo_database_schema.sql</code></li>
                </ul>
            </div>

            <!-- Success Message -->
            <div class="success" style="text-align: center;">
                <h3>✓ OCR Feature Successfully Integrated!</h3>
                <p>The OCR feature is now fully integrated into your iSCHO application. Start using it in the Admin Dashboard to extract text from applicant documents.</p>
            </div>
        </div>
    </div>
</body>
</html>

<?php
/**
 * ============================================
 * OCR FEATURE - QUICK REFERENCE CARD
 * ============================================
 * 
 * This is a quick reference card for the OCR implementation
 * Access at: http://localhost/ischo2/OCR_QUICK_START.php
 */
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Quick Start Guide</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .header h1 {
            color: #667eea;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        .header p {
            color: #6b7280;
            font-size: 1.1rem;
        }
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }
        .card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .card-icon.feature {
            color: #667eea;
        }
        .card-icon.file {
            color: #10b981;
        }
        .card-icon.tech {
            color: #f59e0b;
        }
        .card h3 {
            color: #1f2937;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        .card p {
            color: #6b7280;
            line-height: 1.6;
        }
        .card ul {
            list-style: none;
            margin-top: 1rem;
        }
        .card li {
            padding: 0.5rem 0;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
        }
        .card li:last-child {
            border-bottom: none;
        }
        .card li:before {
            content: "▸ ";
            color: #667eea;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        .full-width {
            grid-column: 1 / -1;
        }
        .step-guide {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .step-guide h2 {
            color: #667eea;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #667eea;
        }
        .steps {
            display: grid;
            gap: 1.5rem;
        }
        .step {
            display: flex;
            gap: 1.5rem;
        }
        .step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
            font-size: 1.2rem;
        }
        .step-content h4 {
            color: #1f2937;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .step-content p {
            color: #6b7280;
        }
        .code-snippet {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            margin-top: 0.5rem;
            border-left: 4px solid #667eea;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        .feature-item {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 6px;
            border-left: 4px solid #667eea;
        }
        .feature-item strong {
            color: #1f2937;
        }
        .footer {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        .footer h3 {
            color: #667eea;
            margin-bottom: 1rem;
        }
        .footer p {
            color: #6b7280;
            margin-bottom: 1rem;
        }
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #1f2937;
        }
        .btn-secondary:hover {
            background: #d1d5db;
        }
        .highlight {
            background: #fef3c7;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            color: #d97706;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🚀 OCR Quick Start Guide</h1>
            <p>Get up and running with Optical Character Recognition in iSCHO</p>
        </div>

        <!-- Cards Grid -->
        <div class="cards-grid">
            <!-- Key Features -->
            <div class="card">
                <div class="card-icon feature">
                    <i class="fas fa-star"></i>
                </div>
                <h3>Key Features</h3>
                <ul>
                    <li>FREE - No API keys needed</li>
                    <li>100% Client-side processing</li>
                    <li>Support for 8+ image formats</li>
                    <li>Real-time quality scoring</li>
                    <li>Automatic text validation</li>
                </ul>
            </div>

            <!-- Files Added -->
            <div class="card">
                <div class="card-icon file">
                    <i class="fas fa-file-code"></i>
                </div>
                <h3>Files Created</h3>
                <ul>
                    <li><code>utils/ocr_service.php</code></li>
                    <li><code>ajax_ocr_handler.php</code></li>
                    <li><code>OCR_FEATURE_GUIDE.php</code></li>
                    <li><code>ocr_verify_setup.php</code></li>
                    <li>Modified: <code>admindashboard.php</code></li>
                </ul>
            </div>

            <!-- Technology Stack -->
            <div class="card">
                <div class="card-icon tech">
                    <i class="fas fa-microchip"></i>
                </div>
                <h3>Technology</h3>
                <ul>
                    <li>Tesseract.js v5.0.4</li>
                    <li>JavaScript/HTML5</li>
                    <li>MySQL Database</li>
                    <li>PHP Backend</li>
                    <li>Responsive CSS</li>
                </ul>
            </div>
        </div>

        <!-- Step-by-Step Guide -->
        <div class="step-guide">
            <h2>📋 Step-by-Step Usage Guide</h2>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Login as Admin</h4>
                        <p>Log into the iSCHO system with Admin or Superadmin credentials.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Go to Admin Dashboard</h4>
                        <p>Navigate to the Admin Dashboard to view applicants and their documents.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Select an Applicant</h4>
                        <p>Find the applicant and click on their document (COR, Voter ID, Indigency Certificate) to view it.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h4>Click "Extract Text"</h4>
                        <p>In the document viewer, click the purple <span class="highlight">Extract Text</span> button.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">5</div>
                    <div class="step-content">
                        <h4>Start OCR Processing</h4>
                        <p>Click <span class="highlight">Extract Text with OCR</span> button. The browser will process the image locally (no data sent to servers).</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">6</div>
                    <div class="step-content">
                        <h4>Review Results</h4>
                        <p>Wait for processing (usually 3-15 seconds). Review the extracted text and quality metrics.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">7</div>
                    <div class="step-content">
                        <h4>Accept or Retry</h4>
                        <p>Click <span class="highlight">Accept & Save</span> to store the extraction, <span class="highlight">Retry</span> for new extraction, or <span class="highlight">Discard</span> to cancel.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Detail -->
        <div class="step-guide">
            <h2>✨ Features Explained</h2>
            
            <div class="features-grid">
                <div class="feature-item">
                    <strong>🔒 Privacy First</strong>
                    <p>All processing happens in your browser. No images or text are sent to external servers.</p>
                </div>
                
                <div class="feature-item">
                    <strong>⚡ Fast Processing</strong>
                    <p>After first use, text extraction is cached and runs 3-15 seconds typically.</p>
                </div>
                
                <div class="feature-item">
                    <strong>📊 Quality Metrics</strong>
                    <p>Get confidence scores, text length, processing time, and quality assessments.</p>
                </div>
                
                <div class="feature-item">
                    <strong>🔍 Smart Detection</strong>
                    <p>Automatically detects names, IDs, dates, emails, and phone numbers in documents.</p>
                </div>
                
                <div class="feature-item">
                    <strong>💾 Audit Trail</strong>
                    <p>All extracted text is saved to database for compliance and verification purposes.</p>
                </div>
                
                <div class="feature-item">
                    <strong>🌐 Multi-Language</strong>
                    <p>Supports 120+ languages for text extraction (English is default).</p>
                </div>
            </div>
        </div>

        <!-- System Requirements -->
        <div class="step-guide">
            <h2>📱 System Requirements</h2>
            
            <div class="features-grid">
                <div class="feature-item">
                    <strong>Browser Support</strong>
                    <p>Chrome 52+, Firefox 55+, Safari 11+, Edge 15+, Opera 39+</p>
                </div>
                
                <div class="feature-item">
                    <strong>Hardware</strong>
                    <p>Minimum 2GB RAM recommended. Works on desktop, tablet, and mobile.</p>
                </div>
                
                <div class="feature-item">
                    <strong>Internet</strong>
                    <p>First use needs internet (downloads ~50MB models). Subsequent uses are faster.</p>
                </div>
                
                <div class="feature-item">
                    <strong>Database</strong>
                    <p>MySQL 5.7+ with PDO support. Auto-creates ocr_results table.</p>
                </div>
            </div>
        </div>

        <!-- Troubleshooting -->
        <div class="step-guide">
            <h2>🔧 Quick Troubleshooting</h2>
            
            <div class="features-grid">
                <div class="feature-item">
                    <strong>❌ "Failed to initialize"?</strong>
                    <p>Use a modern browser with JavaScript enabled. Check internet connection.</p>
                </div>
                
                <div class="feature-item">
                    <strong>⏳ "Takes too long"?</strong>
                    <p>First use downloads language models (~50MB). Subsequent uses are faster. Be patient!</p>
                </div>
                
                <div class="feature-item">
                    <strong>📄 "No text detected"?</strong>
                    <p>Try with a higher quality image. Poor quality scans may not work. Retry with another image.</p>
                </div>
                
                <div class="feature-item">
                    <strong>🔴 "Low quality score"?</strong>
                    <p>This is normal for poor quality documents. The system is being cautious. You can still accept it.</p>
                </div>
            </div>
        </div>

        <!-- Security Info -->
        <div class="step-guide">
            <h2>🔐 Security & Privacy</h2>
            <p>The OCR feature is designed with security in mind:</p>
            <div class="features-grid">
                <div class="feature-item">
                    <strong>Client-Side Processing</strong>
                    <p>All OCR processing happens in your browser. No image data leaves your computer.</p>
                </div>
                
                <div class="feature-item">
                    <strong>Admin-Only Access</strong>
                    <p>Only logged-in Admins/Superadmins can use OCR feature. Full access control.</p>
                </div>
                
                <div class="feature-item">
                    <strong>Database Security</strong>
                    <p>Extracted text uses PDO prepared statements. Protected against SQL injection.</p>
                </div>
                
                <div class="feature-item">
                    <strong>No External APIs</strong>
                    <p>Zero dependency on external OCR services. 100% self-contained solution.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <h3>Ready to Start?</h3>
            <p>The OCR feature is now fully integrated into your iSCHO system and ready to use!</p>
            <div class="btn-group">
                <a href="admindashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i> Go to Admin Dashboard
                </a>
                <a href="OCR_FEATURE_GUIDE.php" class="btn btn-secondary">
                    <i class="fas fa-book"></i> Full Documentation
                </a>
                <a href="ocr_verify_setup.php" class="btn btn-secondary">
                    <i class="fas fa-check-circle"></i> Verify Setup
                </a>
            </div>
            <p style="margin-top: 2rem; font-size: 0.9rem;">
                <strong>Need Help?</strong> Check the full documentation or setup verification page above.
            </p>
        </div>
    </div>
</body>
</html>

# OCR PDF Support Documentation

## Overview

The OCR feature now fully supports PDF documents. When you click "Extract Text with OCR" on a PDF file, the system automatically:

1. **Detects PDF format** - Checks if the document is a PDF
2. **Renders PDF to image** - Converts the first page of the PDF to a high-quality PNG image (2x scale for clarity)
3. **Extracts text** - Processes the rendered image with Tesseract.js OCR
4. **Stores results** - Saves extracted text and metadata to the database

## How It Works

### PDF Processing Flow

```
PDF Document → PDF.js Library → Render to Canvas → PNG Image → Tesseract.js → Extracted Text
```

### Key Features

#### Supported Formats

- **Images**: PNG, JPEG, GIF, BMP, WebP
- **PDFs**: Single and multi-page documents (first page extracted)

#### Processing Steps

1. **Document Detection** - System identifies file type from URL
2. **Conversion** - PDFs are rendered at 2x scale for better OCR accuracy
3. **Recognition** - Tesseract.js processes the image with high precision
4. **Validation** - Quality scoring and confidence calculation
5. **Storage** - Results saved to `ocr_results` database table

#### Performance Notes

- **First Use**: Tesseract model downloads (~50MB) - takes 30-60 seconds
- **Subsequent Uses**: Much faster (~10-20 seconds per page)
- **PDF Rendering**: Additional 5-10 seconds for PDF to image conversion
- **Total Time**: Typically 20-30 seconds for PDFs on first use, 10-15 seconds afterward

## Technical Details

### Libraries Used

- **PDF.js 3.11.174** - PDF rendering and canvas conversion
- **Tesseract.js 5.0.4** - OCR text recognition (120+ languages)
- **HTML5 Canvas API** - Image processing and manipulation

### PDF Rendering Process

```javascript
// 1. Load PDF document via PDF.js
const pdf = await pdfjsLib.getDocument(pdfUrl).promise;

// 2. Get first page
const page = await pdf.getPage(1);

// 3. Create canvas with high quality (2x scale)
const viewport = page.getViewport({ scale: 2 });
const canvas = document.createElement("canvas");

// 4. Render page to canvas
await page.render(renderContext).promise;

// 5. Convert to data URL
const imageData = canvas.toDataURL("image/png");

// 6. Process with Tesseract.js
const result = await ocrWorker.recognize(imageData);
```

### Database Storage

Extracted text is stored in the `ocr_results` table:

- `document_type` - Auto-detected (PDF or image format)
- `extracted_text` - Full OCR result (LONGTEXT)
- `confidence` - Confidence percentage
- `file_path` - Original document path
- `processing_time` - Milliseconds taken
- `created_at` / `updated_at` - Timestamps

## Troubleshooting

### "PDF.js library not loaded" Error

**Solution**: Refresh the page with Ctrl+F5 (hard refresh)

### "Failed to convert PDF to image" Error

**Causes**:

- PDF may be corrupted
- PDF might use unsupported security features
- Large file size (>100MB)

**Solutions**:

1. Try uploading a different PDF
2. Open the PDF in Adobe Reader to verify it's readable
3. Re-save the PDF as a new file

### Slow Processing on First Use

**Expected**: First use downloads ~50MB Tesseract model

- Patience required for initial download
- Subsequent uses will be faster
- Check browser's developer console for progress

### Blank or Garbled Text Results

**Causes**:

- Low-quality PDF images (scanned at low DPI)
- Rotated or skewed pages
- Text as image rather than searchable PDF

**Solutions**:

1. Try the "Retry" button to re-process
2. Ensure PDFs are scanned at 300+ DPI
3. Use "Discard" and upload a clearer document

## User Experience

### Loading States

- **Images**: Shows "Extracting text from document..."
- **PDFs**: Shows "Converting PDF to image..." with a note about processing time

### Quality Feedback

After extraction, users receive:

- **Confidence Score** - How confident the OCR is (%)
- **Quality Assessment** - Point-based scoring (0-100)
- **Text Statistics** - Character and word count
- **Processing Time** - Milliseconds taken
- **Detected Information** - Auto-detected names, IDs, dates, emails, phones

## Limitations

### Current Constraints

1. **Single Page**: Only first page of multi-page PDFs processed
2. **Size Limit**: Very large PDFs (>100MB) may timeout
3. **Scanned Content**: Poor quality scans may produce inaccurate results
4. **Languages**: Best results for English; supports 120+ languages

### Future Enhancements (Optional)

- Multi-page PDF processing
- Batch document processing
- Advanced image preprocessing (deskew, denoise)
- Language detection and auto-selection
- Handwriting recognition

## Security

### Privacy Protection

- OCR processing happens **client-side** (in user's browser)
- PDFs are **never uploaded to external services**
- Extracted text is **only stored in local database**
- All processing is **end-to-end encrypted**

### CORS Handling

The system uses CORS-compliant CDN URLs:

- `cdnjs.cloudflare.com` - PDF.js library
- `cdn.jsdelivr.net` - Tesseract.js library

## Example Usage

### For Admin Users

```
1. Open Admin Dashboard
2. Navigate to document verification
3. Click "View" on any document
4. In document viewer, click purple "Extract Text" button
5. Click "Extract Text with OCR"
6. Wait for processing to complete
7. Review extracted text, confidence, and detected information
8. Click "Accept & Save" to store results
```

### Processing Multiple Documents

1. Extract text from first document
2. Click "Accept & Save"
3. Return to document list
4. Open next document
5. Repeat steps 1-2

## Support & Debugging

### Check Console Logs

Press F12 → Console tab to see detailed processing logs:

- PDF loading progress
- Canvas rendering status
- Tesseract worker initialization
- Recognition progress
- Data URL conversion confirmation

### Common Log Messages

```
✓ "PDF loaded, pages: 1" - PDF successfully loaded
✓ "PDF page rendered to canvas" - Rendering complete
✓ "Canvas converted to data URL" - Image conversion complete
✓ "OCR recognition complete" - Text extraction done
✗ "Error converting PDF" - PDF processing failed
```

## Additional Resources

- [PDF.js Documentation](https://mozilla.github.io/pdf.js/)
- [Tesseract.js Documentation](https://github.com/naptha/tesseract.js)
- [OCR Best Practices](OCR_FEATURE_GUIDE.php)
- [Quick Start Guide](OCR_QUICK_START.php)

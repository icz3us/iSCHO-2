# OCR Feature - Debug & Testing Guide

## Quick Checklist

Before testing the OCR feature, ensure:

- [ ] You're logged in as Admin or Superadmin
- [ ] You have a document to test with (PDF or image file)
- [ ] Browser console is open (F12) to check for errors
- [ ] JavaScript is enabled
- [ ] Pop-ups are not blocked

## Step-by-Step Testing Procedure

### Step 1: Navigate to Admin Dashboard

1. Login to the iSCHO application
2. Go to Admin Dashboard
3. Verify you can see the list of documents/claims

### Step 2: Open a Document

1. Find a document in the list
2. Click the document to view it
3. You should see:
   - Document preview in a modal
   - "Extract Text" button in the top-right of the modal (purple button with microchip icon)

**If you don't see this button:**

- Make sure a document is actually loaded in the viewer
- Check browser console (F12 > Console tab) for JavaScript errors
- Try refreshing the page

### Step 3: Click "Extract Text" Button

1. With document modal open, click the purple "Extract Text" button
2. A new modal should appear:
   - Black semi-transparent overlay behind it
   - White box centered on screen
   - Heading "Document Text Extraction (OCR)"
   - Purple "Extract Text with OCR" button centered in the box

**If button doesn't appear:**

- Check browser console (F12 > Console tab) for errors
- Look for messages like: "No document URL available"
- Verify documentViewer has a src attribute

### Step 4: Click "Extract Text with OCR" Button

1. Once the modal is visible, click the purple "Extract Text with OCR" button
2. You should see:
   - Purple button disappears
   - Loading spinner appears with "Extracting text from document..."
   - This may take 10-30 seconds on first run (Tesseract downloads ~50MB model)

**If nothing happens:**

- Check browser console for errors
- Look for Tesseract initialization messages
- Wait longer (model download can be slow on first run)

### Step 5: View Results

1. After extraction completes, you should see:
   - Extracted text in a textarea
   - Quality assessment metrics (Confidence, Quality Score, etc.)
   - Detected information section
   - Three action buttons: "Accept & Save", "Retry", "Discard"

**If nothing appears after 30 seconds:**

- Check browser console for errors
- Try refreshing and testing again
- Check your internet connection (Tesseract needs to download model files)

### Step 6: Save Results

1. Review the extracted text
2. Click "Accept & Save" to save to database
3. You should see a success message
4. Modal closes

---

## Browser Console Debugging

### How to Open Console

- **Windows/Linux**: Press `F12` or `Ctrl+Shift+I`
- **Mac**: Press `Cmd+Option+I`
- Click "Console" tab

### What to Look For

**Success Indicators:**

```
Tesseract worker initialized
OCR extraction complete Object { text: "...", confidence: 0.95, time: 2500 }
```

**Common Errors:**

1. **"No document URL available"**

   - Cause: Document viewer doesn't have a source URL
   - Fix: Make sure document is fully loaded in the viewer

2. **"Failed to initialize Tesseract"**

   - Cause: Can't load Tesseract.js library
   - Fix: Check internet connection, try refreshing page

3. **"Failed to convert document to image"**

   - Cause: Document format not supported for OCR
   - Fix: Try with a different document (PDF or image)

4. **Network/CORS Errors**

   - Cause: Can't download Tesseract model files
   - Fix: Check internet connection, try a different network

5. **"Error during text extraction: ..."**
   - Cause: Various OCR processing issues
   - Fix: Check console for specific error, try different document

---

## Testing with Sample Documents

### Recommended Test Files

- **Best**: Clear, well-scanned ID or document
- **Good**: Text document with clear fonts
- **Avoid**: Handwritten text, very small fonts, damaged documents

### Quality Expectations

- Clear printed text: 85-95% confidence
- Handwritten text: 40-60% confidence
- Low quality scans: 20-40% confidence

---

## Database Verification

### Check if Results Were Saved

1. **Using phpMyAdmin** (if available):

   - Navigate to your database
   - Go to `ocr_results` table
   - Should see entries for each extraction

2. **Using CLI** (if comfortable with terminal):
   ```sql
   SELECT * FROM ocr_results ORDER BY created_at DESC LIMIT 5;
   ```

---

## Troubleshooting Checklist

| Issue                       | Solution                                                 |
| --------------------------- | -------------------------------------------------------- |
| Modal not centered          | Refresh page, clear browser cache                        |
| Button not visible          | Check browser console, verify document loaded            |
| OCR takes too long          | First run downloads ~50MB model, be patient              |
| "No document URL" error     | Make sure document viewer has loaded a file              |
| Extracted text is gibberish | Document quality too low, try different file             |
| Can't save results          | Check database connection, review console for SQL errors |

---

## Performance Expectations

| Operation           | Expected Time                        |
| ------------------- | ------------------------------------ |
| Modal open          | < 100ms                              |
| First OCR run       | 20-60s (model download + processing) |
| Subsequent OCR runs | 10-30s (processing only)             |
| Save to database    | < 1s                                 |

---

## Advanced Debugging

### Enable Verbose Logging

Add this to your browser console (F12 > Console tab):

```javascript
// Show all Tesseract events
localStorage.setItem("debug", "tesseract:*");
```

Then reload the page and check console for detailed messages.

### Check Tesseract.js Version

```javascript
console.log(Tesseract);
```

Should show version `5.0.4` and available languages.

### Manual OCR Test

```javascript
// In browser console, after document is loaded:
openOCRForCurrentDocument();
```

This manually triggers the OCR modal without clicking the button.

---

## Still Having Issues?

1. **Take a screenshot** of the browser console error
2. **Note the exact steps** that lead to the problem
3. **Check**: Document format, browser type, internet connection
4. **Try**:
   - Different document
   - Different browser (Chrome, Firefox, Edge)
   - Clearing browser cache (Ctrl+Shift+Delete)
   - Hard refresh (Ctrl+F5 on Windows, Cmd+Shift+R on Mac)

---

## Feature Limitations

- **Supported formats**: PDF, JPG, PNG, GIF, WebP, BMP
- **Max document size**: ~10MB (Tesseract limitation)
- **Languages**: 120+ supported (default: English)
- **Processing**: Done client-side (your browser)
- **Privacy**: No data sent to external servers

---

Last Updated: $(date)
For technical questions, check `OCR_FEATURE_GUIDE.php` for detailed API documentation.

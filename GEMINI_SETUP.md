# Gemini AI Chatbot Setup Guide

## Getting Your Gemini API Key

To enable the AI chatbot functionality, you need to obtain a Gemini API key from Google:

1. Go to [Google AI Studio](https://aistudio.google.com/)
2. Sign in with your Google account
3. Click on "Get API key" or navigate to the API keys section
4. Create a new API key
5. Copy the API key

## Configuring the Chatbot

1. Open `chatbot_api.php` in your project
2. Replace `YOUR_ACTUAL_GEMINI_API_KEY_HERE` with your actual API key:
   ```php
   define('GEMINI_API_KEY', 'YOUR_ACTUAL_API_KEY_HERE');
   ```

## Testing the Chatbot

1. Start your local server (XAMPP/WAMP/MAMP)
2. Navigate to your applicant dashboard
3. Click the chatbot button at the bottom right
4. Ask a question related to scholarships or the application process
5. You should receive a response from the Gemini AI

## Troubleshooting

If you encounter issues:

1. **API Key Errors**: Make sure your API key is correctly entered and has the necessary permissions
2. **Network Issues**: Ensure your server can make outbound HTTPS requests
3. **Rate Limiting**: Gemini has rate limits; if you exceed them, you may need to wait before making more requests

## Security Note

Never commit your actual API key to version control. For production use, consider:
- Using environment variables
- Storing the key in a secure configuration file outside the web root
- Implementing proper error handling to avoid exposing the key in error messages
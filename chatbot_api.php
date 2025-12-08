<?php
// Gemini AI Chatbot API Endpoint using official PHP client
require_once 'vendor/autoload.php';

// Load environment variables
if (file_exists('.env')) {
    $env = parse_ini_file('.env');
    if (isset($env['GEMINI_API_KEY'])) {
        define('GEMINI_API_KEY', $env['GEMINI_API_KEY']);
    }
}

use GeminiAPI\Client;
use GeminiAPI\Resources\Parts\TextPart;

header('Content-Type: application/json');

// Turn off error reporting to prevent PHP errors from interfering with JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if API key is set
if (empty(GEMINI_API_KEY)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please set your Gemini API key in the .env file']);
    exit;
}

// Get the user message from the request
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = isset($input['message']) ? trim($input['message']) : '';

if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

// Prepare the prompt for the Gemini AI
// Add context about the scholarship application system
$prompt = "You are a helpful scholarship application assistant for the iSCHO system. 
The user is asking: \"$userMessage\"

Please provide a helpful, concise response related to scholarship applications, requirements, or the application process.
If the question is not related to scholarships or this system, politely redirect them to scholarship-related questions.

Some context about the system:
- This is a scholarship application system for students
- Users can apply for scholarships by filling out forms
- Required documents typically include certificates of registration, indigency certificates, and voter IDs
- The application process involves multiple steps including personal information, residency details, and family background
- Users can track their application status in the dashboard
- This is a Philippine-based scholarship system focusing on students from Zambales and Bataan provinces";

try {
    // Initialize the Gemini client
    $client = new Client(GEMINI_API_KEY);
    
    // Select the model (using gemini-2.5-flash which is available based on our API test)
    $model = $client->generativeModel('gemini-2.5-flash');
    
    // Generate content using the model
    $response = $model->generateContent(new TextPart($prompt));
    
    // Extract the AI response
    if (isset($response->candidates[0]->content->parts[0]->text)) {
        $aiResponse = $response->candidates[0]->content->parts[0]->text;
        echo json_encode(['response' => $aiResponse]);
    } else {
        echo json_encode(['response' => 'Sorry, I couldn\'t process that request. Please try again.']);
    }
} catch (Exception $e) {
    // Handle any exceptions that occur during the API call
    error_log("Gemini API Exception: " . $e->getMessage());
    error_log("Exception trace: " . $e->getTraceAsString());
    
    // More specific error handling
    $errorMessage = $e->getMessage();
    
    // Check for common error types
    if (strpos($errorMessage, 'API key not valid') !== false) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key. Please check your Gemini API key configuration.']);
    } elseif (strpos($errorMessage, '400') !== false && strpos($errorMessage, 'API_KEY_INVALID') !== false) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key. Please check your Gemini API key configuration.']);
    } elseif (strpos($errorMessage, '400') !== false) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad request to Gemini API. Error: ' . $errorMessage]);
    } elseif (strpos($errorMessage, '404') !== false) {
        http_response_code(404);
        echo json_encode(['error' => 'Gemini API endpoint not found. Error: ' . $errorMessage]);
    } elseif (strpos($errorMessage, '500') !== false) {
        http_response_code(500);
        echo json_encode(['error' => 'Gemini API server error. Error: ' . $errorMessage]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get response from AI service. Please check your API key and internet connection. Error: ' . $errorMessage]);
    }
    exit;
}
?>
<?php
require './connect/connection.php';
require './utils/predictive_analytics.php';
require_once 'vendor/autoload.php';

// Load environment variables
$apiKey = '';
if (file_exists('.env')) {
    $env = parse_ini_file('.env');
    if (isset($env['GEMINI_API_KEY'])) {
        $apiKey = $env['GEMINI_API_KEY'];
    }
}

use GeminiAPI\Client;
use GeminiAPI\Resources\Parts\TextPart;

try {
    echo "Testing Gemini API connection...\n";
    
    // Test the API key
    if (empty($apiKey)) {
        echo "Error: API key is not configured\n";
        exit;
    }
    
    echo "API Key: " . substr($apiKey, 0, 5) . "**********\n";
    
    // Test Gemini API connection
    echo "Initializing Gemini client...\n";
    $client = new Client($apiKey);
    
    echo "Testing model availability...\n";
    $modelsResponse = $client->listModels();
    echo "Models response type: " . gettype($modelsResponse) . "\n";
    
    // Test a simple prompt
    echo "Testing simple prompt...\n";
    $model = $client->generativeModel('gemini-2.5-flash');
    $prompt = "Hello, this is a test. Please respond with 'Test successful' and nothing else.";
    $response = $model->generateContent(new TextPart($prompt));
    
    if (isset($response->candidates[0]->content->parts[0]->text)) {
        echo "Response: " . $response->candidates[0]->content->parts[0]->text . "\n";
        echo "Gemini API is working correctly!\n";
    } else {
        echo "Error: No valid response from Gemini API\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
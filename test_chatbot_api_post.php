<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate a POST request to the chatbot API
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Set up input data
$input_data = json_encode(['message' => 'Hello, how do I apply for a scholarship?']);
file_put_contents('php://input', $input_data);

echo "Testing chatbot API with POST request...\n";

try {
    // Include the chatbot API
    include 'chatbot_api.php';
    echo "Chatbot API executed successfully\n";
} catch (Exception $e) {
    echo "Error executing chatbot API: " . $e->getMessage() . "\n";
    echo "Exception trace: " . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "Fatal error executing chatbot API: " . $e->getMessage() . "\n";
    echo "Error trace: " . $e->getTraceAsString() . "\n";
}
?>
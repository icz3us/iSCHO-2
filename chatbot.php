<?php
// Chatbot UI for Gemini AI integration
// This file contains the HTML, CSS, and JavaScript for the chatbot interface
?>
<div id="chatbot-container" class="chatbot-container">
  <div id="chatbot-popup" class="chatbot-popup">
    <div class="chatbot-header">
      <h3>Scholarship Assistant</h3>
      <button id="close-chatbot" class="close-btn">&times;</button>
    </div>
    <div class="chatbot-messages" id="chatbot-messages">
      <div class="message bot-message">
        Hello! I'm your Scholarship Assistant. How can I help you today?
      </div>
    </div>
    <div class="chatbot-input-container">
      <input type="text" id="chatbot-input" placeholder="Ask me anything about scholarships..." />
      <button id="send-message" class="send-btn">
        <i class="fas fa-paper-plane"></i>
      </button>
    </div>
  </div>
  <button id="chatbot-toggle" class="chatbot-toggle">
    <i class="fas fa-robot"></i>
  </button>
</div>

<style>
.chatbot-container {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 10000;
  font-family: 'Poppins', sans-serif;
}

.chatbot-toggle {
  background: #4f46e5;
  color: white;
  border: none;
  border-radius: 50%;
  width: 60px;
  height: 60px;
  font-size: 24px;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
  transition: all 0.3s ease;
}

.chatbot-toggle:hover {
  background: #4338ca;
  transform: scale(1.1);
}

.chatbot-popup {
  position: absolute;
  bottom: 70px;
  right: 0;
  width: 350px;
  height: 450px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  display: none;
  flex-direction: column;
  overflow: hidden;
}

.chatbot-popup.active {
  display: flex;
}

.chatbot-header {
  background: #4f46e5;
  color: white;
  padding: 15px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.chatbot-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 500;
}

.close-btn {
  background: none;
  border: none;
  color: white;
  font-size: 24px;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.chatbot-messages {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
  background: #f9fafb;
}

.message {
  margin-bottom: 15px;
  padding: 12px 15px;
  border-radius: 18px;
  max-width: 80%;
  word-wrap: break-word;
}

.user-message {
  background: #4f46e5;
  color: white;
  margin-left: auto;
}

.bot-message {
  background: #e5e7eb;
  color: #1f2937;
}

.chatbot-input-container {
  display: flex;
  padding: 15px;
  border-top: 1px solid #e5e7eb;
  background: white;
}

#chatbot-input {
  flex: 1;
  padding: 12px 15px;
  border: 1px solid #e5e7eb;
  border-radius: 24px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.3s;
}

#chatbot-input:focus {
  border-color: #4f46e5;
}

.send-btn {
  background: #4f46e5;
  color: white;
  border: none;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  margin-left: 10px;
  cursor: pointer;
  transition: background 0.3s;
}

.send-btn:hover {
  background: #4338ca;
}

@media (max-width: 768px) {
  .chatbot-popup {
    width: 300px;
    height: 400px;
    right: -20px;
  }
  
  .chatbot-toggle {
    width: 50px;
    height: 50px;
    font-size: 20px;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const chatbotToggle = document.getElementById('chatbot-toggle');
  const chatbotPopup = document.getElementById('chatbot-popup');
  const closeChatbot = document.getElementById('close-chatbot');
  const chatbotInput = document.getElementById('chatbot-input');
  const sendButton = document.getElementById('send-message');
  const chatbotMessages = document.getElementById('chatbot-messages');

  // Toggle chatbot visibility
  chatbotToggle.addEventListener('click', function() {
    chatbotPopup.classList.toggle('active');
  });

  // Close chatbot
  closeChatbot.addEventListener('click', function() {
    chatbotPopup.classList.remove('active');
  });

  // Send message on button click
  sendButton.addEventListener('click', sendMessage);

  // Send message on Enter key
  chatbotInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      sendMessage();
    }
  });

  function sendMessage() {
    const message = chatbotInput.value.trim();
    if (message) {
      // Add user message to chat
      addMessage(message, 'user');
      chatbotInput.value = '';

      // Show typing indicator
      const typingIndicator = document.createElement('div');
      typingIndicator.className = 'message bot-message';
      typingIndicator.id = 'typing-indicator';
      typingIndicator.textContent = 'Thinking...';
      chatbotMessages.appendChild(typingIndicator);
      chatbotMessages.scrollTop = chatbotMessages.scrollHeight;

      // Send message to Gemini API
      fetch('chatbot_api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ message: message })
      })
      .then(response => {
        // Check if the response is ok
        if (!response.ok) {
          throw new Error('Network response was not ok. Status: ' + response.status);
        }
        return response.json();
      })
      .then(data => {
        // Remove typing indicator
        const indicator = document.getElementById('typing-indicator');
        if (indicator) {
          indicator.remove();
        }
        
        // Check if there's an error in the response
        if (data.error) {
          addMessage('Error: ' + data.error, 'bot');
        } else if (data.response) {
          addMessage(data.response, 'bot');
        } else {
          addMessage('Sorry, I encountered an error. Please try again.', 'bot');
        }
      })
      .catch(error => {
        // Remove typing indicator
        const indicator = document.getElementById('typing-indicator');
        if (indicator) {
          indicator.remove();
        }
        
        // Add error message
        addMessage('Sorry, I encountered an error. Please check your internet connection and try again. Error: ' + error.message, 'bot');
        console.error('Chatbot error:', error);
      });
    }
  }

  function addMessage(text, sender) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}-message`;
    messageDiv.textContent = text;
    chatbotMessages.appendChild(messageDiv);
    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
  }
});
</script>
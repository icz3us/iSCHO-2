# Predictive Analysis with AI Integration

## Overview

The Predictive Analysis with AI Integration feature is a core component of the iSCHO system that leverages data analytics and artificial intelligence to provide insights, predictions, and recommendations for improving the scholarship program. This feature analyzes applicant data, identifies trends, and generates actionable recommendations to optimize the scholarship selection process.

## Architecture

The predictive analytics system consists of several key components:

1. **Data Collection Layer**: Gathers data from various sources within the iSCHO system
2. **Analytics Engine**: Processes and analyzes data using statistical methods
3. **AI Integration Layer**: Utilizes Google's Gemini AI for advanced insights and recommendations
4. **API Layer**: Exposes analytics functionality through RESTful endpoints
5. **Presentation Layer**: Displays insights and recommendations in the Superadmin dashboard

## Key Features

### 1. Scholarship Trend Analysis
Analyzes application patterns by municipality, identifying high-volume and high-success areas.

### 2. Applicant Success Prediction
Predicts the likelihood of applicant approval based on historical data and key factors such as:
- Municipality of origin
- Course of study
- Civil status
- Other demographic factors

### 3. AI-Powered Recommendations
Generates actionable recommendations for improving the scholarship program using Gemini AI, based on:
- Overall approval rates
- Municipal application volumes
- Popular courses
- Identified disparities

## Technical Implementation

### Core Components

#### PredictiveAnalytics Class
Located in [utils/predictive_analytics.php](file:///C:/xampp/htdocs/iSCHO2/utils/predictive_analytics.php), this class provides the main functionality:

- `analyzeScholarshipTrends()`: Analyzes application trends by municipality
- `predictApplicantSuccess()`: Predicts applicant success based on various factors
- `generateRecommendations()`: Generates AI-powered recommendations using Gemini

#### AJAX Handler
The [ajax_analytics_handler.php](file:///C:/xampp/htdocs/iSCHO2/ajax_analytics_handler.php) file provides the API endpoints for the frontend:
- `get_scholarship_trends`: Returns scholarship trend analysis
- `get_applicant_predictions`: Returns applicant success predictions
- `get_recommendations`: Returns AI-generated recommendations

### Data Model

The system uses the following database tables:

#### Analytics Data Table
```sql
CREATE TABLE IF NOT EXISTS analytics_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,2) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    period DATE DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric_name (metric_name),
    INDEX idx_category (category),
    INDEX idx_period (period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### User Information Tables
The analytics engine also leverages data from:
- `users_info`: Contains applicant demographic information
- `user_personal`: Contains educational information
- `user_residency`: Contains residency information

## AI Integration

### Gemini AI Implementation

The system integrates with Google's Gemini AI to generate intelligent recommendations:

1. **API Key Configuration**: The system uses a configured API key to authenticate with Gemini
2. **Context Preparation**: Analytics data is formatted and provided as context to the AI
3. **Prompt Engineering**: Carefully crafted prompts guide the AI to generate relevant recommendations
4. **Response Processing**: AI responses are parsed and formatted for presentation

### AI Prompt Structure

The AI is provided with a structured prompt containing:
- Overall statistics (total applications, approvals, denials)
- Top municipalities by application volume
- Popular courses among applicants
- Approval rates by various categories

### Fallback Mechanism

If the AI integration fails, the system provides basic recommendations as a fallback to ensure continuity of service.

## API Endpoints

All analytics endpoints are accessible through POST requests to [ajax_analytics_handler.php](file:///C:/xampp/htdocs/iSCHO2/ajax_analytics_handler.php):

### Get Scholarship Trends
```
POST /ajax_analytics_handler.php
Action: get_scholarship_trends
```

### Get Applicant Predictions
```
POST /ajax_analytics_handler.php
Action: get_applicant_predictions
Optional Parameter: applicant_id (for specific applicant analysis)
```

### Get AI Recommendations
```
POST /ajax_analytics_handler.php
Action: get_recommendations
```

## Security Considerations

1. **Role-Based Access**: Only Superadmin users can access analytics functionality
2. **Data Privacy**: All analytics processing respects user privacy and data protection regulations
3. **Input Validation**: All API inputs are properly validated and sanitized
4. **Error Handling**: Comprehensive error handling prevents information leakage

## Performance Optimization

1. **Database Indexing**: Proper indexing on analytics tables for efficient querying
2. **Caching**: Results are cached where appropriate to reduce database load
3. **Asynchronous Processing**: Long-running analytics tasks are processed asynchronously
4. **Resource Management**: AI API calls are managed to prevent rate limiting

## Future Enhancements

1. **Real-time Analytics**: Implementation of streaming analytics for real-time insights
2. **Advanced ML Models**: Integration of more sophisticated machine learning models
3. **Customizable Dashboards**: User-configurable analytics dashboards
4. **Automated Reporting**: Scheduled report generation and distribution
5. **Predictive Alerts**: Automated alerts for significant trends or anomalies

## Troubleshooting

### Common Issues

1. **AI API Errors**: Check API key configuration and network connectivity
2. **Database Performance**: Ensure proper indexing and query optimization
3. **Authorization Errors**: Verify user role and session management
4. **Data Inconsistencies**: Validate data integrity and handle missing values appropriately

### Logging and Monitoring

The system logs all analytics operations and AI interactions for monitoring and debugging purposes. Check the PHP error log for detailed error information.

## Configuration

### Required Settings

1. **Gemini API Key**: Must be configured in the predictive analytics utility
2. **Database Permissions**: Ensure proper read access to user data tables
3. **Network Access**: System must be able to reach Gemini API endpoints

### Environment Variables

No specific environment variables are required, but the system relies on proper database configuration and API key availability.
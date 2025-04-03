<?php

return [
    'paths' => ['api/*'], // Define the routes where CORS should be applied
    'allowed_methods' => ['*'], // Allow all HTTP methods (GET, POST, PUT, DELETE, etc.)
    'allowed_origins' => ['*'], // Allow requests from any origin (Change this in production)
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'], // Allow all headers
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false, // Set to true if using authentication with cookies
];
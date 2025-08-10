'name' => 'Sample User',
'email' => 'sampleuser@example.com',
'password' => bcrypt('password123')  // Always hash passwords


# To generate documentation page
php artisan scribe:generate


php artisan serve --host=0.0.0.0 --port=8000

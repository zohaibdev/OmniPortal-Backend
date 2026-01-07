<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ID Encryption Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for encrypting IDs in URLs and API responses
    |
    */

    // Salt for HashIds (combined with app key)
    'id_salt' => env('ENCRYPTION_ID_SALT', 'omniportal_secure_ids'),

    // Minimum length for encoded IDs
    'id_min_length' => env('ENCRYPTION_ID_MIN_LENGTH', 10),

    // Alphabet for HashIds (must be at least 16 unique characters)
    'id_alphabet' => env('ENCRYPTION_ID_ALPHABET', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890'),
];

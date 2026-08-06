<?php
 return [
       'paths' => ['api/*', 'sanctum/csrf-cookie'],
       'allowed_methods' => ['*'],
       // Put your exact Vercel URL here:
       'allowed_origins' => ['https://frontendaco-otug.vercel.app'],
       'allowed_origins_patterns' => [],
       'allowed_headers' => ['*'],
       'exposed_headers' => [],
       'max_age' => 0,
       'supports_credentials' => true, // MUST be true for Sanctum
   ];
<?php
return [
  // SQLite file lives at project root as mvp.db
  'dsn'  => 'sqlite:' . __DIR__ . '/mvp.db',
  'user' => null,
  'pass' => null,

  'base_url' => 'http://127.0.0.1:8080',
  'upload_dir' => __DIR__ . '/public/uploads',
  'upload_max_mb' => 50,

  // Email (simple PHP mail() for MVP; switch to SMTP later)
  'email_to' => 'quotes@example.com',
  'email_from' => 'no-reply@example.com',
  'currency' => '£',
];
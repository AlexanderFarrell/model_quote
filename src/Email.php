<?php
require_once __DIR__.'/Config.php';

class Email {
  static function notifyNewLead(array $lead, array $quote): void {
    // MVP: use PHP mail(); replace with SMTP lib later.
    $cfg = Config::load();
    $subj = 'New Instant Quote Lead: '.$quote['public_ref'];
    $body = "Lead for {$quote['public_ref']}\n".
      "Name: {$lead['name']}\nEmail: {$lead['email']}\nCompany: {$lead['company']}\n".
      "Phone: {$lead['phone']}\nNotes: {$lead['notes']}\n";
    @mail($cfg['email_to'], $subj, $body, "From: {$cfg['email_from']}");
  }
}
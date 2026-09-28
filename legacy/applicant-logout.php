<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Applicant Session Logout Handler
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['applicant_id']);
unset($_SESSION['applicant_name']);
unset($_SESSION['applicant_email']);
unset($_SESSION['applicant_nationality']);
unset($_SESSION['applicant_passport']);

header("Location: index");
exit();

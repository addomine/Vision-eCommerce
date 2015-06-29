<?php
require_once 'conf/config.connector.php';
require_once 'lib/class.phpmailer.php';

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
$psOutput = null;
exec("ps aux | grep erp2ps.php", $psOutput);

$alreadyExtecued = FALSE;

foreach ($psOutput as $row) {

  if (strpos($row, md5(ADMIN_FOLDER)) !== false) {
    $alreadyExtecued = TRUE;
    break;
  } else {
    $alreadyExtecued = FALSE;
  }
}
if ($alreadyExtecued) {
  sendMailAlert();
} else {
  shell_exec('php erp2ps.php ' . md5(ADMIN_FOLDER) . ' > /dev/null 2>/dev/null &');
}

function sendMailAlert() {
  $mail = new PHPMailer();

  $mail -> AddAddress(EMAIL_LOG_TO);
  $mail -> AddAddress(EMAIL_LOG_TO_2);

  $mail -> From = EMAIL_LOG_FROM;
  $mail -> FromName = EMAIL_LOG_SUBJECT;
  $mail -> Subject = EMAIL_LOG_SUBJECT;
  $mail -> Body = "Caro Amministratore,\r\nil processo di esportazione dati da VisionERP è già partito ed è ancora in esecuzione. Aspettare la mail di fine processo prima di rilanciarlo.\r\n\r\n";

  $mail -> Send();
}
?>
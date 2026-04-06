<?php
include('class.phpmailer.php');
include('class.smtp.php');
use PHPMailer;
 $mail = new PHPMailer;
     $mail->isSMTP();                                  
    //  $mail->Host = 'smtp.office365.com'; 
      $mail->Host = '172.16.13.208';
     $mail->SMTPAuth = false;                             
    //  $mail->Username = 'pwadmin@aacanet.org';                 
    //  $mail->Password = 'december.SURVEY.95';                           
    //  $mail->SMTPSecure = 'tls';                           
     $mail->Port = 25;  
     $mail->From = 'pwadmin@aacanet.org';   
     $mail->FromName = 'Pipeway 2.0';



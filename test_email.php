<?php
/******************************************************************************
 * Script Name: test_email.php
 * Author: KEANT Technologies
 * Description: Test email script to verify SMTP configuration and email
 *              delivery functionality for the invoicing system.
 *
 * Usage: php test_email.php [optional_email_address]
 *        If no email address provided, sends to default recipients.
 *
 * Change Log:
 * Date         Modified By              Description
 * ----------   ----------------------   ----------------------------------------
 * 2026-02-14   KEANT Technologies       Initial version - Test email script
 *
 *****************************************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include PHPMailer classes
require_once('/var/www/html/bi/dist/PHPMailer/class.phpmailer.php');
require '/var/www/html/bi/dist/PHPMailer/PHPMailerAutoload.php';
require '/var/www/html/bi/dist/PHPMailer/class.smtp.php';

// Logging function
function writeLog($message) {
    $logFile = '/home/pipewayweb/log/test_email.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage; // Also print to console
}

// Start of script execution
writeLog("=== Test Email Script Started ===");

// Check if custom email address is provided via command line
$customEmail = isset($argv[1]) ? $argv[1] : null;

if ($customEmail) {
    // Validate email format
    if (!filter_var($customEmail, FILTER_VALIDATE_EMAIL)) {
        writeLog("ERROR: Invalid email format provided: {$customEmail}");
        echo "\nUsage: php test_email.php [email@example.com]\n";
        exit(1);
    }
    writeLog("Custom email recipient: {$customEmail}");
}

// Send test email
try {
    writeLog("Preparing to send test email...");
    
    // Include mail setup configuration
    include_once('/var/www/html/bi/dist/mailsetup.php');
    
    // Add recipients
    if ($customEmail) {
        // Send only to custom email if provided
        $mail->addAddress($customEmail);
        writeLog("Added recipient: {$customEmail}");
    } else {
        // Send to default recipients
        $mail->addAddress('tbeal@aacanet.org');
        $mail->addAddress('dpriest@aacanet.org');
        $mail->addAddress('droberts@aacanet.org');
        $mail->addAddress('tbalcerzak@aacanet.org');
        $mail->addAddress('vishalkul94@gmail.com');
        $mail->addAddress('keanttech@gmail.com');
        
        writeLog("Added default recipients");
    }
    
    // Set email content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email - Invoicing System';
    
    // Create email body with system information
    $currentDateTime = date('l, F j, Y - g:i:s A');
    $serverName = gethostname();
    $phpVersion = phpversion();
    
    $mail->Body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .info-table td { padding: 10px; border-bottom: 1px solid #ddd; }
            .info-table td:first-child { font-weight: bold; width: 40%; }
            .footer { text-align: center; padding: 20px; color: #777; font-size: 12px; }
            .success { color: #4CAF50; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✓ Test Email Successful</h1>
            </div>
            <div class='content'>
                <p>This is a test email from the <strong>Invoicing System</strong> to verify SMTP configuration and email delivery.</p>
                
                <table class='info-table'>
                    <tr>
                        <td>Server:</td>
                        <td>{$serverName}</td>
                    </tr>
                    <tr>
                        <td>Date & Time:</td>
                        <td>{$currentDateTime}</td>
                    </tr>
                    <tr>
                        <td>PHP Version:</td>
                        <td>{$phpVersion}</td>
                    </tr>
                    <tr>
                        <td>Script Name:</td>
                        <td>test_email.php</td>
                    </tr>
                    <tr>
                        <td>Status:</td>
                        <td><span class='success'>Email delivery is working correctly</span></td>
                    </tr>
                </table>
                
                <p><strong>SMTP Configuration:</strong></p>
                <ul>
                    <li>Host: 172.16.13.208</li>
                    <li>Port: 25</li>
                    <li>Authentication: Disabled</li>
                    <li>From: pwadmin@aacanet.org</li>
                    <li>From Name: Pipeway 2.0</li>
                </ul>
                
                <p style='margin-top: 20px;'>If you received this email, the mail server configuration is working properly.</p>
            </div>
            <div class='footer'>
                <p>American Automated Collection Association (AACA)<br>
                40 Northwood Blvd. Suite C<br>
                Columbus, Ohio 43235<br>
                614/523-2251 | www.aacanet.com</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Plain text version for email clients that don't support HTML
    $mail->AltBody = "Test Email - Invoicing System\n\n"
                   . "This is a test email from the Invoicing System.\n\n"
                   . "Server: {$serverName}\n"
                   . "Date & Time: {$currentDateTime}\n"
                   . "PHP Version: {$phpVersion}\n"
                   . "Status: Email delivery is working correctly\n\n"
                   . "SMTP Configuration:\n"
                   . "Host: 172.16.13.208\n"
                   . "Port: 25\n"
                   . "From: pwadmin@aacanet.org\n";
    
    // Optional: Enable verbose debug output
    // Uncomment the line below to see detailed SMTP communication
    // $mail->SMTPDebug = 2;
    
    writeLog("Sending email...");
    
    // Send the email
    if ($mail->send()) {
        writeLog("SUCCESS: Email sent successfully!");
        echo "\n✓ Email sent successfully!\n";
        
        if ($customEmail) {
            echo "  Recipient: {$customEmail}\n";
        } else {
            echo "  Recipients: Default list (5 addresses)\n";
        }
        
        echo "\nCheck your inbox for the test email.\n";
        echo "Log file: /home/pipewayweb/log/test_email.txt\n\n";
        
        writeLog("=== Test Email Script Completed Successfully ===");
        exit(0);
    } else {
        writeLog("ERROR: Failed to send email");
        writeLog("Mailer Error: " . $mail->ErrorInfo);
        echo "\n✗ Failed to send email\n";
        echo "Error: " . $mail->ErrorInfo . "\n\n";
        
        writeLog("=== Test Email Script Completed with Errors ===");
        exit(1);
    }
    
} catch (Exception $e) {
    writeLog("EXCEPTION: " . $e->getMessage());
    echo "\n✗ Exception occurred: " . $e->getMessage() . "\n\n";
    
    writeLog("=== Test Email Script Failed ===");
    exit(1);
}
?>

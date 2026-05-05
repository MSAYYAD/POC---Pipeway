<?php
/******************************************************************************
 * Script Name: court_costs_cron_KNT.php
 * Author: KEANT Technologies
 * Description: Automated cron job script for generating Court Cost invoices.
 *              This script queries the RMAACABHS database for court cost 
 *              transactions (RMSTRANCDE='1A'), generates PDF invoices using 
 *              wkhtmltopdf, and emails notification upon completion.
 *
 * Change Log:
 * Date         Modified By              Description
 * ----------   ----------------------   ----------------------------------------
 * 2026-01-24   KEANT Technologies       Initial version - Added developer tag,
 *                                       change log, and comprehensive logging
 *                                       functionality for better traceability
 * ----------  ----------------------   ---------------------------------------
 * 2026-04-06    AH05052026             Added AS400 ODBC connection            
 *                                      replaced PDO $conndb2 query with                    
 *                                      ODBC AS400 direct connection
 *
 *****************************************************************************/
error_reporting(1); 
require_once('/var/www/html/bi/dist/PHPMailer/class.phpmailer.php');
require '/var/www/html/bi/dist/PHPMailer/PHPMailerAutoload.php';
require '/var/www/html/bi/dist/PHPMailer/class.smtp.php';
// include_once('pdoconn.php'); //AH05052026
require '/var/www/html/bi/dist/vendor/autoload.php';

use Knp\Snappy\Pdf;
putenv('XDG_RUNTIME_DIR=/tmp/runtime-www-data');
$snappy = new Pdf('/usr/bin/wkhtmltopdf');

// Logging function
function writeLog($message) {
    $logFile = '/home/pipewayweb/log/cost_cron.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// ===== AH05052026 START =====
// AS400 ODBC connection constants — update DSN/user/pass as per /etc/odbc.ini
define('AS400_DSN',     'DB2');                 // DSN name configured in /etc/odbc.ini
define('AS400_HOST',    '192.168.21.8');      // AS400 hostname or IP
define('AS400_LIB',     'SABINESH', 'AACALIB'); // Default library/schema
define('AS400_USER',    'TESTADMIN');             // AS400 user profile
define('AS400_PASS',    'x1ns8@p5d7');          // AS400 password
/**
 * Connect to AS400 via ODBC
 * @return resource ODBC connection resource
 * @throws Exception if connection fails
 */
function connectAS400() {
    $conn = @odbc_connect(AS400_DSN, AS400_USER, AS400_PASS);
    if (!$conn) {
        $err = odbc_errormsg();
        writeLog('AS400 ODBC connection failed: ' . $err, 'ERROR');
        throw new Exception('AS400 ODBC connection failed: ' . $err);
    }
    writeLog('AS400 ODBC connection established successfully.', 'INFO');
    return $conn;
}

/**
 * Execute a query on AS400 via ODBC and return results
 * mimics PDO fetchAll(PDO::FETCH_OBJ) — returns array of stdClass objects
 * so all existing $row->COLUMN references work without any change
 * @param resource $conn  ODBC connection resource
 * @param string   $query SQL query string (no parameters needed — billdate injected as string)
 * @return array          Array of stdClass objects (same as PDO FETCH_OBJ)
 */
function odbc_fetch_all_obj($conn, $query) {
    $stmt = @odbc_exec($conn, $query);
    if (!$stmt) {
        $err = odbc_errormsg($conn);
        writeLog('AS400 ODBC query failed: ' . $err . ' | Query: ' . $query, 'ERROR');
        odbc_free_result($stmt);
        return [];
    }
    $results = [];
    while ($row = odbc_fetch_array($stmt)) {
        // Trim trailing spaces — AS400 pads CHAR fields with spaces
        $obj = new stdClass();
        foreach ($row as $col => $val) {
            $obj->$col = is_string($val) ? trim($val) : $val;
        }
        $results[] = $obj;
    }
    odbc_free_result($stmt);
    return $results;
}
// ===== AH05052026 END =====

// Start of script execution
writeLog("=== Court Cost Invoice Cron Job Started ===");

// Start of script execution
writeLog("=== Court Cost Invoice Cron Job Started ===");

// Disable local file access to prevent blocked file warnings
$snappy->setOption('disable-local-file-access', false);
// Optionally disable loading images if they cause problems
$snappy->setOption('no-images', true);
$snappy->setOption('enable-local-file-access', true);

// Check for command line argument for billdate
if (isset($argv[1]) && !empty($argv[1])) {
    // User provided a billdate parameter
    $billdate = $argv[1];
    
    // Validate the date format (YYYYMMDD - 8 digits)
    if (preg_match('/^\d{8}$/', $billdate)) {
        writeLog("Using user-provided bill date: {$billdate} (Parameter mode)");
    } else {
        writeLog("Invalid date format provided: {$billdate}. Expected format: YYYYMMDD (e.g., 20260124)", 'ERROR');
        exit(1);
    }
} else {
    // No parameter provided, use automatic date calculation
    $currentDay = date('D');
    $currentdate = date('Ymd');
    
    if($currentDay == 'Mon'){
        $billdate = date('Ymd', strtotime('-3 day', strtotime($currentdate)));
    } else {
        $billdate = date('Ymd', strtotime('-1 day', strtotime($currentdate)));
    }
    
    writeLog("Bill date calculated: {$billdate} (Current day: {$currentDay}, Auto mode)");
}

// AH05052026 start
// Replaced PDO $conndb2 query with direct AS400 ODBC connection
// Original PDO block preserved below as reference (commented out)
// Query database for clients with court cost transactions
writeLog("Querying database for clients with court cost transactions...");
// $Querycli = "SELECT PYALORGCD FROM RMAACABHS WHERE BILLDATE='" . $billdate . "' AND RMSTRANCDE='1A' group by PYALORGCD";//echo $Querycli;exit;          
// $Query1prepcli = $conndb2->prepare($Querycli); //AH05052026
// $Query1prepcli->execute(); //AH05052026
// $Query1prepcli->setFetchMode(PDO::FETCH_OBJ); //AH05052026
// $main_resultcli = $Query1prepcli->fetchAll(); //AH05052026
$Querycli = "SELECT PYALORGCD FROM RMAACABLF WHERE BILLDATE='" . $billdate . "' AND RMSTRANCDE='1A' group by PYALORGCD";//echo $Querycli;exit;          
try {
    $as400conn      = connectAS400();
    $main_resultcli = odbc_fetch_all_obj($as400conn, $Querycli);
    odbc_close($as400conn);                                          // MS06APR26 - close after query
    writeLog('AS400 client query returned ' . count($main_resultcli) . ' records.', 'INFO');
} catch (Exception $e) {
    writeLog('Failed to fetch clients from AS400: ' . $e->getMessage(), 'ERROR');
    $main_resultcli = [];
}

//AH05052026 END

$clientname=$main_resultcli;
writeLog("Clients found: " . count($main_resultcli));

writeLog("Clients found: " . count($main_resultcli));

// $htmlContent = ''; 
// $totalfirmall = 0;

// Load and encode invoice template images
writeLog("Loading invoice template images...");
$imagePath = '/var/www/html/bi/dist/images/aaca-net.png';
$imageData = base64_encode(file_get_contents($imagePath));

$imagePathbefore = '/var/www/html/bi/dist/images/court-costs-invoice-tl-design.png';
$imageDatabefore = base64_encode(file_get_contents($imagePathbefore));

$imagePathafter = '/var/www/html/bi/dist/images/court-costs-invoice-br-design.png';
$imageDataafter = base64_encode(file_get_contents($imagePathafter));
writeLog("Images loaded and encoded successfully");
$htmlContenthead .='<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
     
   
</head>
<style>
body,
p {
  margin: 0;
  padding: 0;
}

body {
  font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
  font-size: 14px; 
  line-height: 1.42857143;
  color: #333;
  background-color: #fff;
}

.h1,
.h2,
.h3,
.h4,
.h5,
.h6,
h1,
h2,
h3,
h4,
h5,
h6 {
  font-family: inherit;
  font-weight: 500;
  line-height: 1.1;
  color: inherit;
}

.invoice-table-responsive {
  min-height: 0.01%;
  
}

.invoice-table-bordered {
  border: 1px solid #ddd;
}

.invoice-table {
  width: 100%;
  max-width: 100%;
  margin-bottom: 10px;
}

table {
  background-color: transparent;
}

table {
  border-spacing: 0;
  border-collapse: collapse;
}

.invoice-table-bordered > tbody > tr > td,
.invoice-table-bordered > tbody > tr > th,
.invoice-table-bordered > tfoot > tr > td,
.invoice-table-bordered > tfoot > tr > th,
.invoice-table-bordered > thead > tr > td,
.invoice-table-bordered > thead > tr > th {
  border: 1px solid #ddd;
}

.invoice-table > tbody > tr > td,
.invoice-table > tbody > tr > th,
.invoice-table > tfoot > tr > td,
.invoice-table > tfoot > tr > th,
.invoice-table > thead > tr > td,
.invoice-table > thead > tr > th {
  padding: 8px;
  line-height: 1.42857143;
  vertical-align: top;
  border-top: 1px solid #ddd;
  text-align: center;
}

td,
th {
  padding: 0;
}

.w-100 {
  width: 100%;
}
.w-75 {
  width: 75%;
}
.w-50 {
  width: 50%;
}
.w-25 {
  width: 25%;
}

.offset-w-100 {
  margin-left: 100%;
}
.offset-w-75 {
  margin-left: 75%;
}
.offset-w-50 {
  margin-left: 50%;
}
.offset-w-25 {
  margin-left: 25%;
}

img {
  vertical-align: middle;
}

img {
  border: 0;
}

.text-center {
  text-align: center;
}

.fw-bolder {
  font-weight: bolder;
}

.float-right {
  float: right;
}

.text-right {
  text-align: right;
}

.invoice-d-flex {
  display: flex;
}

.mt-4 {
  margin-top: 4rem;
}

.sample-invoice::before,
.sample-invoice::after,
.remittances-invoice::before,
.remittances-invoice::after,
.legal-fees-invoice::before,
.legal-fees-invoice::after,
.direct-pays-invoice::before,
.direct-pays-invoice::after,
.court-costs-invoice::before,
.court-costs-invoice::after {
  content: "";
  width: 100px;
  height: 400px;
  z-index: -1;
}
.sample-invoice::before,
.remittances-invoice::before,
.legal-fees-invoice::before,
.direct-pays-invoice::before,
.court-costs-invoice::before {
  position: absolute;
  top: 0px;
  left: 0px;
}
.sample-invoice::after,
.remittances-invoice::after,
.legal-fees-invoice::after,
.direct-pays-invoice::after,
.court-costs-invoice::after {
  position: absolute;
  bottom: 0px;
  right: 0px;
}

.sample-invoice,
.remittances-invoice,
.legal-fees-invoice,
.direct-pays-invoice,
.court-costs-invoice {
  position: relative;
}

.invoice-sub-header,
.due-table,
.main-table,
.main-content {
  padding: 10px 135px;
}

.invoice-header h1 {
  font-size: 50px;
  margin: 0;
  padding: 30px 0;
}

.invoice-sub-header p {
  margin: 0;
}

.invoice-img-responsive {
  display: block;
  max-width: 100%;
  height: auto;
}

.main-table table thead {
  text-transform: uppercase;
}

.main-table .text-right .invoice-table > tbody > tr > td {
  text-align: right;
}

.due-table table {
  text-transform: uppercase;
}

.main-content {
  margin-top: 30px;
  text-align: right;
}

@page {
  margin: 0;
}



.sample-invoice::before {
  background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/sample-invoice-tl-design.png")) ?>") no-repeat center center;

}
.sample-invoice::after {
   background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/sample-invoice-br-design.png")) ?>") no-repeat center center;
 
}
.main-table-sample table thead {
  background-color: #cce9e7;
}


.remittances-invoice::before {
  background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/remittances-invoice-tl-design.png")) ?>") no-repeat center center;
  
}
.remittances-invoice::after {
  background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/remittances-invoice-br-design.png")) ?>") no-repeat center center;
 
}
.main-table-remittances table thead {
  background-color: #aedc9b;
}


.legal-fees-invoice::before {
   background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/legal-fees-invoice-tl-design.png")) ?>") no-repeat center center;
 
}
.legal-fees-invoice::after {
   background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/legal-fees-invoice-br-design.png")) ?>") no-repeat center center;
 
}

.main-table-legal-fees table thead {
  background-color: #ffad5b;
}


.direct-pays-invoice::before {
   background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/direct-pays-invoice-tl-design.png")) ?>") no-repeat center center;

}
.direct-pays-invoice::after {
   background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/direct-pays-invoice-br-design.png")) ?>") no-repeat center center;
 
}

.main-table-direct-pays table thead {
  background-color: #ecedef;
}

.court-costs-invoice::before {
  background: url("data:image/png;base64,' . $imageDatabefore . '") repeat center center/cover;
 
 
}
.court-costs-invoice::after {
  background: url("data:image/png;base64,' . $imageDataafter . '") repeat center center/cover;
 
}
.main-table-court-costs table thead {
  background-color: #ffe38f;
}



@media print {
  * {
    -moz-print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }

  .main-table {
    margin-top: 30px;
  }

  .invoice-header h1 {
    font-size: 40px;
    padding: 10px 0;
  }
  .invoice-sub-header,
  .due-table,
  .main-table,
  .main-content {
    padding: 10px 100px;
  }
  .invoice-body::before,
  .invoice-body::after {
    content: "";
    width: 80px;
    height: 300px;
  }
  .invoice-body::before {
    position: fixed;
    top: 0px;
    left: 0px;
  }
  .invoice-body::after {
    position: fixed;
    bottom: 0px;
    right: 0px;
  }
  .sample-invoice::before,
  .sample-invoice::after,
  .remittances-invoice::before,
  .remittances-invoice::after,
  .legal-fees-invoice::before,
  .legal-fees-invoice::after,
  .direct-pays-invoice::before,
  .direct-pays-invoice::after,
  .court-costs-invoice::before,
  .court-costs-invoice::after {
   /* display: none;*/
  }
 
  .sample-invoice-body::before {
     background: url("/var/www/html/bi/dist/images/sample-invoice-tl-design.png") no-repeat center center;
   
  }
  .sample-invoice-body::after {
    background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/sample-invoice-br-design.png")) ?>") no-repeat center center;
   
  }

  .remittances-invoice-body::before {
        background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/remittances-invoice-tl-design.png")) ?>") no-repeat center center;
   
   
  }

  .remittances-invoice-body::after {
     background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/remittances-invoice-br-design.png")) ?>") no-repeat center center;
    
  }

  .legal-fees-invoice-body::before {
     background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/legal-fees-invoice-tl-design.png")) ?>") no-repeat center center;
   
  }
  .legal-fees-invoice-body::after {
    background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/legal-fees-invoice-br-design.png")) ?>") no-repeat center center;
    
  }
 
  .direct-pays-invoice-body::before {
     background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/direct-pays-invoice-tl-design.png")) ?>") no-repeat center center;
   
  }
  .direct-pays-invoice-body::after {
    background: url("data:image/png;base64,<?= base64_encode(file_get_contents("/var/www/html/bi/dist/images/direct-pays-invoice-br-design.png")) ?>") no-repeat center center;
   
  }
 
  .court-costs-invoice-body::before {
    background: url("data:image/png;base64,' . $imageDatabefore . '") no-repeat center center/cover;
   
   
  }
  .court-costs-invoice-body::after {
     background: url("data:image/png;base64,' . $imageDataafter . '") no-repeat center center/cover;
   
  }
}

 table {
    width: 100% !important;
    border-collapse: collapse !important; 
  }



.page-break {
  page-break-after: always;
}
</style>';

if(count($main_resultcli)==0){
	writeLog("No client found for Dt:" . $billdate . " for Court-Cost");
	$Body="No client found for Dt:".$billdate." for Court-Cost";//echo $Body;
	mailsend($Body);
	writeLog("=== Court Cost Invoice Cron Job Completed (No Clients) ===");
}else{
//print_r($clientname)	;exit;
writeLog("Starting invoice generation for " . count($clientname) . " client(s)...");
$successfulPDFs = array();
$failedPDFs = array();

foreach ($clientname as $eachclients) {
  $htmlContent = ''; 
  $totalfirmall = 0;
   $htmlContent=''.$htmlContenthead;
	$eachclient=$eachclients->PYALORGCD;
	
	writeLog("Processing client: {$eachclient}");
	
	$clientadd.=$eachclient.',';
 
    // -------------------------------------------------------------------------
    // AH05052026 - start
    // Query to get BLAAINNM — replaced PDO $conndb2 with AS400 ODBC connection
    // -------------------------------------------------------------------------  
    // Query to get BLAAINNM
    writeLog("Fetching invoice numbers for client: {$eachclient}");
    $Query1 = "SELECT BLAAINNM FROM RMAACABLF WHERE PYALORGCD='" . $eachclient . "' and BILLDATE='" . $billdate . "' AND RMSTRANCDE='1A'  group by BLAAINNM";          
    // $Query1prep = $conndb2->prepare($Query1); //AH05052026
    // $Query1prep->execute(); //AH05052026
    // $Query1prep->setFetchMode(PDO::FETCH_OBJ); //AH05052026
    // $main_result = $Query1prep->fetchAll();  //AH05052026
        try {
        $as400conn   = connectAS400();
        $main_result = odbc_fetch_all_obj($as400conn, $Query1);
        odbc_close($as400conn);                        // AH05052026 - close after query
        writeLog("BLAAINNM query for client {$eachclient} returned " . count($main_result) . " record(s).", 'INFO');
    } catch (Exception $e) {
        writeLog("Failed to fetch BLAAINNM for client {$eachclient}: " . $e->getMessage(), 'ERROR');
        $main_result = [];
    }
    //AH05052026 - end

    foreach ($main_result as $row) {
        $BLAAINNM = $row->BLAAINNM;
        writeLog("Processing invoice: {$BLAAINNM} for client: {$eachclient}");

        // Query to get invoice details
        // $Query2 = "SELECT a.BLAAINNM, a.VENDORNUM, a.rmscorpnm2, a.rmscorpnm1, a.RMSACCTNUM, a.EXPORTDATE, a.RMSTRANDSC, a.RMSTRANCDE, a.pyalorgcd, SUM(a.INVCLIENT) AS INVCLIENT,
        //             b.RMSBRGLVL2, b.ASNADDR1, b.ASNCITY, b.ASNSTATE, b.ASNZIPCDE, b.CLIENTNAME
        //             FROM RMAACABHS a LEFT JOIN RMSPSYSASN b ON a.pyalorgcd = b.RMSBRGLVL2 
        //             WHERE a.BILLDATE = '" . $billdate . "' AND a.PYALORGCD = '" . $eachclient . "' AND a.BLAAINNM='" . $BLAAINNM . "' AND a.RMSTRANCDE='1A' 
        //             GROUP BY a.BLAAINNM, a.VENDORNUM";//echo   $Query2 ;exit;
      
        // ---------------------------------------------------------------------
        // AH05052026 - start
        // Query to get invoice details — replaced PDO $conndb2 with AS400 ODBC connection
        // ---------------------------------------------------------------------  
        // Query to get invoice details
$Query2="WITH
AggregatedTable1 AS (
    SELECT
        BLAAINNM,
        VENDORNUM,
        MIN(rmscorpnm2) AS rmscorpnm2,   --MIN() used to make it valid select list to run on AS400 without fail and get the actual value
        MIN(rmscorpnm1) AS rmscorpnm1,
        MIN(RMSACCTNUM) AS RMSACCTNUM,
        MIN(EXPORTDATE) AS EXPORTDATE,
        MIN(RMSTRANDSC) AS RMSTRANDSC,
        MIN(RMSTRANCDE) AS RMSTRANCDE,
        MIN(pyalorgcd) AS pyalorgcd,
        SUM(INVCLIENT) AS INVCLIENT,
        MIN(LVL2) AS LVL2,
        MIN(ROFFCD) AS ROFFCD
    FROM
        RMAACABLF
    WHERE
        billdate = '" . $billdate . "'
        AND RMSTRANCDE='1A'
        AND PYALORGCD = '" . $eachclient . "'
        AND BLAAINNM='" . $BLAAINNM . "'
    GROUP BY
        BLAAINNM, VENDORNUM
),
AggregatedTable2 AS (
    SELECT
        MIN(RCLNM1) AS RCLNM1,   
        MIN(RCLNM2) AS RCLNM2,
        MIN(RCLAD2) AS RCLAD2,
        MIN(RCLCTY) AS RCLCTY,
        MIN(RCLST) AS RCLST,
        MIN(RCLZIP) AS RCLZIP,
        RCLCD
    FROM
        AACALIB.RMRMCLNM
    GROUP BY
        RCLCD
)
SELECT
    A.BLAAINNM,
    A.VENDORNUM,
    A.rmscorpnm2,
    A.rmscorpnm1,
    A.RMSACCTNUM,
    A.EXPORTDATE,
    A.RMSTRANDSC,
    A.RMSTRANCDE,
    A.pyalorgcd,
    A.INVCLIENT,
    A.LVL2,
    A.ROFFCD,
    B.RCLNM1,
    B.RCLNM2,
    B.RCLAD2,
    B.RCLCTY,
    B.RCLST,
    B.RCLZIP,
    B.RCLCD
FROM
    AggregatedTable1 A
LEFT JOIN
    AggregatedTable2 B ON A.ROFFCD = B.RCLCD";
        // $Query2prep = $conndb2->prepare($Query2); //AH05052026
        // $Query2prep->execute(); //AH05052026
        // $Query2prep->setFetchMode(PDO::FETCH_OBJ); //AH05052026
        // $main_result2 = $Query2prep->fetchAll(); //AH05052026

            try {
            $as400conn    = connectAS400();
            $main_result2 = odbc_fetch_all_obj($as400conn, $Query2);
            odbc_close($as400conn);                        // AH05052026 - close after query
            writeLog("Invoice detail query for client {$eachclient} / BLAAINNM {$BLAAINNM} returned " . count($main_result2) . " record(s).", 'INFO');
        } catch (Exception $e) {
            writeLog("Failed to fetch invoice details for client {$eachclient} / BLAAINNM {$BLAAINNM}: " . $e->getMessage(), 'ERROR');
            $main_result2 = [];
        }
        // AH05052026 - end

        // Build the HTML content
        $htmlContent .= '<section class="court-costs-invoice">
            <header class="invoice-header">
                <div class="invoice-d-flex w-100"></div>
                    <div class="offset-w-75 w-25">
                      <img  class="logo invoice-img-responsive" src="data:image/png;base64,' . $imageData . '" alt="Company Logo"  scrolling="no">
                        
                    </div>
                    <div class="w-100 text-center">
                        <h1 class="fw-bolder">INVOICE</h1>
                    </div>
                </div>
            </header>
            <div class="invoice-sub-header">
                <div class="invoice-d-flex w-100">
                    <div class="w-75"> 
                        <p>';
        $htmlContent .= '<strong>Invoice To:' . $eachclient . '</strong><br />';
        $htmlContent .= $main_result2[0]->RCLCTY . " ," . $main_result2[0]->RCLST . ' ,' . $main_result2[0]->RCLZIP;
        $htmlContent .= '<br /></p></div>
                    <div class="w-25 text-right">
                        <p>';
        $htmlContent .= '<strong>Invoice #:</strong>' . $BLAAINNM . '<br />';
        $htmlContent .= '<strong>Date:</strong>' . date("m-d-Y", strtotime($billdate)) . '</p>
                    </div>
                </div>
            </div>';
        
        $htmlContent .= '<div class="due-table">
            <div class="w-100">
                <div class="invoice-table-responsive">
                    <table class="invoice-table invoice-table-bordered scroll-table">
                        <thead>
                            <tr>
                                <th>Invoice Type</th>
                                <th>Client Name</th>
                                <th>Payment Term</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                            <td>Court-Cost</td>';
        $htmlContent .= '<td>' . $main_result2[0]->RCLNM1.' '.$main_result2[0]->RCLNM2. '</td>';
        $htmlContent .= '<td>Due on Receipt</td>';
        $htmlContent .= '<td>' . date("m-d-Y", strtotime($billdate)) . '</td>';
        $htmlContent .= '</tr></tbody></table></div></div></div>';

        $htmlContent .= '<div class="main-table main-table-court-costs">
            <div class="w-100">
                <div class="invoice-table-responsive">
                    <table class="invoice-table invoice-table-bordered scroll-table">
                        <thead>
                            <tr>
                                <th scope="col">Firm Name</th>
                                <th scope="col">Total Court Cost</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        $total = 0;
        foreach ($main_result2 as $row2) {
            $total += $row2->INVCLIENT;
            $htmlContent .= '<tr>';
            $htmlContent .= '<td>' . $row2->LVL2 . '</td>';
            $htmlContent .= '<td>' . number_format($row2->INVCLIENT, 2). '</td>';
            $htmlContent .= '</tr>';
        } 
        
        $htmlContent .= '<tr>
            <td><strong>Total<strong></td>';
        $htmlContent .= '<td><strong>' . number_format($total, 2) . '</strong></td>';
        $htmlContent .= '</tr></tbody></table></div></div></div>';

        $htmlContent .= '<div class="main-table main-table-court-costs">
            <div class="w-100">
                <div class="invoice-table-responsive">
                    <table class="invoice-table invoice-table-bordered scroll-table">
                        <thead>
                            <tr>
                                <th scope="col">FIRM</th>
                                <th scope="col">DEBTOR</th>
                                <th scope="col">ACCOUNT NUMBER</th>
                                <th scope="col">DATE</th>
                                <th scope="col">CD</th>
                                <th scope="col">TRAN DESCRIPTION</th>
                                <th scope="col">AMOUNT</th>
                                <th scope="col">FIRM INV#</th>
                            </tr>
                        </thead>
                        <tbody>';

        // ---------------------------------------------------------------------
        // AH05052026 - start
        // Query for vendor numbers — replaced PDO $conndb2 with AS400 ODBC connection
        // ---------------------------------------------------------------------
        // Query for detailed data
        $Query2main = "SELECT VENDORNUM FROM RMAACABLF
            WHERE BILLDATE = '" . $billdate . "' AND RMSTRANCDE='1A' AND PYALORGCD = '" . $eachclient . "' AND BLAAINNM='" . $BLAAINNM . "'  group by VENDORNUM";
        // $Query2mainprep = $conndb2->prepare($Query2main);//AH05052026
        // $Query2mainprep->execute(); //AH05052026
        // $Query2mainprep->setFetchMode(PDO::FETCH_OBJ); //AH05052026
        // $main_result2main = $Query2mainprep->fetchAll();//AH05052026

            try {
            $as400conn       = connectAS400();
            $main_result2main = odbc_fetch_all_obj($as400conn, $Query2main);
            odbc_close($as400conn);                            // AH05052026 - close after query
            writeLog("Vendor number query for client {$eachclient} / BLAAINNM {$BLAAINNM} returned " . count($main_result2main) . " record(s).", 'INFO');
        } catch (Exception $e) {
            writeLog("Failed to fetch vendor numbers for client {$eachclient} / BLAAINNM {$BLAAINNM}: " . $e->getMessage(), 'ERROR');
            $main_result2main = [];
        }
        // AH05052026 - end

        $totalfirmwise = 0;

        // -----------------------------------------------------------------
        // AH05052026 - start
        // Query for detailed row data — replaced PDO $conndb2 with AS400 ODBC connection
        // -----------------------------------------------------------------

        foreach ($main_result2main as $mainVENDORNUM) {
            $VENDORNUM = $mainVENDORNUM->VENDORNUM;
            $getdata = "SELECT LVL2, RMSCORPNM1,RMSCORPNM2, BACCTN, RMSACCTNUM, PAIDDATE, RMSTRANCDE, RMSTRANDSC, INVCLIENT, VENDORNUM, INVOICENO from RMAACABLF
                WHERE BILLDATE = '" . $billdate . "' AND RMSTRANCDE='1A' AND PYALORGCD = '" . $eachclient . "' AND BLAAINNM='" . $BLAAINNM . "' AND VENDORNUM='" . $VENDORNUM . "'";
            // $getdataprep = $conndb2->prepare($getdata); //AH05052026
            // $getdataprep->execute(); //AH05052026
            // $getdataprep->setFetchMode(PDO::FETCH_OBJ); //AH05052026
            // $getdatres = $getdataprep->fetchAll(); //AH05052026
                try {
                $as400conn  = connectAS400();
                $getdatres  = odbc_fetch_all_obj($as400conn, $getdata);
                odbc_close($as400conn);                        // AH05052026 - close after query
                writeLog("Detail data query for client {$eachclient} / VENDORNUM {$VENDORNUM} returned " . count($getdatres) . " record(s).", 'INFO');
            } catch (Exception $e) {
                writeLog("Failed to fetch detail data for client {$eachclient} / VENDORNUM {$VENDORNUM}: " . $e->getMessage(), 'ERROR');
                $getdatres = [];
            }
            // AH05052026 - end

            $amntmid=0;
            foreach ($getdatres as $maidata) {
                $totalfirmwise += $maidata->INVCLIENT;
                $totalfirmall += $maidata->INVCLIENT;
                $amntmid +=$maidata->INVCLIENT;

                $htmlContent .= '<tr>';
                $htmlContent .= '<td>' . $maidata->LVL2 . '</td>';
                $htmlContent .= '<td>' . $maidata->RMSCORPNM2.' '.$maidata->RMSCORPNM1 . '</td>';
                $htmlContent .= '<td>' . $maidata->RMSACCTNUM . '</td>';
                $htmlContent .= '<td>' . $maidata->PAIDDATE . '</td>';
                $htmlContent .= '<td>' . $maidata->RMSTRANCDE . '</td>';
                $htmlContent .= '<td>' . $maidata->RMSTRANDSC . '</td>';
                $htmlContent .= '<td>' . number_format($maidata->INVCLIENT, 2) . '</td>';
                $htmlContent .= '<td>' . $maidata->VENDORNUM . '-' . $maidata->INVOICENO . '</td>';
                $htmlContent .= '</tr>'; 
            }
            $htmlContent .= '<tr><td><strong>'.$maidata->LVL2.' </strong></td>';
       
        $htmlContent .= '<td></td>';
        $htmlContent .= '<td></td>';
        $htmlContent .= '<td></td>';
        $htmlContent .= '<td></td>';
        $htmlContent .= '<td></td>';
        $htmlContent .= '<td><strong>' . number_format($amntmid, 2). '</strong></td>';
        $htmlContent .= '<td></td>';
        }

        
        $htmlContent .= '</tr></tbody></table></div></div></div>';
        $htmlContent .= '<div class="main-content">
            <div class="w-100 text-right">
                <p>
                    40 Northwood Blvd.Suits.C.<br />
                    Columbus, Ohio 43235<br />
                    614/523-2251<br />
                    www.aacanet.com
                </p>
            </div>
        </div>
        <div class="page-break"></div>';
    }
    // Final total
$htmlContent .= '<div class="main-table main-table-court-costs">
    <div class="w-100">
        <div class="invoice-table-responsive">
            <table class="invoice-table invoice-table-bordered scroll-table">
                <thead>
                    <tr>
                        <th scope="col">Overall Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . number_format($totalfirmall, 2). '</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</html>
';

//echo $htmlContent;
//You can uncomment the below lines to save the PDF file if needed
// if (file_exists('/var/www/html/bi/dist/Invoicing/invfile/'.$eachclient.'-COURTCOST_'.$billdate.'.pdf')) {
//     unlink('/var/www/html/bi/dist/Invoicing/invfile/'.$eachclient.'-COURTCOST_'.$billdate.'.pdf'); // Deletes the existing file
// }
//$snappy->generateFromHtml($htmlContent, '/var/www/html/bi/dist/Invoicing/invfile/'.$eachclient.'-COURTCOST_'.$billdate.'.pdf');

 // Generate PDF
 writeLog("Generating PDF for client {$eachclient}...");
 try {
     $pdfPath = '/var/www/html/bi/dist/Mako/downloadfile/'.$eachclient.'/AC/'.$eachclient.'-COURTCOST_'.$billdate.'.pdf';
     $snappy->generateFromHtml($htmlContent, $pdfPath);
     
     // Check if PDF was created successfully
     if (file_exists($pdfPath)) {
         $fileSize = filesize($pdfPath);
         writeLog("SUCCESS: PDF generated successfully for {$eachclient} (Size: {$fileSize} bytes)");
         $successfulPDFs[] = $eachclient;
     } else {
         writeLog("ERROR: PDF file not found after generation for {$eachclient}");
         $failedPDFs[] = $eachclient;
     }
 } catch (Exception $e) {
     writeLog("ERROR: Exception during PDF generation for {$eachclient}: " . $e->getMessage());
     $failedPDFs[] = $eachclient;
 }
 }
 
 // Log summary
 writeLog("--- Invoice Generation Summary ---");
 writeLog("Total clients processed: " . count($clientname));
 writeLog("Successful PDF generations: " . count($successfulPDFs));
 if (count($failedPDFs) > 0) {
     writeLog("Failed PDF generations: " . count($failedPDFs));
     writeLog("Failed clients: " . implode(", ", $failedPDFs));
 }
 if (count($successfulPDFs) > 0) {
     writeLog("Client codes: " . implode(",", $successfulPDFs));
     writeLog("Successfully generated PDFs for: " . implode(", ", $successfulPDFs));
 }
 
 $body="Court-cost invoices generated for ".rtrim($clientadd, ',');
 mailsend($body);
 writeLog("Email notification sent");
 writeLog("=== Court Cost Invoice Cron Job Completed Successfully ===");
}
 function mailsend($body){
 	  include_once('/var/www/html/bi/dist/mailsetup.php');
          $mail->addAddress('tbeal@aacanet.org');
          $mail->addAddress('dpriest@aacanet.org');
          //$mail->addAddress('bandana.kumari@goolean.tech');
          $mail->addAddress('droberts@aacanet.org');
          $mail->addAddress('tbalcerzak@aacanet.org');
          $mail->addAddress('vishalkul94@gmail.com');

          $mail->isHTML(true);
         // $mail->SMTPDebug = 2;
          //$mail->SMTPDebug = SMTP::DEBUG_SERVER;  
          $mail-> Subject= 'Invoicing';
          $mail-> Body= "<p>".$body."</p>";
          $mail->send();

 }
?>



 


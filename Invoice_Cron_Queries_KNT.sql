-- ============================================================================
-- File: Invoice_Cron_Queries_KNT.sql
-- Author: KEANT Technologies
-- Description:
--   Consolidated SQL queries extracted from:
--   1) Court_costs_cron_KNT.php
--   2) Direct_pay_cron_KNT.php
--   3) Remittance_cron_KNT.php
--
-- Notes:
--   Replace bind-like placeholders before execution:
--     :billdate   -> YYYYMMDD
--     :eachclient -> Client code (PYALORGCD)
--     :blaainnm   -> Invoice number
--     :vendornum  -> Vendor number
-- ============================================================================


-- ###########################################################################
-- COURT COST (RMSTRANCDE = '1A')
-- Source: Court_costs_cron_KNT.php
-- ###########################################################################

-- [CC-1] Client list for bill date
SELECT PYALORGCD
FROM RMAACABHS
WHERE BILLDATE = :billdate
  AND RMSTRANCDE = '1A'
GROUP BY PYALORGCD;

-- [CC-2] Invoice numbers per client
SELECT BLAAINNM
FROM RMAACABHS
WHERE PYALORGCD = :eachclient
  AND BILLDATE = :billdate
  AND RMSTRANCDE = '1A'
GROUP BY BLAAINNM;

-- [CC-3] Invoice summary (CTE)
WITH AggregatedTable1 AS (
    SELECT
        BLAAINNM,
        VENDORNUM,
        rmscorpnm2,
        rmscorpnm1,
        RMSACCTNUM,
        EXPORTDATE,
        RMSTRANDSC,
        RMSTRANCDE,
        pyalorgcd,
        SUM(INVCLIENT) AS INVCLIENT,
        LVL2,
        ROFFCD
    FROM RMAACABHS
    WHERE billdate = :billdate
      AND RMSTRANCDE = '1A'
      AND PYALORGCD = :eachclient
      AND BLAAINNM = :blaainnm
    GROUP BY BLAAINNM, VENDORNUM
),
AggregatedTable2 AS (
    SELECT
        RCLNM1,
        RCLNM2,
        RCLAD2,
        RCLCTY,
        RCLST,
        RCLZIP,
        RCLCD
    FROM RMRMCLNM
    GROUP BY RCLCD
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
FROM AggregatedTable1 A
LEFT JOIN AggregatedTable2 B
       ON A.ROFFCD = B.RCLCD;

-- [CC-4] Vendor list per client + invoice
SELECT VENDORNUM
FROM RMAACABHS
WHERE BILLDATE = :billdate
  AND RMSTRANCDE = '1A'
  AND PYALORGCD = :eachclient
  AND BLAAINNM = :blaainnm
GROUP BY VENDORNUM;

-- [CC-5] Detail rows per vendor
SELECT
    LVL2,
    RMSCORPNM1,
    RMSCORPNM2,
    BACCTN,
    RMSACCTNUM,
    PAIDDATE,
    RMSTRANCDE,
    RMSTRANDSC,
    INVCLIENT,
    VENDORNUM,
    INVOICENO
FROM RMAACABHS
WHERE BILLDATE = :billdate
  AND RMSTRANCDE = '1A'
  AND PYALORGCD = :eachclient
  AND BLAAINNM = :blaainnm
  AND VENDORNUM = :vendornum;


-- ###########################################################################
-- REMITTANCE (RMSTRANCDE 50-59 EXCLUDING 51, BLAAINNM LIKE '%R%')
-- Source: Remittance_cron_KNT.php
-- ###########################################################################

-- [RM-1] Client list for bill date
SELECT PYALORGCD
FROM RMAACABHS
WHERE BILLDATE = :billdate
  AND RMSTRANCDE >= '50'
  AND RMSTRANCDE <= '59'
  AND RMSTRANCDE <> '51'
  AND BLAAINNM LIKE '%R%'
GROUP BY PYALORGCD;

-- [RM-2] Invoice numbers per client
SELECT BLAAINNM
FROM RMAACABHS
WHERE PYALORGCD = :eachclient
  AND BILLDATE = :billdate
  AND RMSTRANCDE >= '50'
  AND RMSTRANCDE <= '59'
  AND RMSTRANCDE <> '51'
  AND BLAAINNM LIKE '%R%'
GROUP BY BLAAINNM;

-- [RM-3] Invoice summary (CTE)
WITH AggregatedTable1 AS (
    SELECT
        BLAAINNM,
        VENDORNUM,
        rmscorpnm2,
        rmscorpnm1,
        RMSACCTNUM,
        EXPORTDATE,
        RMSTRANDSC,
        RMSTRANCDE,
        pyalorgcd,
        SUM(FEES) AS FEES,
        SUM(COLLAM) AS COLLAM,
        SUM(SETASIDES) AS SETASIDES,
        SUM(FEESA) AS FEESA,
        LVL2,
        ROFFCD
    FROM RMAACABHS
    WHERE billdate = :billdate
      AND RMSTRANCDE >= '50'
      AND RMSTRANCDE <= '59'
      AND RMSTRANCDE <> '51'
      AND BLAAINNM LIKE '%R%'
      AND PYALORGCD = :eachclient
      AND BLAAINNM = :blaainnm
    GROUP BY BLAAINNM, VENDORNUM
),
AggregatedTable2 AS (
    SELECT
        RCLNM1,
        RCLNM2,
        RCLAD2,
        RCLCTY,
        RCLST,
        RCLZIP,
        RCLCD
    FROM RMRMCLNM
    GROUP BY RCLCD
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
    A.FEES,
    A.COLLAM,
    A.SETASIDES,
    A.FEESA,
    A.LVL2,
    A.ROFFCD,
    B.RCLNM1,
    B.RCLNM2,
    B.RCLAD2,
    B.RCLCTY,
    B.RCLST,
    B.RCLZIP,
    B.RCLCD
FROM AggregatedTable1 A
LEFT JOIN AggregatedTable2 B
       ON A.ROFFCD = B.RCLCD;

-- [RM-4] Vendor list per client + invoice
SELECT VENDORNUM
FROM RMAACABHS
WHERE BILLDATE = :billdate
  AND PYALORGCD = :eachclient
  AND BLAAINNM = :blaainnm
  AND RMSTRANCDE >= '50'
  AND RMSTRANCDE <= '59'
  AND RMSTRANCDE <> '51'
  AND BLAAINNM LIKE '%R%'
GROUP BY VENDORNUM;

-- [RM-5] Detail rows per vendor
SELECT
    LVL2,
    RMSCORPNM1,
    RMSCORPNM2,
    BACCTN,
    RMSACCTNUM,
    PAIDDATE,
    RMSTRANCDE,
    RMSTRANDSC,
    INVCLIENT,
    VENDORNUM,
    INVOICENO,
    COLLAM,
    FEEST,
    SETASIDES
FROM RMAACABHS
WHERE BILLDATE = :billdate
  AND PYALORGCD = :eachclient
  AND BLAAINNM = :blaainnm
  AND RMSTRANCDE >= '50'
  AND RMSTRANCDE <= '59'
  AND RMSTRANCDE <> '51'
  AND BLAAINNM LIKE '%R%'
  AND VENDORNUM = :vendornum;


-- ###########################################################################
-- DIRECT PAY (RMSTRANCDE = '51')
-- Source: Direct_pay_cron_KNT.php
-- ###########################################################################

-- [DP-1] Client list for bill date
SELECT PYALORGCD
FROM RMAACABHS
WHERE BILLDATE = :billdate
  AND RMSTRANCDE = '51'
GROUP BY PYALORGCD;

-- [DP-2] Invoice numbers per client
SELECT BLAAINNM
FROM RMAACABHS
WHERE PYALORGCD = :eachclient
  AND BILLDATE = :billdate
  AND RMSTRANCDE = '51'
GROUP BY BLAAINNM;

-- [DP-3] Invoice summary (CTE)
WITH AggregatedTable1 AS (
    SELECT
        BLAAINNM,
        VENDORNUM,
        rmscorpnm2,
        rmscorpnm1,
        RMSACCTNUM,
        EXPORTDATE,
        RMSTRANDSC,
        RMSTRANCDE,
        pyalorgcd,
        BLPYFRTO,
        SUM(FEESFR) AS FEESFR,
        SUM(FEESA) AS FEESA,
        LVL2,
        ROFFCD
    FROM RMAACABHS
    WHERE billdate = :billdate
      AND RMSTRANCDE = '51'
      AND PYALORGCD = :eachclient
      AND BLAAINNM = :blaainnm
    GROUP BY BLAAINNM, VENDORNUM
),
AggregatedTable2 AS (
    SELECT
        RCLNM1,
        RCLNM2,
        RCLAD2,
        RCLCTY,
        RCLST,
        RCLZIP,
        RCLCD
    FROM RMRMCLNM
    GROUP BY RCLCD
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
    A.BLPYFRTO,
    A.FEESFR,
    A.FEESA,
    A.LVL2,
    A.ROFFCD,
    B.RCLNM1,
    B.RCLNM2,
    B.RCLAD2,
    B.RCLCTY,
    B.RCLST,
    B.RCLZIP,
    B.RCLCD
FROM AggregatedTable1 A
LEFT JOIN AggregatedTable2 B
       ON A.ROFFCD = B.RCLCD;

-- [DP-4] Vendor list per client + invoice
SELECT VENDORNUM
FROM RMAACABHS
WHERE BILLDATE = :billdate
  AND PYALORGCD = :eachclient
  AND BLAAINNM = :blaainnm
  AND RMSTRANCDE = '51'
GROUP BY VENDORNUM;

-- [DP-5] Detail rows per vendor
SELECT
    LVL2,
    RMSCORPNM1,
    RMSCORPNM2,
    RMSACCTNUM,
    PAIDDATE,
    RMSTRANCDE,
    RMSTRANDSC,
    INVCLIENT,
    VENDORNUM,
    INVOICENO,
    DUECLIENT,
    FEEST,
    SETASIDES,
    COLLAM
FROM RMAACABHS
WHERE BILLDATE = :billdate
  AND PYALORGCD = :eachclient
  AND BLAAINNM = :blaainnm
  AND RMSTRANCDE = '51'
  AND VENDORNUM = :vendornum;

# Stacked Invoice Processor

This collection of scripts performs Xerox invoice extraction for Stacked. PDF's can be sent in via email. The scripts then perform the following steps:

1. Read email in specified mail address. Save PDF attachments to the database
2. Extract the PDF as an image
3. OCR the image, extract as text
4. Generate an invoice record in JSON format
5. Export the invoices as CSV records
6. Archive the exported records

## Configuration

There is a file called cron_config.php in the includes folder which contains all of the required variable names and configuration options.

## Composer Packages

The system uses PHP Mailer to send the email to the end user. The mails are sent using the SMTP2Go service.

## Status Codes

Transaction Level Status codes:
- A list of transaction status codes are assigned to each record in the tbl_txn table.
- An explanation of transaction status codes is contained in the tbl_statuscodes

Invoice Extraction Status Codes:
- A list of Invoice Extraction status codes are assigned to each record in the to tbl_txn table
- An explanation of extraction status codes is contained in the tbl_invoiceextractstatuscodes

System Codes:
- The tbl_status is used to track system status. A single digit X (e.g.1) means process is not running. A three digit code XXX means process is running (e.g. 100)


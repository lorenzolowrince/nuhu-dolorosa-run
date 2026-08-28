<?php
declare(strict_types=1);

// ============================================================
// Email Service — PHPMailer wrapper with SMTP
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

class EmailService
{
    // --- Send registration confirmation to participant ---
    public static function sendConfirmation(array $participant, array $payment, array $category): bool
    {
        $subject = 'Registration Confirmed – ' . getSetting('event_name');
        $body    = self::buildConfirmationEmail($participant, $payment, $category);
        return self::send($participant['email'], $participant['full_name'], $subject, $body);
    }

    // --- Send registration pending / bank transfer instructions email ---
    public static function sendRegistrationPending(array $participant, array $category, float $totalFee = 0.0): bool
    {
        $subject = 'Registration Received – ' . getSetting('event_name');
        $body    = self::buildPendingEmail($participant, $category, $totalFee);
        return self::send($participant['email'], $participant['full_name'], $subject, $body);
    }

    // --- Send registration confirmed (when admin sets status to Confirmed) ---
    public static function sendRegistrationConfirmed(array $participant): bool
    {
        $subject = 'Registration Confirmed! – ' . getSetting('event_name');
        $body    = self::buildRegistrationConfirmedEmail($participant);
        return self::send($participant['email'], $participant['full_name'], $subject, $body);
    }

    // --- Send payment failure notification ---
    public static function sendPaymentFailed(array $participant): bool
    {
        $subject = 'Payment Unsuccessful – ' . getSetting('event_name');
        $body    = self::buildFailedEmail($participant);
        return self::send($participant['email'], $participant['full_name'], $subject, $body);
    }

    // --- Core send method ---
    private static function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        $autoloaderPaths = [
            BASE_PATH . '/vendor/autoload.php',
            BASE_PATH . '/../vendor/autoload.php',
        ];

        $autoloaded = false;
        foreach ($autoloaderPaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $autoloaded = true;
                break;
            }
        }

        if (!$autoloaded || !class_exists(PHPMailer::class)) {
            // Fallback to native mail() if PHPMailer not available
            return self::sendNative($toEmail, $toName, $subject, $htmlBody);
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host        = MAIL_HOST;
            $mail->SMTPAuth    = true;
            $mail->Username    = MAIL_USERNAME;
            $mail->Password    = MAIL_PASSWORD;
            $mail->SMTPSecure  = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port        = MAIL_PORT;
            $mail->CharSet     = 'UTF-8';

            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            return $mail->send();
        } catch (MailException $e) {
            error_log('Mail error: ' . $e->getMessage());
            return false;
        }
    }

    private static function sendNative(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">\r\n";
        return mail($toEmail, $subject, $htmlBody, $headers);
    }

    // --- Email templates ---
    private static function buildConfirmationEmail(array $p, array $payment, array $category): string
    {
        $eventName  = e(getSetting('event_name'));
        $eventDate  = e(getSetting('event_date'));
        $eventVenue = e(getSetting('event_venue'));
        $contact    = self::getSetting('contact_email');
        $year       = self::year();

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Confirmed</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 10px; overflow: hidden; }
            .header { background: linear-gradient(135deg, #6B21A8, #7C3AED); color: #fff; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .badge { background: #F59E0B; color: #fff; padding: 5px 15px; border-radius: 20px; font-size: 13px; margin-top: 8px; display: inline-block; }
            .content { padding: 30px; }
            .detail-box { background: #f8f4ff; border-left: 4px solid #6B21A8; padding: 15px 20px; border-radius: 0 8px 8px 0; margin: 20px 0; }
            .detail-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e9d5ff; }
            .detail-row:last-child { border-bottom: none; font-weight: bold; }
            .label { color: #6b7280; font-size: 14px; }
            .value { font-weight: 600; font-size: 14px; }
            .ref-box { background: #F59E0B; color: #fff; text-align: center; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .ref-box .ref { font-size: 22px; font-weight: bold; letter-spacing: 2px; }
            .footer { background: #1f2937; color: #9ca3af; text-align: center; padding: 20px; font-size: 12px; }
        </style>
        </head>
        <body>
        <div class="container">
            <div class="header">
                <h1>🏃 Registration Confirmed!</h1>
                <div class="badge">Run For Hope</div>
                <p style="margin:10px 0 0">{$eventName}</p>
            </div>
            <div class="content">
                <p>Dear <strong>{$p['full_name']}</strong>,</p>
                <p>Your registration has been confirmed and payment received. See you at the race!</p>

                <div class="ref-box">
                    <div style="font-size:13px;opacity:0.9;">Your Registration Reference</div>
                    <div class="ref">{$p['reference_number']}</div>
                </div>

                <div class="detail-box">
                    <div class="detail-row"><span class="label">Event</span><span class="value">{$eventName}</span></div>
                    <div class="detail-row"><span class="label">Date</span><span class="value">{$eventDate}</span></div>
                    <div class="detail-row"><span class="label">Venue</span><span class="value">{$eventVenue}</span></div>
                    <div class="detail-row"><span class="label">Category</span><span class="value">{$category['name']}</span></div>
                    <div class="detail-row"><span class="label">IC Number</span><span class="value">{$p['ic_number']}</span></div>
                    <div class="detail-row"><span class="label">Payment Method</span><span class="value">{$payment['gateway']}</span></div>
                    <div class="detail-row"><span class="label">Total Paid</span><span class="value">RM {$payment['total_amount']}</span></div>
                </div>

                <p style="color:#6b7280;font-size:14px;">Please keep this reference number for collection of your race pack on event day.</p>
                <p style="color:#6b7280;font-size:14px;">For enquiries, contact us at <a href="mailto:{$contact}">{$contact}</a></p>
            </div>
            <div class="footer">
                <p>{$eventName} &copy; {$year}</p>
                <p>This is an automated email. Please do not reply.</p>
            </div>
        </div>
        </body></html>
        HTML;
    }

    private static function buildFailedEmail(array $p): string
    {
        $eventName = e(getSetting('event_name'));
        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8"><title>Payment Unsuccessful</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; }
            .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 10px; overflow: hidden; }
            .header { background: #dc2626; color: #fff; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .footer { background: #1f2937; color: #9ca3af; text-align: center; padding: 20px; font-size: 12px; }
        </style>
        </head>
        <body>
        <div class="container">
            <div class="header"><h1>Payment Unsuccessful</h1></div>
            <div class="content">
                <p>Dear <strong>{$p['full_name']}</strong>,</p>
                <p>Your payment for <strong>{$eventName}</strong> (Ref: <strong>{$p['reference_number']}</strong>) was not completed.</p>
                <p>Please return to the website and try registering again. Your registration slot is still available.</p>
                <p>Contact us if you need assistance.</p>
            </div>
            <div class="footer"><p>{$eventName}</p></div>
        </div>
        </body></html>
        HTML;
    }

    private static function buildPendingEmail(array $p, array $cat, float $totalFee): string
    {
        $eventName  = e(getSetting('event_name'));
        $bankName   = 'Public Bank Berhad';
        $bankAcc    = 'RTDKK St. Anne Penampang Baru';
        $bankNum    = '3242653113';
        $contact    = self::getSetting('contact_email');
        $year       = self::year();
        $ref        = e($p['reference_number']);
        $catName    = e($cat['name'] ?? $cat['category_name'] ?? '');
        $fee        = number_format($totalFee > 0 ? $totalFee : (float)($cat['fee'] ?? 0), 2);
        $appUrl     = APP_URL;
        $uploadUrl  = $appUrl . '/upload-receipt.php?ref=' . urlencode($p['reference_number']);
        $qrUrl      = $appUrl . '/assets/images/duitnow-qr.png';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Received</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 10px; overflow: hidden; }
            .header { background: linear-gradient(135deg, #6B21A8, #7C3AED); color: #fff; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 22px; }
            .badge { background: #F59E0B; color: #fff; padding: 5px 15px; border-radius: 20px; font-size: 13px; margin-top: 8px; display: inline-block; }
            .content { padding: 30px; }
            .ref-box { background: #F59E0B; color: #fff; text-align: center; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .ref-box .ref { font-size: 22px; font-weight: bold; letter-spacing: 2px; }
            .bank-box { background: #eff6ff; border-left: 4px solid #2563eb; padding: 15px 20px; border-radius: 0 8px 8px 0; margin: 20px 0; }
            .bank-row { padding: 5px 0; border-bottom: 1px solid #dbeafe; font-size: 14px; }
            .bank-row:last-child { border-bottom: none; }
            .label { color: #6b7280; display: inline-block; width: 130px; }
            .footer { background: #1f2937; color: #9ca3af; text-align: center; padding: 20px; font-size: 12px; }
        </style>
        </head>
        <body>
        <div class="container">
            <div class="header">
                <h1>&#127881; Registration Received!</h1>
                <div class="badge">Run For Hope</div>
                <p style="margin:10px 0 0">{$eventName}</p>
            </div>
            <div class="content">
                <p>Dear <strong>{$p['full_name']}</strong>,</p>
                <p>Thank you for registering! Your spot is <strong>reserved</strong>. Please complete your payment to confirm your registration.</p>

                <div class="ref-box">
                    <div style="font-size:13px;opacity:0.9;">Your Registration Reference</div>
                    <div class="ref">{$ref}</div>
                    <div style="font-size:12px;opacity:0.85;margin-top:5px;">Use this as your payment reference / note</div>
                </div>

                <p><strong>Category:</strong> {$catName} &nbsp; | &nbsp; <strong>Amount Due:</strong> RM {$fee}</p>

                <!-- Payment options side by side -->
                <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;">
                    <tr>
                        <!-- Bank Transfer -->
                        <td width="58%" valign="top" style="padding-right:10px;">
                            <div class="bank-box">
                                <p style="font-weight:700;margin:0 0 8px;color:#1e40af;">Bank Transfer</p>
                                <div class="bank-row"><span class="label">Bank</span> <strong>{$bankName}</strong></div>
                                <div class="bank-row"><span class="label">Account Name</span> <strong>{$bankAcc}</strong></div>
                                <div class="bank-row"><span class="label">Account No.</span> <strong style="font-size:16px;color:#1d4ed8;">{$bankNum}</strong></div>
                                <div class="bank-row"><span class="label">Payment Ref</span> <strong>{$ref}</strong></div>
                            </div>
                        </td>
                        <!-- DuitNow QR -->
                        <td width="42%" valign="top" style="padding-left:10px;text-align:center;">
                            <div style="border:1.5px solid #fce7f3;border-radius:10px;padding:12px;background:#fff5f7;display:inline-block;width:100%;box-sizing:border-box;">
                                <p style="margin:0 0 8px;font-weight:700;color:#e11d6a;font-size:12px;">Scan to Pay — DuitNow QR</p>
                                <img src="{$qrUrl}"
                                     alt="DuitNow QR"
                                     width="160" height="160"
                                     style="display:block;margin:0 auto;border-radius:6px;">
                                <p style="margin:6px 0 0;font-size:10px;font-weight:700;color:#374151;">RTDKK ST. ANNE PENAMPANG BARU</p>
                                <p style="margin:2px 0 0;font-size:9px;color:#9ca3af;">All Banks &amp; e-Wallets</p>
                            </div>
                        </td>
                    </tr>
                </table>

                <p style="font-size:13px;color:#6b7280;background:#fef9c3;padding:10px 14px;border-radius:6px;border-left:3px solid #d97706;">
                    <strong>Note:</strong> If the QR code image does not display, please use the bank transfer details on the left to make payment.
                </p>

                <p style="font-size:14px;color:#6b7280;margin-top:20px;">After making the payment, please upload your receipt at:<br>
                <a href="{$uploadUrl}" style="color:#2563eb;">{$uploadUrl}</a></p>

                <p style="color:#6b7280;font-size:14px;">For enquiries, contact us at <a href="mailto:{$contact}">{$contact}</a></p>
            </div>
            <div class="footer">
                <p>{$eventName} &copy; {$year}</p>
                <p>This is an automated email. Please do not reply.</p>
            </div>
        </div>
        </body></html>
        HTML;
    }

    private static function buildRegistrationConfirmedEmail(array $p): string
    {
        $eventName  = e(getSetting('event_name'));
        $eventDate  = e(getSetting('event_date'));
        $eventVenue = e(getSetting('event_venue'));
        $contact    = self::getSetting('contact_email');
        $year       = self::year();
        $ref        = e($p['reference_number']);
        $catName    = e($p['category_name'] ?? '');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Confirmed</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 10px; overflow: hidden; }
            .header { background: linear-gradient(135deg, #059669, #10b981); color: #fff; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 22px; }
            .badge { background: #F59E0B; color: #fff; padding: 5px 15px; border-radius: 20px; font-size: 13px; margin-top: 8px; display: inline-block; }
            .content { padding: 30px; }
            .detail-box { background: #f0fdf4; border-left: 4px solid #16a34a; padding: 15px 20px; border-radius: 0 8px 8px 0; margin: 20px 0; }
            .detail-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #bbf7d0; font-size:14px; }
            .detail-row:last-child { border-bottom: none; font-weight: bold; }
            .ref-box { background: #F59E0B; color: #fff; text-align: center; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .ref-box .ref { font-size: 22px; font-weight: bold; letter-spacing: 2px; }
            .footer { background: #1f2937; color: #9ca3af; text-align: center; padding: 20px; font-size: 12px; }
        </style>
        </head>
        <body>
        <div class="container">
            <div class="header">
                <h1>✅ Registration Confirmed!</h1>
                <div class="badge">Payment Verified</div>
                <p style="margin:10px 0 0">{$eventName}</p>
            </div>
            <div class="content">
                <p>Dear <strong>{$p['full_name']}</strong>,</p>
                <p>Great news! Your payment has been verified and your registration is now <strong>confirmed</strong>. See you at the race!</p>

                <div class="ref-box">
                    <div style="font-size:13px;opacity:0.9;">Your Registration Reference</div>
                    <div class="ref">{$ref}</div>
                </div>

                <div class="detail-box">
                    <div class="detail-row"><span style="color:#6b7280;">Event</span><span>{$eventName}</span></div>
                    <div class="detail-row"><span style="color:#6b7280;">Date</span><span>{$eventDate}</span></div>
                    <div class="detail-row"><span style="color:#6b7280;">Venue</span><span>{$eventVenue}</span></div>
                    <div class="detail-row"><span style="color:#6b7280;">Category</span><span>{$catName}</span></div>
                    <div class="detail-row"><span style="color:#6b7280;">IC Number</span><span>{$p['ic_number']}</span></div>
                </div>

                <p style="color:#6b7280;font-size:14px;">Please keep this reference number for race pack collection on event day.</p>
                <p style="color:#6b7280;font-size:14px;">For enquiries, contact us at <a href="mailto:{$contact}">{$contact}</a></p>
            </div>
            <div class="footer">
                <p>{$eventName} &copy; {$year}</p>
                <p>This is an automated email. Please do not reply.</p>
            </div>
        </div>
        </body></html>
        HTML;
    }

    private static function getSetting(string $key): string
    {
        return \getSetting($key);
    }

    private static function year(): string
    {
        return date('Y');
    }
}

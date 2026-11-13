<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Storkia Verification Code</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background-color:#F5F5F4;font-family:'Plus Jakarta Sans',Arial,Helvetica,sans-serif;color:#292524;-webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F5F5F4;padding:40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:480px;background-color:#FFFFFF;border-radius:20px;border:1px solid #E7E5E4;overflow:hidden;box-shadow:0 4px 12px rgba(93,49,64,0.06);">
                    <tr>
                        <td align="center" style="background-color:#5D3140;padding:30px 24px;border-bottom:3px solid #CF4173;">
                            <span style="font-family:'DM Serif Display',Georgia,serif;font-size:34px;color:#FFFFFF;font-weight:normal;letter-spacing:1px;display:inline-block;">
                                Storkia
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 32px;text-align:center;">
                            <h1 style="font-family:'Plus Jakarta Sans',Arial,sans-serif;font-size:22px;font-weight:700;color:#5D3140;margin:0 0 10px;">
                                Account Verification
                            </h1>
                            <p style="font-family:'Plus Jakarta Sans',Arial,sans-serif;font-size:14px;color:#78716C;margin:0 0 28px;line-height:1.6;">
                                Enter this 6-digit verification code to confirm your email address and continue on Storkia.
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px;">
                                <tr>
                                    <td align="center">
                                        <div style="display:inline-block;background-color:#FDF8F3;border:2px dashed #CF4173;border-radius:16px;padding:18px 32px;">
                                            <span style="font-family:'Plus Jakarta Sans','Courier New',monospace;font-size:38px;font-weight:800;letter-spacing:10px;color:#CF4173;display:inline-block;margin-left:10px;">
                                                {{ $code }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <div style="background-color:#F5F5F4;border-radius:12px;padding:14px 18px;text-align:center;">
                                <p style="font-family:'Plus Jakarta Sans',Arial,sans-serif;font-size:13px;color:#78716C;margin:0;line-height:1.5;">
                                    This code will expire in <strong style="color:#292524;">{{ $expiresInMinutes }} minutes</strong>.<br>
                                    If you did not request this code, you can safely ignore this email.
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#FAF8F6;padding:18px 24px;text-align:center;border-top:1px solid #E7E5E4;">
                            <p style="font-family:'Plus Jakarta Sans',Arial,sans-serif;font-size:12px;color:#78716C;margin:0;">
                                &copy; {{ date('Y') }} {{ config('app.name', 'Storkia') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
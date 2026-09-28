<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Storkia Verification Code</title>
    <!-- Web Font Imports for Compatible Email Clients -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/emails/registration-otp.css'])
</head>
<body class="email-body">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="email-wrapper-table">
        <tr>
            <td align="center">
                <!-- Main Container Card -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="email-card-table">
                    
                    <!-- Header Bar -->
                    <tr>
                        <td align="center" class="header-cell">
                            <span class="brand-font header-brand">
                                Storkia
                            </span>
                        </td>
                    </tr>

                    <!-- Card Body -->
                    <tr>
                        <td class="card-body-cell">
                            <h1 class="body-font title-text">
                                Account Verification
                            </h1>
                            <p class="body-font subtitle-text">
                                Enter this code to verify your email and complete registration.
                            </p>

                            <!-- OTP Box -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="otp-table">
                                <tr>
                                    <td align="center">
                                        <div class="otp-box">
                                            <span class="body-font otp-text">
                                                {{ $code }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Expiration & Disclaimer Notice -->
                            <div class="notice-box">
                                <p class="body-font notice-text">
                                    This code will expire in <strong class="notice-strong">{{ $expiresInMinutes }} minutes</strong>. If you did not request this registration, please disregard this email.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer Bar -->
                    <tr>
                        <td class="footer-cell">
                            <p class="body-font footer-text">
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
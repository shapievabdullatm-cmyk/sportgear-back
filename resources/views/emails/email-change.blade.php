<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family:sans-serif;padding:40px;color:#111;max-width:560px;margin:0 auto;">
<!DOCTYPE html>

<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Подтверждение email</title>
</head>
<body style="margin:0;padding:0;background:#0f0f0f;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#fff;">

<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
    <tr>
        <td align="center">

            ```
            <!-- CARD -->
            <table width="520" cellpadding="0" cellspacing="0" style="background:#151515;border:1px solid #222;border-radius:12px;padding:32px;">

                <!-- LOGO / TITLE -->
                <tr>
                    <td style="padding-bottom:20px;">
                        <div style="font-size:12px;color:#777;margin-bottom:8px;">Rage Shop</div>
                        <h1 style="margin:0;font-size:22px;font-weight:800;letter-spacing:-0.03em;">
                            Подтверждение email
                        </h1>
                    </td>
                </tr>

                <!-- TEXT -->
                <tr>
                    <td style="padding-bottom:20px;">
                        <p style="margin:0;color:#aaa;font-size:14px;line-height:1.6;">
                            Вы указали новый адрес:
                            <br><br>
                            <strong style="color:#fff;font-size:15px;">{{ $newEmail }}</strong>
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding-bottom:28px;">
                        <p style="margin:0;color:#888;font-size:14px;line-height:1.6;">
                            Нажмите кнопку ниже, чтобы подтвердить смену email.
                            Ссылка действительна <strong style="color:#fff;">30 минут</strong>.
                        </p>
                    </td>
                </tr>

                <!-- BUTTON -->
                <tr>
                    <td align="left">
                        <a href="{{ config('app.frontend_url') }}/account/confirm-email?token={{ $token }}"
                           style="display:inline-block;padding:14px 26px;background:#fff;color:#000;
             border-radius:8px;font-size:14px;font-weight:700;text-decoration:none;">
                            Подтвердить email
                        </a>
                    </td>
                </tr>

                <!-- DIVIDER -->
                <tr>
                    <td style="padding:30px 0 20px 0;">
                        <div style="height:1px;background:#222;"></div>
                    </td>
                </tr>

                <!-- FOOTER -->
                <tr>
                    <td>
                        <p style="margin:0;color:#666;font-size:12px;line-height:1.6;">
                            Если вы не запрашивали смену email — просто проигнорируйте это письмо.
                        </p>
                    </td>
                </tr>

            </table>
            <!-- END CARD -->

            <!-- FOOT NOTE -->
            <table width="520" cellpadding="0" cellspacing="0" style="margin-top:16px;">
                <tr>
                    <td align="center" style="color:#555;font-size:11px;">
                        © Rage Shop
                    </td>
                </tr>
            </table>

        </td>
    </tr>
    ```

</table>

</body>
</html>

</body>
</html>

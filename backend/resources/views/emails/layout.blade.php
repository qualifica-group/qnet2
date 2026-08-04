@php
    $appLabel = $appName ?? config('app.name');
    $appInitial = mb_strtoupper(mb_substr((string) $appLabel, 0, 1));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $appLabel }}</title>
    <style>
        /* Palette allineata ai token del gestionale (frontend/src/index.css):
           primary #1F3654, ring #3976C6, foreground #303F55, muted-foreground #495465. */
        :root { color-scheme: light dark; supported-color-schemes: light dark; }

        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            background-color: #e3e7ed;
            color: #303f55;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { border: 0; line-height: 100%; outline: none; text-decoration: none; }

        .wrapper { width: 100%; background-color: #e3e7ed; }
        .wrapper-cell { padding: 32px 12px; }

        /* Larghezza percentuale + cap in max-width: una tabella annidata con
           `width: 520px` non si restringe sotto i 520 sui viewport stretti.
           Outlook ignora max-width, per questo la ghost table MSO nel body. */
        .content {
            width: 100%;
            max-width: 520px;
            background-color: #ffffff;
            border: 1px solid #d8dee7;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(31, 54, 84, 0.06), 0 8px 24px rgba(31, 54, 84, 0.08);
        }

        /* Filo di accento: separa la testata dal bordo della card senza aggiungere peso. */
        .accent-bar { height: 3px; line-height: 3px; font-size: 0; background-color: #3976c6; }

        .header { padding: 22px 32px; background-color: #1f3654; }
        .header-mark {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background-color: #34507a;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            line-height: 34px;
            text-align: center;
            letter-spacing: 0.4px;
        }
        .header-name {
            padding-left: 12px;
            color: #ffffff;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: -0.1px;
        }

        .body {
            padding: 32px;
            font-size: 15px;
            line-height: 1.65;
            color: #303f55;
            mso-line-height-rule: exactly;
        }
        .body h1 {
            margin: 0 0 18px;
            font-size: 21px;
            line-height: 1.3;
            font-weight: 700;
            letter-spacing: -0.3px;
            color: #1f3654;
        }
        .body h2 { margin: 24px 0 10px; font-size: 16px; line-height: 1.4; font-weight: 600; color: #1f3654; }
        .body p { margin: 0 0 16px; }
        .body p:last-child { margin-bottom: 0; }
        .body strong { color: #1f3654; font-weight: 600; }
        .body a { color: #2f65ad; text-decoration: underline; text-underline-offset: 2px; }
        .body ul, .body ol { margin: 0 0 16px; padding-left: 20px; }
        .body li { margin: 0 0 6px; }
        .body hr { height: 1px; margin: 24px 0; border: 0; background-color: #e6eaf0; }

        .button {
            display: inline-block;
            margin: 4px 0;
            padding: 13px 26px;
            background-color: #1f3654;
            color: #ffffff !important;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.2;
            letter-spacing: 0.1px;
            text-decoration: none !important;
            border-radius: 10px;
            border: 1px solid #1f3654;
            mso-padding-alt: 13px 26px;
        }
        .button:hover { background-color: #2a4670; border-color: #2a4670; }

        .muted { color: #495465; font-size: 13px; line-height: 1.6; }
        .muted a { color: #495465; }
        .break { word-break: break-all; overflow-wrap: anywhere; }

        .footer {
            padding: 18px 32px 22px;
            background-color: #f7f9fb;
            border-top: 1px solid #e6eaf0;
            color: #566172;
            font-size: 12px;
            line-height: 1.55;
            text-align: center;
        }
        .footer-name { color: #1f3654; font-weight: 600; }

        @media only screen and (max-width: 600px) {
            .wrapper-cell { padding: 16px 8px; }
            .content { border-radius: 12px; }
            .header { padding: 18px 20px; }
            .body { padding: 24px 20px; font-size: 15px; }
            .body h1 { font-size: 19px; }
            .button { display: block; text-align: center; }
            .footer { padding: 16px 20px 20px; }
        }

        @media (prefers-color-scheme: dark) {
            body, .wrapper { background-color: #08090d !important; color: #e7eaee !important; }
            .content { background-color: #2e3548 !important; border-color: #4f5972 !important; box-shadow: none !important; }
            .header { background-color: #1b2130 !important; }
            .header-mark { background-color: #34507a !important; }
            .body { color: #e7eaee !important; }
            .body h1, .body h2, .body strong { color: #ffffff !important; }
            .body a { color: #9dbde8 !important; }
            .body hr { background-color: #4f5972 !important; }
            /* Specificita' maggiore di `.body a`, altrimenti il colore del link
               vince sull'etichetta del bottone e la rende illeggibile. */
            .body a.button, .button { background-color: #799ece !important; border-color: #799ece !important; color: #172230 !important; }
            .muted, .muted a { color: #b2b9c3 !important; }
            .footer { background-color: #262c3c !important; border-top-color: #4f5972 !important; color: #b2b9c3 !important; }
            .footer-name { color: #e7eaee !important; }
        }
    </style>
</head>
<body>
    <table class="wrapper" role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0">
        <tr>
            <td class="wrapper-cell" align="center">
                <!--[if mso]><table role="presentation" width="520" align="center" border="0" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
                <table class="content" role="presentation" width="100%" align="center" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="accent-bar">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="header">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    {{-- Decorativo: ripete l'iniziale del nome gia' letto nella cella accanto. --}}
                                    <td class="header-mark" align="center" aria-hidden="true">{{ $appInitial }}</td>
                                    <td class="header-name">{{ $appLabel }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="body">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td class="footer">
                            <span class="footer-name">{{ $appLabel }}</span><br>
                            &copy; {{ date('Y') }}. {{ __('This is an automated message, please do not reply.') }}
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>

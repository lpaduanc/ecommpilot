<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Análise de IA Concluída</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: #f4f4f7;
        }

        /* Typography */
        .body-text {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            color: #374151;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                max-width: 100% !important;
            }
            .content-cell {
                padding: 16px 12px !important;
            }
            .suggestion-card {
                padding: 12px !important;
            }
            .score-circle {
                width: 70px !important;
                height: 70px !important;
                line-height: 70px !important;
            }
            .score-circle span {
                font-size: 28px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f7;">
    <!-- Wrapper -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f7;">
        <tr>
            <td align="center" style="padding: 12px 8px;">
                <!-- Container - 90% width -->
                <table role="presentation" class="container" width="90%" cellpadding="0" cellspacing="0" style="max-width: 900px; width: 90%;">

                    <!-- Header Compacto -->
                    <tr>
                        <td align="center" style="padding-bottom: 12px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <div style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 12px; padding: 20px 24px;">
                                            <h1 style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 24px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">
                                                EcommPilot
                                            </h1>
                                            <p style="margin: 4px 0 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 13px; color: rgba(255,255,255,0.85);">
                                                Inteligência Artificial para E-commerce
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Main Content Card -->
                    <tr>
                        <td>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);">

                                <!-- Greeting + Period em linha -->
                                <tr>
                                    <td class="content-cell" style="padding: 20px 20px 16px 20px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="vertical-align: top; width: 65%;">
                                                    <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 16px; color: #1f2937;">
                                                        Olá, <strong>{{ $userName }}</strong>!
                                                    </p>
                                                    <p style="margin: 8px 0 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; line-height: 1.5; color: #4b5563;">
                                                        A análise de IA da sua loja <strong style="color: #1f2937;">{{ $storeName }}</strong> foi concluída. Confira os resultados abaixo.
                                                    </p>
                                                </td>
                                                <td style="vertical-align: top; text-align: right; padding-left: 16px;">
                                                    <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; font-weight: 600;">
                                                        Período
                                                    </p>
                                                    <p style="margin: 2px 0 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 13px; color: #1f2937; font-weight: 500;">
                                                        {{ $periodStart }} a {{ $periodEnd }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Health Score + Main Insight lado a lado -->
                                <tr>
                                    <td style="padding: 0 20px 16px 20px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <!-- Score -->
                                                <td style="vertical-align: top; width: 140px; text-align: center;">
                                                    @php
                                                        $scoreColor = match(true) {
                                                            $healthScore >= 80 => '#10b981',
                                                            $healthScore >= 60 => '#f59e0b',
                                                            $healthScore >= 40 => '#f97316',
                                                            default => '#ef4444',
                                                        };
                                                        $scoreBgColor = match(true) {
                                                            $healthScore >= 80 => '#d1fae5',
                                                            $healthScore >= 60 => '#fef3c7',
                                                            $healthScore >= 40 => '#ffedd5',
                                                            default => '#fee2e2',
                                                        };
                                                    @endphp
                                                    <div class="score-circle" style="width: 90px; height: 90px; border-radius: 50%; background-color: {{ $scoreBgColor }}; display: inline-block; text-align: center; line-height: 90px;">
                                                        <span style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 36px; font-weight: 700; color: {{ $scoreColor }};">
                                                            {{ $healthScore }}
                                                        </span>
                                                    </div>
                                                    <p style="margin: 6px 0 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 12px; color: #6b7280;">
                                                        Score de Saúde
                                                    </p>
                                                    <span style="display: inline-block; margin-top: 4px; padding: 3px 10px; border-radius: 12px; background-color: {{ $scoreBgColor }}; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 12px; font-weight: 600; color: {{ $scoreColor }};">
                                                        {{ $healthStatus }}
                                                    </span>
                                                </td>
                                                <!-- Insight -->
                                                <td style="vertical-align: top; padding-left: 20px;">
                                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #ede9fe 0%, #e0e7ff 100%); border-radius: 10px;">
                                                        <tr>
                                                            <td style="padding: 16px;">
                                                                <p style="margin: 0 0 6px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #6366f1; font-weight: 600;">
                                                                    Insight Principal
                                                                </p>
                                                                <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; line-height: 1.5; color: #1f2937;">
                                                                    {{ $mainInsight }}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Divider -->
                                <tr>
                                    <td style="padding: 0 20px;">
                                        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 0;">
                                    </td>
                                </tr>

                                <!-- Suggestions Section -->
                                <tr>
                                    <td style="padding: 16px 20px 12px 20px;">
                                        <h2 style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 18px; font-weight: 700; color: #1f2937;">
                                            Recomendações Personalizadas
                                        </h2>
                                        <p style="margin: 4px 0 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 13px; color: #6b7280;">
                                            {{ count($suggestions) }} sugestões para melhorar sua loja
                                        </p>
                                    </td>
                                </tr>

                                <!-- Suggestions List -->
                                @foreach($suggestions as $index => $suggestion)
                                @php
                                    $impactColor = match($suggestion['expected_impact']) {
                                        'high' => '#dc2626',
                                        'medium' => '#f59e0b',
                                        'low' => '#10b981',
                                        default => '#6b7280',
                                    };
                                    $impactBg = match($suggestion['expected_impact']) {
                                        'high' => '#fef2f2',
                                        'medium' => '#fffbeb',
                                        'low' => '#f0fdf4',
                                        default => '#f9fafb',
                                    };
                                    $impactLabel = match($suggestion['expected_impact']) {
                                        'high' => 'Alta',
                                        'medium' => 'Média',
                                        'low' => 'Baixa',
                                        default => '-',
                                    };
                                @endphp
                                <tr>
                                    <td style="padding: 0 20px 12px 20px;">
                                        <table role="presentation" class="suggestion-card" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 10px; border-left: 4px solid {{ $impactColor }};">
                                            <tr>
                                                <td style="padding: 14px 16px;">
                                                    <!-- Header -->
                                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                                        <tr>
                                                            <td>
                                                                <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; background-color: {{ $impactBg }}; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 10px; font-weight: 600; color: {{ $impactColor }}; text-transform: uppercase; letter-spacing: 0.3px;">
                                                                    {{ $impactLabel }}
                                                                </span>
                                                                <span style="display: inline-block; margin-left: 6px; padding: 3px 8px; border-radius: 4px; background-color: #e5e7eb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 10px; font-weight: 500; color: #4b5563;">
                                                                    {{ $suggestion['category'] }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    </table>

                                                    <!-- Title -->
                                                    <h3 style="margin: 10px 0 6px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 15px; font-weight: 600; color: #1f2937;">
                                                        {{ $suggestion['title'] }}
                                                    </h3>

                                                    <!-- Description -->
                                                    <p style="margin: 0 0 10px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 13px; line-height: 1.45; color: #4b5563;">
                                                        {{ $suggestion['description'] }}
                                                    </p>

                                                    <!-- Action -->
                                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 6px;">
                                                        <tr>
                                                            <td style="padding: 10px 12px;">
                                                                <p style="margin: 0 0 3px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; color: #6366f1; font-weight: 600;">
                                                                    Ação Recomendada
                                                                </p>
                                                                <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 13px; line-height: 1.5; color: #1f2937;">
                                                                    {!! nl2br(e($suggestion['recommended_action'])) !!}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @endforeach

                                <!-- CTA Button -->
                                <tr>
                                    <td align="center" style="padding: 16px 20px 20px 20px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" style="border-radius: 8px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);">
                                                    <a href="{{ config('app.url') }}/analysis" target="_blank" style="display: inline-block; padding: 12px 28px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none;">
                                                        Ver Análise Completa
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>

                    <!-- Footer Compacto -->
                    <tr>
                        <td style="padding: 16px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 12px; color: #6b7280;">
                                            <strong style="color: #4b5563;">EcommPilot</strong> &middot; Inteligência Artificial para E-commerce &middot; &copy; {{ date('Y') }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>

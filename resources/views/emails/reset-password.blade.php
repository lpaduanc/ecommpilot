<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinição de Senha - EcommPilot</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f7;
            margin: 0;
            padding: 0;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .email-body {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333333;
        }
        .content {
            font-size: 16px;
            color: #555555;
            margin-bottom: 30px;
            line-height: 1.8;
        }
        .button-wrapper {
            text-align: center;
            margin: 40px 0;
        }
        .reset-button {
            display: inline-block;
            padding: 16px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s;
        }
        .reset-button:hover {
            transform: translateY(-2px);
        }
        .expiration-notice {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            margin: 30px 0;
            border-radius: 4px;
        }
        .expiration-notice p {
            margin: 0;
            color: #856404;
            font-size: 14px;
        }
        .alternative-link {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin: 30px 0;
            font-size: 14px;
            color: #666666;
        }
        .alternative-link p {
            margin: 0 0 10px 0;
        }
        .alternative-link a {
            color: #667eea;
            word-break: break-all;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        .footer p {
            margin: 5px 0;
            font-size: 14px;
            color: #6c757d;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        .security-tip {
            background-color: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .security-tip p {
            margin: 0;
            color: #0c5460;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <h1>🔐 EcommPilot</h1>
        </div>

        <div class="email-body">
            <div class="greeting">
                Olá, {{ $userName }}!
            </div>

            <div class="content">
                <p>Você está recebendo este e-mail porque recebemos uma solicitação de redefinição de senha para sua conta no <strong>EcommPilot</strong>.</p>

                <p>Para redefinir sua senha, clique no botão abaixo:</p>
            </div>

            <div class="button-wrapper">
                <a href="{{ $resetUrl }}" class="reset-button">
                    Redefinir Senha
                </a>
            </div>

            <div class="expiration-notice">
                <p><strong>⏱️ Atenção:</strong> Este link de redefinição expira em <strong>60 minutos</strong>.</p>
            </div>

            <div class="alternative-link">
                <p><strong>O botão não está funcionando?</strong></p>
                <p>Copie e cole o link abaixo no seu navegador:</p>
                <p><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></p>
            </div>

            <div class="security-tip">
                <p><strong>🔒 Dica de Segurança:</strong> Recomendamos que você altere sua senha regularmente e use uma senha forte com letras, números e caracteres especiais.</p>
            </div>

            <div class="content" style="margin-top: 30px;">
                <p>Se você <strong>não solicitou</strong> a redefinição de senha, ignore este e-mail. Nenhuma ação adicional é necessária e sua senha permanecerá a mesma.</p>
            </div>
        </div>

        <div class="footer">
            <p><strong>Atenciosamente,</strong></p>
            <p><strong>Equipe EcommPilot</strong></p>
            <p style="margin-top: 20px; font-size: 12px;">
                Este é um e-mail automático. Por favor, não responda.
            </p>
            <p style="font-size: 12px;">
                © {{ date('Y') }} EcommPilot. Todos os direitos reservados.
            </p>
        </div>
    </div>
</body>
</html>

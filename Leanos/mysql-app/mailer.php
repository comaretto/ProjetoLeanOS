<?php
declare(strict_types=1);

function smtp_enviar(string $destinatario, string $assunto, string $corpo): void
{
    $host = getenv('LEANOS_SMTP_HOST');
    $port = (int)(getenv('LEANOS_SMTP_PORT') ?: 587);
    $user = getenv('LEANOS_SMTP_USER');
    $password = getenv('LEANOS_SMTP_PASSWORD');
    $from = getenv('LEANOS_SMTP_FROM') ?: $user;
    $encryption = strtolower(getenv('LEANOS_SMTP_ENCRYPTION') ?: 'tls');

    if (!$host || !$user || !$password || !$from) {
        throw new RuntimeException('SMTP não configurado.');
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errorCode, $errorMessage, 15);
    if (!$socket) {
        throw new RuntimeException("Falha ao conectar no SMTP: $errorMessage");
    }

    stream_set_timeout($socket, 15);
    smtp_resposta($socket, 220);
    smtp_comando($socket, 'EHLO leanos.local', 250);
    if ($encryption === 'tls') {
        smtp_comando($socket, 'STARTTLS', 220);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            throw new RuntimeException('Não foi possível iniciar TLS no SMTP.');
        }
        smtp_comando($socket, 'EHLO leanos.local', 250);
    }
    smtp_comando($socket, 'AUTH LOGIN', 334);
    smtp_comando($socket, base64_encode($user), 334);
    smtp_comando($socket, base64_encode($password), 235);
    smtp_comando($socket, "MAIL FROM:<$from>", 250);
    smtp_comando($socket, "RCPT TO:<$destinatario>", 250);
    smtp_comando($socket, 'DATA', 354);

    $headers = [
        "From: LeanOS <$from>",
        "To: <$destinatario>",
        'Subject: ' . mb_encode_mimeheader($assunto, 'UTF-8'),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    $mensagem = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\r"], "\n", $corpo);
    $mensagem = str_replace("\n", "\r\n", $mensagem);
    fwrite($socket, $mensagem . "\r\n.\r\n");
    smtp_resposta($socket, 250);
    smtp_comando($socket, 'QUIT', 221);
    fclose($socket);
}

function smtp_comando($socket, string $comando, int $esperado): void
{
    fwrite($socket, $comando . "\r\n");
    smtp_resposta($socket, $esperado);
}

function smtp_resposta($socket, int $esperado): void
{
    $resposta = '';
    while (($linha = fgets($socket, 515)) !== false) {
        $resposta .= $linha;
        if (isset($linha[3]) && $linha[3] === ' ') {
            break;
        }
    }
    $codigo = (int)substr($resposta, 0, 3);
    if ($codigo !== $esperado) {
        throw new RuntimeException("Resposta SMTP inesperada: $resposta");
    }
}

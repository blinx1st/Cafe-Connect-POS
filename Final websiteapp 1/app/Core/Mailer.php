<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Mailer
{
    private array $config;
    private mixed $socket = null;

    public function __construct()
    {
        $path = APP_ROOT . '/config/mail.php';
        $this->config = is_file($path) ? (require $path) : [];
    }

    public function sendPasswordReset(string $toEmail, string $toName, string $resetUrl): void
    {
        $subject = 'Đặt lại mật khẩu Cafe Connect';
        $safeName = $toName !== '' ? $toName : 'thanh vien';
        $body = "Xin chao {$safeName},\n\n"
            . "Bạn vừa yêu cầu đặt lại mật khẩu Cafe Connect.\n"
            . "Vui lòng mở liên kết sau trong 30 phút để tạo mật khẩu mới:\n\n"
            . $resetUrl . "\n\n"
            . "Nếu bạn không yêu cầu thao tác này, hãy bỏ qua email này.\n\n"
            . "Cafe Connect";

        $this->send($toEmail, $toName, $subject, $body);
    }

    public function send(string $toEmail, string $toName, string $subject, string $body): void
    {
        $this->assertConfigured();

        $host = (string) $this->config['host'];
        $port = (int) ($this->config['port'] ?? 587);
        $encryption = strtolower((string) ($this->config['encryption'] ?? 'tls'));
        $timeout = (int) ($this->config['timeout'] ?? 15);
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

        $this->socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!$this->socket) {
            throw new RuntimeException('Không thể kết nối SMTP: ' . $errstr);
        }
        stream_set_timeout($this->socket, $timeout);

        try {
            $this->expect([220]);
            $this->command('EHLO localhost', [250]);

            if ($encryption === 'tls') {
                $this->command('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Không thể bật TLS cho SMTP.');
                }
                $this->command('EHLO localhost', [250]);
            }

            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode((string) $this->config['username']), [334]);
            $this->command(base64_encode((string) $this->config['password']), [235]);

            $fromEmail = (string) $this->config['from_email'];
            $fromName = (string) ($this->config['from_name'] ?? 'Cafe Connect');
            $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command('RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command('DATA', [354]);

            $headers = [
                'From: ' . $this->formatAddress($fromEmail, $fromName),
                'To: ' . $this->formatAddress($toEmail, $toName),
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\n"], "\r\n", $body);
            fwrite($this->socket, $message . "\r\n.\r\n");
            $this->expect([250]);
            $this->command('QUIT', [221]);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    private function assertConfigured(): void
    {
        foreach (['host', 'username', 'password', 'from_email'] as $key) {
            if (trim((string) ($this->config[$key] ?? '')) === '') {
                throw new RuntimeException('SMTP chưa được cấu hình trong config/mail.php.');
            }
        }
    }

    private function command(string $command, array $expectedCodes): string
    {
        fwrite($this->socket, $command . "\r\n");
        return $this->expect($expectedCodes);
    }

    private function expect(array $expectedCodes): string
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3}\s/', $line) === 1) {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP tra ve loi: ' . trim($response));
        }

        return $response;
    }

    private function formatAddress(string $email, string $name): string
    {
        $name = trim($name);
        return $name === '' ? '<' . $email . '>' : $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}

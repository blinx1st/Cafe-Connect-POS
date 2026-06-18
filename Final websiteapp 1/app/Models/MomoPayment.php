<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;
use RuntimeException;

final class MomoPayment extends Model
{
    public function isConfigured(): bool
    {
        return PAYMENT_MOMO_ENABLED
            && PAYMENT_MOMO_PARTNER_CODE !== ''
            && PAYMENT_MOMO_ACCESS_KEY !== ''
            && PAYMENT_MOMO_SECRET_KEY !== ''
            && PAYMENT_MOMO_REDIRECT_URL !== ''
            && PAYMENT_MOMO_IPN_URL !== '';
    }

    public function createForInvoice(int $customerId, int $invoiceId): array
    {
        if (!$this->isConfigured()) {
            throw new InvalidArgumentException('MoMo chưa được cấu hình. Vui lòng tạo config/payment.local.php và điền thông tin sandbox.');
        }

        $invoice = $this->websiteInvoiceForCustomer($customerId, $invoiceId);
        if (!$invoice) {
            throw new InvalidArgumentException('Không tìm thấy đơn hàng website để thanh toán MoMo.');
        }
        if (($invoice['payment_method'] ?? '') !== 'e_wallet') {
            throw new InvalidArgumentException('Đơn hàng này không dùng phương thức MoMo.');
        }
        if (($invoice['invoice_status'] ?? '') !== 'pending') {
            return $this->status($invoiceId, $customerId);
        }

        $payment = $this->paymentForInvoice($invoiceId);
        $amount = (int) round((float) $invoice['total_amount']);
        if ($amount < 1000) {
            throw new InvalidArgumentException('MoMo yêu cầu số tiền thanh toán tối thiểu 1.000đ.');
        }

        $requestId = 'REQ-' . $invoiceId . '-' . time();
        $orderId = 'CCINV-' . $invoiceId . '-' . time();
        $extraData = base64_encode(json_encode([
            'invoice_id' => $invoiceId,
            'customer_id' => $customerId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        $redirectUrl = $this->appendQuery(PAYMENT_MOMO_REDIRECT_URL, ['invoice_id' => $invoiceId]);
        $orderInfo = 'Cafe Connect invoice #' . $invoiceId;
        $requestType = 'captureWallet';
        $rawSignature = $this->createRawSignature([
            'accessKey' => PAYMENT_MOMO_ACCESS_KEY,
            'amount' => (string) $amount,
            'extraData' => $extraData,
            'ipnUrl' => PAYMENT_MOMO_IPN_URL,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'partnerCode' => PAYMENT_MOMO_PARTNER_CODE,
            'redirectUrl' => $redirectUrl,
            'requestId' => $requestId,
            'requestType' => $requestType,
        ]);

        $request = [
            'partnerCode' => PAYMENT_MOMO_PARTNER_CODE,
            'requestType' => $requestType,
            'ipnUrl' => PAYMENT_MOMO_IPN_URL,
            'redirectUrl' => $redirectUrl,
            'orderId' => $orderId,
            'amount' => $amount,
            'orderInfo' => $orderInfo,
            'requestId' => $requestId,
            'extraData' => $extraData,
            'lang' => 'vi',
            'signature' => hash_hmac('sha256', $rawSignature, PAYMENT_MOMO_SECRET_KEY),
        ];

        $this->db->prepare(
            "INSERT INTO payment_transactions (
                payment_id, invoice_id, provider, provider_order_id, provider_request_id,
                amount, status, raw_request_json
             ) VALUES (
                :payment_id, :invoice_id, 'momo', :provider_order_id, :provider_request_id,
                :amount, 'created', :raw_request_json
             )"
        )->execute([
            'payment_id' => (int) ($payment['id'] ?? 0) ?: null,
            'invoice_id' => $invoiceId,
            'provider_order_id' => $orderId,
            'provider_request_id' => $requestId,
            'amount' => $amount,
            'raw_request_json' => json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $transactionId = (int) $this->db->lastInsertId();

        try {
            $response = $this->postJson(PAYMENT_MOMO_ENDPOINT, $request);
        } catch (\Throwable $exception) {
            $this->db->prepare(
                "UPDATE payment_transactions
                 SET status = 'failed', message = :message, updated_at = NOW()
                 WHERE id = :id"
            )->execute(['id' => $transactionId, 'message' => $exception->getMessage()]);
            throw new InvalidArgumentException('Không thể kết nối MoMo sandbox: ' . $exception->getMessage());
        }

        $resultCode = (int) ($response['resultCode'] ?? -1);
        $status = $resultCode === 0 && !empty($response['payUrl']) ? 'pending' : 'failed';
        $this->db->prepare(
            "UPDATE payment_transactions
             SET status = :status,
                 result_code = :result_code,
                 message = :message,
                 pay_url = :pay_url,
                 deeplink = :deeplink,
                 qr_code_url = :qr_code_url,
                 raw_response_json = :raw_response_json,
                 updated_at = NOW()
             WHERE id = :id"
        )->execute([
            'id' => $transactionId,
            'status' => $status,
            'result_code' => $resultCode,
            'message' => (string) ($response['message'] ?? ''),
            'pay_url' => (string) ($response['payUrl'] ?? ''),
            'deeplink' => (string) ($response['deeplink'] ?? ''),
            'qr_code_url' => (string) ($response['qrCodeUrl'] ?? ''),
            'raw_response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if ($status !== 'pending') {
            throw new InvalidArgumentException('MoMo từ chối tạo thanh toán: ' . (string) ($response['message'] ?? 'Không rõ lỗi.'));
        }

        return [
            'transaction_id' => $transactionId,
            'provider_order_id' => $orderId,
            'provider_request_id' => $requestId,
            'pay_url' => (string) $response['payUrl'],
            'deeplink' => (string) ($response['deeplink'] ?? ''),
            'qr_code_url' => (string) ($response['qrCodeUrl'] ?? ''),
            'status' => 'pending',
        ];
    }

    public function handleNotification(array $payload, string $source = 'ipn'): array
    {
        if (!$this->verifyResultSignature($payload)) {
            throw new InvalidArgumentException('Chữ ký MoMo không hợp lệ.');
        }

        $orderId = (string) ($payload['orderId'] ?? '');
        $stmt = $this->db->prepare("SELECT * FROM payment_transactions WHERE provider_order_id = :order_id LIMIT 1");
        $stmt->execute(['order_id' => $orderId]);
        $transaction = $stmt->fetch();
        if (!$transaction) {
            throw new InvalidArgumentException('Không tìm thấy giao dịch MoMo.');
        }

        $invoiceId = (int) $transaction['invoice_id'];
        $resultCode = (int) ($payload['resultCode'] ?? -1);
        $status = $resultCode === 0 ? 'paid' : 'failed';
        $this->db->prepare(
            "UPDATE payment_transactions
             SET status = :status,
                 result_code = :result_code,
                 provider_transaction_id = :trans_id,
                 message = :message,
                 raw_ipn_json = :raw_json,
                 updated_at = NOW()
             WHERE id = :id"
        )->execute([
            'id' => (int) $transaction['id'],
            'status' => $status,
            'result_code' => $resultCode,
            'trans_id' => (string) ($payload['transId'] ?? ''),
            'message' => (string) ($payload['message'] ?? ''),
            'raw_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if ($status === 'paid') {
            $customerId = $this->customerIdForInvoice($invoiceId);
            if ($customerId > 0) {
                (new WebsiteOrder())->confirmMomoPayment($customerId, $invoiceId, [
                    'source' => $source,
                    'transaction_reference' => (string) ($payload['transId'] ?? $orderId),
                    'provider_order_id' => $orderId,
                ]);
            }
        } else {
            (new WebsiteOrder())->failMomoPayment($invoiceId, (string) ($payload['message'] ?? 'MoMo payment failed.'), [
                'source' => $source,
                'provider_order_id' => $orderId,
                'result_code' => $resultCode,
            ]);
        }

        return $this->status($invoiceId, null);
    }

    public function status(int $invoiceId = 0, ?int $customerId = null, string $providerOrderId = ''): array
    {
        if ($invoiceId <= 0 && $providerOrderId !== '') {
            $stmt = $this->db->prepare("SELECT invoice_id FROM payment_transactions WHERE provider_order_id = :order_id LIMIT 1");
            $stmt->execute(['order_id' => $providerOrderId]);
            $invoiceId = (int) ($stmt->fetchColumn() ?: 0);
        }
        if ($invoiceId <= 0) {
            throw new InvalidArgumentException('Thiếu mã hóa đơn thanh toán.');
        }

        $params = ['invoice_id' => $invoiceId];
        $customerSql = '';
        if ($customerId !== null && $customerId > 0) {
            $customerSql = ' AND (wo.customer_id = :customer_id OR i.customer_id = :customer_id)';
            $params['customer_id'] = $customerId;
        }

        $stmt = $this->db->prepare(
            "SELECT i.id AS invoice_id, i.status AS invoice_status, i.payment_method, i.total_amount,
                    wo.order_status, p.status AS payment_status, p.payment_provider,
                    pt.provider_order_id, pt.provider_request_id, pt.provider_transaction_id,
                    pt.status AS transaction_status, pt.result_code, pt.message, pt.pay_url
             FROM invoices i
             LEFT JOIN website_orders wo ON wo.invoice_id = i.id
             LEFT JOIN payments p ON p.invoice_id = i.id
             LEFT JOIN payment_transactions pt ON pt.invoice_id = i.id
             WHERE i.id = :invoice_id $customerSql
             ORDER BY pt.id DESC
             LIMIT 1"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();
        if (!$row) {
            throw new InvalidArgumentException('Không tìm thấy trạng thái thanh toán.');
        }

        return $row;
    }

    private function websiteInvoiceForCustomer(int $customerId, int $invoiceId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT i.*, i.status AS invoice_status, wo.order_status
             FROM invoices i
             JOIN website_orders wo ON wo.invoice_id = i.id
             WHERE i.id = :invoice_id AND wo.customer_id = :customer_id
             LIMIT 1"
        );
        $stmt->execute(['invoice_id' => $invoiceId, 'customer_id' => $customerId]);
        $invoice = $stmt->fetch();
        return $invoice ?: null;
    }

    private function paymentForInvoice(int $invoiceId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE invoice_id = :invoice_id ORDER BY id DESC LIMIT 1");
        $stmt->execute(['invoice_id' => $invoiceId]);
        $payment = $stmt->fetch();
        return $payment ?: null;
    }

    private function customerIdForInvoice(int $invoiceId): int
    {
        $stmt = $this->db->prepare("SELECT customer_id FROM website_orders WHERE invoice_id = :invoice_id LIMIT 1");
        $stmt->execute(['invoice_id' => $invoiceId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function createRawSignature(array $data): string
    {
        return implode('&', array_map(
            static fn (string $key): string => $key . '=' . (string) $data[$key],
            array_keys($data)
        ));
    }

    private function verifyResultSignature(array $payload): bool
    {
        $required = [
            'accessKey',
            'amount',
            'extraData',
            'message',
            'orderId',
            'orderInfo',
            'orderType',
            'partnerCode',
            'payType',
            'requestId',
            'responseTime',
            'resultCode',
            'transId',
        ];
        $data = [];
        foreach ($required as $key) {
            $data[$key] = $key === 'accessKey' ? PAYMENT_MOMO_ACCESS_KEY : (string) ($payload[$key] ?? '');
        }
        $raw = $this->createRawSignature($data);
        $expected = hash_hmac('sha256', $raw, PAYMENT_MOMO_SECRET_KEY);
        return hash_equals($expected, (string) ($payload['signature'] ?? ''));
    }

    private function postJson(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Cannot encode MoMo request.');
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($body)],
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 35,
            ]);
            $raw = curl_exec($ch);
            if ($raw === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new RuntimeException($error);
            }
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('HTTP ' . $status . ' from MoMo.');
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $body,
                    'timeout' => 35,
                ],
            ]);
            $raw = file_get_contents($url, false, $context);
            if ($raw === false) {
                throw new RuntimeException('Cannot connect to MoMo endpoint.');
            }
        }

        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('MoMo returned invalid JSON.');
        }

        return $json;
    }

    private function appendQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($params);
    }
}

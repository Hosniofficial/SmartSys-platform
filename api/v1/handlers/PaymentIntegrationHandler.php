<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Exception;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MonologHandler;

class PaymentIntegrationHandler extends BaseHandler
{
    private array $supportedGateways = [
        'stripe',
        'paypal',
        'tap',
        'myfatoorah'
    ];

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = MonologHandler::getInstance('payment_integration');
    }

    /**
     * إنشاء معاملة دفع جديدة (يتطلب Request للحصول على tenant_id)
     *
     * ⚠️ RACE CONDITION NOTE:
     * إذا نجحت عملية الدفع عند البوابة (Stripe/PayPal/etc) لكن فشل الـ INSERT في DB،
     * فسيكون المبلغ قد تم حجزه فعلاً عند البوابة بدون وجود سجل في قاعدة البيانات.
     * الحل الموصى به:
     * 1. تخزين transaction_id من البوابة مؤقتاً
     * 2. عمل retry mechanism للـ INSERT
     * 3. إضافة webhook handler للتحقق من المعاملات المفقودة
     */
    public function createPayment(Request $request, array $data): array
    {
        $tenantId = $data['tenant_id'] ?? $this->extractTenantId($request);
        if (!$tenantId) {
            throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
        }

        $gateway = strtolower(trim((string) ($data['gateway'] ?? '')));
        if ($gateway === '' || !in_array($gateway, $this->supportedGateways, true)) {
            throw new Exception('بوابة الدفع غير مدعومة');
        }

        $paymentData = [
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'description' => $data['description'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'success_url' => $data['success_url'] ?? null,
            'cancel_url' => $data['cancel_url'] ?? null
        ];

        try {
            $response = $this->processPayment($gateway, $paymentData);

            $stmt = $this->db->prepare("
                INSERT INTO payment_transactions (
                    tenant_id,
                    gateway,
                    amount,
                    currency,
                    status,
                    transaction_id,
                    customer_email,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $tenantId,
                $gateway,
                $paymentData['amount'],
                $paymentData['currency'],
                'pending',
                $response['transaction_id'],
                $paymentData['customer_email']
            ]);

            return $response;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create payment transaction', [
                'tenant_id' => $tenantId,
                'gateway' => $gateway,
                'amount' => $paymentData['amount'],
                'customer_email' => $paymentData['customer_email'],
                'message' => $e->getMessage()
            ]);

            throw new Exception('فشل في إنشاء معاملة الدفع');
        }
    }

    /**
     * معالجة الدفع عبر البوابة المحددة
     */
    public function processPayment(string $gateway, array $data): array
    {
        switch ($gateway) {
            case 'stripe':
                return $this->processStripePayment($data);

            case 'paypal':
                return $this->processPayPalPayment($data);

            case 'tap':
                return $this->processTapPayment($data);

            case 'myfatoorah':
                return $this->processMyFatoorahPayment($data);

            default:
                throw new Exception('بوابة الدفع غير مدعومة');
        }
    }

    /**
     * معالجة الدفع عبر Stripe
     */
    private function processStripePayment(array $data): array
    {
        \Stripe\Stripe::setApiKey($this->getStripeKey());

        try {
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $data['currency'],
                        'unit_amount' => (float) $data['amount'] * 100,
                        'product_data' => [
                            'name' => $data['description']
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $data['success_url'],
                'cancel_url' => $data['cancel_url'],
                'customer_email' => $data['customer_email']
            ]);

            return [
                'transaction_id' => $session->id,
                'redirect_url' => $session->url
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->logger->error('Stripe payment failed', [
                'message' => $e->getMessage(),
                'customer_email' => $data['customer_email'] ?? null
            ]);

            throw new Exception('فشل في معالجة الدفع عبر Stripe');
        }
    }

    /**
     * معالجة الدفع عبر PayPal
     */
    private function processPayPalPayment(array $data): array
    {
        $paypal = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                $this->getPayPalClientId(),
                $this->getPayPalSecret()
            )
        );

        try {
            $payment = new \PayPal\Api\Payment();
            $payment->setIntent('sale')
                ->setPayer(
                    (new \PayPal\Api\Payer())->setPaymentMethod('paypal')
                )
                ->setTransactions([
                    (new \PayPal\Api\Transaction())
                        ->setAmount(
                            (new \PayPal\Api\Amount())
                                ->setTotal($data['amount'])
                                ->setCurrency($data['currency'])
                        )
                        ->setDescription($data['description'])
                ]);

            $redirectUrls = new \PayPal\Api\RedirectUrls();
            $redirectUrls->setReturnUrl($data['success_url'])
                ->setCancelUrl($data['cancel_url']);

            $payment->setRedirectUrls($redirectUrls);
            $payment->create($paypal);

            return [
                'transaction_id' => $payment->getId(),
                'redirect_url' => $payment->getApprovalLink()
            ];
        } catch (\PayPal\Exception\PayPalConnectionException $e) {
            $this->logger->error('PayPal payment failed', [
                'message' => $e->getMessage(),
                'customer_email' => $data['customer_email'] ?? null
            ]);

            throw new Exception('فشل في معالجة الدفع عبر PayPal');
        }
    }

    /**
     * معالجة الدفع عبر Tap
     */
    private function processTapPayment(array $data): array
    {
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->post('https://api.tap.company/v2/charges', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getTapKey(),
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'customer' => [
                        'email' => $data['customer_email']
                    ],
                    'source' => ['id' => 'src_all'],
                    'redirect' => [
                        'url' => $data['success_url']
                    ]
                ]
            ]);

            $result = json_decode((string) $response->getBody(), true);

            return [
                'transaction_id' => $result['id'],
                'redirect_url' => $result['transaction']['url']
            ];
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $this->logger->error('Tap payment failed', [
                'message' => $e->getMessage(),
                'customer_email' => $data['customer_email'] ?? null
            ]);

            throw new Exception('فشل في معالجة الدفع عبر Tap');
        }
    }

    /**
     * معالجة الدفع عبر MyFatoorah
     */
    private function processMyFatoorahPayment(array $data): array
    {
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->post('https://api.myfatoorah.com/v2/InitiatePayment', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getMyFatoorahKey(),
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'InvoiceAmount' => $data['amount'],
                    'CurrencyIso' => $data['currency'],
                    'CustomerEmail' => $data['customer_email'],
                    'CallBackUrl' => $data['success_url'],
                    'ErrorUrl' => $data['cancel_url'],
                    'Language' => 'ar',
                    'CustomerReference' => uniqid()
                ]
            ]);

            $result = json_decode((string) $response->getBody(), true);

            return [
                'transaction_id' => $result['Data']['InvoiceId'],
                'redirect_url' => $result['Data']['PaymentURL']
            ];
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $this->logger->error('MyFatoorah payment failed', [
                'message' => $e->getMessage(),
                'customer_email' => $data['customer_email'] ?? null
            ]);

            throw new Exception('فشل في معالجة الدفع عبر MyFatoorah');
        }
    }

    /**
     * التحقق من حالة الدفع
     */
    public function checkPaymentStatus(Request $request, string $transactionId, string $gateway): array
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM payment_transactions
            WHERE transaction_id = ? AND gateway = ? AND tenant_id = ?
        ");
        $stmt->execute([$transactionId, $gateway, $tenantId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            throw new Exception('معاملة غير موجودة');
        }

        return $transaction;
    }

    /**
     * تحديث حالة الدفع
     */
    public function updatePaymentStatus(Request $request, string $transactionId, string $status): bool
    {
        $tenantId = $this->extractTenantId($request);
        if (!$tenantId) {
            throw new Exception('مطلوب معرف المستأجر (Tenant ID).');
        }

        // Validate status value
        $validStatuses = ['pending', 'completed', 'failed', 'cancelled', 'refunded'];
        $status = strtolower(trim($status));
        if (!in_array($status, $validStatuses, true)) {
            throw new Exception('حالة الدفع غير صحيحة. القيم المقبولة: ' . implode(', ', $validStatuses));
        }

        $stmt = $this->db->prepare("
            UPDATE payment_transactions
            SET status = ?, updated_at = NOW()
            WHERE transaction_id = ? AND tenant_id = ?
        ");

        return $stmt->execute([$status, $transactionId, $tenantId]);
    }

    /**
     * الحصول على مفتاح Stripe
     */
    private function getStripeKey(): string
    {
        $key = getenv('STRIPE_SECRET_KEY');
        if (!$key || $key === false) {
            throw new Exception('STRIPE_SECRET_KEY environment variable is not configured');
        }
        return (string) $key;
    }

    /**
     * الحصول على معرف عميل PayPal
     */
    private function getPayPalClientId(): string
    {
        $clientId = getenv('PAYPAL_CLIENT_ID');
        if (!$clientId || $clientId === false) {
            throw new Exception('PAYPAL_CLIENT_ID environment variable is not configured');
        }
        return (string) $clientId;
    }

    /**
     * الحصول على سر PayPal
     */
    private function getPayPalSecret(): string
    {
        $secret = getenv('PAYPAL_SECRET');
        if (!$secret || $secret === false) {
            throw new Exception('PAYPAL_SECRET environment variable is not configured');
        }
        return (string) $secret;
    }

    /**
     * الحصول على مفتاح Tap
     */
    private function getTapKey(): string
    {
        $key = getenv('TAP_SECRET_KEY');
        if (!$key || $key === false) {
            throw new Exception('TAP_SECRET_KEY environment variable is not configured');
        }
        return (string) $key;
    }

    /**
     * الحصول على مفتاح MyFatoorah
     */
    private function getMyFatoorahKey(): string
    {
        $key = getenv('MYFATOORAH_API_KEY');
        if (!$key || $key === false) {
            throw new Exception('MYFATOORAH_API_KEY environment variable is not configured');
        }
        return (string) $key;
    }
}

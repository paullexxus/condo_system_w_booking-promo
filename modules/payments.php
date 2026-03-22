<?php
namespace BookIT\Modules;

class PaymentSystem {
    private $config;
    private $db;

    public function __construct($config, $db) {
        $this->config = $config;
        $this->db = $db;
    }

    public function processPayment($amount, $paymentMethod, $customerData) {
        try {
            // Validate payment data
            $this->validatePaymentData($amount, $paymentMethod, $customerData);
            
            // Process payment based on method
            switch ($paymentMethod) {
                case 'credit_card':
                    return $this->processCreditCardPayment($amount, $customerData);
                case 'bank_transfer':
                    return $this->processBankTransfer($amount, $customerData);
                default:
                    throw new \Exception("Unsupported payment method");
            }
        } catch (\Exception $e) {
            error_log("Payment processing error: " . $e->getMessage());
            return false;
        }
    }

    private function validatePaymentData($amount, $paymentMethod, $customerData) {
        if (!is_numeric($amount) || $amount <= 0) {
            throw new \Exception("Invalid payment amount");
        }
        if (empty($paymentMethod) || empty($customerData)) {
            throw new \Exception("Missing payment information");
        }
    }

    public function getPaymentHistory($customerId) {
        // Fetch payment history from database
        $query = "SELECT * FROM payments WHERE customer_id = ? ORDER BY payment_date DESC";
        return $this->db->query($query, [$customerId]);
    }

    public function generatePaymentReceipt($paymentId) {
        // Generate payment receipt
        // Implementation depends on your specific needs
    }
}
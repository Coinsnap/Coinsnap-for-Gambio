<?php declare(strict_types=1);

namespace GXModules\Shop\Classes\Entity;

class CoinsnapTransactionEntity {
    public const FIELD_ID = 'id';
    public const FIELD_TRANSACTION_ID = 'transaction_id';
    public const FIELD_CONFIRMATION_EMAIL_SENT = 'confirmation_email_sent';
    public const FIELD_DATA = 'data';
    public const FIELD_PAYMENT_METHOD = 'payment_method';
    public const FIELD_ORDER_ID = 'order_id';
    public const FIELD_STATE = 'state';
    public const FIELD_CREATED_AT = 'created_at';
    public const FIELD_UPDATED = 'updated_at';

    public $id;                     //  @var int $id
    public $transactionId;          //  @var int $transactionId
    public $confirmationEmailSent;  //  @var int $confirmationEmailSent
    public $data;                   //  @var string $data
    public $paymentMethod;          //  @var string $paymentMethod
    public $orderId;                //  @var int $orderId
    public $state;                  //  @var string $state
    public $createdAt;              //  @var string $createdAt
    public $updatedAt;              //  @var string $updatedAt

    public function __construct(array $entityData){ //  @param array $entityData
        $this   ->setId((int)$entityData[self::FIELD_ID])
                ->setTransactionId((int)$entityData[self::FIELD_TRANSACTION_ID])
		->setConfirmationEmailSent((int)(bool)$entityData[self::FIELD_CONFIRMATION_EMAIL_SENT])
		->setData($entityData[self::FIELD_DATA])
		->setPaymentMethod($entityData[self::FIELD_PAYMENT_METHOD])
		->setOrderId((int)$entityData[self::FIELD_ORDER_ID])
		->setState($entityData[self::FIELD_STATE])
		->setCreatedAt($entityData[self::FIELD_CREATED_AT])
		->setUpdatedAt($entityData[self::FIELD_UPDATED]);
    }

    public function getId(): int {
	return $this->id;
    }

    public function setId(int $id): CoinsnapTransactionEntity {
        $this->id = $id;
	return $this;
    }

    public function getTransactionId(): int {
	return $this->transactionId;
    }

    public function setTransactionId(int $transactionId): CoinsnapTransactionEntity {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function getConfirmationEmailSent(): int {
	return $this->confirmationEmailSent;
    }

    public function setConfirmationEmailSent(int $confirmationEmailSent): CoinsnapTransactionEntity {
	$this->confirmationEmailSent = $confirmationEmailSent;
	return $this;
    }

    public function getData(): string {
	return $this->data;
    }

    public function setData(string $data): CoinsnapTransactionEntity {
	$this->data = $data;
	return $this;
    }

    public function getPaymentMethod(): string {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): CoinsnapTransactionEntity {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getOrderId(): int {
        return $this->orderId;
    }

    public function setOrderId(int $orderId): CoinsnapTransactionEntity {
	$this->orderId = $orderId;
	return $this;
    }

    public function getState(): string {
	return $this->state;
    }

    public function setState(string $state): CoinsnapTransactionEntity{
	$this->state = $state;
	return $this;
    }

    public function getCreatedAt(): string {
	return $this->createdAt;
    }

    public function setCreatedAt(string $createdAt): CoinsnapTransactionEntity {
	$this->createdAt = $createdAt;
	return $this;
    }

    public function getUpdatedAt(): string {
	return $this->updatedAt;
    }

    public function setUpdatedAt(?string $updatedAt = null): CoinsnapTransactionEntity {
	$this->updatedAt = $updatedAt;
	return $this;
    }
}
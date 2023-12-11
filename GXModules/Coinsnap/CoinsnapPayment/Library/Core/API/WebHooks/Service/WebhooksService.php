<?php declare(strict_types=1);

namespace GXModules\Library\Core\Api\WebHooks\Service;

use GXModules\CoinsnapPayment\Library\Core\{
    Service\PaymentService, 
    Settings\Struct\Settings};
use CoinsnapStorage;

class WebHooksService {

    protected $webHookEntitiesConfig = [];

    protected $webHookEntityArrayConfig = [
    //  Transaction WebHook Entity Id
        [
            'id' => '1472041829003',
            'name' => 'Gambio4::WebHook::Transaction',
            'states' => [
		TransactionState::NEW,
		TransactionState::SETTLED,
		TransactionState::EXPIRED,
		TransactionState::PROCESSING,
            ],
            'notifyEveryChange' => false,
        ]
    ];

    public $settings;   //@var Settings $settings
    public $configuration;  //@var CoinsnapStorage $configuration

    public function __construct($configuration){
        $this->configuration = $configuration;
        $this->settings = new Settings($configuration);
        $this->setWebHookEntitiesConfig();
    }

    //  Set webhook configs
    protected function setWebHookEntitiesConfig(): void {
        foreach ($this->webHookEntityArrayConfig as $item) {
            $this->webHookEntitiesConfig[] = [
		"id" => $item['id'],
		"name" => $item['name'],
		"states" => $item['states'],
		"notifyEveryChange" => $item['notifyEveryChange']
            ];
	}
    }

	/**
	 * Install WebHooks
	 *
	 * @return array
	 * @throws \Coinsnap\ApiException
	 * @throws \Coinsnap\Http\ConnectionException
	 * @throws \Coinsnap\VersioningException
	 */
	public function install(): array
	{
		return $this->installListeners();
	}

	/**
	 * Install Listeners
	 *
	 * @return array
	 */
	protected function installListeners(): array
	{
		$returnValue = [];
		try {
			$webHookUrlId = $this->getOrCreateWebHookUrl()->getId();
			$installedWebHooks = $this->getInstalledWebHookListeners($webHookUrlId);
			$webHookEntityIds = array_map(function (WebhookListener $webHook) {
				return $webHook->getEntity();
			}, $installedWebHooks);

			foreach ($this->webHookEntitiesConfig as $data) {

				if (in_array($data['id'], $webHookEntityIds)) {
					continue;
				}

				$entity = (new WebhookListenerCreate())
					->setName($data['name'])
					->setEntity($data['id'])
					->setNotifyEveryChange($data['notifyEveryChange'])
					->setState(CreationEntityState::CREATE)
					->setEntityStates($data['states'])
					->setUrl($webHookUrlId);

				$returnValue[] = $this->settings->getApiClient()->getWebhookListenerService()->create($this->settings->getSpaceId(), $entity);
			}
		} catch (\Exception $exception) {
			throw $exception;
		}

		return $returnValue;
	}

	/**
	 * Create WebHook URL
	 *
	 * @return WebhookUrl
	 * @throws \Coinsnap\Sdk\ApiException
	 * @throws \Coinsnap\Sdk\Http\ConnectionException
	 * @throws \Coinsnap\Sdk\VersioningException
	 */
	protected function getOrCreateWebHookUrl(): WebhookUrl
	{
		$url = $this->getWebHookCallBackUrl();
		/** @noinspection PhpParamsInspection */
		$entityQueryFilter = (new EntityQueryFilter())
			->setType(EntityQueryFilterType::_AND)
			->setChildren([
				$this->getEntityFilter('state', CreationEntityState::ACTIVE),
				$this->getEntityFilter('url', $url),
			]);

		$query = (new EntityQuery())->setFilter($entityQueryFilter)->setNumberOfEntities(1);

		$webHookUrls = $this->settings->getApiClient()->getWebhookUrlService()->search($this->settings->getSpaceId(), $query);

		if (!empty($webHookUrls[0])) {
			return $webHookUrls[0];
		}

		/** @noinspection PhpParamsInspection */
		$entity = (new WebhookUrlCreate())
			->setName('Gambio4::WebHookURL')
			->setUrl($url)
			->setState(CreationEntityState::ACTIVE);

		return $this->settings->getApiClient()->getWebhookUrlService()->create($this->settings->getSpaceId(), $entity);
	}

	/**
	 * Creates and returns a new entity filter.
	 *
	 * @param string $fieldName
	 * @param        $value
	 * @param string $operator
	 *
	 * @return \Coinsnap\Sdk\Model\EntityQueryFilter
	 */
	protected function getEntityFilter(string $fieldName, $value, string $operator = CriteriaOperator::EQUALS): EntityQueryFilter
	{
		/** @noinspection PhpParamsInspection */
		return (new EntityQueryFilter())
			->setType(EntityQueryFilterType::LEAF)
			->setOperator($operator)
			->setFieldName($fieldName)
			->setValue($value);
	}

	/**
	 * Get web hook callback url
	 *
	 * @return string
	 */
	protected function getWebHookCallBackUrl(): string
	{
		$shopUrl = xtc_catalog_href_link("shop.php", 'do=CoinsnapWebhook/Index');
		return $shopUrl;
	}

	/**
	 * @param int $webHookUrlId
	 *
	 * @return array
	 * @throws \Coinsnap\Sdk\ApiException
	 * @throws \Coinsnap\Sdk\Http\ConnectionException
	 * @throws \Coinsnap\Sdk\VersioningException
	 */
	protected function getInstalledWebHookListeners(int $webHookUrlId): array
	{
		/** @noinspection PhpParamsInspection */
		$entityQueryFilter = (new EntityQueryFilter())
			->setType(EntityQueryFilterType::_AND)
			->setChildren([
				$this->getEntityFilter('state', CreationEntityState::ACTIVE),
				$this->getEntityFilter('url.id', $webHookUrlId),
			]);

		$query = (new EntityQuery())->setFilter($entityQueryFilter);

		return $this->settings->getApiClient()->getWebhookListenerService()->search($this->settings->getSpaceId(), $query);
	}

	/**
	 * @void
	 */
	public function synchronize(): void
	{
		$paymentService = new PaymentService($this->configuration);
		$paymentService->syncPaymentMethods();
	}

}
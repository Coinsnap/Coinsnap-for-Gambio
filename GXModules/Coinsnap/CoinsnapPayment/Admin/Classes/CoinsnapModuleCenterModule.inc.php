<?php declare(strict_types=1);

if (file_exists(dirname(__DIR__) . '/../loader.php')) {
    require_once dirname(__DIR__) . '/../loader.php';
}

//use GXModules\Library\Core\Service\PaymentService;

class CoinsnapModuleCenterModule extends AbstractModuleCenterModule {
	
    protected $paymentService;  //  @var PaymentService $paymentService
    
    protected $rootDir; //@var string
    public $settings; //@var Settings
    public $configuration; //@var CoinsnapStorage
    public $languageTextManager;

    protected function _init(): void {
        //$this->paymentService = new PaymentService();
        $this->name = 'Coinsnap';
        $this->title = 'Coinsnap ' . $this->languageTextManager->get_text('payment', 'coinsnap');
        $this->description = 'Coinsnap ' . $this->languageTextManager->get_text('description', 'coinsnap');
        $this->sortOrder = 10000;
    }

    //  Installs the module
    public function install(): void {
        $databasePath = dirname(__DIR__) . '/Database/';
        $possibleVersions = glob($databasePath . '*.sql');
        foreach ($possibleVersions as $migrationFile) {
            //$fileVersion = (int) str_replace([$databasePath, '.sql'], ['', ''], $migrationFile);
            //if ($fileVersion < $this->getVersion()) { continue; }
            try {
                $migrationContent = file_get_contents($migrationFile);
                $queries = explode("\n\n", $migrationContent);

		foreach ($queries as $query) {
                    if (empty($query)) { continue; }
                    xtc_db_query($query);
		}
            }
            catch (\Exception $e) {}
        }

	//$this->increaseVersion();
        parent::install();
    }

    //  Uninstalls the module
    public function uninstall() {
        parent::uninstall();
    }

    private function getVersion(): int {
        $configuration = $this->paymentService->getConfiguration();
        return (int) $configuration->get('version') ?? 1;
    }

    private function increaseVersion(): void{
        $configuration = $this->paymentService->getConfiguration();
        $configuration->set('version', $this->getVersion() + 1);
    }
}

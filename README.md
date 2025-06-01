# NovaPay SDK
NovaPay low-level API implementation

Class for working with account statement Novapay (via API)

Класс для получения онлайн выписки по расчетному счету в Новапей. Выписка выгружается через API. Документация API - ссылки с описанием ниже.

Класс для отримання онлайн виписок за розрахунковим рахунком в Новапей. Виписки вивантажуються через АРІ. Документація АРШ - посилання з описом нижче.


NovaPay API:


 
 
 
## Installation

Install using composer:

```bash
composer require abertime/novapay-sdk
```

## Usage

Create 2 tables in your DB
```bash
-- TABLE FOR TOKENS
CREATE TABLE IF NOT EXISTS flp_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,         -- id
    label VARCHAR(255) NOT NULL,               -- pr label
    novapay_login VARCHAR(255) NOT NULL,       -- login
    novapay_password VARCHAR(255) NOT NULL,    -- pass
    novapay_principal VARCHAR(255) DEFAULT NULL,   -- temp_token
    novapay_expiration DATETIME DEFAULT NULL,      -- date of expire
    novapay_client_id INT DEFAULT NULL            -- client_id novapay
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLE FOR NOVA PAY TRANSACTIONS
CREATE TABLE IF NOT EXISTS novapay_transactions (
    flp_id INT NOT NULL,                 -- pr id
    account_id INT NOT NULL,             -- novapay client_id
    code VARCHAR(50) NOT NULL,           -- code of transactions
    label VARCHAR(255) NOT NULL,         -- pr name
    date DATE NOT NULL,                  -- date
    amount DECIMAL(15,2) NOT NULL,       -- sum of transaction
    currency VARCHAR(10) NOT NULL,       -- currency
    purpose VARCHAR(255) NULL,           -- comment (purpose)
    sender_name VARCHAR(255) NULL,       -- counter name
    PRIMARY KEY (flp_id, account_id, code, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

```php
<?php
declare(strict_types=1);

//    If you are not using Composer, replace this with direct require statements
//    for TokenRepository.php, TransactionRepository.php, and Client.php.
require __DIR__ . '/vendor/autoload.php';

//  Load database configuration and $pdo instance
require __DIR__ . '/config.php';

use Abertime\NovaPaySDK\Client;
use Abertime\NovaPaySDK\Repositories\TokenRepository;
use Abertime\NovaPaySDK\Repositories\TransactionRepository;

//  Create a SoapClient to interact with NovaPay web service
$wsdl = 'https://business.novapay.ua/Services/ClientAPIService.svc?wsdl';
$soapOptions = [
    'trace'      => true,              // Enable request/response tracing
    'exceptions' => true,              // Throw exceptions on SOAP faults
    'cache_wsdl' => WSDL_CACHE_NONE,   // Disable WSDL caching for development
];
$soapClient = new SoapClient($wsdl, $soapOptions);

// Instantiate repositories by passing the PDO instance
$tokenRepo = new TokenRepository($pdo);
$txRepo    = new TransactionRepository($pdo);

// Instantiate the NovaPay Client with SoapClient and repositories
$client = new Client($soapClient, $tokenRepo, $txRepo);

//  Define date range for importing transactions (format: d.m.Y)
$fromDate = '01.06.2025';
$toDate   = '30.06.2025';

// Run the import process: fetch accounts, extract transactions, and save to DB
$client->runImport($fromDate, $toDate);

echo "Import completed successfully.\n";

```

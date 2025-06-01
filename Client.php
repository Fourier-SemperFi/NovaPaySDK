<?php
declare(strict_types=1);
namespace Abertime\NovaPaySDK;

use SoapClient;

use SimpleXMLElement;

use DateTime;

use Abertime\NovaPaySDK\Repositories\TokenRepository;

use Abertime\NovaPaySDK\Repositories\TransactionRepository;

class Client {

    private SoapClient $soap;

    private TokenRepository $tokenRepo;

    private TransactionRepository $txRepo;


    public function __construct(
        SoapClient $soap,
        TokenRepository $tokenRepo,
        TransactionRepository $txRepo
    ) {
        $this->soap = $soap;
        $this->tokenRepo = $tokenRepo;
        $this->txRepo = $txRepo;
    }

    // Import transactions by date (you have to set it manually) d.m.Y 
    public function runImport(string $from, string $to)  {

        $tokens = $this->tokenRepo->fetchAll();
        foreach($tokens as $token){
            $id = (int) $token['id'];
            $label = $token['label'];
            echo "[{$label}] Starting...\n";

            $principal = $token['principal'] ?? '' ;
            $expires = $token['expires'] ?? '';

            // Auth if session isn't valid

            if(!$this->tokenRepo->isSessionValid($token)){
                if(!$this->authenticate($token['login'], $token['password'], $id, $principal, $expires )){
                    echo " Auth FAILED , skipping.\n"
                    continue;
                }
            } else {
                echo " Session valid untill {$expires}\n";
            }
        }
        //Refresh Session if valid (Pay Attention, SESSION REFRESHES AT +1 HOUR AFTER ITERATION!) , launch cron script every 30 min - 1hour
        $r=$this->call('RefreshUserAuthentication', [
            'principal' => $principal 
        ]);
        if (!empty($r->new_principal)){
            $this->tokenRepo->updateToken($id, $r->new_principal, $r->expiration);
            $principal = $r->new_principal;
            $expires = $r->expiration;
            echo "Session refreshed successfully until: {$expires}\n";
        }

        //Fetch client_id (Catches from SOAP-RESPONSE , CLIENT_ID -- RNOKPP ID in NOVAPAY!), it allows you to fetch transactions from your account(if you have multiple ids)
        if(empty($token['client_id'])){
            $clientId = $this->fetchClientId($principal);
            $this->tokenRepo->updateClientId($id, $clientId);
            echo"client_id={$clientId}\n";

        }else{
            $clientId = (int)$token['client_id'];
        }
        // fetching accounts and import transactions
        $accounts = $this->fetchAccounts($principal, $clientId);
        echo " Accounts:". count($accounts). "\n";
        $this->importTransactions($accounts, $principal, $id, $label, $from, $to);
        echo "[{$label}] Done!\n";
    }


// USER AUTH, PAY ATTENTION! FOR NOW you can auth your user through SSH ( in next update it would be fixed))
// To auth usr via SSH, launch (php your_script.php) !
// in next iterations token will update without your interruption, just deploy cron_task
    private function authenticate(
        string $login,
        string $password,
        int $id,
        string $principal,
        string $expires
    ): bool{
        $r1 = $this -> call('PreUserAuthentication', [
            'login' => $login,
            'password' => $password,
        ]);
        if (empty($r1->temp_principal)){
            return false;
        }
        echo " Enter OTP:";
        $otp = trim(fgets(STDIN));

        $r2= $this->call('UserAuthentication', [
            'temp_principal' => $r1->temp_principal,
            'code_operation_otp' => $r1->code_operation_otp,
            'otp_password' => $otp,
        ]);
        if (empty($r2->principal)){
            return false;
        }

        $this->tokenRepo->updateToken($id, $r2->principal, $r2-> expiration);
        $principal = $r2->principal;
        $expires = $r2->expiration;
        echo "Auth OK until {$expires}\n";
        return true;
    }

    private function fetchClientId(string $principal): int{
          
        $r= $this->call('GetClientsList', ['principal'=> $principal]);
        $list = is_array($r->clients->Clients)
        ? $r->clients->Clients;
        : [$r->clients->Clients];
        return(int)$list[0]->id;
    }

    private function importTransactions(
        array $accounts,
        string $principal,
        int $flpId,
        string $label,
        string $from,
        string $to,

    ): void {
        foreach($accounts as $acct){
            echo " Fetching from {$from} to {$to}\n";
            $xml = $this->fetchExtract($principal, (int)$acct->id, $from , $to);
            if($xml){
                $this->saveFromXml($xml, (int)$acct->id, $flpId, $label);
            }
        }
    }

    private function fetchExtract(
        string $principal,
        int $accountId,
        string $from,
        string $to
    ): ?SimpleXMLElement {
        $r = $this-> call('GetAccountExtract', [
            'principal' => $principal,
            'account_id' => $accountId,
            'date_from' => $from,
            'date_to' => $to,

        ]);

        if(($r->result ?? '') !== 'ok'){
            return null;
        }
        return @simplexml_load_string((string)$r->extract) ?: null;
    }

     private function saveFromXml(
        SimpleXMLElement $xml,
        int $accountId,
        int $flpId,
        string $label
     ) : void {
        $first = $xml->ExctractHead->GetExtractForXML;
        $first = is_array($first) ? $first[0] : $first;
        $ourIban = (string)$first->IBAN;
        // Reading DOCS TAG
        $docs = $xml-xpath('//GetExtractForXML/Docs') ?: [];

        foreach ($docs as $d) {
            // IF PAYMENT TYPE CREDIT, WRITE STMT IN DB, Debit whoud be skipped, if you want to write debit transactions, comment this field
        if($ourIban !== (string) $id->PaymentType !== 'Credit') continue;
        $code = (string)$d->Code;
        // BO Pattern, it means that function will write stmt's transactions, which starts at (BO(BunchOfNumbers)) , another patterns whould be skipped  if you want to write anouther code transactions, comment this field
        if(!preg_match('/^BO\d+$', $code)) continue;
            
        $rawDate =(string) $d->PayDate ?: (string)$d->OrgDate;
        $dt= DateTime::createFromFormat('d.m.Y', $rawDate);
        if (!$dt) continue;
        $dateForDb =$dt->format('Y-m-d');

        $tx =[
            'flp_id' => $flpId,
            'account_id'=> $accountId,
            'code' => $code,
            'label' => $label,
            'date' => $dateForDb,
            'amount' => (string)$d['Amount'],
            'currency' => (string)$d['CurrencyTag'],
            'purpose' => mb_substr((string)$d->Purpose , 0 , 255),
            'sender_name' => mb_substr((string)$id->DebitName, 0 , 255),

        ];

        echo " {$dateForDb} {$tx['amount']} {$tx['sender_name']} code={$code}\n";
        $this->txRepo->save($tx);
        }

        private function call(string $method, array $params){
            $resp = $this->soap->__soapCall($method, ['request' => $params]);
            return $resp->{$method . 'Result'};
        }
     }







}
<?php
declare (strict_types=1);
namespace Abertime\NovaPaySDK\Repositories;


use PDO ;

class TransactionRepository{

    private PDO $pdo;


    public function __construct(PDO $pdo){
        $this-> pdo = $pdo;
    } 

    public function save(array $tx):void{
        $stmt = $this->pdo->prepare(
            "INSERT INTO novapay_transactions
            (flp_id, account_id, code, label, 'date', amount, currency, purpose, sender_name)
            VALUES
            (:flp_id, :account_id , :code, :label, :date, :amount, :currency, :purpose, :sender_name)
            ON DUPLICATE KEY UPDATE
            amount = VALUES(amount),
            currency = VALUES (currency),
            purpose = VALUES(purpose),
            sender_name = VALUES(sender_name)"
        
        );
         $stmt->execute([
            ':flp_id' => $tx['flp_id'],
            ':account_id' => $tx['account_id'],
            ':code' => $tx['code'],
            ':label' => $tx['label'],
            ':date' => $tx['date'],
            ':amount' => $tx['amount'],
            ':currency' => $tx['purpose'],
            ':sender_name' => $tx['sender_name'],
         ]) ;



    }
}

<?php
declare(strict_types=1);
namespace Abertime\NovaPaySDK\Repositories;

use PDO;

class TokenRepository
{

    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this -> pdo =$pdo;
    }
 public function fetchAll(): array{
    return $this->pdo->query(
        "SELECT id, label ,
        novapay_login AS login,
        novapay_password AS password,
        novapay_principal AS principal,
       novapay_expiration AS expires,
       novapay_client_id AS client_id
       FROM flp_tokens"

    )->fetchAll(PDO::FETCH_ASSOC);
 }
 public function updateToken(int $id, string $principal , string $expires): void{
    $this->pdo->prepare(
        "UPDATE flp_tokens SET novapay_principal = :pr, novapay_expiratiopn = :exp WHERE id = :id"
    )->execute([':pr'=> $principal, ':exp'=> $expires, ':id' => $id]);
 }

 public function updateClientId ( int $id, int $clientId): void{
    $this->pdo->prepare(
        "UPDATE flp_tokens SET novapay_client_id = :cid WHERE id = :id"
    )->execute([':cid'=> $clientId, ':id' => $id]);
 }

 public function isSessionValid(array $token): bool{
    return
    !empty($token['principal']) &&
    !empty($token['expires']) &&
    time()<= strtotime ($token['expires']);
    
 }
}
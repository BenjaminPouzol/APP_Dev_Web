<?php

class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

<<<<<<< HEAD
<<<<<<< HEAD
    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
=======
=======
>>>>>>> d58d48942018bbf3474db54714ed754249b0ce24
    public function getByEmail($email) {
    $sql = "SELECT * FROM users WHERE email = :email";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['email' => $email]);
    return $stmt->fetch();
}
<<<<<<< HEAD
>>>>>>> origin/test
=======
>>>>>>> d58d48942018bbf3474db54714ed754249b0ce24
}
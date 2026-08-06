<?php

class Database {
    private $host;
    private $port;
    private $db;
    private $user;
    private $pass;
    private $pdo;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'db';
        $this->port = getenv('DB_PORT') ?: '5432';
        $this->db = getenv('DB_DATABASE') ?: 'eksporsiap';
        $this->user = getenv('DB_USERNAME') ?: 'postgres';
        $this->pass = getenv('DB_PASSWORD') ?: 'postgres';
    }

    public function getConnection() {
        $this->pdo = null;

        try {
            $dsn = "pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db;
            $this->pdo = new PDO($dsn, $this->user, $this->pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->pdo;
    }
}

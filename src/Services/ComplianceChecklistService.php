<?php

require_once __DIR__ . '/../Config/database.php';

class ComplianceChecklistService {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // RULE-BASED: Bagian ini hanya melakukan query deterministik statis ke database berdasarkan negara. Tidak ada proses AI/Embedding di sini.
    public function getByCountry(string $country): array {
        $stmt = $this->db->prepare("SELECT * FROM compliance_checklist WHERE negara = :negara");
        $stmt->bindParam(':negara', $country);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}

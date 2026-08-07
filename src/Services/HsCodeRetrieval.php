<?php

require_once __DIR__ . '/../Config/database.php';

class HsCodeRetrieval {
    private $db;
    private $apiUrl;
    private $apiKey;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->apiUrl = getenv('EMBEDDING_API_URL');
        $this->apiKey = getenv('EMBEDDING_API_KEY');
    }

    // CORE AI: Bagian ini menggunakan model embedding (AI) sesungguhnya untuk mencari relevansi semantik, bukan sekadar pencocokan string/keyword.
    public function retrieve(string $description): array {
        $embedding = $this->generateEmbedding($description);
        
        $stmt = $this->db->query("SELECT id, hs_code, deskripsi_resmi, bab, embedding FROM hs_code_reference WHERE embedding IS NOT NULL");
        $allHsCodes = $stmt->fetchAll();

        $results = [];
        foreach ($allHsCodes as $row) {
            $dbEmbedding = json_decode($row['embedding'], true);
            $similarity = $this->cosineSimilarity($embedding, $dbEmbedding);
            
            $results[] = [
                'hs_code' => $row['hs_code'],
                'deskripsi_resmi' => $row['deskripsi_resmi'],
                'skor_similarity' => $similarity,
                'alasan' => 'Relevan berdasarkan skor kemiripan semantik (' . round($similarity, 2) . ') pada Bab ' . $row['bab']
            ];
        }

        usort($results, function($a, $b) {
            return $b['skor_similarity'] <=> $a['skor_similarity'];
        });

        $uniqueResults = [];
        foreach ($results as $res) {
            if (!isset($uniqueResults[$res['hs_code']])) {
                $uniqueResults[$res['hs_code']] = $res;
            }
            if (count($uniqueResults) >= 3) {
                break;
            }
        }

        return array_values($uniqueResults);
    }

    private function generateEmbedding(string $text): array {
        if (empty($this->apiUrl) || empty($this->apiKey)) {
            throw new Exception("Konfigurasi EMBEDDING_API_URL atau EMBEDDING_API_KEY tidak ditemukan di environment.");
        }

        $data = json_encode([
            'model' => getenv('EMBEDDING_MODEL') ?: 'models/gemini-embedding-001',
            'content' => ['parts' => [['text' => $text]]]
        ]);
        
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Network error saat memanggil API Embedding: " . $error);
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("API Embedding mengembalikan error HTTP: " . $httpCode . " - " . $response);
        }
        
        $responseData = json_decode($response, true);
        
        if (!isset($responseData['embedding']['values'])) {
            throw new Exception("Format respons API Embedding tidak sesuai: " . $response);
        }
        
        return $responseData['embedding']['values'];
    }

    private function cosineSimilarity(array $vecA, array $vecB): float {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;
        $count = min(count($vecA), count($vecB));
        
        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] * $vecA[$i];
            $normB += $vecB[$i] * $vecB[$i];
        }
        
        if ($normA == 0 || $normB == 0) {
            return 0;
        }
        
        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}

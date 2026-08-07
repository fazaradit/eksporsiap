<?php

require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Services/HsCodeRetrieval.php';

$database = new Database();
$db = $database->getConnection();

echo "Memulai import data...\n";

// --- 1. Import Compliance Checklist (Rule-based) ---
$complianceFile = __DIR__ . '/../database/seed_compliance_checklist.csv';
if (file_exists($complianceFile)) {
    echo "Importing compliance checklist...\n";
    $handle = fopen($complianceFile, "r");
    fgetcsv($handle, 1000, ";"); // Skip header
    
    $stmt = $db->prepare("INSERT INTO compliance_checklist (negara, kategori_dokumen, deskripsi, wajib_opsional, catatan, sumber_referensi) VALUES (:negara, :kategori_dokumen, :deskripsi, :wajib_opsional, :catatan, :sumber_referensi) ON CONFLICT (negara, kategori_dokumen) DO UPDATE SET deskripsi = EXCLUDED.deskripsi, wajib_opsional = EXCLUDED.wajib_opsional, catatan = EXCLUDED.catatan, sumber_referensi = EXCLUDED.sumber_referensi");
    
    while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
        if (count($data) < 6) continue;
        
        $stmt->execute([
            ':negara' => $data[0],
            ':kategori_dokumen' => $data[1],
            ':deskripsi' => $data[2],
            ':wajib_opsional' => $data[3],
            ':catatan' => $data[4],
            ':sumber_referensi' => $data[5]
        ]);
    }
    fclose($handle);
    echo "✅ Compliance checklist imported.\n";
} else {
    echo "❌ File seed_compliance_checklist.csv tidak ditemukan.\n";
}

// --- 2. Import HS Code Reference (Core AI) ---
$hsCodeFile = __DIR__ . '/../database/seed_hs_code.csv';
if (file_exists($hsCodeFile)) {
    echo "\nImporting HS Codes and generating embeddings...\n";
    
    $apiUrl = getenv('EMBEDDING_API_URL');
    $apiKey = getenv('EMBEDDING_API_KEY');
    $model = getenv('EMBEDDING_MODEL') ?: 'text-embedding-ada-002';
    
    if (empty($apiUrl) || empty($apiKey)) {
        echo "⚠️ WARNING: EMBEDDING_API_URL atau EMBEDDING_API_KEY belum diset. Import HS Code dibatalkan.\n";
    } else {
        $handle = fopen($hsCodeFile, "r");
        fgetcsv($handle, 1000, ";"); // Skip header
        
        $stmtInsert = $db->prepare("INSERT INTO hs_code_reference (hs_code, deskripsi_resmi, bab, kata_kunci_produk, embedding) VALUES (:hs_code, :deskripsi_resmi, :bab, :kata_kunci_produk, :embedding)");
        
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            if (count($data) < 4) continue;
            
            $hs_code = $data[0];
            $deskripsi_resmi = $data[1];
            $bab = $data[2];
            $kata_kunci_produk = $data[3];
            
            // Konteks teks = deskripsi resmi + kata kunci
            $textSource = $deskripsi_resmi . " " . $kata_kunci_produk;
            
            echo "Memproses HS Code: $hs_code ...\n";
            
            // Panggil API Embedding
            $postData = json_encode([
                'model' => $model,
                'content' => ['parts' => [['text' => $textSource]]]
            ]);
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey
            ]);
            
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                echo "❌ Network error: " . curl_error($ch) . "\n";
                continue;
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 400) {
                echo "❌ API Error $httpCode: $response\n";
                continue;
            }
            
            $respData = json_decode($response, true);
            if (!isset($respData['embedding']['values'])) {
                echo "❌ Invalid API response format\n";
                continue;
            }
            
            $embeddingVector = $respData['embedding']['values'];
            $embeddingJson = json_encode($embeddingVector);
            
            // Insert data baru (tiap baris sebagai data independen)
            $stmtInsert->execute([
                ':hs_code' => $hs_code,
                ':deskripsi_resmi' => $deskripsi_resmi,
                ':bab' => $bab,
                ':kata_kunci_produk' => $kata_kunci_produk,
                ':embedding' => $embeddingJson
            ]);
            echo "  ✅ Sukses tersimpan.\n";
        }
        fclose($handle);
        echo "✅ Proses import HS Codes selesai.\n";
    }
} else {
    echo "❌ File seed_hs_code.csv tidak ditemukan.\n";
}

echo "\nSelesai.\n";

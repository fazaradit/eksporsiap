<?php

require_once __DIR__ . '/../src/Services/HsCodeRetrieval.php';
require_once __DIR__ . '/../src/Services/ComplianceChecklistService.php';

echo "=== TEST HS CODE RETRIEVAL (CORE AI) ===\n";
try {
    $hsService = new HsCodeRetrieval();
    $input_produk = "Kopi arabika robusta biji mentah";
    echo "Mencari HS Code untuk: \"$input_produk\"\n";
    
    $response = $hsService->retrieve($input_produk);
    $results = $response['kandidat'];
    $peringatan = $response['peringatan'];
    
    if ($peringatan) {
        echo "⚠️ PERINGATAN: " . $peringatan . "\n\n";
    }
    
    if (empty($results)) {
        echo "Tidak ada hasil ditemukan (Mungkin database kosong/belum diimport)\n";
    } else {
        foreach ($results as $index => $res) {
            echo ($index + 1) . ". HS Code: " . $res['hs_code'] . "\n";
            echo "   Deskripsi: " . $res['deskripsi_resmi'] . "\n";
            echo "   Skor Kemiripan: " . round($res['skor_similarity'], 4) . "\n";
            echo "   Alasan: " . $res['alasan'] . "\n\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error HS Code Retrieval: " . $e->getMessage() . "\n";
}

echo "=== TEST COMPLIANCE CHECKLIST (RULE-BASED) ===\n";
try {
    $complianceService = new ComplianceChecklistService();
    $negara = "Jepang"; // Atau "Malaysia"
    echo "Mencari dokumen compliance wajib untuk: $negara\n";
    
    $checklists = $complianceService->getByCountry($negara);
    
    if (empty($checklists)) {
        echo "Tidak ada checklist ditemukan untuk negara tersebut.\n";
    } else {
        foreach ($checklists as $chk) {
            echo "- [" . $chk['wajib_opsional'] . "] " . $chk['kategori_dokumen'] . ": " . $chk['deskripsi'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error Compliance Checklist: " . $e->getMessage() . "\n";
}

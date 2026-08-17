<?php

require_once __DIR__ . '/../Services/HsCodeRetrieval.php';
require_once __DIR__ . '/../Services/ComplianceChecklistService.php';

class ClassifyController {
    const MIN_RELEVANCE_SCORE = 0.60;
    
    public function classify() {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed. Use POST.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        $deskripsi = trim($input['deskripsi_produk'] ?? '');
        $negara = trim($input['negara_tujuan'] ?? '');
        $komposisi = trim($input['komposisi'] ?? '');
        $jenis_kemasan = trim($input['jenis_kemasan'] ?? '');
        $berat = trim($input['berat'] ?? '');
        $satuan_berat = trim($input['satuan_berat'] ?? '');
        
        if (empty($deskripsi)) {
            http_response_code(400);
            echo json_encode(['error' => 'deskripsi_produk tidak boleh kosong.']);
            return;
        }
        
        $negaraLower = strtolower($negara);
        if ($negaraLower !== 'malaysia' && $negaraLower !== 'jepang') {
            http_response_code(400);
            echo json_encode(['error' => 'negara_tujuan harus Malaysia atau Jepang.']);
            return;
        }
        
        // Kapitalisasi yang benar untuk lookup database (Malaysia / Jepang)
        $negaraTujuan = ucfirst($negaraLower);
        
        $teksLengkap = $deskripsi;
        if (!empty($komposisi)) {
            $teksLengkap .= ". Komposisi: " . $komposisi;
        }
        if (!empty($jenis_kemasan) && $jenis_kemasan !== '(Tidak diisi)') {
            $teksLengkap .= ". Kemasan: " . $jenis_kemasan;
        }
        if (!empty($berat)) {
            $teksLengkap .= ", berat " . $berat . " " . $satuan_berat;
        }

        try {
            // 2. Retrieval kandidat HS Code
            $hsService = new HsCodeRetrieval();
            $hsResult = $hsService->retrieve($teksLengkap);
            
            $kandidat = $hsResult['kandidat'] ?? [];
            $peringatan = $hsResult['peringatan'] ?? null;
            
            $topScore = !empty($kandidat) ? ($kandidat[0]['skor_similarity'] ?? 0) : 0;
            
            if (empty($kandidat) || $topScore < self::MIN_RELEVANCE_SCORE) {
                echo json_encode([
                    'status' => 'tidak_terdeteksi',
                    'pesan' => 'Deskripsi produk tidak terdeteksi sebagai kategori pangan olahan yang valid. Silakan masukkan deskripsi produk yang lebih spesifik.',
                    'kandidat_hs_code' => [],
                    'checklist_dokumen' => [],
                    'ringkasan' => null,
                    'peringatan_confidence' => null
                ]);
                return;
            }
            
            // 3. Retrieval checklist dokumen
            $complianceService = new ComplianceChecklistService();
            $checklist = $complianceService->getByCountry($negaraTujuan);
            
            $jumlahWajib = count(array_filter($checklist, function($c) {
                return strtolower($c['wajib_opsional']) === 'wajib';
            }));
            
            // 4 & 5. Panggil LLM Gemini untuk ringkasan (atau fallback jika gagal)
            $ringkasanData = $this->generateSummary($kandidat, $peringatan, $jumlahWajib, $negaraTujuan);
            
            echo json_encode([
                'status' => 'berhasil',
                'kandidat_hs_code' => $kandidat,
                'peringatan_confidence' => $peringatan,
                'checklist_dokumen' => $checklist,
                'ringkasan' => $ringkasanData['ringkasan'],
                'ringkasan_sumber' => $ringkasanData['sumber']
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Terjadi kesalahan internal: ' . $e->getMessage()]);
        }
    }

    private function generateSummary(array $kandidat, ?string $peringatan, int $jumlahWajib, string $negara): array {
        $llmModel = getenv('LLM_MODEL') ?: 'gemini-1.5-flash';
        $apiKey = getenv('EMBEDDING_API_KEY'); // Gunakan key yang sama
        
        if (empty($apiKey)) {
            return $this->getFallbackSummary($kandidat, $peringatan, $jumlahWajib, $negara);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$llmModel}:generateContent";
        
        $contextText = "Data Kandidat:\n" . json_encode($kandidat) . "\n";
        $contextText .= "Peringatan: " . ($peringatan ?: "Tidak ada") . "\n";
        $contextText .= "Jumlah Dokumen Wajib: $jumlahWajib\n";
        
        $prompt = "Kamu adalah asisten yang membantu UMKM Indonesia memahami hasil klasifikasi HS Code dan syarat ekspor. Berdasarkan data kandidat HS Code dan checklist dokumen yang diberikan, buat ringkasan singkat (maksimal 4 kalimat) yang:\n";
        $prompt .= "1. Menjelaskan kandidat HS Code paling relevan dalam bahasa yang mudah dipahami pelaku UMKM (bukan bahasa hukum/teknis)\n";
        $prompt .= "2. Jika ada peringatan skor rendah, sampaikan dengan jelas bahwa hasil ini kurang meyakinkan dan sarankan verifikasi manual\n";
        $prompt .= "3. Sebutkan singkat jumlah dokumen wajib yang perlu disiapkan untuk negara tujuan\n";
        $prompt .= "4. WAJIB tutup dengan kalimat: 'Hasil ini adalah rekomendasi awal dan bukan pengganti konsultasi resmi dengan pihak Bea Cukai atau customs broker.'\n";
        $prompt .= "Jawab HANYA dalam format JSON: {\"ringkasan\": \"...\"}\n\n";
        $prompt .= $contextText;

        $postData = json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey
        ]);
        
        // Timeout agar request tidak hang jika network lambat
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Handle sukses
        if ($httpCode >= 200 && $httpCode < 300 && $response) {
            $respData = json_decode($response, true);
            if (isset($respData['candidates'][0]['content']['parts'][0]['text'])) {
                $llmText = $respData['candidates'][0]['content']['parts'][0]['text'];
                
                // Hapus markdown block ```json jika ada
                $llmText = preg_replace('/```json/i', '', $llmText);
                $llmText = preg_replace('/```/', '', $llmText);
                $llmText = trim($llmText);
                
                $parsed = json_decode($llmText, true);
                if (isset($parsed['ringkasan'])) {
                    return [
                        'ringkasan' => $parsed['ringkasan'],
                        'sumber' => 'llm'
                    ];
                }
            }
        }
        
        // Jika gagal LLM, logging ke PHP error log lalu fallback
        error_log("LLM Gemini API Failed: HTTP $httpCode - Response: $response");
        
        return $this->getFallbackSummary($kandidat, $peringatan, $jumlahWajib, $negara);
    }
    
    private function getFallbackSummary(array $kandidat, ?string $peringatan, int $jumlahWajib, string $negara): array {
        $hsCode = !empty($kandidat) ? $kandidat[0]['hs_code'] : 'N/A';
        $deskripsi = !empty($kandidat) ? $kandidat[0]['deskripsi_resmi'] : 'Tidak diketahui';
        
        $ringkasan = "Kandidat HS Code teratas: $hsCode ($deskripsi). ";
        if ($peringatan) {
            $ringkasan .= "Hasil pencarian ini kurang meyakinkan dan perlu verifikasi manual. ";
        }
        $ringkasan .= "Terdapat $jumlahWajib dokumen wajib untuk $negara. Hasil ini adalah rekomendasi awal dan bukan pengganti konsultasi resmi dengan pihak Bea Cukai atau customs broker.";
        
        return [
            'ringkasan' => $ringkasan,
            'sumber' => 'fallback'
        ];
    }
}

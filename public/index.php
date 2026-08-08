<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle API requests
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($requestUri === '/api/classify') {
    require_once __DIR__ . '/../src/Controllers/ClassifyController.php';
    $controller = new ClassifyController();
    $controller->classify();
    exit;
}

// Existing fallback compatibility
$page = $_GET['page'] ?? 'home';
if ($page !== 'home') {
    echo "<h1>Halaman tidak ditemukan</h1>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EksporSiap - AI Copilot Compliance Ekspor untuk UMKM</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@700&family=Roboto+Slab:wght@700&display=swap');
        
        :root {
            --bg-color: #F1EEE6;
            --text-main: #1E2A4A;
            --stempel-merah: #B23A2E;
            --stempel-kuningan: #A67C3D;
            --card-bg: #FFFFFF;
            --border: rgba(30, 42, 74, 0.2);
            --text-muted: #52607D;
        }
        
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); line-height: 1.6; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        
        h1, h2, h3, h4, h5, h6 { font-family: 'Roboto Slab', serif; font-weight: 700; color: var(--text-main); margin-top: 0; }
        
        header { text-align: center; margin-bottom: 2.5rem; padding-bottom: 1rem; }
        header h1 { font-size: 2.75rem; letter-spacing: -0.02em; margin-bottom: 0.5rem; }
        header p { color: var(--text-muted); margin: 0; font-size: 1.15rem; }
        
        .card { background: var(--card-bg); border-radius: 4px; padding: 24px; border: 1px solid var(--border); margin-bottom: 24px; box-shadow: 2px 2px 0 rgba(30, 42, 74, 0.05); }
        
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-main); }
        textarea, select { width: 100%; padding: 0.875rem; border: 1px solid var(--border); border-radius: 4px; font-size: 1rem; background-color: #FAFAFA; transition: border-color 0.2s; }
        textarea:focus, select:focus { outline: none; border-color: var(--text-main); }
        
        button { background-color: var(--text-main); color: white; border: none; padding: 1rem 1.5rem; border-radius: 4px; font-size: 1rem; font-weight: 600; cursor: pointer; width: 100%; transition: opacity 0.2s; font-family: 'Roboto Slab', serif; letter-spacing: 0.05em; text-transform: uppercase; }
        button:hover { opacity: 0.9; }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .hidden { display: none !important; }
        
        .alert-warning { background-color: #FFF5F5; color: var(--stempel-merah); padding: 16px; border-radius: 4px; margin-bottom: 24px; font-weight: 600; display: flex; align-items: flex-start; gap: 12px; border: 2px solid var(--stempel-merah); }
        .alert-error { background-color: #FFF5F5; color: var(--stempel-merah); padding: 16px; border-radius: 4px; margin-bottom: 24px; font-weight: 600; border: 1px solid var(--stempel-merah); }
        .alert-info { background-color: #F8FAFC; color: var(--text-main); padding: 16px; border-radius: 4px; margin-bottom: 24px; font-weight: 500; display: flex; align-items: flex-start; gap: 12px; border: 1px solid var(--border); }
        
        #result-area { border-top: 2px dashed var(--border); padding-top: 32px; margin-top: 8px; }
        
        .summary-box { background-color: #FAFAFA; border: 1px solid var(--border); border-radius: 4px; padding: 24px; margin-bottom: 32px; border-left: 4px solid var(--text-main); }
        .summary-box h3 { margin-bottom: 12px; font-size: 1.25rem; }
        .summary-box p { margin: 0; font-size: 1.1rem; font-weight: 500; }
        
        .section-title { font-size: 1.25rem; margin-top: 32px; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 8px; }
        
        .hs-card { border: 1px solid var(--border); border-radius: 4px; padding: 16px; margin-bottom: 12px; background: white; }
        .hs-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .hs-code { font-family: 'JetBrains Mono', monospace; font-size: 1.35rem; font-weight: 700; color: var(--text-main); }
        
        .hs-score { 
            font-family: 'JetBrains Mono', monospace; 
            padding: 4px 10px; 
            border-radius: 50px; 
            font-size: 0.85rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            transform: rotate(-3deg);
            border: 3px double currentColor;
            display: inline-block;
            background: transparent;
        }
        .score-high { color: var(--stempel-kuningan); border-color: var(--stempel-kuningan); }
        .score-low { color: var(--stempel-merah); border-color: var(--stempel-merah); }
        
        .hs-desc { font-weight: 600; margin-bottom: 8px; font-size: 1.05rem; }
        .hs-reason { font-size: 0.95rem; color: var(--text-muted); }
        
        .checklist-item { display: flex; gap: 16px; padding: 16px 0; border-bottom: 1px solid var(--border); align-items: flex-start; }
        .checklist-item:last-child { border-bottom: none; }
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; white-space: nowrap; border: 2px solid currentColor; background: transparent; transform: rotate(-2deg); width: 90px; text-align: center; flex-shrink: 0; }
        .badge-wajib { color: var(--stempel-merah); }
        .badge-opsional { color: var(--stempel-kuningan); }
        .doc-details h4 { margin: 0 0 4px 0; font-size: 1.05rem; }
        .doc-details p { margin: 0 0 4px 0; font-size: 0.95rem; color: var(--text-muted); }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>EksporSiap</h1>
        <p>AI Copilot Compliance Ekspor untuk UMKM Indonesia</p>
    </header>

    <div id="error-container" class="alert-error hidden"></div>

    <div class="card">
        <form id="classify-form">
            <div class="form-group">
                <label for="deskripsi_produk">Deskripsi Produk</label>
                <textarea id="deskripsi_produk" rows="3" placeholder="Contoh: kerupuk udang kemasan plastik 250gr" style="font-family: 'JetBrains Mono', monospace;" required></textarea>
            </div>
            <div class="form-group">
                <label for="negara_tujuan">Negara Tujuan Ekspor</label>
                <select id="negara_tujuan" required>
                    <option value="" disabled selected>Pilih negara tujuan...</option>
                    <option value="Malaysia">Malaysia</option>
                    <option value="Jepang">Jepang</option>
                </select>
            </div>
            <button type="submit" id="submit-btn">Analisis Produk</button>
        </form>
    </div>

    <div id="result-area" class="hidden">
        
        <div id="warning-banner" class="alert-warning hidden">
            <span style="font-size: 1.25rem;">⚠️</span>
            <span id="warning-text"></span>
        </div>
        
        <div id="info-banner" class="alert-info hidden">
            <span style="font-size: 1.25rem;">ℹ️</span>
            <span id="info-text"></span>
        </div>

        <div id="success-sections">
            <div class="summary-box">
                <h3>Ringkasan Analisis AI</h3>
                <p id="summary-text"></p>
            </div>
    
            <h3 class="section-title">Kandidat Klasifikasi HS Code</h3>
            <div id="hs-candidates"></div>
    
            <h3 class="section-title">Syarat Dokumen Kepatuhan</h3>
            <div id="compliance-checklist" class="card" style="padding: 0 24px;"></div>
        </div>
        
    </div>
</div>

<script>
document.getElementById('classify-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const deskripsi = document.getElementById('deskripsi_produk').value.trim();
    const negara = document.getElementById('negara_tujuan').value;
    
    if (!deskripsi || !negara) return;

    const btn = document.getElementById('submit-btn');
    const errorContainer = document.getElementById('error-container');
    const resultArea = document.getElementById('result-area');
    
    // Reset state
    btn.disabled = true;
    btn.textContent = 'Menganalisis...';
    errorContainer.classList.add('hidden');
    resultArea.classList.add('hidden');
    
    try {
        const response = await fetch('/api/classify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ deskripsi_produk: deskripsi, negara_tujuan: negara })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Terjadi kesalahan pada server');
        }
        
        renderResults(data);
        
    } catch (err) {
        errorContainer.textContent = err.message || 'Gagal terhubung ke server. Silakan coba lagi.';
        errorContainer.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Analisis Produk';
    }
});

function renderResults(data) {
    const resultArea = document.getElementById('result-area');
    const warningBanner = document.getElementById('warning-banner');
    const warningText = document.getElementById('warning-text');
    const infoBanner = document.getElementById('info-banner');
    const infoText = document.getElementById('info-text');
    const successSections = document.getElementById('success-sections');
    const summaryText = document.getElementById('summary-text');
    const hsContainer = document.getElementById('hs-candidates');
    const checklistContainer = document.getElementById('compliance-checklist');
    
    // Reset displays
    warningBanner.classList.add('hidden');
    infoBanner.classList.add('hidden');
    successSections.classList.remove('hidden');

    if (data.status === 'tidak_terdeteksi') {
        infoText.textContent = data.pesan;
        infoBanner.classList.remove('hidden');
        successSections.classList.add('hidden');
        resultArea.classList.remove('hidden');
        return;
    }
    
    // Warning
    if (data.peringatan_confidence) {
        warningText.textContent = data.peringatan_confidence;
        warningBanner.classList.remove('hidden');
    }
    
    // Summary
    summaryText.textContent = data.ringkasan;
    
    // HS Codes
    hsContainer.innerHTML = '';
    if (data.kandidat_hs_code && data.kandidat_hs_code.length > 0) {
        data.kandidat_hs_code.forEach(item => {
            const scorePercent = Math.round(item.skor_similarity * 100);
            const isHigh = scorePercent >= 75;
            const scoreClass = isHigh ? 'score-high' : 'score-low';
            
            const card = document.createElement('div');
            card.className = 'hs-card';
            card.innerHTML = `
                <div class="hs-header">
                    <span class="hs-code">${item.hs_code}</span>
                    <span class="hs-score ${scoreClass}">${scorePercent}% Match</span>
                </div>
                <div class="hs-desc">${item.deskripsi_resmi}</div>
                <div class="hs-reason">${item.alasan}</div>
            `;
            hsContainer.appendChild(card);
        });
    } else {
        hsContainer.innerHTML = '<p style="color: var(--text-muted)">Tidak ada kandidat yang ditemukan.</p>';
    }
    
    // Checklist
    checklistContainer.innerHTML = '';
    if (data.checklist_dokumen && data.checklist_dokumen.length > 0) {
        data.checklist_dokumen.forEach(item => {
            const isWajib = item.wajib_opsional.toLowerCase() === 'wajib';
            const badgeClass = isWajib ? 'badge-wajib' : 'badge-opsional';
            
            const row = document.createElement('div');
            row.className = 'checklist-item';
            row.innerHTML = `
                <span class="badge ${badgeClass}">${item.wajib_opsional}</span>
                <div class="doc-details">
                    <h4>${item.kategori_dokumen}</h4>
                    <p>${item.deskripsi}</p>
                    ${item.catatan ? `<p style="font-size: 0.85rem; font-style: italic; color: var(--text-muted);">Catatan: ${item.catatan}</p>` : ''}
                </div>
            `;
            checklistContainer.appendChild(row);
        });
    } else {
        checklistContainer.innerHTML = '<div style="padding: 24px 0;"><p style="color: var(--text-muted); margin: 0;">Tidak ada data regulasi untuk negara ini.</p></div>';
    }
    
    // Show results
    resultArea.classList.remove('hidden');
}
</script>

</body>
</html>
